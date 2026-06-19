<?php
/**
 * Booking Calendar Shortcode Class
 * Main class for [booking_calendar] shortcode
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Booking_Calendar_Shortcode
 * Manages the booking calendar shortcode functionality
 */
class Clinic_Booking_Calendar_Shortcode {

    /**
     * Post types שמפעילים כרטיס מובייל (singular או כרטיס בלולאת ארכיון).
     */
    private const MOBILE_CTA_POST_TYPES = array('doctors', 'clinics');

    /**
     * Singleton instance
     * 
     * @var Clinic_Booking_Calendar_Shortcode
     */
    private static $instance = null;
    
    /**
     * Data provider instance
     */
    private $data_provider;

    /**
     * האם הודפס שורטקוד booking calendar בבקשה הנוכחית.
     *
     * משמש כדי לרנדר את מודל ה-singleton רק בעמודים שבהם
     * השורטקוד אכן הופיע, ולא בכל עמוד באתר.
     *
     * @var bool
     */
    private $should_render_expanded_modal = false;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Booking_Calendar_Shortcode
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
        $this->load_dependencies();
        $this->register_shortcode();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once __DIR__ . '/managers/class-calendar-data-provider.php';
        $this->data_provider = Booking_Calendar_Data_Provider::get_instance();
    }
    
    /**
     * Register the shortcode
     */
    public function register_shortcode() {
        add_shortcode('booking_calendar', array($this, 'render_shortcode'));
        add_action('wp_footer', array($this, 'render_expanded_modal_singleton'));
    }

    /**
     * מרנדר את skeleton המודל המורחב פעם אחת ב-wp_footer.
     * הוא singleton – קיים פעם אחת ב-DOM ומשותף לכל יומני התורים בעמוד.
     * התוכן הדינמי מתמלא ע"י booking-calendar-expanded-modal.js בכל פתיחה.
     */
    public function render_expanded_modal_singleton() {
        if (!$this->should_render_expanded_modal) {
            return;
        }

        include __DIR__ . '/views/booking-calendar-expanded-modal.php';
    }
    
    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        $this->should_render_expanded_modal = true;

        // Parse attributes
        $atts = shortcode_atts(array(
            'mode'           => 'auto', // auto|doctor|clinic
            'doctor_id'      => '',
            'clinic_id'      => '',
            'treatment_type' => '',
            'slot_rows'      => 4, // כמה שורות סלוטים (דסקטופ; מובייל = כרטיס נפרד)
            'mobile_cta'     => 'auto', // auto|yes|no – כרטיס מובייל booking-calendar-mobile-cta
        ), $atts, 'booking_calendar');
        
        // Auto-detect from context
        $context = $this->auto_detect_context();
        
        // Merge with attributes (attributes override auto-detection)
        $settings = $this->merge_settings($atts, $context);

        // טיפולים נאספים ב-JS מהיומנים שנטענו.
        $treatments = array();

        // Load all schedulers with all meta fields based on mode
        $all_schedulers = array();
        if ($settings['mode'] === 'clinic' && !empty($settings['clinic_id'])) {
            // Clinic mode: get all schedulers for this clinic via relations
            $all_schedulers = $this->data_provider->get_schedulers_by_clinic($settings['clinic_id']);
        } elseif ($settings['mode'] === 'doctor' && !empty($settings['doctor_id'])) {
            // Doctor mode: get all schedulers for this doctor via relations
            $all_schedulers = $this->data_provider->get_schedulers_by_doctor($settings['doctor_id']);
        }

        // Convert associative array to numeric array for JavaScript
        // get_schedulers_by_clinic() returns [scheduler_id => [...]] but JS needs array of objects
        $all_schedulers_array = array();
        if (!empty($all_schedulers) && is_array($all_schedulers)) {
            foreach ($all_schedulers as $scheduler_id => $scheduler_data) {
                // Ensure scheduler_data has 'id' field
                if (is_array($scheduler_data)) {
                    $scheduler_data['id'] = $scheduler_id;
                    $all_schedulers_array[] = $scheduler_data;
                }
            }
        }

        // Enqueue assets
        $this->enqueue_assets();

        /*
         * Per-instance widget id.
         * Required when multiple shortcode instances exist in the same page (e.g. listings),
         * so each instance can consume its own localized schedulers data without being overridden.
         */
        $widget_id = 'booking-calendar-' . wp_unique_id();

        // Pass schedulers data to JavaScript per widget instance
        $instance_payload = array(
            'schedulers' => $all_schedulers_array,
            'settings'   => $settings,
        );
        // לפני init (לא main): ב-footer ה-DOM לעיתים כבר ready ו-init רץ מיד — הנתונים חייבים להיות זמינים לפני init.
        wp_add_inline_script(
            'booking-calendar-init',
            'window.bookingCalendarInitialDataByWidget = window.bookingCalendarInitialDataByWidget || {};'
            . 'window.bookingCalendarInitialDataByWidget[' . wp_json_encode($widget_id) . '] = '
            . wp_json_encode($instance_payload) . ';',
            'before'
        );
        
        // Loading placeholder icon from assets/images/icons (same convention as schedule-form)
        $loading_placeholder_icon = '';
        if (class_exists('Clinic_Schedule_Form_Manager')) {
            $loading_placeholder_icon = Clinic_Schedule_Form_Manager::load_icon_from_assets('calendar-pink-icon.svg', 32, 32);
        }

        // When there are no schedulers, show empty state (card with message by clinic/doctor)
        $empty_calendars = (count($all_schedulers_array) === 0);
        $empty_state_message = '';
        if ($empty_calendars) {
            $empty_state_message = ($settings['mode'] === 'clinic')
                ? __('לא קיימים יומנים פעילים למרפאה זו.', 'clinic-queue-management')
                : __('לא קיימים יומנים פעילים לרופא/מטפל זה.', 'clinic-queue-management');
        }
        $empty_state_icon = $loading_placeholder_icon;

        // מובייל: כרטיס קומפקטי + פנל fullscreen (ראה should_enable_mobile_cta).
        $enable_mobile_cta          = $this->should_enable_mobile_cta($atts['mobile_cta'], $settings);
        $show_elementor_editor_hint = $this->is_elementor_editor_context();

        // Render HTML
        ob_start();
        include __DIR__ . '/views/booking-calendar-html.php';
        return ob_get_clean();
    }
    
    /**
     * האם להפעיל תצוגת מובייל לפי שורטקוד + הקשר עמוד.
     *
     * @param string $mobile_cta_attr auto|yes|no
     * @param array  $settings        הגדרות ממוזגות (mode, clinic_id, doctor_id).
     * @return bool
     */
    private function should_enable_mobile_cta($mobile_cta_attr, array $settings) {
        $forced = $this->parse_bool_attr($mobile_cta_attr);
        if ($forced !== null) {
            return $forced;
        }

        return $this->should_enable_mobile_cta_by_context($settings);
    }

    /**
     * זיהוי אוטומטי: כרטיס עם מזהה ישות, ארכיון, חיפוש, או doctors/clinics בלולאה.
     *
     * חשוב: ב-AJAX (למשל csr_load_calendar בליסטינג מרפאות) is_archive() הוא false —
     * לכן כרטיס עם clinic_id/doctor_id מפורש מפעיל מובייל גם בלי הקשר ארכיון.
     *
     * @param array $settings
     * @return bool
     */
    private function should_enable_mobile_cta_by_context(array $settings) {
        if ($this->has_resolved_entity_for_mobile($settings)) {
            return true;
        }

        if ($this->is_doctors_or_clinics_post_context()) {
            return true;
        }

        if (function_exists('is_search') && is_search()) {
            return true;
        }

        if (function_exists('is_post_type_archive') && is_post_type_archive(self::MOBILE_CTA_POST_TYPES)) {
            return true;
        }

        if (function_exists('is_archive') && is_archive()) {
            return !(function_exists('is_home') && is_home());
        }

        return false;
    }

    /**
     * יש מזהה מרפאה/רופא לכרטיס (שורטקוד בלולאה, AJAX, או singular).
     *
     * @param array $settings
     * @return bool
     */
    private function has_resolved_entity_for_mobile(array $settings) {
        $mode = isset($settings['mode']) ? (string) $settings['mode'] : 'doctor';

        if ($mode === 'clinic') {
            return absint($settings['clinic_id'] ?? 0) > 0;
        }

        if ($mode === 'doctor') {
            return absint($settings['doctor_id'] ?? 0) > 0;
        }

        return false;
    }

    /**
     * global $post הוא רופא/מרפאה (לולאת ארכיון / JetEngine / Elementor).
     *
     * @return bool
     */
    private function is_doctors_or_clinics_post_context() {
        global $post;

        if (!$post || !isset($post->ID)) {
            return false;
        }

        return in_array(get_post_type($post), self::MOBILE_CTA_POST_TYPES, true);
    }

    /**
     * @param string $value
     * @return bool|null null = auto
     */
    private function parse_bool_attr($value) {
        $value = strtolower(trim((string) $value));
        if (in_array($value, array('yes', '1', 'true', 'on'), true)) {
            return true;
        }
        if (in_array($value, array('no', '0', 'false', 'off'), true)) {
            return false;
        }
        return null;
    }

    /**
     * הצגת הסבר בעורך Elementor בלבד.
     *
     * @return bool
     */
    private function is_elementor_editor_context() {
        if (!empty($_GET['elementor-preview'])) {
            return true;
        }

        if (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::$instance;

        if (isset($elementor->editor) && $elementor->editor->is_edit_mode()) {
            return true;
        }

        return isset($elementor->preview) && $elementor->preview->is_preview_mode();
    }

    /**
     * Auto-detect context from current post
     * 
     * @return array Context information
     */
    private function auto_detect_context() {
        global $post;
        
        $context = array(
            'mode' => 'doctor',
            'doctor_id' => null,
            'clinic_id' => null,
        );
        
        if (!$post) {
            return $context;
        }
        
        $post_type = get_post_type($post);
        
        if ($post_type === 'doctors') {
            $context['mode'] = 'doctor';
            $context['doctor_id'] = $post->ID;
        } elseif ($post_type === 'clinics') {
            $context['mode'] = 'clinic';
            $context['clinic_id'] = $post->ID;
        }
        
        return $context;
    }
    
    /**
     * Merge settings from attributes and context
     * Attributes override auto-detection
     * 
     * @param array $atts Shortcode attributes
     * @param array $context Auto-detected context
     * @return array Merged settings
     */
    private function merge_settings($atts, $context) {
        $resolved_doctor = !empty($atts['doctor_id']) ? $atts['doctor_id'] : $context['doctor_id'];
        $resolved_clinic = !empty($atts['clinic_id']) ? $atts['clinic_id'] : $context['clinic_id'];

        return array(
            'mode'           => $atts['mode'] !== 'auto' ? $atts['mode'] : $context['mode'],
            'doctor_id'      => ($resolved_doctor !== null && $resolved_doctor !== '') ? (int) $resolved_doctor : null,
            'clinic_id'      => ($resolved_clinic !== null && $resolved_clinic !== '') ? (int) $resolved_clinic : null,
            'treatment_type' => $atts['treatment_type'],
            'slot_rows'      => max(1, intval($atts['slot_rows'])),
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

        // CSS - use shared styles
        wp_enqueue_style(
            'booking-calendar-base',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Buttons CSS - required for .btn, .btn-primary, .btn-secondary classes
        wp_enqueue_style(
            'booking-calendar-buttons',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/buttons.css',
            array('booking-calendar-base'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_style(
            'booking-calendar-style',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/appointments-calendar.css',
            array('booking-calendar-base', 'booking-calendar-buttons'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // Custom date picker (replaces native browser date input popup)
        wp_enqueue_style(
            'clinic-queue-date-picker-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/date-picker.css',
            array('booking-calendar-base', 'booking-calendar-style'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_style('dashicons');
        
        // Select2 (if not already loaded)
        if (!wp_script_is('select2', 'enqueued') && !wp_script_is('select2-js', 'enqueued')) {
            wp_enqueue_style(
                'booking-calendar-select2-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.css',
                array(),
                '4.1.0'
            );
            
            wp_enqueue_script(
                'booking-calendar-select2-js',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.js',
                array('jquery'),
                '4.1.0',
                true
            );
        }
        
        // Select custom CSS
        wp_enqueue_style(
            'booking-calendar-select-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/select.css',
            array('booking-calendar-base', 'dashicons'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Custom date picker JS (must load before the expanded-modal module
        // so its pointer-events:none + stopPropagation intercept is in place
        // when the modal's click handlers are bound on first open).
        wp_enqueue_script(
            'clinic-queue-date-picker',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/date-picker.js',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        // JavaScript modules
        $modules = array(
            'booking-calendar-utils',
            'booking-calendar-data-manager',
            'booking-calendar-ui-manager',
            'booking-calendar-field-manager',
            'booking-calendar-expanded-modal',
            'booking-calendar-mobile-compact',
            'booking-calendar-core',
            'booking-calendar-init',
        );
        
        foreach ($modules as $i => $module) {
            wp_enqueue_script(
                $module,
                CLINIC_QUEUE_MANAGEMENT_URL . "frontend/shortcodes/booking-calendar/js/modules/{$module}.js",
                $i === 0 ? array('jquery') : array('booking-calendar-utils'),
                CLINIC_QUEUE_MANAGEMENT_VERSION,
                true
            );
        }
        
        // Main script
        wp_enqueue_script(
            'booking-calendar-main',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/booking-calendar/js/booking-calendar.js',
            $modules,
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script data (empty for now, data loaded via API)
        // Find booking page dynamically by shortcode
        $helpers = Clinic_Queue_Helpers::get_instance();
        $booking_page_id = $helpers->find_page_by_shortcode('booking_form');
        
        // Fallback to hardcoded ID if not found (for backward compatibility)
        if (!$booking_page_id) {
            $booking_page_id = 4366;
        }
        
        $booking_page_url = get_permalink($booking_page_id);
        if (!$booking_page_url) {
            // Fallback: build URL manually if permalink not available
            $booking_page_url = home_url('/?p=' . $booking_page_id);
        }
        
        $calendar_icon_url = defined('CLINIC_QUEUE_MANAGEMENT_URL')
            ? CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/icons/calendar-pink-icon.svg'
            : '';
        wp_localize_script('booking-calendar-main', 'bookingCalendarData', array(
            'appointments' => array(),
            'doctors' => array(),
            'clinics' => array(),
            'treatments' => array(),
            'settings' => array(),
            'pageUrls' => array(
                $booking_page_id => $booking_page_url
            ),
            'bookingPageId' => $booking_page_id,
            'calendarIconUrl' => $calendar_icon_url
        ));
        
        // AJAX data (use clinicQueueAjax for consistency with widget)
        wp_localize_script('booking-calendar-main', 'clinicQueueAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxurl' => admin_url('admin-ajax.php'), // Support both naming conventions
            'nonce' => wp_create_nonce('clinic_queue_ajax'),
            'current_user_id' => get_current_user_id()
        ));

        $assets_loaded = true;
    }
}

