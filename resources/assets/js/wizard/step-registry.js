/**
 * Step Registry
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/step-registry.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Steps = WSSCD.Steps || {};

	// Step factory registry
	WSSCD.Steps._factories = WSSCD.Steps._factories || {};

	/**
	 * Register a step factory
	 *
	 * @param {string} stepName - Name of the step
	 * @param {Function} factory - Factory function that returns a step instance
	 */
	WSSCD.Steps.registerFactory = function( stepName, factory ) {
		if ( 'function' !== typeof factory ) {
			throw new Error( 'Factory for step "' + stepName + '" must be a function, got: ' + typeof factory );
		}

		if ( !stepName || 'string' !== typeof stepName ) {
			throw new Error( 'Step name must be a non-empty string, got: ' + typeof stepName );
		}

		WSSCD.Steps._factories[stepName] = factory;

		// Emit registration event for debugging
		if ( 'undefined' !== typeof $ ) {
			$( document ).trigger( 'wsscd:step:registered', [ stepName ] );
		}
	};

	/**
	 * Create a step instance from factory
	 *
	 * @param {string} stepName - Name of the step
	 * @returns {object | null} Step instance or null if not found
	 */
	WSSCD.Steps.createStep = function( stepName ) {
		var factory = WSSCD.Steps._factories[stepName];

		if ( !factory ) {
			throw new Error( 'No factory registered for step: ' + stepName );
		}

		try {
			var instance = factory();

			if ( !instance || 'object' !== typeof instance ) {
				throw new Error( 'Factory for step "' + stepName + '" must return an object, got: ' + typeof instance );
			}

			// Emit creation event for debugging
			if ( 'undefined' !== typeof $ ) {
				$( document ).trigger( 'wsscd:step:created', [ stepName, instance ] );
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
	WSSCD.Steps.hasFactory = function( stepName ) {
		return Object.prototype.hasOwnProperty.call( WSSCD.Steps._factories, stepName );
	};

	/**
	 * Get all registered step names
	 *
	 * @returns {string[]} Array of step names
	 */
	WSSCD.Steps.getRegisteredSteps = function() {
		return Object.keys( WSSCD.Steps._factories );
	};

} )( jQuery );