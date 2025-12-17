<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-service.php';
require_once __DIR__ . '/../dto/class-appointment-dto.php';
require_once __DIR__ . '/../dto/class-response-dto.php';

/**
 * Appointment Service
 * שירות לניהול תורים
 */
class Clinic_Queue_Appointment_Service extends Clinic_Queue_Base_Service {
    
    /**
     * Create appointment
     * 
     * @param Clinic_Queue_Appointment_DTO $appointment_dto
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Base_Response_DTO|WP_Error
     */
    public function create_appointment($appointment_dto, $scheduler_id) {
        // Validate DTO
        $validation = $appointment_dto->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        // Convert DTO to array
        $data = $appointment_dto->to_array();
        
        // Convert customer DTO to array
        if ($data['customer'] instanceof Clinic_Queue_Customer_DTO) {
            $data['customer'] = $data['customer']->to_array();
        }
        
        // Make API request
        $response = $this->make_request('POST', '/Appointment/Create', $data, $scheduler_id);
        
        // Handle response
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_DTO');
    }
}

