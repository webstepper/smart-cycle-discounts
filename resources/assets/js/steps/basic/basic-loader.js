/**
 * Basic Step Loader
 *
 * Handles lazy loading of basic step modules
 *
 * @param _$
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( _$ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Steps = window.SCD.Steps || {};

	// Register basic step modules
	if ( window.SCD && window.SCD.ModuleLoader ) {
		window.SCD.ModuleLoader.register( 'basic-loader', function() {
			// Basic step modules are already loaded via script registry
			return true;
		} );
	}

} )( jQuery );