/**
 * Bulk Actions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/bulk-actions.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function ( $ ) {
	'use strict';

	// Bulk actions handler
	var BulkActions = {
		/**
		 * Initialize bulk actions
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			var self = this;

			// Handle bulk action form submission
			$( '#posts-filter' ).on(
				'submit',
				function ( e ) {
					var action = self.getSelectedAction();

					if ( ! action ) {
						return true; // Allow form submission
					}

					// Get selected campaigns
					var selectedCampaigns = $( 'input[name="campaign[]"]:checked' );

					if ( 0 === selectedCampaigns.length ) {
						e.preventDefault();
						alert( 'Please select at least one campaign.' );
						return false;
					}

					// Show confirmation for destructive actions
					if ( self.requiresConfirmation( action ) ) {
						e.preventDefault();
						self.showConfirmation(
							action,
							selectedCampaigns.length,
							function ( confirmed ) {
								if ( confirmed ) {
									$( '#posts-filter' )[0].submit();
								}
							}
						);
						return false;
					}

					// Allow form to submit for non-destructive actions
					return true;
				}
			);

			// Handle select/deselect all
			$( '#cb-select-all-1, #cb-select-all-2' ).on(
				'change',
				function () {
					var isChecked = $( this ).is( ':checked' );
					$( 'input[name="campaign[]"]' ).prop( 'checked', isChecked );
				}
			);
		},

		/**
		 * Get selected bulk action
		 *
		 * @return {string|null} Selected action or null
		 */
		getSelectedAction: function () {
			var topAction    = $( 'select[name="action"]' ).val();
			var bottomAction = $( 'select[name="action2"]' ).val();

			return ( topAction && '-1' !== topAction ) ? topAction : ( bottomAction && '-1' !== bottomAction ? bottomAction : null );
		},

		/**
		 * Check if action requires confirmation
		 *
		 * @param {string} action Action name
		 * @return {boolean} True if requires confirmation
		 */
		requiresConfirmation: function ( action ) {
			var confirmActions = [ 'delete', 'delete_permanently', 'stop_recurring' ];
			return confirmActions.indexOf( action ) !== -1;
		},

		/**
		 * Show confirmation dialog
		 *
		 * @param {string} action Action name
		 * @param {number} count Number of selected campaigns
		 * @param {Function} callback Callback function
		 */
		showConfirmation: function ( action, count, callback ) {
			var messages = {
				'delete': 'Are you sure you want to move %d campaign(s) to trash?',
				'delete_permanently': 'Are you sure you want to permanently delete %d campaign(s)? This action cannot be undone.',
				'stop_recurring': 'Are you sure you want to stop recurring for %d campaign(s)? They will only run once.'
			};

			var message = messages[ action ] || 'Are you sure you want to perform this action on %d campaign(s)?';
			message     = message.replace( '%d', count );

			var confirmed = confirm( message );
			callback( confirmed );
		}
	};

	// Initialize when DOM is ready
	$( document ).ready(
		function () {
			// Only initialize on campaigns list page
			if ( $( '.wp-list-table.campaigns' ).length > 0 ) {
					BulkActions.init();
			}
		}
	);

} )( jQuery );
