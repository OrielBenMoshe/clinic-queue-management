<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-service.php';
require_once __DIR__ . '/../models/class-appointment-model.php';
require_once __DIR__ . '/../models/class-response-model.php';

/**
 * Appointment Service
 * שירות לניהול תורים
 */
class Clinic_Queue_Appointment_Service extends Clinic_Queue_Base_Service {
    
    /**
     * Create appointment
     * 
     * @param Clinic_Queue_Appointment_Model $appointment_model
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Base_Response_Model|WP_Error
     */
    public function create_appointment($appointment_model, $scheduler_id) {
        // Validate Model
        $validation = $appointment_model->validate();
        if ($validation !== true) {
            return new WP_Error('validation_error', 'שגיאת ולידציה', array('errors' => $validation));
        }
        
        // Convert Model to array
        $data = $appointment_model->to_array();
        
        // Convert customer Model to array
        if ($data['customer'] instanceof Clinic_Queue_Customer_Model) {
            $data['customer'] = $data['customer']->to_array();
        }
        
        // Make API request
        $response = $this->make_request('POST', '/Appointment/Create', $data, $scheduler_id);
        
        // Handle response
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_Model');
    }
}

