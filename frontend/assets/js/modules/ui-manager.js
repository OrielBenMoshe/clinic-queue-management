/**
 * Clinic Queue Management - UI Manager Module
 * Handles all UI operations and rendering
 */
(function($) {
    'use strict';

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
            // Don't update month title if it's already showing a no-appointments message
            const currentTitle = this.core.element.find('.month-and-year').text();
            if (currentTitle === 'לא קיימים תורים ביומן זה') {
                return;
            }
            
            const monthTitle = this.core.currentMonth.toLocaleDateString('he-IL', { 
                month: 'long', 
                year: 'numeric' 
            });
            // Remove any existing space before year and add comma with space
            const monthTitleWithComma = monthTitle.replace(/\s*(\d{4})/, ', $1');
            // Update the h2 inside month-and-year
            this.core.element.find('.month-and-year').text(monthTitleWithComma);
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
            
            // Find the first active day (with appointments)
            let firstActiveDate = null;
            for (let i = 0; i < 6; i++) {
                const currentDay = new Date(today);
                currentDay.setDate(today.getDate() + i);
                const dateStr = currentDay.toISOString().split('T')[0];
                const appointment = appointmentsMap.get(dateStr);
                const hasSlots = appointment && appointment.time_slots && appointment.time_slots.length > 0;
                
                if (hasSlots && !firstActiveDate) {
                    firstActiveDate = dateStr;
                }
            }
            
            // If no active date found, don't select any date and show message
            if (!firstActiveDate) {
                this.core.selectedDate = null;
                // Update month title to show no appointments message
                this.core.element.find('.month-and-year').text('לא קיימים תורים ביומן זה');
            } else {
                // Update selected date if not already set or if current selection is not active
                if (!this.core.selectedDate || !appointmentsMap.has(this.core.selectedDate)) {
                    this.core.selectedDate = firstActiveDate;
                }
            }
            
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
                    .attr('data-date', dateStr)
                    .data('date', dateStr)
                    .toggleClass('selected', isSelected)
                    .toggleClass('disabled', !hasSlots);
                                
                if (isSelected) {
                    console.log('[ClinicQueue] Day tab is selected:', dateStr, dayTab.attr('class'));
                    console.log('[ClinicQueue] Selected date matches:', this.core.selectedDate === dateStr);
                }

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
            
            // Auto-select the first active day and render its time slots
            this.renderTimeSlots();
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
            console.log('[ClinicQueue] selectDate called with date:', date);
            
            // Check if the same date is already selected
            if (this.core.selectedDate === date) {
                // Deselect the date
                this.core.selectedDate = null;
                this.core.selectedTime = null;
                
                // Remove selection from day tabs
                const daysContainer = this.core.element.find('.days-container');
                daysContainer.find('.day-tab').removeClass('selected');
                
                // Clear time slots
                const timeSlotsContainer = this.core.element.find('.time-slots-container');
                timeSlotsContainer.empty();
                
                console.log('[ClinicQueue] Date deselected:', date);
            } else {
                // Select the new date
                this.core.selectedDate = date;
                this.core.selectedTime = null; // Reset selected time when changing date

                // Update selection in day tabs - search in the correct container
                const daysContainer = this.core.element.find('.days-container');
                daysContainer.find('.day-tab').removeClass('selected');
                
                // Try both attribute and data selectors
                let selectedTab = daysContainer.find(`.day-tab[data-date="${date}"]`);
                if (selectedTab.length === 0) {
                    selectedTab = daysContainer.find('.day-tab').filter(function() {
                        return $(this).data('date') === date;
                    });
                }
                
                console.log('[ClinicQueue] Found selected tab:', selectedTab.length, 'elements');
                console.log('[ClinicQueue] Selected tab element:', selectedTab);
                console.log('[ClinicQueue] Days container:', daysContainer);
                console.log('[ClinicQueue] Looking for date:', date);
                
                if (selectedTab.length > 0) {
                    selectedTab.addClass('selected');
                    console.log('[ClinicQueue] Added selected class. New classes:', selectedTab.attr('class'));
                } else {
                    console.log('[ClinicQueue] ERROR: No tab found for date:', date);
                    console.log('[ClinicQueue] Available tabs:', daysContainer.find('.day-tab').map(function() {
                        return $(this).data('date');
                    }).get());
                }

                this.renderTimeSlots();
                console.log('[ClinicQueue] Date selected:', date);
            }
            
            this.updateBookButtonState(); // Update button state after changing date
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
                this.updateBookButtonState();
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
                this.updateBookButtonState();
                return;
            }
            
            // Create time slots grid            
            const slotsHtml = dayData.time_slots.map(slot => {
                const slotTime = slot.time_slot || slot.time || slot.start_time || slot.appointment_time || '';
                const formattedTime = this.formatTimeForDisplay(slotTime);
                const isBooked = slot.is_booked === 1 || slot.is_booked === true || slot.booked || slot.status === 'booked';
                const slotClass = isBooked ? 'time-slot-badge booked' : 'time-slot-badge free';

                return `
                    <div class="${slotClass}" data-time="${slotTime}" ${isBooked ? 'data-disabled="true"' : ''}>
                        ${formattedTime}
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
            
            // Update button state after rendering slots
            this.updateBookButtonState();
            
            // Bind click events for time slots
            timeSlotsContainer.find('.time-slot-badge.free').on('click', (e) => {
                const $slot = $(e.currentTarget);
                const time = $slot.data('time');

                // Toggle selection - only one can be selected at a time
                this.selectTimeSlot(time);
            });
        }
        
        selectTimeSlot(time) {
            // Check if the same time slot is already selected
            if (this.core.selectedTime === time) {
                // Deselect the time slot
                this.core.selectedTime = null;
                this.core.element.find('.time-slot-badge').removeClass('selected');
                console.log('[ClinicQueue] Time slot deselected:', time);
            } else {
                // Select the new time slot
                this.core.selectedTime = time;
                
                // Update selection in time slots
                this.core.element.find('.time-slot-badge').removeClass('selected');
                this.core.element.find(`.time-slot-badge[data-time="${time}"]`).addClass('selected');
                
                console.log('[ClinicQueue] Time slot selected:', time);
            }
            
            // Update button state
            this.updateBookButtonState();
            
            // Focus on the book button after a short delay to ensure it's enabled (only if a slot is selected)
            if (this.core.selectedTime) {
                setTimeout(() => {
                    const bookButton = this.core.element.find('.ap-book-btn');
                    if (bookButton.length > 0 && !bookButton.prop('disabled')) {
                        bookButton.focus();
                        console.log('[ClinicQueue] Focused on book button');
                    }
                }, 100);
            }
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
                    <button type="button" class="btn btn-secondary ap-view-all-btn">
                        ${viewAllLabel}
                    </button>
                    <button type="button" class="btn btn-primary ap-book-btn disabled" disabled>
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
        
        updateBookButtonState() {
            const bookButton = this.core.element.find('.ap-book-btn');
            const hasSelection = this.core.selectedDate && this.core.selectedTime;
            
            if (bookButton.length > 0) {
                if (hasSelection) {
                    bookButton.prop('disabled', false).removeClass('disabled');
                } else {
                    bookButton.prop('disabled', true).addClass('disabled');
                }
            }
        }

        changeMonth(direction) {
            this.core.currentMonth.setMonth(this.core.currentMonth.getMonth() + direction);
            this.renderCalendar();
        }
        
        /**
         * Format time for display (HH:MM:SS -> HH:MM)
         */
        formatTimeForDisplay(timeString) {
            if (!timeString) return '';
            
            // If it's already in HH:MM format, return as is
            if (/^\d{1,2}:\d{2}$/.test(timeString)) {
                return timeString;
            }
            
            // If it's in HH:MM:SS format, remove seconds
            if (/^\d{1,2}:\d{2}:\d{2}$/.test(timeString)) {
                return timeString.substring(0, 5); // Remove last 3 characters (:SS)
            }
            
            // If it's a full datetime, extract time part
            if (timeString.includes('T')) {
                const timePart = timeString.split('T')[1];
                if (timePart) {
                    return timePart.substring(0, 5); // HH:MM
                }
            }
            
            // If it's a time with seconds, remove them
            if (timeString.includes(':')) {
                const parts = timeString.split(':');
                if (parts.length >= 2) {
                    return `${parts[0]}:${parts[1]}`;
                }
            }
            
            // Fallback: return as is
            return timeString;
        }
    }

    // Export to global scope
    window.ClinicQueueUIManager = UIManager;

})(jQuery);
