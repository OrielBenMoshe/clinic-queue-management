/**
 * Clinic Queue Date Picker
 *
 * Custom RTL date picker for input[type="date"] inside .bcm-field--native containers.
 * Supports single-date and range (fromDate/toDate) modes with day/month/year views.
 */
/* global window */
'use strict';

(function () {

    const MONTH_NAMES = [
        'ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני',
        'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר',
    ];

    const DAY_ABBREVS = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳', 'ש׳'];

    const SVG_PREV = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    const SVG_NEXT = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    const MOBILE_BP = 600;
    const RANGE_MAX_DAYS = 30;
    const FUTURE_YEARS = 4;
    const MAX_PAST_YEARS = 150;
    const MS_DAY = 86400000;

    /** @param {number} y @param {number} m 1-based @param {number} d @returns {string} */
    const toISO = (y, m, d) => `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

    const todayISO = () => {
        const d = new Date();
        return toISO(d.getFullYear(), d.getMonth() + 1, d.getDate());
    };

    const defaultMinISO = () => {
        const d = new Date();
        d.setFullYear(d.getFullYear() - MAX_PAST_YEARS);
        return toISO(d.getFullYear(), d.getMonth() + 1, d.getDate());
    };

    /** @param {string} iso @returns {Date|null} */
    const parseISO = (iso) => {
        if (!iso || typeof iso !== 'string') return null;
        const [y, m, d] = iso.split('-').map(Number);
        if ([y, m, d].some(Number.isNaN)) return null;
        const date = new Date(y, m - 1, d);
        date.setHours(0, 0, 0, 0);
        return date;
    };

    /** @param {Date|null} date @returns {string} */
    const isoFromDate = (date) => date
        ? toISO(date.getFullYear(), date.getMonth() + 1, date.getDate())
        : '';

    /** @param {Date|null} a @param {Date|null} b @returns {boolean} */
    const isSameDay = (a, b) => a && b
        && a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();

    /** @param {HTMLInputElement} input */
    const disableNativeInput = (input) => {
        input.setAttribute('data-cq-datepicker', 'true');
        input.style.pointerEvents = 'none';
    };

    /** @param {HTMLElement} field */
    const prepareTriggerField = (field) => {
        if (!field.hasAttribute('tabindex')) field.setAttribute('tabindex', '0');
        field.setAttribute('role', 'button');
        field.setAttribute('aria-haspopup', 'dialog');
        field.setAttribute('aria-expanded', 'false');
    };

    /** @param {HTMLInputElement} input @param {string} iso */
    const commitInputValue = (input, iso) => {
        input.value = iso;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    class ClinicQueueDatePicker {

        /** @param {HTMLInputElement} inputEl @param {HTMLElement} fieldEl */
        constructor(inputEl, fieldEl) {
            this.input = inputEl;
            this.field = fieldEl;
            this.popup = null;
            this._backdrop = null;
            this._open = false;
            this.viewYear = 0;
            this.viewMonth = 0;
            this.viewMode = 'days';
            this.triggerMode = inputEl.getAttribute('data-cq-dp-trigger')
                || fieldEl.getAttribute('data-cq-dp-trigger')
                || 'field';

            inputEl._cqDatePicker = this;

            const filter = this.input.getAttribute('data-filter');
            this.isRangeMode = filter === 'fromDate' || filter === 'toDate';

            if (this.isRangeMode) {
                this.tempStart = null;
                this.tempEnd = null;
                this.fromInput = null;
                this.toInput = null;
                this.fromField = null;
                this.toField = null;
            }

            this._onOutsideClick = this._onOutsideClick.bind(this);
            this._onKeydown = this._onKeydown.bind(this);
            this._onResize = this._onResize.bind(this);
            this._onPopupClick = this._onPopupClick.bind(this);

            this._attach();
            this._setupAttributeObserver();
        }

        // ── Setup ───────────────────────────────────────────────────────────

        _attach() {
            disableNativeInput(this.input);

            if (this.isRangeMode) {
                this._attachRangeMode();
                return;
            }

            if (this.triggerMode === 'icon-only') {
                this._bindIconOnlyTriggers();
                return;
            }

            prepareTriggerField(this.field);
            this._bindToggle(this.field);
        }

        _bindIconOnlyTriggers() {
            const triggers = this.field.querySelectorAll('[data-cq-dp-open]');
            if (!triggers.length) {
                prepareTriggerField(this.field);
                this._bindToggle(this.field);
                return;
            }

            triggers.forEach((trigger) => {
                if (trigger.tagName === 'BUTTON' && !trigger.hasAttribute('type')) {
                    trigger.setAttribute('type', 'button');
                }
                if (!trigger.hasAttribute('tabindex')) {
                    trigger.setAttribute('tabindex', '0');
                }
                trigger.setAttribute('aria-haspopup', 'dialog');
                trigger.setAttribute('aria-expanded', 'false');
                this._bindToggle(trigger);
            });
        }

        _attachRangeMode() {
            const container = this.field.closest('.bcm-filters, #bcm-expanded-modal');
            if (!container) return;

            const fromInput = container.querySelector('input[data-filter="fromDate"]');
            const toInput = container.querySelector('input[data-filter="toDate"]');
            if (!fromInput || !toInput) return;

            this.fromInput = fromInput;
            this.toInput = toInput;
            this.fromField = fromInput.closest('.bcm-field--native');
            this.toField = toInput.closest('.bcm-field--native');

            [fromInput, toInput].forEach(disableNativeInput);
            [this.fromField, this.toField].forEach((field) => {
                if (field) {
                    prepareTriggerField(field);
                    this._bindToggle(field);
                }
            });
        }

        /** @param {HTMLElement} field */
        _bindToggle(field) {
            const toggle = (e) => {
                e.stopPropagation();
                this._open ? this.close() : this.open();
            };

            field.addEventListener('click', toggle);
            field.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                e.stopPropagation();
                toggle(e);
            });
        }

        _setupAttributeObserver() {
            const inputs = this.isRangeMode && this.fromInput && this.toInput
                ? [this.fromInput, this.toInput]
                : [this.input];

            this._observer = new MutationObserver((mutations) => {
                const changed = mutations.some(
                    (m) => m.type === 'attributes' && (m.attributeName === 'min' || m.attributeName === 'max')
                );
                if (changed && this._open && this.popup) this._render();
            });

            inputs.forEach((input) => {
                this._observer.observe(input, { attributes: true, attributeFilter: ['min', 'max'] });
            });
        }

        // ── Public API ──────────────────────────────────────────────────────

        open() {
            if (this._open) return;

            this._open = true;
            this.viewMode = 'days';
            this._initViewState();

            this._createPopup();
            this._render();
            this._position();
            this._setExpanded(true);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    this.popup?.classList.add('cq-dp--visible');
                    this._focusSelectedOrFirst();
                });
            });

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

            this._setExpanded(false);
            this._removeBackdrop();

            if (this.popup) {
                this.popup.classList.remove('cq-dp--visible');
                const node = this.popup;
                this.popup = null;
                setTimeout(() => node.remove(), 180);
            }

            if (this.triggerMode === 'icon-only') {
                this.field?.querySelector('[data-cq-dp-open]')?.focus();
            } else {
                (this.isRangeMode ? this.fromField : this.field)?.focus();
            }
        }

        // ── View state ──────────────────────────────────────────────────────

        _initViewState() {
            if (this.isRangeMode) {
                this.tempStart = parseISO(this.fromInput?.value || '');
                this.tempEnd = parseISO(this.toInput?.value || '');
            }

            const anchor = this.tempStart
                || parseISO(this.input.value)
                || new Date();

            this.viewYear = anchor.getFullYear();
            this.viewMonth = anchor.getMonth();
        }

        /** @param {boolean} expanded */
        _setExpanded(expanded) {
            const fields = this.isRangeMode
                ? [this.fromField, this.toField]
                : [this.field];

            fields.forEach((field) => {
                if (!field) return;
                if (this.triggerMode !== 'icon-only') {
                    field.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }
                field.classList.toggle('cq-datepicker-open', expanded);
            });

            if (this.triggerMode === 'icon-only' && this.field) {
                this.field.querySelectorAll('[data-cq-dp-open]').forEach((trigger) => {
                    trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                });
            }
        }

        /** @param {HTMLInputElement|null} input @returns {string} */
        _resolveMinDate(input) {
            const explicit = input?.getAttribute('min');
            return explicit || defaultMinISO();
        }

        /** @returns {{ minDate: string, maxDate: string }} */
        _getBounds() {
            if (this.isRangeMode && this.fromInput && this.toInput) {
                return {
                    minDate: this._resolveMinDate(this.fromInput),
                    maxDate: this.toInput.getAttribute('max') || '',
                };
            }
            return {
                minDate: this._resolveMinDate(this.input),
                maxDate: this.input.getAttribute('max') || '',
            };
        }

        /** @param {number} year @param {number} month 0-based */
        _isMonthEnabled(year, month) {
            const { minDate, maxDate } = this._getBounds();
            const first = toISO(year, month + 1, 1);
            const last = toISO(year, month + 1, new Date(year, month + 1, 0).getDate());
            if (minDate && last < minDate) return false;
            if (maxDate && first > maxDate) return false;
            return true;
        }

        /** @param {number} year */
        _isYearEnabled(year) {
            return MONTH_NAMES.some((_, month) => this._isMonthEnabled(year, month));
        }

        _getMaxAllowedISO() {
            const d = new Date();
            d.setFullYear(d.getFullYear() + FUTURE_YEARS);
            return toISO(d.getFullYear(), d.getMonth() + 1, d.getDate());
        }

        // ── Render ──────────────────────────────────────────────────────────

        _createPopup() {
            const popup = document.createElement('div');
            popup.className = 'clinic-queue-datepicker';
            popup.setAttribute('role', 'dialog');
            popup.setAttribute('aria-modal', 'true');
            popup.setAttribute('dir', 'rtl');
            popup.addEventListener('click', this._onPopupClick);
            document.body.appendChild(popup);
            this.popup = popup;
        }

        _render() {
            if (!this.popup) return;

            const builders = {
                days: () => this._buildDaysMarkup(),
                months: () => this._buildMonthsMarkup(),
                years: () => this._buildYearsMarkup(),
            };

            this.popup.innerHTML = builders[this.viewMode]?.() || builders.days();
            if (this._open) this._position();
        }

        /** @param {{ prevLabel: string, nextLabel: string, canGoPrev: boolean, canGoNext: boolean, titleHtml: string, bodyHtml: string }} cfg */
        _buildShell(cfg) {
            const footer = this.isRangeMode ? `
                <div class="cq-dp__footer">
                    <button type="button" class="cq-dp__btn cq-dp__btn--cancel" data-action="cancel">ביטול</button>
                    <button type="button" class="cq-dp__btn cq-dp__btn--apply" data-action="apply">החל</button>
                </div>` : '';

            const dis = (ok) => (ok ? '' : ' disabled aria-disabled="true"');

            return `
                <div class="cq-dp__header">
                    <button type="button" class="cq-dp__nav cq-dp__nav--prev" data-action="prev"${dis(cfg.canGoPrev)} aria-label="${cfg.prevLabel}">${SVG_PREV}</button>
                    <div class="cq-dp__month-year">${cfg.titleHtml}</div>
                    <button type="button" class="cq-dp__nav cq-dp__nav--next" data-action="next"${dis(cfg.canGoNext)} aria-label="${cfg.nextLabel}">${SVG_NEXT}</button>
                </div>
                ${cfg.bodyHtml}
                ${footer}`;
        }

        _buildDaysMarkup() {
            const { minDate, maxDate } = this._getBounds();
            const today = todayISO();
            const y = this.viewYear;
            const m = this.viewMonth;
            const mName = MONTH_NAMES[m];
            const daysInMonth = new Date(y, m + 1, 0).getDate();
            const selVal = this.isRangeMode ? '' : (this.input.value || '');

            const headerCells = DAY_ABBREVS.map((n) => `<span class="cq-dp__weekday" aria-hidden="true">${n}</span>`).join('');
            const offset = new Array(new Date(y, m, 1).getDay()).fill('<span class="cq-dp__day cq-dp__day--empty" aria-hidden="true"></span>').join('');

            const dayCells = Array.from({ length: daysInMonth }, (_, i) => {
                const day = i + 1;
                const iso = toISO(y, m + 1, day);
                const disabled = (minDate && iso < minDate) || (maxDate && iso > maxDate);
                const isToday = iso === today;
                const isSel = !this.isRangeMode && iso === selVal;

                const classes = ['cq-dp__day'];
                if (disabled) classes.push('cq-dp__day--disabled');
                if (isToday) classes.push('cq-dp__day--today');
                if (isSel) classes.push('cq-dp__day--selected');

                if (this.isRangeMode) {
                    const dateObj = parseISO(iso);
                    if (this.tempStart && isSameDay(dateObj, this.tempStart)) classes.push('cq-dp__day--start');
                    if (this.tempEnd && isSameDay(dateObj, this.tempEnd)) classes.push('cq-dp__day--end');
                    if (this.tempStart && this.tempEnd && dateObj > this.tempStart && dateObj < this.tempEnd) {
                        classes.push('cq-dp__day--in-range');
                    }
                }

                const attrs = [
                    `class="${classes.join(' ')}"`,
                    `data-date="${iso}"`,
                    `aria-label="${day} ${mName} ${y}"`,
                    `tabindex="${!disabled && (isSel || (isToday && !selVal)) ? '0' : '-1'}"`,
                ];
                if (disabled) attrs.push('disabled aria-disabled="true"');
                if (isSel) attrs.push('aria-pressed="true"');

                return `<button type="button" ${attrs.join(' ')}>${day}</button>`;
            }).join('');

            const prevLast = toISO(m === 0 ? y - 1 : y, m === 0 ? 12 : m, new Date(y, m, 0).getDate());
            const nextFirst = toISO(m === 11 ? y + 1 : y, m === 11 ? 1 : m + 2, 1);
            const maxAllowed = this._getMaxAllowedISO();

            this.popup.setAttribute('aria-label', `בחירת תאריך – ${mName} ${y}`);

            return this._buildShell({
                prevLabel: 'חודש קודם',
                nextLabel: 'חודש הבא',
                canGoPrev: !minDate || prevLast >= minDate,
                canGoNext: nextFirst <= maxAllowed && (!maxDate || nextFirst <= maxDate),
                titleHtml: `
                    <button type="button" class="cq-dp__month cq-dp__pick-month" data-action="view-months" aria-label="בחירת חודש">${mName}</button>
                    <button type="button" class="cq-dp__year cq-dp__pick-year" data-action="view-years" aria-label="בחירת שנה">${y}</button>`,
                bodyHtml: `<div class="cq-dp__grid" role="grid" aria-label="${mName} ${y}">${headerCells}${offset}${dayCells}</div>`,
            });
        }

        _buildMonthsMarkup() {
            const y = this.viewYear;
            this.popup.setAttribute('aria-label', `בחירת חודש – ${y}`);

            const cells = MONTH_NAMES.map((name, index) => {
                const enabled = this._isMonthEnabled(y, index);
                const selected = index === this.viewMonth;
                const classes = ['cq-dp__month-cell'];
                if (!enabled) classes.push('cq-dp__month-cell--disabled');
                if (selected) classes.push('cq-dp__month-cell--selected');

                const attrs = [`class="${classes.join(' ')}"`, `data-month="${index}"`];
                if (!enabled) attrs.push('disabled aria-disabled="true"');
                if (selected) attrs.push('aria-pressed="true"');

                return `<button type="button" ${attrs.join(' ')}>${name}</button>`;
            }).join('');

            return this._buildShell({
                prevLabel: 'שנה קודמת',
                nextLabel: 'שנה הבאה',
                canGoPrev: this._isYearEnabled(y - 1),
                canGoNext: this._isYearEnabled(y + 1),
                titleHtml: `
                    <button type="button" class="cq-dp__year cq-dp__pick-year" data-action="view-years" aria-label="בחירת שנה">${y}</button>
                    <span class="cq-dp__view-label">בחירת חודש</span>`,
                bodyHtml: `<div class="cq-dp__months-grid" role="listbox" aria-label="חודשים">${cells}</div>`,
            });
        }

        _buildYearsMarkup() {
            const start = Math.floor(this.viewYear / 12) * 12;
            const end = start + 11;

            this.popup.setAttribute('aria-label', `בחירת שנה – ${start}–${end}`);

            const cells = Array.from({ length: 12 }, (_, i) => {
                const year = start + i;
                const enabled = this._isYearEnabled(year);
                const selected = year === this.viewYear;
                const classes = ['cq-dp__year-cell'];
                if (!enabled) classes.push('cq-dp__year-cell--disabled');
                if (selected) classes.push('cq-dp__year-cell--selected');

                const attrs = [`class="${classes.join(' ')}"`, `data-year="${year}"`];
                if (!enabled) attrs.push('disabled aria-disabled="true"');
                if (selected) attrs.push('aria-pressed="true"');

                return `<button type="button" ${attrs.join(' ')}>${year}</button>`;
            }).join('');

            return this._buildShell({
                prevLabel: '12 שנים קודמות',
                nextLabel: '12 שנים הבאות',
                canGoPrev: this._isYearEnabled(start - 1),
                canGoNext: this._isYearEnabled(end + 1),
                titleHtml: `<span class="cq-dp__view-label">${start} – ${end}</span>`,
                bodyHtml: `<div class="cq-dp__years-grid" role="listbox" aria-label="שנים">${cells}</div>`,
            });
        }

        // ── Navigation & selection ──────────────────────────────────────────

        /** @param {MouseEvent} e */
        _onPopupClick(e) {
            e.stopPropagation();

            const btn = e.target.closest('button');
            if (!btn || btn.disabled) return;

            const { action, date, month, year } = btn.dataset;

            if (action === 'prev') return this._navPrev();
            if (action === 'next') return this._navNext();
            if (action === 'view-months') { this.viewMode = 'months'; return this._render(); }
            if (action === 'view-years') { this.viewMode = 'years'; return this._render(); }
            if (action === 'apply') return this._applyRange();
            if (action === 'cancel') return this.close();
            if (date) return this._selectDate(date);
            if (month != null) {
                this.viewMonth = Number(month);
                this.viewMode = 'days';
                this._render();
                return this._focusSelectedOrFirst();
            }
            if (year != null) {
                this.viewYear = Number(year);
                this.viewMode = 'months';
                return this._render();
            }
        }

        _navPrev() {
            if (this.viewMode === 'years') return this._shiftYearPage(-1);
            if (this.viewMode === 'months') return this._shiftYear(-1);
            this._shiftMonth(-1);
        }

        _navNext() {
            if (this.viewMode === 'years') return this._shiftYearPage(1);
            if (this.viewMode === 'months') return this._shiftYear(1);
            this._shiftMonth(1);
        }

        /** @param {number} delta */
        _shiftMonth(delta) {
            this.viewMonth += delta;
            if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; }
            if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; }
            this._render();
            this._focusSelectedOrFirst();
        }

        /** @param {number} delta */
        _shiftYear(delta) {
            this.viewYear += delta;
            this._render();
        }

        /** @param {number} delta */
        _shiftYearPage(delta) {
            this.viewYear += delta * 12;
            this._render();
        }

        /** @param {string} isoDate */
        _selectDate(isoDate) {
            if (this.isRangeMode) {
                this._selectRangeDate(isoDate);
                return;
            }

            commitInputValue(this.input, isoDate);
            this.close();
        }

        /** @param {string} isoDate */
        _selectRangeDate(isoDate) {
            const selected = parseISO(isoDate);
            if (!selected) return;

            if (this.tempStart && this.tempEnd) {
                this.tempStart = selected;
                this.tempEnd = null;
            } else if (!this.tempStart) {
                this.tempStart = selected;
                this.tempEnd = null;
            } else if (isSameDay(selected, this.tempStart)) {
                this.tempEnd = selected;
            } else if (selected < this.tempStart) {
                this.tempStart = selected;
                this.tempEnd = null;
            } else {
                const diffDays = Math.floor((selected - this.tempStart) / MS_DAY);
                if (diffDays > RANGE_MAX_DAYS) {
                    this._showRangeWarning();
                    return;
                }
                this.tempEnd = selected;
            }

            this._render();
        }

        _showRangeWarning() {
            if (!this.popup || this.popup.querySelector('.cq-dp__warning')) return;

            const warning = document.createElement('div');
            warning.className = 'cq-dp__warning';
            warning.textContent = 'ניתן לבחור עד 30 יום';
            this.popup.appendChild(warning);
            setTimeout(() => warning.remove(), 2000);
        }

        _applyRange() {
            if (!this.tempStart) {
                this.close();
                return;
            }

            const fromDate = isoFromDate(this.tempStart);
            const toDate = isoFromDate(this.tempEnd || this.tempStart);

            commitInputValue(this.fromInput, fromDate);
            commitInputValue(this.toInput, toDate);
            this.close();
        }

        // ── Positioning ─────────────────────────────────────────────────────

        _position() {
            if (!this.popup) return;

            const isMobile = window.innerWidth < MOBILE_BP;

            if (isMobile) {
                this.popup.classList.add('cq-dp--mobile');
                this._showBackdrop();
            } else {
                this.popup.classList.remove('cq-dp--mobile');
                this._removeBackdrop();
            }

            const rect = this.field.getBoundingClientRect();
            const { innerWidth: vw, innerHeight: vh } = window;
            const margin = isMobile ? 16 : 8;

            this.popup.style.visibility = 'hidden';
            this.popup.style.position = 'fixed';
            this.popup.style.top = '-9999px';
            this.popup.style.left = '-9999px';

            const pw = this.popup.offsetWidth || 296;
            const ph = this.popup.offsetHeight || 320;
            this.popup.style.visibility = '';

            let top;
            let left;

            if (isMobile) {
                left = (vw - pw) / 2;
                left = Math.max(margin, Math.min(left, vw - pw - margin));

                const belowFieldTop = rect.bottom + 6;
                const aboveFieldTop = rect.top - ph - 6;
                const centeredTop = (vh - ph) / 2;

                if (belowFieldTop + ph <= vh - margin) {
                    top = belowFieldTop;
                } else if (aboveFieldTop >= margin) {
                    top = aboveFieldTop;
                } else {
                    top = centeredTop;
                }

                top = Math.max(margin, Math.min(top, vh - ph - margin));
            } else {
                top = rect.bottom + 6;
                if (top + ph > vh - margin) top = rect.top - ph - 6;
                top = Math.max(margin, Math.min(top, vh - ph - margin));

                const fieldCenter = rect.left + rect.width / 2;
                left = fieldCenter - pw / 2;
                left = Math.max(margin, Math.min(left, vw - pw - margin));
            }

            this.popup.style.top = `${top}px`;
            this.popup.style.left = `${left}px`;
        }

        _onResize() {
            if (this._open && this.popup) this._position();
        }

        _showBackdrop() {
            if (this._backdrop) return;

            const bd = document.createElement('div');
            bd.className = 'cq-dp-backdrop';
            document.body.insertBefore(bd, this.popup);
            this._backdrop = bd;

            requestAnimationFrame(() => {
                requestAnimationFrame(() => bd.classList.add('cq-dp-backdrop--visible'));
            });

            bd.addEventListener('click', () => this.close());
        }

        _removeBackdrop() {
            if (!this._backdrop) return;

            const bd = this._backdrop;
            this._backdrop = null;
            bd.classList.remove('cq-dp-backdrop--visible');
            setTimeout(() => bd.remove(), 180);
        }

        // ── Events ──────────────────────────────────────────────────────────

        /** @param {MouseEvent} e */
        _onOutsideClick(e) {
            if (!this._open || !this.popup || this.popup.contains(e.target)) return;

            const triggers = this.isRangeMode
                ? [this.fromField, this.toField]
                : [this.field];

            if (triggers.some((field) => field?.contains(e.target))) return;
            if (this._backdrop?.contains(e.target)) return;

            this.close();
        }

        /** @param {KeyboardEvent} e */
        _onKeydown(e) {
            if (!this._open || !this.popup) return;

            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    if (this.viewMode === 'years') { this.viewMode = 'months'; this._render(); break; }
                    if (this.viewMode === 'months') { this.viewMode = 'days'; this._render(); this._focusSelectedOrFirst(); break; }
                    this.close();
                    break;

                case 'ArrowLeft':
                case 'ArrowRight':
                case 'ArrowUp':
                case 'ArrowDown':
                    if (this.viewMode === 'days') this._handleGridArrow(e);
                    break;

                case 'Enter':
                case ' ': {
                    const day = this.popup.querySelector('.cq-dp__day:focus:not([disabled])');
                    if (day?.dataset.date) {
                        e.preventDefault();
                        this._selectDate(day.dataset.date);
                    }
                    break;
                }

                case 'PageUp':
                    e.preventDefault();
                    if (this.viewMode === 'years') this._shiftYearPage(-1);
                    else if (this.viewMode === 'months') this._shiftYear(-1);
                    else this._shiftMonth(e.shiftKey ? -12 : -1);
                    break;

                case 'PageDown':
                    e.preventDefault();
                    if (this.viewMode === 'years') this._shiftYearPage(1);
                    else if (this.viewMode === 'months') this._shiftYear(1);
                    else this._shiftMonth(e.shiftKey ? 12 : 1);
                    break;

                case 'Home': {
                    e.preventDefault();
                    const first = this.popup.querySelector('.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)');
                    if (first) this._moveFocusTo(first);
                    break;
                }

                case 'End': {
                    e.preventDefault();
                    const days = this.popup.querySelectorAll('.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)');
                    if (days.length) this._moveFocusTo(days[days.length - 1]);
                    break;
                }

                default:
                    break;
            }
        }

        /** @param {KeyboardEvent} e */
        _handleGridArrow(e) {
            e.preventDefault();

            const grid = this.popup.querySelector('.cq-dp__grid');
            if (!grid) return;

            const days = [...grid.querySelectorAll('.cq-dp__day:not(.cq-dp__day--empty)')];
            const focused = grid.querySelector('.cq-dp__day:focus');
            if (!focused) return this._focusSelectedOrFirst();

            const idx = days.indexOf(focused);
            const moves = { ArrowLeft: 1, ArrowRight: -1, ArrowDown: 7, ArrowUp: -7 };
            const nextIdx = idx + (moves[e.key] || 0);

            if (nextIdx < 0) return this._shiftMonth(-1);
            if (nextIdx >= days.length) return this._shiftMonth(1);

            const target = days[nextIdx];
            if (target && !target.disabled) this._moveFocusTo(target, days);
        }

        /** @param {HTMLButtonElement} target @param {HTMLButtonElement[]} [days] */
        _moveFocusTo(target, days) {
            const list = days || [...this.popup.querySelectorAll('.cq-dp__day:not(.cq-dp__day--empty)')];
            list.forEach((day) => day.setAttribute('tabindex', '-1'));
            target.setAttribute('tabindex', '0');
            target.focus();
        }

        _focusSelectedOrFirst() {
            if (!this.popup) return;

            const target = this.popup.querySelector('.cq-dp__day--selected:not([disabled])')
                || this.popup.querySelector('.cq-dp__day--today:not([disabled])')
                || this.popup.querySelector('.cq-dp__day:not(.cq-dp__day--disabled):not(.cq-dp__day--empty)');

            if (target) this._moveFocusTo(target);
        }

        // ── Static API ──────────────────────────────────────────────────────

        /** @param {HTMLInputElement|null} inputEl @returns {ClinicQueueDatePicker|null} */
        static getInstance(inputEl) {
            return inputEl?._cqDatePicker || null;
        }

        static init() {
            const processedRanges = new Set();

            document.querySelectorAll('.bcm-field--native input[type="date"]').forEach((input) => {
                if (input.getAttribute('data-cq-datepicker')) return;

                const field = input.closest('.bcm-field--native');
                if (!field) return;

                const filter = input.getAttribute('data-filter');
                if (filter === 'fromDate' || filter === 'toDate') {
                    const container = field.closest('.bcm-filters, #bcm-expanded-modal');
                    if (!container) return;

                    const key = container.id || container.className;
                    if (processedRanges.has(key)) return;
                    processedRanges.add(key);
                }

                new ClinicQueueDatePicker(input, field);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ClinicQueueDatePicker.init);
    } else {
        ClinicQueueDatePicker.init();
    }

    window.ClinicQueueDatePicker = ClinicQueueDatePicker;

}());
