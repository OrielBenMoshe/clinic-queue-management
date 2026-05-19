/**
 * Settings page interactions (frontend/admin)
 *
 * @package ClinicQueue
 */

(function ($) {
    'use strict';

    const strings = (window.clinicQueueSettings && window.clinicQueueSettings.strings) || {};
    const SETTINGS_INPUT_SELECTOR = '.clinic-settings-wrap .clinic-setting-field__input';

    const DELETE_CONFIRM_MESSAGES = {
        api_token: strings.confirmDeleteToken,
        api_endpoint: strings.confirmDeleteEndpoint,
        google_client_id: strings.confirmDeleteGoogleId,
        google_client_secret: strings.confirmDeleteGoogleSecret,
    };

    /**
     * RTL while empty (placeholder visible); LTR when the field has content.
     *
     * @param {jQuery} $input
     */
    function syncSettingsInputDirection($input) {
        const hasValue = String($input.val() || '').trim() !== '';
        $input.toggleClass('clinic-settings-input--ltr', hasValue);
    }

    function bindSettingsInputDirection() {
        const $inputs = $(SETTINGS_INPUT_SELECTOR);

        $inputs.each(function () {
            syncSettingsInputDirection($(this));
        });

        $inputs.on('input', function () {
            syncSettingsInputDirection($(this));
        });
    }

    function toggleHidden($el, show) {
        if (show) {
            $el.removeClass('clinic-field-hidden');
        } else {
            $el.addClass('clinic-field-hidden');
        }
    }

    /**
     * @param {jQuery} $field
     */
    function bindSettingField($field) {
        const fieldKey = $field.data('field');
        const isSensitive = String($field.data('sensitive')) === '1';
        let hasStored = String($field.data('has-stored')) === '1';

        const $view = $field.find('.clinic-setting-field__view');
        const $edit = $field.find('.clinic-setting-field__edit');
        const $input = $field.find('.clinic-setting-field__input');
        const $display = $field.find('.clinic-setting-field__display');
        const $editBtn = $field.find('.clinic-setting-field__edit-btn');
        const $deleteBtn = $field.find('.clinic-setting-field__delete-btn');
        const $cancelBtn = $field.find('.clinic-setting-field__cancel-btn');
        const $saveBtn = $field.find('.clinic-setting-field__save-btn');
        const $deleteForm = $field.find('.clinic-setting-field__delete-form');

        const originalDisplayValue = $display.length ? String($display.val() || '') : '';

        function syncSaveVisibility() {
            const hasContent = String($input.val() || '').trim() !== '';
            toggleHidden($saveBtn, hasContent);
        }

        function setInputEnabled(enabled) {
            $input.prop('readonly', !enabled).prop('disabled', !enabled);
        }

        function enterEditMode() {
            toggleHidden($edit, true);
            toggleHidden($editBtn, false);
            toggleHidden($cancelBtn, true);
            toggleHidden($deleteBtn, false);
            toggleHidden($saveBtn, false);

            if (hasStored) {
                toggleHidden($view, false);
            }

            if (isSensitive) {
                $input.val('');
            } else if ($display.length) {
                $input.val(String($display.val() || ''));
            } else {
                $input.val(originalDisplayValue);
            }

            setInputEnabled(true);
            syncSaveVisibility();
            syncSettingsInputDirection($input);
            $input.trigger('focus');
        }

        function exitEditMode() {
            toggleHidden($edit, false);
            toggleHidden($cancelBtn, false);
            toggleHidden($saveBtn, false);
            toggleHidden($editBtn, true);

            $input.val('');
            setInputEnabled(false);
            syncSettingsInputDirection($input);

            if (hasStored) {
                toggleHidden($view, true);
                toggleHidden($deleteBtn, true);
            } else {
                toggleHidden($view, false);
                toggleHidden($deleteBtn, false);
            }
        }

        $editBtn.on('click', function () {
            enterEditMode();
        });

        $cancelBtn.on('click', function () {
            exitEditMode();
        });

        $input.on('input', syncSaveVisibility);

        $deleteBtn.on('click', function () {
            const message = DELETE_CONFIRM_MESSAGES[fieldKey]
                || 'האם אתה בטוח שברצונך למחוק את הערך השמור?';

            if (!window.confirm(message)) {
                return;
            }

            $deleteForm.trigger('submit');
        });

        if (hasStored) {
            toggleHidden($edit, false);
            toggleHidden($view, true);
            toggleHidden($deleteBtn, true);
        } else {
            toggleHidden($view, false);
            toggleHidden($edit, true);
            toggleHidden($deleteBtn, false);
        }

        setInputEnabled(false);
    }

    $(function () {
        bindSettingsInputDirection();

        $('.clinic-setting-field').each(function () {
            bindSettingField($(this));
        });
    });
}(jQuery));
