<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-jetengine-relations-service.php';

/**
 * Relations Handler – פניות ל-API של Jet (JetEngine)
 * מטפל ב-endpoints שמפנים ל-JetEngine Relations API (jet-rel).
 *
 * Endpoints (namespace: clinic-queue/v1):
 * - GET /relations/clinic/{clinic_id}/doctors - קבלת רופאים לפי מרפאה (פנייה ל-API של Jet)
 *
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Relations_Jet_Api_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * JetEngine Relations Service instance
     *
     * @var Clinic_Queue_JetEngine_Relations_Service
     */
    private $relations_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->relations_service = Clinic_Queue_JetEngine_Relations_Service::get_instance();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // GET /relations/clinic/{clinic_id}/doctors
        register_rest_route($this->namespace, '/relations/clinic/(?P<clinic_id>\d+)/doctors', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_doctors_by_clinic'),
            'permission_callback' => '__return_true', // Public endpoint - anyone can read doctors list
            'args' => array(
                'clinic_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
            )
        ));

        // GET /relations/clinic/{clinic_id}/booked-doctor-ids
        register_rest_route($this->namespace, '/relations/clinic/(?P<clinic_id>\d+)/booked-doctor-ids', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_booked_doctor_ids'),
            'permission_callback' => '__return_true',
            'args' => array(
                'clinic_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
            )
        ));
    }
    
    /**
     * Get doctors by clinic ID using JetEngine Relations (Relation 201: Clinic -> Doctor)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_doctors_by_clinic($request) {
        $clinic_id = $this->get_int_param($request, 'clinic_id');
        
        if (empty($clinic_id)) {
            return $this->error_response(
                'Clinic ID is required',
                400,
                'missing_clinic_id'
            );
        }
        
        $doctors = $this->relations_service->get_doctors_by_clinic($clinic_id);
        return new WP_REST_Response($doctors, 200);
    }

    /**
     * Get doctor IDs that already have an active scheduler for a specific clinic
     * (Relation 184: Clinic->Scheduler + 'doctor_id' meta on each scheduler)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_booked_doctor_ids($request) {
        $clinic_id = $this->get_int_param($request, 'clinic_id');

        if (empty($clinic_id)) {
            return $this->error_response(
                'Clinic ID is required',
                400,
                'missing_clinic_id'
            );
        }

        $booked_doctor_ids = $this->relations_service->get_booked_doctor_ids_for_clinic($clinic_id);
        return new WP_REST_Response($booked_doctor_ids, 200);
    }
}
