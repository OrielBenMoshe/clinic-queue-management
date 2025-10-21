<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Core Class
 * Central logic and initialization
 */
class Clinic_Queue_Plugin_Core {
    
    private static $instance = null;
    private $min_wp_version = '6.2';
    private $min_elementor_version = '3.12.0';
    
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
     * Initialize the plugin
     */
    public function init() {
        // Always register the widget hook regardless of requirements
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        
        // Also try the init hook for Elementor widgets (fallback)
        add_action('elementor/init', array($this, 'on_elementor_init'));
        
        // Validate other requirements before proceeding with core functionality
        if (!$this->check_core_requirements()) {
            add_action('admin_notices', array($this, 'admin_notice_requirements'));
            return;
        }

        // Load required files
        $this->load_dependencies();
        
        // Initialize database
        $this->init_database();
        
        // Initialize cron jobs
        $this->init_cron_jobs();
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Check minimum WP requirements for core functionality
     */
    private function check_core_requirements() {
        // Check WordPress version
        if (function_exists('get_bloginfo')) {
            $wp_version = get_bloginfo('version');
            if ($wp_version && version_compare($wp_version, $this->min_wp_version, '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check minimum WP and Elementor requirements for widget functionality
     */
    private function check_elementor_requirements() {
        // Check WordPress version
        if (function_exists('get_bloginfo')) {
            $wp_version = get_bloginfo('version');
            if ($wp_version && version_compare($wp_version, $this->min_wp_version, '<')) {
                return false;
            }
        }

        // Check Elementor is loaded and meets minimum version
        if (!did_action('elementor/loaded')) {
            return false;
        }

        if (defined('ELEMENTOR_VERSION')) {
            if (version_compare(ELEMENTOR_VERSION, $this->min_elementor_version, '<')) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Admin notice when requirements are not met
     */
    public function admin_notice_requirements() {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('תוסף Clinic Queue Management דורש WordPress גרסה ' . $this->min_wp_version . ' ומעלה ו-Elementor גרסה ' . $this->min_elementor_version . ' ומעלה. אנא עדכן והפעל את Elementor.', 'clinic-queue-management')
            . '</p></div>';
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-database-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-appointment-manager.php';
        
        // API classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-api-manager.php';
        
        // Frontend classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-widget-fields-manager.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/class-shortcode-handler.php';
        
        // DON'T load widget class here - it will be loaded on-demand in register_widgets()
        
        // Admin classes
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-dashboard.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-calendars.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-sync-status.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-cron-jobs.php';
        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/class-cron-manager.php';
    }
    
    /**
     * Initialize database
     */
    private function init_database() {
        $db_manager = Clinic_Queue_Database_Manager::get_instance();
        
        // Check if tables exist, create if not
        if (!$db_manager->tables_exist()) {
            $db_manager->create_tables();
        }
        
        // Initialize default calendars from mock data
        $this->initialize_default_calendars();
    }
    
    /**
     * Initialize default calendars from mock data
     */
    private function initialize_default_calendars() {
        $json_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        
        foreach ($data['calendars'] as $calendar) {
            // Initialize calendar for this doctor-clinic-treatment combination
            $api_manager->sync_from_api(
                $calendar['doctor_id'], 
                isset($calendar['clinic_id']) ? $calendar['clinic_id'] : '', 
                $calendar['treatment_type']
            );
        }
    }
    
    /**
     * Initialize cron jobs
     */
    private function init_cron_jobs() {
        $cron_manager = Clinic_Queue_Cron_Manager::get_instance();
        $cron_manager->init_cron_jobs();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
        
        add_action('wp_ajax_clinic_queue_get_appointments', array($fields_manager, 'handle_ajax_request'));
        add_action('wp_ajax_nopriv_clinic_queue_get_appointments', array($fields_manager, 'handle_ajax_request'));
        
        add_action('wp_ajax_clinic_queue_get_clinics', array($fields_manager, 'handle_get_clinics_request'));
        add_action('wp_ajax_nopriv_clinic_queue_get_clinics', array($fields_manager, 'handle_get_clinics_request'));
        
        add_action('wp_ajax_clinic_queue_get_doctors', array($fields_manager, 'handle_get_doctors_request'));
        add_action('wp_ajax_nopriv_clinic_queue_get_doctors', array($fields_manager, 'handle_get_doctors_request'));
        
        add_action('wp_ajax_clinic_queue_book_appointment', array($fields_manager, 'handle_booking_request'));
        add_action('wp_ajax_nopriv_clinic_queue_book_appointment', array($fields_manager, 'handle_booking_request'));
        
        // Dashboard AJAX handlers
        add_action('wp_ajax_clinic_queue_sync_all', array($this, 'ajax_sync_all_calendars'));
        add_action('wp_ajax_clinic_queue_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_clinic_queue_generate_appointments', array($this, 'ajax_generate_appointments'));
        
        // Cron Jobs AJAX handlers
        add_action('wp_ajax_clinic_queue_run_cron_task', array($this, 'ajax_run_cron_task'));
        add_action('wp_ajax_clinic_queue_reset_cron', array($this, 'ajax_reset_cron'));
        
        // Additional AJAX handlers
        add_action('wp_ajax_clinic_queue_sync_calendar', array($this, 'ajax_sync_calendar'));
        add_action('wp_ajax_clinic_queue_delete_calendar', array($this, 'ajax_delete_calendar'));
        add_action('wp_ajax_clinic_queue_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_clinic_queue_get_calendar_details', array($this, 'ajax_get_calendar_details'));
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
    
    /**
     * Called when Elementor initializes
     */
    public function on_elementor_init() {
        // Try to register widgets directly with Elementor
        if (class_exists('Elementor\Plugin')) {
            $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
            if ($widgets_manager) {
                $this->register_widgets($widgets_manager);
            }
        }
    }
    

    /**
     * Register Elementor widgets
     */
    public function register_widgets($widgets_manager) {
        
        // Check Elementor requirements before registering widget
        if (!$this->check_elementor_requirements()) {
            return;
        }

        // Minimal guard: ensure Elementor base exists
        if (!class_exists('Elementor\Widget_Base')) {
            return;
        }

        // Ensure dependencies are loaded
        $this->load_widget_dependencies();

        // Load widget class if not already loaded
        if (!class_exists('Clinic_Queue_Widget')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-clinic-queue-widget.php';
        }
        
        if (!class_exists('Clinic_Queue_Widget')) {
            return;
        }
        
        $widgets_manager->register(new Clinic_Queue_Widget());
    }
    
    /**
     * Load widget dependencies only
     */
    private function load_widget_dependencies() {
        // Load only the dependencies needed for the widget
        if (!class_exists('Clinic_Queue_Constants')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        }
        if (!class_exists('Clinic_Queue_Widget_Fields_Manager')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/widgets/class-widget-fields-manager.php';
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('clinic-queue/v1', '/appointments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_appointments'),
            'permission_callback' => '__return_true',
            'args' => array(
                'doctor_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'clinic_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'treatment_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        register_rest_route('clinic-queue/v1', '/all-appointments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_appointments'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Get appointments via REST API
     */
    public function get_appointments($request) {
        $doctor_id = $request->get_param('doctor_id');
        $clinic_id = $request->get_param('clinic_id');
        $treatment_type = $request->get_param('treatment_type') ?: 'רפואה כללית';
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $appointments_data = $api_manager->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
        
        if (!$appointments_data) {
            return new WP_Error('no_appointments', 'No appointments found', array('status' => 404));
        }
        
        return rest_ensure_response($appointments_data);
    }
    
    /**
     * Get all appointments via REST API (for client-side filtering)
     */
    public function get_all_appointments($request) {
        // Load all data from mock-data.json
        $json_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return new WP_Error('no_data_file', 'Mock data file not found', array('status' => 404));
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return new WP_Error('invalid_data', 'Invalid data format', array('status' => 500));
        }
        
        return rest_ensure_response($data);
    }
    
    /**
     * AJAX: Sync all calendars
     */
    public function ajax_sync_all_calendars() {
        check_ajax_referer('clinic_queue_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $result = $api_manager->schedule_auto_sync();
        
        if ($result) {
            wp_send_json_success('All calendars synced successfully');
        } else {
            wp_send_json_error('Failed to sync calendars');
        }
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('clinic_queue_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $api_manager->clear_cache();
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    /**
     * AJAX: Generate new appointments
     */
    public function ajax_generate_appointments() {
        check_ajax_referer('clinic_queue_generate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $appointment_manager = Clinic_Queue_Appointment_Manager::get_instance();
        
        // Get all calendars
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $calendars = $wpdb->get_results("SELECT id FROM $table_calendars");
        
        $generated = 0;
        foreach ($calendars as $calendar) {
            $appointment_manager->generate_future_appointments($calendar->id, 3);
            $generated++;
        }
        
        wp_send_json_success("Generated appointments for $generated calendars");
    }
    
    /**
     * AJAX: Run specific cron task
     */
    public function ajax_run_cron_task() {
        check_ajax_referer('clinic_queue_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $task = sanitize_text_field($_POST['task']);
        $cron_manager = Clinic_Queue_Cron_Manager::get_instance();
        
        switch ($task) {
            case 'auto_sync':
                $cron_manager->run_auto_sync_task();
                wp_send_json_success('Auto sync completed');
                break;
                
            case 'cleanup':
                $cron_manager->run_cleanup_task();
                wp_send_json_success('Cleanup completed');
                break;
                
            case 'extend_calendars':
                $cron_manager->run_extend_calendars_task();
                wp_send_json_success('Extend calendars completed');
                break;
                
            default:
                wp_send_json_error('Unknown task');
        }
    }
    
    /**
     * AJAX: Reset all cron jobs
     */
    public function ajax_reset_cron() {
        check_ajax_referer('clinic_queue_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $cron_manager = Clinic_Queue_Cron_Manager::get_instance();
        $cron_manager->reset_all_cron_jobs();
        
        wp_send_json_success('All cron jobs reset successfully');
    }
    
    /**
     * AJAX: Sync specific calendar
     */
    public function ajax_sync_calendar() {
        check_ajax_referer('clinic_queue_sync_calendar', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $doctor_id = sanitize_text_field($_POST['doctor_id']);
        $clinic_id = sanitize_text_field($_POST['clinic_id']);
        $treatment_type = sanitize_text_field($_POST['treatment_type']);
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $result = $api_manager->sync_from_api($doctor_id, $clinic_id, $treatment_type);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Delete calendar
     */
    public function ajax_delete_calendar() {
        check_ajax_referer('clinic_queue_delete_calendar', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $calendar_id = intval($_POST['calendar_id']);
        
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        $deleted = $wpdb->delete(
            $table_calendars,
            array('id' => $calendar_id),
            array('%d')
        );
        
        if ($deleted) {
            wp_send_json_success('Calendar deleted successfully');
        } else {
            wp_send_json_error('Failed to delete calendar');
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('clinic_queue_test_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_manager = Clinic_Queue_API_Manager::get_instance();
        $result = $api_manager->test_api_connection('mock');
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get calendar details for dialog
     */
    public function ajax_get_calendar_details() {
        check_ajax_referer('clinic_queue_view_calendar', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $calendar_id = intval($_POST['calendar_id']);
        
        if (!$calendar_id) {
            wp_send_json_error('Invalid calendar ID');
            return;
        }
        
        // Get calendar details
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $calendar = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars WHERE id = %d",
            $calendar_id
        ));
        
        if (!$calendar) {
            wp_send_json_error('Calendar not found');
            return;
        }
        
        // Get appointments data
        $appointments_data = $this->get_calendar_appointments_for_dialog($calendar_id);
        
        // Debug: Print data to console
        error_log('Calendar ID: ' . $calendar_id);
        error_log('Calendar data: ' . print_r($calendar, true));
        error_log('Appointments data: ' . print_r($appointments_data, true));
        
        // Generate HTML content
        $html = $this->generate_calendar_dialog_html($calendar, $appointments_data);
        
        wp_send_json_success($html);
    }
    
    /**
     * Get calendar appointments for dialog
     */
    private function get_calendar_appointments_for_dialog($calendar_id) {
        global $wpdb;
        
        // First, get calendar info
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        $calendar = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars WHERE id = %d",
            $calendar_id
        ));
        
        if (!$calendar) {
            error_log('Calendar not found for ID: ' . $calendar_id);
            return array();
        }
        
        // Try to get data from mock data first
        $mock_data = $this->get_mock_data_for_calendar($calendar->doctor_id, $calendar->clinic_id, $calendar->treatment_type);
        
        if ($mock_data && isset($mock_data['days'])) {
            error_log('Using mock data for calendar ' . $calendar_id);
            return $this->convert_mock_data_to_appointments_format($mock_data);
        }
        
        // Fallback to database
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
        
        error_log('Found ' . count($dates) . ' dates for calendar ' . $calendar_id);
        
        // If no dates found, try to sync data first
        if (empty($dates)) {
            error_log('No dates found for calendar ' . $calendar_id . ', attempting to sync...');
            
            // Try to sync data
            $api_manager = Clinic_Queue_API_Manager::get_instance();
            $sync_result = $api_manager->sync_from_api(
                $calendar->doctor_id, 
                $calendar->clinic_id, 
                $calendar->treatment_type
            );
            
            if ($sync_result['success']) {
                error_log('Sync successful, retrying to get dates...');
                // Try again to get dates
                $dates = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_dates 
                     WHERE calendar_id = %d 
                     AND appointment_date >= %s 
                     AND appointment_date <= %s 
                     ORDER BY appointment_date ASC",
                    $calendar_id, $start_date, $end_date
                ));
                error_log('After sync: Found ' . count($dates) . ' dates for calendar ' . $calendar_id);
            }
        }
        
        foreach ($dates as $date) {
            $time_slots = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_times 
                 WHERE date_id = %d 
                 ORDER BY time_slot ASC",
                $date->id
            ));
            
            error_log('Date: ' . $date->appointment_date . ' has ' . count($time_slots) . ' time slots');
            error_log('Time slots for date ' . $date->appointment_date . ': ' . print_r($time_slots, true));
            
            $appointments_data[] = array(
                'date' => $date,
                'time_slots' => $time_slots
            );
        }
        
        error_log('Final appointments data structure: ' . print_r($appointments_data, true));
        
        return $appointments_data;
    }
    
    /**
     * Get mock data for specific calendar
     */
    private function get_mock_data_for_calendar($doctor_id, $clinic_id, $treatment_type) {
        $json_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return null;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return null;
        }
        
        // Find the specific calendar
        foreach ($data['calendars'] as $calendar) {
            if ($calendar['doctor_id'] == $doctor_id && 
                $calendar['clinic_id'] == $clinic_id && 
                $calendar['treatment_type'] == $treatment_type) {
                return $calendar;
            }
        }
        
        return null;
    }
    
    /**
     * Convert mock data to appointments format
     */
    private function convert_mock_data_to_appointments_format($mock_data) {
        $appointments_data = array();
        
        if (!isset($mock_data['appointments'])) {
            return $appointments_data;
        }
        
        foreach ($mock_data['appointments'] as $date => $slots) {
            // Create a mock date object
            $date_obj = new stdClass();
            $date_obj->id = 0; // Mock ID
            $date_obj->appointment_date = $date;
            $date_obj->calendar_id = 0; // Mock calendar ID
            
            $time_slots = array();
            foreach ($slots as $slot) {
                $time_slot = new stdClass();
                $time_slot->id = 0; // Mock ID
                $time_slot->time_slot = $slot['time'];
                $time_slot->is_booked = $slot['is_booked'];
                $time_slot->date_id = 0; // Mock date ID
                $time_slots[] = $time_slot;
            }
            
            $appointments_data[] = array(
                'date' => $date_obj,
                'time_slots' => $time_slots
            );
        }
        
        return $appointments_data;
    }
    
    /**
     * Generate calendar dialog HTML
     */
    private function generate_calendar_dialog_html($calendar, $appointments_data) {
        ob_start();
        ?>
        <div class="calendar-details">
        <div class="calendar-info">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>מזהה רופא</th>
                        <th>שם רופא</th>
                        <th>מזהה מרפאה</th>
                        <th>שם מרפאה</th>
                        <th>סוג טיפול</th>
                        <th>עודכן לאחרונה</th>
                        <th>נוצר ב</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html($calendar->doctor_id); ?></strong></td>
                        <td><?php echo esc_html($this->get_doctor_name($calendar->doctor_id)); ?></td>
                        <td><?php echo esc_html($calendar->clinic_id); ?></td>
                        <td><?php echo esc_html($this->get_clinic_name($calendar->clinic_id)); ?></td>
                        <td><?php echo esc_html($calendar->treatment_type); ?></td>
                        <td><?php echo esc_html($calendar->last_updated); ?></td>
                        <td><?php echo esc_html($calendar->created_at); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
            
            <div class="appointments-calendar" style="max-width: 478px; margin: 0 auto; min-height: 459px; display: flex; flex-direction: column;">
                <?php if (empty($appointments_data)): ?>
                    <div class="notice notice-info">
                        <p>אין תורים זמינים לתקופה הקרובה.</p>
                        <p><strong>טיפ:</strong> לחץ על כפתור "סנכרן" כדי לטעון נתונים חדשים מה-API.</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Get current month and year from first appointment
                    $first_date = strtotime($appointments_data[0]['date']->appointment_date);
                    $current_month = date('F', $first_date);
                    $current_year = date('Y', $first_date);
                    
                    // Hebrew month names and day abbreviations from constants
                    $hebrew_months = class_exists('Clinic_Queue_Constants') ? Clinic_Queue_Constants::get_hebrew_months() : array();
                    $hebrew_day_abbrev = class_exists('Clinic_Queue_Constants') ? Clinic_Queue_Constants::get_hebrew_day_abbrev() : array();
                    ?>
                    
                    <!-- Top Section -->
                    <div class="top-section">
                        <!-- Month and Year Header -->
                        <h2 class="month-and-year"><?php echo $hebrew_months[$current_month] . ', ' . $current_year; ?></h2>
                        
                        <!-- Days Carousel/Tabs -->
                        <div class="days-carousel">
                            <div class="days-container">
                                <?php foreach ($appointments_data as $appointment): ?>
                                    <?php
                                    $date = strtotime($appointment['date']->appointment_date);
                                    $day_number = date('j', $date);
                                    $day_name = date('l', $date);
                                    $total_slots = count($appointment['time_slots']);
                                    ?>
                                    <div class="day-tab <?php echo $index === 0 ? 'active selected' : ''; ?>" data-date="<?php echo date('Y-m-d', $date); ?>">
                                        <div class="day-abbrev"><?php echo $hebrew_day_abbrev[$day_name]; ?></div>
                                        <div class="day-content">
                                            <div class="day-number"><?php echo $day_number; ?></div>
                                            <div class="day-slots-count"><?php echo $total_slots; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bottom Section -->
                    <div class="bottom-section">
                        <!-- Time Slots for Selected Day -->
                        <div class="time-slots-container">
                            <?php foreach ($appointments_data as $index => $appointment): ?>
                                <div class="day-time-slots <?php echo $index === 0 ? 'active' : ''; ?>" data-date="<?php echo date('Y-m-d', strtotime($appointment['date']->appointment_date)); ?>">
                                    <?php if (empty($appointment['time_slots'])): ?>
                                        <p class="no-slots">אין תורים זמינים</p>
                                    <?php else: ?>
                                        <div class="time-slots-grid">
                                            <?php foreach ($appointment['time_slots'] as $slot): ?>
                                                <div class="time-slot <?php echo $slot->is_booked ? 'booked' : 'available'; ?>">
                                                    <span class="slot-time"><?php echo date('H:i', strtotime($slot->time_slot)); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="appointments-overview">
                <h3>סטטיסטיקות תורים</h3>
                <?php
                $total_dates = count($appointments_data);
                $total_slots = 0;
                $booked_slots = 0;
                $free_slots = 0;
                
                foreach ($appointments_data as $appointment) {
                    foreach ($appointment['time_slots'] as $slot) {
                        $total_slots++;
                        if ($slot->is_booked) {
                            $booked_slots++;
                        } else {
                            $free_slots++;
                        }
                    }
                }
                ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_dates; ?></span>
                        <span class="stat-label">תאריכים זמינים</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_slots; ?></span>
                        <span class="stat-label">סה"כ תורים</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number booked"><?php echo $booked_slots; ?></span>
                        <span class="stat-label">תורים תפוסים</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number free"><?php echo $free_slots; ?></span>
                        <span class="stat-label">תורים פנויים</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
