/**
 * Review Step Orchestrator
 *
 * Manages the review and launch step extending BaseOrchestrator
 * for consistent wizard behavior
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Review = SCD.Modules.Review || {};

	/**
	 * Review Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	SCD.Steps.ReviewOrchestrator = SCD.Shared.BaseOrchestrator.createStep( 'review', {

		/**
		 * Initialize step modules
		 * Called by BaseOrchestrator's initializeModules
		 */
		initializeStep: function() {
			try {
				// Only create modules if they don't already exist
				if ( !this.modules.state ) {
					this.modules.state = new SCD.Modules.Review.State();
				}

				if ( !this.modules.api ) {
					this.modules.api = new SCD.Modules.Review.API();
				}

				if ( !this.modules.components ) {
					this.modules.components = new SCD.Modules.Review.Components( this.modules.state, this.modules.api );
					this.modules.components.init();
				}
			} catch ( error ) {
				SCD.ErrorHandler.handle(
					error,
					'ReviewOrchestrator.initializeStep',
					SCD.ErrorHandler.SEVERITY.HIGH
				);
				throw error;
			}
		},

		/**
		 * Custom initialization for review step
		 * The factory's init handles standard setup, this handles review-specific setup
		 * @param wizard
		 * @param config
		 */
		init: function( wizard, config ) {
			// Load wizard data for review
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
			this.bindCustomEvent( 'scd:wizard:step-loaded', function( event, stepName ) {
				if ( 'review' === stepName ) {
					self.onStepLoaded();
				}
			} );

			// Listen for navigation before-complete event
			this.bindCustomEvent( 'scd:navigation:before-complete', function() {
				self.storeLaunchOption();
			} );

			// Update button text when state changes
			this.bindCustomEvent( 'scd:review:state:changed', function( e, data ) {
				if ( 'launchOption' === data.property ) {
					if ( self.modules.components && 'function' === typeof self.modules.components.updateNavigationButton ) {
						self.modules.components.updateNavigationButton();
					}
				}
			} );
		},

		// loadInitialData method removed - data population is handled by wizard orchestrator
		// which calls populateFields() directly with saved data from scdWizardData.currentCampaign

		/**
		 * Handle step loaded
		 */
		onStepLoaded: function() {
			// Initialize integrated components module
			if ( this.modules.components && 'function' === typeof this.modules.components.init ) {
				this.modules.components.init();
			}

			// Data loading is handled in init() method

			this.triggerCustomEvent( 'scd:review:ready', [ this.modules ] );
		},

		/**
		 * Load wizard data for review
		 */
		loadWizardData: function() {
			// Try multiple sources for wizard data
			var wizardData = null;

			// Try SCD.Wizard.data first (modern pattern)
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.data ) {
				wizardData = window.SCD.Wizard.data;
			}
			// Try scdWizardData (legacy pattern)
			else if ( window.scdWizardData && window.scdWizardData.steps ) {
				wizardData = window.scdWizardData.steps;
			}

			if ( wizardData ) {
				this.modules.state.setState( {
					wizardData: wizardData
				} );

				// Summary is now server-rendered, no need to update via JavaScript
			}
		},

		/**
		 * Store launch option in wizard data
		 * Note: Status is determined by the PHP compiler service based on launch_option and start_date.
		 * We do NOT set status here to avoid race conditions and ensure single source of truth.
		 */
		storeLaunchOption: function() {
			var launchOption = this.modules.state.getState().launchOption;

			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.data ) {
				window.SCD.Wizard.data.launchOption = launchOption;
			}

			return launchOption;
		},

		/**
		 * Set loading state
		 * @param loading
		 */
	// Note: setLoading() is inherited from BaseOrchestrator

		// Note: collectData is now handled by StepPersistence mixin
		// The mixin uses field definitions to automatically collect all fields
		// We override it to handle special cases

		/**
		 * Collect data - overrides mixin to handle special cases
		 * @override
		 */
		collectData: function() {
			// Call parent method from mixin first
			var data = SCD.Mixins.StepPersistence.collectData.call( this );

			// Use state's toJSON for any additional complex data
			if ( this.modules.state && 'function' === typeof this.modules.state.toJSON ) {
				var stateData = this.modules.state.toJSON();
				// Merge any additional state data that might be needed
				data = $.extend( {}, stateData, data );
			}

			// Note: Status is determined by the PHP compiler service, not here
			// The compiler uses launch_option + start_date to calculate the correct status

			return data;
		},

		// Note: validateData is now handled by StepPersistence mixin
		// The mixin uses field definitions for automatic validation
		// We can still override if we need additional business logic validation

		/**
		 * Validate data - overrides mixin to add business logic validation
		 * @override
		 */
		validateData: function( data ) {
			// Call parent method from mixin first
			var validation = SCD.Mixins.StepPersistence.validateData.call( this, data );
			var errors = validation.errors || [];

			// Add component-specific validation if needed
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
		 * Show validation errors using ValidationError component
		 * Required for consistent error display across all steps
		 * @param errors
		 */
	// Note: showErrors() is inherited from StepPersistence mixin

		/**
		 * Custom logic after field population
		 * Called by StepPersistence mixin after standard field population
		 * @param {object} savedData - Populated data
		 */
		onPopulateFieldsComplete: function( savedData ) {
			// Summary is now server-rendered, UI components handle their own state
			// All fields are populated by parent method

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

			this.triggerCustomEvent( 'scd:review:reset', [] );
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
			return SCD.Utils.get( this.modules, name );
		},

		/**
		 * Check if step is ready
		 */
		isReady: function() {
			return !SCD.Utils.isEmpty( this.modules ) &&
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
