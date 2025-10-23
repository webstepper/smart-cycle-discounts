/**
 * Review Step Sidebar JavaScript
 *
 * Handles quick edit links navigation for the review step sidebar.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize review sidebar functionality
	 */
	function initReviewSidebar() {
		// Handle quick edit link clicks
		$( '.scd-edit-link' ).on( 'click', function( e ) {
			e.preventDefault();

			var step = $( this ).data( 'step' );

			// Trigger wizard navigation
			if ( window.SCD_WizardOrchestrator && window.SCD_WizardOrchestrator.navigateToStep ) {
				window.SCD_WizardOrchestrator.navigateToStep( step );
			}
		} );
	}

	// Initialize when DOM is ready
	$( document ).ready( function() {
		initReviewSidebar();
	} );

} )( jQuery );
