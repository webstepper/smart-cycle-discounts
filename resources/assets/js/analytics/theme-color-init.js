/**
 * Theme Color Initialization for Analytics
 *
 * Ensures analytics components use WordPress admin theme colors
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize theme colors for analytics components
	 */
	function initializeAnalyticsThemeColors() {
		// Wait for theme color service
		if ( !window.SCD || !window.SCD.Shared || !window.SCD.Shared.ThemeColorService ) {
			setTimeout( initializeAnalyticsThemeColors, 100 );
			return;
		}

		// Apply analytics-specific color mappings
		var analyticsMapping = {
			'--scd-chart-primary': 'var(--scd-color-primary)',
			'--scd-chart-secondary': 'var(--scd-color-secondary)',
			'--scd-chart-success': 'var(--scd-color-success)',
			'--scd-chart-warning': 'var(--scd-color-warning)',
			'--scd-chart-danger': 'var(--scd-color-danger)',
			'--scd-chart-accent': 'var(--scd-color-accent)',
			'--scd-chart-grid': 'var(--scd-color-border-light)',
			'--scd-chart-text': 'var(--scd-color-text)',
			'--scd-chart-background': 'var(--scd-color-background)',
			'--scd-timeline-active': 'var(--scd-color-primary)',
			'--scd-timeline-future': 'var(--scd-color-secondary)',
			'--scd-timeline-past': 'var(--scd-color-text-muted)',
			'--scd-timeline-current': 'var(--scd-color-danger)',
			'--scd-timeline-grid': 'var(--scd-color-border-light)'
		};

		window.SCD.Shared.ThemeColorService.applyToComponent( 'analytics', analyticsMapping );

		// Subscribe to theme changes
		window.SCD.Shared.ThemeColorService.subscribe( function( colors ) {
			updateChartColors( colors );
			updateTimelineColors( colors );
		}, 'analytics' );
	}

	/**
	 * Update Chart.js instances with new colors
	 * @param colors
	 */
	function updateChartColors( colors ) {
		// If Chart.js is loaded and there are chart instances
		if ( 'undefined' !== typeof Chart && Chart.instances ) {
			// Chart.js v4 - instances is an object, not an array
			var instances = Object.values( Chart.instances || {} );
			instances.forEach( function( chart ) {
				if ( !chart || !chart.config ) {return;}

				// Update dataset colors based on chart type
				if ( 'line' === chart.config.type ) {
					if ( chart.data.datasets[0] ) {
						chart.data.datasets[0].borderColor = colors.primary;
						chart.data.datasets[0].backgroundColor = colors.primary + '1A'; // 10% opacity
					}
				} else if ( 'bar' === chart.config.type ) {
					if ( chart.data.datasets[0] ) {
						chart.data.datasets[0].backgroundColor = [
							colors.primary + 'CC',    // 80% opacity
							colors.danger + 'CC',
							colors.warning + 'CC',
							colors.success + 'CC',
							colors.accent + 'CC'
						];
						chart.data.datasets[0].borderColor = [
							colors.primary,
							colors.danger,
							colors.warning,
							colors.success,
							colors.accent
						];
					}
				} else if ( 'doughnut' === chart.config.type ) {
					if ( chart.data.datasets[0] ) {
						chart.data.datasets[0].backgroundColor = [
							colors.primary + 'CC',
							colors.warning + 'CC',
							colors.success + 'CC'
						];
						chart.data.datasets[0].borderColor = [
							colors.primary,
							colors.warning,
							colors.success
						];
					}
				}

				// Update grid colors
				if ( chart.options && chart.options.scales ) {
					if ( chart.options.scales.x && chart.options.scales.x.grid ) {
						chart.options.scales.x.grid.color = colors.border_light + '4D';
					}
					if ( chart.options.scales.y && chart.options.scales.y.grid ) {
						chart.options.scales.y.grid.color = colors.border_light + '4D';
					}
				}

				// Force chart update
				chart.update();
			} );
		}
	}

	/**
	 * Update timeline visualizer colors
	 * @param colors
	 */
	function updateTimelineColors( colors ) {
		// Update any active timeline instances
		$( '.scd-timeline-visualizer' ).each( function() {
			var instance = $( this ).data( 'scdTimeline' );
			if ( instance && instance.options ) {
				// Update colors
				instance.options.colors = {
					background: colors.background || '#f0f0f1',
					timeline: colors.border || '#dcdcde',
					active: colors.primary || '#0073aa',
					future: colors.secondary || '#72aee6',
					past: colors.text_muted || '#646970',
					current: colors.danger || '#d63638',
					interactive: colors.primary || '#2271b1',
					text: colors.text || '#1e1e1e',
					grid: colors.border_light || '#e0e0e0'
				};

				// Re-render
				if ( instance.render ) {
					instance.render();
				}
			}
		} );
	}

	// Initialize when DOM is ready
	$( document ).ready( function() {
		initializeAnalyticsThemeColors();
	} );

} )( jQuery );