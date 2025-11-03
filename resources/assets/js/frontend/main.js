/**
 * Main
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/frontend/main.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Wait for document ready
	$( document ).ready( function() {

		/**
		 * Frontend Discount Manager
		 */
		var SCDFrontend = {

			/**
			 * Initialize frontend components
			 */
			init: function() {
				this.bindEvents();
				this.initDiscountBadges();
				this.initCountdownTimers();
			},

			/**
			 * Bind frontend events
			 */
			bindEvents: function() {
				$( document ).on( 'found_variation', '.variations_form', this.onVariationFound.bind( this ) );
				$( document ).on( 'reset_data', '.variations_form', this.onVariationReset.bind( this ) );

				// Cart updates
				$( document.body ).on( 'added_to_cart', this.onAddedToCart.bind( this ) );
				$( document.body ).on( 'updated_cart_totals', this.onCartUpdated.bind( this ) );
			},

			/**
			 * Initialize discount badges
			 */
			initDiscountBadges: function() {
				// Find all products with discounts
				$( '.scd-discount-badge' ).each( function() {
					var $badge = $( this );

					$badge.addClass( 'scd-badge-animated' );
				} );
			},

			/**
			 * Initialize countdown timers
			 */
			initCountdownTimers: function() {
				$( '.scd-countdown-timer' ).each( function() {
					var $timer = $( this );
					var endTime = $timer.data( 'end-time' );

					if ( endTime ) {
						SCDFrontend.startCountdown( $timer, endTime );
					}
				} );
			},

			/**
			 * Start countdown timer
			 *
			 * @param {jQuery} $element Timer element
			 * @param {string} endTime End time ISO string
			 */
			startCountdown: function( $element, endTime ) {
				var endDate = new Date( endTime ).getTime();

				var timer = setInterval( function() {
					var now = new Date().getTime();
					var distance = endDate - now;

					// Time calculations
					var days = Math.floor( distance / ( 1000 * 60 * 60 * 24 ) );
					var hours = Math.floor( ( distance % ( 1000 * 60 * 60 * 24 ) ) / ( 1000 * 60 * 60 ) );
					var minutes = Math.floor( ( distance % ( 1000 * 60 * 60 ) ) / ( 1000 * 60 ) );
					var seconds = Math.floor( ( distance % ( 1000 * 60 ) ) / 1000 );

					// Display result
					if ( 0 < distance ) {
						var html = '';
						if ( 0 < days ) {
							html += '<span class="scd-timer-days">' + days + 'd</span> ';
						}
						html += '<span class="scd-timer-hours">' + hours + 'h</span> ';
						html += '<span class="scd-timer-minutes">' + minutes + 'm</span> ';
						html += '<span class="scd-timer-seconds">' + seconds + 's</span>';

						$element.html( html );
					} else {
						// Timer expired
						clearInterval( timer );
						$element.html( '<span class="scd-timer-expired">Expired</span>' );

						// Trigger expired event
						$( document.body ).trigger( 'scd_discount_expired', [ $element ] );
					}
				}, 1000 );

				$element.data( 'timer-id', timer );
			},

			/**
			 * Stop countdown timer
			 *
			 * @param {jQuery} $element Timer element
			 */
			stopCountdown: function( $element ) {
				var timerId = $element.data( 'timer-id' );
				if ( timerId ) {
					clearInterval( timerId );
					$element.removeData( 'timer-id' );
				}
			},

			/**
			 * Cleanup all timers
			 */
			cleanupAllTimers: function() {
				$( '.scd-countdown-timer' ).each( function() {
					SCD.Frontend.stopCountdown( $( this ) );
				} );
			},

			/**
			 * Handle variation found event
			 *
			 * @param {Event} event jQuery event
			 * @param {object} variation Variation data
			 */
			onVariationFound: function( event, variation ) {
				var $form = $( event.target );
				var $priceElement = $form.find( '.woocommerce-variation-price' );

				if ( variation.scd_discount ) {
					this.updateDiscountBadge( $priceElement, variation.scd_discount );
				}
			},

			/**
			 * Handle variation reset event
			 *
			 * @param {Event} event jQuery event
			 */
			onVariationReset: function( event ) {
				var $form = $( event.target );
				var $priceElement = $form.find( '.woocommerce-variation-price' );

				$priceElement.find( '.scd-discount-badge-dynamic' ).remove();
			},

			/**
			 * Update discount badge
			 *
			 * @param {jQuery} $container Container element
			 * @param {object} discount Discount data
			 */
			updateDiscountBadge: function( $container, discount ) {
				$container.find( '.scd-discount-badge-dynamic' ).remove();

				if ( 'percentage' === discount.type ) {
					var badge = '<span class="scd-discount-badge scd-discount-badge-dynamic">-' +
						discount.value + '%</span>';
					$container.append( badge );
				}
			},

			/**
			 * Handle added to cart event
			 *
			 * @param {Event} _event jQuery event (unused)
			 * @param {object} _fragments Cart fragments (unused)
			 */
			onAddedToCart: function( _event, _fragments ) {
				// Reinitialize components after cart update
				this.initDiscountBadges();
			},

			/**
			 * Handle cart updated event
			 */
			onCartUpdated: function() {
				// Reinitialize components after cart update
				this.initDiscountBadges();
			}
		};

		// Initialize
		SCDFrontend.init();

		// Cleanup on page unload to prevent memory leaks
		$( window ).on( 'beforeunload', function() {
			SCDFrontend.cleanupAllTimers();
		} );

		// Also cleanup when elements are removed from DOM
		$( document ).on( 'DOMNodeRemoved', function( e ) {
			var $target = $( e.target );
			if ( $target.hasClass( 'scd-countdown-timer' ) ) {
				SCDFrontend.stopCountdown( $target );
			} else {
				$target.find( '.scd-countdown-timer' ).each( function() {
					SCDFrontend.stopCountdown( $( this ) );
				} );
			}
		} );

		// Expose for external use
		window.SCDFrontend = SCDFrontend;

	} );

} )( jQuery );