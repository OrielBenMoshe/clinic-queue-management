<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/services/class-encryption-service.php';

/**
 * Settings Handler
 * 
 * תפקיד הקובץ:
 * =============
 * קובץ זה מטפל בכל הלוגיקה העסקית של דף ההגדרות של התוסף.
 * הוא אחראי על:
 * - טיפול בהגשת טופס ההגדרות (form submission)
 * - שמירה וניהול טוקן API (מוצפן במסד הנתונים)
 * - שמירה וניהול כתובת שרת API
 * - אחזור נתוני הגדרות להצגה
 * - טעינת assets (CSS/JS) של דף ההגדרות
 * - רינדור שדות הטופס (token, endpoint)
 * 
 * ארכיטקטורה:
 * ============
 * הקובץ משתייך לשכבת ה-Handlers (Business Logic Layer):
 * - handlers/ - לוגיקה עסקית
 * - services/ - שירותים משותפים (כגון Encryption_Service)
 * - views/ - תבניות HTML
 * 
 * הפרדת אחריות:
 * ==============
 * - Form Handling: handle_form_submission(), process_settings()
 * - Token Management: save_token(), delete_token()
 * - Endpoint Management: save_endpoint()
 * - Data Retrieval: get_settings_data(), get_token_debug_info()
 * - Rendering: render_page(), render_token_field(), render_endpoint_field()
 * - Assets: enqueue_assets()
 * 
 * הערות חשובות:
 * ==============
 * 1. הטוקן נשמר מוצפן במסד הנתונים (WordPress Options) עם שם: 'clinic_queue_api_token_encrypted'
 * 2. הקוד תומך ב-prefix מותאם אישית של מסד הנתונים (לא רק 'wp_')
 * 3. יש fallback לשאילתות ישירות למסד הנתונים אם פונקציות WordPress נכשלות
 * 4. הלוגים נכתבים גם ל-error_log וגם לקבצים נפרדים (לצורכי דיבוג)
 * 
 * @package ClinicQueue
 * @subpackage Admin\Handlers
 * @since 1.0.0
 */
class Clinic_Queue_Settings_Handler {
    
    /**
     * Singleton instance
     * 
     * @var Clinic_Queue_Settings_Handler|null
     */
    private static $instance = null;
    
    /**
     * Encryption service instance
     * 
     * @var Clinic_Queue_Encryption_Service
     */
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
    
    /**
     * Constructor
     * 
     * מאתחל את ה-handler ורושם את ה-hooks הנדרשים לטיפול בהגשת טופס.
     * משתמש ב-admin_init (סטנדרטי) וב-admin_post (גיבוי) למקסימום תאימות.
     */
    private function __construct() {
        $this->encryption_service = Clinic_Queue_Encryption_Service::get_instance();
        
        // Clean up old/obsolete options
        $this->cleanup_obsolete_options();
        
        // Register form submission handler
        // admin_post is the correct hook for admin-post.php
        add_action('admin_post_clinic_queue_save_settings', array($this, 'handle_form_submission'));
        
        // Also register for logged-out users (if needed in future)
        add_action('admin_post_nopriv_clinic_queue_save_settings', array($this, 'handle_form_submission'));
        
        // admin_init as fallback (for direct form submissions to admin.php)
        add_action('admin_init', array($this, 'handle_form_submission'));
    }
    
    /**
     * Clean up obsolete options from database
     * מנקה רשומות ישנות/לא רלוונטיות מטבלת options
     * 
     * @return void
     */
    private function cleanup_obsolete_options() {
        global $wpdb;
        
        // List of obsolete option names to delete
        $obsolete_options = array(
            'clinic_queue_cron_logs', // לא בשימוש, שארית קוד ישן
        );
        
        foreach ($obsolete_options as $option_name) {
            $exists = get_option($option_name, false);
            if ($exists !== false) {
                delete_option($option_name);
            }
        }
    }
    
    /**
     * Handle form submission
     * 
     * מטפל בהגשת טופס ההגדרות:
     * - בודק שהטופס נשלח מהדף הנכון
     * - מאמת nonce (אבטחה)
     * - בודק הרשאות משתמש
     * - מעבד ושומר את ההגדרות
     * - מפנה חזרה לדף עם הודעת הצלחה/שגיאה
     * 
     * @return void
     */
    public function handle_form_submission() {
        try {
            // Store in transient that function was called (for debugging)
            set_transient('clinic_queue_handler_called', 'yes', 10);
        
        // Check if this is admin_post request (more reliable)
        $is_admin_post = (isset($_REQUEST['action']) && $_REQUEST['action'] === 'clinic_queue_save_settings');
        
        // For admin_init hook, check GET parameter
        $is_settings_page = (isset($_GET['page']) && $_GET['page'] === 'clinic-queue-settings');
        
        // Only process if it's admin_post OR it's our settings page
        // For admin-post.php, we should always have the action in REQUEST
        if (!$is_admin_post && !$is_settings_page) {
            set_transient('clinic_queue_handler_exit_reason', 'wrong_page_or_action', 10);
            
            // If we're on admin-post.php but action doesn't match, redirect back
            if (strpos($_SERVER['REQUEST_URI'], 'admin-post.php') !== false) {
                wp_redirect(admin_url('admin.php?page=clinic-queue-settings&error=action_mismatch'));
                exit;
            }
            return;
        }
        
        // Check if form was submitted
        // The form sends 'action' field, not 'clinic_queue_save_settings'
        $form_action = isset($_POST['action']) ? $_POST['action'] : (isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
        if ($form_action !== 'clinic_queue_save_settings') {
            set_transient('clinic_queue_handler_exit_reason', 'form_not_submitted_or_wrong_action: ' . $form_action, 10);
            return;
        }
        
        // Verify nonce (security check)
        if (!isset($_POST['clinic_queue_settings_nonce'])) {
            wp_die(__('Security check failed: Nonce not found', 'clinic-queue'));
        }
        
        $nonce_verified = wp_verify_nonce($_POST['clinic_queue_settings_nonce'], 'clinic_queue_save_settings');
        if (!$nonce_verified) {
            wp_die(__('Security check failed', 'clinic-queue'));
        }
        
        // Check permissions (only administrators can save settings)
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to save settings', 'clinic-queue'));
        }
        
        // Process settings
        $save_result = $this->process_settings();
        
        // Determine if this is admin_post request (for redirect)
        $is_admin_post_redirect = (isset($_REQUEST['action']) && $_REQUEST['action'] === 'clinic_queue_save_settings');
        
        // Redirect back with success/error message
        // If admin_post, redirect to settings page
        if ($is_admin_post_redirect) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'clinic-queue-settings',
                    'settings-updated' => $save_result ? 'true' : 'false'
                ),
                admin_url('admin.php')
            );
        } else {
            // For admin_init, add query parameter to current URL
            $redirect_url = add_query_arg(
                array(
                    'settings-updated' => $save_result ? 'true' : 'false'
                ),
                admin_url('admin.php?page=clinic-queue-settings')
            );
        }
        
            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            // Always redirect back, even on error
            $error_url = add_query_arg(
                array(
                    'page' => 'clinic-queue-settings',
                    'error' => 'save_failed',
                    'error_message' => urlencode($e->getMessage())
                ),
                admin_url('admin.php')
            );
            wp_redirect($error_url);
            exit;
        } catch (Error $e) {
            // Always redirect back, even on fatal error
            $error_url = add_query_arg(
                array(
                    'page' => 'clinic-queue-settings',
                    'error' => 'fatal_error',
                    'error_message' => urlencode('Fatal error occurred. Check logs.')
                ),
                admin_url('admin.php')
            );
            wp_redirect($error_url);
            exit;
        }
    }
    
    /**
     * Process and save settings
     * 
     * מעבד את כל ההגדרות מהטופס ושומר אותן:
     * - טוקן API (אם נשלח)
     * - כתובת שרת API (אם נשלחה)
     * 
     * @return bool True אם כל ההגדרות נשמרו בהצלחה, false אחרת
     */
    private function process_settings() {
        $token_saved = true;
        $endpoint_saved = true;
        
        // Check if user wants to delete the token
        $delete_token = isset($_POST['clinic_delete_token_flag']) && $_POST['clinic_delete_token_flag'] === '1';
        
        if ($delete_token) {
            $this->delete_token();
        } else {
            $token_saved = $this->save_token();
        }
        
        // Save API endpoint
        $endpoint_saved = $this->save_endpoint();
        
        return $token_saved && $endpoint_saved;
    }
    
    /**
     * Delete API token
     * 
     * מוחק את הטוקן המוצפן ממסד הנתונים.
     * 
     * @return void
     */
    private function delete_token() {
        delete_option('clinic_queue_api_token_encrypted');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ClinicQueue Settings] Token deleted');
        }
    }
    
    /**
     * Save API token
     * 
     * שומר את טוקן ה-API מוצפן במסד הנתונים.
     * 
     * תהליך:
     * 1. מקבל את הטוקן מהטופס
     * 2. מצפין אותו באמצעות Encryption_Service
     * 3. מנסה לשמור עם update_option() / add_option()
     * 4. אם נכשל, משתמש בשאילתה ישירה למסד הנתונים
     * 5. בודק שהשמירה הצליחה
     * 
     * תמיכה ב-prefix מותאם:
     * הקוד תומך ב-prefix מותאם אישית של מסד הנתונים (לא רק 'wp_')
     * באמצעות $wpdb->prefix.
     * 
     * @return bool|void True אם נשמר בהצלחה, false אם נכשל, void אם לא היה טוקן לשמירה
     */
    private function save_token() {
        // Check if token field exists in POST
        if (!isset($_POST['clinic_queue_api_token'])) {
            return false;
        }
        
        // Sanitize token
        $token = sanitize_text_field($_POST['clinic_queue_api_token']);
        
        // If token is empty, keep existing one (don't delete)
        if (empty($token)) {
            return true; // Not an error, just no change
        }
        
        // Encrypt token using Encryption Service
        $encrypted = $this->encryption_service->encrypt_token($token);
        
        // Check if encryption returned empty or null
        if (empty($encrypted)) {
            set_transient('clinic_queue_token_save_error', 'שגיאה: הצפנת הטוקן נכשלה.', 30);
            return false;
        }
        
        // Get database prefix and table name (supports custom prefixes)
        global $wpdb;
        $db_prefix = $wpdb->prefix;
        $table_name = $db_prefix . 'options';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if (!$table_exists) {
            set_transient('clinic_queue_token_save_error', 'שגיאה קריטית: טבלת Options לא נמצאה במסד הנתונים.', 30);
            return false;
        }
        
        // Check if option already exists using direct query (with correct prefix)
        $option_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE option_name = %s",
            'clinic_queue_api_token_encrypted'
        )) > 0;
        
        // Try WordPress functions first (they should handle prefix automatically)
        $result = false;
        if ($option_exists) {
            $result = update_option('clinic_queue_api_token_encrypted', $encrypted);
        } else {
            $result = add_option('clinic_queue_api_token_encrypted', $encrypted);
        }
        
        // Always verify with direct query, and if WordPress functions failed, use direct DB
        $direct_check = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$table_name} WHERE option_name = %s LIMIT 1",
            'clinic_queue_api_token_encrypted'
        ));
        
        // If WordPress functions failed or verification failed, use direct DB query
        if (empty($direct_check) || !$result) {
            if ($option_exists) {
                // Update existing record
                $db_result = $wpdb->update(
                    $table_name,
                    array('option_value' => $encrypted),
                    array('option_name' => 'clinic_queue_api_token_encrypted'),
                    array('%s'),
                    array('%s')
                );
                $result = ($db_result !== false);
            } else {
                // Insert new record
                $db_result = $wpdb->insert(
                    $table_name,
                    array(
                        'option_name' => 'clinic_queue_api_token_encrypted',
                        'option_value' => $encrypted,
                        'autoload' => 'no'
                    ),
                    array('%s', '%s', '%s')
                );
                
                if ($db_result === false) {
                    // Try direct SQL query as last resort
                    $sql = $wpdb->prepare(
                        "INSERT INTO {$table_name} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
                        'clinic_queue_api_token_encrypted',
                        $encrypted,
                        'no'
                    );
                    $direct_sql_result = $wpdb->query($sql);
                    $result = ($direct_sql_result !== false && $direct_sql_result > 0);
                } else {
                    $result = true;
                }
            }
            
            // Verify again after direct DB operation
            $direct_check = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$table_name} WHERE option_name = %s LIMIT 1",
                'clinic_queue_api_token_encrypted'
            ));
        }
        
        // Also check via get_option (WordPress function)
        $saved_value = get_option('clinic_queue_api_token_encrypted', null);
        
        // Check if save was successful
        if (empty($direct_check) && empty($saved_value)) {
            // Store error in transient to show to user
            set_transient('clinic_queue_token_save_error', 'שגיאה: הטוקן לא נשמר למסד הנתונים.', 30);
            return false;
        } else {
            // Clear any previous errors
            delete_transient('clinic_queue_token_save_error');
            return true;
        }
    }
    
    /**
     * Save API endpoint
     * 
     * שומר את כתובת שרת ה-API במסד הנתונים.
     * 
     * תהליך:
     * 1. מקבל את הכתובת מהטופס
     * 2. מאמת שהיא כתובת URL תקינה
     * 3. מנסה לשמור עם update_option()
     * 4. אם נכשל, משתמש בשאילתה ישירה למסד הנתונים
     * 
     * @return bool True אם נשמר בהצלחה, false אם נכשל, true אם השדה לא נשלח (לא שגיאה)
     */
    private function save_endpoint() {
        // Check if endpoint field exists in POST
        if (!isset($_POST['clinic_queue_api_endpoint'])) {
            return true; // Not an error if field not present
        }
        
        // Sanitize and validate URL
        $endpoint = esc_url_raw($_POST['clinic_queue_api_endpoint']);
        
        if (empty($endpoint)) {
            return true; // Not an error if empty
        }
        
        // Try WordPress function first
        $result = update_option('clinic_queue_api_endpoint', $endpoint);
        
        // If failed, use direct DB query
        if (!$result) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'options';
            
            // Check if option exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE option_name = %s",
                'clinic_queue_api_endpoint'
            )) > 0;
            
            if ($exists) {
                // Update existing record
                $db_result = $wpdb->update(
                    $table_name,
                    array('option_value' => $endpoint),
                    array('option_name' => 'clinic_queue_api_endpoint'),
                    array('%s'),
                    array('%s')
                );
                return ($db_result !== false);
            } else {
                // Insert new record
                $db_result = $wpdb->insert(
                    $table_name,
                    array(
                        'option_name' => 'clinic_queue_api_endpoint',
                        'option_value' => $endpoint,
                        'autoload' => 'no'
                    ),
                    array('%s', '%s', '%s')
                );
                return ($db_result !== false);
            }
        }
        
        return $result;
    }
    
    /**
     * Enqueue settings page assets
     * 
     * טוען את קבצי ה-CSS וה-JavaScript של דף ההגדרות.
     * נקרא ישירות מ-render_page() (לא דרך hook) כדי להבטיח שהקבצים נטענים.
     * 
     * @return void
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
        
        // Localize script (pass data to JavaScript)
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
     * אוסף את כל נתוני ההגדרות הנוכחיים מהמסד הנתונים ומכין אותם להצגה.
     * 
     * @return array מערך עם כל נתוני ההגדרות:
     *   - updated: האם ההגדרות עודכנו לאחרונה
     *   - has_token: האם יש טוקן שמור
     *   - current_token: הטוקן הנוכחי (מפוענח, רק להצגה)
     *   - api_endpoint: כתובת שרת ה-API
     *   - has_constant_endpoint: האם יש קבוע endpoint
     *   - endpoint_from_constant: ערך ה-endpoint מהקבוע
     *   - encryption_available: האם הצפנה זמינה
     *   - token_debug_info: מידע דיבוג על הטוקן (לצורכי בדיקה)
     */
    public function get_settings_data() {
        // Get encrypted token from database
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        $has_token = !empty($encrypted_token);
        
        // Decrypt token for display (if exists)
        $current_token = '';
        if ($has_token) {
            $current_token = $this->encryption_service->decrypt_token($encrypted_token);
        }
        
        // Get API endpoint (with fallback to default)
        $api_endpoint = get_option('clinic_queue_api_endpoint', 'https://do-proxy-staging.doctor-clinix.com');
        
        // Check if endpoint is defined as constant
        $endpoint_from_constant = defined('CLINIC_QUEUE_API_ENDPOINT') ? CLINIC_QUEUE_API_ENDPOINT : null;
        $has_constant_endpoint = !empty($endpoint_from_constant);
        
        // Get token debug info (for testing purposes)
        $token_debug_info = $this->get_token_debug_info();
        
        return array(
            'updated' => isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true',
            'has_token' => $has_token,
            'current_token' => $current_token,
            'api_endpoint' => $api_endpoint,
            'has_constant_endpoint' => $has_constant_endpoint,
            'endpoint_from_constant' => $endpoint_from_constant,
            'encryption_available' => $this->encryption_service->is_encryption_available(),
            'token_debug_info' => $token_debug_info
        );
    }
    
    /**
     * Get detailed token debug information
     * 
     * אוסף מידע מפורט על מצב הטוקן במסד הנתונים.
     * משמש לצורכי דיבוג ובדיקה בלבד.
     * 
     * @return array מערך עם מידע דיבוג:
     *   - option_exists: האם האופציה קיימת במסד הנתונים
     *   - option_value_preview: תצוגה מקדימה של הערך המוצפן
     *   - option_value_length: אורך הערך המוצפן
     *   - decryption_success: האם הפענוח הצליח
     *   - decrypted_token_length: אורך הטוקן המפוענח
     *   - decrypted_token_preview: תצוגה מקדימה של הטוקן המפוענח
     *   - token_source: מקור הטוקן (wordpress_option / none)
     *   - token_source_details: פרטים נוספים על המקור
     */
    private function get_token_debug_info() {
        $debug_info = array(
            'option_exists' => false,
            'option_value_preview' => '',
            'option_value_length' => 0,
            'decryption_success' => false,
            'decrypted_token_length' => 0,
            'decrypted_token_preview' => '',
            'token_source' => 'none',
            'token_source_details' => array()
        );
        
        // Check option storage
        $encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
        if (!empty($encrypted_token)) {
            $debug_info['option_exists'] = true;
            $debug_info['option_value_length'] = strlen($encrypted_token);
            $debug_info['option_value_preview'] = substr($encrypted_token, 0, 50) . '...';
            
            // Try to decrypt
            $decrypted = $this->encryption_service->decrypt_token($encrypted_token);
            if ($decrypted !== false) {
                $debug_info['decryption_success'] = true;
                $debug_info['decrypted_token_length'] = strlen($decrypted);
                $debug_info['decrypted_token_preview'] = substr($decrypted, 0, 20) . '...';
            }
        }
        
        // Determine token source (only WordPress Option is supported)
        if ($debug_info['option_exists']) {
            $debug_info['token_source'] = 'wordpress_option';
            $debug_info['token_source_details'] = array(
                'option_name' => 'clinic_queue_api_token_encrypted',
                'encrypted' => true,
                'decryptable' => $debug_info['decryption_success']
            );
        }
        
        return $debug_info;
    }
    
    /**
     * Render settings page
     * 
     * נקודת הכניסה הראשית לרינדור דף ההגדרות.
     * 
     * תהליך:
     * 1. טוען assets (CSS/JS)
     * 2. אוסף נתוני הגדרות
     * 3. מציג הודעת שגיאה אם יש (משמירה קודמת)
     * 4. כולל את תבנית ה-HTML
     * 
     * @return void
     */
    public function render_page() {
        
        // Show error message if token save failed
        $error_message = get_transient('clinic_queue_token_save_error');
        if ($error_message) {
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html($error_message) . '</strong></p></div>';
            });
        }
        
        // Enqueue assets (called directly, not via hook)
        $this->enqueue_assets();
        
        // Get settings data
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
     * 
     * מציג את שדה הטוקן בטופס ההגדרות.
     * 
     * התנהגות:
     * - אם יש טוקן שמור: מציג סטטוס + כפתורי עריכה/מחיקה
     * - אם אין טוקן: מציג שדה קלט לשמירת טוקן חדש
     * 
     * נקרא מהתבנית (settings-html.php) דרך $handler->render_token_field()
     * 
     * @return void
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
     * 
     * מציג את שדה כתובת שרת ה-API בטופס ההגדרות.
     * 
     * נקרא מהתבנית (settings-html.php) דרך $handler->render_endpoint_field()
     * 
     * @return void
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
