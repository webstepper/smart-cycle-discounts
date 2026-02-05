/**
 * Base Api
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/base-api.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Shared = WSSCD.Shared || {};

	/**
	 * Base API Class
	 *
	 * @class WSSCD.Shared.BaseAPI
	 * @param {object} config - Configuration options
	 */
	WSSCD.Shared.BaseAPI = function( config ) {
		config = config || {};

		this.config = $.extend( {}, this.getDefaultConfig(), config );
		this._pendingRequests = {};
	};

	WSSCD.Shared.BaseAPI.prototype = {
		/**
		 * Get default configuration
		 *
		 * @returns {object} Default config
		 */
		getDefaultConfig: function() {
			return {
				ajaxUrl: window.wsscdAjax && window.wsscdAjax.ajaxUrl || '',
				nonce: window.wsscdAjax && window.wsscdAjax.nonce || '',
				timeout: 30000,
				retryAttempts: 0
			};
		},

		/**
		 * Make AJAX request with automatic error handling
		 *
		 * This is the core method that all API methods should use.
		 * It provides consistent error handling, request tracking, and logging.
		 *
		 * @param {string} action - WordPress AJAX action name
		 * @param {object} data - Request data
		 * @param {object} options - Additional options
		 * @returns {jQuery.Promise} Promise that resolves with response data
		 */
		request: function( action, data, options ) {
			options = options || {};
			var self = this;

			// Build request key for deduplication
			var requestKey = action + ':' + JSON.stringify( data );

			// Return existing request if still pending
			if ( this._pendingRequests[requestKey] ) {
				return this._pendingRequests[requestKey];
			}

			var promise = WSSCD.Ajax.post( action, data, {
				timeout: options.timeout || this.config.timeout
			} ).fail( function( xhr, textStatus, errorThrown ) {
				// Automatic error handling using centralized ErrorHandler
				if ( window.WSSCD && window.WSSCD.ErrorHandler ) {
					window.WSSCD.ErrorHandler.handleAjaxError( xhr, action, {
						data: data,
						status: textStatus,
						error: errorThrown
					} );
				} else {
					// Fallback if ErrorHandler not available
					console.error( '[BaseAPI] AJAX Error:', {
						action: action,
						status: textStatus,
						error: errorThrown,
						response: xhr.responseText
					} );
				}
			} ).always( function() {
				// Clean up pending request tracking
				delete self._pendingRequests[requestKey];
			} );

			this._pendingRequests[requestKey] = promise;

			return promise;
		},

		/**
		 * Make GET request
		 *
		 * @param {string} action - WordPress AJAX action
		 * @param {object} data - Request data
		 * @param {object} options - Additional options
		 * @returns {jQuery.Promise}
		 */
		get: function( action, data, options ) {
			return this.request( action, data, options );
		},

		/**
		 * Make POST request
		 *
		 * @param {string} action - WordPress AJAX action
		 * @param {object} data - Request data
		 * @param {object} options - Additional options
		 * @returns {jQuery.Promise}
		 */
		post: function( action, data, options ) {
			return this.request( action, data, options );
		},

		/**
		 * Cancel all pending requests
		 */
		cancelPendingRequests: function() {
			for ( var key in this._pendingRequests ) {
				if ( Object.prototype.hasOwnProperty.call( this._pendingRequests, key ) ) {
					var request = this._pendingRequests[key];
					if ( request && 'function' === typeof request.abort ) {
						request.abort();
					}
				}
			}
			this._pendingRequests = {};
		},

		/**
		 * Check if API is ready
		 *
		 * @returns {boolean} True if ready
		 */
		isReady: function() {
			return !! ( window.WSSCD && window.WSSCD.Ajax );
		},

		/**
		 * Cleanup and destroy
		 */
		destroy: function() {
			this.cancelPendingRequests();
			this.config = null;
			this._pendingRequests = null;
		}
	};

	/**
	 * Factory method to create an API class that extends BaseAPI
	 *
	 * @param {string} stepName - Name of the step (e.g., 'basic', 'products')
	 * @param {object} customMethods - Custom API methods for the step
	 * @returns {Function} API constructor function
	 */
	WSSCD.Shared.BaseAPI.createAPI = function( stepName, customMethods ) {
		if ( !stepName || 'string' !== typeof stepName ) {
			throw new Error( 'Step name must be a non-empty string' );
		}

		customMethods = customMethods || {};

		var APIClass = function( config ) {
			// Call parent constructor
			WSSCD.Shared.BaseAPI.call( this, config );

			this.stepName = stepName;
		};

		APIClass.prototype = Object.create( WSSCD.Shared.BaseAPI.prototype );
		APIClass.prototype.constructor = APIClass;

		$.extend( APIClass.prototype, customMethods );

		if ( !customMethods.saveStepData ) {
			APIClass.prototype.saveStepData = function( data ) {
				return this.request( 'wsscd_save_step', {
					step: this.stepName,
					data: data
				} );
			};
		}

		return APIClass;
	};

} )( jQuery );
