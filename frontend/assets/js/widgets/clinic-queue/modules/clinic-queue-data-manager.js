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
         */
        async loadFreeSlots() {
            if (this.core.isLoading) return;
            
            this.core.isLoading = true;
            this.showLoading();
            
            try {
                const schedulerId = this.core.effectiveDoctorId || this.core.effectiveClinicId || 1;
                
                // Calculate date range (next 30 days)
                const now = new Date();
                const toDate = new Date();
                toDate.setDate(toDate.getDate() + 30);
                
                // Use ISO format for API
                const fromDateUTC = now.toISOString();
                const toDateUTC = toDate.toISOString();
                
                const endpoint = `${this.apiBaseUrl}/scheduler/free-time`;
                const params = {
                    scheduler_id: schedulerId,
                    duration: 15, // Default duration
                    from_date_utc: fromDateUTC,
                    to_date_utc: toDateUTC
                };

                console.log('[ClinicQueue] Loading free slots:', params);
                
                const response = await $.get(endpoint, params);
                
                console.log('[ClinicQueue] API Response:', response);
                
                if (!response || !response.result) {
                    window.ClinicQueueUtils.log('No data found in API response');
                    this.showNoDataMessage();
                    return;
                }
                
                // Transform API data to internal format
                const processedData = this.processApiData(response.result);
                this.core.appointmentData = processedData;
                
                if (processedData.length === 0) {
                    this.showNoAppointmentsMessage();
                    return;
                }
                
                window.ClinicQueueUtils.log('Data loaded successfully, rendering...');
                this.renderData();

            } catch (error) {
                window.ClinicQueueUtils.error('Failed to load appointment data:', error);
                this.core.showError('שגיאה בטעינת נתוני התורים');
            } finally {
                this.core.isLoading = false;
            }
        }
        
        /**
         * Process flat API slots into grouped days
         */
        processApiData(slots) {
            const slotsByDate = {};
            
            slots.forEach(slot => {
                const fromDate = new Date(slot.from);
                const dateKey = window.ClinicQueueUtils.formatDate(fromDate); // YYYY-MM-DD
                
                if (!slotsByDate[dateKey]) {
                    slotsByDate[dateKey] = [];
                }
                
                // Format time HH:MM
                const hours = String(fromDate.getHours()).padStart(2, '0');
                const minutes = String(fromDate.getMinutes()).padStart(2, '0');
                const timeStr = `${hours}:${minutes}`;
                
                slotsByDate[dateKey].push({
                    time_slot: timeStr,
                    is_booked: 0,
                    from: slot.from,
                    to: slot.to
                });
            });
            
            // Convert to array format expected by UI
            const appointmentsData = Object.keys(slotsByDate).sort().map(date => ({
                date: { appointment_date: date },
                time_slots: slotsByDate[date].sort((a, b) => a.time_slot.localeCompare(b.time_slot))
            }));
            
            return appointmentsData;
        }

        // Legacy alias for compatibility
        loadAllAppointmentData() {
            return this.loadFreeSlots();
        }
        
        // Legacy alias
        filterAndRenderData() {
            return this.loadFreeSlots();
        }

        renderData() {
            // Reset selected date to allow auto-selection of first active day
            this.core.selectedDate = null;

            // Hide loading message
            this.core.element.find('.loading-message').remove();

            // Always ensure containers are visible
            const container = this.core.element.find('.appointments-calendar');
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();

            // Render calendar with data
            this.core.uiManager.renderDays();
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

    // Export to global scope
    window.ClinicQueueDataManager = DataManager;

})(jQuery);
