<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu Handler for Clinic Queue Management
 * Handles admin menu creation and page rendering
 */
class Clinic_Queue_Admin_Menu {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'ניהול תורי מרפאה',
            'ניהול תורים',
            'manage_options',
            'clinic-queue-management',
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'clinic-queue-management',
            'הגדרות',
            'הגדרות',
            'manage_options',
            'clinic-queue-settings',
            array($this, 'render_settings')
        );
        
        add_submenu_page(
            'clinic-queue-management',
            'מדריך שימוש',
            'מדריך שימוש',
            'manage_options',
            'clinic-queue-help',
            array($this, 'render_help')
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $dashboard = Clinic_Queue_Dashboard_Admin::get_instance();
        $dashboard->render_page();
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        $settings = Clinic_Queue_Settings_Admin::get_instance();
        $settings->render_page();
    }
    
    /**
     * Render help page
     */
    public function render_help() {
        $help = Clinic_Queue_Help_Admin::get_instance();
        $help->render_page();
    }
    
}
