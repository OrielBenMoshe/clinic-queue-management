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
        
        // Debug: Log received schedule data
        error_log('[ClinicQueue] Received schedule data: ' . print_r($schedule_data, true));
        
        // Validate required fields
        if (empty($schedule_data['days']) || !is_array($schedule_data['days'])) {
            wp_send_json_error('No working days provided');
            return;
        }
        
        if (empty($schedule_data['treatments']) || !is_array($schedule_data['treatments'])) {
            wp_send_json_error('No treatments provided');
            return;
        }
        
        // Determine post title: "יומן 🏥 [clinic_name] | [icon] [doctor_name/manual_name]"
        // Icon: 👨‍⚕️ for doctor, 📅 for manual calendar
        $post_title = 'יומן 🏥 ';
        
        // Get clinic name
        if (!empty($schedule_data['clinic_id'])) {
            $clinic = get_post($schedule_data['clinic_id']);
            $clinic_name = $clinic ? $clinic->post_title : 'מרפאה #' . $schedule_data['clinic_id'];
        } else {
            $clinic_name = 'לא ידוע';
        }
        
        $post_title .= $clinic_name . ' | ';
        
        // Get doctor/manual name with appropriate icon
        if (!empty($schedule_data['doctor_id'])) {
            // Has doctor - use doctor icon
            $doctor = get_post($schedule_data['doctor_id']);
            $doctor_name = $doctor ? $doctor->post_title : 'רופא #' . $schedule_data['doctor_id'];
            $post_title .= '👨‍⚕️ ' . $doctor_name;
        } elseif (!empty($schedule_data['manual_calendar_name'])) {
            // Manual calendar - use calendar icon
            $post_title .= '📅 ' . sanitize_text_field($schedule_data['manual_calendar_name']);
        } else {
            // Fallback
            $post_title .= '📅 ללא שם';
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
        // Validate schedule_type value - must be 'clinix' or 'google'
        $schedule_type = isset($schedule_data['action_type']) ? sanitize_text_field($schedule_data['action_type']) : '';
        if (!in_array($schedule_type, array('clinix', 'google'), true)) {
            error_log('[ClinicQueue] Invalid schedule_type value: ' . $schedule_type);
            $schedule_type = 'clinix'; // Default fallback
        }
        
        update_post_meta($post_id, 'schedule_type', $schedule_type);
        update_post_meta($post_id, 'clinic_id', sanitize_text_field($schedule_data['clinic_id']));
        update_post_meta($post_id, 'doctor_id', sanitize_text_field($schedule_data['doctor_id']));
        update_post_meta($post_id, 'manual_calendar_name', sanitize_text_field($schedule_data['manual_calendar_name']));
        
        // Debug: Verify saved value
        $saved_schedule_type = get_post_meta($post_id, 'schedule_type', true);
        error_log('[ClinicQueue] Saved schedule_type: ' . $saved_schedule_type . ' (Expected: ' . $schedule_type . ')');
        
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
        // Repeater field name: 'treatment_type'
        // Sub-fields: treatment_type (text), sub_speciality (term ID from glossary), cost (number), duration (number)
        // Note: JetEngine repeater field names must match exactly as defined in Meta Box
        $sanitized_treatments = array();
        foreach ($schedule_data['treatments'] as $treatment) {
            if (!empty($treatment['treatment_type'])) {
                $sanitized_treatments[] = array(
                    'treatment_type' => sanitize_text_field($treatment['treatment_type']),
                    'sub_speciality' => !empty($treatment['sub_speciality']) ? absint($treatment['sub_speciality']) : 0,
                    'cost' => absint($treatment['cost']),
                    'duration' => absint($treatment['duration'])
                );
            }
        }
        
        if (!empty($sanitized_treatments)) {
            // Save to the repeater field - the field name should match the JetEngine repeater name
            update_post_meta($post_id, 'treatments', $sanitized_treatments);
        }
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Schedule saved successfully',
            'post_id' => $post_id,
            'scheduler_id' => $post_id, // For Google Calendar integration
            'post_title' => $post_title
        ));
    }
}
