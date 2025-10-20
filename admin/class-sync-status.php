<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sync Status Admin Page
 * Monitor sync status and logs
 */
class Clinic_Queue_Sync_Status_Admin {
    
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
     * Render sync status page
     */
    public function render_page() {
        // Enqueue assets
        $this->enqueue_assets();
        
        // Get data
        $data = $this->get_sync_status_data();
        
        // Include HTML template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/sync-status-html.php';
    }
    
    /**
     * Enqueue sync status assets
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
            'clinic-queue-sync-status-style',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/css/sync-status.css',
            array('clinic-queue-assistant-font'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_script(
            'clinic-queue-sync-status-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/js/sync-status.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('clinic-queue-sync-status-script', 'clinicQueueSyncStatus', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_sync_status')
        ));
    }
    
    /**
     * Get sync status data
     */
    private function get_sync_status_data() {
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        
        return array(
            'sync_status' => $api_manager->get_sync_status(),
            'api_stats' => $api_manager->get_api_stats(),
            'logs' => $this->get_sync_logs()
        );
    }
    
    /**
     * Get status icon
     */
    private function get_status_icon($status) {
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
     * Get status text
     */
    private function get_status_text($status) {
        switch ($status) {
            case 'synced':
                return 'מסונכרן';
            case 'stale':
                return 'ישן';
            case 'outdated':
                return 'לא מעודכן';
            default:
                return 'לא ידוע';
        }
    }
    
    /**
     * Get time ago
     */
    private function get_time_ago($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'לפני ' . $diff . ' שניות';
        } elseif ($diff < 3600) {
            return 'לפני ' . floor($diff / 60) . ' דקות';
        } elseif ($diff < 86400) {
            return 'לפני ' . floor($diff / 3600) . ' שעות';
        } else {
            return 'לפני ' . floor($diff / 86400) . ' ימים';
        }
    }
    
    /**
     * Get response time
     */
    private function get_response_time($datetime) {
        // Mock response time for now
        return rand(50, 500) . 'ms';
    }
    
    /**
     * Get sync logs
     */
    private function get_sync_logs() {
        // Mock logs for now - in real implementation, this would come from database
        return [
            [
                'timestamp' => date('H:i:s'),
                'message' => 'סנכרון יומן 1 הושלם בהצלחה',
                'type' => 'success'
            ],
            [
                'timestamp' => date('H:i:s', time() - 300),
                'message' => 'סנכרון יומן 2 הושלם בהצלחה',
                'type' => 'success'
            ],
            [
                'timestamp' => date('H:i:s', time() - 600),
                'message' => 'שגיאה בסנכרון יומן 3',
                'type' => 'error'
            ]
        ];
    }
}
