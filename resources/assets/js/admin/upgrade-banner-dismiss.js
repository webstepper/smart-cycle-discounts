/**
 * Upgrade Banner Dismiss
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/upgrade-banner-dismiss.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize banner dismiss functionality.
	 *
	 * @since 1.0.0
	 */
	function initBannerDismiss() {
		// Handle dismiss button click
		$( document ).on( 'click', '.scd-banner-dismiss', function ( e ) {
			e.preventDefault();

			var $banner = $( this ).closest( '.scd-upgrade-banner-inline' );
			var bannerId = $banner.data( 'banner-id' );
			var dismissNonce = $banner.data( 'dismiss-nonce' );

			if ( ! bannerId || ! dismissNonce ) {
				console.error( 'SCD: Missing banner ID or nonce for dismiss action' );
				return;
			}

			// Send AJAX request to dismiss banner
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_dismiss_upgrade_banner',
					nonce: dismissNonce,
					banner_id: bannerId
				},
				beforeSend: function () {
					// Fade out banner immediately for better UX
					$banner.fadeOut( 200 );
				},
				success: function ( response ) {
					if ( response.success ) {
						setTimeout( function () {
							$banner.remove();
						}, 200 );
					} else {
						$banner.fadeIn( 200 );
						console.error( 'SCD: Failed to dismiss banner:', response.data.message );
					}
				},
				error: function ( xhr, status, error ) {
					$banner.fadeIn( 200 );
					console.error( 'SCD: AJAX error dismissing banner:', error );
				}
			});
		});
	}

	$( document ).ready( function () {
		initBannerDismiss();
	});

})( jQuery );
