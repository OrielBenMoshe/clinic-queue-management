/**
 * Clinic Queue Management - Utils Module
 * Utility functions for the clinic queue system
 */
(function($) {
    'use strict';

    // Utility functions
    const Utils = {
        formatDate: (date, format = 'YYYY-MM-DD') => {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        
        log: (message, data = null) => {
            if (window.console && window.console.log) {
                if (data !== null && data !== undefined) {
                    console.log(`[ClinicQueue] ${message}`, data);
                } else {
                    console.log(`[ClinicQueue] ${message}`);
                }
            }
        },
        
        error: (message, error = null) => {
            if (window.console && window.console.error) {
                if (error !== null && error !== undefined) {
                    console.error(`[ClinicQueue] ${message}`, error);
                } else {
                    console.error(`[ClinicQueue] ${message}`);
                }
            }
        }
    };

    // Export to global scope
    window.ClinicQueueUtils = Utils;

})(jQuery);
