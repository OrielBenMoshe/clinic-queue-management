<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Controller for Clinic Queue Management
 * (Renamed from Widget_Fields_Manager)
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
class Clinic_Queue_Widget_Controller {
    
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
            // Silently fail to avoid breaking Elementor editor
        } catch (Error $e) {
            // Catch PHP 7+ fatal errors as well
        }
    }
    
    /**
     * Load manager classes
     * Enhanced with comprehensive error handling
     */
    private function load_managers() {
        $managers_path = __DIR__ . '/managers/';
        
        $files = array(
            'class-calendar-data-provider.php',
            'class-calendar-filter-engine.php',
            'class-widget-ajax-handlers.php'
        );
        
        foreach ($files as $file) {
            $file_path = $managers_path . $file;
            if (file_exists($file_path)) {
                try {
                    require_once $file_path;
                } catch (Exception $e) {
                    // Don't break - allow graceful degradation
                } catch (Error $e) {
                    // Catch PHP 7+ errors as well
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
        if (!$this->data_provider) {
            return ['1' => 'ד"ר יוסי כהן'];
        }
        
        $doctors = $this->data_provider->get_all_doctors();
        
        $options = [];
        foreach ($doctors as $id => $doctor) {
            $specialty = $doctor['specialty'] ?? '';
            $options[$id] = $doctor['name'] . ($specialty ? ' - ' . $specialty : '');
        }
        
        return !empty($options) ? $options : ['1' => 'ד"ר יוסי כהן'];
    }
    
    /**
     * Get schedulers options for specific clinic
     * Returns array of scheduler options for dropdown
     * Delegates to: Data Provider
     * 
     * @param int $clinic_id The clinic ID
     * @return array Array of options: [scheduler_id => 'Doctor Name - Treatment Type']
     */
    public function get_schedulers_by_clinic($clinic_id) {
        if (!$this->data_provider) {
            return [];
        }
        
        $schedulers = $this->data_provider->get_schedulers_by_clinic($clinic_id);
        
        $options = [];
        foreach ($schedulers as $id => $scheduler) {
            $label = $scheduler['doctor_name'] ?? 'ללא שם';
            if (!empty($scheduler['treatment_type'])) {
                $label .= ' - ' . $scheduler['treatment_type'];
            }
            if (!empty($scheduler['doctor_specialty'])) {
                $label .= ' (' . $scheduler['doctor_specialty'] . ')';
            }
            $options[$id] = $label;
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
     * Get filtered calendars for widget
     * Delegates to: Filter Engine
     */
    public function get_filtered_calendars_for_widget($settings) {
        if (!$this->filter_engine) {
            return [];
        }
        return $this->filter_engine->get_filtered_calendars_for_widget($settings);
    }
    
    /**
     * Get field options for current selection
     * Delegates to: Filter Engine
     */
    public function get_field_options_for_current_selection($settings, $current_selections = []) {
        if (!$this->filter_engine) {
            return [];
        }
        return $this->filter_engine->get_field_options_for_current_selection($settings, $current_selections);
    }
    
    /**
     * Get smart field updates
     * Delegates to: Filter Engine
     */
    public function get_smart_field_updates($settings, $changed_field, $changed_value, $current_selections) {
        if (!$this->filter_engine) {
            return [];
        }
        return $this->filter_engine->get_smart_field_updates($settings, $changed_field, $changed_value, $current_selections);
    }
    
    /**
     * Get appointments data for widget
     * Delegates to: Data Provider
     * Direct API call - no local storage
     */
    public function get_appointments_data($calendar_id = null, $doctor_id = null, $clinic_id = null, $treatment_type = '') {
        if (!$this->data_provider) {
            return null;
        }
        return $this->data_provider->get_appointments_from_api($calendar_id, $doctor_id, $clinic_id, $treatment_type);
    }
    
    // ============================================================================
    // ELEMENTOR CONTROLS - Stays here (tightly coupled with widget)
    // ============================================================================
    
    /**
     * Register Elementor widget controls
     * SIMPLIFIED: Auto-detection based on current post type
     * - Clinics post → Clinic calendar
     * - Doctors post → Doctor calendar
     * - Post ID is detected automatically
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
        
        // Info message - explain auto-detection
        $widget->add_control(
            'auto_detection_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="padding: 10px; background: #e3f2fd; border-radius: 4px; margin-bottom: 15px;">
                    <strong>🔍 זיהוי אוטומטי:</strong><br>
                    • בדף מרפאה → יומן מרפאה<br>
                    • בדף רופא → יומן רופא<br>
                    • המערכת תזהה אוטומטית את סוג הדף והמזהה
                </div>',
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
    
    // ============================================================================
    // AUTO-DETECTION - Automatically detect post type and ID
    // ============================================================================
    
    /**
     * Auto-detect calendar type and post ID based on current post
     * Works in both Elementor editor and frontend
     * 
     * @return array ['selection_mode' => 'doctor'|'clinic', 'post_id' => int, 'post_type' => string]
     */
    private function auto_detect_calendar_settings() {
        $result = [
            'selection_mode' => 'doctor', // default
            'post_id' => null,
            'post_type' => null
        ];
        
        // Try to get current post
        global $post;
        $current_post = $post;
        
        // If not available, try get_the_ID()
        if (!$current_post || !isset($current_post->ID)) {
            $post_id = get_the_ID();
            if ($post_id) {
                $current_post = get_post($post_id);
            }
        }
        
        // In Elementor editor, try to get the post being edited
        if (!$current_post || !isset($current_post->ID)) {
            if (isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
                if ($post_id) {
                    $current_post = get_post($post_id);
                }
            } elseif (isset($_POST['editor_post_id'])) {
                $post_id = intval($_POST['editor_post_id']);
                if ($post_id) {
                    $current_post = get_post($post_id);
                }
            }
        }
        
        // If we have a post, detect the type
        if ($current_post && isset($current_post->ID)) {
            $result['post_id'] = $current_post->ID;
            $result['post_type'] = get_post_type($current_post);
            
            // Determine selection mode based on post type
            if ($result['post_type'] === 'clinics') {
                $result['selection_mode'] = 'clinic';
            } elseif ($result['post_type'] === 'doctors') {
                $result['selection_mode'] = 'doctor';
            }
        }
        
        return $result;
    }
    
    // ============================================================================
    // DYNAMIC TAGS PROCESSING - Stays here (small utility function)
    // ============================================================================
    
    /**
     * Get widget data for rendering - only settings, data will be loaded via API
     * Enhanced with AUTO-DETECTION and comprehensive error handling
     */
    public function get_widget_data($settings) {
        try {
            // AUTO-DETECT calendar type and post ID
            $auto_detected = $this->auto_detect_calendar_settings();
            
            // Return safe defaults if settings are not available
            if (empty($settings)) {
                $settings = [];
            }
            
            // Override settings with auto-detected values
            $settings['selection_mode'] = $auto_detected['selection_mode'];
            
            // Set specific IDs based on auto-detection
            if ($auto_detected['post_id']) {
                if ($auto_detected['selection_mode'] === 'clinic') {
                    $settings['specific_clinic_id'] = $auto_detected['post_id'];
                } elseif ($auto_detected['selection_mode'] === 'doctor') {
                    $settings['specific_doctor_id'] = $auto_detected['post_id'];
                }
            }
            
            // Determine which values to use based on switchers
            $doctor_id = $this->get_effective_doctor_id($settings);
            $clinic_id = $this->get_effective_clinic_id($settings);
            $treatment_type = $this->get_effective_treatment_type($settings);
            
            return [
                'error' => false,
                'settings' => [
                    'selection_mode' => $settings['selection_mode'] ?? 'doctor',
                    'use_specific_treatment' => $settings['use_specific_treatment'] ?? 'no',
                    'effective_doctor_id' => $doctor_id,
                    'effective_clinic_id' => $clinic_id,
                    'effective_treatment_type' => $treatment_type,
                    'auto_detected' => $auto_detected // Include auto-detection info
                ]
            ];
        } catch (Exception $e) {
            // Log error and return safe defaults
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Clinic Queue: Error in get_widget_data - ' . $e->getMessage());
            }
            return [
                'error' => false, // Don't show error to user, just use defaults
                'settings' => [
                    'selection_mode' => 'doctor',
                    'use_specific_treatment' => 'no',
                    'effective_doctor_id' => '1',
                    'effective_clinic_id' => '1',
                    'effective_treatment_type' => ''
                ]
            ];
        } catch (Error $e) {
            // Catch PHP 7+ fatal errors
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
}
