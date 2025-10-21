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
            $specialty = isset($doctor['specialty']) ? $doctor['specialty'] : '';
            $options[$id] = $doctor['name'] . ($specialty ? ' - ' . $specialty : '');
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
     * Get all clinics options (for clinic selection mode)
     */
    public function get_all_clinics_options() {
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
        
        // Extract unique clinics from calendars
        foreach ($data['calendars'] as $calendar) {
            $clinic_id = $calendar['clinic_id'];
            if (!isset($clinics[$clinic_id])) {
                $clinics[$clinic_id] = [
                    'name' => $calendar['clinic_name'],
                    'address' => $calendar['clinic_address'] ?? '',
                ];
            }
        }
        
        foreach ($clinics as $id => $clinic) {
            $options[$id] = $clinic['name'];
        }
        
        return $options;
    }
    public function get_treatment_types_options() {
        return [
            'רפואה כללית' => 'רפואה כללית',
            'קרדיולוגיה' => 'קרדיולוגיה',
            'דרמטולוגיה' => 'דרמטולוגיה',
            'אורתופדיה' => 'אורתופדיה',
            'רפואת ילדים' => 'רפואת ילדים',
            'גינקולוגיה' => 'גינקולוגיה',
            'נוירולוגיה' => 'נוירולוגיה',
            'פסיכיאטריה' => 'פסיכיאטריה'
        ];
    }
    
    /**
     * Get treatment types filtered by doctor
     */
    public function get_treatment_types_by_doctor($doctor_id) {
        $mock_data_file = plugin_dir_path(__FILE__) . '../../data/mock-data.json';
        if (!file_exists($mock_data_file)) {
            return $this->get_treatment_types_options();
        }
        
        $json_content = file_get_contents($mock_data_file);
        $data = json_decode($json_content, true);
        
        if (!$data || !isset($data['calendars'])) {
            return $this->get_treatment_types_options();
        }
        
        $treatment_types = [];
        foreach ($data['calendars'] as $calendar) {
            if ($calendar['doctor_id'] == $doctor_id) {
                $treatment_type = $calendar['treatment_type'];
                $treatment_types[$treatment_type] = $treatment_type;
            }
        }
        
        return !empty($treatment_types) ? $treatment_types : $this->get_treatment_types_options();
    }
    
    /**
     * Get treatment types filtered by clinic
     */
    public function get_treatment_types_by_clinic($clinic_id) {
        $mock_data_file = plugin_dir_path(__FILE__) . '../../data/mock-data.json';
        if (!file_exists($mock_data_file)) {
            return $this->get_treatment_types_options();
        }
        
        $json_content = file_get_contents($mock_data_file);
        $data = json_decode($json_content, true);
        
        if (!$data || !isset($data['calendars'])) {
            return $this->get_treatment_types_options();
        }
        
        $treatment_types = [];
        foreach ($data['calendars'] as $calendar) {
            if ($calendar['clinic_id'] == $clinic_id) {
                $treatment_type = $calendar['treatment_type'];
                $treatment_types[$treatment_type] = $treatment_type;
            }
        }
        
        return !empty($treatment_types) ? $treatment_types : $this->get_treatment_types_options();
    }
    
    /**
     * Get appointments data for widget - only via API
     */
    public function get_appointments_data($doctor_id, $clinic_id, $treatment_type = '') {
        // Get data from API manager only
        $api_data = $this->api_manager->get_appointments_data($doctor_id, $clinic_id, $treatment_type);
        
        if ($api_data && !empty($api_data['days'])) {
            return $this->convert_api_data_to_widget_format($api_data);
        }
        
        return null;
    }
    
    
    /**
     * Convert API data format to widget format
     */
    private function convert_api_data_to_widget_format($api_data) {
        if (!isset($api_data['days']) || !is_array($api_data['days'])) {
            return null;
        }
        
        $appointments_data = [];
        
        foreach ($api_data['days'] as $day) {
            $time_slots = [];
            foreach ($day['slots'] as $slot) {
                $time_slots[] = (object) [
                    'time_slot' => $slot['time'],
                    'is_booked' => $slot['booked'] ? 1 : 0,
                    'patient_name' => $slot['patient_name'] ?? null,
                    'patient_phone' => $slot['patient_phone'] ?? null
                ];
            }
            
            $appointments_data[] = [
                'date' => (object) [
                    'appointment_date' => $day['date']
                ],
                'time_slots' => $time_slots
            ];
        }
        
        return $appointments_data;
    }
    
    /**
     * Register Elementor widget controls
     */
    public function register_widget_controls($widget) {
        // Content Section
        $widget->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('הגדרות', 'clinic-queue-management'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        // סוג יומן - מה המשתמשים יכולים לבחור
        $widget->add_control(
            'selection_mode',
            [
                'label' => esc_html__('סוג יומן', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'doctor' => esc_html__('יומן רופא', 'clinic-queue-management'),
                    'clinic' => esc_html__('יומן מרפאה', 'clinic-queue-management'),
                ],
                'default' => 'doctor',
                'description' => esc_html__('בחר איזה סוג יומן להציג. האפשרות השנייה תהיה קבועה.', 'clinic-queue-management'),
            ]
        );
        
        // מזהה רופא ספציפי (מוצג כאשר מרפאה ניתנת לבחירה)
        $widget->add_control(
            'specific_doctor_id',
            [
                'label' => esc_html__('מזהה רופא קבוע', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1',
                'placeholder' => esc_html__('הזן מזהה רופא או תג דינמי', 'clinic-queue-management'),
                'description' => esc_html__('הזן מזהה רופא ספציפי או השתמש בתג דינמי (למשל, {post_id})', 'clinic-queue-management'),
                'condition' => [
                    'selection_mode' => 'clinic',
                ],
            ]
        );
        
        // Dynamic Content Button for Doctor ID (Elementor Pro)
        if (defined('ELEMENTOR_PRO_VERSION') || class_exists('\ElementorPro\Modules\DynamicTags\Module')) {
            $widget->add_control(
                'doctor_id_dynamic_button',
                [
                    'type' => \Elementor\Controls_Manager::BUTTON,
                    'label' => esc_html__('תוכן דינמי', 'clinic-queue-management'),
                    'text' => esc_html__('⚡ תגים דינמיים', 'clinic-queue-management'),
                    'button_type' => 'default',
                    'description' => esc_html__('הוסף תגים דינמיים באמצעות Elementor Pro', 'clinic-queue-management'),
                    'condition' => [
                        'selection_mode' => 'clinic',
                    ],
                    'event' => 'clinic_queue:open_dynamic_tags',
                    'args' => [
                        'field' => 'specific_doctor_id'
                    ],
                ]
            );
        }
        
        // מזהה מרפאה ספציפית (מוצג כאשר רופא ניתן לבחירה)
        $widget->add_control(
            'specific_clinic_id',
            [
                'label' => esc_html__('מזהה מרפאה קבועה', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1',
                'placeholder' => esc_html__('הזן מזהה מרפאה או תג דינמי', 'clinic-queue-management'),
                'description' => esc_html__('הזן מזהה מרפאה ספציפית או השתמש בתג דינמי (למשל, {post_id})', 'clinic-queue-management'),
                'condition' => [
                    'selection_mode' => 'doctor',
                ],
            ]
        );
        
        // Dynamic Content Button for Clinic ID (Elementor Pro)
        if (defined('ELEMENTOR_PRO_VERSION') || class_exists('\ElementorPro\Modules\DynamicTags\Module')) {
            $widget->add_control(
                'clinic_id_dynamic_button',
                [
                    'type' => \Elementor\Controls_Manager::BUTTON,
                    'label' => esc_html__('תוכן דינמי', 'clinic-queue-management'),
                    'text' => esc_html__('⚡ תגים דינמיים', 'clinic-queue-management'),
                    'button_type' => 'default',
                    'description' => esc_html__('הוסף תגים דינמיים באמצעות Elementor Pro', 'clinic-queue-management'),
                    'condition' => [
                        'selection_mode' => 'doctor',
                    ],
                    'event' => 'clinic_queue:open_dynamic_tags',
                    'args' => [
                        'field' => 'specific_clinic_id'
                    ],
                ]
            );
        }
        
        // החלפת סוג טיפול ספציפי
        $widget->add_control(
            'use_specific_treatment',
            [
                'label' => esc_html__('השתמש בסוג טיפול ספציפי', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('כן', 'clinic-queue-management'),
                'label_off' => esc_html__('לא', 'clinic-queue-management'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => esc_html__('אפשר להגדיר סוג טיפול ספציפי במקום בחירת המשתמש', 'clinic-queue-management'),
            ]
        );
        
        // מזהה סוג טיפול ספציפי
        $widget->add_control(
            'specific_treatment_type',
            [
                'label' => esc_html__('סוג טיפול ספציפי', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'רפואה כללית',
                'placeholder' => esc_html__('הזן סוג טיפול או תג דינמי', 'clinic-queue-management'),
                'description' => esc_html__('הזן סוג טיפול ספציפי או השתמש בתג דינמי (למשל, {post_id})', 'clinic-queue-management'),
                'condition' => [
                    'use_specific_treatment' => 'yes',
                ],
            ]
        );
        
        // כפתור תוכן דינמי עבור סוג טיפול (Elementor Pro)
        if (defined('ELEMENTOR_PRO_VERSION') || class_exists('\ElementorPro\Modules\DynamicTags\Module')) {
            $widget->add_control(
                'treatment_type_dynamic_button',
                [
                    'type' => \Elementor\Controls_Manager::BUTTON,
                    'label' => esc_html__('תוכן דינמי', 'clinic-queue-management'),
                    'text' => esc_html__('⚡ תגים דינמיים', 'clinic-queue-management'),
                    'button_type' => 'default',
                    'description' => esc_html__('הוסף תגים דינמיים באמצעות Elementor Pro', 'clinic-queue-management'),
                    'condition' => [
                        'use_specific_treatment' => 'yes',
                    ],
                    'event' => 'clinic_queue:open_dynamic_tags',
                    'args' => [
                        'field' => 'specific_treatment_type'
                    ],
                ]
            );
        }
        
        // תווית כפתור הזמנה
        $widget->add_control(
            'cta_label',
            [
                'label' => esc_html__('תווית כפתור הזמנה', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('הזמן תור', 'clinic-queue-management'),
                'placeholder' => esc_html__('הזן טקסט כפתור...', 'clinic-queue-management'),
            ]
        );
        
        // תווית כפתור צפייה בכל התורים
        $widget->add_control(
            'view_all_label',
            [
                'label' => esc_html__('תווית כפתור צפייה בכל התורים', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('צפייה בכל התורים', 'clinic-queue-management'),
                'placeholder' => esc_html__('הזן טקסט כפתור...', 'clinic-queue-management'),
            ]
        );
        
        
        // רוחב הוויג'ט
        $widget->add_responsive_control(
            'widget_width',
            [
                'label' => esc_html__('רוחב הוויג\'ט', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vw'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 800,
                        'step' => 1,
                    ],
                    '%' => [
                        'min' => 50,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 478,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ap-widget' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
                'description' => esc_html__('הגדר את הרוחב המקסימלי של הוויג\'ט', 'clinic-queue-management'),
            ]
        );
        
        $widget->end_controls_section();
        
        // סקציית עיצוב
        $this->register_style_controls($widget);
    }
    
    /**
     * רישום בקרות עיצוב
     */
    private function register_style_controls($widget) {
        $widget->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('עיצוב', 'clinic-queue-management'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        // עיצובי קונטיינר
        $widget->add_control(
            'container_heading',
            [
                'label' => esc_html__('קונטיינר', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $widget->add_control(
            'container_background',
            [
                'label' => esc_html__('צבע רקע', 'clinic-queue-management'),
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
                'label' => esc_html__('רדיוס גבול', 'clinic-queue-management'),
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
                'label' => esc_html__('צבע ראשי', 'clinic-queue-management'),
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
                'label' => esc_html__('צבע משני', 'clinic-queue-management'),
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
                'label' => esc_html__('כפתורים', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $widget->add_control(
            'button_text_color',
            [
                'label' => esc_html__('צבע טקסט', 'clinic-queue-management'),
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
     * Get widget data for rendering - only settings, data will be loaded via API
     */
    public function get_widget_data($settings) {
        // Determine which values to use based on switchers
        $doctor_id = $this->get_effective_doctor_id($settings);
        $clinic_id = $this->get_effective_clinic_id($settings);
        $treatment_type = $this->get_effective_treatment_type($settings);
        
        return [
            'error' => false,
            'settings' => [
                'cta_label' => $settings['cta_label'] ?? 'הזמן תור',
                'view_all_label' => $settings['view_all_label'] ?? 'צפייה בכל התורים',
                'selection_mode' => $settings['selection_mode'] ?? 'doctor',
                'use_specific_treatment' => $settings['use_specific_treatment'] ?? 'no',
                'effective_doctor_id' => $doctor_id,
                'effective_clinic_id' => $clinic_id,
                'effective_treatment_type' => $treatment_type
            ]
        ];
    }
    
    /**
     * Get effective doctor ID based on selection mode
     */
    private function get_effective_doctor_id($settings) {
        $selection_mode = $settings['selection_mode'] ?? 'doctor';
        
        if ($selection_mode === 'clinic') {
            // Doctor is fixed when clinic is selectable
            $specific_id = $settings['specific_doctor_id'] ?? '1';
            return $this->process_dynamic_tag($specific_id);
        }
        
        // Doctor is selectable when selection_mode is 'doctor'
        // Return default for initial load, will be updated by user selection
        return '1';
    }
    
    /**
     * Get effective clinic ID based on selection mode
     */
    private function get_effective_clinic_id($settings) {
        $selection_mode = $settings['selection_mode'] ?? 'doctor';
        
        if ($selection_mode === 'doctor') {
            // Clinic is fixed when doctor is selectable
            $specific_id = $settings['specific_clinic_id'] ?? '1';
            return $this->process_dynamic_tag($specific_id);
        }
        
        // Clinic is selectable when selection_mode is 'clinic'
        // Return default for initial load, will be updated by user selection
        return '1';
    }
    
    /**
     * Get effective treatment type based on switcher settings
     */
    private function get_effective_treatment_type($settings) {
        if (($settings['use_specific_treatment'] ?? 'no') === 'yes') {
            $specific_type = $settings['specific_treatment_type'] ?? 'רפואה כללית';
            // Process dynamic tags if needed
            return $this->process_dynamic_tag($specific_type);
        }
        // Default treatment type when not using specific treatment
        return 'רפואה כללית';
    }
    
    /**
     * Process dynamic tags in field values
     */
    private function process_dynamic_tag($value) {
        // Handle {post_id} dynamic tag
        if (strpos($value, '{post_id}') !== false) {
            global $post;
            if ($post && isset($post->ID)) {
                $value = str_replace('{post_id}', $post->ID, $value);
            } else {
                // Fallback to current post ID if available
                $post_id = get_the_ID();
                if ($post_id) {
                    $value = str_replace('{post_id}', $post_id, $value);
                }
            }
        }
        
        // Handle {current_date} dynamic tag
        if (strpos($value, '{current_date}') !== false) {
            $value = str_replace('{current_date}', date('Y-m-d'), $value);
        }
        
        // Handle {current_time} dynamic tag
        if (strpos($value, '{current_time}') !== false) {
            $value = str_replace('{current_time}', date('H:i:s'), $value);
        }
        
        // Handle {user_id} dynamic tag
        if (strpos($value, '{user_id}') !== false) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $value = str_replace('{user_id}', $user_id, $value);
            }
        }
        
        return $value;
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
     * Handle AJAX request for getting clinics by doctor
     */
    public function handle_get_clinics_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_die('Security check failed');
        }
        
        $doctor_id = sanitize_text_field($_POST['doctor_id']);
        $clinics_options = $this->get_clinics_options($doctor_id);
        
        // Convert to array format for JavaScript
        $clinics_data = [];
        foreach ($clinics_options as $id => $name) {
            $clinics_data[] = [
                'id' => $id,
                'name' => $name
            ];
        }
        
        wp_send_json_success($clinics_data);
    }
    
    /**
     * Handle AJAX request for getting doctors by clinic
     */
    public function handle_get_doctors_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clinic_queue_ajax')) {
            wp_die('Security check failed');
        }
        
        $clinic_id = sanitize_text_field($_POST['clinic_id']);
        $doctors_options = $this->get_doctors_by_clinic($clinic_id);
        
        // Convert to array format for JavaScript
        $doctors_data = [];
        foreach ($doctors_options as $id => $name) {
            $doctors_data[] = [
                'id' => $id,
                'name' => $name
            ];
        }
        
        wp_send_json_success($doctors_data);
    }
    
    /**
     * Get doctors options for specific clinic
     */
    public function get_doctors_by_clinic($clinic_id) {
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
        
        // Extract unique doctors for this clinic from calendars
        foreach ($data['calendars'] as $calendar) {
            if ($calendar['clinic_id'] == $clinic_id) {
                $doctor_id = $calendar['doctor_id'];
                if (!isset($doctors[$doctor_id])) {
                    $doctors[$doctor_id] = [
                        'name' => $calendar['doctor_name'],
                    ];
                }
            }
        }
        
        foreach ($doctors as $id => $doctor) {
            $specialty = isset($doctor['specialty']) ? $doctor['specialty'] : '';
            $options[$id] = $doctor['name'] . ($specialty ? ' - ' . $specialty : '');
        }
        
        return $options;
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
