<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar Filter Engine
 * Handles all filtering logic and field options generation
 * Depends on: Calendar Data Provider
 */
class Clinic_Queue_Calendar_Filter_Engine {
    
    private static $instance = null;
    private $data_provider;
    
    public function __construct() {
        $this->data_provider = Clinic_Queue_Calendar_Data_Provider::get_instance();
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
     * Get filtered calendars for a specific widget based on its settings
     * This is the primary filtering mechanism for each widget
     * 
     * Widget variations:
     * 1. Doctor mode: Doctor SELECTABLE, Clinic FIXED, Treatment DYNAMIC/FIXED
     * 2. Clinic mode: Clinic SELECTABLE, Doctor FIXED, Treatment DYNAMIC/FIXED
     */
    public function get_filtered_calendars_for_widget($settings) {
        // Debug logging
        error_log('[ClinicQueue] get_filtered_calendars_for_widget - Input settings: ' . print_r($settings, true));
        
        // Get data from database via data provider
        $data = $this->data_provider->get_all_calendars();
        
        if (!$data || !is_array($data)) {
            return [];
        }
        
        $filtered_calendars = [];
        $selection_mode = $settings['selection_mode'] ?? 'doctor';
        
        // Determine fixed parameters based on selection mode and settings
        $fixed_doctor_id = null;
        $fixed_clinic_id = null;
        $fixed_treatment_type = null;
        
        if ($selection_mode === 'doctor') {
            // Doctor mode: Doctor is SELECTABLE, Clinic is FIXED
            $fixed_clinic_id = $settings['specific_clinic_id'] ?? '1';
        } else {
            // Clinic mode: Clinic is SELECTABLE, Doctor is FIXED  
            $fixed_doctor_id = $settings['specific_doctor_id'] ?? '1';
        }
        
        // Check if treatment type is fixed
        if (($settings['use_specific_treatment'] ?? 'no') === 'yes') {
            $fixed_treatment_type = $settings['specific_treatment_type'] ?? 'רפואה כללית';
        }
        
        // Filter calendars based on fixed parameters
        // Note: get_all_calendars() returns an array of calendars directly, not ['calendars' => [...]]
        foreach ($data as $calendar) {
            // Skip invalid calendar entries
            if (!is_array($calendar)) {
                continue;
            }
            
            $include_calendar = true;
            
            // Check doctor filter
            if ($fixed_doctor_id !== null && ($calendar['doctor_id'] ?? null) != $fixed_doctor_id) {
                $include_calendar = false;
            }
            
            // Check clinic filter
            if ($fixed_clinic_id !== null && ($calendar['clinic_id'] ?? null) != $fixed_clinic_id) {
                $include_calendar = false;
            }
            
            // Check treatment type filter
            if ($fixed_treatment_type !== null && ($calendar['treatment_type'] ?? null) != $fixed_treatment_type) {
                $include_calendar = false;
            }
            
            if ($include_calendar) {
                $filtered_calendars[] = $calendar;
            }
        }
        
        return $filtered_calendars;
    }
    
    /**
     * Get field options based on current selections and widget settings
     * This handles the advanced filtering between fields
     */
    public function get_field_options_for_current_selection($settings, $current_selections = []) {
        // Debug logging
        error_log('[ClinicQueue] get_field_options_for_current_selection - Input settings: ' . print_r($settings, true));
        error_log('[ClinicQueue] get_field_options_for_current_selection - Current selections: ' . print_r($current_selections, true));
        
        // Check if we're in clinic mode (יומן מרפאה)
        $selection_mode = $settings['selection_mode'] ?? 'doctor';
        $clinic_id = $settings['effective_clinic_id'] ?? null;
        
        // In clinic mode, return schedulers instead of doctors
        if ($selection_mode === 'clinic' && !empty($clinic_id)) {
            return $this->get_schedulers_options($clinic_id, $current_selections);
        }
        
        // Default behavior for doctor mode
        // Get the filtered calendars for this widget
        $filtered_calendars = $this->get_filtered_calendars_for_widget($settings);
        
        if (empty($filtered_calendars)) {
            return [
                'doctors' => [],
                'clinics' => [],
                'treatment_types' => []
            ];
        }
        
        // Apply additional filtering based on current selections
        $further_filtered_calendars = $this->apply_current_selection_filters($filtered_calendars, $current_selections);
        
        // Extract unique options from the filtered calendars
        $options = [
            'doctors' => [],
            'clinics' => [],
            'treatment_types' => []
        ];
        
        foreach ($further_filtered_calendars as $calendar) {
            // Add unique doctors
            if (!isset($options['doctors'][$calendar['doctor_id']])) {
                $options['doctors'][$calendar['doctor_id']] = [
                    'id' => $calendar['doctor_id'],
                    'name' => $calendar['doctor_name']
                ];
            }
            
            // Add unique clinics
            if (!isset($options['clinics'][$calendar['clinic_id']])) {
                $options['clinics'][$calendar['clinic_id']] = [
                    'id' => $calendar['clinic_id'],
                    'name' => $calendar['clinic_name']
                ];
            }
            
            // Add unique treatment types
            if (!isset($options['treatment_types'][$calendar['treatment_type']])) {
                $options['treatment_types'][$calendar['treatment_type']] = [
                    'id' => $calendar['treatment_type'],
                    'name' => $calendar['treatment_type']
                ];
            }
        }
        
        // Convert to indexed arrays
        return [
            'doctors' => array_values($options['doctors']),
            'clinics' => array_values($options['clinics']),
            'treatment_types' => array_values($options['treatment_types'])
        ];
    }
    
    /**
     * Get schedulers options for clinic mode
     * Returns schedulers instead of doctors when in clinic calendar mode
     * 
     * @param int $clinic_id The clinic ID
     * @param array $current_selections Current user selections
     * @return array Options with 'schedulers' key instead of 'doctors'
     */
    private function get_schedulers_options($clinic_id, $current_selections = []) {
        if (!$this->data_provider) {
            return [
                'schedulers' => [],
                'treatment_types' => []
            ];
        }
        
        // Get schedulers for this clinic
        $schedulers = $this->data_provider->get_schedulers_by_clinic($clinic_id);
        
        if (empty($schedulers)) {
            return [
                'schedulers' => [],
                'treatment_types' => []
            ];
        }
        
        // Build schedulers options
        $scheduler_options = [];
        $treatment_types = [];
        
        foreach ($schedulers as $scheduler_id => $scheduler) {
            // Filter by treatment type if selected
            if (!empty($current_selections['treatment_type'])) {
                if ($scheduler['treatment_type'] !== $current_selections['treatment_type']) {
                    continue;
                }
            }
            
            $scheduler_options[] = [
                'id' => $scheduler_id,
                'name' => $scheduler['doctor_name'] ?? 'ללא שם',
                'treatment_type' => $scheduler['treatment_type'] ?? '',
                'doctor_specialty' => $scheduler['doctor_specialty'] ?? '',
                'label' => $this->build_scheduler_label($scheduler)
            ];
            
            // Collect unique treatment types
            if (!empty($scheduler['treatment_type']) && !isset($treatment_types[$scheduler['treatment_type']])) {
                $treatment_types[$scheduler['treatment_type']] = [
                    'id' => $scheduler['treatment_type'],
                    'name' => $scheduler['treatment_type']
                ];
            }
        }
        
        return [
            'schedulers' => $scheduler_options,
            'treatment_types' => array_values($treatment_types)
        ];
    }
    
    /**
     * Build a label for scheduler option
     */
    private function build_scheduler_label($scheduler) {
        $label = $scheduler['doctor_name'] ?? 'ללא שם';
        
        if (!empty($scheduler['treatment_type'])) {
            $label .= ' - ' . $scheduler['treatment_type'];
        }
        
        if (!empty($scheduler['doctor_specialty'])) {
            $label .= ' (' . $scheduler['doctor_specialty'] . ')';
        }
        
        return $label;
    }
    
    /**
     * Apply additional filtering based on current user selections
     */
    private function apply_current_selection_filters($calendars, $current_selections) {
        $filtered = $calendars;
        
        // If doctor is selected, filter by doctor
        if (!empty($current_selections['doctor_id'])) {
            $filtered = array_filter($filtered, function($calendar) use ($current_selections) {
                return $calendar['doctor_id'] == $current_selections['doctor_id'];
            });
        }
        
        // If clinic is selected, filter by clinic
        if (!empty($current_selections['clinic_id'])) {
            $filtered = array_filter($filtered, function($calendar) use ($current_selections) {
                return $calendar['clinic_id'] == $current_selections['clinic_id'];
            });
        }
        
        // If treatment type is selected, filter by treatment type
        if (!empty($current_selections['treatment_type'])) {
            $filtered = array_filter($filtered, function($calendar) use ($current_selections) {
                return $calendar['treatment_type'] == $current_selections['treatment_type'];
            });
        }
        
        return array_values($filtered);
    }
    
    /**
     * Get smart field updates based on changed field
     * This implements the advanced filtering logic where each field affects others
     */
    public function get_smart_field_updates($settings, $changed_field, $changed_value, $current_selections) {
        // Debug logging
        error_log('[ClinicQueue] get_smart_field_updates - Input settings: ' . print_r($settings, true));
        error_log('[ClinicQueue] get_smart_field_updates - Changed field: ' . $changed_field . ', Changed value: ' . $changed_value);
        error_log('[ClinicQueue] get_smart_field_updates - Current selections: ' . print_r($current_selections, true));
        
        // Create a copy of current selections and update the changed field
        $updated_selections = $current_selections;
        $updated_selections[$changed_field] = $changed_value;
        
        // Get all possible options for each field based on the updated selections
        $all_options = $this->get_field_options_for_current_selection($settings, $updated_selections);
        
        // Determine which fields need to be updated based on the changed field
        $fields_to_update = $this->get_fields_affected_by_change($changed_field, $settings);
        
        $updates = [];
        
        foreach ($fields_to_update as $field_name) {
            $field_options = $all_options[$field_name] ?? [];
            $current_value = $current_selections[$field_name] ?? '';
            
            // Smart selection logic:
            // 1. Try to keep current value if it's still available
            // 2. Fall back to first option if current value is not available
            $selected_value = $this->get_smart_selection($field_options, $current_value);
            
            $updates[$field_name] = [
                'options' => $field_options,
                'selected_value' => $selected_value
            ];
        }
        
        return $updates;
    }
    
    /**
     * Determine which fields are affected by a change in a specific field
     */
    private function get_fields_affected_by_change($changed_field, $settings) {
        $selection_mode = $settings['selection_mode'] ?? 'doctor';
        $use_specific_treatment = ($settings['use_specific_treatment'] ?? 'no') === 'yes';
        
        $affected_fields = [];
        
        if ($selection_mode === 'doctor') {
            // Doctor mode: Doctor is SELECTABLE, Clinic is FIXED, Treatment is DYNAMIC/FIXED
            if ($changed_field === 'doctor_id') {
                if (!$use_specific_treatment) {
                    $affected_fields[] = 'treatment_types';
                }
            } elseif ($changed_field === 'treatment_type') {
                // Treatment type change doesn't affect other fields in doctor mode
                // because clinic is fixed and doctor is the primary selector
            }
        } else {
            // Clinic mode: Clinic is SELECTABLE, Doctor is FIXED, Treatment is DYNAMIC/FIXED
            if ($changed_field === 'clinic_id') {
                if (!$use_specific_treatment) {
                    $affected_fields[] = 'treatment_types';
                }
            } elseif ($changed_field === 'treatment_type') {
                // Treatment type change doesn't affect other fields in clinic mode
                // because doctor is fixed and clinic is the primary selector
            }
        }
        
        return $affected_fields;
    }
    
    /**
     * Smart selection logic: prefer current value, fallback to first option
     */
    private function get_smart_selection($options, $current_value) {
        if (empty($options)) {
            return '';
        }
        
        // Check if current value is still available
        foreach ($options as $option) {
            if ($option['id'] === $current_value) {
                return $current_value;
            }
        }
        
        // Fallback to first option
        return $options[0]['id'] ?? '';
    }
    
    // ============================================================================
    // Legacy wrapper functions for backward compatibility
    // These call the new filtering system but maintain the old API
    // ============================================================================
    
    /**
     * Get clinics options for specific doctor (LEGACY)
     */
    public function get_clinics_options($doctor_id, $widget_settings = []) {
        $settings = [
            'selection_mode' => 'doctor', 
            'specific_doctor_id' => $doctor_id,
            'specific_clinic_id' => $widget_settings['effective_clinic_id'] ?? '1',
            'use_specific_treatment' => $widget_settings['use_specific_treatment'] ?? 'no',
            'specific_treatment_type' => $widget_settings['specific_treatment_type'] ?? ''
        ];
        $current_selections = ['doctor_id' => $doctor_id];
        
        $options = $this->get_field_options_for_current_selection($settings, $current_selections);
        
        // Convert to legacy format
        $legacy_options = [];
        foreach ($options['clinics'] as $clinic) {
            $legacy_options[$clinic['id']] = $clinic['name'];
        }
        
        return !empty($legacy_options) ? $legacy_options : ['1' => 'מרפאה תל אביב'];
    }
    
    /**
     * Get treatment types filtered by doctor (LEGACY)
     */
    public function get_treatment_types_by_doctor($doctor_id, $widget_settings = []) {
        $settings = [
            'selection_mode' => 'doctor', 
            'specific_doctor_id' => $doctor_id,
            'specific_clinic_id' => $widget_settings['effective_clinic_id'] ?? '1',
            'use_specific_treatment' => $widget_settings['use_specific_treatment'] ?? 'no',
            'specific_treatment_type' => $widget_settings['specific_treatment_type'] ?? ''
        ];
        $current_selections = ['doctor_id' => $doctor_id];
        
        $options = $this->get_field_options_for_current_selection($settings, $current_selections);
        
        // Convert to legacy format
        $legacy_options = [];
        foreach ($options['treatment_types'] as $type) {
            $legacy_options[$type['id']] = $type['name'];
        }
        
        return !empty($legacy_options) ? $legacy_options : $this->get_default_treatment_types_array();
    }
    
    /**
     * Get treatment types filtered by clinic (LEGACY)
     */
    public function get_treatment_types_by_clinic($clinic_id, $widget_settings = []) {
        $settings = [
            'selection_mode' => 'clinic', 
            'specific_clinic_id' => $clinic_id,
            'specific_doctor_id' => $widget_settings['effective_doctor_id'] ?? '1',
            'use_specific_treatment' => $widget_settings['use_specific_treatment'] ?? 'no',
            'specific_treatment_type' => $widget_settings['specific_treatment_type'] ?? ''
        ];
        $current_selections = ['clinic_id' => $clinic_id];
        
        $options = $this->get_field_options_for_current_selection($settings, $current_selections);
        
        // Convert to legacy format
        $legacy_options = [];
        foreach ($options['treatment_types'] as $type) {
            $legacy_options[$type['id']] = $type['name'];
        }
        
        return !empty($legacy_options) ? $legacy_options : $this->get_default_treatment_types_array();
    }
    
    /**
     * Get doctors options for specific clinic (LEGACY)
     */
    public function get_doctors_by_clinic($clinic_id, $widget_settings = []) {
        $settings = [
            'selection_mode' => 'clinic', 
            'specific_clinic_id' => $clinic_id,
            'specific_doctor_id' => $widget_settings['effective_doctor_id'] ?? '1',
            'use_specific_treatment' => $widget_settings['use_specific_treatment'] ?? 'no',
            'specific_treatment_type' => $widget_settings['specific_treatment_type'] ?? ''
        ];
        $current_selections = ['clinic_id' => $clinic_id];
        
        $options = $this->get_field_options_for_current_selection($settings, $current_selections);
        
        // Convert to legacy format
        $legacy_options = [];
        foreach ($options['doctors'] as $doctor) {
            $legacy_options[$doctor['id']] = $doctor['name'];
        }
        
        return !empty($legacy_options) ? $legacy_options : ['1' => 'ד"ר יוסי כהן'];
    }
    
    /**
     * Get default treatment types as associative array (for legacy compatibility)
     */
    private function get_default_treatment_types_array() {
        return [
            'רפואה כללית' => 15,
            'קרדיולוגיה' => 30,
            'דרמטולוגיה' => 20,
            'אורתופדיה' => 20,
            'רפואת ילדים' => 20,
            'גינקולוגיה' => 30,
            'נוירולוגיה' => 40,
            'פסיכיאטריה' => 60
        ];
    }
}

