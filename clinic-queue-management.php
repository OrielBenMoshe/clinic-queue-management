<?php
/**
 * Plugin Name: מערכת ניהול מרפאות
 * Plugin URI: 
 * Description: מערכת מקיפה לניהול יומני מרפאות, טפסים, API ושורטקודים
 * Version: 0.4.06
 * Author: Oriel Ben-Moshe
 * Text Domain: clinic-queue-management
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


define('CLINIC_QUEUE_MANAGEMENT_VERSION', '0.4.06');
define('CLINIC_QUEUE_MANAGEMENT_PATH', plugin_dir_path(__FILE__));
// Force HTTPS for asset URL when page is HTTPS to avoid Mixed Content (fonts/resources blocked)
define('CLINIC_QUEUE_MANAGEMENT_URL', is_ssl() ? set_url_scheme(plugin_dir_url(__FILE__), 'https') : plugin_dir_url(__FILE__));
define('CLINIC_QUEUE_MANAGEMENT_FILE', __FILE__);

// Load debug configuration if file exists
if (file_exists(CLINIC_QUEUE_MANAGEMENT_PATH . 'debug-config.php')) {
    require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'debug-config.php';
}


/**
 * Main plugin class - Simplified to use the new core structure
 */
class Clinic_Queue_Management_Plugin {
    
    public function __construct() {
        // Load Database Manager early (needs to register activation hook)
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-database-manager.php';
        Clinic_Queue_Database_Manager::get_instance();
        
        // Use priority 20 to load after JetFormBuilder (which loads at priority 10)
        add_action('plugins_loaded', array($this, 'init'), 20);
    }
    
    
    public function init() {
        // Load constants first (includes hardcoded API endpoint and token)
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        
        // Load the core plugin logic
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-plugin-core.php';
        
        // Initialize the core
        $core = Clinic_Queue_Plugin_Core::get_instance();
        $core->init();
        
        // Load feature toggle to check if CSS/JS are disabled
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-feature-toggle.php';
        $feature_toggle = Clinic_Queue_Feature_Toggle::get_instance();
        
        // Add Assistant font to admin and frontend (if not disabled)
        if (!$feature_toggle->is_disabled('CSS')) {
            // Only enqueue on admin pages or pages that actually use our widgets/shortcodes
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assistant_font'));
            // Don't enqueue globally on frontend - let widgets/shortcodes enqueue their own assets
            // add_action('wp_enqueue_scripts', array($this, 'enqueue_assistant_font'));

            // טעינת סטיילי השורטקודים בתצוגת המקדימה של עורך Elementor (כששמים וידג'ט שורטקוד)
            add_action('wp_enqueue_scripts', array($this, 'register_shortcode_styles_for_editor_preview'), 5);
            add_action('elementor/preview/enqueue_styles', array($this, 'enqueue_shortcode_styles_in_editor_preview'));
        }
    }
    
    /**
     * רישום סטיילי השורטקודים כדי שיהיו זמינים לתצוגת המקדימה של Elementor.
     * נקרא מ-wp_enqueue_scripts (רישום בלבד, בלי טעינה).
     */
    public function register_shortcode_styles_for_editor_preview() {
        if (!did_action('elementor/loaded')) {
            return;
        }
        wp_register_style(
            'clinic-queue-main',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        wp_register_style(
            'select2-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.css',
            array(),
            '4.1.0'
        );
        wp_register_style(
            'schedule-form-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/schedule-form.css',
            array('clinic-queue-main', 'select2-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
    }

    /**
     * טעינת סטיילי השורטקודים בתצוגת המקדימה של עורך Elementor.
     * מאפשר לראות את העיצוב הנכון כשמוסיפים וידג'ט שורטקוד (לוח תורים / טופס תזמון).
     */
    public function enqueue_shortcode_styles_in_editor_preview() {
        $feature_toggle = Clinic_Queue_Feature_Toggle::get_instance();
        if ($feature_toggle->is_disabled('CSS')) {
            return;
        }
        wp_enqueue_style('clinic-queue-main');
        wp_enqueue_style('select2-css');
        wp_enqueue_style('schedule-form-css');
        wp_enqueue_style('dashicons');
    }

    /**
     * Enqueue Assistant font
     */
    public function enqueue_assistant_font() {
        // Enqueue the global Assistant font CSS file
        wp_enqueue_style(
            'clinic-queue-assistant-font',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Also enqueue Google Fonts directly as fallback
        wp_enqueue_style(
            'clinic-queue-google-assistant-font',
            'https://fonts.googleapis.com/css2?family=Assistant:wght@200;300;400;500;600;700;800&display=swap',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
    }
}

// Cleanup on deactivation (if needed in the future)
register_deactivation_hook(__FILE__, function() {
    // No cleanup needed - no database or cron jobs to clean
});

// Initialize the plugin
new Clinic_Queue_Management_Plugin();