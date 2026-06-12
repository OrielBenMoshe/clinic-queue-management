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

            this.schedulerId  = Number(this.config.schedulerId || 0);
            this.accessToken  = (this.config.accessToken || '').trim();
            this.clinicName   = (this.config.clinicName || '').trim();
            this.calendarName = (this.config.calendarName || '').trim();

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

            // Populate URL-param data immediately (no loading delay).
            this.populateFromUrlParams();

            // Fetch authoritative data from REST (updates fields not set from URL).
            this.loadSchedulerInfo();
        }

        /**
         * Populate the UI immediately from URL parameters (clinic & calendar name).
         * Working days are always fetched from REST to get the full time-range data.
         */
        populateFromUrlParams() {
            // Clinic name — inline subtitle span + summary info-row
            this.setInfoField('clinic-name', this.clinicName, '[data-role="clinic-name-row"]');
            this.setInfoField('clinic-name-inline', this.clinicName);

            // Calendar name — also reveals the divider above working-hours
            this.setCalendarName(this.calendarName);
        }

        /**
         * Set the calendar name and show the divider above working hours if non-empty.
         *
         * @param {string} value Calendar name to display.
         */
        setCalendarName(value) {
            this.setInfoField('calendar-name', value, '[data-role="calendar-name-row"]');
        }

        /**
         * Set all elements sharing a data-role and optionally reveal a hidden row wrapper.
         *
         * @param {string} role          data-role value to target (all matching elements are updated).
         * @param {string} value         Text to display.
         * @param {string} [rowSelector] Optional selector of the hidden row wrapper to reveal.
         */
        setInfoField(role, value, rowSelector) {
            if (!value) {
                return;
            }
            this.root.querySelectorAll(`[data-role="${role}"]`).forEach((el) => {
                el.textContent = value;
            });
            if (rowSelector) {
                const rowEl = this.root.querySelector(rowSelector);
                if (rowEl) {
                    rowEl.style.display = '';
                }
            }
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

            // כפתור "כאן" לרענון יומנים — delegated כי הוא בתוך calendar-selection שנטען דינמית
            this.root.addEventListener('click', (e) => {
                if (e.target.closest('[data-action="refresh-calendars"]')) {
                    this.refreshCalendars();
                }
            });
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

                const info = result.data || {};

                // Fill in clinic name if it wasn't passed in the URL.
                if (!this.clinicName && info.clinic_name) {
                    this.clinicName = info.clinic_name;
                    this.setInfoField('clinic-name', this.clinicName, '[data-role="clinic-name-row"]');
                    this.setInfoField('clinic-name-inline', this.clinicName);
                }

                // Fill in calendar name if it wasn't passed in the URL.
                const restCalendarName = info.schedule_name || info.manual_calendar_name || '';
                if (!this.calendarName && restCalendarName) {
                    this.calendarName = restCalendarName;
                    this.setCalendarName(this.calendarName);
                }

                // Working days always come from REST (includes per-day time ranges).
                this.renderWorkingDays(info.working_days || {});
            } catch (error) {
                this.showError(error.message || 'שגיאה בטעינת פרטי היומן');
            }
        }

        applyInactiveState() {
            this.root.classList.add('is-inactive');
            this.renderWorkingDays({}, true);

            const inactiveNote = this.root.querySelector('[data-role="inactive-note"]');
            if (inactiveNote) {
                inactiveNote.style.display = 'block';
            }

            // Disable only the approval-card buttons, not the modal's buttons.
            const approvalCard = this.root.querySelector('[data-step="approval"]');
            if (approvalCard) {
                approvalCard.querySelectorAll('[data-action], .save-calendar-btn').forEach((btn) => {
                    btn.setAttribute('disabled', 'disabled');
                });
            }
        }

        renderWorkingDays(workingDays) {
            const target = this.root.querySelector('[data-role="working-days"]');
            if (!target) {
                return;
            }

            const dayLabels = {
                sunday: 'א׳',
                monday: 'ב׳',
                tuesday: 'ג׳',
                wednesday: 'ד׳',
                thursday: 'ה׳',
                friday: 'ו׳',
                saturday: 'ש׳'
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

            target.innerHTML = lines.length ? lines.join('') : '<p>לא הוגדרו שעות פעילות</p>';
        }

        async handleConnectGoogle() {
            try {
                this.showError('');
                this.showLoader();
                const result = await this.googleAuthManager.connect();
                const sourceCredsId = result && result.data ? result.data.source_credentials_id : null;
                if (!sourceCredsId) {
                    throw new Error('לא התקבל מזהה credentials');
                }

                this.formData.source_credentials_id = sourceCredsId;
                await this.loadCalendars();
                this.hideLoader();
                this.showStep('calendar-selection');
            } catch (error) {
                this.hideLoader();
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

        /**
         * רענון רשימת היומנים — נקרא מכפתור "כאן" כשכל היומנים בשימוש.
         */
        async refreshCalendars() {
            try {
                this.showLoader();
                await this.loadCalendars();
                this.hideLoader();
            } catch (error) {
                this.hideLoader();
                this.showError(error.message || 'שגיאה בטעינת יומנים');
            }
        }

        async handleSaveCalendarSelection() {
            try {
                const selected = this.root.querySelector('.calendar-item.is-selected');
                if (!selected) {
                    throw new Error('אנא בחר יומן');
                }

                this.showLoader();
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

                this.hideLoader();
                this.showStep('final-success');
            } catch (error) {
                this.hideLoader();
                this.showError(error.message || 'שגיאה בשמירת היומן');
            }
        }

        showLoader() {
            const overlay = this.root.querySelector('[data-role="card-loader"]');
            if (overlay) {
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');
            }
        }

        hideLoader() {
            const overlay = this.root.querySelector('[data-role="card-loader"]');
            if (overlay) {
                overlay.classList.remove('is-visible');
                overlay.setAttribute('aria-hidden', 'true');
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
