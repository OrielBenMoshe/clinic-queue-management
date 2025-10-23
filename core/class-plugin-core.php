<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Core Class
 * Central logic and initialization
 */
class Clinic_Queue_Plugin_Core {
    
    private static $instance = null;
    private $min_wp_version = '6.2';
    private $min_elementor_version = '3.12.0';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Always register the widget hook regardless of requirements
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        
        // Also try the init hook for Elementor widgets (fallback)
        add_action('elementor/init', array($this, 'on_elementor_init'));
        
        // Validate other requirements before proceeding with core functionality
        if (!$this->check_core_requirements()) {
            add_action('admin_notices', array($this, 'admin_notice_requirements'));
            return;
        }

        // Load required files
        $this->load_dependencies();
        
        // Initialize database
        $this->init_database();
        
        // Initialize cron jobs
        $this->init_cron_jobs();
        
        // Initialize AJAX handlers
        Clinic_Queue_Ajax_Handlers::get_instance();
        
        // Initialize REST API handlers
        Clinic_Queue_Rest_Handlers::get_instance();
        
        // Initialize admin menu
        Clinic_Queue_Admin_Menu::get_instance();
    }

    /**
     * Check minimum WP requirements for core functionality
     */
    private function check_core_requirements() {
        // Check WordPress version
        if (function_exists('get_bloginfo')) {
            $wp_version = get_bloginfo('version');
            if ($wp_version && version_compare($wp_version, $this->min_wp_version, '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check minimum WP and Elementor requirements for widget functionality
     */
    private function check_elementor_requirements() {
        // Check WordPress version
        if (function_exists('get_bloginfo')) {
            $wp_version = get_bloginfo('version');
            if ($wp_version && version_compare($wp_version, $this->min_wp_version, '<')) {
                return false;
            }
        }

        // Check Elementor is loaded and meets minimum version
        if (!did_action('elementor/loaded')) {
            return false;
        }

        if (defined('ELEMENTOR_VERSION')) {
            if (version_compare(ELEMENTOR_VERSION, $this->min_elementor_version, '<')) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Admin notice when requirements are not met
     */
    public function admin_notice_requirements() {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('תוסף Clinic Queue Management דורש WordPress גרסה ' . $this->min_wp_version . ' ומעלה ו-Elementor גרסה ' . $this->min_elementor_version . ' ומעלה. אנא עדכן והפעל את Elementor.', 'clinic-queue-management')
            . '</p></div>';
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-database-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-appointment-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-helpers.php';
        
        // API classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-api-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-rest-handlers.php';
        
        // Frontend classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-widget-fields-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/class-shortcode-handler.php';
        
        // DON'T load widget class here - it will be loaded on-demand in register_widgets()
        
        // Admin classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-dashboard.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-calendars.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-sync-status.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-cron-jobs.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-cron-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-ajax-handlers.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-admin-menu.php';
    }
    
    /**
     * Initialize database
     */
    private function init_database() {
        $db_manager = Clinic_Queue_Database_Manager::get_instance();
        
        // Check if tables exist, create if not
        if (!$db_manager->tables_exist()) {
            $db_manager->create_tables();
        } else {
            // Update existing database structure if needed
            $db_manager->update_database_structure();
        }
        
        // Initialize default calendars from mock data
        $this->initialize_default_calendars();
    }
    
    /**
     * Initialize default calendars from mock data
     */
    private function initialize_default_calendars() {
        $json_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        
        foreach ($data['calendars'] as $calendar) {
            // Initialize calendar for this doctor-clinic-treatment combination
            $api_manager->sync_from_api(
                $calendar['doctor_id'], 
                isset($calendar['clinic_id']) ? $calendar['clinic_id'] : '', 
                $calendar['treatment_type']
            );
        }
    }
    
    /**
     * Initialize cron jobs
     */
    private function init_cron_jobs() {
        $cron_manager = Clinic_Queue_Cron_Manager::get_instance();
        $cron_manager->init_cron_jobs();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Widget AJAX handlers are now auto-registered by Widget_Ajax_Handlers class
        // when Fields Manager is initialized (it loads the handlers automatically)
        
        // Just initialize the Fields Manager - it will load and initialize the AJAX handlers
        Clinic_Queue_Widget_Fields_Manager::get_instance();
    }
    
    
    /**
     * Called when Elementor initializes
     */
    public function on_elementor_init() {
        // Try to register widgets directly with Elementor
        if (class_exists('Elementor\Plugin')) {
            $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
            if ($widgets_manager) {
                $this->register_widgets($widgets_manager);
            }
        }
    }
    

    /**
     * Register Elementor widgets
     */
    public function register_widgets($widgets_manager) {
        
        // Check Elementor requirements before registering widget
        if (!$this->check_elementor_requirements()) {
            return;
        }

        // Minimal guard: ensure Elementor base exists
        if (!class_exists('Elementor\Widget_Base')) {
            return;
        }

        // Ensure dependencies are loaded
        $this->load_widget_dependencies();

        // Load widget class if not already loaded
        if (!class_exists('Clinic_Queue_Widget')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-clinic-queue-widget.php';
        }
        
        if (!class_exists('Clinic_Queue_Widget')) {
            return;
        }
        
        $widgets_manager->register(new Clinic_Queue_Widget());
    }
    
    /**
     * Load widget dependencies only
     */
    private function load_widget_dependencies() {
        // Load only the dependencies needed for the widget
        if (!class_exists('Clinic_Queue_Constants')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        }
        if (!class_exists('Clinic_Queue_Widget_Fields_Manager')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-widget-fields-manager.php';
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
