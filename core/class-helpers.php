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
    
    /**
     * Find page ID by shortcode name
     * 
     * @param string $shortcode_name Shortcode name (e.g., 'booking_form')
     * @return int|null Page ID or null if not found
     */
    public function find_page_by_shortcode($shortcode_name) {
        // Cache key
        $cache_key = 'clinic_queue_page_by_shortcode_' . $shortcode_name;
        
        // Try to get from cache
        $page_id = wp_cache_get($cache_key, 'clinic_queue');
        if ($page_id !== false) {
            return $page_id ? (int) $page_id : null;
        }
        
        // Search for pages containing the shortcode
        // Use direct database query for better performance
        global $wpdb;
        
        $shortcode_pattern = '%[' . $wpdb->esc_like($shortcode_name) . '%';
        
        $page_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
            AND post_status = 'publish' 
            AND (post_content LIKE %s OR post_excerpt LIKE %s)
            ORDER BY post_date DESC
            LIMIT 100",
            $shortcode_pattern,
            $shortcode_pattern
        ));
        
        $page_id = null;
        
        if (!empty($page_ids)) {
            foreach ($page_ids as $id) {
                $post = get_post($id);
                if ($post && has_shortcode($post->post_content, $shortcode_name)) {
                    $page_id = (int) $id;
                    break;
                }
            }
        }
        
        // Cache result (24 hours)
        wp_cache_set($cache_key, $page_id ? $page_id : 0, 'clinic_queue', 86400);
        
        return $page_id ? (int) $page_id : null;
    }
}
