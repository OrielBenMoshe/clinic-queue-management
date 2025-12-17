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

			// Clinic select change
			if (this.elements.clinicSelect) {
				this.elements.clinicSelect.addEventListener('change', async (e) => {
					const clinicId = e.target.value;
					
					if (clinicId) {
						await this.loadDoctors(clinicId);
						if (this.elements.manualCalendar) {
							this.elements.manualCalendar.disabled = true;
						}
					} else {
						if (this.elements.doctorSelect) {
							this.elements.doctorSelect.innerHTML = '<option value="">בחר מרפאה תחילה</option>';
							this.elements.doctorSelect.disabled = true;
						}
						if (this.elements.manualCalendar) {
							this.elements.manualCalendar.disabled = false;
						}
					}
					
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
					
					// Load subspecialities when entering step 3
					if (data.clinic_id) {
						this.loadSubspecialities(data.clinic_id);
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

				const clinics = await this.dataManager.loadClinics();
				
				this.uiManager.populateClinicSelect(clinics, this.elements.clinicSelect);
			} catch (error) {
				console.error('Error loading clinics:', error);
				this.elements.clinicSelect.innerHTML = '<option value="">שגיאה בטעינת מרפאות</option>';
				this.uiManager.showError('שגיאה בטעינת מרפאות');
			}
		}

		/**
		 * Load doctors for clinic
		 */
		async loadDoctors(clinicId) {
			if (!this.elements.doctorSelect) return;

			try {
				this.elements.doctorSelect.disabled = true;
				this.elements.doctorSelect.innerHTML = '<option value="">טוען רופאים...</option>';

				const doctors = await this.dataManager.loadDoctors(clinicId);
				
				this.uiManager.populateDoctorSelect(doctors, this.elements.doctorSelect, this.dataManager);
			} catch (error) {
				console.error('Error loading doctors:', error);
				this.elements.doctorSelect.innerHTML = '<option value="">שגיאה בטעינת רופאים</option>';
				this.uiManager.showError('שגיאה בטעינת רופאים');
			}
		}

		/**
		 * Load subspecialities for clinic
		 */
		async loadSubspecialities(clinicId) {
			try {
				const subspecialities = await this.dataManager.loadSubspecialities(clinicId);
				this.uiManager.populateSubspecialitySelects(subspecialities);
			} catch (error) {
				console.error('Error loading subspecialities:', error);
				// Not critical, continue without subspecialities
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
				const name = row.querySelector('input[name="treatment_name[]"]').value;
				const subspeciality = row.querySelector('select[name="treatment_subspeciality[]"]').value;
				const price = row.querySelector('input[name="treatment_price[]"]').value;
				const duration = row.querySelector('select[name="treatment_duration[]"]').value;
				
				if (name) {
					scheduleData.treatments.push({
						name: name,
						subspeciality: subspeciality,
						price: price,
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

