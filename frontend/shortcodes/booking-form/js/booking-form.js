/**
 * Booking Form JavaScript
 * Handles form submission and family list refresh
 */

(function($) {
    'use strict';

    /**
     * Booking Form Manager
     */
    class BookingFormManager {
        constructor() {
            this.form = null;
            this.messageBox = null;
            this.submitBtn = null;
            this.loader = null;
            this.popupId = null;
            
            this.init();
        }

        init() {
            // Wait for DOM
            $(document).ready(() => {
                this.form = $('#ajax-booking-form');
                this.messageBox = $('#booking-message');
                this.submitBtn = $('#submit-btn');
                
                // Get popup ID from trigger element
                const trigger = $('.add-patient-trigger');
                if (trigger.length) {
                    this.popupId = trigger.data('popup-id');
                }
                
                // Fill form from URL parameters
                this.fillFormFromQueryParams();
                
                this.bindEvents();
            });
        }
        
        /**
         * Fill form fields from URL query parameters
         */
        fillFormFromQueryParams() {
            if (!this.form.length) {
                return;
            }
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Fill date field
            const dateParam = urlParams.get('date');
            if (dateParam) {
                const dateField = this.form.find('#appt_date');
                if (dateField.length && !dateField.val()) {
                    dateField.val(dateParam);
                }
            }
            
            // Fill time field
            const timeParam = urlParams.get('time');
            if (timeParam) {
                const timeField = this.form.find('#appt_time');
                if (timeField.length && !timeField.val()) {
                    timeField.val(timeParam);
                }
            }
        }

        bindEvents() {
            // Form submission
            if (this.form.length) {
                this.form.on('submit', (e) => {
                    this.handleFormSubmit(e);
                });
            }

            // Add patient trigger click
            $('.add-patient-trigger').on('click', (e) => {
                this.openAddPatientPopup();
            });

            // Listen for JetFormBuilder success
            $(document).on('jet-form-builder/ajax/on-success', (event, response) => {
                this.refreshFamilyListAndClosePopup();
            });

            // Listen for JetEngine success (fallback)
            $(document).on('jet-engine/form/on-ajax-success', (event, response) => {
                this.refreshFamilyListAndClosePopup();
            });
        }

        handleFormSubmit(e) {
            e.preventDefault();
            
            if (!this.form.length || !this.submitBtn.length) {
                return;
            }

            // Disable submit button
            this.submitBtn.prop('disabled', true);
            this.submitBtn.html('שולח... <span class="loader" style="display:inline;">⌛</span>');
            this.messageBox.hide();

            // Prepare form data
            const formData = new FormData(this.form[0]);

            // AJAX request
            fetch(bookingFormData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.messageBox.show();
                
                if (data.success) {
                    this.messageBox
                        .removeClass('msg-error')
                        .addClass('msg-success')
                        .text(data.data.message);
                    
                    // Reset form
                    this.form[0].reset();
                    
                    // Hide message after 5 seconds
                    setTimeout(() => {
                        this.messageBox.hide();
                    }, 5000);
                } else {
                    this.messageBox
                        .removeClass('msg-success')
                        .addClass('msg-error')
                        .text(data.data?.message || 'שגיאה');
                }
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                this.messageBox
                    .removeClass('msg-success')
                    .addClass('msg-error')
                    .text('שגיאה.');
                this.messageBox.show();
            })
            .finally(() => {
                // Re-enable submit button
                this.submitBtn.prop('disabled', false);
                this.submitBtn.html('קבע את התור <span class="loader" style="display:none;">⌛</span>');
            });
        }

        openAddPatientPopup() {
            if (this.popupId) {
                $(window).trigger('jet-popup-open-trigger', {
                    popupId: this.popupId
                });
            }
        }

        refreshFamilyListAndClosePopup() {
            // Close popup
            if (this.popupId) {
                $(window).trigger('jet-popup-close-trigger', {
                    popupId: this.popupId
                });
            }

            // Refresh family list via AJAX
            $.ajax({
                url: bookingFormData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'refresh_family_list_html'
                },
                success: (response) => {
                    if (response.success) {
                        // Replace old HTML with new
                        $('#patients-list-container').html(response.data.html);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error refreshing family list:', error);
                }
            });
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('#ajax-booking-form').length) {
            new BookingFormManager();
        }
    });

})(jQuery);
