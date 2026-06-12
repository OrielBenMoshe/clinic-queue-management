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
				transferRequestBtn: this.root.querySelector('.transfer-request-btn'),
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
		 * When entering schedule-settings: set flow visibility; for Clinix load read-only data from proxy.
		 */
		setupStepChangedListener() {
			this.root.addEventListener('schedule-form:step-changed', (e) => {
				const step = e.detail && e.detail.step;
				if (step === 'google-connect' && this.googleCalendarManager) {
					this.googleCalendarManager.resetGoogleConnectUI();
				}
				if (step === 'schedule-settings') {
					const actionType = this.stepsManager.formData.action_type || (this.root.classList.contains('action-type-clinix') ? 'clinix' : 'google');
					this.fieldManager.applyFlowVisibility(actionType || 'google');
					// יומן קליניקס: סימון אזור ימים ושעות כמוגבל (readonly)
					const daysContainer = this.root.querySelector('.days-schedule-container');
					if (daysContainer) {
						if (actionType === 'clinix') {
							daysContainer.classList.add('is-readonly');
						} else {
							daysContainer.classList.remove('is-readonly');
						}
					}
					// כותרת שלב: קליניקס = "ימים ושעות עבודה", גוגל = "הגדרת ימים ושעות עבודה"
					const stepEl = this.root.querySelector('.schedule-settings-step');
					const titleEl = stepEl ? stepEl.querySelector('.schedule-settings-step-title') : null;
					if (stepEl && titleEl) {
						const isClinix = actionType === 'clinix';
						const title = isClinix
							? (stepEl.getAttribute('data-schedule-title-clinix') || 'ימים ושעות עבודה')
							: (stepEl.getAttribute('data-schedule-title-google') || 'הגדרת ימים ושעות עבודה');
						titleEl.textContent = title;
					}
					if (actionType === 'clinix') {
						this.fieldManager.loadClinixScheduleData();
					} else {
						this.uiManager.resetScheduleSettingsScroll();
						this.uiManager.ensureCostDurationOptionsForGoogleRows();
						this.uiManager.validateTreatmentsComplete();
					}
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
		 * Setup back button: shown when a previous step exists; hidden only on final-success.
		 */
		setupBackButton() {
			const backWrap = this.elements.backWrap;
			const backBtn = this.elements.backBtn;

			const updateBackVisibility = () => {
				const prev = this.stepsManager.getPreviousStep();
				const isFinalStep = this.stepsManager.currentStep === 'final-success';
				const showBack = !!prev && !isFinalStep;
				if (backWrap) {
					backWrap.classList.toggle('is-visible', showBack);
					backWrap.setAttribute('aria-hidden', showBack ? 'false' : 'true');
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
							window.ScheduleFormUtils.log('Loading treatment types for schedule step');
						}
						try {
							const terms = await this.dataManager.loadTreatmentTypes();
							this.fieldManager.populatePortalTreatments(terms);
						} catch (err) {
							if (window.ScheduleFormUtils) {
								window.ScheduleFormUtils.warn('Could not load treatment types', err);
							}
						}
						if (window.ScheduleFormUtils) {
							window.ScheduleFormUtils.log('Finished loading clinic data');
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
					const clinicVal = this.elements.clinicSelect ? String(this.elements.clinicSelect.value || '').trim() : '';
					const data = {
						clinic_id: clinicVal,
						doctor_id: this.elements.doctorSelect ? this.elements.doctorSelect.value : '',
						manual_calendar_name: this.elements.manualScheduleName ? this.elements.manualScheduleName.value.trim() : '',
					};
					if (!data.clinic_id) {
						this.uiManager.showError('נא לבחור מרפאה לפני המשך.');
						return;
					}
					if (!data.doctor_id && !data.manual_calendar_name) {
						this.uiManager.showError('נא לבחור רופא מהפורטל או להזין שם יומן.');
						return;
					}
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
	 * Setup google-connect screen and calendar-selection save button (branches Clinix vs Google).
	 */
	setupSuccessScreen() {
		if (this.elements.syncGoogleBtn && this.googleAuthManager) {
			this.elements.syncGoogleBtn.addEventListener('click', async () => {
				await this.googleCalendarManager.handleGoogleSync();
			});
		}

		if (this.elements.transferRequestBtn) {
			this.elements.transferRequestBtn.addEventListener('click', async () => {
				await this.googleCalendarManager.handleTransferRequest();
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
					// כותרת שלב קליניקס: "ימים ושעות עבודה" (לפני מעבר לשלב)
					const scheduleStep = this.root.querySelector('.schedule-settings-step');
					const titleEl = scheduleStep ? scheduleStep.querySelector('.schedule-settings-step-title') : null;
					if (titleEl) {
						titleEl.textContent = scheduleStep && scheduleStep.getAttribute('data-schedule-title-clinix')
							? scheduleStep.getAttribute('data-schedule-title-clinix')
							: 'ימים ושעות עבודה';
					}
					this.stepsManager.goToStep('schedule-settings');
					return;
				}
				await this.googleCalendarManager.handleSaveCalendarSelection();
			});
		}

		// כפתור "העתק קישור לחיבור יומן גוגל"
		const copyLinkBtn = this.root.querySelector('.copy-connect-link-btn');
		if (copyLinkBtn) {
			copyLinkBtn.addEventListener('click', () => {
				const url = copyLinkBtn.dataset.connectUrl || '';
				if (!url) return;

				const label  = copyLinkBtn.querySelector('.copy-connect-link-btn__label');
				const copied = copyLinkBtn.querySelector('.copy-connect-link-btn__copied');

				const applyCopied = () => {
					if (label)  label.style.display  = 'none';
					if (copied) copied.style.display = 'inline-flex';
					copyLinkBtn.classList.add('is-copied');
					setTimeout(() => {
						if (label)  label.style.display  = '';
						if (copied) copied.style.display = 'none';
						copyLinkBtn.classList.remove('is-copied');
					}, 2500);
				};

				navigator.clipboard.writeText(url).then(applyCopied).catch(() => {
					// Fallback for older browsers
					const textarea = document.createElement('textarea');
					textarea.value = url;
					textarea.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
					document.body.appendChild(textarea);
					textarea.select();
					document.execCommand('copy');
					document.body.removeChild(textarea);
					applyCopied();
				});
			});
		}

		// כפתור "סיום" במסך final-success: רענון העמוד
		const finishBtn = this.root.querySelector('.finish-btn');
		if (finishBtn) {
			finishBtn.addEventListener('click', () => {
				window.location.reload();
			});
		}
	}

	/**
	 * Reset the form to its initial state.
	 * Called externally when the containing popup/modal is closed.
	 * Resets step navigation, form data, field values, and UI state.
	 */
	reset() {
		// Reset step navigation and internal form data
		this.stepsManager.reset();

		// Remove flow-specific classes set when an action type is chosen
		this.root.classList.remove('action-type-clinix', 'action-type-google');

		// Reset action cards (step 1)
		this.root.querySelectorAll('.action-card').forEach((card) => {
			card.classList.remove('is-active');
		});
		this.root.querySelectorAll('input[type="radio"]').forEach((radio) => {
			radio.checked = false;
		});

		// Re-disable the start-step continue button (was enabled after radio selection)
		const startContinueBtn = this.root.querySelector('.step[data-step="start"] .continue-btn');
		if (startContinueBtn) {
			startContinueBtn.disabled = true;
			startContinueBtn.classList.add('is-disabled');
		}

		// Clear clinic / doctor selects and manual calendar name (step 2)
		if (this.fieldManager) {
			if (this.elements.clinicSelect) {
				this.fieldManager.clearSelectField(this.elements.clinicSelect);
				this.fieldManager.setSelectDisabled(this.elements.clinicSelect, true);
			}
			if (this.elements.doctorSelect) {
				this.fieldManager.clearSelectField(this.elements.doctorSelect);
				this.fieldManager.setSelectDisabled(this.elements.doctorSelect, true);
				this.fieldManager.updateDoctorPlaceholder('default', true);
			}
		}
		if (this.elements.manualScheduleName) {
			this.elements.manualScheduleName.value = '';
			this.elements.manualScheduleName.dispatchEvent(new Event('input', { bubbles: true }));
		}
		if (this.elements.googleNextBtn) {
			this.elements.googleNextBtn.disabled = true;
		}

		// Clear Clinix API token input
		if (this.elements.clinixApiInput) {
			this.elements.clinixApiInput.value = '';
		}
		if (this.elements.clinixNextBtn) {
			this.elements.clinixNextBtn.disabled = true;
		}

		// Restore sub-screens that may have been toggled via inline display
		['.google-connect-step', '.final-success-step', '.calendar-selection-step'].forEach((selector) => {
			const stepEl = this.root.querySelector(selector);
			if (stepEl) {
				stepEl.style.display = '';
			}
		});

		const daysContainer = this.root.querySelector('.days-schedule-container');
		if (daysContainer) {
			daysContainer.classList.remove('is-readonly');
		}

		const loader = this.root.querySelector('.schedule-form-loader-overlay');
		if (loader) {
			loader.classList.remove('is-visible');
			loader.setAttribute('aria-hidden', 'true');
			loader.setAttribute('aria-busy', 'false');
		}

		// Reset Google connection UI to initial state
		if (this.googleCalendarManager) {
			this.googleCalendarManager.resetGoogleConnectUI();
		}
	}

}

// Export to global scope
window.ScheduleFormCore = ScheduleFormCore;

})(window);

