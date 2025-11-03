/**
 * Sidebar Collapse
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/sidebar-collapse.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Sidebar collapse manager
	 */
	var SidebarCollapse = {

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
			// Handle header clicks (entire header is clickable)
			$( document ).on( 'click', '.scd-sidebar-section-header', function( e ) {
				e.preventDefault();
				var $toggle = $( this ).find( '.scd-sidebar-section-toggle' );
				SidebarCollapse.toggleSection( $toggle );
			} );

			// Handle keyboard accessibility (Enter/Space on header)
			$( document ).on( 'keydown', '.scd-sidebar-section-header', function( e ) {
				if ( 13 === e.which || 32 === e.which ) { // Enter or Space
					e.preventDefault();
					var $toggle = $( this ).find( '.scd-sidebar-section-toggle' );
					SidebarCollapse.toggleSection( $toggle );
				}
			} );
		},

		/**
		 * Toggle section open/closed
		 *
		 * @param {jQuery} $toggle Toggle button element
		 */
		toggleSection: function( $toggle ) {
			var $section = $toggle.closest( '.scd-sidebar-section' );
			var sectionId = $section.attr( 'id' );
			var $header = $section.find( '.scd-sidebar-section-header' );
			var $content = $section.find( '.scd-sidebar-section-content' );
			var $icon = $toggle.find( '.dashicons' );

			// Toggle collapsed state
			if ( $section.hasClass( 'collapsed' ) ) {
				// Expand
				$section.removeClass( 'collapsed' );
				$content.slideDown( 200 );
				$icon.removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );
				$header.attr( 'aria-expanded', 'true' );

				this.saveState( sectionId, 'open' );
			} else {
				// Collapse
				$section.addClass( 'collapsed' );
				$content.slideUp( 200 );
				$icon.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );
				$header.attr( 'aria-expanded', 'false' );

				this.saveState( sectionId, 'collapsed' );
			}
		},

		/**
		 * Restore section states from localStorage
		 */
		restoreStates: function() {
			var self = this;

			$( '.scd-sidebar-section' ).each( function() {
				var $section = $( this );
				var sectionId = $section.attr( 'id' );
				var $header = $section.find( '.scd-sidebar-section-header' );
				var savedState = self.getState( sectionId );
				var defaultState = $section.data( 'default-state' ) || 'open';

				// Use saved state if available, otherwise use default
				var state = savedState || defaultState;

				if ( 'collapsed' === state ) {
					// Apply collapsed state without animation on page load
					$section.addClass( 'collapsed' );
					$section.find( '.scd-sidebar-section-content' ).hide();
					$section.find( '.dashicons' ).removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );
					$header.attr( 'aria-expanded', 'false' );
				} else {
					// Ensure expanded state
					$section.removeClass( 'collapsed' );
					$section.find( '.scd-sidebar-section-content' ).show();
					$section.find( '.dashicons' ).removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );
					$header.attr( 'aria-expanded', 'true' );
				}
			} );
		},

		/**
		 * Save section state to localStorage
		 *
		 * @param {string} sectionId Section identifier
		 * @param {string} state State (open or collapsed)
		 */
		saveState: function( sectionId, state ) {
			if ( ! sectionId ) {
				return;
			}

			try {
				var key = 'scd_sidebar_' + sectionId;
				localStorage.setItem( key, state );
			} catch ( e ) {
				// localStorage not available or quota exceeded
				// Fail silently
			}
		},

		/**
		 * Get section state from localStorage
		 *
		 * @param {string} sectionId Section identifier
		 * @return {string|null} Saved state or null
		 */
		getState: function( sectionId ) {
			if ( ! sectionId ) {
				return null;
			}

			try {
				var key = 'scd_sidebar_' + sectionId;
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
					if ( key && key.indexOf( 'scd_sidebar_' ) === 0 ) {
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
		SidebarCollapse.init();
	} );

	/**
	 * Expose to global scope for debugging
	 */
	window.SCD = window.SCD || {};
	window.SCD.SidebarCollapse = SidebarCollapse;

} )( jQuery );
