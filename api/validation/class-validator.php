<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validator Class
 * שכבת ולידציה מקצועית
 */
class Clinic_Queue_Validator {
    
    /**
     * Validate email
     */
    public static function validate_email($email) {
        return is_email($email);
    }
    
    /**
     * Validate phone number (basic validation)
     */
    public static function validate_phone($phone) {
        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
        // Check if it contains only digits and optionally starts with +
        return preg_match('/^\+?[0-9]{7,15}$/', $cleaned);
    }
    
    /**
     * Validate date-time string (ISO 8601)
     */
    public static function validate_datetime($datetime_string) {
        $date = DateTime::createFromFormat(DateTime::ATOM, $datetime_string);
        return $date !== false;
    }
    
    /**
     * Validate date range
     */
    public static function validate_date_range($from_date, $to_date) {
        $from = new DateTime($from_date);
        $to = new DateTime($to_date);
        return $from <= $to;
    }
    
    /**
     * Validate integer in range
     */
    public static function validate_integer_range($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        $int_value = intval($value);
        if ($min !== null && $int_value < $min) {
            return false;
        }
        if ($max !== null && $int_value > $max) {
            return false;
        }
        return true;
    }
    
    /**
     * Validate required fields
     */
    public static function validate_required($data, $required_fields) {
        $errors = array();
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "השדה '{$field}' הוא חובה";
            }
        }
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Sanitize and validate input
     */
    public static function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'int':
                return intval($input);
            case 'float':
                return floatval($input);
            case 'url':
                return esc_url_raw($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            default:
                return sanitize_text_field($input);
        }
    }
}

