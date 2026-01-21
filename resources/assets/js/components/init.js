/**
 * Init
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/components/init.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function() {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Admin = window.WSSCD.Admin || {};
	window.wsscdAdmin = window.wsscdAdmin || {
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
		if ( window.WSSCD && window.WSSCD.Admin && 'function' === typeof window.WSSCD.Admin.init ) {
			window.WSSCD.Admin.init();
		}

		// Trigger custom event for other components
		$( document ).trigger( 'wsscd:admin:ready' );
	} );

} )();