/**
 * טבלת מרפאות רופא — מיון + תפריט פעולות (kebab).
 *
 * @package Clinic_Queue_Management
 */
(function ($) {
	'use strict';

	/* ── דיבוג ── */
	function logDebug() {
		const cfg = window.clinicQueueUserDoctorClinicsTable || {};
		if (!cfg.debugEnabled || !cfg.debugPayload) {
			return;
		}
		console.groupCollapsed('[user_doctor_clinics_table] debug');
		console.log('payload', cfg.debugPayload);
		console.groupEnd();
	}

	/* ── מיון טבלה ── */
	$(document).on(
		'click.clinicsTableSort',
		'.user-clinics-table-root .clinics-table__th--sortable',
		function () {
			const $th     = $(this);
			const $table  = $th.closest('.clinics-table__table');
			const $tbody  = $table.find('.clinics-table__body');
			const sortKey = $th.data('sort');
			const isAsc   = $th.hasClass('clinics-table__th--sort-asc');

			$table.find('.clinics-table__th--sortable')
				.removeClass('clinics-table__th--sort-asc clinics-table__th--sort-desc')
				.attr('aria-sort', 'none');

			$th.addClass(isAsc ? 'clinics-table__th--sort-desc' : 'clinics-table__th--sort-asc')
			   .attr('aria-sort', isAsc ? 'descending' : 'ascending');

			const $rows = $tbody
				.find('tr.clinics-table__row:not(.clinics-table__row--empty)')
				.toArray();

			$rows.sort(function (a, b) {
				let aVal, bVal;

				if (sortKey === 'name') {
					aVal = $(a).find('.clinics-table__name').data('sort-name') || '';
					bVal = $(b).find('.clinics-table__name').data('sort-name') || '';
				} else {
					aVal = $(a).find('.clinics-table__status').data('sort-status') || '';
					bVal = $(b).find('.clinics-table__status').data('sort-status') || '';
					const order = { 'התקבל יומן לקישור': 0, 'פעיל': 1, 'לא הוגדר יומן': 2 };
					aVal = order[aVal] !== undefined ? order[aVal] : 99;
					bVal = order[bVal] !== undefined ? order[bVal] : 99;
				}

				const cmp = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
				return $th.hasClass('clinics-table__th--sort-asc') ? cmp : -cmp;
			});

			$.each($rows, function (_, row) { $tbody.append(row); });
		}
	);

	/* ── תפריט שלוש נקודות (kebab) ── */

	function closeAllMenus() {
		$('.clinics-table__actions-menu').attr('hidden', '');
		$('.clinics-table__actions-trigger').attr('aria-expanded', 'false');
	}

	/* פתיחה/סגירה בלחיצה על הכפתור */
	$(document).on(
		'click.clinicsTableKebab',
		'.clinics-table__actions-trigger',
		function (e) {
			e.stopPropagation();

			const $trigger = $(this);
			const $menu    = $trigger.siblings('.clinics-table__actions-menu');
			const isOpen   = $trigger.attr('aria-expanded') === 'true';

			closeAllMenus();

			if (!isOpen) {
				$menu.removeAttr('hidden');
				$trigger.attr('aria-expanded', 'true');
			}
		}
	);

	/* סגירה בלחיצה מחוץ לתפריט */
	$(document).on('click.clinicsTableKebabOutside', function () {
		closeAllMenus();
	});

	/* מניעת סגירה בלחיצה בתוך התפריט */
	$(document).on(
		'click.clinicsTableKebabInside',
		'.clinics-table__actions-menu',
		function (e) {
			e.stopPropagation();
		}
	);

	/* סגירה במקש Escape */
	$(document).on('keydown.clinicsTableKebabEsc', function (e) {
		if (e.key === 'Escape') {
			closeAllMenus();
		}
	});

	/* ── מודל אישור התנתקות ── */

	const $detachOverlay = $('#clinics-table-detach-modal');
	const $detachConfirm = $('#clinics-table-detach-modal-confirm');
	const $detachCancel  = $('#clinics-table-detach-modal-cancel');
	const $detachError   = $('#clinics-table-detach-modal-error');
	let   detachData     = {};

	function openDetachModal(clinicId, scheduleId, $row) {
		detachData = { clinicId, scheduleId, $row };
		$detachError.attr('hidden', '').text('');
		$detachConfirm.prop('disabled', false);
		$detachOverlay.removeAttr('hidden');
		$detachConfirm.trigger('focus');
	}

	function closeDetachModal() {
		$detachOverlay.attr('hidden', '');
		detachData = {};
	}

	/* סגירה בלחיצה על הרקע */
	$(document).on('click.clinicsTableDetachOverlay', '#clinics-table-detach-modal', function (e) {
		if ($(e.target).is('#clinics-table-detach-modal')) {
			closeDetachModal();
		}
	});

	/* סגירה בכפתור ביטול */
	$(document).on('click.clinicsTableDetachCancel', '#clinics-table-detach-modal-cancel', function () {
		closeDetachModal();
	});

	/* סגירה במקש Escape */
	$(document).on('keydown.clinicsTableDetachEsc', function (e) {
		if (e.key === 'Escape' && !$detachOverlay.is('[hidden]')) {
			closeDetachModal();
		}
	});

	/* אישור — שליחת AJAX */
	$(document).on('click.clinicsTableDetachConfirm', '#clinics-table-detach-modal-confirm', function () {
		const cfg      = window.clinicQueueUserDoctorClinicsTable || {};
		const $root    = $('.user-clinics-table-root');
		const doctorId = $root.data('doctor-id') || 0;
		const nonce    = cfg.nonce || '';
		const ajaxUrl  = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';

		$detachConfirm.prop('disabled', true);
		$detachError.attr('hidden', '').text('');

		$.post(
			ajaxUrl,
			{
				action:      'clinic_queue_detach_doctor_from_clinic',
				nonce:       nonce,
				clinic_id:   detachData.clinicId,
				schedule_id: detachData.scheduleId,
				doctor_id:   doctorId,
			},
			function (response) {
				if (response && response.success) {
					if (response.data && response.data.proxy_response) {
						console.log('[detach] proxy response:', response.data.proxy_response);
					}
					const $removedRow = detachData.$row;
					closeDetachModal();
					if ($removedRow && $removedRow.length) {
						$removedRow.remove();
					} else {
						window.location.reload();
					}
				} else {
					const msg = (response && response.data && response.data.message)
						? response.data.message
						: 'אירעה שגיאה. אנא נסה שוב.';
					$detachError.text(msg).removeAttr('hidden');
					$detachConfirm.prop('disabled', false);
				}
			}
		).fail(function () {
			$detachError.text('שגיאת תקשורת. אנא נסה שוב.').removeAttr('hidden');
			$detachConfirm.prop('disabled', false);
		});
	});

	/* ── מודל אישור הפעלת יומן ── */

	const $activateOverlay = $('#clinics-table-activate-modal');
	const $activateConfirm = $('#clinics-table-activate-modal-confirm');
	const $activateCancel  = $('#clinics-table-activate-modal-cancel');
	const $activateError   = $('#clinics-table-activate-modal-error');
	let   activateData     = {};

	function openActivateModal(clinicId, scheduleId, $row) {
		activateData = { clinicId, scheduleId, $row };
		$activateError.attr('hidden', '').text('');
		$activateConfirm.prop('disabled', false);
		$activateOverlay.removeAttr('hidden');
		$activateConfirm.trigger('focus');
	}

	function closeActivateModal() {
		$activateOverlay.attr('hidden', '');
		activateData = {};
	}

	/* סגירה בלחיצה על הרקע */
	$(document).on('click.clinicsTableActivateOverlay', '#clinics-table-activate-modal', function (e) {
		if ($(e.target).is('#clinics-table-activate-modal')) {
			closeActivateModal();
		}
	});

	/* סגירה בכפתור ביטול */
	$(document).on('click.clinicsTableActivateCancel', '#clinics-table-activate-modal-cancel', function () {
		closeActivateModal();
	});

	/* סגירה במקש Escape */
	$(document).on('keydown.clinicsTableActivateEsc', function (e) {
		if (e.key === 'Escape' && !$activateOverlay.is('[hidden]')) {
			closeActivateModal();
		}
	});

	/* אישור הפעלה — שליחת AJAX */
	$(document).on('click.clinicsTableActivateConfirm', '#clinics-table-activate-modal-confirm', function () {
		const cfg     = window.clinicQueueUserDoctorClinicsTable || {};
		const nonce   = cfg.unfreezeNonce || '';
		const ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';

		$activateConfirm.prop('disabled', true);
		$activateError.attr('hidden', '').text('');

		$.post(
			ajaxUrl,
			{
				action:      'clinic_queue_activate_schedule',
				nonce:       nonce,
				clinic_id:   activateData.clinicId,
				schedule_id: activateData.scheduleId,
			},
			function (response) {
				if (response && response.success) {
					if (response.data && response.data.proxy_response) {
						console.log('[unfreeze] proxy response:', response.data.proxy_response);
					}
					closeActivateModal();
					window.location.reload();
				} else {
					const msg = (response && response.data && response.data.message)
						? response.data.message
						: 'אירעה שגיאה. אנא נסה שוב.';
					$activateError.text(msg).removeAttr('hidden');
					$activateConfirm.prop('disabled', false);
				}
			}
		).fail(function () {
			$activateError.text('שגיאת תקשורת. אנא נסה שוב.').removeAttr('hidden');
			$activateConfirm.prop('disabled', false);
		});
	});

	/* ── מודל אישור הקפאת יומן ── */

	const $freezeOverlay = $('#clinics-table-freeze-modal');
	const $freezeConfirm = $('#clinics-table-freeze-modal-confirm');
	const $freezeCancel  = $('#clinics-table-freeze-modal-cancel');
	const $freezeError   = $('#clinics-table-freeze-modal-error');
	let   freezeData     = {};

	function openFreezeModal(clinicId, scheduleId, $row) {
		freezeData = { clinicId, scheduleId, $row };
		$freezeError.attr('hidden', '').text('');
		$freezeConfirm.prop('disabled', false);
		$freezeOverlay.removeAttr('hidden');
		$freezeConfirm.trigger('focus');
	}

	function closeFreezeModal() {
		$freezeOverlay.attr('hidden', '');
		freezeData = {};
	}

	/* סגירה בלחיצה על הרקע */
	$(document).on('click.clinicsTableFreezeOverlay', '#clinics-table-freeze-modal', function (e) {
		if ($(e.target).is('#clinics-table-freeze-modal')) {
			closeFreezeModal();
		}
	});

	/* סגירה בכפתור ביטול */
	$(document).on('click.clinicsTableFreezeCancel', '#clinics-table-freeze-modal-cancel', function () {
		closeFreezeModal();
	});

	/* סגירה במקש Escape */
	$(document).on('keydown.clinicsTableFreezeEsc', function (e) {
		if (e.key === 'Escape' && !$freezeOverlay.is('[hidden]')) {
			closeFreezeModal();
		}
	});

	/* אישור הקפאה — שליחת AJAX */
	$(document).on('click.clinicsTableFreezeConfirm', '#clinics-table-freeze-modal-confirm', function () {
		const cfg     = window.clinicQueueUserDoctorClinicsTable || {};
		const nonce   = cfg.freezeNonce || '';
		const ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';

		$freezeConfirm.prop('disabled', true);
		$freezeError.attr('hidden', '').text('');

		$.post(
			ajaxUrl,
			{
				action:      'clinic_queue_freeze_schedule',
				nonce:       nonce,
				clinic_id:   freezeData.clinicId,
				schedule_id: freezeData.scheduleId,
			},
			function (response) {
				if (response && response.success) {
					if (response.data && response.data.proxy_response) {
						console.log('[freeze] proxy response:', response.data.proxy_response);
					}
					closeFreezeModal();
					window.location.reload();
					if (freezeData.$row && freezeData.$row.length) {
						const $badge = freezeData.$row.find('.clinics-table__status-badge');
						$badge
							.removeClass('clinics-table__status-badge--active')
							.addClass('clinics-table__status-badge--none')
							.text('לא פעיל');
						freezeData.$row.find('.clinics-table__status').attr('data-sort-status', 'לא פעיל');

						const $actions = freezeData.$row.find('.clinics-table__actions');
						$actions.attr('data-badge-mod', 'none');

						const $freezeBtn = freezeData.$row.find('[data-action="freeze"]');
						if ($freezeBtn.length) {
							$freezeBtn.attr('data-action', 'unfreeze').text('הפעלת יומן');
						}
					}
				} else {
					const msg = (response && response.data && response.data.message)
						? response.data.message
						: 'אירעה שגיאה. אנא נסה שוב.';
					$freezeError.text(msg).removeAttr('hidden');
					$freezeConfirm.prop('disabled', false);
				}
			}
		).fail(function () {
			$freezeError.text('שגיאת תקשורת. אנא נסה שוב.').removeAttr('hidden');
			$freezeConfirm.prop('disabled', false);
		});
	});

	/* ── לחיצה על פריט בתפריט ── */
	$(document).on(
		'click.clinicsTableKebabItem',
		'.clinics-table__actions-item',
		function () {
			const $item      = $(this);
			const action     = $item.data('action');
			const $actions   = $item.closest('.clinics-table__actions');
			const clinicId   = $actions.data('clinic-id');
			const scheduleId = $actions.data('schedule-id');
			const $row       = $item.closest('tr.clinics-table__row');

			closeAllMenus();

			if (action === 'detach') {
				openDetachModal(clinicId, scheduleId, $row);
			} else if (action === 'freeze') {
				openFreezeModal(clinicId, scheduleId, $row);
			} else if (action === 'unfreeze') {
				openActivateModal(clinicId, scheduleId, $row);
			}
		}
	);

	$(function () {
		logDebug();
	});

})(window.jQuery);
