<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: עדכון הגדרות יומן (ימים, שעות, טיפולים) — user-schedules-table edit modal.
 *
 * Action: clinic_queue_update_schedule_settings
 * Nonce:  clinic_queue_update_schedule_settings
 *
 * POST params:
 *   schedule_id    (int)    — מזהה פוסט schedules
 *   schedule_data  (JSON)   — { days, treatments }
 *
 * Returns JSON:
 *   success:
 *     message          string
 *     days_updated     bool     — האם ימים/שעות עודכנו ב-WP
 *     proxy_needed     bool     — האם נדרש עדכון פרוקסי (Google + מחובר)
 *     proxy_schedule_id int    — מזהה פרוקסי לשליחה ב-REST
 *     wp_schedule_id   int
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Update_Schedule_Settings {

    /**
     * Callback: wp_ajax_clinic_queue_update_schedule_settings
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_update_schedule_settings', 'nonce');

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

        $schedule_data_json = isset($_POST['schedule_data']) ? wp_unslash($_POST['schedule_data']) : '';
        $schedule_data      = json_decode($schedule_data_json, true);

        if (!is_array($schedule_data)) {
            wp_send_json_error(array('message' => 'נתוני עדכון לא תקינים.'));
            return;
        }

        $schedule_type = get_post_meta($schedule_id, 'schedule_type', true) ?: 'google';
        $days_updated  = false;

        // עדכון ימים/שעות — רק עבור Google (Clinix: שעות קבועות במערכת המקור)
        if ($schedule_type === 'google' && !empty($schedule_data['days']) && is_array($schedule_data['days'])) {
            self::update_days_meta($schedule_id, $schedule_data['days']);
            $days_updated = true;
        }

        // עדכון טיפולים (לשניהם)
        if (!empty($schedule_data['treatments']) && is_array($schedule_data['treatments'])) {
            self::update_treatments_meta(
                $schedule_id,
                $schedule_data['treatments'],
                get_post_meta($schedule_id, 'clinic_id', true),
                get_post_meta($schedule_id, 'doctor_id', true)
            );
        }

        // בדיקת צורך בעדכון פרוקסי
        $proxy_schedule_id = absint(get_post_meta($schedule_id, 'proxy_schedule_id', true));
        $is_connected      = (bool) get_post_meta($schedule_id, 'proxy_connected', true);
        $proxy_needed      = $days_updated && $is_connected && $proxy_schedule_id > 0;

        wp_send_json_success(array(
            'message'          => 'הגדרות היומן עודכנו בהצלחה',
            'days_updated'     => $days_updated,
            'proxy_needed'     => $proxy_needed,
            'proxy_schedule_id' => $proxy_schedule_id,
            'wp_schedule_id'   => $schedule_id,
        ));
    }

    /**
     * עדכון מטא ימים ו-working_days.
     *
     * @param int   $schedule_id
     * @param array $days_data { day_key: [{start_time, end_time}] }
     */
    private static function update_days_meta($schedule_id, array $days_data) {
        $all_day_keys = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');

        // מחיקת ימים קיימים שאינם בבחירה החדשה
        foreach ($all_day_keys as $day_key) {
            if (!array_key_exists($day_key, $days_data)) {
                delete_post_meta($schedule_id, $day_key);
            }
        }

        // שמירת ימים שנבחרו
        foreach ($days_data as $day_key => $time_ranges) {
            if (!in_array($day_key, $all_day_keys, true) || !is_array($time_ranges)) {
                continue;
            }
            $sanitized = array();
            foreach ($time_ranges as $range) {
                $start = isset($range['start_time']) ? sanitize_text_field($range['start_time']) : '';
                $end   = isset($range['end_time'])   ? sanitize_text_field($range['end_time'])   : '';
                if ('' !== $start && '' !== $end) {
                    $sanitized[] = array(
                        'start_time' => $start,
                        'end_time'   => $end,
                    );
                }
            }
            if (!empty($sanitized)) {
                update_post_meta($schedule_id, sanitize_key($day_key), $sanitized);
            }
        }

        // working_days (תצוגה)
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/clinic-schedule-setup-form/managers/class-schedule-form-manager.php';
        $working_days = Clinic_Schedule_Form_Manager::get_working_days_meta_values($days_data);
        if (!empty($working_days)) {
            update_post_meta($schedule_id, 'working_days', $working_days);
        } else {
            delete_post_meta($schedule_id, 'working_days');
        }
    }

    /**
     * עדכון מטא treatments ומונחי טקסונומיה.
     *
     * @param int    $schedule_id
     * @param array  $treatments_data
     * @param mixed  $clinic_id
     * @param mixed  $doctor_id
     */
    private static function update_treatments_meta($schedule_id, array $treatments_data, $clinic_id, $doctor_id) {
        $sanitized = array();
        foreach ($treatments_data as $t) {
            if (!is_array($t)) {
                continue;
            }
            $sanitized[] = array(
                'clinix_treatment_id'   => isset($t['clinix_treatment_id'])   ? sanitize_text_field($t['clinix_treatment_id'])   : '',
                'clinix_treatment_name' => isset($t['clinix_treatment_name']) ? sanitize_text_field($t['clinix_treatment_name']) : '',
                'treatment_type'        => isset($t['treatment_type'])        ? absint($t['treatment_type'])                     : 0,
                'cost'                  => isset($t['cost'])                  ? absint($t['cost'])                               : 0,
                'duration'              => isset($t['duration'])              ? absint($t['duration'])                           : 0,
            );
        }

        if (!empty($sanitized)) {
            update_post_meta($schedule_id, 'treatments', $sanitized);
        }

        // עדכון טקסונומיה treatment_types
        $taxonomy = 'treatment_types';
        if (taxonomy_exists($taxonomy)) {
            $portal_ids = array();
            foreach ($sanitized as $t) {
                $tid = isset($t['treatment_type']) ? absint($t['treatment_type']) : 0;
                if ($tid > 0) {
                    $portal_ids[] = $tid;
                }
            }
            $portal_ids = array_unique($portal_ids);
            wp_set_object_terms($schedule_id, $portal_ids, $taxonomy);

            // עדכון מרפאה
            $clinic_int = !empty($clinic_id) && is_numeric($clinic_id) ? intval($clinic_id) : 0;
            if ($clinic_int > 0 && get_post_type($clinic_int) === 'clinics' && !empty($portal_ids)) {
                $existing    = wp_get_object_terms($clinic_int, $taxonomy);
                $existing_ids = is_wp_error($existing) ? array() : wp_list_pluck($existing, 'term_id');
                wp_set_object_terms($clinic_int, array_unique(array_merge($existing_ids, $portal_ids)), $taxonomy);
            }

            // עדכון רופא
            $doctor_int = !empty($doctor_id) && is_numeric($doctor_id) ? intval($doctor_id) : 0;
            if ($doctor_int > 0 && get_post_type($doctor_int) === 'doctors' && !empty($portal_ids)) {
                $existing    = wp_get_object_terms($doctor_int, $taxonomy);
                $existing_ids = is_wp_error($existing) ? array() : wp_list_pluck($existing, 'term_id');
                wp_set_object_terms($doctor_int, array_unique(array_merge($existing_ids, $portal_ids)), $taxonomy);
            }
        }

        // עדכון טקסונומיה specialties (דרך treatment → specialty)
        if (class_exists('Clinic_Queue_Specialty_Taxonomy')) {
            $spec_taxonomy = Clinic_Queue_Specialty_Taxonomy::get_specialties_taxonomy();
            if (taxonomy_exists($spec_taxonomy)) {
                $specialty_ids = array();
                foreach ($sanitized as $t) {
                    $tid = isset($t['treatment_type']) ? absint($t['treatment_type']) : 0;
                    if ($tid > 0) {
                        $sid = Clinic_Queue_Specialty_Taxonomy::get_specialty_id_of_treatment($tid);
                        if ($sid > 0) {
                            $specialty_ids[] = $sid;
                        }
                    }
                }
                $specialty_ids = array_unique(array_values($specialty_ids));
                if (!empty($specialty_ids)) {
                    wp_set_object_terms($schedule_id, $specialty_ids, $spec_taxonomy);
                }
            }
        }
    }

    /**
     * בדיקת הרשאת עריכה.
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
