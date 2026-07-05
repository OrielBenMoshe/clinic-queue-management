<?php
/**
 * שורטקוד [user_schedules_table] — טבלת היומנים של המשתמש המחובר.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * רישום שורטקוד, טעינת נכסים ורינדור תצוגת HTML בלבד.
 */
class Clinic_User_Schedules_Table_Shortcode {

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * משמש למניעת טעינה כפולה של stylesheet/script.
     *
     * @var bool
     */
    private static $static_assets_registered = false;

    /**
     * @var Clinic_User_Schedules_Table_Data
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
     * Clinic_User_Schedules_Table_Shortcode constructor.
     */
    private function __construct() {
        $this->data_service = new Clinic_User_Schedules_Table_Data();
        add_shortcode('user_schedules_table', array($this, 'render_shortcode'));
    }

    /**
     * הצגת טבלת היומנים למשתמש המחובר.
     *
     * @param array  $atts    תכונות שורטקוד (לא בשימוש).
     * @param string $content תוכן מקונן (לא בשימוש).
     * @return string
     */
    public function render_shortcode($atts = array(), $content = '') {
        $this->enqueue_assets();

        if (!is_user_logged_in()) {
            return $this->render_view(
                array(
                    'state' => 'login_required',
                    'rows'  => array(),
                )
            );
        }

        $rows = $this->data_service->get_rows_for_user(get_current_user_id());

        return $this->render_view(
            array(
                'state' => 'ready',
                'rows'  => $rows,
            )
        );
    }

    /**
     * טעינת stylesheet/script והזרקת קונפיגורציית AJAX.
     *
     * @return void
     */
    private function enqueue_assets() {
        if (self::$static_assets_registered) {
            return;
        }

        $js_relative_path = 'frontend/shortcodes/user-schedules-table/js/user-schedules-table.js';
        $js_absolute_path = CLINIC_QUEUE_MANAGEMENT_PATH . $js_relative_path;
        $js_version       = CLINIC_QUEUE_MANAGEMENT_VERSION;
        if (file_exists($js_absolute_path)) {
            $js_version .= '.' . filemtime($js_absolute_path);
        }

        $em_js_relative = 'frontend/shortcodes/user-schedules-table/js/modules/user-schedules-table-edit-modal.js';
        $em_js_absolute = CLINIC_QUEUE_MANAGEMENT_PATH . $em_js_relative;
        $em_js_version  = CLINIC_QUEUE_MANAGEMENT_VERSION;
        if (file_exists($em_js_absolute)) {
            $em_js_version .= '.' . filemtime($em_js_absolute);
        }

        // Select2
        wp_register_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0-rc.0',
            true
        );
        wp_register_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0-rc.0'
        );

        wp_register_style(
            'clinic-queue-confirm-modal-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/confirm-modal.css',
            array('clinic-queue-base-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style('select2');

        wp_enqueue_style(
            'clinic-queue-base-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // jetform-mui-fields ו-schedule-form — שיתוף סגנונות (.day-checkbox, .treatment-row וכו')
        wp_enqueue_style(
            'clinic-queue-jetform-mui',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/jetform-mui-fields.css',
            array('clinic-queue-base-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'schedule-form-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/schedule-form.css',
            array('clinic-queue-base-css', 'select2', 'clinic-queue-jetform-mui', 'clinic-queue-confirm-modal-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'clinic-queue-user-schedules-table-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/user-schedules-table.css',
            array('clinic-queue-base-css', 'schedule-form-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // JS: מודל עריכה (לפני הסקריפט הראשי)
        wp_enqueue_script(
            'clinic-queue-user-schedules-table-edit-modal-js',
            CLINIC_QUEUE_MANAGEMENT_URL . $em_js_relative,
            array('jquery', 'select2'),
            $em_js_version,
            true
        );

        wp_enqueue_script(
            'clinic-queue-user-schedules-table-js',
            CLINIC_QUEUE_MANAGEMENT_URL . $js_relative_path,
            array('jquery', 'clinic-queue-user-schedules-table-edit-modal-js'),
            $js_version,
            true
        );

        wp_localize_script(
            'clinic-queue-user-schedules-table-js',
            'clinicQueueUserSchedulesTable',
            array(
                'ajaxUrl'             => admin_url('admin-ajax.php'),
                'restUrl'             => rest_url('clinic-queue/v1'),
                'restNonce'           => wp_create_nonce('wp_rest'),
                'deleteNonce'         => wp_create_nonce('clinic_queue_delete_schedule'),
                'getDataNonce'        => wp_create_nonce('clinic_queue_get_schedule_data'),
                'updateSettingsNonce' => wp_create_nonce('clinic_queue_update_schedule_settings'),
                'treatmentTypesEndpoint' => rest_url('wp/v2/treatment_types?per_page=100'),
            )
        );

        self::$static_assets_registered = true;
    }

    /**
     * רינדור תבנית ה-HTML הנקיה.
     *
     * @param array<string, mixed> $data מפתחות: state, rows.
     * @return string
     */
    private function render_view(array $data) {
        ob_start();
        include __DIR__ . '/views/user-schedules-table-html.php';

        return ob_get_clean();
    }
}
