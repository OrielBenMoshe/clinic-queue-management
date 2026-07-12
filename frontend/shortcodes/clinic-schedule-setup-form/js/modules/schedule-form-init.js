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
	const DEFAULT_WIZARD_POPUP_ID = 3746;
	const SHORTCODE_POPUP_FADE_MS = 350;
	let _shortcodePopupOpenCount = 0;

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
	 * Check whether a popup element is currently visible in the layout.
	 *
	 * @param {Element} element Popup root element
	 * @returns {boolean}
	 */
	function isPopupElementVisible(element) {
		if (!element) {
			return false;
		}

		if (element.getAttribute('aria-hidden') === 'true') {
			return false;
		}

		return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
	}

	/**
	 * Find JetPopup containers that host the schedule wizard form.
	 *
	 * @param {Object} [options] Lookup options
	 * @param {boolean} [options.requireVisible=true] Only return visible popups
	 * @returns {Element[]} Matching popup elements
	 */
	function findJetPopupsWithWizard(options) {
		const settings = options || {};
		const requireVisible = settings.requireVisible !== false;
		const matches = [];

		document.querySelectorAll('[id^="jet-popup-"], .jet-popup').forEach(function(popupEl) {
			if (!popupEl.querySelector(WIZARD_FORM_SELECTOR)) {
				return;
			}

			if (requireVisible && !isPopupElementVisible(popupEl)) {
				return;
			}

			matches.push(popupEl);
		});

		return matches;
	}

	/**
	 * Resolve the schedule wizard JetPopup id from localized config or markup.
	 *
	 * @returns {string|number}
	 */
	function getWizardPopupId() {
		if (window.scheduleFormData && window.scheduleFormData.wizardPopupId === false) {
			return null;
		}

		if (window.scheduleFormData && window.scheduleFormData.wizardPopupId) {
			return window.scheduleFormData.wizardPopupId;
		}

		const formWithAttr = document.querySelector(
			'.clinic-add-schedule-form[data-schedule-wizard-popup-id]'
		);
		if (formWithAttr) {
			const attrValue = formWithAttr.getAttribute('data-schedule-wizard-popup-id');
			return attrValue || null;
		}

		return DEFAULT_WIZARD_POPUP_ID;
	}

	/**
	 * Resolve the schedule wizard JetPopup root element.
	 *
	 * @returns {Element|null}
	 */
	function getWizardPopupElement() {
		return resolvePopupElement(getWizardPopupId());
	}

	/**
	 * Check whether a popup id matches the schedule wizard popup.
	 *
	 * @param {string|number|null} popupId Popup identifier
	 * @returns {boolean}
	 */
	function isWizardPopupId(popupId) {
		if (popupId === null || popupId === undefined || popupId === '') {
			return false;
		}

		const wizardPopupId = getWizardPopupId();
		if (!wizardPopupId) {
			return false;
		}

		const normalizedPopupId = String(popupId).trim().replace(/^jet-popup-/, '');
		return normalizedPopupId === String(wizardPopupId).trim();
	}

	/**
	 * Reset wizard forms inside the known schedule wizard popup (#jet-popup-3746).
	 */
	function handleWizardPopupOpen() {
		const popupEl = getWizardPopupElement();
		if (!popupEl) {
			return;
		}

		prepareWizardFormsInContainer(popupEl);
		setTimeout(function() {
			prepareWizardFormsInContainer(popupEl);
		}, 0);
	}

	/**
	 * Resolve popup id from event arguments or JetPopup event payload.
	 *
	 * @param {string|number|Object|null} popupId Popup identifier or popupData object
	 * @param {jQuery.Event|null} event jQuery event object
	 * @returns {string|number|null}
	 */
	function resolvePopupId(popupId, event) {
		if (popupId !== null && popupId !== undefined && popupId !== '') {
			if (typeof popupId === 'object' && popupId.popupId) {
				return popupId.popupId;
			}

			return popupId;
		}

		if (!event) {
			return null;
		}

		if (event.popupData && event.popupData.popupId) {
			return event.popupData.popupId;
		}

		if (event.originalEvent && event.originalEvent.popupData && event.originalEvent.popupData.popupId) {
			return event.originalEvent.popupData.popupId;
		}

		if (event.popupId) {
			return event.popupId;
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
		function prepareInResolvedContainer() {
			const resolvedPopup = resolvePopupElement(resolvePopupId(popupId, event), instance)
				|| findJetPopupsWithWizard({ requireVisible: true })[0]
				|| findJetPopupsWithWizard({ requireVisible: false })[0];

			prepareWizardFormsInContainer(resolvedPopup);
		}

		prepareInResolvedContainer();

		// JetPopup show events often fire before content/AJAX is in the DOM.
		setTimeout(prepareInResolvedContainer, 0);
	}

	/**
	 * Reset wizard forms inside a resolved popup, with DOM fallbacks when id is missing.
	 *
	 * @param {string|number|Object|null} popupId Popup identifier
	 * @param {Object|null} instance Optional Elementor popup instance
	 * @param {jQuery.Event|null} event Optional jQuery event object
	 */
	function handlePopupHide(popupId, instance, event) {
		const resolvedPopup = resolvePopupElement(resolvePopupId(popupId, event), instance);

		if (resolvedPopup) {
			resetFormsInContainer(resolvedPopup);
			return;
		}

		findJetPopupsWithWizard({ requireVisible: false }).forEach(function(popupEl) {
			resetFormsInContainer(popupEl);
		});
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
	 * Handle popup open events, with a dedicated path for the schedule wizard popup.
	 *
	 * @param {string|number|Object|null} popupId Popup identifier
	 * @param {Object|null} instance Optional Elementor popup instance
	 * @param {jQuery.Event|null} event Optional jQuery event object
	 */
	function handlePopupOpenEvent(popupId, instance, event) {
		const resolvedPopupId = resolvePopupId(popupId, event);

		if (isWizardPopupId(resolvedPopupId)) {
			handleWizardPopupOpen();
			return;
		}

		if (!resolvedPopupId) {
			handleWizardPopupOpen();
			return;
		}

		handlePopupShow(popupId, instance, event);
	}

	/**
	 * Observe the wizard popup for visibility changes as a DOM fallback.
	 */
	function setupWizardPopupVisibilityObserver() {
		if (!getWizardPopupId()) {
			return;
		}

		let wizardPopupWasVisible = false;

		function observePopup(popupEl) {
			if (!popupEl || popupEl.hasAttribute('data-wizard-popup-observed')) {
				return;
			}

			popupEl.setAttribute('data-wizard-popup-observed', 'true');

			const checkVisibility = function() {
				const isVisible = isPopupElementVisible(popupEl);
				if (isVisible && !wizardPopupWasVisible) {
					prepareWizardFormsInContainer(popupEl);
				}
				wizardPopupWasVisible = isVisible;
			};

			checkVisibility();

			const observer = new MutationObserver(checkVisibility);
			observer.observe(popupEl, {
				attributes: true,
				attributeFilter: ['aria-hidden', 'style', 'class']
			});
		}

		const existingPopup = getWizardPopupElement();
		if (existingPopup) {
			observePopup(existingPopup);
			return;
		}

		const bodyObserver = new MutationObserver(function() {
			const popupEl = getWizardPopupElement();
			if (popupEl) {
				observePopup(popupEl);
				bodyObserver.disconnect();
			}
		});

		bodyObserver.observe(document.body, {
			childList: true,
			subtree: true
		});
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

		// JetPopup — carries popupData.popupId when a popup is opened
		$(window).on('jet-popup-open-trigger', function(event) {
			handlePopupOpenEvent(null, null, event);
		});

		// JetPopup — reset before popup is shown (user always sees step 1)
		$(window).on('jet-popup/show-event/before-show', function(event, popupId) {
			handlePopupOpenEvent(popupId, null, event);
		});

		// JetPopup — fallback when popup content loads after show (AJAX popups)
		$(window).on('jet-popup/show-event/after-show', function(event, popupId) {
			handlePopupOpenEvent(popupId, null, event);
		});

		// JetPopup — fires after the popup is hidden (backup cleanup on close)
		$(window).on('jet-popup/hide-event/after-hide', function(event, popupId) {
			if (isWizardPopupId(resolvePopupId(popupId, event))) {
				resetFormsInContainer(getWizardPopupElement());
				return;
			}

			handlePopupHide(popupId, null, event);
		});

		// JetPopup — programmatic close trigger
		$(window).on('jet-popup-close-trigger', function(event) {
			if (isWizardPopupId(resolvePopupId(null, event))) {
				resetFormsInContainer(getWizardPopupElement());
				return;
			}

			handlePopupHide(null, null, event);
		});

		// Elementor Pro Popup — reset on show
		$(document).on('elementor/popup/show', function(event, id, instance) {
			handlePopupOpenEvent(id, instance, event);
		});

		// Elementor Pro Popup — backup cleanup on close
		$(document).on('elementor/popup/hide', function(event, id, instance) {
			resetFormsInContainer(resolvePopupElement(id, instance));
		});

		setupWizardPopupVisibilityObserver();
	}

	/**
	 * Open/close handlers for self-contained shortcode popup.
	 */
	function setupShortcodePopup() {
		document.querySelectorAll('.clinic-schedule-form-shortcode').forEach(function(wrapper) {
			const triggerBtn = wrapper.querySelector('.clinic-schedule-form__trigger-btn');
			const overlay = wrapper.querySelector('.clinic-schedule-form__popup-overlay');
			if (!triggerBtn || !overlay) {
				return;
			}

			// Elementor/widgets use transform — fixed positioning breaks inside them.
			if (overlay.parentElement !== document.body) {
				document.body.appendChild(overlay);
			}

			function lockScroll() {
				_shortcodePopupOpenCount += 1;
				if (_shortcodePopupOpenCount === 1) {
					document.body.style.overflow = 'hidden';
				}
			}

			function unlockScroll() {
				_shortcodePopupOpenCount = Math.max(0, _shortcodePopupOpenCount - 1);
				if (_shortcodePopupOpenCount === 0) {
					document.body.style.overflow = '';
				}
			}

			function openPopup() {
				overlay.removeAttribute('hidden');
				overlay.setAttribute('aria-hidden', 'false');
				lockScroll();
				prepareWizardFormsInContainer(overlay);

				requestAnimationFrame(function() {
					requestAnimationFrame(function() {
						overlay.classList.add('is-open');
					});
				});

				requestAnimationFrame(function() {
					const firstFocusable = overlay.querySelector(
						'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled])'
					);
					if (firstFocusable) {
						firstFocusable.focus();
					}
				});
			}

			function closePopup() {
				if (!overlay.classList.contains('is-open')) {
					return;
				}

				overlay.classList.remove('is-open');
				overlay.setAttribute('aria-hidden', 'true');

				let closed = false;
				function finishClose() {
					if (closed) {
						return;
					}

					closed = true;
					overlay.removeEventListener('transitionend', onTransitionEnd);
					overlay.setAttribute('hidden', '');
					unlockScroll();
					resetFormsInContainer(overlay);
					triggerBtn.focus();
				}

				function onTransitionEnd(event) {
					if (event.target === overlay && event.propertyName === 'opacity') {
						finishClose();
					}
				}

				overlay.addEventListener('transitionend', onTransitionEnd);
				window.setTimeout(finishClose, SHORTCODE_POPUP_FADE_MS);
			}

			triggerBtn.addEventListener('click', openPopup);

			const closeBtn = overlay.querySelector('.clinic-schedule-form__popup-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function(e) {
					e.stopPropagation();
					closePopup();
				});
			}
		});
	}

	/**
	 * Initialize on DOM ready
	 */
	function onDOMReady() {
		setupShortcodePopup();
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
