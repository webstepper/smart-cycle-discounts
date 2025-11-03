/**
 * Step Loader Factory
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/step-loader-factory.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Wizard = SCD.Wizard || {};

	// Register this module with the ModuleLoader
	if ( window.SCD.ModuleLoader ) {
		SCD.ModuleLoader.modules['step-loader-factory'] = true;
	}

	/**
	 * Step Loader Factory
	 *
	 * Creates loader functions for wizard steps based on configuration
	 */
	SCD.Wizard.StepLoaderFactory = {

		/**
		 * Create a loader function for a specific step
		 *
		 * @param {string} stepName - Name of the step
		 * @param {object} config - Step configuration object
		 * @returns {Function} Loader function for the step
		 */
		createLoader: function( stepName, config ) {
			return function() {
				var orchestratorProperty = '_' + stepName + 'Orchestrator';
				var orchestratorClass = config.orchestratorClass;
				var deferred;

				// Check if orchestrator is already loaded
				if ( SCD.Steps[orchestratorProperty] ) {
					deferred = $.Deferred();
					deferred.resolve();
					return deferred.promise();
				}

				// Check if orchestrator class exists
				if ( !SCD.Steps[orchestratorClass] ) {
					deferred = $.Deferred();
					deferred.reject( new Error( orchestratorClass + ' not available' ) );
					return deferred.promise();
				}

				// Create and initialize orchestrator
				var orchestrator = new SCD.Steps[orchestratorClass]();

				// Store orchestrator reference
				SCD.Steps[orchestratorProperty] = orchestrator;

				// Create the getter/setter for modern implementation
				Object.defineProperty( SCD.Steps, stepName, {
					get: function() {
						return SCD.Steps[orchestratorProperty];
					},
					set: function( value ) {
						SCD.Steps[orchestratorProperty] = value;
					},
					configurable: true
				} );

				// Initialize step if method exists
				if ( 'function' === typeof orchestrator.initializeStep ) {
					orchestrator.initializeStep();
				}

				deferred = $.Deferred();
				deferred.resolve();
				return deferred.promise();
			};
		},

		/**
		 * Register event handlers for a step
		 *
		 * @param {string} stepName - Name of the step
		 * @param {object} config - Step configuration object
		 */
		registerStepEvents: function( stepName, config ) {
			var loaderFunction = 'load' + stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 );

			// Auto-initialize when wizard loads this step
			$( document ).on( 'scd:wizard:stepLoaded', function( e, loadedStepName ) {
				if ( loadedStepName === stepName ) {
					SCD.Steps[loaderFunction]().then( function() {
						// Step loaded successfully
					} ).fail( function( error ) {
						// Trigger error event for wizard to handle
						$( document ).trigger( 'scd:wizard:stepLoadError', [ stepName, error ] );
					} );
				}
			} );

			// Also initialize on DOM ready if we're already on this step
			$( document ).ready( function() {
				// Check if we're on this step using configured selectors
				var isOnStep = false;

				if ( config.domSelectors && 0 < config.domSelectors.length ) {
					for ( var i = 0; i < config.domSelectors.length; i++ ) {
						if ( 0 < $( config.domSelectors[i] ).length ) {
							isOnStep = true;
							break;
						}
					}
				}

				if ( isOnStep ) {
					// Trigger step loaded event
					$( document ).trigger( 'scd:wizard:stepLoaded', [ stepName ] );
				}
			} );
		},

		/**
		 * Initialize all step loaders from configuration
		 */
		init: function() {
			// Ensure Steps namespace exists
			SCD.Steps = SCD.Steps || {};

			// Wait for configuration to be available
			if ( !SCD.Wizard.StepConfig ) {
				console.error( 'SCD: Step configuration not found' );
				return;
			}

			// Create loader functions for each configured step
			for ( var stepName in SCD.Wizard.StepConfig ) {
				if ( Object.prototype.hasOwnProperty.call( SCD.Wizard.StepConfig, stepName ) ) {
					var config = SCD.Wizard.StepConfig[stepName];
					var loaderName = 'load' + stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 );

					// Create the loader function
					SCD.Steps[loaderName] = this.createLoader( stepName, config );

					// Register event handlers
					this.registerStepEvents( stepName, config );
				}
			}
		}
	};

	// Initialize the factory when DOM is ready
	$( document ).ready( function() {
		if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StepLoaderFactory ) {
			SCD.Wizard.StepLoaderFactory.init();
		}
	} );

} )( jQuery );