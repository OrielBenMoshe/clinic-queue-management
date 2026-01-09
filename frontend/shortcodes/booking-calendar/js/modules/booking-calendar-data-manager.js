/**
 * Clinic Queue Management - Data Manager Module
 * Handles all data operations and API calls
 */
(function($) {
    'use strict';

    // Data Manager - handles all data operations
    class DataManager {
        constructor(core) {
            this.core = core;
            this.apiBaseUrl = `${window.location.origin}/wp-json/clinic-queue/v1`;
        }

        /**
         * Load free slots from API
         * Uses proxy_scheduler_id, duration from selected treatment, and date range (3 weeks)
         */
        async loadFreeSlots() {
            if (this.core.isLoading) return;
            
            this.core.isLoading = true;
            this.showLoading();
            
            try {
                // Get selected scheduler and treatment
                const schedulerField = this.core.element.find('.scheduler-field');
                const treatmentField = this.core.element.find('.treatment-field');
                
                if (!schedulerField.length || !schedulerField.val()) {
                    window.BookingCalendarUtils.log('No scheduler selected');
                    this.core.appointmentData = [];
                    this.showNoAppointmentsMessage();
                    return;
                }
                
                // Get proxy_scheduler_id from selected scheduler option
                const selectedSchedulerOption = schedulerField.find('option:selected');
                const proxySchedulerId = selectedSchedulerOption.data('proxy-scheduler-id');
                
                if (!proxySchedulerId) {
                    window.BookingCalendarUtils.error('No proxy_scheduler_id found for selected scheduler');
                    this.core.appointmentData = [];
                    this.showNoAppointmentsMessage();
                    return;
                }
                
                // Get duration from scheduler option (duration comes from treatments repeater)
                // Duration is stored in scheduler option's data-duration attribute
                let duration = 30; // Default duration
                const schedulerDuration = selectedSchedulerOption.data('duration');
                if (schedulerDuration) {
                    duration = parseInt(schedulerDuration, 10);
                }
                
                // Calculate date range: from now to 3 weeks ahead, end of day
                const now = new Date();
                const toDate = new Date();
                toDate.setDate(toDate.getDate() + 21); // 3 weeks = 21 days
                toDate.setHours(23, 59, 59, 999); // End of day
                
                // Convert to UTC format: YYYY-MM-DDTHH:mm:ssZ
                const fromDateUTC = this.formatDateUTC(now);
                const toDateUTC = this.formatDateUTC(toDate);
                
                // Build schedulerIDsStr
                // According to requirements: if multiple schedulers found, pass all proxy_scheduler_id separated by commas
                // For now, we use the selected scheduler (single selection)
                // If in the future we support multi-select, we would collect all proxy_scheduler_id values
                const schedulerIDsStr = String(proxySchedulerId);
                
                const endpoint = `${this.apiBaseUrl}/scheduler/free-time`;
                const params = {
                    schedulerIDsStr: schedulerIDsStr,
                    duration: duration,
                    fromDateUTC: fromDateUTC,
                    toDateUTC: toDateUTC
                };

                window.BookingCalendarUtils.log('Loading free slots:', params);
                
                const response = await $.get(endpoint, params);
                
                window.BookingCalendarUtils.log('API Response:', response);
                
                if (!response || !response.result) {
                    window.BookingCalendarUtils.log('No data found in API response');
                    this.core.appointmentData = [];
                    this.showNoAppointmentsMessage();
                    return;
                }
                
                // Transform API data to internal format
                const processedData = this.processApiData(response.result);
                this.core.appointmentData = processedData;
                
                if (processedData.length === 0) {
                    this.showNoAppointmentsMessage();
                    return;
                }
                
                window.BookingCalendarUtils.log('Data loaded successfully, rendering...');
                this.renderData();

            } catch (error) {
                window.BookingCalendarUtils.error('Failed to load appointment data:', error);
                this.core.appointmentData = [];
                this.showNoAppointmentsMessage();
                window.BookingCalendarUtils.log('Rendering empty calendar due to API error');
            } finally {
                this.core.isLoading = false;
            }
        }
        
        /**
         * Format date to UTC ISO 8601 format: YYYY-MM-DDTHH:mm:ssZ
         * @param {Date} date - The date to format
         * @returns {string} Formatted date string
         */
        formatDateUTC(date) {
            const year = date.getUTCFullYear();
            const month = String(date.getUTCMonth() + 1).padStart(2, '0');
            const day = String(date.getUTCDate()).padStart(2, '0');
            const hours = String(date.getUTCHours()).padStart(2, '0');
            const minutes = String(date.getUTCMinutes()).padStart(2, '0');
            const seconds = String(date.getUTCSeconds()).padStart(2, '0');
            
            return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}Z`;
        }
        
        /**
         * Process flat API slots into grouped days
         * Updated Dec 2025: Calculate 'to' time since API no longer returns it
         */
        processApiData(slots) {
            const slotsByDate = {};
            
            slots.forEach(slot => {
                const fromDate = new Date(slot.from);
                const dateKey = window.BookingCalendarUtils.formatDate(fromDate); // YYYY-MM-DD
                
                if (!slotsByDate[dateKey]) {
                    slotsByDate[dateKey] = [];
                }
                
                // Format time HH:MM
                const hours = String(fromDate.getHours()).padStart(2, '0');
                const minutes = String(fromDate.getMinutes()).padStart(2, '0');
                const timeStr = `${hours}:${minutes}`;
                
                // Calculate 'to' time: from + duration (default 15 minutes if not provided)
                const duration = slot.duration || 15; // Default duration in minutes
                const toDate = new Date(fromDate.getTime() + duration * 60000);
                
                slotsByDate[dateKey].push({
                    time_slot: timeStr,
                    is_booked: 0,
                    from: slot.from,
                    to: toDate.toISOString() // Calculated 'to' time
                });
            });
            
            // Convert to array format expected by UI
            const appointmentsData = Object.keys(slotsByDate).sort().map(date => ({
                date: { appointment_date: date },
                time_slots: slotsByDate[date].sort((a, b) => a.time_slot.localeCompare(b.time_slot))
            }));
            
            return appointmentsData;
        }


        renderData() {
            // Reset selected date to allow auto-selection of first active day
            this.core.selectedDate = null;

            // Hide loading message
            this.core.element.find('.loading-message').remove();

            // Always ensure containers are visible
            const container = this.core.element.find('.booking-calendar-shortcode');
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();

            // Render calendar with data
            this.core.uiManager.renderDays();
            this.core.uiManager.renderCalendar();
            this.core.showContent();
        }

        showLoading() {
            const container = this.core.element.find('.booking-calendar-shortcode');
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
            // הסר הודעות טעינה
            this.core.element.find('.loading-message').remove();
            
            // הצג את מבנה היומן
            const container = this.core.element.find('.booking-calendar-shortcode');
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();
            
            // עדכן את כותרת החודש
            const today = new Date();
            const monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'סתמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
            this.core.element.find('.month-and-year').text(`${monthNames[today.getMonth()]} ${today.getFullYear()}`);
            
            // הצג ימים ריקים
            this.renderEmptyDays();
            
            // הצג מסר בחלק התחתון
            this.core.element.find('.time-slots-container').html(`
                <div style="text-align: center; padding: 40px 20px; color: #6c757d; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 10px 0;">
                    <div style="font-size: 32px; margin-bottom: 10px;">📅</div>
                    <p style="margin: 0; font-size: 16px; font-weight: 500;">אין תורים זמינים</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #999;">לא נמצאו תורים פנויים כרגע. נסה שוב מאוחר יותר.</p>
                </div>
            `);
        }
        
        showNoMatchMessage() {
            this.showNoAppointmentsMessage();
        }
        
        renderEmptyDays() {
            const daysContainer = this.core.element.find('.days-container');
            daysContainer.empty();
            
            const hebrewDayAbbrev = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳'];
            const today = new Date();
            
            for (let i = 0; i < 6; i++) {
                const currentDay = new Date(today);
                currentDay.setDate(today.getDate() + i);
                const dayNumber = currentDay.getDate();
                const dayAbbrev = hebrewDayAbbrev[currentDay.getDay() % 6]; // Simple approx
                
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
            const container = this.core.element.find('.booking-calendar-shortcode');
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
            const container = this.core.element.find('.booking-calendar-shortcode');
            container.find('.loading-message, .no-appointments-message, .no-data-message, .no-match-message').remove();
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();
        }

        /**
         * Load initial schedulers on page load
         * @param {string|null} clinicId - The clinic ID (optional)
         * @param {string|null} doctorId - The doctor ID (optional)
         * @returns {Promise<Array>} Array of schedulers with all meta fields
         */
        async loadInitialSchedulers(clinicId, doctorId) {
            try {
                window.BookingCalendarUtils.log('Loading initial schedulers:', { clinicId, doctorId });
                
                // Get AJAX URL and nonce
                if (!window.clinicQueueAjax) {
                    window.BookingCalendarUtils.error('AJAX data not found. Make sure clinicQueueAjax is localized.');
                    return [];
                }
                
                // Build request data
                const requestData = {
                    action: 'clinic_queue_get_initial_schedulers',
                    nonce: window.clinicQueueAjax.nonce
                };
                
                if (clinicId) {
                    requestData.clinic_id = clinicId;
                }
                if (doctorId) {
                    requestData.doctor_id = doctorId;
                }
                
                const response = await $.ajax({
                    url: window.clinicQueueAjax.ajaxUrl || window.clinicQueueAjax.ajaxurl,
                    type: 'POST',
                    data: requestData
                });
                
                window.BookingCalendarUtils.log('Initial schedulers AJAX Response:', response);
                
                if (response.success && response.data && response.data.schedulers) {
                    // Handle both array and object formats
                    let schedulers = [];
                    if (Array.isArray(response.data.schedulers)) {
                        schedulers = response.data.schedulers;
                    } else if (typeof response.data.schedulers === 'object') {
                        schedulers = Object.values(response.data.schedulers);
                    }
                    
                    window.BookingCalendarUtils.log(`Loaded ${schedulers.length} initial schedulers`);
                    return schedulers;
                } else {
                    window.BookingCalendarUtils.log('No initial schedulers found');
                    return [];
                }
                
            } catch (error) {
                window.BookingCalendarUtils.error('Failed to load initial schedulers:', error);
                return [];
            }
        }

    }

    // Export to global scope
    window.BookingCalendarDataManager = DataManager;

})(jQuery);
