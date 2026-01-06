/**
 * Schedule Form Entry Point
 * Location: frontend/shortcodes/schedule-form/js/schedule-form.js
 * 
 * @package Clinic_Queue_Management
 * 
 * Dependencies (loaded in order):
 * 1. modules/schedule-form-data.js
 * 2. modules/schedule-form-steps.js
 * 3. modules/schedule-form-ui.js
 * 4. modules/schedule-form-core.js
 * 5. schedule-form.js (this file)
 */

(function(window, document) {
	'use strict';

	console.log('[ScheduleForm] Entry point loaded');

	/**
	 * Verify all modules are loaded
	 */
	function verifyModules() {
		const modules = [
			'ScheduleFormDataManager',
			'ScheduleFormStepsManager',
			'ScheduleFormUIManager',
			'ScheduleFormCore'
		];

		const missingModules = modules.filter(module => typeof window[module] === 'undefined');

		if (missingModules.length > 0) {
			console.error('[ScheduleForm] Missing modules:', missingModules);
			return false;
		}

		console.log('[ScheduleForm] All modules loaded successfully');
		return true;
	}

	/**
	 * Initialize schedule form
	 */
	function initScheduleForm() {
		// Check if scheduleFormData is available
		if (typeof window.scheduleFormData === 'undefined') {
			console.error('[ScheduleForm] Configuration data (scheduleFormData) not found');
			return;
		}

		// Find all form instances
		const forms = document.querySelectorAll('.clinic-add-schedule-form');
		
		if (forms.length === 0) {
			console.log('[ScheduleForm] No forms found on this page');
			return;
		}

		console.log(`[ScheduleForm] Initializing ${forms.length} form(s)`);

		// Initialize each form
		forms.forEach((form, index) => {
			try {
				// Create unique ID if needed
				if (!form.id) {
					form.id = `schedule-form-${index}`;
				}

				// Initialize core
				const core = new window.ScheduleFormCore(form, window.scheduleFormData);
				
				// Store instance on element for external access
				form.scheduleFormCore = core;

				console.log(`[ScheduleForm] Form #${index} initialized successfully`);
			} catch (error) {
				console.error(`[ScheduleForm] Error initializing form #${index}:`, error);
			}
		});
	}

	/**
	 * Initialize on DOM ready
	 */
	function onDOMReady() {
		// Verify modules first
		if (!verifyModules()) {
			console.error('[ScheduleForm] Cannot initialize - modules missing');
			return;
		}

		// Initialize forms
		initScheduleForm();
	}

	// Check if DOM is already loaded
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onDOMReady);
	} else {
		// DOM already loaded
		onDOMReady();
	}

	// Export initialization function for manual use
	window.initScheduleForm = initScheduleForm;

})(window, document);

