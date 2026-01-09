/**
 * Clinic Queue Management - Core Module
 * Main core class and functionality for booking calendar shortcode
 */
(function($) {
    'use strict';

    // Main BookingCalendarCore class
    class BookingCalendarCore {
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
            
            // Initialize treatment type (will be set when first treatment is selected)
            this.treatmentType = null;
            
            // Calculate current values
            this.currentDoctorId = this.calculateCurrentDoctorId();
            this.currentClinicId = this.calculateCurrentClinicId();
            
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
            
            // Store all schedulers loaded on initial page load (with all meta fields)
            this.allSchedulers = [];
            
            // Initialize managers
            this.dataManager = new window.BookingCalendarDataManager(this);
            this.uiManager = new window.BookingCalendarUIManager(this);
            
            // Register this instance
            window.BookingCalendarManager.instances.set(this.widgetId, this);
            
            this.init();
        }
        
        init() {
            
            // Remove loading placeholder if exists
            this.element.find('.booking-calendar-loading-placeholder').remove();
            
            // Trigger initialized event
            this.element.trigger('booking-calendar-initialized');
            
            // Set dataManager reference in uiManager
            this.uiManager.dataManager = this.dataManager;
            
            this.bindEvents();
            this.initializeSelect2();
            
            // Load all schedulers on initial page load
            this.loadInitialSchedulers();
        }
        
        /**
         * Load all schedulers on initial page load
         * Loads schedulers from PHP (via localized script) or via AJAX if needed
         */
        async loadInitialSchedulers() {
            // Try to get schedulers from localized script data (from PHP)
            if (typeof window.bookingCalendarInitialData !== 'undefined' && 
                window.bookingCalendarInitialData.schedulers) {
                this.allSchedulers = window.bookingCalendarInitialData.schedulers;
                
                // Log detailed information about loaded schedulers
                const schedulersCount = Array.isArray(this.allSchedulers) 
                    ? this.allSchedulers.length 
                    : Object.keys(this.allSchedulers).length;
                
                window.BookingCalendarUtils.log('כל היומנים:', this.allSchedulers);
            } else {
                // Fallback: load via AJAX
                const clinicId = this.element.data('specific-clinic-id');
                const doctorId = this.element.data('specific-doctor-id');
                
                if (clinicId || doctorId) {
                    try {
                        const schedulers = await this.dataManager.loadInitialSchedulers(clinicId, doctorId);
                        this.allSchedulers = schedulers;
                    } catch (error) {
                        window.BookingCalendarUtils.error('Failed to load initial schedulers:', error);
                        this.allSchedulers = [];
                    }
                } else {
                    this.allSchedulers = [];
                }
            }
            
            // Disable scheduler field until treatment is selected
            const schedulerField = this.element.find('.scheduler-field');
            if (schedulerField.length) {
                schedulerField.prop('disabled', true);
            }
            
            // Select first treatment by default and filter schedulers
            setTimeout(() => {
                this.selectFirstTreatmentAndLoadSchedulers();
            }, 100);
        }
        
        /**
         * Select first treatment by default and load schedulers
         */
        async selectFirstTreatmentAndLoadSchedulers() {
            const treatmentField = this.element.find('.treatment-field');
            if (!treatmentField.length) {
                return;
            }
            
            // Get first treatment option (skip placeholder)
            const firstTreatmentOption = treatmentField.find('option:not([value=""])').first();
            if (!firstTreatmentOption.length) {
                return;
            }
            
            const firstTreatmentValue = firstTreatmentOption.val();
            
            // Update treatment type in instance
            this.treatmentType = firstTreatmentValue;
            
            // Set value in the select element
            treatmentField.val(firstTreatmentValue);
            
            // Update Select2 if it's already initialized
            if (treatmentField.hasClass('select2-hidden-accessible')) {
                treatmentField.trigger('change.select2');
            }
            
            // Load schedulers for this treatment directly (without triggering change event)
            await this.handleTreatmentChange(firstTreatmentValue);
        }
        
        /**
         * Handle treatment type change
         * Filters schedulers locally (from pre-loaded data) and populates the scheduler field
         */
        handleTreatmentChange(treatmentType) {
            if (!treatmentType) {
                return;
            }
            
            // Disable scheduler field while filtering
            const schedulerField = this.element.find('.scheduler-field');
            schedulerField.prop('disabled', true);
            
            try {
                // Filter schedulers locally from pre-loaded data
                const filteredSchedulers = this.filterSchedulersByTreatment(treatmentType);
                
                // Populate scheduler field
                this.populateSchedulerField(filteredSchedulers);
                
                // Enable field if schedulers available
                if (filteredSchedulers.length > 0) {
                    schedulerField.prop('disabled', false);
                    
                    // Re-enable Select2 if it was disabled
                    if (schedulerField.hasClass('select2-hidden-accessible')) {
                        schedulerField.select2('enable');
                    }
                    
                    // If only one scheduler found, select it automatically
                    if (filteredSchedulers.length === 1) {
                        const singleScheduler = filteredSchedulers[0];
                        schedulerField.val(singleScheduler.id).trigger('change');
                    }
                } else {
                    schedulerField.prop('disabled', false); // Enable even if no schedulers (to show message)
                    this.showNoSchedulersMessage();
                }
                
            } catch (error) {
                window.BookingCalendarUtils.error('Failed to filter schedulers:', error);
                this.showSchedulerFieldError();
            }
        }
        
        /**
         * Filter schedulers by treatment_type locally
         * Searches in the treatments repeater field of each scheduler
         * 
         * @param {string} treatmentType The treatment type to filter by
         * @return {Array} Array of filtered schedulers
         */
        filterSchedulersByTreatment(treatmentType) {
            if (!this.allSchedulers || (Array.isArray(this.allSchedulers) && this.allSchedulers.length === 0) || 
                (!Array.isArray(this.allSchedulers) && Object.keys(this.allSchedulers).length === 0)) {
                return [];
            }
            
            const filtered = [];
            const normalizedTreatmentType = treatmentType.trim();
            
            // Convert object to array if needed
            const schedulersArray = Array.isArray(this.allSchedulers) 
                ? this.allSchedulers 
                : Object.values(this.allSchedulers);
            
            schedulersArray.forEach((scheduler, index) => {
                // Check if scheduler has treatments repeater
                if (!scheduler.treatments || !Array.isArray(scheduler.treatments)) {
                    return;
                }
                
                // Check if any treatment matches the treatment_type
                const hasTreatment = scheduler.treatments.some((treatment) => {
                    const treatmentTypeValue = treatment.treatment_type ? treatment.treatment_type.trim() : '';
                    return treatmentTypeValue === normalizedTreatmentType || 
                           treatmentTypeValue.toLowerCase() === normalizedTreatmentType.toLowerCase();
                });
                
                if (hasTreatment) {
                    window.BookingCalendarUtils.log(`  → יומן ${index + 1} תואם! מוסיף לרשימה`);
                    filtered.push(scheduler);
                }
            });
            
            return filtered;
        }
        
        /**
         * Populate scheduler field with options
         * Value = scheduler ID, Label = doctor name (from relation) or schedule_name or clinic name (if doctor mode)
         */
        populateSchedulerField(schedulers) {
            const schedulerField = this.element.find('.scheduler-field');
            if (!schedulerField.length) {
                return;
            }
            
            // Clear existing options except placeholder
            schedulerField.find('option:not([value=""])').remove();
            
            // Add new options
            schedulers.forEach((scheduler) => {
                // Determine label based on mode and available data
                let label = '';
                
                if (this.selectionMode === 'doctor') {
                    // Doctor mode: show clinic name (from relation 184)
                    if (scheduler.clinic_name) {
                        label = scheduler.clinic_name;
                    } else if (scheduler.schedule_name) {
                        label = scheduler.schedule_name;
                    } else if (scheduler.manual_calendar_name) {
                        label = scheduler.manual_calendar_name;
                    } else {
                        label = scheduler.title || `יומן #${scheduler.id}`;
                    }
                } else {
                    // Clinic mode: show doctor name (from relation 185) or schedule_name
                    if (scheduler.doctor_name) {
                        label = scheduler.doctor_name;
                    } else if (scheduler.schedule_name) {
                        label = scheduler.schedule_name;
                    } else if (scheduler.manual_calendar_name) {
                        label = scheduler.manual_calendar_name;
                    } else {
                        label = scheduler.title || `יומן #${scheduler.id}`;
                    }
                }
                
                // Get duration from first matching treatment (if available)
                let duration = scheduler.duration || 30;
                if (scheduler.treatments && Array.isArray(scheduler.treatments) && scheduler.treatments.length > 0) {
                    // Try to find duration from treatments
                    const firstTreatment = scheduler.treatments[0];
                    if (firstTreatment.duration) {
                        duration = firstTreatment.duration;
                    }
                }
                
                const option = $('<option>', {
                    value: scheduler.id, // Value = scheduler ID
                    text: label, // Label = doctor/clinic/schedule name
                    'data-proxy-scheduler-id': scheduler.proxy_scheduler_id || '',
                    'data-duration': duration
                });
                schedulerField.append(option);
            });
            
            // Initialize or refresh Select2
            if (schedulerField.hasClass('select2-hidden-accessible')) {
                // Already initialized - refresh it
                schedulerField.select2('destroy');
            }
            // Initialize Select2 if available
            if (typeof $.fn.select2 !== 'undefined') {
                schedulerField.select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1, // Disable search
                    placeholder: schedulerField.find('option[value=""]').text() || 'בחר רופא/מטפל',
                    allowClear: false,
                    dropdownParent: this.element
                });
            }
        }
        
        /**
         * Show no schedulers message
         */
        showNoSchedulersMessage() {
            const schedulerField = this.element.find('.scheduler-field');
            const placeholder = schedulerField.find('option[value=""]');
            if (placeholder.length) {
                placeholder.text('בחר רופא/טיפול');
            }
            
            // Ensure Select2 is initialized even with no options
            if (typeof $.fn.select2 !== 'undefined' && !schedulerField.hasClass('select2-hidden-accessible')) {
                schedulerField.select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1,
                    placeholder: 'בחר רופא/טיפול',
                    allowClear: false,
                    dropdownParent: this.element
                });
            } else if (schedulerField.hasClass('select2-hidden-accessible')) {
                // Refresh Select2 to show updated placeholder
                schedulerField.select2('destroy').select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1,
                    placeholder: 'בחר רופא/טיפול',
                    allowClear: false,
                    dropdownParent: this.element
                });
            }
        }
        
        /**
         * Show error message for scheduler field
         */
        showSchedulerFieldError() {
            const schedulerField = this.element.find('.scheduler-field');
            const placeholder = schedulerField.find('option[value=""]');
            if (placeholder.length) {
                placeholder.text('שגיאה בטעינת יומנים');
            }
            
            // Ensure Select2 is initialized even with error
            if (typeof $.fn.select2 !== 'undefined' && !schedulerField.hasClass('select2-hidden-accessible')) {
                schedulerField.select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1,
                    placeholder: 'שגיאה בטעינת יומנים',
                    allowClear: false,
                    dropdownParent: this.element
                });
            } else if (schedulerField.hasClass('select2-hidden-accessible')) {
                // Refresh Select2 to show updated placeholder
                schedulerField.select2('destroy').select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1,
                    placeholder: 'שגיאה בטעינת יומנים',
                    allowClear: false,
                    dropdownParent: this.element
                });
            }
        }
        
        /**
         * Initialize Select2 for all form field selects
         */
        initializeSelect2() {
            // Check if Select2 is available
            if (typeof $.fn.select2 === 'undefined') {
                console.warn('[BookingCalendar] Select2 is not loaded, skipping initialization');
                return;
            }

            // Initialize Select2 for all form field selects (including disabled ones)
            this.element.find('.form-field-select').each((index, element) => {
                const $select = $(element);
                
                // Skip if already initialized
                if ($select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                // Get placeholder text from first option
                const firstOption = $select.find('option:first');
                const placeholderText = firstOption.length ? firstOption.text() : '';

                $select.select2({
                    theme: 'clinic-queue',
                    dir: 'rtl',
                    language: 'he',
                    width: '100%',
                    minimumResultsForSearch: -1, // Disable search
                    placeholder: placeholderText,
                    allowClear: false,
                    dropdownParent: this.element,
                    disabled: $select.prop('disabled') // Preserve disabled state
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
        calculateCurrentDoctorId() {
            if (this.selectionMode === 'doctor') {
                // Doctor mode: Doctor is SELECTABLE, return current selection or default
                return this.doctorId || '1';
            } else {
                // Clinic mode: Scheduler is SELECTABLE, return scheduler ID if selected
                return this.schedulerId || this.element.data('specific-doctor-id') || '1';
            }
        }

        /**
         * Calculate current clinic ID based on selection mode
         */
        calculateCurrentClinicId() {
            if (this.selectionMode === 'clinic') {
                // Clinic mode: Clinic is SELECTABLE, return current selection or default
                return this.clinicId || '1';
            } else {
                // Doctor mode: Clinic is FIXED, return specific clinic ID
                return this.element.data('specific-clinic-id') || '1';
            }
        }


        bindEvents() {
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            
            // Form field selections
            this.element.on(`change${eventNamespace}`, '.form-field-select', (e) => {
                const field = $(e.target).data('field');
                const value = $(e.target).val();
                
                // If treatment_type field changes, load schedulers
                if (field === 'treatment_type') {
                    this.handleTreatmentChange(value);
                    return; // Don't call handleFormFieldChange for treatment_type
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
            // If scheduler_id field changes, load free slots
            if (field === 'scheduler_id') {
                // Update scheduler ID
                this.schedulerId = value;
                
                // Load free slots from API
                await this.dataManager.loadFreeSlots();
                return;
            }
            
            // Get current form data for smart updates
            const form = this.element.find('.widget-selection-form');
            const formData = this.getFormData(form);
            
            // Get widget settings from data attributes
            const widgetSettings = this.getWidgetSettings();
            
            // Update dependent fields using smart filtering
            this.updateFieldsWithSmartFiltering(field, value, formData, widgetSettings);
            
            // Update effective values based on selection mode
            this.updateCurrentValues(formData);
            
            // Reinitialize Select2 in case the DOM was updated
            this.reinitializeSelect2();
            
            // Show loading state
            this.showLoadingState();
            
            // Reset selections
            this.resetSelections();
            
            // Re-load free slots with updated parameters
            this.dataManager.loadFreeSlots();
        }
        
        getWidgetSettings() {
            const settings = {
                selection_mode: this.element.data('selection-mode') || 'doctor',
                specific_doctor_id: this.element.data('specific-doctor-id') || '',
                specific_clinic_id: this.element.data('specific-clinic-id') || '',
                use_specific_treatment: this.element.data('use-specific-treatment') || 'no',
                specific_treatment_type: this.element.data('specific-treatment-type') || ''
            };
            
            return settings;
        }
        
        updateFieldsWithSmartFiltering(changedField, changedValue, currentSelections, widgetSettings) {
            // Use pre-loaded data instead of AJAX call
            if (typeof window.clinicQueueData === 'undefined' || !window.clinicQueueData.field_updates) {
                console.warn('[BookingCalendar] No field updates data available');
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
            const form = this.element.find('.widget-selection-form');
            const formData = this.getFormData(form);
            
            this.updateCurrentValues(formData);
            this.showLoadingState();
            this.resetSelections();
            this.dataManager.loadFreeSlots();
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
        
        updateCurrentValues(formData) {
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
            this.treatmentType = formData.treatment_type || this.element.data('specific-treatment-type') || this.treatmentType || null;
            
            // Recalculate current values
            this.currentDoctorId = this.calculateCurrentDoctorId();
            this.currentClinicId = this.calculateCurrentClinicId();
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
            window.BookingCalendarManager.instances.delete(this.widgetId);
        }
    }

    // Export to global scope
    window.BookingCalendarCore = BookingCalendarCore;
    // Legacy alias for backward compatibility
    window.BookingCalendarWidget = BookingCalendarCore;

})(jQuery);
