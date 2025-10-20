<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Admin Page
 * Main dashboard with statistics and quick actions
 */
class Clinic_Queue_Dashboard_Admin {
    
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
     * Render dashboard page
     */
    public function render_page() {
        // Enqueue assets
        $this->enqueue_assets();
        
        // Get data
        $data = $this->get_dashboard_data();
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/dashboard-html.php';
    }
    
    /**
     * Enqueue dashboard assets
     */
    private function enqueue_assets() {
        // Enqueue Assistant font first
        wp_enqueue_style(
            'clinic-queue-assistant-font',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/global-assistant-font.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_style(
            'clinic-queue-dashboard-style',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/css/dashboard.css',
            array('clinic-queue-assistant-font'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_script(
            'clinic-queue-dashboard-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/js/dashboard.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('clinic-queue-dashboard-script', 'clinicQueueDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_dashboard')
        ));
    }
    
    /**
     * Get dashboard data
     */
    private function get_dashboard_data() {
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        
        return array(
            'calendars_count' => $this->get_calendars_count(),
            'total_appointments' => $this->get_total_appointments(),
            'booked_appointments' => $this->get_booked_appointments(),
            'sync_status' => $api_manager->get_sync_status(),
            'recent_bookings' => $this->get_recent_bookings()
        );
    }
    
    /**
     * Get calendars count
     */
    private function get_calendars_count() {
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_calendars");
    }
    
    /**
     * Get total appointments count
     */
    private function get_total_appointments() {
        global $wpdb;
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_times");
    }
    
    /**
     * Get booked appointments count
     */
    private function get_booked_appointments() {
        global $wpdb;
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_times WHERE is_booked = 1");
    }
    
    /**
     * Get recent bookings
     */
    private function get_recent_bookings() {
        global $wpdb;
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        
        return $wpdb->get_results(
            "SELECT t.patient_name, t.time_slot, d.appointment_date 
             FROM $table_times t 
             JOIN $table_dates d ON t.date_id = d.id 
             WHERE t.is_booked = 1 AND t.patient_name IS NOT NULL 
             ORDER BY d.appointment_date DESC, t.time_slot DESC 
             LIMIT 10"
        );
    }
    
    /**
     * Get synced calendars count
     */
    private function get_synced_calendars_count($sync_status) {
        $synced = 0;
        foreach ($sync_status as $calendar) {
            if ($calendar->sync_status === 'synced') {
                $synced++;
            }
        }
        return $synced;
    }
    
    /**
     * Get sync status icon
     */
    private function get_sync_status_icon($status) {
        switch ($status) {
            case 'synced':
                return '✅';
            case 'stale':
                return '⚠️';
            case 'outdated':
                return '❌';
            default:
                return '❓';
        }
    }
    
    /**
     * Get last sync time
     */
    private function get_last_sync_time() {
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $last_sync = $wpdb->get_var("SELECT MAX(last_updated) FROM $table_calendars");
        return $last_sync ? date('d/m/Y H:i', strtotime($last_sync)) : 'לא ידוע';
    }
    
    /**
     * Get cron status
     */
    private function get_cron_status() {
        $next_scheduled = wp_next_scheduled('clinic_queue_auto_sync');
        if ($next_scheduled) {
            return 'פעיל - הבא: ' . date('d/m/Y H:i', $next_scheduled);
        }
        return 'לא פעיל';
    }
}
