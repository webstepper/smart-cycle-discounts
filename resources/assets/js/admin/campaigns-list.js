/**
 * Smart Cycle Discounts - Campaigns List
 *
 * Handles campaign list page functionality
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	var SCD_QuickEdit = {
		/**
		 * Initialize quick edit
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Quick Edit button click
			$( document ).on( 'click', '.editinline', function( e ) {
				e.preventDefault();
				self.openQuickEdit( $( this ) );
			} );

			// Cancel button click
			$( document ).on( 'click', '#scd-quick-edit-row .cancel', function( e ) {
				e.preventDefault();
				self.closeQuickEdit();
			} );

			// Save button click
			$( document ).on( 'click', '#scd-quick-edit-row .save', function( e ) {
				e.preventDefault();
				self.saveQuickEdit();
			} );

			// Press ESC to close
			$( document ).on( 'keyup', function( e ) {
				if ( 27 === e.keyCode ) {
					self.closeQuickEdit();
				}
			} );
		},

		/**
		 * Open quick edit for a campaign
		 */
		openQuickEdit: function( $button ) {
			var campaignId = $button.data( 'campaign-id' );
			var $row = $button.closest( 'tr' );

			// Close any open quick edit
			this.closeQuickEdit();

			// Get campaign data from row
			var campaignName = $row.find( '.row-title' ).text().trim();
			var campaignStatus = this.extractStatus( $row );
			var campaignPriority = this.extractPriority( $row );
			var campaignDiscount = this.extractDiscount( $row );
			var campaignDates = this.extractDates( $row );

			// Clone and show quick edit row
			var $quickEditRow = $( '#scd-quick-edit-row' ).clone();
			$quickEditRow.attr( 'id', 'edit-' + campaignId );
			$quickEditRow.removeClass( 'inline-edit-row' );
			$quickEditRow.addClass( 'inline-editor' );

			// Populate fields
			$quickEditRow.find( 'input[name="campaign_id"]' ).val( campaignId );
			$quickEditRow.find( 'input[name="name"]' ).val( campaignName );
			$quickEditRow.find( 'select[name="status"]' ).val( campaignStatus );
			$quickEditRow.find( 'input[name="priority"]' ).val( campaignPriority );
			$quickEditRow.find( 'input[name="discount_value"]' ).val( campaignDiscount );

			if ( campaignDates.start ) {
				$quickEditRow.find( 'input[name="start_date"]' ).val( campaignDates.start );
			}
			if ( campaignDates.end ) {
				$quickEditRow.find( 'input[name="end_date"]' ).val( campaignDates.end );
			}

			// Insert after current row and show
			$row.after( $quickEditRow );
			$quickEditRow.show();
			$row.addClass( 'is-expanded' );

			// Focus on name field
			$quickEditRow.find( 'input[name="name"]' ).focus().select();
		},

		/**
		 * Close quick edit
		 */
		closeQuickEdit: function() {
			$( '.inline-editor' ).remove();
			$( 'tr.is-expanded' ).removeClass( 'is-expanded' );
		},

		/**
		 * Save quick edit changes
		 */
		saveQuickEdit: function() {
			var $form = $( '.inline-editor' );
			var $saveButton = $form.find( '.save' );
			var $spinner = $form.find( '.spinner' );
			var $error = $form.find( '.error' );

			// Get form data
			var data = {
				action: 'scd_ajax',
				scdAction: 'quick_edit',
				nonce: $form.find( 'input[name="nonce"]' ).val(),
				campaign_id: $form.find( 'input[name="campaign_id"]' ).val(),
				name: $form.find( 'input[name="name"]' ).val(),
				status: $form.find( 'select[name="status"]' ).val(),
				priority: $form.find( 'input[name="priority"]' ).val(),
				discount_value: $form.find( 'input[name="discount_value"]' ).val(),
				start_date: $form.find( 'input[name="start_date"]' ).val(),
				end_date: $form.find( 'input[name="end_date"]' ).val()
			};

			// Show loading
			$saveButton.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$error.hide();

			// Send AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: data,
				success: function( response ) {
					if ( response.success ) {
						// Reload the page to show updated data
						window.location.reload();
					} else {
						$error.text( response.data.message || 'Failed to update campaign.' ).show();
						$saveButton.prop( 'disabled', false );
						$spinner.removeClass( 'is-active' );
					}
				},
				error: function() {
					$error.text( 'An error occurred. Please try again.' ).show();
					$saveButton.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Extract status from row
		 */
		extractStatus: function( $row ) {
			var statusBadge = $row.find( '.scd-status-badge' ).attr( 'class' );
			if ( ! statusBadge ) {
				return 'draft';
			}

			if ( -1 !== statusBadge.indexOf( 'active' ) ) {
				return 'active';
			}
			if ( -1 !== statusBadge.indexOf( 'paused' ) ) {
				return 'paused';
			}
			if ( -1 !== statusBadge.indexOf( 'scheduled' ) ) {
				return 'scheduled';
			}
			if ( -1 !== statusBadge.indexOf( 'expired' ) ) {
				return 'expired';
			}

			return 'draft';
		},

		/**
		 * Extract priority from row
		 */
		extractPriority: function( $row ) {
			var priorityText = $row.find( '.scd-priority-badge' ).text().trim();
			return priorityText || '5';
		},

		/**
		 * Extract discount from row
		 */
		extractDiscount: function( $row ) {
			var discountText = $row.find( '.column-discount strong' ).text().trim();
			// Remove % or currency symbol and parse
			var discount = parseFloat( discountText.replace( /[^0-9.]/g, '' ) );
			return discount || '';
		},

		/**
		 * Extract dates from row
		 */
		extractDates: function( _$row ) {
			// This would need to parse the schedule column
			// For now, return empty - dates can be edited but not pre-filled
			return {
				start: '',
				end: ''
			};
		}
	};

	/**
	 * Status Monitor
	 *
	 * Monitors campaign list page for stale data and provides refresh functionality
	 */
	var SCD_StatusMonitor = {
		/**
		 * Stale notice delay in milliseconds (5 minutes)
		 *
		 * @type {number}
		 */
		staleDelay: 300000,

		/**
		 * Initialize status monitor
		 */
		init: function() {
			// Only initialize on campaigns list page
			if ( 'sc-discounts_page_scd-campaigns' !== window.pagenow ) {
				return;
			}

			this.addRefreshButton();
			this.scheduleStaleNotice();
		},

		/**
		 * Add refresh button to table navigation
		 */
		addRefreshButton: function() {
			var $actions = $( '.tablenav.top .actions' ).first();

			if ( 0 === $actions.length ) {
				return;
			}

			var $button = $( '<button>', {
				type: 'button',
				'class': 'button scd-refresh-status',
				css: {
					marginLeft: '10px'
				}
			} );

			var $icon = $( '<span>', {
				'class': 'dashicons dashicons-update',
				css: {
					marginTop: '3px'
				}
			} );

			$button.append( $icon ).append( ' Refresh Statuses' );

			$button.on( 'click', function( e ) {
				e.preventDefault();
				window.location.reload();
			} );

			$actions.append( $button );
		},

		/**
		 * Schedule stale data notice to appear after delay
		 */
		scheduleStaleNotice: function() {
			var self = this;

			setTimeout( function() {
				self.showStaleNotice();
			}, this.staleDelay );
		},

		/**
		 * Show stale data notice
		 */
		showStaleNotice: function() {
			// Don't show if notice already exists
			if ( $( '.scd-stale-notice' ).length > 0 ) {
				return;
			}

			var $notice = $( '<div>', {
				'class': 'notice notice-info inline scd-stale-notice',
				css: {
					marginBottom: '10px'
				}
			} );

			var $paragraph = $( '<p>' ).html(
				'<strong>Note:</strong> Campaign statuses may have changed. ' +
				'Click <strong>Refresh Statuses</strong> to see the latest data.'
			);

			$notice.append( $paragraph );

			// Insert before the table
			$( '.wp-list-table' ).before( $notice );
		}
	};

	// Initialize when DOM is ready
	$( document ).ready( function() {
		SCD_QuickEdit.init();
		SCD_StatusMonitor.init();
	} );

} )( jQuery );