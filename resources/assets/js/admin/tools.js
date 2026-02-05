/**
 * Tools
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/tools.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
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
		$( '.wsscd-export-campaigns-btn' ).on( 'click', handleExportCampaigns );
		$( '.wsscd-export-settings-btn' ).on( 'click', handleExportSettings );
		$( '.wsscd-import-data-btn' ).on( 'click', handleImportData );

		// Database maintenance handlers
		$( '.wsscd-optimize-tables-btn' ).on( 'click', handleOptimizeTables );
		$( '.wsscd-cleanup-expired-btn' ).on( 'click', handleCleanupExpired );

		$( '.wsscd-rebuild-cache-btn' ).on( 'click', handleRebuildCache );

		// Debug logs handlers
		$( '.wsscd-view-logs-btn' ).on( 'click', handleViewLogs );
		$( '.wsscd-download-logs-btn' ).on( 'click', handleDownloadLogs );
		$( '.wsscd-clear-logs-btn' ).on( 'click', handleClearLogs );
		$( '.wsscd-copy-log-btn' ).on( 'click', handleCopyLog );

		// System diagnostics handlers
		$( '.wsscd-health-check-btn' ).on( 'click', handleHealthCheck );
		$( '.wsscd-generate-report-btn' ).on( 'click', handleGenerateReport );
		$( '.wsscd-copy-report-btn' ).on( 'click', handleCopyReport );
		$( '.wsscd-download-report-btn' ).on( 'click', handleDownloadReport );
	}

	/**
	 * Handle export campaigns button click
	 */
	function handleExportCampaigns( e ) {
		e.preventDefault();
		var $button = $( this );

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Exporting...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'export',
				exportType: 'campaigns',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
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
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle export settings button click
	 */
	function handleExportSettings( e ) {
		e.preventDefault();
		var $button = $( this );

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Exporting...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'export',
				exportType: 'settings',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
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
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle import data button click
	 */
	function handleImportData( e ) {
		e.preventDefault();
		var $button = $( this );
		var originalText = $button.html();
		var $fileInput = $( '#wsscd-import-file' );
		var file = $fileInput[ 0 ].files[ 0 ];

		if ( ! file ) {
			showNotification( 'Please select a file to import', 'error' );
			return;
		}

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Importing...' );
		} else {
			$button.prop( 'disabled', true );
			$button.html( 'Importing...' );
		}

		// Read file content
		var reader = new FileReader();
		reader.onload = function( event ) {
			// Make AJAX request
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wsscd_ajax',
					wsscdAction: 'import',
					importData: event.target.result,
					nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
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
					if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
						WSSCD.LoaderUtil.hideButton( $button, originalText );
					} else {
						$button.prop( 'disabled', false );
						$button.html( originalText );
					}
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

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Optimizing...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'database_maintenance',
				operation: 'optimize',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
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
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
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

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Cleaning...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'database_maintenance',
				operation: 'cleanup_expired',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
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
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle clear cache button click
	 */
	function handleRebuildCache( e ) {
		e.preventDefault();
		var $button = $( this );

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Clearing...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'cache_management',
				operation: 'rebuild_cache',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success ) {
					showNotification( response.data && response.data.message ? response.data.message : 'Cache cleared successfully', 'success' );
				} else {
					showNotification( WSSCD.Shared.extractErrorMessage( response, 'Failed to clear cache' ), 'error' );
				}
			},
			error: function( xhr ) {
				showNotification( WSSCD.Shared.extractErrorMessage( xhr.responseJSON, 'Error clearing cache. Please try again.' ), 'error' );
			},
			complete: function() {
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle view logs button click
	 */
	function handleViewLogs( e ) {
		e.preventDefault();
		var $button = $( this );

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Loading...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'log_viewer',
				logAction: 'view',
				lines: 500,
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					var $modal = $( '#wsscd-log-viewer-modal' );
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
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle download logs button click
	 */
	function handleDownloadLogs( e ) {
		e.preventDefault();

		var form = $( '<form>', {
			method: 'POST',
			action: ajaxurl
		} );

		form.append( $( '<input>', { type: 'hidden', name: 'action', value: 'wsscd_ajax' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'wsscdAction', value: 'log_viewer' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'logAction', value: 'download' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'nonce', value: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || '' } ) );

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

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Clearing...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'log_viewer',
				logAction: 'clear',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
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
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle copy log to clipboard button click
	 */
	function handleCopyLog( e ) {
		e.preventDefault();
		var $button = $( this );
		var $textarea = $( '#wsscd-log-viewer-modal textarea' );

		if ( ! $textarea.val() ) {
			showNotification( 'No log data to copy. Please view the log first.', 'error' );
			return;
		}

		// Add pulse animation class
		$button.addClass( 'wsscd-button-pulse' );

		// Select and copy
		$textarea.select();
		document.execCommand( 'copy' );

		var originalText = $button.html();
		$button.html( WSSCD.IconHelper.check( { size: 16 } ) + ' Copied!' );
		$button.addClass( 'wsscd-button-success' );

		setTimeout( function() {
			$button.html( originalText );
			$button.removeClass( 'wsscd-button-pulse wsscd-button-success' );
		}, 2000 );
	}

	/**
	 * Handle health check button click
	 */
	function handleHealthCheck( e ) {
		e.preventDefault();
		var $button = $( this );

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Checking...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'health_check',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					var $results = $( '#wsscd-health-check-results' );
					$results.html( formatHealthCheckResults( response.data.results ) );
					$results.slideDown();
				} else {
					showNotification( WSSCD.Shared.extractErrorMessage( response, 'Failed to run health check' ), 'error' );
				}
			},
			error: function( xhr ) {
				showNotification( WSSCD.Shared.extractErrorMessage( xhr.responseJSON, 'Error running health check. Please try again.' ), 'error' );
			},
			complete: function() {
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle generate system report button click
	 */
	function handleGenerateReport( e ) {
		e.preventDefault();
		var $button = $( this );

		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.showButton( $button, 'Generating...' );
		}

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wsscd_ajax',
				wsscdAction: 'log_viewer',
				logAction: 'system_report',
				nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || ''
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					var $reportContainer = $( '#wsscd-system-report' );
					$reportContainer.find( 'textarea' ).val( response.data.report );
					$reportContainer.slideDown();

					$( '.wsscd-copy-report-btn, .wsscd-download-report-btn' ).fadeIn();
				} else {
					showNotification( WSSCD.Shared.extractErrorMessage( response, 'Failed to generate report' ), 'error' );
				}
			},
			error: function( xhr ) {
				showNotification( WSSCD.Shared.extractErrorMessage( xhr.responseJSON, 'Error generating report. Please try again.' ), 'error' );
			},
			complete: function() {
				if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
					WSSCD.LoaderUtil.hideButton( $button );
				}
			}
		} );
	}

	/**
	 * Handle copy report to clipboard button click
	 */
	function handleCopyReport( e ) {
		e.preventDefault();
		var $button = $( this );
		var $textarea = $( '#wsscd-system-report textarea' );

		if ( ! $textarea.val() ) {
			showNotification( 'No report to copy. Please generate a report first.', 'error' );
			return;
		}

		// Add pulse animation class
		$button.addClass( 'wsscd-button-pulse' );

		// Select and copy
		$textarea.select();
		document.execCommand( 'copy' );

		var originalText = $button.html();
		$button.html( WSSCD.IconHelper.check( { size: 16 } ) + ' Copied!' );
		$button.addClass( 'wsscd-button-success' );

		setTimeout( function() {
			$button.html( originalText );
			$button.removeClass( 'wsscd-button-pulse wsscd-button-success' );
		}, 2000 );
	}

	/**
	 * Handle download report button click
	 */
	function handleDownloadReport( e ) {
		e.preventDefault();
		var $button = $( this );
		var reportContent = $( '#wsscd-system-report textarea' ).val();

		if ( ! reportContent ) {
			showNotification( 'No report to download. Please generate a report first.', 'error' );
			return;
		}

		// Add pulse animation class
		$button.addClass( 'wsscd-button-pulse' );

		var originalText = $button.html();
		$button.html( WSSCD.IconHelper.spinner( { size: 16 } ) + ' Downloading...' );

		// Small delay to show the animation
		setTimeout( function() {
			var blob = new Blob( [ reportContent ], { type: 'text/plain' } );
			var url = window.URL.createObjectURL( blob );
			var timestamp = new Date().toISOString().replace( /[:.]/g, '-' ).slice( 0, -5 );
			var filename = 'wsscd-system-report-' + timestamp + '.txt';

			var a = document.createElement( 'a' );
			a.href = url;
			a.download = filename;
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			window.URL.revokeObjectURL( url );

			// Show success state
			$button.html( WSSCD.IconHelper.check( { size: 16 } ) + ' Downloaded!' );
			$button.addClass( 'wsscd-button-success' );

			setTimeout( function() {
				$button.html( originalText );
				$button.removeClass( 'wsscd-button-pulse wsscd-button-success' );
			}, 1500 );
		}, 300 );
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
		var html = '<div class="wsscd-health-check-results">';

		for ( var i = 0; i < results.length; i++ ) {
			var result = results[ i ];
			var statusClass = 'pass' === result.status ? 'notice-success' : ( 'warning' === result.status ? 'notice-warning' : 'notice-error' );
			var statusIcon = 'pass' === result.status ? 'yes' : ( 'warning' === result.status ? 'warning' : 'no' );

			// Add staggered animation delay for each result
			html += '<div class="notice ' + statusClass + ' inline wsscd-health-result-item" style="animation-delay: ' + ( i * 0.1 ) + 's;">';
			html += '<p>';
			html += '<span class="dashicons dashicons-' + statusIcon + '"></span> ';
			html += '<strong>' + result.test + ':</strong> ' + result.message;
			html += '</p>';
			html += '</div>';
		}

		html += '</div>';
		return html;
	}

	/**
	 * Show notification message via the shared NotificationService.
	 *
	 * NOTE: This helper now delegates directly to the canonical service and exists only
	 * to keep this file concise. Prefer calling NotificationService directly in new code:
	 * WSSCD.Shared.NotificationService.success( message );
	 *
	 * @param {string} message Notification message
	 * @param {string} type    Notification type (success, error, warning, info)
	 */
	function showNotification( message, type ) {
		// Use NotificationService if available (canonical JS notification layer).
		if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
			WSSCD.Shared.NotificationService.show( message, type || 'info', 3000 );
			return;
		}

		// Fallback to alert for extremely early‑load or error cases where assets
		// failed to initialize. This keeps tools usable even if JS fails partially.
		alert( message );
	}

	$( document ).ready( init );

})( jQuery );
