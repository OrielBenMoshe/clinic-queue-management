<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Jobs Admin Page
 * Manage automated tasks and scheduling
 */
class Clinic_Queue_Cron_Jobs_Admin {
    
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
     * Render cron jobs page
     */
    public function render_page() {
        // Enqueue assets
        $this->enqueue_assets();
        
        // Get data
        $data = $this->get_cron_jobs_data();
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/cron-jobs-html.php';
    }
    
    /**
     * Enqueue cron jobs assets
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
            'clinic-queue-cron-jobs-style',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/css/cron-jobs.css',
            array('clinic-queue-assistant-font'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_script(
            'clinic-queue-cron-jobs-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/js/cron-jobs.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('clinic-queue-cron-jobs-script', 'clinicQueueCron', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_cron')
        ));
    }
    
    /**
     * Get cron jobs data
     */
    private function get_cron_jobs_data() {
        return array(
            'auto_sync_next' => wp_next_scheduled('clinic_queue_auto_sync'),
            'cleanup_next' => wp_next_scheduled('clinic_queue_cleanup'),
            'extend_next' => wp_next_scheduled('clinic_queue_extend_calendars'),
            'logs' => $this->get_cron_logs()
        );
    }
    
    /**
     * Get cron logs
     */
    private function get_cron_logs() {
        $logs = get_option('clinic_queue_cron_logs', []);
        return array_slice(array_reverse($logs), 0, 50);
    }
}
