<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load services only if not already loaded
// Use conditional loading to prevent fatal errors
if (!class_exists('Clinic_Queue_JetEngine_Relations_Service') || !class_exists('Clinic_Queue_DoctorOnline_API_Service')) {
    require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/index.php';
}

/**
 * API Manager for Clinic Queue Management
 * 
 * Orchestrates all API communication - acts as a facade for various API services
 * Maintains backward compatibility while delegating to specialized services
 * 
 * @package Clinic_Queue_Management
 * @subpackage API
 */
class Clinic_Queue_API_Manager {
    
    private static $instance = null;
    
    // Services
    private $doctoronline_service;
    private $relations_service;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_API_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor (Singleton)
     */
    private function __construct() {
        // Initialize services
        $this->doctoronline_service = Clinic_Queue_DoctorOnline_API_Service::get_instance();
        $this->relations_service = Clinic_Queue_JetEngine_Relations_Service::get_instance();
    }
    
    // ============================================================================
    // PUBLIC API - DoctorOnline Proxy API
    // ============================================================================
    
    /**
     * Fetch appointments from external API
     * This is called directly when widget loads - no local storage
     * 
     * @param int|null $calendar_id Calendar ID
     * @param int|null $doctor_id Doctor ID
     * @param int|null $clinic_id Clinic ID
     * @param string $treatment_type Treatment type
     * @return array|null API response data or null on error
     */
    public function fetch_appointments($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        return $this->doctoronline_service->fetch_appointments($calendar_id, $doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Get appointments data by scheduler IDs string
     * Uses schedulerIDsStr (comma-separated) instead of single scheduler ID
     * 
     * @param string $schedulerIDsStr Comma-separated string of scheduler IDs
     * @param int $duration Duration in minutes
     * @param string $fromDateUTC From date in UTC format (ISO 8601)
     * @param string $toDateUTC To date in UTC format (ISO 8601)
     * @return array|null API response data or null on error
     */
    public function get_appointments_data_by_scheduler_ids($schedulerIDsStr, $duration = 30, $fromDateUTC = '', $toDateUTC = '') {
        return $this->doctoronline_service->get_free_time($schedulerIDsStr, $duration, $fromDateUTC, $toDateUTC);
    }
    
    /**
     * Get appointments data - direct API call (no caching, no local storage)
     * 
     * @param int|null $calendar_id Calendar ID
     * @param int|null $doctor_id Doctor ID
     * @param int|null $clinic_id Clinic ID
     * @param string $treatment_type Treatment type
     * @return array|null API response data or null on error
     */
    public function get_appointments_data($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        return $this->fetch_appointments($calendar_id, $doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Get available slots - alias for get_appointments_data
     * 
     * @param int|null $calendar_id Calendar ID
     * @param int|null $doctor_id Doctor ID
     * @param int|null $clinic_id Clinic ID
     * @param string $treatment_type Treatment type
     * @return array|null API response data or null on error
     */
    public function get_available_slots($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        return $this->get_appointments_data($calendar_id, $doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Handle API errors
     * 
     * @param string $error Error message
     * @return null
     */
    public function handle_api_error($error) {
        // Note: Should use client-side logging instead of error_log
        return null;
    }
    
    // ============================================================================
    // PUBLIC API - JetEngine Relations
    // ============================================================================
    
    /**
     * Get scheduler IDs by clinic ID using JetEngine Relations
     * Uses Relation 184: Clinic (parent) -> Scheduler (child)
     * 
     * @param int $clinic_id The clinic ID
     * @return array Array of scheduler IDs (integers)
     */
    public function get_scheduler_ids_by_clinic($clinic_id) {
        return $this->relations_service->get_scheduler_ids_by_clinic($clinic_id);
    }
    
    /**
     * Get scheduler IDs by doctor ID using JetEngine Relations
     * Uses Relation 185: Scheduler (parent) -> Doctor (child)
     * 
     * @param int $doctor_id The doctor ID
     * @return array Array of scheduler IDs (integers)
     */
    public function get_scheduler_ids_by_doctor($doctor_id) {
        return $this->relations_service->get_scheduler_ids_by_doctor($doctor_id);
    }
    
    /**
     * Get schedulers (calendars) by clinic ID using Jet Relations
     * Uses Relation 184: Clinic (parent) -> Scheduler (child)
     * 
     * @param int $clinic_id The clinic ID
     * @return array Array of schedulers with their details
     */
    public function get_schedulers_by_clinic($clinic_id) {
        if (empty($clinic_id) || !is_numeric($clinic_id)) {
            // Debug
            add_action('wp_footer', function() use ($clinic_id) {
                echo '<script>console.log("[API Debug] get_schedulers_by_clinic - invalid clinic_id:", ' . json_encode($clinic_id) . ');</script>';
            }, 999);
            return array();
        }
        
        // Get scheduler IDs using Relations Service
        $scheduler_ids = $this->relations_service->get_scheduler_ids_by_clinic($clinic_id);
        
        // Debug
        add_action('wp_footer', function() use ($clinic_id, $scheduler_ids) {
            echo '<script>console.log("[API Debug] get_schedulers_by_clinic - clinic_id: ' . intval($clinic_id) . ', scheduler_ids count: ' . count($scheduler_ids) . '");</script>';
            echo '<script>console.log("[API Debug] scheduler_ids: ' . json_encode($scheduler_ids) . '");</script>';
        }, 999);
        
        if (empty($scheduler_ids)) {
            return array();
        }
        
        $schedulers = array();
        
        // Fetch details for each scheduler
        foreach ($scheduler_ids as $scheduler_id) {
            if (empty($scheduler_id)) {
                continue;
            }
            
            $scheduler = get_post($scheduler_id);
            if (!$scheduler || $scheduler->post_type !== 'schedules') {
                continue;
            }
            
            // Get scheduler meta data
            $doctor_id = get_post_meta($scheduler_id, 'doctor_id', true);
            $treatment_type = get_post_meta($scheduler_id, 'treatment_type', true);
            $proxy_schedule_id = get_post_meta($scheduler_id, 'proxy_schedule_id', true);
            
            // Get all scheduler meta data
            $schedule_name = get_post_meta($scheduler_id, 'schedule_name', true);
            $manual_calendar_name = get_post_meta($scheduler_id, 'manual_calendar_name', true);
            $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
            
            // Get scheduler treatments repeater (JetEngine format) - all fields
            $scheduler_treatments_raw = get_post_meta($scheduler_id, 'treatments', true);
            $scheduler_treatments = array();
            
            if (!empty($scheduler_treatments_raw) && is_array($scheduler_treatments_raw)) {
                foreach ($scheduler_treatments_raw as $item) {
                    if (isset($item['treatment_type']) && !empty($item['treatment_type'])) {
                        $scheduler_treatments[] = array(
                            'treatment_type' => $item['treatment_type'],
                            'sub_speciality' => isset($item['sub_speciality']) ? $item['sub_speciality'] : '',
                            'cost' => isset($item['cost']) ? $item['cost'] : '',
                            'duration' => isset($item['duration']) ? $item['duration'] : ''
                        );
                    }
                }
            }
            
            // Get doctor details via relation
            $doctor_name = '';
            $doctor_specialty = '';
            $doctor_thumbnail = '';
            if ($doctor_id) {
                $doctor = get_post($doctor_id);
                if ($doctor) {
                    $doctor_name = $doctor->post_title;
                    $doctor_specialty = get_post_meta($doctor_id, 'specialty', true);
                    
                    // Get doctor thumbnail (featured image or meta field)
                    $thumbnail_id = get_post_thumbnail_id($doctor_id);
                    if ($thumbnail_id) {
                        $doctor_thumbnail = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
                    } else {
                        // Fallback: check meta field
                        $thumbnail_meta = get_post_meta($doctor_id, 'thumbnail', true);
                        if ($thumbnail_meta) {
                            if (is_array($thumbnail_meta) && isset($thumbnail_meta['url'])) {
                                $doctor_thumbnail = $thumbnail_meta['url'];
                            } elseif (is_string($thumbnail_meta)) {
                                $doctor_thumbnail = $thumbnail_meta;
                            }
                        }
                    }
                }
            }
            
            // Get clinic details
            $clinic_name = '';
            $clinic_address = '';
            if ($clinic_id) {
                $clinic = get_post($clinic_id);
                if ($clinic) {
                    $clinic_name = $clinic->post_title;
                    // Get clinic address from meta fields
                    $clinic_address = get_post_meta($clinic_id, 'address', true);
                    if (empty($clinic_address)) {
                        // Try alternative field names
                        $clinic_address = get_post_meta($clinic_id, 'clinic_address', true);
                    }
                    if (empty($clinic_address)) {
                        $clinic_address = get_post_meta($clinic_id, 'location', true);
                    }
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
            
            $schedulers[$scheduler_id] = array(
                'id' => $scheduler_id,
                'title' => $scheduler->post_title,
                'doctor_id' => $doctor_id,
                'doctor_name' => $doctor_name,
                'doctor_specialty' => $doctor_specialty,
                'doctor_thumbnail' => $doctor_thumbnail,
                'clinic_id' => $clinic_id,
                'clinic_name' => $clinic_name,
                'clinic_address' => $clinic_address,
                'treatment_type' => $treatment_type,
                'proxy_schedule_id' => $proxy_schedule_id,
                'schedule_name' => $schedule_name,
                'manual_calendar_name' => $manual_calendar_name,
                'schedule_type' => $schedule_type,
                'treatments' => $scheduler_treatments, // Full treatment details with all fields
                'working_days' => $working_days // All working days with time ranges
            );
        }
        
        return $schedulers;
    }
    
    /**
     * Get treatment details from clinic
     * Returns full treatment details (duration, cost, sub_speciality) from clinic's treatments repeater
     * Filtered by scheduler's allowed treatment_types
     * 
     * @param int $clinic_id The clinic ID
     * @param array $allowed_treatment_types Array of treatment_type strings from scheduler
     * @return array Array of treatments with full details
     */
    public function get_clinic_treatments_for_scheduler($clinic_id, $allowed_treatment_types = array()) {
        if (empty($clinic_id) || !is_numeric($clinic_id)) {
            return array();
        }
        
        // Get clinic treatments repeater (JetEngine format)
        $clinic_treatments_raw = get_post_meta($clinic_id, 'treatments', true);
        
        if (empty($clinic_treatments_raw) || !is_array($clinic_treatments_raw)) {
            return array();
        }
        
        $treatments = array();
        
        foreach ($clinic_treatments_raw as $treatment) {
            $treatment_type = isset($treatment['treatment_type']) ? $treatment['treatment_type'] : '';
            
            // Skip if empty
            if (empty($treatment_type)) {
                continue;
            }
            
            // If scheduler has allowed treatments, filter by them
            if (!empty($allowed_treatment_types) && !in_array($treatment_type, $allowed_treatment_types, true)) {
                continue;
            }
            
            $treatments[] = array(
                'treatment_type' => $treatment_type,
                'sub_speciality' => isset($treatment['sub_speciality']) ? $treatment['sub_speciality'] : '',
                'cost' => isset($treatment['cost']) ? $treatment['cost'] : '',
                'duration' => isset($treatment['duration']) ? $treatment['duration'] : ''
            );
        }
        
        return $treatments;
    }
    
    /**
     * Get schedulers (calendars) by doctor ID using Jet Relations
     * Uses Relation 185: Scheduler (parent) -> Doctor (child)
     * 
     * @param int $doctor_id The doctor ID
     * @return array Array of schedulers with their details and all meta fields
     */
    public function get_schedulers_by_doctor($doctor_id) {
        if (empty($doctor_id) || !is_numeric($doctor_id)) {
            return array();
        }
        
        // Get scheduler IDs using Relations Service
        $scheduler_ids = $this->relations_service->get_scheduler_ids_by_doctor($doctor_id);
        
        if (empty($scheduler_ids)) {
            return array();
        }
        
        $schedulers = array();
        
        // Fetch details for each scheduler with all meta fields
        foreach ($scheduler_ids as $scheduler_id) {
            if (empty($scheduler_id)) {
                continue;
            }
            
            $scheduler = get_post($scheduler_id);
            if (!$scheduler || $scheduler->post_type !== 'schedules') {
                continue;
            }
            
            // Get all scheduler meta data
            $doctor_id_meta = get_post_meta($scheduler_id, 'doctor_id', true);
            $clinic_id = get_post_meta($scheduler_id, 'clinic_id', true);
            $treatment_type = get_post_meta($scheduler_id, 'treatment_type', true);
            $proxy_schedule_id = get_post_meta($scheduler_id, 'proxy_schedule_id', true);
            
            // Fallback: try alternative meta key names
            if (empty($proxy_schedule_id)) {
                $proxy_schedule_id = get_post_meta($scheduler_id, 'proxy_scheduler_id', true);
            }
            if (empty($proxy_schedule_id)) {
                $proxy_schedule_id = get_post_meta($scheduler_id, 'source_scheduler_id', true);
            }
            
            // Fallback: try to extract from title if it contains "🆔 {id}"
            if (empty($proxy_schedule_id) && !empty($scheduler->post_title)) {
                // Pattern: "🆔 1006 | ..." - extract the number after 🆔
                if (preg_match('/🆔\s*(\d+)/', $scheduler->post_title, $matches)) {
                    $proxy_schedule_id = $matches[1];
                }
            }
            
            $schedule_name = get_post_meta($scheduler_id, 'schedule_name', true);
            $manual_calendar_name = get_post_meta($scheduler_id, 'manual_calendar_name', true);
            $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
            
            // Get scheduler treatments repeater (JetEngine format) - all fields
            $scheduler_treatments_raw = get_post_meta($scheduler_id, 'treatments', true);
            $scheduler_treatments = array();
            
            if (!empty($scheduler_treatments_raw) && is_array($scheduler_treatments_raw)) {
                foreach ($scheduler_treatments_raw as $item) {
                    if (isset($item['treatment_type']) && !empty($item['treatment_type'])) {
                        $scheduler_treatments[] = array(
                            'treatment_type' => $item['treatment_type'],
                            'sub_speciality' => isset($item['sub_speciality']) ? $item['sub_speciality'] : '',
                            'cost' => isset($item['cost']) ? $item['cost'] : '',
                            'duration' => isset($item['duration']) ? $item['duration'] : ''
                        );
                    }
                }
            }
            
            // Get doctor details via relation
            $doctor_name = '';
            $doctor_specialty = '';
            $doctor_thumbnail = '';
            if ($doctor_id_meta) {
                $doctor = get_post($doctor_id_meta);
                if ($doctor) {
                    $doctor_name = $doctor->post_title;
                    $doctor_specialty = get_post_meta($doctor_id_meta, 'specialty', true);
                    
                    // Get doctor thumbnail (featured image or meta field)
                    $thumbnail_id = get_post_thumbnail_id($doctor_id_meta);
                    if ($thumbnail_id) {
                        $doctor_thumbnail = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
                    } else {
                        // Fallback: check meta field
                        $thumbnail_meta = get_post_meta($doctor_id_meta, 'thumbnail', true);
                        if ($thumbnail_meta) {
                            if (is_array($thumbnail_meta) && isset($thumbnail_meta['url'])) {
                                $doctor_thumbnail = $thumbnail_meta['url'];
                            } elseif (is_string($thumbnail_meta)) {
                                $doctor_thumbnail = $thumbnail_meta;
                            }
                        }
                    }
                }
            }
            
            // Get clinic details via relation
            $clinic_name = '';
            $clinic_address = '';
            if ($clinic_id) {
                $clinic = get_post($clinic_id);
                if ($clinic) {
                    $clinic_name = $clinic->post_title;
                    // Get clinic address from meta fields
                    $clinic_address = get_post_meta($clinic_id, 'address', true);
                    if (empty($clinic_address)) {
                        // Try alternative field names
                        $clinic_address = get_post_meta($clinic_id, 'clinic_address', true);
                    }
                    if (empty($clinic_address)) {
                        $clinic_address = get_post_meta($clinic_id, 'location', true);
                    }
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
            
            $schedulers[$scheduler_id] = array(
                'id' => $scheduler_id,
                'title' => $scheduler->post_title,
                'doctor_id' => $doctor_id_meta,
                'doctor_name' => $doctor_name,
                'doctor_specialty' => $doctor_specialty,
                'doctor_thumbnail' => $doctor_thumbnail,
                'clinic_id' => $clinic_id,
                'clinic_name' => $clinic_name,
                'clinic_address' => $clinic_address,
                'treatment_type' => $treatment_type,
                'proxy_schedule_id' => $proxy_schedule_id,
                'schedule_name' => $schedule_name,
                'manual_calendar_name' => $manual_calendar_name,
                'schedule_type' => $schedule_type,
                'treatments' => $scheduler_treatments, // Full treatment details with all fields
                'working_days' => $working_days // All working days with time ranges
            );
        }
        
        return $schedulers;
    }
}
