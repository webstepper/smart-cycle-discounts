<?php
/**
 * Tooltip Helper Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers/class-tooltip-helper.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tooltip Helper Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Tooltip_Helper {

	/**
	 * Render tooltip icon with text.
	 *
	 * @since    1.0.0
	 * @param    string $text    Tooltip text.
	 * @param    array  $args    Optional arguments.
	 * @return   void
	 */
	public static function render( $text, $args = array() ) {
		$defaults = array(
			'icon'       => 'editor-help',
			'aria_label' => $text,
			'class'      => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$icon_name = str_replace( 'dashicons-', '', $args['icon'] );
		$icon_html = SCD_Icon_Helper::get( $icon_name, array( 'size' => 16 ) );

		printf(
			'<span class="scd-field-helper %s" aria-label="%s" data-tooltip="%s" tabindex="0">%s</span>',
			esc_attr( $args['class'] ),
			esc_attr( $args['aria_label'] ),
			esc_attr( $text ),
			$icon_html
		);
	}

	/**
	 * Get tooltip HTML (returns instead of echoing).
	 *
	 * @since    1.0.0
	 * @param    string $text    Tooltip text.
	 * @param    array  $args    Optional arguments.
	 * @return   string             Tooltip HTML.
	 */
	public static function get( $text, $args = array() ) {
		ob_start();
		self::render( $text, $args );
		return ob_get_clean();
	}

	/**
	 * Render field label with tooltip.
	 *
	 * @since    1.0.0
	 * @param    string $label     Field label text.
	 * @param    string $for       Input ID.
	 * @param    string $tooltip   Tooltip text.
	 * @param    array  $args      Optional tooltip arguments.
	 * @return   void
	 */
	public static function render_label( $label, $for, $tooltip, $args = array() ) {
		printf(
			'<label for="%s">%s %s</label>',
			esc_attr( $for ),
			esc_html( $label ),
			self::get( $tooltip, $args )
		);
	}

	/**
	 * Render inline help text with tooltip.
	 *
	 * Useful for adding tooltips after form fields or buttons.
	 *
	 * @since    1.0.0
	 * @param    string $text    Tooltip text.
	 * @param    array  $args    Optional arguments.
	 * @return   void
	 */
	public static function render_inline( $text, $args = array() ) {
		$defaults = array(
			'class' => 'scd-inline-tooltip',
		);

		$args = wp_parse_args( $args, $defaults );

		self::render( $text, $args );
	}

	/**
	 * Get inline tooltip HTML.
	 *
	 * @since    1.0.0
	 * @param    string $text    Tooltip text.
	 * @param    array  $args    Optional arguments.
	 * @return   string             Tooltip HTML.
	 */
	public static function get_inline( $text, $args = array() ) {
		ob_start();
		self::render_inline( $text, $args );
		return ob_get_clean();
	}
}
