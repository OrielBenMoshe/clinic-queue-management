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
     * Queries clinics post type from WordPress database
     * 
     * @param string|int $doctor_id Optional doctor ID (not currently used for filtering)
     * @return array Array of clinics (id => name format)
     */
    public function get_clinics_options($doctor_id = null) {
        // Query all clinics post type
        $args = array(
            'post_type' => 'clinics',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $query = new WP_Query($args);
        $clinics = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $clinic_id = get_the_ID();
                $clinic_title = get_the_title();
                
                if (!empty($clinic_title)) {
                    $clinics[$clinic_id] = $clinic_title;
                }
            }
            wp_reset_postdata();
        }
        
        return $clinics;
    }
    
    /**
     * Get treatment types for initial load
     * Loads from JetEngine Integration API
     * 
     * @deprecated Treatment types are now collected from schedulers in JavaScript (both clinic and doctor modes)
     * This method is kept for backward compatibility but is no longer used by the booking calendar shortcode
     * 
     * @return array Array of treatment types (id => name format)
     */
    public function get_treatment_types() {
        // Load from JetEngine Integration
        if (class_exists('Clinic_Queue_JetEngine_Integration')) {
            $integration = Clinic_Queue_JetEngine_Integration::get_instance();
            return $integration->get_treatment_types_simple();
        }
        
        // Fallback to empty array
        return array();
    }
    
    /**
     * Get treatments for specific scheduler
     * Returns treatments filtered by scheduler's allowed treatment_types
     * 
     * @deprecated This method is no longer used. Treatment filtering is now done client-side in JavaScript.
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
    
    /**
     * Get schedulers (schedules post type) by treatment_type, clinic_id, or doctor_id
     * Searches in schedules post type, in the treatments repeater field
     * Filters by clinic_id using JetEngine Relations (Relation 184: Clinic -> Scheduler)
     * Filters by doctor_id using JetEngine Relations (Relation 185: Scheduler -> Doctor)
     * 
     * @deprecated Scheduler filtering is now done client-side in JavaScript for better performance.
     * This method is still used by the AJAX handler for backward compatibility, but the booking calendar
     * shortcode no longer uses AJAX for filtering - it filters pre-loaded schedulers locally.
     * 
     * @param string $treatment_type The treatment type to search for
     * @param int $clinic_id The clinic ID to filter by (optional)
     * @param int $doctor_id The doctor ID to filter by (optional)
     * @return array Array of schedulers with ID as key, including all meta fields
     */
    public function get_schedulers_by_treatment_type($treatment_type, $clinic_id = null, $doctor_id = null) {
        if (empty($treatment_type)) {
            return array();
        }
        
        // Normalize treatment_type (trim whitespace)
        $treatment_type = trim($treatment_type);
        
        // Get scheduler IDs using JetEngine Relations
        $scheduler_ids = array();
        
        if (!empty($doctor_id) && is_numeric($doctor_id)) {
            // Use API Manager to get scheduler IDs by doctor (Relation 185)
            if (class_exists('Clinic_Queue_API_Manager')) {
                $api_manager = Clinic_Queue_API_Manager::get_instance();
                $scheduler_ids = $api_manager->get_scheduler_ids_by_doctor($doctor_id);
            }
        } elseif (!empty($clinic_id) && is_numeric($clinic_id)) {
            // Use API Manager to get scheduler IDs by clinic (Relation 184)
            if (class_exists('Clinic_Queue_API_Manager')) {
                $api_manager = Clinic_Queue_API_Manager::get_instance();
                $scheduler_ids = $api_manager->get_scheduler_ids_by_clinic($clinic_id);
            }
        }
        
        // If no scheduler IDs found via relations, return empty (no fallback - only relations allowed)
        if (empty($scheduler_ids)) {
            return array();
        }
        
        // Query schedules - only query those scheduler IDs from relations
        $args = array(
            'post_type' => 'schedules',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'post__in' => $scheduler_ids
        );
        
        $query = new WP_Query($args);
        $schedulers = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $scheduler_id = get_the_ID();
                
                // Get treatments repeater
                $treatments_raw = get_post_meta($scheduler_id, 'treatments', true);
                
                if (empty($treatments_raw) || !is_array($treatments_raw)) {
                    continue;
                }
                
                // Check if this scheduler has the treatment_type
                $found_treatment = null;
                foreach ($treatments_raw as $treatment) {
                    $treatment_type_value = isset($treatment['treatment_type']) ? trim($treatment['treatment_type']) : '';
                    // String comparison (case-sensitive, exact match after trim)
                    // Also try case-insensitive comparison as fallback
                    if ($treatment_type_value === $treatment_type || 
                        strcasecmp($treatment_type_value, $treatment_type) === 0) {
                        $found_treatment = $treatment;
                        break;
                    }
                }
                
                // Skip if treatment_type not found
                if (!$found_treatment) {
                    continue;
                }
                
                // Get all scheduler meta data
                $doctor_id_meta = get_post_meta($scheduler_id, 'doctor_id', true);
                $clinic_id_meta = get_post_meta($scheduler_id, 'clinic_id', true);
                $proxy_schedule_id = get_post_meta($scheduler_id, 'proxy_schedule_id', true);
                $schedule_name = get_post_meta($scheduler_id, 'schedule_name', true);
                $manual_calendar_name = get_post_meta($scheduler_id, 'manual_calendar_name', true);
                $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
                
                // Get all treatments with full details
                $all_treatments = array();
                foreach ($treatments_raw as $treatment) {
                    if (isset($treatment['treatment_type']) && !empty($treatment['treatment_type'])) {
                        $all_treatments[] = array(
                            'treatment_type' => $treatment['treatment_type'],
                            'sub_speciality' => isset($treatment['sub_speciality']) ? $treatment['sub_speciality'] : '',
                            'cost' => isset($treatment['cost']) ? $treatment['cost'] : '',
                            'duration' => isset($treatment['duration']) ? $treatment['duration'] : ''
                        );
                    }
                }
                
                // Get doctor details
                $doctor_name = '';
                $doctor_specialty = '';
                if ($doctor_id_meta) {
                    $doctor = get_post($doctor_id_meta);
                    if ($doctor) {
                        $doctor_name = $doctor->post_title;
                        $doctor_specialty = get_post_meta($doctor_id_meta, 'specialty', true);
                    }
                }
                
                // Get clinic details
                $clinic_name = '';
                if ($clinic_id_meta) {
                    $clinic = get_post($clinic_id_meta);
                    if ($clinic) {
                        $clinic_name = $clinic->post_title;
                    }
                }
                
                // Get working days meta (all days)
                $working_days = array();
                $days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
                foreach ($days as $day) {
                    $day_data = get_post_meta($scheduler_id, $day, true);
                    if (!empty($day_data) && is_array($day_data)) {
                        $working_days[$day] = $day_data;
                    }
                }
                
                // Get duration from found treatment
                $duration = isset($found_treatment['duration']) ? intval($found_treatment['duration']) : 30;
                
                $schedulers[$scheduler_id] = array(
                    'id' => $scheduler_id,
                    'title' => get_the_title(),
                    'doctor_id' => $doctor_id_meta,
                    'doctor_name' => $doctor_name,
                    'doctor_specialty' => $doctor_specialty,
                    'clinic_id' => $clinic_id_meta,
                    'clinic_name' => $clinic_name,
                    'proxy_schedule_id' => $proxy_schedule_id,
                    'schedule_name' => $schedule_name,
                    'manual_calendar_name' => $manual_calendar_name,
                    'schedule_type' => $schedule_type,
                    'duration' => $duration,
                    'treatment_type' => $treatment_type,
                    'treatments' => $all_treatments, // Full treatment details with all fields
                    'working_days' => $working_days // All working days with time ranges
                );
            }
            wp_reset_postdata();
        }
        
        return $schedulers;
    }
}

