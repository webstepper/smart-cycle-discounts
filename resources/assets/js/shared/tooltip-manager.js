/**
 * Tooltip Manager Utility
 *
 * Centralized tooltip management for consistent tooltip display across the plugin.
 * Eliminates duplicate tooltip code across orchestrators.
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};

	/**
	 * Tooltip Manager
	 * Manages tooltip display with positioning, animations, and cleanup
	 */
	SCD.TooltipManager = {

		/**
		 * Current active tooltip
		 * @type {jQuery|null}
		 */
		currentTooltip: null,

		/**
		 * Initialize tooltips for a container
		 * Sets up event handlers for tooltip triggers
		 *
		 * @param {jQuery} $container - Container element to attach tooltips to
		 * @param {string} selector - Selector for tooltip trigger elements (default: '.scd-tooltip-trigger')
		 * @returns {void}
		 */
		initialize: function( $container, selector ) {
			selector = selector || '.scd-tooltip-trigger';
			var self = this;

			if ( !$container || !$container.length ) {
				return;
			}

			// Remove any existing handlers to prevent duplicates
			$container.off( 'mouseenter.tooltip mouseleave.tooltip', selector );

			// Add tooltip handlers
			$container.on( 'mouseenter.tooltip', selector, function() {
				var $this = $( this );
				var tooltipText = $this.data( 'tooltip' ) || $this.attr( 'title' );
				if ( tooltipText ) {
					// Remove title attribute to prevent browser default tooltip
					$this.removeAttr( 'title' );
					self.show( $this, tooltipText );
				}
			} );

			$container.on( 'mouseleave.tooltip', selector, function() {
				self.hide();
			} );
		},

		/**
		 * Show tooltip
		 *
		 * @param {jQuery} $element - Element to show tooltip for
		 * @param {string} text - Tooltip text
		 * @returns {void}
		 */
		show: function( $element, text ) {
			// Hide any existing tooltip first
			this.hide();

			// Create tooltip element
			var $tooltip = $( '<div class="scd-tooltip"></div>' ).html( text );
			$( 'body' ).append( $tooltip );

			// Calculate position
			var offset = $element.offset();
			var tooltipWidth = $tooltip.outerWidth();
			var tooltipHeight = $tooltip.outerHeight();
			var windowWidth = $( window ).width();

			// Center tooltip above element
			var left = offset.left + ( $element.outerWidth() / 2 ) - ( tooltipWidth / 2 );
			var top = offset.top - tooltipHeight - 10;

			// Keep tooltip within window bounds
			if ( left < 10 ) {
				left = 10;
			} else if ( left + tooltipWidth > windowWidth - 10 ) {
				left = windowWidth - tooltipWidth - 10;
			}

			// Position tooltip
			$tooltip.css( {
				'position': 'absolute',
				'top': top + 'px',
				'left': left + 'px',
				'z-index': 99999
			} );

			// Show with animation
			setTimeout( function() {
				$tooltip.addClass( 'show' );
			}, 10 );

			// Store reference
			this.currentTooltip = $tooltip;
		},

		/**
		 * Hide tooltip
		 * Removes current tooltip with animation
		 *
		 * @returns {void}
		 */
		hide: function() {
			if ( this.currentTooltip ) {
				this.currentTooltip.removeClass( 'show' );
				var $tooltip = this.currentTooltip;
				setTimeout( function() {
					$tooltip.remove();
				}, 200 );
				this.currentTooltip = null;
			}

			// Remove any orphaned tooltips
			$( '.scd-tooltip' ).remove();
		},

		/**
		 * Destroy tooltips for a container
		 * Removes event handlers and hides any active tooltip
		 *
		 * @param {jQuery} $container - Container to remove tooltips from
		 * @param {string} selector - Selector for tooltip triggers (default: '.scd-tooltip-trigger')
		 * @returns {void}
		 */
		destroy: function( $container, selector ) {
			selector = selector || '.scd-tooltip-trigger';

			if ( $container && $container.length ) {
				$container.off( 'mouseenter.tooltip mouseleave.tooltip', selector );
			}

			this.hide();
		}
	};

} )( jQuery );
