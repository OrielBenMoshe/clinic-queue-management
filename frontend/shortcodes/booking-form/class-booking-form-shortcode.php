<?php
/**
 * Booking Form Shortcode Class
 * Main class for [booking_form] shortcode
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Booking_Form_Shortcode
 * Manages the booking form shortcode functionality
 */
class Clinic_Booking_Form_Shortcode {
    
    /** @var string Family members repeater meta key in user meta */
    private const FAMILY_REPEATER_META_KEY = 'family_members';

    /** @var string Primary user ID number meta key */
    private const USER_ID_NUMBER_META_KEY = 'user_id_number';
    
    /**
     * Singleton instance
     * 
     * @var Clinic_Booking_Form_Shortcode
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Booking_Form_Shortcode
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->register_shortcode();
        $this->register_ajax_handlers();
    }
    
    /**
     * Register the shortcode
     */
    private function register_shortcode() {
        add_shortcode('booking_form', array($this, 'render_shortcode'));
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_submit_appointment_ajax', array($this, 'handle_appointment_submission_ajax'));
        add_action('wp_ajax_nopriv_submit_appointment_ajax', array($this, 'handle_appointment_submission_ajax'));
        add_action('wp_ajax_refresh_family_list_html', array($this, 'handle_refresh_family_list_html'));
        add_action('wp_ajax_save_user_id_number', array($this, 'handle_save_user_id_number_ajax'));
    }
    
    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts = array()) {
        // Parse attributes (kept for backward compatibility; no active parameters currently)
        shortcode_atts(array(), $atts, 'booking_form');
        
        // Guest user: login form view (default [mad_login_form]); after login, page refresh shows the booking form.
        if (!is_user_logged_in()) {
            $guest_login_shortcode_string    = $this->get_guest_login_shortcode_string();
            $guest_register_shortcode_string = $this->get_guest_register_shortcode_string();
            $guest_login_html_fragment       = do_shortcode($guest_login_shortcode_string);
            $guest_register_html_fragment    = do_shortcode($guest_register_shortcode_string);
            $this->enqueue_guest_assets(
                array(
                    'loginHtml'    => $guest_login_html_fragment,
                    'registerHtml' => $guest_register_html_fragment,
                )
            );
            $data = array(
                'require_login_register'     => true,
                'appointment_data'           => $this->get_appointment_data_from_query(),
                'guest_login_html_fragment'  => $guest_login_html_fragment,
            );
            ob_start();
            include __DIR__ . '/views/booking-form-html.php';
            return ob_get_clean();
        }

        // Enqueue assets
        $this->enqueue_assets();

        // Prepare data for view
        $data = $this->prepare_data();
        
        // Start output buffering
        ob_start();
        
        // Include the view
        include __DIR__ . '/views/booking-form-html.php';
        
        // Return buffered content
        return ob_get_clean();
    }
    
    /**
     * Enqueue shared CSS for the booking form (logged-in user and guest).
     */
    private function enqueue_booking_form_styles() {
        wp_enqueue_style(
            'clinic-queue-base-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'clinic-queue-forms-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/forms.css',
            array('clinic-queue-base-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'clinic-queue-jetform-mui',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/jetform-mui-fields.css',
            array('clinic-queue-base-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'booking-form-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/booking-form.css',
            array('clinic-queue-base-css', 'clinic-queue-forms-css', 'clinic-queue-jetform-mui'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
    }

    /**
     * Custom date picker assets (shared with family-member popup on booking pages).
     */
    private function enqueue_date_picker_assets() {
        wp_enqueue_style(
            'clinic-queue-date-picker-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/date-picker.css',
            array('clinic-queue-base-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_script(
            'clinic-queue-date-picker',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/date-picker.js',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
    }

    /**
     * Guest assets: appointment summary styling + login form only (no booking form JS).
     *
     * @param array<string, string>|null $register_gate_markup Keys loginHtml/registerHtml for client-side login/register toggle.
     */
    private function enqueue_guest_assets($register_gate_markup = null) {
        static $guest_assets_loaded = false;
        if ($guest_assets_loaded) {
            return;
        }
        $this->enqueue_booking_form_styles();

        if (is_array($register_gate_markup) && isset($register_gate_markup['loginHtml'], $register_gate_markup['registerHtml'])) {
            wp_enqueue_script(
                'clinic-queue-booking-register-gate',
                CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/booking-form/js/booking-register-gate.js',
                array(),
                CLINIC_QUEUE_MANAGEMENT_VERSION,
                true
            );
            wp_add_inline_script(
                'clinic-queue-booking-register-gate',
                'window.ClinicQueueBookingRegisterGate = ' .
                    wp_json_encode(
                        array(
                            'loginHtml'    => $register_gate_markup['loginHtml'],
                            'registerHtml' => $register_gate_markup['registerHtml'],
                        )
                    ) .
                    ';',
                'before'
            );
        }

        $guest_assets_loaded = true;
    }

    /**
     * Shortcode string for guest login form (filters as in the previous view layer).
     *
     * @return string
     */
    private function get_guest_login_shortcode_string() {
        return apply_filters(
            'clinic_queue_booking_form_login_shortcode',
            apply_filters(
                'clinic_queue_booking_form_register_shortcode',
                '[mad_login_form redirect="current"]'
            )
        );
    }

    /**
     * Shortcode string for guest registration form.
     *
     * @return string
     */
    private function get_guest_register_shortcode_string() {
        return apply_filters(
            'clinic_queue_booking_form_user_register_shortcode',
            '[user_register_form redirect="current"]'
        );
    }

    /**
     * Page URL to redirect to when clicking "Close" in the success modal
     *
     * @return string Full URL or empty string if the page does not exist
     */
    private function get_booking_success_close_redirect_url() {
        $page_id = 2907;
        $url     = get_permalink($page_id);

        return $url ? esc_url($url) : '';
    }

    /**
     * Appointment date for display (d/m/Y)
     *
     * @param string $appt_date Date in Y-m-d or d/m/Y format
     * @return string
     */
    private function format_appointment_date_display($appt_date) {
        $appt_date = trim((string) $appt_date);
        if ($appt_date === '') {
            return '';
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $appt_date);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('d/m/Y');
        }

        $dt = DateTimeImmutable::createFromFormat('d/m/Y', $appt_date);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('d/m/Y');
        }

        return $appt_date;
    }

    /**
     * Normalize display string — strip stray backslashes and decode entities before escape.
     *
     * @param string $value Raw string (query param, post title, etc.).
     * @return string
     */
    private function normalize_display_string($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = wp_unslash($value);
        $value = stripslashes($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        return trim($value);
    }

    /**
     * Clinic location for modal and Google Calendar display
     *
     * @param array<string, mixed> $appointment_data Appointment data from query or meta
     * @param int                  $clinic_id        Clinic ID
     * @return string
     */
    private function resolve_clinic_location_display($appointment_data, $clinic_id) {
        $clinic_name    = isset($appointment_data['clinic_name'])
            ? $this->normalize_display_string((string) $appointment_data['clinic_name'])
            : '';
        $clinic_address = isset($appointment_data['clinic_address']) ? trim((string) $appointment_data['clinic_address']) : '';

        if ($clinic_id > 0) {
            if ($clinic_name === '') {
                $clinic_post = get_post($clinic_id);
                if ($clinic_post) {
                    $clinic_name = $this->normalize_display_string($clinic_post->post_title);
                }
            }
            if ($clinic_address === '') {
                $meta_address = get_post_meta($clinic_id, 'clinic_address', true);
                if (is_string($meta_address) && trim($meta_address) !== '') {
                    $clinic_address = trim($meta_address);
                }
            }
        }

        if ($clinic_name !== '' && $clinic_address !== '') {
            return $clinic_name . ', ' . $clinic_address;
        }
        if ($clinic_name !== '') {
            return $clinic_name;
        }

        return $clinic_address;
    }

    /**
     * Doctor name for success modal (only when doctor_id is valid).
     *
     * @param array<string, mixed> $appointment_data Appointment data
     * @param int                  $doctor_id        Doctor ID
     * @return string
     */
    private function resolve_doctor_name_for_success($appointment_data, $doctor_id) {
        $doctor_id = (int) $doctor_id;
        if ($doctor_id <= 0) {
            return '';
        }

        if (!empty($appointment_data['doctor_name'])) {
            return trim((string) $appointment_data['doctor_name']);
        }

        $doctor_post = get_post($doctor_id);
        if ($doctor_post) {
            return $doctor_post->post_title;
        }

        return '';
    }

    /**
     * Schedule name (schedule_name meta) when no doctor is assigned.
     * If schedule_name is empty — fall back to the schedule post title.
     *
     * @param int $scheduler_id Schedule post ID (schedules CPT).
     * @return string
     */
    private function resolve_schedule_name_display($scheduler_id) {
        $scheduler_id = (int) $scheduler_id;
        if ($scheduler_id <= 0) {
            return '';
        }

        $schedule_name = get_post_meta($scheduler_id, 'schedule_name', true);
        if (is_string($schedule_name)) {
            $schedule_name = trim($schedule_name);
            if ($schedule_name !== '') {
                return $schedule_name;
            }
        }

        $scheduler_post = get_post($scheduler_id);
        if ($scheduler_post) {
            return trim((string) $scheduler_post->post_title);
        }

        return '';
    }

    /**
     * Display name for "treating doctor" row and success modal: doctor if set, otherwise schedule name.
     *
     * @param array<string, mixed> $appointment_data Appointment data.
     * @param int                  $doctor_id        Doctor ID (from schedule meta only).
     * @param int                  $scheduler_id     Schedule post ID.
     * @return string
     */
    private function resolve_treating_doctor_display_name($appointment_data, $doctor_id, $scheduler_id = 0) {
        $doctor_id = (int) $doctor_id;
        if ($doctor_id > 0) {
            return $this->resolve_doctor_name_for_success($appointment_data, $doctor_id);
        }

        $scheduler_id = (int) $scheduler_id;
        if ($scheduler_id <= 0 && !empty($appointment_data['scheduler_id'])) {
            $scheduler_id = (int) $appointment_data['scheduler_id'];
        }

        if ($scheduler_id <= 0) {
            return '';
        }

        return $this->resolve_schedule_name_display($scheduler_id);
    }

    /**
     * Doctor page URL (CPT post) by ID.
     *
     * @param int $doctor_id Doctor post ID.
     * @return string URL or empty string.
     */
    private function resolve_doctor_permalink($doctor_id) {
        $doctor_id = (int) $doctor_id;
        if ($doctor_id <= 0) {
            return '';
        }

        $doctor_post = get_post($doctor_id);
        if (!$doctor_post || $doctor_post->post_status !== 'publish') {
            return '';
        }

        $permalink = get_permalink($doctor_id);

        return is_string($permalink) ? $permalink : '';
    }

    /**
     * Build success modal data array (AJAX response)
     *
     * @param string               $patient_name     Patient name
     * @param string               $appt_date        Date (Y-m-d)
     * @param string               $appt_time        Time
     * @param int                  $duration         Duration in minutes
     * @param string               $notes            Notes
     * @param array<string, mixed> $appointment_data Supplemental appointment data
     * @param int                  $doctor_id        Doctor ID
     * @param int                  $clinic_id        Clinic ID
     * @param string               $treatment_type   Treatment type (raw)
     * @return array<string, mixed>
     */
    private function build_success_modal_payload(
        $patient_name,
        $appt_date,
        $appt_time,
        $duration,
        $notes,
        $appointment_data,
        $doctor_id,
        $clinic_id,
        $treatment_type
    ) {
        $scheduler_id = (int) ($appointment_data['scheduler_id'] ?? 0);
        $doctor_name  = $this->resolve_treating_doctor_display_name($appointment_data, $doctor_id, $scheduler_id);
        $location     = $this->resolve_clinic_location_display($appointment_data, $clinic_id);

        $treatment_display = '';
        if ($treatment_type !== '') {
            $treatment_display = $this->resolve_treatment_type_label($treatment_type);
        } elseif (!empty($appointment_data['treatment_type'])) {
            $treatment_display = $this->resolve_treatment_type_label((string) $appointment_data['treatment_type']);
        }
        if ($treatment_display === '' && !empty($appointment_data['treatment_type_display'])) {
            $treatment_display = (string) $appointment_data['treatment_type_display'];
        }

        return array(
            'message'                 => 'התור נקבע בהצלחה עבור ' . $patient_name . '!',
            'patient_name'            => $patient_name,
            'doctor_name'             => $doctor_name,
            'appt_date'               => $appt_date,
            'appt_date_display'       => $this->format_appointment_date_display($appt_date),
            'appt_time'               => $appt_time,
            'duration'                => max(0, (int) $duration),
            'clinic_location'         => $location,
            'treatment_type'          => $treatment_display,
            'notes'                   => $notes,
        );
    }

    /**
     * Enqueue CSS and JavaScript assets
     */
    private function enqueue_assets() {
        static $assets_loaded = false;

        if ($assets_loaded) {
            return;
        }

        $this->enqueue_booking_form_styles();
        $this->enqueue_date_picker_assets();

        wp_enqueue_script(
            'clinic-queue-floating-labels',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/floating-labels.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'booking-form-js',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/booking-form/js/booking-form.js',
            array('jquery', 'clinic-queue-floating-labels'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_localize_script('booking-form-js', 'bookingFormData', array(
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('save_booking_ajax_nonce'),
            'closeRedirectUrl' => $this->get_booking_success_close_redirect_url(),
            'hasValidUserIdNumber' => $this->user_has_valid_id_number(get_current_user_id()),
            'assets'           => array(
                'confetti'    => CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/confeti.png',
                'successIcon' => CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/vii.png',
            ),
            'i18n'             => array(
                'titlePrefix'        => __('התור ל:', 'clinic-queue-management'),
                'titleSuffix'        => __('נקבע בהצלחה!', 'clinic-queue-management'),
                'calendarEventTitle' => __('תור אצל %s', 'clinic-queue-management'),
                'idModalInvalid'     => __('מספר תעודת זהות אינו תקין. אנא בדוק והזן שוב.', 'clinic-queue-management'),
                'idModalSaving'      => __('שומר...', 'clinic-queue-management'),
                'familyMemberSaved' => __(
                    'בן המשפחה נוסף בהצלחה. ניתן לבחור אותו ברשימה.',
                    'clinic-queue-management'
                ),
            ),
        ));

        $assets_loaded = true;
    }
    
    /**
     * Prepare data for the view
     *
     * @return array Data for view
     */
    private function prepare_data() {
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $family_members = $this->get_family_members_list($user_id);
        
        // Read query parameters from URL (passed from booking calendar)
        $appointment_data = $this->get_appointment_data_from_query();
        
        return array(
            'current_user' => $current_user,
            'family_members' => $family_members,
            'appointment_data' => $appointment_data,
        );
    }
    
    /**
     * Get appointment data from URL query parameters
     * 
     * @return array Appointment data or empty array
     */
    private function get_appointment_data_from_query() {
        $data = array();
        
        // Read all relevant query parameters
        $data['date'] = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $data['time'] = isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '';
        $data['treatment_type'] = isset($_GET['treatment_type']) ? sanitize_text_field($_GET['treatment_type']) : '';
        $data['treatment_type_display'] = isset($_GET['treatment_type_display'])
            ? sanitize_text_field(wp_unslash($_GET['treatment_type_display']))
            : '';
        $data['doctor_id'] = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
        $data['doctor_name'] = isset($_GET['doctor_name']) ? sanitize_text_field($_GET['doctor_name']) : '';
        $data['doctor_url'] = isset($_GET['doctor_url']) ? esc_url_raw($_GET['doctor_url']) : '';
        $data['doctor_specialty'] = isset($_GET['doctor_specialty']) ? sanitize_text_field($_GET['doctor_specialty']) : '';
        $data['doctor_thumbnail'] = isset($_GET['doctor_thumbnail']) ? esc_url_raw($_GET['doctor_thumbnail']) : '';
        $data['clinic_address'] = isset($_GET['clinic_address']) ? sanitize_text_field($_GET['clinic_address']) : '';
        $data['clinic_name'] = isset($_GET['clinic_name'])
            ? sanitize_text_field($this->normalize_display_string((string) wp_unslash($_GET['clinic_name'])))
            : '';
        $data['clinic_id'] = isset($_GET['clinic_id']) ? intval($_GET['clinic_id']) : 0;
        $data['clinic_thumbnail'] = isset($_GET['clinic_thumbnail']) ? esc_url_raw($_GET['clinic_thumbnail']) : '';
        $data['clinic_specialty'] = isset($_GET['clinic_specialty']) ? sanitize_text_field($_GET['clinic_specialty']) : '';
        $data['scheduler_id'] = isset($_GET['scheduler_id']) ? intval($_GET['scheduler_id']) : 0;
        $data['proxy_schedule_id'] = isset($_GET['proxy_schedule_id']) ? sanitize_text_field($_GET['proxy_schedule_id']) : '';
        $data['duration'] = isset($_GET['duration']) ? intval($_GET['duration']) : 0;
        $data['clinix_reason_id'] = isset($_GET['clinix_reason_id']) ? sanitize_text_field($_GET['clinix_reason_id']) : '';
        $data['from'] = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $data['to'] = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
        $data['referrer_url'] = isset($_GET['referrer_url']) ? esc_url_raw($_GET['referrer_url']) : '';
        $data['treatment_cost'] = isset($_GET['treatment_cost']) ? absint($_GET['treatment_cost']) : 0;
        
        $this->enrich_doctor_fields_in_appointment_data($data);

        if ($data['treatment_type_display'] === '' && $data['treatment_type'] !== '') {
            $data['treatment_type_display'] = $this->resolve_treatment_type_label($data['treatment_type']);
        }

        $this->enrich_clinic_fields_in_appointment_data($data);

        $scheduler_id = (int) ($data['scheduler_id'] ?? 0);
        $doctor_id    = $this->get_doctor_id_from_scheduler($scheduler_id);
        $data['doctor_id']   = $doctor_id;
        $data['doctor_name'] = $this->resolve_treating_doctor_display_name($data, $doctor_id, $scheduler_id);
        if ($doctor_id > 0 && empty($data['doctor_url'])) {
            $data['doctor_url'] = $this->resolve_doctor_permalink($doctor_id);
        } elseif ($doctor_id <= 0) {
            $data['doctor_url'] = '';
        }
        $data['has_treating_doctor'] = ($doctor_id > 0);
        $data['appt_date_display']     = $this->format_appointment_date_display($data['date']);
        $this->enrich_treatment_cost_in_appointment_data($data);
        $data['treatment_cost_display'] = $this->format_treatment_cost_display($data['treatment_cost'] ?? 0);

        return $data;
    }

    /**
     * Fill treatment cost from schedule treatments meta when missing in query.
     *
     * @param array<string, mixed> $data Appointment data (updated in place).
     * @return void
     */
    private function enrich_treatment_cost_in_appointment_data(array &$data) {
        $current_cost = isset($data['treatment_cost']) ? absint($data['treatment_cost']) : 0;
        if ($current_cost > 0) {
            return;
        }

        $scheduler_id = (int) ($data['scheduler_id'] ?? 0);
        $treatment_type = isset($data['treatment_type']) ? trim((string) $data['treatment_type']) : '';
        if ($scheduler_id <= 0 || $treatment_type === '') {
            return;
        }

        $resolved_cost = $this->resolve_treatment_cost_from_scheduler($scheduler_id, $treatment_type);
        if ($resolved_cost > 0) {
            $data['treatment_cost'] = $resolved_cost;
        }
    }

    /**
     * Get treatment cost from schedule post treatments meta by treatment type.
     *
     * @param int    $scheduler_id   Schedule post ID.
     * @param string $treatment_type Treatment type ID/slug.
     * @return int
     */
    private function resolve_treatment_cost_from_scheduler($scheduler_id, $treatment_type) {
        $scheduler_id = (int) $scheduler_id;
        $treatment_type = trim((string) $treatment_type);
        if ($scheduler_id <= 0 || $treatment_type === '') {
            return 0;
        }

        $treatments_raw = get_post_meta($scheduler_id, 'treatments', true);
        if (empty($treatments_raw)) {
            return 0;
        }

        $treatments = is_string($treatments_raw) ? maybe_unserialize($treatments_raw) : $treatments_raw;
        if (!is_array($treatments)) {
            return 0;
        }

        foreach ($treatments as $treatment) {
            if (!is_array($treatment)) {
                continue;
            }

            $raw_type = isset($treatment['treatment_type']) ? trim((string) $treatment['treatment_type']) : '';
            if ($raw_type === '' || $raw_type !== $treatment_type) {
                continue;
            }

            return isset($treatment['cost']) ? absint($treatment['cost']) : 0;
        }

        return 0;
    }

    /**
     * Display format for treatment cost (₪).
     *
     * @param int|string $cost Cost in NIS.
     * @return string Empty string when no valid cost.
     */
    private function format_treatment_cost_display($cost) {
        $cost = absint($cost);
        if ($cost <= 0) {
            return '';
        }

        return '₪ ' . number_format_i18n($cost);
    }

    /**
     * Doctor ID meta on schedule (scheduler) post.
     *
     * @param int $scheduler_id Schedule post ID.
     * @return int
     */
    private function get_doctor_id_from_scheduler($scheduler_id) {
        $scheduler_id = (int) $scheduler_id;
        if ($scheduler_id <= 0) {
            return 0;
        }

        return (int) get_post_meta($scheduler_id, 'doctor_id', true);
    }

    /**
     * Fill doctor name, specialty, and image from schedule when missing in query.
     *
     * @param array<string, mixed> $data Appointment data (updated in place).
     * @return void
     */
    private function enrich_doctor_fields_in_appointment_data(array &$data) {
        if (empty($data['scheduler_id'])) {
            return;
        }

        $scheduler_id = (int) $data['scheduler_id'];
        if ($scheduler_id <= 0 || !get_post($scheduler_id)) {
            return;
        }

        $doctor_id = $this->get_doctor_id_from_scheduler($scheduler_id);
        if ($doctor_id <= 0) {
            return;
        }

        $doctor_post = get_post($doctor_id);
        if (!$doctor_post) {
            return;
        }

        if (empty($data['doctor_name'])) {
            $data['doctor_name'] = $doctor_post->post_title;
        }
        if (empty($data['doctor_specialty'])) {
            $data['doctor_specialty'] = $this->resolve_doctor_specialty_display($doctor_id);
        }
        if (empty($data['doctor_thumbnail'])) {
            $thumbnail_id = get_post_thumbnail_id($doctor_id);
            if ($thumbnail_id) {
                $data['doctor_thumbnail'] = wp_get_attachment_image_url($thumbnail_id, 'medium');
            }
        }

        $data['doctor_id'] = $doctor_id;
        if (empty($data['doctor_url'])) {
            $data['doctor_url'] = $this->resolve_doctor_permalink($doctor_id);
        }
    }

    /**
     * Fill clinic name, address, image, and specialties for appointment summary.
     *
     * @param array<string, mixed> $data Appointment data (updated in place).
     * @return void
     */
    private function enrich_clinic_fields_in_appointment_data(array &$data) {
        if (!empty($data['clinic_name'])) {
            $data['clinic_name'] = $this->normalize_display_string((string) $data['clinic_name']);
        }

        $clinic_id = $this->resolve_clinic_id_from_appointment_data($data);
        if ($clinic_id <= 0) {
            return;
        }

        $data['clinic_id'] = $clinic_id;

        if (empty($data['clinic_name'])) {
            $clinic_post = get_post($clinic_id);
            if ($clinic_post) {
                $data['clinic_name'] = $this->normalize_display_string($clinic_post->post_title);
            }
        }

        if (empty($data['clinic_address'])) {
            $meta_address = get_post_meta($clinic_id, 'clinic_address', true);
            if (is_string($meta_address) && trim($meta_address) !== '') {
                $data['clinic_address'] = trim($meta_address);
            }
        }

        if (empty($data['clinic_thumbnail'])) {
            $data['clinic_thumbnail'] = $this->resolve_clinic_thumbnail_url($clinic_id);
        }

        if (empty($data['clinic_specialty'])) {
            $data['clinic_specialty'] = $this->resolve_clinic_specialty_display($clinic_id);
        }
    }

    /**
     * Clinic ID from query params or schedule meta.
     *
     * @param array<string, mixed> $data Appointment data.
     * @return int
     */
    private function resolve_clinic_id_from_appointment_data(array $data) {
        if (!empty($data['clinic_id'])) {
            return (int) $data['clinic_id'];
        }

        if (!empty($data['scheduler_id'])) {
            $clinic_id = (int) get_post_meta((int) $data['scheduler_id'], 'clinic_id', true);
            if ($clinic_id > 0) {
                return $clinic_id;
            }
        }

        return 0;
    }

    /**
     * Clinic image: clinc_img meta (JetEngine) or post featured image.
     *
     * @param int $clinic_id Clinic post ID.
     * @return string URL or empty string.
     */
    private function resolve_clinic_thumbnail_url($clinic_id) {
        $clinic_id = (int) $clinic_id;
        if ($clinic_id <= 0) {
            return '';
        }

        $img_id = get_post_meta($clinic_id, 'clinc_img', true);
        if ($img_id) {
            $url = wp_get_attachment_image_url((int) $img_id, 'medium');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $thumbnail_id = get_post_thumbnail_id($clinic_id);
        if ($thumbnail_id) {
            $url = wp_get_attachment_image_url($thumbnail_id, 'medium');
            return is_string($url) ? $url : '';
        }

        return '';
    }

    /**
     * Clinic specialties: specialties taxonomy, or clinic_specialization meta if empty.
     *
     * @param int $clinic_id Clinic post ID.
     * @return string Comma-separated list for display.
     */
    private function resolve_clinic_specialty_display($clinic_id) {
        $clinic_id = (int) $clinic_id;
        if ($clinic_id <= 0) {
            return '';
        }

        $labels = array();
        $tax_slug = 'specialties';
        if (class_exists('Clinic_Queue_Specialty_Taxonomy')) {
            $tax_slug = Clinic_Queue_Specialty_Taxonomy::TAXONOMY_SPECIALTIES;
        }

        if (taxonomy_exists($tax_slug)) {
            $terms = wp_get_post_terms($clinic_id, $tax_slug, array('fields' => 'names'));
            if (!is_wp_error($terms) && !empty($terms)) {
                $labels = array_map('strval', $terms);
            }
        }

        if (empty($labels)) {
            $specs_raw = get_post_meta($clinic_id, 'clinic_specialization', true);
            $specs_arr = maybe_unserialize($specs_raw);
            if (is_array($specs_arr)) {
                foreach ($specs_arr as $key => $value) {
                    if ($value === 'true' || $value === true) {
                        $labels[] = (string) $key;
                    } elseif (is_int($key) && is_string($value) && $value !== 'false' && $value !== 'true') {
                        $labels[] = trim($value);
                    }
                }
            }
        }

        $labels = array_values(
            array_unique(
                array_filter(
                    array_map('trim', $labels),
                    static function ($label) {
                        return $label !== '';
                    }
                )
            )
        );

        return implode(', ', $labels);
    }

    /**
     * Doctor specialty display: specialty meta, or specialties taxonomy terms if empty.
     *
     * @param int $doctor_id Doctor post ID.
     * @return string
     */
    private function resolve_doctor_specialty_display($doctor_id) {
        $doctor_id = (int) $doctor_id;
        if ($doctor_id <= 0) {
            return '';
        }

        $meta = get_post_meta($doctor_id, 'specialty', true);
        if (is_array($meta)) {
            $meta = implode(', ', array_filter(array_map('strval', $meta)));
        }
        if (is_string($meta) && trim($meta) !== '') {
            return trim($meta);
        }

        if (class_exists('Clinic_Queue_Specialty_Taxonomy')) {
            $terms = wp_get_post_terms($doctor_id, Clinic_Queue_Specialty_Taxonomy::TAXONOMY_SPECIALTIES);
            if (!is_wp_error($terms) && !empty($terms)) {
                return implode(', ', wp_list_pluck($terms, 'name'));
            }
        }

        return '';
    }

    /**
     * Site treatment type taxonomy (Jet/plugin: sometimes treatment_type, sometimes treatment_types).
     *
     * @return string Empty slug if none registered.
     */
    private function get_treatment_type_taxonomy_slug() {
        $slug = apply_filters('clinic_queue_booking_treatment_type_taxonomy', '');
        if (is_string($slug) && $slug !== '' && taxonomy_exists($slug)) {
            return $slug;
        }
        if (taxonomy_exists('treatment_type')) {
            return 'treatment_type';
        }
        if (taxonomy_exists('treatment_types')) {
            return 'treatment_types';
        }
        return '';
    }

    /**
     * Convert treatment term ID/slug to display name.
     *
     * @param string $raw Value from query string (e.g. treatment_type=123).
     * @return string Term name; if not found — original value (backward compatibility).
     */
    private function resolve_treatment_type_label($raw) {
        if (!is_string($raw)) {
            $raw = '';
        }
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $taxonomy = $this->get_treatment_type_taxonomy_slug();
        if ($taxonomy === '') {
            return $raw;
        }

        if (preg_match('/^\d+$/', $raw)) {
            $term = get_term((int) $raw, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        $slug = sanitize_title($raw);
        if ($slug !== '') {
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        $term = get_term_by('name', $raw, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return $term->name;
        }

        return $raw;
    }

    /**
     * Check whether the user has a valid ID number in profile.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    private function user_has_valid_id_number($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $stored = get_user_meta($user_id, self::USER_ID_NUMBER_META_KEY, true);

        return Clinic_Queue_Helpers::is_valid_israeli_id_number((string) $stored);
    }

    /**
     * Get normalized family members list (indexes 0, 1, 2…).
     * JetEngine may store keys like item-0 — unsuitable for (int) on submit.
     *
     * @param int $user_id User ID.
     * @return array<int, array<string, mixed>>
     */
    private function get_family_members_list($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }

        $raw = get_user_meta($user_id, self::FAMILY_REPEATER_META_KEY, true);
        if (!is_array($raw)) {
            return array();
        }

        $list = array();
        foreach ($raw as $member) {
            if (is_array($member)) {
                $list[] = $this->normalize_family_member_row($member);
            }
        }

        return $list;
    }

    /**
     * Read raw ID number from a family member repeater row (JetEngine/snippet aliases).
     *
     * @param array<string, mixed> $member Family member row.
     * @return string
     */
    private function get_family_member_id_raw(array $member) {
        $keys = array(
            'id_number',
            'user_id_number',
            'id-number',
            'national_id',
            'identity',
            'tz',
        );

        foreach ($keys as $key) {
            if (!isset($member[$key])) {
                continue;
            }

            $value = trim((string) $member[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Read raw date of birth from a family member row (JetEngine/snippet aliases).
     *
     * @param array<string, mixed> $member Family member row.
     * @return string Y-m-d or empty string.
     */
    private function get_family_member_dob_raw(array $member) {
        $keys = array(
            'dob',
            'date_of_birth',
            'birth_date',
        );

        foreach ($keys as $key) {
            if (!isset($member[$key])) {
                continue;
            }

            $value = trim((string) $member[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Normalize a family member row so id_number and dob are populated from known aliases.
     *
     * @param array<string, mixed> $member Family member row.
     * @return array<string, mixed>
     */
    private function normalize_family_member_row(array $member) {
        $raw_id = $this->get_family_member_id_raw($member);
        if ($raw_id !== '') {
            $member['id_number'] = Clinic_Queue_Helpers::normalize_israeli_id_number($raw_id);
        }

        $dob_raw = $this->get_family_member_dob_raw($member);
        if ($dob_raw !== '') {
            $member['dob'] = $dob_raw;
            $member['date_of_birth'] = $dob_raw;
        }

        return $member;
    }

    /**
     * Validate profile birth date (Y-m-d, not in future, within 150 years).
     *
     * @param string $birth_date Birth date string.
     * @return bool
     */
    private function is_valid_profile_birth_date($birth_date) {
        $birth_date = trim((string) $birth_date);
        if ($birth_date === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$parsed instanceof DateTime) {
            return false;
        }

        $parsed->setTime(0, 0, 0);
        $today = new DateTime('today');

        if ($parsed > $today) {
            return false;
        }

        $min_date = clone $today;
        $min_date->modify('-150 years');

        return $parsed >= $min_date;
    }

    /**
     * Format profile birth date for proxy API (ISO 8601 UTC midnight).
     *
     * @param string|null $birth_date Birth date string (Y-m-d).
     * @return string|null
     */
    private function format_profile_birth_date_for_api($birth_date) {
        $birth_date = trim((string) $birth_date);
        if ($birth_date === '' || !$this->is_valid_profile_birth_date($birth_date)) {
            return null;
        }

        try {
            $birth_dt = new DateTime($birth_date);
            $birth_dt->setTime(0, 0, 0);

            return $birth_dt->format('Y-m-d\TH:i:s\Z');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Resolve patient profile from form selection (primary user or family member).
     *
     * @param int    $user_id          Logged-in user ID.
     * @param string $selected_patient patient_select value from the form.
     * @return array<string, mixed>|WP_Error
     */
    private function resolve_patient_profile($user_id, $selected_patient) {
        $current_user = get_userdata($user_id);
        if (!$current_user) {
            return new WP_Error('invalid_user', 'משתמש לא תקין.');
        }

        $first_name = get_user_meta($user_id, 'first_name', true);
        if (empty(trim((string) $first_name))) {
            $first_name = $current_user->display_name;
        }

        $self_id_raw = trim((string) get_user_meta($user_id, self::USER_ID_NUMBER_META_KEY, true));

        $profile = array(
            'first_name'    => $first_name,
            'last_name'     => (string) ($current_user->last_name ?? ''),
            'email'         => $current_user->user_email,
            'primary_phone' => trim((string) get_user_meta($user_id, 'phone', true)),
            'identity_raw'  => $self_id_raw,
            'identity'      => Clinic_Queue_Helpers::normalize_israeli_id_number($self_id_raw),
            'gender'        => Clinic_Queue_Helpers::map_gender_for_api(
                (string) get_user_meta($user_id, 'gender', true)
            ),
            'birth_date'    => null,
            'is_self'       => true,
        );

        if (!str_starts_with($selected_patient, 'family_')) {
            return $profile;
        }

        $index = (int) str_replace('family_', '', $selected_patient);
        $family = $this->get_family_members_list($user_id);
        if (!isset($family[$index])) {
            return new WP_Error('family_not_found', 'בן המשפחה שנבחר לא נמצא.');
        }

        $member = $family[$index];
        $member_id_raw = $this->get_family_member_id_raw($member);

        $profile['is_self']      = false;
        $profile['first_name']   = trim((string) ($member['first_name'] ?? ''));
        $profile['last_name']    = (string) ($member['last_name'] ?? '');
        $profile['identity_raw'] = $member_id_raw;
        $profile['identity']     = Clinic_Queue_Helpers::normalize_israeli_id_number($member_id_raw);
        $profile['gender']       = Clinic_Queue_Helpers::map_relationship_to_gender_for_api(
            (string) ($member['relationship'] ?? '')
        );

        $member_dob = $this->get_family_member_dob_raw($member);
        $profile['birth_date'] = $member_dob !== '' ? $member_dob : null;

        return $profile;
    }

    /**
     * Build appointment remark from personal note only.
     * Additional phone is now sent as a dedicated API field (customer.additionalMobilePhone).
     *
     * @param string $personal_note Personal note from the form.
     * @return string|null
     */
    private function build_appointment_remark($personal_note) {
        $personal_note = trim((string) $personal_note);

        if ($personal_note === '') {
            return null;
        }

        return sprintf(
            /* translators: %s patient note */
            __('הערת מטופל: %s', 'clinic-queue-management'),
            $personal_note
        );
    }

    /**
     * Validate patient profile before booking.
     *
     * @param array<string, mixed> $profile Patient profile.
     * @return array<string, mixed>|null Error array for wp_send_json_error or null if valid.
     */
    private function get_patient_profile_booking_error(array $profile) {
        $is_self = !empty($profile['is_self']);

        $identity = isset($profile['identity']) ? (string) $profile['identity'] : '';
        $identity_raw = isset($profile['identity_raw'])
            ? trim((string) $profile['identity_raw'])
            : $identity;

        if ($is_self) {
            if ($identity === '' || !Clinic_Queue_Helpers::is_valid_israeli_id_number($identity)) {
                return array(
                    'error_code'             => 'missing_user_id_number',
                    'missing_user_id_number' => true,
                    'error_reason'           => __('חסרה או לא תקינה תעודת זהות (user_id_number) בפרופיל המשתמש.', 'clinic-queue-management'),
                    'message'                => __('נדרשת השלמת תעודת זהות לפני קביעת התור.', 'clinic-queue-management'),
                );
            }
        } elseif ($identity === '') {
            if ($identity_raw !== '') {
                return array(
                    'error_code'   => 'family_invalid_id_number',
                    'error_reason' => sprintf(
                        /* translators: %s raw id number from profile */
                        __('תעודת הזהות שנשלפה מהפרופיל (%s) אינה תקינה (פורמט או ספרת ביקורת).', 'clinic-queue-management'),
                        $identity_raw
                    ),
                    'message'      => __('תעודת הזהות של בן המשפחה שנבחר אינה תקינה. אנא עדכן את הפרטים.', 'clinic-queue-management'),
                );
            }

            return array(
                'error_code'   => 'family_missing_id_number',
                'error_reason' => __('למטופל שנבחר חסרה תעודת זהות בפרופיל (שדה id_number / user_id_number ברפיטר family_members).', 'clinic-queue-management'),
                'message'      => __('למטופל שנבחר חסרה תעודת זהות בפרופיל. אנא עדכן את פרטי בן המשפחה.', 'clinic-queue-management'),
            );
        } elseif (!Clinic_Queue_Helpers::is_valid_israeli_id_number($identity)) {
            return array(
                'error_code'   => 'family_invalid_id_number',
                'error_reason' => sprintf(
                    /* translators: %s normalized id number from profile */
                    __('תעודת הזהות שנשלפה מהפרופיל (%s) אינה תקינה (ספרת ביקורת).', 'clinic-queue-management'),
                    $identity
                ),
                'message'      => __('תעודת הזהות של בן המשפחה שנבחר אינה תקינה. אנא עדכן את הפרטים.', 'clinic-queue-management'),
            );
        }

        $email = trim((string) ($profile['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return array(
                'error_code'   => 'missing_email',
                'error_reason' => __(
                    'חסר אימייל תקין בפרופיל המשתמש המחובר — הוא נשלח כ-email ל-API (גם עבור בן משפחה).',
                    'clinic-queue-management'
                ),
                'message'      => __('חסר אימייל תקין בפרופיל שלך. אנא עדכן את הפרטים האישיים.', 'clinic-queue-management'),
            );
        }

        if (empty($profile['primary_phone'])) {
            return array(
                'error_code'   => 'missing_primary_phone',
                'error_reason' => __('חסר מספר טלפון ראשי (phone) בפרופיל המשתמש המחובר — הוא נשלח כ-mobilePhone ל-API.', 'clinic-queue-management'),
                'message'      => __('חסר מספר טלפון ראשי בפרופיל שלך. אנא עדכן את הפרטים האישיים.', 'clinic-queue-management'),
            );
        }

        if (!$is_self) {
            $first_name = trim((string) ($profile['first_name'] ?? ''));
            if ($first_name === '') {
                return array(
                    'error_code'   => 'family_missing_first_name',
                    'error_reason' => __('למטופל שנבחר חסר שם פרטי בפרופיל בן המשפחה (family_members).', 'clinic-queue-management'),
                    'message'      => __('למטופל שנבחר חסר שם פרטי. אנא עדכן את פרטי בן המשפחה.', 'clinic-queue-management'),
                );
            }

            $birth_date = trim((string) ($profile['birth_date'] ?? ''));
            if ($birth_date === '') {
                return array(
                    'error_code'   => 'family_missing_dob',
                    'error_reason' => __('למטופל שנבחר חסר תאריך לידה בפרופיל (dob / date_of_birth / birth_date).', 'clinic-queue-management'),
                    'message'      => __('למטופל שנבחר חסר תאריך לידה. אנא עדכן את פרטי בן המשפחה.', 'clinic-queue-management'),
                );
            }

            if (!$this->is_valid_profile_birth_date($birth_date)) {
                return array(
                    'error_code'   => 'family_invalid_dob',
                    'error_reason' => sprintf(
                        /* translators: %s birth date from profile */
                        __('תאריך הלידה שנשלף מהפרופיל (%s) אינו תקין.', 'clinic-queue-management'),
                        $birth_date
                    ),
                    'message'      => __('תאריך הלידה של בן המשפחה שנבחר אינו תקין. אנא עדכן את הפרטים.', 'clinic-queue-management'),
                );
            }

        }

        return null;
    }

    /**
     * Build Customer Model from patient profile.
     *
     * @param array<string, mixed> $profile          Patient profile.
     * @param string               $additional_phone Additional phone from the booking form (optional).
     * @return Clinic_Queue_Customer_Model
     */
    private function build_customer_from_profile(array $profile, $additional_phone = '') {
        $customer = new Clinic_Queue_Customer_Model();
        $customer->firstName = $profile['first_name'];
        $customer->lastName = $profile['last_name'];
        $customer->identity = $profile['identity'];
        $customer->identityType = 'TZ';
        $customer->email = $profile['email'];
        $customer->mobilePhone = $profile['primary_phone'];

        $additional_phone = trim((string) $additional_phone);
        if ($additional_phone !== '') {
            $customer->additionalMobilePhone = $additional_phone;
        }

        $gender = $profile['gender'] ?? null;
        if ($gender !== null && in_array($gender, array('Male', 'Female'), true)) {
            $customer->gender = $gender;
        }

        $formatted_birth_date = $this->format_profile_birth_date_for_api($profile['birth_date'] ?? null);
        if ($formatted_birth_date !== null) {
            $customer->birthDate = $formatted_birth_date;
        }

        return $customer;
    }

    /**
     * Patient summary fetched server-side (not from POST) — for AJAX response debug.
     *
     * @param array<string, mixed> $profile Patient profile.
     * @return array<string, mixed>
     */
    private function build_resolved_patient_summary(array $profile) {
        $identity = isset($profile['identity']) ? (string) $profile['identity'] : '';
        $identity_status = 'valid';

        if ($identity === '') {
            $identity_status = 'missing';
        } elseif (!Clinic_Queue_Helpers::is_valid_israeli_id_number($identity)) {
            $identity_status = 'invalid';
        }

        return array(
            'is_self'          => !empty($profile['is_self']),
            'first_name'       => (string) ($profile['first_name'] ?? ''),
            'last_name'        => (string) ($profile['last_name'] ?? ''),
            'identity'         => $identity,
            'identity_status'  => $identity_status,
            'mobile_phone'     => (string) ($profile['primary_phone'] ?? ''),
            'email'            => (string) ($profile['email'] ?? ''),
            'gender'           => $profile['gender'] ?? null,
            'birth_date'       => $profile['birth_date'] ?? null,
        );
    }

    /**
     * Payload preview as sent to Appointment/Create (after sanitization like proxy service).
     *
     * @param Clinic_Queue_Appointment_Model $appointment_model Appointment model.
     * @return array<string, mixed>
     */
    private function build_proxy_api_payload_preview(Clinic_Queue_Appointment_Model $appointment_model) {
        $data = $appointment_model->to_array();

        if ($data['customer'] instanceof Clinic_Queue_Customer_Model) {
            $customer = $data['customer']->to_array();
            if (!isset($customer['gender']) || !in_array($customer['gender'], array('Male', 'Female'), true)) {
                unset($customer['gender']);
            }
            if (!isset($customer['additionalMobilePhone']) || trim((string) $customer['additionalMobilePhone']) === '') {
                unset($customer['additionalMobilePhone']);
            }
            $data['customer'] = $customer;
        }

        if (!isset($data['remark']) || $data['remark'] === null || trim((string) $data['remark']) === '') {
            unset($data['remark']);
        }

        $data['drWebReasonID'] = isset($data['drWebReasonID']) && is_numeric($data['drWebReasonID'])
            ? (int) $data['drWebReasonID']
            : 0;
        $data['duration'] = isset($data['duration']) && is_numeric($data['duration'])
            ? (int) $data['duration']
            : 30;
        $data['schedulerID'] = (int) $data['schedulerID'];

        return $data;
    }

    /**
     * Debug context for AJAX response (server-side patient + proxy payload).
     *
     * @param array<string, mixed>              $profile           Patient profile.
     * @param string                              $selected_patient  patient_select from form.
     * @param Clinic_Queue_Appointment_Model|null $appointment_model Appointment model (optional).
     * @return array<string, mixed>
     */
    private function build_booking_debug_context(
        array $profile,
        $selected_patient,
        $appointment_model = null
    ) {
        $context = array(
            'patient_select'   => (string) $selected_patient,
            'resolved_patient' => $this->build_resolved_patient_summary($profile),
            'data_source'      => 'server_user_meta',
        );

        if ($appointment_model instanceof Clinic_Queue_Appointment_Model) {
            $context['proxy_api_payload'] = $this->build_proxy_api_payload_preview($appointment_model);
        }

        return $context;
    }

    /**
     * Send AJAX error with patient context and payload (if available).
     *
     * @param array<string, mixed>              $error             Error fields (message, error_code, etc.).
     * @param array<string, mixed>|null         $profile           Patient profile.
     * @param string                              $selected_patient  patient_select.
     * @param Clinic_Queue_Appointment_Model|null $appointment_model Appointment model.
     * @return void
     */
    private function send_booking_json_error(
        array $error,
        $profile = null,
        $selected_patient = '',
        $appointment_model = null
    ) {
        if (is_array($profile)) {
            $error = array_merge(
                $error,
                $this->build_booking_debug_context($profile, $selected_patient, $appointment_model)
            );
        } elseif ($selected_patient !== '') {
            $error['patient_select'] = (string) $selected_patient;
            $error['data_source']    = 'server_user_meta';
        }

        wp_send_json_error($error);
    }

    /**
     * AJAX handler: save ID number for primary user.
     *
     * @return void
     */
    public function handle_save_user_id_number_ajax() {
        check_ajax_referer('save_booking_ajax_nonce', 'security');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'עליך להתחבר למערכת.'));
            return;
        }

        $raw_id = sanitize_text_field(wp_unslash($_POST['user_id_number'] ?? ''));
        if (!Clinic_Queue_Helpers::is_valid_israeli_id_number($raw_id)) {
            wp_send_json_error(array(
                'message' => __('מספר תעודת זהות אינו תקין.', 'clinic-queue-management'),
            ));
            return;
        }

        $normalized = Clinic_Queue_Helpers::normalize_israeli_id_number($raw_id);
        update_user_meta($user_id, self::USER_ID_NUMBER_META_KEY, $normalized);

        wp_send_json_success(array(
            'user_id_number' => $normalized,
        ));
    }

    /**
     * AJAX Handler: Submit appointment
     */
    public function handle_appointment_submission_ajax() {
        check_ajax_referer('save_booking_ajax_nonce', 'security');
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => 'עליך להתחבר למערכת.'));
            return;
        }
        
        // Load services and handlers
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-scheduler-proxy-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-appointment-proxy-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-scheduler-model.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-appointment-model.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-database-manager.php';
        
        $scheduler_service = new Clinic_Queue_Scheduler_Proxy_Service();
        $appointment_service = new Clinic_Queue_Appointment_Proxy_Service();
        $db_manager = Clinic_Queue_Database_Manager::get_instance();
        
        // Collect form data
        $selected_patient  = sanitize_text_field($_POST['patient_select'] ?? '');
        $additional_phone  = sanitize_text_field($_POST['additional_phone'] ?? '');
        $personal_note     = sanitize_textarea_field($_POST['notes'] ?? '');
        $first_visit       = sanitize_text_field($_POST['first_visit'] ?? '');
        $appt_date = sanitize_text_field($_POST['appt_date'] ?? '');
        $appt_time = sanitize_text_field($_POST['appt_time'] ?? '');
        $scheduler_id = isset($_POST['scheduler_id']) ? intval($_POST['scheduler_id']) : 0;
        $proxy_schedule_id = isset($_POST['proxy_schedule_id']) ? sanitize_text_field($_POST['proxy_schedule_id']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;

        $appointment_data = $this->get_appointment_data_from_query();
        if (empty($appointment_data['scheduler_id']) && $scheduler_id > 0) {
            $appointment_data['scheduler_id'] = $scheduler_id;
        }

        // If scheduler_id is missing from form, try URL
        if (empty($scheduler_id)) {
            $scheduler_id = $appointment_data['scheduler_id'] ?? 0;
        }
        if (empty($proxy_schedule_id)) {
            $proxy_schedule_id = $appointment_data['proxy_schedule_id'] ?? '';
        }
        if (empty($duration)) {
            $duration = $appointment_data['duration'] ?? 0;
        }
        if ($appt_date === '' && !empty($appointment_data['date'])) {
            $appt_date = sanitize_text_field($appointment_data['date']);
        }
        if ($appt_time === '' && !empty($appointment_data['time'])) {
            $appt_time = sanitize_text_field($appointment_data['time']);
        }

        if (empty($scheduler_id)) {
            wp_send_json_error(array('message' => 'שגיאה: מזהה יומן חסר.'));
            return;
        }
        
        // Schedule ID for proxy API — proxy expects proxy_schedule_id (external ID), not WordPress post ID
        $api_scheduler_id = !empty($proxy_schedule_id) && is_numeric($proxy_schedule_id)
            ? intval($proxy_schedule_id)
            : $scheduler_id;
        
        // Resolve patient data from profile (meta / repeater)
        $profile = $this->resolve_patient_profile($user_id, $selected_patient);
        if (is_wp_error($profile)) {
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'family_not_found',
                    'error_reason' => $profile->get_error_message(),
                    'message'      => $profile->get_error_message(),
                ),
                null,
                $selected_patient
            );
            return;
        }

        $profile_error = $this->get_patient_profile_booking_error($profile);
        if ($profile_error !== null) {
            $this->send_booking_json_error($profile_error, $profile, $selected_patient);
            return;
        }

        $combined_remark = $this->build_appointment_remark($personal_note);
        
        // Convert date and time to UTC ISO 8601
        $timezone_string = get_option('timezone_string');
        if (empty($timezone_string)) {
            $timezone_string = 'Asia/Jerusalem'; // Default
        }
        
        try {
            $timezone = new DateTimeZone($timezone_string);
            $dt = new DateTime("$appt_date $appt_time", $timezone);
            $dt->setTimezone(new DateTimeZone('UTC'));
            $fromUTC = $dt->format('Y-m-d\TH:i:s\Z');
        } catch (Exception $e) {
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'invalid_datetime',
                    'error_reason' => __('תאריך או שעה לא תקינים.', 'clinic-queue-management'),
                    'message'      => 'שגיאה בעיבוד תאריך ושעה.',
                ),
                $profile,
                $selected_patient
            );
            return;
        }
        
        // Step 1: Check slot availability via Scheduler Service (proxy expects api_scheduler_id)
        $slot_model = new Clinic_Queue_Check_Slot_Available_Model();
        $slot_model->schedulerID = $api_scheduler_id;
        $slot_model->fromUTC = $fromUTC;
        $slot_model->duration = $duration;
        
        $slot_check = $scheduler_service->check_slot_available($slot_model, $scheduler_id);
        
        if (is_wp_error($slot_check)) {
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'slot_check_failed',
                    'slot_taken'   => true,
                    'error_reason' => $slot_check->get_error_message(),
                    'message'      => 'שגיאה בבדיקת זמינות התור: ' . $slot_check->get_error_message(),
                ),
                $profile,
                $selected_patient
            );
            return;
        }

        // Check if slot is already taken
        if (!$slot_check->is_success()) {
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'slot_taken',
                    'slot_taken'   => true,
                    'error_reason' => __('הסלוט כבר תפוס.', 'clinic-queue-management'),
                    'message'      => 'מצטערים, התור שבחרת כבר נתפס על ידי מישהו אחר. אנא בחר תור אחר.',
                ),
                $profile,
                $selected_patient
            );
            return;
        }

        // Step 2: Build Customer Model
        $customer = $this->build_customer_from_profile($profile, $additional_phone);
        
        // Step 3: Build Appointment Model (schedulerID = schedule ID in proxy system)
        $dr_web_reason_id = 0;
        $schedule_type = $scheduler_id ? get_post_meta((int) $scheduler_id, 'schedule_type', true) : '';
        if ($schedule_type === 'clinix') {
            $clinix_reason = isset($_POST['clinix_reason_id']) ? sanitize_text_field($_POST['clinix_reason_id']) : '';
            if ($clinix_reason === '' && !empty($appointment_data['clinix_reason_id'])) {
                $clinix_reason = (string) $appointment_data['clinix_reason_id'];
            }
            if ($clinix_reason !== '' && is_numeric($clinix_reason)) {
                $dr_web_reason_id = (int) $clinix_reason;
            }
        }
        $appointment_model = new Clinic_Queue_Appointment_Model();
        $appointment_model->schedulerID = $api_scheduler_id;
        $appointment_model->customer = $customer;
        $appointment_model->startAtUTC = $fromUTC;
        $appointment_model->drWebReasonID = $dr_web_reason_id;
        $appointment_model->remark = $combined_remark;
        $appointment_model->duration = $duration;
        
        // Step 4: Create appointment in proxy via Appointment Service
        $proxy_response = $appointment_service->create_appointment($appointment_model, $scheduler_id);
        
        if (is_wp_error($proxy_response)) {
            $error_data = $proxy_response->get_error_data();
            $validation_errors = null;
            if (is_array($error_data) && !empty($error_data['errors'])) {
                $validation_errors = $error_data['errors'];
            }
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'proxy_request_failed',
                    'proxy_error'  => true,
                    'error_reason' => $proxy_response->get_error_message(),
                    'message'      => 'שגיאה בקביעת התור בפרוקסי: ' . $proxy_response->get_error_message(),
                    'validation_errors' => is_array($validation_errors) ? $validation_errors : null,
                ),
                $profile,
                $selected_patient,
                $appointment_model
            );
            return;
        }

        if (!$proxy_response->is_success()) {
            $proxy_message = !empty($proxy_response->error) ? $proxy_response->error : 'אנא נסה שוב.';
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'proxy_api_rejected',
                    'proxy_error'  => true,
                    'error_reason' => $proxy_message,
                    'message'      => 'שגיאה בקביעת התור בפרוקסי. ' . $proxy_message,
                ),
                $profile,
                $selected_patient,
                $appointment_model
            );
            return;
        }
        
        // Step 5: Save appointment to local appointments table
        $clinic_id = 0;
        $doctor_id = 0;
        if ($scheduler_id > 0) {
            $clinic_id  = (int) get_post_meta($scheduler_id, 'clinic_id', true);
            $doctor_id  = $this->get_doctor_id_from_scheduler($scheduler_id);
        }

        $treatment_type = '';
        if (!empty($_POST['treatment_type'])) {
            $treatment_type = sanitize_text_field($_POST['treatment_type']);
        } elseif (!empty($appointment_data['treatment_type'])) {
            $treatment_type = (string) $appointment_data['treatment_type'];
        }
        if ($proxy_schedule_id === '' && !empty($appointment_data['proxy_schedule_id'])) {
            $proxy_schedule_id = (string) $appointment_data['proxy_schedule_id'];
        }
        
        $row = array(
            'wp_clinic_id' => $clinic_id ?: 1,
            'wp_doctor_id' => $doctor_id ?: 1,
            'wp_schedule_id' => $scheduler_id,
            'patient_first_name' => $profile['first_name'],
            'patient_last_name' => $profile['last_name'] ?? '',
            'patient_phone' => $profile['primary_phone'],
            'patient_email' => $profile['email'] ?? null,
            'patient_id_number' => $profile['identity'] ?: null,
            'appointment_datetime' => $fromUTC,
            'duration' => $duration,
            'treatment_type' => $treatment_type ?: null,
            'remark' => $combined_remark,
            'first_visit' => ($first_visit === 'כן' || $first_visit === 'yes' || $first_visit === '1') ? 1 : 0,
            'proxy_schedule_id' => $proxy_schedule_id ?: null,
            'proxy_appointment_id' => isset($proxy_response->result) ? (string) $proxy_response->result : null,
            'created_by' => $user_id,
        );
        
        $appointment_id = $db_manager->create_appointment($row);
        
        if ($appointment_id === false) {
            $this->send_booking_json_error(
                array(
                    'error_code'   => 'db_save_failed',
                    'error_reason' => __('התור נוצר בפרוקסי אך השמירה המקומית נכשלה.', 'clinic-queue-management'),
                    'message'      => 'שגיאה בשמירת התור במערכת.',
                ),
                $profile,
                $selected_patient,
                $appointment_model
            );
            return;
        }

        $success_payload = $this->build_success_modal_payload(
            $profile['first_name'],
            $appt_date,
            $appt_time,
            $duration,
            $combined_remark ?? '',
            $appointment_data,
            $doctor_id,
            $clinic_id,
            $treatment_type
        );
        $success_payload['appointment_id'] = $appointment_id;
        $success_payload = array_merge(
            $success_payload,
            $this->build_booking_debug_context($profile, $selected_patient, $appointment_model)
        );

        wp_send_json_success($success_payload);
    }
    
    /**
     * HTML for patient select radio buttons (user + family members).
     *
     * @param int $user_id Logged-in user ID.
     * @return string
     */
    private function render_patient_select_radios_html($user_id) {
        $user_id = (int) $user_id;
        $current_user = get_userdata($user_id);
        if (!$current_user) {
            return '';
        }

        $family_members = $this->get_family_members_list($user_id);

        ob_start();
        include __DIR__ . '/views/partials/patient-select-radios.php';

        return (string) ob_get_clean();
    }

    /**
     * AJAX Handler: Refresh family list HTML
     */
    public function handle_refresh_family_list_html() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
            return;
        }

        wp_send_json_success(array(
            'html' => $this->render_patient_select_radios_html($user_id),
        ));
    }
}
