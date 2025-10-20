<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Manager for Clinic Queue Management
 * Handles external API communication and data synchronization
 */
class Clinic_Queue_API_Manager {
    
    private static $instance = null;
    private $appointment_manager;
    private $db_manager;
    private $cache_duration = 1800; // 30 minutes in seconds
    
    public function __construct() {
        $this->appointment_manager = Clinic_Queue_Appointment_Manager::get_instance();
        $this->db_manager = Clinic_Queue_Database_Manager::get_instance();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Sync data from external API
     */
    public function sync_from_api($doctor_id, $clinic_id, $treatment_type = '', $api_endpoint = null) {
        // For now, we'll use the mock data from JSON file
        // In production, this would connect to real API
        $mock_data = $this->get_mock_data($doctor_id, $clinic_id, $treatment_type);
        
        if (!$mock_data) {
            return ['success' => false, 'message' => 'No data found'];
        }
        
        // Update calendar with the data
        $result = $this->appointment_manager->update_calendar_from_data(
            $doctor_id, 
            $clinic_id, 
            $treatment_type, 
            $mock_data
        );
        
        if ($result) {
            // Update last sync time
            $this->update_last_sync($doctor_id, $clinic_id, $treatment_type);
            return ['success' => true, 'message' => 'Data synchronized successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to sync data'];
        }
    }
    
    /**
     * Get mock data from JSON file (simulating API call)
     */
    private function get_mock_data($doctor_id, $clinic_id, $treatment_type = '') {
        $json_file = plugin_dir_path(__FILE__) . '../data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return null;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return null;
        }
        
        // Find the specific calendar (doctor + clinic + treatment combination)
        $calendar = null;
        foreach ($data['calendars'] as $cal) {
            if ($cal['doctor_id'] == $doctor_id && 
                isset($cal['clinic_id']) && $cal['clinic_id'] == $clinic_id && 
                $cal['treatment_type'] == $treatment_type) {
                $calendar = $cal;
                break;
            }
        }
        
        if (!$calendar) {
            return null;
        }
        
        // Convert appointments data to our format
        $appointments_data = [
            'doctor' => [
                'id' => $calendar['doctor_id'],
                'name' => $calendar['doctor_name'],
            ],
            'clinic' => [
                'id' => isset($calendar['clinic_id']) ? $calendar['clinic_id'] : '',
                'name' => $calendar['clinic_name'],
                'address' => $calendar['clinic_address']
            ],
            'treatment_type' => $calendar['treatment_type'],
            'timezone' => 'Asia/Jerusalem',
            'days' => []
        ];
        
        // Process appointments
        if (isset($calendar['appointments'])) {
            foreach ($calendar['appointments'] as $date => $slots) {
                $day_slots = [];
                foreach ($slots as $slot) {
                    $day_slots[] = [
                        'time' => $slot['time'],
                        'booked' => $slot['is_booked'],
                        'patient_name' => null,
                        'patient_phone' => null
                    ];
                }
                
                $appointments_data['days'][] = [
                    'date' => $date,
                    'slots' => $day_slots
                ];
            }
        }
        
        return $appointments_data;
    }
    
    /**
     * Update last sync time
     */
    private function update_last_sync($doctor_id, $clinic_id, $treatment_type) {
        $calendar = $this->db_manager->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if ($calendar) {
            global $wpdb;
            $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
            
            $wpdb->update(
                $table_calendars,
                ['last_updated' => current_time('mysql')],
                ['id' => $calendar->id],
                ['%s'],
                ['%d']
            );
        }
    }
    
    /**
     * Check if data needs sync
     */
    public function needs_sync($doctor_id, $clinic_id, $treatment_type = '') {
        $calendar = $this->db_manager->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if (!$calendar) {
            return true; // Need to create calendar
        }
        
        $last_sync = strtotime($calendar->last_updated);
        $now = time();
        
        return ($now - $last_sync) > $this->cache_duration;
    }
    
    /**
     * Get appointments data (with caching)
     */
    public function get_appointments_data($doctor_id, $clinic_id, $treatment_type = '') {
        // Check if we need to sync
        if ($this->needs_sync($doctor_id, $clinic_id, $treatment_type)) {
            $this->sync_from_api($doctor_id, $clinic_id, $treatment_type);
        }
        
        // Get data from database
        return $this->db_manager->get_appointments_for_widget($doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Schedule automatic sync (called by WordPress cron)
     */
    public function schedule_auto_sync() {
        // Get all calendars that need sync
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        $calendars = $wpdb->get_results(
            "SELECT doctor_id, clinic_id, treatment_type FROM $table_calendars"
        );
        
        foreach ($calendars as $calendar) {
            if ($this->needs_sync($calendar->doctor_id, $calendar->clinic_id, $calendar->treatment_type)) {
                $this->sync_from_api($calendar->doctor_id, $calendar->clinic_id, $calendar->treatment_type);
            }
        }
    }
    
    /**
     * Manual sync for specific calendar
     */
    public function manual_sync($doctor_id, $clinic_id, $treatment_type = '') {
        return $this->sync_from_api($doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Get sync status for all calendars
     */
    public function get_sync_status() {
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        $calendars = $wpdb->get_results(
            "SELECT *, 
             CASE 
                 WHEN last_updated > DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 'synced'
                 WHEN last_updated > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'stale'
                 ELSE 'outdated'
             END as sync_status
             FROM $table_calendars 
             ORDER BY last_updated DESC"
        );
        
        return $calendars;
    }
    
    /**
     * Clear all cached data
     */
    public function clear_cache() {
        // This would clear any external API cache if we had one
        // For now, we just update the last_sync time to force refresh
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        $wpdb->query(
            "UPDATE $table_calendars SET last_updated = '1970-01-01 00:00:00'"
        );
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        
        // Delete dates older than 3 weeks
        $three_weeks_ago = date('Y-m-d', strtotime('-3 weeks'));
        
        $deleted_dates = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_dates WHERE appointment_date < %s",
                $three_weeks_ago
            )
        );
        
        // Clear cache
        $this->clear_cache();
        
        return $deleted_dates;
    }
    
    /**
     * Test API connection (for future real API implementation)
     */
    public function test_api_connection($api_endpoint) {
        // This would test a real API connection
        // For now, we'll just return success for mock data
        return [
            'success' => true,
            'message' => 'Mock API connection successful',
            'response_time' => 0.1
        ];
    }
    
    /**
     * Get API statistics
     */
    public function get_api_stats() {
        $status = $this->get_sync_status();
        
        $stats = [
            'total_calendars' => count($status),
            'synced' => count(array_filter($status, function($cal) { return $cal->sync_status === 'synced'; })),
            'stale' => count(array_filter($status, function($cal) { return $cal->sync_status === 'stale'; })),
            'outdated' => count(array_filter($status, function($cal) { return $cal->sync_status === 'outdated'; })),
            'last_sync' => !empty($status) ? max(array_column($status, 'last_updated')) : null
        ];
        
        return $stats;
    }
}
