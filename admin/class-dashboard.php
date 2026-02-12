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
        // Enqueue widget assets (same as frontend)
        $this->enqueue_widget_assets();
        
        // Enqueue main CSS file with all styles
        wp_enqueue_style(
            'clinic-queue-main',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
            array(),
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
     * Enqueue widget assets (same as widget class)
     * clinic-queue-main is already enqueued in enqueue_assets(); base + select are inside main.
     */
    private function enqueue_widget_assets() {
        wp_enqueue_style(
            'select2-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.css',
            array(),
            '4.1.0'
        );

        wp_enqueue_style('dashicons');

        // Select overrides after Select2 (main.css already includes select; this ensures cascade)
        wp_enqueue_style(
            'select-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/select.css',
            array('clinic-queue-main', 'select2-css', 'dashicons'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // Enqueue Select2 JS
        wp_enqueue_script(
            'select2-js',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );

        // Enqueue module scripts in correct order
        $modules = [
            'clinic-queue-utils',
            'clinic-queue-data-manager',
            'clinic-queue-ui-manager',
            'clinic-queue-widget',
            'clinic-queue-init'
        ];
        $module_handles = [];
        
        foreach ($modules as $module) {
            $handle = $module; // Module name already includes 'clinic-queue-' prefix
            $module_handles[] = $handle;
            
            wp_enqueue_script(
                $handle,
                CLINIC_QUEUE_MANAGEMENT_URL . "frontend/assets/js/widgets/clinic-queue/modules/{$module}.js",
                $module === 'clinic-queue-utils' ? ['jquery'] : ['clinic-queue-utils'],
                CLINIC_QUEUE_MANAGEMENT_VERSION,
                true
            );
        }

        // Enqueue main script that depends on all modules
        wp_enqueue_script(
            'clinic-queue-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/assets/js/widgets/clinic-queue/clinic-queue.js',
            array_merge(['jquery', 'select2-js'], $module_handles),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('clinic-queue-script', 'clinicQueueAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_ajax')
        ));

        // Localize script with widget data (empty for admin, will be set by widget)
        wp_localize_script('clinic-queue-script', 'clinicQueueData', array(
            'appointments' => [],
            'doctors' => [],
            'clinics' => [],
            'treatments' => [],
            'settings' => [],
            'field_updates' => []
        ));
    }
    
    /**
     * Get dashboard data
     * Gets actual scheduler data from WordPress database
     */
    private function get_dashboard_data() {
        // Get count of schedulers from database
        $schedulers_query = new WP_Query(array(
            'post_type' => 'schedulers',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids' // Only get IDs for counting
        ));
        
        $schedulers_count = $schedulers_query->found_posts;
        
        return array(
            'calendars_count' => $schedulers_count,
            'calendars' => array() // Empty - not needed for dashboard display
        );
    }
    
    /**
     * Render widget preview HTML
     */
    public function render_widget_preview($settings, $widget_settings, $doctors, $clinics, $treatment_types) {
        $selection_mode = $settings['selection_mode'] ?? 'doctor';
        $use_specific_treatment = $settings['use_specific_treatment'] ?? 'no';
        
        // Determine which fields to show
        $show_treatment_field = ($use_specific_treatment === 'no');
        $show_doctor_field = ($selection_mode === 'doctor');
        $show_clinic_field = ($selection_mode === 'clinic');
        
        // Get options
        $doctors_options = array();
        foreach ($doctors as $id => $doctor) {
            $doctors_options[$id] = $doctor['name'];
        }
        
        $clinics_options = array();
        foreach ($clinics as $id => $clinic) {
            $clinics_options[$id] = $clinic['name'];
        }
        
        $treatment_types_options = array();
        foreach ($treatment_types as $type) {
            $treatment_types_options[$type] = $type;
        }
        
        ?>
        <div class="appointments-calendar"
            style="max-width: 478px; margin: 0 auto; min-height: 459px; display: flex; flex-direction: column;"
            data-selection-mode="<?php echo esc_attr($selection_mode); ?>"
            data-use-specific-treatment="<?php echo esc_attr($use_specific_treatment); ?>"
            data-specific-clinic-id="<?php echo esc_attr($settings['specific_clinic_id'] ?? ''); ?>"
            data-specific-doctor-id="<?php echo esc_attr($settings['specific_doctor_id'] ?? ''); ?>"
            data-specific-treatment-type="<?php echo esc_attr($settings['specific_treatment_type'] ?? ''); ?>"
            id="admin-widget-preview-<?php echo uniqid(); ?>">
            <div class="top-section">
                <!-- Selection Form -->
                <form class="widget-selection-form" id="clinic-queue-form-<?php echo uniqid(); ?>">
                    <input type="hidden" name="selection_mode" value="<?php echo esc_attr($selection_mode); ?>">

                    <?php if ($show_treatment_field): ?>
                        <select id="widget-treatment-select" name="treatment_type" class="form-field-select" data-field="treatment_type">
                            <?php foreach ($treatment_types_options as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($widget_settings['effective_treatment_type'] ?? 'רפואה כללית', $id); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <?php if ($show_doctor_field): ?>
                        <select id="widget-doctor-select" name="doctor_id" class="form-field-select" data-field="doctor_id">
                            <?php foreach ($doctors_options as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($widget_settings['effective_doctor_id'] ?? '1', $id); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <?php if ($show_clinic_field): ?>
                        <select id="widget-clinic-select" name="clinic_id" class="form-field-select" data-field="clinic_id">
                            <?php foreach ($clinics_options as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($widget_settings['effective_clinic_id'] ?? '1', $id); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </form>

                <!-- Month and Year Header -->
                <h2 class="month-and-year">טוען...</h2>

                <!-- Days Carousel/Tabs -->
                <div class="days-carousel">
                    <div class="days-container">
                        <!-- Days will be loaded via JavaScript -->
                    </div>
                </div>
            </div>
            <div class="bottom-section">
                <!-- Time Slots for Selected Day -->
                <div class="time-slots-container">
                    <!-- Time slots will be loaded via JavaScript -->
                </div>
            </div>
        </div>
        <?php
    }
    
}
