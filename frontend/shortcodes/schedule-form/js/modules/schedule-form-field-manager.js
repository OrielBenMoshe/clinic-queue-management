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
			doctorField: '.doctor-search-field',
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
				default: 'חיפוש רופא לפי שם או מספר רישוי',
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
		 * Create doctor Select2 templates (templateResult and templateSelection)
		 * @returns {Object} Object with templateResult and templateSelection functions
		 */
		createDoctorTemplates() {
			return {
				templateResult: (data) => {
					if (!data || !data.element) {
						return data.text;
					}
					
					const $element = jQuery(data.element);
					const doctorName = $element.attr('data-doctor-name') || data.text;
					const licenseNumber = $element.attr('data-license-number') || '';
					const thumbnail = $element.attr('data-thumbnail') || '';
					
					// Create doctor card HTML
					const $result = jQuery('<div class="clinic-queue-doctor-result"></div>');
					
					// Thumbnail (First)
					if (thumbnail) {
						const $thumb = jQuery('<div class="clinic-queue-doctor-thumbnail"></div>');
						const $img = jQuery('<img>').attr('src', thumbnail).attr('alt', doctorName);
						$thumb.append($img);
						$result.append($thumb);
					}
					
					// Doctor info section
					const $info = jQuery('<div class="clinic-queue-doctor-info"></div>');
					
					// Doctor name
					const $name = jQuery('<b class="clinic-queue-doctor-name"></b>').text(doctorName);
					$info.append($name);
					
					// License number
					if (licenseNumber) {
						const $license = jQuery('<p class="clinic-queue-doctor-license"></p>').text(licenseNumber);
						$info.append($license);
					}
					
					$result.append($info);
					
					return $result;
				},
				templateSelection: (data) => {
					if (!data || !data.element) {
						return data.text;
					}
					
					const $element = jQuery(data.element);
					const doctorName = $element.attr('data-doctor-name') || data.text;
					const thumbnail = $element.attr('data-thumbnail') || '';
					
					// For selected item, show name + thumbnail (compact)
					const thumbHtml = thumbnail 
						? '<span class="clinic-queue-doctor-selection__thumb"><img src="' + thumbnail + '" alt="' + doctorName + '" /></span>'
						: '';
					const nameHtml = '<span class="clinic-queue-doctor-selection__name">' + doctorName + '</span>';
					
					return '<span class="clinic-queue-doctor-selection">' + thumbHtml + nameHtml + '</span>';
				}
			};
		}

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
				getAttributes: (doctor) => {
					// Check if methods exist (for backward compatibility)
					const licenseNumber = (typeof dataManager.getDoctorLicenseNumber === 'function') 
						? dataManager.getDoctorLicenseNumber(doctor) 
						: (doctor.license_number || (doctor.meta && doctor.meta.license_number) || '');
					const thumbnail = (typeof dataManager.getDoctorThumbnail === 'function')
						? dataManager.getDoctorThumbnail(doctor)
						: (doctor.thumbnail || (doctor._embedded && doctor._embedded['wp:featuredmedia'] && doctor._embedded['wp:featuredmedia'][0] && doctor._embedded['wp:featuredmedia'][0].source_url) || '');
					
					return {
						'data-license-number': licenseNumber,
						'data-thumbnail': thumbnail,
						'data-doctor-name': dataManager.getDoctorName(doctor)
					};
				},
				select2Config: {
					minimumResultsForSearch: 0, // Enable search for doctor field
					placeholder: this.doctorPlaceholders.default,
					allowClear: true, // Allow clear for doctor field
					dropdownParent: $doctorSelect ? $doctorSelect.closest('.jet-form-builder__field-wrap') : jQuery(this.root),
					escapeMarkup: (markup) => markup,
					...this.createDoctorTemplates()
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
	}

	// Export to global scope
	window.ScheduleFormFieldManager = ScheduleFormFieldManager;

})(window);
