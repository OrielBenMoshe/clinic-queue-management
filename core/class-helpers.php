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

    /**
     * מפתח wp_options ללוג webhooks נכנסים (ring buffer).
     *
     * @var string
     */
    const WEBHOOK_LOGS_OPTION = 'clinic_queue_webhook_logs';

    /**
     * מספר רשומות מקסימלי בלוג.
     *
     * @var int
     */
    const WEBHOOK_LOGS_MAX = 50;

    /**
     * רישום כניסה ללוג webhook נכנס.
     *
     * @param array $data endpoint, status, auth, body, response, ip (אופציונלי).
     * @return void
     */
    public static function log_webhook_entry(array $data) {
        $logs = get_option(self::WEBHOOK_LOGS_OPTION, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        $entry = array(
            'time'     => current_time('mysql'),
            'endpoint' => isset($data['endpoint']) ? sanitize_text_field($data['endpoint']) : '',
            'status'   => isset($data['status']) ? absint($data['status']) : 0,
            'auth'     => isset($data['auth']) ? sanitize_key($data['auth']) : '',
            'body'     => is_array($data['body'] ?? null) ? $data['body'] : array(),
            'response' => is_array($data['response'] ?? null) ? $data['response'] : array(),
        );

        if (!empty($data['ip'])) {
            $ip = sanitize_text_field($data['ip']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $entry['ip'] = $ip;
            }
        }

        array_unshift($logs, $entry);
        $logs = array_slice($logs, 0, self::WEBHOOK_LOGS_MAX);
        update_option(self::WEBHOOK_LOGS_OPTION, $logs, false);
    }

    /**
     * שליפת כל רשומות לוג ה-webhooks.
     *
     * @return array
     */
    public static function get_webhook_logs() {
        $logs = get_option(self::WEBHOOK_LOGS_OPTION, array());
        return is_array($logs) ? $logs : array();
    }

    /**
     * מחיקת כל רשומות לוג ה-webhooks.
     *
     * @return void
     */
    public static function clear_webhook_logs() {
        delete_option(self::WEBHOOK_LOGS_OPTION);
    }

    /**
     * נרמול מספר תעודת זהות ישראלית לספרות בלבד (עד 9).
     *
     * @param string $id מספר גולמי.
     * @return string
     */
    public static function normalize_israeli_id_number($id) {
        $digits = preg_replace('/\D+/', '', (string) $id);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) > 9) {
            return '';
        }

        return str_pad($digits, 9, '0', STR_PAD_LEFT);
    }

    /**
     * ולידציה של תעודת זהות ישראלית (ספרת ביקורת).
     *
     * @param string $id מספר תעודת זהות.
     * @return bool
     */
    public static function is_valid_israeli_id_number($id) {
        $normalized = self::normalize_israeli_id_number($id);
        if ($normalized === '' || strlen($normalized) !== 9) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $normalized[$i];
            $step  = $digit * (($i % 2) + 1);
            if ($step > 9) {
                $step -= 9;
            }
            $sum += $step;
        }

        return ($sum % 10) === 0;
    }

    /**
     * מיפוי ערך מין מ-user meta לערך API (Male/Female).
     *
     * @param string $raw_gender ערך גולמי מ-meta או מרפיטר.
     * @return string|null Male|Female או null כשאין ערך תקף.
     */
    public static function map_gender_for_api($raw_gender) {
        $value = strtolower(trim((string) $raw_gender));
        if ($value === '') {
            return null;
        }

        if (in_array($value, array('male', 'm', 'זכר'), true)) {
            return 'Male';
        }

        if (in_array($value, array('female', 'f', 'נקבה'), true)) {
            return 'Female';
        }

        return null;
    }

    /**
     * Infer API gender (Male/Female) from family relationship label.
     *
     * Neutral relationships (הורה, אחר) return null — omit gender in API payload.
     *
     * @param string $relationship Hebrew relationship label.
     * @return string|null Male|Female or null when unknown/neutral.
     */
    public static function map_relationship_to_gender_for_api($relationship) {
        $relationship = trim((string) $relationship);
        if ($relationship === '') {
            return null;
        }

        $male_relationships = array('בן', 'בן זוג', 'אח', 'אבא', 'סבא', 'דוד');
        $female_relationships = array('בת', 'בת זוג', 'אחות', 'אמא', 'סבתא', 'דודה');

        if (in_array($relationship, $male_relationships, true)) {
            return 'Male';
        }

        if (in_array($relationship, $female_relationships, true)) {
            return 'Female';
        }

        return null;
    }

    /**
     * Load inline SVG icon from assets/images/icons.
     *
     * @param string   $filename Icon filename e.g. 'close-icon.svg'.
     * @param int|null $width    Optional width attribute.
     * @param int|null $height   Optional height attribute.
     * @return string SVG markup or empty string when missing.
     */
    public static function load_icon_from_assets($filename, $width = null, $height = null) {
        if (!defined('CLINIC_QUEUE_MANAGEMENT_PATH')) {
            return '';
        }

        $path = CLINIC_QUEUE_MANAGEMENT_PATH . 'assets/images/icons/' . $filename;
        if (!is_readable($path)) {
            return '';
        }

        $svg = file_get_contents($path);
        if ($svg === false || $svg === '') {
            return '';
        }

        $svg = trim($svg);
        if ($width !== null || $height !== null) {
            $w   = $width !== null ? (int) $width : '';
            $h   = $height !== null ? (int) $height : '';
            $svg = preg_replace('/\s*width="[^"]*"/', ' width="' . $w . '"', $svg, 1);
            $svg = preg_replace('/\s*height="[^"]*"/', ' height="' . $h . '"', $svg, 1);
        }

        return $svg;
    }

    /**
     * מיפוי ערך מטא `scheduler_status_in_proxy` לתגית סטטוס יומן.
     *
     * ערכים מותרים: active, inactive, pending, error.
     * ערך ריק/לא מוכר מחזיר slug `unknown` עם תווית fallback.
     *
     * @param mixed $raw_status ערך המטא הגולמי.
     * @return array{status: string, label: string}
     */
    public static function resolve_scheduler_proxy_status($raw_status) {
        $status = is_string($raw_status) ? strtolower(trim($raw_status)) : '';

        $labels = array(
            'active'   => 'פעיל',
            'inactive' => 'לא פעיל',
            'pending'  => 'ממתין',
            'error'    => 'שגיאה',
        );

        if (isset($labels[$status])) {
            return array(
                'status' => $status,
                'label'  => $labels[$status],
            );
        }

        return array(
            'status' => 'unknown',
            'label'  => 'לא ידוע',
        );
    }

    /**
     * Render shared modal close button with 16×16 X icon.
     *
     * @param array $args {
     *     @type string $class       Additional CSS classes (space-separated).
     *     @type string $aria_label  Accessible label.
     *     @type array  $attributes  Extra HTML attributes [name => value].
     * }
     * @return void
     */
    public static function render_modal_close_button($args = array()) {
        $defaults = array(
            'class'       => '',
            'aria_label'  => __('סגור', 'clinic-queue-management'),
            'attributes'  => array(),
        );
        $args = wp_parse_args($args, $defaults);

        $classes = array('clinic-queue__modal-close');
        if (!empty($args['class'])) {
            $extra_classes = preg_split('/\s+/', trim((string) $args['class']));
            if (is_array($extra_classes)) {
                $classes = array_merge($classes, array_filter($extra_classes));
            }
        }

        $button_class = implode(' ', array_unique($classes));
        $aria_label   = (string) $args['aria_label'];
        $close_icon   = self::load_icon_from_assets('close-icon.svg', 16, 16);
        $extra_attrs  = '';

        if (!empty($args['attributes']) && is_array($args['attributes'])) {
            foreach ($args['attributes'] as $name => $value) {
                $extra_attrs .= sprintf(' %s="%s"', esc_attr($name), esc_attr((string) $value));
            }
        }

        $path = CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shared/views/modal-close-button.php';
        if (!is_readable($path)) {
            return;
        }

        include $path;
    }
}
