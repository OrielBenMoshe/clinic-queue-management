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
        
        // Get initial data (filter, sort, first_visit from request)
        $filter = isset($_GET['filter']) && in_array($_GET['filter'], array('all', 'past', 'future'), true) ? $_GET['filter'] : 'all';
        $first_visit = isset($_GET['first_visit']) && in_array($_GET['first_visit'], array('all', 'yes', 'no'), true) ? $_GET['first_visit'] : 'all';
        $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'appointment_datetime';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        $appointments = $this->get_appointments_data(50, 0, $filter, $orderby, $order, $first_visit);
        $stats = $this->get_appointments_stats();
        
        // Include view
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/appointments-html.php';
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    private function enqueue_assets() {
        // CSS - from main assets folder (דשאיקונים לאייקוני מיון)
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'clinic-queue-appointments',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/admin/appointments.css',
            array('dashicons'),
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
     * Allowed columns for ORDER BY (whitelist)
     * patient_name = sort by full name (first + last)
     */
    private function get_allowed_orderby_columns() {
        return array(
            'id', 'wp_clinic_id', 'wp_doctor_id', 'wp_schedule_id', 'created_by',
            'patient_name', 'patient_first_name', 'patient_last_name', 'patient_phone', 'patient_email', 'patient_id_number',
            'appointment_datetime', 'duration', 'first_visit', 'treatment_type', 'created_at',
        );
    }
    
    /**
     * Get appointments data from database
     *
     * @param int    $limit             Number of records to return
     * @param int    $offset            Offset for pagination
     * @param string $filter            'all'|'past'|'future'
     * @param string $orderby           Column name for sort
     * @param string $order             'ASC'|'DESC'
     * @param string $first_visit_filter 'all'|'yes'|'no'
     * @return array Appointments data
     */
    private function get_appointments_data($limit = 50, $offset = 0, $filter = 'all', $orderby = 'appointment_datetime', $order = 'DESC', $first_visit_filter = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        $allowed = $this->get_allowed_orderby_columns();
        $orderby = in_array($orderby, $allowed, true) ? $orderby : 'appointment_datetime';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $order_by_expr = ($orderby === 'patient_name')
            ? "CONCAT(patient_first_name, ' ', patient_last_name) {$order}"
            : "{$orderby} {$order}";
        $now = gmdate('Y-m-d\TH:i');
        
        $where = array('1=1');
        $prepare_args = array();
        
        if ($filter === 'past') {
            $where[] = 'appointment_datetime < %s';
            $prepare_args[] = $now;
        } elseif ($filter === 'future') {
            $where[] = 'appointment_datetime >= %s';
            $prepare_args[] = $now;
        }
        
        if ($first_visit_filter === 'yes') {
            $where[] = 'first_visit = 1';
        } elseif ($first_visit_filter === 'no') {
            $where[] = 'first_visit = 0';
        }
        
        $where_sql = implode(' AND ', $where);
        $prepare_args[] = $limit;
        $prepare_args[] = $offset;
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY {$order_by_expr} LIMIT %d OFFSET %d",
            $prepare_args
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get appointments statistics (total, past, future)
     *
     * @return array Statistics data: total, past, future
     */
    private function get_appointments_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array('total' => 0, 'past' => 0, 'future' => 0);
        }
        
        $now = gmdate('Y-m-d\TH:i');
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $past = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE appointment_datetime < %s",
            $now
        ));
        $future = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE appointment_datetime >= %s",
            $now
        ));
        
        return array('total' => $total, 'past' => $past, 'future' => $future);
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
        $filter = isset($_POST['filter']) && in_array($_POST['filter'], array('all', 'past', 'future'), true) ? $_POST['filter'] : 'all';
        $first_visit = isset($_POST['first_visit']) && in_array($_POST['first_visit'], array('all', 'yes', 'no'), true) ? $_POST['first_visit'] : 'all';
        $orderby = isset($_POST['orderby']) ? $_POST['orderby'] : 'appointment_datetime';
        $order = isset($_POST['order']) && strtoupper($_POST['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $appointments = $this->get_appointments_data($limit, $offset, $filter, $orderby, $order, $first_visit);
        $stats = $this->get_appointments_stats();
        
        wp_send_json_success(array(
            'appointments' => $appointments,
            'stats' => $stats,
        ));
    }
    
    /**
     * AJAX handler: Create test appointment
     * משתמש ב-Database_Manager::create_appointment כדי להבטיח תאימות מלאה למבנה הטבלה
     */
    public function ajax_create_test_appointment() {
        check_ajax_referer('clinic_queue_appointments', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $db_manager = \Clinic_Queue_Database_Manager::get_instance();
        if (!$db_manager->appointments_table_exists()) {
            wp_send_json_error(array('message' => 'טבלת התורים אינה קיימת.'));
        }
        
        $appointment_datetime = gmdate('Y-m-d\TH:i\Z', strtotime('+24 hours'));
        $test_data = array(
            'wp_clinic_id' => random_int(1000, 9999),
            'wp_doctor_id' => random_int(1000, 9999),
            'wp_schedule_id' => null,
            'patient_first_name' => 'בדיקה',
            'patient_last_name' => 'מערכת',
            'patient_phone' => '050-1234567',
            'patient_email' => 'test@example.com',
            'patient_id_number' => '123456789',
            'appointment_datetime' => $appointment_datetime,
            'duration' => 30,
            'treatment_type' => 'בדיקה כללית',
            'remark' => 'רשומת בדיקה שנוצרה אוטומטית',
            'first_visit' => random_int(0, 1),
            'proxy_schedule_id' => null,
            'proxy_appointment_id' => null,
            'created_by' => get_current_user_id(),
        );
        
        $appointment_id = $db_manager->create_appointment($test_data);
        if ($appointment_id === false) {
            wp_send_json_error(array(
                'message' => __('שגיאה ביצירת רשומת בדיקה.', 'clinic-queue'),
            ));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $appointment_id),
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'message' => __('רשומת בדיקה נוצרה בהצלחה.', 'clinic-queue'),
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
