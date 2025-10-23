/**
 * Summary Module
 *
 * Displays campaign configuration summary
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Review = SCD.Modules.Review || {};

	/**
	 * Summary Display Handler
	 *
	 * @param state
	 * @param api
	 * @class SCD.Modules.Review.Summary
	 */
	SCD.Modules.Review.Summary = function( state, api ) {
		this.state = state;
		this.api = api;
		this.elements = {};
	};

	/**
	 * Initialize module
	 */
	SCD.Modules.Review.Summary.prototype.init = function() {
		this.cacheElements();
		this.bindEvents();
		this.loadSummaryData();
	};

	/**
	 * Cache DOM elements
	 */
	SCD.Modules.Review.Summary.prototype.cacheElements = function() {
		this.elements = {
			$summaryContainer: $( '.scd-review-summary' ),
			$basicSummary: $( '.scd-summary-basic' ),
			$productsSummary: $( '.scd-summary-products' ),
			$discountsSummary: $( '.scd-summary-discounts' ),
			$scheduleSummary: $( '.scd-summary-schedule' ),
			$editButtons: $( '.scd-summary-edit' ),
			$expandButtons: $( '.scd-summary-expand' )
		};
	};

	/**
	 * Bind event handlers
	 */
	SCD.Modules.Review.Summary.prototype.bindEvents = function() {
		var self = this;

		// Edit button clicks
		this.elements.$editButtons.on( 'click', function( e ) {
			e.preventDefault();
			var step = $( this ).data( 'step' );
			self.navigateToStep( step );
		} );

		// Expand/collapse sections
		this.elements.$expandButtons.on( 'click', function( e ) {
			e.preventDefault();
			var $section = $( this ).closest( '.scd-summary-section' );
			$section.toggleClass( 'expanded' );

			var isExpanded = $section.hasClass( 'expanded' );
			$( this ).find( '.dashicons' )
				.removeClass( isExpanded ? 'dashicons-arrow-down' : 'dashicons-arrow-up' )
				.addClass( isExpanded ? 'dashicons-arrow-up' : 'dashicons-arrow-down' );
		} );

		// Listen to state changes
		$( document ).on( 'scd:review:state:changed', function( e, data ) {
			if ( 'summaryData' === data.property ) {
				self.updateAllSummaries();
			}
		} );
	};

	/**
	 * Load summary data
	 */
	SCD.Modules.Review.Summary.prototype.loadSummaryData = function() {
		// Get wizard data
		var wizardData = ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.data ) || {};

		// Update state with wizard data
		this.state.loadWizardData( wizardData );

		// Update summary displays
		this.updateAllSummaries();
	};

	/**
	 * Update all summary sections
	 */
	SCD.Modules.Review.Summary.prototype.updateAllSummaries = function() {
		var state = this.state.getState();
		var data = state.summaryData;

		this.updateBasicSummary( data.basic );
		this.updateProductsSummary( data.products );
		this.updateDiscountsSummary( data.discounts );
		this.updateScheduleSummary( data.schedule );
	};

	/**
	 * Update basic information summary
	 * @param data
	 */
	SCD.Modules.Review.Summary.prototype.updateBasicSummary = function( data ) {
		if ( !this.elements.$basicSummary.length || !data ) {return;}

		var html = '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Campaign Name:</span>' +
            '<span class="scd-summary-value">' + SCD.utils.escapeHtml( data.name || 'Untitled Campaign' ) + '</span>' +
            '</div>' +
            '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Priority:</span>' +
            '<span class="scd-summary-value">' + ( data.priority || 5 ) + ' / 10</span>' +
            '</div>';

		if ( data.description ) {
			html += '<div class="scd-summary-item">' +
                '<span class="scd-summary-label">Description:</span>' +
                '<span class="scd-summary-value">' + SCD.utils.escapeHtml( data.description ) + '</span>' +
                '</div>';
		}

		this.elements.$basicSummary.html( html );
	};

	/**
	 * Update products summary
	 * @param data
	 */
	SCD.Modules.Review.Summary.prototype.updateProductsSummary = function( data ) {
		if ( !this.elements.$productsSummary.length || !data ) {return;}

		var productsText = 'No products selected';

		// AJAX response automatically converts to camelCase
		var selectionType = data.productSelectionType || data.selectionType;
		var categoryIds = data.categoryIds;
		var productIds = data.productIds;
		var randomCount = data.randomCount;

		if ( 'all_products' === selectionType || 'all' === selectionType ) {
			productsText = 'All Products';
			if ( categoryIds && 0 < categoryIds.length && -1 === categoryIds.indexOf( 'all' ) ) {
				productsText += ' in ' + categoryIds.length + ' categories';
			}
		} else if ( 'random_products' === selectionType || 'random' === selectionType ) {
			productsText = ( randomCount || 0 ) + ' Random Products';
			if ( categoryIds && 0 < categoryIds.length && -1 === categoryIds.indexOf( 'all' ) ) {
				productsText += ' from ' + categoryIds.length + ' categories';
			}
		} else if ( 'specific_products' === selectionType || 'specific' === selectionType ) {
			var count = productIds ? productIds.length : 0;
			productsText = count + ' Specific Product' + ( 1 !== count ? 's' : '' );
		}

		var html = '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Selection Type:</span>' +
            '<span class="scd-summary-value">' + this.formatSelectionType( selectionType ) + '</span>' +
            '</div>' +
            '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Products:</span>' +
            '<span class="scd-summary-value">' + productsText + '</span>' +
            '</div>';

		this.elements.$productsSummary.html( html );
	};

	/**
	 * Update discounts summary
	 * @param data
	 */
	SCD.Modules.Review.Summary.prototype.updateDiscountsSummary = function( data ) {
		if ( !this.elements.$discountsSummary.length || !data ) {return;}

		// AJAX response automatically converts to camelCase
		var discountType = data.discountType;
		var buyQuantity = data.buyQuantity;
		var getQuantity = data.getQuantity;

		var discountTypeFormatted = this.formatDiscountType( discountType );
		var discountValue = 'Not configured';

		if ( 'percentage' === discountType ) {
			discountValue = ( data.percentage || 0 ) + '% off';
		} else if ( 'fixed_amount' === discountType ) {
			discountValue = '$' + ( data.amount || 0 ) + ' off';
		} else if ( 'buy_x_get_y' === discountType ) {
			discountValue = 'Buy ' + ( buyQuantity || 0 ) + ' Get ' + ( getQuantity || 0 );
		} else if ( 'tiered' === discountType ) {
			var tierCount = data.tiers ? data.tiers.length : 0;
			discountValue = tierCount + ' tier' + ( 1 !== tierCount ? 's' : '' ) + ' configured';
		}

		var html = '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Discount Type:</span>' +
            '<span class="scd-summary-value">' + discountTypeFormatted + '</span>' +
            '</div>' +
            '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Discount Value:</span>' +
            '<span class="scd-summary-value">' + discountValue + '</span>' +
            '</div>';

		this.elements.$discountsSummary.html( html );
	};

	/**
	 * Update schedule summary
	 * @param data
	 */
	SCD.Modules.Review.Summary.prototype.updateScheduleSummary = function( data ) {
		if ( !this.elements.$scheduleSummary.length || !data ) {return;}

		// AJAX response automatically converts to camelCase
		var startDate = data.startDate;
		var endDate = data.endDate;
		var recurringEnabled = data.recurringEnabled;
		var recurringPattern = data.recurringPattern;

		var scheduleText = 'No schedule configured';

		if ( startDate || endDate ) {
			var start = startDate ? new Date( startDate ).toLocaleDateString() : 'Immediately';
			var end = endDate ? new Date( endDate ).toLocaleDateString() : 'No end date';
			scheduleText = start + ' - ' + end;
		}

		var html = '<div class="scd-summary-item">' +
            '<span class="scd-summary-label">Schedule:</span>' +
            '<span class="scd-summary-value">' + scheduleText + '</span>' +
            '</div>';

		if ( recurringEnabled ) {
			html += '<div class="scd-summary-item">' +
                '<span class="scd-summary-label">Recurring:</span>' +
                '<span class="scd-summary-value">' + this.formatRecurringPattern( recurringPattern ) + '</span>' +
                '</div>';
		}

		this.elements.$scheduleSummary.html( html );
	};

	/**
	 * Navigate to a specific step for editing
	 * @param step
	 */
	SCD.Modules.Review.Summary.prototype.navigateToStep = function( step ) {
		if ( window.SCD && window.SCD.Wizard && 'function' === typeof window.SCD.Wizard.navigateToStep ) {
			window.SCD.Wizard.navigateToStep( step );
		} else {
			// Fallback: Use URL navigation
			var currentUrl = new URL( window.location.href );
			currentUrl.searchParams['step'] = step;
			window.location.href = currentUrl.toString();
		}
	};

	/**
	 * Format selection type for display
	 * @param type
	 */
	SCD.Modules.Review.Summary.prototype.formatSelectionType = function( type ) {
		var types = {
			'all_products': 'All Products',
			'all': 'All Products',
			'random_products': 'Random Products',
			'random': 'Random Products',
			'specific_products': 'Specific Products',
			'specific': 'Specific Products'
		};
		return types[type] || type;
	};

	/**
	 * Format discount type for display
	 * @param type
	 */
	SCD.Modules.Review.Summary.prototype.formatDiscountType = function( type ) {
		var types = {
			'percentage': 'Percentage Discount',
			'fixed_amount': 'Fixed Amount',
			'buy_x_get_y': 'Buy X Get Y',
			'tiered': 'Tiered Discount'
		};
		return types[type] || type;
	};

	/**
	 * Format recurring pattern for display
	 * @param pattern
	 */
	SCD.Modules.Review.Summary.prototype.formatRecurringPattern = function( pattern ) {
		var patterns = {
			'daily': 'Daily',
			'weekly': 'Weekly',
			'monthly': 'Monthly',
			'custom': 'Custom Pattern'
		};
		return patterns[pattern] || pattern;
	};

	/**
	 * Update summary with new data
	 * @param wizardData
	 */
	SCD.Modules.Review.Summary.prototype.updateSummary = function( wizardData ) {
		if ( wizardData ) {
			this.state.loadWizardData( wizardData );
		}
	};

	/**
	 * Destroy module
	 */
	SCD.Modules.Review.Summary.prototype.destroy = function() {
		this.elements.$editButtons.off( 'click' );
		this.elements.$expandButtons.off( 'click' );
		$( document ).off( 'scd:review:state:changed' );
	};

} )( jQuery );