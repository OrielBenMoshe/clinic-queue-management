<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Save clinic schedule (schedule form shortcode)
 * Creates schedule post, meta, treatments, relations.
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Save_Schedule {

    /**
     * Callback for wp_ajax_save_clinic_schedule
     */
    public static function handle() {
        check_ajax_referer('save_clinic_schedule', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
            return;
        }

        $schedule_data_json = isset($_POST['schedule_data']) ? wp_unslash($_POST['schedule_data']) : '';
        $schedule_data = json_decode($schedule_data_json, true);

        if (!$schedule_data) {
            wp_send_json_error('Invalid schedule data');
            return;
        }

        if (empty($schedule_data['days']) || !is_array($schedule_data['days'])) {
            wp_send_json_error('No working days provided');
            return;
        }

        if (empty($schedule_data['treatments']) || !is_array($schedule_data['treatments'])) {
            wp_send_json_error('No treatments provided');
            return;
        }

        $action_type = isset($schedule_data['action_type']) ? sanitize_text_field($schedule_data['action_type']) : '';
        if ($action_type === 'clinix') {
            if (empty($schedule_data['selected_calendar_id'])) {
                wp_send_json_error('חסר יומן מקור. אנא בחר יומן.');
                return;
            }
        } else {
            if (empty($schedule_data['doctor_id']) && empty($schedule_data['manual_calendar_name'])) {
                wp_send_json_error('חובה לבחור רופא או להזין שם יומן');
                return;
            }
        }

        $post_title_suffix = self::build_schedule_post_title($schedule_data, $action_type);

        $post_data = array(
            'post_type' => 'schedules',
            'post_title' => $post_title_suffix,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        );

        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create schedule post: ' . $post_id->get_error_message());
            return;
        }

        self::save_schedule_meta($post_id, $schedule_data, $action_type);

        $sanitized_treatments = self::save_treatments_meta($post_id, $schedule_data);

        self::assign_portal_treatment_terms_to_schedule_clinic_doctor(
            $post_id,
            $sanitized_treatments,
            isset($schedule_data['clinic_id']) ? $schedule_data['clinic_id'] : '',
            isset($schedule_data['doctor_id']) ? $schedule_data['doctor_id'] : ''
        );

        $relations_result = self::create_scheduler_relations($post_id);
        $relations_ok = is_array($relations_result) && isset($relations_result['success']) ? $relations_result['success'] : false;

        wp_send_json_success(array(
            'message' => 'Schedule saved successfully',
            'post_id' => $post_id,
            'scheduler_id' => $post_id,
            'post_title' => $post_title_suffix,
            'relations_created' => $relations_ok
        ));
    }

    /**
     * Build post title for schedule (clinic name, doctor/calendar name, Clinix ID prefix)
     *
     * @param array  $schedule_data Decoded schedule form data
     * @param string $action_type   'clinix' or 'google'
     * @return string
     */
    private static function build_schedule_post_title($schedule_data, $action_type) {
        $has_clinic_or_doctor = !empty($schedule_data['doctor_id']) || !empty($schedule_data['manual_calendar_name']);
        $drweb_calendar_id = isset($schedule_data['selected_calendar_id']) ? sanitize_text_field($schedule_data['selected_calendar_id']) : '';

        if ($has_clinic_or_doctor) {
            $post_title_suffix = 'יומן 🏥 ';
            if (!empty($schedule_data['clinic_id'])) {
                $clinic = get_post($schedule_data['clinic_id']);
                $clinic_name = $clinic ? html_entity_decode($clinic->post_title, ENT_QUOTES, 'UTF-8') : 'מרפאה #' . $schedule_data['clinic_id'];
            } else {
                $clinic_name = 'לא ידוע';
            }
            $post_title_suffix .= $clinic_name . ' | ';
            if (!empty($schedule_data['doctor_id'])) {
                $doctor = get_post($schedule_data['doctor_id']);
                $doctor_name = $doctor ? html_entity_decode($doctor->post_title, ENT_QUOTES, 'UTF-8') : 'רופא #' . $schedule_data['doctor_id'];
                $post_title_suffix .= '👨‍⚕️ ' . $doctor_name;
            } elseif (!empty($schedule_data['manual_calendar_name'])) {
                $post_title_suffix .= '📅 ' . sanitize_text_field($schedule_data['manual_calendar_name']);
            }
        } elseif ($action_type === 'clinix' && $drweb_calendar_id !== '') {
            $post_title_suffix = 'יומן קליניקס';
        } else {
            $post_title_suffix = 'יומן 🏥 לא ידוע';
        }

        if ($action_type === 'clinix' && $drweb_calendar_id !== '') {
            $post_title_suffix = '🆔 ' . $drweb_calendar_id . ' | ' . $post_title_suffix;
        }

        return $post_title_suffix;
    }

    /**
     * Save all schedule post meta (type, clinic, doctor, days, working_days, clinix/google fields)
     *
     * @param int    $post_id       Schedule post ID
     * @param array  $schedule_data Decoded schedule form data
     * @param string $action_type   'clinix' or 'google'
     */
    private static function save_schedule_meta($post_id, $schedule_data, $action_type) {
        $schedule_type = isset($schedule_data['action_type']) ? sanitize_text_field($schedule_data['action_type']) : '';
        if (!in_array($schedule_type, array('clinix', 'google'), true)) {
            $schedule_type = 'clinix';
        }

        update_post_meta($post_id, 'schedule_type', $schedule_type);
        update_post_meta($post_id, 'clinic_id', isset($schedule_data['clinic_id']) ? sanitize_text_field($schedule_data['clinic_id']) : '');
        update_post_meta($post_id, 'doctor_id', isset($schedule_data['doctor_id']) ? sanitize_text_field($schedule_data['doctor_id']) : '');

        if ($action_type === 'clinix') {
            update_post_meta($post_id, 'clinix_source_calendar_id', sanitize_text_field($schedule_data['selected_calendar_id']));
            if (!empty($schedule_data['add_api'])) {
                update_post_meta($post_id, 'clinix_api_token', sanitize_text_field($schedule_data['add_api']));
            }
            $clinix_proxy_schedule_id = isset($schedule_data['selected_calendar_id']) ? sanitize_text_field($schedule_data['selected_calendar_id']) : '';
            if ($clinix_proxy_schedule_id !== '') {
                update_post_meta($post_id, 'proxy_schedule_id', $clinix_proxy_schedule_id);
                update_post_meta($post_id, 'proxy_connected', true);
                update_post_meta($post_id, 'proxy_connected_at', current_time('mysql'));
                update_post_meta($post_id, 'doctor_online_proxy_connected', true);
            }
        }

        if (!empty($schedule_data['manual_calendar_name'])) {
            $manual_name = sanitize_text_field($schedule_data['manual_calendar_name']);
            update_post_meta($post_id, 'manual_calendar_name', $manual_name);
            update_post_meta($post_id, 'schedule_name', $manual_name);
        }

        foreach ($schedule_data['days'] as $day => $time_ranges) {
            if (!is_array($time_ranges)) {
                continue;
            }
            $sanitized_ranges = array();
            foreach ($time_ranges as $range) {
                if (isset($range['start_time']) && isset($range['end_time'])) {
                    $sanitized_ranges[] = array(
                        'start_time' => sanitize_text_field($range['start_time']),
                        'end_time' => sanitize_text_field($range['end_time'])
                    );
                }
            }
            if (!empty($sanitized_ranges)) {
                update_post_meta($post_id, sanitize_key($day), $sanitized_ranges);
            }
        }

        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/schedule-form/managers/class-schedule-form-manager.php';
        $working_days_values = Clinic_Schedule_Form_Manager::get_working_days_meta_values($schedule_data['days']);
        if (!empty($working_days_values)) {
            update_post_meta($post_id, 'working_days', $working_days_values);
        }
    }

    /**
     * Save treatments repeater meta; returns sanitized array for taxonomy assignment
     *
     * @param int   $post_id       Schedule post ID
     * @param array $schedule_data Decoded schedule form data
     * @return array Sanitized treatments
     */
    private static function save_treatments_meta($post_id, $schedule_data) {
        $sanitized_treatments = array();
        foreach ($schedule_data['treatments'] as $treatment) {
            $sanitized_treatments[] = array(
                'clinix_treatment_name' => isset($treatment['clinix_treatment_name']) ? sanitize_text_field($treatment['clinix_treatment_name']) : '',
                'clinix_treatment_id'   => isset($treatment['clinix_treatment_id']) ? sanitize_text_field($treatment['clinix_treatment_id']) : '',
                'treatment_type'        => !empty($treatment['treatment_type']) ? absint($treatment['treatment_type']) : 0,
                'cost'                  => isset($treatment['cost']) ? absint($treatment['cost']) : 0,
                'duration'              => isset($treatment['duration']) ? absint($treatment['duration']) : 0
            );
        }
        if (!empty($sanitized_treatments)) {
            update_post_meta($post_id, 'treatments', $sanitized_treatments);
        }
        return $sanitized_treatments;
    }

    /**
     * Assign treatment_types terms to schedule, clinic and doctor
     *
     * @param int   $schedule_id        Schedule post ID
     * @param array $sanitized_treatments Treatments with treatment_type (term ID)
     * @param mixed $clinic_id          Clinic post ID
     * @param mixed $doctor_id          Doctor post ID
     */
    private static function assign_portal_treatment_terms_to_schedule_clinic_doctor($schedule_id, $sanitized_treatments, $clinic_id, $doctor_id) {
        $taxonomy = 'treatment_types';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $portal_treatment_ids = array();
        foreach ($sanitized_treatments as $t) {
            $tid = isset($t['treatment_type']) ? absint($t['treatment_type']) : 0;
            if ($tid > 0) {
                $portal_treatment_ids[] = $tid;
            }
        }
        $portal_treatment_ids = array_unique($portal_treatment_ids);
        if (empty($portal_treatment_ids)) {
            return;
        }

        wp_set_object_terms($schedule_id, $portal_treatment_ids, $taxonomy);

        $clinic_id_int = !empty($clinic_id) && is_numeric($clinic_id) ? intval($clinic_id) : 0;
        if ($clinic_id_int > 0 && get_post_type($clinic_id_int) === 'clinics') {
            $existing = wp_get_object_terms($clinic_id_int, $taxonomy);
            $existing_ids = is_wp_error($existing) ? array() : wp_list_pluck($existing, 'term_id');
            $merged = array_unique(array_merge($existing_ids, $portal_treatment_ids));
            wp_set_object_terms($clinic_id_int, $merged, $taxonomy);
        }

        $doctor_id_int = !empty($doctor_id) && is_numeric($doctor_id) ? intval($doctor_id) : 0;
        if ($doctor_id_int > 0 && get_post_type($doctor_id_int) === 'doctors') {
            $existing = wp_get_object_terms($doctor_id_int, $taxonomy);
            $existing_ids = is_wp_error($existing) ? array() : wp_list_pluck($existing, 'term_id');
            $merged = array_unique(array_merge($existing_ids, $portal_treatment_ids));
            wp_set_object_terms($doctor_id_int, $merged, $taxonomy);
        }
    }

    /**
     * Create JetEngine relations for schedule (clinic, doctor)
     *
     * @param int $post_id Schedule post ID
     * @return array { success: bool }
     */
    private static function create_scheduler_relations($post_id) {
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/services/class-relations-service.php';
        $relations_service = Relations_Service::get_instance();
        return $relations_service->create_scheduler_relations($post_id);
    }
}
