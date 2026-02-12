<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Appointments Handler
 * מטפל בעמוד ניהול התורים - הצגה, יצירה, עדכון ומחיקה
 * 
 * @package ClinicQueue
 * @subpackage Admin\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Appointments_Handler {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_Appointments_Handler
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
    private function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_clinic_queue_get_appointments', array($this, 'ajax_get_appointments'));
        add_action('wp_ajax_clinic_queue_create_test_appointment', array($this, 'ajax_create_test_appointment'));
        add_action('wp_ajax_clinic_queue_delete_appointment', array($this, 'ajax_delete_appointment'));
    }
    
    /**
     * Render appointments page
     */
    public function render_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('אין לך הרשאות מספיקות לגשת לדף זה.', 'clinic-queue'));
        }
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Get initial data
        $appointments = $this->get_appointments_data();
        $stats = $this->get_appointments_stats();
        
        // Include view
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/appointments-html.php';
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    private function enqueue_assets() {
        // CSS - from main assets folder
        wp_enqueue_style(
            'clinic-queue-appointments',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/admin/appointments.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // JavaScript - from admin/js folder
        wp_enqueue_script(
            'clinic-queue-appointments',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/js/appointments.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('clinic-queue-appointments', 'clinicQueueAppointments', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_appointments'),
            'strings' => array(
                'confirmDelete' => __('האם אתה בטוח שברצונך למחוק תור זה?', 'clinic-queue'),
                'deleteSuccess' => __('התור נמחק בהצלחה', 'clinic-queue'),
                'deleteError' => __('שגיאה במחיקת התור', 'clinic-queue'),
                'createSuccess' => __('רשומת בדיקה נוצרה בהצלחה', 'clinic-queue'),
                'createError' => __('שגיאה ביצירת רשומת בדיקה', 'clinic-queue'),
                'loadError' => __('שגיאה בטעינת התורים', 'clinic-queue'),
            ),
        ));
    }
    
    /**
     * Get appointments data from database
     * 
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Appointments data
     */
    private function get_appointments_data($limit = 50, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get appointments statistics
     * 
     * @return array Statistics data
     */
    private function get_appointments_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array(
                'total' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
            );
        }
        
        $stats = array();
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
        $stats['confirmed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'confirmed'");
        $stats['completed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'completed'");
        $stats['cancelled'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'cancelled'");
        
        return $stats;
    }
    
    /**
     * AJAX handler: Get appointments
     */
    public function ajax_get_appointments() {
        check_ajax_referer('clinic_queue_appointments', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        $appointments = $this->get_appointments_data($limit, $offset);
        $stats = $this->get_appointments_stats();
        
        wp_send_json_success(array(
            'appointments' => $appointments,
            'stats' => $stats,
        ));
    }
    
    /**
     * AJAX handler: Create test appointment
     */
    public function ajax_create_test_appointment() {
        check_ajax_referer('clinic_queue_appointments', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        
        // Create test data
        $test_data = array(
            'wp_clinic_id' => 1,
            'wp_doctor_id' => 1,
            'wp_schedule_id' => null,
            'patient_first_name' => 'בדיקה',
            'patient_last_name' => 'מערכת',
            'patient_phone' => '050-1234567',
            'patient_email' => 'test@example.com',
            'patient_id_number' => '123456789',
            'appointment_datetime' => gmdate('Y-m-d\TH:i\Z'),
            'duration' => 30,
            'treatment_type' => 'בדיקה כללית',
            'status' => 'pending',
            'remark' => 'רשומת בדיקה שנוצרה אוטומטית',
            'proxy_schedule_id' => null,
            'drWebReasonID' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'updated_by' => get_current_user_id(),
        );
        
        $result = $wpdb->insert($table_name, $test_data);
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => 'Failed to create test appointment',
                'error' => $wpdb->last_error,
            ));
        }
        
        $appointment_id = $wpdb->insert_id;
        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $appointment_id),
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'message' => 'Test appointment created successfully',
            'appointment' => $appointment,
        ));
    }
    
    /**
     * AJAX handler: Delete appointment
     */
    public function ajax_delete_appointment() {
        check_ajax_referer('clinic_queue_appointments', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        if ($appointment_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid appointment ID'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        
        $result = $wpdb->delete($table_name, array('id' => $appointment_id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => 'Failed to delete appointment',
                'error' => $wpdb->last_error,
            ));
        }
        
        wp_send_json_success(array('message' => 'Appointment deleted successfully'));
    }
}
