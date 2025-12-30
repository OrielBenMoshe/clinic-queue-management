<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/services/class-encryption-service.php';

/**
 * Settings Handler
 * Handles all business logic for settings page
 * 
 * @package ClinicQueue
 * @subpackage Admin\Handlers
 */
class Clinic_Queue_Settings_Handler {
    
    private static $instance = null;
    private $encryption_service;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_Settings_Handler
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->encryption_service = Clinic_Queue_Encryption_Service::get_instance();
        add_action('admin_init', array($this, 'handle_form_submission'));
    }
    
    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        // Only process on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'clinic-queue-settings') {
            return;
        }
        
        // Check if form was submitted
        if (!isset($_POST['clinic_queue_save_settings'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['clinic_queue_settings_nonce']) || 
            !wp_verify_nonce($_POST['clinic_queue_settings_nonce'], 'clinic_queue_save_settings')) {
            wp_die(__('Security check failed', 'clinic-queue'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to save settings', 'clinic-queue'));
        }
        
        // Process settings
        $this->process_settings();
        
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
    
    /**
     * Process and save settings
     */
    private function process_settings() {
        // Check if user wants to delete the token
        $delete_token = isset($_POST['clinic_delete_token_flag']) && $_POST['clinic_delete_token_flag'] === '1';
        
        if ($delete_token) {
            $this->delete_token();
        } else {
            $this->save_token();
        }
        
        // Save API endpoint
        $this->save_endpoint();
    }
    
    /**
     * Delete API token
     */
    private function delete_token() {
        delete_option('clinic_queue_api_token_encrypted');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ClinicQueue Settings] Token deleted');
        }
    }
    
    /**
     * Save API token
     */
    private function save_token() {
        if (!isset($_POST['clinic_queue_api_token'])) {
            return;
        }
        
        $token = sanitize_text_field($_POST['clinic_queue_api_token']);
        
        // If token is empty, keep existing one (don't delete)
        if (empty($token)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ClinicQueue Settings] Empty token provided, keeping existing');
            }
            return;
        }
        
        // Encrypt and save new token
        $encrypted = $this->encryption_service->encrypt_token($token);
        $result = update_option('clinic_queue_api_token_encrypted', $encrypted);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ClinicQueue Settings] Token saved: ' . ($result ? 'success' : 'failed'));
        }
    }
    
    /**
     * Save API endpoint
     */
    private function save_endpoint() {
        if (!isset($_POST['clinic_queue_api_endpoint'])) {
            return;
        }
        
        $endpoint = esc_url_raw($_POST['clinic_queue_api_endpoint']);
        
        if (empty($endpoint)) {
            return;
        }
        
        $result = update_option('clinic_queue_api_endpoint', $endpoint);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ClinicQueue Settings] Endpoint saved: ' . ($result ? 'success' : 'failed'));
        }
    }
    
    /**
     * Enqueue settings page assets
     * Called directly from render_page() (not via hook)
     */
    public function enqueue_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'clinic-queue-settings',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/css/settings.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'clinic-queue-settings',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/js/settings.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('clinic-queue-settings', 'clinicQueueSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_save_settings'),
            'strings' => array(
                'saving' => __('שומר...', 'clinic-queue'),
                'saved' => __('נשמר!', 'clinic-queue'),
                'error' => __('שגיאה בשמירה', 'clinic-queue'),
                'confirmDelete' => __('האם אתה בטוח שברצונך למחוק את הטוקן?', 'clinic-queue')
            )
        ));
    }
    
    /**
     * Get current settings data for rendering
     * 
     * @return array Settings data
     */
    public function get_settings_data() {
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        $has_token = !empty($encrypted_token);
        
        $current_token = '';
        if ($has_token) {
            $current_token = $this->encryption_service->decrypt_token($encrypted_token);
        }
        
        // Check if token is set via constant (highest priority)
        $token_from_constant = defined('CLINIC_QUEUE_API_TOKEN') ? CLINIC_QUEUE_API_TOKEN : null;
        $has_constant_token = !empty($token_from_constant);
        
        $api_endpoint = get_option('clinic_queue_api_endpoint', 'https://do-proxy-staging.doctor-clinix.com');
        $endpoint_from_constant = defined('CLINIC_QUEUE_API_ENDPOINT') ? CLINIC_QUEUE_API_ENDPOINT : null;
        $has_constant_endpoint = !empty($endpoint_from_constant);
        
        return array(
            'updated' => isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true',
            'has_token' => $has_token,
            'current_token' => $current_token,
            'has_constant_token' => $has_constant_token,
            'token_from_constant' => $token_from_constant,
            'api_endpoint' => $api_endpoint,
            'has_constant_endpoint' => $has_constant_endpoint,
            'endpoint_from_constant' => $endpoint_from_constant,
            'encryption_available' => $this->encryption_service->is_encryption_available()
        );
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        // Enqueue assets (called directly, not via hook)
        $this->enqueue_assets();
        
        $data = $this->get_settings_data();
        
        // Extract variables for template
        extract($data);
        
        // Make handler methods available to template
        $handler = $this;
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/settings-html.php';
    }
    
    /**
     * Render token field
     * Called from template
     */
    public function render_token_field() {
        $has_token = !empty(get_option('clinic_queue_api_token_encrypted', null));
        ?>
        <div class="clinic-token-field-wrapper">
            <?php if ($has_token): ?>
                <!-- Token exists - show status and edit button -->
                <div class="token-status-display">
                    <div class="token-saved-indicator">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span class="token-status-text">טוקן מוגדר ושמור מוצפן</span>
                    </div>
                    <div class="token-action-buttons">
                        <button type="button" class="button button-secondary clinic-edit-token-btn" id="clinic_edit_token_btn">
                            <span class="dashicons dashicons-edit"></span>
                            ערוך טוקן
                        </button>
                        <button type="button" class="button clinic-delete-token-btn" id="clinic_delete_token_btn">
                            <span class="dashicons dashicons-trash"></span>
                            מחק טוקן
                        </button>
                    </div>
                </div>
                
                <!-- Hidden input for deletion -->
                <input type="hidden" id="clinic_delete_token_flag" name="clinic_delete_token_flag" value="0" />
                
                <!-- Hidden input for editing (shown when clicking edit) -->
                <div class="token-edit-field" id="clinic_token_edit_field" style="display: none; margin-top: 16px;">
                    <div class="token-input-with-button">
                        <input 
                            type="password" 
                            id="clinic_queue_api_token" 
                            name="clinic_queue_api_token" 
                            value="" 
                            class="regular-text" 
                            placeholder="הזן טוקן חדש (או השאר ריק לשמור את הקיים)" 
                            style="direction: ltr; text-align: left;"
                            autocomplete="new-password"
                        />
                        <button type="submit" name="clinic_queue_save_settings" class="button button-primary clinic-save-token-btn" id="clinic_save_token_btn">
                            שמור
                        </button>
                        <button type="button" class="button clinic-cancel-edit-btn" id="clinic_cancel_edit_btn">
                            ביטול
                        </button>
                    </div>
                    <p class="description">השאר שדה ריק לשמור את הטוקן הקיים, או הזן טוקן חדש לעדכון</p>
                </div>
                
            <?php else: ?>
                <!-- No token - show input field -->
                <div class="token-input-field">
                    <div class="token-input-with-button">
                        <input 
                            type="password" 
                            id="clinic_queue_api_token" 
                            name="clinic_queue_api_token" 
                            value="" 
                            class="regular-text" 
                            placeholder="הזן את טוקן ה-API כאן" 
                            style="direction: ltr; text-align: left;"
                            required
                            autocomplete="new-password"
                        />
                        <button type="submit" name="clinic_queue_save_settings" class="button button-primary clinic-save-token-btn">
                            שמור
                        </button>
                    </div>
                    <p class="description" style="margin-top: 12px;">הטוקן יישמר מוצפן במסד הנתונים באמצעות AES-256-CBC</p>
                    <p class="description" style="color: #d63638; margin-top: 8px;">
                        <span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: text-bottom;"></span>
                        <strong>שים לב:</strong> עליך להזין טוקן כדי שהמערכת תוכל לתקשר עם ה-API
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render endpoint field
     * Called from template
     */
    public function render_endpoint_field() {
        $api_endpoint = get_option('clinic_queue_api_endpoint', 'https://do-proxy-staging.doctor-clinix.com');
        ?>
        <div class="clinic-endpoint-field-wrapper">
            <div class="endpoint-input-field">
                <div class="endpoint-input-with-button">
                    <input 
                        type="url" 
                        id="clinic_queue_api_endpoint" 
                        name="clinic_queue_api_endpoint" 
                        value="<?php echo esc_attr($api_endpoint); ?>" 
                        class="regular-text" 
                        placeholder="https://do-proxy-staging.doctor-clinix.com"
                        style="direction: ltr; text-align: left;"
                        required
                    />
                    <button type="submit" name="clinic_queue_save_settings" class="button button-primary clinic-save-endpoint-btn">
                        שמור
                    </button>
                </div>
                <p class="description" style="margin-top: 12px;">כתובת ה-API הבסיסית לשירות DoctorOnline Proxy</p>
            </div>
        </div>
        <?php
    }
}

