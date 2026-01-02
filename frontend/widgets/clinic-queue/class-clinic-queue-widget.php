<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Widget Controller
require_once __DIR__ . '/class-widget-controller.php';

/**
 * Elementor Clinic Queue Widget
 */
if (class_exists('Elementor\Widget_Base')) {

    class Clinic_Queue_Widget extends \Elementor\Widget_Base
    {

        public function __construct($data = [], $args = null)
        {
            parent::__construct($data, $args);
        }

        /**
         * Get widget name
         */
        public function get_name()
        {
            return 'clinic-queue-widget';
        }

        /**
         * Get widget title
         */
        public function get_title()
        {
            return esc_html__('יומן קביעת תורים', 'clinic-queue-management');
        }

        /**
         * Get widget icon
         */
        public function get_icon()
        {
            return 'eicon-calendar';
        }

        /**
         * Get widget categories
         */
        public function get_categories()
        {
            // Use standard Elementor category 'general' to ensure widget appears
            // You can also use custom categories like 'רפואה כללית' if registered
            return ['general'];
        }

        /**
         * Get widget keywords
         */
        public function get_keywords()
        {
            return [
                // English
                'clinic',
                'queue',
                'appointment',
                'medical',
                'booking',
                'date',
                'time',
                'slot',
                // Hebrew
                'יומן',
                'תור',
                'תורים',
                'קביעת תור',
                'קביעת תורים',
                'מרפאה',
                'רופא',
                'טיפול',
                'הזמנה',
                'תיאום'
            ];
        }

        /**
         * Get style dependencies
         * Return style handles so Elementor loads them in the editor
         */
        public function get_style_depends()
        {
            // Return style handles so they load in editor preview
            return ['clinic-queue-base-css', 'clinic-queue-calendar-css', 'dashicons'];
        }

        /**
         * Get script dependencies
         * IMPORTANT: Return empty array to prevent Elementor from auto-loading assets
         * This prevents conflicts with JetForms when widget is just registered
         * We'll register and enqueue assets manually only in render() when widget is actually displayed
         */
        public function get_script_depends()
        {
            // Return empty array - don't let Elementor auto-load assets
            // This prevents conflicts with JetForms
            return [];
        }

        /**
         * Register and enqueue widget assets (called only when widget is actually rendered)
         * IMPORTANT: This is called only from render(), not from get_style_depends/get_script_depends
         * This prevents conflicts with JetForms and other plugins
         * 
         * In Elementor editor: Don't load Select2 (Elementor/JetForms already have it)
         * In Frontend: Load Select2 only if not already loaded
         */
        private function enqueue_widget_assets()
        {
            static $assets_enqueued = false;

            if ($assets_enqueued) {
                return;
            }
            
            // Check if we're in Elementor editor
            $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
            
            // IMPORTANT: Register styles first so they can be used by get_style_depends()
            
            // Register base.css first for CSS variables (scoped, won't affect other plugins)
            wp_register_style(
                'clinic-queue-base-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
                array(),
                CLINIC_QUEUE_MANAGEMENT_VERSION
            );
            
            // Register appointments calendar CSS (scoped to .appointments-calendar)
            wp_register_style(
                'clinic-queue-calendar-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/appointments-calendar.css',
                array('clinic-queue-base-css'),
                CLINIC_QUEUE_MANAGEMENT_VERSION
            );
            
            // Enqueue styles (will be used in both editor and frontend via get_style_depends)
            wp_enqueue_style('clinic-queue-base-css');
            wp_enqueue_style('clinic-queue-calendar-css');
            wp_enqueue_style('dashicons');
            
            // Handle Select2: 
            // - In Elementor editor: DON'T load Select2 at all (not used, and will conflict with JetForms)
            // - In frontend: Load Select2 only if not already loaded (used for form field selects)
            $select2_handle = 'jquery'; // Default fallback
            
            if (!$is_editor) {
                // In frontend ONLY: Load Select2 only if not already loaded
                // Select2 is used by JavaScript to initialize form field selects
                if (!wp_script_is('select2', 'enqueued') && !wp_script_is('select2-js', 'enqueued')) {
                    // Enqueue Select2 CSS only if not already loaded
                    if (!wp_style_is('select2', 'enqueued')) {
                        wp_enqueue_style(
                            'clinic-queue-select2-css',
                            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.css',
                            array(),
                            '4.1.0'
                        );
                    }
                    
                    // Enqueue Select2 JS with unique handle to avoid conflicts
                    wp_enqueue_script(
                        'clinic-queue-select2-js',
                        CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.js',
                        array('jquery'),
                        '4.1.0',
                        true
                    );
                    $select2_handle = 'clinic-queue-select2-js';
                } else {
                    // Select2 already loaded - use existing handle
                    $select2_handle = wp_script_is('select2', 'enqueued') ? 'select2' : 'select2-js';
                }
            }
            // In editor: Don't load Select2 at all - it's not used and will conflict with JetForms

            // Enqueue Select2 Custom CSS (depends on base.css for CSS variables)
            // IMPORTANT: In editor, DON'T load select.css - it contains overrides for JetFormBuilder
            // that can break JetForms widgets. Only load in frontend.
            if (!$is_editor) {
                wp_enqueue_style(
                    'clinic-queue-select-css',
                    CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/select.css',
                    array('clinic-queue-base-css', 'dashicons'),
                    CLINIC_QUEUE_MANAGEMENT_VERSION
                );
            }

            // Register main widget style handle (required by Elementor)
            // This combines all our CSS files into one handle
            // In editor: Don't include select-css (to avoid JetForms conflicts)
            // In frontend: Include select-css
            $style_dependencies = array('clinic-queue-base-css', 'dashicons');
            if (!$is_editor) {
                $style_dependencies[] = 'clinic-queue-select-css';
            }
            
            wp_enqueue_style(
                'clinic-queue-style',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/appointments-calendar.css',
                $style_dependencies,
                CLINIC_QUEUE_MANAGEMENT_VERSION
            );

            // Enqueue module scripts in correct order
            // IMPORTANT: In editor, DON'T load JavaScript - it can conflict with JetForms
            // The widget preview in editor will show static HTML (from content_template)
            // JavaScript will only run in frontend
            if (!$is_editor) {
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
                        CLINIC_QUEUE_MANAGEMENT_URL . "frontend/widgets/clinic-queue/js/modules/{$module}.js",
                        $module === 'clinic-queue-utils' ? ['jquery'] : ['clinic-queue-utils'],
                        CLINIC_QUEUE_MANAGEMENT_VERSION,
                        true
                    );
                }

                // Enqueue main script that depends on all modules
                // Use the correct Select2 handle (either ours or existing)
                $script_dependencies = ['jquery'];
                if ($select2_handle !== 'jquery') {
                    $script_dependencies[] = $select2_handle;
                }
                $script_dependencies = array_merge($script_dependencies, $module_handles);
                
                wp_enqueue_script(
                    'clinic-queue-script',
                    CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/widgets/clinic-queue/js/clinic-queue.js',
                    $script_dependencies,
                    CLINIC_QUEUE_MANAGEMENT_VERSION,
                    true
                );

                // Don't try to get settings here - it will fail in preview mode
                // Provide empty data to JavaScript to prevent errors
                wp_localize_script('clinic-queue-script', 'clinicQueueData', array(
                    'appointments' => [],
                    'doctors' => [],
                    'clinics' => [],
                    'treatments' => [],
                    'settings' => [],
                    'field_updates' => []
                ));
                
                // Keep AJAX for backward compatibility (but shouldn't be used)
                wp_localize_script('clinic-queue-script', 'clinicQueueAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('clinic_queue_ajax'),
                    'current_user_id' => get_current_user_id()
                ));
            }
            // In editor: JavaScript not loaded - widget shows static preview from content_template()

            $assets_enqueued = true;
        }

        /**
         * Register widget controls
         */
        protected function register_controls()
        {
            $fields_manager = Clinic_Queue_Widget_Controller::get_instance();
            $fields_manager->register_widget_controls($this);
        }

        /**
         * Get widget default width
         */
        protected function get_default_width()
        {
            return 478;
        }


        /**
         * Render widget output on the frontend
         * Enhanced with comprehensive error handling to prevent breaking the page
         */
        protected function render()
        {
            try {
                // Enqueue assets ONLY when widget is actually rendered (not just registered)
                // This prevents conflicts with JetForms and other plugins
                $this->enqueue_widget_assets();
                
                $settings = $this->get_settings_for_display();
                if (!is_array($settings)) {
                    $settings = array();
                }

                // Get widget settings using the fields manager
                $fields_manager = Clinic_Queue_Widget_Controller::get_instance();
                $widget_settings = $fields_manager->get_widget_data($settings);

                if ($widget_settings['error']) {
                    // Show error message
                    echo '<div class="clinic-queue-error" style="padding: 20px; text-align: center; color: #d63384; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">';
                    echo '<h3>שגיאה בטעינת נתונים</h3>';
                    echo '<p>' . esc_html($widget_settings['message']) . '</p>';
                    echo '</div>';
                    return;
                }

                // Update clinicQueueData with actual settings if render is called
                // (It was already initialized with empty data in enqueue_widget_assets)
                
                // Render the appointments calendar component - data will be loaded via API
                $this->render_widget_html($settings, null, $widget_settings['settings']);
            } catch (Exception $e) {
                // Log error and show friendly message
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Clinic Queue Widget: Render error - ' . $e->getMessage());
                }
                echo '<div class="clinic-queue-error" style="padding: 20px; text-align: center; color: #d63384; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">';
                echo '<h3>שגיאה זמנית</h3>';
                echo '<p>אנחנו עובדים על תיקון הבעיה. אנא נסה שוב מאוחר יותר.</p>';
                echo '</div>';
            } catch (Error $e) {
                // Catch PHP 7+ fatal errors
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Clinic Queue Widget: Fatal render error - ' . $e->getMessage());
                }
                echo '<div class="clinic-queue-error" style="padding: 20px; text-align: center; color: #d63384; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">';
                echo '<h3>שגיאה זמנית</h3>';
                echo '<p>אנחנו עובדים על תיקון הבעיה. אנא נסה שוב מאוחר יותר.</p>';
                echo '</div>';
            }
        }

        /**
         * Render the widget HTML - only the basic structure, data will be loaded via API
         */
        private function render_widget_html($settings, $appointments_data, $widget_settings = null)
        {
            // Get fields manager for options
            $fields_manager = Clinic_Queue_Widget_Controller::get_instance();

            // Get options based on selection mode
            $selection_mode = $settings['selection_mode'] ?? 'doctor';
            if ($selection_mode === 'doctor') {
                // Doctor mode: Doctor is SELECTABLE, Clinic is FIXED
                $fixed_clinic_id = $widget_settings['effective_clinic_id'] ?? $settings['specific_clinic_id'] ?? '1';
                $doctors_options = $fields_manager->get_doctors_options();
                $clinics_options = $fields_manager->get_clinics_options($widget_settings['effective_doctor_id'] ?? '1', $widget_settings);
                $treatment_types_options = $fields_manager->get_treatment_types_by_doctor($widget_settings['effective_doctor_id'] ?? '1', $widget_settings);
            } else {
                // Clinic mode: Clinic is SELECTABLE, Doctor is FIXED
                $fixed_doctor_id = $widget_settings['effective_doctor_id'] ?? $settings['specific_doctor_id'] ?? '1';
                $doctors_options = $fields_manager->get_doctors_by_clinic($widget_settings['effective_clinic_id'] ?? '1', $widget_settings);
                $clinics_options = $fields_manager->get_all_clinics_options();
                $treatment_types_options = $fields_manager->get_treatment_types_by_clinic($widget_settings['effective_clinic_id'] ?? '1', $widget_settings);
            }
            $show_doctor_field = ($selection_mode === 'clinic'); // Clinic mode shows doctor selection
            $show_clinic_field = ($selection_mode === 'doctor'); // Doctor mode shows clinic selection
            $show_treatment_field = ($settings['use_specific_treatment'] ?? 'no') !== 'yes';
            ?>
            <div class="appointments-calendar"
                style="max-width: 478px; margin: 0 auto; min-height: 459px; display: flex; flex-direction: column;"
                data-selection-mode="<?php echo esc_attr($selection_mode); ?>"
                data-use-specific-treatment="<?php echo esc_attr($settings['use_specific_treatment'] ?? 'no'); ?>"
                data-specific-clinic-id="<?php echo esc_attr($settings['specific_clinic_id'] ?? ''); ?>"
                data-specific-doctor-id="<?php echo esc_attr($settings['specific_doctor_id'] ?? ''); ?>"
                data-specific-treatment-type="<?php echo esc_attr($settings['specific_treatment_type'] ?? ''); ?>"
>
                <div class="top-section">
                    <!-- Selection Form -->
                    <form class="widget-selection-form" id="clinic-queue-form-<?php echo uniqid(); ?>">
                        <!-- Hidden field for selection mode -->
                        <input type="hidden" name="selection_mode" value="<?php echo esc_attr($selection_mode); ?>">

                        <?php if ($show_treatment_field): ?>
                            <!-- Treatment type is SELECTABLE - ALWAYS FIRST -->
                            <select id="widget-treatment-select" name="treatment_type" class="form-field-select"
                                data-field="treatment_type">
                                <?php foreach ($treatment_types_options as $id => $name): ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($widget_settings['effective_treatment_type'] ?? 'רפואה כללית', $id); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if (($settings['use_specific_treatment'] ?? 'no') === 'yes'): ?>
                            <!-- Treatment type is FIXED (hidden) -->
                            <input type="hidden" name="treatment_type"
                                value="<?php echo esc_attr($settings['specific_treatment_type'] ?? 'רפואה כללית'); ?>">
                        <?php endif; ?>

                        <?php if ($selection_mode === 'doctor'): ?>
                            <!-- Doctor mode: Doctor is FIXED (hidden), Clinic is SELECTABLE -->
                            <input type="hidden" name="doctor_id"
                                value="<?php echo esc_attr($widget_settings['effective_doctor_id'] ?? $settings['specific_doctor_id'] ?? '1'); ?>">
                        <?php endif; ?>

                        <?php if ($selection_mode === 'clinic'): ?>
                            <!-- Clinic mode: Clinic is FIXED (hidden), Doctor is SELECTABLE -->
                            <input type="hidden" name="clinic_id"
                                value="<?php echo esc_attr($widget_settings['effective_clinic_id'] ?? $settings['specific_clinic_id'] ?? '1'); ?>">
                        <?php endif; ?>

                        <?php if ($show_doctor_field): ?>
                            <!-- Doctor is SELECTABLE (in clinic mode) -->
                            <select id="widget-doctor-select" name="doctor_id" class="form-field-select" data-field="doctor_id">
                                <?php foreach ($doctors_options as $id => $name): ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($widget_settings['effective_doctor_id'] ?? '1', $id); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($show_clinic_field): ?>
                            <!-- Clinic is SELECTABLE (in doctor mode) -->
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
                    
                    <!-- Action Buttons will be added by JavaScript -->
                </div>
            </div>
            <?php
        }

        /**
         * Render widget output in the editor (same as frontend for this widget)
         */
        protected function content_template()
        {
            ?>
            <#
            var selectionMode = settings.selection_mode || 'doctor';
            var useSpecificTreatment = settings.use_specific_treatment || 'no';
            var specificClinicId = settings.specific_clinic_id || '';
            var specificDoctorId = settings.specific_doctor_id || '';
            var specificTreatmentType = settings.specific_treatment_type || '';
            var widgetId = 'elementor-preview-' + Math.random().toString(36).substr(2, 9);
            #>
            <div class="appointments-calendar"
                style="max-width: 478px; margin: 0 auto; min-height: 459px; display: flex; flex-direction: column;"
                data-selection-mode="{{{ selectionMode }}}"
                data-use-specific-treatment="{{{ useSpecificTreatment }}}"
                data-specific-clinic-id="{{{ specificClinicId }}}"
                data-specific-doctor-id="{{{ specificDoctorId }}}"
                data-specific-treatment-type="{{{ specificTreatmentType }}}"
                id="clinic-queue-{{{ widgetId }}}">
                
                <div class="top-section">
                    <!-- Selection Form -->
                    <form class="widget-selection-form" id="clinic-queue-form-{{{ widgetId }}}">
                        <!-- Hidden field for selection mode -->
                        <input type="hidden" name="selection_mode" value="{{{ selectionMode }}}">
                        
                        <!-- Loading state for editor preview -->
                        <div class="ap-loading" style="padding: 20px; text-align: center;">
                            <div class="ap-spinner"></div>
                            <p>טוען נתונים...</p>
                        </div>
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
                    
                    <!-- Action Buttons will be added by JavaScript -->
                </div>
            </div>
            <?php
        }
    }

}