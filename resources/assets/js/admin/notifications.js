/**
 * Notifications Page - Email Queue Management
 *
 * Handles button interactions for email queue management and test email functionality.
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Admin = SCD.Admin || {};

	/**
	 * Notifications Page Handler
	 */
	SCD.Admin.Notifications = {
		/**
		 * Initialize notifications page
		 */
		init: function() {
			this.bindEvents();
			this.initProviderFieldVisibility();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Send test email button
			$( '#scd-test-email' ).on( 'click', function( e ) {
				e.preventDefault();
				self.sendTestEmail( $( this ) );
			} );

			// Process queue button
			$( '#scd-process-queue' ).on( 'click', function( e ) {
				e.preventDefault();
				self.processQueue( $( this ) );
			} );

			// Retry failed emails button
			$( '#scd-retry-failed' ).on( 'click', function( e ) {
				e.preventDefault();
				self.retryFailedEmails( $( this ) );
			} );

			// Clear queue button
			$( '#scd-clear-queue' ).on( 'click', function( e ) {
				e.preventDefault();
				self.clearQueue( $( this ) );
			} );

			// Test provider connection buttons
			$( '.scd-test-connection-btn' ).on( 'click', function( e ) {
				e.preventDefault();
				var provider = $( this ).data( 'provider' );
				self.testProviderConnection( $( this ), provider );
			} );

			// Email provider dropdown change
			$( 'select[name="scd_settings[email_provider]"]' ).on( 'change', function() {
				self.toggleProviderFields( $( this ).val() );
			} );
		},

		/**
		 * Initialize provider field visibility based on current selection
		 */
		initProviderFieldVisibility: function() {
			var $providerSelect = $( 'select[name="scd_settings[email_provider]"]' );
			if ( $providerSelect.length > 0 ) {
				var selectedProvider = $providerSelect.val();
				this.toggleProviderFields( selectedProvider );
			}
		},

		/**
		 * Toggle provider-specific fields visibility
		 *
		 * @param {string} provider Selected provider (wpmail, sendgrid, amazonses)
		 */
		toggleProviderFields: function( provider ) {
			// Find all provider field rows
			var $sendgridRow = $( 'input[name="scd_settings[sendgrid_api_key]"]' ).closest( 'tr' );
			var $amazonAccessRow = $( 'input[name="scd_settings[amazonses_access_key]"]' ).closest( 'tr' );
			var $amazonSecretRow = $( 'input[name="scd_settings[amazonses_secret_key]"]' ).closest( 'tr' );
			var $amazonRegionRow = $( 'select[name="scd_settings[amazonses_region]"]' ).closest( 'tr' );

			// Hide all provider-specific fields
			$sendgridRow.hide();
			$amazonAccessRow.hide();
			$amazonSecretRow.hide();
			$amazonRegionRow.hide();

			// Show relevant fields based on provider
			if ( 'sendgrid' === provider ) {
				$sendgridRow.show();
			} else if ( 'amazonses' === provider ) {
				$amazonAccessRow.show();
				$amazonSecretRow.show();
				$amazonRegionRow.show();
			}
			// wpmail has no additional fields
		},

		/**
		 * Send test email
		 *
		 * @param {jQuery} $button Button element
		 */
		sendTestEmail: function( $button ) {
			var self = this;

			// Gather current settings from form
			var settings = {
				email_provider: $( 'select[name="scd_settings[email_provider]"]' ).val() || 'wpmail',
				from_email: $( 'input[name="scd_settings[from_email]"]' ).val() || '',
				from_name: $( 'input[name="scd_settings[from_name]"]' ).val() || '',
				sendgrid_api_key: $( 'input[name="scd_settings[sendgrid_api_key]"]' ).val() || '',
				amazonses_access_key: $( 'input[name="scd_settings[amazonses_access_key]"]' ).val() || '',
				amazonses_secret_key: $( 'input[name="scd_settings[amazonses_secret_key]"]' ).val() || '',
				amazonses_region: $( 'select[name="scd_settings[amazonses_region]"]' ).val() || 'us-east-1'
			};

			// Disable button and show loading state
			$button.prop( 'disabled', true );
			var originalText = $button.html();
			$button.html( '<span class="dashicons dashicons-update spin"></span> ' + scdNotifications.i18n.sending );

			// Make AJAX request
			SCD.Shared.AjaxService.post( 'send_test_email', {
				nonce: scdNotifications.nonce,
				settings: settings
			} ).then( function( response ) {
				// Success - show WordPress admin notice
				self.showAdminNotice(
					response.message || scdNotifications.i18n.testEmailSent,
					'success'
				);

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} ).catch( function( error ) {
				// Error - show WordPress admin notice
				self.showAdminNotice(
					error.message || scdNotifications.i18n.testEmailFailed,
					'error'
				);

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} );
		},

		/**
		 * Process email queue
		 *
		 * @param {jQuery} $button Button element
		 */
		processQueue: function( $button ) {
			var self = this;
			var $resultDiv = $( '#scd-queue-result' );

			// Disable button and show loading state
			$button.prop( 'disabled', true );
			var originalText = $button.html();
			$button.html( '<span class="dashicons dashicons-update spin"></span> ' + scdNotifications.i18n.processing );

			// Hide previous results
			$resultDiv.hide().removeClass( 'notice-success notice-error' );

			// Make AJAX request
			SCD.Shared.AjaxService.post( 'process_queue', {
				nonce: scdNotifications.nonce
			} ).then( function( response ) {
				// Success
				var message = response.message || scdNotifications.i18n.queueProcessed;
				if ( response.data && response.data.processed ) {
					message = scdNotifications.i18n.emailsProcessed.replace( '%d', response.data.processed );
				}

				$resultDiv
					.addClass( 'notice-success' )
					.html( '<p>' + message + '</p>' )
					.show();

				self.showAdminNotice( message, 'success' );

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );

				// Reload page after 2 seconds to show updated queue stats
				setTimeout( function() {
					window.location.reload();
				}, 2000 );
			} ).catch( function( error ) {
				// Error
				var errorMessage = error.message || scdNotifications.i18n.queueProcessFailed;

				$resultDiv
					.addClass( 'notice-error' )
					.html( '<p>' + errorMessage + '</p>' )
					.show();

				self.showAdminNotice( errorMessage, 'error' );

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} );
		},

		/**
		 * Retry failed emails
		 *
		 * @param {jQuery} $button Button element
		 */
		retryFailedEmails: function( $button ) {
			var self = this;
			var $resultDiv = $( '#scd-queue-result' );

			// Confirm action
			if ( ! confirm( scdNotifications.i18n.confirmRetry ) ) {
				return;
			}

			// Disable button and show loading state
			$button.prop( 'disabled', true );
			var originalText = $button.html();
			$button.html( '<span class="dashicons dashicons-update spin"></span> ' + scdNotifications.i18n.retrying );

			// Hide previous results
			$resultDiv.hide().removeClass( 'notice-success notice-error' );

			// Make AJAX request
			SCD.Shared.AjaxService.post( 'retry_failed_emails', {
				nonce: scdNotifications.nonce
			} ).then( function( response ) {
				// Success
				var message = response.message || scdNotifications.i18n.failedRetried;
				if ( response.data && response.data.retried ) {
					message = scdNotifications.i18n.emailsRetried.replace( '%d', response.data.retried );
				}

				$resultDiv
					.addClass( 'notice-success' )
					.html( '<p>' + message + '</p>' )
					.show();

				self.showAdminNotice( message, 'success' );

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );

				// Reload page after 2 seconds to show updated queue stats
				setTimeout( function() {
					window.location.reload();
				}, 2000 );
			} ).catch( function( error ) {
				// Error
				var errorMessage = error.message || scdNotifications.i18n.retryFailed;

				$resultDiv
					.addClass( 'notice-error' )
					.html( '<p>' + errorMessage + '</p>' )
					.show();

				self.showAdminNotice( errorMessage, 'error' );

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} );
		},

		/**
		 * Clear old queue items
		 *
		 * @param {jQuery} $button Button element
		 */
		clearQueue: function( $button ) {
			var self = this;
			var $resultDiv = $( '#scd-queue-result' );

			// Confirm action
			if ( ! confirm( scdNotifications.i18n.confirmClear ) ) {
				return;
			}

			// Disable button and show loading state
			$button.prop( 'disabled', true );
			var originalText = $button.html();
			$button.html( '<span class="dashicons dashicons-update spin"></span> ' + scdNotifications.i18n.clearing );

			// Hide previous results
			$resultDiv.hide().removeClass( 'notice-success notice-error' );

			// Make AJAX request
			SCD.Shared.AjaxService.post( 'clear_queue', {
				nonce: scdNotifications.nonce
			} ).then( function( response ) {
				// Success
				var message = response.message || scdNotifications.i18n.queueCleared;
				if ( response.data && response.data.cleared ) {
					message = scdNotifications.i18n.itemsCleared.replace( '%d', response.data.cleared );
				}

				$resultDiv
					.addClass( 'notice-success' )
					.html( '<p>' + message + '</p>' )
					.show();

				self.showAdminNotice( message, 'success' );

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );

				// Reload page after 2 seconds to show updated queue stats
				setTimeout( function() {
					window.location.reload();
				}, 2000 );
			} ).catch( function( error ) {
				// Error
				var errorMessage = error.message || scdNotifications.i18n.clearFailed;

				$resultDiv
					.addClass( 'notice-error' )
					.html( '<p>' + errorMessage + '</p>' )
					.show();

				self.showAdminNotice( errorMessage, 'error' );

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} );
		},

		/**
		 * Test email provider connection
		 *
		 * @param {jQuery} $button   Button element
		 * @param {string} provider  Provider name (sendgrid, amazonses)
		 */
		testProviderConnection: function( $button, provider ) {
			var self = this;

			// Get provider-specific configuration values
			var config = {};

			if ( 'sendgrid' === provider ) {
				config.api_key = $( '#scd_settings_sendgrid_api_key' ).val();

				if ( ! config.api_key ) {
					self.showAdminNotice( scdNotifications.i18n.missingApiKey, 'error' );
					return;
				}
			} else if ( 'amazonses' === provider ) {
				config.access_key = $( '#scd_settings_amazonses_access_key' ).val();
				config.secret_key = $( '#scd_settings_amazonses_secret_key' ).val();
				config.region = $( '#scd_settings_amazonses_region' ).val();

				if ( ! config.access_key || ! config.secret_key ) {
					self.showAdminNotice( scdNotifications.i18n.missingCredentials, 'error' );
					return;
				}
			}

			// Disable button and show loading state
			$button.prop( 'disabled', true );
			var originalText = $button.html();
			$button.html( '<span class="dashicons dashicons-update spin"></span> ' + scdNotifications.i18n.testing );

			// Make AJAX request
			SCD.Shared.AjaxService.post( 'test_provider_connection', {
				nonce: scdNotifications.nonce,
				provider: provider,
				config: config
			} ).then( function( response ) {
				// Success
				self.showAdminNotice(
					response.message || scdNotifications.i18n.connectionSuccess,
					'success'
				);

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} ).catch( function( error ) {
				// Error
				self.showAdminNotice(
					error.message || scdNotifications.i18n.connectionFailed,
					'error'
				);

				// Restore button
				$button.prop( 'disabled', false ).html( originalText );
			} );
		},

		/**
		 * Show WordPress admin notice
		 *
		 * @param {string} message Notice message
		 * @param {string} type Notice type (success, error, warning, info)
		 */
		showAdminNotice: function( message, type ) {
			type = type || 'info';

			// Remove any existing notifications
			$( '.scd-admin-notice' ).remove();

			// Create WordPress-style admin notice
			var $notice = $( '<div>' )
				.addClass( 'notice notice-' + type + ' is-dismissible scd-admin-notice' )
				.html( '<p>' + message + '</p>' );

			// Add dismiss button
			var $dismissButton = $( '<button>' )
				.attr( 'type', 'button' )
				.addClass( 'notice-dismiss' )
				.html( '<span class="screen-reader-text">Dismiss this notice.</span>' );

			$notice.append( $dismissButton );

			// Insert after page title (WordPress standard position)
			if ( $( '.wrap > h1' ).length ) {
				$( '.wrap > h1' ).after( $notice );
			} else if ( $( '.wrap > h2' ).length ) {
				$( '.wrap > h2' ).after( $notice );
			} else {
				$( '.wrap' ).prepend( $notice );
			}

			// Bind dismiss button
			$dismissButton.on( 'click', function() {
				$notice.fadeOut( 200, function() {
					$( this ).remove();
				} );
			} );

			// Auto-dismiss success notices after 5 seconds
			if ( 'success' === type ) {
				setTimeout( function() {
					$notice.fadeOut( 200, function() {
						$( this ).remove();
					} );
				}, 5000 );
			}

			// Scroll to notice
			$( 'html, body' ).animate( {
				scrollTop: $notice.offset().top - 50
			}, 300 );
		}
	};

	// Initialize on document ready
	$( document ).ready( function() {
		// Check if we're on the notifications page
		var isNotificationsPage = $( 'body' ).hasClass( 'smart-cycle-discounts_page_scd-notifications' );

		// Check dependencies
		if ( typeof SCD.Shared === 'undefined' || typeof SCD.Shared.AjaxService === 'undefined' ) {
			return;
		}

		if ( typeof SCD.Shared.NotificationService === 'undefined' ) {
			return;
		}

		// Initialize if on notifications page OR if test email button exists (fallback)
		if ( isNotificationsPage || $( '#scd-test-email' ).length > 0 ) {
			SCD.Admin.Notifications.init();
		}
	} );

} )( jQuery );
