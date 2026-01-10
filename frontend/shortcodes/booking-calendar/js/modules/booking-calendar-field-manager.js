/**
 * Clinic Queue Management - Field Manager Module
 * Handles all form field population and Select2 management
 */
(function($) {
    'use strict';

    // Field Manager - handles all form field operations
    class FieldManager {
        constructor(core) {
            this.core = core;
        }

        /**
         * Populate clinic field from loaded schedulers (doctor mode only)
         * 
         * In doctor mode, the clinic field should only show clinics that have schedulers
         * associated with the current doctor. This function collects unique clinics from
         * the loaded schedulers and populates the clinic field.
         * 
         * @param {Array|Object} allSchedulers All schedulers loaded on page load
         */
        populateClinicFieldFromSchedulers(allSchedulers) {
            this.populateClinicFieldFromFilteredSchedulers(allSchedulers);
        }
        
        /**
         * Populate clinic field from filtered schedulers (doctor mode only)
         * 
         * This function is called both on initial load and after treatment filtering
         * to ensure the clinic field always shows only clinics from the current schedulers.
         * 
         * @param {Array|Object} schedulers Schedulers to extract clinics from (can be array or object)
         */
        populateClinicFieldFromFilteredSchedulers(schedulers) {
            if (!schedulers || 
                (Array.isArray(schedulers) && schedulers.length === 0) || 
                (!Array.isArray(schedulers) && Object.keys(schedulers).length === 0)) {
                return;
            }
            
            // Convert to array if needed
            const schedulersArray = Array.isArray(schedulers) 
                ? schedulers 
                : Object.values(schedulers);
            
            // Collect unique clinics (by clinic_name)
            // Use Map to ensure uniqueness and keep track of proxy_schedule_id and scheduler.id
            const clinicsMap = new Map();
            
            schedulersArray.forEach((scheduler) => {
                if (scheduler.clinic_name && scheduler.clinic_name.trim()) {
                    const clinicName = scheduler.clinic_name.trim();
                    // Use proxy_schedule_id as value, fallback to scheduler.id if proxy_schedule_id is empty
                    const proxyScheduleId = scheduler.proxy_schedule_id || scheduler.proxy_scheduler_id || '';
                    const value = proxyScheduleId || scheduler.id;
                    
                    // If clinic not in map yet, add it
                    // If already exists, prefer the one with proxy_schedule_id
                    if (!clinicsMap.has(clinicName)) {
                        clinicsMap.set(clinicName, {
                            clinicName: clinicName,
                            value: value,
                            proxyScheduleId: proxyScheduleId
                        });
                    } else {
                        // If current has proxy_schedule_id and existing doesn't, replace it
                        const existing = clinicsMap.get(clinicName);
                        if (proxyScheduleId && !existing.proxyScheduleId) {
                            clinicsMap.set(clinicName, {
                                clinicName: clinicName,
                                value: value,
                                proxyScheduleId: proxyScheduleId
                            });
                        }
                    }
                }
            });
            
            // Convert Map to sorted array
            const clinics = Array.from(clinicsMap.values()).sort((a, b) => {
                return a.clinicName.localeCompare(b.clinicName);
            });
            
            // Populate clinic field
            const clinicField = this.core.element.find('select[data-field="clinic_id"]');
            if (!clinicField.length) {
                return;
            }
            
            // Clear existing options except placeholder
            clinicField.find('option:not([value=""])').remove();
            
            // Add collected clinics
            clinics.forEach((clinic) => {
                const option = $('<option>', {
                    value: clinic.value, // Value = proxy_schedule_id (or scheduler.id if proxy_schedule_id is empty)
                    text: clinic.clinicName // Label = clinic name
                });
                clinicField.append(option);
            });
            
            // Refresh Select2 if initialized
            if (clinicField.hasClass('select2-hidden-accessible')) {
                clinicField.select2('destroy');
            }
            this.initializeSelect2ForField(clinicField, 'בחר מרפאה');
            
            // If only one clinic found, select it automatically
            // Use silent change to avoid triggering handleFormFieldChange unnecessarily
            if (clinics.length === 1) {
                clinicField.val(clinics[0].value);
                // Trigger change silently (without bubbling) to update Select2 only
                clinicField.trigger('change.select2');
            }
        }

        /**
         * Collect all treatment types from loaded schedulers and populate treatment field
         * 
         * Collects unique treatment types from all schedulers and populates the treatment field.
         * Works for both clinic and doctor modes - ensures consistency.
         * 
         * @param {Array|Object} allSchedulers All schedulers loaded on page load
         */
        collectAndPopulateTreatmentTypes(allSchedulers) {
            if (!allSchedulers || 
                (Array.isArray(allSchedulers) && allSchedulers.length === 0) || 
                (!Array.isArray(allSchedulers) && Object.keys(allSchedulers).length === 0)) {
                return;
            }
            
            // Convert to array if needed
            const schedulersArray = Array.isArray(allSchedulers) 
                ? allSchedulers 
                : Object.values(allSchedulers);
            
            // Collect all unique treatment types
            const treatmentTypesSet = new Set();
            
            schedulersArray.forEach((scheduler) => {
                if (scheduler.treatments && Array.isArray(scheduler.treatments)) {
                    scheduler.treatments.forEach((treatment) => {
                        if (treatment.treatment_type && treatment.treatment_type.trim()) {
                            treatmentTypesSet.add(treatment.treatment_type.trim());
                        }
                    });
                }
            });
            
            // Convert Set to sorted array
            const treatmentTypes = Array.from(treatmentTypesSet).sort();
            
            // Populate treatment field
            const treatmentField = this.core.element.find('.treatment-field');
            if (!treatmentField.length) {
                return;
            }
            
            // Clear existing options except placeholder
            treatmentField.find('option:not([value=""])').remove();
            
            // Add collected treatment types
            treatmentTypes.forEach((treatmentType) => {
                const option = $('<option>', {
                    value: treatmentType,
                    text: treatmentType
                });
                treatmentField.append(option);
            });
            
            // Refresh Select2 if initialized
            if (treatmentField.hasClass('select2-hidden-accessible')) {
                treatmentField.select2('destroy');
            }
            this.initializeSelect2ForField(treatmentField, 'בחר סוג טיפול');
        }

        /**
         * Populate scheduler field with filtered schedulers
         * 
         * Flow:
         * 1. Clear existing options
         * 2. For each filtered scheduler:
         *    - Clinic mode: Show doctor name (from Relation 185: Scheduler → Doctor)
         *    - Doctor mode: Show clinic name (from Relation 184: Clinic → Scheduler)
         *    - Fallback: schedule_name, manual_calendar_name, or title
         * 3. Value = scheduler ID (for form submission)
         * 4. Initialize/refresh Select2
         * 
         * @param {Array} schedulers Array of filtered schedulers to populate
         * @param {string} selectionMode The selection mode ('doctor' or 'clinic')
         */
        populateSchedulerField(schedulers, selectionMode) {
            const schedulerField = this.core.element.find('.scheduler-field');
            if (!schedulerField.length) {
                return;
            }
            
            // Clear existing options except placeholder
            schedulerField.find('option:not([value=""])').remove();
            
            // Add new options
            schedulers.forEach((scheduler) => {
                // Determine label based on mode and available data
                let label = '';
                
                if (selectionMode === 'doctor') {
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
                    'data-proxy-scheduler-id': scheduler.proxy_schedule_id || '',
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
            const schedulerPlaceholder = schedulerField.find('option[value=""]').text() || 'בחר רופא/מטפל';
            this.initializeSelect2ForField(schedulerField, schedulerPlaceholder);
        }

        /**
         * Show no schedulers message
         */
        showNoSchedulersMessage() {
            const schedulerField = this.core.element.find('.scheduler-field');
            const placeholder = schedulerField.find('option[value=""]');
            if (placeholder.length) {
                placeholder.text('בחר רופא/טיפול');
            }
            
            // Ensure Select2 is initialized even with no options
            if (schedulerField.hasClass('select2-hidden-accessible')) {
                // Refresh Select2 to show updated placeholder
                schedulerField.select2('destroy');
            }
            this.initializeSelect2ForField(schedulerField, 'בחר רופא/טיפול');
        }
        
        /**
         * Show error message for scheduler field
         */
        showSchedulerFieldError() {
            const schedulerField = this.core.element.find('.scheduler-field');
            const placeholder = schedulerField.find('option[value=""]');
            if (placeholder.length) {
                placeholder.text('שגיאה בטעינת יומנים');
            }
            
            // Ensure Select2 is initialized even with error
            if (schedulerField.hasClass('select2-hidden-accessible')) {
                // Refresh Select2 to show updated placeholder
                schedulerField.select2('destroy');
            }
            this.initializeSelect2ForField(schedulerField, 'שגיאה בטעינת יומנים');
        }

        /**
         * Initialize Select2 for a single select element
         * Helper function to avoid code duplication
         * 
         * @param {jQuery} $select - The select element to initialize
         * @param {string} placeholder - Optional placeholder text (if not provided, uses first option text)
         */
        initializeSelect2ForField($select, placeholder = null) {
            // Check if Select2 is available
            if (typeof $.fn.select2 === 'undefined') {
                if (window.BookingCalendarUtils) {
                    window.BookingCalendarUtils.log('Select2 is not loaded, skipping initialization');
                }
                return false;
            }
            
            // Skip if already initialized
            if ($select.hasClass('select2-hidden-accessible')) {
                return false;
            }
            
            // Get placeholder text
            let placeholderText = placeholder;
            if (!placeholderText) {
                const firstOption = $select.find('option:first');
                placeholderText = firstOption.length ? firstOption.text() : '';
            }
            
            // Initialize Select2
            $select.select2({
                theme: 'clinic-queue',
                dir: 'rtl',
                language: 'he',
                width: '100%',
                minimumResultsForSearch: -1, // Disable search
                placeholder: placeholderText,
                allowClear: false,
                dropdownParent: this.core.element,
                disabled: $select.prop('disabled') // Preserve disabled state
            });
            
            return true;
        }

        /**
         * Initialize Select2 for all form field selects
         */
        initializeSelect2() {
            // Initialize Select2 for all form field selects (including disabled ones)
            this.core.element.find('.form-field-select').each((index, element) => {
                this.initializeSelect2ForField($(element));
            });
        }

        /**
         * Reinitialize Select2 after dynamic content changes
         */
        reinitializeSelect2() {
            // Destroy existing Select2 instances
            this.core.element.find('.form-field-select').each((index, element) => {
                const $select = $(element);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
            });

            // Reinitialize
            this.initializeSelect2();
        }

    }

    // Export to global scope
    window.BookingCalendarFieldManager = FieldManager;

})(jQuery);
