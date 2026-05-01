/**
 * Doctor Connect Reject Module
 *
 * Handles the "reject request" flow: shows a custom warning popup and, upon
 * confirmation, calls the REST endpoint that deletes the scheduler post.
 *
 * @package Clinic_Queue_Management
 */

(function(window, document) {
    'use strict';

    class DoctorConnectReject {
        constructor(root, data) {
            this.root = root;
            this.data = data;
            this.modal = root.querySelector('[data-role="reject-modal"]');
            this.isBusy = false;

            this.bindEvents();
        }

        bindEvents() {
            // Open modal
            const rejectBtn = this.root.querySelector('[data-action="reject-request"]');
            if (rejectBtn) {
                rejectBtn.addEventListener('click', () => this.openModal());
            }

            // Close modal (overlay + cancel button)
            this.root.querySelectorAll('[data-action="cancel-reject"]').forEach((el) => {
                el.addEventListener('click', () => this.closeModal());
            });

            // Confirm rejection
            const confirmBtn = this.root.querySelector('[data-action="confirm-reject"]');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => this.handleReject(confirmBtn));
            }

            // Close on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal && this.modal.style.display !== 'none') {
                    this.closeModal();
                }
            });
        }

        openModal() {
            if (!this.modal) {
                return;
            }
            this.clearModalError();
            this.modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        closeModal() {
            if (!this.modal || this.isBusy) {
                return;
            }
            this.modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        clearModalError() {
            const errorEl = this.modal && this.modal.querySelector('[data-role="modal-error"]');
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
            }
        }

        showModalError(message) {
            const errorEl = this.modal && this.modal.querySelector('[data-role="modal-error"]');
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }
        }

        setConfirmLoading(btn, isLoading) {
            if (!btn) {
                return;
            }
            btn.disabled = isLoading;
            btn.textContent = isLoading ? 'שולח...' : 'כן, דחה את הבקשה';
        }

        async handleReject(confirmBtn) {
            if (this.isBusy) {
                return;
            }
            this.isBusy = true;
            this.clearModalError();
            this.setConfirmLoading(confirmBtn, true);

            try {
                const payload = {
                    scheduler_id: this.data.schedulerId,
                    access_token: this.data.accessToken || ''
                };

                const response = await fetch(`${this.data.restUrl}/doctor/reject-scheduler`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.data.restNonce
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'שגיאה בדחיית הבקשה');
                }

                this.closeModal();

                if (window.doctorConnectCore && typeof window.doctorConnectCore.showStep === 'function') {
                    window.doctorConnectCore.showStep('rejected');
                }
            } catch (error) {
                this.showModalError(error.message || 'שגיאה בדחיית הבקשה');
            } finally {
                this.isBusy = false;
                this.setConfirmLoading(confirmBtn, false);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const root = document.querySelector('.clinic-doctor-connect');
        if (!root || !window.doctorConnectData) {
            return;
        }
        new DoctorConnectReject(root, window.doctorConnectData);
    });
})(window, document);
