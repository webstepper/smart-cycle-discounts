/**
 * Admin Notices Dismiss Handler
 *
 * Handles dismissal of all SCD admin notices (campaign expiration, currency change, etc.)
 * Uses data attributes to determine which AJAX action to call.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/admin-notices-dismiss.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize admin notice dismiss handlers.
	 */
	$( document ).ready( function() {
		// Verify localized data is available
		if ( 'undefined' === typeof window.scdAdminNotices ) {
			console.error( 'SCD Admin Notices: Localized data not found' );
			return;
		}

		/**
		 * Handle dismiss button clicks for all SCD admin notices.
		 *
		 * Uses data-action attribute to determine which AJAX action to call.
		 * Nonces are provided via localized scdAdminNotices object.
		 *
		 * Note: Using .off().on() to prevent duplicate handlers if script loads multiple times.
		 */
		$( '.scd-dismiss-notice' ).off( 'click.scdDismiss' ).on( 'click.scdDismiss', function() {
			var $button = $( this );
			var $notice = $button.closest( '.notice' );
			var action  = $button.data( 'action' );
			var type    = $button.data( 'type' ); // 'expiration' or 'currency'

			// Validate required data attributes
			if ( ! action || ! type ) {
				console.error( 'SCD Admin Notice: Missing required data attributes (action or type)' );
				return;
			}

			// Get nonce from localized data
			var nonce = window.scdAdminNotices.nonces[type];
			if ( ! nonce ) {
				console.error( 'SCD Admin Notice: Nonce not found for type "' + type + '"' );
				return;
			}

			// Make AJAX request to dismiss notice
			$.post(
				window.ajaxurl,
				{
					action: action,
					_wpnonce: nonce
				},
				function( response ) {
					// Success or error, just fade out the notice
					// Server-side will handle transient deletion
					$notice.fadeOut( 300, function() {
						$( this ).remove();
					} );
				}
			).fail( function( xhr, status, error ) {
				// Log error but still remove notice from UI
				console.error( 'SCD Admin Notice dismiss error:', error );
				$notice.fadeOut( 300, function() {
					$( this ).remove();
				} );
			} );
		} );
	} );

}( jQuery ) );
