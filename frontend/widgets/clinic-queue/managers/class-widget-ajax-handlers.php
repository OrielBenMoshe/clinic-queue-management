<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget AJAX Handlers
 * Handles all AJAX endpoints and WordPress hooks registration
 * Depends on: Calendar Filter Engine, Calendar Data Provider
 */
class Clinic_Queue_Widget_Ajax_Handlers {
    
    private static $instance = null;
    private $filter_engine;
    private $data_provider;
    
    public function __construct() {
        $this->filter_engine = Clinic_Queue_Calendar_Filter_Engine::get_instance();
        $this->data_provider = Clinic_Queue_Calendar_Data_Provider::get_instance();
        
        // Auto-register hooks
        $this->register_hooks();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register all WordPress AJAX hooks
     */
    public function register_hooks() {
        // AJAX endpoint for getting appointments data
        add_action('wp_ajax_clinic_queue_get_appointments', [$this, 'handle_ajax_request']);
        add_action('wp_ajax_nopriv_clinic_queue_get_appointments', [$this, 'handle_ajax_request']);
        
        // AJAX endpoint for getting advanced filtered field options
        add_action('wp_ajax_clinic_queue_get_advanced_filtered_fields', [$this, 'handle_get_advanced_filtered_fields']);
        add_action('wp_ajax_nopriv_clinic_queue_get_advanced_filtered_fields', [$this, 'handle_get_advanced_filtered_fields']);
        
        // AJAX endpoint for getting smart field updates
        add_action('wp_ajax_clinic_queue_get_smart_field_updates', [$this, 'handle_get_smart_field_updates']);
        add_action('wp_ajax_nopriv_clinic_queue_get_smart_field_updates', [$this, 'handle_get_smart_field_updates']);
    }
    
    /**
     * Handle AJAX request for appointments data
     * Direct API call - no local storage
     */
    public function handle_ajax_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_die('Security check failed');
        }
        
        // Get parameters - support both calendar_id and doctor_id+clinic_id
        $calendar_id = isset($_POST['calendar_id']) ? sanitize_text_field($_POST['calendar_id']) : null;
        $doctor_id = isset($_POST['doctor_id']) ? sanitize_text_field($_POST['doctor_id']) : null;
        $clinic_id = isset($_POST['clinic_id']) ? sanitize_text_field($_POST['clinic_id']) : null;
        $treatment_type = isset($_POST['treatment_type']) ? sanitize_text_field($_POST['treatment_type']) : '';
        
        // Fetch directly from API
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $api_data = $api_manager->get_appointments_data($calendar_id, $doctor_id, $clinic_id, $treatment_type);
        
        if ($api_data) {
            // Convert to widget format
            $data = $this->data_provider->convert_api_format($api_data);
            if ($data) {
                wp_send_json_success($data);
            } else {
                wp_send_json_error('Failed to format appointments data');
            }
        } else {
            wp_send_json_error('Failed to load appointments data from API');
        }
    }
    
    /**
     * Handle AJAX request for getting advanced filtered field options
     * This is the main endpoint for the advanced filtering system
     */
    public function handle_get_advanced_filtered_fields() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_die('Security check failed');
        }
        
        // Debug logging
        error_log('[ClinicQueue] handle_get_advanced_filtered_fields - Raw POST data: ' . print_r($_POST, true));
        
        // Get widget settings
        $settings = [
            'selection_mode' => sanitize_text_field($_POST['selection_mode'] ?? 'doctor'),
            'specific_doctor_id' => sanitize_text_field($_POST['specific_doctor_id'] ?? ''),
            'specific_clinic_id' => sanitize_text_field($_POST['specific_clinic_id'] ?? ''),
            'use_specific_treatment' => sanitize_text_field($_POST['use_specific_treatment'] ?? 'no'),
            'specific_treatment_type' => sanitize_text_field($_POST['specific_treatment_type'] ?? '')
        ];
        
        // Get current selections
        $current_selections = [
            'doctor_id' => sanitize_text_field($_POST['current_doctor_id'] ?? ''),
            'clinic_id' => sanitize_text_field($_POST['current_clinic_id'] ?? ''),
            'treatment_type' => sanitize_text_field($_POST['current_treatment_type'] ?? '')
        ];
        
        // Debug logging
        error_log('[ClinicQueue] handle_get_advanced_filtered_fields - Processed settings: ' . print_r($settings, true));
        error_log('[ClinicQueue] handle_get_advanced_filtered_fields - Current selections: ' . print_r($current_selections, true));
        
        // Get filtered options
        $options = $this->filter_engine->get_field_options_for_current_selection($settings, $current_selections);
        
        wp_send_json_success($options);
    }
    
    /**
     * Handle AJAX request for smart field updates
     * This is the main endpoint for the smart filtering system
     */
    public function handle_get_smart_field_updates() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_die('Security check failed');
        }
        
        // Debug logging
        error_log('[ClinicQueue] handle_get_smart_field_updates - Raw POST data: ' . print_r($_POST, true));
        
        // Get widget settings
        $settings = [
            'selection_mode' => sanitize_text_field($_POST['selection_mode'] ?? 'doctor'),
            'specific_doctor_id' => sanitize_text_field($_POST['specific_doctor_id'] ?? ''),
            'specific_clinic_id' => sanitize_text_field($_POST['specific_clinic_id'] ?? ''),
            'use_specific_treatment' => sanitize_text_field($_POST['use_specific_treatment'] ?? 'no'),
            'specific_treatment_type' => sanitize_text_field($_POST['specific_treatment_type'] ?? '')
        ];
        
        // Debug logging
        error_log('[ClinicQueue] handle_get_smart_field_updates - Processed settings: ' . print_r($settings, true));
        
        // Get current selections
        $current_selections = [
            'doctor_id' => sanitize_text_field($_POST['current_doctor_id'] ?? ''),
            'clinic_id' => sanitize_text_field($_POST['current_clinic_id'] ?? ''),
            'treatment_type' => sanitize_text_field($_POST['current_treatment_type'] ?? '')
        ];
        
        // Get changed field info
        $changed_field = sanitize_text_field($_POST['changed_field'] ?? '');
        $changed_value = sanitize_text_field($_POST['changed_value'] ?? '');
        
        if (empty($changed_field)) {
            wp_send_json_error('Missing changed_field parameter');
        }
        
        // Get smart field updates
        $updates = $this->filter_engine->get_smart_field_updates($settings, $changed_field, $changed_value, $current_selections);
        
        wp_send_json_success($updates);
    }
}

