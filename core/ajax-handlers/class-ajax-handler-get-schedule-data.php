<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: קבלת נתוני יומן לצורך מודל עריכה (user-schedules-table)
 *
 * Action: clinic_queue_get_schedule_data
 * Nonce:  clinic_queue_get_schedule_data
 *
 * POST params:
 *   schedule_id (int) — מזהה פוסט schedules
 *
 * Returns JSON:
 *   schedule_type       string  'clinix' | 'google'
 *   days                array   { day_key: [{start_time, end_time}] }
 *   treatments          array   [{clinix_treatment_id, clinix_treatment_name, treatment_type, cost, duration}]
 *   proxy_schedule_id      int     מזהה בפרוקסי (0 = לא מחובר)
 *   is_proxy_connected     bool    האם מחובר לפרוקסי
 *   source_credentials_id  string  מזהה credentials (Clinix) — ריק אם לא מוגדר
 *   source_scheduler_id    string  מזהה scheduler (Clinix) — fallback ל-clinix_source_calendar_id
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Get_Schedule_Data {

    /**
     * ימי שבוע לפי סדר.
     */
    private static $day_keys = array(
        'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
    );

    /**
     * Callback: wp_ajax_clinic_queue_get_schedule_data
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_get_schedule_data', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'יש להתחבר למערכת.'));
            return;
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if ($schedule_id <= 0) {
            wp_send_json_error(array('message' => 'מזהה יומן לא תקין.'));
            return;
        }

        $post = get_post($schedule_id);
        if (!$post || $post->post_type !== 'schedules') {
            wp_send_json_error(array('message' => 'יומן לא נמצא.'));
            return;
        }

        if (!self::current_user_can_edit($post)) {
            wp_send_json_error(array('message' => 'אין הרשאה לערוך יומן זה.'));
            return;
        }

        $schedule_type     = get_post_meta($schedule_id, 'schedule_type', true) ?: 'google';
        $proxy_schedule_id = absint(get_post_meta($schedule_id, 'proxy_schedule_id', true));
        $is_connected      = (bool) get_post_meta($schedule_id, 'proxy_connected', true);

        $days       = self::get_days_data($schedule_id);
        $treatments = self::get_treatments_data($schedule_id);

        $source_credentials_id = get_post_meta($schedule_id, 'source_credentials_id', true);
        $source_scheduler_id   = get_post_meta($schedule_id, 'source_scheduler_id', true);
        if ($source_scheduler_id === '' || $source_scheduler_id === false) {
            $source_scheduler_id = get_post_meta($schedule_id, 'clinix_source_calendar_id', true);
        }

        wp_send_json_success(array(
            'schedule_id'           => $schedule_id,
            'schedule_type'         => $schedule_type,
            'days'                  => $days,
            'treatments'            => $treatments,
            'proxy_schedule_id'     => $proxy_schedule_id,
            'is_proxy_connected'    => $is_connected,
            'source_credentials_id' => $source_credentials_id ?: '',
            'source_scheduler_id'   => $source_scheduler_id   ?: '',
        ));
    }

    /**
     * שליפת נתוני ימים ושעות מהמטא של הפוסט.
     *
     * @param int $schedule_id
     * @return array { day_key: [{start_time, end_time}] }
     */
    private static function get_days_data($schedule_id) {
        $days = array();
        foreach (self::$day_keys as $day_key) {
            $raw = get_post_meta($schedule_id, $day_key, true);
            if (empty($raw)) {
                continue;
            }
            $ranges = is_string($raw) ? maybe_unserialize($raw) : $raw;
            if (!is_array($ranges) || empty($ranges)) {
                continue;
            }
            $valid_ranges = array();
            foreach ($ranges as $range) {
                if (!is_array($range)) {
                    continue;
                }
                $start = isset($range['start_time']) ? sanitize_text_field($range['start_time']) : '';
                $end   = isset($range['end_time'])   ? sanitize_text_field($range['end_time'])   : '';
                if ('' !== $start && '' !== $end) {
                    $valid_ranges[] = array(
                        'start_time' => $start,
                        'end_time'   => $end,
                    );
                }
            }
            if (!empty($valid_ranges)) {
                $days[$day_key] = $valid_ranges;
            }
        }
        return $days;
    }

    /**
     * שליפת נתוני טיפולים מהמטא treatments.
     *
     * @param int $schedule_id
     * @return array
     */
    private static function get_treatments_data($schedule_id) {
        $raw = get_post_meta($schedule_id, 'treatments', true);
        if (empty($raw)) {
            return array();
        }
        $treatments = is_string($raw) ? maybe_unserialize($raw) : $raw;
        if (!is_array($treatments)) {
            return array();
        }
        $result = array();
        foreach ($treatments as $t) {
            if (!is_array($t)) {
                continue;
            }
            $result[] = array(
                'clinix_treatment_id'   => isset($t['clinix_treatment_id'])   ? sanitize_text_field($t['clinix_treatment_id'])   : '',
                'clinix_treatment_name' => isset($t['clinix_treatment_name']) ? sanitize_text_field($t['clinix_treatment_name']) : '',
                'treatment_type'        => isset($t['treatment_type'])        ? absint($t['treatment_type'])                     : 0,
                'cost'                  => isset($t['cost'])                  ? absint($t['cost'])                               : 0,
                'duration'              => isset($t['duration'])              ? absint($t['duration'])                           : 0,
            );
        }
        return $result;
    }

    /**
     * בדיקת הרשאת עריכה: מחבר הפוסט, admin, או בעל יומן doctors.
     *
     * @param WP_Post $post
     * @return bool
     */
    private static function current_user_can_edit($post) {
        $user_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            return true;
        }
        if ((int) $post->post_author === $user_id) {
            return true;
        }
        // בעל CPT doctors המקושר ליומן
        $doctor_id = absint(get_post_meta($post->ID, 'doctor_id', true));
        if ($doctor_id > 0) {
            $doctor = get_post($doctor_id);
            if ($doctor && (int) $doctor->post_author === $user_id) {
                return true;
            }
        }
        return false;
    }
}
