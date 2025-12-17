<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Manager for Clinic Queue Management
 * Handles external API communication - direct requests without local storage
 */
class Clinic_Queue_API_Manager {
    
    private static $instance = null;
    
    // External API endpoint (can be configured)
    private $api_endpoint = null; // Will be set via filter or constant
    
    public function __construct() {
        // Get API endpoint from constant, option, or filter
        // Priority: constant > option > filter
        // Default to null so mock data is used unless explicitly configured
        if (defined('CLINIC_QUEUE_API_ENDPOINT') && !empty(CLINIC_QUEUE_API_ENDPOINT)) {
            $this->api_endpoint = CLINIC_QUEUE_API_ENDPOINT;
        } else {
            $this->api_endpoint = get_option('clinic_queue_api_endpoint', null);
            if (empty($this->api_endpoint)) {
                $this->api_endpoint = apply_filters('clinic_queue_api_endpoint', null);
            }
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
     * Fetch appointments from external API
     * This is called directly when widget loads - no local storage
     * 
     * Priority:
     * 1. If API endpoint is configured AND we have calendar/doctor ID -> use real API
     * 2. Otherwise -> use mock data (for development/demo)
     * 
     * Note: The calendar_id or doctor_id is used as the scheduler ID and also as the DoctorOnlineProxyAuthToken
     */
    public function fetch_appointments($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        // If we have a real API endpoint AND a scheduler ID (calendar_id or doctor_id), use real API
        if ($this->api_endpoint && ($calendar_id || $doctor_id)) {
            $real_api_result = $this->fetch_from_real_api($calendar_id, $doctor_id, $clinic_id, $treatment_type);
            // If real API returns data, use it; otherwise fall back to mock
            if ($real_api_result !== null) {
                return $real_api_result;
            }
        }
        
        // Use mock data (for development/demo)
        // This simulates the real API behavior - returns only free slots
        return $this->get_mock_data($doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Fetch from real external API using DoctorOnline Proxy API
     * Uses POST /Scheduler/GetFreeTime endpoint
     * 
     * According to API documentation:
     * - DoctorOnlineProxyAuthToken in header is the scheduler/calendar ID
     * - schedulers array contains scheduler IDs (can be multiple)
     * - drWebBranchID is optional clinic/branch ID
     */
    private function fetch_from_real_api($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        // Build the API URL
        $base_url = rtrim($this->api_endpoint, '/');
        $url = $base_url . '/Scheduler/GetFreeTime';
        
        // Determine scheduler ID - priority: calendar_id > doctor_id
        $scheduler_id = null;
        if ($calendar_id) {
            $scheduler_id = intval($calendar_id);
        } elseif ($doctor_id) {
            $scheduler_id = intval($doctor_id);
        }
        
        // If no scheduler ID, return null
        if (!$scheduler_id) {
            return null;
        }
        
        // Calculate date range (next 30 days from now)
        $from_date = new DateTime('now', new DateTimeZone('Asia/Jerusalem'));
        $to_date = new DateTime('now', new DateTimeZone('Asia/Jerusalem'));
        $to_date->modify('+30 days');
        
        // Calculate ticks for time range (8:00 AM to 8:00 PM)
        // Ticks = (hours * 60 * 60 + minutes * 60) * 10,000,000
        $from_time_ticks = 8 * 60 * 60 * 10000000; // 8:00 AM
        $to_time_ticks = 20 * 60 * 60 * 10000000;  // 8:00 PM
        
        // Build request body according to GetFreeTimeRequest schema
        // Format dates as ISO 8601 with milliseconds (e.g., "2025-11-17T12:29:33.668Z")
        // Note: PHP DateTime doesn't support 'v' (milliseconds), so we use 'u' (microseconds) and take first 3 digits
        $from_date_formatted = $from_date->format('Y-m-d\TH:i:s') . '.' . substr($from_date->format('u'), 0, 3) . 'Z';
        $to_date_formatted = $to_date->format('Y-m-d\TH:i:s') . '.' . substr($to_date->format('u'), 0, 3) . 'Z';
        
        $request_body = array(
            'schedulers' => array($scheduler_id), // Array of scheduler IDs
            'duration' => 30, // Slot duration in minutes (default 30)
            'fromDate' => $from_date_formatted, // ISO 8601 with milliseconds
            'toDate' => $to_date_formatted, // ISO 8601 with milliseconds
            'fromTime' => array(
                'ticks' => $from_time_ticks // TimeSpan ticks (8:00 AM)
            ),
            'toTime' => array(
                'ticks' => $to_time_ticks // TimeSpan ticks (8:00 PM)
            )
        );
        
        // Add optional drWebBranchID if clinic_id is provided
        if ($clinic_id) {
            $request_body['drWebBranchID'] = intval($clinic_id);
        } else {
            $request_body['drWebBranchID'] = 0; // Default to 0 if not provided
        }
        
        // Build headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            // DoctorOnlineProxyAuthToken is the scheduler/calendar ID (not a separate token)
            'DoctorOnlineProxyAuthToken' => (string)$scheduler_id
        );
        
        // Make API request
        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => $headers,
            'body' => json_encode($request_body)
        ));
        
        if (is_wp_error($response)) {
            error_log('[Clinic Queue API] Error: ' . $response->get_error_message());
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('[Clinic Queue API] HTTP Error: ' . $response_code . ' - Response: ' . $body);
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            error_log('[Clinic Queue API] Invalid JSON response: ' . $body);
            return null;
        }
        
        // Validate and format response
        return $this->validate_doctoronline_api_response($data);
    }
    
    /**
     * Validate DoctorOnline API response format
     * Converts BaseListResponse<FreeTimeSlotModel> to our internal format
     * 
     * Response structure:
     * {
     *   "code": "Success" | "Undefined" | ...,
     *   "error": "string" | null,
     *   "result": [FreeTimeSlotModel],
     *   "serverTime": "string",
     *   "nextPageToken": "string" | null
     * }
     */
    private function validate_doctoronline_api_response($data) {
        // Check response code - accept "Success" or check for errors
        if (isset($data['code']) && $data['code'] !== 'Success') {
            $error_msg = $data['error'] ?? 'Unknown error';
            error_log('[Clinic Queue API] API Error (code: ' . $data['code'] . '): ' . $error_msg);
            // Still try to parse result if available, but log the error
        }
        
        // Check if we have result array
        if (!isset($data['result']) || !is_array($data['result'])) {
            return null;
        }
        
        // Group slots by date
        $slots_by_date = array();
        foreach ($data['result'] as $slot) {
            if (!isset($slot['from']) || !isset($slot['to'])) {
                continue;
            }
            
            // Parse datetime
            $from_datetime = new DateTime($slot['from']);
            $to_datetime = new DateTime($slot['to']);
            
            // Get date (YYYY-MM-DD)
            $date = $from_datetime->format('Y-m-d');
            
            // Get time (HH:MM)
            $time = $from_datetime->format('H:i');
            
            // Initialize date array if not exists
            if (!isset($slots_by_date[$date])) {
                $slots_by_date[$date] = array();
            }
            
            // Add slot (all slots from API are free/available)
            $slots_by_date[$date][] = array(
                'time' => $time,
                'schedulerID' => $slot['schedulerID'] ?? null,
                'drWebBranchID' => $slot['drWebBranchID'] ?? null,
                'from' => $slot['from'],
                'to' => $slot['to']
            );
        }
        
        // Convert to our expected format
        $formatted = array(
            'timezone' => 'Asia/Jerusalem',
            'days' => array()
        );
        
        foreach ($slots_by_date as $date => $slots) {
            // Sort slots by time
            usort($slots, function($a, $b) {
                return strcmp($a['time'], $b['time']);
            });
            
            $formatted['days'][] = array(
                'date' => $date,
                'slots' => $slots
            );
        }
        
        // Sort days by date
        usort($formatted['days'], function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $formatted;
    }
    
    /**
     * Validate API response format (legacy - for backward compatibility)
     */
    private function validate_api_response($data) {
        // This is for the old format, but we'll use validate_doctoronline_api_response instead
        return $this->validate_doctoronline_api_response($data);
    }
    
    /**
     * Get mock data from JSON file (simulating API call)
     * Returns only available (free) slots, matching the real API behavior
     */
    private function get_mock_data($doctor_id, $clinic_id, $treatment_type = '') {
        // Use the new flat format mock-data.json
        $json_file = plugin_dir_path(__FILE__) . '../data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return null;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['result'])) {
            return null;
        }
        
        // Find schedulers that match the criteria from metadata file
        $schedulers = $this->get_all_calendars();
        $matching_schedulers = [];
        
        foreach ($schedulers as $scheduler) {
            $match = true;
            if ($doctor_id && $scheduler['doctor_id'] != $doctor_id) $match = false;
            if ($clinic_id && $scheduler['clinic_id'] != $clinic_id) $match = false;
            if ($treatment_type && $scheduler['treatment_type'] != $treatment_type) $match = false;
            
            if ($match) {
                $matching_schedulers[] = $scheduler['id']; // This is the scheduler ID
            }
        }
        
        // Process appointments - filter by matching scheduler IDs
        // Note: The mock data has schedulerID field
        $filtered_results = [];
        
        foreach ($data['result'] as $slot) {
            $slot_scheduler_id = $slot['schedulerID'] ?? null;
            
            // If we have specific matching schedulers, filter by them
            // Otherwise (if no filters provided), include everything (or filtered by partial matches)
            // But in the context of "fetch_appointments", we usually target a specific doctor/clinic.
            
            // Loose matching: if doctor_id is provided, we expect schedulerID to match doctor_id (simple mapping for mock)
            // OR match against the metadata we found
            
            $include_slot = false;
            
            if (!empty($matching_schedulers)) {
                // If we found metadata matches, use them
                if (in_array($slot_scheduler_id, $matching_schedulers)) {
                    $include_slot = true;
                }
            } else {
                // Fallback simple logic if metadata not found or empty filters
                // Assume doctor_id matches schedulerID for mock purposes
                if ($doctor_id && $slot_scheduler_id == $doctor_id) {
                    $include_slot = true;
                } elseif (!$doctor_id) {
                    $include_slot = true; // Return all if no filter?
                }
            }
            
            if ($include_slot) {
                $filtered_results[] = $slot;
            }
        }
        
        // Convert to legacy grouped format using the validator helper
        return $this->validate_doctoronline_api_response(['code' => 'Success', 'result' => $filtered_results]);
    }
    
    
    /**
     * Get appointments data - direct API call (no caching, no local storage)
     */
    public function get_appointments_data($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        // Fetch directly from API - no local storage
        return $this->fetch_appointments($calendar_id, $doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Get available slots - alias for get_appointments_data
     */
    public function get_available_slots($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        return $this->get_appointments_data($calendar_id, $doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Handle API errors
     */
    public function handle_api_error($error) {
        error_log('[Clinic Queue API] Error: ' . $error);
        return null;
    }
    
    /**
     * Get all doctors from mock data (for development)
     * In production, this would come from the API
     */
    public function get_all_doctors() {
        $json_file = plugin_dir_path(__FILE__) . '../data/mock-schedulers.json';
        
        if (!file_exists($json_file)) {
            return array();
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['schedulers'])) {
            return array();
        }
        
        $doctors = array();
        foreach ($data['schedulers'] as $calendar) {
            // Mock mapping: doctor_id IS the scheduler id for simplicity in this mock context
            $doctor_id = $calendar['id']; 
            if (!isset($doctors[$doctor_id])) {
                $doctors[$doctor_id] = array(
                    'id' => $doctor_id,
                    'name' => $calendar['name'] ?? '',
                    'specialty' => $calendar['specialty'] ?? ''
                );
            }
        }
        
        return $doctors;
    }
    
    /**
     * Get all clinics from mock data (for development)
     * In production, this would come from the API
     */
    public function get_all_clinics() {
        $json_file = plugin_dir_path(__FILE__) . '../data/mock-schedulers.json';
        
        if (!file_exists($json_file)) {
            return array();
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['schedulers'])) {
            return array();
        }
        
        $clinics = array();
        foreach ($data['schedulers'] as $calendar) {
            $clinic_id = $calendar['clinic_id'] ?? '';
            if ($clinic_id && !isset($clinics[$clinic_id])) {
                $clinics[$clinic_id] = array(
                    'id' => $clinic_id,
                    'name' => $calendar['clinic_name'] ?? '',
                    'address' => $calendar['clinic_address'] ?? ''
                );
            }
        }
        
        return $clinics;
    }
    
    /**
     * Get all treatment types from mock data (for development)
     * In production, this would come from the API
     */
    public function get_all_treatment_types() {
        $json_file = plugin_dir_path(__FILE__) . '../data/mock-schedulers.json';
        
        if (!file_exists($json_file)) {
            return array();
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['schedulers'])) {
            return array();
        }
        
        $treatment_types = array();
        foreach ($data['schedulers'] as $calendar) {
            $treatment_type = $calendar['treatment_type'] ?? '';
            if ($treatment_type && !in_array($treatment_type, $treatment_types)) {
                $treatment_types[] = $treatment_type;
            }
        }
        
        return $treatment_types;
    }
    
    /**
     * Get all calendars from mock data (for development)
     * In production, this would come from the API
     */
    public function get_all_calendars() {
        $json_file = plugin_dir_path(__FILE__) . '../data/mock-schedulers.json';
        
        if (!file_exists($json_file)) {
            return array();
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['schedulers'])) {
            return array();
        }
        
        $calendars = array();
        foreach ($data['schedulers'] as $calendar) {
            $calendars[] = array(
                'id' => $calendar['id'] ?? '',
                'doctor_id' => $calendar['id'] ?? '', // Map scheduler ID to doctor ID
                'doctor_name' => $calendar['name'] ?? '',
                'doctor_specialty' => $calendar['specialty'] ?? '',
                'clinic_id' => $calendar['clinic_id'] ?? '',
                'clinic_name' => $calendar['clinic_name'] ?? '',
                'clinic_address' => $calendar['clinic_address'] ?? '',
                'treatment_type' => $calendar['treatment_type'] ?? ''
            );
        }
        
        return $calendars;
    }
}
