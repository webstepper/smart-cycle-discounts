<?php
/**
 * HTML Helper Class
 *
 * Provides centralized HTML escaping with support for SVG icons, form elements,
 * and CSS properties. Use this class to escape HTML output for WordPress.org compliance.
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
 * HTML Helper Class
 *
 * Centralized HTML escaping using wp_kses with comprehensive allowed tags
 * including SVG icons, form elements, and extended CSS properties.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_HTML_Helper {

	/**
	 * Cached allowed tags array.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array|null
	 */
	private static $allowed_tags = null;

	/**
	 * Escape HTML content with comprehensive tag and CSS support.
	 *
	 * This method uses wp_kses() with an extended allowed tags list that includes:
	 * - All standard post HTML tags
	 * - SVG elements and attributes
	 * - Form elements (input, select, textarea, button, etc.)
	 * - Data-* attributes (via wildcard)
	 * - ARIA accessibility attributes
	 * - Extended CSS properties (display, flex, fill, etc.)
	 *
	 * @since    1.0.0
	 * @param    string $html    HTML content to escape.
	 * @return   string          Escaped HTML safe for output.
	 */
	public static function kses( $html ) {
		if ( empty( $html ) ) {
			return '';
		}

		// Add CSS properties filter
		add_filter( 'safe_style_css', array( __CLASS__, 'add_safe_style_css' ) );

		// Escape with comprehensive allowed tags
		$result = wp_kses( $html, self::get_allowed_tags() );

		// Remove filter to avoid affecting other code
		remove_filter( 'safe_style_css', array( __CLASS__, 'add_safe_style_css' ) );

		return $result;
	}

	/**
	 * Escape and echo HTML content.
	 *
	 * Convenience method that escapes and outputs HTML in one call.
	 *
	 * @since    1.0.0
	 * @param    string $html    HTML content to escape and output.
	 * @return   void
	 */
	public static function output( $html ) {
		echo self::kses( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped by kses() method using wp_kses with comprehensive allowed tags.
	}

	/**
	 * Add additional CSS properties to WordPress's safe style list.
	 *
	 * WordPress's safecss_filter_attr() has a limited whitelist of CSS properties.
	 * This filter adds properties needed for SVG icons, flexbox, and modern layouts.
	 *
	 * @since    1.0.0
	 * @param    array $styles    Array of allowed CSS properties.
	 * @return   array            Modified array with additional properties.
	 */
	public static function add_safe_style_css( $styles ) {
		$additional_styles = array(
			// Display and visibility
			'display',
			'visibility',
			'opacity',
			// Flexbox
			'flex',
			'flex-grow',
			'flex-shrink',
			'flex-basis',
			'flex-direction',
			'flex-wrap',
			'align-items',
			'align-content',
			'align-self',
			'justify-content',
			'justify-items',
			'justify-self',
			'gap',
			'row-gap',
			'column-gap',
			'order',
			// Grid
			'grid',
			'grid-template',
			'grid-template-columns',
			'grid-template-rows',
			'grid-column',
			'grid-row',
			'grid-gap',
			// Positioning
			'position',
			'top',
			'right',
			'bottom',
			'left',
			'z-index',
			// Sizing
			'min-width',
			'max-width',
			'min-height',
			'max-height',
			'overflow',
			'overflow-x',
			'overflow-y',
			// SVG specific
			'fill',
			'stroke',
			'stroke-width',
			'stroke-linecap',
			'stroke-linejoin',
			// Transforms and transitions
			'transform',
			'transition',
			// Cursor and pointer
			'cursor',
			'pointer-events',
			// Box model
			'box-sizing',
			// Vertical alignment
			'vertical-align',
		);

		return array_merge( $styles, $additional_styles );
	}

	/**
	 * Get comprehensive allowed HTML tags.
	 *
	 * Returns a cached array of allowed HTML tags including:
	 * - Standard post HTML tags
	 * - SVG elements
	 * - Form elements
	 * - Data-* and ARIA attributes
	 *
	 * @since    1.0.0
	 * @return   array    Allowed tags array for wp_kses().
	 */
	public static function get_allowed_tags() {
		if ( null !== self::$allowed_tags ) {
			return self::$allowed_tags;
		}

		// Start with post allowed tags
		$allowed = wp_kses_allowed_html( 'post' );

		// Common attributes for most elements
		$common_attrs = array(
			'id'               => true,
			'class'            => true,
			'style'            => true,
			'data-*'           => true,
			'title'            => true,
			'tabindex'         => true,
			'role'             => true,
			'aria-label'       => true,
			'aria-labelledby'  => true,
			'aria-describedby' => true,
			'aria-hidden'      => true,
			'aria-expanded'    => true,
			'aria-controls'    => true,
			'aria-selected'    => true,
			'aria-checked'     => true,
			'aria-disabled'    => true,
			'aria-required'    => true,
			'aria-invalid'     => true,
			'aria-live'        => true,
			'aria-atomic'      => true,
			'aria-busy'        => true,
			'aria-current'     => true,
			'aria-haspopup'    => true,
			'aria-pressed'     => true,
			'aria-valuenow'    => true,
			'aria-valuemin'    => true,
			'aria-valuemax'    => true,
			'aria-valuetext'   => true,
		);

		// Form element attributes
		$input_attrs = array_merge( $common_attrs, array(
			'type'         => true,
			'name'         => true,
			'value'        => true,
			'placeholder'  => true,
			'required'     => true,
			'disabled'     => true,
			'readonly'     => true,
			'checked'      => true,
			'selected'     => true,
			'min'          => true,
			'max'          => true,
			'step'         => true,
			'minlength'    => true,
			'maxlength'    => true,
			'pattern'      => true,
			'autocomplete' => true,
			'autofocus'    => true,
			'size'         => true,
			'inputmode'    => true,
			'accept'       => true,
			'multiple'     => true,
			'list'         => true,
			'form'         => true,
		) );

		$select_attrs = array_merge( $common_attrs, array(
			'name'     => true,
			'disabled' => true,
			'required' => true,
			'multiple' => true,
			'size'     => true,
			'form'     => true,
		) );

		$option_attrs = array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
			'label'    => true,
			'class'    => true,
			'data-*'   => true,
		);

		$textarea_attrs = array_merge( $common_attrs, array(
			'name'        => true,
			'placeholder' => true,
			'required'    => true,
			'disabled'    => true,
			'readonly'    => true,
			'rows'        => true,
			'cols'        => true,
			'minlength'   => true,
			'maxlength'   => true,
			'wrap'        => true,
			'form'        => true,
		) );

		$button_attrs = array_merge( $common_attrs, array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'disabled' => true,
			'form'     => true,
		) );

		$label_attrs = array_merge( $common_attrs, array(
			'for' => true,
		) );

		$form_attrs = array_merge( $common_attrs, array(
			'action'     => true,
			'method'     => true,
			'enctype'    => true,
			'novalidate' => true,
			'target'     => true,
			'name'       => true,
		) );

		// Add/update form elements
		$allowed['input']    = $input_attrs;
		$allowed['select']   = $select_attrs;
		$allowed['option']   = $option_attrs;
		$allowed['optgroup'] = array_merge( $option_attrs, array( 'label' => true ) );
		$allowed['textarea'] = $textarea_attrs;
		$allowed['button']   = $button_attrs;
		$allowed['label']    = $label_attrs;
		$allowed['form']     = $form_attrs;
		$allowed['fieldset'] = $common_attrs;
		$allowed['legend']   = $common_attrs;
		$allowed['datalist'] = $common_attrs;
		$allowed['output']   = array_merge( $common_attrs, array( 'for' => true, 'name' => true, 'form' => true ) );
		$allowed['progress'] = array_merge( $common_attrs, array( 'value' => true, 'max' => true ) );
		$allowed['meter']    = array_merge( $common_attrs, array( 'value' => true, 'min' => true, 'max' => true, 'low' => true, 'high' => true, 'optimum' => true ) );

		// SVG elements
		$allowed['svg'] = array(
			'class'       => true,
			'xmlns'       => true,
			'width'       => true,
			'height'      => true,
			'viewbox'     => true,
			'fill'        => true,
			'style'       => true,
			'aria-hidden' => true,
			'aria-label'  => true,
			'role'        => true,
			'focusable'   => true,
			'data-*'      => true,
			'id'          => true,
		);

		$allowed['path'] = array(
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
			'id'              => true,
			'style'           => true,
		);

		$allowed['g'] = array(
			'fill'      => true,
			'transform' => true,
			'class'     => true,
			'id'        => true,
			'style'     => true,
		);

		$allowed['circle'] = array(
			'cx'     => true,
			'cy'     => true,
			'r'      => true,
			'fill'   => true,
			'stroke' => true,
			'class'  => true,
			'id'     => true,
			'style'  => true,
		);

		$allowed['rect'] = array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'rx'     => true,
			'ry'     => true,
			'fill'   => true,
			'stroke' => true,
			'class'  => true,
			'id'     => true,
			'style'  => true,
		);

		$allowed['line'] = array(
			'x1'           => true,
			'y1'           => true,
			'x2'           => true,
			'y2'           => true,
			'stroke'       => true,
			'stroke-width' => true,
			'class'        => true,
			'id'           => true,
			'style'        => true,
		);

		$allowed['polyline'] = array(
			'points' => true,
			'fill'   => true,
			'stroke' => true,
			'class'  => true,
			'id'     => true,
			'style'  => true,
		);

		$allowed['polygon'] = array(
			'points' => true,
			'fill'   => true,
			'stroke' => true,
			'class'  => true,
			'id'     => true,
			'style'  => true,
		);

		$allowed['text'] = array(
			'x'           => true,
			'y'           => true,
			'fill'        => true,
			'font-size'   => true,
			'font-family' => true,
			'text-anchor' => true,
			'class'       => true,
			'id'          => true,
			'style'       => true,
		);

		$allowed['tspan'] = array(
			'x'      => true,
			'y'      => true,
			'dx'     => true,
			'dy'     => true,
			'fill'   => true,
			'class'  => true,
			'id'     => true,
			'style'  => true,
		);

		$allowed['use'] = array(
			'href'       => true,
			'xlink:href' => true,
			'x'          => true,
			'y'          => true,
			'width'      => true,
			'height'     => true,
			'class'      => true,
			'id'         => true,
			'style'      => true,
		);

		$allowed['defs']   = array( 'id' => true );
		$allowed['symbol'] = array(
			'id'      => true,
			'viewbox' => true,
			'class'   => true,
		);

		// Update existing elements with common attrs + data-*
		$elements_to_update = array(
			'div', 'span', 'p', 'a', 'img', 'table', 'thead', 'tbody', 'tfoot',
			'tr', 'th', 'td', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'h1', 'h2',
			'h3', 'h4', 'h5', 'h6', 'section', 'article', 'aside', 'header',
			'footer', 'nav', 'main', 'figure', 'figcaption', 'details', 'summary',
			'time', 'mark', 'code', 'pre', 'blockquote', 'cite', 'q', 'abbr',
			'strong', 'em', 'b', 'i', 'u', 's', 'small', 'sub', 'sup', 'hr', 'br',
		);

		foreach ( $elements_to_update as $element ) {
			if ( isset( $allowed[ $element ] ) ) {
				$allowed[ $element ] = array_merge( $allowed[ $element ], $common_attrs );
			} else {
				$allowed[ $element ] = $common_attrs;
			}
		}

		// Special handling for anchor tags
		$allowed['a'] = array_merge( $allowed['a'] ?? array(), $common_attrs, array(
			'href'     => true,
			'target'   => true,
			'rel'      => true,
			'download' => true,
			'hreflang' => true,
			'type'     => true,
		) );

		// Special handling for images
		$allowed['img'] = array_merge( $allowed['img'] ?? array(), $common_attrs, array(
			'src'      => true,
			'alt'      => true,
			'width'    => true,
			'height'   => true,
			'loading'  => true,
			'decoding' => true,
			'srcset'   => true,
			'sizes'    => true,
		) );

		// Table cell attributes
		$cell_attrs = array_merge( $common_attrs, array(
			'colspan' => true,
			'rowspan' => true,
			'scope'   => true,
			'headers' => true,
		) );
		$allowed['th'] = $cell_attrs;
		$allowed['td'] = $cell_attrs;

		// Cache and return
		self::$allowed_tags = $allowed;

		return self::$allowed_tags;
	}

	/**
	 * Clear the cached allowed tags.
	 *
	 * Useful if you need to modify allowed tags dynamically.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function clear_cache() {
		self::$allowed_tags = null;
	}
}
