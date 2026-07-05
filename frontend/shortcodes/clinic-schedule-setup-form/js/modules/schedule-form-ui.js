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
		 * @param {HTMLElement} container
		 * @param {string} itemSelector
		 * @param {string} btnSelector
		 */
		_hideRemoveButtonWhenSingle(container, itemSelector, btnSelector) {
			const remainingItems = container.querySelectorAll(itemSelector);
			if (remainingItems.length === 1) {
				const lastRemoveBtn = remainingItems[0].querySelector(btnSelector);
				if (lastRemoveBtn) {
					lastRemoveBtn.style.display = 'none';
				}
			}
		}

		/**
		 * @param {HTMLSelectElement} select
		 */
		_triggerSelect2Change(select) {
			const $select = this._jQuerySelect(select);
			if ($select && $select.hasClass('select2-hidden-accessible')) {
				$select.trigger('change.select2');
			}
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
		 * Setup day checkboxes (Step 3)
		 */
		setupDayCheckboxes() {
			const dayCheckboxes = this.root.querySelectorAll('.day-checkbox input[type="checkbox"]');

			dayCheckboxes.forEach(checkbox => {
				checkbox.addEventListener('change', (e) => {
					const day = e.target.dataset.day;
					const dayTimeRange = this.root.querySelector(`.day-time-range[data-day="${day}"]`);

					if (dayTimeRange) {
						dayTimeRange.style.display = e.target.checked ? 'flex' : 'none';
					}
				});
			});
		}

		/**
		 * @param {string} timeStr
		 * @returns {number}
		 */
		_timeToMinutes(timeStr) {
			const [h, m] = (timeStr || '0:0').split(':').map(Number);
			return (h * 60) + (m || 0);
		}

		/**
		 * Normalize time-range rows for a day: enforce ordering and valid from/to pairs.
		 *
		 * @param {string} day
		 */
		_normalizeDayTimeRanges(day) {
			const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			if (!list) {
				return;
			}

			let prevEndMinutes = null;
			const rows = Array.from(list.querySelectorAll('.time-range-row'));

			rows.forEach((row) => {
				const fromSelect = row.querySelector('.from-time');
				const toSelect = row.querySelector('.to-time');
				if (!fromSelect || !toSelect) {
					return;
				}

				fromSelect.querySelectorAll('option').forEach((opt) => {
					opt.disabled = false;
					opt.style.display = '';
				});
				toSelect.querySelectorAll('option').forEach((opt) => {
					opt.disabled = false;
					opt.style.display = '';
				});

				if (prevEndMinutes !== null) {
					fromSelect.querySelectorAll('option').forEach((opt) => {
						if (this._timeToMinutes(opt.value) < prevEndMinutes) {
							opt.disabled = true;
							opt.style.display = 'none';
						}
					});
				}

				let fromVal = fromSelect.value;
				if (fromVal && prevEndMinutes !== null && this._timeToMinutes(fromVal) < prevEndMinutes) {
					const allowed = Array.from(fromSelect.options).find((opt) => !opt.disabled);
					if (allowed) {
						fromSelect.value = allowed.value;
					}
					fromVal = fromSelect.value;
				}

				const startMinutes = this._timeToMinutes(fromSelect.value);
				toSelect.querySelectorAll('option').forEach((opt) => {
					if (this._timeToMinutes(opt.value) <= startMinutes) {
						opt.disabled = true;
						opt.style.display = 'none';
					}
				});

				let toVal = toSelect.value;
				if (!toVal || this._timeToMinutes(toVal) <= startMinutes || toSelect.selectedOptions[0]?.disabled) {
					const allowedTo = Array.from(toSelect.options).find(
						(opt) => !opt.disabled && this._timeToMinutes(opt.value) > startMinutes
					);
					if (allowedTo) {
						toSelect.value = allowedTo.value;
					} else {
						const later = Array.from(toSelect.options).find(
							(opt) => this._timeToMinutes(opt.value) > startMinutes
						);
						if (later) {
							toSelect.value = later.value;
						}
					}
					toVal = toSelect.value;
				}

				this._triggerSelect2Change(fromSelect);
				this._triggerSelect2Change(toSelect);

				prevEndMinutes = this._timeToMinutes(toSelect.value);
			});
		}

		/**
		 * @param {HTMLElement} row
		 * @param {string} day
		 */
		_attachTimeSelectListeners(row, day) {
			const handler = () => this._normalizeDayTimeRanges(day);

			row.querySelectorAll('.from-time, .to-time').forEach((select) => {
				select.addEventListener('change', handler);
				const $select = this._jQuerySelect(select);
				if ($select) {
					$select.on('select2:select', handler);
				}
			});
		}

		/**
		 * Setup time splits (add/remove time ranges)
		 */
		setupTimeSplits() {
			this.root.querySelectorAll('.add-time-split-btn').forEach((btn) => {
				btn.addEventListener('click', (e) => {
					const day = e.target.closest('.add-time-split-btn').dataset.day;
					const timeRangesList = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);

					if (!timeRangesList) {
						return;
					}

					const currentCount = timeRangesList.querySelectorAll('.time-range-row').length;
					if (currentCount >= 2) {
						return;
					}

					const firstRow = timeRangesList.querySelector('.time-range-row');
					this.addTimeRange(timeRangesList, firstRow, day);
					this._normalizeDayTimeRanges(day);
				});
			});

			this.root.querySelectorAll('.time-ranges-list').forEach((list) => {
				this.setupRemoveButtons(list, '.time-range-row', '.remove-time-split-btn');

				const day = list.dataset.day;
				this.updateAddButtonVisibility(day);
				this._normalizeDayTimeRanges(day);
				list.querySelectorAll('.time-range-row').forEach((row) => {
					this._attachTimeSelectListeners(row, day);
				});
			});
		}

		/**
		 * Update add button visibility based on number of splits
		 */
		updateAddButtonVisibility(day) {
			const timeRangesList = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			const addButton = this.root.querySelector(`.add-time-split-btn[data-day="${day}"]`);
			
			if (!timeRangesList || !addButton) return;
			
			const currentCount = timeRangesList.querySelectorAll('.time-range-row').length;
			
			// Hide button if we have 2 splits, show if less than 2
			if (currentCount >= 2) {
				addButton.style.display = 'none';
			} else {
				addButton.style.display = 'inline-flex';
			}
		}

	/**
	 * Add a time range row
	 */
	addTimeRange(container, templateRow, day) {
		const currentCount = container.querySelectorAll('.time-range-row').length;
		if (currentCount >= 2) {
			return;
		}

		const newRow = templateRow.cloneNode(false);
		newRow.innerHTML = templateRow.innerHTML;
		newRow.querySelectorAll('.select2-container').forEach((el) => el.remove());
		this._resetClonedSelects(newRow);

		const removeBtn = newRow.querySelector('.remove-time-split-btn');
		if (removeBtn) {
			removeBtn.style.display = 'inline-flex';
		}
		container.querySelectorAll('.remove-time-split-btn').forEach((btn) => {
			btn.style.display = 'inline-flex';
		});

		container.appendChild(newRow);
		if (day) {
			this.updateAddButtonVisibility(day);
		}

		this.reinitializeSelect2(newRow);
		this._attachTimeSelectListeners(newRow, day);

		if (removeBtn) {
			removeBtn.addEventListener('click', () => {
				this.removeTimeRange(newRow, day);
			});
		}
	}

		/**
		 * Remove a time range row
		 */
		removeTimeRange(row, day) {
			row.remove();

			const container = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			if (container) {
				this._hideRemoveButtonWhenSingle(container, '.time-range-row', '.remove-time-split-btn');
			}

			if (day) {
				this.updateAddButtonVisibility(day);
				this._normalizeDayTimeRanges(day);
			}
		}

		/**
		 * Setup remove buttons for repeater items
		 */
		setupRemoveButtons(container, itemSelector, btnSelector) {
			const removeButtons = container.querySelectorAll(btnSelector);
			const day = container.dataset.day;

			removeButtons.forEach((btn) => {
				btn.addEventListener('click', () => {
					const row = btn.closest(itemSelector);
					if (!row) {
						return;
					}

					if (itemSelector === '.time-range-row' && day) {
						this.removeTimeRange(row, day);
						return;
					}

					row.remove();
					this._hideRemoveButtonWhenSingle(container, itemSelector, btnSelector);
				});
			});
		}

		/**
		 * Setup treatments repeater: add button clones default row; change listeners trigger validation.
		 */
		setupTreatmentsRepeater() {
			const addTreatmentBtn = this.root.querySelector('.add-treatment-btn');
			const treatmentsRepeater = this.root.querySelector('.treatments-repeater');
			if (!treatmentsRepeater) return;

			if (addTreatmentBtn) {
				addTreatmentBtn.addEventListener('click', () => {
					const defaultRow = treatmentsRepeater.querySelector('.treatment-row-default');
					if (defaultRow) {
						this.addTreatmentRow(treatmentsRepeater, defaultRow);
					}
				});
			}

		// Validate on any change in treatment fields
		const runValidation = () => this.validateTreatmentsComplete();
		treatmentsRepeater.addEventListener('change', (e) => {
			if (e.target.matches('.portal-treatment-select, .treatment-cost-input, .treatment-duration-input, .clinix-treatment-select')) {
				runValidation();
			}
		});
		treatmentsRepeater.addEventListener('input', (e) => {
			if (e.target.matches('.treatment-cost-input, .treatment-duration-input')) {
				runValidation();
			}
		});
		// jQuery's .trigger('change') (used by Select2) does not always fire native addEventListener handlers.
		// Listen directly to Select2 events to ensure validation runs after portal/clinix treatment selection.
		if (typeof jQuery !== 'undefined') {
			jQuery(treatmentsRepeater).on('select2:select select2:clear', function(e) {
				if (jQuery(e.target).is('.portal-treatment-select, .clinix-treatment-select')) {
					runValidation();
				}
			});
		}
		}

	/**
	 * Validate that all treatment rows have required fields filled. Enable/disable save button.
	 * Google: portal + cost + duration. Clinix: clinix + portal + cost + duration.
	 * @returns {boolean} true if all valid
	 */
	validateTreatmentsComplete() {
		const saveBtn = this.root.querySelector('.save-schedule-btn');
		const repeater = this.root.querySelector('.treatments-repeater');
		if (!repeater || !saveBtn) {
			return false;
		}

		const isClinix = this.isClinixTreatmentsFlow(repeater);
		const rows = repeater.querySelectorAll('.treatment-row');
		let allValid = true;

		rows.forEach((row) => {
			const portalVal = this._getSelectValue(row.querySelector('.portal-treatment-select'));
			const clinixVal = this._getSelectValue(row.querySelector('.clinix-treatment-select'));
			const costInput = row.querySelector('.treatment-cost-input');
			const durationInput = row.querySelector('.treatment-duration-input');
			const costOk = costInput && String(costInput.value).trim() !== '';
			const durationOk = durationInput && String(durationInput.value).trim() !== '';
			const clinixOk = !isClinix || !!clinixVal;

			if (!portalVal || !costOk || !durationOk || !clinixOk) {
				allValid = false;
			}
		});

		saveBtn.disabled = !allValid;
		return allValid;
	}

	/**
	 * @param {HTMLSelectElement|null} select
	 * @returns {string}
	 */
	_getSelectValue(select) {
		if (!select) {
			return '';
		}
		const $select = this._jQuerySelect(select);
		return $select ? ($select.val() || '') : (select.value || '');
	}

	/**
	 * Strip Select2 artifacts from cloned row selects before re-binding.
	 *
	 * @param {HTMLElement} row
	 */
	_resetClonedSelects(row) {
		row.querySelectorAll('select').forEach((select) => {
			select.removeAttribute('data-select2-id');
			select.classList.remove('select2-hidden-accessible');
			select.removeAttribute('aria-hidden');
			select.removeAttribute('tabindex');
			select.selectedIndex = 0;
		});
	}

	/**
	 * Add a new treatment row by cloning the default row; add remove button and wire validation.
	 * @param {HTMLElement} container - .treatments-repeater
	 * @param {HTMLElement} defaultRow - .treatment-row-default
	 */
	addTreatmentRow(container, defaultRow) {
		const newRow = defaultRow.cloneNode(false);
		newRow.innerHTML = defaultRow.innerHTML;
		newRow.classList.remove('treatment-row-default');
		newRow.removeAttribute('data-is-default');

		const legend = newRow.querySelector('.treatment-row-legend');
		if (legend) {
			legend.remove();
		}

		const editableCount = container.querySelectorAll('.treatment-row:not(.treatment-row-default)').length;
		newRow.setAttribute('data-row-index', String(editableCount + 1));

		newRow.querySelectorAll('.select2-container').forEach((el) => el.remove());
		this._resetClonedSelects(newRow);

		const costInput = newRow.querySelector('.treatment-cost-input');
		const durationInput = newRow.querySelector('.treatment-duration-input');
		if (costInput) {
			costInput.value = '';
		}
		if (durationInput) {
			durationInput.value = '';
		}

		const removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'remove-treatment-btn';
		removeBtn.setAttribute('aria-label', 'הסר טיפול');
		removeBtn.innerHTML = (window.scheduleFormData && window.scheduleFormData.trashIcon)
			? window.scheduleFormData.trashIcon
			: '×';
		removeBtn.addEventListener('click', () => {
			newRow.remove();
			this.validateTreatmentsComplete();
		});
		newRow.appendChild(removeBtn);
		container.appendChild(newRow);

		const { clinixSelect, portalSelect } = this._populateNewTreatmentRowSelects(newRow);
		const isClinixFlow = this.isClinixTreatmentsFlow(container);

		if (portalSelect) {
			this._initializeTreatmentSelectField(portalSelect, true);
		}
		if (isClinixFlow && clinixSelect) {
			this._initializeTreatmentSelectField(clinixSelect, true);
		}

		this.validateTreatmentsComplete();
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

