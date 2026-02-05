<?php
/**
 * Theme Color Inline Styles Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets/class-theme-color-inline-styles.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
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
class WSSCD_Theme_Color_Inline_Styles {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		// Hook into admin head with high priority to output early
		add_action( 'admin_head', array( $this, 'print_theme_color_styles' ), 1 );

		// Also hook into SCD's inline style action
		add_action( 'wsscd_print_inline_style', array( $this, 'add_theme_colors_to_inline' ), 10, 2 );
	}

	/**
	 * Print theme color styles in admin head.
	 *
	 * @since 1.0.0
	 */
	public function print_theme_color_styles(): void {
		$colors = $this->get_theme_colors();

		if ( empty( $colors ) ) {
			return;
		}

		// Generate inline CSS
		$css = $this->generate_theme_color_css( $colors );

		if ( ! empty( $css ) ) {
			// Use wp_add_inline_style for WordPress.org compliance
			// We need a handle - use wp-admin as it's always loaded in admin
			wp_add_inline_style( 'wp-admin', $css );

			// Add the JavaScript to mark colors as loaded using wp_add_inline_script
			$js = 'document.addEventListener("DOMContentLoaded", function() {' .
				'setTimeout(function() {' .
				'if (document.body) { document.body.classList.add("wsscd-colors-loaded"); }' .
				'}, 100);' .
				'});';

			wp_add_inline_script( 'jquery-core', $js );
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

		// Base colors - sanitize each color value before output
		foreach ( $colors as $key => $value ) {
			$sanitized_key   = $this->sanitize_css_property_name( $key );
			$sanitized_value = $this->sanitize_css_color_value( $value );

			// Skip if invalid
			if ( empty( $sanitized_key ) || empty( $sanitized_value ) ) {
				continue;
			}

			$css .= sprintf( '    --wsscd-color-%s: %s;' . "\n", $sanitized_key, $sanitized_value );
		}

		// Component-specific colors - sanitize each property and value
		$component_mappings = $this->get_component_mappings( $colors );
		foreach ( $component_mappings as $property => $value ) {
			$sanitized_property = $this->sanitize_css_property_name( $property );
			$sanitized_value    = $this->sanitize_css_color_value( $value );

			// Skip if invalid
			if ( empty( $sanitized_property ) || empty( $sanitized_value ) ) {
				continue;
			}

			$css .= sprintf( '    %s: %s;' . "\n", $sanitized_property, $sanitized_value );
		}

		$css .= '}' . "\n";

		$css .= "\n" . '/* Badge styles are now in shared/_badges.css */' . "\n";
		$css .= "\n" . '/* Draft Notice styles are now in campaigns-list.css */' . "\n";

		$css .= "\n" . '/* Prevent color transitions during initial load */' . "\n";
		$css .= 'body:not(.wsscd-colors-loaded) * {' . "\n";
		$css .= '    transition-duration: 0s !important;' . "\n";
		$css .= '}' . "\n";

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
		$mappings['--wsscd-wizard-primary']   = $colors['primary'] ?? '#2271b1';
		$mappings['--wsscd-wizard-secondary'] = $colors['secondary'] ?? '#72aee6';
		$mappings['--wsscd-wizard-success']   = $colors['success'] ?? '#00a32a';
		$mappings['--wsscd-wizard-warning']   = $colors['warning'] ?? '#dba617';
		$mappings['--wsscd-wizard-danger']    = $colors['danger'] ?? '#d63638';
		$mappings['--wsscd-wizard-accent']    = $colors['accent'] ?? '#3858e9';

		// Form colors
		$mappings['--wsscd-form-focus']          = $colors['primary'] ?? '#2271b1';
		$mappings['--wsscd-form-error']          = $colors['danger'] ?? '#d63638';
		$mappings['--wsscd-form-success']        = $colors['success'] ?? '#00a32a';
		$mappings['--wsscd-form-checked-bg']     = $colors['primary'] ?? '#2271b1';
		$mappings['--wsscd-form-checked-border'] = $colors['primary'] ?? '#2271b1';
		$mappings['--wsscd-form-toggle-on']      = $colors['success'] ?? '#00a32a';
		$mappings['--wsscd-form-focus-shadow']   = $this->hex_to_rgba( $colors['primary'] ?? '#2271b1', 0.25 );

		// Button colors
		$mappings['--wsscd-button-primary']       = $colors['primary'] ?? '#2271b1';
		$mappings['--wsscd-button-primary-hover'] = $colors['primary_dark'] ?? '#135e96';
		$mappings['--wsscd-button-success']       = $colors['success'] ?? '#00a32a';
		$mappings['--wsscd-button-success-hover'] = $colors['success_dark'] ?? '#008a20';
		$mappings['--wsscd-button-danger']        = $colors['danger'] ?? '#d63638';
		$mappings['--wsscd-button-danger-hover']  = $colors['danger_dark'] ?? '#b32d2e';

		// Badge colors
		$mappings['--wsscd-badge-active']    = $colors['success'] ?? '#00a32a';
		$mappings['--wsscd-badge-inactive']  = $colors['text_muted'] ?? '#646970';
		$mappings['--wsscd-badge-scheduled'] = $colors['secondary'] ?? '#72aee6';
		$mappings['--wsscd-badge-expired']   = $colors['danger'] ?? '#d63638';
		$mappings['--wsscd-badge-draft']     = $colors['warning'] ?? '#dba617';

		// Alert backgrounds
		$mappings['--wsscd-alert-success-bg']     = $this->hex_to_rgba( $colors['success'] ?? '#00a32a', 0.1 );
		$mappings['--wsscd-alert-success-border'] = $colors['success'] ?? '#00a32a';
		$mappings['--wsscd-alert-error-bg']       = $this->hex_to_rgba( $colors['danger'] ?? '#d63638', 0.1 );
		$mappings['--wsscd-alert-error-border']   = $colors['danger'] ?? '#d63638';
		$mappings['--wsscd-alert-warning-bg']     = $this->hex_to_rgba( $colors['warning'] ?? '#dba617', 0.1 );
		$mappings['--wsscd-alert-warning-border'] = $colors['warning'] ?? '#dba617';
		$mappings['--wsscd-alert-info-bg']        = $this->hex_to_rgba( $colors['secondary'] ?? '#72aee6', 0.1 );
		$mappings['--wsscd-alert-info-border']    = $colors['secondary'] ?? '#72aee6';

		// General component colors
		$mappings['--wsscd-color-primary-bg'] = $this->hex_to_rgba( $colors['primary'] ?? '#2271b1', 0.05 );
		$mappings['--wsscd-color-success-bg'] = $this->hex_to_rgba( $colors['success'] ?? '#00a32a', 0.1 );
		$mappings['--wsscd-color-warning-bg'] = $this->hex_to_rgba( $colors['warning'] ?? '#dba617', 0.1 );
		$mappings['--wsscd-color-error-bg']   = $this->hex_to_rgba( $colors['danger'] ?? '#d63638', 0.1 );

		return apply_filters( 'wsscd_theme_color_mappings', $mappings, $colors );
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
		$colors             = WSSCD_Theme_Colors::get_theme_colors();
		$admin_color_scheme = get_user_meta( get_current_user_id(), 'admin_color', true );

		$wc_colors = get_theme_mod( 'woocommerce_colors', array() );
		if ( ! empty( $wc_colors ) ) {
			if ( isset( $wc_colors['primary'] ) ) {
				$colors['primary'] = $wc_colors['primary'];
			}
			if ( isset( $wc_colors['secondary'] ) ) {
				$colors['secondary'] = $wc_colors['secondary'];
			}
		}

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
		return apply_filters( 'wsscd_theme_colors', $colors, $admin_color_scheme );
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

		return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Sanitize CSS property name.
	 *
	 * Ensures CSS property names contain only valid characters.
	 *
	 * @since 1.0.0
	 * @param string $property CSS property name.
	 * @return string Sanitized property name or empty string if invalid.
	 */
	private function sanitize_css_property_name( string $property ): string {
		// CSS custom properties (variables) start with -- (e.g., --wsscd-color-primary)
		// Standard properties contain only letters, numbers, hyphens (e.g., background-color)
		// Pattern allows:
		// 1. Custom properties: --[identifier]
		// 2. Standard properties: [identifier]
		if ( preg_match( '/^(--)?[a-zA-Z_][a-zA-Z0-9_-]*$/', $property ) ) {
			return $property;
		}

		return '';
	}

	/**
	 * Sanitize CSS color value.
	 *
	 * Validates and sanitizes color values to prevent CSS/HTML injection.
	 * Accepts:
	 * - Hex colors: #fff, #ffffff
	 * - RGB/RGBA: rgb(0,0,0), rgba(0,0,0,0.5)
	 * - Named colors from a whitelist
	 *
	 * @since 1.0.0
	 * @param string $value CSS color value.
	 * @return string Sanitized color value or empty string if invalid.
	 */
	private function sanitize_css_color_value( string $value ): string {
		$value = trim( $value );

		// Try WordPress sanitize_hex_color first for hex values
		if ( 0 === strpos( $value, '#' ) ) {
			$hex = sanitize_hex_color( $value );
			if ( $hex ) {
				return $hex;
			}
			// Try 3-character hex
			$hex = sanitize_hex_color_no_hash( str_replace( '#', '', $value ) );
			if ( $hex ) {
				return '#' . $hex;
			}
		}

		// Validate RGB format: rgb(0, 0, 0) or rgb(0,0,0)
		if ( preg_match( '/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $matches ) ) {
			$r = min( 255, max( 0, absint( $matches[1] ) ) );
			$g = min( 255, max( 0, absint( $matches[2] ) ) );
			$b = min( 255, max( 0, absint( $matches[3] ) ) );
			return sprintf( 'rgb(%d, %d, %d)', $r, $g, $b );
		}

		// Validate RGBA format: rgba(0, 0, 0, 0.5)
		if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([0-9.]+)\s*\)$/i', $value, $matches ) ) {
			$r = min( 255, max( 0, absint( $matches[1] ) ) );
			$g = min( 255, max( 0, absint( $matches[2] ) ) );
			$b = min( 255, max( 0, absint( $matches[3] ) ) );
			$a = min( 1, max( 0, (float) $matches[4] ) );
			return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $a );
		}

		// Whitelist of safe CSS color keywords
		$safe_colors = array(
			'transparent',
			'inherit',
			'currentColor',
			'black',
			'white',
			'red',
			'green',
			'blue',
			'yellow',
			'orange',
			'purple',
			'gray',
			'grey',
		);

		if ( in_array( strtolower( $value ), array_map( 'strtolower', $safe_colors ), true ) ) {
			return strtolower( $value );
		}

		// Invalid color - return empty
		return '';
	}
}
