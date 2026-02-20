/**
 * Schedule Form Form Manager Module
 * Handles form data collection and saving
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Schedule Form Form Manager
	 * Manages form data collection and schedule saving
	 */
	class ScheduleFormFormManager {
		constructor(core) {
			this.core = core;
			this.root = core.root;
			this.dataManager = core.dataManager;
			this.stepsManager = core.stepsManager;
			this.uiManager = core.uiManager;
			this.elements = core.elements;
		}

		/**
		 * Collect schedule data from form
		 */
		collectScheduleData() {
			const scheduleData = {
				...this.stepsManager.getFormData(),
				days: {},
				treatments: []
			};

			// Collect days and time ranges
			const dayCheckboxes = this.root.querySelectorAll('.day-checkbox input[type="checkbox"]');
			dayCheckboxes.forEach(checkbox => {
				if (checkbox.checked) {
					const day = checkbox.dataset.day;
					const timeRangesList = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
					const timeRanges = [];
					
					if (timeRangesList) {
						timeRangesList.querySelectorAll('.time-range-row').forEach(row => {
							const fromTime = row.querySelector('.from-time').value;
							const toTime = row.querySelector('.to-time').value;
							timeRanges.push({ start_time: fromTime, end_time: toTime });
						});
					}
					
					scheduleData.days[day] = timeRanges;
				}
			});

			this.root.querySelectorAll('.treatment-row').forEach(row => {
				const clinixSelect = row.querySelector('.clinix-treatment-select');
				const clinixTreatmentId = clinixSelect ? clinixSelect.value : '';
				const clinixTreatmentName = clinixSelect && clinixSelect.selectedOptions && clinixSelect.selectedOptions[0]
					? clinixSelect.selectedOptions[0].text : '';
				const treatmentType = (row.querySelector('.portal-treatment-select') || {}).value || '';
				const costInput = row.querySelector('.treatment-cost-input');
				const durationInput = row.querySelector('.treatment-duration-input');
				const cost = costInput ? parseInt(costInput.value, 10) : 0;
				const duration = durationInput ? parseInt(durationInput.value, 10) : 0;
				scheduleData.treatments.push({
					clinix_treatment_name: clinixTreatmentName,
					clinix_treatment_id: clinixTreatmentId,
					treatment_type: treatmentType,
					cost: isNaN(cost) ? 0 : cost,
					duration: isNaN(duration) ? 0 : duration
				});
			});

			return scheduleData;
		}

		/**
		 * Collect and save schedule data
		 */
		async saveSchedule() {
			try {
				if (typeof this.uiManager.validateTreatmentsComplete === 'function' && !this.uiManager.validateTreatmentsComplete()) {
					this.uiManager.showError('אנא מלא את כל שדות הטיפולים');
					return;
				}
				const scheduleData = this.collectScheduleData();

				// Validate
				if (Object.keys(scheduleData.days).length === 0) {
					this.uiManager.showError('אנא בחר לפחות יום עבודה אחד');
					return;
				}

				if (scheduleData.treatments.length === 0) {
					this.uiManager.showError('אנא הוסף לפחות טיפול אחד');
					return;
				}

				// Show loading state
				this.uiManager.setButtonLoading(this.elements.saveScheduleBtn, true, 'שומר...');

				// Save
				const result = await this.dataManager.saveSchedule(scheduleData);

				// Save scheduler_id to formData for Google Auth
				if (result.data && result.data.scheduler_id) {
					this.stepsManager.updateFormData({ scheduler_id: result.data.scheduler_id });
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.log('Scheduler ID saved:', result.data.scheduler_id);
					} else {
						console.log('[ScheduleForm] Scheduler ID saved:', result.data.scheduler_id);
					}
				}

				// Clinix: go directly to final success (no Google sync step)
				if (scheduleData.action_type === 'clinix') {
					this.stepsManager.goToStep('final-success');
					const finalSuccessStep = this.root.querySelector('.final-success-step');
					if (finalSuccessStep) finalSuccessStep.style.display = 'block';
				} else {
					this.stepsManager.showSuccessScreen(scheduleData);
				}

			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error saving schedule', error);
				} else {
					console.error('Error saving schedule:', error);
				}
				this.uiManager.showError('שגיאה בשמירת היומן: ' + error.message);
			} finally {
				const isClinix = this.stepsManager.formData.action_type === 'clinix';
				this.uiManager.setButtonLoading(this.elements.saveScheduleBtn, false, '', isClinix ? 'יצירת יומן' : 'שמירת הגדרות יומן');
			}
		}
	}

	// Export to global scope
	window.ScheduleFormFormManager = ScheduleFormFormManager;

})(window);
