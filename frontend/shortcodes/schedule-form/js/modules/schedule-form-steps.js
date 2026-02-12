/**
 * Schedule Form Steps Module
 * Handles navigation between form steps
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Constants
	 */
	const DAY_NAMES_HE = {
		sunday: 'יום ראשון',
		monday: 'יום שני',
		tuesday: 'יום שלישי',
		wednesday: 'יום רביעי',
		thursday: 'יום חמישי',
		friday: 'יום שישי',
		saturday: 'יום שבת'
	};

	/**
	 * Schedule Form Steps Manager
	 */
	class ScheduleFormStepsManager {
		constructor(rootElement) {
			this.root = rootElement;
			this.currentStep = 'start';
			this.formData = {
				action_type: '',
				add_api: '', // API value for clinix (add calendar) flow
				clinic_id: '',
				doctor_id: '',
				manual_calendar_name: '',
				scheduler_id: '', // WordPress post ID
				source_credentials_id: '', // From proxy after saving credentials
				selected_calendar_id: '' // Selected calendar sourceSchedulerID
			};
		}

		/**
		 * Get the previous step for the current step (for back button)
		 *
		 * @return {string|null} Step name or null if no previous (e.g. start)
		 */
		getPreviousStep() {
			switch (this.currentStep) {
				case 'clinix':
					return 'google';
				case 'google':
					return 'start';
				case 'calendar-selection':
					return this.formData.action_type === 'clinix' ? 'clinix' : 'google';
				case 'schedule-settings':
					return this.formData.action_type === 'google' ? 'google' : 'calendar-selection';
				case 'success':
				case 'final-success':
					return 'schedule-settings';
				default:
					return null;
			}
		}

		/**
		 * Go to specific step
		 */
		goToStep(stepName) {
			const steps = this.root.querySelectorAll('.step');
			
			steps.forEach(step => {
				const isActive = step.dataset.step === stepName;
				step.classList.toggle('is-active', isActive);
				
				if (isActive) {
					step.removeAttribute('aria-hidden');
				} else {
					step.setAttribute('aria-hidden', 'true');
				}
			});

			this.currentStep = stepName;

			// Dispatch custom event
			this.root.dispatchEvent(new CustomEvent('schedule-form:step-changed', {
				detail: { step: stepName },
				bubbles: true
			}));
		}

		/**
		 * Handle step 1 -> step 2 (action selection).
		 * Both Google and Clinix go to google step first (clinic, doctor, manual name).
		 */
		handleStep1Next(selectedAction) {
			this.formData.action_type = selectedAction;
			this.root.classList.toggle('action-type-clinix', selectedAction === 'clinix');
			this.root.classList.toggle('action-type-google', selectedAction === 'google');
			this.goToStep('google');
		}

		/**
		 * Handle clinix step (token) -> calendar selection.
		 */
		handleClinixStepNext(apiValue) {
			this.formData.add_api = apiValue || '';
			this.goToStep('calendar-selection');
		}

		/**
		 * Handle step 2 -> step 3 (doctor/clinic selection)
		 */
		handleStep2Next(data) {
			Object.assign(this.formData, data);
			this.goToStep('schedule-settings');
		}

		/**
		 * Validate current step
		 */
		validateCurrentStep() {
			switch (this.currentStep) {
				case 'start':
					return !!this.formData.action_type;
				
				case 'clinix':
					return true; // API field optional for now; enable button when filled via UI
				
				case 'google':
					return !!(this.formData.doctor_id || this.formData.manual_calendar_name);
				
				case 'calendar-selection':
					return !!this.formData.selected_calendar_id;
				
				case 'schedule-settings':
					// Will be validated in the save function
					return true;
				
				default:
					return false;
			}
		}

		/**
		 * Get form data
		 */
		getFormData() {
			return { ...this.formData };
		}

		/**
		 * Update form data
		 */
		updateFormData(data) {
			Object.assign(this.formData, data);
		}

		/**
		 * Show success screen
		 */
		showSuccessScreen(scheduleData) {
			this.goToStep('success');

			// Populate schedule summary
			const daysList = this.root.querySelector('.schedule-days-list');
			if (!daysList) return;

			
			let summaryHTML = '';
			for (const [day, ranges] of Object.entries(scheduleData.days || {})) {
				const dayName = DAY_NAMES_HE[day] || day;
				const timeRanges = ranges.map(r => `${r.start_time}–${r.end_time}`).join(', ');
				summaryHTML += `<div>${dayName}: ${timeRanges}</div>`;
			}
			daysList.innerHTML = summaryHTML;

			// Show the success step
			const successStep = this.root.querySelector('.success-step');
			if (successStep) {
				successStep.style.display = 'block';
			}
		}

		/**
		 * Reset form
		 */
		reset() {
			this.formData = {
				action_type: '',
				add_api: '',
				clinic_id: '',
				doctor_id: '',
				manual_calendar_name: '',
				scheduler_id: '',
				source_credentials_id: '',
				selected_calendar_id: ''
			};
			this.goToStep('start');
		}
	}

	// Export to global scope
	window.ScheduleFormStepsManager = ScheduleFormStepsManager;

})(window);

