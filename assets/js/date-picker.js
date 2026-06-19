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

            this._onOutsideClick = this._onOutsideClick.bind(this);
            this._onKeydown      = this._onKeydown.bind(this);
            this._onResize       = this._onResize.bind(this);

            this._attach();
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

            // Make field focusable and expose semantics to assistive technologies.
            if (!this.field.hasAttribute('tabindex')) {
                this.field.setAttribute('tabindex', '0');
            }
            this.field.setAttribute('role', 'button');
            this.field.setAttribute('aria-haspopup', 'dialog');
            this.field.setAttribute('aria-expanded', 'false');

            /*
             * stopPropagation() prevents the jQuery delegated handler on the modal
             * root ($m) from running, so inputEl.showPicker() is never called.
             */
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

        // ── Public API ────────────────────────────────────────────────────────

        open() {
            if (this._open) return;
            this._open = true;

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

            this.field.setAttribute('aria-expanded', 'true');
            this.field.classList.add('cq-datepicker-open');

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

            this.field.setAttribute('aria-expanded', 'false');
            this.field.classList.remove('cq-datepicker-open');

            this._removeBackdrop();

            if (this.popup) {
                this.popup.classList.remove('cq-dp--visible');
                const p    = this.popup;
                this.popup = null;
                setTimeout(() => { if (p && p.parentNode) p.parentNode.removeChild(p); }, 180);
            }

            // Return focus to the trigger
            this.field.focus();
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

            const today    = this._todayISO();
            const minDate  = this.input.getAttribute('min')   || '';
            const maxDate  = this.input.getAttribute('max')   || '';
            const selVal   = this.input.value || '';
            const y        = this.viewYear;
            const m        = this.viewMonth;
            const mName    = MONTH_NAMES[m];

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
            for (let day = 1; day <= daysInMonth; day++) {
                const iso      = this._toISO(y, m + 1, day);
                const disabled = (minDate && iso < minDate) || (maxDate && iso > maxDate);
                const isToday  = iso === today;
                const isSel    = iso === selVal;

                let cls = 'cq-dp__day';
                if (disabled) cls += ' cq-dp__day--disabled';
                if (isToday)  cls += ' cq-dp__day--today';
                if (isSel)    cls += ' cq-dp__day--selected';

                const ariaLabel  = `${day} ${mName} ${y}`;
                /*
                 * tabindex management: the selected (or today-if-no-selection) day
                 * is in the tab ring; all others are -1 (roving tabindex pattern).
                 */
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
            const canGoPrev = !minDate || prevLastDay >= minDate;
            const canGoNext = !maxDate || nextFirstDay <= maxDate;

            // ── SVG arrows ──
            /*
             * In RTL flex layout the FIRST button is rightmost (prev month)
             * and the LAST button is leftmost (next month) – matching Hebrew
             * calendar convention (past on the right, future on the left).
             *
             * Prev (right side): right-pointing chevron ›
             * Next (left side):  left-pointing chevron  ‹
             */
            const arrowRight = `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
            const arrowLeft  = `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;

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
            `;

            // ── Event binding ──
            this.popup.querySelector('.cq-dp__nav--prev').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!e.currentTarget.disabled) this._navigateMonth(-1);
            });
            this.popup.querySelector('.cq-dp__nav--next').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!e.currentTarget.disabled) this._navigateMonth(1);
            });

            this.popup.querySelectorAll('.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)')
                .forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this._selectDate(btn.dataset.date);
                    });
                });

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
         * Commits the selected date, fires native events, and closes the picker.
         *
         * @param {string} isoDate – YYYY-MM-DD
         */
        _selectDate(isoDate) {
            this.input.value = isoDate;

            /*
             * Dispatch native input + change events so the jQuery delegated handlers
             * in booking-calendar-expanded-modal.js receive them normally:
             *   - syncNativeFieldDisplay() updates the visible shell text
             *   - normalizeDateRange()     enforces the 21-day cap
             *   - syncUpdateButtonState() enables/disables the "update results" button
             */
            this.input.dispatchEvent(new Event('input',  { bubbles: true }));
            this.input.dispatchEvent(new Event('change', { bubbles: true }));

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
            if (this.field.contains(e.target))  return;
            if (this._backdrop && this._backdrop.contains(e.target)) return;
            this.close();
        }

        _onKeydown(e) {
            if (!this._open || !this.popup) return;

            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    this.close();
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

        // ── Static API ────────────────────────────────────────────────────────

        /**
         * Auto-initializes date pickers on all matching inputs.
         * Safe to call multiple times; already-initialized inputs are skipped.
         */
        static init() {
            document.querySelectorAll('.bcm-field--native input[type="date"]').forEach(input => {
                if (input.getAttribute('data-cq-datepicker')) return;
                const field = input.closest('.bcm-field--native');
                if (!field) return;
                new ClinicQueueDatePicker(input, field);
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
