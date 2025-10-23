/**
 * Discount Type Registry Module
 *
 * Manages registration and instantiation of discount type modules.
 * Provides a central registry for all available discount types.
 * Now supports lazy loading of type modules via feature flag.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * Discount Type Registry
	 *
	 * @param state
	 * @class SCD.Modules.Discounts.TypeRegistry
	 */
	SCD.Modules.Discounts.TypeRegistry = function( state ) {
		this.state = state;
		this.types = {};
		this.instances = {};
		this.activeType = null;
		this.lazyLoadEnabled = false; // Disable lazy loading since scripts are pre-loaded
		this.loadingPromises = {};
	};

	SCD.Modules.Discounts.TypeRegistry.prototype = {
		/**
		 * Initialize the registry
		 */
		init: function() {
			this.registerBuiltInTypes();
			this.setupStateSubscription();
			this._ready = true;

			// Registration is now handled by the orchestrator when it creates this instance

			// No longer need to pre-initialize instances - handlers manage data now

			// Initialize with current discount type
			// Initialize with current discount type if set
			var currentDiscountType = null;

			if ( this.state && 'function' === typeof this.state.getState ) {
				var stateData = this.state.getState();
				currentDiscountType = stateData && stateData.discountType;
			}

			if ( currentDiscountType && 'string' === typeof currentDiscountType ) {
				this.activateType( currentDiscountType );
			}
		},

		/**
		 * Check if module is ready
		 * @returns {boolean}
		 */
		isReady: function() {
			return true === this._ready;
		},

		/**
		 * Register built-in discount types
		 */
		registerBuiltInTypes: function() {
			if ( this.lazyLoadEnabled ) {
				// Register types with lazy loading configuration
				this.registerType( 'percentage', null, {
					label: 'Percentage Off',
					icon: 'percent',
					description: 'Reduce price by a percentage',
					modulePath: 'types/percentage-discount.js'
				} );

				this.registerType( 'fixed', null, {
					label: 'Fixed Amount Off',
					icon: 'tag',
					description: 'Reduce price by a fixed amount',
					modulePath: 'types/fixed-discount.js'
				} );

				this.registerType( 'tiered', null, {
					label: 'Tiered Discount',
					icon: 'layers',
					description: 'Different discounts based on quantity or order value',
					modulePath: 'types/tiered-discount.js'
				} );

				this.registerType( 'bogo', null, {
					label: 'Buy One Get One',
					icon: 'gift',
					description: 'Buy X Get Y deals',
					modulePath: 'types/bogo-discount.js'
				} );

				this.registerType( 'spend_threshold', null, {
					label: 'Spend Threshold',
					icon: 'money',
					description: 'Discounts based on cart total',
					modulePath: 'types/spend-threshold.js'
				} );

			} else {
				// Original eager loading approach
				this.registerType( 'percentage', SCD.Modules.Discounts.Types.PercentageDiscount, {
					label: 'Percentage Off',
					icon: 'percent',
					description: 'Reduce price by a percentage'
				} );

				this.registerType( 'fixed', SCD.Modules.Discounts.Types.FixedDiscount, {
					label: 'Fixed Amount Off',
					icon: 'tag',
					description: 'Reduce price by a fixed amount'
				} );

				this.registerType( 'tiered', SCD.Modules.Discounts.Types.TieredDiscount, {
					label: 'Tiered Discount',
					icon: 'layers',
					description: 'Different discounts based on quantity or order value'
				} );

				this.registerType( 'bogo', SCD.Modules.Discounts.Types.BogoDiscount, {
					label: 'Buy One Get One',
					icon: 'gift',
					description: 'Buy X Get Y deals'
				} );

				this.registerType( 'spend_threshold', SCD.Modules.Discounts.Types.SpendThreshold, {
					label: 'Spend Threshold',
					icon: 'money',
					description: 'Discounts based on cart total'
				} );
			}
		},

		/**
		 * Register a discount type
		 * @param typeId
		 * @param TypeClass
		 * @param metadata
		 */
		registerType: function( typeId, TypeClass, metadata ) {
			metadata = metadata || {};
			if ( this.types[typeId] ) {

				return;
			}

			this.types[typeId] = {
				id: typeId,
				class: TypeClass,
				metadata: metadata
			};
		},

		/**
		 * Setup state event listeners
		 */
		setupStateSubscription: function() {
			var self = this;

			// Listen for discount type changes using event system
			$( document ).on( 'scd:discounts:type:changed.typeRegistry', function( event, data ) {
				if ( data.newType && data.newType !== self.activeType ) {
					self.activateType( data.newType );
				}
			} );
		},

		/**
		 * Activate a discount type
		 * @param typeId
		 */
		activateType: function( typeId ) {
			// Robust type extraction
			if ( typeId && 'object' === typeof typeId ) {
				// Extract from object
				typeId = typeId.discountType || typeId.value || typeId.type;

				// If still an object, try to get the first string property
				if ( 'object' === typeof typeId ) {

					return;
				}
			}

			// Ensure typeId is a string and valid
			if ( !typeId || 'string' !== typeof typeId ) {

				return;
			}

			typeId = typeId.trim();
			// Activating discount type

			// Deactivate current type
			if ( this.activeType && this.instances[this.activeType] ) {
				// Deactivating current type
				this.instances[this.activeType].deactivate();
			}

			// Check if type is registered
			if ( !this.types[typeId] ) {

				return;
			}

			// Strategy-specific options will be handled by the orchestrator

			try {
				// Check if we need to load the module
				var typeConfig = this.types[typeId];
				if ( this.lazyLoadEnabled && !typeConfig.class && typeConfig.metadata.modulePath ) {
					// Loading state handled by orchestrator

					// Load the module
					this.loadTypeModule( typeId );

					// Loading state handled by orchestrator
				}

				// Get or create instance
				var instance = this.getInstance( typeId );
				if ( instance ) {
					instance.activate();
					this.activeType = typeId;
				}
			} catch ( error ) {

				// Loading state handled by orchestrator

				// Show error to user
				if ( window.SCD.Shared && window.SCD.Shared.NotificationService ) {
					SCD.Shared.NotificationService.error( 'Failed to load discount type. Please try again.' );
				}
			}
		},

		/**
		 * Get instance of a discount type
		 * @param typeId
		 */
		getInstance: function( typeId ) {
			// Return existing instance if available
			if ( this.instances[typeId] ) {
				return this.instances[typeId];
			}

			// Get type configuration
			var typeConfig = this.types[typeId];
			if ( !typeConfig ) {
				return null;
			}

			// If lazy loading is enabled and class is not loaded
			if ( this.lazyLoadEnabled && !typeConfig.class && typeConfig.metadata.modulePath ) {
				// Return null for now, will be loaded asynchronously
				return null;
			}

			// Create new instance if class is available
			if ( typeConfig.class ) {
				try {
				var instance = new typeConfig.class( this.state );
				instance.init();
				this.instances[typeId] = instance;

				// Emit event when instance is created for handler registration
				$( document ).trigger( 'scd:discount:type:instance:created', [ { typeId: typeId, instance: instance } ] );

				return instance;
				} catch ( error ) {
					// Error creating instance
					return null;
				}
			}

			return null;
		},

		/**
		 * Load a discount type module asynchronously
		 * @param typeId
		 */
		loadTypeModule: function( typeId ) {
			var typeConfig = this.types[typeId];
			if ( !typeConfig || !typeConfig.metadata.modulePath ) {
				throw new Error( 'No module path defined for type ' + typeId );
			}

			// Check if already loading
			if ( this.loadingPromises[typeId] ) {
				return this.loadingPromises[typeId];
			}

			// Create loading promise
			var self = this;
			this.loadingPromises[typeId] = new Promise( function( resolve, reject ) {
				var modulePath = self.getModulePath( typeConfig.metadata.modulePath );
				var script = document.createElement( 'script' );

				script.src = modulePath;
				script.async = true;

				script.onload = function() {
					// Get the class from the expected namespace
					var className = self.getClassNameFromType( typeId );
					var TypeClass = window.SCD.Modules.Discounts.Types[className];

					if ( TypeClass ) {
						typeConfig.class = TypeClass;
						delete self.loadingPromises[typeId];
						resolve( TypeClass );
					} else {
						reject( new Error( 'Class ' + className + ' not found after loading module' ) );
					}
				};

				script.onerror = function() {
					delete self.loadingPromises[typeId];
					reject( new Error( 'Failed to load module: ' + modulePath ) );
				};

				document.head.appendChild( script );
			} );

			return this.loadingPromises[typeId];
		},

		/**
		 * Get module path
		 * @param relativePath
		 */
		getModulePath: function( relativePath ) {
			var baseUrl = ( window.scdAdminData && window.scdAdminData.assetsUrl ) || ( window.scdWizardData && window.scdWizardData.plugin_url + 'assets/' );
			return baseUrl + 'js/admin/wizard/modules/discounts/' + relativePath;
		},

		/**
		 * Get class name from type ID
		 * @param typeId
		 */
		getClassNameFromType: function( typeId ) {
			var typeMap = {
				'percentage': 'PercentageDiscount',
				'fixed': 'FixedDiscount',
				'tiered': 'TieredDiscount',
				'bogo': 'BogoDiscount',
				'spend_threshold': 'SpendThreshold'
			};
			return typeMap[typeId] || typeId;
		},

		/**
		 * Get active discount type instance
		 */
		getActiveInstance: function() {
			if ( !this.activeType ) {
				return null;
			}

			return this.instances[this.activeType] || null;
		},

		/**
		 * Get all registered types
		 */
		getRegisteredTypes: function() {
			var types = {};

			var self = this;
			Object.keys( this.types ).forEach( function( typeId ) {
				types[typeId] = $.extend( {
					id: typeId
				}, self.types[typeId].metadata );
			} );

			return types;
		},

		/**
		 * Check if a type is registered
		 * @param typeId
		 */
		isTypeRegistered: function( typeId ) {
			return !!this.types[typeId];
		},

		/**
		 * Validate current discount configuration
		 */
		validateCurrent: function() {
			var instance = this.getActiveInstance();

			if ( !instance ) {
				return {
					valid: false,
					errors: { discountType: 'No discount type selected' },
					warnings: {}
				};
			}

			return instance.validate();
		},

		/**
		 * Load data for a specific discount type
		 * @param typeId
		 * @param data
		 */
		loadData: function( typeId, data ) {
			var instance = this.getInstance( typeId );

			if ( instance ) {
				instance.loadData( data );
			}
		},

		/**
		 * Get summary for current discount
		 */
		getCurrentSummary: function() {
			var instance = this.getActiveInstance();

			if ( !instance ) {
				return 'No discount configured';
			}

			return instance.getSummary();
		},

		/**
		 * Calculate value for current discount
		 */
		calculateCurrentValue: function() {
			var instance = this.getActiveInstance();

			if ( !instance ) {
				return 0;
			}

			return instance.calculateValue();
		},

		/**
		 * Get preview configuration for current discount
		 */
		getCurrentPreviewConfig: function() {
			var instance = this.getActiveInstance();

			if ( !instance ) {
				return { type: 'none', value: 0 };
			}

			return instance.getPreviewConfig();
		},

		// Removed all tier/threshold specific methods - handlers manage this now

		/**
		 * Destroy all instances
		 */
		destroy: function() {
			// Destroy all instances
			var self = this;
			Object.keys( this.instances ).forEach( function( typeId ) {
				if ( self.instances[typeId].destroy ) {
					self.instances[typeId].destroy();
				}
			} );

			// Remove event listeners
			$( document ).off( '.typeRegistry' );

			this.instances = {};
			this.activeType = null;
		}
	};

} )( jQuery );