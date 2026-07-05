/**
 * Edit Modal Module – user-schedules-table
 *
 * אחראי על:
 * - פתיחת מודל עריכת יומן
 * - טעינת נתוני יומן קיים (AJAX)
 * - מילוי ימים/שעות וטיפולים
 * - ניהול UI: day rows, time ranges, treatments repeater, Select2
 * - שמירה: עדכון WP (AJAX) + עדכון פרוקסי (REST – Google בלבד)
 *
 * תלויות: jQuery, window.clinicQueueUserSchedulesTable (מ-wp_localize_script)
 *
 * @package Clinic_Queue_Management
 */

(function ($) {
	'use strict';

	/* ─────────────────────────────────────────
	 * קבועים / selectors
	 * ───────────────────────────────────────── */

	const DAYS_ORDER = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

	/* ─────────────────────────────────────────
	 * מצב מודל (module-level state)
	 * ───────────────────────────────────────── */

	let _scheduleId      = 0;
	let _scheduleType    = 'google';
	let _proxyScheduleId = 0;
	let _isConnected     = false;
	let _portalTreatments = [];   // אפשרויות טיפול מהפורטל (taxonomy)
	let _treatmentRowIndex = 0;   // מונה שורות טיפול

	/* ─────────────────────────────────────────
	 * תמיכה בפלאגין Select2
	 * ───────────────────────────────────────── */

	function hasSelect2() {
		return typeof $.fn.select2 === 'function';
	}

	function initSelect2($el) {
		if (!hasSelect2() || !$el || !$el.length) {
			return;
		}
		destroySelect2($el);
		$el.select2({
			theme:              'clinic-queue',
			dir:                'rtl',
			language:           'he',
			width:              '100%',
			placeholder:        $el.find('option:first').text() || 'בחר סוג טיפול',
			allowClear:         false,
			dropdownParent:     getFormWrapper(),
			dropdownCssClass:   'portal-treatment-dropdown',
			minimumResultsForSearch: Infinity,
			escapeMarkup:       function (m) { return m; },
		});
	}

	function destroySelect2($el) {
		if (!hasSelect2() || !$el || !$el.length) {
			return;
		}
		if ($el.hasClass('select2-hidden-accessible')) {
			$el.select2('destroy');
		}
	}

	function getSelect2Val($el) {
		if (hasSelect2() && $el && $el.hasClass('select2-hidden-accessible')) {
			return $el.val() || '';
		}
		return ($el && $el.val()) || '';
	}

	function setSelect2Val($el, val) {
		if (!$el || !$el.length) {
			return;
		}
		$el.val(val);
		if (hasSelect2() && $el.hasClass('select2-hidden-accessible')) {
			$el.trigger('change');
		}
	}

	/* ─────────────────────────────────────────
	 * refs ל-DOM
	 * ───────────────────────────────────────── */

	function getOverlay()        { return $('#schedule-table-edit-modal'); }
	function getLoader()         { return $('#schedule-table-edit-modal-loader'); }
	function getBody()           { return $('#schedule-table-edit-modal-body'); }
	function getFooter()         { return $('#schedule-table-edit-modal-footer'); }
	function getSaveBtn()        { return $('#schedule-table-edit-modal-save'); }
	function getCancelBtn()      { return $('#schedule-table-edit-modal-cancel'); }
	function getCloseBtn()       { return $('.schedule-table__edit-modal-close'); }
	function getError()          { return $('#schedule-table-edit-modal-error'); }
	function getSuccess()        { return $('#schedule-table-edit-modal-success'); }
	function getClinicBadge()    { return $('#edit-modal-clinix-badge'); }
	function getTreatmentsWrap() { return $('#edit-modal-treatments'); }
	function getAddTreatmentBtn(){ return $('#edit-modal-add-treatment'); }
	/** אלמנט עטיפה הנושא את theme clinic-queue — dropdownParent של Select2 */
	function getFormWrapper()    { return getOverlay().find('.clinic-add-schedule-form'); }

	/* ─────────────────────────────────────────
	 * פתיחה / סגירה
	 * ───────────────────────────────────────── */

	function openModal() {
		getOverlay().removeAttr('hidden');
		$('body').addClass('schedule-edit-modal-open');
		// focus trap – focus ל-close button
		getCloseBtn().trigger('focus');
	}

	function closeModal() {
		getOverlay().attr('hidden', '');
		$('body').removeClass('schedule-edit-modal-open');
		_resetModalState();
	}

	function _resetModalState() {
		_scheduleId      = 0;
		_scheduleType    = 'google';
		_proxyScheduleId = 0;
		_isConnected     = false;
		_treatmentRowIndex = 0;

		getLoader().attr('hidden', '');
		getBody().attr('hidden', '');
		getFooter().attr('hidden', '');
		getError().attr('hidden', '').text('');
		getSuccess().attr('hidden', '').text('');

		// ניקוי ימים
		_resetDays();
		// ניקוי טיפולים
		_resetTreatments();
	}

	/* ─────────────────────────────────────────
	 * טעינת נתוני יומן
	 * ───────────────────────────────────────── */

	function loadScheduleData(scheduleId) {
		const cfg = window.clinicQueueUserSchedulesTable || {};
		openModal();
		getLoader().removeAttr('hidden');

		$.post(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
			action:      'clinic_queue_get_schedule_data',
			nonce:       cfg.getDataNonce || '',
			schedule_id: scheduleId,
		})
		.done(function (res) {
			if (!res || !res.success) {
				const msg = res && res.data && res.data.message ? res.data.message : 'שגיאה בטעינת נתוני היומן.';
				_showLoadError(msg);
				return;
			}
			const data = res.data;
			_scheduleId      = data.schedule_id      || scheduleId;
			_scheduleType    = data.schedule_type    || 'google';
			_proxyScheduleId = data.proxy_schedule_id || 0;
			_isConnected     = !!data.is_proxy_connected;

			_fillDays(data.days || {});
			_loadPortalTreatments().then(function () {
				_fillTreatments(data.treatments || []);
				_applyScheduleTypeRules();
				getLoader().attr('hidden', '');
				getBody().removeAttr('hidden');
				getFooter().removeAttr('hidden');
			});
		})
		.fail(function () {
			_showLoadError('שגיאת תקשורת. אנא נסה שוב.');
		});
	}

	function _showLoadError(msg) {
		getLoader().attr('hidden', '');
		getBody().removeAttr('hidden');
		getError().text(msg).removeAttr('hidden');
	}

	/* ─────────────────────────────────────────
	 * טעינת אפשרויות טיפולים מהפורטל
	 * ───────────────────────────────────────── */

	function _loadPortalTreatments() {
		if (_portalTreatments.length > 0) {
			return Promise.resolve(_portalTreatments);
		}

		const cfg      = window.clinicQueueUserSchedulesTable || {};
		const endpoint = (cfg.treatmentTypesEndpoint || '').replace(/\/$/, '');
		const nonce    = cfg.restNonce || '';

		if (!endpoint) {
			return Promise.resolve([]);
		}

		return fetch(endpoint, { headers: { 'X-WP-Nonce': nonce } })
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function (items) {
				_portalTreatments = Array.isArray(items) ? items : [];
				return _portalTreatments;
			})
			.catch(function () {
				_portalTreatments = [];
				return [];
			});
	}

	/* ─────────────────────────────────────────
	 * ניהול ימים / שעות
	 * ───────────────────────────────────────── */

	function _resetDays() {
		DAYS_ORDER.forEach(function (day) {
			const $row = _getDayRow(day);
			if (!$row.length) return;

			const $cb = $row.find('input[type="checkbox"][data-day="' + day + '"]');
			$cb.prop('checked', false).prop('disabled', false);

			const $timeRange = $row.find('.day-time-range[data-day="' + day + '"]');
			$timeRange.hide();

			// השאר רק את שורת הזמן הראשונה, הסר נוספות
			const $list = $timeRange.find('.time-ranges-list');
			$list.find('.time-range-row').slice(1).remove();
			// אפס שעות ברירת מחדל בשורה הראשונה
			const $firstRow = $list.find('.time-range-row').first();
			$firstRow.find('.from-time').val('08:00');
			$firstRow.find('.to-time').val(day === 'friday' ? '16:00' : '18:00');
			$firstRow.find('.remove-time-split-btn').hide();
		});
	}

	function _getDayRow(day) {
		return getOverlay().find('.edit-modal__day-row[data-day-row="' + day + '"]');
	}

	function _fillDays(daysData) {
		_resetDays();
		const hasDays = Object.keys(daysData).length > 0;
		$('#edit-modal-no-days-msg').attr('hidden', hasDays ? '' : null).toggle(!hasDays);

		Object.keys(daysData).forEach(function (day) {
			const ranges = daysData[day];
			if (!Array.isArray(ranges) || ranges.length === 0) return;

			const $row = _getDayRow(day);
			if (!$row.length) return;

			const $cb = $row.find('input[type="checkbox"][data-day="' + day + '"]');
			$cb.prop('checked', true);

			const $timeRange = $row.find('.day-time-range[data-day="' + day + '"]');
			$timeRange.show();

			const $list = $timeRange.find('.time-ranges-list');
			// מלא שורת ראשונה
			const $firstRow = $list.find('.time-range-row').first();
			if (ranges[0]) {
				$firstRow.find('.from-time').val(ranges[0].start_time || '08:00');
				$firstRow.find('.to-time').val(ranges[0].end_time || '18:00');
			}
			// הוסף שורות נוספות אם יש פיצולים
			for (let i = 1; i < ranges.length; i++) {
				_addTimeSplitRow($list, ranges[i].start_time || '08:00', ranges[i].end_time || '18:00', day);
			}
			// הצג כפתור הסרה אם יש יותר מ-1
			if (ranges.length > 1) {
				$list.find('.remove-time-split-btn').show();
			}
		});
	}

	function _addTimeSplitRow($list, fromTime, toTime, day) {
		const $first = $list.find('.time-range-row').first();
		const $clone = $first.clone();
		$clone.find('.from-time').val(fromTime || '08:00');
		$clone.find('.to-time').val(toTime || '18:00');
		$clone.find('.remove-time-split-btn').show();
		$list.append($clone);
	}

	/* ─────────────────────────────────────────
	 * ניהול טיפולים
	 * ───────────────────────────────────────── */

	function _resetTreatments() {
		const $wrap = getTreatmentsWrap();
		// מחק את כל השורות חוץ מהברירת מחדל הראשונה
		$wrap.find('.treatment-row:not(.treatment-row-default)').each(function () {
			destroySelect2($(this).find('.portal-treatment-select'));
			$(this).remove();
		});
		// אפס את שורת ברירת המחדל
		const $defaultRow = $wrap.find('.treatment-row-default');
		_resetTreatmentRow($defaultRow);
		_treatmentRowIndex = 0;
	}

	function _resetTreatmentRow($row) {
		destroySelect2($row.find('.portal-treatment-select'));
		$row.find('.clinix-treatment-select').val('');
		$row.find('.portal-treatment-select').val('');
		$row.find('.treatment-cost-input').val('');
		$row.find('.treatment-duration-input').val('');
		$row.find('.remove-treatment-btn').attr('hidden', '');
	}

	/**
	 * מקבץ את רשימת הטיפולים לפי specialty, בדיוק כמו _groupTermsBySpecialty בטופס ההקמה.
	 * טיפולים ללא specialty מקובצים בסוף תחת "כללי".
	 *
	 * @param {Array} terms
	 * @returns {Array<{specialtyId: number|null, label: string, treatments: Array}>}
	 */
	function _groupBySpecialty(terms) {
		const map = new Map();
		terms.forEach(function (t) {
			const key   = t.specialty ? t.specialty.id   : null;
			const label = t.specialty ? t.specialty.name : 'כללי';
			if (!map.has(key)) {
				map.set(key, { specialtyId: key, label: label, treatments: [] });
			}
			map.get(key).treatments.push(t);
		});
		return Array.from(map.values()).sort(function (a, b) {
			if (a.specialtyId === null) return 1;
			if (b.specialtyId === null) return -1;
			return a.label.localeCompare(b.label, 'he');
		});
	}

	/**
	 * בונה HTML לאפשרויות של portal-treatment-select.
	 * מקבץ תחת <optgroup> לפי specialty (כמו schedule-form).
	 *
	 * @param {number|string} selectedId
	 * @returns {string}
	 */
	function _buildPortalOptionsHtml(selectedId) {
		if (!_portalTreatments.length) {
			return '<option value="">אין טיפולים זמינים</option>';
		}

		let html = '<option value="">בחר סוג טיפול</option>';
		const groups = _groupBySpecialty(_portalTreatments);

		groups.forEach(function (group) {
			html += '<optgroup label="תחום: ' + _escapeHtml(group.label) + '">';
			group.treatments.forEach(function (t) {
				const id       = t.id   || t.term_id || '';
				const name     = t.name || t.label   || '';
				const suffix   = t.specialty ? ' (' + t.specialty.name + ')' : '';
				const selected = String(id) === String(selectedId) ? ' selected' : '';
				html += '<option value="' + id + '"' + selected + '>' + _escapeHtml(name + suffix) + '</option>';
			});
			html += '</optgroup>';
		});
		return html;
	}

	function _escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function _fillTreatments(treatments) {
		_resetTreatments();
		const $wrap       = getTreatmentsWrap();
		const $defaultRow = $wrap.find('.treatment-row-default');

		if (!Array.isArray(treatments) || treatments.length === 0) {
			// אין טיפולים שמורים — אפס options ואתחל Select2 על שורת ברירת-מחדל
			$defaultRow.find('.portal-treatment-select').html(_buildPortalOptionsHtml(0));
			initSelect2($defaultRow.find('.portal-treatment-select'));
			return;
		}

		treatments.forEach(function (t, i) {
			let $row;
			if (i === 0) {
				$row = $defaultRow;
			} else {
				$row = _cloneTreatmentRow();
				$wrap.append($row);
			}
			_fillTreatmentRow($row, t, i);
		});

		// כפתורי הסרה — הצג רק אם יש יותר מ-1
		_refreshRemoveButtons();
	}

	function _fillTreatmentRow($row, treatment, index) {
		$row.attr('data-row-index', index);

		// Clinix (שם בלבד, disabled)
		const $clinixSel = $row.find('.clinix-treatment-select');
		$clinixSel.empty();
		if (treatment.clinix_treatment_id || treatment.clinix_treatment_name) {
			$clinixSel.append(
				$('<option>').val(treatment.clinix_treatment_id || '').text(treatment.clinix_treatment_name || treatment.clinix_treatment_id || '')
			);
		}

		// Portal — בנה options ובחר
		const $portalSel = $row.find('.portal-treatment-select');
		destroySelect2($portalSel);
		$portalSel.html(_buildPortalOptionsHtml(treatment.treatment_type || 0));
		initSelect2($portalSel);

		$row.find('.treatment-cost-input').val(treatment.cost || '');
		$row.find('.treatment-duration-input').val(treatment.duration || '');
	}

	function _cloneTreatmentRow() {
		_treatmentRowIndex++;
		const $wrap  = getTreatmentsWrap();
		const $first = $wrap.find('.treatment-row-default');
		const $clone = $first.clone(false); // false = לא להעתיק event listeners
		$clone.removeClass('treatment-row-default');
		$clone.removeAttr('data-is-default');
		$clone.attr('data-row-index', _treatmentRowIndex);

		// נקה שרידי Select2 מכל ה-<select> ב-clone (כמו schedule-form-ui.js addTreatmentRow)
		$clone.find('select')
			.removeClass('select2-hidden-accessible')
			.removeAttr('data-select2-id')
			.removeAttr('aria-hidden')
			.removeAttr('tabindex');

		// אפס תוכן selects
		$clone.find('.portal-treatment-select').html(_buildPortalOptionsHtml(0));
		$clone.find('.clinix-treatment-select').empty().append('<option value=""></option>');

		$clone.find('.treatment-cost-input').val('');
		$clone.find('.treatment-duration-input').val('');
		$clone.find('.remove-treatment-btn').removeAttr('hidden');

		// הסר את ה-select2 container שנוסף ל-DOM ע"י Select2 (אם קיים ב-clone)
		$clone.find('.select2-container').remove();

		return $clone;
	}

	function _addNewTreatmentRow() {
		const $row = _cloneTreatmentRow();
		getTreatmentsWrap().append($row);
		initSelect2($row.find('.portal-treatment-select'));
		_refreshRemoveButtons();
	}

	function _removeRow($row) {
		// שורת ברירת-מחדל אינה ניתנת למחיקה
		if ($row.hasClass('treatment-row-default')) {
			return;
		}
		destroySelect2($row.find('.portal-treatment-select'));
		$row.remove();
		_refreshRemoveButtons();
	}

	function _refreshRemoveButtons() {
		const $rows   = getTreatmentsWrap().find('.treatment-row');
		const multiple = $rows.length > 1;
		$rows.each(function () {
			const isDefault = $(this).hasClass('treatment-row-default');
			// כפתור מחיקה: מוצג רק על שורות לא-ברירת-מחדל כשיש יותר משורה אחת
			$(this).find('.remove-treatment-btn').attr('hidden', (isDefault || !multiple) ? '' : null);
		});
	}

	/* ─────────────────────────────────────────
	 * כללים לפי סוג יומן
	 * ───────────────────────────────────────── */

	function _applyScheduleTypeRules() {
		const isClinix = _scheduleType === 'clinix';

		// Clinix badge + readonly ימים
		getClinicBadge().attr('hidden', isClinix ? null : '');
		getOverlay().find('.edit-modal__day-row .day-checkbox input[type="checkbox"]')
			.prop('disabled', isClinix);
		getOverlay().find('.day-time-range select, .day-time-range button').prop('disabled', isClinix);

		// Flow class on treatments-repeater — CSS (schedule-form.css) מטפל בנראות clinix-only/google-only
		getTreatmentsWrap()
			.toggleClass('is-clinix-flow', isClinix)
			.toggleClass('is-google-flow', !isClinix);

		// עבור clinix: מחיר ומשך הם read-only (נשמרים מהמערכת, לא ניתנים לעריכה ידנית)
		getTreatmentsWrap()
			.find('.treatment-cost-input, .treatment-duration-input')
			.prop('disabled', isClinix);

		// כפתור "הוספת טיפול" — מוסתר עבור clinix (טיפולים נקבעים ע"י המערכת)
		getAddTreatmentBtn().attr('hidden', isClinix ? '' : null);
	}

	/* ─────────────────────────────────────────
	 * איסוף נתוני הטופס
	 * ───────────────────────────────────────── */

	function _collectDays() {
		const days = {};
		DAYS_ORDER.forEach(function (day) {
			const $row = _getDayRow(day);
			if (!$row.length) return;
			const $cb = $row.find('input[type="checkbox"][data-day="' + day + '"]');
			if (!$cb.prop('checked')) return;

			const ranges = [];
			$row.find('.time-range-row').each(function () {
				const from = $(this).find('.from-time').val();
				const to   = $(this).find('.to-time').val();
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

	function _collectTreatments() {
		const treatments = [];
		getTreatmentsWrap().find('.treatment-row').each(function () {
			const $r = $(this);
			const clinixId   = $r.find('.clinix-treatment-select').val() || '';
			const clinixName = $r.find('.clinix-treatment-select option:selected').text() || '';
			const portalType = getSelect2Val($r.find('.portal-treatment-select'));
			const cost       = parseInt($r.find('.treatment-cost-input').val(), 10) || 0;
			const duration   = parseInt($r.find('.treatment-duration-input').val(), 10) || 0;
			treatments.push({
				clinix_treatment_id:   clinixId,
				clinix_treatment_name: clinixName,
				treatment_type:        portalType ? parseInt(portalType, 10) : 0,
				cost:                  cost,
				duration:              duration,
			});
		});
		return treatments;
	}

	/* ─────────────────────────────────────────
	 * ולידציה
	 * ───────────────────────────────────────── */

	function _validate(days, treatments) {
		if (_scheduleType === 'google' && Object.keys(days).length === 0) {
			return 'אנא בחר לפחות יום עבודה אחד.';
		}
		if (treatments.length === 0) {
			return 'אנא הגדר לפחות טיפול אחד.';
		}
		return null;
	}

	/* ─────────────────────────────────────────
	 * שמירה
	 * ───────────────────────────────────────── */

	async function _save() {
		const $saveBtn = getSaveBtn();
		const cfg      = window.clinicQueueUserSchedulesTable || {};

		getError().attr('hidden', '').text('');
		getSuccess().attr('hidden', '').text('');
		$saveBtn.prop('disabled', true).text('שומר...');

		try {
			const days       = _collectDays();
			const treatments = _collectTreatments();

			const validationErr = _validate(days, treatments);
			if (validationErr) {
				getError().text(validationErr).removeAttr('hidden');
				return;
			}

			// שלב 1: עדכון WordPress
			const wpResult = await _updateWordPress(days, treatments, cfg);
			if (!wpResult.success) {
				getError().text(wpResult.message || 'שגיאה בשמירת הנתונים.').removeAttr('hidden');
				return;
			}

			// שלב 2: עדכון פרוקסי (Google + מחובר בלבד)
			if (wpResult.proxy_needed) {
				try {
					await _updateProxy(days, _scheduleId, cfg);
				} catch (proxyErr) {
					// מצליח לשמור ב-WP אבל פרוקסי נכשל – הצג אזהרה
					getSuccess().text('הגדרות היומן נשמרו. שים לב: לא הצלחנו לעדכן את שעות הפעילות בפרוקסי (' + proxyErr + ')').removeAttr('hidden');
					_updateTableRow();
					return;
				}
			}

			getSuccess().text('הגדרות היומן עודכנו בהצלחה!').removeAttr('hidden');
			_updateTableRow();
			setTimeout(closeModal, 1500);

		} catch (err) {
			getError().text('שגיאה לא צפויה: ' + (err.message || err)).removeAttr('hidden');
		} finally {
			$saveBtn.prop('disabled', false).text('שמירה');
		}
	}

	function _updateWordPress(days, treatments, cfg) {
		return new Promise(function (resolve, reject) {
			$.post(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
				action:        'clinic_queue_update_schedule_settings',
				nonce:         cfg.updateSettingsNonce || '',
				schedule_id:   _scheduleId,
				schedule_data: JSON.stringify({ days: days, treatments: treatments }),
			})
			.done(function (res) {
				if (!res) {
					resolve({ success: false, message: 'תגובה לא תקינה מהשרת.' });
					return;
				}
				if (res.success) {
					resolve({
						success:          true,
						proxy_needed:     !!(res.data && res.data.proxy_needed),
						proxy_schedule_id: (res.data && res.data.proxy_schedule_id) || 0,
					});
				} else {
					resolve({ success: false, message: (res.data && res.data.message) || 'שגיאה בשמירת הנתונים.' });
				}
			})
			.fail(function () {
				reject(new Error('שגיאת תקשורת בעדכון WordPress.'));
			});
		});
	}

	function _updateProxy(days, wpScheduleId, cfg) {
		const restUrl = (cfg.restUrl || '').replace(/\/$/, '');
		const nonce   = cfg.restNonce || '';
		const url     = restUrl + '/scheduler/set-active-hours';

		return fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   nonce,
			},
			body: JSON.stringify({
				schedulerID: wpScheduleId,
				days:        days,
			}),
		}).then(function (r) {
			return r.json().then(function (data) {
				if (!r.ok || (data && !data.success)) {
					const msg = (data && data.message) || ('HTTP ' + r.status);
					throw new Error(msg);
				}
				return data;
			});
		});
	}

	/* ─────────────────────────────────────────
	 * עדכון שורת הטבלה לאחר שמירה
	 * ───────────────────────────────────────── */

	function _updateTableRow() {
		// עדכן working_days בטבלה (מה שנאסף)
		const days = _collectDays();
		const dayMap = {
			sunday: 'א׳', monday: 'ב׳', tuesday: 'ג׳', wednesday: 'ד׳',
			thursday: 'ה׳', friday: 'ו׳', saturday: 'ש׳',
		};
		const labels = DAYS_ORDER
			.filter(function (d) { return days[d]; })
			.map(function (d) { return dayMap[d] || d; });

		const $row = $('.user-schedules-table-root .schedule-table__row[data-id="' + _scheduleId + '"]');
		if ($row.length) {
			$row.find('.schedule-table__days').text(labels.length > 0 ? labels.join(', ') : '—');
		}
	}

	/* ─────────────────────────────────────────
	 * Event Listeners
	 * ───────────────────────────────────────── */

	function initEventListeners() {
		// פתיחה בלחיצה על "עריכת יומן"
		$(document).on('click.scheduleTableEdit', '.user-schedules-table-root .schedule-table__edit-button', function () {
			const $row = $(this).closest('tr.schedule-table__row');
			_scheduleId      = absInt($row.data('id'));
			_scheduleType    = $row.data('schedule-type') || 'google';
			_proxyScheduleId = absInt($row.data('proxy-id'));
			_isConnected     = $row.data('is-connected') === 'true' || $row.data('is-connected') === true;

			// סגור תפריט kebab
			$('.user-schedules-table-root .schedule-table__menu').attr('hidden', '');
			$('.user-schedules-table-root .schedule-table__menu-button').attr('aria-expanded', 'false');

			loadScheduleData(_scheduleId);
		});

		// סגירה
		$(document).on('click.scheduleTableEditClose', '.schedule-table__edit-modal-close, #schedule-table-edit-modal-cancel', closeModal);

		// סגירה ברקע
		$(document).on('click.scheduleTableEditOverlay', '#schedule-table-edit-modal', function (e) {
			if ($(e.target).is('#schedule-table-edit-modal')) {
				closeModal();
			}
		});

		// Escape
		$(document).on('keydown.scheduleTableEditEsc', function (e) {
			if (e.key === 'Escape' && !getOverlay().is('[hidden]')) {
				closeModal();
			}
		});

		// שמירה
		$(document).on('click.scheduleTableEditSave', '#schedule-table-edit-modal-save', _save);

		// הוספת טיפול
		$(document).on('click.scheduleTableEditAddTreatment', '#edit-modal-add-treatment', _addNewTreatmentRow);

		// הסרת טיפול
		$(document).on('click.scheduleTableEditRemoveTreatment', '.schedule-table__edit-modal .remove-treatment-btn', function () {
			const $row = $(this).closest('.treatment-row');
			_removeRow($row);
		});

		// הוספת פיצול שעה
		$(document).on('click.scheduleTableEditAddSplit', '.schedule-table__edit-modal .add-time-split-btn', function () {
			const day   = $(this).data('day');
			const $list = $(this).siblings('.time-ranges-list');
			_addTimeSplitRow($list, '08:00', day === 'friday' ? '16:00' : '18:00', day);
			$list.find('.remove-time-split-btn').show();
		});

		// הסרת פיצול שעה
		$(document).on('click.scheduleTableEditRemoveSplit', '.schedule-table__edit-modal .remove-time-split-btn', function () {
			const $timeRows = $(this).closest('.time-ranges-list').find('.time-range-row');
			if ($timeRows.length <= 1) return;
			$(this).closest('.time-range-row').remove();
			const $remaining = $(this).closest('.time-ranges-list').find('.time-range-row');
			if ($remaining.length <= 1) {
				$remaining.find('.remove-time-split-btn').hide();
			}
		});

		// toggle שעות בלחיצה על checkbox יום
		$(document).on('change.scheduleTableEditDayCheck', '.schedule-table__edit-modal .day-checkbox input[type="checkbox"]', function () {
			const day        = $(this).data('day');
			const $dayRow    = _getDayRow(day);
			const $timeRange = $dayRow.find('.day-time-range[data-day="' + day + '"]');
			if ($(this).prop('checked')) {
				$timeRange.show();
			} else {
				$timeRange.hide();
			}
		});
	}

	/* ─────────────────────────────────────────
	 * עזר
	 * ───────────────────────────────────────── */

	function absInt(val) {
		const n = parseInt(val, 10);
		return isNaN(n) || n < 0 ? 0 : n;
	}

	/* ─────────────────────────────────────────
	 * init
	 * ───────────────────────────────────────── */

	$(function () {
		initEventListeners();
	});

})(window.jQuery);
