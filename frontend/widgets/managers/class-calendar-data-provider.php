<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar Data Provider
 * Responsible for reading raw data from mock-data.json and API
 * No filtering logic - just pure data retrieval
 */
class Clinic_Queue_Calendar_Data_Provider {
    
    private static $instance = null;
    private $api_manager;
    
    public function __construct() {
        $this->api_manager = Clinic_Queue_API_Manager::get_instance();
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
     * Get all unique doctors from calendars
     * Returns array with doctor_id as key
     */
    public function get_all_doctors() {
        // Get doctors from database via API manager
        return $this->api_manager->get_all_doctors();
    }
    
    /**
     * Get all unique clinics from calendars
     * Returns array with clinic_id as key
     */
    public function get_all_clinics() {
        // Get clinics from database via API manager
        return $this->api_manager->get_all_clinics();
    }
    
    /**
     * Get all unique treatment types from calendars
     * Returns array of treatment types
     */
    public function get_all_treatment_types() {
        // Get treatment types from database via API manager
        $treatment_types = $this->api_manager->get_all_treatment_types();
        
        if (empty($treatment_types)) {
            return $this->get_default_treatment_types();
        }
        
        return $treatment_types;
    }
    
    /**
     * Get all calendars from database
     * Returns array of calendar objects
     */
    public function get_all_calendars() {
        // Get calendars from database via API manager
        return $this->api_manager->get_all_calendars();
    }
    
    /**
     * Get default treatment types (fallback)
     */
    private function get_default_treatment_types() {
        return [
            'רפואה כללית',
            'קרדיולוגיה',
            'דרמטולוגיה',
            'אורתופדיה',
            'רפואת ילדים',
            'גינקולוגיה',
            'נוירולוגיה',
            'פסיכיאטריה'
        ];
    }
    
    /**
     * Get appointments data from API
     * Returns formatted appointment data for widget
     */
    public function get_appointments_from_api($doctor_id, $clinic_id, $treatment_type = '') {
        // Get data from API manager
        $api_data = $this->api_manager->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
        
        if ($api_data && !empty($api_data['days'])) {
            return $this->convert_api_format($api_data);
        }
        
        return null;
    }
    
    /**
     * Convert API data format to widget format
     * Transforms API response structure to the format expected by the widget
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
                    'is_booked' => $slot['booked'] ? 1 : 0
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
}

