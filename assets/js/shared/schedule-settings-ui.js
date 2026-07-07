/**
 * Shared schedule-settings UI (days, time ranges, treatments).
 *
 * @package Clinic_Queue_Management
 */

(function (window) {
	'use strict';

	const DAYS_ORDER = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

	/**
	 * @param {HTMLElement} root
	 * @param {Object} [options]
	 */
	class ScheduleSettingsUI {
		constructor(root, options = {}) {
			const existing = root && root._clinicQueueScheduleSettingsUI;
			if (existing instanceof ScheduleSettingsUI) {
				return existing;
			}

			this.root = root;
			this.options = Object.assign(
				{
					context: 'wizard',
					maxSplitsPerDay: 2,
					normalizeTimeRanges: true,
					scheduleType: 'google',
					portalTerms: [],
					trashIcon: '',
					saveButtonSelector: '.save-schedule-btn',
					readonlyBadgeSelector: '#edit-modal-clinix-badge',
					addTreatmentButtonSelector: '.add-treatment-btn',
					noDaysMessageSelector: '',
					dayRowSelector: '.day-row',
					select2MinimumResultsForSearch: null,
					onValidationChange: null,
					onAddTreatmentRow: null,
					buildPortalOptionsHtml: null,
					getDropdownParent: null,
				},
				options
			);
			this._treatmentRowIndex = 0;
			this._portalTerms = Array.isArray(this.options.portalTerms) ? this.options.portalTerms : [];
			this._bound = false;
			this._addTreatmentBtn = null;
			this._onAddTreatmentClick = () => this.addTreatmentRow();

			if (root) {
				root._clinicQueueScheduleSettingsUI = this;
			}
		}

		getTreatmentsRepeater() {
			return this.root.querySelector('.treatments-repeater');
		}

		getDayRow(day) {
			return this.root.querySelector(`${this.options.dayRowSelector}[data-day-row="${day}"]`);
		}

		setPortalTerms(terms) {
			this._portalTerms = Array.isArray(terms) ? terms : [];
		}

		setScheduleType(type) {
			this.options.scheduleType = type || 'google';
		}

		/* ── Select2 helpers ── */

		hasSelect2() {
			return typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 === 'function';
		}

		_jQuery(el) {
			return (typeof window.jQuery !== 'undefined' && el) ? window.jQuery(el) : null;
		}

		getSelectValue(select) {
			if (!select) {
				return '';
			}
			const $select = this._jQuery(select);
			return $select ? ($select.val() || '') : (select.value || '');
		}

		/**
		 * Sync native select value to Select2 display after programmatic updates.
		 *
		 * @param {HTMLSelectElement|null} select
		 */
		_triggerSelect2Change(select) {
			const $select = this._jQuery(select);
			if ($select && $select.length && $select.hasClass('select2-hidden-accessible')) {
				$select.trigger('change.select2');
			}
		}

		destroySelect2($el) {
			if (!this.hasSelect2() || !$el || !$el.length) {
				return;
			}
			if ($el.hasClass('select2-hidden-accessible')) {
				$el.select2('destroy');
			}
		}

		/**
		 * Destroy Select2 on all schedule-settings selects within a scope.
		 *
		 * @param {HTMLElement|null} scope Optional container; defaults to form root
		 */
		destroyAllSelect2(scope) {
			const container = scope || this.root;
			if (!container) {
				return;
			}

			container.querySelectorAll('.select-field').forEach((select) => {
				if (select.classList.contains('doctor-select') || select.classList.contains('clinix-treatment-select')) {
					return;
				}
				this.destroySelect2(this._jQuery(select));
			});
		}

		/**
		 * Reset days, treatments, flow rules, and tear down Select2 widgets.
		 *
		 * @param {string} [scheduleType] Schedule type to apply after reset (default: google)
		 */
		resetFormState(scheduleType) {
			const type = scheduleType || 'google';
			this.setScheduleType(type);
			this.resetDays();
			this.resetTreatments();
			this.applyScheduleTypeRules(type);
			this.destroyAllSelect2();
			this.root.classList.remove('action-type-clinix');
			this._initializeDayTimeRanges();
		}

		getSelect2DropdownParent(element) {
			if (typeof this.options.getDropdownParent === 'function') {
				return this.options.getDropdownParent();
			}
			const scroll = element && element.closest('.schedule-settings-scroll-content');
			return scroll || this.root;
		}

		buildSelect2Options($select, extraOptions = {}) {
			const element = $select[0];
			const isTimeSelect = $select.hasClass('time-select')
				|| $select.hasClass('from-time')
				|| $select.hasClass('to-time');
			const isPortalTreatmentSelect = $select.hasClass('portal-treatment-select');

			return {
				theme: 'clinic-queue',
				dir: 'rtl',
				language: 'he',
				width: '100%',
				placeholder: $select.find('option:first').text() || '',
				allowClear: false,
				dropdownParent: window.jQuery(this.getSelect2DropdownParent(element)),
				escapeMarkup: (markup) => markup,
				minimumResultsForSearch: Infinity,
				...(isTimeSelect && { dropdownCssClass: 'time-select-dropdown' }),
				...(isPortalTreatmentSelect && { dropdownCssClass: 'portal-treatment-dropdown' }),
				...(window.ClinicQueueSelect2 && !isTimeSelect && !isPortalTreatmentSelect
					? window.ClinicQueueSelect2.getInlineSearchOptions($select)
					: {}),
				...extraOptions,
			};
		}

		initTimeSelect2(select) {
			if (!this.hasSelect2() || !select) {
				return;
			}
			const $el = this._jQuery(select);
			if (!$el || !$el.length || $el.hasClass('doctor-select')) {
				return;
			}

			this.destroySelect2($el);
			$el.select2(this.buildSelect2Options($el));

			if (window.ClinicQueueSelect2) {
				window.ClinicQueueSelect2.setupInlineSearch(
					$el,
					window.jQuery(this.getSelect2DropdownParent(select))
				);
			}
		}

		initPortalSelect2(select) {
			if (!this.hasSelect2() || !select) {
				return;
			}
			const $el = this._jQuery(select);
			if (!$el || !$el.length) {
				return;
			}

			this.destroySelect2($el);

			const config = this.buildSelect2Options($el, {
				placeholder: $el.find('option:first').text() || 'בחר סוג טיפול',
				dropdownCssClass: 'portal-treatment-dropdown',
			});

			if (this.options.select2MinimumResultsForSearch !== null) {
				config.minimumResultsForSearch = this.options.select2MinimumResultsForSearch;
			}

			$el.select2(config);
		}

		/**
		 * Initialize Select2 on schedule-settings fields (time + portal), same as wizard step 3.
		 *
		 * @param {HTMLElement|null} scope Optional container; defaults to form root
		 */
		initScheduleSelectFields(scope) {
			const container = scope || this.root;
			if (!container) {
				return;
			}

			container.querySelectorAll('.select-field').forEach((select) => {
				if (select.classList.contains('doctor-select') || select.classList.contains('clinix-treatment-select')) {
					return;
				}
				if (select.classList.contains('portal-treatment-select')) {
					this.initPortalSelect2(select);
					return;
				}
				this.initTimeSelect2(select);
			});
		}

		/* ── Flow rules ── */

		isClinixFlow(repeater) {
			const rep = repeater || this.getTreatmentsRepeater();
			return !!(
				(rep && rep.classList.contains('is-clinix-flow'))
				|| this.options.scheduleType === 'clinix'
				|| this.root.classList.contains('action-type-clinix')
			);
		}

		applyScheduleTypeRules(scheduleType) {
			const type = scheduleType || this.options.scheduleType || 'google';
			this.options.scheduleType = type;
			const isClinix = type === 'clinix';
			const repeater = this.getTreatmentsRepeater();

			const badge = this.options.readonlyBadgeSelector
				? this.root.querySelector(this.options.readonlyBadgeSelector)
				: null;
			if (badge) {
				if (isClinix) {
					badge.removeAttribute('hidden');
				} else {
					badge.setAttribute('hidden', '');
				}
			}

			this.root.querySelectorAll(`${this.options.dayRowSelector} .day-checkbox input[type="checkbox"]`)
				.forEach((cb) => {
					cb.disabled = isClinix;
				});
			this.root.querySelectorAll('.day-time-range select, .day-time-range button')
				.forEach((el) => {
					el.disabled = isClinix;
				});

			if (repeater) {
				repeater.classList.toggle('is-clinix-flow', isClinix);
				repeater.classList.toggle('is-google-flow', !isClinix);
				repeater.querySelectorAll('.treatment-cost-input, .treatment-duration-input')
					.forEach((input) => {
						input.disabled = isClinix;
					});
			}

			const addBtn = this.options.addTreatmentButtonSelector
				? this.root.querySelector(this.options.addTreatmentButtonSelector)
				: this.root.querySelector('.add-treatment-btn');
			if (addBtn) {
				if (isClinix) {
					addBtn.setAttribute('hidden', '');
				} else {
					addBtn.removeAttribute('hidden');
				}
			}
		}

		/* ── Days ── */

		resetDays() {
			DAYS_ORDER.forEach((day) => {
				const row = this.getDayRow(day);
				if (!row) {
					return;
				}

				const cb = row.querySelector(`input[type="checkbox"][data-day="${day}"]`);
				if (cb) {
					cb.checked = false;
					cb.disabled = false;
				}

				const timeRange = row.querySelector(`.day-time-range[data-day="${day}"]`);
				if (timeRange) {
					timeRange.style.display = 'none';
				}

				const list = row.querySelector('.time-ranges-list');
				if (!list) {
					return;
				}

				list.querySelectorAll('.time-range-row').forEach((tr, idx) => {
					if (idx > 0) {
						tr.querySelectorAll('.from-time, .to-time').forEach((select) => {
							this.destroySelect2(this._jQuery(select));
						});
						tr.remove();
					}
				});

				const firstRow = list.querySelector('.time-range-row');
				if (firstRow) {
					const from = firstRow.querySelector('.from-time');
					const to = firstRow.querySelector('.to-time');
					if (from) {
						this.destroySelect2(this._jQuery(from));
						from.value = '08:00';
						from.disabled = false;
					}
					if (to) {
						this.destroySelect2(this._jQuery(to));
						to.value = day === 'friday' ? '16:00' : '18:00';
						to.disabled = false;
					}
					const removeBtn = firstRow.querySelector('.remove-time-split-btn');
					if (removeBtn) {
						removeBtn.style.display = 'none';
					}
				}

				row.classList.remove('day-row--clinix-hidden');
			});

			this._toggleNoDaysMessage(false);
		}

		fillDays(daysData) {
			this.resetDays();
			const data = daysData && typeof daysData === 'object' ? daysData : {};
			const hasDays = Object.keys(data).length > 0;
			this._toggleNoDaysMessage(!hasDays);

			Object.keys(data).forEach((day) => {
				const ranges = data[day];
				if (!Array.isArray(ranges) || ranges.length === 0) {
					return;
				}

				const row = this.getDayRow(day);
				if (!row) {
					return;
				}

				const cb = row.querySelector(`input[type="checkbox"][data-day="${day}"]`);
				if (cb) {
					cb.checked = true;
				}

				const timeRange = row.querySelector(`.day-time-range[data-day="${day}"]`);
				if (timeRange) {
					timeRange.style.display = 'flex';
				}

				const list = row.querySelector('.time-ranges-list');
				if (!list) {
					return;
				}

				const firstRow = list.querySelector('.time-range-row');
				if (firstRow && ranges[0]) {
					const from = firstRow.querySelector('.from-time');
					const to = firstRow.querySelector('.to-time');
					if (from) {
						from.value = ranges[0].start_time || '08:00';
					}
					if (to) {
						to.value = ranges[0].end_time || '18:00';
					}
				}

				for (let i = 1; i < ranges.length; i++) {
					this._addTimeSplitRow(list, ranges[i].start_time, ranges[i].end_time, day);
				}

				if (ranges.length > 1) {
					list.querySelectorAll('.remove-time-split-btn').forEach((btn) => {
						btn.style.display = 'inline-flex';
					});
				}

				if (this.options.normalizeTimeRanges) {
					this._normalizeDayTimeRanges(day);
				}
			});

			this.initScheduleSelectFields(this.root);
			this.validateTreatmentsComplete();
		}

		/**
		 * @returns {boolean} true when at least one day checkbox is checked
		 */
		_hasAtLeastOneDayChecked() {
			return DAYS_ORDER.some((day) => {
				const row = this.getDayRow(day);
				if (!row) {
					return false;
				}
				const cb = row.querySelector(`input[type="checkbox"][data-day="${day}"]`);
				return !!(cb && cb.checked);
			});
		}

		collectDays() {
			const days = {};
			DAYS_ORDER.forEach((day) => {
				const row = this.getDayRow(day);
				if (!row) {
					return;
				}
				const cb = row.querySelector(`input[type="checkbox"][data-day="${day}"]`);
				if (!cb || !cb.checked) {
					return;
				}

				const ranges = [];
				row.querySelectorAll('.time-range-row').forEach((tr) => {
					const fromEl = tr.querySelector('.from-time');
					const toEl = tr.querySelector('.to-time');
					const from = fromEl ? fromEl.value : '';
					const to = toEl ? toEl.value : '';
					if (from && to) {
						ranges.push({ start_time: from, end_time: to });
					}
				});

				if (ranges.length > 0) {
					days[day] = ranges;
				}
			});
			return days;
		}

		_toggleNoDaysMessage(show) {
			let el = null;
			if (this.options.noDaysMessageSelector) {
				el = this.root.querySelector(this.options.noDaysMessageSelector);
			}
			if (!el) {
				el = this.root.querySelector('.schedule-form-no-work-days-message')
					|| this.root.querySelector('#edit-modal-no-days-msg');
			}
			if (!el) {
				return;
			}
			if (show) {
				el.removeAttribute('hidden');
				if (el.style) {
					el.style.display = '';
				}
			} else {
				el.setAttribute('hidden', '');
				if (el.style && el.classList.contains('schedule-form-no-work-days-message')) {
					el.style.display = 'none';
				}
			}
		}

		/**
		 * Show or hide the no-work-days message (Clinix / edit modal).
		 *
		 * @param {boolean} show
		 */
		setNoDaysMessageVisible(show) {
			this._toggleNoDaysMessage(show);
		}

		_timeToMinutes(timeStr) {
			const parts = (timeStr || '0:0').split(':').map(Number);
			return (parts[0] * 60) + (parts[1] || 0);
		}

		_normalizeDayTimeRanges(day) {
			if (!this.options.normalizeTimeRanges) {
				return;
			}

			const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			if (!list) {
				return;
			}

			let prevEndMinutes = null;
			list.querySelectorAll('.time-range-row').forEach((row) => {
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
				}

				this._triggerSelect2Change(fromSelect);
				this._triggerSelect2Change(toSelect);

				prevEndMinutes = this._timeToMinutes(toSelect.value);
			});
		}

		_addTimeSplitRow(list, fromTime, toTime, day) {
			const first = list.querySelector('.time-range-row');
			if (!first) {
				return;
			}

			const max = this.options.maxSplitsPerDay;
			if (max !== null && list.querySelectorAll('.time-range-row').length >= max) {
				return;
			}

			const clone = first.cloneNode(true);
			clone.querySelectorAll('.select2-container').forEach((el) => el.remove());
			clone.querySelectorAll('select').forEach((select) => {
				select.classList.remove('select2-hidden-accessible');
				select.removeAttribute('data-select2-id');
				select.removeAttribute('aria-hidden');
				select.removeAttribute('tabindex');
			});

			const from = clone.querySelector('.from-time');
			const to = clone.querySelector('.to-time');
			if (from) {
				from.value = fromTime || '08:00';
			}
			if (to) {
				to.value = toTime || (day === 'friday' ? '16:00' : '18:00');
			}

			const removeBtn = clone.querySelector('.remove-time-split-btn');
			if (removeBtn) {
				removeBtn.style.display = 'inline-flex';
			}

			list.appendChild(clone);
			this._updateAddButtonVisibility(day);
			clone.querySelectorAll('.from-time, .to-time').forEach((select) => {
				this.initTimeSelect2(select);
			});
		}

		_updateAddButtonVisibility(day) {
			const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			const addButton = this.root.querySelector(`.add-time-split-btn[data-day="${day}"]`);
			if (!list || !addButton) {
				return;
			}

			const max = this.options.maxSplitsPerDay;
			const count = list.querySelectorAll('.time-range-row').length;
			if (max !== null && count >= max) {
				addButton.style.display = 'none';
			} else {
				addButton.style.display = 'inline-flex';
			}
		}

		/**
		 * Run time-range normalization for a day when from/to values change.
		 *
		 * @param {string} day
		 */
		_handleTimeRangeChange(day) {
			if (!day || !this.options.normalizeTimeRanges) {
				return;
			}
			this._normalizeDayTimeRanges(day);
		}

		/**
		 * Initialize split-button visibility and time-range constraints for all days.
		 */
		_initializeDayTimeRanges() {
			DAYS_ORDER.forEach((day) => {
				this._updateAddButtonVisibility(day);
				if (this.options.normalizeTimeRanges) {
					this._normalizeDayTimeRanges(day);
				}
			});
		}

		/**
		 * Re-apply split limits and time-range ordering (public for wizard reset).
		 */
		refreshDayTimeConstraints() {
			this._initializeDayTimeRanges();
		}

		/* ── Treatments ── */

		_escapeHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		_groupBySpecialty(terms) {
			const map = new Map();
			terms.forEach((t) => {
				const key = t.specialty ? t.specialty.id : null;
				const label = t.specialty ? t.specialty.name : 'כללי';
				if (!map.has(key)) {
					map.set(key, { specialtyId: key, label, treatments: [] });
				}
				map.get(key).treatments.push(t);
			});
			return Array.from(map.values()).sort((a, b) => {
				if (a.specialtyId === null) {
					return 1;
				}
				if (b.specialtyId === null) {
					return -1;
				}
				return a.label.localeCompare(b.label, 'he');
			});
		}

		buildPortalOptionsHtml(selectedId) {
			if (typeof this.options.buildPortalOptionsHtml === 'function') {
				return this.options.buildPortalOptionsHtml(selectedId, this._portalTerms);
			}

			if (!this._portalTerms.length) {
				return '<option value="">אין טיפולים זמינים</option>';
			}

			let html = '<option value="">בחר סוג טיפול</option>';
			this._groupBySpecialty(this._portalTerms).forEach((group) => {
				html += `<optgroup label="תחום: ${this._escapeHtml(group.label)}">`;
				group.treatments.forEach((t) => {
					const id = t.id || t.term_id || '';
					const name = t.name || t.label || '';
					const suffix = t.specialty ? ` (${t.specialty.name})` : '';
					const selected = String(id) === String(selectedId) ? ' selected' : '';
					html += `<option value="${id}"${selected}>${this._escapeHtml(name + suffix)}</option>`;
				});
				html += '</optgroup>';
			});
			return html;
		}

		resetTreatments() {
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return;
			}

			repeater.querySelectorAll('.treatment-row:not(.treatment-row-default)').forEach((row) => {
				const portal = row.querySelector('.portal-treatment-select');
				if (portal) {
					this.destroySelect2(this._jQuery(portal));
				}
				row.remove();
			});

			const defaultRow = repeater.querySelector('.treatment-row-default');
			if (defaultRow) {
				this._resetTreatmentRow(defaultRow);
			}
			this._treatmentRowIndex = 0;
		}

		_resetTreatmentRow(row) {
			const portal = row.querySelector('.portal-treatment-select');
			if (portal) {
				this.destroySelect2(this._jQuery(portal));
			}
			const clinix = row.querySelector('.clinix-treatment-select');
			if (clinix) {
				clinix.innerHTML = '<option value=""></option>';
			}
			if (portal) {
				portal.innerHTML = this.buildPortalOptionsHtml(0);
			}
			const cost = row.querySelector('.treatment-cost-input');
			const duration = row.querySelector('.treatment-duration-input');
			if (cost) {
				cost.value = '';
			}
			if (duration) {
				duration.value = '';
			}
			const removeBtn = row.querySelector('.remove-treatment-btn');
			if (removeBtn) {
				removeBtn.setAttribute('hidden', '');
			}
		}

		fillTreatments(treatments) {
			this.resetTreatments();
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return;
			}

			const defaultRow = repeater.querySelector('.treatment-row-default');
			if (!defaultRow) {
				return;
			}

			const list = Array.isArray(treatments) ? treatments : [];
			if (!list.length) {
				const portal = defaultRow.querySelector('.portal-treatment-select');
				if (portal) {
					portal.innerHTML = this.buildPortalOptionsHtml(0);
					this.initPortalSelect2(portal);
				}
				return;
			}

			list.forEach((treatment, index) => {
				let row = defaultRow;
				if (index > 0) {
					row = this._cloneTreatmentRow();
					repeater.appendChild(row);
				}
				this._fillTreatmentRow(row, treatment, index);
			});

			this._refreshRemoveButtons();
		}

		_fillTreatmentRow(row, treatment, index) {
			row.setAttribute('data-row-index', String(index));

			const clinixSel = row.querySelector('.clinix-treatment-select');
			if (clinixSel) {
				clinixSel.innerHTML = '';
				if (treatment.clinix_treatment_id || treatment.clinix_treatment_name) {
					const opt = document.createElement('option');
					opt.value = treatment.clinix_treatment_id || '';
					opt.textContent = treatment.clinix_treatment_name || treatment.clinix_treatment_id || '';
					clinixSel.appendChild(opt);
				}
			}

			const portalSel = row.querySelector('.portal-treatment-select');
			if (portalSel) {
				this.destroySelect2(this._jQuery(portalSel));
				portalSel.innerHTML = this.buildPortalOptionsHtml(treatment.treatment_type || 0);
				this.initPortalSelect2(portalSel);
			}

			const cost = row.querySelector('.treatment-cost-input');
			const duration = row.querySelector('.treatment-duration-input');
			if (cost) {
				cost.value = treatment.cost || '';
			}
			if (duration) {
				duration.value = treatment.duration || '';
			}
		}

		_cloneTreatmentRow() {
			this._treatmentRowIndex += 1;
			const repeater = this.getTreatmentsRepeater();
			const first = repeater.querySelector('.treatment-row-default');
			const clone = first.cloneNode(true);
			clone.classList.remove('treatment-row-default');
			clone.removeAttribute('data-is-default');
			clone.setAttribute('data-row-index', String(this._treatmentRowIndex));

			clone.querySelectorAll('.select2-container').forEach((el) => el.remove());
			clone.querySelectorAll('select').forEach((select) => {
				select.classList.remove('select2-hidden-accessible');
				select.removeAttribute('data-select2-id');
				select.removeAttribute('aria-hidden');
				select.removeAttribute('tabindex');
			});

			const portal = clone.querySelector('.portal-treatment-select');
			if (portal) {
				portal.innerHTML = this.buildPortalOptionsHtml(0);
			}
			const clinix = clone.querySelector('.clinix-treatment-select');
			if (clinix) {
				clinix.innerHTML = '<option value=""></option>';
			}

			const cost = clone.querySelector('.treatment-cost-input');
			const duration = clone.querySelector('.treatment-duration-input');
			if (cost) {
				cost.value = '';
			}
			if (duration) {
				duration.value = '';
			}

			const removeBtn = clone.querySelector('.remove-treatment-btn');
			if (removeBtn) {
				removeBtn.removeAttribute('hidden');
			}

			return clone;
		}

		addTreatmentRow() {
			const repeater = this.getTreatmentsRepeater();
			const defaultRow = repeater ? repeater.querySelector('.treatment-row-default') : null;
			if (!repeater || !defaultRow) {
				return null;
			}

			const row = this._cloneTreatmentRow();
			repeater.appendChild(row);

			if (typeof this.options.onAddTreatmentRow === 'function') {
				this.options.onAddTreatmentRow(row, repeater);
			} else {
				const portal = row.querySelector('.portal-treatment-select');
				if (portal) {
					this.initPortalSelect2(portal);
				}
			}

			this._refreshRemoveButtons();
			this.validateTreatmentsComplete();
			return row;
		}

		removeTreatmentRow(row) {
			if (!row || row.classList.contains('treatment-row-default')) {
				return;
			}
			const portal = row.querySelector('.portal-treatment-select');
			if (portal) {
				this.destroySelect2(this._jQuery(portal));
			}
			row.remove();
			this._refreshRemoveButtons();
			this.validateTreatmentsComplete();
		}

		_refreshRemoveButtons() {
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return;
			}
			const rows = repeater.querySelectorAll('.treatment-row');
			const multiple = rows.length > 1;
			rows.forEach((row) => {
				const btn = row.querySelector('.remove-treatment-btn');
				if (!btn) {
					return;
				}
				const isDefault = row.classList.contains('treatment-row-default');
				if (isDefault || !multiple) {
					btn.setAttribute('hidden', '');
				} else {
					btn.removeAttribute('hidden');
				}
			});
		}

		collectTreatments() {
			const treatments = [];
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return treatments;
			}

			repeater.querySelectorAll('.treatment-row').forEach((row) => {
				const clinixSel = row.querySelector('.clinix-treatment-select');
				const portalSel = row.querySelector('.portal-treatment-select');
				const costInput = row.querySelector('.treatment-cost-input');
				const durationInput = row.querySelector('.treatment-duration-input');

				treatments.push({
					clinix_treatment_id: clinixSel ? (clinixSel.value || '') : '',
					clinix_treatment_name: clinixSel && clinixSel.selectedOptions && clinixSel.selectedOptions[0]
						? clinixSel.selectedOptions[0].text
						: '',
					treatment_type: portalSel ? parseInt(this.getSelectValue(portalSel), 10) || 0 : 0,
					cost: costInput ? parseInt(costInput.value, 10) || 0 : 0,
					duration: durationInput ? parseInt(durationInput.value, 10) || 0 : 0,
				});
			});

			return treatments;
		}

		validate(context, scheduleType) {
			const type = scheduleType || this.options.scheduleType || 'google';
			const days = this.collectDays();
			const treatments = this.collectTreatments();

			if (context !== 'edit_modal' && type === 'google' && Object.keys(days).length === 0) {
				return 'אנא בחר לפחות יום עבודה אחד.';
			}
			if (context === 'edit_modal' && type === 'google' && Object.keys(days).length === 0) {
				return 'אנא בחר לפחות יום עבודה אחד.';
			}
			if (treatments.length === 0) {
				return 'אנא הגדר לפחות טיפול אחד.';
			}
			return null;
		}

		validateTreatmentsComplete() {
			const saveBtn = this.root.querySelector(this.options.saveButtonSelector);
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return false;
			}

			const isClinix = this.isClinixFlow(repeater);
			let treatmentsValid = true;

			repeater.querySelectorAll('.treatment-row').forEach((row) => {
				const portalVal = this.getSelectValue(row.querySelector('.portal-treatment-select'));
				const clinixVal = this.getSelectValue(row.querySelector('.clinix-treatment-select'));
				const costInput = row.querySelector('.treatment-cost-input');
				const durationInput = row.querySelector('.treatment-duration-input');
				const costOk = costInput && String(costInput.value).trim() !== '';
				const durationOk = durationInput && String(durationInput.value).trim() !== '';
				const clinixOk = !isClinix || !!clinixVal;

				if (!portalVal || !costOk || !durationOk || !clinixOk) {
					treatmentsValid = false;
				}
			});

			const daysValid = this._hasAtLeastOneDayChecked();
			const allValid = treatmentsValid && daysValid;

			if (saveBtn) {
				saveBtn.disabled = !allValid;
			}

			if (typeof this.options.onValidationChange === 'function') {
				this.options.onValidationChange(allValid);
			}

			return allValid;
		}

		bindEvents() {
			if (this._bound) {
				return;
			}
			this._bound = true;

			this.root.querySelectorAll('.day-checkbox input[type="checkbox"]').forEach((checkbox) => {
				checkbox.addEventListener('change', (e) => {
					const day = e.target.dataset.day;
					const timeRange = this.root.querySelector(`.day-time-range[data-day="${day}"]`);
					if (timeRange) {
						timeRange.style.display = e.target.checked ? 'flex' : 'none';
					}
					this.validateTreatmentsComplete();
				});
			});

			this.root.addEventListener('change', (e) => {
				if (!e.target.matches('.from-time, .to-time')) {
					return;
				}
				const list = e.target.closest('.time-ranges-list');
				if (list && list.dataset.day) {
					this._handleTimeRangeChange(list.dataset.day);
				}
			});

			if (this.hasSelect2()) {
				window.jQuery(this.root).on(
					'select2:select.scheduleSettingsTime select2:clear.scheduleSettingsTime',
					'.from-time, .to-time',
					(e) => {
						const list = e.target.closest('.time-ranges-list');
						if (list && list.dataset.day) {
							this._handleTimeRangeChange(list.dataset.day);
						}
					}
				);
			}

			this.root.addEventListener('click', (e) => {
				const removeBtn = e.target.closest('.remove-time-split-btn');
				if (!removeBtn) {
					return;
				}
				const row = removeBtn.closest('.time-range-row');
				const list = removeBtn.closest('.time-ranges-list');
				if (!row || !list) {
					return;
				}
				if (list.querySelectorAll('.time-range-row').length <= 1) {
					return;
				}
				const day = list.dataset.day;
				row.querySelectorAll('.from-time, .to-time').forEach((select) => {
					this.destroySelect2(this._jQuery(select));
				});
				row.remove();
				if (list.querySelectorAll('.time-range-row').length <= 1) {
					list.querySelectorAll('.remove-time-split-btn').forEach((btn) => {
						btn.style.display = 'none';
					});
				}
				this._updateAddButtonVisibility(day);
				this._handleTimeRangeChange(day);
			});

			this.root.querySelectorAll('.add-time-split-btn').forEach((btn) => {
				btn.addEventListener('click', (e) => {
					const day = e.currentTarget.dataset.day;
					const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
					if (!list) {
						return;
					}
					const max = this.options.maxSplitsPerDay;
					if (max !== null && list.querySelectorAll('.time-range-row').length >= max) {
						return;
					}
					this._addTimeSplitRow(
						list,
						'08:00',
						day === 'friday' ? '16:00' : '18:00',
						day
					);
					this._handleTimeRangeChange(day);
				});
			});

			const repeater = this.getTreatmentsRepeater();
			const addBtn = this.options.addTreatmentButtonSelector
				? this.root.querySelector(this.options.addTreatmentButtonSelector)
				: this.root.querySelector('.add-treatment-btn');

			if (addBtn) {
				if (this._addTreatmentBtn && this._onAddTreatmentClick) {
					this._addTreatmentBtn.removeEventListener('click', this._onAddTreatmentClick);
				}
				this._addTreatmentBtn = addBtn;
				addBtn.addEventListener('click', this._onAddTreatmentClick);
			}

			if (repeater) {
				repeater.addEventListener('click', (e) => {
					const btn = e.target.closest('.remove-treatment-btn');
					if (!btn) {
						return;
					}
					const row = btn.closest('.treatment-row');
					if (row) {
						this.removeTreatmentRow(row);
					}
				});

				const runValidation = () => this.validateTreatmentsComplete();
				repeater.addEventListener('change', (e) => {
					if (e.target.matches('.portal-treatment-select, .treatment-cost-input, .treatment-duration-input, .clinix-treatment-select')) {
						runValidation();
					}
				});
				repeater.addEventListener('input', (e) => {
					if (e.target.matches('.treatment-cost-input, .treatment-duration-input')) {
						runValidation();
					}
				});

				if (this.hasSelect2()) {
					window.jQuery(repeater).on('select2:select select2:clear', (e) => {
						if (window.jQuery(e.target).is('.portal-treatment-select, .clinix-treatment-select')) {
							runValidation();
						}
					});
				}
			}

			this.initScheduleSelectFields(this.root);
			this._initializeDayTimeRanges();
			this.validateTreatmentsComplete();
		}
	}

	window.ClinicQueueScheduleSettingsUI = ScheduleSettingsUI;
	window.ClinicQueueScheduleSettingsUI.DAYS_ORDER = DAYS_ORDER;

})(window);
