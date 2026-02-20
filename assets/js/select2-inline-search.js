/**
 * Select2 Filter Search - סינון בתוך תפריט בחירה
 *
 * Convention: הוסף class `cq-searchable` לכל <select> שרוצים בו שדה סינון.
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

    /**
     * מחזיר את אפשרויות ה-Select2 הנוספות עבור שדה עם סינון.
     * אם ה-select לא נושא class cq-searchable, מחזיר אובייקט ריק.
     *
     * @param  {jQuery} jqSelect אלמנט ה-select
     * @return {Object}          אפשרויות Select2 להשלמה על האובייקט הבסיסי
     */
    window.ClinicQueueSelect2.getInlineSearchOptions = function (jqSelect) {
        if (!jqSelect.hasClass('cq-searchable')) {
            return {};
        }
        return {
            minimumResultsForSearch: 0,
            dropdownCssClass: 'clinic-queue-filterable',
        };
    };

    /**
     * ממקד את תיבת הסינון ברגע שהדרופדאון נפתח.
     * אם ה-select לא נושא class cq-searchable, הפונקציה לא עושה כלום.
     *
     * @param {jQuery} jqSelect    אלמנט ה-select
     * @param {jQuery} jqRoot      dropdownParent
     */
    window.ClinicQueueSelect2.setupInlineSearch = function (jqSelect, jqRoot) {
        if (!jqSelect.hasClass('cq-searchable')) {
            return;
        }

        jqSelect.off('select2:open.inline-search');

        jqSelect.on('select2:open.inline-search', function () {
            setTimeout(function () {
                jqRoot.find('.clinic-queue-filterable .select2-search__field')
                    .attr('placeholder', 'סינון')
                    .trigger('focus');
            }, 0);
        });
    };

})(window, jQuery);
