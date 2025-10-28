<?php
/**
 * Theme Color Inline Styles
 *
 * Outputs inline CSS with theme colors to prevent flash of unstyled content
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Theme Color Inline Styles Class
 *
 * @since      1.0.0
 */
class SCD_Theme_Color_Inline_Styles {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		// Hook into admin head with high priority to output early
		add_action( 'admin_head', array( $this, 'print_theme_color_styles' ), 1 );

		// Also hook into SCD's inline style action
		add_action( 'scd_print_inline_style', array( $this, 'add_theme_colors_to_inline' ), 10, 2 );
	}

	/**
	 * Print theme color styles in admin head.
	 *
	 * @since 1.0.0
	 */
	public function print_theme_color_styles(): void {
		// Get theme colors directly
		$colors = $this->get_theme_colors();

		if ( empty( $colors ) ) {
			return;
		}

		// Generate inline CSS
		$css = $this->generate_theme_color_css( $colors );

		if ( ! empty( $css ) ) {
			echo '<style id="scd-theme-colors-early">' . "\n";
			echo $css;
			echo '</style>' . "\n";
		}
	}

	/**
	 * Generate theme color CSS.
	 *
	 * @since 1.0.0
	 * @param array $colors Theme colors.
	 * @return string Generated CSS.
	 */
	private function generate_theme_color_css( array $colors ): string {
		$css = ':root {' . "\n";

		// Base colors
		foreach ( $colors as $key => $value ) {
			$css .= sprintf( '    --scd-color-%s: %s;' . "\n", $key, $value );
		}

		// Component-specific colors
		$component_mappings = $this->get_component_mappings( $colors );
		foreach ( $component_mappings as $property => $value ) {
			$css .= sprintf( '    %s: %s;' . "\n", $property, $value );
		}

		$css .= '}' . "\n";

		// Add status badge styles
		$css .= "\n" . '/* Status Badge Styles */' . "\n";
		$css .= '.scd-status-badge {' . "\n";
		$css .= '    display: inline-block;' . "\n";
		$css .= '    padding: 2px 8px;' . "\n";
		$css .= '    font-size: 11px;' . "\n";
		$css .= '    line-height: 1.4;' . "\n";
		$css .= '    font-weight: 600;' . "\n";
		$css .= '    text-transform: uppercase;' . "\n";
		$css .= '    letter-spacing: 0.02em;' . "\n";
		$css .= '    border-radius: 3px;' . "\n";
		$css .= '    background-color: var(--scd-badge-inactive);' . "\n";
		$css .= '    color: #fff;' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-status-badge--active {' . "\n";
		$css .= '    background-color: var(--scd-badge-active);' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-status-badge--scheduled {' . "\n";
		$css .= '    background-color: var(--scd-badge-scheduled);' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-status-badge--paused,' . "\n";
		$css .= '.scd-status-badge--inactive {' . "\n";
		$css .= '    background-color: var(--scd-badge-inactive);' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-status-badge--expired {' . "\n";
		$css .= '    background-color: var(--scd-badge-expired);' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-status-badge--draft {' . "\n";
		$css .= '    background-color: var(--scd-badge-draft);' . "\n";
		$css .= '}' . "\n";

		// Add draft notice styles
		$css .= "\n" . '/* Draft Notice Styles */' . "\n";
		$css .= '.scd-draft-notice {' . "\n";
		$css .= '    display: flex;' . "\n";
		$css .= '    align-items: center;' . "\n";
		$css .= '    padding: 12px;' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-draft-notice p {' . "\n";
		$css .= '    margin: 0;' . "\n";
		$css .= '    display: flex;' . "\n";
		$css .= '    align-items: center;' . "\n";
		$css .= '    width: 100%;' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-draft-meta {' . "\n";
		$css .= '    color: #646970;' . "\n";
		$css .= '    font-size: 12px;' . "\n";
		$css .= '    margin-left: 8px;' . "\n";
		$css .= '}' . "\n";

		$css .= '.scd-draft-actions {' . "\n";
		$css .= '    margin-left: auto;' . "\n";
		$css .= '    display: flex;' . "\n";
		$css .= '    gap: 12px;' . "\n";
		$css .= '    align-items: center;' . "\n";
		$css .= '}' . "\n";

		$css .= '.button-secondary .dashicons {' . "\n";
		$css .= '    margin-right: 4px;' . "\n";
		$css .= '    vertical-align: middle;' . "\n";
		$css .= '}' . "\n";

		// Add transition prevention during initial load
		$css .= "\n" . '/* Prevent color transitions during initial load */' . "\n";
		$css .= 'body:not(.scd-colors-loaded) * {' . "\n";
		$css .= '    transition-duration: 0s !important;' . "\n";
		$css .= '}' . "\n";

		// Add JavaScript to mark colors as loaded
		$css .= '</style>' . "\n";
		$css .= '<script>' . "\n";
		$css .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
		$css .= '    setTimeout(function() {' . "\n";
		$css .= '        document.body.classList.add("scd-colors-loaded");' . "\n";
		$css .= '    }, 100);' . "\n";
		$css .= '});' . "\n";
		$css .= '</script>' . "\n";
		$css .= '<style>' . "\n";

		return $css;
	}

	/**
	 * Get component color mappings.
	 *
	 * @since 1.0.0
	 * @param array $colors Base colors.
	 * @return array Component mappings.
	 */
	private function get_component_mappings( array $colors ): array {
		$mappings = array();

		// Wizard colors
		$mappings['--scd-wizard-primary']   = $colors['primary'] ?? '#2271b1';
		$mappings['--scd-wizard-secondary'] = $colors['secondary'] ?? '#72aee6';
		$mappings['--scd-wizard-success']   = $colors['success'] ?? '#00a32a';
		$mappings['--scd-wizard-warning']   = $colors['warning'] ?? '#dba617';
		$mappings['--scd-wizard-danger']    = $colors['danger'] ?? '#d63638';
		$mappings['--scd-wizard-accent']    = $colors['accent'] ?? '#3858e9';

		// Form colors
		$mappings['--scd-form-focus']          = $colors['primary'] ?? '#2271b1';
		$mappings['--scd-form-error']          = $colors['danger'] ?? '#d63638';
		$mappings['--scd-form-success']        = $colors['success'] ?? '#00a32a';
		$mappings['--scd-form-checked-bg']     = $colors['primary'] ?? '#2271b1';
		$mappings['--scd-form-checked-border'] = $colors['primary'] ?? '#2271b1';
		$mappings['--scd-form-toggle-on']      = $colors['success'] ?? '#00a32a';
		$mappings['--scd-form-focus-shadow']   = $this->hex_to_rgba( $colors['primary'] ?? '#2271b1', 0.25 );

		// Button colors
		$mappings['--scd-button-primary']       = $colors['primary'] ?? '#2271b1';
		$mappings['--scd-button-primary-hover'] = $colors['primary_dark'] ?? '#135e96';
		$mappings['--scd-button-success']       = $colors['success'] ?? '#00a32a';
		$mappings['--scd-button-success-hover'] = $colors['success_dark'] ?? '#008a20';
		$mappings['--scd-button-danger']        = $colors['danger'] ?? '#d63638';
		$mappings['--scd-button-danger-hover']  = $colors['danger_dark'] ?? '#b32d2e';

		// Badge colors
		$mappings['--scd-badge-active']    = $colors['success'] ?? '#00a32a';
		$mappings['--scd-badge-inactive']  = $colors['text_muted'] ?? '#646970';
		$mappings['--scd-badge-scheduled'] = $colors['secondary'] ?? '#72aee6';
		$mappings['--scd-badge-expired']   = $colors['danger'] ?? '#d63638';
		$mappings['--scd-badge-draft']     = $colors['warning'] ?? '#dba617';

		// Alert backgrounds
		$mappings['--scd-alert-success-bg']     = $this->hex_to_rgba( $colors['success'] ?? '#00a32a', 0.1 );
		$mappings['--scd-alert-success-border'] = $colors['success'] ?? '#00a32a';
		$mappings['--scd-alert-error-bg']       = $this->hex_to_rgba( $colors['danger'] ?? '#d63638', 0.1 );
		$mappings['--scd-alert-error-border']   = $colors['danger'] ?? '#d63638';
		$mappings['--scd-alert-warning-bg']     = $this->hex_to_rgba( $colors['warning'] ?? '#dba617', 0.1 );
		$mappings['--scd-alert-warning-border'] = $colors['warning'] ?? '#dba617';
		$mappings['--scd-alert-info-bg']        = $this->hex_to_rgba( $colors['secondary'] ?? '#72aee6', 0.1 );
		$mappings['--scd-alert-info-border']    = $colors['secondary'] ?? '#72aee6';

		// General component colors
		$mappings['--scd-color-primary-bg'] = $this->hex_to_rgba( $colors['primary'] ?? '#2271b1', 0.05 );
		$mappings['--scd-color-success-bg'] = $this->hex_to_rgba( $colors['success'] ?? '#00a32a', 0.1 );
		$mappings['--scd-color-warning-bg'] = $this->hex_to_rgba( $colors['warning'] ?? '#dba617', 0.1 );
		$mappings['--scd-color-error-bg']   = $this->hex_to_rgba( $colors['danger'] ?? '#d63638', 0.1 );

		return apply_filters( 'scd_theme_color_mappings', $mappings, $colors );
	}

	/**
	 * Convert hex color to rgba.
	 *
	 * @since 1.0.0
	 * @param string $hex Hex color.
	 * @param float  $alpha Alpha value.
	 * @return string RGBA color.
	 */
	private function hex_to_rgba( string $hex, float $alpha ): string {
		$hex = str_replace( '#', '', $hex );

		if ( strlen( $hex ) === 3 ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} else {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		}

		return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha );
	}

	/**
	 * Add theme colors to inline styles.
	 *
	 * @since 1.0.0
	 * @param string $handle Style handle.
	 * @param array  $style Style data.
	 */
	public function add_theme_colors_to_inline( string $handle, array $style ): void {
		// This can be used to add theme colors to specific style handles
		// For now, we're handling everything in the admin_head action
	}

	/**
	 * Get theme colors from WordPress admin color scheme and WooCommerce.
	 *
	 * @since 1.0.0
	 * @return array Theme colors.
	 */
	private function get_theme_colors(): array {
		// Get WordPress admin color scheme colors from shared utility
		$colors             = SCD_Theme_Colors::get_theme_colors();
		$admin_color_scheme = get_user_meta( get_current_user_id(), 'admin_color', true );

		// Check for WooCommerce theme color customizations
		$wc_colors = get_theme_mod( 'woocommerce_colors', array() );
		if ( ! empty( $wc_colors ) ) {
			if ( isset( $wc_colors['primary'] ) ) {
				$colors['primary'] = $wc_colors['primary'];
			}
			if ( isset( $wc_colors['secondary'] ) ) {
				$colors['secondary'] = $wc_colors['secondary'];
			}
		}

		// Check for theme support colors
		if ( current_theme_supports( 'custom-colors' ) ) {
			$theme_colors = get_theme_support( 'custom-colors' );
			if ( is_array( $theme_colors ) && ! empty( $theme_colors[0] ) ) {
				$colors = array_merge( $colors, $theme_colors[0] );
			}
		}

		// Generate additional color variations
		$colors['primary_light'] = $this->adjust_color_brightness( $colors['primary'], 20 );
		$colors['primary_dark']  = $this->adjust_color_brightness( $colors['primary'], -20 );
		$colors['success_light'] = $this->adjust_color_brightness( $colors['success'], 20 );
		$colors['success_dark']  = $this->adjust_color_brightness( $colors['success'], -20 );
		$colors['warning_light'] = $this->adjust_color_brightness( $colors['warning'], 20 );
		$colors['warning_dark']  = $this->adjust_color_brightness( $colors['warning'], -20 );
		$colors['danger_light']  = $this->adjust_color_brightness( $colors['danger'], 20 );
		$colors['danger_dark']   = $this->adjust_color_brightness( $colors['danger'], -20 );

		// Common UI colors
		$colors['text']          = '#1e1e1e';
		$colors['text_muted']    = '#646970';
		$colors['text_disabled'] = '#a7aaad';
		$colors['background']    = '#ffffff';
		$colors['surface']       = '#f6f7f7';
		$colors['surface_dark']  = '#e7e8e9';
		$colors['border']        = '#dcdcde';
		$colors['border_light']  = '#f0f0f1';
		$colors['border_dark']   = '#c3c4c7';
		$colors['shadow']        = 'rgba(0, 0, 0, 0.1)';

		// Allow filtering
		return apply_filters( 'scd_theme_colors', $colors, $admin_color_scheme );
	}

	/**
	 * Adjust color brightness.
	 *
	 * @since 1.0.0
	 * @param string $color Hex color.
	 * @param int    $percent Percentage to adjust (-100 to 100).
	 * @return string Adjusted hex color.
	 */
	private function adjust_color_brightness( string $color, int $percent ): string {
		$color = str_replace( '#', '', $color );

		// Convert to RGB
		if ( strlen( $color ) === 3 ) {
			$r = hexdec( substr( $color, 0, 1 ) . substr( $color, 0, 1 ) );
			$g = hexdec( substr( $color, 1, 1 ) . substr( $color, 1, 1 ) );
			$b = hexdec( substr( $color, 2, 1 ) . substr( $color, 2, 1 ) );
		} else {
			$r = hexdec( substr( $color, 0, 2 ) );
			$g = hexdec( substr( $color, 2, 2 ) );
			$b = hexdec( substr( $color, 4, 2 ) );
		}

		// Adjust brightness
		$r = max( 0, min( 255, $r + ( $r * $percent / 100 ) ) );
		$g = max( 0, min( 255, $g + ( $g * $percent / 100 ) ) );
		$b = max( 0, min( 255, $b + ( $b * $percent / 100 ) ) );

		// Convert back to hex
		return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
	}
}
