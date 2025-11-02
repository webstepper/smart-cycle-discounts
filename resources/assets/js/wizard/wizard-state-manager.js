/**
 * Wizard State Manager
 *
 * Centralized state management for the wizard.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Wizard = window.SCD.Wizard || {};

	/**
	 * State Manager
	 *
	 * Manages all wizard state with persistence and change tracking
	 */
	SCD.Wizard.StateManager = {
		// Complete application state
		state: {
			// Session management - Session ID no longer needed (handled server-side via secure cookies)
			sessionValid: true,
			sessionVersion: 0,

			// Navigation state - removed, URL is single source of truth
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
				smartSave: true,  // Replaced autoSave with Smart Save
				validation: true,
				preview: true,
				debug: window.scdWizardData && window.scdWizardData.debug
			}
		},

		// State change listeners
		listeners: [],

		// State history for undo/redo
		history: [],
		historyIndex: -1,
		maxHistorySize: 50,

		/**
		 * Initialize state manager
		 * @param initialState
		 */
		init: function( initialState ) {
			// Merge with initial state
			if ( initialState ) {
				// Deep merge most properties, but REPLACE stepData completely
				// This prevents snake_case keys from PHP mixing with camelCase keys from JS
				var stepData = initialState.stepData;
				delete initialState.stepData;

				this.state = $.extend( true, {}, this.state, initialState );

				// Replace stepData instead of merging
				if ( stepData ) {
					this.state.stepData = $.extend( true, {}, stepData );
				}
			}

			// Allow configuration of history size
			if ( initialState && initialState.maxHistorySize ) {
				this.maxHistorySize = initialState.maxHistorySize;
			}

			// Check if intent is to start fresh OR if we're loading fresh edit data
			var isEditMode = initialState && initialState.wizardMode === 'edit' && initialState.campaignId;
			var isNewIntent = window.scdWizardSessionInfo && 'new' === window.scdWizardSessionInfo.intent;

			if ( isNewIntent ) {
				// Clear session storage when starting fresh
				this.clearStorage();
			} else if ( isEditMode ) {
				// CRITICAL: When editing, don't load from sessionStorage
				// The fresh campaign data from server should take precedence
				// Clear old session data to prevent stale wizardMode/campaignId
				this.clearStorage();
				if ( window.SCD && window.SCD.Debug ) {
					window.SCD.Debug.log( '[StateManager] Edit mode detected - using fresh campaign data from server, not sessionStorage' );
				}
			} else {
				// Load from session storage if available (for new campaigns in progress)
				this.loadFromStorage();
			}

			// Start activity tracking
			this.startActivityTracking();

			// Initialize history
			this.addToHistory( this.state );

			return this;
		},

		/**
		 * Get state value
		 * @param key
		 */
		get: function( key ) {
			if ( !key ) {
				return $.extend( true, {}, this.state );
			}

			// Support dot notation
			var keys = key.split( '.' );
			var value = this.state;

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
		 * Set state value
		 * @param updates
		 * @param options
		 */
		set: function( updates, options ) {
			options = options || {};
			var oldState = $.extend( true, {}, this.state );

			// Handle single key-value or object
			if ( 'string' === typeof updates ) {
				var key = updates;
				var value = arguments[1];
				updates = {};
				this.setNestedValue( updates, key, value );
				options = arguments[2] || {};
			}

			// Merge updates, but REPLACE stepData to prevent key pollution
			if ( updates.stepData ) {
				var stepData = updates.stepData;
				delete updates.stepData;

				this.state = $.extend( true, {}, this.state, updates );

				// Replace entire stepData object instead of deep merging
				// This prevents snake_case/camelCase key conflicts
				this.state.stepData = $.extend( true, {}, this.state.stepData || {}, stepData );
			} else {
				// Normal deep merge for non-stepData updates
				this.state = $.extend( true, {}, this.state, updates );
			}

			// Update timestamps
			this.state.lastActivityAt = new Date().toISOString();
			if ( updates.stepData && !options.silent ) {
				this.state.hasUnsavedChanges = true;
				this.state.isDirty = true;
			}

			// Add to history if not silent
			if ( !options.silent && !options.skipHistory ) {
				this.addToHistory( this.state );
			}

			// Notify listeners if not silent
			if ( !options.silent ) {
				this.notifyListeners( this.state, oldState, updates );
			}

			// Persist to storage
			if ( !options.skipStorage ) {
				this.saveToStorage();
			}

			return this.state;
		},

		/**
		 * Set nested value using dot notation
		 * @param obj
		 * @param path
		 * @param value
		 */
		setNestedValue: function( obj, path, value ) {
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
		 * Subscribe to state changes
		 * @param listener
		 * @param filter
		 */
		subscribe: function( listener, filter ) {
			if ( 'function' !== typeof listener ) {
				return;
			}

			this.listeners.push( {
				callback: listener,
				filter: filter // Optional filter for specific state keys
			} );

			// Return unsubscribe function
			var self = this;
			return function() {
				self.listeners = self.listeners.filter( function( l ) {
					return l.callback !== listener;
				} );
			};
		},

		/**
		 * Notify listeners of state change
		 * @param newState
		 * @param oldState
		 * @param updates
		 */
		notifyListeners: function( newState, oldState, updates ) {
			this.listeners.forEach( function( listener ) {
				// Check if listener has filter
				if ( listener.filter ) {
					var shouldNotify = false;

					if ( 'string' === typeof listener.filter ) {
						shouldNotify = listener.filter in updates;
					} else if ( Array.isArray( listener.filter ) ) {
						shouldNotify = listener.filter.some( function( key ) {
							return key in updates;
						} );
					}

					if ( !shouldNotify ) {
						return;
					}
				}

				// Call listener
				try {
					listener.callback( newState, oldState, updates );
				} catch ( error ) {
					if ( window.SCD && window.SCD.Debug ) {
						window.SCD.Debug.error( 'State listener error:', error );
					}
				}
			} );
		},

		/**
		 * Add state to history
		 * @param state
		 */
		addToHistory: function( state ) {
			// Remove any states after current index
			this.history = this.history.slice( 0, this.historyIndex + 1 );

			// Add new state
			this.history.push( $.extend( true, {}, state ) );
			this.historyIndex++;

			// Limit history size
			if ( this.history.length > this.maxHistorySize ) {
				this.history.shift();
				this.historyIndex--;
			}
		},

		/**
		 * Reset state
		 * @param options
		 */
		reset: function( options ) {
			options = options || {};

			var defaultState = {
				// Session ID no longer needed - managed server-side
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

			this.set( defaultState, {
				silent: options.silent,
				skipHistory: true
			} );

			// Clear history
			this.history = [ this.state ];
			this.historyIndex = 0;

			// Clear storage
			if ( !options.keepStorage ) {
				this.clearStorage();
			}
		},

		/**
		 * Load state from storage
		 */
		loadFromStorage: function() {
			// Check sessionStorage availability first
			if ( !window.sessionStorage ) {
				return;
			}

			var stored = sessionStorage.getItem( 'scd_wizard_state' );
			if ( stored ) {
				var parsedState = JSON.parse( stored );
				// Session validation now handled server-side
				this.state = $.extend( true, {}, this.state, parsedState );
			}
		},

		/**
		 * Save state to storage
		 */
		saveToStorage: function() {
			// Check sessionStorage availability first
			if ( !window.sessionStorage ) {
				this.handleStorageError( 'sessionStorage not available' );
				this._storageDisabled = true;
				return false;
			}

			try {
			// Safely serialize state with circular reference detection
			var seen = [];
			var stateString = JSON.stringify( this.state, function( key, value ) {
				// Remove functions and undefined
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

			sessionStorage.setItem( 'scd_wizard_state', stateString );

				// Clear any previous storage errors
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
						sessionStorage.setItem( 'scd_wizard_state', stateString );
						this.clearStorageError();
						this._storageDisabled = false;
						return true;
					} catch ( retryError ) {
						// CRITICAL: Storage still full after cleanup
						this._storageDisabled = true;

						// Show persistent warning to user
						if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
							window.SCD.Shared.NotificationService.error(
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
		 * @param message
		 */
		handleStorageError: function( message ) {
			// Notify UI about storage failure
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.warning(
					'Your progress may not be saved locally. Server saves are still working.',
					{ duration: 3000 }
				);
			}


			// Set error state
			this.storageError = {
				message: message,
				timestamp: Date.now()
			};

			// Trigger event for other components
			$( document ).trigger( 'scd:storage:error', [ message ] );
		},

		/**
		 * Clear storage error state
		 */
		clearStorageError: function() {
			this.storageError = null;
			$( document ).trigger( 'scd:storage:recovered' );
		},

		/**
		 * Clear old storage data to free up space
		 */
		clearOldStorageData: function() {
			if ( !window.sessionStorage ) {
				return;
			}

			// Clear other SCD-related storage that might be old
			var keysToRemove = [];
			var i, key;

			for ( i = 0; i < sessionStorage.length; i++ ) {
				key = sessionStorage.key( i );

				// Remove old SCD data except current state
				if ( key && 0 === key.indexOf( 'scd_' ) && 'scd_wizard_state' !== key ) {
					keysToRemove.push( key );
				}
			}

			// Remove identified keys
			for ( i = 0; i < keysToRemove.length; i++ ) {
				sessionStorage.removeItem( keysToRemove[i] );
			}
		},

		/**
		 * Clear storage
		 */
		clearStorage: function() {
			// Check sessionStorage availability first
			if ( !window.sessionStorage ) {
				return;
			}

			try {
				sessionStorage.removeItem( 'scd_wizard_state' );
				this.clearStorageError();
			} catch ( e ) {
				// Handle any storage errors silently
				}
		},

		/**
		 * Start activity tracking
		 */
		startActivityTracking: function() {
			var self = this;

			// Track mouse/keyboard activity
			var updateActivity = function() {
				self.set( { lastActivityAt: new Date().toISOString() }, {
					silent: true,
					skipHistory: true
				} );
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

		$( document ).on( 'mousemove.scd-activity keypress.scd-activity', throttledUpdate );
		},

		/**
		 * Stop activity tracking
		 */
		stopActivityTracking: function() {
			$( document ).off( '.scd-activity' );
		},

		/**
		 * Destroy state manager and cleanup
		 */
		destroy: function() {
			// Stop activity tracking
			this.stopActivityTracking();

			// Clear history to free memory
			this.history = [];
			this.historyIndex = -1;

			// Remove all listeners
			this.listeners = [];

			// Reset state
			this.state = {};
		}
	};

} )( jQuery );