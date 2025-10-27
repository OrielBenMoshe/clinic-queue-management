<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Fields Manager for Clinic Queue Management
 * 
 * REFACTORED: This is now an orchestrator that delegates to specialized managers
 * - Calendar Data Provider: Raw data retrieval
 * - Calendar Filter Engine: Filtering logic
 * - Widget AJAX Handlers: AJAX endpoints
 * 
 * This class maintains:
 * - Elementor Controls (tightly coupled with widget)
 * - Dynamic Tags Processing (small utility)
 * - Public API for backward compatibility
 * - Delegation to specialized managers
 */
class Clinic_Queue_Widget_Fields_Manager {
    
    private static $instance = null;
    private $data_provider;
    private $filter_engine;
    private $ajax_handlers;
    private $api_manager;
    
    public function __construct() {
        // Load dependencies
        try {
            $this->load_managers();
            
            // Initialize managers with error checks
            if (class_exists('Clinic_Queue_Calendar_Data_Provider')) {
                $this->data_provider = Clinic_Queue_Calendar_Data_Provider::get_instance();
            }
            if (class_exists('Clinic_Queue_Calendar_Filter_Engine')) {
                $this->filter_engine = Clinic_Queue_Calendar_Filter_Engine::get_instance();
            }
            if (class_exists('Clinic_Queue_Widget_Ajax_Handlers')) {
                $this->ajax_handlers = Clinic_Queue_Widget_Ajax_Handlers::get_instance();
            }
            if (class_exists('Clinic_Queue_API_Manager')) {
                $this->api_manager = Clinic_Queue_API_Manager::get_instance();
            }
        } catch (Exception $e) {
            // Log error silently to avoid breaking Elementor editor
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Clinic Queue: Error loading managers - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Load manager classes
     */
    private function load_managers() {
        $managers_path = plugin_dir_path(__FILE__) . 'managers/';
        
        $files = array(
            'class-calendar-data-provider.php',
            'class-calendar-filter-engine.php',
            'class-widget-ajax-handlers.php'
        );
        
        foreach ($files as $file) {
            $file_path = $managers_path . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Clinic Queue: Manager file not found - ' . $file_path);
                }
            }
        }
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
    
    // ============================================================================
    // PUBLIC API - Delegating to managers (Backward Compatibility)
    // ============================================================================
    
    /**
     * Get doctors options for dropdown
     * Delegates to: Data Provider
     */
    public function get_doctors_options() {
        $doctors = $this->data_provider->get_all_doctors();
        
        $options = [];
        foreach ($doctors as $id => $doctor) {
            $specialty = $doctor['specialty'] ?? '';
            $options[$id] = $doctor['name'] . ($specialty ? ' - ' . $specialty : '');
        }
        
        return !empty($options) ? $options : ['1' => 'ד"ר יוסי כהן'];
    }
    
    /**
     * Get all clinics options
     * Delegates to: Data Provider
     */
    public function get_all_clinics_options() {
        $clinics = $this->data_provider->get_all_clinics();
        
        $options = [];
        foreach ($clinics as $id => $clinic) {
            $options[$id] = $clinic['name'];
        }
        
        return !empty($options) ? $options : ['1' => 'מרפאה תל אביב'];
    }
    
    /**
     * Get treatment types options
     */
    public function get_treatment_types_options() {
        $types = $this->data_provider->get_all_treatment_types();
        
        $options = [];
        foreach ($types as $type) {
            $options[$type] = $type;
        }
        
        return $options;
    }
    
    /**
     * Get clinics options for specific doctor (LEGACY)
     * Delegates to: Filter Engine
     */
    public function get_clinics_options($doctor_id, $widget_settings = []) {
        return $this->filter_engine->get_clinics_options($doctor_id, $widget_settings);
    }
    
    /**
     * Get treatment types filtered by doctor (LEGACY)
     * Delegates to: Filter Engine
     */
    public function get_treatment_types_by_doctor($doctor_id, $widget_settings = []) {
        return $this->filter_engine->get_treatment_types_by_doctor($doctor_id, $widget_settings);
    }
    
    /**
     * Get treatment types filtered by clinic (LEGACY)
     * Delegates to: Filter Engine
     */
    public function get_treatment_types_by_clinic($clinic_id, $widget_settings = []) {
        return $this->filter_engine->get_treatment_types_by_clinic($clinic_id, $widget_settings);
    }
    
    /**
     * Get doctors options for specific clinic (LEGACY)
     * Delegates to: Filter Engine
     */
    public function get_doctors_by_clinic($clinic_id, $widget_settings = []) {
        return $this->filter_engine->get_doctors_by_clinic($clinic_id, $widget_settings);
    }
    
    /**
     * Get filtered calendars for widget
     * Delegates to: Filter Engine
     */
    public function get_filtered_calendars_for_widget($settings) {
        return $this->filter_engine->get_filtered_calendars_for_widget($settings);
    }
    
    /**
     * Get field options for current selection
     * Delegates to: Filter Engine
     */
    public function get_field_options_for_current_selection($settings, $current_selections = []) {
        return $this->filter_engine->get_field_options_for_current_selection($settings, $current_selections);
    }
    
    /**
     * Get smart field updates
     * Delegates to: Filter Engine
     */
    public function get_smart_field_updates($settings, $changed_field, $changed_value, $current_selections) {
        return $this->filter_engine->get_smart_field_updates($settings, $changed_field, $changed_value, $current_selections);
    }
    
    /**
     * Get appointments data for widget
     * Delegates to: Data Provider
     */
    public function get_appointments_data($doctor_id, $clinic_id, $treatment_type = '') {
        return $this->data_provider->get_appointments_from_api($doctor_id, $clinic_id, $treatment_type);
    }
    
    // ============================================================================
    // ELEMENTOR CONTROLS - Stays here (tightly coupled with widget)
    // ============================================================================
    
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
        
        // מזהה מרפאה ספציפית (מוצג כאשר מרפאה ניתנת לבחירה)
        $widget->add_control(
            'specific_clinic_id',
            [
                'label' => esc_html__('מזהה מרפאה קבועה', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1',
                'placeholder' => esc_html__('הזן מזהה מרפאה או תג דינמי', 'clinic-queue-management'),
                'description' => esc_html__('הזן מזהה מרפאה ספציפית או השתמש בתג דינמי (למשל, {post_id})', 'clinic-queue-management'),
                'condition' => [
                    'selection_mode' => 'clinic',
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
                        'selection_mode' => 'clinic',
                    ],
                    'event' => 'clinic_queue:open_dynamic_tags',
                    'args' => [
                        'field' => 'specific_clinic_id'
                    ],
                ]
            );
        }
        
        // מזהה רופא ספציפי (מוצג כאשר רופא ניתן לבחירה)
        $widget->add_control(
            'specific_doctor_id',
            [
                'label' => esc_html__('מזהה רופא קבוע', 'clinic-queue-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1',
                'placeholder' => esc_html__('הזן מזהה רופא או תג דינמי', 'clinic-queue-management'),
                'description' => esc_html__('הזן מזהה רופא ספציפי או השתמש בתג דינמי (למשל, {post_id})', 'clinic-queue-management'),
                'condition' => [
                    'selection_mode' => 'doctor',
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
                        'selection_mode' => 'doctor',
                    ],
                    'event' => 'clinic_queue:open_dynamic_tags',
                    'args' => [
                        'field' => 'specific_doctor_id'
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
    
    // ============================================================================
    // DYNAMIC TAGS PROCESSING - Stays here (small utility function)
    // ============================================================================
    
    /**
     * Get widget data for rendering - only settings, data will be loaded via API
     */
    public function get_widget_data($settings) {
        // Return safe defaults if settings are not available
        if (empty($settings)) {
            return [
                'error' => false,
                'settings' => [
                    'selection_mode' => 'doctor',
                    'use_specific_treatment' => 'no',
                    'effective_doctor_id' => '1',
                    'effective_clinic_id' => '1',
                    'effective_treatment_type' => ''
                ]
            ];
        }
        
        // Determine which values to use based on switchers
        $doctor_id = $this->get_effective_doctor_id($settings);
        $clinic_id = $this->get_effective_clinic_id($settings);
        $treatment_type = $this->get_effective_treatment_type($settings);
        
        // Debug logging
        error_log('[ClinicQueue] Widget data - Raw settings: ' . print_r($settings, true));
        error_log('[ClinicQueue] Widget data - Effective values: doctor_id=' . $doctor_id . ', clinic_id=' . $clinic_id . ', treatment_type=' . $treatment_type);
        
        return [
            'error' => false,
            'settings' => [
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
        // Return the specific doctor ID if set, otherwise default
        $specific_id = $settings['specific_doctor_id'] ?? '1';
        return $this->process_dynamic_tag($specific_id);
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
        // Return the specific clinic ID if set, otherwise default
        $specific_id = $settings['specific_clinic_id'] ?? '1';
        return $this->process_dynamic_tag($specific_id);
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
        // Debug logging
        error_log('[ClinicQueue] Processing dynamic tag - Input value: ' . $value);
        
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
        
        // Debug logging
        error_log('[ClinicQueue] Processing dynamic tag - Output value: ' . $value);
        
        return $value;
    }
}
