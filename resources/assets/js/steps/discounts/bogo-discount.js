/**
 * BOGO ( Buy One Get One ) Discount Type Module
 *
 * Handles Buy One Get One discount configurations using consolidated utilities
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
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

		// Initialize with dependency checks
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
			var self = this;

			// Use requestAnimationFrame for optimal timing
			requestAnimationFrame( function() {
				self.setupBogoHandlers();
				self.setupPresetHandler();

				// Mark as ready
				self._ready = true;

				// Trigger ready event
				self.state.triggerChange( 'bogo:ready' );
			} );
		},

		/**
		 * Set default values for BOGO discount
		 */
		setDefaults: function() {
			// Don't set default BOGO rules - let user define them
			// Show placeholder on initial load
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
				$container.addClass( 'active' ).show();

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

			// Add rule button
			$( document ).off( 'click.bogo' ).on( 'click.bogo', '.scd-add-bogo-rule', function( e ) {
				e.preventDefault();
				self.addBogoRule();
			} );

			// Remove rule button
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
				var config = self.state.getState( 'bogoConfig' ) || {};
				config.allowMultiple = $( this ).is( ':checked' );
				self.state.setState( { bogoConfig: config } );
			} );

			// Simple PHP template field handlers
			$( document ).on( 'input.bogo change.bogo', '#bogo_buy_quantity, #bogo_get_quantity, #bogo_discount', function() {
				// Update preview immediately
				self.updatePreview();

				// Also update the state for visual preview
				var buyQty = parseInt( $( '#bogo_buy_quantity' ).val() ) || 1;
				var getQty = parseInt( $( '#bogo_get_quantity' ).val() ) || 1;
				var discount = parseFloat( $( '#bogo_discount' ).val() ) || 100;

				// Update state with simple BOGO config
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
			var config = this.state.getState( 'bogoConfig' ) || {};
			var rules = config.rules || [];
			var $container = $( '.scd-bogo-rules-container' );

			if ( !$container.length ) {return;}

			var html = '';

			var self = this;
			rules.forEach( function( rule, index ) {
				html += self.renderBogoRuleRow( rule, index );
			} );

			$container.html( html );

			// Update add button state
			var $addButton = $( '.scd-add-bogo-rule' );
			if ( rules.length >= this.maxRules ) {
				$addButton.prop( 'disabled', true ).text( 'Maximum rules reached' );
			} else {
				$addButton.prop( 'disabled', false ).text( 'Add BOGO Rule' );
			}

			// Update allow multiple checkbox
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

			// Check if current rule matches a preset
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
			html += '<select class="scd-bogo-preset-select" data-index="' + index + '">';

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
			html += '<input type="number" class="scd-bogo-input" data-index="' + index + '" data-field="buyQuantity" value="' + ( rule.buyQuantity || 1 ) + '" min="1" step="1">';
			html += '</div>';

			html += '<div class="scd-field-group">';
			html += '<label>Get Quantity:</label>';
			html += '<input type="number" class="scd-bogo-input" data-index="' + index + '" data-field="getQuantity" value="' + ( rule.getQuantity || 1 ) + '" min="1" step="1">';
			html += '</div>';

			html += '<div class="scd-field-group">';
			html += '<label>Discount on Free Items:</label>';
			html += '<input type="number" class="scd-bogo-input" data-index="' + index + '" data-field="discountPercent" value="' + ( rule.discountPercent || 100 ) + '" min="0" max="100" step="1">';
			html += '<span>%</span>';
			html += '</div>';

			html += '<div class="scd-field-group">';
			html += '<label>Apply To:</label>';
			html += '<select class="scd-bogo-apply-to" data-index="' + index + '">';
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
						html += product.name;
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
			var config = this.state.getState( 'bogoConfig' ) || { rules: [] };

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
			var config = this.state.getState( 'bogoConfig' ) || { rules: [] };

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

			var config = this.state.getState( 'bogoConfig' ) || { rules: [] };

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
			var config = this.state.getState( 'bogoConfig' ) || { rules: [] };

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

				var config = self.state.getState( 'bogoConfig' ) || { rules: [] };

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
			var errors = {};
			var warnings = {};

			var config = this.state.getState( 'bogoConfig' ) || {};
			var rules = config.rules || [];

			if ( 0 === rules.length ) {
				errors.bogoRules = 'At least one BOGO rule is required';
			} else {
				rules.forEach( function( rule, index ) {
					// Validate quantities
					if ( !rule.buyQuantity || 0 >= rule.buyQuantity ) {
						errors['bogo_' + ( index ) + '_buy'] = 'Rule ' + ( index + 1 ) + ': Invalid buy quantity';
					}

					if ( !rule.getQuantity || 0 >= rule.getQuantity ) {
						errors['bogo_' + ( index ) + '_get'] = 'Rule ' + ( index + 1 ) + ': Invalid get quantity';
					}

					// Validate discount
					if ( 0 > rule.discountPercent || 100 < rule.discountPercent ) {
						errors['bogo_' + ( index ) + '_discount'] = 'Rule ' + ( index + 1 ) + ': Discount must be between 0-100%';
					}

					// Validate different products selection
					if ( 'different' === rule.applyTo && ( !rule.getProducts || 0 === rule.getProducts.length ) ) {
						errors['bogo_' + ( index ) + '_products'] = 'Rule ' + ( index + 1 ) + ': Select products for free items';
					}

					// Warning for unusual configurations
					if ( rule.getQuantity > rule.buyQuantity ) {
						warnings['bogo_' + ( index ) + '_ratio'] = 'Rule ' + ( index + 1 ) + ': Getting more than buying';
					}
				} );
			}

			return {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};
		},

		/**
		 * Collect BOGO discount data
		 */
		collectData: function() {
			var config = this.state.getState( 'bogoConfig' ) || {};

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

			// Get values from inputs
			var buyQty = parseInt( $( '#bogo_buy_quantity' ).val() ) || 0;
			var getQty = parseInt( $( '#bogo_get_quantity' ).val() ) || 0;
			var discount = parseFloat( $( '#bogo_discount' ).val() );

			// Check if we have valid values
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
			// Check for structured bogo_config first (preferred format)
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

			// Handle simple BOGO fields (template format)
			// Data already converted to camelCase by server
			var buyQuantity = data.bogoBuyQuantity || data.buyQuantity;
			var getQuantity = data.bogoGetQuantity || data.getQuantity;
			var discount = data.bogoDiscount || data.discountPercent;

			if ( buyQuantity || getQuantity || discount ) {
				// Create a single simple rule
				var rule = {
					buyQuantity: parseInt( buyQuantity ) || 2,
					getQuantity: parseInt( getQuantity ) || 1,
					discountPercent: parseFloat( discount ) || 100,
					applyTo: 'same', // Simple BOGO always applies to same products
					getProducts: []
				};

				this.state.setState( {
					bogoConfig: {
						rules: [ rule ],
						allowMultiple: false
					}
				} );
				this.renderBogoRules();
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
				config = this.state.getState( 'bogoConfig' ) || { rules: [] };
			}

			// Update hidden DOM fields that StepPersistence will collect
			var $bogoConfig = $( '#bogo_config' );
			if ( $bogoConfig.length ) {
				// Server will auto-convert camelCase to snake_case
				// Update both jQuery data and DOM attribute for field definitions to collect
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

				var $discount = $( '#bogo_discount' );
				if ( $discount.length && $discount.val() !== rule.discountPercent ) {
					$discount.val( rule.discountPercent ).trigger( 'change' );
				}
			}
		},

		/**
		 * Get summary text
		 */
		getSummary: function() {
			var config = this.state.getState( 'bogoConfig' ) || {};
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
			var config = this.state.getState( 'bogoConfig' ) || {};

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
					buy_quantity: parseInt( $( '#bogo_buy_quantity' ).val() ) || 1,
					get_quantity: parseInt( $( '#bogo_get_quantity' ).val() ) || 1,
					discount_percent: parseFloat( $( '#bogo_discount' ).val() ) || 100
				};

				if ( window.scdDebugDiscounts ) {
				}

				return config;
			} catch ( error ) {
				console.error( '[BOGODiscount] getValue error:', error );
				return { buy_quantity: 1, get_quantity: 1, discount_percent: 100 };
			}
		},

		/**
		 * Set BOGO configuration from backend (complex field handler)
		 * @param {object} config - BOGO configuration with snake_case keys
		 */
		setValue: function( config ) {
			try {
				if ( !config || 'object' !== typeof config ) {
					if ( window.scdDebugDiscounts ) {
					}
					return;
				}

				if ( window.scdDebugDiscounts ) {
				}

				// Set form fields
				if ( config.buy_quantity ) {
					$( '#bogo_buy_quantity' ).val( config.buy_quantity );
				}
				if ( config.get_quantity ) {
					$( '#bogo_get_quantity' ).val( config.get_quantity );
				}
				if ( config.discount_percent ) {
					$( '#bogo_discount' ).val( config.discount_percent );
				}

				// Update state
				if ( this.state && 'function' === typeof this.state.setState ) {
					this.state.setState( {
						bogoConfig: config
					} );
				}

				// Update preview
				if ( 'function' === typeof this.updatePreview ) {
					this.updatePreview();
				}
			} catch ( error ) {
				console.error( '[BOGODiscount] setValue error:', error );
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
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( 'click.bogo' );
			$( document ).off( 'change.bogo' );

			SCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );
