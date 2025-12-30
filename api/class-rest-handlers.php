<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/services/class-appointment-service.php';
require_once __DIR__ . '/services/class-scheduler-service.php';
require_once __DIR__ . '/services/class-source-credentials-service.php';
require_once __DIR__ . '/dto/class-appointment-dto.php';
require_once __DIR__ . '/dto/class-scheduler-dto.php';
require_once __DIR__ . '/dto/class-response-dto.php';
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
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'duration' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'from_date_utc' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'to_date_utc' => array(
                    'required' => true,
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
     */
    public function get_all_appointments($request) {
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $calendars = $api_manager->get_all_calendars();
        
        if (empty($calendars)) {
            return new WP_Error('no_calendars', 'No calendars found', array('status' => 404));
        }
        
        $result = array('calendars' => array());
        
        foreach ($calendars as $calendar) {
            $appointments_data = $api_manager->get_appointments_data(
                $calendar['id'],
                $calendar['doctor_id'],
                $calendar['clinic_id'],
                $calendar['treatment_type']
            );
            
            if ($appointments_data && !empty($appointments_data['days'])) {
                $appointments = array();
                foreach ($appointments_data['days'] as $day) {
                    $slots = array();
                    foreach ($day['slots'] as $slot) {
                        $slots[] = array(
                            'time' => $slot['time'],
                            'is_booked' => false
                        );
                    }
                    if (!empty($slots)) {
                        $appointments[$day['date']] = $slots;
                    }
                }
                
                $result['calendars'][] = array(
                    'doctor_id' => $calendar['doctor_id'],
                    'doctor_name' => $calendar['doctor_name'],
                    'clinic_id' => $calendar['clinic_id'],
                    'clinic_name' => $calendar['clinic_name'],
                    'treatment_type' => $calendar['treatment_type'],
                    'appointments' => $appointments
                );
            }
        }
        
        return rest_ensure_response($result);
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
        
        // Create DTOs
        $customer_dto = Clinic_Queue_Customer_DTO::from_array($body['customer'] ?? array());
        $appointment_dto = Clinic_Queue_Appointment_DTO::from_array($body);
        $appointment_dto->customer = $customer_dto;
        
        // Get scheduler ID from request or body
        $scheduler_id = $request->get_param('scheduler_id') ?: ($body['scheduler_id'] ?? null);
        if (!$scheduler_id) {
            return new WP_Error('missing_scheduler_id', 'מזהה יומן הוא חובה', array('status' => 400));
        }
        
        // Call service
        $result = $this->appointment_service->create_appointment($appointment_dto, intval($scheduler_id));
        
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
        $free_time_dto = new Clinic_Queue_Get_Free_Time_DTO();
        $free_time_dto->schedulerID = $request->get_param('scheduler_id');
        $free_time_dto->duration = $request->get_param('duration');
        $free_time_dto->fromDateUTC = $request->get_param('from_date_utc');
        $free_time_dto->toDateUTC = $request->get_param('to_date_utc');
        
        $result = $this->scheduler_service->get_free_time($free_time_dto, intval($free_time_dto->schedulerID));
        
        if (is_wp_error($result)) {
            return Clinic_Queue_Error_Handler::format_rest_error($result);
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Check if slot is available
     * GET /clinic-queue/v1/scheduler/check-slot-available
     */
    public function check_slot_available($request) {
        $slot_dto = new Clinic_Queue_Check_Slot_Available_DTO();
        $slot_dto->schedulerID = $request->get_param('scheduler_id');
        $slot_dto->fromUTC = $request->get_param('from_utc');
        $slot_dto->duration = $request->get_param('duration');
        
        $result = $this->scheduler_service->check_slot_available($slot_dto, intval($slot_dto->schedulerID));
        
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
        error_log('[Clinic Queue] About to get user info with access_token: ' . substr($tokens_result['access_token'], 0, 20) . '...');
        $user_info_result = $google_service->get_user_info($tokens_result['access_token']);
        
        if (is_wp_error($user_info_result)) {
            error_log('[Clinic Queue] User info failed: ' . $user_info_result->get_error_message());
            error_log('[Clinic Queue] Tokens result: ' . print_r($tokens_result, true));
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
        
        // Step 5: Return success with user info
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Successfully connected to Google Calendar',
            'data' => array(
                'scheduler_id' => $scheduler_id,
                'user_email' => $user_info_result['email'],
                'user_name' => isset($user_info_result['name']) ? $user_info_result['name'] : '',
                'connected_at' => current_time('mysql'),
                'calendar_id' => 'primary'
            )
        ));
    }
}
