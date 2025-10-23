/**
 * Initialize shared modules
 *
 * This ensures shared modules are available before other scripts try to use them
 */
( function() {
	'use strict';

	// Ensure SCD namespace exists
	window.SCD = window.SCD || {};
	window.SCD.Shared = window.SCD.Shared || {};

	// Shared modules namespace initialized
	// No logging needed - this is expected behavior

	// Define translation functions if not available
	if ( 'undefined' === typeof window.__ ) {
		window.__ = function( text, domain ) {
			// If wp.i18n is available, use it
			if ( window.wp && window.wp.i18n && window.wp.i18n.__ ) {
				return window.wp.i18n.__( text, domain || 'smart-cycle-discounts' );
			}
			// Otherwise return the text as-is
			return text;
		};
	}

	if ( 'undefined' === typeof window.sprintf ) {
		window.sprintf = function() {
			// If wp.i18n is available, use it
			if ( window.wp && window.wp.i18n && window.wp.i18n.sprintf ) {
				return window.wp.i18n.sprintf.apply( null, arguments );
			}
			// Basic sprintf implementation for fallback
			var args = Array.prototype.slice.call( arguments );
			var format = args.shift();
			var i = 0;
			return format.replace( /%[sdf]/g, function( match ) {
				return args[i++] || match;
			} );
		};
	}
} )();