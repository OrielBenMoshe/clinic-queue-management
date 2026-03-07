<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Test API connection (admin)
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Test_Api {

    /**
     * Callback for wp_ajax_clinic_queue_test_api
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_test_api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $test_data = $api_manager->get_appointments_data(null, '1', '1', 'רפואה כללית');

        if ($test_data) {
            wp_send_json_success(array(
                'message' => 'API connection successful',
                'has_data' => !empty($test_data['days'])
            ));
        } else {
            wp_send_json_error('API connection failed or no data returned');
        }
    }
}
