<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-service.php';
require_once __DIR__ . '/../models/class-scheduler-model.php';
require_once __DIR__ . '/../models/class-response-model.php';

/**
 * Scheduler Service
 * שירות לניהול יומנים
 */
class Clinic_Queue_Scheduler_Service extends Clinic_Queue_Base_Service {
    
    /**
     * Get all source calendars
     * 
     * @param int $source_creds_id Source credentials ID
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_all_source_calendars($source_creds_id, $scheduler_id) {
        $endpoint = '/Scheduler/GetAllSourceCalendars?sourceCredsID=' . intval($source_creds_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_Model');
    }
    
    /**
     * Get DRWeb calendar reasons
     * 
     * @param int $source_creds_id Source credentials ID
     * @param string $drweb_calendar_id DRWeb calendar ID
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_drweb_calendar_reasons($source_creds_id, $drweb_calendar_id, $scheduler_id) {
        $endpoint = '/Scheduler/GetDRWebCalendarReasons?sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_Model');
    }
    
    /**
     * Get DRWeb calendar active hours
     * 
     * @param int $source_creds_id Source credentials ID
     * @param string $drweb_calendar_id DRWeb calendar ID
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_drweb_calendar_active_hours($source_creds_id, $drweb_calendar_id, $scheduler_id) {
        $endpoint = '/Scheduler/GetDRWebCalendarActiveHours?sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_Model');
    }
    
    /**
     * Create scheduler
     * 
     * @param Clinic_Queue_Create_Scheduler_Model $scheduler_model
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Result_Response_Model|WP_Error
     */
    public function create_scheduler($scheduler_model, $scheduler_id) {
        $validation = $scheduler_model->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $data = $scheduler_model->to_array();
        $response = $this->make_request('POST', '/Scheduler/Create', $data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_Model');
    }
    
    /**
     * Update scheduler
     * 
     * @param Clinic_Queue_Update_Scheduler_Model $scheduler_model
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Base_Response_Model|WP_Error
     */
    public function update_scheduler($scheduler_model, $scheduler_id) {
        $validation = $scheduler_model->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $data = $scheduler_model->to_array();
        $response = $this->make_request('POST', '/Scheduler/Update', $data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_Model');
    }
    
    /**
     * Set active hours for scheduler
     * 
     * @param Clinic_Queue_Update_Active_Hours_Model $active_hours_model
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Base_Response_Model|WP_Error
     */
    public function set_active_hours($active_hours_model, $scheduler_id) {
        $validation = $active_hours_model->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $data = $active_hours_model->to_array();
        $response = $this->make_request('POST', '/Scheduler/SetActiveHours', $data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_Model');
    }
    
    /**
     * Get free time slots
     * 
     * @param Clinic_Queue_Get_Free_Time_Model $free_time_model
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_free_time($free_time_model, $scheduler_id) {
        $validation = $free_time_model->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        // If no API endpoint is configured, return mock data
        if (!$this->api_endpoint) {
            return $this->get_mock_free_time($free_time_model);
        }
        
        $params = array(
            'schedulerID' => intval($free_time_model->schedulerID),
            'duration' => intval($free_time_model->duration),
            'fromDateUTC' => $free_time_model->fromDateUTC,
            'toDateUTC' => $free_time_model->toDateUTC,
        );
        
        $endpoint = '/Scheduler/GetFreeTime?' . http_build_query($params);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_Model');
    }
    
    /**
     * Check if slot is available
     * 
     * @param Clinic_Queue_Check_Slot_Available_Model $slot_model
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Result_Response_Model|WP_Error
     */
    public function check_slot_available($slot_model, $scheduler_id) {
        $validation = $slot_model->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $params = array(
            'schedulerID' => intval($slot_model->schedulerID),
            'fromUTC' => $slot_model->fromUTC,
            'duration' => intval($slot_model->duration),
        );
        
        $endpoint = '/Scheduler/CheckIsSlotAvailable?' . http_build_query($params);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_Model');
    }
    
    /**
     * Get scheduler properties
     * 
     * @param int $scheduler_id Scheduler ID
     * @return Clinic_Queue_Result_Response_Model|WP_Error
     */
    public function get_scheduler_properties($scheduler_id) {
        $endpoint = '/Scheduler/GetSchedulersProperties?schedulerID=' . intval($scheduler_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_Model');
    }

    /**
     * Get mock free time data
     */
    private function get_mock_free_time($free_time_model) {
        $mock_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        if (!file_exists($mock_file)) {
            return new WP_Error('mock_data_missing', 'Mock data file missing');
        }

        $json_content = file_get_contents($mock_file);
        $data = json_decode($json_content, true);

        if (!$data || !isset($data['result'])) {
            return new WP_Error('mock_data_invalid', 'Invalid mock data format');
        }

        // Filter by schedulerID and date range
        $filtered_slots = array_filter($data['result'], function($slot) use ($free_time_model) {
            // Check scheduler ID (approximate check, in real app would be exact)
            // Mock data has schedulerID 1..8
            if (isset($slot['schedulerID']) && $slot['schedulerID'] != $free_time_model->schedulerID) {
                return false;
            }

            // Check date range
            $slot_from = strtotime($slot['from']);
            $req_from = strtotime($free_time_model->fromDateUTC);
            $req_to = strtotime($free_time_model->toDateUTC);

            return $slot_from >= $req_from && $slot_from <= $req_to;
        });

        // Reset keys
        $data['result'] = array_values($filtered_slots);
        
        return $this->handle_response($data, 'Clinic_Queue_List_Response_Model');
    }
    
    /**
     * ============================================
     * Google Calendar Integration Methods
     * ============================================
     */
    
    /**
     * שמירת Google Calendar credentials
     * שומר tokens מוצפנים ופרטי חיבור
     * 
     * @param int $scheduler_id מזהה scheduler (post ID)
     * @param array $credentials מערך עם: access_token, refresh_token, expires_at, user_email, timezone
     * @return bool true אם הצליח, false אם נכשל
     */
    public function save_google_credentials($scheduler_id, $credentials) {
        if (empty($scheduler_id) || !is_array($credentials)) {
            return false;
        }
        
        // Load Google Calendar Service for encryption
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-google-calendar-service.php';
        $google_service = new Clinic_Queue_Google_Calendar_Service();
        
        // הצפנת tokens
        $encrypted_access = '';
        $encrypted_refresh = '';
        
        if (isset($credentials['access_token'])) {
            $encrypted_access = $google_service->encrypt_token($credentials['access_token']);
        }
        
        if (isset($credentials['refresh_token'])) {
            $encrypted_refresh = $google_service->encrypt_token($credentials['refresh_token']);
        }
        
        // שמירת כל השדות
        update_post_meta($scheduler_id, 'google_connected', true);
        update_post_meta($scheduler_id, 'google_calendar_id', 'primary');
        update_post_meta($scheduler_id, 'google_access_token', $encrypted_access);
        update_post_meta($scheduler_id, 'google_refresh_token', $encrypted_refresh);
        update_post_meta($scheduler_id, 'google_token_expires_at', isset($credentials['expires_at']) ? $credentials['expires_at'] : '');
        update_post_meta($scheduler_id, 'google_user_email', isset($credentials['user_email']) ? sanitize_email($credentials['user_email']) : '');
        update_post_meta($scheduler_id, 'google_timezone', isset($credentials['timezone']) ? sanitize_text_field($credentials['timezone']) : 'Asia/Jerusalem');
        update_post_meta($scheduler_id, 'google_connected_at', current_time('mysql'));
        update_post_meta($scheduler_id, 'google_sync_status', 'active');
        
        return true;
    }
    
    /**
     * קריאת Google Calendar credentials
     * מחזיר tokens מפוענחים
     * 
     * @param int $scheduler_id מזהה scheduler (post ID)
     * @return array|false מערך עם credentials או false אם לא קיים
     */
    public function get_google_credentials($scheduler_id) {
        if (empty($scheduler_id)) {
            return false;
        }
        
        $connected = get_post_meta($scheduler_id, 'google_connected', true);
        
        if (!$connected) {
            return false;
        }
        
        // Load Google Calendar Service for decryption
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-google-calendar-service.php';
        $google_service = new Clinic_Queue_Google_Calendar_Service();
        
        $encrypted_access = get_post_meta($scheduler_id, 'google_access_token', true);
        $encrypted_refresh = get_post_meta($scheduler_id, 'google_refresh_token', true);
        
        return array(
            'connected' => true,
            'calendar_id' => 'primary',
            'access_token' => $google_service->decrypt_token($encrypted_access),
            'refresh_token' => $google_service->decrypt_token($encrypted_refresh),
            'expires_at' => get_post_meta($scheduler_id, 'google_token_expires_at', true),
            'user_email' => get_post_meta($scheduler_id, 'google_user_email', true),
            'timezone' => get_post_meta($scheduler_id, 'google_timezone', true),
            'connected_at' => get_post_meta($scheduler_id, 'google_connected_at', true),
            'sync_status' => get_post_meta($scheduler_id, 'google_sync_status', true),
            'last_error' => get_post_meta($scheduler_id, 'google_last_error', true)
        );
    }
    
    /**
     * בדיקה האם token עדיין תקף
     * 
     * @param int $scheduler_id מזהה scheduler (post ID)
     * @return bool true אם תקף, false אם פג או לא קיים
     */
    public function is_google_token_valid($scheduler_id) {
        if (empty($scheduler_id)) {
            return false;
        }
        
        $expires_at = get_post_meta($scheduler_id, 'google_token_expires_at', true);
        
        if (empty($expires_at)) {
            return false;
        }
        
        $expires_timestamp = strtotime($expires_at);
        $current_timestamp = time();
        
        // נותן buffer של 5 דקות
        return $expires_timestamp > ($current_timestamp + 300);
    }
    
    /**
     * עדכון access token אחרי refresh
     * 
     * @param int $scheduler_id מזהה scheduler
     * @param string $new_access_token Token חדש
     * @param string $new_expires_at תאריך תפוגה חדש
     * @return bool
     */
    public function update_google_access_token($scheduler_id, $new_access_token, $new_expires_at) {
        if (empty($scheduler_id) || empty($new_access_token)) {
            return false;
        }
        
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-google-calendar-service.php';
        $google_service = new Clinic_Queue_Google_Calendar_Service();
        
        $encrypted_access = $google_service->encrypt_token($new_access_token);
        
        update_post_meta($scheduler_id, 'google_access_token', $encrypted_access);
        update_post_meta($scheduler_id, 'google_token_expires_at', $new_expires_at);
        update_post_meta($scheduler_id, 'google_sync_status', 'active');
        
        // מנקה שגיאות קודמות
        delete_post_meta($scheduler_id, 'google_last_error');
        
        return true;
    }
    
    /**
     * עדכון סטטוס סנכרון
     * 
     * @param int $scheduler_id מזהה scheduler
     * @param string $status active|expired|error
     * @return bool
     */
    public function update_google_sync_status($scheduler_id, $status) {
        if (empty($scheduler_id)) {
            return false;
        }
        
        $valid_statuses = array('active', 'expired', 'error');
        
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        update_post_meta($scheduler_id, 'google_sync_status', $status);
        
        return true;
    }
    
    /**
     * שמירת שגיאה
     * 
     * @param int $scheduler_id מזהה scheduler
     * @param string $error_message הודעת שגיאה
     * @return bool
     */
    public function log_google_error($scheduler_id, $error_message) {
        if (empty($scheduler_id)) {
            return false;
        }
        
        update_post_meta($scheduler_id, 'google_last_error', sanitize_text_field($error_message));
        update_post_meta($scheduler_id, 'google_sync_status', 'error');
        
        return true;
    }
    
    /**
     * ניתוק Google Calendar
     * מוחק את כל ה-credentials
     * 
     * @param int $scheduler_id מזהה scheduler
     * @return bool
     */
    public function disconnect_google_calendar($scheduler_id) {
        if (empty($scheduler_id)) {
            return false;
        }
        
        // מחיקת כל השדות
        delete_post_meta($scheduler_id, 'google_connected');
        delete_post_meta($scheduler_id, 'google_calendar_id');
        delete_post_meta($scheduler_id, 'google_access_token');
        delete_post_meta($scheduler_id, 'google_refresh_token');
        delete_post_meta($scheduler_id, 'google_token_expires_at');
        delete_post_meta($scheduler_id, 'google_user_email');
        delete_post_meta($scheduler_id, 'google_timezone');
        delete_post_meta($scheduler_id, 'google_connected_at');
        delete_post_meta($scheduler_id, 'google_sync_status');
        delete_post_meta($scheduler_id, 'google_last_error');
        
        return true;
    }
    
    /**
     * בדיקה האם scheduler מחובר לגוגל
     * 
     * @param int $scheduler_id מזהה scheduler
     * @return bool
     */
    public function is_google_connected($scheduler_id) {
        if (empty($scheduler_id)) {
            return false;
        }
        
        return (bool) get_post_meta($scheduler_id, 'google_connected', true);
    }
    
    /**
     * ============================================
     * Active Hours Conversion Methods
     * ============================================
     */
    
    /**
     * Convert time string (HH:MM) to UTC ticks for a specific day
     * 
     * @param string $time_str Time in HH:MM format (local time)
     * @param string $day_key Day key (sunday, monday, etc.)
     * @param string $timezone Timezone (default: Asia/Jerusalem)
     * @return int Ticks value
     */
    private function time_to_utc_ticks($time_str, $day_key, $timezone = 'Asia/Jerusalem') {
        // Parse time
        list($hours, $minutes) = explode(':', $time_str);
        $hours = intval($hours);
        $minutes = intval($minutes);
        
        // Create DateTime for next occurrence of this day at this time (local)
        $day_names = array(
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday'
        );
        
        $day_name = isset($day_names[$day_key]) ? $day_names[$day_key] : 'Sunday';
        
        // Get next occurrence of this day
        $local_tz = new DateTimeZone($timezone);
        $now = new DateTime('now', $local_tz);
        $target = clone $now;
        $target->modify('next ' . $day_name);
        $target->setTime($hours, $minutes, 0);
        
        // Convert to UTC
        $target->setTimezone(new DateTimeZone('UTC'));
        
        // Calculate ticks from midnight UTC of that day
        $midnight_utc = clone $target;
        $midnight_utc->setTime(0, 0, 0);
        
        // Calculate total seconds from midnight UTC
        $total_seconds = ($target->getTimestamp() - $midnight_utc->getTimestamp());
        
        // Ticks = seconds * 10,000,000 (100-nanosecond intervals)
        return $total_seconds * 10000000;
    }
    
    /**
     * Convert schedule days data to activeHours format
     * 
     * @param array $days_data Days data from form: { "sunday": [{ "start_time": "09:00", "end_time": "17:00" }], ... }
     * @param string $timezone Timezone (default: Asia/Jerusalem)
     * @return array Active hours array for API
     */
    public function convert_days_to_active_hours($days_data, $timezone = 'Asia/Jerusalem') {
        $active_hours = array();
        
        $day_mapping = array(
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday'
        );
        
        foreach ($days_data as $day_key => $time_ranges) {
            if (!is_array($time_ranges) || empty($time_ranges)) {
                continue;
            }
            
            $week_day = isset($day_mapping[$day_key]) ? $day_mapping[$day_key] : null;
            if (!$week_day) {
                continue;
            }
            
            // For each time range on this day
            foreach ($time_ranges as $range) {
                if (!isset($range['start_time']) || !isset($range['end_time'])) {
                    continue;
                }
                
                $from_ticks = $this->time_to_utc_ticks($range['start_time'], $day_key, $timezone);
                $to_ticks = $this->time_to_utc_ticks($range['end_time'], $day_key, $timezone);
                
                // Ensure ticks are integers (Int64) - cast to int to prevent float conversion
                $active_hours[] = array(
                    'weekDay' => $week_day,
                    'fromUTC' => array('ticks' => (int)$from_ticks),  // Int64 - must be integer
                    'toUTC' => array('ticks' => (int)$to_ticks)  // Int64 - must be integer
                );
            }
        }
        
        return $active_hours;
    }
}
