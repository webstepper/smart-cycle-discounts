/**
 * Debug Logger
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/debug-logger.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};

	/**
	 * Debug Logger
	 */
	SCD.DebugLogger = {
		/**
		 * Log queue for batching requests
		 */
		logQueue: [],

		/**
		 * Flush timeout ID
		 */
		flushTimeout: null,

		/**
		 * Initialize logger
		 */
		init: function() {
			// Auto-flush on page unload
			$( window ).on( 'beforeunload', function() {
				SCD.DebugLogger.flush( true );
			} );
		},

		/**
		 * Log a message to both console and file
		 *
		 * @param {string} level Log level (info, warn, error, debug)
		 * @param {string} category Category/component name
		 * @param {string} message Log message
		 * @param {*} data Optional data to log
		 */
		log: function( level, category, message, data ) {
			// Always log to console
			var consoleMessage = '[' + category + '] ' + message;
			if ( data !== undefined ) {
				console[ level ]( consoleMessage, data );
			} else {
				console[ level ]( consoleMessage );
			}

			// Add to queue for server-side logging
			this.logQueue.push( {
				timestamp: new Date().toISOString(),
				level: level,
				category: category,
				message: message,
				data: data !== undefined ? this.serializeData( data ) : null
			} );

			// Schedule flush
			this.scheduleFlush();
		},

		/**
		 * Serialize data for logging
		 */
		serializeData: function( data ) {
			try {
				if ( typeof data === 'string' ) {
					return data;
				}
				return JSON.stringify( data, null, 2 );
			} catch ( e ) {
				return String( data );
			}
		},

		/**
		 * Schedule a flush of the log queue
		 */
		scheduleFlush: function() {
			// Clear existing timeout
			if ( this.flushTimeout ) {
				clearTimeout( this.flushTimeout );
			}

			// Flush after 1 second of inactivity or when queue gets large
			if ( this.logQueue.length >= 10 ) {
				this.flush();
			} else {
				this.flushTimeout = setTimeout( function() {
					SCD.DebugLogger.flush();
				}, 1000 );
			}
		},

		/**
		 * Flush log queue to server
		 *
		 * @param {boolean} sync Use synchronous request (for page unload)
		 */
		flush: function( sync ) {
			if ( this.logQueue.length === 0 ) {
				return;
			}

			var logs = this.logQueue.slice(); // Copy queue
			this.logQueue = []; // Clear queue

			// Send to server
			$.ajax( {
				url: window.scdWizardData && window.scdWizardData.ajax_url ? window.scdWizardData.ajax_url : window.ajaxurl,
				type: 'POST',
				async: ! sync,
				data: {
					action: 'scd_ajax',
				scdAction: 'writeDebugLog',
					nonce: window.scdWizardData && window.scdWizardData.nonce ? window.scdWizardData.nonce : '',
					logs: JSON.stringify( logs )
				}
			} );
		}
	};

	// Initialize on document ready
	$( document ).ready( function() {
		SCD.DebugLogger.init();
	} );

} )( jQuery );
