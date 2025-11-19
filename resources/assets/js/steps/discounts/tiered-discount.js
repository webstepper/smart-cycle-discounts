/**
 * Tiered Discount
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/tiered-discount.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register tiered discount using utility with inheritance
	SCD.Utils.registerModule( 'SCD.Modules.Discounts.Types', 'TieredDiscount', function( state ) {
		// Call parent constructor
		SCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'tiered';
		this.config = SCD.Modules.Discounts.Config;
		this.maxTiers = 5;

		this.currencySymbol = '$'; // Default
		if ( window.scdDiscountStepData && window.scdDiscountStepData.currencySymbol ) {
			this.currencySymbol = this.decodeHtmlEntity( window.scdDiscountStepData.currencySymbol );
		}

		// Ready state tracking (following advanced filters pattern)
		this._ready = false;
		this._queuedPercentageTiers = null;
		this._queuedFixedTiers = null;

		// Internal state for handler pattern
		this._percentageTiers = [];
		this._fixedTiers = [];

		if ( !SCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'TieredDiscount' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	SCD.Modules.Discounts.Types.TieredDiscount.prototype = Object.create( SCD.Modules.Discounts.Types.BaseDiscount.prototype );
	SCD.Modules.Discounts.Types.TieredDiscount.prototype.constructor =
		SCD.Modules.Discounts.Types.TieredDiscount;

	// Override methods using utility extend
	SCD.Utils.extend( SCD.Modules.Discounts.Types.TieredDiscount.prototype, {
		/**
		 * Decode HTML entities
		 * @param html
		 */
		decodeHtmlEntity: function( html ) {
			var parser = new DOMParser();
			var doc = parser.parseFromString( html, 'text/html' );
			return doc.documentElement.textContent || '';
		},
		/**
		 * Initialize tiered discount
		 */
		init: function() {
			SCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );
			var self = this;

			// Use requestAnimationFrame for optimal timing (following advanced filters pattern)
			requestAnimationFrame( function() {
				self.setupTierHandlers();

				// Process any queued data before marking as ready
				if ( null !== self._queuedPercentageTiers ) {
					self.setPercentageTiers( self._queuedPercentageTiers );
					self._queuedPercentageTiers = null;
				}

				if ( null !== self._queuedFixedTiers ) {
					self.setFixedTiers( self._queuedFixedTiers );
					self._queuedFixedTiers = null;
				}

				// Mark as ready
				self._ready = true;

				// Registration is handled by the type registry

				// Trigger ready event
				self.state.triggerChange( 'tiered:ready' );
			} );
		},

		/**
		 * Set default values for tiered discount
		 */
		setDefaults: function() {
			this._percentageTiers = [
				{
					quantity: 5,
					discount: 10,
					type: 'percentage'
				},
				{
					quantity: 10,
					discount: 20,
					type: 'percentage'
				}
			];

			this._fixedTiers = [
				{
					quantity: 5,
					discount: 10,
					type: 'fixed'
				},
				{
					quantity: 10,
					discount: 20,
					type: 'fixed'
				}
			];

			this.state.setState( {
				tierMode: 'percentage',
				percentageTiers: this._percentageTiers,
				fixedTiers: this._fixedTiers,
				tierType: 'quantity',
				applyTo: 'per_item' // Default value for new campaigns
			} );

			this.renderTiers();
			this.updateInlinePreview();
		},

		/**
		 * Clear tiered discount data
		 */
		clearData: function() {
			this.state.setState( {
				tierMode: 'percentage',
				percentageTiers: [],
				fixedTiers: [],
				tierType: 'quantity',
				applyTo: 'per_item'
			} );
		},

		/**
		 * Show tiered discount UI
		 */
		showUI: function() {
			var $container = $( '.scd-strategy-tiered' );

			if ( $container.length ) {
				$container.addClass( 'active' );

				// Sync state with internal data - load from config.tiers
				var fullState = this.state.getState();
				var config = fullState.discountConfig && fullState.discountConfig.tiered;
				if ( config && config.tiers ) {
					var tierMode = fullState.tierMode || 'percentage';
					if ( 'percentage' === tierMode ) {
						this._percentageTiers = config.tiers.slice();
						this._fixedTiers = [];
					} else {
						this._fixedTiers = config.tiers.slice();
						this._percentageTiers = [];
					}
				}

				$( '#percentage-tiers-list' ).attr( 'data-empty-message', 'No percentage tiers added yet' );
				$( '#fixed-tiers-list' ).attr( 'data-empty-message', 'No fixed amount tiers added yet' );

				// Always render tiers for the PHP template
				this.renderTiers();
				// Trigger initial preview update
				this.updateInlinePreview();

				this.checkDiscountProgression();
			}
		},

		/**
		 * Hide tiered discount UI
		 */
		hideUI: function() {
			$( '.scd-strategy-tiered' ).removeClass( 'active' );
		},

		/**
		 * Setup tier-specific event handlers
		 */
		setupTierHandlers: function() {
			var self = this;

			$( '.scd-add-tier' ).off( 'click.tiered' );
			$( document ).off( 'click.tiered', '.scd-remove-tier' );
			$( document ).off( 'change.tiered', '[name="tier_type"]' );
			$( document ).off( 'change.tiered', '[name="apply_to"]' );
			$( document ).off( 'change.tiered input.tiered', '.scd-tier-input' );
			$( '[name="tier_mode"]' ).off( 'change.tiered' );

			// Apply To selection (per_item vs order_total)
			$( '[name="apply_to"]' ).on( 'change.tiered', function() {
				var applyTo = $( this ).val();
				self.state.setState( { applyTo: applyTo } );
				self.updateInlinePreview();
			} );

			// Tier mode selection
			$( '[name="tier_mode"]' ).on( 'change.tiered', function() {
				var mode = $( this ).val();
				self.state.setState( { tierMode: mode } );

				// Show/hide appropriate tier groups
				if ( 'percentage' === mode ) {
					$( '#percentage-tiers-group' ).show();
					$( '#fixed-tiers-group' ).hide();
				} else {
					$( '#percentage-tiers-group' ).hide();
					$( '#fixed-tiers-group' ).show();
				}

				self.renderTiers();
				self.updateInlinePreview();
			} );

			$( '.scd-add-tier' ).on( 'click.tiered', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				var tierType = $( this ).data( 'tier-type' );
				self.addTier( tierType );
				return false;
			} );

			$( document ).on( 'click.tiered', '.scd-remove-tier', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				var $row = $( this ).closest( '.scd-tier-row' );
				var index = parseInt( $row.data( 'index' ), 10 );
				var mode = $row.data( 'mode' );
				self.removeTier( index, mode );
				return false;
			} );

			// Tier type change
			$( document ).on( 'change.tiered', '[name="tier_type"]', function() {
				self.state.setState( { tierType: $( this ).val() } );
				self.renderTiers();
				self.updateInlinePreview();
			} );

			// Tier value changes
			$( document ).on( 'change.tiered input.tiered', '.scd-tier-input', function() {
				self.updateTierFromInput( $( this ) );
				self.updateInlinePreview();
			} );
		},

		/**
		 * Render tier UI
		 */
		renderTiers: function() {
			var fullState = this.state.getState();
			var tierType = fullState.tierType || 'quantity';

			// Render from internal arrays (kept in sync with config.tiers)
			this.renderTierList( this._percentageTiers, tierType, 'percentage', '#percentage-tiers-list' );
			this.renderTierList( this._fixedTiers, tierType, 'fixed', '#fixed-tiers-list' );
		},

		/**
		 * Render a specific tier list
		 * @param tiers
		 * @param tierType
		 * @param mode
		 * @param containerSelector
		 */
		renderTierList: function( tiers, tierType, mode, containerSelector ) {
			var $container = $( containerSelector );
			if ( !$container.length ) {return;}

			var html = '';
			var self = this;

			tiers.forEach( function( tier, index ) {
				html += self.renderTierRow( tier, index, tierType, mode );
			} );

			$container.html( html );

			var $addButton = $( '.scd-add-tier[data-tier-type="' + mode + '"]' );
			if ( tiers.length >= self.maxTiers ) {
				$addButton.prop( 'disabled', true ).html( SCD.IconHelper.warning( { size: 16 } ) + ' Maximum tiers reached' );
			} else {
				var buttonText = 'percentage' === mode ? 'Add Percentage Tier' : 'Add Fixed Amount Tier';
				$addButton.prop( 'disabled', false ).html( SCD.IconHelper.get( 'plus', { size: 16 } ) + ' ' + buttonText );
			}
		},

		/**
		 * Get Row Factory configuration for tiered discount rows
		 * Phase 3: Declarative row configuration using Row Factory
		 * @param tierType
		 * @param mode
		 */
		getTieredRowConfig: function( tierType, mode ) {
			var thresholdLabel = 'quantity' === tierType ? 'Minimum Quantity' : 'Minimum Order Value';
			var thresholdPlaceholder = 'quantity' === tierType ? 'e.g., 5' : 'e.g., 50.00';
			var discountPlaceholder = 'percentage' === mode ? 'e.g., 10' : 'e.g., 5.00';

			return {
				rowClass: 'scd-tier-row',
				dataAttributes: { mode: mode },
				fields: [
					{
						type: 'number',
						name: 'threshold',
						label: thresholdLabel,
						min: 'quantity' === tierType ? 2 : 0.01,
						step: 'quantity' === tierType ? 1 : 0.01,
						placeholder: thresholdPlaceholder,
						class: 'scd-tier-input scd-tier-threshold scd-enhanced-input',
						dataAttributes: { field: 'threshold' },
						suffix: 'value' === tierType ? this.currencySymbol : ''
					},
					{
						type: 'number',
						name: 'discount',
						label: 'Discount Value',
						min: 0,
						step: 0.01,
						placeholder: discountPlaceholder,
						class: 'scd-tier-input scd-tier-discount scd-enhanced-input',
						dataAttributes: { field: 'discount' }
					}
				],
				removeButton: {
					enabled: true,
					label: 'Remove',
					class: 'scd-remove-tier',
					showLabel: true
				}
			};
		},

		/**
		 * Render a single tier row
		 * Phase 3: Uses Row Factory for declarative row generation
		 * @param tier
		 * @param index
		 * @param tierType
		 * @param mode
		 */
		renderTierRow: function( tier, index, tierType, mode ) {
			var rowData = {
				threshold: tier.quantity || tier.value || '',
				discount: tier.discount || ''
			};

			var config = this.getTieredRowConfig( tierType, mode );
			var $row = SCD.Shared.RowFactory.create( config, rowData, index );

			// Add currency prefix for discount field
			var $discountWrapper = $row.find( '[data-field="discount"]' ).closest( '.scd-field-group' );
			if ( $discountWrapper.length ) {
				var prefix = 'percentage' === mode ? '%' : this.currencySymbol;
				$discountWrapper.find( '.scd-input-wrapper' ).addClass( 'scd-input-with-prefix' );
				$discountWrapper.find( '.scd-input-wrapper' ).prepend( '<span class="scd-input-prefix">' + prefix + '</span>' );
			}

			return $row[0].outerHTML;
		},

		/**
		 * Add a new tier
		 * @param mode
		 */
		addTier: function( mode ) {
			var fullState = this.state.getState();
			var tierType = fullState.tierType || 'quantity';

			var tiers = 'percentage' === mode ? this._percentageTiers : this._fixedTiers;

			if ( tiers.length >= this.maxTiers ) {return;}

			// Calculate smart progressive values
			var nextThreshold = 1;
			var nextDiscount = 'percentage' === mode ? 5 : 10;

			if ( 0 < tiers.length ) {
				var lastTier = tiers[tiers.length - 1];
				var lastThreshold = lastTier.quantity || lastTier.value || 0;
				var lastDiscount = lastTier.discount || 0;

				// Use helper functions for smart progression
				nextThreshold = this.calculateNextThreshold( lastThreshold, tierType );
				nextDiscount = this.calculateNextDiscount( lastDiscount, mode );
			}

			var newTier = {
				discount: nextDiscount,
				type: mode
			};
			newTier['quantity' === tierType ? 'quantity' : 'value'] = nextThreshold;

			tiers.push( newTier );

			var allTiers = this._percentageTiers.concat( this._fixedTiers );
			this.state.setState( {
				discountConfig: {
					tiered: {
						tiers: allTiers
					}
				}
			} );

			this.renderTiers();
			this.updateInlinePreview();
			this.checkDiscountProgression();
		},

		/**
		 * Remove a tier
		 * @param index
		 * @param mode
		 */
		removeTier: function( index, mode ) {
			var tiers = 'percentage' === mode ? this._percentageTiers : this._fixedTiers;

			if ( 0 <= index && index < tiers.length ) {
				tiers.splice( index, 1 );

				var tiersKey = 'percentage' === mode ? 'percentageTiers' : 'fixedTiers';
				var updatedState = {};
				updatedState[tiersKey] = tiers.slice(); // Clone array
				this.state.setState( updatedState );

				this.renderTiers();
				this.updateInlinePreview();
				this.checkDiscountProgression();
			}
		},

		/**
		 * Update tier from input change
		 * @param $input
		 */
		updateTierFromInput: function( $input ) {
			var $row = $input.closest( '.scd-tier-row' );
			var index = parseInt( $row.data( 'index' ) );
			var mode = $row.data( 'mode' );
			var field = $input.data( 'field' );
			var value = $input.val();

			var fullState = this.state.getState();
			var tiers = 'percentage' === mode ? this._percentageTiers : this._fixedTiers;
			var tierType = fullState.tierType || 'quantity';

			if ( !tiers[index] ) {return;}

			switch ( field ) {
				case 'threshold':
					if ( 'quantity' === tierType ) {
						tiers[index].quantity = parseInt( value ) || 0;
						delete tiers[index].value;
					} else {
						tiers[index].value = parseFloat( value ) || 0;
						delete tiers[index].quantity;
					}
					break;

					// No discountType case needed - mode is fixed per tier group

				case 'discount':
					tiers[index].discount = parseFloat( value ) || 0;
					break;
			}

			var allTiers = this._percentageTiers.concat( this._fixedTiers );
			this.state.setState( {
				discountConfig: {
					tiered: {
						tiers: allTiers
					}
				}
			} );

			this.checkDiscountProgression();
		},

		/**
		 * Calculate next threshold based on smart progression
		 * @param lastThreshold
		 * @param tierType
		 */
		calculateNextThreshold: function( lastThreshold, tierType ) {
			if ( 'quantity' === tierType ) {
				if ( 5 > lastThreshold ) {
					return lastThreshold + 2;
				} else if ( 10 > lastThreshold ) {
					return lastThreshold + 3;
				} else if ( 25 > lastThreshold ) {
					return lastThreshold + 5;
				} else if ( 100 > lastThreshold ) {
					return Math.ceil( lastThreshold * 1.5 );
				} else {
					return Math.ceil( lastThreshold * 1.25 );
				}
			} else {
				// For value-based, use percentage increase
				return Math.ceil( lastThreshold * 1.5 * 100 ) / 100;
			}
		},

		/**
		 * Calculate next discount based on smart progression
		 * @param lastDiscount
		 * @param mode
		 */
		calculateNextDiscount: function( lastDiscount, mode ) {
			if ( 'percentage' === mode ) {
				// Smaller increases for percentages
				if ( 10 > lastDiscount ) {
					return lastDiscount + 5;
				} else if ( 20 > lastDiscount ) {
					return lastDiscount + 3;
				} else {
					return Math.min( lastDiscount + 2, 50 ); // Cap at 50%
				}
			} else {
				// Larger increases for fixed amounts
				if ( 20 > lastDiscount ) {
					return lastDiscount * 2;
				} else if ( 50 > lastDiscount ) {
					return Math.ceil( lastDiscount * 1.5 );
				} else {
					return Math.ceil( lastDiscount * 1.3 );
				}
			}
		},

		/**
		 * Check if discounts are in proper ascending order
		 */
		checkDiscountProgression: function() {
			var fullState = this.state.getState();
			var tierMode = fullState.tierMode || 'percentage';
			// Read from internal arrays (kept in sync with config.tiers)
			var tiers = 'percentage' === tierMode ? this._percentageTiers : this._fixedTiers;

			$( '.scd-tier-warning' ).remove();

			if ( 2 > tiers.length ) {return;}

			// Sort tiers by threshold to check in order
			var sortedTiers = tiers.slice().sort( function( a, b ) {
				var aThreshold = a.quantity || a.value || 0;
				var bThreshold = b.quantity || b.value || 0;
				return aThreshold - bThreshold;
			} );

			var hasIssue = false;
			for ( var i = 1; i < sortedTiers.length; i++ ) {
				var prevDiscount = sortedTiers[i-1].discount || 0;
				var currDiscount = sortedTiers[i].discount || 0;

				if ( currDiscount <= prevDiscount ) {
					hasIssue = true;
					break;
				}
			}

			if ( hasIssue ) {
				var warningHtml = '<div class="scd-tier-warning">';
				warningHtml += SCD.IconHelper.warning( { size: 16 } );
				warningHtml += '<span class="warning-text">Tip: Higher quantity tiers usually have bigger discounts.</span>';
				warningHtml += '</div>';

				var $activeList = 'percentage' === tierMode ? $( '#percentage-tiers-list' ) : $( '#fixed-tiers-list' );
				$activeList.after( warningHtml );
			}
		},

		/**
		 * Validate tiered configuration
		 */
		validate: function() {
			var errors = {};
			var warnings = {};

			var fullState = this.state.getState();
			var tierMode = fullState.tierMode || 'percentage';
			// Read from internal arrays (kept in sync with config.tiers)
			var tiers = 'percentage' === tierMode ? this._percentageTiers : this._fixedTiers;

			if ( 0 === tiers.length ) {
				errors.tiers = 'At least one tier is required';
			} else {
				var thresholds = [];

				tiers.forEach( function( tier, index ) {
					var threshold = tier.quantity || tier.value || 0;
					var discount = tier.discount || 0;

					if ( 0 >= threshold ) {
						errors['tier_' + ( index ) + '_threshold'] = 'Tier ' + ( index + 1 ) + ': Invalid threshold';
					}

					if ( -1 !== thresholds.indexOf( threshold ) ) {
						errors['tier_' + ( index ) + '_threshold'] = 'Tier ' + ( index + 1 ) + ': Duplicate threshold';
					}
					thresholds.push( threshold );

					if ( 0 >= discount ) {
						errors['tier_' + ( index ) + '_discount'] = 'Tier ' + ( index + 1 ) + ': Invalid discount value';
					} else if ( 'percentage' === tier.type && 100 < discount ) {
						errors['tier_' + ( index ) + '_discount'] = 'Tier ' + ( index + 1 ) + ': Percentage cannot exceed 100%';
					}
				} );

				var sortedThresholds = thresholds.slice().sort( function( a, b ) { return a - b; } );
				if ( JSON.stringify( thresholds ) !== JSON.stringify( sortedThresholds ) ) {
					warnings.tier_order = 'Tiers should be in ascending order by threshold';
				}
			}

			return {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};
		},

		/**
		 * Collect tiered discount data
		 */
		collectData: function() {
			var fullState = this.state.getState();

			var config = fullState.discountConfig && fullState.discountConfig.tiered;
			var tiers = ( config && config.tiers ) || [];
			var tierMode = fullState.tierMode || 'percentage';
			var tierType = fullState.tierType || 'quantity';
			var applyTo = fullState.applyTo || 'per_item'; // Default to per_item

			// Sort tiers by threshold
			var sortedTiers = tiers.slice().sort( function( a, b ) {
				var aThreshold = a.quantity || a.value || 0;
				var bThreshold = b.quantity || b.value || 0;
				return aThreshold - bThreshold;
			} );

			return {
				discountType: 'tiered',
				tierType: tierType,
				tierMode: tierMode,
				applyTo: applyTo,
				tiers: sortedTiers
			};
		},

		/**
		 * Load tiered discount data
		 * @param data
		 */
		loadData: function( data ) {
			var stateUpdate = {};
			var hasData = false;

			// Handle combined tiers format (single source of truth)
			if ( !hasData && data.tiers ) {
				// Normalize tier objects
				var normalizedTiers = Array.isArray( data.tiers ) ?
					data.tiers.map( function( tier ) {
						return {
							quantity: tier.quantity,
							value: tier.value,
							discount: tier.discount,
							type: tier.type || 'percentage'
						};
					} ) : [];

				// Determine mode from tier data
				var mode = data.tierMode || ( normalizedTiers[0] && normalizedTiers[0].type ) || 'percentage';
				stateUpdate.tierMode = mode;

				stateUpdate.discountConfig = stateUpdate.discountConfig || {};
				stateUpdate.discountConfig.tiered = stateUpdate.discountConfig.tiered || {};
				stateUpdate.discountConfig.tiered.tiers = normalizedTiers;

				// Also store tier type if provided
				var tierType = data.tierType || 'quantity';
				stateUpdate.tierType = tierType;

				if ( 'percentage' === mode ) {
					this._percentageTiers = normalizedTiers;
					this._fixedTiers = [];
				} else {
					this._fixedTiers = normalizedTiers;
					this._percentageTiers = [];
				}
				hasData = true;
			}

			if ( data.tierMode ) {
				stateUpdate.tierMode = data.tierMode;
			}

			if ( data.tierType ) {
				stateUpdate.tierType = data.tierType;
			} else {
				stateUpdate.tierType = 'quantity'; // Default
			}

			if ( data.applyTo ) {
				stateUpdate.applyTo = data.applyTo;
			} else {
				stateUpdate.applyTo = 'per_item'; // Default value when not specified in data
			}

			// Only update state if we have data
			if ( hasData ) {
				this.state.setState( stateUpdate );

				// Re-render tiers and check progression after loading
				this.renderTiers();
				var self = this;
				setTimeout( function() {
					self.checkDiscountProgression();
				}, 100 );
			}
		},

		/**
		 * Get summary text
		 */
		getSummary: function() {
			var fullState = this.state.getState();
			var tierMode = fullState.tierMode || 'percentage';
			// Read from internal arrays (kept in sync with config.tiers)
			var tiers = 'percentage' === tierMode ? this._percentageTiers : this._fixedTiers;
			var tierType = fullState.tierType || 'quantity';

			if ( 0 === tiers.length ) {
				return 'No tiers configured';
			}

			var sortedTiers = tiers.slice().sort( function( a, b ) {
				var aThreshold = a.quantity || a.value || 0;
				var bThreshold = b.quantity || b.value || 0;
				return aThreshold - bThreshold;
			} );

			var firstTier = sortedTiers[0];
			var lastTier = sortedTiers[sortedTiers.length - 1];

			if ( 1 === sortedTiers.length ) {
				var threshold = firstTier.quantity || firstTier.value;
				var discountText = firstTier.discount;
				var symbol = 'percentage' === firstTier.type ? '%' : ' ' + this.currencySymbol;
				var itemsText = 'quantity' === tierType ? 'items' : '';
				return discountText + symbol + ' off for ' + threshold + '+ ' + itemsText;
			} else {
				var firstDiscount = firstTier.discount;
				var firstSymbol = 'percentage' === firstTier.type ? '%' : this.currencySymbol;
				var lastDiscount = lastTier.discount;
				var lastSymbol = 'percentage' === lastTier.type ? '%' : this.currencySymbol;
				return sortedTiers.length + ' tiers: ' + firstDiscount + firstSymbol + ' to ' + lastDiscount + lastSymbol + ' off';
			}
		},

		/**
		 * Get preview configuration
		 */
		getPreviewConfig: function() {
			var fullState = this.state.getState();
			var tierMode = fullState.tierMode || 'percentage';
			// Read from internal arrays (kept in sync with config.tiers)
			var tiers = 'percentage' === tierMode ? this._percentageTiers : this._fixedTiers;
			var tierType = fullState.tierType || 'quantity';

			return {
				type: 'tiered',
				tierType: tierType,
				tiers: tiers.map( function( tier ) {
					return {
						threshold: tier.quantity || tier.value || 0,
						discount: tier.discount,
						discountType: tier.type
					};
				} )
			};
		},

		/**
		 * Update inline preview text
		 */
		updateInlinePreview: function() {
			var $preview = $( '#tiered-preview .preview-text' );
			if ( !$preview.length ) {return;}

			var fullState = this.state.getState();
			var tierMode = fullState.tierMode || 'percentage';
			// Read from internal arrays (kept in sync with config.tiers)
			var tiers = 'percentage' === tierMode ? this._percentageTiers : this._fixedTiers;
			var tierType = fullState.tierType || 'quantity';

			if ( 0 === tiers.length ) {
				$preview.addClass( 'placeholder' ).text( 'Add tiers to see the volume discount preview' );
				return;
			}

			// Sort tiers by threshold
			var sortedTiers = tiers.slice().sort( function( a, b ) {
				var aThreshold = a.quantity || a.value || 0;
				var bThreshold = b.quantity || b.value || 0;
				return aThreshold - bThreshold;
			} );

			var previewText = '';
			sortedTiers.forEach( function( tier, index ) {
				if ( 0 < index ) {
					previewText += ' | ';
				}

				var threshold = tier.quantity || tier.value || 0;
				var discount = tier.discount || 0;

				if ( 'quantity' === tierType ) {
					previewText += 'Buy ' + threshold + '+: ';
				} else {
					previewText += 'Order ' + this.currencySymbol + threshold + '+: ';
				}

				// Mode determines discount type for all tiers in the group
				if ( 'percentage' === tierMode ) {
					previewText += discount + '% off';
				} else {
					previewText += this.currencySymbol + discount + ' off';
				}
			}.bind( this ) );

			$preview.removeClass( 'placeholder' ).text( previewText );
		},

		/**
		 * Handle state changes specific to tiered discount
		 * Override parent method to handle tier updates
		 * @param change
		 */
		handleStateChange: function( change ) {
			// Re-render tiers if tier data changes
			if ( 'percentageTiers' === change.property ||
                 'fixedTiers' === change.property ||
                 'tierMode' === change.property ) {
				// Sync internal state with component state
				var fullState = this.state.getState();
				if ( 'percentageTiers' === change.property ) {
					this._percentageTiers = fullState.percentageTiers || [];
				}
				if ( 'fixedTiers' === change.property ) {
					this._fixedTiers = fullState.fixedTiers || [];
				}

				this.renderTiers();
				this.updateInlinePreview();
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
		 * Handler interface - Get percentage tiers
		 * @returns {Array}
		 */
		getPercentageTiers: function() {
			return this._percentageTiers.slice(); // Return copy
		},

		/**
		 * Handler interface - Set percentage tiers
		 * @param {Array} tiers
		 */
		setPercentageTiers: function( tiers ) {
			if ( !this._ready ) {
				this._queuedPercentageTiers = tiers;
				return;
			}

			if ( Array.isArray( tiers ) ) {
				this._percentageTiers = tiers.slice(); // Clone array
				this.state.setState( { percentageTiers: tiers } );
				this.renderTiers();
				this.updateInlinePreview();
			}
		},

		/**
		 * Handler interface - Get fixed tiers
		 * @returns {Array}
		 */
		getFixedTiers: function() {
			return this._fixedTiers.slice(); // Return copy
		},

		/**
		 * Handler interface - Set fixed tiers
		 * @param {Array} tiers
		 */
		setFixedTiers: function( tiers ) {
			if ( !this._ready ) {
				this._queuedFixedTiers = tiers;
				return;
			}

			if ( Array.isArray( tiers ) ) {
				this._fixedTiers = tiers.slice(); // Clone array
				this.state.setState( { fixedTiers: tiers } );
				this.renderTiers();
				this.updateInlinePreview();
			}
		},

		/**
		 * Get consolidated tier data for backend (complex field handler)
		 * Combines percentage and fixed tiers into single array with type property
		 * @return {Array} Consolidated tiers array
		 */
		getValue: function() {
			console.log( '[TieredDiscount] getValue() called' );
			console.log( '[TieredDiscount] _percentageTiers:', this._percentageTiers );
			console.log( '[TieredDiscount] _fixedTiers:', this._fixedTiers );

			try {
				var tiers = [];

				if ( this._percentageTiers && this._percentageTiers.length ) {
					console.log( '[TieredDiscount] Processing', this._percentageTiers.length, 'percentage tiers' );
					this._percentageTiers.forEach( function( tier ) {
						var tierObj = {
							minQuantity: parseInt( tier.quantity || tier.value ) || 0,
							discountValue: parseFloat( tier.discount ) || 0,
							discountType: 'percentage'
						};
						console.log( '[TieredDiscount] Adding percentage tier:', tierObj );
						tiers.push( tierObj );
					} );
				}

				if ( this._fixedTiers && this._fixedTiers.length ) {
					console.log( '[TieredDiscount] Processing', this._fixedTiers.length, 'fixed tiers' );
					this._fixedTiers.forEach( function( tier ) {
						var tierObj = {
							minQuantity: parseInt( tier.quantity || tier.value ) || 0,
							discountValue: parseFloat( tier.discount ) || 0,
							discountType: 'fixed'
						};
						console.log( '[TieredDiscount] Adding fixed tier:', tierObj );
						tiers.push( tierObj );
					} );
				}

				console.log( '[TieredDiscount] getValue() returning:', tiers );
				return tiers;
			} catch ( error ) {
				console.error( '[TieredDiscount] getValue error:', error );
				return [];
			}
		},

		/**
		 * Set tier data from backend (complex field handler)
		 * Splits consolidated array into percentage and fixed tiers
		 * Accepts both camelCase (from Asset Localizer) and snake_case (raw PHP) property names
		 * @param {Array} tiers - Consolidated tiers array from backend
		 */
		setValue: function( tiers ) {
			console.log( '[TieredDiscount] setValue() called with:', tiers );

			try {
				if ( !tiers || !Array.isArray( tiers ) ) {
					return;
				}


				// Split into percentage and fixed arrays
				this._percentageTiers = [];
				this._fixedTiers = [];

				tiers.forEach( function( tier ) {
					var tierObj = {
						quantity: parseInt( tier.min_quantity || tier.minQuantity ) || 0,
						discount: parseFloat( tier.discount_value || tier.discountValue ) || 0,
						type: tier.discount_type || tier.discountType
					};

					if ( 'percentage' === tierObj.type ) {
						this._percentageTiers.push( tierObj );
					} else {
						this._fixedTiers.push( tierObj );
					}
				}.bind( this ) );

				if ( this.state && 'function' === typeof this.state.setState ) {
					this.state.setState( {
						percentageTiers: this._percentageTiers,
						fixedTiers: this._fixedTiers
					} );
				}

				// Re-render UI
				if ( 'function' === typeof this.renderTiers ) {
					this.renderTiers();
				}

				if ( 'function' === typeof this.updateInlinePreview ) {
					this.updateInlinePreview();
				}
			} catch ( error ) {
				console.error( '[TieredDiscount] setValue error:', error );
			}
		},

		/**
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( 'click.tiered' );
			$( document ).off( 'change.tiered input.tiered' );
			$( '[name="apply_to"]' ).off( 'change.tiered' );
			$( '[name="tier_mode"]' ).off( 'change.tiered' );

			SCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );
