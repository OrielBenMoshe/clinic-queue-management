/**
 * Appointments Management Page JavaScript
 * ניהול עמוד התורים - פונקציונליות צד לקוח
 * 
 * @package ClinicQueue
 * @subpackage Admin\JS
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Appointments Manager Class
     */
    class AppointmentsManager {
        constructor() {
            this.config = window.clinicQueueAppointments || {};
            this.init();
        }
        
        /**
         * Initialize the manager
         */
        init() {
            this.bindEvents();
        }
        
        /**
         * Bind event listeners
         */
        bindEvents() {
            // Create test appointment button
            $(document).on('click', '.clinic-queue-create-test-btn', this.handleCreateTest.bind(this));
            
            // Delete appointment button
            $(document).on('click', '.clinic-queue-delete-btn', this.handleDelete.bind(this));
        }
        
        /**
         * Handle create test appointment
         */
        handleCreateTest(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            button.prop('disabled', true).text('יוצר...');
            
            this.showLoading();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'clinic_queue_create_test_appointment',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', this.config.strings.createSuccess);
                        
                        // Reload page to show new appointment
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        this.showNotice('error', this.config.strings.createError);
                        button.prop('disabled', false).text('יצירת רשומת בדיקה');
                    }
                },
                error: () => {
                    this.showNotice('error', this.config.strings.createError);
                    button.prop('disabled', false).text('יצירת רשומת בדיקה');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }
        
        /**
         * Handle delete appointment
         */
        handleDelete(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirmDelete)) {
                return;
            }
            
            const button = $(e.currentTarget);
            const appointmentId = button.data('appointment-id');
            const row = button.closest('tr');
            
            button.prop('disabled', true).text('מוחק...');
            
            this.showLoading();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'clinic_queue_delete_appointment',
                    nonce: this.config.nonce,
                    appointment_id: appointmentId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', this.config.strings.deleteSuccess);
                        
                        // Remove row with animation
                        row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            const tbody = $('.clinic-queue-appointments-table tbody');
                            if (tbody.find('tr').length === 0) {
                                tbody.html(
                                    '<tr class="no-items">' +
                                    '<td colspan="9" class="colspanchange">' +
                                    'לא נמצאו תורים. לחץ על "יצירת רשומת בדיקה" כדי להתחיל.' +
                                    '</td>' +
                                    '</tr>'
                                );
                            }
                        });
                        
                        // Update stats (reload page to update)
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        this.showNotice('error', this.config.strings.deleteError);
                        button.prop('disabled', false).text('מחק');
                    }
                },
                error: () => {
                    this.showNotice('error', this.config.strings.deleteError);
                    button.prop('disabled', false).text('מחק');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }
        
        /**
         * Show loading overlay
         */
        showLoading() {
            $('.clinic-queue-loading-overlay').fadeIn(200);
        }
        
        /**
         * Hide loading overlay
         */
        hideLoading() {
            $('.clinic-queue-loading-overlay').fadeOut(200);
        }
        
        /**
         * Show admin notice
         * 
         * @param {string} type - Notice type (success, error, warning, info)
         * @param {string} message - Notice message
         */
        showNotice(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 
                               type === 'error' ? 'notice-error' :
                               type === 'warning' ? 'notice-warning' : 'notice-info';
            
            const notice = $('<div>')
                .addClass('notice ' + noticeClass + ' is-dismissible')
                .html('<p>' + message + '</p>');
            
            // Insert after first h1
            $('.clinic-queue-appointments-page h1').first().after(notice);
            
            // Add dismiss button functionality
            notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">סגור הודעה</span></button>');
            
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(200, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        new AppointmentsManager();
    });
    
})(jQuery);
