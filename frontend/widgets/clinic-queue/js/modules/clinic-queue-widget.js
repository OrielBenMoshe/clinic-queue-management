/**
 * Clinic Queue Management - Widget Module
 * Main widget class and core functionality
 */
(function($) {
    'use strict';

    // Main ClinicQueueWidget class
    class ClinicQueueWidget {
        constructor(element) {
            this.element = $(element);
            this.widgetId = this.element.attr('id');
            this.ctaLabel = this.element.data('cta-label');
            
            // Get selection mode from data attributes
            this.selectionMode = this.element.data('selection-mode') || 'doctor';
            
            // Initialize based on selection mode
            if (this.selectionMode === 'doctor') {
                // Doctor mode: Doctor is SELECTABLE, Clinic is FIXED
                this.doctorId = '1'; // Default doctor ID
                this.clinicId = this.element.data('specific-clinic-id') || '1';
            } else if (this.selectionMode === 'clinic') {
                // Clinic mode: Clinic is FIXED, Scheduler is SELECTABLE
                this.schedulerId = null; // Will be set when user selects a scheduler
                this.clinicId = this.element.data('specific-clinic-id') || '1';
            }
            
            // Initialize treatment type
            this.treatmentType = 'רפואה כללית'; // Default treatment type
            
            // Calculate effective values
            this.effectiveDoctorId = this.calculateEffectiveDoctorId();
            this.effectiveClinicId = this.calculateEffectiveClinicId();
            this.effectiveTreatmentType = this.calculateEffectiveTreatmentType();
            
            // Instance-specific state
            this.selectedDate = null;
            this.selectedTime = null;
            this.selectedClinic = null;

            // Set default selected date to today
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0]; // YYYY-MM-DD format
            this.selectedDate = todayStr;
            this.currentMonth = new Date();
            this.appointmentData = null;
            this.allAppointmentData = null;
            this.isLoading = false;
            
            // Initialize managers
            this.dataManager = new window.ClinicQueueDataManager(this);
            this.uiManager = new window.ClinicQueueUIManager(this);
            
            // Register this instance
            window.ClinicQueueManager.instances.set(this.widgetId, this);
            
            this.init();
        }
        
        init() {
            console.log('[ClinicQueue] Widget initialized with ID:', this.widgetId);
            console.log('[ClinicQueue] Initial configuration:', {
                selectionMode: this.selectionMode,
                effectiveDoctorId: this.effectiveDoctorId,
                effectiveClinicId: this.effectiveClinicId,
                effectiveTreatmentType: this.effectiveTreatmentType
            });
            
            // Set dataManager reference in uiManager
            this.uiManager.dataManager = this.dataManager;
            
            this.bindEvents();
            this.initializeSelect2();
            this.dataManager.loadAllAppointmentData();
        }
        
        /**
         * Initialize Select2 for all form field selects
         */
        initializeSelect2() {
            // Check if Select2 is available
            if (typeof $.fn.select2 === 'undefined') {
                console.warn('[ClinicQueue] Select2 is not loaded, skipping initialization');
                return;
            }

            // Initialize Select2 for all form field selects
            this.element.find('.form-field-select').each((index, element) => {
                const $select = $(element);
                
                // Skip if already initialized
                if ($select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                $select.select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1, // Disable search
                    placeholder: $select.find('option:first').text(),
                    allowClear: false,
                    dropdownParent: this.element
                });
            });
        }

        /**
         * Reinitialize Select2 after dynamic content changes
         */
        reinitializeSelect2() {
            // Destroy existing Select2 instances
            this.element.find('.form-field-select').each((index, element) => {
                const $select = $(element);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
            });

            // Reinitialize
            this.initializeSelect2();
        }

        /**
         * Calculate effective doctor ID based on selection mode
         * In clinic mode, this returns the scheduler ID if selected
         */
        calculateEffectiveDoctorId() {
            if (this.selectionMode === 'doctor') {
                // Doctor mode: Doctor is SELECTABLE, return current selection or default
                return this.doctorId || '1';
            } else {
                // Clinic mode: Scheduler is SELECTABLE, return scheduler ID if selected
                return this.schedulerId || this.element.data('specific-doctor-id') || '1';
            }
        }

        /**
         * Calculate effective clinic ID based on selection mode
         */
        calculateEffectiveClinicId() {
            if (this.selectionMode === 'clinic') {
                // Clinic mode: Clinic is SELECTABLE, return current selection or default
                return this.clinicId || '1';
            } else {
                // Doctor mode: Clinic is FIXED, return specific clinic ID
                return this.element.data('specific-clinic-id') || '1';
            }
        }

        /**
         * Calculate effective treatment type based on settings
         */
        calculateEffectiveTreatmentType() {
            const useSpecificTreatment = this.element.data('use-specific-treatment') === 'yes';
            
            if (useSpecificTreatment) {
                // Use specific treatment type
                return this.element.data('specific-treatment-type') || 'רפואה כללית';
            } else {
                // Use current selection or default
                return this.treatmentType || 'רפואה כללית';
            }
        }

        bindEvents() {
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            
            // Form field selections
            this.element.on(`change${eventNamespace}`, '.form-field-select', (e) => {
                const field = $(e.target).data('field');
                const value = $(e.target).val();
                window.ClinicQueueUtils.log('Form field changed:', { field, value });
                
                // In clinic mode, if scheduler_id field changes, load treatments for that scheduler
                if (this.selectionMode === 'clinic' && field === 'scheduler_id') {
                    this.uiManager.handleSchedulerChange(value);
                }
                
                this.handleFormFieldChange(field, value);
            });
            
            // Form submission (prevent default)
            this.element.on(`submit${eventNamespace}`, '.widget-selection-form', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
            
            // Date selection (day tabs)
            this.element.on(`click${eventNamespace}`, '.day-tab:not(.disabled)', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $target = $(e.currentTarget);
                const date = $target.data('date');
                console.log('[ClinicQueue] Day tab clicked:', date, $target.attr('class'));
                console.log('[ClinicQueue] Target element:', $target);
                if (date) {
                    this.uiManager.selectDate(date);
                }
            });
            
            // View all appointments
            this.element.on(`click${eventNamespace}`, '.ap-view-all-btn', (e) => {
                e.preventDefault();
                this.viewAllAppointments();
            });
        }
        
        async handleFormFieldChange(field, value) {
            console.log(`[ClinicQueue] Field changed: ${field} = ${value}`);
            
            // Get current form data for smart updates
            const form = this.element.find('.widget-selection-form');
            const formData = this.getFormData(form);
            
            // Get widget settings from data attributes
            const widgetSettings = this.getWidgetSettings();
            
            // Update dependent fields using smart filtering
            this.updateFieldsWithSmartFiltering(field, value, formData, widgetSettings);
            
            // Update effective values based on selection mode
            this.updateEffectiveValues(formData);
            
            // Reinitialize Select2 in case the DOM was updated
            this.reinitializeSelect2();
            
            // Show loading state
            this.showLoadingState();
            
            // Reset selections
            this.resetSelections();
            
            // Re-filter and render data with updated parameters
            this.dataManager.filterAndRenderData();
        }
        
        getWidgetSettings() {
            const settings = {
                selection_mode: this.element.data('selection-mode') || 'doctor',
                specific_doctor_id: this.element.data('specific-doctor-id') || '',
                specific_clinic_id: this.element.data('specific-clinic-id') || '',
                use_specific_treatment: this.element.data('use-specific-treatment') || 'no',
                specific_treatment_type: this.element.data('specific-treatment-type') || ''
            };
            
            console.log('[ClinicQueue] Widget settings:', settings);
            return settings;
        }
        
        updateFieldsWithSmartFiltering(changedField, changedValue, currentSelections, widgetSettings) {
            // Use pre-loaded data instead of AJAX call
            if (typeof window.clinicQueueData === 'undefined' || !window.clinicQueueData.field_updates) {
                console.warn('[ClinicQueue] No field updates data available');
                return;
            }
            
            // Get field updates from pre-loaded data
            const fieldUpdates = window.clinicQueueData.field_updates;
            
            // Find updates for the changed field
            const updates = fieldUpdates[changedField] || {};
            
            // Update each affected field
            for (const [fieldName, fieldUpdate] of Object.entries(updates)) {
                this.updateFieldWithOptions(fieldName, fieldUpdate.options, fieldUpdate.selected_value);
            }
        }
        
        updateFieldWithOptions(fieldName, options, selectedValue) {
            const fieldSelect = this.element.find(`select[name="${fieldName}"]`);
            
            if (fieldSelect.length && options) {
                const currentValue = fieldSelect.val();
                
                // Clear current options
                fieldSelect.empty();
                
                // Add new options
                options.forEach(option => {
                    const optionElement = $('<option></option>')
                        .attr('value', option.id)
                        .text(option.name);
                    fieldSelect.append(optionElement);
                });
                
                // Set selected value (smart selection)
                if (selectedValue) {
                    fieldSelect.val(selectedValue);
                } else if (options.length > 0) {
                    // Fallback to first option
                    fieldSelect.val(options[0].id);
                }
                
                // Trigger change event to update Select2
                fieldSelect.trigger('change');
            }
        }
        
        handleFormSubmit() {
            window.ClinicQueueUtils.log('Form submitted');
            const form = this.element.find('.widget-selection-form');
            const formData = this.getFormData(form);
            
            this.updateEffectiveValues(formData);
            this.showLoadingState();
            this.resetSelections();
            this.dataManager.filterAndRenderData();
        }
        
        getFormData(form) {
            const formData = {};
            form.find('input, select').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const value = $field.val();
                if (name && value) {
                    formData[name] = value;
                }
            });
            return formData;
        }
        
        updateEffectiveValues(formData) {
            const selectionMode = formData.selection_mode || this.selectionMode;
            
            if (selectionMode === 'doctor') {
                // Doctor mode: Doctor is SELECTABLE, Clinic is FIXED
                this.doctorId = formData.doctor_id || this.doctorId;
                this.clinicId = formData.clinic_id || this.element.data('specific-clinic-id') || '1';
            } else if (selectionMode === 'clinic') {
                // Clinic mode: Clinic is FIXED, Scheduler is SELECTABLE
                // In clinic mode, we use scheduler_id instead of doctor_id
                this.schedulerId = formData.scheduler_id || this.schedulerId;
                this.clinicId = formData.clinic_id || this.element.data('specific-clinic-id') || this.clinicId || '1';
            }
            
            // Treatment type - either selectable or fixed
            this.treatmentType = formData.treatment_type || this.element.data('specific-treatment-type') || this.treatmentType || 'רפואה כללית';
            
            // Recalculate effective values
            this.effectiveDoctorId = this.calculateEffectiveDoctorId();
            this.effectiveClinicId = this.calculateEffectiveClinicId();
            this.effectiveTreatmentType = this.calculateEffectiveTreatmentType();
        }
        
        showLoadingState() {
            // Show loading message in time-slots-container instead of hiding everything
            this.element.find('.month-and-year').html('טוען...');
            this.element.find('.days-container').html(`
                <div style="text-align: center; padding: 20px;">
                    <div class="spinner" style="width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                </div>
            `);
            this.element.find('.time-slots-container').html(`
                <div style="text-align: center; padding: 40px 20px; color: #6c757d;">
                    <p style="margin: 0; font-size: 16px;">טוען יומן חדש...</p>
                </div>
            `);
        }
        
        resetSelections() {
            this.selectedDate = null;
            this.selectedTime = null;
            
            this.element.find('.day-tab').removeClass('selected');
            this.element.find('.time-slot-badge').removeClass('selected');
            this.element.find('.time-slots-container').hide();
        }
        
        viewAllAppointments() {
            // Currently disabled - no action
            console.log('[ClinicQueue] View all appointments clicked - action disabled');
        }
        
        showContent() {
            this.element.find('.ap-loading').hide();
            this.element.find('.ap-content').show();
        }
        
        showError(message) {
            this.element.find('.ap-loading').html(`
                <div class="ap-error">
                    <p>שגיאה: ${message}</p>
                </div>
            `);
        }
        
        destroy() {
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            this.element.off(eventNamespace);
            window.ClinicQueueManager.instances.delete(this.widgetId);
        }
    }

    // Export to global scope
    window.ClinicQueueWidget = ClinicQueueWidget;

})(jQuery);
