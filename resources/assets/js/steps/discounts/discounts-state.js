/**
 * Discounts State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-state.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * Discounts State Constructor
	 * Extends BaseState for state management
	 */
	SCD.Modules.Discounts.State = function() {
		// Define initial state
		var initialState = {
			// Core discount configuration
			discountType: 'percentage',

			// Unified discount value model with type-specific configuration
			discountValue: 0,
			discountConfig: {
				percentage: { value: 0 },
				fixed: { value: 0 },
				tiered: { tiers: [] },
				bogo: { buyQuantity: 1, getQuantity: 1, discountPercentage: 100, applyTo: 'cheapest' }
			},

			// Conditions for discount application
			conditions: [],
			conditionsLogic: 'all', // 'all' | 'any'

			// Usage Limits
			usageLimitPerCustomer: '',
			totalUsageLimit: '',
			lifetimeUsageCap: '',
			onePerOrder: false,

			// Application Rules
			applyTo: 'per_item', // 'per_item' | 'cart_total'
			maxDiscountAmount: '',
			minimumQuantity: '',
			minimumOrderAmount: '',

			// Combination Policy
			stackWithOthers: 'no', // 'yes' | 'no'
			allowCoupons: false,
			applyToSaleItems: false,

			// Badge customization
			badgeEnabled: false,
			badgeText: 'auto',
			badgeBgColor: '#ff0000',
			badgeTextColor: '#ffffff',
			badgePosition: 'top-right',

			// UI state
			activePanel: 'basic',
			isLoading: false,
			isSaving: false,

			// For spend threshold discount type
			thresholdMode: 'percentage',
			thresholds: [],

			// For tiered discount type
			tierMode: 'percentage',
			tierType: 'quantity' // 'quantity' | 'value'
		};

		// Call parent constructor
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
			SCD.Shared.BaseState.call( this, initialState );
		}

		// Additional properties not in base state
		this.errors = {};
		this.validated = false;
		this.lastSaved = null;
		this.campaignId = ( window.scdWizardData && window.scdWizardData.current_campaign && window.scdWizardData.current_campaign.id ) || null;

		this.initEventManager();

		// Setup discount type change monitoring
		this._previousDiscountType = initialState.discountType;

		this.loadInitialData();
	};

	if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
		SCD.Modules.Discounts.State.prototype = Object.create( SCD.Shared.BaseState.prototype );
		SCD.Modules.Discounts.State.prototype.constructor = SCD.Modules.Discounts.State;
	}

	// Extend prototype with custom methods
	SCD.Utils.extend( SCD.Modules.Discounts.State.prototype, {
		/**
		 * Set API reference
		 * @param api
		 */
		setApi: function( api ) {
			this.api = api;
		},

		/**
		 * Set orchestrator reference
		 * @param orchestrator
		 */
		setOrchestrator: function( orchestrator ) {
			this.orchestrator = orchestrator;
		},

		/**
		 * Set data with validation
		 * Override to add custom discount handling
		 * @param key
		 * @param value
		 */
		setData: function( key, value ) {
			if ( 'object' === typeof key ) {
				// Bulk update
				var updates = key;
				var state = this.getState();

				// Special handling for discount type changes
				if ( updates.discountType && state.discountType !== updates.discountType ) {
					this._handleDiscountTypeChange( updates.discountType, state.discountType );
				}

				// Special handling for discount config changes
				if ( updates.discountConfig ) {
					this._updateDiscountValue();
				}

				// Call parent setState
				this.setState( updates, true );
			} else {
				var currentState = this.getState();
				// Special handling for discount type changes
				if ( 'discountType' === key && currentState.discountType !== value ) {
					this._handleDiscountTypeChange( value, currentState.discountType );
				}

				// Special handling for discount config changes
				if ( 'discountConfig' === key ) {
					this._updateDiscountValue();
				}

				// Call parent setState with single property
				var update = {};
				update[key] = value;
				this.setState( update, false );
			}
		},

		/**
		 * Get current discount configuration
		 */
		getCurrentConfig: function() {
			var state = this.getState();
			return state.discountConfig[state.discountType];
		},

		/**
		 * Update discount configuration for current type
		 * @param updates
		 */
		updateCurrentConfig: function( updates ) {
			var state = this.getState();
			var currentConfig = $.extend( true, {}, state.discountConfig );
			currentConfig[state.discountType] = $.extend( {}, currentConfig[state.discountType], updates );
			this.setState( { discountConfig: currentConfig } );
		},

		/**
		 * Handle discount type changes
		 * @param newType
		 * @param oldType
		 */
		_handleDiscountTypeChange: function( newType, oldType ) {
			this._updateDiscountValue();

			// Trigger specific event for type change
			this.triggerCustomEvent( 'scd:discounts:type:changed', [ {
				newType: newType,
				oldType: oldType,
				config: this.getCurrentConfig(),
				state: this.getState()
			} ] );
		},

		/**
		 * Update discount value based on current type and config
		 */
		_updateDiscountValue: function() {
			var value = this._calculateDiscountValue();
			var state = this.getState();
			if ( value !== state.discountValue ) {
				this.setState( { discountValue: value } );

				// Trigger value changed event
				this.triggerCustomEvent( 'scd:discounts:value:changed', [ {
					value: value,
					type: state.discountType,
					config: this.getCurrentConfig(),
					state: this.getState()
				} ] );
			}
		},

		/**
		 * Calculate discount value based on current type
		 */
		_calculateDiscountValue: function() {
			var state = this.getState();
			var config = state.discountConfig[state.discountType];
			var value = 0;

			if ( !config ) {
				return value;
			}

			switch ( state.discountType ) {
				case 'percentage':
				case 'fixed':
					value = config.value || 0;
					break;
				case 'tiered':
					// Use first tier as representative value
					value = config.tiers && 0 < config.tiers.length ? config.tiers[0].discount : 0;
					break;
				case 'bogo':
					value = config.discountPercentage || 100;
					break;
				case 'spend_threshold':
					// Use first threshold as representative value
					var thresholds = state.thresholds || [];
					value = 0 < thresholds.length ? thresholds[0].discount : 0;
					break;
			}

			return value;
		},

		/**
		 * Validate state
		 */
		validate: function() {
			this.errors = {};
			var state = this.getState();

			var validTypes = [ 'percentage', 'fixed', 'tiered', 'bogo', 'spend_threshold' ];
			if ( -1 === validTypes.indexOf( state.discountType ) ) {
				this.errors.discountType = 'Invalid discount type';
			}

			var config = this.getCurrentConfig();
			switch ( state.discountType ) {
				case 'percentage':
					if ( !config.value || 0 >= config.value || 100 < config.value ) {
						this.errors.discountValue = 'Percentage must be between 1 and 100';
					}
					break;
				case 'fixed':
					if ( !config.value || 0 >= config.value ) {
						this.errors.discountValue = 'Fixed amount must be greater than 0';
					}
					break;
				case 'tiered':
					if ( !config.tiers || 0 === config.tiers.length ) {
						this.errors.tiers = 'At least one tier must be configured';
					}
					break;
				case 'bogo':
					if ( !config.buyQuantity || 1 > config.buyQuantity ) {
						this.errors.buyQuantity = 'Buy quantity must be at least 1';
					}
					if ( !config.getQuantity || 1 > config.getQuantity ) {
						this.errors.getQuantity = 'Get quantity must be at least 1';
					}
					break;
			}

			this.validated = true;
			var isValid = SCD.Utils.isEmpty( this.errors );

			// Log validation errors if any
			if ( !isValid && window.SCD.ErrorHandler ) {
				SCD.ErrorHandler.handle(
					new Error( 'Discounts step validation failed' ),
					'DiscountsState.validate',
					SCD.ErrorHandler.SEVERITY.LOW,
					{ errors: this.errors }
				);
			}

			return isValid;
		},

		/**
		 * Trigger change event
		 * @param property
		 * @param oldValue
		 */
		triggerChange: function( property, oldValue ) {
			var state = this.getState();
			var eventData = {
				data: state,
				property: property || null
			};

			if ( property ) {
				eventData.value = state[property];
				if ( oldValue !== undefined ) {
					eventData.oldValue = oldValue;
				}
			}

			// Main state change event
			this.triggerCustomEvent( 'scd:discounts:state:changed', [ eventData ] );

			// Also trigger wizard data changed event
			this.triggerCustomEvent( 'scd:wizard:dataChanged', [] );
		},

		/**
		 * Get validation errors
		 */
		getErrors: function() {
			return this.errors || {};
		},

		/**
		 * Clear validation errors
		 */
		clearErrors: function() {
			this.errors = {};
			this.validated = false;
		},

		/**
		 * Reset state to defaults
		 * Override to use custom defaults
		 */
		reset: function() {
			var defaults = {
				discountType: 'percentage',
				discountValue: 0,
				discountConfig: {
					percentage: { value: 0 },
					fixed: { value: 0 },
					tiered: { tiers: [] },
					bogo: { buyQuantity: 1, getQuantity: 1, discountPercentage: 100, applyTo: 'cheapest' }
				},
				conditions: [],
				conditionsLogic: 'all',
				usageLimitPerCustomer: '',
				totalUsageLimit: '',
				lifetimeUsageCap: '',
				onePerOrder: false,
				applyTo: 'per_item',
				maxDiscountAmount: '',
				minimumQuantity: '',
				minimumOrderAmount: '',
				stackWithOthers: 'no',
				allowCoupons: false,
				applyToSaleItems: false,
				badgeEnabled: false,
				badgeText: 'auto',
				badgeBgColor: '#ff0000',
				badgeTextColor: '#ffffff',
				badgePosition: 'top-right',
				activePanel: 'basic',
				isLoading: false,
				isSaving: false,
				thresholdMode: 'percentage',
				thresholds: [],
				tierMode: 'percentage',
				tierType: 'quantity'
			};

			// Call parent reset with custom defaults
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.reset.call( this, defaults );
			}

			this.errors = {};
			this.validated = false;

			this.triggerChange();
		},

		/**
		 * Convert state to JSON for saving
		 */
		toJSON: function() {
			var state = this.getState();
			var config = state.discountConfig[state.discountType];

			// Calculate unified value
			var discountValue = this._calculateDiscountValue();

			// Base data structure
			var data = {
				discountType: state.discountType,
				discountValue: discountValue,
				conditions: state.conditions,
				conditionsLogic: state.conditionsLogic,
				// Usage Limits
				usageLimitPerCustomer: state.usageLimitPerCustomer,
				totalUsageLimit: state.totalUsageLimit,
				lifetimeUsageCap: state.lifetimeUsageCap,
				onePerOrder: state.onePerOrder,
				// Application Rules
				applyTo: state.applyTo,
				maxDiscountAmount: state.maxDiscountAmount,
				minimumQuantity: state.minimumQuantity,
				minimumOrderAmount: state.minimumOrderAmount,
				// Combination Policy
				stackWithOthers: state.stackWithOthers,
				allowCoupons: state.allowCoupons,
				applyToSaleItems: state.applyToSaleItems,
				// Badge settings
				badgeEnabled: state.badgeEnabled,
				badgeText: state.badgeText,
				badgeBgColor: state.badgeBgColor,
				badgeTextColor: state.badgeTextColor,
				badgePosition: state.badgePosition
			};

			switch ( state.discountType ) {
				case 'percentage':
					data.discountValuePercentage = config && config.value !== undefined ? config.value : discountValue;
					break;
				case 'fixed':
					data.discountValueFixed = config && config.value !== undefined ? config.value : discountValue;
					break;
				case 'tiered':
					data.tiers = config.tiers || [];
					data.tierMode = state.tierMode || 'percentage';
					data.tierType = state.tierType || 'quantity';
					break;
				case 'bogo':
					data.bogoConfig = {
						buyQuantity: ( config && config.buyQuantity ) || 1,
						getQuantity: ( config && config.getQuantity ) || 1,
						discountPercent: ( config && config.discountPercentage ) || 100,
						applyTo: ( config && config.applyTo ) || 'cheapest'
					};
					break;
				case 'spend_threshold':
					data.thresholds = state.thresholds || [];
					data.thresholdMode = state.thresholdMode || 'percentage';
					break;
			}

			return data;
		},

		/**
		 * Export state for saving (alias for toJSON)
		 */
		export: function() {
			return this.toJSON();
		},

		/**
		 * Load state from saved data
		 * @param data
		 */
		fromJSON: function( data ) {
			if ( !data ) {
				return;
			}


			// Data already converted to camelCase by server
			var updates = {};
			var configUpdates = {};

			// Map backend fields to state
			// Check both camelCase (AJAX) and snake_case (localized data)
			var discountType = data.discountType || data.discount_type;
			if ( discountType ) {
				updates.discountType = discountType;
			}

			// Build discount configuration based on type
			if ( discountType ) {
				switch ( discountType ) {
					case 'percentage':
						var percentageValue = data.discountValuePercentage || data.discount_value_percentage || data.discountValue || data.discount_value;
						if ( percentageValue !== undefined ) {
							configUpdates.percentage = { value: parseFloat( percentageValue ) || 0 };
						}
						break;
					case 'fixed':
						var fixedValue = data.discountValueFixed || data.discount_value_fixed || data.discountValue || data.discount_value;
						if ( fixedValue !== undefined ) {
							configUpdates.fixed = { value: parseFloat( fixedValue ) || 0 };
						}
						break;
					case 'tiered':
						var tieredData = data.tiers;
						if ( tieredData ) {
							configUpdates.tiered = {
								tiers: Array.isArray( tieredData ) ? tieredData : []
							};
						}
						if ( data.tierMode || data.tier_mode ) {
							updates.tierMode = data.tierMode || data.tier_mode;
						}
						if ( data.tierType || data.tier_type ) {
							updates.tierType = data.tierType || data.tier_type;
						}
						break;
					case 'bogo':
						// Check both formats:
						// 1. Object format: data.bogoConfig or data.bogo_config
						// 2. Individual fields: data.bogo_buy_quantity, etc.
						var bogoData = data.bogoConfig || data.bogo_config;

						if ( bogoData && 'object' === typeof bogoData ) {
							// BOGO data is in grouped format, handle both camelCase and snake_case property names
							configUpdates.bogo = {
								buyQuantity: bogoData.buyQuantity || bogoData.buy_quantity || 1,
								getQuantity: bogoData.getQuantity || bogoData.get_quantity || 1,
								discountPercentage: bogoData.discountPercent || bogoData.discount_percent || bogoData.discountPercentage || 100,
								applyTo: bogoData.applyTo || bogoData.apply_to || 'cheapest'
							};
						}
						break;
					case 'spend_threshold':
						if ( data.thresholdMode || data.threshold_mode ) {
							updates.thresholdMode = data.thresholdMode || data.threshold_mode;
						}
						if ( data.thresholds ) {
							updates.thresholds = Array.isArray( data.thresholds ) ? data.thresholds : [];
						}
						break;
				}
			}

			// Handle existing discountConfig directly if provided (for direct state imports)
			if ( data.discountConfig ) {
				configUpdates = $.extend( true, configUpdates, data.discountConfig );
			}

			// Merge config updates into existing config
			if ( 0 < Object.keys( configUpdates ).length ) {
				var state = this.getState();
				var currentConfig = state.discountConfig || {};
				updates.discountConfig = $.extend( true, {}, currentConfig, configUpdates );
			}

			// Other fields - check both camelCase and snake_case
			if ( data.conditions ) {updates.conditions = data.conditions;}
			if ( data.conditionsLogic || data.conditions_logic ) {updates.conditionsLogic = data.conditionsLogic || data.conditions_logic;}

			// Usage Limits
			var usageLimitPerCustomer = data.usageLimitPerCustomer !== undefined ? data.usageLimitPerCustomer : data.usage_limit_per_customer;
			if ( usageLimitPerCustomer !== undefined ) {updates.usageLimitPerCustomer = usageLimitPerCustomer;}

			var totalUsageLimit = data.totalUsageLimit !== undefined ? data.totalUsageLimit : data.total_usage_limit;
			if ( totalUsageLimit !== undefined ) {updates.totalUsageLimit = totalUsageLimit;}

			var lifetimeUsageCap = data.lifetimeUsageCap !== undefined ? data.lifetimeUsageCap : data.lifetime_usage_cap;
			if ( lifetimeUsageCap !== undefined ) {updates.lifetimeUsageCap = lifetimeUsageCap;}

			var onePerOrder = data.onePerOrder !== undefined ? data.onePerOrder : data.one_per_order;
			if ( onePerOrder !== undefined ) {updates.onePerOrder = onePerOrder;}

			// Application Rules
			var applyTo = data.applyTo !== undefined ? data.applyTo : data.apply_to;
			if ( applyTo !== undefined ) {updates.applyTo = applyTo;}

			var maxDiscountAmount = data.maxDiscountAmount !== undefined ? data.maxDiscountAmount : data.max_discount_amount;
			if ( maxDiscountAmount !== undefined ) {updates.maxDiscountAmount = maxDiscountAmount;}

			var minimumQuantity = data.minimumQuantity !== undefined ? data.minimumQuantity : data.minimum_quantity;
			if ( minimumQuantity !== undefined ) {updates.minimumQuantity = minimumQuantity;}

			var minimumOrderAmount = data.minimumOrderAmount !== undefined ? data.minimumOrderAmount : data.minimum_order_amount;
			if ( minimumOrderAmount !== undefined ) {updates.minimumOrderAmount = minimumOrderAmount;}

			// Combination Policy
			var stackWithOthers = data.stackWithOthers !== undefined ? data.stackWithOthers : data.stack_with_others;
			if ( stackWithOthers !== undefined ) {updates.stackWithOthers = stackWithOthers;}

			var allowCoupons = data.allowCoupons !== undefined ? data.allowCoupons : data.allow_coupons;
			if ( allowCoupons !== undefined ) {updates.allowCoupons = allowCoupons;}

			var applyToSaleItems = data.applyToSaleItems !== undefined ? data.applyToSaleItems : data.apply_to_sale_items;
			if ( applyToSaleItems !== undefined ) {updates.applyToSaleItems = applyToSaleItems;}

			// Badge settings
			var badgeEnabled = data.badgeEnabled !== undefined ? data.badgeEnabled : data.badge_enabled;
			if ( badgeEnabled !== undefined ) {updates.badgeEnabled = badgeEnabled;}

			if ( data.badgeText || data.badge_text ) {updates.badgeText = data.badgeText || data.badge_text;}
			if ( data.badgeBgColor || data.badge_bg_color ) {updates.badgeBgColor = data.badgeBgColor || data.badge_bg_color;}
			if ( data.badgeTextColor || data.badge_text_color ) {updates.badgeTextColor = data.badgeTextColor || data.badge_text_color;}
			if ( data.badgePosition || data.badge_position ) {updates.badgePosition = data.badgePosition || data.badge_position;}

			// Apply all updates
			this.setState( updates );

			this.clearDirty();
		},

		/**
		 * Import state from saved data (alias for fromJSON)
		 * @param data
		 */
		import: function( data ) {
			this.fromJSON( data );
		},

		/**
		 * Load initial data
		 */
		loadInitialData: function() {
			var sources = [
				window.scdWizardData && window.scdWizardData.current_campaign && window.scdWizardData.current_campaign.discounts,
				window.scdDiscountState,
				this.loadFromStorage()
			];

			// Find first valid data source
			var initialData = sources.find( function( data ) { return data && 0 < Object.keys( data ).length; } );

			if ( initialData ) {
				this.fromJSON( initialData );
			}
		},

		/**
		 * Save state data
		 * @param callback
		 */
		save: function( callback ) {
			var self = this;
			var state = this.getState();

			if ( state.isSaving ) {
				return ( function() { var d = $.Deferred(); d.reject( 'Save already in progress' ); return d.promise(); } )();
			}

			this.setState( { isSaving: true } );

			var data = this.toJSON();

			// Don't save if no discount type is selected
			if ( !data.discountType || '' === data.discountType ) {
				this.setState( { isSaving: false } );

				// Return a rejected promise with a descriptive message
				var deferred = $.Deferred();
				deferred.reject( { message: 'No discount type selected yet' } );
				return deferred.promise();
			}

			this.saveToStorage( data );

			// Save to backend if API is available
			var promise;
			if ( this.api && 'function' === typeof this.api.saveStepData ) {
				promise = this.api.saveStepData( data );
			} else {
				// No API available, just resolve immediately
				promise = $.Deferred().resolve( { success: true } ).promise();
			}

			promise
				.done( function( response ) {
					self.clearDirty();
					self.lastSaved = Date.now();
					SCD.Shared.NotificationService.success( 'Discount settings saved' );

					if ( callback ) {callback( null, response );}
				} )
				.fail( function( error ) {
					SCD.Shared.NotificationService.error( 'Failed to save discount settings' );

					if ( callback ) {callback( error );}
				} )
				.always( function() {
					self.setState( { isSaving: false } );
				} );

			return promise;
		},

		/**
		 * Load data from localStorage
		 */
		loadFromStorage: function() {
			if ( !this.campaignId ) {return null;}

			try {
				var stored = localStorage.getItem( 'scd_discount_' + this.campaignId );
				return stored ? JSON.parse( stored ) : null;
			} catch ( e ) {
				// Failed to retrieve stored discount data
				return null;
			}
		},

		/**
		 * Save data to localStorage
		 * @param data
		 */
		saveToStorage: function( data ) {
			if ( !this.campaignId ) {return;}

			try {
				localStorage.setItem( 'scd_discount_' + this.campaignId, JSON.stringify( data ) );
			} catch ( e ) {
				// Failed to store discount data
			}
		},

		/**
		 * Export discount data to file
		 */
		exportToFile: function() {
			var data = this.toJSON();
			var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
			var url = URL.createObjectURL( blob );

			var a = document.createElement( 'a' );
			a.href = url;
			a.download = 'discount_' + this.campaignId + '_' + Date.now() + '.json';
			a.click();

			URL.revokeObjectURL( url );
		},

		/**
		 * Import discount data from file
		 * @param file
		 */
		importFromFile: function( file ) {
			var self = this;
			var deferred = $.Deferred();
			var reader = new FileReader();

			reader.onload = function( e ) {
				try {
					var data = JSON.parse( e.target.result );
					self.fromJSON( data );
					SCD.Shared.NotificationService.success( 'Discount settings imported successfully' );
					deferred.resolve( data );
				} catch ( error ) {
					SCD.Shared.NotificationService.error( 'Invalid JSON file' );
					deferred.reject( 'Invalid JSON file' );
				}
			};

			reader.onerror = function() {
				SCD.Shared.NotificationService.error( 'Failed to read file' );
				deferred.reject( 'Failed to read file' );
			};

			reader.readAsText( file );

			return deferred.promise();
		},

		/**
		 * Cleanup
		 */
		destroy: function() {
			this.unbindAllEvents();

			// Call parent destroy
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.destroy.call( this );
			}

			this.errors = null;
			this.api = null;
			this.orchestrator = null;
		}
	} );

	// Mix in event manager functionality
	SCD.Utils.extend( SCD.Modules.Discounts.State.prototype, SCD.Mixins.EventManager );

} )( jQuery );
