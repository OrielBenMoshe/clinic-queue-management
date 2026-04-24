/**
 * Doctor Connect Core Module
 *
 * @package Clinic_Queue_Management
 */

(function(window, document) {
    'use strict';

    class DoctorConnectCore {
        constructor(config) {
            this.config = config || {};
            this.root = document.querySelector('.clinic-doctor-connect');
            if (!this.root) {
                return;
            }

            this.schedulerId = Number(this.config.schedulerId || 0);
            this.accessToken = (this.config.accessToken || '').trim();

            this.formData = {
                selected_calendar_id: '',
                source_credentials_id: null
            };

            this.googleAuthManager = new window.ScheduleFormGoogleAuthManager({
                googleClientId: this.config.googleClientId || '',
                googleScopes: this.config.googleScopes || '',
                restUrl: this.config.restUrl || '',
                restNonce: this.config.restNonce || ''
            });

            this.googleAuthManager.setSchedulerId(this.schedulerId);
            if (this.accessToken) {
                this.googleAuthManager.setAccessToken(this.accessToken);
            }

            this.bindEvents();

            if (!this.schedulerId && !this.accessToken) {
                this.applyInactiveState();
                return;
            }

            this.loadSchedulerInfo();
        }

        bindEvents() {
            const connectButton = this.root.querySelector('[data-action="connect-google"]');
            if (connectButton) {
                connectButton.addEventListener('click', () => this.handleConnectGoogle());
            }

            const saveButton = this.root.querySelector('.save-calendar-btn');
            if (saveButton) {
                saveButton.addEventListener('click', () => this.handleSaveCalendarSelection());
            }
        }

        async loadSchedulerInfo() {
            try {
                const query = new URLSearchParams({
                    scheduler_id: String(this.schedulerId || 0)
                });
                if (this.accessToken) {
                    query.set('access_token', this.accessToken);
                }

                const response = await fetch(`${this.config.restUrl}/doctor/scheduler-info?${query.toString()}`, {
                    headers: {
                        'X-WP-Nonce': this.config.restNonce
                    }
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed loading scheduler');
                }

                this.renderWorkingDays(result.data.working_days || {});
            } catch (error) {
                this.showError(error.message || 'שגיאה בטעינת פרטי היומן');
            }
        }

        applyInactiveState() {
            this.root.classList.add('is-inactive');
            this.renderWorkingDays({});

            const inactiveNote = this.root.querySelector('[data-role="inactive-note"]');
            if (inactiveNote) {
                inactiveNote.style.display = 'block';
            }

            this.root.querySelectorAll('[data-action], .save-calendar-btn').forEach((button) => {
                button.setAttribute('disabled', 'disabled');
            });
        }

        renderWorkingDays(workingDays) {
            const target = this.root.querySelector('[data-role="working-days"]');
            if (!target) {
                return;
            }

            const dayLabels = {
                sunday: 'יום א׳',
                monday: 'יום ב׳',
                tuesday: 'יום ג׳',
                wednesday: 'יום ד׳',
                thursday: 'יום ה׳',
                friday: 'יום ו׳',
                saturday: 'שבת'
            };

            const lines = [];
            Object.keys(workingDays).forEach((dayKey) => {
                const ranges = Array.isArray(workingDays[dayKey]) ? workingDays[dayKey] : [];
                const formatted = ranges
                    .map((range) => `${range.start_time || ''}-${range.end_time || ''}`)
                    .filter(Boolean)
                    .join(', ');

                if (formatted) {
                    lines.push(`<p>${dayLabels[dayKey] || dayKey}: ${formatted}</p>`);
                }
            });

            target.innerHTML = lines.length ? lines.join('') : '<p>לא הוגדרו ימי עבודה</p>';
        }

        async handleConnectGoogle() {
            try {
                this.showError('');
                const result = await this.googleAuthManager.connect();
                const sourceCredsId = result && result.data ? result.data.source_credentials_id : null;
                if (!sourceCredsId) {
                    throw new Error('לא התקבל מזהה credentials');
                }

                this.formData.source_credentials_id = sourceCredsId;
                await this.loadCalendars();
                this.showStep('calendar-selection');
            } catch (error) {
                this.showError(error.message || 'שגיאה בחיבור לגוגל');
            }
        }

        async loadCalendars() {
            const query = new URLSearchParams({
                scheduler_id: String(this.schedulerId || 0),
                source_creds_id: String(this.formData.source_credentials_id || 0)
            });
            if (this.accessToken) {
                query.set('access_token', this.accessToken);
            }

            const response = await fetch(`${this.config.restUrl}/google/calendars?${query.toString()}`, {
                headers: {
                    'X-WP-Nonce': this.config.restNonce
                }
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'שגיאה בטעינת יומנים');
            }

            const stepsAdapter = {
                updateFormData: (data) => {
                    this.formData = Object.assign({}, this.formData, data);
                }
            };

            window.ScheduleFormCalendarList.renderCalendarList(this.root, result.calendars || [], stepsAdapter);
        }

        async handleSaveCalendarSelection() {
            try {
                const selected = this.root.querySelector('.calendar-item.is-selected');
                if (!selected) {
                    throw new Error('אנא בחר יומן');
                }

                const payload = {
                    scheduler_id: this.schedulerId,
                    source_credentials_id: this.formData.source_credentials_id,
                    source_scheduler_id: selected.dataset.sourceSchedulerId || '',
                    access_token: this.accessToken || ''
                };

                const response = await fetch(`${this.config.restUrl}/scheduler/create-schedule-in-proxy`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.restNonce
                    },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'שגיאה ביצירת יומן בפרוקסי');
                }

                this.showStep('final-success');
            } catch (error) {
                this.showError(error.message || 'שגיאה בשמירת היומן');
            }
        }

        showStep(stepName) {
            this.root.querySelectorAll('[data-step]').forEach((step) => {
                step.style.display = step.dataset.step === stepName ? 'block' : 'none';
            });
        }

        showError(message) {
            const errorEl = this.root.querySelector('[data-role="error-message"]');
            if (!errorEl) {
                return;
            }
            if (!message) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
                return;
            }
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
    }

    window.DoctorConnectCore = DoctorConnectCore;

    document.addEventListener('DOMContentLoaded', function() {
        if (!window.doctorConnectData) {
            return;
        }
        window.doctorConnectCore = new DoctorConnectCore(window.doctorConnectData);
    });
})(window, document);
