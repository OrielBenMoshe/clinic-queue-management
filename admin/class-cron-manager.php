<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Manager for Clinic Queue Management
 * Handles automated tasks, data synchronization, and scheduled updates
 */
class Clinic_Queue_Cron_Manager {
    
    private static $instance = null;
    private $api_manager;
    private $appointment_manager;
    private $db_manager;
    
    // Cron job hooks
    const SYNC_HOOK = 'clinic_queue_auto_sync';
    const CLEANUP_HOOK = 'clinic_queue_cleanup';
    const EXTEND_CALENDARS_HOOK = 'clinic_queue_extend_calendars';
    
    // Cron intervals
    const SYNC_INTERVAL = 'clinic_queue_30min';
    const CLEANUP_INTERVAL = 'clinic_queue_daily';
    const EXTEND_INTERVAL = 'clinic_queue_weekly';
    
    public function __construct() {
        $this->api_manager = Clinic_Queue_API_Manager::get_instance();
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
     * Initialize cron jobs
     */
    public function init_cron_jobs() {
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Hook the cron functions
        add_action(self::SYNC_HOOK, array($this, 'run_auto_sync'));
        add_action(self::CLEANUP_HOOK, array($this, 'run_cleanup'));
        add_action(self::EXTEND_CALENDARS_HOOK, array($this, 'run_extend_calendars'));
        
        // Admin interface is handled by the main plugin core
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules[self::SYNC_INTERVAL] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'clinic-queue-management')
        );
        
        $schedules[self::CLEANUP_INTERVAL] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __('Daily', 'clinic-queue-management')
        );
        
        $schedules[self::EXTEND_INTERVAL] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly', 'clinic-queue-management')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule all cron jobs
     */
    public function schedule_cron_jobs() {
        // Auto sync every 30 minutes
        if (!wp_next_scheduled(self::SYNC_HOOK)) {
            wp_schedule_event(time(), self::SYNC_INTERVAL, self::SYNC_HOOK);
        }
        
        // Cleanup old data daily
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), self::CLEANUP_INTERVAL, self::CLEANUP_HOOK);
        }
        
        // Extend calendars weekly
        if (!wp_next_scheduled(self::EXTEND_CALENDARS_HOOK)) {
            wp_schedule_event(time(), self::EXTEND_INTERVAL, self::EXTEND_CALENDARS_HOOK);
        }
    }
    
    /**
     * Run auto sync for all calendars
     */
    public function run_auto_sync() {
        $this->log_cron_start('Auto Sync');
        
        try {
            // Get all calendars that need sync
            $calendars = $this->get_all_calendars();
            $synced_count = 0;
            $error_count = 0;
            
            foreach ($calendars as $calendar) {
                $result = $this->api_manager->sync_from_api(
                    $calendar->doctor_id,
                    $calendar->clinic_id,
                    $calendar->treatment_type
                );
                
                if ($result['success']) {
                    $synced_count++;
                } else {
                    $error_count++;
                    $this->log_error("Sync failed for calendar {$calendar->id}: " . $result['message']);
                }
            }
            
            $this->log_cron_success('Auto Sync', "Synced: {$synced_count}, Errors: {$error_count}");
            
        } catch (Exception $e) {
            $this->log_cron_error('Auto Sync', $e->getMessage());
        }
    }
    
    /**
     * Run cleanup of old data
     */
    public function run_cleanup() {
        $this->log_cron_start('Cleanup');
        
        try {
            // Clean up old appointments (older than 3 weeks)
            $deleted_count = $this->appointment_manager->cleanup_old_appointments();
            
            // Clear expired cache
            $this->api_manager->clear_cache();
            
            $this->log_cron_success('Cleanup', "Deleted {$deleted_count} old appointments");
            
        } catch (Exception $e) {
            $this->log_cron_error('Cleanup', $e->getMessage());
        }
    }
    
    /**
     * Run extend calendars (add more weeks)
     */
    public function run_extend_calendars() {
        $this->log_cron_start('Extend Calendars');
        
        try {
            $calendars = $this->get_all_calendars();
            $extended_count = 0;
            
            foreach ($calendars as $calendar) {
                $result = $this->appointment_manager->extend_calendar($calendar->id, 1); // Add 1 week
                
                if ($result) {
                    $extended_count++;
                }
            }
            
            $this->log_cron_success('Extend Calendars', "Extended {$extended_count} calendars");
            
        } catch (Exception $e) {
            $this->log_cron_error('Extend Calendars', $e->getMessage());
        }
    }
    
    /**
     * Get all calendars from database
     */
    private function get_all_calendars() {
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_calendars ORDER BY last_updated ASC"
        );
    }
    
    /**
     * Manual sync for specific calendar
     */
    public function manual_sync_calendar($doctor_id, $clinic_id, $treatment_type) {
        $this->log_cron_start("Manual Sync: {$doctor_id}-{$clinic_id}-{$treatment_type}");
        
        try {
            $result = $this->api_manager->sync_from_api($doctor_id, $clinic_id, $treatment_type);
            
            if ($result['success']) {
                $this->log_cron_success("Manual Sync: {$doctor_id}-{$clinic_id}-{$treatment_type}", $result['message']);
                return $result;
            } else {
                $this->log_cron_error("Manual Sync: {$doctor_id}-{$clinic_id}-{$treatment_type}", $result['message']);
                return $result;
            }
            
        } catch (Exception $e) {
            $this->log_cron_error("Manual Sync: {$doctor_id}-{$clinic_id}-{$treatment_type}", $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Sync all calendars manually
     */
    public function manual_sync_all() {
        $this->log_cron_start('Manual Sync All');
        
        try {
            $calendars = $this->get_all_calendars();
            $results = [];
            
            foreach ($calendars as $calendar) {
                $result = $this->manual_sync_calendar(
                    $calendar->doctor_id,
                    $calendar->clinic_id,
                    $calendar->treatment_type
                );
                $results[] = $result;
            }
            
            $success_count = count(array_filter($results, function($r) { return $r['success']; }));
            $this->log_cron_success('Manual Sync All', "Synced {$success_count}/" . count($results) . " calendars");
            
            return [
                'success' => true,
                'message' => "Synced {$success_count}/" . count($results) . " calendars",
                'results' => $results
            ];
            
        } catch (Exception $e) {
            $this->log_cron_error('Manual Sync All', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get cron status
     */
    public function get_cron_status() {
        $status = [
            'auto_sync' => [
                'scheduled' => wp_next_scheduled(self::SYNC_HOOK),
                'interval' => self::SYNC_INTERVAL,
                'next_run' => wp_next_scheduled(self::SYNC_HOOK) ? date('Y-m-d H:i:s', wp_next_scheduled(self::SYNC_HOOK)) : 'Not scheduled'
            ],
            'cleanup' => [
                'scheduled' => wp_next_scheduled(self::CLEANUP_HOOK),
                'interval' => self::CLEANUP_INTERVAL,
                'next_run' => wp_next_scheduled(self::CLEANUP_HOOK) ? date('Y-m-d H:i:s', wp_next_scheduled(self::CLEANUP_HOOK)) : 'Not scheduled'
            ],
            'extend_calendars' => [
                'scheduled' => wp_next_scheduled(self::EXTEND_CALENDARS_HOOK),
                'interval' => self::EXTEND_INTERVAL,
                'next_run' => wp_next_scheduled(self::EXTEND_CALENDARS_HOOK) ? date('Y-m-d H:i:s', wp_next_scheduled(self::EXTEND_CALENDARS_HOOK)) : 'Not scheduled'
            ]
        ];
        
        return $status;
    }
    
    /**
     * Clear all scheduled cron jobs
     */
    public function clear_all_cron_jobs() {
        wp_clear_scheduled_hook(self::SYNC_HOOK);
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
        wp_clear_scheduled_hook(self::EXTEND_CALENDARS_HOOK);
        
        $this->log_cron_success('Clear All Cron Jobs', 'All cron jobs cleared');
    }
    
    /**
     * Reschedule all cron jobs
     */
    public function reschedule_cron_jobs() {
        $this->clear_all_cron_jobs();
        $this->schedule_cron_jobs();
        
        $this->log_cron_success('Reschedule Cron Jobs', 'All cron jobs rescheduled');
    }
    
    /**
     * Add cron admin menu - REMOVED: This is handled by the main plugin core
     */
    
    /**
     * Cron admin page - REMOVED: This is handled by the main plugin core
     */
    
    /**
     * Handle cron admin actions - REMOVED: This is handled by the main plugin core
     */
    
    /**
     * Log cron start
     */
    private function log_cron_start($job_name) {
        $this->log_cron_event($job_name, 'started', 'info');
    }
    
    /**
     * Log cron success
     */
    private function log_cron_success($job_name, $message) {
        $this->log_cron_event($job_name, "SUCCESS: {$message}", 'success');
    }
    
    /**
     * Log cron error
     */
    private function log_cron_error($job_name, $message) {
        $this->log_cron_event($job_name, "ERROR: {$message}", 'error');
    }
    
    /**
     * Log error
     */
    private function log_error($message) {
        error_log("[Clinic Queue] {$message}");
    }
    
    /**
     * Log cron event
     */
    private function log_cron_event($job_name, $message, $type = 'info') {
        $log_entry = [
            'timestamp' => current_time('Y-m-d H:i:s'),
            'job' => $job_name,
            'message' => $message,
            'type' => $type
        ];
        
        // Store in WordPress options (you could also use a custom table)
        $logs = get_option('clinic_queue_cron_logs', []);
        $logs[] = $log_entry;
        
        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('clinic_queue_cron_logs', $logs);
    }
    
    /**
     * Get recent logs
     */
    private function get_recent_logs($limit = 50) {
        $logs = get_option('clinic_queue_cron_logs', []);
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    /**
     * Cleanup on plugin deactivation
     */
    public function cleanup_on_deactivation() {
        $this->clear_all_cron_jobs();
    }
    
    /**
     * Run auto sync task manually
     */
    public function run_auto_sync_task() {
        $this->log_cron_task('auto_sync', 'Starting manual auto sync');
        
        try {
            $api_manager = Clinic_Queue_API_Manager::get_instance();
            $api_manager->schedule_auto_sync();
            $this->log_cron_task('auto_sync', 'Manual auto sync completed successfully', 'success');
        } catch (Exception $e) {
            $this->log_cron_task('auto_sync', 'Manual auto sync failed: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Run cleanup task manually
     */
    public function run_cleanup_task() {
        $this->log_cron_task('cleanup', 'Starting manual cleanup');
        
        try {
            $api_manager = Clinic_Queue_API_Manager::get_instance();
            $api_manager->cleanup_old_data();
            $this->log_cron_task('cleanup', 'Manual cleanup completed successfully', 'success');
        } catch (Exception $e) {
            $this->log_cron_task('cleanup', 'Manual cleanup failed: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Run extend calendars task manually
     */
    public function run_extend_calendars_task() {
        $this->log_cron_task('extend_calendars', 'Starting manual extend calendars');
        
        try {
            $appointment_manager = Clinic_Queue_Appointment_Manager::get_instance();
            
            // Get all calendars
            global $wpdb;
            $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
            $calendars = $wpdb->get_results("SELECT id FROM $table_calendars");
            
            $extended = 0;
            foreach ($calendars as $calendar) {
                $appointment_manager->generate_future_appointments($calendar->id, 1); // Add 1 week
                $extended++;
            }
            
            $this->log_cron_task('extend_calendars', "Manual extend calendars completed for $extended calendars", 'success');
        } catch (Exception $e) {
            $this->log_cron_task('extend_calendars', 'Manual extend calendars failed: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Reset all cron jobs
     */
    public function reset_all_cron_jobs() {
        $this->log_cron_task('reset', 'Resetting all cron jobs');
        
        // Clear existing schedules
        wp_clear_scheduled_hook(self::SYNC_HOOK);
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
        wp_clear_scheduled_hook(self::EXTEND_CALENDARS_HOOK);
        
        // Schedule new ones
        $this->schedule_cron_jobs();
        
        $this->log_cron_task('reset', 'All cron jobs reset successfully', 'success');
    }
    
    /**
     * Log cron task execution
     */
    private function log_cron_task($task_name, $message, $status = 'info') {
        $logs = get_option('clinic_queue_cron_logs', []);
        
        $logs[] = [
            'task_name' => $task_name,
            'message' => $message,
            'status' => $status,
            'created_at' => current_time('mysql')
        ];
        
        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('clinic_queue_cron_logs', $logs);
    }
}
