<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Exception Classes
 * מחלקות יוצאות דופן לטיפול בשגיאות
 */

/**
 * Base API Exception
 */
class Clinic_Queue_API_Exception extends Exception {
    protected $error_code;
    protected $error_data;
    
    public function __construct($message = '', $error_code = '', $error_data = array(), $code = 0, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->error_code = $error_code;
        $this->error_data = $error_data;
    }
    
    public function get_error_code() {
        return $this->error_code;
    }
    
    public function get_error_data() {
        return $this->error_data;
    }
    
    public function to_wp_error() {
        return new WP_Error($this->error_code, $this->getMessage(), $this->error_data);
    }
}

/**
 * Validation Exception
 */
class Clinic_Queue_Validation_Exception extends Clinic_Queue_API_Exception {
    public function __construct($errors = array(), $code = 400) {
        $message = 'שגיאת ולידציה';
        parent::__construct($message, 'validation_error', array('errors' => $errors), $code);
    }
}

/**
 * API Request Exception
 */
class Clinic_Queue_API_Request_Exception extends Clinic_Queue_API_Exception {
    public function __construct($message = 'שגיאה בבקשת API', $error_code = 'api_error', $error_data = array(), $code = 500) {
        parent::__construct($message, $error_code, $error_data, $code);
    }
}

/**
 * Cache Miss Exception
 */
class Clinic_Queue_Cache_Miss_Exception extends Clinic_Queue_API_Exception {
    public function __construct($message = 'Cache miss - נסה שוב בעוד כמה רגעים', $code = 503) {
        parent::__construct($message, 'cache_miss', array(), $code);
    }
}

