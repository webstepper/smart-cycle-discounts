/**
 * Tools
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/tools.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize tools page functionality
	 */
	function init() {
		// Import/Export handlers
		$( '.scd-export-campaigns-btn' ).on( 'click', handleExportCampaigns );
		$( '.scd-export-settings-btn' ).on( 'click', handleExportSettings );
		$( '.scd-import-data-btn' ).on( 'click', handleImportData );

		// Database maintenance handlers
		$( '.scd-optimize-tables-btn' ).on( 'click', handleOptimizeTables );
		$( '.scd-cleanup-expired-btn' ).on( 'click', handleCleanupExpired );

		// Cache management handlers
		$( '.scd-rebuild-cache-btn' ).on( 'click', handleRebuildCache );

		// Debug logs handlers
		$( '.scd-view-logs-btn' ).on( 'click', handleViewLogs );
		$( '.scd-download-logs-btn' ).on( 'click', handleDownloadLogs );
		$( '.scd-clear-logs-btn' ).on( 'click', handleClearLogs );
		$( '.scd-copy-log-btn' ).on( 'click', handleCopyLog );

		// System diagnostics handlers
		$( '.scd-health-check-btn' ).on( 'click', handleHealthCheck );
		$( '.scd-generate-report-btn' ).on( 'click', handleGenerateReport );
		$( '.scd-copy-report-btn' ).on( 'click', handleCopyReport );
		$( '.scd-download-report-btn' ).on( 'click', handleDownloadReport );
	}

	/**
	 * Handle export campaigns button click
	 */
	function handleExportCampaigns( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Exporting...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'export',
				export_type: 'campaigns',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					// Trigger download
					triggerDownload( response.data.content, response.data.filename );
					showNotification( 'Campaigns exported successfully', 'success' );
				} else {
					showNotification( response.data ? response.data.message : 'Failed to export campaigns', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error exporting campaigns. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle export settings button click
	 */
	function handleExportSettings( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Exporting...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'export',
				export_type: 'settings',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					// Trigger download
					triggerDownload( response.data.content, response.data.filename );
					showNotification( 'Settings exported successfully', 'success' );
				} else {
					showNotification( response.data ? response.data.message : 'Failed to export settings', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error exporting settings. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle import data button click
	 */
	function handleImportData( e ) {
		e.preventDefault();
		var $button = $( this );
		var $fileInput = $( '#scd-import-file' );
		var file = $fileInput[ 0 ].files[ 0 ];

		if ( ! file ) {
			showNotification( 'Please select a file to import', 'error' );
			return;
		}

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Importing...' );

		// Read file content
		var reader = new FileReader();
		reader.onload = function( event ) {
			// Make AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_ajax',
					scdAction: 'import',
					import_data: event.target.result,
					nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
				},
				success: function( response ) {
					if ( response.success ) {
						showNotification( response.data.message || 'Data imported successfully', 'success' );
						// Reload page after successful import
						setTimeout( function() {
							location.reload();
						}, 2000 );
					} else {
						showNotification( response.data ? response.data.message : 'Failed to import data', 'error' );
					}
				},
				error: function() {
					showNotification( 'Error importing data. Please try again.', 'error' );
				},
				complete: function() {
					$button.prop( 'disabled', false );
					$button.html( originalText );
				}
			} );
		};
		reader.readAsText( file );
	}

	/**
	 * Handle optimize tables button click
	 */
	function handleOptimizeTables( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Optimizing...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'database_maintenance',
				operation: 'optimize',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success ) {
					showNotification( response.data.message || 'Tables optimized successfully', 'success' );
					// Reload page to update stats
					setTimeout( function() {
						location.reload();
					}, 1500 );
				} else {
					showNotification( response.data ? response.data.message : 'Failed to optimize tables', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error optimizing tables. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle cleanup expired data button click
	 */
	function handleCleanupExpired( e ) {
		e.preventDefault();
		var $button = $( this );

		// Confirmation is handled by onclick attribute

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Cleaning...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'database_maintenance',
				operation: 'cleanup_expired',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success ) {
					showNotification( response.data.message || 'Expired data cleaned successfully', 'success' );
				} else {
					showNotification( response.data ? response.data.message : 'Failed to cleanup data', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error cleaning up data. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle rebuild cache button click
	 */
	function handleRebuildCache( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Rebuilding...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'cache_management',
				operation: 'rebuild_cache',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success ) {
					showNotification( response.data.message || 'Cache rebuilt successfully', 'success' );
				} else {
					showNotification( response.data ? response.data.message : 'Failed to rebuild cache', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error rebuilding cache. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle view logs button click
	 */
	function handleViewLogs( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Loading...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'log_viewer',
				log_action: 'view',
				lines: 500,
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					// Show logs in textarea
					var $modal = $( '#scd-log-viewer-modal' );
					$modal.find( 'textarea' ).val( response.data.logs );
					$modal.slideDown();
				} else {
					showNotification( response.data ? response.data.message : 'Failed to load logs', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error loading logs. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle download logs button click
	 */
	function handleDownloadLogs( e ) {
		e.preventDefault();

		// Create hidden form and submit
		var form = $( '<form>', {
			method: 'POST',
			action: ajaxurl
		} );

		form.append( $( '<input>', { type: 'hidden', name: 'action', value: 'scd_ajax' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'scdAction', value: 'log_viewer' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'log_action', value: 'download' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'nonce', value: ( window.scdAdmin && window.scdAdmin.nonce ) || '' } ) );

		$( 'body' ).append( form );
		form.submit();
		form.remove();
	}

	/**
	 * Handle clear logs button click
	 */
	function handleClearLogs( e ) {
		e.preventDefault();
		var $button = $( this );

		// Confirm action
		if ( ! confirm( 'This will permanently delete the log file. Continue?' ) ) {
			return;
		}

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Clearing...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'log_viewer',
				log_action: 'clear',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success ) {
					showNotification( response.data.message || 'Logs cleared successfully', 'success' );
					// Reload page to update stats
					setTimeout( function() {
						location.reload();
					}, 1500 );
				} else {
					showNotification( response.data ? response.data.message : 'Failed to clear logs', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error clearing logs. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle copy log to clipboard button click
	 */
	function handleCopyLog( e ) {
		e.preventDefault();
		var $textarea = $( '#scd-log-viewer-modal textarea' );

		// Check if log is loaded
		if ( ! $textarea.val() ) {
			showNotification( 'No log data to copy. Please view the log first.', 'error' );
			return;
		}

		// Select and copy
		$textarea.select();
		document.execCommand( 'copy' );

		// Show feedback
		var $button = $( this );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-yes"></span> Copied!' );

		setTimeout( function() {
			$button.html( originalText );
		}, 2000 );
	}

	/**
	 * Handle health check button click
	 */
	function handleHealthCheck( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Checking...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'health_check',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					// Show results
					var $results = $( '#scd-health-check-results' );
					$results.html( formatHealthCheckResults( response.data.results ) );
					$results.slideDown();
				} else {
					showNotification( response.data ? response.data.message : 'Failed to run health check', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error running health check. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle generate system report button click
	 */
	function handleGenerateReport( e ) {
		e.preventDefault();
		var $button = $( this );

		// Show loading state
		$button.prop( 'disabled', true );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-update dashicons-spin"></span> Generating...' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_ajax',
				scdAction: 'log_viewer',
				log_action: 'system_report',
				nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					// Show report in textarea
					var $reportContainer = $( '#scd-system-report' );
					$reportContainer.find( 'textarea' ).val( response.data.report );
					$reportContainer.slideDown();

					// Show copy and download buttons
					$( '.scd-copy-report-btn, .scd-download-report-btn' ).fadeIn();
				} else {
					showNotification( response.data ? response.data.message : 'Failed to generate report', 'error' );
				}
			},
			error: function() {
				showNotification( 'Error generating report. Please try again.', 'error' );
			},
			complete: function() {
				$button.prop( 'disabled', false );
				$button.html( originalText );
			}
		} );
	}

	/**
	 * Handle copy report to clipboard button click
	 */
	function handleCopyReport( e ) {
		e.preventDefault();
		var $textarea = $( '#scd-system-report textarea' );

		// Select and copy
		$textarea.select();
		document.execCommand( 'copy' );

		// Show feedback
		var $button = $( this );
		var originalText = $button.html();
		$button.html( '<span class="dashicons dashicons-yes"></span> Copied!' );

		setTimeout( function() {
			$button.html( originalText );
		}, 2000 );
	}

	/**
	 * Handle download report button click
	 */
	function handleDownloadReport( e ) {
		e.preventDefault();
		var reportContent = $( '#scd-system-report textarea' ).val();

		if ( ! reportContent ) {
			showNotification( 'No report to download. Please generate a report first.', 'error' );
			return;
		}

		// Create blob and download
		var blob = new Blob( [ reportContent ], { type: 'text/plain' } );
		var url = window.URL.createObjectURL( blob );
		var timestamp = new Date().toISOString().replace( /[:.]/g, '-' ).slice( 0, -5 );
		var filename = 'scd-system-report-' + timestamp + '.txt';

		var a = document.createElement( 'a' );
		a.href = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		window.URL.revokeObjectURL( url );
	}

	/**
	 * Trigger file download
	 *
	 * @param {string} content  File content
	 * @param {string} filename File name
	 */
	function triggerDownload( content, filename ) {
		var blob = new Blob( [ content ], { type: 'application/json' } );
		var url = window.URL.createObjectURL( blob );

		var a = document.createElement( 'a' );
		a.href = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		window.URL.revokeObjectURL( url );
	}

	/**
	 * Format health check results
	 *
	 * @param {Array} results Health check results
	 * @return {string} Formatted HTML
	 */
	function formatHealthCheckResults( results ) {
		var html = '<div class="scd-health-check-results">';

		for ( var i = 0; i < results.length; i++ ) {
			var result = results[ i ];
			var statusClass = result.status === 'pass' ? 'notice-success' : ( result.status === 'warning' ? 'notice-warning' : 'notice-error' );

			html += '<div class="notice ' + statusClass + ' inline">';
			html += '<p><strong>' + result.test + ':</strong> ' + result.message + '</p>';
			html += '</div>';
		}

		html += '</div>';
		return html;
	}

	/**
	 * Show notification message
	 *
	 * @param {string} message Notification message
	 * @param {string} type    Notification type (success, error, warning, info)
	 */
	function showNotification( message, type ) {
		// Use NotificationService if available
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
			window.SCD.Shared.NotificationService.show( message, type || 'info', 3000 );
		} else {
			// Fallback to alert
			alert( message );
		}
	}

	// Initialize on document ready
	$( document ).ready( init );

})( jQuery );
