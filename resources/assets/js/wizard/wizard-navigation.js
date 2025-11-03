/**
 * Wizard Navigation
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-navigation.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
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

	window.SCD = window.SCD || {};
	window.SCD.Wizard = window.SCD.Wizard || {};

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
		NAVIGATION_TIMEOUT: 3000,

		/**
		 * DOM selector constants
		 *
		 * @since 1.0.0
		 */
		SELECTORS: {
			NAV_BTN: '.scd-nav-btn',
			NAV_BTN_NEXT: '.scd-nav-btn--next',
			NAV_BTN_PREV: '.scd-nav-btn--previous'
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
			steps: window.scdNavigation && window.scdNavigation.steps ?
				window.scdNavigation.steps :
				[ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			ajaxAction: 'wizard_navigation',
			loadingClass: 'is-navigating',
			disabledClass: 'scd-navigation-disabled'
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
				$container: $( '.scd-wizard-navigation' ),
				$buttons: $( this.SELECTORS.NAV_BTN ),
				$nextButton: $( this.SELECTORS.NAV_BTN_NEXT ),
				$prevButton: $( this.SELECTORS.NAV_BTN_PREV ),
				$form: $( '.scd-wizard-form' )
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
				.on( 'click.scd-nav', this.SELECTORS.NAV_BTN, function( e ) {
					e.preventDefault();
					self.handleNavigationClick( $( this ) );
				} )
				.on( 'submit.scd-nav', '.scd-wizard-form', function( e ) {
					e.preventDefault();
					self.handleFormSubmit();
				} )
				.on( 'click.scd-nav', '.scd-wizard-steps li', function( e ) {
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

			// Store clicked button for processing state
			this.clickedButton = $button;

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
					$( document ).trigger( 'scd:navigation:request', [ {
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
			var currentStep = this.getCurrentStep();
			var nextStep = this.getNextStep( currentStep );

			if ( nextStep ) {
				var self = this;
				this.validateCurrentStep( currentStep ).done( function( validationResult ) {
					if ( validationResult && validationResult.isValid ) {
						self.navigateToStep( nextStep, currentStep, validationResult.formData );
					}
					// Validation errors are shown by ValidationError component automatically
				} ).fail( function( error ) {
					console.error( '[SCD Navigation] Validation failed:', error );
				} );
			}
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

			// CRITICAL: Check PRO features FIRST before field validation
			// This prevents confusing "field required" errors for locked PRO features
			var formData = this.collectStepData( stepName );
			var proCheck = this.checkProFeatures( stepName, formData );

			if ( proCheck.blocked ) {
				// Modal is already shown by checkProFeatures()
				// Block validation without showing error notification
				return $.Deferred().resolve( { isValid: false, formData: formData } ).promise();
			}

			// Try to get step orchestrator
			if ( SCD.Wizard && SCD.Wizard.Orchestrator && SCD.Wizard.Orchestrator.getStepInstance ) {
				var stepOrchestrator = SCD.Wizard.Orchestrator.getStepInstance( stepName );

				// If orchestrator exists and has validateStep, use it
				if ( stepOrchestrator && 'function' === typeof stepOrchestrator.validateStep ) {
					// Wrap orchestrator validation to include formData in result
					return stepOrchestrator.validateStep().then( function( validationResult ) {
						return { isValid: validationResult, formData: formData };
					} );
				}
			}

			// CRITICAL FIX: If orchestrator not available, BLOCK navigation
			// Don't allow skipping validation just because orchestrator isn't loaded
			// This prevents the validation bypass bug
			console.error( '[SCD Wizard:Navigation] Cannot validate - step orchestrator not loaded for: ' + stepName );

			// Show error to user using NotificationService
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.error(
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
		performNavigation: function( fromStep, targetStep, isRetry, formData ) {
			var self = this;

			if ( SCD.Wizard && SCD.Wizard.Orchestrator ) {
				SCD.Wizard.Orchestrator.isInternalNavigation = true;
			}

			this.setNavigationState( true );

			var formData = this.collectStepData( fromStep );

			// Note: PRO feature check now happens in validateCurrentStep() before this point
			// This ensures field validation doesn't show errors for locked PRO features

			// Debug: Log navigation data

			this.sendNavigationRequest( fromStep, targetStep, formData )
				.done( function( response ) {
					if ( false === response.success ) {
						console.error( '[SCD Wizard:Navigation] Server returned error:', response );
						console.error( '[SCD Wizard:Navigation] Error message:', response.data && response.data.message ? response.data.message : 'No message' );
						console.error( '[SCD Wizard:Navigation] Error code:', response.data && response.data.code ? response.data.code : 'No code' );
						console.error( '[SCD Wizard:Navigation] Error details:', response.data && response.data.errors ? response.data.errors : 'No details' );
						var errorObj = {
							message: response.data && response.data.message ? response.data.message : 'Server returned an error',
							code: response.data && response.data.code ? response.data.code : 'server_error',
							details: response
						};
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
						// Network issue on first attempt - retry once

						// Show user we're retrying
						self.handleNavigationError( {
							message: 'Connection issue detected, retrying...',
							code: 'network_retry',
							isRetrying: true
						} );

						// Retry after brief delay
						setTimeout( function() {
							self.performNavigation( fromStep, targetStep, true );
						}, 1000 );
						return;
					}

					// Special handling for JSON parsing errors caused by PHP warnings/errors
					if ( 'parsererror' === textStatus && xhr.responseText ) {
						var response = xhr.responseText;
						// Try to extract JSON from response that may have PHP warnings/errors
						var jsonStartPos = response.indexOf( '{' );
						if ( jsonStartPos > 0 ) {
							try {
								var cleanJson = response.substring( jsonStartPos );
								var parsedResponse = JSON.parse( cleanJson );
								if ( parsedResponse && false === parsedResponse.success ) {
									// Handle the actual error from the cleaned JSON
									self.handleNavigationError( {
										message: parsedResponse.data && parsedResponse.data.message ? parsedResponse.data.message : 'Server error occurred',
										code: parsedResponse.data && parsedResponse.data.code ? parsedResponse.data.code : 'server_error'
									} );
									self.setNavigationState( false );
									return;
								}
							} catch ( e ) {
								// Fall through to normal error handling
							}
						}

						// Check for specific PHP errors
						if ( response.indexOf( 'Input variables exceeded' ) !== -1 ) {
							self.handleNavigationError( {
								message: 'Too much data sent. Please save the current step first, then try navigation.',
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

			// Validate we have main orchestrator
			if ( ! window.SCD || ! window.SCD.Wizard || ! window.SCD.Wizard.Orchestrator ) {
				console.error( '[SCD Navigation] Main orchestrator not available' );
				return $.Deferred().reject( {
					success: false,
					data: {
						message: 'Orchestrator not available',
						code: 'service_unavailable'
					}
				} ).promise();
			}

			// Get step orchestrator
			var stepOrchestrator = window.SCD.Wizard.Orchestrator.getStepInstance( fromStep );
			if ( ! stepOrchestrator ) {
				console.error( '[SCD Navigation] Step orchestrator not found for step:', fromStep );
				console.error( '[SCD Navigation] Available orchestrators:', window.SCD.Wizard.Orchestrator.stepOrchestrators ? Object.keys( window.SCD.Wizard.Orchestrator.stepOrchestrators ) : 'none' );
				return $.Deferred().reject( {
					success: false,
					data: {
						message: 'Step orchestrator not found',
						code: 'orchestrator_not_found'
					}
				} ).promise();
			}

			if ( 'function' !== typeof stepOrchestrator.saveStep ) {
				console.error( '[SCD Navigation] saveStep method not available on orchestrator for step:', fromStep );
				console.error( '[SCD Navigation] Orchestrator methods:', Object.keys( stepOrchestrator ) );
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
					// Clear save flag
				self._saveInProgress = false;
				self._savePromise = null;

				// Server validated and saved data
				// Extract completed steps from progress
				var completedSteps = [];
				if ( response && response.progress && response.progress.completedSteps ) {
					completedSteps = response.progress.completedSteps;
				}

				var redirectUrl = self.buildStepUrl( targetStep );

				// Build navigation response and return immediately
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
					// Clear save flag on error
				self._saveInProgress = false;
				self._savePromise = null;

				// Validation failed or save error
				var errorMessage = window.SCD && window.SCD.Utils && window.SCD.Utils.getValidationMessage
					? window.SCD.Utils.getValidationMessage( 'navigation.incomplete_step', 'Please complete all required fields before proceeding.' )
					: 'Please complete all required fields before proceeding.';
				var errorCode = 'validation_failed';

				if ( error && error.data ) {
					errorMessage = error.data.message || errorMessage;
					errorCode = error.data.code || errorCode;
				} else if ( error && error.message ) {
					errorMessage = error.message;
				}

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
		 *
		 * PHASE 3: Clean delegation to SkeletonTemplates service.
		 * Delegates to centralized skeleton generation service.
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		showSkeletonScreen: function( targetStep ) {
			// Delegate to SkeletonTemplates service (loaded via dependencies)
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.SkeletonTemplates ) {
				// Initialize with config if not already done
				if ( this.config && this.config.steps ) {
					window.SCD.Wizard.SkeletonTemplates.init( this.config );
				}
				window.SCD.Wizard.SkeletonTemplates.render( targetStep );
				return;
			}

			// Service not loaded - log error and skip skeleton
			console.error( '[SCD Navigation] SkeletonTemplates service not loaded - check script dependencies' );
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

			// Update completed steps if provided
			if ( data.completedSteps && SCD.Wizard && SCD.Wizard.StateManager ) {
				SCD.Wizard.StateManager.set( {
					completedSteps: data.completedSteps
				}, { silent: true } );

				// Update step indicator UI with completed class
				this.updateCompletedSteps( data.completedSteps );
			}

			// Server automatically converts snake_case to camelCase via SCD_AJAX_Response
			// Use camelCase directly as received from server
			if ( data.redirectUrl ) {
				// Show skeleton screen immediately for instant feedback
				this.showSkeletonScreen( targetStep );

				// Prevent any navigation events from interfering
				self.setNavigationState( true );

				// Minimal delay for skeleton render, then redirect
				setTimeout( function() {
					window.location.href = data.redirectUrl;
				}, 100 );

				// Nothing after this line will execute
				return;
			} else {
					// Client-side navigation - update UI then re-enable buttons
				this.updateURL( targetStep );

				// Load the target step's orchestrator so it's available for next validation
				if ( SCD.Wizard && SCD.Wizard.Orchestrator && SCD.Wizard.Orchestrator.loadCurrentStep ) {
					SCD.Wizard.Orchestrator.loadCurrentStep()
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
			var errorObj = {
				message: 'Navigation failed. Please try again.',
				code: 'ajax_error',
				status: textStatus,
				details: {}
			};

			// Provide specific error messages based on error type
			if ( 'timeout' === textStatus ) {
				errorObj.message = 'Server is taking too long to respond. Please try again.';
				errorObj.code = 'timeout';
			} else if ( 'abort' === textStatus ) {
				errorObj.message = 'Navigation was cancelled. Please try again.';
				errorObj.code = 'abort';
			} else if ( xhr && 0 === xhr.status ) {
				errorObj.message = 'Unable to connect to server. Please check your internet connection.';
				errorObj.code = 'network_error';
			} else if ( xhr && xhr.status >= 500 ) {
				errorObj.message = 'Server error occurred. Please try again in a moment.';
				errorObj.code = 'server_error';
			} else if ( xhr && 403 === xhr.status ) {
				errorObj.message = 'Permission denied. Please refresh the page and try again.';
				errorObj.code = 'permission_denied';
			} else if ( xhr && 404 === xhr.status ) {
				errorObj.message = 'Navigation endpoint not found. Please contact support.';
				errorObj.code = 'not_found';
			}

			// Try to get specific error from server response
			if ( xhr && xhr.responseJSON ) {
				if ( xhr.responseJSON.data && xhr.responseJSON.data.message ) {
					errorObj.message = xhr.responseJSON.data.message;
					errorObj.code = xhr.responseJSON.data.code || errorObj.code;
				} else if ( xhr.responseJSON.message ) {
					errorObj.message = xhr.responseJSON.message;
				}
				errorObj.details = xhr.responseJSON;
			} else if ( xhr && xhr.responseText ) {
				try {
					var response = JSON.parse( xhr.responseText );
					if ( response.data && response.data.message ) {
						errorObj.message = response.data.message;
						errorObj.code = response.data.code || errorObj.code;
					}
				} catch ( e ) {
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
			var message = error.message || 'Navigation failed. Please try again.';

			// Show retry message as info/warning, not error
			var messageType = error.isRetrying ? 'warning' : 'error';


			// Standard error handling
			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.show( message, messageType );
			} else if ( window.console ) {
				// Fallback to console (better than alert)
				if ( 'error' === messageType ) {
					console.error( '[SCD Navigation] ' + message );
				}
			}

			$( document ).trigger( 'scd:navigation:error', [ error ] );
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
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Orchestrator &&
				'function' === typeof window.SCD.Wizard.Orchestrator.collectCurrentStepData ) {
				try {
					var data = window.SCD.Wizard.Orchestrator.collectCurrentStepData();



					return data && 'object' === typeof data ? data : {};
				} catch ( e ) {
					if ( window.console && window.console.error ) {
						console.error( '[SCD Navigation] Error collecting step data:', e );
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

				// Load step data from server before updating UI to ensure fresh data
			if ( SCD.Wizard && SCD.Wizard.Orchestrator && SCD.Wizard.Orchestrator.loadCurrentStep ) {
				SCD.Wizard.Orchestrator.loadCurrentStep()
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

				$( document ).trigger( 'scd:wizard:stepChanged', [ {
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
			$( '.scd-wizard-navigation .step' ).removeClass( 'active current' );
			$( '.scd-wizard-navigation .step[data-step="' + targetStep + '"]' ).addClass( 'active current' );

			this.updateProgress( targetStep );

			// Ensure completed steps UI is synced with current state
			if ( SCD.Wizard && SCD.Wizard.StateManager ) {
				var completedSteps = SCD.Wizard.StateManager.get( 'completedSteps' );
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

			$( '.scd-progress-bar' ).css( 'width', progress + '%' );
			$( '.scd-progress-text' ).text( 'Step ' + ( currentIndex + 1 ) + ' of ' + this.config.steps.length );
		},

		/**
		 * Update completed steps visual indicators
		 *
		 * @since 1.0.0
		 * @param {array} completedSteps Array of completed step names
		 */
		updateCompletedSteps: function( completedSteps ) {
			if ( !  completedSteps || ! Array.isArray( completedSteps ) ) {
				return;
			}

			// Remove all completed classes first
			$( '.scd-wizard-steps li' ).removeClass( 'completed' );

			// Add completed class to each completed step
			for ( var i = 0; i < completedSteps.length; i++ ) {
				var stepName = completedSteps[i];
				$( '.scd-wizard-steps li[data-step-name="' + stepName + '"]' ).addClass( 'completed' );
			}
		},

		/**
		 * Notify other components of step change
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		notifyStepChange: function( targetStep ) {
			$( document ).trigger( 'scd:wizard:stepChanged', [ targetStep ] );
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
					// Show "Processing..." only on clicked button
					$buttons.each( function() {
						var $btn = $( this );
						var $text = $btn.find( '.scd-nav-btn__text' );
						var $icon = $btn.find( '.dashicons' );

						// Check if this is the clicked button
						var isClickedButton = $clickedButton && $clickedButton.length && $btn.is( $clickedButton );

						if ( isClickedButton ) {
							// Clicked button: show "Processing..." and hide icon
							if ( $text.length && !$btn.data( self.DATA_KEYS.ORIGINAL_TEXT ) ) {
								$btn.data( self.DATA_KEYS.ORIGINAL_TEXT, $text.text() );
								$text.text( 'Processing...' );
							}
							if ( $icon.length ) {
								$icon.hide();
							}
						}
						// Other buttons: just disabled, keep text and icon
					} );
				} else {
					// Restore all buttons
					$buttons.each( function() {
						var $btn = $( this );
						var $text = $btn.find( '.scd-nav-btn__text' );
						var $icon = $btn.find( '.dashicons' );
						var originalText = $btn.data( self.DATA_KEYS.ORIGINAL_TEXT );
						if ( $text.length && originalText ) {
							$text.text( originalText );
							$btn.removeData( self.DATA_KEYS.ORIGINAL_TEXT );
						}
						if ( $icon.length ) {
							$icon.show();
						}
					} );

					// Clear clicked button reference
					this.clickedButton = null;
				}
			}

			// Container can stay cached as it's always present
			var $container = $( '.scd-wizard-navigation' );
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
			if ( window.scdNavigation && window.scdNavigation.nonce ) {
				return window.scdNavigation.nonce;
			}
			if ( window.scdWizardData && window.scdWizardData.nonces ) {
				return window.scdWizardData.nonces.scd_wizard_nonce || window.scdWizardData.nonces.wizard_nonce;
			}
			return '';
		},

	/**
	 * Check if user is trying to use PRO features (client-side)
	 *
	 * PHASE 3: Clean delegation to ProFeatureGate service.
	 * Delegates to centralized PRO feature detection service.
	 *
	 * @since 1.0.0
	 * @param {string} step Current step name
	 * @param {object} formData Form data being validated
	 * @returns {object} { blocked: boolean, message: string, feature: string, upgradeUrl: string }
	 */
	checkProFeatures: function( step, formData ) {
		// Delegate to ProFeatureGate service (loaded via dependencies)
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.ProFeatureGate ) {
			return window.SCD.Shared.ProFeatureGate.check( step, formData );
		}

		// Service not loaded - log error and allow all features
		console.error( '[SCD Navigation] ProFeatureGate service not loaded - check script dependencies' );
		return { blocked: false };
	},

		/**
		 * Cleanup navigation service
		 *
		 * @since 1.0.0
		 */
		destroy: function() {
			$( document ).off( '.scd-nav' );

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
		window.SCD = window.SCD || {};
		window.SCD.Wizard = window.SCD.Wizard || {};
		window.SCD.Wizard.Navigation = NavigationService;

		if ( ( $( '.scd-wizard-page' ).length > 0 ||
			$( '.scd-wizard-wrap' ).length > 0 ||
			window.location.href.indexOf( 'action=wizard' ) !== -1 ) &&
			window.SCD && window.SCD.Wizard && window.SCD.Wizard.Navigation ) {
			window.SCD.Wizard.Navigation.init();
		}
	} );

} )( jQuery );