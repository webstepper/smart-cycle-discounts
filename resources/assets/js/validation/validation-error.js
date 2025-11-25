/**
 * Validation Error
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/validation/validation-error.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Components = window.SCD.Components || {};

	/**
	 * ValidationError Component
	 *
	 * Centralized error display component that handles all validation error
	 * presentation throughout the plugin. Provides consistent styling,
	 * accessibility compliance, and integration with validation workflows.
	 *
	 * @namespace SCD.Components.ValidationError
	 * @since 1.0.0
	 *
	 * Design Principles:
	 * - KISS: Simple, focused error display functionality
	 * - DRY: Single implementation eliminates code duplication
	 * - WCAG 2.1 AA: Full accessibility compliance
	 * - Performance: Efficient DOM manipulation and caching
	 * - Integration: Seamless ValidationManager integration
	 */
	SCD.Components.ValidationError = {

		/**
		 * Track timeouts for cleanup
		 * @private
		 */
		_activeTimeouts: [],

		/**
		 * Track pending focus timeout to prevent memory leaks
		 * @private
		 */
		_pendingFocusTimeout: null,

		/**
		 * Cache for field labels to avoid repeated lookups
		 * @private
		 */
		_labelCache: {},

		/**
		 * Cached announcer element
		 * @private
		 */
		_$announcer: null,

		/**
		 * Cached scroll container
		 * @private
		 */
		_$scrollContainer: null,

		/**
		 * Display validation error for a specific field
		 *
		 * Shows an error message for a form field with proper accessibility
		 * attributes, animations, and styling. Handles error element creation,
		 * positioning, and ARIA compliance automatically.
		 *
		 * @function show
		 * @memberof SCD.Components.ValidationError
		 * @param {jQuery} $field - jQuery field element to show error for
		 * @param {string} message - Error message to display
		 * @param {object} [options={}] - Display configuration options
		 * @param {string} [options.type='error'] - Error type (error, warning, info)
		 * @param {boolean} [options.animate=true] - Whether to animate error display
		 * @param {string} [options.position='after-field'] - Error position relative to field
		 * @param {boolean} [options.focus=false] - Whether to focus the field
		 * @param {string} [options.cssClass=''] - Additional CSS classes
		 *
		 * @example
		 * // Show basic error
		 * SCD.Components.ValidationError.show($('#email'), 'Invalid email address');
		 *
		 * // Show warning with custom options
		 * SCD.Components.ValidationError.show($('#password'), 'Weak password', {
		 *     type: 'warning',
		 *     animate: true,
		 *     focus: true
		 * });
		 *
		 * @since 1.0.0
		 */
	show: function( $field, message, options ) {
		options = options || {};

		if ( ! $field || ! $field.length ) {
			return;
		}

		this.clear( $field );

		// Apply error styling (red border, ARIA attributes)
		this._applyErrorStyling( $field );

		if ( message ) {
			// Extract message string from error object if needed
			var messageText = message;
			if ( 'object' === typeof message && message.message ) {
				messageText = message.message;
			} else if ( 'object' === typeof message && message.code ) {
				// Fallback: use code if message property not available
				messageText = message.code;
			}

			var $container = this._findContainer( $field );
			var errorId = 'error-' + ( $field.attr( 'name' ) || $field.attr( 'id' ) || 'field' ).replace( /[^a-zA-Z0-9]/g, '-' );

			var $errorMsg = $( '<div class="scd-field-error" id="' + errorId + '" role="alert"></div>' ).text( messageText );

			// Special handling for Tom Select fields
			var $tomSelectWrapper = $field.siblings( '.ts-wrapper' );
			if ( $tomSelectWrapper.length ) {
				// Insert error after Tom Select wrapper (visible UI)
				$tomSelectWrapper.after( $errorMsg );
			} else {
				// Insert error message inside container, after the field element
				// This keeps error visually close to its field while avoiding flex layout issues
				$field.after( $errorMsg );
			}

			// Link field to error message for accessibility
			$field.attr( 'aria-describedby', errorId );

			// Announce error to screen readers
			this.announceError( messageText, $field.attr( 'name' ) || $field.attr( 'id' ) );
		}

		// Debug logging
		var fieldName = $field.attr( 'name' ) || $field.attr( 'id' ) || 'field';
		this._logDebug( 'Showing error for field: ' + fieldName, { message: message } );
	},

		/**
		 * Clear validation error from a specific field
		 *
		 * Removes error display, resets field state, and cleans up ARIA
		 * attributes. Handles animations and provides options for complete
		 * error element removal or just hiding.
		 *
		 * @function clear
		 * @memberof SCD.Components.ValidationError
		 * @param {jQuery} $field - jQuery field element to clear error from
		 * @param {object} [options={}] - Clear configuration options
		 * @param {boolean} [options.animate=true] - Whether to animate error removal
		 * @param {boolean} [options.remove=false] - Whether to remove error element completely
		 *
		 * @example
		 * // Clear error with animation
		 * SCD.Components.ValidationError.clear($('#email'));
		 *
		 * // Clear and remove error element completely
		 * SCD.Components.ValidationError.clear($('#password'), {
		 *     animate: true,
		 *     remove: true
		 * });
		 *
		 * @since 1.0.0
		 */
	clear: function( $field, _options ) {
		_options = _options || {};

		if ( ! $field || ! $field.length ) {
			return;
		}

		var $container = this._findContainer( $field );

		var errorId = $field.attr( 'aria-describedby' );
		if ( errorId ) {
			$( '#' + errorId ).remove();
		}

		// Also remove any error messages near the field
		$field.siblings( '.scd-field-error' ).remove();
		$container.find( '.scd-field-error' ).remove();

		// Check if this is a Tom Select field and clear error styling from wrapper
		var $tomSelectWrapper = $field.siblings( '.ts-wrapper' );
		if ( $tomSelectWrapper.length ) {
			$tomSelectWrapper.removeClass( 'error' );
			$tomSelectWrapper.css( {
				'border': '',
				'background-color': ''
			} );
		}

		$field
			.attr( 'aria-invalid', 'false' )
			.removeAttr( 'aria-describedby' )
			.removeClass( 'error' );

		$field.css( {
			'border': '',
			'background-color': ''
		} );

		$container.removeClass( 'has-error' );

		$field.closest( '.form-field' ).removeClass( 'has-error' );
	},

		/**
		 * Display validation errors for multiple fields simultaneously
		 *
		 * Efficiently processes and displays errors for multiple form fields,
		 * with optional validation summary generation and batch clearing.
		 * Commonly used for complete form validation results.
		 *
		 * @function showMultiple
		 * @memberof SCD.Components.ValidationError
		 * @param {object} errors - Field name to error messages mapping
		 * @param {jQuery} $context - Container element to search for fields
		 * @param {object} [options={}] - Display configuration options
		 * @param {boolean} [options.clearFirst=false] - Clear existing errors first
		 * @param {boolean} [options.showSummary=false] - Show validation summary
		 * @param {string} [options.summaryPosition='top'] - Summary position
		 * @param {boolean} [options.focusSummary=false] - Focus summary after display
		 *
		 * @example
		 * // Show multiple field errors
		 * const errors = {
		 *     'email': 'Invalid email format',
		 *     'password': 'Password too short',
		 *     'confirm_password': 'Passwords do not match'
		 * };
		 *
		 * SCD.Components.ValidationError.showMultiple(errors, $('#registration-form'), {
		 *     clearFirst: true,
		 *     showSummary: true,
		 *     summaryPosition: 'top'
		 * });
		 *
		 * @since 1.0.0
		 */
		showMultiple: function( errors, $context, options ) {
			var self = this;
			options = options || {};

			// Debug logging
			this._logDebug( 'showMultiple called', { errors: errors, context: $context, options: options } );

			if ( options.clearFirst ) {
				this.clearAll( $context );
			}

			$.each( errors, function( fieldName, messages ) {
				var $field = self._findField( fieldName, $context );

				if ( $field.length ) {
					var message = Array.isArray( messages ) ? messages[0] : messages;

					// Use the centralized show() method to display inline error with message
					self.show( $field, message );
					self._logDebug( 'Showing error for field: ' + fieldName, { message: message } );
				} else {
					// Field not found - log for debugging
					self._logDebug( 'Field not found: ' + fieldName, {
						context: $context,
						availableFields: $context.find( 'input, select, textarea' ).map( function() {
							return this.name || this.id;
						} ).get()
					} );
				}
			} );

			if ( options.showSummary ) {
				this.showSummary( errors, $context, options );
			}

			// Auto-focus first error if requested
			if ( options.focusFirstError ) {
				if ( this._pendingFocusTimeout ) {
					this._clearTimeout( this._pendingFocusTimeout );
					this._pendingFocusTimeout = null;
				}

				// Small delay to ensure DOM updates are complete
				this._pendingFocusTimeout = this._setTimeout( function() {
					self._pendingFocusTimeout = null;
					self.focusFirstError( $context );
				}, 50 );
			}
		},

		/**
		 * Find field by name with fallback strategies
		 * @private
		 * @since 1.0.0
		 * @param {string} fieldName - Field name to find
		 * @param {jQuery} $context - Context to search in
		 * @returns {jQuery} Field element or empty jQuery object
		 */
		_findField: function( fieldName, $context ) {
			// Try to find field by name first
			var $field = $context.find( '[name="' + this.escapeSelector( fieldName ) + '"]' );

			// If not found by name, try by ID
			if ( ! $field.length ) {
				$field = $context.find( '#' + this.escapeSelector( fieldName ) );
			}

			// If camelCase field not found, try snake_case conversion
			// (PHP validation returns camelCase, but DOM uses snake_case)
			if ( ! $field.length && /[A-Z]/.test( fieldName ) ) {
				var snakeName = window.SCD && window.SCD.Utils && window.SCD.Utils.Fields && window.SCD.Utils.Fields.toSnakeCase
					? window.SCD.Utils.Fields.toSnakeCase( fieldName )
					: fieldName.replace( /[A-Z]/g, function( letter ) { return '_' + letter.toLowerCase(); } );
				$field = $context.find( '[name="' + this.escapeSelector( snakeName ) + '"]' );
				if ( ! $field.length ) {
					$field = $context.find( '#' + this.escapeSelector( snakeName ) );
				}
			}

			return $field;
		},

		/**
		 * Find field container with fallback strategies
		 * @private
		 * @since 1.0.0
		 * @param {jQuery} $field - Field element
		 * @returns {jQuery} Container element
		 */
		_findContainer: function( $field ) {
			var $container = $field.closest( '.scd-field-container, .scd-field-wrapper, .form-field' );

			if ( ! $container.length ) {
				$container = $field.parent();
			}

			return $container;
		},

		/**
		 * Log debug message if debug enabled
		 * @private
		 * @since 1.0.0
		 * @param {string} message - Debug message
		 * @param {*} data - Optional data to log
		 */
		_logDebug: function( message, data ) {
			// Debug logging intentionally removed for production
		},

		/**
		 * Apply error styling to field without showing error message
		 * @private
		 * @since 1.0.0
		 * @param {jQuery} $field - Field element
		 */
		_applyErrorStyling: function( $field ) {
			// Check if this is a Tom Select field
			var $tomSelectWrapper = $field.siblings( '.ts-wrapper' );

			if ( $tomSelectWrapper.length ) {
				// Apply error styling to visible Tom Select wrapper
				$tomSelectWrapper.addClass( 'error' );
				// Also add to hidden input for consistency
				$field.addClass( 'error' );
				$field.attr( 'aria-invalid', 'true' );
			} else {
				// Standard field: apply directly
				$field.addClass( 'error' );
				$field.attr( 'aria-invalid', 'true' );
			}

			// Also add error class to container for proper CSS targeting
			var $container = this._findContainer( $field );
			$container.addClass( 'has-error' );
		},

		/**
		 * Create and track timeout for proper cleanup
		 * @private
		 * @since 1.0.0
		 * @param {function} callback - Callback function
		 * @param {number} delay - Delay in milliseconds
		 * @returns {number} Timeout ID
		 */
		_setTimeout: function( callback, delay ) {
			var timeoutId = setTimeout( callback, delay );
			this._activeTimeouts.push( timeoutId );
			return timeoutId;
		},

		/**
		 * Clear specific timeout and remove from tracking
		 * @private
		 * @since 1.0.0
		 * @param {number} timeoutId - Timeout ID to clear
		 */
		_clearTimeout: function( timeoutId ) {
			var index = this._activeTimeouts.indexOf( timeoutId );
			if ( -1 !== index ) {
				this._activeTimeouts.splice( index, 1 );
			}
			clearTimeout( timeoutId );
		},

		/**
		 * Clear all pending timeouts
		 * @private
		 * @since 1.0.0
		 */
		_clearAllTimeouts: function() {
			while ( this._activeTimeouts.length ) {
				clearTimeout( this._activeTimeouts.pop() );
			}
		},

		/**
		 * Clear all validation errors within a container
		 *
		 * Removes all validation errors, summaries, and resets field states
		 * within the specified container. Useful for form resets or when
		 * starting fresh validation cycles.
		 *
		 * @function clearAll
		 * @memberof SCD.Components.ValidationError
		 * @param {jQuery} $context - Container element to clear errors from
		 * @param {object} [options={}] - Clear configuration options
		 * @param {boolean} [options.animate=true] - Whether to animate error removal
		 * @param {boolean} [options.remove=false] - Whether to remove error elements
		 *
		 * @example
		 * // Clear all errors in form
		 * SCD.Components.ValidationError.clearAll($('#wizard-form'));
		 *
		 * // Clear all errors and remove elements
		 * SCD.Components.ValidationError.clearAll($('#step-container'), {
		 *     animate: false,
		 *     remove: true
		 * });
		 *
		 * @since 1.0.0
		 */
		clearAll: function( $context, options ) {
			var self = this;
			options = options || {};

			// Find all error fields
			$context.find( '.error[aria-invalid="true"]' ).each( function() {
				self.clear( $( this ), options );
			} );

			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.dismiss( 'validation-errors' );
			}

			if ( this._$announcer && this._$announcer.length ) {
				this._$announcer.empty();
			}
		},

		/**
		 * Show validation summary using notification system
		 *
		 * @param {object} errors Field errors
		 * @param {jQuery} $context Container element
		 * @param {object} options Summary options
		 */
		showSummary: function( errors, $context, _options ) {
			_options = _options || {};
			var self = this;

			// Count errors and build messages
			var errorCount = 0;
			var errorMessages = [];
			var fieldCache = {};

			$.each( errors, function( fieldName, messages ) {
				var messageArray = Array.isArray( messages ) ? messages : [ messages ];

				if ( ! fieldCache[fieldName] ) {
					fieldCache[fieldName] = self._findField( fieldName, $context );
				}
				var $field = fieldCache[fieldName];

				$.each( messageArray, function( index, message ) {
					errorCount++;

					// Extract message string from error object if needed
					var messageText = message;
					if ( 'object' === typeof message && message.message ) {
						messageText = message.message;
					} else if ( 'object' === typeof message && message.code ) {
						// Fallback: use code if message property not available
						messageText = message.code;
					}

					// Use public getFieldLabel which handles all fallback logic
					var fieldLabel = SCD.Components.ValidationError.getFieldLabel( $field.length ? $field : fieldName, fieldName );

					// If no label found, don't show empty prefix
					if ( fieldLabel && fieldLabel.trim() ) {
						errorMessages.push( fieldLabel + ': ' + messageText );
					} else {
						errorMessages.push( messageText );
					}
				} );
			} );

		if ( 0 === errorCount ) {
			// Dismiss any existing validation error notification when there are no errors
			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.dismiss( 'validation-errors' );
			}
			return;
		}

			// Build more specific notification message based on error count
			var notificationMessage;
			if ( 1 === errorCount ) {
				// Single error: Show the specific field error
				notificationMessage = errorMessages[0];
			} else if ( errorCount <= 3 ) {
				// 2-3 errors: List all errors with bullets
				notificationMessage = errorMessages.join( ' • ' );
			} else {
				// Many errors: Show count with first few examples
				var displayErrors = errorMessages.slice( 0, 2 );
				var remainingCount = errorCount - 2;
				notificationMessage = displayErrors.join( ', ' ) +
					' and ' + remainingCount + ' more field' + ( remainingCount > 1 ? 's' : '' ) + ' require attention';
			}

			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.show(
					notificationMessage,
					'error',
					5000, // Longer duration for reading multiple errors
					{
						id: 'validation-errors',
						replace: true,
						dismissible: true
					}
				);
			}

		// Note: Scrolling and focusing is handled by focusFirstError() when focusFirstError option is set
		// This avoids duplicate scrolling and allows proper animation timing
		},

		/**
		 * Get field label from DOM with caching
		 * Returns empty string if no label found (caller handles formatting)
		 * @private
		 * @param {jQuery} $field Field element
		 * @param {string} fieldName Field name for cache key
		 * @returns {string} Field label or empty string
		 */
		_getFieldLabel: function( $field, fieldName ) {
			if ( ! $field || ! $field.length ) {
				return '';
			}

			var cacheKey = fieldName || $field.attr( 'name' ) || $field.attr( 'id' );
			if ( cacheKey && this._labelCache[cacheKey] ) {
				return this._labelCache[cacheKey];
			}

			var label = '';

			// Try to find associated label by ID
			var fieldId = $field.attr( 'id' );
			if ( fieldId ) {
				var $label = $( 'label[for="' + fieldId + '"]' );
				if ( $label.length ) {
					label = $label.text().replace( /[*:]/g, '' ).trim();
				}
			}

			// Try parent label
			if ( ! label ) {
				var $parentLabel = $field.closest( 'label' );
				if ( $parentLabel.length ) {
					var labelText = $parentLabel.clone();
					labelText.find( 'input, select, textarea' ).remove();
					label = labelText.text().replace( /[*:]/g, '' ).trim();
				}
			}

			// Try aria-label
			if ( ! label ) {
				var ariaLabel = $field.attr( 'aria-label' );
				if ( ariaLabel ) {
					label = ariaLabel;
				}
			}

			// Try placeholder
			if ( ! label ) {
				var placeholder = $field.attr( 'placeholder' );
				if ( placeholder ) {
					label = placeholder;
				}
			}

			// Cache the result
			if ( label && cacheKey ) {
				this._labelCache[cacheKey] = label;
			}

			return label;
		},

		/**
		 * Announce error to screen readers
		 * Uses cached announcer element for performance
		 * @param {string} message Error message
		 * @param {string} fieldName Field name
		 */
		announceError: function( message, fieldName ) {
			// Use cached announcer or create if needed
			if ( ! this._$announcer || ! this._$announcer.length ) {
				this._$announcer = $( '#scd-validation-announcer' );

				if ( ! this._$announcer.length ) {
					this._$announcer = $( '<div id="scd-validation-announcer" class="screen-reader-text" aria-live="assertive" aria-atomic="true"></div>' );
					$( 'body' ).append( this._$announcer );
				}
			}

			// Format announcement using public method which handles all fallback logic
			var announcement = 'Error in ' + SCD.Components.ValidationError.getFieldLabel( fieldName ) + ': ' + message;

			var $announcer = this._$announcer;
			$announcer.empty();
			// Use requestAnimationFrame to ensure DOM update
			requestAnimationFrame( function() {
				$announcer.text( announcement );
			} );
		},

		/**
		 * Escape jQuery selector special characters
		 * @since 1.0.0
		 * @param {string} selector Selector to escape
		 * @returns {string} Escaped selector
		 */
		escapeSelector: function( selector ) {
			if ( 'string' !== typeof selector ) {
				return '';
			}
			// Escape special jQuery selector characters
			return selector.replace( /([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1' );
		},

		/**
		 * Initialize error component
		 * Sets up announcer and caches
		 */
		init: function() {
			if ( ! $( '#scd-validation-announcer' ).length ) {
				this._$announcer = $( '<div id="scd-validation-announcer" class="screen-reader-text" aria-live="assertive" aria-atomic="true"></div>' );
				$( 'body' ).append( this._$announcer );
			} else {
				this._$announcer = $( '#scd-validation-announcer' );
			}

			this._$scrollContainer = $( 'html, body' );

		},

		/**
		 * Destroy method for cleanup
		 * Cleans up all resources, removes elements, clears caches and timeouts
		 */
		destroy: function() {
			if ( this._pendingFocusTimeout ) {
				this._clearTimeout( this._pendingFocusTimeout );
				this._pendingFocusTimeout = null;
			}

			this._clearAllTimeouts();

			$( '.scd-field-error, .field-error' ).remove();

			if ( this._$announcer && this._$announcer.length ) {
				this._$announcer.remove();
			}
			$( '#scd-validation-announcer' ).remove();

			$( '.scd-validation-summary' ).remove();

			$( '.has-error' ).removeClass( 'has-error' );
			$( '.scd-field-error-border' ).removeClass( 'scd-field-error-border' );

			this._labelCache = {};
			this._$announcer = null;
			this._$scrollContainer = null;
		}
	};

	/**
	 * Unified getFieldLabel function
	 * Delegates to private instance method or formats field name
	 * @param {string|jQuery} fieldOrName - Field element or field name
	 * @param {string} fallbackName - Fallback field name if first param is jQuery
	 * @returns {string} Human-readable label
	 */
	SCD.Components.ValidationError.getFieldLabel = function( fieldOrName, fallbackName ) {
		// If first parameter is a jQuery object, use private instance method
		if ( fieldOrName && fieldOrName.jquery ) {
			var fieldName = fallbackName || fieldOrName.attr( 'name' ) || fieldOrName.attr( 'id' ) || '';
			// Call private instance method which has caching and DOM lookup logic
			return this._getFieldLabel.call( this, fieldOrName, fieldName );
		}

		// Otherwise, treat as field name string
		var fieldName = String( fieldOrName || '' );

		// Try PHP field definitions first
		if ( window.SCD && window.SCD.FieldDefinitions && window.SCD.FieldDefinitions.getFieldByName ) {
			var steps = ['basic', 'products', 'discounts', 'schedule'];
			for ( var i = 0; i < steps.length; i++ ) {
				var field = window.SCD.FieldDefinitions.getFieldByName( steps[i], fieldName );

				if ( field && field.label ) {
					return field.label;
				}
			}
		}

		// Fallback: Convert snake_case or camelCase to Title Case
		if ( fieldName && fieldName.trim() ) {
			// Convert camelCase to snake_case first
			var snakeCased = fieldName.replace( /([A-Z])/g, '_$1' ).toLowerCase();
			// Then convert to Title Case
			return snakeCased.replace( /_/g, ' ' ).replace( /\b\w/g, function( letter ) {
				return letter.toUpperCase();
			} ).trim();
		}

		// Final fallback if no field name at all
		return 'This field';
	};

	/**
	 * Get validation message with proper formatting and escaping
	 * Moved from validation-manager.js for better separation of concerns
	 * @param {string} key - Message key
	 * @param {string} fieldName - Field name for field-specific messages
	 * @returns {string} Formatted and escaped message
	 */
	SCD.Components.ValidationError.getMessage = function( key, fieldName ) {
		// Use localized messages from PHP
		var messages = window.scdValidationMessages || {};
		var message = messages[key];

		var fieldLabel = fieldName ? this.getFieldLabel( fieldName ) : 'This field';

		// Minimal fallback for critical messages only
		if ( ! message ) {
			var fallbacks = {
				'required': fieldLabel + ' is required',
				'invalid': 'Invalid ' + fieldLabel
			};
			message = fallbacks[key] || key;
		}

		// Final fallback
		message = message || key;

		var templateArgs = Array.prototype.slice.call( arguments, 2 );

		// Escape all template arguments to prevent XSS
		var escapedArgs = [];
		for ( var i = 0; i < templateArgs.length; i++ ) {
			var argValue = String( templateArgs[i] );
			// Use SCD.Utils.escapeHtml if available, otherwise use basic escaping
			if ( window.SCD && window.SCD.Utils && window.SCD.Utils.escapeHtml ) {
				escapedArgs.push( window.SCD.Utils.escapeHtml( argValue ) );
			} else {
				// Basic HTML escaping as fallback
				escapedArgs.push( argValue
					.replace( /&/g, '&amp;' )
					.replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' )
					.replace( /"/g, '&quot;' )
					.replace( /'/g, '&#39;' )
				);
			}
		}

		// Simple sprintf replacement with escaped values
		$.each( escapedArgs, function( index, arg ) {
			var placeholder1 = new RegExp( '%' + ( index + 1 ) + '\\$?[ds]', 'g' );
			var placeholder2 = new RegExp( '\\{' + index + '\\}', 'g' );
			var placeholder3 = new RegExp( '%[ds]' ); // Handle simple %s or %d (only first occurrence)
			message = message.replace( placeholder1, arg );
			message = message.replace( placeholder2, arg );
			if ( 0 === index ) {
				message = message.replace( placeholder3, arg );
			}
		} );

		return message;
	};

	/**
	 * Focus and scroll to first invalid field
	 * @param {jQuery} $container - Container to search for errors
	 * @param {object} [options={}] - Options for focus behavior
	 */
	SCD.Components.ValidationError.focusFirstError = function( $container, options ) {
		options = options || {};
		options = $.extend( {
			scrollOffset: 100,
			animationDuration: 300,
			focusDelay: 10
		}, options );

		// Find first error field
		var $firstError = $container.find( '.error[aria-invalid="true"]' ).first();

		if ( ! $firstError.length ) {
			// Try to find error within radio groups or complex fields
			$firstError = $container.find( '.has-error' ).first().find( 'input, select, textarea' ).first();
		}

		if ( $firstError.length ) {
			// Check if this is a Tom Select field - use wrapper for scroll position
			var $tomSelectWrapper = $firstError.siblings( '.ts-wrapper' );
			var $scrollTarget = $tomSelectWrapper.length ? $tomSelectWrapper : $firstError;

			// Calculate scroll position using visible element
			var scrollTop = $scrollTarget.offset().top - options.scrollOffset;

			// Smooth scroll to field using cached scroll container
			var $scroller = this._$scrollContainer || $( 'html, body' );
			$scroller.animate( {
				scrollTop: scrollTop
			}, options.animationDuration, function() {
				// Focus the field after scroll completes
				setTimeout( function() {
					if ( $firstError.is( ':visible' ) && ! $firstError.is( ':disabled' ) ) {
						$firstError.focus();

						// If it's a select element with Tom Select, focus the Tom Select control
						if ( $firstError.is( 'select' ) && $firstError[0].tomselect ) {
							$firstError[0].tomselect.focus();
						}
					}
				}, options.focusDelay );
			} );

			// Announce to screen readers
			this.announceError( 'Please correct the errors below', 'form' );

			return true;
		}

		return false;
	};

	$( document ).ready( function() {
		SCD.Components.ValidationError.init();
	} );

	// Make component globally available
	window.SCD.ValidationError = SCD.Components.ValidationError;


} )( jQuery );
