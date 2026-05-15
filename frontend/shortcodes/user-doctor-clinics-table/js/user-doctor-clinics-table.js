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
			}
		}
	);

	$(function () {
		logDebug();
	});

})(window.jQuery);
