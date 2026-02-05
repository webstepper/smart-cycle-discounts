/**
 * Step Loader Factory
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/step-loader-factory.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Wizard = WSSCD.Wizard || {};

	// Register this module with the ModuleLoader
	if ( window.WSSCD.ModuleLoader ) {
		WSSCD.ModuleLoader.modules['step-loader-factory'] = true;
	}

	/**
	 * Step Loader Factory
	 *
	 * Creates loader functions for wizard steps based on configuration
	 */
	WSSCD.Wizard.StepLoaderFactory = {

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

				if ( WSSCD.Steps[orchestratorProperty] ) {
					deferred = $.Deferred();
					deferred.resolve();
					return deferred.promise();
				}

				if ( !WSSCD.Steps[orchestratorClass] ) {
					deferred = $.Deferred();
					deferred.reject( new Error( orchestratorClass + ' not available' ) );
					return deferred.promise();
				}

				var orchestrator = new WSSCD.Steps[orchestratorClass]();

				WSSCD.Steps[orchestratorProperty] = orchestrator;

				Object.defineProperty( WSSCD.Steps, stepName, {
					get: function() {
						return WSSCD.Steps[orchestratorProperty];
					},
					set: function( value ) {
						WSSCD.Steps[orchestratorProperty] = value;
					},
					configurable: true
				} );

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
			$( document ).on( 'wsscd:wizard:stepLoaded', function( e, loadedStepName ) {
				if ( loadedStepName === stepName ) {
					WSSCD.Steps[loaderFunction]().then( function() {
						// Step loaded successfully
					} ).fail( function( error ) {
						// Trigger error event for wizard to handle
						$( document ).trigger( 'wsscd:wizard:stepLoadError', [ stepName, error ] );
					} );
				}
			} );

			// Also initialize on DOM ready if we're already on this step
			$( document ).ready( function() {
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
					$( document ).trigger( 'wsscd:wizard:stepLoaded', [ stepName ] );
				}
			} );
		},

		/**
		 * Initialize all step loaders from configuration
		 */
		init: function() {
			// Ensure Steps namespace exists
			WSSCD.Steps = WSSCD.Steps || {};

			// Wait for configuration to be available
			if ( !WSSCD.Wizard.StepConfig ) {
				console.error( 'WSSCD: Step configuration not found' );
				return;
			}

			for ( var stepName in WSSCD.Wizard.StepConfig ) {
				if ( Object.prototype.hasOwnProperty.call( WSSCD.Wizard.StepConfig, stepName ) ) {
					var config = WSSCD.Wizard.StepConfig[stepName];
					var loaderName = 'load' + stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 );

					WSSCD.Steps[loaderName] = this.createLoader( stepName, config );

					// Register event handlers
					this.registerStepEvents( stepName, config );
				}
			}
		}
	};

	$( document ).ready( function() {
		if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.StepLoaderFactory ) {
			WSSCD.Wizard.StepLoaderFactory.init();
		}
	} );

} )( jQuery );