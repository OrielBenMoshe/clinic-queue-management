/**
 * לייבלים צפים בסגנון MUI — משותף לטפסי התוסף (יומן, הזמנה וכו').
 * דורש jQuery, מבנה HTML עם .floating-label אחרי השדה, ו-CSS ב-clinic-queue-jetform-mui.
 *
 * @package Clinic_Queue_Management
 */
(function(window, $) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    /**
     * @param {JQuery} $input שדה טקסט / מספר / textarea
     */
    function setupOne($input) {
        var $fieldRow = $input.closest('.field-type-text-field, .field-type-number-field');
        if (!$fieldRow.length) {
            return;
        }

        var updateLabelState = function() {
            var currentValue = $input.val();
            var hasValue = currentValue && String(currentValue).trim() !== '';
            var isFocused = $input.is(':focus');
            if (hasValue || isFocused) {
                $fieldRow.addClass('has-value');
            } else {
                $fieldRow.removeClass('has-value');
            }
        };

        updateLabelState();
        setTimeout(updateLabelState, 10);
        setTimeout(updateLabelState, 100);

        $input.off('input.floating-label focus.floating-label blur.floating-label');
        $input.on('input.floating-label', updateLabelState);
        $input.on('focus.floating-label', updateLabelState);
        $input.on('blur.floating-label', updateLabelState);
    }

    /**
     * אתחול כל השדות עם .floating-label מתחת לאותו root
     *
     * @param {Element|JQuery|string} root אלמנט הורה או סלקטור jQuery
     */
    function init(root) {
        var $root = root instanceof $ ? root : $(root);
        if (!$root.length) {
            return;
        }

        $root
            .find(
                '.field-type-text-field .jet-form-builder__field[type="text"], ' +
                '.field-type-text-field .jet-form-builder__field[type="number"], ' +
                '.field-type-number-field .jet-form-builder__field[type="number"], ' +
                '.field-type-text-field textarea.jet-form-builder__field'
            )
            .each(function() {
                var $input = $(this);
                var $fieldWrap = $input.closest('.jet-form-builder__field-wrap');
                if ($fieldWrap.find('.floating-label').length > 0) {
                    setupOne($input);
                }
            });
    }

    window.ClinicQueueFloatingLabels = {
        init: init,
        setupOne: setupOne
    };
})(window, window.jQuery);
