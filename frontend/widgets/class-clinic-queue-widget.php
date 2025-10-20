<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if Elementor is loaded
if (!class_exists('Elementor\Widget_Base')) {
    return;
}

// Check if Elementor is properly loaded
if (!defined('ELEMENTOR_VERSION')) {
    return;
}

// Check if Elementor is active
if (!did_action('elementor/loaded')) {
    return;
}

// Check if Elementor is loaded
if (!function_exists('elementor_load_plugin')) {
    return;
}

// Check if Elementor is properly initialized
if (!class_exists('Elementor\Plugin')) {
    return;
}

// Check if Elementor is ready
if (!did_action('elementor/init')) {
    return;
}

// Check if Elementor is ready for widgets
if (!did_action('elementor/widgets/register')) {
    return;
}

/**
 * Elementor Clinic Queue Widget
 */
class Clinic_Queue_Widget extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'clinic-queue-widget';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return esc_html__('Clinic Queue', 'clinic-queue-management');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-calendar';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['general'];
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['clinic', 'queue', 'appointment', 'medical', 'booking', 'date', 'time', 'slot'];
    }
    
    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        // Ensure assets are enqueued
        $this->enqueue_widget_assets();
        return ['clinic-queue-style'];
    }
    
    /**
     * Get script dependencies
     */
    public function get_script_depends() {
        // Ensure assets are enqueued
        $this->enqueue_widget_assets();
        return ['clinic-queue-script'];
    }
    
    /**
     * Enqueue widget assets (called only once per page)
     */
    private function enqueue_widget_assets() {
        static $assets_enqueued = false;
        
        if ($assets_enqueued) {
            return;
        }
        
        // Enqueue Assistant font first
        wp_enqueue_style(
            'clinic-queue-assistant-font',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/global-assistant-font.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        wp_enqueue_style(
            'clinic-queue-style',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/assets/css/clinic-queue.css',
            array('clinic-queue-assistant-font'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        wp_enqueue_script(
            'clinic-queue-script',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/assets/js/clinic-queue.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('clinic-queue-script', 'clinicQueueAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clinic_queue_ajax')
        ));
        
        $assets_enqueued = true;
    }
    
    /**
     * Register widget controls
     */
    protected function register_controls() {
        $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
        $fields_manager->register_widget_controls($this);
    }

    
    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get widget data using the fields manager
        $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
        $widget_data = $fields_manager->get_widget_data($settings);
        
        if ($widget_data['error']) {
            echo '<div class="ap-widget ap-error">' . esc_html($widget_data['message']) . '</div>';
            return;
        }
        
        // Render the appointments calendar component with the same structure as admin/dialog
        $this->render_widget_html($settings, $widget_data['data']);
    }
    
    /**
     * Render the widget HTML using the same appointments-calendar component
     * used by the admin dialog and calendars view.
     */
    private function render_widget_html($settings, $appointments_data) {
        // Use centralized constants to avoid duplication
        if (!class_exists('Clinic_Queue_Constants')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/constants.php';
        }
        $hebrew_months = class_exists('Clinic_Queue_Constants') ? Clinic_Queue_Constants::get_hebrew_months() : array();
        $hebrew_day_abbrev = class_exists('Clinic_Queue_Constants') ? Clinic_Queue_Constants::get_hebrew_day_abbrev() : array();

        if (empty($appointments_data)) {
            echo '<div class="appointments-calendar">'
               . '<div class="notice notice-info">'
               . '<p>אין תורים זמינים לתקופה הקרובה.</p>'
               . '</div>'
               . '</div>';
            return;
        }

        $first_date = strtotime($appointments_data[0]['date']->appointment_date);
        $current_month = date('F', $first_date);
        $current_year = date('Y', $first_date);
        ?>
        <div class="appointments-calendar">
            <!-- Month and Year Header -->
            <div class="calendar-header">
                <h2><?php echo $hebrew_months[$current_month] . ', ' . $current_year; ?></h2>
            </div>

            <!-- Days Carousel/Tabs -->
            <div class="days-carousel">
                <div class="days-container">
                    <?php foreach ($appointments_data as $appointment): ?>
                        <?php
                        $date = strtotime($appointment['date']->appointment_date);
                        $day_number = date('j', $date);
                        $day_name = date('l', $date);
                        $total_slots = isset($appointment['time_slots']) ? count($appointment['time_slots']) : 0;
                        ?>
                        <div class="day-tab" data-date="<?php echo date('Y-m-d', $date); ?>">
                            <div class="day-abbrev"><?php echo $hebrew_day_abbrev[$day_name]; ?></div>
                            <div class="day-content">
                                <div class="day-number"><?php echo $day_number; ?></div>
                                <div class="day-slots-count"><?php echo $total_slots; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Time Slots for Selected Day -->
            <div class="time-slots-container">
                <?php foreach ($appointments_data as $index => $appointment): ?>
                    <div class="day-time-slots <?php echo $index === 0 ? 'active' : ''; ?>" data-date="<?php echo date('Y-m-d', strtotime($appointment['date']->appointment_date)); ?>">
                        <?php if (empty($appointment['time_slots'])): ?>
                            <p class="no-slots">אין תורים זמינים</p>
                        <?php else: ?>
                            <div class="time-slots-grid">
                                <?php foreach ($appointment['time_slots'] as $slot): ?>
                                    <div class="time-slot-badge <?php echo !empty($slot->is_booked) ? 'booked' : 'free'; ?>">
                                        <?php echo date('H:i', strtotime($slot->time_slot)); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        // Stats section similar to admin (optional in widget, but keeps parity)
        $total_dates = count($appointments_data);
        $total_slots = 0;
        $booked_slots = 0;
        $free_slots = 0;
        foreach ($appointments_data as $appointment) {
            if (!empty($appointment['time_slots'])) {
                foreach ($appointment['time_slots'] as $slot) {
                    $total_slots++;
                    if (!empty($slot->is_booked)) {
                        $booked_slots++;
                    } else {
                        $free_slots++;
                    }
                }
            }
        }
        ?>
        <div class="appointments-overview">
            <h3>סטטיסטיקות תורים</h3>
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
        <?php
    }
    
    /**
     * Render widget output in the editor (same as frontend for this widget)
     */
    protected function content_template() {
        ?>
        <#
        var doctorId = settings.doctor_id || '1';
        var clinicId = settings.clinic_id || '';
        var ctaLabel = settings.cta_label || 'הזמן תור';
        var widgetId = 'elementor-preview-' + Math.random().toString(36).substr(2, 9);
        #>
        <div id="clinic-queue-{{{ widgetId }}}" class="ap-widget <?php echo is_rtl() ? 'ap-rtl' : 'ap-ltr'; ?>" 
             data-doctor-id="{{{ doctorId }}}"
             data-clinic-id="{{{ clinicId }}}"
             data-cta-label="{{{ ctaLabel }}}">
            
            <!-- Loading state for editor preview -->
            <div class="ap-loading">
                <div class="ap-spinner"></div>
                <p>טוען נתונים...</p>
            </div>
            
        </div>
        <?php
    }
}