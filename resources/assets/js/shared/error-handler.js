/**
 * Error Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/error-handler.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};

	/**
	 * Error Handler Service
	 *
	 * @class SCD.ErrorHandler
	 */
	SCD.ErrorHandler = {

		/**
		 * Error severity levels
		 */
		SEVERITY: {
			LOW: 'low',
			MEDIUM: 'medium',
			HIGH: 'high',
			CRITICAL: 'critical'
		},

		/**
		 * Error log storage
		 */
		errorLog: [],

		/**
		 * Maximum number of errors to store
		 */
		maxLogSize: 100,

		/**
		 * Handle an error
		 *
		 * @param {Error|string} error - Error object or message
		 * @param {string} context - Context where error occurred
		 * @param {string} severity - Error severity level
		 * @param {object} metadata - Additional error metadata
		 */
		handle: function( error, context, severity, metadata ) {
			var errorData = this._normalizeError( error, context, severity, metadata );

			this._logError( errorData );

			// Console logging based on severity
			this._consoleLog( errorData );

			// Trigger global error event
			this._triggerErrorEvent( errorData );

			if ( errorData.severity === this.SEVERITY.HIGH || errorData.severity === this.SEVERITY.CRITICAL ) {
				this._showUserNotification( errorData );
			}

			// Send to analytics if available (non-critical errors only)
			if ( errorData.severity !== this.SEVERITY.CRITICAL ) {
				this._sendToAnalytics( errorData );
			}
		},

		/**
		 * Handle AJAX errors specifically
		 *
		 * @param {object} xhr - XMLHttpRequest object
		 * @param {string} action - AJAX action name
		 * @param {object} requestData - Original request data
		 */
		handleAjaxError: function( xhr, action, requestData ) {
			var errorMessage = 'AJAX request failed';
			var severity = this.SEVERITY.MEDIUM;

			// Extract more specific error information
			if ( xhr.responseJSON && xhr.responseJSON.data ) {
				errorMessage = xhr.responseJSON.data;
			} else if ( xhr.statusText ) {
				errorMessage = 'AJAX Error: ' + xhr.statusText;
			}

			// Determine severity based on status code
			if ( 500 <= xhr.status ) {
				severity = this.SEVERITY.HIGH;
			} else if ( 403 === xhr.status || 401 === xhr.status ) {
				severity = this.SEVERITY.MEDIUM;
			}

			this.handle(
				new Error( errorMessage ),
				'AJAX: ' + action,
				severity,
				{
					status: xhr.status,
					statusText: xhr.statusText,
					action: action,
					requestData: requestData
				}
			);
		},

		/**
		 * Get error log
		 *
		 * @param {number} limit - Maximum number of errors to return
		 * @returns {Array} Array of error objects
		 */
		getErrorLog: function( limit ) {
			if ( limit && 0 < limit ) {
				return this.errorLog.slice( -limit );
			}
			return this.errorLog.slice();
		},

		/**
		 * Clear error log
		 */
		clearErrorLog: function() {
			this.errorLog = [];
		},

		/**
		 * Normalize error data
		 *
		 * @private
		 * @param {Error|string} error - Error object or message
		 * @param {string} context - Error context
		 * @param {string} severity - Error severity
		 * @param {object} metadata - Additional metadata
		 * @returns {object} Normalized error object
		 */
		_normalizeError: function( error, context, severity, metadata ) {
			var message = '';
			var stack = '';

			if ( error instanceof Error ) {
				message = error.message;
				stack = error.stack || '';
			} else if ( 'string' === typeof error ) {
				message = error;
			} else {
				message = 'Unknown error';
			}

			return {
				message: message,
				stack: stack,
				context: context || 'Unknown',
				severity: severity || this.SEVERITY.MEDIUM,
				timestamp: new Date(),
				metadata: metadata || {},
				userAgent: navigator.userAgent,
				url: window.location.href
			};
		},

		/**
		 * Log error to internal storage
		 *
		 * @private
		 * @param {object} errorData - Normalized error data
		 */
		_logError: function( errorData ) {
			this.errorLog.push( errorData );

			// Maintain log size limit
			if ( this.errorLog.length > this.maxLogSize ) {
				this.errorLog = this.errorLog.slice( -this.maxLogSize );
			}
		},

		/**
		 * Log error to console
		 *
		 * @private
		 * @param {object} errorData - Normalized error data
		 */
		_consoleLog: function( errorData ) {
			var logMethod = 'log';
			var prefix = '[SCD]';

			// Choose appropriate console method
			if ( errorData.severity === this.SEVERITY.CRITICAL ) {
				logMethod = 'error';
				prefix = '[SCD ERROR]';
			} else if ( errorData.severity === this.SEVERITY.HIGH ) {
				logMethod = 'warn';
				prefix = '[SCD WARNING]';
			}

			// Only log in debug mode or for critical errors
			var safeConsole = console[logMethod] && typeof console[logMethod] === 'function' ? console[logMethod] : console.log;

			if ( window.scdWizardData && window.scdWizardData.debug ) {
				safeConsole.call( console, prefix, '[' + errorData.context + ']', errorData.message );
				if ( errorData.stack ) {
					safeConsole.call( console, errorData.stack );
				}
				if ( errorData.metadata && ( !SCD.Utils || !SCD.Utils.isEmpty || !SCD.Utils.isEmpty( errorData.metadata ) ) ) {
					safeConsole.call( console, 'Metadata:', errorData.metadata );
				}
			} else if ( errorData.severity === this.SEVERITY.CRITICAL ) {
				safeConsole.call( console, prefix, errorData.message );
			}
		},

		/**
		 * Trigger global error event
		 *
		 * @private
		 * @param {object} errorData - Normalized error data
		 */
		_triggerErrorEvent: function( errorData ) {
			$( document ).trigger( 'scd:error', [ errorData ] );
		},

		/**
		 * Show user notification
		 *
		 * @private
		 * @param {object} errorData - Normalized error data
		 */
		_showUserNotification: function( errorData ) {
			// Always use NotificationService from Shared namespace
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.error( errorData.message );
				return;
			}

			// Fallback to console for critical errors
			if ( errorData.severity === this.SEVERITY.CRITICAL ) {
				console.error( 'Critical Error:', errorData.message );
			}
		},

		/**
		 * Send error to analytics (if available and enabled)
		 *
		 * @private
		 * @param {object} errorData - Normalized error data
		 */
		_sendToAnalytics: function( errorData ) {
			// Only send analytics in production and if user has opted in
			if ( window.scdWizardData &&
				window.scdWizardData.analytics &&
				window.scdWizardData.analytics.enabled &&
				window.SCD &&
				window.SCD.Analytics ) {

				try {
					SCD.Analytics.trackError( {
						message: errorData.message,
						context: errorData.context,
						severity: errorData.severity,
						timestamp: errorData.timestamp
					} );
				} catch ( analyticsError ) {
					// Don't let analytics errors cause more errors
				}
			}
		}
	};

	$( document ).ready( function() {
		window.onerror = function( message, source, lineno, colno, error ) {
			SCD.ErrorHandler.handle(
				error || new Error( message ),
				'Global',
				SCD.ErrorHandler.SEVERITY.HIGH,
				{
					source: source,
					line: lineno,
					column: colno
				}
			);
		};
	} );

} )( jQuery );