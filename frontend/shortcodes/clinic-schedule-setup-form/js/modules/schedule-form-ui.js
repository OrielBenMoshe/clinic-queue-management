/**
 * Schedule Form UI Module
 * Handles UI interactions and repeaters
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Schedule Form UI Manager
	 */
	class ScheduleFormUIManager {
		constructor(rootElement, config) {
			this.root = rootElement;
			this.config = config || {};
			this.fieldManager = null;

			this._alertModalOnPrimary = null;
			this._bindAlertModalEvents();
		}

		/**
		 * Cache alert modal elements and wire open/close interactions.
		 */
		_bindAlertModalEvents() {
			this.alertModal = {
				overlay: this.root.querySelector('#schedule-form-alert-modal'),
				title: this.root.querySelector('#schedule-form-alert-modal-title'),
				body: this.root.querySelector('#schedule-form-alert-modal-body'),
				primary: this.root.querySelector('#schedule-form-alert-modal-primary'),
				secondary: this.root.querySelector('#schedule-form-alert-modal-secondary'),
			};

			if (!this.alertModal.overlay) {
				return;
			}

			this.alertModal.overlay.addEventListener('click', (event) => {
				if (event.target === this.alertModal.overlay) {
					this.closeAlertModal();
				}
			});

			if (this.alertModal.primary) {
				this.alertModal.primary.addEventListener('click', () => {
					const callback = this._alertModalOnPrimary;
					this.closeAlertModal();
					if (typeof callback === 'function') {
						callback();
					}
				});
			}

			if (this.alertModal.secondary) {
				this.alertModal.secondary.addEventListener('click', () => {
					this.closeAlertModal();
				});
			}

			this._alertModalEscapeHandler = (event) => {
				if (event.key !== 'Escape' || !this.alertModal.overlay || this.alertModal.overlay.hasAttribute('hidden')) {
					return;
				}
				this.closeAlertModal();
			};
			document.addEventListener('keydown', this._alertModalEscapeHandler);
		}

		/**
		 * Open a styled alert modal (replaces window.alert).
		 *
		 * @param {Object} options Modal content and actions
		 * @param {string} [options.title]
		 * @param {string} [options.body]
		 * @param {string} [options.primaryLabel]
		 * @param {Function|null} [options.onPrimary]
		 * @param {string} [options.secondaryLabel]
		 */
		showAlertModal(options = {}) {
			const title = options.title || 'שגיאה';
			const body = options.body || '';
			const primaryLabel = options.primaryLabel || 'הבנתי';
			const secondaryLabel = options.secondaryLabel || '';

			if (!this.alertModal || !this.alertModal.overlay) {
				window.alert(body ? (title + '\n\n' + body) : title);
				return;
			}

			if (this.alertModal.title) {
				this.alertModal.title.textContent = title;
			}
			if (this.alertModal.body) {
				this.alertModal.body.textContent = body;
			}
			if (this.alertModal.primary) {
				this.alertModal.primary.textContent = primaryLabel;
			}

			this._alertModalOnPrimary = typeof options.onPrimary === 'function' ? options.onPrimary : null;

			if (this.alertModal.secondary) {
				if (secondaryLabel) {
					this.alertModal.secondary.textContent = secondaryLabel;
					this.alertModal.secondary.removeAttribute('hidden');
				} else {
					this.alertModal.secondary.setAttribute('hidden', '');
				}
			}

			this.alertModal.overlay.removeAttribute('hidden');
			if (this.alertModal.primary) {
				this.alertModal.primary.focus();
			}
		}

		/**
		 * Close the alert modal.
		 */
		closeAlertModal() {
			if (!this.alertModal || !this.alertModal.overlay) {
				return;
			}
			this.alertModal.overlay.setAttribute('hidden', '');
			this._alertModalOnPrimary = null;
		}

		/**
		 * Initialize shared schedule-settings UI module (days + treatments).
		 *
		 * @param {Object|null} fieldManager ScheduleFormFieldManager instance
		 */
		initScheduleSettingsUI(fieldManager) {
			if (!window.ClinicQueueScheduleSettingsUI) {
				return;
			}

			if (this.scheduleSettingsUI) {
				return;
			}

			const self = this;
			this.scheduleSettingsUI = new window.ClinicQueueScheduleSettingsUI(this.root, {
				context: 'wizard',
				maxSplitsPerDay: 2,
				normalizeTimeRanges: true,
				noDaysMessageSelector: '.schedule-form-no-work-days-message',
				trashIcon: (window.scheduleFormData && window.scheduleFormData.trashIcon) || '',
				getDropdownParent: () => {
					const scroll = self.root.querySelector('.schedule-settings-scroll-content');
					return scroll || self.root;
				},
				onAddTreatmentRow: (row) => {
					const repeater = self.root.querySelector('.treatments-repeater');
					const populated = self._populateNewTreatmentRowSelects(row);
					if (populated.portalSelect) {
						self._initializeTreatmentSelectField(populated.portalSelect, true);
					}
					if (self.isClinixTreatmentsFlow(repeater) && populated.clinixSelect) {
						self._initializeTreatmentSelectField(populated.clinixSelect, true);
					}
				},
			});

			if (fieldManager) {
				this.fieldManager = fieldManager;
			}

			this.scheduleSettingsUI.bindEvents();
		}

		/**
		 * @param {HTMLElement|null} select
		 * @returns {jQuery|null}
		 */
		_jQuerySelect(select) {
			return (typeof jQuery !== 'undefined' && select) ? jQuery(select) : null;
		}

		/**
		 * @param {jQuery|null} $select
		 * @param {boolean} disabled
		 */
		_syncSelect2Disabled($select, disabled) {
			if ($select && $select.hasClass('select2-hidden-accessible')) {
				$select.prop('disabled', disabled).trigger('change.select2');
			}
		}

		/**
		 * @param {HTMLElement|null} fieldWrapper
		 * @param {boolean} disabled
		 */
		_setFieldDisabledClass(fieldWrapper, disabled) {
			if (!fieldWrapper) {
				return;
			}
			fieldWrapper.classList.toggle('field-disabled', disabled);
		}

		/**
		 * Setup action card selection (Step 1)
		 */
		setupActionCards(onSelectCallback) {
		const cards = this.root.querySelectorAll('.action-card');
		const button = this.root.querySelector('.continue-btn');

		const setActive = (value) => {
			cards.forEach((card) => {
				const input = card.querySelector('input[type="radio"]');
				const isActive = input && input.value === value; 
				card.classList.toggle('is-active', isActive);
				if (isActive && input) input.checked = true;
			});
			if (button) {
				button.disabled = !value;
				button.classList.toggle('is-disabled', !value);
			}
		};

		cards.forEach((card) => {
			card.addEventListener('click', () => {
				const value = card.dataset.value || '';
				setActive(value);
				this.root.dispatchEvent(new CustomEvent('jet-multi-step:select', { 
					detail: { value }, 
					bubbles: true 
				}));
			});
		});

		if (button) {
			button.addEventListener('click', () => {
				const selected = this.root.querySelector('input[name="jet_action_choice"]:checked');
				const value = selected ? selected.value : '';
				if (value && onSelectCallback) {
					onSelectCallback(value);
				}
			});
		}
		}

		/**
		 * Sync Google step (Step 2) - enable/disable fields
		 */
		setupGoogleStepSync(googleNextBtn, clinicSelect, doctorSelect, manualScheduleName) {
			const syncGoogleStep = () => {
				const $clinicSelect = this._jQuerySelect(clinicSelect);
				const hasClinic = $clinicSelect
					? ($clinicSelect.val() && String($clinicSelect.val()).trim() !== '')
					: !!(clinicSelect && clinicSelect.value && String(clinicSelect.value).trim() !== '');
				const $doctorSelect = this._jQuerySelect(doctorSelect);
				const hasDoctor = $doctorSelect
					? ($doctorSelect.val() && $doctorSelect.val() !== '')
					: !!(doctorSelect && doctorSelect.value);
				const hasManual = manualScheduleName && manualScheduleName.value.trim().length > 0;

				if (doctorSelect) {
					const noDoctorsLoaded = doctorSelect.options.length <= 1;
					const shouldDisableDoctor = hasManual || !hasClinic || noDoctorsLoaded;
					doctorSelect.disabled = shouldDisableDoctor;
					this._syncSelect2Disabled($doctorSelect, shouldDisableDoctor);
					this._setFieldDisabledClass(
						this.root.querySelector('.doctor-select-field'),
						shouldDisableDoctor
					);
				}

				if (clinicSelect) {
					clinicSelect.disabled = false;
					this._syncSelect2Disabled($clinicSelect, false);
					this._setFieldDisabledClass(this.root.querySelector('.clinic-select-field'), false);
				}

				if (manualScheduleName) {
					manualScheduleName.disabled = hasDoctor;
					this._setFieldDisabledClass(
						manualScheduleName.closest('.jet-form-builder__row'),
						hasDoctor
					);
				}

				if (googleNextBtn) {
					googleNextBtn.disabled = !(hasClinic && (hasDoctor || hasManual));
				}
			};

			if (clinicSelect) {
				clinicSelect.addEventListener('change', syncGoogleStep);
				const $clinicSelect = this._jQuerySelect(clinicSelect);
				if ($clinicSelect) {
					$clinicSelect.on('select2:select select2:clear', syncGoogleStep);
				}
			}

			if (doctorSelect) {
				doctorSelect.addEventListener('change', syncGoogleStep);
				const $doctorSelect = this._jQuerySelect(doctorSelect);
				if ($doctorSelect) {
					$doctorSelect.on('select2:select select2:clear', syncGoogleStep);
				}
			}

			if (manualScheduleName) {
				['input', 'change'].forEach((evt) => manualScheduleName.addEventListener(evt, syncGoogleStep));
			}

			return syncGoogleStep;
		}

	/**
	 * Validate that all treatment rows have required fields filled. Enable/disable save button.
	 * @returns {boolean} true if all valid
	 */
	validateTreatmentsComplete() {
		return this.scheduleSettingsUI
			? this.scheduleSettingsUI.validateTreatmentsComplete()
			: false;
	}

	/**
	 * @param {HTMLElement} newRow
	 * @returns {{ clinixSelect: HTMLSelectElement|null, portalSelect: HTMLSelectElement|null }}
	 */
	_populateNewTreatmentRowSelects(newRow) {
		const clinixSelect = newRow.querySelector('.clinix-treatment-select');
		const portalSelect = newRow.querySelector('.portal-treatment-select');
		const fieldManager = this.fieldManager;

		if (clinixSelect) {
			const clinixReasons = fieldManager && typeof fieldManager.getClinixTreatmentReasons === 'function'
				? fieldManager.getClinixTreatmentReasons()
				: (this.root._clinixTreatmentReasons || this.root.clinicReasons || []);

			if (fieldManager && typeof fieldManager.fillClinixTreatmentSelect === 'function' && clinixReasons.length) {
				fieldManager.fillClinixTreatmentSelect(clinixSelect, clinixReasons, {
					selectedId: '',
					includePlaceholder: true,
				});
			} else {
				this._copyClinixOptionsFromDefaultRow(clinixSelect);
			}
		}

		if (portalSelect) {
			const portalTerms = this.root._portalTreatmentTerms || [];
			if (fieldManager && typeof fieldManager.fillPortalTreatmentSelect === 'function') {
				fieldManager.fillPortalTreatmentSelect(portalSelect, portalTerms, '');
			} else {
				this._copyPortalOptionsFromDefaultRow(portalSelect);
			}
		}

		return { clinixSelect, portalSelect };
	}

		/**
		 * האם הטופס במצב זרימת קליניקס (לפי class על ה-repeater או על שורש הטופס).
		 *
		 * @param {HTMLElement|null} repeater
		 * @returns {boolean}
		 */
		isClinixTreatmentsFlow(repeater) {
			return !!(
				(repeater && repeater.classList.contains('is-clinix-flow'))
				|| this.root.classList.contains('action-type-clinix')
			);
		}

		/**
		 * @param {HTMLSelectElement} targetSelect
		 * @returns {boolean}
		 */
		_copyClinixOptionsFromDefaultRow(targetSelect) {
			const defaultClinix = this.root.querySelector('.treatment-row-default .clinix-treatment-select');
			if (!defaultClinix || !targetSelect || defaultClinix.options.length === 0) {
				if (targetSelect) {
					targetSelect.innerHTML = '<option value="">בחר טיפול Clinix</option>';
				}
				return false;
			}

			targetSelect.innerHTML = '';
			const placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = 'בחר טיפול Clinix';
			targetSelect.appendChild(placeholder);

			Array.from(defaultClinix.options).forEach((opt) => {
				if (!opt.value) {
					return;
				}
				const clone = document.createElement('option');
				clone.value = opt.value;
				clone.textContent = opt.textContent;
				targetSelect.appendChild(clone);
			});

			targetSelect.value = '';
			targetSelect.disabled = false;
			return true;
		}

		/**
		 * @param {HTMLSelectElement} targetSelect
		 * @returns {boolean}
		 */
		_copyPortalOptionsFromDefaultRow(targetSelect) {
			const defaultPortal = this.root.querySelector('.treatment-row-default .portal-treatment-select');
			if (!defaultPortal || !targetSelect || defaultPortal.options.length === 0) {
				if (targetSelect) {
					targetSelect.innerHTML = '<option value="">בחר סוג טיפול</option>';
				}
				return false;
			}

			targetSelect.innerHTML = defaultPortal.innerHTML;
			targetSelect.value = '';
			Array.from(targetSelect.options).forEach((opt) => {
				opt.selected = false;
			});
			const placeholder = targetSelect.querySelector('option[value=""]');
			if (placeholder) {
				placeholder.selected = true;
			}
			targetSelect.disabled = false;
			return true;
		}

		/**
		 * @param {HTMLSelectElement} select
		 * @param {boolean} [resetValue=false]
		 */
		_initializeTreatmentSelectField(select, resetValue = false) {
			this.initializeSelectField(select);
			const $select = this._jQuerySelect(select);
			if (resetValue && $select) {
				$select.val('').trigger('change');
			}
		}

		/**
		 * Initialize Select2 for one select field (destroy + bind).
		 *
		 * @param {HTMLSelectElement} element
		 */
		initializeSelectField(element) {
			const $select = this._jQuerySelect(element);
			if (!$select || typeof jQuery.fn.select2 === 'undefined') {
				return;
			}
			if ($select.hasClass('doctor-select')) {
				return;
			}

			if ($select.hasClass('select2-hidden-accessible')) {
				$select.select2('destroy');
			}

			$select.select2(this.buildSelect2Options($select));

			if (window.ClinicQueueSelect2) {
				window.ClinicQueueSelect2.setupInlineSearch($select, this.getSelect2DropdownParent(element));
			}
		}

		/**
		 * Show loading state on button
		 */
		setButtonLoading(button, isLoading, loadingText = 'שומר...', originalText = '') {
			if (!button) return;

			if (isLoading) {
				button.disabled = true;
				button.dataset.originalText = button.textContent;
				button.textContent = loadingText;
			} else {
				button.disabled = false;
				button.textContent = button.dataset.originalText || originalText;
			}
		}

		/**
		 * Show a simple error message in the alert modal.
		 *
		 * @param {string} message Plain-text message
		 */
		showError(message) {
			const text = String(message || 'שגיאה לא ידועה')
				.replace(/<br\s*\/?>/gi, '\n')
				.replace(/<[^>]+>/g, '')
				.trim();

			this.showAlertModal({
				title: 'שגיאה',
				body: text,
				primaryLabel: 'הבנתי',
			});
		}

		/**
		 * Scroll container for schedule-settings step (days + treatments).
		 *
		 * @returns {HTMLElement|null}
		 */
		getScheduleSettingsScrollContainer() {
			return this.root.querySelector('.schedule-settings-scroll-content');
		}

		/**
		 * Reset schedule-settings UI (days, time splits, treatments) to defaults.
		 */
		resetScheduleSettings() {
			if (!this.scheduleSettingsUI) {
				return;
			}

			this.scheduleSettingsUI.resetDays();
			this.scheduleSettingsUI.resetTreatments();
			this.scheduleSettingsUI.setScheduleType('google');
			this.scheduleSettingsUI.applyScheduleTypeRules('google');
			this.scheduleSettingsUI.initScheduleSelectFields(this.root);
			if (typeof this.scheduleSettingsUI.refreshDayTimeConstraints === 'function') {
				this.scheduleSettingsUI.refreshDayTimeConstraints();
			}
			this.resetScheduleSettingsScroll();
			this.validateTreatmentsComplete();
		}

		/**
		 * Reset scroll position to top when entering schedule-settings.
		 * Uses double rAF so layout (flex + Select2) is settled before applying.
		 *
		 * @param {HTMLElement|null} scrollEl Optional scroll container
		 */
		resetScheduleSettingsScroll(scrollEl) {
			const container = scrollEl || this.getScheduleSettingsScrollContainer();
			if (!container) {
				return;
			}

			const applyScrollTop = () => {
				container.scrollTop = 0;
			};

			applyScrollTop();
			if (typeof requestAnimationFrame === 'function') {
				requestAnimationFrame(() => {
					requestAnimationFrame(applyScrollTop);
				});
			}
		}

		/**
		 * Resolve Select2 dropdownParent for a field.
		 * Fields inside the scrollable schedule-settings area use that container
		 * so dropdown positioning does not hijack scroll on the whole form.
		 *
		 * @param {HTMLElement} element Native select element
		 * @returns {jQuery}
		 */
		getSelect2DropdownParent(element) {
			const $root = jQuery(this.root);
			const scrollContent = element && element.closest('.schedule-settings-scroll-content');
			if (scrollContent) {
				return jQuery(scrollContent);
			}
			return $root;
		}

		/**
		 * Build shared Select2 options for a select field.
		 *
		 * @param {jQuery} $select jQuery-wrapped select
		 * @param {Object} extraOptions Additional Select2 options
		 * @returns {Object}
		 */
	buildSelect2Options($select, extraOptions = {}) {
		const element = $select[0];
		const isTimeSelect = $select.hasClass('time-select');
		const isDoctorSelect = $select.hasClass('doctor-select');
		const isPortalTreatmentSelect = $select.hasClass('portal-treatment-select');

		return {
			theme: 'clinic-queue',
			dir: 'rtl',
			language: 'he',
			width: '100%',
			placeholder: $select.find('option:first').text() || '',
			allowClear: false,
			dropdownParent: this.getSelect2DropdownParent(element),
			escapeMarkup: (markup) => markup,
			minimumResultsForSearch: Infinity,
			...(isDoctorSelect
				? { minimumResultsForSearch: 0, dropdownCssClass: 'clinic-queue-doctor-dropdown' }
				: window.ClinicQueueSelect2 ? window.ClinicQueueSelect2.getInlineSearchOptions($select) : {}),
			...(isTimeSelect && { dropdownCssClass: 'time-select-dropdown' }),
			...(isPortalTreatmentSelect && { dropdownCssClass: 'portal-treatment-dropdown' }),
			...extraOptions
		};
	}

		/**
		 * Bind Select2 to select fields within a scope.
		 *
		 * @param {HTMLElement|jQuery|null} scope Container to search within (defaults to form root)
		 * @param {boolean} forceReinit Destroy existing instances before re-binding
		 */
		bindSelect2Fields(scope, forceReinit) {
			if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
				if (!forceReinit && window.ScheduleFormUtils) {
					window.ScheduleFormUtils.warn('Select2 is not loaded, skipping initialization');
				}
				return;
			}

			const $scope = scope ? jQuery(scope) : jQuery(this.root);

			$scope.find('.select-field').each((index, element) => {
				const $select = jQuery(element);
				if ($select.hasClass('doctor-select')) {
					return;
				}
				if (
					this.scheduleSettingsUI
					&& $select.closest('.treatments-repeater, .days-schedule-container').length
				) {
					return;
				}
				if ($select.hasClass('select2-hidden-accessible') && !forceReinit) {
					return;
				}
				this.initializeSelectField(element);
			});
		}

		/**
		 * Initialize Select2 for all select fields
		 */
		initializeSelect2() {
			this.bindSelect2Fields(null, false);
		}

		/**
		 * Reinitialize Select2 after dynamic content changes.
		 * Pass an optional scope element to avoid reinitializing hidden-step fields
		 * (which can force the schedule-settings scroller to the bottom).
		 *
		 * @param {HTMLElement|null} scope Optional container to limit reinit scope
		 */
		reinitializeSelect2(scope) {
			this.bindSelect2Fields(scope, true);
		}

		/**
		 * Initialize floating labels for text fields (MUI style)
		 * Label moves from center (default) to top (focused/has value)
		 */
		initializeFloatingLabels() {
			if (window.ClinicQueueFloatingLabels && typeof window.ClinicQueueFloatingLabels.init === 'function') {
				window.ClinicQueueFloatingLabels.init(this.root);
				this.setupFloatingLabel = window.ClinicQueueFloatingLabels.setupOne;
				return;
			}
		}

}

	// Export to global scope
	window.ScheduleFormUIManager = ScheduleFormUIManager;

})(window);

