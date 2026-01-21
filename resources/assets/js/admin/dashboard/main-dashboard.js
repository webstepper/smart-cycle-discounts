/**
 * Main Dashboard
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/dashboard/main-dashboard.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $, window, document ) {
	'use strict';

	/**
	 * Main Dashboard Manager
	 *
	 * @since 1.0.0
	 */
	var WSSCD_Main_Dashboard = {

		/**
		 * Dashboard data cache
		 *
		 * @since 1.0.0
		 * @type {Object}
		 */
		data: {},

		/**
		 * Loading state
		 *
		 * @since 1.0.0
		 * @type {Boolean}
		 */
		isLoading: false,

		/**
		 * Initialize dashboard
		 *
		 * @since 1.0.0
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			// Refresh button (if needed in future)
			$( document ).on( 'click', '#wsscd-refresh-data', function( e ) {
				e.preventDefault();
				WSSCD_Main_Dashboard.loadDashboardData();
			} );

			// Campaign suggestion details toggle
			$( document ).on( 'click', '.wsscd-details-toggle-btn', function( e ) {
				e.preventDefault();
				WSSCD_Main_Dashboard.toggleSuggestionDetails( $( this ) );
			} );

			// Prevent page unload during data loading
			$( window ).on( 'beforeunload', function() {
				if ( WSSCD_Main_Dashboard.isLoading ) {
					return 'Dashboard data is still loading. Are you sure you want to leave?';
				}
			} );
		},

		/**
		 * Load dashboard data via AJAX
		 *
		 * @since 1.0.0
		 */
		loadDashboardData: function() {
			// Prevent multiple simultaneous requests
			if ( this.isLoading ) {
				return;
			}

			this.showLoading();

			var self = this;

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wsscd_ajax',
					wsscdAction: 'main_dashboard_data',
					nonce: window.wsscdDashboard.nonce
				},
				dataType: 'json',
				timeout: 30000,
				success: function( response ) {
					self.hideLoading();

					if ( response.success && response.data ) {
						self.data = response.data;
						self.updateDashboard( response.data );
					} else {
						self.handleError( response.data && response.data.message ? response.data.message : 'Failed to load dashboard data' );
					}
				},
				error: function( xhr, status, error ) {
					self.hideLoading();
					self.handleError( 'Network error: ' + error );
				}
			} );
		},

		/**
		 * Update dashboard with new data
		 *
		 * @since 1.0.0
		 * @param {Object} data Dashboard data
		 */
		updateDashboard: function( data ) {
			if ( data.metrics ) {
				this.updateHeroStats( data.metrics );
			}

			if ( data.campaign_stats ) {
				this.updateCampaignStats( data.campaign_stats );
			}

			if ( data.top_campaigns ) {
				this.updateTopCampaigns( data.top_campaigns );
			}

			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.success( 'Dashboard updated successfully' );
			}
		},

		/**
		 * Update hero stat cards
		 *
		 * @since 1.0.0
		 * @param {Object} metrics Metrics data
		 */
		updateHeroStats: function( metrics ) {
			if ( metrics.revenue !== undefined ) {
				$( '#wsscd-stat-revenue' ).html( this.formatCurrency( metrics.revenue ) );
			}

			if ( metrics.active !== undefined ) {
				$( '#wsscd-stat-active-campaigns' ).text( metrics.active );
			}

			if ( metrics.conversions !== undefined ) {
				$( '#wsscd-stat-conversions' ).text( this.formatNumber( metrics.conversions ) );
			}

			// Update CTR
			if ( metrics.clicks !== undefined && metrics.impressions !== undefined ) {
				var ctr = metrics.impressions > 0 ? ( metrics.clicks / metrics.impressions ) * 100 : 0;
				$( '#wsscd-stat-ctr' ).text( this.formatPercentage( ctr ) );
			}
		},

		/**
		 * Update campaign status badges
		 *
		 * @since 1.0.0
		 * @param {Object} stats Campaign status counts
		 */
		updateCampaignStats: function( stats ) {
			var statuses = [ 'active', 'scheduled', 'paused', 'draft' ];

			for ( var i = 0; i < statuses.length; i++ ) {
				var status = statuses[i];
				if ( stats[status] !== undefined ) {
					$( '.wsscd-badge-status--' + status ).each( function() {
						var $badge = $( this );
						var currentText = $badge.text();
						// Replace number while keeping label
						var newText = currentText.replace( /\d+/, stats[status] );
						$badge.text( newText );
					} );
				}
			}

			if ( stats.total !== undefined ) {
				$( '.wsscd-status-total strong' ).next().text( stats.total );
			}
		},

		/**
		 * Update top campaigns table
		 *
		 * @since 1.0.0
		 * @param {Array} campaigns Top campaigns data
		 */
		updateTopCampaigns: function( campaigns ) {
			var $tbody = $( '.wsscd-campaigns-table tbody' );

			if ( !campaigns || campaigns.length === 0 ) {
				return;
			}

			$tbody.empty();

			for ( var i = 0; i < campaigns.length; i++ ) {
				var campaign = campaigns[i];
				var row = '<tr>' +
					'<td>' +
					'<strong>' + this.escapeHtml( campaign.name ) + '</strong>' +
					'<span class="wsscd-badge-status--' + this.escapeHtml( campaign.status ) + '">' +
					this.escapeHtml( this.capitalizeFirst( campaign.status ) ) +
					'</span>' +
					'</td>' +
					'<td>' + this.formatCurrency( campaign.revenue || 0 ) + '</td>' +
					'<td>' + this.formatNumber( campaign.conversions || 0 ) + '</td>' +
					'<td>' +
					'<a href="admin.php?page=wsscd-campaigns&action=edit&id=' + campaign.id + '" class="button button-small">Edit</a>' +
					'</td>' +
					'</tr>';

				$tbody.append( row );
			}
		},

		/**
		 * Show loading overlay
		 *
		 * @since 1.0.0
		 */
		showLoading: function() {
			this.isLoading = true;
			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.show( 'wsscd-dashboard-loading', { fade: true } );
			}
		},

		/**
		 * Hide loading overlay
		 *
		 * @since 1.0.0
		 */
		hideLoading: function() {
			this.isLoading = false;
			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.hide( 'wsscd-dashboard-loading', { fade: true } );
			}
		},

		/**
		 * Handle error
		 *
		 * @since 1.0.0
		 * @param {String} message Error message
		 */
		handleError: function( message ) {
			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.error( message );
			} else {
				alert( 'Error: ' + message );
			}
		},

		/**
		 * Format currency value
		 *
		 * @since 1.0.0
		 * @param {Number} value Numeric value
		 * @return {String} Formatted currency
		 */
		formatCurrency: function( value ) {
			// Use WordPress currency formatting if available
			if ( window.wsscdDashboard && window.wsscdDashboard.currencySymbol ) {
				return window.wsscdDashboard.currencySymbol + this.formatNumber( value, 2 );
			}
			return '$' + this.formatNumber( value, 2 );
		},

		/**
		 * Format number with thousands separator
		 *
		 * @since 1.0.0
		 * @param {Number} value Numeric value
		 * @param {Number} decimals Number of decimal places
		 * @return {String} Formatted number
		 */
		formatNumber: function( value, decimals ) {
			decimals = decimals || 0;
			var number = parseFloat( value ).toFixed( decimals );
			var parts = number.split( '.' );
			parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
			return parts.join( '.' );
		},

		/**
		 * Format percentage value
		 *
		 * @since 1.0.0
		 * @param {Number} value Numeric value
		 * @return {String} Formatted percentage
		 */
		formatPercentage: function( value ) {
			return this.formatNumber( value, 2 ) + '%';
		},

		/**
		 * Toggle campaign suggestion details
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Toggle button element
		 */
		toggleSuggestionDetails: function( $button ) {
			var suggestionId = $button.data( 'suggestion-id' );
			var $details = $( '#wsscd-details-' + suggestionId );

			if ( $details.is( ':visible' ) ) {
				// Close details
				$details.slideUp( 300 );
				$button.removeClass( 'wsscd-active' );
				$button.find( 'span:not(.wsscd-icon)' ).text( 'Show More Details' );
			} else {
				// Open details
				$details.slideDown( 300 );
				$button.addClass( 'wsscd-active' );
				$button.find( 'span:not(.wsscd-icon)' ).text( 'Show Less Details' );
			}
		},

		/**
		 * Capitalize first letter
		 *
		 * @since 1.0.0
		 * @param {String} str String to capitalize
		 * @return {String} Capitalized string
		 */
		capitalizeFirst: function( str ) {
			return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
		},

		/**
		 * Escape HTML entities
		 *
		 * @since 1.0.0
		 * @param {String} str String to escape
		 * @return {String} Escaped string
		 */
		escapeHtml: function( str ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( str ) );
			return div.innerHTML;
		}
	};

	$( document ).ready( function() {
		// Only initialize if we're on the main dashboard page
		if ( $( '.wsscd-main-dashboard' ).length > 0 ) {
			WSSCD_Main_Dashboard.init();
		}
	} );

	// Expose to global scope for debugging
	window.WSSCD_Main_Dashboard = WSSCD_Main_Dashboard;

} )( jQuery, window, document );
