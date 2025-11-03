/**
 * Theme Color Init
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/theme-color-init.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

// IMMEDIATE EXECUTION: Apply colors before DOM ready to prevent flash
( function() {
	'use strict';

	// Apply colors immediately if available
	if ( window.scdAdmin && window.scdAdmin.colors ) {
		var root = document.documentElement;
		var colors = window.scdAdmin.colors;

		// Apply critical colors immediately to prevent flash
		Object.keys( colors ).forEach( function( key ) {
			root.style.setProperty( '--scd-color-' + key, colors[key] );
		} );

		// Apply component-specific critical colors
		root.style.setProperty( '--scd-wizard-primary', colors.primary || '#2271b1' );
		root.style.setProperty( '--scd-wizard-secondary', colors.secondary || '#72aee6' );
		root.style.setProperty( '--scd-wizard-success', colors.success || '#00a32a' );
		root.style.setProperty( '--scd-wizard-warning', colors.warning || '#dba617' );
		root.style.setProperty( '--scd-wizard-danger', colors.danger || '#d63638' );
		root.style.setProperty( '--scd-wizard-accent', colors.accent || '#3858e9' );

		// Mark as applied
		root.setAttribute( 'data-scd-early-colors', 'true' );
	}
} )();

// jQuery initialization after DOM ready
( function( $ ) {
	'use strict';

	// Wait for DOM ready
	$( document ).ready( function() {

		// Initialize theme color service
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.ThemeColorService ) {
			window.SCD.Shared.ThemeColorService.init();

			// Apply component-specific colors
			applyComponentColors();

			// Apply chart colors if analytics is present
			applyChartColors();

			// Apply notification colors
			applyNotificationColors();

			// Apply form element colors
			applyFormColors();

			// Apply button colors
			applyButtonColors();

			// Apply badge colors
			applyBadgeColors();
		}

	} );

	/**
	 * Apply colors to various components
	 */
	function applyComponentColors() {
		var ThemeColorService = window.SCD.Shared.ThemeColorService;

		// Wizard progress (already handled in wizard.js)

		// Data tables
		ThemeColorService.applyToComponent( 'table', {
			'hover-bg': 'primary',
			'selected-bg': 'primary',
			'sort-active': 'primary',
			'action-link': 'primary'
		} );

		// Loading states
		ThemeColorService.applyToComponent( 'loader', {
			'spinner': 'primary',
			'track': 'light',
			'progress': 'primary'
		} );
	}

	/**
	 * Apply colors to Chart.js instances
	 */
	function applyChartColors() {
		if ( !window.Chart ) {return;}

		var ThemeColorService = window.SCD.Shared.ThemeColorService;
		var colors = ThemeColorService.getAllColors();

		// Default chart colors based on theme
		Chart.defaults.color = colors.dark;
		Chart.defaults.borderColor = colors.light;

		// Subscribe to color changes
		ThemeColorService.subscribe( function( newColors ) {
			updateChartColors( newColors );
		}, 'charts' );

		// Initial application
		updateChartColors( colors );
	}

	/**
	 * Update chart colors
	 * @param colors
	 */
	function updateChartColors( colors ) {
		// Update Chart.js defaults
		if ( window.Chart ) {
			Chart.defaults.color = colors.dark;
			Chart.defaults.borderColor = colors.light;

			// Update all active chart instances
			Object.keys( Chart.instances ).forEach( function( chartId ) {
				var chart = Chart.instances[chartId];
				if ( chart && chart.data && chart.data.datasets ) {
					updateChartDatasetColors( chart, colors );
					chart.update();
				}
			} );
		}

		// Update analytics dashboard colors if present
		if ( window.scdAnalytics ) {
			window.scdAnalytics.chart_config = window.scdAnalytics.chart_config || {};
			window.scdAnalytics.chart_config.colors = {
				primary: colors.primary,
				secondary: colors.secondary,
				success: colors.success,
				warning: colors.warning,
				danger: colors.danger,
				series: [
					colors.primary,
					colors.accent,
					colors.secondary,
					colors.success,
					colors.warning
				]
			};
		}
	}

	/**
	 * Update dataset colors for a chart
	 * @param chart
	 * @param colors
	 */
	function updateChartDatasetColors( chart, colors ) {
		var colorArray = [
			colors.primary,
			colors.accent,
			colors.secondary,
			colors.success,
			colors.warning,
			colors.danger
		];

		chart.data.datasets.forEach( function( dataset, index ) {
			var color = colorArray[index % colorArray.length];

			// Line/area charts
			if ( 'line' === chart.config.type || 'area' === chart.config.type ) {
				dataset.borderColor = color;
				dataset.backgroundColor = hexToRgba( color, 0.1 );
				dataset.pointBackgroundColor = color;
				dataset.pointBorderColor = color;
			}

			// Bar charts
			else if ( 'bar' === chart.config.type ) {
				dataset.backgroundColor = color;
				dataset.borderColor = color;
			}

			// Pie/doughnut charts
			else if ( 'pie' === chart.config.type || 'doughnut' === chart.config.type ) {
				dataset.backgroundColor = colorArray;
				dataset.borderColor = colors.white;
			}
		} );
	}

	/**
	 * Apply colors to notifications
	 */
	function applyNotificationColors() {
		var ThemeColorService = window.SCD.Shared.ThemeColorService;

		ThemeColorService.applyToComponent( 'notice', {
			'info-bg': 'info',
			'info-border': 'info',
			'success-bg': 'success',
			'success-border': 'success',
			'warning-bg': 'warning',
			'warning-border': 'warning',
			'error-bg': 'danger',
			'error-border': 'danger'
		} );
	}

	/**
	 * Apply colors to form elements
	 */
	function applyFormColors() {
		var ThemeColorService = window.SCD.Shared.ThemeColorService;

		ThemeColorService.applyToComponent( 'form', {
			'focus-border': 'primary',
			'focus-shadow': 'primary',
			'checked-bg': 'primary',
			'checked-border': 'primary',
			'toggle-on': 'success',
			'error-border': 'danger',
			'error-text': 'danger',
			'success-border': 'success',
			'success-text': 'success'
		} );
	}

	/**
	 * Apply colors to buttons
	 */
	function applyButtonColors() {
		// Already handled by CSS variables in _theme-colors.css
		// but we can add dynamic classes if needed
	}

	/**
	 * Apply colors to badges
	 */
	function applyBadgeColors() {
		var ThemeColorService = window.SCD.Shared.ThemeColorService;

		ThemeColorService.applyToComponent( 'badge', {
			'active': 'success',
			'inactive': 'muted',
			'scheduled': 'info',
			'expired': 'danger',
			'draft': 'warning'
		} );
	}

	/**
	 * Convert hex to rgba
	 * @param hex
	 * @param alpha
	 */
	function hexToRgba( hex, alpha ) {
		var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec( hex );
		return result ?
			'rgba(' + parseInt( result[1], 16 ) + ', ' +
            parseInt( result[2], 16 ) + ', ' +
            parseInt( result[3], 16 ) + ', ' + alpha + ')' : hex;
	}

} )( jQuery );