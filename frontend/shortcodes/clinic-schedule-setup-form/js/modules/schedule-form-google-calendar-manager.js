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
		 * Parse a failed REST response into an Error with code/data.
		 *
		 * @param {Object} result Parsed JSON
		 * @param {Response} response Fetch response
		 * @returns {Error}
		 */
		createRestError(result, response) {
			if (window.ScheduleFormUtils && typeof window.ScheduleFormUtils.createRestError === 'function') {
				return window.ScheduleFormUtils.createRestError(result, response);
			}

			const err = new Error((result && result.message) || 'שגיאה בבקשה לשרת');
			err.code = result && result.code ? result.code : '';
			err.data = result && result.data ? result.data : null;
			err.httpStatus = response && response.status ? response.status : 0;
			return err;
		}

		/**
		 * Log proxy debug payload to the browser console (never shown in the modal).
		 *
		 * @param {Object} data REST error data object
		 */
		logProxyDebug(data) {
			if (!data) {
				return;
			}

			if (data.proxy_response) {
				if (window.ScheduleFormUtils && typeof window.ScheduleFormUtils.log === 'function') {
					window.ScheduleFormUtils.log('create-schedule-in-proxy proxy_response', data.proxy_response);
				} else {
					console.log('[ScheduleForm] create-schedule-in-proxy proxy_response:', data.proxy_response);
				}
			}

			if (!data.debug) {
				return;
			}

			if (window.ScheduleFormUtils && typeof window.ScheduleFormUtils.error === 'function') {
				window.ScheduleFormUtils.error('create-schedule-in-proxy debug', data.debug);
			} else {
				console.error('[ScheduleForm] create-schedule-in-proxy debug:', data.debug);
			}
		}

		/**
		 * Build user-facing body text for duplicate scheduler (409).
		 *
		 * @param {Error} error REST error with code/data
		 * @returns {string}
		 */
		buildDuplicateSchedulerBody(error) {
			const lines = [];
			if (error.message) {
				lines.push(error.message);
			}
			if (error.data && error.data.help) {
				lines.push(String(error.data.help));
			}
			if (error.data && error.data.source_scheduler_id) {
				lines.push('יומן גוגל שנבחר: ' + String(error.data.source_scheduler_id));
			}
			return lines.join('\n\n');
		}

		/**
		 * Show a modal for create-schedule-in-proxy failures.
		 *
		 * @param {Error} error Error from createRestError
		 */
		handleProxySchedulerError(error) {
			this.logProxyDebug(error.data);

			if (error.code === 'scheduler_already_exists') {
				this.uiManager.showAlertModal({
					title: 'יומן זה כבר קיים',
					body: this.buildDuplicateSchedulerBody(error),
					primaryLabel: 'בחר יומן אחר',
				});
				return;
			}

			this.uiManager.showError(error.message || 'שגיאה ביצירת יומן');
		}

	/**
	 * Create the schedule WP post from scheduleData stored in formData (Google flow only).
	 * Idempotent: if scheduler_id is already in formData the call is skipped.
	 * הנתונים מועברים ישירות ב-POST (ללא transient).
	 *
	 * @param {Object} [options]
	 * @param {boolean} [options.asDraft] אם true: זרימת "שליחת בקשה לרופא" → שרת יוצר כטיוטא
	 * @returns {Promise<number>} The created scheduler post ID.
	 */
	async ensureSchedulerCreated(options) {
		const opts = options && typeof options === 'object' ? options : {};

		if (this.stepsManager.formData.scheduler_id) {
			return this.stepsManager.formData.scheduler_id;
		}

		const scheduleData = this.stepsManager.formData.scheduleData;
		if (!scheduleData) {
			throw new Error('נתוני היומן לא נמצאו. אנא חזור לשלב הגדרת הימים ונסה שוב.');
		}

		const body = new URLSearchParams({
			action: 'create_schedule_from_temp',
			nonce:  this.config.createFromTempNonce || '',
			schedule_data: JSON.stringify(scheduleData),
		});
		if (opts.asDraft) {
			body.set('create_as_draft', '1');
		}

		const response = await fetch(this.config.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body,
		});

		const result = await response.json();

		if (!result.success) {
			throw new Error(result.data || 'שגיאה ביצירת פוסט היומן');
		}

		const schedulerId = result.data.scheduler_id;
		this.stepsManager.updateFormData({ scheduler_id: schedulerId });

		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log('Schedule post created directly from data, scheduler_id:', schedulerId);
		}

		return schedulerId;
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

			let schedulerId;
			let loadingShown = false;
			try {
				this.showGoogleLoading();
				loadingShown = true;
				schedulerId = await this.ensureSchedulerCreated();
			} catch (err) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Failed to create schedule post', err);
				}
				this.showGoogleError(err.message || 'שגיאה ביצירת פוסט היומן');
				return;
			}

			// Set scheduler ID in Google Auth Manager
			this.googleAuthManager.setSchedulerId(schedulerId);

			try {
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
				
				this.hideGoogleConnectLoading();
				loadingShown = false;
				this.stepsManager.goToStep('calendar-selection');
				await this.loadCalendars(schedulerId, result.data.source_credentials_id);

			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Google connection failed', error);
				} else {
					console.error('[ScheduleForm] Google connection failed:', error);
				}
				this.showGoogleError(error.message || 'שגיאה בחיבור לגוגל');
				loadingShown = false;
			} finally {
				if (loadingShown) {
					this.hideGoogleConnectLoading();
				}
			}
		}

		/**
		 * Send doctor connect request – create schedule post, build secure link, show success.
		 */
		async handleTransferRequest() {
			const showLoader = (msg) => {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.showFormLoader(this.root, msg);
				}
			};
			const hideLoader = () => {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.hideFormLoader(this.root);
				}
			};

			try {
				showLoader('יוצר יומן...');
				const schedulerId = await this.ensureSchedulerCreated({ asDraft: true });

				showLoader('יוצר קישור חיבור לרופא...');

				const response = await fetch(`${this.config.restUrl}/doctor/send-connect-request`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.config.restNonce
					},
					body: JSON.stringify({ scheduler_id: schedulerId })
				});

				const result = await response.json();
				if (!response.ok || !result.success) {
					throw new Error(result.message || 'שליחת הבקשה לרופא נכשלה');
				}

				const connectUrl = (result.data && result.data.doctor_connect_url) ? result.data.doctor_connect_url : '';
				this.stepsManager.goToStep('final-success');
				this.showTransferSuccessScreen(connectUrl);

				if (this.core && typeof this.core.onSubmissionSuccess === 'function') {
					this.core.onSubmissionSuccess();
				}

			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error sending transfer request', error);
				} else {
					console.error('[ScheduleForm] Error sending transfer request:', error);
				}
				this.uiManager.showError(error.message || 'שליחת הבקשה לרופא נכשלה');
			} finally {
				hideLoader();
			}
		}

		/**
		 * Populate the final-success step for the transfer (send-request) flow.
		 *
		 * @param {string} connectUrl The doctor connect URL to copy.
		 */
		showTransferSuccessScreen(connectUrl) {
			const successStep = this.root.querySelector('.final-success-step');
			if (!successStep) return;

			// Switch to transfer variant
			successStep.classList.add('is-transfer-flow');

			// Store URL on copy button
			const copyBtn = successStep.querySelector('.copy-connect-link-btn');
			if (copyBtn) {
				copyBtn.dataset.connectUrl = connectUrl || '';
				copyBtn.style.display = '';
			}
		}

		/**
		 * Reset google-connect step UI to idle (buttons visible, no loader/error/success).
		 */
		resetGoogleConnectUI() {
			this.hideGoogleConnectLoading();

			if (this.elements.googleConnectionError) {
				this.elements.googleConnectionError.style.display = 'none';
			}
			if (this.elements.googleSyncStatus) {
				this.elements.googleSyncStatus.style.display = 'none';
			}
		}

		/**
		 * Hide loader and restore action buttons (keeps error/success blocks as-is).
		 */
		hideGoogleConnectLoading() {
			if (this.elements.googleConnectionLoading) {
				this.elements.googleConnectionLoading.style.display = 'none';
			}
			if (this.elements.syncGoogleBtn) {
				this.elements.syncGoogleBtn.style.display = '';
			}
			if (this.elements.transferRequestBtn) {
				this.elements.transferRequestBtn.style.display = '';
			}
		}

		/**
		 * Show Google loading state
		 */
		showGoogleLoading() {
			// Hide action buttons
			if (this.elements.syncGoogleBtn) {
				this.elements.syncGoogleBtn.style.display = 'none';
			}
			if (this.elements.transferRequestBtn) {
				this.elements.transferRequestBtn.style.display = 'none';
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
		 * Show Google connection errors via the shared alert modal.
		 *
		 * @param {string} errorMessage User-facing message
		 */
		showGoogleError(errorMessage) {
			this.hideGoogleConnectLoading();

			if (this.elements.googleConnectionError) {
				this.elements.googleConnectionError.style.display = 'none';
			}

			if (this.uiManager && typeof this.uiManager.showError === 'function') {
				this.uiManager.showError(errorMessage);
			}
		}

		/**
		 * Block save when user selected a calendar already marked in use.
		 *
		 * @param {HTMLElement|null} selectedItem Selected calendar row
		 * @returns {boolean} True when selection was rejected
		 */
		rejectDisabledCalendarSelection(selectedItem) {
			if (!selectedItem || !selectedItem.classList.contains('is-disabled')) {
				return false;
			}

			const sourceSchedulerID = selectedItem.dataset.sourceSchedulerId || '';
			this.handleProxySchedulerError(this.buildDuplicateSchedulerLocalError(sourceSchedulerID));
			return true;
		}

		/**
		 * Build a local duplicate-scheduler error (client-side pre-check).
		 *
		 * @param {string} sourceSchedulerID Selected calendar ID
		 * @returns {Error}
		 */
		buildDuplicateSchedulerLocalError(sourceSchedulerID) {
			return this.createRestError(
				{
					code: 'scheduler_already_exists',
					message: 'יומן זה כבר קיים במערכת. נראה שכבר נוצר יומן עם אותו יומן Google Calendar בעבר.',
					data: {
						source_scheduler_id: sourceSchedulerID,
						help: 'אפשרויות: (1) בחר יומן אחר מרשימת Google Calendar. (2) מחק את היומן הקיים מטבלת היומנים שלך. (3) פנה לתמיכה אם אינך בטוח מה לעשות.',
					},
				},
				{ status: 409, ok: false }
			);
		}

		/**
		 * Load calendars from proxy (Google flow only).
		 */
		async loadCalendars(schedulerId, sourceCredsId) {
			const container = this.root.querySelector('.calendar-list-container');
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

				const calendars = data.calendars || [];
				if (window.ScheduleFormCalendarList && typeof window.ScheduleFormCalendarList.renderCalendarList === 'function') {
					window.ScheduleFormCalendarList.renderCalendarList(this.root, calendars, this.stepsManager);
				}

			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error loading calendars', error);
				} else {
					console.error('[ScheduleForm] Error loading calendars:', error);
				}
				if (errorDiv) {
					errorDiv.style.display = 'none';
				}
				container.innerHTML = '<p style="text-align:center;color:#EA4335;">שגיאה בטעינת יומנים</p>';
				if (this.uiManager && typeof this.uiManager.showError === 'function') {
					this.uiManager.showError(error.message || 'שגיאה בטעינת יומנים');
				}
			}
		}

		/**
		 * Handle save in calendar-selection step (Google flow only: create schedule in proxy).
		 * Called from core when user clicks save and action_type is not 'clinix'.
		 */
		async handleSaveCalendarSelection() {
			const saveBtn = this.root.querySelector('.save-calendar-btn');
			const selectedItem = this.root.querySelector('.calendar-item.is-selected');
			const sourceSchedulerID = selectedItem.dataset.sourceSchedulerId;
			const schedulerId = this.stepsManager.formData.scheduler_id;
			const sourceCredsId = this.stepsManager.formData.source_credentials_id;

			if (!schedulerId || !sourceCredsId) {
				this.uiManager.showError('נתונים חסרים. אנא נסה שוב.');
				return;
			}

			try {
				this.uiManager.setButtonLoading(saveBtn, true, 'יוצר יומן...');

				const result = await this.createSchedulerInProxyForGoogle(sourceSchedulerID, schedulerId, sourceCredsId);

				this.stepsManager.goToStep('final-success');
				this.showFinalSuccess(result.data);

				if (this.core && typeof this.core.onSubmissionSuccess === 'function') {
					this.core.onSubmissionSuccess();
				}

			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error creating scheduler', error);
				} else {
					console.error('[ScheduleForm] Error creating scheduler:', error);
				}
				this.handleProxySchedulerError(error);
			} finally {
				this.uiManager.setButtonLoading(saveBtn, false, '', 'שמירה');
			}
		}

		/**
		 * Create scheduler in proxy for Google flow (used from calendar-selection step).
		 *
		 * @param {string} sourceSchedulerID
		 * @param {number} schedulerId
		 * @param {number} sourceCredsId
		 * @returns {Promise<Object>} result from REST endpoint
		 */
		async createSchedulerInProxyForGoogle(sourceSchedulerID, schedulerId, sourceCredsId) {
			const requestBody = {
				scheduler_id: schedulerId,
				source_credentials_id: sourceCredsId,
				source_scheduler_id: sourceSchedulerID
			};

			// Google flow always sends active_hours (days from formData or form fields).
			const scheduleData = this.core.formManager.collectScheduleData();
			if (scheduleData.days && Object.keys(scheduleData.days).length > 0) {
				requestBody.active_hours = scheduleData.days;
			} else if (this.stepsManager.formData.days && Object.keys(this.stepsManager.formData.days).length > 0) {
				requestBody.active_hours = this.stepsManager.formData.days;
			}

			const response = await fetch(`${this.config.restUrl}/scheduler/create-schedule-in-proxy`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.config.restNonce
				},
                // active_hours (אם יש) מגיעים כ-days; השרת ממפה לפי schedule_type והמטה של היומן.
				body: JSON.stringify(requestBody)
			});

			const result = await response.json();

			if (!response.ok || result.success === false) {
				throw this.createRestError(result, response);
			}

			return result;
		}

		/**
		 * Create scheduler in proxy for Clinix flow.
		 * משתמש באותו REST endpoint כמו גוגל, אך הנתונים מגיעים מ-formData
		 * (scheduler_id, source_credentials_id, selected_calendar_id).
		 *
		 * @returns {Promise<Object>} result from REST endpoint
		 */
		async createSchedulerInProxyForClinix() {
			const schedulerId = this.stepsManager.formData.scheduler_id;
			const sourceCredsId = this.stepsManager.formData.source_credentials_id;
			const sourceSchedulerID = (this.stepsManager.formData.selected_calendar_id || '').toString().trim();

			if (!schedulerId || !sourceCredsId || !sourceSchedulerID) {
				const details = {
					scheduler_id: schedulerId,
					source_credentials_id: sourceCredsId,
					selected_calendar_id: sourceSchedulerID
				};
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Missing data for Clinix proxy scheduler creation', details);
				} else {
					console.error('[ScheduleForm] Missing data for Clinix proxy scheduler creation:', details);
				}
				throw new Error('נתונים חסרים ליצירת יומן בפרוקסי (קליניקס)');
			}

			const requestBody = {
				scheduler_id: schedulerId,
				source_credentials_id: sourceCredsId,
				source_scheduler_id: sourceSchedulerID
			};

			const response = await fetch(`${this.config.restUrl}/scheduler/create-schedule-in-proxy`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.config.restNonce
				},
				body: JSON.stringify(requestBody)
			});

			const result = await response.json();

			if (!response.ok || result.success === false) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Clinix scheduler creation failed', { response, result });
				} else {
					console.error('[ScheduleForm] Clinix scheduler creation failed:', response, result);
				}
				throw this.createRestError(result, response);
			}

			return result;
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
