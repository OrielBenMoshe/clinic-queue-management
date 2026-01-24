/**
 * Schedule Form UI Module
 * Handles UI interactions and repeaters
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	// Module loaded - version will be logged by init module

	/**
	 * Schedule Form UI Manager
	 */
	class ScheduleFormUIManager {
		constructor(rootElement, config) {
			this.root = rootElement;
			this.config = config || {};
			
			// Default placeholder for doctor field (used in Select2 initialization)
			// Note: Actual placeholder management is in Field Manager
			this.doctorPlaceholderDefault = 'בחר רופא';
		}

	/**
	 * Setup action card selection (Step 1)
	 */
	setupActionCards(onSelectCallback) {
		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log('Setting up action cards');
		}
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
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log('Card clicked, value:', value);
				}
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
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log('Continue button clicked, selected value:', value);
				}
				if (value && onSelectCallback) {
					onSelectCallback(value);
				}
			});
		}
	}

		/**
		 * Sync Google step (Step 2) - enable/disable fields
		 */
		setupGoogleStepSync(googleNextBtn, clinicSelect, doctorSelect, manualScheduleName) {
			const syncGoogleStep = () => {
				const $doctorSelect = typeof jQuery !== 'undefined' && doctorSelect ? jQuery(doctorSelect) : null;
				const hasDoctor = $doctorSelect ? ($doctorSelect.val() && $doctorSelect.val() !== '') : (doctorSelect && doctorSelect.value);
				const hasManual = manualScheduleName && manualScheduleName.value.trim().length > 0;

				// Update doctor select disabled state
				if (doctorSelect) {
					const shouldDisableDoctor = hasManual || !clinicSelect?.value;
					doctorSelect.disabled = shouldDisableDoctor;
					
					// Update Select2 disabled state if initialized
					if ($doctorSelect && $doctorSelect.hasClass('select2-hidden-accessible')) {
						if (shouldDisableDoctor) {
							$doctorSelect.prop('disabled', true);
						} else {
							$doctorSelect.prop('disabled', false);
						}
						// Update Select2 UI to reflect disabled state
						$doctorSelect.trigger('change.select2');
					}
					
					// Update field-disabled class for doctor field
					const doctorField = this.root.querySelector('.doctor-select-field');
					if (doctorField) {
						if (shouldDisableDoctor) {
							doctorField.classList.add('field-disabled');
						} else {
							doctorField.classList.remove('field-disabled');
						}
					}
				}

				// Update clinic select disabled state
				if (clinicSelect) {
					const $clinicSelect = typeof jQuery !== 'undefined' ? jQuery(clinicSelect) : null;
					// Clinic is mandatory, never disable it unless loading (handled elsewhere)
					clinicSelect.disabled = false;
					
					// Update Select2 disabled state if initialized
					if ($clinicSelect && $clinicSelect.hasClass('select2-hidden-accessible')) {
						$clinicSelect.prop('disabled', false);
					}
					
					// Update field-disabled class for clinic field
					const clinicField = this.root.querySelector('.clinic-select-field');
					if (clinicField) {
						clinicField.classList.remove('field-disabled');
					}
				}

				// Update manual calendar disabled state
				if (manualScheduleName) {
					manualScheduleName.disabled = hasDoctor;
					
					// Update field-disabled class for manual calendar field
					const manualScheduleNameField = manualScheduleName.closest('.jet-form-builder__row');
					if (manualScheduleNameField) {
						if (hasDoctor) {
							manualScheduleNameField.classList.add('field-disabled');
						} else {
							manualScheduleNameField.classList.remove('field-disabled');
						}
					}
				}

				if (googleNextBtn) {
					googleNextBtn.disabled = !(hasDoctor || hasManual);
				}
			};

			// Listen to doctor select changes (both native and Select2)
			if (doctorSelect) {
				doctorSelect.addEventListener('change', syncGoogleStep);
				
				// Also listen to Select2 events if jQuery is available
				if (typeof jQuery !== 'undefined') {
					const $doctorSelect = jQuery(doctorSelect);
					$doctorSelect.on('select2:select select2:clear', syncGoogleStep);
				}
			}
			
			// Listen to manual calendar changes
			if (manualScheduleName) {
				['input', 'change'].forEach(evt => manualScheduleName.addEventListener(evt, syncGoogleStep));
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
			// Helper: time string -> minutes
			const toMinutes = (timeStr) => {
				const [h, m] = (timeStr || '0:0').split(':').map(Number);
				return (h * 60) + (m || 0);
			};
			
			// Normalize all time ranges for a given day:
			// - each start >= previous end
			// - each end > start
			// - available options are disabled accordingly
			const normalizeDayTimeRanges = (day) => {
				const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
				if (!list) return;
				
				let prevEndMinutes = null;
				const rows = Array.from(list.querySelectorAll('.time-range-row'));
				
			rows.forEach((row, idx) => {
				const fromSelect = row.querySelector('.from-time');
				const toSelect = row.querySelector('.to-time');
				if (!fromSelect || !toSelect) return;
				
				// Enable and show all options first
				fromSelect.querySelectorAll('option').forEach(opt => {
					opt.disabled = false;
					opt.style.display = '';
				});
				toSelect.querySelectorAll('option').forEach(opt => {
					opt.disabled = false;
					opt.style.display = '';
				});
				
				// Enforce start >= prevEnd
				if (prevEndMinutes !== null) {
					fromSelect.querySelectorAll('option').forEach(opt => {
						if (toMinutes(opt.value) < prevEndMinutes) {
							opt.disabled = true;
							opt.style.display = 'none';
						}
					});
				}
				
				// Ensure from value is valid
				let fromVal = fromSelect.value;
				if (fromVal && prevEndMinutes !== null && toMinutes(fromVal) < prevEndMinutes) {
					// Pick first available >= prevEnd
					const allowed = Array.from(fromSelect.options).find(opt => !opt.disabled);
					if (allowed) {
						fromSelect.value = allowed.value;
					}
					fromVal = fromSelect.value;
				}
				
				// Enforce end > start
				const startMinutes = toMinutes(fromSelect.value);
				toSelect.querySelectorAll('option').forEach(opt => {
					if (toMinutes(opt.value) <= startMinutes) {
						opt.disabled = true;
						opt.style.display = 'none';
					}
				});
					
					// Ensure to value is valid
					let toVal = toSelect.value;
					if (!toVal || toMinutes(toVal) <= startMinutes || toSelect.selectedOptions[0]?.disabled) {
						const allowedTo = Array.from(toSelect.options).find(opt => !opt.disabled && toMinutes(opt.value) > startMinutes);
						if (allowedTo) {
							toSelect.value = allowedTo.value;
						} else {
							// If no later option, fall back to closest after start (if exists) else keep
							const later = Array.from(toSelect.options).find(opt => toMinutes(opt.value) > startMinutes);
							if (later) {
								toSelect.value = later.value;
							}
						}
						toVal = toSelect.value;
					}
					
					// Update Select2 if initialized
					if (typeof jQuery !== 'undefined') {
						const $fromSelect = jQuery(fromSelect);
						const $toSelect = jQuery(toSelect);
						
						if ($fromSelect.hasClass('select2-hidden-accessible')) {
							$fromSelect.trigger('change.select2');
						}
						if ($toSelect.hasClass('select2-hidden-accessible')) {
							$toSelect.trigger('change.select2');
						}
					}
					
					// Update prevEnd for next row
					prevEndMinutes = toMinutes(toSelect.value);
				});
			};
			
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
						normalizeDayTimeRanges(day);
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
				normalizeDayTimeRanges(day);
				
				// Attach change listeners to enforce constraints on change
				list.querySelectorAll('.time-range-row').forEach(row => {
					const fromSelect = row.querySelector('.from-time');
					const toSelect = row.querySelector('.to-time');
					if (fromSelect) {
						fromSelect.addEventListener('change', () => normalizeDayTimeRanges(day));
						
						// Also listen to Select2 events if jQuery is available
						if (typeof jQuery !== 'undefined') {
							jQuery(fromSelect).on('select2:select', () => normalizeDayTimeRanges(day));
						}
					}
					if (toSelect) {
						toSelect.addEventListener('change', () => normalizeDayTimeRanges(day));
						
						// Also listen to Select2 events if jQuery is available
						if (typeof jQuery !== 'undefined') {
							jQuery(toSelect).on('select2:select', () => normalizeDayTimeRanges(day));
						}
					}
				});
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
		
		// Remove cloned Select2 containers (they'll be recreated)
		newRow.querySelectorAll('.select2-container').forEach(container => container.remove());
		newRow.querySelectorAll('.select-field').forEach(select => {
			select.classList.remove('select2-hidden-accessible');
			select.removeAttribute('data-select2-id');
			select.removeAttribute('aria-hidden');
			select.removeAttribute('tabindex');
		});
		
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
		
		// Reinitialize Select2 for new time selects
		this.reinitializeSelect2();
		
		// Attach time constraint listeners to the new row
		this.attachTimeConstraintListeners(newRow, day);
		
		// Setup remove functionality for new row
		if (removeBtn) {
			removeBtn.addEventListener('click', () => {
				this.removeTimeRange(newRow, day);
			});
		}
	}

	/**
	 * Attach time constraint listeners to a time range row
	 */
	attachTimeConstraintListeners(row, day) {
		// Helper: time string -> minutes
		const toMinutes = (timeStr) => {
			const [h, m] = (timeStr || '0:0').split(':').map(Number);
			return (h * 60) + (m || 0);
		};
		
		// Normalize day time ranges function
		const normalizeDayTimeRanges = (day) => {
			const list = this.root.querySelector(`.time-ranges-list[data-day="${day}"]`);
			if (!list) return;
			
			let prevEndMinutes = null;
			const rows = Array.from(list.querySelectorAll('.time-range-row'));
			
			rows.forEach((row, idx) => {
				const fromSelect = row.querySelector('.from-time');
				const toSelect = row.querySelector('.to-time');
				if (!fromSelect || !toSelect) return;
				
				// Enable and show all options first
				fromSelect.querySelectorAll('option').forEach(opt => {
					opt.disabled = false;
					opt.style.display = '';
				});
				toSelect.querySelectorAll('option').forEach(opt => {
					opt.disabled = false;
					opt.style.display = '';
				});
				
				// Enforce start >= prevEnd
				if (prevEndMinutes !== null) {
					fromSelect.querySelectorAll('option').forEach(opt => {
						if (toMinutes(opt.value) < prevEndMinutes) {
							opt.disabled = true;
							opt.style.display = 'none';
						}
					});
				}
				
				// Ensure from value is valid
				let fromVal = fromSelect.value;
				if (fromVal && prevEndMinutes !== null && toMinutes(fromVal) < prevEndMinutes) {
					// Pick first available >= prevEnd
					const allowed = Array.from(fromSelect.options).find(opt => !opt.disabled);
					if (allowed) {
						fromSelect.value = allowed.value;
					}
					fromVal = fromSelect.value;
				}
				
				// Enforce end > start
				const startMinutes = toMinutes(fromSelect.value);
				toSelect.querySelectorAll('option').forEach(opt => {
					if (toMinutes(opt.value) <= startMinutes) {
						opt.disabled = true;
						opt.style.display = 'none';
					}
				});
				
				// Ensure to value is valid
				let toVal = toSelect.value;
				if (!toVal || toMinutes(toVal) <= startMinutes || toSelect.selectedOptions[0]?.disabled) {
					const allowedTo = Array.from(toSelect.options).find(opt => !opt.disabled && toMinutes(opt.value) > startMinutes);
					if (allowedTo) {
						toSelect.value = allowedTo.value;
					} else {
						const later = Array.from(toSelect.options).find(opt => toMinutes(opt.value) > startMinutes);
						if (later) {
							toSelect.value = later.value;
						}
					}
					toVal = toSelect.value;
				}
				
				// Update Select2 if initialized
				if (typeof jQuery !== 'undefined') {
					const $fromSelect = jQuery(fromSelect);
					const $toSelect = jQuery(toSelect);
					
					if ($fromSelect.hasClass('select2-hidden-accessible')) {
						$fromSelect.trigger('change.select2');
					}
					if ($toSelect.hasClass('select2-hidden-accessible')) {
						$toSelect.trigger('change.select2');
					}
				}
				
				// Update prevEnd for next row
				prevEndMinutes = toMinutes(toSelect.value);
			});
		};
		
		const fromSelect = row.querySelector('.from-time');
		const toSelect = row.querySelector('.to-time');
		
		if (fromSelect) {
			fromSelect.addEventListener('change', () => normalizeDayTimeRanges(day));
			
			// Also listen to Select2 events if jQuery is available
			if (typeof jQuery !== 'undefined') {
				jQuery(fromSelect).on('select2:select', () => normalizeDayTimeRanges(day));
			}
		}
		
		if (toSelect) {
			toSelect.addEventListener('change', () => normalizeDayTimeRanges(day));
			
			// Also listen to Select2 events if jQuery is available
			if (typeof jQuery !== 'undefined') {
				jQuery(toSelect).on('select2:select', () => normalizeDayTimeRanges(day));
			}
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
					// Use first editable row as template (not the default row)
					const firstEditableRow = treatmentsRepeater.querySelector('.treatment-row:not(.treatment-row-default)');
					if (firstEditableRow) {
						this.addTreatmentRow(treatmentsRepeater, firstEditableRow);
					}
				});
			}

			// Setup initial remove buttons (only for editable rows, not default)
			if (treatmentsRepeater) {
				// Setup remove button handlers for existing rows
				const editableRows = treatmentsRepeater.querySelectorAll('.treatment-row:not(.treatment-row-default)');
				
				editableRows.forEach(row => {
					const removeBtn = row.querySelector('.remove-treatment-btn');
					if (removeBtn) {
						// Remove existing listeners by cloning
						const newBtn = removeBtn.cloneNode(true);
						removeBtn.parentNode.replaceChild(newBtn, removeBtn);
						
						// Add new listener
						newBtn.addEventListener('click', () => {
							if (row && !row.classList.contains('treatment-row-default')) {
								row.remove();
								
								// Update treatment selects availability after removal
								this.updateTreatmentSelectsAvailability();
								
								// Update remove button visibility
								const remainingEditableRows = treatmentsRepeater.querySelectorAll('.treatment-row:not(.treatment-row-default)');
								if (remainingEditableRows.length === 1) {
									const lastRemoveBtn = remainingEditableRows[0].querySelector('.remove-treatment-btn');
									if (lastRemoveBtn) {
										lastRemoveBtn.style.display = 'none';
									}
								}
							}
						});
						
						// Show remove button
						newBtn.style.display = 'inline-flex';
					}
				});
				
				// Hide remove button on first editable row if it's the only one
				if (editableRows.length === 1) {
					const firstRemoveBtn = editableRows[0].querySelector('.remove-treatment-btn');
					if (firstRemoveBtn) {
						firstRemoveBtn.style.display = 'none';
					}
				}
			}
		}

	/**
	 * Get all selected treatment values (excluding default row)
	 * @returns {Array<string>} Array of selected treatment JSON strings
	 */
	getSelectedTreatments() {
		const selectedTreatments = [];
		const editableSelects = Array.from(this.root.querySelectorAll('.treatment-name-select'))
			.filter(select => {
				const row = select.closest('.treatment-row');
				return row && !row.dataset.isDefault && select.value && select.value !== '';
			});
		
		editableSelects.forEach(select => {
			if (select.value && select.value !== '') {
				selectedTreatments.push(select.value);
			}
		});
		
		return selectedTreatments;
	}

	/**
	 * Get available treatments (excluding already selected ones)
	 * @param {Array} allTreatments - All available treatments
	 * @param {HTMLElement} excludeSelect - Select element to exclude from check (current select)
	 * @returns {Array} Available treatments
	 */
	getAvailableTreatments(allTreatments, excludeSelect = null) {
		const selectedTreatments = this.getSelectedTreatments();
		
		return allTreatments.filter(treatment => {
			const treatmentJson = JSON.stringify(treatment);
			
			// Exclude if already selected in another row
			if (selectedTreatments.includes(treatmentJson)) {
				// But include if it's selected in the current select (excludeSelect)
				if (excludeSelect && excludeSelect.value === treatmentJson) {
					return true;
				}
				return false;
			}
			
			return true;
		});
	}

	/**
	 * Update all treatment selects to exclude already selected treatments
	 */
	updateTreatmentSelectsAvailability() {
		if (!this.root.clinicTreatments) return;
		
		const treatments = Array.isArray(this.root.clinicTreatments) 
			? this.root.clinicTreatments 
			: Object.values(this.root.clinicTreatments).flat();
		
		const editableSelects = Array.from(this.root.querySelectorAll('.treatment-name-select'))
			.filter(select => {
				const row = select.closest('.treatment-row');
				return row && !row.dataset.isDefault;
			});
		
		editableSelects.forEach(select => {
			const currentValue = select.value;
			const availableTreatments = this.getAvailableTreatments(treatments, select);
			
			// Clear and rebuild options
			select.innerHTML = '<option value="">בחר שם טיפול</option>';
			
			availableTreatments.forEach(treatment => {
				const option = document.createElement('option');
				option.value = JSON.stringify(treatment);
				option.textContent = treatment.treatment_type;
				
				// Restore current selection if still available
				if (currentValue === option.value) {
					option.selected = true;
				}
				
				select.appendChild(option);
			});
			
			// If current value is no longer available, clear selection
			if (currentValue && !availableTreatments.some(t => JSON.stringify(t) === currentValue)) {
				select.value = '';
			}
		});
		
		// Reinitialize Select2
		this.reinitializeSelect2();
		
		// Update add button visibility
		this.updateAddTreatmentButtonVisibility();
	}

	/**
	 * Update add treatment button visibility based on available treatments
	 */
	updateAddTreatmentButtonVisibility() {
		const addTreatmentBtn = this.root.querySelector('.add-treatment-btn');
		if (!addTreatmentBtn || !this.root.clinicTreatments) return;
		
		const treatments = Array.isArray(this.root.clinicTreatments) 
			? this.root.clinicTreatments 
			: Object.values(this.root.clinicTreatments).flat();
		
		const selectedTreatments = this.getSelectedTreatments();
		const availableTreatments = treatments.length - selectedTreatments.length;
		
		// Hide button if no treatments available
		if (availableTreatments <= 0) {
			addTreatmentBtn.style.display = 'none';
		} else {
			addTreatmentBtn.style.display = '';
		}
	}

	/**
	 * Populate treatments after clinic selection
	 * @param {number} clinicId - Selected clinic ID
	 */
	async populateTreatmentCategories(clinicId) {
		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log(`Populating treatments for clinic ${clinicId}`);
		}
		
		try {
			// Load treatments from clinic
			const dataManager = new ScheduleFormDataManager(this.config);
			const { treatments } = await dataManager.loadClinicTreatments(clinicId);
			
			// Store all treatments in root element for later use
			this.root.clinicTreatments = treatments;
			
			if (!treatments || treatments.length === 0) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.warn('No treatments found for clinic', { clinicId });
				}
				// Disable all treatment selects
				this.root.querySelectorAll('.treatment-name-select').forEach(select => {
					select.disabled = true;
					select.innerHTML = '<option value="">לא נמצאו טיפולים למרפאה זו</option>';
				});
				this.reinitializeSelect2();
				return;
			}
			
			// Get first treatment for default row
			const firstTreatment = treatments[0];
			
			// Get default treatment row (read-only)
			const defaultRow = this.root.querySelector('.treatment-row-default');
			const defaultSelect = defaultRow ? defaultRow.querySelector('.treatment-name-select') : null;
			
			// Get treatments repeater
			const treatmentsRepeater = this.root.querySelector('.treatments-repeater');
			
			// If only one treatment, remove all editable rows (keep only default)
			if (treatments.length === 1) {
				const editableRows = treatmentsRepeater ? treatmentsRepeater.querySelectorAll('.treatment-row:not(.treatment-row-default)') : [];
				editableRows.forEach(row => row.remove());
				
				// Hide add button
				const addTreatmentBtn = this.root.querySelector('.add-treatment-btn');
				if (addTreatmentBtn) {
					addTreatmentBtn.style.display = 'none';
				}
			} else {
				// Show add button if there are multiple treatments
				const addTreatmentBtn = this.root.querySelector('.add-treatment-btn');
				if (addTreatmentBtn) {
					addTreatmentBtn.style.display = '';
				}
				
				// Get editable treatment rows (all except default)
				const editableSelects = Array.from(this.root.querySelectorAll('.treatment-name-select'))
					.filter(select => {
						const row = select.closest('.treatment-row');
						return row && !row.dataset.isDefault;
					});
				
				// Populate editable rows with available treatments (excluding default)
				editableSelects.forEach((select) => {
					select.innerHTML = '<option value="">בחר שם טיפול</option>';
					
					// Exclude first treatment (already in default row)
					const availableTreatments = treatments.filter((t, index) => index !== 0);
					
					availableTreatments.forEach(treatment => {
						const option = document.createElement('option');
						option.value = JSON.stringify(treatment);
						option.textContent = treatment.treatment_type;
						select.appendChild(option);
					});
					
					// Enable the select
					select.disabled = false;
				});
				
				// Setup change listeners to update availability
				editableSelects.forEach(select => {
					// Remove existing listeners
					const newSelect = select.cloneNode(true);
					select.parentNode.replaceChild(newSelect, select);
					
					// Add new listener
					newSelect.addEventListener('change', () => {
						this.updateTreatmentSelectsAvailability();
					});
				});
			}
			
			// Populate default row with first treatment (read-only)
			if (defaultSelect) {
				defaultSelect.innerHTML = '';
				const option = document.createElement('option');
				option.value = JSON.stringify(firstTreatment);
				option.textContent = firstTreatment.treatment_type;
				option.selected = true;
				defaultSelect.appendChild(option);
				defaultSelect.disabled = true; // Read-only
			}
			
			// Reinitialize Select2
			this.reinitializeSelect2();
			
			// Update add button visibility
			this.updateAddTreatmentButtonVisibility();
			
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.log(`Successfully populated ${treatments.length} treatments`);
			}
		} catch (error) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.error('Error populating treatments', error);
			}
			
			// Show error in selects
			this.root.querySelectorAll('.treatment-name-select').forEach(select => {
				select.disabled = true;
				select.innerHTML = '<option value="">שגיאה בטעינת טיפולים</option>';
			});
			this.reinitializeSelect2();
		}
	}


	/**
	 * Add a treatment row (updated for new structure - no category field)
	 */
	addTreatmentRow(container, templateRow) {
		// Check if there are available treatments
		if (!this.root.clinicTreatments) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.warn('Cannot add treatment row: treatments not loaded');
			}
			return;
		}
		
		const treatments = Array.isArray(this.root.clinicTreatments) 
			? this.root.clinicTreatments 
			: Object.values(this.root.clinicTreatments).flat();
		
		// Check if all treatments are already selected
		const selectedTreatments = this.getSelectedTreatments();
		const availableTreatments = treatments.length - selectedTreatments.length - 1; // -1 for default row
		
		if (availableTreatments <= 0) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.warn('Cannot add treatment row: all treatments already selected');
			}
			return;
		}
		
		// Don't clone the default row - use the first editable row as template
		const editableRows = container.querySelectorAll('.treatment-row:not(.treatment-row-default)');
		const template = editableRows.length > 0 ? editableRows[0] : templateRow;
		
		const newRow = template.cloneNode(true);
		
		// Calculate new row index (exclude default row)
		const allRows = container.querySelectorAll('.treatment-row:not(.treatment-row-default)');
		const rowIndex = allRows.length;
		
		// Remove cloned Select2 containers
		newRow.querySelectorAll('.select2-container').forEach(container => container.remove());
		newRow.querySelectorAll('.select-field').forEach(select => {
			select.classList.remove('select2-hidden-accessible');
			select.removeAttribute('data-select2-id');
			select.removeAttribute('aria-hidden');
			select.removeAttribute('tabindex');
		});
		
		// Update row index and data attributes
		newRow.dataset.rowIndex = rowIndex;
		newRow.removeAttribute('data-is-default'); // Make sure it's not marked as default
		newRow.querySelectorAll('select').forEach(select => {
			select.dataset.rowIndex = rowIndex;
			select.selectedIndex = 0;
		});
		
		// Populate treatment select with available treatments (excluding already selected and default)
		const treatmentSelect = newRow.querySelector('.treatment-name-select');
		if (treatmentSelect) {
			treatmentSelect.innerHTML = '<option value="">בחר שם טיפול</option>';
			
			// Get available treatments (excluding default and already selected)
			const availableTreatmentsList = this.getAvailableTreatments(treatments, treatmentSelect);
			// Also exclude first treatment (default row)
			const firstTreatmentJson = JSON.stringify(treatments[0]);
			const filteredTreatments = availableTreatmentsList.filter(t => JSON.stringify(t) !== firstTreatmentJson);
			
			filteredTreatments.forEach(treatment => {
				const option = document.createElement('option');
				option.value = JSON.stringify(treatment);
				option.textContent = treatment.treatment_type;
				treatmentSelect.appendChild(option);
			});
			
			treatmentSelect.disabled = false;
			
			// Add change listener
			treatmentSelect.addEventListener('change', () => {
				this.updateTreatmentSelectsAvailability();
			});
		}
		
		// Show remove button
		const removeBtn = newRow.querySelector('.remove-treatment-btn');
		if (removeBtn) {
			removeBtn.style.display = 'inline-flex';
		}
		
		// Show all remove buttons (except for default row)
		container.querySelectorAll('.treatment-row:not(.treatment-row-default) .remove-treatment-btn').forEach(btn => {
			btn.style.display = 'inline-flex';
		});
		
		container.appendChild(newRow);
		
		// Reinitialize Select2
		this.reinitializeSelect2();
		
		// Setup remove functionality
		if (removeBtn) {
			removeBtn.addEventListener('click', () => {
				const row = removeBtn.closest('.treatment-row');
				if (row && !row.classList.contains('treatment-row-default')) {
					row.remove();
					
					// Update treatment selects availability after removal
					this.updateTreatmentSelectsAvailability();
					
					// Update remove button visibility
					const remainingEditableRows = container.querySelectorAll('.treatment-row:not(.treatment-row-default)');
					if (remainingEditableRows.length === 1) {
						const lastRemoveBtn = remainingEditableRows[0].querySelector('.remove-treatment-btn');
						if (lastRemoveBtn) {
							lastRemoveBtn.style.display = 'none';
						}
					}
				}
			});
		}
		
		// Update add button visibility
		this.updateAddTreatmentButtonVisibility();
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

		/**
		 * Initialize Select2 for all select fields
		 */
		initializeSelect2() {
		// Check if Select2 is available
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.warn('Select2 is not loaded, skipping initialization');
			}
			return;
		}

			const $root = jQuery(this.root);

			// Initialize Select2 for all select fields
			$root.find('.select-field').each((index, element) => {
				const $select = jQuery(element);
				
				// Skip if already initialized
				if ($select.hasClass('select2-hidden-accessible')) {
					return;
				}

				// Check if this is a time select field
				const isTimeSelect = $select.hasClass('time-select');
				
				// Prepare Select2 options
				const select2Options = {
					theme: 'clinic-queue',
					dir: 'rtl',
					language: 'he',
					width: '100%',
					minimumResultsForSearch: -1, // Disable search for all fields
					placeholder: $select.find('option:first').text(),
					allowClear: false, // No clear button
					dropdownParent: $root
				};
				
				$select.select2(select2Options);
			});
		}

	/**
	 * Reinitialize Select2 after dynamic content changes
	 * Note: This function updates Select2 but preserves custom placeholder text
	 */
	reinitializeSelect2() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
			return;
		}

		const $root = jQuery(this.root);
		
		// Update existing Select2 instances or initialize new ones
		$root.find('.select-field').each((index, element) => {
			const $select = jQuery(element);
			
			if ($select.hasClass('select2-hidden-accessible')) {
				// Destroy existing Select2 instance first
				$select.select2('destroy');
			}
			
			// Initialize Select2 instance (both new and re-initialized)
			const isTimeSelect = $select.hasClass('time-select');
			
			// Prepare Select2 options
			const select2Options = {
				theme: 'clinic-queue',
				dir: 'rtl',
				language: 'he',
				width: '100%',
				minimumResultsForSearch: -1, // Disable search for all fields
				placeholder: $select.find('option:first').text() || '',
				allowClear: false, // No clear button
				dropdownParent: $root,
				escapeMarkup: (markup) => markup
			};
			
			$select.select2(select2Options);
		});
	}

		/**
		 * Initialize floating labels for text fields (MUI style)
		 * Label moves from center (default) to top (focused/has value)
		 */
		initializeFloatingLabels() {
			const $root = typeof jQuery !== 'undefined' ? jQuery(this.root) : null;
			if (!$root) return;

			// Function to setup floating label for a single field
			const setupFloatingLabel = ($input) => {
				const $fieldRow = $input.closest('.field-type-text-field, .field-type-number-field');
				if (!$fieldRow.length) return;
				
				// Function to update label state
				const updateLabelState = () => {
					const currentValue = $input.val();
					const hasValue = currentValue && currentValue.toString().trim() !== '';
					const isFocused = $input.is(':focus');
					
					// Add has-value class if field has value OR is focused
					if (hasValue || isFocused) {
						$fieldRow.addClass('has-value');
					} else {
						$fieldRow.removeClass('has-value');
					}
				};
				
				// Check initial state immediately and after a short delay
				updateLabelState();
				setTimeout(updateLabelState, 10);
				setTimeout(updateLabelState, 100);
				
				// Handle input, focus, and blur events
				$input.off('input.floating-label focus.floating-label blur.floating-label');
				$input.on('input.floating-label', updateLabelState);
				$input.on('focus.floating-label', updateLabelState);
				$input.on('blur.floating-label', updateLabelState);
			};

			// Find all text and number fields with floating labels
			$root.find('.field-type-text-field .jet-form-builder__field[type="text"], .field-type-text-field .jet-form-builder__field[type="number"], .field-type-number-field .jet-form-builder__field[type="number"]').each((index, element) => {
				const $input = jQuery(element);
				// Only setup if field has a floating label (check if label exists as sibling)
				const $fieldWrap = $input.closest('.jet-form-builder__field-wrap');
				if ($fieldWrap && $fieldWrap.find('.floating-label').length > 0) {
					setupFloatingLabel($input);
				}
			});
			
			// Store setup function for dynamic fields
			this.setupFloatingLabel = setupFloatingLabel;
		}
	}

	// Export to global scope
	window.ScheduleFormUIManager = ScheduleFormUIManager;

})(window);

