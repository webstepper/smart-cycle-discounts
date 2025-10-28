/**
 * Wizard Orchestrator
 *
 * Central orchestrator for the wizard system following the established orchestrator pattern.
 * Coordinates all wizard modules and delegates responsibilities appropriately.
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
		if ( !  SCD.Shared || ! SCD.Shared.BaseOrchestrator ) {
			return;
		}

		// Call parent constructor
		SCD.Shared.BaseOrchestrator.call( this, 'wizard', {
			// Module factories
			stateManager: function() {
				return SCD.Wizard.StateManager;
			},
			eventBus: function() {
				return SCD.Wizard.EventBus;
			},
			navigationService: function() {
				return SCD.Wizard.Navigation;
			},
			lifecycleManager: function() {
				return SCD.Wizard.LifecycleManager;
			},
			ajaxService: function() {
				return SCD.Ajax || null;
			},
			uiService: function() {
				return SCD.Shared ? SCD.Shared.UI : null;
			}
		} );

		// Wizard-specific properties
		this.stepOrchestrators = {};
		this.isInternalNavigation = false; // Track wizard internal navigation
		this.config = {
			steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			sessionTimeout: 3600000
		};
	};

	// Extend BaseOrchestrator
	WizardOrchestrator.prototype = Object.create( SCD.Shared.BaseOrchestrator.prototype );
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
		return SCD.Shared.BaseOrchestrator.prototype.init.call( this, wizard, config );
	};

	/**
	 * Get default configuration
	 */
	WizardOrchestrator.prototype.getDefaultConfig = function() {
		return $.extend( {}, SCD.Shared.BaseOrchestrator.prototype.getDefaultConfig.call( this ), {
			steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			sessionTimeout: 3600000,
			validateOnNavigation: true,
			allowBackNavigation: true
		} );
	};

	/**
	 * Clear session storage for new campaigns
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.clearSessionStorage = function() {
		if ( !  window.sessionStorage ) {
			return;
		}

		sessionStorage.removeItem( 'scd_wizard_state' );

		// Clear any other SCD-related sessionStorage items
		var keysToRemove = [];
		for ( var i = 0; i < sessionStorage.length; i++ ) {
			var key = sessionStorage.key( i );
			if ( key && 0 === key.indexOf( 'scd_' ) ) {
				keysToRemove.push( key );
			}
		}
		keysToRemove.forEach( function( key ) {
			sessionStorage.removeItem( key );
		} );
	};

	/**
	 * Initialize state from window data
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.initializeStateFromWindowData = function() {
		if ( !  window.scdWizardData ) {
			return;
		}

		this.config.nonce = window.scdWizardData.nonce || window.scdWizardData.wizard_nonce;

		// Initialize state manager with data
		if ( !  this.modules.stateManager ) {
			return;
		}

		var initData = {
			currentStep: window.scdWizardData.current_step || 'basic',
			nonce: this.config.nonce
		};

		// Load completed steps if available
		if ( window.scdWizardData.current_campaign && window.scdWizardData.current_campaign.completedSteps ) {
			initData.completedSteps = window.scdWizardData.current_campaign.completedSteps;
		}

		// Check if we're in edit mode (URL has intent=edit or id parameter)
		var urlParams = new URLSearchParams( window.location.search );
		var isEditMode = urlParams.get( 'intent' ) === 'edit' || ( urlParams.get( 'id' ) && urlParams.get( 'intent' ) !== 'new' );

		// Load step data from current campaign
		// PHP uses snake_case (current_campaign) which gets auto-converted to camelCase (currentCampaign)
		var currentCampaign = window.scdWizardData.currentCampaign || window.scdWizardData.current_campaign;

		if ( window.SCD && window.SCD.Debug ) {
			window.SCD.Debug.log( '[WizardOrchestrator] isEditMode:', isEditMode );
			window.SCD.Debug.log( '[WizardOrchestrator] currentCampaign:', currentCampaign );
			window.SCD.Debug.log( '[WizardOrchestrator] window.scdWizardData:', window.scdWizardData );
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

			// Load campaign ID ONLY if in edit mode
			if ( isEditMode ) {
				// Get campaign ID from currentCampaign or URL parameter
				var campaignId = currentCampaign.campaignId || urlParams.get( 'id' );
				if ( campaignId ) {
					initData.campaignId = parseInt( campaignId, 10 );
					initData.wizardMode = 'edit';

					if ( window.SCD && window.SCD.Debug ) {
						window.SCD.Debug.log( '[WizardOrchestrator] Setting edit mode. Campaign ID: ' + initData.campaignId );
					}
				}
			}
		}

		if ( window.SCD && window.SCD.Debug ) {
			window.SCD.Debug.log( '[WizardOrchestrator] Initializing StateManager with:', initData );
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

		// Initialize lifecycle manager
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
	 * Initialize orchestrator
	 */
	WizardOrchestrator.prototype.onInit = function() {
		var self = this;

		// Check if we're starting a new campaign
		var urlParams = new URLSearchParams( window.location.search );
		var intent = urlParams.get( 'intent' );

		// If intent=new, clear all client-side state first
		if ( 'new' === intent ) {
			this.clearSessionStorage();

			// Reset state manager to default state
			if ( this.modules.stateManager ) {
				this.modules.stateManager.reset( { keepStorage: false } );
			}
		}

		// Initialize state from window data
		this.initializeStateFromWindowData();

		// Initialize and register lifecycle manager
		this.initializeLifecycleManager();

		// Initialize current step
		this.loadCurrentStep()
			.then( function() {
				// Set lifecycle to ready
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
		$( document ).on( 'scd:navigation:request', function( e, data ) {
			self.handleNavigationRequest( data );
		} );

		// Listen for retry event from completion modal
		$( document ).on( 'scd:wizard:retry', function() {
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
			this.modules.stateManager.subscribe( function( state, oldState, changes ) {
				self.handleStateChange( state, oldState, changes );
			} );
		}

		// beforeunload warning removed - navigation saves handle data protection
		// Navigation saves work perfectly without false warnings

		// Track form changes to set hasUnsavedChanges (for UI state only)
		$( document ).on( 'change.wizard input.wizard', 'input, select, textarea', function( e ) {
			// Skip if this is a navigation button or non-form element
			if ( 0 < $( e.target ).closest( '.scd-wizard-navigation' ).length ) {
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
		if ( ! ( 'function' === typeof SCD.Steps.hasFactory && SCD.Steps.hasFactory( stepName ) ) ) {
			return null;
		}

		try {
			var instance = SCD.Steps.createStep( stepName );
			if ( instance ) {
				this.stepOrchestrators[stepName] = instance;
				return instance;
			}
		} catch ( e ) {
			// Factory creation failed
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
		if ( !  window.SCD.Steps[className] ) {
			return null;
		}

		var OrchestratorClass = window.SCD.Steps[className];
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

		// Check if step orchestrator already loaded
		if ( this.stepOrchestrators[stepName] ) {
			this.initializeWizardStep( stepName );
			deferred.resolve();
			return deferred.promise();
		}

		// Check if step is available in registry
		if ( !  ( window.SCD && window.SCD.Steps ) ) {
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
		if ( !  ( SCD.Steps.hasFactory && SCD.Steps.hasFactory( stepName ) ) ) {
			return false;
		}

		var instance = SCD.Steps.createStep( stepName );
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

		// Check if module loader exists and if the step loader is registered
		var hasModuleLoader = window.SCD && window.SCD.ModuleLoader && window.SCD.ModuleLoader.has;
		var hasStepLoader = hasModuleLoader && window.SCD.ModuleLoader.has( stepName + '-loader' );

		if ( hasStepLoader ) {
			window.SCD.ModuleLoader.load( stepName + '-loader', function() {
				// Check if loaded and create instance
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
		// First check state manager (but only if it has non-empty data)
		if ( this.modules.stateManager ) {
			var state = this.modules.stateManager.get();
			if ( state && state.stepData && state.stepData[stepName] ) {
				// Check if the data is actually non-empty (not just an empty object)
				var hasData = false;
				if ( 'object' === typeof state.stepData[stepName] ) {
					// Check if object has any own properties
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

		// If no data in state manager, check wizard data
		if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.data && window.SCD.Wizard.data[stepName] ) {
			return window.SCD.Wizard.data[stepName];
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
			console.error( '[SCD Wizard:Orchestrator] Failed to populate fields for step "' + stepName + '":', e );

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
		// Validate stepName
		if ( !  stepName || 'string' !== typeof stepName ) {
			return;
		}

		var orchestrator = this.stepOrchestrators[stepName];

		if ( orchestrator && 'function' === typeof orchestrator.init ) {
			// Pass wizard and config to orchestrator init
			var config = {
				stepName: stepName,
				container: '.scd-wizard-step--' + stepName
			};

			orchestrator.init( this, config );

			// After init, populate with any saved data
			var stepData = this.getStepData( stepName );
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
		// Update old navigation structure (if it exists)
		$( '.scd-wizard-navigation .step' ).removeClass( 'active' );
		$( '.scd-wizard-navigation .step[data-step="' + stepName + '"]' ).addClass( 'active' );

		// Update new progress step indicators
		// Only remove 'active' class, preserve 'completed' class
		$( '.scd-wizard-steps li' ).removeClass( 'active' );
		$( '.scd-wizard-steps li[data-step-name="' + stepName + '"]' ).addClass( 'active' );

		// Update content
		$( '.scd-wizard-step' ).removeClass( 'active' );
		$( '#scd-step-' + stepName ).addClass( 'active' );

		// Update progress
		var currentIndex = this.config.steps.indexOf( stepName );
		var progress = ( ( currentIndex + 1 ) / this.config.steps.length ) * 100;

		$( '.scd-progress-bar' ).css( 'width', progress + '%' );
		$( '.scd-progress-text' ).text( 'Step ' + ( currentIndex + 1 ) + ' of ' + this.config.steps.length );
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
			if ( 'next' === action ) {
				this.modules.navigationService.navigateNext();
			} else if ( 'prev' === action ) {
				this.modules.navigationService.navigatePrev();
			} else if ( targetStep ) {
				this.modules.navigationService.navigateToStep( targetStep );
			}
		}
	};

	/**
	 * Handle wizard completion success
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.handleCompletionSuccess = function( response ) {
		var campaignName = this.getCampaignName();

		// Update state with completion data
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
					redirectUrl: response.redirectUrl
				}
			} );
		}

		// Build completion event data
		var eventData = {
			campaignId: response.campaignId,
			campaignName: campaignName,
			status: response.status || 'draft',
			message: response.message,
			redirectUrl: response.redirectUrl
		};

		// Emit completion event for other components
		if ( this.modules.eventBus ) {
			this.modules.eventBus.emit( 'wizard:completed', eventData );
		}

		// Trigger document event for navigation component
		$( document ).trigger( 'scd:wizard:completed', [ eventData ] );

		// Redirect after delay
		this.scheduleRedirect( response.redirectUrl );
	};

	/**
	 * Handle wizard completion error
	 * Extracted to reduce cognitive complexity
	 */
	WizardOrchestrator.prototype.handleCompletionError = function( error ) {
		// Clear processing state on error
		if ( this.modules.stateManager ) {
			this.modules.stateManager.set( {
				isProcessing: false,
				completionInProgress: false,
				completionSuccess: false
			} );
		}

		// Get error message
		var errorMessage = error.message || error.error || 'An error occurred while creating the campaign.';

		// Emit error event for modal
		$( document ).trigger( 'scd:wizard:error', [ {
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

		// Different validation for draft vs complete
		var validationPromise = saveAsDraft ? this.validateBasicInfo() : this.validateAllSteps();

		validationPromise
			.then( function( isValid ) {
				if ( !  isValid ) {
					throw new Error( saveAsDraft ?
						'Please provide a campaign name to save as draft' :
						'Please complete all required fields' );
				}

				// Emit completing event for modal to show loading state
				$( document ).trigger( 'scd:wizard:completing' );

				// Set processing state
				if ( self.modules.stateManager ) {
					self.modules.stateManager.set( {
						isProcessing: true,
						completionInProgress: true
					} );
				}

				// Complete wizard via AJAX
				return SCD.Ajax.post( 'scd_complete_wizard', {
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
		// Get the last attempted completion type from state
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
	 * Get campaign name from form
	 */
	WizardOrchestrator.prototype.getCampaignName = function() {
		// Try to get from state manager first
		if ( this.modules.stateManager ) {
			var state = this.modules.stateManager.get();
			if ( state && state.stepData && state.stepData.basic && state.stepData.basic.name ) {
				return state.stepData.basic.name;
			}
		}

		// Try to get from wizard data
		if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.data && window.SCD.Wizard.data.name ) {
			return window.SCD.Wizard.data.name;
		}

		// Fall back to DOM
		var $field = $( '[name="name"]' );
		if ( $field.length ) {
			return $field.val() || '';
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
		// No beforeunload warning to suppress (removed with Smart Save system)
	};

	/**
	 * Validate basic info (for draft saves)
	 */
	WizardOrchestrator.prototype.validateBasicInfo = function() {
		var deferred = $.Deferred();

		// Check if basic step orchestrator exists
		var basicOrchestrator = this.stepOrchestrators.basic;
		if ( basicOrchestrator && 'function' === typeof basicOrchestrator.validateStep ) {
			basicOrchestrator.validateStep()
				.then( function( isValid ) {
					deferred.resolve( isValid );
				} )
				.fail( function() {
					deferred.resolve( false );
				} );
		} else {
			// No basic orchestrator, check if we have campaign name field
			var $field = $( '[name="name"]' );
			var campaignName = $field.val();
			deferred.resolve( campaignName && 0 < campaignName.trim().length );
		}

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

		// Try to get step data from state manager
		if ( this.modules.stateManager && 'function' === typeof this.modules.stateManager.get ) {
			var fullState = this.modules.stateManager.get();
			stepData = fullState.stepData ? fullState.stepData[stepName] : null;
		} else if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.data ) {
			// Fallback to global wizard data
			stepData = window.SCD.Wizard.data[stepName] || null;
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

		// Validate each step
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
	 * @param state
	 * @param oldState
	 * @param changes
	 */
	WizardOrchestrator.prototype.handleStateChange = function( state, oldState, changes ) {
		// Update UI based on state
		if ( undefined !== changes.isProcessing ) {
			if ( changes.isProcessing ) {
				this.setLoading( true );
			} else {
				this.setLoading( false );
			}
		}

		// Handle step change
		if ( changes.currentStep && oldState.currentStep !== changes.currentStep ) {
			this.loadCurrentStep();
		}
	};

	/**
	 * Handle validation complete
	 * @param data
	 */
	WizardOrchestrator.prototype.handleValidationComplete = function( data ) {
		if ( !  data.valid && data.errors ) {
			// Show validation errors
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
		// Update state
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

		// Show error
		this.showError( 'Your session has expired. Please refresh the page.', 'error', 0 );

		// Disable UI
		$( '.scd-wizard-wrapper' ).addClass( 'session-expired' );
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
		SCD.Shared.BaseOrchestrator.prototype.destroy.call( this );
	};

	/**
	 * Custom destroy
	 */
	WizardOrchestrator.prototype.onDestroy = function() {
		// Unbind window events
		$( window ).off( '.wizard' );
		$( document ).off( '.wizard' );

		// Unbind specific navigation and wizard events
		$( document ).off( 'scd:navigation:request scd:wizard:retry' );

		// Destroy state manager
		if ( this.modules.stateManager && 'function' === typeof this.modules.stateManager.destroy ) {
			this.modules.stateManager.destroy();
		}

		// Destroy navigation service
		if ( this.modules.navigationService && 'function' === typeof this.modules.navigationService.destroy ) {
			this.modules.navigationService.destroy();
		}

		// Clear references
		this.stepOrchestrators = {};
	};

	// Service readiness system - implements proper dependency waiting
	var ServiceReadiness = {
		requiredServices: [ 'EventBus', 'BaseOrchestrator' ],
		readyServices: [],
		maxWaitTime: 10000, // 10 seconds timeout - increased for slow connections
		checkInterval: 250,  // Check every 250ms - reduced polling frequency

		// Initialize service ready event listeners
		init: function() {
			var self = this;

			// Check for already-loaded services before setting up listener
			for ( var i = 0; i < self.requiredServices.length; i++ ) {
				var serviceName = self.requiredServices[i];
				if ( self.isServiceReady( serviceName ) && -1 === self.readyServices.indexOf( serviceName ) ) {
					self.readyServices.push( serviceName );
				}
			}

			$( document ).on( 'scd:service:ready', function( event, serviceName ) {
				if ( -1 !== self.requiredServices.indexOf( serviceName ) &&
                    -1 === self.readyServices.indexOf( serviceName ) ) {
					self.readyServices.push( serviceName );
				}
			} );
		},

		// Check if EventBus is ready
		// Extracted to reduce cognitive complexity
		isEventBusReady: function() {
			var windowCheck = !! ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.EventBus );
			var localCheck = !! ( SCD && SCD.Wizard && SCD.Wizard.EventBus );
			return windowCheck || localCheck;
		},

		// Check if BaseOrchestrator is ready
		// Extracted to reduce cognitive complexity
		isBaseOrchestratorReady: function() {
			var windowCheck = !! ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseOrchestrator );
			var localCheck = !! ( SCD && SCD.Shared && SCD.Shared.BaseOrchestrator );
			return windowCheck || localCheck;
		},

		// Check if a specific service is ready
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

		// Check all services readiness
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

			console.error( '[SCD Wizard:Orchestrator] Service initialization timeout. Missing:', missingServices );

			// Initialize anyway to prevent complete failure
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

				// Check timeout
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

	// Create orchestrator when services are ready (ES5 compatible)
	function createOrchestratorWhenReady() {
		ServiceReadiness.waitForServices(
			// Success callback
			function() {
				SCD.Wizard.Orchestrator = new WizardOrchestrator();

				// Emit orchestrator ready event
				$( document ).trigger( 'scd:orchestrator:ready' );
			},
			// Error callback
			function( _error ) {
				// Service initialization timeout
			}
		);
	}

	$( document ).ready( function() {
		// Initialize service readiness system
		ServiceReadiness.init();

		// Use proper service readiness system instead of retry logic
		createOrchestratorWhenReady();
	} );

} )( jQuery );