/**
 * Clinic Queue Management - Utils Module
 * Utility functions for the booking calendar shortcode
 */
(function($) {
    'use strict';

    /**
     * Debug: localStorage.setItem('bookingCalendarDebug', '1') then refresh.
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

    /**
     * On mobile, intercepts the browser/device Back button: when a modal/panel opens,
     * pushes a history entry so Back closes the modal instead of leaving the page.
     * Normal close (X / overlay / swipe / ESC) removes the pushed entry via release().
     *
     * Shared by the compact panel (core) and expanded modal.
     */
    class MobileBackGuard {
        /**
         * @param {Object}   options
         * @param {Function} options.onBack     Called when Back is pressed while the modal is open.
         * @param {Function} [options.isMobile] Returns true when this behavior should apply (default: mobile detection).
         * @param {string}   [options.label]    Label for the history entry and logs.
         */
        constructor(options) {
            const opts = options || {};
            this._onBack   = typeof opts.onBack === 'function' ? opts.onBack : () => {};
            this._isMobile = typeof opts.isMobile === 'function' ? opts.isMobile : Utils.isMobileViewport;
            this._label    = opts.label || 'bcMobileModal';
            this._active   = false;
            this._onPopState = this._handlePopState.bind(this);
        }

        /** Pushes a history entry on mobile so the next Back press closes the modal. */
        push() {
            if (this._active || !this._isMobile()) {
                return;
            }
            try {
                window.history.pushState({ bookingCalendarBackGuard: this._label }, '');
            } catch (e) {
                Utils.error('MobileBackGuard: pushState failed', e);
                return;
            }
            this._active = true;
            window.addEventListener('popstate', this._onPopState);
            Utils.log('MobileBackGuard pushed:', this._label);
        }

        /**
         * Cleans up after a normal UI close (X / overlay / swipe / ESC).
         * Calls history.back() to remove the entry we pushed.
         */
        release() {
            if (!this._active) {
                return;
            }
            this._active = false;
            window.removeEventListener('popstate', this._onPopState);
            try {
                window.history.back();
            } catch (e) {
                Utils.error('MobileBackGuard: history.back failed', e);
            }
            Utils.log('MobileBackGuard released:', this._label);
        }

        /** @returns {boolean} */
        isActive() {
            return this._active;
        }

        /** Handles popstate when Back was pressed while our history entry was active. */
        _handlePopState() {
            if (!this._active) {
                return;
            }
            // Reset before onBack so release() during close does not call history.back() again.
            this._active = false;
            window.removeEventListener('popstate', this._onPopState);
            Utils.log('MobileBackGuard back pressed → closing:', this._label);
            this._onBack();
        }
    }

    /** @type {number|null} */
    let bodyScrollLockY = null;

    /**
     * האם יש UI פתוח שדורש נעילת גלילת עמוד (פאנל מובייל או מודל מורחב).
     *
     * @returns {boolean}
     */
    function isBookingCalendarScrollLockNeeded() {
        return !!(
            document.querySelector('.booking-calendar-shortcode.is-mobile-open')
            || document.querySelector('#bcm-expanded-modal.bcm-open')
        );
    }

    /**
     * נועל גלילת העמוד (html + body) – תומך ב-iOS עם position:fixed.
     * משותף לפאנל המובייל (core) ולמודל "כל התורים" (expanded modal).
     */
    function lockBodyScroll() {
        if (document.body.classList.contains('booking-calendar-body-lock')) {
            return;
        }

        bodyScrollLockY = window.scrollY || window.pageYOffset || 0;
        document.documentElement.classList.add('booking-calendar-body-lock', 'bcm-body-lock');
        document.body.classList.add('booking-calendar-body-lock', 'bcm-body-lock');
        document.body.style.position = 'fixed';
        document.body.style.top = `-${bodyScrollLockY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
    }

    /**
     * משחרר נעילת גלילה רק כשאין פאנל מובייל או מודל מורחב פתוחים.
     */
    function unlockBodyScroll() {
        if (isBookingCalendarScrollLockNeeded()) {
            return;
        }

        document.documentElement.classList.remove('booking-calendar-body-lock', 'bcm-body-lock');
        document.body.classList.remove('booking-calendar-body-lock', 'bcm-body-lock');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';

        if (bodyScrollLockY !== null) {
            window.scrollTo(0, bodyScrollLockY);
            bodyScrollLockY = null;
        }
    }

    const Utils = {
        lockBodyScroll,
        unlockBodyScroll,
        isBookingCalendarScrollLockNeeded,

        formatDate: (date) => {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        /**
         * Normalizes a date value (string, Date, or jQuery .data()) to a consistent YYYY-MM-DD key.
         * jQuery parses data-date="2026-05-27" as a Date object – normalize before comparisons.
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

        /**
         * Mobile / portrait tablet detection – same media query the UI manager uses
         * for full-screen panel behavior.
         *
         * @returns {boolean}
         */
        isMobileViewport: () => {
            return window.matchMedia(
                '(max-width: 767px), (max-width: 1024px) and (orientation: portrait)'
            ).matches;
        },

        /**
         * Whether the element is inside an Elementor column hidden on mobile (.elementor-hidden-mobile).
         *
         * @param {Element} element
         * @returns {boolean}
         */
        isInsideElementorHiddenMobileColumn: (element) => {
            if (!element || !element.closest) {
                return false;
            }
            const hiddenCol = element.closest('.elementor-hidden-mobile');
            if (!hiddenCol) {
                return false;
            }
            return window.getComputedStyle(hiddenCol).display === 'none';
        },

        /**
         * Finds a visible Elementor column in the same row (sibling of a mobile-hidden column).
         * Common on doctor pages: calendar in a sidebar column hidden on mobile.
         *
         * @param {Element} hiddenColumn .elementor-hidden-mobile column
         * @returns {Element|null}
         */
        findElementorVisibleSiblingColumn: (hiddenColumn) => {
            if (!hiddenColumn || !hiddenColumn.parentElement) {
                return null;
            }

            const row = hiddenColumn.parentElement;
            for (let i = 0; i < row.children.length; i++) {
                const child = row.children[i];
                if (child === hiddenColumn) {
                    continue;
                }
                if (child.classList && child.classList.contains('elementor-hidden-mobile')) {
                    continue;
                }
                const display = window.getComputedStyle(child).display;
                if (display !== 'none' && display !== 'hidden') {
                    return child;
                }
            }

            return row;
        },

        /**
         * @param {Object} options @see MobileBackGuard
         * @returns {MobileBackGuard}
         */
        createMobileBackGuard: (options) => new MobileBackGuard(options),
    };

    window.BookingCalendarUtils = Utils;

})(jQuery);
