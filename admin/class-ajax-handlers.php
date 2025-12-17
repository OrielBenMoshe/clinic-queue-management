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
        // Test API connection (for admin)
        add_action('wp_ajax_clinic_queue_test_api', array($this, 'ajax_test_api'));
        
        // Save clinic schedule (for logged-in users)
        add_action('wp_ajax_save_clinic_schedule', array($this, 'ajax_save_clinic_schedule'));
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
        
        // Test API by fetching sample data
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
    
    /**
     * AJAX: Save clinic schedule
     * Creates a new schedule post with working hours and treatments
     */
    public function ajax_save_clinic_schedule() {
        // Verify nonce
        check_ajax_referer('save_clinic_schedule', 'nonce');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
            return;
        }
        
        // Get and decode schedule data
        $schedule_data_json = isset($_POST['schedule_data']) ? wp_unslash($_POST['schedule_data']) : '';
        $schedule_data = json_decode($schedule_data_json, true);
        
        if (!$schedule_data) {
            wp_send_json_error('Invalid schedule data');
            return;
        }
        
        // Validate required fields
        if (empty($schedule_data['days']) || !is_array($schedule_data['days'])) {
            wp_send_json_error('No working days provided');
            return;
        }
        
        if (empty($schedule_data['treatments']) || !is_array($schedule_data['treatments'])) {
            wp_send_json_error('No treatments provided');
            return;
        }
        
        // Determine post title
        $post_title = 'יומן של ';
        if (!empty($schedule_data['manual_calendar_name'])) {
            $post_title .= sanitize_text_field($schedule_data['manual_calendar_name']);
        } elseif (!empty($schedule_data['doctor_id'])) {
            $doctor = get_post($schedule_data['doctor_id']);
            $post_title .= $doctor ? $doctor->post_title : 'רופא #' . $schedule_data['doctor_id'];
        } else {
            $post_title .= 'ללא שם';
        }
        
        // Create schedule post
        $post_data = array(
            'post_type' => 'schedules',
            'post_title' => $post_title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create schedule post: ' . $post_id->get_error_message());
            return;
        }
        
        // Save meta fields
        update_post_meta($post_id, 'schedule_type', sanitize_text_field($schedule_data['action_type']));
        update_post_meta($post_id, 'clinic_id', sanitize_text_field($schedule_data['clinic_id']));
        update_post_meta($post_id, 'doctor_id', sanitize_text_field($schedule_data['doctor_id']));
        update_post_meta($post_id, 'manual_calendar_name', sanitize_text_field($schedule_data['manual_calendar_name']));
        
        // Save working days and time ranges (as JetEngine repeater format)
        $days_mapping = array(
            'sunday' => 'יום ראשון',
            'monday' => 'יום שני',
            'tuesday' => 'יום שלישי',
            'wednesday' => 'יום רביעי',
            'thursday' => 'יום חמישי',
            'friday' => 'יום שישי',
            'saturday' => 'יום שבת'
        );
        
        foreach ($schedule_data['days'] as $day => $time_ranges) {
            if (!is_array($time_ranges)) {
                continue;
            }
            
            $sanitized_ranges = array();
            foreach ($time_ranges as $range) {
                if (isset($range['start_time']) && isset($range['end_time'])) {
                    $sanitized_ranges[] = array(
                        'start_time' => sanitize_text_field($range['start_time']),
                        'end_time' => sanitize_text_field($range['end_time'])
                    );
                }
            }
            
            if (!empty($sanitized_ranges)) {
                update_post_meta($post_id, sanitize_key($day), $sanitized_ranges);
            }
        }
        
        // Save treatments (as JetEngine repeater format)
        $sanitized_treatments = array();
        foreach ($schedule_data['treatments'] as $treatment) {
            if (!empty($treatment['name'])) {
                $sanitized_treatments[] = array(
                    'treatment_name' => sanitize_text_field($treatment['name']),
                    'subspeciality' => sanitize_text_field($treatment['subspeciality']),
                    'price' => absint($treatment['price']),
                    'duration' => absint($treatment['duration'])
                );
            }
        }
        
        if (!empty($sanitized_treatments)) {
            update_post_meta($post_id, 'treatments', $sanitized_treatments);
        }
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Schedule saved successfully',
            'post_id' => $post_id,
            'post_title' => $post_title
        ));
    }
}
