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
        
        // POST /clinic-queue/v1/scheduler/create-proxy
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
    
    /**
     * Create scheduler in proxy
     * POST /clinic-queue/v1/scheduler/create-proxy
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
        
        // Active hours are only required for DRWeb, not for Google Calendar
        $active_hours = array();
        
        if ($schedule_type === 'clinix' || $schedule_type === 'drweb') {
            // For DRWeb: Get schedule days data from WordPress meta and convert to activeHours
            $days_data = array();
            $day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
            
            foreach ($day_keys as $day_key) {
                $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                if ($time_ranges && is_array($time_ranges)) {
                    $days_data[$day_key] = $time_ranges;
                }
            }
            
            if (empty($days_data)) {
                return new WP_Error('no_active_hours', 'No active hours configured for DRWeb scheduler', array('status' => 400));
            }
            
            // Convert days to activeHours format
            $active_hours = $this->scheduler_service->convert_days_to_active_hours($days_data);
            
            if (empty($active_hours)) {
                return new WP_Error('conversion_failed', 'Failed to convert active hours', array('status' => 500));
            }
        } else {
            // For Google Calendar: activeHours should be empty array
            // Google Calendar manages its own schedule, so we don't send activeHours
            $active_hours = array();
        }
        
        // Create DTO
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/dto/class-scheduler-dto.php';
        $scheduler_dto = new Clinic_Queue_Create_Scheduler_DTO();
        $scheduler_dto->sourceCredentialsID = $source_creds_id;
        $scheduler_dto->sourceSchedulerID = $source_scheduler_id;
        $scheduler_dto->activeHours = $active_hours;
        $scheduler_dto->maxOverlappingMeeting = 1; // Default
        $scheduler_dto->overlappingDurationInMinutes = 0; // Default
        
        // Debug: Log DTO data before sending
        $debug_info = array();
        $debug_info[] = 'Scheduler ID: ' . $scheduler_id;
        $debug_info[] = 'Schedule Type: ' . ($schedule_type ? $schedule_type : 'NOT SET');
        $debug_info[] = 'Source Credentials ID: ' . $source_creds_id;
        $debug_info[] = 'Source Scheduler ID: ' . $source_scheduler_id;
        $debug_info[] = 'Active Hours Count: ' . count($active_hours) . ' (empty for Google, required for DRWeb)';
        $debug_info[] = 'Max Overlapping Meeting: ' . $scheduler_dto->maxOverlappingMeeting;
        $debug_info[] = 'Overlapping Duration (minutes): ' . $scheduler_dto->overlappingDurationInMinutes;
        
        // Log first few active hours for debugging
        if (!empty($active_hours)) {
            $debug_info[] = 'First active hour: ' . json_encode($active_hours[0], JSON_UNESCAPED_UNICODE);
            if (count($active_hours) > 1) {
                $debug_info[] = 'Second active hour: ' . json_encode($active_hours[1], JSON_UNESCAPED_UNICODE);
            }
        }
        
        $dto_array = $scheduler_dto->to_array();
        $debug_info[] = 'DTO to_array() result: ' . json_encode($dto_array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        // Create scheduler in proxy
        $result = $this->scheduler_service->create_scheduler($scheduler_dto, $scheduler_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check if creation was successful
        if (!isset($result->code) || $result->code !== 'Success') {
            $error_msg = isset($result->error) ? $result->error : 'Failed to create scheduler';
            
            // Add debug info to error response
            $debug_info[] = 'Proxy response code: ' . (isset($result->code) ? $result->code : 'not set');
            $debug_info[] = 'Proxy response error: ' . (isset($result->error) ? $result->error : 'not set');
            $debug_info[] = 'Proxy response result: ' . (isset($result->result) ? json_encode($result->result) : 'not set');
            
            // Return error with debug info
            $error_response = new WP_Error('proxy_error', $error_msg, array(
                'status' => 500,
                'debug' => $debug_info
            ));
            
            // WordPress REST API will automatically convert WP_Error to JSON
            // The debug info will be in error_data['debug']
            return $error_response;
        }
        
        // Get proxy scheduler ID from result
        $proxy_scheduler_id = isset($result->result) ? intval($result->result) : null;
        
        if (!$proxy_scheduler_id) {
            return new WP_Error('no_scheduler_id', 'Proxy did not return scheduler ID', array('status' => 500));
        }
        
        // Save proxy scheduler ID to WordPress meta
        update_post_meta($scheduler_id, 'proxy_scheduler_id', $proxy_scheduler_id);
        update_post_meta($scheduler_id, 'proxy_connected', true);
        update_post_meta($scheduler_id, 'proxy_connected_at', current_time('mysql'));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Scheduler created successfully in proxy',
            'data' => array(
                'proxy_scheduler_id' => $proxy_scheduler_id,
                'scheduler_id' => $scheduler_id
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
        
        if (is_wp_error($source_creds_result)) {
            $error_message = $source_creds_result->get_error_message();
            $error_data = $source_creds_result->get_error_data();
            $debug_info[] = 'WP_Error occurred: ' . $error_message;
            $debug_info[] = 'Error data: ' . json_encode($error_data, JSON_PRETTY_PRINT);
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
            'debug' => $debug_info // Send debug info to browser console
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
