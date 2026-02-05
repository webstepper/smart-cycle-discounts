/**
 * Wizard Session Monitor
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-session-monitor.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Session Monitor Module
	var SessionMonitor = {
		/**
		 * Check interval (1 minute)
		 */
		CHECK_INTERVAL: 60000,

		/**
		 * Warning threshold (5 minutes before expiration)
		 */
		WARNING_THRESHOLD: 300,

		/**
		 * Timer ID
		 */
		timerId: null,

		/**
		 * Warning shown flag
		 */
		warningShown: false,

		/**
		 * Last check timestamp
		 */
		lastCheck: 0,

		/**
		 * Initialize the session monitor
		 */
		init: function() {
			if ( 'undefined' === typeof window.WSSCD || ! window.WSSCD.Wizard ) {
				return;
			}

			// Start monitoring
			this.startMonitoring();

			// Listen for wizard activity to extend session
			this.bindActivityListeners();
		},

		/**
		 * Start monitoring session expiration
		 */
		startMonitoring: function() {
			var self = this;

			if ( this.timerId ) {
				clearInterval( this.timerId );
			}

			this.checkSession();

			this.timerId = setInterval( function() {
				self.checkSession();
			}, this.CHECK_INTERVAL );
		},

		/**
		 * Stop monitoring
		 */
		stopMonitoring: function() {
			if ( this.timerId ) {
				clearInterval( this.timerId );
				this.timerId = null;
			}
		},

		/**
		 * Check session status
		 */
		checkSession: function() {
			var self = this;
			var now = Date.now();

			// Throttle checks to once per minute
			if ( now - this.lastCheck < this.CHECK_INTERVAL - 1000 ) {
				return;
			}

			this.lastCheck = now;

			// Make AJAX request to check session using AjaxService
			if ( ! window.WSSCD || ! window.WSSCD.Shared || ! window.WSSCD.Shared.AjaxService ) {
				console.error( '[WSSCD Session Monitor] AjaxService not available' );
				return;
			}

			window.WSSCD.Shared.AjaxService.post( 'session_status', {}, {} ).then( function( response ) {
				if ( response.success && response.data ) {
					self.handleSessionStatus( response.data );
				}
			} ).catch( function( error ) {
				console.error( '[WSSCD Session Monitor] AJAX error:', error );
			} );
		},

		/**
		 * Handle session status response
		 *
		 * @param {Object} data - Response data
		 */
		handleSessionStatus: function( data ) {
			if ( ! data.session_exists ) {
				// No session exists yet (e.g., fresh wizard load)
				// This is normal - don't show error or stop monitoring
				// Session will be created when user starts interacting with wizard
				return;
			}

			var expirationInfo = data.expiration_info;

			if ( expirationInfo.is_expired ) {
				// Session just expired
				this.showSessionExpiredModal();
				this.stopMonitoring();
			} else if ( expirationInfo.is_expiring_soon && ! this.warningShown ) {
				// Session expiring soon (less than 5 minutes)
				this.showExpirationWarning( expirationInfo.time_remaining );
				this.warningShown = true;
			}
		},

		/**
		 * Show expiration warning
		 *
		 * @param {number} timeRemaining - Seconds until expiration
		 */
		showExpirationWarning: function( timeRemaining ) {
			var minutes = Math.ceil( timeRemaining / 60 );

			var message = 'Your session will expire in ' + minutes + ' minute' + ( 1 !== minutes ? 's' : '' ) + '. ';
			message += 'Save your progress to avoid losing data.';

			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				window.WSSCD.Shared.NotificationService.warning(
					'Session Expiration Warning: ' + message,
					10000, // 10 seconds for important session warning
					{
						id: 'session-expiration-warning',
						replace: true
					}
				);
			}

			// Trigger event via EventBus
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.EventBus ) {
				window.WSSCD.Wizard.EventBus.emit( 'session:warning', {
					timeRemaining: timeRemaining,
					minutes: minutes
				} );
			}
		},

		/**
		 * Show session expired modal
		 */
		showSessionExpiredModal: function() {
			// Create modal HTML
			var modalHtml = '<div class="wsscd-modal-overlay wsscd-session-expired-modal">' +
				'<div class="wsscd-modal wsscd-modal--medium">' +
				'<div class="wsscd-modal__header">' +
				'<h2 class="wsscd-modal__title">Session Expired</h2>' +
				'</div>' +
				'<div class="wsscd-modal__content">' +
				'<p>Your session has expired. Any unsaved changes may be lost.</p>' +
				'<p>Please refresh the page to continue working.</p>' +
				'</div>' +
				'<div class="wsscd-modal__footer">' +
				'<button type="button" class="button button-primary wsscd-refresh-page">Refresh Page</button>' +
				'</div>' +
				'</div>' +
				'</div>';

			// Add modal to body
			var $modal = $( modalHtml );
			$( 'body' ).append( $modal );

			// Bind refresh button
			$modal.find( '.wsscd-refresh-page' ).on( 'click', function() {
				window.location.reload();
			} );

			// Show modal with fade in
			setTimeout( function() {
				$modal.addClass( 'wsscd-modal--active' );
			}, 10 );

			// Trigger event via EventBus
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.EventBus ) {
				window.WSSCD.Wizard.EventBus.emit( 'session:expired' );
			}

			console.error( '[WSSCD Session Monitor] Session expired' );
		},

		/**
		 * Bind activity listeners to extend session
		 */
		bindActivityListeners: function() {
			var self = this;

			// Listen for step saves (session automatically extends)
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.EventBus ) {
				window.WSSCD.Wizard.EventBus.on( 'step:saved', function() {
					// Session was extended by save, reset warning
					self.warningShown = false;
				} );
			}

			// Listen for wizard navigation (indicates user is active)
			$( document ).on( 'wsscd:wizard:stepChanged', function() {
				// User is active, check session status
				self.checkSession();
			} );
		}
	};

	$( document ).ready( function() {
		// Only initialize on wizard pages
		if ( $( '.wsscd-wizard-wrap' ).length > 0 || $( '.wsscd-wizard-page' ).length > 0 ) {
			SessionMonitor.init();
		}
	} );

	// Expose to global scope
	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};
	window.WSSCD.Wizard.SessionMonitor = SessionMonitor;

} )( jQuery );
