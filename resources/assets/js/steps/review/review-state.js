/**
 * Review State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/review/review-state.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( _$ ) {
	'use strict';

	// Define the Review State constructor
	SCD.Utils.registerModule( 'SCD.Modules.Review', 'State', function() {
		// Define initial state
		var initialState = {
			launchOption: 'active', // 'active' or 'draft'
			wizardData: null,
			summaryData: {
				basic: {},
				products: {},
				discounts: {},
				schedule: {}
			},
			isLoading: false,
			errors: []
		};

		// Define computed properties
		var computed = {
			isValid: function( state ) {
				return null !== state.launchOption;
			},
			buttonText: function( state ) {
				return 'active' === state.launchOption
					? ( ( window.scdReviewStrings && window.scdReviewStrings.launchActive ) || 'Launch Campaign' )
					: ( ( window.scdReviewStrings && window.scdReviewStrings.saveDraft ) || 'Save as Draft' );
			},
			hasWizardData: function( state ) {
				return null !== state.wizardData && 0 < Object.keys( state.wizardData ).length;
			},
			campaignName: function( state ) {
				return ( state.wizardData && state.wizardData.basic && state.wizardData.basic.name ) ||
                       ( state.summaryData && state.summaryData.basic && state.summaryData.basic.name ) ||
                       'Untitled Campaign';
			},
			totalProducts: function( state ) {
				var products = ( state.wizardData && state.wizardData.products ) ||
                              ( state.summaryData && state.summaryData.products ) || {};
				if ( 'all_products' === products.selectionType || 'all' === products.selectionType ) {
					return 'All Products';
				} else if ( 'random_products' === products.selectionType || 'random' === products.selectionType ) {
					return ( products.randomCount || 0 ) + ' Random Products';
				} else if ( products.productIds ) {
					return products.productIds.length;
				}
				return 0;
			}
		};

		// Call parent constructor
		SCD.Shared.BaseState.call( this, initialState );

		// Register computed properties if BaseState supports it
		if ( this.registerComputed ) {
			this.registerComputed( computed );
		}

		if ( this.initEventManager ) {
			this.initEventManager();
		}
	} );

	SCD.Modules.Review.State.prototype = Object.create( SCD.Shared.BaseState.prototype );
	SCD.Modules.Review.State.prototype.constructor = SCD.Modules.Review.State;

	// THEN extend with event manager mixin
	SCD.Utils.extend( SCD.Modules.Review.State.prototype, SCD.EventManager );

	/**
	 * Load wizard data
	 * @param data
	 */
	SCD.Modules.Review.State.prototype.loadWizardData = function( data ) {
		if ( data ) {
			this.setState( {
				wizardData: data,
				summaryData: {
					basic: data.basic || {},
					products: data.products || {},
					discounts: data.discounts || {},
					schedule: data.schedule || {}
				}
			} );
		}
	};

	/**
	 * Update summary data for a specific step
	 * @param step
	 * @param data
	 */
	SCD.Modules.Review.State.prototype.updateStepSummary = function( step, data ) {
		var currentSummary = this.getState().summaryData;
		var newSummaryData = {};
		// Copy existing summary data
		for ( var key in currentSummary ) {
			if ( Object.prototype.hasOwnProperty.call( currentSummary, key ) ) {
				newSummaryData[key] = currentSummary[key];
			}
		}
		newSummaryData[step] = data;

		this.setState( {
			summaryData: newSummaryData
		} );
	};

	/**
	 * Convert state to JSON for saving
	 * @returns {object} Serialized state
	 */
	SCD.Modules.Review.State.prototype.toJSON = function() {
		var state = this.getState();

		return {
			launchOption: state.launchOption,
			status: state.launchOption
		};
	};

	/**
	 * Load state from saved data
	 * @param {object} data Saved data
	 */
	SCD.Modules.Review.State.prototype.fromJSON = function( data ) {
		if ( !data ) {return;}

		var updates = {};

		if ( data.launchOption ) {
			updates.launchOption = data.launchOption;
		}

		if ( 0 < Object.keys( updates ).length ) {
			this.setState( updates );
		}
	};

	/**
	 * Reset to defaults
	 * @param {object} newState Optional new state to reset to
	 */
	SCD.Modules.Review.State.prototype.reset = function( newState ) {
		// If no state provided, use defaults
		if ( !newState ) {
			newState = {
				launchOption: 'draft',
				wizardData: null,
				summaryData: {
					basic: {},
					products: {},
					discounts: {},
					schedule: {}
				},
				isLoading: false,
				errors: []
			};
		}

		// Call parent reset with the state
		SCD.Shared.BaseState.prototype.reset.call( this, newState );
	};

} )( jQuery );