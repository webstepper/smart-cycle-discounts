<?php
/**
 * Tooltip Helper
 *
 * Provides consistent tooltip generation across the plugin.
 * Uses the shared tooltip component (scd-tooltips.js) for display.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
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
			'icon'       => 'dashicons-editor-help',
			'aria_label' => $text,
			'class'      => '',
		);

		$args = wp_parse_args( $args, $defaults );

		printf(
			'<span class="scd-field-helper %s" aria-label="%s" data-tooltip="%s" tabindex="0"><span class="dashicons %s"></span></span>',
			esc_attr( $args['class'] ),
			esc_attr( $args['aria_label'] ),
			esc_attr( $text ),
			esc_attr( $args['icon'] )
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
