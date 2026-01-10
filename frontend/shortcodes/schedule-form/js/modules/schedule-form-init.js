/**
 * Schedule Form Initialization Module
 * Global initialization and utility functions
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	// Global registry for form instances
	window.ScheduleFormManager = window.ScheduleFormManager || {
		instances: new Map(),
		globalSettings: {
			loadingTimeouts: new Map(),
			sharedCache: new Map()
		}
	};

	/**
	 * Initialize forms when DOM is ready
	 */
	function initializeForms() {
		const forms = document.querySelectorAll('.clinic-add-schedule-form:not([data-initialized])');
		
		if (forms.length === 0) {
			return;
		}

		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log(`Initializing ${forms.length} form(s)...`);
		}

		forms.forEach((form, index) => {
			try {
				// Mark as initialized
				form.setAttribute('data-initialized', 'true');
				
				// Create unique ID if needed
				if (!form.id) {
					form.id = `schedule-form-${index}`;
				}

				// Check if scheduleFormData is available
				if (typeof window.scheduleFormData === 'undefined') {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.error('Configuration data (scheduleFormData) not found');
					}
					return;
				}

				// Verify all modules are loaded
				const requiredModules = [
					'ScheduleFormDataManager',
					'ScheduleFormStepsManager',
					'ScheduleFormUIManager',
					'ScheduleFormFieldManager',
					'ScheduleFormFormManager',
					'ScheduleFormGoogleCalendarManager',
					'ScheduleFormCore'
				];

				const missingModules = requiredModules.filter(module => typeof window[module] === 'undefined');
				if (missingModules.length > 0) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.error('Missing modules:', missingModules);
					}
					return;
				}

				// Initialize core
				const core = new window.ScheduleFormCore(form, window.scheduleFormData);
				
				// Store instance on element for external access
				form.scheduleFormCore = core;
				
				// Register instance in global registry
				window.ScheduleFormManager.instances.set(form.id, core);

				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log(`Form #${index} (${form.id}) initialized successfully`);
				}
			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error(`Error initializing form #${index}:`, error);
				}
			}
		});
	}

	/**
	 * Initialize on DOM ready
	 */
	function onDOMReady() {
		if (window.ScheduleFormUtils) {
			window.ScheduleFormUtils.log('DOM ready, initializing forms...');
		}
		initializeForms();
		
		// For Elementor editor - reinitialize after a delay
		if (typeof elementor !== 'undefined' || window.location.href.indexOf('elementor') > -1) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.log('Elementor detected, scheduling delayed init...');
			}
			setTimeout(() => {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log('Delayed init for Elementor...');
				}
				initializeForms();
			}, 1000);
		}
	}

	// Check if DOM is already loaded
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onDOMReady);
	} else {
		// DOM already loaded
		onDOMReady();
	}
	
	// Re-initialize if new forms are added dynamically
	// Using MutationObserver to detect new forms
	if (typeof MutationObserver !== 'undefined') {
		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === 1) { // Element node
						// Check if the added node is our form or contains our form
						const isForm = node.classList && node.classList.contains('clinic-add-schedule-form');
						const containsForm = node.querySelector && node.querySelector('.clinic-add-schedule-form');
						
						if ((isForm || containsForm) && !node.hasAttribute('data-initialized')) {
							// Check if it's actually our form (not just any element)
							const formElement = isForm ? node : node.querySelector('.clinic-add-schedule-form');
							if (formElement && !formElement.hasAttribute('data-initialized')) {
								setTimeout(initializeForms, 100);
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
		elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', () => {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.log('Elementor shortcode widget ready, reinitializing...');
			}
			setTimeout(initializeForms, 300);
		});
	}

	// Global utility functions
	window.ScheduleFormManager.utils = {
		/**
		 * Get form instance by ID
		 * @param {string} formId - Form ID
		 * @returns {ScheduleFormCore|null} Form instance or null
		 */
		getInstance: (formId) => window.ScheduleFormManager.instances.get(formId),
		
		/**
		 * Get all form instances
		 * @returns {Array<ScheduleFormCore>} Array of all form instances
		 */
		getAllInstances: () => Array.from(window.ScheduleFormManager.instances.values()),
		
		/**
		 * Destroy form instance
		 * @param {string} formId - Form ID
		 */
		destroyInstance: (formId) => {
			const instance = window.ScheduleFormManager.instances.get(formId);
			if (instance && typeof instance.destroy === 'function') {
				instance.destroy();
			}
			window.ScheduleFormManager.instances.delete(formId);
		},
		
		/**
		 * Clear shared cache
		 */
		clearCache: () => window.ScheduleFormManager.globalSettings.sharedCache.clear(),
		
		/**
		 * Reinitialize all forms
		 */
		reinitialize: initializeForms
	};

})(window);
