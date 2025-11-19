/**
 * Console Logger
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/console-logger.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function() {
	'use strict';

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
				'action=scd_ajax' +
				'&scdAction=scd_log_console' +
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

		// Only capture SCD-related logs and errors
		if ( message.indexOf( '[SCD' ) !== -1 ||
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

	// Override console methods
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

	// Capture unhandled errors
	window.addEventListener( 'error', function( event ) {
		captureLog( 'error', [ 'Uncaught error:', event.message, 'at', event.filename + ':' + event.lineno ] );
	} );

} )();
