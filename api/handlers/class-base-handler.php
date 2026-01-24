<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Handler Class
 * מחלקת בסיס לכל ה-REST API Handlers
 * 
 * כל handler של endpoint יירש מהמחלקה הזו כדי לקבל פונקציונליות משותפת:
 * - Namespace אחיד
 * - פונקציות עזר לתגובות
 * - בדיקת הרשאות
 * - Validation
 * - Error handling
 * 
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
abstract class Clinic_Queue_Base_Handler {
    
    /**
     * REST API namespace
     * 
     * @var string
     */
    protected $namespace = 'clinic-queue/v1';
    
    /**
     * Error handler instance
     * 
     * @var Clinic_Queue_Error_Handler
     */
    protected $error_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize error handler
        if (class_exists('Clinic_Queue_Error_Handler')) {
            $this->error_handler = new Clinic_Queue_Error_Handler();
        }
    }
    
    /**
     * Register routes - must be implemented by child classes
     * 
     * כל handler צריך לממש את הפונקציה הזו ולרשום את ה-routes שלו
     * 
     * @return void
     */
    abstract public function register_routes();
    
    /**
     * Create success response
     * 
     * @param mixed $data התוכן להחזרה
     * @param int $status HTTP status code (default: 200)
     * @param array $headers Additional headers (optional)
     * @return WP_REST_Response
     */
    protected function success_response($data, $status = 200, $headers = array()) {
        $response = new WP_REST_Response($data, $status);
        
        // Add custom headers if provided
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
        }
        
        return $response;
    }
    
    /**
     * Create error response
     * 
     * @param string $message הודעת השגיאה
     * @param int $status HTTP status code (default: 400)
     * @param string $code קוד השגיאה (default: 'api_error')
     * @param array $data נתונים נוספים (optional)
     * @return WP_Error
     */
    protected function error_response($message, $status = 400, $code = 'api_error', $data = array()) {
        $error_data = array_merge(
            array('status' => $status),
            $data
        );
        
        return new WP_Error($code, $message, $error_data);
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool|WP_Error True if logged in, WP_Error otherwise
     */
    protected function check_user_logged_in() {
        if (!is_user_logged_in()) {
            return $this->error_response(
                'נדרשת התחברות למערכת',
                401,
                'unauthorized'
            );
        }
        return true;
    }
    
    /**
     * Check user capability
     * 
     * @param string $capability שם ההרשאה לבדיקה
     * @return bool|WP_Error True if has capability, WP_Error otherwise
     */
    protected function check_user_capability($capability) {
        $logged_in_check = $this->check_user_logged_in();
        if (is_wp_error($logged_in_check)) {
            return $logged_in_check;
        }
        
        if (!current_user_can($capability)) {
            return $this->error_response(
                'אין לך הרשאות מספיקות לביצוע פעולה זו',
                403,
                'forbidden'
            );
        }
        
        return true;
    }
    
    /**
     * Validate required parameters
     * 
     * @param WP_REST_Request $request The request object
     * @param array $required_params רשימת פרמטרים נדרשים
     * @return bool|WP_Error True if all present, WP_Error otherwise
     */
    protected function validate_required_params($request, $required_params) {
        $missing_params = array();
        
        foreach ($required_params as $param) {
            if (!$request->has_param($param) || empty($request->get_param($param))) {
                $missing_params[] = $param;
            }
        }
        
        if (!empty($missing_params)) {
            return $this->error_response(
                sprintf(
                    'חסרים פרמטרים נדרשים: %s',
                    implode(', ', $missing_params)
                ),
                400,
                'missing_params',
                array('missing_params' => $missing_params)
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize request parameters
     * 
     * @param array $params הפרמטרים לניקוי
     * @param array $sanitize_map מפת ניקוי (param => callback)
     * @return array פרמטרים מנוקים
     */
    protected function sanitize_params($params, $sanitize_map) {
        $sanitized = array();
        
        foreach ($params as $key => $value) {
            if (isset($sanitize_map[$key]) && is_callable($sanitize_map[$key])) {
                $sanitized[$key] = call_user_func($sanitize_map[$key], $value);
            } else {
                // Default sanitization
                if (is_string($value)) {
                    $sanitized[$key] = sanitize_text_field($value);
                } elseif (is_array($value)) {
                    $sanitized[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Handle WP_Error or return data
     * 
     * פונקציה עזר לטיפול בתגובות שעשויות להיות WP_Error או data
     * 
     * @param mixed $result התוצאה לטיפול
     * @param int $success_status Status code למקרה של הצלחה (default: 200)
     * @return WP_REST_Response|WP_Error
     */
    protected function handle_result($result, $success_status = 200) {
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $this->success_response($result, $success_status);
    }
    
    /**
     * Get sanitized integer parameter
     * 
     * @param WP_REST_Request $request The request object
     * @param string $param_name שם הפרמטר
     * @param int|null $default ערך ברירת מחדל
     * @return int|null
     */
    protected function get_int_param($request, $param_name, $default = null) {
        $value = $request->get_param($param_name);
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return absint($value);
    }
    
    /**
     * Get sanitized string parameter
     * 
     * @param WP_REST_Request $request The request object
     * @param string $param_name שם הפרמטר
     * @param string|null $default ערך ברירת מחדל
     * @return string|null
     */
    protected function get_string_param($request, $param_name, $default = null) {
        $value = $request->get_param($param_name);
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Get sanitized boolean parameter
     * 
     * @param WP_REST_Request $request The request object
     * @param string $param_name שם הפרמטר
     * @param bool|null $default ערך ברירת מחדל
     * @return bool|null
     */
    protected function get_bool_param($request, $param_name, $default = null) {
        $value = $request->get_param($param_name);
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Get sanitized array parameter
     * 
     * @param WP_REST_Request $request The request object
     * @param string $param_name שם הפרמטר
     * @param array $default ערך ברירת מחדל
     * @return array
     */
    protected function get_array_param($request, $param_name, $default = array()) {
        $value = $request->get_param($param_name);
        
        if (!is_array($value)) {
            return $default;
        }
        
        return array_map('sanitize_text_field', $value);
    }
    
    /**
     * Verify nonce for secure requests
     * 
     * @param WP_REST_Request $request The request object
     * @param string $nonce_name שם ה-nonce
     * @param string $action פעולת ה-nonce
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    protected function verify_nonce($request, $nonce_name, $action) {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (!$nonce) {
            $nonce = $request->get_param($nonce_name);
        }
        
        if (!$nonce || !wp_verify_nonce($nonce, $action)) {
            return $this->error_response(
                'אימות אבטחה נכשל',
                403,
                'invalid_nonce'
            );
        }
        
        return true;
    }
    
    /**
     * Log to browser console (via JavaScript)
     * 
     * Note: PHP error_log is disabled. All logging should be done via JavaScript.
     * This is a placeholder for future JavaScript integration.
     * 
     * @param string $message הודעה ללוג
     * @param string $level רמת הלוג (log/error/warn/info)
     * @return void
     */
    protected function log($message, $level = 'log') {
        // Logging disabled - use JavaScript console.log() instead
        // For debugging, add JavaScript code that calls:
        // window.ClinicQueueUtils.log(message) or console.log(message)
    }
    
    /**
     * Default permission callback - always allow
     * 
     * Use this for public endpoints
     * 
     * @return bool
     */
    public function permission_callback_public() {
        return true;
    }
    
    /**
     * Permission callback - require logged in user
     * 
     * @return bool
     */
    public function permission_callback_logged_in() {
        return is_user_logged_in();
    }
    
    /**
     * Permission callback - require admin
     * 
     * @return bool
     */
    public function permission_callback_admin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Permission callback - require editor or above
     * 
     * @return bool
     */
    public function permission_callback_editor() {
        return current_user_can('edit_others_posts');
    }
}
