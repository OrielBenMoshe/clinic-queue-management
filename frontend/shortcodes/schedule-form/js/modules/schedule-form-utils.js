/**
 * Schedule Form Utils Module
 * Utility functions for the schedule form system
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Utility functions
	 */
	const Utils = {
		/**
		 * Format date to YYYY-MM-DD format
		 * @param {Date|string} date - Date to format
		 * @param {string} format - Format string (default: 'YYYY-MM-DD')
		 * @returns {string} Formatted date
		 */
		formatDate: (date, format = 'YYYY-MM-DD') => {
			const d = new Date(date);
			const year = d.getFullYear();
			const month = String(d.getMonth() + 1).padStart(2, '0');
			const day = String(d.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		},
		
		/**
		 * Log message to console with prefix
		 * @param {string} message - Message to log
		 * @param {*} data - Optional data to log
		 */
		log: (message, data = null) => {
			if (window.console && window.console.log) {
				if (data !== null && data !== undefined) {
					console.log(`[ScheduleForm] ${message}`, data);
				} else {
					console.log(`[ScheduleForm] ${message}`);
				}
			}
		},
		
		/**
		 * Log error to console with prefix
		 * @param {string} message - Error message
		 * @param {Error|*} error - Optional error object
		 */
		error: (message, error = null) => {
			if (window.console && window.console.error) {
				if (error !== null && error !== undefined) {
					console.error(`[ScheduleForm] ${message}`, error);
				} else {
					console.error(`[ScheduleForm] ${message}`);
				}
			}
		},
		
		/**
		 * Log warning to console with prefix
		 * @param {string} message - Warning message
		 * @param {*} data - Optional data to log
		 */
		warn: (message, data = null) => {
			if (window.console && window.console.warn) {
				if (data !== null && data !== undefined) {
					console.warn(`[ScheduleForm] ${message}`, data);
				} else {
					console.warn(`[ScheduleForm] ${message}`);
				}
			}
		},

		/**
		 * הצגת לואדר הטופס (לואדר אחד לכל הטופס)
		 * @param {HTMLElement} formRoot - אלמנט השורש של הטופס (.clinic-add-schedule-form)
		 * @param {string} [message='טוען...'] - טקסט להצגה
		 */
		showFormLoader: (formRoot, message = 'טוען...') => {
			if (!formRoot) return;
			const overlay = formRoot.querySelector('.schedule-form-loader-overlay');
			if (!overlay) return;
			const textEl = overlay.querySelector('.schedule-form-loader-overlay__text');
			if (textEl) textEl.textContent = message;
			overlay.classList.add('is-visible');
			overlay.setAttribute('aria-hidden', 'false');
			overlay.setAttribute('aria-busy', 'true');
		},

		/**
		 * הסתרת לואדר הטופס
		 * @param {HTMLElement} formRoot - אלמנט השורש של הטופס (.clinic-add-schedule-form)
		 */
		hideFormLoader: (formRoot) => {
			if (!formRoot) return;
			const overlay = formRoot.querySelector('.schedule-form-loader-overlay');
			if (!overlay) return;
			overlay.classList.remove('is-visible');
			overlay.setAttribute('aria-hidden', 'true');
			overlay.setAttribute('aria-busy', 'false');
		}
	};

	// Export to global scope
	window.ScheduleFormUtils = Utils;

})(window);
