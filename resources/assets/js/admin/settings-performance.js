/**
 * Settings Performance
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/settings-performance.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Performance Settings Manager
	 */
	function PerformanceSettingsManager() {
		this.config = window.scdSettingsPerformance || {};
		this.init();
	}

	/**
	 * Initialize the settings manager
	 */
	PerformanceSettingsManager.prototype.init = function() {
		this.bindEvents();
	};

	/**
	 * Bind event listeners
	 */
	PerformanceSettingsManager.prototype.bindEvents = function() {
		var self = this;

		// Clear cache button
		$( '#scd-clear-cache-btn' ).on( 'click', function( e ) {
			e.preventDefault();
			self.clearCache();
		} );

		// Handle checkbox changes for conditional fields
		$( 'input[type="checkbox"]' ).on( 'change', function() {
			self.toggleConditionalFields();
		} );
	};

	/**
	 * Clear all plugin cache
	 */
	PerformanceSettingsManager.prototype.clearCache = function() {
		var $button = $( '#scd-clear-cache-btn' );
		var $status = $( '#scd-clear-cache-status' );
		var self = this;

		// Confirm action
		if ( ! confirm( this.config.strings && this.config.strings.confirmClearCache
			? this.config.strings.confirmClearCache
			: 'Are you sure you want to clear all plugin cache?' ) ) {
			return;
		}

		// Disable button and show loading
		$button.prop( 'disabled', true );
		$status.html( '<span class="dashicons dashicons-update-alt spin"></span> Clearing cache...' );

		// AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_clear_cache',
				nonce: this.config.nonce || ''
			},
			success: function( response ) {
				if ( response.success ) {
					$status.html( '<span style="color: #00a32a;"><span class="dashicons dashicons-yes-alt"></span> ' +
						( response.data.message || 'Cache cleared successfully!' ) + '</span>' );

					// Update cached entries count to 0
					$( '#scd-cached-entries-count' ).html( '0 items' );

					// Clear status after 3 seconds
					setTimeout( function() {
						$status.html( '' );
					}, 3000 );
				} else {
					$status.html( '<span style="color: #d63638;"><span class="dashicons dashicons-dismiss"></span> ' +
						( response.data && response.data.message ? response.data.message : 'Failed to clear cache' ) + '</span>' );
				}
			},
			error: function( xhr, status, error ) {
				$status.html( '<span style="color: #d63638;"><span class="dashicons dashicons-dismiss"></span> Error: ' +
					error + '</span>' );
			},
			complete: function() {
				// Re-enable button
				$button.prop( 'disabled', false );
			}
		} );
	};

	/**
	 * Toggle conditional fields based on dependencies
	 */
	PerformanceSettingsManager.prototype.toggleConditionalFields = function() {
		$( '.scd-conditional' ).each( function() {
			var depends = $( this ).data( 'depends' );
			var dependsField = $( '#' + depends );

			if ( dependsField.length && dependsField.is( ':checkbox' ) ) {
				if ( dependsField.is( ':checked' ) ) {
					$( this ).addClass( 'show' );
				} else {
					$( this ).removeClass( 'show' );
				}
			}
		} );
	};

	// Initialize on document ready
	$( document ).ready( function() {
		new PerformanceSettingsManager();
	} );

} )( jQuery );
