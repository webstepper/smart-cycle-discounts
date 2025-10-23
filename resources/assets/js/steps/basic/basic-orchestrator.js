/**
 * Basic Step Orchestrator
 *
 * Manages the basic information step extending BaseOrchestrator
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
	SCD.Modules.Basic = SCD.Modules.Basic || {};

	/**
	 * Basic Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	SCD.Steps.BasicOrchestrator = SCD.Shared.BaseOrchestrator.createStep( 'basic', {

		/**
		 * Initialize step modules
		 * Called by BaseOrchestrator's initializeModules
		 */
		initializeStep: function() {
			try {
				// Create step modules
				this.modules.state = new SCD.Modules.Basic.State();
				this.modules.api = new SCD.Modules.Basic.API();
				this.modules.fields = new SCD.Modules.Basic.Fields( this.modules.state );
			} catch ( error ) {
				SCD.ErrorHandler.handle(
					error,
					'BasicOrchestrator.initializeStep',
					SCD.ErrorHandler.SEVERITY.HIGH
				);
				throw error;
			}
		},


		/**
		 * Custom initialization hook
		 * Called by BaseOrchestrator after standard init
		 */
		onInit: function() {
			// Basic step specific initialization can go here if needed
		},

		/**
		 * Custom event binding hook
		 * Called by BaseOrchestrator after standard event binding
		 */
		onBindEvents: function() {
			var self = this;

			// Listen for field change events from the fields module
			this.bindCustomEvent( 'scd:basic:field:changed', function( e, data ) {
				if ( data && data.field && data.value !== undefined ) {
					// Update state through orchestrator (single source of truth)
					if ( self.modules.state ) {
						// setData expects an object, not individual key/value
						var update = {};
						update[data.field] = data.value;
						self.modules.state.setData( update );
					}
				}
			} );
		},

		// Note: populateFields() is now handled entirely by StepPersistence mixin
		// Basic step doesn't need custom post-population logic, so no onPopulateFieldsComplete() override needed

		/**
		 * Handle step loaded
		 */
		onStepLoaded: function() {
			// Initialize modules if not already done
			if ( !this.modules || 0 === Object.keys( this.modules ).length ) {
				this.initializeStep();
			}

			// Initialize fields module
			if ( this.modules.fields && 'function' === typeof this.modules.fields.init ) {
				this.modules.fields.init();
			}

			// Notify that step is ready
			this.triggerCustomEvent( 'scd:basic:ready', [ this.modules ] );
		},

		// Note: collectData, validateData, showErrors, getData, setData, and populateFields
		// are now handled by the StepPersistence mixin

		/**
		 * Reset step
		 */
		reset: function() {
			if ( this.modules.state ) {
				this.modules.state.reset();
			}

			if ( this.modules.fields ) {
				this.modules.fields.resetFields();
			}

			this.triggerCustomEvent( 'scd:basic:reset', [] );
		},

		// Note: isValid and isDirty are now handled by StepPersistence mixin

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
					this.modules.fields;
		},

		/**
		 * Custom cleanup hook
		 * Called by BaseOrchestrator destroy
		 */
		onDestroy: function() {
			// Note: Module destruction is automatically handled by BaseOrchestrator
			// No step-specific cleanup needed for basic step
		}
	} );

} )( jQuery );