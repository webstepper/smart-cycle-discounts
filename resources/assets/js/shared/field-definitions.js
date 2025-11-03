/**
 * Field Definitions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/field-definitions.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};

	/**
	 * Field Definitions Registry
	 * 
	 * Field definitions are loaded from PHP via wp_localize_script
	 * and made available through window.scdAdmin.scdFieldDefinitions
	 */
	SCD.FieldDefinitions = ( function() {
		var definitions = {};
		var initialized = false;

		/**
		 * Initialize field definitions
		 */
		function init() {
			if ( initialized ) {
				return;
			}

			// Load definitions from localized data
			if ( window.scdAdmin && window.scdAdmin.scdFieldDefinitions ) {
				definitions = window.scdAdmin.scdFieldDefinitions;
				initialized = true;
			} else {
			}
		}

		/**
		 * Get field definitions for a step
		 *
		 * @param {string} step Step name
		 * @return {object} Field definitions
		 */
		function getStepFields( step ) {
			init();
			return definitions[ step ] || {};
		}

		/**
		 * Get a specific field definition
		 *
		 * @param {string} step Step name
		 * @param {string} field Field name (camelCase)
		 * @return {object|null} Field definition or null
		 */
		function getField( step, field ) {
			var stepFields = getStepFields( step );
			return stepFields[ field ] || null;
		}

		/**
		 * Get field definition by form field name
		 *
		 * @param {string} step Step name
		 * @param {string} fieldName Form field name (snake_case)
		 * @return {object|null} Field definition or null
		 */
		function getFieldByName( step, fieldName ) {
			var stepFields = getStepFields( step );
			
			// Look for field with matching fieldName property
			for ( var key in stepFields ) {
				if ( stepFields.hasOwnProperty( key ) && 
					 stepFields[ key ].fieldName === fieldName ) {
					return stepFields[ key ];
				}
			}

			return null;
		}

		/**
		 * Get all field definitions
		 *
		 * @return {object} All field definitions
		 */
		function getAll() {
			init();
			return definitions;
		}

		/**
		 * Check if a field is required
		 *
		 * @param {string} step Step name
		 * @param {string} field Field name
		 * @return {boolean} True if required
		 */
		function isRequired( step, field ) {
			var fieldDef = getField( step, field );
			return fieldDef && fieldDef.required === true;
		}

		/**
		 * Get validation rules for a field
		 *
		 * @param {string} step Step name
		 * @param {string} field Field name
		 * @return {object} Validation rules
		 */
		function getValidationRules( step, field ) {
			var fieldDef = getField( step, field );
			if ( ! fieldDef ) {
				return {};
			}

			var rules = {
				required: fieldDef.required || false
			};

			// Add numeric rules
			if ( 'undefined' !== typeof fieldDef.min ) {
				rules.min = fieldDef.min;
			}
			if ( 'undefined' !== typeof fieldDef.max ) {
				rules.max = fieldDef.max;
			}

			// Add string length rules
			if ( 'undefined' !== typeof fieldDef.minLength ) {
				rules.minLength = fieldDef.minLength;
			}
			if ( 'undefined' !== typeof fieldDef.maxLength ) {
				rules.maxLength = fieldDef.maxLength;
			}

			return rules;
		}

		// Public API
		return {
			init: init,
			getStepFields: getStepFields,
			getField: getField,
			getFieldByName: getFieldByName,
			getAll: getAll,
			isRequired: isRequired,
			getValidationRules: getValidationRules
		};
	} )();

	// Initialize on document ready
	$( document ).ready( function() {
		SCD.FieldDefinitions.init();
	});

} )( jQuery );