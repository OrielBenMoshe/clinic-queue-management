/**
 * Schedule Form Calendar List Module
 * Shared UI for rendering calendar list (used by both Google and Clinix flows).
 * This module does not contain source-specific logic.
 *
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Renders a list of calendars into the container and wires selection to stepsManager.
	 *
	 * @param {HTMLElement} root Form root element
	 * @param {Array} calendars Array of { sourceSchedulerID, name, description }
	 * @param {Object} stepsManager Steps manager instance (updateFormData)
	 */
	function renderCalendarList(root, calendars, stepsManager) {
		const container = root.querySelector('.calendar-list-container');
		const saveBtn = root.querySelector('.save-calendar-btn');
		if (!container) return;

		if (!calendars || calendars.length === 0) {
			container.innerHTML = '<p style="text-align:center;color:#666;">לא נמצאו יומנים</p>';
			return;
		}

		let html = '';
		calendars.forEach((calendar, index) => {
			const isFirst = index === 0;
			html += `
				<div class="calendar-item ${isFirst ? 'is-selected' : ''}" 
					 data-source-scheduler-id="${calendar.sourceSchedulerID || ''}">
					<div style="flex:1;">
						<div class="calendar-item-name">${calendar.name || 'יומן ללא שם'}</div>
						<div class="calendar-item-description">${calendar.description || 'Lorem ipsum aliquet varius non'}</div>
					</div>
				</div>
			`;
		});

		container.innerHTML = html;

		container.querySelectorAll('.calendar-item').forEach(item => {
			item.addEventListener('click', () => {
				container.querySelectorAll('.calendar-item').forEach(i => {
					i.classList.remove('is-selected');
				});
				item.classList.add('is-selected');
				const sourceSchedulerID = item.dataset.sourceSchedulerId || '';
				if (stepsManager) {
					stepsManager.updateFormData({ selected_calendar_id: sourceSchedulerID });
				}
				if (saveBtn) saveBtn.disabled = false;
			});
		});

		if (stepsManager && calendars.length > 0) {
			stepsManager.updateFormData({
				selected_calendar_id: calendars[0].sourceSchedulerID || ''
			});
		}
		if (saveBtn && calendars.length > 0) {
			saveBtn.disabled = false;
		}
	}

	window.ScheduleFormCalendarList = {
		renderCalendarList: renderCalendarList
	};
})(window);
