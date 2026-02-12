/**
 * Schedule Form Field Manager Module
 * Handles all form field population and Select2 management
 * 
 * Architecture: Prepared for future split into separate managers:
 * - Select2Manager (Select2 operations)
 * - FieldPopulator (Data population)
 * - PlaceholderManager (Placeholder management)
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Default configuration for field manager
	 * Can be overridden via constructor options
	 */
	const DEFAULT_CONFIG = {
		// Retry delays for async operations (ms)
		retryDelays: [10, 50],
		
		// CSS Selectors
		selectors: {
			clinicField: '.clinic-select-field',
			doctorField: '.doctor-select-field',
			clinicSelect: '.clinic-select',
			doctorSelect: '.doctor-select',
			select2Rendered: '.select2-container--clinic-queue .select2-selection--single .select2-selection__rendered'
		},
		
		// Messages
		messages: {
			clinics: {
				loading: 'טוען מרפאות...',
				error: 'שגיאה בטעינת מרפאות',
				empty: 'רשימת המרפאות שלך',
				noResults: 'לא נמצאו מרפאות'
			},
			doctors: {
				default: 'בחר רופא',
				loading: 'טוען רופאים...',
				noDoctors: 'לא נמצאו רופאים למרפאה זו',
				error: 'שגיאה בטעינת רופאים'
			}
		},
		
		// Select2 default options
		select2: {
			theme: 'clinic-queue',
			dir: 'rtl',
			language: 'he',
			width: '100%'
		}
	};

	/**
	 * Schedule Form Field Manager
	 * Manages clinic and doctor field loading, population, and Select2 updates
	 * 
	 * Architecture: Organized into logical sections for future extraction:
	 * 1. Configuration & Initialization
	 * 2. Utility Helpers (can become separate utility class)
	 * 3. Select2 Management (can become Select2Manager class)
	 * 4. Placeholder Management (can become PlaceholderManager class)
	 * 5. Field Population (can become FieldPopulator class)
	 * 6. Public API (loadClinics, loadDoctors)
	 */
	class ScheduleFormFieldManager {
		constructor(core, config = {}) {
			this.core = core;
			this.root = core.root;
			this.dataManager = core.dataManager;
			this.uiManager = core.uiManager;
			this.elements = core.elements;
			
			// Merge config with defaults
			this.config = this._mergeConfig(DEFAULT_CONFIG, config);
			
			// Placeholder texts - using config
			this.doctorPlaceholders = this.config.messages.doctors;
		}

		// ====================================================================
		// SECTION 1: Configuration & Initialization
		// ====================================================================

		/**
		 * Merge user config with defaults
		 * @private
		 * @param {Object} defaults - Default configuration
		 * @param {Object} userConfig - User-provided configuration
		 * @returns {Object} Merged configuration
		 */
		_mergeConfig(defaults, userConfig) {
			const merged = { ...defaults };
			
			// Deep merge nested objects
			if (userConfig.selectors) {
				merged.selectors = { ...defaults.selectors, ...userConfig.selectors };
			}
			if (userConfig.messages) {
				merged.messages = {
					clinics: { ...defaults.messages.clinics, ...(userConfig.messages.clinics || {}) },
					doctors: { ...defaults.messages.doctors, ...(userConfig.messages.doctors || {}) }
				};
			}
			if (userConfig.select2) {
				merged.select2 = { ...defaults.select2, ...userConfig.select2 };
			}
			if (userConfig.retryDelays) {
				merged.retryDelays = userConfig.retryDelays;
			}
			
			return merged;
		}

		// ====================================================================
		// SECTION 2: Utility Helpers
		// Future: Can be extracted to FieldManagerUtils class
		// ====================================================================

		/**
		 * Check if jQuery and Select2 are available
		 * @returns {boolean} True if both are available
		 */
		isSelect2Available() {
			return typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined';
		}

		/**
		 * Get jQuery select element
		 * @param {HTMLElement} select - Native select element
		 * @returns {jQuery|null} jQuery select or null
		 */
		getJQuerySelect(select) {
			return (typeof jQuery !== 'undefined' && select) ? jQuery(select) : null;
		}

		/**
		 * Clear select field (removes all options and adds empty option)
		 * @param {HTMLElement|jQuery} select - Select element
		 */
		clearSelectField(select) {
			const $select = this.getJQuerySelect(select);
			if ($select) {
				$select.empty().append('<option value=""></option>');
			} else if (select) {
				select.innerHTML = '<option value=""></option>';
			}
		}

		/**
		 * Set select disabled state
		 * @param {HTMLElement|jQuery} select - Select element
		 * @param {boolean} disabled - Disabled state
		 */
		setSelectDisabled(select, disabled) {
			const $select = this.getJQuerySelect(select);
			if ($select) {
				$select.prop('disabled', disabled);
			} else if (select) {
				select.disabled = disabled;
			}
		}

		/**
		 * Decode HTML entities
		 * @param {string} html - HTML string to decode
		 * @returns {string} Decoded string
		 */
		decodeHtml(html) {
			if (!html) return '';
			try {
				const doc = new DOMParser().parseFromString(html, "text/html");
				return doc.documentElement.textContent;
			} catch (e) {
				const txt = document.createElement("textarea");
				txt.innerHTML = html;
				return txt.value;
			}
		}

		/**
		 * Log error with ScheduleFormUtils fallback
		 * @param {string} message - Error message
		 * @param {Error} error - Error object
		 */
		logError(message, error) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.error(message, error);
			} else {
				console.error(`[ScheduleForm] ${message}`, error);
			}
		}

		/**
		 * Log message with ScheduleFormUtils fallback
		 * @param {string} message - Log message
		 * @param {*} data - Optional data
		 */
		log(message, data = null) {
			if (window.ScheduleFormUtils) {
				if (data !== null && data !== undefined) {
					window.ScheduleFormUtils.log(message, data);
				} else {
					window.ScheduleFormUtils.log(message);
				}
			} else {
				if (data !== null && data !== undefined) {
					console.log(`[ScheduleForm] ${message}`, data);
				} else {
					console.log(`[ScheduleForm] ${message}`);
				}
			}
		}

		// ====================================================================
		// SECTION 3: Select2 Management
		// Future: Can be extracted to Select2Manager class
		// ====================================================================


		/**
		 * Reinitialize Select2 with new options
		 * @param {jQuery} $select - jQuery select element
		 * @param {Object} options - Select2 options
		 */
		reinitializeSelect2($select, options) {
			if (!$select || !this.isSelect2Available()) {
				return;
			}

			// Destroy existing Select2 instance if initialized
			if ($select.hasClass('select2-hidden-accessible')) {
				$select.select2('destroy');
			}

			// Initialize Select2 with provided options
			$select.select2(options);
		}

		/**
		 * Build Select2 options from config
		 * @param {Object} customOptions - Custom options to override defaults
		 * @returns {Object} Select2 options object
		 */
		buildSelect2Options(customOptions = {}) {
			return {
				...this.config.select2,
				...customOptions
			};
		}

		// ====================================================================
		// SECTION 4: Placeholder Management
		// Future: Can be extracted to PlaceholderManager class
		// ====================================================================

		/**
		 * Update Select2 rendered text (placeholder)
		 * @param {string} selector - CSS selector for rendered element
		 * @param {string} text - Placeholder text
		 * @param {Array<number>} retryDelays - Array of delays in ms for retries
		 */
		updateSelect2RenderedText(selector, text, retryDelays = null) {
			const delays = retryDelays || this.config.retryDelays;
			
			const doUpdate = () => {
				const rendered = this.root.querySelector(selector);
				if (rendered) {
					rendered.setAttribute('title', '');
					rendered.setAttribute('data-placeholder', text);
					rendered.innerHTML = '<span class="select2-selection__placeholder">' + text + '</span>';
				}
			};

			// Try immediately
			doUpdate();

			// Retry with delays
			delays.forEach(delay => {
				setTimeout(doUpdate, delay);
			});
		}

		/**
		 * Update placeholder with retries (for doctor field)
		 * @param {string} state - Placeholder state key
		 * @param {boolean} force - Force update even if field has value
		 * @param {Array<number>} retryDelays - Array of delays in ms for retries
		 */
		updatePlaceholderWithRetries(state, force, retryDelays = null) {
			const delays = retryDelays || this.config.retryDelays;
			const placeholderText = this.doctorPlaceholders[state] || this.doctorPlaceholders.default;
			const $doctorSelect = this.getJQuerySelect(this.root.querySelector(this.config.selectors.doctorSelect));
			const hasValue = $doctorSelect && $doctorSelect.val() && $doctorSelect.val() !== '';
			
			// Only update if field is empty (unless forced)
			if (hasValue && !force) {
				return;
			}
			
			const selector = `${this.config.selectors.doctorField} ${this.config.selectors.select2Rendered}`;
			
			const doUpdate = () => {
				const doctorRendered = this.root.querySelector(selector);
				if (doctorRendered) {
					// Only update if field is still empty (unless forced)
					if (force || !$doctorSelect || !$doctorSelect.val() || $doctorSelect.val() === '') {
						doctorRendered.setAttribute('title', '');
						doctorRendered.setAttribute('data-placeholder', placeholderText);
						doctorRendered.innerHTML = '<span class="select2-selection__placeholder">' + placeholderText + '</span>';
					}
				}
			};
			
			// Try immediately
			doUpdate();
			
			// Retry with delays if Select2 is initialized
			if ($doctorSelect && $doctorSelect.hasClass('select2-hidden-accessible')) {
				delays.forEach(delay => {
					setTimeout(doUpdate, delay);
				});
			}
		}

		/**
		 * Update doctor field placeholder text
		 * @param {string} state - One of: 'default', 'loading', 'noDoctors', 'error'
		 * @param {boolean} force - Force update even if field has value
		 */
		updateDoctorPlaceholder(state = 'default', force = false) {
			this.updatePlaceholderWithRetries(state, force);
		}

		// ====================================================================
		// SECTION 5: Field Population
		// Future: Can be extracted to FieldPopulator class
		// ====================================================================

		/**
		 * Generic method to populate select field
		 * Replaces populateClinicSelect and populateDoctorSelect
		 * @param {Array} data - Array of data items
		 * @param {HTMLElement} select - Select element
		 * @param {Object} options - Population options
		 * @param {Function} options.getValue - Function to get value from data item
		 * @param {Function} options.getText - Function to get text from data item
		 * @param {Function} options.getAttributes - Optional function to get data attributes
		 * @param {Object} options.select2Config - Select2 configuration
		 * @param {string} options.fieldSelector - CSS selector for field wrapper
		 * @param {string} options.placeholderSelector - CSS selector for placeholder element
		 * @param {Object} options.placeholderTexts - Placeholder texts (hasData, noData)
		 */
		populateSelect(data, select, options = {}) {
			if (!select) return;

			const hasData = data && data.length > 0;
			const $select = this.getJQuerySelect(select);
			
			// Clear and add default option
			this.clearSelectField(select);

			// Add options
			if (hasData && options.getValue && options.getText) {
				data.forEach(item => {
					const option = document.createElement('option');
					option.value = options.getValue(item);
					option.textContent = options.getText(item);
					
					// Add data attributes if provided
					if (options.getAttributes) {
						const attributes = options.getAttributes(item);
						Object.keys(attributes).forEach(key => {
							if (attributes[key]) {
								option.setAttribute(key, attributes[key]);
							}
						});
					}
					
					if ($select) {
						$select.append(option);
					} else {
						select.appendChild(option);
					}
				});
			}

			// Set disabled state
			this.setSelectDisabled(select, !hasData);

			// Update field styling
			if (options.fieldSelector) {
				const field = this.root.querySelector(options.fieldSelector);
				if (field) {
					if (hasData) {
						field.classList.remove('field-disabled');
					} else {
						field.classList.add('field-disabled');
					}
				}
			}

			// Update Select2 after adding options
			if ($select && this.isSelect2Available() && options.select2Config) {
				const select2Options = this.buildSelect2Options(options.select2Config);
				this.reinitializeSelect2($select, select2Options);
				
				// Ensure disabled state is correct
				this.setSelectDisabled($select, !hasData);
				
				// Update placeholder if provided
				if (options.placeholderSelector && options.placeholderTexts) {
					const placeholderText = hasData ? options.placeholderTexts.hasData : options.placeholderTexts.noData;
					setTimeout(() => {
						const rendered = this.root.querySelector(options.placeholderSelector);
						if (rendered && (!$select || !$select.val() || $select.val() === '')) {
							this.updateSelect2RenderedText(options.placeholderSelector, placeholderText, [0]);
						}
					}, 0);
				}
			}
		}

		/**
		 * Populate clinic select (uses generic populateSelect)
		 * @param {Array} clinics - Array of clinic objects
		 * @param {HTMLElement} clinicSelect - Clinic select element
		 */
		populateClinicSelect(clinics, clinicSelect) {
			const placeholderSelector = `${this.config.selectors.clinicField} ${this.config.selectors.select2Rendered}`;
			
			this.populateSelect(clinics, clinicSelect, {
				getValue: (clinic) => clinic.id,
				getText: (clinic) => {
					const rawTitle = clinic.title?.rendered || clinic.title || clinic.name || '';
					return this.decodeHtml(rawTitle);
				},
				select2Config: {
					minimumResultsForSearch: -1, // Disable search for clinic field
					placeholder: clinics && clinics.length > 0 ? this.config.messages.clinics.empty : this.config.messages.clinics.noResults,
					allowClear: false,
					dropdownParent: jQuery(this.root)
				},
				fieldSelector: this.config.selectors.clinicField,
				placeholderSelector: placeholderSelector,
				placeholderTexts: {
					hasData: this.config.messages.clinics.empty,
					noData: this.config.messages.clinics.noResults
				}
			});
		}

		/**
		 * Populate doctor select (uses generic populateSelect)
		 * @param {Array} doctors - Array of doctor objects
		 * @param {HTMLElement} doctorSelect - Doctor select element
		 * @param {Object} dataManager - Data manager instance
		 */
		populateDoctorSelect(doctors, doctorSelect, dataManager) {
			const $doctorSelect = this.getJQuerySelect(doctorSelect);
			const hasDoctors = doctors && doctors.length > 0;
			
			// Use generic populateSelect
			this.populateSelect(doctors, doctorSelect, {
				getValue: (doctor) => doctor.id,
				getText: (doctor) => dataManager.getDoctorName(doctor),
				select2Config: {
					minimumResultsForSearch: -1, // Disable search for doctor field
					placeholder: this.doctorPlaceholders.default,
					allowClear: false, // No clear button for doctor field
					dropdownParent: $doctorSelect ? $doctorSelect.closest('.jet-form-builder__field-wrap') : jQuery(this.root)
				},
				fieldSelector: this.config.selectors.doctorField
			});

			// Update placeholder based on state
			const state = hasDoctors ? 'default' : 'noDoctors';
			this.updatePlaceholderWithRetries(state, false);
		}

		// ====================================================================
		// SECTION 6: Public API
		// ====================================================================

		/**
		 * Load clinics
		 */
		async loadClinics() {
			if (!this.elements.clinicSelect) return;

			try {
				this.clearSelectField(this.elements.clinicSelect);
				this.elements.clinicSelect.innerHTML = `<option value="">${this.config.messages.clinics.loading}</option>`;
				this.setSelectDisabled(this.elements.clinicSelect, true);

				// Update rendered text to show loading
				const placeholderSelector = `${this.config.selectors.clinicField} ${this.config.selectors.select2Rendered}`;
				this.updateSelect2RenderedText(placeholderSelector, this.config.messages.clinics.loading);

				const clinics = await this.dataManager.loadClinics();
				
				// Populate clinics
				this.populateClinicSelect(clinics, this.elements.clinicSelect);
				
				// Auto-select if only one clinic
				if (clinics && clinics.length === 1) {
					const singleClinicId = clinics[0].id;
					if (this.elements.clinicSelect) {
						const $clinicSelect = this.getJQuerySelect(this.elements.clinicSelect);
						if ($clinicSelect) {
							$clinicSelect.val(singleClinicId).trigger('change');
						} else {
							this.elements.clinicSelect.value = singleClinicId;
							this.elements.clinicSelect.dispatchEvent(new Event('change'));
						}
						this.log('Auto-selected single clinic:', singleClinicId);
					}
				}
			} catch (error) {
				this.logError('Error loading clinics', error);
				this.elements.clinicSelect.innerHTML = `<option value="">${this.config.messages.clinics.error}</option>`;
				
				// Update rendered text to show error
				const placeholderSelector = `${this.config.selectors.clinicField} ${this.config.selectors.select2Rendered}`;
				this.updateSelect2RenderedText(placeholderSelector, this.config.messages.clinics.error);
				
				this.uiManager.showError(this.config.messages.clinics.error);
			}
		}

		/**
		 * Load doctors for clinic
		 */
		async loadDoctors(clinicId) {
			if (!this.elements.doctorSelect) return;

			try {
				const $doctorSelect = this.getJQuerySelect(this.elements.doctorSelect);
				const doctorField = this.root.querySelector(this.config.selectors.doctorField);
				
				// Update placeholder to show loading state FIRST (before Select2 updates)
				this.updatePlaceholderWithRetries('loading', true);
				
				// Disable and show loading
				this.setSelectDisabled(this.elements.doctorSelect, true);
				this.clearSelectField(this.elements.doctorSelect);
				
				// Update Select2 if initialized
				if ($doctorSelect && $doctorSelect.hasClass('select2-hidden-accessible')) {
					$doctorSelect.trigger('change.select2');
					// Update placeholder again after Select2 updates
					setTimeout(() => {
						this.updatePlaceholderWithRetries('loading', true);
					}, this.config.retryDelays[0]);
				}
				
				// Add disabled class for styling
				if (doctorField) {
					doctorField.classList.add('field-disabled');
				}

				const doctors = await this.dataManager.loadDoctors(clinicId);
				
				// Populate doctors
				this.populateDoctorSelect(doctors, this.elements.doctorSelect, this.dataManager);
			} catch (error) {
				this.logError('Error loading doctors', error);
				const $doctorSelect = this.getJQuerySelect(this.elements.doctorSelect);
				this.clearSelectField(this.elements.doctorSelect);
				
				if ($doctorSelect && $doctorSelect.hasClass('select2-hidden-accessible')) {
					$doctorSelect.trigger('change.select2');
				}
				
				// Update placeholder to show error state
				this.updatePlaceholderWithRetries('error', true);
				this.uiManager.showError(this.config.messages.doctors.error);
			}
		}

		/**
		 * Load schedule data from proxy for Clinix (active hours + reasons) and show in read-only mode.
		 * Called when entering schedule-settings step with action_type === 'clinix'.
		 * מעביר רק: drweb_calendar_id (→ drwebCalendarID, היומן שנבחר מ-GetAllSourceCalendars)
		 * ו-source_creds_id (→ sourceCredsID, מזהה מ-SourceCredentials/Save). בלי טוקן מהפרונט.
		 */
		async loadClinixScheduleData() {
			const stepsManager = this.core.stepsManager;
			const drwebCalendarId = (stepsManager.formData.selected_calendar_id || '').toString().trim();
			const sourceCredsId = stepsManager.formData.source_credentials_id || '';
			if (!drwebCalendarId || !sourceCredsId) {
				this.uiManager.showError('חסרים יומן מקור או מזהה credentials. אנא חזור ובחר יומן.');
				return;
			}

			const restUrl = this.core.config.restUrl || '';
			const restNonce = this.core.config.restNonce || '';
			const scheduleStep = this.root.querySelector('.schedule-settings-step');
			const saveBtn = this.root.querySelector('.save-schedule-btn');
			const container = scheduleStep ? scheduleStep.querySelector('.days-schedule-container') : null;
			let loader = null;

			if (container) {
				container.style.position = 'relative';
				container.setAttribute('data-loading', 'true');
				loader = document.createElement('div');
				loader.className = 'clinic-queue-loading-overlay';
				loader.innerHTML = '<div class="spinner"></div><p>טוען ימים ושעות...</p>';
				loader.setAttribute('style', 'position:absolute;inset:0;background:rgba(255,255,255,0.9);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;');
				container.appendChild(loader);
			}

			const params = new URLSearchParams({
				source_creds_id: String(sourceCredsId),
				drweb_calendar_id: drwebCalendarId,
			});
			const urlHours = `${restUrl}/scheduler/drweb-calendar-active-hours?${params}`;
			const urlReasons = `${restUrl}/scheduler/drweb-calendar-reasons?${params}`;
			const logToConsole = (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.log === 'function')
				? window.ClinicQueueUtils.log.bind(window.ClinicQueueUtils)
				: console.log;
			logToConsole('[drweb-calendar] נשלח לשרת:', { urlHours, urlReasons, params: Object.fromEntries(params) });

			try {
				const [hoursRes, reasonsRes] = await Promise.all([
					fetch(urlHours, { headers: { 'X-WP-Nonce': restNonce } }),
					fetch(urlReasons, { headers: { 'X-WP-Nonce': restNonce } })
				]);

				const hoursData = await hoursRes.json();
				const reasonsData = await reasonsRes.json();

				logToConsole('[drweb-calendar] תגובת שעות פעילות:', { status: hoursRes.status, ok: hoursRes.ok, body: hoursData });
				logToConsole('[drweb-calendar] תגובת סיבות:', { status: reasonsRes.status, ok: reasonsRes.ok, body: reasonsData });

				const logError = (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.error === 'function')
					? window.ClinicQueueUtils.error.bind(window.ClinicQueueUtils)
					: console.error;

				if (!hoursRes.ok) {
					if (hoursRes.status === 404) {
						logError('[drweb-calendar] 404 – הנתיב לא רשום ב-WordPress. הבקשה לא הגיעה לפרוקסי GetDRWebCalendarActiveHours.', { url: urlHours, body: hoursData });
					} else {
						logError('[drweb-calendar] שגיאה מתגובת שעות פעילות (פרוקסי/שרת):', { status: hoursRes.status, body: hoursData });
						if (hoursData && hoursData.data) {
							logToConsole('[drweb-calendar] דיבוג שעות (מה הועבר לפרוקסי ומה החזיר):', hoursData.data);
						}
					}
				}
				if (!reasonsRes.ok) {
					if (reasonsRes.status === 404) {
						logError('[drweb-calendar] 404 – הנתיב לא רשום ב-WordPress. הבקשה לא הגיעה לפרוקסי GetDRWebCalendarReasons.', { url: urlReasons, body: reasonsData });
					} else {
						logError('[drweb-calendar] שגיאה מתגובת סיבות (פרוקסי/שרת):', { status: reasonsRes.status, body: reasonsData });
						if (reasonsData && reasonsData.data) {
							logToConsole('[drweb-calendar] דיבוג סיבות (מה הועבר לפרוקסי ומה החזיר):', reasonsData.data);
						}
					}
				}

				if (loader && loader.parentNode) {
					loader.remove();
				}
				if (container) {
					container.removeAttribute('data-loading');
				}

				const hoursList = (hoursData && hoursData.result && Array.isArray(hoursData.result)) ? hoursData.result : [];
				const reasonsList = (reasonsData && reasonsData.result && Array.isArray(reasonsData.result)) ? reasonsData.result : [];

				const daysMap = {};
				hoursList.forEach((item) => {
					const raw = item || {};
					const weekDay = (raw.weekDay || raw.WeekDay || '').toString().toLowerCase();
					const from = (raw.fromUTC || raw.FromUTC || raw.from || '').toString().substring(0, 5);
					const to = (raw.toUTC || raw.ToUTC || raw.to || '').toString().substring(0, 5);
					if (!weekDay) return;
					if (!daysMap[weekDay]) daysMap[weekDay] = [];
					daysMap[weekDay].push({ start_time: from || '08:00', end_time: to || '18:00' });
				});

				const treatmentsMap = reasonsList.map((item) => {
					const raw = item || {};
					const name = (raw.name || raw.Name || '').toString() || 'טיפול';
					const duration = parseInt(raw.duration || raw.Duration || 0, 10) || 30;
					return {
						treatment_type: name,
						sub_speciality: 0,
						cost: 0,
						duration: duration
					};
				});

				this.applyClinixReadOnlyState(daysMap, treatmentsMap);
				if (saveBtn) {
					saveBtn.textContent = 'יצירת יומן';
				}
			} catch (error) {
				this.logError('Error loading Clinix schedule data', error);
				if (loader && loader.parentNode) loader.remove();
				if (container) container.removeAttribute('data-loading');
				this.uiManager.showError('שגיאה בטעינת ימים וטיפולים מהמערכת.');
			}
		}

		/**
		 * Apply Clinix data to schedule-settings form.
		 * Days/hours stay read-only. Treatments (reasons) use same repeater as Google:
		 * default row = first reason (read-only), one editable row with dropdown of all reasons,
		 * add row and remove row (except default) like Google flow.
		 *
		 * @param {Object} daysMap - { dayKey: [ { start_time, end_time } ] }
		 * @param {Array} treatmentsMap - [ { treatment_type, duration, sub_speciality, cost } ]
		 */
		applyClinixReadOnlyState(daysMap, treatmentsMap) {
			const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

			dayKeys.forEach((dayKey) => {
				const checkbox = this.root.querySelector(`.day-checkbox input[data-day="${dayKey}"]`);
				const dayRow = this.root.querySelector(`.day-row[data-day-row="${dayKey}"]`);
				const dayTimeRange = dayRow ? dayRow.querySelector('.day-time-range') : null;
				const timeRangesList = dayRow ? dayRow.querySelector('.time-ranges-list[data-day="' + dayKey + '"]') : null;

				const ranges = daysMap[dayKey];
				if (ranges && ranges.length > 0 && checkbox && dayTimeRange && timeRangesList) {
					checkbox.checked = true;
					checkbox.disabled = true;
					dayTimeRange.style.display = '';

					const rows = timeRangesList.querySelectorAll('.time-range-row');
					ranges.forEach((range, idx) => {
						const row = rows[idx];
						if (!row) return;
						const fromSelect = row.querySelector('.from-time');
						const toSelect = row.querySelector('.to-time');
						if (fromSelect && range.start_time) fromSelect.value = range.start_time;
						if (toSelect && range.end_time) toSelect.value = range.end_time;
						if (fromSelect) fromSelect.disabled = true;
						if (toSelect) toSelect.disabled = true;
					});
				} else if (checkbox) {
					checkbox.disabled = true;
				}
			});

			this.root.querySelectorAll('.add-time-split-btn').forEach((btn) => { btn.style.display = 'none'; });
			this.root.querySelectorAll('.remove-time-split-btn').forEach((btn) => { btn.style.display = 'none'; });

			const repeater = this.root.querySelector('.treatments-repeater');
			if (!repeater || !treatmentsMap.length) return;

			this.root.clinicTreatments = treatmentsMap.map((t) => ({
				treatment_type: t.treatment_type,
				sub_speciality: t.sub_speciality || 0,
				cost: t.cost || 0,
				duration: t.duration || 30
			}));

			const defaultRow = repeater.querySelector('.treatment-row-default');
			const editableRows = repeater.querySelectorAll('.treatment-row:not(.treatment-row-default)');
			const templateRow = editableRows.length > 0 ? editableRows[0].cloneNode(true) : null;
			editableRows.forEach((row) => row.remove());

			const firstReason = treatmentsMap[0];
			const firstOptionValue = JSON.stringify({
				treatment_type: firstReason.treatment_type,
				sub_speciality: firstReason.sub_speciality || 0,
				cost: firstReason.cost || 0,
				duration: firstReason.duration || 30
			});
			if (defaultRow) {
				const defaultSelect = defaultRow.querySelector('select.treatment-name-select');
				if (defaultSelect) {
					defaultSelect.innerHTML = '<option value="' + this.escapeHtml(firstOptionValue) + '">' + this.escapeHtml(firstReason.treatment_type) + '</option>';
					defaultSelect.value = firstOptionValue;
					defaultSelect.disabled = true;
				}
			}

			if (templateRow) {
				templateRow.removeAttribute('data-is-default');
				templateRow.setAttribute('data-row-index', '1');
				templateRow.querySelectorAll('.select2-container').forEach((el) => el.remove());
				const select = templateRow.querySelector('.treatment-name-select');
				if (select) {
					select.removeAttribute('data-select2-id');
					select.removeAttribute('aria-hidden');
					select.removeAttribute('tabindex');
					select.classList.remove('select2-hidden-accessible');
					select.dataset.rowIndex = '1';
					select.selectedIndex = 0;
				}
				repeater.appendChild(templateRow);
				this.uiManager.updateTreatmentSelectsAvailability();
				const removeBtn = templateRow.querySelector('.remove-treatment-btn');
				if (removeBtn) {
					removeBtn.style.display = 'none';
					removeBtn.addEventListener('click', () => {
						const row = removeBtn.closest('.treatment-row');
						if (row && !row.classList.contains('treatment-row-default')) {
							row.remove();
							this.uiManager.updateTreatmentSelectsAvailability();
							const remaining = repeater.querySelectorAll('.treatment-row:not(.treatment-row-default)');
							if (remaining.length === 1) {
								const lastRemove = remaining[0].querySelector('.remove-treatment-btn');
								if (lastRemove) lastRemove.style.display = 'none';
							}
						}
					});
				}
				this.uiManager.updateAddTreatmentButtonVisibility();
			}
		}

		escapeHtml(str) {
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	}

	// Export to global scope
	window.ScheduleFormFieldManager = ScheduleFormFieldManager;

})(window);
