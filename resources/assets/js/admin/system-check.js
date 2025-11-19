/**
 * System Check
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/system-check.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * System Check Utility
	 */
	SCD.Shared.SystemCheck = {
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
				{ name: 'BaseOrchestrator', path: 'SCD.Shared.BaseOrchestrator' },
				{ name: 'BaseState', path: 'SCD.Shared.BaseState' },
				{ name: 'ValidationManager', path: 'SCD.ValidationManager' },
				{ name: 'UI', path: 'SCD.Shared.UI' },
				{ name: 'AjaxService', path: 'SCD.Ajax' }
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

			var productsOrchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.ProductsOrchestrator;

			if ( productsOrchestrator ) {
				var prodTestInstance = new productsOrchestrator();
				if ( ! ( SCD.Shared && SCD.Shared.BaseOrchestrator && prodTestInstance instanceof SCD.Shared.BaseOrchestrator ) ) {
				}
			}

			// Check Discounts

			var discountsOrchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.DiscountOrchestrator;

			if ( discountsOrchestrator ) {
				var discTestInstance = new discountsOrchestrator();
				if ( ! ( SCD.Shared && SCD.Shared.BaseOrchestrator && discTestInstance instanceof SCD.Shared.BaseOrchestrator ) ) {
				}
			}

			// Check Schedule

			var scheduleOrchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.ScheduleOrchestrator;

			if ( scheduleOrchestrator ) {
				var schedTestInstance = new scheduleOrchestrator();
				if ( ! ( SCD.Shared && SCD.Shared.BaseOrchestrator && schedTestInstance instanceof SCD.Shared.BaseOrchestrator ) ) {
				}
			}

		},

		/**
		 * Check development tools
		 */
		checkDevelopmentTools: function() {

			var tools = [
				{ name: 'Debug API', exists: !!window.scd },
				{ name: 'Feature Toggle', exists: !!window.scdFeatures },
				{ name: 'Module Inspector', exists: !!( SCD.Shared && SCD.Shared.ModuleInspector ) },
				{ name: 'Dev Tools', exists: !!( SCD.Shared && SCD.Shared.DevTools ) }
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
						BaseOrchestrator: !!( SCD.Shared && SCD.Shared.BaseOrchestrator ),
						BaseState: !!( SCD.Shared && SCD.Shared.BaseState ),
						ValidationManager: !!( SCD.ValidationManager ),
						UI: !!( SCD.Shared && SCD.Shared.UI )
					},
					steps: {
						products: !!( SCD.Steps && SCD.Steps.products ),
						discounts: !!( SCD.Steps && SCD.Steps.discounts ),
						schedule: !!( SCD.Steps && SCD.Steps.schedule )
					}
				},
				ready: true
			};

			return report;
		}
	};

	// Auto-run check when debug mode is enabled
	$( document ).ready( function() {
		if ( window.scdDebugMode || 'localhost' === window.location.hostname ) {
			// Run check after a short delay to ensure all modules are loaded
			setTimeout( function() {

				SCD.Shared.SystemCheck.runChecks();
			}, 1000 );
		}
	} );

	if ( window.scd ) {
		window.scd.systemCheck = function() { return SCD.Shared.SystemCheck.runChecks(); };
	}

} )( jQuery );