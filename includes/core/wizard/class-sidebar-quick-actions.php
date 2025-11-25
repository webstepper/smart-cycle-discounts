<?php
/**
 * Sidebar Quick Actions
 *
 * Provides one-click templates and shortcuts for common campaign patterns.
 * Helps users set up campaigns faster with pre-configured templates.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
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
 * Sidebar Quick Actions
 *
 * @since 1.0.0
 */
class SCD_Sidebar_Quick_Actions {

	/**
	 * Get quick action templates for step
	 *
	 * @since  1.0.0
	 * @param  string $step Step identifier.
	 * @return array        Template configurations
	 */
	public static function get_templates( $step ) {
		$templates = array(
			'basic'     => self::get_basic_templates(),
			'products'  => self::get_products_templates(),
			'discounts' => self::get_discounts_templates(),
			'schedule'  => self::get_schedule_templates(),
		);

		$step_templates = isset( $templates[ $step ] ) ? $templates[ $step ] : array();

		return apply_filters( "scd_sidebar_quick_actions_{$step}", $step_templates, $step );
	}

	/**
	 * Get basic step templates
	 *
	 * @since  1.0.0
	 * @return array Template configurations
	 */
	private static function get_basic_templates() {
		return array(
			array(
				'id'          => 'flash-sale',
				'name'        => __( 'Flash Sale', 'smart-cycle-discounts' ),
				'icon'        => 'superhero',
				'description' => __( 'High-priority time-sensitive sale', 'smart-cycle-discounts' ),
				'config'      => array(
					'priority' => 4,
				),
			),
			array(
				'id'          => 'seasonal-promo',
				'name'        => __( 'Seasonal Promotion', 'smart-cycle-discounts' ),
				'icon'        => 'calendar-alt',
				'description' => __( 'Regular seasonal campaign', 'smart-cycle-discounts' ),
				'config'      => array(
					'priority' => 3,
				),
			),
			array(
				'id'          => 'clearance',
				'name'        => __( 'Clearance Sale', 'smart-cycle-discounts' ),
				'icon'        => 'tag',
				'description' => __( 'Low-priority ongoing clearance', 'smart-cycle-discounts' ),
				'config'      => array(
					'priority' => 2,
				),
			),
		);
	}

	/**
	 * Get products step templates
	 *
	 * @since  1.0.0
	 * @return array Template configurations
	 */
	private static function get_products_templates() {
		return array(
			array(
				'id'          => 'clearance-overstock',
				'name'        => __( 'Clearance (Overstocked)', 'smart-cycle-discounts' ),
				'icon'        => 'tag',
				'description' => __( 'Target products with high inventory', 'smart-cycle-discounts' ),
				'config'      => array(
					'product_selection_type' => 'smart_selection',
					'conditions'             => array(
						array(
							'condition_type' => 'stock_quantity',
							'operator'       => '>',
							'value'          => '50',
							'mode'           => 'include',
						),
					),
					'conditions_logic'       => 'all',
				),
			),
			array(
				'id'          => 'premium-products',
				'name'        => __( 'Premium Products', 'smart-cycle-discounts' ),
				'icon'        => 'star-filled',
				'description' => __( 'High-value items only (price > $100)', 'smart-cycle-discounts' ),
				'config'      => array(
					'product_selection_type' => 'smart_selection',
					'conditions'             => array(
						array(
							'condition_type' => 'regular_price',
							'operator'       => '>',
							'value'          => '100',
							'mode'           => 'include',
						),
					),
					'conditions_logic'       => 'all',
				),
			),
			array(
				'id'          => 'exclude-sale-items',
				'name'        => __( 'Exclude Sale Items', 'smart-cycle-discounts' ),
				'icon'        => 'shield',
				'description' => __( 'Prevent double-discounting', 'smart-cycle-discounts' ),
				'config'      => array(
					'product_selection_type' => 'smart_selection',
					'conditions'             => array(
						array(
							'condition_type' => 'on_sale',
							'operator'       => '!=',
							'value'          => 'yes',
							'mode'           => 'include',
						),
					),
					'conditions_logic'       => 'all',
				),
			),
			array(
				'id'          => 'in-stock-only',
				'name'        => __( 'In Stock Only', 'smart-cycle-discounts' ),
				'icon'        => 'yes',
				'description' => __( 'Only discount available products', 'smart-cycle-discounts' ),
				'config'      => array(
					'product_selection_type' => 'smart_selection',
					'conditions'             => array(
						array(
							'condition_type' => 'stock_status',
							'operator'       => '=',
							'value'          => 'instock',
							'mode'           => 'include',
						),
					),
					'conditions_logic'       => 'all',
				),
			),
		);
	}

	/**
	 * Get discounts step templates
	 *
	 * @since  1.0.0
	 * @return array Template configurations
	 */
	private static function get_discounts_templates() {
		return array(
			array(
				'id'          => 'standard-percentage',
				'name'        => __( 'Standard Discount (20%)', 'smart-cycle-discounts' ),
				'icon'        => 'tag',
				'description' => __( 'Common percentage discount', 'smart-cycle-discounts' ),
				'config'      => array(
					'discount_type'  => 'percentage',
					'discount_value' => '20',
				),
			),
			array(
				'id'          => 'bogo-50',
				'name'        => __( 'Buy 2 Get 1 at 50%', 'smart-cycle-discounts' ),
				'icon'        => 'products',
				'description' => __( 'Safer BOGO with partial discount', 'smart-cycle-discounts' ),
				'config'      => array(
					'discount_type' => 'bogo',
					'bogo_config'   => array(
						'buy_quantity'     => '2',
						'get_quantity'     => '1',
						'discount_percent' => '50',
					),
				),
			),
			array(
				'id'          => 'volume-incentive',
				'name'        => __( 'Volume Incentive', 'smart-cycle-discounts' ),
				'icon'        => 'chart-line',
				'description' => __( 'Reward bulk purchases', 'smart-cycle-discounts' ),
				'config'      => array(
					'discount_type' => 'tiered',
					'tiers'         => array(
						array(
							'min_quantity'   => '3',
							'discount_value' => '10',
						),
						array(
							'min_quantity'   => '5',
							'discount_value' => '15',
						),
						array(
							'min_quantity'   => '10',
							'discount_value' => '20',
						),
					),
				),
			),
			array(
				'id'          => 'free-shipping-threshold',
				'name'        => __( 'Spend $100 Get 15% Off', 'smart-cycle-discounts' ),
				'icon'        => 'money',
				'description' => __( 'Increase order value', 'smart-cycle-discounts' ),
				'config'      => array(
					'discount_type'  => 'spend_threshold',
					'spend_amount'   => '100',
					'discount_value' => '15',
				),
			),
		);
	}

	/**
	 * Get schedule step templates
	 *
	 * @since  1.0.0
	 * @return array Template configurations
	 */
	private static function get_schedule_templates() {
		return array(
			array(
				'id'          => 'flash-24h',
				'name'        => __( '24-Hour Flash Sale', 'smart-cycle-discounts' ),
				'icon'        => 'superhero',
				'description' => __( 'Tomorrow, runs for 24 hours', 'smart-cycle-discounts' ),
				'config'      => array(
					'start_date' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
					'end_date'   => gmdate( 'Y-m-d', strtotime( '+2 days' ) ),
				),
			),
			array(
				'id'          => 'weekend-sale',
				'name'        => __( 'This Weekend', 'smart-cycle-discounts' ),
				'icon'        => 'calendar',
				'description' => __( 'Friday through Sunday', 'smart-cycle-discounts' ),
				'config'      => array(
					'start_date' => self::get_next_friday(),
					'end_date'   => self::get_next_sunday(),
				),
			),
			array(
				'id'          => 'weekly-recurring',
				'name'        => __( 'Every Weekend', 'smart-cycle-discounts' ),
				'icon'        => 'backup',
				'description' => __( 'Recurring Friday-Sunday', 'smart-cycle-discounts' ),
				'config'      => array(
					'recurring_type' => 'weekly',
					'recurring_days' => array( 'friday', 'saturday', 'sunday' ),
				),
			),
			array(
				'id'          => 'ongoing',
				'name'        => __( 'Always On', 'smart-cycle-discounts' ),
				'icon'        => 'yes',
				'description' => __( 'No start/end dates', 'smart-cycle-discounts' ),
				'config'      => array(
					'start_date' => '',
					'end_date'   => '',
				),
			),
		);
	}

	/**
	 * Get next Friday's date
	 *
	 * @since  1.0.0
	 * @return string Date in Y-m-d format
	 */
	private static function get_next_friday() {
		$now = new DateTime( 'now', wp_timezone() );

		if ( 5 >= (int) $now->format( 'N' ) ) {
			// Before or on Friday this week
			$now->modify( 'this friday' );
		} else {
			// Weekend, get next Friday
			$now->modify( 'next friday' );
		}

		return $now->format( 'Y-m-d' );
	}

	/**
	 * Get next Sunday's date
	 *
	 * @since  1.0.0
	 * @return string Date in Y-m-d format
	 */
	private static function get_next_sunday() {
		$now = new DateTime( 'now', wp_timezone() );

		if ( 7 > (int) $now->format( 'N' ) ) {
			// Before Sunday this week
			$now->modify( 'this sunday' );
		} else {
			// Already Sunday, get next Sunday
			$now->modify( 'next sunday' );
		}

		return $now->format( 'Y-m-d' );
	}

	/**
	 * Get template by ID
	 *
	 * @since  1.0.0
	 * @param  string $step        Step identifier.
	 * @param  string $template_id Template ID.
	 * @return array|null          Template configuration or null
	 */
	public static function get_template( $step, $template_id ) {
		$templates = self::get_templates( $step );

		foreach ( $templates as $template ) {
			if ( $template_id === $template['id'] ) {
				return $template;
			}
		}

		return null;
	}
}
