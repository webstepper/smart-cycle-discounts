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

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};

	/**
	 * Skeleton Templates Service
	 */
	window.WSSCD.Wizard.SkeletonTemplates = {

		config: {
			steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
			labelWidths: [ '45px', '65px', '70px', '65px', '55px' ]
		},

		// Common style constants using CSS variables for consistency
		styles: {
			card: 'padding:var(--wsscd-spacing-lg);margin-bottom:var(--wsscd-spacing-lg);border-radius:var(--wsscd-radius-md);',
			cardTitle: 'margin:0 0 var(--wsscd-spacing-sm) 0;display:flex;align-items:center;',
			cardSubtitle: 'margin:0 0 var(--wsscd-spacing-md) 0;',
			cardContent: 'padding-top:var(--wsscd-spacing-md);',
			flexCenter: 'display:flex;align-items:center;',
			flexBetween: 'display:flex;align-items:center;justify-content:space-between;',
			border: '',
			borderDark: '',
			shimmer: 'background:linear-gradient(90deg,var(--wsscd-color-surface-alt) 25%,var(--wsscd-color-border-light) 50%,var(--wsscd-color-surface-alt) 75%);background-size:200% 100%;animation:wsscd-skeleton-shimmer 1.8s ease-in-out infinite;'
		},

		init: function( config ) {
			if ( config && config.steps ) {
				this.config.steps = config.steps;
			}
		},

		// =========================================================================
		// Helper Methods - Reusable skeleton elements
		// =========================================================================

		line: function( width, height, extra ) {
			return '<span class="wsscd-skeleton-line" style="width:' + width + ';height:' + ( height || '14px' ) + ';' + ( extra || '' ) + '"></span>';
		},

		icon: function( size, extra ) {
			var s = size || 20;
			return '<span class="wsscd-skeleton-icon" style="width:' + s + 'px;height:' + s + 'px;' + ( extra || '' ) + '"></span>';
		},

		input: function( height, extra ) {
			return '<div class="wsscd-skeleton-input" style="height:' + ( height || 'var(--wsscd-input-height)' ) + ';border-radius:var(--wsscd-radius-md);' + ( extra || '' ) + '"></div>';
		},

		radio: function( size ) {
			var s = size || 18;
			return '<div class="wsscd-skeleton-radio" style="width:' + s + 'px;height:' + s + 'px;border-radius:var(--wsscd-radius-full);"></div>';
		},

		badge: function( width, height ) {
			return '<span class="wsscd-skeleton-badge" style="width:' + ( width || '60px' ) + ';height:' + ( height || '20px' ) + ';border-radius:var(--wsscd-radius-full);"></span>';
		},

		button: function( width, height ) {
			return '<div class="wsscd-skeleton-button" style="width:' + ( width || '100px' ) + ';height:' + ( height || 'var(--wsscd-button-height)' ) + ';border-radius:var(--wsscd-radius-sm);"></div>';
		},

		// Card wrapper helpers
		cardStart: function( titleWidth, subtitleWidth, badge ) {
			var html = [
				'<div class="wsscd-card wsscd-wizard-card" style="' + this.styles.card + '">',
				'<div class="wsscd-card__header">',
				'<h3 class="wsscd-card__title" style="' + this.styles.cardTitle + '">',
				this.icon( 20, 'display:inline-block;margin-right:var(--wsscd-spacing-sm);' ),
				this.line( titleWidth || '160px', '22px', 'display:inline-block;vertical-align:middle;margin-bottom:0;' )
			];
			if ( badge ) {
				html.push( '<span class="wsscd-skeleton-badge" style="width:' + badge + ';height:20px;margin-left:var(--wsscd-spacing-sm);border-radius:var(--wsscd-radius-full);"></span>' );
			}
			html.push(
				'</h3>',
				'<p class="wsscd-card__subtitle" style="' + this.styles.cardSubtitle + '">',
				this.line( subtitleWidth || '85%', '14px' ),
				'</p>',
				'</div>',
				'<div class="wsscd-card__content" style="' + this.styles.cardContent + '">'
			);
			return html.join( '' );
		},

		cardEnd: function() {
			return '</div></div>';
		},

		field: function( labelWidth, inputHeight, isLast ) {
			return [
				'<div class="wsscd-skeleton-field" style="margin-bottom:' + ( isLast ? '0' : 'var(--wsscd-spacing-lg)' ) + ';">',
				this.line( labelWidth || '120px', '14px', 'margin-bottom:var(--wsscd-spacing-sm);display:block;' ),
				this.input( inputHeight ),
				'</div>'
			].join( '' );
		},

		// =========================================================================
		// Main API
		// =========================================================================

		render: function( targetStep ) {
			var $wizardWrap = $( '.wsscd-wizard-wrap' ).first();
			if ( ! $wizardWrap.length ) {
				return;
			}

			$( 'body' ).append( '<div class="wsscd-loading-bar"></div>' );
			$wizardWrap.addClass( 'wsscd-wizard-main--loading' )
				.html( this.buildFullPage( targetStep, this.getStepContent( targetStep ) ) )
				.removeClass( 'wsscd-wizard-main--loading' );
		},

		buildFullPage: function( targetStep, contentSkeleton ) {
			var idx = this.config.steps.indexOf( targetStep );
			return this.buildProgress( targetStep, idx ) +
				this.buildContent( targetStep, contentSkeleton, this.buildSidebar(), this.buildNavigation( idx ) );
		},

		// =========================================================================
		// Layout Components
		// =========================================================================

		buildProgress: function( targetStep, currentIndex ) {
			var self = this;
			// Use actual HTML structure so CSS from wizard-fullscreen.css applies identically
			var html = [
				'<div class="wsscd-wizard-progress">',
				'<div class="wsscd-wizard-progress-header">',
				'<div class="wsscd-wizard-progress-title">',
				// Use actual h1 element so it inherits WordPress admin h1 styles
				'<h1><span class="wsscd-skeleton-shimmer" style="display:inline-block;width:200px;height:1em;border-radius:var(--wsscd-radius-sm);vertical-align:middle;' + this.styles.shimmer + '">&nbsp;</span></h1>',
				'</div>',
				// Use actual .button class so it inherits WordPress button styles (border removed for skeleton)
				'<a href="#" class="button wsscd-exit-wizard wsscd-skeleton-shimmer" style="pointer-events:none;border:none;color:transparent;' + this.styles.shimmer + '">',
				'<span style="visibility:hidden;">Exit Wizard</span>',
				'</a>',
				'</div>',
				'<ul class="wsscd-wizard-steps">'
			];

			// Use actual li structure with skeleton class to override ::before
			this.config.steps.forEach( function( stepName, i ) {
				var classes = 'wsscd-step--skeleton';
				if ( i === currentIndex ) { classes += ' active'; }
				if ( i < currentIndex ) { classes += ' completed'; }

				html.push(
					'<li class="' + classes + '" data-step="' + ( i + 1 ) + '" data-step-name="' + stepName + '" style="cursor:default;">',
					// Circle skeleton element (replaces ::before which is hidden via CSS)
					'<span class="wsscd-step-circle-skeleton" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--wsscd-radius-full);margin-bottom:var(--wsscd-spacing-sm);' + self.styles.shimmer + 'animation-delay:' + ( i * 0.05 ) + 's;"></span>',
					// Label shimmer inside actual span.step-label so it inherits proper styles
					'<span class="step-label"><span class="wsscd-skeleton-shimmer" style="display:inline-block;width:' + ( self.config.labelWidths[ i ] || '60px' ) + ';height:1em;border-radius:var(--wsscd-radius-sm);' + self.styles.shimmer + 'animation-delay:' + ( i * 0.05 + 0.1 ) + 's;">&nbsp;</span></span>',
					'</li>'
				);
			} );

			html.push( '</ul></div>' );
			return html.join( '' );
		},

		buildSidebar: function() {
			var self = this;
			var html = [
				'<aside class="wsscd-step-sidebar" style="flex:0 0 var(--wsscd-sidebar-width);width:var(--wsscd-sidebar-width);">',
				'<div class="wsscd-card wsscd-sidebar-panel" style="padding:var(--wsscd-spacing-lg);border-radius:var(--wsscd-radius-md);">',
				'<div class="wsscd-card__header">',
				'<h3 class="wsscd-card__title" style="' + this.styles.cardTitle + '">',
				this.icon( 20, 'display:inline-block;margin-right:var(--wsscd-spacing-sm);' ),
				this.line( '180px', '20px', 'display:inline-block;vertical-align:middle;margin-bottom:0;' ),
				'</h3>',
				'<p class="wsscd-card__subtitle" style="' + this.styles.cardSubtitle + '">' + this.line( '85%', '14px' ) + '</p>',
				'</div>',
				'<div class="wsscd-card__content" style="padding-top:var(--wsscd-spacing-sm);">'
			];

			// 3 collapsible sections
			[ 0, 1, 2 ].forEach( function( s ) {
				var isOpen = 0 === s;
				var mb = s < 2 ? 'var(--wsscd-spacing-lg)' : '0';
				html.push(
					'<div class="wsscd-sidebar-section" style="margin-bottom:' + mb + ';background:var(--wsscd-color-surface);' + self.styles.border + 'border-radius:var(--wsscd-radius-sm);">',
					'<div class="wsscd-sidebar-section-header" style="' + self.styles.flexCenter + 'padding:var(--wsscd-spacing-md) var(--wsscd-spacing-lg);background:' + ( isOpen ? 'var(--wsscd-color-surface-alt)' : 'transparent' ) + ';border-bottom:' + ( isOpen ? 'var(--wsscd-border-width) solid var(--wsscd-color-border-light)' : 'none' ) + ';border-radius:' + ( isOpen ? 'var(--wsscd-radius-sm) var(--wsscd-radius-sm) 0 0' : 'var(--wsscd-radius-sm)' ) + ';">',
					self.icon( 20, 'margin-right:var(--wsscd-spacing-md);' ),
					self.line( ( 110 + s * 20 ) + 'px', '14px', 'margin-bottom:0;' ),
					'</div>'
				);
				if ( isOpen ) {
					html.push( '<div class="wsscd-sidebar-section-content" style="padding:var(--wsscd-spacing-lg);">' );
					[ 0, 1, 2 ].forEach( function( i ) {
						html.push( '<div style="margin-bottom:' + ( i < 2 ? 'var(--wsscd-spacing-sm)' : '0' ) + ';">' + self.line( ( 85 + i * 5 ) + '%', '13px' ) + '</div>' );
					} );
					html.push( '</div>' );
				}
				html.push( '</div>' );
			} );

			html.push( '</div></div></aside>' );
			return html.join( '' );
		},

		buildNavigation: function( currentIndex ) {
			var html = [
				'<nav class="wsscd-wizard-navigation" role="navigation" style="padding:var(--wsscd-spacing-md) 0;">',
				'<div class="wsscd-nav-container">',
				'<div class="wsscd-nav-section wsscd-nav-section--left">'
			];
			if ( currentIndex > 0 ) {
				html.push( this.button( '100px', 'var(--wsscd-button-height-large)' ) );
			}
			html.push(
				'</div>',
				'<div class="wsscd-nav-section wsscd-nav-section--center"><div class="wsscd-nav-status">',
				this.line( '90px', '14px', 'display:inline-block;margin-bottom:0;' ),
				'</div></div>',
				'<div class="wsscd-nav-section wsscd-nav-section--right">',
				this.button( '100px', 'var(--wsscd-button-height-large)' ),
				'</div></div></nav>'
			);
			return html.join( '' );
		},

		buildContent: function( targetStep, contentSkeleton, sidebar, navigation ) {
			return [
				'<form method="post" class="wsscd-wizard-form" autocomplete="off">',
				'<div class="wsscd-wizard-content wsscd-wizard-layout" data-step="' + targetStep + '">',
				'<div class="wsscd-step-main-content wsscd-wizard-step--' + targetStep + '">' + contentSkeleton + '</div>',
				sidebar,
				'</div>',
				navigation,
				'</form>'
			].join( '' );
		},

		// =========================================================================
		// Step Content Router
		// =========================================================================

		getStepContent: function( stepName ) {
			var methods = {
				'basic': 'createBasicStepCards',
				'products': 'createProductsStepCards',
				'discounts': 'createDiscountsStepCards',
				'schedule': 'createScheduleStepCards',
				'review': 'createReviewStepCards'
			};
			return methods[ stepName ] ? this[ methods[ stepName ] ]() : this.createCard( 2 ) + this.createCard( 2 );
		},

		createCard: function( fields ) {
			var html = [ this.cardStart( '200px', '85%' ) ];
			for ( var i = 0; i < fields; i++ ) {
				html.push( '<div class="wsscd-skeleton-field" style="margin-bottom:' + ( i < fields - 1 ? '16px' : '0' ) + ';"></div>' );
			}
			html.push( this.cardEnd() );
			return html.join( '' );
		},

		// =========================================================================
		// Step-Specific Skeletons
		// =========================================================================

		createBasicStepCards: function() {
			// Card 1: Campaign Details
			var card1 = [
				this.cardStart( '160px', '85%' ),
				this.field( '120px', '30px' ),
				this.field( '100px', '80px', true ),
				this.cardEnd()
			].join( '' );

			// Card 2: Priority
			var card2 = [
				this.cardStart( '150px', '90%' ),
				'<div class="wsscd-skeleton-field">',
				this.line( '80px', '14px', 'margin-bottom:12px;display:block;' ),
				'<div class="wsscd-skeleton-slider" style="height:8px;border-radius:4px;margin-bottom:8px;"></div>',
				'<div style="display:flex;justify-content:space-between;">',
				this.line( '40px', '12px' ),
				this.line( '50px', '12px' ),
				this.line( '40px', '12px' ),
				'</div></div>',
				this.cardEnd()
			].join( '' );

			return card1 + card2;
		},

		createProductsStepCards: function() {
			var self = this;

			// Card 1: Categories
			var card1 = [
				this.cardStart( '150px', '75%' ),
				this.field( '90px', '38px', true ),
				this.cardEnd()
			].join( '' );

			// Card 2: Selection (4 options)
			var optionWidths = [ '100px', '130px', '130px', '120px' ];
			var descWidths = [ '280px', '240px', '220px', '260px' ];
			var options = optionWidths.map( function( w, i ) {
				return [
					'<div style="padding:16px;border-radius:6px;' + self.styles.border + '">',
					'<div style="display:flex;align-items:flex-start;gap:12px;">',
					'<div style="flex-shrink:0;margin-top:2px;">' + self.radio() + '</div>',
					'<div style="flex:1;">',
					self.line( w, '16px', 'margin-bottom:6px;display:block;' ),
					self.line( descWidths[ i ], '13px', 'max-width:90%;' ),
					'</div></div></div>'
				].join( '' );
			} ).join( '' );

			var card2 = [
				this.cardStart( '130px', '80%' ),
				'<div style="display:flex;flex-direction:column;gap:12px;">',
				options,
				'</div>',
				this.cardEnd()
			].join( '' );

			// Card 3: Filters (PRO)
			var card3 = [
				this.cardStart( '140px', '70%', '60px' ),
				'<div style="' + this.styles.flexCenter + 'gap:12px;margin-bottom:16px;">',
				this.line( '140px', '14px', 'margin-bottom:0;' ),
				'<div class="wsscd-skeleton-toggle" style="width:180px;height:30px;border-radius:6px;"></div>',
				'</div>',
				'<div style="display:flex;flex-wrap:wrap;gap:8px;padding:12px;background:#f9f9f9;border-radius:6px;">',
				this.input( '30px', 'width:90px;' ),
				this.input( '30px', 'width:150px;' ),
				this.input( '30px', 'width:130px;' ),
				this.input( '30px', 'width:100px;' ),
				'</div>',
				this.cardEnd()
			].join( '' );

			return card1 + card2 + card3;
		},

		createDiscountsStepCards: function() {
			var self = this;

			// Card 1: Type Grid (5 items)
			var typeCards = [ 0, 1, 2, 3, 4 ].map( function( i ) {
				var isPro = i >= 2;
				return [
					'<div style="padding:20px 12px;border-radius:8px;text-align:center;min-height:140px;background:var(--wsscd-color-surface-alt);">',
					'<div class="wsscd-skeleton-icon-lg" style="width:48px;height:48px;margin:0 auto 12px;border-radius:8px;"></div>',
					self.line( '75%', '16px', 'margin:0 auto 8px;display:block;' ),
					self.line( '90%', '13px', 'margin:0 auto 6px;display:block;' ),
					self.line( '65%', '12px', 'margin:0 auto;display:block;' ),
					isPro ? '<div class="wsscd-skeleton-badge" style="width:36px;height:18px;margin:10px auto 0;border-radius:9px;"></div>' : '',
					'</div>'
				].join( '' );
			} ).join( '' );

			var card1 = [
				this.cardStart( '200px', '75%' ),
				'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">',
				typeCards,
				'</div>',
				this.cardEnd()
			].join( '' );

			// Card 2: Config (3 form rows)
			var formRows = [ 0, 1, 2 ].map( function( j ) {
				return [
					'<div style="display:flex;align-items:center;padding:12px 0;">',
					self.line( '140px', '14px', 'flex-shrink:0;margin-bottom:0;' ),
					self.input( '30px', 'flex:1;margin-left:20px;' ),
					'</div>'
				].join( '' );
			} ).join( '' );

			var card2 = this.cardStart( '190px', '65%' ) + formRows + this.cardEnd();

			// Card 3: Badge
			var card3 = [
				this.cardStart( '120px', '70%' ),
				'<div style="' + this.styles.flexCenter + 'gap:12px;margin-bottom:20px;">',
				'<div class="wsscd-skeleton-toggle-switch" style="width:44px;height:24px;border-radius:12px;"></div>',
				this.line( '120px', '14px' ),
				'</div>',
				'<div class="wsscd-skeleton-preview" style="height:80px;border-radius:8px;background:var(--wsscd-color-surface-alt);"></div>',
				this.cardEnd()
			].join( '' );

			// Card 4: Rules (PRO)
			var sections = [ '130px', '150px', '160px' ].map( function( w, k ) {
				return [
					'<div style="' + self.styles.border + 'border-radius:4px;margin-bottom:' + ( k < 2 ? '12px' : '0' ) + ';">',
					'<div style="' + self.styles.flexCenter + 'padding:14px 16px;">',
					self.icon( 16, 'margin-right:10px;' ),
					self.line( w, '14px' ),
					'<div class="wsscd-skeleton-chevron" style="width:16px;height:16px;margin-left:auto;"></div>',
					'</div></div>'
				].join( '' );
			} ).join( '' );

			var card4 = this.cardStart( '180px', '60%', '35px' ) + sections + this.cardEnd();

			return card1 + card2 + card3 + card4;
		},

		createScheduleStepCards: function() {
			var self = this;

			// Card 1: Presets (6 items)
			var presets = [ 0, 1, 2, 3, 4, 5 ].map( function() {
				return [
					'<div style="padding:16px;border-radius:6px;' + self.styles.border + 'text-align:center;min-height:60px;">',
					self.line( '70%', '15px', 'margin:0 auto 8px;display:block;' ),
					self.line( '50%', '12px', 'margin:0 auto;display:block;' ),
					'</div>'
				].join( '' );
			} ).join( '' );

			var card1 = [
				this.cardStart( '170px', '75%' ),
				'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">',
				presets,
				'</div>',
				this.cardEnd()
			].join( '' );

			// Card 2: Configuration
			var startTypes = [ 0, 1 ].map( function() {
				return [
					'<div style="flex:1;padding:14px;border-radius:6px;' + self.styles.border + '">',
					'<div style="' + self.styles.flexCenter + 'gap:10px;">',
					self.radio(),
					self.line( '100px', '14px', 'margin-bottom:0;' ),
					'</div></div>'
				].join( '' );
			} ).join( '' );

			var card2 = [
				this.cardStart( '190px', '70%' ),
				'<div style="display:flex;gap:12px;margin-bottom:20px;">' + startTypes + '</div>',
				'<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">',
				this.field( '80px', '30px', true ),
				this.field( '70px', '30px', true ),
				'</div>',
				'<div style="margin-top:16px;padding:12px;background:#f9f9f9;border-radius:6px;">',
				this.line( '200px', '14px', 'margin:0 auto;display:block;' ),
				'</div>',
				this.cardEnd()
			].join( '' );

			// Card 3: Recurring
			var card3 = [
				this.cardStart( '150px', '80%' ),
				'<div style="' + this.styles.flexCenter + 'gap:12px;margin-bottom:20px;">',
				'<div class="wsscd-skeleton-toggle-switch" style="width:44px;height:24px;border-radius:12px;"></div>',
				this.line( '140px', '14px' ),
				'</div>',
				'<div style="opacity:0.5;">',
				this.line( '120px', '14px', 'margin-bottom:12px;display:block;' ),
				'<div style="display:flex;gap:10px;">',
				'<div class="wsscd-skeleton-pill" style="width:60px;height:32px;border-radius:16px;"></div>',
				'<div class="wsscd-skeleton-pill" style="width:70px;height:32px;border-radius:16px;"></div>',
				'<div class="wsscd-skeleton-pill" style="width:80px;height:32px;border-radius:16px;"></div>',
				'</div></div>',
				this.cardEnd()
			].join( '' );

			return card1 + card2 + card3;
		},

		createReviewStepCards: function() {
			var self = this;

			// Loading state (matches #wsscd-health-loading)
			var loading = [
				'<div style="text-align:center;padding:40px 20px;margin-bottom:20px;">',
				'<div class="wsscd-skeleton-spinner" style="width:40px;height:40px;border-radius:50%;margin:0 auto 16px;"></div>',
				this.line( '220px', '14px', 'margin:0 auto;display:block;' ),
				'</div>'
			].join( '' );

			// Health Score card (headerless - matches .wsscd-health-score-card)
			var healthScore = [
				'<div class="wsscd-card wsscd-wizard-card" style="' + this.styles.card + '">',
				'<div class="wsscd-card__content">',
				'<div style="padding:24px;margin-bottom:0;border-radius:8px;' + this.styles.border + this.styles.flexBetween + '">',
				'<div>',
				this.line( '180px', '20px', 'margin-bottom:8px;display:block;' ),
				this.line( '140px', '14px' ),
				'</div>',
				'<div class="wsscd-skeleton-score" style="width:80px;height:50px;border-radius:8px;"></div>',
				'</div></div></div>'
			].join( '' );

			// Health Factors card (Critical Issues / recommendations placeholder)
			var healthFactors = this.cardStart( '160px', '75%' ) +
				'<div style="margin-bottom:var(--wsscd-spacing-md);">' + self.line( '90%', '14px', 'display:block;' ) + '</div>' +
				'<div style="margin-bottom:var(--wsscd-spacing-md);">' + self.line( '85%', '14px', 'display:block;' ) + '</div>' +
				'<div>' + self.line( '70%', '14px', 'display:block;' ) + '</div>' +
				this.cardEnd();

			// Recommendations card
			var recommendations = this.cardStart( '150px', '70%' ) +
				'<div style="display:flex;flex-direction:column;gap:var(--wsscd-spacing-sm);">' +
				[ 0, 1, 2 ].map( function() {
					return '<div style="' + self.styles.flexCenter + 'gap:var(--wsscd-spacing-md);">' +
						self.icon( 20, 'flex-shrink:0;' ) +
						self.line( '75%', '14px', 'margin-bottom:0;' ) +
						'</div>';
				} ).join( '' ) +
				'</div>' +
				this.cardEnd();

			// Campaign Conflicts card
			var conflicts = this.cardStart( '160px', '65%' ) +
				'<div style="display:flex;flex-direction:column;gap:var(--wsscd-spacing-sm);">' +
				[ 0, 1 ].map( function() {
					return '<div style="' + self.styles.flexCenter + 'gap:var(--wsscd-spacing-md);">' +
						self.icon( 16, 'flex-shrink:0;' ) +
						self.line( '80%', '13px', 'margin-bottom:0;' ) +
						'</div>';
				} ).join( '' ) +
				'</div>' +
				this.cardEnd();

			// Impact (3 metrics - matches .wsscd-impact-grid)
			var metrics = [ 0, 1, 2 ].map( function() {
				return [
					'<div style="padding:16px;border-radius:6px;' + self.styles.border + 'text-align:center;">',
					self.line( '80%', '12px', 'margin:0 auto 10px;display:block;' ),
					'<div class="wsscd-skeleton-value" style="width:50px;height:28px;margin:0 auto;border-radius:4px;"></div>',
					'</div>'
				].join( '' );
			} ).join( '' );

			var impact = [
				this.cardStart( '200px', '70%' ),
				'<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">',
				metrics,
				'</div>',
				this.cardEnd()
			].join( '' );

			// Configuration Summary card (grid of summary items)
			var summaryItems = [ 0, 1, 2, 3, 4 ].map( function() {
				return '<div style="display:flex;justify-content:space-between;align-items:center;padding:var(--wsscd-spacing-sm) 0;">' +
					self.line( '100px', '14px', 'margin-bottom:0;' ) +
					self.line( '60px', '14px', 'margin-bottom:0;' ) +
					'</div>';
			} ).join( '' );
			var configSummary = this.cardStart( '180px', '60%' ) +
				'<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--wsscd-spacing-md);">' +
				summaryItems +
				'</div>' +
				this.cardEnd();

			// Launch Options (2 options - matches .wsscd-launch-container)
			var options = [ 0, 1 ].map( function() {
				return [
					'<div style="flex:1;padding:20px;border-radius:8px;' + self.styles.border + '">',
					'<div style="display:flex;align-items:flex-start;gap:12px;">',
					'<div style="flex-shrink:0;margin-top:2px;">' + self.radio( 20 ) + '</div>',
					'<div style="flex:1;">',
					self.icon( 24, 'margin-bottom:10px;display:block;' ),
					self.line( '120px', '16px', 'margin-bottom:8px;display:block;' ),
					self.line( '90%', '13px' ),
					'</div></div></div>'
				].join( '' );
			} ).join( '' );

			var launch = [
				this.cardStart( '180px', '60%' ),
				'<div style="display:flex;gap:16px;">' + options + '</div>',
				'<div style="margin-top:16px;padding:12px;background:var(--wsscd-color-surface-alt);border-radius:4px;' + this.styles.flexCenter + 'gap:10px;">',
				this.icon( 16, 'flex-shrink:0;' ),
				this.line( '80%', '13px' ),
				'</div>',
				this.cardEnd()
			].join( '' );

			return loading + healthScore + healthFactors + recommendations + conflicts + impact + configSummary + launch;
		}
	};

	$( document ).ready( function() {
		$( document ).trigger( 'wsscd:service:ready', [ 'SkeletonTemplates' ] );
	} );

} )( jQuery );
