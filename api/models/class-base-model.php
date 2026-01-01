<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Model (Data Transfer Object) Class
 * כל ה-Models יורשים מהקלאס הזה
 */
abstract class Clinic_Queue_Base_Model {
    
    /**
     * Convert Model to array
     */
    public function to_array() {
        return get_object_vars($this);
    }
    
    /**
     * Create Model from array
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
     * Validate Model
     * כל Model צריך לממש את הפונקציה הזו
     */
    abstract public function validate();
}

