<?php
/**
 * Card Helper Class
 *
 * Centralized card rendering with consistent styling and structure.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers
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
 * Card Helper Class
 *
 * Provides centralized card rendering following SCD design system.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/helpers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Card_Helper {

	/**
	 * Render a card element.
	 *
	 * @since    1.0.0
	 * @param    array $args {
	 *     Card configuration arguments.
	 *
	 *     @type string $title        Card title (optional).
	 *     @type string $subtitle     Card subtitle (optional).
	 *     @type string $content      Card body content (required).
	 *     @type string $footer       Card footer content (optional).
	 *     @type string $icon         Icon name for header. Optional.
	 *     @type string $badge        Badge text for header. Optional.
	 *     @type string $badge_type   Badge type: 'success', 'warning', 'danger', 'info'. Default 'info'.
	 *     @type array  $actions      Array of action button configs (see Button_Helper). Optional.
	 *     @type string $variant      Card variant: 'default', 'stat', 'metric', 'summary', 'highlight'. Default 'default'.
	 *     @type array  $classes      Additional CSS classes. Optional.
	 *     @type array  $attributes   Additional HTML attributes (data-*, id, etc.). Optional.
	 *     @type bool   $collapsible  Whether card is collapsible. Default false.
	 *     @type bool   $collapsed    Initial collapsed state (requires collapsible). Default false.
	 *     @type bool   $echo         Whether to echo or return. Default true.
	 * }
	 * @return   string|void    Card HTML if $echo is false.
	 */
	public static function render( $args = array() ) {
		$defaults = array(
			'title'        => '',
			'subtitle'     => '',
			'content'      => '',
			'footer'       => '',
			'icon'         => '',
			'badge'        => '',
			'badge_type'   => 'info',
			'actions'      => array(),
			'variant'      => 'default',
			'classes'      => array(),
			'attributes'   => array(),
			'collapsible'  => false,
			'collapsed'    => false,
			'echo'         => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate required fields
		if ( empty( $args['content'] ) && empty( $args['title'] ) ) {
			return '';
		}

		// Build classes
		$classes = array( 'wsscd-card' );

		// Variant classes
		switch ( $args['variant'] ) {
			case 'stat':
				$classes[] = 'wsscd-card--stat';
				break;
			case 'metric':
				$classes[] = 'wsscd-card--metric';
				break;
			case 'summary':
				$classes[] = 'wsscd-card--summary';
				break;
			case 'highlight':
				$classes[] = 'wsscd-card--highlight';
				break;
		}

		// Collapsible state
		if ( $args['collapsible'] ) {
			$classes[] = 'wsscd-card--collapsible';
			if ( $args['collapsed'] ) {
				$classes[] = 'wsscd-card--collapsed';
			}
		}

		// Add custom classes
		if ( ! empty( $args['classes'] ) ) {
			$classes = array_merge( $classes, (array) $args['classes'] );
		}

		// Build attributes
		$attributes = array(
			'class' => implode( ' ', array_filter( $classes ) ),
		);

		// Add custom attributes
		if ( ! empty( $args['attributes'] ) ) {
			$attributes = array_merge( $attributes, $args['attributes'] );
		}

		// Start building HTML
		$html = sprintf( '<div %s>', self::build_attributes_string( $attributes ) );

		// Build header if title, icon, badge, or actions provided
		if ( ! empty( $args['title'] ) || ! empty( $args['icon'] ) || ! empty( $args['badge'] ) || ! empty( $args['actions'] ) ) {
			$html .= '<div class="wsscd-card__header">';

			// Header content wrapper
			$html .= '<div class="wsscd-card__header-content">';

			// Icon - use wp_kses with SVG allowed tags since wp_kses_post strips SVG
			if ( ! empty( $args['icon'] ) ) {
				$html .= '<span class="wsscd-card__icon">' . wp_kses( WSSCD_Icon_Helper::get( $args['icon'], array( 'size' => 20 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() ) . '</span>';
			}

			// Title and subtitle
			if ( ! empty( $args['title'] ) ) {
				$html .= '<div class="wsscd-card__header-text">';
				$html .= sprintf( '<h3 class="wsscd-card__title">%s</h3>', esc_html( $args['title'] ) );
				if ( ! empty( $args['subtitle'] ) ) {
					$html .= sprintf( '<p class="wsscd-card__subtitle">%s</p>', esc_html( $args['subtitle'] ) );
				}
				$html .= '</div>';
			}

			// Badge - Use Badge_Helper for consistency
			if ( ! empty( $args['badge'] ) ) {
				$badge_class = 'wsscd-badge wsscd-badge--' . esc_attr( $args['badge_type'] );
				$html .= wp_kses_post( WSSCD_Badge_Helper::badge( $args['badge'], $badge_class ) );
			}

			$html .= '</div>'; // End header-content

			// Actions - Button_Helper output is already properly escaped including SVG icons
			if ( ! empty( $args['actions'] ) ) {
				$html .= '<div class="wsscd-card__actions">';
				foreach ( $args['actions'] as $action_args ) {
					$action_args['echo'] = false;
					$html .= WSSCD_Button_Helper::render( $action_args );
				}
				$html .= '</div>';
			}

			// Collapsible toggle - use wp_kses with SVG allowed tags since wp_kses_post strips SVG
			if ( $args['collapsible'] ) {
				$aria_expanded = $args['collapsed'] ? 'false' : 'true';
				$aria_label = $args['collapsed']
					? __( 'Expand card content', 'smart-cycle-discounts' )
					: __( 'Collapse card content', 'smart-cycle-discounts' );
				$html .= sprintf(
					'<button type="button" class="wsscd-card__toggle" aria-expanded="%s" aria-label="%s">%s</button>',
					esc_attr( $aria_expanded ),
					esc_attr( $aria_label ),
					wp_kses( WSSCD_Icon_Helper::get( 'arrow-down', array( 'size' => 16 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() )
				);
			}

			$html .= '</div>'; // End header
		}

		// Body (using __content to match existing convention)
		$html .= '<div class="wsscd-card__content">';
		$html .= $args['content']; // Content is already escaped by caller
		$html .= '</div>';

		// Footer
		if ( ! empty( $args['footer'] ) ) {
			$html .= '<div class="wsscd-card__footer">';
			$html .= $args['footer']; // Footer is already escaped by caller
			$html .= '</div>';
		}

		$html .= '</div>'; // End card

		if ( $args['echo'] ) {
			WSSCD_HTML_Helper::output( $html );
		} else {
			return $html;
		}
	}

	/**
	 * Render a stat card (large number with label).
	 *
	 * @since    1.0.0
	 * @param    string $value       The stat value (number).
	 * @param    string $label       The stat label.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Card HTML if $args['echo'] is false.
	 */
	public static function stat( $value, $label, $args = array() ) {
		$content = sprintf(
			'<div class="wsscd-stat-value">%s</div><div class="wsscd-stat-label">%s</div>',
			esc_html( $value ),
			esc_html( $label )
		);

		$args['content'] = $content;
		$args['variant'] = 'stat';
		return self::render( $args );
	}

	/**
	 * Render a metric card (value with trend indicator).
	 *
	 * @since    1.0.0
	 * @param    string $value       The metric value.
	 * @param    string $label       The metric label.
	 * @param    string $trend       Trend direction: 'up', 'down', 'neutral'. Default 'neutral'.
	 * @param    string $change      Trend change text (e.g., '+15%'). Optional.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Card HTML if $args['echo'] is false.
	 */
	public static function metric( $value, $label, $trend = 'neutral', $change = '', $args = array() ) {
		$trend_class = 'wsscd-metric-trend--' . esc_attr( $trend );
		$trend_icon = 'neutral' === $trend ? 'minus' : ( 'up' === $trend ? 'arrow-up-alt' : 'arrow-down-alt' );

		$content = sprintf(
			'<div class="wsscd-metric-value">%s</div><div class="wsscd-metric-label">%s</div>',
			esc_html( $value ),
			esc_html( $label )
		);

		if ( ! empty( $change ) ) {
			$icon_html = WSSCD_Icon_Helper::get( $trend_icon, array( 'size' => 16 ) );
			$content .= sprintf(
				'<div class="wsscd-metric-trend %s">%s %s</div>',
				esc_attr( $trend_class ),
				wp_kses( $icon_html, WSSCD_Icon_Helper::get_allowed_svg_tags() ),
				esc_html( $change )
			);
		}

		$args['content'] = $content;
		$args['variant'] = 'metric';
		return self::render( $args );
	}

	/**
	 * Render a summary card (key-value pairs).
	 *
	 * @since    1.0.0
	 * @param    array $items        Array of key-value pairs array( 'label' => 'value' ).
	 * @param    array $args         Additional arguments. See render() for full list.
	 * @return   string|void    Card HTML if $args['echo'] is false.
	 */
	public static function summary( $items, $args = array() ) {
		if ( empty( $items ) ) {
			return '';
		}

		$content = '<dl class="wsscd-summary-list">';
		foreach ( $items as $label => $value ) {
			$content .= sprintf(
				'<div class="wsscd-summary-item"><dt class="wsscd-summary-label">%s</dt><dd class="wsscd-summary-value">%s</dd></div>',
				esc_html( $label ),
				esc_html( $value )
			);
		}
		$content .= '</dl>';

		$args['content'] = $content;
		$args['variant'] = 'summary';
		return self::render( $args );
	}

	/**
	 * Render a highlight card (important message or callout).
	 *
	 * @since    1.0.0
	 * @param    string $message     The message text.
	 * @param    string $type        Highlight type: 'info', 'success', 'warning', 'danger'. Default 'info'.
	 * @param    array  $args        Additional arguments. See render() for full list.
	 * @return   string|void    Card HTML if $args['echo'] is false.
	 */
	public static function highlight( $message, $type = 'info', $args = array() ) {
		$icon_map = array(
			'info'    => 'info',
			'success' => 'yes-alt',
			'warning' => 'warning',
			'danger'  => 'dismiss',
		);

		$args['content'] = sprintf( '<p>%s</p>', esc_html( $message ) );
		$args['variant'] = 'highlight';
		$args['icon'] = isset( $icon_map[ $type ] ) ? $icon_map[ $type ] : $icon_map['info'];
		$args['classes'] = isset( $args['classes'] ) ? $args['classes'] : array();
		$args['classes'][] = 'wsscd-card--' . esc_attr( $type );

		return self::render( $args );
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
