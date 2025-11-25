/**
 * Row Factory - Dynamic Row Generation System
 *
 * Eliminates manual HTML string building by providing
 * a reusable factory for creating dynamic rows from configuration.
 *
 * @since 2.0.0
 * @package Smart_Cycle_Discounts
 */

( function( window, $, SCD ) {
	'use strict';

	if ( ! SCD || ! SCD.Shared ) {
		console.error( 'SCD.Shared is not defined. Row Factory cannot initialize.' );
		return;
	}

	/**
	 * Row Factory
	 *
	 * Creates dynamic rows from declarative configuration
	 *
	 * @example
	 * var config = {
	 *     rowClass: 'scd-tier-row',
	 *     fields: [
	 *         {
	 *             type: 'number',
	 *             name: 'min_quantity',
	 *             label: 'Min Quantity',
	 *             min: 1,
	 *             placeholder: 'Enter quantity'
	 *         },
	 *         {
	 *             type: 'number',
	 *             name: 'discount_value',
	 *             label: 'Discount Value',
	 *             min: 0,
	 *             prefix: '%',    // Badge-style prefix before input
	 *             suffix: 'off'   // Text suffix after input
	 *         }
	 *     ],
	 *     removeButton: {
	 *         enabled: true,
	 *         label: 'Remove',
	 *         class: 'scd-remove-tier'
	 *     }
	 * };
	 *
	 * var data = { min_quantity: 5, discount_value: 10 };
	 * var $row = SCD.Shared.RowFactory.create( config, data, 0 );
	 */
	SCD.Shared.RowFactory = {

		/**
		 * Create a row from configuration
		 *
		 * @param {Object} config Row configuration
		 * @param {Object} data Row data
		 * @param {number} index Row index
		 * @return {jQuery} Created row element
		 */
		create: function( config, data, index ) {
			var self = this;

			// Validate configuration
			if ( ! config || typeof config !== 'object' ) {
				SCD.ErrorHandler.handle(
					new Error( 'Row Factory: Invalid configuration provided' ),
					'RowFactory.create'
				);
				return $( '<div></div>' );
			}

			if ( ! config.fields || ! Array.isArray( config.fields ) ) {
				SCD.ErrorHandler.handle(
					new Error( 'Row Factory: Configuration must include fields array' ),
					'RowFactory.create'
				);
				return $( '<div></div>' );
			}

			// Default configuration
			var settings = $.extend( true, {
				rowClass: 'scd-dynamic-row',
				rowWrapper: 'div',
				fieldsWrapper: 'div',
				fieldsWrapperClass: 'scd-row-fields',
				fieldWrapper: 'div',
				fieldWrapperClass: 'scd-row-field',
				dataAttributes: {},
				removeButton: {
					enabled: false,
					label: 'Remove',
					class: 'scd-remove-row button',
					icon: 'delete'
				},
				dragHandle: false
			}, config );

			// Create row container
			var $row = $( '<' + settings.rowWrapper + '>' )
				.addClass( settings.rowClass )
				.attr( 'data-index', index );

			// Add custom data attributes
			if ( settings.dataAttributes && typeof settings.dataAttributes === 'object' ) {
				for ( var attr in settings.dataAttributes ) {
					if ( settings.dataAttributes.hasOwnProperty( attr ) ) {
						$row.attr( 'data-' + attr, settings.dataAttributes[attr] );
					}
				}
			}

			// Add drag handle if enabled
			if ( settings.dragHandle ) {
				var $dragHandle = $( '<span class="scd-drag-handle" draggable="true"></span>' )
					.append( SCD.IconHelper.get( 'menu', { size: 16 } ) );
				$row.append( $dragHandle );
			}

			// Create fields wrapper
			var $fieldsWrapper = $( '<' + settings.fieldsWrapper + '>' )
				.addClass( settings.fieldsWrapperClass );

			// Create fields
			for ( var i = 0; i < settings.fields.length; i++ ) {
				var fieldConfig = settings.fields[i];
				var fieldValue = data && data[fieldConfig.name] !== undefined ? data[fieldConfig.name] : '';

				var $field = self._createField( fieldConfig, fieldValue, index );
				$fieldsWrapper.append( $field );
			}

			// Add remove button if enabled - append to fields wrapper, not row
			if ( settings.removeButton && settings.removeButton.enabled ) {
				var $removeBtn = self._createRemoveButton( settings.removeButton );
				$fieldsWrapper.append( $removeBtn );
			}

			$row.append( $fieldsWrapper );

			return $row;
		},

		/**
		 * Create a field element
		 *
		 * @param {Object} fieldConfig Field configuration
		 * @param {*} value Field value
		 * @param {number} rowIndex Row index
		 * @return {jQuery} Created field element
		 */
		_createField: function( fieldConfig, value, rowIndex ) {
			var $wrapper = $( '<div class="' + ( fieldConfig.wrapperClass || 'scd-row-field' ) + '"></div>' );

			// Add label if provided
			if ( fieldConfig.label ) {
				var $label = $( '<label></label>' ).text( fieldConfig.label );

				// Add icon if provided
				if ( fieldConfig.icon ) {
					$label.prepend( SCD.IconHelper.get( fieldConfig.icon, { size: 16 } ) + ' ' );
				}

				// Add required indicator
				if ( fieldConfig.required ) {
					$label.append( ' <span class="required">*</span>' );
				}

				$wrapper.append( $label );
			}

			// Create input wrapper (for prefix/suffix support)
			var $inputWrapper = $( '<div class="scd-input-wrapper"></div>' );

			// Add prefix class if prefix is provided
			if ( fieldConfig.prefix ) {
				$inputWrapper.addClass( 'scd-input-with-prefix' );
			}

			// Add prefix if provided
			if ( fieldConfig.prefix ) {
				$inputWrapper.append(
					'<span class="scd-input-prefix">' + this._escapeHtml( fieldConfig.prefix ) + '</span>'
				);
			}

			// Create input element
			var $input = this._createInput( fieldConfig, value, rowIndex );
			$inputWrapper.append( $input );

			// Add suffix if provided
			if ( fieldConfig.suffix ) {
				$inputWrapper.append(
					'<span class="scd-field-suffix">' + this._escapeHtml( fieldConfig.suffix ) + '</span>'
				);
			}

			$wrapper.append( $inputWrapper );

			// Add description if provided
			if ( fieldConfig.description ) {
				$wrapper.append(
					'<p class="description">' + this._escapeHtml( fieldConfig.description ) + '</p>'
				);
			}

			return $wrapper;
		},

		/**
		 * Create input element
		 *
		 * @param {Object} fieldConfig Field configuration
		 * @param {*} value Field value
		 * @param {number} rowIndex Row index
		 * @return {jQuery} Input element
		 */
		_createInput: function( fieldConfig, value, rowIndex ) {
			var type = fieldConfig.type || 'text';
			var $input;

			// Create appropriate input type
			if ( type === 'textarea' ) {
				$input = $( '<textarea></textarea>' );
				$input.val( value );
			} else if ( type === 'select' && fieldConfig.options ) {
				$input = $( '<select></select>' );

				// Add options
				for ( var optValue in fieldConfig.options ) {
					if ( fieldConfig.options.hasOwnProperty( optValue ) ) {
						var $option = $( '<option></option>' )
							.attr( 'value', optValue )
							.text( fieldConfig.options[optValue] );

						if ( String( optValue ) === String( value ) ) {
							$option.attr( 'selected', 'selected' );
						}

						$input.append( $option );
					}
				}
			} else {
				$input = $( '<input>' )
					.attr( 'type', type );

				// Set value using both .val() and .attr() to ensure it persists in HTML
				if ( value !== '' && value !== null && value !== undefined ) {
					$input.val( value ).attr( 'value', value );
				}
			}

			// Set common attributes
			$input
				.addClass( fieldConfig.class || 'scd-row-input' )
				.attr( 'name', fieldConfig.name )
				.attr( 'data-field', fieldConfig.name )
				.attr( 'data-index', rowIndex );

			// Set optional attributes
			if ( fieldConfig.placeholder ) {
				$input.attr( 'placeholder', fieldConfig.placeholder );
			}

			if ( fieldConfig.min !== undefined ) {
				$input.attr( 'min', fieldConfig.min );
			}

			if ( fieldConfig.max !== undefined ) {
				$input.attr( 'max', fieldConfig.max );
			}

			if ( fieldConfig.step !== undefined ) {
				$input.attr( 'step', fieldConfig.step );
			}

			// Auto-add inputmode for number fields (mobile keyboard optimization)
			if ( type === 'number' ) {
				var inputmode = 'numeric';  // Default for integers
				var inputType = 'integer';  // Default input type

				// Determine if decimals are allowed based on step
				if ( fieldConfig.step !== undefined ) {
					var step = parseFloat( fieldConfig.step );
					if ( step !== Math.floor( step ) ) {
						inputmode = 'decimal';  // Allow decimal point on mobile
						inputType = 'decimal';
					}
				}

				// Check for percentage fields (0-100 range)
				if ( fieldConfig.max === 100 && ( fieldConfig.min === 0 || fieldConfig.min === 1 ) ) {
					inputType = 'percentage';
				}

				// Allow override via config
				if ( fieldConfig.inputmode ) {
					inputmode = fieldConfig.inputmode;
				}
				if ( fieldConfig.inputType ) {
					inputType = fieldConfig.inputType;
				}

				$input.attr( 'inputmode', inputmode );
				$input.attr( 'data-input-type', inputType );
			}

			if ( fieldConfig.required ) {
				$input.attr( 'required', 'required' )
					.attr( 'aria-required', 'true' );
			}

			if ( fieldConfig.readonly ) {
				$input.attr( 'readonly', 'readonly' );
			}

			if ( fieldConfig.disabled ) {
				$input.attr( 'disabled', 'disabled' );
			}

			// Add custom data attributes
			if ( fieldConfig.dataAttributes && typeof fieldConfig.dataAttributes === 'object' ) {
				for ( var attr in fieldConfig.dataAttributes ) {
					if ( fieldConfig.dataAttributes.hasOwnProperty( attr ) ) {
						$input.attr( 'data-' + attr, fieldConfig.dataAttributes[attr] );
					}
				}
			}

			return $input;
		},

		/**
		 * Create remove button
		 *
		 * @param {Object} buttonConfig Button configuration
		 * @return {jQuery} Button element wrapped in field container for alignment
		 */
		_createRemoveButton: function( buttonConfig ) {
			var $button = $( '<button type="button"></button>' )
				.addClass( buttonConfig.class )
				.attr( 'aria-label', buttonConfig.label );

			// Add icon if provided
			if ( buttonConfig.icon ) {
				$button.append( SCD.IconHelper.get( buttonConfig.icon, { size: 16 } ) + ' ' );
			}

			// Add label text
			if ( buttonConfig.showLabel !== false ) {
				$button.append( document.createTextNode( buttonConfig.label ) );
			}

			// Wrap button in field-like container for proper alignment with labeled fields
			var $wrapper = $( '<div class="scd-row-field scd-row-field--button"></div>' );

			// Add empty label spacer to match field structure (label + input)
			var $labelSpacer = $( '<label class="scd-field-label-spacer"></label>' )
				.html( '&nbsp;' )
				.css( 'visibility', 'hidden' );

			$wrapper.append( $labelSpacer );
			$wrapper.append( $button );

			return $wrapper;
		},

		/**
		 * Create multiple rows
		 *
		 * @param {Object} config Row configuration
		 * @param {Array} dataArray Array of row data objects
		 * @return {jQuery} Container with all rows
		 */
		createMultiple: function( config, dataArray ) {
			var $container = $( '<div class="scd-rows-container"></div>' );

			if ( ! dataArray || ! Array.isArray( dataArray ) ) {
				return $container;
			}

			for ( var i = 0; i < dataArray.length; i++ ) {
				var $row = this.create( config, dataArray[i], i );
				$container.append( $row );
			}

			return $container;
		},

		/**
		 * Collect data from rows
		 *
		 * @param {jQuery} $container Container with rows
		 * @param {string} rowSelector Row selector (default: '.scd-dynamic-row')
		 * @return {Array} Array of row data objects
		 */
		collectData: function( $container, rowSelector ) {
			rowSelector = rowSelector || '.scd-dynamic-row';
			var data = [];

			$container.find( rowSelector ).each( function( index ) {
				var $row = $( this );
				var rowData = {};

				// Collect all inputs in row
				$row.find( 'input, select, textarea' ).each( function() {
					var $input = $( this );
					var fieldName = $input.attr( 'data-field' ) || $input.attr( 'name' );

					if ( fieldName ) {
						var val = $input.val();

						// Convert number inputs to numbers
						if ( $input.attr( 'type' ) === 'number' ) {
							val = val === '' ? null : parseFloat( val );
						}

						// Handle checkboxes
						if ( $input.attr( 'type' ) === 'checkbox' ) {
							val = $input.is( ':checked' );
						}

						rowData[fieldName] = val;
					}
				} );

				// Add index
				rowData.index = index;

				data.push( rowData );
			} );

			return data;
		},

		/**
		 * Update row index
		 *
		 * Updates all index attributes in a row after reordering
		 *
		 * @param {jQuery} $row Row element
		 * @param {number} newIndex New index
		 * @return {void}
		 */
		updateIndex: function( $row, newIndex ) {
			$row.attr( 'data-index', newIndex );
			$row.find( '[data-index]' ).attr( 'data-index', newIndex );
		},

		/**
		 * Reindex all rows in container
		 *
		 * @param {jQuery} $container Container element
		 * @param {string} rowSelector Row selector
		 * @return {void}
		 */
		reindex: function( $container, rowSelector ) {
			var self = this;
			rowSelector = rowSelector || '.scd-dynamic-row';

			$container.find( rowSelector ).each( function( index ) {
				self.updateIndex( $( this ), index );
			} );
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		_escapeHtml: function( text ) {
			var div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Validate row configuration
		 *
		 * @param {Object} config Row configuration
		 * @return {Object} Validation result {valid: boolean, errors: array}
		 */
		validate: function( config ) {
			var errors = [];

			if ( ! config || typeof config !== 'object' ) {
				return {
					valid: false,
					errors: ['Configuration must be an object']
				};
			}

			// Must have fields array
			if ( ! config.fields || ! Array.isArray( config.fields ) ) {
				errors.push( 'Configuration must include fields array' );
			} else {
				// Validate each field
				for ( var i = 0; i < config.fields.length; i++ ) {
					var field = config.fields[i];

					if ( ! field.name ) {
						errors.push( 'Field at index ' + i + ' missing name' );
					}

					if ( field.type === 'select' && ! field.options ) {
						errors.push( 'Select field "' + field.name + '" missing options' );
					}
				}
			}

			return {
				valid: errors.length === 0,
				errors: errors
			};
		}

	};

	// Module loaded
	if ( window.SCD && window.SCD.DebugLogger ) {
		SCD.DebugLogger.log( 'info', 'RowFactory', 'Row Factory loaded' );
	}

} )( window, jQuery, window.SCD || {} );
