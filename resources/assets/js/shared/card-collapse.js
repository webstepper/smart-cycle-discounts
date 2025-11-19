/**
 * Card Collapse
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/card-collapse.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Card collapse manager
	 */
	var CardCollapse = {

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.restoreStates();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Handle toggle button clicks
			$( document ).on( 'click', '.scd-card__toggle', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				CardCollapse.toggleCard( $( this ) );
			} );

			// Handle header clicks for collapsible cards
			$( document ).on( 'click', '.scd-card--collapsible .scd-card__header', function( e ) {
				// Don't toggle if clicking on actions or inside them
				if ( $( e.target ).closest( '.scd-card__actions' ).length > 0 ) {
					return;
				}

				e.preventDefault();
				var $toggle = $( this ).find( '.scd-card__toggle' );
				if ( $toggle.length ) {
					CardCollapse.toggleCard( $toggle );
				}
			} );

			// Handle keyboard accessibility (Enter/Space on toggle button)
			$( document ).on( 'keydown', '.scd-card__toggle', function( e ) {
				if ( 13 === e.which || 32 === e.which ) { // Enter or Space
					e.preventDefault();
					CardCollapse.toggleCard( $( this ) );
				}
			} );
		},

		/**
		 * Toggle card open/closed
		 *
		 * @param {jQuery} $toggle Toggle button element
		 */
		toggleCard: function( $toggle ) {
			var $card = $toggle.closest( '.scd-card' );
			var cardId = $card.attr( 'id' );
			var $content = $card.find( '.scd-card__content' );
			var $footer = $card.find( '.scd-card__footer' );
			var $icon = $toggle.find( '.scd-icon' );

			// Toggle collapsed state
			if ( $card.hasClass( 'scd-card--collapsed' ) ) {
				// Expand
				$card.removeClass( 'scd-card--collapsed' );
				$content.slideDown( 200 );
				$footer.slideDown( 200 );
				$toggle.attr( 'aria-expanded', 'true' );
				$toggle.attr( 'aria-label', $toggle.data( 'label-collapse' ) || 'Collapse card content' );

				this.saveState( cardId, 'open' );
			} else {
				// Collapse
				$card.addClass( 'scd-card--collapsed' );
				$content.slideUp( 200 );
				$footer.slideUp( 200 );
				$toggle.attr( 'aria-expanded', 'false' );
				$toggle.attr( 'aria-label', $toggle.data( 'label-expand' ) || 'Expand card content' );

				this.saveState( cardId, 'collapsed' );
			}
		},

		/**
		 * Restore card states from localStorage
		 */
		restoreStates: function() {
			var self = this;

			$( '.scd-card--collapsible' ).each( function() {
				var $card = $( this );
				var cardId = $card.attr( 'id' );

				// Skip cards without IDs (can't save state)
				if ( ! cardId ) {
					return;
				}

				var $toggle = $card.find( '.scd-card__toggle' );
				var savedState = self.getState( cardId );
				var defaultState = $card.data( 'default-state' ) || 'open';

				// Use saved state if available, otherwise use default
				var state = savedState || defaultState;

				if ( 'collapsed' === state && ! $card.hasClass( 'scd-card--collapsed' ) ) {
					// Apply collapsed state without animation on page load
					$card.addClass( 'scd-card--collapsed' );
					$card.find( '.scd-card__content' ).hide();
					$card.find( '.scd-card__footer' ).hide();
					$toggle.attr( 'aria-expanded', 'false' );
					$toggle.attr( 'aria-label', $toggle.data( 'label-expand' ) || 'Expand card content' );
				} else if ( 'open' === state && $card.hasClass( 'scd-card--collapsed' ) ) {
					// Ensure expanded state
					$card.removeClass( 'scd-card--collapsed' );
					$card.find( '.scd-card__content' ).show();
					$card.find( '.scd-card__footer' ).show();
					$toggle.attr( 'aria-expanded', 'true' );
					$toggle.attr( 'aria-label', $toggle.data( 'label-collapse' ) || 'Collapse card content' );
				}
			} );
		},

		/**
		 * Save card state to localStorage
		 *
		 * @param {string} cardId Card identifier
		 * @param {string} state State (open or collapsed)
		 */
		saveState: function( cardId, state ) {
			if ( ! cardId ) {
				return;
			}

			try {
				var key = 'scd_card_' + cardId;
				localStorage.setItem( key, state );
			} catch ( e ) {
				// localStorage not available or quota exceeded
				// Fail silently
			}
		},

		/**
		 * Get card state from localStorage
		 *
		 * @param {string} cardId Card identifier
		 * @return {string|null} Saved state or null
		 */
		getState: function( cardId ) {
			if ( ! cardId ) {
				return null;
			}

			try {
				var key = 'scd_card_' + cardId;
				return localStorage.getItem( key );
			} catch ( e ) {
				// localStorage not available
				return null;
			}
		},

		/**
		 * Clear all saved states (for debugging)
		 */
		clearAllStates: function() {
			try {
				var keys = [];
				for ( var i = 0; i < localStorage.length; i++ ) {
					var key = localStorage.key( i );
					if ( key && key.indexOf( 'scd_card_' ) === 0 ) {
						keys.push( key );
					}
				}

				for ( var j = 0; j < keys.length; j++ ) {
					localStorage.removeItem( keys[j] );
				}
			} catch ( e ) {
				// localStorage not available
			}
		}
	};

	/**
	 * Initialize on DOM ready
	 */
	$( document ).ready( function() {
		CardCollapse.init();
	} );

	/**
	 * Expose to global scope for debugging
	 */
	window.SCD = window.SCD || {};
	window.SCD.CardCollapse = CardCollapse;

} )( jQuery );
