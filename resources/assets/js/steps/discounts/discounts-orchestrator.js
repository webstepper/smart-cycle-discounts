/**
 * Discounts Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-orchestrator.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * Discounts Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	SCD.Steps.DiscountOrchestrator = SCD.Shared.BaseOrchestrator.createStep( 'discounts', {

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

			// Initialize custom properties
			this.complexFieldHandlers = {};

			// Initialize modules
			this.initializeModules();

			// Initialize UI components
			this.initializeUI();

			// Expose globally for field definitions
			window.scdDiscountsOrchestrator = this;
		},

		/**
		 * Initialize modules
		 */
		initializeModules: function() {
			// State module
			if ( !this.modules.state ) {
				this.modules.state = new SCD.Modules.Discounts.State();
				if ( 'function' === typeof this.modules.state.init ) {
					this.modules.state.init();
				}
			}

			// API module
			if ( !this.modules.api ) {
				this.modules.api = new SCD.Modules.Discounts.API();
				if ( 'function' === typeof this.modules.api.init ) {
					this.modules.api.init();
				}
				// Connect API to state
				if ( this.modules.state && 'function' === typeof this.modules.state.setApi ) {
					this.modules.state.setApi( this.modules.api );
				}
			}

			// Type registry for discount type modules
			if ( !this.modules.typeRegistry ) {
				this.modules.typeRegistry = new SCD.Modules.Discounts.TypeRegistry( this.modules.state );
				if ( 'function' === typeof this.modules.typeRegistry.init ) {
					this.modules.typeRegistry.init();
				}
				// TypeRegistry is not a field handler - it's a module registry
				// Complex fields like tiers/bogo_config/thresholds are managed by discount type modules through state
			}

			// Setup event-driven handler registration to avoid race conditions
			this.setupComplexFieldHandlerRegistration();

			// Register handlers for any already-initialized instances
			this.registerExistingHandlers();
		},

		/**
		 * Setup event-driven complex field handler registration
		 */
		setupComplexFieldHandlerRegistration: function() {
			var self = this;

			// Listen for discount type instance creation
			$( document ).on( 'scd:discount:type:instance:created.orchestrator', function( event, data ) {
				if ( data && data.typeId && data.instance ) {
					self.registerHandlerForType( data.typeId, data.instance );
				}
			} );
		},

		/**
		 * Register handler for a specific discount type
		 * @param {string} typeId - Type identifier
		 * @param {object} instance - Discount type instance
		 */
		registerHandlerForType: function( typeId, instance ) {
			var handlerName = null;

			switch ( typeId ) {
				case 'tiered':
					handlerName = 'tieredDiscount';
					break;
				case 'bogo':
					handlerName = 'bogoDiscount';
					break;
				case 'spend_threshold':
					handlerName = 'spendThreshold';
					break;
			}

			if ( handlerName && instance ) {
				this.registerComplexFieldHandler( handlerName, instance );
			}
		},

		/**
		 * Register handlers for already-initialized type instances
		 */
		registerExistingHandlers: function() {
			if ( !this.modules.typeRegistry || !this.modules.typeRegistry.instances ) {
				return;
			}

			var self = this;
			var instances = this.modules.typeRegistry.instances;
			Object.keys( instances ).forEach( function( typeId ) {
				if ( instances[typeId] ) {
					self.registerHandlerForType( typeId, instances[typeId] );
				}
			} );
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
			this.bindDelegatedEvent( document, '.scd-discount-type-card', 'click', function( e ) {
				// Allow upgrade button clicks to navigate to pricing page
				var $target = $( e.target );
				if ( $target.closest( '.button, a' ).length && $target.closest( '.scd-pro-feature-unavailable' ).length ) {
					// Clicking upgrade button - allow default navigation
					return;
				}

				e.preventDefault();
				self.handleDiscountTypeSelect( $( this ) );
			} );

			// Discount value changes
			this.bindDelegatedEvent( document, '#discount_value_percentage, #discount_value_fixed', 'input change', function() {
				var $field = $( this );
				// Clear error using ValidationError component
				if ( window.SCD && window.SCD.ValidationError ) {
					window.SCD.ValidationError.clear( $field );
				}
				self.updateDiscountValue( $field );
			} );

			// BOGO fields
			this.bindDelegatedEvent( document, '#bogo_buy_quantity, #bogo_get_quantity, #bogo_discount', 'input change', function() {
				// Clear error for bogo_config using ValidationError component
				var $bogoField = $( '[name="bogo_config"]' );
				if ( $bogoField.length && window.SCD && window.SCD.ValidationError ) {
					window.SCD.ValidationError.clear( $bogoField );
				}
				self.updateBOGOConfig();
			} );

			// Tier management
			this.bindDelegatedEvent( document, '.scd-add-tier', 'click', function( e ) {
				e.preventDefault();
				// Clear error for tiers using ValidationError component
				var $tiersField = $( '[name="tiers"]' );
				if ( $tiersField.length && window.SCD && window.SCD.ValidationError ) {
					window.SCD.ValidationError.clear( $tiersField );
				}
				// TypeRegistry handles the actual tier addition
			} );

			// Threshold management
			this.bindDelegatedEvent( document, '.scd-add-threshold', 'click', function( e ) {
				e.preventDefault();
				// Clear error for thresholds using ValidationError component
				var $thresholdsField = $( '[name="thresholds"]' );
				if ( $thresholdsField.length && window.SCD && window.SCD.ValidationError ) {
					window.SCD.ValidationError.clear( $thresholdsField );
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
			this.bindDelegatedEvent( document, '#badge_enabled, #badge_text, #badge_position, #badge_bg_color, #badge_text_color', 'change', function() {
				self.updateBadgeSettings();
			} );

			// Collapsible sections
			this.bindDelegatedEvent( document, '.scd-collapsible-trigger', 'click', function( e ) {
				e.preventDefault();
				self.toggleSection( $( this ).closest( '.scd-collapsible' ) );
			} );

			// Listen for state changes
			this.bindCustomEvent( 'scd:discounts:state:changed', function( event, change ) {
				if ( 'discountType' === change.property ) {
					self.updateDiscountTypeUI( change.value );
				}
			} );

			// Listen for field change events from modules (single source of truth)
			this.bindCustomEvent( 'scd:discounts:field:changed', function( event, data ) {
				if ( data && data.field && data.value !== undefined ) {
					// Update state through orchestrator
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
			// Initialize collapsible sections
			this.initializeCollapsibles();

			// Set initial discount type UI
			var currentType = this.getDiscountType();
			if ( currentType ) {
				this.updateDiscountTypeUI( currentType );
			}

		},

		// loadData method removed - data population is handled by wizard orchestrator
		// which calls populateFields() directly with saved data from scdWizardData.currentCampaign

		/**
		 * Custom logic after field population
		 * Called by StepPersistence mixin after standard field population
		 * @param {object} data - Populated data
		 */
		onPopulateFieldsComplete: function( data ) {
			// All standard fields and complex fields are populated by parent method
			// Only handle discount type UI updates

			// Discount type
			var discountType = this.getPropertyValue( data, [ 'discountType' ] );
			if ( discountType ) {
				// Update field value directly using jQuery
				$( '#discount_type' ).val( discountType );
				// Clear all selections first, then select the correct one
				$( '.scd-discount-type-card' ).removeClass( 'selected' );
				$( '.scd-discount-type-card[data-type="' + discountType + '"]' ).addClass( 'selected' );

				// Update UI to reflect loaded data
				this.updateDiscountTypeUI( discountType );

				// Activate discount type in registry
				if ( this.modules.typeRegistry ) {
					this.modules.typeRegistry.activateType( discountType );
				}
			} else {
				// Initialize with default discount type if no saved data
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

			// Check if card is locked (PRO feature)
			if ( $card.data( 'locked' ) === true || $card.attr( 'data-locked' ) === 'true' || $card.hasClass( 'scd-discount-type-card--locked' ) ) {
				// Show overlay for locked PRO type but don't allow actual selection
				var $detailsContainer = $( '#scd-discount-details-container' );
				if ( $detailsContainer.length ) {
					$detailsContainer.addClass( 'scd-pro-container--locked' );
					$detailsContainer.attr( 'data-active-type', discountType );
				}
				return;
			}

			// Clear any errors
			this.clearFieldError( 'discount_type' );

			// Update UI
			$( '.scd-discount-type-card' ).removeClass( 'selected' );
			$card.addClass( 'selected' );

			// Update field value directly using jQuery
			$( '#discount_type' ).val( discountType ).trigger( 'change' );

			// Update state
			if ( this.modules.state ) {
				this.modules.state.setState( { discountType: discountType } );
			}

			// Activate type module
			if ( this.modules.typeRegistry ) {
				this.modules.typeRegistry.activateType( discountType );
			}

			// Update UI
			this.updateDiscountTypeUI( discountType );

		},

		/**
		 * Update discount type UI
		 * @param discountType
		 */
		updateDiscountTypeUI: function( discountType ) {
			// Hide all strategy options using CSS class (not inline styles)
			$( '.scd-strategy-options' ).removeClass( 'active' );

			// Show selected strategy using CSS class (not inline styles)
			if ( discountType ) {
				$( '.scd-strategy-' + discountType ).addClass( 'active' );
				$( '#discount-value-card' ).show();
				$( '#discount-rules-card' ).removeClass( 'scd-hidden' ).show();
				// Update field value directly using jQuery
				$( '#discount_type' ).val( discountType );
				$( '.scd-discount-type-card' ).removeClass( 'selected' );
				$( '.scd-discount-type-card[data-type="' + discountType + '"]' ).addClass( 'selected' );

				// Update discount details container for PRO types
				// Only lock if the discount type card itself is locked (free user trying to use PRO type)
				var proTypes = [ 'tiered', 'bogo', 'spend_threshold' ];
				var $detailsContainer = $( '#scd-discount-details-container' );
				if ( $detailsContainer.length ) {
					var isPro = proTypes.indexOf( discountType ) !== -1;
					var $card = $( '.scd-discount-type-card[data-type="' + discountType + '"]' );
					var isCardLocked = $card.length && ( $card.attr( 'data-locked' ) === 'true' || $card.hasClass( 'scd-discount-type-card--locked' ) );

					if ( isPro && isCardLocked ) {
						// PRO type that user doesn't have access to
						$detailsContainer.addClass( 'scd-pro-container--locked' );
						$detailsContainer.attr( 'data-active-type', discountType );
					} else {
						// Free type or PRO type that user has access to
						$detailsContainer.removeClass( 'scd-pro-container--locked' );
						$detailsContainer.removeAttr( 'data-active-type' );
					}
				}
			}
		},

		/**
		 * Update discount value in state
		 * @param $field
		 */
		updateDiscountValue: function( $field ) {
			var fieldName = $field.attr( 'name' );
			var value = parseFloat( $field.val() ) || 0;

			// Update state configuration - state module handles value synchronization
			if ( this.modules.state ) {
				// Update the config only - state._updateDiscountValue() will handle discountValue
				this.modules.state.updateCurrentConfig( { value: value } );
			}

			// Validate field using real-time validation
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
				discountPercentage: parseFloat( $( '#bogo_discount' ).val() ) || 100
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
					badgePosition: $( '#badge_position' ).val(),
					badgeBgColor: $( '#badge_bg_color' ).val(),
					badgeTextColor: $( '#badge_text_color' ).val()
				} );
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

			// Check for currently selected discount type card
			var $selectedCard = $( '.scd-discount-type-card.selected' );
			if ( $selectedCard.length ) {
				return $selectedCard.data( 'type' );
			}

			// Check hidden field
			var hiddenValue = $( '#discount_type' ).val();
			if ( hiddenValue ) {
				return hiddenValue;
			}

			// Default fallback
			return 'percentage';
		},

		// Note: collectData and validateData are handled by StepPersistence mixin
		// The mixin uses field definitions to automatically collect and validate all fields
		// Business logic validation is handled by the PHP unified validator

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

			$section.toggleClass( 'scd-collapsed' );

			// Save state to localStorage
			var sectionId = $section.data( 'section' );
			if ( sectionId ) {
				this.saveCollapsibleState( sectionId, $section.hasClass( 'scd-collapsed' ) );
			}
		},

		/**
		 * Initialize collapsible sections
		 */
		initializeCollapsibles: function() {
			var savedState = this.getCollapsibleState();

			this.$container.find( '.scd-collapsible' ).each( function() {
				var $section = $( this );
				var sectionId = $section.data( 'section' );

				if ( true === savedState[sectionId] ) {
					$section.addClass( 'scd-collapsed' );
				}
			} );
		},

		/**
		 * Get collapsible state from localStorage
		 */
		getCollapsibleState: function() {
			try {
				var state = localStorage.getItem( 'scd_collapsible_state' );
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
				localStorage.setItem( 'scd_collapsible_state', JSON.stringify( state ) );
			} catch ( e ) {
				// Fail silently
			}
		},

		// Note: Tooltip methods (initializeTooltips, showTooltip, hideTooltip)
		// are now handled by shared SCD.TooltipManager utility

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

			// Remove tooltip handlers
			if ( this.$container ) {
				this.$container.find( '[data-tooltip]' ).off( '.scd-tooltip' );
			}

			// Unbind all events
			if ( 'function' === typeof this.unbindAllEvents ) {
				this.unbindAllEvents();
			}

			// Clear references
			this.$container = null;
			this.initialized = false;

			// Call parent destroy
			if ( SCD.Shared.BaseOrchestrator.prototype.destroy ) {
				SCD.Shared.BaseOrchestrator.prototype.destroy.call( this );
			}
		}
	} );

} )( jQuery );
