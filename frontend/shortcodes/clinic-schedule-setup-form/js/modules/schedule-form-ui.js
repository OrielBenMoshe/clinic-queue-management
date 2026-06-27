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

			this._alertModalOnPrimary = null;
			this._bindAlertModalEvents();
		}

		/**
		 * Cache alert modal elements and wire open/close interactions.
		 */
		_bindAlertModalEvents() {
			this.alertModal = {
				overlay: this.root.querySelector('#schedule-form-alert-modal'),
				title: this.root.querySelector('#schedule-form-alert-modal-title'),
				body: this.root.querySelector('#schedule-form-alert-modal-body'),
				primary: this.root.querySelector('#schedule-form-alert-modal-primary'),
				secondary: this.root.querySelector('#schedule-form-alert-modal-secondary'),
			};

			if (!this.alertModal.overlay) {
				return;
			}

			this.alertModal.overlay.addEventListener('click', (event) => {
				if (event.target === this.alertModal.overlay) {
					this.closeAlertModal();
				}
			});

			if (this.alertModal.primary) {
				this.alertModal.primary.addEventListener('click', () => {
					const callback = this._alertModalOnPrimary;
					this.closeAlertModal();
					if (typeof callback === 'function') {
						callback();
					}
				});
			}

			if (this.alertModal.secondary) {
				this.alertModal.secondary.addEventListener('click', () => {
					this.closeAlertModal();
				});
			}

			this._alertModalEscapeHandler = (event) => {
				if (event.key !== 'Escape' || !this.alertModal.overlay || this.alertModal.overlay.hasAttribute('hidden')) {
					return;
				}
				this.closeAlertModal();
			};
			document.addEventListener('keydown', this._alertModalEscapeHandler);
		}

		/**
		 * Open a styled alert modal (replaces window.alert).
		 *
		 * @param {Object} options Modal content and actions
		 * @param {string} [options.title]
		 * @param {string} [options.body]
		 * @param {string} [options.primaryLabel]
		 * @param {Function|null} [options.onPrimary]
		 * @param {string} [options.secondaryLabel]
		 */
		showAlertModal(options = {}) {
			const title = options.title || 'שגיאה';
			const body = options.body || '';
			const primaryLabel = options.primaryLabel || 'הבנתי';
			const secondaryLabel = options.secondaryLabel || '';

			if (!this.alertModal || !this.alertModal.overlay) {
				window.alert(body ? (title + '\n\n' + body) : title);
				return;
			}

			if (this.alertModal.title) {
				this.alertModal.title.textContent = title;
			}
			if (this.alertModal.body) {
				this.alertModal.body.textContent = body;
			}
			if (this.alertModal.primary) {
				this.alertModal.primary.textContent = primaryLabel;
			}

			this._alertModalOnPrimary = typeof options.onPrimary === 'function' ? options.onPrimary : null;

			if (this.alertModal.secondary) {
				if (secondaryLabel) {
					this.alertModal.secondary.textContent = secondaryLabel;
					this.alertModal.secondary.removeAttribute('hidden');
				} else {
					this.alertModal.secondary.setAttribute('hidden', '');
				}
			}

			this.alertModal.overlay.removeAttribute('hidden');
			if (this.alertModal.primary) {
				this.alertModal.primary.focus();
			}
		}

		/**
		 * Close the alert modal.
		 */
		closeAlertModal() {
			if (!this.alertModal || !this.alertModal.overlay) {
				return;
			}
			this.alertModal.overlay.setAttribute('hidden', '');
			this._alertModalOnPrimary = null;
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
				const $clinicSelect = typeof jQuery !== 'undefined' && clinicSelect ? jQuery(clinicSelect) : null;
				const hasClinic = $clinicSelect
					? ($clinicSelect.val() && String($clinicSelect.val()).trim() !== '')
					: !!(clinicSelect && clinicSelect.value && String(clinicSelect.value).trim() !== '');
				const $doctorSelect = typeof jQuery !== 'undefined' && doctorSelect ? jQuery(doctorSelect) : null;
				const hasDoctor = $doctorSelect ? ($doctorSelect.val() && $doctorSelect.val() !== '') : (doctorSelect && doctorSelect.value);
				const hasManual = manualScheduleName && manualScheduleName.value.trim().length > 0;

			// Update doctor select disabled state
			if (doctorSelect) {
				// שדה רופא מופעל רק אם יש מרפאה, אין שם יומן ידני, ויש רופאים נטענים (יותר מאפשרות ריקה אחת)
				const noDoctorsLoaded = doctorSelect.options.length <= 1;
				const shouldDisableDoctor = hasManual || !hasClinic || noDoctorsLoaded;
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
					googleNextBtn.disabled = !(hasClinic && (hasDoctor || hasManual));
				}
			};

			if (clinicSelect) {
				clinicSelect.addEventListener('change', syncGoogleStep);
				if (typeof jQuery !== 'undefined') {
					jQuery(clinicSelect).on('select2:select select2:clear', syncGoogleStep);
				}
			}

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
		
		// Reinitialize Select2 for new time selects only (not the entire form)
		this.reinitializeSelect2(newRow);
		
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
		// jQuery's .trigger('change') (used by Select2) does not always fire native addEventListener handlers.
		// Listen directly to Select2 events to ensure validation runs after portal/clinix treatment selection.
		if (typeof jQuery !== 'undefined') {
			jQuery(treatmentsRepeater).on('select2:select select2:clear', function(e) {
				if (jQuery(e.target).is('.portal-treatment-select, .clinix-treatment-select')) {
					runValidation();
				}
			});
		}
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
			// קריאת ה-value דרך jQuery אם זמין (בגלל Select2)
			let portalVal = '';
			if (portalSelect) {
				if (typeof jQuery !== 'undefined') {
					portalVal = jQuery(portalSelect).val() || '';
				} else {
					portalVal = portalSelect.value || '';
				}
			}
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
		removeBtn.innerHTML = (window.scheduleFormData && window.scheduleFormData.trashIcon) ? window.scheduleFormData.trashIcon : '×';
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
		this.reinitializeSelect2(newRow);
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
		 * Show a simple error message in the alert modal.
		 *
		 * @param {string} message Plain-text message
		 */
		showError(message) {
			const text = String(message || 'שגיאה לא ידועה')
				.replace(/<br\s*\/?>/gi, '\n')
				.replace(/<[^>]+>/g, '')
				.trim();

			this.showAlertModal({
				title: 'שגיאה',
				body: text,
				primaryLabel: 'הבנתי',
			});
		}

		/**
		 * Scroll container for schedule-settings step (days + treatments).
		 *
		 * @returns {HTMLElement|null}
		 */
		getScheduleSettingsScrollContainer() {
			return this.root.querySelector('.schedule-settings-scroll-content');
		}

		/**
		 * Reset scroll position to top when entering schedule-settings.
		 * Uses double rAF so layout (flex + Select2) is settled before applying.
		 *
		 * @param {HTMLElement|null} scrollEl Optional scroll container
		 */
		resetScheduleSettingsScroll(scrollEl) {
			const container = scrollEl || this.getScheduleSettingsScrollContainer();
			if (!container) {
				return;
			}

			const applyScrollTop = () => {
				container.scrollTop = 0;
			};

			applyScrollTop();
			if (typeof requestAnimationFrame === 'function') {
				requestAnimationFrame(() => {
					requestAnimationFrame(applyScrollTop);
				});
			}
		}

		/**
		 * Resolve Select2 dropdownParent for a field.
		 * Fields inside the scrollable schedule-settings area use that container
		 * so dropdown positioning does not hijack scroll on the whole form.
		 *
		 * @param {HTMLElement} element Native select element
		 * @returns {jQuery}
		 */
		getSelect2DropdownParent(element) {
			const $root = jQuery(this.root);
			const scrollContent = element && element.closest('.schedule-settings-scroll-content');
			if (scrollContent) {
				return jQuery(scrollContent);
			}
			return $root;
		}

		/**
		 * Build shared Select2 options for a select field.
		 *
		 * @param {jQuery} $select jQuery-wrapped select
		 * @param {Object} extraOptions Additional Select2 options
		 * @returns {Object}
		 */
		buildSelect2Options($select, extraOptions = {}) {
			const element = $select[0];
			const isTimeSelect = $select.hasClass('time-select');
			const isDoctorSelect = $select.hasClass('doctor-select');

			return {
				theme: 'clinic-queue',
				dir: 'rtl',
				language: 'he',
				width: '100%',
				placeholder: $select.find('option:first').text() || '',
				allowClear: false,
				dropdownParent: this.getSelect2DropdownParent(element),
				escapeMarkup: (markup) => markup,
				minimumResultsForSearch: Infinity,
				...(isDoctorSelect
					? { minimumResultsForSearch: 0, dropdownCssClass: 'clinic-queue-doctor-dropdown' }
					: window.ClinicQueueSelect2 ? window.ClinicQueueSelect2.getInlineSearchOptions($select) : {}),
				...(isTimeSelect && { dropdownCssClass: 'time-select-dropdown' }),
				...extraOptions
			};
		}

		/**
		 * Bind Select2 to select fields within a scope.
		 *
		 * @param {HTMLElement|jQuery|null} scope Container to search within (defaults to form root)
		 * @param {boolean} forceReinit Destroy existing instances before re-binding
		 */
		bindSelect2Fields(scope, forceReinit) {
			if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
				if (!forceReinit && window.ScheduleFormUtils) {
					window.ScheduleFormUtils.warn('Select2 is not loaded, skipping initialization');
				}
				return;
			}

			const $scope = scope ? jQuery(scope) : jQuery(this.root);

			$scope.find('.select-field').each((index, element) => {
				const $select = jQuery(element);

				// שדה הרופא מנוהל ע"י FieldManager עם templateResult — לא לאפס אותו כאן
				if ($select.hasClass('doctor-select')) {
					return;
				}

				if ($select.hasClass('select2-hidden-accessible')) {
					if (!forceReinit) {
						return;
					}
					$select.select2('destroy');
				}

				$select.select2(this.buildSelect2Options($select));

				if (window.ClinicQueueSelect2) {
					window.ClinicQueueSelect2.setupInlineSearch($select, this.getSelect2DropdownParent(element));
				}
			});
		}

		/**
		 * Initialize Select2 for all select fields
		 */
		initializeSelect2() {
			this.bindSelect2Fields(null, false);
		}

		/**
		 * Reinitialize Select2 after dynamic content changes.
		 * Pass an optional scope element to avoid reinitializing hidden-step fields
		 * (which can force the schedule-settings scroller to the bottom).
		 *
		 * @param {HTMLElement|null} scope Optional container to limit reinit scope
		 */
		reinitializeSelect2(scope) {
			this.bindSelect2Fields(scope, true);
		}

		/**
		 * Initialize floating labels for text fields (MUI style)
		 * Label moves from center (default) to top (focused/has value)
		 */
		initializeFloatingLabels() {
			if (window.ClinicQueueFloatingLabels && typeof window.ClinicQueueFloatingLabels.init === 'function') {
				window.ClinicQueueFloatingLabels.init(this.root);
				this.setupFloatingLabel = window.ClinicQueueFloatingLabels.setupOne;
				return;
			}
		}

}

	// Export to global scope
	window.ScheduleFormUIManager = ScheduleFormUIManager;

})(window);

