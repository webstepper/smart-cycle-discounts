/**
 * Unified Loader Utility
 *
 * Provides consistent JavaScript functions for showing/hiding loaders
 * across the plugin using WordPress spinner with custom overlays.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize WSSCD namespace
	 */
	window.WSSCD = window.WSSCD || {};

	/**
	 * Loader Utility
	 *
	 * Provides consistent methods for showing/hiding loaders
	 * with proper accessibility and animation support.
	 */
	window.WSSCD.LoaderUtil = {

		/**
		 * Show loader
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 * @param {Object} options - Configuration options
		 * @param {boolean} options.fade - Use fade animation (default: true)
		 * @param {string} options.text - Update loader text
		 * @param {string} options.ariaLive - ARIA live region politeness ('polite' or 'assertive')
		 */
		show: function( loader, options ) {
			var $loader = this._getElement( loader );
			if ( ! $loader.length ) {
				return;
			}

			var settings = $.extend( {
				fade: true,
				text: null,
				ariaLive: 'polite'
			}, options );

			// Update text if provided
			if ( settings.text ) {
				$loader.find( '.wsscd-loader-text' ).text( settings.text );
			}

			// Set ARIA attributes for accessibility
			$loader.attr( 'aria-busy', 'true' );
			$loader.attr( 'aria-live', settings.ariaLive );
			$loader.attr( 'role', 'status' );

			// Show loader with optional fade animation
			if ( settings.fade ) {
				$loader.addClass( 'wsscd-loader-fade-in' );
				$loader.show();
				// Remove animation class after animation completes
				setTimeout( function() {
					$loader.removeClass( 'wsscd-loader-fade-in' );
				}, 200 );
			} else {
				$loader.show();
			}
		},

		/**
		 * Hide loader
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 * @param {Object} options - Configuration options
		 * @param {boolean} options.fade - Use fade animation (default: true)
		 * @param {number} options.delay - Delay before hiding (milliseconds)
		 */
		hide: function( loader, options ) {
			var self = this;
			var $loader = this._getElement( loader );
			if ( ! $loader.length ) {
				return;
			}

			var settings = $.extend( {
				fade: true,
				delay: 0
			}, options );

			var hideLoader = function() {
				// Remove ARIA attributes
				$loader.attr( 'aria-busy', 'false' );
				$loader.removeAttr( 'aria-live' );
				$loader.removeAttr( 'role' );

				// Hide loader with optional fade animation
				if ( settings.fade ) {
					$loader.addClass( 'wsscd-loader-fade-out' );
					setTimeout( function() {
						$loader.hide();
						$loader.removeClass( 'wsscd-loader-fade-out' );
					}, 200 );
				} else {
					$loader.hide();
				}
			};

			if ( settings.delay > 0 ) {
				setTimeout( hideLoader, settings.delay );
			} else {
				hideLoader();
			}
		},

		/**
		 * Toggle loader visibility
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 * @param {boolean} show - True to show, false to hide
		 * @param {Object} options - Configuration options (passed to show/hide)
		 */
		toggle: function( loader, show, options ) {
			if ( show ) {
				this.show( loader, options );
			} else {
				this.hide( loader, options );
			}
		},

	/**
	 * Show button loading state
	 *
	 * Hides button text and shows loader inline with button.
	 * Handles both pre-rendered loaders and dynamic HTML replacement.
	 *
	 * @param {string|jQuery} button - Button element ID or jQuery object
	 * @param {string} text - Loading text to display (optional)
	 */
	showButton: function( button, text ) {
		var $button = this._getElement( button );
		if ( ! $button.length ) {
			return;
		}

		// Add loading class to button
		$button.addClass( 'wsscd-button-loading' );
		$button.prop( 'disabled', true );

		// Check if button has pre-rendered loader element
		var $loader = $button.find( '.wsscd-loader-button' );

		if ( $loader.length ) {
			// Pre-rendered loader exists - update text and show
			if ( text ) {
				$loader.find( '.wsscd-loader-text' ).text( text );
			}
			$loader.show();
		} else {
			// No pre-rendered loader - replace button HTML
			// Store original HTML for restoration
			if ( ! $button.data( 'wsscd-original-html' ) ) {
				$button.data( 'wsscd-original-html', $button.html() );
			}

			// Replace with loader HTML
			var loaderHtml = WSSCD.IconHelper.spinner( { size: 16 } );
			if ( text ) {
				loaderHtml += ' ' + text;
			}
			$button.html( loaderHtml );
		}

		// Set ARIA attributes
		$button.attr( 'aria-busy', 'true' );
	},


	/**
	 * Hide button loading state
	 *
	 * Shows button text and hides loader.
	 * Handles both pre-rendered loaders and dynamic HTML restoration.
	 *
	 * @param {string|jQuery} button - Button element ID or jQuery object
	 * @param {boolean} keepDisabled - Keep button disabled (default: false)
	 */
	hideButton: function( button, keepDisabled ) {
		var $button = this._getElement( button );
		if ( ! $button.length ) {
			return;
		}

		// Remove loading class from button
		$button.removeClass( 'wsscd-button-loading' );

		if ( ! keepDisabled ) {
			$button.prop( 'disabled', false );
		}

		// Check if button has pre-rendered loader element
		var $loader = $button.find( '.wsscd-loader-button' );

		if ( $loader.length ) {
			// Pre-rendered loader - just hide it
			$loader.hide();
		} else {
			// Dynamic HTML replacement - restore original HTML
			var originalHtml = $button.data( 'wsscd-original-html' );
			if ( originalHtml ) {
				$button.html( originalHtml );
				$button.removeData( 'wsscd-original-html' );
			}
		}

		// Remove ARIA attributes
		$button.attr( 'aria-busy', 'false' );
	},


		/**
		 * Show inline loader
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 */
		showInline: function( loader ) {
			var $loader = this._getElement( loader );
			if ( ! $loader.length ) {
				return;
			}

			$loader.addClass( 'is-active' );
		},

		/**
		 * Hide inline loader
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 */
		hideInline: function( loader ) {
			var $loader = this._getElement( loader );
			if ( ! $loader.length ) {
				return;
			}

			$loader.removeClass( 'is-active' );
		},

	/**
	 * Show loading overlay on target element
	 *
	 * Creates a dynamic overlay with spinner and message.
	 *
	 * @param {string|jQuery} target - Target element to overlay
	 * @param {string} message - Loading message (default: 'Loading...')
	 * @return {jQuery} Overlay element
	 */
	showOverlay: function( target, message ) {
		message = message || 'Loading...';
		var $target = this._getElement( target );
		if ( ! $target.length ) {
			return null;
		}

		// Remove existing overlay
		$target.find( '.wsscd-loading-overlay' ).remove();

		// Ensure target has position for absolute overlay
		if ( 'static' === $target.css( 'position' ) ) {
			$target.css( 'position', 'relative' );
		}

		// Create overlay element
		var $overlay = $(
			'<div class="wsscd-loading-overlay">' +
				'<div class="wsscd-loading-content">' +
					'<span class="spinner is-active"></span>' +
					'<span class="wsscd-loading-text">' + this._escapeHtml( message ) + '</span>' +
				'</div>' +
			'</div>'
		);

		// Set ARIA attributes
		$overlay.attr( 'role', 'status' );
		$overlay.attr( 'aria-live', 'polite' );
		$overlay.attr( 'aria-busy', 'true' );

		// Append and fade in
		$target.append( $overlay );
		$overlay.hide().fadeIn( 200 );

		return $overlay;
	},

	/**
	 * Hide loading overlay
	 *
	 * @param {string|jQuery} target - Target element
	 */
	hideOverlay: function( target ) {
		var $target = this._getElement( target );
		if ( ! $target.length ) {
			return;
		}

		$target.find( '.wsscd-loading-overlay' ).fadeOut( 200, function() {
			$( this ).remove();
		} );
	},

		/**
		 * Update loader text
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 * @param {string} text - New text to display
		 */
		updateText: function( loader, text ) {
			var $loader = this._getElement( loader );
			if ( ! $loader.length ) {
				return;
			}

			$loader.find( '.wsscd-loader-text' ).text( text );
		},

		/**
		 * Check if loader is visible
		 *
		 * @param {string|jQuery} loader - Loader element ID or jQuery object
		 * @return {boolean} True if loader is visible
		 */
		isVisible: function( loader ) {
			var $loader = this._getElement( loader );
			if ( ! $loader.length ) {
				return false;
			}

			return $loader.is( ':visible' );
		},
	/**
	 * Escape HTML to prevent XSS
	 *
	 * @private
	 * @param {string} text - Text to escape
	 * @return {string} Escaped text
	 */
	_escapeHtml: function( text ) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String( text ).replace( /[&<>"']/g, function( m ) {
			return map[m];
		} );
	},


		/**
		 * Get element from ID or jQuery object
		 *
		 * @private
		 * @param {string|jQuery} element - Element ID or jQuery object
		 * @return {jQuery} jQuery object
		 */
		_getElement: function( element ) {
			if ( element instanceof $ ) {
				return element;
			}

			// Add # prefix if not present
			var selector = element;
			if ( selector.indexOf( '#' ) !== 0 ) {
				selector = '#' + selector;
			}

			return $( selector );
		}
	};

	/**
	 * jQuery plugin wrapper for convenience
	 */
	$.fn.wsscdLoader = function( action, options ) {
		var loader = this;

		switch ( action ) {
			case 'show':
				WSSCD.LoaderUtil.show( loader, options );
				break;
			case 'hide':
				WSSCD.LoaderUtil.hide( loader, options );
				break;
			case 'toggle':
				WSSCD.LoaderUtil.toggle( loader, options.show, options );
				break;
			case 'updateText':
				WSSCD.LoaderUtil.updateText( loader, options );
				break;
			default:
				console.warn( 'Unknown wsscdLoader action:', action );
		}

		return this;
	};

	/**
	 * Legacy compatibility aliases
	 * For backward compatibility with existing code
	 */
	window.WSSCD.Loader = window.WSSCD.LoaderUtil;

} )( jQuery );
