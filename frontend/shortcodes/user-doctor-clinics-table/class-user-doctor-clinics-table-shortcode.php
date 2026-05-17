<?php
/**
 * שורטקוד [user_doctor_clinics_table] — טבלת מרפאות ולוחות זמנים של הרופא המחובר.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * רישום שורטקוד, טעינת נכסים ורינדור תצוגת HTML בלבד.
 */
class Clinic_User_Doctor_Clinics_Table_Shortcode {

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * משמש למניעת טעינה כפולה של stylesheet/script בדרך כלל.
     *
     * @var bool
     */
    private static $static_assets_registered = false;

    /**
     * @var Clinic_User_Doctor_Clinics_Table_Data
     */
    private $data_service;

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Clinic_User_Doctor_Clinics_Table_Shortcode constructor.
     */
    private function __construct() {
        $this->data_service = new Clinic_User_Doctor_Clinics_Table_Data();
        add_shortcode('user_doctor_clinics_table', array($this, 'render_shortcode'));
    }

    /**
     * הצגת הטבלה לאחר אימות הרשאות/זיהוי רופא.
     *
     * @param array  $atts    תכונות שורטקוד.
     * @param string $content תוכן מקונן (לא בשימוש).
     * @return string
     */
    public function render_shortcode($atts = array(), $content = '') {
        $atts = shortcode_atts(
            array(
                'doctor_id' => '',
                'debug'     => '0',
            ),
            $atts,
            'user_doctor_clinics_table'
        );

        $debug_enabled = filter_var($atts['debug'], FILTER_VALIDATE_BOOLEAN);

        if (!is_user_logged_in()) {
            $this->enqueue_assets(array(), $debug_enabled);

            return $this->render_view(
                array(
                    'state'         => 'login_required',
                    'rows'          => array(),
                    'doctor_id'     => 0,
                    'debug_enabled' => $debug_enabled,
                )
            );
        }

        $doctor_id = $this->resolve_doctor_id($atts);
        if ($doctor_id <= 0) {
            $this->enqueue_assets(array(), $debug_enabled);

            return $this->render_view(
                array(
                    'state'         => 'no_doctor_profile',
                    'rows'          => array(),
                    'doctor_id'     => 0,
                    'debug_enabled' => $debug_enabled,
                )
            );
        }

        $rows = $this->data_service->get_rows_for_doctor($doctor_id, $debug_enabled);
        $debug_payload = array();
        if ($debug_enabled) {
            $debug_payload = $this->data_service->get_last_debug();
        }

        $this->enqueue_assets($debug_payload, $debug_enabled);

        return $this->render_view(
            array(
                'state'         => 'ready',
                'rows'          => $rows,
                'doctor_id'     => $doctor_id,
                'debug_enabled' => $debug_enabled,
            )
        );
    }

    /**
     * זיהוי מזהה פוסט `doctors` לפי מחבר או מטא (עם הרשאה לכלול דריגה ידנית).
     *
     * @param array $atts מאפייני שורטקוד לאחר סינון.
     * @return int
     */
    private function resolve_doctor_id(array $atts) {
        $allow_override = apply_filters(
            'clinic_queue_user_doctor_clinics_table_allow_doctor_override',
            current_user_can('manage_options')
        );

        if (
            $allow_override
            && isset($atts['doctor_id'])
            && '' !== trim((string) $atts['doctor_id'])
        ) {
            $forced = absint($atts['doctor_id']);
            if ($forced > 0 && 'doctors' === get_post_type($forced)) {
                return $forced;
            }
        }

        $user_id = get_current_user_id();
        $doctor_posts = get_posts(
            array(
                'post_type'        => 'doctors',
                'author'           => $user_id,
                'post_status'      => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page'   => 1,
                'orderby'          => 'modified',
                'order'            => 'DESC',
                'fields'           => 'ids',
                'suppress_filters' => false,
                'no_found_rows'    => true,
            )
        );

        if (!empty($doctor_posts[0])) {
            return absint($doctor_posts[0]);
        }

        $meta_keys = apply_filters(
            'clinic_queue_user_doctor_clinics_table_user_meta_candidates',
            array('linked_doctor_id', 'doctor_post_id')
        );

        foreach ($meta_keys as $meta_key) {
            $maybe = absint(get_user_meta($user_id, sanitize_key((string) $meta_key), true));
            if ($maybe > 0 && 'doctors' === get_post_type($maybe)) {
                return $maybe;
            }
        }

        return 0;
    }

    /**
     * טעינת stylesheet/script ואריזת דיבוג בדפדפן בלבד.
     *
     * @param array<string, mixed> $debug_payload   נתונים עבור console (אופציונלי).
     * @param bool                 $debug_enabled   האם דיבוג JS פעיל.
     * @return void
     */
    private function enqueue_assets(array $debug_payload, $debug_enabled) {
        if (!self::$static_assets_registered) {
            wp_enqueue_style(
                'clinic-queue-base-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
                array(),
                CLINIC_QUEUE_MANAGEMENT_VERSION
            );

            wp_enqueue_style(
                'clinic-queue-user-doctor-clinics-table-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/user-doctor-clinics-table.css',
                array('clinic-queue-base-css'),
                CLINIC_QUEUE_MANAGEMENT_VERSION
            );

            wp_enqueue_script(
                'clinic-queue-user-doctor-clinics-table-js',
                CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/user-doctor-clinics-table/js/user-doctor-clinics-table.js',
                array('jquery'),
                CLINIC_QUEUE_MANAGEMENT_VERSION,
                true
            );

            self::$static_assets_registered = true;
        }

        wp_localize_script(
            'clinic-queue-user-doctor-clinics-table-js',
            'clinicQueueUserDoctorClinicsTable',
            array(
                'debugEnabled' => (bool) $debug_enabled,
                'debugPayload' => $debug_enabled ? $debug_payload : null,
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('clinic_queue_detach_doctor_from_clinic'),
                'freezeNonce'    => wp_create_nonce('clinic_queue_freeze_schedule'),
                'unfreezeNonce'  => wp_create_nonce('clinic_queue_activate_schedule'),
            )
        );
    }

    /**
     * רינדור תבנית ה-HTML הנקיה.
     *
     * @param array<string, mixed> $data מפתחות: state, rows, doctor_id, debug_enabled.
     * @return string
     */
    private function render_view(array $data) {
        ob_start();
        include __DIR__ . '/views/user-doctor-clinics-table-html.php';

        return ob_get_clean();
    }
}
