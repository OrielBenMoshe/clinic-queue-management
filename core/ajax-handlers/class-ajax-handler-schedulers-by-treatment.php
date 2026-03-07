<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Get schedulers by treatment type (booking calendar)
 *
 * @deprecated The booking calendar shortcode now filters schedulers client-side in JavaScript.
 * This endpoint is kept for backward compatibility.
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Schedulers_By_Treatment {

    /**
     * Callback for wp_ajax_clinic_queue_get_schedulers_by_treatment and nopriv
     */
    public static function handle() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        $treatment_type = isset($_POST['treatment_type']) ? sanitize_text_field($_POST['treatment_type']) : '';
        $clinic_id = isset($_POST['clinic_id']) ? intval($_POST['clinic_id']) : null;
        $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;

        if (empty($treatment_type)) {
            wp_send_json_error(array('message' => 'Missing treatment_type parameter'));
            return;
        }

        $filter_engine_path = CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/booking-calendar/managers/class-calendar-filter-engine.php';
        if (file_exists($filter_engine_path) && !class_exists('Booking_Calendar_Filter_Engine')) {
            require_once $filter_engine_path;
        }

        $schedulers = array();
        if (class_exists('Booking_Calendar_Filter_Engine')) {
            $shortcode_filter_engine = Booking_Calendar_Filter_Engine::get_instance();
            $schedulers = $shortcode_filter_engine->get_schedulers_by_treatment_type($treatment_type, $clinic_id, $doctor_id);
        } else {
            wp_send_json_error(array('message' => 'Filter engine not available'));
            return;
        }

        $response_data = array('schedulers' => $schedulers);

        if (empty($schedulers)) {
            $scheduler_ids_from_relations = array();
            if (!empty($doctor_id) && is_numeric($doctor_id) && class_exists('Clinic_Queue_API_Manager')) {
                $api_manager = Clinic_Queue_API_Manager::get_instance();
                $scheduler_ids_from_relations = $api_manager->get_scheduler_ids_by_doctor($doctor_id);
            } elseif (!empty($clinic_id) && is_numeric($clinic_id) && class_exists('Clinic_Queue_API_Manager')) {
                $api_manager = Clinic_Queue_API_Manager::get_instance();
                $scheduler_ids_from_relations = $api_manager->get_scheduler_ids_by_clinic($clinic_id);
            }
            $response_data['debug'] = array(
                'treatment_type' => $treatment_type,
                'clinic_id' => $clinic_id,
                'doctor_id' => $doctor_id,
                'scheduler_ids_from_relations' => $scheduler_ids_from_relations,
                'scheduler_ids_count' => count($scheduler_ids_from_relations),
                'message' => 'No schedulers found. Check relations and treatment_type matching.'
            );
        }

        wp_send_json_success($response_data);
    }
}
