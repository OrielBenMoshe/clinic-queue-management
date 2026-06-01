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
        $this->enqueue_assets();

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
     * מיקום מרפאה לתצוגה במודאל וביומן Google
     *
     * @param array<string, mixed> $appointment_data נתוני תור מ-query או מטא
     * @param int                  $clinic_id        מזהה מרפאה
     * @return string
     */
    private function resolve_clinic_location_display($appointment_data, $clinic_id) {
        $clinic_name    = isset($appointment_data['clinic_name']) ? trim((string) $appointment_data['clinic_name']) : '';
        $clinic_address = isset($appointment_data['clinic_address']) ? trim((string) $appointment_data['clinic_address']) : '';

        if ($clinic_id > 0) {
            if ($clinic_name === '') {
                $clinic_post = get_post($clinic_id);
                if ($clinic_post) {
                    $clinic_name = $clinic_post->post_title;
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
     * שם רופא לתצוגה במודאל הצלחה
     *
     * @param array<string, mixed> $appointment_data נתוני תור
     * @param int                  $doctor_id        מזהה רופא
     * @return string
     */
    private function resolve_doctor_name_for_success($appointment_data, $doctor_id) {
        if (!empty($appointment_data['doctor_name'])) {
            return trim((string) $appointment_data['doctor_name']);
        }

        if ($doctor_id > 0) {
            $doctor_post = get_post($doctor_id);
            if ($doctor_post) {
                return $doctor_post->post_title;
            }
        }

        return '';
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
        $doctor_name = $this->resolve_doctor_name_for_success($appointment_data, $doctor_id);
        $location    = $this->resolve_clinic_location_display($appointment_data, $clinic_id);

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
            'refreshNonce'     => wp_create_nonce('refresh_family_list_nonce'),
            'closeRedirectUrl' => $this->get_booking_success_close_redirect_url(),
            'assets'           => array(
                'confetti'    => CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/confeti.png',
                'successIcon' => CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/vii.png',
            ),
            'i18n'             => array(
                'titlePrefix'       => __('התור ל', 'clinic-queue-management'),
                'titleSuffix'       => __('נקבע בהצלחה!', 'clinic-queue-management'),
                'calendarEventTitle' => __('תור אצל %s', 'clinic-queue-management'),
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
        $repeater_key = 'family_members';
        $family_members = get_user_meta($user_id, $repeater_key, true);
        
        // Read query parameters from URL (passed from booking calendar)
        $appointment_data = $this->get_appointment_data_from_query();
        
        return array(
            'user_id' => $user_id,
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
        $data['doctor_id'] = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
        $data['doctor_name'] = isset($_GET['doctor_name']) ? sanitize_text_field($_GET['doctor_name']) : '';
        $data['doctor_url'] = isset($_GET['doctor_url']) ? esc_url_raw($_GET['doctor_url']) : '';
        $data['doctor_specialty'] = isset($_GET['doctor_specialty']) ? sanitize_text_field($_GET['doctor_specialty']) : '';
        $data['doctor_thumbnail'] = isset($_GET['doctor_thumbnail']) ? esc_url_raw($_GET['doctor_thumbnail']) : '';
        $data['clinic_address'] = isset($_GET['clinic_address']) ? sanitize_text_field($_GET['clinic_address']) : '';
        $data['clinic_name'] = isset($_GET['clinic_name']) ? sanitize_text_field($_GET['clinic_name']) : '';
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
        
        $this->enrich_doctor_fields_in_appointment_data($data);

        $data['treatment_type_display'] = '';
        if ($data['treatment_type'] !== '') {
            $data['treatment_type_display'] = $this->resolve_treatment_type_label($data['treatment_type']);
        }

        $this->enrich_clinic_fields_in_appointment_data($data);

        $doctor_id = $this->get_doctor_id_from_scheduler((int) ($data['scheduler_id'] ?? 0));
        if ($doctor_id <= 0 && !empty($data['doctor_id'])) {
            $doctor_id = (int) $data['doctor_id'];
        }
        $data['doctor_id']             = $doctor_id;
        $data['doctor_name']           = $this->resolve_doctor_name_for_success($data, $doctor_id);
        if (empty($data['doctor_url'])) {
            $data['doctor_url'] = $this->resolve_doctor_permalink($doctor_id);
        }
        $data['appt_date_display']     = $this->format_appointment_date_display($data['date']);

        return $data;
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
        $clinic_id = $this->resolve_clinic_id_from_appointment_data($data);
        if ($clinic_id <= 0) {
            return;
        }

        $data['clinic_id'] = $clinic_id;

        if (empty($data['clinic_name'])) {
            $clinic_post = get_post($clinic_id);
            if ($clinic_post) {
                $data['clinic_name'] = $clinic_post->post_title;
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
     * AJAX Handler: Submit appointment
     */
    public function handle_appointment_submission_ajax() {
        check_ajax_referer('save_booking_ajax_nonce', 'security');
        
        $repeater_key = 'family_members';
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => 'עליך להתחבר למערכת.'));
            return;
        }
        
        // טעינת Services ו-Handler
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-scheduler-proxy-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-appointment-proxy-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-scheduler-model.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-database-manager.php';
        
        $scheduler_service = new Clinic_Queue_Scheduler_Proxy_Service();
        $appointment_service = new Clinic_Queue_Appointment_Proxy_Service();
        $db_manager = Clinic_Queue_Database_Manager::get_instance();
        
        // איסוף נתונים מהטופס
        $selected_patient = sanitize_text_field($_POST['patient_select'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $id_num = sanitize_text_field($_POST['id_number'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $first_visit = sanitize_text_field($_POST['first_visit'] ?? '');
        $appt_date = sanitize_text_field($_POST['appt_date'] ?? '');
        $appt_time = sanitize_text_field($_POST['appt_time'] ?? '');
        $scheduler_id = isset($_POST['scheduler_id']) ? intval($_POST['scheduler_id']) : 0;
        $proxy_schedule_id = isset($_POST['proxy_schedule_id']) ? sanitize_text_field($_POST['proxy_schedule_id']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;

        $appointment_data = $this->get_appointment_data_from_query();

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
        
        // איסוף נתוני מטופל
        $current_user = get_userdata($user_id);
        $first_name = get_user_meta($user_id, 'first_name', true);
        if (empty(trim((string) $first_name))) {
            $first_name = $current_user->display_name;
        }
        $patient_data = array(
            'first_name' => $first_name,
            'last_name' => $current_user->last_name ?? '',
            'email' => $current_user->user_email,
            'phone' => $phone,
            'identity' => $id_num,
            'gender' => 'NotSet',
            'birth_date' => null,
        );
        
        // אם נבחר בן משפחה
        if (strpos($selected_patient, 'family_') !== false) {
            $index = str_replace('family_', '', $selected_patient);
            $family = get_user_meta($user_id, $repeater_key, true);
            if (isset($family[$index])) {
                $member = $family[$index];
                $patient_data['first_name'] = !empty(trim((string) ($member['first_name'] ?? ''))) ? $member['first_name'] : $current_user->display_name;
                $patient_data['last_name'] = $member['last_name'] ?? '';
                $patient_data['email'] = $member['email'] ?? $current_user->user_email;
                $patient_data['gender'] = $member['gender'] ?? 'NotSet';
                $patient_data['birth_date'] = $member['birth_date'] ?? null;
            }
        }
        
        $patient_name = $patient_data['first_name'];
        
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
            wp_send_json_error(array('message' => 'שגיאה בעיבוד תאריך ושעה.'));
            return;
        }
        
        // שלב 1: בדיקת זמינות הסלוט דרך Scheduler Service (הפרוקסי מצפה ל-api_scheduler_id)
        $slot_model = new Clinic_Queue_Check_Slot_Available_Model();
        $slot_model->schedulerID = $api_scheduler_id;
        $slot_model->fromUTC = $fromUTC;
        $slot_model->duration = $duration;
        
        $slot_check = $scheduler_service->check_slot_available($slot_model, $scheduler_id);
        
        if (is_wp_error($slot_check)) {
            wp_send_json_error(array(
                'slot_taken' => true,
                'message' => 'שגיאה בבדיקת זמינות התור: ' . $slot_check->get_error_message()
            ));
            return;
        }
        
        // בדיקה אם התור תפוס
        if (!$slot_check->is_success()) {
            wp_send_json_error(array(
                'slot_taken' => true,
                'message' => 'מצטערים, התור שבחרת כבר נתפס על ידי מישהו אחר. אנא בחר תור אחר.'
            ));
            return;
        }
        
        // שלב 2: בניית Customer Model
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/models/class-appointment-model.php';
        
        $customer = new Clinic_Queue_Customer_Model();
        $customer->firstName = $patient_data['first_name'];
        $customer->lastName = $patient_data['last_name'];
        $customer->identity = $patient_data['identity'];
        $customer->identityType = 'TZ'; // תעודת זהות ישראלית – לפי מפרט API: Undefined | TZ | Passport
        $customer->email = $patient_data['email'];
        $customer->mobilePhone = $patient_data['phone'];
        $customer->gender = $patient_data['gender'];
        
        // המרת תאריך לידה ל-ISO 8601 – שדה חובה לפי מפרט API
        if (!empty($patient_data['birth_date'])) {
            try {
                $birth_dt = new DateTime($patient_data['birth_date']);
                $customer->birthDate = $birth_dt->format('Y-m-d\TH:i:s\Z');
            } catch (Exception $e) {
                $customer->birthDate = '1970-01-01T00:00:00Z'; // fallback
            }
        } else {
            $customer->birthDate = '1970-01-01T00:00:00Z'; // fallback כשחסר – מומלץ להוסיף שדה תאריך לידה לטופס
        }
        
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
        $appointment_model->remark = $notes;
        $appointment_model->duration = $duration;
        
        // שלב 4: קביעת התור בפרוקסי דרך Appointment Service
        $proxy_response = $appointment_service->create_appointment($appointment_model, $scheduler_id);
        
        if (is_wp_error($proxy_response)) {
            wp_send_json_error(array(
                'proxy_error' => true,
                'message' => 'שגיאה בקביעת התור בפרוקסי: ' . $proxy_response->get_error_message()
            ));
            return;
        }
        
        if (!$proxy_response->is_success()) {
            $proxy_message = !empty($proxy_response->error) ? $proxy_response->error : 'אנא נסה שוב.';
            wp_send_json_error(array(
                'proxy_error' => true,
                'message' => 'שגיאה בקביעת התור בפרוקסי. ' . $proxy_message
            ));
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
            'patient_first_name' => $patient_data['first_name'],
            'patient_last_name' => $patient_data['last_name'] ?? '',
            'patient_phone' => $phone,
            'patient_email' => $patient_data['email'] ?? null,
            'patient_id_number' => $id_num ?: null,
            'appointment_datetime' => $fromUTC,
            'duration' => $duration,
            'treatment_type' => $treatment_type ?: null,
            'remark' => $notes ?: null,
            'first_visit' => ($first_visit === 'כן' || $first_visit === 'yes' || $first_visit === '1') ? 1 : 0,
            'proxy_schedule_id' => $proxy_schedule_id ?: null,
            'proxy_appointment_id' => isset($proxy_response->result) ? (string) $proxy_response->result : null,
            'created_by' => $user_id,
        );
        
        $appointment_id = $db_manager->create_appointment($row);
        
        if ($appointment_id === false) {
            wp_send_json_error(array('message' => 'שגיאה בשמירת התור במערכת.'));
            return;
        }
        
        $success_payload = $this->build_success_modal_payload(
            $patient_name,
            $appt_date,
            $appt_time,
            $duration,
            $notes,
            $appointment_data,
            $doctor_id,
            $clinic_id,
            $treatment_type
        );
        $success_payload['appointment_id'] = $appointment_id;

        wp_send_json_success($success_payload);
    }
    
    /**
     * AJAX Handler: Refresh family list HTML
     */
    public function handle_refresh_family_list_html() {
        // Check user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
            return;
        }
        
        $repeater_key = 'family_members';
        $current_user = wp_get_current_user();
        $family_members = get_user_meta($user_id, $repeater_key, true);
        
        ob_start();
        ?>
        <label class="jet-form-builder__field-wrap">
            <input type="radio" name="patient_select" id="pat_self" value="self" class="jet-form-builder__field radio-field" checked>
            <span class="jet-form-builder__field-label">עבורי - <?php echo esc_html($current_user->display_name); ?></span>
        </label>
        <?php if (!empty($family_members) && is_array($family_members)) : ?>
            <?php foreach ($family_members as $index => $member) : 
                $name = isset($member['first_name']) ? $member['first_name'] : 'בן משפחה';
            ?>
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="patient_select" id="pat_<?php echo esc_attr($index); ?>" value="family_<?php echo esc_attr($index); ?>" class="jet-form-builder__field radio-field">
                <span class="jet-form-builder__field-label"><?php echo esc_html($name); ?></span>
            </label>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
}
