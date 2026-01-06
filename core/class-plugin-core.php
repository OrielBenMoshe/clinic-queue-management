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
        // Load feature toggle to check if widget is disabled
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-feature-toggle.php';
        $feature_toggle = Clinic_Queue_Feature_Toggle::get_instance();
        
        // Always register the widget hook regardless of requirements (unless disabled)
        // Use high priority (20) to ensure we register AFTER JetForms and other plugins
        // This prevents conflicts with their assets
        if (!$feature_toggle->is_disabled('WIDGET')) {
            add_action('elementor/widgets/register', array($this, 'register_widgets'), 20);
            
            // Also try the init hook for Elementor widgets (fallback)
            add_action('elementor/init', array($this, 'on_elementor_init'), 20);
        }
        
        // Check requirements and show notices, but don't block plugin loading (unless disabled)
        if (!$feature_toggle->is_disabled('VERSION_CHECK')) {
            add_action('admin_notices', array($this, 'admin_notice_requirements'));
        }
        
        // Validate WordPress version (critical - block if not met)
        if (!$this->check_wp_requirements()) {
            return;
        }

        // Load required files
        $this->load_dependencies();
        
        $feature_toggle = Clinic_Queue_Feature_Toggle::get_instance();
        
        // Initialize Schedule Form Shortcode (if not disabled)
        if (!$feature_toggle->is_disabled('SHORTCODE')) {
            // Initialize Schedule Form Shortcode only if JetFormBuilder is available
            // This prevents errors if JetFormBuilder is not loaded yet
            // Only load if JetFormBuilder is actually required AND available
            if ($this->is_jetform_required()) {
                // Only initialize if JetFormBuilder is actually available
                if ($this->check_jetform_requirements()) {
                    Clinic_Schedule_Form_Shortcode::get_instance();
                }
                // If JetFormBuilder is required but not available, we'll show a warning notice
                // but won't break the site
            } else {
                // If JetFormBuilder is not required, we can still load the shortcode
                // (though it probably won't work without JetFormBuilder)
                Clinic_Schedule_Form_Shortcode::get_instance();
            }
            
            // Initialize Booking Calendar Shortcode (NEW - independent of JetFormBuilder)
            Clinic_Booking_Calendar_Shortcode::get_instance();
        }
        
        // Initialize AJAX handlers (if not disabled)
        if (!$feature_toggle->is_disabled('AJAX')) {
            Clinic_Queue_Ajax_Handlers::get_instance();
        }
        
        // Initialize REST API handlers (if not disabled)
        if (!$feature_toggle->is_disabled('REST_API')) {
            Clinic_Queue_Rest_Handlers::get_instance();
        }
        
        // Initialize admin menu (if not disabled)
        if (!$feature_toggle->is_disabled('ADMIN_MENU')) {
            Clinic_Queue_Admin_Menu::get_instance();
        }
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
               file_exists(CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/class-schedule-form-shortcode.php');
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
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load feature toggle first
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-feature-toggle.php';
        $feature_toggle = Clinic_Queue_Feature_Toggle::get_instance();
        
        // Show status notice if any features are disabled
        add_action('admin_notices', array($feature_toggle, 'show_status_notice'));
        
        // Core classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-helpers.php';
        
        // API classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-api-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-rest-handlers.php';
        
        // Frontend classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-widget-fields-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/class-shortcode-handler.php';
        
        // Schedule Form Shortcode
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/managers/class-schedule-form-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/class-schedule-form-shortcode.php';
        
        // Booking Calendar Shortcode (NEW)
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/booking-calendar/class-booking-calendar-shortcode.php';
        
        // DON'T load widget class here - it will be loaded on-demand in register_widgets()
        
        // Admin classes - Load services and handlers FIRST (before classes that depend on them)
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/services/class-encryption-service.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/handlers/class-settings-handler.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/ajax/class-ajax-handlers.php';
        
        // Admin classes - Load after dependencies
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-dashboard.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-help.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-settings.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-admin-menu.php';
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
     * Enhanced with comprehensive error handling to prevent breaking Elementor
     */
    public function register_widgets($widgets_manager) {
        try {
            // Check Elementor requirements before registering widget
            if (!$this->check_elementor_requirements()) {
                return;
            }

            // Minimal guard: ensure Elementor base exists
            if (!class_exists('Elementor\Widget_Base')) {
                return;
            }

            // Ensure dependencies are loaded
            try {
                $this->load_widget_dependencies();
            } catch (Exception $e) {
                return;
            } catch (Error $e) {
                return;
            }

            // Load widget class if not already loaded
            if (!class_exists('Clinic_Queue_Widget')) {
                try {
                    require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/clinic-queue/class-clinic-queue-widget.php';
                } catch (Exception $e) {
                    return;
                } catch (Error $e) {
                    return;
                }
            }
            
            if (!class_exists('Clinic_Queue_Widget')) {
                return;
            }
            
            try {
                $widget_instance = new Clinic_Queue_Widget();
                $widgets_manager->register($widget_instance);
            } catch (Exception $e) {
                // Silent fail to avoid breaking Elementor
            } catch (Error $e) {
                // Silent fail to avoid breaking Elementor
            }
        } catch (Exception $e) {
            // Top-level catch - absolutely prevent breaking Elementor
        } catch (Error $e) {
            // Top-level catch - absolutely prevent breaking Elementor
        }
    }
    
    /**
     * Load widget dependencies only
     * Enhanced with error handling and validation
     */
    private function load_widget_dependencies() {
        // Load constants first
        if (!class_exists('Clinic_Queue_Constants')) {
            $constants_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
            if (file_exists($constants_file)) {
                try {
                    require_once $constants_file;
                } catch (Exception $e) {
                    throw $e;
                }
            } else {
                throw new Exception('Constants file not found');
            }
        }
        
        // Load Widget Fields Manager
        if (!class_exists('Clinic_Queue_Widget_Fields_Manager')) {
            $fields_manager_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-widget-fields-manager.php';
            if (file_exists($fields_manager_file)) {
                try {
                    require_once $fields_manager_file;
                } catch (Exception $e) {
                    throw $e;
                }
            } else {
                throw new Exception('Widget Fields Manager file not found');
            }
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
