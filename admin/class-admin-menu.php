<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/handlers/class-settings-handler.php';
require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-help.php';
require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-dashboard.php';

/**
 * Admin Menu Handler
 * Handles admin menu creation and routing to appropriate handlers
 * 
 * @package ClinicQueue
 * @subpackage Admin
 */
class Clinic_Queue_Admin_Menu {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_Admin_Menu
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
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add main menu page - הגדרות כעמוד ראשי
        add_menu_page(
            __('מערכת ניהול מרפאות', 'clinic-queue'),
            __('מערכת ניהול מרפאות', 'clinic-queue'),
            'manage_options',
            'clinic-queue-settings',
            array($this, 'render_settings'),
            'dashicons-calendar-alt',
            30
        );
        
        // Add submenu - מדריך שימוש
        add_submenu_page(
            'clinic-queue-settings',
            __('מדריך שימוש', 'clinic-queue'),
            __('מדריך שימוש', 'clinic-queue'),
            'manage_options',
            'clinic-queue-help',
            array($this, 'render_help')
        );
        
        // Rename first submenu item to match parent
        add_submenu_page(
            'clinic-queue-settings',
            __('הגדרות', 'clinic-queue'),
            __('הגדרות', 'clinic-queue'),
            'manage_options',
            'clinic-queue-settings',
            array($this, 'render_settings')
        );
    }
    
    /**
     * Render settings page
     * Routes to Settings Handler
     */
    public function render_settings() {
        $handler = Clinic_Queue_Settings_Handler::get_instance();
        $handler->render_page();
    }
    
    /**
     * Render help page
     * Routes to Help Handler
     */
    public function render_help() {
        $help = Clinic_Queue_Help_Admin::get_instance();
        $help->render_page();
    }
}
