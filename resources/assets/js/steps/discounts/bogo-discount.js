/**
 * Bogo Discount
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/bogo-discount.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register BOGO discount using utility with inheritance
	SCD.Utils.registerModule( 'SCD.Modules.Discounts.Types', 'BogoDiscount', function( state ) {
		// Call parent constructor
		SCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'bogo';
		this.config = SCD.Modules.Discounts.Config;
		this.maxRules = 5;

		// Ready state tracking
		this._ready = false;

		if ( !SCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'BogoDiscount' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	SCD.Modules.Discounts.Types.BogoDiscount.prototype = Object.create( SCD.Modules.Discounts.Types.BaseDiscount.prototype );
	SCD.Modules.Discounts.Types.BogoDiscount.prototype.constructor =
		SCD.Modules.Discounts.Types.BogoDiscount;

	// Override methods using utility extend
	SCD.Utils.extend( SCD.Modules.Discounts.Types.BogoDiscount.prototype, {
		/**
		 * Initialize BOGO discount
		 */
		init: function() {
			SCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );

			this.setupBogoHandlers();
			this.setupPresetHandler();

			// Mark as ready after initialization complete
			this._ready = true;

			// Trigger ready event
			this.state.triggerChange( 'bogo:ready' );
		},

		/**
		 * Set default values for BOGO discount
		 */
		setDefaults: function() {
			// Don't set default BOGO rules - let user define them
			var self = this;
			setTimeout( function() {
				if ( 'function' === typeof self.updatePreview ) {
					self.updatePreview();
				}
			}, 100 );
		},

		/**
		 * Clear BOGO discount data
		 */
		clearData: function() {
			this.state.setState( {
				bogoConfig: {
					rules: [],
					allowMultiple: true
				}
			} );
		},

		/**
		 * Show BOGO discount UI
		 */
		showUI: function() {
			// First check for the new modular container
			var $container = $( '.scd-bogo-options' );

			// If not found, use the existing PHP template container
			if ( !$container.length ) {
				$container = $( '.scd-strategy-bogo' );
			}

			if ( $container.length ) {
				$container.addClass( 'active' );

				// Only render BOGO rules if we have the modular container
				if ( $container.hasClass( 'scd-bogo-options' ) ) {
					this.renderBogoRules();
				}
				// Otherwise, the PHP template fields are already there

				// Trigger initial preview update
				var self = this;
				setTimeout( function() {
					self.updatePreview();
				}, 100 );
			}
		},

		/**
		 * Hide BOGO discount UI
		 */
		hideUI: function() {
			$( '.scd-bogo-options, .scd-strategy-bogo' ).removeClass( 'active' );
		},

		/**
		 * Setup BOGO-specific event handlers
		 */
		setupBogoHandlers: function() {
			var self = this;

			$( document ).off( 'click.bogo' ).on( 'click.bogo', '.scd-add-bogo-rule', function( e ) {
				e.preventDefault();
				self.addBogoRule();
			} );

			$( document ).on( 'click.bogo', '.scd-remove-bogo-rule', function( e ) {
				e.preventDefault();
				var index = $( this ).data( 'index' );
				self.removeBogoRule( index );
			} );

			// Rule field changes
			$( document ).on( 'change.bogo', '.scd-bogo-input', function() {
				self.updateBogoRuleFromInput( $( this ) );
			} );

			// Apply to change ( same/different products )
			$( document ).on( 'change.bogo', '.scd-bogo-apply-to', function() {
				var index = $( this ).data( 'index' );
				var value = $( this ).val();
				self.handleApplyToChange( index, value );
			} );

			// Allow multiple toggle
			$( document ).on( 'change.bogo', '[name="bogo_allow_multiple"]', function() {
				var config = self.state.getData( 'bogoConfig' ) || {};
				config.allowMultiple = $( this ).is( ':checked' );
				self.state.setState( { bogoConfig: config } );
			} );

			// Simple PHP template field handlers
			$( document ).on( 'input.bogo change.bogo', '#bogo_buy_quantity, #bogo_get_quantity, #bogo_discount_percentage', function() {
				if ( window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.clear( $( this ) );
				}

				self.updatePreview();

				// Also update the state for visual preview
				var buyQty = parseInt( $( '#bogo_buy_quantity' ).val() ) || 1;
				var getQty = parseInt( $( '#bogo_get_quantity' ).val() ) || 1;
				var discount = parseFloat( $( '#bogo_discount_percentage' ).val() ) || 100;

				self.state.setState( {
					bogoConfig: {
						rules: [ {
							buyQuantity: buyQty,
							getQuantity: getQty,
							discountPercent: discount,
							applyTo: 'same',
							getProducts: []
						} ],
						allowMultiple: true
					}
				} );

			} );
		},

		/**
		 * Render BOGO rules UI
		 */
		renderBogoRules: function() {
			var config = this.state.getData( 'bogoConfig' ) || {};
			var rules = config.rules || [];
			var $container = $( '.scd-bogo-rules-container' );

			if ( !$container.length ) {return;}

			var html = '';

			var self = this;
			rules.forEach( function( rule, index ) {
				html += self.renderBogoRuleRow( rule, index );
			} );

			$container.html( html );

			var $addButton = $( '.scd-add-bogo-rule' );
			if ( rules.length >= this.maxRules ) {
				$addButton.prop( 'disabled', true ).text( 'Maximum rules reached' );
			} else {
				$addButton.prop( 'disabled', false ).text( 'Add BOGO Rule' );
			}

			$( '[name="bogo_allow_multiple"]' ).prop( 'checked', false !== config.allowMultiple );
		},

		/**
		 * Render a single BOGO rule row
		 * @param rule
		 * @param index
		 */
		renderBogoRuleRow: function( rule, index ) {
			var presetOptions = [
				{ buy: 1, get: 1, discount: 100, label: 'Buy 1 Get 1 Free' },
				{ buy: 2, get: 1, discount: 100, label: 'Buy 2 Get 1 Free' },
				{ buy: 1, get: 1, discount: 50, label: 'Buy 1 Get 1 at 50% Off' },
				{ buy: 3, get: 1, discount: 100, label: 'Buy 3 Get 1 Free' },
				{ buy: 0, get: 0, discount: 0, label: 'Custom' }
			];

			var selectedPreset = 'custom';
			presetOptions.forEach( function( preset ) {
				if ( preset.buy === rule.buyQuantity &&
                    preset.get === rule.getQuantity &&
                    preset.discount === rule.discountPercent ) {
					selectedPreset = preset.buy + '_' + preset.get + '_' + preset.discount;
				}
			} );

			var html = '<div class="scd-bogo-rule" data-index="' + index + '">';
			html += '<h4>BOGO Rule ' + ( index + 1 ) + '</h4>';

			html += '<div class="scd-bogo-preset">';
			html += '<label>Quick Select:</label>';
			html += '<select class="scd-bogo-preset-select scd-enhanced-select" data-index="' + index + '">';

			presetOptions.forEach( function( preset ) {
				var optionValue = preset.buy + '_' + preset.get + '_' + preset.discount;
				var isSelected = selectedPreset === optionValue ? 'selected' : '';
				html += '<option value="' + optionValue + '" ' + isSelected + '>' + preset.label + '</option>';
			} );

			html += '</select>';
			html += '</div>';

			html += '<div class="scd-bogo-fields">';

			html += '<div class="scd-field-group">';
			html += '<label>Buy Quantity:</label>';
			html += '<input type="number" class="scd-bogo-input scd-enhanced-input" data-index="' + index + '" data-field="buyQuantity" value="' + ( rule.buyQuantity || 1 ) + '" min="1" step="1">';
			html += '</div>';

			html += '<div class="scd-field-group">';
			html += '<label>Get Quantity:</label>';
			html += '<input type="number" class="scd-bogo-input scd-enhanced-input" data-index="' + index + '" data-field="getQuantity" value="' + ( rule.getQuantity || 1 ) + '" min="1" step="1">';
			html += '</div>';

			html += '<div class="scd-field-group">';
			html += '<label>Discount on Free Items:</label>';
			html += '<input type="number" class="scd-bogo-input scd-enhanced-input" data-index="' + index + '" data-field="discountPercent" value="' + ( rule.discountPercent || 100 ) + '" min="0" max="100" step="1">';
			html += '<span>%</span>';
			html += '</div>';

			html += '<div class="scd-field-group">';
			html += '<label>Apply To:</label>';
			html += '<select class="scd-bogo-apply-to scd-enhanced-select" data-index="' + index + '">';
			html += '<option value="same" ' + ( 'same' === rule.applyTo ? 'selected' : '' ) + '>Same Product</option>';
			html += '<option value="different" ' + ( 'different' === rule.applyTo ? 'selected' : '' ) + '>Different Products</option>';
			html += '</select>';
			html += '</div>';

			if ( 'different' === rule.applyTo ) {
				html += '<div class="scd-field-group scd-get-products">';
				html += '<label>Free Products:</label>';
				html += '<div class="scd-product-selector" data-index="' + index + '">';
				html += '<input type="text" class="scd-product-search" placeholder="Search products..." data-index="' + index + '">';
				html += '<div class="scd-selected-products">';

				if ( rule.getProducts && 0 < rule.getProducts.length ) {
					rule.getProducts.forEach( function( product ) {
						html += '<span class="scd-selected-product" data-id="' + product.id + '">';
						html += this._escapeHtml( product.name );
						html += '<button type="button" class="scd-remove-product" data-index="' + index + '" data-product-id="' + product.id + '">×</button>';
						html += '</span>';
					} );
				}

				html += '</div>';
				html += '</div>';
				html += '</div>';
			}

			html += '<button type="button" class="scd-remove-bogo-rule" data-index="' + index + '">Remove Rule</button>';
			html += '</div>';
			html += '</div>';

			return html;
		},

		/**
		 * Add a new BOGO rule
		 */
		addBogoRule: function() {
			var config = this.state.getData( 'bogoConfig' ) || { rules: [] };

			if ( config.rules.length >= this.maxRules ) {return;}

			var newRule = {
				buyQuantity: 1,
				getQuantity: 1,
				discountPercent: 100,
				applyTo: 'same',
				getProducts: []
			};

			config.rules.push( newRule );
			this.state.setState( { bogoConfig: config } );
			this.renderBogoRules();
			this.syncWithDOMFields( config );
		},

		/**
		 * Remove a BOGO rule
		 * @param index
		 */
		removeBogoRule: function( index ) {
			var config = this.state.getData( 'bogoConfig' ) || { rules: [] };

			if ( 0 <= index && index < config.rules.length ) {
				config.rules.splice( index, 1 );
				this.state.setState( { bogoConfig: config } );
				this.renderBogoRules();
				this.syncWithDOMFields( config );
			}
		},

		/**
		 * Update BOGO rule from input
		 * @param $input
		 */
		updateBogoRuleFromInput: function( $input ) {
			var index = parseInt( $input.data( 'index' ) );
			var field = $input.data( 'field' );
			var value = $input.val();

			var config = this.state.getData( 'bogoConfig' ) || { rules: [] };

			if ( !config.rules[index] ) {return;}

			switch ( field ) {
				case 'buyQuantity':
					config.rules[index].buyQuantity = parseInt( value ) || 1;
					break;

				case 'getQuantity':
					config.rules[index].getQuantity = parseInt( value ) || 1;
					break;

				case 'discountPercent':
					config.rules[index].discountPercent = Math.min( 100, Math.max( 0, parseInt( value ) || 0 ) );
					break;
			}

			this.state.setState( { bogoConfig: config } );

			// Sync with DOM fields for persistence
			this.syncWithDOMFields( config );
		},

		/**
		 * Handle apply to change
		 * @param index
		 * @param value
		 */
		handleApplyToChange: function( index, value ) {
			var config = this.state.getData( 'bogoConfig' ) || { rules: [] };

			if ( !config.rules[index] ) {return;}

			config.rules[index].applyTo = value;

			if ( 'same' === value ) {
				config.rules[index].getProducts = [];
			}

			this.state.setState( { bogoConfig: config } );
			this.renderBogoRules();
		},

		/**
		 * Setup preset handler
		 */
		setupPresetHandler: function() {
			var self = this;

			$( document ).on( 'change.bogo', '.scd-bogo-preset-select', function() {
				var index = $( this ).data( 'index' );
				var preset = $( this ).val().split( '_' ).map( function( v ) { return parseInt( v ); } );

				if ( 0 === preset[0] ) {return;} // Custom option

				var config = self.state.getData( 'bogoConfig' ) || { rules: [] };

				if ( config.rules[index] ) {
					config.rules[index].buyQuantity = preset[0];
					config.rules[index].getQuantity = preset[1];
					config.rules[index].discountPercent = preset[2];

					self.state.setState( { bogoConfig: config } );
					self.renderBogoRules();
				}
			} );
		},

		/**
		 * Handle state changes
		 * @param change
		 */
		handleStateChange: function( change ) {
			if ( 'bogoConfig' === change.property ) {
				// Update UI preview
				this.updatePreview();
			}
		},

		/**
		 * Validate BOGO configuration
		 */
		validate: function() {
			console.group( '🔍 BOGO DISCOUNT - validate()' );
			var errors = {};
			var warnings = {};

			var config = this.state.getData( 'bogoConfig' ) || {};
			var rules = config.rules || [];

			console.log( 'BOGO Config:', config );
			console.log( 'Rules:', rules );
			console.log( 'Field #bogo_buy_quantity exists:', $( '#bogo_buy_quantity' ).length > 0 );
			console.log( 'Field #bogo_buy_quantity value:', $( '#bogo_buy_quantity' ).val() );

			if ( 0 === rules.length ) {
				errors.bogo_buy_quantity = 'BOGO configuration is required';
				console.warn( '❌ No BOGO rules configured' );
			} else {
				var rule = rules[0];
				console.log( 'Validating rule:', rule );

				if ( !rule.buyQuantity || 0 >= rule.buyQuantity ) {
					errors.bogo_buy_quantity = 'Buy quantity must be at least 1';
					console.warn( '❌ Invalid buy quantity:', rule.buyQuantity );
				}

				if ( !rule.getQuantity || 0 >= rule.getQuantity ) {
					errors.bogo_get_quantity = 'Get quantity must be at least 1';
					console.warn( '❌ Invalid get quantity:', rule.getQuantity );
				}

				if ( undefined === rule.discountPercent || 0 > rule.discountPercent || 100 < rule.discountPercent ) {
					errors.bogo_discount_percentage = 'Discount must be between 0-100%';
					console.warn( '❌ Invalid discount percent:', rule.discountPercent );
				}

				if ( 'different' === rule.applyTo && ( !rule.getProducts || 0 === rule.getProducts.length ) ) {
					errors.bogo_get_products = 'Select products for free items';
					console.warn( '❌ No products selected for "different" apply mode' );
				}

				if ( rule.getQuantity > rule.buyQuantity ) {
					warnings.bogo_get_quantity = 'Getting more items than buying is unusual';
					console.log( '⚠️ Warning: Get quantity exceeds buy quantity' );
				}
			}

			var result = {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};

			console.log( 'Validation result:', result );
			console.groupEnd();
			return result;
		},

		/**
		 * Collect BOGO discount data
		 */
		collectData: function() {
			var config = this.state.getData( 'bogoConfig' ) || {};

			return {
				discountType: 'bogo',
				bogoConfig: {
					rules: config.rules || [],
					allowMultiple: false !== config.allowMultiple
				}
			};
		},

		/**
		 * Update BOGO preview text
		 */
		updatePreview: function() {
			var $preview = $( '#bogo-preview .preview-text' );
			if ( !$preview.length ) {return;}

			var buyQty = parseInt( $( '#bogo_buy_quantity' ).val() ) || 0;
			var getQty = parseInt( $( '#bogo_get_quantity' ).val() ) || 0;
			var discount = parseFloat( $( '#bogo_discount_percentage' ).val() );

			if ( 0 < buyQty && 0 < getQty && !isNaN( discount ) ) {
				var previewText = '';
				if ( 100 === discount ) {
					previewText = 'Buy ' + buyQty + ' Get ' + getQty + ' Free';
				} else {
					previewText = 'Buy ' + buyQty + ' Get ' + getQty + ' at ' + discount + '% off';
				}
				$preview.removeClass( 'placeholder' ).text( previewText );
			} else {
				$preview.addClass( 'placeholder' ).text( 'Configure quantities to see the BOGO preview' );
			}
		},

		/**
		 * Load BOGO discount data
		 * @param data
		 */
		loadData: function( data ) {
			// Data already converted to camelCase by server
			if ( data.bogoConfig ) {
				var config = data.bogoConfig;

				this.state.setState( {
					bogoConfig: {
						rules: config.rules || [],
						allowMultiple: false !== config.allowMultiple
					}
				} );
				this.renderBogoRules();
				return;
			}

			// Sync with DOM fields after loading to ensure persistence works
			this.syncWithDOMFields();
		},

		/**
		 * Sync BOGO data with DOM fields for StepPersistence
		 * @param config
		 */
		syncWithDOMFields: function( config ) {
			if ( !config ) {
				config = this.state.getData( 'bogoConfig' ) || { rules: [] };
			}

			var $bogoConfig = $( '#bogo_config' );
			if ( $bogoConfig.length ) {
				// Server will auto-convert camelCase to snake_case
				$bogoConfig.data( 'value', config );
				$bogoConfig.attr( 'data-value', JSON.stringify( config ) );
				$bogoConfig.trigger( 'change' );
			}

			// Also update individual BOGO fields for compatibility
			if ( config.rules && 0 < config.rules.length ) {
				var rule = config.rules[0];

				var $buyQty = $( '#bogo_buy_quantity' );
				if ( $buyQty.length && $buyQty.val() !== rule.buyQuantity ) {
					$buyQty.val( rule.buyQuantity ).trigger( 'change' );
				}

				var $getQty = $( '#bogo_get_quantity' );
				if ( $getQty.length && $getQty.val() !== rule.getQuantity ) {
					$getQty.val( rule.getQuantity ).trigger( 'change' );
				}

				var $discount = $( '#bogo_discount_percentage' );
				if ( $discount.length && $discount.val() !== rule.discountPercent ) {
					$discount.val( rule.discountPercent ).trigger( 'change' );
				}
			}
		},

		/**
		 * Get summary text
		 */
		getSummary: function() {
			var config = this.state.getData( 'bogoConfig' ) || {};
			var rules = config.rules || [];

			if ( 0 === rules.length ) {
				return 'No BOGO rules configured';
			}

			if ( 1 === rules.length ) {
				var rule = rules[0];
				var discount = 100 === rule.discountPercent ? 'Free' : '' + ( rule.discountPercent ) + '% Off';
				return 'Buy ' + ( rule.buyQuantity ) + ' Get ' + ( rule.getQuantity ) + ' ' + ( discount ) + '';
			} else {
				return '' + ( rules.length ) + ' BOGO rules configured';
			}
		},

		/**
		 * Get preview configuration
		 */
		getPreviewConfig: function() {
			var config = this.state.getData( 'bogoConfig' ) || {};

			return {
				type: 'bogo',
				rules: ( config.rules || [] ).map( function( rule ) {
					return {
						buy: rule.buyQuantity,
						get: rule.getQuantity,
						discount: rule.discountPercent,
						applyTo: rule.applyTo,
						products: rule.getProducts || []
					};
				} ),
				allowMultiple: false !== config.allowMultiple
			};
		},

		/**
		 * Get BOGO configuration for backend (complex field handler)
		 * Returns snake_case keys matching PHP schema
		 * @return {object} BOGO configuration object
		 */
		getValue: function() {
			try {
				var config = {
					buyQuantity: parseInt( $( '#bogo_buy_quantity' ).val() ) || 1,           // camelCase - auto-converts to buy_quantity
					getQuantity: parseInt( $( '#bogo_get_quantity' ).val() ) || 1,           // camelCase - auto-converts to get_quantity
					discountPercentage: parseFloat( $( '#bogo_discount_percentage' ).val() ) || 100  // camelCase - auto-converts to discount_percentage
				};

				return config;
			} catch ( error ) {
				console.error( '[BOGODiscount] getValue error:', error );
				return { buyQuantity: 1, getQuantity: 1, discountPercentage: 100 };
			}
		},

		/**
		 * Set BOGO configuration from backend (complex field handler)
		 * Accepts both camelCase (from Asset Localizer) and snake_case (raw PHP) property names
		 * @param {object} config - BOGO configuration
		 */
		setValue: function( config ) {
			// Case converter handles snake_case → camelCase automatically
			var buyQty = config.buyQuantity;
			var getQty = config.getQuantity;
			var discount = config.discountPercentage || config.discountPercent;

			if ( buyQty ) {
				$( '#bogo_buy_quantity' ).val( buyQty );
			}
			if ( getQty ) {
				$( '#bogo_get_quantity' ).val( getQty );
			}
			if ( discount ) {
				$( '#bogo_discount_percentage' ).val( discount );
			}

			if ( this.state && 'function' === typeof this.state.setState ) {
				this.state.setState( {
					bogoConfig: {
						buyQuantity: parseInt( buyQty ) || 1,
						getQuantity: parseInt( getQty ) || 1,
						discountPercent: parseFloat( discount ) || 100
					}
				} );
			}

			if ( 'function' === typeof this.updatePreview ) {
				this.updatePreview();
			}
		},

		/**
		 * Handler interface - Check if module is ready
		 * @returns {boolean}
		 */
		isReady: function() {
			return true === this._ready;
		},

		/**
		 * Escape HTML for safe output
		 *
		 * @since 1.0.0
		 * @private
		 * @param {string} text - Text to escape
		 * @returns {string} Escaped text
		 */
		_escapeHtml: function( text ) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String( text ).replace( /[&<>"']/g, function( m ) {
				return map[m];
			} );
		},

		/**
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( 'click.bogo' );
			$( document ).off( 'change.bogo' );

			SCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );
