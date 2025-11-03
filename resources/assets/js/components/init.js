/**
 * Init
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/components/init.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
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