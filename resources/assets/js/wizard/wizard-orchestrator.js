/**
 * Wizard Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-orchestrator.js
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
	 * Field definitions will be loaded from the centralized field-definitions.js module
	 * which loads definitions from PHP via wp_localize_script
	 */


	/**
	 * Wizard Orchestrator Class
	 *
	 * Extends BaseOrchestrator to coordinate wizard-level functionality
	 */
	var WizardOrchestrator = function() {
		// Ensure BaseOrchestrator is available
		if ( !  WSSCD.Shared || ! WSSCD.Shared.BaseOrchestrator ) {
			return;
		}

		// Call parent constructor
		WSSCD.Shared.BaseOrchestrator.call( this, 'wizard', {
			// Module factories
			stateManager: function() {
				return WSSCD.Wizard.StateManager.getInstance();
			},
			eventBus: function() {
				return WSSCD.Wizard.EventBus;
			},
			navigationService: function() {
				return WSSCD.Wizard.Navigation;
			},
			lifecycleManager: function() {
				return WSSCD.Wizard.LifecycleManager;
			},
			ajaxService: function() {
				return WSSCD.Ajax || null;
			},
			uiService: function() {
				return WSSCD.Shared ? WSSCD.Shared.UI : null;
			}
		} );

		// Wizard-specific properties
		this.stepOrchestrators = {};
		this.isInternalNavigation = false; // Track wizard internal navigation
		this.config = {
			steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			sessionTimeout: 7200000  // 2 hours - aligned with PHP SESSION_LIFETIME
		};
	};

	// Extend BaseOrchestrator
	WizardOrchestrator.prototype = Object.create( WSSCD.Shared.BaseOrchestrator.prototype );
	WizardOrchestrator.prototype.constructor = WizardOrchestrator;

	/**
	 * Initialize wizard orchestrator
	 * Overrides BaseOrchestrator.init to add wizard-specific initialization
	 *
	 * @param {object} wizard Wizard instance
	 * @param {object} config Configuration options
	 * @returns {Promise} Initialization promise
	 */
	WizardOrchestrator.prototype.init = function( wizard, config ) {
		// Call parent init
		// Note: Parent init calls onInit() which loads the current step
		// No need to call loadCurrentStep() here - it's handled in onInit()
		return WSSCD.Shared.BaseOrchestrator.prototype.init.call( this, wizard, config );
	};

	/**
	 * Get default configuration
	 */
	WizardOrchestrator.prototype.getDefaultConfig = function() {
		return $.extend( {}, WSSCD.Shared.BaseOrchestrator.prototype.getDefaultConfig.call( this ), {
			steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			sessionTimeout: 7200000,  // 2 hours - aligned with PHP SESSION_LIFETIME
			validateOnNavigation: true,
			allowBackNavigation: true
		} );
	};

	/**
	 * Clear all wizard session data
	 *
	 * Delegates to centralized WizardSession utility for consistent behavior.
	 * Clears both browser sessionStorage and StateManager singleton.
	 *
	 * @since 1.0.0
	 * @param {object} options - Optional settings.
	 * @param {boolean} options.clearServer - Also clear server-side session (default: false).
	 * @returns {void}
	 */
	WizardOrchestrator.prototype.clearSessionStorage = function( options ) {
		if ( window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.WizardSession ) {
			// Use centralized clear() which handles both storage layers
			WSSCD.Utils.WizardSession.clear( options || {} );
		}
	};

	/**
	 * Initialize state from window data
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.initializeStateFromWindowData = function() {
		if ( ! window.wsscdWizardData ) {
			return;
		}

		this.config.nonce = window.wsscdWizardData.nonce || window.wsscdWizardData.wizardNonce;

		if ( ! this.modules.stateManager ) {
			return;
		}

		var initData = {
			currentStep: window.wsscdWizardData.currentStep || 'basic',
			nonce: this.config.nonce
		};

		if ( window.wsscdWizardData.currentCampaign && window.wsscdWizardData.currentCampaign.completedSteps ) {
			initData.completedSteps = window.wsscdWizardData.currentCampaign.completedSteps;
		}

		var urlParams = new URLSearchParams( window.location.search );

		// Check if this is a fresh session (new campaign) from PHP flag.
		// Intent Handler strips URL params after processing, so use PHP flag as authority.
		var isFreshFromPHP = window.wsscdWizardData && window.wsscdWizardData.isFresh;

		// Edit mode: has campaign ID but NOT a fresh new campaign.
		var isEditMode = urlParams.get( 'intent' ) === 'edit' || ( urlParams.get( 'id' ) && ! isFreshFromPHP );

		// Data is auto-converted to camelCase by Asset Localizer
		var currentCampaign = window.wsscdWizardData.currentCampaign;

		if ( window.WSSCD && window.WSSCD.Debug ) {
			window.WSSCD.Debug.log( '[WizardOrchestrator] isEditMode:', isEditMode );
			window.WSSCD.Debug.log( '[WizardOrchestrator] currentCampaign:', currentCampaign );
			window.WSSCD.Debug.log( '[WizardOrchestrator] window.wsscdWizardData:', window.wsscdWizardData );
		}

		if ( currentCampaign ) {
			// Only initialize stepData if we have actual data to load
			initData.stepData = {};

			// Deep clone step data to avoid mutations affecting the original window data
			// Only add step data that actually exists in the session
			if ( currentCampaign.basic ) {
				initData.stepData.basic = $.extend( true, {}, currentCampaign.basic );
			}
			if ( currentCampaign.products ) {
				initData.stepData.products = $.extend( true, {}, currentCampaign.products );
			}
			if ( currentCampaign.discounts ) {
				initData.stepData.discounts = $.extend( true, {}, currentCampaign.discounts );
			}
			if ( currentCampaign.schedule ) {
				initData.stepData.schedule = $.extend( true, {}, currentCampaign.schedule );
			}
			if ( currentCampaign.review ) {
				initData.stepData.review = $.extend( true, {}, currentCampaign.review );
			}

			if ( isEditMode ) {
				var campaignId = currentCampaign.campaignId || urlParams.get( 'id' );
				if ( campaignId ) {
					initData.campaignId = parseInt( campaignId, 10 );
					initData.wizardMode = 'edit';

					if ( window.WSSCD && window.WSSCD.Debug ) {
						window.WSSCD.Debug.log( '[WizardOrchestrator] Setting edit mode. Campaign ID: ' + initData.campaignId );
					}
				}
			}
		}

		if ( window.WSSCD && window.WSSCD.Debug ) {
			window.WSSCD.Debug.log( '[WizardOrchestrator] Initializing StateManager with:', initData );
		}

		this.modules.stateManager.init( initData );

		// Update UI for completed steps
		if ( initData.completedSteps && this.modules.navigationService ) {
			this.modules.navigationService.updateCompletedSteps( initData.completedSteps );
		}
	};

	/**
	 * Initialize lifecycle manager
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.initializeLifecycleManager = function() {
		if ( !  this.modules.lifecycleManager ) {
			return;
		}

		if ( 'function' === typeof this.modules.lifecycleManager.init ) {
			this.modules.lifecycleManager.init();
		}

		// Register wizard in lifecycle
		if ( 'function' === typeof this.modules.lifecycleManager.registerComponent ) {
			this.modules.lifecycleManager.registerComponent( 'wizard', {
				component: this,
				priority: 0,
				dependencies: []
			} );
		}
	};

	/**
	 * Initialize orchestrator.
	 *
	 * @since 1.0.0
	 * @returns {void}
	 */
	WizardOrchestrator.prototype.onInit = function() {
		var self = this;

		// Check isFresh flag from PHP (one-time signal, consumed after read).
		// This is the authoritative source - URL intent param is stripped by Intent Handler.
		var isFresh = window.wsscdWizardData && window.wsscdWizardData.isFresh;

		// Clear browser sessionStorage for fresh sessions.
		if ( isFresh ) {
			this.clearSessionStorage();
		}

		this.initializeStateFromWindowData();

		this.initializeLifecycleManager();

		this.loadCurrentStep()
			.then( function() {
				if ( self.modules.lifecycleManager ) {
					self.modules.lifecycleManager.setPhase( 'ready' );
				}

				// Emit initialized event
				if ( self.modules.eventBus ) {
					self.modules.eventBus.emit( 'wizard:initialized', { wizard: self } );
				}
			} )
			.catch( function( error ) {
				self.handleError( error, 'initialization' );
			} );
	};

	/**
	 * Bind wizard-specific events
	 */
	WizardOrchestrator.prototype.onBindEvents = function() {
		var self = this;

		// Navigation events
		if ( this.modules.eventBus ) {
			this.modules.eventBus.on( 'navigation:request', function( e, data ) {
				self.handleNavigationRequest( data );
			} );
		}

		// Also listen for document-based navigation request
		$( document ).on( 'wsscd:navigation:request', function( e, data ) {
			self.handleNavigationRequest( data );
		} );

		// Listen for retry event from completion modal
		$( document ).on( 'wsscd:wizard:retry', function() {
			self.retryCompletion();
		} );

		if ( this.modules.eventBus ) {
			this.modules.eventBus.on( 'validation:complete', function( e, data ) {
				self.handleValidationComplete( data );
			} );

			this.modules.eventBus.on( 'persistence:saved', function( e, data ) {
				self.handleDataSaved( data );
			} );

			this.modules.eventBus.on( 'session:expired', function() {
				self.handleSessionExpired();
			} );
		}

		// State changes
		if ( this.modules.stateManager ) {
			this.modules.stateManager.subscribe( function( changes ) {
				self.handleStateChange( changes );
			} );
		}

		// CRITICAL FIX: Add beforeunload warning for external navigation only
		// Internal wizard navigation saves automatically, but browser close/crash needs warning
		$( window ).on( 'beforeunload.wizard', function( e ) {
			// Don't warn during internal navigation
			if ( self.isInternalNavigation ) {
				return undefined;
			}

			// Warn if there are unsaved changes
			if ( self.modules.stateManager && self.modules.stateManager.get( 'hasUnsavedChanges' ) ) {
				var message = 'You have unsaved changes. Are you sure you want to leave?';
				e.returnValue = message;
				return message;
			}

			return undefined;
		} );

		// Track form changes to set hasUnsavedChanges (for UI state only)
		$( document ).on( 'change.wizard input.wizard', 'input, select, textarea', function( e ) {
			// Skip if this is a navigation button or non-form element
			if ( 0 < $( e.target ).closest( '.wsscd-wizard-navigation' ).length ) {
				return;
			}

			// Mark as having unsaved changes
			if ( self.modules.stateManager ) {
				// Only log if state actually changed
				var currentState = self.modules.stateManager.get( 'hasUnsavedChanges' );
				if ( !  currentState ) {
					self.modules.stateManager.set( { hasUnsavedChanges: true } );
				} else {
					// Silently update without logging
					self.modules.stateManager.set( { hasUnsavedChanges: true }, { silent: true } );
				}
			}
		} );
	};

	/**
	 * Try to create step from factory
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.tryCreateStepFromFactory = function( stepName ) {
		if ( ! ( 'function' === typeof WSSCD.Steps.hasFactory && WSSCD.Steps.hasFactory( stepName ) ) ) {
			return null;
		}

		try {
			var instance = WSSCD.Steps.createStep( stepName );
			if ( instance ) {
				this.stepOrchestrators[stepName] = instance;
				return instance;
			}
		} catch ( e ) {
			// Factory creation failed - keep error for debugging
			throw new Error( 'Factory failed to create step: ' + stepName );
		}

		return null;
	};

	/**
	 * Try to create step from registered class
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.tryCreateStepFromClass = function( stepName ) {
		var className = stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 ) + 'Orchestrator';
		if ( !  window.WSSCD.Steps[className] ) {
			return null;
		}

		var OrchestratorClass = window.WSSCD.Steps[className];
		if ( 'function' === typeof OrchestratorClass ) {
			this.stepOrchestrators[stepName] = new OrchestratorClass();
			return this.stepOrchestrators[stepName];
		}

		return null;
	};

	/**
	 * Load current step
	 */
	WizardOrchestrator.prototype.loadCurrentStep = function() {
		var self = this;
		var stepName = this.getCurrentStep();
		var deferred = $.Deferred();

		// Ensure stepName is valid
		if ( !  stepName || 'string' !== typeof stepName ) {
			stepName = 'basic'; // Default to basic step
		}

		if ( this.stepOrchestrators[stepName] ) {
			this.initializeWizardStep( stepName );
			deferred.resolve();
			return deferred.promise();
		}

		if ( !  ( window.WSSCD && window.WSSCD.Steps ) ) {
			deferred.resolve();
			return deferred.promise();
		}

		// Try using factory first
		try {
			var factoryInstance = this.tryCreateStepFromFactory( stepName );
			if ( factoryInstance ) {
				this.initializeWizardStep( stepName );
				deferred.resolve();
				return deferred.promise();
			}
		} catch ( e ) {
			deferred.reject( e );
			return deferred.promise();
		}

		// Fallback: Check if step is already registered with class name
		var classInstance = this.tryCreateStepFromClass( stepName );
		if ( classInstance ) {
			this.initializeWizardStep( stepName );
			deferred.resolve();
			return deferred.promise();
		}

		// Try loading the step module
		this.loadStepModule( stepName )
			.then( function() {
				self.initializeWizardStep( stepName );
				deferred.resolve();
			} )
			.catch( function( _error ) {
				// Log the error but still try to initialize basic functionality

				// Even without the module, initialize basic step UI
				self.initializeWizardStep( stepName );
				deferred.resolve(); // Resolve anyway to not block wizard
			} );

		return deferred.promise();
	};

	/**
	 * Get step instance by name
	 * @param stepName
	 */
	WizardOrchestrator.prototype.getStepInstance = function( stepName ) {
		return this.stepOrchestrators[stepName] || null;
	};

	/**
	 * Create step instance from factory if available
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.createStepInstanceFromFactory = function( stepName, deferred ) {
		if ( !  ( WSSCD.Steps.hasFactory && WSSCD.Steps.hasFactory( stepName ) ) ) {
			return false;
		}

		var instance = WSSCD.Steps.createStep( stepName );
		if ( instance ) {
			this.stepOrchestrators[stepName] = instance;
			deferred.resolve();
			return true;
		}

		deferred.reject( new Error( 'Failed to create step instance' ) );
		return true;
	};

	/**
	 * Load step module dynamically
	 * @param stepName
	 */
	WizardOrchestrator.prototype.loadStepModule = function( stepName ) {
		var self = this;
		var deferred = $.Deferred();

		var hasModuleLoader = window.WSSCD && window.WSSCD.ModuleLoader && window.WSSCD.ModuleLoader.isLoaded;
		var hasStepLoader = hasModuleLoader && window.WSSCD.ModuleLoader.isLoaded( stepName + '-loader' );

		if ( hasStepLoader ) {
			window.WSSCD.ModuleLoader.load( stepName + '-loader', function() {
				if ( !  self.createStepInstanceFromFactory( stepName, deferred ) ) {
					deferred.reject( new Error( 'Step not found after loading' ) );
				}
			} );
		} else {
			// No module loader or step loader not registered - check if factory exists
			if ( !  this.createStepInstanceFromFactory( stepName, deferred ) ) {
				// Step modules are optional
				deferred.resolve();
			}
		}

		return deferred.promise();
	};


	/**
	 * Retrieve step data from state manager or wizard data
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.getStepData = function( stepName ) {
		// CRITICAL: In edit mode, ALWAYS use wizard state manager's runtime data
		// The state manager gets updated after each save, whereas window.wsscdWizardData
		// contains stale data from initial page load
		if ( this.modules.stateManager ) {
			var state = this.modules.stateManager.get();

			var isEditMode = state && ( state.wizardMode === 'edit' || state.campaignId );

			if ( state && state.stepData && state.stepData[stepName] ) {
				// In edit mode: Always return stepData from state manager (even if empty)
				// This ensures we use runtime-updated data, not stale PHP data
				if ( isEditMode ) {
					if ( window.WSSCD && window.WSSCD.Debug ) {
						window.WSSCD.Debug.log( '[WizardOrchestrator] Edit mode - using wizard state manager data for step:', stepName );
					}
					return state.stepData[stepName];
				}

				// In create mode: Only return if data is non-empty
				var hasData = false;
				if ( 'object' === typeof state.stepData[stepName] ) {
					for ( var key in state.stepData[stepName] ) {
						if ( Object.prototype.hasOwnProperty.call( state.stepData[stepName], key ) ) {
							hasData = true;
							break;
						}
					}
				}

				if ( hasData ) {
					return state.stepData[stepName];
				}
			}
		}

		return null;
	};

	/**
	 * Populate step fields with data
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.populateStepFields = function( stepName, orchestrator, stepData ) {
		if ( !  stepData ) {
			return;
		}

		if ( !  ( orchestrator.populateFields && 'function' === typeof orchestrator.populateFields ) ) {
			return;
		}

		try {
			orchestrator.populateFields( stepData );
		} catch ( e ) {
			console.error( '[WSSCD Wizard:Orchestrator] Failed to populate fields for step "' + stepName + '":', e );

			// Emit event for error handling
			if ( this.modules.eventBus ) {
				this.modules.eventBus.emit( 'step:populateError', {
					step: stepName,
					error: e,
					data: stepData
				} );
			}
		}
	};

	/**
	 * Initialize wizard step
	 * @param stepName
	 */
	WizardOrchestrator.prototype.initializeWizardStep = function( stepName ) {
		if ( !  stepName || 'string' !== typeof stepName ) {
			return;
		}

		var orchestrator = this.stepOrchestrators[stepName];

		if ( orchestrator && 'function' === typeof orchestrator.init ) {
			// Pass wizard and config to orchestrator init
			var config = {
				stepName: stepName,
				container: '.wsscd-wizard-step--' + stepName
			};

			orchestrator.init( this, config );

			// After init, populate with any saved data
			var stepData;

			// In edit mode, prefer wizard state manager (has correct structure from change tracker)
			// In create mode, prefer step-specific state (fresher from database)
			var state = this.modules.stateManager ? this.modules.stateManager.get() : null;
			var isEditMode = state && ( state.wizardMode === 'edit' || state.campaignId );

			if ( isEditMode ) {
				// Edit mode: Use wizard state manager (change tracker has correct structure)
				stepData = this.getStepData( stepName );
			} else {
				// Create mode: Prefer step-specific state over wizard state
				var stepStateVar = 'wsscd' + stepName.charAt(0).toUpperCase() + stepName.slice(1) + 'State';

				if ( window[stepStateVar] && window[stepStateVar].saved_data ) {
					// Use step-specific state (PHP-rendered, current from database)
					stepData = window[stepStateVar].saved_data;
				} else {
					// Fallback to wizard-level state (session-based)
					stepData = this.getStepData( stepName );
				}
			}

			this.populateStepFields( stepName, orchestrator, stepData );
		}

		// Update UI
		this.updateStepUI( stepName );

		// Emit event
		if ( this.modules.eventBus ) {
			this.modules.eventBus.emit( 'step:loaded', { step: stepName } );
		}
	};

	/**
	 * Update step UI
	 * @param stepName
	 */
	WizardOrchestrator.prototype.updateStepUI = function( stepName ) {
		$( '.wsscd-wizard-navigation .step' ).removeClass( 'active' );
		$( '.wsscd-wizard-navigation .step[data-step="' + stepName + '"]' ).addClass( 'active' );

		// Only remove 'active' class, preserve 'completed' class
		$( '.wsscd-wizard-steps li' ).removeClass( 'active' );
		$( '.wsscd-wizard-steps li[data-step-name="' + stepName + '"]' ).addClass( 'active' );

		$( '.wsscd-wizard-step' ).removeClass( 'active' );
		$( '#wsscd-step-' + stepName ).addClass( 'active' );

		var currentIndex = this.config.steps.indexOf( stepName );
		var progress = ( ( currentIndex + 1 ) / this.config.steps.length ) * 100;

		$( '.wsscd-progress-bar' ).css( 'width', progress + '%' );
		$( '.wsscd-progress-text' ).text( 'Step ' + ( currentIndex + 1 ) + ' of ' + this.config.steps.length );
	};

	/**
	 * Handle navigation request
	 * @param data
	 */
	WizardOrchestrator.prototype.handleNavigationRequest = function( data ) {
		// Handle data being passed as an array from jQuery trigger
		if ( Array.isArray( data ) && 0 < data.length ) {
			data = data[0];
		}

		var action = data.action;
		var targetStep = data.step || data.targetStep;


		// Handle completion
		if ( 'complete' === action ) {
			var saveAsDraft = data.saveAsDraft || false;
			this.completeWizard( saveAsDraft );
			return;
		}

		// Delegate to navigation service
		if ( this.modules.navigationService ) {
			// Mark as internal navigation to suppress beforeunload warning
			this.isInternalNavigation = true;

			if ( 'next' === action ) {
				this.modules.navigationService.navigateNext();
			} else if ( 'prev' === action ) {
				this.modules.navigationService.navigatePrev();
			} else if ( targetStep ) {
				this.modules.navigationService.navigateToStep( targetStep );
			}

			var self = this;
			setTimeout( function() {
				self.isInternalNavigation = false;
			}, 1000 );
		}
	};

	/**
	 * Handle wizard completion success
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.handleCompletionSuccess = function( response ) {
		// Hide fullscreen loader
		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.hide( 'wsscd-wizard-completion-loading' );
		}

		// Re-enable navigation after completion
		if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
			window.WSSCD.Wizard.Navigation.setNavigationState( false );
		}

		var campaignName = this.getCampaignName();

		// Determine if we're in edit mode
		var state = this.modules.stateManager ? this.modules.stateManager.get() : null;
		var isEditMode = state && ( state.wizardMode === 'edit' || state.campaignId );

		if ( this.modules.stateManager ) {
			this.modules.stateManager.set( {
				isProcessing: false,
				completionInProgress: false,
				completionSuccess: true,
				hasUnsavedChanges: false,
				completionData: {
					campaignId: response.campaignId,
					campaignName: campaignName,
					status: response.status || 'draft',
					message: response.message,
					redirectUrl: response.redirectUrl,
					isEditMode: isEditMode
				}
			} );
		}

		// Build completion event data
		var eventData = {
			campaignId: response.campaignId,
			campaignName: campaignName,
			status: response.status || 'draft',
			message: response.message,
			redirectUrl: response.redirectUrl,
			isEditMode: isEditMode
		};

		// Emit completion event for other components
		if ( this.modules.eventBus ) {
			this.modules.eventBus.emit( 'wizard:completed', eventData );
		}

		// Trigger document event for navigation component
		$( document ).trigger( 'wsscd:wizard:completed', [ eventData ] );

		// Redirect after delay
		this.scheduleRedirect( response.redirectUrl );
	};

	/**
	 * Handle wizard completion error
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.handleCompletionError = function( error ) {
		// Hide fullscreen loader
		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.hide( 'wsscd-wizard-completion-loading' );
		}

		// Re-enable navigation after error
		if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
			window.WSSCD.Wizard.Navigation.setNavigationState( false );
		}

		if ( this.modules.stateManager ) {
			this.modules.stateManager.set( {
				isProcessing: false,
				completionInProgress: false,
				completionSuccess: false
			} );
		}

		var errorMessage = error.message || error.error || 'An error occurred while creating the campaign.';

		// Emit error event for modal
		$( document ).trigger( 'wsscd:wizard:error', [ {
			message: errorMessage,
			error: error
		} ] );

		// Also handle through error handler for logging
		this.handleError( error, 'completion' );
	};

	/**
	 * Complete wizard
	 * @param saveAsDraft
	 */
	WizardOrchestrator.prototype.completeWizard = function( saveAsDraft ) {
		var self = this;
		var options = {
			saveAsDraft: saveAsDraft || false
		};

		// Show fullscreen loader instead of button/modal loader
		if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
			WSSCD.LoaderUtil.show( 'wsscd-wizard-completion-loading' );
		}

		// Disable navigation during completion process
		if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
			window.WSSCD.Wizard.Navigation.setNavigationState( true );
		}

		if ( saveAsDraft && this.modules.stateManager ) {
			var state = this.modules.stateManager.get();
			var isEditMode = state && ( state.wizardMode === 'edit' || state.campaignId );
			var currentStatus = state && state.currentCampaign ? state.currentCampaign.status : null;

			// Confirm if converting active campaign to draft
			if ( isEditMode && 'active' === currentStatus ) {
				var confirmed = window.confirm(
					'Saving as draft will deactivate your campaign and stop applying discounts. ' +
					'You can republish it later. Continue?'
				);

				if ( ! confirmed ) {
					// Hide loader if user cancelled
					if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
						WSSCD.LoaderUtil.hide( 'wsscd-wizard-completion-loading' );
					}

					// Re-enable navigation when user cancels
					if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
						window.WSSCD.Wizard.Navigation.setNavigationState( false );
					}

					return; // User cancelled
				}
			}
		}

		// Different validation for draft vs complete
		var validationPromise = saveAsDraft ? this.validateBasicInfo() : this.validateAllSteps();

		validationPromise
			.then( function( isValid ) {
				if ( !  isValid ) {
					throw new Error( saveAsDraft ?
						'Please provide a campaign name to save as draft' :
						'Please complete all required fields' );
				}

				// CRITICAL FIX: Save current step data (review step with launch_option) BEFORE completing
				// This ensures the radio button selection is persisted to the session
				var currentStep = self.getCurrentStep();
				if ( currentStep ) {
					var stepOrchestrator = self.getStepInstance( currentStep );
					if ( stepOrchestrator && 'function' === typeof stepOrchestrator.saveStep ) {
						return stepOrchestrator.saveStep();
					}
				}
				return Promise.resolve();
				} )
				.then( function() {
				// Note: Fullscreen loader is now shown, don't show modal loading state
				// $( document ).trigger( 'wsscd:wizard:completing' );

				if ( self.modules.stateManager ) {
					self.modules.stateManager.set( {
						isProcessing: true,
						completionInProgress: true
					} );
				}

				// Complete wizard via AJAX
				return WSSCD.Ajax.post( 'wsscd_complete_wizard', {
					saveAsDraft: options.saveAsDraft
				} );
			} )
			.then( function( response ) {
				self.handleCompletionSuccess( response );
			} )
			.catch( function( error ) {
				self.handleCompletionError( error );
			} );
	};

	/**
	 * Retry completion after error
	 */
	WizardOrchestrator.prototype.retryCompletion = function() {
		var saveAsDraft = false;
		if ( this.modules.stateManager ) {
			var completionData = this.modules.stateManager.get( 'completionData' );
			if ( completionData && completionData.status ) {
				saveAsDraft = 'draft' === completionData.status;
			}
		}

		// Retry the completion
		this.completeWizard( saveAsDraft );
	};

	/**
	 * Get campaign name from state manager (single source of truth)
	 */
	WizardOrchestrator.prototype.getCampaignName = function() {
		if ( this.modules.stateManager ) {
			var state = this.modules.stateManager.get();
			if ( state && state.stepData && state.stepData.basic && state.stepData.basic.name ) {
				return state.stepData.basic.name;
			}
		}

		return '';
	};

	/**
	 * Schedule redirect after completion
	 * @param _redirectUrl - redirectUrl parameter is passed through but not used here as modal handles actual redirect
	 */
	WizardOrchestrator.prototype.scheduleRedirect = function( _redirectUrl ) {
		// Note: Redirect delay is now controlled by completion modal (3 seconds)
		// This method is called immediately, modal handles the delay
	};

	/**
	 * Validate basic info (for draft saves)
	 */
	WizardOrchestrator.prototype.validateBasicInfo = function() {
		var deferred = $.Deferred();

		var basicData = this.getStepData( 'basic' );
		var campaignName = basicData && basicData.name ? basicData.name : null;

		deferred.resolve( campaignName && 0 < campaignName.trim().length );

		return deferred.promise();
	};

	/**
	 * Validate step data
	 * Basic validation for steps without loaded orchestrators
	 * @param stepName
	 * @param data
	 */
	WizardOrchestrator.prototype.validateStepData = function( stepName, data ) {
		if ( !  data ) {
			// No data means step hasn't been visited, which is okay except for basic
			return 'basic' !== stepName;
		}

		// Step-specific validation rules
		switch ( stepName ) {
			case 'basic':
				return !! ( data.name && 0 < data.name.trim().length );

			case 'products':
				return !! data.productSelectionType;

			case 'discounts':
				return !! data.discountType;

			case 'schedule':
				return undefined !== data.startType;

			case 'review':
				return true;

			default:
				return true;
		}
	};

	/**
	 * Create validation promise for unloaded step
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.createUnloadedStepValidationPromise = function( stepName ) {
		var stepDeferred = $.Deferred();
		var stepData = null;

		if ( this.modules.stateManager && 'function' === typeof this.modules.stateManager.get ) {
			var fullState = this.modules.stateManager.get();
			stepData = fullState.stepData ? fullState.stepData[stepName] : null;
		}

		// Basic validation - ensure required fields are present
		var isValid = this.validateStepData( stepName, stepData );
		stepDeferred.resolve( isValid );

		return stepDeferred.promise();
	};

	/**
	 * Validate all steps
	 */
	WizardOrchestrator.prototype.validateAllSteps = function() {
		var self = this;
		var deferred = $.Deferred();
		var promises = [];

		this.config.steps.forEach( function( stepName ) {
			var orchestrator = self.stepOrchestrators[stepName];
			if ( orchestrator && 'function' === typeof orchestrator.validateStep ) {
				promises.push( orchestrator.validateStep() );
			} else {
				// If orchestrator not loaded, validate based on collected data
				promises.push( self.createUnloadedStepValidationPromise( stepName ) );
			}
		} );

		$.when.apply( $, promises )
			.then( function() {
				var allValid = true;
				for ( var i = 0; i < arguments.length; i++ ) {
					if ( !  arguments[i] ) {
						allValid = false;
						break;
					}
				}
				deferred.resolve( allValid );
			} )
			.fail( function() {
				deferred.resolve( false );
			} );

		return deferred.promise();
	};


	/**
	 * Handle state change
	 *
	 * @param {object} changes - Change notification object from BaseState
	 *   For individual updates: {property, value, oldValue, newValue, state}
	 *   For batch updates: {batch: true, changes: {...}, state: {...}}
	 *   For reset: {reset: true, state: {...}}
	 */
	WizardOrchestrator.prototype.handleStateChange = function( changes ) {
		if ( !changes ) {
			return;
		}

		// Handle batch updates
		if ( changes.batch && changes.changes ) {
			// Check for isProcessing in batch changes
			if ( undefined !== changes.changes.isProcessing ) {
				if ( changes.changes.isProcessing ) {
					this.setLoading( true );
				} else {
					this.setLoading( false );
				}
			}

			// Handle step change in batch
			if ( changes.changes.currentStep ) {
				this.loadCurrentStep();
			}
		}
		// Handle individual property updates
		else if ( changes.property ) {
			// Update UI based on specific property
			if ( 'isProcessing' === changes.property ) {
				if ( changes.newValue ) {
					this.setLoading( true );
				} else {
					this.setLoading( false );
				}
			}

			// Handle step change
			if ( 'currentStep' === changes.property && changes.oldValue !== changes.newValue ) {
				this.loadCurrentStep();
			}
		}
		// Handle reset
		else if ( changes.reset ) {
			this.loadCurrentStep();
		}
	};

	/**
	 * Handle validation complete
	 * @param data
	 */
	WizardOrchestrator.prototype.handleValidationComplete = function( data ) {
		if ( !  data.valid && data.errors ) {
			var stepName = data.step || this.getCurrentStep();
			var orchestrator = this.stepOrchestrators[stepName];

			if ( orchestrator && 'function' === typeof orchestrator.showErrors ) {
				orchestrator.showErrors( data.errors );
			}
		}
	};

	/**
	 * Handle data saved
	 * @param _data
	 */
	WizardOrchestrator.prototype.handleDataSaved = function( _data ) {
		if ( this.modules.stateManager ) {
			this.modules.stateManager.set( {
				hasUnsavedChanges: false,
				lastSaveTime: new Date().toISOString()
			}, { silent: true } );
		}
	};

	/**
	 * Handle session expired
	 */
	WizardOrchestrator.prototype.handleSessionExpired = function() {
		// Stop timers
		this.cleanup();

		this.showError( 'Your session has expired. Please refresh the page.', 'error', 0 );

		// Disable UI
		$( '.wsscd-wizard-wrap' ).addClass( 'session-expired' );
	};

	/**
	 * Get current step using navigation service
	 */
	WizardOrchestrator.prototype.getCurrentStep = function() {
		if ( this.modules.navigationService && 'function' === typeof this.modules.navigationService.getCurrentStep ) {
			return this.modules.navigationService.getCurrentStep();
		}

		// Fallback if navigation service unavailable
		var urlParams = new URLSearchParams( window.location.search );
		var urlStep = urlParams.get( 'step' );
		var validSteps = [ 'basic', 'products', 'discounts', 'schedule', 'review' ];

		if ( urlStep && -1 !== validSteps.indexOf( urlStep ) ) {
			return urlStep;
		}

		return 'basic';
	};

	/**
	 * Collect current step data
	 */
	WizardOrchestrator.prototype.collectCurrentStepData = function() {
		var stepName = this.getCurrentStep();
		var stepOrchestrator = this.stepOrchestrators[stepName];

		if ( stepOrchestrator && 'function' === typeof stepOrchestrator.collectData ) {
			var data = stepOrchestrator.collectData();

			// Save collected data to state manager
			if ( this.modules.stateManager && data && 0 < Object.keys( data ).length ) {
				var currentState = this.modules.stateManager.get();
				var stepData = currentState.stepData || {};
				stepData[stepName] = data;
				this.modules.stateManager.set( { stepData: stepData } );
			}

			return data;
		}

		return {};
	};

	/**
	 * Collect data (required by BaseOrchestrator)
	 */
	WizardOrchestrator.prototype.collectData = function() {
		var data = {};

		// Collect from all steps
		for ( var stepName in this.stepOrchestrators ) {
			if ( Object.prototype.hasOwnProperty.call( this.stepOrchestrators, stepName ) ) {
				var orchestrator = this.stepOrchestrators[stepName];
				if ( orchestrator && 'function' === typeof orchestrator.collectData ) {
					data[stepName] = orchestrator.collectData();
				}
			}
		}

		return data;
	};

	/**
	 * Validate data (required by BaseOrchestrator)
	 * @param _data
	 */
	WizardOrchestrator.prototype.validateData = function( _data ) {
		// Wizard-level validation
		return {
			valid: true,
			errors: []
		};
	};

	/**
	 * Cleanup
	 */
	WizardOrchestrator.prototype.cleanup = function() {
		// Cleanup step orchestrators
		for ( var stepName in this.stepOrchestrators ) {
			if ( Object.prototype.hasOwnProperty.call( this.stepOrchestrators, stepName ) ) {
				var orchestrator = this.stepOrchestrators[stepName];
				if ( orchestrator && 'function' === typeof orchestrator.destroy ) {
					orchestrator.destroy();
				}
			}
		}

		// Call parent cleanup
		WSSCD.Shared.BaseOrchestrator.prototype.destroy.call( this );
	};

	/**
	 * Custom destroy
	 */
	WizardOrchestrator.prototype.onDestroy = function() {
		// Unbind window events including beforeunload
		$( window ).off( '.wizard' );
		$( window ).off( 'beforeunload.wizard' );
		$( document ).off( '.wizard' );

		// Unbind specific navigation and wizard events
		$( document ).off( 'wsscd:navigation:request wsscd:wizard:retry' );

		// Destroy state manager
		if ( this.modules.stateManager && 'function' === typeof this.modules.stateManager.destroy ) {
			this.modules.stateManager.destroy();
		}

		// Destroy navigation service
		if ( this.modules.navigationService && 'function' === typeof this.modules.navigationService.destroy ) {
			this.modules.navigationService.destroy();
		}

		this.stepOrchestrators = {};
	};

	// Service readiness system - implements proper dependency waiting
	var ServiceReadiness = {
		requiredServices: [ 'EventBus', 'BaseOrchestrator' ],
		readyServices: [],
		maxWaitTime: 10000, // 10 seconds timeout - increased for slow connections
		checkInterval: 250,  // Check every 250ms - reduced polling frequency

		init: function() {
			var self = this;

			for ( var i = 0; i < self.requiredServices.length; i++ ) {
				var serviceName = self.requiredServices[i];
				if ( self.isServiceReady( serviceName ) && -1 === self.readyServices.indexOf( serviceName ) ) {
					self.readyServices.push( serviceName );
				}
			}

			$( document ).on( 'wsscd:service:ready', function( event, serviceName ) {
				if ( -1 !== self.requiredServices.indexOf( serviceName ) &&
                    -1 === self.readyServices.indexOf( serviceName ) ) {
					self.readyServices.push( serviceName );
				}
			} );
		},

		// Extracted to reduce cognitive complexity
		isEventBusReady: function() {
			return !! ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.EventBus );
		},

		// Extracted to reduce cognitive complexity
		isBaseOrchestratorReady: function() {
			return !! ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseOrchestrator );
		},

		isServiceReady: function( serviceName ) {
			// First check if service was announced via event
			if ( -1 !== this.readyServices.indexOf( serviceName ) ) {
				return true;
			}

			switch( serviceName ) {
				case 'EventBus':
					return this.isEventBusReady();

				case 'BaseOrchestrator':
					return this.isBaseOrchestratorReady();

				default:
					return false;
			}
		},

		// Extracted to reduce cognitive complexity
		checkAllServicesReady: function() {
			var allReady = true;
			var serviceStatus = {};

			for ( var i = 0; i < this.requiredServices.length; i++ ) {
				var serviceName = this.requiredServices[i];
				var isReady = this.isServiceReady( serviceName );
				serviceStatus[serviceName] = isReady;
				if ( !  isReady ) {
					allReady = false;
				}
			}

			return {
				allReady: allReady,
				serviceStatus: serviceStatus
			};
		},

		// Handle service timeout
		// Extracted to reduce cognitive complexity
		handleServiceTimeout: function( serviceStatus, onSuccess ) {
			var missingServices = [];
			for ( var i = 0; i < this.requiredServices.length; i++ ) {
				var serviceName = this.requiredServices[i];
				if ( ! serviceStatus[serviceName] ) {
					missingServices.push( serviceName );
				}
			}

			console.error( '[WSSCD Wizard:Orchestrator] Service initialization timeout. Missing:', missingServices );

			if ( onSuccess ) {
				onSuccess();
			}
		},

		// Wait for all required services (ES5 compatible)
		waitForServices: function( onSuccess, _onError ) {
			var self = this;
			var startTime = Date.now();

			function checkServices() {
				var result = self.checkAllServicesReady();

				if ( result.allReady ) {
					if ( onSuccess ) {
						onSuccess();
					}
					return;
				}

				if ( Date.now() - startTime > self.maxWaitTime ) {
					self.handleServiceTimeout( result.serviceStatus, onSuccess );
					return;
				}

				// Continue checking
				setTimeout( checkServices, self.checkInterval );
			}

			checkServices();
		}
	};

	function createOrchestratorWhenReady() {
		ServiceReadiness.waitForServices(
			// Success callback
			function() {
				WSSCD.Wizard.Orchestrator = new WizardOrchestrator();

				// Emit orchestrator ready event
				$( document ).trigger( 'wsscd:orchestrator:ready' );
			},
			// Error callback
			function( _error ) {
				// Service initialization timeout
			}
		);
	}

	$( document ).ready( function() {
		ServiceReadiness.init();

		// Use proper service readiness system instead of retry logic
		createOrchestratorWhenReady();
	} );

} )( jQuery );