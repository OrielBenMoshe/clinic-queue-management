/**
 * Clinic Queue Management - Expanded Modal Module
 *
 * "All appointments" modal: expanded list of available slots by date with filters.
 *
 * Architecture:
 *   - HTML skeleton: views/booking-calendar-expanded-modal.php
 *     Rendered once in wp_footer (singleton) under <body id="bcm-expanded-modal">.
 *   - JS: show/hide, populate selects, filter, render slots.
 *   - Each open syncs from the core instance that opened it (multiple calendars per page).
 *
 * Core integration:
 *   core.appointmentData
 *   core.allSchedulers
 *   core.treatmentType / core.schedulerId
 *   core.selectedDate
 *   core.uiManager.formatTimeForDisplay()
 *   core.dataManager.filterSchedulersByTreatment()
 *   core.handleBookButtonClick()
 */
(function ($) {
    'use strict';

    const MODAL_SELECTOR = '#bcm-expanded-modal';
    const DAYS_PER_PAGE  = Number.MAX_SAFE_INTEGER;
    const MAX_RANGE_DAYS = 21; // max 3-week date range
    const MS_PER_DAY     = 24 * 60 * 60 * 1000;
    const UPDATE_COOLDOWN_MS = 2 * 60 * 1000; // cooldown per identical server-field snapshot
    const AUTO_REFETCH_DEBOUNCE_MS = 300; // debounce auto server refetch on treatment/scheduler change

    class BookingCalendarExpandedModal {

        /**
         * @param {BookingCalendarCore} core Calendar instance that opened the modal
         */
        constructor(core) {
            this.core   = core;
            this.$modal = null;
            this._rangeNoticeTimer = null;
            this._updateCooldownTimer = null;
            this._autoRefetchTimer = null;
            this._isUpdateLoading = false;
            /** True during open() until snapshot is captured; suppresses dirty state from programmatic change events. */
            this._isInitializing = false;
            this.lastRequestedDateRangeKey = '';
            this.lastRequestedDateRangeAt = 0;

            /** Snapshot of treatmentType, schedulerId, fromDate, toDate for update-button dirty state. */
            this._serverFieldsSnapshot = '';

            this.filterState = {
                treatmentType : '',
                schedulerId   : '',
                fromDate      : '',
                toDate        : '',
                fromTime      : '',
                toTime        : '',
                days          : ['0', '1', '2', '3', '4', '5', '6'],
            };

            this.selectedDate     = null;
            this.selectedTime     = null;
            this.visibleDaysCount = DAYS_PER_PAGE;

            // שומר על כפתור "חזור" במובייל: לחיצה על "חזור" כשהמודל פתוח תסגור אותו במקום לנווט אחורה.
            this._backGuard = window.BookingCalendarUtils.createMobileBackGuard({
                onBack: () => this.close(),
                label: 'expanded-modal',
            });
        }

        /**
         * Opens the modal and hydrates it from the current core state (singleton in DOM).
         */
        open() {
            this.$modal = $(MODAL_SELECTOR);

            if (!this.$modal.length) {
                window.BookingCalendarUtils.error('Expanded modal element not found in DOM (#bcm-expanded-modal)');
                return;
            }

            this._isInitializing = true;

            this.destroySelect2InModal();
            this.initFromCore();
            this.setDateDefaults();
            this.bindEvents();
            this.populateSelects();
            this.initializeSelect2InModal();
            this.renderResults();
            this.captureServerFieldsSnapshot();
            this._isInitializing = false;

            this.$modal.attr('aria-hidden', 'false');

            // ודא שה-overlay ישירות תחת body – מונע שבירת position:fixed בגלל transform של הורה.
            const modalEl = this.$modal[0];
            if (modalEl && modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
            }

            // Two rAF ticks: first applies display:flex, second starts the transition
            requestAnimationFrame(() => {
                this.$modal.addClass('bcm-open');
                requestAnimationFrame(() => {
                    this.$modal.find('.bcm-dialog').addClass('bcm-dialog--visible');
                });
            });

            window.BookingCalendarUtils.lockBodyScroll();
            this._backGuard.push();
            window.BookingCalendarUtils.log('Expanded modal opened by widget:', this.core.widgetId);
        }

        /** Closes the modal (kept in DOM for reuse). */
        close() {
            if (!this.$modal || !this.$modal.length) return;

            if (this._autoRefetchTimer) {
                clearTimeout(this._autoRefetchTimer);
                this._autoRefetchTimer = null;
            }

            this.$modal.removeClass('bcm-open');
            this.$modal.find('.bcm-dialog').removeClass('bcm-dialog--visible');
            this.$modal.attr('aria-hidden', 'true');

            window.BookingCalendarUtils.unlockBodyScroll();
            $(document).off('keydown.bcm-modal touchmove.bcm-modal touchend.bcm-modal touchcancel.bcm-modal');

            // ניקוי רשומת ההיסטוריה שנדחפה (no-op אם הסגירה הגיעה מלחיצת "חזור").
            this._backGuard.release();

            window.BookingCalendarUtils.log('Expanded modal closed');
        }

        /** Initializes filterState from the opening core instance. */
        initFromCore() {
            this.filterState.treatmentType = this.core.treatmentType || '';
            this.filterState.schedulerId   = String(this.core.schedulerId || '');
            this.filterState.days          = ['0', '1', '2', '3', '4', '5', '6'];

            this.selectedDate     = this.core.selectedDate || null;
            this.selectedTime     = null;
            this.visibleDaysCount = DAYS_PER_PAGE;

            window.BookingCalendarUtils.log('Expanded modal init from core:', {
                widgetId      : this.core.widgetId,
                treatmentType : this.filterState.treatmentType,
                schedulerId   : this.filterState.schedulerId,
                selectedDate  : this.selectedDate,
            });
        }

        /**
         * Sets default date filter values and syncs filterState.
         * Time filters always start empty on open (optional client-side filters only).
         */
        setDateDefaults() {
            const todayIso = this.getTodayIsoDate();
            let fromDate = '';
            let toDate = '';

            const latestRequest = this.core.lastFreeTimeRequest || null;
            if (latestRequest && latestRequest.fromDateUTC && latestRequest.toDateUTC) {
                const parsedFrom = this.parseUtcRangeValue(latestRequest.fromDateUTC);
                const parsedTo = this.parseUtcRangeValue(latestRequest.toDateUTC);
                fromDate = parsedFrom.date;
                toDate = parsedTo.date;
            } else {
                const today = new Date();
                const endRange = new Date(today);
                endRange.setDate(endRange.getDate() + MAX_RANGE_DAYS);
                fromDate = window.BookingCalendarUtils.formatDate(today);
                toDate   = window.BookingCalendarUtils.formatDate(endRange);
            }

            this.filterState.fromDate = fromDate;
            this.filterState.toDate   = toDate;

            const $fromDateInput = this.$modal.find('[data-filter="fromDate"]');
            $fromDateInput.attr('min', todayIso);

            // Hard clamp: never allow a date earlier than today.
            if (fromDate && fromDate < todayIso) {
                fromDate = todayIso;
                this.filterState.fromDate = todayIso;
            }

            $fromDateInput.val(fromDate);
            this.$modal.find('[data-filter="toDate"]').val(toDate);
            this.resetTimeFilters();
            this.$modal.find('.bcm-day-cb').prop('checked', true);
            this.updateDateInputsBounds();
            this.updateFieldPlaceholderState();
        }

        /**
         * Resets the time filters to empty: state, native inputs, custom shell display
         * and the clear-button state. Single source of truth so every open path
         * (desktop and mobile alike) starts with clean time fields.
         */
        resetTimeFilters() {
            this.filterState.fromTime = '';
            this.filterState.toTime   = '';

            if (!this.$modal || !this.$modal.length) {
                return;
            }

            ['fromTime', 'toTime'].forEach(filterName => {
                const $input = this.$modal.find(`[data-filter="${filterName}"]`);
                if (!$input.length) {
                    return;
                }

                // Clear via the native property too: some mobile browsers keep a
                // stale value on <input type="time"> after a jQuery .val('') alone.
                const inputEl = $input.get(0);
                if (inputEl) {
                    inputEl.value = '';
                }

                this.syncNativeFieldDisplay($input);
            });
        }

        populateSelects() {
            this.populateTreatmentSelect();
            this.populateSchedulerSelect();
        }

        /** Populates treatment types (same logic as field-manager). */
        populateTreatmentSelect() {
            const $select = this.$modal.find('[data-filter="treatmentType"]');
            const map     = new Map();

            this.getSchedulersArray().forEach(s => {
                (s.treatments || []).forEach(t => {
                    const id = String(t.treatment_type || '').trim();
                    if (!id || map.has(id)) return;
                    map.set(id, (t.treatment_type_name || '').trim() || id);
                });
            });

            $select.find('option:not([value=""])').remove();

            Array.from(map.entries())
                .sort((a, b) => a[1].localeCompare(b[1]))
                .forEach(([id, name]) => $select.append($('<option>', { value: id, text: name })));

            if (this.filterState.treatmentType) {
                $select.val(this.filterState.treatmentType);
            }
        }

        /**
         * Populates schedulers/doctors for the current treatment type.
         *
         * @param {jQuery} [$select] Defaults to the modal scheduler select
         */
        populateSchedulerSelect($select) {
            $select = $select || this.$modal.find('[data-filter="schedulerId"]');
            $select.find('option:not([value=""])').remove();

            const schedulers = this.getSchedulersArray();
            const filtered   = this.filterState.treatmentType
                ? this.core.dataManager.filterSchedulersByTreatment(schedulers, this.filterState.treatmentType)
                : schedulers;

            filtered.forEach(s => {
                const label = s.doctor_name || s.schedule_name || s.manual_calendar_name || `יומן #${s.id}`;
                $select.append($('<option>', { value: String(s.id), text: label }));
            });

            const currentId = String(this.filterState.schedulerId || '');
            const isCurrentValid = currentId && filtered.some(s => String(s.id) === currentId);
            const targetId = isCurrentValid
                ? currentId
                : (filtered.length ? String(filtered[0].id) : '');

            this.filterState.schedulerId = targetId;
            $select.val(targetId);

            window.BookingCalendarUtils.log('Expanded modal schedulers populated:', filtered.length, 'selected:', targetId);
        }

        /** Destroys Select2 on modal selects before repopulating or closing. */
        destroySelect2InModal() {
            const $m = this.$modal;
            $m.find('[data-filter="treatmentType"], [data-filter="schedulerId"]').each(function () {
                const $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
            });
        }

        /** Initializes Select2 on modal selects; dropdownParent is the modal to avoid clipping. */
        initializeSelect2InModal() {
            if (typeof $.fn.select2 === 'undefined') {
                window.BookingCalendarUtils.log('Select2 is not loaded, skipping modal selects');
                return;
            }
            const $treatment = this.$modal.find('[data-filter="treatmentType"]');
            const $scheduler = this.$modal.find('[data-filter="schedulerId"]');
            this.initializeSelect2ForSelect($treatment, 'סוג טיפול');
            this.initializeSelect2ForSelect($scheduler, 'רופא / מטפל');
        }

        /**
         * @param {jQuery} $select
         * @param {string} placeholder
         */
        initializeSelect2ForSelect($select, placeholder) {
            if (!$select.length || typeof $.fn.select2 === 'undefined') return;
            if ($select.hasClass('select2-hidden-accessible')) return;

            $select.select2({
                theme: 'clinic-queue',
                dir: 'rtl',
                language: 'he',
                width: '100%',
                minimumResultsForSearch: -1,
                placeholder: placeholder,
                allowClear: false,
                dropdownParent: this.$modal
            });
        }

        /**
         * Binds modal events; clears prior `.bcm-modal` handlers to avoid duplicates across opens/cores.
         */
        bindEvents() {
            const $m = this.$modal;

            $m.off('.bcm-modal');
            $(document).off('keydown.bcm-modal touchmove.bcm-modal touchend.bcm-modal touchcancel.bcm-modal');

            $m.on('click.bcm-modal', '.bcm-close-btn', () => this.close());
            $m.on('click.bcm-modal', e => { if ($(e.target).is(MODAL_SELECTOR)) this.close(); });
            $(document).on('keydown.bcm-modal', e => { if (e.key === 'Escape') this.close(); });

            this._bindDragToClose($m);

            $m.on('click.bcm-modal', '.bcm-update-btn', () => {
                this.readFilters();
                if (this.isDateRangeUpdateBlocked()) {
                    this.syncUpdateButtonState();
                    return;
                }
                this.refetchForCurrentDateRange();
            });

            $m.on('click.bcm-modal', '.bcm-load-more-btn', () => {
                this.visibleDaysCount += DAYS_PER_PAGE;
                this.renderResults(true);
            });

            $m.on('click.bcm-modal', '.bcm-slot', e => {
                const $slot = $(e.currentTarget);
                const date  = $slot.closest('.bcm-day-block').data('date');
                const time  = $slot.data('time');
                this.selectSlot(date, time);
            });

            $m.on('click.bcm-modal', '.bcm-book-btn:not([disabled])', () => this.triggerBooking());

            $m.on('change.bcm-modal', '[data-filter="treatmentType"]', () => {
                this.filterState.treatmentType = $m.find('[data-filter="treatmentType"]').val();
                this.filterState.schedulerId   = '';
                const $schedSel = $m.find('[data-filter="schedulerId"]');
                if ($schedSel.hasClass('select2-hidden-accessible')) {
                    $schedSel.select2('destroy');
                }
                $schedSel.val('');
                this.populateSchedulerSelect($schedSel);
                this.initializeSelect2ForSelect($schedSel, 'רופא / מטפל');
                if ($schedSel.hasClass('select2-hidden-accessible')) {
                    $schedSel.trigger('change.select2');
                }
                $schedSel.trigger('change');
                this.syncUpdateButtonState();
                this.scheduleAutoRefetch();
            });

            $m.on('change.bcm-modal', '[data-filter="schedulerId"]', () => {
                this.filterState.schedulerId = $m.find('[data-filter="schedulerId"]').val() || '';
                this.syncUpdateButtonState();
                this.scheduleAutoRefetch();
            });

            $m.on('input.bcm-modal change.bcm-modal', '[data-filter="fromTime"], [data-filter="toTime"]', () => {
                this.readFilters();
                this.applyFiltersAndRender();
            });

            $m.on('change.bcm-modal', '.bcm-day-cb', () => {
                this.readFilters();
                this.applyFiltersAndRender();
            });

            // Native date/time custom shell sync.
            $m.on('input.bcm-modal change.bcm-modal', '.bcm-input', e => {
                this.syncNativeFieldDisplay($(e.currentTarget));
            });

            $m.on('click.bcm-modal', '.bcm-time-clear-btn', e => {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(e.currentTarget);
                const filterName = String($btn.data('clearFilter') || '');
                if (!filterName) return;

                const $input = $m.find(`[data-filter="${filterName}"]`);
                if (!$input.length) return;

                $input.val('');
                this.syncNativeFieldDisplay($input);
                this.readFilters();
                this.applyFiltersAndRender();
            });

            $m.on('change.bcm-modal', '[data-filter="fromDate"]', () => {
                this.filterState.fromDate = $m.find('[data-filter="fromDate"]').val() || '';
                this.filterState.toDate   = $m.find('[data-filter="toDate"]').val()   || '';
                this.normalizeDateRange('fromDate');
                this.syncUpdateButtonState();
            });

            $m.on('change.bcm-modal', '[data-filter="toDate"]', () => {
                this.filterState.fromDate = $m.find('[data-filter="fromDate"]').val() || '';
                this.filterState.toDate   = $m.find('[data-filter="toDate"]').val()   || '';
                this.normalizeDateRange('toDate');
                this.syncUpdateButtonState();
            });

            // Open native pickers when clicking anywhere on the custom shell.
            $m.on('click.bcm-modal', '.bcm-field--native, .bcm-native-shell, .bcm-native-text', e => {
                const $field = $(e.currentTarget).closest('.bcm-field--native');
                const $input = $field.find('.bcm-input--native');
                if (!$input.length) return;

                const inputEl = $input.get(0);
                if (!inputEl) return;

                if (typeof inputEl.showPicker === 'function') {
                    try {
                        inputEl.showPicker();
                        return;
                    } catch (_) {
                        // Fallback to focus+click for browsers restricting showPicker.
                    }
                }

                inputEl.focus();
                inputEl.click();
            });
        }

        /** Reads filter values from the DOM into filterState. */
        readFilters() {
            const $m = this.$modal;
            const todayIso = this.getTodayIsoDate();
            this.filterState.treatmentType = $m.find('[data-filter="treatmentType"]').val() || '';
            this.filterState.schedulerId   = $m.find('[data-filter="schedulerId"]').val()   || '';
            this.filterState.fromDate      = $m.find('[data-filter="fromDate"]').val()       || '';
            this.filterState.toDate        = $m.find('[data-filter="toDate"]').val()         || '';
            this.filterState.fromTime      = $m.find('[data-filter="fromTime"]').val()       || '';
            this.filterState.toTime        = $m.find('[data-filter="toTime"]').val()         || '';
            this.filterState.days          = [];
            $m.find('.bcm-day-cb:checked').each((_, el) => this.filterState.days.push($(el).val()));

            // Enforce min date in case of manual typing or browser edge cases.
            if (this.filterState.fromDate && this.filterState.fromDate < todayIso) {
                this.filterState.fromDate = todayIso;
                $m.find('[data-filter="fromDate"]').val(todayIso);
                this.syncNativeFieldDisplay($m.find('[data-filter="fromDate"]'));
            }

            this.normalizeDateRange('toDate', { silent: true });

            window.BookingCalendarUtils.log('Expanded modal filters applied:', this.filterState);
        }

        /**
         * @returns {Array} Appointment data after client-side filters
         */
        getFilteredData() {
            const data = this.core.appointmentData || [];

            return data.reduce((acc, dayObj) => {
                const dateStr = dayObj.date?.appointment_date || (typeof dayObj.date === 'string' ? dayObj.date : '');
                if (!dateStr) return acc;

                if (this.filterState.fromDate && dateStr < this.filterState.fromDate) return acc;
                if (this.filterState.toDate   && dateStr > this.filterState.toDate)   return acc;

                if (this.filterState.days.length < 7) {
                    const dow = String(new Date(dateStr + 'T12:00:00').getDay());
                    if (!this.filterState.days.includes(dow)) return acc;
                }

                let slots = dayObj.time_slots || [];
                if (this.filterState.fromTime || this.filterState.toTime) {
                    slots = slots.filter(s => {
                        const t = s.time_slot || s.time || '';
                        if (this.filterState.fromTime && t < this.filterState.fromTime) return false;
                        if (this.filterState.toTime   && t > this.filterState.toTime)   return false;
                        return true;
                    });
                }

                if (!slots.length) return acc;

                acc.push({ ...dayObj, time_slots: slots });
                return acc;
            }, []);
        }

        /**
         * @param {boolean} [preserveSelection] Keep the current slot selection when re-rendering
         */
        renderResults(preserveSelection = false) {
            const $list         = this.$modal.find('.bcm-results-list');
            const $resultsWrap  = this.$modal.find('.bcm-results');
            const allDays       = this.getFilteredData();
            const visibleDays   = allDays.slice(0, this.visibleDaysCount);

            window.BookingCalendarUtils.log('Expanded modal rendering:', {
                total   : allDays.length,
                visible : visibleDays.length,
            });

            if (!allDays.length) {
                $resultsWrap.addClass('bcm-results--empty');
                const calendarIconUrl = (typeof window.bookingCalendarData !== 'undefined' && window.bookingCalendarData.calendarIconUrl)
                    ? window.bookingCalendarData.calendarIconUrl
                    : '';
                const emptyIconHtml = calendarIconUrl
                    ? `<img src="${calendarIconUrl}" alt="" class="bcm-empty-icon" width="32" height="32" />`
                    : '';
                $list.html(`
                    <div class="bcm-empty">
                        ${emptyIconHtml}
                        <p>לא נמצאו תורים זמינים לפי הפילטרים שנבחרו</p>
                    </div>
                `);
                this.updateBookButton();
                return;
            }

            $resultsWrap.removeClass('bcm-results--empty');

            $list.html(visibleDays.map(d => {
                const dateStr = d.date?.appointment_date || d.date;
                return this.renderDayBlock(dateStr, d.time_slots || []);
            }).join(''));

            if (preserveSelection && this.selectedDate && this.selectedTime) {
                this.$modal
                    .find(`.bcm-day-block[data-date="${this.selectedDate}"] .bcm-slot[data-time="${this.selectedTime}"]`)
                    .addClass('bcm-slot--selected selected');
            }

            this.updateBookButton();
        }

        /**
         * @param {string} dateStr
         * @param {Array} slots
         * @returns {string} HTML for one day block
         */
        renderDayBlock(dateStr, slots) {
            const title         = this.formatHebrewDate(dateStr);
            const isSelectedDay = this.selectedDate === dateStr;

            let slotsHtml;

            if (!slots.length) {
                slotsHtml = `<p class="bcm-no-slots">אין תורים זמינים</p>`;
            } else {
                const badges = slots.map(slot => {
                    const time       = slot.time_slot || slot.time || '';
                    const display    = this.core.uiManager.formatTimeForDisplay(time);
                    const isSelected = isSelectedDay && this.selectedTime === time;
                    return `<div class="time-slot-badge free bcm-slot${isSelected ? ' bcm-slot--selected selected' : ''}" data-time="${time}">${display}</div>`;
                }).join('');

                slotsHtml = `<div class="time-slots-grid bcm-slots-grid">${badges}</div>`;
            }

            return `
                <div class="bcm-day-block" data-date="${dateStr}">
                    <h3 class="bcm-day-title">${title}</h3>
                    ${slotsHtml}
                </div>
            `;
        }

        /**
         * @param {string} date
         * @param {string} time
         */
        selectSlot(date, time) {
            this.$modal.find('.bcm-slot').removeClass('bcm-slot--selected selected');

            if (this.selectedDate === date && this.selectedTime === time) {
                this.selectedDate = null;
                this.selectedTime = null;
                window.BookingCalendarUtils.log('Expanded modal slot deselected');
            } else {
                this.selectedDate = date;
                this.selectedTime = time;
                this.$modal
                    .find(`.bcm-day-block[data-date="${date}"] .bcm-slot[data-time="${time}"]`)
                    .addClass('bcm-slot--selected selected');
                window.BookingCalendarUtils.log('Expanded modal slot selected:', { date, time });
            }

            this.updateBookButton();
        }

        updateBookButton() {
            const $btn    = this.$modal.find('.bcm-book-btn');
            const canBook = !!(this.selectedDate && this.selectedTime);
            $btn.prop('disabled', !canBook)
                .attr('aria-disabled', canBook ? 'false' : 'true')
                .toggleClass('bcm-book-btn--active', canBook);
        }

        /** Applies selection to core and runs the existing booking flow. */
        triggerBooking() {
            if (!this.selectedDate || !this.selectedTime) return;

            this.core.selectedDate = this.selectedDate;
            this.core.selectedTime = this.selectedTime;

            if (this.filterState.schedulerId) {
                this.core.schedulerId = this.filterState.schedulerId;
            }

            window.BookingCalendarUtils.log('Expanded modal triggering booking:', {
                date : this.selectedDate,
                time : this.selectedTime,
            });

            this.close();

            if (typeof this.core.handleBookButtonClick === 'function') {
                this.core.handleBookButtonClick();
            }
        }

        /**
         * @returns {Array} Schedulers as array (normalizes object map)
         */
        getSchedulersArray() {
            return Array.isArray(this.core.allSchedulers)
                ? this.core.allSchedulers
                : Object.values(this.core.allSchedulers || {});
        }

        /**
         * @param {string} dateStr YYYY-MM-DD
         * @returns {string} Hebrew locale display date
         */
        formatHebrewDate(dateStr) {
            try {
                const d       = new Date(dateStr + 'T12:00:00');
                const weekday = d.toLocaleDateString('he-IL', { weekday: 'long' });
                const day     = d.getDate();
                const month   = d.toLocaleDateString('he-IL', { month: 'long' });
                return `${weekday}, ${day} ב${month}`;
            } catch (_) {
                return dateStr;
            }
        }

        /**
         * Syncs the custom date/time shell text; native input keeps picker/value.
         *
         * @param {jQuery} $input
         */
        syncNativeFieldDisplay($input) {
            const filterName = String($input.data('filter') || '');
            if (!filterName) return;

            const $display = this.$modal.find(`.bcm-native-text[data-display-for="${filterName}"]`);
            if (!$display.length) return;

            const rawValue = String($input.val() || '').trim();
            const emptyText = String($display.data('emptyText') || '').trim();
            const hasValue = !!rawValue;
            this.syncTimeClearButtonState(filterName, hasValue);

            if (!hasValue) {
                $display.text(emptyText).addClass('is-placeholder');
                return;
            }

            if (filterName === 'fromDate' || filterName === 'toDate') {
                $display.text(this.formatDisplayDate(rawValue)).removeClass('is-placeholder');
                return;
            }

            $display.text(rawValue).removeClass('is-placeholder');
        }

        /**
         * @param {string} filterName
         * @param {boolean} hasValue
         */
        syncTimeClearButtonState(filterName, hasValue) {
            if (filterName !== 'fromTime' && filterName !== 'toTime') return;
            const $input = this.$modal.find(`[data-filter="${filterName}"]`);
            const $field = $input.closest('.bcm-field--native');
            $field.toggleClass('bcm-field--has-value', !!hasValue);
        }

        /**
         * @param {string} rawDate YYYY-MM-DD
         * @returns {string} dd/mm/yyyy
         */
        formatDisplayDate(rawDate) {
            const parts = rawDate.split('-');
            if (parts.length !== 3) return rawDate;
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }

        /**
         * Split UTC datetime string (YYYY-MM-DDTHH:mm:ssZ) into
         * date and time values that native date/time inputs accept.
         *
         * @param {string} utcDateTime
         * @return {{date: string, time: string}}
         */
        parseUtcRangeValue(utcDateTime) {
            const raw = String(utcDateTime || '').trim();
            const match = raw.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/);
            if (!match) {
                return { date: '', time: '' };
            }

            return {
                date: match[1],
                time: `${match[2]}:${match[3]}`
            };
        }

        /**
         * @return {string} Today's local date as YYYY-MM-DD.
         */
        getTodayIsoDate() {
            return window.BookingCalendarUtils.formatDate(new Date());
        }

        updateFieldPlaceholderState() {
            this.$modal.find('.bcm-input').each((_, el) => {
                this.syncNativeFieldDisplay($(el));
            });
        }

        /**
         * Debounced auto server refetch, triggered when the treatment type or scheduler
         * (doctor) selection changes. A treatment change cascades into a scheduler change,
         * so debouncing collapses both into a single server request.
         *
         * Mirrors the manual "update results" button: reads filters, honors the per-snapshot
         * cooldown (falls back to client-side filtering when blocked), otherwise refetches.
         */
        scheduleAutoRefetch() {
            if (this._isInitializing) return;

            if (this._autoRefetchTimer) {
                clearTimeout(this._autoRefetchTimer);
            }

            this._autoRefetchTimer = setTimeout(() => {
                this._autoRefetchTimer = null;
                this.readFilters();

                if (this.isDateRangeUpdateBlocked()) {
                    this.syncUpdateButtonState();
                    this.applyFiltersAndRender();
                    return;
                }

                this.refetchForCurrentDateRange();
            }, AUTO_REFETCH_DEBOUNCE_MS);
        }

        /**
         * Fetches free-time for the selected date range, then applies client-side time/day filters.
         *
         * @returns {Promise<void>}
         */
        async refetchForCurrentDateRange() {
            const fromDate = this.filterState.fromDate;
            const toDate   = this.filterState.toDate;

            if (!fromDate || !toDate) {
                window.BookingCalendarUtils.log(
                    'refetchForCurrentDateRange: missing date range, filtering locally'
                );
                this.applyFiltersAndRender();
                return;
            }

            const { proxySchedulerIds, duration } = this.core.dataManager.resolveSchedulerParamsForFilters({
                allSchedulers: this.core.allSchedulers,
                treatmentType: this.filterState.treatmentType,
                schedulerId: this.filterState.schedulerId
            });
            if (!proxySchedulerIds.length) {
                window.BookingCalendarUtils.log(
                    'refetchForCurrentDateRange: no scheduler resolved, skipping fetch'
                );
                this.applyFiltersAndRender();
                return;
            }

            const fromDateObj = this.buildStartOfDay(fromDate);
            const toDateObj   = this.buildEndOfDay(toDate);
            if (!fromDateObj || !toDateObj) {
                this.applyFiltersAndRender();
                return;
            }

            const fromDateUTC = this.core.dataManager.formatDateUTC(fromDateObj);
            const toDateUTC   = this.core.dataManager.formatDateUTC(toDateObj);

            this.setUpdateLoadingState(true);

            try {
                await this.core.dataManager.fetchFreeTimeRange({
                    schedulerIDsStr: proxySchedulerIds.join(','),
                    duration: duration,
                    fromDateUTC: fromDateUTC,
                    toDateUTC: toDateUTC
                });
                this.markDateRangeRequestCompleted();
            } catch (error) {
                window.BookingCalendarUtils.error(
                    'refetchForCurrentDateRange: failed to load free slots', error
                );
            } finally {
                this.setUpdateLoadingState(false);
                this.applyFiltersAndRender();
            }
        }

        /**
         * Clamps the date range to {@link MAX_RANGE_DAYS}; adjusts the opposite edge when exceeded.
         *
         * @param {'fromDate'|'toDate'} changedField Field the user edited
         * @param {Object} [options]
         * @param {boolean} [options.silent] Skip user-facing notice (internal guard)
         */
        normalizeDateRange(changedField, options) {
            const silent = !!(options && options.silent);
            const todayIso = this.getTodayIsoDate();
            const $m = this.$modal;
            const $fromInput = $m.find('[data-filter="fromDate"]');
            const $toInput   = $m.find('[data-filter="toDate"]');

            let fromDate = this.filterState.fromDate || $fromInput.val() || '';
            let toDate   = this.filterState.toDate   || $toInput.val()   || '';

            if (!fromDate || !toDate) return;

            if (fromDate < todayIso) fromDate = todayIso;

            if (toDate < fromDate) {
                if (changedField === 'fromDate') {
                    toDate = fromDate;
                } else {
                    fromDate = toDate < todayIso ? todayIso : toDate;
                }
            }

            const diff = this.diffInDays(fromDate, toDate);
            let adjusted = false;
            let adjustedField = null;

            if (diff > MAX_RANGE_DAYS) {
                if (changedField === 'fromDate') {
                    toDate = this.addDaysToIso(fromDate, MAX_RANGE_DAYS);
                    adjustedField = 'toDate';
                } else {
                    const candidate = this.addDaysToIso(toDate, -MAX_RANGE_DAYS);
                    fromDate = candidate < todayIso ? todayIso : candidate;
                    adjustedField = 'fromDate';
                }
                adjusted = true;
            }

            const prevFrom = this.filterState.fromDate;
            const prevTo   = this.filterState.toDate;

            this.filterState.fromDate = fromDate;
            this.filterState.toDate   = toDate;

            if (prevFrom !== fromDate) {
                $fromInput.val(fromDate);
                this.syncNativeFieldDisplay($fromInput);
            }
            if (prevTo !== toDate) {
                $toInput.val(toDate);
                this.syncNativeFieldDisplay($toInput);
            }

            this.updateDateInputsBounds();

            if (adjusted && !silent) {
                const fieldLabel = adjustedField === 'toDate' ? 'תאריך הסיום' : 'תאריך ההתחלה';
                this.showRangeLimitNotice(
                    `טווח התאריכים המרבי הוא 3 שבועות – ${fieldLabel} עודכן אוטומטית.`
                );
            }
        }

        /** Sets min/max on native date inputs to match allowed range. */
        updateDateInputsBounds() {
            const todayIso = this.getTodayIsoDate();
            const $m = this.$modal;
            const $fromInput = $m.find('[data-filter="fromDate"]');
            const $toInput   = $m.find('[data-filter="toDate"]');
            const fromDate = this.filterState.fromDate || '';
            const toDate   = this.filterState.toDate   || '';

            $fromInput.attr('min', todayIso);
            if (toDate) {
                $fromInput.attr('max', toDate);
            } else {
                $fromInput.removeAttr('max');
            }

            const toMin = fromDate && fromDate > todayIso ? fromDate : todayIso;
            $toInput.attr('min', toMin);
            if (fromDate) {
                $toInput.attr('max', this.addDaysToIso(fromDate, MAX_RANGE_DAYS));
            } else {
                $toInput.removeAttr('max');
            }
        }

        /**
         * @param {string} fromIso
         * @param {string} toIso
         * @returns {number} Whole days between dates (0 if invalid)
         */
        diffInDays(fromIso, toIso) {
            const from = this.buildStartOfDay(fromIso);
            const to   = this.buildStartOfDay(toIso);
            if (!from || !to) return 0;
            return Math.round((to.getTime() - from.getTime()) / MS_PER_DAY);
        }

        /**
         * @param {string} isoDate
         * @param {number} delta Day offset (may be negative)
         * @returns {string}
         */
        addDaysToIso(isoDate, delta) {
            const base = this.buildStartOfDay(isoDate);
            if (!base) return isoDate;
            base.setDate(base.getDate() + delta);
            const year  = base.getFullYear();
            const month = String(base.getMonth() + 1).padStart(2, '0');
            const day   = String(base.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        /**
         * @param {string} message Shown below date filters; auto-hides after 4s
         */
        showRangeLimitNotice(message) {
            const $filtersRow = this.$modal.find('.bcm-filters-row--dates');
            if (!$filtersRow.length) return;

            let $notice = this.$modal.find('.bcm-range-notice');
            if (!$notice.length) {
                $notice = $(
                    '<div class="bcm-range-notice" role="status" aria-live="polite"></div>'
                );
                $filtersRow.after($notice);
            }

            $notice.text(message).addClass('bcm-range-notice--visible');

            if (this._rangeNoticeTimer) {
                clearTimeout(this._rangeNoticeTimer);
            }
            this._rangeNoticeTimer = setTimeout(() => {
                $notice.removeClass('bcm-range-notice--visible');
            }, 4000);
        }

        /**
         * Stable key for server refetch/cooldown (treatment, scheduler, fromDate, toDate).
         *
         * @returns {string}
         */
        getCurrentDateRangeKey() {
            return [
                this.filterState.treatmentType || '',
                String(this.filterState.schedulerId || ''),
                this.filterState.fromDate || '',
                this.filterState.toDate || '',
            ].join('|');
        }

        /**
         * @returns {boolean} True while cooldown applies to the current server-field key
         */
        isDateRangeUpdateBlocked() {
            const currentKey = this.getCurrentDateRangeKey();
            if (!currentKey || !this.lastRequestedDateRangeKey || !this.lastRequestedDateRangeAt) {
                return false;
            }
            if (currentKey !== this.lastRequestedDateRangeKey) {
                return false;
            }
            return (Date.now() - this.lastRequestedDateRangeAt) < UPDATE_COOLDOWN_MS;
        }

        /** Records a successful refetch for cooldown and resets the dirty snapshot. */
        markDateRangeRequestCompleted() {
            this.lastRequestedDateRangeKey = this.getCurrentDateRangeKey();
            this.lastRequestedDateRangeAt = Date.now();
            this.captureServerFieldsSnapshot();
        }

        /**
         * @returns {string} DOM snapshot of server-bound filter fields
         */
        getServerFieldsSnapshot() {
            const $m = this.$modal;
            if (!$m || !$m.length) return '';
            return [
                $m.find('[data-filter="treatmentType"]').val() || '',
                $m.find('[data-filter="schedulerId"]').val()   || '',
                $m.find('[data-filter="fromDate"]').val()       || '',
                $m.find('[data-filter="toDate"]').val()         || '',
            ].join('|');
        }

        /** Stores server-field snapshot and syncs the update button (after open or successful refetch). */
        captureServerFieldsSnapshot() {
            this._serverFieldsSnapshot = this.getServerFieldsSnapshot();
            this.syncUpdateButtonState();
        }

        /**
         * @returns {boolean}
         */
        isUpdateDirty() {
            return this.getServerFieldsSnapshot() !== this._serverFieldsSnapshot;
        }

        /**
         * @returns {number} Milliseconds until cooldown ends for the current key
         */
        getDateRangeCooldownRemainingMs() {
            if (!this.isDateRangeUpdateBlocked()) return 0;
            const elapsed = Date.now() - this.lastRequestedDateRangeAt;
            return Math.max(0, UPDATE_COOLDOWN_MS - elapsed);
        }

        /** Syncs update button disabled state (loading, cooldown, dirty, initializing). */
        syncUpdateButtonState() {
            const $btn = this.$modal ? this.$modal.find('.bcm-update-btn') : $();
            if (!$btn.length) return;

            if (this._updateCooldownTimer) {
                clearTimeout(this._updateCooldownTimer);
                this._updateCooldownTimer = null;
            }

            const blockedByCooldown = this.isDateRangeUpdateBlocked();
            const isDirty = this._isInitializing ? false : this.isUpdateDirty();
            const isDisabled = this._isUpdateLoading || blockedByCooldown || !isDirty;
            $btn.prop('disabled', isDisabled)
                .toggleClass('bcm-update-btn--cooldown', blockedByCooldown)
                .toggleClass('bcm-update-btn--clean', !isDirty && !this._isUpdateLoading)
                .attr('aria-disabled', isDisabled ? 'true' : 'false');

            if (blockedByCooldown && !this._isUpdateLoading) {
                const remaining = this.getDateRangeCooldownRemainingMs();
                if (remaining > 0) {
                    this._updateCooldownTimer = setTimeout(() => {
                        this.syncUpdateButtonState();
                    }, remaining + 50);
                }
            }
        }

        /**
         * @param {string} isoDate YYYY-MM-DD
         * @returns {Date|null} Local start of day
         */
        buildStartOfDay(isoDate) {
            const parts = String(isoDate || '').split('-').map(Number);
            if (parts.length !== 3 || parts.some(isNaN)) return null;
            const [y, m, d] = parts;
            return new Date(y, m - 1, d, 0, 0, 0, 0);
        }

        /**
         * @param {string} isoDate YYYY-MM-DD
         * @returns {Date|null} Local end of day
         */
        buildEndOfDay(isoDate) {
            const parts = String(isoDate || '').split('-').map(Number);
            if (parts.length !== 3 || parts.some(isNaN)) return null;
            const [y, m, d] = parts;
            return new Date(y, m - 1, d, 23, 59, 59, 999);
        }

        /**
         * @param {boolean} isLoading
         */
        setUpdateLoadingState(isLoading) {
            const $btn          = this.$modal.find('.bcm-update-btn');
            const $resultsWrap  = this.$modal.find('.bcm-results');
            const $resultsList  = this.$modal.find('.bcm-results-list');
            this._isUpdateLoading = !!isLoading;

            $btn.attr('aria-busy', isLoading ? 'true' : 'false')
                .toggleClass('bcm-update-btn--loading', !!isLoading);
            $resultsWrap.toggleClass('bcm-results--loading', !!isLoading);

            // Reuse booking-calendar loader while refetching free-time from the server
            if (isLoading) {
                $resultsWrap.removeClass('bcm-results--empty');
                $resultsList.html(`
                    <div class="booking-calendar-loader" style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px;">
                        <div class="spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary, #d82466); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px;"></div>
                        <p style="margin: 0; font-size: 16px; color: #6c757d; font-weight: 500;">טוען תורים זמינים...</p>
                    </div>
                `);
            }

            this.syncUpdateButtonState();
        }

        /** Client-side filter change: reset selection and re-render with transition. */
        applyFiltersAndRender() {
            this.visibleDaysCount = DAYS_PER_PAGE;
            this.selectedDate = null;
            this.selectedTime = null;
            this.animateResultsTransition();
        }

        /**
         * Mobile drag-to-close on dialog handle (same idea as core.bindMobileDragToClose).
         *
         * @param {jQuery} $m Modal root element
         */
        _bindDragToClose($m) {
            const $dialog       = $m.find('.bcm-dialog');
            const closeThreshold = 110;
            let startY   = 0;
            let currentDrag = 0;
            let isDragging  = false;

            $m.on('touchstart.bcm-modal', '.booking-calendar-mobile-drag-handle', (e) => {
                const touch = e.originalEvent.touches && e.originalEvent.touches[0];
                if (!touch) return;
                startY      = touch.clientY;
                currentDrag = 0;
                isDragging  = true;
                $dialog.css('transition', 'none');
            });

            $(document).on('touchmove.bcm-modal', (e) => {
                if (!isDragging) return;
                const touch = e.originalEvent.touches && e.originalEvent.touches[0];
                if (!touch) return;
                const deltaY = Math.max(0, touch.clientY - startY);
                currentDrag  = deltaY;
                $dialog.css('transform', `translateY(${deltaY}px)`);
                if (deltaY > 0 && e.cancelable) e.preventDefault();
            });

            const endDrag = () => {
                if (!isDragging) return;
                isDragging = false;

                if (currentDrag >= closeThreshold) {
                    $dialog.css({ transform: '', transition: '' });
                    this.close();
                    return;
                }

                $dialog.css('transition', 'transform 180ms ease-out')
                       .css('transform', 'translateY(0)');
                setTimeout(() => {
                    $dialog.css({ transform: '', transition: '' });
                }, 220);
            };

            $(document).on('touchend.bcm-modal', endDrag);
            $(document).on('touchcancel.bcm-modal', endDrag);
        }

        /** Fade-out, render, fade-in when client-side filters change. */
        animateResultsTransition() {
            const $list = this.$modal.find('.bcm-results-list');
            if (!$list.length) {
                this.renderResults();
                return;
            }

            $list.addClass('bcm-results-list--transition-out');

            setTimeout(() => {
                this.renderResults();
                $list.removeClass('bcm-results-list--transition-out')
                    .addClass('bcm-results-list--transition-in');

                setTimeout(() => {
                    $list.removeClass('bcm-results-list--transition-in');
                }, 180);
            }, 120);
        }
    }

    window.BookingCalendarExpandedModal = BookingCalendarExpandedModal;

})(jQuery);
