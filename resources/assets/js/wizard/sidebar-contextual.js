/**
 * Smart Contextual Help Sidebar
 *
 * Focus-aware sidebar that automatically displays relevant help based on:
 * - Field focus (highest priority)
 * - Field hover with debounce (medium priority)
 * - Current wizard step (fallback)
 *
 * Features:
 * - Fixed position, always visible
 * - Context breadcrumb showing current help location
 * - Smooth transitions between help topics
 * - Three-tier caching (JS cache → Pre-rendered → AJAX)
 * - Dynamic method display for discount types
 * - Mobile collapse/expand toggle
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/sidebar-contextual.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Debug mode - set to false for production
	 */
	var DEBUG = false;

	/**
	 * Debug logger
	 */
	function debug() {
		if ( DEBUG && window.console && window.console.log ) {
			console.log.apply( console, arguments );
		}
	}

	/**
	 * Smart Contextual Sidebar Controller
	 */
	var SidebarContextual = {

		/**
		 * Current step identifier
		 */
		currentStep: '',

		/**
		 * Currently displayed topic
		 */
		currentTopic: '',

		/**
		 * Help content cache
		 */
		helpCache: {},

		/**
		 * Hover debounce timer
		 */
		hoverTimer: null,

		/**
		 * Hover debounce delay (milliseconds)
		 * Set to 200ms for smooth, responsive feel
		 */
		hoverDelay: 200,

		/**
		 * Mouse move throttle flag
		 */
		mouseMoveThrottle: false,

		/**
		 * Sidebar collapsed state (for mobile)
		 */
		isCollapsed: false,

		/**
		 * Storage key for collapsed state
		 */
		storageKey: 'wsscd_sidebar_collapsed',

		/**
		 * Currently focused/active element (prevents hover override)
		 */
		focusedElement: null,

		/**
		 * Initialize sidebar
		 */
		init: function() {
			debug('[WSSCD Sidebar] Initializing smart contextual help...');

			var $sidebar = $('.wsscd-sidebar-contextual');

			if ( ! $sidebar.length ) {
				debug('[WSSCD Sidebar] Sidebar element not found');
				return;
			}

			this.currentStep = $sidebar.data('step') || '';

			if ( ! this.currentStep ) {
				debug('[WSSCD Sidebar] No step identifier found');
				return;
			}

			// Review step uses static sidebar - no contextual help
			if ( 'review' === this.currentStep ) {
				debug('[WSSCD Sidebar] Review step - using static sidebar');
				this.initMobileToggle();
				return;
			}

			debug('[WSSCD Sidebar] Step:', this.currentStep);

			this.initMobileToggle();
			this.bindEvents();

			debug('[WSSCD Sidebar] Initialization complete');
		},

		/**
		 * Initialize mobile collapse/expand toggle
		 */
		initMobileToggle: function() {
			var self = this;
			var $stepSidebar = $('.wsscd-step-sidebar');

			if ( ! $stepSidebar.length ) {
				return;
			}

			// Create toggle button if it doesn't exist
			if ( ! $stepSidebar.find('.wsscd-sidebar-toggle').length ) {
				var $toggleBtn = $('<button type="button" class="wsscd-sidebar-toggle" aria-label="' + this.getToggleLabel( false ) + '" aria-expanded="true">' +
					'<span class="wsscd-sidebar-toggle-icon"></span>' +
					'</button>');

				$stepSidebar.prepend( $toggleBtn );
			}

			// Restore collapsed state from localStorage
			this.isCollapsed = this.getStoredState();
			if ( this.isCollapsed ) {
				$stepSidebar.addClass( 'wsscd-sidebar-collapsed' );
				this.updateToggleButton( true );
			}

			// Bind toggle click
			$stepSidebar.on( 'click', '.wsscd-sidebar-toggle', function( e ) {
				e.preventDefault();
				self.toggleSidebar();
			});

			debug('[WSSCD Sidebar] Mobile toggle initialized');
		},

		/**
		 * Toggle sidebar collapsed state
		 */
		toggleSidebar: function() {
			var $stepSidebar = $('.wsscd-step-sidebar');

			this.isCollapsed = ! this.isCollapsed;

			if ( this.isCollapsed ) {
				$stepSidebar.addClass( 'wsscd-sidebar-collapsed' );
			} else {
				$stepSidebar.removeClass( 'wsscd-sidebar-collapsed' );
			}

			this.updateToggleButton( this.isCollapsed );
			this.storeState( this.isCollapsed );

			debug('[WSSCD Sidebar] Toggled:', this.isCollapsed ? 'collapsed' : 'expanded');
		},

		/**
		 * Update toggle button aria attributes
		 *
		 * @param {boolean} collapsed Whether sidebar is collapsed
		 */
		updateToggleButton: function( collapsed ) {
			var $btn = $('.wsscd-sidebar-toggle');
			$btn.attr( 'aria-expanded', ! collapsed );
			$btn.attr( 'aria-label', this.getToggleLabel( collapsed ) );
		},

		/**
		 * Get toggle button label based on state
		 *
		 * @param {boolean} collapsed Whether sidebar is collapsed
		 * @return {string} Button label
		 */
		getToggleLabel: function( collapsed ) {
			return collapsed ? 'Expand help sidebar' : 'Collapse help sidebar';
		},

		/**
		 * Store collapsed state in localStorage
		 *
		 * @param {boolean} collapsed Whether sidebar is collapsed
		 */
		storeState: function( collapsed ) {
			try {
				localStorage.setItem( this.storageKey, collapsed ? '1' : '0' );
			} catch ( e ) {
				debug('[WSSCD Sidebar] localStorage not available');
			}
		},

		/**
		 * Get stored collapsed state from localStorage
		 *
		 * @return {boolean} Whether sidebar should be collapsed
		 */
		getStoredState: function() {
			try {
				return localStorage.getItem( this.storageKey ) === '1';
			} catch ( e ) {
				return false;
			}
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Focus-aware help (highest priority)
			// Listen for focusin on any focusable element, then find its help topic
			$(document).on('focusin.wsscd-sidebar', function( e ) {
				var $focused = $(e.target);

				// Find the closest element with data-help-topic
				// Start from the focused element itself
				var $helpElement = $focused.closest('[data-help-topic]');

				if ( ! $helpElement.length ) {
					return;
				}

				var topic = $helpElement.data('help-topic');
				var label = self.extractFieldLabel( $helpElement );

				// Track focused element to prevent hover override
				self.focusedElement = $focused[0];

				// Clear any pending hover timer
				if ( self.hoverTimer ) {
					clearTimeout( self.hoverTimer );
					self.hoverTimer = null;
				}

				debug('[WSSCD Sidebar] Focus:', topic, 'from element:', $focused.prop('tagName'));
				self.showHelpTopic( topic, label );
			});

			// Clear focused element tracking on blur
			$(document).on('focusout.wsscd-sidebar', function() {
				self.focusedElement = null;
			});

			// Click handler for non-focusable elements (cards, divs)
			// This handles elements that can't receive focus naturally
			$(document).on('click.wsscd-sidebar', '[data-help-topic]', function( e ) {
				var $delegateTarget = $(this);
				var $clickTarget = $(e.target);

				// Find the closest help-topic element from the actual click target
				// This ensures we get the most specific help topic (child over parent)
				var $element = $clickTarget.closest('[data-help-topic]');

				if ( ! $element.length ) {
					return;
				}

				// Skip if the element itself is focusable (input, select, etc.)
				// Those are handled by focusin
				if ( self.isFocusableElement( $element ) ) {
					return;
				}

				// Skip if clicking on or inside a focusable element
				// Let focusin handle those cases
				if ( $clickTarget.closest('input, select, textarea, button, a').length ) {
					return;
				}

				var topic = $element.data('help-topic');
				var label = self.extractFieldLabel( $element );

				// Track as active element
				self.focusedElement = $element[0];

				// Clear any pending hover timer
				if ( self.hoverTimer ) {
					clearTimeout( self.hoverTimer );
					self.hoverTimer = null;
				}

				debug('[WSSCD Sidebar] Click (non-focusable):', topic);
				self.showHelpTopic( topic, label );
			});

			// Hover for additional context (medium priority) - with debounce
			// Listen on document to catch all mousemove events including children
			$(document).on('mousemove.wsscd-sidebar', function( e ) {
				// Throttle mousemove checks
				if ( self.mouseMoveThrottle ) {
					return;
				}

				self.mouseMoveThrottle = true;
				setTimeout(function() {
					self.mouseMoveThrottle = false;
				}, 100);

				// Find the closest element with data-help-topic
				var $target = $(e.target).closest('[data-help-topic]');

				if ( ! $target.length ) {
					// No help topic element found, clear timer
					if ( self.hoverTimer ) {
						clearTimeout( self.hoverTimer );
						self.hoverTimer = null;
					}
					return;
				}

				// Don't show on hover if any element is focused/active
				if ( self.focusedElement ) {
					return;
				}

				// Also check native focus state
				if ( $target.is(':focus') || $target.find(':focus').length ) {
					return;
				}

				var topic = $target.data('help-topic');

				// If same topic, do nothing
				if ( topic === self.currentTopic ) {
					return;
				}

				// Clear existing timer
				if ( self.hoverTimer ) {
					clearTimeout( self.hoverTimer );
				}

				// Set new debounce timer
				self.hoverTimer = setTimeout(function() {
					// Double-check no element is focused before showing hover help
					if ( self.focusedElement || $('[data-help-topic]:focus').length ) {
						return;
					}

					var label = self.extractFieldLabel( $target );

					debug('[WSSCD Sidebar] Hover (debounced):', topic);
					self.showHelpTopic( topic, label );
				}, self.hoverDelay);
			});

			// Clear focused element when clicking outside help topic elements
			$(document).on('click.wsscd-sidebar-clear', function( e ) {
				if ( ! $(e.target).closest('[data-help-topic]').length ) {
					self.focusedElement = null;
				}
			});

			// Dynamic method display (for discount types)
			$(document).on('change', '[name="discount_type"]', function() {
				var discountType = $(this).val();
				debug('[WSSCD Sidebar] Discount type changed:', discountType);
				self.showDynamicMethod(discountType);
			});

			// Show initial discount method if discount type is selected
			var initialDiscountType = $('[name="discount_type"]:checked').val();
			if ( initialDiscountType ) {
				setTimeout(function() {
					self.showDynamicMethod(initialDiscountType);
				}, 100);
			}
		},


		/**
		 * Show help topic
		 *
		 * @param {string} topicId   Topic identifier
		 * @param {string} topicName Readable topic name for breadcrumb
		 */
		showHelpTopic: function(topicId, topicName) {
			var self = this;

			if ( ! topicId || topicId === this.currentTopic ) {
				return;
			}

			this.currentTopic = topicId;

			// Update breadcrumb
			this.updateBreadcrumb(topicName || this.formatTopicName(topicId));

			// Check cache first
			if ( this.helpCache[topicId] ) {
				debug('[WSSCD Sidebar] Using cached help for:', topicId);
				this.renderHelpContent(this.helpCache[topicId]);
				return;
			}

			// PERFORMANCE: Check pre-rendered topics before AJAX
			var $prerendered = $('#wsscd-prerendered-topics .wsscd-prerendered-topic[data-topic-id="' + topicId + '"]');
			if ( $prerendered.length ) {
				debug('[WSSCD Sidebar] Using pre-rendered help for:', topicId);
				var html = $prerendered.html();
				// Cache for future use
				this.helpCache[topicId] = html;
				this.renderHelpContent(html);
				return;
			}

			// Fetch from server
			debug('[WSSCD Sidebar] Fetching help for:', topicId);
			this.showLoading();

			$.ajax({
				url: window.wsscdWizardData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wsscd_ajax',
					wsscdAction: 'get_help_topic',
					nonce: window.wsscdWizardData.nonce,
					topic_id: topicId
				},
				success: function(response) {
					debug('[WSSCD Sidebar] Help response:', response);

					if ( response.success && response.data && response.data.html ) {
						// Cache the result
						self.helpCache[topicId] = response.data.html;
						self.renderHelpContent(response.data.html);
					} else {
						debug('[WSSCD Sidebar] Invalid help response:', response);
						self.showError('Help topic not found');
					}
				},
				error: function(xhr, status, error) {
					debug('[WSSCD Sidebar] AJAX error:', {status: status, error: error, response: xhr.responseText});
					self.showError('Failed to load help content');
				}
			});
		},

		/**
		 * Render help content with smooth transition
		 *
		 * @param {string} html Help HTML content
		 */
		renderHelpContent: function(html) {
			var $helpArea = $('#wsscd-sidebar-help-content');
			var $scrollContainer = $('.wsscd-sidebar-help-area');

			// Guard: element may not exist during navigation/skeleton
			if ( ! $helpArea.length ) {
				return;
			}

			// Trigger transition by adding class (CSS handles the animation)
			$helpArea.addClass('wsscd-transitioning');

			// Wait for fade-out transition to complete (150ms - matches CSS)
			setTimeout(function() {
				// Re-query in case DOM changed during timeout
				var $currentHelpArea = $('#wsscd-sidebar-help-content');
				var $currentScrollContainer = $('.wsscd-sidebar-help-area');

				// Guard: element may have been removed during navigation
				if ( ! $currentHelpArea.length ) {
					return;
				}

				// Update content while invisible
				$currentHelpArea.html(html);

				// Scroll to top smoothly (using CSS scroll-behavior: smooth)
				if ( $currentScrollContainer.length && $currentScrollContainer.scrollTop() > 0 ) {
					$currentScrollContainer.scrollTop(0);
				}

				// Force reflow to ensure CSS transition triggers
				if ( $currentHelpArea[0] ) {
					$currentHelpArea[0].offsetHeight;
				}

				// Remove transitioning class to trigger fade-in
				$currentHelpArea.removeClass('wsscd-transitioning wsscd-loading');
			}, 150);
		},

		/**
		 * Update breadcrumb with current context
		 *
		 * @param {string} topicName Readable topic name
		 */
		updateBreadcrumb: function(topicName) {
			var $breadcrumbTopic = $('#wsscd-breadcrumb-topic');

			if ( $breadcrumbTopic.length ) {
				// Add CSS class for transition
				$breadcrumbTopic.css({
					'opacity': '0',
					'transform': 'translateX(-4px)'
				});

				setTimeout(function() {
					$breadcrumbTopic.text(topicName);
					$breadcrumbTopic.css({
						'opacity': '1',
						'transform': 'translateX(0)'
					});
				}, 150);
			}
		},

		/**
		 * Format topic ID to readable name
		 *
		 * @param {string} topicId Topic identifier
		 * @return {string} Formatted name
		 */
		formatTopicName: function(topicId) {
			// Convert kebab-case to Title Case
			return topicId
				.split('-')
				.map(function(word) {
					return word.charAt(0).toUpperCase() + word.slice(1);
				})
				.join(' ');
		},

		/**
		 * Extract clean field label from element
		 *
		 * Finds the label text for a field element and cleans it up
		 * by removing required indicators (*), trimming whitespace, etc.
		 *
		 * @param {jQuery} $element The field element
		 * @return {string} Clean label text
		 */
		extractFieldLabel: function($element) {
			var label = '';

			// Check if this is a card element
			if ( $element.hasClass('wsscd-card') || $element.hasClass('wsscd-wizard-card') ) {
				// For cards, get the card title
				label = $element.find('.wsscd-card__title').first().text();
			} else {
				// Try multiple label sources in order of preference
				// 1. Look for associated label via form-field wrapper
				var $formField = $element.closest('.wsscd-form-field');
				if ( $formField.length ) {
					label = $formField.find('label').first().text();
				}

				// 2. Try field-group wrapper (for modern field layout)
				if ( ! label ) {
					var $fieldGroup = $element.closest('.wsscd-field-group');
					if ( $fieldGroup.length ) {
						label = $fieldGroup.find('.wsscd-field-label').first().text();
					}
				}

				// 3. Try form-section header
				if ( ! label ) {
					var $formSection = $element.closest('.wsscd-form-section');
					if ( $formSection.length ) {
						label = $formSection.find('.wsscd-form-section-title').first().text();
					}
				}

				// 4. Fallback to placeholder
				if ( ! label ) {
					label = $element.attr('placeholder') || '';
				}
			}

			// Clean up the label
			return this.cleanLabel(label);
		},

		/**
		 * Clean up label text
		 *
		 * Removes required indicators, extra whitespace, etc.
		 *
		 * @param {string} label Raw label text
		 * @return {string} Cleaned label
		 */
		cleanLabel: function(label) {
			if ( ! label ) {
				return 'Field';
			}

			return label
				// Remove asterisks (required indicators)
				.replace(/\*/g, '')
				// Remove "(Required)" text
				.replace(/\(required\)/gi, '')
				// Remove "(Optional)" text
				.replace(/\(optional\)/gi, '')
				// Normalize whitespace
				.replace(/\s+/g, ' ')
				// Trim
				.trim() || 'Field';
		},

		/**
		 * Check if element is natively focusable
		 *
		 * @param {jQuery} $element Element to check
		 * @return {boolean} True if element can receive focus naturally
		 */
		isFocusableElement: function( $element ) {
			if ( ! $element || ! $element.length ) {
				return false;
			}

			var tagName = $element.prop('tagName');
			if ( ! tagName ) {
				return false;
			}

			tagName = tagName.toLowerCase();

			// These elements are natively focusable
			var focusableTags = [ 'input', 'select', 'textarea', 'button', 'a' ];
			if ( focusableTags.indexOf( tagName ) !== -1 ) {
				// Anchors need href to be focusable
				if ( 'a' === tagName && ! $element.attr('href') ) {
					return false;
				}
				return true;
			}

			// Elements with tabindex are focusable
			var tabindex = $element.attr('tabindex');
			if ( tabindex !== undefined && parseInt( tabindex, 10 ) >= 0 ) {
				return true;
			}

			return false;
		},

		/**
		 * Show dynamic method based on selection
		 *
		 * @param {string} methodId Method identifier
		 */
		showDynamicMethod: function(methodId) {
			$('.wsscd-dynamic-method').hide();
			$('.wsscd-dynamic-method[data-method="' + methodId + '"]').fadeIn(200);
		},

		/**
		 * Show loading state
		 */
		showLoading: function() {
			$('#wsscd-sidebar-help-content').addClass('wsscd-loading');
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function(message) {
			var errorHtml = '<div class="wsscd-sidebar-error">' +
				'<svg class="wsscd-icon" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">' +
				'<circle cx="12" cy="12" r="10"></circle>' +
				'<line x1="12" y1="8" x2="12" y2="12"></line>' +
				'<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
				'</svg>' +
				'<p>' + message + '</p>' +
				'</div>';

			this.renderHelpContent(errorHtml);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		debug('[WSSCD Sidebar] Document ready - initializing...');

		// Small delay to ensure DOM is fully loaded
		setTimeout(function() {
			SidebarContextual.init();
		}, 100);
	});

})(jQuery);

