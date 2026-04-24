<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-doctor-connect-service.php';

/**
 * Doctor Connect Handler
 * Handles doctor-side scheduler approval/rejection endpoints.
 *
 * @package ClinicQueue
 * @subpackage API\Handlers
 */
class Clinic_Queue_Doctor_Connect_Handler extends Clinic_Queue_Base_Handler {

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/doctor/scheduler-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_scheduler_info'),
            'permission_callback' => array($this, 'permission_callback_scheduler_access'),
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'access_token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/doctor/reject-scheduler', array(
            'methods' => 'POST',
            'callback' => array($this, 'reject_scheduler'),
            'permission_callback' => array($this, 'permission_callback_scheduler_access'),
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'access_token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'reason' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/doctor/send-connect-request', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_connect_request'),
            'permission_callback' => array($this, 'permission_callback_scheduler_access'),
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'access_token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Get scheduler info for doctor confirmation view.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_scheduler_info($request) {
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        $post = get_post($scheduler_id);

        if (!$post || $post->post_type !== 'schedules') {
            return $this->error_response('Scheduler not found', 404, 'invalid_scheduler');
        }

        $clinic_id = absint(get_post_meta($scheduler_id, 'clinic_id', true));
        $doctor_id = absint(get_post_meta($scheduler_id, 'doctor_id', true));

        $clinic_name = '';
        if ($clinic_id > 0) {
            $clinic_post = get_post($clinic_id);
            $clinic_name = $clinic_post ? $clinic_post->post_title : '';
        }

        $doctor_name = '';
        if ($doctor_id > 0) {
            $doctor_post = get_post($doctor_id);
            $doctor_name = $doctor_post ? $doctor_post->post_title : '';
        }

        $working_days = get_post_meta($scheduler_id, 'working_days', true);
        if (!is_array($working_days)) {
            $working_days = array();
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'scheduler_id' => $scheduler_id,
                'schedule_name' => (string) get_post_meta($scheduler_id, 'schedule_name', true),
                'manual_calendar_name' => (string) get_post_meta($scheduler_id, 'manual_calendar_name', true),
                'schedule_type' => (string) get_post_meta($scheduler_id, 'schedule_type', true),
                'doctor_connect_status' => (string) get_post_meta($scheduler_id, 'doctor_connect_status', true),
                'clinic_id' => $clinic_id,
                'clinic_name' => $clinic_name,
                'doctor_id' => $doctor_id,
                'doctor_name' => $doctor_name,
                'working_days' => $working_days,
            ),
        ));
    }

    /**
     * Reject scheduler connection request, notify owners, then delete scheduler.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function reject_scheduler($request) {
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        $reason = $this->get_string_param($request, 'reason', '');

        $post = get_post($scheduler_id);
        if (!$post || $post->post_type !== 'schedules') {
            return $this->error_response('Scheduler not found', 404, 'invalid_scheduler');
        }

        update_post_meta($scheduler_id, 'doctor_connect_status', 'rejected');
        Clinic_Queue_Doctor_Connect_Service::send_rejection_email($scheduler_id, $reason);
        Clinic_Queue_Doctor_Connect_Service::revoke_token($scheduler_id);

        $deleted = wp_delete_post($scheduler_id, true);
        if (!$deleted) {
            return $this->error_response('Failed to delete scheduler', 500, 'delete_failed');
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Scheduler request rejected and deleted',
        ));
    }

    /**
     * Send doctor connect request email and refresh connect link metadata.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function send_connect_request($request) {
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        $post = get_post($scheduler_id);
        if (!$post || $post->post_type !== 'schedules') {
            return $this->error_response('Scheduler not found', 404, 'invalid_scheduler');
        }

        $link_result = Clinic_Queue_Doctor_Connect_Service::generate_connect_request_link($scheduler_id, 14);
        if (is_wp_error($link_result)) {
            return $this->error_response($link_result->get_error_message(), 500, 'connect_link_failed');
        }

        $mail_result = Clinic_Queue_Doctor_Connect_Service::send_connect_request_email($scheduler_id, $link_result['connect_url']);
        if (is_wp_error($mail_result)) {
            return $this->error_response($mail_result->get_error_message(), 500, 'connect_request_email_failed');
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'בקשת הסנכרון נשלחה לרופא בהצלחה',
            'data' => array(
                'scheduler_id' => $scheduler_id,
                'doctor_connect_url' => $link_result['connect_url'],
                'doctor_connect_expires_at' => $link_result['expires_at'],
                'recipients' => isset($mail_result['recipients']) ? $mail_result['recipients'] : array(),
            ),
        ));
    }
}
