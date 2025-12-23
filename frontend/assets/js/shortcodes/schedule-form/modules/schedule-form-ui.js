/**
 * Schedule Form UI Module
 * Handles UI interactions and repeaters
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	console.log('[ScheduleForm UI] Module loaded - version 0.2.33');

	/**
	 * Schedule Form UI Manager
	 */
	class ScheduleFormUIManager {
		constructor(rootElement) {
			this.root = rootElement;
			
			// Placeholder texts for doctor field - single source of truth
			this.doctorPlaceholders = {
				default: 'חיפוש רופא לפי שם או מספר רישוי',
				loading: 'טוען רופאים...',
				noDoctors: 'לא נמצאו רופאים למרפאה זו',
				error: 'שגיאה בטעינת רופאים'
			};
		}

	/**
	 * Setup action card selection (Step 1)
	 */
	setupActionCards(onSelectCallback) {
		console.log('[ScheduleForm UI] Setting up action cards');
		const cards = this.root.querySelectorAll('.action-card');
		const button = this.root.querySelector('.continue-btn');
		
		console.log('[ScheduleForm UI] Found', cards.length, 'action cards');
		console.log('[ScheduleForm UI] Continue button:', button);

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
				console.log('[ScheduleForm UI] Card clicked, value:', value);
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
				console.log('[ScheduleForm UI] Continue button clicked, selected value:', value);
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
				const $doctorSelect = typeof jQuery !== 'undefined' && doctorSelect ? jQuery(doctorSelect) : null;
				const hasDoctor = $doctorSelect ? ($doctorSelect.val() && $doctorSelect.val() !== '') : (doctorSelect && doctorSelect.value);
				const hasManual = manualCalendar && manualCalendar.value.trim().length > 0;

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
					}
					
					// Update field-disabled class for doctor field
					const doctorField = this.root.querySelector('.doctor-search-field');
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
					clinicSelect.disabled = hasManual;
					
					// Update Select2 disabled state if initialized
					if ($clinicSelect && $clinicSelect.hasClass('select2-hidden-accessible')) {
						if (hasManual) {
							$clinicSelect.prop('disabled', true);
						} else {
							$clinicSelect.prop('disabled', false);
						}
					}
					
					// Update field-disabled class for clinic field
					const clinicField = this.root.querySelector('.clinic-select-field');
					if (clinicField) {
						if (hasManual) {
							clinicField.classList.add('field-disabled');
						} else {
							clinicField.classList.remove('field-disabled');
						}
					}
				}

				// Update manual calendar disabled state
				if (manualCalendar) {
					manualCalendar.disabled = hasDoctor;
					
					// Update field-disabled class for manual calendar field
					const manualCalendarField = manualCalendar.closest('.jet-form-builder__row');
					if (manualCalendarField) {
						if (hasDoctor) {
							manualCalendarField.classList.add('field-disabled');
						} else {
							manualCalendarField.classList.remove('field-disabled');
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
					
					// Enable all options first
					fromSelect.querySelectorAll('option').forEach(opt => opt.disabled = false);
					toSelect.querySelectorAll('option').forEach(opt => opt.disabled = false);
					
					// Enforce start >= prevEnd
					if (prevEndMinutes !== null) {
						fromSelect.querySelectorAll('option').forEach(opt => {
							if (toMinutes(opt.value) < prevEndMinutes) {
								opt.disabled = true;
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
				
				// Enable all options first
				fromSelect.querySelectorAll('option').forEach(opt => opt.disabled = false);
				toSelect.querySelectorAll('option').forEach(opt => opt.disabled = false);
				
				// Enforce start >= prevEnd
				if (prevEndMinutes !== null) {
					fromSelect.querySelectorAll('option').forEach(opt => {
						if (toMinutes(opt.value) < prevEndMinutes) {
							opt.disabled = true;
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
		
		// Remove cloned Select2 containers (they'll be recreated)
		newRow.querySelectorAll('.select2-container').forEach(container => container.remove());
		newRow.querySelectorAll('.select-field').forEach(select => {
			select.classList.remove('select2-hidden-accessible');
			select.removeAttribute('data-select2-id');
			select.removeAttribute('aria-hidden');
			select.removeAttribute('tabindex');
		});
		
		// Clear values and update IDs
		newRow.querySelectorAll('input').forEach(input => {
			input.value = '';
			// Update IDs if present
			if (input.id) {
				const newId = input.id + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
				input.id = newId;
			}
		});
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
		
		// Reinitialize Select2 for new treatment selects
		this.reinitializeSelect2();
		
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

			const hasClinics = clinics && clinics.length > 0;
			const $clinicSelect = typeof jQuery !== 'undefined' ? jQuery(clinicSelect) : null;
			
			// Clear and add default option
			if ($clinicSelect) {
				$clinicSelect.empty().append('<option value=""></option>');
			} else {
				clinicSelect.innerHTML = '<option value=""></option>';
			}

			// Add clinic options first
			if (hasClinics) {
				clinics.forEach(clinic => {
					const option = document.createElement('option');
					option.value = clinic.id;
					option.textContent = clinic.title.rendered || clinic.title || clinic.name;
					if ($clinicSelect) {
						$clinicSelect.append(option);
					} else {
						clinicSelect.appendChild(option);
					}
				});
				
				if ($clinicSelect) {
					$clinicSelect.prop('disabled', false);
				} else {
					clinicSelect.disabled = false;
				}
			} else {
				if ($clinicSelect) {
					$clinicSelect.prop('disabled', true);
				} else {
					clinicSelect.disabled = true;
				}
			}

			// Update rendered text after adding options
			const clinicRendered = this.root.querySelector('.clinic-select-field .select2-container--clinic-queue .select2-selection--single .select2-selection__rendered');
			if (clinicRendered) {
				// Trigger Select2 update first if initialized
				if ($clinicSelect && $clinicSelect.hasClass('select2-hidden-accessible')) {
					$clinicSelect.trigger('change.select2');
				}
				
				// Update text after Select2 has updated (use setTimeout to ensure it happens after)
				setTimeout(() => {
					const clinicRenderedAfter = this.root.querySelector('.clinic-select-field .select2-container--clinic-queue .select2-selection--single .select2-selection__rendered');
					if (clinicRenderedAfter && (!$clinicSelect || !$clinicSelect.val() || $clinicSelect.val() === '')) {
						if (hasClinics) {
							// Show "מרפאות" when there are clinics
							clinicRenderedAfter.setAttribute('title', '');
							clinicRenderedAfter.setAttribute('data-placeholder', 'מרפאות');
							clinicRenderedAfter.innerHTML = '<span class="select2-selection__placeholder">מרפאות</span>';
						} else {
							// Show "לא נמצאו מרפאות" when no clinics
							clinicRenderedAfter.setAttribute('title', '');
							clinicRenderedAfter.setAttribute('data-placeholder', 'לא נמצאו מרפאות');
							clinicRenderedAfter.innerHTML = '<span class="select2-selection__placeholder">לא נמצאו מרפאות</span>';
						}
					}
				}, 0);
			}
		}

		/**
		 * Update doctor field placeholder text
		 * @param {string} state - One of: 'default', 'loading', 'noDoctors', 'error'
		 * @param {boolean} force - Force update even if field has value
		 */
		updateDoctorPlaceholder(state = 'default', force = false) {
			const placeholderText = this.doctorPlaceholders[state] || this.doctorPlaceholders.default;
			const $doctorSelect = typeof jQuery !== 'undefined' ? jQuery(this.root.querySelector('.doctor-select')) : null;
			const hasValue = $doctorSelect && $doctorSelect.val() && $doctorSelect.val() !== '';
			
			// Only update if field is empty (unless forced)
			if (hasValue && !force) {
				return;
			}
			
			// Function to actually update the placeholder
			const doUpdate = () => {
				const doctorRendered = this.root.querySelector('.doctor-search-field .select2-container--clinic-queue .select2-selection--single .select2-selection__rendered');
				if (doctorRendered) {
					// Only update if field is still empty (unless forced)
					if (force || !$doctorSelect || !$doctorSelect.val() || $doctorSelect.val() === '') {
						doctorRendered.setAttribute('title', '');
						doctorRendered.setAttribute('data-placeholder', placeholderText);
						doctorRendered.innerHTML = '<span class="select2-selection__placeholder">' + placeholderText + '</span>';
					}
				}
			};
			
			// Try to update immediately
			doUpdate();
			
			// If Select2 is initialized, also try after a short delay to ensure DOM is updated
			if ($doctorSelect && $doctorSelect.hasClass('select2-hidden-accessible')) {
				setTimeout(doUpdate, 10);
				setTimeout(doUpdate, 50);
			}
		}

		/**
		 * Populate doctor select
		 */
		populateDoctorSelect(doctors, doctorSelect, dataManager) {
			if (!doctorSelect) return;

			const $doctorSelect = typeof jQuery !== 'undefined' ? jQuery(doctorSelect) : null;
			const hasDoctors = doctors && doctors.length > 0;
			
			// Clear and add default option
			if ($doctorSelect) {
				$doctorSelect.empty().append('<option value=""></option>');
			} else {
				doctorSelect.innerHTML = '<option value=""></option>';
			}

			// Add doctor options first
			if (hasDoctors) {
				doctors.forEach(doctor => {
					const option = document.createElement('option');
					option.value = doctor.id;
					option.textContent = dataManager.getDoctorName(doctor);
					
					// Store additional data in data attributes
					// Check if methods exist (for backward compatibility)
					const licenseNumber = (typeof dataManager.getDoctorLicenseNumber === 'function') 
						? dataManager.getDoctorLicenseNumber(doctor) 
						: (doctor.license_number || (doctor.meta && doctor.meta.license_number) || '');
					const thumbnail = (typeof dataManager.getDoctorThumbnail === 'function')
						? dataManager.getDoctorThumbnail(doctor)
						: (doctor.thumbnail || (doctor._embedded && doctor._embedded['wp:featuredmedia'] && doctor._embedded['wp:featuredmedia'][0] && doctor._embedded['wp:featuredmedia'][0].source_url) || '');
					
					if (licenseNumber) {
						option.setAttribute('data-license-number', licenseNumber);
					}
					if (thumbnail) {
						option.setAttribute('data-thumbnail', thumbnail);
					}
					
					// Store doctor name for template
					option.setAttribute('data-doctor-name', dataManager.getDoctorName(doctor));
					
					if ($doctorSelect) {
						$doctorSelect.append(option);
					} else {
						doctorSelect.appendChild(option);
					}
				});
				
				if ($doctorSelect) {
					$doctorSelect.prop('disabled', false);
				} else {
					doctorSelect.disabled = false;
				}
			} else {
				// Keep disabled when no doctors
				if ($doctorSelect) {
					$doctorSelect.prop('disabled', true);
				} else {
					doctorSelect.disabled = true;
				}
			}

			// Update field disabled class for styling
			const doctorField = this.root.querySelector('.doctor-search-field');
			if (hasDoctors) {
				// Remove disabled class when field is enabled
				if (doctorField) {
					doctorField.classList.remove('field-disabled');
				}
			} else {
				// Add disabled class for styling when no doctors
				if (doctorField) {
					doctorField.classList.add('field-disabled');
				}
			}

			// Update Select2 after adding options
			if ($doctorSelect && $doctorSelect.hasClass('select2-hidden-accessible')) {
				// Ensure Select2 disabled state is updated before triggering change
				$doctorSelect.prop('disabled', !hasDoctors);
				$doctorSelect.trigger('change.select2');
			}
			
			// Update placeholder based on state - use multiple timeouts to ensure it happens after Select2 updates
			const state = hasDoctors ? 'default' : 'noDoctors';
			this.updateDoctorPlaceholder(state, false);
			setTimeout(() => {
				this.updateDoctorPlaceholder(state, false);
			}, 10);
			setTimeout(() => {
				this.updateDoctorPlaceholder(state, false);
			}, 50);
		}

	/**
	 * Populate subspeciality selects with hierarchical structure
	 * Parents are disabled (headers), children are selectable
	 */
	populateSubspecialitySelects(specialities) {
		const subspecialitySelects = this.root.querySelectorAll('.subspeciality-select');

		subspecialitySelects.forEach(select => {
			select.innerHTML = '<option value="">בחר תת-תחום</option>';
			
			if (specialities && specialities.length > 0) {
				specialities.forEach(term => {
					const option = document.createElement('option');
					option.value = term.id;
					option.textContent = term.name;
					
					// Disable parent options (they serve as headers)
					if (term.disabled) {
						option.disabled = true;
						option.style.fontWeight = '700';
						option.style.color = '#0c1c4a';
						option.style.backgroundColor = '#f5f5f5';
					}
					
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

		/**
		 * Initialize Select2 for all select fields
		 */
		initializeSelect2() {
			// Check if Select2 is available
			if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
				console.warn('[ScheduleForm] Select2 is not loaded, skipping initialization');
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

				// Check if this is the doctor search field
				const isDoctorSearch = $select.hasClass('doctor-select') || $select.closest('.doctor-search-field').length > 0;
				
				// Check if this is a time select field
				const isTimeSelect = $select.hasClass('time-select');
				
				// Prepare Select2 options
				const select2Options = {
					theme: 'clinic-queue',
					dir: 'rtl',
					language: 'he',
					width: '100%',
					minimumResultsForSearch: isDoctorSearch ? 0 : -1, // Enable search only for doctor field
					placeholder: isDoctorSearch ? this.doctorPlaceholders.default : $select.find('option:first').text(),
					allowClear: isDoctorSearch, // Allow clear only for doctor field
					dropdownParent: isDoctorSearch ? $select.closest('.jet-form-builder__field-wrap') : $root
				};
				
				// Add custom templates for doctor search field
				if (isDoctorSearch) {
					select2Options.templateResult = (data) => {
						if (!data || !data.element) {
							return data.text;
						}
						
						const $element = jQuery(data.element);
						const doctorName = $element.attr('data-doctor-name') || data.text;
						const licenseNumber = $element.attr('data-license-number') || '';
						const thumbnail = $element.attr('data-thumbnail') || '';
						
						// Create doctor card HTML
						const $result = jQuery('<div class="clinic-queue-doctor-result"></div>');
						
						// Doctor info section
						const $info = jQuery('<div class="clinic-queue-doctor-info"></div>');
						
						// Doctor name
						const $name = jQuery('<p class="clinic-queue-doctor-name"></p>').text(doctorName);
						$info.append($name);
						
						// License number
						if (licenseNumber) {
							const $license = jQuery('<p class="clinic-queue-doctor-license"></p>').text(licenseNumber);
							$info.append($license);
						}
						
						$result.append($info);
						
						// Thumbnail
						if (thumbnail) {
							const $thumb = jQuery('<div class="clinic-queue-doctor-thumbnail"></div>');
							const $img = jQuery('<img>').attr('src', thumbnail).attr('alt', doctorName);
							$thumb.append($img);
							$result.append($thumb);
						}
						
						return $result;
					};
					
					select2Options.templateSelection = (data) => {
						if (!data || !data.element) {
							return data.text;
						}
						
						const $element = jQuery(data.element);
						const doctorName = $element.attr('data-doctor-name') || data.text;
						
						// For selected item, show just the name
						return doctorName;
					};
				}
				
				$select.select2(select2Options);
				
				// For doctor search field, update placeholder styling
				if (isDoctorSearch) {
					const $container = $select.next('.select2-container--clinic-queue');
					const $rendered = $container.find('.select2-selection__rendered');
					
					// Update placeholder when empty
					$select.on('select2:select select2:clear', function() {
						const value = $select.val();
						if (!value || value === '') {
							$rendered.attr('title', '');
						}
					});
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
			
			// Initialize Select2 instance (both new and re-initialized)
			const isDoctorSearch = $select.hasClass('doctor-select') || $select.closest('.doctor-search-field').length > 0;
			const isTimeSelect = $select.hasClass('time-select');
			
			// Prepare Select2 options
			const select2Options = {
				theme: 'clinic-queue',
				dir: 'rtl',
				language: 'he',
				width: '100%',
				minimumResultsForSearch: isDoctorSearch ? 0 : -1, // Enable search only for doctor field
				placeholder: isDoctorSearch ? this.doctorPlaceholders.default : ($select.find('option:first').text() || ''),
				allowClear: isDoctorSearch, // Allow clear only for doctor field
				dropdownParent: isDoctorSearch ? $select.closest('.jet-form-builder__field-wrap') : $root,
				escapeMarkup: (markup) => markup
			};
			
			// Add custom templates for doctor search field
			if (isDoctorSearch) {
				select2Options.templateResult = (data) => {
					if (!data || !data.element) {
						return data.text;
					}
					
					const $element = jQuery(data.element);
					const doctorName = $element.attr('data-doctor-name') || data.text;
					const licenseNumber = $element.attr('data-license-number') || '';
					const thumbnail = $element.attr('data-thumbnail') || '';
					
					// Create doctor card HTML
					const $result = jQuery('<div class="clinic-queue-doctor-result"></div>');
					
					// Thumbnail (First)
					if (thumbnail) {
						const $thumb = jQuery('<div class="clinic-queue-doctor-thumbnail"></div>');
						const $img = jQuery('<img>').attr('src', thumbnail).attr('alt', doctorName);
						$thumb.append($img);
						$result.append($thumb);
					}
					
					// Doctor info section
					const $info = jQuery('<div class="clinic-queue-doctor-info"></div>');
					
					// Doctor name
					const $name = jQuery('<b class="clinic-queue-doctor-name"></b>').text(doctorName);
					$info.append($name);
					
					// License number
					if (licenseNumber) {
						const $license = jQuery('<p class="clinic-queue-doctor-license"></p>').text(licenseNumber);
						$info.append($license);
					}
					
					$result.append($info);
					
					return $result;
				};
				
				select2Options.templateSelection = (data) => {
					if (!data || !data.element) {
						return data.text;
					}
					
					const $element = jQuery(data.element);
					const doctorName = $element.attr('data-doctor-name') || data.text;
					const thumbnail = $element.attr('data-thumbnail') || '';
					
					// For selected item, show name + thumbnail (compact)
					const thumbHtml = thumbnail 
						? '<span class="clinic-queue-doctor-selection__thumb"><img src="' + thumbnail + '" alt="' + doctorName + '" /></span>'
						: '';
					const nameHtml = '<span class="clinic-queue-doctor-selection__name">' + doctorName + '</span>';
					
					return '<span class="clinic-queue-doctor-selection">' + thumbHtml + nameHtml + '</span>';
				};
			}
			
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

