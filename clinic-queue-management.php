<?php
/**
 * Plugin Name: Clinic Queue Management
 * Plugin URI: 
 * Description: Elementor widget for medical clinic appointment queue management
 * Version: 0.2.23
 * Author: 
 * Text Domain: clinic-queue-management
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('CLINIC_QUEUE_MANAGEMENT_VERSION', '0.2.23');
define('CLINIC_QUEUE_MANAGEMENT_PATH', plugin_dir_path(__FILE__));
define('CLINIC_QUEUE_MANAGEMENT_URL', plugin_dir_url(__FILE__));


/**
 * Main plugin class - Simplified to use the new core structure
 */
class Clinic_Queue_Management_Plugin {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    
    public function init() {
        // Load the core plugin logic
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-plugin-core.php';
        
        // Initialize the core
        $core = Clinic_Queue_Plugin_Core::get_instance();
        $core->init();
        
        // Add Assistant font to admin and frontend
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assistant_font'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assistant_font'));
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