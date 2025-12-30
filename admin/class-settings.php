<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/handlers/class-settings-handler.php';

/**
 * Settings Admin Page (Legacy Wrapper)
 * This class is kept for backward compatibility
 * All business logic is in Clinic_Queue_Settings_Handler
 * 
 * @package ClinicQueue
 * @subpackage Admin
 * @deprecated Use Clinic_Queue_Settings_Handler directly
 */
class Clinic_Queue_Settings_Admin {
    
    private static $instance = null;
    private $handler;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_Settings_Admin
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->handler = Clinic_Queue_Settings_Handler::get_instance();
    }
    
    /**
     * Render settings page
     * Delegates to handler
     */
    public function render_page() {
        $this->handler->render_page();
    }
}
