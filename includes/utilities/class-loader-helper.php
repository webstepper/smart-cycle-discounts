<?php
/**
 * Loader Helper Class
 *
 * Provides unified loader markup generation for consistent loading states
 * across the plugin using WordPress core spinner with custom overlays.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Loader Helper Class
 *
 * Generates consistent loader markup using WordPress spinner
 * with plugin-specific wrapper and overlay styles.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Loader_Helper {

	/**
	 * Generate full-screen loader markup
	 *
	 * Covers the viewport (excluding admin sidebar) with a centered spinner.
	 * Use for: Page loads, analytics dashboard, major data refreshes.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    string $text        Loading text to display (default: 'Loading...').
	 * @param    bool   $visible     Whether loader should be visible on render (default: false).
	 * @return   string              HTML markup for full-screen loader.
	 */
	public static function fullscreen( $id = 'scd-loader-fullscreen', $text = '', $visible = false ) {
		$text         = $text ? $text : __( 'Loading...', 'smart-cycle-discounts' );
		$display      = $visible ? 'block' : 'none';
		$escaped_id   = esc_attr( $id );
		$escaped_text = esc_html( $text );

		return sprintf(
			'<div id="%s" class="scd-loader-overlay scd-loader-fullscreen" style="display: %s;">
				<div class="scd-loader-content">
					<span class="spinner is-active"></span>
					<span class="scd-loader-text">%s</span>
				</div>
			</div>',
			$escaped_id,
			esc_attr( $display ),
			$escaped_text
		);
	}

	/**
	 * Generate container loader markup
	 *
	 * Covers a specific container with an absolute-positioned overlay.
	 * Use for: Dashboard widgets, panels, sections, modals.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    string $text        Loading text to display (optional, can be empty).
	 * @param    bool   $visible     Whether loader should be visible on render (default: false).
	 * @return   string              HTML markup for container loader.
	 */
	public static function container( $id = 'scd-loader-container', $text = '', $visible = false ) {
		$display      = $visible ? 'flex' : 'none';
		$escaped_id   = esc_attr( $id );
		$text_markup  = '';

		if ( $text ) {
			$escaped_text = esc_html( $text );
			$text_markup  = sprintf( '<span class="scd-loader-text">%s</span>', $escaped_text );
		}

		return sprintf(
			'<div id="%s" class="scd-loader-overlay scd-loader-container" style="display: %s;">
				<div class="scd-loader-content">
					<span class="spinner is-active"></span>
					%s
				</div>
			</div>',
			$escaped_id,
			esc_attr( $display ),
			$text_markup
		);
	}

	/**
	 * Generate inline loader markup
	 *
	 * Small spinner for buttons or inline elements (no overlay).
	 * Use for: Launch button, Save button, inline actions.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    bool   $visible     Whether loader should be visible on render (default: false).
	 * @return   string              HTML markup for inline loader.
	 */
	public static function inline( $id = 'scd-loader-inline', $visible = false ) {
		$class       = $visible ? 'scd-loader-inline is-active' : 'scd-loader-inline';
		$escaped_id  = esc_attr( $id );

		return sprintf(
			'<span id="%s" class="%s"><span class="spinner is-active"></span></span>',
			$escaped_id,
			esc_attr( $class )
		);
	}

	/**
	 * Generate button loader markup
	 *
	 * Spinner that replaces button text during processing.
	 * Use for: Form submissions, campaign launch, save operations.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    string $text        Loading text to display (default: 'Processing...').
	 * @param    bool   $visible     Whether loader should be visible on render (default: false).
	 * @return   string              HTML markup for button loader.
	 */
	public static function button( $id = 'scd-loader-button', $text = '', $visible = false ) {
		$text        = $text ? $text : __( 'Processing...', 'smart-cycle-discounts' );
		$display     = $visible ? 'inline-flex' : 'none';
		$escaped_id  = esc_attr( $id );
		$escaped_text = esc_html( $text );

		return sprintf(
			'<span id="%s" class="scd-loader-button" style="display: %s;">
				<span class="spinner is-active"></span>
				<span class="scd-loader-text">%s</span>
			</span>',
			$escaped_id,
			esc_attr( $display ),
			$escaped_text
		);
	}

	/**
	 * Output full-screen loader
	 *
	 * Directly echoes the full-screen loader markup.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    string $text        Loading text to display.
	 * @param    bool   $visible     Whether loader should be visible on render.
	 * @return   void
	 */
	public static function render_fullscreen( $id = 'scd-loader-fullscreen', $text = '', $visible = false ) {
		echo self::fullscreen( $id, $text, $visible );
	}

	/**
	 * Output container loader
	 *
	 * Directly echoes the container loader markup.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    string $text        Loading text to display.
	 * @param    bool   $visible     Whether loader should be visible on render.
	 * @return   void
	 */
	public static function render_container( $id = 'scd-loader-container', $text = '', $visible = false ) {
		echo self::container( $id, $text, $visible );
	}

	/**
	 * Output inline loader
	 *
	 * Directly echoes the inline loader markup.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    bool   $visible     Whether loader should be visible on render.
	 * @return   void
	 */
	public static function render_inline( $id = 'scd-loader-inline', $visible = false ) {
		echo self::inline( $id, $visible );
	}

	/**
	 * Output button loader
	 *
	 * Directly echoes the button loader markup.
	 *
	 * @since    1.0.0
	 * @param    string $id          Unique ID for the loader element.
	 * @param    string $text        Loading text to display.
	 * @param    bool   $visible     Whether loader should be visible on render.
	 * @return   void
	 */
	public static function render_button( $id = 'scd-loader-button', $text = '', $visible = false ) {
		echo self::button( $id, $text, $visible );
	}
}
