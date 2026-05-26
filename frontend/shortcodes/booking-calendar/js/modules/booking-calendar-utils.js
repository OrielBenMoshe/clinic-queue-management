/**
 * Clinic Queue Management - Utils Module
 * Utility functions for the booking calendar shortcode
 */
(function($) {
    'use strict';

    /**
     * דיבוג: localStorage.setItem('bookingCalendarDebug', '1') ורענון.
     *
     * @return {boolean}
     */
    function is_debug_enabled() {
        try {
            return window.localStorage.getItem('bookingCalendarDebug') === '1';
        } catch (e) {
            return false;
        }
    }

    const Utils = {
        formatDate: (date) => {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        /**
         * ממיר ערך תאריך (מחרוזת, Date, או jQuery .data()) למפתח YYYY-MM-DD עקבי.
         * jQuery מפרש data-date="2026-05-27" כאובייקט Date – חובה לנרמל לפני השוואות.
         *
         * @param {string|Date|number|null|undefined} value
         * @returns {string}
         */
        normalizeDateKey: (value) => {
            if (value === null || value === undefined || value === '') {
                return '';
            }
            if (typeof value === 'string') {
                const trimmed = value.trim();
                const isoDay = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
                if (isoDay) {
                    return isoDay[1];
                }
            }
            const parsed = value instanceof Date ? value : new Date(value);
            if (!Number.isNaN(parsed.getTime())) {
                const year = parsed.getFullYear();
                const month = String(parsed.getMonth() + 1).padStart(2, '0');
                const day = String(parsed.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            return String(value).trim();
        },

        log: (message, data = null) => {
            if (!is_debug_enabled() || !window.console || !window.console.log) {
                return;
            }
            if (data !== null && data !== undefined) {
                console.log(`[BookingCalendar] ${message}`, data);
            } else {
                console.log(`[BookingCalendar] ${message}`);
            }
        },

        error: (message, error = null) => {
            if (!window.console || !window.console.error) {
                return;
            }
            if (error !== null && error !== undefined) {
                console.error(`[BookingCalendar] ${message}`, error);
            } else {
                console.error(`[BookingCalendar] ${message}`);
            }
        },
    };

    window.BookingCalendarUtils = Utils;

})(jQuery);
