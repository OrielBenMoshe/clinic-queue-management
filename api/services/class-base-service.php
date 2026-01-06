<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Service Class
 * כל השירותים יורשים מהקלאס הזה
 */
abstract class Clinic_Queue_Base_Service {
    
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
     * Make API request
     */
    protected function make_request($method, $endpoint, $data = null, $scheduler_id = null) {
        if (!$this->api_endpoint) {
            return new WP_Error('no_endpoint', 'API endpoint לא מוגדר');
        }
        
        $url = rtrim($this->api_endpoint, '/') . $endpoint;
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        );
        
        // Get authentication token (with fallback to scheduler_id)
        $auth_token = $this->get_auth_token($scheduler_id);
        if ($auth_token) {
            $headers['DoctorOnlineProxyAuthToken'] = (string)$auth_token;
        }
        
        $args = array(
            'timeout' => 30,
            'headers' => $headers,
        );
        
        if ($data !== null) {
            // Use JSON_UNESCAPED_UNICODE for proper Hebrew support
            // JSON_NUMERIC_CHECK removed - we send HH:mm:ss strings now, not ticks
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
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            return new WP_Error('http_error', 'שגיאת HTTP: ' . $response_code, array('status' => $response_code));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('invalid_json', 'תגובה לא תקינה מ-API');
        }
        
        return $data;
    }
    
    /**
     * Make API request and return both parsed data and raw body
     * Useful for debugging - returns raw response body
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @param int|null $scheduler_id Scheduler ID for authentication
     * @return array|WP_Error Returns array with 'data' and 'raw_body', or WP_Error on failure
     */
    protected function make_request_with_raw_body($method, $endpoint, $data = null, $scheduler_id = null) {
        if (!$this->api_endpoint) {
            return new WP_Error('no_endpoint', 'API endpoint לא מוגדר');
        }
        
        $url = rtrim($this->api_endpoint, '/') . $endpoint;
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        );
        
        // Get authentication token (with fallback to scheduler_id)
        $auth_token = $this->get_auth_token($scheduler_id);
        if ($auth_token) {
            $headers['DoctorOnlineProxyAuthToken'] = (string)$auth_token;
        }
        
        $args = array(
            'timeout' => 30,
            'headers' => $headers,
        );
        
        if ($data !== null) {
            // Use JSON_UNESCAPED_UNICODE for proper Hebrew support
            // JSON_NUMERIC_CHECK removed - we send HH:mm:ss strings now, not ticks
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
            // Return error with raw body for debugging
            return new WP_Error('http_error', 'שגיאת HTTP: ' . $response_code, array('status' => $response_code, 'raw_body' => $body));
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('invalid_json', 'תגובה לא תקינה מ-API', array('raw_body' => $body));
        }
        
        return array(
            'data' => $data,
            'raw_body' => $body
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

