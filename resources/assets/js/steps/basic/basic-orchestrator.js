/**
 * Basic Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/basic/basic-orchestrator.js
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
	WSSCD.Modules.Basic = WSSCD.Modules.Basic || {};

	/**
	 * Basic Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 *
	 * Phase 2 Migration: Uses Module Registry for declarative module initialization
	 */
	WSSCD.Steps.BasicOrchestrator = WSSCD.Shared.BaseOrchestrator.createStep( 'basic', {

		/**
		 * Initialize step modules
		 * Called by BaseOrchestrator's initializeModules
		 *
		 * Phase 2: Uses Module Registry for declarative initialization
		 */
		initializeStep: function() {
			// Create module configuration using helper
			var moduleConfig = WSSCD.Shared.ModuleRegistry.createStepConfig( 'basic' );

			// Initialize all modules with automatic dependency injection
			this.modules = WSSCD.Shared.ModuleRegistry.initialize( moduleConfig, this );
		},

		/**
		 * Custom initialization hook
		 * Called by BaseOrchestrator after standard init
		 *
		 * Phase 2: Bind auto events for convention-based event handling
		 */
		onInit: function() {
			// Bind auto events - convention-based event binding via data attributes
			WSSCD.Shared.AutoEvents.bind( this.$container, this );
		},

		/**
		 * Custom event binding hook
		 * Called by BaseOrchestrator after standard event binding
		 *
		 * Phase 2: Field change events handled automatically by fields module
		 */
		onBindEvents: function() {
			// Phase 2: No manual event binding needed
			// Field module handles field changes automatically
			// Auto Events handles UI interactions via data attributes
		},

		// Note: populateFields() is now handled entirely by StepPersistence mixin
		// Basic step doesn't need custom post-population logic, so no onPopulateFieldsComplete() override needed

		/**
		 * Handle step loaded
		 */
		onStepLoaded: function() {
			if ( !this.modules || 0 === Object.keys( this.modules ).length ) {
				this.initializeStep();
			}

			if ( this.modules.fields && 'function' === typeof this.modules.fields.init ) {
				this.modules.fields.init();
			}

			// Notify that step is ready
			this.triggerCustomEvent( 'wsscd:basic:ready', [ this.modules ] );
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

			this.triggerCustomEvent( 'wsscd:basic:reset', [] );
		},

		// Note: isValid and isDirty are now handled by StepPersistence mixin

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