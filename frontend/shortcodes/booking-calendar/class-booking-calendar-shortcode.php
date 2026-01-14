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
     * Filter engine instance
     */
    private $filter_engine;
    
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
        // Load managers
        require_once __DIR__ . '/managers/class-calendar-data-provider.php';
        require_once __DIR__ . '/managers/class-calendar-filter-engine.php';
        
        // Initialize managers
        $this->data_provider = Booking_Calendar_Data_Provider::get_instance();
        $this->filter_engine = Booking_Calendar_Filter_Engine::get_instance();
    }
    
    /**
     * Register the shortcode
     */
    public function register_shortcode() {
        add_shortcode('booking_calendar', array($this, 'render_shortcode'));
    }
    
    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'mode' => 'auto',           // auto|doctor|clinic
            'doctor_id' => '',
            'clinic_id' => '',
            'treatment_type' => '',
        ), $atts, 'booking_calendar');
        
        // Auto-detect from context
        $context = $this->auto_detect_context();
        
        // Merge with attributes (attributes override auto-detection)
        $settings = $this->merge_settings($atts, $context);
        
        // Get options for dropdowns
        $clinics = $this->filter_engine->get_clinics_options($settings['doctor_id'] ?? '1');
        
        // Treatment types are collected from schedulers in JavaScript (both clinic and doctor modes)
        // This ensures we only show treatments that are actually available in the loaded schedulers
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
        
        // Debug: Log scheduler loading (will be visible in JavaScript console via inline script)
        $schedulers_count = is_array($all_schedulers) ? count($all_schedulers) : 0;
        
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
        
        // Debug: Log to JavaScript console via inline script
        $debug_info = array(
            'mode' => $settings['mode'],
            'clinic_id' => $settings['clinic_id'] ?? null,
            'doctor_id' => $settings['doctor_id'] ?? null,
            'schedulers_count_before_conversion' => $schedulers_count,
            'schedulers_count_after_conversion' => count($all_schedulers_array),
            'schedulers_structure' => !empty($all_schedulers) ? 'associative' : 'empty',
            'first_scheduler_sample' => !empty($all_schedulers) ? array_slice($all_schedulers, 0, 1, true) : null
        );
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Pass schedulers data to JavaScript
        // Use numeric array instead of associative array for better JS compatibility
        wp_localize_script('booking-calendar-main', 'bookingCalendarInitialData', array(
            'schedulers' => $all_schedulers_array,
            'settings' => $settings
        ));
        
        // Render HTML
        ob_start();
        include __DIR__ . '/views/booking-calendar-html.php';
        return ob_get_clean();
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
        return array(
            'mode' => $atts['mode'] !== 'auto' ? $atts['mode'] : $context['mode'],
            'doctor_id' => !empty($atts['doctor_id']) ? $atts['doctor_id'] : $context['doctor_id'],
            'clinic_id' => !empty($atts['clinic_id']) ? $atts['clinic_id'] : $context['clinic_id'],
            'treatment_type' => $atts['treatment_type'],
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
        
        // JavaScript modules
        $modules = array(
            'booking-calendar-utils',
            'booking-calendar-data-manager',
            'booking-calendar-ui-manager',
            'booking-calendar-field-manager',
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
        
        wp_localize_script('booking-calendar-main', 'bookingCalendarData', array(
            'appointments' => array(),
            'doctors' => array(),
            'clinics' => array(),
            'treatments' => array(),
            'settings' => array(),
            'pageUrls' => array(
                $booking_page_id => $booking_page_url
            ),
            'bookingPageId' => $booking_page_id // Add page ID for JavaScript
        ));
        
        // AJAX data (use clinicQueueAjax for consistency with widget)
        wp_localize_script('booking-calendar-main', 'clinicQueueAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxurl' => admin_url('admin-ajax.php'), // Support both naming conventions
            'nonce' => wp_create_nonce('clinic_queue_ajax'),
            'current_user_id' => get_current_user_id()
        ));
        
        // Add inline script to ensure initialization in Elementor editor
        wp_add_inline_script('booking-calendar-main', '
            jQuery(document).ready(function($) {
                // Wait a bit for Elementor to finish rendering
                setTimeout(function() {
                    if (typeof window.BookingCalendarManager !== "undefined") {
                        // Re-initialize any widgets that were added
                        $(".booking-calendar-shortcode:not([data-initialized])").each(function() {
                            $(this).attr("data-initialized", "true");
                            new window.BookingCalendarCore(this);
                        });
                    }
                }, 500);
            });
        ');
        
        $assets_loaded = true;
    }
}

