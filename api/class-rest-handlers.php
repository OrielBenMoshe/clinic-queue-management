<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/services/class-appointment-service.php';
require_once __DIR__ . '/services/class-scheduler-service.php';
require_once __DIR__ . '/services/class-source-credentials-service.php';
require_once __DIR__ . '/models/class-appointment-model.php';
require_once __DIR__ . '/models/class-scheduler-model.php';
require_once __DIR__ . '/models/class-response-model.php';
require_once __DIR__ . '/handlers/class-error-handler.php';

// Load Google Calendar dependencies
$google_service_file = __DIR__ . '/services/class-google-calendar-service.php';
$google_credentials_file = __DIR__ . '/config/google-credentials.php';

if (file_exists($google_service_file)) {
    require_once $google_service_file;
}

if (file_exists($google_credentials_file)) {
    require_once $google_credentials_file;
}

/**
 * REST API Handlers for Clinic Queue Management
 * Handles all REST API endpoints - ארכיטקטורה מקצועית ומסודרת
 */
class Clinic_Queue_Rest_Handlers {
    
    private static $instance = null;
    
    // Services
    private $appointment_service;
    private $scheduler_service;
    private $source_credentials_service;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Initialize services
        $this->appointment_service = new Clinic_Queue_Appointment_Service();
        $this->scheduler_service = new Clinic_Queue_Scheduler_Service();
        $this->source_credentials_service = new Clinic_Queue_Source_Credentials_Service();
        
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('rest_api_init', array($this, 'register_doctor_custom_fields'));
        add_action('rest_api_init', array($this, 'register_clinic_custom_fields'));
    }
    
    /**
     * Register REST API routes
     * רישום כל נקודות הקצה בהתבסס על Swagger documentation
     */
    public function register_rest_routes() {
        // Legacy endpoints (for backward compatibility)
        $this->register_legacy_routes();
        
        // Appointment endpoints
        $this->register_appointment_routes();
        
        // Scheduler endpoints
        $this->register_scheduler_routes();
        
        // Source Credentials endpoints
        $this->register_source_credentials_routes();
        
        // Google Calendar endpoints
        $this->register_google_calendar_routes();
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
            'callback' => array($this, 'create_scheduler_in_proxy'),
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
     * Register Appointment routes
     */
    private function register_appointment_routes() {
        // POST /Appointment/Create
        register_rest_route('clinic-queue/v1', '/appointment/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_appointment'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Register Scheduler routes
     */
    private function register_scheduler_routes() {
        // GET /Scheduler/GetAllSourceCalendars
        register_rest_route('clinic-queue/v1', '/scheduler/source-calendars', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_source_calendars'),
            'permission_callback' => '__return_true',
            'args' => array(
                'source_creds_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // GET /Scheduler/GetDRWebCalendarReasons
        register_rest_route('clinic-queue/v1', '/scheduler/drweb-calendar-reasons', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_drweb_calendar_reasons'),
            'permission_callback' => '__return_true',
            'args' => array(
                'source_creds_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'drweb_calendar_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // GET /Scheduler/GetDRWebCalendarActiveHours
        register_rest_route('clinic-queue/v1', '/scheduler/drweb-calendar-active-hours', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_drweb_calendar_active_hours'),
            'permission_callback' => '__return_true',
            'args' => array(
                'source_creds_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'drweb_calendar_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // GET /Scheduler/GetFreeTime
        register_rest_route('clinic-queue/v1', '/scheduler/free-time', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_free_time'),
            'permission_callback' => '__return_true',
            'args' => array(
                'schedulerIDsStr' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'scheduler_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'duration' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'fromDateUTC' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'from_date_utc' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'toDateUTC' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'to_date_utc' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // GET /Scheduler/CheckIsSlotAvailable
        register_rest_route('clinic-queue/v1', '/scheduler/check-slot-available', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_slot_available'),
            'permission_callback' => '__return_true',
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'from_utc' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'duration' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // GET /Scheduler/GetSchedulersProperties
        register_rest_route('clinic-queue/v1', '/scheduler/properties', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_scheduler_properties'),
            'permission_callback' => '__return_true',
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // POST /clinic-queue/v1/scheduler/create-schedule-in-proxy
        register_rest_route('clinic-queue/v1', '/scheduler/create-schedule-in-proxy', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_scheduler_in_proxy'),
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
            )
        ));
    }
    
    /**
     * Register Source Credentials routes
     */
    private function register_source_credentials_routes() {
        // POST /SourceCredentials/Save
        register_rest_route('clinic-queue/v1', '/source-credentials/save', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_source_credentials'),
            'permission_callback' => '__return_true',
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
    
    // ============================================
    // Appointment Endpoints
    // ============================================
    
    /**
     * Create appointment
     * POST /clinic-queue/v1/appointment/create
     */
    public function create_appointment($request) {
        $body = $request->get_json_params();
        
        if (empty($body)) {
            return new WP_Error('invalid_request', 'בקשה לא תקינה', array('status' => 400));
        }
        
        // Create Models
        $customer_model = Clinic_Queue_Customer_Model::from_array($body['customer'] ?? array());
        $appointment_model = Clinic_Queue_Appointment_Model::from_array($body);
        $appointment_model->customer = $customer_model;
        
        // Get scheduler ID from request or body
        $scheduler_id = $request->get_param('scheduler_id') ?: ($body['scheduler_id'] ?? null);
        if (!$scheduler_id) {
            return new WP_Error('missing_scheduler_id', 'מזהה יומן הוא חובה', array('status' => 400));
        }
        
        // Call service
        $result = $this->appointment_service->create_appointment($appointment_model, intval($scheduler_id));
        
        // Handle response
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    // ============================================
    // Scheduler Endpoints
    // ============================================
    
    /**
     * Get all source calendars
     * GET /clinic-queue/v1/scheduler/source-calendars
     */
    public function get_all_source_calendars($request) {
        $source_creds_id = $request->get_param('source_creds_id');
        $scheduler_id = $request->get_param('scheduler_id');
        
        $result = $this->scheduler_service->get_all_source_calendars($source_creds_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get DRWeb calendar reasons
     * GET /clinic-queue/v1/scheduler/drweb-calendar-reasons
     */
    public function get_drweb_calendar_reasons($request) {
        $source_creds_id = $request->get_param('source_creds_id');
        $drweb_calendar_id = $request->get_param('drweb_calendar_id');
        $scheduler_id = $request->get_param('scheduler_id');
        
        $result = $this->scheduler_service->get_drweb_calendar_reasons($source_creds_id, $drweb_calendar_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get DRWeb calendar active hours
     * GET /clinic-queue/v1/scheduler/drweb-calendar-active-hours
     */
    public function get_drweb_calendar_active_hours($request) {
        $source_creds_id = $request->get_param('source_creds_id');
        $drweb_calendar_id = $request->get_param('drweb_calendar_id');
        $scheduler_id = $request->get_param('scheduler_id');
        
        $result = $this->scheduler_service->get_drweb_calendar_active_hours($source_creds_id, $drweb_calendar_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get free time slots
     * GET /clinic-queue/v1/scheduler/free-time
     */
    public function get_free_time($request) {
        // Support both schedulerIDsStr (new) and scheduler_id (legacy)
        $schedulerIDsStr = $request->get_param('schedulerIDsStr');
        $scheduler_id = $request->get_param('scheduler_id');
        
        // Get duration
        $duration = $request->get_param('duration');
        
        // Get dates - support both fromDateUTC/toDateUTC (new) and from_date_utc/to_date_utc (legacy)
        $fromDateUTC = $request->get_param('fromDateUTC') ?: $request->get_param('from_date_utc');
        $toDateUTC = $request->get_param('toDateUTC') ?: $request->get_param('to_date_utc');
        
        // If schedulerIDsStr is provided, use it directly
        if (!empty($schedulerIDsStr)) {
            // Call API Manager directly with schedulerIDsStr
            $api_manager = Clinic_Queue_API_Manager::get_instance();
            $result = $api_manager->get_appointments_data_by_scheduler_ids(
                $schedulerIDsStr,
                $duration,
                $fromDateUTC,
                $toDateUTC
            );
            
            if (is_wp_error($result)) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            
            return rest_ensure_response($result);
        }
        
        // Legacy support: use scheduler_id
        if (!empty($scheduler_id)) {
            $free_time_model = new Clinic_Queue_Get_Free_Time_Model();
            $free_time_model->schedulerID = $scheduler_id;
            $free_time_model->duration = $duration;
            $free_time_model->fromDateUTC = $fromDateUTC;
            $free_time_model->toDateUTC = $toDateUTC;
            
            $result = $this->scheduler_service->get_free_time($free_time_model, intval($free_time_model->schedulerID));
            
            if (is_wp_error($result)) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            
            return rest_ensure_response($result->to_array());
        }
        
        // Error: missing required parameters
        return new WP_Error(
            'missing_parameters',
            'Missing required parameters: schedulerIDsStr or scheduler_id',
            array('status' => 400)
        );
    }
    
    /**
     * Check if slot is available
     * GET /clinic-queue/v1/scheduler/check-slot-available
     */
    public function check_slot_available($request) {
        $slot_model = new Clinic_Queue_Check_Slot_Available_Model();
        $slot_model->schedulerID = $request->get_param('scheduler_id');
        $slot_model->fromUTC = $request->get_param('from_utc');
        $slot_model->duration = $request->get_param('duration');
        
        $result = $this->scheduler_service->check_slot_available($slot_model, intval($slot_model->schedulerID));
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get scheduler properties
     * GET /clinic-queue/v1/scheduler/properties
     */
    public function get_scheduler_properties($request) {
        $scheduler_id = $request->get_param('scheduler_id');
        
        $result = $this->scheduler_service->get_scheduler_properties($scheduler_id);
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Create scheduler in proxy
     * POST /clinic-queue/v1/scheduler/create-schedule-in-proxy
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_scheduler_in_proxy($request) {
        $scheduler_id = $request->get_param('scheduler_id');
        $source_creds_id = $request->get_param('source_credentials_id');
        $source_scheduler_id = $request->get_param('source_scheduler_id');
        
        // Validation
        if (empty($scheduler_id) || empty($source_creds_id) || empty($source_scheduler_id)) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }
        
        // Check permissions
        $post = get_post($scheduler_id);
        if (!$post || $post->post_type !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Scheduler not found', array('status' => 404));
        }
        
        $current_user_id = get_current_user_id();
        if ($post->post_author != $current_user_id && !current_user_can('edit_others_posts')) {
            return new WP_Error('permission_denied', 'Permission denied', array('status' => 403));
        }
        
        // Get schedule_type from post meta to determine if this is Google or DRWeb
        $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
        
        // Active hours handling:
        // According to API schema: "Required - unless its a DRWeb scheduler"
        // This means: for Google Calendar - REQUIRED (but nullable: true, so can be null or array)
        //             for DRWeb - NOT REQUIRED (can be null)
        $active_hours = null;
        
        if ($schedule_type === 'google') {
            // For Google Calendar: Get activeHours from request body (sent from frontend)
            // The frontend should send the days/time ranges that user configured
            $request_body = $request->get_json_params();
            $active_hours_data = isset($request_body['active_hours']) ? $request_body['active_hours'] : null;
            
            if ($active_hours_data && is_array($active_hours_data)) {
                // Convert frontend format to API format
                $active_hours = $this->scheduler_service->convert_days_to_active_hours($active_hours_data);
            } else {
                // Try to get from WordPress meta as fallback
                $days_data = array();
                $day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
                
                foreach ($day_keys as $day_key) {
                    $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                    if ($time_ranges && is_array($time_ranges) && !empty($time_ranges)) {
                        // Ensure format is correct: array of {start_time, end_time}
                        $formatted_ranges = array();
                        foreach ($time_ranges as $range) {
                            // Handle both formats: {start_time, end_time} and {from, to}
                            if (isset($range['start_time']) && isset($range['end_time'])) {
                                $formatted_ranges[] = array(
                                    'start_time' => $range['start_time'],
                                    'end_time' => $range['end_time']
                                );
                            } elseif (isset($range['from']) && isset($range['to'])) {
                                $formatted_ranges[] = array(
                                    'start_time' => $range['from'],
                                    'end_time' => $range['to']
                                );
                            }
                        }
                        if (!empty($formatted_ranges)) {
                            $days_data[$day_key] = $formatted_ranges;
                        }
                    }
                }
                
                if (!empty($days_data)) {
                    $active_hours = $this->scheduler_service->convert_days_to_active_hours($days_data);
                }
            }
            
            // For Google Calendar, activeHours is required by API schema
            // Validate that we have valid active hours
            if (empty($active_hours) || !is_array($active_hours) || count($active_hours) === 0) {
                return new WP_Error(
                    'missing_active_hours', 
                    'Active hours are required for Google Calendar scheduler. Please configure working hours in the schedule settings before connecting to Google Calendar.', 
                    array('status' => 400)
                );
            }
            
            // Validate that active_hours has valid structure
            foreach ($active_hours as $index => $hour) {
                if (!isset($hour['weekDay']) || !isset($hour['fromUTC']) || !isset($hour['toUTC'])) {
                    return new WP_Error(
                        'invalid_active_hours',
                        'Invalid active hours format. Please reconfigure working hours.',
                        array('status' => 400)
                    );
                }
                
                // Validate HH:mm:ss format
                // fromUTC and toUTC should already be strings in HH:mm:ss format from convert_days_to_active_hours
                if (!is_string($hour['fromUTC']) || !is_string($hour['toUTC'])) {
                    return new WP_Error(
                        'invalid_active_hours',
                        'Invalid time format in active hours. Expected HH:mm:ss strings.',
                        array('status' => 400)
                    );
                }
            }
        } elseif ($schedule_type === 'clinix' || $schedule_type === 'drweb') {
            // For DRWeb: Get schedule days data from WordPress meta and convert to activeHours
            $days_data = array();
            $day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
            
            foreach ($day_keys as $day_key) {
                $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                if ($time_ranges && is_array($time_ranges)) {
                    $days_data[$day_key] = $time_ranges;
                }
            }
            
            if (!empty($days_data)) {
                // Convert days to activeHours format
                $active_hours = $this->scheduler_service->convert_days_to_active_hours($days_data);
            }
            // For DRWeb, activeHours is optional (can be null)
        }
        
        // Create Model
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-scheduler-model.php';
        $scheduler_model = new Clinic_Queue_Create_Scheduler_Model();
        $scheduler_model->sourceCredentialsID = $source_creds_id;
        $scheduler_model->sourceSchedulerID = $source_scheduler_id;
        $scheduler_model->activeHours = $active_hours; // Array with active hours (required for Google)
        $scheduler_model->maxOverlappingMeeting = 1; // Default
        $scheduler_model->overlappingDurationInMinutes = 0; // Default
        
        // For debugging: prepare data to send to frontend
        $scheduler_data = $scheduler_model->to_array();
        $debug_data = array(
            'schedule_type' => $schedule_type,
            'data_sent_to_proxy' => $scheduler_data,
            'json_sent' => json_encode($scheduler_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        
        // Create scheduler in proxy
        $result = $this->scheduler_service->create_scheduler($scheduler_model, $scheduler_id);
        
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            if (!is_array($error_data)) {
                $error_data = array();
            }
            $error_data['debug'] = $debug_data;
            
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                $error_data
            );
        }
        
        // Check if creation was successful
        if (!isset($result->code) || $result->code !== 'Success') {
            $error_msg = isset($result->error) ? $result->error : 'Failed to create scheduler';
            
            // Check if this is a duplicate entry error
            if (stripos($error_msg, 'Duplicate entry') !== false && stripos($error_msg, 'UQ_Scheduler_Source') !== false) {
                // This scheduler already exists in the proxy
                // Try to get the existing scheduler ID
                // Unfortunately, the proxy doesn't return the existing scheduler ID in the error
                // So we need to query for it using GetAllSchedulers or similar endpoint
                
                return new WP_Error(
                    'scheduler_already_exists',
                    'יומן זה כבר קיים בפרוקסי. נראה שיצרת scheduler עם אותו Google Calendar בעבר. אם ברצונך ליצור scheduler חדש, בחר יומן אחר מ-Google Calendar, או מחק את ה-scheduler הקיים.',
                    array(
                        'status' => 409, // 409 Conflict
                        'debug' => $debug_data,
                        'source_scheduler_id' => $source_scheduler_id,
                        'error_type' => 'duplicate_scheduler',
                        'help' => 'אפשרויות: 1) בחר יומן אחר מ-Google Calendar. 2) מחק את ה-scheduler הקיים בפרוקסי. 3) השתמש ב-scheduler הקיים אם אתה יודע את ה-proxy_schedule_id שלו.'
                    )
                );
            }
            
            return new WP_Error('proxy_error', $error_msg, array(
                'status' => 500,
                'debug' => $debug_data
            ));
        }
        
        // Get proxy scheduler ID from result
        // The result->result contains the schedulerID returned from proxy
        $proxy_schedule_id = isset($result->result) ? intval($result->result) : null;
        
        if (!$proxy_schedule_id) {
            return new WP_Error('no_scheduler_id', 'Proxy did not return scheduler ID', array('status' => 500));
        }
        
        // Save proxy scheduler ID to WordPress meta
        // This is used for all proxy operations related to this scheduler
        update_post_meta($scheduler_id, 'proxy_schedule_id', $proxy_schedule_id);
        update_post_meta($scheduler_id, 'proxy_connected', true);
        update_post_meta($scheduler_id, 'proxy_connected_at', current_time('mysql'));
        
        // Create JetEngine Relations (using API layer service)
        // יצירת קשרים בין היומן למרפאה ורופא
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-jetengine-relations-service.php';
        $relations_service = Clinic_Queue_JetEngine_Relations_Service::get_instance();
        $relations_result = $relations_service->create_scheduler_relations($scheduler_id);
        
        if (!$relations_result['success']) {
            // לא נכשיל את כל הפעולה בגלל Relations
        }
        
        // Update JetEngine switcher field (for display/filtering)
        update_post_meta($scheduler_id, 'doctor_online_proxy_connected', true);
        
        // Update post title to include proxy scheduler ID at the beginning
        $current_post = get_post($scheduler_id);
        if ($current_post) {
            $current_title = $current_post->post_title;
            // Add proxy scheduler ID with icon at the beginning
            $new_title = '🆔 ' . $proxy_schedule_id . ' | ' . $current_title;
            wp_update_post(array(
                'ID' => $scheduler_id,
                'post_title' => $new_title
            ));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Scheduler created successfully in proxy',
            'data' => array(
                'proxy_schedule_id' => $proxy_schedule_id, // Proxy scheduler ID (used for all proxy operations)
                'wordpress_scheduler_id' => $scheduler_id, // WordPress post ID (post_type = schedules)
                'source_scheduler_id' => $source_scheduler_id // Source Calendar ID (Google/DRWeb)
            )
        ));
    }
    
    // ============================================
    // Source Credentials Endpoints
    // ============================================
    
    /**
     * Save source credentials
     * POST /clinic-queue/v1/source-credentials/save
     */
    public function save_source_credentials($request) {
        $body = $request->get_json_params();
        
        if (empty($body)) {
            return new WP_Error('invalid_request', 'בקשה לא תקינה', array('status' => 400));
        }
        
        $scheduler_id = $request->get_param('scheduler_id') ?: ($body['scheduler_id'] ?? null);
        if (!$scheduler_id) {
            return new WP_Error('missing_scheduler_id', 'מזהה יומן הוא חובה', array('status' => 400));
        }
        
        $result = $this->source_credentials_service->save_source_credentials($body, intval($scheduler_id));
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * ============================================
     * Google Calendar Routes
     * ============================================
     */
    
    /**
     * Register Google Calendar routes
     */
    private function register_google_calendar_routes() {
        // POST /clinic-queue/v1/google/connect
        register_rest_route('clinic-queue/v1', '/google/connect', array(
            'methods' => 'POST',
            'callback' => array($this, 'google_connect'),
            'permission_callback' => 'is_user_logged_in',
            'args' => array(
                'code' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Authorization code from Google OAuth'
                ),
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Scheduler post ID'
                )
            )
        ));
        
        // GET /clinic-queue/v1/google/calendars
        register_rest_route('clinic-queue/v1', '/google/calendars', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_google_calendars'),
            'permission_callback' => 'is_user_logged_in',
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'source_creds_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            )
        ));
    }
    
    /**
     * Google Connect Handler
     * מטפל בחיבור ליומן Google Calendar
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function google_connect($request) {
        $code = $request->get_param('code');
        $scheduler_id = $request->get_param('scheduler_id');
        
        // Validation
        if (empty($code)) {
            return new WP_Error('missing_code', 'Authorization code is required', array('status' => 400));
        }
        
        if (empty($scheduler_id)) {
            return new WP_Error('missing_scheduler_id', 'Scheduler ID is required', array('status' => 400));
        }
        
        // בדיקה שהפוסט קיים ושהמשתמש הנוכחי הוא הבעלים
        $post = get_post($scheduler_id);
        
        if (!$post || $post->post_type !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Scheduler not found', array('status' => 404));
        }
        
        $current_user_id = get_current_user_id();
        if ($post->post_author != $current_user_id && !current_user_can('edit_others_posts')) {
            return new WP_Error('permission_denied', 'You do not have permission to connect this scheduler', array('status' => 403));
        }
        
        // Check if Google Calendar Service is available
        if (!class_exists('Clinic_Queue_Google_Calendar_Service')) {
            return new WP_Error(
                'service_unavailable',
                'Google Calendar service is not available. Please contact administrator.',
                array('status' => 503)
            );
        }
        
        // Initialize Google Calendar Service
        $google_service = new Clinic_Queue_Google_Calendar_Service();
        
        // Step 1: Exchange code for tokens
        $tokens_result = $google_service->exchange_code_for_tokens($code);
        
        if (is_wp_error($tokens_result)) {
            $this->scheduler_service->log_google_error($scheduler_id, $tokens_result->get_error_message());
            return new WP_Error(
                'token_exchange_failed',
                'Failed to connect to Google: ' . $tokens_result->get_error_message(),
                array('status' => 500)
            );
        }
        
        // Step 2: Get user info
        $user_info_result = $google_service->get_user_info($tokens_result['access_token']);
        
        if (is_wp_error($user_info_result)) {
            $this->scheduler_service->log_google_error($scheduler_id, $user_info_result->get_error_message());
            return new WP_Error(
                'user_info_failed',
                'Failed to get user information: ' . $user_info_result->get_error_message() . ' (Token: ' . substr($tokens_result['access_token'], 0, 10) . '...)',
                array('status' => 500, 'debug' => array('token_length' => strlen($tokens_result['access_token'])))
            );
        }
        
        // Step 3: Prepare credentials array
        $credentials = array(
            'access_token' => $tokens_result['access_token'],
            'refresh_token' => $tokens_result['refresh_token'],
            'expires_at' => $tokens_result['expires_at'],
            'user_email' => $user_info_result['email'],
            'timezone' => 'Asia/Jerusalem' // Default, יכול להשתנות בעתיד
        );
        
        // Step 4: Save credentials to scheduler meta fields
        $save_result = $this->scheduler_service->save_google_credentials($scheduler_id, $credentials);
        
        if (!$save_result) {
            return new WP_Error(
                'save_failed',
                'Failed to save Google credentials',
                array('status' => 500)
            );
        }
        
        // Step 5: Save credentials to proxy and get sourceCredentialsID
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-source-credentials-service.php';
        $source_creds_service = new Clinic_Queue_Source_Credentials_Service();
        
        // Debug info for browser console
        $debug_info = array();
        
        // Check API endpoint and token
        $api_endpoint = defined('CLINIC_QUEUE_API_ENDPOINT') ? CLINIC_QUEUE_API_ENDPOINT : get_option('clinic_queue_api_endpoint', null);
        $api_token = defined('CLINIC_QUEUE_API_TOKEN') ? CLINIC_QUEUE_API_TOKEN : null;
        
        $debug_info[] = 'API Endpoint: ' . ($api_endpoint ? $api_endpoint : 'NOT SET');
        $debug_info[] = 'API Token: ' . ($api_token ? substr($api_token, 0, 20) . '...' : 'NOT SET');
        
        // Get schedule_type from post meta (saved from form step 1)
        // This comes from the user's choice in step 1: 'google' or 'clinix'
        $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
        $debug_info[] = 'Schedule type from meta: ' . ($schedule_type ? $schedule_type : 'NOT SET');
        
        // Convert schedule_type to API sourceType format
        // 'google' → 'Google' (for Google Calendar)
        // 'clinix' → 'DBWeb' (for Doctor Clinix/DRWeb)
        $source_type_map = array(
            'google' => 'Google',
            'clinix' => 'DBWeb'
        );
        
        // If schedule_type is not set or invalid, default to 'Google' (since we're in google_connect)
        if (empty($schedule_type) || !isset($source_type_map[$schedule_type])) {
            $debug_info[] = 'WARNING: schedule_type is missing or invalid, defaulting to Google';
            $schedule_type = 'google'; // Default to google for this endpoint
        }
        
        $source_type = $source_type_map[$schedule_type];
        $debug_info[] = 'Converted sourceType: ' . $source_type . ' (from schedule_type: ' . $schedule_type . ')';
        
        // Convert expires_at to ISO 8601 format (required by API)
        // API expects ISO 8601 with timezone (e.g., "2025-12-28T16:01:32.063Z")
        // Prefer calculating from expires_in (seconds) to avoid timezone conversion issues
        $expires_at_iso = '';
        if (!empty($tokens_result['expires_in'])) {
            // Calculate expiration time directly in UTC from expires_in (seconds from now)
            $expires_in_seconds = intval($tokens_result['expires_in']);
            $expires_timestamp = time() + $expires_in_seconds;
            $expires_at_iso = gmdate('Y-m-d\TH:i:s.000\Z', $expires_timestamp);
            $debug_info[] = 'Calculated expires_at from expires_in (' . $expires_in_seconds . ' seconds): "' . $expires_at_iso . '"';
        } elseif (!empty($tokens_result['expires_at'])) {
            // Fallback: parse expires_at (should already be in UTC format from exchange_code_for_tokens)
            try {
                // expires_at is now in UTC format from exchange_code_for_tokens, so parse as UTC
                $expires_datetime = new DateTime($tokens_result['expires_at'], new DateTimeZone('UTC'));
                // Format: Y-m-d\TH:i:s.000\Z (ISO 8601 with milliseconds)
                $iso_string = $expires_datetime->format('Y-m-d\TH:i:s');
                $microseconds = intval($expires_datetime->format('u'));
                // Convert microseconds to milliseconds (first 3 digits)
                $milliseconds = str_pad(strval(intval($microseconds / 1000)), 3, '0', STR_PAD_LEFT);
                $expires_at_iso = $iso_string . '.' . $milliseconds . 'Z';
                $debug_info[] = 'Converted expires_at from "' . $tokens_result['expires_at'] . '" to ISO 8601: "' . $expires_at_iso . '"';
            } catch (Exception $e) {
                $debug_info[] = 'WARNING: Failed to convert expires_at to ISO 8601: ' . $e->getMessage();
                // Fallback: use current time + 1 hour
                $expires_at_iso = gmdate('Y-m-d\TH:i:s.000\Z', time() + 3600);
                $debug_info[] = 'Using fallback expires_at: ' . $expires_at_iso;
            }
        } else {
            // If neither expires_in nor expires_at is set, default to 1 hour from now
            $expires_at_iso = gmdate('Y-m-d\TH:i:s.000\Z', time() + 3600);
            $debug_info[] = 'WARNING: Neither expires_in nor expires_at is set, using default (1 hour from now): ' . $expires_at_iso;
        }
        
        $credentials_data = array(
            'sourceType' => $source_type,
            'accessToken' => $tokens_result['access_token'],
            'accessTokenExpiresIn' => $expires_at_iso,
            'refreshToken' => $tokens_result['refresh_token']
        );
        
        $debug_info[] = 'Sending credentials to proxy...';
        $debug_info[] = 'Credentials data keys: ' . implode(', ', array_keys($credentials_data));
        $debug_info[] = 'accessTokenExpiresIn (ISO 8601): ' . $expires_at_iso;
        
        $source_creds_result = $source_creds_service->save_source_credentials($credentials_data, $scheduler_id);
        
        $debug_info[] = 'Source credentials save attempt completed';
        $debug_info[] = 'Result type: ' . gettype($source_creds_result);
        
        // Extract raw body for debugging
        $raw_proxy_response = null;
        if (is_array($source_creds_result) && isset($source_creds_result['raw_body'])) {
            $raw_proxy_response = $source_creds_result['raw_body'];
            $source_creds_result = $source_creds_result['model']; // Use the model for processing
            $debug_info[] = '=== תשובה גולמית מהפרוקסי API ===';
            $debug_info[] = $raw_proxy_response;
        }
        
        if (is_wp_error($source_creds_result)) {
            $error_message = $source_creds_result->get_error_message();
            $error_data = $source_creds_result->get_error_data();
            $debug_info[] = 'WP_Error occurred: ' . $error_message;
            $debug_info[] = 'Error data: ' . json_encode($error_data, JSON_PRETTY_PRINT);
            
            // If we have raw body in error data, add it to debug and to response
            if (isset($error_data['raw_body'])) {
                $raw_proxy_response = $error_data['raw_body'];
                $debug_info[] = '=== תשובה גולמית מהפרוקסי API (מתוך Error) ===';
                $debug_info[] = $raw_proxy_response;
            }
            
            // Continue anyway - we can retry later
            $source_creds_id = null;
        } else {
            // Check if save was successful
            if (is_object($source_creds_result)) {
                $debug_info[] = 'Result class: ' . get_class($source_creds_result);
                if (method_exists($source_creds_result, 'to_array')) {
                    $debug_info[] = 'Result data: ' . json_encode($source_creds_result->to_array(), JSON_PRETTY_PRINT);
                } else {
                    $debug_info[] = 'Result data: ' . json_encode(get_object_vars($source_creds_result), JSON_PRETTY_PRINT);
                }
                
                if (isset($source_creds_result->code)) {
                    $debug_info[] = 'Response code: ' . $source_creds_result->code;
                    
                    if ($source_creds_result->code === 'Success') {
                        $result_value = $source_creds_result->result;
                        $debug_info[] = 'Response result value: ' . json_encode($result_value, JSON_PRETTY_PRINT);
                        $source_creds_id = isset($result_value) ? intval($result_value) : null;
                        
                        if ($source_creds_id) {
                            // Save source_creds_id to scheduler meta
                            update_post_meta($scheduler_id, 'source_credentials_id', $source_creds_id);
                            $debug_info[] = 'Source credentials saved to proxy. ID: ' . $source_creds_id;
                        } else {
                            $debug_info[] = 'WARNING: Response code is Success but result is null or invalid';
                        }
                    } else {
                        $error_msg = isset($source_creds_result->error) ? $source_creds_result->error : 'Unknown error';
                        $debug_info[] = 'Proxy returned error code: ' . $source_creds_result->code;
                        $debug_info[] = 'Error message: ' . $error_msg;
                        $source_creds_id = null;
                    }
                } else {
                    $debug_info[] = 'WARNING: Response object does not have code property';
                    $source_creds_id = null;
                }
            } else {
                $debug_info[] = 'WARNING: Result is not an object. Type: ' . gettype($source_creds_result);
                $debug_info[] = 'Result value: ' . json_encode($source_creds_result, JSON_PRETTY_PRINT);
                $source_creds_id = null;
            }
        }
        
        // Step 6: Return success with user info
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Successfully connected to Google Calendar',
            'data' => array(
                'scheduler_id' => $scheduler_id,
                'user_email' => $user_info_result['email'],
                'user_name' => isset($user_info_result['name']) ? $user_info_result['name'] : '',
                'connected_at' => current_time('mysql'),
                'source_credentials_id' => $source_creds_id,
                'calendar_id' => 'primary'
            ),
            'debug' => $debug_info, // Send debug info to browser console
            'proxy_raw_response' => $raw_proxy_response // Raw response from proxy API for debugging
        ));
    }
    
    /**
     * Get Google calendars list
     * GET /clinic-queue/v1/google/calendars
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_google_calendars($request) {
        $scheduler_id = $request->get_param('scheduler_id');
        $source_creds_id = $request->get_param('source_creds_id');
        
        // Validation
        if (empty($scheduler_id) || empty($source_creds_id)) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }
        
        // Check permissions
        $post = get_post($scheduler_id);
        if (!$post || $post->post_type !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Scheduler not found', array('status' => 404));
        }
        
        $current_user_id = get_current_user_id();
        if ($post->post_author != $current_user_id && !current_user_can('edit_others_posts')) {
            return new WP_Error('permission_denied', 'Permission denied', array('status' => 403));
        }
        
        // Get calendars from proxy
        $result = $this->scheduler_service->get_all_source_calendars($source_creds_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        // Format response
        $calendars = array();
        if (isset($result->result) && is_array($result->result)) {
            foreach ($result->result as $calendar) {
                $calendars[] = array(
                    'sourceSchedulerID' => isset($calendar['sourceSchedulerID']) ? $calendar['sourceSchedulerID'] : '',
                    'name' => isset($calendar['name']) ? $calendar['name'] : '',
                    'description' => isset($calendar['description']) ? $calendar['description'] : ''
                );
            }
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'calendars' => $calendars
        ));
    }
}
