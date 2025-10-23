/**
 * Base API Module
 *
 * Foundation for all wizard step API services.
 * Provides automatic error handling and consistent AJAX patterns.
 *
 * Features:
 * - Automatic error handling for AJAX requests
 * - Request deduplication
 * - Consistent response format
 * - Error logging and reporting
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * Base API Class
	 *
	 * @class SCD.Shared.BaseAPI
	 * @param {object} config - Configuration options
	 */
	SCD.Shared.BaseAPI = function( config ) {
		config = config || {};

		this.config = $.extend( {}, this.getDefaultConfig(), config );
		this._pendingRequests = {};
	};

	SCD.Shared.BaseAPI.prototype = {
		/**
		 * Get default configuration
		 *
		 * @returns {object} Default config
		 */
		getDefaultConfig: function() {
			return {
				ajaxUrl: window.scdAjax && window.scdAjax.ajaxUrl || '',
				nonce: window.scdAjax && window.scdAjax.nonce || '',
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

			// Create and store request promise
			var promise = SCD.Ajax.post( action, data, {
				timeout: options.timeout || this.config.timeout
			} ).fail( function( xhr, textStatus, errorThrown ) {
				// Automatic error handling using centralized ErrorHandler
				if ( window.SCD && window.SCD.ErrorHandler ) {
					window.SCD.ErrorHandler.handleAjaxError( xhr, action, {
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

			// Store as pending
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
			return !! ( window.SCD && window.SCD.Ajax );
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
	SCD.Shared.BaseAPI.createAPI = function( stepName, customMethods ) {
		// Validate parameters
		if ( !stepName || 'string' !== typeof stepName ) {
			throw new Error( 'Step name must be a non-empty string' );
		}

		customMethods = customMethods || {};

		// Create constructor function
		var APIClass = function( config ) {
			// Call parent constructor
			SCD.Shared.BaseAPI.call( this, config );

			// Initialize step-specific properties
			this.stepName = stepName;
		};

		// Set up proper prototype chain
		APIClass.prototype = Object.create( SCD.Shared.BaseAPI.prototype );
		APIClass.prototype.constructor = APIClass;

		// Add custom methods to prototype
		$.extend( APIClass.prototype, customMethods );

		// Add convenience method for saving step data
		if ( !customMethods.saveStepData ) {
			APIClass.prototype.saveStepData = function( data ) {
				return this.request( 'scd_save_step', {
					step: this.stepName,
					data: data
				} );
			};
		}

		return APIClass;
	};

} )( jQuery );
