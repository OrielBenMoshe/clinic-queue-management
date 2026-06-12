/**
 * טבלת היומנים של המשתמש — מיון, תפריט פעולות (kebab) ומחיקת יומן.
 *
 * @package Clinic_Queue_Management
 */
(function ($) {
	'use strict';

	/* ── מיון טבלה ── */
	$(document).on(
		'click.schedulesTableSort',
		'.user-schedules-table-root .schedule-table__th--sortable',
		function () {
			const $th     = $(this);
			const $table  = $th.closest('.schedule-table__table');
			const $tbody  = $table.find('.schedule-table__body');
			const sortKey = $th.data('sort');
			const isAsc   = $th.hasClass('schedule-table__th--sort-asc');

			$table.find('.schedule-table__th--sortable')
				.removeClass('schedule-table__th--sort-asc schedule-table__th--sort-desc')
				.attr('aria-sort', 'none');

			$th.addClass(isAsc ? 'schedule-table__th--sort-desc' : 'schedule-table__th--sort-asc')
			   .attr('aria-sort', isAsc ? 'descending' : 'ascending');

			const $rows = $tbody
				.find('tr.schedule-table__row:not(.schedule-table__row--empty)')
				.toArray();

			$rows.sort(function (a, b) {
				let aVal, bVal;

				if (sortKey === 'name') {
					aVal = $(a).find('.schedule-table__name').data('sort-name') || '';
					bVal = $(b).find('.schedule-table__name').data('sort-name') || '';
				} else if (sortKey === 'clinic') {
					aVal = $(a).find('.schedule-table__clinic').data('sort-clinic') || '';
					bVal = $(b).find('.schedule-table__clinic').data('sort-clinic') || '';
				} else {
					aVal = $(a).find('.schedule-table__status').data('sort-status') || '';
					bVal = $(b).find('.schedule-table__status').data('sort-status') || '';
					const order = { 'פעיל': 0, 'לא פעיל': 1 };
					aVal = order[aVal] !== undefined ? order[aVal] : 99;
					bVal = order[bVal] !== undefined ? order[bVal] : 99;
				}

				const cmp = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
				return $th.hasClass('schedule-table__th--sort-asc') ? cmp : -cmp;
			});

			$.each($rows, function (_, row) { $tbody.append(row); });
		}
	);

	/* ── תפריט שלוש נקודות (kebab) ── */

	function closeAllMenus() {
		$('.user-schedules-table-root .schedule-table__menu').attr('hidden', '');
		$('.user-schedules-table-root .schedule-table__menu-button').attr('aria-expanded', 'false');
	}

	/* פתיחה/סגירה בלחיצה על הכפתור */
	$(document).on(
		'click.schedulesTableKebab',
		'.user-schedules-table-root .schedule-table__menu-button',
		function (e) {
			e.stopPropagation();

			const $trigger = $(this);
			const $menu    = $trigger.siblings('.schedule-table__menu');
			const isOpen   = $trigger.attr('aria-expanded') === 'true';

			closeAllMenus();

			if (!isOpen) {
				$menu.removeAttr('hidden');
				$trigger.attr('aria-expanded', 'true');
			}
		}
	);

	/* סגירה בלחיצה מחוץ לתפריט */
	$(document).on('click.schedulesTableKebabOutside', function () {
		closeAllMenus();
	});

	/* מניעת סגירה בלחיצה בתוך התפריט */
	$(document).on(
		'click.schedulesTableKebabInside',
		'.user-schedules-table-root .schedule-table__menu',
		function (e) {
			e.stopPropagation();
		}
	);

	/* סגירה במקש Escape */
	$(document).on('keydown.schedulesTableKebabEsc', function (e) {
		if (e.key === 'Escape') {
			closeAllMenus();
		}
	});

	/* ── מודל אישור מחיקה ── */

	const $deleteOverlay = $('#schedule-table-delete-modal');
	const $deleteConfirm = $('#schedule-table-delete-modal-confirm');
	const $deleteBody    = $('#schedule-table-delete-modal-body');
	const $deleteError   = $('#schedule-table-delete-modal-error');
	let   deleteData     = {};

	function buildDeleteModalMessage(scheduleName, clinicName) {
		const name   = (scheduleName || '').trim();
		const clinic = (clinicName || '').trim();

		if (name && clinic) {
			return 'האם אתה בטוח שברצונך למחוק את היומן של ' + name + ' במרפאה ' + clinic + '? פעולה זו אינה ניתנת לביטול.';
		}
		if (name) {
			return 'האם אתה בטוח שברצונך למחוק את היומן של ' + name + '? פעולה זו אינה ניתנת לביטול.';
		}
		if (clinic) {
			return 'האם אתה בטוח שברצונך למחוק את היומן במרפאה ' + clinic + '? פעולה זו אינה ניתנת לביטול.';
		}
		return 'האם אתה בטוח שברצונך למחוק את היומן? פעולה זו אינה ניתנת לביטול.';
	}

	function openDeleteModal(scheduleId, scheduleName, clinicName, $row) {
		deleteData = { scheduleId, $row };

		$deleteBody.text(buildDeleteModalMessage(scheduleName, clinicName));

		$deleteError.attr('hidden', '').text('');
		$deleteConfirm.prop('disabled', false);
		$deleteOverlay.removeAttr('hidden');
		$deleteConfirm.trigger('focus');
	}

	function closeDeleteModal() {
		$deleteOverlay.attr('hidden', '');
		deleteData = {};
	}

	/* סגירה בלחיצה על הרקע */
	$(document).on('click.schedulesTableDeleteOverlay', '#schedule-table-delete-modal', function (e) {
		if ($(e.target).is('#schedule-table-delete-modal')) {
			closeDeleteModal();
		}
	});

	/* סגירה בכפתור ביטול */
	$(document).on('click.schedulesTableDeleteCancel', '#schedule-table-delete-modal-cancel', function () {
		closeDeleteModal();
	});

	/* סגירה במקש Escape */
	$(document).on('keydown.schedulesTableDeleteEsc', function (e) {
		if (e.key === 'Escape' && !$deleteOverlay.is('[hidden]')) {
			closeDeleteModal();
		}
	});

	/* פתיחת המודל בלחיצה על "מחיקת יומן" בתפריט */
	$(document).on(
		'click.schedulesTableDelete',
		'.user-schedules-table-root .schedule-table__delete-button',
		function () {
			const $row    = $(this).closest('tr.schedule-table__row');
			const id      = $row.data('id');
			const name    = $row.find('.schedule-table__name').data('sort-name') || '';
			const clinic  = $row.find('.schedule-table__clinic').data('sort-clinic') || '';

			closeAllMenus();
			openDeleteModal(id, name, clinic, $row);
		}
	);

	/* אישור — שליחת AJAX */
	$(document).on('click.schedulesTableDeleteConfirm', '#schedule-table-delete-modal-confirm', function () {
		const cfg     = window.clinicQueueUserSchedulesTable || {};
		const ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
		const nonce   = cfg.deleteNonce || '';

		$deleteConfirm.prop('disabled', true);
		$deleteError.attr('hidden', '').text('');

		$.post(
			ajaxUrl,
			{
				action:      'clinic_queue_delete_schedule',
				nonce:       nonce,
				schedule_id: deleteData.scheduleId,
			},
			function (response) {
				if (response && response.success) {
					const $removedRow = deleteData.$row;
					closeDeleteModal();
					if ($removedRow && $removedRow.length) {
						$removedRow.remove();
					}
				} else {
					const msg = (response && response.data && response.data.message)
						? response.data.message
						: 'אירעה שגיאה. אנא נסה שוב.';
					$deleteError.text(msg).removeAttr('hidden');
					$deleteConfirm.prop('disabled', false);
				}
			}
		).fail(function () {
			$deleteError.text('שגיאת תקשורת. אנא נסה שוב.').removeAttr('hidden');
			$deleteConfirm.prop('disabled', false);
		});
	});

})(window.jQuery);
