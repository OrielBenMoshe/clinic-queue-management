<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base DTO (Data Transfer Object) Class
 * כל ה-DTOs יורשים מהקלאס הזה
 */
abstract class Clinic_Queue_Base_DTO {
    
    /**
     * Convert DTO to array
     */
    public function to_array() {
        return get_object_vars($this);
    }
    
    /**
     * Create DTO from array
     */
    public static function from_array($data) {
        $instance = new static();
        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->$key = $value;
            }
        }
        return $instance;
    }
    
    /**
     * Validate DTO
     * כל DTO צריך לממש את הפונקציה הזו
     */
    abstract public function validate();
}

