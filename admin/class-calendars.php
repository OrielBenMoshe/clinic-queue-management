<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendars Admin Page
 * Manage appointment calendars
 */
class Clinic_Queue_Calendars_Admin {
    
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
     * Render calendars page
     */
    public function render_page() {
        // Enqueue assets
        $this->enqueue_assets();
        
        // Check for action parameter
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $calendar_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($action === 'view' && $calendar_id > 0) {
            $this->render_calendar_view($calendar_id);
        } else {
            // Get data for main calendars list
            $data = $this->get_calendars_data();
            
            // Include HTML template
            include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/calendars-html.php';
        }
    }
    
    /**
     * Enqueue calendars assets
     */
    private function enqueue_assets() {
        // Enqueue main CSS file with all styles
        wp_enqueue_style(
            'clinic-queue-main',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_script(
            'clinic-queue-calendars-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/assets/js/calendars.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Enqueue Font Awesome (WordPress default)
        wp_enqueue_style('dashicons');
        
        // Localize script for AJAX
        wp_localize_script('clinic-queue-calendars-script', 'clinicQueueCalendars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'sync_nonce' => wp_create_nonce('clinic_queue_sync_calendar'),
            'delete_nonce' => wp_create_nonce('clinic_queue_delete_calendar'),
            'view_nonce' => wp_create_nonce('clinic_queue_view_calendar')
        ));
    }
    
    /**
     * Get calendars data
     */
    private function get_calendars_data() {
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        
        return array(
            'status' => $api_manager->get_sync_status()
        );
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
     * Render calendar view page
     */
    private function render_calendar_view($calendar_id) {
        // Get calendar details
        $calendar = $this->get_calendar_by_id($calendar_id);
        
        if (!$calendar) {
            echo '<div class="wrap"><h1>יומן לא נמצא</h1><p>היומן המבוקש לא נמצא במערכת.</p></div>';
            return;
        }
        
        // Get calendar appointments data
        $appointments_data = $this->get_calendar_appointments($calendar_id);
        
        // Prepare data for template
        $data = array(
            'calendar' => $calendar,
            'appointments' => $appointments_data
        );
        
        // Include calendar view template
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/calendar-view-html.php';
    }
    
    /**
     * Get calendar by ID
     */
    private function get_calendar_by_id($calendar_id) {
        global $wpdb;
        
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars WHERE id = %d",
            $calendar_id
        ));
    }
    
    /**
     * Get calendar appointments data
     */
    private function get_calendar_appointments($calendar_id) {
        global $wpdb;
        
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        
        // Get dates for the next 4 weeks
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+4 weeks'));
        
        $dates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_dates 
             WHERE calendar_id = %d 
             AND appointment_date >= %s 
             AND appointment_date <= %s 
             ORDER BY appointment_date ASC",
            $calendar_id, $start_date, $end_date
        ));
        
        $appointments_data = array();
        
        foreach ($dates as $date) {
            $time_slots = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_times 
                 WHERE date_id = %d 
                 ORDER BY time_slot ASC",
                $date->id
            ));
            
            $appointments_data[] = array(
                'date' => $date,
                'time_slots' => $time_slots
            );
        }
        
        return $appointments_data;
    }
    
    /**
     * Get doctor name by ID
     */
    private function get_doctor_name($doctor_id) {
        // Mock data for doctor names - replace with actual data source
        $doctors = array(
            '1' => 'ד"ר יוסי כהן',
            '2' => 'ד"ר שרה לוי',
            '3' => 'ד"ר דוד ישראלי',
            '4' => 'ד"ר מיכל גולד',
            '5' => 'ד"ר אורי ברק'
        );
        
        return isset($doctors[$doctor_id]) ? $doctors[$doctor_id] : 'רופא לא ידוע';
    }
    
    /**
     * Get clinic name by ID
     */
    private function get_clinic_name($clinic_id) {
        // Mock data for clinic names - replace with actual data source
        $clinics = array(
            '1' => 'מרפאת "הטרול המחייך"',
            '2' => 'מרפאת "הדובון החמוד"',
            '3' => 'מרפאת "הפילון הקטן"',
            '4' => 'מרפאת "הקיפוד הנחמד"',
            '5' => 'מרפאת "הדולפין השמח"'
        );
        
        return isset($clinics[$clinic_id]) ? $clinics[$clinic_id] : 'מרפאה לא ידועה';
    }
}
