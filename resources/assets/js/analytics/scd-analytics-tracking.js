/**
 * Scd Analytics Tracking
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/analytics/scd-analytics-tracking.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCDAnalytics = {
		/**
		 * Track analytics event
		 *
		 * @param {string} eventType Event type to track
		 * @param {number} campaignId Campaign ID
		 * @param {object} eventData Event data object
		 */
		trackEvent: function( eventType, campaignId, eventData ) {
			if ( window.scdAnalyticsTracking ) {
				$.ajax( {
					url: window.scdAnalyticsTracking.ajaxUrl,
					type: 'POST',
					data: {
						action: 'scd_track_event',
						event_type: eventType,
						campaign_id: campaignId,
						event_data: eventData || {},
						nonce: window.scdAnalyticsTracking.nonce,
						tracking_token: window.scdAnalyticsTracking.trackingToken
					},
					dataType: 'json'
				} );
			}
		},

		/**
		 * Track discount view
		 *
		 * @param {number} campaignId Campaign ID
		 * @param {number} productId Product ID
		 * @param {object} discountData Discount data
		 */
		trackDiscountView: function( campaignId, productId, discountData ) {
			var eventData = {
				product_id: productId
			};

			// ES5 compatible way to merge objects
			for ( var key in discountData ) {
				if ( Object.prototype.hasOwnProperty.call( discountData, key ) ) {
					eventData[key] = discountData[key];
				}
			}

			this.trackEvent( 'discount_view', campaignId, eventData );
		},

		/**
		 * Track discount click
		 *
		 * @param {number} campaignId Campaign ID
		 * @param {number} productId Product ID
		 * @param {string} clickSource Click source
		 */
		trackDiscountClick: function( campaignId, productId, clickSource ) {
			this.trackEvent( 'discount_click', campaignId, {
				product_id: productId,
				click_source: clickSource || 'unknown'
			} );
		}
	};

	// Auto-track discount views on page load
	$( document ).ready( function() {
		$( '.scd-discount-badge, .scd-discount-banner' ).each( function() {
			var $element = $( this );
			var campaignId = $element.data( 'campaign-id' );
			var productId = $element.data( 'product-id' );

			if ( campaignId && productId ) {
				window.SCDAnalytics.trackDiscountView( campaignId, productId, {
					element_type: $element.hasClass( 'scd-discount-badge' ) ? 'badge' : 'banner'
				} );
			}
		} );
	} );

	// Track discount clicks
	$( document ).on( 'click', '.scd-discount-badge, .scd-discount-banner', function() {
		var $element = $( this );
		var campaignId = $element.data( 'campaign-id' );
		var productId = $element.data( 'product-id' );
		var clickSource = $element.hasClass( 'scd-discount-badge' ) ? 'badge' : 'banner';

		if ( campaignId && productId ) {
			window.SCDAnalytics.trackDiscountClick( campaignId, productId, clickSource );
		}
	} );

} )( jQuery );