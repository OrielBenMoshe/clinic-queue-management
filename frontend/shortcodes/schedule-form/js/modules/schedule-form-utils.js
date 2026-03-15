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
		 * Log message (no-op in production; kept for API compatibility)
		 * @param {string} message - Message to log
		 * @param {*} data - Optional data to log
		 */
		log: () => {},
		
		/**
		 * Log error (no-op in production; kept for API compatibility)
		 * @param {string} message - Error message
		 * @param {Error|*} error - Optional error object
		 */
		error: () => {},
		
		/**
		 * Log warning (no-op in production; kept for API compatibility)
		 * @param {string} message - Warning message
		 * @param {*} data - Optional data to log
		 */
		warn: () => {},

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
