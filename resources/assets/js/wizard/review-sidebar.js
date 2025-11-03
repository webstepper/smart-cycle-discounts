/**
 * Review Sidebar
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/review-sidebar.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
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

			// Use wizard facade's public API for navigation
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.goToStep ) {
				window.SCD.Wizard.goToStep( step );
			}
		} );
	}

	// Initialize when DOM is ready
	$( document ).ready( function() {
		initReviewSidebar();
	} );

} )( jQuery );
