<?php
/**
 * שירות איסוף נתונים לטבלת מרפאות הרופא (קשרי JetEngine 201 / 184 / 185).
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_User_Doctor_Clinics_Table_Data
 *
 * משחזר מידע פר-מרפאה ופר-לו\"ז משורת קשר כפי מתואר בשורטקוד המקורי:
 * r201 קליניקה→רופא, r184 קליניקה→schedule, r185 רופא (parent) → schedules (children) ביחס one-to-many.
 */
class Clinic_User_Doctor_Clinics_Table_Data {

    public const REL_CLINIC_DOCTOR    = 201;
    public const REL_CLINIC_SCHEDULE  = 184;
    public const REL_DOCTOR_SCHEDULE  = 185;

    /**
     * שם טבלת קשר JetEngine שנמצאה בבסיס הנתונים (אם קיימת).
     *
     * @var string
     */
    private $resolved_rel_table = '';

    /**
     * אובייקט ניפוי עם מטא זמני (נטען ב-JavaScript לפי ההגדרות).
     *
     * @var array<string, mixed>
     */
    private $last_debug = array();

    /**
     * שליפת שורות מרפאה + תקציר סטטוס לו\"ז למזהה רופא CPT.
     *
     * כל תוצאה היא רשומה associative עבור ה-view.
     *
     * @param int $doctor_id מזהה פוסט `doctors`.
     * @param bool $include_debug האם למלא `$this->last_debug` עם מידע לדיבוג בדפדפן בלבד.
     * @return array<int, array<string, mixed>>
     */
    public function get_rows_for_doctor($doctor_id, $include_debug = false) {
        $this->last_debug = array();

        $doctor_id = absint($doctor_id);
        if ($doctor_id <= 0) {
            if ($include_debug) {
                $this->last_debug = array(
                    'reason'   => 'invalid_doctor_id',
                    'doctorId' => $doctor_id,
                );
            }

            return array();
        }

        global $wpdb;
        $posts = $wpdb->posts;
        $table = $this->get_relations_table();

        if ($table === '') {
            if ($include_debug) {
                $this->last_debug = array(
                    'reason'         => 'no_jet_relations_table',
                    'doctorId'       => $doctor_id,
                    'triedPrefixes'  => array(
                        $wpdb->prefix . 'jet_rel_default',
                    ),
                );
            }

            return array();
        }

        $sql = "
			SELECT
				c.ID AS clinic_id,
				c.post_title AS clinic_name,
				sch.ID AS schedule_id
			FROM {$posts} AS c
			INNER JOIN {$table} AS r201 ON r201.rel_id = %d
				AND r201.parent_object_id = c.ID
				AND r201.child_object_id = %d
			LEFT JOIN (
				SELECT
					r184.parent_object_id AS clinic_id_inner,
					r184.child_object_id AS schedule_id_inner
				FROM {$table} AS r184
				INNER JOIN {$table} AS r185 ON r185.rel_id = %d
					AND r185.child_object_id = r184.child_object_id
					AND r185.parent_object_id = %d
				WHERE r184.rel_id = %d
			) AS link ON link.clinic_id_inner = c.ID
			LEFT JOIN {$posts} AS sch ON sch.ID = link.schedule_id_inner
				AND sch.post_type = 'schedules'
			WHERE c.post_type = 'clinics'
				AND c.post_status = 'publish'
			ORDER BY c.post_title ASC, sch.ID ASC
		";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- יחס טבלה דינמי אחרי אימות שם טבלה; יתר הפליחה כמספרי שלם.
        $prepared = $wpdb->prepare(
            $sql,
            self::REL_CLINIC_DOCTOR,
            $doctor_id,
            self::REL_DOCTOR_SCHEDULE,
            $doctor_id,
            self::REL_CLINIC_SCHEDULE
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $raw = $wpdb->get_results($prepared, ARRAY_A);

        if (!is_array($raw)) {
            $raw = array();
        }

        if ($include_debug) {
            $this->last_debug = array(
                'doctorId'      => $doctor_id,
                'relationsTable'=> $table,
                'relations'     => array(
                    'clinic_doctor'   => self::REL_CLINIC_DOCTOR,
                    'clinic_schedule' => self::REL_CLINIC_SCHEDULE,
                    'doctor_schedule' => self::REL_DOCTOR_SCHEDULE,
                ),
                'rowCountRaw' => count($raw),
            );
        }

        $built = array();
        foreach ($raw as $idx => $row) {
            $clinic_id   = isset($row['clinic_id']) ? absint($row['clinic_id']) : 0;
            $clinic_name = isset($row['clinic_name']) ? (string) $row['clinic_name'] : '';
            if ($clinic_id <= 0) {
                continue;
            }

            $schedule_id = isset($row['schedule_id']) ? absint($row['schedule_id']) : 0;

            $working_days_readable = '';
            $doctor_connect_url    = '';
            $status_kind           = 'none';
            $badge_label           = 'לא הוגדר יומן';

            if ($schedule_id <= 0) {
                $status_kind = 'none';
                $badge_label = 'לא הוגדר יומן';
            } else {
                $proxy_status = Clinic_Queue_Helpers::resolve_scheduler_proxy_status(
                    get_post_meta($schedule_id, 'scheduler_status_in_proxy', true)
                );
                $status_kind           = $proxy_status['status'];
                $badge_label           = $proxy_status['label'];
                $working_days_readable = self::format_working_days_labels($schedule_id);

                if ('pending' === $status_kind) {
                    $url = get_post_meta($schedule_id, 'doctor_connect_url', true);
                    if (is_string($url)) {
                        $url = trim($url);
                    } else {
                        $url = '';
                    }
                    if ($url !== '') {
                        $doctor_connect_url = esc_url_raw($url);
                    }
                }
            }

            $open_appointments_count = null;
            if ('active' === $status_kind && $schedule_id > 0) {
                $open_appointments_count = $this->count_open_appointments($schedule_id);
            }

            $built[] = array(
                'idx'                     => $idx,
                'clinic_id'               => $clinic_id,
                'clinic_title'             => html_entity_decode($clinic_name, ENT_QUOTES, 'UTF-8'),
                'clinic_address'          => (string) get_post_meta($clinic_id, 'clinic_address', true),
                'schedule_id'             => $schedule_id,
                'working_days_text'       => $working_days_readable,
                'status_kind'             => $status_kind,
                'badge_label'             => $badge_label,
                'doctor_connect_url'      => $doctor_connect_url,
                'open_appointments_count' => $open_appointments_count,
            );

            if ($include_debug && $schedule_id > 0) {
                $this->append_row_debug_snapshot(
                    array(
                        'clinic_id'            => $clinic_id,
                        'schedule_id'          => $schedule_id,
                        'proxy_status'         => $status_kind,
                        'working_days_meta_raw'=> self::peek_working_days_meta($schedule_id),
                    )
                );
            }
        }

        return $built;
    }

    /**
     * נתוני דיבוג אחרונה (לקונסול בדפדפן בלבד).
     *
     * @return array<string, mixed>
     */
    public function get_last_debug() {
        return $this->last_debug;
    }

    /**
     * ספירת תורים עתידיים עבור לו"ז פעיל.
     *
     * השדה `appointment_datetime` הוא VARCHAR בפורמט ISO 8601 עם UTC (`YYYY-MM-DDTHH:mmZ`).
     * השוואה לקסיקוגרפית תקפה כי הפורמט ניתן למיון אלפביתי.
     *
     * @param int $schedule_id מזהה פוסט לו"ז.
     * @return int מספר התורים הפתוחים (עתידיים).
     */
    private function count_open_appointments($schedule_id) {
        $schedule_id = absint($schedule_id);
        if ($schedule_id <= 0) {
            return 0;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'clinic_queue_appointments';
        $now_utc = gmdate('Y-m-d\TH:i\Z');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE wp_schedule_id = %d AND appointment_datetime > %s",
                $schedule_id,
                $now_utc
            )
        );

        return $count !== null ? absint($count) : 0;
    }

    /**
     * מזהה את טבלת הקשר הבסיסית של JetEngine.
     *
     * @return string שם טבלה מלא או מחרוזת ריקה.
     */
    private function get_relations_table() {
        if ($this->resolved_rel_table !== '') {
            return $this->resolved_rel_table;
        }

        global $wpdb;

        // JetEngine משתמש בדרך כלל ב-jet_rel_default (תלוי גירסת תוסף/הגדרות).
        $candidate = $wpdb->prefix . 'jet_rel_default';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.ShowTables
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate));

        $this->resolved_rel_table = ($found === $candidate) ? $candidate : '';

        return $this->resolved_rel_table;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function append_row_debug_snapshot(array $snapshot) {
        if (!isset($this->last_debug['rows']) || !is_array($this->last_debug['rows'])) {
            $this->last_debug['rows'] = array();
        }
        $this->last_debug['rows'][] = $snapshot;
    }

    /**
     * ערך meta brute לדיבוג (ללא פרשנות).
     *
     * @param int $schedule_id
     * @return mixed
     */
    private static function peek_working_days_meta($schedule_id) {
        $schedule_id = absint($schedule_id);
        if ($schedule_id <= 0) {
            return null;
        }

        return get_post_meta($schedule_id, 'working_days', true);
    }

    /**
     * הפקת טקסט ימים מטא `working_days` לפוסט לו\"ז.
     *
     * @param int $schedule_id
     * @return string
     */
    private static function format_working_days_labels($schedule_id) {
        $schedule_id = absint($schedule_id);
        if ($schedule_id <= 0) {
            return '';
        }

        $raw = get_post_meta($schedule_id, 'working_days', true);
        if ($raw === '' || $raw === null) {
            return '';
        }

        if (is_string($raw)) {
            $maybe = maybe_unserialize($raw);
            $raw = is_array($maybe) ? $maybe : preg_split('/\s*,\s*/', trim($maybe));
        }

        if (!is_array($raw)) {
            return '';
        }

        $map_slug_to_long = array();
        foreach (Clinic_Schedule_Form_Manager::get_working_days_meta_mapping() as $day_key => $slug) {
            $days_human = Clinic_Schedule_Form_Manager::get_days_of_week();
            $label_long = isset($days_human[$day_key]) ? $days_human[$day_key] : $slug;

            /* תצוגת טבלה מקוצרת: אות יום בסגנון אלפבית בעברית (א'-ש'). */
            $map_slug_to_long[ $slug ] = self::shorten_hebrew_day_label($label_long);
        }

        $labels = array();
        foreach ($raw as $item) {
            $slug = is_string($item) ? strtolower(trim($item)) : '';
            if ($slug === '') {
                continue;
            }

            $labels[] = isset($map_slug_to_long[$slug])
                ? $map_slug_to_long[$slug]
                : $slug;
        }

        return implode(', ', array_unique(array_filter($labels)));
    }

    /**
     * @param string $label לדוגמה: "יום ראשון".
     * @return string קירוב ל- "א'" וכדומה במידת האפשר.
     */
    private static function shorten_hebrew_day_label($label) {
        $normalized = preg_replace('/\s+/', ' ', trim(wp_strip_all_tags((string) $label)));
        if ($normalized === '') {
            return '';
        }

        switch ($normalized) {
            case 'יום ראשון':
                return 'א׳';
            case 'יום שני':
                return 'ב׳';
            case 'יום שלישי':
                return 'ג׳';
            case 'יום רביעי':
                return 'ד׳';
            case 'יום חמישי':
                return 'ה׳';
            case 'יום שישי':
                return 'ו׳';
            case 'יום שבת':
                return 'ש׳';
            default:
                return $normalized;
        }
    }
}
