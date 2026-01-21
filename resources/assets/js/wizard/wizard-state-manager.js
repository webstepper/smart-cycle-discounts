/**
 * Wizard State Manager
 *
 * Centralized state management for the campaign wizard using BaseState pattern.
 * Extends WSSCD.Shared.BaseState for consistent state management with change tracking,
 * subscribers, and history support.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-state-manager.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};

	/**
	 * Wizard State Manager Constructor
	 *
	 * Extends BaseState for state management with wizard-specific functionality.
	 * Implements singleton pattern - use getInstance() to access.
	 *
	 * @class WSSCD.Wizard.StateManager
	 * @extends WSSCD.Shared.BaseState
	 */
	WSSCD.Wizard.StateManager = function() {
		// Return existing instance if already created (singleton pattern)
		if ( WSSCD.Wizard.StateManager._instance ) {
			return WSSCD.Wizard.StateManager._instance;
		}

		// Define initial state
		var initialState = {
			// Session management
			sessionValid: true,
			sessionVersion: 0,

			// Navigation state
			stepHistory: [],
			completedSteps: [],
			visitedSteps: [],
			availableSteps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],

			// Data state
			hasUnsavedChanges: false,
			isDirty: false,
			isSaving: false,
			isLoading: false,
			isTransitioning: false,

			// Validation state
			isValid: true,
			validationErrors: {},

			// Step data
			stepData: {
				basic: {},
				products: {},
				discounts: {},
				schedule: {},
				review: {}
			},

			// UI state
			sidebarCollapsed: false,
			debugMode: false,
			previewMode: false,

			// Timestamps
			startedAt: new Date().toISOString(),
			lastSavedAt: null,
			lastActivityAt: new Date().toISOString(),

			// Metadata
			campaignId: null,
			campaignName: '',
			wizardMode: 'create', // 'create' or 'edit'

			// Feature flags
			features: {
				smartSave: true,
				validation: true,
				preview: true,
				debug: window.wsscdWizardData && window.wsscdWizardData.debug
			}
		};

		// Call BaseState constructor
		if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseState ) {
			WSSCD.Shared.BaseState.call( this, initialState );
		} else {
			console.error( 'WSSCD.Shared.BaseState not found! Wizard state management will not work.' );
			return;
		}

		// Storage error tracking
		this._storageDisabled = false;
		this.storageError = null;

		// Store singleton instance
		WSSCD.Wizard.StateManager._instance = this;
	};

	// Inherit from BaseState
	if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseState ) {
		WSSCD.Wizard.StateManager.prototype = Object.create( WSSCD.Shared.BaseState.prototype );
		WSSCD.Wizard.StateManager.prototype.constructor = WSSCD.Wizard.StateManager;
	}

	// Extend prototype with wizard-specific methods
	WSSCD.Utils.extend( WSSCD.Wizard.StateManager.prototype, {

		/**
		 * Initialize state manager with campaign data
		 *
		 * @since 1.0.0
		 * @param {object} initialData - Initial wizard data
		 * @returns {WSSCD.Wizard.StateManager} this
		 */
		init: function( initialData ) {
			if ( !initialData ) {
				initialData = {};
			}

			// Merge with initial state
			if ( initialData && Object.keys( initialData ).length > 0 ) {
				// Deep merge most properties, but REPLACE stepData completely
				// This prevents snake_case keys from PHP mixing with camelCase keys from JS
				var stepData = initialData.stepData;
				delete initialData.stepData;

				var mergedState = $.extend( true, {}, this.getState(), initialData );

				// Replace stepData instead of merging
				if ( stepData ) {
					mergedState.stepData = $.extend( true, {}, stepData );
				}

				this.setState( mergedState, true ); // Silent update
			}

			var isEditMode = initialData && initialData.wizardMode === 'edit' && initialData.campaignId;

			// Check isFresh flag from PHP (set by Intent Handler when processing intent=new).
			// This is the authoritative source - URL param is stripped after redirect.
			var isFreshFromPHP = window.wsscdWizardData && window.wsscdWizardData.isFresh;

			if ( isFreshFromPHP ) {
				this.clearStorage();
			} else if ( isEditMode ) {
				// CRITICAL: When editing, don't load from sessionStorage
				// The fresh campaign data from server should take precedence
				this.clearStorage();
				if ( window.WSSCD && window.WSSCD.Debug ) {
					window.WSSCD.Debug.log( '[StateManager] Edit mode detected - using fresh campaign data from server, not sessionStorage' );
				}
			} else {
				this.loadFromStorage();
			}

			// Start activity tracking
			this.startActivityTracking();

			return this;
		},

		/**
		 * Override setState to add wizard-specific behavior
		 *
		 * @since 1.0.0
		 * @param {object} updates - State updates
		 * @param {boolean} batch - Batch update (skip notifications)
		 * @returns {void}
		 */
		setState: function( updates, batch ) {
			// Handle stepData specially to prevent key pollution
			var stepData = null;
			if ( updates.stepData ) {
				stepData = updates.stepData;
				delete updates.stepData;
			}

			// Call parent setState for normal updates
			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseState ) {
				WSSCD.Shared.BaseState.prototype.setState.call( this, updates, batch );
			}

			// Handle stepData replacement
			if ( stepData ) {
				var currentState = this.getState();
				currentState.stepData = $.extend( true, {}, currentState.stepData || {}, stepData );
				this._state = currentState;

				// Mark as dirty when stepData changes
				if ( !batch ) {
					WSSCD.Shared.BaseState.prototype.setState.call( this, {
						hasUnsavedChanges: true,
						isDirty: true
					}, false );
				}
			}

			// Update activity timestamp
			this._state.lastActivityAt = new Date().toISOString();

			// Persist to storage
			if ( !batch ) {
				this.saveToStorage();
			}
		},

		/**
		 * Get state value with dot notation support
		 *
		 * Extends BaseState.getState() with dot notation for nested access.
		 *
		 * @since 1.0.0
		 * @param {string} key - State key (supports dot notation: 'stepData.basic.name')
		 * @returns {*} State value
		 */
		get: function( key ) {
			if ( !key ) {
				return this.getState();
			}

			// Support dot notation
			var keys = key.split( '.' );
			var value = this.getState();

			for ( var i = 0; i < keys.length; i++ ) {
				if ( value && 'object' === typeof value && keys[i] in value ) {
					value = value[keys[i]];
				} else {
					return undefined;
				}
			}

			// Return copy for objects/arrays
			if ( 'object' === typeof value && null !== value ) {
				return $.extend( true, {}, value );
			}

			return value;
		},

		/**
		 * Set state value with dot notation support
		 *
		 * Convenience wrapper around setState() with additional features:
		 * - Dot notation support: set('stepData.basic.name', 'value')
		 * - Silent mode: set(updates, {silent: true}) for batch operations
		 *
		 * @since 1.0.0
		 * @param {string|object} updates - Key (dot notation) or object of updates
		 * @param {*} value - Value (if updates is a string key)
		 * @param {object} options - Options: {silent: boolean} (if updates is a string key)
		 * @returns {object} Updated state
		 */
		set: function( updates, value, options ) {
			// Handle single key-value or object
			if ( 'string' === typeof updates ) {
				var key = updates;
				var updateObj = {};
				this._setNestedValue( updateObj, key, value );
				options = options || {};
				this.setState( updateObj, options.silent );
			} else {
				options = value || {};
				this.setState( updates, options.silent );
			}

			return this.getState();
		},

		/**
		 * Set nested value using dot notation
		 *
		 * @private
		 * @since 1.0.0
		 * @param {object} obj - Object to set value in
		 * @param {string} path - Dot notation path
		 * @param {*} value - Value to set
		 * @returns {void}
		 */
		_setNestedValue: function( obj, path, value ) {
			var keys = path.split( '.' );
			var current = obj;

			for ( var i = 0; i < keys.length - 1; i++ ) {
				if ( !( keys[i] in current ) ) {
					current[keys[i]] = {};
				}
				current = current[keys[i]];
			}

			current[keys[keys.length - 1]] = value;
		},

		/**
		 * Reset state to defaults
		 *
		 * @since 1.0.0
		 * @param {object} options - Reset options
		 * @returns {void}
		 */
		reset: function( options ) {
			options = options || {};

			var defaultState = {
				stepHistory: [],
				completedSteps: [],
				visitedSteps: [],
				hasUnsavedChanges: false,
				isDirty: false,
				stepData: {
					basic: {},
					products: {},
					discounts: {},
					schedule: {},
					review: {}
				}
			};

			this.setState( defaultState, options.silent );

			// Reset history
			if ( this._history ) {
				this._history = [ this.getState() ];
				this._historyIndex = 0;
			}

			if ( !options.keepStorage ) {
				this.clearStorage();
			}
		},

		/**
		 * Load state from sessionStorage
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		loadFromStorage: function() {
			if ( !window.sessionStorage ) {
				return;
			}

			try {
				var stored = sessionStorage.getItem( 'wsscd_wizard_state' );
				if ( stored ) {
					var parsedState = JSON.parse( stored );
					this.setState( parsedState, true ); // Silent update
				}
			} catch ( e ) {
				if ( window.WSSCD && window.WSSCD.Debug ) {
					window.WSSCD.Debug.error( 'Failed to load state from storage:', e );
				}
			}
		},

		/**
		 * Save state to sessionStorage
		 *
		 * @since 1.0.0
		 * @returns {boolean} Success status
		 */
		saveToStorage: function() {
			if ( !window.sessionStorage ) {
				this.handleStorageError( 'sessionStorage not available' );
				this._storageDisabled = true;
				return false;
			}

			try {
				// Safely serialize state with circular reference detection
				var seen = [];
				var stateString = JSON.stringify( this.getState(), function( key, value ) {
					if ( 'function' === typeof value || 'undefined' === typeof value ) {
						return;
					}
					// Detect circular references
					if ( 'object' === typeof value && null !== value ) {
						if ( -1 !== seen.indexOf( value ) ) {
							return '[Circular Reference]';
						}
						seen.push( value );
					}
					return value;
				} );
				seen = null; // Clear for garbage collection

				sessionStorage.setItem( 'wsscd_wizard_state', stateString );

				this.clearStorageError();
				this._storageDisabled = false;
				return true;
			} catch ( e ) {
				// Handle quota exceeded or other storage errors
				var errorMessage = 'Failed to save state: ';

				if ( 'QuotaExceededError' === e.name || 'NS_ERROR_DOM_QUOTA_REACHED' === e.name ) {
					errorMessage += 'Storage quota exceeded';

					// Try to clear old data and retry once
					try {
						this.clearOldStorageData();
						sessionStorage.setItem( 'wsscd_wizard_state', stateString );
						this.clearStorageError();
						this._storageDisabled = false;
						return true;
					} catch ( retryError ) {
						// CRITICAL: Storage still full after cleanup
						this._storageDisabled = true;

						if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
							window.WSSCD.Shared.NotificationService.error(
								'Storage full! Your data may be lost if you refresh. Please save your campaign immediately.',
								{ duration: 0 } // Persistent notification
							);
						} else if ( window.alert ) {
							// Fallback if notification service unavailable
							alert( 'Warning: Local storage is full. Please save your campaign to avoid data loss.' );
						}
					}
				} else {
					errorMessage += e.message || 'Unknown error';
					this._storageDisabled = true;
				}

				this.handleStorageError( errorMessage );
				return false;
			}
		},

		/**
		 * Handle storage errors
		 *
		 * @since 1.0.0
		 * @param {string} message - Error message
		 * @returns {void}
		 */
		handleStorageError: function( message ) {
			// Notify UI about storage failure
			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				window.WSSCD.Shared.NotificationService.warning(
					'Your progress may not be saved locally. Server saves are still working.',
					{ duration: 3000 }
				);
			}

			this.storageError = {
				message: message,
				timestamp: Date.now()
			};

			// Trigger event for other components
			$( document ).trigger( 'wsscd:storage:error', [ message ] );
		},

		/**
		 * Clear storage error state
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		clearStorageError: function() {
			this.storageError = null;
			$( document ).trigger( 'wsscd:storage:recovered' );
		},

		/**
		 * Clear old storage data to free up space
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		clearOldStorageData: function() {
			if ( !window.sessionStorage ) {
				return;
			}

			var keysToRemove = [];
			var i, key;

			for ( i = 0; i < sessionStorage.length; i++ ) {
				key = sessionStorage.key( i );

				if ( key && 0 === key.indexOf( 'wsscd_' ) && 'wsscd_wizard_state' !== key ) {
					keysToRemove.push( key );
				}
			}

			for ( i = 0; i < keysToRemove.length; i++ ) {
				sessionStorage.removeItem( keysToRemove[i] );
			}
		},

		/**
		 * Clear storage
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		clearStorage: function() {
			if ( !window.sessionStorage ) {
				return;
			}

			try {
				sessionStorage.removeItem( 'wsscd_wizard_state' );
				this.clearStorageError();
			} catch ( e ) {
				// Handle any storage errors silently
			}
		},

		/**
		 * Start activity tracking
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		startActivityTracking: function() {
			var self = this;

			// Track mouse/keyboard activity
			var updateActivity = function() {
				self.setState( { lastActivityAt: new Date().toISOString() }, true ); // Silent update
			};

			// Native throttle implementation (no Underscore.js dependency)
			var throttleTimer = null;
			var lastRun = 0;
			var throttledUpdate = function() {
				var now = Date.now();
				var timeSinceLastRun = now - lastRun;

				if ( timeSinceLastRun >= 30000 ) {
					lastRun = now;
					updateActivity();
				} else {
					if ( throttleTimer ) {
						clearTimeout( throttleTimer );
					}
					throttleTimer = setTimeout( function() {
						lastRun = Date.now();
						updateActivity();
						throttleTimer = null;
					}, 30000 - timeSinceLastRun );
				}
			};

			$( document ).on( 'mousemove.wsscd-activity keypress.wsscd-activity', throttledUpdate );
		},

		/**
		 * Stop activity tracking
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		stopActivityTracking: function() {
			$( document ).off( '.wsscd-activity' );
		},

		/**
		 * Destroy state manager and cleanup
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			// Stop activity tracking
			this.stopActivityTracking();

			// Clear singleton instance
			WSSCD.Wizard.StateManager._instance = null;

			// Call parent destroy if available
			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseState && WSSCD.Shared.BaseState.prototype.destroy ) {
				WSSCD.Shared.BaseState.prototype.destroy.call( this );
			}
		}
	} );

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @returns {WSSCD.Wizard.StateManager} Singleton instance
	 */
	WSSCD.Wizard.StateManager.getInstance = function() {
		if ( !WSSCD.Wizard.StateManager._instance ) {
			WSSCD.Wizard.StateManager._instance = new WSSCD.Wizard.StateManager();
		}
		return WSSCD.Wizard.StateManager._instance;
	};

} )( jQuery );
