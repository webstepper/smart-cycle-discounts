/**
 * Notifications Settings Page
 *
 * Handles provider-specific field display, test email functionality,
 * and form interactions for the notifications settings page.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/assets/js/admin
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Notifications Settings Handler
	 */
	var NotificationsSettings = {

		/**
		 * Initialize
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.updateProviderFields();
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$providerSelect = $( '#email_provider' );
			this.$providerFields = $( '.wsscd-provider-setting' );
			this.$testEmailBtn = $( '#wsscd-test-email' );
			this.$testConnectionBtns = $( '.wsscd-test-connection-btn' );
			this.$form = $( '.wsscd-notifications-form' );
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Provider selection change
			this.$providerSelect.on( 'change', function() {
				self.updateProviderFields();
			} );

			// Test email button
			this.$testEmailBtn.on( 'click', function( e ) {
				e.preventDefault();
				self.sendTestEmail();
			} );

			// Test connection buttons
			this.$testConnectionBtns.on( 'click', function( e ) {
				e.preventDefault();
				var provider = $( this ).data( 'provider' );
				self.testProviderConnection( provider );
			} );

			// Form validation
			this.$form.on( 'submit', function( e ) {
				return self.validateForm();
			} );
		},

		/**
		 * Update provider-specific fields visibility
		 */
		updateProviderFields: function() {
			var selectedProvider = this.$providerSelect.val();

			// Hide all provider-specific fields first
			this.$providerFields.closest( 'tr' ).hide();

			// Show fields for selected provider
			if ( selectedProvider ) {
				$( '.wsscd-provider-' + selectedProvider ).closest( 'tr' ).show();
			}
		},

		/**
		 * Send test email
		 */
		sendTestEmail: function() {
			var self = this;
			var $button = this.$testEmailBtn;
			var originalText = $button.text();

			// Disable button and show loading
			$button.prop( 'disabled', true ).text( wsscdNotificationsL10n.sending );

			// Get form data
			var formData = this.$form.serializeArray();
			var settings = {};

			// Convert form data to settings object
			$( formData ).each( function( index, field ) {
				var fieldName = field.name.replace( 'wsscd_settings[notifications][', '' ).replace( ']', '' );
				settings[ fieldName ] = field.value;
			} );

			// Send AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wsscd_send_test_email',
					nonce: wsscdNotificationsL10n.nonce,
					settings: settings
				},
				success: function( response ) {
					if ( response.success ) {
						// Success response
						var message = response.data && response.data.message ? response.data.message : wsscdNotificationsL10n.testEmailSent;
						self.showNotice( message, 'success' );
					} else {
						// Error response - handle object or string
						var errorMessage = wsscdNotificationsL10n.testEmailFailed;

						if ( response.data ) {
							if ( typeof response.data === 'string' ) {
								errorMessage = response.data;
							} else if ( response.data.message ) {
								errorMessage = response.data.message;
							} else if ( response.data.error ) {
								errorMessage = response.data.error;
							}
						}

						self.showNotice( errorMessage, 'error' );
					}
				},
				error: function( xhr, status, error ) {
					console.error( 'AJAX Error:', status, error );
					console.error( 'Response:', xhr.responseText );
					self.showNotice( wsscdNotificationsL10n.testEmailFailed, 'error' );
				},
				complete: function() {
					// Re-enable button
					$button.prop( 'disabled', false ).text( originalText );
				}
			} );
		},

		/**
		 * Test provider connection
		 *
		 * @param {string} provider Provider name (sendgrid or amazonses)
		 */
		testProviderConnection: function( provider ) {
			var self = this;
			var $button = $( '.wsscd-test-connection-btn[data-provider="' + provider + '"]' );
			var originalHtml = $button.html();

			// Disable button and show loading
			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.showButton( $button, wsscdNotificationsL10n.testing );
			}

			// Get form data
			var formData = this.$form.serializeArray();
			var settings = {};

			// Convert form data to settings object
			$( formData ).each( function( index, field ) {
				var fieldName = field.name.replace( 'wsscd_settings[notifications][', '' ).replace( ']', '' );
				settings[ fieldName ] = field.value;
			} );

			// Send AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wsscd_test_provider_connection',
					nonce: wsscdNotificationsL10n.nonce,
					provider: provider,
					settings: settings
				},
				success: function( response ) {
					if ( response.success ) {
						// Success response
						var message = response.data && response.data.message ? response.data.message : wsscdNotificationsL10n.connectionSuccess;
						self.showNotice( message, 'success' );

						// Show stats if available
						if ( response.data && response.data.stats && Object.keys( response.data.stats ).length > 0 ) {
							self.showProviderStats( provider, response.data.stats );
						}
					} else {
						// Error response
						var errorMessage = wsscdNotificationsL10n.connectionFailed;

						if ( response.data ) {
							if ( typeof response.data === 'string' ) {
								errorMessage = response.data;
							} else if ( response.data.message ) {
								errorMessage = response.data.message;
							} else if ( response.data.error ) {
								errorMessage = response.data.error;
							}
						}

						self.showNotice( errorMessage, 'error' );
					}
				},
				error: function( xhr, status, error ) {
					console.error( 'AJAX Error:', status, error );
					console.error( 'Response:', xhr.responseText );
					self.showNotice( wsscdNotificationsL10n.connectionFailed, 'error' );
				},
				complete: function() {
					// Re-enable button
					if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
				}
			} );
		},

		/**
		 * Show provider statistics
		 *
		 * @param {string} provider Provider name
		 * @param {object} stats Statistics object
		 */
		showProviderStats: function( provider, stats ) {
			var statsHtml = '<div class="wsscd-provider-stats"><h4>Provider Statistics:</h4><ul>';

			for ( var key in stats ) {
				if ( stats.hasOwnProperty( key ) ) {
					var label = key.replace( /_/g, ' ' ).replace( /\b\w/g, function( l ) {
						return l.toUpperCase();
					} );
					statsHtml += '<li><strong>' + label + ':</strong> ' + stats[ key ] + '</li>';
				}
			}

			statsHtml += '</ul></div>';

			// Show stats in a notice
			this.showNotice( statsHtml, 'info' );
		},

		/**
		 * Validate form before submission
		 */
		validateForm: function() {
			var selectedProvider = this.$providerSelect.val();
			var isValid = true;

			// Validate provider-specific fields
			if ( 'sendgrid' === selectedProvider ) {
				var apiKey = $( '#sendgrid_api_key' ).val();
				if ( ! apiKey || apiKey.trim() === '' ) {
					this.showNotice( wsscdNotificationsL10n.sendgridApiKeyRequired, 'error' );
					isValid = false;
				}
			}

			if ( 'amazonses' === selectedProvider ) {
				var accessKey = $( '#amazonses_access_key' ).val();
				var secretKey = $( '#amazonses_secret_key' ).val();

				if ( ! accessKey || accessKey.trim() === '' || ! secretKey || secretKey.trim() === '' ) {
					this.showNotice( wsscdNotificationsL10n.awsKeysRequired, 'error' );
					isValid = false;
				}
			}

			// Validate from email
			var fromEmail = $( '#from_email' ).val();
			if ( fromEmail && ! this.isValidEmail( fromEmail ) ) {
				this.showNotice( wsscdNotificationsL10n.invalidFromEmail, 'error' );
				isValid = false;
			}

			// Validate additional recipients
			var additionalRecipients = $( '#additional_recipients' ).val();
			if ( additionalRecipients ) {
				var emails = additionalRecipients.split( ',' );
				var self = this;
				$( emails ).each( function( index, email ) {
					var trimmedEmail = email.trim();
					if ( trimmedEmail && ! self.isValidEmail( trimmedEmail ) ) {
						self.showNotice( wsscdNotificationsL10n.invalidRecipientEmail.replace( '%s', trimmedEmail ), 'error' );
						isValid = false;
						return false; // Break loop
					}
				} );
			}

			return isValid;
		},

		/**
		 * Validate email address format
		 *
		 * @param {string} email Email address to validate
		 * @return {boolean} True if valid
		 */
		isValidEmail: function( email ) {
			var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return regex.test( email );
		},

		/**
		 * Show admin notice
		 *
		 * @param {string} message Notice message
		 * @param {string} type Notice type (success, error, warning, info)
		 */
		showNotice: function( message, type ) {
			type = type || 'info';

			var $notice = $( '<div>' )
				.addClass( 'notice notice-' + type + ' is-dismissible' )
				.append( '<p>' + message + '</p>' )
				.append( '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' );

			// Insert after h1
			$( '.wrap h1' ).first().after( $notice );

			// Make dismissible
			$notice.find( '.notice-dismiss' ).on( 'click', function() {
				$notice.fadeOut( function() {
					$( this ).remove();
				} );
			} );

			// Auto-dismiss after 5 seconds
			setTimeout( function() {
				$notice.fadeOut( function() {
					$( this ).remove();
				} );
			}, 5000 );

			// Scroll to notice
			$( 'html, body' ).animate( {
				scrollTop: $notice.offset().top - 50
			}, 300 );
		}
	};

	/**
	 * Initialize when document is ready
	 */
	$( document ).ready( function() {
		// Only initialize on notifications settings page
		// Check if provider select exists (more reliable than body class)
		if ( $( '#email_provider' ).length > 0 ) {
			NotificationsSettings.init();
		}
	} );

} )( jQuery );
