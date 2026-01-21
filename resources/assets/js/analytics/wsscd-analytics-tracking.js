/**
 * WSSCD Analytics Tracking
 *
 * Optimized analytics tracking using Beacon API and Intersection Observer.
 * Provides non-blocking, high-performance tracking for impressions and clicks.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/analytics/wsscd-analytics-tracking.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCDAnalytics = {
		/**
		 * Tracked impressions cache (prevents duplicate tracking)
		 */
		trackedImpressions: {},

		/**
		 * Send tracking data using Beacon API or fallback to AJAX
		 *
		 * @param {string} action AJAX action name
		 * @param {object} data   Data to send
		 * @return {boolean}        Success status
		 */
		sendBeacon: function( action, data ) {
			if ( ! window.wsscdAnalyticsTracking ) {
				return false;
			}

			// Prepare data for Beacon API
			var beaconData = new FormData();
			beaconData.append( 'action', action );
			beaconData.append( 'nonce', window.wsscdAnalyticsTracking.nonce );

			// Add all data properties
			for ( var key in data ) {
				if ( Object.prototype.hasOwnProperty.call( data, key ) ) {
					beaconData.append( key, data[key] );
				}
			}

			// Try Beacon API first (non-blocking)
			if ( navigator.sendBeacon ) {
				var success = navigator.sendBeacon(
					window.wsscdAnalyticsTracking.ajaxUrl,
					beaconData
				);

				if ( success ) {
					return true;
				}
			}

			// Fallback to AJAX if Beacon API fails or not available
			$.ajax( {
				url: window.wsscdAnalyticsTracking.ajaxUrl,
				type: 'POST',
				data: {
					action: action,
					campaignId: data.campaignId,
					productId: data.productId,
					source: data.source,
					nonce: window.wsscdAnalyticsTracking.nonce
				},
				async: true,
				cache: false
			} );

			return true;
		},

		/**
		 * Track campaign impression
		 *
		 * Uses aggregated storage for high-volume tracking.
		 *
		 * @param {number} campaignId Campaign ID
		 * @param {number} productId  Product ID (optional)
		 * @param {string} source     Source (optional)
		 */
		trackImpression: function( campaignId, productId, source ) {
			// Prevent duplicate impressions for same campaign
			var cacheKey = 'c' + campaignId + '_p' + ( productId || 0 );
			if ( this.trackedImpressions[cacheKey] ) {
				return;
			}

			this.trackedImpressions[cacheKey] = true;

			this.sendBeacon( 'wsscd_track_impression', {
				campaignId: campaignId,
				productId: productId || 0,
				source: source || 'unknown'
			} );
		},

		/**
		 * Track discount click
		 *
		 * Uses aggregated storage for high-volume tracking.
		 *
		 * @param {number} campaignId  Campaign ID
		 * @param {number} productId   Product ID (optional)
		 * @param {string} clickSource Click source
		 */
		trackClick: function( campaignId, productId, clickSource ) {
			this.sendBeacon( 'wsscd_track_click', {
				campaignId: campaignId,
				productId: productId || 0,
				source: clickSource || 'unknown'
			} );
		},

		/**
		 * Setup Intersection Observer for impression tracking
		 *
		 * Tracks when discount elements become visible in viewport.
		 */
		setupImpressionObserver: function() {
			// Check for Intersection Observer support
			if ( ! window.IntersectionObserver ) {
				// Fallback: track all elements immediately
				this.trackAllVisibleElements();
				return;
			}

			var self = this;

			var observerOptions = {
				root: null, // viewport
				rootMargin: '50px', // trigger slightly before entering viewport
				threshold: 0.5 // element must be at least 50% visible
			};

			var observer = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( entry.isIntersecting ) {
						var $element = $( entry.target );
						var campaignId = parseInt( $element.data( 'campaign-id' ), 10 );
						var productId = parseInt( $element.data( 'product-id' ), 10 ) || 0;
						var source = $element.hasClass( 'wsscd-discount-badge' ) ? 'badge' : 'banner';

						if ( campaignId ) {
							self.trackImpression( campaignId, productId, source );
						}

						// Stop observing this element
						observer.unobserve( entry.target );
					}
				} );
			}, observerOptions );

			// Observe all discount elements
			$( '.wsscd-discount-badge, .wsscd-discount-banner' ).each( function() {
				observer.observe( this );
			} );
		},

		/**
		 * Fallback: Track all visible elements immediately
		 *
		 * Used when Intersection Observer is not supported.
		 */
		trackAllVisibleElements: function() {
			var self = this;

			$( '.wsscd-discount-badge, .wsscd-discount-banner' ).each( function() {
				var $element = $( this );
				var campaignId = parseInt( $element.data( 'campaign-id' ), 10 );
				var productId = parseInt( $element.data( 'product-id' ), 10 ) || 0;
				var source = $element.hasClass( 'wsscd-discount-badge' ) ? 'badge' : 'banner';

				if ( campaignId ) {
					self.trackImpression( campaignId, productId, source );
				}
			} );
		}
	};

	// Initialize tracking on document ready
	$( document ).ready( function() {
		// Setup impression tracking using Intersection Observer
		window.WSSCDAnalytics.setupImpressionObserver();
	} );

	// Track discount clicks
	$( document ).on( 'click', '.wsscd-discount-badge, .wsscd-discount-banner, [data-wsscd-campaign-id]', function( e ) {
		var $element = $( this );
		var campaignId = parseInt( $element.data( 'campaign-id' ) || $element.data( 'wsscd-campaign-id' ), 10 );
		var productId = parseInt( $element.data( 'product-id' ) || $element.data( 'wsscd-product-id' ), 10 ) || 0;
		var clickSource = $element.data( 'click-source' ) || (
			$element.hasClass( 'wsscd-discount-badge' ) ? 'badge' : 'banner'
		);

		if ( campaignId ) {
			window.WSSCDAnalytics.trackClick( campaignId, productId, clickSource );
		}
	} );

} )( jQuery );