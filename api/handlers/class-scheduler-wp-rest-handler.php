<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-scheduler-proxy-service.php';
require_once __DIR__ . '/../services/class-jetengine-relations-service.php';
require_once __DIR__ . '/../models/class-scheduler-model.php';

/**
 * Scheduler Handler – פניות ל-REST API של וורדפרס
 * מטפל בכל ה-endpoints של WordPress REST API הקשורים ליומנים ו-schedulers.
 *
 * Endpoints (namespace: clinic-queue/v1):
 * - GET /scheduler/source-calendars - קבלת כל יומני המקור
 * - GET /scheduler/drweb-calendar-reasons - קבלת סיבות יומן DRWeb
 * - GET /scheduler/drweb-calendar-active-hours - קבלת שעות פעילות יומן DRWeb
 * - GET /scheduler/free-time - קבלת זמנים פנויים
 * - GET /scheduler/check-slot-available - בדיקת זמינות slot
 * - GET /scheduler/properties - קבלת מאפייני scheduler
 * - POST /scheduler/create-schedule-in-proxy - יצירת scheduler בפרוקסי
 * - POST /scheduler/update - עדכון scheduler בפרוקסי (Scheduler/Update)
 *
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Scheduler_Wp_Rest_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * Scheduler Service instance
     * 
     * @var Clinic_Queue_Scheduler_Proxy_Service
     */
    private $scheduler_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize service
        $this->scheduler_service = new Clinic_Queue_Scheduler_Proxy_Service();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // GET /scheduler/source-calendars – גוגל וקליניקס: מקור אחד עם טוקן (מהפרונט) או scheduler_id+source_creds_id
        register_rest_route($this->namespace, '/scheduler/source-calendars', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_source_calendars'),
            'permission_callback' => array($this, 'permission_callback_source_calendars'),
            'args' => array(
                'token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'source_creds_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ),
                'scheduler_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // GET /scheduler/drweb-calendar-reasons (sourceCredsID from SourceCredentials/Save, drwebCalendarID from GetAllSourceCalendars)
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
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
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
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
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
            'permission_callback' => array($this, 'permission_callback_scheduler_access'),
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
                ),
                'access_token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Doctor connect access token (guest doctor flow)',
                ),
            )
        ));

        // POST /scheduler/set-active-hours – עדכון שעות פעילות ב-Scheduler/SetActiveHours (Google בלבד)
        register_rest_route($this->namespace, '/scheduler/set-active-hours', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'set_active_hours'),
            'permission_callback' => array($this, 'permission_callback_scheduler_update'),
            'args'                => array(
                'schedulerID' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'מזהה פוסט schedules בוורדפרס (או proxy_schedule_id אם קיים במטא)',
                ),
                'days' => array(
                    'required'    => true,
                    'type'        => 'object',
                    'description' => 'ימי עבודה ושעות: { sunday: [{start_time, end_time}], … }',
                ),
            ),
        ));

        // POST /scheduler/update – פרוקסי ל-Scheduler/Update (Doctor Online)
        register_rest_route($this->namespace, '/scheduler/update', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_scheduler_in_proxy'),
            'permission_callback' => array($this, 'permission_callback_scheduler_update'),
            'args' => array(
                'schedulerID' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'מזהה יומן (פוסט schedules, או proxy_schedule_id אם קיים במטא)',
                ),
                'isActive' => array(
                    'required' => true,
                    'type' => 'boolean',
                ),
            ),
        ));
    }
    
    /**
     * Permission for source-calendars: when token is sent (Clinix) require logged-in; otherwise public.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function permission_callback_source_calendars($request) {
        $token = $request->get_param('token');
        if (!empty($token)) {
            return is_user_logged_in();
        }
        return true;
    }

    /**
     * הרשאה ל-POST /scheduler/update: זיהוי פוסט schedules מ-schedulerID (או proxy_schedule_id) + אותם כללים כמו create.
     *
     * @param WP_REST_Request $request בקשה.
     * @return bool
     */
    public function permission_callback_scheduler_update($request) {
        $wp_schedule_id = $this->resolve_wp_schedule_post_id_for_request($request);
        if (empty($wp_schedule_id)) {
            return false;
        }
        $request->set_param('scheduler_id', $wp_schedule_id);
        return $this->permission_callback_scheduler_access($request);
    }

    /**
     * מחזיר מזהה פוסט WordPress מסוג schedules מגוף JSON / query.
     * מקבל schedulerID (או scheduler_id) כמזהה פוסט, או כמו proxy_schedule_id במטא.
     *
     * @param WP_REST_Request $request בקשה.
     * @return int|null מזהה פוסט או null
     */
    protected function resolve_wp_schedule_post_id_for_request($request) {
        $json = $request->get_json_params();
        if (!is_array($json)) {
            $json = array();
        }

        $raw = 0;
        if (array_key_exists('schedulerID', $json)) {
            $raw = absint($json['schedulerID']);
        } elseif (array_key_exists('scheduler_id', $json)) {
            $raw = absint($json['scheduler_id']);
        }

        if (empty($raw)) {
            $raw = absint($request->get_param('schedulerID'));
        }
        if (empty($raw)) {
            $raw = absint($request->get_param('scheduler_id'));
        }

        if (empty($raw)) {
            return null;
        }

        $post = get_post($raw);
        if ($post && $post->post_type === 'schedules') {
            return (int) $raw;
        }

        $query = new WP_Query(array(
            'post_type' => 'schedules',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => array(
                array(
                    'key' => 'proxy_schedule_id',
                    'value' => (string) $raw,
                    'compare' => '=',
                ),
            ),
        ));

        if (!empty($query->posts)) {
            return (int) $query->posts[0];
        }

        return null;
    }

    /**
     * מזהה schedulerID לשליחה לפרוקסי (עדיפות ל-proxy_schedule_id כשקיים).
     *
     * @param int $wp_schedule_id מזהה פוסט schedules.
     * @return int
     */
    protected function get_upstream_scheduler_id($wp_schedule_id) {
        $proxy_meta = get_post_meta((int) $wp_schedule_id, 'proxy_schedule_id', true);
        if ($proxy_meta !== '' && $proxy_meta !== null && is_numeric($proxy_meta)) {
            return absint($proxy_meta);
        }
        return (int) $wp_schedule_id;
    }

    /**
     * Get all source calendars – גוגל וקליניקס.
     * GET /clinic-queue/v1/scheduler/source-calendars
     *
     * מקבל: source_creds_id (חובה). אימות: טוקן האתר (אין פוסט יומן עדיין).
     * מחזיר: { success: true, calendars: [ { sourceSchedulerID, name, description, inUse }, ... ] }
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_all_source_calendars($request) {
        $source_creds_id = $this->get_int_param($request, 'source_creds_id', 0);

        if (0 === $source_creds_id && empty($request->get_param('source_creds_id'))) {
            return $this->error_response(
                'source_creds_id is required',
                400,
                'missing_params'
            );
        }

        $result = $this->scheduler_service->get_all_source_calendars($source_creds_id);

        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }

        $calendars = array();
        if (isset($result->result) && is_array($result->result)) {
            foreach ($result->result as $calendar) {
                $item = is_array($calendar) ? $calendar : (array) $calendar;
                $sid = isset($item['sourceSchedulerID']) ? $item['sourceSchedulerID'] : (isset($item['sourceSchedulerId']) ? $item['sourceSchedulerId'] : '');
                $calendars[] = array(
                    'sourceSchedulerID' => (string) $sid,
                    'name' => isset($item['name']) ? $item['name'] : '',
                    'description' => isset($item['description']) ? $item['description'] : '',
                    'inUse' => isset($item['inUse']) ? (bool) $item['inUse'] : false,
                );
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'calendars' => $calendars,
        ));
    }
    
    /**
     * Get DRWeb calendar reasons
     * GET /clinic-queue/v1/scheduler/drweb-calendar-reasons
     * source_creds_id = מתשובת SourceCredentials/Save; drweb_calendar_id = מזהה היומן מ-GetAllSourceCalendars
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_drweb_calendar_reasons($request) {
        $source_creds_id = $this->get_int_param($request, 'source_creds_id');
        $drweb_calendar_id = $this->get_string_param($request, 'drweb_calendar_id');

        $result = $this->scheduler_service->get_drweb_calendar_reasons($source_creds_id, $drweb_calendar_id);
        
        if (is_wp_error($result)) {
            $err_data = $result->get_error_data();
            $status = isset($err_data['status']) ? (int) $err_data['status'] : 502;
            $debug = $this->build_drweb_sent_to_proxy_debug('/Scheduler/GetDRWebCalendarReasons', $source_creds_id, $drweb_calendar_id);
            $debug['proxy_raw_response'] = isset($err_data['raw_body']) ? $err_data['raw_body'] : '';
            return $this->error_response(
                $result->get_error_message(),
                $status,
                $result->get_error_code(),
                array('debug' => $debug)
            );
        }
        
        return rest_ensure_response($result->to_array());
    }
    
    /**
     * Get DRWeb calendar active hours
     * GET /clinic-queue/v1/scheduler/drweb-calendar-active-hours
     * source_creds_id = מתשובת SourceCredentials/Save; drweb_calendar_id = מזהה היומן מ-GetAllSourceCalendars
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_drweb_calendar_active_hours($request) {
        $source_creds_id = $this->get_int_param($request, 'source_creds_id');
        $drweb_calendar_id = $this->get_string_param($request, 'drweb_calendar_id');

        $result = $this->scheduler_service->get_drweb_calendar_active_hours($source_creds_id, $drweb_calendar_id);
        
        if (is_wp_error($result)) {
            $err_data = $result->get_error_data();
            $status = isset($err_data['status']) ? (int) $err_data['status'] : 502;
            $debug = $this->build_drweb_sent_to_proxy_debug('/Scheduler/GetDRWebCalendarActiveHours', $source_creds_id, $drweb_calendar_id);
            $debug['proxy_raw_response'] = isset($err_data['raw_body']) ? $err_data['raw_body'] : '';
            return $this->error_response(
                $result->get_error_message(),
                $status,
                $result->get_error_code(),
                array('debug' => $debug)
            );
        }
        
        return rest_ensure_response($result->to_array());
    }

    /**
     * בונה אובייקט דיבוג של מה שנשלח לפרוקסי ל-DRWeb GET (Reasons / ActiveHours).
     *
     * @param string $path נתיב הפרוקסי (למשל /Scheduler/GetDRWebCalendarReasons)
     * @param int    $source_creds_id  sourceCredsID – מזהה מתשובת SourceCredentials/Save
     * @param string $drweb_calendar_id drwebCalendarID – מזהה היומן שנבחר מ-GetAllSourceCalendars
     * @return array
     */
    private function build_drweb_sent_to_proxy_debug($path, $source_creds_id, $drweb_calendar_id) {
        $base_url = $this->scheduler_service->get_endpoint_base();
        $query = 'sourceCredsID=' . intval($source_creds_id) . '&drwebCalendarID=' . urlencode($drweb_calendar_id);
        $full_url = $base_url ? $base_url . $path . '?' . $query : '(endpoint not set)';
        return array(
            'sent_to_proxy' => array(
                'method' => 'GET',
                'path' => $path,
                'url' => $full_url,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'ClinicQueue-WordPress/1.0',
                    'DoctorOnlineProxyAuthToken' => '(טוקן – לא מוצג)',
                ),
                'query' => array(
                    'sourceCredsID' => (int) $source_creds_id,
                    'drwebCalendarID' => $drweb_calendar_id,
                ),
            ),
        );
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
        
        // schedulerIDsStr: route through Scheduler Service (single entry point for free-time)
        if (!empty($schedulerIDsStr)) {
            $result = $this->scheduler_service->get_free_time_by_scheduler_ids_str(
                $schedulerIDsStr,
                $duration,
                $fromDateUTC,
                $toDateUTC
            );
            
            if (is_wp_error($result)) {
                if ($this->error_handler) {
                    return Clinic_Queue_Error_Handler::format_rest_error($result);
                }
                return $result;
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
        
        $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
        $active_hours = $this->scheduler_service->get_active_hours_for_scheduler($scheduler_id, $request->get_json_params());
        
        if (is_wp_error($active_hours)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($active_hours);
            }
            return $active_hours;
        }
        
        // Create Model
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
        
        $existing_schedule_id = $this->find_published_schedule_by_source_scheduler_id(
            $source_scheduler_id,
            $scheduler_id
        );
        if ($existing_schedule_id > 0) {
            return $this->scheduler_already_exists_response(
                $source_scheduler_id,
                $debug_data,
                array('existing_schedule_id' => $existing_schedule_id)
            );
        }

        // Create scheduler in proxy
        $result = $this->scheduler_service->create_scheduler($scheduler_model, $scheduler_id);
        
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            if (!is_array($error_data)) {
                $error_data = array();
            }
            $proxy_response = $this->proxy_response_from_wp_error($result);
            if ($proxy_response !== null) {
                $error_data['proxy_response'] = $proxy_response;
            }
            $error_data = $this->attach_debug_if_enabled($error_data, $debug_data);

            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                $error_data
            );
        }
        
        $proxy_response = $this->proxy_response_to_array($result);

        // Check if creation was successful
        if (!isset($result->code) || $result->code !== 'Success') {
            $error_msg = isset($result->error) ? $result->error : 'Failed to create scheduler';
            
            if ($this->is_duplicate_scheduler_proxy_error($error_msg)) {
                return $this->scheduler_already_exists_response(
                    $source_scheduler_id,
                    $debug_data,
                    array('proxy_response' => $proxy_response)
                );
            }

            return $this->error_response(
                $error_msg,
                500,
                'proxy_error',
                $this->attach_debug_if_enabled(
                    array('proxy_response' => $proxy_response),
                    $debug_data
                )
            );
        }
        
        // Get proxy scheduler ID from result
        $proxy_schedule_id = isset($result->result) ? intval($result->result) : null;
        
        if (!$proxy_schedule_id) {
            return $this->error_response(
                'Proxy did not return scheduler ID',
                500,
                'no_scheduler_id',
                array('proxy_response' => $proxy_response)
            );
        }
        
        // Save proxy scheduler ID to WordPress meta
        update_post_meta($scheduler_id, 'proxy_schedule_id', $proxy_schedule_id);
        update_post_meta($scheduler_id, 'source_credentials_id', $source_creds_id);
        update_post_meta($scheduler_id, 'source_scheduler_id', $source_scheduler_id);
        update_post_meta($scheduler_id, 'proxy_connected', true);
        update_post_meta($scheduler_id, 'proxy_connected_at', current_time('mysql'));
        update_post_meta($scheduler_id, 'doctor_connect_status', 'connected');

        if (!class_exists('Clinic_Queue_Doctor_Connect_Service')) {
            $doctor_connect_service_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-doctor-connect-service.php';
            if (file_exists($doctor_connect_service_file)) {
                require_once $doctor_connect_service_file;
            }
        }
        if (class_exists('Clinic_Queue_Doctor_Connect_Service')) {
            Clinic_Queue_Doctor_Connect_Service::revoke_token($scheduler_id);
        }
        
        // Create JetEngine Relations
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-jetengine-relations-service.php';
        $relations_service = Clinic_Queue_JetEngine_Relations_Service::get_instance();
        $relations_result = $relations_service->create_scheduler_relations($scheduler_id);
        
        // Update JetEngine switcher field
        update_post_meta($scheduler_id, 'doctor_online_proxy_connected', true);
        
        // עדכון כותרת + פרסום אם הפוסט לא פורסם (זרימת "שליחת בקשה לרופא": טיוטא עד חיבור גוגל)
        $current_post = get_post($scheduler_id);
        if ($current_post) {
            $current_title = $current_post->post_title;
            // הסר אם קיים בלוק "🆔 מספר |" בתחילת הכותרת (מזהה מקור ישן) כדי שיופיע רק מזהה היומן שנוצר
            $title_without_leading_id = preg_replace('/^🆔\s*\d+\s*\|\s*/u', '', $current_title);
            $new_title = '🆔 ' . $proxy_schedule_id . ' | ' . $title_without_leading_id;

            $update_payload = array(
                'ID' => $scheduler_id,
                'post_title' => $new_title,
            );
            if ($current_post->post_status !== 'publish') {
                $update_payload['post_status'] = 'publish';
            }
            wp_update_post($update_payload);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Scheduler created successfully in proxy',
            'proxy_response' => $proxy_response,
            'data' => array(
                'proxy_schedule_id' => $proxy_schedule_id,
                'wordpress_scheduler_id' => $scheduler_id,
                'source_scheduler_id' => $source_scheduler_id
            )
        ));
    }

    /**
     * עדכון שעות פעילות בפרוקסי – POST /clinic-queue/v1/scheduler/set-active-hours → Scheduler/SetActiveHours
     *
     * מיועד ליומני Google בלבד (Clinix: שעות הפעילות מנוהלות ע"י המערכת המקורית).
     * מקבל `days` בפורמט הפנימי { day_key: [{start_time,end_time}] } וממיר ל-ticks לפרוקסי.
     *
     * @param WP_REST_Request $request בקשה.
     * @return WP_REST_Response|WP_Error
     */
    public function set_active_hours($request) {
        $wp_schedule_id = absint($request->get_param('schedulerID'));
        if (empty($wp_schedule_id)) {
            return $this->error_response('schedulerID הוא חובה', 400, 'missing_params');
        }

        $days = $request->get_param('days');
        if (empty($days) || !is_array($days)) {
            return $this->error_response('days הוא חובה ומייצג ימי עבודה עם שעות', 400, 'missing_params');
        }

        $post = get_post($wp_schedule_id);
        if (!$post || $post->post_type !== 'schedules') {
            return $this->error_response('יומן לא נמצא', 404, 'invalid_scheduler');
        }

        $schedule_type = get_post_meta($wp_schedule_id, 'schedule_type', true);
        if ($schedule_type !== 'google') {
            return $this->error_response(
                'SetActiveHours מיועד ליומני Google בלבד',
                400,
                'wrong_schedule_type',
                array('schedule_type' => $schedule_type)
            );
        }

        $upstream_id = $this->get_upstream_scheduler_id($wp_schedule_id);
        if (empty($upstream_id)) {
            return $this->error_response(
                'היומן אינו מחובר לפרוקסי עדיין (proxy_schedule_id חסר)',
                400,
                'no_proxy_connection'
            );
        }

        $model = Clinic_Queue_Update_Active_Hours_Model::from_days_data($upstream_id, $days);
        $validation = $model->validate();
        if ($validation !== true) {
            return $this->error_response('שגיאת ולידציה', 400, 'validation_error', array('errors' => $validation));
        }

        $result = $this->scheduler_service->set_active_hours($model, $wp_schedule_id);

        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }

        if (!isset($result->code) || $result->code !== 'Success') {
            $error_msg = isset($result->error) ? (string) $result->error : 'Proxy SetActiveHours failed';
            return $this->error_response($error_msg, 502, 'proxy_error');
        }

        return rest_ensure_response(array(
            'success'      => true,
            'message'      => 'שעות הפעילות עודכנו בהצלחה',
            'schedulerID'  => $upstream_id,
            'active_hours' => count($model->activeHours) . ' slots',
        ));
    }

    /**
     * עדכון scheduler בפרוקסי – POST /clinic-queue/v1/scheduler/update → upstream Scheduler/Update
     *
     * @param WP_REST_Request $request בקשה (גוף JSON לפי Swagger).
     * @return WP_REST_Response|WP_Error
     */
    public function update_scheduler_in_proxy($request) {
        $json = $request->get_json_params();
        if (!is_array($json)) {
            $json = array();
        }

        foreach (array('schedulerID', 'isActive', 'maxOverlappingMeeting', 'overlappingDurationInMinutes') as $merge_key) {
            if (!array_key_exists($merge_key, $json) && $request->has_param($merge_key)) {
                $json[$merge_key] = $request->get_param($merge_key);
            }
        }

        $wp_schedule_id = $this->resolve_wp_schedule_post_id_for_request($request);
        if (empty($wp_schedule_id)) {
            return $this->error_response(
                'Scheduler not found',
                404,
                'invalid_scheduler'
            );
        }

        if (!array_key_exists('isActive', $json)) {
            return $this->error_response(
                'isActive is required',
                400,
                'missing_params'
            );
        }

        $is_active = null;
        if (is_bool($json['isActive'])) {
            $is_active = $json['isActive'];
        } else {
            $is_active = filter_var($json['isActive'], FILTER_VALIDATE_BOOLEAN);
        }

        $upstream_scheduler_id = $this->get_upstream_scheduler_id($wp_schedule_id);

        $body = array(
            'schedulerID' => $upstream_scheduler_id,
            'isActive' => $is_active,
        );

        if (array_key_exists('maxOverlappingMeeting', $json)) {
            $body['maxOverlappingMeeting'] = ($json['maxOverlappingMeeting'] === null) ? null : absint($json['maxOverlappingMeeting']);
        }

        if (array_key_exists('overlappingDurationInMinutes', $json)) {
            $body['overlappingDurationInMinutes'] = ($json['overlappingDurationInMinutes'] === null)
                ? null
                : absint($json['overlappingDurationInMinutes']);
        }

        $model = new Clinic_Queue_Update_Scheduler_Model($body);

        $validation = $model->validate();
        if ($validation !== true) {
            return $this->error_response(
                'Validation failed',
                400,
                'validation_error',
                array('errors' => $validation)
            );
        }

        $result = $this->scheduler_service->update_scheduler($model, $wp_schedule_id);

        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }

        if (!isset($result->code) || $result->code !== 'Success') {
            $error_msg = isset($result->error) ? (string) $result->error : 'Proxy update failed';
            $status = ($result->code === 'InvalidCredential' || $result->code === 'ClientError') ? 400 : 502;
            return $this->error_response(
                $error_msg,
                $status,
                'proxy_error',
                array(
                    'code' => isset($result->code) ? $result->code : null,
                )
            );
        }

        return rest_ensure_response($result->to_array());
    }

    /**
     * User-facing help text for duplicate scheduler errors.
     *
     * @return string
     */
    private function get_scheduler_already_exists_help() {
        return 'אפשרויות: (1) בחר לוח שנה אחר מהרשימה. (2) מחק את היומן הקיים מטבלת היומנים שלך. (3) פנה לתמיכה אם אינך בטוח מה לעשות.';
    }

    /**
     * Detect duplicate scheduler errors returned by the proxy API.
     *
     * @param string $error_msg Proxy error message.
     * @return bool
     */
    private function is_duplicate_scheduler_proxy_error($error_msg) {
        return stripos($error_msg, 'Duplicate entry') !== false
            && stripos($error_msg, 'UQ_Scheduler_Source') !== false;
    }

    /**
     * Build a standardized duplicate-scheduler REST error.
     *
     * @param string $source_scheduler_id Selected external calendar ID.
     * @param array  $debug_data Debug payload for developers.
     * @param array  $extra Additional error data fields.
     * @return WP_Error
     */
    private function scheduler_already_exists_response($source_scheduler_id, array $debug_data, array $extra = array()) {
        return $this->error_response(
            'לוח השנה שבחרת כבר משויך ליומן אחר במערכת.',
            409,
            'scheduler_already_exists',
            $this->attach_debug_if_enabled(
                array_merge(
                    array(
                        'source_scheduler_id' => $source_scheduler_id,
                        'error_type'          => 'duplicate_scheduler',
                        'help'                => $this->get_scheduler_already_exists_help(),
                    ),
                    $extra
                ),
                $debug_data
            )
        );
    }

    /**
     * Find a published schedule post that already uses the given source scheduler ID.
     *
     * @param string $source_scheduler_id External calendar identifier from proxy.
     * @param int    $exclude_scheduler_id Current draft schedule to exclude from the search.
     * @return int WordPress post ID or 0 if not found.
     */
    private function find_published_schedule_by_source_scheduler_id($source_scheduler_id, $exclude_scheduler_id = 0) {
        $source_scheduler_id = trim((string) $source_scheduler_id);
        if ($source_scheduler_id === '') {
            return 0;
        }

        $query_args = array(
            'post_type' => 'schedules',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => array(
                array(
                    'key' => 'source_scheduler_id',
                    'value' => $source_scheduler_id,
                    'compare' => '=',
                ),
            ),
        );

        $exclude_scheduler_id = absint($exclude_scheduler_id);
        if ($exclude_scheduler_id > 0) {
            $query_args['post__not_in'] = array($exclude_scheduler_id);
        }

        $query = new WP_Query($query_args);

        return !empty($query->posts) ? (int) $query->posts[0] : 0;
    }

    /**
     * Convert parsed proxy model to array for REST response.
     *
     * @param object|null $result Proxy response model.
     * @return array|null
     */
    private function proxy_response_to_array($result) {
        if (is_object($result) && method_exists($result, 'to_array')) {
            return $result->to_array();
        }

        return is_array($result) ? $result : null;
    }

    /**
     * Extract proxy response from WP_Error (e.g. HTTP/JSON failures).
     *
     * @param WP_Error $error Error from proxy service.
     * @return array|null
     */
    private function proxy_response_from_wp_error($error) {
        if (!is_wp_error($error)) {
            return null;
        }

        $data = $error->get_error_data();
        if (!is_array($data) || empty($data['raw_body'])) {
            return null;
        }

        $decoded = json_decode($data['raw_body'], true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return array('raw_body' => $data['raw_body']);
    }

    /**
     * Attach proxy debug payload only when WP_DEBUG is enabled.
     *
     * @param array $data Error payload.
     * @param array $debug_data Debug details for developers.
     * @return array
     */
    private function attach_debug_if_enabled(array $data, array $debug_data) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $data['debug'] = $debug_data;
        }

        return $data;
    }
}
