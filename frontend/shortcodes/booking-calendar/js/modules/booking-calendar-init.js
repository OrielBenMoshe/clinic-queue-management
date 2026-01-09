/**
 * Clinic Queue Management - Initialization Module
 * Global initialization and utility functions
 */
(function($) {
    'use strict';

    // Global registry for widget instances
    window.BookingCalendarManager = window.BookingCalendarManager || {
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
            const altWidgets = $('.booking-calendar-shortcode:not([data-initialized])');
            
            altWidgets.each(function() {
                $(this).attr('data-initialized', 'true');
                new window.BookingCalendarCore(this);
            });
        } else {
            widgets.each(function() {
                $(this).attr('data-initialized', 'true');
                new window.BookingCalendarCore(this);
            });
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        console.log('[BookingCalendar] DOM ready, initializing widgets...');
        initializeWidgets();
        
        // For Elementor editor - reinitialize after a delay
        if (typeof elementor !== 'undefined' || window.location.href.indexOf('elementor') > -1) {
            console.log('[BookingCalendar] Elementor detected, scheduling delayed init...');
            setTimeout(function() {
                console.log('[BookingCalendar] Delayed init for Elementor...');
                initializeWidgets();
            }, 1000);
        }
    });
    
    // Re-initialize if new widgets are added dynamically
    // IMPORTANT: Only listen for our specific widgets, not all DOM changes
    // This prevents interference with other plugins like JetFormBuilder
    $(document).on('DOMNodeInserted', function(e) {
        const $target = $(e.target);
        // Only initialize if it's our widget, not any other element
        if (($target.hasClass('ap-widget') || $target.hasClass('booking-calendar-shortcode')) && 
            !$target.attr('data-initialized') &&
            ($target.closest('.ap-widget').length > 0 || $target.closest('.booking-calendar-shortcode').length > 0)) {
            setTimeout(initializeWidgets, 100);
        }
    });
    
    // Listen for Elementor preview loaded event
    if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks && typeof elementorFrontend.hooks.addAction === 'function') {
        elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function() {
            console.log('[BookingCalendar] Elementor shortcode widget ready, reinitializing...');
            setTimeout(initializeWidgets, 300);
        });
    }

    // Global utility functions
    window.BookingCalendarManager.utils = {
        getInstance: (widgetId) => window.BookingCalendarManager.instances.get(widgetId),
        getAllInstances: () => Array.from(window.BookingCalendarManager.instances.values()),
        destroyInstance: (widgetId) => {
            const instance = window.BookingCalendarManager.instances.get(widgetId);
            if (instance) instance.destroy();
        },
        clearCache: () => window.BookingCalendarManager.globalSettings.sharedCache.clear(),
        reinitialize: initializeWidgets
    };

})(jQuery);
