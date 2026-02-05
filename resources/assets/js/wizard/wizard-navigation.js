/**
 * Wizard Navigation
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-navigation.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

/* eslint-disable sonarjs/cognitive-complexity */
( function( $ ) {
	'use strict';

	if ( 'undefined' === typeof $ ) {
		return;
	}

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};

	/**
	 * Navigation Service
	 *
	 * @since 1.0.0
	 */
	var NavigationService = {
		/**
		 * Configuration constants
		 *
		 * @since 1.0.0
		 */
		CLICK_DELAY: 100,

		/**
		 * DOM selector constants
		 *
		 * @since 1.0.0
		 */
		SELECTORS: {
			NAV_BTN: '.wsscd-nav-btn',
			NAV_BTN_NEXT: '.wsscd-nav-btn--next',
			NAV_BTN_PREV: '.wsscd-nav-btn--previous'
		},

		/**
		 * Data attribute keys
		 *
		 * @since 1.0.0
		 */
		DATA_KEYS: {
			ORIGINAL_TEXT: 'original-text'
		},

		/**
		 * Configuration object
		 *
		 * @since 1.0.0
		 */
		config: {
			steps: window.wsscdNavigation && window.wsscdNavigation.steps ?
				window.wsscdNavigation.steps :
				[ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			ajaxAction: 'wizard_navigation',
			loadingClass: 'is-navigating',
			disabledClass: 'wsscd-navigation-disabled'
		},

		/**
		 * State properties
		 *
		 * @since 1.0.0
		 */
		initialized: false,
		isNavigating: false,
		elements: null,
		navigationTimeout: null,
		popstateHandler: null,

		/**
		 * Helper: Safely get nested property
		 *
		 * @since 1.0.0
		 * @param {object} obj Object to access
		 * @param {string} path Dot-notation path (e.g., 'data.message')
		 * @param {*} defaultValue Default value if path not found
		 * @returns {*} Value at path or default
		 */
		getNestedProp: function( obj, path, defaultValue ) {
			if ( ! obj || ! path ) {
				return defaultValue;
			}
			var keys = path.split( '.' );
			var result = obj;
			for ( var i = 0; i < keys.length; i++ ) {
				if ( null === result || 'undefined' === typeof result[keys[i]] ) {
					return defaultValue;
				}
				result = result[keys[i]];
			}
			return result;
		},

		/**
		 * Helper: Extract error from response
		 *
		 * @since 1.0.0
		 * @param {object} response Server response
		 * @returns {object} Error object {message, code, details}
		 */
		extractError: function( response ) {
			return {
				message: this.getNestedProp( response, 'data.message', 'Server returned an error' ),
				code: this.getNestedProp( response, 'data.code', 'server_error' ),
				details: response
			};
		},

		/**
		 * Helper: Try to parse JSON from potentially corrupted response
		 *
		 * @since 1.0.0
		 * @param {string} responseText Response text that may contain PHP warnings
		 * @returns {object|null} Parsed JSON or null
		 */
		tryParseJSON: function( responseText ) {
			if ( ! responseText ) {
				return null;
			}
			var jsonStartPos = responseText.indexOf( '{' );
			if ( jsonStartPos > 0 ) {
				try {
					return JSON.parse( responseText.substring( jsonStartPos ) );
				} catch ( e ) {
					return null;
				}
			}
			try {
				return JSON.parse( responseText );
			} catch ( e ) {
				return null;
			}
		},

		/**
		 * Helper: Error message lookup
		 *
		 * @since 1.0.0
		 */
		ERROR_MESSAGES: {
			timeout: 'Server is taking too long to respond. Please try again.',
			abort: 'Navigation was cancelled. Please try again.',
			network_error: 'Unable to connect to server. Please check your internet connection.',
			server_error: 'Server error occurred. Please try again in a moment.',
			permission_denied: 'Permission denied. Please refresh the page and try again.',
			not_found: 'Navigation endpoint not found. Please contact support.',
			payload_too_large: 'Too much data sent. Please save the current step first, then try navigation.',
			network_retry: 'Connection issue detected, retrying...',
			default: 'Navigation failed. Please try again.'
		},

		/**
		 * Initialize navigation service
		 *
		 * @since 1.0.0
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.cacheElements();
			this.bindEvents();
			this.handleBrowserNavigation();
			this.initialized = true;
		},

		/**
		 * Cache DOM elements once
		 *
		 * @since 1.0.0
		 */
		cacheElements: function() {
			this.elements = {
				$container: $( '.wsscd-wizard-navigation' ),
				$buttons: $( this.SELECTORS.NAV_BTN ),
				$nextButton: $( this.SELECTORS.NAV_BTN_NEXT ),
				$prevButton: $( this.SELECTORS.NAV_BTN_PREV ),
				$form: $( '.wsscd-wizard-form' )
			};
		},

		/**
		 * Bind events using delegation
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			$( document )
				.on( 'click.wsscd-nav', this.SELECTORS.NAV_BTN, function( e ) {
					e.preventDefault();
					self.handleNavigationClick( $( this ) );
				} )
				.on( 'submit.wsscd-nav', '.wsscd-wizard-form', function( e ) {
					e.preventDefault();
					self.handleFormSubmit();
				} )
				.on( 'click.wsscd-nav', '.wsscd-wizard-steps li', function( e ) {
					e.preventDefault();
					var $step = $( this );
					var targetStep = $step.data( 'step-name' );
					var currentStep = self.getCurrentStep();

					if ( targetStep && targetStep !== currentStep ) {
						self.handleStepIndicatorClick( targetStep, currentStep, $step );
					}
				} );
		},

		/**
		 * Handle navigation button click
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Button element
		 */
		handleNavigationClick: function( $button ) {
			var self = this;

			if ( this.isNavigating || $button.hasClass( this.config.disabledClass ) ) {
				return;
			}

			if ( this.navigationTimeout ) {
				clearTimeout( this.navigationTimeout );
			}

			this.clickedButton = $button;

			// CRITICAL: Disable buttons IMMEDIATELY to prevent double-clicks
			// This closes the race condition gap during the debounce delay
			this.setNavigationState( true );

			this.navigationTimeout = setTimeout( function() {
				self.navigationTimeout = null;

				var action = $button.data( 'action' );
				var targetStep = $button.data( 'target' );
				var saveAsDraft = $button.data( 'save-as-draft' ) || false;

				if ( 'next' === action ) {
					self.navigateNext();
				} else if ( 'previous' === action ) {
					self.navigatePrev();
				} else if ( 'complete' === action ) {
					$( document ).trigger( 'wsscd:navigation:request', [ {
						action: 'complete',
						saveAsDraft: saveAsDraft
					} ] );
				} else if ( targetStep ) {
					self.navigateToStep( targetStep );
				}
			}, this.CLICK_DELAY );
		},

		/**
		 * Handle form submission
		 *
		 * @since 1.0.0
		 */
		handleFormSubmit: function() {
			this.navigateNext();
		},

		/**
		 * Handle step indicator click
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 * @param {string} currentStep Current step name
		 * @param {jQuery} _$stepElement Step element clicked (unused)
		 */
		handleStepIndicatorClick: function( targetStep, currentStep, _$stepElement ) {
			var self = this;
			var currentIndex = this.config.steps.indexOf( currentStep );
			var targetIndex = this.config.steps.indexOf( targetStep );

			// Only validate if moving forward
			if ( targetIndex > currentIndex ) {
				this.validateCurrentStep( currentStep ).done( function( validationResult ) {
					if ( validationResult.isValid ) {
						self.navigateToStep( targetStep, currentStep, validationResult.formData );
					}
				} );
			} else {
				// Allow backward navigation without validation
				this.navigateToStep( targetStep, currentStep );
			}
		},

		/**
		 * Navigate to next step
		 *
		 * @since 1.0.0
		 */
		navigateNext: function() {
			var self = this;
			var currentStep = this.getCurrentStep();
			var nextStep = this.getNextStep( currentStep );

			if ( ! nextStep ) {
				console.warn( '[WSSCD Navigation] No next step found for:', currentStep );
				this.setNavigationState( false );
				return;
			}

			setTimeout( function() {
				self.validateCurrentStep( currentStep ).done( function( validationResult ) {
					if ( validationResult && validationResult.isValid ) {
						self.navigateToStep( nextStep, currentStep, validationResult.formData );
					} else {
						self.setNavigationState( false );
					}
				} ).fail( function( error ) {
					console.error( '[WSSCD Navigation] Validation failed:', error );
					self.setNavigationState( false );
				} );
			}, 0 );
		},

		/**
		 * Validate current step before navigation
		 *
		 * Attempts to use step orchestrator's validation if available.
		 * Falls back to allowing navigation if orchestrator not loaded.
		 *
		 * @since 1.0.0
		 * @param {string} stepName Step name to validate
		 * @returns {jQuery.Deferred} Promise that resolves to {isValid: boolean, formData: object}
		 */
		validateCurrentStep: function( stepName ) {
			var self = this;

			var formData = this.collectStepData( stepName );

			var proCheck = this.checkProFeatures( stepName, formData );

			if ( proCheck.blocked ) {
				return $.Deferred().resolve( { isValid: false, formData: formData } ).promise();
			}

			if ( WSSCD.Wizard && WSSCD.Wizard.Orchestrator && WSSCD.Wizard.Orchestrator.getStepInstance ) {
				var stepOrchestrator = WSSCD.Wizard.Orchestrator.getStepInstance( stepName );

				if ( stepOrchestrator && 'function' === typeof stepOrchestrator.validateStep ) {
					return stepOrchestrator.validateStep().then( function( validationResult ) {
						return { isValid: validationResult, formData: formData };
					} );
				}
			}

			// CRITICAL FIX: If orchestrator not available, BLOCK navigation
			// Don't allow skipping validation just because orchestrator isn't loaded
			// This prevents the validation bypass bug
			console.error( '[WSSCD Wizard:Navigation] Cannot validate - step orchestrator not loaded for: ' + stepName );

			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				window.WSSCD.Shared.NotificationService.error(
					'Cannot proceed: Step validation system not ready. Please refresh the page and try again.',
					0 // Persistent notification
				);
			}

			return $.Deferred().resolve( { isValid: false, formData: formData } ).promise();
		},

		/**
		 * Navigate to previous step
		 *
		 * @since 1.0.0
		 */
		navigatePrev: function() {
			var currentStep = this.getCurrentStep();
			var prevStep = this.getPrevStep( currentStep );

			if ( prevStep ) {
				this.navigateToStep( prevStep, currentStep );
			} else {
				// No previous step (already on first step) - re-enable buttons
				this.setNavigationState( false );
			}
		},

		/**
		 * Navigate to specific step
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 * @param {string} fromStep Current step name
		 */
		navigateToStep: function( targetStep, fromStep ) {
			fromStep = fromStep || this.getCurrentStep();

			if ( targetStep === fromStep ) {
				// Already on target step - re-enable buttons
				this.setNavigationState( false );
				return;
			}

			this.performNavigation( fromStep, targetStep );
		},

		/**
		 * Perform navigation operation
		 *
		 * @since 1.0.0
		 * @param {string} fromStep Current step
		 * @param {string} targetStep Target step
		 * @param {boolean} isRetry Whether this is a retry attempt
		 */
		performNavigation: function( fromStep, targetStep, isRetry ) {
			var self = this;

			if ( WSSCD.Wizard && WSSCD.Wizard.Orchestrator ) {
				WSSCD.Wizard.Orchestrator.isInternalNavigation = true;
			}

			this.setNavigationState( true );

			// Check navigation direction
			var fromIndex = this.config.steps.indexOf( fromStep );
			var targetIndex = this.config.steps.indexOf( targetStep );
			var isBackward = targetIndex < fromIndex;

			// Skip save/validation when navigating backwards
			if ( isBackward ) {
				// Direct navigation without saving
				var redirectUrl = this.buildStepUrl( targetStep );
				window.location.href = redirectUrl;
				return;
			}

			var formData = this.collectStepData( fromStep );

			// Note: PRO feature check now happens in validateCurrentStep() before this point
			// This ensures field validation doesn't show errors for locked PRO features

			// Debug: Log navigation data

			this.sendNavigationRequest( fromStep, targetStep, formData )
				.done( function( response ) {
					if ( false === response.success ) {
						var errorObj = self.extractError( response );
						console.error( '[WSSCD Wizard:Navigation] Server error:', errorObj.message, errorObj.code );
						self.handleNavigationError( errorObj );
						self.setNavigationState( false );
						return;
					}

					// Don't re-enable buttons here - let handleNavigationSuccess decide
					// For redirects: buttons stay disabled until new page loads
					// For client-side: buttons re-enabled after UI updates
					self.handleNavigationSuccess( response, targetStep );
				} )
				.fail( function( xhr, textStatus, errorThrown ) {
					// Retry logic for network failures
					var isNetworkError = xhr.status === 0 || xhr.status === 503 || 'timeout' === textStatus;

					if ( isNetworkError && ! isRetry ) {
						// Network issue - retry once
						self.handleNavigationError( {
							message: self.ERROR_MESSAGES.network_retry,
							code: 'network_retry',
							isRetrying: true
						} );
						setTimeout( function() {
							self.performNavigation( fromStep, targetStep, true );
						}, 1000 );
						return;
					}

					// Handle JSON parsing errors from PHP warnings/errors
					if ( 'parsererror' === textStatus && xhr.responseText ) {
						var parsedResponse = self.tryParseJSON( xhr.responseText );
						if ( parsedResponse && false === parsedResponse.success ) {
							self.handleNavigationError( self.extractError( parsedResponse ) );
							self.setNavigationState( false );
							return;
						}
						if ( xhr.responseText.indexOf( 'Input variables exceeded' ) !== -1 ) {
							self.handleNavigationError( {
								message: self.ERROR_MESSAGES.payload_too_large,
								code: 'payload_too_large'
							} );
							self.setNavigationState( false );
							return;
						}
					}

					var errorObj = self.normalizeAjaxError( xhr, textStatus, errorThrown );
					self.handleNavigationError( errorObj );
					self.setNavigationState( false );
				} );
		},

		/**
		 * Send navigation request to server
		 *
		 * Saves current step data with navigation flag, which enforces validation.
		 * Server will only mark step complete if validation passes.
		 *
		 * @since 1.0.0
		 * @param {string} fromStep Current step
		 * @param {string} targetStep Target step
		 * @param {object} formData Form data to save
		 * @returns {jQuery.Promise} Promise that resolves with server response
		 */
		sendNavigationRequest: function( fromStep, targetStep, formData ) {
			var self = this;

			// Prevent duplicate save requests (debounce)
			if ( this._saveInProgress ) {
				return this._savePromise || $.Deferred().reject( {
					success: false,
					data: {
						message: 'Save already in progress',
						code: 'save_in_progress'
					}
				} ).promise();
			}

			if ( ! window.WSSCD || ! window.WSSCD.Wizard || ! window.WSSCD.Wizard.Orchestrator ) {
				console.error( '[WSSCD Navigation] Main orchestrator not available' );
				return $.Deferred().reject( {
					success: false,
					data: {
						message: 'Orchestrator not available',
						code: 'service_unavailable'
					}
				} ).promise();
			}

			var stepOrchestrator = window.WSSCD.Wizard.Orchestrator.getStepInstance( fromStep );
			if ( ! stepOrchestrator ) {
				console.error( '[WSSCD Navigation] Step orchestrator not found for step:', fromStep );
				console.error( '[WSSCD Navigation] Available orchestrators:', window.WSSCD.Wizard.Orchestrator.stepOrchestrators ? Object.keys( window.WSSCD.Wizard.Orchestrator.stepOrchestrators ) : 'none' );
				return $.Deferred().reject( {
					success: false,
					data: {
						message: 'Step orchestrator not found',
						code: 'orchestrator_not_found'
					}
				} ).promise();
			}

			if ( 'function' !== typeof stepOrchestrator.saveStep ) {
				console.error( '[WSSCD Navigation] saveStep method not available on orchestrator for step:', fromStep );
				console.error( '[WSSCD Navigation] Orchestrator methods:', Object.keys( stepOrchestrator ) );
				return $.Deferred().reject( {
					success: false,
					data: {
						message: 'saveStep method not available',
						code: 'method_not_available'
					}
				} ).promise();
			}

			// Mark save as in progress
			this._saveInProgress = true;

			// Save via step orchestrator (navigation save - primary save mechanism)
			return stepOrchestrator.saveStep().then( function( response ) {
				self._saveInProgress = false;
				self._savePromise = null;

				// Server validated and saved data
				// Extract completed steps from progress
				var completedSteps = [];
				if ( response && response.progress && response.progress.completed_steps ) {
					completedSteps = response.progress.completed_steps;
				}

				// Build URL for server-side navigation (full page reload)
				// This is intentional - wizard uses server-rendered step content
				var redirectUrl = self.buildStepUrl( targetStep );

				return {
					success: true,
					data: {
						message: response.message || 'Step saved successfully',
						navigationAction: 'navigate',
						currentStep: targetStep,
						nextStep: targetStep,
						completedSteps: completedSteps,
						redirectUrl: redirectUrl
					}
				};
			} ).fail( function( error ) {
				console.error( '[WSSCD Navigation] saveStep FAILED:', error );
				self._saveInProgress = false;
				self._savePromise = null;

				// Validation failed or save error
				var errorMessage = window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.getValidationMessage
					? window.WSSCD.Utils.getValidationMessage( 'navigation.incomplete_step', 'Please complete all required fields before proceeding.' )
					: 'Please complete all required fields before proceeding.';
				var errorCode = 'validation_failed';

				if ( error && error.data ) {
					errorMessage = error.data.message || errorMessage;
					errorCode = error.data.code || errorCode;
				} else if ( error && error.message ) {
					errorMessage = error.message;
				}

				console.error( '[WSSCD Navigation] Returning formatted error:', errorMessage, errorCode );

				// Return properly formatted error
				return $.Deferred().reject( {
					success: false,
					data: {
						message: errorMessage,
						code: errorCode,
						errors: error && error.data && error.data.errors ? error.data.errors : []
					}
				} ).promise();
			} );
		},

		/**
		 * Show skeleton loading screen
		 * Delegates to centralized skeleton generation service.
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		showSkeletonScreen: function( targetStep ) {
			// Delegate to SkeletonTemplates service (loaded via dependencies)
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.SkeletonTemplates ) {
				if ( this.config && this.config.steps ) {
					window.WSSCD.Wizard.SkeletonTemplates.init( this.config );
				}
				window.WSSCD.Wizard.SkeletonTemplates.render( targetStep );
				return;
			}

			// Service not loaded - log error and skip skeleton
			console.error( '[WSSCD Navigation] SkeletonTemplates service not loaded - check script dependencies' );
		},

		/**
		 * Handle successful navigation
		 *
		 * @since 1.0.0
		 * @param {object} response Server response
		 * @param {string} targetStep Target step name
		 */
		handleNavigationSuccess: function( response, targetStep ) {
			var self = this;
			var data = response;
			if ( response.success && response.data ) {
				data = response.data;
			}

			if ( data.completedSteps && WSSCD.Wizard && WSSCD.Wizard.StateManager ) {
				WSSCD.Wizard.StateManager.getInstance().set( {
					completedSteps: data.completedSteps
				}, { silent: true } );

				this.updateCompletedSteps( data.completedSteps );
			}

			// Use camelCase directly as received from server
			if ( data.redirectUrl ) {
				this.showSkeletonScreen( targetStep );

				// Prevent any navigation events from interfering
				self.setNavigationState( true );

				// Delay to ensure session save completes before redirect
				// Critical: Server must finish saving before new page request
				setTimeout( function() {
					window.location.href = data.redirectUrl;
				}, 300 );

				// Nothing after this line will execute
				return;
			} else {
					// Client-side navigation - update UI then re-enable buttons
				this.updateURL( targetStep );

				if ( WSSCD.Wizard && WSSCD.Wizard.Orchestrator && WSSCD.Wizard.Orchestrator.loadCurrentStep ) {
					WSSCD.Wizard.Orchestrator.loadCurrentStep()
						.always( function() {
							// Update UI and re-enable buttons after orchestrator loads (or fails)
							self.updateStepUI( targetStep );
							self.notifyStepChange( targetStep );
							self.setNavigationState( false );
						} );
				} else {
					// No orchestrator available - still update UI
					this.updateStepUI( targetStep );
					this.notifyStepChange( targetStep );
					this.setNavigationState( false );
				}
			}
		},

		/**
		 * Normalize AJAX error objects to consistent format
		 *
		 * @since 1.0.0
		 * @param {object} xhr XMLHttpRequest object
		 * @param {string} textStatus Status text
		 * @param {string} _errorThrown Error message (unused)
		 * @returns {object} Normalized error object
		 */
		normalizeAjaxError: function( xhr, textStatus, _errorThrown ) {
			// Determine error code and message from error type
			var errorCode = 'ajax_error';
			var errorMessage = this.ERROR_MESSAGES.default;

			if ( 'timeout' === textStatus ) {
				errorCode = 'timeout';
				errorMessage = this.ERROR_MESSAGES.timeout;
			} else if ( 'abort' === textStatus ) {
				errorCode = 'abort';
				errorMessage = this.ERROR_MESSAGES.abort;
			} else if ( xhr && 0 === xhr.status ) {
				errorCode = 'network_error';
				errorMessage = this.ERROR_MESSAGES.network_error;
			} else if ( xhr && xhr.status >= 500 ) {
				errorCode = 'server_error';
				errorMessage = this.ERROR_MESSAGES.server_error;
			} else if ( xhr && 403 === xhr.status ) {
				errorCode = 'permission_denied';
				errorMessage = this.ERROR_MESSAGES.permission_denied;
			} else if ( xhr && 404 === xhr.status ) {
				errorCode = 'not_found';
				errorMessage = this.ERROR_MESSAGES.not_found;
			}

			var errorObj = {
				message: errorMessage,
				code: errorCode,
				status: textStatus,
				details: {}
			};

			// Try to get specific error from server response
			if ( xhr && xhr.responseJSON ) {
				errorObj.message = this.getNestedProp( xhr.responseJSON, 'data.message', errorMessage );
				errorObj.code = this.getNestedProp( xhr.responseJSON, 'data.code', errorCode );
				errorObj.details = xhr.responseJSON;
			} else if ( xhr && xhr.responseText ) {
				var parsedResponse = this.tryParseJSON( xhr.responseText );
				if ( parsedResponse ) {
					errorObj.message = this.getNestedProp( parsedResponse, 'data.message', errorMessage );
					errorObj.code = this.getNestedProp( parsedResponse, 'data.code', errorCode );
				} else {
					errorObj.details.responseText = xhr.responseText;
				}
			}

			if ( xhr ) {
				errorObj.details.status = xhr.status;
				errorObj.details.statusText = xhr.statusText;
			}

			return errorObj;
		},

		/**
		 * Handle navigation error
		 *
		 * @since 1.0.0
		 * @param {object} error Error object
		 */
		handleNavigationError: function( error ) {
			var message = error.message || this.ERROR_MESSAGES.default;

			var messageType = error.isRetrying ? 'warning' : 'error';


			// Standard error handling
			if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.show( message, messageType );
			} else if ( window.console ) {
				// Fallback to console (better than alert)
				if ( 'error' === messageType ) {
					console.error( '[WSSCD Navigation] ' + message );
				}
			}

			$( document ).trigger( 'wsscd:navigation:error', [ error ] );
		},

		/**
		 * Get current step from URL
		 *
		 * @since 1.0.0
		 * @returns {string} Current step name
		 */
		getCurrentStep: function() {
			var urlParams = new URLSearchParams( window.location.search );
			var urlStep = urlParams.get( 'step' );

			if ( urlStep && this.config.steps.indexOf( urlStep ) !== -1 ) {
				return urlStep;
			}

			return this.config.steps[0];
		},

		/**
		 * Get next step
		 *
		 * @since 1.0.0
		 * @param {string} currentStep Current step name
		 * @returns {string|null} Next step name or null
		 */
		getNextStep: function( currentStep ) {
			var currentIndex = this.config.steps.indexOf( currentStep );
			if ( currentIndex >= 0 && currentIndex < this.config.steps.length - 1 ) {
				return this.config.steps[currentIndex + 1];
			}
			return null;
		},

		/**
		 * Get previous step
		 *
		 * @since 1.0.0
		 * @param {string} currentStep Current step name
		 * @returns {string|null} Previous step name or null
		 */
		getPrevStep: function( currentStep ) {
			var currentIndex = this.config.steps.indexOf( currentStep );
			if ( currentIndex > 0 ) {
				return this.config.steps[currentIndex - 1];
			}
			return null;
		},

		/**
		 * Collect step data from orchestrator
		 *
		 * All step orchestrators use StepPersistence mixin which provides collectData()
		 * based on field definitions. No need for manual form parsing.
		 *
		 * @since 1.0.0
		 * @param {string} _stepName Step name (unused - uses current step from orchestrator)
		 * @returns {object} Collected step data
		 */
		collectStepData: function( _stepName ) {
			// Use orchestrator's collectCurrentStepData which delegates to step's collectData()
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Orchestrator &&
				'function' === typeof window.WSSCD.Wizard.Orchestrator.collectCurrentStepData ) {
				try {
					var data = window.WSSCD.Wizard.Orchestrator.collectCurrentStepData();



					return data && 'object' === typeof data ? data : {};
				} catch ( e ) {
					if ( window.console && window.console.error ) {
						console.error( '[WSSCD Navigation] Error collecting step data:', e );
					}
					return {};
				}
			}

			// Orchestrator not available
			return {};
		},

		/**
		 * Build URL for a specific step
		 *
		 * @since 1.0.0
		 * @param {string} step Step name
		 * @returns {string} Step URL
		 */
		buildStepUrl: function( step ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'step', step );
			return url.toString();
		},

		/**
		 * Update URL without reload
		 *
		 * @since 1.0.0
		 * @param {string} step Step name
		 */
		updateURL: function( step ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'step', step );

			if ( window.history && window.history.pushState ) {
				window.history.pushState( { step: step }, '', url.toString() );
			}
		},

		/**
		 * Handle browser back/forward navigation
		 *
		 * @since 1.0.0
		 */
		handleBrowserNavigation: function() {
			var self = this;

			this.popstateHandler = function() {
				var currentStep = self.getCurrentStep();

			if ( WSSCD.Wizard && WSSCD.Wizard.Orchestrator && WSSCD.Wizard.Orchestrator.loadCurrentStep ) {
				WSSCD.Wizard.Orchestrator.loadCurrentStep()
					.done( function() {
						self.updateStepUI( currentStep );
					} )
					.fail( function() {
						// Fallback if load fails
						self.updateStepUI( currentStep );
					} );
			} else {
				self.updateStepUI( currentStep );
			}

				$( document ).trigger( 'wsscd:wizard:stepChanged', [ {
					step: currentStep,
					source: 'browser_navigation'
				} ] );
			};

			window.addEventListener( 'popstate', this.popstateHandler );
		},

		/**
		 * Update step UI
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		updateStepUI: function( targetStep ) {
			$( '.wsscd-wizard-navigation .step' ).removeClass( 'active current' );
			$( '.wsscd-wizard-navigation .step[data-step="' + targetStep + '"]' ).addClass( 'active current' );

			this.updateProgress( targetStep );

			// Ensure completed steps UI is synced with current state
			if ( WSSCD.Wizard && WSSCD.Wizard.StateManager ) {
				var completedSteps = WSSCD.Wizard.StateManager.getInstance().get( 'completedSteps' );
				if ( completedSteps ) {
					this.updateCompletedSteps( completedSteps );
				}
			}
		},

		/**
		 * Update progress indicator
		 *
		 * @since 1.0.0
		 * @param {string} currentStep Current step name
		 */
		updateProgress: function( currentStep ) {
			var currentIndex = this.config.steps.indexOf( currentStep );
			var progress = ( ( currentIndex + 1 ) / this.config.steps.length ) * 100;

			$( '.wsscd-wizard-navigation' ).css( '--progress', progress + '%' );
			$( '.wsscd-nav-status__step' ).text( 'Step ' + ( currentIndex + 1 ) + ' of ' + this.config.steps.length );
		},

		/**
		 * Update completed steps visual indicators
		 *
		 * @since 1.0.0
		 * @param {array} completedSteps Array of completed step names
		 */
		updateCompletedSteps: function( completedSteps ) {
			if ( ! completedSteps || ! Array.isArray( completedSteps ) ) {
				return;
			}

			$( '.wsscd-wizard-steps li' ).removeClass( 'completed' );

			for ( var i = 0; i < completedSteps.length; i++ ) {
				var stepName = completedSteps[i];
				$( '.wsscd-wizard-steps li[data-step-name="' + stepName + '"]' ).addClass( 'completed' );
			}
		},

		/**
		 * Notify other components of step change
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		notifyStepChange: function( targetStep ) {
			$( document ).trigger( 'wsscd:wizard:stepChanged', [ targetStep ] );
		},

		/**
		 * Set navigation loading state
		 *
		 * Use fresh selectors instead of cached elements to handle dynamically loaded buttons.
		 * Only the clicked button shows "Processing..." while others are just disabled.
		 *
		 * @since 1.0.0
		 * @param {boolean} isNavigating Navigation state
		 */
		setNavigationState: function( isNavigating ) {
			var self = this;
			this.isNavigating = isNavigating;

			// Query buttons fresh each time - handles dynamic/late-loaded buttons
			var $buttons = $( this.SELECTORS.NAV_BTN );
			var $clickedButton = this.clickedButton;

			if ( $buttons.length > 0 ) {
				// Disable ALL buttons
				$buttons
					.toggleClass( this.config.disabledClass, isNavigating )
					.prop( 'disabled', isNavigating );

				if ( isNavigating ) {
					// Show "Proceeding..." on clicked button only
					$buttons.each( function() {
						var $btn = $( this );
						var $text = $btn.find( '.wsscd-nav-btn__text, .wsscd-button-text' ).first();

						var isClickedButton = $clickedButton && $clickedButton.length && $btn.is( $clickedButton );

						if ( isClickedButton && $text.length && ! $btn.data( self.DATA_KEYS.ORIGINAL_TEXT ) ) {
							$btn.data( self.DATA_KEYS.ORIGINAL_TEXT, $text.text() );
							$text.text( 'Proceeding...' );
						}
					} );
				} else {
					// Restore all buttons
					$buttons.each( function() {
						var $btn = $( this );
						var $text = $btn.find( '.wsscd-nav-btn__text, .wsscd-button-text' ).first();
						var originalText = $btn.data( self.DATA_KEYS.ORIGINAL_TEXT );

						if ( $text.length && originalText ) {
							$text.text( originalText );
							$btn.removeData( self.DATA_KEYS.ORIGINAL_TEXT );
						}
					} );

					this.clickedButton = null;
				}
			}

			// Container can stay cached as it's always present
			var $container = $( '.wsscd-wizard-navigation' );
			if ( $container.length > 0 ) {
				$container.toggleClass( this.config.loadingClass, isNavigating );
			}
		},

		/**
		 * Get nonce for navigation
		 *
		 * @since 1.0.0
		 * @returns {string} Nonce value
		 */
		getNonce: function() {
			if ( window.wsscdNavigation && window.wsscdNavigation.nonce ) {
				return window.wsscdNavigation.nonce;
			}
			if ( window.wsscdWizardData && window.wsscdWizardData.nonces ) {
				return window.wsscdWizardData.nonces.wsscdWizardNonce;
			}
			return '';
		},

	/**
	 * Check if user is trying to use PRO features (client-side)
	 * Delegates to centralized PRO feature detection service.
	 *
	 * @since 1.0.0
	 * @param {string} step Current step name
	 * @param {object} formData Form data being validated
	 * @returns {object} { blocked: boolean, message: string, feature: string, upgradeUrl: string }
	 */
	checkProFeatures: function( step, formData ) {
		// Delegate to ProFeatureGate service (loaded via dependencies)
		if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.ProFeatureGate ) {
			return window.WSSCD.Shared.ProFeatureGate.check( step, formData );
		}

		// Service not loaded - log error and allow all features
		console.error( '[WSSCD Navigation] ProFeatureGate service not loaded - check script dependencies' );
		return { blocked: false };
	},

		/**
		 * Cleanup navigation service
		 *
		 * @since 1.0.0
		 */
		destroy: function() {
			$( document ).off( '.wsscd-nav' );

			if ( this.popstateHandler ) {
				window.removeEventListener( 'popstate', this.popstateHandler );
				this.popstateHandler = null;
			}

			if ( this.navigationTimeout ) {
				clearTimeout( this.navigationTimeout );
				this.navigationTimeout = null;
			}

			this.initialized = false;
			this.isNavigating = false;
			this.elements = null;
		}
	};

	/**
	 * Auto-initialize on document ready
	 *
	 * @since 1.0.0
	 */
	$( function() {
		// Ensure namespace exists and assign Navigation service after other scripts load
		window.WSSCD = window.WSSCD || {};
		window.WSSCD.Wizard = window.WSSCD.Wizard || {};
		window.WSSCD.Wizard.Navigation = NavigationService;

		if ( ( $( '.wsscd-wizard-page' ).length > 0 ||
			$( '.wsscd-wizard-wrap' ).length > 0 ||
			window.location.href.indexOf( 'action=wizard' ) !== -1 ) &&
			window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
			window.WSSCD.Wizard.Navigation.init();
		}
	} );

} )( jQuery );