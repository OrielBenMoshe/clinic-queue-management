/**
 * Schedule Form Core Module
 * Coordinates all form functionality
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Schedule Form Core
	 */
	class ScheduleFormCore {
		constructor(rootElement, config) {
			this.root = rootElement;
			this.config = config || {};
			
			// Cache DOM elements FIRST - before initializing managers that depend on them
			this.elements = {
				backWrap: this.root.querySelector('.schedule-form-back-wrap'),
				backBtn: this.root.querySelector('.schedule-form-back-btn'),
				clinicSelect: this.root.querySelector('.clinic-select'),
				doctorSelect: this.root.querySelector('.doctor-select'),
				manualScheduleName: this.root.querySelector('.manual-schedule_name'),
				googleNextBtn: this.root.querySelector('.continue-btn-google'),
				clinixApiInput: this.root.querySelector('.clinix-api-input'),
				clinixNextBtn: this.root.querySelector('.continue-btn-clinix'),
				saveScheduleBtn: this.root.querySelector('.save-schedule-btn'),
				syncGoogleBtn: this.root.querySelector('.sync-google-btn'),
				googleSyncStatus: this.root.querySelector('.google-sync-status'),
				googleConnectionLoading: this.root.querySelector('.google-connection-loading'),
				googleConnectionError: this.root.querySelector('.google-connection-error')
			};
			
			// Initialize managers - pass config to UIManager
			this.dataManager = new window.ScheduleFormDataManager(this.config);
			this.stepsManager = new window.ScheduleFormStepsManager(this.root);
			this.uiManager = new window.ScheduleFormUIManager(this.root, this.config);
			
			// Initialize Google Auth Manager (if available)
			if (window.ScheduleFormGoogleAuthManager) {
				this.googleAuthManager = new window.ScheduleFormGoogleAuthManager(this.config);
			}
			
			// Initialize specialized managers (they depend on this.elements)
			this.fieldManager = new window.ScheduleFormFieldManager(this);
			this.formManager = new window.ScheduleFormFormManager(this);
			this.googleCalendarManager = new window.ScheduleFormGoogleCalendarManager(this);
			this.clinixCalendarManager = window.ScheduleFormClinixCalendarManager
				? new window.ScheduleFormClinixCalendarManager(this)
				: null;

			this.init();
		}

		/**
		 * Initialize all functionality
		 */
		init() {
			this.setupStep1();
			this.setupClinixStep();
			this.setupStep2();
			this.setupStep3();
			this.setupSuccessScreen();
			this.setupBackButton();
			this.setupStepChangedListener();
			
			// Initialize Select2 for all select fields
			this.uiManager.initializeSelect2();
			
			// Initialize floating labels for text fields
			this.uiManager.initializeFloatingLabels();
		}

		/**
		 * When entering schedule-settings with Clinix flow, load read-only data from proxy.
		 */
		setupStepChangedListener() {
			this.root.addEventListener('schedule-form:step-changed', (e) => {
				const step = e.detail && e.detail.step;
				if (step === 'schedule-settings' && this.stepsManager.formData.action_type === 'clinix') {
					this.fieldManager.loadClinixScheduleData();
				}
			});
		}

		/**
		 * Setup Step 1 - Action selection
		 */
		setupStep1() {
			this.uiManager.setupActionCards((selectedAction) => {
				this.stepsManager.handleStep1Next(selectedAction);
				// Both Google and Clinix go to google step (clinic/doctor/name) – load clinics
				this.fieldManager.loadClinics();
			});
		}

		/**
		 * Setup Clinix step (add calendar - API field)
		 */
		setupClinixStep() {
			const apiInput = this.elements.clinixApiInput;
			const nextBtn = this.elements.clinixNextBtn;

			const syncClinixContinue = () => {
				if (nextBtn) {
					const hasValue = apiInput && apiInput.value.trim().length > 0;
					nextBtn.disabled = !hasValue;
				}
			};

			if (apiInput) {
				apiInput.addEventListener('input', syncClinixContinue);
				apiInput.addEventListener('change', syncClinixContinue);
			}

			if (nextBtn) {
				nextBtn.addEventListener('click', async () => {
					const apiValue = apiInput ? apiInput.value.trim() : '';
					this.stepsManager.handleClinixStepNext(apiValue);
					if (this.clinixCalendarManager) {
						await this.clinixCalendarManager.loadCalendarsByToken(apiValue);
					}
				});
			}

			syncClinixContinue();
		}

		/**
		 * Setup back button - visible from step 2 onward, returns to previous step
		 */
		setupBackButton() {
			const backWrap = this.elements.backWrap;
			const backBtn = this.elements.backBtn;

			const updateBackVisibility = () => {
				const prev = this.stepsManager.getPreviousStep();
				if (backWrap) {
					backWrap.classList.toggle('is-visible', !!prev);
					backWrap.setAttribute('aria-hidden', prev ? 'false' : 'true');
				}
			};

			if (backBtn) {
				backBtn.addEventListener('click', (e) => {
					e.preventDefault();
					const prev = this.stepsManager.getPreviousStep();
					if (prev) {
						this.stepsManager.goToStep(prev);
					}
				});
			}

			this.root.addEventListener('schedule-form:step-changed', updateBackVisibility);
			updateBackVisibility();
		}

		/**
		 * Setup Step 2 - Clinic/Doctor selection
		 */
		setupStep2() {
			// Setup field sync
			const syncFunction = this.uiManager.setupGoogleStepSync(
				this.elements.googleNextBtn,
				this.elements.clinicSelect,
				this.elements.doctorSelect,
				this.elements.manualScheduleName
			);

			// Clinic select change - use jQuery with Select2 events
			if (this.elements.clinicSelect && typeof jQuery !== 'undefined') {
				const $clinicSelect = jQuery(this.elements.clinicSelect);
				
			// Listen to Select2 events only (prevents duplicate calls)
			$clinicSelect.on('select2:select select2:clear', async (e) => {
					const clinicId = $clinicSelect.val();
					
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.log('Clinic selected:', clinicId);
					}
					
					if (clinicId) {
						if (window.ScheduleFormUtils) {
							window.ScheduleFormUtils.log('Loading data for clinic...');
						}
						await this.fieldManager.loadDoctors(clinicId);
						
						if (window.ScheduleFormUtils) {
							window.ScheduleFormUtils.log('Loading treatments for clinic:', clinicId);
						}
						await this.uiManager.populateTreatmentCategories(clinicId);
						if (window.ScheduleFormUtils) {
							window.ScheduleFormUtils.log('Finished loading treatments');
						}
						// syncGoogleStep will handle manualScheduleName disabled state
					} else {
						if (this.elements.doctorSelect) {
							const $doctorSelect = jQuery(this.elements.doctorSelect);
							$doctorSelect.empty().append('<option value=""></option>');
							$doctorSelect.prop('disabled', true);
							// Reinitialize Select2 after clearing
							if ($doctorSelect.hasClass('select2-hidden-accessible')) {
								$doctorSelect.trigger('change.select2');
							}
						}
						
						// Add disabled class for styling
						const doctorField = this.root.querySelector('.doctor-select-field');
						if (doctorField) {
							doctorField.classList.add('field-disabled');
						}
						
						// Update placeholder using centralized function - return to default state
						// Use multiple timeouts to ensure it happens after Select2 updates
						this.fieldManager.updateDoctorPlaceholder('default', true);
						setTimeout(() => {
							this.fieldManager.updateDoctorPlaceholder('default', true);
						}, 10);
						setTimeout(() => {
							this.fieldManager.updateDoctorPlaceholder('default', true);
						}, 50);
						
						// Restore original doctor label when clinic is cleared
						const doctorLabel = this.root.querySelector('.doctor-select-field .jet-form-builder__label-text.helper-text');
						if (doctorLabel) {
							doctorLabel.textContent = 'בחר רופא מתוך רשימת אנשי צוות בפורטל';
						}
					}
					
					// syncGoogleStep will handle all field states (including manualScheduleName)
					syncFunction();
				});
			}

			// Google step "המשך" – Clinix: save clinic/doctor/name and go to token step; Google: go to schedule-settings
			if (this.elements.googleNextBtn) {
				this.elements.googleNextBtn.addEventListener('click', () => {
					const data = {
						clinic_id: this.elements.clinicSelect ? this.elements.clinicSelect.value : '',
						doctor_id: this.elements.doctorSelect ? this.elements.doctorSelect.value : '',
						manual_calendar_name: this.elements.manualScheduleName ? this.elements.manualScheduleName.value.trim() : '',
					};
					if (this.stepsManager.formData.action_type === 'clinix') {
						this.stepsManager.updateFormData(data);
						this.stepsManager.goToStep('clinix');
					} else {
						this.stepsManager.handleStep2Next(data);
					}
				});
			}

			// Initial sync
			syncFunction();
		}

		/**
		 * Setup Step 3 - Schedule settings
		 */
		setupStep3() {
			// Setup day checkboxes
			this.uiManager.setupDayCheckboxes();
			
			// Setup time splits
			this.uiManager.setupTimeSplits();
			
			// Setup treatments repeater
			this.uiManager.setupTreatmentsRepeater();
			
			// Save button
			if (this.elements.saveScheduleBtn) {
				this.elements.saveScheduleBtn.addEventListener('click', () => {
					this.formManager.saveSchedule();
				});
			}
		}

	/**
	 * Setup success screen and calendar-selection save button (branches Clinix vs Google).
	 */
	setupSuccessScreen() {
		if (this.elements.syncGoogleBtn && this.googleAuthManager) {
			this.elements.syncGoogleBtn.addEventListener('click', async () => {
				await this.googleCalendarManager.handleGoogleSync();
			});
		}

		const saveCalendarBtn = this.root.querySelector('.save-calendar-btn');
		if (saveCalendarBtn) {
			saveCalendarBtn.addEventListener('click', async () => {
				const selectedItem = this.root.querySelector('.calendar-item.is-selected');
				if (!selectedItem) {
					this.uiManager.showError('אנא בחר יומן');
					return;
				}
				const sourceSchedulerID = selectedItem.dataset.sourceSchedulerId || '';
				if (this.stepsManager.formData.action_type === 'clinix') {
					this.stepsManager.updateFormData({ selected_calendar_id: sourceSchedulerID });
					this.stepsManager.goToStep('schedule-settings');
					return;
				}
				await this.googleCalendarManager.handleSaveCalendarSelection();
			});
		}
	}

}

// Export to global scope
window.ScheduleFormCore = ScheduleFormCore;

})(window);

