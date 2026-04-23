/**
 * Clinic Queue Management - Expanded Modal Module
 *
 * מודל "כל התורים" – תצוגה מורחבת של כל הסלוטים הפנויים כרשימת תאריכים
 * עם שדות פילטור.
 *
 * ארכיטקטורה:
 *   - HTML skeleton: views/booking-calendar-expanded-modal.php
 *     נרנדר פעם אחת ב-wp_footer (singleton) תחת <body id="bcm-expanded-modal">.
 *   - JS אחראי על: הצגה/הסתרה, מילוי selects, סינון, רנדור slots.
 *   - כל פתיחה מעדכנת את המודל לפי ה-core שפתח אותו.
 *     כך ניתן לתמוך במספר יומני תורים בעמוד.
 *
 * שיתוף עם core:
 *   core.appointmentData                         – הסלוטים שנטענו
 *   core.allSchedulers                           – כל היומנים
 *   core.treatmentType / core.schedulerId        – בחירות נוכחיות
 *   core.selectedDate                            – תאריך נבחר
 *   core.uiManager.formatTimeForDisplay()        – פורמט שעה (שימוש חוזר)
 *   core.dataManager.filterSchedulersByTreatment() – פילטור יומנים (שימוש חוזר)
 *   core.handleBookButtonClick()                 – זרימת הזמנה (שימוש חוזר)
 */
(function ($) {
    'use strict';

    const MODAL_SELECTOR = '#bcm-expanded-modal';
    const DAYS_PER_PAGE  = Number.MAX_SAFE_INTEGER;
    const MAX_RANGE_DAYS = 21; // הגבלה: עד 3 שבועות
    const MS_PER_DAY     = 24 * 60 * 60 * 1000;
    const UPDATE_COOLDOWN_MS = 2 * 60 * 1000; // 2 דקות בין עדכונים לאותו טווח תאריכים

    class BookingCalendarExpandedModal {

        /**
         * @param {BookingCalendarCore} core – instance של הלוח שפתח את המודל
         */
        constructor(core) {
            this.core   = core;
            this.$modal = null;
            this._rangeNoticeTimer = null;
            this._updateCooldownTimer = null;
            this._isUpdateLoading = false;
            this.lastRequestedDateRangeKey = '';
            this.lastRequestedDateRangeAt = 0;

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
        }

        /* ─────────────────────────────────────────
           PUBLIC API
        ───────────────────────────────────────── */

        /**
         * פותח את המודל ומאכלס אותו עם נתוני ה-core הנוכחי.
         * בגלל ש-Modal הוא singleton, הוא תמיד נמצא ב-<body>.
         */
        open() {
            this.$modal = $(MODAL_SELECTOR);

            if (!this.$modal.length) {
                window.BookingCalendarUtils.error('Expanded modal element not found in DOM (#bcm-expanded-modal)');
                return;
            }

            this.destroySelect2InModal();
            this.initFromCore();
            this.setDateDefaults();
            this.bindEvents();
            this.populateSelects();
            this.initializeSelect2InModal();
            this.renderResults();
            this.syncUpdateButtonState();

            this.$modal.attr('aria-hidden', 'false');

            // שני rAF: הראשון מפעיל display:flex, השני מפעיל ה-transition
            requestAnimationFrame(() => {
                this.$modal.addClass('bcm-open');
                requestAnimationFrame(() => {
                    this.$modal.find('.bcm-dialog').addClass('bcm-dialog--visible');
                });
            });

            $('body').addClass('bcm-body-lock');
            window.BookingCalendarUtils.log('Expanded modal opened by widget:', this.core.widgetId);
        }

        /**
         * סוגר את המודל (שומר אותו ב-DOM לשימוש חוזר)
         */
        close() {
            if (!this.$modal || !this.$modal.length) return;

            this.$modal.removeClass('bcm-open');
            this.$modal.find('.bcm-dialog').removeClass('bcm-dialog--visible');
            this.$modal.attr('aria-hidden', 'true');

            $('body').removeClass('bcm-body-lock');
            $(document).off('keydown.bcm-modal');

            window.BookingCalendarUtils.log('Expanded modal closed');
        }

        /* ─────────────────────────────────────────
           INIT FROM CORE STATE
        ───────────────────────────────────────── */

        /**
         * מאתחל את filterState מהסטייט הנוכחי של ה-core שפתח את המודל
         */
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
         * מגדיר ברירות מחדל לשדות תאריך ומעדכן את filterState
         */
        setDateDefaults() {
            const todayIso = this.getTodayIsoDate();
            let fromDate = '';
            let toDate = '';
            let fromTime = '';
            let toTime = '';

            const latestRequest = this.core.lastFreeTimeRequest || null;
            if (latestRequest && latestRequest.fromDateUTC && latestRequest.toDateUTC) {
                const parsedFrom = this.parseUtcRangeValue(latestRequest.fromDateUTC);
                const parsedTo = this.parseUtcRangeValue(latestRequest.toDateUTC);
                fromDate = parsedFrom.date;
                toDate = parsedTo.date;
                fromTime = parsedFrom.time;
                toTime = parsedTo.time;
            } else {
                // fallback: טווח ברירת מחדל של 3 שבועות (תואם את ה-API).
                const today = new Date();
                const endRange = new Date(today);
                endRange.setDate(endRange.getDate() + MAX_RANGE_DAYS);
                fromDate = window.BookingCalendarUtils.formatDate(today);
                toDate   = window.BookingCalendarUtils.formatDate(endRange);
            }

            this.filterState.fromDate = fromDate;
            this.filterState.toDate   = toDate;
            this.filterState.fromTime = fromTime;
            this.filterState.toTime   = toTime;

            const $fromDateInput = this.$modal.find('[data-filter="fromDate"]');
            $fromDateInput.attr('min', todayIso);

            // Hard clamp: never allow a date earlier than today.
            if (fromDate && fromDate < todayIso) {
                fromDate = todayIso;
                this.filterState.fromDate = todayIso;
            }

            $fromDateInput.val(fromDate);
            this.$modal.find('[data-filter="toDate"]').val(toDate);
            this.$modal.find('[data-filter="fromTime"]').val(fromTime);
            this.$modal.find('[data-filter="toTime"]').val(toTime);
            this.$modal.find('.bcm-day-cb').prop('checked', true);
            this.updateDateInputsBounds();
            this.updateFieldPlaceholderState();
        }

        /* ─────────────────────────────────────────
           POPULATE SELECTS
        ───────────────────────────────────────── */

        /**
         * ממלא את ה-select fields מהנתונים הקיימים ב-core
         */
        populateSelects() {
            this.populateTreatmentSelect();
            this.populateSchedulerSelect();
        }

        /**
         * ממלא רשימת סוגי טיפול — אותה לוגיקה כמו ב-field-manager
         */
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
         * ממלא רשימת יומנים/רופאים לפי סוג הטיפול הנוכחי.
         * משתמש ב-core.dataManager.filterSchedulersByTreatment() לשימוש חוזר.
         *
         * @param {jQuery} [$select] – ברירת מחדל: select מה-modal
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

            if (this.filterState.schedulerId) {
                $select.val(this.filterState.schedulerId);
            }

            window.BookingCalendarUtils.log('Expanded modal schedulers populated:', filtered.length);
        }

        /**
         * הורס Select2 משדות הבחירה במודל (לפני מילוי מחדש או סגירה)
         */
        destroySelect2InModal() {
            const $m = this.$modal;
            $m.find('[data-filter="treatmentType"], [data-filter="schedulerId"]').each(function () {
                const $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
            });
        }

        /**
         * מאתחל Select2 על שדות הבחירה במודל (אותה ספריה כמו ב-field-manager).
         * dropdownParent = המודל כדי שהדרופדאון לא ייחתך.
         */
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
         * אתחול Select2 ל-select בודד במודל (RTL, theme, dropdown בתוך המודל)
         *
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

        /* ─────────────────────────────────────────
           EVENTS
        ───────────────────────────────────────── */

        /**
         * קושר את כל האירועים של המודל.
         * מנקה events ישנים תחילה עם namespace .bcm-modal
         * כדי למנוע כפילות בין פתיחות שונות ומ-cores שונים.
         */
        bindEvents() {
            const $m = this.$modal;

            // ניקוי events ישנים
            $m.off('.bcm-modal');
            $(document).off('keydown.bcm-modal');

            // סגירה
            $m.on('click.bcm-modal', '.bcm-close-btn', () => this.close());
            $m.on('click.bcm-modal', e => { if ($(e.target).is(MODAL_SELECTOR)) this.close(); });
            $(document).on('keydown.bcm-modal', e => { if (e.key === 'Escape') this.close(); });

            // עדכון תוצאות – שולח בקשת free-time חדשה לטווח התאריכים שנבחר
            // (תחילת יום של מתאריך → סוף יום של עד תאריך). פילטרי השעות/ימים
            // ממשיכים לרוץ client-side על תשובת השרת.
            $m.on('click.bcm-modal', '.bcm-update-btn', () => {
                this.readFilters();
                if (this.isDateRangeUpdateBlocked()) {
                    this.syncUpdateButtonState();
                    return;
                }
                this.refetchForCurrentDateRange();
            });

            // טען עוד
            $m.on('click.bcm-modal', '.bcm-load-more-btn', () => {
                this.visibleDaysCount += DAYS_PER_PAGE;
                this.renderResults(true);
            });

            // בחירת slot
            $m.on('click.bcm-modal', '.bcm-slot', e => {
                const $slot = $(e.currentTarget);
                const date  = $slot.closest('.bcm-day-block').data('date');
                const time  = $slot.data('time');
                this.selectSlot(date, time);
            });

            // הזמן תור
            $m.on('click.bcm-modal', '.bcm-book-btn:not([disabled])', () => this.triggerBooking());

            // שינוי סוג טיפול → עדכן רשימת יומנים ואתחל מחדש Select2
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
            });

            // סינון מיידי: שדות שעות
            $m.on('input.bcm-modal change.bcm-modal', '[data-filter="fromTime"], [data-filter="toTime"]', () => {
                this.readFilters();
                this.applyFiltersAndRender();
            });

            // סינון מיידי: ימי שבוע
            $m.on('change.bcm-modal', '.bcm-day-cb', () => {
                this.readFilters();
                this.applyFiltersAndRender();
            });

            // Native date/time custom shell sync.
            $m.on('input.bcm-modal change.bcm-modal', '.bcm-input', e => {
                this.syncNativeFieldDisplay($(e.currentTarget));
            });

            // ניקוי שדות שעה (מהשעה/עד השעה) עם סינון מיידי.
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

            // Range limit: כששדה תאריך משתנה, בודקים שהטווח אינו חורג מ-3 שבועות.
            // אם חורג – מתקנים אוטומטית את הקצה הנגדי ומציגים חיווי.
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

        /**
         * קורא את ערכי הפילטרים מה-DOM ומעדכן את filterState
         */
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

            // גארד אחרון לפני שליחת בקשה – אכיפת טווח מקסימלי של 3 שבועות.
            this.normalizeDateRange('toDate', { silent: true });

            window.BookingCalendarUtils.log('Expanded modal filters applied:', this.filterState);
        }

        /* ─────────────────────────────────────────
           DATA FILTERING
        ───────────────────────────────────────── */

        /**
         * מחזיר את הנתונים לאחר סינון לפי filterState
         *
         * @return {Array}
         */
        getFilteredData() {
            const data = this.core.appointmentData || [];

            return data.reduce((acc, dayObj) => {
                const dateStr = dayObj.date?.appointment_date || (typeof dayObj.date === 'string' ? dayObj.date : '');
                if (!dateStr) return acc;

                if (this.filterState.fromDate && dateStr < this.filterState.fromDate) return acc;
                if (this.filterState.toDate   && dateStr > this.filterState.toDate)   return acc;

                // יום בשבוע (0 = ראשון)
                if (this.filterState.days.length < 7) {
                    const dow = String(new Date(dateStr + 'T12:00:00').getDay());
                    if (!this.filterState.days.includes(dow)) return acc;
                }

                // פילטר שעות
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

        /* ─────────────────────────────────────────
           RENDER
        ───────────────────────────────────────── */

        /**
         * מרנדר את רשימת התאריכים והסלוטים
         *
         * @param {boolean} preserveSelection – שמור בחירה קיימת
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
         * מרנדר בלוק יום אחד עם כותרת וגריד סלוטים.
         * משתמש ב-core.uiManager.formatTimeForDisplay() לשימוש חוזר.
         *
         * @param {string} dateStr
         * @param {Array}  slots
         * @return {string}
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

        /* ─────────────────────────────────────────
           SELECTION & BOOKING
        ───────────────────────────────────────── */

        /**
         * בוחר/מבטל בחירה של slot
         *
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

        /**
         * מעדכן מצב כפתור "הזמן תור" (disabled / active)
         */
        updateBookButton() {
            const $btn    = this.$modal.find('.bcm-book-btn');
            const canBook = !!(this.selectedDate && this.selectedTime);
            $btn.prop('disabled', !canBook)
                .attr('aria-disabled', canBook ? 'false' : 'true')
                .toggleClass('bcm-book-btn--active', canBook);
        }

        /**
         * מעביר את הבחירה ל-core ומפעיל את זרימת הזמנת התור הקיימת
         */
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

        /* ─────────────────────────────────────────
           HELPERS
        ───────────────────────────────────────── */

        /**
         * מחזיר מערך של כל היומנים (מנרמל object/array)
         *
         * @return {Array}
         */
        getSchedulersArray() {
            return Array.isArray(this.core.allSchedulers)
                ? this.core.allSchedulers
                : Object.values(this.core.allSchedulers || {});
        }

        /**
         * מחזיר תאריך בפורמט עברי, למשל: "יום שני, 17 במרץ"
         *
         * @param {string} dateStr – YYYY-MM-DD
         * @return {string}
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
         * מסנכרן את הטקסט במעטפת הוויזואלית של שדות date/time.
         * השדה הנייטיבי נשאר פעיל לפתיחת picker ולשמירת ערך.
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
         * הצגה/הסתרה של כפתור ניקוי בשדות שעה בלבד.
         *
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
         * ממיר YYYY-MM-DD לפורמט תצוגה dd/mm/yyyy.
         *
         * @param {string} rawDate
         * @return {string}
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

        /**
         * עדכון מצב placeholder לכל שדות הקלט במודל.
         */
        updateFieldPlaceholderState() {
            this.$modal.find('.bcm-input').each((_, el) => {
                this.syncNativeFieldDisplay($(el));
            });
        }

        /**
         * שולח בקשת free-time חדשה לטווח התאריכים שנבחר (תחילת יום → סוף יום)
         * ואז מפעיל סינון מקומי של שעות/ימי שבוע על הנתונים החדשים.
         *
         * @return {Promise<void>}
         */
        async refetchForCurrentDateRange() {
            const fromDate = this.filterState.fromDate;
            const toDate   = this.filterState.toDate;

            // אם אין טווח תאריכים תקין – נסתפק בסינון מקומי בלבד.
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
         * אוכף טווח תאריכים מקסימלי של {@link MAX_RANGE_DAYS} ימים.
         * אם הטווח חורג, מתקן אוטומטית את הקצה הנגדי לזה ששונה
         * ומציג חיווי למשתמש.
         *
         * @param {'fromDate'|'toDate'} changedField השדה שהמשתמש שינה.
         * @param {Object} [options]
         * @param {boolean} [options.silent] אם true – לא מציג notice (עבור גארד פנימי).
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

            // clamp למינימום היום (fromDate לא יכול להיות בעבר).
            if (fromDate < todayIso) fromDate = todayIso;

            // אם הטווח הפוך (toDate קטן מ-fromDate) – ניישר לפי השדה ששונה.
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

            // עדכון state + DOM (רק אם יש שינוי בפועל).
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

            // עדכון גבולות דינמיים על ה-pickers עצמם כדי למנוע בחירה חורגת.
            this.updateDateInputsBounds();

            if (adjusted && !silent) {
                const fieldLabel = adjustedField === 'toDate' ? 'תאריך הסיום' : 'תאריך ההתחלה';
                this.showRangeLimitNotice(
                    `טווח התאריכים המרבי הוא 3 שבועות – ${fieldLabel} עודכן אוטומטית.`
                );
            }
        }

        /**
         * מעדכן את מאפייני min/max על שדות התאריך כך שה-picker
         * הנטיבי לא יאפשר לבחור ערכים שחורגים מהטווח המותר.
         */
        updateDateInputsBounds() {
            const todayIso = this.getTodayIsoDate();
            const $m = this.$modal;
            const $fromInput = $m.find('[data-filter="fromDate"]');
            const $toInput   = $m.find('[data-filter="toDate"]');
            const fromDate = this.filterState.fromDate || '';
            const toDate   = this.filterState.toDate   || '';

            // fromDate: מינימום = היום; מקסימום = toDate (אם קיים).
            $fromInput.attr('min', todayIso);
            if (toDate) {
                $fromInput.attr('max', toDate);
            } else {
                $fromInput.removeAttr('max');
            }

            // toDate: מינימום = max(היום, fromDate). מקסימום = fromDate + MAX_RANGE_DAYS.
            const toMin = fromDate && fromDate > todayIso ? fromDate : todayIso;
            $toInput.attr('min', toMin);
            if (fromDate) {
                $toInput.attr('max', this.addDaysToIso(fromDate, MAX_RANGE_DAYS));
            } else {
                $toInput.removeAttr('max');
            }
        }

        /**
         * מחשב את הפרש הימים בין שני תאריכים בפורמט YYYY-MM-DD (שעון מקומי).
         *
         * @param {string} fromIso
         * @param {string} toIso
         * @return {number} מספר ימים (עגול כלפי מטה). 0 אם לא תקין.
         */
        diffInDays(fromIso, toIso) {
            const from = this.buildStartOfDay(fromIso);
            const to   = this.buildStartOfDay(toIso);
            if (!from || !to) return 0;
            return Math.round((to.getTime() - from.getTime()) / MS_PER_DAY);
        }

        /**
         * מחזיר YYYY-MM-DD אחרי הוספת `delta` ימים (delta יכול להיות שלילי).
         *
         * @param {string} isoDate
         * @param {number} delta
         * @return {string}
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
         * מציג הודעת חיווי קצרה מתחת לשדות התאריך.
         * ההודעה נוצרת on-demand ומוסתרת אוטומטית אחרי 4 שניות.
         *
         * @param {string} message
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
         * מחזיר מפתח יציב לטווח התאריכים הנוכחי.
         *
         * @returns {string}
         */
        getCurrentDateRangeKey() {
            return `${this.filterState.fromDate || ''}|${this.filterState.toDate || ''}`;
        }

        /**
         * בודק האם מותר לעדכן שוב עבור אותו טווח תאריכים.
         * אם טרם עברו 2 דקות מאז הבקשה האחרונה לאותו טווח – חסום.
         *
         * @returns {boolean}
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

        /**
         * מסמן בקשת תאריכים שהושלמה בהצלחה לצורך cooldown.
         */
        markDateRangeRequestCompleted() {
            this.lastRequestedDateRangeKey = this.getCurrentDateRangeKey();
            this.lastRequestedDateRangeAt = Date.now();
            this.syncUpdateButtonState();
        }

        /**
         * מחשב זמן שנותר (ב-ms) עד סיום ה-cooldown לאותו טווח.
         *
         * @returns {number}
         */
        getDateRangeCooldownRemainingMs() {
            if (!this.isDateRangeUpdateBlocked()) return 0;
            const elapsed = Date.now() - this.lastRequestedDateRangeAt;
            return Math.max(0, UPDATE_COOLDOWN_MS - elapsed);
        }

        /**
         * מסנכרן מצב כפתור "עדכון תוצאות" לפי טעינה/cooldown.
         */
        syncUpdateButtonState() {
            const $btn = this.$modal ? this.$modal.find('.bcm-update-btn') : $();
            if (!$btn.length) return;

            if (this._updateCooldownTimer) {
                clearTimeout(this._updateCooldownTimer);
                this._updateCooldownTimer = null;
            }

            const blockedByCooldown = this.isDateRangeUpdateBlocked();
            const isDisabled = this._isUpdateLoading || blockedByCooldown;
            $btn.prop('disabled', isDisabled)
                .toggleClass('bcm-update-btn--cooldown', blockedByCooldown)
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
         * בונה אובייקט Date בשעת תחילת יום (00:00:00.000) מתאריך YYYY-MM-DD.
         *
         * @param {string} isoDate
         * @return {Date|null}
         */
        buildStartOfDay(isoDate) {
            const parts = String(isoDate || '').split('-').map(Number);
            if (parts.length !== 3 || parts.some(isNaN)) return null;
            const [y, m, d] = parts;
            return new Date(y, m - 1, d, 0, 0, 0, 0);
        }

        /**
         * בונה אובייקט Date בשעת סוף יום (23:59:59.999) מתאריך YYYY-MM-DD.
         *
         * @param {string} isoDate
         * @return {Date|null}
         */
        buildEndOfDay(isoDate) {
            const parts = String(isoDate || '').split('-').map(Number);
            if (parts.length !== 3 || parts.some(isNaN)) return null;
            const [y, m, d] = parts;
            return new Date(y, m - 1, d, 23, 59, 59, 999);
        }

        /**
         * מצב טעינה של כפתור "עדכון תוצאות" + רשימת התוצאות.
         *
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

            // Reuse the existing booking-calendar loader pattern while refetching
            // free-time from the server after clicking "עדכון תוצאות".
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

        /**
         * מפעיל רינדור מחודש לאחר שינוי פילטרים.
         * כרגע מיושם לסינון מיידי של שעות וימי שבוע.
         */
        applyFiltersAndRender() {
            this.visibleDaysCount = DAYS_PER_PAGE;
            this.selectedDate = null;
            this.selectedTime = null;
            this.animateResultsTransition();
        }

        /**
         * אנימציית מעבר קצרה בעת סינון תוצאות.
         * שלב 1: fade-out קל, שלב 2: render, שלב 3: fade-in.
         */
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
