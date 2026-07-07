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

			if (this.uiManager.scheduleSettingsUI) {
				scheduleData.days = this.uiManager.scheduleSettingsUI.collectDays();
				scheduleData.treatments = this.uiManager.scheduleSettingsUI.collectTreatments();
			}

			return scheduleData;
		}

	/**
	 * Validate, collect and route by action_type:
	 * - clinix: AJAX → יצירת פוסט מיידית → פרוקסי → final-success
	 * - google: שמירה ב-memory בלבד (formData.scheduleData) → google-connect (ללא AJAX)
	 */
	async saveSchedule() {
		if (typeof this.uiManager.validateTreatmentsComplete === 'function' && !this.uiManager.validateTreatmentsComplete()) {
			this.uiManager.showError('אנא מלא את כל שדות הטיפולים');
			return;
		}

		const scheduleData = this.collectScheduleData();

		if (Object.keys(scheduleData.days).length === 0) {
			this.uiManager.showError('אנא בחר לפחות יום עבודה אחד');
			return;
		}

		if (scheduleData.treatments.length === 0) {
			this.uiManager.showError('אנא הוסף לפחות טיפול אחד');
			return;
		}

		if (scheduleData.action_type === 'clinix') {
			await this._saveScheduleClinix(scheduleData);
		} else {
			this._navigateToGoogleConnect(scheduleData);
		}
	}

	/**
	 * Google flow: שמור נתונים ב-formData ועבור ל-google-connect ללא קריאת שרת.
	 * הפוסט ייווצר רק בעת לחיצה על סנכרון/שליחת בקשה בשלב google-connect.
	 *
	 * @param {Object} scheduleData נתוני הטופס שנאספו
	 */
	_navigateToGoogleConnect(scheduleData) {
		this.stepsManager.updateFormData({ scheduleData });
		this.stepsManager.showGoogleConnectScreen(scheduleData);
	}

	/**
	 * Clinix flow: AJAX → יצירת פוסט + שמירת מטה → פרוקסי → final-success.
	 *
	 * @param {Object} scheduleData נתוני הטופס שנאספו
	 */
	async _saveScheduleClinix(scheduleData) {
		try {
			this.uiManager.setButtonLoading(this.elements.saveScheduleBtn, true, 'שומר...');

			const result = await this.dataManager.saveSchedule(scheduleData);

			if (result.data && result.data.scheduler_id) {
				this.stepsManager.updateFormData({ scheduler_id: result.data.scheduler_id });
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log('Clinix scheduler_id saved:', result.data.scheduler_id);
				}
			}

			try {
				if (this.core.googleCalendarManager && typeof this.core.googleCalendarManager.createSchedulerInProxyForClinix === 'function') {
					const proxyResult = await this.core.googleCalendarManager.createSchedulerInProxyForClinix();
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.log('Clinix proxy scheduler created:', proxyResult);
					}
				}
			} catch (proxyError) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error creating Clinix scheduler in proxy', proxyError);
				} else {
					console.error('[ScheduleForm] Error creating Clinix scheduler in proxy:', proxyError);
				}
				if (this.core.googleCalendarManager && typeof this.core.googleCalendarManager.handleProxySchedulerError === 'function') {
					this.core.googleCalendarManager.handleProxySchedulerError(proxyError);
				} else {
					this.uiManager.showError('שגיאה ביצירת היומן בפרוקסי (קליניקס): ' + (proxyError.message || 'שגיאה לא ידועה'));
				}
				return;
			}

			this.stepsManager.goToStep('final-success');
			const finalSuccessStep = this.root.querySelector('.final-success-step');
			if (finalSuccessStep) {
				finalSuccessStep.style.display = 'block';
			}

			if (this.core && typeof this.core.onSubmissionSuccess === 'function') {
				this.core.onSubmissionSuccess();
			}

		} catch (error) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.error('Error saving Clinix schedule', error);
			} else {
				console.error('Error saving schedule:', error);
			}
			this.uiManager.showError('שגיאה בשמירת היומן: ' + error.message);
		} finally {
			this.uiManager.setButtonLoading(this.elements.saveScheduleBtn, false, '', 'יצירת יומן');
		}
	}
	}

	// Export to global scope
	window.ScheduleFormFormManager = ScheduleFormFormManager;

})(window);
