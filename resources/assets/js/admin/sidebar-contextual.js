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
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
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
		 * Increased to 500ms to reduce unnecessary requests
		 */
		hoverDelay: 500,

		/**
		 * Mouse move throttle flag
		 */
		mouseMoveThrottle: false,

		/**
		 * Initialize sidebar
		 */
		init: function() {
			debug('[SCD Sidebar] Initializing smart contextual help...');

			var $sidebar = $('.scd-sidebar-contextual');

			if ( ! $sidebar.length ) {
				debug('[SCD Sidebar] Sidebar element not found');
				return;
			}

			this.currentStep = $sidebar.data('step') || '';

			if ( ! this.currentStep ) {
				debug('[SCD Sidebar] No step identifier found');
				return;
			}

			debug('[SCD Sidebar] Step:', this.currentStep);

			this.bindEvents();

			debug('[SCD Sidebar] Initialization complete');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Focus-aware help (highest priority)
			$(document).on('focus', '[data-help-topic]', function() {
				var topic = $(this).data('help-topic');
				var label = $(this).closest('.scd-form-field').find('label').first().text() ||
							$(this).attr('placeholder') ||
							'Current Field';

				debug('[SCD Sidebar] Focus:', topic);
				self.showHelpTopic(topic, label);
			});

			// Hover for additional context (medium priority) - with debounce
		// Listen on document to catch all mousemove events including children
		$(document).on('mousemove.scd-sidebar', function(e) {
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
					clearTimeout(self.hoverTimer);
					self.hoverTimer = null;
				}
				return;
			}

			// Don't show on hover if element is focused
			if ( $target.is(':focus') ) {
				return;
			}

			var topic = $target.data('help-topic');

			// If same topic, do nothing
			if ( topic === self.currentTopic ) {
				return;
			}

			// Clear existing timer
			if ( self.hoverTimer ) {
				clearTimeout(self.hoverTimer);
			}

			// Set new debounce timer
			self.hoverTimer = setTimeout(function() {
				// Only show if no field currently focused
				if ( $('[data-help-topic]:focus').length ) {
					return;
				}

				var label = '';

				// Check if this is a card element
				if ( $target.hasClass('scd-card') || $target.hasClass('scd-wizard-card') ) {
					// For cards, get the card title
					label = $target.find('.scd-card__title').first().text().trim() || 'Card';
				} else {
					// For form fields, get the label from the form field wrapper
					label = $target.closest('.scd-form-field').find('label').first().text() ||
							$target.attr('placeholder') ||
							'Field';
				}

				debug('[SCD Sidebar] Hover (debounced):', topic);
				self.showHelpTopic(topic, label);
			}, self.hoverDelay);
		});
			// Dynamic method display (for discount types)
			$(document).on('change', '[name="discount_type"]', function() {
				var discountType = $(this).val();
				debug('[SCD Sidebar] Discount type changed:', discountType);
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
				debug('[SCD Sidebar] Using cached help for:', topicId);
				this.renderHelpContent(this.helpCache[topicId]);
				return;
			}

			// PERFORMANCE: Check pre-rendered topics before AJAX
			var $prerendered = $('#scd-prerendered-topics .scd-prerendered-topic[data-topic-id="' + topicId + '"]');
			if ( $prerendered.length ) {
				debug('[SCD Sidebar] Using pre-rendered help for:', topicId);
				var html = $prerendered.html();
				// Cache for future use
				this.helpCache[topicId] = html;
				this.renderHelpContent(html);
				return;
			}

			// Fetch from server
			debug('[SCD Sidebar] Fetching help for:', topicId);
			this.showLoading();

			$.ajax({
				url: window.scdWizardData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'scd_ajax',
					scdAction: 'get_help_topic',
					nonce: window.scdWizardData.nonce,
					topic_id: topicId
				},
				success: function(response) {
					debug('[SCD Sidebar] Help response:', response);

					if ( response.success && response.data && response.data.html ) {
						// Cache the result
						self.helpCache[topicId] = response.data.html;
						self.renderHelpContent(response.data.html);
					} else {
						debug('[SCD Sidebar] Invalid help response:', response);
						self.showError('Help topic not found');
					}
				},
				error: function(xhr, status, error) {
					debug('[SCD Sidebar] AJAX error:', {status: status, error: error, response: xhr.responseText});
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
			var $helpArea = $('#scd-sidebar-help-content');
			var $scrollContainer = $('.scd-sidebar-help-area');

			// Trigger transition by adding class (CSS handles the animation)
			$helpArea.addClass('scd-transitioning');

			// Wait for fade-out transition to complete (250ms)
			setTimeout(function() {
				// Update content while invisible
				$helpArea.html(html);

				// Scroll to top smoothly (using CSS scroll-behavior: smooth)
				if ( $scrollContainer.length && $scrollContainer.scrollTop() > 0 ) {
					$scrollContainer.scrollTop(0);
				}

				// Force reflow to ensure CSS transition triggers
				$helpArea[0].offsetHeight;

				// Remove transitioning class to trigger fade-in
				$helpArea.removeClass('scd-transitioning scd-loading');
			}, 250);
		},

		/**
		 * Update breadcrumb with current context
		 *
		 * @param {string} topicName Readable topic name
		 */
		updateBreadcrumb: function(topicName) {
			var $breadcrumbTopic = $('#scd-breadcrumb-topic');

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
		 * Show dynamic method based on selection
		 *
		 * @param {string} methodId Method identifier
		 */
		showDynamicMethod: function(methodId) {
			$('.scd-dynamic-method').hide();
			$('.scd-dynamic-method[data-method="' + methodId + '"]').fadeIn(200);
		},

		/**
		 * Show loading state
		 */
		showLoading: function() {
			$('#scd-sidebar-help-content').addClass('scd-loading');
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function(message) {
			var errorHtml = '<div class="scd-sidebar-error">' +
				'<svg class="scd-icon" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">' +
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
		debug('[SCD Sidebar] Document ready - initializing...');

		// Small delay to ensure DOM is fully loaded
		setTimeout(function() {
			SidebarContextual.init();
		}, 100);
	});

})(jQuery);

