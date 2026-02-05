/**
 * Discounts Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-orchestrator.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.WSSCD = window.WSSCD || {};
	WSSCD.Steps = WSSCD.Steps || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Discounts = WSSCD.Modules.Discounts || {};

	/**
	 * Discounts Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	WSSCD.Steps.DiscountOrchestrator = WSSCD.Shared.BaseOrchestrator.createStep( 'discounts', {

		// Custom properties
		complexFieldHandlers: {},

		/**
		 * Custom initialization for discounts step
		 * The factory's init handles standard setup, this handles discounts-specific setup
		 * @param wizard
		 * @param config
		 */
		init: function( wizard, config ) {
			var self = this;

			this.complexFieldHandlers = {};

			this.initializeModules();

			// Initialize UI components
			this.initializeUI();

			// Expose globally for field definitions
			window.wsscdDiscountsOrchestrator = this;
		},

		/**
		 * Initialize modules
		 */
		initializeModules: function() {
			// Use Module Registry for state and api
			if ( !this.modules.state || !this.modules.api ) {
				var moduleConfig = WSSCD.Shared.ModuleRegistry.createStepConfig( 'discounts' );
				this.modules = WSSCD.Shared.ModuleRegistry.initialize( moduleConfig, this );

				// Post-initialization: Connect API to state
				if ( this.modules.state && this.modules.api && 'function' === typeof this.modules.state.setApi ) {
					this.modules.state.setApi( this.modules.api );
				}
			}

			// Setup event-driven handler registration BEFORE initializing type registry
			// This ensures we catch the instance creation event
			this.setupComplexFieldHandlerRegistration();

			// Type registry for discount type modules
			// Manual initialization to control timing (must be after event handler setup)
			if ( !this.modules.typeRegistry ) {
				this.modules.typeRegistry = new WSSCD.Modules.Discounts.TypeRegistry( this.modules.state );
				if ( 'function' === typeof this.modules.typeRegistry.init ) {
					this.modules.typeRegistry.init();
				}
				// TypeRegistry is not a field handler - it's a module registry
				// Complex fields like tiers/bogo_config/thresholds are managed by discount type modules through state
			}

			// CRITICAL FIX: Register handlers for instances that were created during typeRegistry.init()
			// Now that handlers initialize synchronously (no requestAnimationFrame in init()),
			// we can register them immediately
			this.registerExistingHandlers();

			// Register FreeShipping as complex field handler so StepPersistence finds it immediately
			// (avoids 50x retry loop and "Max retries reached" when populating/collecting freeShippingConfig).
			if ( WSSCD.Modules.Discounts.FreeShipping && 'function' === typeof WSSCD.Modules.Discounts.FreeShipping.getData ) {
				this.registerComplexFieldHandler( 'WSSCD.Modules.Discounts.FreeShipping', WSSCD.Modules.Discounts.FreeShipping );
			}
		},

		/**
		 * Setup event-driven complex field handler registration
		 */
		setupComplexFieldHandlerRegistration: function() {
			var self = this;

			// Listen for discount type instance creation
			$( document ).on( 'wsscd:discount:type:instance:created.orchestrator', function( event, data ) {
				if ( data && data.typeId && data.instance ) {
					self.registerHandlerForType( data.typeId, data.instance );
				}
			} );
		},

		/**
		 * Register handler for a specific discount type
		 * Only complex types (tiered, bogo, spend_threshold) need registration
		 * Simple types (percentage, fixed) are silently skipped
		 * @param {string} typeId - Type identifier
		 * @param {object} instance - Discount type instance
		 */
		registerHandlerForType: function( typeId, instance ) {
			var handlerName = this.getHandlerNameForType( typeId );

			// handlerName is null for simple types (percentage, fixed) - this is expected
			if ( ! handlerName ) {
				return; // Simple types don't need complex field handler registration
			}

			if ( instance ) {
				this.registerComplexFieldHandler( handlerName, instance );
			} else {
				// Only warn if this is a complex type that should have an instance
				console.warn( '[DiscountsOrchestrator] Cannot register complex field handler - missing instance:', { typeId: typeId, handlerName: handlerName } );
			}
		},

		/**
		 * Register handlers for already-initialized type instances
		 * Enhanced with retry logic to handle race conditions
		 */
		registerExistingHandlers: function() {
			if ( !this.modules.typeRegistry ) {
				console.warn( '[DiscountsOrchestrator] Cannot register existing handlers - typeRegistry not initialized' );
				return;
			}

			var instances = this.modules.typeRegistry.instances;
			if ( !instances || 0 === Object.keys( instances ).length ) {
				// No instances exist yet - this is normal on initial page load
				// Handlers will be registered via event when instances are created
				return;
			}

			var self = this;
			Object.keys( instances ).forEach( function( typeId ) {
				if ( instances[typeId] ) {
					// Check if handler is already registered to avoid duplicates
					var handlerName = self.getHandlerNameForType( typeId );
					if ( handlerName && ! self.complexFieldHandlers[handlerName] ) {
						self.registerHandlerForType( typeId, instances[typeId] );
					}
				}
			} );
		},

		/**
		 * Get handler name for a discount type ID
		 * Only returns names for complex types that require getValue/setValue handlers
		 * @param {string} typeId - Type identifier (tiered, bogo, spend_threshold)
		 * @returns {string|null} Handler name or null (null for simple types like percentage, fixed)
		 */
		getHandlerNameForType: function( typeId ) {
			// Only return handler names for complex types that need getValue/setValue
			// Simple types (percentage, fixed) don't need complex field handler registration
			switch ( typeId ) {
				case 'tiered':
					return 'tieredDiscount';
				case 'bogo':
					return 'bogoDiscount';
				case 'spend_threshold':
					return 'spendThreshold';
				default:
					return null;
			}
		},

		/**
		 * Register a complex field handler
		 * @param {string} name - Handler name
		 * @param {object} handler - Handler object with getValue/setValue methods
		 */
		registerComplexFieldHandler: function( name, handler ) {
			if ( handler && 'function' === typeof handler.getValue ) {
				this.complexFieldHandlers[name] = handler;
			}
		},

		/**
		 * Get a complex field handler
		 * @param {string} name - Handler name
		 * @return {object|null} Handler object or null
		 */
		getComplexFieldHandler: function( name ) {
			return this.complexFieldHandlers[name] || null;
		},

		/**
		 * Bind events - single entry point
		 */
		bindEvents: function() {
			var self = this;

			// Discount type selection
			this.bindDelegatedEvent( document, '.wsscd-discount-type-card', 'click', function( e ) {
				// Allow upgrade button clicks to navigate to pricing page
				var $target = $( e.target );
				if ( $target.closest( '.button, a' ).length && $target.closest( '.wsscd-pro-feature-unavailable' ).length ) {
					// Clicking upgrade button - allow default navigation
					return;
				}

				e.preventDefault();
				self.handleDiscountTypeSelect( $( this ) );
			} );

			// Discount value changes
			this.bindDelegatedEvent( document, '#discount_value_percentage, #discount_value_fixed', 'input change', function() {
				var $field = $( this );
				if ( window.WSSCD && window.WSSCD.ValidationError ) {
					window.WSSCD.ValidationError.clear( $field );
				}
				self.updateDiscountValue( $field );
			} );

			// BOGO fields
			this.bindDelegatedEvent( document, '#bogo_buy_quantity, #bogo_get_quantity, #bogo_discount_percentage', 'input change', function() {
				var $bogoField = $( '[name="bogo_config"]' );
				if ( $bogoField.length && window.WSSCD && window.WSSCD.ValidationError ) {
					window.WSSCD.ValidationError.clear( $bogoField );
				}
				self.updateBOGOConfig();
			} );

			// Tier management
			this.bindDelegatedEvent( document, '.wsscd-add-tier', 'click', function( e ) {
				e.preventDefault();
				var $tiersField = $( '[name="tiers"]' );
				if ( $tiersField.length && window.WSSCD && window.WSSCD.ValidationError ) {
					window.WSSCD.ValidationError.clear( $tiersField );
				}
				// TypeRegistry handles the actual tier addition
			} );

			// Threshold management
			this.bindDelegatedEvent( document, '.wsscd-add-threshold', 'click', function( e ) {
				e.preventDefault();
				var $thresholdsField = $( '[name="thresholds"]' );
				if ( $thresholdsField.length && window.WSSCD && window.WSSCD.ValidationError ) {
					window.WSSCD.ValidationError.clear( $thresholdsField );
				}
				// TypeRegistry handles the actual threshold addition
			} );

			// Usage limit fields
			this.bindDelegatedEvent( document, '#usage_limit_per_customer, #total_usage_limit, #lifetime_usage_cap', 'change', function() {
				self.updateUsageLimits();
			} );

			// Application rule fields
			this.bindDelegatedEvent( document, '#apply_to, #max_discount_amount, #minimum_quantity, #minimum_order_amount', 'change', function() {
				self.updateApplicationRules();
			} );

			// Combination policy fields
			this.bindDelegatedEvent( document, '#stack_with_others, #allow_coupons, #apply_to_sale_items', 'change', function() {
				self.updateCombinationPolicy();
			} );

			// Badge settings fields
			this.bindDelegatedEvent( document, '#badge_enabled, #badge_text, input[name="badge_position"], #badge_bg_color, #badge_text_color', 'change', function() {
				self.updateBadgeSettings();
			} );

			// Free shipping toggle
			this.bindDelegatedEvent( document, '#free_shipping_enabled', 'change', function() {
				self.handleFreeShippingToggle();
			} );

			// Free shipping method type selection (all vs selected)
			this.bindDelegatedEvent( document, 'input[name="free_shipping_method_type"]', 'change', function() {
				self.handleFreeShippingMethodTypeChange();
			} );

			// Note: Free shipping method checkboxes are now handled by FreeShipping module

			// Collapsible sections
			this.bindDelegatedEvent( document, '.wsscd-collapsible-trigger', 'click', function( e ) {
				e.preventDefault();
				self.toggleSection( $( this ).closest( '.wsscd-collapsible' ) );
			} );

			// Listen for state changes
			this.bindCustomEvent( 'wsscd:discounts:state:changed', function( event, change ) {
				if ( 'discountType' === change.property ) {
					self.updateDiscountTypeUI( change.value );
					// Update free shipping threshold note when discount type changes
					self.updateFreeShippingThresholdNote();
				}
			} );

			// Listen for field change events from modules (single source of truth)
			this.bindCustomEvent( 'wsscd:discounts:field:changed', function( event, data ) {
				if ( data && data.field && data.value !== undefined ) {
					if ( self.modules.state ) {
						self.modules.state.setData( data.field, data.value );
					}
				}
			} );

		},

		/**
		 * Initialize UI components
		 */
		initializeUI: function() {
			this.initializeCollapsibles();

			var currentType = this.getDiscountType();
			if ( currentType ) {
				this.updateDiscountTypeUI( currentType );
			}

			// Initialize free shipping UI
			this.initializeFreeShippingUI();
		},

		/**
		 * Initialize free shipping UI based on saved state
		 */
		initializeFreeShippingUI: function() {
			var $toggle = $( '#free_shipping_enabled' );
			var isEnabled = $toggle.is( ':checked' );

			// Update visibility of config wrapper
			if ( isEnabled ) {
				$( '.wsscd-free-shipping-config-wrapper' ).removeClass( 'wsscd-hidden' );

				// Check if 'selected' is chosen
				var methodType = $( 'input[name="free_shipping_method_type"]:checked' ).val();
				if ( 'selected' === methodType ) {
					$( '#wsscd-shipping-methods-list' ).removeClass( 'wsscd-hidden' );
					this.loadShippingMethodsIfNeeded();
				}
			}

			// Update threshold note visibility
			this.updateFreeShippingThresholdNote();
		},

		// loadData method removed - data population is handled by wizard orchestrator
		// which calls populateFields() directly with saved data from wsscdWizardData.currentCampaign

		/**
		 * Custom logic after field population
		 * Called by StepPersistence mixin after standard field population
		 * @param {object} data - Populated data
		 */
		onPopulateFieldsComplete: function( data ) {
			// All standard fields and complex fields are populated by parent method
			// Only handle discount type UI updates

			// Case converter handles snake_case → camelCase automatically
			var discountType = this.getPropertyValue( data, [ 'discountType' ] ) || this.getPropertyValue( data, [ 'discount_type' ] );

			// If still not found, check state (which should have been set by fromJSON)
			if ( ! discountType && this.modules.state ) {
				discountType = this.modules.state.getData( 'discountType' );
			}

			if ( discountType ) {
				$( '#discount_type' ).val( discountType );
				$( '.wsscd-discount-type-card' ).removeClass( 'selected' );
				$( '.wsscd-discount-type-card[data-type="' + discountType + '"]' ).addClass( 'selected' );

				// Update UI to reflect loaded data
				this.updateDiscountTypeUI( discountType );

				// Activate discount type in registry
				if ( this.modules.typeRegistry ) {
					this.modules.typeRegistry.activateType( discountType );
				}
			} else {
				// Fallback: use DOM value if available
				if ( this.modules.state ) {
					var currentType = this.getDiscountType();
					if ( currentType ) {
						this.modules.state.setState( { discountType: currentType } );
					}
				}
			}
		},

		/**
		 * Handle discount type selection
		 * @param $card
		 */
		handleDiscountTypeSelect: function( $card ) {
			var discountType = $card.data( 'type' );

			if ( $card.data( 'locked' ) === true || $card.attr( 'data-locked' ) === 'true' || $card.hasClass( 'wsscd-discount-type-card--locked' ) ) {
				var $detailsContainer = $( '#wsscd-discount-details-container' );
				if ( $detailsContainer.length ) {
					$detailsContainer.addClass( 'wsscd-pro-container--locked' );
					$detailsContainer.attr( 'data-active-type', discountType );
				}
				return;
			}

			this.clearFieldError( 'discount_type' );

			// Update UI
			$( '.wsscd-discount-type-card' ).removeClass( 'selected' );
			$card.addClass( 'selected' );

			$( '#discount_type' ).val( discountType ).trigger( 'change' );

			if ( this.modules.state ) {
				this.modules.state.setState( { discountType: discountType } );
			}

			// Activate type module
			if ( this.modules.typeRegistry ) {
				this.modules.typeRegistry.activateType( discountType );
			}

			// Update UI
			this.updateDiscountTypeUI( discountType );

			// Scroll to discount details card and focus first input
			this.scrollToDiscountDetails( discountType );
		},

		/**
		 * Scroll to discount details card and focus the first input field
		 * @param {string} discountType - The selected discount type
		 */
		scrollToDiscountDetails: function( discountType ) {
			var self = this;
			var detailsCard = document.getElementById( 'discount-value-card' );

			if ( ! detailsCard || ! detailsCard.scrollIntoView ) {
				return;
			}

			setTimeout( function() {
				detailsCard.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				setTimeout( function() {
					self.focusFirstInputForType( discountType );
				}, 350 );
			}, 100 );
		},

		/**
		 * Focus the first input field for the given discount type
		 * @param {string} discountType - The discount type
		 */
		focusFirstInputForType: function( discountType ) {
			var $input = null;

			switch ( discountType ) {
				case 'percentage':
					$input = $( '#discount_value_percentage' );
					break;
				case 'fixed':
					$input = $( '#discount_value_fixed' );
					break;
				case 'tiered':
					// Focus first tier quantity input or the add tier button
					$input = $( '.wsscd-strategy-tiered .wsscd-tier-row:first input:first' );
					if ( ! $input.length ) {
						$input = $( '.wsscd-strategy-tiered input[name="apply_to"]:first' );
					}
					break;
				case 'bogo':
					$input = $( '#bogo_buy_quantity' );
					break;
				case 'spend_threshold':
					// Focus first threshold input or the add threshold button
					$input = $( '.wsscd-strategy-spend_threshold .wsscd-threshold-row:first input:first' );
					if ( ! $input.length ) {
						$input = $( '.wsscd-strategy-spend_threshold input[name="threshold_mode"]:first' );
					}
					break;
			}

			if ( $input && $input.length && $input.is( ':visible' ) && $input[0].focus ) {
				$input[0].focus();
			}
		},

		/**
		 * Update discount type UI
		 * @param discountType
		 */
		updateDiscountTypeUI: function( discountType ) {
			$( '.wsscd-strategy-options' ).removeClass( 'active' );

			if ( discountType ) {
				$( '.wsscd-strategy-' + discountType ).addClass( 'active' );
				$( '#discount-value-card' ).removeClass( 'wsscd-hidden' );
				$( '#discount-rules-card' ).removeClass( 'wsscd-hidden' );
				$( '#discount_type' ).val( discountType );
				$( '.wsscd-discount-type-card' ).removeClass( 'selected' );
				$( '.wsscd-discount-type-card[data-type="' + discountType + '"]' ).addClass( 'selected' );

				// Update conditional rule visibility based on discount type
				this.updateConditionalRuleVisibility( discountType );

				// Only lock if the discount type card itself is locked (free user trying to use PRO type)
				var proTypes = [ 'tiered', 'bogo', 'spend_threshold' ];
				var $detailsContainer = $( '#wsscd-discount-details-container' );
				if ( $detailsContainer.length ) {
					var isPro = proTypes.indexOf( discountType ) !== -1;
					var $card = $( '.wsscd-discount-type-card[data-type="' + discountType + '"]' );
					var isCardLocked = $card.length && ( $card.attr( 'data-locked' ) === 'true' || $card.hasClass( 'wsscd-discount-type-card--locked' ) );

					if ( isPro && isCardLocked ) {
						// PRO type that user doesn't have access to
						$detailsContainer.addClass( 'wsscd-pro-container--locked' );
						$detailsContainer.attr( 'data-active-type', discountType );
					} else {
						// Free type or PRO type that user has access to
						$detailsContainer.removeClass( 'wsscd-pro-container--locked' );
						$detailsContainer.removeAttr( 'data-active-type' );
					}
				}
			}
		},

		/**
		 * Update conditional rule visibility based on discount type
		 *
		 * Hides/shows discount rules based on logical compatibility:
		 * - apply_to: Hidden for Fixed and BOGO (doesn't apply)
		 * - minimum_quantity: Hidden for BOGO (has its own buy_quantity)
		 * - minimum_order_amount: Hidden for Spend Threshold (redundant)
		 *
		 * @param {string} discountType - Current discount type
		 */
		updateConditionalRuleVisibility: function( discountType ) {
			var self = this;

			// Get all conditional rule rows
			var $conditionalRules = $( '.wsscd-conditional-rule[data-hide-for-types]' );

			// Show all rules first
			$conditionalRules.removeClass( 'wsscd-rule-hidden' );

			// Hide rules based on discount type
			$conditionalRules.each( function() {
				var $row = $( this );
				var hideForTypes = $row.data( 'hide-for-types' );

				if ( !hideForTypes ) {
					return;
				}

				// Convert to array if string
				var typesToHide = 'string' === typeof hideForTypes
					? hideForTypes.split( ',' ).map( function( type ) { return type.trim(); } )
					: hideForTypes;

				// Check if current type should hide this rule
				if ( -1 !== typesToHide.indexOf( discountType ) ) {
					$row.addClass( 'wsscd-rule-hidden' );

					// Clear the field value when hiding to avoid conflicts
					var $input = $row.find( 'input, select' );
					if ( $input.length ) {
						$input.val( '' );
					}
				}
			} );
		},

		/**
		 * Update discount value in state
		 * @param $field
		 */
		updateDiscountValue: function( $field ) {
			var fieldName = $field.attr( 'name' );
			var value = parseFloat( $field.val() ) || 0;

			if ( this.modules.state ) {
				this.modules.state.updateCurrentConfig( { value: value } );
			}

			if ( $field.length ) {
				this.validateFieldRealTime( $field );
			}
		},

		/**
		 * Update BOGO configuration
		 */
		updateBOGOConfig: function() {
			var config = {
				buyQuantity: parseInt( $( '#bogo_buy_quantity' ).val() ) || 1,
				getQuantity: parseInt( $( '#bogo_get_quantity' ).val() ) || 1,
				discountPercentage: parseFloat( $( '#bogo_discount_percentage' ).val() ) || 100
			};

			if ( this.modules.state ) {
				this.modules.state.updateCurrentConfig( config );
			}
		},

		/**
		 * Update usage limits
		 */
		updateUsageLimits: function() {
			if ( this.modules.state ) {
				this.modules.state.setState( {
					usageLimitPerCustomer: $( '#usage_limit_per_customer' ).val(),
					totalUsageLimit: $( '#total_usage_limit' ).val(),
					lifetimeUsageCap: $( '#lifetime_usage_cap' ).val()
				} );
			}
		},

		/**
		 * Update application rules
		 */
		updateApplicationRules: function() {
			if ( this.modules.state ) {
				this.modules.state.setState( {
					applyTo: $( '#apply_to' ).val(),
					maxDiscountAmount: $( '#max_discount_amount' ).val(),
					minimumQuantity: $( '#minimum_quantity' ).val(),
					minimumOrderAmount: $( '#minimum_order_amount' ).val()
				} );
			}
		},

		/**
		 * Update combination policy
		 */
		updateCombinationPolicy: function() {
			if ( this.modules.state ) {
				this.modules.state.setState( {
					stackWithOthers: $( '#stack_with_others' ).val(),
					allowCoupons: $( '#allow_coupons' ).is( ':checked' ),
					applyToSaleItems: $( '#apply_to_sale_items' ).is( ':checked' )
				} );
			}
		},

		/**
		 * Update badge settings
		 */
		updateBadgeSettings: function() {
			if ( this.modules.state ) {
				this.modules.state.setState( {
					badgeEnabled: $( '#badge_enabled' ).is( ':checked' ),
					badgeText: $( '#badge_text' ).val(),
					badgePosition: $( 'input[name="badge_position"]:checked' ).val() || 'top-right',
					badgeBgColor: $( '#badge_bg_color' ).val(),
					badgeTextColor: $( '#badge_text_color' ).val()
				} );
			}
		},

		/**
		 * Handle free shipping toggle change
		 */
		handleFreeShippingToggle: function() {
			var isEnabled = $( '#free_shipping_enabled' ).is( ':checked' );
			var $configWrapper = $( '.wsscd-free-shipping-config-wrapper' );

			// Show/hide configuration options
			if ( isEnabled ) {
				$configWrapper.removeClass( 'wsscd-hidden' );
				// Load shipping methods if not already loaded
				this.loadShippingMethodsIfNeeded();
			} else {
				$configWrapper.addClass( 'wsscd-hidden' );
			}

			// Update state
			this.updateFreeShippingState();

			// Show threshold note for spend_threshold discount type
			this.updateFreeShippingThresholdNote();
		},

		/**
		 * Handle free shipping method type change (all vs selected)
		 */
		handleFreeShippingMethodTypeChange: function() {
			var methodType = $( 'input[name="free_shipping_method_type"]:checked' ).val();
			var $methodsList = $( '#wsscd-shipping-methods-list' );

			if ( 'selected' === methodType ) {
				$methodsList.removeClass( 'wsscd-hidden' );
				// Load shipping methods if not already loaded
				this.loadShippingMethodsIfNeeded();
			} else {
				$methodsList.addClass( 'wsscd-hidden' );
			}

			// Update state
			this.updateFreeShippingState();
		},

		/**
		 * Load shipping methods from server if not already loaded
		 * Delegates to the FreeShipping module if available
		 */
		loadShippingMethodsIfNeeded: function() {
			// Delegate to FreeShipping module if available
			if ( window.WSSCD && WSSCD.Modules && WSSCD.Modules.Discounts && WSSCD.Modules.Discounts.FreeShipping ) {
				var freeShippingModule = WSSCD.Modules.Discounts.FreeShipping;

				// Only load if not already loaded and not currently loading
				if ( 0 === freeShippingModule.shippingMethods.length && ! freeShippingModule.isLoading ) {
					freeShippingModule.loadShippingMethods();
				}
				return;
			}

			// Fallback: Check if already loaded in DOM
			var $container = $( '.wsscd-shipping-methods-checkboxes' );
			if ( $container.children().length > 0 ) {
				return;
			}
		},

		/**
		 * Update free shipping state from UI
		 * Delegates to FreeShipping module if available
		 */
		updateFreeShippingState: function() {
			var config;

			// Prefer FreeShipping module's data (single source of truth)
			if ( window.WSSCD && WSSCD.Modules && WSSCD.Modules.Discounts && WSSCD.Modules.Discounts.FreeShipping ) {
				config = WSSCD.Modules.Discounts.FreeShipping.getData();
			} else {
				// Fallback: read directly from DOM
				var isEnabled = $( '#free_shipping_enabled' ).is( ':checked' );
				var methodType = $( 'input[name="free_shipping_method_type"]:checked' ).val() || 'all';
				var methods = 'all';

				if ( 'selected' === methodType ) {
					var hiddenValue = $( '#free_shipping_methods' ).val();
					if ( hiddenValue && 'all' !== hiddenValue ) {
						try {
							methods = JSON.parse( hiddenValue );
						} catch ( e ) {
							methods = [];
						}
					} else {
						methods = [];
					}
				}

				config = {
					enabled: isEnabled,
					methods: methods
				};
			}

			// Update hidden input
			$( '#free_shipping_methods' ).val( 'all' === methods ? 'all' : JSON.stringify( methods ) );

			// Update state
			if ( this.modules.state ) {
				this.modules.state.setState( {
					freeShippingConfig: config
				} );
			}
		},

		/**
		 * Update free shipping threshold note visibility
		 * Shows a note when spend_threshold discount is selected
		 */
		updateFreeShippingThresholdNote: function() {
			var $note = $( '#wsscd-free-shipping-threshold-note' );
			var discountType = this.getDiscountType();

			if ( 'spend_threshold' === discountType && $( '#free_shipping_enabled' ).is( ':checked' ) ) {
				$note.removeClass( 'wsscd-hidden' );
			} else {
				$note.addClass( 'wsscd-hidden' );
			}
		},

		/**
		 * Get current discount type
		 */
		getDiscountType: function() {
			if ( this.modules.state ) {
				var stateType = this.modules.state.getData( 'discountType' );
				if ( stateType ) {
					return stateType;
				}
			}

			var $selectedCard = $( '.wsscd-discount-type-card.selected' );
			if ( $selectedCard.length ) {
				return $selectedCard.data( 'type' );
			}

			var hiddenValue = $( '#discount_type' ).val();
			if ( hiddenValue ) {
				return hiddenValue;
			}

			// Default fallback
			return 'percentage';
		},

		/**
		 * Validate discount step data
		 *
		 * Calls the active discount type's validate() method to perform client-side validation.
		 * This provides immediate feedback before PHP server-side validation.
		 *
		 * @param {object} _data Step data (unused - validation uses current state)
		 * @returns {object} Validation result with {valid, errors, warnings}
		 */
		validateData: function( _data ) {
			// Clear previous validation errors.
			if ( window.WSSCD && window.WSSCD.ValidationError && this.$container ) {
				WSSCD.ValidationError.clearAll( this.$container );
			}

			// Delegate validation to TypeRegistry.
			if ( this.modules && this.modules.typeRegistry ) {
				return this.modules.typeRegistry.validateCurrent();
			}

			// Fallback: return valid if no registry available.
			return {
				valid: true,
				errors: {},
				warnings: {}
			};
		},

		// Note: collectData is handled by StepPersistence mixin
		// The mixin uses field definitions to automatically collect all fields

		// Note: showErrors() is inherited from StepPersistence mixin

		/**
		 * Show error for specific field using ValidationError component
		 * @param fieldName
		 * @param errorMessages
		 */
	// Note: showFieldError() and clearFieldError() are inherited from BaseOrchestrator

		/**
		 * Clear field error using ValidationError component
		 * @param fieldName
		 */

		/**
		 * Toggle collapsible section
		 * @param $section
		 */
		toggleSection: function( $section ) {
			if ( !$section || !$section.length ) {
				return;
			}

			$section.toggleClass( 'wsscd-collapsed' );

			// Save state to localStorage
			var sectionId = $section.data( 'section' );
			if ( sectionId ) {
				this.saveCollapsibleState( sectionId, $section.hasClass( 'wsscd-collapsed' ) );
			}
		},

		/**
		 * Initialize collapsible sections
		 */
		initializeCollapsibles: function() {
			var savedState = this.getCollapsibleState();

			this.$container.find( '.wsscd-collapsible' ).each( function() {
				var $section = $( this );
				var sectionId = $section.data( 'section' );

				if ( true === savedState[sectionId] ) {
					$section.addClass( 'wsscd-collapsed' );
				}
			} );
		},

		/**
		 * Get collapsible state from localStorage
		 */
		getCollapsibleState: function() {
			try {
				var state = localStorage.getItem( 'wsscd_collapsible_state' );
				return state ? JSON.parse( state ) : {};
			} catch ( e ) {
				return {};
			}
		},

		/**
		 * Save collapsible state to localStorage
		 * @param sectionId
		 * @param isCollapsed
		 */
		saveCollapsibleState: function( sectionId, isCollapsed ) {
			try {
				var state = this.getCollapsibleState();
				state[sectionId] = isCollapsed;
				localStorage.setItem( 'wsscd_collapsible_state', JSON.stringify( state ) );
			} catch ( e ) {
				// Fail silently
			}
		},

		// Note: Tooltip methods (initializeTooltips, showTooltip, hideTooltip)
		// are now handled by shared WSSCD.TooltipManager utility

		/**
		 * Clean up when leaving step
		 */
		destroy: function() {
			// Clean up modules
			if ( this.modules ) {
				for ( var key in this.modules ) {
					if ( Object.prototype.hasOwnProperty.call( this.modules, key ) && this.modules[key] ) {
						if ( 'function' === typeof this.modules[key].destroy ) {
							this.modules[key].destroy();
						}
					}
				}
				this.modules = {};
			}

			if ( this.$container ) {
				this.$container.find( '[data-tooltip]' ).off( '.wsscd-tooltip' );
			}

			// Unbind all events
			if ( 'function' === typeof this.unbindAllEvents ) {
				this.unbindAllEvents();
			}

			this.$container = null;
			this.initialized = false;

			// Call parent destroy
			if ( WSSCD.Shared.BaseOrchestrator.prototype.destroy ) {
				WSSCD.Shared.BaseOrchestrator.prototype.destroy.call( this );
			}
		}
	} );

} )( jQuery );
