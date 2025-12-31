<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/services/class-encryption-service.php';

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
        // Priority: hardcoded constant > legacy constant > option > filter
        // ⚠️ TEMPORARY: Using hardcoded constant for development
        if (defined('CLINIC_QUEUE_API_ENDPOINT') && !empty(CLINIC_QUEUE_API_ENDPOINT)) {
            $this->api_endpoint = CLINIC_QUEUE_API_ENDPOINT;
        } elseif (defined('DOCTOR_ONLINE_PROXY_BASE_URL') && !empty(DOCTOR_ONLINE_PROXY_BASE_URL)) {
            $this->api_endpoint = DOCTOR_ONLINE_PROXY_BASE_URL;
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
     * Get authentication token
     * 
     * ⚠️ TEMPORARY: Using hardcoded constant for development
     * TODO: Replace with settings page implementation after core functionality is working
     * 
     * Priority: hardcoded constant > legacy constant > WordPress option > filter > fallback to scheduler_id
     * 
     * @param int|null $scheduler_id Optional scheduler ID for fallback
     * @return string|null Authentication token
     */
    private function get_auth_token($scheduler_id = null) {
        // Priority 1: Hardcoded constant (TEMPORARY - for development)
        if (defined('CLINIC_QUEUE_API_TOKEN') && !empty(CLINIC_QUEUE_API_TOKEN) && CLINIC_QUEUE_API_TOKEN !== 'YOUR_API_TOKEN_HERE') {
            return CLINIC_QUEUE_API_TOKEN;
        }
        
        // Priority 2: Legacy constant (for backward compatibility)
        if (defined('DOCTOR_ONLINE_PROXY_AUTH_TOKEN') && !empty(DOCTOR_ONLINE_PROXY_AUTH_TOKEN)) {
            return DOCTOR_ONLINE_PROXY_AUTH_TOKEN;
        }
        
        // Priority 3: WordPress option (encrypted - will be used in future when settings page is implemented)
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        if ($encrypted_token) {
            // Use Encryption Service to decrypt
            $encryption_service = Clinic_Queue_Encryption_Service::get_instance();
            $token = $encryption_service->decrypt_token($encrypted_token);
            if ($token) {
                return $token;
            }
        }
        
        // Priority 4: Legacy WordPress option (non-encrypted)
        $option_token = get_option('clinic_queue_api_token', null);
        if (!empty($option_token)) {
            return $option_token;
        }
        
        // Priority 5: Filter (programmatic override)
        $filter_token = apply_filters('clinic_queue_api_token', null, $scheduler_id);
        if (!empty($filter_token)) {
            return $filter_token;
        }
        
        // Priority 6: Fallback to scheduler_id (legacy behavior)
        if ($scheduler_id) {
            return (string)$scheduler_id;
        }
        
        return null;
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
     * Uses GET /Scheduler/GetFreeTime endpoint
     * 
     * According to API documentation (Updated 2025-12):
     * - DoctorOnlineProxyAuthToken in header is the authentication token
     * - schedulerIDsStr is a comma-separated string of scheduler IDs
     * - duration is slot duration in minutes
     * - fromDateUTC and toDateUTC are in ISO 8601 format (e.g., "2025-11-25T00:00:00Z")
     */
    private function fetch_from_real_api($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        try {
            // Build the API URL
            $base_url = rtrim($this->api_endpoint, '/');
            
            // Determine scheduler ID - priority: calendar_id > doctor_id
            $scheduler_id = null;
            if ($calendar_id) {
                $scheduler_id = intval($calendar_id);
            } elseif ($doctor_id) {
                $scheduler_id = intval($doctor_id);
            }
            
            // If no scheduler ID, return null
            if (!$scheduler_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Clinic Queue API] No scheduler ID provided');
                }
                return null;
            }
            
            // Calculate date range (next 30 days from now)
            // Use UTC timezone as required by API
            $from_date = new DateTime('now', new DateTimeZone('UTC'));
            $to_date = new DateTime('now', new DateTimeZone('UTC'));
            $to_date->modify('+30 days');
            
            // Format dates as ISO 8601 in UTC (e.g., "2025-11-25T00:00:00Z")
            $from_date_formatted = $from_date->format('Y-m-d\TH:i:s\Z');
            $to_date_formatted = $to_date->format('Y-m-d\TH:i:s\Z');
            
            // Build query parameters
            $query_params = array(
                'schedulerIDsStr' => (string)$scheduler_id, // Comma-separated string of scheduler IDs
                'duration' => 30, // Slot duration in minutes (default 30)
                'fromDateUTC' => $from_date_formatted, // From date in UTC
                'toDateUTC' => $to_date_formatted // To date in UTC
            );
            
            // Build URL with query parameters
            $url = $base_url . '/Scheduler/GetFreeTime?' . http_build_query($query_params);
            
            // Get authentication token (with fallback to scheduler_id)
            $auth_token = $this->get_auth_token($scheduler_id);
            if (!$auth_token) {
                $auth_token = (string)$scheduler_id; // Fallback to scheduler_id for backward compatibility
            }
            
            // Build headers
            $headers = array(
                'Accept' => 'application/json',
                'DoctorOnlineProxyAuthToken' => $auth_token
            );
            
            // Make API request (GET instead of POST)
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => $headers
            ));
            
            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Clinic Queue API] Error: ' . $response->get_error_message());
                }
                return null;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Clinic Queue API] HTTP Error: ' . $response_code . ' - Response: ' . $body);
                }
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Clinic Queue API] Invalid JSON response: ' . $body);
                }
                return null;
            }
            
            // Validate and format response (pass duration for 'to' time calculation)
            return $this->validate_doctoronline_api_response($data, 30);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Clinic Queue API] Exception in fetch_from_real_api: ' . $e->getMessage());
            }
            return null;
        } catch (Error $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Clinic Queue API] Fatal error in fetch_from_real_api: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    
    /**
     * Validate DoctorOnline API response format
     * Converts ListResultBaseResponse<FreeTimeSlotModel> to our internal format
     * 
     * Response structure (Updated 2025-12):
     * {
     *   "code": "Success" | "Undefined" | ...,
     *   "error": "string" | null,
     *   "result": [
     *     {
     *       "from": "2025-12-28T16:00:55.185Z",
     *       "schedulerID": 0
     *     }
     *   ]
     * }
     * 
     * Note: The 'to' field was removed from FreeTimeSlotModel.
     *       We calculate 'to' based on 'from' + duration (default 30 minutes).
     */
    private function validate_doctoronline_api_response($data, $duration = 30) {
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
            if (!isset($slot['from'])) {
                continue;
            }
            
            // Parse datetime
            $from_datetime = new DateTime($slot['from']);
            
            // Calculate 'to' time based on duration (default 30 minutes)
            $to_datetime = clone $from_datetime;
            $to_datetime->modify('+' . $duration . ' minutes');
            
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
                'from' => $slot['from'],
                'to' => $to_datetime->format('Y-m-d\TH:i:s\Z') // Calculated 'to' time
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
     * 
     * @param array $data Response data from API
     * @param int $duration Slot duration in minutes (default 30)
     * @return array|null Formatted response or null on error
     */
    private function validate_api_response($data, $duration = 30) {
        // This is for the old format, but we'll use validate_doctoronline_api_response instead
        return $this->validate_doctoronline_api_response($data, $duration);
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
