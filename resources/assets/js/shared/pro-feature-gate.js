/**
 * PRO Feature Gate Service
 *
 * Handles PRO feature detection and upgrade prompts across the plugin.
 * Completely independent service that can be used by any module.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	// Initialize namespace
	window.SCD = window.SCD || {};
	window.SCD.Shared = window.SCD.Shared || {};

	/**
	 * PRO Feature Gate Service
	 *
	 * Public API for checking PRO features and showing upgrade prompts.
	 */
	window.SCD.Shared.ProFeatureGate = {

		/**
		 * Check if user is trying to use PRO features
		 *
		 * Prevents field validation errors from showing for PRO features
		 * by checking feature access before validation runs.
		 *
		 * @since 1.0.0
		 * @param {string} step Current step name
		 * @param {object} formData Form data being validated
		 * @returns {object} { blocked: boolean, message: string, feature: string, upgradeUrl: string }
		 */
		check: function( step, formData ) {
			// Check if user is premium (from localized config)
			var isPremium = window.scdWizardConfig && window.scdWizardConfig.is_premium;

			// If premium, allow all features
			if ( isPremium ) {
				return { blocked: false };
			}

			// Get upgrade URL
			var upgradeUrl = ( window.scdWizardConfig && window.scdWizardConfig.upgrade_url ) || '#';

			// Check discounts step for PRO discount types
			if ( 'discounts' === step && formData.discountType ) {
				var proDiscountTypes = [ 'tiered', 'bogo', 'spend_threshold' ];
				if ( -1 !== proDiscountTypes.indexOf( formData.discountType ) ) {
					// Show modal
					this.showModal( {
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
				this.showModal( {
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
				this.showModal( {
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
		showModal: function( options ) {
			var self = this;
			var $modal = $( '#scd-pro-required-modal' );

			if ( ! $modal.length ) {
				console.error( '[SCD ProFeatureGate] PRO modal not found in DOM' );
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
					window.gtag( 'event', 'upgrade_attempt', {
						event_category: 'Pro_Feature',
						event_label: options.featureKey
					} );
				}

				// Open upgrade page in new tab
				window.open( upgradeUrl, '_blank' );
			} );

			// Handle change button
			$modal.find( '.scd-modal-change' ).off( 'click' ).on( 'click', function() {
				// Close modal
				self.closeModal();

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
				self.closeModal();

				// Trigger save via orchestrator
				if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Orchestrator ) {
					var currentStep = window.SCD.Wizard.StateManager.state.currentStep;
					var stepOrchestrator = window.SCD.Wizard.Orchestrator.getStepInstance( currentStep );

					if ( stepOrchestrator && 'function' === typeof stepOrchestrator.saveStep ) {
						stepOrchestrator.saveStep().done( function() {
							if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
								window.SCD.Shared.NotificationService.success( 'Draft saved! Upgrade anytime to activate this campaign.' );
							}
						} );
					}
				}
			} );

			// Handle close button
			$modal.find( '.scd-modal-close' ).off( 'click' ).on( 'click', function() {
				self.closeModal();
			} );

			// Close on overlay click
			$modal.find( '.scd-modal-overlay' ).off( 'click' ).on( 'click', function() {
				self.closeModal();
			} );

			// Close on ESC key
			$( document ).off( 'keydown.scd-pro-modal' ).on( 'keydown.scd-pro-modal', function( e ) {
				if ( 27 === e.keyCode ) {
					self.closeModal();
				}
			} );
		},

		/**
		 * Close PRO required modal
		 *
		 * @since 1.0.0
		 */
		closeModal: function() {
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
				tiered: 'Tiered Discount',
				bogo: 'Buy One Get One',
				spend_threshold: 'Spend Threshold'
			};
			return labels[type] || type;
		}
	};

	// Emit service ready event for dependency tracking
	$( document ).ready( function() {
		$( document ).trigger( 'scd:service:ready', [ 'ProFeatureGate' ] );
	} );

} )( jQuery );
