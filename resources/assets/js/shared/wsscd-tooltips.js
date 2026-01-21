/**
 * Tooltip functionality for Smart Cycle Discounts
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/wsscd-tooltips.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	var $tooltip = null;
	var $tooltipWrapper = null;
	var hideTimeout = null;
	var positioningRafId = null;

	/**
	 * Initialize tooltips when DOM is ready
	 */
	$( document ).ready( function() {
		setupTooltipContainer();
		initializeTooltips();
		
		// Reinitialize when new content is added to the DOM
		$( document ).on( 'wsscd:content-updated', function() {
			initializeTooltips();
		} );
		
		// Recalculate on window resize or admin menu toggle
		$( window ).on( 'resize', debounce( handleResize, 250 ) );
		$( document ).on( 'wp-menu-state-change', handleResize );
	} );
	
	/**
	 * Setup tooltip container within wp-content
	 */
	function setupTooltipContainer() {
		var $wpContent = $( '#wpcontent' );

		if ( ! $wpContent.length ) {
			return;
		}

		// Always try to find existing wrapper first (prevents duplicates if script runs multiple times)
		$tooltipWrapper = $( '#wsscd-tooltip-wrapper' );

		// Create wrapper only if it truly doesn't exist
		if ( ! $tooltipWrapper.length ) {
			$tooltipWrapper = $( '<div id="wsscd-tooltip-wrapper"></div>' );
			$wpContent.append( $tooltipWrapper );
		}

		// Remove any duplicate tooltip elements that might exist
		var $tooltips = $tooltipWrapper.find( '.wsscd-tooltip' );
		if ( $tooltips.length > 1 ) {
			// Keep first, remove others
			$tooltips.slice( 1 ).remove();
		}
	}
	
	/**
	 * Debounce helper
	 */
	function debounce( func, wait ) {
		var timeout;
		return function executedFunction() {
			var later = function() {
				clearTimeout( timeout );
				func();
			};
			clearTimeout( timeout );
			timeout = setTimeout( later, wait );
		};
	}
	
	/**
	 * Handle window resize
	 */
	function handleResize() {
		if ( $tooltip && $tooltip.hasClass( 'show' ) ) {
			hideTooltip();
		}
	}

	/**
	 * Set up tooltip behavior for all tooltip triggers
	 */
	function initializeTooltips() {
		// Remove any existing tooltip events to prevent duplicates
		$( document ).off( '.wsscd-tooltip' );
		
		// Target the actual help icon containers, not child elements
		$( document ).on( 'mouseenter.wsscd-tooltip', '.wsscd-field-helper', function( e ) {
			e.stopPropagation();
			var $trigger = $( this );
			var tooltipText = $trigger.attr( 'data-tooltip' );
			
			if ( tooltipText ) {
				showTooltip( $trigger, tooltipText );
			}
		} );
		
		$( document ).on( 'mouseleave.wsscd-tooltip', '.wsscd-field-helper', function( e ) {
			e.stopPropagation();
			hideTooltip();
		} );

		// Keyboard accessibility - ensure help icons are focusable (only if not already set by PHP)
		$( '.wsscd-field-helper:not([tabindex])' ).attr( 'tabindex', '0' );

		$( document ).on( 'focus.wsscd-tooltip', '.wsscd-field-helper', function( e ) {
			var $trigger = $( this );
			var tooltipText = $trigger.attr( 'data-tooltip' );
			
			if ( tooltipText ) {
				showTooltip( $trigger, tooltipText );
			}
		} );
		
		$( document ).on( 'blur.wsscd-tooltip', '.wsscd-field-helper', function( e ) {
			hideTooltip();
		} );
	}

	/**
	 * Get WordPress admin boundaries
	 */
	function getAdminBoundaries() {
		var $body = $( 'body' );
		var isRTL = $body.hasClass( 'rtl' );
		var isFolded = $body.hasClass( 'folded' );
		var isMobile = $( window ).width() <= 782;
		
		var adminMenuWidth = 0;
		if ( ! isMobile ) {
			adminMenuWidth = isFolded ? 36 : 160;
		}
		
		return {
			menuWidth: adminMenuWidth,
			isMobile: isMobile,
			isRTL: isRTL
		};
	}
	
	/**
	 * Show tooltip near the trigger element
	 * Uses absolute positioning within wp-content container
	 *
	 * @param {jQuery} $trigger The element that triggered the tooltip
	 * @param {string} text The tooltip text to display
	 */
	function showTooltip( $trigger, text ) {
		// Clear any pending hide timeout
		if ( hideTimeout ) {
			clearTimeout( hideTimeout );
			hideTimeout = null;
		}

		// Ensure container exists
		if ( ! $tooltipWrapper || ! $tooltipWrapper.length ) {
			setupTooltipContainer();
			if ( ! $tooltipWrapper ) {
				return;
			}
		}

		// Create tooltip if it doesn't exist (check DOM first to prevent duplicates)
		if ( ! $tooltip || ! $tooltip.length ) {
			// Check if a tooltip already exists in the DOM
			$tooltip = $tooltipWrapper.find( '.wsscd-tooltip' ).first();

			// If still no tooltip found, create one
			if ( ! $tooltip.length ) {
				$tooltip = $( '<div class="wsscd-tooltip" role="tooltip" aria-hidden="true"></div>' );
				$tooltipWrapper.append( $tooltip );
			}
		}

		// Hide any other tooltips that might be visible (safety check)
		$tooltipWrapper.find( '.wsscd-tooltip' ).not( $tooltip ).removeClass( 'show' );

		// Set content
		$tooltip.text( text );
		
		// Reset classes
		$tooltip.removeClass( 'tooltip-bottom tooltip-right' );
		
		// Get positions
		var $wpContent = $( '#wpcontent' );
		var contentOffset = $wpContent.offset();
		var contentWidth = $wpContent.outerWidth();
		var contentHeight = $wpContent.height();
		
		var triggerOffset = $trigger.offset();
		var triggerWidth = $trigger.outerWidth();
		var triggerHeight = $trigger.outerHeight();
		
		// Calculate relative positions
		var relativeTriggerLeft = triggerOffset.left - contentOffset.left;
		var relativeTriggerTop = triggerOffset.top - contentOffset.top;
		
		// Initial position (centered above trigger)
		var tooltipLeft = relativeTriggerLeft + ( triggerWidth / 2 );
		var tooltipTop = relativeTriggerTop - 12; // 12px gap
		
		// Apply initial position
		$tooltip.css( {
			'position': 'absolute',
			'left': tooltipLeft + 'px',
			'top': tooltipTop + 'px',
			'transform': 'translateX(-50%) translateY(-100%)'
		} );
		
		// Force reflow before adding show class for smooth transition
		if ( $tooltip[0] ) {
			$tooltip[0].offsetHeight;
		}

		// Make visible
		$tooltip.addClass( 'show' );
		
		// Cancel any pending positioning from previous tooltip
		if ( positioningRafId ) {
			cancelAnimationFrame( positioningRafId );
			positioningRafId = null;
		}

		// Check positioning after a frame
		positioningRafId = requestAnimationFrame( function() {
			positioningRafId = null;

			// Guard: tooltip may have been removed during navigation
			if ( ! $tooltip || ! $tooltip.length || ! $tooltip[0] ) {
				return;
			}

			var tooltipWidth = $tooltip.outerWidth();
			var tooltipHeight = $tooltip.outerHeight();
			
			var boundaries = getAdminBoundaries();
			var padding = 10;
			
			// Calculate available space
			var spaceAbove = relativeTriggerTop;
			var spaceBelow = contentHeight - ( relativeTriggerTop + triggerHeight );
			var spaceLeft = relativeTriggerLeft;
			var spaceRight = contentWidth - ( relativeTriggerLeft + triggerWidth );
			
			// Determine best position
			var position = 'top'; // default
			var finalLeft = tooltipLeft;
			var finalTop = tooltipTop;
			var transform = 'translateX(-50%) translateY(-100%)';
			
			// Check vertical space
			if ( spaceAbove < tooltipHeight + padding && spaceBelow > tooltipHeight + padding ) {
				// Position below
				position = 'bottom';
				finalTop = relativeTriggerTop + triggerHeight + 12;
				transform = 'translateX(-50%) translateY(0)';
				$tooltip.addClass( 'tooltip-bottom' );
			}
			
			// Check horizontal boundaries
			var tooltipRect = {
				left: finalLeft - ( tooltipWidth / 2 ),
				right: finalLeft + ( tooltipWidth / 2 )
			};
			
			// Track arrow offset for when tooltip position is adjusted
			var arrowOffset = 0;
			
			// Adjust if tooltip extends beyond content boundaries
			if ( tooltipRect.left < padding ) {
				// Calculate how much we're shifting the tooltip
				var shift = padding - tooltipRect.left;
				finalLeft = padding + ( tooltipWidth / 2 );
				// Arrow needs to move opposite direction to stay on icon
				arrowOffset = -shift;
			} else if ( tooltipRect.right > contentWidth - padding ) {
				// Calculate how much we're shifting the tooltip
				var shift = tooltipRect.right - ( contentWidth - padding );
				finalLeft = contentWidth - padding - ( tooltipWidth / 2 );
				// Arrow needs to move opposite direction to stay on icon
				arrowOffset = shift;
			}
			
			// For very constrained spaces, position to the side
			if ( relativeTriggerLeft < 100 && spaceRight > tooltipWidth + padding ) {
				// Position to the right
				position = 'right';
				finalLeft = relativeTriggerLeft + triggerWidth + 12;
				finalTop = relativeTriggerTop + ( triggerHeight / 2 );
				transform = 'translateY(-50%)';
				$tooltip.removeClass( 'tooltip-bottom' ).addClass( 'tooltip-right' );
			}
			
			// Apply final position
			$tooltip.css( {
				'left': finalLeft + 'px',
				'top': finalTop + 'px',
				'transform': transform,
				'--arrow-offset': arrowOffset + 'px'
			} );
		} );
	}

	/**
	 * Hide the tooltip
	 */
	function hideTooltip() {
		// Cancel any pending positioning animation frame
		if ( positioningRafId ) {
			cancelAnimationFrame( positioningRafId );
			positioningRafId = null;
		}

		if ( hideTimeout ) {
			clearTimeout( hideTimeout );
		}

		hideTimeout = setTimeout( function() {
			if ( $tooltip ) {
				$tooltip.removeClass( 'show tooltip-bottom tooltip-right' );
			}
		}, 100 ); // Small delay prevents flashing when moving between elements
	}

	// Clean up on page unload
	$( window ).on( 'beforeunload', function() {
		// Cancel pending animation frame
		if ( positioningRafId ) {
			cancelAnimationFrame( positioningRafId );
			positioningRafId = null;
		}

		if ( $tooltip ) {
			$tooltip.remove();
			$tooltip = null;
		}
	} );

} )( jQuery );