/**
 * Upgrade Banner Dismiss Handler
 *
 * Handles dismissing upgrade banners with AJAX.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
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

			// Validate data
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
						// Remove banner from DOM after fade out
						setTimeout( function () {
							$banner.remove();
						}, 200 );
					} else {
						// Show banner again if request failed
						$banner.fadeIn( 200 );
						console.error( 'SCD: Failed to dismiss banner:', response.data.message );
					}
				},
				error: function ( xhr, status, error ) {
					// Show banner again if request failed
					$banner.fadeIn( 200 );
					console.error( 'SCD: AJAX error dismissing banner:', error );
				}
			});
		});
	}

	// Initialize when document is ready
	$( document ).ready( function () {
		initBannerDismiss();
	});

})( jQuery );
