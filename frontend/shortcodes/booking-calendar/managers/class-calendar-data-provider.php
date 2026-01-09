<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar Data Provider for Booking Calendar Shortcode
 * Responsible for reading data from WordPress database via API Manager
 * No filtering logic - just pure data retrieval
 * 
 * NOTE: This is a duplicate of the widget's data provider for shortcode independence
 */
class Booking_Calendar_Data_Provider {
    
    private static $instance = null;
    private $api_manager;
    
    public function __construct() {
        if (class_exists('Clinic_Queue_API_Manager')) {
            $this->api_manager = Clinic_Queue_API_Manager::get_instance();
        }
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
     * Get schedulers (calendars) by clinic ID
     * Returns array of scheduler objects with ID as key
     * 
     * @param int $clinic_id The clinic ID
     * @return array Array of schedulers: [scheduler_id => [...details]]
     */
    public function get_schedulers_by_clinic($clinic_id) {
        if (!$this->api_manager) {
            return array();
        }
        return $this->api_manager->get_schedulers_by_clinic($clinic_id);
    }
    
    /**
     * Get schedulers (calendars) by doctor ID
     * Returns array of scheduler objects with ID as key, including all meta fields
     * 
     * @param int $doctor_id The doctor ID
     * @return array Array of schedulers: [scheduler_id => [...details with all meta]]
     */
    public function get_schedulers_by_doctor($doctor_id) {
        if (!$this->api_manager) {
            return array();
        }
        return $this->api_manager->get_schedulers_by_doctor($doctor_id);
    }
    
    /**
     * Get appointments data from API
     * Returns formatted appointment data for shortcode
     * Direct API call - no local storage
     */
    public function get_appointments_from_api($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        // Get data from API manager - direct call
        if (!$this->api_manager) {
            return null;
        }
        $api_data = $this->api_manager->get_appointments_data($calendar_id, $doctor_id, $clinic_id, $treatment_type);
        
        if ($api_data && !empty($api_data['days'])) {
            return $this->convert_api_format($api_data);
        }
        
        return null;
    }
    
    /**
     * Convert API data format to shortcode format
     * Transforms API response structure to the format expected by the shortcode
     */
    public function convert_api_format($api_data) {
        if (!isset($api_data['days']) || !is_array($api_data['days'])) {
            return null;
        }
        
        $appointments_data = [];
        
        foreach ($api_data['days'] as $day) {
            $time_slots = [];
            foreach ($day['slots'] as $slot) {
                $time_slots[] = (object) [
                    'time_slot' => $slot['time'],
                    'is_booked' => 0 // All slots returned are free/available
                ];
            }
            
            $appointments_data[] = [
                'date' => (object) [
                    'appointment_date' => $day['date']
                ],
                'time_slots' => $time_slots
            ];
        }
        
        return $appointments_data;
    }
    
    /**
     * Clear cached data (useful for testing or when data changes)
     */
    public function clear_cache() {
        $this->mock_data_cache = null;
    }
    
    /**
     * Get treatments for scheduler from clinic
     * Returns treatments filtered by scheduler's allowed treatment_types
     * with full details from clinic
     * 
     * @param int $clinic_id The clinic ID
     * @param array $allowed_treatment_types Array of allowed treatment types from scheduler
     * @return array Array of treatment details
     */
    public function get_treatments_for_scheduler($clinic_id, $allowed_treatment_types = array()) {
        if (!$this->api_manager) {
            return array();
        }
        return $this->api_manager->get_clinic_treatments_for_scheduler($clinic_id, $allowed_treatment_types);
    }
    
    /**
     * Get all doctors (for doctor mode)
     * 
     * @deprecated This method is kept for backward compatibility only.
     * Use get_schedulers_by_doctor() with relations instead.
     * 
     * @return array Array of doctors (empty array - not implemented via API Manager)
     */
    public function get_all_doctors() {
        // This method is deprecated - use relations instead
        // Returning empty array for backward compatibility
        return array();
    }
}

