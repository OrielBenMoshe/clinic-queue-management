/**
 * Clinic Queue Management - UI Manager Module
 * Handles all UI operations and rendering
 */
(function($) {
    'use strict';
    
    // Custom easing function for carousel scrolling
    // Ease-out exponential: very fast start, dramatic slowdown at the end
    // Formula: starts at full speed, exponentially slows down
    $.easing.easeOutExpo = function(x, t, b, c, d) {
        if (t === 0) return b;
        if (t === d) return b + c;
        return c * (-Math.pow(2, -10 * t / d) + 1) + b;
    };

    // UI Manager - handles all UI operations
    class UIManager {
        constructor(core) {
            this.core = core;
            this.dataManager = null; // Will be set by init
        }

        renderCalendar() {
            window.BookingCalendarUtils.log('Rendering calendar...');
            
            if (!this.core.appointmentData || this.core.appointmentData.length === 0) {
                window.BookingCalendarUtils.log('No appointment data to render');
                return;
            }
            
            this.updateMonthTitle();
            this.renderDays();
            window.BookingCalendarUtils.log('Calendar rendered successfully');
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
                window.BookingCalendarUtils.log('Days container not found!');
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
            
            // Generate 21 days (3 weeks) starting from today
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const totalDays = 21; // 3 weeks
            
            // Find the first active day (with appointments)
            let firstActiveDate = null;
            for (let i = 0; i < totalDays; i++) {
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
            
            for (let i = 0; i < totalDays; i++) {
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
                    .toggleClass('disabled', !hasSlots)
                    // Prevent text selection
                    .css({
                        'user-select': 'none',
                        '-webkit-user-select': 'none',
                        '-moz-user-select': 'none',
                        '-ms-user-select': 'none',
                        '-webkit-user-drag': 'none',
                        'user-drag': 'none'
                    });

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
            
            // Initialize carousel navigation after rendering days
            this.initCarouselNavigation();
            
            // Auto-select the first active day and render its time slots
            this.renderTimeSlots();
        }
        
        /**
         * Initialize carousel navigation with arrow buttons
         */
        initCarouselNavigation() {
            const carousel = this.core.element.find('.days-carousel');
            const container = carousel.find('.days-container');
            const containerWrapper = carousel.find('.days-container-wrapper');
            const prevArrow = carousel.find('.days-carousel-arrow-prev');
            const nextArrow = carousel.find('.days-carousel-arrow-next');
            
            if (container.length === 0 || prevArrow.length === 0 || nextArrow.length === 0) {
                return;
            }
            
            // Calculate and set max-width for container wrapper to show exactly 6 days
            const setContainerWidth = () => {
                const firstDay = container.find('.day-tab').first();
                if (firstDay.length > 0) {
                    const dayWidth = firstDay.outerWidth();
                    const gap = parseInt(container.css('gap')) || 10;
                    // Width for 6 days: (dayWidth * 6) + (gap * 5) + padding (20px total)
                    const maxWidth = (dayWidth * 6) + (gap * 5) + 20;
                    containerWrapper.css('max-width', maxWidth + 'px');
                    window.BookingCalendarUtils.log('Set container max-width to:', maxWidth, 'for 6 days');
                }
            };
            
            // Set width after a short delay to ensure DOM is ready
            setTimeout(setContainerWidth, 50);
            
            // Update on window resize
            $(window).off('resize.booking-calendar-width').on('resize.booking-calendar-width', setContainerWidth);
            
            // Always show arrows, but disable them when at start/end
            prevArrow.css('display', 'flex');
            nextArrow.css('display', 'flex');
            
            // Update arrow state based on scroll position
            // In RTL: jQuery scrollLeft() starts at 0 (right side) and becomes negative when scrolling left
            const updateArrows = () => {
                const containerElement = container[0];
                const scrollLeft = container.scrollLeft(); // jQuery method - in RTL: 0 to negative
                const scrollWidth = containerElement.scrollWidth;
                const clientWidth = containerElement.clientWidth;
                // In RTL: maxScroll is negative: -(scrollWidth - clientWidth)
                const maxScroll = -(scrollWidth - clientWidth);
                
                // In RTL: At start (right side) = scrollLeft >= -1 (close to 0)
                //         At end (left side) = scrollLeft <= maxScroll + 1
                const isAtStart = scrollLeft >= -1; // Allow 1px tolerance (at right side/start)
                const isAtEnd = maxScroll >= -1 || scrollLeft <= maxScroll + 1; // -1 for rounding issues (at left side/end)
                
                // Disable/enable arrows based on scroll position
                if (isAtStart) {
                    prevArrow.prop('disabled', true).addClass('disabled');
                } else {
                    prevArrow.prop('disabled', false).removeClass('disabled');
                }
                
                if (isAtEnd) {
                    nextArrow.prop('disabled', true).addClass('disabled');
                } else {
                    nextArrow.prop('disabled', false).removeClass('disabled');
                }
            };
            
            // Calculate scroll amount for 6 days
            const calculateScrollAmount = () => {
                const firstDay = container.find('.day-tab').first();
                if (firstDay.length === 0) {
                    return 0;
                }
                // Get width of one day tab (day-content is 38px)
                const dayWidth = firstDay.outerWidth(); // width of day-tab element
                // Get gap from container (10px)
                const gap = parseInt(container.css('gap')) || 10;
                // Calculate width of 6 days: (dayWidth * 6) + (gap * 5) for gaps between 6 days
                const scrollAmount = (dayWidth * 6) + (gap * 5);
                window.BookingCalendarUtils.log('Scroll amount for 6 days:', scrollAmount, 'dayWidth:', dayWidth, 'gap:', gap);
                
                // Debug: Check if container can scroll
                const containerElement = container[0];
                if (containerElement) {
                    const canScroll = containerElement.scrollWidth > containerElement.clientWidth;
                    const currentScrollLeft = container.scrollLeft(); // Use jQuery method
                    window.BookingCalendarUtils.log('Container scroll info:', {
                        scrollWidth: containerElement.scrollWidth,
                        clientWidth: containerElement.clientWidth,
                        scrollLeft: currentScrollLeft,
                        canScroll: canScroll
                    });
                }
                
                return scrollAmount;
            };
            
            // Scroll handler - scroll 6 days at a time
            // In RTL: jQuery scrollLeft() starts at 0 (right side) and becomes negative when scrolling left
            // - prev arrow (left button) = scroll RIGHT (backward) = increase scrollLeft (closer to 0)
            // - next arrow (right button) = scroll LEFT (forward) = decrease scrollLeft (more negative)
            prevArrow.off('click').on('click', function() {
                if (!$(this).prop('disabled')) {
                    const scrollAmount = calculateScrollAmount();
                    const currentScroll = container.scrollLeft(); // jQuery method - handles RTL automatically
                    const containerElement = container[0];
                    
                    if (!containerElement) {
                        window.BookingCalendarUtils.error('Container element not found');
                        return;
                    }
                    
                    // In RTL: Prev arrow (left) = scroll RIGHT (backward) = increase scrollLeft (closer to 0)
                    const targetScroll = Math.min(0, currentScroll + scrollAmount);
                    
                    window.BookingCalendarUtils.log('Prev arrow (left) clicked - should scroll RIGHT - currentScroll:', currentScroll, 'targetScroll:', targetScroll);
                    
                    // Ensure container is scrollable
                    const computedStyle = window.getComputedStyle(containerElement);
                    if (computedStyle.overflowX === 'hidden' && computedStyle.overflow === 'hidden') {
                        window.BookingCalendarUtils.error('Container is not scrollable! overflow-x is hidden');
                        container.css('overflow-x', 'auto'); // Use jQuery instead of native style
                    }
                    
                    // Custom smooth scroll with easeOutExpo - starts fast, dramatic slowdown
                    // Using requestAnimationFrame for better control over easing
                    const startScroll = currentScroll;
                    const distance = targetScroll - startScroll;
                    const duration = 550; // milliseconds - slower for more comfortable feel
                    let startTime = null;
                    
                    // Custom easing: starts fast, begins slowing down earlier, smooth deceleration
                    // This creates a more gradual slowdown that starts before the end
                    const easeOutCustom = function(t) {
                        // t is 0-1, returns 0-1 with ease-out curve
                        // Uses a combination that starts slowing down around 30% of the way
                        if (t === 0) return 0;
                        if (t === 1) return 1;
                        // Blend between easeOutQuart (smoother) and easeOutExpo (dramatic)
                        // This creates gradual slowdown that starts earlier
                        const quart = 1 - Math.pow(1 - t, 4);
                        const expo = 1 - Math.pow(2, -10 * t);
                        // Weight: more quart early on, more expo at the end
                        return quart * 0.6 + expo * 0.4;
                    };
                    
                    const animateScroll = (currentTime) => {
                        if (startTime === null) {
                            startTime = currentTime;
                        }
                        
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        
                        // Apply custom easing - starts fast, slows down gradually before the end
                        const easedProgress = easeOutCustom(progress);
                        const currentScrollPos = startScroll + (distance * easedProgress);
                        
                        container.scrollLeft(currentScrollPos);
                        
                        if (progress < 1) {
                            requestAnimationFrame(animateScroll);
                        } else {
                            // Animation complete
                            const finalScroll = container.scrollLeft();
                            window.BookingCalendarUtils.log('After custom animate - scrollLeft:', finalScroll, 'expected:', targetScroll);
                        }
                    };
                    
                    requestAnimationFrame(animateScroll);
                    window.BookingCalendarUtils.log('Used custom requestAnimationFrame with gradual ease-out:', targetScroll);
                }
            });
            
            nextArrow.off('click').on('click', function() {
                if (!$(this).prop('disabled')) {
                    const scrollAmount = calculateScrollAmount();
                    const containerElement = container[0];
                    
                    if (!containerElement) {
                        window.BookingCalendarUtils.error('Container element not found');
                        return;
                    }
                    
                    const currentScroll = container.scrollLeft(); // jQuery method - handles RTL automatically
                    const scrollWidth = containerElement.scrollWidth;
                    const clientWidth = containerElement.clientWidth;
                    // In RTL: maxScroll is negative: -(scrollWidth - clientWidth)
                    const maxScroll = -(scrollWidth - clientWidth);
                    
                    window.BookingCalendarUtils.log('Next arrow (right) clicked - should scroll LEFT - currentScroll:', currentScroll, 'maxScroll:', maxScroll);
                    
                    // In RTL: Next arrow (right) = scroll LEFT (forward) = decrease scrollLeft (more negative)
                    const targetScroll = Math.max(maxScroll, currentScroll - scrollAmount);
                    
                    window.BookingCalendarUtils.log('Target scroll:', targetScroll, 'scrollAmount:', scrollAmount);
                    
                    // Ensure container is scrollable
                    const computedStyle = window.getComputedStyle(containerElement);
                    if (computedStyle.overflowX === 'hidden' && computedStyle.overflow === 'hidden') {
                        window.BookingCalendarUtils.error('Container is not scrollable! overflow-x is hidden');
                        container.css('overflow-x', 'auto'); // Use jQuery instead of native style
                    }
                    
                    // Custom smooth scroll with easeOutExpo - starts fast, dramatic slowdown
                    // Using requestAnimationFrame for better control over easing
                    const startScroll = currentScroll;
                    const distance = targetScroll - startScroll;
                    const duration = 550; // milliseconds - slower for more comfortable feel
                    let startTime = null;
                    
                    // Custom easing: starts fast, begins slowing down earlier, smooth deceleration
                    // This creates a more gradual slowdown that starts before the end
                    const easeOutCustom = function(t) {
                        // t is 0-1, returns 0-1 with ease-out curve
                        // Uses a combination that starts slowing down around 30% of the way
                        if (t === 0) return 0;
                        if (t === 1) return 1;
                        // Blend between easeOutQuart (smoother) and easeOutExpo (dramatic)
                        // This creates gradual slowdown that starts earlier
                        const quart = 1 - Math.pow(1 - t, 4);
                        const expo = 1 - Math.pow(2, -10 * t);
                        // Weight: more quart early on, more expo at the end
                        return quart * 0.6 + expo * 0.4;
                    };
                    
                    const animateScroll = (currentTime) => {
                        if (startTime === null) {
                            startTime = currentTime;
                        }
                        
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        
                        // Apply custom easing - starts fast, slows down gradually before the end
                        const easedProgress = easeOutCustom(progress);
                        const currentScrollPos = startScroll + (distance * easedProgress);
                        
                        container.scrollLeft(currentScrollPos);
                        
                        if (progress < 1) {
                            requestAnimationFrame(animateScroll);
                        } else {
                            // Animation complete
                            const finalScroll = container.scrollLeft();
                            window.BookingCalendarUtils.log('After custom animate - scrollLeft:', finalScroll, 'expected:', targetScroll);
                        }
                    };
                    
                    requestAnimationFrame(animateScroll);
                    window.BookingCalendarUtils.log('Used custom requestAnimationFrame with gradual ease-out:', targetScroll);
                }
            });
            
            // Update arrows on scroll
            container.off('scroll').on('scroll', updateArrows);
            
            // Enable drag scrolling on desktop
            this.initDragScrolling(container);
            
            // Initial update (with small delay to ensure DOM is ready)
            setTimeout(() => {
                updateArrows();
            }, 100);
            
            // Update on window resize
            $(window).off('resize.booking-calendar-carousel').on('resize.booking-calendar-carousel', updateArrows);
        }
        
        /**
         * Initialize drag scrolling for the carousel container
         * Allows users to drag the carousel on desktop
         * Distinguishes between clicking (for date selection) and dragging (for scrolling)
         */
        initDragScrolling(container) {
            let isDragging = false;
            let startX = 0;
            let scrollLeftStart = 0;
            let hasMoved = false;
            const dragThreshold = 5; // Minimum pixels to move before considering it a drag
            
            // Prevent text selection on container and day tabs
            container.css({
                'user-select': 'none',
                '-webkit-user-select': 'none',
                '-moz-user-select': 'none',
                '-ms-user-select': 'none'
            });
            
            container.find('.day-tab').css({
                'user-select': 'none',
                '-webkit-user-select': 'none',
                '-moz-user-select': 'none',
                '-ms-user-select': 'none',
                '-webkit-user-drag': 'none',
                'user-drag': 'none'
            });
            
            // Mouse down - start potential drag
            container.on('mousedown.booking-calendar-drag', function(e) {
                // Only allow drag on left mouse button
                if (e.button !== 0) {
                    return;
                }
                
                // Don't start drag if clicking on a day-tab (let click handler work)
                const $target = $(e.target);
                if ($target.closest('.day-tab:not(.disabled)').length > 0) {
                    // Allow click on day-tab, but still track movement for drag
                    isDragging = true;
                    startX = e.pageX;
                    scrollLeftStart = container.scrollLeft();
                    hasMoved = false;
                    return;
                }
                
                isDragging = true;
                startX = e.pageX;
                scrollLeftStart = container.scrollLeft();
                hasMoved = false;
                
                // Change cursor to grabbing
                container.css('cursor', 'grabbing');
                
                // Prevent default to avoid text selection
                e.preventDefault();
            });
            
            // Mouse move - drag the container
            $(document).on('mousemove.booking-calendar-drag', function(e) {
                if (!isDragging) {
                    return;
                }
                
                const currentX = e.pageX;
                const diffX = Math.abs(currentX - startX);
                
                // Check if mouse has moved enough to be considered a drag
                if (diffX > dragThreshold) {
                    hasMoved = true;
                    e.preventDefault();
                    
                    const walk = (currentX - startX) * 2; // Multiply by 2 for faster scrolling
                    const newScrollLeft = scrollLeftStart - walk;
                    
                    container.scrollLeft(newScrollLeft);
                }
            });
            
            // Mouse up - stop dragging
            $(document).on('mouseup.booking-calendar-drag', function(e) {
                if (isDragging) {
                    // If we moved during drag, prevent click event on day-tab
                    if (hasMoved) {
                        e.stopPropagation();
                        // Prevent click event from firing on day-tab
                        container.find('.day-tab').off('click.booking-calendar-drag-prevent');
                        container.find('.day-tab').on('click.booking-calendar-drag-prevent', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                        });
                        
                        // Remove the prevent click handler after a short delay
                        setTimeout(() => {
                            container.find('.day-tab').off('click.booking-calendar-drag-prevent');
                        }, 100);
                    }
                    
                    isDragging = false;
                    hasMoved = false;
                    container.css('cursor', 'grab');
                }
            });
            
            // Mouse leave - stop dragging if mouse leaves the window
            $(document).on('mouseleave.booking-calendar-drag', function() {
                if (isDragging) {
                    isDragging = false;
                    hasMoved = false;
                    container.css('cursor', 'grab');
                }
            });
        }

        selectDate(date) {
            window.BookingCalendarUtils.log('selectDate called with date:', date);
            
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
                
                window.BookingCalendarUtils.log('Date deselected:', date);

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
                
                if (selectedTab.length > 0) {
                    selectedTab.addClass('selected');
                } else {
                    window.BookingCalendarUtils.error('No tab found for date:', date);
                }

                this.renderTimeSlots();
                window.BookingCalendarUtils.log('Date selected:', date);
            }
            
            this.updateBookButtonState(); // Update button state after changing date
            this.core.showContent();
        }

        renderTimeSlots() {
            const timeSlotsContainer = this.core.element.find('.time-slots-container');
            const self = this;
            
            // Remove any existing loader
            timeSlotsContainer.find('.booking-calendar-loader').remove();
            
            // Helper function to bind events and update buttons
            const bindEventsAndUpdate = () => {
                // Add action buttons to bottom section (only if not already added)
                self.ensureActionButtons();
                
                // Update button state after rendering slots
                self.updateBookButtonState();
                
                // Bind click events for time slots
                // All slots are free/available (API only returns free slots)
                timeSlotsContainer.find('.time-slot-badge').on('click', (e) => {
                    const $slot = $(e.currentTarget);
                    const time = $slot.data('time');

                    // Toggle selection - only one can be selected at a time
                    self.selectTimeSlot(time);
                });
            };
            
            
            if (!this.core.selectedDate) {
                const html = `
                    <div style="text-align: center; padding: 40px 20px; color: #6c757d;">
                        <p style="margin: 0; font-size: 16px;">בחר תאריך כדי לראות תורים זמינים</p>
                    </div>
                `;
                
                // Check if there's existing content to fade out
                if (timeSlotsContainer.children().length > 0 && timeSlotsContainer.is(':visible')) {
                    timeSlotsContainer.fadeOut(200, () => {
                        timeSlotsContainer.html(html);
                        timeSlotsContainer.css({
                            'opacity': 0,
                            'display': 'block'
                        });
                        bindEventsAndUpdate();
                        timeSlotsContainer.animate({ opacity: 1 }, 300);
                    });
                } else {
                    // First load - show immediately
                    timeSlotsContainer.html(html);
                    timeSlotsContainer.css('display', 'block');
                    bindEventsAndUpdate();
                }
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
                const html = `
                    <div style="text-align: center; padding: 40px 20px; color: #856404; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; margin: 10px 0;">
                        <p style="margin: 0; font-size: 16px;">אין תורים זמינים בתאריך זה</p>
                    </div>
                `;
                
                // Check if there's existing content to fade out
                if (timeSlotsContainer.children().length > 0 && timeSlotsContainer.is(':visible')) {
                    timeSlotsContainer.fadeOut(200, () => {
                        timeSlotsContainer.html(html);
                        timeSlotsContainer.css({
                            'opacity': 0,
                            'display': 'block'
                        });
                        bindEventsAndUpdate();
                        timeSlotsContainer.animate({ opacity: 1 }, 300);
                    });
                } else {
                    // First load - show immediately
                    timeSlotsContainer.html(html);
                    timeSlotsContainer.css('display', 'block');
                    bindEventsAndUpdate();
                }
                return;
            }
            
            // Create time slots grid
            // All slots returned are free/available (API only returns free slots)
            // Note: selectedTime is reset when changing dates (in selectDate), so we don't need to preserve it
            const slotsHtml = dayData.time_slots.map(slot => {
                const slotTime = slot.time_slot || slot.time || slot.start_time || slot.appointment_time || '';
                const formattedTime = this.formatTimeForDisplay(slotTime);
                // Check if this slot should be selected (only if it matches the current selectedTime)
                const isSelected = this.core.selectedTime === slotTime;

                return `
                    <div class="time-slot-badge free${isSelected ? ' selected' : ''}" data-time="${slotTime}">
                        ${formattedTime}
                    </div>
                `;
            }).join('');
            
            const newContent = `
                <div class="time-slots-grid">
                    ${slotsHtml}
                </div>
            `;
            
            // Check if there's existing content to fade out
            if (timeSlotsContainer.children().length > 0 && timeSlotsContainer.is(':visible')) {
                // Fade out existing content, then fade in new content
                timeSlotsContainer.fadeOut(200, () => {
                    timeSlotsContainer.html(newContent);
                    // Ensure container is visible
                    timeSlotsContainer.css({
                        'opacity': 0,
                        'display': 'block'
                    });
                    // Bind events immediately (before fade-in animation)
                    bindEventsAndUpdate();
                    // Fade in the new content
                    timeSlotsContainer.animate({ opacity: 1 }, 300);
                });
            } else {
                // No existing content, just render with fade-in
                // For first load, show immediately without fade-in to avoid delay
                if (timeSlotsContainer.children().length === 0) {
                    timeSlotsContainer.html(newContent);
                    bindEventsAndUpdate();
                } else {
                    timeSlotsContainer.html(newContent);
                    // Ensure container is visible
                    timeSlotsContainer.css({
                        'opacity': 0,
                        'display': 'block'
                    });
                    // Bind events immediately (before fade-in animation)
                    bindEventsAndUpdate();
                    // Fade in the new content
                    timeSlotsContainer.animate({ opacity: 1 }, 300);
                }
            }
        }
        
        selectTimeSlot(time) {
            // Check if the same time slot is already selected
            if (this.core.selectedTime === time) {
                // Deselect the time slot
                this.core.selectedTime = null;
                this.core.element.find('.time-slot-badge').removeClass('selected');
                window.BookingCalendarUtils.log('Time slot deselected:', time);
            } else {
                // Select the new time slot
                this.core.selectedTime = time;
                
                // Update selection in time slots
                this.core.element.find('.time-slot-badge').removeClass('selected');
                this.core.element.find(`.time-slot-badge[data-time="${time}"]`).addClass('selected');
                
                window.BookingCalendarUtils.log('Time slot selected:', time);
            }
            
            // Update button state
            this.updateBookButtonState();
            
            // Focus on the book button after a short delay to ensure it's enabled (only if a slot is selected)
            if (this.core.selectedTime) {
                setTimeout(() => {
                    const bookButton = this.core.element.find('.ap-book-btn');
                    if (bookButton.length > 0 && !bookButton.prop('disabled')) {
                        bookButton.focus();
                        window.BookingCalendarUtils.log('Focused on book button');
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

        /**
         * Show loading state
         */
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

        /**
         * Show no appointments message
         */
        showNoAppointmentsMessage() {
            // הסר הודעות טעינה ולואדר
            this.core.element.find('.loading-message, .booking-calendar-loader').remove();
            
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

        /**
         * Render empty days (when no appointments available)
         */
        renderEmptyDays() {
            const daysContainer = this.core.element.find('.days-container');
            daysContainer.empty();
            
            // Use the same Hebrew day abbreviations as renderDays()
            const hebrewDayAbbrev = {
                'Sunday': 'א׳',
                'Monday': 'ב׳',
                'Tuesday': 'ג׳',
                'Wednesday': 'ד׳',
                'Thursday': 'ה׳',
                'Friday': 'ו׳',
                'Saturday': 'ש׳'
            };
            const today = new Date();
            const totalDays = 21; // 3 weeks
            
            for (let i = 0; i < totalDays; i++) {
                const currentDay = new Date(today);
                currentDay.setDate(today.getDate() + i);
                const dayNumber = currentDay.getDate();
                const dayName = currentDay.toLocaleDateString('en-US', { weekday: 'long' });
                const dayAbbrev = hebrewDayAbbrev[dayName] || dayName;
                
                const dayTab = $('<div>')
                    .addClass('day-tab disabled')
                    .css({
                        'pointer-events': 'none',
                        'user-select': 'none',
                        '-webkit-user-select': 'none',
                        '-moz-user-select': 'none',
                        '-ms-user-select': 'none',
                        '-webkit-user-drag': 'none',
                        'user-drag': 'none'
                    });
                
                dayTab.html(`
                    <div class="day-abbrev">${dayAbbrev}</div>
                    <div class="day-content">
                        <div class="day-number">${dayNumber}</div>
                        <div class="day-slots-count">0</div>
                    </div>
                `);
                
                daysContainer.append(dayTab);
            }
            
            // Initialize carousel navigation for empty days too
            this.initCarouselNavigation();
        }

    }

    // Export to global scope
    window.BookingCalendarUIManager = UIManager;

})(jQuery);
