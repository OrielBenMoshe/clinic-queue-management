/**
 * Schedule Form Entry Point
 * Location: frontend/shortcodes/schedule-form/js/schedule-form.js
 * 
 * @package Clinic_Queue_Management
 * 
 * Dependencies (loaded in order):
 * 1. modules/schedule-form-utils.js
 * 2. modules/schedule-form-data.js
 * 3. modules/schedule-form-steps.js
 * 4. modules/schedule-form-ui.js
 * 5. modules/schedule-form-google-auth.js
 * 6. modules/schedule-form-core.js
 * 7. modules/schedule-form-init.js
 * 8. schedule-form.js (this file - verification only)
 */

(function(window) {
	'use strict';

	// Verify all modules are loaded
	const requiredModules = [
		'ScheduleFormUtils',
		'ScheduleFormDataManager',
		'ScheduleFormStepsManager',
		'ScheduleFormUIManager',
		'ScheduleFormGoogleAuthManager',
		'ScheduleFormCore',
		'ScheduleFormManager'
	];

	const missingModules = requiredModules.filter(module => typeof window[module] === 'undefined');

	if (missingModules.length > 0) {
		console.error('[ScheduleForm] Missing modules:', missingModules);
	} else {
		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log('All modules loaded successfully');
		} else {
			console.log('[ScheduleForm] All modules loaded successfully');
		}
	}

	// Export initialization function for manual use (delegates to Manager)
	window.initScheduleForm = () => {
		if (window.ScheduleFormManager && window.ScheduleFormManager.utils) {
			window.ScheduleFormManager.utils.reinitialize();
		} else {
			console.error('[ScheduleForm] ScheduleFormManager not available');
		}
	};

})(window);

