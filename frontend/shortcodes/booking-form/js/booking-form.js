/**
 * Booking Form JavaScript
 * Handles form submission and family list refresh (family management snippet popup)
 * Uses jQuery — $ is jQuery inside the IIFE
 */

(function($) {
    'use strict';

    /**
     * Validates an Israeli ID number (checksum digit).
     *
     * @param {string} id
     * @returns {boolean}
     */
    function isValidIsraeliIdNumber(id) {
        const digits = String(id || '').replace(/\D+/g, '');
        if (digits === '' || digits.length > 9) {
            return false;
        }

        const normalized = digits.padStart(9, '0');
        let sum = 0;

        for (let i = 0; i < 9; i++) {
            let step = parseInt(normalized[i], 10) * ((i % 2) + 1);
            if (step > 9) {
                step -= 9;
            }
            sum += step;
        }

        return sum % 10 === 0;
    }

    /**
     * Booking Form Manager
     */
    class BookingFormManager {
        constructor() {
            this.form = null;
            this.messageBox = null;
            this.submitBtn = null;
            
            this.init();
        }

        init() {
            // Wait for DOM
            $(document).ready(() => {
                this.form = $('#ajax-booking-form');
                this.messageBox = $('#booking-message');
                this.submitBtn = $('#submit-btn');
                
                // Fill form from URL parameters
                this.fillFormFromQueryParams();

                if (window.ClinicQueueFloatingLabels && typeof window.ClinicQueueFloatingLabels.init === 'function') {
                    window.ClinicQueueFloatingLabels.init(this.form);
                }

                this.bindEvents();
                this.initMobileFloatingCta();
            });
        }

        /**
         * Mobile: sticky "Book appointment" button at the bottom until scrolled to its natural position in the form.
         */
        initMobileFloatingCta() {
            const $wrapper = this.form.closest('.booking-form-wrapper');
            if (!this.form.length || !$wrapper.length || $wrapper.hasClass('clinic-queue-booking--register-gate')) {
                return;
            }

            const $anchor = this.form.find('.booking-form-submit-bar-anchor');
            const $bar = this.form.find('.booking-form-submit-bar');
            if (!$anchor.length || !$bar.length) {
                return;
            }

            const mq = window.matchMedia('(max-width: 768px)');
            let rafId = 0;

            const applyFloatingState = () => {
                rafId = 0;
                if (!mq.matches) {
                    $bar.removeClass('booking-form-submit-bar--floating');
                    this.form.removeClass('booking-form--cta-floating');
                    return;
                }
                const anchorTop = $anchor[0].getBoundingClientRect().top;
                const shouldFloat = anchorTop > window.innerHeight;
                $bar.toggleClass('booking-form-submit-bar--floating', shouldFloat);
                this.form.toggleClass('booking-form--cta-floating', shouldFloat);
            };

            const schedule = () => {
                if (rafId) {
                    return;
                }
                rafId = window.requestAnimationFrame(applyFloatingState);
            };

            this._scheduleFloatingCta = schedule;

            applyFloatingState();

            $(window).on(
                'scroll.bookingFormFloatingCta resize.bookingFormFloatingCta orientationchange.bookingFormFloatingCta',
                schedule
            );

            if (typeof mq.addEventListener === 'function') {
                mq.addEventListener('change', applyFloatingState);
            } else if (typeof mq.addListener === 'function') {
                mq.addListener(applyFloatingState);
            }
        }
        
        /**
         * Fill form fields from URL query parameters
         */
        fillFormFromQueryParams() {
            if (!this.form.length) {
                return;
            }
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Fill date field
            const dateParam = urlParams.get('date');
            if (dateParam) {
                const dateField = this.form.find('#appt_date');
                if (dateField.length && !dateField.val()) {
                    dateField.val(dateParam);
                }
            }
            
            // Fill time field
            const timeParam = urlParams.get('time');
            if (timeParam) {
                const timeField = this.form.find('#appt_time');
                if (timeField.length && !timeField.val()) {
                    timeField.val(timeParam);
                }
            }
        }

        bindEvents() {
            // Form submission
            if (this.form.length) {
                this.form.on('submit', (e) => {
                    this.handleFormSubmit(e);
                });
            }

            // Add family member — popup from family management snippet (class trigger-add-member)
            // Refresh patient radios after save: family-member-saved event or save_family_member_ajax AJAX
            $(document).on('family-member-saved', () => {
                this.handleFamilyMemberSaved('family_member_saved_event');
            });

            $(document).ajaxComplete((event, xhr, settings) => {
                if (!this.isFamilyMemberSaveAjax(settings)) {
                    return;
                }

                try {
                    const json = JSON.parse(xhr.responseText);
                    if (json && json.success === true) {
                        this.handleFamilyMemberSaved('save_family_member_ajax');
                    }
                } catch (parseError) {
                    this.logBookingDebug('family_member_save_non_json', {
                        status: xhr.status,
                        preview: String(xhr.responseText || '').substring(0, 300),
                    });
                }
            });
        }

        handleFormSubmit(e) {
            e.preventDefault();
            
            if (!this.form.length || !this.submitBtn.length) {
                return;
            }

            const formEl = this.form[0];
            if (formEl && typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
                if (typeof formEl.reportValidity === 'function') {
                    formEl.reportValidity();
                }
                return;
            }

            this.proceedWithBooking();
        }

        /**
         * Proceed with booking after verifying primary user ID (modal if needed).
         */
        proceedWithBooking() {
            if (this.shouldPromptForUserIdNumber()) {
                this.showIdCompletionModal(() => this.submitAppointmentForm());
                return;
            }

            this.submitAppointmentForm();
        }

        /**
         * Whether the primary user ID completion modal is required.
         *
         * @returns {boolean}
         */
        shouldPromptForUserIdNumber() {
            const selectedPatient = this.form.find('input[name="patient_select"]:checked').val() || 'self';
            const isSelf = selectedPatient === 'self';
            const hasValidId = Boolean(bookingFormData.hasValidUserIdNumber);

            return isSelf && !hasValidId;
        }

        /**
         * Debug log to console (ClinicQueueUtils or console).
         *
         * @param {string} label
         * @param {Object} payload
         */
        logBookingDebug(label, payload) {
            if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.log === 'function') {
                window.ClinicQueueUtils.log(label, payload);
                return;
            }

            console.log('[booking-form]', label, payload);
        }

        /**
         * User-facing Hebrew messages keyed by server error_code.
         *
         * @returns {Object<string, string>}
         */
        getBookingErrorMessageMap() {
            return {
                family_invalid_id_number:
                    'למטופל שנבחר יש תעודת זהות לא תקינה. אנא ערכו את פרטי בן המשפחה ועדכנו את מספר ת.ז.',
                family_missing_id_number:
                    'למטופל שנבחר חסרה תעודת זהות בפרופיל. אנא עדכנו את פרטי בן המשפחה והזינו מספר ת.ז.',
                family_missing_first_name:
                    'למטופל שנבחר חסר שם פרטי בפרופיל. אנא עדכנו את פרטי בן המשפחה.',
                family_missing_dob:
                    'למטופל שנבחר חסר תאריך לידה בפרופיל. אנא עדכנו את פרטי בן המשפחה.',
                family_invalid_dob:
                    'תאריך הלידה של בן המשפחה שנבחר אינו תקין. אנא עדכנו את הפרטים.',
                family_not_found:
                    'לא נמצאו פרטי המטופל שנבחר. אנא בחרו מטופל אחר או עדכנו את רשימת בני המשפחה.',
                missing_email:
                    'חסר אימייל תקין בפרופיל שלכם. אנא עדכנו את הפרטים האישיים לפני קביעת התור.',
                missing_primary_phone:
                    'חסר מספר טלפון ראשי בפרופיל שלכם. אנא עדכנו את הפרטים האישיים לפני קביעת התור.',
                invalid_datetime:
                    'תאריך או שעת התור אינם תקינים. אנא חזרו ליומן ובחרו תור מחדש.',
                slot_taken:
                    'מצטערים, התור שבחרתם כבר נתפס. אנא בחרו תור אחר.',
                slot_check_failed:
                    'לא ניתן לאמת את זמינות התור. אנא בחרו תור אחר.',
            };
        }

        /**
         * Resolve a user-facing booking error (message + optional action hint).
         *
         * @param {Object} payload data.data from the AJAX response
         * @returns {{message: string, hint: string|null, hintType: string|null}}
         */
        getBookingErrorUserMessage(payload) {
            if (!payload || typeof payload !== 'object') {
                return {
                    message: 'אירעה שגיאה. אנא נסו שוב.',
                    hint: null,
                    hintType: null,
                };
            }

            const code = payload.error_code ? String(payload.error_code) : '';
            const messageMap = this.getBookingErrorMessageMap();

            if (code && messageMap[code]) {
                return {
                    message: messageMap[code],
                    hint: this.getBookingErrorActionHint(code),
                    hintType: this.getBookingErrorHintType(code),
                };
            }

            if (payload.message && typeof payload.message === 'string' && payload.message.trim() !== '') {
                return {
                    message: payload.message.trim(),
                    hint: null,
                    hintType: null,
                };
            }

            return {
                message: 'אירעה שגיאה. אנא נסו שוב.',
                hint: null,
                hintType: null,
            };
        }

        /**
         * Optional action label shown below the inline error message.
         *
         * @param {string} errorCode
         * @returns {string|null}
         */
        getBookingErrorActionHint(errorCode) {
            if (errorCode.startsWith('family_')) {
                return 'עדכון פרטי בן משפחה';
            }

            if (errorCode === 'missing_email' || errorCode === 'missing_primary_phone') {
                return null;
            }

            return null;
        }

        /**
         * Hint action type for inline error CTA.
         *
         * @param {string} errorCode
         * @returns {string|null}
         */
        getBookingErrorHintType(errorCode) {
            if (errorCode.startsWith('family_')) {
                return 'family';
            }

            return null;
        }

        /**
         * Log technical booking error details for developers only.
         *
         * @param {Object} payload
         */
        logBookingErrorDetails(payload) {
            const debugPayload = {
                error_code: payload?.error_code || null,
                error_reason: payload?.error_reason || null,
                message: payload?.message || null,
                patient_select: payload?.patient_select || null,
                resolved_patient: payload?.resolved_patient || null,
                data_source: payload?.data_source || null,
            };

            this.logBookingDebug('booking_error_details', debugPayload);

            if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.error === 'function') {
                window.ClinicQueueUtils.error('booking_error_details', debugPayload);
                return;
            }

            console.error('[booking-form] booking_error_details', debugPayload);
        }

        /**
         * Build a user-facing error message from the server payload.
         *
         * @param {Object} payload data.data from the AJAX response
         * @returns {string}
         */
        formatBookingErrorMessage(payload) {
            return this.getBookingErrorUserMessage(payload).message;
        }

        /**
         * Hide the inline booking error banner.
         */
        hideBookingMessage() {
            if (!this.messageBox.length) {
                return;
            }

            this.messageBox.attr('hidden', 'hidden');
            this.messageBox.find('.booking-form-message__text').text('');
            this.messageBox.find('.booking-form-message__action').empty().attr('hidden', 'hidden');
        }

        /**
         * Show inline booking error with optional family edit action.
         *
         * @param {Object} payload
         */
        showInlineBookingError(payload) {
            if (!this.messageBox.length) {
                return;
            }

            const { message, hint, hintType } = this.getBookingErrorUserMessage(payload);
            this.logBookingErrorDetails(payload);

            this.messageBox
                .removeClass('msg-success')
                .addClass('msg-error');

            this.messageBox.find('.booking-form-message__text').text(message);

            const $action = this.messageBox.find('.booking-form-message__action');
            $action.empty();

            if (hint && hintType === 'family') {
                const $btn = $('<button>', {
                    type: 'button',
                    class: 'booking-form-message__action-btn',
                    text: hint,
                });
                $btn.on('click', (event) => {
                    event.preventDefault();
                    this.openFamilyMemberFromError();
                });
                $action.append($btn).removeAttr('hidden');
            } else {
                $action.attr('hidden', 'hidden');
            }

            this.messageBox.removeAttr('hidden');
            this.scrollToBookingMessage();
        }

        /**
         * Scroll the viewport to the inline booking error banner.
         */
        scrollToBookingMessage() {
            if (!this.messageBox.length || this.messageBox.prop('hidden')) {
                return;
            }

            const top = this.messageBox[0].getBoundingClientRect().top + window.pageYOffset - 24;
            window.scrollTo({
                top: Math.max(0, top),
                behavior: 'smooth',
            });
        }

        /**
         * Open family member edit popup when possible; otherwise open add-family flow.
         */
        openFamilyMemberFromError() {
            const selectedPatient = this.form.find('input[name="patient_select"]:checked').val() || '';
            const familyMatch = /^family_(\d+)$/.exec(selectedPatient);

            if (familyMatch) {
                const $editTrigger = $(`.action-edit[data-index="${familyMatch[1]}"]`);
                if ($editTrigger.length) {
                    $editTrigger.trigger('click');
                    return;
                }
            }

            this.form
                .closest('.booking-form-wrapper')
                .find('.trigger-add-member')
                .first()
                .trigger('click');
        }

        /**
         * Submit the booking form (AJAX).
         */
        submitAppointmentForm() {
            if (!this.form.length || !this.submitBtn.length) {
                return;
            }

            // Disable submit button
            this.submitBtn.prop('disabled', true);
            this.hideBookingMessage();

            const formData = new FormData(this.form[0]);

            fetch(bookingFormData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const payload = data.data || {};

                if (payload.missing_user_id_number) {
                    this.logBookingDebug('booking_validation_error', payload);
                    bookingFormData.hasValidUserIdNumber = false;
                    this.showIdCompletionModal(() => this.submitAppointmentForm());
                    return;
                }

                if (data.success) {
                    this.logBookingDebug('booking_success', payload);
                    this.showSuccessModal(payload);
                    return;
                }

                this.logBookingDebug('booking_error', payload);

                // Handle responses by error type
                if (payload.slot_taken) {
                    this.logBookingErrorDetails(payload);
                    this.showModal({
                        type: 'error',
                        title: 'התור כבר תפוס',
                        message: this.getBookingErrorUserMessage(payload).message,
                        button: 'בחירת תור אחר',
                        onClose: () => {
                            const referrerUrl = this.getReferrerUrl();
                            if (referrerUrl) {
                                window.location.href = referrerUrl;
                            }
                        }
                    });
                } else if (payload.proxy_error) {
                    const cleanMessage = this.parseProxyErrorMessage(payload.message);
                    this.logBookingErrorDetails(payload);
                    this.showModal({
                        type: 'error',
                        title: 'שגיאה ביצירת התור',
                        message: cleanMessage,
                        button: 'חזרה ליומן',
                        onClose: () => {
                            const referrerUrl = this.getReferrerUrl();
                            if (referrerUrl) {
                                window.location.href = referrerUrl;
                            }
                        }
                    });
                } else {
                    this.showInlineBookingError(payload);
                }
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                this.showInlineBookingError({
                    message: 'אירעה שגיאה בשליחת הטופס. אנא נסו שוב.',
                });
            })
            .finally(() => {
                this.submitBtn.prop('disabled', false);
                this.submitBtn.html('קבע את התור <span class="loader" style="display:none;">⌛</span>');
            });
        }

        /**
         * ID number completion modal for the primary user.
         *
         * @param {Function} onSuccess Callback after successful save
         */
        showIdCompletionModal(onSuccess) {
            $('.clinic-queue-booking-id-overlay').remove();

            const $template = $('#clinic-queue-booking-id-modal-tpl');
            if (!$template.length) {
                return;
            }

            const i18n = bookingFormData.i18n || {};
            const $overlay = $($template.html().trim());
            const $input = $overlay.find('.clinic-queue-booking-id-modal__input');
            const $error = $overlay.find('.clinic-queue-booking-id-modal__error');
            const $saveBtn = $overlay.find('.clinic-queue-booking-id-modal__btn--save');
            const saveBtnDefaultText = $saveBtn.text();

            const hideError = () => {
                $error.attr('hidden', 'hidden').text('');
                $input.removeClass('is-invalid');
            };

            const showError = (message) => {
                $error.text(message).removeAttr('hidden');
                $input.addClass('is-invalid');
            };

            const closeModal = () => {
                $overlay.fadeOut(200, function() {
                    $(this).remove();
                });
            };

            const saveIdNumber = () => {
                const rawValue = ($input.val() || '').trim();
                hideError();

                if (!isValidIsraeliIdNumber(rawValue)) {
                    showError(i18n.idModalInvalid || 'מספר תעודת זהות אינו תקין.');
                    return;
                }

                $saveBtn.prop('disabled', true).text(i18n.idModalSaving || 'שומר...');

                const body = new FormData();
                body.append('action', 'save_user_id_number');
                body.append('security', bookingFormData.nonce);
                body.append('user_id_number', rawValue);

                fetch(bookingFormData.ajaxUrl, {
                    method: 'POST',
                    body
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.success) {
                            showError(data.data?.message || i18n.idModalInvalid || 'מספר תעודת זהות אינו תקין.');
                            return;
                        }

                        bookingFormData.hasValidUserIdNumber = true;
                        closeModal();
                        if (typeof onSuccess === 'function') {
                            onSuccess();
                        }
                    })
                    .catch(() => {
                        showError(i18n.idModalInvalid || 'מספר תעודת זהות אינו תקין.');
                    })
                    .finally(() => {
                        $saveBtn.prop('disabled', false).text(saveBtnDefaultText);
                    });
            };

            $saveBtn.on('click', saveIdNumber);
            $input.on('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    saveIdNumber();
                }
            });

            $overlay.find('.clinic-queue-booking-id-modal__btn--close').on('click', closeModal);
            $overlay.on('click', (event) => {
                if (event.target === $overlay[0]) {
                    closeModal();
                }
            });

            $('body').append($overlay);
            $input.trigger('focus');
        }
        
        /**
         * Success modal after booking (Figma design).
         *
         * @param {Object} payload Appointment data from the server
         */
        showSuccessModal(payload) {
            $('.booking-modal-overlay').remove();

            const $template = $('#clinic-queue-booking-success-modal-tpl');
            if (!$template.length) {
                this.showModal({
                    type: 'success',
                    title: 'התור נקבע בהצלחה!',
                    message: payload.message || '',
                    button: 'סגור',
                    onClose: () => this.redirectAfterSuccessClose()
                });
                return;
            }

            const $overlay = $($template.html().trim());
            const assets = bookingFormData.assets || {};
            const i18n = bookingFormData.i18n || {};

            $overlay.find('.clinic-queue-booking-success-modal__confetti-img').attr('src', assets.confetti || '');
            $overlay.find('.clinic-queue-booking-success-modal__check-icon').attr('src', assets.successIcon || '');

            const doctorName = (payload.doctor_name || '').trim();
            const $title = $overlay.find('.clinic-queue-booking-success-modal__title');
            const $prefix = $overlay.find('.clinic-queue-booking-success-modal__title-prefix');
            const $doctorSpan = $overlay.find('.clinic-queue-booking-success-modal__doctor-name');
            const $suffix = $overlay.find('.clinic-queue-booking-success-modal__title-suffix');

            if (doctorName) {
                $prefix.text(i18n.titlePrefix || 'התור ל:');
                $doctorSpan.text(doctorName);
                $suffix.text(i18n.titleSuffix || ' נקבע בהצלחה!');
            } else {
                $prefix.text('');
                $doctorSpan.text('');
                $suffix.text('התור נקבע בהצלחה!');
            }

            const dateDisplay = (payload.appt_date_display || payload.appt_date || '').trim();
            const timeDisplay = (payload.appt_time || '').trim();
            let datetimeText = '';
            if (dateDisplay && timeDisplay) {
                datetimeText = `${dateDisplay} | ${timeDisplay}`;
            } else if (dateDisplay) {
                datetimeText = dateDisplay;
            } else if (timeDisplay) {
                datetimeText = timeDisplay;
            }
            $overlay.find('.clinic-queue-booking-success-modal__datetime').text(datetimeText);

            const location = (payload.clinic_location || '').trim();
            const $locationRow = $overlay.find('.clinic-queue-booking-success-modal__location');
            if (location) {
                $locationRow.find('.clinic-queue-booking-success-modal__location-text').text(location);
                $locationRow.removeClass('is-hidden');
            } else {
                $locationRow.addClass('is-hidden');
            }

            $('body').append($overlay);

            const calendarUrl = this.buildGoogleCalendarUrl(payload);

            $overlay.find('.clinic-queue-booking-success-modal__btn--calendar').on('click', () => {
                if (calendarUrl) {
                    window.open(calendarUrl, '_blank', 'noopener,noreferrer');
                }
            });

            $overlay.find('.clinic-queue-booking-success-modal__btn--close').on('click', () => {
                this.redirectAfterSuccessClose();
            });

            $overlay.on('click', (e) => {
                if (e.target === $overlay[0]) {
                    this.redirectAfterSuccessClose();
                }
            });
        }

        /**
         * Redirect to page 2907 on "Close" — without closing the modal (page navigates immediately).
         */
        redirectAfterSuccessClose() {
            const redirectUrl = bookingFormData.closeRedirectUrl;
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }

            this.closeModal();
        }

        /**
         * Build a Google Calendar link (no OAuth).
         *
         * @param {Object} payload Appointment data
         * @returns {string}
         */
        buildGoogleCalendarUrl(payload) {
            const start = this.parseAppointmentStart(payload.appt_date, payload.appt_time);
            if (!start || Number.isNaN(start.getTime())) {
                return '';
            }

            const durationMinutes = Math.max(15, parseInt(payload.duration, 10) || 30);
            const end = new Date(start.getTime() + durationMinutes * 60000);

            const doctorName = (payload.doctor_name || '').trim();
            const titleTemplate = (bookingFormData.i18n && bookingFormData.i18n.calendarEventTitle) || 'תור אצל %s';
            const title = doctorName
                ? titleTemplate.replace('%s', doctorName)
                : 'תור במרפאה';

            const detailsParts = [];
            if (payload.patient_name) {
                detailsParts.push(`מטופל: ${payload.patient_name}`);
            }
            if (payload.treatment_type) {
                detailsParts.push(`סוג טיפול: ${payload.treatment_type}`);
            }
            if (payload.notes) {
                detailsParts.push(`הערות: ${payload.notes}`);
            }

            const params = new URLSearchParams({
                action: 'TEMPLATE',
                text: title,
                dates: `${this.formatGoogleCalendarDate(start)}/${this.formatGoogleCalendarDate(end)}`,
                details: detailsParts.join('\n'),
                location: (payload.clinic_location || '').trim()
            });

            return `https://calendar.google.com/calendar/render?${params.toString()}`;
        }

        /**
         * Format date/time for Google Calendar (local time).
         *
         * @param {Date} date
         * @returns {string}
         */
        formatGoogleCalendarDate(date) {
            const pad = (value) => String(value).padStart(2, '0');
            return (
                `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}` +
                `T${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`
            );
        }

        /**
         * Parse appointment date and time into a local Date.
         *
         * @param {string} apptDate Y-m-d or d/m/Y
         * @param {string} apptTime HH:mm
         * @returns {Date|null}
         */
        parseAppointmentStart(apptDate, apptTime) {
            const dateStr = (apptDate || '').trim();
            const timeStr = (apptTime || '').trim();
            if (!dateStr || !timeStr) {
                return null;
            }

            let year;
            let month;
            let day;

            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                [year, month, day] = dateStr.split('-').map(Number);
            } else if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
                const parts = dateStr.split('/').map(Number);
                day = parts[0];
                month = parts[1];
                year = parts[2];
            } else {
                return null;
            }

            const timeMatch = timeStr.match(/^(\d{1,2}):(\d{2})/);
            if (!timeMatch) {
                return null;
            }

            return new Date(year, month - 1, day, parseInt(timeMatch[1], 10), parseInt(timeMatch[2], 10), 0);
        }

        /**
         * Extract a clean error message from a proxy error response.
         *
         * @param {string} rawMessage Raw message from the server
         * @returns {string} Clean message
         */
        parseProxyErrorMessage(rawMessage) {
            if (!rawMessage || typeof rawMessage !== 'string') {
                return 'שגיאה ביצירת התור. אנא נסה שנית.';
            }

            // First attempt: extract text after "Got error from drweb: "
            // This is the cleanest and most common format
            const drwebMatch = rawMessage.match(/Got error from drweb:\s*(.+?)(?:\s*$)/);
            if (drwebMatch && drwebMatch[1]) {
                return drwebMatch[1].trim();
            }

            // Second attempt: extract from embedded JSON ({\"Code\":11,\"Error\":\"message\"})
            // Looks for the Error field inside embedded JSON
            const errorFieldMatch = rawMessage.match(/\\"Error\\":\\"([^"\\]+)\\"/);
            if (errorFieldMatch && errorFieldMatch[1]) {
                // Remove escape characters
                return errorFieldMatch[1].replace(/\\"/g, '"').replace(/\\\\/g, '\\');
            }

            // Third attempt: JSON with standard escaping
            const jsonMatch = rawMessage.match(/\{"Code":\d+,"Error":"([^"]+)"\}/);
            if (jsonMatch && jsonMatch[1]) {
                return jsonMatch[1];
            }

            // Fallback: show a generic message
            return 'שגיאה ביצירת התור. אנא נסה שנית.';
        }

        /**
         * Show modal popup (errors).
         * 
         * @param {Object} options Modal options
         * @param {string} options.type Modal type ('success' or 'error')
         * @param {string} options.title Modal title
         * @param {string} options.message Modal message
         * @param {string|null} options.button Button text (null for no button)
         * @param {Function|null} options.onClose Callback when modal closes
         * @param {number|null} options.autoClose Auto close after milliseconds
         */
        showModal({ type, title, message, button, onClose, autoClose }) {
            $('.booking-modal-overlay').not('.clinic-queue-booking-id-overlay').remove();
            
            // Create modal HTML
            const icon = type === 'success' ? '✓' : '✗';
            const modalHtml = `
                <div class="booking-modal-overlay">
                    <div class="booking-modal booking-modal--${type}">
                        <div class="booking-modal__icon">${icon}</div>
                        <div class="booking-modal__title">${title}</div>
                        <div class="booking-modal__message">${message}</div>
                        ${button ? `<button class="booking-modal__button">${button}</button>` : ''}
                    </div>
                </div>
            `;
            
            // Append to body
            $('body').append(modalHtml);
            
            const $overlay = $('.booking-modal-overlay');
            const $modal = $('.booking-modal');
            
            // Handle button click
            if (button) {
                $modal.find('.booking-modal__button').on('click', () => {
                    this.closeModal(onClose);
                });
            }
            
            // Handle overlay click (close on click outside)
            $overlay.on('click', (e) => {
                if (e.target === $overlay[0]) {
                    this.closeModal(onClose);
                }
            });
            
            // Auto close if specified
            if (autoClose) {
                setTimeout(() => {
                    this.closeModal(onClose);
                }, autoClose);
            }
        }
        
        /**
         * Close modal
         * 
         * @param {Function|null} onClose Callback
         */
        closeModal(onClose) {
            $('.booking-modal-overlay').not('.clinic-queue-booking-id-overlay').fadeOut(300, function() {
                $(this).remove();
                if (onClose && typeof onClose === 'function') {
                    onClose();
                }
            });
        }
        
        /**
         * Get referrer URL from form or URL params
         * 
         * @returns {string|null}
         */
        getReferrerUrl() {
            // Try to get from form field first
            const formField = this.form.find('#referrer_url');
            if (formField.length && formField.val()) {
                return formField.val();
            }
            
            // Fallback to URL params
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('referrer_url') || null;
        }

        /**
         * Whether the AJAX request is a family member save from the snippet (family management).
         *
         * @param {Object} settings jQuery ajax settings
         * @returns {boolean}
         */
        isFamilyMemberSaveAjax(settings) {
            if (!this.form.length) {
                return false;
            }

            const rawData = settings.data;
            let payload = '';

            if (typeof rawData === 'string') {
                payload = rawData;
            } else if (rawData && typeof rawData === 'object') {
                payload = $.param(rawData);
            }

            return payload.indexOf('action=save_family_member_ajax') !== -1;
        }

        /**
         * After family member save — refresh patient list (debounced).
         * Success feedback stays in the snippet popup only; popup close is handled there.
         *
         * @param {string} source
         */
        handleFamilyMemberSaved(source) {
            const now = Date.now();
            if (this._lastFamilyMemberSaved && (now - this._lastFamilyMemberSaved) < 1500) {
                return;
            }
            this._lastFamilyMemberSaved = now;
            this.logBookingDebug('family_member_saved', { source });
            this.hideBookingMessage();
            this.refreshFamilyList();
        }

        /**
         * Refresh "Who is the appointment for?" radios after adding a family member.
         */
        refreshFamilyList() {
            $.ajax({
                url: bookingFormData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'refresh_family_list_html',
                },
                success: (response) => {
                    if (response.success) {
                        $('#patients-list-container').html(response.data.html);
                        if (typeof this._scheduleFloatingCta === 'function') {
                            this._scheduleFloatingCta();
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error refreshing family list:', error);
                },
            });
        }
    }

    // Initialize
    $(document).ready(function() {
        new BookingFormManager();
    });

})(jQuery);
