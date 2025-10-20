/**
 * Clinic Queue Management JavaScript
 * Handles multiple widget instances on the same page
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

    class ClinicQueueWidget {
        constructor(element) {
            this.element = $(element);
            this.widgetId = this.element.attr('id');
            this.doctorId = this.element.data('doctor-id');
            this.clinicId = this.element.data('clinic-id');
            this.ctaLabel = this.element.data('cta-label');
            
            // Instance-specific state
            this.selectedDate = null;
            this.selectedTime = null;
            this.selectedClinic = null;
            this.currentMonth = new Date();
            this.appointmentData = null;
            this.isLoading = false;
            
            // Register this instance
            window.ClinicQueueManager.instances.set(this.widgetId, this);
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.loadAppointmentData();
        }
        
        bindEvents() {
            // Use namespaced events to prevent conflicts between instances
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            
            // Month navigation
            this.element.on(`click${eventNamespace}`, '.ap-prev-month', (e) => {
                e.preventDefault();
                this.changeMonth(-1);
            });
            
            this.element.on(`click${eventNamespace}`, '.ap-next-month', (e) => {
                e.preventDefault();
                this.changeMonth(1);
            });
            
            // Date selection
            this.element.on(`click${eventNamespace}`, '.ap-day-btn:not(.past):not(:disabled)', (e) => {
                e.preventDefault();
                const date = $(e.currentTarget).data('date');
                this.selectDate(date);
            });
            
            // Time selection
            this.element.on(`click${eventNamespace}`, '.ap-slot-btn:not(.unavailable)', (e) => {
                e.preventDefault();
                const time = $(e.currentTarget).data('time');
                this.selectTime(time);
            });
            
            // Clinic selection
            this.element.on(`change${eventNamespace}`, '.ap-clinic-select', (e) => {
                const clinicId = $(e.target).val();
                this.changeClinic(clinicId);
            });
            
            // Book appointment
            this.element.on(`click${eventNamespace}`, '.ap-book-btn', (e) => {
                e.preventDefault();
                this.bookAppointment();
            });
        }
        
        loadAppointmentData() {
            // Check if data is already loaded from PHP
            const preloadedData = window.clinicQueueData && window.clinicQueueData[this.widgetId];
            
            if (preloadedData) {
                this.appointmentData = preloadedData;
                this.populateClinicSelector();
                this.renderCalendar();
                this.showContent();
                return;
            }
            
            // Prevent multiple simultaneous loads for the same widget
            if (this.isLoading) {
                return;
            }
            
            this.isLoading = true;
            
            // Check cache first (shared between instances with same doctor/clinic)
            const cacheKey = `${this.doctorId}-${this.clinicId}`;
            const cachedData = window.ClinicQueueManager.globalSettings.sharedCache.get(cacheKey);
            
            if (cachedData && (Date.now() - cachedData.timestamp < 30000)) { // 30 second cache
                this.appointmentData = cachedData.data;
                this.populateClinicSelector();
                this.renderCalendar();
                this.showContent();
                this.isLoading = false;
                return;
            }
            
            // Use REST API
            const restUrl = `${window.location.origin}/wp-json/clinic-queue/v1/appointments`;
            const params = new URLSearchParams({
                doctor_id: this.doctorId,
                clinic_id: this.clinicId,
                treatment_type: this.element.data('treatment-type') || 'general'
            });
            
            $.get(`${restUrl}?${params}`)
                .done((response) => {
                    this.isLoading = false;
                    
                    if (response && response.doctor) {
                        this.appointmentData = response;
                        
                        // Cache the data for other instances
                        window.ClinicQueueManager.globalSettings.sharedCache.set(cacheKey, {
                            data: response,
                            timestamp: Date.now()
                        });
                        
                        this.populateClinicSelector();
                        this.renderCalendar();
                        this.showContent();
                        
                        // Notify other instances of the same doctor/clinic about the update
                        this.notifyOtherInstances(cacheKey, response);
                    } else {
                        this.showError('Failed to load appointment data');
                    }
                })
                .fail(() => {
                    this.isLoading = false;
                    this.showError('Network error occurred');
                });
        }
        
        notifyOtherInstances(cacheKey, data) {
            // Update other widget instances with the same doctor/clinic combination
            window.ClinicQueueManager.instances.forEach((instance, instanceId) => {
                if (instanceId !== this.widgetId && 
                    `${instance.doctorId}-${instance.clinicId}` === cacheKey) {
                    
                    // Update their data without making another AJAX call
                    instance.appointmentData = data;
                    if (!instance.isLoading) {
                        instance.populateClinicSelector();
                        instance.renderCalendar();
                        instance.showContent();
                    }
                }
            });
        }
        
        populateClinicSelector() {
            const clinicSelect = this.element.find('.ap-clinic-select');
            clinicSelect.empty();
            
            if (this.appointmentData.clinics) {
                this.appointmentData.clinics.forEach(clinic => {
                    const option = $('<option>')
                        .val(clinic.id)
                        .text(clinic.name);
                    
                    if (clinic.id === this.appointmentData.clinic.id) {
                        option.prop('selected', true);
                        this.selectedClinic = clinic.id;
                    }
                    
                    clinicSelect.append(option);
                });
            }
        }
        
        renderCalendar() {
            this.updateMonthTitle();
            this.renderDays();
        }
        
        updateMonthTitle() {
            const monthTitle = this.currentMonth.toLocaleDateString('he-IL', { 
                month: 'long', 
                year: 'numeric' 
            });
            this.element.find('.ap-month-title').text(monthTitle);
        }
        
        renderDays() {
            const daysContainer = this.element.find('.ap-days');
            daysContainer.empty();
            
            const days = this.generateDays();
            const currentDate = new Date();
            currentDate.setHours(0, 0, 0, 0);
            
            // Hebrew day names
            const hebrewDays = ['א', 'ב', 'ג', 'ד', 'ה', 'ו', 'ש'];
            
            days.forEach(day => {
                const dateStr = day.toISOString().split('T')[0];
                const dayData = this.appointmentData?.days?.find(d => d.date === dateStr);
                const isSelected = this.selectedDate === dateStr;
                const isCurrentMonth = day.getMonth() === this.currentMonth.getMonth();
                const isPast = day < currentDate;
                const slotCount = dayData?.slots?.length || 0;
                
                const dayButton = $('<button>')
                    .addClass('ap-day-btn')
                    .data('date', dateStr)
                    .prop('disabled', isPast);
                
                if (isSelected) dayButton.addClass('selected');
                if (!isCurrentMonth) dayButton.addClass('other-month');
                if (isPast) dayButton.addClass('past');
                
                // Day name (Hebrew)
                const dayName = $('<div>')
                    .addClass('ap-day-name')
                    .text(hebrewDays[day.getDay()]);
                
                // Day number
                const dayNumber = $('<div>')
                    .addClass('ap-day-number')
                    .text(day.getDate());
                
                dayButton.append(dayName);
                dayButton.append(dayNumber);
                
                if (slotCount > 0) {
                    const slotIndicator = $('<div>')
                        .addClass('ap-slot-count')
                        .text(slotCount);
                    dayButton.append(slotIndicator);
                }
                
                daysContainer.append(dayButton);
            });
        }
        
        generateDays() {
            const days = [];
            const firstDay = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth(), 1);
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
            this.selectedDate = date;
            this.selectedTime = null;
            
            // Update UI
            this.element.find('.ap-day-btn').removeClass('selected');
            this.element.find(`[data-date="${date}"]`).addClass('selected');
            
            this.renderTimeSlots();
            this.updateBookButton();
        }
        
        renderTimeSlots() {
            const timeSlotsContainer = this.element.find('.ap-time-slots');
            const slotsGrid = this.element.find('.ap-slots-grid');
            
            if (!this.selectedDate) {
                timeSlotsContainer.hide();
                return;
            }
            
            const dayData = this.appointmentData?.days?.find(d => d.date === this.selectedDate);
            const slots = dayData?.slots || [];
            
            slotsGrid.empty();
            
            slots.forEach(slot => {
                const slotButton = $('<button>')
                    .addClass('ap-slot-btn')
                    .data('time', slot.time)
                    .text(slot.time)
                    .prop('disabled', slot.booked);
                
                if (slot.booked) {
                    slotButton.addClass('unavailable');
                }
                
                slotsGrid.append(slotButton);
            });
            
            timeSlotsContainer.show();
        }
        
        selectTime(time) {
            this.selectedTime = time;
            
            // Update UI
            this.element.find('.ap-slot-btn').removeClass('selected');
            this.element.find(`[data-time="${time}"]`).addClass('selected');
            
            this.updateBookButton();
        }
        
        updateBookButton() {
            const bookButton = this.element.find('.ap-book-btn');
            const canBook = this.selectedDate && this.selectedTime;
            
            bookButton.prop('disabled', !canBook);
        }
        
        changeMonth(direction) {
            this.currentMonth.setMonth(this.currentMonth.getMonth() + direction);
            this.renderCalendar();
        }
        
        changeClinic(clinicId) {
            if (clinicId !== this.selectedClinic) {
                this.selectedClinic = clinicId;
                this.clinicId = clinicId;
                this.selectedDate = null;
                this.selectedTime = null;
                
                this.element.find('.ap-time-slots').hide();
                this.updateBookButton();
                
                this.loadAppointmentData();
            }
        }
        
        bookAppointment() {
            if (this.selectedDate && this.selectedTime) {
                // Get patient details (you can customize this)
                const patientName = prompt('שם המטופל:');
                const patientPhone = prompt('טלפון המטופל:');
                
                if (!patientName || !patientPhone) {
                    alert('נא למלא את כל הפרטים');
                    return;
                }
                
                // Send booking request to server
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'clinic_queue_book_appointment',
                        nonce: clinicQueueAjax.nonce,
                        doctor_id: this.doctorId,
                        clinic_id: this.clinicId,
                        treatment_type: this.element.data('treatment-type') || 'general',
                        date: this.selectedDate,
                        time: this.selectedTime,
                        patient_name: patientName,
                        patient_phone: patientPhone
                    },
                    success: (response) => {
                        if (response.success) {
                            alert('התור נקבע בהצלחה!');
                            
                            // Dispatch custom event for external integration
                            const event = new CustomEvent('clinic_queue:selected', {
                                detail: {
                                    widgetId: this.widgetId,
                                    date: this.selectedDate,
                                    slot: {
                                        time: this.selectedTime,
                                        id: this.selectedDate + 'T' + this.selectedTime
                                    },
                                    tz: this.appointmentData.timezone || 'Asia/Jerusalem',
                                    doctor: this.appointmentData.doctor,
                                    clinic: this.appointmentData.clinic,
                                    patient: {
                                        name: patientName,
                                        phone: patientPhone
                                    },
                                    instance: this
                                }
                            });
                            
                            window.dispatchEvent(event);
                            
                            // Refresh the widget data
                            this.loadAppointmentData();
                        } else {
                            alert('שגיאה: ' + response.data);
                        }
                    },
                    error: () => {
                        alert('שגיאה בחיבור לשרת');
                    }
                });
            }
        }
        
        // Method to cleanup when widget is removed
        destroy() {
            // Remove event listeners
            const eventNamespace = `.clinic-queue-${this.widgetId}`;
            this.element.off(eventNamespace);
            
            // Remove from global registry
            window.ClinicQueueManager.instances.delete(this.widgetId);
            
            // Clear any timeouts
            const timeoutKey = this.widgetId;
            if (window.ClinicQueueManager.globalSettings.loadingTimeouts.has(timeoutKey)) {
                clearTimeout(window.ClinicQueueManager.globalSettings.loadingTimeouts.get(timeoutKey));
                window.ClinicQueueManager.globalSettings.loadingTimeouts.delete(timeoutKey);
            }
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
    }

    // Initialize widgets when DOM is ready
    function initializeWidgets() {
        $('.ap-widget:not([data-initialized])').each(function() {
            // Mark as initialized to prevent double initialization
            $(this).attr('data-initialized', 'true');
            new ClinicQueueWidget(this);
        });
    }

    // Initialize on DOM ready
    $(document).ready(initializeWidgets);
    
    // Re-initialize if new widgets are added dynamically (e.g., via AJAX)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('ap-widget') && !$(e.target).attr('data-initialized')) {
            // Small delay to ensure the element is fully rendered
            setTimeout(() => {
                initializeWidgets();
            }, 100);
        }
    });

    // Global utility functions
    window.ClinicQueueManager.utils = {
        // Get specific widget instance
        getInstance: function(widgetId) {
            return window.ClinicQueueManager.instances.get(widgetId);
        },
        
        // Get all instances
        getAllInstances: function() {
            return Array.from(window.ClinicQueueManager.instances.values());
        },
        
        // Destroy specific instance
        destroyInstance: function(widgetId) {
            const instance = window.ClinicQueueManager.instances.get(widgetId);
            if (instance) {
                instance.destroy();
            }
        },
        
        // Clear all cache
        clearCache: function() {
            window.ClinicQueueManager.globalSettings.sharedCache.clear();
        },
        
        // Reinitialize all widgets (useful for dynamic content)
        reinitialize: function() {
            initializeWidgets();
        }
    };

    // Export for external use
    window.ClinicQueueWidget = ClinicQueueWidget;

})(jQuery);