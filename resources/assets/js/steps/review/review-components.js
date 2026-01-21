/**
 * Review Components
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/review/review-components.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Review = WSSCD.Modules.Review || {};

	/**
	 * Review Components Handler
	 *
	 * @param state
	 * @param api
	 * @class WSSCD.Modules.Review.Components
	 */
	WSSCD.Modules.Review.Components = function( state, api ) {
		this.state = state;
		this.api = api;
		this.elements = {};
		this.editHandlers = {};
		this.collapsibleStates = {};
	};

	/**
	 * Initialize components
	 */
	WSSCD.Modules.Review.Components.prototype.init = function() {
		this.cacheElements();
		this.bindEvents();
		this.initializeLaunchOptions();
		this.initializeCollapsibles();
		this.loadSummaryData();
		this.updateLaunchInfoText();
	};

	/**
	 * Cache DOM elements
	 */
	WSSCD.Modules.Review.Components.prototype.cacheElements = function() {
		this.elements = {
			// Summary elements
			$summaryContainer: $( '.wsscd-wizard-step--review' ),
			$summaryCards: $( '.wsscd-wizard-card' ),
			$summaryGrids: $( '.wsscd-summary-grid' ),
			$editButtons: $( '[data-edit-step]' ),
			$collapsibleCards: $( '.wsscd-wizard-card[data-collapsible="true"]' ),

			// Launch options elements
			$launchOptions: $( 'input[name="launch_option"]' ),
			$launchOptionLabels: $( '.wsscd-launch-option' ),
			$completeButton: $( '.wsscd-nav-btn--complete' ),
			$launchInfoText: $( '.wsscd-launch-info-text' ),

			// Preview elements
			$previewBox: $( '.wsscd-preview-box' ),
			$previewBadge: $( '.wsscd-preview-badge' )
		};
	};

	/**
	 * Bind event handlers
	 */
	WSSCD.Modules.Review.Components.prototype.bindEvents = function() {
		var self = this;

		// Edit button events
		this.elements.$editButtons.on( 'click', function( e ) {
			e.preventDefault();
			self.handleEditClick( $( this ) );
		} );

		// Collapsible card events
		this.elements.$collapsibleCards.on( 'click', '.wsscd-card__header', function( e ) {
			if ( $( e.target ).closest( '[data-edit-step]' ).length ) {
				return; // Don't collapse if clicking edit button
			}
			e.preventDefault();
			self.handleCardToggle( $( this ).closest( '.wsscd-wizard-card' ) );
		} );

		// Launch option events
		this.elements.$launchOptions.on( 'change', function() {
			self.handleLaunchOptionChange( $( this ).val() );
		} );

		this.elements.$launchOptionLabels.on( 'click', function( e ) {
			if ( 'INPUT' !== e.target.tagName ) {
				var $radio = $( this ).find( 'input[type="radio"]' );
				if ( $radio.length && !$radio.prop( 'checked' ) ) {
					$radio.prop( 'checked', true ).trigger( 'change' );
				}
			}
		} );

		// Summary is server-rendered, no need to listen for wizard data changes
	};

	/* ===== COLLAPSIBLE CARDS METHODS ===== */

	/**
	 * Initialize collapsible cards
	 */
	WSSCD.Modules.Review.Components.prototype.initializeCollapsibles = function() {
		var self = this;

		this.elements.$collapsibleCards.each( function() {
			var $card = $( this );
			var $header = $card.find( '.wsscd-card__header' );

			if ( !$header.find( '.wsscd-card-toggle' ).length ) {
				var arrowIcon = WSSCD.IconHelper ? WSSCD.IconHelper.get( 'arrow-down', { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-arrow-down"></span>';
				$header.append( '<span class="wsscd-card-toggle">' + arrowIcon + '</span>' );
			}

			self.collapsibleStates[$card.attr( 'id' )] = false;
		} );
	};

	/**
	 * Handle card toggle
	 * @param $card
	 */
	WSSCD.Modules.Review.Components.prototype.handleCardToggle = function( $card ) {
		var cardId = $card.attr( 'id' );
		var isCollapsed = this.collapsibleStates[cardId] || false;

		if ( isCollapsed ) {
			this.expandCard( $card );
		} else {
			this.collapseCard( $card );
		}

		this.collapsibleStates[cardId] = !isCollapsed;
	};

	/**
	 * Collapse card
	 * @param $card
	 */
	WSSCD.Modules.Review.Components.prototype.collapseCard = function( $card ) {
		var $content = $card.find( '.wsscd-card__content' );
		var $toggle = $card.find( '.wsscd-card-toggle' );

		$content.slideUp( 200 );
		$card.addClass( 'wsscd-card-collapsed' );
		var arrowRightIcon = WSSCD.IconHelper ? WSSCD.IconHelper.get( 'arrow-right', { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-arrow-right"></span>';
		$toggle.html( arrowRightIcon );
	};

	/**
	 * Expand card
	 * @param $card
	 */
	WSSCD.Modules.Review.Components.prototype.expandCard = function( $card ) {
		var $content = $card.find( '.wsscd-card__content' );
		var $toggle = $card.find( '.wsscd-card-toggle' );

		$content.slideDown( 200 );
		$card.removeClass( 'wsscd-card-collapsed' );
		var arrowDownIcon = WSSCD.IconHelper ? WSSCD.IconHelper.get( 'arrow-down', { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-arrow-down"></span>';
		$toggle.html( arrowDownIcon );
	};

	/* ===== LAUNCH OPTIONS METHODS ===== */

	/**
	 * Initialize launch options with default value
	 */
	WSSCD.Modules.Review.Components.prototype.initializeLaunchOptions = function() {
		var currentOption = this.elements.$launchOptions.filter( ':checked' ).val() || 'active';
		this.state.setState( { launchOption: currentOption } );
		this.updateNavigationButton();
		this.updateLaunchOptionStyles( currentOption );
	};

	/**
	 * Handle launch option change
	 * @param value
	 */
	WSSCD.Modules.Review.Components.prototype.handleLaunchOptionChange = function( value ) {
		this.state.setState( { launchOption: value } );

		// Update UI
		this.updateLaunchOptionStyles( value );
		this.updateLaunchInfoText();
		this.updateNavigationButton();
	};

	/**
	 * Update launch option styles
	 * @param selectedValue
	 */
	WSSCD.Modules.Review.Components.prototype.updateLaunchOptionStyles = function( selectedValue ) {
		this.elements.$launchOptionLabels.removeClass( 'selected' );
		this.elements.$launchOptionLabels.filter( '.wsscd-launch-option--' + selectedValue ).addClass( 'selected' );
	};

	/**
	 * Update launch info text
	 */
	WSSCD.Modules.Review.Components.prototype.updateLaunchInfoText = function() {
		var option = this.state.getState().launchOption;

		if ( this.elements.$launchInfoText.length ) {
			var activeText = this.elements.$launchInfoText.data( 'active' );
			var draftText = this.elements.$launchInfoText.data( 'draft' );

			this.elements.$launchInfoText.fadeOut( 150, function() {
				$( this ).text( 'draft' === option ? draftText : activeText ).fadeIn( 150 );
			} );
		}
	};

	/**
	 * Update navigation button text
	 */
	WSSCD.Modules.Review.Components.prototype.updateNavigationButton = function() {
		var option = this.state.getState().launchOption;

		// SINGLE SOURCE OF TRUTH: Wizard State Manager
		var isEditMode = this.isEditMode();

		// Button text based on mode and launch option
		var buttonText;
		if ( isEditMode ) {
			// Editing existing campaign
			buttonText = 'draft' === option ? 'Update Draft' : 'Update Campaign';
		} else {
			// Creating new campaign
			buttonText = 'draft' === option ? 'Save as Draft' : 'Launch Campaign';
		}

		if ( this.elements.$completeButton.length ) {
			// Try both selector formats for compatibility
			var $buttonText = this.elements.$completeButton.find( '.wsscd-btn-text' );
			if ( ! $buttonText.length ) {
				$buttonText = this.elements.$completeButton.find( '.wsscd-nav-btn__text' );
			}
			$buttonText.text( buttonText );

			// Also update the data attribute for the draft flag
			if ( 'draft' === option ) {
				this.elements.$completeButton.attr( 'data-save-as-draft', 'true' );
			} else {
				this.elements.$completeButton.removeAttr( 'data-save-as-draft' );
			}
		}
	};

	/**
	 * Check if in edit mode (SINGLE SOURCE OF TRUTH)
	 *
	 * @return {boolean} True if editing existing campaign
	 */
	WSSCD.Modules.Review.Components.prototype.isEditMode = function() {
		if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.StateManager ) {
			var wizardState = window.WSSCD.Wizard.StateManager.getInstance().get();
			return wizardState && ( wizardState.wizardMode === 'edit' || wizardState.campaignId > 0 );
		}
		return false;
	};

	/* ===== SUMMARY METHODS ===== */

	/**
	 * Load summary data
	 */
	WSSCD.Modules.Review.Components.prototype.loadSummaryData = function() {
		// Summary is pre-rendered on server, just initialize any dynamic features
		this.initializeSummaryFeatures();
	};

	/**
	 * Initialize summary features
	 */
	WSSCD.Modules.Review.Components.prototype.initializeSummaryFeatures = function() {
		this.elements.$summaryGrids.find( '.wsscd-summary-row' )
			.on( 'mouseenter', function() { $( this ).addClass( 'hover' ); } )
			.on( 'mouseleave', function() { $( this ).removeClass( 'hover' ); } );

		if ( $.fn.tooltip ) {
			this.elements.$summaryContainer.find( '[data-tooltip]' ).tooltip();
		}
	};

	/**
	 * Handle edit button click
	 * @param $button
	 */
	WSSCD.Modules.Review.Components.prototype.handleEditClick = function( $button ) {
		var step = $button.data( 'edit-step' );

		if ( step ) {
			// Save current state before navigating
			if ( this.api && this.api.saveStepData ) {
				this.api.saveStepData( 'review', this.getStepData() );
			}

			// Try multiple navigation methods for compatibility
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation && window.WSSCD.Wizard.Navigation.navigateToStep ) {
				window.WSSCD.Wizard.Navigation.navigateToStep( step );
			} else if ( window.wsscdWizardNavigation && 'function' === typeof window.wsscdWizardNavigation.navigateToStep ) {
				window.wsscdWizardNavigation.navigateToStep( step );
			} else {
				// Fallback: trigger navigation event
				$( document ).trigger( 'wsscd:wizard:navigate', { step: step, from: 'review' } );
			}
		}
	};

	/* ===== UTILITY METHODS ===== */

	/**
	 * Get current step data
	 */
	WSSCD.Modules.Review.Components.prototype.getStepData = function() {
		return {
			launchOption: this.state.getState().launchOption
		};
	};

	/**
	 * Validate components
	 */
	WSSCD.Modules.Review.Components.prototype.validate = function() {
		var launchOption = this.state.getState().launchOption;

		if ( !launchOption ) {
			return {
				valid: false,
				errors: [ {
					field: 'launch_option',
					message: 'Please select a launch option'
				} ]
			};
		}

		return {
			valid: true,
			errors: []
		};
	};

	/**
	 * Show validation errors
	 * @param {Array} errors - Array of error objects with field and message
	 */
	WSSCD.Modules.Review.Components.prototype.showErrors = function( errors ) {
		if ( !errors || !Array.isArray( errors ) ) {
			return;
		}

		if ( window.WSSCD && window.WSSCD.ValidationError ) {
			window.WSSCD.ValidationError.clearAll( this.elements.$launchOptions.closest( 'form' ) );
		}

		for ( var i = 0; i < errors.length; i++ ) {
			var error = errors[i];
			if ( 'launch_option' === error.field && this.elements.$launchOptions && this.elements.$launchOptions.length ) {
				// Use ValidationError component for consistent error display
				if ( window.WSSCD && window.WSSCD.ValidationError ) {
					window.WSSCD.ValidationError.show( this.elements.$launchOptions.first(), error.message );
				}
			}
		}
	};

	/**
	 * Reset components
	 */
	WSSCD.Modules.Review.Components.prototype.reset = function() {
		if ( this.elements.$launchOptions ) {
			this.elements.$launchOptions.filter( '[value="active"]' ).prop( 'checked', true ).trigger( 'change' );
		}

		// Expand all collapsed cards
		var self = this;
		this.elements.$collapsibleCards.each( function() {
			var $card = $( this );
			if ( $card.hasClass( 'wsscd-card-collapsed' ) ) {
				self.expandCard( $card );
			}
		} );

		$( '.wsscd-launch-option-error' ).remove();
		$( '.wsscd-field-error' ).removeClass( 'wsscd-field-error' );
	};

	/**
	 * Destroy handler
	 */
	WSSCD.Modules.Review.Components.prototype.destroy = function() {
		// Unbind events
		if ( this.elements.$editButtons ) {
			this.elements.$editButtons.off( 'click' );
		}
		if ( this.elements.$collapsibleCards ) {
			this.elements.$collapsibleCards.off( 'click', '.wsscd-card__header' );
		}
		if ( this.elements.$launchOptions ) {
			this.elements.$launchOptions.off( 'change' );
		}
		if ( this.elements.$launchOptionLabels ) {
			this.elements.$launchOptionLabels.off( 'click' );
		}

		this.elements = {};
		this.state = null;
		this.api = null;
	};

	WSSCD.Modules.Review.Summary = function( state, api ) {
		return new WSSCD.Modules.Review.Components( state, api );
	};

	WSSCD.Modules.Review.LaunchOptions = function( state ) {
		return new WSSCD.Modules.Review.Components( state, null );
	};

} )( jQuery );