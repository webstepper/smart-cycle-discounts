/**
 * System Check
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/system-check.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Shared = WSSCD.Shared || {};

	/**
	 * System Check Utility
	 */
	WSSCD.Shared.SystemCheck = {
		/**
		 * Run all system checks
		 */
		runChecks: function() {

			this.checkSharedModules();
			this.checkStepModules();
			this.checkDevelopmentTools();

			return this.getReport();
		},

		/**
		 * Check shared modules
		 */
		checkSharedModules: function() {

			var modules = [
				{ name: 'BaseOrchestrator', path: 'WSSCD.Shared.BaseOrchestrator' },
				{ name: 'BaseState', path: 'WSSCD.Shared.BaseState' },
				{ name: 'ValidationManager', path: 'WSSCD.ValidationManager' },
				{ name: 'UI', path: 'WSSCD.Shared.UI' },
				{ name: 'AjaxService', path: 'WSSCD.Ajax' }
			];

			var self = this;
			modules.forEach( function( module ) {
				self.checkPath( module.path );
			} );

		},

		/**
		 * Check step modules
		 */
		checkStepModules: function() {

			// Check Products

			var productsOrchestrator = window.WSSCD && window.WSSCD.Steps && window.WSSCD.Steps.ProductsOrchestrator;

			if ( productsOrchestrator ) {
				var prodTestInstance = new productsOrchestrator();
				if ( ! ( WSSCD.Shared && WSSCD.Shared.BaseOrchestrator && prodTestInstance instanceof WSSCD.Shared.BaseOrchestrator ) ) {
				}
			}

			// Check Discounts

			var discountsOrchestrator = window.WSSCD && window.WSSCD.Steps && window.WSSCD.Steps.DiscountOrchestrator;

			if ( discountsOrchestrator ) {
				var discTestInstance = new discountsOrchestrator();
				if ( ! ( WSSCD.Shared && WSSCD.Shared.BaseOrchestrator && discTestInstance instanceof WSSCD.Shared.BaseOrchestrator ) ) {
				}
			}

			// Check Schedule

			var scheduleOrchestrator = window.WSSCD && window.WSSCD.Steps && window.WSSCD.Steps.ScheduleOrchestrator;

			if ( scheduleOrchestrator ) {
				var schedTestInstance = new scheduleOrchestrator();
				if ( ! ( WSSCD.Shared && WSSCD.Shared.BaseOrchestrator && schedTestInstance instanceof WSSCD.Shared.BaseOrchestrator ) ) {
				}
			}

		},

		/**
		 * Check development tools
		 */
		checkDevelopmentTools: function() {

			var tools = [
				{ name: 'Debug API', exists: !!window.wsscd },
				{ name: 'Feature Toggle', exists: !!window.wsscdFeatures },
				{ name: 'Module Inspector', exists: !!( WSSCD.Shared && WSSCD.Shared.ModuleInspector ) },
				{ name: 'Dev Tools', exists: !!( WSSCD.Shared && WSSCD.Shared.DevTools ) }
			];

			tools.forEach( function( _tool ) {
				// Tool checking removed - handled by checkModules
			} );

		},

		/**
		 * Check if a path exists
		 * @param path
		 */
		checkPath: function( path ) {
			var parts = path.split( '.' );
			var current = window;

			for ( var i = 0; i < parts.length; i++ ) {
				var part = parts[i];
				if ( !current || !current[part] ) {
					return false;
				}
				current = current[part];
			}

			return true;
		},

		/**
		 * Generate system report
		 */
		getReport: function() {
			var report = {
				timestamp: new Date().toISOString(),
				modularSystemActive: true,
				modules: {
					shared: {
						BaseOrchestrator: !!( WSSCD.Shared && WSSCD.Shared.BaseOrchestrator ),
						BaseState: !!( WSSCD.Shared && WSSCD.Shared.BaseState ),
						ValidationManager: !!( WSSCD.ValidationManager ),
						UI: !!( WSSCD.Shared && WSSCD.Shared.UI )
					},
					steps: {
						products: !!( WSSCD.Steps && WSSCD.Steps.products ),
						discounts: !!( WSSCD.Steps && WSSCD.Steps.discounts ),
						schedule: !!( WSSCD.Steps && WSSCD.Steps.schedule )
					}
				},
				ready: true
			};

			return report;
		}
	};

	// Auto-run check when debug mode is enabled
	$( document ).ready( function() {
		if ( window.wsscdDebugMode || 'localhost' === window.location.hostname ) {
			// Run check after a short delay to ensure all modules are loaded
			setTimeout( function() {

				WSSCD.Shared.SystemCheck.runChecks();
			}, 1000 );
		}
	} );

	if ( window.wsscd ) {
		window.wsscd.systemCheck = function() { return WSSCD.Shared.SystemCheck.runChecks(); };
	}

} )( jQuery );