<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * JetEngine Integration
 * Handles integration with JetEngine meta fields and custom post types
 * 
 * @package ClinicQueue
 * @since 1.0.0
 */
class Clinic_Queue_JetEngine_Integration {
    
    private static $instance = null;
    
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
     * Constructor
     */
    private function __construct() {
        // Hook into JetEngine to modify field options
        add_filter('jet-engine/meta-fields/config', array($this, 'modify_treatment_type_field'), 10, 2);
        add_filter('jet-engine/forms/booking/field-value', array($this, 'populate_treatment_options'), 10, 3);
    }
    
    /**
     * Modify treatment_type field to pull options from API
     * 
     * @param array $config Field configuration
     * @param string $field_name Field name
     * @return array Modified configuration
     */
    public function modify_treatment_type_field($config, $field_name) {
        // Only modify treatment_type field in clinics post type
        if ($field_name !== 'treatment_type') {
            return $config;
        }
        
        // Check if we're editing a clinic post
        global $post;
        if (!$post || $post->post_type !== 'clinics') {
            return $config;
        }
        
        // Get treatment types from API
        $treatment_types = $this->get_treatment_types_from_api();
        
        // Modify field config to use our options
        if (!empty($treatment_types)) {
            $config['options'] = $treatment_types;
        }
        
        return $config;
    }
    
    /**
     * Populate treatment options for JetFormBuilder
     * 
     * @param mixed $value Current field value
     * @param array $field Field configuration
     * @param string $form_id Form ID
     * @return mixed Modified value
     */
    public function populate_treatment_options($value, $field, $form_id) {
        if (isset($field['name']) && $field['name'] === 'treatment_type') {
            $treatment_types = $this->get_treatment_types_from_api();
            if (!empty($treatment_types)) {
                $field['options'] = $treatment_types;
            }
        }
        return $value;
    }
    
    /**
     * Get treatment types from external API
     * 
     * @return array Array of treatment types (value => label format)
     */
    private function get_treatment_types_from_api() {
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
            array('value' => 'רפואה כללית', 'label' => 'רפואה כללית'),
            array('value' => 'קרדיולוגיה', 'label' => 'קרדיולוגיה'),
            array('value' => 'דרמטולוגיה', 'label' => 'דרמטולוגיה'),
            array('value' => 'אורתופדיה', 'label' => 'אורתופדיה'),
            array('value' => 'רפואת ילדים', 'label' => 'רפואת ילדים')
        );
        
        // Handle errors
        if (is_wp_error($response)) {
            error_log('[JetEngine Integration] Failed to fetch treatment types: ' . $response->get_error_message());
            return $default_treatments;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data) || empty($data)) {
            error_log('[JetEngine Integration] Invalid treatment types data received from API');
            return $default_treatments;
        }
        
        // Transform API data to JetEngine format (value => label pairs)
        $treatments = array();
        foreach ($data as $item) {
            if (isset($item['name']) && !empty($item['name'])) {
                $name = $item['name'];
                $treatments[] = array(
                    'value' => $name,
                    'label' => $name
                );
            }
        }
        
        // If no treatments found, use default
        if (empty($treatments)) {
            error_log('[JetEngine Integration] No treatment types found in API response');
            return $default_treatments;
        }
        
        // Sort alphabetically by label (Hebrew)
        usort($treatments, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        
        return $treatments;
    }
    
    /**
     * Get treatment types in simple format (for compatibility)
     * 
     * @return array Array of treatment types (name => name format)
     */
    public function get_treatment_types_simple() {
        $treatments_array = $this->get_treatment_types_from_api();
        
        $simple = array();
        foreach ($treatments_array as $treatment) {
            $simple[$treatment['value']] = $treatment['label'];
        }
        
        return $simple;
    }
}

