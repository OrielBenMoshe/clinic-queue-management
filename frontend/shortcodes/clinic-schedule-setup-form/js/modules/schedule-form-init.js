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
		const forms = document.querySelectorAll(
			'.clinic-add-schedule-form:not([data-initialized]):not([data-schedule-form-role="edit-modal"])'
		);
		
		if (forms.length === 0) {
			return;
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

			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error(`Error initializing form #${index}:`, error);
				}
			}
		});
	}

	const WIZARD_FORM_SELECTOR = '.clinic-add-schedule-form:not([data-schedule-form-role="edit-modal"])';

	/**
	 * Reset wizard schedule form instances inside a popup/modal container.
	 *
	 * @param {Element|null} containerEl Popup root element
	 */
	function resetFormsInContainer(containerEl) {
		if (!containerEl) {
			return;
		}

		containerEl.querySelectorAll(WIZARD_FORM_SELECTOR).forEach(function(form) {
			const core = form.scheduleFormCore;
			if (core && typeof core.reset === 'function') {
				core.reset();
			}
		});
	}

	/**
	 * Ensure wizard forms in a popup are initialized, then reset to step 1.
	 *
	 * @param {Element|null} containerEl Popup root element
	 */
	function prepareWizardFormsInContainer(containerEl) {
		if (!containerEl) {
			return;
		}

		const hasUninitializedForms = containerEl.querySelector(
			WIZARD_FORM_SELECTOR + ':not([data-initialized])'
		);
		if (hasUninitializedForms) {
			initializeForms();
		}

		resetFormsInContainer(containerEl);
	}

	/**
	 * Resolve popup id from event arguments or JetPopup event payload.
	 *
	 * @param {string|number|null} popupId Popup identifier
	 * @param {jQuery.Event|null} event jQuery event object
	 * @returns {string|number|null}
	 */
	function resolvePopupId(popupId, event) {
		if (popupId !== null && popupId !== undefined && popupId !== '') {
			return popupId;
		}

		if (event && event.popupData && event.popupData.popupId) {
			return event.popupData.popupId;
		}

		return null;
	}

	/**
	 * Reset wizard forms when a popup opens so users always start at step 1.
	 *
	 * @param {string|number|null} popupId Popup identifier
	 * @param {Object|null} instance Optional Elementor popup instance
	 * @param {jQuery.Event|null} event Optional jQuery event object
	 */
	function handlePopupShow(popupId, instance, event) {
		prepareWizardFormsInContainer(
			resolvePopupElement(resolvePopupId(popupId, event), instance)
		);
	}

	/**
	 * Resolve popup DOM node from JetPopup id or Elementor popup instance.
	 *
	 * @param {string|number|null} popupId Popup identifier (e.g. 128 or jet-popup-128)
	 * @param {Object|null} instance Optional Elementor popup instance
	 * @returns {Element|null}
	 */
	function resolvePopupElement(popupId, instance) {
		if (instance && instance.$element && instance.$element[0]) {
			return instance.$element[0];
		}

		if (popupId === null || popupId === undefined || popupId === '') {
			return null;
		}

		const id = String(popupId).trim();
		if (!id) {
			return null;
		}

		return document.getElementById(id)
			|| document.getElementById(id.startsWith('jet-popup-') ? id : 'jet-popup-' + id);
	}

	/**
	 * Reset wizard forms when their hosting popup opens or closes.
	 * Primary: JetPopup (Crocoblock). Fallback: Elementor Pro Popup.
	 */
	function setupPopupFormListeners() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		const $ = jQuery;

		// JetPopup — reset before popup is shown (user always sees step 1)
		$(window).on('jet-popup/show-event/before-show', function(event, popupId) {
			handlePopupShow(popupId, null, event);
		});

		// JetPopup — fallback when popup content loads after show (AJAX popups)
		$(window).on('jet-popup/show-event/after-show', function(event, popupId) {
			handlePopupShow(popupId, null, event);
		});

		// JetPopup — fires after the popup is hidden (backup cleanup on close)
		$(window).on('jet-popup/hide-event/after-hide', function(event, popupId) {
			resetFormsInContainer(resolvePopupElement(popupId));
		});

		// JetPopup — programmatic close trigger
		$(window).on('jet-popup-close-trigger', function(event) {
			const popupId = event.popupData && event.popupData.popupId;
			resetFormsInContainer(resolvePopupElement(popupId));
		});

		// Elementor Pro Popup — reset on show
		$(document).on('elementor/popup/show', function(event, id, instance) {
			handlePopupShow(id, instance, event);
		});

		// Elementor Pro Popup — backup cleanup on close
		$(document).on('elementor/popup/hide', function(event, id, instance) {
			resetFormsInContainer(resolvePopupElement(id, instance));
		});
	}

	/**
	 * Initialize on DOM ready
	 */
	function onDOMReady() {
		initializeForms();
		setupPopupFormListeners();
		
		// For Elementor editor - reinitialize after a delay
		if (typeof elementor !== 'undefined' || window.location.href.indexOf('elementor') > -1) {
			setTimeout(() => {
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
