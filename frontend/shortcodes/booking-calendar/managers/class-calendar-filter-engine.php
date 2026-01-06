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
     * Get treatment types from API
     * 
     * @return array Treatment types array (name => name)
     */
    public function get_treatment_types() {
        // Fetch from API
        $api_url = 'https://doctor-place.com/wp-json/clinics/sub-specialties/';
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        // Default fallback
        $default_treatments = array(
            'רפואה כללית' => 'רפואה כללית',
            'קרדיולוגיה' => 'קרדיולוגיה',
            'דרמטולוגיה' => 'דרמטולוגיה',
            'אורתופדיה' => 'אורתופדיה',
            'רפואת ילדים' => 'רפואת ילדים'
        );
        
        // Handle errors
        if (is_wp_error($response)) {
            error_log('[Booking Calendar] Failed to fetch treatment types: ' . $response->get_error_message());
            return $default_treatments;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data) || empty($data)) {
            error_log('[Booking Calendar] Invalid treatment types data received from API');
            return $default_treatments;
        }
        
        // Transform API data to our format
        $treatments = array();
        foreach ($data as $item) {
            if (isset($item['name']) && !empty($item['name'])) {
                $name = $item['name'];
                $treatments[$name] = $name;
            }
        }
        
        // If no treatments found, use default
        if (empty($treatments)) {
            error_log('[Booking Calendar] No treatment types found in API response');
            return $default_treatments;
        }
        
        // Sort alphabetically (Hebrew)
        asort($treatments, SORT_STRING | SORT_FLAG_CASE);
        
        return $treatments;
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

