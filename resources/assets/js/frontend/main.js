/**
 * Main
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/frontend/main.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
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
		var WSSCDFrontend = {

			/**
			 * Cache for spend threshold data
			 */
			spendThresholdCache: null,

			/**
			 * Initialize frontend components
			 */
			init: function() {
				this.bindEvents();
				this.initDiscountBadges();
				this.initCountdownTimers();
				this.initSpendThresholdProgress();
			},

			/**
			 * Bind frontend events
			 */
			bindEvents: function() {
				var self = this;

				$( document ).on( 'found_variation', '.variations_form', this.onVariationFound.bind( this ) );
				$( document ).on( 'reset_data', '.variations_form', this.onVariationReset.bind( this ) );

				// Cart updates - refresh spend threshold progress
				$( document.body ).on( 'added_to_cart', this.onAddedToCart.bind( this ) );
				$( document.body ).on( 'updated_cart_totals', this.onCartUpdated.bind( this ) );
				$( document.body ).on( 'removed_from_cart', function() {
					self.refreshSpendThresholdProgress();
				} );
				$( document.body ).on( 'wc_cart_emptied', function() {
					self.refreshSpendThresholdProgress();
				} );

				// Listen for mini-cart updates
				$( document.body ).on( 'wc_fragments_refreshed', function() {
					self.refreshSpendThresholdProgress();
				} );
			},

			/**
			 * Initialize discount badges
			 */
			initDiscountBadges: function() {
				// Find all products with discounts
				$( '.wsscd-discount-badge' ).each( function() {
					var $badge = $( this );

					$badge.addClass( 'wsscd-badge-animated' );
				} );
			},

			/**
			 * Initialize countdown timers
			 */
			initCountdownTimers: function() {
				$( '.wsscd-countdown-timer' ).each( function() {
					var $timer = $( this );
					var endTime = $timer.data( 'end-time' );

					if ( endTime ) {
						WSSCDFrontend.startCountdown( $timer, endTime );
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
							html += '<span class="wsscd-timer-days">' + days + 'd</span> ';
						}
						html += '<span class="wsscd-timer-hours">' + hours + 'h</span> ';
						html += '<span class="wsscd-timer-minutes">' + minutes + 'm</span> ';
						html += '<span class="wsscd-timer-seconds">' + seconds + 's</span>';

						$element.html( html );
					} else {
						// Timer expired
						clearInterval( timer );
						$element.html( '<span class="wsscd-timer-expired">Expired</span>' );

						// Trigger expired event
						$( document.body ).trigger( 'wsscd_discount_expired', [ $element ] );
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
				$( '.wsscd-countdown-timer' ).each( function() {
					WSSCD.Frontend.stopCountdown( $( this ) );
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

				if ( variation.wsscd_discount ) {
					this.updateDiscountBadge( $priceElement, variation.wsscd_discount );
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

				$priceElement.find( '.wsscd-discount-badge-dynamic' ).remove();
			},

			/**
			 * Update discount badge
			 *
			 * @param {jQuery} $container Container element
			 * @param {object} discount Discount data
			 */
			updateDiscountBadge: function( $container, discount ) {
				$container.find( '.wsscd-discount-badge-dynamic' ).remove();

				if ( 'percentage' === discount.type ) {
					var badge = '<span class="wsscd-discount-badge wsscd-discount-badge-dynamic">-' +
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
				this.refreshSpendThresholdProgress();
			},

			/**
			 * Initialize spend threshold progress display
			 */
			initSpendThresholdProgress: function() {
				// Only initialize on cart page or if progress bars exist
				if ( $( '.wsscd-spend-progress' ).length > 0 || $( '.wsscd-badge-spend_threshold' ).length > 0 ) {
					this.refreshSpendThresholdProgress();
				}
			},

			/**
			 * Refresh spend threshold progress via AJAX
			 */
			refreshSpendThresholdProgress: function() {
				var self = this;

				// Get settings from localized data
				var settings = window.wsscdFrontend || {};
				var ajaxUrl = settings.ajax_url || ( window.woocommerce_params && window.woocommerce_params.ajax_url ) || '/wp-admin/admin-ajax.php';
				var nonce = settings.nonce || '';

				if ( ! nonce ) {
					return;
				}

				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wsscd_get_spend_threshold_progress',
						nonce: nonce
					},
					success: function( response ) {
						if ( response.success && response.data ) {
							self.updateSpendThresholdDisplay( response.data );
						}
					},
					error: function() {
						// Silently fail - don't disrupt user experience
					}
				} );
			},

			/**
			 * Update spend threshold display with new data
			 *
			 * @param {object} data Spend threshold data from AJAX
			 */
			updateSpendThresholdDisplay: function( data ) {
				var self = this;
				var thresholds = data.thresholds || [];

				if ( 0 === thresholds.length ) {
					// No active spend threshold campaigns
					$( '.wsscd-spend-progress-container' ).hide();
					return;
				}

				// Update each threshold progress bar
				$.each( thresholds, function( index, threshold ) {
					var $container = $( '.wsscd-spend-progress[data-campaign-id="' + threshold.campaign_id + '"]' );

					if ( 0 === $container.length ) {
						// Create progress bar if it doesn't exist
						self.createSpendProgressBar( threshold );
					} else {
						self.updateSpendProgressBar( $container, threshold );
					}
				} );

				// Show progress containers
				$( '.wsscd-spend-progress-container' ).show();
			},

			/**
			 * Create spend threshold progress bar
			 *
			 * @param {object} threshold Threshold data
			 */
			createSpendProgressBar: function( threshold ) {
				var progressHtml = this.buildProgressBarHtml( threshold );
				var $cartTotals = $( '.cart_totals' );

				if ( $cartTotals.length > 0 ) {
					// Insert before cart totals on cart page
					if ( 0 === $( '.wsscd-spend-progress-container' ).length ) {
						$cartTotals.before( '<div class="wsscd-spend-progress-container"></div>' );
					}
					$( '.wsscd-spend-progress-container' ).append( progressHtml );
				}
			},

			/**
			 * Build progress bar HTML
			 *
			 * @param {object} threshold Threshold data
			 * @return {string} HTML string
			 */
			buildProgressBarHtml: function( threshold ) {
				var progressPercent = threshold.progress_percent || 0;
				var nextDiscount = threshold.next_discount;
				var currentDiscount = threshold.current_discount;
				var amountToNext = threshold.amount_to_next || 0;

				var message = '';
				if ( null !== nextDiscount && amountToNext > 0 ) {
					// Show how much more to spend
					var formattedAmount = this.formatPrice( amountToNext );
					message = '<span class="wsscd-spend-message">' +
						'Spend <strong>' + formattedAmount + '</strong> more to get <strong>' + nextDiscount.display + '</strong>!' +
						'</span>';
				} else if ( null !== currentDiscount ) {
					// All thresholds reached
					message = '<span class="wsscd-spend-message wsscd-spend-complete">' +
						'You\'re getting <strong>' + currentDiscount.display + '</strong>!' +
						'</span>';
				}

				var bgColor = threshold.badge_bg_color || '#d63638';

				return '<div class="wsscd-spend-progress" data-campaign-id="' + threshold.campaign_id + '">' +
					'<div class="wsscd-spend-progress-header">' +
						'<span class="wsscd-spend-title">' + ( threshold.campaign_name || 'Spend & Save' ) + '</span>' +
					'</div>' +
					'<div class="wsscd-spend-progress-bar-wrapper">' +
						'<div class="wsscd-spend-progress-bar" style="width: ' + progressPercent + '%; background-color: ' + bgColor + ';"></div>' +
					'</div>' +
					message +
				'</div>';
			},

			/**
			 * Update existing progress bar
			 *
			 * @param {jQuery} $container Progress bar container
			 * @param {object} threshold Threshold data
			 */
			updateSpendProgressBar: function( $container, threshold ) {
				var progressPercent = threshold.progress_percent || 0;
				var nextDiscount = threshold.next_discount;
				var currentDiscount = threshold.current_discount;
				var amountToNext = threshold.amount_to_next || 0;
				var bgColor = threshold.badge_bg_color || '#d63638';

				// Update progress bar width with animation
				$container.find( '.wsscd-spend-progress-bar' ).css( {
					'width': progressPercent + '%',
					'background-color': bgColor
				} );

				// Update message
				var $message = $container.find( '.wsscd-spend-message' );
				var newMessage = '';

				if ( null !== nextDiscount && amountToNext > 0 ) {
					var formattedAmount = this.formatPrice( amountToNext );
					newMessage = 'Spend <strong>' + formattedAmount + '</strong> more to get <strong>' + nextDiscount.display + '</strong>!';
					$message.removeClass( 'wsscd-spend-complete' );
				} else if ( null !== currentDiscount ) {
					newMessage = 'You\'re getting <strong>' + currentDiscount.display + '</strong>!';
					$message.addClass( 'wsscd-spend-complete' );
				}

				$message.html( newMessage );
			},

			/**
			 * Format price using WooCommerce settings
			 *
			 * @param {number} amount Amount to format
			 * @return {string} Formatted price
			 */
			formatPrice: function( amount ) {
				var settings = window.wsscdFrontend || {};
				var symbol = settings.currency_symbol || '$';
				var position = settings.currency_position || 'left';
				var decimals = settings.price_decimals || 2;
				var decimalSep = settings.decimal_separator || '.';
				var thousandSep = settings.thousand_separator || ',';

				// Format number
				var formatted = parseFloat( amount ).toFixed( decimals );
				var parts = formatted.split( '.' );
				parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );
				formatted = parts.join( decimalSep );

				// Apply currency position
				if ( 'left' === position || 'left_space' === position ) {
					return symbol + ( 'left_space' === position ? ' ' : '' ) + formatted;
				} else {
					return formatted + ( 'right_space' === position ? ' ' : '' ) + symbol;
				}
			}
		};

		// Initialize
		WSSCDFrontend.init();

		// Cleanup on page unload to prevent memory leaks
		$( window ).on( 'beforeunload', function() {
			WSSCDFrontend.cleanupAllTimers();
		} );

		// Also cleanup when elements are removed from DOM
		$( document ).on( 'DOMNodeRemoved', function( e ) {
			var $target = $( e.target );
			if ( $target.hasClass( 'wsscd-countdown-timer' ) ) {
				WSSCDFrontend.stopCountdown( $target );
			} else {
				$target.find( '.wsscd-countdown-timer' ).each( function() {
					WSSCDFrontend.stopCountdown( $( this ) );
				} );
			}
		} );

		// Expose for external use
		window.WSSCDFrontend = WSSCDFrontend;

	} );

} )( jQuery );