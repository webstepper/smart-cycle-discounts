/**
 * Skeleton Screen Templates
 *
 * Centralized skeleton HTML generation for wizard steps.
 * Provides instant visual feedback during navigation transitions.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Wizard = window.SCD.Wizard || {};

	/**
	 * Skeleton Templates Service
	 *
	 * Public API for generating skeleton screens during navigation.
	 */
	window.SCD.Wizard.SkeletonTemplates = {

		/**
		 * Step configuration
		 *
		 * @since 1.0.0
		 */
		config: {
			steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ]
		},

		/**
		 * Initialize configuration
		 *
		 * @since 1.0.0
		 * @param {object} config Configuration options
		 */
		init: function( config ) {
			if ( config && config.steps ) {
				this.config.steps = config.steps;
			}
		},

		/**
		 * Show skeleton screen for a target step
		 *
		 * Displays a skeleton placeholder while navigating to give instant feedback.
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 */
		render: function( targetStep ) {
			var contentSkeleton = this.getStepContent( targetStep );
			var fullSkeleton = this.buildFullPage( targetStep, contentSkeleton );
			var $wizardWrap = $( '.scd-wizard-wrap' ).first();

			if ( $wizardWrap.length ) {
				$( 'body' ).append( '<div class="scd-loading-bar"></div>' );

				$wizardWrap.addClass( 'scd-wizard-main--loading' );
				$wizardWrap.html( fullSkeleton );
				$wizardWrap.removeClass( 'scd-wizard-main--loading' );
			} else {
				console.error( '[SCD SkeletonTemplates] No .scd-wizard-wrap element found!' );
			}
		},

		/**
		 * Build full page skeleton with header, progress, content, sidebar, and navigation
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 * @param {string} contentSkeleton Content skeleton HTML
		 * @returns {string} Complete skeleton HTML
		 */
		buildFullPage: function( targetStep, contentSkeleton ) {
			var currentIndex = this.config.steps.indexOf( targetStep );

			var header = this.buildHeader();
			var progress = this.buildProgress( targetStep, currentIndex );
			var sidebar = this.buildSidebar();
			var navigation = this.buildNavigation( currentIndex );
			var content = this.buildContent( targetStep, contentSkeleton, sidebar, navigation );

			return header + progress + content;
		},

		/**
		 * Build header skeleton
		 *
		 * @since 1.0.0
		 * @returns {string} Header skeleton HTML
		 */
		buildHeader: function() {
			return '<div class="scd-wizard-header" style="margin-bottom:20px;display:flex;align-items:center;">' +
				'<h1 style="margin:0;padding:0;">' +
				'<span class="scd-skeleton-line" style="width:220px;height:32px;display:inline-block;margin-bottom:0;"></span>' +
				'</h1>' +
				'</div>';
		},

		/**
		 * Build progress indicator skeleton
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 * @param {number} currentIndex Current step index
		 * @returns {string} Progress skeleton HTML
		 */
		buildProgress: function( targetStep, currentIndex ) {
			var progress = '<div class="scd-wizard-progress" style="padding:24px 40px;">' +
				'<ul class="scd-wizard-steps" style="max-width:800px;margin:0 auto;padding:0;list-style:none;display:flex;justify-content:space-between;align-items:center;">';

			for ( var i = 0; i < this.config.steps.length; i++ ) {
				var stepName = this.config.steps[i];
				var isActive = i === currentIndex;
				var isCompleted = i < currentIndex;
				var classes = 'scd-wizard-step scd-wizard-step--skeleton';
				if ( isActive ) {
					classes += ' active current';
				}
				if ( isCompleted ) {
					classes += ' completed';
				}

				var stepNumberSkeleton = '<div class="scd-skeleton-step-circle" style="width:32px;height:32px;border-radius:50%;margin-bottom:8px;background:linear-gradient(90deg,#f0f0f1 25%,#e8e9ea 50%,#f0f0f1 75%);background-size:200% 100%;animation:scd-skeleton-shimmer 1.8s ease-in-out infinite;"></div>';

				var labelWidths = [ '80px', '95px', '110px', '85px', '100px' ];
				var labelWidth = labelWidths[i] || '90px';
				var stepLabelSkeleton = '<div class="scd-skeleton-step-label" style="width:' + labelWidth + ';height:13px;margin-top:8px;border-radius:4px;background:linear-gradient(90deg,#f0f0f1 25%,#e8e9ea 50%,#f0f0f1 75%);background-size:200% 100%;animation:scd-skeleton-shimmer 1.8s ease-in-out infinite;animation-delay:' + ( i * 0.05 ) + 's;"></div>';

				progress += '<li class="' + classes + '" data-step-name="' + stepName + '" style="flex:1;text-align:center;display:flex;flex-direction:column;align-items:center;">' +
					stepNumberSkeleton +
					stepLabelSkeleton +
					'</li>';
			}
			progress += '</ul></div>';

			return progress;
		},

		/**
		 * Build sidebar skeleton
		 *
		 * @since 1.0.0
		 * @returns {string} Sidebar skeleton HTML
		 */
		buildSidebar: function() {
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
			for ( var s = 0; s < 3; s++ ) {
				var isOpen = 0 === s;

				// Section container (matches .scd-sidebar-section)
				sidebar += '<div class="scd-sidebar-section" style="margin-bottom:' + ( s < 2 ? '20px' : '0' ) + ';background:#fff;border:1px solid #f0f0f1;border-radius:4px;">';

				// Section header (matches .scd-sidebar-section-header)
				sidebar += '<div class="scd-sidebar-section-header" style="display:flex;align-items:center;padding:16px 20px;background:' + ( isOpen ? '#f6f7f7' : 'transparent' ) + ';border-bottom:' + ( isOpen ? '1px solid #f0f0f1' : 'none' ) + ';border-radius:' + ( isOpen ? '4px 4px 0 0' : '4px' ) + ';">';
				sidebar += '<span class="scd-skeleton-icon" style="width:20px;height:20px;margin-right:12px;"></span>';
				sidebar += '<span class="scd-skeleton-line" style="width:' + ( 110 + s * 20 ) + 'px;height:14px;margin-bottom:0;"></span>';
				sidebar += '</div>';

				// Section content (only for open section)
				if ( isOpen ) {
					sidebar += '<div class="scd-sidebar-section-content" style="padding:20px;">';

					// List items (3 items)
					for ( var i = 0; i < 3; i++ ) {
						sidebar += '<div style="margin-bottom:' + ( i < 2 ? '8px' : '0' ) + ';">';
						sidebar += '<span class="scd-skeleton-line" style="width:' + ( 85 + i * 5 ) + '%;height:13px;"></span>';
						sidebar += '</div>';
					}

					sidebar += '</div>';
				}

				sidebar += '</div>';
			}

			sidebar += '</div>';
			sidebar += '</div>';
			sidebar += '</aside>';

			return sidebar;
		},

		/**
		 * Build navigation skeleton
		 *
		 * @since 1.0.0
		 * @param {number} currentIndex Current step index
		 * @returns {string} Navigation skeleton HTML
		 */
		buildNavigation: function( currentIndex ) {
			var navigation = '<nav class="scd-wizard-navigation" role="navigation" style="padding:12px 0;">';
			navigation += '<div class="scd-nav-container">';

			// Left section - Previous button (only if not first step)
			navigation += '<div class="scd-nav-section scd-nav-section--left">';
			if ( currentIndex > 0 ) {
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

			navigation += '</div>';
			navigation += '</nav>';

			return navigation;
		},

		/**
		 * Build content wrapper with step content and sidebar
		 *
		 * @since 1.0.0
		 * @param {string} targetStep Target step name
		 * @param {string} contentSkeleton Content skeleton HTML
		 * @param {string} sidebar Sidebar skeleton HTML
		 * @param {string} navigation Navigation skeleton HTML
		 * @returns {string} Content wrapper HTML
		 */
		buildContent: function( targetStep, contentSkeleton, sidebar, navigation ) {
			return '<form method="post" class="scd-wizard-form" autocomplete="off">' +
				'<div class="scd-wizard-content scd-wizard-layout" style="gap:40px;padding:30px 0 40px 0;" data-step="' + targetStep + '">' +
				'<div class="scd-step-main-content scd-wizard-step--' + targetStep + '">' + contentSkeleton + '</div>' +
				sidebar +
				'</div>' +
				navigation +
				'</form>';
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
		getStepContent: function( stepName ) {
			var skeleton = '';

			// Step-specific skeletons matching actual layouts
			if ( 'basic' === stepName ) {
				// Basic: Campaign Details + Priority cards
				skeleton = this.createCard( true, 2 ) + this.createCard( true, 1 );

			} else if ( 'products' === stepName ) {
				// Products: Selection card with grid
				skeleton = this.createProductsCard();

			} else if ( 'discounts' === stepName ) {
				// Discounts: Type selector + Config cards
				skeleton = this.createCard( true, 1 ) + this.createCard( true, 3 );

			} else if ( 'schedule' === stepName ) {
				// Schedule: Date Range + Usage Limits
				skeleton = this.createCard( true, 2 ) + this.createCard( true, 2 );

			} else if ( 'review' === stepName ) {
				// Review: Summary cards with varied line widths for realistic look
				skeleton = this.createReviewCards();

			} else {
				// Fallback: Generic 2-card layout
				skeleton = this.createCard( true, 2 ) + this.createCard( true, 2 );
			}

			return skeleton;
		},

		/**
		 * Create a wizard card skeleton matching actual .scd-card structure
		 *
		 * @since 1.0.0
		 * @param {boolean} hasIcon Whether to show icon
		 * @param {number} fields Number of fields
		 * @returns {string} Card skeleton HTML
		 */
		createCard: function( hasIcon, fields ) {
			var card = '<div class="scd-card scd-wizard-card" style="padding:20px;margin-bottom:20px;border-radius:8px;">' +
				'<div class="scd-card__header">' +
				'<h3 class="scd-card__title" style="margin:0 0 8px 0;display:flex;align-items:center;">';

			if ( hasIcon ) {
				card += '<span class="scd-skeleton-icon" style="width:20px;height:20px;display:inline-block;margin-right:8px;"></span>';
			}

			card += '<span class="scd-skeleton-line" style="width:200px;height:22px;display:inline-block;vertical-align:middle;margin-bottom:0;"></span>' +
				'</h3>' +
				'<p class="scd-card__subtitle" style="margin:0 0 16px 0;">' +
				'<span class="scd-skeleton-line" style="width:85%;height:14px;"></span>' +
				'</p>' +
				'</div>' +
				'<div class="scd-card__content" style="padding-top:8px;">';

			for ( var i = 0; i < fields; i++ ) {
				card += '<div class="scd-skeleton-field" style="margin-bottom:' + ( i < fields - 1 ? '16px' : '0' ) + ';"></div>';
			}

			card += '</div></div>';

			return card;
		},

		/**
		 * Create products step card with grid
		 *
		 * @since 1.0.0
		 * @returns {string} Products card skeleton HTML
		 */
		createProductsCard: function() {
			return '<div class="scd-card scd-wizard-card">' +
				'<div class="scd-card__header">' +
				'<h3 class="scd-card__title">' +
				'<span class="scd-skeleton-icon" style="width:20px;height:20px;display:inline-block;margin-right:8px;"></span>' +
				'<span class="scd-skeleton-line" style="width:50%;height:20px;display:inline-block;vertical-align:middle;margin-bottom:0;"></span>' +
				'</h3>' +
				'<p class="scd-card__subtitle">' +
				'<span class="scd-skeleton-line scd-skeleton-line--long" style="height:14px;"></span>' +
				'</p>' +
				'</div>' +
				'<div class="scd-card__content">' +
				'<div class="scd-skeleton-field" style="margin-bottom:20px;"></div>' +
				'<div class="scd-skeleton-grid">' +
				'<div class="scd-skeleton-grid-item"></div>' +
				'<div class="scd-skeleton-grid-item"></div>' +
				'<div class="scd-skeleton-grid-item"></div>' +
				'<div class="scd-skeleton-grid-item"></div>' +
				'</div>' +
				'</div>' +
				'</div>';
		},

		/**
		 * Create review step cards with varied line widths
		 *
		 * @since 1.0.0
		 * @returns {string} Review cards skeleton HTML
		 */
		createReviewCards: function() {
			var rc1 = '<div class="scd-card scd-wizard-card">' +
				'<div class="scd-card__header">' +
				'<h3 class="scd-card__title">' +
				'<span class="scd-skeleton-line" style="width:35%;height:20px;display:inline-block;margin-bottom:0;"></span>' +
				'</h3>' +
				'</div>' +
				'<div class="scd-card__content">' +
				'<div class="scd-skeleton-line" style="width:92%;"></div>' +
				'<div class="scd-skeleton-line" style="width:78%;"></div>' +
				'<div class="scd-skeleton-line" style="width:65%;"></div>' +
				'</div>' +
				'</div>';

			var rc2 = '<div class="scd-card scd-wizard-card">' +
				'<div class="scd-card__header">' +
				'<h3 class="scd-card__title">' +
				'<span class="scd-skeleton-line" style="width:40%;height:20px;display:inline-block;margin-bottom:0;"></span>' +
				'</h3>' +
				'</div>' +
				'<div class="scd-card__content">' +
				'<div class="scd-skeleton-line" style="width:88%;"></div>' +
				'<div class="scd-skeleton-line" style="width:95%;"></div>' +
				'<div class="scd-skeleton-line" style="width:72%;"></div>' +
				'</div>' +
				'</div>';

			var rc3 = '<div class="scd-card scd-wizard-card">' +
				'<div class="scd-card__header">' +
				'<h3 class="scd-card__title">' +
				'<span class="scd-skeleton-line" style="width:45%;height:20px;display:inline-block;margin-bottom:0;"></span>' +
				'</h3>' +
				'</div>' +
				'<div class="scd-card__content">' +
				'<div class="scd-skeleton-line" style="width:85%;"></div>' +
				'<div class="scd-skeleton-line" style="width:90%;"></div>' +
				'<div class="scd-skeleton-line" style="width:68%;"></div>' +
				'</div>' +
				'</div>';

			var rc4 = '<div class="scd-card scd-wizard-card">' +
				'<div class="scd-card__header">' +
				'<h3 class="scd-card__title">' +
				'<span class="scd-skeleton-line" style="width:38%;height:20px;display:inline-block;margin-bottom:0;"></span>' +
				'</h3>' +
				'</div>' +
				'<div class="scd-card__content">' +
				'<div class="scd-skeleton-line" style="width:93%;"></div>' +
				'<div class="scd-skeleton-line" style="width:80%;"></div>' +
				'<div class="scd-skeleton-line" style="width:70%;"></div>' +
				'</div>' +
				'</div>';

			return rc1 + rc2 + rc3 + rc4;
		}
	};

	// Emit service ready event for dependency tracking
	$( document ).ready( function() {
		$( document ).trigger( 'scd:service:ready', [ 'SkeletonTemplates' ] );
	} );

} )( jQuery );
