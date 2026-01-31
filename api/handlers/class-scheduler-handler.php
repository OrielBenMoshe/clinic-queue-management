<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-scheduler-service.php';
require_once __DIR__ . '/../services/class-jetengine-relations-service.php';
require_once __DIR__ . '/../models/class-scheduler-model.php';

/**
 * Scheduler Handler
 * מטפל בכל endpoints הקשורים ליומנים ו-schedulers
 * 
 * Endpoints:
 * - GET /scheduler/source-calendars - קבלת כל יומני המקור
 * - GET /scheduler/drweb-calendar-reasons - קבלת סיבות יומן DRWeb
 * - GET /scheduler/drweb-calendar-active-hours - קבלת שעות פעילות יומן DRWeb
 * - GET /scheduler/free-time - קבלת זמנים פנויים
 * - GET /scheduler/check-slot-available - בדיקת זמינות slot
 * - GET /scheduler/properties - קבלת מאפייני scheduler
 * - POST /scheduler/create-schedule-in-proxy - יצירת scheduler בפרוקסי
 * 
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Scheduler_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * Scheduler Service instance
     * 
     * @var Clinic_Queue_Scheduler_Service
     */
    private $scheduler_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize service
        $this->scheduler_service = new Clinic_Queue_Scheduler_Service();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // GET /scheduler/source-calendars
        register_rest_route($this->namespace, '/scheduler/source-calendars', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_source_calendars'),
            'permission_callback' => array($this, 'permission_callback_public'),
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
        
        // GET /scheduler/drweb-calendar-reasons
        register_rest_route($this->namespace, '/scheduler/drweb-calendar-reasons', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_drweb_calendar_reasons'),
            'permission_callback' => array($this, 'permission_callback_public'),
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
        
        // GET /scheduler/drweb-calendar-active-hours
        register_rest_route($this->namespace, '/scheduler/drweb-calendar-active-hours', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_drweb_calendar_active_hours'),
            'permission_callback' => array($this, 'permission_callback_public'),
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
        
        // GET /scheduler/free-time
        register_rest_route($this->namespace, '/scheduler/free-time', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_free_time'),
            'permission_callback' => array($this, 'permission_callback_public'),
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
        
        // GET /scheduler/check-slot-available
        register_rest_route($this->namespace, '/scheduler/check-slot-available', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_slot_available'),
            'permission_callback' => array($this, 'permission_callback_public'),
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
        
        // GET /scheduler/properties
        register_rest_route($this->namespace, '/scheduler/properties', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_scheduler_properties'),
            'permission_callback' => array($this, 'permission_callback_public'),
            'args' => array(
                'scheduler_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // POST /scheduler/create-schedule-in-proxy
        register_rest_route($this->namespace, '/scheduler/create-schedule-in-proxy', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_scheduler_in_proxy'),
            'permission_callback' => array($this, 'permission_callback_logged_in'),
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
     * Get all source calendars
     * GET /clinic-queue/v1/scheduler/source-calendars
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_all_source_calendars($request) {
        $source_creds_id = $this->get_int_param($request, 'source_creds_id');
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        
        $result = $this->scheduler_service->get_all_source_calendars($source_creds_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get DRWeb calendar reasons
     * GET /clinic-queue/v1/scheduler/drweb-calendar-reasons
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_drweb_calendar_reasons($request) {
        $source_creds_id = $this->get_int_param($request, 'source_creds_id');
        $drweb_calendar_id = $this->get_string_param($request, 'drweb_calendar_id');
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        
        $result = $this->scheduler_service->get_drweb_calendar_reasons($source_creds_id, $drweb_calendar_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get DRWeb calendar active hours
     * GET /clinic-queue/v1/scheduler/drweb-calendar-active-hours
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_drweb_calendar_active_hours($request) {
        $source_creds_id = $this->get_int_param($request, 'source_creds_id');
        $drweb_calendar_id = $this->get_string_param($request, 'drweb_calendar_id');
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        
        $result = $this->scheduler_service->get_drweb_calendar_active_hours($source_creds_id, $drweb_calendar_id, $scheduler_id);
        
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get free time slots
     * GET /clinic-queue/v1/scheduler/free-time
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_free_time($request) {
        // Support both schedulerIDsStr (new) and scheduler_id (legacy)
        $schedulerIDsStr = $this->get_string_param($request, 'schedulerIDsStr');
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        
        // Get duration
        $duration = $this->get_int_param($request, 'duration');
        
        // Get dates - support both fromDateUTC/toDateUTC (new) and from_date_utc/to_date_utc (legacy)
        $fromDateUTC = $this->get_string_param($request, 'fromDateUTC') ?: $this->get_string_param($request, 'from_date_utc');
        $toDateUTC = $this->get_string_param($request, 'toDateUTC') ?: $this->get_string_param($request, 'to_date_utc');
        
        // If schedulerIDsStr is provided, use API Manager directly
        if (!empty($schedulerIDsStr)) {
            $api_manager = Clinic_Queue_API_Manager::get_instance();
            $result = $api_manager->get_appointments_data_by_scheduler_ids(
                $schedulerIDsStr,
                $duration,
                $fromDateUTC,
                $toDateUTC
            );
            
            if (is_wp_error($result)) {
                // תגובת הפרוקסי מועברת ללקוח ב-error_data.proxy_response
                if ($this->error_handler) {
                    return Clinic_Queue_Error_Handler::format_rest_error($result);
                }
                return $result;
            }
            
            // Fallback: null (לא אמור לקרות – השירות מחזיר WP_Error בשגיאה)
            if ($result === null) {
                return rest_ensure_response(array(
                    'code' => 'Error',
                    'error' => 'Failed to fetch free time slots from proxy API',
                    'result' => array()
                ));
            }
            
            // Convert formatted result back to flat array format
            $flat_result = array();
            if (isset($result['days']) && is_array($result['days'])) {
                foreach ($result['days'] as $day) {
                    if (isset($day['slots']) && is_array($day['slots'])) {
                        foreach ($day['slots'] as $slot) {
                            $flat_result[] = array(
                                'from' => $slot['from'],
                                'schedulerID' => $slot['schedulerID'] ?? 0
                            );
                        }
                    }
                }
            }
            
            return rest_ensure_response(array(
                'code' => 'Success',
                'error' => null,
                'result' => $flat_result
            ));
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
                if ($this->error_handler) {
                    return Clinic_Queue_Error_Handler::format_rest_error($result);
                }
                return $result;
            }
            
            return rest_ensure_response($result->to_array());
        }
        
        // Error: missing required parameters
        return $this->error_response(
            'Missing required parameters: schedulerIDsStr or scheduler_id',
            400,
            'missing_parameters'
        );
    }
    
    /**
     * Check if slot is available
     * GET /clinic-queue/v1/scheduler/check-slot-available
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function check_slot_available($request) {
        $slot_model = new Clinic_Queue_Check_Slot_Available_Model();
        $slot_model->schedulerID = $this->get_int_param($request, 'scheduler_id');
        $slot_model->fromUTC = $this->get_string_param($request, 'from_utc');
        $slot_model->duration = $this->get_int_param($request, 'duration');
        
        $result = $this->scheduler_service->check_slot_available($slot_model, intval($slot_model->schedulerID));
        
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get scheduler properties
     * GET /clinic-queue/v1/scheduler/properties
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_scheduler_properties($request) {
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        
        $result = $this->scheduler_service->get_scheduler_properties($scheduler_id);
        
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Create scheduler in proxy
     * POST /clinic-queue/v1/scheduler/create-schedule-in-proxy
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create_scheduler_in_proxy($request) {
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        $source_creds_id = $this->get_int_param($request, 'source_credentials_id');
        $source_scheduler_id = $this->get_string_param($request, 'source_scheduler_id');
        
        // Validation
        if (empty($scheduler_id) || empty($source_creds_id) || empty($source_scheduler_id)) {
            return $this->error_response(
                'Missing required parameters',
                400,
                'missing_params'
            );
        }
        
        // Check permissions
        $post = get_post($scheduler_id);
        if (!$post || $post->post_type !== 'schedules') {
            return $this->error_response(
                'Scheduler not found',
                404,
                'invalid_scheduler'
            );
        }
        
        $current_user_id = get_current_user_id();
        if ($post->post_author != $current_user_id && !current_user_can('edit_others_posts')) {
            return $this->error_response(
                'Permission denied',
                403,
                'permission_denied'
            );
        }
        
        // Get schedule_type from post meta
        $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
        
        // Active hours handling
        $active_hours = null;
        
        if ($schedule_type === 'google') {
            // For Google Calendar: Get activeHours from request body
            $request_body = $request->get_json_params();
            $active_hours_data = isset($request_body['active_hours']) ? $request_body['active_hours'] : null;
            
            if ($active_hours_data && is_array($active_hours_data)) {
                $active_hours = $this->scheduler_service->convert_days_to_active_hours($active_hours_data);
            } else {
                // Try to get from WordPress meta as fallback
                $days_data = array();
                $day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
                
                foreach ($day_keys as $day_key) {
                    $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                    if ($time_ranges && is_array($time_ranges) && !empty($time_ranges)) {
                        $formatted_ranges = array();
                        foreach ($time_ranges as $range) {
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
            
            // For Google Calendar, activeHours is required
            if (empty($active_hours) || !is_array($active_hours) || count($active_hours) === 0) {
                return $this->error_response(
                    'Active hours are required for Google Calendar scheduler. Please configure working hours in the schedule settings before connecting to Google Calendar.',
                    400,
                    'missing_active_hours'
                );
            }
            
            // Validate active_hours structure
            foreach ($active_hours as $index => $hour) {
                if (!isset($hour['weekDay']) || !isset($hour['fromUTC']) || !isset($hour['toUTC'])) {
                    return $this->error_response(
                        'Invalid active hours format. Please reconfigure working hours.',
                        400,
                        'invalid_active_hours'
                    );
                }
                
                if (!is_string($hour['fromUTC']) || !is_string($hour['toUTC'])) {
                    return $this->error_response(
                        'Invalid time format in active hours. Expected HH:mm:ss strings.',
                        400,
                        'invalid_active_hours'
                    );
                }
            }
        } elseif ($schedule_type === 'clinix' || $schedule_type === 'drweb') {
            // For DRWeb: Get schedule days data from WordPress meta
            $days_data = array();
            $day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
            
            foreach ($day_keys as $day_key) {
                $time_ranges = get_post_meta($scheduler_id, $day_key, true);
                if ($time_ranges && is_array($time_ranges)) {
                    $days_data[$day_key] = $time_ranges;
                }
            }
            
            if (!empty($days_data)) {
                $active_hours = $this->scheduler_service->convert_days_to_active_hours($days_data);
            }
        }
        
        // Create Model
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-scheduler-model.php';
        $scheduler_model = new Clinic_Queue_Create_Scheduler_Model();
        $scheduler_model->sourceCredentialsID = $source_creds_id;
        $scheduler_model->sourceSchedulerID = $source_scheduler_id;
        $scheduler_model->activeHours = $active_hours;
        $scheduler_model->maxOverlappingMeeting = 1;
        $scheduler_model->overlappingDurationInMinutes = 0;
        
        // Debug data
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
            
            // Check for duplicate entry error
            if (stripos($error_msg, 'Duplicate entry') !== false && stripos($error_msg, 'UQ_Scheduler_Source') !== false) {
                return $this->error_response(
                    'יומן זה כבר קיים בפרוקסי. נראה שיצרת scheduler עם אותו Google Calendar בעבר. אם ברצונך ליצור scheduler חדש, בחר יומן אחר מ-Google Calendar, או מחק את ה-scheduler הקיים.',
                    409,
                    'scheduler_already_exists',
                    array(
                        'debug' => $debug_data,
                        'source_scheduler_id' => $source_scheduler_id,
                        'error_type' => 'duplicate_scheduler',
                        'help' => 'אפשרויות: 1) בחר יומן אחר מ-Google Calendar. 2) מחק את ה-scheduler הקיים בפרוקסי. 3) השתמש ב-scheduler הקיים אם אתה יודע את ה-proxy_schedule_id שלו.'
                    )
                );
            }
            
            return $this->error_response(
                $error_msg,
                500,
                'proxy_error',
                array('debug' => $debug_data)
            );
        }
        
        // Get proxy scheduler ID from result
        $proxy_schedule_id = isset($result->result) ? intval($result->result) : null;
        
        if (!$proxy_schedule_id) {
            return $this->error_response(
                'Proxy did not return scheduler ID',
                500,
                'no_scheduler_id'
            );
        }
        
        // Save proxy scheduler ID to WordPress meta
        update_post_meta($scheduler_id, 'proxy_schedule_id', $proxy_schedule_id);
        update_post_meta($scheduler_id, 'proxy_connected', true);
        update_post_meta($scheduler_id, 'proxy_connected_at', current_time('mysql'));
        
        // Create JetEngine Relations
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-jetengine-relations-service.php';
        $relations_service = Clinic_Queue_JetEngine_Relations_Service::get_instance();
        $relations_result = $relations_service->create_scheduler_relations($scheduler_id);
        
        // Update JetEngine switcher field
        update_post_meta($scheduler_id, 'doctor_online_proxy_connected', true);
        
        // Update post title to include proxy scheduler ID
        $current_post = get_post($scheduler_id);
        if ($current_post) {
            $current_title = $current_post->post_title;
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
                'proxy_schedule_id' => $proxy_schedule_id,
                'wordpress_scheduler_id' => $scheduler_id,
                'source_scheduler_id' => $source_scheduler_id
            )
        ));
    }
}
