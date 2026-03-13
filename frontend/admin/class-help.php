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
        
        // Calendar icon for help view (same source as booking-calendar and schedule-form)
        $calendar_icon_svg = '';
        if (class_exists('Clinic_Schedule_Form_Manager')) {
            $calendar_icon_svg = Clinic_Schedule_Form_Manager::load_icon_from_assets('Calendar.svg', 24, 24);
        }
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/views/help-html.php';
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

