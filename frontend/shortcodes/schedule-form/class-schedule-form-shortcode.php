<?php
/**
 * Schedule Form Shortcode Class
 * Main class for [clinic_add_schedule_form] shortcode
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Schedule_Form_Shortcode
 * Manages the schedule form shortcode functionality
 */
class Clinic_Schedule_Form_Shortcode {
    
    /**
     * Singleton instance
     * 
     * @var Clinic_Schedule_Form_Shortcode
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Schedule_Form_Shortcode
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
    }
    
    /**
     * Register the shortcode
     */
    public function register_shortcode() {
        add_shortcode('clinic_add_schedule_form', array($this, 'render_shortcode'));
    }
    
    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts = array()) {
        // Parse attributes (if any in the future)
        $atts = shortcode_atts(array(
            // Future attributes can be added here
        ), $atts, 'clinic_add_schedule_form');
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Prepare data for view
        $data = $this->prepare_data();
        
        // Start output buffering
        ob_start();
        
        // Include the view
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/schedule-form/views/schedule-form-html.php';
        
        // Return buffered content
        return ob_get_clean();
    }
    
    /**
     * Enqueue CSS and JavaScript assets
     */
    private function enqueue_assets() {
        static $enqueued = false;
        
        // Prevent double enqueue
        if ($enqueued) {
            return;
        }
        
        // Enqueue base.css first for CSS variables
        wp_enqueue_style(
            'clinic-queue-base-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Enqueue Select2 CSS
        wp_enqueue_style(
            'select2-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.css',
            array(),
            '4.1.0'
        );
        
        // Enqueue Dashicons for chevron icons (WordPress built-in)
        wp_enqueue_style('dashicons');
        
        // Enqueue Select2 Custom CSS (depends on base.css for CSS variables)
        wp_enqueue_style(
            'select-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/select.css',
            array('clinic-queue-base-css', 'select2-css', 'dashicons'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'schedule-form-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/schedule-form.css',
            array('select2-css', 'select-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Enqueue Select2 JS
        wp_enqueue_script(
            'select2-js',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );
        
        // Enqueue Utils module first (no dependencies)
        wp_enqueue_script(
            'clinic-schedule-form-utils',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/schedule-form/js/modules/schedule-form-utils.js',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Enqueue Google Auth module
        wp_enqueue_script(
            'clinic-schedule-form-google-auth',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/schedule-form/js/modules/schedule-form-google-auth.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Enqueue JavaScript modules in correct order
        $modules = array(
            'schedule-form-data',
            'schedule-form-steps',
            'schedule-form-ui',
            'schedule-form-field-manager',
            'schedule-form-form-manager',
            'schedule-form-google-calendar-manager',
            'schedule-form-core',
            'schedule-form-init'
        );
        
        $module_handles = array('clinic-schedule-form-utils', 'clinic-schedule-form-google-auth');
        
        foreach ($modules as $module) {
            $handle = "clinic-{$module}";
            $module_handles[] = $handle;
            
            // Determine dependencies based on module
            $dependencies = array('jquery', 'clinic-schedule-form-utils');
            
            // Add module-specific dependencies
            if ($module === 'schedule-form-steps') {
                // Steps depends on data
                $dependencies[] = 'clinic-schedule-form-data';
            } elseif ($module === 'schedule-form-ui') {
                // UI depends on data and steps
                $dependencies[] = 'clinic-schedule-form-data';
                $dependencies[] = 'clinic-schedule-form-steps';
            } elseif ($module === 'schedule-form-field-manager') {
                // Field Manager depends on data and UI
                $dependencies[] = 'clinic-schedule-form-data';
                $dependencies[] = 'clinic-schedule-form-ui';
            } elseif ($module === 'schedule-form-form-manager') {
                // Form Manager depends on data, steps, and UI
                $dependencies[] = 'clinic-schedule-form-data';
                $dependencies[] = 'clinic-schedule-form-steps';
                $dependencies[] = 'clinic-schedule-form-ui';
            } elseif ($module === 'schedule-form-google-calendar-manager') {
                // Google Calendar Manager depends on data, steps, UI, and google-auth
                $dependencies[] = 'clinic-schedule-form-data';
                $dependencies[] = 'clinic-schedule-form-steps';
                $dependencies[] = 'clinic-schedule-form-ui';
                $dependencies[] = 'clinic-schedule-form-google-auth';
            } elseif ($module === 'schedule-form-core') {
                // Core depends on all managers
                $dependencies[] = 'clinic-schedule-form-data';
                $dependencies[] = 'clinic-schedule-form-steps';
                $dependencies[] = 'clinic-schedule-form-ui';
                $dependencies[] = 'clinic-schedule-form-google-auth';
                $dependencies[] = 'clinic-schedule-form-field-manager';
                $dependencies[] = 'clinic-schedule-form-form-manager';
                $dependencies[] = 'clinic-schedule-form-google-calendar-manager';
            } elseif ($module === 'schedule-form-init') {
                // Init depends on core
                $dependencies[] = 'clinic-schedule-form-data';
                $dependencies[] = 'clinic-schedule-form-steps';
                $dependencies[] = 'clinic-schedule-form-ui';
                $dependencies[] = 'clinic-schedule-form-google-auth';
                $dependencies[] = 'clinic-schedule-form-field-manager';
                $dependencies[] = 'clinic-schedule-form-form-manager';
                $dependencies[] = 'clinic-schedule-form-google-calendar-manager';
                $dependencies[] = 'clinic-schedule-form-core';
            }
            
            wp_enqueue_script(
                $handle,
                CLINIC_QUEUE_MANAGEMENT_URL . "frontend/shortcodes/schedule-form/js/modules/{$module}.js",
                $dependencies,
                CLINIC_QUEUE_MANAGEMENT_VERSION,
                true
            );
        }
        
        // Enqueue main script
        wp_enqueue_script(
            'schedule-form-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/schedule-form/js/schedule-form.js',
            array_merge(array('jquery', 'select2-js'), $module_handles),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Load Google credentials
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/config/google-credentials.php';
        
        // Localize script with configuration data
        wp_localize_script('schedule-form-script', 'scheduleFormData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('clinic-queue/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'saveNonce' => wp_create_nonce('save_clinic_schedule'),
            'currentUserId' => get_current_user_id(),
            'clinicsEndpoint' => rest_url('wp/v2/clinics'),
            'clinicsListEndpoint' => rest_url('wp/v2/clinics?per_page=30&author=' . get_current_user_id()),
            'doctorsEndpoint' => rest_url('wp/v2/doctors/'),
            'jetRelEndpoint' => rest_url('jet-rel'),
            'relationId' => 185, // Relation 185: Clinic (parent) -> Doctor (child)
            // Google Calendar Config
            'googleClientId' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
            'googleScopes' => defined('GOOGLE_CALENDAR_SCOPES') ? GOOGLE_CALENDAR_SCOPES : '',
            'i18n' => array(
                'loading' => __('טוען...', 'clinic-queue-management'),
                'error' => __('שגיאה', 'clinic-queue-management'),
                'noClinicSelected' => __('לא נבחרה מרפאה', 'clinic-queue-management'),
                'noDoctorSelected' => __('לא נבחר רופא', 'clinic-queue-management'),
                'saving' => __('שומר...', 'clinic-queue-management'),
                'selectDay' => __('אנא בחר לפחות יום עבודה אחד', 'clinic-queue-management'),
                'addTreatment' => __('אנא הוסף לפחות טיפול אחד', 'clinic-queue-management'),
            )
        ));
        
        $enqueued = true;
    }
    
    /**
     * Prepare data for the view
     * 
     * @return array Data for view
     */
    private function prepare_data() {
        $icons = Clinic_Schedule_Form_Manager::get_svg_icons();
        
        return array(
            'svg_google_calendar' => $icons['google_calendar'],
            'svg_clinix_logo' => $icons['clinix_logo'],
            'svg_calendar_icon' => $icons['calendar_icon'],
            'svg_trash_icon' => $icons['trash_icon'],
            'svg_checkbox_checked' => $icons['checkbox_checked'],
            'svg_checkbox_unchecked' => $icons['checkbox_unchecked'],
            'days_of_week' => Clinic_Schedule_Form_Manager::get_days_of_week(),
            'generate_day_time_range_callback' => array('Clinic_Schedule_Form_Manager', 'generate_day_time_range'),
            'generate_duration_options_callback' => array('Clinic_Schedule_Form_Manager', 'generate_duration_options'),
        );
    }
}

