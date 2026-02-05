/**
 * Discounts Config
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-config.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Discounts = WSSCD.Modules.Discounts || {};

	/**
	 * Unified Discount Configuration
	 */
	WSSCD.Modules.Discounts.Config = {
		/**
		 * Static Constants
		 * Immutable values that never change during runtime
		 */
		constants: {
			// DOM Selectors
			selectors: {
				// Main containers
				container: '#wsscd-step-discounts',
				formWrapper: '.wsscd-discount-form-wrapper',
				
				// Form elements
				discountType: '#wsscd-discount-type',
				discountValue: '#wsscd-discount-value',
				recurringDiscount: '#wsscd-recurring-discount',
				limitUsageToggle: '#wsscd-limit-usage-toggle',
				usageLimit: '#wsscd-usage-limit',
				usageLimitPerCustomer: '#wsscd-usage-limit-per-customer',
				
				// Complex fields
				tierContainer: '.wsscd-tier-container',
				thresholdContainer: '.wsscd-threshold-container',
				bogoContainer: '.wsscd-bogo-container',
				
				// Sections
				valueSection: '.wsscd-discount-value-section',
				tierSection: '.wsscd-tier-section',
				thresholdSection: '.wsscd-threshold-section',
				bogoSection: '.wsscd-bogo-section',
				limitSection: '.wsscd-limit-section',
				conditionsSection: '.wsscd-conditions-section',
				
				// Buttons
				addTierButton: '.wsscd-add-tier',
				addThresholdButton: '.wsscd-add-threshold',
				
				// Preview
				previewContainer: '.wsscd-discount-preview',
				previewContent: '.wsscd-discount-preview-content',
				previewLoader: '.wsscd-discount-preview-loader'
			},
			
			// Timing Constants
			timing: {
				debounceDelay: 300,
				previewUpdateDelay: 500,
				validationDelay: 150,
				animationDuration: 200
			},
			
			// Discount Type Definitions
			discountTypes: {
				percentage: {
					id: 'percentage',
					label: 'Percentage Discount',
					icon: 'tag',
					description: 'Discount by a percentage of the original price'
				},
				fixed: {
					id: 'fixed',
					label: 'Fixed Amount',
					icon: 'money',
					description: 'Discount by a fixed amount'
				},
				tiered: {
					id: 'tiered',
					label: 'Tiered Discount',
					icon: 'chart-bar',
					description: 'Different discounts based on quantity or amount'
				},
				bogo: {
					id: 'bogo',
					label: 'Buy One Get One',
					icon: 'products',
					description: 'Buy X Get Y deals'
				},
				spend_threshold: {
					id: 'spend_threshold',
					label: 'Spend Threshold',
					icon: 'cart',
					description: 'Discounts based on cart total'
				}
			}
		},
		
		
		/**
		 * Runtime Configuration
		 * Settings that can be modified during runtime
		 */
		runtime: {
			// Behavior settings
			behavior: {
				validateOnChange: true,
				validateOnBlur: true,
				showPreview: true,
				animateTransitions: true,
				allowDynamicFields: true
			},
			
			// Feature toggles
			features: {
				enableTieredDiscounts: true,
				enableBOGO: true,
				enableSpendThresholds: true,
				enableConditions: true,
				enablePreview: true,
				enableUsageLimits: true
			},
			
			// API configuration
			api: {
				endpoints: {
					validate: 'wsscd_validate_discount',
					preview: 'wsscd_preview_discount',
					save: 'wsscd_save_discount'
				},
				timeout: 30000,
				retryAttempts: 3,
				retryDelay: 1000
			},
			
			// Limits and constraints
			limits: {
				maxTiers: 10,
				maxThresholds: 5,
				maxDiscountValue: {
					percentage: 100,
					fixed: 999999.99
				},
				maxUsageLimit: 999999,
				maxUsageLimitPerCustomer: 999
			},
			
			// User messages
			messages: {
				saving: 'Saving discount settings...',
				saved: 'Discount settings saved',
				error: 'Error saving discount settings',
				validationError: 'Please fix the errors before continuing',
				previewLoading: 'Loading preview...',
				previewError: 'Unable to load preview'
			}
		},
		
		/**
		 * Default Values
		 * Single source of truth for all defaults
		 */
		defaults: {
			// Default values by discount type
			values: {
				percentage: {
					discountValue: 10,
					recurringDiscount: false
				},
				fixed: {
					discountValue: 5,
					recurringDiscount: false
				},
				tiered: {
					tiers: [
						{ minQuantity: 5, discountValue: 5 },
						{ minQuantity: 10, discountValue: 10 }
					]
				},
				bogo: {
					buyQuantity: 1,
					getQuantity: 1,
					discountPercent: 100
				},
				spend_threshold: {
					thresholds: [
						{ minAmount: 50, discountType: 'percentage', discountValue: 10 }
					]
				}
			},
			
			// Default structures
			structures: {
				tier: {
					minQuantity: 2,
					discountValue: 0,
					discountType: 'percentage'
				},
				threshold: {
					minAmount: 0,
					discountType: 'percentage',
					discountValue: 0
				}
			}
		},
		
		/**
		 * Configuration Methods
		 */
		
		/**
		 * Get configuration value by path
		 *
		 * @param {string} path Dot-separated path
		 * @param {*} defaultValue Default if not found
		 * @returns {*} Configuration value
		 */
		get: function( path, defaultValue ) {
			if ( ! path ) {
				return this;
			}
			
			var parts = path.split( '.' );
			var current = this;
			
			for ( var i = 0; i < parts.length; i++ ) {
				if ( 'undefined' === typeof current[parts[i]] ) {
					return defaultValue;
				}
				current = current[parts[i]];
			}
			
			return current;
		},
		
		/**
		 * Set configuration value by path
		 *
		 * @param {string} path Dot-separated path
		 * @param {*} value Value to set
		 */
		set: function( path, value ) {
			if ( ! path ) {
				return;
			}
			
			var parts = path.split( '.' );
			var current = this;
			
			for ( var i = 0; i < parts.length - 1; i++ ) {
				if ( 'undefined' === typeof current[parts[i]] ) {
					current[parts[i]] = {};
				}
				current = current[parts[i]];
			}
			
			current[parts[parts.length - 1]] = value;
		},
		
		/**
		 * Merge configuration
		 *
		 * @param {object} config Configuration to merge
		 */
		merge: function( config ) {
			$.extend( true, this.runtime, config );
		},
		
		/**
		 * Initialize configuration
		 */
		init: function() {
			this.loadPreferences();
			
			// Apply any server-side configuration
			if ( window.wsscdDiscountsConfig ) {
				this.merge( window.wsscdDiscountsConfig );
			}
		},
		
		/**
		 * Load user preferences
		 */
		loadPreferences: function() {
			try {
				var prefs = localStorage.getItem( 'wsscd_discount_preferences' );
				if ( prefs ) {
					var parsed = JSON.parse( prefs );
					if ( parsed.behavior ) {
						$.extend( this.runtime.behavior, parsed.behavior );
					}
				}
			} catch ( e ) {
				// Silent fail for localStorage issues
			}
		},
		
		/**
		 * Save user preferences
		 *
		 * @param {object} prefs Preferences to save
		 */
		savePreferences: function( prefs ) {
			try {
				localStorage.setItem( 'wsscd_discount_preferences', JSON.stringify( prefs ) );
			} catch ( e ) {
				// Silent fail for localStorage issues
			}
		}
	};
	
	$( document ).ready( function() {
		if ( WSSCD.Modules.Discounts.Config ) {
			WSSCD.Modules.Discounts.Config.init();
		}
	} );

} )( jQuery );