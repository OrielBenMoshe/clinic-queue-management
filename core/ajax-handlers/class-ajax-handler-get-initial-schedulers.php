<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Get Initial Schedulers
 *
 * Fallback for booking calendar widgets that could not receive per-widget data
 * via the inline <script> tag (e.g. aggressive page caching edge cases).
 *
 * Action: clinic_queue_get_initial_schedulers
 * Access: logged-in and guests (frontend booking widget)
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\AjaxHandlers
 */
class Clinic_Queue_Ajax_Handler_Get_Initial_Schedulers {

    /**
     * Handle the AJAX request.
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_ajax', 'nonce');

        $clinic_id = isset($_POST['clinic_id']) ? absint($_POST['clinic_id']) : 0;
        $doctor_id = isset($_POST['doctor_id']) ? absint($_POST['doctor_id']) : 0;

        if (!class_exists('Clinic_Queue_API_Manager')) {
            wp_send_json_success(array('schedulers' => array()));
        }

        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $schedulers_raw = array();

        if ($clinic_id > 0) {
            $schedulers_raw = $api_manager->get_schedulers_by_clinic($clinic_id);
        } elseif ($doctor_id > 0) {
            $schedulers_raw = $api_manager->get_schedulers_by_doctor($doctor_id);
        }

        // Convert associative [id => data] to indexed array with 'id' field (same as PHP render).
        $schedulers = array();
        if (!empty($schedulers_raw) && is_array($schedulers_raw)) {
            foreach ($schedulers_raw as $scheduler_id => $scheduler_data) {
                if (is_array($scheduler_data)) {
                    $scheduler_data['id'] = $scheduler_id;
                    $schedulers[] = $scheduler_data;
                }
            }
        }

        wp_send_json_success(array('schedulers' => $schedulers));
    }
}
