/**
 * Toggle between login and registration forms in the [booking_form] guest gate.
 * Uses innerHTML — previous form content is removed from the DOM and replaced from server-embedded data.
 *
 * Depends on window.ClinicQueueBookingRegisterGate (JSON config from server).
 */
(function () {
    const cfg = window.ClinicQueueBookingRegisterGate;
    if (
        typeof cfg !== 'object' ||
        cfg === null ||
        typeof cfg.loginHtml !== 'string' ||
        typeof cfg.registerHtml !== 'string'
    ) {
        return;
    }

    /**
     *
     * @param {HTMLElement} root
     */
    function bindRoot(root) {
        const mount = root.querySelector('.clinic-queue-booking-register-gate__mount');
        const rowLogin = root.querySelector(
            '.clinic-queue-booking-register-gate__switch-row--login'
        );
        const rowRegister = root.querySelector(
            '.clinic-queue-booking-register-gate__switch-row--register'
        );
        if (!mount || !rowLogin || !rowRegister) {
            return;
        }

        /**
         * @param {'login'|'register'} next
         */
        function setMode(next) {
            if (next !== 'login' && next !== 'register') {
                return;
            }

            mount.innerHTML = next === 'login' ? cfg.loginHtml : cfg.registerHtml;

            if (next === 'login') {
                rowLogin.hidden = false;
                rowLogin.setAttribute('aria-hidden', 'false');
                rowRegister.hidden = true;
                rowRegister.setAttribute('aria-hidden', 'true');
            } else {
                rowLogin.hidden = true;
                rowLogin.setAttribute('aria-hidden', 'true');
                rowRegister.hidden = false;
                rowRegister.setAttribute('aria-hidden', 'false');
            }

            root.dispatchEvent(
                new CustomEvent('clinicQueueBookingRegisterGate:modechange', {
                    detail: { mode: next },
                })
            );
        }

        rowLogin.hidden = false;
        rowLogin.setAttribute('aria-hidden', 'false');
        rowRegister.hidden = true;
        rowRegister.setAttribute('aria-hidden', 'true');

        root.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-clinic-queue-register-gate-switch]');
            if (!trigger || !root.contains(trigger)) {
                return;
            }
            event.preventDefault();
            const target = trigger.getAttribute('data-clinic-queue-register-gate-switch');
            setMode(target === 'register' ? 'register' : 'login');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document
            .querySelectorAll('[data-clinic-queue-register-gate-root]')
            .forEach(function (rootEl) {
                bindRoot(rootEl);
            });
    });
})();
