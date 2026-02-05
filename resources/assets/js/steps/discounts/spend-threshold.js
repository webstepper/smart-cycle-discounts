/**
 * @fs_premium_only
 *
 * Spend Threshold
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/spend-threshold.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register spend threshold discount using utility with inheritance
	WSSCD.Utils.registerModule( 'WSSCD.Modules.Discounts.Types', 'SpendThreshold', function( state ) {
		// Call parent constructor
		WSSCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'spend_threshold';
		this.config = WSSCD.Modules.Discounts.Config;
		this.maxThresholds = 5;

		this.currencySymbol = '$'; // Default
		if ( window.wsscdSettings && window.wsscdSettings.currencySymbol ) {
			this.currencySymbol = this.decodeHtmlEntity( window.wsscdSettings.currencySymbol );
		}

		// Ready state tracking (following advanced filters pattern)
		this._ready = false;
		this._queuedPercentageThresholds = null;
		this._queuedFixedThresholds = null;

		// Internal state for handler pattern
		this._percentageThresholds = [];
		this._fixedThresholds = [];

		if ( !WSSCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'SpendThreshold' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	WSSCD.Modules.Discounts.Types.SpendThreshold.prototype = Object.create( WSSCD.Modules.Discounts.Types.BaseDiscount.prototype );
	WSSCD.Modules.Discounts.Types.SpendThreshold.prototype.constructor =
		WSSCD.Modules.Discounts.Types.SpendThreshold;

	// Override methods using utility extend
	WSSCD.Utils.extend( WSSCD.Modules.Discounts.Types.SpendThreshold.prototype, {
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
		 * Initialize spend threshold
		 */
		init: function() {
			WSSCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );

			this.setupThresholdHandlers();

			// Process any queued data
			if ( null !== this._queuedPercentageThresholds ) {
				this.setPercentageThresholds( this._queuedPercentageThresholds );
				this._queuedPercentageThresholds = null;
			}

			if ( null !== this._queuedFixedThresholds ) {
				this.setFixedThresholds( this._queuedFixedThresholds );
				this._queuedFixedThresholds = null;
			}

			// Mark as ready after initialization complete
			this._ready = true;

			// Trigger ready event
			this.state.triggerChange( 'spendAmount:ready' );
		},

		/**
		 * Set default values for spend threshold
		 */
		setDefaults: function() {
			// DO NOT create placeholder data - keep arrays empty
			// Placeholders should be UI-only, not real state data
			var fullState = this.state.getState();

			// Only initialize if state doesn't already have threshold data
			if ( !fullState.percentageSpendThresholds && !fullState.fixedSpendThresholds ) {
				this._percentageThresholds = [];
				this._fixedThresholds = [];

				this.state.setState( {
					thresholdMode: 'percentage',
					percentageSpendThresholds: [],
					fixedSpendThresholds: []
				} );
			} else {
				this._percentageThresholds = fullState.percentageSpendThresholds || [];
				this._fixedThresholds = fullState.fixedSpendThresholds || [];
			}

			this.renderThresholds();
			this.updateInlinePreview();
		},

		/**
		 * Clear spend threshold data
		 */
		clearData: function() {
			// Clear internal arrays
			this._percentageThresholds = [];
			this._fixedThresholds = [];

			// Clear state
			this.state.setState( {
				thresholdMode: 'percentage',
				percentageSpendThresholds: [],
				fixedSpendThresholds: []
			} );
		},

		/**
		 * Show spend threshold UI
		 */
		showUI: function() {
			$( '.wsscd-strategy-spend_threshold' ).addClass( 'active' );

			// Sync state with internal data - ALWAYS use state as source of truth
			var fullState = this.state.getState();

			this._percentageThresholds = fullState.percentageSpendThresholds || [];
			this._fixedThresholds = fullState.fixedSpendThresholds || [];


			// Always render thresholds for the PHP template
			this.renderThresholds();
			// Trigger initial preview update
			this.updateInlinePreview();

			this.checkDiscountProgression();
		},

		/**
		 * Hide spend threshold UI
		 */
		hideUI: function() {
			$( '.wsscd-strategy-spend_threshold' ).removeClass( 'active' );
		},

		/**
		 * Bind events for spend threshold
		 */
		bindEvents: function() {
			// Events are already bound in setupThresholdHandlers
			this.setupThresholdHandlers();
		},

		/**
		 * Unbind events
		 */
		unbindEvents: function() {
			$( document ).off( '.spendthreshold' );
		},

		/**
		 * Setup threshold-specific event handlers
		 */
		setupThresholdHandlers: function() {
			var self = this;

			// Use event delegation on document for better reliability
			$( document ).off( '.spendthreshold' ); // Remove all namespaced events

			// Threshold mode selection
			$( document ).on( 'change.spendthreshold', '.wsscd-strategy-spend_threshold [name="threshold_mode"]', function() {
				var mode = $( this ).val();
				self.state.setState( { thresholdMode: mode } );

				// Show/hide appropriate threshold groups using CSS classes
				if ( 'percentage' === mode ) {
					$( '#percentage-thresholds-group' ).removeClass( 'wsscd-hidden' );
					$( '#fixed-thresholds-group' ).addClass( 'wsscd-hidden' );
				} else {
					$( '#percentage-thresholds-group' ).addClass( 'wsscd-hidden' );
					$( '#fixed-thresholds-group' ).removeClass( 'wsscd-hidden' );
				}

				self.renderThresholds();
				self.updateInlinePreview();
			} );

			$( document ).on( 'click.spendthreshold', '.wsscd-strategy-spend_threshold .wsscd-add-threshold', function( e ) {
				e.preventDefault();
				var thresholdType = $( this ).data( 'threshold-type' );
				self.addThreshold( thresholdType );
			} );

			$( document ).on( 'click.spendthreshold', '.wsscd-strategy-spend_threshold .wsscd-remove-threshold', function( e ) {
				e.preventDefault();
				var $row = $( this ).closest( '.wsscd-threshold-row' );
				var index = $row.data( 'index' );
				var thresholdType = $row.data( 'threshold-type' );
				self.removeThreshold( index, thresholdType );
			} );

			// Handle threshold input changes
			$( document ).on( 'change.spendthreshold input.spendthreshold', '.wsscd-strategy-spend_threshold .wsscd-threshold-input', function() {
				var $input = $( this );
				var $row = $input.closest( '.wsscd-threshold-row' );
				var index = $row.data( 'index' );
				var field = $input.data( 'field' );
				var value = $input.val();
				var thresholdType = $row.data( 'threshold-type' );

				self.updateThreshold( index, field, value, thresholdType );
			} );
		},

		/**
		 * Add a new threshold
		 * @param thresholdType
		 */
		addThreshold: function( thresholdType ) {
			var thresholds = 'percentage' === thresholdType ? this._percentageThresholds : this._fixedThresholds;

			if ( thresholds.length >= this.maxThresholds ) {
				if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
					WSSCD.Shared.NotificationService.warning(
						'Maximum ' + this.maxThresholds + ' thresholds allowed'
					);
				}
				return;
			}

			// Calculate smart default values
			var newThreshold = this.calculateSmartProgression( thresholds, thresholdType );

			thresholds.push( newThreshold );

			var thresholdsKey = 'percentage' === thresholdType ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var updatedState = {};
			updatedState[thresholdsKey] = thresholds.slice(); // Clone array
			this.state.setState( updatedState );


			this.renderThresholds();
			this.updateInlinePreview();
		},

		/**
		 * Calculate smart progression for new threshold
		 * @param existingThresholds
		 * @param type
		 */
		calculateSmartProgression: function( existingThresholds, type ) {
			var lastThreshold = 0 < existingThresholds.length ?
				existingThresholds[existingThresholds.length - 1] : null;

			if ( !lastThreshold ) {
				// First threshold - provide smart defaults to help users
				return {
					spendAmount: 50,
					discountValue: 5,
					discountType: type
				};
			}

			var lastAmount = lastThreshold.spendAmount || 0;
			var lastDiscount = lastThreshold.discountValue || 0;

			// Smart progression: double the amount, increase discount
			var nextAmount = Math.min( lastAmount * 2, 500 ); // Cap at 500
			var nextDiscount;

			if ( 'percentage' === type ) {
				// Increase by 5% each time, max 50%
				nextDiscount = Math.min( lastDiscount + 5, 50 );
			} else {
				// For fixed amount, roughly 10% of the spend threshold
				nextDiscount = Math.round( nextAmount * 0.1 );
			}

			return {
				spendAmount: nextAmount,
				discountValue: nextDiscount,
				discountType: type
			};
		},

		/**
		 * Remove a threshold
		 * @param index
		 * @param thresholdType
		 */
		removeThreshold: function( index, thresholdType ) {
			var thresholds = 'percentage' === thresholdType ? this._percentageThresholds : this._fixedThresholds;

			if ( thresholds[index] ) {
				thresholds.splice( index, 1 );

				var thresholdsKey = 'percentage' === thresholdType ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
				var updatedState = {};
				updatedState[thresholdsKey] = thresholds.slice(); // Clone array
				this.state.setState( updatedState );

				this.renderThresholds();
				this.updateInlinePreview();
				this.checkDiscountProgression();
			}
		},

		/**
		 * Update threshold value
		 * @param index
		 * @param field
		 * @param value
		 * @param thresholdType
		 */
		updateThreshold: function( index, field, value, thresholdType ) {
			var thresholds = 'percentage' === thresholdType ? this._percentageThresholds : this._fixedThresholds;

			if ( !thresholds[index] ) {
				return;
			}

			// Update the discount value
			if ( 'discount_value' === field ) {
				thresholds[index].discountValue = parseFloat( value ) || 0;
			}

			var thresholdsKey = 'percentage' === thresholdType ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var updatedState = {};
			updatedState[thresholdsKey] = thresholds.slice(); // Clone array
			this.state.setState( updatedState );

			this.updateInlinePreview();
			this.checkDiscountProgression();
		},

		/**
		 * Get Row Factory configuration for spend threshold rows
		 * Phase 3: Declarative row configuration using Row Factory
		 * @param thresholdMode
		 */
		getSpendThresholdRowConfig: function( thresholdMode ) {
			var discountPlaceholder = 'percentage' === thresholdMode ? '10' : '5.00';
			var discountMax = 'percentage' === thresholdMode ? 100 : null;

			return {
				rowClass: 'wsscd-threshold-row',
				fieldsWrapperClass: 'wsscd-threshold-fields',
				dataAttributes: { 'threshold-type': thresholdMode },
				fields: [
					{
						type: 'number',
						name: 'threshold',
						label: 'Spend Amount',
						min: 0.01,
						step: 0.01,
						placeholder: '100.00',
						class: 'wsscd-threshold-input wsscd-enhanced-input',
						dataAttributes: { field: 'threshold' },
						prefix: this.currencySymbol
					},
					{
						type: 'number',
						name: 'discount_value',
						label: 'Discount Value',
						min: 0.01,
						max: discountMax,
						step: 0.01,
						placeholder: discountPlaceholder,
						class: 'wsscd-threshold-input wsscd-enhanced-input',
						dataAttributes: { field: 'discount_value' },
						prefix: 'percentage' === thresholdMode ? '%' : this.currencySymbol
					}
				],
				removeButton: {
					enabled: true,
					label: 'Remove threshold',
					class: 'wsscd-button wsscd-button--icon-only wsscd-button--small wsscd-button--ghost-danger wsscd-remove-threshold',
					icon: 'trash',
					showLabel: false
				}
			};
		},

		/**
		 * Render a single threshold row
		 * Phase 3: Uses Row Factory for declarative row generation
		 * @param threshold
		 * @param index
		 * @param thresholdMode
		 */
		renderThresholdRow: function( threshold, index, thresholdMode ) {
			var rowData = {
				threshold: threshold.spendAmount || '',
				discount_value: threshold.discountValue || ''
			};

			var config = this.getSpendThresholdRowConfig( thresholdMode );
			var $row = WSSCD.Shared.RowFactory.create( config, rowData, index );

			return $row[0].outerHTML;
		},

		/**
		 * Render thresholds UI
		 * Phase 3: Uses Row Factory helper for row generation
		 */
		renderThresholds: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';
			var thresholdsKey = 'percentage' === thresholdMode ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];

			var $container = 'percentage' === thresholdMode ?
				$( '#percentage-thresholds-list' ) :
				$( '#fixed-thresholds-list' );

			if ( 0 === thresholds.length ) {
				$container.empty();
				return;
			}

			var html = '';
			var self = this;

			// Sort thresholds by threshold for display
			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				return ( a.spendAmount || 0 ) - ( b.spendAmount || 0 );
			} );

			// Build HTML using Row Factory
			sortedThresholds.forEach( function( threshold ) {
				// Find original index
				var originalIndex = thresholds.indexOf( threshold );
				html += self.renderThresholdRow( threshold, originalIndex, thresholdMode );
			} );

			$container.html( html );
		},

		/**
		 * Update inline preview
		 */
		updateInlinePreview: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';
			var thresholdsKey = 'percentage' === thresholdMode ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];
			var $preview = $( '#spend-threshold-preview .preview-text' );

			if ( 0 === thresholds.length ) {
				$preview.addClass( 'placeholder' ).text( 'Add thresholds to see the spend discount preview' );
				return;
			}

			// Sort thresholds by threshold for display
			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				return ( a.spendAmount || 0 ) - ( b.spendAmount || 0 );
			} );

			var previewText = '';
			var self = this;

			sortedThresholds.forEach( function( threshold, index ) {
				if ( 0 < index ) {previewText += ', ';}

				previewText += 'Spend ' + self.currencySymbol + threshold.spendAmount + ' or more';

				if ( 'percentage' === thresholdMode ) {
					previewText += ' → ' + threshold.discountValue + '% off';
				} else {
					previewText += ' → ' + self.currencySymbol + threshold.discountValue + ' off';
				}
			} );

			$preview.text( previewText ).removeClass( 'placeholder' );
		},

		/**
		 * Check if discounts are in proper ascending order
		 */
		checkDiscountProgression: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';
			var thresholdsKey = 'percentage' === thresholdMode ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];

			$( '.wsscd-threshold-warning' ).remove();

			if ( 2 > thresholds.length ) {return;}

			// Sort thresholds by threshold to check in order
			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				var aAmount = a.spendAmount || 0;
				var bAmount = b.spendAmount || 0;
				return aAmount - bAmount;
			} );

			var hasIssue = false;
			for ( var i = 1; i < sortedThresholds.length; i++ ) {
				var prevDiscount = sortedThresholds[i-1].discountValue || 0;
				var currDiscount = sortedThresholds[i].discountValue || 0;

				if ( currDiscount <= prevDiscount ) {
					hasIssue = true;
					break;
				}
			}

			if ( hasIssue ) {
				var warningIcon = WSSCD.IconHelper ? WSSCD.IconHelper.warning( { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-warning"></span>';
				var warningHtml = '<div class="wsscd-tier-warning wsscd-threshold-warning">';
				warningHtml += warningIcon;
				warningHtml += '<span class="warning-text">Tip: Higher spending thresholds usually have bigger discounts.</span>';
				warningHtml += '</div>';

				var $activeList = 'percentage' === thresholdMode ? $( '#percentage-thresholds-list' ) : $( '#fixed-thresholds-list' );
				$activeList.after( warningHtml );
			}
		},

		/**
		 * Validate spend threshold configuration
		 */
		validate: function() {
			var errors = {};
			var warnings = {};

			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';
			var thresholdsKey = 'percentage' === thresholdMode ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];

			if ( 0 === thresholds.length ) {
				errors.thresholds = 'At least one threshold is required';
			}

			var amountsSeen = {};
			var hasInvalidThresholds = false;

			thresholds.forEach( function( threshold, index ) {
				var spendAmount = threshold.spendAmount || 0;

				if ( !spendAmount || 0 >= spendAmount ) {
					hasInvalidThresholds = true;
				} else if ( amountsSeen[spendAmount] ) {
					hasInvalidThresholds = true;
				} else {
					amountsSeen[spendAmount] = true;
				}

				if ( !threshold.discountValue || 0 >= threshold.discountValue ) {
					hasInvalidThresholds = true;
				}

				if ( 'percentage' === thresholdMode && 100 < threshold.discountValue ) {
					hasInvalidThresholds = true;
				}
			} );

			// Show single error on container instead of individual row fields
			if ( hasInvalidThresholds ) {
				errors.thresholds = 'One or more thresholds have invalid values';
			}

			var result = {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};

			return result;
		},

		/**
		 * Get configuration data
		 *
		 * Reads from the correct state property based on current mode.
		 */
		getData: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';

			// Read from mode-specific state property
			var thresholdsKey = 'percentage' === thresholdMode ?
				'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];

			return {
				thresholdMode: thresholdMode,
				thresholds: thresholds
			};
		},

		/**
		 * Load spend threshold data
		 * @param data
		 */
		loadData: function( data ) {
			var stateUpdate = {};
			var hasData = false;

			// Handle combined thresholds format (single source of truth)
			if ( !hasData && data.thresholds ) {
				// Normalize threshold objects (case converter handles snake_case → camelCase)
				var normalizedThresholds = Array.isArray( data.thresholds ) ?
					data.thresholds.map( function( threshold ) {
						return {
							spendAmount: threshold.spendAmount || 0,
							discountValue: threshold.discountValue || 0,
							discountType: threshold.discountType || 'percentage'
						};
					} ) : [];

				// Determine mode from threshold data
				var mode = data.thresholdMode || 'percentage';
				stateUpdate.thresholdMode = mode;

				// Update mode-specific state property (not generic 'thresholds')
				if ( 'percentage' === mode ) {
					stateUpdate.percentageSpendThresholds = normalizedThresholds;
					this._percentageThresholds = normalizedThresholds;
					this._fixedThresholds = [];
				} else {
					stateUpdate.fixedSpendThresholds = normalizedThresholds;
					this._fixedThresholds = normalizedThresholds;
					this._percentageThresholds = [];
				}
				hasData = true;
			}

			if ( data.thresholdMode ) {
				stateUpdate.thresholdMode = data.thresholdMode;
			}

			// Only update state if we have data
			if ( hasData ) {
				this.state.setState( stateUpdate );

				// Re-render thresholds and check progression after loading
				this.renderThresholds();
				var self = this;
				setTimeout( function() {
					self.checkDiscountProgression();
				}, 100 );
			}
		},

		/**
		 * Collect spend threshold data
		 *
		 * Reads from the correct state property based on current mode.
		 */
		collectData: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';


			// Read from mode-specific state property
			var thresholdsKey = 'percentage' === thresholdMode ?
				'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];


			// Sort thresholds by threshold
			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				var aAmount = a.spendAmount || 0;
				var bAmount = b.spendAmount || 0;
				return aAmount - bAmount;
			} );


			var result = {
				discountType: 'spend_threshold',
				thresholdMode: thresholdMode,
				thresholds: sortedThresholds
			};

			return result;
		},

		/**
		 * Get summary text
		 */
		getSummary: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';
			var thresholdsKey = 'percentage' === thresholdMode ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];

			if ( 0 === thresholds.length ) {
				return 'No spend thresholds configured';
			}

			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				var aAmount = a.spendAmount || 0;
				var bAmount = b.spendAmount || 0;
				return aAmount - bAmount;
			} );

			var firstThreshold = sortedThresholds[0];
			var lastThreshold = sortedThresholds[sortedThresholds.length - 1];

			if ( 1 === sortedThresholds.length ) {
				var discount = 'percentage' === thresholdMode ?
					firstThreshold.discountValue + '%' :
					this.currencySymbol + firstThreshold.discountValue;
				return 'Spend ' + this.currencySymbol + firstThreshold.spendAmount + '+ → ' + discount + ' off';
			} else {
				return '' + ( sortedThresholds.length ) + ' spend thresholds: ' + this.currencySymbol + firstThreshold.spendAmount + ' to ' + this.currencySymbol + lastThreshold.spendAmount + '';
			}
		},

		/**
		 * Get preview configuration
		 */
		getPreviewConfig: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';
			var thresholdsKey = 'percentage' === thresholdMode ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var thresholds = fullState[thresholdsKey] || [];

			return {
				type: 'spend_threshold',
				thresholdMode: thresholdMode,
				thresholds: thresholds.map( function( threshold ) {
					return {
						spendAmount: threshold.spendAmount || 0,
						discountValue: threshold.discountValue || 0,
						discountType: thresholdMode
					};
				} )
			};
		},

		/**
		 * Handle state change
		 * @param change
		 */
		handleStateChange: function( change ) {
			// Re-render thresholds if threshold data changes
			if ( 'percentageSpendThresholds' === change.property ||
                 'fixedSpendThresholds' === change.property ||
                 'thresholdMode' === change.property ) {
				// Sync internal state with component state
				var fullState = this.state.getState();
				if ( 'percentageSpendThresholds' === change.property ) {
					this._percentageThresholds = fullState.percentageSpendThresholds || [];
				}
				if ( 'fixedSpendThresholds' === change.property ) {
					this._fixedThresholds = fullState.fixedSpendThresholds || [];
				}

				this.renderThresholds();
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
		 * Handler interface - Get percentage thresholds
		 * @returns {Array}
		 */
		getPercentageThresholds: function() {
			return this._percentageThresholds.slice(); // Return copy
		},

		/**
		 * Handler interface - Set percentage thresholds
		 * @param {Array} thresholds
		 */
		setPercentageThresholds: function( thresholds ) {
			if ( !this._ready ) {
				this._queuedPercentageThresholds = thresholds;
				return;
			}

			if ( Array.isArray( thresholds ) ) {
				this._percentageThresholds = thresholds.slice(); // Clone array
				this.state.setState( { percentageSpendThresholds: thresholds } );
				this.renderThresholds();
				this.updateInlinePreview();
			}
		},

		/**
		 * Handler interface - Get fixed thresholds
		 * @returns {Array}
		 */
		getFixedThresholds: function() {
			return this._fixedThresholds.slice(); // Return copy
		},

		/**
		 * Handler interface - Set fixed thresholds
		 * @param {Array} thresholds
		 */
		setFixedThresholds: function( thresholds ) {
			if ( !this._ready ) {
				this._queuedFixedThresholds = thresholds;
				return;
			}

			if ( Array.isArray( thresholds ) ) {
				this._fixedThresholds = thresholds.slice(); // Clone array
				this.state.setState( { fixedSpendThresholds: thresholds } );
				this.renderThresholds();
				this.updateInlinePreview();
			}
		},

		/**
		 * Get consolidated threshold data for backend (complex field handler)
		 * Combines percentage and fixed thresholds into single array with type property
		 *
		 * NOTE: Returns camelCase property names (JavaScript standard).
		 * AJAX Router automatically converts camelCase to snake_case for PHP backend.
		 *
		 * @return {Array} Consolidated thresholds array with camelCase properties (spendAmount, discountValue, discountType)
		 */
		getValue: function() {

			try {
				var thresholds = [];

				if ( this._percentageThresholds && this._percentageThresholds.length ) {
					this._percentageThresholds.forEach( function( threshold ) {
						var amount = parseFloat( threshold.spendAmount ) || 0;
						var discount = parseFloat( threshold.discountValue ) || 0;

						// Only include thresholds with valid amounts and discounts
						if ( amount > 0 && discount > 0 ) {
							var thresholdObj = {
								spendAmount: amount,
								discountValue: discount,
								discountType: 'percentage'
							};
							thresholds.push( thresholdObj );
						}
					} );
				}

				if ( this._fixedThresholds && this._fixedThresholds.length ) {
					this._fixedThresholds.forEach( function( threshold ) {
						var amount = parseFloat( threshold.spendAmount ) || 0;
						var discount = parseFloat( threshold.discountValue ) || 0;

						// Only include thresholds with valid amounts and discounts
						if ( amount > 0 && discount > 0 ) {
							var thresholdObj = {
								spendAmount: amount,
								discountValue: discount,
								discountType: 'fixed'
							};
							thresholds.push( thresholdObj );
						}
					} );
				}

				// Sort thresholds by spendAmount in ascending order
				thresholds.sort( function( a, b ) {
					return a.spendAmount - b.spendAmount;
				} );

				// Remove duplicates - check BOTH amount and type
				var seen = {};
				thresholds = thresholds.filter( function( threshold ) {
					var key = threshold.spendAmount + '_' + threshold.discountType;
					if ( seen[key] ) {
						return false;
					}
					seen[key] = true;
					return true;
				} );


				return thresholds;
			} catch ( error ) {
				console.error( '[SpendThreshold] getValue error:', error );
				return [];
			}
		},

		/**
		 * Set threshold data from backend (complex field handler)
		 * Splits consolidated array into percentage and fixed thresholds
		 *
		 * @param {Array} thresholds - Consolidated thresholds array from backend
		 */
		setValue: function( thresholds ) {
			try {
				this._percentageThresholds = [];
				this._fixedThresholds = [];

				thresholds.forEach( function( threshold ) {
					// Case converter handles snake_case → camelCase automatically
					var thresholdObj = {
						spendAmount: threshold.spendAmount || 0,
						discountValue: threshold.discountValue || 0,
						discountType: threshold.discountType || 'percentage'
					};

					if ( 'percentage' === threshold.discountType ) {
						this._percentageThresholds.push( thresholdObj );
					} else {
						this._fixedThresholds.push( thresholdObj );
					}
				}.bind( this ) );

				if ( this.state && 'function' === typeof this.state.setState ) {
					this.state.setState( {
						percentageSpendThresholds: this._percentageThresholds,
						fixedSpendThresholds: this._fixedThresholds
					} );
				}

				// Re-render UI
				if ( 'function' === typeof this.renderThresholds ) {
					this.renderThresholds();
				}

				if ( 'function' === typeof this.updateInlinePreview ) {
					this.updateInlinePreview();
				}
			} catch ( error ) {
				console.error( '[SpendThreshold] setValue error:', error );
			}
		},

		/**
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( '.spendthreshold' );

			WSSCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );
