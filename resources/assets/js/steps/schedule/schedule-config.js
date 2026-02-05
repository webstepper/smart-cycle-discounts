/**
 * Schedule Config
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/schedule/schedule-config.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( window ) {
	'use strict';

	// Ensure namespace exists
	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};

	// Safe timezone getter
	function getTimezone() {
		try {
			return ( window.wsscd_admin && window.wsscd_admin.timezone ) ||
                   ( window.wsscdAdmin && window.wsscdAdmin.timezone ) ||
                   ( window.wsscdWizardData && window.wsscdWizardData.timezone ) ||
                   'UTC';
		} catch ( e ) {
			return 'UTC';
		}
	}

	/**
	 * Schedule Configuration
	 */
	WSSCD.Modules.ScheduleConfig = {

		// DOM Selectors
		selectors: {
			// Form elements
			form: '#wsscd-wizard-schedule-form',

			// Date inputs
			startDate: '#start_date',
			endDate: '#end_date',
			startDateDisplay: '#start_date_display',
			endDateDisplay: '#end_date_display',

			// Time inputs (hidden values; display is custom dropdown)
			startTime: '#start_time',
			endTime: '#end_time',

			// UI elements
			clearEndDate: '.wsscd-clear-end-date',
			durationDisplay: '#wsscd-duration-display',
			durationText: '#wsscd-duration-text',
			durationHint: '#wsscd-duration-hint',
			statusPanel: '#wsscd-schedule-status',
			loadingOverlay: '#wsscd-schedule-loading',
			saveIndicator: '#wsscd-save-indicator',
			announcements: '#wsscd-schedule-announcements',

			// Preset elements
			presetsContainer: '#wsscd-preset-recommendations',
			presetGrid: '#wsscd-preset-recommendations',

			// Schedule type elements
			startTypeRadios: 'input[name="start_type"]',
			scheduledStartFields: '.wsscd-scheduled-start-fields',
			timelineSection: '#wsscd-timeline-section'
		},

		// CSS Classes
		classes: {
			// Input states
			error: 'error',
			loading: 'wsscd-loading',

			// Status panels
			success: 'wsscd-status-panel--success',
			warning: 'wsscd-status-panel--warning',
			errorPanel: 'wsscd-status-panel--error',

			// Presets
			presetActive: 'wsscd-preset-card--active',
			presetRecommended: 'wsscd-preset-card--recommended',

			// Accessibility
			srOnly: 'screen-reader-text',
			ariaLive: 'aria-live-region'
		},

		// Timing Configuration
		timing: {
			debounceDelay: 300,
			feedbackDuration: 3000,
			animationDuration: 300
		},

		// Validation Rules
		validation: {
			maxDurationDays: 365,
			minDurationMinutes: 5, // 5 minutes minimum for testing purposes
			maxScheduleMonths: 12,
			dateFormat: 'YYYY-MM-DD',
			timeFormat: '24h' // Backend value format (HH:mm); display uses WordPress time_format
		},

		// Default Values (24h HH:mm)
		defaults: {
			startTime: '00:00',
			endTime: '23:59',
			timezone: getTimezone()
		},

		// Preset Templates - Consolidated 5-preset system
		presets: {
			durations: {
				flash: {
					days: 1,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Flash Sale', 'smart-cycle-discounts' ) : 'Flash Sale',
					icon: 'performance',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Quick 24-hour promotion for urgency', 'smart-cycle-discounts' ) : 'Quick 24-hour promotion for urgency'
				},
				weekend: {
					days: 3,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Weekend Special', 'smart-cycle-discounts' ) : 'Weekend Special',
					icon: 'star-filled',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Friday to Sunday promotion', 'smart-cycle-discounts' ) : 'Friday to Sunday promotion'
				},
				week: {
					days: 7,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Weekly Campaign', 'smart-cycle-discounts' ) : 'Weekly Campaign',
					icon: 'calendar',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Perfect for holidays and events', 'smart-cycle-discounts' ) : 'Perfect for holidays and events'
				},
				twoWeeks: {
					days: 14,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Extended Sale', 'smart-cycle-discounts' ) : 'Extended Sale',
					icon: 'chart-line',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Longer promotion period', 'smart-cycle-discounts' ) : 'Longer promotion period'
				},
				monthly: {
					days: 30,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Seasonal Campaign', 'smart-cycle-discounts' ) : 'Seasonal Campaign',
					icon: 'palmtree',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Month-long seasonal promotion', 'smart-cycle-discounts' ) : 'Month-long seasonal promotion'
				}
			}
		},

		// Error Messages using wp.i18n
		messages: {
			errors: {
				invalidDate: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Please enter a valid date ( YYYY-MM-DD format )', 'smart-cycle-discounts' ) : 'Please enter a valid date ( YYYY-MM-DD format )'; },
				pastDate: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Start date cannot be in the past', 'smart-cycle-discounts' ) : 'Start date cannot be in the past'; },
				endBeforeStart: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'End date must be after start date', 'smart-cycle-discounts' ) : 'End date must be after start date'; },
				tooLong: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Campaign duration cannot exceed %s days', 'smart-cycle-discounts' ) : 'Campaign duration cannot exceed %s days'; },
				tooShort: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Campaign must be at least %s minutes', 'smart-cycle-discounts' ) : 'Campaign must be at least %s minutes'; },
				required: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'This field is required', 'smart-cycle-discounts' ) : 'This field is required'; },
				invalidTime: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Please enter time in HH:MM format ( e.g., 14:30 or 2:30 PM )', 'smart-cycle-discounts' ) : 'Please enter time in HH:MM format ( e.g., 14:30 or 2:30 PM )';
				}
			},

			success: {
				saved: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Schedule saved successfully', 'smart-cycle-discounts' ) : 'Schedule saved successfully'; },
				validated: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Schedule is valid', 'smart-cycle-discounts' ) : 'Schedule is valid'; },
				presetApplied: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Preset applied successfully', 'smart-cycle-discounts' ) : 'Preset applied successfully'; }
			},

			warnings: {
				unsavedChanges: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'You have unsaved changes', 'smart-cycle-discounts' ) : 'You have unsaved changes'; },
				longDuration: function() { return ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'This is a long campaign. Consider breaking it into smaller campaigns.', 'smart-cycle-discounts' ) : 'This is a long campaign. Consider breaking it into smaller campaigns.'; }
			}
		},

		// API Endpoints
		endpoints: {
			save: 'wsscd_save_schedule',
			validate: 'wsscd_validate_schedule',
			getPresets: 'wsscd_get_schedule_presets',
			getContext: 'wsscd_get_campaign_context'
		},

		/**
		 * Get configuration value
		 *
		 * @param {string} path Dot-notation path ( e.g., 'selectors.form' )
		 * @param {*} defaultValue Default value if not found
		 * @returns {*} Configuration value
		 */
		get: function( path, defaultValue ) {
			if ( 'undefined' === typeof defaultValue ) {
				defaultValue = null;
			}

			var keys = path.split( '.' );
			var value = this;
			var i;

			for ( i = 0; i < keys.length; i++ ) {
				var key = keys[i];
				if ( value && 'object' === typeof value && key in value ) {
					value = value[key];
				} else {
					return defaultValue;
				}
			}

			return value;
		},

		/**
		 * Set configuration value
		 *
		 * @param {string} path Dot-notation path
		 * @param {*} value Value to set
		 */
		set: function( path, value ) {
			var keys = path.split( '.' );
			var lastKey = keys.pop();
			var target = this;

			var i;
			for ( i = 0; i < keys.length; i++ ) {
				var key = keys[i];
				if ( !( key in target ) ) {
					target[key] = {};
				}
				target = target[key];
			}

			target[lastKey] = value;
		},

		/**
		 * Get all selectors
		 *
		 * @returns {object} All selectors
		 */
		getSelectors: function() {
			return this.selectors;
		},

		/**
		 * Get all classes
		 *
		 * @returns {object} All classes
		 */
		getClasses: function() {
			return this.classes;
		},

		/**
		 * Get validation rules
		 *
		 * @returns {object} Validation rules
		 */
		getValidationRules: function() {
			return this.validation;
		},

		/**
		 * Get error message
		 *
		 * @param {string} key Message key
		 * @param {object} replacements Placeholder replacements
		 * @returns {string} Formatted message
		 */
		getMessage: function( key, replacements ) {
			if ( !replacements ) {
				replacements = {};
			}
			var messageFunc = this.get( 'messages.' + key );

			// If not found, return empty string
			if ( !messageFunc ) {
				return '';
			}

			var message = 'function' === typeof messageFunc ? messageFunc() : messageFunc;

			// Handle sprintf-style placeholders for wp.i18n
			if ( replacements.max !== undefined || replacements.min !== undefined ) {
				// For messages with %s placeholders
				var value = replacements.max || replacements.min;
				if ( window.wp && wp.i18n && wp.i18n.sprintf ) {
					return wp.i18n.sprintf( message, value );
				}
				// Fallback: simple string replacement
				return message.replace( '%s', value );
			}

			return message;
		}
	};

	// Register module with loader
	if ( WSSCD.ModuleLoader ) {
		WSSCD.ModuleLoader.register( 'schedule-config', WSSCD.Modules.ScheduleConfig );
	}

} )( window );