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
    const DAYS_PER_PAGE  = 5;

    class BookingCalendarExpandedModal {

        /**
         * @param {BookingCalendarCore} core – instance של הלוח שפתח את המודל
         */
        constructor(core) {
            this.core   = core;
            this.$modal = null;

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
            const today  = new Date();
            const toDate = new Date(today);
            toDate.setDate(today.getDate() + 21);

            const fromStr = today.toISOString().split('T')[0];
            const toStr   = toDate.toISOString().split('T')[0];

            this.filterState.fromDate = fromStr;
            this.filterState.toDate   = toStr;

            this.$modal.find('[data-filter="fromDate"]').val(fromStr);
            this.$modal.find('[data-filter="toDate"]').val(toStr);
            this.$modal.find('[data-filter="fromTime"]').val('');
            this.$modal.find('[data-filter="toTime"]').val('');
            this.$modal.find('.bcm-day-cb').prop('checked', true);
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

            // עדכון תוצאות
            $m.on('click.bcm-modal', '.bcm-update-btn', () => {
                this.readFilters();
                this.visibleDaysCount = DAYS_PER_PAGE;
                this.selectedDate     = null;
                this.selectedTime     = null;
                this.renderResults();
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
        }

        /**
         * קורא את ערכי הפילטרים מה-DOM ומעדכן את filterState
         */
        readFilters() {
            const $m = this.$modal;
            this.filterState.treatmentType = $m.find('[data-filter="treatmentType"]').val() || '';
            this.filterState.schedulerId   = $m.find('[data-filter="schedulerId"]').val()   || '';
            this.filterState.fromDate      = $m.find('[data-filter="fromDate"]').val()       || '';
            this.filterState.toDate        = $m.find('[data-filter="toDate"]').val()         || '';
            this.filterState.fromTime      = $m.find('[data-filter="fromTime"]').val()       || '';
            this.filterState.toTime        = $m.find('[data-filter="toTime"]').val()         || '';
            this.filterState.days          = [];
            $m.find('.bcm-day-cb:checked').each((_, el) => this.filterState.days.push($(el).val()));

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
            const $loadMoreWrap = this.$modal.find('.bcm-load-more-wrap');
            const allDays       = this.getFilteredData();
            const visibleDays   = allDays.slice(0, this.visibleDaysCount);

            window.BookingCalendarUtils.log('Expanded modal rendering:', {
                total   : allDays.length,
                visible : visibleDays.length,
            });

            if (!allDays.length) {
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
                $loadMoreWrap.hide();
                this.updateBookButton();
                return;
            }

            $list.html(visibleDays.map(d => {
                const dateStr = d.date?.appointment_date || d.date;
                return this.renderDayBlock(dateStr, d.time_slots || []);
            }).join(''));

            if (preserveSelection && this.selectedDate && this.selectedTime) {
                this.$modal
                    .find(`.bcm-day-block[data-date="${this.selectedDate}"] .bcm-slot[data-time="${this.selectedTime}"]`)
                    .addClass('bcm-slot--selected selected');
            }

            allDays.length > this.visibleDaysCount ? $loadMoreWrap.show() : $loadMoreWrap.hide();
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
    }

    window.BookingCalendarExpandedModal = BookingCalendarExpandedModal;

})(jQuery);
