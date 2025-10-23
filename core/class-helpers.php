<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper Functions for Clinic Queue Management
 * Contains utility functions used across the plugin
 */
class Clinic_Queue_Helpers {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get doctor name by ID
     */
    public function get_doctor_name($doctor_id) {
        // Mock data for doctor names - replace with actual data source
        $doctors = array(
            '1' => 'ד"ר יוסי כהן',
            '2' => 'ד"ר שרה לוי',
            '3' => 'ד"ר דוד ישראלי',
            '4' => 'ד"ר מיכל גולד',
            '5' => 'ד"ר אורי ברק'
        );
        
        return isset($doctors[$doctor_id]) ? $doctors[$doctor_id] : 'רופא לא ידוע';
    }
    
    /**
     * Get clinic name by ID
     */
    public function get_clinic_name($clinic_id) {
        // Mock data for clinic names - replace with actual data source
        $clinics = array(
            '1' => 'מרפאת "הטרול המחייך"',
            '2' => 'מרפאת "הדובון החמוד"',
            '3' => 'מרפאת "הפילון הקטן"',
            '4' => 'מרפאת "הקיפוד הנחמד"',
            '5' => 'מרפאת "הדולפין השמח"'
        );
        
        return isset($clinics[$clinic_id]) ? $clinics[$clinic_id] : 'מרפאה לא ידועה';
    }
    
    /**
     * Format date for display
     */
    public function format_date($date, $format = 'Y-m-d') {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        return $date->format($format);
    }
    
    /**
     * Get Hebrew month name
     */
    public function get_hebrew_month($month_number) {
        $hebrew_months = array(
            1 => 'ינואר',
            2 => 'פברואר',
            3 => 'מרץ',
            4 => 'אפריל',
            5 => 'מאי',
            6 => 'יוני',
            7 => 'יולי',
            8 => 'אוגוסט',
            9 => 'ספטמבר',
            10 => 'אוקטובר',
            11 => 'נובמבר',
            12 => 'דצמבר'
        );
        
        return isset($hebrew_months[$month_number]) ? $hebrew_months[$month_number] : '';
    }
    
    /**
     * Get Hebrew day abbreviation
     */
    public function get_hebrew_day_abbrev($day_name) {
        $hebrew_day_abbrev = array(
            'Sunday' => 'א׳',
            'Monday' => 'ב׳',
            'Tuesday' => 'ג׳',
            'Wednesday' => 'ד׳',
            'Thursday' => 'ה׳',
            'Friday' => 'ו׳',
            'Saturday' => 'ש׳'
        );
        
        return isset($hebrew_day_abbrev[$day_name]) ? $hebrew_day_abbrev[$day_name] : $day_name;
    }
    
    /**
     * Sanitize text input
     */
    public function sanitize_text($text) {
        return sanitize_text_field($text);
    }
    
    /**
     * Sanitize email input
     */
    public function sanitize_email($email) {
        return sanitize_email($email);
    }
    
    /**
     * Check if user has required capability
     */
    public function user_can_manage() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get current timestamp
     */
    public function get_current_timestamp() {
        return current_time('timestamp');
    }
    
    /**
     * Get current date in Y-m-d format
     */
    public function get_current_date() {
        return current_time('Y-m-d');
    }
    
    /**
     * Get current datetime in Y-m-d H:i:s format
     */
    public function get_current_datetime() {
        return current_time('Y-m-d H:i:s');
    }
}
