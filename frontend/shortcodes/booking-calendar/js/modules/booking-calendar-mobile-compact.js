/**
 * Booking Calendar – Mobile Compact View Module
 *
 * מנהל את תצוגת ה"כרטיס הקומפקטי" במובייל: שני שדות בחירה + קרוסלת קלפי ימים.
 * מחליף את כפתור ה-CTA הדביק הפשוט.
 *
 * זרימה:
 * 1. לחיצה על קלף יום → פתיחת הוויג'ט כ-fullscreen panel עם אוטו-סלקציה לאותו יום
 * 2. לחיצה על קלף "צפייה בכל התורים" → פתיחת המודל המורחב
 * 3. שינוי שדות הבחירה → סנכרון עם הטופס הראשי + רענון הקרוסלה
 *
 * @package ClinicQueue\Frontend\Shortcodes
 * @since   1.0.0
 */
(function ($) {
    'use strict';

    class BookingCalendarMobileCompact {
        /**
         * @param {BookingCalendarCore} core - מופע ה-core של הוויג'ט
         */
        constructor(core) {
            this.core    = core;
            this.element = core.element;
            this.$cta    = this.element.find('.booking-calendar-mobile-cta');

            // אם אין CTA (לא עמוד single clinic/doctor), לא עושים כלום
            if (!this.$cta.length) {
                return;
            }

            this.$carousel = this.$cta.find('.mobile-compact-carousel');
            this.$dots     = this.$cta.find('.mobile-compact-dots');

            this.bindEvents();
            this.setupCarouselScroll();
        }

        /* ──────────────────────────────────────────
           רענון כרטיס (נקרא לאחר טעינת נתונים)
           ────────────────────────────────────────── */

        /**
         * מרענן את השדות וקרוסלת הימים לפי הנתונים העדכניים.
         * נקרא מ-UIManager.renderCalendar() לאחר שכל הנתונים נטענו.
         */
        refresh() {
            if (!this.$cta || !this.$cta.length) {
                return;
            }
            this.populateSelects();
            this.populateDayCards();
        }

        /* ──────────────────────────────────────────
           שדות בחירה
           ────────────────────────────────────────── */

        /**
         * מעתיק אפשרויות מהשדות הראשיים לשדות הקומפקטיים.
         */
        populateSelects() {
            if (!this.$cta || !this.$cta.length) {
                return;
            }
            this._syncSelect('treatment_type', '.treatment-field', 'בחר סוג טיפול');
            this._syncSelect('scheduler_id',   '.scheduler-field', 'בחר רופא / מטפל');
        }

        /**
         * @param {string} compactFor  ערך data-compact-for
         * @param {string} mainSelector selector לשדה הראשי
         * @param {string} placeholder  טקסט placeholder כשאין בחירה
         */
        _syncSelect(compactFor, mainSelector, placeholder) {
            const $main    = this.element.find(mainSelector);
            const $compact = this.$cta.find(`[data-compact-for="${compactFor}"]`);
            const $wrap    = $compact.closest('.mobile-compact-select-wrap');

            if (!$main.length || !$compact.length) {
                if ($wrap.length) {
                    $wrap.hide();
                }
                return;
            }

            const currentVal = $main.val();

            // העתקת אפשרויות
            $compact.empty();
            $main.find('option').each(function () {
                $compact.append($(this).clone());
            });
            $compact.val(currentVal);

            // עדכון טקסט התצוגה
            const selectedText = $compact.find('option:selected').text().trim();
            $wrap.find('.mobile-compact-select-text').text(selectedText || placeholder);
            $wrap.show();
        }

        /* ──────────────────────────────────────────
           קרוסלת קלפי ימים
           ────────────────────────────────────────── */

        /**
         * מייצר ומציג את קלפי הימים לפי appointmentData של ה-core.
         */
        populateDayCards() {
            if (!this.$carousel || !this.$carousel.length) {
                return;
            }
            this.$carousel.empty();

            const activeDays = this._getActiveDays(this.core.appointmentData);

            if (!activeDays.length) {
                this.$carousel.append(
                    $('<div>')
                        .addClass('mobile-compact-empty')
                        .text('אין תורים זמינים בקרוב')
                );
                this.updateDots();
                return;
            }

            activeDays.forEach(day => this.$carousel.append(this._createDayCard(day)));
            this.$carousel.append(this._createViewAllCard());

            this.updateDots();
        }

        /**
         * מחלץ רשימת ימים עם תורים ממערך appointmentData.
         * @param {Array} appointmentData
         * @returns {Array<{date: string, slots: number}>}
         */
        _getActiveDays(appointmentData) {
            if (!appointmentData || !appointmentData.length) {
                return [];
            }

            const days = [];

            appointmentData.forEach(appointment => {
                const hasSlots = appointment.time_slots && appointment.time_slots.length > 0;
                if (!hasSlots) {
                    return;
                }

                let dateStr = '';
                if (appointment.date) {
                    if (appointment.date.appointment_date) {
                        dateStr = appointment.date.appointment_date;
                    } else if (typeof appointment.date === 'string') {
                        dateStr = appointment.date;
                    }
                }

                if (dateStr) {
                    days.push({ date: dateStr, slots: appointment.time_slots.length });
                }
            });

            days.sort((a, b) => a.date.localeCompare(b.date));

            // מגביל ל-7 ימים (השאר נגישים דרך המודל המורחב)
            return days.slice(0, 7);
        }

        /**
         * יוצר קלף יום רגיל.
         * @param {{date: string, slots: number}} day
         * @returns {jQuery}
         */
        _createDayCard(day) {
            // new Date עם שעה 12:00 מונע בעיות עם timezone
            const date        = new Date(day.date + 'T12:00:00');
            const dayNames    = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳', 'ש׳'];
            const dayAbbrev   = `יום ${dayNames[date.getDay()]}`;
            const dateFormatted = date.toLocaleDateString('he-IL', {
                day:   '2-digit',
                month: '2-digit',
                year:  'numeric',
            });
            const slotsText = `${day.slots} תורים`;

            return $('<button>')
                .addClass('mobile-compact-day-card')
                .attr('type', 'button')
                .attr('data-date', day.date)
                .append($('<span>').addClass('mobile-compact-day-name').text(dayAbbrev))
                .append($('<span>').addClass('mobile-compact-day-date').text(dateFormatted))
                .append($('<span>').addClass('mobile-compact-day-slots').text(slotsText));
        }

        /**
         * יוצר את קלף "צפייה בכל התורים" (הקלף האחרון בקרוסלה).
         * @returns {jQuery}
         */
        _createViewAllCard() {
            const arrowSvg = `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M16.6666 10H3.33325M3.33325 10L8.33325 5M3.33325 10L8.33325 15" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;

            return $('<button>')
                .addClass('mobile-compact-day-card mobile-compact-day-card--view-all')
                .attr('type', 'button')
                .attr('aria-label', 'צפייה בכל התורים הזמינים')
                .append(
                    $('<span>').addClass('mobile-compact-view-all-inner')
                        .append($('<span>').addClass('mobile-compact-view-all-text').text('צפייה בעוד תורים זמינים'))
                        .append($('<span>').addClass('mobile-compact-view-all-icon').html(arrowSvg))
                );
        }

        /* ──────────────────────────────────────────
           אינדיקטור נקודות
           ────────────────────────────────────────── */

        /**
         * מעדכן את נקודות הסליידר לפי מיקום גלילה נוכחי.
         */
        updateDots() {
            if (!this.$dots.length || !this.$carousel.length) {
                return;
            }

            const carousel   = this.$carousel[0];
            // ב-RTL scrollLeft יכול להיות שלילי בחלק מהדפדפנים
            const scrollAbs  = Math.abs(carousel.scrollLeft);
            const maxScroll  = carousel.scrollWidth - carousel.clientWidth;
            const scrollPct  = maxScroll > 0 ? scrollAbs / maxScroll : 0;

            const totalDots = 3;
            const activeDot = Math.round(scrollPct * (totalDots - 1));

            this.$dots.empty();
            for (let i = 0; i < totalDots; i++) {
                this.$dots.append(
                    $('<span>')
                        .addClass('mobile-compact-dot')
                        .toggleClass('mobile-compact-dot--active', i === activeDot)
                );
            }
        }

        /* ──────────────────────────────────────────
           אירועים
           ────────────────────────────────────────── */

        bindEvents() {
            const ns = `.bc-mobile-compact-${this.core.widgetId}`;

            // לחיצה על קלף יום → פתיחת הפנל לאותו יום
            this.$cta.on(`click${ns}`, '.mobile-compact-day-card:not(.mobile-compact-day-card--view-all)', (e) => {
                const date = $(e.currentTarget).data('date');
                if (date) {
                    this.core.openMobilePanel(date);
                }
            });

            // לחיצה על "כל התורים" → מודל מורחב
            this.$cta.on(`click${ns}`, '.mobile-compact-day-card--view-all', () => {
                this.core.openExpandedModal();
            });

            // שינוי שדה בחירה קומפקטי → סנכרון עם הטופס הראשי
            this.$cta.on(`change${ns}`, '.mobile-compact-select', (e) => {
                const $select      = $(e.target);
                const targetField  = $select.data('compact-for');
                const value        = $select.val();
                const selectedText = $select.find('option:selected').text().trim();

                // עדכון טקסט התצוגה
                $select.closest('.mobile-compact-select-wrap')
                    .find('.mobile-compact-select-text')
                    .text(selectedText);

                // עדכון שדה הטופס הראשי והפעלת שרשרת הלוגיקה
                const $mainSelect = this.element.find(`[name="${targetField}"]`);
                if ($mainSelect.length) {
                    $mainSelect.val(value);
                    if ($mainSelect.hasClass('select2-hidden-accessible')) {
                        $mainSelect.trigger('change.select2');
                    }
                    $mainSelect.trigger('change');
                }
            });

            // עדכון שדה קומפקטי כשהשדה הראשי משתנה (סנכרון הפוך)
            this.element.on(`change${ns}`, '.form-field-select', (e) => {
                const $select  = $(e.target);
                const name     = $select.attr('name');
                const value    = $select.val();
                const text     = $select.find('option:selected').text().trim();

                const $compact = this.$cta.find(`[data-compact-for="${name}"]`);
                if ($compact.length && $compact.val() !== value) {
                    $compact.val(value);
                    $compact.closest('.mobile-compact-select-wrap')
                        .find('.mobile-compact-select-text')
                        .text(text);
                }
            });
        }

        setupCarouselScroll() {
            if (!this.$carousel.length) {
                return;
            }
            this.$carousel.on('scroll', () => this.updateDots());
        }

        /* ──────────────────────────────────────────
           ניקוי
           ────────────────────────────────────────── */

        destroy() {
            const ns = `.bc-mobile-compact-${this.core.widgetId}`;
            this.$cta.off(ns);
            this.element.off(ns);
            this.$carousel.off('scroll');
        }
    }

    window.BookingCalendarMobileCompact = BookingCalendarMobileCompact;

})(jQuery);
