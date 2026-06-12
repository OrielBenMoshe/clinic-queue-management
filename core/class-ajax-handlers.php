<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handlers Registry
 * Loads handler classes and registers WordPress AJAX actions.
 *
 * @package Clinic_Queue_Management
 * @subpackage Core
 */
class Clinic_Queue_Ajax_Handlers {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->load_handlers();
        $this->register_hooks();
    }

    /**
     * Load individual AJAX handler classes
     */
    private function load_handlers() {
        $base = CLINIC_QUEUE_MANAGEMENT_PATH . 'core/ajax-handlers/';
        require_once $base . 'class-ajax-handler-test-api.php';
        require_once $base . 'class-ajax-handler-save-schedule.php';
        require_once $base . 'class-ajax-handler-detach-doctor-from-clinic.php';
        require_once $base . 'class-ajax-handler-freeze-schedule.php';
        require_once $base . 'class-ajax-handler-activate-schedule.php';
        require_once $base . 'class-ajax-handler-delete-schedule.php';
    }

    /**
     * Register all AJAX hooks (delegate to handler classes)
     */
    private function register_hooks() {
        add_action('wp_ajax_clinic_queue_test_api', array('Clinic_Queue_Ajax_Handler_Test_Api', 'handle'));

        add_action('wp_ajax_save_clinic_schedule', array('Clinic_Queue_Ajax_Handler_Save_Schedule', 'handle'));

        add_action('wp_ajax_create_schedule_from_temp', array('Clinic_Queue_Ajax_Handler_Save_Schedule', 'handle_from_temp'));

        add_action('wp_ajax_clinic_queue_detach_doctor_from_clinic', array('Clinic_Queue_Ajax_Handler_Detach_Doctor_From_Clinic', 'handle'));

        add_action('wp_ajax_clinic_queue_freeze_schedule', array('Clinic_Queue_Ajax_Handler_Freeze_Schedule', 'handle'));

        add_action('wp_ajax_clinic_queue_activate_schedule', array('Clinic_Queue_Ajax_Handler_Activate_Schedule', 'handle'));

        add_action('wp_ajax_clinic_queue_delete_schedule', array('Clinic_Queue_Ajax_Handler_Delete_Schedule', 'handle'));
    }
}
