/**
 * User Roles Module
 *
 * Handles user role targeting UI in the discounts step of the campaign wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/user-roles.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.3.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Discounts = WSSCD.Modules.Discounts || {};

	/**
	 * User Roles Module
	 *
	 * @class WSSCD.Modules.Discounts.UserRoles
	 */
	WSSCD.Modules.Discounts.UserRoles = {
		/**
		 * Cached DOM elements
		 */
		$elements: {},

		/**
		 * Initialization flag
		 */
		initialized: false,

		/**
		 * Initialize the module
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.cacheElements();

			// Check if required elements exist before proceeding.
			if ( ! this.$elements.$modeRadios.length ) {
				return;
			}

			this.bindEvents();
			this.initialized = true;

			// Set initial state
			this.handleModeChange( this.getCurrentMode() );
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$elements = {
				$modeRadios: $( 'input[name="user_roles_mode"]' ),
				$selectionWrapper: $( '#wsscd-user-roles-selection' ),
				$roleCheckboxes: $( '.wsscd-role-checkbox input[type="checkbox"]' ),
				$hiddenInput: $( '#user_roles_json' ),
				$sectionLabel: $( '#wsscd-user-roles-section-label' ),
				$helpText: $( '#wsscd-user-roles-help' )
			};
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Handle mode radio changes
			this.$elements.$modeRadios.on( 'change', function() {
				self.handleModeChange( $( this ).val() );
			} );

			// Handle role checkbox changes
			$( document ).on( 'change', '.wsscd-role-checkbox input[type="checkbox"]', function() {
				self.updateSelectedRoles();
			} );
		},

		/**
		 * Get current mode value
		 *
		 * @return {string} Current mode (all, include, exclude)
		 */
		getCurrentMode: function() {
			return this.$elements.$modeRadios.filter( ':checked' ).val() || 'all';
		},

		/**
		 * Handle mode radio change
		 *
		 * @param {string} mode 'all', 'include', or 'exclude'
		 */
		handleModeChange: function( mode ) {
			if ( 'all' === mode ) {
				this.$elements.$selectionWrapper.addClass( 'wsscd-hidden' );
				// Clear selected roles when switching to "all"
				this.$elements.$roleCheckboxes.prop( 'checked', false );
				this.$elements.$hiddenInput.val( '[]' );
			} else {
				this.$elements.$selectionWrapper.removeClass( 'wsscd-hidden' );
				this.updateLabels( mode );
			}
		},

		/**
		 * Update section labels based on mode
		 *
		 * @param {string} mode 'include' or 'exclude'
		 */
		updateLabels: function( mode ) {
			var sectionLabel = '';
			var helpText = '';

			if ( 'include' === mode ) {
				sectionLabel = this.getI18n( 'selectRolesToInclude' );
				helpText = this.getI18n( 'includeHelpText' );
			} else {
				sectionLabel = this.getI18n( 'selectRolesToExclude' );
				helpText = this.getI18n( 'excludeHelpText' );
			}

			this.$elements.$sectionLabel.text( sectionLabel );
			this.$elements.$helpText.text( helpText );
		},

		/**
		 * Update selected roles in hidden input
		 */
		updateSelectedRoles: function() {
			var selectedRoles = [];

			$( '.wsscd-role-checkbox input[type="checkbox"]:checked' ).each( function() {
				selectedRoles.push( $( this ).val() );
			} );

			this.$elements.$hiddenInput.val( JSON.stringify( selectedRoles ) );
		},

		/**
		 * Get i18n string
		 *
		 * @param {string} key Translation key
		 * @return {string} Translated string
		 */
		getI18n: function( key ) {
			var i18n = ( window.wsscdWizardData && window.wsscdWizardData.i18n ) ||
				( window.wsscdAdmin && window.wsscdAdmin.i18n ) ||
				{};

			var defaults = {
				selectRolesToInclude: 'Select Roles to Include',
				selectRolesToExclude: 'Select Roles to Exclude',
				includeHelpText: 'Only users with the selected roles will see this discount.',
				excludeHelpText: 'Users with the selected roles will NOT see this discount.'
			};

			return i18n[ key ] || defaults[ key ] || key;
		},

		/**
		 * Get user roles configuration data
		 *
		 * @return {object} User roles config
		 */
		getData: function() {
			var mode = this.getCurrentMode();
			var roles = [];

			if ( 'all' !== mode ) {
				var hiddenValue = this.$elements.$hiddenInput.val();
				if ( hiddenValue ) {
					try {
						roles = JSON.parse( hiddenValue );
					} catch ( e ) {
						roles = [];
					}
				}
			}

			return {
				user_roles_mode: mode,
				user_roles: roles
			};
		},

		/**
		 * Set user roles configuration data
		 *
		 * @param {object} data User roles config
		 */
		setData: function( data ) {
			if ( ! data ) {
				return;
			}

			// Set mode
			var mode = data.user_roles_mode || data.userRolesMode || 'all';
			this.$elements.$modeRadios
				.filter( '[value="' + mode + '"]' )
				.prop( 'checked', true );

			// Set selected roles
			var roles = data.user_roles || data.userRoles || [];
			if ( ! Array.isArray( roles ) ) {
				roles = [];
			}

			// Update checkboxes
			this.$elements.$roleCheckboxes.each( function() {
				var roleSlug = $( this ).val();
				$( this ).prop( 'checked', -1 !== $.inArray( roleSlug, roles ) );
			} );

			// Update hidden input
			this.$elements.$hiddenInput.val( JSON.stringify( roles ) );

			// Update UI state
			this.handleModeChange( mode );
		}
	};

	// Initialize when document is ready and on step activation
	$( document ).ready( function() {
		// Initialize immediately if on discounts step
		if ( $( 'input[name="user_roles_mode"]' ).length ) {
			WSSCD.Modules.Discounts.UserRoles.init();
		}
	} );

	// Also initialize when discounts step is activated
	$( document ).on( 'wsscd:step:activated', function( event, data ) {
		if ( 'discounts' === data.step ) {
			WSSCD.Modules.Discounts.UserRoles.init();
		}
	} );

} )( jQuery );
