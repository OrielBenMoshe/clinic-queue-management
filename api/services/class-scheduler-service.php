<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-service.php';
require_once __DIR__ . '/../dto/class-scheduler-dto.php';
require_once __DIR__ . '/../dto/class-response-dto.php';

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
     * @return Clinic_Queue_List_Response_DTO|WP_Error
     */
    public function get_all_source_calendars($source_creds_id, $scheduler_id) {
        $endpoint = '/Scheduler/GetAllSourceCalendars?sourceCredsID=' . intval($source_creds_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_DTO');
    }
    
    /**
     * Get DRWeb calendar reasons
     * 
     * @param int $source_creds_id Source credentials ID
     * @param string $drweb_calendar_id DRWeb calendar ID
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_DTO|WP_Error
     */
    public function get_drweb_calendar_reasons($source_creds_id, $drweb_calendar_id, $scheduler_id) {
        $endpoint = '/Scheduler/GetDRWebCalendarReasons?sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_DTO');
    }
    
    /**
     * Get DRWeb calendar active hours
     * 
     * @param int $source_creds_id Source credentials ID
     * @param string $drweb_calendar_id DRWeb calendar ID
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_DTO|WP_Error
     */
    public function get_drweb_calendar_active_hours($source_creds_id, $drweb_calendar_id, $scheduler_id) {
        $endpoint = '/Scheduler/GetDRWebCalendarActiveHours?sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_DTO');
    }
    
    /**
     * Create scheduler
     * 
     * @param Clinic_Queue_Create_Scheduler_DTO $scheduler_dto
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Result_Response_DTO|WP_Error
     */
    public function create_scheduler($scheduler_dto, $scheduler_id) {
        $validation = $scheduler_dto->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $data = $scheduler_dto->to_array();
        $response = $this->make_request('POST', '/Scheduler/Create', $data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_DTO');
    }
    
    /**
     * Update scheduler
     * 
     * @param Clinic_Queue_Update_Scheduler_DTO $scheduler_dto
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Base_Response_DTO|WP_Error
     */
    public function update_scheduler($scheduler_dto, $scheduler_id) {
        $validation = $scheduler_dto->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $data = $scheduler_dto->to_array();
        $response = $this->make_request('POST', '/Scheduler/Update', $data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_DTO');
    }
    
    /**
     * Set active hours for scheduler
     * 
     * @param Clinic_Queue_Update_Active_Hours_DTO $active_hours_dto
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Base_Response_DTO|WP_Error
     */
    public function set_active_hours($active_hours_dto, $scheduler_id) {
        $validation = $active_hours_dto->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $data = $active_hours_dto->to_array();
        $response = $this->make_request('POST', '/Scheduler/SetActiveHours', $data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_DTO');
    }
    
    /**
     * Get free time slots
     * 
     * @param Clinic_Queue_Get_Free_Time_DTO $free_time_dto
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_List_Response_DTO|WP_Error
     */
    public function get_free_time($free_time_dto, $scheduler_id) {
        $validation = $free_time_dto->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        // If no API endpoint is configured, return mock data
        if (!$this->api_endpoint) {
            return $this->get_mock_free_time($free_time_dto);
        }
        
        $params = array(
            'schedulerID' => intval($free_time_dto->schedulerID),
            'duration' => intval($free_time_dto->duration),
            'fromDateUTC' => $free_time_dto->fromDateUTC,
            'toDateUTC' => $free_time_dto->toDateUTC,
        );
        
        $endpoint = '/Scheduler/GetFreeTime?' . http_build_query($params);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_List_Response_DTO');
    }
    
    /**
     * Check if slot is available
     * 
     * @param Clinic_Queue_Check_Slot_Available_DTO $slot_dto
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Result_Response_DTO|WP_Error
     */
    public function check_slot_available($slot_dto, $scheduler_id) {
        $validation = $slot_dto->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        $params = array(
            'schedulerID' => intval($slot_dto->schedulerID),
            'fromUTC' => $slot_dto->fromUTC,
            'duration' => intval($slot_dto->duration),
        );
        
        $endpoint = '/Scheduler/CheckIsSlotAvailable?' . http_build_query($params);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_DTO');
    }
    
    /**
     * Get scheduler properties
     * 
     * @param int $scheduler_id Scheduler ID
     * @return Clinic_Queue_Result_Response_DTO|WP_Error
     */
    public function get_scheduler_properties($scheduler_id) {
        $endpoint = '/Scheduler/GetSchedulersProperties?schedulerID=' . intval($scheduler_id);
        $response = $this->make_request('GET', $endpoint, null, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_DTO');
    }

    /**
     * Get mock free time data
     */
    private function get_mock_free_time($free_time_dto) {
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
        $filtered_slots = array_filter($data['result'], function($slot) use ($free_time_dto) {
            // Check scheduler ID (approximate check, in real app would be exact)
            // Mock data has schedulerID 1..8
            if (isset($slot['schedulerID']) && $slot['schedulerID'] != $free_time_dto->schedulerID) {
                return false;
            }

            // Check date range
            $slot_from = strtotime($slot['from']);
            $req_from = strtotime($free_time_dto->fromDateUTC);
            $req_to = strtotime($free_time_dto->toDateUTC);

            return $slot_from >= $req_from && $slot_from <= $req_to;
        });

        // Reset keys
        $data['result'] = array_values($filtered_slots);
        
        return $this->handle_response($data, 'Clinic_Queue_List_Response_DTO');
    }
}
