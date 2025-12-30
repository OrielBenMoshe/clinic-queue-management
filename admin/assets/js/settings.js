/**
 * Settings Page JavaScript
 * Handles UI interactions for settings page
 * 
 * @package ClinicQueue
 * @subpackage Admin\Assets
 */

(function($) {
    'use strict';

    /**
     * Settings Manager Class
     * Manages all settings page UI interactions
     */
    class SettingsManager {
        constructor() {
            this.editTokenBtn = $('#clinic_edit_token_btn');
            this.deleteTokenBtn = $('#clinic_delete_token_btn');
            this.cancelEditBtn = $('#clinic_cancel_edit_btn');
            this.tokenEditField = $('#clinic_token_edit_field');
            this.tokenInput = $('#clinic_queue_api_token');
            this.deleteFlag = $('#clinic_delete_token_flag');
            this.endpointField = $('#clinic_queue_api_endpoint');
            this.saveEndpointBtn = $('.clinic-save-endpoint-btn');
            this.form = $('.clinic-queue-settings-form');
            
            this.init();
        }

        /**
         * Initialize event listeners
         */
        init() {
            // Edit token button
            if (this.editTokenBtn.length) {
                this.editTokenBtn.on('click', (e) => {
                    e.preventDefault();
                    this.showTokenEditField();
                });
            }
            
            // Cancel edit button
            if (this.cancelEditBtn.length) {
                this.cancelEditBtn.on('click', (e) => {
                    e.preventDefault();
                    this.hideTokenEditField();
                });
            }
            
            // Delete token button
            if (this.deleteTokenBtn.length) {
                this.deleteTokenBtn.on('click', (e) => {
                    e.preventDefault();
                    this.handleDeleteToken();
                });
            }
            
            // Endpoint save button validation
            if (this.saveEndpointBtn.length) {
                this.saveEndpointBtn.on('click', (e) => {
                    if (!this.validateEndpoint()) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
            
            // Form submission debug (only in debug mode)
            if (typeof console !== 'undefined' && console.log) {
                this.form.on('submit', () => {
                    if (window.clinicQueueSettings && window.clinicQueueSettings.debug) {
                        console.log('[ClinicQueue Settings] Form submitting...');
                        console.log('[ClinicQueue Settings] Token field:', this.tokenInput.val() ? '***' : 'empty');
                        console.log('[ClinicQueue Settings] Endpoint:', this.endpointField.val());
                        console.log('[ClinicQueue Settings] Delete flag:', this.deleteFlag.val());
                    }
                });
            }
        }

        /**
         * Show token edit field
         */
        showTokenEditField() {
            this.tokenEditField.slideDown(300);
            this.editTokenBtn.prop('disabled', true);
            if (this.tokenInput.length) {
                this.tokenInput.focus();
            }
        }

        /**
         * Hide token edit field
         */
        hideTokenEditField() {
            this.tokenEditField.slideUp(300);
            this.editTokenBtn.prop('disabled', false);
            if (this.tokenInput.length) {
                this.tokenInput.val('');
            }
        }

        /**
         * Handle token deletion
         */
        handleDeleteToken() {
            const message = 'האם אתה בטוח שברצונך למחוק את הטוקן?\n\nפעולה זו תמחק את הטוקן השמור ותצטרך להזין טוקן חדש.';
            
            if (!confirm(message)) {
                return;
            }

            // Set the delete flag
            if (this.deleteFlag.length) {
                this.deleteFlag.val('1');
            }
            
            // Submit the form
            this.form.submit();
        }

        /**
         * Validate endpoint URL
         * 
         * @return {boolean} True if valid
         */
        validateEndpoint() {
            const endpoint = this.endpointField.val().trim();
            
            if (!endpoint) {
                alert('נא להזין כתובת API תקינה');
                this.endpointField.focus();
                return false;
            }
            
            // Validate URL format
            try {
                new URL(endpoint);
                return true;
            } catch (e) {
                alert('כתובת ה-API אינה תקינה. נא להזין URL מלא (לדוגמה: https://example.com)');
                this.endpointField.focus();
                return false;
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on settings page
        if ($('#clinic-queue-settings-form').length || $('.clinic-queue-settings-form').length) {
            new SettingsManager();
        }
    });

})(jQuery);
