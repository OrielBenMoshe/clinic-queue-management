/**
 * Shared schedule-settings UI (days, time ranges, treatments).
 *
 * @package Clinic_Queue_Management
 */

(function (window) {
	'use strict';

	const DAYS_ORDER = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
	const PORTAL_TREATMENT_DROPDOWN_WIDTH = 480;
	const PORTAL_TREATMENT_DROPDOWN_GAP = 6;
	const PORTAL_TREATMENT_VIEWPORT_PADDING = 8;
	const PORTAL_TREATMENT_RESULTS_MAX_HEIGHT = 500;
	const PORTAL_TREATMENT_RESULTS_MIN_HEIGHT = 80;

	/**
	 * @param {HTMLElement} root
	 * @param {Object} [options]
	 */
	class ScheduleSettingsUI {
		constructor(root, options = {}) {
			const existing = root && root._clinicQueueScheduleSettingsUI;
			if (existing instanceof ScheduleSettingsUI) {
				Object.assign(existing.options, options);
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
					addTreatmentButtonSelector: '.add-treatment-btn',
					noDaysMessageSelector: '',
					dayRowSelector: '.day-row',
					select2MinimumResultsForSearch: null,
					onValidationChange: null,
					onAddTreatmentRow: null,
					buildPortalOptionsHtml: null,
					getDropdownParent: null,
					deferSelectInit: false,
				},
				options
			);
			this._treatmentRowIndex = 0;
			this._portalTerms = Array.isArray(this.options.portalTerms) ? this.options.portalTerms : [];
			this._noWorkDaysBlocked = false;
			this._bound = false;
			this._addTreatmentBtn = null;

			if (root) {
				root._clinicQueueScheduleSettingsUI = this;
			}
		}

		/**
		 * Stable click handler stored on the form root so rebinding does not stack listeners.
		 *
		 * @returns {Function}
		 */
		_getAddTreatmentClickHandler() {
			if (!this.root._clinicQueueOnAddTreatmentClick) {
				this.root._clinicQueueOnAddTreatmentClick = (e) => {
					if (e) {
						e.preventDefault();
					}
					const ui = this.root._clinicQueueScheduleSettingsUI;
					if (ui && typeof ui.addTreatmentRow === 'function') {
						ui.addTreatmentRow();
					}
				};
			}
			return this.root._clinicQueueOnAddTreatmentClick;
		}

		/**
		 * @returns {Array<{ name: string, drWebID: string, duration: number, cost: number }>}
		 */
		_getClinixTreatmentReasons() {
			if (Array.isArray(this.root._clinixTreatmentReasons)) {
				return this.root._clinixTreatmentReasons;
			}
			if (Array.isArray(this.root.clinicReasons)) {
				return this.root.clinicReasons;
			}
			return [];
		}

		/**
		 * @param {Array} treatments
		 */
		_storeClinixTreatmentReasonsFromTreatments(treatments) {
			const existing = this._getClinixTreatmentReasons();
			if (existing.length) {
				return;
			}

			const list = Array.isArray(treatments) ? treatments : [];
			const reasons = list
				.filter((treatment) => treatment && (treatment.clinix_treatment_id || treatment.clinix_treatment_name))
				.map((treatment) => ({
					name: treatment.clinix_treatment_name || String(treatment.clinix_treatment_id || ''),
					drWebID: String(treatment.clinix_treatment_id || ''),
					duration: parseInt(treatment.duration, 10) || 0,
					cost: parseInt(treatment.cost, 10) || 0,
				}))
				.filter((reason) => reason.drWebID);

			if (reasons.length) {
				this.root._clinixTreatmentReasons = reasons;
				this.root.clinicReasons = reasons;
			}
		}

		/**
		 * @param {HTMLSelectElement} targetSelect
		 * @returns {boolean}
		 */
		_copyClinixOptionsFromDefaultRow(targetSelect) {
			const defaultClinix = this.root.querySelector('.treatment-row-default .clinix-treatment-select');
			if (!defaultClinix || !targetSelect || defaultClinix.options.length <= 1) {
				return false;
			}

			const selectedId = targetSelect.value || '';
			this.destroySelect2(this._jQuery(targetSelect));
			targetSelect.innerHTML = '';

			const placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = 'בחר טיפול';
			targetSelect.appendChild(placeholder);

			Array.from(defaultClinix.options).forEach((opt) => {
				if (!opt.value) {
					return;
				}
				const clone = document.createElement('option');
				clone.value = opt.value;
				clone.textContent = opt.textContent;
				if (selectedId && opt.value === selectedId) {
					clone.selected = true;
				}
				targetSelect.appendChild(clone);
			});

			targetSelect.value = selectedId;
			targetSelect.disabled = false;
			return true;
		}

		/**
		 * @param {HTMLSelectElement} select
		 * @param {Array} reasons
		 * @param {string|number} [selectedId='']
		 */
		_fillClinixTreatmentSelectOptions(select, reasons, selectedId = '') {
			if (!select) {
				return;
			}

			this.destroySelect2(this._jQuery(select));

			const selected = selectedId ? String(selectedId) : '';
			const list = Array.isArray(reasons) ? reasons : [];

			select.innerHTML = '';

			const placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = list.length ? 'בחר טיפול' : 'לא נמצאו טיפולים';
			select.appendChild(placeholder);

			list.forEach((reason) => {
				const opt = document.createElement('option');
				const reasonId = String(reason.drWebID);
				opt.value = reasonId;
				opt.textContent = reason.name || reasonId;
				if (selected && reasonId === selected) {
					opt.selected = true;
				}
				select.appendChild(opt);
			});

			select.disabled = list.length === 0;
			select.value = selected;
		}

		/**
		 * Populate a newly added Clinix treatment select from cached reasons or the default row.
		 *
		 * @param {HTMLSelectElement|null} select
		 * @param {string|number} [selectedId='']
		 */
		_populateNewRowClinixSelect(select, selectedId = '') {
			if (!select) {
				return;
			}

			const reasons = this._getClinixTreatmentReasons();
			if (reasons.length) {
				this._fillClinixTreatmentSelectOptions(select, reasons, selectedId);
				return;
			}

			this._copyClinixOptionsFromDefaultRow(select);
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
				$select.val(select.value).trigger('change.select2');
			}
		}

		destroySelect2($el) {
			if (!this.hasSelect2() || !$el || !$el.length) {
				return;
			}
			if ($el.hasClass('portal-treatment-select')) {
				$el.off(
					'select2:opening.portalTreatmentPosition select2:open.portalTreatmentPosition select2:close.portalTreatmentPosition'
				);
				this.clearPortalTreatmentScrollPosition($el[0]);
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
				if (select.classList.contains('doctor-select')) {
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
				const customParent = this.options.getDropdownParent(element);
				if (customParent) {
					return customParent;
				}
			}

			const modalOverlay = element && element.closest('#schedule-table-edit-modal');
			if (modalOverlay) {
				return modalOverlay;
			}

			return this.root;
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
				...(isTimeSelect && {
					dropdownCssClass: 'time-select-dropdown',
					templateResult: (data) => (
						data.element && data.element.disabled ? null : data.text
					),
				}),
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

		getPortalSelectPlaceholder() {
			return this._portalTerms.length > 0 ? 'בחר סוג טיפול' : 'לא נמצאו סוגי טיפולים';
		}

		/**
		 * Re-init portal treatment Select2 after options change (wizard + edit modal).
		 *
		 * @param {HTMLElement|null} scope Optional container; defaults to treatments repeater
		 */
		refreshPortalTreatmentSelects(scope) {
			const container = scope || this.getTreatmentsRepeater();
			if (!container) {
				return;
			}
			container.querySelectorAll('.portal-treatment-select').forEach((select) => {
				this.initPortalSelect2(select);
			});
		}

		hasInlineSearch($el) {
			return !!(window.ClinicQueueSelect2 && window.ClinicQueueSelect2.isSearchable($el));
		}

		getPortalDropdownCssClass($el) {
			const classes = ['portal-treatment-dropdown'];
			if (this.hasInlineSearch($el)) {
				classes.push('clinic-queue-filterable');
			}
			return classes.join(' ');
		}

		/**
		 * Collect scrollable ancestors for scroll restoration.
		 *
		 * @param {HTMLElement|null} element
		 * @returns {HTMLElement[]}
		 */
		getPortalTreatmentScrollableAncestors(element) {
			const ancestors = [];
			let el = element;

			while (el) {
				const style = window.getComputedStyle(el);
				const overflowY = style.overflowY;
				if (
					(overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay')
					&& el.scrollHeight > el.clientHeight
				) {
					ancestors.push(el);
				}
				el = el.parentElement;
			}

			return ancestors;
		}

		/**
		 * Save window and ancestor scroll positions before portal dropdown open.
		 *
		 * @param {HTMLSelectElement} select
		 */
		capturePortalTreatmentScrollPosition(select) {
			const $el = this._jQuery(select);
			const containerEl = $el && $el.data('select2')?.$container?.[0];
			const scrollNodes = new Set(this.getPortalTreatmentScrollableAncestors(containerEl));

			select._portalTreatmentScrollSnapshot = {
				windowX: window.scrollX,
				windowY: window.scrollY,
				elements: Array.from(scrollNodes).map((node) => ({
					node,
					scrollTop: node.scrollTop,
					scrollLeft: node.scrollLeft,
				})),
			};
		}

		/**
		 * Restore scroll positions captured on portal dropdown open.
		 *
		 * @param {HTMLSelectElement} select
		 */
		restorePortalTreatmentScrollPosition(select) {
			const snapshot = select?._portalTreatmentScrollSnapshot;
			if (!snapshot) {
				return;
			}

			window.scrollTo(snapshot.windowX, snapshot.windowY);
			snapshot.elements.forEach(({ node, scrollTop, scrollLeft }) => {
				if (node && node.isConnected) {
					node.scrollTop = scrollTop;
					node.scrollLeft = scrollLeft;
				}
			});
		}

		/**
		 * Clear stored portal dropdown scroll snapshot.
		 *
		 * @param {HTMLSelectElement|null} select
		 */
		clearPortalTreatmentScrollPosition(select) {
			if (select) {
				delete select._portalTreatmentScrollSnapshot;
			}
		}

		/**
		 * Reset inline portal dropdown styles applied during positioning.
		 *
		 * @param {HTMLSelectElement} select
		 */
		resetPortalTreatmentDropdownStyles(select) {
			const $el = this._jQuery(select);
			if (!$el || !$el.length) {
				return;
			}

			const select2Instance = $el.data('select2');
			if (!select2Instance || !select2Instance.$dropdown) {
				return;
			}

			const dropdownEl = select2Instance.$dropdown[0];
			if (!dropdownEl) {
				return;
			}

			dropdownEl.style.position = '';
			dropdownEl.style.width = '';
			dropdownEl.style.minWidth = '';

			const resultsEl = dropdownEl.querySelector('.select2-results');
			if (resultsEl) {
				resultsEl.style.maxHeight = '';
			}

			select2Instance.$dropdown.css({
				top: '',
				left: '',
				right: '',
			});
		}

		/**
		 * Clamp portal treatment results height to available viewport space above the field.
		 *
		 * @param {HTMLElement} dropdownEl
		 * @param {DOMRect} triggerRect
		 * @returns {number}
		 */
		clampPortalTreatmentResultsHeight(dropdownEl, triggerRect) {
			const resultsEl = dropdownEl.querySelector('.select2-results');
			if (!resultsEl) {
				return dropdownEl.offsetHeight;
			}

			resultsEl.style.maxHeight = `${PORTAL_TREATMENT_RESULTS_MAX_HEIGHT}px`;

			const availableAbove = triggerRect.top
				- PORTAL_TREATMENT_VIEWPORT_PADDING
				- PORTAL_TREATMENT_DROPDOWN_GAP;
			let dropdownHeight = dropdownEl.offsetHeight;

			if (availableAbove > 0 && dropdownHeight > availableAbove) {
				const chromeHeight = dropdownHeight - resultsEl.offsetHeight;
				const clampedResultsHeight = Math.max(
					PORTAL_TREATMENT_RESULTS_MIN_HEIGHT,
					Math.min(
						PORTAL_TREATMENT_RESULTS_MAX_HEIGHT,
						availableAbove - chromeHeight
					)
				);
				resultsEl.style.maxHeight = `${clampedResultsHeight}px`;
				dropdownHeight = dropdownEl.offsetHeight;
			}

			return dropdownHeight;
		}

		/**
		 * Position portal treatment dropdown above the field with viewport clamping (RTL-aware).
		 *
		 * @param {HTMLSelectElement} select
		 */
		positionPortalTreatmentDropdown(select) {
			const $el = this._jQuery(select);
			if (!$el || !$el.length) {
				return;
			}

			const select2Instance = $el.data('select2');
			if (!select2Instance || !select2Instance.$dropdown || !select2Instance.$container) {
				return;
			}

			const $dropdown = select2Instance.$dropdown;
			if (!$dropdown.hasClass('portal-treatment-dropdown')) {
				return;
			}

			const runPosition = () => {
				const containerEl = select2Instance.$container[0];
				const dropdownEl = $dropdown[0];
				if (!containerEl || !dropdownEl) {
					return;
				}

				const triggerRect = containerEl.getBoundingClientRect();

				dropdownEl.style.position = 'fixed';
				dropdownEl.style.width = `${PORTAL_TREATMENT_DROPDOWN_WIDTH}px`;
				dropdownEl.style.minWidth = `${PORTAL_TREATMENT_DROPDOWN_WIDTH}px`;

				const dropdownHeight = this.clampPortalTreatmentResultsHeight(dropdownEl, triggerRect);

				let top = triggerRect.top - dropdownHeight - PORTAL_TREATMENT_DROPDOWN_GAP;
				top = Math.max(PORTAL_TREATMENT_VIEWPORT_PADDING, top);

				const maxTop = window.innerHeight
					- PORTAL_TREATMENT_VIEWPORT_PADDING
					- dropdownHeight;
				top = Math.min(top, maxTop);

				let left = triggerRect.right - PORTAL_TREATMENT_DROPDOWN_WIDTH;
				left = Math.max(PORTAL_TREATMENT_VIEWPORT_PADDING, left);
				left = Math.min(
					left,
					window.innerWidth - PORTAL_TREATMENT_VIEWPORT_PADDING - PORTAL_TREATMENT_DROPDOWN_WIDTH
				);

				$dropdown.css({
					position: 'fixed',
					top: `${top}px`,
					left: `${left}px`,
					right: 'auto',
				});

				this.restorePortalTreatmentScrollPosition(select);
			};

			window.requestAnimationFrame(() => {
				runPosition();
				window.requestAnimationFrame(() => {
					this.restorePortalTreatmentScrollPosition(select);
				});
			});
		}

		/**
		 * Bind namespaced portal treatment dropdown open/close handlers.
		 *
		 * @param {jQuery} $el
		 * @param {HTMLSelectElement} select
		 */
		bindPortalTreatmentDropdownPosition($el, select) {
			$el.off(
				'select2:opening.portalTreatmentPosition select2:open.portalTreatmentPosition select2:close.portalTreatmentPosition'
			);

			$el.on('select2:opening.portalTreatmentPosition', () => {
				this.capturePortalTreatmentScrollPosition(select);
			});

			$el.on('select2:open.portalTreatmentPosition', () => {
				const searchField = document.querySelector(
					'.select2-dropdown.portal-treatment-dropdown .select2-search__field'
				);
				if (searchField && typeof searchField.focus === 'function') {
					searchField.focus({ preventScroll: true });
				}

				this.positionPortalTreatmentDropdown(select);
			});

			$el.on('select2:close.portalTreatmentPosition', () => {
				this.resetPortalTreatmentDropdownStyles(select);
				this.clearPortalTreatmentScrollPosition(select);
			});
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

			const hasInlineSearch = this.hasInlineSearch($el);

			const config = this.buildSelect2Options($el, {
				placeholder: this.getPortalSelectPlaceholder(),
				dropdownCssClass: this.getPortalDropdownCssClass($el),
				...(hasInlineSearch ? { minimumResultsForSearch: 0 } : {}),
			});

			if (this.options.select2MinimumResultsForSearch !== null && !hasInlineSearch) {
				config.minimumResultsForSearch = this.options.select2MinimumResultsForSearch;
			}

			$el.select2(config);
			this.bindPortalTreatmentDropdownPosition($el, select);

			if (hasInlineSearch) {
				window.ClinicQueueSelect2.setupInlineSearch(
					$el,
					window.jQuery(this.getSelect2DropdownParent(select))
				);
			}

			if (!$el.val()) {
				$el.val('').trigger('change.select2');
			}
		}

		getClinixSelectPlaceholder() {
			return 'בחר טיפול';
		}

		/**
		 * Re-init Clinix treatment Select2 after options change (wizard + edit modal).
		 *
		 * @param {HTMLElement|null} scope Optional container; defaults to treatments repeater
		 */
		refreshClinixTreatmentSelects(scope) {
			const container = scope || this.getTreatmentsRepeater();
			if (!container) {
				return;
			}
			container.querySelectorAll('.clinix-treatment-select').forEach((select) => {
				this.initClinixSelect2(select);
			});
		}

		initClinixSelect2(select) {
			if (!this.hasSelect2() || !select) {
				return;
			}
			const $el = this._jQuery(select);
			if (!$el || !$el.length) {
				return;
			}

			this.destroySelect2($el);

			const placeholderOption = $el.find('option[value=""]').first();
			const hasInlineSearch = this.hasInlineSearch($el);
			const inlineSearchOpts = hasInlineSearch
				? window.ClinicQueueSelect2.getInlineSearchOptions($el)
				: {};

			const config = this.buildSelect2Options($el, {
				placeholder: placeholderOption.length
					? (placeholderOption.text() || this.getClinixSelectPlaceholder())
					: this.getClinixSelectPlaceholder(),
				...inlineSearchOpts,
			});

			if (this.options.select2MinimumResultsForSearch !== null && !hasInlineSearch) {
				config.minimumResultsForSearch = this.options.select2MinimumResultsForSearch;
			}

			$el.select2(config);

			if (hasInlineSearch) {
				window.ClinicQueueSelect2.setupInlineSearch(
					$el,
					window.jQuery(this.getSelect2DropdownParent(select))
				);
			}

			if (!$el.val()) {
				$el.val('').trigger('change.select2');
			}
		}

		/**
		 * Initialize Select2 on schedule-settings fields (time + portal + clinix), same as wizard step 3.
		 *
		 * @param {HTMLElement|null} scope Optional container; defaults to form root
		 */
		initScheduleSelectFields(scope) {
			const container = scope || this.root;
			if (!container) {
				return;
			}

			container.querySelectorAll('.select-field').forEach((select) => {
				if (select.classList.contains('doctor-select')) {
					return;
				}
				if (select.classList.contains('portal-treatment-select')) {
					this.initPortalSelect2(select);
					return;
				}
				if (select.classList.contains('clinix-treatment-select')) {
					this.initClinixSelect2(select);
					return;
				}
				this.initTimeSelect2(select);
			});
		}

		/**
		 * Initialize Select2 on time-range selects only (after fillDays).
		 *
		 * @param {HTMLElement|null} scope Optional container; defaults to form root
		 */
		_initTimeSelectFields(scope) {
			const container = scope || this.root;
			if (!container) {
				return;
			}

			container.querySelectorAll('.from-time, .to-time, .time-select').forEach((select) => {
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

			if (isClinix) {
				this.root.querySelectorAll('.add-time-split-btn, .remove-time-split-btn').forEach((btn) => {
					btn.style.display = 'none';
				});
			} else {
				DAYS_ORDER.forEach((day) => {
					this._updateAddButtonVisibility(day);
					const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
					if (list) {
						this._refreshTimeSplitRemoveButtons(list);
					}
				});
			}

			if (this._noWorkDaysBlocked) {
				this._disableAllTreatmentFields();
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
				}

				this._refreshTimeSplitRemoveButtons(list);
				this._updateAddButtonVisibility(day);

				row.classList.remove('day-row--clinix-hidden');
			});

			this.setNoWorkDaysBlocked(false);
		}

		fillDays(daysData) {
			this.resetDays();
			const data = daysData && typeof daysData === 'object' ? daysData : {};
			const hasDays = Object.keys(data).length > 0;

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
					this._refreshTimeSplitRemoveButtons(list);
				}

				this._updateAddButtonVisibility(day);

				if (this.options.normalizeTimeRanges) {
					this._normalizeDayTimeRanges(day);
				}
			});

			if (!this.options.deferSelectInit) {
				this._initTimeSelectFields(this.root);
			}

			if (this.options.scheduleType === 'clinix' || this.isClinixFlow()) {
				DAYS_ORDER.forEach((day) => {
					const row = this.getDayRow(day);
					if (!row) {
						return;
					}
					const ranges = data[day];
					if (!Array.isArray(ranges) || ranges.length === 0) {
						row.classList.add('day-row--clinix-hidden');
					}
				});
				this.setNoWorkDaysBlocked(!hasDays);
			} else {
				this.validateTreatmentsComplete();
			}
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

		/**
		 * Sync native select disabled state with Select2 widget.
		 *
		 * @param {HTMLSelectElement|null} select
		 * @param {boolean} disabled
		 */
		_syncSelectDisabled(select, disabled) {
			if (!select) {
				return;
			}
			select.disabled = disabled;
			const $select = this._jQuery(select);
			if ($select && $select.hasClass('select2-hidden-accessible')) {
				$select.prop('disabled', disabled).trigger('change.select2');
			}
		}

		/**
		 * Disable all treatment row fields (no work days in Clinix calendar).
		 */
		_disableAllTreatmentFields() {
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return;
			}

			repeater.querySelectorAll('.treatment-row').forEach((row) => {
				row.querySelectorAll('.portal-treatment-select, .clinix-treatment-select').forEach((select) => {
					this._syncSelectDisabled(select, true);
					const wrap = select.closest('.jet-form-builder__row');
					if (wrap) {
						wrap.classList.add('field-disabled');
					}
				});

				row.querySelectorAll('.treatment-cost-input, .treatment-duration-input').forEach((input) => {
					input.disabled = true;
					const wrap = input.closest('.jet-form-builder__row');
					if (wrap) {
						wrap.classList.add('field-disabled');
					}
				});

				const removeBtn = row.querySelector('.remove-treatment-btn');
				if (removeBtn) {
					removeBtn.disabled = true;
				}
			});

			const addBtn = this.options.addTreatmentButtonSelector
				? this.root.querySelector(this.options.addTreatmentButtonSelector)
				: this.root.querySelector('.add-treatment-btn');
			if (addBtn) {
				addBtn.disabled = true;
			}
		}

		/**
		 * Re-enable treatment fields after work days are available (respects Clinix/Google flow rules).
		 */
		_restoreTreatmentFieldsAfterWorkDays() {
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return;
			}

			const isClinix = this.isClinixFlow(repeater);

			repeater.querySelectorAll('.treatment-row').forEach((row) => {
				row.querySelectorAll('.portal-treatment-select, .clinix-treatment-select').forEach((select) => {
					this._syncSelectDisabled(select, false);
					const wrap = select.closest('.jet-form-builder__row');
					if (wrap) {
						wrap.classList.remove('field-disabled');
					}
				});

				row.querySelectorAll('.treatment-cost-input, .treatment-duration-input').forEach((input) => {
					input.disabled = isClinix;
					const wrap = input.closest('.jet-form-builder__row');
					if (wrap) {
						wrap.classList.toggle('field-disabled', isClinix);
					}
				});

				const removeBtn = row.querySelector('.remove-treatment-btn');
				if (removeBtn) {
					removeBtn.disabled = false;
				}
			});

			const addBtn = this.options.addTreatmentButtonSelector
				? this.root.querySelector(this.options.addTreatmentButtonSelector)
				: this.root.querySelector('.add-treatment-btn');
			if (addBtn) {
				addBtn.disabled = false;
				if (isClinix) {
					addBtn.setAttribute('hidden', '');
				}
			}
		}

		/**
		 * Wizard treatments heading row (sibling before .treatments-repeater).
		 *
		 * @returns {HTMLElement|null}
		 */
		_getTreatmentsHeadingRow() {
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return null;
			}

			let prev = repeater.previousElementSibling;
			while (prev) {
				if (prev.classList.contains('field-type-heading')) {
					return prev;
				}
				prev = prev.previousElementSibling;
			}

			return null;
		}

		/**
		 * Hide or show days/hours and treatments UI when Clinix has no work days.
		 * No-work-days message stays visible when hidden is true.
		 *
		 * @param {boolean} hidden
		 */
		_setScheduleSectionsHidden(hidden) {
			const daysContainer = this.root.querySelector('.days-schedule-container');

			if (daysContainer) {
				daysContainer.querySelectorAll(this.options.dayRowSelector).forEach((row) => {
					if (hidden) {
						row.setAttribute('hidden', '');
					} else {
						row.removeAttribute('hidden');
					}
				});
			}

			const daysTitleRow = this.root.querySelector('.schedule-settings-step-title')?.closest('.jet-form-builder__row');
			if (daysTitleRow) {
				if (hidden) {
					daysTitleRow.setAttribute('hidden', '');
				} else {
					daysTitleRow.removeAttribute('hidden');
				}
			}

			const daysSection = daysContainer ? daysContainer.closest('.edit-modal__section') : null;
			if (daysSection) {
				const header = daysSection.querySelector('.edit-modal__section-header');
				if (header) {
					if (hidden) {
						header.setAttribute('hidden', '');
					} else {
						header.removeAttribute('hidden');
					}
				}
			}

			const treatmentsHeading = this._getTreatmentsHeadingRow();
			if (treatmentsHeading) {
				if (hidden) {
					treatmentsHeading.setAttribute('hidden', '');
				} else {
					treatmentsHeading.removeAttribute('hidden');
				}
			}

			const repeater = this.getTreatmentsRepeater();
			if (repeater) {
				if (hidden) {
					repeater.setAttribute('hidden', '');
				} else {
					repeater.removeAttribute('hidden');
				}
			}

			const addBtn = this.options.addTreatmentButtonSelector
				? this.root.querySelector(this.options.addTreatmentButtonSelector)
				: this.root.querySelector('.add-treatment-btn');
			if (addBtn) {
				if (hidden) {
					addBtn.setAttribute('hidden', '');
				} else {
					addBtn.removeAttribute('hidden');
				}
			}

			const treatmentsSection = repeater ? repeater.closest('.edit-modal__section') : null;
			if (treatmentsSection && treatmentsSection !== daysSection) {
				if (hidden) {
					treatmentsSection.setAttribute('hidden', '');
				} else {
					treatmentsSection.removeAttribute('hidden');
				}
			}
		}

		/**
		 * Block schedule creation when Clinix calendar has no work days/hours.
		 * Shows message, hides days/treatments sections, disables save button.
		 *
		 * @param {boolean} blocked
		 */
		setNoWorkDaysBlocked(blocked) {
			this._noWorkDaysBlocked = !!blocked;
			this._toggleNoDaysMessage(this._noWorkDaysBlocked);
			this._setScheduleSectionsHidden(this._noWorkDaysBlocked);

			if (this._noWorkDaysBlocked) {
				this._disableAllTreatmentFields();
			} else {
				this._restoreTreatmentFieldsAfterWorkDays();
				this.applyScheduleTypeRules(this.options.scheduleType);
			}

			this.validateTreatmentsComplete();
		}

		_timeToMinutes(timeStr) {
			const parts = (timeStr || '0:0').split(':').map(Number);
			return (parts[0] * 60) + (parts[1] || 0);
		}

		/**
		 * End time of the last range row in a day's list (for new split defaults).
		 *
		 * @param {HTMLElement} list
		 * @returns {string}
		 */
		_getLastRangeEndTime(list) {
			if (!list) {
				return '08:00';
			}
			const rows = list.querySelectorAll('.time-range-row');
			const lastRow = rows[rows.length - 1];
			if (!lastRow) {
				return '08:00';
			}
			const toSelect = lastRow.querySelector('.to-time');
			return (toSelect && toSelect.value) ? toSelect.value : '08:00';
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

		/**
		 * Reset cloned selects after duplicating a time-range row (strip Select2 artifacts).
		 *
		 * @param {HTMLElement} row
		 */
		_resetClonedTimeRangeSelects(row) {
			row.querySelectorAll('.select2-container').forEach((el) => el.remove());
			row.querySelectorAll('select').forEach((select) => {
				select.classList.remove('select2-hidden-accessible');
				select.removeAttribute('data-select2-id');
				select.removeAttribute('aria-hidden');
				select.removeAttribute('tabindex');
				select.disabled = false;
			});
		}

		/**
		 * Show remove buttons when multiple splits exist; hide when only one row remains.
		 *
		 * @param {HTMLElement} list
		 */
		_refreshTimeSplitRemoveButtons(list) {
			if (!list) {
				return;
			}

			const rows = list.querySelectorAll('.time-range-row');
			const multiple = rows.length > 1;
			rows.forEach((row) => {
				const btn = row.querySelector('.remove-time-split-btn');
				if (!btn) {
					return;
				}
				btn.disabled = false;
				if (multiple) {
					btn.style.display = 'inline-flex';
				} else {
					btn.style.display = 'none';
				}
			});
		}

		/**
		 * Remove one time-split row and refresh split controls for the day.
		 *
		 * @param {HTMLElement} row
		 * @param {HTMLElement} list
		 * @param {string} day
		 */
		_removeTimeSplitRow(row, list, day) {
			if (!row || !list || list.querySelectorAll('.time-range-row').length <= 1) {
				return;
			}

			row.querySelectorAll('.from-time, .to-time').forEach((select) => {
				this.destroySelect2(this._jQuery(select));
			});
			row.remove();
			this._refreshTimeSplitRemoveButtons(list);
			this._updateAddButtonVisibility(day);
			this._handleTimeRangeChange(day);
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

			const clone = first.cloneNode(false);
			clone.innerHTML = first.innerHTML;
			this._resetClonedTimeRangeSelects(clone);

			const from = clone.querySelector('.from-time');
			const to = clone.querySelector('.to-time');
			if (from) {
				from.value = fromTime || '08:00';
			}
			if (to) {
				to.value = toTime || (day === 'friday' ? '16:00' : '18:00');
			}

			list.appendChild(clone);
			this._refreshTimeSplitRemoveButtons(list);
			this._updateAddButtonVisibility(day);
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
				row.querySelectorAll('.portal-treatment-select, .clinix-treatment-select').forEach((select) => {
					this.destroySelect2(this._jQuery(select));
				});
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
				this.destroySelect2(this._jQuery(clinix));
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
		}

		_getRemoveTreatmentIconHtml() {
			if (this.options.trashIcon) {
				return this.options.trashIcon;
			}
			const repeater = this.getTreatmentsRepeater();
			const source = repeater ? repeater.querySelector('.remove-treatment-btn-icon-source') : null;
			return source ? source.innerHTML : '';
		}

		_ensureRemoveButton(row) {
			if (!row || row.classList.contains('treatment-row-default')) {
				return null;
			}

			let btn = row.querySelector('.remove-treatment-btn');
			if (!btn) {
				btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'remove-treatment-btn';
				btn.setAttribute('aria-label', 'הסר טיפול');
				btn.innerHTML = this._getRemoveTreatmentIconHtml();
				row.appendChild(btn);
			}

			btn.removeAttribute('hidden');
			return btn;
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
				}
				this.validateTreatmentsComplete();
				return;
			}

			if (this.isClinixFlow(repeater)) {
				this._storeClinixTreatmentReasonsFromTreatments(list);
			}

			list.forEach((treatment, index) => {
				let row = defaultRow;
				if (index > 0) {
					row = this._cloneTreatmentRow();
					repeater.appendChild(row);
				}
				this._fillTreatmentRow(row, treatment, index);
			});

			if (this.isClinixFlow(repeater)) {
				const reasons = this._getClinixTreatmentReasons();
				if (reasons.length) {
					repeater.querySelectorAll('.treatment-row').forEach((row, index) => {
						const clinixSel = row.querySelector('.clinix-treatment-select');
						const treatment = list[index];
						if (!clinixSel || !treatment) {
							return;
						}
						this._fillClinixTreatmentSelectOptions(
							clinixSel,
							reasons,
							treatment.clinix_treatment_id || ''
						);
					});
				}
			}

			this._refreshRemoveButtons();

			if (this._noWorkDaysBlocked) {
				this._disableAllTreatmentFields();
			}
			this.validateTreatmentsComplete();
		}

		_fillTreatmentRow(row, treatment, index) {
			row.setAttribute('data-row-index', String(index));

			const clinixSel = row.querySelector('.clinix-treatment-select');
			if (clinixSel) {
				this.destroySelect2(this._jQuery(clinixSel));
				clinixSel.innerHTML = '';
				if (treatment.clinix_treatment_id || treatment.clinix_treatment_name) {
					const opt = document.createElement('option');
					opt.value = treatment.clinix_treatment_id || '';
					opt.textContent = treatment.clinix_treatment_name || treatment.clinix_treatment_id || '';
					clinixSel.appendChild(opt);
					clinixSel.value = treatment.clinix_treatment_id || '';
				}
			}

			const portalSel = row.querySelector('.portal-treatment-select');
			if (portalSel) {
				this.destroySelect2(this._jQuery(portalSel));
				portalSel.innerHTML = this.buildPortalOptionsHtml(treatment.treatment_type || 0);
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

			this._ensureRemoveButton(clone);

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

			const clinix = row.querySelector('.clinix-treatment-select');
			if (clinix && this.isClinixFlow(repeater)) {
				this._populateNewRowClinixSelect(clinix);
			}

			if (typeof this.options.onAddTreatmentRow === 'function') {
				this.options.onAddTreatmentRow(row, repeater);
			} else {
				const portal = row.querySelector('.portal-treatment-select');
				if (portal) {
					this.initPortalSelect2(portal);
				}
				if (clinix) {
					this.initClinixSelect2(clinix);
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
			row.querySelectorAll('.portal-treatment-select, .clinix-treatment-select').forEach((select) => {
				this.destroySelect2(this._jQuery(select));
			});
			row.remove();
			this._refreshRemoveButtons();
			this.validateTreatmentsComplete();
		}

		_refreshRemoveButtons() {
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return;
			}
			const rows = repeater.querySelectorAll('.treatment-row:not(.treatment-row-default)');
			const multiple = repeater.querySelectorAll('.treatment-row').length > 1;
			rows.forEach((row) => {
				const btn = this._ensureRemoveButton(row);
				if (!btn) {
					return;
				}
				if (multiple) {
					btn.removeAttribute('hidden');
				} else {
					btn.setAttribute('hidden', '');
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

		/**
		 * Resolve save button — may live outside form root (e.g. edit modal footer).
		 *
		 * @returns {HTMLElement|null}
		 */
		_getSaveButton() {
			const selector = this.options.saveButtonSelector;
			if (!selector || !this.root) {
				return null;
			}
			return this.root.querySelector(selector) || document.querySelector(selector);
		}

		validate(context, scheduleType) {
			const type = scheduleType || this.options.scheduleType || 'google';
			const days = this.collectDays();
			const treatments = this.collectTreatments();

			if (this._noWorkDaysBlocked || (type === 'clinix' && Object.keys(days).length === 0)) {
				if (context === 'edit_modal') {
					return 'לא מוגדרים ימי עבודה ביומן זה.';
				}
				return 'לא מוגדרים ימים ושעות עבודה, נא לחזור אחורה לבחירת יומן אחר.';
			}
			if (type === 'google' && Object.keys(days).length === 0) {
				return 'אנא בחר לפחות יום עבודה אחד.';
			}
			if (treatments.length === 0) {
				return 'אנא הגדר לפחות טיפול אחד.';
			}
			return null;
		}

		validateTreatmentsComplete() {
			const saveBtn = this._getSaveButton();
			const repeater = this.getTreatmentsRepeater();
			if (!repeater) {
				return false;
			}

			if (this._noWorkDaysBlocked) {
				if (saveBtn) {
					saveBtn.disabled = true;
				}
				if (typeof this.options.onValidationChange === 'function') {
					this.options.onValidationChange(false);
				}
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

		unbindEvents() {
			const handler = this.root && this.root._clinicQueueOnAddTreatmentClick;
			if (this._addTreatmentBtn && handler) {
				this._addTreatmentBtn.removeEventListener('click', handler);
			}
			this._addTreatmentBtn = null;
			this._bound = false;
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
					if (
						this._noWorkDaysBlocked
						&& this.options.scheduleType !== 'clinix'
						&& !this.isClinixFlow()
						&& this._hasAtLeastOneDayChecked()
					) {
						this.setNoWorkDaysBlocked(false);
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

			this.root.querySelectorAll('.time-ranges-list').forEach((list) => {
				list.addEventListener('click', (e) => {
					const removeBtn = e.target.closest('.remove-time-split-btn');
					if (!removeBtn || removeBtn.disabled) {
						return;
					}
					const row = removeBtn.closest('.time-range-row');
					if (!row || !list.contains(row)) {
						return;
					}
					const day = list.dataset.day;
					if (!day) {
						return;
					}
					e.preventDefault();
					this._removeTimeSplitRow(row, list, day);
				});
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
						this._getLastRangeEndTime(list),
						day === 'friday' ? '16:00' : '18:00',
						day
					);
					this._handleTimeRangeChange(day);
					if (!this.options.deferSelectInit) {
						const lastRow = list.querySelector('.time-range-row:last-child');
						if (lastRow) {
							lastRow.querySelectorAll('.from-time, .to-time').forEach((select) => {
								this.initTimeSelect2(select);
							});
						}
					}
				});
			});

			const repeater = this.getTreatmentsRepeater();
			const addBtn = this.options.addTreatmentButtonSelector
				? this.root.querySelector(this.options.addTreatmentButtonSelector)
				: this.root.querySelector('.add-treatment-btn');

			if (addBtn) {
				const handler = this._getAddTreatmentClickHandler();
				addBtn.removeEventListener('click', handler);
				this._addTreatmentBtn = addBtn;
				addBtn.addEventListener('click', handler);
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

			if (!this.options.deferSelectInit) {
				this.initScheduleSelectFields(this.root);
			}
			this._initializeDayTimeRanges();
			this.validateTreatmentsComplete();
		}
	}

	window.ClinicQueueScheduleSettingsUI = ScheduleSettingsUI;
	window.ClinicQueueScheduleSettingsUI.DAYS_ORDER = DAYS_ORDER;

})(window);
