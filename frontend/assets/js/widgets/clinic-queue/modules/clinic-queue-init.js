/**
 * Clinic Queue Management - Initialization Module
 * Global initialization and utility functions
 */
(function($) {
    'use strict';

    // Global registry for widget instances
    window.ClinicQueueManager = window.ClinicQueueManager || {
        instances: new Map(),
        globalSettings: {
            loadingTimeouts: new Map(),
            sharedCache: new Map()
        }
    };

    // Initialize widgets when DOM is ready
    function initializeWidgets() {
        const widgets = $('.ap-widget:not([data-initialized])');
        
        if (widgets.length === 0) {
            const altWidgets = $('.appointments-calendar:not([data-initialized])');
            
            altWidgets.each(function() {
                $(this).attr('data-initialized', 'true');
                new window.ClinicQueueWidget(this);
            });
        } else {
            widgets.each(function() {
                $(this).attr('data-initialized', 'true');
                new window.ClinicQueueWidget(this);
            });
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        console.log('[ClinicQueue] DOM ready, initializing widgets...');
        initializeWidgets();
    });
    
    // Re-initialize if new widgets are added dynamically
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('ap-widget') && !$(e.target).attr('data-initialized')) {
            setTimeout(initializeWidgets, 100);
        }
    });

    // Global utility functions
    window.ClinicQueueManager.utils = {
        getInstance: (widgetId) => window.ClinicQueueManager.instances.get(widgetId),
        getAllInstances: () => Array.from(window.ClinicQueueManager.instances.values()),
        destroyInstance: (widgetId) => {
            const instance = window.ClinicQueueManager.instances.get(widgetId);
            if (instance) instance.destroy();
        },
        clearCache: () => window.ClinicQueueManager.globalSettings.sharedCache.clear(),
        reinitialize: initializeWidgets
    };

})(jQuery);
