/**
 * Console Logger
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/console-logger.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function() {
	'use strict';

	/**
	 * Check if debug mode is enabled
	 *
	 * @returns {boolean} True if debug mode is enabled
	 */
	function isDebugEnabled() {
		return ( window.wsscdWizardData && window.wsscdWizardData.debug ) ||
			   ( window.wsscdAdmin && window.wsscdAdmin.debug );
	}

	// Only intercept console methods in debug mode
	// In production, leave console methods untouched
	if ( ! isDebugEnabled() ) {
		return;
	}

	var originalLog = console.log;
	var originalWarn = console.warn;
	var originalError = console.error;

	var logBuffer = [];
	var sendTimeout = null;

	/**
	 * Send buffered logs to server
	 */
	function sendLogsToServer() {
		if ( logBuffer.length === 0 ) {
			return;
		}

		var logs = logBuffer.slice();
		logBuffer = [];

		if ( window.ajaxurl ) {
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', window.ajaxurl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.send(
				'action=wsscd_ajax' +
				'&wsscdAction=wsscd_log_console' +
				'&logs=' + encodeURIComponent( JSON.stringify( logs ) )
			);
		}
	}

	/**
	 * Capture log message
	 */
	function captureLog( level, args ) {
		var message = Array.prototype.slice.call( args ).map( function( arg ) {
			if ( typeof arg === 'object' ) {
				try {
					return JSON.stringify( arg );
				} catch ( e ) {
					return String( arg );
				}
			}
			return String( arg );
		} ).join( ' ' );

		// Only capture WSSCD-related logs and errors
		if ( message.indexOf( '[WSSCD' ) !== -1 ||
		     message.indexOf( '[Validation' ) !== -1 ||
		     message.indexOf( 'array is not defined' ) !== -1 ||
		     message.indexOf( 'ReferenceError' ) !== -1 ||
		     message.indexOf( 'Uncaught' ) !== -1 ) {

			logBuffer.push( {
				level: level,
				message: message,
				timestamp: new Date().toISOString()
			} );

			clearTimeout( sendTimeout );
			sendTimeout = setTimeout( sendLogsToServer, 100 );
		}
	}

	// Override console methods (only in debug mode)
	console.log = function() {
		originalLog.apply( console, arguments );
		captureLog( 'log', arguments );
	};

	console.warn = function() {
		originalWarn.apply( console, arguments );
		captureLog( 'warn', arguments );
	};

	console.error = function() {
		originalError.apply( console, arguments );
		captureLog( 'error', arguments );
	};

	// Capture unhandled errors (only in debug mode)
	window.addEventListener( 'error', function( event ) {
		captureLog( 'error', [ 'Uncaught error:', event.message, 'at', event.filename + ':' + event.lineno ] );
	} );

} )();
