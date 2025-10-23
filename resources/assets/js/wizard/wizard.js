/**
 * Smart Cycle Discounts - Wizard Facade
 *
 * Thin facade that provides public API and delegates to the wizard orchestrator.
 * Maintains backward compatibility for existing code.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Create namespace
	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};

	/**
	 * Wizard Facade
	 *
	 * Public API that delegates to the orchestrator
	 * Preserve existing properties (like EventBus, PersistenceService)
	 */
	SCD.Wizard = SCD.Utils.extend( SCD.Wizard || {}, {
		// Constants
		EVENT_PREFIX: 'scd:wizard:',

		// Reference to the orchestrator (lazy loaded) - using camelCase per CLAUDE.md
		orchestrator: null,

		/**
		 * Get orchestrator instance - renamed to camelCase per CLAUDE.md
		 * @private
		 */
		getOrchestrator: function() {
			if ( !this.orchestrator && SCD.Wizard.Orchestrator ) {
				this.orchestrator = SCD.Wizard.Orchestrator;
			}
			return this.orchestrator;
		},

		/**
		 * Initialize wizard
		 * Delegates to orchestrator
		 */
		init: function() {
			// Hide WordPress screen options only if we're in the wizard
			if ( 0 < $( '.scd-wizard-page' ).length ) {
				$( '#screen-meta-links' ).hide();
				$( '#screen-meta' ).hide();
			}

			// Theme colors are handled by shared/theme-color-init.js
			// No need to set them again here

			var orchestrator = this.getOrchestrator();
			if ( orchestrator && 'function' === typeof orchestrator.init ) {
				orchestrator.init();
			}

			// Load session data if continuing a draft
			// Do this after a slight delay to ensure all services are ready
			if ( this.loadFromSession ) {
				var self = this;
				setTimeout( function() {
					self.loadSessionData();
				}, 100 );
			}

			// Orchestrator not available
		},

		/**
		 * Go to next step
		 * Delegates to navigation service
		 */
		goToNextStep: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			var navigationService = orchestrator.modules && orchestrator.modules.navigationService;
			if ( !navigationService ) {return;}

			navigationService.navigateNext();
		},

		/**
		 * Go to previous step
		 * Delegates to navigation service
		 */
		goToPreviousStep: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			var navigationService = orchestrator.modules && orchestrator.modules.navigationService;
			if ( !navigationService ) {return;}

			navigationService.navigatePrevious();
		},

		/**
		 * Go to specific step
		 * Delegates to navigation service
		 * @param stepName
		 */
		goToStep: function( stepName ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			var navigationService = orchestrator.modules && orchestrator.modules.navigationService;
			if ( !navigationService ) {return;}

			navigationService.navigateToStep( stepName );
		},

		/**
		 * Validate current step
		 * Delegates to validation service
		 */
		validateCurrentStep: function() {
			var orchestrator = this.getOrchestrator();

			if ( !orchestrator ) {
				return $.Deferred().resolve( true ).promise();
			}

			var currentStep = this.getCurrentStep();
			
			if ( ! currentStep ) {
				return $.Deferred().resolve( true ).promise();
			}

			// Try to use step orchestrator's validation first
			if ( orchestrator.getStepInstance ) {
				var stepOrchestrator = orchestrator.getStepInstance( currentStep );
				
				if ( stepOrchestrator && typeof stepOrchestrator.validateStep === 'function' ) {
					return stepOrchestrator.validateStep();
				}
			}

			// Fallback to ValidationManager for basic HTML5 validation
			if ( window.SCD && window.SCD.ValidationManager ) {
				var result = window.SCD.ValidationManager.validateStep( currentStep );
				// ValidationManager returns plain object { ok, errors, clean }, wrap in Promise
				return $.Deferred().resolve( result.ok ).promise();
			}

			return $.Deferred().resolve( true ).promise();
		},

		/**
		 * Save current step
		 * Delegates to persistence service
		 */
		saveCurrentStep: function() {
			var orchestrator = this.getOrchestrator();
			if ( orchestrator ) {
				var stepName = this.getCurrentStep();
				var stepData = this.collectStepData();

				// Use persistence service directly to save and return promise
				var persistenceService = orchestrator.modules && orchestrator.modules.persistenceService;
				if ( persistenceService ) {
					return persistenceService.saveStepData( stepName, stepData );
				}
			}

			// Return resolved promise if no orchestrator
			return $.Deferred().resolve( true ).promise();
		},

		/**
		 * Complete wizard
		 * Delegates to orchestrator
		 */
		completeWizard: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			if ( 'function' === typeof orchestrator.completeWizard ) {
				orchestrator.completeWizard();
			}
		},


		/**
		 * Show loader
		 * Delegates to orchestrator
		 */
		showLoader: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			if ( 'function' === typeof orchestrator.setLoading ) {
				orchestrator.setLoading( true );
			}
		},

		/**
		 * Hide loader
		 * Delegates to orchestrator
		 */
		hideLoader: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			if ( 'function' === typeof orchestrator.setLoading ) {
				orchestrator.setLoading( false );
			}
		},

		/**
		 * API Methods for Step Modules
		 * These methods provide backward compatibility for step modules
		 */

		/**
		 * Get data for a specific step
		 * Delegates to StateManager through orchestrator
		 * @param stepName
		 */
		getData: function( stepName ) {
			return this.getStepData( stepName );
		},

		/**
		 * Get data for a specific step
		 * Delegates to StateManager
		 * @param stepName
		 */
		getStepData: function( stepName ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return {};}

			var stateManager = orchestrator.modules && orchestrator.modules.stateManager;
			if ( !stateManager ) {return {};}

			var allData = stateManager.get( 'stepData' ) || {};
			return allData[stepName] || {};
		},

		/**
		 * Collect current step data
		 * For navigation compatibility
		 */
		collectStepData: function() {
			var orchestrator = this.getOrchestrator();
			if ( orchestrator ) {
				var stepName = this.getCurrentStep();
				var stepOrchestrator = orchestrator.stepOrchestrators[stepName];
				if ( stepOrchestrator && 'function' === typeof stepOrchestrator.collectData ) {
					return stepOrchestrator.collectData();
				}
			}

			// Step orchestrator not available for data collection
			return {};
		},

		/**
		 * Set data for a specific step
		 * Delegates to StateManager
		 * @param stepName
		 * @param data
		 */
		setStepData: function( stepName, data ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			var stateManager = orchestrator.modules && orchestrator.modules.stateManager;
			if ( !stateManager ) {return;}

			var allData = stateManager.get( 'stepData' ) || {};
			allData[stepName] = data;
			stateManager.set( { stepData: allData } );
		},

		/**
		 * Mark wizard as having unsaved changes
		 * Delegates to StateManager
		 */
		markAsChanged: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			var stateManager = orchestrator.modules && orchestrator.modules.stateManager;
			if ( !stateManager ) {return;}

			stateManager.set( { hasUnsavedChanges: true } );
		},

		/**
		 * Save progress for a specific step
		 * Delegates to persistence service
		 * @param stepName
		 * @param data
		 */
		saveProgress: function( stepName, data ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {
				return $.Deferred().resolve().promise();
			}

			var persistenceService = orchestrator.modules && orchestrator.modules.persistenceService;
			if ( !persistenceService ) {
				return $.Deferred().resolve().promise();
			}

			return persistenceService.saveStepData( stepName, data );
		},

		/**
		 * Navigate to a specific step
		 * Delegates to navigation service
		 * @param stepName
		 */
		navigateToStep: function( stepName ) {
			this.goToStep( stepName );
		},

		/**
		 * Get current step name
		 * Delegates to StateManager
		 */
		getCurrentStep: function() {
			// URL is the single source of truth
			var urlParams = new URLSearchParams( window.location.search );
			var urlStep = urlParams.get( 'step' );

			// Validate step
			var validSteps = [ 'basic', 'products', 'discounts', 'schedule', 'review' ];
			if ( urlStep && -1 !== validSteps.indexOf( urlStep ) ) {
				return urlStep;
			}

			return 'basic';
		},

		/**
		 * Event emitter methods for compatibility
		 * Delegates to EventBus
		 * @param event
		 * @param callback
		 */
		on: function( event, callback ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {
				$( document ).on( this.EVENT_PREFIX + event, callback );
				return;
			}

			var eventBus = orchestrator.modules && orchestrator.modules.eventBus;
			if ( eventBus ) {
				eventBus.on( 'wizard:' + event, callback );
			} else {
				$( document ).on( this.EVENT_PREFIX + event, callback );
			}
		},

		off: function( event, callback ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {
				$( document ).off( this.EVENT_PREFIX + event, callback );
				return;
			}

			var eventBus = orchestrator.modules && orchestrator.modules.eventBus;
			if ( eventBus ) {
				eventBus.off( 'wizard:' + event, callback );
			} else {
				$( document ).off( this.EVENT_PREFIX + event, callback );
			}
		},

		trigger: function( event, data ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {
				$( document ).trigger( this.EVENT_PREFIX + event, data );
				return;
			}

			var eventBus = orchestrator.modules && orchestrator.modules.eventBus;
			if ( eventBus ) {
				eventBus.emit( 'wizard:' + event, data );
			} else {
				$( document ).trigger( this.EVENT_PREFIX + event, data );
			}
		},

		/**
		 * Handle error
		 * Delegates to orchestrator
		 * @param error
		 */
		handleError: function( error ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			if ( 'function' === typeof orchestrator.handleError ) {
				orchestrator.handleError( error );
			}
		},

		/**
		 * Set state (for components that directly set state)
		 * @param {object} state - State to set
		*/
		setState: function( state ) {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {return;}

			var stateManager = orchestrator.modules && orchestrator.modules.stateManager;
			if ( !stateManager ) {return;}

			stateManager.set( state );
		},

		/**
		 * Display array of validation errors
		 * Extracted to reduce cognitive complexity
		 */
		displayArrayErrors: function( errors ) {
			for ( var i = 0; i < errors.length; i++ ) {
				SCD.Shared.NotificationService.error( errors[i].message || errors[i] );
			}
		},

		/**
		 * Display object of field validation errors
		 * Extracted to reduce cognitive complexity
		 */
		displayFieldErrors: function( errors ) {
			for ( var field in errors ) {
				if ( Object.prototype.hasOwnProperty.call( errors, field ) ) {
					SCD.Shared.NotificationService.error( field + ': ' + errors[field] );
				}
			}
		},

		/**
		 * Show validation errors (for navigation)
		 */
		showValidationErrors: function() {
			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {
				return;
			}

			// Use ValidationManager to get validation state
			if ( !window.SCD || !window.SCD.ValidationManager ) {
				return;
			}

			var currentStep = this.getCurrentStep();
			var validationState = window.SCD.ValidationManager.getValidationState( currentStep );

			if ( !validationState || validationState.valid ) {
				return;
			}

			var errors = validationState.errors;
			if ( !errors ) {
				return;
			}

			if ( Array.isArray( errors ) ) {
				this.displayArrayErrors( errors );
			} else {
				this.displayFieldErrors( errors );
			}
		},

		/**
		 * Update state manager with step data from session
		 * Extracted to reduce cognitive complexity
		 */
		updateStateManagerWithSteps: function( steps ) {
			if ( !( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) ) {
				return;
			}

			var allStepData = {};
			for ( var stepName in steps ) {
				if ( Object.prototype.hasOwnProperty.call( steps, stepName ) ) {
					allStepData[stepName] = steps[stepName];

					// Also store in wizard data
					if ( !this.data ) {
						this.data = {};
					}
					this.data[stepName] = steps[stepName];
				}
			}

			// Update state manager with all step data at once
			window.SCD.Wizard.StateManager.set( { stepData: allStepData } );
		},

		/**
		 * Populate current step fields from session data
		 * Extracted to reduce cognitive complexity
		 */
		populateCurrentStepFromSession: function( steps ) {
			var currentStep = this.getCurrentStep();
			if ( !steps[currentStep] ) {
				return;
			}

			var orchestrator = this.getOrchestrator();
			if ( !orchestrator ) {
				return;
			}

			if ( orchestrator.stepOrchestrators && orchestrator.stepOrchestrators[currentStep] ) {
				var stepOrchestrator = orchestrator.stepOrchestrators[currentStep];
				if ( stepOrchestrator.populateFields ) {
					stepOrchestrator.populateFields( steps[currentStep] );
				}
			} else {
				// Step orchestrator might not be loaded yet, trigger a reload
				orchestrator.loadCurrentStep();
			}
		},

		/**
		 * Update completed steps from session data
		 * Extracted to reduce cognitive complexity
		 */
		updateCompletedStepsFromSession: function( completedSteps ) {
			if ( !( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) ) {
				return;
			}

			window.SCD.Wizard.StateManager.set( {
				completedSteps: completedSteps
			} );

			// Update UI to reflect completed steps
			if ( window.SCD.Wizard.Navigation &&
			     window.SCD.Wizard.Navigation.updateCompletedSteps &&
			     'function' === typeof window.SCD.Wizard.Navigation.updateCompletedSteps ) {
				window.SCD.Wizard.Navigation.updateCompletedSteps( completedSteps );
			}
		},

		/**
		 * Load session data when continuing a draft
		 */
		loadSessionData: function() {
			var self = this;

			// Get persistence service
			var persistenceService = null;
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.PersistenceService ) {
				persistenceService = window.SCD.Wizard.PersistenceService;
			}

			if ( !persistenceService || !persistenceService.loadSessionData ) {
				console.error( '[SCD Wizard] Persistence service not available for loading session' );
				return;
			}

			// Load session data
			persistenceService.loadSessionData()
				.then( function( response ) {
					if ( !( response && response.data ) ) {
						return;
					}

					// Extract step data from session
					if ( response.data.steps ) {
						self.updateStateManagerWithSteps( response.data.steps );
						self.populateCurrentStepFromSession( response.data.steps );
					}

					// Update other session properties
					// Server automatically converts snake_case to camelCase
					if ( response.data.completedSteps ) {
						self.updateCompletedStepsFromSession( response.data.completedSteps );
					}
				} )
				.catch( function( error ) {
					console.error( '[SCD Wizard] Failed to load session data:', error );
					SCD.Shared.NotificationService.error( 'Failed to load saved data. Starting fresh.' );
				} );
		},

		// loadWizardThemeColors() and updateWizardCSSColors() removed
		// Theme colors are now handled exclusively by shared/theme-color-init.js
		// This eliminates duplicate color setting that caused visual flashing
	} );

	// Initialize on ready
	$( document ).ready( function() {
		// Only initialize on wizard pages
		if ( ! $( '.scd-wizard-page' ).length &&
             ! $( '.scd-wizard-wrap' ).length &&
             -1 === window.location.href.indexOf( 'action=wizard' ) ) {
			return;
		}

		// Check if we're continuing a draft
		var urlParams = new URLSearchParams( window.location.search );
		var intent = urlParams.get( 'intent' );

		// Initialize wizard data
		if ( 'continue' === intent ) {
			// When continuing, we'll load data from session after orchestrator is ready
			SCD.Wizard.data = {};
			SCD.Wizard.loadFromSession = true;
		} else if ( window.scdWizardData && window.scdWizardData.current_campaign ) {
			// Use pre-loaded data for editing existing campaigns
			SCD.Wizard.data = window.scdWizardData.current_campaign;
		} else {
			// Fresh start
			SCD.Wizard.data = {};
		}

		// Initialize wizard (handle race condition properly)
		function initializeWizard() {
			if ( ! SCD.Wizard.initialized ) {
				SCD.Wizard.init();
				SCD.Wizard.initialized = true;
			}
		}

		// Check if orchestrator is already ready
		if ( SCD.Wizard.Orchestrator ) {
			// Orchestrator already exists - initialize immediately
			initializeWizard();
		} else {
			// Orchestrator not ready yet - wait for event
			$( document ).on( 'scd:orchestrator:ready', initializeWizard );
		}
	} );

} )( jQuery );