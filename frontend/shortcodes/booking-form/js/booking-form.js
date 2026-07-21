/**
 * Booking Form
 * Form submit, ID modal, success/error modals, family list refresh.
 * Depends on window.ClinicQueueBookingFormUtils and bookingFormData.
 */

(function ($) {
    'use strict';

    const Utils = window.ClinicQueueBookingFormUtils;

    class BookingFormManager {
        constructor() {
            this.form = null;
            this.messageBox = null;
            this.submitBtn = null;
            this.init();
        }

        init() {
            this.form = $('#ajax-booking-form');
            this.messageBox = $('#booking-message');
            this.submitBtn = $('#submit-btn');

            this.fillFormFromQueryParams();

            if (window.ClinicQueueFloatingLabels && typeof window.ClinicQueueFloatingLabels.init === 'function') {
                window.ClinicQueueFloatingLabels.init(this.form);
            }

            this.bindEvents();
            this.initMobileFloatingCta();
        }

        /**
         * Sticky CTA on mobile until the submit bar reaches its natural place.
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

                const shouldFloat = $anchor[0].getBoundingClientRect().top > window.innerHeight;
                $bar.toggleClass('booking-form-submit-bar--floating', shouldFloat);
                this.form.toggleClass('booking-form--cta-floating', shouldFloat);
            };

            const schedule = () => {
                if (!rafId) {
                    rafId = window.requestAnimationFrame(applyFloatingState);
                }
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

        fillFormFromQueryParams() {
            if (!this.form.length) {
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            const dateParam = urlParams.get('date');
            const timeParam = urlParams.get('time');

            if (dateParam) {
                const $dateField = this.form.find('#appt_date');
                if ($dateField.length && !$dateField.val()) {
                    $dateField.val(dateParam);
                }
            }

            if (timeParam) {
                const $timeField = this.form.find('#appt_time');
                if ($timeField.length && !$timeField.val()) {
                    $timeField.val(timeParam);
                }
            }
        }

        bindEvents() {
            if (this.form.length) {
                this.form.on('submit', (e) => this.handleFormSubmit(e));
            }

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
                    Utils.log('family_member_save_non_json', {
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

        proceedWithBooking() {
            if (this.shouldPromptForUserIdNumber()) {
                this.showIdCompletionModal(() => this.submitAppointmentForm());
                return;
            }

            this.submitAppointmentForm();
        }

        shouldPromptForUserIdNumber() {
            const selectedPatient = this.form.find('input[name="patient_select"]:checked').val() || 'self';
            return selectedPatient === 'self' && !bookingFormData.hasValidUserIdNumber;
        }

        logBookingErrorDetails(payload) {
            const debugPayload = {
                error_code: payload?.error_code || null,
                error_reason: payload?.error_reason || null,
                message: payload?.message || null,
                validation_errors: payload?.validation_errors || null,
                patient_select: payload?.patient_select || null,
                resolved_patient: payload?.resolved_patient || null,
                data_source: payload?.data_source || null,
            };

            Utils.logError('booking_error_details', debugPayload);
        }

        hideBookingMessage() {
            if (!this.messageBox.length) {
                return;
            }

            this.messageBox.attr('hidden', 'hidden');
            this.messageBox.find('.booking-form-message__text').text('');
            this.messageBox.find('.booking-form-message__action').empty().attr('hidden', 'hidden');
        }

        showInlineBookingError(payload) {
            if (!this.messageBox.length) {
                return;
            }

            const { message, hint, hintType } = Utils.getBookingErrorUserMessage(payload);
            this.logBookingErrorDetails(payload);

            this.messageBox.removeClass('msg-success').addClass('msg-error');
            this.messageBox.find('.booking-form-message__text').text(message);

            const $action = this.messageBox.find('.booking-form-message__action').empty();

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

        scrollToBookingMessage() {
            if (!this.messageBox.length || this.messageBox.prop('hidden')) {
                return;
            }

            const top = this.messageBox[0].getBoundingClientRect().top + window.pageYOffset - 24;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }

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

            this.form.closest('.booking-form-wrapper').find('.trigger-add-member').first().trigger('click');
        }

        /**
         * Navigate back to calendar after booking errors that require a new slot.
         */
        redirectToReferrer() {
            const referrerUrl = this.getReferrerUrl();
            if (referrerUrl) {
                window.location.href = referrerUrl;
            }
        }

        showBookingErrorModal({ title, message, button }) {
            this.showModal({
                type: 'error',
                title,
                message,
                button,
                onClose: () => this.redirectToReferrer(),
            });
        }

        submitAppointmentForm() {
            if (!this.form.length || !this.submitBtn.length) {
                return;
            }

            this.submitBtn.prop('disabled', true);
            this.hideBookingMessage();

            fetch(bookingFormData.ajaxUrl, {
                method: 'POST',
                body: new FormData(this.form[0]),
            })
                .then((response) => response.json())
                .then((data) => {
                    const payload = data.data || {};

                    if (payload.missing_user_id_number) {
                        Utils.log('booking_validation_error', payload);
                        bookingFormData.hasValidUserIdNumber = false;
                        this.showIdCompletionModal(() => this.submitAppointmentForm());
                        return;
                    }

                    if (data.success) {
                        Utils.log('booking_success', payload);
                        this.showSuccessModal(payload);
                        return;
                    }

                    Utils.log('booking_error', payload);

                    if (payload.slot_taken) {
                        this.logBookingErrorDetails(payload);
                        this.showBookingErrorModal({
                            title: 'התור כבר תפוס',
                            message: Utils.getBookingErrorUserMessage(payload).message,
                            button: 'בחירת תור אחר',
                        });
                        return;
                    }

                    if (payload.proxy_error) {
                        this.logBookingErrorDetails(payload);
                        this.showBookingErrorModal({
                            title: 'שגיאה ביצירת התור',
                            message: Utils.getProxyErrorUserMessage(payload),
                            button: 'חזרה ליומן',
                        });
                        return;
                    }

                    this.showInlineBookingError(payload);
                })
                .catch((error) => {
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
                $overlay.fadeOut(200, function () {
                    $(this).remove();
                });
            };

            const saveIdNumber = () => {
                const rawValue = ($input.val() || '').trim();
                hideError();

                if (!Utils.isValidIsraeliIdNumber(rawValue)) {
                    showError(i18n.idModalInvalid || 'מספר תעודת זהות אינו תקין.');
                    return;
                }

                $saveBtn.prop('disabled', true).text(i18n.idModalSaving || 'שומר...');

                const body = new FormData();
                body.append('action', 'save_user_id_number');
                body.append('security', bookingFormData.nonce);
                body.append('user_id_number', rawValue);

                fetch(bookingFormData.ajaxUrl, { method: 'POST', body })
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

        showSuccessModal(payload) {
            $('.booking-modal-overlay').remove();

            const $template = $('#clinic-queue-booking-success-modal-tpl');
            if (!$template.length) {
                this.showModal({
                    type: 'success',
                    title: 'התור נקבע בהצלחה!',
                    message: payload.message || '',
                    button: 'סגור',
                    onClose: () => this.redirectAfterSuccessClose(),
                });
                return;
            }

            const $overlay = $($template.html().trim());
            const assets = bookingFormData.assets || {};
            const i18n = bookingFormData.i18n || {};

            $overlay.find('.clinic-queue-booking-success-modal__confetti-img').attr('src', assets.confetti || '');
            $overlay.find('.clinic-queue-booking-success-modal__check-icon').attr('src', assets.successIcon || '');

            const doctorName = (payload.doctor_name || '').trim();
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
            } else {
                datetimeText = dateDisplay || timeDisplay;
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

            const calendarUrl = Utils.buildGoogleCalendarUrl(payload, {
                calendarEventTitle: i18n.calendarEventTitle,
            });

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

        redirectAfterSuccessClose() {
            if (bookingFormData.closeRedirectUrl) {
                window.location.href = bookingFormData.closeRedirectUrl;
                return;
            }

            this.closeModal();
        }

        showModal({ type, title, message, button, onClose, autoClose }) {
            $('.booking-modal-overlay').not('.clinic-queue-booking-id-overlay').remove();

            const safeType = type === 'success' ? 'success' : 'error';
            const icon = safeType === 'success' ? '✓' : '✗';
            const safeTitle = Utils.escapeHtml(title);
            const safeMessage = Utils.escapeHtml(message);
            const safeButton = button ? Utils.escapeHtml(button) : '';

            const $overlay = $(`
                <div class="booking-modal-overlay">
                    <div class="booking-modal booking-modal--${safeType}">
                        <div class="booking-modal__icon">${icon}</div>
                        <div class="booking-modal__title">${safeTitle}</div>
                        <div class="booking-modal__message">${safeMessage}</div>
                        ${button ? `<button type="button" class="booking-modal__button">${safeButton}</button>` : ''}
                    </div>
                </div>
            `).appendTo('body');

            if (button) {
                $overlay.find('.booking-modal__button').on('click', () => this.closeModal(onClose));
            }

            $overlay.on('click', (e) => {
                if (e.target === $overlay[0]) {
                    this.closeModal(onClose);
                }
            });

            if (autoClose) {
                setTimeout(() => this.closeModal(onClose), autoClose);
            }
        }

        closeModal(onClose) {
            $('.booking-modal-overlay').not('.clinic-queue-booking-id-overlay').fadeOut(300, function () {
                $(this).remove();
                if (typeof onClose === 'function') {
                    onClose();
                }
            });
        }

        getReferrerUrl() {
            const formField = this.form.find('#referrer_url');
            if (formField.length && formField.val()) {
                return formField.val();
            }

            return new URLSearchParams(window.location.search).get('referrer_url') || null;
        }

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

        handleFamilyMemberSaved(source) {
            const now = Date.now();
            if (this._lastFamilyMemberSaved && now - this._lastFamilyMemberSaved < 1500) {
                return;
            }

            this._lastFamilyMemberSaved = now;
            Utils.log('family_member_saved', { source });
            this.hideBookingMessage();
            this.refreshFamilyList();
        }

        refreshFamilyList() {
            $.ajax({
                url: bookingFormData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'refresh_family_list_html',
                    security: bookingFormData.nonce,
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

    $(function () {
        if (!Utils || typeof Utils.getBookingErrorUserMessage !== 'function') {
            console.error('[booking-form] ClinicQueueBookingFormUtils is missing. Aborting init.');
            return;
        }

        new BookingFormManager();
    });
})(jQuery);
