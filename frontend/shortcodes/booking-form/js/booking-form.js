/**
 * Booking Form JavaScript
 * Handles form submission and family list refresh
 * משתמש ב-jQuery – $ הוא jQuery בתוך ה-IIFE
 */

(function($) {
    'use strict';

    /**
     * ולידציה של תעודת זהות ישראלית (ספרת ביקורת).
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
            this.popupId = null;
            
            this.init();
        }

        init() {
            // Wait for DOM
            $(document).ready(() => {
                this.form = $('#ajax-booking-form');
                this.messageBox = $('#booking-message');
                this.submitBtn = $('#submit-btn');
                
                // Get popup ID from trigger (attr אמין יותר מ-data)
                const trigger = $('.add-patient-trigger');
                if (trigger.length) {
                    this.popupId = trigger.attr('data-popup-id');
                }
                
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
         * מובייל: כפתור "קבע את התור" דביק לתחתית המסך עד שגוללים עד למיקומו הטבעי בטופס.
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

            // Add patient – event delegation (עובד גם אם האלמנט נטען אחרי ready)
            $(document).on('click', '.add-patient-trigger', (e) => {
                e.preventDefault();
                this.popupId = $(e.currentTarget).attr('data-popup-id');
                this.openAddPatientPopup();
            });

            // JetFormBuilder — הוספת בן משפחה בפופאפ (לא טופס קביעת התור שלנו)
            $(document).on('jet-form-builder/ajax/on-success', (event, response, $form) => {
                if (!this.isFamilyPopupJetForm($form)) {
                    return;
                }
                this.handleFamilyMemberFormComplete('jfb_success');
            });

            $(document).on('jet-engine/form/on-ajax-success', (event, response, $form) => {
                if ($form && !this.isFamilyPopupJetForm($form)) {
                    return;
                }
                this.handleFamilyMemberFormComplete('jet_engine_success');
            });

            // Fallback: JFB מחזיר 500 אחרי שמירה — on-success לא נורה; onFail של JFB עלול לקרוס
            $(document).ajaxComplete((event, xhr, settings) => {
                if (!this.isFamilyMemberJetFormAjax(settings)) {
                    return;
                }

                if (xhr.status === 200) {
                    try {
                        const json = JSON.parse(xhr.responseText);
                        if (json && (json.status === 'success' || json.success === true)) {
                            this.handleFamilyMemberFormComplete('jfb_ajax_200');
                        }
                    } catch (parseError) {
                        this.logBookingDebug('family_form_non_json_response', {
                            status: xhr.status,
                            preview: String(xhr.responseText || '').substring(0, 300),
                        });
                    }
                    return;
                }

                if (xhr.status >= 500) {
                    this.logBookingDebug('family_form_server_error', {
                        status: xhr.status,
                        preview: String(xhr.responseText || '').substring(0, 500),
                    });
                    this.handleFamilyMemberFormComplete('jfb_server_error', { showPartialSaveNotice: true });
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
         * ממשיך לקביעת תור לאחר וידוא ת.ז. ליוזר ראשי (פופאפ במידת הצורך).
         */
        proceedWithBooking() {
            if (this.shouldPromptForUserIdNumber()) {
                this.showIdCompletionModal(() => this.submitAppointmentForm());
                return;
            }

            this.submitAppointmentForm();
        }

        /**
         * האם נדרש פופאפ השלמת ת.ז. ליוזר הראשי.
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
         * לוג דיבוג לקונסול (ClinicQueueUtils או console).
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
         * בניית הודעת שגיאה עם סיבה ופרטי מטופל מהשרת.
         *
         * @param {Object} payload data.data מתשובת AJAX
         * @returns {string}
         */
        formatBookingErrorMessage(payload) {
            if (!payload || typeof payload !== 'object') {
                return 'שגיאה';
            }

            const lines = [];
            const reason = payload.error_reason || payload.message;
            if (reason) {
                lines.push(reason);
            }

            if (payload.error_code) {
                lines.push(`קוד: ${payload.error_code}`);
            }

            const patient = payload.resolved_patient;
            if (patient) {
                const name = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim();
                if (name) {
                    lines.push(`מטופל מהשרת: ${name}`);
                }

                if (patient.identity_status === 'missing') {
                    lines.push('ת.ז. בפרופיל: חסרה (id_number / user_id_number)');
                } else if (patient.identity_status === 'invalid') {
                    lines.push(`ת.ז. בפרופיל: לא תקינה (${patient.identity || '—'})`);
                } else if (patient.identity) {
                    lines.push(`ת.ז. בפרופיל: ${patient.identity}`);
                }

                if (patient.mobile_phone) {
                    lines.push(`טלפון (mobilePhone): ${patient.mobile_phone}`);
                }
            }

            if (payload.data_source) {
                lines.push('מקור נתוני מטופל: פרופיל משתמש בשרת (לא מהטופס)');
            }

            return lines.join('\n');
        }

        /**
         * שליחת טופס קביעת תור (AJAX).
         */
        submitAppointmentForm() {
            if (!this.form.length || !this.submitBtn.length) {
                return;
            }

            // Disable submit button
            this.submitBtn.prop('disabled', true);
            this.messageBox.hide();

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

                // טיפול בתשובות לפי סוג השגיאה
                if (payload.slot_taken) {
                    this.showModal({
                        type: 'error',
                        title: 'התור כבר תפוס',
                        message: this.formatBookingErrorMessage(payload),
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
                    const detailMessage = this.formatBookingErrorMessage({
                        ...payload,
                        error_reason: cleanMessage,
                    });
                    this.showModal({
                        type: 'error',
                        title: 'שגיאה ביצירת התור',
                        message: detailMessage,
                        button: 'חזרה ליומן',
                        onClose: () => {
                            const referrerUrl = this.getReferrerUrl();
                            if (referrerUrl) {
                                window.location.href = referrerUrl;
                            }
                        }
                    });
                } else {
                    this.messageBox
                        .removeClass('msg-success')
                        .addClass('msg-error')
                        .text(this.formatBookingErrorMessage(payload));
                    this.messageBox.show();
                }
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                this.messageBox
                    .removeClass('msg-success')
                    .addClass('msg-error')
                    .text('שגיאה.');
                this.messageBox.show();
            })
            .finally(() => {
                this.submitBtn.prop('disabled', false);
                this.submitBtn.html('קבע את התור <span class="loader" style="display:none;">⌛</span>');
            });
        }

        /**
         * פופאפ השלמת תעודת זהות ליוזר ראשי.
         *
         * @param {Function} onSuccess לאחר שמירה מוצלחת
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
         * מודאל הצלחה לאחר קביעת תור (עיצוב Figma)
         *
         * @param {Object} payload נתוני תור מהשרת
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
         * הפניה לעמוד 2907 בלחיצה על "סגור" — ללא סגירת המודאל (הדף מתחלף מיד).
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
         * בניית קישור Google Calendar (ללא OAuth)
         *
         * @param {Object} payload נתוני תור
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
         * פורמט תאריך/שעה ל-Google Calendar (זמן מקומי)
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
         * המרת תאריך ושעת תור ל-Date מקומי
         *
         * @param {string} apptDate Y-m-d או d/m/Y
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
         * חילוץ הודעת שגיאה נקייה משגיאת proxy
         * 
         * @param {string} rawMessage ההודעה הגולמית מהשרת
         * @returns {string} ההודעה הנקייה
         */
        parseProxyErrorMessage(rawMessage) {
            if (!rawMessage || typeof rawMessage !== 'string') {
                return 'שגיאה ביצירת התור. אנא נסה שנית.';
            }

            // ניסיון ראשון: חילוץ אחרי "Got error from drweb: "
            // זה הפורמט הכי נקי והנפוץ
            const drwebMatch = rawMessage.match(/Got error from drweb:\s*(.+?)(?:\s*$)/);
            if (drwebMatch && drwebMatch[1]) {
                return drwebMatch[1].trim();
            }

            // ניסיון שני: חילוץ מתוך JSON מוטמע ({\"Code\":11,\"Error\":\"הודעה\"})
            // מחפש את שדה Error בתוך JSON מוטמע
            const errorFieldMatch = rawMessage.match(/\\"Error\\":\\"([^"\\]+)\\"/);
            if (errorFieldMatch && errorFieldMatch[1]) {
                // הסר escape characters
                return errorFieldMatch[1].replace(/\\"/g, '"').replace(/\\\\/g, '\\');
            }

            // ניסיון שלישי: JSON עם escape רגיל
            const jsonMatch = rawMessage.match(/\{"Code":\d+,"Error":"([^"]+)"\}/);
            if (jsonMatch && jsonMatch[1]) {
                return jsonMatch[1];
            }

            // fallback: הצג הודעה גנרית
            return 'שגיאה ביצירת התור. אנא נסה שנית.';
        }

        /**
         * Show modal popup (שגיאות)
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

        /** מזהה JetPopup להוספת בן משפחה (jet-popup-3953) */
        getFamilyPopupId() {
            const raw = this.popupId || (bookingFormData.familyPopupId || '');
            const id = parseInt(String(raw).replace(/^jet-popup-/, ''), 10);
            return Number.isNaN(id) ? null : 'jet-popup-' + id;
        }

        /** @deprecated use getFamilyPopupId */
        getJetPopupId() {
            return this.getFamilyPopupId();
        }

        /**
         * האם טופס JFB שייך לפופאפ בן משפחה (לא #ajax-booking-form).
         *
         * @param {jQuery} $form
         * @returns {boolean}
         */
        isFamilyPopupJetForm($form) {
            if (!$form || !$form.length) {
                return false;
            }

            if ($form.is('#ajax-booking-form') || $form.attr('id') === 'ajax-booking-form') {
                return false;
            }

            const popupId = this.getFamilyPopupId();
            if (!popupId) {
                return false;
            }

            const numericId = popupId.replace('jet-popup-', '');
            return $form.closest(`#${popupId}, .jet-popup-${numericId}, .jet-popup`).length > 0;
        }

        /**
         * האם בקשת AJAX של JetFormBuilder להוספת בן משפחה (לא submit_appointment_ajax).
         *
         * @param {Object} settings jQuery ajax settings
         * @returns {boolean}
         */
        isFamilyMemberJetFormAjax(settings) {
            const url = String(settings.url || '');
            if (url.indexOf('admin-ajax.php') !== -1) {
                return false;
            }
            if (url.indexOf('method=ajax') === -1) {
                return false;
            }
            return true;
        }

        /**
         * סיום שליחת טופס בן משפחה — רענון רשימה + סגירת פופאפ (עם debounce).
         *
         * @param {string} source
         * @param {Object} options
         */
        handleFamilyMemberFormComplete(source, options = {}) {
            const now = Date.now();
            if (this._lastFamilyFormComplete && (now - this._lastFamilyFormComplete) < 1500) {
                return;
            }
            this._lastFamilyFormComplete = now;
            this.logBookingDebug('family_form_complete', { source, options });
            this.refreshFamilyListAndClosePopup(options);
        }

        openAddPatientPopup() {
            const popupId = this.getFamilyPopupId();
            if (!popupId) return;
            $(window).trigger({ type: 'jet-popup-open-trigger', popupData: { popupId } });
        }

        closeFamilyPopup() {
            const popupId = this.getFamilyPopupId();
            if (!popupId) {
                return;
            }

            $(window).trigger({
                type: 'jet-popup-close-trigger',
                popupData: { popupId, constantly: false },
            });

            const numericId = popupId.replace('jet-popup-', '');
            $(`.jet-popup-${numericId} .jet-popup__close-button, #${popupId} .jet-popup__close-button`)
                .first()
                .trigger('click');
        }

        refreshFamilyListAndClosePopup(options = {}) {
            this.closeFamilyPopup();

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

                        if (options.showPartialSaveNotice && this.messageBox.length) {
                            const i18n = bookingFormData.i18n || {};
                            this.messageBox
                                .removeClass('msg-error')
                                .addClass('msg-success')
                                .text(i18n.familyFormPartialSave || 'בן המשפחה נשמר. הרשימה עודכנה.');
                            this.messageBox.show();
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error refreshing family list:', error);
                },
            });
        }
    }

    // Initialize – תמיד יוצרים מנג'ר; event delegation מטפל בלחיצה גם אם הטופס נטען מאוחר
    $(document).ready(function() {
        new BookingFormManager();
    });

})(jQuery);
