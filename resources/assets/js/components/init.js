/**
 * Smart Cycle Discounts - Initialization Script
 * Ensures proper loading order and availability of global objects
 */

( function() {
	'use strict';

	// Create global namespaces if they don't exist
	window.SCD = window.SCD || {};
	window.SCD.Admin = window.SCD.Admin || {};
	window.scdAdmin = window.scdAdmin || {
		ajaxUrl: window.ajaxurl || '',
		nonce: '',
		restNonce: '',
		debug: false,
		strings: {}
	};

	// Ensure jQuery is available
	if ( 'undefined' === typeof jQuery ) {
		throw new Error( 'Smart Cycle Discounts: jQuery is not loaded' );
	}

	// Document ready handler
	jQuery( document ).ready( function( $ ) {
		// Initialize admin if not already initialized
		if ( window.SCD && window.SCD.Admin && 'function' === typeof window.SCD.Admin.init ) {
			window.SCD.Admin.init();
		}

		// Trigger custom event for other components
		$( document ).trigger( 'scd:admin:ready' );
	} );

} )();