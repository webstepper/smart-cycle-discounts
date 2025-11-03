/**
 * Wp I18N Polyfill
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/polyfills/wp-i18n-polyfill.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function() {
	'use strict';

	if ( 'undefined' !== typeof wp && 'undefined' !== typeof wp.i18n ) {
		return;
	}

	window.wp = window.wp || {};

	window.wp.i18n = {
		/**
		 * Translate string
		 *
		 * @param {string} text   Text to translate
		 * @param {string} _domain Text domain (ignored in polyfill)
		 * @returns {string} Original text (no translation in polyfill)
		 */
		__: function( text, _domain ) {
			return text;
		},

		/**
		 * Translate string with context
		 *
		 * @param {string} text    Text to translate
		 * @param {string} _context Translation context
		 * @param {string} _domain  Text domain (ignored in polyfill)
		 * @returns {string} Original text (no translation in polyfill)
		 */
		_x: function( text, _context, _domain ) {
			return text;
		},

		/**
		 * Translate plural string
		 *
		 * @param {string} single Singular form
		 * @param {string} plural Plural form
		 * @param {number} number Number to check
		 * @param {string} _domain Text domain (ignored in polyfill)
		 * @returns {string} Appropriate form based on number
		 */
		_n: function( single, plural, number, _domain ) {
			return 1 === number ? single : plural;
		},

		/**
		 * Translate plural string with context
		 *
		 * @param {string} single  Singular form
		 * @param {string} plural  Plural form
		 * @param {number} number  Number to check
		 * @param {string} _context Translation context
		 * @param {string} _domain  Text domain (ignored in polyfill)
		 * @returns {string} Appropriate form based on number
		 */
		_nx: function( single, plural, number, _context, _domain ) {
			return 1 === number ? single : plural;
		},

		/**
		 * Basic sprintf implementation
		 *
		 * @param {string} format Format string with %s placeholders
		 * @returns {string} Formatted string
		 */
		sprintf: function( format ) {
			var args = Array.prototype.slice.call( arguments, 1 );
			var i = 0;

			return format.replace( /%s/g, function() {
				return i < args.length ? String( args[i++] ) : '%s';
			} );
		}
	};

} )();