<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


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
            return ['רפואה כללית'];
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
         */
        public function get_style_depends()
        {
            // Ensure assets are enqueued
            $this->enqueue_widget_assets();
            return ['clinic-queue-style'];
        }

        /**
         * Get script dependencies
         */
        public function get_script_depends()
        {
            // Ensure assets are enqueued
            $this->enqueue_widget_assets();
            return ['clinic-queue-script'];
        }

        /**
         * Enqueue widget assets (called only once per page)
         */
        private function enqueue_widget_assets()
        {
            static $assets_enqueued = false;

            if ($assets_enqueued) {
                return;
            }

            // Enqueue Assistant font first
            wp_enqueue_style(
                'clinic-queue-assistant-font',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/main.css',
                array(),
                CLINIC_QUEUE_MANAGEMENT_VERSION
            );

            // Main CSS file already includes all styles

            // Enqueue Select2 CSS
            wp_enqueue_style(
                'select2-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/js/vendor/select2/select2.min.css',
                array(),
                '4.1.0'
            );

            // Enqueue Dashicons for chevron icons (WordPress built-in)
            wp_enqueue_style('dashicons');

            // Enqueue Select2 Custom CSS
            wp_enqueue_style(
                'select-css',
                CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/select.css',
                array('select2-css', 'dashicons'),
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

            $assets_enqueued = true;
        }

        /**
         * Register widget controls
         */
        protected function register_controls()
        {
            $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
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
         */
        protected function render()
        {
            $settings = $this->get_settings_for_display();
            if (!is_array($settings)) {
                $settings = array();
            }

            // Get widget settings using the fields manager
            $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
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
        }

        /**
         * Render the widget HTML - only the basic structure, data will be loaded via API
         */
        private function render_widget_html($settings, $appointments_data, $widget_settings = null)
        {
            // Get fields manager for options
            $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();

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
            <# var doctorId=settings.doctor_id || '1' ; var clinicId=settings.clinic_id || '' ; var widgetId='elementor-preview-' + Math.random().toString(36).substr(2, 9); #>
                <div id="clinic-queue-{{{ widgetId }}}" class="ap-widget <?php echo is_rtl() ? 'ap-rtl' : 'ap-ltr'; ?>"
                    data-doctor-id="{{{ doctorId }}}" data-clinic-id="{{{ clinicId }}}"
                    style="max-width: 478px; margin: 0 auto;">

                    <!-- Loading state for editor preview -->
                    <div class="ap-loading">
                        <div class="ap-spinner"></div>
                        <p>טוען נתונים...</p>
                    </div>

                </div>
                <?php
        }
    }

}