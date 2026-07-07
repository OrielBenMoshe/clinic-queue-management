/**
 * Edit Modal Module – user-schedules-table
 *
 * @package Clinic_Queue_Management
 */

(function ($) {
	'use strict';

	let _scheduleId       = 0;
	let _scheduleType     = 'google';
	let _portalTreatments = [];
	let _scheduleSettingsUI = null;
	let _loadRequest      = null;

	function getOverlay()        { return $('#schedule-table-edit-modal'); }
	function getLoader()         { return $('#schedule-table-edit-modal-loader'); }
	function getBody()           { return $('#schedule-table-edit-modal-body'); }
	function getFooter()         { return $('#schedule-table-edit-modal-footer'); }
	function getSaveBtn()        { return $('#schedule-table-edit-modal-save'); }
	function getCloseBtn()       { return $('.schedule-table__edit-modal-close'); }
	function getError()          { return $('#schedule-table-edit-modal-error'); }
	function getSuccess()        { return $('#schedule-table-edit-modal-success'); }
	function getFormWrapper()    { return getOverlay().find('.clinic-add-schedule-form'); }

	function getScheduleSettingsUI() {
		const formEl = getFormWrapper()[0];
		if (!formEl || !window.ClinicQueueScheduleSettingsUI) {
			return null;
		}

		if (!_scheduleSettingsUI) {
			_scheduleSettingsUI = new window.ClinicQueueScheduleSettingsUI(formEl, {
				context: 'edit_modal',
				maxSplitsPerDay: null,
				normalizeTimeRanges: false,
				scheduleType: _scheduleType,
				noDaysMessageSelector: '#edit-modal-no-days-msg',
				readonlyBadgeSelector: '#edit-modal-clinix-badge',
				addTreatmentButtonSelector: '#edit-modal-add-treatment',
				select2MinimumResultsForSearch: Infinity,
				getDropdownParent: () => getFormWrapper()[0],
			});
			_scheduleSettingsUI.bindEvents();
		}

		return _scheduleSettingsUI;
	}

	function openModal() {
		getOverlay().removeAttr('hidden');
		$('body').addClass('schedule-edit-modal-open');
		getCloseBtn().trigger('focus');
	}

	function _destroyScheduleSettingsUI() {
		if (_scheduleSettingsUI) {
			_scheduleSettingsUI.resetFormState('google');
			_scheduleSettingsUI = null;
		}
	}

	function _abortLoadRequest() {
		if (_loadRequest) {
			_loadRequest.abort();
			_loadRequest = null;
		}
	}

	function closeModal() {
		getOverlay().attr('hidden', '');
		$('body').removeClass('schedule-edit-modal-open');
		_resetModalState();
	}

	function _resetModalState() {
		_scheduleId   = 0;
		_scheduleType = 'google';

		_abortLoadRequest();

		getLoader().attr('hidden', '');
		getBody().attr('hidden', '');
		getFooter().attr('hidden', '');
		getError().attr('hidden', '').text('');
		getSuccess().attr('hidden', '').text('');

		_destroyScheduleSettingsUI();
	}

	function _initSharedFormUi() {
		const ui = getScheduleSettingsUI();
		if (!ui) {
			return;
		}
		if (typeof ui.initScheduleSelectFields === 'function') {
			ui.initScheduleSelectFields();
		}
		const formEl = getFormWrapper()[0];
		if (formEl && window.ClinicQueueFloatingLabels && typeof window.ClinicQueueFloatingLabels.init === 'function') {
			window.ClinicQueueFloatingLabels.init(formEl);
		}
	}

	function loadScheduleData(scheduleId) {
		const cfg = window.clinicQueueUserSchedulesTable || {};

		_abortLoadRequest();
		_destroyScheduleSettingsUI();

		openModal();
		getLoader().removeAttr('hidden');
		getBody().attr('hidden', '');
		getFooter().attr('hidden', '');
		getError().attr('hidden', '').text('');
		getSuccess().attr('hidden', '').text('');

		_loadRequest = $.post(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
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

			const ui = getScheduleSettingsUI();
			if (ui) {
				ui.setScheduleType(_scheduleType);
				ui.fillDays(data.days || {});
			}

			_loadPortalTreatments().then(function () {
				if (ui) {
					ui.setPortalTerms(_portalTreatments);
					ui.fillTreatments(data.treatments || []);
					ui.applyScheduleTypeRules(_scheduleType);
					try {
						_initSharedFormUi();
					} catch (err) {
						if (window.ClinicQueueUtils && typeof window.ClinicQueueUtils.error === 'function') {
							window.ClinicQueueUtils.error('Edit modal UI init failed:', err);
						}
					}
				}
				getLoader().attr('hidden', '');
				getBody().removeAttr('hidden');
				getFooter().removeAttr('hidden');
			});
		})
		.fail(function (_xhr, status) {
			if (status === 'abort') {
				return;
			}
			_showLoadError('שגיאת תקשורת. אנא נסה שוב.');
		})
		.always(function () {
			_loadRequest = null;
		});
	}

	function _showLoadError(msg) {
		getLoader().attr('hidden', '');
		getBody().removeAttr('hidden');
		getError().text(msg).removeAttr('hidden');
	}

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
				if (!r.ok) {
					throw new Error('HTTP ' + r.status);
				}
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

	function _collectDays() {
		const ui = getScheduleSettingsUI();
		return ui ? ui.collectDays() : {};
	}

	function _collectTreatments() {
		const ui = getScheduleSettingsUI();
		return ui ? ui.collectTreatments() : [];
	}

	function _validate() {
		const ui = getScheduleSettingsUI();
		return ui ? ui.validate('edit_modal', _scheduleType) : 'לא ניתן לטעון את טופס העריכה.';
	}

	async function _save() {
		const $saveBtn = getSaveBtn();
		const cfg      = window.clinicQueueUserSchedulesTable || {};

		getError().attr('hidden', '').text('');
		getSuccess().attr('hidden', '').text('');
		$saveBtn.prop('disabled', true).text('שומר...');

		try {
			const days       = _collectDays();
			const treatments = _collectTreatments();
			const validationErr = _validate();

			if (validationErr) {
				getError().text(validationErr).removeAttr('hidden');
				return;
			}

			const wpResult = await _updateWordPress(days, treatments, cfg);
			if (!wpResult.success) {
				getError().text(wpResult.message || 'שגיאה בשמירת הנתונים.').removeAttr('hidden');
				return;
			}

			if (wpResult.proxy_needed) {
				try {
					await _updateProxy(days, _scheduleId, cfg);
				} catch (proxyErr) {
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
						success:      true,
						proxy_needed: !!(res.data && res.data.proxy_needed),
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

	function _updateTableRow() {
		const days = _collectDays();
		const dayMap = {
			sunday: 'א׳', monday: 'ב׳', tuesday: 'ג׳', wednesday: 'ד׳',
			thursday: 'ה׳', friday: 'ו׳', saturday: 'ש׳',
		};
		const order = window.ClinicQueueScheduleSettingsUI
			? window.ClinicQueueScheduleSettingsUI.DAYS_ORDER
			: ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
		const labels = order
			.filter(function (d) { return days[d]; })
			.map(function (d) { return dayMap[d] || d; });

		const $row = $('.user-schedules-table-root .schedule-table__row[data-id="' + _scheduleId + '"]');
		if ($row.length) {
			$row.find('.schedule-table__days').text(labels.length > 0 ? labels.join(', ') : '—');
		}
	}

	function absInt(val) {
		const n = parseInt(val, 10);
		return isNaN(n) || n < 0 ? 0 : n;
	}

	function initEventListeners() {
		$(document).on('click.scheduleTableEdit', '.user-schedules-table-root .schedule-table__edit-button', function () {
			const $row = $(this).closest('tr.schedule-table__row');
			_scheduleId      = absInt($row.data('id'));
			_scheduleType    = $row.data('schedule-type') || 'google';

			$('.user-schedules-table-root .schedule-table__menu').attr('hidden', '');
			$('.user-schedules-table-root .schedule-table__menu-button').attr('aria-expanded', 'false');

			loadScheduleData(_scheduleId);
		});

		$(document).on('click.scheduleTableEditClose', '.schedule-table__edit-modal-close, #schedule-table-edit-modal-cancel', closeModal);

		$(document).on('click.scheduleTableEditOverlay', '#schedule-table-edit-modal', function (e) {
			if ($(e.target).is('#schedule-table-edit-modal')) {
				closeModal();
			}
		});

		$(document).on('keydown.scheduleTableEditEsc', function (e) {
			if (e.key === 'Escape' && !getOverlay().is('[hidden]')) {
				closeModal();
			}
		});

		$(document).on('click.scheduleTableEditSave', '#schedule-table-edit-modal-save', _save);
	}

	$(function () {
		initEventListeners();
	});

})(window.jQuery);
