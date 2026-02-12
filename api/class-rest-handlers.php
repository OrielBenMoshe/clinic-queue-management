<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load handler dependencies
require_once __DIR__ . '/handlers/class-appointment-handler.php';
require_once __DIR__ . '/handlers/class-scheduler-wp-rest-handler.php';
require_once __DIR__ . '/handlers/class-source-credentials-handler.php';
require_once __DIR__ . '/handlers/class-google-calendar-handler.php';
require_once __DIR__ . '/handlers/class-relations-jet-api-handler.php';
require_once __DIR__ . '/handlers/class-error-handler.php';

/**
 * REST API Handlers Registry for Clinic Queue Management
 * 
 * מחלקה זו משמשת כ-Registry בלבד - רושמת handlers ומפנה אליהם.
 * כל הלוגיקה העסקית מועברת ל-handlers נפרדים.
 * 
 * ארכיטקטורה:
 * - Registry (מחלקה זו) - רושמת handlers
 * - Handlers (תיקיית handlers/) - מטפלים ב-endpoints
 * - Services (תיקיית services/) - לוגיקה עסקית
 * - Models (תיקיית models/) - Data Transfer Objects
 * 
 * @package ClinicQueue
 * @subpackage API
 * @since 2.0.0
 */
class Clinic_Queue_Rest_Handlers {
    
    private static $instance = null;
    
    /**
     * Handler instances
     */
    private $appointment_handler;
    private $scheduler_handler;
    private $source_credentials_handler;
    private $google_calendar_handler;
    private $relations_handler;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Initialize handlers
        $this->appointment_handler = new Clinic_Queue_Appointment_Handler();
        $this->scheduler_handler = new Clinic_Queue_Scheduler_Wp_Rest_Handler();
        $this->source_credentials_handler = new Clinic_Queue_Source_Credentials_Handler();
        $this->google_calendar_handler = new Clinic_Queue_Google_Calendar_Handler();
        $this->relations_handler = new Clinic_Queue_Relations_Jet_Api_Handler();
        
        // Register routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('rest_api_init', array($this, 'register_doctor_custom_fields'));
        add_action('rest_api_init', array($this, 'register_clinic_custom_fields'));
    }
    
    /**
     * Register REST API routes
     * 
     * Registry בלבד - מפנה לכל ה-handlers
     * כל הלוגיקה העסקית נמצאת ב-handlers נפרדים
     */
    public function register_rest_routes() {
        // Register all handler routes
        $this->appointment_handler->register_routes();
        $this->scheduler_handler->register_routes();
        $this->source_credentials_handler->register_routes();
        $this->google_calendar_handler->register_routes();
        $this->relations_handler->register_routes();
        
        // Legacy endpoints (for backward compatibility)
        $this->register_legacy_routes();
    }
    
    /**
     * Register legacy routes (backward compatibility)
     */
    private function register_legacy_routes() {
        register_rest_route('clinic-queue/v1', '/appointments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_appointments'),
            'permission_callback' => '__return_true',
            'args' => array(
                'calendar_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'doctor_id' => array(
                    'required' => false,
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
        
        // Legacy endpoint: redirect old create-proxy to new create-schedule-in-proxy
        register_rest_route('clinic-queue/v1', '/scheduler/create-proxy', array(
            'methods' => 'POST',
            'callback' => array($this->scheduler_handler, 'create_scheduler_in_proxy'),
            'permission_callback' => 'is_user_logged_in',
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'source_credentials_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'source_scheduler_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'active_hours' => array(
                    'required' => false,
                    'type' => 'object',
                    'description' => 'Active hours data for Google Calendar (required for Google, optional for DRWeb)',
                )
            ),
        ));
    }
    
    
    /**
     * Register custom REST API fields for doctors post type
     * Exposes JetEngine custom fields (meta) in REST API
     */
    public function register_doctor_custom_fields() {
        // Register license_number field
        register_rest_field('doctors', 'license_number', array(
            'get_callback' => function($post_object) {
                // Get meta value - JetEngine stores custom fields as post meta
                $value = get_post_meta($post_object['id'], 'license_number', true);
                return $value ? $value : '';
            },
            'update_callback' => null, // Read-only for now
            'schema' => array(
                'description' => 'Doctor license number',
                'type' => 'string',
                'context' => array('view', 'edit'),
            ),
        ));
        
        // Register thumbnail field (can be meta field or featured image)
        register_rest_field('doctors', 'thumbnail', array(
            'get_callback' => function($post_object) {
                // First check if there's a custom thumbnail meta field
                $thumbnail_meta = get_post_meta($post_object['id'], 'thumbnail', true);
                if ($thumbnail_meta) {
                    // If it's an array (from JetEngine media field), return URL
                    if (is_array($thumbnail_meta) && isset($thumbnail_meta['url'])) {
                        return $thumbnail_meta['url'];
                    } elseif (is_string($thumbnail_meta)) {
                        return $thumbnail_meta;
                    }
                }
                
                // Fallback to featured image
                $thumbnail_id = get_post_thumbnail_id($post_object['id']);
                if ($thumbnail_id) {
                    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
                    if ($thumbnail_url) {
                        return $thumbnail_url;
                    }
                }
                
                return '';
            },
            'update_callback' => null, // Read-only for now
            'schema' => array(
                'description' => 'Doctor thumbnail image URL',
                'type' => 'string',
                'format' => 'uri',
                'context' => array('view', 'edit'),
            ),
        ));
    }
    
    /**
     * Register custom fields for clinics in REST API
     * Exposes JetEngine custom fields (meta) in REST API
     */
    public function register_clinic_custom_fields() {
        // Register treatments repeater field
        register_rest_field('clinics', 'treatments', array(
            'get_callback' => function($post_object) {
                // Get meta value - JetEngine stores repeater as post meta
                $treatments = get_post_meta($post_object['id'], 'treatments', true);
                
                // Return empty array if no treatments
                if (!$treatments || !is_array($treatments)) {
                    return array();
                }
                
                // Ensure each treatment has all required fields
                $formatted_treatments = array();
                foreach ($treatments as $treatment) {
                    $formatted_treatments[] = array(
                        'treatment_type' => isset($treatment['treatment_type']) ? $treatment['treatment_type'] : '',
                        'sub_speciality' => isset($treatment['sub_speciality']) ? intval($treatment['sub_speciality']) : 0,
                        'cost' => isset($treatment['cost']) ? intval($treatment['cost']) : 0,
                        'duration' => isset($treatment['duration']) ? intval($treatment['duration']) : 0,
                    );
                }
                
                return $formatted_treatments;
            },
            'update_callback' => null, // Read-only for now
            'schema' => array(
                'description' => 'Clinic treatments repeater',
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'treatment_type' => array(
                            'type' => 'string',
                            'description' => 'Treatment type name'
                        ),
                        'sub_speciality' => array(
                            'type' => 'integer',
                            'description' => 'Sub-speciality term ID from glossary taxonomy'
                        ),
                        'cost' => array(
                            'type' => 'integer',
                            'description' => 'Treatment cost'
                        ),
                        'duration' => array(
                            'type' => 'integer',
                            'description' => 'Treatment duration in minutes'
                        ),
                    ),
                ),
                'context' => array('view', 'edit'),
            ),
        ));
    }
    
    // ============================================
    // Legacy Endpoints (Backward Compatibility)
    // ============================================
    
    /**
     * Get appointments via REST API (Legacy)
     * Direct API call - no local storage
     */
    public function get_appointments($request) {
        // Support both calendar_id and doctor_id+clinic_id
        $calendar_id = $request->get_param('calendar_id');
        $doctor_id = $request->get_param('doctor_id');
        $clinic_id = $request->get_param('clinic_id');
        $treatment_type = $request->get_param('treatment_type') ?: '';
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $appointments_data = $api_manager->get_appointments_data($calendar_id, $doctor_id, $clinic_id, $treatment_type);
        
        if (!$appointments_data) {
            return new WP_Error('no_appointments', 'No appointments found', array('status' => 404));
        }
        
        return rest_ensure_response($appointments_data);
    }
    
    /**
     * Get all appointments via REST API (Legacy)
     * 
     * @deprecated This endpoint is deprecated and returns empty result
     * Use individual appointment endpoints instead
     */
    public function get_all_appointments($request) {
        // This endpoint is deprecated - return empty result
        // The old get_all_calendars() method was removed during API refactoring
        // If you need this functionality, use get_schedulers_by_clinic() instead
        return rest_ensure_response(array(
            'calendars' => array(),
            'message' => 'This endpoint is deprecated. Use individual appointment endpoints instead.'
        ));
    }
}
