/**
 * Wizard Persistence Service
 *
 * Handles saving, loading, and auto-save functionality.
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
	 * Persistence Service
	 *
	 * Manages data persistence, auto-save, and server communication
	 */
	SCD.Wizard.PersistenceService = {
		// Configuration
		config: {
			autoSaveEnabled: true,
			autoSaveInterval: 30000, // 30 seconds - keeps session alive
			sessionTimeout: 7200000, // 2 hours - must match PHP SESSION_LIFETIME (7200 seconds)
			inactivityTimeout: 600000, // 10 minutes - pause auto-save after this period of inactivity
			autoSaveDraftAfter: 1800000, // 30 minutes - auto-save as draft if inactive this long
			expirationWarningAt: 6600000, // 1 hour 50 minutes - warn user before expiration
			inactivityCheckInterval: 30000, // 30 seconds - how often to check for inactivity
			activityThrottle: 1000, // 1 second - throttle activity tracking updates
			modalAnimationDuration: 300, // 300ms - modal fade animation duration
			redirectDelay: 3000, // 3 seconds - delay before redirect after session expiration
			notificationDuration: 3000, // 3 seconds - auto-save notification display time
			errorNotificationDuration: 5000, // 5 seconds - error notification display time
			ajaxTimeout: 30000 // 30 seconds - AJAX request timeout
		},

		// State
		lastSaveTime: null,
		saveQueue: [],
		isSaving: false,
		autoSaveTimer: null,
		inactivityMonitorInterval: null,
		// Cleanup handles
		unsubscribeHandles: [],

		// Session info
		// Session ID no longer needed - managed server-side via secure cookies
		sessionVersion: 0,

		// Activity tracking
		lastUserActivity: Date.now(),
		activityListeners: [],
		visibilityListenerAdded: false,
		hasAutoSavedDraft: false,
		isAutoSavingDraft: false,
		draftCampaignId: null,
		expirationWarningShown: false,

		// Cached references (performance optimization)
		_i18nCache: null,

		/**
		 * Initialize persistence service
		 * @param config
		 */
		init: function( config ) {
			this.config = $.extend( {}, this.config, config || {} );

			this._logDebug( 'init with config:', this.config );

			// Get session info from config
			// Session ID no longer needed - managed server-side via secure cookies

			// Initialize activity tracking
			this.initActivityTracking();

			// Start safety mechanisms
			this.startInactivityMonitoring();

			// Start auto-save if enabled
			if ( this.config.autoSaveEnabled ) {
				this.startAutoSave();
			}

			// Start session monitoring
			this.startSessionMonitoring();

			// Bind events
			this.bindEvents();

			return this;
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			var self = this;

			// Listen for state changes
		if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
			var unsubscribe = window.SCD.Wizard.StateManager.subscribe( function( state, oldState, changes ) {
				if ( changes.stepData || changes.hasUnsavedChanges ) {
					self.markDirty();
				}
			} );
			self.unsubscribeHandles.push( unsubscribe );
		}

			// Listen for save requests
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.on( 'wizard:save', function() {
					self.saveAll();
				} );

				window.SCD.Wizard.EventBus.on( 'wizard:saveStep', function( e, data ) {
					self.saveStepData( data.step, data.data );
				} );
			}

			// Window unload handler
			$( window ).on( 'beforeunload.persistence', function() {
				if ( self.hasUnsavedChanges() ) {
					self.saveAll( { async: false } );
				}
			} );
		},

		/**
		 * Initialize activity tracking
		 *
		 * Tracks user interactions to determine if auto-save should continue.
		 * Pauses auto-save when user is inactive or tab is hidden.
		 */
		initActivityTracking: function() {
			var self = this;

			// Track user interactions
			var events = ['mousedown', 'keydown', 'scroll', 'touchstart'];

			// Throttle activity handler to prevent excessive updates
			var activityHandler = this._throttle( function() {
				self.lastUserActivity = Date.now();
				self._logDebug( 'User activity detected' );
			}, this.config.activityThrottle );

			// Add event listeners with passive flag for better performance
			for ( var i = 0; i < events.length; i++ ) {
				document.addEventListener( events[i], activityHandler, { passive: true } );
				// Store reference for cleanup
				self.activityListeners.push( {
					event: events[i],
					handler: activityHandler
				} );
			}

			// Use Page Visibility API to pause when tab is hidden
			if ( ! this.visibilityListenerAdded && 'undefined' !== typeof document.hidden ) {
				var visibilityHandler = function() {
					if ( document.hidden ) {
						self._logDebug( 'Tab hidden - pausing auto-save' );
						self.stopAutoSave();
					} else {
						self._logDebug( 'Tab visible - resuming auto-save' );
						self.lastUserActivity = Date.now();
						if ( self.config.autoSaveEnabled ) {
							self.startAutoSave();
						}
					}
				};

				document.addEventListener( 'visibilitychange', visibilityHandler );
				this.visibilityListenerAdded = true;

				// Store for cleanup
				this.activityListeners.push( {
					event: 'visibilitychange',
					handler: visibilityHandler
				} );
			}

			this._logDebug( 'Activity tracking initialized' );
		},

		/**
		 * Check if user is currently active
		 *
		 * @returns {boolean} True if user has interacted recently
		 */
		isUserActive: function() {
			var timeSinceActivity = Date.now() - this.lastUserActivity;
			var isActive = timeSinceActivity < this.config.inactivityTimeout;

			if ( !isActive ) {
				this._logDebug( 'User inactive for ' + ( timeSinceActivity / 1000 ) + ' seconds' );
			}

			return isActive;
		},

		/**
		 * Start inactivity monitoring
		 *
		 * Monitors user inactivity and triggers safety mechanisms:
		 * - Auto-save as draft after 30 minutes
		 * - Show expiration warning after 1h 50min
		 */
		startInactivityMonitoring: function() {
			var self = this;

			// Clear existing interval if any
			if ( this.inactivityMonitorInterval ) {
				clearInterval( this.inactivityMonitorInterval );
			}

			// Check inactivity at configured interval
			this.inactivityMonitorInterval = setInterval( function() {
				self.checkInactivitySafetyMechanisms();
			}, this.config.inactivityCheckInterval );

			this._logDebug( 'Inactivity monitoring started' );
		},

		/**
		 * Check and trigger safety mechanisms based on inactivity
		 */
		checkInactivitySafetyMechanisms: function() {
			var timeSinceActivity = Date.now() - this.lastUserActivity;

			// Safety Mechanism 1: Auto-save as draft after 30 minutes inactivity
			if ( timeSinceActivity > this.config.autoSaveDraftAfter && ! this.hasAutoSavedDraft ) {
				this._logDebug( 'Auto-saving as draft due to 30 min inactivity' );
				this.autoSaveAsDraft();
			}

			// Safety Mechanism 2: Show warning after 1h 50min (10 min before expiration)
			if ( timeSinceActivity > this.config.expirationWarningAt && ! this.expirationWarningShown ) {
				this._logDebug( 'Showing expiration warning - session expires in 10 minutes' );
				this.showExpirationWarning();
			}
		},

		/**
		 * Auto-save current wizard state as draft campaign
		 *
		 * Triggered automatically after 30 minutes of user inactivity.
		 * Saves work without interrupting user, prevents data loss.
		 */
		autoSaveAsDraft: function() {
			var self = this;

			// Prevent duplicate auto-save operations
			if ( this.isAutoSavingDraft ) {
				this._logDebug( 'Auto-save as draft already in progress, skipping' );
				return;
			}

			// Mark that we've done this (only do once per session)
			this.hasAutoSavedDraft = true;
			this.isAutoSavingDraft = true;

			this._logDebug( 'Auto-saving wizard state as draft campaign...' );

			// Get cached i18n strings
			var i18n = this._getI18n();

			// Save as draft via AJAX (via AjaxService to prevent rate limiting)
			SCD.Ajax.post( 'scd_save_draft', {
				draft_action: 'save',
				auto_save: true
			} ).then( function( response ) {
				self.isAutoSavingDraft = false;

				if ( response.success && response.data && response.data.campaign_id ) {
					self.draftCampaignId = response.data.campaign_id;
					self._logDebug( 'Auto-saved as draft campaign #' + self.draftCampaignId );

					// Show subtle notification (non-intrusive)
					self._notify(
						i18n.autoSaveSuccess || 'Your work has been auto-saved as a draft',
						'info',
						self.config.notificationDuration
					);
				} else {
					self._logDebug( 'Auto-save as draft failed', response );

					// Show error notification
					self._notify(
						i18n.autoSaveError || 'Failed to auto-save your work. Please save manually.',
						'error',
						self.config.errorNotificationDuration
					);
				}
			} ).catch( function( error ) {
				self.isAutoSavingDraft = false;
				self._logDebug( 'Auto-save as draft error', error );

				// Show error notification
				self._notify(
					i18n.autoSaveError || 'Failed to auto-save your work. Please save manually.',
					'error',
					self.config.errorNotificationDuration
				);
			} );
		},

		/**
		 * Show expiration warning modal
		 *
		 * Warns user that session will expire in 10 minutes.
		 * Gives option to continue working or save and exit.
		 */
		showExpirationWarning: function() {
			var self = this;
			this.expirationWarningShown = true;

			// Get cached localized strings
			var i18n = this._getI18n();

			var $modal = $(
				'<div class="scd-modal scd-expiration-warning-modal">' +
				'<div class="scd-modal-overlay"></div>' +
				'<div class="scd-modal-content">' +
				'<div class="scd-modal-header">' +
				'<span class="dashicons dashicons-clock"></span>' +
				'<h2>' + ( i18n.modalTitle || 'Session Expiring Soon' ) + '</h2>' +
				'</div>' +
				'<div class="scd-modal-body">' +
				'<p><strong>' + ( i18n.warningStrong || 'Your wizard session will expire in 10 minutes due to inactivity.' ) + '</strong></p>' +
				'<p>' + ( i18n.draftSaved || 'Your work has been automatically saved as a draft and is safe.' ) + '</p>' +
				'<p>' + ( i18n.continuePrompt || 'Would you like to continue working?' ) + '</p>' +
				'</div>' +
				'<div class="scd-modal-footer">' +
				'<button class="button button-primary scd-continue-working">' + ( i18n.continueButton || 'Continue Working' ) + '</button>' +
				'<button class="button button-secondary scd-view-drafts">' + ( i18n.viewDraftsButton || 'View Drafts' ) + '</button>' +
				'</div>' +
				'</div>' +
				'</div>'
			);

			// Continue working - resets activity and extends session
			$modal.find( '.scd-continue-working' ).on( 'click', function() {
				self.lastUserActivity = Date.now();
				self.expirationWarningShown = false;
				self.saveAll( { silent: false } ); // Trigger save to extend session
				$modal.fadeOut( self.config.modalAnimationDuration, function() {
					$modal.remove();
				} );
			} );

			// View drafts - redirect to campaign list
			$modal.find( '.scd-view-drafts' ).on( 'click', function() {
				window.location.href = window.scdWizardData.campaignListUrl || 'admin.php?page=scd-campaigns';
			} );

			// Show modal
			$( 'body' ).append( $modal );
			$modal.hide().fadeIn( self.config.modalAnimationDuration );

			this._logDebug( 'Expiration warning modal shown' );
		},

		/**
		 * Save all data
		 * @param options
		 */
		saveAll: function( options ) {
			options = options || {};
			var self = this;
			var deferred = $.Deferred();

			if ( this.isSaving && !options.force ) {
				deferred.reject( 'Save already in progress' );
				return deferred.promise();
			}

			this.isSaving = true;

			// Emit auto-save start event if this is an auto-save
			if ( options.silent ) {
				if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
					window.SCD.Wizard.EventBus.emit( 'autoSave:start' );
				}
			}

			// Update state
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				window.SCD.Wizard.StateManager.set( { isSaving: true }, { silent: true } );
			}

			// Collect all data
			var allData = this.collectAllData();

			// For save_all, extract current step data and send as step save
			var currentStep = allData.step;
			var stepData = ( allData.step_data && allData.step_data[currentStep] ) || {};

			// Create proper save_step request structure
			var data = {
				step: currentStep,
				data: stepData,
				// Include metadata for context
				campaignId: allData.campaignId || allData.campaign_id,
				campaignName: allData.campaignName || allData.campaign_name,
				wizardMode: allData.wizardMode || allData.wizard_mode,
				startedAt: allData.startedAt || allData.started_at,
				lastActivity: allData.lastActivity || allData.last_activity,
				isAutoSave: options.autoSave || false
			};

			// Send to server
			var _startTime = performance.now();
			this.sendRequest( 'save_step', data, options )  // Use save_step directly
				.then( function( response ) {
					// Debug: Log save success
					if ( window.scdDebugPersistence ) {
					}

					self.handleSaveSuccess( response );
					deferred.resolve( response );
				} )
				.fail( function( error ) {
					// Debug: Log save error
					if ( window.scdDebugPersistence ) {
					}

					self.handleSaveError( error );
					deferred.reject( error );
				} )
				.always( function() {
					self.isSaving = false;
					if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
						window.SCD.Wizard.StateManager.set( { isSaving: false }, { silent: true } );
					}
				} );

			return deferred.promise();
		},

		/**
		 * Save step data
		 * @param step
		 * @param data
		 * @param options
		 */
		saveStepData: function( step, data, options ) {
			options = options || {};

			// Validate input data
			if ( !this._isRequestDataValid( data ) ) {
				var error = new Error( 'Invalid data provided to saveStepData' );
				this._handleError( error, 'saveStepData' );
				return $.Deferred().reject( error ).promise();
			}

			// Debug: Log save request
			this._logDebug( 'saveStepData for step: ' + step, options );

			var self = this;
			var deferred = $.Deferred();

			// Add to queue if currently saving
			if ( this.isSaving && !options.force ) {
				this.addToQueue( step, data );
				deferred.resolve( { queued: true } );
				return deferred.promise();
			}

			this.isSaving = true;

			// Prepare request data
			var requestData = {
				step: step,
				data: data,
				// Session ID no longer needed - sent via secure cookie
				sessionVersion: this.sessionVersion
			};

			// Pass through autosave flag if present
			if ( options && options.isAutoSave ) {
				requestData.isAutoSave = true;
			}

			// Pass through navigation save flag if present
			if ( options && options.isNavigationSave ) {
				requestData.isNavigationSave = true;
			}

			// Send request
			this.sendRequest( 'save_step', requestData, options )
				.then( function( response ) {
					self.handleStepSaveSuccess( step, response );
					deferred.resolve( response );
				} )
				.fail( function( error ) {
					self._handleError( error, 'saveStepData' );
					self.handleSaveError( error );
					deferred.reject( error );
				} )
				.always( function() {
					self.isSaving = false;
					self.processQueue();
				} );

			return deferred.promise();
		},

		/**
		 * Load session data
		 */
		loadSessionData: function() {
			var self = this;
			var deferred = $.Deferred();

			// Debug: Log load request
			if ( window.scdDebugPersistence ) {
			}

			var requestData = {
				// Session ID no longer needed - sent via secure cookie
			};

			this.sendRequest( 'load_session', requestData )
				.then( function( response ) {
					self.handleLoadSuccess( response );

					// Debug: Log loaded data details
					if ( window.scdDebugPersistence ) {
					}

					deferred.resolve( response );
				} )
				.fail( function( error ) {
					// Debug: Log load error
					if ( window.scdDebugPersistence ) {
					}

					self.handleLoadError( error );
					deferred.reject( error );
				} );

			return deferred.promise();
		},

		/**
		 * Complete wizard
		 * @param options
		 */
		completeWizard: function( options ) {
			var self = this;
			var deferred = $.Deferred();
			options = options || {};

			// Validate all data first
			var data = this.collectAllData();
			data.action = 'complete';

			// Add save as draft option
			if ( options.saveAsDraft ) {
				data.saveAsDraft = true;
			}

			// Validate request data
			if ( !this._isRequestDataValid( data ) ) {
				var error = new Error( 'Invalid data for wizard completion' );
				this._handleError( error, 'completeWizard' );
				deferred.reject( error );
				return deferred.promise();
			}

			// Get nonce from wizard data
			var nonce = '';
			if ( window.scdWizardData && window.scdWizardData.nonces ) {
				nonce = window.scdWizardData.nonces.scd_wizard_nonce || window.scdWizardData.nonces.wizard_nonce || '';
			}

			// Use complete_wizard action directly
			var requestData = {
				action: 'scd_ajax',
				scdAction: 'complete_wizard',
				nonce: nonce,
				draft_action: 'complete',
				campaign_data: data,
				save_as_draft: options.saveAsDraft || false
			};

			SCD.Ajax.post( 'scd_complete_wizard', requestData )
				.then( function( response ) {
					// Unwrap the response data - backend returns {success: true, data: {...}}
					var completionData = response.data || response;


					self.handleCompleteSuccess( completionData );
					deferred.resolve( completionData );
				} )
				.fail( function( error ) {
					self._handleError( error, 'completeWizard' );
					self.handleCompleteError( error );
					deferred.reject( error );
				} );

			return deferred.promise();
		},

		/**
		 * Send AJAX request
		 * @param action
		 * @param data
		 * @param options
		 */
		sendRequest: function( action, data, options ) {
			options = options || {};
			var self = this;
			var deferred = $.Deferred();
			var requestData;

			// Use shared AJAX service if available
			if ( SCD.Ajax ) {
				requestData = $.extend( {
					// Session ID no longer needed - sent via secure cookie
				}, data );

				var requestOptions = {
					timeout: options.timeout || self.config.ajaxTimeout,
					async: false !== options.async,
					retryLimit: self.config.maxRetries,
					retryDelay: self.config.retryDelay
				};

				// Map persistence actions to correct AJAX actions
				var actionMap = {
					'save_step': 'scd_save_step',
					'load_all': 'scd_load_data',
					'load_session': 'scd_load_session',
					'check_session': 'scd_check_session',
					'complete_wizard': 'scd_complete_wizard'
				};
				var ajaxAction = actionMap[action] || action;

				SCD.Ajax.post( ajaxAction, requestData, requestOptions )
					.then( function( response ) {
						deferred.resolve( response );
					} )
					.catch( function( error ) {
						deferred.reject( error );
					} );

				return deferred.promise();
			}

			// Fallback to direct AJAX
			requestData = $.extend( {
				action: 'scd_ajax',
				scdAction: action,
				nonce: window.scdWizardData ? window.scdWizardData.nonce : ''
				// Session ID no longer needed - sent via secure cookie
			}, data );

			var ajaxOptions = {
				url: window.scdWizardData ? window.scdWizardData.ajax_url : ajaxurl,
				type: 'POST',
				data: requestData,
				async: false !== options.async,
				timeout: options.timeout || self.config.ajaxTimeout
			};

			// Make AJAX request - no retries, fix issues at source
			$.ajax( ajaxOptions )
				.done( function( response ) {
					// Check session validity in every response
					self.checkSessionInResponse( response );

					if ( response.success ) {
						deferred.resolve( response.data );
					} else {
						deferred.reject( response.data || 'Request failed' );
					}
				} )
				.fail( function( xhr, status, error ) {
					deferred.reject( {
						status: status,
						error: error,
						xhr: xhr
					} );
				} );

			return deferred.promise();
		},

		/**
		 * Collect all data
		 */
		collectAllData: function() {
			var data = {};

			// Get state data
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				var state = window.SCD.Wizard.StateManager.get();

				// Server will auto-convert camelCase to snake_case
				// Send state directly in camelCase
				// Only include essential state info, not full stepData for all steps
			data = {
				step: state.currentStep,
				campaignId: state.campaignId,
				campaignName: state.campaignName,
				wizardMode: state.wizardMode,
				startedAt: state.startedAt,
				lastActivity: state.lastActivityAt
			};

			// CRITICAL FIX: Include ALL step data, not just current step
			// The Campaign Creator Service needs access to all step data to compile the campaign
			if ( state.stepData && 'object' === typeof state.stepData ) {
				data.step_data = state.stepData;
			}

		}

		// Note: We include all step data here because:
		// 1. Campaign creation needs complete data from all steps
		// 2. The session may have this data, but we need to ensure it's sent in the request
		// 3. This is only called during wizard completion, not during auto-save

		return data;
		},

		/**
		 * Handle save success
		 * @param response
		 */
		handleSaveSuccess: function( response ) {
			// Update session info
			if ( response.session ) {
				// Session ID no longer needed - managed server-side
				this.sessionVersion = response.session.version;
			}

			// Update last save time
			this.lastSaveTime = new Date();

			// Update state
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				window.SCD.Wizard.StateManager.set( {
					hasUnsavedChanges: false,
					lastSavedAt: this.lastSaveTime.toISOString()
				}, { silent: true } );
			}

			// Emit success event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'save:success', {
					timestamp: this.lastSaveTime,
					response: response
				} );
			}
		},

		/**
		 * Handle step save success
		 * @param step
		 * @param response
		 */
		handleStepSaveSuccess: function( step, response ) {
			this.handleSaveSuccess( response );

			// Mark step as saved
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				var savedSteps = window.SCD.Wizard.StateManager.get( 'savedSteps' ) || {};
				savedSteps[step] = new Date().toISOString();
				window.SCD.Wizard.StateManager.set( { savedSteps: savedSteps }, { silent: true } );
			}
		},

		/**
		 * Handle save error
		 * @param error
		 */
		handleSaveError: function( error ) {
			// Emit error event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'save:error', {
					error: error,
					timestamp: new Date()
				} );
			}

			// Show error notification
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.error(
					'Failed to save. Please try again.'
				);
			}
		},

		/**
		 * Handle load success
		 * @param response
		 */
		handleLoadSuccess: function( response ) {
			// Update session info
			if ( response.session ) {
				// Session ID no longer needed - managed server-side
				this.sessionVersion = response.session.version;
			}

			// Emit load event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'load:success', response );
			}
		},

		/**
		 * Handle load error
		 * @param error
		 */
		handleLoadError: function( error ) {
			// Emit error event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'load:error', error );
			}
		},

		/**
		 * Handle complete success
		 * @param response
		 */
		handleCompleteSuccess: function( response ) {
			// Clear session data
			this.clearSession();

			// Emit complete event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'wizard:completed', response );
			}
		},

		/**
		 * Handle complete error
		 * @param error
		 */
		handleCompleteError: function( error ) {
			// Emit error event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'wizard:completeError', error );
			}
		},

		/**
		 * Start auto-save
		 */
		startAutoSave: function() {
			var self = this;

			this.stopAutoSave();

			// Listen for state changes instead of polling
			$( document ).on( 'scd:wizard:dataChanged.autosave', function() {
				self.scheduleAutoSave();
			} );
		},

		/**
		 * Schedule auto-save after changes
		 *
		 * Only saves if user is active to prevent zombie sessions.
		 */
		scheduleAutoSave: function() {
			var self = this;

			// Clear existing timer
			if ( this.autoSaveTimer ) {
				clearTimeout( this.autoSaveTimer );
			}

			// Schedule save after interval
			this.autoSaveTimer = setTimeout( function() {
				// Only auto-save if user is actually active
				if ( self.isUserActive() && self.hasUnsavedChanges() && !self.isSaving ) {
					self._logDebug( 'Auto-save triggered - user is active' );
					self.saveAll( { silent: true } );
				} else if ( !self.isUserActive() ) {
					self._logDebug( 'Auto-save skipped - user inactive' );
				}
			}, this.config.autoSaveInterval );
		},

		/**
		 * Stop auto-save
		 */
		stopAutoSave: function() {
			if ( this.autoSaveTimer ) {
				clearTimeout( this.autoSaveTimer );
				this.autoSaveTimer = null;
			}
			$( document ).off( '.autosave' );
		},

		/**
		 * Start session monitoring
		 */
		startSessionMonitoring: function() {
			// Session validity is checked on each save operation
			// No need for polling - the server will reject expired sessions
		},

		/**
		 * Check session validity in response
		 * @param response
		 */
		checkSessionInResponse: function( response ) {
			// Check if session expired in any response
			if ( response && response.sessionExpired ) {
				this.handleSessionExpired();
				return false;
			}
			return true;
		},

		/**
		 * Handle session expired with smart recovery
		 *
		 * Instead of showing error, intelligently redirect user:
		 * 1. If draft was auto-saved → redirect to edit that draft
		 * 2. If no draft → redirect to campaign list with message
		 */
		handleSessionExpired: function() {
			var _self = this;

			// Stop auto-save
			this.stopAutoSave();

			// Update state
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				window.SCD.Wizard.StateManager.set( { sessionValid: false }, { silent: true } );
			}

			// Emit event
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
				window.SCD.Wizard.EventBus.emit( 'session:expired' );
			}

			this._logDebug( 'Session expired - redirecting user...' );

			// Smart redirection based on whether draft was saved
			if ( this.draftCampaignId ) {
				// Draft exists - redirect to edit that draft
				this.redirectToDraft( this.draftCampaignId );
			} else {
				// No draft - redirect to campaign list
				this.redirectToCampaignList();
			}
		},

		/**
		 * Redirect to edit draft campaign
		 *
		 * @param {number} campaignId Draft campaign ID
		 */
		redirectToDraft: function( campaignId ) {
			var self = this;

			// Get cached localized strings
			var i18n = this._getI18n();

			var message = i18n.sessionExpiredDraft ||
						 'Your session expired, but your work was auto-saved. Resuming from draft...';

			// Show brief message before redirect using optimized helper
			this._notify( message, 'info', self.config.redirectDelay );

			// Redirect after short delay to show message
			setTimeout( function() {
				var editUrl = window.scdWizardData && window.scdWizardData.editDraftUrl ?
					window.scdWizardData.editDraftUrl.replace( '{id}', campaignId ) :
					'admin.php?page=scd-campaigns&action=edit&campaign_id=' + campaignId;

				window.location.href = editUrl;
			}, self.config.redirectDelay );

			this._logDebug( 'Redirecting to draft campaign #' + campaignId );
		},

		/**
		 * Redirect to campaign list with message
		 */
		redirectToCampaignList: function() {
			var self = this;

			// Get cached localized strings
			var i18n = this._getI18n();

			var message = i18n.sessionExpiredNoDraft ||
						 'Your session expired. Check your drafts to recover your work.';

			// Show message using optimized helper
			this._notify( message, 'warning', self.config.redirectDelay );

			// Redirect after delay
			setTimeout( function() {
				var campaignListUrl = window.scdWizardData && window.scdWizardData.campaignListUrl ?
					window.scdWizardData.campaignListUrl :
					'admin.php?page=scd-campaigns';

				window.location.href = campaignListUrl;
			}, self.config.redirectDelay );

			this._logDebug( 'Redirecting to campaign list' );
		},

		/**
		 * Clear session
		 */
		clearSession: function() {
			// Session ID no longer needed - managed server-side
			this.sessionVersion = 0;
			this.lastSaveTime = null;

			// Clear storage
			try {
				sessionStorage.removeItem( 'scd_wizard_session' );
				sessionStorage.removeItem( 'scd_wizard_state' );
			} catch ( e ) {
				// Storage errors should not break functionality but should be tracked
				if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
					window.SCD.Wizard.EventBus.emit( 'storage:error', {
						error: e,
						operation: 'clear',
						timestamp: new Date()
					} );
				}
			}
		},

		/**
		 * Check for unsaved changes
		 */
		hasUnsavedChanges: function() {
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				return window.SCD.Wizard.StateManager.get( 'hasUnsavedChanges' );
			}
			return false;
		},

		/**
		 * Mark as dirty
		 */
		markDirty: function() {
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				window.SCD.Wizard.StateManager.set( {
					hasUnsavedChanges: true,
					isDirty: true
				}, { silent: true } );
			}
		},

		/**
		 * Add to save queue
		 * @param step
		 * @param data
		 */
		addToQueue: function( step, data ) {
			this.saveQueue.push( {
				step: step,
				data: data,
				timestamp: new Date()
			} );
		},

		/**
		 * Process save queue
		 */
		processQueue: function() {
			if ( 0 === this.saveQueue.length || this.isSaving ) {
				return;
			}

			var item = this.saveQueue.shift();
			this.saveStepData( item.step, item.data );
		},

		/**
		 * Enable auto-save
		 */
		enableAutoSave: function() {
			this.config.autoSaveEnabled = true;
			this.startAutoSave();
		},

		/**
		 * Disable auto-save
		 */
		disableAutoSave: function() {
			this.config.autoSaveEnabled = false;
			this.stopAutoSave();
		},

		/**
		 * Export persistence data
		 */
		exportPersistenceData: function() {
			return {
				config: this.config,
				// Session ID no longer needed,
				sessionVersion: this.sessionVersion,
				lastSaveTime: this.lastSaveTime,
				isSaving: this.isSaving,
				queueLength: this.saveQueue.length,
				hasUnsavedChanges: this.hasUnsavedChanges()
			};
		},

		/**
		 * Check if request is valid
		 * @private
		 * @since 1.0.0
		 * @param {object} data Data to validate
		 * @returns {boolean} True if valid
		 */
		_isRequestDataValid: function( data ) {
			if ( !data || 'object' !== typeof data ) {
				return false;
			}
			
			// Check for circular references
			try {
				JSON.stringify( data );
				return true;
			} catch ( e ) {
				return false;
			}
		},

		/**
		 * Get cached i18n strings (performance optimization)
		 * @private
		 * @since 1.0.0
		 * @returns {Object} i18n strings object
		 */
		_getI18n: function() {
			if ( ! this._i18nCache ) {
				this._i18nCache = ( window.scdWizardData && window.scdWizardData.sessionExpiration ) ?
								 window.scdWizardData.sessionExpiration : {};
			}
			return this._i18nCache;
		},

		/**
		 * Show notification (optimized helper)
		 * @private
		 * @since 1.0.0
		 * @param {string} message Message to show
		 * @param {string} type Notification type (info, error, success, warning)
		 * @param {number} duration Duration in milliseconds
		 */
		_notify: function( message, type, duration ) {
			// Use NotificationService directly for consistent notifications
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.show( message, type, duration );
			}
		},

		/**
		 * Throttle function calls
		 * @private
		 * @since 1.0.0
		 * @param {Function} func Function to throttle
		 * @param {number} wait Milliseconds to wait between calls
		 * @returns {Function} Throttled function
		 */
		_throttle: function( func, wait ) {
			var timeout = null;
			var previous = 0;

			return function() {
				var now = Date.now();
				var remaining = wait - ( now - previous );
				var context = this;
				var args = arguments;

				if ( remaining <= 0 || remaining > wait ) {
					if ( timeout ) {
						clearTimeout( timeout );
						timeout = null;
					}
					previous = now;
					func.apply( context, args );
				} else if ( ! timeout ) {
					timeout = setTimeout( function() {
						previous = Date.now();
						timeout = null;
						func.apply( context, args );
					}, remaining );
				}
			};
		},

		/**
		 * Log debug message if debug enabled
		 * @private
		 * @since 1.0.0
		 * @param {string} message Debug message
		 * @param {*} data Optional data to log
		 */
		_logDebug: function( message, data ) {
			if ( window.scdDebugPersistence ) {
				if ( data ) {
				} else {
				}
			}
		},

		/**
		 * Handle error with proper logging
		 * @private
		 * @since 1.0.0
		 * @param {*} error Error to handle
		 * @param {string} operation Operation that failed
		 */
		_handleError: function( error, operation ) {
			if ( window.SCD && window.SCD.ErrorHandler ) {
				window.SCD.ErrorHandler.handle( error, 'PersistenceService.' + operation );
			} else {
				this._logDebug( operation + ' failed:', error );
			}
		},

		/**
		 * Destroy service
		 */
	destroy: function() {
		this.stopAutoSave();

		// Stop inactivity monitoring
		if ( this.inactivityMonitorInterval ) {
			clearInterval( this.inactivityMonitorInterval );
			this.inactivityMonitorInterval = null;
		}

		// Remove window event listeners
		$( window ).off( '.persistence' );

		// Remove activity tracking listeners
		for ( var i = 0; i < this.activityListeners.length; i++ ) {
			var listener = this.activityListeners[i];
			document.removeEventListener( listener.event, listener.handler );
		}
		this.activityListeners = [];
		this.visibilityListenerAdded = false;

		// Unsubscribe from state manager
		for ( var j = 0; j < this.unsubscribeHandles.length; j++ ) {
			if ( 'function' === typeof this.unsubscribeHandles[j] ) {
				this.unsubscribeHandles[j]();
			}
		}
		this.unsubscribeHandles = [];

		// Remove EventBus listeners
		if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus ) {
			window.SCD.Wizard.EventBus.off( 'wizard:save' );
			window.SCD.Wizard.EventBus.off( 'wizard:saveStep' );
		}

		this.saveQueue = [];
		this.isSaving = false;
	}
	};

	// Also set it on the window directly as a fallback
	window.SCDPersistenceService = SCD.Wizard.PersistenceService;

	// Trigger service ready event
	$( document ).trigger( 'scd:service:ready', [ 'PersistenceService' ] );

} )( jQuery );