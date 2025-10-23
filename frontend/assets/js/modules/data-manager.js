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
                    window.ClinicQueueUtils.log('No data found in API response');
                    this.showNoDataMessage();
                    return;
                }
                
                window.ClinicQueueUtils.log('Data loaded successfully, filtering and rendering...');
                this.filterAndRenderData();
            } catch (error) {
                window.ClinicQueueUtils.error('Failed to load appointment data:', error);
                this.core.showError('שגיאה בטעינת נתוני התורים');
            } finally {
                this.core.isLoading = false;
            }
        }
        
        filterAppointmentData(allData, doctorId, clinicId, treatmentType) {
            if (!allData?.calendars) {
                return null;
            }
            
            const matchingCalendar = allData.calendars.find(calendar => {
                const doctorMatch = String(calendar.doctor_id) === String(doctorId);
                const clinicMatch = String(calendar.clinic_id) === String(clinicId);
                const treatmentMatch = calendar.treatment_type === treatmentType;
                
                return doctorMatch && clinicMatch && treatmentMatch;
            });
            
            if (!matchingCalendar) {
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

            // Reset selected date to allow auto-selection of first active day
            this.core.selectedDate = null;

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

    // Export to global scope
    window.ClinicQueueDataManager = DataManager;

})(jQuery);
