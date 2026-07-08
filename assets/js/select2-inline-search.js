/**
 * Select2 Filter Search - סינון בתוך תפריט בחירה
 *
 * Convention: הוסף class `cq-searchable` (או `cq-em-searchable` במודל עריכה) לכל <select> שרוצים בו שדה סינון.
 * ה-Select2 יציג תיבת חיפוש בראש הדרופדאון, שמסננת את הרשימה תוך כדי הקלדה.
 *
 * שימוש:
 *   1. הוסף class `cq-searchable` לאלמנט ה-<select>
 *   2. הקוד הגלובלי מטפל בשאר אוטומטית דרך schedule-form-ui.js
 *
 * @package ClinicQueue
 */

(function (window, $) {
    'use strict';

    window.ClinicQueueSelect2 = window.ClinicQueueSelect2 || {};

    /** @type {string[]} */
    window.ClinicQueueSelect2.SEARCHABLE_CLASSES = ['cq-searchable', 'cq-em-searchable'];

    /**
     * בודק אם ל-select יש class שמפעיל סינון inline.
     *
     * @param  {jQuery} jqSelect אלמנט ה-select
     * @return {boolean}
     */
    window.ClinicQueueSelect2.isSearchable = function (jqSelect) {
        if (!jqSelect || !jqSelect.length) {
            return false;
        }
        return window.ClinicQueueSelect2.SEARCHABLE_CLASSES.some(function (cls) {
            return jqSelect.hasClass(cls);
        });
    };

    /**
     * מחזיר את אפשרויות ה-Select2 הנוספות עבור שדה עם סינון.
     * אם ה-select לא נושא class cq-searchable / cq-em-searchable, מחזיר אובייקט ריק.
     *
     * @param  {jQuery} jqSelect אלמנט ה-select
     * @return {Object}          אפשרויות Select2 להשלמה על האובייקט הבסיסי
     */
    window.ClinicQueueSelect2.getInlineSearchOptions = function (jqSelect) {
        if (!window.ClinicQueueSelect2.isSearchable(jqSelect)) {
            return {};
        }
        return {
            minimumResultsForSearch: 0,
            dropdownCssClass: 'clinic-queue-filterable',
        };
    };

    /**
     * ממקד את תיבת הסינון ברגע שהדרופדאון נפתח.
     * אם ה-select לא נושא class cq-searchable / cq-em-searchable, הפונקציה לא עושה כלום.
     *
     * @param {jQuery} jqSelect    אלמנט ה-select
     * @param {jQuery} jqRoot      dropdownParent
     */
    window.ClinicQueueSelect2.setupInlineSearch = function (jqSelect, jqRoot) {
        if (!window.ClinicQueueSelect2.isSearchable(jqSelect)) {
            return;
        }

        jqSelect.off('select2:open.inline-search');

        jqSelect.on('select2:open.inline-search', function () {
            setTimeout(function () {
                const $field = jqRoot.find('.clinic-queue-filterable .select2-search__field');
                $field.attr('placeholder', 'סינון');
                const field = $field.get(0);
                if (field && typeof field.focus === 'function') {
                    field.focus({ preventScroll: true });
                }
            }, 0);
        });
    };

})(window, jQuery);
