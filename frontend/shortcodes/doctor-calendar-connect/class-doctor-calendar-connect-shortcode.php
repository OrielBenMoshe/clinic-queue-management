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
        add_action('template_redirect', array($this, 'check_connect_status'));
    }

    /**
     * Render shortcode output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode($atts = array()) {
        $url_params = $this->extract_url_params();

        $this->enqueue_assets($url_params);

        $data = $this->prepare_data($url_params);
        ob_start();
        include __DIR__ . '/views/doctor-calendar-connect-html.php';

        return ob_get_clean();
    }

    /**
     * Fired on template_redirect: verifies the scheduler's doctor_connect_status is 'pending'.
     * If the page doesn't contain the shortcode, or no scheduler_id is present, bail early.
     * If the status is anything other than 'pending', load the WordPress 404 template and exit.
     *
     * @return void
     */
    public function check_connect_status() {
        // Only act on pages that actually contain this shortcode.
        global $post;
        if (
            !is_a($post, 'WP_Post') ||
            !has_shortcode($post->post_content, 'clinic_doctor_calendar_connect')
        ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $scheduler_id = isset($_GET['scheduler_id']) ? absint($_GET['scheduler_id']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($scheduler_id <= 0) {
            return;
        }

        $scheduler_post = get_post($scheduler_id);
        if (!$scheduler_post || $scheduler_post->post_type !== 'schedules') {
            $this->load_not_found();
        }

        $status = (string) get_post_meta($scheduler_id, 'doctor_connect_status', true);
        if ($status !== 'pending') {
            $this->load_not_found();
        }
    }

    /**
     * Load the WordPress 404 template and exit.
     * Uses the standard theme 404.php; falls back to wp_die() if none exists.
     *
     * @return void
     */
    private function load_not_found() {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();

        $template = get_404_template();
        if ($template) {
            include $template;
        } else {
            wp_die(
                esc_html__('הדף המבוקש אינו זמין.', 'clinic-queue'),
                esc_html__('דף לא נמצא', 'clinic-queue'),
                array('response' => 404)
            );
        }
        exit;
    }

    /**
     * Extract and validate URL parameters from the doctor connect link.
     *
     * @return array {
     *     @type int    scheduler_id         Scheduler post ID.
     *     @type string token                Raw access token.
     *     @type string clinic_name          Clinic display name.
     *     @type string calendar_name        Calendar display name.
     *     @type string source_scheduler_id  External source calendar ID.
     *     @type bool   is_valid_sig         Whether the HMAC signature is valid.
     * }
     */
    private function extract_url_params() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $scheduler_id        = isset($_GET['scheduler_id']) ? absint($_GET['scheduler_id']) : 0;
        $token               = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $sig                 = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
        $clinic_name         = isset($_GET['clinic_name']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['clinic_name']))) : '';
        $calendar_name       = isset($_GET['calendar_name']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['calendar_name']))) : '';
        $source_scheduler_id = isset($_GET['source_scheduler_id']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['source_scheduler_id']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $is_valid_sig = false;
        if ($scheduler_id > 0 && $token !== '' && $sig !== '') {
            $expected_sig = hash_hmac('sha256', $scheduler_id . '|' . $token, wp_salt('auth'));
            $is_valid_sig = hash_equals($expected_sig, $sig);
        }

        return array(
            'scheduler_id'         => $scheduler_id, // WordPress post ID (schedules CPT), not proxy or Google calendar ID
            'token'                => $token,
            'clinic_name'          => $clinic_name,
            'calendar_name'        => $calendar_name,
            'source_scheduler_id'  => $source_scheduler_id,
            'is_valid_sig'         => $is_valid_sig,
        );
    }

    /**
     * Enqueue required assets and localize JS data.
     *
     * @param array $url_params Parsed URL parameters from extract_url_params().
     * @return void
     */
    private function enqueue_assets($url_params) {
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
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/clinic-schedule-setup-form/js/modules/schedule-form-utils.js',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'clinic-schedule-form-google-auth',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/clinic-schedule-setup-form/js/modules/schedule-form-google-auth.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'clinic-schedule-form-calendar-list',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/clinic-schedule-setup-form/js/modules/schedule-form-calendar-list.js',
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

        $plugin_settings = class_exists('Clinic_Queue_Plugin_Settings_Service')
            ? Clinic_Queue_Plugin_Settings_Service::get_instance()
            : null;

        wp_localize_script('clinic-doctor-connect-core', 'doctorConnectData', array(
            'restUrl'            => rest_url('clinic-queue/v1'),
            'restNonce'          => wp_create_nonce('wp_rest'),
            'schedulerId'        => $url_params['scheduler_id'],
            'accessToken'        => $url_params['token'],
            'clinicName'         => $url_params['clinic_name'],
            'calendarName'       => $url_params['calendar_name'],
            'sourceSchedulerId'  => isset($url_params['source_scheduler_id']) ? $url_params['source_scheduler_id'] : '',
            'isValidSig'         => $url_params['is_valid_sig'],
            'googleClientId'     => $plugin_settings ? $plugin_settings->get_google_client_id() : '',
            'googleScopes'       => $plugin_settings ? $plugin_settings->get_google_calendar_scopes() : '',
        ));

        $enqueued = true;
    }

    /**
     * Prepare template data from pre-parsed URL parameters.
     *
     * @param array $url_params Parsed URL parameters from extract_url_params().
     * @return array
     */
    private function prepare_data($url_params) {
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
            'calendar_icon'  => $icon_svg,
            'clinic_name'    => $url_params['clinic_name'],
            'calendar_name'  => $url_params['calendar_name'],
            'is_valid_sig'   => $url_params['is_valid_sig'],
            'has_params'     => $url_params['scheduler_id'] > 0 && $url_params['token'] !== '',
        );
    }
}
