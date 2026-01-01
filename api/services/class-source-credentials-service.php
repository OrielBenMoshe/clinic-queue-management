<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-service.php';
require_once __DIR__ . '/../models/class-response-model.php';

/**
 * Source Credentials Service
 * שירות לניהול פרטי התחברות למקורות
 */
class Clinic_Queue_Source_Credentials_Service extends Clinic_Queue_Base_Service {
    
    /**
     * Save source credentials
     * 
     * @param array $credentials_data Credentials data (will be converted to Model later)
     * @param int $scheduler_id Scheduler ID for authentication
     * @return array|WP_Error Returns array with 'model' (Clinic_Queue_Result_Response_Model) and 'raw_body' (string) for debugging
     */
    public function save_source_credentials($credentials_data, $scheduler_id) {
        // TODO: Create SourceCredentials Model when schema is available
        $response = $this->make_request_with_raw_body('POST', '/SourceCredentials/Save', $credentials_data, $scheduler_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // response is now array with 'data' and 'raw_body'
        $model = $this->handle_response($response['data'], 'Clinic_Queue_Result_Response_Model');
        
        return array(
            'model' => $model,
            'raw_body' => $response['raw_body']
        );
    }
}

