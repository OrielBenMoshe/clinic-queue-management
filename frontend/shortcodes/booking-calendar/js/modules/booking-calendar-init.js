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
        if (window.BookingCalendarUtils) {
            window.BookingCalendarUtils.log('DOM ready, initializing widgets...');
        }
        initializeWidgets();
        
        // For Elementor editor - reinitialize after a delay
        if (typeof elementor !== 'undefined' || window.location.href.indexOf('elementor') > -1) {
            if (window.BookingCalendarUtils) {
                window.BookingCalendarUtils.log('Elementor detected, scheduling delayed init...');
            }
            setTimeout(function() {
                if (window.BookingCalendarUtils) {
                    window.BookingCalendarUtils.log('Delayed init for Elementor...');
                }
                initializeWidgets();
            }, 1000);
        }
    });
    
    // Re-initialize if new widgets are added dynamically
    // IMPORTANT: Only listen for our specific widgets, not all DOM changes
    // This prevents interference with other plugins like JetFormBuilder
    // Using MutationObserver instead of deprecated DOMNodeInserted
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        const $target = $(node);
                        // Check if the added node is our widget or contains our widget
                        const isWidget = $target.hasClass('ap-widget') || $target.hasClass('booking-calendar-shortcode');
                        const containsWidget = $target.find('.ap-widget, .booking-calendar-shortcode').length > 0;
                        
                        if ((isWidget || containsWidget) && !$target.attr('data-initialized')) {
                            // Check if it's actually our widget (not just any element)
                            const widgetElement = isWidget ? $target : $target.find('.ap-widget, .booking-calendar-shortcode').first();
                            if (widgetElement.length && !widgetElement.attr('data-initialized')) {
                                setTimeout(initializeWidgets, 100);
                            }
                        }
                    }
                });
            });
        });
        
        // Start observing the document body for added nodes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Listen for Elementor preview loaded event
    if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks && typeof elementorFrontend.hooks.addAction === 'function') {
        elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function() {
            if (window.BookingCalendarUtils) {
                window.BookingCalendarUtils.log('Elementor shortcode widget ready, reinitializing...');
            }
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
