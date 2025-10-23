<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Handlers for Clinic Queue Management
 * Handles all REST API endpoints
 */
class Clinic_Queue_Rest_Handlers {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('clinic-queue/v1', '/appointments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_appointments'),
            'permission_callback' => '__return_true',
            'args' => array(
                'doctor_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'clinic_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'treatment_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        register_rest_route('clinic-queue/v1', '/all-appointments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_appointments'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Get appointments via REST API
     */
    public function get_appointments($request) {
        $doctor_id = $request->get_param('doctor_id');
        $clinic_id = $request->get_param('clinic_id');
        $treatment_type = $request->get_param('treatment_type') ?: 'רפואה כללית';
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $appointments_data = $api_manager->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
        
        if (!$appointments_data) {
            return new WP_Error('no_appointments', 'No appointments found', array('status' => 404));
        }
        
        return rest_ensure_response($appointments_data);
    }
    
    /**
     * Get all appointments via REST API (for client-side filtering)
     */
    public function get_all_appointments($request) {
        // Get all calendars from database
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        
        // Get all calendars
        $calendars = $wpdb->get_results("SELECT * FROM $table_calendars ORDER BY doctor_id, clinic_id, treatment_type");
        
        if (empty($calendars)) {
            return new WP_Error('no_calendars', 'No calendars found in database', array('status' => 404));
        }
        
        $result = array('calendars' => array());
        $helpers = Clinic_Queue_Helpers::get_instance();
        
        foreach ($calendars as $calendar) {
            // Get appointments for this calendar (next 30 days)
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+30 days'));
            
            $dates = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_dates 
                 WHERE calendar_id = %d 
                 AND appointment_date >= %s 
                 AND appointment_date <= %s 
                 ORDER BY appointment_date ASC",
                $calendar->id, $start_date, $end_date
            ));
            
            $appointments = array();
            
            foreach ($dates as $date) {
                $time_slots = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_times 
                     WHERE date_id = %d 
                     ORDER BY time_slot ASC",
                    $date->id
                ));
                
                $slots = array();
                foreach ($time_slots as $slot) {
                    $slots[] = array(
                        'time' => $slot->time_slot,
                        'is_booked' => (bool) $slot->is_booked
                    );
                }
                
                if (!empty($slots)) {
                    $appointments[$date->appointment_date] = $slots;
                }
            }
            
            // Add calendar to result
            $result['calendars'][] = array(
                'doctor_id' => $calendar->doctor_id,
                'doctor_name' => $helpers->get_doctor_name($calendar->doctor_id),
                'clinic_id' => $calendar->clinic_id,
                'clinic_name' => $helpers->get_clinic_name($calendar->clinic_id),
                'treatment_type' => $calendar->treatment_type,
                'appointments' => $appointments
            );
        }
        
        return rest_ensure_response($result);
    }
}
