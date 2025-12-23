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
			
			// Initialize managers
			this.dataManager = new window.ScheduleFormDataManager(this.config);
			this.stepsManager = new window.ScheduleFormStepsManager(this.root);
			this.uiManager = new window.ScheduleFormUIManager(this.root);
			
			// Cache DOM elements
			this.elements = {
				clinicSelect: this.root.querySelector('.clinic-select'),
				doctorSelect: this.root.querySelector('.doctor-select'),
				manualCalendar: this.root.querySelector('.manual-calendar'),
				googleNextBtn: this.root.querySelector('.continue-btn-google'),
				saveScheduleBtn: this.root.querySelector('.save-schedule-btn'),
				syncGoogleBtn: this.root.querySelector('.sync-google-btn')
			};

			this.init();
		}

		/**
		 * Initialize all functionality
		 */
		init() {
			this.setupStep1();
			this.setupStep2();
			this.setupStep3();
			this.setupSuccessScreen();
			
			// Initialize Select2 for all select fields
			this.uiManager.initializeSelect2();
			
			// Initialize floating labels for text fields
			this.uiManager.initializeFloatingLabels();
		}

		/**
		 * Setup Step 1 - Action selection
		 */
		setupStep1() {
			this.uiManager.setupActionCards((selectedAction) => {
				this.stepsManager.handleStep1Next(selectedAction);
				
				// Load clinics when entering Google step
				if (selectedAction === 'google') {
					this.loadClinics();
				}
			});
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
				this.elements.manualCalendar
			);

			// Clinic select change - use jQuery with Select2 events
			if (this.elements.clinicSelect && typeof jQuery !== 'undefined') {
				const $clinicSelect = jQuery(this.elements.clinicSelect);
				
				// Listen to Select2 change event (works with Select2)
				$clinicSelect.on('select2:select select2:clear change', async (e) => {
					const clinicId = $clinicSelect.val();
					
					if (clinicId) {
						await this.loadDoctors(clinicId);
						// syncGoogleStep will handle manualCalendar disabled state
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
						const doctorField = this.root.querySelector('.doctor-search-field');
						if (doctorField) {
							doctorField.classList.add('field-disabled');
						}
						
						// Update placeholder using centralized function - return to default state
						// Use multiple timeouts to ensure it happens after Select2 updates
						this.uiManager.updateDoctorPlaceholder('default', true);
						setTimeout(() => {
							this.uiManager.updateDoctorPlaceholder('default', true);
						}, 10);
						setTimeout(() => {
							this.uiManager.updateDoctorPlaceholder('default', true);
						}, 50);
						
						// Restore original doctor label when clinic is cleared
						const doctorLabel = this.root.querySelector('.doctor-search-field .jet-form-builder__label-text.helper-text');
						if (doctorLabel) {
							doctorLabel.textContent = 'בחר רופא מתוך רשימת אנשי צוות בפורטל';
						}
					}
					
					// syncGoogleStep will handle all field states (including manualCalendar)
					syncFunction();
				});
			}

			// Google next button
			if (this.elements.googleNextBtn) {
				this.elements.googleNextBtn.addEventListener('click', () => {
					const data = {
						clinic_id: this.elements.clinicSelect ? this.elements.clinicSelect.value : '',
						doctor_id: this.elements.doctorSelect ? this.elements.doctorSelect.value : '',
						manual_calendar_name: this.elements.manualCalendar ? this.elements.manualCalendar.value.trim() : '',
					};
				
				this.stepsManager.handleStep2Next(data);
				
				// Load all specialities when entering step 3
				this.loadAllSpecialities();
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
					this.saveSchedule();
				});
			}
		}

		/**
		 * Setup success screen
		 */
		setupSuccessScreen() {
			if (this.elements.syncGoogleBtn) {
				this.elements.syncGoogleBtn.addEventListener('click', () => {
					// TODO: Implement Google Calendar sync
					alert('תכונת סנכרון Google Calendar תתווסף בקרוב');
				});
			}
		}

		/**
		 * Load clinics
		 */
		async loadClinics() {
			if (!this.elements.clinicSelect) return;

			try {
				this.elements.clinicSelect.innerHTML = '<option value="">טוען מרפאות...</option>';
				this.elements.clinicSelect.disabled = true;

				// Update rendered text to show "טוען מרפאות"
				const clinicRendered = this.root.querySelector('.clinic-select-field .select2-container--clinic-queue .select2-selection--single .select2-selection__rendered');
				if (clinicRendered) {
					clinicRendered.setAttribute('title', '');
					clinicRendered.setAttribute('data-placeholder', 'טוען מרפאות');
					clinicRendered.innerHTML = '<span class="select2-selection__placeholder">טוען מרפאות</span>';
				}

				const clinics = await this.dataManager.loadClinics();
				
				// Populate clinics - this function already updates Select2
				this.uiManager.populateClinicSelect(clinics, this.elements.clinicSelect);
			} catch (error) {
				console.error('Error loading clinics:', error);
				this.elements.clinicSelect.innerHTML = '<option value="">שגיאה בטעינת מרפאות</option>';
				
				// Update rendered text to show error
				const clinicRendered = this.root.querySelector('.clinic-select-field .select2-container--clinic-queue .select2-selection--single .select2-selection__rendered');
				if (clinicRendered) {
					clinicRendered.setAttribute('title', '');
					clinicRendered.setAttribute('data-placeholder', 'שגיאה בטעינת מרפאות');
					clinicRendered.innerHTML = '<span class="select2-selection__placeholder">שגיאה בטעינת מרפאות</span>';
				}
				
				this.uiManager.showError('שגיאה בטעינת מרפאות');
			}
		}

		/**
		 * Load doctors for clinic
		 */
		async loadDoctors(clinicId) {
			if (!this.elements.doctorSelect) return;

			try {
				const $doctorSelect = typeof jQuery !== 'undefined' ? jQuery(this.elements.doctorSelect) : null;
				const doctorField = this.root.querySelector('.doctor-search-field');
				
				// Update placeholder to show loading state FIRST (before Select2 updates)
				this.uiManager.updateDoctorPlaceholder('loading', true);
				
				// Disable and show loading
				if ($doctorSelect) {
					$doctorSelect.prop('disabled', true);
					$doctorSelect.empty().append('<option value=""></option>');
					// Update Select2 if initialized
					if ($doctorSelect.hasClass('select2-hidden-accessible')) {
						$doctorSelect.trigger('change.select2');
						// Update placeholder again after Select2 updates
						setTimeout(() => {
							this.uiManager.updateDoctorPlaceholder('loading', true);
						}, 10);
					}
				} else {
					this.elements.doctorSelect.disabled = true;
					this.elements.doctorSelect.innerHTML = '<option value=""></option>';
				}
				
				// Add disabled class for styling
				if (doctorField) {
					doctorField.classList.add('field-disabled');
				}

				const doctors = await this.dataManager.loadDoctors(clinicId);
				
				// Populate doctors - this function already updates Select2
				this.uiManager.populateDoctorSelect(doctors, this.elements.doctorSelect, this.dataManager);
			} catch (error) {
				console.error('Error loading doctors:', error);
				const $doctorSelect = typeof jQuery !== 'undefined' ? jQuery(this.elements.doctorSelect) : null;
				if ($doctorSelect) {
					$doctorSelect.empty().append('<option value=""></option>');
					if ($doctorSelect.hasClass('select2-hidden-accessible')) {
						$doctorSelect.trigger('change.select2');
					}
				} else {
					this.elements.doctorSelect.innerHTML = '<option value=""></option>';
				}
				// Update placeholder to show error state - use multiple timeouts to ensure it happens after Select2 updates
				this.uiManager.updateDoctorPlaceholder('error', true);
				setTimeout(() => {
					this.uiManager.updateDoctorPlaceholder('error', true);
				}, 10);
				setTimeout(() => {
					this.uiManager.updateDoctorPlaceholder('error', true);
				}, 50);
				this.uiManager.showError('שגיאה בטעינת רופאים');
			}
		}

	/**
	 * Load all specialities (hierarchical)
	 */
	async loadAllSpecialities() {
		try {
			const specialities = await this.dataManager.loadAllSpecialities();
			this.uiManager.populateSubspecialitySelects(specialities);
			
			// Reinitialize Select2 after populating
			this.uiManager.reinitializeSelect2();
		} catch (error) {
			console.error('Error loading specialities:', error);
			// Not critical, continue without specialities
		}
	}

		/**
		 * Collect and save schedule data
		 */
		async saveSchedule() {
			try {
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

				// Show success screen
				this.stepsManager.showSuccessScreen(scheduleData);

			} catch (error) {
				console.error('Error saving schedule:', error);
				this.uiManager.showError('שגיאה בשמירת היומן: ' + error.message);
			} finally {
				this.uiManager.setButtonLoading(this.elements.saveScheduleBtn, false, '', 'שמירת הגדרות יומן');
			}
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

		// Collect treatments
		this.root.querySelectorAll('.treatment-row').forEach(row => {
			const treatmentType = row.querySelector('input[name="treatment_name[]"]').value;
			const subSpeciality = row.querySelector('select[name="treatment_subspeciality[]"]').value;
			const cost = row.querySelector('input[name="treatment_price[]"]').value;
			const duration = row.querySelector('select[name="treatment_duration[]"]').value;
			
			if (treatmentType) {
				scheduleData.treatments.push({
					treatment_type: treatmentType,
					sub_speciality: subSpeciality,
					cost: cost,
					duration: duration
				});
			}
		});

		return scheduleData;
	}
}

// Export to global scope
window.ScheduleFormCore = ScheduleFormCore;

})(window);

