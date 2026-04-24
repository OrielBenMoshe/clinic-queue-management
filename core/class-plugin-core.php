<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Core Class
 * נקודת כניסה ראשית: bootstrap, טעינת תלויות, אתחול shortcodes, שירותי core (AJAX, REST), אדמין.
 *
 * @package Clinic_Queue_Management
 * @subpackage Core
 */
class Clinic_Queue_Plugin_Core {

    private static $instance = null;
    private $min_wp_version = '6.0';
    private $min_elementor_version = '3.30.0';
    private $min_jetform_version = '3.0.0';
    
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
        $feature_toggle = $this->bootstrap_core();
        if (!$feature_toggle) {
            return;
        }

        $this->load_dependencies();

        $this->init_shortcodes($feature_toggle);
        $this->init_core_services($feature_toggle);
        $this->init_admin($feature_toggle);
    }

    /**
     * Bootstrap: feature toggle, JetEngine, hooks, WP requirements.
     *
     * @return Clinic_Queue_Feature_Toggle|null Feature toggle instance or null if requirements failed
     */
    private function bootstrap_core() {
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-feature-toggle.php';
        $feature_toggle = Clinic_Queue_Feature_Toggle::get_instance();

        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-jetengine-integration.php';
        Clinic_Queue_JetEngine_Integration::get_instance();

        if (!$feature_toggle->is_disabled('VERSION_CHECK')) {
            add_action('admin_notices', array($this, 'admin_notice_requirements'));
        }

        if (!$this->check_wp_requirements()) {
            return null;
        }

        return $feature_toggle;
    }

    /**
     * Initialize shortcodes (schedule form, booking calendar, booking form).
     */
    private function init_shortcodes($feature_toggle) {
        if ($feature_toggle->is_disabled('SHORTCODE')) {
            return;
        }

        if ($this->is_jetform_required()) {
            if ($this->check_jetform_requirements()) {
                Clinic_Schedule_Form_Shortcode::get_instance();
            }
        } else {
            Clinic_Schedule_Form_Shortcode::get_instance();
        }

        Clinic_Booking_Calendar_Shortcode::get_instance();
        Clinic_Booking_Form_Shortcode::get_instance();
        Clinic_Doctor_Calendar_Connect_Shortcode::get_instance();
    }

    /**
     * Initialize core services: AJAX endpoints, REST API.
     */
    private function init_core_services($feature_toggle) {
        if (!$feature_toggle->is_disabled('AJAX')) {
            Clinic_Queue_Ajax_Handlers::get_instance();
        }

        if (!$feature_toggle->is_disabled('REST_API')) {
            Clinic_Queue_Rest_Handlers::get_instance();
        }
    }

    /**
     * Initialize admin UI (menu, dashboard, etc.).
     */
    private function init_admin($feature_toggle) {
        if ($feature_toggle->is_disabled('ADMIN_MENU')) {
            return;
        }
        Clinic_Queue_Admin_Menu::get_instance();
    }

    /**
     * Check minimum WP requirements (critical - blocks plugin)
     */
    private function check_wp_requirements() {
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
     * Check if JetFormBuilder is required (used in shortcodes)
     */
    private function is_jetform_required() {
        // Check if schedule form shortcode exists (uses JetFormBuilder)
        return class_exists('Clinic_Schedule_Form_Shortcode') || 
               file_exists(CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/schedule-form/class-schedule-form-shortcode.php');
    }
    
    /**
     * Check JetFormBuilder requirements
     * Uses multiple methods to detect JetFormBuilder
     */
    private function check_jetform_requirements() {
        // Method 1: Check if constant is defined (most reliable - loaded early)
        if (defined('JET_FORM_BUILDER_VERSION')) {
            // Check version if defined
            if (version_compare(JET_FORM_BUILDER_VERSION, $this->min_jetform_version, '<')) {
                return false;
            }
            return true;
        }
        
        // Method 2: Check if main class exists
        if (class_exists('Jet_Form_Builder')) {
            return true;
        }
        
        // Method 3: Check if function exists
        if (function_exists('jet_form_builder')) {
            return true;
        }
        
        // Method 4: Check if plugin is active via WordPress function (requires admin functions)
        if (function_exists('is_plugin_active')) {
            if (is_plugin_active('jet-form-builder/jet-form-builder.php')) {
                return true;
            }
        }
        
        // Method 5: Check if file exists (last resort - doesn't mean it's active)
        if (file_exists(WP_PLUGIN_DIR . '/jet-form-builder/jet-form-builder.php')) {
            // File exists, but we can't be sure it's active without loading admin functions
            // Return true optimistically
            return true;
        }
        
        return false;
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
        $messages = array();
        
        // Check WordPress version
        if (function_exists('get_bloginfo')) {
            $wp_version = get_bloginfo('version');
            if ($wp_version && version_compare($wp_version, $this->min_wp_version, '<')) {
                $messages[] = sprintf(
                    esc_html__('WordPress גרסה %s ומעלה', 'clinic-queue-management'),
                    $this->min_wp_version
                );
            }
        }
        
        // Check Elementor
        if (defined('ELEMENTOR_VERSION')) {
            if (version_compare(ELEMENTOR_VERSION, $this->min_elementor_version, '<')) {
                $messages[] = sprintf(
                    esc_html__('Elementor גרסה %s ומעלה', 'clinic-queue-management'),
                    $this->min_elementor_version
                );
            }
        } elseif (!did_action('elementor/loaded')) {
            $messages[] = sprintf(
                esc_html__('Elementor גרסה %s ומעלה', 'clinic-queue-management'),
                $this->min_elementor_version
            );
        }
        
        // Check JetFormBuilder if required (only show warning, don't block)
        if ($this->is_jetform_required()) {
            $jetform_ok = $this->check_jetform_requirements();
            if (!$jetform_ok) {
                $messages[] = esc_html__('JetFormBuilder תוסף מופעל (נדרש לטופס הוספת יומן)', 'clinic-queue-management');
            } elseif (defined('JET_FORM_BUILDER_VERSION') && 
                      version_compare(JET_FORM_BUILDER_VERSION, $this->min_jetform_version, '<')) {
                $messages[] = sprintf(
                    esc_html__('JetFormBuilder גרסה %s ומעלה (מומלץ לעדכן)', 'clinic-queue-management'),
                    $this->min_jetform_version
                );
            }
        }
        
        if (!empty($messages)) {
            // Use warning for non-critical issues (like JetFormBuilder), error for critical (like WP version)
            $has_critical = false;
            foreach ($messages as $msg) {
                if (strpos($msg, 'WordPress') !== false || strpos($msg, 'Elementor') !== false) {
                    $has_critical = true;
                    break;
                }
            }
            
            $notice_class = $has_critical ? 'notice-error' : 'notice-warning';
            echo '<div class="notice ' . $notice_class . '"><p><strong>' 
                . esc_html__('תוסף Clinic Queue Management:', 'clinic-queue-management')
                . '</strong> ' . implode(', ', $messages) . '</p></div>';
        }
    }
    
    /**
     * Load plugin dependencies (core → config → API → frontend → admin).
     */
    private function load_dependencies() {
        // ─── Core ─────────────────────────────────────────────────────────
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-feature-toggle.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-helpers.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-specialty-taxonomy.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-ajax-handlers.php';
        Clinic_Queue_Specialty_Taxonomy::get_instance();

        // ─── Config ───────────────────────────────────────────────────────
        $google_credentials_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'api/config/google-credentials.php';
        if (file_exists($google_credentials_file)) {
            require_once $google_credentials_file;
        }

        // ─── API ──────────────────────────────────────────────────────────
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-api-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/handlers/class-base-handler.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/handlers/class-relations-jet-api-handler.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-rest-handlers.php';

        // ─── Frontend (shortcodes only; widgets folder removed) ───────────
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/schedule-form/managers/class-schedule-form-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/schedule-form/class-schedule-form-shortcode.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/booking-calendar/class-booking-calendar-shortcode.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/booking-form/class-booking-form-shortcode.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/doctor-calendar-connect/class-doctor-calendar-connect-shortcode.php';

        // ─── Admin (services & handlers first, then UI) ────────────────────
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/services/class-encryption-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/handlers/class-settings-handler.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/handlers/class-treatment-specialty-handler.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/class-dashboard.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/class-help.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/class-admin-menu.php';

        Clinic_Queue_Treatment_Specialty_Handler::get_instance();
    }

}
