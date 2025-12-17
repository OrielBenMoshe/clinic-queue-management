<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Help/Guide Admin Page
 * Provides documentation and usage instructions for the widget
 */
class Clinic_Queue_Help_Admin {
    
    private static $instance = null;
    
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
     * Render help page
     */
    public function render_page() {
        // Enqueue assets
        $this->enqueue_assets();
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/help-html.php';
    }
    
    /**
     * Enqueue help page assets
     */
    private function enqueue_assets() {
        // Enqueue main CSS file
        wp_enqueue_style(
            'clinic-queue-main',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
    }
}

