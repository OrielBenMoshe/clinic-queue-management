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
                // Doctor mode: doctor is fixed from page context; clinic is chosen per scheduler
                this.doctorId = this.element.data('specific-doctor-id') || '1';
                this.clinicId = this.element.data('specific-clinic-id') || '1';
            } else if (this.selectionMode === 'clinic') {
                // Clinic mode: Clinic is FIXED, Scheduler is SELECTABLE
                this.schedulerId = null; // Will be set when user selects a scheduler
                this.clinicId = this.element.data('specific-clinic-id') || '1';
            }
            
            // Initialize treatment type (will be set when first treatment is selected)
            this.treatmentType = null;
            /** @type {boolean} User picked a treatment before auto-select ran */
            this._userChangedTreatment = false;
            
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

            // שומר על כפתור "חזור" במובייל: לחיצה על "חזור" כשהפאנל פתוח תסגור אותו במקום לנווט אחורה.
            this._mobileBackGuard = window.BookingCalendarUtils.createMobileBackGuard({
                onBack: () => this.closeMobilePanel(),
                label: `mobile-panel-${this.widgetId}`,
            });
            
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

            // אתחול תצוגת הכרטיס הקומפקטי למובייל (אם המודול נטען)
            if (typeof window.BookingCalendarMobileCompact === 'function') {
                this.mobileCompact = new window.BookingCalendarMobileCompact(this);
            }
        }

        /**
         * מסנכרן את שדות הבחירה בכרטיס המובייל מהשדות הראשיים (מוסתרים ב-top-section).
         * נקרא לאחר מילוי אפשרויות ב-field manager ולא רק אחרי רינדור יומן.
         */
        syncMobileCompactSelects() {
            if (!this.mobileCompact || typeof this.mobileCompact.populateSelects !== 'function') {
                return;
            }

            const runSync = () => {
                this.mobileCompact.populateSelects();
            };

            // דחייה קצרה כדי ש-Select2 יסיים לעדכן את ה-select המקורי אחרי auto-select
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(runSync);
            } else {
                setTimeout(runSync, 0);
            }
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
            // STEP 1.1: Load schedulers from PHP per-widget payload or via AJAX fallback
            const instanceInitialData = (
                typeof window.bookingCalendarInitialDataByWidget !== 'undefined' &&
                window.bookingCalendarInitialDataByWidget &&
                window.bookingCalendarInitialDataByWidget[this.widgetId]
            ) ? window.bookingCalendarInitialDataByWidget[this.widgetId] : null;

            if (instanceInitialData && Array.isArray(instanceInitialData.schedulers)) {
                this.allSchedulers = instanceInitialData.schedulers;
                window.BookingCalendarUtils.log('כל היומנים (per widget):', this.allSchedulers);
            } else if (typeof window.bookingCalendarInitialData !== 'undefined' &&
                window.bookingCalendarInitialData.schedulers) {
                // Backward compatibility for legacy single-instance pages
                this.allSchedulers = window.bookingCalendarInitialData.schedulers;
                window.BookingCalendarUtils.log('כל היומנים (legacy):', this.allSchedulers);
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
            
            // STEP 1.3: Disable scheduler field until treatment is selected
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
            if (this._userChangedTreatment) {
                return;
            }

            const treatmentField = this.element.find('.treatment-field');
            if (!treatmentField.length) {
                return;
            }

            const existingValue = treatmentField.val();
            if (existingValue && String(existingValue).trim() !== '') {
                this.treatmentType = String(existingValue).trim();
                await this.handleTreatmentChange(this.treatmentType, { isAutoSelect: true });
                return;
            }
            
            // Get first treatment option (skip placeholder)
            const firstTreatmentOption = treatmentField.find('option:not([value=""])').first();
            if (!firstTreatmentOption.length) {
                return;
            }
            
            const firstTreatmentValue = firstTreatmentOption.val();
            
            this.treatmentType = firstTreatmentValue;
            treatmentField.val(firstTreatmentValue);
            
            if (treatmentField.hasClass('select2-hidden-accessible')) {
                treatmentField.trigger('change.select2');
            }
            
            await this.handleTreatmentChange(firstTreatmentValue, { isAutoSelect: true });
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
         * @param {{ isAutoSelect?: boolean }} [options]
         */
        handleTreatmentChange(treatmentType, options = {}) {
            if (!treatmentType) {
                return;
            }

            this.treatmentType = treatmentType;
            if (!options.isAutoSelect) {
                this._userChangedTreatment = true;
            }
            // Show loading state in time-slots-container
            this.showLoadingState();
            
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
                    // In clinic mode, we have a scheduler field - enable it
                    if (schedulerField.length) {
                        schedulerField.prop('disabled', false);
                        
                        // Re-enable Select2 if it was disabled
                        if (schedulerField.hasClass('select2-hidden-accessible')) {
                            schedulerField.select2('enable');
                        }
                        
                        // Auto-select the first available scheduler (first doctor/therapist),
                        // whether there is one option or many. Triggering 'change' updates
                        // Select2 and runs handleFormFieldChange() which loads the free slots.
                        const firstScheduler = filteredSchedulers[0];
                        schedulerField.val(firstScheduler.id);
                        if (schedulerField.hasClass('select2-hidden-accessible')) {
                            schedulerField.trigger('change.select2');
                        }
                        schedulerField.trigger('change');
                        this.syncMobileCompactSelects();
                    } else {
                        // Doctor mode: no scheduler field exists, load slots directly
                        window.BookingCalendarUtils.log('Doctor mode: no scheduler field, filteredSchedulers:', filteredSchedulers);
                        if (filteredSchedulers.length === 1) {
                            // Single scheduler: store it and load slots directly
                            const singleScheduler = filteredSchedulers[0];
                            this.schedulerId = singleScheduler.id;
                            window.BookingCalendarUtils.log('Doctor mode: single scheduler found, ID:', singleScheduler.id, 'proxy_schedule_id:', singleScheduler.proxy_schedule_id);
                            // Load free slots directly (no field to select)
                            this.dataManager.loadFreeSlots();
                        } else {
                            // Multiple schedulers: load free slots for all of them
                            window.BookingCalendarUtils.log('Doctor mode: multiple schedulers found, count:', filteredSchedulers.length);
                            this.dataManager.loadFreeSlotsForMultipleSchedulers(filteredSchedulers, treatmentType);
                        }
                    }
                } else {
                    if (schedulerField.length) {
                        schedulerField.prop('disabled', false); // Enable even if no schedulers (to show message)
                        this.fieldManager.showNoSchedulersMessage();
                    }
                }
                
            } catch (error) {
                window.BookingCalendarUtils.error('Failed to filter schedulers:', error);
                this.fieldManager.showSchedulerFieldError();
            } finally {
                this.syncMobileCompactSelects();
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
                
                if (field === 'treatment_type') {
                    this.handleTreatmentChange(value);
                    return;
                }
                
                this.handleFormFieldChange(field, value);
            });

            // Select2 fires select2:select — ensure instance tracks the latest treatment id
            this.element.on(`select2:select${eventNamespace}`, '.treatment-field', (e) => {
                const value = $(e.target).val();
                if (value) {
                    this.treatmentType = String(value);
                    this._userChangedTreatment = true;
                }
            });
            
            // Form submission (prevent default)
            this.element.on(`submit${eventNamespace}`, '.widget-selection-form', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
            
            // Date selection (day tabs)
            this.element.on(`click${eventNamespace}`, '.day-tab:not(.disabled)', (e) => {
                if (this._suppressDayTabClicksUntil && Date.now() < this._suppressDayTabClicksUntil) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                const $target = $(e.currentTarget);
                const date = $target.attr('data-date');
                if (date) {
                    this.uiManager.selectDate(date);
                }
            });
            
            // View all appointments – open expanded modal (כל התורים).
            this.element.on(`click${eventNamespace}`, '.ap-view-all-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.openExpandedModal();
            });


            this.bindMobileDragToClose(eventNamespace);

            // ESC לסגירת הפנל במובייל (רק כשהוא פתוח)
            $(document).on(`keydown${eventNamespace}`, (e) => {
                if (e.key === 'Escape' && this.element.hasClass('is-mobile-open')) {
                    this.closeMobilePanel();
                }
            });

            // Book appointment button click
            this.element.on(`click${eventNamespace}`, '.ap-book-btn:not(.disabled)', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleBookButtonClick();
            });
        }
        
        async handleFormFieldChange(field, value) {
            // If scheduler_id field changes, load free slots
            if (field === 'scheduler_id') {
                // Show loading state before loading slots
                this.showLoadingState();
                
                // Update scheduler ID
                this.schedulerId = value;
                
                // Load free slots from API
                await this.dataManager.loadFreeSlots();
                return;
            }
            
            if (this.selectionMode === 'doctor' && field === 'clinic_id') {
                this.updateCurrentValues(this.getFormData(this.element.find('.widget-selection-form')));

                const schedulersArray = Array.isArray(this.allSchedulers)
                    ? this.allSchedulers
                    : Object.values(this.allSchedulers || {});

                const scheduler = schedulersArray.find((s) => {
                    const proxyId = String(s.proxy_schedule_id || s.proxy_scheduler_id || '');
                    const id = String(s.id || '');
                    const selected = String(value || '');
                    return (proxyId && proxyId === selected) || (id && id === selected);
                });

                if (scheduler) {
                    this.schedulerId = scheduler.id;
                    this.showLoadingState();
                    await this.dataManager.loadFreeSlots();
                }
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

        /**
         * Reads treatment type id from the live select (Select2-compatible).
         *
         * @returns {string}
         */
        readTreatmentTypeFromField() {
            const $field = this.element.find('.treatment-field');
            if (!$field.length) {
                return '';
            }

            const value = $field.val();
            if (value === null || value === undefined || String(value).trim() === '') {
                return '';
            }

            return String(value).trim();
        }

        /**
         * Syncs booking navigation params from the form DOM before building the URL.
         */
        syncBookingStateFromForm() {
            const fromField = this.readTreatmentTypeFromField();
            if (fromField) {
                this.treatmentType = fromField;
            }

            const form = this.element.find('.widget-selection-form');
            if (!form.length) {
                return;
            }

            const formData = this.getFormData(form);

            if (formData.scheduler_id) {
                this.schedulerId = formData.scheduler_id;
            }

            if (this.selectionMode === 'doctor' && formData.clinic_id) {
                this.clinicId = formData.clinic_id;

                const schedulersArray = Array.isArray(this.allSchedulers)
                    ? this.allSchedulers
                    : Object.values(this.allSchedulers || {});

                const selected = String(formData.clinic_id);
                const scheduler = schedulersArray.find((s) => {
                    const proxyId = String(s.proxy_schedule_id || s.proxy_scheduler_id || '');
                    const id = String(s.id || '');
                    return (proxyId && proxyId === selected) || (id && id === selected);
                });

                if (scheduler) {
                    this.schedulerId = scheduler.id;
                }
            }

            this.currentDoctorId = this.calculateCurrentDoctorId();
            this.currentClinicId = this.calculateCurrentClinicId();
        }
        
        updateCurrentValues(formData) {
            const selectionMode = formData.selection_mode || this.selectionMode;
            
            if (selectionMode === 'doctor') {
                this.doctorId = formData.doctor_id || this.doctorId;
                this.clinicId = formData.clinic_id || this.element.data('specific-clinic-id') || '1';
            } else if (selectionMode === 'clinic') {
                this.schedulerId = formData.scheduler_id || this.schedulerId;
                this.clinicId = formData.clinic_id || this.element.data('specific-clinic-id') || this.clinicId || '1';
            }
            
            const fromField = this.readTreatmentTypeFromField();
            this.treatmentType = fromField
                || formData.treatment_type
                || this.element.data('specific-treatment-type')
                || this.treatmentType
                || null;
            
            this.currentDoctorId = this.calculateCurrentDoctorId();
            this.currentClinicId = this.calculateCurrentClinicId();
        }
        
        showLoadingState() {
            // Show loading spinner in time-slots-container only
            const timeSlotsContainer = this.element.find('.time-slots-container');
            timeSlotsContainer.html(`
                <div class="booking-calendar-loader">
                    <div class="booking-calendar-loader__spinner" aria-hidden="true"></div>
                    <p class="booking-calendar-loader__text">טוען תורים זמינים...</p>
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
        
        /**
         * פותח את המודל המורחב "כל התורים" עם הסטייט הנוכחי
         */
        openExpandedModal() {
            if (typeof window.BookingCalendarExpandedModal !== 'function') {
                window.BookingCalendarUtils.error('BookingCalendarExpandedModal not loaded');
                return;
            }
            const modal = new window.BookingCalendarExpandedModal(this);
            modal.open();
        }

        /**
         * במובייל / טאבלט במצב מאונך, פותח את ה-widget כ-fullscreen panel
         * (118px מתחת לשולי המסך העליון, עד התחתית). העיצוב כולו ב-CSS;
         * JS רק מסמן סטייט + נועל גלילה של ה-body.
         *
         * @param {string|null} date תאריך לאוטו-סלקציה (YYYY-MM-DD), או null לא לבחור.
         *                          כשנלחץ קלף יום בכרטיס הקומפקטי, date מועבר
         *                          ואותו יום נבחר ומוגלל אוטומטית בתוך הפנל.
         */
        openMobilePanel(date = null) {
            if (this.element.hasClass('is-mobile-open')) {
                return;
            }
            this.element.removeClass('is-mobile-dragging').css({
                transform: '',
                transition: ''
            });
            this.element.addClass('is-mobile-open');
            window.BookingCalendarUtils.lockBodyScroll();
            this._mobileBackGuard.push();
            $(window).trigger('resize.booking-calendar-width');

            if (date && this.uiManager && typeof this.uiManager.selectDate === 'function') {
                // מניעת click/ghost-click על טאב יום מיד אחרי פתיחה (היה מבטל בחירה ב-toggle)
                this._suppressDayTabClicksUntil = Date.now() + 450;
                const dateKey = window.BookingCalendarUtils.normalizeDateKey(date);
                // בחירה אוטומטית של היום שנלחץ; forceSelect – בלי toggle-off אם היום כבר נבחר בטעינה
                setTimeout(() => {
                    this.uiManager.selectDate(dateKey, { forceSelect: true });
                }, 60);
            } else if (this.uiManager && typeof this.uiManager.scrollToActiveDateTab === 'function') {
                // גלילה ליום הפעיל הנוכחי
                this.uiManager.scrollToActiveDateTab();
            }

            window.BookingCalendarUtils.log(
                'Mobile fullscreen panel opened:',
                this.widgetId,
                date ? `→ date: ${date}` : ''
            );
        }

        /**
         * סוגר את ה-fullscreen panel של המובייל וחוזר למצב CTA דביק.
         */
        closeMobilePanel() {
            if (!this.element.hasClass('is-mobile-open')) {
                return;
            }
            this.element.removeClass('is-mobile-dragging').css({
                transform: '',
                transition: ''
            });
            this.element.removeClass('is-mobile-open');
            window.BookingCalendarUtils.unlockBodyScroll();
            // ניקוי רשומת ההיסטוריה שנדחפה (no-op אם הסגירה הגיעה מלחיצת "חזור").
            this._mobileBackGuard.release();
            window.BookingCalendarUtils.log(
                'Mobile fullscreen panel closed:',
                this.widgetId
            );
        }

        /**
         * סגירה במובייל באמצעות גרירה למטה.
         * מתחיל רק מהידית העליונה כדי לא לפגוע בגלילה פנימית של התוכן.
         */
        bindMobileDragToClose(eventNamespace) {
            let startY = 0;
            let currentDrag = 0;
            let isDragging = false;
            const closeThreshold = 110;

            this.element.on(
                `touchstart${eventNamespace}`,
                '.booking-calendar-mobile-drag-handle',
                (e) => {
                    if (!this.element.hasClass('is-mobile-open')) {
                        return;
                    }
                    const touch = e.originalEvent.touches && e.originalEvent.touches[0];
                    if (!touch) {
                        return;
                    }
                    startY = touch.clientY;
                    currentDrag = 0;
                    isDragging = true;
                    this.element.addClass('is-mobile-dragging').css('transition', 'none');
                }
            );

            $(document).on(`touchmove${eventNamespace}`, (e) => {
                if (!isDragging) {
                    return;
                }
                const touch = e.originalEvent.touches && e.originalEvent.touches[0];
                if (!touch) {
                    return;
                }
                const deltaY = Math.max(0, touch.clientY - startY);
                currentDrag = deltaY;
                this.element.css('transform', `translateY(${deltaY}px)`);

                if (deltaY > 0 && e.cancelable) {
                    e.preventDefault();
                }
            });

            const endDrag = () => {
                if (!isDragging) {
                    return;
                }
                isDragging = false;
                this.element.removeClass('is-mobile-dragging');

                if (currentDrag >= closeThreshold) {
                    this.closeMobilePanel();
                    return;
                }

                this.element
                    .css('transition', 'transform 180ms ease-out')
                    .css('transform', 'translateY(0)');
                setTimeout(() => {
                    if (this.element.hasClass('is-mobile-open')) {
                        this.element.css({
                            transform: '',
                            transition: ''
                        });
                    }
                }, 220);
            };

            $(document).on(`touchend${eventNamespace}`, endDrag);
            $(document).on(`touchcancel${eventNamespace}`, endDrag);
        }

        /**
         * מטפל בלחיצה על כפתור "הזמן תור"
         * אוסף את כל הפרמטרים הדרושים ומעביר לעמוד טופס הזמנת התור
         */
        handleBookButtonClick() {
            this.syncBookingStateFromForm();

            const params = this.collectBookingParameters();
            if (!params) {
                return; // שגיאה כבר הוצגה ב-collectBookingParameters
            }
            
            // בניית URL - שימוש בעמוד דינמי מ-localized data
            let bookingPageId = 4366; // Fallback למזהה ברירת מחדל
            if (window.bookingCalendarData && window.bookingCalendarData.bookingPageId) {
                bookingPageId = window.bookingCalendarData.bookingPageId;
            }
            
            const url = this.buildBookingUrl(bookingPageId, params);
            
            window.BookingCalendarUtils.log('מעבר לעמוד הזמנת תור:', url);
            
            // מעבר לעמוד
            window.location.href = url;
        }
        
        /**
         * אוסף את כל הפרמטרים הדרושים להזמנת תור
         * @returns {Object|null} אובייקט עם כל הפרמטרים, או null אם חסרים פרמטרים חובה
         */
        collectBookingParameters() {
            this.syncBookingStateFromForm();

            if (!this.selectedDate || !this.selectedTime) {
                window.BookingCalendarUtils.error('נא לבחור תאריך ושעה');
                return null;
            }
            
            const treatmentTypeId = this.readTreatmentTypeFromField() || this.treatmentType;
            if (!treatmentTypeId) {
                window.BookingCalendarUtils.error('נא לבחור סוג טיפול');
                return null;
            }

            this.treatmentType = treatmentTypeId;
            
            // מציאת היומן הנבחר
            const scheduler = this.getSelectedScheduler();
            if (!scheduler) {
                window.BookingCalendarUtils.error('יומן לא נבחר');
                return null;
            }
            
            // בניית תאריך ושעה מלא
            const fromDateTime = this.buildDateTime(this.selectedDate, this.selectedTime);
            const duration = this.getTreatmentDuration(scheduler);
            const toDateTime = new Date(fromDateTime.getTime() + duration * 60000);
            const matchingTreatment = this.findSchedulerTreatment(scheduler, treatmentTypeId);

            const params = {
                scheduler_id: scheduler.id,
                proxy_schedule_id: scheduler.proxy_schedule_id || scheduler.proxy_scheduler_id || '',
                treatment_type: treatmentTypeId,
                treatment_type_display: this.getSelectedTreatmentDisplayName(scheduler, matchingTreatment),
                date: this.selectedDate,
                time: this.selectedTime,
                duration: duration,
                from: fromDateTime.toISOString(),
                to: toDateTime.toISOString(),
                clinic_id: scheduler.clinic_id || this.currentClinicId || '',
                doctor_id: this.currentDoctorId || scheduler.doctor_id || '',
                clinic_name: scheduler.clinic_name || '',
                doctor_id_full: scheduler.doctor_id || '', // מזהה הרופא המלא
                doctor_name: scheduler.doctor_name || scheduler.name || '',
                doctor_specialty: scheduler.doctor_specialty || '', // התמחות הרופא
                doctor_thumbnail: scheduler.doctor_thumbnail || '', // תמונת הרופא (אם יש)
                clinic_address: scheduler.clinic_address || '', // כתובת המרפאה (אם יש)
                referrer_url: window.location.href // URL של עמוד יומן התורים
            };
            // יומן קליניקס: העברת מזהה סיבת התור (drWebReasonID) לטופס ההזמנה
            if (scheduler.schedule_type === 'clinix' && matchingTreatment && matchingTreatment.clinix_treatment_id) {
                params.clinix_reason_id = matchingTreatment.clinix_treatment_id;
            }
            if (matchingTreatment && matchingTreatment.cost) {
                const treatmentCost = parseInt(matchingTreatment.cost, 10);
                if (treatmentCost > 0) {
                    params.treatment_cost = treatmentCost;
                }
            }
            
            window.BookingCalendarUtils.log('פרמטרים שנאספו להזמנת תור:', params);
            
            return params;
        }
        
        /**
         * מוצא את היומן הנבחר לפי schedulerId או מהשדה
         * @returns {Object|null} אובייקט היומן או null אם לא נמצא
         */
        getSelectedScheduler() {
            let schedulerId = null;
            
            // ניסיון לקבל מה-field (clinic mode)
            const schedulerField = this.element.find('.scheduler-field');
            if (schedulerField.length && schedulerField.val()) {
                schedulerId = schedulerField.val();
            } else if (this.schedulerId) {
                // Doctor mode: schedulerId נשמר ב-instance
                schedulerId = this.schedulerId;
            }
            
            if (!schedulerId) {
                return null;
            }
            
            // מציאת היומן ב-allSchedulers
            const schedulersArray = Array.isArray(this.allSchedulers) 
                ? this.allSchedulers 
                : Object.values(this.allSchedulers);
            
            const scheduler = schedulersArray.find(s => {
                return s.id == schedulerId || s.id === schedulerId || String(s.id) === String(schedulerId);
            });
            
            return scheduler || null;
        }
        
        /**
         * בונה תאריך ושעה מלא מ-date ו-time
         * @param {string} date תאריך בפורמט YYYY-MM-DD
         * @param {string} time שעה בפורמט HH:MM
         * @returns {Date} אובייקט Date
         */
        buildDateTime(date, time) {
            // date בפורמט YYYY-MM-DD, time בפורמט HH:MM
            const dateTimeStr = `${date}T${time}:00`;
            const localDate = new Date(dateTimeStr);
            
            // המרה ל-UTC (אבל נשמור את הזמן המקומי)
            // בפועל, נשתמש ב-toISOString() שיהמיר ל-UTC
            return localDate;
        }
        
        /**
         * מקבל את משך הטיפול בדקות מהיומן הנבחר
         * @param {Object} scheduler אובייקט היומן
         * @returns {number} משך הטיפול בדקות (ברירת מחדל: 30)
         */
        getTreatmentDuration(scheduler) {
            const matchingTreatment = this.findSchedulerTreatment(scheduler, this.treatmentType);
            if (matchingTreatment && matchingTreatment.duration) {
                return parseInt(matchingTreatment.duration, 10);
            }

            return 30;
        }

        /**
         * @param {Object} scheduler
         * @param {string|number|null} treatmentTypeId
         * @returns {Object|null}
         */
        findSchedulerTreatment(scheduler, treatmentTypeId) {
            const currentId = (treatmentTypeId !== undefined && treatmentTypeId !== null)
                ? String(treatmentTypeId).trim()
                : '';
            if (!currentId || !scheduler || !Array.isArray(scheduler.treatments)) {
                return null;
            }

            return scheduler.treatments.find((treatment) => {
                const tt = (treatment.treatment_type !== undefined && treatment.treatment_type !== null)
                    ? String(treatment.treatment_type).trim()
                    : '';
                return tt === currentId;
            }) || null;
        }

        /**
         * @param {Object} scheduler
         * @param {Object|null} [matchingTreatment]
         * @returns {string}
         */
        getSelectedTreatmentDisplayName(scheduler, matchingTreatment) {
            const match = matchingTreatment || this.findSchedulerTreatment(scheduler, this.treatmentType);
            if (match && match.treatment_type_name) {
                const name = String(match.treatment_type_name).trim();
                if (name) {
                    return name;
                }
            }

            const treatmentField = this.element.find('.treatment-field');
            if (treatmentField.length) {
                return String(treatmentField.find('option:selected').text() || '').trim();
            }

            return '';
        }
        
        /**
         * בונה URL עם query parameters
         * @param {number} pageId מזהה העמוד (4366)
         * @param {Object} params פרמטרים להעברה
         * @returns {string} URL מלא עם query parameters
         */
        buildBookingUrl(pageId, params) {
            // קבלת URL בסיס של העמוד
            const baseUrl = this.getPageUrl(pageId);
            
            // בניית query string
            const queryParams = new URLSearchParams();
            Object.keys(params).forEach(key => {
                const value = params[key];
                if (value !== null && value !== undefined && value !== '') {
                    queryParams.append(key, value);
                }
            });
            
            // החזרת URL מלא
            const separator = baseUrl.includes('?') ? '&' : '?';
            return `${baseUrl}${separator}${queryParams.toString()}`;
        }
        
        /**
         * מקבל URL של עמוד לפי ID
         * @param {number} pageId מזהה העמוד
         * @returns {string} URL של העמוד
         */
        getPageUrl(pageId) {
            // אם יש localized data עם permalink, השתמש בו
            if (window.bookingCalendarData && window.bookingCalendarData.pageUrls && 
                window.bookingCalendarData.pageUrls[pageId]) {
                return window.bookingCalendarData.pageUrls[pageId];
            }
            
            // אחרת, בנה URL ידנית (frontend page)
            // WordPress משתמש ב-?p= או ב-permalink
            // ננסה לקבל את ה-permalink דרך AJAX או נשתמש ב-URL ידני
            return `${window.location.origin}/?p=${pageId}`;
        }
        
        destroy() {
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            this.element.off(eventNamespace);
            $(document).off(eventNamespace);
            this.element.removeClass('is-mobile-dragging').css({
                transform: '',
                transition: ''
            });

            // ניקוי הכרטיס הקומפקטי
            if (this.mobileCompact && typeof this.mobileCompact.destroy === 'function') {
                this.mobileCompact.destroy();
            }

            if (this.element.hasClass('is-mobile-open')) {
                this.element.removeClass('is-mobile-open');
                window.BookingCalendarUtils.unlockBodyScroll();
            }

            // ניקוי רשומת ההיסטוריה אם הפאנל נסגר תוך כדי השמדה.
            if (this._mobileBackGuard) {
                this._mobileBackGuard.release();
            }

            // ניקוי ResizeObserver של ה-days carousel (מוגדר ב-UIManager.initCarouselNavigation)
            if (this.uiManager && this.uiManager._daysContainerResizeObserver) {
                this.uiManager._daysContainerResizeObserver.disconnect();
                this.uiManager._daysContainerResizeObserver = null;
            }

            window.BookingCalendarManager.instances.delete(this.widgetId);
        }
    }

    // Export to global scope
    window.BookingCalendarCore = BookingCalendarCore;
    // Legacy alias for backward compatibility
    window.BookingCalendarWidget = BookingCalendarCore;

})(jQuery);
