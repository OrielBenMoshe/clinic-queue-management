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
            'יומנים',
            'יומנים',
            'manage_options',
            'clinic-queue-calendars',
            array($this, 'render_calendars')
        );
        
        add_submenu_page(
            'clinic-queue-management',
            'סטטוס סנכרון',
            'סטטוס סנכרון',
            'manage_options',
            'clinic-queue-sync',
            array($this, 'render_sync_status')
        );
        
        add_submenu_page(
            'clinic-queue-management',
            'משימות אוטומטיות',
            'משימות אוטומטיות',
            'manage_options',
            'clinic-queue-cron',
            array($this, 'render_cron_jobs')
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
     * Render calendars page
     */
    public function render_calendars() {
        $calendars = Clinic_Queue_Calendars_Admin::get_instance();
        $calendars->render_page();
    }
    
    /**
     * Render sync status page
     */
    public function render_sync_status() {
        $sync_status = Clinic_Queue_Sync_Status_Admin::get_instance();
        $sync_status->render_page();
    }
    
    /**
     * Render cron jobs page
     */
    public function render_cron_jobs() {
        $cron_jobs = Clinic_Queue_Cron_Jobs_Admin::get_instance();
        $cron_jobs->render_page();
    }
}
