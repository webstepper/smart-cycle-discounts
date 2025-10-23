/**
 * Step Registry
 *
 * Provides factory registration for wizard steps
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};

	// Step factory registry
	SCD.Steps._factories = SCD.Steps._factories || {};

	/**
	 * Register a step factory
	 *
	 * @param {string} stepName - Name of the step
	 * @param {Function} factory - Factory function that returns a step instance
	 */
	SCD.Steps.registerFactory = function( stepName, factory ) {
		if ( 'function' !== typeof factory ) {
			throw new Error( 'Factory for step "' + stepName + '" must be a function, got: ' + typeof factory );
		}

		if ( !stepName || 'string' !== typeof stepName ) {
			throw new Error( 'Step name must be a non-empty string, got: ' + typeof stepName );
		}

		SCD.Steps._factories[stepName] = factory;

		// Emit registration event for debugging
		if ( 'undefined' !== typeof $ ) {
			$( document ).trigger( 'scd:step:registered', [ stepName ] );
		}
	};

	/**
	 * Create a step instance from factory
	 *
	 * @param {string} stepName - Name of the step
	 * @returns {object | null} Step instance or null if not found
	 */
	SCD.Steps.createStep = function( stepName ) {
		var factory = SCD.Steps._factories[stepName];

		if ( !factory ) {
			throw new Error( 'No factory registered for step: ' + stepName );
		}

		try {
			var instance = factory();

			// Validate that factory returned a valid instance
			if ( !instance || 'object' !== typeof instance ) {
				throw new Error( 'Factory for step "' + stepName + '" must return an object, got: ' + typeof instance );
			}

			// Emit creation event for debugging
			if ( 'undefined' !== typeof $ ) {
				$( document ).trigger( 'scd:step:created', [ stepName, instance ] );
			}

			return instance;
		} catch ( error ) {
			throw new Error( 'Failed to create step "' + stepName + '": ' + error.message );
		}
	};

	/**
	 * Check if a step factory is registered
	 *
	 * @param {string} stepName - Name of the step
	 * @returns {boolean} True if factory exists
	 */
	SCD.Steps.hasFactory = function( stepName ) {
		return Object.prototype.hasOwnProperty.call( SCD.Steps._factories, stepName );
	};

	/**
	 * Get all registered step names
	 *
	 * @returns {string[]} Array of step names
	 */
	SCD.Steps.getRegisteredSteps = function() {
		return Object.keys( SCD.Steps._factories );
	};

} )( jQuery );