/**
 * Review Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/review/review-orchestrator.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.WSSCD = window.WSSCD || {};
	WSSCD.Steps = WSSCD.Steps || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Review = WSSCD.Modules.Review || {};

	/**
	 * Review Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	WSSCD.Steps.ReviewOrchestrator = WSSCD.Shared.BaseOrchestrator.createStep( 'review', {

		/**
		 * Initialize step modules using Module Registry
		 * Called by BaseOrchestrator's initializeModules
		 */
		initializeStep: function() {
			var moduleConfig = WSSCD.Shared.ModuleRegistry.createStepConfig( 'review', {
				components: {
					class: 'WSSCD.Modules.Review.Components',
					deps: ['state', 'api']
				}
			} );
			this.modules = WSSCD.Shared.ModuleRegistry.initialize( moduleConfig, this );
		},

		/**
		 * Custom initialization for review step
		 * The factory's init handles standard setup, this handles review-specific setup
		 * @param wizard
		 * @param config
		 */
		init: function( wizard, config ) {
			this.loadWizardData();
		},

		/**
		 * Custom initialization hook
		 * Called by BaseOrchestrator after standard init
		 */
		onInit: function() {
			// Review step specific initialization
			// Components initialization happens in onStepLoaded
		},

		/**
		 * Custom event binding hook
		 * Called by BaseOrchestrator after standard event binding
		 */
		onBindEvents: function() {
			var self = this;

			// Listen for step initialization
			this.bindCustomEvent( 'wsscd:wizard:step-loaded', function( event, stepName ) {
				if ( 'review' === stepName ) {
					self.onStepLoaded();
				}
			} );

			// Listen for navigation before-complete event
			this.bindCustomEvent( 'wsscd:navigation:before-complete', function() {
				self.storeLaunchOption();
			} );

			this.bindCustomEvent( 'wsscd:review:state:changed', function( e, data ) {
				if ( 'launchOption' === data.property ) {
					if ( self.modules.components && 'function' === typeof self.modules.components.updateNavigationButton ) {
						self.modules.components.updateNavigationButton();
					}
				}
			} );
		},

		/**
		 * Handle step loaded
		 */
		onStepLoaded: function() {
			if ( this.modules.components && 'function' === typeof this.modules.components.init ) {
				this.modules.components.init();
			}

			this.triggerCustomEvent( 'wsscd:review:ready', [ this.modules ] );
		},

		/**
		 * Load wizard data for review
		 */
		loadWizardData: function() {
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.data ) {
				this.modules.state.setState( {
					wizardData: window.WSSCD.Wizard.data
				} );
			}
		},

		/**
		 * Store launch option in wizard data
		 * Note: Status is determined by the PHP compiler service based on launch_option and start_date.
		 * We do NOT set status here to avoid race conditions and ensure single source of truth.
		 */
		storeLaunchOption: function() {
			var launchOption = this.modules.state.getState().launchOption;

			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.data ) {
				window.WSSCD.Wizard.data.launchOption = launchOption;
			}

			return launchOption;
		},

		/**
		 * Collect data - overrides mixin to handle special cases
		 * @override
		 */
		collectData: function() {
			// Call parent method from mixin first
			var data = WSSCD.Mixins.StepPersistence.collectData.call( this );

			// Use state's toJSON for any additional complex data
			if ( this.modules.state && 'function' === typeof this.modules.state.toJSON ) {
				var stateData = this.modules.state.toJSON();
				// Merge any additional state data that might be needed
				data = $.extend( {}, stateData, data );
			}

			return data;
		},

		/**
		 * Validate data - overrides mixin to add business logic validation
		 * @override
		 */
		validateData: function( data ) {
			// Call parent method from mixin first
			var validation = WSSCD.Mixins.StepPersistence.validateData.call( this, data );
			var errors = validation.errors || [];

			if ( this.modules.components && 'function' === typeof this.modules.components.validate ) {
				var componentValidation = this.modules.components.validate();
				if ( !componentValidation.valid && componentValidation.errors ) {
					for ( var i = 0; i < componentValidation.errors.length; i++ ) {
						errors.push( componentValidation.errors[i] );
					}
				}
			}

			return {
				valid: 0 === errors.length,
				errors: errors
			};
		},

		/**
		 * Custom logic after field population
		 * Called by StepPersistence mixin after standard field population
		 * @param {object} savedData - Populated data
		 */
		onPopulateFieldsComplete: function( savedData ) {
			// Only handle UI-specific updates
			var launchOption = this.getPropertyValue( savedData, [ 'launchOption' ] );
			if ( launchOption && this.modules.components && 'function' === typeof this.modules.components.updateNavigationButton ) {
				this.modules.components.updateNavigationButton();
			}
		},

		/**
		 * Get step data
		 */
		getData: function() {
			return this.modules.state ? this.modules.state.getState() : {};
		},

		/**
		 * Set step data
		 * @param data
		 */
		setData: function( data ) {
			if ( this.modules.state ) {
				this.modules.state.setState( data );
			}

			// Also populate fields
			this.populateFields( data );
		},

		/**
		 * Reset step
		 */
		reset: function() {
			if ( this.modules.state ) {
				this.modules.state.reset();
			}

			if ( this.modules.components ) {
				this.modules.components.reset();
			}

			this.triggerCustomEvent( 'wsscd:review:reset', [] );
		},

		/**
		 * Check if step is valid
		 */
		isValid: function() {
			var validation = this.validateData( this.collectData() );
			return validation.valid;
		},

		/**
		 * Check if step has changes
		 */
		isDirty: function() {
			return this.modules.state ? this.modules.state.isDirty() : false;
		},

		/**
		 * Get module instance
		 * @param name
		 */
		getModule: function( name ) {
			return WSSCD.Utils.get( this.modules, name );
		},

		/**
		 * Check if step is ready
		 */
		isReady: function() {
			return !WSSCD.Utils.isEmpty( this.modules ) &&
					this.modules.state &&
					this.modules.api &&
					this.modules.components;
		},

		/**
		 * Custom cleanup hook
		 * Called by BaseOrchestrator destroy
		 */
		onDestroy: function() {
			// Review step specific cleanup
			// Base class handles module destruction and event cleanup
		}
	} );

} )( jQuery );
