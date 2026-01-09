/**
 * Clinic Queue Widget - Main Entry Point
 * Location: frontend/assets/js/widgets/clinic-queue/clinic-queue.js
 * 
 * Loads all modules in correct order:
 * 1. modules/clinic-queue-utils.js
 * 2. modules/clinic-queue-data-manager.js
 * 3. modules/clinic-queue-ui-manager.js
 * 4. modules/clinic-queue-widget.js
 * 5. modules/clinic-queue-init.js
 */
(function($) {
    'use strict';
    
    // This file serves as the main entry point for the Clinic Queue Widget
    // All functionality is split into modules under widgets/clinic-queue/modules/
    // - clinic-queue-utils.js: Utility functions
    // - clinic-queue-data-manager.js: Data operations and API calls
    // - clinic-queue-ui-manager.js: UI rendering and interactions
    // - booking-calendar-core.js: Main core class
    // - clinic-queue-init.js: Global initialization and utilities
    
    console.log('[BookingCalendar] Main entry point loaded');
    
    // Verify all modules are loaded
    if (typeof window.BookingCalendarUtils === 'undefined') {
        console.error('[BookingCalendar] Utils module not loaded');
    }
    if (typeof window.BookingCalendarDataManager === 'undefined') {
        console.error('[BookingCalendar] DataManager module not loaded');
    }
    if (typeof window.BookingCalendarUIManager === 'undefined') {
        console.error('[BookingCalendar] UIManager module not loaded');
    }
    if (typeof window.BookingCalendarCore === 'undefined') {
        console.error('[BookingCalendar] Core module not loaded');
    }
    if (typeof window.BookingCalendarManager === 'undefined') {
        console.error('[BookingCalendar] Manager module not loaded');
    }
    
})(jQuery);