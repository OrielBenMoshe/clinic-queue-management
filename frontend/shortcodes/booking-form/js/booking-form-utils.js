/**
 * Booking Form Utils
 * Pure helpers for [booking_form] (no DOM / jQuery).
 *
 * @package Clinic_Queue_Management
 */

(function (window) {
    'use strict';

    const GENERIC_ERROR = 'אירעה שגיאה. אנא נסו שוב.';
    const PROXY_GENERIC_ERROR = 'שגיאה ביצירת התור. אנא נסה שנית.';

    const DEFAULT_ERROR_MESSAGES = {
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

    /**
     * Localized error map from bookingFormData.i18n.errors, with defaults as fallback.
     *
     * @returns {Object<string, string>}
     */
    function getErrorMessages() {
        const localized =
            window.bookingFormData &&
            bookingFormData.i18n &&
            bookingFormData.i18n.errors &&
            typeof bookingFormData.i18n.errors === 'object'
                ? bookingFormData.i18n.errors
                : null;

        return localized ? Object.assign({}, DEFAULT_ERROR_MESSAGES, localized) : DEFAULT_ERROR_MESSAGES;
    }

    /**
     * @returns {string}
     */
    function getGenericError() {
        return (window.bookingFormData && bookingFormData.i18n && bookingFormData.i18n.genericError) || GENERIC_ERROR;
    }

    /**
     * @returns {string}
     */
    function getProxyGenericError() {
        return (window.bookingFormData && bookingFormData.i18n && bookingFormData.i18n.proxyGenericError) || PROXY_GENERIC_ERROR;
    }

    /**
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
     * @param {*} value
     * @returns {string}
     */
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * @param {*} validationErrors
     * @returns {string|null}
     */
    function formatValidationErrors(validationErrors) {
        if (!Array.isArray(validationErrors) || validationErrors.length === 0) {
            return null;
        }

        const cleaned = validationErrors
            .map((item) => (typeof item === 'string' ? item.trim() : ''))
            .filter(Boolean);

        return cleaned.length > 0 ? cleaned.join('\n') : null;
    }

    /**
     * @param {string} rawMessage
     * @returns {string|null}
     */
    function parseProxyErrorMessage(rawMessage) {
        if (!rawMessage || typeof rawMessage !== 'string') {
            return null;
        }

        const drwebMatch = rawMessage.match(/Got error from drweb:\s*(.+?)(?:\s*$)/);
        if (drwebMatch && drwebMatch[1]) {
            return drwebMatch[1].trim();
        }

        const errorFieldMatch = rawMessage.match(/\\"Error\\":\\"([^"\\]+)\\"/);
        if (errorFieldMatch && errorFieldMatch[1]) {
            return errorFieldMatch[1].replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        }

        const jsonMatch = rawMessage.match(/\{"Code":\d+,"Error":"([^"]+)"\}/);
        if (jsonMatch && jsonMatch[1]) {
            return jsonMatch[1];
        }

        const hebrewProxyMatch = rawMessage.match(/שגיאה בקביעת התור בפרוקסי[:.]?\s*(.+)$/);
        if (hebrewProxyMatch && hebrewProxyMatch[1]) {
            return hebrewProxyMatch[1].trim();
        }

        return null;
    }

    /**
     * @param {string} errorCode
     * @returns {{hint: string|null, hintType: string|null}}
     */
    function getErrorHint(errorCode) {
        if (String(errorCode || '').startsWith('family_')) {
            const hint =
                (window.bookingFormData && bookingFormData.i18n && bookingFormData.i18n.familyEditHint) ||
                'עדכון פרטי בן משפחה';
            return { hint: hint, hintType: 'family' };
        }

        return { hint: null, hintType: null };
    }

    /**
     * @param {Object} payload
     * @returns {{message: string, hint: string|null, hintType: string|null}}
     */
    function getBookingErrorUserMessage(payload) {
        const genericError = getGenericError();

        if (!payload || typeof payload !== 'object') {
            return { message: genericError, hint: null, hintType: null };
        }

        const validationMessage = formatValidationErrors(payload.validation_errors);
        if (validationMessage) {
            return { message: validationMessage, hint: null, hintType: null };
        }

        const code = payload.error_code ? String(payload.error_code) : '';
        const errorMessages = getErrorMessages();
        if (code && errorMessages[code]) {
            const hintInfo = getErrorHint(code);
            return {
                message: errorMessages[code],
                hint: hintInfo.hint,
                hintType: hintInfo.hintType,
            };
        }

        if (payload.message && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return { message: payload.message.trim(), hint: null, hintType: null };
        }

        return { message: genericError, hint: null, hintType: null };
    }

    /**
     * @param {Object} payload
     * @returns {string}
     */
    function getProxyErrorUserMessage(payload) {
        const proxyGeneric = getProxyGenericError();

        if (!payload || typeof payload !== 'object') {
            return proxyGeneric;
        }

        const validationMessage = formatValidationErrors(payload.validation_errors);
        if (validationMessage) {
            return validationMessage;
        }

        const parsedMessage = parseProxyErrorMessage(payload.message);
        if (parsedMessage) {
            return parsedMessage;
        }

        if (payload.error_reason && typeof payload.error_reason === 'string' && payload.error_reason.trim() !== '') {
            return payload.error_reason.trim();
        }

        return proxyGeneric;
    }

    /**
     * @param {string} apptDate
     * @param {string} apptTime
     * @returns {Date|null}
     */
    function parseAppointmentStart(apptDate, apptTime) {
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
     * @param {Date} date
     * @returns {string}
     */
    function formatGoogleCalendarDate(date) {
        const pad = (value) => String(value).padStart(2, '0');
        return (
            `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}` +
            `T${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`
        );
    }

    /**
     * @param {Object} payload
     * @param {Object} [options]
     * @param {string} [options.calendarEventTitle]
     * @returns {string}
     */
    function buildGoogleCalendarUrl(payload, options) {
        const start = parseAppointmentStart(payload.appt_date, payload.appt_time);
        if (!start || Number.isNaN(start.getTime())) {
            return '';
        }

        const durationMinutes = Math.max(15, parseInt(payload.duration, 10) || 30);
        const end = new Date(start.getTime() + durationMinutes * 60000);
        const doctorName = (payload.doctor_name || '').trim();
        const titleTemplate = (options && options.calendarEventTitle) || 'תור אצל %s';
        const title = doctorName ? titleTemplate.replace('%s', doctorName) : 'תור במרפאה';

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
            dates: `${formatGoogleCalendarDate(start)}/${formatGoogleCalendarDate(end)}`,
            details: detailsParts.join('\n'),
            location: (payload.clinic_location || '').trim(),
        });

        return `https://calendar.google.com/calendar/render?${params.toString()}`;
    }

    /**
     * @param {string} label
     * @param {*} payload
     */
    function log(label, payload) {
        if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.log === 'function') {
            window.ClinicQueueUtils.log(label, payload);
            return;
        }
        console.log('[booking-form]', label, payload);
    }

    /**
     * @param {string} label
     * @param {*} payload
     */
    function logError(label, payload) {
        if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.error === 'function') {
            window.ClinicQueueUtils.error(label, payload);
            return;
        }
        console.error('[booking-form]', label, payload);
    }

    window.ClinicQueueBookingFormUtils = {
        isValidIsraeliIdNumber,
        escapeHtml,
        formatValidationErrors,
        parseProxyErrorMessage,
        getBookingErrorUserMessage,
        getProxyErrorUserMessage,
        parseAppointmentStart,
        formatGoogleCalendarDate,
        buildGoogleCalendarUrl,
        log,
        logError,
    };
})(window);
