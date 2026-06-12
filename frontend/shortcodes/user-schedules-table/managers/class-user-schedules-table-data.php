<?php
/**
 * שירות איסוף נתונים לטבלת היומנים של המשתמש — [user_schedules_table].
 *
 * קשרי JetEngine בשימוש:
 * r184 מרפאה (parent) → schedule (child), r185 רופא (parent) → schedules (children).
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_User_Schedules_Table_Data
 *
 * בונה שורת תצוגה לכל פוסט `schedules` שהמשתמש הנוכחי הוא המחבר שלו:
 * שם תצוגה, רופא (תמונה + שם), מרפאות מקושרות, סטטוס, ימי פעילות והתמחויות.
 */
class Clinic_User_Schedules_Table_Data {

    public const REL_CLINIC_SCHEDULE = 184;
    public const REL_DOCTOR_SCHEDULE = 185;

    /**
     * שם טבלת קשר JetEngine שנמצאה בבסיס הנתונים (אם קיימת).
     *
     * @var string
     */
    private $resolved_rel_table = '';

    /**
     * שליפת שורות יומן עבור משתמש מחובר.
     *
     * @param int $user_id מזהה משתמש וורדפרס.
     * @return array<int, array<string, mixed>>
     */
    public function get_rows_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array();
        }

        $schedule_ids = get_posts(
            array(
                'post_type'        => 'schedules',
                'author'           => $user_id,
                'post_status'      => 'any',
                'posts_per_page'   => -1,
                'fields'           => 'ids',
                'suppress_filters' => false,
                'no_found_rows'    => true,
            )
        );

        $rows = array();
        foreach ($schedule_ids as $schedule_id) {
            $rows[] = $this->build_row(absint($schedule_id));
        }

        return $rows;
    }

    /**
     * בניית שורת נתונים אחת לפוסט יומן.
     *
     * @param int $schedule_id מזהה פוסט `schedules`.
     * @return array<string, mixed>
     */
    private function build_row($schedule_id) {
        $schedule_name        = get_post_meta($schedule_id, 'schedule_name', true);
        $manual_calendar_name = get_post_meta($schedule_id, 'manual_calendar_name', true);

        $doctor = $this->resolve_doctor($schedule_id);

        $display_name = $schedule_name ?: $manual_calendar_name ?: $doctor['name'];
        if ('' === (string) $display_name) {
            $display_name = get_the_title($schedule_id) ?: '—';
        }

        $is_active = (bool) get_post_meta($schedule_id, 'doctor_online_proxy_connected', true);

        return array(
            'schedule_id'    => $schedule_id,
            'display_name'   => (string) $display_name,
            'doctor_image'   => $doctor['image'],
            'doctor_url'     => $doctor['url'],
            'clinics_text'   => $this->get_clinics_display($schedule_id),
            'is_active'      => $is_active,
            'status_label'   => $is_active ? 'פעיל' : 'לא פעיל',
            'days_text'      => self::format_working_days($schedule_id),
            'specialties'    => self::get_specialty_names($schedule_id),
        );
    }

    /**
     * שמות המרפאות המקושרות ליומן (קשר 184: מרפאה parent → יומן child).
     *
     * @param int $schedule_id מזהה פוסט יומן.
     * @return string שמות מופרדים בפסיק, או '—' כשאין קשר.
     */
    private function get_clinics_display($schedule_id) {
        $table = $this->get_relations_table();
        if ('' === $table) {
            return '—';
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- שם הטבלה אומת ב-get_relations_table().
        $clinic_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d",
                self::REL_CLINIC_SCHEDULE,
                $schedule_id
            )
        );

        if (empty($clinic_ids)) {
            return '—';
        }

        $names = array();
        foreach ($clinic_ids as $clinic_id) {
            $title = get_the_title(absint($clinic_id));
            if ('' !== (string) $title) {
                $names[] = $title;
            }
        }

        return !empty($names) ? implode(', ', $names) : '—';
    }

    /**
     * זיהוי הרופא של היומן: מטא `doctor_id` תחילה, ואם ריק — קשר 185
     * (רופא parent → יומן child).
     *
     * @param int $schedule_id מזהה פוסט יומן.
     * @return array{id: int, name: string, image: string, url: string}
     */
    private function resolve_doctor($schedule_id) {
        $empty = array(
            'id'    => 0,
            'name'  => '',
            'image' => '',
            'url'   => '',
        );

        $doctor_id = absint(get_post_meta($schedule_id, 'doctor_id', true));

        if ($doctor_id <= 0) {
            $table = $this->get_relations_table();
            if ('' !== $table) {
                global $wpdb;

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- שם הטבלה אומת ב-get_relations_table().
                $doctor_id = absint(
                    $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d LIMIT 1",
                            self::REL_DOCTOR_SCHEDULE,
                            $schedule_id
                        )
                    )
                );
            }
        }

        if ($doctor_id <= 0 || 'doctors' !== get_post_type($doctor_id)) {
            return $empty;
        }

        $image = '';
        $thumb_url = get_the_post_thumbnail_url($doctor_id, 'thumbnail');
        if ($thumb_url) {
            $image = (string) $thumb_url;
        } else {
            $thumb_meta = get_post_meta($doctor_id, 'thumbnail', true);
            if (is_array($thumb_meta) && !empty($thumb_meta['url'])) {
                $image = (string) $thumb_meta['url'];
            } elseif (is_string($thumb_meta) && '' !== $thumb_meta) {
                $image = $thumb_meta;
            }
        }

        $permalink = get_permalink($doctor_id);

        return array(
            'id'    => $doctor_id,
            'name'  => (string) get_the_title($doctor_id),
            'image' => $image,
            'url'   => $permalink ? (string) $permalink : '',
        );
    }

    /**
     * הפקת טקסט ימי פעילות מקוצר (א׳, ב׳...) ממטא `working_days`.
     *
     * @param int $schedule_id מזהה פוסט יומן.
     * @return string
     */
    private static function format_working_days($schedule_id) {
        $raw = get_post_meta($schedule_id, 'working_days', true);

        if (is_string($raw) && '' !== $raw) {
            $maybe = maybe_unserialize($raw);
            $raw   = is_array($maybe) ? $maybe : preg_split('/\s*,\s*/', trim($maybe));
        }

        if (!is_array($raw)) {
            return '—';
        }

        $map = array(
            'sun' => 'א׳',
            'mon' => 'ב׳',
            'tue' => 'ג׳',
            'wed' => 'ד׳',
            'thu' => 'ה׳',
            'fri' => 'ו׳',
            'sat' => 'ש׳',
        );

        $labels = array();
        foreach ($raw as $day) {
            $slug = is_string($day) ? strtolower(trim($day)) : '';
            if (isset($map[$slug])) {
                $labels[] = $map[$slug];
            }
        }

        return !empty($labels) ? implode(', ', $labels) : '—';
    }

    /**
     * שמות מונחי הטקסונומיה `specialties` של היומן.
     *
     * @param int $schedule_id מזהה פוסט יומן.
     * @return array<int, string>
     */
    private static function get_specialty_names($schedule_id) {
        $terms = get_the_terms($schedule_id, 'specialties');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        return array_map('strval', wp_list_pluck($terms, 'name'));
    }

    /**
     * מזהה את טבלת הקשר הבסיסית של JetEngine.
     *
     * @return string שם טבלה מלא או מחרוזת ריקה.
     */
    private function get_relations_table() {
        if ('' !== $this->resolved_rel_table) {
            return $this->resolved_rel_table;
        }

        global $wpdb;

        $candidate = $wpdb->prefix . 'jet_rel_default';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.ShowTables
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate));

        $this->resolved_rel_table = ($found === $candidate) ? $candidate : '';

        return $this->resolved_rel_table;
    }
}
