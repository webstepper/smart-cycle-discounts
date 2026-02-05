/**
 * Settings Advanced
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/settings-advanced.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Advanced Settings Manager
	 */
	function AdvancedSettingsManager() {
		this.config = window.wsscdSettingsAdvanced || {};
		this.init();
	}

	/**
	 * Initialize the settings manager
	 */
	AdvancedSettingsManager.prototype.init = function() {
		this.bindEvents();
		this.toggleConditionalFields();
	};

	/**
	 * Bind event listeners
	 */
	AdvancedSettingsManager.prototype.bindEvents = function() {
		var self = this;

		// Handle checkbox changes for conditional fields
		$( 'input[type="checkbox"]' ).on( 'change', function() {
			self.toggleConditionalFields();
		} );

		// Regenerate webhook secret
		$( '#regenerate-webhook-secret' ).on( 'click', function() {
			self.regenerateWebhookSecret();
		} );

		$( '#reset-advanced-settings' ).on( 'click', function() {
			self.resetToDefaults();
		} );

		// Export settings
		$( '#export-settings' ).on( 'click', function() {
			self.exportSettings();
		} );
	};

	/**
	 * Toggle conditional fields based on dependencies
	 */
	AdvancedSettingsManager.prototype.toggleConditionalFields = function() {
		$( '.wsscd-conditional' ).each( function() {
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

	/**
	 * Regenerate webhook secret
	 */
	AdvancedSettingsManager.prototype.regenerateWebhookSecret = function() {
		if ( confirm( this.config.strings.confirmRegenerateWebhook ) ) {
			var newSecret = this.generateRandomString( 32 );
			$( '#webhook_secret' ).val( newSecret );
		}
	};

	/**
	 * Generate random string
	 *
	 * @param {number} length - Length of the string
	 * @returns {string} Random string
	 */
	AdvancedSettingsManager.prototype.generateRandomString = function( length ) {
		var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		var result = '';
		for ( var i = 0; i < length; i++ ) {
			result += chars.charAt( Math.floor( Math.random() * chars.length ) );
		}
		return result;
	};

	/**
	 * Reset settings to defaults
	 */
	AdvancedSettingsManager.prototype.resetToDefaults = function() {
		if ( confirm( this.config.strings.confirmReset ) ) {
			var defaults = this.config.defaults || {};

			$( '#enable_webhooks' ).prop( 'checked', defaults.enable_webhooks );
			$( '#enable_database_optimization' ).prop( 'checked', defaults.enable_database_optimization );
			$( '#auto_cleanup_logs' ).prop( 'checked', defaults.auto_cleanup_logs );
			$( '#log_retention_days' ).val( defaults.log_retention_days );
			$( '#enable_query_optimization' ).prop( 'checked', defaults.enable_query_optimization );
			$( '#enable_object_cache' ).prop( 'checked', defaults.enable_object_cache );
			$( '#cache_compression' ).prop( 'checked', defaults.cache_compression );
			$( '#enable_cdn_support' ).prop( 'checked', defaults.enable_cdn_support );
			$( '#cdn_url' ).val( defaults.cdn_url );
			$( '#enable_custom_css' ).prop( 'checked', defaults.enable_custom_css );
			$( '#custom_css' ).val( defaults.custom_css );
			$( '#enable_custom_js' ).prop( 'checked', defaults.enable_custom_js );
			$( '#custom_js' ).val( defaults.custom_js );
			$( '#enable_advanced_security' ).prop( 'checked', defaults.enable_advanced_security );
			$( '#security_headers' ).prop( 'checked', defaults.security_headers );
			$( '#enable_ip_blocking' ).prop( 'checked', defaults.enable_ip_blocking );
			$( '#blocked_ips' ).val( defaults.blocked_ips );
			$( '#enable_advanced_analytics' ).prop( 'checked', defaults.enable_advanced_analytics );
			$( '#analytics_retention_days' ).val( defaults.analytics_retention_days );
			$( '#enable_a_b_testing' ).prop( 'checked', defaults.enable_a_b_testing );
			$( '#enable_export_import' ).prop( 'checked', defaults.enable_export_import );
			$( '#enable_backup_restore' ).prop( 'checked', defaults.enable_backup_restore );
			$( '#backup_frequency' ).val( defaults.backup_frequency );

			this.toggleConditionalFields();
		}
	};

	/**
	 * Export settings to JSON
	 */
	AdvancedSettingsManager.prototype.exportSettings = function() {
		var settings = {};

		// Collect all form values
		$( 'input, select, textarea' ).each( function() {
			var $this = $( this );
			var name = $this.attr( 'name' );

			if ( name && name !== 'wsscd_advanced_settings_nonce' && name !== '_wp_http_referer' ) {
				if ( $this.is( ':checkbox' ) ) {
					settings[name] = $this.is( ':checked' );
				} else {
					settings[name] = $this.val();
				}
			}
		} );

		var dataStr = JSON.stringify( settings, null, 2 );
		var dataBlob = new Blob( [ dataStr ], { type: 'application/json' } );
		var url = URL.createObjectURL( dataBlob );
		var link = document.createElement( 'a' );
		link.href = url;
		link.download = 'wsscd-advanced-settings-' + new Date().toISOString().split( 'T' )[0] + '.json';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		URL.revokeObjectURL( url );
	};

	$( document ).ready( function() {
		new AdvancedSettingsManager();
	} );

} )( jQuery );
