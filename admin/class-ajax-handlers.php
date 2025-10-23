<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handlers for Clinic Queue Management
 * Handles all AJAX endpoints for admin functionality
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
        $this->register_hooks();
    }
    
    /**
     * Register all AJAX hooks
     */
    private function register_hooks() {
        // Dashboard AJAX handlers
        add_action('wp_ajax_clinic_queue_sync_all', array($this, 'ajax_sync_all_calendars'));
        add_action('wp_ajax_clinic_queue_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_clinic_queue_generate_appointments', array($this, 'ajax_generate_appointments'));
        
        // Cron Jobs AJAX handlers
        add_action('wp_ajax_clinic_queue_run_cron_task', array($this, 'ajax_run_cron_task'));
        add_action('wp_ajax_clinic_queue_reset_cron', array($this, 'ajax_reset_cron'));
        
        // Additional AJAX handlers
        add_action('wp_ajax_clinic_queue_sync_calendar', array($this, 'ajax_sync_calendar'));
        add_action('wp_ajax_clinic_queue_delete_calendar', array($this, 'ajax_delete_calendar'));
        add_action('wp_ajax_clinic_queue_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_clinic_queue_get_calendar_details', array($this, 'ajax_get_calendar_details'));
    }
    
    /**
     * AJAX: Sync all calendars
     */
    public function ajax_sync_all_calendars() {
        check_ajax_referer('clinic_queue_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $result = $api_manager->schedule_auto_sync();
        
        if ($result) {
            wp_send_json_success('All calendars synced successfully');
        } else {
            wp_send_json_error('Failed to sync calendars');
        }
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('clinic_queue_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $api_manager->clear_cache();
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    /**
     * AJAX: Generate new appointments
     */
    public function ajax_generate_appointments() {
        check_ajax_referer('clinic_queue_generate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $appointment_manager = Clinic_Queue_Appointment_Manager::get_instance();
        
        // Get all calendars
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $calendars = $wpdb->get_results("SELECT id FROM $table_calendars");
        
        $generated = 0;
        foreach ($calendars as $calendar) {
            $appointment_manager->generate_future_appointments($calendar->id, 3);
            $generated++;
        }
        
        wp_send_json_success("Generated appointments for $generated calendars");
    }
    
    /**
     * AJAX: Run specific cron task
     */
    public function ajax_run_cron_task() {
        check_ajax_referer('clinic_queue_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $task = sanitize_text_field($_POST['task']);
        $cron_manager = Clinic_Queue_Cron_Manager::get_instance();
        
        switch ($task) {
            case 'auto_sync':
                $cron_manager->run_auto_sync_task();
                wp_send_json_success('Auto sync completed');
                break;
                
            case 'cleanup':
                $cron_manager->run_cleanup_task();
                wp_send_json_success('Cleanup completed');
                break;
                
            case 'extend_calendars':
                $cron_manager->run_extend_calendars_task();
                wp_send_json_success('Extend calendars completed');
                break;
                
            default:
                wp_send_json_error('Unknown task');
        }
    }
    
    /**
     * AJAX: Reset all cron jobs
     */
    public function ajax_reset_cron() {
        check_ajax_referer('clinic_queue_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $cron_manager = Clinic_Queue_Cron_Manager::get_instance();
        $cron_manager->reset_all_cron_jobs();
        
        wp_send_json_success('All cron jobs reset successfully');
    }
    
    /**
     * AJAX: Sync specific calendar
     */
    public function ajax_sync_calendar() {
        check_ajax_referer('clinic_queue_sync_calendar', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $doctor_id = sanitize_text_field($_POST['doctor_id']);
        $clinic_id = sanitize_text_field($_POST['clinic_id']);
        $treatment_type = sanitize_text_field($_POST['treatment_type']);
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $result = $api_manager->sync_from_api($doctor_id, $clinic_id, $treatment_type);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Delete calendar
     */
    public function ajax_delete_calendar() {
        check_ajax_referer('clinic_queue_delete_calendar', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $calendar_id = intval($_POST['calendar_id']);
        
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        $deleted = $wpdb->delete(
            $table_calendars,
            array('id' => $calendar_id),
            array('%d')
        );
        
        if ($deleted) {
            wp_send_json_success('Calendar deleted successfully');
        } else {
            wp_send_json_error('Failed to delete calendar');
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('clinic_queue_test_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $result = $api_manager->test_api_connection('mock');
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get calendar details for dialog
     */
    public function ajax_get_calendar_details() {
        check_ajax_referer('clinic_queue_view_calendar', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $calendar_id = intval($_POST['calendar_id']);
        
        if (!$calendar_id) {
            wp_send_json_error('Invalid calendar ID');
            return;
        }
        
        // Get calendar details
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $calendar = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars WHERE id = %d",
            $calendar_id
        ));
        
        if (!$calendar) {
            wp_send_json_error('Calendar not found');
            return;
        }
        
        // Get appointments data
        $appointments_data = $this->get_calendar_appointments_for_dialog($calendar_id);
        
        // Debug: Print data to console
        error_log('Calendar ID: ' . $calendar_id);
        error_log('Calendar data: ' . print_r($calendar, true));
        error_log('Appointments data: ' . print_r($appointments_data, true));
        
        // Generate HTML content
        $html = $this->generate_calendar_dialog_html($calendar, $appointments_data);
        
        wp_send_json_success($html);
    }
    
    /**
     * Get calendar appointments for dialog
     */
    private function get_calendar_appointments_for_dialog($calendar_id) {
        global $wpdb;
        
        // First, get calendar info
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $calendar = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars WHERE id = %d",
            $calendar_id
        ));
        
        if (!$calendar) {
            error_log('Calendar not found for ID: ' . $calendar_id);
            return array();
        }
        
        // Try to get data from mock data first
        $mock_data = $this->get_mock_data_for_calendar($calendar->doctor_id, $calendar->clinic_id, $calendar->treatment_type);
        
        if ($mock_data && isset($mock_data['days'])) {
            error_log('Using mock data for calendar ' . $calendar_id);
            return $this->convert_mock_data_to_appointments_format($mock_data);
        }
        
        // Fallback to database
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        
        // Get dates for the next 4 weeks
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+4 weeks'));
        
        $dates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_dates 
             WHERE calendar_id = %d 
             AND appointment_date >= %s 
             AND appointment_date <= %s 
             ORDER BY appointment_date ASC",
            $calendar_id, $start_date, $end_date
        ));
        
        $appointments_data = array();
        
        error_log('Found ' . count($dates) . ' dates for calendar ' . $calendar_id);
        
        // If no dates found, try to sync data first
        if (empty($dates)) {
            error_log('No dates found for calendar ' . $calendar_id . ', attempting to sync...');
            
            // Try to sync data
            $api_manager = Clinic_Queue_API_Manager::get_instance();
            $sync_result = $api_manager->sync_from_api(
                $calendar->doctor_id, 
                $calendar->clinic_id, 
                $calendar->treatment_type
            );
            
            if ($sync_result['success']) {
                error_log('Sync successful, retrying to get dates...');
                // Try again to get dates
                $dates = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_dates 
                     WHERE calendar_id = %d 
                     AND appointment_date >= %s 
                     AND appointment_date <= %s 
                     ORDER BY appointment_date ASC",
                    $calendar_id, $start_date, $end_date
                ));
                error_log('After sync: Found ' . count($dates) . ' dates for calendar ' . $calendar_id);
            }
        }
        
        foreach ($dates as $date) {
            $time_slots = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_times 
                 WHERE date_id = %d 
                 ORDER BY time_slot ASC",
                $date->id
            ));
            
            error_log('Date: ' . $date->appointment_date . ' has ' . count($time_slots) . ' time slots');
            error_log('Time slots for date ' . $date->appointment_date . ': ' . print_r($time_slots, true));
            
            $appointments_data[] = array(
                'date' => $date,
                'time_slots' => $time_slots
            );
        }
        
        error_log('Final appointments data structure: ' . print_r($appointments_data, true));
        
        return $appointments_data;
    }
    
    /**
     * Get mock data for specific calendar
     */
    private function get_mock_data_for_calendar($doctor_id, $clinic_id, $treatment_type) {
        $json_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return null;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return null;
        }
        
        // Find the specific calendar
        foreach ($data['calendars'] as $calendar) {
            if ($calendar['doctor_id'] == $doctor_id && 
                $calendar['clinic_id'] == $clinic_id && 
                $calendar['treatment_type'] == $treatment_type) {
                return $calendar;
            }
        }
        
        return null;
    }
    
    /**
     * Convert mock data to appointments format
     */
    private function convert_mock_data_to_appointments_format($mock_data) {
        $appointments_data = array();
        
        if (!isset($mock_data['appointments'])) {
            return $appointments_data;
        }
        
        foreach ($mock_data['appointments'] as $date => $slots) {
            // Create a mock date object
            $date_obj = new stdClass();
            $date_obj->id = 0; // Mock ID
            $date_obj->appointment_date = $date;
            $date_obj->calendar_id = 0; // Mock calendar ID
            
            $time_slots = array();
            foreach ($slots as $slot) {
                $time_slot = new stdClass();
                $time_slot->id = 0; // Mock ID
                $time_slot->time_slot = $slot['time'];
                $time_slot->is_booked = $slot['is_booked'];
                $time_slot->date_id = 0; // Mock date ID
                $time_slots[] = $time_slot;
            }
            
            $appointments_data[] = array(
                'date' => $date_obj,
                'time_slots' => $time_slots
            );
        }
        
        return $appointments_data;
    }
    
    /**
     * Generate calendar dialog HTML
     */
    private function generate_calendar_dialog_html($calendar, $appointments_data) {
        // Include the view file
        $view_file = plugin_dir_path(__FILE__) . 'views/calendar-dialog-html.php';
        
        if (file_exists($view_file)) {
            include_once $view_file;
            return cqm_generate_calendar_dialog_html($calendar, $appointments_data);
        }
        
        // Fallback if view file doesn't exist
        return '<div class="error">קובץ התצוגה לא נמצא</div>';
    }
}
