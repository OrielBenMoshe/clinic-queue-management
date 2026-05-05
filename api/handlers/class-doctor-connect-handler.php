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

        $working_days = $this->build_working_days_with_hours($scheduler_id);

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'scheduler_id'          => $scheduler_id,
                'schedule_name'         => (string) get_post_meta($scheduler_id, 'schedule_name', true),
                'manual_calendar_name'  => (string) get_post_meta($scheduler_id, 'manual_calendar_name', true),
                'schedule_type'         => (string) get_post_meta($scheduler_id, 'schedule_type', true),
                'doctor_connect_status' => (string) get_post_meta($scheduler_id, 'doctor_connect_status', true),
                'clinic_id'             => $clinic_id,
                'clinic_name'           => $clinic_name,
                'doctor_id'             => $doctor_id,
                'doctor_name'           => $doctor_name,
                'working_days'          => $working_days,
            ),
        ));
    }

    /**
     * Reject scheduler connection request: set status to 'rejected', notify owners, revoke token.
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

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Scheduler request rejected',
        ));
    }

    /**
     * Send doctor connect request: build secure link to the doctor-connect page
     * (page ID 5564) with scheduler parameters, save it to post meta, and return
     * the URL for the admin to copy and share with the doctor/practitioner.
     *
     * Flow:
     * 1. Validate scheduler post.
     * 2. Resolve clinic name, calendar name and working-days from scheduler meta.
     * 3. Check whether a doctor (WP user) is linked; derive their email if so.
     * 4. Generate / renew access token via the Doctor Connect Service.
     * 5. Build a signed URL pointing to page ID 5564 with all required params.
     * 6. Persist the new URL in the `doctor_connect_url` meta field.
     * 7. Return the URL to the front-end.
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

        // --- 1. Collect scheduler meta ---
        $doctor_id    = absint(get_post_meta($scheduler_id, 'doctor_id', true));
        $clinic_id    = absint(get_post_meta($scheduler_id, 'clinic_id', true));
        $working_days = get_post_meta($scheduler_id, 'working_days', true);
        if (!is_array($working_days)) {
            $working_days = array();
        }
        $manual_calendar_name = (string) get_post_meta($scheduler_id, 'manual_calendar_name', true);
        $schedule_name        = (string) get_post_meta($scheduler_id, 'schedule_name', true);

        // --- 2. Resolve display names ---
        $clinic_name = '';
        if ($clinic_id > 0) {
            $clinic_post = get_post($clinic_id);
            if ($clinic_post) {
                $clinic_name = html_entity_decode($clinic_post->post_title, ENT_QUOTES, 'UTF-8');
            }
        }

        // Prefer explicit schedule_name, fall back to manual calendar name
        $calendar_name = $schedule_name ?: $manual_calendar_name;

        // --- 3. Resolve doctor's WordPress user email (if doctor is linked) ---
        $doctor_email = '';
        if ($doctor_id > 0) {
            $doctor_post = get_post($doctor_id);
            if ($doctor_post && $doctor_post->post_author > 0) {
                $doctor_user  = get_userdata($doctor_post->post_author);
                $doctor_email = $doctor_user ? $doctor_user->user_email : '';
            }
        }

        // --- 4. Generate / renew access token ---
        $link_result = Clinic_Queue_Doctor_Connect_Service::generate_connect_request_link($scheduler_id, 14);
        if (is_wp_error($link_result)) {
            return $this->error_response($link_result->get_error_message(), 500, 'connect_link_failed');
        }
        $token      = $link_result['token'];
        $expires_at = $link_result['expires_at'];

        // --- 5. Build signed URL to the doctor-connect page (ID 5564) ---
        // Working-days are omitted from the URL; the page fetches them live via REST.
        $connect_url = $this->build_doctor_connect_page_url(
            $scheduler_id,
            $token,
            $clinic_name,
            $calendar_name
        );

        // --- 6. Persist the new URL in post meta ---
        update_post_meta($scheduler_id, 'doctor_connect_url', esc_url_raw($connect_url));

        // --- 7. Send connect request email to the doctor ---
        $email_result    = Clinic_Queue_Doctor_Connect_Service::send_connect_request_email($scheduler_id, $connect_url);
        $email_sent      = !is_wp_error($email_result);
        $email_error     = $email_sent ? null : $email_result->get_error_message();
        $email_recipients = $email_sent ? $email_result['recipients'] : array();

        // --- 8. Return URL and email status to front-end ---
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'קישור חיבור ליומן נוצר בהצלחה',
            'data'    => array(
                'scheduler_id'              => $scheduler_id,
                'doctor_connect_url'        => $connect_url,
                'doctor_connect_expires_at' => $expires_at,
                'doctor_email'              => $doctor_email,
                'email_sent'                => $email_sent,
                'email_recipients'          => $email_recipients,
                'email_error'               => $email_error,
            ),
        ));
    }

    /**
     * Build a signed URL to the doctor-connect page (WP page ID 5564) including
     * all parameters needed for the Google Calendar connection flow and for
     * displaying scheduler details to the doctor/practitioner.
     *
     * Working-days are intentionally omitted from the URL because the page
     * fetches them live via REST from the scheduler post meta.
     *
     * Security: a HMAC-SHA256 signature (sig) covers scheduler_id + token so
     * that neither value can be tampered with undetected.
     *
     * @param int    $scheduler_id  Scheduler post ID.
     * @param string $token         Raw access token.
     * @param string $clinic_name   Human-readable clinic name.
     * @param string $calendar_name Human-readable calendar / schedule name.
     * @return string Signed URL.
     */
    private function build_doctor_connect_page_url($scheduler_id, $token, $clinic_name, $calendar_name) {
        $page_url = get_permalink(5564);
        if (!$page_url) {
            $page_url = home_url('/?page_id=5564');
        }

        $sig = hash_hmac(
            'sha256',
            absint($scheduler_id) . '|' . $token,
            wp_salt('auth')
        );

        return add_query_arg(
            array(
                'scheduler_id'  => absint($scheduler_id),
                'token'         => rawurlencode($token),
                'clinic_name'   => rawurlencode($clinic_name),
                'calendar_name' => rawurlencode($calendar_name),
                'sig'           => $sig,
            ),
            $page_url
        );
    }

    /**
     * Build a structured working-days array with per-day time ranges.
     *
     * WordPress stores two separate pieces of data:
     * - `working_days` post meta: flat array of short day codes e.g. ['sun', 'mon', 'wed']
     * - Per-day post metas ('sunday', 'monday'…): arrays of {start_time, end_time} ranges
     *
     * This method merges them into a single keyed object:
     *   { sunday: [{start_time: '09:00', end_time: '17:00'}], monday: [...], ... }
     *
     * @param int $scheduler_id Scheduler post ID.
     * @return array Keyed by full English day name; values are arrays of time-range objects.
     */
    private function build_working_days_with_hours($scheduler_id) {
        $short_to_full = array(
            'sun' => 'sunday',
            'mon' => 'monday',
            'tue' => 'tuesday',
            'wed' => 'wednesday',
            'thu' => 'thursday',
            'fri' => 'friday',
            'sat' => 'saturday',
        );

        $active_days = get_post_meta($scheduler_id, 'working_days', true);
        if (!is_array($active_days)) {
            $active_days = array();
        }

        $result = array();
        foreach ($active_days as $short_day) {
            if (!isset($short_to_full[ $short_day ])) {
                continue;
            }
            $full_day    = $short_to_full[ $short_day ];
            $time_ranges = get_post_meta($scheduler_id, $full_day, true);
            if (!is_array($time_ranges)) {
                $time_ranges = array();
            }
            $result[ $full_day ] = array_values(
                array_filter(
                    $time_ranges,
                    static function ($r) {
                        return !empty($r['start_time']) && !empty($r['end_time']);
                    }
                )
            );
        }

        return $result;
    }
}
