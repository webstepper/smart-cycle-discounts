/**
 * Spend Threshold
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/spend-threshold.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register spend threshold discount using utility with inheritance
	SCD.Utils.registerModule( 'SCD.Modules.Discounts.Types', 'SpendThreshold', function( state ) {
		// Call parent constructor
		SCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'spend_threshold';
		this.config = SCD.Modules.Discounts.Config;
		this.maxThresholds = 5;

		// Get currency symbol (simpler approach)
		this.currencySymbol = '$'; // Default
		if ( window.scdDiscountStepData && window.scdDiscountStepData.currencySymbol ) {
			this.currencySymbol = this.decodeHtmlEntity( window.scdDiscountStepData.currencySymbol );
		}

		// Ready state tracking (following advanced filters pattern)
		this._ready = false;
		this._queuedPercentageThresholds = null;
		this._queuedFixedThresholds = null;

		// Internal state for handler pattern
		this._percentageThresholds = [];
		this._fixedThresholds = [];

		// Initialize with dependency checks
		if ( !SCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'SpendThreshold' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	SCD.Modules.Discounts.Types.SpendThreshold.prototype = Object.create( SCD.Modules.Discounts.Types.BaseDiscount.prototype );
	SCD.Modules.Discounts.Types.SpendThreshold.prototype.constructor =
		SCD.Modules.Discounts.Types.SpendThreshold;

	// Override methods using utility extend
	SCD.Utils.extend( SCD.Modules.Discounts.Types.SpendThreshold.prototype, {
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
			SCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );
			var self = this;

			// Use requestAnimationFrame for optimal timing (following advanced filters pattern)
			requestAnimationFrame( function() {
				self.setupThresholdHandlers();

				// Process any queued data before marking as ready
				if ( null !== self._queuedPercentageThresholds ) {
					self.setPercentageThresholds( self._queuedPercentageThresholds );
					self._queuedPercentageThresholds = null;
				}

				if ( null !== self._queuedFixedThresholds ) {
					self.setFixedThresholds( self._queuedFixedThresholds );
					self._queuedFixedThresholds = null;
				}

				// Mark as ready
				self._ready = true;

				// Registration is handled by the type registry

				// Trigger ready event
				self.state.triggerChange( 'threshold:ready' );
			} );
		},

		/**
		 * Set default values for spend threshold
		 */
		setDefaults: function() {
			// Initialize internal state with defaults
			this._percentageThresholds = [
				{ threshold: 50, discount_value: 5, discount_type: 'percentage' },
				{ threshold: 100, discount_value: 10, discount_type: 'percentage' }
			];

			this._fixedThresholds = [
				{ threshold: 50, discount_value: 5, discount_type: 'fixed' },
				{ threshold: 100, discount_value: 10, discount_type: 'fixed' }
			];

			// Update state from internal values
			this.state.setState( {
				thresholdMode: 'percentage',
				percentageSpendThresholds: this._percentageThresholds,
				fixedSpendThresholds: this._fixedThresholds
			} );

			// Show preview and render thresholds
			this.renderThresholds();
			this.updateInlinePreview();
		},

		/**
		 * Clear spend threshold data
		 */
		clearData: function() {
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
			$( '.scd-strategy-spend_threshold' ).addClass( 'active' );

			// Sync state with internal data
			var fullState = this.state.getState();
			this._percentageThresholds = fullState.percentageSpendThresholds || this._percentageThresholds;
			this._fixedThresholds = fullState.fixedSpendThresholds || this._fixedThresholds;

			// Always render thresholds for the PHP template
			this.renderThresholds();
			// Trigger initial preview update
			this.updateInlinePreview();

			// Check progression on initial display
			this.checkDiscountProgression();
		},

		/**
		 * Hide spend threshold UI
		 */
		hideUI: function() {
			$( '.scd-strategy-spend_threshold' ).removeClass( 'active' );
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
			$( document ).on( 'change.spendthreshold', '.scd-strategy-spend_threshold [name="threshold_mode"]', function() {
				var mode = $( this ).val();
				self.state.setState( { thresholdMode: mode } );

				// Show/hide appropriate threshold groups
				if ( 'percentage' === mode ) {
					$( '#percentage-thresholds-group' ).show();
					$( '#fixed-thresholds-group' ).hide();
				} else {
					$( '#percentage-thresholds-group' ).hide();
					$( '#fixed-thresholds-group' ).show();
				}

				self.renderThresholds();
				self.updateInlinePreview();
			} );

			// Add threshold button
			$( document ).on( 'click.spendthreshold', '.scd-strategy-spend_threshold .scd-add-threshold', function( e ) {
				e.preventDefault();
				var thresholdType = $( this ).data( 'threshold-type' );
				self.addThreshold( thresholdType );
			} );

			// Remove threshold button
			$( document ).on( 'click.spendthreshold', '.scd-strategy-spend_threshold .scd-remove-threshold', function( e ) {
				e.preventDefault();
				var $row = $( this ).closest( '.scd-threshold-row' );
				var index = $row.data( 'index' );
				var thresholdType = $row.data( 'threshold-type' );
				self.removeThreshold( index, thresholdType );
			} );

			// Handle threshold input changes
			$( document ).on( 'change.spendthreshold input.spendthreshold', '.scd-strategy-spend_threshold .scd-threshold-input', function() {
				var $input = $( this );
				var $row = $input.closest( '.scd-threshold-row' );
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
			// Get internal thresholds array
			var thresholds = 'percentage' === thresholdType ? this._percentageThresholds : this._fixedThresholds;

			if ( thresholds.length >= this.maxThresholds ) {
				if ( SCD.Shared && SCD.Shared.NotificationService ) {
					SCD.Shared.NotificationService.warning(
						'Maximum ' + this.maxThresholds + ' thresholds allowed'
					);
				}
				return;
			}

			// Calculate smart default values
			var newThreshold = this.calculateSmartProgression( thresholds, thresholdType );

			// Update internal state
			thresholds.push( newThreshold );

			// Update component state
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
				// First threshold defaults
				return {
					threshold: 50,
					discount_value: 5,
					discount_type: type
				};
			}

			var lastAmount = lastThreshold.threshold || 0;
			var lastDiscount = lastThreshold.discount_value || 0;

			// Simple progression: double the amount, increase discount
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
				threshold: nextAmount,
				discount_value: nextDiscount,
				discount_type: type
			};
		},

		/**
		 * Remove a threshold
		 * @param index
		 * @param thresholdType
		 */
		removeThreshold: function( index, thresholdType ) {
			// Get internal thresholds array
			var thresholds = 'percentage' === thresholdType ? this._percentageThresholds : this._fixedThresholds;

			if ( thresholds[index] ) {
				// Update internal state
				thresholds.splice( index, 1 );

				// Update component state
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
			// Get internal thresholds array
			var thresholds = 'percentage' === thresholdType ? this._percentageThresholds : this._fixedThresholds;

			if ( !thresholds[index] ) {
				return;
			}

			// Update the field - support both old and new field names
			switch( field ) {
				case 'threshold':
				case 'amount':
					thresholds[index].threshold = parseFloat( value ) || 0;
					break;
				case 'discount_value':
				case 'discount':
					thresholds[index].discount_value = parseFloat( value ) || 0;
					break;
			}

			// Update component state
			var thresholdsKey = 'percentage' === thresholdType ? 'percentageSpendThresholds' : 'fixedSpendThresholds';
			var updatedState = {};
			updatedState[thresholdsKey] = thresholds.slice(); // Clone array
			this.state.setState( updatedState );

			this.updateInlinePreview();
			this.checkDiscountProgression();
		},

		/**
		 * Render thresholds UI
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
				return ( a.threshold || 0 ) - ( b.threshold || 0 );
			} );

			// Build HTML using WordPress admin patterns
			sortedThresholds.forEach( function( threshold, _sortedIndex ) {
				// Find original index
				var originalIndex = thresholds.indexOf( threshold );

				html += '<div class="scd-threshold-row" data-index="' + originalIndex + '" data-threshold-type="' + thresholdMode + '">';
				html += '  <div class="scd-threshold-fields">';

				// Spend amount field
				html += '    <div class="scd-field-group">';
				html += '      <label>Spend Amount:</label>';
				html += '      <div class="scd-input-with-prefix">';
				html += '        <span class="scd-input-prefix">' + self.currencySymbol + '</span>';
				html += '        <input type="number" class="scd-threshold-input" data-field="threshold" ';
				html += '               name="' + thresholdMode + '_spend_thresholds[' + originalIndex + '][threshold]" ';
				html += '               value="' + ( threshold.threshold || '' ) + '" min="0.01" step="0.01" placeholder="100.00">';
				html += '      </div>';
				html += '    </div>';

				// Discount value field
				html += '    <div class="scd-field-group">';
				html += '      <label>Discount Value:</label>';
				html += '      <div class="scd-input-with-prefix">';

				if ( 'percentage' === thresholdMode ) {
					html += '        <span class="scd-input-prefix">%</span>';
					html += '        <input type="number" class="scd-threshold-input" data-field="discount_value" ';
					html += '               name="' + thresholdMode + '_spend_thresholds[' + originalIndex + '][discount_value]" ';
					html += '               value="' + ( threshold.discount_value || '' ) + '" min="0.01" max="100" step="0.01" placeholder="10">';
				} else {
					html += '        <span class="scd-input-prefix">' + self.currencySymbol + '</span>';
					html += '        <input type="number" class="scd-threshold-input" data-field="discount_value" ';
					html += '               name="' + thresholdMode + '_spend_thresholds[' + originalIndex + '][discount_value]" ';
					html += '               value="' + ( threshold.discount_value || '' ) + '" min="0.01" step="0.01" placeholder="5.00">';
				}

				html += '      </div>';
				html += '    </div>';

				// Remove button
				html += '    <button type="button" class="scd-remove-threshold">Remove</button>';

				html += '  </div>';
				html += '</div>';
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
				return ( a.threshold || 0 ) - ( b.threshold || 0 );
			} );

			var previewText = '';
			var self = this;

			sortedThresholds.forEach( function( threshold, index ) {
				if ( 0 < index ) {previewText += ', ';}

				previewText += 'Spend ' + self.currencySymbol + threshold.threshold + ' or more';

				if ( 'percentage' === thresholdMode ) {
					previewText += ' → ' + threshold.discount_value + '% off';
				} else {
					previewText += ' → ' + self.currencySymbol + threshold.discount_value + ' off';
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

			// Remove any existing warnings
			$( '.scd-threshold-warning' ).remove();

			if ( 2 > thresholds.length ) {return;}

			// Sort thresholds by threshold to check in order
			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				var aAmount = a.threshold || 0;
				var bAmount = b.threshold || 0;
				return aAmount - bAmount;
			} );

			// Check if discounts increase with thresholds
			var hasIssue = false;
			for ( var i = 1; i < sortedThresholds.length; i++ ) {
				var prevDiscount = sortedThresholds[i-1].discount_value || 0;
				var currDiscount = sortedThresholds[i].discount_value || 0;

				if ( currDiscount <= prevDiscount ) {
					hasIssue = true;
					break;
				}
			}

			// Show warning if there's an issue
			if ( hasIssue ) {
				var warningHtml = '<div class="scd-tier-warning scd-threshold-warning">';
				warningHtml += '<span class="dashicons dashicons-warning"></span>';
				warningHtml += '<span class="warning-text">Tip: Higher spending thresholds usually have bigger discounts.</span>';
				warningHtml += '</div>';

				// Add warning to the active threshold list
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

			// Check if at least one threshold exists
			if ( 0 === thresholds.length ) {
				errors.thresholds = [ 'At least one threshold is required' ];
			}

			// Validate each threshold
			var amountsSeen = {};

			thresholds.forEach( function( threshold, index ) {
				// Check threshold amount
				if ( !threshold.threshold || 0 >= threshold.threshold ) {
					errors['threshold_' + index + '_threshold'] = [ 'Spend amount must be greater than 0' ];
				} else if ( amountsSeen[threshold.threshold] ) {
					warnings['threshold_' + index + '_threshold'] = [ 'Duplicate spend amount' ];
				} else {
					amountsSeen[threshold.threshold] = true;
				}

				// Check discount value
				if ( !threshold.discount_value || 0 >= threshold.discount_value ) {
					errors['threshold_' + index + '_discount_value'] = [ 'Discount must be greater than 0' ];
				}

				if ( 'percentage' === thresholdMode && 100 < threshold.discount_value ) {
					errors['threshold_' + index + '_discount_value'] = [ 'Percentage cannot exceed 100%' ];
				}
			} );

			return {
				isValid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};
		},

		/**
		 * Get configuration data
		 */
		getData: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';

			return {
				thresholdMode: thresholdMode,
				thresholds: fullState.thresholds || []
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
				// Normalize threshold objects
				var normalizedThresholds = Array.isArray( data.thresholds ) ?
					data.thresholds.map( function( threshold ) {
						return {
							threshold: threshold.threshold || 0,
							discount_value: threshold.discountValue || 0,
							discount_type: threshold.discountType || 'percentage'
						};
					} ) : [];

				// Determine mode from threshold data or explicit mode
				var mode = data.thresholdMode || 'percentage';
				stateUpdate.thresholdMode = mode;

				// Store in combined format in state
				stateUpdate.thresholds = normalizedThresholds;

				// Update internal arrays for UI (still needed for rendering logic)
				if ( 'percentage' === mode ) {
					this._percentageThresholds = normalizedThresholds;
					this._fixedThresholds = [];
				} else {
					this._fixedThresholds = normalizedThresholds;
					this._percentageThresholds = [];
				}
				hasData = true;
			}

			// Set threshold mode from data if provided
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
		 */
		collectData: function() {
			var fullState = this.state.getState();
			var thresholdMode = fullState.thresholdMode || 'percentage';

			// Get thresholds from state (single source of truth)
			var thresholds = fullState.thresholds || [];

			// Sort thresholds by threshold
			var sortedThresholds = thresholds.slice().sort( function( a, b ) {
				var aAmount = a.threshold || 0;
				var bAmount = b.threshold || 0;
				return aAmount - bAmount;
			} );

			return {
				discountType: 'spend_threshold',
				thresholdMode: thresholdMode,
				thresholds: sortedThresholds
			};
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
				var aAmount = a.threshold || 0;
				var bAmount = b.threshold || 0;
				return aAmount - bAmount;
			} );

			var firstThreshold = sortedThresholds[0];
			var lastThreshold = sortedThresholds[sortedThresholds.length - 1];

			if ( 1 === sortedThresholds.length ) {
				var discount = 'percentage' === thresholdMode ?
					firstThreshold.discount_value + '%' :
					this.currencySymbol + firstThreshold.discount_value;
				return 'Spend ' + this.currencySymbol + firstThreshold.threshold + '+ → ' + discount + ' off';
			} else {
				return '' + ( sortedThresholds.length ) + ' spend thresholds: ' + this.currencySymbol + firstThreshold.threshold + ' to ' + this.currencySymbol + lastThreshold.threshold + '';
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
						threshold: threshold.threshold || 0,
						discount_value: threshold.discount_value || 0,
						discount_type: thresholdMode
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
		 * @return {Array} Consolidated thresholds array
		 */
		getValue: function() {
			try {
				var thresholds = [];

				// Add percentage thresholds
				if ( this._percentageThresholds && this._percentageThresholds.length ) {
					this._percentageThresholds.forEach( function( threshold ) {
						var amount = parseFloat( threshold.threshold ) || 0;
						var discount = parseFloat( threshold.discount_value ) || 0;

						// Only include thresholds with valid amounts and discounts
						if ( amount > 0 && discount > 0 ) {
							thresholds.push( {
								threshold: amount,
								discount_value: discount,
								discount_type: 'percentage'
							} );
						}
					} );
				}

				// Add fixed thresholds
				if ( this._fixedThresholds && this._fixedThresholds.length ) {
					this._fixedThresholds.forEach( function( threshold ) {
						var amount = parseFloat( threshold.threshold ) || 0;
						var discount = parseFloat( threshold.discount_value ) || 0;

						// Only include thresholds with valid amounts and discounts
						if ( amount > 0 && discount > 0 ) {
							thresholds.push( {
								threshold: amount,
								discount_value: discount,
								discount_type: 'fixed'
							} );
						}
					} );
				}

				// Sort thresholds by threshold in ascending order
				thresholds.sort( function( a, b ) {
					return a.threshold - b.threshold;
				} );

				// Remove duplicates with same threshold (keep first occurrence)
				var seen = {};
				thresholds = thresholds.filter( function( threshold ) {
					if ( seen[threshold.threshold] ) {
						return false;
					}
					seen[threshold.threshold] = true;
					return true;
				} );

				if ( window.scdDebugDiscounts ) {
				}

				return thresholds;
			} catch ( error ) {
				console.error( '[SpendThreshold] getValue error:', error );
				return [];
			}
		},

		/**
		 * Set threshold data from backend (complex field handler)
		 * Splits consolidated array into percentage and fixed thresholds
		 * @param {Array} thresholds - Consolidated thresholds array from backend
		 */
		setValue: function( thresholds ) {
			try {
				if ( !thresholds || !Array.isArray( thresholds ) ) {
					if ( window.scdDebugDiscounts ) {
					}
					return;
				}

				if ( window.scdDebugDiscounts ) {
				}

				// Split into percentage and fixed arrays
				this._percentageThresholds = [];
				this._fixedThresholds = [];

				thresholds.forEach( function( threshold ) {
					var thresholdObj = {
						threshold: threshold.threshold,
						discount_value: threshold.discount_value,
						discount_type: threshold.discount_type
					};

					if ( 'percentage' === threshold.discount_type ) {
						this._percentageThresholds.push( thresholdObj );
					} else {
						this._fixedThresholds.push( thresholdObj );
					}
				}.bind( this ) );

				// Update state
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

				// Update preview
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

			SCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );