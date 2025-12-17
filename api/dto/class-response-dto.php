<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-dto.php';

/**
 * Response DTOs
 * אובייקטי תגובה מ-API
 */

/**
 * Base Response DTO
 */
class Clinic_Queue_Base_Response_DTO extends Clinic_Queue_Base_DTO {
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
 * Result Base Response DTO (with result property)
 */
class Clinic_Queue_Result_Response_DTO extends Clinic_Queue_Base_Response_DTO {
    public $result = null;
}

/**
 * List Result Base Response DTO (with result array)
 */
class Clinic_Queue_List_Response_DTO extends Clinic_Queue_Base_Response_DTO {
    public $result = array(); // Array of items
}

