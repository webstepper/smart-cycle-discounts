<?php
/**
 * Button Helper Class
 *
 * Centralized button rendering with consistent styling and accessibility.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers
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
 * Button Helper Class
 *
 * Provides centralized button rendering following WordPress standards.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Button_Helper {

	/**
	 * Render a button element.
	 *
	 * @since    1.0.0
	 * @param    array $args {
	 *     Button configuration arguments.
	 *
	 *     @type string $text        Button text (required).
	 *     @type string $type        Button type: 'button', 'submit', 'reset'. Default 'button'.
	 *     @type string $style       Button style: 'primary', 'secondary', 'success', 'danger', 'link', 'ghost', 'ghost-danger'. Default 'primary'.
	 *     @type string $size        Button size: 'small', 'normal', 'large', 'hero' (hero maps to large). Default 'normal'.
	 *     @type string $icon        Icon name (SVG icon name). Optional.
	 *     @type string $icon_position Icon position: 'left', 'right'. Default 'left'.
	 *     @type string $href        URL for link-style buttons. Optional.
	 *     @type array  $classes     Additional CSS classes. Optional.
	 *     @type array  $attributes  Additional HTML attributes (data-*, id, etc.). Optional.
	 *     @type bool   $disabled    Whether button is disabled. Default false.
	 *     @type bool   $echo        Whether to echo or return. Default true.
	 * }
	 * @return   string|void    Button HTML if $echo is false.
	 */
	public static function render( $args = array() ) {
		$defaults = array(
			'text'          => '',
			'type'          => 'button',
			'style'         => 'primary',
			'size'          => 'normal',
			'icon'          => '',
			'icon_position' => 'left',
			'href'          => '',
			'classes'       => array(),
			'attributes'    => array(),
			'disabled'      => false,
			'echo'          => true,
			'icon_only'     => false, // Internal flag for icon-only buttons
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate required fields - text is required unless icon-only button
		if ( empty( $args['text'] ) && empty( $args['icon_only'] ) ) {
			return '';
		}

		// Build classes - Using SCD design system
		$classes = array( 'wsscd-button' );

		// Style classes
		switch ( $args['style'] ) {
			case 'primary':
				$classes[] = 'wsscd-button--primary';
				break;
			case 'secondary':
				$classes[] = 'wsscd-button--secondary';
				break;
			case 'danger':
				$classes[] = 'wsscd-button--danger';
				break;
			case 'success':
				$classes[] = 'wsscd-button--success';
				break;
			case 'link':
				$classes[] = 'wsscd-button--link';
				break;
			case 'ghost':
				$classes[] = 'wsscd-button--ghost';
				break;
			case 'ghost-danger':
				$classes[] = 'wsscd-button--ghost-danger';
				break;
		}

		// Size classes
		switch ( $args['size'] ) {
			case 'small':
				$classes[] = 'wsscd-button--small';
				break;
			case 'large':
			case 'hero': // Map hero to large
				$classes[] = 'wsscd-button--large';
				break;
		}

		// Add icon-only class
		if ( ! empty( $args['icon_only'] ) ) {
			$classes[] = 'wsscd-button--icon-only';
		}

		// Add custom classes
		if ( ! empty( $args['classes'] ) ) {
			$classes = array_merge( $classes, (array) $args['classes'] );
		}

		// Build attributes
		$attributes = array(
			'class' => implode( ' ', array_filter( $classes ) ),
		);

		// Add disabled attribute
		if ( $args['disabled'] ) {
			$attributes['disabled'] = 'disabled';
			$attributes['aria-disabled'] = 'true';
		}

		// Add custom attributes
		if ( ! empty( $args['attributes'] ) ) {
			$attributes = array_merge( $attributes, $args['attributes'] );
		}

		// Build icon HTML - Use SVG icons for perfect alignment
		$icon_html = '';
		if ( ! empty( $args['icon'] ) ) {
			// Get SVG icon from Icon_Helper
			if ( class_exists( 'WSSCD_Icon_Helper' ) && WSSCD_Icon_Helper::has_icon( $args['icon'] ) ) {
				$icon_html = WSSCD_Icon_Helper::get(
					$args['icon'],
					array(
						'size'        => 20,
						'class'       => 'wsscd-button-icon',
						'aria_hidden' => true,
					)
				);
			}
		}

		// Build button content
		// Note: $icon_html is already escaped via wp_kses() in WSSCD_Icon_Helper::get()
		$content = '';
		if ( 'left' === $args['icon_position'] && ! empty( $icon_html ) ) {
			$content .= $icon_html;
		}
		// For icon-only buttons, only render the icon
		if ( ! empty( $args['icon_only'] ) ) {
			// Icon already added above for left position (which is default)
			// No text span needed for icon-only buttons
		} else {
			// Wrap text in span for JavaScript manipulation (e.g., loading states)
			$content .= '<span class="wsscd-button-text">' . esc_html( $args['text'] ) . '</span>';
		}
		if ( 'right' === $args['icon_position'] && ! empty( $icon_html ) ) {
			$content .= ' ' . $icon_html;
		}

		// Render button or link
		if ( ! empty( $args['href'] ) ) {
			// Render as link
			$attributes['href'] = esc_url( $args['href'] );
			$html = sprintf(
				'<a %s>%s</a>',
				self::build_attributes_string( $attributes ),
				$content
			);
		} else {
			// Render as button
			$attributes['type'] = esc_attr( $args['type'] );
			$html = sprintf(
				'<button %s>%s</button>',
				self::build_attributes_string( $attributes ),
				$content
			);
		}

		if ( $args['echo'] ) {
			WSSCD_HTML_Helper::output( $html );
		} else {
			return $html;
		}
	}

	/**
	 * Render a primary button.
	 *
	 * @since    1.0.0
	 * @param    string $text        Button text.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Button HTML if $args['echo'] is false.
	 */
	public static function primary( $text, $args = array() ) {
		$args['text'] = $text;
		$args['style'] = 'primary';
		return self::render( $args );
	}

	/**
	 * Render a secondary button.
	 *
	 * @since    1.0.0
	 * @param    string $text        Button text.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Button HTML if $args['echo'] is false.
	 */
	public static function secondary( $text, $args = array() ) {
		$args['text'] = $text;
		$args['style'] = 'secondary';
		return self::render( $args );
	}

	/**
	 * Render a danger button.
	 *
	 * @since    1.0.0
	 * @param    string $text        Button text.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Button HTML if $args['echo'] is false.
	 */
	public static function danger( $text, $args = array() ) {
		$args['text'] = $text;
		$args['style'] = 'danger';
		return self::render( $args );
	}

	/**
	 * Render a success button.
	 *
	 * @since    1.0.0
	 * @param    string $text        Button text.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Button HTML if $args['echo'] is false.
	 */
	public static function success( $text, $args = array() ) {
		$args['text'] = $text;
		$args['style'] = 'success';
		return self::render( $args );
	}

	/**
	 * Render a link-style button.
	 *
	 * @since    1.0.0
	 * @param    string $text        Button text.
	 * @param    string $href        Link URL.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Button HTML if $args['echo'] is false.
	 */
	public static function link( $text, $href, $args = array() ) {
		$args['text'] = $text;
		$args['href'] = $href;
		$args['style'] = 'link';
		return self::render( $args );
	}

	/**
	 * Render an icon button (icon only, no text visible).
	 *
	 * @since    1.0.0
	 * @param    string $icon           SVG icon name from Icon_Helper.
	 * @param    string $aria_label     Accessible label for screen readers (required).
	 * @param    array  $args           Additional arguments. See render() for full list.
	 * @return   string|void    Button HTML if $args['echo'] is false.
	 */
	public static function icon( $icon, $aria_label, $args = array() ) {
		// Validate required parameters
		if ( empty( $icon ) || empty( $aria_label ) ) {
			return '';
		}

		// Set icon-only flag and icon
		$args['icon_only'] = true;
		$args['icon'] = $icon;
		$args['text'] = ''; // No visible text for icon buttons

		// Ensure attributes array exists
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		// Add aria-label for accessibility
		$args['attributes']['aria-label'] = $aria_label;

		// Use main render method for consistency
		return self::render( $args );
	}

	/**
	 * Render a button group.
	 *
	 * @since    1.0.0
	 * @param    array $buttons    Array of button configurations (each with args for render()).
	 * @param    array $args {
	 *     Button group configuration.
	 *
	 *     @type array  $classes     Additional CSS classes for the group container. Optional.
	 *     @type bool   $echo        Whether to echo or return. Default true.
	 * }
	 * @return   string|void    Button group HTML if $args['echo'] is false.
	 */
	public static function group( $buttons, $args = array() ) {
		$defaults = array(
			'classes' => array(),
			'echo'    => true,
		);

		$args = wp_parse_args( $args, $defaults );

		$classes = array( 'wsscd-button-group' );
		if ( ! empty( $args['classes'] ) ) {
			$classes = array_merge( $classes, (array) $args['classes'] );
		}

		$html = sprintf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );

		foreach ( $buttons as $button_args ) {
			$button_args['echo'] = false;
			$html .= self::render( $button_args );
		}

		$html .= '</div>';

		if ( $args['echo'] ) {
			WSSCD_HTML_Helper::output( $html );
		} else {
			return $html;
		}
	}

	/**
	 * Build HTML attributes string from array.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $attributes    Associative array of attributes.
	 * @return   string    HTML attributes string.
	 */
	private static function build_attributes_string( $attributes ) {
		$output = array();
		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$output[] = esc_attr( $key );
				}
			} else {
				$output[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
		}
		return implode( ' ', $output );
	}
}
