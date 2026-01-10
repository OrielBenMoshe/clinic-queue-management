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
		}
	};

	// Export to global scope
	window.ScheduleFormUtils = Utils;

})(window);
