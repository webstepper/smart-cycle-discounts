/**
 * Step Bridge
 *
 * Bridges the modular step system with the main wizard expectations
 * Ensures backward compatibility while using the new modular architecture
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};

	/**
	 * Initialize step bridge
	 */
	function initializeStepBridge() {
		// List of available steps
		var steps = [ 'basic', 'products', 'discounts', 'schedule', 'review' ];

		// Get current step from URL or wizard data
		var currentStep = null;
		if ( window.location.search ) {
			var matches = window.location.search.match( /[?&]step=([^&]+)/ );
			if ( matches ) {
				currentStep = matches[1];
			}
		}
		if ( !currentStep && window.scdWizardData && window.scdWizardData.current_step ) {
			currentStep = window.scdWizardData.current_step;
		}

		// Create bridge for each step
		steps.forEach( function( stepName ) {
			// Skip if already defined
			if ( SCD.Steps[stepName] ) {
				return;
			}

			// Create a lazy-loading bridge object
			Object.defineProperty( SCD.Steps, stepName, {
				get: function() {
					// Don't auto-create instances - let the wizard orchestrator handle this
					// Return a placeholder for all steps
					return {
						name: stepName,
						init: function() {
							// Step will be loaded by wizard orchestrator when needed
						}
					};

					// Note: Removed automatic factory creation to prevent conflicts with wizard orchestrator
					// The wizard orchestrator should be the one creating and initializing step orchestrators
				},
				configurable: true
			} );
		} );
	}

	// Initialize on DOM ready
	$( document ).ready( function() {
		initializeStepBridge();
	} );

	// Also initialize immediately in case modules load before DOM ready
	initializeStepBridge();

	// Listen for module registration events
	$( document ).on( 'scd:module:registered', function( e, data ) {
		if ( data && data.stepName && data.instance ) {
			// Update the step reference
			SCD.Steps[data.stepName] = data.instance;
		}
	} );

} )( jQuery );