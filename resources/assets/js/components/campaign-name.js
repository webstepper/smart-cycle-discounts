/**
 * Campaign Name Module
 *
 * Handles campaign name input, validation, and uniqueness checking
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Basic = SCD.Modules.Basic || {};

	/**
	 * Campaign Name Handler
	 *
	 * @param state
	 * @param api
	 * @class SCD.Modules.Basic.CampaignName
	 */
	SCD.Modules.Basic.CampaignName = function( state, api ) {
		this.state = state;
		this.api = api;
		this.nameCheckTimer = null;
		this.elements = {};
	};

	/**
	 * Initialize module
	 */
	SCD.Modules.Basic.CampaignName.prototype.init = function() {
		this.cacheElements();
		this.bindEvents();
		this.loadInitialValue();
	};

	/**
	 * Cache DOM elements
	 */
	SCD.Modules.Basic.CampaignName.prototype.cacheElements = function() {
		this.elements = {
			$input: $( '[name="name"]' ),
			$container: $( '[name="name"]' ).closest( '.scd-field-container' ),
			$campaignId: $( '#campaign_id' )
		};
	};

	/**
	 * Bind event handlers
	 */
	SCD.Modules.Basic.CampaignName.prototype.bindEvents = function() {
		var self = this;

		// Campaign name input
		this.elements.$input.on( 'input', function( _e ) {
			self.handleNameInput( $( this ) );
		} );

		// Listen to state changes
		$( document ).on( 'scd:basic:state:changed', function( e, data ) {
			if ( 'name' === data.property ) {
				self.updateUI( data.value );
			}
		} );
	};

	/**
	 * Load initial value
	 */
	SCD.Modules.Basic.CampaignName.prototype.loadInitialValue = function() {
		var currentName = this.state.getState().name;
		if ( currentName ) {
			this.elements.$input.val( currentName );
		}
	};

	/**
	 * Handle name input
	 * @param $input
	 */
	SCD.Modules.Basic.CampaignName.prototype.handleNameInput = function( $input ) {
		var value = $input.val().trim();

		// Update state
		this.state.setState( { name: value } );

		// Clear existing validation
		this.clearValidation();

		// Perform validation
		var validation = this.validateName( value );

		if ( !validation.valid ) {
			this.showError( validation.error );
			this.state.setState( {
				nameCheckStatus: 'error',
				nameCheckMessage: validation.error
			} );
			return;
		}

		// Check uniqueness if valid
		if ( 3 <= value.length ) {
			this.state.setState( { nameCheckStatus: 'checking' } );

			clearTimeout( this.nameCheckTimer );
			var self = this;
			this.nameCheckTimer = setTimeout( function() {
				self.checkNameUniqueness( value );
			}, 300 );
		}

		// Mark as valid
		$input.addClass( 'valid' );
	};

	/**
	 * Validate campaign name
	 * @param value
	 */
	SCD.Modules.Basic.CampaignName.prototype.validateName = function( value ) {
		// Check if empty
		if ( !value ) {
			return { valid: false, error: 'Campaign name is required' };
		}

		// Check minimum length
		if ( 3 > value.length ) {
			return { valid: false, error: 'Campaign name must be at least 3 characters' };
		}

		// Check for special characters ( allow ampersands )
		if ( !/^[a-zA-Z0-9\s\-_&]+$/.test( value ) ) {
			return { valid: false, error: 'Campaign name can only contain letters, numbers, spaces, hyphens, underscores, and ampersands' };
		}

		// Check for reserved words
		var reserved = [ 'admin', 'system', 'default', 'template', 'draft' ];
		if ( -1 !== reserved.indexOf( value.toLowerCase() ) ) {
			return { valid: false, error: '"' + value + '" is a reserved name. Please choose another.' };
		}

		// Check consecutive spaces
		if ( /\s{2,}/.test( value ) ) {
			return { valid: false, error: 'Campaign name should not contain consecutive spaces' };
		}

		return { valid: true };
	};

	/**
	 * Check name uniqueness via AJAX
	 */
	SCD.Modules.Basic.CampaignName.prototype.checkNameUniqueness = /* async */ function( name ) {
		var currentId = this.elements.$campaignId.val();

		try {
			var response = /* await */ this.api.checkCampaignName( name, currentId );

			if ( !response.success || !response.data.unique ) {
				this.state.setState( {
					nameCheckStatus: 'taken',
					nameCheckMessage: 'A campaign with this name already exists. Consider using a different name.'
				} );
				this.showWarning( 'A campaign with this name already exists. Consider using a different name.' );
			} else {
				this.state.setState( {
					nameCheckStatus: 'available',
					nameCheckMessage: ''
				} );
			}
		} catch ( error ) {
			// Silently fail - uniqueness check is not critical
			this.state.setState( {
				nameCheckStatus: 'error',
				nameCheckMessage: 'Could not verify name uniqueness'
			} );
		}
	};

	/**
	 * Update UI based on state
	 * @param value
	 */
	SCD.Modules.Basic.CampaignName.prototype.updateUI = function( value ) {
		if ( this.elements.$input.val() !== value ) {
			this.elements.$input.val( value );
		}
	};

	/**
	 * Clear validation messages
	 */
	SCD.Modules.Basic.CampaignName.prototype.clearValidation = function() {
		// Use centralized ValidationError component
		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.clear( this.elements.$input );
		}
		// Clear warnings (not handled by ValidationError)
		this.elements.$container.find( '.scd-field-warning' ).remove();
		this.elements.$input.removeClass( 'valid' );
	};

	/**
	 * Show error message
	 * @param message
	 */
	SCD.Modules.Basic.CampaignName.prototype.showError = function( message ) {
		this.clearValidation();
		// Use centralized ValidationError component
		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.show( this.elements.$input, message );
		}
	};

	/**
	 * Show warning message
	 * @param message
	 */
	SCD.Modules.Basic.CampaignName.prototype.showWarning = function( message ) {
		this.elements.$container.find( '.scd-field-warning' ).remove();
		// Create element safely with .text() to prevent XSS
		var $warning = $( '<div class="scd-uniqueness-hint scd-field-warning"></div>' ).text( message );
		this.elements.$container.append( $warning );
	};

	/**
	 * Validate module
	 */
	SCD.Modules.Basic.CampaignName.prototype.validate = /* async */ function() {
		var state = this.state.getState();
		var validation = this.validateName( state.name );

		if ( !validation.valid ) {
			return {
				valid: false,
				errors: [ {
					field: 'name',
					message: validation.error
				} ]
			};
		}

		// Check if uniqueness check is complete
		if ( 'checking' === state.nameCheckStatus ) {
			return {
				valid: false,
				errors: [ {
					field: 'name',
					message: 'Checking name availability...'
				} ]
			};
		}

		if ( 'taken' === state.nameCheckStatus ) {
			return {
				valid: false,
				errors: [ {
					field: 'name',
					message: state.nameCheckMessage
				} ]
			};
		}

		return { valid: true, errors: [] };
	};

	/**
	 * Destroy module
	 */
	SCD.Modules.Basic.CampaignName.prototype.destroy = function() {
		clearTimeout( this.nameCheckTimer );
		this.elements.$input.off( 'input' );
		$( document ).off( 'scd:basic:state:changed' );
	};

} )( jQuery );