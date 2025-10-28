/**
 * Smart Cycle Discounts - Simplified Validation Manager
 *
 * Provides client-side validation using WordPress patterns.
 * Server-side validation through WordPress Settings API is authoritative.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};

	/**
	 * Validation Manager - Simplified Implementation
	 *
	 * Features:
	 * - HTML5 native validation
	 * - WordPress admin notice integration
	 * - Localized validation messages
	 * - Simple field validation
	 */
	function ValidationManager() {
		// Singleton pattern
		if ( ValidationManager.instance ) {
			return ValidationManager.instance;
		}

		// Configuration from localized data
		this.config = {
			ajaxUrl: ( window.scdWizardData && window.scdWizardData.ajax_url ) || window.ajaxurl || '',
			nonce: ( window.scdWizardData && window.scdWizardData.nonce ) || '',
			debounceDelay: 150,
			cacheTime: 300000
		};
		
		// State management
		this.timers = {};
		this.validationState = {};
		
		// Get localized constants if available
		if ( window.scdValidationConstants ) {
			if ( window.scdValidationConstants.timing ) {
				this.config.debounceDelay = window.scdValidationConstants.timing.debounce_delay || 150;
				this.config.cacheTime = window.scdValidationConstants.timing.cache_duration || 300000;
			}
		}

		ValidationManager.instance = this;
	}

	/**
	 * Initialize validation for a context
	 * @since 1.0.0
	 * @param {string} context - Validation context
	 * @returns {Promise}
	 */
	ValidationManager.prototype.init = function( _context ) {
		// Simple initialization - no rules loading needed
		var deferred = $.Deferred();
		deferred.resolve();
		return deferred.promise();
	};

	/**
	 * Validate a single field - PURE function, no side effects
	 * @since 1.0.0
	 * @param {string} fieldName - Field name
	 * @param {*} value - Field value
	 * @param {object} context - Validation context
	 * @returns {object} { ok: boolean, clean: *, errors: Array<{code: string, message: string}> }
	 */
	ValidationManager.prototype.validateField = function( fieldName, value, context ) {
		// Initialize context with defaults
		context = $.extend( {
			stepId: null,
			allValues: {},
			visibilityMap: null,
			locale: 'en_US'
		}, context || {} );
		
		// Result object
		var result = {
			ok: true,
			clean: value,
			errors: []
		};
		
		// Check visibility first - skip validation ONLY if field is EXPLICITLY false (hidden)
		// CRITICAL FIX: Do NOT skip if undefined (field not in map) - that's fail-open!
		// Only skip if visibility is explicitly set to false
		if ( context.visibilityMap && context.visibilityMap[fieldName] === false ) {
			return result; // Field is explicitly hidden, no validation needed
		}

		// Get field definition
		var fieldDef = this._getFieldDefinition( fieldName, context );

		// Fixed validation order: required → type → normalize → min/max → pattern → custom

		// 1. Required validation
		var requiredResult = this._validateRequired( fieldName, value, fieldDef, context );

		if ( ! requiredResult.ok ) {
			result.ok = false;
			result.errors = requiredResult.errors;
			return result;
		}
		
		// If no value and not required, valid
		if ( this._isEmpty( value ) ) {
			return result;
		}
		
		// 2. Type validation and normalization
		if ( fieldDef && fieldDef.type ) {
			var typeResult = this._validateType( value, fieldDef.type, fieldName );
			if ( !typeResult.ok ) {
				result.ok = false;
				result.errors.push( typeResult.error );
				return result;
			}
			result.clean = typeResult.clean; // Use normalized value
			value = typeResult.clean; // Continue with normalized value
		}
		
		// 3. Min/Max validation
		if ( fieldDef ) {
			// Determine which properties to check based on field type
			var fieldType = fieldDef.type || '';
			var isTextType = fieldType === 'text' || fieldType === 'string';

			// Min validation - check minLength for text, min for numbers
			var minValue = isTextType ? fieldDef.minLength : fieldDef.min;
			if ( minValue !== undefined ) {
				var minResult = this._validateMin( value, minValue, fieldDef.type );
				if ( !minResult.ok ) {
					result.ok = false;
					result.errors.push( {
						code: isTextType ? 'min_length' : 'min',
						message: isTextType ?
							this.getMessage( 'min_length', minValue ) :
							this.getMessage( 'min_value', minValue )
					} );
					return result;
				}
			}

			// Max validation - check maxLength for text, max for numbers
			var maxValue = isTextType ? fieldDef.maxLength : fieldDef.max;
			if ( maxValue !== undefined ) {
				var maxResult = this._validateMax( value, maxValue, fieldDef.type );
				if ( !maxResult.ok ) {
					result.ok = false;
					result.errors.push( {
						code: isTextType ? 'max_length' : 'max',
						message: isTextType ?
							this.getMessage( 'max_length', maxValue ) :
							this.getMessage( 'max_value', maxValue )
					} );
					return result;
				}
			}
		}
		
		// 4. Pattern validation
		if ( fieldDef && fieldDef.pattern ) {
			var patternResult = this._validatePattern( value, fieldDef.pattern );
			if ( !patternResult.ok ) {
				result.ok = false;
				result.errors.push( {
					code: 'pattern',
					message: fieldDef.messages && fieldDef.messages.pattern ? 
						fieldDef.messages.pattern : 
						this.getMessage( 'invalid_format', fieldName )
				} );
				return result;
			}
		}
		
		// 5. Custom validation
		if ( fieldDef && fieldDef.validate ) {
			var customResult = fieldDef.validate( value, fieldDef, context.allValues );
			// Handle both old format (valid property) and new format (ok property)
			var isValid = customResult.ok !== undefined ? customResult.ok : customResult.valid;
			if ( !isValid ) {
				result.ok = false;
				result.errors.push( {
					code: 'custom',
					message: customResult.message || 'Invalid value'
				} );
				return result;
			}
		}
		
		return result;
	};

	/**
	 * Get field definition from field definitions
	 * @private
	 * @since 1.0.0
	 * @param {string} fieldName - Field name
	 * @param {object} context - Validation context
	 * @returns {object|null} Field definition or null
	 */
	ValidationManager.prototype._getFieldDefinition = function( fieldName, context ) {
		if ( ! context.stepId || ! window.SCD || ! window.SCD.FieldDefinitions || ! window.SCD.FieldDefinitions.getField ) {
			return null;
		}
		
		// Convert fieldName to camelCase for field definitions lookup
		var camelCaseName = window.SCD.Utils && window.SCD.Utils.snakeToCamelCase ? 
			window.SCD.Utils.snakeToCamelCase( fieldName ) : fieldName;
			
		return window.SCD.FieldDefinitions.getField( context.stepId, camelCaseName );
	};

	/**
	 * Check if value is empty
	 * @private
	 * @since 1.0.0
	 * @param {*} value - Value to check
	 * @returns {boolean} True if empty
	 */
	ValidationManager.prototype._isEmpty = function( value ) {
		if ( value === null || value === undefined ) {
			return true;
		}
		
		if ( 'string' === typeof value ) {
			return 0 === value.trim().length;
		}
		
		if ( Array.isArray( value ) ) {
			return 0 === value.length;
		}
		
		return false === value || '' === value;
	};

	/**
	 * Validate required field
	 * @private
	 * @since 1.0.0
	 * @param {string} fieldName - Field name
	 * @param {*} value - Field value
	 * @param {object} fieldDef - Field definition
	 * @param {object} context - Validation context
	 * @returns {object} Validation result
	 */
	ValidationManager.prototype._validateRequired = function( fieldName, value, fieldDef, context ) {
		var result = { ok: true, errors: [] };

		if ( ! fieldDef || ! fieldDef.required ) {
			return result;
		}

		// Check if field is conditionally visible - skip validation if condition not met
		if ( fieldDef.conditional ) {
			var isVisible = this._evaluateCondition( fieldDef.conditional, context.allValues );
			if ( ! isVisible ) {
				// Field is hidden by conditional logic, skip required validation
				return result;
			}
		}

		// Check required
		if ( this._isEmpty( value ) ) {
			result.ok = false;
			result.errors.push( {
				code: 'required',
				message: this.getMessage( 'required', fieldName )
			} );
		}

		return result;
	};

	/**
	 * Helper: Evaluate condition for conditional field visibility
	 *
	 * Supports two formats:
	 * 1. Structured format (from PHP field definitions): { field: 'fieldName', value: 'expectedValue' }
	 * 2. Legacy flat format: { 'fieldName': 'expectedValue' }
	 *
	 * @private
	 * @since 1.0.0
	 * @param {object} condition - Condition object
	 * @param {object} allValues - All field values
	 * @returns {boolean} True if condition is met
	 */
	ValidationManager.prototype._evaluateCondition = function( condition, allValues ) {
		// Handle structured format: { field: 'fieldName', value: 'expectedValue' }
		// This is the format from PHP field definitions after export_for_js conversion
		if ( condition.field && condition.hasOwnProperty( 'value' ) ) {
			var fieldName = condition.field;
			var expectedValue = condition.value;

			// Try to get value with multiple naming conventions
			// collectData() returns camelCase keys, but field definitions use snake_case
			var actualValue = allValues[fieldName]; // Try original name first (snake_case from PHP)

			// If not found, try snake_case conversion
			if ( undefined === actualValue ) {
				var htmlFieldName = window.SCD && window.SCD.Utils && window.SCD.Utils.camelToSnakeCase ?
					window.SCD.Utils.camelToSnakeCase( fieldName ) : fieldName;
				actualValue = allValues[htmlFieldName];
			}

			// Array of possible values
			if ( Array.isArray( expectedValue ) ) {
				return expectedValue.indexOf( actualValue ) !== -1;
			}
			// Boolean comparison with type coercion
			else if ( 'boolean' === typeof expectedValue ) {
				// Coerce actual value to boolean for comparison
				// Handle checkbox values: '1', 1, true, 'true' -> true
				// Handle: '0', 0, false, 'false', '', null, undefined -> false
				var actualBoolean = !! actualValue && '0' !== actualValue && 'false' !== actualValue;
				return actualBoolean === expectedValue;
			}
			// Single value - strict equality
			else {
				return actualValue === expectedValue;
			}
		}

		// Handle legacy flat format: { 'fieldName': 'expectedValue' }
		// Iterate through all conditions (all must be true for AND logic)
		for ( var field in condition ) {
			if ( condition.hasOwnProperty( field ) ) {
				var expectedValue = condition[field];
				var actualValue = allValues[field];

				// Array of possible values
				if ( Array.isArray( expectedValue ) ) {
					if ( expectedValue.indexOf( actualValue ) === -1 ) {
						return false;
					}
				}
				// Boolean comparison with type coercion
				else if ( 'boolean' === typeof expectedValue ) {
					// Coerce actual value to boolean for comparison
					// Handle checkbox values: '1', 1, true, 'true' -> true
					// Handle: '0', 0, false, 'false', '', null, undefined -> false
					var actualBoolean = !! actualValue && '0' !== actualValue && 'false' !== actualValue;
					if ( actualBoolean !== expectedValue ) {
						return false;
					}
				}
				// Single value - strict equality
				else if ( actualValue !== expectedValue ) {
					return false;
				}
			}
		}
		return true;
	};
	
	/**
	 * Helper: Validate and normalize type
	 * @private
	 * @since 1.0.0
	 * @param {*} value - Value to validate
	 * @param {string} type - Field type
	 * @param {string} fieldName - Field name for error messages
	 * @returns {object} { ok: boolean, clean: *, error: object }
	 */
	ValidationManager.prototype._validateType = function( value, type, _fieldName ) {
		var result = { ok: true, clean: value, error: null };
		
		switch ( type ) {
			case 'email':
				var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if ( !emailRegex.test( value ) ) {
					result.ok = false;
					result.error = {
						code: 'invalid_email',
						message: this.getMessage( 'invalid_email' )
					};
				} else {
					result.clean = value.toLowerCase().trim();
				}
				break;
				
			case 'number':
				var num = parseFloat( value );
				if ( isNaN( num ) ) {
					result.ok = false;
					result.error = {
						code: 'invalid_number',
						message: this.getMessage( 'invalid_number' )
					};
				} else {
					result.clean = num;
				}
				break;
				
			case 'integer':
				var int = parseInt( value, 10 );
				if ( isNaN( int ) || int !== parseFloat( value ) ) {
					result.ok = false;
					result.error = {
						code: 'invalid_integer',
						message: this.getMessage( 'invalid_integer' )
					};
				} else {
					result.clean = int;
				}
				break;
				
			case 'text':
			case 'string':
				result.clean = String( value ).trim();
				break;
				
			case 'array':
				if ( !Array.isArray( value ) ) {
					// Try to convert if it's a string representation
					if ( typeof value === 'string' && value.indexOf( ',' ) !== -1 ) {
						result.clean = value.split( ',' ).map( function( v ) { return v.trim(); } );
					} else {
						result.ok = false;
						result.error = {
							code: 'invalid_array',
							message: this.getMessage( 'invalid_array' )
						};
					}
				}
				break;
				
			case 'boolean':
				result.clean = value === true || value === 'true' || value === '1' || value === 1;
				break;
				
			default:
				// No specific type validation
				break;
		}
		
		return result;
	};
	
	/**
	 * Helper: Validate minimum value/length
	 * @private
	 * @since 1.0.0
	 * @param {*} value - Value to validate
	 * @param {*} min - Minimum value
	 * @param {string} type - Field type
	 * @returns {object} { ok: boolean }
	 */
	ValidationManager.prototype._validateMin = function( value, min, type ) {
		if ( type === 'number' || type === 'integer' ) {
			return { ok: value >= min };
		} else if ( type === 'text' || type === 'string' ) {
			return { ok: value.length >= min };
		} else if ( type === 'array' ) {
			return { ok: Array.isArray( value ) && value.length >= min };
		}
		return { ok: true };
	};
	
	/**
	 * Helper: Validate maximum value/length
	 * @private
	 * @since 1.0.0
	 * @param {*} value - Value to validate
	 * @param {*} max - Maximum value
	 * @param {string} type - Field type
	 * @returns {object} { ok: boolean }
	 */
	ValidationManager.prototype._validateMax = function( value, max, type ) {
		if ( type === 'number' || type === 'integer' ) {
			return { ok: value <= max };
		} else if ( type === 'text' || type === 'string' ) {
			return { ok: value.length <= max };
		} else if ( type === 'array' ) {
			return { ok: Array.isArray( value ) && value.length <= max };
		}
		return { ok: true };
	};
	
	/**
	 * Helper: Validate pattern
	 * @private
	 * @since 1.0.0
	 * @param {*} value - Value to validate
	 * @param {RegExp|string} pattern - Pattern to match
	 * @returns {object} { ok: boolean }
	 */
	ValidationManager.prototype._validatePattern = function( value, pattern ) {
		if ( typeof pattern === 'string' ) {
			pattern = new RegExp( pattern );
		}
		return { ok: pattern.test( value ) };
	};

	/**
	 * Validate field with debouncing - NOW calls pure validation
	 * @param {HTMLElement} field - Field element
	 * @param {object} context - Validation context
	 * @param {Function} callback - Optional callback
	 */
	ValidationManager.prototype.validateFieldDebounced = function( field, context, callback ) {
		var self = this;
		var $field = $( field );
		var fieldName = $field.attr( 'name' ) || $field.attr( 'id' );
		
		if ( ! fieldName ) {
			return;
		}
		
		// Clear existing timer
		if ( this.timers[fieldName] ) {
			clearTimeout( this.timers[fieldName] );
		}
		
		// Set new timer
		this.timers[fieldName] = setTimeout( function() {
			// Build context object for pure validation
			var validationContext;
			if ( context && typeof context === 'object' ) {
				// Already object format - use it but fill in missing values
				validationContext = {
					stepId: context.stepId || null,
					allValues: context.allValues || self._collectFormValues( $field.closest( 'form' ) ),
					visibilityMap: context.visibilityMap || self._computeVisibilityMap( $field.closest( 'form' ) )
				};
			} else {
				// Legacy string format - convert
				validationContext = {
					stepId: context && typeof context === 'string' ? context.split( '.' )[1] : null,
					allValues: self._collectFormValues( $field.closest( 'form' ) ),
					visibilityMap: self._computeVisibilityMap( $field.closest( 'form' ) )
				};
			}
			
			// Call pure validation
			var result = self.validateField( fieldName, $field.val(), validationContext );
			
			// Handle UI updates separately (side effects)
			if ( !result.ok && window.SCD && window.SCD.ValidationError ) {
				// Show first error with safe message extraction
				var errorMessage = result.errors[0] && result.errors[0].message ? 
					result.errors[0].message : 
					(result.errors[0] || 'Invalid value');
				window.SCD.ValidationError.show( $field, errorMessage );
			} else if ( result.ok && window.SCD && window.SCD.ValidationError ) {
				window.SCD.ValidationError.clear( $field );
			}
			
			if ( callback ) {
				callback( result );
			}
			
			delete self.timers[fieldName];
		}, this.config.debounceDelay );
	};

	/**
	 * Attach validation to a form
	 * @param {jQuery} $form - Form element
	 * @param {string} context - Validation context
	 */
	ValidationManager.prototype.attachToForm = function( $form, context ) {
		var self = this;
		
		if ( ! $form || ! $form.length ) {
			return;
		}
		
		// Prevent duplicate bindings
		if ( $form.data( 'scd-validation-attached' ) ) {
			return;
		}
		
		$form.data( 'scd-validation-attached', true );
		
		// Real-time field validation
		$form.on( 'blur', 'input:not([type="submit"]):not([type="button"]):not([type="radio"]):not([type="checkbox"]), select, textarea', function( e ) {
			self.validateFieldDebounced( e.target, context );
		} );
		
		// Radio and checkbox validation on change
		$form.on( 'change', 'input[type="radio"], input[type="checkbox"]', function( e ) {
			self.validateFieldDebounced( e.target, context );
		} );
		
		// Clear validation on focus
		$form.on( 'focus', 'input:not([type="submit"]):not([type="button"]), select, textarea', function() {
			if ( window.SCD && window.SCD.ValidationError ) {
				window.SCD.ValidationError.clear( $( this ) );
			}
		} );
		
		// Form submit validation
		$form.on( 'submit', function( e ) {
			var isValid = self.validateForm( $form, context );
			if ( ! isValid ) {
				e.preventDefault();
				// Focus first error field
				var $firstError = $form.find( '.has-error' ).first();
				if ( $firstError.length ) {
					$firstError.find( 'input, select, textarea' ).first().focus();
				}
			}
		} );
	};

	/**
	 * Validate entire form - returns validation results without side effects
	 * @param {jQuery} $form - Form element
	 * @param {string|object} context - Validation context
	 * @returns {object} { ok: boolean, errors: object, clean: object }
	 */
	ValidationManager.prototype.validateForm = function( $form, context ) {
		var self = this;
		var formResult = {
			ok: true,
			errors: {},
			clean: {}
		};
		
		// Build full context
		var validationContext = {
			stepId: context && typeof context === 'string' ? context.split( '.' )[1] : 
					(context && context.stepId ? context.stepId : null),
			allValues: this._collectFormValues( $form ),
			visibilityMap: this._computeVisibilityMap( $form )
		};
		
		// Validate each field
		$form.find( 'input:not([type="submit"]):not([type="button"]), select, textarea' ).each( function() {
			var $field = $( this );
			var fieldName = $field.attr( 'name' ) || $field.attr( 'id' );
			
			if ( fieldName && !$field.prop( 'disabled' ) ) {
				var result = self.validateField( fieldName, $field.val(), validationContext );
				if ( !result.ok ) {
					formResult.ok = false;
					formResult.errors[fieldName] = result.errors;
				}
				// Always store clean value
				formResult.clean[fieldName] = result.clean;
			}
		} );
		
		return formResult;
	};
	
	/**
	 * Helper: Collect all form values
	 * @private
	 * @param {jQuery} $form - Form element
	 * @returns {object} Form values
	 */
	ValidationManager.prototype._collectFormValues = function( $form ) {

		var values = {};

		if ( !$form || !$form.length ) {
			return values;
		}

		var serializedArray = $form.serializeArray();

		serializedArray.forEach( function( item ) {
			// Handle array fields
			if ( item.name.indexOf( '[]' ) !== -1 ) {
				var name = item.name.replace( '[]', '' );
				if ( !values[name] ) {
					values[name] = [];
				}
				values[name].push( item.value );
			} else {
				values[item.name] = item.value;
			}
		} );

		// Include unchecked checkboxes
		$form.find( 'input[type="checkbox"]' ).each( function() {
			var name = this.name;
			if ( name && !values.hasOwnProperty( name ) ) {
				values[name] = '';
			}
		} );

		// CRITICAL FIX: Collect complex fields using the SAME pattern as collectData()
		// Tom Select and other complex components manage state in JavaScript,
		// not in DOM. DOM state lags behind due to async updates.
		// Use the existing collectComplexField() method that persistence already uses.
		if ( window.SCD && window.SCD.FieldDefinitions ) {
			// Get the step name from the form
			var stepName = $form.data( 'step' ) || $form.closest( '[data-step]' ).data( 'step' );

			if ( stepName ) {
				// Find the orchestrator for this step
				var orchestratorKey = stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 ) + 'Orchestrator';
				var orchestrator = window.SCD.Steps && window.SCD.Steps[orchestratorKey];


				if ( orchestrator && 'function' === typeof orchestrator.collectComplexField ) {
					// Get field definitions for this step
					var stepFields = window.SCD.FieldDefinitions.getStepFields( stepName ) || {};

					// Collect complex fields using the SAME method as persistence
					for ( var fieldName in stepFields ) {
						if ( stepFields.hasOwnProperty( fieldName ) ) {
							var fieldDef = stepFields[fieldName];

							// Only process complex fields
							if ( 'complex' === fieldDef.type ) {
								// Convert to snake_case for HTML field names
								var htmlFieldName = window.SCD.Utils && window.SCD.Utils.camelToSnakeCase
									? window.SCD.Utils.camelToSnakeCase( fieldName )
									: fieldName;

								// Use the SAME collectComplexField method as persistence
								var complexValue = orchestrator.collectComplexField( fieldDef );

								if ( complexValue !== null && complexValue !== undefined ) {
									values[htmlFieldName] = complexValue;
								}
							}
						}
					}
				}
			}
		}


		return values;
	};
	
	/**
	 * Helper: Compute visibility map for fields
	 * @private
	 * @param {jQuery} $form - Form element
	 * @returns {object} Visibility map { fieldName: boolean }
	 */
	ValidationManager.prototype._computeVisibilityMap = function( $form ) {
		var visibilityMap = {};
		
		if ( !$form || !$form.length ) {
			return visibilityMap;
		}
		
		// Get current form values for condition evaluation
		var formValues = this._collectFormValues( $form );
		
		// Check each field's visibility
		$form.find( 'input, select, textarea' ).each( function() {
			var $field = $( this );
			var fieldName = $field.attr( 'name' ) || $field.attr( 'id' );
			
			if ( fieldName ) {
				// Check if field is visible in DOM
				var isVisible = $field.is( ':visible' );
				
				// Also check parent containers
				if ( isVisible ) {
					var $parent = $field.closest( '.form-group, .field-container, .form-row' );
					if ( $parent.length ) {
						isVisible = $parent.is( ':visible' );
					}
				}
				
				// Check field definition for conditional visibility
				if ( isVisible && window.SCD && window.SCD.FieldDefinitions ) {
					// Try to get field definition
					var stepId = $form.data( 'step' ) || $form.closest( '[data-step]' ).data( 'step' );
					if ( stepId ) {
						var camelCaseName = SCD.Utils.snakeToCamelCase ? SCD.Utils.snakeToCamelCase( fieldName ) : fieldName;
						var fieldDef = window.SCD.FieldDefinitions.getField( stepId, camelCaseName );

						if ( fieldDef && fieldDef.conditional ) {
							isVisible = this._evaluateCondition( fieldDef.conditional, formValues );
						}
					}
				}
				
				visibilityMap[fieldName] = isVisible;
			}
		}.bind( this ) );
		
		return visibilityMap;
	};

	/**
	 * Validate wizard step - PURE function returning validation results
	 * @param {string} stepName - Step name
	 * @param {object} allStepData - Optional: all step data for cross-field validation
	 * @returns {object} { ok: boolean, errors: object, clean: object }
	 */
	ValidationManager.prototype.validateStep = function( stepName, allStepData ) {

		var stepResult = {
			ok: true,
			errors: {},
			clean: {}
		};

		// Find step container using the ACTUAL DOM structure from PHP
		// The wizard renders: <div class="scd-wizard-content scd-wizard-layout" data-step="products">
		var $stepContainer = $( '.scd-wizard-content[data-step="' + stepName + '"]' );

		if ( !$stepContainer.length ) {
			// CRITICAL FIX: Container not found - validation MUST fail for safety
			// Never assume valid when we cannot verify - fail closed, not fail open
			console.error( '[ValidationManager] Step container not found for step:', stepName );
			return {
				ok: false,
				errors: {
					_system: [ {
						code: 'container_not_found',
						message: 'Cannot validate: Step container not found. Please refresh the page.'
					} ]
				},
				clean: {}
			};
		}

		var $form = $stepContainer.find( 'form' ).first();

		// Build validation context
		var validationContext = {
			stepId: stepName,
			allValues: allStepData || this._collectFormValues( $form ),
			visibilityMap: this._computeVisibilityMap( $form )
		};

		
		// Get field definitions for this step
		if ( window.SCD && window.SCD.FieldDefinitions && window.SCD.FieldDefinitions.getStepFields ) {
			var stepFields = window.SCD.FieldDefinitions.getStepFields( stepName );


			// CRITICAL FIX: Check if stepFields has actual fields, not just truthy check
			// Empty object {} is truthy but has no fields, which would skip validation
			var hasFields = stepFields && Object.keys( stepFields ).length > 0;


			if ( hasFields ) {
				// Validate each field defined in the schema
				for ( var fieldName in stepFields ) {
					if ( stepFields.hasOwnProperty( fieldName ) ) {
						// Convert to snake_case for HTML field lookup
						var htmlFieldName = SCD.Utils.camelToSnakeCase ?
							SCD.Utils.camelToSnakeCase( fieldName ) : fieldName;

						var value = validationContext.allValues[htmlFieldName];


						var fieldResult = this.validateField( htmlFieldName, value, validationContext );


						if ( !fieldResult.ok ) {
							stepResult.ok = false;
							stepResult.errors[htmlFieldName] = fieldResult.errors;
							console.error( '[ValidationManager] - VALIDATION FAILED for', htmlFieldName, ':', fieldResult.errors );
						}

						// Always store clean value
						stepResult.clean[htmlFieldName] = fieldResult.clean;
					}
				}
			} else {
				// CRITICAL: No field definitions found - FAIL SAFE
				// Never assume valid when we have no validation rules
				console.error( '[ValidationManager] No field definitions found for step:', stepName );
				return {
					ok: false,
					errors: {
						_system: [ {
							code: 'no_field_definitions',
							message: 'Cannot validate: Field definitions not loaded. Please refresh the page.'
						} ]
					},
					clean: {}
				};
			}
		} else {
			// Fallback: validate all form fields in step
			var formResult = this.validateForm( $stepContainer.find( 'form' ).first(), validationContext );
			stepResult.ok = formResult.ok;
			stepResult.errors = formResult.errors;
			stepResult.clean = formResult.clean;
		}


		return stepResult;
	};

	/**
	 * Get validation message
	 * @param {string} key - Message key
	 * @param {*} param - Optional parameter
	 * @returns {string} Formatted message
	 */
	ValidationManager.prototype.getMessage = function( key, param ) {
		// Try to get message from PHP localization using utility function
		if ( window.SCD && window.SCD.Utils && window.SCD.Utils.getValidationMessage ) {
			// Try step-specific message first
			var stepName = this.currentContext ? this.currentContext.split( '.' )[1] : null;
			if ( stepName ) {
				var stepMessage = window.SCD.Utils.getValidationMessage( stepName + '.' + key );
				if ( stepMessage && stepMessage !== key ) {
					return this.formatMessage( stepMessage, param );
				}
			}
			
			// Try general message
			var message = window.SCD.Utils.getValidationMessage( key );
			if ( message && message !== key ) {
				return this.formatMessage( message, param );
			}
		}
		
		// Use localized messages if available (backward compatibility)
		if ( window.scdValidationMessages && window.scdValidationMessages[key] ) {
			return this.formatMessage( window.scdValidationMessages[key], param );
		}
		
		// Fallback messages with machine codes
		var messages = {
			required: 'This field is required',
			invalid_email: 'Please enter a valid email address',
			invalid_number: 'Please enter a valid number',
			invalid_integer: 'Please enter a whole number',
			invalid_array: 'Invalid array format',
			invalid_format: 'Invalid format',
			min_value: 'Must be at least %s',
			max_value: 'Cannot exceed %s',
			min: 'Must be at least %s',
			max: 'Cannot exceed %s',
			min_length: 'Must be at least %s characters',
			max_length: 'Must not exceed %s characters',
			pattern: 'Invalid format',
			custom: 'Invalid value',
			invalid_date: 'Please enter a valid date'
		};
		
		return this.formatMessage( messages[key] || 'Invalid value', param );
	};
	
	/**
	 * Sanitize field value - PURE function for data cleaning
	 * @param {*} value - Raw field value
	 * @param {string} type - Field type
	 * @returns {*} Sanitized value
	 */
	ValidationManager.prototype.sanitizeFieldValue = function( value, type ) {
		if ( !value && value !== 0 && value !== false ) {
			return '';
		}
		
		switch ( type ) {
			case 'email':
				return String( value ).toLowerCase().trim();
				
			case 'number':
				var num = parseFloat( value );
				return isNaN( num ) ? 0 : num;
				
			case 'integer':
				var int = parseInt( value, 10 );
				return isNaN( int ) ? 0 : int;
				
			case 'text':
			case 'string':
				return String( value ).trim();
				
			case 'array':
				if ( Array.isArray( value ) ) {
					return value;
				}
				if ( typeof value === 'string' && value.indexOf( ',' ) !== -1 ) {
					return value.split( ',' ).map( function( v ) { return v.trim(); } );
				}
				return [];
				
			case 'boolean':
				return value === true || value === 'true' || value === '1' || value === 1;
				
			case 'complex':
				// Complex fields should preserve their original value
				// They handle their own sanitization internally
				return value;
				
			default:
				return String( value ).trim();
		}
	};
	
	/**
	 * Format message with parameters
	 * @param {string} message - Message template
	 * @param {*} param - Optional parameter
	 * @returns {string} Formatted message
	 */
	ValidationManager.prototype.formatMessage = function( message, param ) {
		if ( param !== undefined ) {
			// Support both %s and %d placeholders, as well as {value} style
			message = message.replace( /%s/g, param )
						.replace( /%d/g, param )
						.replace( /{value}/g, param );
		}
		return message;
	};


	/**
	 * Clear validation state
	 * @param {jQuery} $form - Form element
	 */
	ValidationManager.prototype.clearValidation = function( $form ) {
		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.clearAll( $form );
		}
		this.validationState = {};
	};

	/**
	 * Get form data
	 * @param {jQuery} $form - Form element
	 * @returns {object} Form data
	 */
	ValidationManager.prototype.getFormData = function( $form ) {
		var data = {};
		
		if ( ! $form || ! $form.length ) {
			return data;
		}
		
		$form.serializeArray().forEach( function( item ) {
			data[item.name] = item.value;
		} );
		
		return data;
	};
	

	// Create and expose instance
	window.SCD.ValidationManager = new ValidationManager();
	
	// Trigger ready event
	$( document ).trigger( 'scd:validation:ready' );

} )( jQuery );