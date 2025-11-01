/**
 * Wizard Navigation Service
 *
 * Clean, reliable navigation system following WordPress patterns.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
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
		 * Displays a skeleton placeholder while navigating to give instant feedback.
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		showSkeletonScreen: function( targetStep ) {
			var contentSkeleton = this.getSkeletonHTML( targetStep );
			var fullSkeleton = this.buildFullPageSkeleton( targetStep, contentSkeleton );
			var $wizardWrap = $( '.scd-wizard-wrap' ).first();

			if ( $wizardWrap.length ) {
				// Add loading progress bar at top
				$( 'body' ).append( '<div class="scd-loading-bar"></div>' );

				// Show skeleton immediately for instant feedback
				$wizardWrap.addClass( 'scd-wizard-main--loading' );
				$wizardWrap.html( fullSkeleton );
				$wizardWrap.removeClass( 'scd-wizard-main--loading' );
			} else {
				console.error( '[SCD Skeleton] No .scd-wizard-wrap element found!' );
			}
		},

		buildFullPageSkeleton: function( targetStep, contentSkeleton ) {
			var stepLabels = { 'basic': 'Campaign Setup', 'products': 'Product Selection', 'discounts': 'Discount Configuration', 'schedule': 'Schedule & Limits', 'review': 'Review & Launch' };
			var currentIndex = this.config.steps.indexOf( targetStep );
			var totalSteps = this.config.steps.length;
			var isLast = currentIndex === totalSteps - 1;

			var header = '<div class="scd-wizard-header" style="margin-bottom:20px;display:flex;align-items:center;"><h1 style="margin:0;padding:0;"><span class="scd-skeleton-line" style="width:220px;height:32px;display:inline-block;margin-bottom:0;"></span></h1></div>';

			var progress = '<div class="scd-wizard-progress" style="padding:24px 40px;"><ul class="scd-wizard-steps" style="max-width:800px;margin:0 auto;padding:0;list-style:none;display:flex;justify-content:space-between;align-items:center;">';
			for ( var i = 0; i < this.config.steps.length; i++ ) {
				var stepName = this.config.steps[i];
				var isActive = i === currentIndex;
				var isCompleted = i < currentIndex;
				var classes = 'scd-wizard-step scd-wizard-step--skeleton';
				if ( isActive ) { classes += ' active current'; }
				if ( isCompleted ) { classes += ' completed'; }

				// Create skeleton circle for step number
				var stepNumberSkeleton = '<div class="scd-skeleton-step-circle" style="width:32px;height:32px;border-radius:50%;margin-bottom:8px;background:linear-gradient(90deg,#f0f0f1 25%,#e8e9ea 50%,#f0f0f1 75%);background-size:200% 100%;animation:scd-skeleton-shimmer 1.8s ease-in-out infinite;"></div>';

				// Create skeleton line for step label with varied widths for organic look
				var labelWidths = ['80px', '95px', '110px', '85px', '100px'];
				var labelWidth = labelWidths[i] || '90px';
				var stepLabelSkeleton = '<div class="scd-skeleton-step-label" style="width:' + labelWidth + ';height:13px;margin-top:8px;border-radius:4px;background:linear-gradient(90deg,#f0f0f1 25%,#e8e9ea 50%,#f0f0f1 75%);background-size:200% 100%;animation:scd-skeleton-shimmer 1.8s ease-in-out infinite;animation-delay:' + (i * 0.05) + 's;"></div>';

				progress += '<li class="' + classes + '" data-step-name="' + stepName + '" style="flex:1;text-align:center;display:flex;flex-direction:column;align-items:center;">' +
					stepNumberSkeleton +
					stepLabelSkeleton +
					'</li>';
			}
			progress += '</ul></div>';

			// Build clean sidebar skeleton matching real structure
			var sidebar = '<aside class="scd-step-sidebar" style="flex:0 0 360px;width:360px;">';

			// Main sidebar card (clean style matching content cards)
			sidebar += '<div class="scd-card scd-sidebar-panel" style="padding:20px;border-radius:8px;">';

			// Card header (same clean style as content cards)
			sidebar += '<div class="scd-card__header">';
			sidebar += '<h3 class="scd-card__title" style="margin:0 0 8px 0;display:flex;align-items:center;">';
			sidebar += '<span class="scd-skeleton-icon" style="width:20px;height:20px;display:inline-block;margin-right:8px;"></span>';
			sidebar += '<span class="scd-skeleton-line" style="width:180px;height:20px;display:inline-block;vertical-align:middle;margin-bottom:0;"></span>';
			sidebar += '</h3>';
			sidebar += '<p class="scd-card__subtitle" style="margin:0 0 16px 0;">';
			sidebar += '<span class="scd-skeleton-line" style="width:85%;height:14px;"></span>';
			sidebar += '</p>';
			sidebar += '</div>';

			// Card content with sections
			sidebar += '<div class="scd-card__content" style="padding-top:8px;">';

			// Create 3 collapsible sections (1 open, 2 collapsed)
			for (var s = 0; s < 3; s++) {
				var isOpen = s === 0;

				// Section container (matches .scd-sidebar-section)
				sidebar += '<div class="scd-sidebar-section" style="margin-bottom:' + (s < 2 ? '20px' : '0') + ';background:#fff;border:1px solid #f0f0f1;border-radius:4px;">';

				// Section header (matches .scd-sidebar-section-header)
				sidebar += '<div class="scd-sidebar-section-header" style="display:flex;align-items:center;padding:16px 20px;background:' + (isOpen ? '#f6f7f7' : 'transparent') + ';border-bottom:' + (isOpen ? '1px solid #f0f0f1' : 'none') + ';border-radius:' + (isOpen ? '4px 4px 0 0' : '4px') + ';">';
				sidebar += '<span class="scd-skeleton-icon" style="width:20px;height:20px;margin-right:12px;"></span>';
				sidebar += '<span class="scd-skeleton-line" style="width:' + (110 + s * 20) + 'px;height:14px;margin-bottom:0;"></span>';
				sidebar += '</div>';

				// Section content (only for open section)
				if (isOpen) {
					sidebar += '<div class="scd-sidebar-section-content" style="padding:20px;">';

					// List items (3 items)
					for (var i = 0; i < 3; i++) {
						sidebar += '<div style="margin-bottom:' + (i < 2 ? '8px' : '0') + ';">';
						sidebar += '<span class="scd-skeleton-line" style="width:' + (85 + i * 5) + '%;height:13px;"></span>';
						sidebar += '</div>';
					}

					sidebar += '</div>';
				}

				sidebar += '</div>'; // Close section
			}

			sidebar += '</div>'; // Close card__content
			sidebar += '</div>'; // Close card
			sidebar += '</aside>';

			// Build navigation skeleton
			var navigation = '<nav class="scd-wizard-navigation" role="navigation" style="padding:12px 0;">';
			navigation += '<div class="scd-nav-container">';

			// Left section - Previous button (only if not first step)
			navigation += '<div class="scd-nav-section scd-nav-section--left">';
			if (currentIndex > 0) {
				navigation += '<div class="scd-skeleton-button" style="min-width:100px;width:100px;height:32px;border-radius:4px;"></div>';
			}
			navigation += '</div>';

			// Center section - Step counter
			navigation += '<div class="scd-nav-section scd-nav-section--center">';
			navigation += '<div class="scd-nav-status">';
			navigation += '<span class="scd-skeleton-line" style="width:90px;height:14px;display:inline-block;margin-bottom:0;"></span>';
			navigation += '</div>';
			navigation += '</div>';

			// Right section - Next/Complete button
			navigation += '<div class="scd-nav-section scd-nav-section--right">';
			navigation += '<div class="scd-skeleton-button" style="min-width:100px;width:100px;height:32px;border-radius:4px;"></div>';
			navigation += '</div>';

			navigation += '</div>'; // Close nav-container
			navigation += '</nav>';

			var content = '<form method="post" class="scd-wizard-form" autocomplete="off"><div class="scd-wizard-content scd-wizard-layout" style="gap:40px;padding:30px 0 40px 0;" data-step="' + targetStep + '"><div class="scd-step-main-content scd-wizard-step--' + targetStep + '">' + contentSkeleton + '</div>' + sidebar + '</div>' + navigation + '</form>';

			return header + progress + content;
		},

		/**
		 * Get skeleton HTML for a specific step
		 *
		 * Returns appropriate skeleton structure based on target step.
		 *
		 * @since 1.0.0
		 * @param {string} stepName Step name
		 * @returns {string} Skeleton HTML
		 */
		getSkeletonHTML: function( stepName ) {
			// Helper to create wizard card skeleton matching actual .scd-card structure
			var createCard = function( hasIcon, fields ) {
				var c = '<div class="scd-card scd-wizard-card" style="padding:20px;margin-bottom:20px;border-radius:8px;"><div class="scd-card__header"><h3 class="scd-card__title" style="margin:0 0 8px 0;display:flex;align-items:center;">';
				if ( hasIcon ) { c += '<span class="scd-skeleton-icon" style="width:20px;height:20px;display:inline-block;margin-right:8px;"></span>'; }
				c += '<span class="scd-skeleton-line" style="width:200px;height:22px;display:inline-block;vertical-align:middle;margin-bottom:0;"></span></h3>';
				c += '<p class="scd-card__subtitle" style="margin:0 0 16px 0;"><span class="scd-skeleton-line" style="width:85%;height:14px;"></span></p>';
				c += '</div><div class="scd-card__content" style="padding-top:8px;">';
				for ( var i = 0; i < fields; i++ ) { c += '<div class="scd-skeleton-field" style="margin-bottom:' + ( i < fields - 1 ? '16px' : '0' ) + ';"></div>'; }
				c += '</div></div>';
				return c;
			};

			var skeleton = '';

			// Step-specific skeletons matching actual layouts
			if ( 'basic' === stepName ) {
				// Basic: Campaign Details + Priority cards
				skeleton = createCard( true, 2 ) + createCard( true, 1 );

			} else if ( 'products' === stepName ) {
				// Products: Selection card with grid
				skeleton = '<div class="scd-card scd-wizard-card"><div class="scd-card__header"><h3 class="scd-card__title">' +
					'<span class="scd-skeleton-icon" style="width:20px;height:20px;display:inline-block;margin-right:8px;"></span>' +
					'<span class="scd-skeleton-line" style="width:50%;height:20px;display:inline-block;vertical-align:middle;margin-bottom:0;"></span></h3>' +
					'<p class="scd-card__subtitle"><span class="scd-skeleton-line scd-skeleton-line--long" style="height:14px;"></span></p>' +
					'</div><div class="scd-card__content">' +
					'<div class="scd-skeleton-field" style="margin-bottom:20px;"></div>' +
					'<div class="scd-skeleton-grid">' +
					'<div class="scd-skeleton-grid-item"></div>' +
					'<div class="scd-skeleton-grid-item"></div>' +
					'<div class="scd-skeleton-grid-item"></div>' +
					'<div class="scd-skeleton-grid-item"></div>' +
					'</div></div></div>';

			} else if ( 'discounts' === stepName ) {
				// Discounts: Type selector + Config cards
				skeleton = createCard( true, 1 ) + createCard( true, 3 );

			} else if ( 'schedule' === stepName ) {
				// Schedule: Date Range + Usage Limits
				skeleton = createCard( true, 2 ) + createCard( true, 2 );

			} else if ( 'review' === stepName ) {
				// Review: Summary cards with varied line widths for realistic look
				var rc1 = '<div class="scd-card scd-wizard-card"><div class="scd-card__header"><h3 class="scd-card__title">' +
					'<span class="scd-skeleton-line" style="width:35%;height:20px;display:inline-block;margin-bottom:0;"></span></h3>' +
					'</div><div class="scd-card__content">' +
					'<div class="scd-skeleton-line" style="width:92%;"></div>' +
					'<div class="scd-skeleton-line" style="width:78%;"></div>' +
					'<div class="scd-skeleton-line" style="width:65%;"></div>' +
					'</div></div>';
				var rc2 = '<div class="scd-card scd-wizard-card"><div class="scd-card__header"><h3 class="scd-card__title">' +
					'<span class="scd-skeleton-line" style="width:40%;height:20px;display:inline-block;margin-bottom:0;"></span></h3>' +
					'</div><div class="scd-card__content">' +
					'<div class="scd-skeleton-line" style="width:88%;"></div>' +
					'<div class="scd-skeleton-line" style="width:95%;"></div>' +
					'<div class="scd-skeleton-line" style="width:72%;"></div>' +
					'</div></div>';
				var rc3 = '<div class="scd-card scd-wizard-card"><div class="scd-card__header"><h3 class="scd-card__title">' +
					'<span class="scd-skeleton-line" style="width:45%;height:20px;display:inline-block;margin-bottom:0;"></span></h3>' +
					'</div><div class="scd-card__content">' +
					'<div class="scd-skeleton-line" style="width:85%;"></div>' +
					'<div class="scd-skeleton-line" style="width:90%;"></div>' +
					'<div class="scd-skeleton-line" style="width:68%;"></div>' +
					'</div></div>';
				var rc4 = '<div class="scd-card scd-wizard-card"><div class="scd-card__header"><h3 class="scd-card__title">' +
					'<span class="scd-skeleton-line" style="width:38%;height:20px;display:inline-block;margin-bottom:0;"></span></h3>' +
					'</div><div class="scd-card__content">' +
					'<div class="scd-skeleton-line" style="width:93%;"></div>' +
					'<div class="scd-skeleton-line" style="width:80%;"></div>' +
					'<div class="scd-skeleton-line" style="width:70%;"></div>' +
					'</div></div>';
				skeleton = rc1 + rc2 + rc3 + rc4;

			} else {
				// Fallback: Generic 2-card layout
				skeleton = createCard( true, 2 ) + createCard( true, 2 );
			}

			return skeleton;
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
	 * Prevents field validation errors from showing for PRO features
	 * by checking feature access before validation runs.
	 *
	 * @since 1.0.0
	 * @param {string} step Current step name
	 * @param {object} formData Form data being validated
	 * @returns {object} { blocked: boolean, message: string, feature: string, upgradeUrl: string }
	 */
	checkProFeatures: function( step, formData ) {
		// Check if user is premium (from localized config)
		var isPremium = window.scdWizardConfig && window.scdWizardConfig.is_premium;

		// If premium, allow all features
		if ( isPremium ) {
			return { blocked: false };
		}

		// Get upgrade URL
		var upgradeUrl = ( window.scdWizardConfig && window.scdWizardConfig.upgrade_url ) || '#';

		// Check discounts step for PRO discount types
		// formData uses camelCase throughout (from StepPersistence mixin)
		if ( 'discounts' === step && formData.discountType ) {
			var proDiscountTypes = [ 'tiered', 'bogo', 'spend_threshold' ];
			if ( -1 !== proDiscountTypes.indexOf( formData.discountType ) ) {
				// Show modal
				this.showProRequiredModal( {
					featureType: 'discount_type',
					featureName: this.getDiscountTypeLabel( formData.discountType ),
					featureKey: 'discount_type_' + formData.discountType,
					upgradeUrl: upgradeUrl,
					configData: formData,
					step: step
				} );

				return {
					blocked: true,
					message: 'The "' + formData.discountType + '" discount type requires a PRO license.',
					feature: 'discount_type_' + formData.discountType,
					upgradeUrl: upgradeUrl
				};
			}
		}

		// Check schedule step for recurring campaigns
		if ( 'schedule' === step && formData.enable_recurring ) {
		// Show modal
		this.showProRequiredModal( {
			featureType: 'recurring_campaigns',
			featureName: 'Recurring Campaigns',
			featureKey: 'campaigns_recurring',
			upgradeUrl: upgradeUrl,
			configData: formData,
			step: step
		} );

			return {
				blocked: true,
				message: 'Recurring campaigns require a PRO license.',
				feature: 'campaigns_recurring',
				upgradeUrl: upgradeUrl
			};
		}

		// Check products step for advanced filters
		if ( 'products' === step && formData.use_advanced_filters ) {
		// Show modal
		this.showProRequiredModal( {
			featureType: 'advanced_filters',
			featureName: 'Advanced Product Filters',
			featureKey: 'campaigns_advanced_product_filters',
			upgradeUrl: upgradeUrl,
			configData: formData,
			step: step
		} );

			return {
				blocked: true,
				message: 'Advanced product filters require a PRO license.',
				feature: 'campaigns_advanced_product_filters',
				upgradeUrl: upgradeUrl
			};
		}

		// No PRO features detected
		return { blocked: false };
	},

	/**
	 * Show PRO required modal
	 *
	 * Displays modal dialog when free users attempt to use PRO features.
	 *
	 * @since 1.0.0
	 * @param {object} options Modal options
	 */
	showProRequiredModal: function( options ) {
		var self = this;
		var $modal = $( '#scd-pro-required-modal' );

		if ( ! $modal.length ) {
			console.error( '[SCD Navigation] PRO modal not found in DOM' );
			return;
		}

		// Populate modal content
		$modal.find( '#scd-pro-feature-name' ).text( '"' + options.featureName + '"' );


		// Show modal with fade-in animation
		$modal.fadeIn( 200 );
		$( 'body' ).addClass( 'scd-modal-open' );

		// Set up focus trap
		$modal.find( '.scd-modal-upgrade' ).focus();

		// Handle upgrade button
		$modal.find( '.scd-modal-upgrade' ).off( 'click' ).on( 'click', function() {
			var $button = $( this );
			var upgradeUrl = $button.data( 'upgrade-url' );

			// Track conversion attempt if analytics available
			if ( window.gtag ) {
				gtag( 'event', 'upgrade_attempt', {
					'event_category': 'Pro_Feature',
					'event_label': options.featureKey
				} );
			}

			// Open upgrade page in new tab
			window.open( upgradeUrl, '_blank' );
		} );

		// Handle change button
		$modal.find( '.scd-modal-change' ).off( 'click' ).on( 'click', function() {
			// Close modal
			self.closeProModal();

			// Scroll to and highlight the relevant selector
			if ( 'discount_type' === options.featureType ) {
				var $selector = $( '[name="discount_type"]' );
				if ( $selector.length ) {
					$( 'html, body' ).animate( {
						scrollTop: $selector.closest( '.scd-wizard-card' ).offset().top - 100
					}, 400, function() {
						// Highlight the selector briefly
						$selector.closest( '.scd-discount-type-selector' ).addClass( 'scd-highlight-field' );
						setTimeout( function() {
							$selector.closest( '.scd-discount-type-selector' ).removeClass( 'scd-highlight-field' );
						}, 2000 );
					} );
				}
			}
		} );

		// Handle save as draft button
		$modal.find( '.scd-modal-save-draft' ).off( 'click' ).on( 'click', function() {
			// Close modal
			self.closeProModal();

			// Trigger save via orchestrator
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Orchestrator ) {
				var currentStep = SCD.Wizard.StateManager.state.currentStep;
				var stepOrchestrator = SCD.Wizard.Orchestrator.getStepInstance( currentStep );

				if ( stepOrchestrator && 'function' === typeof stepOrchestrator.saveStep ) {
					stepOrchestrator.saveStep().done( function() {
						if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
							SCD.Shared.NotificationService.success( 'Draft saved! Upgrade anytime to activate this campaign.' );
						}
					} );
				}
			}
		} );

		// Handle close button
		$modal.find( '.scd-modal-close' ).off( 'click' ).on( 'click', function() {
			self.closeProModal();
		} );

		// Close on overlay click
		$modal.find( '.scd-modal-overlay' ).off( 'click' ).on( 'click', function() {
			self.closeProModal();
		} );

		// Close on ESC key
		$( document ).off( 'keydown.scd-pro-modal' ).on( 'keydown.scd-pro-modal', function( e ) {
			if ( 27 === e.keyCode ) {
				self.closeProModal();
			}
		} );
	},

	/**
	 * Close PRO required modal
	 *
	 * @since 1.0.0
	 */
	closeProModal: function() {
		var $modal = $( '#scd-pro-required-modal' );
		$modal.fadeOut( 200 );
		$( 'body' ).removeClass( 'scd-modal-open' );
		$( document ).off( 'keydown.scd-pro-modal' );
	},


	/**
	 * Get human-readable discount type label
	 *
	 * @since 1.0.0
	 * @param {string} type Discount type key
	 * @returns {string} Label
	 */
	getDiscountTypeLabel: function( type ) {
		var labels = {
			'tiered': 'Tiered Discount',
			'bogo': 'Buy One Get One',
			'spend_threshold': 'Spend Threshold'
		};
		return labels[type] || type;
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