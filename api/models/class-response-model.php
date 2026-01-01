<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-model.php';

/**
 * Response Models
 * אובייקטי תגובה מ-API
 */

/**
 * Base Response Model
 */
class Clinic_Queue_Base_Response_Model extends Clinic_Queue_Base_Model {
    public $code; // 'Success' | 'InvalidCredential' | 'ClientError' | 'InternalServerError' | 'CacheMiss'
    public $error = null;
    
    public function validate() {
        $valid_codes = array('Success', 'InvalidCredential', 'ClientError', 'InternalServerError', 'CacheMiss');
        if (!in_array($this->code, $valid_codes)) {
            return array('קוד תגובה לא תקין');
        }
        return true;
    }
    
    public function is_success() {
        return $this->code === 'Success';
    }
    
    public function is_cache_miss() {
        return $this->code === 'CacheMiss';
    }
}

/**
 * Result Base Response Model (with result property)
 */
class Clinic_Queue_Result_Response_Model extends Clinic_Queue_Base_Response_Model {
    public $result = null;
}

/**
 * List Result Base Response Model (with result array)
 */
class Clinic_Queue_List_Response_Model extends Clinic_Queue_Base_Response_Model {
    public $result = array(); // Array of items
}

