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
    
    /** @var string מפתח רפיטר בני משפחה ב-user meta */
    private const FAMILY_REPEATER_META_KEY = 'family_members';

    /** @var string מפתח ת.ז. של יוזר ראשי */
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
        // Parse attributes
        $atts = shortcode_atts(array(
            'popup_id' => '3953', // ID של הפופאפ להוספת בן משפחה
        ), $atts, 'booking_form');
        
        // משתמש לא מחובר: תצוגת טופס התחברות (ברירת מחדל [mad_login_form]); אחרי התחברות — ריענון העמוד מציג את טופס קביעת התור.
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
                'popup_id'                   => $atts['popup_id'],
                'guest_login_html_fragment'  => $guest_login_html_fragment,
            );
            ob_start();
            include __DIR__ . '/views/booking-form-html.php';
            return ob_get_clean();
        }

        // Enqueue assets
        $this->enqueue_assets($atts);

        // Prepare data for view
        $data = $this->prepare_data($atts);
        
        // Start output buffering
        ob_start();
        
        // Include the view
        include __DIR__ . '/views/booking-form-html.php';
        
        // Return buffered content
        return ob_get_clean();
    }
    
    /**
     * טעינת CSS משותף לטופס קביעת תור (משתמש מחובר ואורח)
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
     * נכסים לאורח: עיצוב סיכום תור + טופס התחברות בלבד (ללא JS של קביעת התור).
     *
     * @param array<string, string>|null $register_gate_markup מפתחות loginHtml/registerHtml למעבר הרשמה/התחברות מהדפדפן.
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
     * מחרוזת השורטקוד לטופס התחברות אורח (פילטרים כמו בשכבת התצוגה הקודמת).
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
     * מחרוזת השורטקוד לטופס הרשמה אורח.
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
     * כתובת עמוד להפניה בלחיצה על "סגור" במודאל הצלחה
     *
     * @return string URL מלא או מחרוזת ריקה אם העמוד לא קיים
     */
    private function get_booking_success_close_redirect_url() {
        $page_id = 2907;
        $url     = get_permalink($page_id);

        return $url ? esc_url($url) : '';
    }

    /**
     * תאריך תור לתצוגה (d/m/Y)
     *
     * @param string $appt_date תאריך בפורמט Y-m-d או d/m/Y
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
     * נרמול מחרוזת תצוגה — הסרת backslashes מיותרים ו-decode של entities לפני escape.
     *
     * @param string $value מחרוזת גולמית (query param, post title וכו').
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
     * מיקום מרפאה לתצוגה במודאל וביומן Google
     *
     * @param array<string, mixed> $appointment_data נתוני תור מ-query או מטא
     * @param int                  $clinic_id        מזהה מרפאה
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
     * שם רופא לתצוגה במודאל הצלחה (רק כשיש doctor_id תקף).
     *
     * @param array<string, mixed> $appointment_data נתוני תור
     * @param int                  $doctor_id        מזהה רופא
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
     * שם יומן (מטא schedule_name) לתצוגה כשאין רופא משויך.
     * אם schedule_name ריק — נפילה לכותרת הפוסט של היומן.
     *
     * @param int $scheduler_id מזהה פוסט יומן (schedules CPT).
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
     * שם לתצוגה בשורת "רופא מטפל" ובמודאל הצלחה: רופא אם קיים, אחרת schedule_name של היומן.
     *
     * @param array<string, mixed> $appointment_data נתוני תור.
     * @param int                  $doctor_id        מזהה רופא (ממטא היומן בלבד).
     * @param int                  $scheduler_id     מזהה פוסט יומן.
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
     * קישור לדף הרופא (פוסט CPT) לפי מזהה.
     *
     * @param int $doctor_id מזהה פוסט רופא.
     * @return string URL או מחרוזת ריקה.
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
     * בניית מערך נתונים למודאל הצלחה (תגובת AJAX)
     *
     * @param string               $patient_name     שם מטופל
     * @param string               $appt_date        תאריך (Y-m-d)
     * @param string               $appt_time        שעה
     * @param int                  $duration         משך בדקות
     * @param string               $notes            הערות
     * @param array<string, mixed> $appointment_data נתוני תור משלימים
     * @param int                  $doctor_id        מזהה רופא
     * @param int                  $clinic_id        מזהה מרפאה
     * @param string               $treatment_type   סוג טיפול (גולמי)
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
     *
     * @param array<string, string> $atts Shortcode attributes.
     */
    private function enqueue_assets($atts = array()) {
        static $assets_loaded = false;

        if ($assets_loaded) {
            return;
        }

        $popup_id = !empty($atts['popup_id']) ? (string) $atts['popup_id'] : '3953';

        $this->enqueue_booking_form_styles();

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
            'familyPopupId'    => $popup_id,
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
                'familyFormPartialSave' => __(
                    'בן המשפחה נשמר, אך השרת החזיר שגיאה בתשובה. הרשימה עודכנה — אם חסר, רענן את העמוד.',
                    'clinic-queue-management'
                ),
            ),
        ));

        $assets_loaded = true;
    }
    
    /**
     * Prepare data for the view
     * 
     * @param array $atts Shortcode attributes
     * @return array Data for view
     */
    private function prepare_data($atts) {
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $family_members = $this->get_family_members_list($user_id);
        
        // Read query parameters from URL (passed from booking calendar)
        $appointment_data = $this->get_appointment_data_from_query();
        
        return array(
            'current_user' => $current_user,
            'family_members' => $family_members,
            'popup_id' => $atts['popup_id'],
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
     * השלמת עלות טיפול ממטא treatments של היומן כשחסר ב-query.
     *
     * @param array<string, mixed> $data נתוני תור (מעודכן in-place).
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
     * שליפת עלות טיפול ממטא treatments של פוסט יומן לפי סוג טיפול.
     *
     * @param int    $scheduler_id   מזהה פוסט יומן.
     * @param string $treatment_type מזהה/סלאג סוג טיפול.
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
     * פורמט תצוגה לעלות טיפול (₪).
     *
     * @param int|string $cost עלות בשקלים.
     * @return string מחרוזת ריקה אם אין עלות תקפה.
     */
    private function format_treatment_cost_display($cost) {
        $cost = absint($cost);
        if ($cost <= 0) {
            return '';
        }

        return '₪ ' . number_format_i18n($cost);
    }

    /**
     * מזהה רופא מטא של פוסט יומן (scheduler).
     *
     * @param int $scheduler_id מזהה פוסט יומן.
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
     * השלמת שם, התמחות ותמונת רופא מיומן כשחסר ב-query.
     *
     * @param array<string, mixed> $data נתוני תור (מעודכן in-place).
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
     * השלמת שם, כתובת, תמונה והתמחויות מרפאה לסיכום התור.
     *
     * @param array<string, mixed> $data נתוני תור (מעודכן in-place).
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
     * מזהה מרפאה מפרמטרי query או מטא של יומן.
     *
     * @param array<string, mixed> $data נתוני תור.
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
     * תמונת מרפאה: מטא clinc_img (JetEngine) או תמונה ראשית של הפוסט.
     *
     * @param int $clinic_id מזהה פוסט מרפאה.
     * @return string URL או מחרוזת ריקה.
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
     * התמחויות מרפאה: טקסונומיית specialties, ואם ריק — מטא clinic_specialization.
     *
     * @param int $clinic_id מזהה פוסט מרפאה.
     * @return string רשימה מופרדת בפסיקים לתצוגה.
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
     * תצוגת התמחות רופא: מטא specialty, ואם ריק — terms בטקסונומיית specialties.
     *
     * @param int $doctor_id מזהה פוסט רופא.
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
     * טקסונומיית סוג טיפול לאתר (Jet/תוסף: לעיתים treatment_type, לעיתים treatment_types).
     *
     * @return string סלאג ריק אם אף אחת לא רשומה.
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
     * המרת מזהה טרם / סלאג של סוג טיפול לשם תצוגה.
     *
     * @param string $raw ערך מ-query string (למשל treatment_type=123).
     * @return string שם הטרם; אם לא נמצא — הערך המקורי (תאימות לאחור).
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
     * בדיקה האם ליוזר יש תעודת זהות תקינה בפרופיל.
     *
     * @param int $user_id מזהה משתמש.
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
     * שליפת רשימת בני משפחה מנורמלת (אינדקסים 0, 1, 2…).
     * JetEngine עשוי לשמור מפתחות כמו item-0 — לא מתאימים ל-(int) ב-submit.
     *
     * @param int $user_id מזהה משתמש.
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
                $list[] = $member;
            }
        }

        return $list;
    }

    /**
     * שליפת פרופיל מטופל לפי בחירה בטופס (יוזר ראשי או בן משפחה).
     *
     * @param int    $user_id          מזהה משתמש מחובר.
     * @param string $selected_patient ערך patient_select מהטופס.
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

        $profile = array(
            'first_name'    => $first_name,
            'last_name'     => (string) ($current_user->last_name ?? ''),
            'email'         => $current_user->user_email,
            'primary_phone' => trim((string) get_user_meta($user_id, 'phone', true)),
            'identity'      => Clinic_Queue_Helpers::normalize_israeli_id_number(
                (string) get_user_meta($user_id, self::USER_ID_NUMBER_META_KEY, true)
            ),
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
        $profile['is_self']    = false;
        $profile['first_name'] = !empty(trim((string) ($member['first_name'] ?? '')))
            ? (string) $member['first_name']
            : $current_user->display_name;
        $profile['last_name']  = (string) ($member['last_name'] ?? '');
        $profile['identity'] = Clinic_Queue_Helpers::normalize_israeli_id_number(
            (string) ($member['id_number'] ?? '')
        );
        $profile['gender']   = Clinic_Queue_Helpers::map_gender_for_api(
            (string) ($member['gender'] ?? '')
        );
        $member_dob = trim((string) ($member['dob'] ?? ''));
        $profile['birth_date'] = $member_dob !== '' ? $member_dob : null;

        return $profile;
    }

    /**
     * בניית הערת פגישה משולבת (טלפון נוסף + הערה אישית).
     *
     * @param string $additional_phone טלפון נוסף מהטופס.
     * @param string $personal_note    הערה אישית מהטופס.
     * @return string|null
     */
    private function build_appointment_remark($additional_phone, $personal_note) {
        $parts = array();

        $additional_phone = trim((string) $additional_phone);
        $personal_note    = trim((string) $personal_note);

        if ($additional_phone !== '') {
            $parts[] = sprintf(
                /* translators: %s additional phone number */
                __('מספר טלפון נוסף: %s', 'clinic-queue-management'),
                $additional_phone
            );
        }

        if ($personal_note !== '') {
            $parts[] = sprintf(
                /* translators: %s patient note */
                __('הערת מטופל: %s', 'clinic-queue-management'),
                $personal_note
            );
        }

        if (empty($parts)) {
            return null;
        }

        return implode("\n", $parts);
    }

    /**
     * ולידציית פרופיל מטופל לפני קביעת תור.
     *
     * @param array<string, mixed> $profile פרופיל מטופל.
     * @return array<string, mixed>|null מערך שגיאה ל-wp_send_json_error או null אם תקין.
     */
    private function get_patient_profile_booking_error(array $profile) {
        $is_self = !empty($profile['is_self']);

        $identity = isset($profile['identity']) ? (string) $profile['identity'] : '';

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
            return array(
                'error_code'   => 'family_missing_id_number',
                'error_reason' => __('למטופל שנבחר חסרה תעודת זהות בפרופיל (שדה id_number ברפיטר family_members).', 'clinic-queue-management'),
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

        if (empty($profile['primary_phone'])) {
            return array(
                'error_code'   => 'missing_primary_phone',
                'error_reason' => __('חסר מספר טלפון ראשי (phone) בפרופיל המשתמש המחובר — הוא נשלח כ-mobilePhone ל-API.', 'clinic-queue-management'),
                'message'      => __('חסר מספר טלפון ראשי בפרופיל שלך. אנא עדכן את הפרטים האישיים.', 'clinic-queue-management'),
            );
        }

        return null;
    }

    /**
     * בניית Customer Model מפרופיל מטופל.
     *
     * @param array<string, mixed> $profile פרופיל מטופל.
     * @return Clinic_Queue_Customer_Model
     */
    private function build_customer_from_profile(array $profile) {
        $customer = new Clinic_Queue_Customer_Model();
        $customer->firstName = $profile['first_name'];
        $customer->lastName = $profile['last_name'];
        $customer->identity = $profile['identity'];
        $customer->identityType = 'TZ';
        $customer->email = $profile['email'];
        $customer->mobilePhone = $profile['primary_phone'];

        if (!empty($profile['gender'])) {
            $customer->gender = $profile['gender'];
        }

        if (!empty($profile['birth_date'])) {
            try {
                $birth_dt = new DateTime($profile['birth_date']);
                $customer->birthDate = $birth_dt->format('Y-m-d\TH:i:s\Z');
            } catch (Exception $e) {
                $customer->birthDate = '1970-01-01T00:00:00Z';
            }
        } else {
            $customer->birthDate = '1970-01-01T00:00:00Z';
        }

        return $customer;
    }

    /**
     * סיכום מטופל שנשלף מהשרת (לא מה-POST) — לדיבוג בתשובת AJAX.
     *
     * @param array<string, mixed> $profile פרופיל מטופל.
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
     * תצוגת payload כפי שנשלח ל-Appointment/Create (לאחר ניקוי כמו ב-proxy service).
     *
     * @param Clinic_Queue_Appointment_Model $appointment_model מודל תור.
     * @return array<string, mixed>
     */
    private function build_proxy_api_payload_preview(Clinic_Queue_Appointment_Model $appointment_model) {
        $data = $appointment_model->to_array();

        if ($data['customer'] instanceof Clinic_Queue_Customer_Model) {
            $customer = $data['customer']->to_array();
            if (!isset($customer['gender']) || !in_array($customer['gender'], array('Male', 'Female'), true)) {
                unset($customer['gender']);
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
     * הקשר דיבוג לתשובת AJAX (מטופל מהשרת + payload לפרוקסי).
     *
     * @param array<string, mixed>              $profile           פרופיל מטופל.
     * @param string                              $selected_patient  patient_select מהטופס.
     * @param Clinic_Queue_Appointment_Model|null $appointment_model מודל תור (אופציונלי).
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
     * שליחת שגיאת AJAX עם הקשר מטופל ו-payload (אם זמין).
     *
     * @param array<string, mixed>              $error             שדות שגיאה (message, error_code…).
     * @param array<string, mixed>|null         $profile           פרופיל מטופל.
     * @param string                              $selected_patient  patient_select.
     * @param Clinic_Queue_Appointment_Model|null $appointment_model מודל תור.
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
     * AJAX Handler: שמירת תעודת זהות ליוזר הראשי.
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
        
        // טעינת Services ו-Handler
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-scheduler-proxy-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-appointment-proxy-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-scheduler-model.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-appointment-model.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-database-manager.php';
        
        $scheduler_service = new Clinic_Queue_Scheduler_Proxy_Service();
        $appointment_service = new Clinic_Queue_Appointment_Proxy_Service();
        $db_manager = Clinic_Queue_Database_Manager::get_instance();
        
        // איסוף נתונים מהטופס
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

        // אם scheduler_id לא בטופס, נסה לקחת מה-URL
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
        
        // מזהה היומן לפרוקסי API – הפרוקסי מצפה ל-proxy_schedule_id (מזהה חיצוני), לא ל-WordPress post ID
        $api_scheduler_id = !empty($proxy_schedule_id) && is_numeric($proxy_schedule_id)
            ? intval($proxy_schedule_id)
            : $scheduler_id;
        
        // איסוף נתוני מטופל מפרופיל (meta / רפיטר)
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

        $combined_remark = $this->build_appointment_remark($additional_phone, $personal_note);
        
        // המרת תאריך ושעה ל-UTC ISO 8601
        $timezone_string = get_option('timezone_string');
        if (empty($timezone_string)) {
            $timezone_string = 'Asia/Jerusalem'; // ברירת מחדל
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
        
        // שלב 1: בדיקת זמינות הסלוט דרך Scheduler Service (הפרוקסי מצפה ל-api_scheduler_id)
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

        // בדיקה אם התור תפוס
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

        // שלב 2: בניית Customer Model
        $customer = $this->build_customer_from_profile($profile);
        
        // שלב 3: בניית Appointment Model (schedulerID = מזהה היומן במערכת הפרוקסי)
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
        
        // שלב 4: קביעת התור בפרוקסי דרך Appointment Service
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
        
        // שלב 5: שמירת התור בטבלת התורים במסד הנתונים
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
     * HTML לרדיו-באטונים של בחירת מטופל (יוזר + בני משפחה).
     *
     * @param int $user_id מזהה משתמש מחובר.
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
