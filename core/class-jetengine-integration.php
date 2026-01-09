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
     * @param string|null $field_name Field name (optional, may not be passed in some contexts)
     * @return array Modified configuration
     */
    public function modify_treatment_type_field($config, $field_name = null) {
        // If field_name is not provided, return config as-is (e.g., when called from Relations page)
        if ($field_name === null) {
            return $config;
        }
        
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
     * Get treatment types from clinics repeater field
     * Collects all unique treatment_type values from all clinics' treatments repeater
     * 
     * @return array Array of treatment types (value => label format)
     */
    private function get_treatment_types_from_api() {
        // Query all clinics
        $args = array(
            'post_type' => 'clinics',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        $treatment_types_set = array(); // Use associative array to ensure uniqueness
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $clinic_id = get_the_ID();
                
                // Get treatments repeater from clinic
                $treatments_raw = get_post_meta($clinic_id, 'treatments', true);
                
                if (empty($treatments_raw) || !is_array($treatments_raw)) {
                    continue;
                }
                
                // Collect all treatment_type values
                foreach ($treatments_raw as $treatment) {
                    $treatment_type = isset($treatment['treatment_type']) ? trim($treatment['treatment_type']) : '';
                    
                    // Skip if empty
                    if (empty($treatment_type)) {
                        continue;
                    }
                    
                    // Add to set (associative array ensures uniqueness)
                    $treatment_types_set[$treatment_type] = $treatment_type;
                }
            }
            wp_reset_postdata();
        }
        
        // If no treatments found, return empty array (no default fallback)
        if (empty($treatment_types_set)) {
            return array();
        }
        
        // Transform to JetEngine format (value => label pairs)
        $treatments = array();
        foreach ($treatment_types_set as $treatment_type) {
            $treatments[] = array(
                'value' => $treatment_type,
                'label' => $treatment_type
            );
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

