<?php
/**
 * Badge Helper Class
 *
 * Provides centralized badge generation for consistent UI elements across the plugin.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
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
 * Badge Helper Class
 *
 * Centralized factory for generating badge HTML throughout the plugin.
 * Ensures consistent styling and makes badge updates easier.
 *
 * @since      1.0.0
 */
class WSSCD_Badge_Helper {

	/**
	 * Generate a generic badge.
	 *
	 * @since 1.0.0
	 * @param string $text Badge text content.
	 * @param string $class CSS class name (default: 'wsscd-badge').
	 * @param string $title Optional title attribute for tooltip.
	 * @return string Badge HTML.
	 */
	public static function badge( $text, $class = 'wsscd-badge', $title = '' ) {
		$title_attr = '';
		if ( ! empty( $title ) ) {
			$title_attr = ' title="' . esc_attr( $title ) . '"';
		}

		return sprintf(
			'<span class="%s"%s>%s</span>',
			esc_attr( $class ),
			$title_attr,
			esc_html( $text )
		);
	}

	/**
	 * Generate a PRO feature badge.
	 *
	 * @since 1.0.0
	 * @param string $title Optional title attribute for tooltip.
	 * @return string PRO badge HTML.
	 */
	public static function pro_badge( $title = '' ) {
		if ( empty( $title ) ) {
			$title = __( 'PRO Feature', 'smart-cycle-discounts' );
		}

		return self::badge(
			__( 'PRO', 'smart-cycle-discounts' ),
			'wsscd-pro-badge',
			$title
		);
	}

	/**
	 * Generate a FREE feature badge.
	 *
	 * @since 1.0.0
	 * @param string $title Optional title attribute for tooltip.
	 * @return string FREE badge HTML.
	 */
	public static function free_badge( $title = '' ) {
		if ( empty( $title ) ) {
			$title = __( 'Free Feature', 'smart-cycle-discounts' );
		}

		return self::badge(
			__( 'FREE', 'smart-cycle-discounts' ),
			'wsscd-free-badge',
			$title
		);
	}

	/**
	 * Generate a status badge.
	 *
	 * @since 1.0.0
	 * @param string $status Status slug (active, inactive, scheduled, expired, draft, paused).
	 * @param string $label Status label (optional, will be generated if not provided).
	 * @return string Status badge HTML.
	 */
	public static function status_badge( $status, $label = '' ) {
		if ( empty( $label ) ) {
			$labels = array(
				'active'    => __( 'Active', 'smart-cycle-discounts' ),
				'inactive'  => __( 'Inactive', 'smart-cycle-discounts' ),
				'scheduled' => __( 'Scheduled', 'smart-cycle-discounts' ),
				'expired'   => __( 'Expired', 'smart-cycle-discounts' ),
				'draft'     => __( 'Draft', 'smart-cycle-discounts' ),
				'paused'    => __( 'Paused', 'smart-cycle-discounts' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
		}

		return self::badge(
			$label,
			'wsscd-badge-status--' . esc_attr( $status )
		);
	}

	/**
	 * Generate a health badge.
	 *
	 * @since 1.0.0
	 * @param string $level Health level (healthy, warning, alert, info).
	 * @param string $label Health label (optional, will be generated if not provided).
	 * @return string Health badge HTML.
	 */
	public static function health_badge( $level, $label = '' ) {
		if ( empty( $label ) ) {
			$labels = array(
				'healthy' => __( 'Healthy', 'smart-cycle-discounts' ),
				'warning' => __( 'Warning', 'smart-cycle-discounts' ),
				'alert'   => __( 'Alert', 'smart-cycle-discounts' ),
				'info'    => __( 'Info', 'smart-cycle-discounts' ),
			);
			$label  = isset( $labels[ $level ] ) ? $labels[ $level ] : ucfirst( $level );
		}

		return self::badge(
			$label,
			'wsscd-badge-health--' . esc_attr( $level )
		);
	}

	/**
	 * Generate a priority badge.
	 *
	 * @since 1.0.0
	 * @param int $priority Priority level (1-5).
	 * @return string Priority badge HTML.
	 */
	public static function priority_badge( $priority ) {
		return self::badge(
			(string) $priority,
			'wsscd-priority-badge wsscd-priority-' . absint( $priority )
		);
	}

	/**
	 * Generate a free shipping badge.
	 *
	 * @since 1.2.0
	 * @param string $title Optional title attribute for tooltip.
	 * @param string $methods_info Optional methods info to display (e.g., "All methods" or "2 methods").
	 * @return string Free shipping badge HTML.
	 */
	public static function free_shipping_badge( $title = '', $methods_info = '' ) {
		if ( empty( $title ) ) {
			$title = __( 'Free shipping enabled for this campaign', 'smart-cycle-discounts' );
		}

		$text = __( 'Free Shipping', 'smart-cycle-discounts' );
		if ( ! empty( $methods_info ) ) {
			$text .= ' (' . $methods_info . ')';
		}

		return self::badge(
			$text,
			'wsscd-free-shipping-badge',
			$title
		);
	}

	/**
	 * Generate a product selection badge.
	 *
	 * @since 1.0.0
	 * @param string $text Badge text content.
	 * @param string $selection_type Selection type for styling (all_products, specific_products, random_products, smart_selection).
	 * @param string $title Optional title attribute for tooltip.
	 * @param bool   $is_empty Whether this is an empty/pending state.
	 * @return string Product badge HTML.
	 */
	public static function product_badge( $text, $selection_type = 'specific_products', $title = '', $is_empty = false ) {
		$classes = array( 'wsscd-badge', 'wsscd-badge-product' );

		// Add selection type class
		$classes[] = 'wsscd-badge-product--' . esc_attr( $selection_type );

		// Add empty state class if needed
		if ( $is_empty ) {
			$classes[] = 'wsscd-badge-product--empty';
		}

		return self::badge(
			$text,
			implode( ' ', $classes ),
			$title
		);
	}
}
