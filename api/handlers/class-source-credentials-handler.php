<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';
require_once __DIR__ . '/../services/class-source-credentials-proxy-service.php';

/**
 * Source Credentials Handler
 * מטפל בכל endpoints הקשורים לפרטי התחברות למקורות
 *
 * Endpoints:
 * - POST /source-credentials/save - שמירת פרטי התחברות (גוגל וכו')
 * - POST /source-credentials/save-clinix - שמירת טוקן קליניקס בפרוקסי (POST /SourceCredentials/Save)
 *
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Source_Credentials_Handler extends Clinic_Queue_Base_Handler {

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

        // Initialize service
        $this->source_credentials_service = new Clinic_Queue_Source_Credentials_Proxy_Service();
    }

    /**
     * Register routes
     * רישום נקודות קצה API
     *
     * @return void
     */
    public function register_routes() {
        // POST /source-credentials/save
        register_rest_route($this->namespace, '/source-credentials/save', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_source_credentials'),
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

        // POST /source-credentials/save-clinix – שמירת טוקן קליניקס בפרוקסי (DRWeb)
        register_rest_route($this->namespace, '/source-credentials/save-clinix', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_clinix_credentials'),
            'permission_callback' => array($this, 'permission_callback_logged_in'),
            'args' => array(
                'token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Clinix API token (גם מ-body)',
                ),
            )
        ));
    }
    
    /**
     * Save source credentials
     * POST /clinic-queue/v1/source-credentials/save
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function save_source_credentials($request) {
        // Get body data
        $body = $request->get_json_params();
        
        if (empty($body)) {
            return $this->error_response(
                'בקשה לא תקינה',
                400,
                'invalid_request'
            );
        }
        
        // Get scheduler_id from request or body
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
        $result = $this->source_credentials_service->save_source_credentials($body, $scheduler_id);
        
        // Handle error
        if (is_wp_error($result)) {
            if ($this->error_handler) {
                return Clinic_Queue_Error_Handler::format_rest_error($result);
            }
            return $result;
        }
        
        // Result is array with 'model' and 'raw_body'
        $model = isset($result['model']) ? $result['model'] : $result;
        
        // Convert model to array
        if (is_object($model) && method_exists($model, 'to_array')) {
            return rest_ensure_response($model->to_array());
        }
        
        return rest_ensure_response($model);
    }

    /**
     * Save Clinix token to proxy (POST /SourceCredentials/Save with sourceType DRWeb).
     * POST /clinic-queue/v1/source-credentials/save-clinix
     *
     * הפרונט שולח את הטוקן, הבאק פונה לפרוקסי עם הטוקן ומקבל source_credentials_id.
     *
     * @param WP_REST_Request $request Request object (body or param: token)
     * @return WP_REST_Response|WP_Error
     */
    public function save_clinix_credentials($request) {
        $body = $request->get_json_params();
        $token = isset($body['token']) ? sanitize_text_field($body['token']) : $this->get_string_param($request, 'token');

        if (empty($token)) {
            return $this->error_response(
                'טוקן קליניקס הוא חובה',
                400,
                'missing_token'
            );
        }

        // Body לפי מפרט הפרוקסי SourceCredentialsModel (DRWeb)
        $expires_in_3h = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+3 hours'));
        $credentials_data = array(
            'sourceType' => 'DRWeb',
            'accessToken' => $token,
            'accessTokenExpiresIn' => $expires_in_3h,
            'refreshToken' => '1',
        );

        // scheduler_id=0 – הטוקן ב-header יהיה טוקן האתר (get_auth_token(0))
        $result = $this->source_credentials_service->save_source_credentials($credentials_data, 0);

        if (is_wp_error($result)) {
            $err_data = $result->get_error_data();
            $status = isset($err_data['status']) ? (int) $err_data['status'] : 502;
            $debug = $this->build_sent_to_proxy_debug($credentials_data);
            $debug['proxy_raw_response'] = isset($err_data['raw_body']) ? $err_data['raw_body'] : '';
            return $this->error_response(
                $result->get_error_message(),
                $status,
                $result->get_error_code(),
                array('debug' => $debug)
            );
        }

        $model = isset($result['model']) ? $result['model'] : $result;
        $source_credentials_id = null;
        if (is_object($model) && isset($model->result)) {
            $source_credentials_id = intval($model->result);
        } elseif (is_array($model) && isset($model['result'])) {
            $source_credentials_id = intval($model['result']);
        }

        if (!$source_credentials_id) {
            $raw_body = isset($result['raw_body']) ? $result['raw_body'] : '';
            $debug = $this->build_sent_to_proxy_debug($credentials_data);
            $debug['proxy_raw_response'] = $raw_body;
            $debug['request_summary'] = array(
                'sourceType' => isset($credentials_data['sourceType']) ? $credentials_data['sourceType'] : '',
                'token_length' => isset($credentials_data['accessToken']) ? strlen($credentials_data['accessToken']) : 0,
                'has_refresh_token' => !empty($credentials_data['refreshToken']),
            );
            $debug['parsed_model'] = is_object($model) ? (array) $model : $model;
            return $this->error_response(
                'הפרוקסי לא החזיר מזהה credentials',
                502,
                'invalid_proxy_response',
                array('debug' => $debug)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'source_credentials_id' => $source_credentials_id,
        ));
    }

    /**
     * בונה אובייקט דיבוג של מה שנשלח לפרוקסי (ללא חשיפת טוקנים).
     *
     * @param array $credentials_data הנתונים שנשלחים לפרוקסי
     * @return array
     */
    private function build_sent_to_proxy_debug($credentials_data) {
        $base_url = $this->source_credentials_service->get_endpoint_base();
        $path = '/SourceCredentials/Save';
        return array(
            'sent_to_proxy' => array(
                'method' => 'POST',
                'path' => $path,
                'url' => $base_url ? $base_url . $path : '(endpoint not set)',
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'ClinicQueue-WordPress/1.0',
                    'DoctorOnlineProxyAuthToken' => '(טוקן האתר – לא מוצג)',
                ),
                'body' => array(
                    'sourceType' => isset($credentials_data['sourceType']) ? $credentials_data['sourceType'] : '',
                    'accessToken' => '(אורך ' . (isset($credentials_data['accessToken']) ? strlen($credentials_data['accessToken']) : 0) . ' תווים)',
                    'accessTokenExpiresIn' => isset($credentials_data['accessTokenExpiresIn']) ? $credentials_data['accessTokenExpiresIn'] : '',
                    'refreshToken' => isset($credentials_data['refreshToken']) ? $credentials_data['refreshToken'] : '',
                ),
            ),
        );
    }
}
