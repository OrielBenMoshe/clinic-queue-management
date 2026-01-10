/**
 * Schedule Form Google Calendar Manager Module
 * Handles all Google Calendar integration logic
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Schedule Form Google Calendar Manager
	 * Manages Google Calendar sync, calendar loading, and selection
	 */
	class ScheduleFormGoogleCalendarManager {
		constructor(core) {
			this.core = core;
			this.root = core.root;
			this.config = core.config;
			this.googleAuthManager = core.googleAuthManager;
			this.stepsManager = core.stepsManager;
			this.uiManager = core.uiManager;
			this.elements = core.elements;
		}

		/**
		 * Handle Google Calendar sync
		 */
		async handleGoogleSync() {
			if (!this.googleAuthManager) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Google Auth Manager not initialized');
				} else {
					console.error('[ScheduleForm] Google Auth Manager not initialized');
				}
				this.showGoogleError('מערכת החיבור לגוגל לא זמינה');
				return;
			}

			// Get scheduler ID from stepsManager formData
			const schedulerId = this.stepsManager.formData.scheduler_id;
			
			if (!schedulerId) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('No scheduler ID found');
				} else {
					console.error('[ScheduleForm] No scheduler ID found');
				}
				this.showGoogleError('מזהה היומן לא נמצא. אנא נסה שוב.');
				return;
			}

			// Set scheduler ID in Google Auth Manager
			this.googleAuthManager.setSchedulerId(schedulerId);

			try {
				// Show loading state
				this.showGoogleLoading();

				// Step 1: OAuth flow
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log('Starting Google OAuth flow...');
				} else {
					console.log('[ScheduleForm] Starting Google OAuth flow...');
				}
				const result = await this.googleAuthManager.connect();

				// Log debug info from server to console
				if (result.debug && Array.isArray(result.debug)) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.log('Debug Info from Server:', result.debug);
					} else {
						console.group('[ScheduleForm] Debug Info from Server:');
						result.debug.forEach((log, index) => {
							console.log(`[${index + 1}]`, log);
						});
						console.groupEnd();
					}
				}

				// Log full response for debugging
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log('Full API response:', result);
					window.ScheduleFormUtils.log('Response data:', result.data);
					window.ScheduleFormUtils.log('source_credentials_id:', result.data?.source_credentials_id);
				} else {
					console.log('[ScheduleForm] Full API response:', result);
					console.log('[ScheduleForm] Response data:', result.data);
					console.log('[ScheduleForm] source_credentials_id:', result.data?.source_credentials_id);
				}
				
				// Log raw proxy response if available
				if (result.proxy_raw_response) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.log('=== תשובה גולמית מהפרוקסי API ===', result.proxy_raw_response);
					} else {
						console.log('[ScheduleForm] === תשובה גולמית מהפרוקסי API ===');
						console.log(result.proxy_raw_response);
					}
				}

				if (!result.data) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.error('No data in response', result);
					} else {
						console.error('[ScheduleForm] No data in response:', result);
					}
					throw new Error('לא התקבלו נתונים מהשרת');
				}

				if (!result.data.source_credentials_id) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.error('source_credentials_id is missing or null', result.data);
					} else {
						console.error('[ScheduleForm] source_credentials_id is missing or null:', result.data);
					}
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
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Google connection failed', error);
				} else {
					console.error('[ScheduleForm] Google connection failed:', error);
				}
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
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error loading calendars', error);
				} else {
					console.error('[ScheduleForm] Error loading calendars:', error);
				}
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
						// Use formManager through core
						const scheduleData = this.core.formManager.collectScheduleData();
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
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.error('Error creating scheduler', error);
					} else {
						console.error('[ScheduleForm] Error creating scheduler:', error);
					}
					
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
								if (window.ScheduleFormUtils) {
									window.ScheduleFormUtils.log('Duplicate scheduler:', {
										source_scheduler_id: debugResult.data.source_scheduler_id,
										help: debugResult.data.help
									});
								} else {
									console.log('[ScheduleForm] Duplicate scheduler:', {
										source_scheduler_id: debugResult.data.source_scheduler_id,
										help: debugResult.data.help
									});
								}
							}
						} else if (debugResult.data && debugResult.data.debug) {
							if (window.ScheduleFormUtils) {
								window.ScheduleFormUtils.log('Debug data:', debugResult.data.debug);
							} else {
								console.log('[ScheduleForm] Debug data:', debugResult.data.debug);
							}
						}
					} catch (debugError) {
						// Debug fetch failed, use original error message
						if (window.ScheduleFormUtils) {
							window.ScheduleFormUtils.error('Debug fetch failed', debugError);
						} else {
							console.error('[ScheduleForm] Debug fetch failed:', debugError);
						}
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
	window.ScheduleFormGoogleCalendarManager = ScheduleFormGoogleCalendarManager;

})(window);
