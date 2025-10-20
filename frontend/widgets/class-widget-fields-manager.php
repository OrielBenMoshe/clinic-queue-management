<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Fields Manager for Clinic Queue Management
 * Handles widget field management and data population
 */
class Clinic_Queue_Widget_Fields_Manager {
    
    private static $instance = null;
    private $api_manager;
    
    public function __construct() {
        $this->api_manager = Clinic_Queue_API_Manager::get_instance();
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
     * Get doctors options for dropdown
     */
    public function get_doctors_options() {
        // Get doctors from mock data (in production, this would come from API or database)
        $json_file = plugin_dir_path(__FILE__) . '../../data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return ['1' => 'ד"ר יוסי כהן'];
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return ['1' => 'ד"ר יוסי כהן'];
        }
        
        $options = [];
        $doctors = [];
        
        // Extract unique doctors from calendars
        foreach ($data['calendars'] as $calendar) {
            $doctor_id = $calendar['doctor_id'];
            if (!isset($doctors[$doctor_id])) {
                $doctors[$doctor_id] = [
                    'name' => $calendar['doctor_name'],
                ];
            }
        }
        
        foreach ($doctors as $id => $doctor) {
            $options[$id] = $doctor['name'] . ' - ' . $doctor['specialty'];
        }
        
        return $options;
    }
    
    /**
     * Get clinics options for specific doctor
     */
    public function get_clinics_options($doctor_id) {
        $json_file = plugin_dir_path(__FILE__) . '../../data/mock-data.json';
        
        if (!file_exists($json_file)) {
            return ['1' => 'מרפאה תל אביב'];
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['calendars'])) {
            return ['1' => 'מרפאה תל אביב'];
        }
        
        $options = [];
        $clinics = [];
        
        // Extract unique clinics for this doctor from calendars
        foreach ($data['calendars'] as $calendar) {
            if ($calendar['doctor_id'] == $doctor_id) {
                $clinic_id = $calendar['clinic_id'];
                if (!isset($clinics[$clinic_id])) {
                    $clinics[$clinic_id] = $calendar['clinic_name'];
                }
            }
        }
        
        foreach ($clinics as $id => $name) {
            $options[$id] = $name;
        }
        
        return $options;
    }
    
    /**
     * Get treatment types options
     */
    public function get_treatment_types_options() {
        return [
            'general' => 'רפואה כללית',
            'cardiology' => 'קרדיולוגיה',
            'dermatology' => 'דרמטולוגיה',
            'orthopedics' => 'אורתופדיה',
            'pediatrics' => 'רפואת ילדים',
            'gynecology' => 'גינקולוגיה',
            'neurology' => 'נוירולוגיה',
            'psychiatry' => 'פסיכיאטריה'
        ];
    }
    
    /**
     * Get appointments data for widget
     */
    public function get_appointments_data($doctor_id, $clinic_id, $treatment_type = '') {
        return $this->api_manager->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
    }
    
    /**
     * Register Elementor widget controls
     */
    public function register_widget_controls($widget) {
        // Content Section
        $widget->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Settings', 'clinic-queue-management'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        // Doctor Selection
        $widget->add_control(
            'doctor_id',
            [
                'label' => esc_html__('Select Doctor', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '1',
                'options' => $this->get_doctors_options(),
                'description' => esc_html__('Choose a doctor from the list', 'clinic-queue-management'),
            ]
        );
        
        // Clinic Selection
        $widget->add_control(
            'clinic_id',
            [
                'label' => esc_html__('Select Clinic', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '1',
                'options' => $this->get_clinics_options('1'),
                'description' => esc_html__('Choose a clinic from the list', 'clinic-queue-management'),
            ]
        );
        
        // Treatment Type Selection
        $widget->add_control(
            'treatment_type',
            [
                'label' => esc_html__('Treatment Type', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'general',
                'options' => $this->get_treatment_types_options(),
                'description' => esc_html__('Choose treatment type', 'clinic-queue-management'),
            ]
        );
        
        // CTA Button Label
        $widget->add_control(
            'cta_label',
            [
                'label' => esc_html__('Book Button Label', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('הזמן תור', 'clinic-queue-management'),
                'placeholder' => esc_html__('Enter button text...', 'clinic-queue-management'),
            ]
        );
        
        // View All Button Label
        $widget->add_control(
            'view_all_label',
            [
                'label' => esc_html__('View All Button Label', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('צפייה בכל התורים', 'clinic-queue-management'),
                'placeholder' => esc_html__('Enter button text...', 'clinic-queue-management'),
            ]
        );
        
        // Auto Sync
        $widget->add_control(
            'auto_sync',
            [
                'label' => esc_html__('Auto Sync Data', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'clinic-queue-management'),
                'label_off' => esc_html__('No', 'clinic-queue-management'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => esc_html__('Automatically sync data from API', 'clinic-queue-management'),
            ]
        );
        
        $widget->end_controls_section();
        
        // Style Section
        $this->register_style_controls($widget);
    }
    
    /**
     * Register style controls
     */
    private function register_style_controls($widget) {
        $widget->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Style', 'clinic-queue-management'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        // Container styles
        $widget->add_control(
            'container_heading',
            [
                'label' => esc_html__('Container', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $widget->add_control(
            'container_background',
            [
                'label' => esc_html__('Background Color', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ap-widget' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $widget->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .ap-widget',
            ]
        );
        
        $widget->add_control(
            'container_border_radius',
            [
                'label' => esc_html__('Border Radius', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ap-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        // Primary color
        $widget->add_control(
            'primary_color',
            [
                'label' => esc_html__('Primary Color', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e91e63',
                'selectors' => [
                    '{{WRAPPER}} .ap-day-btn.selected' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .ap-book-btn' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .ap-slot-btn:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        // Secondary color
        $widget->add_control(
            'secondary_color',
            [
                'label' => esc_html__('Secondary Color', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1e40af',
                'selectors' => [
                    '{{WRAPPER}} .ap-slot-btn.selected' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .ap-slot-count' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        // Button styles
        $widget->add_control(
            'button_heading',
            [
                'label' => esc_html__('Buttons', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $widget->add_control(
            'button_text_color',
            [
                'label' => esc_html__('Text Color', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ap-book-btn' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .ap-view-btn' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $widget->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .ap-book-btn, {{WRAPPER}} .ap-view-btn',
            ]
        );
        
        $widget->end_controls_section();
    }
    
    /**
     * Get widget data for rendering
     */
    public function get_widget_data($settings) {
        $doctor_id = $settings['doctor_id'] ?? '1';
        $clinic_id = $settings['clinic_id'] ?? '1';
        $treatment_type = $settings['treatment_type'] ?? 'general';
        
        // Get appointments data
        $appointments_data = $this->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
        
        if (!$appointments_data) {
            return [
                'error' => true,
                'message' => 'לא ניתן לטעון נתוני תורים'
            ];
        }
        
        return [
            'error' => false,
            'data' => $appointments_data,
            'settings' => [
                'cta_label' => $settings['cta_label'] ?? 'הזמן תור',
                'view_all_label' => $settings['view_all_label'] ?? 'צפייה בכל התורים',
                'auto_sync' => $settings['auto_sync'] ?? 'yes'
            ]
        ];
    }
    
    /**
     * Handle AJAX request for appointments data
     */
    public function handle_ajax_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_die('Security check failed');
        }
        
        $doctor_id = sanitize_text_field($_POST['doctor_id']);
        $clinic_id = sanitize_text_field($_POST['clinic_id']);
        $treatment_type = sanitize_text_field($_POST['treatment_type']);
        
        $data = $this->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
        
        if ($data) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error('Failed to load appointments data');
        }
    }
    
    /**
     * Handle AJAX request for booking appointment
     */
    public function handle_booking_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_booking')) {
            wp_die('Security check failed');
        }
        
        $doctor_id = sanitize_text_field($_POST['doctor_id']);
        $clinic_id = sanitize_text_field($_POST['clinic_id']);
        $treatment_type = sanitize_text_field($_POST['treatment_type']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $patient_name = sanitize_text_field($_POST['patient_name']);
        $patient_phone = sanitize_text_field($_POST['patient_phone']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        $appointment_manager = Clinic_Queue_Appointment_Manager::get_instance();
        
        $result = $appointment_manager->book_appointment(
            $doctor_id,
            $clinic_id,
            $treatment_type,
            $date,
            $time,
            $patient_name,
            $patient_phone,
            $notes
        );
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}
