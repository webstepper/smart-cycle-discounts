/**
 * Launch Options Module
 *
 * Handles campaign launch options ( active/draft )
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Review = SCD.Modules.Review || {};

	/**
	 * Launch Options Handler
	 *
	 * @param state
	 * @class SCD.Modules.Review.LaunchOptions
	 */
	SCD.Modules.Review.LaunchOptions = function( state ) {
		this.state = state;
		this.elements = {};
	};

	/**
	 * Initialize module
	 */
	SCD.Modules.Review.LaunchOptions.prototype.init = function() {
		this.cacheElements();
		this.bindEvents();
		this.loadInitialValue();
		this.updateNavigationButton();
	};

	/**
	 * Cache DOM elements
	 */
	SCD.Modules.Review.LaunchOptions.prototype.cacheElements = function() {
		this.elements = {
			$launchOptions: $( 'input[name="launch_option"]' ),
			$completeButton: $( '.scd-nav-btn--complete' ),
			$optionCards: $( '.scd-launch-option-card' ),
			$statusInfo: $( '.scd-launch-status-info' )
		};
	};

	/**
	 * Bind event handlers
	 */
	SCD.Modules.Review.LaunchOptions.prototype.bindEvents = function() {
		var self = this;

		// Launch option change
		this.elements.$launchOptions.on( 'change', function( _e ) {
			self.handleLaunchOptionChange( $( this ).val() );
		} );

		// Click on option cards
		this.elements.$optionCards.on( 'click', function( e ) {
			if ( !$( e.target ).is( 'input' ) ) {
				$( this ).find( 'input[type="radio"]' ).prop( 'checked', true ).trigger( 'change' );
			}
		} );

		// Listen to state changes
		$( document ).on( 'scd:review:state:changed', function( e, data ) {
			if ( 'launchOption' === data.property ) {
				self.updateUI( data.value );
			}
		} );
	};

	/**
	 * Load initial value
	 */
	SCD.Modules.Review.LaunchOptions.prototype.loadInitialValue = function() {
		// Check if there's a pre-selected value
		var checkedOption = this.elements.$launchOptions.filter( ':checked' ).val();
		if ( checkedOption ) {
			this.state.setState( { launchOption: checkedOption } );
		} else {
			// Set default and check the radio
			var defaultOption = 'draft';
			this.elements.$launchOptions.filter( '[value="' + defaultOption + '"]' ).prop( 'checked', true );
			this.state.setState( { launchOption: defaultOption } );
		}
	};

	/**
	 * Handle launch option change
	 * @param value
	 */
	SCD.Modules.Review.LaunchOptions.prototype.handleLaunchOptionChange = function( value ) {
		// Update state
		this.state.setState( { launchOption: value } );

		// Update UI
		this.updateOptionCards( value );
		this.updateStatusInfo( value );

		// Store in wizard data if available
		if ( window.SCD && window.SCD.Wizard ) {
			if ( 'function' === typeof window.SCD.Wizard.setState ) {
				window.SCD.Wizard.setState( { launchOption: value } );
			} else if ( window.SCD.Wizard.data ) {
				window.SCD.Wizard.data.launchOption = value;
			}
		}
	};

	/**
	 * Update navigation button text
	 */
	SCD.Modules.Review.LaunchOptions.prototype.updateNavigationButton = function() {
		var state = this.state.getState();
		var $button = this.elements.$completeButton;

		if ( $button.length ) {
			var $buttonText = $button.find( '.scd-nav-btn__text' );
			if ( $buttonText.length ) {
				$buttonText.text( state.buttonText );
			}

			// Update button style based on option
			$button.removeClass( 'scd-nav-btn--launch scd-nav-btn--save' );
			if ( 'active' === state.launchOption ) {
				$button.addClass( 'scd-nav-btn--launch' );
			} else {
				$button.addClass( 'scd-nav-btn--save' );
			}
		}
	};

	/**
	 * Update option cards visual state
	 * @param value
	 */
	SCD.Modules.Review.LaunchOptions.prototype.updateOptionCards = function( value ) {
		this.elements.$optionCards.removeClass( 'selected' );
		this.elements.$optionCards.filter( '[data-option="' + value + '"]' ).addClass( 'selected' );
	};

	/**
	 * Update status info message
	 * @param value
	 */
	SCD.Modules.Review.LaunchOptions.prototype.updateStatusInfo = function( value ) {
		if ( this.elements.$statusInfo.length ) {
			var message = '';
			if ( 'active' === value ) {
				message = 'Campaign will be activated immediately and start applying discounts to eligible customers.';
			} else {
				message = 'Campaign will be saved as a draft. You can activate it later from the campaigns list.';
			}
			this.elements.$statusInfo.html( '<span class="dashicons dashicons-info"></span> ' + message );
		}
	};

	/**
	 * Update UI based on state
	 * @param value
	 */
	SCD.Modules.Review.LaunchOptions.prototype.updateUI = function( value ) {
		// Update radio buttons
		this.elements.$launchOptions.filter( '[value="' + value + '"]' ).prop( 'checked', true );

		// Update visual elements
		this.updateOptionCards( value );
		this.updateNavigationButton();
		this.updateStatusInfo( value );
	};

	/**
	 * Get current value
	 */
	SCD.Modules.Review.LaunchOptions.prototype.getValue = function() {
		return this.state.getState().launchOption;
	};

	/**
	 * Destroy module
	 */
	SCD.Modules.Review.LaunchOptions.prototype.destroy = function() {
		this.elements.$launchOptions.off( 'change' );
		this.elements.$optionCards.off( 'click' );
		$( document ).off( 'scd:review:state:changed' );
	};

} )( jQuery );