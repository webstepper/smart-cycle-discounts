/**
 * Schedule Debug
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/schedule/schedule-debug.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( _$ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Schedule = SCD.Modules.Schedule || {};

	/**
	 * Debug utility for schedule step
	 */
	SCD.Modules.Schedule.Debug = {
		// Debug mode flag - can be toggled via console or global setting
		enabled: window.scdScheduleDebug || false,

		// Log styling
		styles: {
			module: 'background: #2196F3; color: white; padding: 2px 4px; border-radius: 2px',
			success: 'background: #4CAF50; color: white; padding: 2px 4px; border-radius: 2px',
			warning: 'background: #FF9800; color: white; padding: 2px 4px; border-radius: 2px',
			error: 'background: #F44336; color: white; padding: 2px 4px; border-radius: 2px',
			info: 'background: #9C27B0; color: white; padding: 2px 4px; border-radius: 2px',
			data: 'background: #607D8B; color: white; padding: 2px 4px; border-radius: 2px'
		},

		/**
		 * Enable debug mode
		 */
		enable: function() {
			this.enabled = true;
			window.scdScheduleDebug = true;
			this.log( 'info', 'Debug', 'To disable: SCD.Modules.Schedule.Debug.disable()' );
		},

		/**
		 * Disable debug mode
		 */
		disable: function() {
			this.enabled = false;
			window.scdScheduleDebug = false;
		},

		/**
		 * Toggle debug mode
		 */
		toggle: function() {
			if ( this.enabled ) {
				this.disable();
			} else {
				this.enable();
			}
		},

		/**
		 * Log message with formatting
		 *
		 * @param {string} type - Type of log (info, success, warning, error, data)
		 * @param {string} module - Module name
		 * @param {string} message - Log message
		 * @param {*} data - Optional data to log
		 */
		log: function( type, module, message, data ) {
			if ( !this.enabled ) {return;}

			var _style = this.styles[type] || this.styles.info;
			var _prefix = '%c' + module;
			var _timestamp = new Date().toISOString().substr( 11, 8 );

			if ( data !== undefined ) {
			} else {
			}
		},

		/**
		 * Log module initialization
		 * @param module
		 * @param config
		 */
		logInit: function( module, config ) {
			this.log( 'module', module, 'Initializing...', config );
		},

		/**
		 * Log state change
		 * @param module
		 * @param changes
		 * @param oldValues
		 */
		logStateChange: function( module, changes, oldValues ) {
			this.log( 'data', module + ' State', 'State changed:', {
				changes: changes,
				previous: oldValues
			} );
		},

		/**
		 * Log API call
		 * @param method
		 * @param endpoint
		 * @param data
		 */
		logApiCall: function( method, endpoint, data ) {
			this.log( 'info', 'API', method + ' ' + endpoint, data );
		},

		/**
		 * Log validation
		 * @param field
		 * @param value
		 * @param result
		 */
		logValidation: function( field, value, result ) {
			var type = result.valid ? 'success' : 'warning';
			this.log( type, 'Validation', field + ' validation:', {
				value: value,
				result: result
			} );
		},

		/**
		 * Log event
		 * @param eventName
		 * @param data
		 */
		logEvent: function( eventName, data ) {
			this.log( 'info', 'Event', eventName, data );
		},

		/**
		 * Log error
		 * @param module
		 * @param error
		 * @param context
		 */
		logError: function( module, error, context ) {
			this.log( 'error', module, error.message || error, {
				error: error,
				context: context,
				stack: error.stack
			} );
		},

		/**
		 * Create a group for related logs
		 * @param label
		 */
		group: function( label ) {
			if ( !this.enabled ) {return;}
			console.group( '📋 ' + label );
		},

		/**
		 * End a group
		 */
		groupEnd: function() {
			if ( !this.enabled ) {return;}
			console.groupEnd();
		},

		/**
		 * Log performance timing
		 * @param label
		 */
		time: function( label ) {
			if ( !this.enabled ) {return;}
			console.time( '⏱️ ' + label );
		},

		/**
		 * End performance timing
		 * @param label
		 */
		timeEnd: function( label ) {
			if ( !this.enabled ) {return;}
			console.timeEnd( '⏱️ ' + label );
		},

		/**
		 * Log current state snapshot
		 * @param module
		 * @param data
		 */
		logSnapshot: function( module, data ) {
			if ( !this.enabled ) {return;}

			console.group( '📸 ' + module + ' Snapshot' );
			console.table( data );
			console.groupEnd();
		}
	};

	// Auto-enable if debug flag is set
	if ( window.scdScheduleDebug ) {
		SCD.Modules.Schedule.Debug.enable();
	}

	// Expose debug commands to console
	window.scheduleDebug = {
		enable: function() { SCD.Modules.Schedule.Debug.enable(); },
		disable: function() { SCD.Modules.Schedule.Debug.disable(); },
		toggle: function() { SCD.Modules.Schedule.Debug.toggle(); }
	};

} )( jQuery );