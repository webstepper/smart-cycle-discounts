/**
 * Utils
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/utils.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	// Register global SCD.Utils namespace for centralized access
	SCD.Utils = SCD.Utils || {};

	/**
	 * Consolidated Utility Functions
	 */
	SCD.Shared.Utils = {
		/**
		 * Get validation constant from PHP localization
		 * 
		 * @param {string} path Dot-notation path (e.g., 'products.RANDOM_COUNT_MIN')
		 * @param {*} defaultValue Default value if not found
		 * @returns {*} Validation constant value
		 */
		getValidationConstant: function( path, defaultValue ) {
			if ( !window.scdValidationConstants || !window.scdValidationConstants.constants ) {
				return defaultValue;
			}
			
			var parts = path.split( '.' );
			var value = window.scdValidationConstants.constants;
			
			for ( var i = 0; i < parts.length; i++ ) {
				if ( value && Object.prototype.hasOwnProperty.call( value, parts[i] ) ) {
					value = value[parts[i]];
				} else {
					return defaultValue;
				}
			}
			
			return value !== undefined ? value : defaultValue;
		},
		
		/**
		 * Get validation message from PHP localization
		 * 
		 * @param {string} path Dot-notation path (e.g., 'products.random_count_required')
		 * @param {string} defaultMessage Default message if not found
		 * @returns {string} Validation message
		 */
		getValidationMessage: function( path, defaultMessage ) {
			if ( !window.scdValidationConstants || !window.scdValidationConstants.messages ) {
				return defaultMessage;
			}
			
			var parts = path.split( '.' );
			var value = window.scdValidationConstants.messages;
			
			for ( var i = 0; i < parts.length; i++ ) {
				if ( value && Object.prototype.hasOwnProperty.call( value, parts[i] ) ) {
					value = value[parts[i]];
				} else {
					return defaultMessage;
				}
			}
			
			return value || defaultMessage;
		},

		/**
		 * Debounce function - delays execution until after wait milliseconds
		 *
		 * @param {Function} func Function to debounce
		 * @param {number} wait Milliseconds to wait
		 * @param {boolean} immediate Execute on leading edge instead of trailing
		 * @returns {Function} Debounced function
		 */
		debounce: function( func, wait, immediate ) {
			var timeout;

			return function debounced() {
				var context = this;
				var args = arguments;

				var later = function() {
					timeout = null;
					if ( ! immediate ) {
						func.apply( context, args );
					}
				};

				var callNow = immediate && ! timeout;

				clearTimeout( timeout );
				timeout = setTimeout( later, wait );

				if ( callNow ) {
					func.apply( context, args );
				}
			};
		},

		/**
		 * Throttle function - ensures function is called at most once per wait period
		 *
		 * @param {Function} func Function to throttle
		 * @param {number} wait Milliseconds between calls
		 * @returns {Function} Throttled function
		 */
		throttle: function( func, wait ) {
			var inThrottle, lastFunc, lastRan;

			return function throttled() {
				var context = this;
				var args = arguments;

				if ( ! inThrottle ) {
					func.apply( context, args );
					lastRan = Date.now();
					inThrottle = true;
				} else {
					clearTimeout( lastFunc );
					lastFunc = setTimeout( function() {
						if ( ( Date.now() - lastRan ) >= wait ) {
							func.apply( context, args );
							lastRan = Date.now();
						}
					}, Math.max( wait - ( Date.now() - lastRan ), 0 ) );
				}
			};
		},

		/**
		 * Deep clone an object ( handles arrays and nested objects )
		 *
		 * @param {*} obj Object to clone
		 * @returns {*} Cloned object
		 */
		deepClone: function( obj ) {
			if ( null === obj || 'object' !== typeof obj ) {return obj;}
			if ( obj instanceof Date ) {return new Date( obj.getTime() );}
			if ( obj instanceof Array ) {return obj.map( function( item ) { return this.deepClone( item ); }.bind( this ) );}
			if ( obj instanceof Object ) {
				var clonedObj = {};
				for ( var key in obj ) {
					if ( Object.prototype.hasOwnProperty.call( obj, key ) ) {
						clonedObj[key] = this.deepClone( obj[key] );
					}
				}
				return clonedObj;
			}
		},

		/**
		 * Generate unique ID
		 *
		 * @param {string} prefix Optional prefix
		 * @returns {string} Unique ID
		 */
		uniqueId: function( prefix ) {
			prefix = 'undefined' === typeof prefix ? 'scd_' : prefix;
			return prefix + Math.random().toString( 36 ).substr( 2, 9 ) + '_' + Date.now();
		},

		/**
		 * Format currency using WooCommerce settings
		 *
		 * @param {number} amount Amount to format
		 * @returns {string} Formatted currency
		 */
		formatCurrency: function( amount ) {
			// Use WooCommerce settings if available
			if ( window.scdDiscountStepData ) {
				var settings = window.scdDiscountStepData;
				var formatted = parseFloat( amount ).toFixed( settings.price_decimals );

				// Add thousand separator
				if ( settings.thousand_separator ) {
					formatted = formatted.replace( /(\d)(?=(\d{3})+\.)/g, '$1' + settings.thousand_separator );
				}

				// Replace decimal separator
				if ( '.' !== settings.decimal_separator ) {
					formatted = formatted.replace( '.', settings.decimal_separator );
				}

				// Add currency symbol
				switch ( settings.currency_pos ) {
					case 'left':
						return settings.currency_symbol + formatted;
					case 'left_space':
						return settings.currency_symbol + ' ' + formatted;
					case 'right':
						return formatted + settings.currency_symbol;
					case 'right_space':
						return formatted + ' ' + settings.currency_symbol;
					default:
						return settings.currency_symbol + formatted;
				}
			}

			// Fallback
			return '$' + parseFloat( amount ).toFixed( 2 );
		},

		/**
		 * Check if value is empty ( null, undefined, empty string, empty array )
		 *
		 * @param {*} value Value to check
		 * @returns {boolean} True if empty
		 */
		isEmpty: function( value ) {
			if ( null === value || value === undefined ) {return true;}
			if ( 'string' === typeof value ) {return '' === value.trim();}
			if ( Array.isArray( value ) ) {return 0 === value.length;}
			if ( 'object' === typeof value ) {return 0 === Object.keys( value ).length;}
			return false;
		},

		/**
		 * Safe JSON parse with error handling
		 *
		 * @param {string} json JSON string to parse
		 * @param {*} defaultValue Default value on error (optional)
		 * @returns {*} Parsed value or default
		 */
		safeJsonParse: function( json, defaultValue ) {
			try {
				return JSON.parse( json );
			} catch ( e ) {
				// Log error to error handler if available
				if ( window.SCD && window.SCD.ErrorHandler && 'function' === typeof window.SCD.ErrorHandler.log ) {
					window.SCD.ErrorHandler.log( 'JSON parse error', { json: json, error: e } );
				}
				return 1 < arguments.length ? defaultValue : null;
			}
		},

		/**
		 * Wait for DOM element to exist using MutationObserver
		 *
		 * @param {string} selector Element selector
		 * @param {Function} callback Callback when element exists
		 * @param {number} timeout Optional timeout in milliseconds
		 */
		waitForElement: function( selector, callback, timeout ) {
			timeout = timeout || 10000; // 10 seconds default
			var observer = null;
			var timeoutId = null;

			// Check if element already exists
			var $element = $( selector );
			if ( 0 < $element.length ) {
				callback( $element );
				return;
			}

			// Use MutationObserver for efficient DOM watching
			if ( window.MutationObserver ) {
				observer = new MutationObserver( function( mutations, obs ) {
					var $element = $( selector );
					if ( 0 < $element.length ) {
						if ( timeoutId ) {
							clearTimeout( timeoutId );
						}
						obs.disconnect();
						callback( $element );
					}
				} );

				// Start observing
				observer.observe( document.body, {
					childList: true,
					subtree: true
				} );

				// Set timeout
				timeoutId = setTimeout( function() {
					observer.disconnect();
					// Element not found within timeout
				}, timeout );
			} else {
				// Fallback for very old browsers
				setTimeout( function() {
					var $element = $( selector );
					if ( 0 < $element.length ) {
						callback( $element );
					}
				}, 100 );
			}
		},

		/**
		 * Request idle callback polyfill
		 *
		 * @param {Function} callback Callback to execute
		 * @param {object} options Options object
		 * @returns {number} Request ID
		 */
		requestIdleCallback: function( callback, options ) {
			if ( 'requestIdleCallback' in window ) {
				return window.requestIdleCallback( callback, options );
			}

			// For browsers without requestIdleCallback, use requestAnimationFrame
			if ( 'requestAnimationFrame' in window ) {
				var rafId;
				rafId = requestAnimationFrame( function() {
					callback( {
						didTimeout: false,
						timeRemaining: function() {
							return 16; // Approximate frame budget
						}
					} );
				} );
				return rafId;
			}

			// Last resort - execute immediately
			callback( {
				didTimeout: false,
				timeRemaining: function() {
					return 50;
				}
			} );
			return 0;
		},

		/**
		 * Cancel idle callback
		 *
		 * @param {number} id Request ID
		 */
		cancelIdleCallback: function( id ) {
			if ( 'cancelIdleCallback' in window ) {
				window.cancelIdleCallback( id );
			} else {
				clearTimeout( id );
			}
		},

		/**
		 * Escape HTML for safe display
		 *
		 * @param {string} text Text to escape
		 * @returns {string} Escaped HTML
		 */
		escapeHtml: function( text ) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};

			return String( text ).replace( /[&<>"']/g, function( char ) {
				return map[char];
			} );
		},

		/**
		 * Date Utilities Sub-object
		 */
		Date: {
			/**
			 * Format date to string
			 *
			 * @param {Date|string} date Date object or string
			 * @param {string} format Format string ( default: YYYY-MM-DD )
			 * @returns {string} Formatted date
			 */
			format: function( date, format ) {
				format = 'undefined' === typeof format ? 'YYYY-MM-DD' : format;
				if ( !date ) {return '';}

				// Ensure we have a Date object
				var dateObj = date instanceof Date ? date : new Date( date );

				if ( isNaN( dateObj.getTime() ) ) {
					return '';
				}

				var year = dateObj.getFullYear();
				var month = String( dateObj.getMonth() + 1 );
				month = 1 === month.length ? '0' + month : month;
				var day = String( dateObj.getDate() );
				day = 1 === day.length ? '0' + day : day;
				var hours = String( dateObj.getHours() );
				hours = 1 === hours.length ? '0' + hours : hours;
				var minutes = String( dateObj.getMinutes() );
				minutes = 1 === minutes.length ? '0' + minutes : minutes;
				var seconds = String( dateObj.getSeconds() );
				seconds = 1 === seconds.length ? '0' + seconds : seconds;

				// Format replacements
				var replacements = {
					'YYYY': year,
					'YY': String( year ).slice( -2 ),
					'MM': month,
					'M': String( dateObj.getMonth() + 1 ),
					'DD': day,
					'D': String( dateObj.getDate() ),
					'HH': hours,
					'H': String( dateObj.getHours() ),
					'mm': minutes,
					'm': String( dateObj.getMinutes() ),
					'ss': seconds,
					's': String( dateObj.getSeconds() )
				};

				var formatted = format;
				Object.keys( replacements ).forEach( function( key ) {
					formatted = formatted.replace( new RegExp( key, 'g' ), replacements[key] );
				}.bind( this ) );

				return formatted;
			},

			/**
			 * Parse date string
			 *
			 * @param {string} dateString Date string to parse
			 * @param {string} _format Expected format ( not currently used )
			 * @returns {Date|null} Parsed date or null
			 */
			parse: function( dateString, _format ) {
				_format = 'undefined' === typeof _format ? null : _format;
				if ( !dateString ) {return null;}

				var date = new Date( dateString );

				if ( isNaN( date.getTime() ) ) {
					// Try parsing common formats
					var parts = dateString.split( /[-/]/ );
					if ( 3 === parts.length ) {
						// Assume YYYY-MM-DD or DD-MM-YYYY
						var year = 4 === parts[0].length ? parts[0] : parts[2];
						var month = parts[1] - 1; // Zero-based months
						var day = 4 === parts[0].length ? parts[2] : parts[0];

						return new Date( year, month, day );
					}
					return null;
				}

				return date;
			},

			/**
			 * Add days to date
			 *
			 * @param {Date|string} date Base date
			 * @param {number} days Days to add ( negative to subtract )
			 * @returns {Date} New date
			 */
			addDays: function( date, days ) {
				var dateObj = date instanceof Date ? new Date( date ) : new Date( date );
				dateObj.setDate( dateObj.getDate() + days );
				return dateObj;
			},

			/**
			 * Add hours to date
			 *
			 * @param {Date|string} date Base date
			 * @param {number} hours Hours to add
			 * @returns {Date} New date
			 */
			addHours: function( date, hours ) {
				var dateObj = date instanceof Date ? new Date( date ) : new Date( date );
				dateObj.setHours( dateObj.getHours() + hours );
				return dateObj;
			},

			/**
			 * Calculate difference between dates
			 *
			 * @param {Date|string} startDate Start date
			 * @param {Date|string} endDate End date
			 * @param {string} unit Unit ( days, hours, minutes, seconds )
			 * @returns {number} Difference
			 */
			diff: function( startDate, endDate, unit ) {
				unit = 'undefined' === typeof unit ? 'days' : unit;
				var start = startDate instanceof Date ? startDate : new Date( startDate );
				var end = endDate instanceof Date ? endDate : new Date( endDate );

				var diffMs = end - start;

				switch ( unit ) {
					case 'seconds':
						return Math.floor( diffMs / 1000 );
					case 'minutes':
						return Math.floor( diffMs / ( 1000 * 60 ) );
					case 'hours':
						return Math.floor( diffMs / ( 1000 * 60 * 60 ) );
					case 'days':
					default:
						return Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );
				}
			},

			/**
			 * Format duration in human-readable format
			 *
			 * @param {number} milliseconds Duration in milliseconds
			 * @param {object} options Formatting options
			 * @returns {string} Formatted duration
			 */
			formatDuration: function( milliseconds, options ) {
				options = 'undefined' === typeof options ? {} : options;
				var defaults = {
					units: [ 'd', 'h', 'm', 's' ],
					separator: ' ',
					round: true
				};

				var config = {};
				for ( var key in defaults ) {
					config[key] = defaults[key];
				}
				for ( var optionKey in options ) {
					config[optionKey] = options[optionKey];
				}

				var days = Math.floor( milliseconds / ( 1000 * 60 * 60 * 24 ) );
				var hours = Math.floor( ( milliseconds % ( 1000 * 60 * 60 * 24 ) ) / ( 1000 * 60 * 60 ) );
				var minutes = Math.floor( ( milliseconds % ( 1000 * 60 * 60 ) ) / ( 1000 * 60 ) );
				var seconds = Math.floor( ( milliseconds % ( 1000 * 60 ) ) / 1000 );

				var parts = [];

				if ( 0 < days && -1 !== config.units.indexOf( 'd' ) ) {
					parts.push( days + 'd' );
				}
				if ( 0 < hours && -1 !== config.units.indexOf( 'h' ) ) {
					parts.push( hours + 'h' );
				}
				if ( 0 < minutes && -1 !== config.units.indexOf( 'm' ) ) {
					parts.push( minutes + 'm' );
				}
				if ( 0 < seconds && -1 !== config.units.indexOf( 's' ) ) {
					parts.push( seconds + 's' );
				}

				return 0 < parts.length ? parts.join( config.separator ) : '0s';
			}
		},

		/**
		 * DOM Utilities Sub-object
		 */
		DOM: {
			/**
			 * Set field state ( error, success, etc. )
			 *
			 * @param {HTMLElement|jQuery} field Field element
			 * @param {string} state State ( error, success, warning, '' )
			 * @param {string} message Optional message
			 */
			setFieldState: function( field, state, message ) {
				message = 'undefined' === typeof message ? '' : message;
				var $field = $( field );
				if ( !$field.length ) {return;}

				// Remove all state classes
				$field.removeClass( 'scd-field-error scd-field-success scd-field-warning' );

				// Find or create message element
				var $message = $field.siblings( '.scd-field-message' );
				if ( !$message.length && message ) {
					$message = $( '<span class="scd-field-message"></span>' );
					$field.after( $message );
				}

				// Apply new state
				if ( state ) {
					$field.addClass( 'scd-field-' + state );
					if ( $message.length ) {
						$message.text( message ).show();
					}
				} else {
					if ( $message.length ) {
						$message.hide();
					}
				}
			},

			/**
			 * Show/hide loading indicator
			 *
			 * @param {HTMLElement|jQuery} element Element to show loading on
			 * @param {boolean} show Show or hide
			 * @param {string} message Loading message
			 */
			showLoading: function( element, show, message ) {
				show = 'undefined' === typeof show ? true : show;
				message = 'undefined' === typeof message ? 'Loading...' : message;
				var $el = $( element );
				if ( !$el.length ) {return;}

				if ( show ) {
					$el.addClass( 'scd-loading' );

					// Add spinner if not exists
					if ( !$el.find( '.scd-loading-spinner' ).length ) {
						var $spinner = $( '<div class="scd-loading-spinner">' + '<span class="spinner is-active"></span>' + '<span class="scd-loading-text">' + message + '</span>' + '</div>' );
						$el.append( $spinner );
					}
				} else {
					$el.removeClass( 'scd-loading' );
					$el.find( '.scd-loading-spinner' ).remove();
				}
			},

			/**
			 * Smooth scroll to element
			 *
			 * @param {HTMLElement|jQuery|string} target Target element or selector
			 * @param {number} offset Offset from top
			 * @param {number} duration Animation duration
			 */
			scrollTo: function( target, offset, duration ) {
				offset = 'undefined' === typeof offset ? 100 : offset;
				duration = 'undefined' === typeof duration ? 500 : duration;
				var $target = $( target );
				if ( !$target.length ) {return;}

				$( 'html, body' ).animate( {
					scrollTop: $target.offset().top - offset
				}, duration );
			},

			/**
			 * Get form data as object
			 *
			 * @param {HTMLElement|jQuery} form Form element
			 * @returns {object} Form data
			 */
			getFormData: function( form ) {
				var $form = $( form );
				if ( !$form.length ) {return {};}

				var formData = {};
				var serializedArray = $form.serializeArray();

				serializedArray.forEach( function( item ) {
					if ( formData[item.name] !== undefined ) {
						if ( !Array.isArray( formData[item.name] ) ) {
							formData[item.name] = [ formData[item.name] ];
						}
						formData[item.name].push( item.value );
					} else {
						formData[item.name] = item.value;
					}
				} );

				return formData;
			},

		},

		/**
		 * Simple cache utility with localStorage support
		 */
		Cache: {
			/**
			 * Get item from cache (memory first, then localStorage)
			 * @param {string} key Cache key
			 * @returns {*} Cached value or null
			 */
			get: function( key ) {
				// Check memory cache first
				if ( this._memoryCache && this._memoryCache[key] ) {
					var memItem = this._memoryCache[key];
					if ( memItem.expires > Date.now() ) {
						return memItem.data;
					}
					delete this._memoryCache[key];
				}

				// Check localStorage if available
				if ( window.localStorage ) {
					try {
						var stored = localStorage.getItem( 'scd_cache_' + key );
						if ( stored ) {
							var parsed = JSON.parse( stored );
							if ( parsed.expires > Date.now() ) {
								// Populate memory cache
								this._memoryCache = this._memoryCache || {};
								this._memoryCache[key] = parsed;
								return parsed.data;
							}
							// Remove expired item
							localStorage.removeItem( 'scd_cache_' + key );
						}
					} catch ( e ) {
						// Ignore localStorage errors
					}
				}

				return null;
			},

			/**
			 * Set item in cache
			 * @param {string} key Cache key
			 * @param {*} data Data to cache
			 * @param {number} ttl Time to live in milliseconds
			 */
			set: function( key, data, ttl ) {
				var item = {
					data: data,
					expires: Date.now() + ttl
				};

				// Store in memory
				this._memoryCache = this._memoryCache || {};
				this._memoryCache[key] = item;

				// Store in localStorage if available
				if ( window.localStorage ) {
					try {
						localStorage.setItem( 'scd_cache_' + key, JSON.stringify( item ) );
					} catch ( e ) {
						// Ignore quota errors
					}
				}
			},

			/**
			 * Remove item from cache
			 * @param {string} key Cache key
			 */
			remove: function( key ) {
				// Remove from memory
				if ( this._memoryCache ) {
					delete this._memoryCache[key];
				}

				// Remove from localStorage
				if ( window.localStorage ) {
					try {
						localStorage.removeItem( 'scd_cache_' + key );
					} catch ( e ) {
						// Ignore errors
					}
				}
			},

			/**
			 * Clear all cache
			 */
			clear: function() {
				// Clear memory cache
				this._memoryCache = {};

				// Clear localStorage cache
				if ( window.localStorage ) {
					try {
						// Remove all scd_cache_* keys
						for ( var i = localStorage.length - 1; 0 <= i; i-- ) {
							var key = localStorage.key( i );
							if ( key && 0 === key.indexOf( 'scd_cache_' ) ) {
								localStorage.removeItem( key );
							}
						}
					} catch ( e ) {
						// Ignore errors
					}
				}
			},

			// Internal memory cache
			_memoryCache: {}
		}
	};

	// Export core utilities to global SCD.Utils namespace for centralized access
	SCD.Utils = $.extend( SCD.Utils, {
		formatCurrency: SCD.Shared.Utils.formatCurrency,
		escapeHtml: SCD.Shared.Utils.escapeHtml,
		isEmpty: SCD.Shared.Utils.isEmpty,
		debounce: SCD.Shared.Utils.debounce,
		throttle: SCD.Shared.Utils.throttle,
		deepClone: SCD.Shared.Utils.deepClone,
		uniqueId: SCD.Shared.Utils.uniqueId,
		safeJsonParse: SCD.Shared.Utils.safeJsonParse,
		waitForElement: SCD.Shared.Utils.waitForElement,
		Cache: SCD.Shared.Utils.Cache,
		getValidationConstant: SCD.Shared.Utils.getValidationConstant,
		getValidationMessage: SCD.Shared.Utils.getValidationMessage,

		/**
		 * Extend object properties
		 *
		 * @param {object} target Target object
		 * @param {object} source Source object
		 * @returns {object} Extended object
		 */
		extend: function( target, source ) {
			return $.extend( target, source );
		},

		/**
		 * Register a module within a namespace
		 *
		 * @param {string} namespace Namespace path (e.g., 'SCD.Steps')
		 * @param {string} moduleName Module name
		 * @param {Function} constructor Constructor function
		 * @returns {Function} The constructor function
		 */
		registerModule: function( namespace, moduleName, constructor ) {
			// Parse namespace path
			var parts = namespace.split( '.' );
			var current = window;

			// Create namespace if needed
			for ( var i = 0; i < parts.length; i++ ) {
				if ( !current[parts[i]] ) {
					current[parts[i]] = {};
				}
				current = current[parts[i]];
			}

			// Register the module
			current[moduleName] = constructor;

			return constructor;
		},

		/**
		 * Get nested object value safely
		 *
		 * @param {object} obj Object to get value from
		 * @param {string} path Dot-separated path
		 * @param {*} defaultValue Default value if path not found
		 * @returns {*} Value at path or default
		 */
		get: function( obj, path, defaultValue ) {
			if ( !obj || !path ) {return defaultValue;}

			var parts = path.split( '.' );
			var current = obj;

			for ( var i = 0; i < parts.length; i++ ) {
				if ( current[parts[i]] === undefined ) {
					return defaultValue;
				}
				current = current[parts[i]];
			}

			return current;
		},

		/**
		 * Ensure a module is initialized
		 *
		 * @param {string} path Module path (e.g., 'SCD.Modules.Discounts.State')
		 * @param {Function} factory Factory function to create if not exists
		 * @returns {object} Module instance
		 */
		ensureInitialized: function( path, factory ) {
			var parts = path.split( '.' );
			var current = window;

			// Navigate to the module
			for ( var i = 0; i < parts.length; i++ ) {
				if ( !current[parts[i]] ) {
					if ( i === parts.length - 1 && factory ) {
						// Create the module using factory
						current[parts[i]] = factory();
					} else {
						// Create namespace
						current[parts[i]] = {};
					}
				}
				current = current[parts[i]];
			}

			return current;
		},

	} );

	/**
	 * Field-specific utilities
	 * Centralized field handling following DRY principle
	 *
	 * @namespace SCD.Utils.Fields
	 * @since 1.0.0
	 */
	SCD.Utils.Fields = {
		/**
		 * Convert camelCase to snake_case
		 *
		 * @param {string} str String to convert
		 * @returns {string} Snake case string
		 */
		toSnakeCase: function( str ) {
			// Already snake_case? Return as-is
			if ( -1 !== str.indexOf( '_' ) ) {
				return str.toLowerCase();
			}
			// Convert camelCase to snake_case
			return String( str )
				.replace( /([a-z0-9])([A-Z])/g, '$1_$2' )
				.replace( /([A-Z])([A-Z][a-z])/g, '$1_$2' )
				.toLowerCase();
		},

		/**
		 * Convert snake_case to camelCase
		 *
		 * @param {string} str String to convert
		 * @returns {string} Camel case string
		 */
		toCamelCase: function( str ) {
			// Already camelCase (no underscores)? Return as-is
			if ( -1 === str.indexOf( '_' ) ) {
				return str;
			}
			// Convert snake_case to camelCase
			return String( str ).replace( /_+([a-z])/g, function( match, char ) {
				return char.toUpperCase();
			} );
		},

		/**
		 * Escape field ID for jQuery selectors
		 * Escapes CSS special characters: !"#$%&'()*+,./:;<=>?@[\]^`{|}~
		 *
		 * @param {string} fieldId Field ID to escape
		 * @returns {string} Escaped field ID
		 */
		escapeFieldId: function( fieldId ) {
			if ( !fieldId ) {
				return '';
			}
			// First ensure it's a string
			fieldId = String( fieldId );
			// If field uses array notation, remove brackets for ID lookup
			if ( '[]' === fieldId.substr( -2 ) ) {
				fieldId = fieldId.slice( 0, -2 );
			}
			// Escape CSS special characters
			return fieldId.replace( /([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1' );
		},

		/**
		 * Check if field is required based on definition and conditions
		 *
		 * @param {object} fieldDef Field definition
		 * @param {object} allData All form data for conditional checks
		 * @returns {boolean} True if field is required
		 */
		isFieldRequired: function( fieldDef, allData ) {
			if ( !fieldDef ) {
				return false;
			}

			// Direct required flag
			if ( fieldDef.required ) {
				return true;
			}

			// Conditional required
			if ( fieldDef.required_when && allData ) {
				var condition = fieldDef.required_when;
				var fieldValue = allData[condition.field];

				// Handle both camelCase and snake_case field names
				if ( undefined === fieldValue && -1 === condition.field.indexOf( '_' ) ) {
					fieldValue = allData[this.toSnakeCase( condition.field )];
				}

				return fieldValue === condition.value;
			}

			return false;
		},

		/**
		 * Get appropriate validation message for field
		 *
		 * @param {object} fieldDef Field definition
		 * @param {string} messageType Type of message (required, min, max, etc.)
		 * @param {*} param Optional parameter for the message
		 * @returns {string} Validation message
		 */
		getFieldMessage: function( fieldDef, messageType, param ) {
			if ( !fieldDef || !fieldDef.messages ) {
				return '';
			}

			var message = fieldDef.messages[messageType] || '';

			// Replace placeholders in message
			if ( message && undefined !== param ) {
				message = message.replace( /%s/g, param );
				message = message.replace( /\{0\}/g, param );
			}

			return message;
		},

		/**
		 * Deep equality check for arrays and objects
		 *
		 * @param {*} a First value
		 * @param {*} b Second value
		 * @returns {boolean} True if deeply equal
		 */
		deepEqual: function( a, b ) {
			if ( a === b ) {
				return true;
			}

			if ( null === a || null === b ) {
				return false;
			}

			if ( typeof a !== typeof b ) {
				return false;
			}

			if ( Array.isArray( a ) ) {
				if ( !Array.isArray( b ) || a.length !== b.length ) {
					return false;
				}

				for ( var i = 0; i < a.length; i++ ) {
					if ( !this.deepEqual( a[i], b[i] ) ) {
						return false;
					}
				}

				return true;
			}

			if ( 'object' === typeof a ) {
				var keysA = Object.keys( a );
				var keysB = Object.keys( b );

				if ( keysA.length !== keysB.length ) {
					return false;
				}

				for ( var j = 0; j < keysA.length; j++ ) {
					var key = keysA[j];
					if ( !Object.prototype.hasOwnProperty.call( b, key ) || !this.deepEqual( a[key], b[key] ) ) {
						return false;
					}
				}

				return true;
			}

			return false;
		},

		/**
		 * Get field value from DOM (camelCase field name → snake_case DOM lookup)
		 * Centralized field handling following DRY principle
		 *
		 * @param {string} fieldNameCamel Field name in camelCase
		 * @param {object} fieldDef Field definition object
		 * @returns {*} Field value
		 */
		getFieldValue: function( fieldNameCamel, fieldDef ) {
			var $field;
			var arrayData;

			// Use explicit selector if provided in field definition
			if ( fieldDef.selector ) {
				$field = $( fieldDef.selector );
			} else {
				// Fallback to auto-generated selectors
				var fieldId = this.toSnakeCase( fieldNameCamel );
				var escapedFieldId = this.escapeFieldId( fieldId );
				$field = $( '#' + escapedFieldId );

				if ( !$field.length ) {
					$field = $( '[name="' + escapedFieldId + '"]' );
				}
				// Also check for array fields with brackets
				if ( !$field.length && 'array' === fieldDef.type ) {
					$field = $( '[name="' + escapedFieldId + '[]"]' );
				}
			}

			if ( !$field.length ) {
				return SCD.Utils.deepClone( fieldDef.default );
			}

			switch ( fieldDef.type ) {
				case 'boolean':
				// Skip disabled fields - return default value instead
				if ( $field.prop( 'disabled' ) ) {
					return fieldDef.default || false;
				}
					return $field.is( ':checked' );
				case 'array':
					if ( $field.is( 'select[multiple]' ) ) {
						return $field.val() || [];
					}
					// Read from DOM attribute instead of jQuery data cache for proper persistence
					var dataValue = $field.attr( 'data-value' );
					if ( dataValue ) {
						arrayData = SCD.Utils.safeJsonParse( dataValue, [] );
						return Array.isArray( arrayData ) ? arrayData.slice() : [];
					}
					return [];
				case 'nested_array':
					// Collect nested form arrays (e.g., conditions[0][mode], conditions[0][type])
					return SCD.Utils.collectNestedFormArray( fieldDef.field_name || this.toSnakeCase( fieldNameCamel ) );
				case 'radio':
					// For radio buttons, filter to get only the checked one
					return $field.filter( ':checked' ).val() || fieldDef.default;
				case 'select':
					// Handle radio buttons (which have type='select' in field definitions)
					if ( $field.is( ':radio' ) ) {
						// For radio buttons, filter to get only the checked one
						return $field.filter( ':checked' ).val() || fieldDef.default;
					}
					return $field.val() || fieldDef.default;
				case 'select_multiple':
					// For multi-select fields, val() returns an array
					var selectedValues = $field.val();
					return selectedValues && selectedValues.length > 0 ? selectedValues : fieldDef.default;
				default:
					return $field.val();
			}
		},

		/**
		 * Set field value in DOM (camelCase field name → snake_case DOM lookup)
		 * Centralized field handling following DRY principle
		 *
		 * @param {string} fieldNameCamel Field name in camelCase
		 * @param {*} value Value to set
		 * @param {object} fieldDef Field definition object
		 */
		setFieldValue: function( fieldNameCamel, value, fieldDef ) {
			var fieldId = this.toSnakeCase( fieldNameCamel );
			var escapedFieldId = this.escapeFieldId( fieldId );
			var $field = $( '#' + escapedFieldId );
			var next, prev, changed;

			if ( !$field.length ) {
				$field = $( '[name="' + escapedFieldId + '"]' );
			}
			if ( !$field.length ) {
				return;
			}

			switch ( fieldDef.type ) {
				case 'boolean':
					next = !!value;
					if ( $field.prop( 'checked' ) !== next ) {
						$field.prop( 'checked', next ).trigger( 'change' );
					}
					break;
				case 'array':
					if ( $field.is( 'select[multiple]' ) ) {
						next = value || [];
						prev = $field.val() || [];
						changed = !this.deepEqual( prev, next );
						$field.val( next );
						if ( changed ) {
							$field.trigger( 'change' );
						}
					} else {
						next = Array.isArray( value ) ? value.slice() : [];
						// Read previous value from DOM attribute for comparison
						var prevDataValue = $field.attr( 'data-value' );
						prev = SCD.Utils.safeJsonParse( prevDataValue, [] ) || [];
						changed = !this.deepEqual( prev, next );
						// Set both jQuery data and DOM attribute for proper persistence
						$field.data( 'value', next );
						$field.attr( 'data-value', JSON.stringify( next ) );
						if ( changed ) {
							$field.trigger( 'scd:set-value', [ next ] );
							$field.trigger( 'change' );
						}
					}
					break;
				case 'nested_array':
					// Populate nested form arrays (e.g., conditions[0][mode], conditions[0][type])
					// Trigger event for orchestrator to handle UI reconstruction
					$( document ).trigger( 'scd:populate-nested-array', {
						fieldName: fieldDef.field_name || this.toSnakeCase( fieldNameCamel ),
						value: value
					} );
					break;
				case 'radio':
					// For radio buttons, we need to check the one with matching value
					var escapedValue = this.escapeFieldId( String( value || '' ) );
					$field.prop( 'checked', false ); // Uncheck all in the group
					$field.filter( '[value="' + escapedValue + '"]' ).prop( 'checked', true ).trigger( 'change' );
					break;
				case 'select':
					// Handle radio buttons separately from select dropdowns
					if ( $field.is( ':radio' ) ) {
						// For radio buttons, we need to check the one with matching value
						var escapedValue = this.escapeFieldId( String( value || '' ) );
						$field.filter( '[value="' + escapedValue + '"]' ).prop( 'checked', true ).trigger( 'change' );
					} else {
						// For select dropdowns, use normal val() method
						prev = $field.val();
						if ( prev !== value ) {
							$field.val( value ).trigger( 'change' );
						}
					}
					break;
				case 'select_multiple':
					// For multi-select fields, value should be an array
					var arrayValue = Array.isArray( value ) ? value : ( value ? [ value ] : [] );
					$field.val( arrayValue ).trigger( 'change' );
					break;
				default:
					prev = $field.val();
					if ( prev !== value ) {
						$field.val( value ).trigger( 'change' );
					}
					break;
			}
		},

		/**
		 * Convert snake_case to camelCase
		 * Per CLAUDE.md naming convention rules
		 * Delegates to SCD.Utils.Fields for consistency
		 * 
		 * @param {string} str - String in snake_case
		 * @returns {string} String in camelCase
		 * @since 1.0.0
		 */
		snakeToCamelCase: function( str ) {
			// Delegate to Fields utility for consistency
			if ( SCD.Utils.Fields && SCD.Utils.Fields.toCamelCase ) {
				return SCD.Utils.Fields.toCamelCase( str );
			}
			// Fallback implementation
			if ( !str ) {
				return str;
			}
			return str.replace( /_([a-z])/g, function( match, letter ) {
				return letter.toUpperCase();
			});
		},

		/**
		 * Convert camelCase to snake_case
		 * Per CLAUDE.md naming convention rules
		 * Delegates to SCD.Utils.Fields for consistency
		 * 
		 * @param {string} str - String in camelCase
		 * @returns {string} String in snake_case
		 * @since 1.0.0
		 */
		camelToSnakeCase: function( str ) {
			// Delegate to Fields utility for consistency
			if ( SCD.Utils.Fields && SCD.Utils.Fields.toSnakeCase ) {
				return SCD.Utils.Fields.toSnakeCase( str );
			}
			// Fallback implementation
			if ( !str ) {
				return str;
			}
			return str.replace( /[A-Z]/g, function( match ) {
				return '_' + match.toLowerCase();
			}).replace( /^_/, '' ); // Remove leading underscore if any
		},

		/**
		 * Collect nested form array from DOM
		 *
		 * Collects form inputs with bracket notation (e.g., conditions[0][mode], conditions[0][type])
		 * and returns a properly structured JavaScript array.
		 *
		 * Example HTML:
		 *   <input name="conditions[0][mode]" value="include">
		 *   <input name="conditions[0][type]" value="price">
		 *
		 * Returns:
		 *   [{ mode: 'include', type: 'price' }]
		 *
		 * @param {string} fieldName - Base field name (e.g., 'conditions')
		 * @param {jQuery} $container - Container element to search within (optional)
		 * @returns {Array} Array of objects representing the nested data
		 * @since 1.0.0
		 */
		collectNestedFormArray: function( fieldName, $container ) {
			$container = $container || $( document );

			// Find all inputs matching pattern: name="fieldName[index][property]"
			var selector = '[name^="' + fieldName + '["]';
			var $fields = $container.find( selector );

			if ( !$fields.length ) {
				return [];
			}

			// Parse field names and build nested structure
			var dataMap = {};

			$fields.each( function() {
				var $field = $( this );
				var name = $field.attr( 'name' );

				// Skip disabled fields
				if ( $field.prop( 'disabled' ) ) {
					return;
				}

				// Parse name: conditions[0][mode] → index: 0, property: mode
				var matches = name.match( /\[(\d+)\]\[([^\]]+)\]/ );
				if ( !matches ) {
					return;
				}

				var index = matches[1];
				var property = matches[2];
				var value = $field.val();

				// Initialize index object if needed
				if ( !dataMap[index] ) {
					dataMap[index] = {};
				}

				// Store value
				dataMap[index][property] = value;
			} );

			// Convert map to array (preserving index order)
			var result = [];
			var indices = Object.keys( dataMap ).sort( function( a, b ) {
				return parseInt( a, 10 ) - parseInt( b, 10 );
			} );

			for ( var i = 0; i < indices.length; i++ ) {
				var idx = indices[i];
				result.push( dataMap[idx] );
			}

			return result;
		}
	};

} )( jQuery );