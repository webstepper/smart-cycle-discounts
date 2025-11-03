/**
 * Discounts Config
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-config.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * Unified Discount Configuration
	 */
	SCD.Modules.Discounts.Config = {
		/**
		 * Static Constants
		 * Immutable values that never change during runtime
		 */
		constants: {
			// DOM Selectors
			selectors: {
				// Main containers
				container: '#scd-step-discounts',
				formWrapper: '.scd-discount-form-wrapper',
				
				// Form elements
				discountType: '#scd-discount-type',
				discountValue: '#scd-discount-value',
				recurringDiscount: '#scd-recurring-discount',
				limitUsageToggle: '#scd-limit-usage-toggle',
				usageLimit: '#scd-usage-limit',
				usageLimitPerCustomer: '#scd-usage-limit-per-customer',
				
				// Complex fields
				tierContainer: '.scd-tier-container',
				thresholdContainer: '.scd-threshold-container',
				bogoContainer: '.scd-bogo-container',
				
				// Sections
				valueSection: '.scd-discount-value-section',
				tierSection: '.scd-tier-section',
				thresholdSection: '.scd-threshold-section',
				bogoSection: '.scd-bogo-section',
				limitSection: '.scd-limit-section',
				conditionsSection: '.scd-conditions-section',
				
				// Buttons
				addTierButton: '.scd-add-tier',
				addThresholdButton: '.scd-add-threshold',
				
				// Preview
				previewContainer: '.scd-discount-preview',
				previewContent: '.scd-discount-preview-content',
				previewLoader: '.scd-discount-preview-loader'
			},
			
			// Timing Constants
			timing: {
				debounceDelay: 300,
				autoSaveDelay: 2000,
				previewUpdateDelay: 500,
				validationDelay: 150,
				animationDuration: 200
			},
			
			// Discount Type Definitions
			discountTypes: {
				percentage: {
					id: 'percentage',
					label: 'Percentage Discount',
					icon: 'dashicons-tag',
					description: 'Discount by a percentage of the original price'
				},
				fixed: {
					id: 'fixed',
					label: 'Fixed Amount',
					icon: 'dashicons-money-alt',
					description: 'Discount by a fixed amount'
				},
				tiered: {
					id: 'tiered',
					label: 'Tiered Discount',
					icon: 'dashicons-chart-bar',
					description: 'Different discounts based on quantity or amount'
				},
				bogo: {
					id: 'bogo',
					label: 'Buy One Get One',
					icon: 'dashicons-products',
					description: 'Buy X Get Y deals'
				},
				spend_threshold: {
					id: 'spend_threshold',
					label: 'Spend Threshold',
					icon: 'dashicons-cart',
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
				autoSave: true,
				autoSaveDelay: 2000,
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
					validate: 'scd_validate_discount',
					preview: 'scd_preview_discount',
					save: 'scd_save_discount'
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
					minQuantity: 1,
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
			// Load user preferences
			this.loadPreferences();
			
			// Apply any server-side configuration
			if ( window.scdDiscountsConfig ) {
				this.merge( window.scdDiscountsConfig );
			}
		},
		
		/**
		 * Load user preferences
		 */
		loadPreferences: function() {
			try {
				var prefs = localStorage.getItem( 'scd_discount_preferences' );
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
				localStorage.setItem( 'scd_discount_preferences', JSON.stringify( prefs ) );
			} catch ( e ) {
				// Silent fail for localStorage issues
			}
		}
	};
	
	// Initialize on document ready
	$( document ).ready( function() {
		if ( SCD.Modules.Discounts.Config ) {
			SCD.Modules.Discounts.Config.init();
		}
	} );

} )( jQuery );