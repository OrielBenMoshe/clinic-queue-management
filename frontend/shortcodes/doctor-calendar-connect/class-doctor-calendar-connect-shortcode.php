<?php
/**
 * Doctor Calendar Connect Shortcode Class
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clinic_Doctor_Calendar_Connect_Shortcode {

    /**
     * Singleton instance.
     *
     * @var Clinic_Doctor_Calendar_Connect_Shortcode|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Clinic_Doctor_Calendar_Connect_Shortcode
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_shortcode('clinic_doctor_calendar_connect', array($this, 'render_shortcode'));
    }

    /**
     * Render shortcode output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode($atts = array()) {
        $this->enqueue_assets();

        $data = $this->prepare_data();
        ob_start();
        include __DIR__ . '/views/doctor-calendar-connect-html.php';

        return ob_get_clean();
    }

    /**
     * Enqueue required assets.
     *
     * @return void
     */
    private function enqueue_assets() {
        static $enqueued = false;
        if ($enqueued) {
            return;
        }

        wp_enqueue_style(
            'clinic-queue-main',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'doctor-calendar-connect-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/doctor-calendar-connect.css',
            array('clinic-queue-main'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_script(
            'clinic-schedule-form-utils',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/schedule-form/js/modules/schedule-form-utils.js',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'clinic-schedule-form-google-auth',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/schedule-form/js/modules/schedule-form-google-auth.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'clinic-schedule-form-calendar-list',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/schedule-form/js/modules/schedule-form-calendar-list.js',
            array('clinic-schedule-form-utils'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'clinic-doctor-connect-core',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/doctor-calendar-connect/js/modules/doctor-connect-core.js',
            array('jquery', 'clinic-schedule-form-utils', 'clinic-schedule-form-google-auth', 'clinic-schedule-form-calendar-list'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'clinic-doctor-connect-reject',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/doctor-calendar-connect/js/modules/doctor-connect-reject.js',
            array('clinic-doctor-connect-core'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/config/google-credentials.php';

        wp_localize_script('clinic-doctor-connect-core', 'doctorConnectData', array(
            'restUrl' => rest_url('clinic-queue/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'schedulerId' => isset($_GET['scheduler_id']) ? absint($_GET['scheduler_id']) : 0,
            'accessToken' => isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '',
            'googleClientId' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
            'googleScopes' => defined('GOOGLE_CALENDAR_SCOPES') ? GOOGLE_CALENDAR_SCOPES : '',
        ));

        $enqueued = true;
    }

    /**
     * Prepare template data.
     *
     * @return array
     */
    private function prepare_data() {
        $icon_svg = '';
        if (class_exists('Clinic_Schedule_Form_Manager')) {
            $icon_svg = Clinic_Schedule_Form_Manager::load_icon_from_assets('calendar-green-image.svg', 120, 120);
        }

        if (empty($icon_svg)) {
            $icon_path = CLINIC_QUEUE_MANAGEMENT_PATH . 'assets/images/icons/calendar-green-image.svg';
            if (is_readable($icon_path)) {
                $loaded_svg = file_get_contents($icon_path);
                if ($loaded_svg !== false) {
                    $icon_svg = trim($loaded_svg);
                }
            }
        }

        return array(
            'calendar_icon' => $icon_svg,
        );
    }
}
