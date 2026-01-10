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
            this.fieldManager = new window.BookingCalendarFieldManager(this);
            
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
            this.fieldManager.initializeSelect2();
            
            // Load all schedulers on initial page load
            this.loadInitialSchedulers();
        }
        
        /**
         * STEP 1: Load all schedulers on initial page load
         * 
         * Flow:
         * 1. Load all schedulers (via PHP localized script or AJAX)
         * 2. Collect all treatment types from schedulers (both clinic and doctor modes)
         * 3. Populate treatment field with collected treatments
         * 4. Select first treatment by default
         * 5. Filter schedulers by selected treatment
         * 6. Populate scheduler field with filtered schedulers (showing doctor/clinic names)
         */
        async loadInitialSchedulers() {
            // STEP 1.1: Load schedulers from PHP (via localized script) or via AJAX
            if (typeof window.bookingCalendarInitialData !== 'undefined' && 
                window.bookingCalendarInitialData.schedulers) {
                this.allSchedulers = window.bookingCalendarInitialData.schedulers;
                
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
            
            // STEP 1.2: Collect all treatment types from schedulers (both clinic and doctor modes)
            // This ensures we only show treatments that are actually available in the loaded schedulers
            this.fieldManager.collectAndPopulateTreatmentTypes(this.allSchedulers);
            
            // STEP 1.3: In doctor mode, populate clinic field from loaded schedulers
            if (this.selectionMode === 'doctor') {
                this.fieldManager.populateClinicFieldFromSchedulers(this.allSchedulers);
            }
            
            // STEP 1.4: Disable scheduler field until treatment is selected
            const schedulerField = this.element.find('.scheduler-field');
            if (schedulerField.length) {
                schedulerField.prop('disabled', true);
            }
            
            // STEP 1.5: Select first treatment by default and filter schedulers
            setTimeout(() => {
                this.selectFirstTreatmentAndLoadSchedulers();
            }, 100);
        }
        
        /**
         * STEP 3: Select first treatment by default and filter schedulers
         * 
         * Flow:
         * 1. Get first treatment option from field
         * 2. Set it as selected
         * 3. Trigger treatment change handler (which filters schedulers and populates scheduler field)
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
         * STEP 4: Handle treatment type change
         * 
         * Flow:
         * 1. Clear scheduler selection (when treatment changes)
         * 2. Filter schedulers by selected treatment type
         * 3. Populate scheduler field with filtered schedulers
         * 4. Show appropriate labels (doctor name in clinic mode, clinic name in doctor mode)
         * 
         * @param {string} treatmentType The selected treatment type
         */
        handleTreatmentChange(treatmentType) {
            if (!treatmentType) {
                return;
            }
            
            // Clear scheduler selection when treatment changes
            const schedulerField = this.element.find('.scheduler-field');
            if (schedulerField.length) {
                schedulerField.val('').trigger('change');
            }
            
            // Disable scheduler field while filtering
            schedulerField.prop('disabled', true);
            
            try {
                // Filter schedulers locally from pre-loaded data
                const filteredSchedulers = this.dataManager.filterSchedulersByTreatment(this.allSchedulers, treatmentType);
                
                // In doctor mode: update clinic field based on filtered schedulers
                if (this.selectionMode === 'doctor') {
                    this.fieldManager.populateClinicFieldFromFilteredSchedulers(filteredSchedulers);
                }
                
                // Populate scheduler field
                this.fieldManager.populateSchedulerField(filteredSchedulers, this.selectionMode);
                
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
                    this.fieldManager.showNoSchedulersMessage();
                }
                
            } catch (error) {
                window.BookingCalendarUtils.error('Failed to filter schedulers:', error);
                this.fieldManager.showSchedulerFieldError();
            }
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
            
            // View all appointments - currently disabled
            // this.element.on(`click${eventNamespace}`, '.ap-view-all-btn', (e) => {
            //     e.preventDefault();
            //     this.viewAllAppointments();
            // });
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
            
            // In doctor mode, clinic_id changes don't require reloading slots
            // The clinic field is just for selection, slots are loaded when scheduler is selected
            if (this.selectionMode === 'doctor' && field === 'clinic_id') {
                // Just update the value, don't reload slots
                this.updateCurrentValues(this.getFormData(this.element.find('.widget-selection-form')));
                return;
            }
            
            // Get current form data for smart updates
            const form = this.element.find('.widget-selection-form');
            const formData = this.getFormData(form);
            
            // Get widget settings from data attributes
            const widgetSettings = this.getWidgetSettings();
            
            // Field updates are now handled client-side in JavaScript
            // (previously: updateFieldsWithSmartFiltering - deprecated)
            
            // Update effective values based on selection mode
            this.updateCurrentValues(formData);
            
            // Reinitialize Select2 in case the DOM was updated
            this.fieldManager.reinitializeSelect2();
            
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
