/**
 * Schedule Form Google Auth Module
 * מטפל ב-OAuth flow עם Google Calendar באמצעות Google Identity Services
 * 
 * @package Clinic_Queue_Management
 */

(function(window, document) {
	'use strict';

	/**
	 * Schedule Form Google Auth Manager
	 * מנהל את תהליך ההתחברות לגוגל עם Google Identity Services (GIS)
	 */
	class ScheduleFormGoogleAuthManager {
		constructor(config) {
			this.config = config || {};
			this.clientId = this.config.googleClientId || '';
			this.scopes = this.config.googleScopes || 'https://www.googleapis.com/auth/calendar';
			this.restUrl = this.config.restUrl || '/wp-json/clinic-queue/v1';
			this.restNonce = this.config.restNonce || '';
			
			// State
			this.schedulerId = null;
			this.tokenClient = null;
			this.isGsiLoaded = false;
			
			console.log('[GoogleAuth] Module initialized');
			
			// טעינת Google Identity Services
			this.loadGoogleIdentityServices();
		}

		/**
		 * טעינת Google Identity Services library
		 */
		loadGoogleIdentityServices() {
			// בדיקה אם הספרייה כבר נטענה
			if (window.google && window.google.accounts) {
				this.isGsiLoaded = true;
				this.initializeTokenClient();
				return;
			}

			// טעינת הספרייה
			const script = document.createElement('script');
			script.src = 'https://accounts.google.com/gsi/client';
			script.async = true;
			script.defer = true;
			script.onload = () => {
				console.log('[GoogleAuth] Google Identity Services loaded');
				this.isGsiLoaded = true;
				this.initializeTokenClient();
			};
			script.onerror = () => {
				console.error('[GoogleAuth] Failed to load Google Identity Services');
			};
			document.head.appendChild(script);
		}

		/**
		 * אתחול Token Client
		 */
		initializeTokenClient() {
			if (!window.google || !window.google.accounts || !window.google.accounts.oauth2) {
				console.error('[GoogleAuth] Google Identity Services not available');
				return;
			}

			if (!this.clientId) {
				console.error('[GoogleAuth] Client ID not configured');
				return;
			}

		try {
			this.tokenClient = window.google.accounts.oauth2.initCodeClient({
				client_id: this.clientId,
				scope: this.scopes,
				ux_mode: 'popup',
				// Request offline access to get refresh token
				access_type: 'offline',
				// Prompt for consent to ensure we get refresh token
				prompt: 'consent',
				callback: (response) => {
					this.handleAuthResponse(response);
				},
				error_callback: (error) => {
					this.handleAuthError(error);
				}
			});

			console.log('[GoogleAuth] Token client initialized with popup mode and offline access');
		} catch (error) {
			console.error('[GoogleAuth] Failed to initialize token client:', error);
		}
		}

		/**
		 * הגדרת scheduler ID
		 */
		setSchedulerId(schedulerId) {
			this.schedulerId = schedulerId;
			console.log('[GoogleAuth] Scheduler ID set:', schedulerId);
		}

		/**
		 * טיפול בתשובת OAuth
		 */
		handleAuthResponse(response) {
			console.log('[GoogleAuth] Auth response received');

			if (response.code) {
				console.log('[GoogleAuth] Got authorization code');
				
				// שמירת הקוד וקריאה לפונקציה שתשלח אותו לשרת
				if (this.onAuthSuccess) {
					this.onAuthSuccess(response.code);
				}
			} else if (response.error) {
				console.error('[GoogleAuth] Auth error:', response.error);
				if (this.onAuthError) {
					this.onAuthError(new Error(response.error));
				}
			}
		}

		/**
		 * טיפול בשגיאת OAuth
		 */
		handleAuthError(error) {
			console.error('[GoogleAuth] OAuth error:', error);
			if (this.onAuthError) {
				this.onAuthError(error);
			}
		}

		/**
		 * פתיחת OAuth popup (גרסה חדשה עם GIS)
		 */
		openOAuthPopup() {
			if (!this.isGsiLoaded) {
				console.error('[GoogleAuth] Google Identity Services not loaded yet');
				return Promise.reject(new Error('Google Identity Services not loaded'));
			}

			if (!this.tokenClient) {
				console.error('[GoogleAuth] Token client not initialized');
				return Promise.reject(new Error('Token client not initialized'));
			}

			if (!this.schedulerId) {
				console.error('[GoogleAuth] Scheduler ID not set');
				return Promise.reject(new Error('Scheduler ID not set'));
			}

			return new Promise((resolve, reject) => {
				// הגדרת callbacks
				this.onAuthSuccess = (code) => {
					resolve(code);
				};
				
				this.onAuthError = (error) => {
					reject(error);
				};

				// פתיחת popup
				console.log('[GoogleAuth] Requesting access token...');
				this.tokenClient.requestCode();
			});
		}

		/**
		 * שליחת authorization code לשרת
		 */
		async sendCodeToServer(code) {
			if (!this.schedulerId) {
				throw new Error('Scheduler ID not set');
			}

			console.log('[GoogleAuth] Sending code to server...');

			const url = `${this.restUrl}/google/connect`;
			
			const response = await fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.restNonce
				},
				body: JSON.stringify({
					code: code,
					scheduler_id: this.schedulerId
				})
			});

			const data = await response.json();

			if (!response.ok) {
				console.error('[GoogleAuth] Server error:', data);
				throw new Error(data.message || 'Failed to connect to Google');
			}

			console.log('[GoogleAuth] Successfully connected:', data);
			return data;
		}

		/**
		 * תהליך מלא: פתיחת popup + שליחה לשרת
		 */
		async connect() {
			try {
				console.log('[GoogleAuth] Starting connection flow...');
				
				// שלב 1: פתיחת popup וקבלת code
				const code = await this.openOAuthPopup();
				console.log('[GoogleAuth] Got authorization code');
				
				// שלב 2: שליחת code לשרת
				const result = await this.sendCodeToServer(code);
				console.log('[GoogleAuth] Connection successful');
				
				return result;
			} catch (error) {
				console.error('[GoogleAuth] Connection failed:', error);
				throw error;
			}
		}

		/**
		 * בדיקת סטטוס חיבור
		 */
		async checkConnectionStatus() {
			if (!this.schedulerId) {
				return false;
			}

			// ניתן להוסיף endpoint לבדיקת סטטוס
			// לעת עתה מחזירים false
			return false;
		}
	}

	// Export to global scope
	window.ScheduleFormGoogleAuthManager = ScheduleFormGoogleAuthManager;

	console.log('[GoogleAuth] Module loaded');

})(window, document);

