/**
 * Analytics Dashboard
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/analytics/analytics-dashboard.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Analytics Dashboard Manager
	 */
	function AnalyticsDashboard() {
		this.config = window.scdAnalytics || {};
		this.charts = {};
		this.refreshTimer = null;
		this.isLoading = false;

		this.init();
	}

	/**
	 * Initialize the dashboard
	 */
	AnalyticsDashboard.prototype.init = function() {
		this.bindEvents();
		this.initCharts();
		this.loadInitialData();
		this.setupAutoRefresh();
	};

	/**
	 * Get theme color with fallback
	 *
	 * @param {string} colorName - Theme color name (e.g., 'primary', 'danger')
	 * @param {string} fallback - Fallback color value
	 * @param {string} alpha - Optional alpha suffix (e.g., 'CC', '1A')
	 * @returns {string} Color value
	 */
	AnalyticsDashboard.prototype.getThemeColor = function( colorName, fallback, alpha ) {
		// Try CSS variable first
		var cssVar = getComputedStyle( document.documentElement )
			.getPropertyValue( '--scd-color-' + colorName ).trim();

		if ( cssVar ) {
			return cssVar + ( alpha || '' );
		}

		// Try window.scdAdmin.colors
		if ( window.scdAdmin && window.scdAdmin.colors && window.scdAdmin.colors[colorName] ) {
			return window.scdAdmin.colors[colorName] + ( alpha || '' );
		}

		// Use fallback
		return fallback;
	};

	/**
	 * Bind event listeners
	 */
	AnalyticsDashboard.prototype.bindEvents = function() {
		var self = this;

		// Quick date filter buttons
		$( '.scd-quick-date-btn' ).on( 'click', function( e ) {
			var $btn = $( e.currentTarget );
			var range = $btn.data( 'range' );

			// Update button states
			$( '.scd-quick-date-btn' ).attr( 'aria-pressed', 'false' );
			$btn.attr( 'aria-pressed', 'true' );

			// Update dropdown to match
			$( '#scd-date-range' ).val( range );

			// Handle the date range change
			self.handleDateRangeChange( range );
		} );

		// Date range selector
		$( '#scd-date-range' ).on( 'change', function( e ) {
			// Update quick button states when dropdown changes
			var value = e.target.value;
			$( '.scd-quick-date-btn' ).attr( 'aria-pressed', 'false' );
			$( '.scd-quick-date-btn[data-range="' + value + '"]' ).attr( 'aria-pressed', 'true' );

			self.handleDateRangeChange( value );
		} );

		// Custom date range
		$( '#scd-apply-date-range' ).on( 'click', function() {
			self.applyCustomDateRange();
		} );

		// Refresh button
		$( '#scd-refresh-data' ).on( 'click', function() {
			self.refreshData();
		} );

		// Auto-refresh toggle
		$( '#scd-auto-refresh' ).on( 'change', function( e ) {
			self.toggleAutoRefresh( e.target.checked );
		} );

		// Chart type selectors
		$( '#scd-revenue-chart-type' ).on( 'change', function( e ) {
			self.changeChartType( 'revenue-trend', e.target.value );
		} );

		// Top Products period selector
		$( '#scd-top-products-period' ).on( 'change', function( e ) {
			self.loadTopProductsData( e.target.value );
		} );

		// Export functionality
		$( '#scd-export-toggle' ).on( 'click', function() {
			self.toggleExportMenu();
		} );

		$( '.scd-export-link' ).on( 'click', function( e ) {
			e.preventDefault();
			self.exportData( $( e.target ).data( 'format' ) );
		} );

		// Export modal
		$( '#scd-export-form' ).on( 'submit', function( e ) {
			e.preventDefault();
			self.handleExportForm();
		} );

		$( '.scd-modal-close' ).on( 'click', function() {
			self.closeModal();
		} );

		// Close dropdowns when clicking outside
		$( document ).on( 'click', function( e ) {
			if ( ! $( e.target ).closest( '.scd-export-dropdown' ).length ) {
				$( '.scd-export-menu' ).hide();
			}
		} );
	};

	/**
	 * Initialize Chart.js charts
	 * Chart.js is loaded via WordPress enqueue system (class-script-registry.php)
	 */
	AnalyticsDashboard.prototype.initCharts = function() {
		// Verify Chart.js is loaded (should always be true via WordPress)
		if ( 'undefined' === typeof Chart ) {
			console.error( 'Chart.js not loaded. Check asset enqueue configuration.' );
			return;
		}

		this.createCharts();
	};

	/**
	 * Create all charts
	 */
	AnalyticsDashboard.prototype.createCharts = function() {
		this.createRevenueChart();
		this.createCampaignChart();
	};

	/**
	 * Create revenue trend chart
	 */
	AnalyticsDashboard.prototype.createRevenueChart = function() {
		var canvas = document.getElementById( 'scd-revenue-trend-chart' );
		if ( ! canvas ) {return;}

		var ctx = canvas.getContext( '2d' );
		var chart = new Chart( ctx, {
			type: 'line',
			data: {
				labels: [],
				datasets: [ {
					label: 'Revenue',
					data: [],
					borderColor: '#2271b1',
					backgroundColor: 'rgba(34, 113, 177, 0.08)',
					borderWidth: 2.5,
					fill: true,
					tension: 0.3,
					pointRadius: 3.5,
					pointHoverRadius: 6,
					pointBackgroundColor: '#fff',
					pointBorderColor: '#2271b1',
					pointBorderWidth: 2,
					pointHoverBackgroundColor: '#2271b1',
					pointHoverBorderColor: '#fff',
					pointHoverBorderWidth: 2.5
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				layout: {
					padding: {
						top: 15,
						right: 10,
						bottom: 15,
						left: 5
					}
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						mode: 'index',
						intersect: false,
						backgroundColor: '#1d2327',
						padding: 14,
						titleColor: '#fff',
						titleFont: {
							size: 12,
							weight: '600',
							family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
						},
						bodyColor: '#f0f0f1',
						bodyFont: {
							size: 13,
							family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
						},
						borderColor: '#3c434a',
						borderWidth: 1,
						displayColors: false,
						cornerRadius: 6,
						caretPadding: 10,
						caretSize: 6,
						callbacks: {
							label: function( context ) {
								var value = context.parsed.y;
								if ( value === 0 ) {
									return 'Revenue: $0.00';
								}
								return 'Revenue: $' + value.toLocaleString( 'en-US', {
									minimumFractionDigits: 2,
									maximumFractionDigits: 2
								} );
							}
						}
					}
				},
				scales: {
					x: {
						display: true,
						border: {
							display: true,
							color: '#dcdcde',
							width: 1
						},
						grid: {
							display: false,
							drawBorder: true
						},
						ticks: {
							autoSkip: true,
							maxRotation: 0,
							minRotation: 0,
							maxTicksLimit: 10,
							padding: 8,
							font: {
								size: 11,
								family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
							},
							color: '#50575e'
						}
					},
					y: {
						display: true,
						min: 0,
						grace: '10%',
						afterDataLimits: function( scale ) {
							if ( scale.max < 100 ) {
								scale.max = 100;
							}
						},
						border: {
							display: true,
							color: '#dcdcde',
							width: 1
						},
						grid: {
							color: '#f0f0f1',
							drawBorder: true,
							lineWidth: 1
						},
						ticks: {
							padding: 12,
							maxTicksLimit: 10,
							font: {
								size: 11,
								family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
							},
							color: '#50575e',
							callback: function( value ) {
								if ( value === 0 ) {
									return '$0';
								}
								if ( value >= 1000 ) {
									return '$' + ( value / 1000 ).toFixed( 1 ) + 'k';
								}
								return '$' + value.toLocaleString( 'en-US', {
									minimumFractionDigits: 0,
									maximumFractionDigits: 0
								} );
							}
						}
					}
				},
				interaction: {
					mode: 'index',
					axis: 'x',
					intersect: false
				}
			}
		} );

		this.charts['revenue-trend'] = chart;
	};

	/**
	 * Create campaign performance chart
	 */
	AnalyticsDashboard.prototype.createCampaignChart = function() {
		var canvas = document.getElementById( 'scd-campaign-performance-chart' );
		if ( ! canvas ) {return;}

		var ctx = canvas.getContext( '2d' );
		var chart = new Chart( ctx, {
			type: 'bar',
			data: {
				labels: [],
				datasets: [ {
					label: 'Revenue',
					data: [],
					backgroundColor: [
						this.getThemeColor( 'primary', 'rgba(54, 162, 235, 0.8)', 'CC' ),
						this.getThemeColor( 'danger', 'rgba(255, 99, 132, 0.8)', 'CC' ),
						this.getThemeColor( 'warning', 'rgba(255, 205, 86, 0.8)', 'CC' ),
						this.getThemeColor( 'success', 'rgba(75, 192, 192, 0.8)', 'CC' ),
						this.getThemeColor( 'accent', 'rgba(153, 102, 255, 0.8)', 'CC' )
					],
					borderColor: [
						this.getThemeColor( 'primary', 'rgba(54, 162, 235, 1)' ),
						this.getThemeColor( 'danger', 'rgba(255, 99, 132, 1)' ),
						this.getThemeColor( 'warning', 'rgba(255, 205, 86, 1)' ),
						this.getThemeColor( 'success', 'rgba(75, 192, 192, 1)' ),
						this.getThemeColor( 'accent', 'rgba(153, 102, 255, 1)' )
					],
					borderWidth: 2
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						callbacks: {
							label: function( context ) {
								return context.dataset.label + ': $' +
                                       context.parsed.y.toLocaleString();
							}
						}
					}
				},
				scales: {
					x: {
						display: true,
						grid: {
							display: false
						}
					},
					y: {
						display: true,
						beginAtZero: true,
						grid: {
							color: this.getThemeColor( 'border', 'rgba(0, 0, 0, 0.1)', '4D' )
						},
						ticks: {
							callback: function( value ) {
								return '$' + value.toLocaleString();
							}
						}
					}
				}
			}
		} );

		this.charts['campaign-performance'] = chart;
	};


	/**
	 * Load initial dashboard data
	 */
	AnalyticsDashboard.prototype.loadInitialData = function() {
		var self = this;

		this.showLoading();

		$.when(
			this.loadOverviewMetrics(),
			this.loadRevenueData(),
			this.loadTopProductsData(),
			this.loadTopCampaigns(),
			this.loadActivityFeed()
		).done( function() {
			self.hideLoading();
			self.updateLastUpdatedTime();
		} ).fail( function( _error ) {
			self.hideLoading();
			self.showError( 'Failed to load dashboard data' );
		} );
	};

	/**
	 * Load overview metrics
	 */
	AnalyticsDashboard.prototype.loadOverviewMetrics = function() {
		var self = this;
		var deferred = $.Deferred();

		this.makeAjaxRequest( 'analytics_overview', {
			dateRange: this.config.currentPeriod
		} ).done( function( response ) {
			// SCD.Ajax.post automatically unwraps the response.data
			if ( response && response.overview ) {
			}
			deferred.resolve();
		} ).fail( function() {
			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * Load revenue trend data
	 */
	AnalyticsDashboard.prototype.loadRevenueData = function() {
		var self = this;
		var deferred = $.Deferred();

		this.makeAjaxRequest( 'analytics_revenue_trend', {
			dateRange: this.config.currentPeriod || '30days',
			granularity: 'daily'
		} ).done( function( response ) {
			// SCD.Ajax.post automatically unwraps the response.data
			if ( response && response.labels && self.charts['revenue-trend'] ) {
				var chart = self.charts['revenue-trend'];
				chart.data.labels = response.labels;
				chart.data.datasets[0].data = response.values;
				chart.update();
			}
			deferred.resolve();
		} ).fail( function() {
			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * Load campaign performance data
	 */
	AnalyticsDashboard.prototype.loadCampaignData = function() {
		var self = this;
		var deferred = $.Deferred();

		this.makeAjaxRequest( 'analytics_campaign_performance', {
			dateRange: this.config.currentPeriod,
			metric: $( '#scd-campaign-metric' ).val() || 'revenue'
		} ).done( function( response ) {
			// SCD.Ajax.post automatically unwraps the response.data
			if ( response && response.labels && self.charts['campaign-performance'] ) {
				var chart = self.charts['campaign-performance'];
				chart.data.labels = response.labels;
				chart.data.datasets[0].data = response.values;
				chart.update();
			}
			deferred.resolve();
		} ).fail( function() {
			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * Load top products data
	 * @param dateRange
	 */
	AnalyticsDashboard.prototype.loadTopProductsData = function( dateRange ) {
		var self = this;
		var deferred = $.Deferred();
		var period = dateRange || $( '#scd-top-products-period' ).val() || '30days';

		this.makeAjaxRequest( 'analytics_top_products', {
			dateRange: period,
			limit: 10
		} ).done( function( response ) {
			// SCD.Ajax.post automatically unwraps the response.data
			if ( response && response.products ) {
				self.updateTopProductsTable( response.products );
			}
			deferred.resolve();
		} ).fail( function() {
			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * Update top products table
	 * @param products
	 */
	AnalyticsDashboard.prototype.updateTopProductsTable = function( products ) {
		var self = this;
		var tbody = $( '#scd-top-products-tbody' );
		tbody.empty();

		if ( ! products || 0 === products.length ) {
			tbody.append(
				'<tr>' +
					'<td colspan="5" class="scd-no-data">' +
						'No product data available for this period' +
					'</td>' +
				'</tr>'
			);
			return;
		}

		$.each( products, function( index, product ) {
			var trend = product.trend || 'neutral';
			var trendIcon = 'neutral' === trend ? '—' :
				( 'up' === trend ? '▲' : '▼' );
			var trendClass = 'scd-trend-' + trend;

			var row = $(
				'<tr>' +
					'<td class="scd-table-col-product">' +
						'<strong>' + self.escapeHtml( product.name ) + '</strong>' +
					'</td>' +
					'<td class="scd-table-col-revenue">' +
						self.formatCurrency( product.revenue ) +
					'</td>' +
					'<td class="scd-table-col-orders">' +
						self.formatNumber( product.order_count ) +
					'</td>' +
					'<td class="scd-table-col-discount">' +
						( product.avg_discount_percent || 0 ).toFixed( 1 ) + '%' +
					'</td>' +
					'<td class="scd-table-col-trend">' +
						'<span class="' + trendClass + '">' + trendIcon + '</span>' +
					'</td>' +
				'</tr>'
			);
			tbody.append( row );
		} );
	};

	/**
	 * Load top campaigns table
	 */
	AnalyticsDashboard.prototype.loadTopCampaigns = function() {
		var self = this;
		var deferred = $.Deferred();

		this.makeAjaxRequest( 'analytics_campaign_performance', {
			dateRange: this.config.currentPeriod,
			limit: 10
		} ).done( function( response ) {
			// SCD.Ajax.post automatically unwraps the response.data
			if ( response && response.campaigns ) {
				self.updateTopCampaignsTable( response.campaigns );
			}
			deferred.resolve();
		} ).fail( function() {
			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * Load activity feed
	 */
	AnalyticsDashboard.prototype.loadActivityFeed = function() {
		var self = this;
		var deferred = $.Deferred();

		this.makeAjaxRequest( 'analytics_activity_feed', {
			limit: 20
		} ).done( function( response ) {
			// SCD.Ajax.post automatically unwraps the response.data
			if ( response && response.activities ) {
				self.updateActivityFeed( response.activities );
			}
			deferred.resolve();
		} ).fail( function() {
			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * Update top campaigns table
	 * @param campaigns
	 */
	AnalyticsDashboard.prototype.updateTopCampaignsTable = function( campaigns ) {
		var self = this;
		var tbody = $( '#scd-top-campaigns-tbody' );
		tbody.empty();

		if ( ! campaigns || 0 === campaigns.length ) {
			tbody.append(
				'<tr>' +
                    '<td colspan="7" class="scd-no-data">' +
                        this.config.strings.noData +
                    '</td>' +
                '</tr>'
			);
			return;
		}

		$.each( campaigns, function( index, campaign ) {
			var row = $(
				'<tr>' +
                    '<td>' +
                        '<strong>' + self.escapeHtml( campaign.name ) + '</strong>' +
                        '<div class="row-actions">' +
                            '<span class="view">' +
                                '<a href="#" class="scd-view-campaign" data-campaign-id="' + campaign.campaignId + '">View</a> | ' +
                            '</span>' +
                            '<span class="edit">' +
                                '<a href="' + campaign.editUrl + '">Edit</a>' +
                            '</span>' +
                        '</div>' +
                    '</td>' +
                    '<td>' +
                        '<span class="scd-status scd-status--' + campaign.status + '">' +
                            self.escapeHtml( campaign.statusLabel ) +
                        '</span>' +
                    '</td>' +
                    '<td>' + self.formatCurrency( campaign.revenue ) + '</td>' +
                    '<td>' + self.formatNumber( campaign.conversions ) + '</td>' +
                    '<td>' + self.formatPercentage( campaign.ctr ) + '</td>' +
                    '<td>' + self.formatPercentage( campaign.roi ) + '</td>' +
                    '<td>' +
                        '<button type="button" class="button button-small scd-view-campaign" data-campaign-id="' + campaign.campaignId + '">' +
                            'View Details' +
                        '</button>' +
                    '</td>' +
                '</tr>'
			);
			tbody.append( row );
		} );
	};

	/**
	 * Update activity feed
	 * @param activities
	 */
	AnalyticsDashboard.prototype.updateActivityFeed = function( activities ) {
		var self = this;
		var feed = $( '#scd-activity-feed' );
		feed.empty();

		if ( ! activities || 0 === activities.length ) {
			feed.append(
				'<div class="scd-activity-item scd-no-activity">' +
                    this.config.strings.noData +
                '</div>'
			);
			return;
		}

		$.each( activities, function( index, activity ) {
			// Convert dashicon class to icon name
			var iconClass = activity.icon || '';
			var iconName = iconClass.replace( 'dashicons-', '' ).replace( 'dashicons', 'info' ) || 'info';
			var icon = SCD.IconHelper ? SCD.IconHelper.get( iconName, { size: 16 } ) : '<span class="scd-icon scd-icon-' + iconName + '"></span>';

			var item = $(
				'<div class="scd-activity-item">' +
                    '<div class="scd-activity-icon">' +
                        icon +
                    '</div>' +
                    '<div class="scd-activity-content">' +
                        '<div class="scd-activity-message">' +
                            self.escapeHtml( activity.message ) +
                        '</div>' +
                        '<div class="scd-activity-time">' +
                            self.escapeHtml( activity.time_ago ) +
                        '</div>' +
                    '</div>' +
                '</div>'
			);
			feed.append( item );
		} );
	};

	/**
	 * Handle date range change
	 * @param value
	 */
	AnalyticsDashboard.prototype.handleDateRangeChange = function( value ) {
		if ( 'custom' === value ) {
			$( '.scd-custom-date-range' ).show();
		} else {
			$( '.scd-custom-date-range' ).hide();
			this.config.currentPeriod = value;
			this.refreshData();
		}
	};

	/**
	 * Apply custom date range
	 */
	AnalyticsDashboard.prototype.applyCustomDateRange = function() {
		var startDate = $( '#scd-start-date' ).val();
		var endDate = $( '#scd-end-date' ).val();

		if ( ! startDate || ! endDate ) {
			SCD.Shared.NotificationService.warning( 'Please select both start and end dates.' );
			return;
		}

		if ( new Date( startDate ) > new Date( endDate ) ) {
			SCD.Shared.NotificationService.warning( 'Start date must be before end date.' );
			return;
		}

		this.config.currentPeriod = 'custom:' + startDate + ':' + endDate;
		this.refreshData();
	};

	/**
	 * Change chart type
	 * @param chartKey
	 * @param newType
	 */
	AnalyticsDashboard.prototype.changeChartType = function( chartKey, newType ) {
		if ( ! this.charts[chartKey] ) {return;}

		var chart = this.charts[chartKey];
		chart.config.type = newType;
		chart.update();
	};

	/**
	 * Refresh all data
	 */
	AnalyticsDashboard.prototype.refreshData = function() {
		if ( this.isLoading ) {return;}

		this.loadInitialData();
	};

	/**
	 * Setup auto-refresh
	 */
	AnalyticsDashboard.prototype.setupAutoRefresh = function() {
		var checkbox = $( '#scd-auto-refresh' );
		if ( checkbox.is( ':checked' ) ) {
			this.startAutoRefresh();
		}
	};

	/**
	 * Toggle auto-refresh
	 * @param enabled
	 */
	AnalyticsDashboard.prototype.toggleAutoRefresh = function( enabled ) {
		if ( enabled ) {
			this.startAutoRefresh();
		} else {
			this.stopAutoRefresh();
		}
	};

	/**
	 * Start auto-refresh using Page Visibility API
	 */
	AnalyticsDashboard.prototype.startAutoRefresh = function() {
		var self = this;
		this.stopAutoRefresh(); // Clear existing setup

		// Use Page Visibility API for efficient refresh
		this.lastRefreshTime = Date.now();

		// Function to check if refresh is needed
		var checkRefresh = function() {
			if ( document.hidden ) {return;}

			var now = Date.now();
			var timeSinceLastRefresh = now - self.lastRefreshTime;

			if ( timeSinceLastRefresh >= self.config.refreshInterval ) {
				self.refreshData();
				self.lastRefreshTime = now;
			}
		};

		// Listen for visibility changes
		this.visibilityHandler = function() {
			if ( !document.hidden ) {
				checkRefresh();
			}
		};

		document.addEventListener( 'visibilitychange', this.visibilityHandler );

		// Also check on focus for browsers that don't support visibility API well
		this.focusHandler = function() {
			checkRefresh();
		};

		window.addEventListener( 'focus', this.focusHandler );

		// Use requestIdleCallback for periodic checks when page is visible
		if ( window.requestIdleCallback ) {
			var scheduleCheck = function() {
				if ( !document.hidden && self.visibilityHandler ) {
					requestIdleCallback( function() {
						checkRefresh();
						scheduleCheck();
					}, { timeout: self.config.refreshInterval } );
				}
			};
			scheduleCheck();
		}
	};

	/**
	 * Stop auto-refresh
	 */
	AnalyticsDashboard.prototype.stopAutoRefresh = function() {
		if ( this.visibilityHandler ) {
			document.removeEventListener( 'visibilitychange', this.visibilityHandler );
			this.visibilityHandler = null;
		}

		if ( this.focusHandler ) {
			window.removeEventListener( 'focus', this.focusHandler );
			this.focusHandler = null;
		}
	};

	/**
	 * Toggle export menu
	 */
	AnalyticsDashboard.prototype.toggleExportMenu = function() {
		$( '.scd-export-menu' ).toggle();
	};

	/**
	 * Export data
	 * @param format
	 */
	AnalyticsDashboard.prototype.exportData = function( format ) {
		var self = this;
		$( '.scd-export-menu' ).hide();

		this.showLoading();

		// Build request data with current filters
		var requestData = {
			format: format,
			dateRange: this.config.currentPeriod,
			exportType: 'overview'
		};

		// Add campaign filter if active
		if ( this.currentCampaignId && 'all' !== this.currentCampaignId ) {
			requestData.campaignId = this.currentCampaignId;
		}

		this.makeAjaxRequest( 'analytics_export', requestData ).done( function( response ) {
			// Response is wrapped in data key by handler
			var exportData = response.data || response;

			if ( exportData && exportData.downloadUrl ) {
				// Trigger download
				var link = document.createElement( 'a' );
				link.href = exportData.downloadUrl;
				link.download = exportData.filename || 'analytics-export.' + format;
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );

				// Use plugin's localized notification strings
				SCD.Shared.NotificationService.success(
					self.config.strings.exportSuccess || 'Export completed successfully'
				);
			} else {
				// Use plugin's localized notification strings
				SCD.Shared.NotificationService.error(
					self.config.strings.exportError || 'Export failed. Please try again.'
				);
			}
		} ).fail( function( error ) {
			// Use plugin's localized notification strings with error message if available
			SCD.Shared.NotificationService.error(
				( error && error.message ) || self.config.strings.exportError || 'Export failed. Please try again.'
			);
		} ).always( function() {
			self.hideLoading();
		} );
	};

	/**
	 * Handle export form submission
	 */
	AnalyticsDashboard.prototype.handleExportForm = function() {
		var self = this;
		var form = $( '#scd-export-form' );
		var formData = form.serializeArray();
		var data = {};

		$.each( formData, function( i, field ) {
			data[field.name] = field.value;
		} );

		this.showLoading();

		this.makeAjaxRequest( 'analytics_export', {
			format: data.format,
			dataType: data.data_type,
			dateRange: this.config.currentPeriod
		} ).done( function( response ) {
			if ( response.success ) {
				// Handle download
				SCD.Shared.NotificationService.success( self.config.strings.exportSuccess );
				self.closeModal();
			} else {
				SCD.Shared.NotificationService.error( self.config.strings.exportError );
			}
		} ).fail( function() {
			SCD.Shared.NotificationService.error( self.config.strings.exportError );
		} ).always( function() {
			self.hideLoading();
		} );
	};

	/**
	 * Close modal
	 */
	AnalyticsDashboard.prototype.closeModal = function() {
		$( '.scd-modal' ).hide();
	};

	/**
	 * Make AJAX request
	 * @param action
	 * @param data
	 */
	AnalyticsDashboard.prototype.makeAjaxRequest = function( action, data ) {
		data = data || {};

		// Use shared AJAX service
		if ( !window.SCD || !window.SCD.Ajax ) {
			throw new Error( 'AJAX service not available' );
		}

		var requestData = $.extend( {}, data );
		requestData.nonce = this.config.nonce;

		return SCD.Ajax.post( 'scd_' + action, requestData );
	};

	/**
	 * Show loading state
	 */
	AnalyticsDashboard.prototype.showLoading = function() {
		this.isLoading = true;
		$( '#scd-dashboard-loading' ).show();
	};

	/**
	 * Hide loading state
	 */
	AnalyticsDashboard.prototype.hideLoading = function() {
		this.isLoading = false;
		$( '#scd-dashboard-loading' ).hide();
	};

	/**
	 * Show error notification
	 * @param {string} message Error message
	 */
	AnalyticsDashboard.prototype.showError = function( message ) {
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
			SCD.Shared.NotificationService.error( message );
		} else {
			console.error( 'Analytics Dashboard Error:', message );
		}
	};

	/**
	 * Update last updated time
	 */
	AnalyticsDashboard.prototype.updateLastUpdatedTime = function() {
		var now = new Date();
		var timeString = now.toLocaleString();
		$( '#scd-last-updated-time' ).text( timeString );
	};

	/**
	 * Utility functions
	 * @param text
	 */
	AnalyticsDashboard.prototype.escapeHtml = function( text ) {
		// Use centralized utility
		if ( !window.SCD || !SCD.Utils || !SCD.Utils.escapeHtml ) {
			throw new Error( 'SCD.Utils.escapeHtml not available. Ensure proper script loading order.' );
		}

		return SCD.Utils.escapeHtml( text );
	};

	AnalyticsDashboard.prototype.formatCurrency = function( amount ) {
		// Use centralized utility
		if ( !window.SCD || !SCD.Utils || !SCD.Utils.formatCurrency ) {
			throw new Error( 'SCD.Utils.formatCurrency not available. Ensure proper script loading order.' );
		}

		return SCD.Utils.formatCurrency( amount );
	};

	AnalyticsDashboard.prototype.formatNumber = function( number ) {
		// Use Intl API - it's well supported
		if ( 'undefined' === typeof Intl || ! Intl.NumberFormat ) {
			// Very old browsers - should upgrade
			throw new Error( 'Intl.NumberFormat not supported. Please upgrade your browser.' );
		}

		return new Intl.NumberFormat( 'en-US' ).format( number || 0 );
	};

	AnalyticsDashboard.prototype.formatPercentage = function( percentage ) {
		var value = ( percentage || 0 ) / 100;

		// Use Intl API - it's well supported
		if ( 'undefined' === typeof Intl || ! Intl.NumberFormat ) {
			// Very old browsers - should upgrade
			throw new Error( 'Intl.NumberFormat not supported. Please upgrade your browser.' );
		}

		return new Intl.NumberFormat( 'en-US', {
			style: 'percent',
			minimumFractionDigits: 1,
			maximumFractionDigits: 1
		} ).format( value );
	};

	$( document ).ready( function() {
		new AnalyticsDashboard();
	} );

} )( jQuery );