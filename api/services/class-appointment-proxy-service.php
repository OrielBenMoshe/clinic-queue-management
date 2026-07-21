<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-proxy-service.php';
require_once __DIR__ . '/../models/class-appointment-model.php';
require_once __DIR__ . '/../models/class-response-model.php';

/**
 * Appointment Proxy Service – פניות ל-Proxy API (Appointment/Create)
 */
class Clinic_Queue_Appointment_Proxy_Service extends Clinic_Queue_Base_Proxy_Service {
    
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
        // מין: שולחים רק אם הוגדר Male/Female; אחרת לא לכלול את השדה בבקשה
        if (!isset($data['customer']['gender']) || !in_array($data['customer']['gender'], array('Male', 'Female'), true)) {
            unset($data['customer']['gender']);
        }

        // טלפון נוסף: שולחים רק אם הוזן ערך; אחרת לא לכלול את השדה בבקשה
        if (!isset($data['customer']['additionalMobilePhone']) || trim((string) $data['customer']['additionalMobilePhone']) === '') {
            unset($data['customer']['additionalMobilePhone']);
        }

        // תאריך לידה: שולחים רק אם יש ערך; אחרת לא לכלול את השדה בבקשה
        if (!isset($data['customer']['birthDate']) || trim((string) $data['customer']['birthDate']) === '') {
            unset($data['customer']['birthDate']);
        }

        if (!isset($data['remark']) || $data['remark'] === null || trim((string) $data['remark']) === '') {
            unset($data['remark']);
        }
        
        // הפרוקסי (.NET) מצפה ל-Int32 – לא null
        $data['drWebReasonID'] = isset($data['drWebReasonID']) && is_numeric($data['drWebReasonID']) ? (int) $data['drWebReasonID'] : 0;
        $data['duration'] = isset($data['duration']) && is_numeric($data['duration']) ? (int) $data['duration'] : 30;
        $data['schedulerID'] = (int) $data['schedulerID'];
        
        // Make API request
        $response = $this->make_request('POST', '/Appointment/Create', $data, $scheduler_id);
        
        // Handle response
        return $this->handle_response($response, 'Clinic_Queue_Base_Response_Model');
    }
}

