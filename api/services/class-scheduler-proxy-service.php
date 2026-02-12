<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-proxy-service.php';
require_once __DIR__ . '/../models/class-scheduler-model.php';
require_once __DIR__ . '/../models/class-response-model.php';

/**
 * Scheduler Proxy Service – פניות ל-Proxy API (Scheduler endpoints)
 */
class Clinic_Queue_Scheduler_Proxy_Service extends Clinic_Queue_Base_Proxy_Service {
    
    /**
     * Get all source calendars (GetAllSourceCalendars).
     * לפי מפרט ה-API – מקבל רק sourceCredsID. מחזיר רשימת יומנים; כל פריט כולל sourceSchedulerID
     * (זה ה-drwebCalendarID שמשמש אחר כך ב-GetDRWebCalendarReasons ו-GetDRWebCalendarActiveHours).
     * אימות: טוקן האתר (לא קשור לפוסט יומן – היומן עדיין לא נוצר בשלב זה).
     *
     * @param int $source_creds_id sourceCredsID – מזהה מ-SourceCredentials/Save
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_all_source_calendars($source_creds_id) {
        $endpoint = '/Scheduler/GetAllSourceCalendars?sourceCredsID=' . intval($source_creds_id);
        $response = $this->make_request('GET', $endpoint, null, null);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_Model');
    }

    /**
     * Get DRWeb calendar reasons
     * sourceCredsID = מתשובת SourceCredentials/Save; drwebCalendarID = מזהה היומן שנבחר מ-GetAllSourceCalendars (sourceSchedulerID).
     * אימות: טוקן האתר (יומן/פוסט עדיין לא נוצר בשלב זה).
     *
     * @param int    $source_creds_id   sourceCredsID – מזהה מתשובת SourceCredentials/Save
     * @param string $drweb_calendar_id drwebCalendarID – מזהה היומן מ-GetAllSourceCalendars
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_drweb_calendar_reasons($source_creds_id, $drweb_calendar_id) {
        $endpoint = '/Scheduler/GetDRWebCalendarReasons?sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $response = $this->make_request('GET', $endpoint, null, null);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_Model');
    }
    
    /**
     * Get DRWeb calendar active hours
     * sourceCredsID = מתשובת SourceCredentials/Save; drwebCalendarID = מזהה היומן שנבחר מ-GetAllSourceCalendars (sourceSchedulerID).
     * אימות: טוקן האתר (יומן/פוסט עדיין לא נוצר בשלב זה).
     *
     * @param int    $source_creds_id   sourceCredsID – מזהה מתשובת SourceCredentials/Save
     * @param string $drweb_calendar_id drwebCalendarID – מזהה היומן מ-GetAllSourceCalendars
     * @return Clinic_Queue_List_Response_Model|WP_Error
     */
    public function get_drweb_calendar_active_hours($source_creds_id, $drweb_calendar_id) {
        $endpoint = '/Scheduler/GetDRWebCalendarActiveHours?sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $response = $this->make_request('GET', $endpoint, null, null);
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
     * Get free time by comma-separated scheduler IDs string (Proxy API).
     * Delegates to DoctorOnline Proxy Service and normalizes response to flat format for REST.
     *
     * @param string $schedulerIDsStr Comma-separated scheduler IDs
     * @param int    $duration        Duration in minutes
     * @param string $fromDateUTC      From date UTC (ISO 8601)
     * @param string $toDateUTC        To date UTC (ISO 8601)
     * @return array|WP_Error Array with code, error, result (flat slots) or WP_Error
     */
    public function get_free_time_by_scheduler_ids_str($schedulerIDsStr, $duration = 30, $fromDateUTC = '', $toDateUTC = '') {
        if (!class_exists('Clinic_Queue_DoctorOnline_Proxy_Service')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-doctoronline-proxy-service.php';
        }
        $doctoronline = Clinic_Queue_DoctorOnline_Proxy_Service::get_instance();
        $result = $doctoronline->get_free_time($schedulerIDsStr, $duration, $fromDateUTC, $toDateUTC);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $flat_result = array();
        if (isset($result['days']) && is_array($result['days'])) {
            foreach ($result['days'] as $day) {
                if (isset($day['slots']) && is_array($day['slots'])) {
                    foreach ($day['slots'] as $slot) {
                        $flat_result[] = array(
                            'from' => isset($slot['from']) ? $slot['from'] : '',
                            'schedulerID' => isset($slot['schedulerID']) ? $slot['schedulerID'] : 0,
                        );
                    }
                }
            }
        }
        
        return array(
            'code' => 'Success',
            'error' => null,
            'result' => $flat_result,
        );
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
     * Get active hours for a scheduler (from request body or post meta).
     * Used by create_scheduler_in_proxy to centralize active hours resolution.
     *
     * @param int   $scheduler_id  Scheduler post ID
     * @param array $request_body  Optional. Request body (e.g. from get_json_params()); may contain active_hours
     * @return array|WP_Error Active hours array (weekDay, fromUTC, toUTC) or WP_Error
     */
    public function get_active_hours_for_scheduler($scheduler_id, $request_body = array()) {
        $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
        $active_hours = null;
        $day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        
        if ($schedule_type === 'google') {
            $active_hours_data = isset($request_body['active_hours']) ? $request_body['active_hours'] : null;
            
            if ($active_hours_data && is_array($active_hours_data)) {
                $active_hours = $this->convert_days_to_active_hours($active_hours_data);
            } else {
                $days_data = array();
                foreach ($day_keys as $day_key) {
                    $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                    if ($time_ranges && is_array($time_ranges) && !empty($time_ranges)) {
                        $formatted_ranges = array();
                        foreach ($time_ranges as $range) {
                            if (isset($range['start_time']) && isset($range['end_time'])) {
                                $formatted_ranges[] = array('start_time' => $range['start_time'], 'end_time' => $range['end_time']);
                            } elseif (isset($range['from']) && isset($range['to'])) {
                                $formatted_ranges[] = array('start_time' => $range['from'], 'end_time' => $range['to']);
                            }
                        }
                        if (!empty($formatted_ranges)) {
                            $days_data[$day_key] = $formatted_ranges;
                        }
                    }
                }
                if (!empty($days_data)) {
                    $active_hours = $this->convert_days_to_active_hours($days_data);
                }
            }
            
            if (empty($active_hours) || !is_array($active_hours) || count($active_hours) === 0) {
                return new WP_Error(
                    'missing_active_hours',
                    'Active hours are required for Google Calendar scheduler. Please configure working hours in the schedule settings before connecting to Google Calendar.',
                    array('status' => 400)
                );
            }
            
            foreach ($active_hours as $index => $hour) {
                if (!isset($hour['weekDay']) || !isset($hour['fromUTC']) || !isset($hour['toUTC'])) {
                    return new WP_Error('invalid_active_hours', 'Invalid active hours format. Please reconfigure working hours.', array('status' => 400));
                }
                if (!is_string($hour['fromUTC']) || !is_string($hour['toUTC'])) {
                    return new WP_Error('invalid_active_hours', 'Invalid time format in active hours. Expected HH:mm:ss strings.', array('status' => 400));
                }
            }
        } elseif ($schedule_type === 'clinix' || $schedule_type === 'drweb') {
            $days_data = array();
            foreach ($day_keys as $day_key) {
                $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                if ($time_ranges && is_array($time_ranges)) {
                    $days_data[$day_key] = $time_ranges;
                }
            }
            if (!empty($days_data)) {
                $active_hours = $this->convert_days_to_active_hours($days_data);
            }
        }
        
        return $active_hours !== null ? $active_hours : array();
    }
    
    /**
     * ============================================
     * Active Hours Conversion Methods
     * ============================================
     */
    
    /**
     * Convert time string (HH:MM) to UTC time string (HH:mm:ss) for a specific day
     * 
     * @param string $time_str Time in HH:MM format (local time)
     * @param string $day_key Day key (sunday, monday, etc.)
     * @param string $timezone Timezone (default: Asia/Jerusalem)
     * @return string Time in HH:mm:ss format (UTC)
     */
    private function time_to_utc_string($time_str, $day_key, $timezone = 'Asia/Jerusalem') {
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
        
        // Return as HH:mm:ss string
        return $target->format('H:i:s');
    }
    
    /**
     * Convert schedule days data to activeHours format
     * 
     * @param array $days_data Days data from form: { "sunday": [{ "start_time": "09:00", "end_time": "17:00" }], ... }
     * @param string $timezone Timezone (default: Asia/Jerusalem)
     * @return array Active hours array for API (HH:mm:ss strings)
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
                
                // Convert to UTC HH:mm:ss strings
                $from_utc = $this->time_to_utc_string($range['start_time'], $day_key, $timezone);
                $to_utc = $this->time_to_utc_string($range['end_time'], $day_key, $timezone);
                
                // Return HH:mm:ss strings as expected by the proxy API
                $active_hours[] = array(
                    'weekDay' => $week_day,
                    'fromUTC' => $from_utc,  // String: "HH:mm:ss"
                    'toUTC' => $to_utc        // String: "HH:mm:ss"
                );
            }
        }
        
        return $active_hours;
    }
}
