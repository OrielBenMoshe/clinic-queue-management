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
         * Load free slots for multiple schedulers (used when treatment type is selected)
         * Collects all proxy_schedule_id from filtered schedulers and makes API call
         * 
         * @param {Array} filteredSchedulers Array of filtered schedulers
         * @param {string} treatmentType The selected treatment type
         */
        async loadFreeSlotsForMultipleSchedulers(filteredSchedulers, treatmentType) {
            if (!filteredSchedulers || filteredSchedulers.length === 0) {
                window.BookingCalendarUtils.log('No schedulers to load slots for');
                return;
            }

            try {
                // Collect all proxy_schedule_id from filtered schedulers
                const proxySchedulerIds = [];
                let duration = 30; // Default duration
                let durationFound = false;

                // First pass: collect proxy_schedule_id and find duration from matching treatment
                filteredSchedulers.forEach((scheduler) => {
                    const proxyScheduleId = scheduler.proxy_schedule_id || scheduler.proxy_scheduler_id;
                    if (proxyScheduleId) {
                        proxySchedulerIds.push(proxyScheduleId);
                        
                        // Try to get duration from scheduler's treatments matching the selected treatment type
                        // Only update duration if we haven't found it yet, or if we find a matching treatment
                        if (!durationFound && scheduler.treatments && Array.isArray(scheduler.treatments)) {
                            const matchingTreatment = scheduler.treatments.find(t => 
                                t.treatment_type && t.treatment_type.trim() === treatmentType.trim()
                            );
                            if (matchingTreatment && matchingTreatment.duration) {
                                duration = parseInt(matchingTreatment.duration, 10);
                                durationFound = true;
                                window.BookingCalendarUtils.log(`נמצא duration ${duration} דקות עבור סוג טיפול "${treatmentType}"`);
                            }
                        }
                    }
                });

                if (proxySchedulerIds.length === 0) {
                    window.BookingCalendarUtils.log('No proxy_schedule_id found in filtered schedulers');
                    return;
                }

                // Calculate date range: from now to 3 weeks ahead, end of day
                const now = new Date();
                const toDate = new Date();
                toDate.setDate(toDate.getDate() + 21); // 3 weeks = 21 days
                toDate.setHours(23, 59, 59, 999); // End of day

                // Convert to UTC format: YYYY-MM-DDTHH:mm:ssZ
                const fromDateUTC = this.formatDateUTC(now);
                const toDateUTC = this.formatDateUTC(toDate);

                // Build schedulerIDsStr - comma-separated list of proxy_schedule_id
                const schedulerIDsStr = proxySchedulerIds.join(',');

                const endpoint = `${this.apiBaseUrl}/scheduler/free-time`;
                const params = {
                    schedulerIDsStr: schedulerIDsStr,
                    duration: duration,
                    fromDateUTC: fromDateUTC,
                    toDateUTC: toDateUTC
                };

                window.BookingCalendarUtils.log('טעינת סלוטים עבור סוג טיפול דיפולטיבית:', {
                    treatmentType: treatmentType,
                    schedulersCount: filteredSchedulers.length,
                    proxySchedulerIds: proxySchedulerIds,
                    duration: duration,
                    durationFound: durationFound,
                    params: params
                });

                const response = await $.get(endpoint, params);

                window.BookingCalendarUtils.log('תוצאות מהפרוקסי API (סוג טיפול דיפולטיבית):', {
                    treatmentType: treatmentType,
                    schedulersCount: filteredSchedulers.length,
                    proxySchedulerIds: proxySchedulerIds,
                    response: response,
                    slotsCount: response && response.result ? response.result.length : 0
                });

                // Print detailed results to console
                if (response && response.result && Array.isArray(response.result)) {
                    console.group('📅 תוצאות פרוקסי API - סוג טיפול דיפולטיבית');
                    console.log('סוג טיפול:', treatmentType);
                    console.log('Duration:', duration, 'דקות', durationFound ? '(נמצא מהטיפול)' : '(ברירת מחדל)');
                    console.log('מספר יומנים:', filteredSchedulers.length);
                    console.log('מזהי פרוקסי:', proxySchedulerIds);
                    console.log('מספר סלוטים:', response.result.length);
                    console.log('סלוטים:', response.result);
                    console.log('Payload שנשלח:', params);
                    console.groupEnd();
                } else {
                    console.warn('⚠️ לא התקבלו תוצאות מהפרוקסי API');
                    console.log('Payload שנשלח:', params);
                }

            } catch (error) {
                window.BookingCalendarUtils.error('שגיאה בטעינת סלוטים עבור סוג טיפול דיפולטיבית:', error);
                console.error('שגיאה בטעינת סלוטים:', error);
            }
        }

        /**
         * Load free slots from API
         * Uses proxy_schedule_id, duration from selected treatment, and date range (3 weeks)
         */
        async loadFreeSlots() {
            if (this.core.isLoading) {
                window.BookingCalendarUtils.log('loadFreeSlots: already loading, skipping');
                return;
            }
            
            window.BookingCalendarUtils.log('loadFreeSlots: starting...');
            this.core.isLoading = true;
            
            // Show loading state in time-slots-container (if not already shown)
            const timeSlotsContainer = this.core.element.find('.time-slots-container');
            if (!timeSlotsContainer.find('.booking-calendar-loader').length) {
                this.core.showLoadingState();
            }
            
            try {
                // Get selected scheduler and treatment
                const schedulerField = this.core.element.find('.scheduler-field');
                const treatmentField = this.core.element.find('.treatment-field');
                
                window.BookingCalendarUtils.log('loadFreeSlots: schedulerField.length:', schedulerField.length, 'schedulerField.val():', schedulerField.val());
                window.BookingCalendarUtils.log('loadFreeSlots: core.schedulerId:', this.core.schedulerId);
                window.BookingCalendarUtils.log('loadFreeSlots: core.allSchedulers:', this.core.allSchedulers);
                
                let proxySchedulerId = null;
                let duration = 30; // Default duration
                
                // Try to get scheduler info from field (clinic mode) or from core instance (doctor mode)
                if (schedulerField.length && schedulerField.val()) {
                    // Clinic mode: get from selected option
                    window.BookingCalendarUtils.log('loadFreeSlots: Clinic mode - getting from field');
                    const selectedSchedulerOption = schedulerField.find('option:selected');
                    proxySchedulerId = selectedSchedulerOption.data('proxy-scheduler-id');
                    
                    // Get duration from scheduler option
                    const schedulerDuration = selectedSchedulerOption.data('duration');
                    if (schedulerDuration) {
                        duration = parseInt(schedulerDuration, 10);
                    }
                    window.BookingCalendarUtils.log('loadFreeSlots: Clinic mode - proxySchedulerId:', proxySchedulerId, 'duration:', duration);
                } else if (this.core.schedulerId) {
                    // Doctor mode: scheduler field doesn't exist, get from core instance
                    window.BookingCalendarUtils.log('loadFreeSlots: Doctor mode - getting from core instance, schedulerId:', this.core.schedulerId);
                    
                    // Convert allSchedulers to array if needed
                    let schedulersArray = [];
                    if (Array.isArray(this.core.allSchedulers)) {
                        schedulersArray = this.core.allSchedulers;
                    } else if (typeof this.core.allSchedulers === 'object' && this.core.allSchedulers !== null) {
                        schedulersArray = Object.values(this.core.allSchedulers);
                    }
                    
                    window.BookingCalendarUtils.log('loadFreeSlots: schedulersArray length:', schedulersArray.length);
                    
                    // Find the scheduler in allSchedulers by ID
                    const schedulerId = this.core.schedulerId;
                    const scheduler = schedulersArray.find(s => s.id == schedulerId || s.id === schedulerId || String(s.id) === String(schedulerId));
                    
                    window.BookingCalendarUtils.log('loadFreeSlots: found scheduler:', scheduler);
                    
                    if (scheduler) {
                        proxySchedulerId = scheduler.proxy_schedule_id || scheduler.proxy_scheduler_id;
                        window.BookingCalendarUtils.log('loadFreeSlots: Doctor mode - proxySchedulerId:', proxySchedulerId);
                        
                        // Get duration from scheduler's treatments matching current treatment type
                        if (scheduler.treatments && Array.isArray(scheduler.treatments) && this.core.treatmentType) {
                            const matchingTreatment = scheduler.treatments.find(t => 
                                t.treatment_type && t.treatment_type.trim() === this.core.treatmentType.trim()
                            );
                            if (matchingTreatment && matchingTreatment.duration) {
                                duration = parseInt(matchingTreatment.duration, 10);
                                window.BookingCalendarUtils.log('loadFreeSlots: found duration from treatment:', duration);
                            }
                        }
                    } else {
                        window.BookingCalendarUtils.error('loadFreeSlots: scheduler not found in allSchedulers for ID:', schedulerId);
                    }
                } else {
                    window.BookingCalendarUtils.log('loadFreeSlots: No scheduler field and no schedulerId in core');
                }
                
                if (!proxySchedulerId) {
                    window.BookingCalendarUtils.log('No scheduler selected or proxy_schedule_id not found');
                    this.core.appointmentData = [];
                    this.core.uiManager.showNoAppointmentsMessage();
                    return;
                }
                
                window.BookingCalendarUtils.log('loadFreeSlots: proceeding with proxySchedulerId:', proxySchedulerId, 'duration:', duration);
                
                // Calculate date range: from now to 3 weeks ahead, end of day
                const now = new Date();
                const toDate = new Date();
                toDate.setDate(toDate.getDate() + 21); // 3 weeks = 21 days
                toDate.setHours(23, 59, 59, 999); // End of day
                
                // Convert to UTC format: YYYY-MM-DDTHH:mm:ssZ
                const fromDateUTC = this.formatDateUTC(now);
                const toDateUTC = this.formatDateUTC(toDate);
                
                // Build schedulerIDsStr
                // According to requirements: if multiple schedulers found, pass all proxy_schedule_id separated by commas
                // For now, we use the selected scheduler (single selection)
                // If in the future we support multi-select, we would collect all proxy_schedule_id values
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
                    this.core.uiManager.showNoAppointmentsMessage();
                    return;
                }
                
                // Transform API data to internal format
                const processedData = this.processApiData(response.result);
                this.core.appointmentData = processedData;
                
                if (processedData.length === 0) {
                    this.core.uiManager.showNoAppointmentsMessage();
                    return;
                }
                
                window.BookingCalendarUtils.log('Data loaded successfully, rendering...');
                this.renderData();

            } catch (error) {
                window.BookingCalendarUtils.error('Failed to load appointment data:', error);
                this.core.appointmentData = [];
                this.core.uiManager.showNoAppointmentsMessage();
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

            // Hide loading message and loader
            this.core.element.find('.loading-message, .booking-calendar-loader').remove();

            // Always ensure containers are visible
            const container = this.core.element.find('.booking-calendar-shortcode');
            container.find('.days-carousel, .time-slots-container, .month-and-year').show();

            // Render calendar with data
            this.core.uiManager.renderDays();
            this.core.uiManager.renderCalendar();
            this.core.showContent();
        }

        /**
         * Filter schedulers by treatment type
         * 
         * Searches in the treatments repeater field of each scheduler.
         * Only schedulers that have the selected treatment type are returned.
         * 
         * @param {Array|Object} allSchedulers All schedulers to filter from
         * @param {string} treatmentType The treatment type to filter by
         * @return {Array} Array of filtered schedulers
         */
        filterSchedulersByTreatment(allSchedulers, treatmentType) {
            if (!allSchedulers || (Array.isArray(allSchedulers) && allSchedulers.length === 0) || 
                (!Array.isArray(allSchedulers) && Object.keys(allSchedulers).length === 0)) {
                return [];
            }
            
            const filtered = [];
            const normalizedTreatmentType = treatmentType.trim();
            
            // Convert object to array if needed
            const schedulersArray = Array.isArray(allSchedulers) 
                ? allSchedulers 
                : Object.values(allSchedulers);
            
            schedulersArray.forEach((scheduler, index) => {
                // Check if scheduler has treatments repeater
                if (!scheduler.treatments || !Array.isArray(scheduler.treatments)) {
                    return;
                }
                
                // Check if any treatment matches the treatment_type
                const hasTreatment = scheduler.treatments.some((treatment) => {
                    const treatmentTypeValue = treatment.treatment_type ? treatment.treatment_type.trim() : '';
                    return treatmentTypeValue === normalizedTreatmentType || 
                           treatmentTypeValue.toLowerCase() === normalizedTreatmentType.toLowerCase();
                });
                
                if (hasTreatment) {
                    window.BookingCalendarUtils.log(`  → יומן ${index + 1} תואם! מוסיף לרשימה`);
                    filtered.push(scheduler);
                }
            });
            
            return filtered;
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
