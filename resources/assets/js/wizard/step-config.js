/**
 * Step Config
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/step-config.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function() {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Wizard = SCD.Wizard || {};

	/**
	 * Step configuration object
	 *
	 * Each step configuration contains:
	 * - orchestratorClass: The orchestrator class name for the step
	 * - domSelectors: Array of DOM selectors to check for auto-initialization
	 * - dependencies: Required modules that must be loaded before the orchestrator
	 */
	SCD.Wizard.StepConfig = {
		basic: {
			orchestratorClass: 'BasicOrchestrator',
			domSelectors: [ '[name="name"]', '.scd-wizard-step--basic' ],
			dependencies: [
				'basic-state',
				'basic-api',
				'basic-fields'
			]
		},

		products: {
			orchestratorClass: 'ProductsOrchestrator',
			domSelectors: [ '.scd-wizard-step--products', '#scd-product-selection' ],
			dependencies: [
				'scd-constants-product-selection',
				'products-state',
				'products-api',
				'products-selector',
				'products-filter',
				'products-tom-select'
			]
		},

		discounts: {
			orchestratorClass: 'DiscountsOrchestrator',
			domSelectors: [ '.scd-wizard-step--discounts', '[name="discount_type"]' ],
			dependencies: [
				'discounts-constants',
				'discounts-state',
				'discounts-api',
				'discounts-events',
				'discounts-data',
				'discounts-conditions',
				'discounts-type-registry'
			]
		},

		schedule: {
			orchestratorClass: 'ScheduleOrchestrator',
			domSelectors: [ '.scd-wizard-step--schedule', '[name="start_date"]' ],
			dependencies: [
				'schedule-state',
				'schedule-api',
				'schedule-ui',
				'schedule-config',
				'scd-timeline-visualizer',
				'scd-date-time-picker'
			]
		},

		review: {
			orchestratorClass: 'ReviewOrchestrator',
			domSelectors: [ '.scd-wizard-step--review', '#scd-review-container' ],
			dependencies: [
				'review-state',
				'review-api',
				'review-components'
			]
		}
	};

} )();