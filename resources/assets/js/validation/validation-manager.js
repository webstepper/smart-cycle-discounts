/**
 * Validation Manager
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/validation/validation-manager.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.WSSCD = window.WSSCD || {};

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
			ajaxUrl: ( window.wsscdWizardData && window.wsscdWizardData.ajaxUrl ) || window.ajaxurl || '',
			nonce: ( window.wsscdWizardData && window.wsscdWizardData.nonce ) || '',
			debounceDelay: 150,
			cacheTime: 300000
		};
		
		// State management
		this.timers = {};
		this.validationState = {};
		
		if ( window.wsscdValidationConstants ) {
			if ( window.wsscdValidationConstants.timing ) {
				this.config.debounceDelay = window.wsscdValidationConstants.timing.debounce_delay || 150;
				this.config.cacheTime = window.wsscdValidationConstants.timing.cache_duration || 300000;
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
		
		// CRITICAL FIX: Do NOT skip if undefined (field not in map) - that's fail-open!
		// Only skip if visibility is explicitly set to false
		if ( context.visibilityMap && context.visibilityMap[fieldName] === false ) {
			return result; // Field is explicitly hidden, no validation needed
		}

		var fieldDef = this._getFieldDefinition( fieldName, context );

		// Validation order: required → type → normalize → min/max → pattern → custom

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
			if ( !customResult.ok ) {
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
		if ( ! context.stepId || ! window.WSSCD || ! window.WSSCD.FieldDefinitions ) {
			return null;
		}

		// Field names from validateStep() are camelCase (object keys from stepFields)
		// Use getField() which looks up by camelCase key
		if ( window.WSSCD.FieldDefinitions.getField ) {
			var fieldDef = window.WSSCD.FieldDefinitions.getField( context.stepId, fieldName );
			if ( fieldDef ) {
				return fieldDef;
			}
		}

		// Fallback: Try getFieldByName() in case fieldName is snake_case (from DOM)
		if ( window.WSSCD.FieldDefinitions.getFieldByName ) {
			return window.WSSCD.FieldDefinitions.getFieldByName( context.stepId, fieldName );
		}

		return null;
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
			var trimmed = value.trim();
			// Empty string OR string "0" (treats numeric 0 as "no value")
			// This allows optional number fields with min > 0 to pass validation
			return 0 === trimmed.length || '0' === trimmed;
		}

		if ( Array.isArray( value ) ) {
			return 0 === value.length;
		}

		// For numbers, treat 0 as empty (no value provided)
		// This allows optional number fields with min > 0 to pass validation when empty
		if ( 'number' === typeof value ) {
			return 0 === value;
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

		if ( fieldDef.conditional ) {
			var isVisible = this._evaluateCondition( fieldDef.conditional, context.allValues );
			if ( ! isVisible ) {
				// Field is hidden by conditional logic, skip required validation
				return result;
			}
		}

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
	 * Uses structured format from PHP field definitions: { field: 'fieldName', value: 'expectedValue', operator?: 'equals'|'not_equals' }
	 *
	 * @private
	 * @since 1.0.0
	 * @param {object} condition - Condition object
	 * @param {object} allValues - All field values
	 * @returns {boolean} True if condition is met
	 */
	ValidationManager.prototype._evaluateCondition = function( condition, allValues ) {
		// Structured format: { field: 'fieldName', value: 'expectedValue', operator?: 'not_equals' }
		var fieldName = condition.field;
		var expectedValue = condition.value;
		var operator = condition.operator || 'equals';

		// CRITICAL FIX: Convert field name from snake_case to camelCase
		var camelCaseFieldName = window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.Fields
			? window.WSSCD.Utils.Fields.toCamelCase( fieldName )
			: fieldName;

		var actualValue = allValues[camelCaseFieldName];

		var matches;
		// Array of possible values
		if ( Array.isArray( expectedValue ) ) {
			matches = expectedValue.indexOf( actualValue ) !== -1;
		}
		// Boolean comparison with type coercion
		else if ( 'boolean' === typeof expectedValue ) {
			var actualBoolean = !! actualValue && '0' !== actualValue && 'false' !== actualValue;
			matches = actualBoolean === expectedValue;
		}
		// Single value - strict equality
		else {
			matches = actualValue === expectedValue;
		}

		if ( 'not_equals' === operator ) {
			return ! matches;
		}
		return matches;
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
		
		if ( this.timers[fieldName] ) {
			clearTimeout( this.timers[fieldName] );
		}
		
		this.timers[fieldName] = setTimeout( function() {
			// Build context object for pure validation
			var validationContext = {
				stepId: ( context && context.stepId ) || null,
				allValues: ( context && context.allValues ) || self._collectFormValues( $field.closest( 'form' ) ),
				visibilityMap: ( context && context.visibilityMap ) || self._computeVisibilityMap( $field.closest( 'form' ) )
			};
			
			// Call pure validation
			var result = self.validateField( fieldName, $field.val(), validationContext );
			
			// Handle UI updates separately (side effects)
			if ( !result.ok && window.WSSCD && window.WSSCD.ValidationError ) {
				var errorMessage = result.errors[0] && result.errors[0].message ? 
					result.errors[0].message : 
					(result.errors[0] || 'Invalid value');
				window.WSSCD.ValidationError.show( $field, errorMessage );
			} else if ( result.ok && window.WSSCD && window.WSSCD.ValidationError ) {
				window.WSSCD.ValidationError.clear( $field );
			}
			
			if ( callback ) {
				callback( result );
			}
			
			delete self.timers[fieldName];
		}, this.config.debounceDelay );
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

		// Helper to convert snake_case to camelCase
		var toCamelCase = function( name ) {
			if ( window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.Fields && window.WSSCD.Utils.Fields.toCamelCase ) {
				return window.WSSCD.Utils.Fields.toCamelCase( name );
			}
			return name;
		};

		serializedArray.forEach( function( item ) {
			// Convert snake_case DOM field names to camelCase for JavaScript layer consistency
			var camelName = toCamelCase( item.name );

			// Handle array fields
			if ( item.name.indexOf( '[]' ) !== -1 ) {
				var name = camelName.replace( '[]', '' );
				if ( !values[name] ) {
					values[name] = [];
				}
				values[name].push( item.value );
			} else {
				values[camelName] = item.value;
			}
		} );

		// Include unchecked checkboxes
		$form.find( 'input[type="checkbox"]' ).each( function() {
			var name = this.name;
			if ( name ) {
				var camelName = toCamelCase( name );
				if ( !values.hasOwnProperty( camelName ) ) {
					values[camelName] = '';
				}
			}
		} );

		// CRITICAL FIX: Collect complex fields using the SAME pattern as collectData()
		// Tom Select and other complex components manage state in JavaScript,
		// not in DOM. DOM state lags behind due to async updates.
		// Use the existing collectComplexField() method that persistence already uses.
		if ( window.WSSCD && window.WSSCD.FieldDefinitions ) {
			var stepName = $form.data( 'step' ) || $form.closest( '[data-step]' ).data( 'step' );

			if ( stepName ) {
				// Find the orchestrator for this step
				var orchestratorKey = stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 ) + 'Orchestrator';
				var orchestrator = window.WSSCD.Steps && window.WSSCD.Steps[orchestratorKey];


				if ( orchestrator && 'function' === typeof orchestrator.collectComplexField ) {
					var stepFields = window.WSSCD.FieldDefinitions.getStepFields( stepName ) || {};

					// Collect complex fields using the SAME method as persistence
					for ( var fieldName in stepFields ) {
						if ( stepFields.hasOwnProperty( fieldName ) ) {
							var fieldDef = stepFields[fieldName];

							// Only process complex fields
							if ( 'complex' === fieldDef.type ) {
								// Use the SAME collectComplexField method as persistence
								var complexValue = orchestrator.collectComplexField( fieldDef );

								// Store with camelCase key (JavaScript layer convention)
								if ( complexValue !== null && complexValue !== undefined ) {
									values[fieldName] = complexValue;
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
		
		var formValues = this._collectFormValues( $form );
		
		$form.find( 'input, select, textarea' ).each( function() {
			var $field = $( this );
			var fieldName = $field.attr( 'name' ) || $field.attr( 'id' );
			
			if ( fieldName ) {
				var isVisible = $field.is( ':visible' );
				
				// Also check parent containers
				if ( isVisible ) {
					var $parent = $field.closest( '.form-group, .field-container, .form-row' );
					if ( $parent.length ) {
						isVisible = $parent.is( ':visible' );
					}
				}
				
				if ( isVisible && window.WSSCD && window.WSSCD.FieldDefinitions ) {
					// Try to get field definition
					var stepId = $form.data( 'step' ) || $form.closest( '[data-step]' ).data( 'step' );
					if ( stepId ) {
						// Field names from DOM are snake_case, use getFieldByName() to match
						var fieldDef = window.WSSCD.FieldDefinitions.getFieldByName ? window.WSSCD.FieldDefinitions.getFieldByName( stepId, fieldName ) : window.WSSCD.FieldDefinitions.getField( stepId, fieldName );

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
		// The wizard renders: <div class="wsscd-wizard-content wsscd-wizard-layout" data-step="products">
		var $stepContainer = $( '.wsscd-wizard-content[data-step="' + stepName + '"]' );

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

		
		if ( window.WSSCD && window.WSSCD.FieldDefinitions && window.WSSCD.FieldDefinitions.getStepFields ) {
			var stepFields = window.WSSCD.FieldDefinitions.getStepFields( stepName );


			// CRITICAL FIX: Check if stepFields has actual fields, not just truthy check
			// Empty object {} is truthy but has no fields, which would skip validation
			var hasFields = stepFields && Object.keys( stepFields ).length > 0;


			if ( hasFields ) {
				for ( var fieldName in stepFields ) {
					if ( stepFields.hasOwnProperty( fieldName ) ) {
						// JavaScript layer uses camelCase
						var value = validationContext.allValues[fieldName];

						var fieldResult = this.validateField( fieldName, value, validationContext );

						if ( !fieldResult.ok ) {
							stepResult.ok = false;
							stepResult.errors[fieldName] = fieldResult.errors;
							console.error( '[ValidationManager] - VALIDATION FAILED for', fieldName, ':', fieldResult.errors );
						}

						// Always store clean value
						stepResult.clean[fieldName] = fieldResult.clean;
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
		if ( window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.getValidationMessage ) {
			// Try step-specific message first
			var stepName = this.currentContext ? this.currentContext.split( '.' )[1] : null;
			if ( stepName ) {
				var stepMessage = window.WSSCD.Utils.getValidationMessage( stepName + '.' + key );
				if ( stepMessage && stepMessage !== key ) {
					return this.formatMessage( stepMessage, param );
				}
			}
			
			// Try general message
			var message = window.WSSCD.Utils.getValidationMessage( key );
			if ( message && message !== key ) {
				return this.formatMessage( message, param );
			}
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

			case 'nested_array':
				// Nested array fields (e.g., conditions) should preserve their array value
				// They are already collected and structured correctly
				if ( Array.isArray( value ) ) {
					return value;
				}
				return [];

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


	window.WSSCD.ValidationManager = new ValidationManager();
	
	// Trigger ready event
	$( document ).trigger( 'wsscd:validation:ready' );

} )( jQuery );