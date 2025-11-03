/**
 * Queue Management
 *
 * Handles queue management actions on the notifications queue status tab.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/assets/js/admin
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Queue Management Handler
	 */
	var QueueManagement = {

		/**
		 * Initialize
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$processQueueBtn = $( '#scd-process-queue' );
			this.$retryFailedBtn = $( '#scd-retry-failed' );
			this.$clearQueueBtn = $( '#scd-clear-queue' );
			this.$result = $( '#scd-queue-result' );
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Process queue button
			this.$processQueueBtn.on( 'click', function() {
				self.processQueue();
			} );

			// Retry failed button
			this.$retryFailedBtn.on( 'click', function() {
				self.retryFailed();
			} );

			// Clear queue button
			this.$clearQueueBtn.on( 'click', function() {
				if ( confirm( scdQueueL10n.confirmClear ) ) {
					self.clearQueue();
				}
			} );
		},

		/**
		 * Process queue manually
		 */
		processQueue: function() {
			var self = this;
			var $button = this.$processQueueBtn;
			var originalText = $button.html();

			// Disable button and show loading
			$button.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + scdQueueL10n.processing );

			// Send AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_process_queue',
					nonce: scdQueueL10n.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						self.showNotice( response.data.message, 'success' );
						// Reload page to update stats
						setTimeout( function() {
							location.reload();
						}, 2000 );
					} else {
						self.showNotice( response.data || scdQueueL10n.processFailed, 'error' );
					}
				},
				error: function() {
					self.showNotice( scdQueueL10n.processFailed, 'error' );
				},
				complete: function() {
					// Re-enable button
					$button.prop( 'disabled', false ).html( originalText );
				}
			} );
		},

		/**
		 * Retry failed emails
		 */
		retryFailed: function() {
			var self = this;
			var $button = this.$retryFailedBtn;
			var originalText = $button.html();

			// Disable button and show loading
			$button.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + scdQueueL10n.retrying );

			// Send AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_retry_failed_emails',
					nonce: scdQueueL10n.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						self.showNotice( response.data.message, 'success' );
						// Reload page to update stats
						setTimeout( function() {
							location.reload();
						}, 2000 );
					} else {
						self.showNotice( response.data || scdQueueL10n.retryFailed, 'error' );
					}
				},
				error: function() {
					self.showNotice( scdQueueL10n.retryFailed, 'error' );
				},
				complete: function() {
					// Re-enable button
					$button.prop( 'disabled', false ).html( originalText );
				}
			} );
		},

		/**
		 * Clear old queue items
		 */
		clearQueue: function() {
			var self = this;
			var $button = this.$clearQueueBtn;
			var originalText = $button.html();

			// Disable button and show loading
			$button.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + scdQueueL10n.clearing );

			// Send AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_clear_queue',
					nonce: scdQueueL10n.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						self.showNotice( response.data.message, 'success' );
						// Reload page to update stats
						setTimeout( function() {
							location.reload();
						}, 2000 );
					} else {
						self.showNotice( response.data || scdQueueL10n.clearFailed, 'error' );
					}
				},
				error: function() {
					self.showNotice( scdQueueL10n.clearFailed, 'error' );
				},
				complete: function() {
					// Re-enable button
					$button.prop( 'disabled', false ).html( originalText );
				}
			} );
		},

		/**
		 * Show notice message
		 *
		 * @param {string} message Notice message
		 * @param {string} type Notice type (success, error, warning, info)
		 */
		showNotice: function( message, type ) {
			type = type || 'info';

			this.$result
				.removeClass( 'notice-success notice-error notice-warning notice-info' )
				.addClass( 'notice-' + type )
				.html( '<p>' + message + '</p>' )
				.fadeIn();
		}
	};

	/**
	 * Initialize when document is ready
	 */
	$( document ).ready( function() {
		// Only initialize on notifications queue page
		if ( $( 'body' ).hasClass( 'smart-cycle-discounts_page_scd-notifications' ) && $( '.scd-queue-actions' ).length ) {
			QueueManagement.init();
		}
	} );

	// Add CSS for spinning icon
	$( '<style>' +
		'@keyframes spin {' +
		'  from { transform: rotate(0deg); }' +
		'  to { transform: rotate(360deg); }' +
		'}' +
		'.dashicons.spin {' +
		'  animation: spin 1s linear infinite;' +
		'}' +
		'</style>' ).appendTo( 'head' );

} )( jQuery );
