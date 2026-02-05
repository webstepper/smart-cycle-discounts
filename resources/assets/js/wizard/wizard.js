/**
 * Wizard
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Steps = WSSCD.Steps || {};

	/**
	 * Wizard Facade
	 *
	 * Public API that delegates to the orchestrator
	 */
	WSSCD.Wizard = WSSCD.Utils.extend( WSSCD.Wizard || {}, {
		// Constants
		EVENT_PREFIX: 'wsscd:wizard:',

		// Reference to the orchestrator (lazy loaded)
		orchestrator: null,

		/**
		 * Get orchestrator instance
		 * @private
		 */
		getOrchestrator: function() {
			if ( !this.orchestrator && WSSCD.Wizard.Orchestrator ) {
				this.orchestrator = WSSCD.Wizard.Orchestrator;
			}
			return this.orchestrator;
		},

		/**
		 * Initialize wizard
		 * Delegates to orchestrator
		 */
		init: function() {
			// Hide WordPress screen options only if we're in the wizard
			if ( 0 < $( '.wsscd-wizard-page' ).length ) {
				$( '#screen-meta-links' ).hide();
				$( '#screen-meta' ).hide();
			}

			// Theme colors are handled by shared/theme-color-init.js
			// No need to set them again here

			var orchestrator = this.getOrchestrator();
			if ( orchestrator && 'function' === typeof orchestrator.init ) {
				orchestrator.init();
			}

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
			if ( window.WSSCD && window.WSSCD.ValidationManager ) {
				var result = window.WSSCD.ValidationManager.validateStep( currentStep );
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

			var stepOrchestrator = orchestrator.getStepInstance( stepName );
			if ( stepOrchestrator && 'function' === typeof stepOrchestrator.saveStep ) {
				return stepOrchestrator.saveStep();
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
		 * Public API Methods for Step Orchestrators
		 *
		 * Step modules use these methods to interact with wizard state, navigation,
		 * and data persistence through a stable interface.
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

			var stepOrchestrator = orchestrator.getStepInstance( stepName );
			if ( stepOrchestrator && 'function' === typeof stepOrchestrator.saveStep ) {
				return stepOrchestrator.saveStep();
			}
		},

		/**
		 * Get current step name
		 * Delegates to StateManager
		 */
		getCurrentStep: function() {
			// URL is the single source of truth
			var urlParams = new URLSearchParams( window.location.search );
			var urlStep = urlParams.get( 'step' );

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
				WSSCD.Shared.NotificationService.error( errors[i].message || errors[i] );
			}
		},

		/**
		 * Display object of field validation errors
		 * Extracted to reduce cognitive complexity
		 */
		displayFieldErrors: function( errors ) {
			for ( var field in errors ) {
				if ( Object.prototype.hasOwnProperty.call( errors, field ) ) {
					WSSCD.Shared.NotificationService.error( field + ': ' + errors[field] );
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
			if ( !window.WSSCD || !window.WSSCD.ValidationManager ) {
				return;
			}

			var currentStep = this.getCurrentStep();
			var validationState = window.WSSCD.ValidationManager.getValidationState( currentStep );

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
		 * Load session data when continuing a draft
		 */
		loadSessionData: function() {
			var self = this;

		// Session data already loaded via PHP template - nothing to do
		}
	} );

	$( document ).ready( function() {
		// Only initialize on wizard pages
		if ( ! $( '.wsscd-wizard-page' ).length &&
             ! $( '.wsscd-wizard-wrap' ).length &&
             -1 === window.location.href.indexOf( 'action=wizard' ) ) {
			return;
		}

		var urlParams = new URLSearchParams( window.location.search );
		var intent = urlParams.get( 'intent' );

		if ( 'continue' === intent ) {
			// When continuing, we'll load data from session after orchestrator is ready
			WSSCD.Wizard.data = {};
			WSSCD.Wizard.loadFromSession = true;
		} else if ( window.wsscdWizardData && window.wsscdWizardData.currentCampaign ) {
			// Use pre-loaded data for editing existing campaigns
			WSSCD.Wizard.data = window.wsscdWizardData.currentCampaign;

			if ( window.wsscdWizardData.debugPersistence ) {
				window.wsscdDebugPersistence = true;
			}
		} else {
			// Fresh start
			WSSCD.Wizard.data = {};
		}

		function initializeWizard() {
			if ( ! WSSCD.Wizard.initialized ) {
				WSSCD.Wizard.init();
				WSSCD.Wizard.initialized = true;
			}
		}

		if ( WSSCD.Wizard.Orchestrator ) {
			// Orchestrator already exists - initialize immediately
			initializeWizard();
		} else {
			// Orchestrator not ready yet - wait for event
			$( document ).on( 'wsscd:orchestrator:ready', initializeWizard );
		}
	} );

} )( jQuery );