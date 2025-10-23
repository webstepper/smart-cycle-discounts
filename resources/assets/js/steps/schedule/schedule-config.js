/**
 * Schedule Configuration Module
 *
 * Centralized configuration and constants for schedule functionality
 *
 * @param window
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( window ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};

	// Safe timezone getter
	function getTimezone() {
		try {
			return ( window.scd_admin && window.scd_admin.timezone ) ||
                   ( window.scdAdmin && window.scdAdmin.timezone ) ||
                   ( window.scdWizardData && window.scdWizardData.timezone ) ||
                   'UTC';
		} catch ( e ) {
			return 'UTC';
		}
	}

	/**
	 * Schedule Configuration
	 */
	SCD.Modules.ScheduleConfig = {

		// DOM Selectors
		selectors: {
			// Form elements
			form: '#scd-wizard-schedule-form',

			// Date inputs
			startDate: '#start_date',
			endDate: '#end_date',
			startDateDisplay: '#start_date_display',
			endDateDisplay: '#end_date_display',

			// Time inputs
			startTimeDisplay: '#start_time_display',
			endTimeDisplay: '#end_time_display',
			startTimeHour: '#start_time_hour',
			startTimeMinute: '#start_time_minute',
			startTimeAmPm: '#start_time_ampm',
			endTimeHour: '#end_time_hour',
			endTimeMinute: '#end_time_minute',
			endTimeAmPm: '#end_time_ampm',

			// UI elements
			clearEndDate: '.scd-clear-end-date',
			durationDisplay: '#scd-duration-display',
			durationText: '#scd-duration-text',
			durationHint: '#scd-duration-hint',
			statusPanel: '#scd-schedule-status',
			loadingOverlay: '#scd-schedule-loading',
			saveIndicator: '#scd-save-indicator',
			announcements: '#scd-schedule-announcements',

			// Preset elements
			presetsContainer: '#scd-smart-presets',
			presetGrid: '#scd-preset-recommendations',

			// Schedule type elements
			startTypeRadios: 'input[name="start_type"]',
			scheduledStartFields: '.scd-scheduled-start-fields',
			timelineSection: '#scd-timeline-section'
		},

		// CSS Classes
		classes: {
			// Input states
			error: 'scd-input--error',
			loading: 'scd-loading',

			// Status panels
			success: 'scd-status-panel--success',
			warning: 'scd-status-panel--warning',
			errorPanel: 'scd-status-panel--error',

			// Presets
			presetActive: 'scd-preset-card--active',
			presetRecommended: 'scd-preset-card--recommended',

			// Accessibility
			srOnly: 'screen-reader-text',
			ariaLive: 'aria-live-region'
		},

		// Timing Configuration
		timing: {
			debounceDelay: 300,
			autoSaveDelay: 2000,
			feedbackDuration: 3000,
			animationDuration: 300
		},

		// Validation Rules
		validation: {
			maxDurationDays: 365,
			minDurationMinutes: 5, // 5 minutes minimum for testing purposes
			maxScheduleMonths: 12,
			dateFormat: 'YYYY-MM-DD',
			timeFormat: '12h' // or '24h'
		},

		// Default Values
		defaults: {
			startTimeHour: '12',
			startTimeMinute: '00',
			startTimeAmPm: 'AM',
			endTimeHour: '11',
			endTimeMinute: '59',
			endTimeAmPm: 'PM',
			timezone: getTimezone()
		},

		// Preset Templates - Consolidated 5-preset system
		presets: {
			durations: {
				flash: {
					days: 1,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Flash Sale', 'smart-cycle-discounts' ) : 'Flash Sale',
					icon: 'dashicons-performance',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Quick 24-hour promotion for urgency', 'smart-cycle-discounts' ) : 'Quick 24-hour promotion for urgency'
				},
				weekend: {
					days: 3,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Weekend Special', 'smart-cycle-discounts' ) : 'Weekend Special',
					icon: 'dashicons-star-filled',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Friday to Sunday promotion', 'smart-cycle-discounts' ) : 'Friday to Sunday promotion'
				},
				week: {
					days: 7,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Weekly Campaign', 'smart-cycle-discounts' ) : 'Weekly Campaign',
					icon: 'dashicons-calendar-alt',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Perfect for holidays and events', 'smart-cycle-discounts' ) : 'Perfect for holidays and events'
				},
				twoWeeks: {
					days: 14,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Extended Sale', 'smart-cycle-discounts' ) : 'Extended Sale',
					icon: 'dashicons-chart-line',
					description: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Longer promotion period', 'smart-cycle-discounts' ) : 'Longer promotion period'
				},
				monthly: {
					days: 30,
					label: ( window.wp && wp.i18n && wp.i18n.__ ) ? wp.i18n.__( 'Seasonal Campaign', 'smart-cycle-discounts' ) : 'Seasonal Campaign',
					icon: 'dashicons-palmtree',
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
			save: 'scd_save_schedule',
			validate: 'scd_validate_schedule',
			getPresets: 'scd_get_schedule_presets',
			getContext: 'scd_get_campaign_context'
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

			// Get the translated message
			var message = 'function' === typeof messageFunc ? messageFunc() : messageFunc;

			// Handle sprintf-style placeholders for wp.i18n
			if ( replacements.max !== undefined || replacements.min !== undefined ) {
				// For messages with %s placeholders
				var value = replacements.max || replacements.min;
				if ( window.wp && wp.i18n && wp.i18n.sprintf ) {
					return wp.i18n.sprintf( message, value );
				} else {
					// Fallback: simple string replacement
					return message.replace( '%s', value );
				}
			}

			// Legacy placeholder replacement for modern implementation
			var placeholder;
			for ( placeholder in replacements ) {
				if ( Object.prototype.hasOwnProperty.call( replacements, placeholder ) ) {
					message = message.replace(
						new RegExp( '{' + placeholder + '}', 'g' ),
						replacements[placeholder]
					);
				}
			}

			return message;
		}
	};

	// Register module with loader
	if ( SCD.ModuleLoader ) {
		SCD.ModuleLoader.register( 'schedule-config', SCD.Modules.ScheduleConfig );
	}

} )( window );