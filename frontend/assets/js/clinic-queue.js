/**
 * Clinic Queue Management - Main Entry Point
 * Loads all modules in correct order
 * 
 * Modules are loaded by WordPress in this order:
 * 1. utils.js
 * 2. data-manager.js
 * 3. ui-manager.js
 * 4. widget.js
 * 5. init.js
 */
(function($) {
    'use strict';
    
    // This file serves as the main entry point
    // All functionality is now split into modules:
    // - utils.js: Utility functions
    // - data-manager.js: Data operations and API calls
    // - ui-manager.js: UI rendering and interactions
    // - widget.js: Main widget class
    // - init.js: Global initialization and utilities
    
    console.log('[ClinicQueue] Main entry point loaded');
    
    // Verify all modules are loaded
    if (typeof window.ClinicQueueUtils === 'undefined') {
        console.error('[ClinicQueue] Utils module not loaded');
    }
    if (typeof window.ClinicQueueDataManager === 'undefined') {
        console.error('[ClinicQueue] DataManager module not loaded');
    }
    if (typeof window.ClinicQueueUIManager === 'undefined') {
        console.error('[ClinicQueue] UIManager module not loaded');
    }
    if (typeof window.ClinicQueueWidget === 'undefined') {
        console.error('[ClinicQueue] Widget module not loaded');
    }
    if (typeof window.ClinicQueueManager === 'undefined') {
        console.error('[ClinicQueue] Manager module not loaded');
    }
    
})(jQuery);