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

            // אם אין בלוק CTA ב-DOM (דף ללא mobile compact), לא עושים כלום
            if (!this.$cta.length) {
                return;
            }

            this.$carousel   = this.$cta.find('.mobile-compact-carousel');
            this.$carouselContainer = this.$cta.find('.mobile-compact-carousel-container');
            this.$dots       = this.$cta.find('.mobile-compact-dots');

            /** @type {number|null} */
            this._transitionFallbackTimer = null;

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

            if (this.core.selectionMode === 'doctor') {
                this._syncSelect('clinic_id', 'select[data-field="clinic_id"]', 'בחר מרפאה');
            } else {
                this._syncSelect('scheduler_id', '.scheduler-field', 'בחר רופא / מטפל');
            }
        }

        /**
         * מחזיר selector לשדה הראשי (לא לפי name – יש hidden עם אותו name במצב רופא).
         * @param {string} compactFor
         * @returns {string}
         */
        _getMainFieldSelector(compactFor) {
            const selectors = {
                treatment_type: '.treatment-field',
                scheduler_id:   '.scheduler-field',
                clinic_id:      'select[data-field="clinic_id"]',
            };
            return selectors[compactFor] || `[name="${compactFor}"]`;
        }

        /**
         * טקסט האפשרות הנבחרת מהשדה הראשי (אמין גם עם Select2).
         * @param {jQuery} $main
         * @param {string} value
         * @returns {string}
         */
        _getSelectedOptionText($main, value) {
            const normalized = value === null || value === undefined ? '' : String(value);
            if (!normalized) {
                return '';
            }

            let label = '';
            $main.find('option').each(function () {
                if (String($(this).val()) === normalized) {
                    label = $(this).text().trim();
                    return false;
                }
            });
            return label;
        }

        /**
         * מעדכן את טקסט התצוגה של שדה קומפקטי לפי השדה הראשי.
         * @param {string} compactFor
         * @param {string} [placeholder]
         */
        updateDisplayFromMain(compactFor, placeholder) {
            const mainSelector = this._getMainFieldSelector(compactFor);
            const $main = this.element.find(mainSelector);
            const $compact = this.$cta.find(`[data-compact-for="${compactFor}"]`);
            const $wrap = $compact.closest('.mobile-compact-select-wrap');

            if (!$main.length || !$compact.length || !$wrap.length) {
                return;
            }

            const value = $main.val();
            const label = this._getSelectedOptionText($main, value);
            const fallbackPlaceholder = placeholder || $compact.find('option[value=""]').first().text().trim();

            if (value) {
                $compact.val(String(value));
            }

            $wrap.find('.mobile-compact-select-text').text(label || fallbackPlaceholder);
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
            const normalizedVal = currentVal === null || currentVal === undefined ? '' : String(currentVal);
            const mainEl = $main[0];

            // העתקת אפשרויות מה-select המקורי (גם כש-Select2 פעיל)
            $compact.empty();
            if (mainEl && mainEl.options && mainEl.options.length) {
                Array.from(mainEl.options).forEach((opt) => {
                    const isSelected = normalizedVal && String(opt.value) === normalizedVal;
                    $compact.append(
                        $('<option>', {
                            value: opt.value,
                            text: opt.text,
                            selected: isSelected,
                        })
                    );
                });
            } else {
                $main.find('option').each(function () {
                    $compact.append($(this).clone());
                });
            }

            if (normalizedVal) {
                $compact.val(normalizedVal);
            } else {
                $compact.val('');
            }

            const selectedText = this._getSelectedOptionText($main, normalizedVal)
                || $compact.find('option:selected').text().trim();
            $wrap.find('.mobile-compact-select-text').text(
                normalizedVal && selectedText ? selectedText : placeholder
            );
            $wrap.show();
        }

        /* ──────────────────────────────────────────
           קרוסלת קלפי ימים
           ────────────────────────────────────────── */

        /**
         * מייצר ומציג את קלפי הימים לפי appointmentData של ה-core.
         * מפעיל אנימציית מעבר כשיש תוכן קודם (שינוי שדה או רענון נתונים).
         */
        populateDayCards() {
            if (!this.$carousel || !this.$carousel.length) {
                return;
            }

            const buildContent = () => {
                const activeDays = this._getActiveDays(this.core.appointmentData);

                if (!activeDays.length) {
                    this.$carousel.append(
                        $('<div>')
                            .addClass('mobile-compact-empty')
                            .text('אין תורים זמינים בקרוב')
                    );
                    return;
                }

                activeDays.forEach((day) => this.$carousel.append(this._createDayCard(day)));
                this.$carousel.append(this._createViewAllCard());
            };

            if (this._prefersReducedMotion()) {
                this._renderCarouselContentDirect(buildContent);
                return;
            }

            if (this._hasDisplayableCarouselContent() || this.$carousel.hasClass('mobile-compact-carousel--exit')) {
                this._animateCarouselReplace(buildContent);
                return;
            }

            this._renderCarouselContentDirect(buildContent);
        }

        /**
         * לפני טעינת נתונים חדשים – יציאה הדרגתית + אינדיקטור טעינה אופציונלי.
         */
        _prepareCarouselForReload() {
            if (!this.$carousel || !this.$carousel.length || this._prefersReducedMotion()) {
                return;
            }
            if (!this._hasDisplayableCarouselContent()) {
                return;
            }
            if (this.$carousel.hasClass('mobile-compact-carousel--exit')) {
                return;
            }

            this._setCarouselLoading(true);
            this.$carousel.addClass('mobile-compact-carousel--transitioning mobile-compact-carousel--exit');
        }

        /**
         * @returns {boolean}
         */
        _prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        /**
         * @returns {boolean}
         */
        _hasDisplayableCarouselContent() {
            if (!this.$carousel || !this.$carousel.length) {
                return false;
            }
            return this.$carousel.find('.mobile-compact-day-card, .mobile-compact-empty').length > 0;
        }

        /**
         * @param {Function} buildContent
         */
        _renderCarouselContentDirect(buildContent) {
            this._clearTransitionTimers();
            this._clearTransitionClasses();
            this._setCarouselLoading(false);
            this.$carousel.empty();
            buildContent();
            this._resetCarouselScroll();
            this.updateDots();
        }

        /**
         * @param {Function} buildContent
         */
        _animateCarouselReplace(buildContent) {
            const carouselEl = this.$carousel[0];
            const runSwap = () => {
                this._clearTransitionTimers();
                this.$carousel.off('transitionend.mobileCompactCarousel');
                this.$carousel.empty();
                buildContent();
                this.$carousel
                    .removeClass('mobile-compact-carousel--exit')
                    .addClass('mobile-compact-carousel--enter');
                this._resetCarouselScroll();

                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(() => {
                        this.$carousel.addClass('mobile-compact-carousel--enter-active');
                    });
                });

                const onEnterEnd = (event) => {
                    if (event.target !== carouselEl) {
                        return;
                    }
                    this._finishCarouselTransition();
                };

                this.$carousel.on('transitionend.mobileCompactCarousel', onEnterEnd);
                this._transitionFallbackTimer = window.setTimeout(() => {
                    this._finishCarouselTransition();
                }, 450);
            };

            if (this.$carousel.hasClass('mobile-compact-carousel--exit')) {
                const opacity = window.getComputedStyle(carouselEl).opacity;
                if (opacity === '0' || parseFloat(opacity) < 0.05) {
                    runSwap();
                    return;
                }

                this.$carousel.one('transitionend.mobileCompactCarousel', (event) => {
                    if (event.target !== carouselEl) {
                        return;
                    }
                    runSwap();
                });
                this._transitionFallbackTimer = window.setTimeout(runSwap, 280);
                return;
            }

            this._setCarouselLoading(true);
            this.$carousel.addClass('mobile-compact-carousel--transitioning mobile-compact-carousel--exit');

            this.$carousel.one('transitionend.mobileCompactCarousel', (event) => {
                if (event.target !== carouselEl) {
                    return;
                }
                runSwap();
            });
            this._transitionFallbackTimer = window.setTimeout(runSwap, 280);
        }

        _finishCarouselTransition() {
            this._clearTransitionTimers();
            this.$carousel.off('transitionend.mobileCompactCarousel');
            this._clearTransitionClasses();
            this._setCarouselLoading(false);
            this.updateDots();
        }

        _clearTransitionClasses() {
            if (!this.$carousel || !this.$carousel.length) {
                return;
            }
            this.$carousel.removeClass(
                'mobile-compact-carousel--transitioning mobile-compact-carousel--exit mobile-compact-carousel--enter mobile-compact-carousel--enter-active'
            );
        }

        _clearTransitionTimers() {
            if (this._transitionFallbackTimer !== null) {
                window.clearTimeout(this._transitionFallbackTimer);
                this._transitionFallbackTimer = null;
            }
        }

        /**
         * @param {boolean} isLoading
         */
        _setCarouselLoading(isLoading) {
            if (!this.$carouselContainer || !this.$carouselContainer.length) {
                return;
            }
            this.$carouselContainer.toggleClass('mobile-compact-carousel-container--loading', isLoading);
        }

        _resetCarouselScroll() {
            if (!this.$carousel || !this.$carousel.length) {
                return;
            }
            const carousel = this.$carousel[0];
            if (typeof carousel.scrollTo === 'function') {
                carousel.scrollTo({ left: 0, behavior: 'auto' });
            } else {
                carousel.scrollLeft = 0;
            }
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
         * מחזיר התקדמות גלילה 0–1 בקרוסלת RTL (כמו days-carousel ב-UIManager).
         * התחלה (ימין): scrollLeft ≈ 0 → 0. סוף (שמאל): scrollLeft ≈ maxScroll שלילי → 1.
         *
         * @returns {number}
         */
        _getCarouselScrollProgress() {
            const carousel = this.$carousel[0];
            if (!carousel) {
                return 0;
            }

            const scrollableDistance = carousel.scrollWidth - carousel.clientWidth;
            if (scrollableDistance <= 1) {
                return 0;
            }

            const scrollLeft = this.$carousel.scrollLeft();
            const maxScroll  = -scrollableDistance;

            if (maxScroll >= -1) {
                return 0;
            }

            const progress = scrollLeft / maxScroll;
            return Math.min(1, Math.max(0, progress));
        }

        /**
         * אינדקס הנקודה הפעילה לפי מיקום הקרוסלה: ראשונה / אמצע / אחרונה.
         *
         * @param {number} totalDots
         * @returns {number}
         */
        _getActiveDotIndex(totalDots) {
            if (totalDots <= 1) {
                return 0;
            }

            const carousel = this.$carousel[0];
            const scrollableDistance = carousel.scrollWidth - carousel.clientWidth;

            if (scrollableDistance <= 1) {
                return 0;
            }

            const scrollLeft = this.$carousel.scrollLeft();
            const maxScroll  = -scrollableDistance;
            const tolerance  = 2;

            if (scrollLeft >= -tolerance) {
                return 0;
            }

            if (scrollLeft <= maxScroll + tolerance) {
                return totalDots - 1;
            }

            const progress = this._getCarouselScrollProgress();
            return Math.min(totalDots - 1, Math.max(0, Math.round(progress * (totalDots - 1))));
        }

        /**
         * מעדכן את נקודות הסליידר לפי מיקום גלילה נוכחי.
         */
        updateDots() {
            if (!this.$dots.length || !this.$carousel.length) {
                return;
            }

            const $dotEls   = this.$dots.find('.mobile-compact-dot');
            const totalDots = $dotEls.length || 3;
            const activeDot = this._getActiveDotIndex(totalDots);

            $dotEls.each(function (index) {
                $(this).toggleClass('mobile-compact-dot--active', index === activeDot);
            });
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
                const mainSelector = this._getMainFieldSelector(targetField);
                const $mainSelect = this.element.find(mainSelector);
                if ($mainSelect.length) {
                    $mainSelect.val(value);
                    if ($mainSelect.hasClass('select2-hidden-accessible')) {
                        $mainSelect.trigger('change.select2');
                    }
                    $mainSelect.trigger('change');
                }
            });

            // שינוי שדה ראשי שמטעין מחדש תורים → אנימציית יציאה לפני שהנתונים מגיעים
            this.element.on(
                `change${ns}`,
                '.treatment-field, .scheduler-field, select[data-field="clinic_id"]',
                () => {
                    this._prepareCarouselForReload();
                }
            );

            // עדכון שדה קומפקטי כשהשדה הראשי משתנה (סנכרון הפוך)
            const syncFromMain = (e) => {
                const $select = $(e.target);
                const name = $select.data('field') || $select.attr('name');
                if (!name) {
                    return;
                }

                const $compact = this.$cta.find(`[data-compact-for="${name}"]`);
                if (!$compact.length) {
                    return;
                }

                const value = $select.val();
                const normalized = value === null || value === undefined ? '' : String(value);
                const text = this._getSelectedOptionText($select, normalized)
                    || $select.find('option:selected').text().trim();
                const $wrap = $compact.closest('.mobile-compact-select-wrap');
                const placeholder = $compact.find('option[value=""]').first().text().trim();
                const displayText = $wrap.find('.mobile-compact-select-text').text().trim();
                const needsValueSync = String($compact.val() || '') !== normalized;
                const needsTextSync = normalized && (!displayText || displayText === placeholder);

                if (needsValueSync || needsTextSync) {
                    if (needsValueSync) {
                        $compact.val(normalized);
                    }
                    $wrap.find('.mobile-compact-select-text').text(text || placeholder);
                }
            };

            this.element.on(`change${ns}`, '.form-field-select', syncFromMain);
            this.element.on(`select2:select${ns}`, '.form-field-select', syncFromMain);
        }

        setupCarouselScroll() {
            if (!this.$carousel.length) {
                return;
            }

            const ns = `.bc-mobile-compact-dots-${this.core.widgetId}`;
            this.$carousel.on(`scroll${ns}`, () => this.updateDots());

            this._resizeHandler = () => this.updateDots();
            window.addEventListener('resize', this._resizeHandler);

            this.updateDots();
        }

        /* ──────────────────────────────────────────
           ניקוי
           ────────────────────────────────────────── */

        destroy() {
            const ns = `.bc-mobile-compact-${this.core.widgetId}`;
            const dotsNs = `.bc-mobile-compact-dots-${this.core.widgetId}`;
            this._clearTransitionTimers();
            this._clearTransitionClasses();
            this._setCarouselLoading(false);
            this.$cta.off(ns);
            this.element.off(ns);
            if (this.$carousel && this.$carousel.length) {
                this.$carousel.off(`scroll${dotsNs} transitionend.mobileCompactCarousel`);
            }
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
                this._resizeHandler = null;
            }
        }
    }

    window.BookingCalendarMobileCompact = BookingCalendarMobileCompact;

})(jQuery);
