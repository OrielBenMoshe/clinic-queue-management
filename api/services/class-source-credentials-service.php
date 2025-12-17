<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-service.php';
require_once __DIR__ . '/../dto/class-response-dto.php';

/**
 * Source Credentials Service
 * שירות לניהול פרטי התחברות למקורות
 */
class Clinic_Queue_Source_Credentials_Service extends Clinic_Queue_Base_Service {
    
    /**
     * Save source credentials
     * 
     * @param array $credentials_data Credentials data (will be converted to DTO later)
     * @param int $scheduler_id Scheduler ID for authentication
     * @return Clinic_Queue_Result_Response_DTO|WP_Error
     */
    public function save_source_credentials($credentials_data, $scheduler_id) {
        // TODO: Create SourceCredentials DTO when schema is available
        $response = $this->make_request('POST', '/SourceCredentials/Save', $credentials_data, $scheduler_id);
        return $this->handle_response($response, 'Clinic_Queue_Result_Response_DTO');
    }
}

