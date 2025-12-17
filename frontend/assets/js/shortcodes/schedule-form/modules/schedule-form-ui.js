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
		constructor(rootElement) {
			this.root = rootElement;
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
		setupGoogleStepSync(googleNextBtn, clinicSelect, doctorSelect, manualCalendar) {
			const syncGoogleStep = () => {
				const hasDoctor = doctorSelect && doctorSelect.value;
				const hasManual = manualCalendar && manualCalendar.value.trim().length > 0;

				if (doctorSelect) doctorSelect.disabled = hasManual || !clinicSelect?.value;
				if (clinicSelect) clinicSelect.disabled = hasManual;
				if (manualCalendar) manualCalendar.disabled = hasDoctor;

				if (googleNextBtn) {
					googleNextBtn.disabled = !(hasDoctor || hasManual);
				}
			};

			if (doctorSelect) {
				doctorSelect.addEventListener('change', syncGoogleStep);
			}
			if (manualCalendar) {
				['input', 'change'].forEach(evt => manualCalendar.addEventListener(evt, syncGoogleStep));
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
		 * Setup time splits (add/remove time ranges)
		 */
		setupTimeSplits() {
			// Add time split buttons
			this.root.querySelectorAll('.add-time-split-btn').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const day = e.target.closest('.add-time-split-btn').dataset.day;
					const timeRangesList = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
					
					if (timeRangesList) {
						// Check if already at max (2 splits)
						const currentCount = timeRangesList.querySelectorAll('.time-range-row').length;
						if (currentCount >= 2) {
							return; // Don't add more than 2 splits
						}
						
						const firstRow = timeRangesList.querySelector('.time-range-row');
						this.addTimeRange(timeRangesList, firstRow, day);
					}
				});
			});

			// Setup initial remove buttons
			this.root.querySelectorAll('.time-ranges-list').forEach(list => {
				this.setupRemoveButtons(list, '.time-range-row', '.remove-time-split-btn');
			});
			
			// Initialize button visibility for all days
			this.root.querySelectorAll('.time-ranges-list').forEach(list => {
				const day = list.dataset.day;
				this.updateAddButtonVisibility(day);
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
			
			// Don't add if already at max (2 splits)
			if (currentCount >= 2) {
				return;
			}
			
			const newRow = templateRow.cloneNode(true);
			
			// Show remove button
			const removeBtn = newRow.querySelector('.remove-time-split-btn');
			if (removeBtn) {
				removeBtn.style.display = 'inline-flex';
			}
			
			// Show all remove buttons
			container.querySelectorAll('.remove-time-split-btn').forEach(btn => {
				btn.style.display = 'inline-flex';
			});
			
			container.appendChild(newRow);
			
			// Update add button visibility
			if (day) {
				this.updateAddButtonVisibility(day);
			}
			
			// Setup remove functionality for new row
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
			
			// Update remove buttons visibility
			const container = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			if (container) {
				const remainingItems = container.querySelectorAll('.time-range-row');
				if (remainingItems.length === 1) {
					const lastRemoveBtn = remainingItems[0].querySelector('.remove-time-split-btn');
					if (lastRemoveBtn) {
						lastRemoveBtn.style.display = 'none';
					}
				}
			}
			
			// Update add button visibility
			if (day) {
				this.updateAddButtonVisibility(day);
			}
		}

		/**
		 * Setup remove buttons for repeater items
		 */
		setupRemoveButtons(container, itemSelector, btnSelector) {
			const removeButtons = container.querySelectorAll(btnSelector);
			const day = container.dataset.day;
			
			removeButtons.forEach(btn => {
				btn.addEventListener('click', () => {
					const row = btn.closest(itemSelector);
					if (row) {
						row.remove();
						
						const remainingItems = container.querySelectorAll(itemSelector);
						if (remainingItems.length === 1) {
							const lastRemoveBtn = remainingItems[0].querySelector(btnSelector);
							if (lastRemoveBtn) {
								lastRemoveBtn.style.display = 'none';
							}
						}
						
						// Update add button visibility for time splits
						if (itemSelector === '.time-range-row' && day) {
							this.updateAddButtonVisibility(day);
						}
					}
				});
			});
		}

		/**
		 * Setup treatments repeater
		 */
		setupTreatmentsRepeater() {
			const addTreatmentBtn = this.root.querySelector('.add-treatment-btn');
			const treatmentsRepeater = this.root.querySelector('.treatments-repeater');
			
			if (addTreatmentBtn && treatmentsRepeater) {
				addTreatmentBtn.addEventListener('click', () => {
					const firstTreatment = treatmentsRepeater.querySelector('.treatment-row');
					this.addTreatmentRow(treatmentsRepeater, firstTreatment);
				});
			}

			// Setup initial remove buttons
			if (treatmentsRepeater) {
				this.setupRemoveButtons(treatmentsRepeater, '.treatment-row', '.remove-treatment-btn');
			}
		}

		/**
		 * Add a treatment row
		 */
		addTreatmentRow(container, templateRow) {
			const newRow = templateRow.cloneNode(true);
			
			// Clear values
			newRow.querySelectorAll('input').forEach(input => input.value = '');
			newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
			
			// Show remove button
			const removeBtn = newRow.querySelector('.remove-treatment-btn');
			if (removeBtn) {
				removeBtn.style.display = 'inline-flex';
			}
			
			// Show all remove buttons
			container.querySelectorAll('.remove-treatment-btn').forEach(btn => {
				btn.style.display = 'inline-flex';
			});
			
			container.appendChild(newRow);
			
			// Setup remove functionality for new row
			if (removeBtn) {
				removeBtn.addEventListener('click', function() {
					this.closest('.treatment-row').remove();
					
					const remainingItems = container.querySelectorAll('.treatment-row');
					if (remainingItems.length === 1) {
						const lastRemoveBtn = remainingItems[0].querySelector('.remove-treatment-btn');
						if (lastRemoveBtn) {
							lastRemoveBtn.style.display = 'none';
						}
					}
				});
			}
		}

		/**
		 * Populate clinic select
		 */
		populateClinicSelect(clinics, clinicSelect) {
			if (!clinicSelect) return;

			clinicSelect.innerHTML = '<option value="">בחר מרפאה</option>';

			if (clinics && clinics.length > 0) {
				clinics.forEach(clinic => {
					const option = document.createElement('option');
					option.value = clinic.id;
					option.textContent = clinic.title.rendered || clinic.title || clinic.name;
					clinicSelect.appendChild(option);
				});
				clinicSelect.disabled = false;
			} else {
				clinicSelect.innerHTML = '<option value="">לא נמצאו מרפאות</option>';
			}
		}

		/**
		 * Populate doctor select
		 */
		populateDoctorSelect(doctors, doctorSelect, dataManager) {
			if (!doctorSelect) return;

			doctorSelect.innerHTML = '<option value="">בחר רופא</option>';

			if (doctors && doctors.length > 0) {
				doctors.forEach(doctor => {
					const option = document.createElement('option');
					option.value = doctor.id;
					option.textContent = dataManager.getDoctorName(doctor);
					doctorSelect.appendChild(option);
				});
				doctorSelect.disabled = false;
			} else {
				doctorSelect.innerHTML = '<option value="">לא נמצאו רופאים במרפאה זו</option>';
			}
		}

		/**
		 * Populate subspeciality selects
		 */
		populateSubspecialitySelects(subspecialities) {
			const subspecialitySelects = this.root.querySelectorAll('.subspeciality-select');

			subspecialitySelects.forEach(select => {
				select.innerHTML = '<option value="">בחר תת-תחום</option>';
				
				if (subspecialities && subspecialities.length > 0) {
					subspecialities.forEach(term => {
						const option = document.createElement('option');
						option.value = term.id;
						option.textContent = term.name;
						select.appendChild(option);
					});
				}
			});
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
		 * Show error message
		 */
		showError(message) {
			alert(message); // Simple for now, can be improved with toast notifications
		}
	}

	// Export to global scope
	window.ScheduleFormUIManager = ScheduleFormUIManager;

})(window);

