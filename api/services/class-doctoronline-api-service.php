<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/services/class-encryption-service.php';

/**
 * DoctorOnline Proxy API Service
 * 
 * שירות מרכזי לכל פעולות DoctorOnline Proxy API
 * מטפל בשליפת תורים, שעות זמינות, ואימות תגובות
 * 
 * @package Clinic_Queue_Management
 * @subpackage API\Services
 */
class Clinic_Queue_DoctorOnline_API_Service {
    
    private static $instance = null;
    
    // External API endpoint (can be configured)
    private $api_endpoint = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_DoctorOnline_API_Service
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
        // Get API endpoint from constant, option, or filter
        // Priority: hardcoded constant > legacy constant > option > filter
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
     * Get authentication token
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
        
        // Priority 3: WordPress option (encrypted)
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        if ($encrypted_token) {
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
     * Get appointments data by scheduler IDs string
     * Uses schedulerIDsStr (comma-separated) instead of single scheduler ID
     * 
     * @param string $schedulerIDsStr Comma-separated string of scheduler IDs
     * @param int $duration Duration in minutes
     * @param string $fromDateUTC From date in UTC format (ISO 8601)
     * @param string $toDateUTC To date in UTC format (ISO 8601)
     * @return array|WP_Error API response data or WP_Error with proxy_response in error_data
     */
    public function get_free_time($schedulerIDsStr, $duration = 30, $fromDateUTC = '', $toDateUTC = '') {
        if (empty($schedulerIDsStr)) {
            return new WP_Error('missing_params', 'schedulerIDsStr is required', array('status' => 400));
        }
        
        // If no dates provided, calculate default range (3 weeks)
        if (empty($fromDateUTC) || empty($toDateUTC)) {
            $from_date = new DateTime('now', new DateTimeZone('UTC'));
            $to_date = new DateTime('now', new DateTimeZone('UTC'));
            $to_date->modify('+21 days'); // 3 weeks
            $to_date->setTime(23, 59, 59); // End of day
            
            $fromDateUTC = $from_date->format('Y-m-d\TH:i:s\Z');
            $toDateUTC = $to_date->format('Y-m-d\TH:i:s\Z');
        }
        
        if (empty($this->api_endpoint)) {
            return new WP_Error('no_endpoint', 'API endpoint not configured', array('status' => 500));
        }
        
        try {
            $base_url = rtrim($this->api_endpoint, '/');
            $query_params = array(
                'schedulerIDsStr' => (string)$schedulerIDsStr,
                'duration' => intval($duration),
                'fromDateUTC' => $fromDateUTC,
                'toDateUTC' => $toDateUTC
            );
            $url = $base_url . '/Scheduler/GetFreeTime?' . http_build_query($query_params);
            
            $scheduler_ids = explode(',', $schedulerIDsStr);
            $first_scheduler_id = trim($scheduler_ids[0]);
            $auth_token = $this->get_auth_token(intval($first_scheduler_id));
            if (!$auth_token) {
                $auth_token = $first_scheduler_id;
            }
            
            $headers = array(
                'Accept' => 'application/json',
                'DoctorOnlineProxyAuthToken' => $auth_token
            );
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => $headers
            ));
            
            if (is_wp_error($response)) {
                return new WP_Error(
                    'proxy_request_failed',
                    $response->get_error_message(),
                    array('status' => 502, 'proxy_response' => array('wp_error' => $response->get_error_message()))
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $msg = isset($data['error']) ? $data['error'] : 'Proxy returned HTTP ' . $response_code;
                return new WP_Error(
                    'proxy_http_error',
                    $msg,
                    array(
                        'status' => $response_code,
                        'proxy_response' => $data ? $data : array('raw_body' => $body)
                    )
                );
            }
            
            if (!$data || !is_array($data)) {
                return new WP_Error(
                    'proxy_invalid_json',
                    'Invalid JSON response from proxy',
                    array('status' => 502, 'proxy_response' => array('raw_body' => $body))
                );
            }
            
            $validated = $this->validate_response($data, intval($duration));
            if ($validated === null) {
                $proxy_error = isset($data['error']) ? $data['error'] : 'Invalid or empty result from proxy';
                $proxy_code = isset($data['code']) ? $data['code'] : 'Error';
                return new WP_Error(
                    'proxy_error',
                    $proxy_error,
                    array(
                        'status' => ($proxy_code === 'ClientError' ? 400 : 500),
                        'proxy_response' => $data
                    )
                );
            }
            
            return $validated;
        } catch (Exception $e) {
            return new WP_Error(
                'proxy_exception',
                $e->getMessage(),
                array('status' => 500, 'proxy_response' => array('exception' => $e->getMessage()))
            );
        } catch (Error $e) {
            return new WP_Error(
                'proxy_exception',
                $e->getMessage(),
                array('status' => 500, 'proxy_response' => array('exception' => $e->getMessage()))
            );
        }
    }
    
    /**
     * Fetch appointments from external API (legacy method)
     * 
     * @param int|null $calendar_id Calendar ID
     * @param int|null $doctor_id Doctor ID
     * @param int|null $clinic_id Clinic ID
     * @param string $treatment_type Treatment type
     * @return array|null API response data or null on error
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
        return $this->get_mock_data($doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Fetch from real external API using DoctorOnline Proxy API
     * Uses GET /Scheduler/GetFreeTime endpoint
     * 
     * @param int|null $calendar_id Calendar ID
     * @param int|null $doctor_id Doctor ID
     * @param int|null $clinic_id Clinic ID
     * @param string $treatment_type Treatment type
     * @return array|null API response data or null on error
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
                'schedulerIDsStr' => (string)$scheduler_id,
                'duration' => 30,
                'fromDateUTC' => $from_date_formatted,
                'toDateUTC' => $to_date_formatted
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
                return null;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data) {
                return null;
            }
            
            // Validate and format response (pass duration for 'to' time calculation)
            return $this->validate_response($data, 30);
        } catch (Exception $e) {
            return null;
        } catch (Error $e) {
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
     * 
     * @param array $data Response data
     * @param int $duration Slot duration in minutes (default 30)
     * @return array|null Formatted response or null on error
     */
    private function validate_response($data, $duration = 30) {
        // Check response code - accept "Success" or check for errors
        if (isset($data['code']) && $data['code'] !== 'Success') {
            // Still try to parse result if available
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
     * Get mock data from JSON file (simulating API call)
     * Returns only available (free) slots, matching the real API behavior
     * 
     * @param int|null $doctor_id Doctor ID
     * @param int|null $clinic_id Clinic ID
     * @param string $treatment_type Treatment type
     * @return array|null Mock data or null on error
     */
    private function get_mock_data($doctor_id, $clinic_id, $treatment_type = '') {
        // Use the new flat format mock-data.json
        $json_file = plugin_dir_path(__FILE__) . '../../data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return null;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['result'])) {
            return null;
        }
        
        // Find schedulers that match the criteria from metadata file
        // Note: This is a simplified mock - in real implementation, you'd query WordPress
        $matching_schedulers = array();
        
        // Process appointments - filter by matching scheduler IDs
        $filtered_results = array();
        
        foreach ($data['result'] as $slot) {
            $slot_scheduler_id = $slot['schedulerID'] ?? null;
            
            // Simple matching logic for mock
            $include_slot = false;
            if ($doctor_id && $slot_scheduler_id == $doctor_id) {
                $include_slot = true;
            } elseif (!$doctor_id) {
                $include_slot = true; // Return all if no filter
            }
            
            if ($include_slot) {
                $filtered_results[] = $slot;
            }
        }
        
        // Convert to legacy grouped format using the validator helper
        return $this->validate_response(array('code' => 'Success', 'result' => $filtered_results));
    }
}

