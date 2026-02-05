/**
 * Debug Logger
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/debug-logger.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.WSSCD = window.WSSCD || {};

	/**
	 * Debug Logger
	 */
	WSSCD.DebugLogger = {
		/**
		 * Log queue for batching requests
		 */
		logQueue: [],

		/**
		 * Flush timeout ID
		 */
		flushTimeout: null,

		/**
		 * Check if debug mode is enabled
		 *
		 * @returns {boolean} True if debug mode is enabled
		 */
		isDebugEnabled: function() {
			return ( window.wsscdWizardData && window.wsscdWizardData.debug ) ||
				   ( window.wsscdAdmin && window.wsscdAdmin.debug );
		},

		/**
		 * Initialize logger
		 */
		init: function() {
			// Auto-flush on page unload (only if debug enabled)
			if ( this.isDebugEnabled() ) {
				$( window ).on( 'beforeunload', function() {
					WSSCD.DebugLogger.flush( true );
				} );
			}
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
			// Only log in debug mode
			if ( ! this.isDebugEnabled() ) {
				return;
			}

			// Log to console in debug mode
			var consoleMessage = '[' + category + '] ' + message;
			// Fallback to console.log if specific method unavailable
			var consoleMethod = console[ level ] && typeof console[ level ] === 'function' ? console[ level ] : console.log;

			if ( data !== undefined ) {
				consoleMethod.call( console, consoleMessage, data );
			} else {
				consoleMethod.call( console, consoleMessage );
			}

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
			if ( this.flushTimeout ) {
				clearTimeout( this.flushTimeout );
			}

			// Flush after 1 second of inactivity or when queue gets large
			if ( this.logQueue.length >= 10 ) {
				this.flush();
			} else {
				this.flushTimeout = setTimeout( function() {
					WSSCD.DebugLogger.flush();
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
				url: window.wsscdWizardData && window.wsscdWizardData.ajaxUrl ? window.wsscdWizardData.ajaxUrl : window.ajaxurl,
				type: 'POST',
				async: ! sync,
				data: {
					action: 'wsscd_ajax',
				wsscdAction: 'writeDebugLog',
					nonce: window.wsscdWizardData && window.wsscdWizardData.nonce ? window.wsscdWizardData.nonce : '',
					logs: JSON.stringify( logs )
				}
			} );
		}
	};

	$( document ).ready( function() {
		WSSCD.DebugLogger.init();
	} );

} )( jQuery );
