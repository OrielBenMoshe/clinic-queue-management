/**
 * Clinic Queue Date Picker
 *
 * תחליף ל-native browser date picker עבור שדות input[type="date"]
 * בתוך טפסי bookingcalendar של התוסף.
 *
 * תכונות:
 *  - לוח RTL-aware (עברית, יום ראשון כעמודה ראשונה מימין)
 *  - מכבד min/max attributes (מתעדכנים דינמית ע"י ה-modal JS)
 *  - מפעיל input + change events נייטיב כדי שה-jQuery handlers הקיימים ירוצו
 *  - מובייל: bottom-sheet עם backdrop
 *  - ניווט מקלדת: arrow keys, Enter, Escape, PageUp/Down
 *  - נגישות WCAG: role="dialog", role="grid", aria-label, aria-pressed
 *
 * אינטגרציה עם booking-calendar-expanded-modal.js:
 *  - pointer-events:none על ה-input מונע פתיחת native picker
 *  - e.stopPropagation() על ה-field container מונע את ה-jQuery handler
 *    שקורא inputEl.showPicker()
 *  - dispatchEvent(change) מפעיל את כל ה-jQuery handlers הקיימים
 *    (syncNativeFieldDisplay, normalizeDateRange, syncUpdateButtonState)
 */
/* global window */
'use strict';

(function () {

    /** @type {string[]} Hebrew month names (index 0 = January) */
    const MONTH_NAMES = [
        'ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני',
        'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר',
    ];

    /**
     * Day abbreviations, Sunday-first (Hebrew calendar convention).
     * In RTL flex layout, index 0 (א׳=Sunday) appears as the RIGHTMOST column.
     */
    const DAY_ABBREVS = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳', 'ש׳'];

    // ─────────────────────────────────────────────────────────────────────────

    class ClinicQueueDatePicker {

        /**
         * @param {HTMLInputElement} inputEl  – The <input type="date"> (transparent, covers field)
         * @param {HTMLElement}      fieldEl  – The .bcm-field--native container
         */
        constructor(inputEl, fieldEl) {
            this.input     = inputEl;
            this.field     = fieldEl;
            this.popup     = null;
            this._backdrop = null;
            this._open     = false;
            this.viewYear  = 0;
            this.viewMonth = 0;

            // Detect mode: range or single
            const filterName = this.input.getAttribute('data-filter');
            this.isRangeMode = filterName === 'fromDate' || filterName === 'toDate';
            this.rangeField  = filterName; // 'fromDate', 'toDate', or null

            // Range mode state
            if (this.isRangeMode) {
                this.tempStart   = null; // Date object for start
                this.tempEnd     = null; // Date object for end
                this.fromInput   = null; // Will be populated in _attach()
                this.toInput     = null; // Will be populated in _attach()
                this.fromField   = null;
                this.toField     = null;
            }

            this._onOutsideClick = this._onOutsideClick.bind(this);
            this._onKeydown      = this._onKeydown.bind(this);
            this._onResize       = this._onResize.bind(this);

            // MutationObserver for dynamic min/max updates
            this._observer = null;

            this._attach();
            this._setupAttributeObserver();
        }

        // ── Setup ─────────────────────────────────────────────────────────────

        /**
         * Marks the input, disables native interactions, and attaches field-level
         * click/keyboard handlers that intercept the jQuery showPicker() delegation.
         */
        _attach() {
            this.input.setAttribute('data-cq-datepicker', 'true');

            /*
             * Prevent the transparent input overlay from receiving pointer events.
             * This means clicks fall through to .bcm-native-shell / .bcm-field--native
             * where our handler lives, and the native date picker never opens.
             */
            this.input.style.pointerEvents = 'none';

            // Range mode: find both inputs and fields
            if (this.isRangeMode) {
                const modalContainer = this.field.closest('.bcm-filters, #bcm-expanded-modal');
                if (modalContainer) {
                    const fromInput = modalContainer.querySelector('input[data-filter="fromDate"]');
                    const toInput   = modalContainer.querySelector('input[data-filter="toDate"]');
                    
                    if (fromInput && toInput) {
                        this.fromInput = fromInput;
                        this.toInput   = toInput;
                        this.fromField = fromInput.closest('.bcm-field--native');
                        this.toField   = toInput.closest('.bcm-field--native');

                        // Mark both inputs
                        this.fromInput.setAttribute('data-cq-datepicker', 'true');
                        this.toInput.setAttribute('data-cq-datepicker', 'true');
                        this.fromInput.style.pointerEvents = 'none';
                        this.toInput.style.pointerEvents = 'none';

                        // Make both fields clickable
                        [this.fromField, this.toField].forEach(f => {
                            if (!f.hasAttribute('tabindex')) {
                                f.setAttribute('tabindex', '0');
                            }
                            f.setAttribute('role', 'button');
                            f.setAttribute('aria-haspopup', 'dialog');
                            f.setAttribute('aria-expanded', 'false');
                        });

                        // Attach click handlers to both fields
                        this.fromField.addEventListener('click', (e) => {
                            e.stopPropagation();
                            this._open ? this.close() : this.open();
                        });
                        this.toField.addEventListener('click', (e) => {
                            e.stopPropagation();
                            this._open ? this.close() : this.open();
                        });

                        // Keyboard handlers
                        [this.fromField, this.toField].forEach(f => {
                            f.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    this._open ? this.close() : this.open();
                                }
                            });
                        });
                    }
                }
            } else {
                // Single mode: original behavior
                if (!this.field.hasAttribute('tabindex')) {
                    this.field.setAttribute('tabindex', '0');
                }
                this.field.setAttribute('role', 'button');
                this.field.setAttribute('aria-haspopup', 'dialog');
                this.field.setAttribute('aria-expanded', 'false');

                this.field.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this._open ? this.close() : this.open();
                });

                this.field.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        e.stopPropagation();
                        this._open ? this.close() : this.open();
                    }
                });
            }
        }

        /**
         * Sets up MutationObserver to watch for dynamic changes to min/max attributes.
         * When attributes change, re-renders the calendar if it's currently open.
         */
        _setupAttributeObserver() {
            if (!this.input) return;

            // Watch all inputs in range mode
            const inputsToWatch = this.isRangeMode && this.fromInput && this.toInput
                ? [this.fromInput, this.toInput]
                : [this.input];

            this._observer = new MutationObserver((mutations) => {
                let shouldRerender = false;

                for (const mutation of mutations) {
                    if (mutation.type === 'attributes' && 
                        (mutation.attributeName === 'min' || mutation.attributeName === 'max')) {
                        shouldRerender = true;
                        break;
                    }
                }

                // Only re-render if the popup is currently open
                if (shouldRerender && this._open && this.popup) {
                    console.log('Date picker: min/max attributes changed, re-rendering');
                    this._render();
                }
            });

            // Observe all relevant inputs
            inputsToWatch.forEach(input => {
                this._observer.observe(input, {
                    attributes: true,
                    attributeFilter: ['min', 'max']
                });
            });
        }

        /**
         * Disconnects the MutationObserver (cleanup).
         */
        _disconnectObserver() {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
        }

        // ── Public API ────────────────────────────────────────────────────────

        open() {
            if (this._open) return;
            this._open = true;

            if (this.isRangeMode) {
                // Range mode: initialize temp range from current input values
                const fromVal = this.fromInput.value;
                const toVal   = this.toInput.value;

                if (fromVal) {
                    this.tempStart = this._parseISODate(fromVal);
                }
                if (toVal) {
                    this.tempEnd = this._parseISODate(toVal);
                }

                // Set view to current selection or today
                if (this.tempStart) {
                    this.viewYear  = this.tempStart.getFullYear();
                    this.viewMonth = this.tempStart.getMonth();
                } else {
                    const today    = new Date();
                    this.viewYear  = today.getFullYear();
                    this.viewMonth = today.getMonth();
                }
            } else {
                // Single mode: original behavior
                const val = this.input.value;
                if (val) {
                    const parts   = val.split('-').map(Number);
                    this.viewYear  = parts[0];
                    this.viewMonth = parts[1] - 1;
                } else {
                    const today    = new Date();
                    this.viewYear  = today.getFullYear();
                    this.viewMonth = today.getMonth();
                }
            }

            this._createPopup();
            this._render();
            this._position();

            // Animate in (two rAF ticks: first paint, second transition start)
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (this.popup) {
                        this.popup.classList.add('cq-dp--visible');
                    }
                    this._focusSelectedOrFirst();
                });
            });

            // Mark both fields as expanded in range mode
            if (this.isRangeMode) {
                if (this.fromField) this.fromField.setAttribute('aria-expanded', 'true');
                if (this.toField)   this.toField.setAttribute('aria-expanded', 'true');
                if (this.fromField) this.fromField.classList.add('cq-datepicker-open');
                if (this.toField)   this.toField.classList.add('cq-datepicker-open');
            } else {
                this.field.setAttribute('aria-expanded', 'true');
                this.field.classList.add('cq-datepicker-open');
            }

            // Use capture phase so we catch the click before any handler can stopPropagation.
            document.addEventListener('click', this._onOutsideClick, true);
            document.addEventListener('keydown', this._onKeydown);
            window.addEventListener('resize', this._onResize);
        }

        close() {
            if (!this._open) return;
            this._open = false;

            document.removeEventListener('click', this._onOutsideClick, true);
            document.removeEventListener('keydown', this._onKeydown);
            window.removeEventListener('resize', this._onResize);

            if (this.isRangeMode) {
                if (this.fromField) {
                    this.fromField.setAttribute('aria-expanded', 'false');
                    this.fromField.classList.remove('cq-datepicker-open');
                }
                if (this.toField) {
                    this.toField.setAttribute('aria-expanded', 'false');
                    this.toField.classList.remove('cq-datepicker-open');
                }
            } else {
                this.field.setAttribute('aria-expanded', 'false');
                this.field.classList.remove('cq-datepicker-open');
            }

            this._removeBackdrop();

            if (this.popup) {
                this.popup.classList.remove('cq-dp--visible');
                const p    = this.popup;
                this.popup = null;
                setTimeout(() => { if (p && p.parentNode) p.parentNode.removeChild(p); }, 180);
            }

            // Return focus to the trigger field
            if (this.isRangeMode && this.fromField) {
                this.fromField.focus();
            } else {
                this.field.focus();
            }
        }

        // ── Private: DOM ──────────────────────────────────────────────────────

        _createPopup() {
            const popup = document.createElement('div');
            popup.className = 'clinic-queue-datepicker';
            popup.setAttribute('role', 'dialog');
            popup.setAttribute('aria-modal', 'true');
            popup.setAttribute('dir', 'rtl');
            // Label set after month name is known; updated in _render().
            document.body.appendChild(popup);
            this.popup = popup;
        }

        _render() {
            if (!this.popup) return;

            const today = this._todayISO();
            
            // In range mode, read min from fromInput and max from toInput
            // In single mode, read both from this.input
            let minDate = '';
            let maxDate = '';
            
            if (this.isRangeMode && this.fromInput && this.toInput) {
                minDate = this.fromInput.getAttribute('min') || '';
                maxDate = this.toInput.getAttribute('max') || '';
            } else {
                minDate = this.input.getAttribute('min') || '';
                maxDate = this.input.getAttribute('max') || '';
            }
            
            const y     = this.viewYear;
            const m     = this.viewMonth;
            const mName = MONTH_NAMES[m];

            this.popup.setAttribute('aria-label', `בחירת תאריך – ${mName} ${y}`);

            const firstDow    = new Date(y, m, 1).getDay();         // 0 = Sunday
            const daysInMonth = new Date(y, m + 1, 0).getDate();

            // ── Day-name header row (RTL: index 0 = Sunday = rightmost col) ──
            const headerCells = DAY_ABBREVS
                .map(n => `<span class="cq-dp__weekday" aria-hidden="true">${n}</span>`)
                .join('');

            // ── Empty offset cells before the 1st ──
            let cells = '';
            for (let i = 0; i < firstDow; i++) {
                cells += `<span class="cq-dp__day cq-dp__day--empty" aria-hidden="true"></span>`;
            }

            // ── Day buttons ──
            const selVal = this.isRangeMode ? '' : (this.input.value || '');
            
            for (let day = 1; day <= daysInMonth; day++) {
                const iso      = this._toISO(y, m + 1, day);
                const disabled = (minDate && iso < minDate) || (maxDate && iso > maxDate);
                const isToday  = iso === today;
                const isSel    = !this.isRangeMode && iso === selVal;

                let cls = 'cq-dp__day';
                if (disabled) cls += ' cq-dp__day--disabled';
                if (isToday)  cls += ' cq-dp__day--today';
                if (isSel)    cls += ' cq-dp__day--selected';

                // Range mode: add start/end/in-range classes
                if (this.isRangeMode) {
                    const dateObj = this._parseISODate(iso);
                    const isStart = this.tempStart && this._isSameDay(dateObj, this.tempStart);
                    const isEnd   = this.tempEnd && this._isSameDay(dateObj, this.tempEnd);
                    const isInRange = this.tempStart && this.tempEnd && 
                                      dateObj > this.tempStart && dateObj < this.tempEnd;

                    if (isStart) cls += ' cq-dp__day--start';
                    if (isEnd)   cls += ' cq-dp__day--end';
                    if (isInRange) cls += ' cq-dp__day--in-range';
                }

                const ariaLabel  = `${day} ${mName} ${y}`;
                const inTabRing  = !disabled && (isSel || (isToday && !selVal));
                const tabindex   = inTabRing ? '0' : '-1';
                const disAttr    = disabled ? ' disabled aria-disabled="true"' : '';
                const selAttr    = isSel    ? ' aria-pressed="true"' : '';

                cells += `<button type="button" class="${cls}" data-date="${iso}"
                    tabindex="${tabindex}" aria-label="${ariaLabel}"${disAttr}${selAttr}>${day}</button>`;
            }

            // ── Can we navigate prev/next month? ──
            const prevLastDay = this._toISO(
                m === 0 ? y - 1 : y,
                m === 0 ? 12    : m,
                new Date(y, m, 0).getDate()
            );
            const nextFirstDay = this._toISO(
                m === 11 ? y + 1 : y,
                m === 11 ? 1     : m + 2,
                1
            );
            
            // Calculate max date: 4 years from today
            const todayDate = new Date();
            const maxAllowedDate = new Date(todayDate.getFullYear() + 4, todayDate.getMonth(), todayDate.getDate());
            const maxAllowedISO = this._toISO(
                maxAllowedDate.getFullYear(),
                maxAllowedDate.getMonth() + 1,
                maxAllowedDate.getDate()
            );
            
            // Determine navigation limits
            const canGoPrev = !minDate || prevLastDay >= minDate;
            const canGoNext = nextFirstDay <= maxAllowedISO && (!maxDate || nextFirstDay <= maxDate);

            // ── SVG arrows ──
            const arrowRight = `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
            const arrowLeft  = `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;

            // ── Footer (range mode only) ──
            const footer = this.isRangeMode ? `
                <div class="cq-dp__footer">
                    <button type="button" class="cq-dp__btn cq-dp__btn--cancel">ביטול</button>
                    <button type="button" class="cq-dp__btn cq-dp__btn--apply">החל</button>
                </div>
            ` : '';

            this.popup.innerHTML = `
                <div class="cq-dp__header">
                    <button type="button" class="cq-dp__nav cq-dp__nav--prev"
                            aria-label="חודש קודם"${canGoPrev ? '' : ' disabled aria-disabled="true"'}>
                        ${arrowRight}
                    </button>
                    <div class="cq-dp__month-year">
                        <span class="cq-dp__month">${mName}</span>
                        <span class="cq-dp__year">${y}</span>
                    </div>
                    <button type="button" class="cq-dp__nav cq-dp__nav--next"
                            aria-label="חודש הבא"${canGoNext ? '' : ' disabled aria-disabled="true"'}>
                        ${arrowLeft}
                    </button>
                </div>
                <div class="cq-dp__grid" role="grid" aria-label="${mName} ${y}">
                    ${headerCells}${cells}
                </div>
                ${footer}
            `;

            // ── Event binding ──
            this.popup.querySelector('.cq-dp__nav--prev').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!e.currentTarget.hasAttribute('disabled')) this._navigateMonth(-1);
            });
            this.popup.querySelector('.cq-dp__nav--next').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!e.currentTarget.hasAttribute('disabled')) this._navigateMonth(1);
            });

            this.popup.querySelectorAll('.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)')
                .forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this._selectDate(btn.dataset.date);
                    });
                });

            // Range mode: bind Apply/Cancel buttons
            if (this.isRangeMode) {
                const applyBtn = this.popup.querySelector('.cq-dp__btn--apply');
                const cancelBtn = this.popup.querySelector('.cq-dp__btn--cancel');

                if (applyBtn) {
                    applyBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this._applyRange();
                    });
                }
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this._cancelRange();
                    });
                }
            }

            // Stop popup-internal clicks from reaching the outside-click handler.
            this.popup.addEventListener('click', (e) => e.stopPropagation());
        }

        _navigateMonth(delta) {
            this.viewMonth += delta;
            if (this.viewMonth > 11) { this.viewMonth = 0;  this.viewYear++; }
            if (this.viewMonth < 0)  { this.viewMonth = 11; this.viewYear--; }
            this._render();
            this._focusSelectedOrFirst();
        }

        /**
         * Commits the selected date (single mode), or updates temp range (range mode).
         * Single mode: fires native events and closes immediately.
         * Range mode: updates temp start/end, re-renders, stays open.
         *
         * @param {string} isoDate – YYYY-MM-DD
         */
        _selectDate(isoDate) {
            if (this.isRangeMode) {
                this._selectRangeDate(isoDate);
            } else {
                // Single mode: original behavior
                this.input.value = isoDate;
                this.input.dispatchEvent(new Event('input',  { bubbles: true }));
                this.input.dispatchEvent(new Event('change', { bubbles: true }));
                this.close();
            }
        }

        /**
         * Range mode: select a date in the range.
         * Logic:
         * - If no start: set start
         * - If start but no end: set end (if after start and within 30 days), ignore if before start
         * - If both start and end: reset and start new range
         *
         * @param {string} isoDate – YYYY-MM-DD
         */
        _selectRangeDate(isoDate) {
            const selectedDate = this._parseISODate(isoDate);
            if (!selectedDate) return;

            // Case 1: Full range exists (both start and end) – reset and start new range
            if (this.tempStart && this.tempEnd) {
                this.tempStart = selectedDate;
                this.tempEnd = null;
                console.log('Range reset, new start:', isoDate);
                this._render();
                return;
            }

            // Case 2: No start yet – set start
            if (!this.tempStart) {
                this.tempStart = selectedDate;
                this.tempEnd = null;
                console.log('Range start set:', isoDate);
                this._render();
                return;
            }

            // Case 3: Start exists, clicking on the same day – set as end (single day range)
            if (this._isSameDay(selectedDate, this.tempStart)) {
                this.tempEnd = selectedDate;
                console.log('Range end set to same day:', isoDate, '(1 day)');
                this._render();
                return;
            }

            // Case 4: Start exists, clicking before start – reset and start new range
            if (selectedDate < this.tempStart) {
                this.tempStart = selectedDate;
                this.tempEnd = null;
                console.log('Range reset with earlier date, new start:', isoDate);
                this._render();
                return;
            }

            // Case 5: Start exists, clicking after start – set end (if within 30 days)
            const diffMs = selectedDate - this.tempStart;
            const diffDays = Math.floor(diffMs / (24 * 60 * 60 * 1000));

            if (diffDays > 30) {
                console.warn('Range exceeds 30 days, not setting end');
                this._showRangeWarning();
                return;
            }

            this.tempEnd = selectedDate;
            console.log('Range end set:', isoDate, `(${diffDays + 1} days)`);
            this._render();
        }

        /**
         * Show warning when range exceeds 30 days
         */
        _showRangeWarning() {
            // Create a temporary warning element
            const warning = document.createElement('div');
            warning.className = 'cq-dp__warning';
            warning.textContent = 'ניתן לבחור עד 30 יום';
            warning.style.cssText = `
                position: absolute;
                bottom: 8px;
                left: 50%;
                transform: translateX(-50%);
                background: #fff6e5;
                border: 1px solid #ffd591;
                color: #8a5a00;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 500;
                font-family: var(--font-primary);
                z-index: 10;
                animation: cq-dp-warning-fade 2s ease-in-out;
            `;

            if (this.popup) {
                this.popup.appendChild(warning);
                setTimeout(() => {
                    if (warning.parentNode) {
                        warning.parentNode.removeChild(warning);
                    }
                }, 2000);
            }
        }

        /**
         * Apply selected range: update inputs and close
         */
        _applyRange() {
            if (!this.tempStart) {
                console.log('No range selected, closing');
                this.close();
                return;
            }

            // If only start is selected, use it for both from and to
            const fromDate = this._toISOFromDate(this.tempStart);
            const toDate   = this.tempEnd ? this._toISOFromDate(this.tempEnd) : fromDate;

            // Update both inputs
            this.fromInput.value = fromDate;
            this.toInput.value   = toDate;

            // Dispatch events to trigger change handlers
            this.fromInput.dispatchEvent(new Event('input',  { bubbles: true }));
            this.fromInput.dispatchEvent(new Event('change', { bubbles: true }));
            this.toInput.dispatchEvent(new Event('input',  { bubbles: true }));
            this.toInput.dispatchEvent(new Event('change', { bubbles: true }));

            console.log('Range applied:', { fromDate, toDate });
            this.close();
        }

        /**
         * Cancel range selection: close without applying
         */
        _cancelRange() {
            console.log('Range selection cancelled');
            this.close();
        }

        // ── Private: Positioning ──────────────────────────────────────────────

        _position() {
            if (!this.popup) return;

            const isMobile = window.innerWidth < 600;

            if (isMobile) {
                this.popup.classList.add('cq-dp--mobile');
                this._showBackdrop();
                return;
            }

            this.popup.classList.remove('cq-dp--mobile');

            const rect = this.field.getBoundingClientRect();
            const vw   = window.innerWidth;
            const vh   = window.innerHeight;

            // Temporarily hide to measure natural dimensions
            this.popup.style.visibility = 'hidden';
            this.popup.style.position   = 'fixed';
            this.popup.style.top        = '-9999px';
            this.popup.style.left       = '-9999px';
            const pw = this.popup.offsetWidth  || 296;
            const ph = this.popup.offsetHeight || 320;
            this.popup.style.visibility = '';

            // Vertical: prefer below field, fall back to above
            let top = rect.bottom + 6;
            if (top + ph > vh - 8) {
                top = rect.top - ph - 6;
            }
            top = Math.max(8, Math.min(top, vh - ph - 8));

            // Horizontal (RTL): align right edge of popup with right edge of field
            let left = rect.right - pw;
            left = Math.max(8, Math.min(left, vw - pw - 8));

            this.popup.style.top  = `${top}px`;
            this.popup.style.left = `${left}px`;
        }

        _onResize() {
            if (this._open && this.popup) this._position();
        }

        // ── Private: Backdrop (mobile) ────────────────────────────────────────

        _showBackdrop() {
            if (this._backdrop) return;
            const bd = document.createElement('div');
            bd.className = 'cq-dp-backdrop';
            // Insert backdrop before popup in DOM so it renders below
            document.body.insertBefore(bd, this.popup);
            this._backdrop = bd;

            requestAnimationFrame(() => {
                requestAnimationFrame(() => { bd.classList.add('cq-dp-backdrop--visible'); });
            });

            bd.addEventListener('click', () => this.close());
        }

        _removeBackdrop() {
            if (!this._backdrop) return;
            const bd       = this._backdrop;
            this._backdrop = null;
            bd.classList.remove('cq-dp-backdrop--visible');
            setTimeout(() => { if (bd.parentNode) bd.parentNode.removeChild(bd); }, 180);
        }

        // ── Private: Events ───────────────────────────────────────────────────

        _onOutsideClick(e) {
            if (!this._open || !this.popup) return;
            if (this.popup.contains(e.target))  return;
            
            // In range mode, don't close if clicking on either field
            if (this.isRangeMode) {
                if (this.fromField && this.fromField.contains(e.target)) return;
                if (this.toField && this.toField.contains(e.target)) return;
            } else {
                if (this.field.contains(e.target))  return;
            }
            
            if (this._backdrop && this._backdrop.contains(e.target)) return;
            
            // In range mode, cancel on outside click
            if (this.isRangeMode) {
                this._cancelRange();
            } else {
                this.close();
            }
        }

        _onKeydown(e) {
            if (!this._open || !this.popup) return;

            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    if (this.isRangeMode) {
                        this._cancelRange();
                    } else {
                        this.close();
                    }
                    break;

                case 'ArrowLeft':
                case 'ArrowRight':
                case 'ArrowUp':
                case 'ArrowDown':
                    this._handleGridArrow(e);
                    break;

                case 'Enter':
                case ' ': {
                    const focused = this.popup.querySelector('.cq-dp__day:focus');
                    if (focused && !focused.disabled && focused.dataset.date) {
                        e.preventDefault();
                        this._selectDate(focused.dataset.date);
                    }
                    break;
                }

                case 'PageUp':
                    e.preventDefault();
                    this._navigateMonth(e.shiftKey ? -12 : -1);
                    break;

                case 'PageDown':
                    e.preventDefault();
                    this._navigateMonth(e.shiftKey ? 12 : 1);
                    break;

                case 'Home': {
                    // Jump to first enabled day of current view
                    e.preventDefault();
                    const first = this.popup.querySelector(
                        '.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)'
                    );
                    if (first) { this._moveFocusTo(first); }
                    break;
                }

                case 'End': {
                    // Jump to last enabled day of current view
                    e.preventDefault();
                    const all  = this.popup.querySelectorAll(
                        '.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)'
                    );
                    if (all.length) { this._moveFocusTo(all[all.length - 1]); }
                    break;
                }
            }
        }

        /**
         * Roving-tabindex arrow navigation inside the day grid.
         * RTL: ArrowLeft moves FORWARD (+1 in array), ArrowRight moves BACKWARD (-1).
         */
        _handleGridArrow(e) {
            e.preventDefault();

            const grid = this.popup ? this.popup.querySelector('.cq-dp__grid') : null;
            if (!grid) return;

            const all     = Array.from(grid.querySelectorAll('.cq-dp__day:not(.cq-dp__day--empty)'));
            const focused = grid.querySelector('.cq-dp__day:focus');

            if (!focused) { this._focusSelectedOrFirst(); return; }

            const idx = all.indexOf(focused);
            let newIdx = idx;

            switch (e.key) {
                case 'ArrowLeft':  newIdx = idx + 1; break; // RTL: forward in reading direction
                case 'ArrowRight': newIdx = idx - 1; break; // RTL: backward
                case 'ArrowDown':  newIdx = idx + 7; break;
                case 'ArrowUp':    newIdx = idx - 7; break;
            }

            if (newIdx < 0) {
                this._navigateMonth(-1);
            } else if (newIdx >= all.length) {
                this._navigateMonth(1);
            } else {
                const target = all[newIdx];
                if (target && !target.disabled) {
                    this._moveFocusTo(target, all);
                }
            }
        }

        /**
         * @param {HTMLButtonElement} target
         * @param {HTMLButtonElement[]} [allDays]
         */
        _moveFocusTo(target, allDays) {
            if (!allDays) {
                allDays = Array.from(this.popup.querySelectorAll(
                    '.cq-dp__day:not(.cq-dp__day--empty)'
                ));
            }
            allDays.forEach(d => d.setAttribute('tabindex', '-1'));
            target.setAttribute('tabindex', '0');
            target.focus();
        }

        _focusSelectedOrFirst() {
            if (!this.popup) return;
            const sel   = this.popup.querySelector('.cq-dp__day--selected:not([disabled])');
            const tod   = this.popup.querySelector('.cq-dp__day--today:not([disabled])');
            const first = this.popup.querySelector(
                '.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)'
            );
            const target = sel || tod || first;
            if (target) { this._moveFocusTo(target); }
        }

        // ── Private: Date Utilities ───────────────────────────────────────────

        _todayISO() {
            const d = new Date();
            return this._toISO(d.getFullYear(), d.getMonth() + 1, d.getDate());
        }

        /**
         * @param {number} y
         * @param {number} m  1-based month
         * @param {number} d
         * @returns {string} YYYY-MM-DD
         */
        _toISO(y, m, d) {
            return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        }

        /**
         * Parse ISO date string (YYYY-MM-DD) to Date object at start of day
         * @param {string} isoDate
         * @returns {Date|null}
         */
        _parseISODate(isoDate) {
            if (!isoDate || typeof isoDate !== 'string') return null;
            const parts = isoDate.split('-').map(Number);
            if (parts.length !== 3 || parts.some(isNaN)) return null;
            const d = new Date(parts[0], parts[1] - 1, parts[2]);
            d.setHours(0, 0, 0, 0);
            return d;
        }

        /**
         * Convert Date object to ISO string (YYYY-MM-DD)
         * @param {Date} dateObj
         * @returns {string}
         */
        _toISOFromDate(dateObj) {
            if (!dateObj) return '';
            return this._toISO(
                dateObj.getFullYear(),
                dateObj.getMonth() + 1,
                dateObj.getDate()
            );
        }

        /**
         * Check if two Date objects represent the same day
         * @param {Date} d1
         * @param {Date} d2
         * @returns {boolean}
         */
        _isSameDay(d1, d2) {
            if (!d1 || !d2) return false;
            return d1.getFullYear() === d2.getFullYear() &&
                   d1.getMonth() === d2.getMonth() &&
                   d1.getDate() === d2.getDate();
        }

        // ── Static API ────────────────────────────────────────────────────────

        /**
         * Auto-initializes date pickers on all matching inputs.
         * Safe to call multiple times; already-initialized inputs are skipped.
         * 
         * Range mode: initialized once per fromDate/toDate pair (both share same instance).
         */
        static init() {
            // Track range pairs to avoid double-initialization
            const processedRanges = new Set();

            document.querySelectorAll('.bcm-field--native input[type="date"]').forEach(input => {
                if (input.getAttribute('data-cq-datepicker')) return;
                
                const field = input.closest('.bcm-field--native');
                if (!field) return;

                const filterName = input.getAttribute('data-filter');

                // Range mode: fromDate or toDate
                if (filterName === 'fromDate' || filterName === 'toDate') {
                    // Find the container (modal or filters section)
                    const container = field.closest('.bcm-filters, #bcm-expanded-modal');
                    if (!container) return;

                    // Create unique key for this range pair
                    const rangeKey = container.id || container.className;
                    if (processedRanges.has(rangeKey)) return;
                    processedRanges.add(rangeKey);

                    // Initialize once with either field (will handle both)
                    new ClinicQueueDatePicker(input, field);
                } else {
                    // Single mode
                    new ClinicQueueDatePicker(input, field);
                }
            });
        }
    }

    // ── Auto-init ─────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ClinicQueueDatePicker.init);
    } else {
        ClinicQueueDatePicker.init();
    }

    window.ClinicQueueDatePicker = ClinicQueueDatePicker;

}());
