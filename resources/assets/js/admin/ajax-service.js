/**
 * Ajax Service
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/ajax-service.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * AJAX Service
	 */
	SCD.Shared.AjaxService = {
		/**
		 * Configuration
		 */
		config: {
			url: window.ajaxurl ||
                 ( window.scdWizardData && window.scdWizardData.ajax_url ) ||
                 ( window.scdAdmin && window.scdAdmin.ajax_url ) ||
                 '/wp-admin/admin-ajax.php',
			timeout: 30000
		},

		/**
		 * Rate limiting configuration
		 * Server limit: 60 requests per 60 seconds
		 * Client safety buffer: 40 requests per 60 seconds (67% of limit - with buffer for rate limit inheritance)
		 */
		rateLimitConfig: {
			maxRequests: 40,        // Maximum requests allowed in time window (generous limit with server at 60)
			maxConcurrent: 5,       // Maximum simultaneous requests (concurrency limit)
			timeWindow: 60000,      // Time window in milliseconds (60 seconds)
			retryDelay: 1000,       // Default retry delay for queue processing (1 second)
			debug: false            // Debug logging disabled for production
		},

		/**
		 * Rate limiting state
		 */
		rateLimitState: {
			requestTimestamps: [],  // Timestamps of recent requests
			pendingRequests: 0,     // Count of in-flight requests (prevents race condition)
			queue: [],              // Queued requests waiting for rate limit slot
			paused: false,          // Whether queue processing is paused (for 429 errors)
			pausedUntil: 0,         // Timestamp when pause expires
			processing: false       // Whether queue is currently being processed
		},

		/**
		 * Active requests
		 */
		activeRequests: {},

		/**
		 * Initialize service
		 */
		init: function() {
			// Setup global AJAX handlers
			this.setupGlobalHandlers();

			// Start queue processor
			this.startQueueProcessor();
		},

		/**
		 * Check if we can make a request without exceeding rate limit
		 *
		 * @returns {boolean} True if request can be made
		 */
		canMakeRequest: function() {
			this.cleanupOldTimestamps();

			var now = Date.now();
			var timeWindow = this.rateLimitConfig.timeWindow;
			var maxRequests = this.rateLimitConfig.maxRequests;

			// Count requests in current time window
			var recentRequests = this.rateLimitState.requestTimestamps.filter( function( timestamp ) {
				return ( now - timestamp ) < timeWindow;
			} );

			// Include pending (in-flight) requests to prevent race condition
			// Multiple simultaneous requests would all see the same count otherwise
			var totalLoad = recentRequests.length + this.rateLimitState.pendingRequests;
			var canMake = totalLoad < maxRequests;

			// Apply concurrency limit (max simultaneous requests)
			var maxConcurrent = this.rateLimitConfig.maxConcurrent;
			var concurrencyOK = this.rateLimitState.pendingRequests < maxConcurrent;
			canMake = canMake && concurrencyOK;


			return canMake;
		},

		/**
		 * Clean up old request timestamps outside the time window
		 */
		cleanupOldTimestamps: function() {
			var now = Date.now();
			var timeWindow = this.rateLimitConfig.timeWindow;

			this.rateLimitState.requestTimestamps = this.rateLimitState.requestTimestamps.filter( function( timestamp ) {
				return ( now - timestamp ) < timeWindow;
			} );
		},

		/**
		 * Record a request timestamp
		 */
		recordRequest: function() {
			this.rateLimitState.requestTimestamps.push( Date.now() );

			if ( this.rateLimitConfig.debug ) {
			}
		},

		/**
		 * Add request to queue
		 *
		 * @param {string} method HTTP method
		 * @param {string} action WordPress action
		 * @param {object} data Request data
		 * @param {object} config Request config
		 * @param {jQuery.Deferred} deferred Deferred to resolve when request completes
		 * @param {string} requestKey Request key for deduplication
		 */
		addToQueue: function( method, action, data, config, deferred, requestKey ) {
			this.rateLimitState.queue.push( {
				method: method,
				action: action,
				data: data,
				config: config,
				deferred: deferred,
				requestKey: requestKey,
				timestamp: Date.now()
			} );

			if ( this.rateLimitConfig.debug ) {
			}

			// Trigger queue processing
			this.processQueue();
		},

		/**
		 * Process queued requests
		 */
		processQueue: function() {
			var self = this;

			// Prevent concurrent processing
			if ( this.rateLimitState.processing ) {
				return;
			}

			if ( this.rateLimitState.paused ) {
				var now = Date.now();
				if ( now < this.rateLimitState.pausedUntil ) {
					// Still paused - schedule retry
					var remainingPause = this.rateLimitState.pausedUntil - now;
					if ( this.rateLimitConfig.debug ) {
					}
					return;
				}

				// Pause expired - resume
				this.rateLimitState.paused = false;
				this.rateLimitState.pausedUntil = 0;
				if ( this.rateLimitConfig.debug ) {
				}
			}

			// Process queue
			this.rateLimitState.processing = true;

			// Process all available slots
			while ( this.rateLimitState.queue.length > 0 ) {
				// Reserve a slot BEFORE checking (prevents race condition)
				this.rateLimitState.pendingRequests++;

				if ( ! this.canMakeRequest() ) {
					// Can't execute - release the slot and stop processing
					this.rateLimitState.pendingRequests--;
					break;
				}

				var queuedRequest = this.rateLimitState.queue.shift();

				if ( this.rateLimitConfig.debug ) {
				}

				// Execute the request (slot already reserved)
				this.executeRequest(
					queuedRequest.method,
					queuedRequest.data,
					queuedRequest.config,
					queuedRequest.deferred,
					queuedRequest.requestKey
				);
			}

			this.rateLimitState.processing = false;

			// If queue still has items, schedule next processing
			if ( this.rateLimitState.queue.length > 0 ) {
				setTimeout( function() {
					self.processQueue();
				}, this.rateLimitConfig.retryDelay );
			}
		},

		/**
		 * Start queue processor (runs periodically)
		 */
		startQueueProcessor: function() {
			var self = this;

			// Process queue every second
			setInterval( function() {
				if ( self.rateLimitState.queue.length > 0 ) {
					self.processQueue();
				}
			}, this.rateLimitConfig.retryDelay );
		},

		/**
		 * Handle 429 rate limit error
		 *
		 * @param {object} error Error object
		 */
		handleRateLimitError: function( error ) {
			// Extract retry_after from error response
			var retryAfter = 30; // Default 30 seconds

			if ( error.response && error.response.error && Array.isArray( error.response.error ) ) {
				var errorObj = error.response.error[0];
				if ( errorObj && errorObj.data && errorObj.data.retryAfter ) {
					retryAfter = errorObj.data.retryAfter;
				}
			}

			// Pause queue processing
			this.rateLimitState.paused = true;
			this.rateLimitState.pausedUntil = Date.now() + ( retryAfter * 1000 );

			if ( this.rateLimitConfig.debug || window.console ) {
				console.warn( '[AjaxService] Rate limit exceeded. Pausing queue for', retryAfter, 'seconds' );
			}
		},

		/**
		 * Make POST request
		 *
		 * @param {string} action WordPress action
		 * @param {object} data Request data
		 * @param {object} options Request options
		 * @returns {Promise} Request promise
		 */
		post: function( action, data, options ) {
			return this.request( 'POST', action, data || {}, options || {} );
		},

		/**
		 * Make GET request
		 *
		 * @param {string} action WordPress action
		 * @param {object} data Request data
		 * @param {object} options Request options
		 * @returns {Promise} Request promise
		 */
		get: function( action, data, options ) {
			return this.request( 'GET', action, data || {}, options || {} );
		},

		/**
		 * Make AJAX request
		 *
		 * @param {string} method HTTP method
		 * @param {string} action WordPress action
		 * @param {object} data Request data
		 * @param {object} options Request options
		 * @returns {Promise} Request promise
		 */
		request: function( method, action, data, options ) {
			data = data || {};
			options = options || {};
			var config = $.extend( {}, this.config, options );

			// Generate request key for deduplication
			var requestKey = this.generateRequestKey( action, data );

			if ( this.activeRequests[requestKey] ) {
				if ( this.rateLimitConfig.debug ) {
				}
				// Return existing promise instead of making duplicate request
				return this.activeRequests[requestKey].deferred.promise();
			}

			// Cancel previous search requests (for search-type actions)
			// Pass data so we can check for non-cancellable operations like get_products_by_ids
			this.cancelPreviousSearchRequest( action, data );

			// Build request data for unified endpoint
			var nonce = this.getNonce( action );

			var requestData = $.extend( {
				action: 'scd_ajax',          // Always use unified endpoint
				scdAction: action,           // Router converts camelCase to snake_case
				nonce: nonce
			}, data );

			if ( ! config.headers ) {
				config.headers = {};
			}
			if ( ! config.headers['X-Idempotency-Key'] && ! config.skipIdempotency ) {
				config.headers['X-Idempotency-Key'] = this.generateIdempotencyKey( action, data );
			}

			var requestDeferred = $.Deferred();

			this.activeRequests[requestKey] = {
				action: action,
				deferred: requestDeferred,
				xhr: null  // Will be set in executeRequest
			};

			// Reserve a slot BEFORE checking (prevents race condition)
			// Multiple simultaneous requests would all see the same count otherwise
			this.rateLimitState.pendingRequests++;

			if ( config.bypassRateLimit || this.canMakeRequest() ) {
				// Execute immediately (slot already reserved)
				this.executeRequest( method, requestData, config, requestDeferred, requestKey );
			} else {
				// Can't execute - release the reserved slot and queue instead
				this.rateLimitState.pendingRequests--;
				if ( this.rateLimitConfig.debug ) {
				}
				this.addToQueue( method, action, requestData, config, requestDeferred, requestKey );
			}

			return requestDeferred.promise();
		},

		/**
		 * Execute AJAX request (called directly or from queue)
		 *
		 * @private
		 * @param {string} method HTTP method
		 * @param {object} data Request data
		 * @param {object} config Configuration
		 * @param {jQuery.Deferred} deferred Deferred object
		 * @param {string} requestKey Request key for deduplication
		 * @returns {jQuery.Deferred} Request deferred
		 */
		executeRequest: function( method, data, config, deferred, requestKey ) {
			var self = this;

			// Record request timestamp for rate limiting
			// NOTE: pendingRequests already incremented by caller (request() or processQueue())
			this.recordRequest();

			if ( config.onProgress ) {
				config.onProgress( {
					loaded: 0,
					total: 100,
					percent: 0
				} );
			}

			var xhr = $.ajax( {
				url: config.url,
				type: method,
				data: data,
				timeout: config.timeout,
				dataType: 'json',

				xhr: function() {
					var xhrInstance = new window.XMLHttpRequest();

					// Store XHR in activeRequests for cancellation
					if ( requestKey && self.activeRequests[requestKey] ) {
						self.activeRequests[requestKey].xhr = xhrInstance;
					}

					var xhr = xhrInstance;

					// Upload progress
					if ( config.onProgress ) {
						xhr.upload.addEventListener( 'progress', function( evt ) {
							if ( evt.lengthComputable ) {
								config.onProgress( {
									loaded: evt.loaded,
									total: evt.total,
									percent: ( evt.loaded / evt.total ) * 100
								} );
							}
						} );
					}

					return xhr;
				},

				success: function( response ) {
					// Decrement pending requests counter
					self.rateLimitState.pendingRequests--;

					// Clean up activeRequests
					if ( requestKey && self.activeRequests[requestKey] ) {
						delete self.activeRequests[requestKey];
					}

					// Check WordPress-style response
					if ( false === response.success ) {
						// Log the error details
						if ( window.console && window.console.error ) {
							console.error( '[AjaxService] Server returned error:', response );
							if ( response.error ) {
								if ( Array.isArray( response.error ) ) {
									console.error( '[AjaxService] Error array:', response.error );
									// Log each error message in the array
									response.error.forEach( function( err, index ) {
										console.error( '[AjaxService] Error [' + index + ']:', err );
										if ( err && 'object' === typeof err ) {
											console.error( '[AjaxService] Error object details:', JSON.stringify( err, null, 2 ) );
										}
									} );
								} else if ( 'object' === typeof response.error ) {
									console.error( '[AjaxService] Error object:', response.error );
									console.error( '[AjaxService] Error details:', JSON.stringify( response.error, null, 2 ) );
								} else {
									console.error( '[AjaxService] Error string:', response.error );
								}
							}
						}

						// Extract error message - handle both standard WP format and this custom format
						var errorMessage = 'Request failed';
						var errorCode = 'unknown_error';

						// Standard WordPress format
						if ( response.data && response.data.message ) {
							errorMessage = response.data.message;
							errorCode = response.data.code || 'unknown_error';
						}
						// Custom format with error array
						else if ( response.error && Array.isArray( response.error ) && 0 < response.error.length ) {
							var firstError = response.error[0];
							if ( firstError && 'object' === typeof firstError ) {
								errorMessage = firstError.message || 'Request failed';
								errorCode = firstError.code || 'server_error';
							} else {
								errorMessage = firstError;
								errorCode = 'server_error';
							}
						}
						// Another custom format
						else if ( response.error && 'string' === typeof response.error ) {
							errorMessage = response.error;
							errorCode = 'server_error';
						}

						var error = new Error( errorMessage );
						error.code = errorCode;
						error.response = response;
						error.responseJSON = response;

						// Handle rate limit errors (429)
						if ( 'rate_limit_exceeded' === errorCode ) {
							self.handleRateLimitError( error );
						}

						deferred.reject( error );
					} else {
						deferred.resolve( response.data || response );
					}
				},

				error: function( jqXHR, textStatus, errorThrown ) {
					// Decrement pending requests counter
					self.rateLimitState.pendingRequests--;

					// Clean up activeRequests
					if ( requestKey && self.activeRequests[requestKey] ) {
						delete self.activeRequests[requestKey];
					}

					// Skip error logging for aborted requests (intentional cancellations)
					if ( 'abort' === textStatus ) {
						var error = new Error( 'Request cancelled' );
						error.code = 'request_cancelled';
						error.status = 0;
						deferred.reject( error );
						return;
					}

					// Handle status 0 (network error or request cancellation)
					// Status 0 with 'error' textStatus often means cancelled/aborted request
					if ( 0 === jqXHR.status && 'error' === textStatus ) {
						// This is likely a cancelled request from debouncing or navigation
						// Log as info, not error
						if ( window.console && window.console.info ) {
							console.info( '[AjaxService] Request cancelled (debouncing or navigation):', {
								action: data.scdAction || data.action,
								textStatus: textStatus
							} );
						}
						var error = new Error( 'Request cancelled' );
						error.code = 'request_cancelled';
						error.status = 0;
						deferred.reject( error );
						return;
					}

					// Debug logging for actual errors
					if ( window.console && window.console.error ) {
						console.error( '[AjaxService] Request failed:', {
							status: jqXHR.status,
							statusText: jqXHR.statusText,
							textStatus: textStatus,
							errorThrown: errorThrown,
							responseText: jqXHR.responseText,
							requestData: data,
							url: config.url
						} );

						// Special handling for 500 errors
						if ( 500 === jqXHR.status ) {
							console.error( '[AjaxService] 500 Internal Server Error detected!' );
							console.error( '[AjaxService] Full response text:', jqXHR.responseText );
							// Try to extract error message from HTML
							if ( jqXHR.responseText ) {
								var match = jqXHR.responseText.match( /Fatal error.*?on line \d+/i );
								if ( match ) {
									console.error( '[AjaxService] PHP Fatal Error:', match[0] );
								}
								match = jqXHR.responseText.match( /Uncaught.*?on line \d+/i );
								if ( match ) {
									console.error( '[AjaxService] PHP Uncaught Error:', match[0] );
								}
							}
						}
					}

					// Build error object
					var error = new Error( errorThrown || textStatus || 'Request failed' );
					error.code = 'ajax_error';
					error.status = jqXHR.status;
					error.statusText = jqXHR.statusText;
					error.responseText = jqXHR.responseText;

					// Try to parse error response if it's JSON
					if ( jqXHR.responseText && '{' === jqXHR.responseText.charAt( 0 ) ) {
						try {
							// Only parse if it looks like JSON
							error.response = JSON.parse( jqXHR.responseText );
						} catch ( e ) {
							// If parse fails, keep raw text
							error.response = jqXHR.responseText;
						}
					} else {
						// Keep raw response text if not JSON
						error.response = jqXHR.responseText;
					}

					deferred.reject( error );
				}
			} );

			// Store XHR for cancellation
			if ( config.onCancel ) {
				config.onCancel( function() {
					xhr.abort();
				} );
			}

			return deferred;
		},

		/**
		 * Get nonce for action
		 *
		 * @param {string} action Action name
		 * @returns {string} Nonce value
		 */
		getNonce: function( action ) {
			var noncesObj = window.scdWizardData && window.scdWizardData.nonces;
			if ( noncesObj && noncesObj.action_map && noncesObj.action_map[action] ) {
				var nonceName = noncesObj.action_map[action];
				if ( noncesObj[nonceName] ) {
					return noncesObj[nonceName];
				}
			}

			// Fallback: try direct nonce properties
			if ( window.scdWizardData ) {
				if ( window.scdWizardData.nonce ) {
					return window.scdWizardData.nonce;
				}
				if ( window.scdWizardData.wizard_nonce ) {
					return window.scdWizardData.wizard_nonce;
				}
			}

			// Try scdAdmin as another fallback
			if ( window.scdAdmin && window.scdAdmin.nonce ) {
				return window.scdAdmin.nonce;
			}

			// Return empty string if no nonce found - let server handle the error
			return '';
		},

		/**
		 * Generate idempotency key for request
		 *
		 * @param {string} action AJAX action
		 * @param {object} data Request data
		 * @returns {string} Idempotency key
		 */
		generateIdempotencyKey: function( action, data ) {
			var timestamp = Date.now();
			var random = Math.random().toString( 36 ).substr( 2, 9 );
			var dataStr = JSON.stringify( data || {} );

			var hash = 0;
			var i;
			for ( i = 0; i < dataStr.length; i++ ) {
				hash = ( ( hash << 5 ) - hash ) + dataStr.charCodeAt( i );
				hash = hash & hash; // Convert to 32bit integer
			}

			return 'scd_' + action + '_' + Math.abs( hash ) + '_' + timestamp + '_' + random;
		},

		/**
		 * Generate request key for deduplication
		 *
		 * @param {string} action Action name
		 * @param {object} data Request data
		 * @returns {string} Request key
		 */
		generateRequestKey: function( action, data ) {
			var dataStr = JSON.stringify( data || {} );
			var hash = 0;
			var i;
			for ( i = 0; i < dataStr.length; i++ ) {
				hash = ( ( hash << 5 ) - hash ) + dataStr.charCodeAt( i );
				hash = hash & hash;
			}
			return action + '_' + Math.abs( hash );
		},

		/**
		 * Cancel previous request with same action (for search-type actions)
		 *
		 * @param {string} action Action name
		 * @param {object} data Request data (to check for exceptions)
		 */
		cancelPreviousSearchRequest: function( action, data ) {
			// Only cancel for search-type actions
			var searchActions = [ 'scd_product_search', 'get_product_categories', 'search_categories' ];
			var isCancellable = searchActions.some( function( searchAction ) {
				return action.indexOf( searchAction ) !== -1;
			} );

			if ( ! isCancellable ) {
				return;
			}

			// EXCEPTION: Don't cancel product restoration requests (get_products_by_ids)
			// These are critical for maintaining Tom Select state and should not be interrupted by search requests
			if ( data && data.wizardAction === 'get_products_by_ids' ) {
				return;
			}

			// Find and cancel previous requests with same action prefix
			var self = this;
			Object.keys( this.activeRequests ).forEach( function( key ) {
				if ( key.indexOf( action ) === 0 ) {
					var request = self.activeRequests[key];
					if ( request && request.xhr && 'function' === typeof request.xhr.abort ) {
						if ( self.rateLimitConfig.debug ) {
						}
						request.xhr.abort();
						// Reject the deferred
						if ( request.deferred ) {
							request.deferred.reject( 'cancelled' );
						}
						// Clean up
						delete self.activeRequests[key];
						// Decrement pending counter
						self.rateLimitState.pendingRequests--;
					}
				}
			} );
		},

		/**
		 * Process WordPress AJAX response
		 *
		 * @param {object} response Raw AJAX response
		 * @returns {jQuery.Deferred} Promise with processed response
		 */
		processResponse: function( response ) {
			var deferred = $.Deferred();

			if ( ! response ) {
				deferred.reject( new Error( 'Empty response' ) );
				return deferred.promise();
			}

			// Handle WordPress standard response format
			if ( 'undefined' !== typeof response.success ) {
				if ( response.success ) {
					deferred.resolve( response.data );
				} else {
					var error = new Error( response.data || 'Request failed' );
					error.code = response.code || 'ajax_error';
					deferred.reject( error );
				}
			} else {
				// Assume successful response if no success field
				deferred.resolve( response );
			}

			return deferred.promise();
		},

		/**
		 * Cancel all active requests
		 */
		cancelAll: function() {
			// jQuery automatically handles cleanup
			this.activeRequests = {};
		},

		/**
		 * Setup global AJAX handlers
		 *
		 * @private
		 */
		setupGlobalHandlers: function() {
			// Global error handler
			$( document ).ajaxError( function( event, jqXHR, ajaxSettings, _thrownError ) {
				if ( ajaxSettings.url && -1 !== ajaxSettings.url.indexOf( 'admin-ajax.php' ) ) {
					// Error logging removed - handle errors at the request level
				}
			} );

			// Handle authentication errors properly
			$( document ).ajaxComplete( function( event, jqXHR ) {
				if ( 401 === jqXHR.status ) {
					$( document ).trigger( 'scd:session:expired' );
				}
			} );
		},

		/**
		 * Batch multiple requests
		 *
		 * @param {Array} requests Array of request configs
		 * @returns {Promise} Promise resolving to array of results
		 */
		batch: function( requests ) {
			var self = this;
			var deferreds = [];

			$.each( requests, function( index, req ) {
				deferreds.push( self.request( req.method || 'POST',
					req.action,
					req.data || {},
					req.options || {} ) );
			} );

			return $.when.apply( $, deferreds ).then( function() {
				// Convert arguments to array
				return Array.prototype.slice.call( arguments );
			} );
		},

		/**
		 * Upload file
		 *
		 * @param {string} action WordPress action
		 * @param {File} file File to upload
		 * @param {object} data Additional data
		 * @param {object} options Request options
		 * @returns {Promise} Upload promise
		 */
		upload: function( action, file, data, options ) {
			data = data || {};
			options = options || {};
			var formData = new FormData();

			formData.append( 'file', file );

			formData.append( 'action', 'scd_ajax' );      // Always use unified endpoint
			formData.append( 'scdAction', action );        // Router converts camelCase to snake_case
			formData.append( 'nonce', this.getNonce( action ) );

			$.each( data, function( key, value ) {
				formData.append( key, value );
			} );

			// Make request with FormData
			var self = this;
			var deferred = $.Deferred();

			$.ajax( {
				url: self.config.url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				timeout: options.timeout || 120000, // 2 minutes for uploads

				xhr: function() {
					var xhr = new window.XMLHttpRequest();

					// Upload progress
					if ( options.onProgress ) {
						xhr.upload.addEventListener( 'progress', function( evt ) {
							if ( evt.lengthComputable ) {
								options.onProgress( {
									loaded: evt.loaded,
									total: evt.total,
									percent: ( evt.loaded / evt.total ) * 100
								} );
							}
						} );
					}

					return xhr;
				},

				success: function( response ) {
					if ( false === response.success ) {
						deferred.reject( new Error( ( response.data && response.data.message ) || 'Upload failed' ) );
					} else {
						deferred.resolve( response.data || response );
					}
				},

				error: function( jqXHR, textStatus, errorThrown ) {
					deferred.reject( new Error( errorThrown || textStatus || 'Upload failed' ) );
				}
			} );

			return deferred.promise();
		}
	};

	SCD.Ajax = SCD.Shared.AjaxService;

	$( document ).ready( function() {
		SCD.Ajax.init();
	} );

} )( jQuery );