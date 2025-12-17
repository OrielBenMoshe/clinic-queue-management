<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-base-service.php';

/**
 * Settings Admin Page
 * Manages plugin settings including API token
 */
class Clinic_Queue_Settings_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Handle form submission manually (simpler than Settings API)
        add_action('admin_init', array($this, 'handle_form_submission'));
    }
    
    /**
     * Handle form submission manually
     */
    public function handle_form_submission() {
        // Only process on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'clinic-queue-settings') {
            return;
        }
        
        // Check if form was submitted
        if (isset($_POST['clinic_queue_save_settings'])) {
            // Verify nonce
            if (!isset($_POST['clinic_queue_settings_nonce']) || 
                !wp_verify_nonce($_POST['clinic_queue_settings_nonce'], 'clinic_queue_save_settings')) {
                wp_die('Security check failed');
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die('You do not have permission to save settings');
            }
            
            // Save API token
            if (isset($_POST['clinic_queue_api_token'])) {
                $token = sanitize_text_field($_POST['clinic_queue_api_token']);
                
                if (!empty($token)) {
                    // Check if token contains dots (masked token)
                    if (strpos($token, '•') === false) {
                        // It's a new/changed token - save it encrypted
                        $encrypted = $this->encrypt_token($token);
                        update_option('clinic_queue_api_token_encrypted', $encrypted);
                    }
                    // If it contains dots, it's the masked version - don't change anything
                } else {
                    // Empty field - delete the token
                    delete_option('clinic_queue_api_token_encrypted');
                }
            }
            
            // Save API endpoint
            if (isset($_POST['clinic_queue_api_endpoint'])) {
                $endpoint = esc_url_raw($_POST['clinic_queue_api_endpoint']);
                update_option('clinic_queue_api_endpoint', $endpoint);
            }
            
            // Redirect back with success message
            wp_redirect(add_query_arg(
                array(
                    'page' => 'clinic-queue-settings',
                    'settings-updated' => 'true'
                ),
                admin_url('admin.php')
            ));
            exit;
        }
    }
    
    /**
     * Encrypt token using WordPress salts
     */
    private function encrypt_token($token) {
        if (!function_exists('openssl_encrypt')) {
            // Fallback: simple obfuscation
            return base64_encode($token);
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt token using WordPress salts
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
     */
    private function get_encryption_key() {
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-in-wp-config';
        return hash('sha256', $salt . get_option('siteurl', ''), true);
    }
    
    /**
     * Render token field
     */
    public function render_token_field() {
        // Get current token
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        $current_token = '';
        $display_token = '';
        
        if ($encrypted_token) {
            $current_token = $this->decrypt_token($encrypted_token);
            // Display first 12 characters + dots for the rest
            if (strlen($current_token) > 12) {
                $display_token = substr($current_token, 0, 12) . str_repeat('•', strlen($current_token) - 12);
            } else {
                $display_token = $current_token;
            }
        }
        
        // Render the input field with toggle button
        echo '<div style="display: flex; gap: 10px; align-items: flex-start;">';
        echo '<input type="text" id="clinic_queue_api_token" name="clinic_queue_api_token" value="' . esc_attr($display_token) . '" class="regular-text" placeholder="הזן את טוקן ה-API כאן" style="direction: ltr; text-align: left;" />';
        
        if (!empty($current_token)) {
            echo '<input type="hidden" id="clinic_queue_api_token_full" value="' . esc_attr($current_token) . '" />';
            echo '<button type="button" class="button" id="clinic_queue_toggle_token" onclick="clinicQueueToggleToken()">הצג מלא</button>';
            echo '<button type="button" class="button" id="clinic_queue_edit_token" onclick="clinicQueueEditToken()" style="display:none;">ערוך</button>';
        }
        
        echo '</div>';
        
        echo '<p class="description">טוקן האימות עבור ה-API. הטוקן יישמר מוצפן במסד הנתונים.</p>';
        if (!empty($current_token)) {
            echo '<p class="description" style="color: green;">✓ טוקן מוגדר ונשמר מוצפן (מוצגים 12 תווים ראשונים)</p>';
        } else {
            echo '<p class="description" style="color: orange;">⚠ אין טוקן מוגדר - הפלאגין ישתמש ב-scheduler_id (legacy)</p>';
        }
        
        // Add inline JavaScript for toggle functionality
        ?>
        <script>
        var clinicQueueTokenMasked = <?php echo json_encode($display_token); ?>;
        var clinicQueueTokenShown = false;
        
        function clinicQueueToggleToken() {
            var input = document.getElementById('clinic_queue_api_token');
            var fullToken = document.getElementById('clinic_queue_api_token_full');
            var toggleBtn = document.getElementById('clinic_queue_toggle_token');
            var editBtn = document.getElementById('clinic_queue_edit_token');
            
            if (!clinicQueueTokenShown) {
                // Show full token
                input.value = fullToken.value;
                input.readOnly = true;
                input.style.backgroundColor = '#f0f0f0';
                toggleBtn.textContent = 'הסתר';
                editBtn.style.display = 'inline-block';
                clinicQueueTokenShown = true;
            } else {
                // Hide token (mask it)
                input.value = clinicQueueTokenMasked;
                input.readOnly = true;
                input.style.backgroundColor = '#f0f0f0';
                toggleBtn.textContent = 'הצג מלא';
                editBtn.style.display = 'none';
                clinicQueueTokenShown = false;
            }
        }
        
        function clinicQueueEditToken() {
            var input = document.getElementById('clinic_queue_api_token');
            var fullToken = document.getElementById('clinic_queue_api_token_full');
            
            input.value = fullToken.value;
            input.readOnly = false;
            input.style.backgroundColor = '';
            input.focus();
            input.select();
        }
        
        // Initialize as readonly with masked value
        document.addEventListener('DOMContentLoaded', function() {
            var input = document.getElementById('clinic_queue_api_token');
            if (input && clinicQueueTokenMasked) {
                input.readOnly = true;
                input.style.backgroundColor = '#f0f0f0';
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render endpoint field
     */
    public function render_endpoint_field() {
        $api_endpoint = get_option('clinic_queue_api_endpoint', 'https://do-proxy-staging.doctor-clinix.com');
        
        // Render the input field
        echo '<input type="url" id="clinic_queue_api_endpoint" name="clinic_queue_api_endpoint" value="' . esc_attr($api_endpoint) . '" class="regular-text" placeholder="https://do-proxy-staging.doctor-clinix.com" />';
        echo '<p class="description">כתובת ה-API הבסיסית לשירות DoctorOnline Proxy.</p>';
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        // Check if settings were updated
        $updated = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
        
        // Get current settings
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        $current_token = '';
        if ($encrypted_token) {
            $current_token = $this->decrypt_token($encrypted_token);
        }
        
        // Check if token is set via constant (highest priority)
        $token_from_constant = defined('CLINIC_QUEUE_API_TOKEN') ? CLINIC_QUEUE_API_TOKEN : null;
        $has_constant_token = !empty($token_from_constant);
        
        $api_endpoint = get_option('clinic_queue_api_endpoint', 'https://do-proxy-staging.doctor-clinix.com');
        $endpoint_from_constant = defined('CLINIC_QUEUE_API_ENDPOINT') ? CLINIC_QUEUE_API_ENDPOINT : null;
        $has_constant_endpoint = !empty($endpoint_from_constant);
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/settings-html.php';
    }
}

