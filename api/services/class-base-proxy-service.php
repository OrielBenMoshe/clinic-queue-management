<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Proxy Service – פניות ל-Proxy API (DoctorOnline)
 * כל השירותים שפונים לפרוקסי יורשים מהמחלקה הזו (make_request, make_request_with_token).
 */
abstract class Clinic_Queue_Base_Proxy_Service {
    
    protected $api_endpoint;
    protected $api_manager;
    
    public function __construct() {
        // Priority: hardcoded constant > option > filter
        // ⚠️ TEMPORARY: Using hardcoded constant for development
        if (defined('CLINIC_QUEUE_API_ENDPOINT') && !empty(CLINIC_QUEUE_API_ENDPOINT)) {
            $this->api_endpoint = CLINIC_QUEUE_API_ENDPOINT;
        } else {
            $this->api_endpoint = get_option('clinic_queue_api_endpoint', null);
            if (empty($this->api_endpoint)) {
                $this->api_endpoint = apply_filters('clinic_queue_api_endpoint', null);
            }
        }
        
        $this->api_manager = Clinic_Queue_API_Manager::get_instance();
    }

    /**
     * Base URL of the proxy API (for debug only, no trailing slash).
     *
     * @return string|null Proxy base URL or null if not set.
     */
    public function get_endpoint_base() {
        return $this->api_endpoint ? rtrim($this->api_endpoint, '/') : null;
    }
    
    /**
     * Get API authentication token
     * 
     * ⚠️ TEMPORARY: Using hardcoded constant for development
     * TODO: Replace with settings page implementation after core functionality is working
     * 
     * Priority: hardcoded constant > WordPress option (encrypted)
     * 
     * @param int|null $scheduler_id Not used anymore, kept for backward compatibility
     * @return string|null Token or null if not found
     */
    protected function get_auth_token($scheduler_id = null) {
        // Priority 1: Hardcoded constant (TEMPORARY - for development)
        if (defined('CLINIC_QUEUE_API_TOKEN') && !empty(CLINIC_QUEUE_API_TOKEN) && CLINIC_QUEUE_API_TOKEN !== 'YOUR_API_TOKEN_HERE') {
            return CLINIC_QUEUE_API_TOKEN;
        }
        
        // Priority 2: Get token from WordPress option (encrypted - set via admin settings)
        // This will be used in the future when settings page is implemented
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        if ($encrypted_token) {
            // Decrypt using WordPress salts
            $token = $this->decrypt_token($encrypted_token);
            if ($token) {
                return $token;
            }
        }
        
        // Token not found - return null
        return null;
    }
    
    /**
     * Encrypt token using WordPress salts
     * 
     * @param string $token Plain token
     * @return string Encrypted token
     */
    private function encrypt_token($token) {
        if (!function_exists('openssl_encrypt')) {
            // Fallback: simple obfuscation (not secure, but better than plain text)
            return base64_encode($token);
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt token using WordPress salts
     * 
     * @param string $encrypted_token Encrypted token
     * @return string|false Plain token or false on failure
     */
    private function decrypt_token($encrypted_token) {
        if (!function_exists('openssl_decrypt')) {
            // Fallback: simple deobfuscation
            return base64_decode($encrypted_token);
        }
        
        $data = base64_decode($encrypted_token);
        if ($data === false) {
            return false;
        }
        
        $key = $this->get_encryption_key();
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key from WordPress salts
     * 
     * @return string Encryption key
     */
    private function get_encryption_key() {
        // Use WordPress salts for encryption key
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-in-wp-config';
        return hash('sha256', $salt . get_option('siteurl', ''), true);
    }
    
    /**
     * Check if response body looks like HTML (e.g. error page instead of JSON).
     *
     * @param string $body Raw response body.
     * @return bool True if body looks like HTML.
     */
    protected function response_is_html($body) {
        $trimmed = ltrim($body);
        return strpos($trimmed, '<') === 0 || stripos($trimmed, '<!doctype') === 0;
    }

    /**
     * Build headers for proxy request (per Swagger: Accept + DoctorOnlineProxyAuthToken; Content-Type only for POST).
     *
     * @param string $method GET or POST.
     * @param string $auth_token Token or empty.
     * @return array Headers array.
     */
    protected function build_proxy_headers($method, $auth_token) {
        $headers = array(
            'Accept' => 'application/json',
            'User-Agent' => 'ClinicQueue-WordPress/1.0',
        );
        if (!empty($auth_token)) {
            $headers['DoctorOnlineProxyAuthToken'] = (string) $auth_token;
        }
        if ($method !== 'GET') {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    /**
     * Make API request
     */
    protected function make_request($method, $endpoint, $data = null, $scheduler_id = null) {
        if (!$this->api_endpoint) {
            return new WP_Error('no_endpoint', 'API endpoint לא מוגדר');
        }

        $url = rtrim($this->api_endpoint, '/') . $endpoint;
        $auth_token = $this->get_auth_token($scheduler_id);
        $headers = $this->build_proxy_headers($method, $auth_token);

        $args = array(
            'timeout' => 30,
            'headers' => $headers,
        );

        if ($data !== null) {
            $args['body'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        if ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $response = wp_remote_post($url, $args);
        }

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $data = json_decode($body, true);
            $msg = (is_array($data) && isset($data['error'])) ? $data['error'] : 'שגיאת HTTP: ' . $response_code;
            return new WP_Error('http_error', $msg, array('status' => $response_code, 'raw_body' => $body));
        }

        if ($this->response_is_html($body)) {
            return new WP_Error('invalid_json', 'הפרוקסי החזיר HTML במקום JSON – ייתכן שכתובת ה-API או הטוקן שגויים', array('raw_body' => $body));
        }

        $data = json_decode($body, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'תגובה לא תקינה מ-API', array('raw_body' => $body));
        }

        return $data;
    }

    /**
     * Make API request using an explicit auth token (e.g. Clinix user token).
     * Use this when the token is provided by the client rather than from site settings.
     *
     * @param string     $method     HTTP method (GET, POST).
     * @param string     $endpoint   API endpoint path with query string if needed.
     * @param array|null $data       Request body data for POST, or null for GET.
     * @param string     $auth_token Token to send in DoctorOnlineProxyAuthToken header.
     * @return array|WP_Error Decoded response body or WP_Error on failure.
     */
    protected function make_request_with_token($method, $endpoint, $data, $auth_token) {
        if (!$this->api_endpoint) {
            return new WP_Error('no_endpoint', 'API endpoint לא מוגדר');
        }

        if (empty($auth_token) || !is_string($auth_token)) {
            return new WP_Error('missing_token', 'נדרש טוקן אימות');
        }

        $url = rtrim($this->api_endpoint, '/') . $endpoint;
        $headers = $this->build_proxy_headers($method, $auth_token);

        $args = array(
            'timeout' => 30,
            'headers' => $headers,
        );

        if ($data !== null) {
            $args['body'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        if ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $response = wp_remote_post($url, $args);
        }

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $decoded = json_decode($body, true);
            $msg = (is_array($decoded) && isset($decoded['error'])) ? $decoded['error'] : 'שגיאת HTTP: ' . $response_code;
            return new WP_Error('http_error', $msg, array('status' => $response_code, 'raw_body' => $body));
        }

        if ($this->response_is_html($body)) {
            return new WP_Error('invalid_json', 'הפרוקסי החזיר HTML במקום JSON – ייתכן שכתובת ה-API או הטוקן שגויים', array('raw_body' => $body));
        }

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'תגובה לא תקינה מ-API', array('raw_body' => $body));
        }

        return $decoded;
    }
    
    /**
     * Make API request and return both parsed data and raw body.
     * Used for POST endpoints where raw response is needed (e.g. SourceCredentials/Save).
     *
     * @param string     $method       HTTP method (GET, POST).
     * @param string     $endpoint     API endpoint path.
     * @param array|null $data         Request body for POST.
     * @param int|null   $scheduler_id Scheduler ID for authentication.
     * @return array|WP_Error Array with 'data' and 'raw_body', or WP_Error on failure.
     */
    protected function make_request_with_raw_body($method, $endpoint, $data = null, $scheduler_id = null) {
        if (!$this->api_endpoint) {
            return new WP_Error('no_endpoint', 'API endpoint לא מוגדר');
        }

        $url = rtrim($this->api_endpoint, '/') . $endpoint;
        $auth_token = $this->get_auth_token($scheduler_id);
        $headers = $this->build_proxy_headers($method, $auth_token);

        $args = array(
            'timeout' => 30,
            'headers' => $headers,
        );

        if ($data !== null) {
            $args['body'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        if ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $response = wp_remote_post($url, $args);
        }

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $decoded = json_decode($body, true);
            $msg = (is_array($decoded) && isset($decoded['error'])) ? $decoded['error'] : 'שגיאת HTTP: ' . $response_code;
            return new WP_Error('http_error', $msg, array('status' => $response_code, 'raw_body' => $body));
        }

        if ($this->response_is_html($body)) {
            return new WP_Error('invalid_json', 'הפרוקסי החזיר HTML במקום JSON', array('raw_body' => $body));
        }

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'תגובה לא תקינה מ-API', array('raw_body' => $body));
        }

        return array(
            'data' => $decoded,
            'raw_body' => $body,
        );
    }
    
    /**
     * Handle API response
     */
    protected function handle_response($response_data, $response_model_class = null) {
        if (is_wp_error($response_data)) {
            return $response_data;
        }
        
        // Create response Model
        if ($response_model_class && class_exists($response_model_class)) {
            $response_model = $response_model_class::from_array($response_data);
            return $response_model;
        }
        
        return $response_data;
    }
}

