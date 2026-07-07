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
			noClinic: 'יש לבחור מרפאה לפני בחירת הרופא',
			default: 'בחר רופא',
			loading: 'טוען רופאים...',
			noDoctors: 'לא קיימים אצלך רופאים במרפאה זו',
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

	/**
	 * Initialize Select2 on the doctor field in its initial disabled state.
	 * Called once during form init (before any clinic is selected) so the
	 * doctor field shows the correct styled Select2 UI from the start instead
	 * of a plain native select.
	 */
	initializeDoctorSelect() {
		if (!this.isSelect2Available() || !this.elements.doctorSelect) {
			return;
		}
		const $select = this.getJQuerySelect(this.elements.doctorSelect);
		if (!$select) {
			return;
		}
		const select2Options = this.buildSelect2Options({
			minimumResultsForSearch: -1,
			placeholder: this.doctorPlaceholders.noClinic,
			allowClear: false,
			dropdownParent: jQuery(this.root),
			dropdownCssClass: 'clinic-queue-doctor-dropdown',
			templateResult: (item) => this._renderDoctorOption(item),
			templateSelection: (item) => this._renderDoctorSelection(item),
		});
		this.reinitializeSelect2($select, select2Options);
		this.updatePlaceholderWithRetries('noClinic', true);
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
		 * @param {string} state - One of: 'noClinic', 'default', 'loading', 'noDoctors', 'error'
		 * @param {boolean} force - Force update even if field has value
		 */
		updateDoctorPlaceholder(state = 'default', force = false) {
			this.updatePlaceholderWithRetries(state, force);
		}

		/**
		 * Reset doctor select to the pre-clinic default: disabled with no-clinic placeholder.
		 */
		resetDoctorSelectNoClinic() {
			if (!this.elements.doctorSelect) {
				return;
			}

			const select = this.elements.doctorSelect;
			const $select = this.getJQuerySelect(select);
			const placeholderText = this.doctorPlaceholders.noClinic;
			const doctorField = this.root.querySelector(this.config.selectors.doctorField);

			if ($select) {
				$select.empty().append(`<option value="">${placeholderText}</option>`);
				$select.prop('disabled', true).val('');
			} else {
				select.innerHTML = `<option value="">${placeholderText}</option>`;
				select.disabled = true;
				select.value = '';
			}

			if (doctorField) {
				doctorField.classList.add('field-disabled');
			}

			if ($select && $select.hasClass('select2-hidden-accessible')) {
				$select.trigger('change.select2');
			}

			this.updatePlaceholderWithRetries('noClinic', true);
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

				// Disable individual options when the caller signals they are unavailable
				if (options.getDisabled && options.getDisabled(item)) {
					option.disabled = true;
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
	 * Populate doctor select with rich Select2 template (photo, license, specialty)
	 * @param {Array} doctors - Array of doctor objects
	 * @param {HTMLElement} doctorSelect - Doctor select element
	 * @param {Object} dataManager - Data manager instance
	 * @param {number[]} [bookedDoctorIds=[]] - Doctor IDs that already have a scheduler for the selected clinic
	 */
	populateDoctorSelect(doctors, doctorSelect, dataManager, bookedDoctorIds = []) {
		const $doctorSelect = this.getJQuerySelect(doctorSelect);
		const hasDoctors = doctors && doctors.length > 0;
		const bookedSet = new Set((bookedDoctorIds || []).map(Number));

		// Build doctor data map for template rendering
		this._doctorDataMap = {};
		if (hasDoctors) {
			doctors.forEach(doctor => {
				this._doctorDataMap[doctor.id] = {
					name: dataManager.getDoctorName(doctor),
					license: dataManager.getDoctorLicenseNumber(doctor),
					thumbnail: dataManager.getDoctorThumbnail(doctor),
					specialties: dataManager.getDoctorSpecialties ? dataManager.getDoctorSpecialties(doctor) : [],
					booked: bookedSet.has(Number(doctor.id)),
				};
			});
		}

	const doctorPlaceholderText = hasDoctors ? this.doctorPlaceholders.default : this.doctorPlaceholders.noDoctors;

	this.populateSelect(doctors, doctorSelect, {
		getValue: (doctor) => doctor.id,
		getText: (doctor) => dataManager.getDoctorName(doctor),
		getDisabled: (doctor) => bookedSet.has(Number(doctor.id)),
		select2Config: {
			minimumResultsForSearch: 0,
			placeholder: doctorPlaceholderText,
			allowClear: hasDoctors,
			dropdownParent: jQuery(this.root),
			dropdownCssClass: 'clinic-queue-doctor-dropdown',
			templateResult: (item) => this._renderDoctorOption(item),
			templateSelection: (item) => this._renderDoctorSelection(item),
		},
		fieldSelector: this.config.selectors.doctorField
	});

	// Auto-focus search field and set placeholder when dropdown opens
	if ($doctorSelect) {
		$doctorSelect.off('select2:open.doctor-search');
		$doctorSelect.on('select2:open.doctor-search', () => {
			setTimeout(() => {
				const $searchField = jQuery('.clinic-queue-doctor-dropdown .select2-search__field');
				$searchField.attr('placeholder', 'חפש רופא...');
				$searchField.trigger('focus');
			}, 0);
		});
	}

	// Update placeholder based on state — force when no doctors so it overrides any Select2 timing
	const state = hasDoctors ? 'default' : 'noDoctors';
	this.updatePlaceholderWithRetries(state, !hasDoctors);
	}

	/**
	 * Render a rich doctor option card for the Select2 dropdown
	 * @private
	 * @param {Object} item - Select2 data item
	 * @returns {jQuery} Rendered element
	 */
	_renderDoctorOption(item) {
		if (!item.id) {
			return jQuery('<span>').text(item.text || '');
		}

		const data = this._doctorDataMap && this._doctorDataMap[item.id];
		if (!data) {
			return jQuery('<span>').text(item.text || '');
		}

		const $wrap = jQuery('<span class="clinic-queue-doctor-result">');

		// For booked doctors: add a full-width notice strip above the card row
		if (data.booked) {
			$wrap.addClass('clinic-queue-doctor-result--booked');
			$wrap.append(
				jQuery('<span class="clinic-queue-doctor-booked-badge">').text('כבר קיים יומן במרפאה עבור רופא זה')
			);
		}

		// Inner row: thumbnail + info + specialties (always a flex-row)
		const $row = jQuery('<span class="clinic-queue-doctor-result__row">');

		// Thumbnail (rightmost in RTL)
		const $thumb = jQuery('<span class="clinic-queue-doctor-thumbnail">');
		if (data.thumbnail) {
			$thumb.append(jQuery('<img>').attr({ src: data.thumbnail, alt: '' }));
		}
		$row.append($thumb);

		// Info: name + license (middle)
		const $info = jQuery('<span class="clinic-queue-doctor-info">');
		$info.append(jQuery('<span class="clinic-queue-doctor-name">').text(data.name));
		if (data.license) {
			$info.append(jQuery('<span class="clinic-queue-doctor-license">').text(data.license));
		}
		$row.append($info);

		// Specialty badges (leftmost in RTL) — show up to 2, then "+N" for the rest
		if (data.specialties && data.specialties.length > 0) {
			const MAX_VISIBLE = 2;
			const $badges = jQuery('<span class="clinic-queue-doctor-specialties">');

			data.specialties.slice(0, MAX_VISIBLE).forEach(name => {
				$badges.append(jQuery('<span class="clinic-queue-doctor-specialty">').text(name));
			});

			const remaining = data.specialties.length - MAX_VISIBLE;
			if (remaining > 0) {
				$badges.append(
					jQuery('<span class="clinic-queue-doctor-specialty clinic-queue-doctor-specialty--more">').text(`+${remaining}`)
				);
			}

			$row.append($badges);
		}

		$wrap.append($row);

		return $wrap;
	}

	/**
	 * Render the selected doctor in the Select2 trigger (compact – name only)
	 * @private
	 * @param {Object} item - Select2 data item
	 * @returns {jQuery} Rendered element
	 */
	_renderDoctorSelection(item) {
		if (!item.id) {
			return jQuery('<span>').text(item.text || '');
		}
		const data = this._doctorDataMap && this._doctorDataMap[item.id];
		return jQuery('<span>').text(data ? data.name : (item.text || ''));
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
							// A programmatic value change does NOT emit Select2's
							// "select2:select" event, which the clinic-change handler
							// relies on to load doctors/treatments. Emit it manually so
							// the single auto-selected clinic triggers the same flow as a
							// real user selection.
							$clinicSelect.trigger({
								type: 'select2:select',
								params: { data: { id: String(singleClinicId) } }
							});
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

		const doctorField = this.root.querySelector(this.config.selectors.doctorField);

		try {
			const $doctorSelect = this.getJQuerySelect(this.elements.doctorSelect);
			
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
			
			// Add disabled + loading classes for styling
			if (doctorField) {
				doctorField.classList.add('field-disabled');
				doctorField.classList.add('is-loading');
			}

			// Load doctors and booked-doctor IDs in parallel to avoid extra latency
		const [doctors, bookedDoctorIds] = await Promise.all([
			this.dataManager.loadDoctors(clinicId),
			this.dataManager.loadBookedDoctorIds(clinicId)
		]);

			// Remove loading indicator before populating
			if (doctorField) {
				doctorField.classList.remove('is-loading');
			}
			
			// Populate doctors
			this.populateDoctorSelect(doctors, this.elements.doctorSelect, this.dataManager, bookedDoctorIds);
		} catch (error) {
			this.logError('Error loading doctors', error);

			// Remove loading indicator on error
			if (doctorField) {
				doctorField.classList.remove('is-loading');
			}

			// Show the error message and reset the doctor field to the pre-clinic
			// state so the user can retry by selecting a clinic again.
			this.uiManager.showError(this.config.messages.doctors.error);
			this.resetDoctorSelectNoClinic();
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

			if (container) {
				// מיד מסתירים את כל שורות הימים עד שייטענו מהשרת – מונע הבזק של כל הימים
				container.querySelectorAll('.day-row').forEach((row) => {
					row.classList.add('day-row--clinix-hidden');
				});
			}

			if (window.ScheduleFormUtils && typeof window.ScheduleFormUtils.showFormLoader === 'function') {
				window.ScheduleFormUtils.showFormLoader(this.root, 'טוען ימים ושעות...');
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

				const hoursList = (hoursData && hoursData.result && Array.isArray(hoursData.result)) ? hoursData.result : [];
				const reasonsList = (reasonsData && reasonsData.result && Array.isArray(reasonsData.result)) ? reasonsData.result : [];

				/** מפה מ־API (מספר/קיצור/שם מלא) למפתחות שלנו: sunday..saturday */
				const apiDayToKey = (val) => {
					const v = (val === undefined || val === null) ? '' : String(val).toLowerCase().trim();
					if (v === '' || v === '7') return null;
					const byNum = { '0': 'sunday', '1': 'monday', '2': 'tuesday', '3': 'wednesday', '4': 'thursday', '5': 'friday', '6': 'saturday' };
					if (byNum[v] !== undefined) return byNum[v];
					const byShort = { sun: 'sunday', mon: 'monday', tue: 'tuesday', wed: 'wednesday', thu: 'thursday', fri: 'friday', sat: 'saturday' };
					const short = v.substring(0, 3);
					if (byShort[short]) return byShort[short];
					const full = [ 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ];
					if (full.indexOf(v) !== -1) return v;
					return null;
				};

				const daysMap = {};
				hoursList.forEach((item) => {
					const raw = item || {};
					const weekDayRaw = raw.weekDay ?? raw.WeekDay ?? raw.week_day ?? raw.dayOfWeek ?? raw.day ?? raw.Day ?? raw.dayName ?? '';
					const dayKey = apiDayToKey(weekDayRaw);
					const from = (raw.fromUTC || raw.FromUTC || raw.from || '').toString().substring(0, 5);
					const to = (raw.toUTC || raw.ToUTC || raw.to || '').toString().substring(0, 5);
					if (!dayKey) return;
					if (!daysMap[dayKey]) daysMap[dayKey] = [];
					daysMap[dayKey].push({ start_time: from || '08:00', end_time: to || '18:00' });
				});

				const hasWorkDays = Object.keys(daysMap).length > 0;
				const scheduleUI = this.core.uiManager.scheduleSettingsUI;
				const dayRows = container ? container.querySelectorAll('.day-row') : [];

				if (scheduleUI && typeof scheduleUI.setNoDaysMessageVisible === 'function') {
					scheduleUI.setNoDaysMessageVisible(!hasWorkDays);
				} else {
					const noWorkDaysMsg = scheduleStep ? scheduleStep.querySelector('.schedule-form-no-work-days-message') : null;
					if (!hasWorkDays && noWorkDaysMsg) {
						noWorkDaysMsg.style.display = '';
					} else if (hasWorkDays && noWorkDaysMsg) {
						noWorkDaysMsg.style.display = 'none';
					}
				}

				if (!hasWorkDays) {
					dayRows.forEach((row) => row.classList.add('day-row--clinix-hidden'));
				} else {
					dayRows.forEach((row) => row.classList.add('day-row--clinix-hidden'));
				}

				const treatmentsMap = reasonsList.map((item, idx) => {
					const raw = item || {};
					const name = (raw.name || raw.Name || '').toString() || 'טיפול';
					const drWebID = (raw.drWebID || raw.drWebId || raw.id || raw.ID || String(idx)).toString();
					const duration = parseInt(raw.duration || raw.Duration || 0, 10) || 30;
					const cost = parseInt(raw.price || raw.Price || raw.cost || raw.Cost || 0, 10) || 0;
					return { name, drWebID, duration, cost };
				});

				let terms = [];
				try {
					terms = await this.dataManager.loadTreatmentTypes();
				} catch (e) {
					// continue without portal terms
				}
				if (hasWorkDays) {
					this.applyClinixReadOnlyState(daysMap, treatmentsMap);
				}
				this.populatePortalTreatments(terms);
				if (saveBtn) {
					saveBtn.textContent = 'יצירת יומן';
				}

				// הסתרת הלואדר רק אחרי שימים ושעות העבודה הופיעו ב-DOM – אחרי frame אחד כדי שהדפדפן יציג
				const finishClinixScheduleLoad = () => {
					if (window.ScheduleFormUtils && typeof window.ScheduleFormUtils.hideFormLoader === 'function') {
						window.ScheduleFormUtils.hideFormLoader(this.root);
					}
					if (typeof this.uiManager.resetScheduleSettingsScroll === 'function') {
						this.uiManager.resetScheduleSettingsScroll();
					}
				};
				if (typeof requestAnimationFrame !== 'undefined') {
					requestAnimationFrame(() => {
						requestAnimationFrame(finishClinixScheduleLoad);
					});
				} else {
					finishClinixScheduleLoad();
				}
			} catch (error) {
				this.logError('Error loading Clinix schedule data', error);
				if (window.ScheduleFormUtils && typeof window.ScheduleFormUtils.hideFormLoader === 'function') {
					window.ScheduleFormUtils.hideFormLoader(this.root);
				}
				this.uiManager.showError('שגיאה בטעינת ימים וטיפולים מהמערכת.');
			}
		}

		/**
		 * ממלא select יחיד של טיפול Clinix מאותה רשימת reasons.
		 *
		 * @param {HTMLSelectElement} select - .clinix-treatment-select
		 * @param {Array} reasons - [ { name, drWebID, duration, cost } ]
		 * @param {Object} [options]
		 * @param {string|number} [options.selectedId='']
		 * @param {boolean} [options.includePlaceholder=true]
		 */
		fillClinixTreatmentSelect(select, reasons, options = {}) {
			if (!select) {
				return;
			}

			const selectedId = options.selectedId ? String(options.selectedId) : '';
			const includePlaceholder = options.includePlaceholder !== false;
			const list = Array.isArray(reasons) ? reasons : [];

			select.innerHTML = '';

			if (includePlaceholder) {
				const placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent = list.length ? 'בחר טיפול Clinix' : 'לא נמצאו טיפולים';
				select.appendChild(placeholder);
			}

			list.forEach((r) => {
				const opt = document.createElement('option');
				const reasonId = String(r.drWebID);
				opt.value = reasonId;
				opt.textContent = r.name || reasonId;
				if (selectedId && reasonId === selectedId) {
					opt.selected = true;
				}
				select.appendChild(opt);
			});

			select.disabled = list.length === 0;

			if (selectedId) {
				select.value = selectedId;
			} else if (!includePlaceholder && list.length > 0) {
				select.value = String(list[0].drWebID);
			} else {
				select.value = '';
			}
		}

		/**
		 * @returns {Array}
		 */
		getClinixTreatmentReasons() {
			if (Array.isArray(this.root._clinixTreatmentReasons)) {
				return this.root._clinixTreatmentReasons;
			}
			if (Array.isArray(this.root.clinicReasons)) {
				return this.root.clinicReasons;
			}
			return [];
		}

		/**
		 * Updates cost and duration inputs in a treatment row from Clinix selection (drWebID).
		 * Used when user changes the clinix-treatment-select; keeps cost/duration in sync.
		 *
		 * @param {Element} row - .treatment-row element
		 * @param {string} drWebID - Selected Clinix treatment drWebID
		 * @returns {boolean} true if updated
		 */
		updateRowCostDurationFromClinix(row, drWebID) {
			const reasons = this.getClinixTreatmentReasons();
			if (!row || !reasons.length || drWebID == null || drWebID === '') return false;
			const reason = reasons.find((r) => String(r.drWebID) === String(drWebID));
			if (!reason) return false;
			const costInput = row.querySelector('.treatment-cost-input');
			const durationInput = row.querySelector('.treatment-duration-input');
			if (costInput) costInput.value = reason.cost;
			if (durationInput) durationInput.value = reason.duration;
			return true;
		}

		/**
		 * Apply Clinix data to schedule-settings form.
		 * Shows only days that exist in daysMap; days/hours read-only. Populates clinix-treatment-select (name + drWebID); on change fills cost/duration.
		 *
		 * @param {Object} daysMap - { dayKey: [ { start_time, end_time } ] }
		 * @param {Array} treatmentsMap - [ { name, drWebID, duration, cost } ]
		 */
		applyClinixReadOnlyState(daysMap, treatmentsMap) {
			const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
			const container = this.root.querySelector('.days-schedule-container');

			// מסתירים את כל שורות הימים באמצעות class (עם !important ב-CSS) – רק ימים שב-daysMap יוצגו
			if (container) {
				container.querySelectorAll('.day-row').forEach((row) => row.classList.add('day-row--clinix-hidden'));
			}

			dayKeys.forEach((dayKey) => {
				const checkbox = this.root.querySelector(`.day-checkbox input[data-day="${dayKey}"]`);
				const dayRow = this.root.querySelector(`.day-row[data-day-row="${dayKey}"]`);
				const dayTimeRange = dayRow ? dayRow.querySelector('.day-time-range') : null;
				const timeRangesList = dayRow ? dayRow.querySelector('.time-ranges-list[data-day="' + dayKey + '"]') : null;

				const ranges = daysMap[dayKey];
				if (ranges && ranges.length > 0 && checkbox && dayRow && dayTimeRange && timeRangesList) {
					dayRow.classList.remove('day-row--clinix-hidden');
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
				}
			});

			this.root.querySelectorAll('.add-time-split-btn').forEach((btn) => { btn.style.display = 'none'; });
			this.root.querySelectorAll('.remove-time-split-btn').forEach((btn) => { btn.style.display = 'none'; });

			const repeater = this.root.querySelector('.treatments-repeater');
			if (!repeater || !treatmentsMap.length) return;

			this.root._clinixTreatmentReasons = treatmentsMap;
			this.root.clinicReasons = treatmentsMap;

			const defaultRow = repeater.querySelector('.treatment-row-default');
			if (!defaultRow) return;

			const clinixSelect = defaultRow.querySelector('.clinix-treatment-select');
			if (clinixSelect) {
				const firstReasonId = treatmentsMap.length > 0 ? treatmentsMap[0].drWebID : '';
				this.fillClinixTreatmentSelect(clinixSelect, treatmentsMap, {
					selectedId: firstReasonId,
					includePlaceholder: false,
				});
				if (treatmentsMap.length > 0) {
					const costInput = defaultRow.querySelector('.treatment-cost-input');
					const durationInput = defaultRow.querySelector('.treatment-duration-input');
					if (costInput) costInput.value = treatmentsMap[0].cost;
					if (durationInput) durationInput.value = treatmentsMap[0].duration;
				}
			}

			const syncCostDurationOnClinixChange = (selectEl) => {
				if (!selectEl || !selectEl.matches('.clinix-treatment-select')) return;
				const row = selectEl.closest('.treatment-row');
				if (!row) return;
				const drWebID = selectEl.value;
				if (this.updateRowCostDurationFromClinix(row, drWebID) && typeof this.uiManager.validateTreatmentsComplete === 'function') {
					this.uiManager.validateTreatmentsComplete();
				}
			};

			repeater.addEventListener('change', (e) => {
				if (!e.target.matches('.clinix-treatment-select')) return;
				syncCostDurationOnClinixChange(e.target);
			});

			if (typeof jQuery !== 'undefined') {
				jQuery(repeater).on('select2:select', '.clinix-treatment-select', function() {
					syncCostDurationOnClinixChange(this);
				});
			}

			if (typeof this.uiManager.validateTreatmentsComplete === 'function') {
				this.uiManager.validateTreatmentsComplete();
			}
		}

	/**
	 * מקבץ terms לפי התמחות, ממויינים לפי שם התמחות.
	 * טיפולים ללא התמחות מקובצים בסוף תחת קבוצה נפרדת.
	 *
	 * @private
	 * @param {Array} terms - [ { id, name, slug, specialty: { id, name } | null } ]
	 * @returns {Array<{ specialtyId: number|null, specialtyName: string, treatments: Array }>}
	 */
	_groupTermsBySpecialty(terms) {
		const groupsMap = new Map();

		terms.forEach((t) => {
			const key          = t.specialty ? t.specialty.id : null;
			const groupLabel   = t.specialty ? t.specialty.name : 'כללי';

			if (!groupsMap.has(key)) {
				groupsMap.set(key, { specialtyId: key, specialtyName: groupLabel, treatments: [] });
			}
			groupsMap.get(key).treatments.push(t);
		});

		// מיין קבוצות לפי שם התמחות; קבוצת "אחר" תמיד בסוף
		return Array.from(groupsMap.values()).sort((a, b) => {
			if (a.specialtyId === null) return 1;
			if (b.specialtyId === null) return -1;
			return a.specialtyName.localeCompare(b.specialtyName, 'he');
		});
	}

	/**
	 * ממלא select יחיד של סוג טיפול (פורטל) עם אפשרויות מקובצות לפי התמחות.
	 *
	 * @param {HTMLSelectElement} select - .portal-treatment-select
	 * @param {Array} terms - [ { id, name, slug, specialty: { id, name } | null } ]
	 * @param {string|number} [selectedId=''] - ערך נבחר (אופציונלי)
	 */
	fillPortalTreatmentSelect(select, terms, selectedId = '') {
		if (!select) {
			return;
		}

		const optionLabel = terms && terms.length ? 'בחר סוג טיפול' : 'לא נמצאו סוגי טיפולים';
		const groups = (terms && terms.length) ? this._groupTermsBySpecialty(terms) : [];
		const selectedValue = selectedId ? String(selectedId) : '';

		select.innerHTML = '<option value="">' + optionLabel + '</option>';

		if (groups.length) {
			groups.forEach((group) => {
				const optgroup = document.createElement('optgroup');
				optgroup.label = 'תחום: ' + group.specialtyName;
				if (group.specialtyId !== null) {
					optgroup.dataset.specialtyId = group.specialtyId;
				}

				group.treatments.forEach((t) => {
					const opt = document.createElement('option');
					const termId = String(t.id);
					opt.value = termId;
					opt.textContent = (t.name || t.slug || termId) + (t.specialty && t.specialty.name ? ' (' + t.specialty.name + ')' : '');
					if (t.specialty) {
						opt.dataset.specialtyId = t.specialty.id;
						opt.dataset.specialtyName = t.specialty.name;
					}
					if (selectedValue && termId === selectedValue) {
						opt.selected = true;
					}
					optgroup.appendChild(opt);
				});

				select.appendChild(optgroup);
			});
			select.disabled = false;
		}

		select.value = selectedValue;
	}

	/**
	 * Populate portal-treatment-select in all treatment rows with taxonomy terms.
	 * מקבץ options תחת <optgroup> לפי תחום התמחות, ממויין לפי שם ההתמחות.
	 *
	 * @param {Array} terms - [ { id, name, slug, specialty: { id, name } | null } ]
	 */
	populatePortalTreatments(terms) {
		const list = Array.isArray(terms) ? terms : [];
		this.root._portalTreatmentTerms = list;

		if (this.core.uiManager.scheduleSettingsUI) {
			this.core.uiManager.scheduleSettingsUI.setPortalTerms(list);
		}

		const repeater = this.root.querySelector('.treatments-repeater');
		if (!repeater) return;

		const selects = repeater.querySelectorAll('.portal-treatment-select');

		selects.forEach((select) => {
			let currentValue = '';
			if (typeof jQuery !== 'undefined') {
				currentValue = jQuery(select).val() || '';
			} else {
				currentValue = select.value || '';
			}
			this.fillPortalTreatmentSelect(select, this.root._portalTreatmentTerms, currentValue);
		});
		if (typeof this.uiManager.reinitializeSelect2 === 'function') {
			this.uiManager.reinitializeSelect2(repeater);
		}
		// Re-run validation after portal treatments are populated so the button state reflects reality.
		// Use setTimeout to ensure Select2 finishes reinitializing before checking values.
		if (typeof this.uiManager.validateTreatmentsComplete === 'function') {
			setTimeout(() => this.uiManager.validateTreatmentsComplete(), 0);
		}
	}

		/**
		 * Set visibility of clinix-only vs google-only fields by flow.
		 * Clinix: cost/duration מוגבלים (disabled, ממולאים מנתוני קליניקס).
		 * Google: cost/duration ניתנים להזנה ידנית; מנקים אותם רק במעבר מקליניקס לגוגל (לא בכל כניסה חוזרת לשלב ההגדרות).
		 * @param {string} actionType - 'clinix' or 'google'
		 */
		applyFlowVisibility(actionType) {
			const repeater = this.root.querySelector('.treatments-repeater');
			if (!repeater) {
				return;
			}

			const wasClinix = repeater.classList.contains('is-clinix-flow');

			if (this.core.uiManager.scheduleSettingsUI) {
				this.core.uiManager.scheduleSettingsUI.setScheduleType(actionType);
				this.core.uiManager.scheduleSettingsUI.applyScheduleTypeRules(actionType);
			}

			if (actionType === 'google' && wasClinix) {
				repeater.querySelectorAll('.treatment-cost-input, .treatment-duration-input').forEach((input) => {
					input.value = '';
				});
			}

			if (typeof this.uiManager.validateTreatmentsComplete === 'function') {
				this.uiManager.validateTreatmentsComplete();
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
