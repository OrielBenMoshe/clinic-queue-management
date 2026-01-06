<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar Filter Engine for Booking Calendar Shortcode
 * Handles all filtering logic and field options generation
 * Depends on: Calendar Data Provider
 * 
 * NOTE: This is a duplicate of the widget's filter engine for shortcode independence
 */
class Booking_Calendar_Filter_Engine {
    
    private static $instance = null;
    private $data_provider;
    
    public function __construct() {
        $this->data_provider = Booking_Calendar_Data_Provider::get_instance();
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
     * Get clinics options for specific doctor
     */
    public function get_clinics_options($doctor_id) {
        // Simple implementation for now - can be expanded
        return array(
            '1' => 'מרפאה תל אביב',
            '2' => 'מרפאה חיפה',
            '3' => 'מרפאה באר שבע'
        );
    }
    
    /**
     * Get treatment types for initial load
     * Note: Actual treatments are loaded dynamically via AJAX based on selected clinic/scheduler
     * This is just for initial placeholder/fallback
     * 
     * @return array Empty array (treatments loaded dynamically from clinic's repeater field)
     */
    public function get_treatment_types() {
        // Return empty - treatments are loaded dynamically via JavaScript
        // from the clinic's "treatments" repeater field based on selected scheduler
        return array();
    }
    
    /**
     * Get treatments for specific scheduler
     * Returns treatments filtered by scheduler's allowed treatment_types
     * 
     * @param int $scheduler_id The scheduler ID
     * @param int $clinic_id The clinic ID
     * @return array Array of treatment options formatted for dropdown
     */
    public function get_treatments_for_scheduler($scheduler_id, $clinic_id) {
        if (!$this->data_provider) {
            return array();
        }
        
        // Get scheduler to find allowed treatments
        $schedulers = $this->data_provider->get_schedulers_by_clinic($clinic_id);
        
        if (!isset($schedulers[$scheduler_id])) {
            return array();
        }
        
        $scheduler = $schedulers[$scheduler_id];
        $allowed_treatments = isset($scheduler['treatments']) ? $scheduler['treatments'] : array();
        
        // Get full treatment details from clinic
        $treatments = $this->data_provider->get_treatments_for_scheduler($clinic_id, $allowed_treatments);
        
        // Format for dropdown
        $options = array();
        foreach ($treatments as $treatment) {
            $options[] = array(
                'id' => $treatment['treatment_type'],
                'name' => $treatment['treatment_type'],
                'duration' => $treatment['duration'],
                'cost' => $treatment['cost'],
                'sub_speciality' => $treatment['sub_speciality']
            );
        }
        
        return $options;
    }
}

