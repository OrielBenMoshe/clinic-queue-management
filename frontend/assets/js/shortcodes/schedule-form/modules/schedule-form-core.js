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
			
			// Initialize Google Auth Manager (if available)
			if (window.ScheduleFormGoogleAuthManager) {
				this.googleAuthManager = new window.ScheduleFormGoogleAuthManager(this.config);
			}
			
			// Cache DOM elements
			this.elements = {
				clinicSelect: this.root.querySelector('.clinic-select'),
				doctorSelect: this.root.querySelector('.doctor-select'),
				manualCalendar: this.root.querySelector('.manual-calendar'),
				googleNextBtn: this.root.querySelector('.continue-btn-google'),
				saveScheduleBtn: this.root.querySelector('.save-schedule-btn'),
				syncGoogleBtn: this.root.querySelector('.sync-google-btn'),
				googleSyncStatus: this.root.querySelector('.google-sync-status'),
				googleConnectionLoading: this.root.querySelector('.google-connection-loading'),
				googleConnectionError: this.root.querySelector('.google-connection-error')
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
		if (this.elements.syncGoogleBtn && this.googleAuthManager) {
			this.elements.syncGoogleBtn.addEventListener('click', async () => {
				await this.handleGoogleSync();
			});
		}
		
		// Setup calendar selection
		this.setupCalendarSelection();
	}

	/**
	 * Handle Google Calendar sync
	 */
	async handleGoogleSync() {
		if (!this.googleAuthManager) {
			console.error('[ScheduleForm] Google Auth Manager not initialized');
			this.showGoogleError('מערכת החיבור לגוגל לא זמינה');
			return;
		}

		// Get scheduler ID from stepsManager formData
		const schedulerId = this.stepsManager.formData.scheduler_id;
		
		if (!schedulerId) {
			console.error('[ScheduleForm] No scheduler ID found');
			this.showGoogleError('מזהה היומן לא נמצא. אנא נסה שוב.');
			return;
		}

		// Set scheduler ID in Google Auth Manager
		this.googleAuthManager.setSchedulerId(schedulerId);

		try {
			// Show loading state
			this.showGoogleLoading();

			// Step 1: OAuth flow
			console.log('[ScheduleForm] Starting Google OAuth flow...');
			const result = await this.googleAuthManager.connect();

			// Log debug info from server to console
			if (result.debug && Array.isArray(result.debug)) {
				console.group('[ScheduleForm] Debug Info from Server:');
				result.debug.forEach((log, index) => {
					console.log(`[${index + 1}]`, log);
				});
				console.groupEnd();
			}

			// Log full response for debugging
			console.log('[ScheduleForm] Full API response:', result);
			console.log('[ScheduleForm] Response data:', result.data);
			console.log('[ScheduleForm] source_credentials_id:', result.data?.source_credentials_id);
			
			// Log raw proxy response if available
			if (result.proxy_raw_response) {
				console.log('[ScheduleForm] === תשובה גולמית מהפרוקסי API ===');
				console.log(result.proxy_raw_response);
			}

			if (!result.data) {
				console.error('[ScheduleForm] No data in response:', result);
				throw new Error('לא התקבלו נתונים מהשרת');
			}

			if (!result.data.source_credentials_id) {
				console.error('[ScheduleForm] source_credentials_id is missing or null:', result.data);
				throw new Error('לא התקבל מזהה credentials מהפרוקסי. ייתכן שהחיבור לפרוקסי נכשל.');
			}

			// Step 2: Load calendars
			this.stepsManager.updateFormData({
				scheduler_id: schedulerId,
				source_credentials_id: result.data.source_credentials_id
			});
			
			this.stepsManager.goToStep('calendar-selection');
			await this.loadCalendars(schedulerId, result.data.source_credentials_id);

		} catch (error) {
			console.error('[ScheduleForm] Google connection failed:', error);
			this.showGoogleError(error.message || 'שגיאה בחיבור לגוגל');
		}
	}

	/**
	 * Show Google loading state
	 */
	showGoogleLoading() {
		// Hide sync button
		if (this.elements.syncGoogleBtn) {
			this.elements.syncGoogleBtn.style.display = 'none';
		}

		// Hide error if visible
		if (this.elements.googleConnectionError) {
			this.elements.googleConnectionError.style.display = 'none';
		}

		// Hide success if visible
		if (this.elements.googleSyncStatus) {
			this.elements.googleSyncStatus.style.display = 'none';
		}

		// Show loading
		if (this.elements.googleConnectionLoading) {
			this.elements.googleConnectionLoading.style.display = 'block';
		}
	}

	/**
	 * Show Google success state
	 */
	showGoogleSuccess(data) {
		// Hide loading
		if (this.elements.googleConnectionLoading) {
			this.elements.googleConnectionLoading.style.display = 'none';
		}

		// Hide error if visible
		if (this.elements.googleConnectionError) {
			this.elements.googleConnectionError.style.display = 'none';
		}

		// Show success status
		if (this.elements.googleSyncStatus) {
			this.elements.googleSyncStatus.style.display = 'flex';
			
			// Update user email
			const emailElement = this.elements.googleSyncStatus.querySelector('.google-user-email');
			if (emailElement && data.user_email) {
				emailElement.textContent = data.user_email;
			}
		}

		// Keep sync button hidden (already connected)
		if (this.elements.syncGoogleBtn) {
			this.elements.syncGoogleBtn.classList.add('connected');
		}
	}

	/**
	 * Show Google error state
	 */
	showGoogleError(errorMessage) {
		// Hide loading
		if (this.elements.googleConnectionLoading) {
			this.elements.googleConnectionLoading.style.display = 'none';
		}

		// Show error
		if (this.elements.googleConnectionError) {
			this.elements.googleConnectionError.style.display = 'block';
			
			const errorDetailsElement = this.elements.googleConnectionError.querySelector('.error-details');
			if (errorDetailsElement) {
				errorDetailsElement.textContent = errorMessage;
			}
		}

		// Show sync button again (allow retry)
		if (this.elements.syncGoogleBtn) {
			this.elements.syncGoogleBtn.style.display = 'inline-flex';
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
				
				// Auto-select if only one clinic
				if (clinics && clinics.length === 1) {
					const singleClinicId = clinics[0].id;
					if (this.elements.clinicSelect) {
						// For Select2, we need to set value and trigger change
						if (typeof jQuery !== 'undefined') {
							jQuery(this.elements.clinicSelect).val(singleClinicId).trigger('change');
						} else {
							this.elements.clinicSelect.value = singleClinicId;
							this.elements.clinicSelect.dispatchEvent(new Event('change'));
						}
						console.log('[ScheduleForm] Auto-selected single clinic:', singleClinicId);
					}
				}
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

			// Save scheduler_id to formData for Google Auth
			if (result.data && result.data.scheduler_id) {
				this.stepsManager.updateFormData({ scheduler_id: result.data.scheduler_id });
				console.log('[ScheduleForm] Scheduler ID saved:', result.data.scheduler_id);
			}

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

	/**
	 * Load calendars from proxy
	 */
	async loadCalendars(schedulerId, sourceCredsId) {
		const container = this.root.querySelector('.calendar-list-container');
		const saveBtn = this.root.querySelector('.save-calendar-btn');
		const errorDiv = this.root.querySelector('.calendar-error');
		
		if (!container) return;
		
		try {
			container.innerHTML = '<div class="calendar-loading" style="text-align:center;padding:2rem;"><div class="spinner"></div><p>טוען יומנים...</p></div>';
			if (errorDiv) errorDiv.style.display = 'none';
			
			const response = await fetch(
				`${this.config.restUrl}/google/calendars?scheduler_id=${schedulerId}&source_creds_id=${sourceCredsId}`,
				{
					headers: {
						'X-WP-Nonce': this.config.restNonce
					}
				}
			);
			
			const data = await response.json();
			
			if (!response.ok || !data.success) {
				throw new Error(data.message || 'שגיאה בטעינת יומנים');
			}
			
			this.renderCalendarList(data.calendars || []);
			
			if (saveBtn && data.calendars && data.calendars.length > 0) {
				saveBtn.disabled = false;
			}
			
		} catch (error) {
			console.error('[ScheduleForm] Error loading calendars:', error);
			if (errorDiv) {
				errorDiv.style.display = 'block';
				const errorMsg = errorDiv.querySelector('.error-message');
				if (errorMsg) {
					errorMsg.textContent = error.message || 'שגיאה בטעינת יומנים';
				}
			}
			container.innerHTML = '<p style="text-align:center;color:#EA4335;">שגיאה בטעינת יומנים</p>';
		}
	}

	/**
	 * Render calendar list
	 */
	renderCalendarList(calendars) {
		const container = this.root.querySelector('.calendar-list-container');
		if (!container) return;
		
		if (calendars.length === 0) {
			container.innerHTML = '<p style="text-align:center;color:#666;">לא נמצאו יומנים</p>';
			return;
		}
		
		let html = '';
		calendars.forEach((calendar, index) => {
			const isFirst = index === 0;
			html += `
				<div class="calendar-item ${isFirst ? 'is-selected' : ''}" 
					 data-source-scheduler-id="${calendar.sourceSchedulerID || ''}">
					<div style="flex:1;">
						<div class="calendar-item-name">${calendar.name || 'יומן ללא שם'}</div>
						<div class="calendar-item-description">${calendar.description || 'Lorem ipsum aliquet varius non'}</div>
					</div>
				</div>
			`;
		});
		
		container.innerHTML = html;
		
		// Setup click handlers
		container.querySelectorAll('.calendar-item').forEach(item => {
			item.addEventListener('click', () => {
				container.querySelectorAll('.calendar-item').forEach(i => {
					i.classList.remove('is-selected');
				});
				item.classList.add('is-selected');
				
				// Update formData with selected calendar
				const sourceSchedulerID = item.dataset.sourceSchedulerId || '';
				this.stepsManager.updateFormData({
					selected_calendar_id: sourceSchedulerID
				});
				
				const saveBtn = this.root.querySelector('.save-calendar-btn');
				if (saveBtn) {
					saveBtn.disabled = false;
				}
			});
		});
		
		// Store first calendar as default
		if (calendars.length > 0) {
			this.stepsManager.updateFormData({
				selected_calendar_id: calendars[0].sourceSchedulerID || ''
			});
		}
	}

	/**
	 * Setup calendar selection handlers
	 */
		setupCalendarSelection() {
		const saveBtn = this.root.querySelector('.save-calendar-btn');
		if (!saveBtn) return;
		
		saveBtn.addEventListener('click', async () => {
			const selectedItem = this.root.querySelector('.calendar-item.is-selected');
			if (!selectedItem) {
				this.uiManager.showError('אנא בחר יומן');
				return;
			}
			
			const sourceSchedulerID = selectedItem.dataset.sourceSchedulerId;
			const schedulerId = this.stepsManager.formData.scheduler_id;
			const sourceCredsId = this.stepsManager.formData.source_credentials_id;
			
			if (!schedulerId || !sourceCredsId) {
				this.uiManager.showError('נתונים חסרים. אנא נסה שוב.');
				return;
			}
			
			try {
				// Show loading
				this.uiManager.setButtonLoading(saveBtn, true, 'יוצר יומן...');
				
				// Get schedule type to determine if we need to send activeHours
				const scheduleType = this.stepsManager.formData.schedule_type || 'google';
				
				// Prepare request body
				const requestBody = {
					scheduler_id: schedulerId,
					source_credentials_id: sourceCredsId,
					source_scheduler_id: sourceSchedulerID
				};
				
				// For Google Calendar: send activeHours (required by API)
				if (scheduleType === 'google') {
					// Get days data from form (collected earlier in the form)
					const scheduleData = this.collectScheduleData();
					if (scheduleData.days && Object.keys(scheduleData.days).length > 0) {
						requestBody.active_hours = scheduleData.days;
					} else {
						// Try to get from formData if available
						if (this.stepsManager.formData.days && Object.keys(this.stepsManager.formData.days).length > 0) {
							requestBody.active_hours = this.stepsManager.formData.days;
						}
					}
				}
				
				// Create scheduler in proxy
				const response = await fetch(`${this.config.restUrl}/scheduler/create-schedule-in-proxy`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.config.restNonce
					},
					body: JSON.stringify(requestBody)
				});
				
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || 'שגיאה ביצירת יומן בפרוקסי');
				}
				
				// Success! Show final success screen
				this.stepsManager.goToStep('final-success');
				this.showFinalSuccess(result.data);
				
			} catch (error) {
				console.error('[ScheduleForm] Error creating scheduler:', error);
				
				// Check if this is a duplicate scheduler error
				const errorMessage = error.message || '';
				let userMessage = errorMessage;
				let isRecoverableError = false;
				
				// Try to parse the error to get more details
				try {
					const debugResponse = await fetch(`${this.config.restUrl}/scheduler/create-schedule-in-proxy`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': this.config.restNonce
						},
						body: JSON.stringify(requestBody)
					});
					
					const debugResult = await debugResponse.json();
					
					if (debugResult.code === 'scheduler_already_exists') {
						// This is a duplicate scheduler error
						userMessage = debugResult.message;
						isRecoverableError = true;
						
						// Show helpful message to user
						this.uiManager.showError(
							`<strong>יומן זה כבר קיים!</strong><br><br>` +
							`${userMessage}<br><br>` +
							`<strong>פתרונות אפשריים:</strong><br>` +
							`• בחר יומן אחר מרשימת היומנים של Google Calendar<br>` +
							`• מחק את היומן הקיים בפרוקסי (אם יש לך גישה)<br>` +
							`• צור קשר עם התמיכה אם אתה צריך עזרה`,
							10000 // Show for 10 seconds
						);
						
						// Log details for debugging
						if (debugResult.data && debugResult.data.source_scheduler_id) {
							console.log('[ScheduleForm] Duplicate scheduler:', {
								source_scheduler_id: debugResult.data.source_scheduler_id,
								help: debugResult.data.help
							});
						}
					} else if (debugResult.data && debugResult.data.debug) {
						console.log('[ScheduleForm] Debug data:', debugResult.data.debug);
					}
				} catch (debugError) {
					// Debug fetch failed, use original error message
					console.error('[ScheduleForm] Debug fetch failed:', debugError);
				}
				
				// Show error to user (if not already shown above)
				if (!isRecoverableError) {
					this.uiManager.showError(`שגיאה ביצירת יומן: ${userMessage}`);
				}
				
			} finally {
				this.uiManager.setButtonLoading(saveBtn, false, '', 'שמירה');
			}
		});
	}

	/**
	 * Show final success screen
	 */
	showFinalSuccess(data) {
		// Update success screen with final message
		const successStep = this.root.querySelector('.final-success-step');
		if (successStep) {
			successStep.style.display = 'block';
		}
		
		// Hide calendar selection
		const calendarStep = this.root.querySelector('.calendar-selection-step');
		if (calendarStep) {
			calendarStep.style.display = 'none';
		}
	}
}

// Export to global scope
window.ScheduleFormCore = ScheduleFormCore;

})(window);

