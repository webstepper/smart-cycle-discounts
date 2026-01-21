/**
 * Settings General
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/settings-general.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * General Settings Manager
	 */
	function GeneralSettingsManager() {
		this.config = window.wsscdSettingsGeneral || {};
		this.init();
	}

	/**
	 * Initialize the settings manager
	 */
	GeneralSettingsManager.prototype.init = function() {
		this.bindEvents();
		this.toggleConditionalFields();
	};

	/**
	 * Bind event listeners
	 */
	GeneralSettingsManager.prototype.bindEvents = function() {
		var self = this;

		// Handle checkbox changes for conditional fields
		$( 'input[type="checkbox"]' ).on( 'change', function() {
			self.toggleConditionalFields();
		} );

		$( '#reset-settings' ).on( 'click', function() {
			self.resetToDefaults();
		} );
	};

	/**
	 * Toggle conditional fields based on dependencies
	 */
	GeneralSettingsManager.prototype.toggleConditionalFields = function() {
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
	 * Reset settings to defaults
	 */
	GeneralSettingsManager.prototype.resetToDefaults = function() {
		if ( confirm( this.config.strings.confirmReset ) ) {
			var defaults = this.config.defaults || {};

			$( '#enable_plugin' ).prop( 'checked', defaults.enable_plugin );
			$( '#default_discount_type' ).val( defaults.default_discount_type );
			$( '#default_priority' ).val( defaults.default_priority );
			$( '#enable_analytics' ).prop( 'checked', defaults.enable_analytics );
			$( '#enable_logging' ).prop( 'checked', defaults.enable_logging );
			$( '#log_level' ).val( defaults.log_level );
			$( '#cache_duration' ).val( defaults.cache_duration );
			$( '#enable_frontend_display' ).prop( 'checked', defaults.enable_frontend_display );
			$( '#discount_badge_position' ).val( defaults.discount_badge_position );
			$( '#countdown_timer_style' ).val( defaults.countdown_timer_style );
			$( '#enable_email_notifications' ).prop( 'checked', defaults.enable_email_notifications );
			$( '#admin_email_notifications' ).prop( 'checked', defaults.admin_email_notifications );
			$( '#customer_email_notifications' ).prop( 'checked', defaults.customer_email_notifications );
			$( '#currency_position' ).val( defaults.currency_position );
			$( '#decimal_places' ).val( defaults.decimal_places );
			$( '#enable_debug_mode' ).prop( 'checked', defaults.enable_debug_mode );
			$( '#cleanup_data_on_uninstall' ).prop( 'checked', defaults.cleanup_data_on_uninstall );

			this.toggleConditionalFields();
		}
	};

	$( document ).ready( function() {
		new GeneralSettingsManager();
	} );

} )( jQuery );
