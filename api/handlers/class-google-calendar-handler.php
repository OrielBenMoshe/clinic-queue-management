<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-google-calendar-service.php';
require_once __DIR__ . '/../services/class-scheduler-proxy-service.php';
require_once __DIR__ . '/../services/class-source-credentials-proxy-service.php';

/**
 * Google Calendar Handler
 * מטפל בכל endpoints הקשורים ל-Google Calendar
 * 
 * Endpoints:
 * - POST /google/connect - חיבור ליומן Google Calendar
 * - GET /google/calendars - קבלת רשימת יומנים מ-Google
 * 
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Google_Calendar_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * Google Calendar Service instance
     * 
     * @var Clinic_Queue_Google_Calendar_Service
     */
    private $google_service;
    
    /**
     * Scheduler Service instance
     * 
     * @var Clinic_Queue_Scheduler_Proxy_Service
     */
    private $scheduler_service;
    
    /**
     * Source Credentials Service instance
     * 
     * @var Clinic_Queue_Source_Credentials_Proxy_Service
     */
    private $source_credentials_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize services
        if (class_exists('Clinic_Queue_Google_Calendar_Service')) {
            $this->google_service = new Clinic_Queue_Google_Calendar_Service();
        }
        $this->scheduler_service = new Clinic_Queue_Scheduler_Proxy_Service();
        $this->source_credentials_service = new Clinic_Queue_Source_Credentials_Proxy_Service();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // POST /google/connect
        register_rest_route($this->namespace, '/google/connect', array(
            'methods' => 'POST',
            'callback' => array($this, 'google_connect'),
            'permission_callback' => array($this, 'permission_callback_logged_in'),
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
        
        // GET /google/calendars
        register_rest_route($this->namespace, '/google/calendars', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_google_calendars'),
            'permission_callback' => array($this, 'permission_callback_logged_in'),
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
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function google_connect($request) {
        $code = $this->get_string_param($request, 'code');
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        
        // Validation
        if (empty($code)) {
            return $this->error_response(
                'Authorization code is required',
                400,
                'missing_code'
            );
        }
        
        if (empty($scheduler_id)) {
            return $this->error_response(
                'Scheduler ID is required',
                400,
                'missing_scheduler_id'
            );
        }
        
        // בדיקה שהפוסט קיים ושהמשתמש הנוכחי הוא הבעלים
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
                'You do not have permission to connect this scheduler',
                403,
                'permission_denied'
            );
        }
        
        // Check if Google Calendar Service is available
        if (!$this->google_service) {
            return $this->error_response(
                'Google Calendar service is not available. Please contact administrator.',
                503,
                'service_unavailable'
            );
        }
        
        // Step 1: Exchange code for tokens
        $tokens_result = $this->google_service->exchange_code_for_tokens($code);
        
        if (is_wp_error($tokens_result)) {
            $this->google_service->log_google_error($scheduler_id, $tokens_result->get_error_message());
            return $this->error_response(
                'Failed to connect to Google: ' . $tokens_result->get_error_message(),
                500,
                'token_exchange_failed'
            );
        }
        
        // Step 2: Get user info
        $user_info_result = $this->google_service->get_user_info($tokens_result['access_token']);
        
        if (is_wp_error($user_info_result)) {
            $this->google_service->log_google_error($scheduler_id, $user_info_result->get_error_message());
            return $this->error_response(
                'Failed to get user information: ' . $user_info_result->get_error_message() . ' (Token: ' . substr($tokens_result['access_token'], 0, 10) . '...)',
                500,
                'user_info_failed',
                array('debug' => array('token_length' => strlen($tokens_result['access_token'])))
            );
        }
        
        // Step 3: Prepare credentials array
        $credentials = array(
            'access_token' => $tokens_result['access_token'],
            'refresh_token' => $tokens_result['refresh_token'],
            'expires_at' => $tokens_result['expires_at'],
            'user_email' => $user_info_result['email'],
            'timezone' => 'Asia/Jerusalem'
        );
        
        // Step 4: Save credentials to scheduler meta fields
        $save_result = $this->google_service->save_google_credentials($scheduler_id, $credentials);
        
        if (!$save_result) {
            return $this->error_response(
                'Failed to save Google credentials',
                500,
                'save_failed'
            );
        }
        
        // Step 5: Save credentials to proxy and get sourceCredentialsID
        $debug_info = array();
        
        // Check API endpoint and token
        $api_endpoint = defined('CLINIC_QUEUE_API_ENDPOINT') ? CLINIC_QUEUE_API_ENDPOINT : get_option('clinic_queue_api_endpoint', null);
        $api_token = defined('CLINIC_QUEUE_API_TOKEN') ? CLINIC_QUEUE_API_TOKEN : null;
        
        $debug_info[] = 'API Endpoint: ' . ($api_endpoint ? $api_endpoint : 'NOT SET');
        $debug_info[] = 'API Token: ' . ($api_token ? substr($api_token, 0, 20) . '...' : 'NOT SET');
        
        // Get schedule_type from post meta
        $schedule_type = get_post_meta($scheduler_id, 'schedule_type', true);
        $debug_info[] = 'Schedule type from meta: ' . ($schedule_type ? $schedule_type : 'NOT SET');
        
        // Convert schedule_type to API sourceType format
        $source_type_map = array(
            'google' => 'Google',
            'clinix' => 'DBWeb'
        );
        
        if (empty($schedule_type) || !isset($source_type_map[$schedule_type])) {
            $debug_info[] = 'WARNING: schedule_type is missing or invalid, defaulting to Google';
            $schedule_type = 'google';
        }
        
        $source_type = $source_type_map[$schedule_type];
        $debug_info[] = 'Converted sourceType: ' . $source_type . ' (from schedule_type: ' . $schedule_type . ')';
        
        // Convert expires_at to ISO 8601 format
        $expires_at_iso = '';
        if (!empty($tokens_result['expires_in'])) {
            $expires_in_seconds = intval($tokens_result['expires_in']);
            $expires_timestamp = time() + $expires_in_seconds;
            $expires_at_iso = gmdate('Y-m-d\TH:i:s.000\Z', $expires_timestamp);
            $debug_info[] = 'Calculated expires_at from expires_in (' . $expires_in_seconds . ' seconds): "' . $expires_at_iso . '"';
        } elseif (!empty($tokens_result['expires_at'])) {
            try {
                $expires_datetime = new DateTime($tokens_result['expires_at'], new DateTimeZone('UTC'));
                $iso_string = $expires_datetime->format('Y-m-d\TH:i:s');
                $microseconds = intval($expires_datetime->format('u'));
                $milliseconds = str_pad(strval(intval($microseconds / 1000)), 3, '0', STR_PAD_LEFT);
                $expires_at_iso = $iso_string . '.' . $milliseconds . 'Z';
                $debug_info[] = 'Converted expires_at from "' . $tokens_result['expires_at'] . '" to ISO 8601: "' . $expires_at_iso . '"';
            } catch (Exception $e) {
                $debug_info[] = 'WARNING: Failed to convert expires_at to ISO 8601: ' . $e->getMessage();
                $expires_at_iso = gmdate('Y-m-d\TH:i:s.000\Z', time() + 3600);
                $debug_info[] = 'Using fallback expires_at: ' . $expires_at_iso;
            }
        } else {
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
        
        $source_creds_result = $this->source_credentials_service->save_source_credentials($credentials_data, $scheduler_id);
        
        $debug_info[] = 'Source credentials save attempt completed';
        $debug_info[] = 'Result type: ' . gettype($source_creds_result);
        
        // Extract raw body for debugging
        $raw_proxy_response = null;
        if (is_array($source_creds_result) && isset($source_creds_result['raw_body'])) {
            $raw_proxy_response = $source_creds_result['raw_body'];
            $source_creds_result = $source_creds_result['model'];
            $debug_info[] = '=== תשובה גולמית מהפרוקסי API ===';
            $debug_info[] = $raw_proxy_response;
        }
        
        $source_creds_id = null;
        
        if (is_wp_error($source_creds_result)) {
            $error_message = $source_creds_result->get_error_message();
            $error_data = $source_creds_result->get_error_data();
            $debug_info[] = 'WP_Error occurred: ' . $error_message;
            $debug_info[] = 'Error data: ' . json_encode($error_data, JSON_PRETTY_PRINT);
            
            if (isset($error_data['raw_body'])) {
                $raw_proxy_response = $error_data['raw_body'];
                $debug_info[] = '=== תשובה גולמית מהפרוקסי API (מתוך Error) ===';
                $debug_info[] = $raw_proxy_response;
            }
        } else {
            if (is_object($source_creds_result)) {
                $debug_info[] = 'Result class: ' . get_class($source_creds_result);
                
                if (isset($source_creds_result->code)) {
                    $debug_info[] = 'Response code: ' . $source_creds_result->code;
                    
                    if ($source_creds_result->code === 'Success') {
                        $result_value = $source_creds_result->result;
                        $debug_info[] = 'Response result value: ' . json_encode($result_value, JSON_PRETTY_PRINT);
                        $source_creds_id = isset($result_value) ? intval($result_value) : null;
                        
                        if ($source_creds_id) {
                            update_post_meta($scheduler_id, 'source_credentials_id', $source_creds_id);
                            $debug_info[] = 'Source credentials saved to proxy. ID: ' . $source_creds_id;
                        } else {
                            $debug_info[] = 'WARNING: Response code is Success but result is null or invalid';
                        }
                    } else {
                        $error_msg = isset($source_creds_result->error) ? $source_creds_result->error : 'Unknown error';
                        $debug_info[] = 'Proxy returned error code: ' . $source_creds_result->code;
                        $debug_info[] = 'Error message: ' . $error_msg;
                    }
                } else {
                    $debug_info[] = 'WARNING: Response object does not have code property';
                }
            } else {
                $debug_info[] = 'WARNING: Result is not an object. Type: ' . gettype($source_creds_result);
                $debug_info[] = 'Result value: ' . json_encode($source_creds_result, JSON_PRETTY_PRINT);
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
            'debug' => $debug_info,
            'proxy_raw_response' => $raw_proxy_response
        ));
    }
    
    /**
     * Get Google calendars list
     * GET /clinic-queue/v1/google/calendars
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_google_calendars($request) {
        $scheduler_id = $this->get_int_param($request, 'scheduler_id');
        $source_creds_id = $this->get_int_param($request, 'source_creds_id');
        
        // Validation
        if (empty($scheduler_id) || empty($source_creds_id)) {
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
        
        // Get calendars from proxy (auth = site token; no scheduler post yet)
        $result = $this->scheduler_service->get_all_source_calendars($source_creds_id);
        
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
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
