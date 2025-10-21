/**
 * Clinic Queue Management JavaScript - Clean & Simple Version
 * Handles multiple widget instances with clean separation of concerns
 */
(function($) {
    'use strict';

    // Global registry for widget instances
    window.ClinicQueueManager = window.ClinicQueueManager || {
        instances: new Map(),
        globalSettings: {
            loadingTimeouts: new Map(),
            sharedCache: new Map()
        }
    };

    // Utility functions
    const Utils = {
        formatDate: (date, format = 'YYYY-MM-DD') => {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        
        log: (message, data = null) => {
            if (window.console && window.console.log) {
                if (data !== null && data !== undefined) {
                    console.log(`[ClinicQueue] ${message}`, data);
                } else {
                    console.log(`[ClinicQueue] ${message}`);
                }
            }
        },
        
        error: (message, error = null) => {
            if (window.console && window.console.error) {
                console.error(`[ClinicQueue] ${message}`, error || '');
            }
        }
    };

    // Data Manager - handles all data operations
    class DataManager {
        constructor(core) {
            this.core = core;
        }

        async loadAllAppointmentData() {
            if (this.core.isLoading) return;
            
            this.core.isLoading = true;
            this.showLoading();
            
            try {
                const restUrl = `${window.location.origin}/wp-json/clinic-queue/v1/all-appointments`;
                console.log('[ClinicQueue] Loading API data from:', restUrl);
                
                const data = await $.get(restUrl);
                
                console.log('[ClinicQueue] ===== API RESPONSE START =====');
                console.log('[ClinicQueue] API Response received:', data);
                console.log('[ClinicQueue] Response type:', typeof data);
                console.log('[ClinicQueue] Has calendars:', data && data.calendars ? 'Yes' : 'No');
                if (data && data.calendars) {
                    console.log('[ClinicQueue] Number of calendars:', data.calendars.length);
                }
                console.log('[ClinicQueue] ===== API RESPONSE END =====');
                
                this.core.allAppointmentData = data;
                
                if (!data || !data.calendars || data.calendars.length === 0) {
                    Utils.log('No data found in API response');
                    this.showNoDataMessage();
                    return;
                }
                
                Utils.log('Data loaded successfully, filtering and rendering...');
                this.filterAndRenderData();
            } catch (error) {
                Utils.error('Failed to load appointment data:', error);
                this.core.showError('שגיאה בטעינת נתוני התורים');
            } finally {
                this.core.isLoading = false;
            }
        }
        

        filterAppointmentData(allData, doctorId, clinicId, treatmentType) {
            if (!allData?.calendars) {
                return null;
            }
            
            const matchingCalendar = allData.calendars.find(calendar => 
                String(calendar.doctor_id) === String(doctorId) && 
                String(calendar.clinic_id) === String(clinicId) && 
                calendar.treatment_type === treatmentType
            );
            
            if (!matchingCalendar) {
                console.log(`[ClinicQueue] ❌ No calendar found for Doctor=${doctorId}, Clinic=${clinicId}, Treatment=${treatmentType}`);
                return null;
            }
            
            const appointmentsData = [];
            if (matchingCalendar.appointments) {
                Object.keys(matchingCalendar.appointments).forEach(date => {
                    const slots = matchingCalendar.appointments[date];
                    const timeSlots = slots.map(slot => ({
                        time_slot: slot.time,
                        is_booked: slot.is_booked ? 1 : 0,
                        patient_name: null,
                        patient_phone: null
                    }));
                    
                    appointmentsData.push({
                        date: { appointment_date: date },
                        time_slots: timeSlots
                    });
                });
            }
            
            console.log(`[ClinicQueue] ✓ Found ${appointmentsData.length} days with appointments`);
            return appointmentsData;
        }

        filterAndRenderData() {
            if (!this.core.allAppointmentData) {
                this.loadAllAppointmentData();
                return;
            }

            const filteredData = this.filterAppointmentData(
                this.core.allAppointmentData,
                this.core.effectiveDoctorId,
                this.core.effectiveClinicId,
                this.core.effectiveTreatmentType
            );

            this.core.appointmentData = filteredData;

            // Hide loading message
            this.core.element.find('.loading-message').remove();

            // Always ensure containers are visible
            const container = this.core.element.find('.appointments-calendar');
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();

            // Always render days, even if no data
            this.core.uiManager.renderDays();

            if (!filteredData || filteredData.length === 0) {
                this.showNoMatchMessage();
                return;
            }

            // Render calendar with data
            this.core.uiManager.renderCalendar();
            this.core.showContent();
        }

        showLoading() {
            const container = this.core.element.find('.appointments-calendar');
            container.find('.days-carousel, .time-slots-container, .month-and-year').hide();
            container.find('.loading-message, .no-appointments-message, .no-data-message').remove();
            
            container.append(`
                <div class="loading-message">
                    <div class="spinner"></div>
                    <p>טוען נתונים...</p>
                </div>
            `);
        }

        showNoAppointmentsMessage() {
            // Show 6 disabled days with no appointments message
            this.renderEmptyDays();
            this.core.element.find('.time-slots-container').html(`
                <div style="text-align: center; padding: 40px 20px; color: #6c757d; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 10px 0;">
                    <div style="font-size: 32px; margin-bottom: 10px;">📅</div>
                    <p style="margin: 0; font-size: 16px; font-weight: 500;">אין תורים זמינים</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #999;">לא נמצאו תורים פנויים כרגע. נסה שוב מאוחר יותר.</p>
                </div>
            `);
        }
        
        showNoMatchMessage() {
            // Show 6 disabled days with no match message
            this.renderEmptyDays();
            this.core.element.find('.month-and-year').html('&nbsp;');
            this.core.element.find('.time-slots-container').html(`
                <div style="text-align: center; padding: 40px 20px; color: #856404; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; margin: 10px 0;">
                    <div style="font-size: 32px; margin-bottom: 10px;">❌</div>
                    <p style="margin: 0; font-size: 16px; font-weight: 500;">לא נמצאה התאמה</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px;">לא קיים יומן עבור השילוב שנבחר. נסה לבחור אפשרויות אחרות.</p>
                </div>
            `);
        }
        
        renderEmptyDays() {
            // Render 6 disabled days as placeholders
            const daysContainer = this.core.element.find('.days-container');
            daysContainer.empty();
            
            const hebrewDayAbbrev = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳'];
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let i = 0; i < 6; i++) {
                const currentDay = new Date(today);
                currentDay.setDate(today.getDate() + i);
                const dayNumber = currentDay.getDate();
                const dayAbbrev = hebrewDayAbbrev[i];
                
                const dayTab = $('<div>')
                    .addClass('day-tab disabled')
                    .css('pointer-events', 'none');
                
                dayTab.html(`
                    <div class="day-abbrev">${dayAbbrev}</div>
                    <div class="day-content">
                        <div class="day-number">${dayNumber}</div>
                        <div class="day-slots-count">0</div>
                    </div>
                `);
                
                daysContainer.append(dayTab);
            }
        }

        showNoDataMessage() {
            const container = this.core.element.find('.appointments-calendar');
            container.find('.days-carousel, .time-slots-container, .month-and-year').hide();
            container.find('.loading-message, .no-appointments-message, .no-data-message').remove();
            
            container.append(`
                <div class="no-data-message">
                    <div class="no-data-icon">📋</div>
                    <h3>אין נתוני תורים</h3>
                    <p>לא נמצאו נתוני תורים במערכת</p>
                </div>
            `);
        }

        hideNoAppointmentsMessage() {
            const container = this.core.element.find('.appointments-calendar');
            container.find('.loading-message, .no-appointments-message, .no-data-message, .no-match-message').remove();
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();
        }
    }

    // UI Manager - handles all UI operations
    class UIManager {
        constructor(core) {
            this.core = core;
        }

        renderCalendar() {
            console.log('[ClinicQueue] Rendering calendar...');
            
            if (!this.core.appointmentData || this.core.appointmentData.length === 0) {
                console.log('[ClinicQueue] No appointment data to render');
                return;
            }
            
            this.updateMonthTitle();
            this.renderDays();
            console.log('[ClinicQueue] Calendar rendered successfully');
        }

        updateMonthTitle() {
            const monthTitle = this.core.currentMonth.toLocaleDateString('he-IL', { 
                month: 'long', 
                year: 'numeric' 
            });
            // Update the h2 inside month-and-year
            this.core.element.find('.month-and-year').text(monthTitle);
        }

        renderDays() {
            const daysContainer = this.core.element.find('.days-container');
            if (daysContainer.length === 0) {
                console.log('[ClinicQueue] Days container not found!');
                return;
            }

            // Clear existing content but preserve selected state
            const currentSelectedDate = this.core.selectedDate;
            daysContainer.empty();
            
            const hebrewDayAbbrev = {
                'Sunday': 'א׳',
                'Monday': 'ב׳',
                'Tuesday': 'ג׳',
                'Wednesday': 'ד׳',
                'Thursday': 'ה׳',
                'Friday': 'ו׳',
                'Saturday': 'ש׳'
            };
            
            // Create a map of dates with appointments for quick lookup
            const appointmentsMap = new Map();
            if (this.core.appointmentData && this.core.appointmentData.length > 0) {
                this.core.appointmentData.forEach(appointment => {
                    let dateStr = '';
                    if (appointment.date) {
                        if (appointment.date.appointment_date) {
                            dateStr = appointment.date.appointment_date;
                        } else if (typeof appointment.date === 'string') {
                            dateStr = appointment.date;
                        }
                    }
                    if (dateStr) {
                        appointmentsMap.set(dateStr, appointment);
                    }
                });
            }
            
            // Generate 6 days starting from today
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let i = 0; i < 6; i++) {
                const currentDay = new Date(today);
                currentDay.setDate(today.getDate() + i);
                const dateStr = currentDay.toISOString().split('T')[0]; // YYYY-MM-DD format
                
                const dayNumber = currentDay.getDate();
                const dayName = currentDay.toLocaleDateString('en-US', { weekday: 'long' });
                
                // Check if this day has appointments
                const appointment = appointmentsMap.get(dateStr);
                const hasSlots = appointment && appointment.time_slots && appointment.time_slots.length > 0;
                const totalSlots = hasSlots ? appointment.time_slots.length : 0;
                const isSelected = this.core.selectedDate === dateStr;
                
                const dayTab = $('<div>')
                    .addClass('day-tab')
                    .data('date', dateStr)
                    .toggleClass('selected', isSelected)
                    .toggleClass('disabled', !hasSlots);

                // If disabled, don't make it clickable
                if (!hasSlots) {
                    dayTab.css('pointer-events', 'none');
                }

                dayTab.html(`
                    <div class="day-abbrev">${hebrewDayAbbrev[dayName] || dayName}</div>
                    <div class="day-content">
                        <div class="day-number">${dayNumber}</div>
                        <div class="day-slots-count">${totalSlots}</div>
                    </div>
                `);

                daysContainer.append(dayTab);
            }
        }

        generateDays() {
            const days = [];
            const firstDay = new Date(this.core.currentMonth.getFullYear(), this.core.currentMonth.getMonth(), 1);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            for (let i = 0; i < 35; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                days.push(date);
            }
            
            return days;
        }

        selectDate(date) {
            this.core.selectedDate = date;
            this.core.selectedTime = null;

            // Update selection in day tabs
            this.core.element.find('.day-tab').removeClass('selected');
            this.core.element.find(`.day-tab[data-date="${date}"]`).addClass('selected');

            this.renderTimeSlots();
            this.core.showContent();
        }

        renderTimeSlots() {
            const timeSlotsContainer = this.core.element.find('.time-slots-container');
            
            if (!this.core.selectedDate) {
                timeSlotsContainer.html(`
                    <div style="text-align: center; padding: 40px 20px; color: #6c757d;">
                        <p style="margin: 0; font-size: 16px;">בחר תאריך כדי לראות תורים זמינים</p>
                    </div>
                `);
                
                // Add action buttons even when no date selected
                this.ensureActionButtons();
                return;
            }
            
            // Find the selected day's data
            const dayData = this.core.appointmentData.find(d => {
                let dateStr = '';
                if (d.date) {
                    if (d.date.appointment_date) {
                        dateStr = d.date.appointment_date;
                    } else if (typeof d.date === 'string') {
                        dateStr = d.date;
                    }
                }
                return dateStr === this.core.selectedDate;
            });
            
            if (!dayData || !dayData.time_slots || dayData.time_slots.length === 0) {
                timeSlotsContainer.html(`
                    <div style="text-align: center; padding: 40px 20px; color: #856404; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; margin: 10px 0;">
                        <p style="margin: 0; font-size: 16px;">אין תורים זמינים בתאריך זה</p>
                    </div>
                `);
                
                // Add action buttons even when no slots available
                this.ensureActionButtons();
                return;
            }
            
            // Create time slots grid
            console.log('[ClinicQueue] Day data:', dayData);
            console.log('[ClinicQueue] Time slots:', dayData.time_slots);
            
            const slotsHtml = dayData.time_slots.map(slot => {
                console.log('[ClinicQueue] Processing slot:', slot);
                const slotTime = slot.time_slot || slot.time || slot.start_time || slot.appointment_time || '';
                const isBooked = slot.is_booked === 1 || slot.is_booked === true || slot.booked || slot.status === 'booked';
                const slotClass = isBooked ? 'time-slot-badge booked' : 'time-slot-badge free';

                return `
                    <div class="${slotClass}" data-time="${slotTime}" ${isBooked ? 'data-disabled="true"' : ''}>
                        ${slotTime}
                    </div>
                `;
            }).join('');
            
            timeSlotsContainer.html(`
                <div class="time-slots-grid">
                    ${slotsHtml}
                </div>
            `);
            
            // Add action buttons to bottom section (only if not already added)
            this.ensureActionButtons();
            
            // Bind click events for time slots
            timeSlotsContainer.find('.time-slot-badge.free').on('click', (e) => {
                const $slot = $(e.currentTarget);
                const time = $slot.data('time');

                // Toggle selection - only one can be selected at a time
                this.selectTimeSlot(time);
            });
        }
        
        selectTimeSlot(time) {
            this.core.selectedTime = time;
            
            // Update selection in time slots
            this.core.element.find('.time-slot-badge').removeClass('selected');
            this.core.element.find(`.time-slot-badge[data-time="${time}"]`).addClass('selected');
            
            console.log('[ClinicQueue] Time slot selected:', time);
        }
        
        addActionButtons() {
            const bottomSection = this.core.element.find('.bottom-section');
            
            // Remove existing action buttons if they exist
            bottomSection.find('.action-buttons-container').remove();
            
            // Get button labels from widget settings (from data attributes on the calendar element)
            const ctaLabel = this.core.element.data('cta-label') || 'הזמן תור';
            const viewAllLabel = this.core.element.data('view-all-label') || 'צפייה בכל התורים';
            
            // Add action buttons container
            const actionButtonsHtml = `
                <div class="action-buttons-container">
                    <button type="button" class="btn btn-outline ap-view-all-btn">
                        ${viewAllLabel}
                    </button>
                    <button type="button" class="btn btn-primary ap-book-btn">
                        ${ctaLabel}
                    </button>
                </div>
            `;
            
            bottomSection.append(actionButtonsHtml);
        }
        
        ensureActionButtons() {
            const bottomSection = this.core.element.find('.bottom-section');
            
            // Only add buttons if they don't already exist
            if (bottomSection.find('.action-buttons-container').length === 0) {
                this.addActionButtons();
            }
        }

        changeMonth(direction) {
            this.core.currentMonth.setMonth(this.core.currentMonth.getMonth() + direction);
            this.renderCalendar();
        }
    }

    // Main ClinicQueueWidget class
    class ClinicQueueWidget {
        constructor(element) {
            console.log('[ClinicQueue] *** Constructor START ***');
            console.log('[ClinicQueue] Element:', element);
            this.element = $(element);
            this.widgetId = this.element.attr('id');
            console.log('[ClinicQueue] Widget ID:', this.widgetId);
            this.ctaLabel = this.element.data('cta-label');
            
            // Get selection mode and effective values from data attributes
            this.selectionMode = this.element.data('selection-mode') || 'doctor';
            this.effectiveDoctorId = this.element.data('effective-doctor-id') || '1';
            this.effectiveClinicId = this.element.data('effective-clinic-id') || '1';
            this.effectiveTreatmentType = this.element.data('effective-treatment-type') || 'רפואה כללית';
            
            // Initialize based on selection mode
            if (this.selectionMode === 'doctor') {
                this.doctorId = this.effectiveDoctorId;
                this.clinicId = this.element.data('specific-clinic-id') || '1';
            } else if (this.selectionMode === 'clinic') {
                this.doctorId = this.element.data('specific-doctor-id') || '1';
                this.clinicId = this.effectiveClinicId;
            }
            
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
            this.dataManager = new DataManager(this);
            this.uiManager = new UIManager(this);
            
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
            
            this.bindEvents();
            this.dataManager.loadAllAppointmentData();
        }
        
        bindEvents() {
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            
            // Form field selections
            this.element.on(`change${eventNamespace}`, '.form-field-select', (e) => {
                const field = $(e.target).data('field');
                const value = $(e.target).val();
                Utils.log('Form field changed:', { field, value });
                this.handleFormFieldChange(field, value);
            });
            
            // Form submission (prevent default)
            this.element.on(`submit${eventNamespace}`, '.widget-selection-form', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
            
            // Date selection (day tabs)
            this.element.on(`click${eventNamespace}`, '.day-tab', (e) => {
                e.preventDefault();
                const date = $(e.currentTarget).data('date');
                if (date) {
                    this.uiManager.selectDate(date);
                }
            });
            
            // Book appointment
            this.element.on(`click${eventNamespace}`, '.ap-book-btn', (e) => {
                e.preventDefault();
                this.bookAppointment();
            });
            
            // View all appointments
            this.element.on(`click${eventNamespace}`, '.ap-view-all-btn', (e) => {
                e.preventDefault();
                this.viewAllAppointments();
            });
        }
        
        handleFormFieldChange(field, value) {
            console.log(`[ClinicQueue] Field changed: ${field} = ${value}`);
            
            // Get form data
            const form = this.element.find('.widget-selection-form');
            const formData = this.getFormData(form);
            
            // Update effective values based on selection mode
            this.updateEffectiveValues(formData);
            
            // Show loading state
            this.showLoadingState();
            
            // Reset selections
            this.resetSelections();
            
            // Re-filter and render data
            this.dataManager.filterAndRenderData();
        }
        
        handleFormSubmit() {
            Utils.log('Form submitted');
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
                if (name) {
                    formData[name] = value;
                }
            });
            return formData;
        }
        
        updateEffectiveValues(formData) {
            const selectionMode = formData.selection_mode || this.selectionMode;
            
            if (selectionMode === 'doctor') {
                // Doctor mode: Doctor is SELECTABLE, Clinic is FIXED
                this.doctorId = formData.doctor_id;
                this.effectiveDoctorId = this.doctorId;
                this.clinicId = formData.clinic_id; // This is the hidden field value
                this.effectiveClinicId = this.clinicId;
            } else if (selectionMode === 'clinic') {
                // Clinic mode: Clinic is SELECTABLE, Doctor is FIXED
                this.doctorId = formData.doctor_id; // This is the hidden field value
                this.effectiveDoctorId = this.doctorId;
                this.clinicId = formData.clinic_id;
                this.effectiveClinicId = this.clinicId;
            }
            
            // Treatment type - either selectable or fixed
            this.treatmentType = formData.treatment_type || 'רפואה כללית';
            this.effectiveTreatmentType = this.treatmentType;
            
            console.log(`[ClinicQueue] Updated to: Doctor=${this.effectiveDoctorId}, Clinic=${this.effectiveClinicId}, Treatment=${this.effectiveTreatmentType}`);
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
        
        bookAppointment() {
            if (this.selectedDate && this.selectedTime) {
                const patientName = prompt('שם המטופל:');
                const patientPhone = prompt('טלפון המטופל:');
                
                if (!patientName || !patientPhone) {
                    alert('נא למלא את כל הפרטים');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'clinic_queue_book_appointment',
                        nonce: clinicQueueAjax.nonce,
                        doctor_id: this.effectiveDoctorId,
                        clinic_id: this.effectiveClinicId,
                        treatment_type: this.effectiveTreatmentType,
                        date: this.selectedDate,
                        time: this.selectedTime,
                        patient_name: patientName,
                        patient_phone: patientPhone
                    },
                    success: (response) => {
                        if (response.success) {
                            alert('התור נקבע בהצלחה!');
                            this.dataManager.loadAllAppointmentData();
                        } else {
                            alert('שגיאה: ' + response.data);
                        }
                    },
                    error: () => {
                        alert('שגיאה בחיבור לשרת');
                    }
                });
            } else {
                alert('נא לבחור תאריך ושעה להזמנה');
            }
        }
        
        viewAllAppointments() {
            // Show all appointments in a modal or redirect to appointments page
            const appointmentsUrl = `${window.location.origin}/wp-admin/admin.php?page=clinic-queue-appointments&doctor_id=${this.effectiveDoctorId}&clinic_id=${this.effectiveClinicId}`;
            window.open(appointmentsUrl, '_blank');
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

    // Initialize widgets when DOM is ready
    function initializeWidgets() {
        console.log('[ClinicQueue] initializeWidgets called');
        const widgets = $('.ap-widget:not([data-initialized])');
        console.log('[ClinicQueue] Found widgets:', widgets.length);
        
        if (widgets.length === 0) {
            console.log('[ClinicQueue] No .ap-widget elements found. Checking for .appointments-calendar...');
            const altWidgets = $('.appointments-calendar:not([data-initialized])');
            console.log('[ClinicQueue] Found .appointments-calendar elements:', altWidgets.length);
            
            altWidgets.each(function() {
                console.log('[ClinicQueue] Initializing widget:', this);
                $(this).attr('data-initialized', 'true');
                new ClinicQueueWidget(this);
            });
        } else {
            widgets.each(function() {
                console.log('[ClinicQueue] Initializing widget:', this);
                $(this).attr('data-initialized', 'true');
                new ClinicQueueWidget(this);
            });
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        console.log('[ClinicQueue] DOM ready, initializing widgets...');
        initializeWidgets();
    });
    
    // Re-initialize if new widgets are added dynamically
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('ap-widget') && !$(e.target).attr('data-initialized')) {
            setTimeout(initializeWidgets, 100);
        }
    });

    // Global utility functions
    window.ClinicQueueManager.utils = {
        getInstance: (widgetId) => window.ClinicQueueManager.instances.get(widgetId),
        getAllInstances: () => Array.from(window.ClinicQueueManager.instances.values()),
        destroyInstance: (widgetId) => {
            const instance = window.ClinicQueueManager.instances.get(widgetId);
            if (instance) instance.destroy();
        },
        clearCache: () => window.ClinicQueueManager.globalSettings.sharedCache.clear(),
        reinitialize: initializeWidgets
    };

    // Export for external use
    window.ClinicQueueWidget = ClinicQueueWidget;
    window.ClinicQueueUtils = Utils;

    console.log('[ClinicQueue] Script loaded successfully at:', new Date().toISOString());
    console.log('[ClinicQueue] Utils available:', typeof Utils !== 'undefined');
    console.log('[ClinicQueue] jQuery available:', typeof jQuery !== 'undefined');

})(jQuery);