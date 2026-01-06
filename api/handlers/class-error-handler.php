<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../exceptions/class-api-exception.php';

/**
 * Error Handler Class
 * טיפול מקצועי בשגיאות
 */
class Clinic_Queue_Error_Handler {
    
    /**
     * Handle API response errors
     */
    public static function handle_api_response($response_data) {
        if (is_wp_error($response_data)) {
            return $response_data;
        }
        
        // Check if response has error code
        if (isset($response_data['code'])) {
            switch ($response_data['code']) {
                case 'Success':
                    return $response_data;
                    
                case 'CacheMiss':
                    return new WP_Error(
                        'cache_miss',
                        'הנתונים לא נמצאים במטמון. נסה שוב בעוד כמה רגעים.',
                        array('status' => 503, 'retry_after' => 5)
                    );
                    
                case 'InvalidCredential':
                    return new WP_Error(
                        'invalid_credential',
                        'פרטי התחברות לא תקינים',
                        array('status' => 401)
                    );
                    
                case 'ClientError':
                    $error_message = isset($response_data['error']) ? $response_data['error'] : 'שגיאת לקוח';
                    return new WP_Error(
                        'client_error',
                        $error_message,
                        array('status' => 400)
                    );
                    
                case 'InternalServerError':
                    $error_message = isset($response_data['error']) ? $response_data['error'] : 'שגיאת שרת פנימית';
                    return new WP_Error(
                        'server_error',
                        $error_message,
                        array('status' => 500)
                    );
                    
                default:
                    $error_message = isset($response_data['error']) ? $response_data['error'] : 'שגיאה לא ידועה';
                    return new WP_Error(
                        'unknown_error',
                        $error_message,
                        array('status' => 500)
                    );
            }
        }
        
        return $response_data;
    }
    
    /**
     * Format error for REST API response
     */
    public static function format_rest_error($error) {
        if (is_wp_error($error)) {
            $error_data = $error->get_error_data();
            $status = isset($error_data['status']) ? $error_data['status'] : 500;
            
            return new WP_Error(
                $error->get_error_code(),
                $error->get_error_message(),
                array(
                    'status' => $status,
                    'data' => $error_data
                )
            );
        }
        
        return $error;
    }
    
    /**
     * Log error
     * Note: Logging is disabled - all logs should go to browser console via JavaScript
     */
    public static function log_error($message, $context = array()) {
        // Logging disabled - use JavaScript console.log() instead
    }
}

