/**
 * WordPress i18n Polyfill
 *
 * Provides fallback functionality when wp.i18n is not available
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */
( function() {
	'use strict';

	// Check if wp.i18n already exists
	if ( 'undefined' !== typeof wp && 'undefined' !== typeof wp.i18n ) {
		return;
	}

	// Create wp namespace if it doesn't exist
	window.wp = window.wp || {};

	// Create basic i18n implementation
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