<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-appointment-proxy-service.php';
require_once __DIR__ . '/../models/class-appointment-model.php';

/**
 * Appointment Handler
 * מטפל בכל endpoints הקשורים לתורים
 * 
 * Endpoints:
 * - POST /appointment/create - יצירת תור חדש
 * 
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Appointment_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * Appointment Service instance
     * 
     * @var Clinic_Queue_Appointment_Proxy_Service
     */
    private $appointment_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize service
        $this->appointment_service = new Clinic_Queue_Appointment_Proxy_Service();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // POST /appointment/create
        register_rest_route($this->namespace, '/appointment/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_appointment'),
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
     * Create appointment
     * POST /clinic-queue/v1/appointment/create
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create_appointment($request) {
        // Get body data
        $body = $request->get_json_params();
        
        if (empty($body)) {
            return $this->error_response(
                'בקשה לא תקינה',
                400,
                'invalid_request'
            );
        }
        
        // Create Models
        $customer_model = Clinic_Queue_Customer_Model::from_array($body['customer'] ?? array());
        $appointment_model = Clinic_Queue_Appointment_Model::from_array($body);
        $appointment_model->customer = $customer_model;
        
        // Get scheduler ID from request or body
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
        $result = $this->appointment_service->create_appointment($appointment_model, $scheduler_id);
        
        // Handle error
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        // Convert model to array and return
        if (is_object($result) && method_exists($result, 'to_array')) {
            return rest_ensure_response($result->to_array());
        }
        
        return rest_ensure_response($result);
    }
}
