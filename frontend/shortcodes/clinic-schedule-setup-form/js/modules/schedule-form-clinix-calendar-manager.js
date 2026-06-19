/**
 * Schedule Form Clinix Calendar Manager Module
 * Handles loading and displaying source calendars for the Clinix (token-based) flow only.
 *
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Schedule Form Clinix Calendar Manager
	 * Loads calendars from API using Clinix token and renders via shared calendar list.
	 */
	class ScheduleFormClinixCalendarManager {
		constructor(core) {
			this.core = core;
			this.root = core.root;
			this.config = core.config;
			this.stepsManager = core.stepsManager;
		}

		/**
		 * Save Clinix token to proxy (POST /source-credentials/save-clinix).
		 * הבאק פונה לפרוקסי POST /SourceCredentials/Save עם הטוקן ומחזיר source_credentials_id.
		 *
		 * @param {string} token Clinix API token
		 * @returns {Promise<number>} source_credentials_id
		 */
		async saveClinixCredentials(token) {
			const url = `${this.config.restUrl}/source-credentials/save-clinix`;
			const payload = { token };
			const requestSummary = {
				url,
				method: 'POST',
				body_summary: { token_length: token ? token.length : 0 }
			};
			if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.log === 'function') {
				window.ClinicQueueUtils.log('[save-clinix] נשלח לשרת:', requestSummary);
			} else {
				console.log('[save-clinix] נשלח לשרת:', requestSummary);
			}

			const response = await fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.config.restNonce
				},
				body: JSON.stringify(payload)
			});
			const data = await response.json();

			if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.log === 'function') {
				window.ClinicQueueUtils.log('[save-clinix] תגובת השרת:', {
					status: response.status,
					ok: response.ok,
					body: data
				});
			} else {
				console.log('[save-clinix] תגובת השרת:', { status: response.status, ok: response.ok, body: data });
			}

			if (!response.ok || !data.success) {
				const logToConsole = window.ClinicQueueUtils && typeof window.ClinicQueueUtils.log === 'function'
					? window.ClinicQueueUtils.log.bind(window.ClinicQueueUtils)
					: console.log;
				if (data.data) {
					if (data.data.debug) {
						logToConsole('[save-clinix] פרטי דיבוג (מה הועבר לפרוקסי ומה החזיר):', data.data.debug);
					}
					logToConsole('[save-clinix] data מלא מהשרת (כולל raw_body/status מהפרוקסי):', data.data);
				}
				throw new Error(data.message || 'שגיאה בשמירת הטוקן בפרוקסי');
			}
			const id = data.source_credentials_id;
			if (this.stepsManager) {
				this.stepsManager.updateFormData({ source_credentials_id: id });
			}
			return id;
		}

		/**
		 * Load calendars by Clinix: קודם שמירת הטוקן בפרוקסי, אחר כך טעינת יומנים עם source_creds_id.
		 *
		 * @param {string} token Clinix API token
		 */
		async loadCalendarsByToken(token) {
			const container = this.root.querySelector('.calendar-list-container');
			const errorDiv = this.root.querySelector('.calendar-error');

			if (!container) return;

			try {
				container.innerHTML = '<div class="calendar-loading" style="text-align:center;padding:2rem;"><div class="spinner"></div><p>טוען יומנים...</p></div>';
				if (errorDiv) errorDiv.style.display = 'none';

				const sourceCredsId = await this.saveClinixCredentials(token);

				const params = new URLSearchParams({
					source_creds_id: String(sourceCredsId),
					scheduler_id: '0'
				});
				const response = await fetch(
					`${this.config.restUrl}/scheduler/source-calendars?${params.toString()}`,
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
				window.ScheduleFormUtils.error('Error loading calendars by token', error);
			} else {
				console.error('[ScheduleForm] Error loading calendars by token:', error);
			}

			// Reset the calendar container so it doesn't show stale loading state
			if (container) {
				container.innerHTML = '';
			}
			if (errorDiv) {
				errorDiv.style.display = 'none';
			}

			// Navigate back to the Clinix token step (so the user can re-enter the token)
			if (this.stepsManager) {
				this.stepsManager.goToStep('clinix');
			}

			// Show inline error below the token input instead of a modal
			const tokenErrorEl = this.root.querySelector('.clinix-token-error');
			if (tokenErrorEl) {
				tokenErrorEl.textContent = 'הטוקן שהוזן אינו תקף או פג תוקף. נא להזין טוקן חדש.';
				tokenErrorEl.removeAttribute('hidden');
			}
		}
		}
	}

	window.ScheduleFormClinixCalendarManager = ScheduleFormClinixCalendarManager;
})(window);
