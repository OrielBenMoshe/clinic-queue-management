/**
 * Booking Form JavaScript
 * Handles form submission and family list refresh
 * משתמש ב-jQuery – $ הוא jQuery בתוך ה-IIFE
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
                
                // Get popup ID from trigger (attr אמין יותר מ-data)
                const trigger = $('.add-patient-trigger');
                if (trigger.length) {
                    this.popupId = trigger.attr('data-popup-id');
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

            // Add patient – event delegation (עובד גם אם האלמנט נטען אחרי ready)
            $(document).on('click', '.add-patient-trigger', (e) => {
                e.preventDefault();
                this.popupId = $(e.currentTarget).attr('data-popup-id');
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
                // טיפול בתשובות לפי סוג השגיאה
                if (data.data?.slot_taken) {
                    // תור תפוס
                    this.showModal({
                        type: 'error',
                        title: 'התור כבר תפוס',
                        message: data.data.message || 'מצטערים, התור שבחרת כבר נתפס על ידי מישהו אחר.',
                        button: 'בחירת תור אחר',
                        onClose: () => {
                            const referrerUrl = this.getReferrerUrl();
                            if (referrerUrl) {
                                window.location.href = referrerUrl;
                            }
                        }
                    });
                } else if (data.data?.proxy_error) {
                    // שגיאה בפרוקסי
                    this.showModal({
                        type: 'error',
                        title: 'שגיאה ביצירת התור',
                        message: data.data.message || 'אירעה שגיאה בעת קביעת התור. אנא נסה שוב.',
                        button: 'חזרה ליומן',
                        onClose: () => {
                            const referrerUrl = this.getReferrerUrl();
                            if (referrerUrl) {
                                window.location.href = referrerUrl;
                            }
                        }
                    });
                } else if (data.success) {
                    // הצלחה
                    this.showModal({
                        type: 'success',
                        title: 'התור נקבע בהצלחה!',
                        message: data.data.message || `התור נקבע בהצלחה עבור ${data.data.patient_name || 'המטופל'}`,
                        button: null,
                        autoClose: 2000,
                        onClose: () => {
                            // מעבר לעמוד תורי המשתמש
                            window.location.href = '/my-appointments/';
                        }
                    });
                } else {
                    // שגיאה כללית
                    this.messageBox
                        .removeClass('msg-success')
                        .addClass('msg-error')
                        .text(data.data?.message || 'שגיאה');
                    this.messageBox.show();
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
        
        /**
         * Show modal popup
         * 
         * @param {Object} options Modal options
         * @param {string} options.type Modal type ('success' or 'error')
         * @param {string} options.title Modal title
         * @param {string} options.message Modal message
         * @param {string|null} options.button Button text (null for no button)
         * @param {Function|null} options.onClose Callback when modal closes
         * @param {number|null} options.autoClose Auto close after milliseconds
         */
        showModal({ type, title, message, button, onClose, autoClose }) {
            // Remove existing modal if any
            $('.booking-modal-overlay').remove();
            
            // Create modal HTML
            const icon = type === 'success' ? '✓' : '✗';
            const modalHtml = `
                <div class="booking-modal-overlay">
                    <div class="booking-modal booking-modal--${type}">
                        <div class="booking-modal__icon">${icon}</div>
                        <div class="booking-modal__title">${title}</div>
                        <div class="booking-modal__message">${message}</div>
                        ${button ? `<button class="booking-modal__button">${button}</button>` : ''}
                    </div>
                </div>
            `;
            
            // Append to body
            $('body').append(modalHtml);
            
            const $overlay = $('.booking-modal-overlay');
            const $modal = $('.booking-modal');
            
            // Handle button click
            if (button) {
                $modal.find('.booking-modal__button').on('click', () => {
                    this.closeModal(onClose);
                });
            }
            
            // Handle overlay click (close on click outside)
            $overlay.on('click', (e) => {
                if (e.target === $overlay[0]) {
                    this.closeModal(onClose);
                }
            });
            
            // Auto close if specified
            if (autoClose) {
                setTimeout(() => {
                    this.closeModal(onClose);
                }, autoClose);
            }
        }
        
        /**
         * Close modal
         * 
         * @param {Function|null} onClose Callback
         */
        closeModal(onClose) {
            $('.booking-modal-overlay').fadeOut(300, function() {
                $(this).remove();
                if (onClose && typeof onClose === 'function') {
                    onClose();
                }
            });
        }
        
        /**
         * Get referrer URL from form or URL params
         * 
         * @returns {string|null}
         */
        getReferrerUrl() {
            // Try to get from form field first
            const formField = this.form.find('#referrer_url');
            if (formField.length && formField.val()) {
                return formField.val();
            }
            
            // Fallback to URL params
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('referrer_url') || null;
        }

        /** מחזיר מזהה JetPopup (jet-popup-123) או null */
        getJetPopupId() {
            const id = parseInt(String(this.popupId || '').replace(/^jet-popup-/, ''), 10);
            return Number.isNaN(id) ? null : 'jet-popup-' + id;
        }

        openAddPatientPopup() {
            const popupId = this.getJetPopupId();
            if (!popupId) return;
            $(window).trigger({ type: 'jet-popup-open-trigger', popupData: { popupId } });
        }

        refreshFamilyListAndClosePopup() {
            const popupId = this.getJetPopupId();
            if (popupId) {
                $(window).trigger({ type: 'jet-popup-close-trigger', popupData: { popupId } });
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

    // Initialize – תמיד יוצרים מנג'ר; event delegation מטפל בלחיצה גם אם הטופס נטען מאוחר
    $(document).ready(function() {
        new BookingFormManager();
    });

})(jQuery);
