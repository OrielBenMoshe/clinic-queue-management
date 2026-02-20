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
        
        // Get schedulers by treatment type (for booking calendar shortcode)
        add_action('wp_ajax_clinic_queue_get_schedulers_by_treatment', array($this, 'ajax_get_schedulers_by_treatment'));
        add_action('wp_ajax_nopriv_clinic_queue_get_schedulers_by_treatment', array($this, 'ajax_get_schedulers_by_treatment'));
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
        
        // Schedule data received (no server-side logging)
        
        // Validate required fields
        if (empty($schedule_data['days']) || !is_array($schedule_data['days'])) {
            wp_send_json_error('No working days provided');
            return;
        }
        
        if (empty($schedule_data['treatments']) || !is_array($schedule_data['treatments'])) {
            wp_send_json_error('No treatments provided');
            return;
        }
        
        $action_type = isset($schedule_data['action_type']) ? sanitize_text_field($schedule_data['action_type']) : '';

        // Validate identity: Google flow needs doctor or manual name; Clinix needs selected_calendar_id
        if ($action_type === 'clinix') {
            if (empty($schedule_data['selected_calendar_id'])) {
                wp_send_json_error('חסר יומן מקור. אנא בחר יומן.');
                return;
            }
        } else {
            if (empty($schedule_data['doctor_id']) && empty($schedule_data['manual_calendar_name'])) {
                wp_send_json_error('חובה לבחור רופא או להזין שם יומן');
                return;
            }
        }

        // Build post title: same format for both flows when clinic/doctor/name exist
        $has_clinic_or_doctor = !empty($schedule_data['doctor_id']) || !empty($schedule_data['manual_calendar_name']);
        if ($has_clinic_or_doctor) {
            $post_title_suffix = 'יומן 🏥 ';
            if (!empty($schedule_data['clinic_id'])) {
                $clinic = get_post($schedule_data['clinic_id']);
                $clinic_name = $clinic ? html_entity_decode($clinic->post_title, ENT_QUOTES, 'UTF-8') : 'מרפאה #' . $schedule_data['clinic_id'];
            } else {
                $clinic_name = 'לא ידוע';
            }
            $post_title_suffix .= $clinic_name . ' | ';
            if (!empty($schedule_data['doctor_id'])) {
                $doctor = get_post($schedule_data['doctor_id']);
                $doctor_name = $doctor ? html_entity_decode($doctor->post_title, ENT_QUOTES, 'UTF-8') : 'רופא #' . $schedule_data['doctor_id'];
                $post_title_suffix .= '👨‍⚕️ ' . $doctor_name;
            } elseif (!empty($schedule_data['manual_calendar_name'])) {
                $post_title_suffix .= '📅 ' . sanitize_text_field($schedule_data['manual_calendar_name']);
            }
        } elseif ($action_type === 'clinix' && !empty($schedule_data['selected_calendar_id'])) {
            $post_title_suffix = 'יומן קליניקס | ' . sanitize_text_field($schedule_data['selected_calendar_id']);
        } else {
            $post_title_suffix = 'יומן 🏥 לא ידוע';
        }
        
        // Create schedule post
        $post_data = array(
            'post_type' => 'schedules',
            'post_title' => $post_title_suffix,
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
            $schedule_type = 'clinix'; // Default fallback
        }
        
        update_post_meta($post_id, 'schedule_type', $schedule_type);
        update_post_meta($post_id, 'clinic_id', isset($schedule_data['clinic_id']) ? sanitize_text_field($schedule_data['clinic_id']) : '');
        update_post_meta($post_id, 'doctor_id', isset($schedule_data['doctor_id']) ? sanitize_text_field($schedule_data['doctor_id']) : '');

        if ($action_type === 'clinix') {
            update_post_meta($post_id, 'clinix_source_calendar_id', sanitize_text_field($schedule_data['selected_calendar_id']));
            if (!empty($schedule_data['add_api'])) {
                update_post_meta($post_id, 'clinix_api_token', sanitize_text_field($schedule_data['add_api']));
            }
        }
        
        // Handle manual calendar name
        if (!empty($schedule_data['manual_calendar_name'])) {
            $manual_name = sanitize_text_field($schedule_data['manual_calendar_name']);
            update_post_meta($post_id, 'manual_calendar_name', $manual_name);
            update_post_meta($post_id, 'schedule_name', $manual_name);
        }
        
        // Schedule type saved (no server-side logging)
        
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
        // Repeater field: treatments. Sub-fields: clinix_treatment_name, clinix_treatment_id, treatment_type (term ID), cost, duration
        $sanitized_treatments = array();
        foreach ($schedule_data['treatments'] as $treatment) {
            $sanitized_treatments[] = array(
                'clinix_treatment_name' => isset($treatment['clinix_treatment_name']) ? sanitize_text_field($treatment['clinix_treatment_name']) : '',
                'clinix_treatment_id'   => isset($treatment['clinix_treatment_id']) ? sanitize_text_field($treatment['clinix_treatment_id']) : '',
                'treatment_type'        => !empty($treatment['treatment_type']) ? absint($treatment['treatment_type']) : 0,
                'cost'                  => isset($treatment['cost']) ? absint($treatment['cost']) : 0,
                'duration'              => isset($treatment['duration']) ? absint($treatment['duration']) : 0
            );
        }

        if (!empty($sanitized_treatments)) {
            update_post_meta($post_id, 'treatments', $sanitized_treatments);
        }
        
        // Create JetEngine Relations
        // יצירת קשרים בין היומן למרפאה ורופא
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/services/class-relations-service.php';
        $relations_service = Relations_Service::get_instance();
        $relations_result = $relations_service->create_scheduler_relations($post_id);
        
        if (!$relations_result['success']) {
            // לא נכשיל את כל הפעולה בגלל Relations
        }
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Schedule saved successfully',
            'post_id' => $post_id,
            'scheduler_id' => $post_id, // For Google Calendar integration
            'post_title' => $post_title_suffix,
            'relations_created' => $relations_result['success']
        ));
    }
    
    /**
     * AJAX: Get schedulers by treatment type
     * Returns all schedulers (schedules post type) that have the specified treatment_type
     * 
     * @deprecated The booking calendar shortcode now filters schedulers client-side in JavaScript
     * for better performance. This AJAX endpoint is kept for backward compatibility but is no longer
     * called by the booking calendar shortcode.
     * 
     * @return void
     */
    public function ajax_get_schedulers_by_treatment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Get and sanitize parameters
        $treatment_type = isset($_POST['treatment_type']) ? sanitize_text_field($_POST['treatment_type']) : '';
        $clinic_id = isset($_POST['clinic_id']) ? intval($_POST['clinic_id']) : null;
        $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
        
        // Validate required parameters
        if (empty($treatment_type)) {
            wp_send_json_error(array('message' => 'Missing treatment_type parameter'));
            return;
        }
        
        // Load shortcode's filter engine if not already loaded
        $filter_engine_path = CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/booking-calendar/managers/class-calendar-filter-engine.php';
        if (file_exists($filter_engine_path) && !class_exists('Booking_Calendar_Filter_Engine')) {
            require_once $filter_engine_path;
        }
        
        // Use Booking Calendar Filter Engine (for shortcode) if available
        $schedulers = array();
        
        if (class_exists('Booking_Calendar_Filter_Engine')) {
            $shortcode_filter_engine = Booking_Calendar_Filter_Engine::get_instance();
            $schedulers = $shortcode_filter_engine->get_schedulers_by_treatment_type($treatment_type, $clinic_id, $doctor_id);
        } else {
            wp_send_json_error(array('message' => 'Filter engine not available'));
            return;
        }
        
        // Return response with debug info (for client-side logging)
        $response_data = array('schedulers' => $schedulers);
        
        // Add debug info for troubleshooting (only if no schedulers found)
        if (empty($schedulers)) {
            // Get scheduler IDs from relations for debugging
            $scheduler_ids_from_relations = array();
            if (!empty($doctor_id) && is_numeric($doctor_id)) {
                if (class_exists('Clinic_Queue_API_Manager')) {
                    $api_manager = Clinic_Queue_API_Manager::get_instance();
                    $scheduler_ids_from_relations = $api_manager->get_scheduler_ids_by_doctor($doctor_id);
                }
            } elseif (!empty($clinic_id) && is_numeric($clinic_id)) {
                if (class_exists('Clinic_Queue_API_Manager')) {
                    $api_manager = Clinic_Queue_API_Manager::get_instance();
                    $scheduler_ids_from_relations = $api_manager->get_scheduler_ids_by_clinic($clinic_id);
                }
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
