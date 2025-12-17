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
        // Priority: constant > option > filter
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
     * Priority:
     * 1. Constant CLINIC_QUEUE_API_TOKEN (from wp-config.php - most secure)
     * 2. WordPress option (encrypted)
     * 3. Filter (for programmatic override)
     * 4. Fallback to scheduler_id (legacy behavior)
     * 
     * @param int|null $scheduler_id Fallback scheduler ID
     * @return string|null Token or scheduler_id as fallback
     */
    protected function get_auth_token($scheduler_id = null) {
        // Priority 1: Constant from wp-config.php (most secure - not in database)
        if (defined('CLINIC_QUEUE_API_TOKEN') && !empty(CLINIC_QUEUE_API_TOKEN)) {
            return CLINIC_QUEUE_API_TOKEN;
        }
        
        // Priority 2: WordPress option (encrypted)
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        if ($encrypted_token) {
            // Decrypt using WordPress salts
            $token = $this->decrypt_token($encrypted_token);
            if ($token) {
                return $token;
            }
        }
        
        // Priority 3: Filter (for programmatic override)
        $token = apply_filters('clinic_queue_api_token', null, $scheduler_id);
        if ($token) {
            return $token;
        }
        
        // Priority 4: Fallback to scheduler_id (legacy behavior)
        return $scheduler_id ? (string)$scheduler_id : null;
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
            $args['body'] = json_encode($data);
        }
        
        if ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $response = wp_remote_post($url, $args);
        }
        
        if (is_wp_error($response)) {
            error_log('[Clinic Queue API] Error: ' . $response->get_error_message());
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('[Clinic Queue API] HTTP Error: ' . $response_code . ' - Response: ' . $body);
            return new WP_Error('http_error', 'שגיאת HTTP: ' . $response_code, array('status' => $response_code));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            error_log('[Clinic Queue API] Invalid JSON response: ' . $body);
            return new WP_Error('invalid_json', 'תגובה לא תקינה מ-API');
        }
        
        return $data;
    }
    
    /**
     * Handle API response
     */
    protected function handle_response($response_data, $response_dto_class = null) {
        if (is_wp_error($response_data)) {
            return $response_data;
        }
        
        // Create response DTO
        if ($response_dto_class && class_exists($response_dto_class)) {
            $response_dto = $response_dto_class::from_array($response_data);
            return $response_dto;
        }
        
        return $response_data;
    }
}

