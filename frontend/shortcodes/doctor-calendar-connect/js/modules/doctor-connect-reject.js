/**
 * Doctor Connect Reject Module
 *
 * @package Clinic_Queue_Management
 */

(function(window, document) {
    'use strict';

    function initRejectFlow() {
        const root = document.querySelector('.clinic-doctor-connect');
        if (!root || !window.doctorConnectData) {
            return;
        }

        const rejectButton = root.querySelector('[data-action="reject-request"]');
        if (!rejectButton) {
            return;
        }

        rejectButton.addEventListener('click', async function() {
            const confirmed = window.confirm('האם למחוק את בקשת החיבור ולדחות אותה?');
            if (!confirmed) {
                return;
            }

            try {
                const payload = {
                    scheduler_id: window.doctorConnectData.schedulerId,
                    access_token: window.doctorConnectData.accessToken || ''
                };

                const response = await fetch(`${window.doctorConnectData.restUrl}/doctor/reject-scheduler`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.doctorConnectData.restNonce
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'שגיאה בדחיית הבקשה');
                }

                if (window.doctorConnectCore && typeof window.doctorConnectCore.showStep === 'function') {
                    window.doctorConnectCore.showStep('rejected');
                }
            } catch (error) {
                if (window.doctorConnectCore && typeof window.doctorConnectCore.showError === 'function') {
                    window.doctorConnectCore.showError(error.message || 'שגיאה בדחיית הבקשה');
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initRejectFlow);
})(window, document);
