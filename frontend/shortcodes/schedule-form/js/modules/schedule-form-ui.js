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
		 * Setup treatments repeater: add button clones default row; change listeners trigger validation.
		 */
		setupTreatmentsRepeater() {
			const addTreatmentBtn = this.root.querySelector('.add-treatment-btn');
			const treatmentsRepeater = this.root.querySelector('.treatments-repeater');
			if (!treatmentsRepeater) return;

			if (addTreatmentBtn) {
				addTreatmentBtn.addEventListener('click', () => {
					const defaultRow = treatmentsRepeater.querySelector('.treatment-row-default');
					if (defaultRow) {
						this.addTreatmentRow(treatmentsRepeater, defaultRow);
					}
				});
			}

			// Validate on any change in treatment fields
			const runValidation = () => {
				if (typeof this.validateTreatmentsComplete === 'function') {
					this.validateTreatmentsComplete();
				}
			};
			treatmentsRepeater.addEventListener('change', (e) => {
				if (e.target.matches('.portal-treatment-select, .treatment-cost-input, .treatment-duration-input, .clinix-treatment-select')) {
					runValidation();
				}
			});
			treatmentsRepeater.addEventListener('input', (e) => {
				if (e.target.matches('.treatment-cost-input, .treatment-duration-input')) {
					runValidation();
				}
			});
		}

	/**
	 * Validate that all treatment rows have required fields filled. Enable/disable save button.
	 * Google: portal + cost + duration. Clinix: clinix + portal + cost + duration.
	 * @returns {boolean} true if all valid
	 */
	validateTreatmentsComplete() {
		const saveBtn = this.root.querySelector('.save-schedule-btn');
		const repeater = this.root.querySelector('.treatments-repeater');
		if (!repeater || !saveBtn) return false;
		const isClinix = repeater.classList.contains('is-clinix-flow');
		const rows = repeater.querySelectorAll('.treatment-row');
		let allValid = true;
		rows.forEach((row) => {
			const portalSelect = row.querySelector('.portal-treatment-select');
			const portalVal = portalSelect ? portalSelect.value : '';
			const costInput = row.querySelector('.treatment-cost-input');
			const durationInput = row.querySelector('.treatment-duration-input');
			const costOk = costInput && String(costInput.value).trim() !== '';
			const durationOk = durationInput && String(durationInput.value).trim() !== '';
			const clinixOk = !isClinix || (row.querySelector('.clinix-treatment-select') && row.querySelector('.clinix-treatment-select').value);
			if (!portalVal || !costOk || !durationOk || !clinixOk) {
				allValid = false;
			}
		});
		saveBtn.disabled = !allValid;
		return allValid;
	}

	/**
	 * Ensure all treatment rows have cost/duration (no-op: cost/duration are number inputs).
	 */
	ensureCostDurationOptionsForGoogleRows() {
		// Cost and duration are now single number inputs; nothing to populate.
	}

	/**
	 * Add a new treatment row by cloning the default row; add remove button and wire validation.
	 * @param {HTMLElement} container - .treatments-repeater
	 * @param {HTMLElement} defaultRow - .treatment-row-default
	 */
	addTreatmentRow(container, defaultRow) {
		const newRow = defaultRow.cloneNode(true);
		newRow.classList.remove('treatment-row-default');
		newRow.removeAttribute('data-is-default');
		const legend = newRow.querySelector('.treatment-row-legend');
		if (legend) legend.remove();
		const editableCount = container.querySelectorAll('.treatment-row:not(.treatment-row-default)').length;
		newRow.setAttribute('data-row-index', String(editableCount + 1));
		newRow.querySelectorAll('.select2-container').forEach((el) => el.remove());
		newRow.querySelectorAll('select').forEach((s) => {
			s.removeAttribute('data-select2-id');
			s.classList.remove('select2-hidden-accessible');
			s.removeAttribute('aria-hidden');
			s.removeAttribute('tabindex');
			s.selectedIndex = 0;
		});
		newRow.querySelector('.clinix-treatment-select').innerHTML = '<option value="">בחר טיפול Clinix</option>';
		newRow.querySelector('.portal-treatment-select').innerHTML = '<option value="">בחר סוג טיפול</option>';
		const costInput = newRow.querySelector('.treatment-cost-input');
		const durationInput = newRow.querySelector('.treatment-duration-input');
		if (costInput) costInput.value = '';
		if (durationInput) durationInput.value = '';
		const removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'remove-treatment-btn';
		removeBtn.setAttribute('aria-label', 'הסר טיפול');
		removeBtn.textContent = '×';
		removeBtn.style.cssText = 'margin-right:8px;';
		newRow.appendChild(removeBtn);
		removeBtn.addEventListener('click', () => {
			if (newRow && !newRow.classList.contains('treatment-row-default')) {
				newRow.remove();
				if (typeof this.validateTreatmentsComplete === 'function') {
					this.validateTreatmentsComplete();
				}
			}
		});
		container.appendChild(newRow);
		if (this.root.clinicReasons && newRow.querySelector('.clinix-treatment-select')) {
			const select = newRow.querySelector('.clinix-treatment-select');
			select.innerHTML = '<option value="">בחר טיפול Clinix</option>';
			this.root.clinicReasons.forEach((r) => {
				const opt = document.createElement('option');
				opt.value = r.drWebID;
				opt.textContent = r.name;
				select.appendChild(opt);
			});
		}
		const portalSelect = newRow.querySelector('.portal-treatment-select');
		if (portalSelect && this.root.querySelector('.treatment-row-default .portal-treatment-select')) {
			const defaultPortal = this.root.querySelector('.treatment-row-default .portal-treatment-select');
			portalSelect.innerHTML = defaultPortal.innerHTML;
		}
		this.reinitializeSelect2();
		if (typeof this.validateTreatmentsComplete === 'function') {
			this.validateTreatmentsComplete();
		}
	}

	/**
	 * Legacy: Populate treatments after clinic selection. Portal treatment types are now loaded in core.
	 * @param {number} clinicId - Selected clinic ID (unused; kept for API compatibility)
	 */
	async populateTreatmentCategories(clinicId) {
		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log('populateTreatmentCategories (no-op): treatment types loaded in core');
		}
		if (typeof this.validateTreatmentsComplete === 'function') {
			this.validateTreatmentsComplete();
		}
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

				const isTimeSelect = $select.hasClass('time-select');

		const select2Options = {
			theme: 'clinic-queue',
			dir: 'rtl',
			language: 'he',
			width: '100%',
			placeholder: $select.find('option:first').text(),
			allowClear: false,
			dropdownParent: $root,
			minimumResultsForSearch: Infinity, // ברירת מחדל: בלי חיפוש; cq-searchable מחליף ל-0
		// אפשרויות חיפוש inline — נקבעות ע"י הקובץ הגלובלי select2-inline-search.js
		...(window.ClinicQueueSelect2 ? window.ClinicQueueSelect2.getInlineSearchOptions($select) : {}),
		...(isTimeSelect && { dropdownCssClass: 'time-select-dropdown' })
	};

		$select.select2(select2Options);

		if (window.ClinicQueueSelect2) {
			window.ClinicQueueSelect2.setupInlineSearch($select, $root);
		}
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
			
			const isTimeSelect = $select.hasClass('time-select');

	const select2Options = {
		theme: 'clinic-queue',
		dir: 'rtl',
		language: 'he',
		width: '100%',
		placeholder: $select.find('option:first').text() || '',
		allowClear: false,
		dropdownParent: $root,
		escapeMarkup: (markup) => markup,
		minimumResultsForSearch: Infinity, // ברירת מחדל: בלי חיפוש; cq-searchable מחליף ל-0
		// אפשרויות חיפוש inline — נקבעות ע"י הקובץ הגלובלי select2-inline-search.js
		...(window.ClinicQueueSelect2 ? window.ClinicQueueSelect2.getInlineSearchOptions($select) : {}),
		...(isTimeSelect && { dropdownCssClass: 'time-select-dropdown' })
	};

		$select.select2(select2Options);

		if (window.ClinicQueueSelect2) {
			window.ClinicQueueSelect2.setupInlineSearch($select, $root);
		}
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

