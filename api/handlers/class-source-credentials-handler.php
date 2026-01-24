<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-source-credentials-service.php';

/**
 * Source Credentials Handler
 * מטפל בכל endpoints הקשורים לפרטי התחברות למקורות
 * 
 * Endpoints:
 * - POST /source-credentials/save - שמירת פרטי התחברות
 * 
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Source_Credentials_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * Source Credentials Service instance
     * 
     * @var Clinic_Queue_Source_Credentials_Service
     */
    private $source_credentials_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize service
        $this->source_credentials_service = new Clinic_Queue_Source_Credentials_Service();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // POST /source-credentials/save
        register_rest_route($this->namespace, '/source-credentials/save', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_source_credentials'),
            'permission_callback' => array($this, 'permission_callback_public'),
            'args' => array(
                'scheduler_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Scheduler ID (can also be in body)'
                )
            )
        ));
    }
    
    /**
     * Save source credentials
     * POST /clinic-queue/v1/source-credentials/save
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function save_source_credentials($request) {
        // Get body data
        $body = $request->get_json_params();
        
        if (empty($body)) {
            return $this->error_response(
                'בקשה לא תקינה',
                400,
                'invalid_request'
            );
        }
        
        // Get scheduler_id from request or body
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        if (!$scheduler_id && isset($body['scheduler_id'])) {
            $scheduler_id = absint($body['scheduler_id']);
        }
        
        if (!$scheduler_id) {
            return $this->error_response(
                'מזהה יומן הוא חובה',
                400,
                'missing_scheduler_id'
            );
        }
        
        // Call service
        $result = $this->source_credentials_service->save_source_credentials($body, $scheduler_id);
        
        // Handle error
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        // Result is array with 'model' and 'raw_body'
        $model = isset($result['model']) ? $result['model'] : $result;
        
        // Convert model to array
        if (is_object($model) && method_exists($model, 'to_array')) {
            return rest_ensure_response($model->to_array());
        }
        
        return rest_ensure_response($model);
    }
}
