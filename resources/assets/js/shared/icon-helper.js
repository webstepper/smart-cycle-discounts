/**
 * Icon Helper - JavaScript SVG Icon Generator
 *
 * Provides a simple interface to generate SVG icons in JavaScript,
 * matching the PHP SCD_Icon_Helper functionality.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Assets/JS/Shared
 * @since      1.0.0
 */

(function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};

	/**
	 * Icon Helper
	 *
	 * Generates SVG icon HTML for use in JavaScript-generated content.
	 *
	 * @since 1.0.0
	 */
	window.SCD.IconHelper = {

		/**
		 * Generate an SVG icon
		 *
		 * @param {string} iconName - Name of the icon (e.g., 'check', 'close', 'arrow-down')
		 * @param {Object} options - Icon options
		 * @param {number} options.size - Icon size in pixels (default: 20)
		 * @param {string} options.className - Additional CSS classes
		 * @param {boolean} options.ariaHidden - Hide from screen readers (default: true)
		 * @returns {string} SVG icon HTML
		 */
		get: function( iconName, options ) {
			options = options || {};
			var size = options.size || 20;
			var className = 'scd-icon scd-icon--' + iconName + ( options.className ? ' ' + options.className : '' );
			var ariaHidden = options.ariaHidden !== false ? ' aria-hidden="true"' : '';

			// Get SVG path from localized data
			var iconPath = '';
			if ( typeof scdIcons !== 'undefined' && scdIcons.paths && scdIcons.paths[ iconName ] ) {
				iconPath = scdIcons.paths[ iconName ];
			} else {
				// Fallback: return empty SVG if icon not found
				console.warn( 'SCD Icon Helper: Icon "' + iconName + '" not found' );
				iconPath = '';
			}

			// Critical inline styles to prevent flash/jump on load
			var inlineStyle = 'width:' + size + 'px;' +
			                  'height:' + size + 'px;' +
			                  'display:inline-block;' +
			                  'vertical-align:middle;' +
			                  'flex-shrink:0';

			// Generate actual SVG element (matches PHP output)
			return '<svg class="' + className + '" ' +
			       'width="' + size + '" ' +
			       'height="' + size + '" ' +
			       'viewBox="0 0 24 24" ' +
			       'fill="currentColor" ' +
			       'xmlns="http://www.w3.org/2000/svg"' +
			       ariaHidden + ' ' +
			       'style="' + inlineStyle + '">' +
			       iconPath +
			       '</svg>';
		},

		/**
		 * Common icon shortcuts
		 */
		check: function( options ) {
			return this.get( 'check', options );
		},

		close: function( options ) {
			return this.get( 'close', options );
		},

		warning: function( options ) {
			return this.get( 'warning', options );
		},

		info: function( options ) {
			return this.get( 'info', options );
		},

		spinner: function( options ) {
			return this.get( 'update', $.extend( {}, options, { className: ( options && options.className || '' ) + ' scd-icon-spin' } ) );
		}
	};

})( jQuery );
