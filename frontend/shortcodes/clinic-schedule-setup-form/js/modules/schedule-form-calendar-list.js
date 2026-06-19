/**
 * Schedule Form Calendar List Module
 * Shared renderer for Google Calendar and Clinix calendar selection lists.
 *
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Render a list of calendar items into the given root element.
	 * Handles selection state and the `inUse` disabled state.
	 *
	 * @param {HTMLElement} root          - Root element of the schedule form.
	 * @param {Array}       calendars     - Array of calendar objects from the API.
	 * @param {Object}      stepsManager  - Steps manager instance (used to store selection).
	 */
	function renderCalendarList(root, calendars, stepsManager) {
		const container = root.querySelector('.calendar-list-container');
		const saveBtn   = root.querySelector('.save-calendar-btn');

		if (!container) {
			return;
		}

		if (!calendars || calendars.length === 0) {
			container.innerHTML = '<p style="text-align:center;color:#666;">לא נמצאו יומנים זמינים</p>';
			return;
		}

		container.innerHTML = '';

		calendars.forEach((calendar) => {
			const isDisabled = calendar.inUse === true;
			const item = document.createElement('div');

			item.className = 'calendar-item' + (isDisabled ? ' is-disabled' : '');
			item.dataset.sourceSchedulerId = calendar.sourceSchedulerID || '';

			if (isDisabled) {
				item.setAttribute('aria-disabled', 'true');
			}

			item.innerHTML = `
				<div class="calendar-item-info">
					<div class="calendar-item-name">${escapeHtml(calendar.name || '')}</div>
					${calendar.description ? `<div class="calendar-item-description">${escapeHtml(calendar.description)}</div>` : ''}
				</div>
				${isDisabled ? '<div class="calendar-item-in-use-badge">כבר נמצא בשימוש</div>' : ''}
			`;

			if (!isDisabled) {
				item.addEventListener('click', () => {
					container.querySelectorAll('.calendar-item.is-selected').forEach((el) => {
						el.classList.remove('is-selected');
					});

					item.classList.add('is-selected');

					if (stepsManager && typeof stepsManager.updateFormData === 'function') {
						stepsManager.updateFormData({ selected_calendar_id: calendar.sourceSchedulerID || '' });
					}

					if (saveBtn) {
						saveBtn.disabled = false;
					}
				});
			}

			container.appendChild(item);
		});

		// Keep save button disabled until the user picks a calendar
		if (saveBtn) {
			saveBtn.disabled = true;
		}
	}

	/**
	 * Escape HTML entities to prevent XSS.
	 *
	 * @param {string} str
	 * @returns {string}
	 */
	function escapeHtml(str) {
		const div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	// Export to global scope
	window.ScheduleFormCalendarList = {
		renderCalendarList,
	};

})(window);
