<?php
/**
 * Test Campaign Data Generator
 *
 * Generates test campaign data for all possible discount types, product selections,
 * and scheduling configurations.
 *
 * @package Smart_Cycle_Discounts\Tests\Utilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test Campaign Data Generator Class
 *
 * Provides methods to generate comprehensive test data for campaign creation tests.
 */
class Test_Campaign_Data_Generator {

	/**
	 * Get base campaign data with common fields.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_base_campaign_data( $overrides = array() ) {
		$future_timestamp = current_time( 'timestamp' ) + ( 2 * HOUR_IN_SECONDS );
		$end_timestamp    = $future_timestamp + ( 7 * DAY_IN_SECONDS );

		$defaults = array(
			'name'                   => 'Test Campaign ' . uniqid(),
			'description'            => 'Test campaign description',
			'status'                 => 'draft',
			'priority'               => 3,
			'start_date'             => gmdate( 'Y-m-d', $future_timestamp ),
			'start_time'             => gmdate( 'H:i', $future_timestamp ),
			'end_date'               => gmdate( 'Y-m-d', $end_timestamp ),
			'end_time'               => '23:59',
			'start_type'             => 'scheduled',
			'timezone'               => 'UTC',
			'created_by'             => get_current_user_id(),
			'product_selection_type' => 'all_products',
			'apply_to'               => 'per_item',
			'stack_with_others'      => false,
			'allow_coupons'          => true,
			'apply_to_sale_items'    => true,
			'badge_enabled'          => false,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Get percentage discount campaign data.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_percentage_discount_data( $overrides = array() ) {
		$discount_data = array(
			'discount_type'             => 'percentage',
			'discount_value'            => 25,
			'discount_value_percentage' => 25,
		);

		return self::get_base_campaign_data( array_merge( $discount_data, $overrides ) );
	}

	/**
	 * Get fixed discount campaign data.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_fixed_discount_data( $overrides = array() ) {
		$discount_data = array(
			'discount_type'        => 'fixed',
			'discount_value'       => 10,
			'discount_value_fixed' => 10,
		);

		return self::get_base_campaign_data( array_merge( $discount_data, $overrides ) );
	}

	/**
	 * Get tiered discount campaign data.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_tiered_discount_data( $overrides = array() ) {
		$discount_data = array(
			'discount_type'  => 'tiered',
			'discount_value' => 0, // Tiered uses tiers array instead
			'tiers'          => array(
				array(
					'min_quantity' => 1,
					'max_quantity' => 5,
					'discount'     => 10,
				),
				array(
					'min_quantity' => 6,
					'max_quantity' => 10,
					'discount'     => 20,
				),
				array(
					'min_quantity' => 11,
					'max_quantity' => 999,
					'discount'     => 30,
				),
			),
		);

		return self::get_base_campaign_data( array_merge( $discount_data, $overrides ) );
	}

	/**
	 * Get BOGO discount campaign data.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_bogo_discount_data( $overrides = array() ) {
		$discount_data = array(
			'discount_type'  => 'bogo',
			'discount_value' => 0, // BOGO uses bogo_config instead
			'bogo_config'    => array(
				'buy_quantity'     => 1,
				'get_quantity'     => 1,
				'discount_percent' => 100,
			),
		);

		return self::get_base_campaign_data( array_merge( $discount_data, $overrides ) );
	}

	/**
	 * Get spend threshold discount campaign data.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_spend_threshold_discount_data( $overrides = array() ) {
		$discount_data = array(
			'discount_type'  => 'spend_threshold',
			'discount_value' => 0, // Spend threshold uses thresholds array instead
			'threshold_mode' => 'percentage',
			'thresholds'     => array(
				array(
					'min_spend' => 50,
					'discount'  => 10,
				),
				array(
					'min_spend' => 100,
					'discount'  => 20,
				),
				array(
					'min_spend' => 200,
					'discount'  => 30,
				),
			),
		);

		return self::get_base_campaign_data( array_merge( $discount_data, $overrides ) );
	}

	/**
	 * Get immediate activation campaign data.
	 *
	 * @param string $discount_type Discount type.
	 * @param array  $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_immediate_campaign_data( $discount_type = 'percentage', $overrides = array() ) {
		$immediate_data = array(
			'start_type' => 'immediate',
			'start_date' => gmdate( 'Y-m-d' ),
			'start_time' => gmdate( 'H:i' ),
		);

		$method_name = "get_{$discount_type}_discount_data";
		if ( method_exists( __CLASS__, $method_name ) ) {
			return self::$method_name( array_merge( $immediate_data, $overrides ) );
		}

		return self::get_base_campaign_data( array_merge( $immediate_data, $overrides ) );
	}

	/**
	 * Get specific products campaign data.
	 *
	 * @param array $product_ids Product IDs to include.
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_specific_products_data( $product_ids = array(), $overrides = array() ) {
		$products_data = array(
			'product_selection_type' => 'specific_products',
			'product_ids'            => ! empty( $product_ids ) ? $product_ids : array( 1, 2, 3 ),
		);

		return array_merge( $products_data, $overrides );
	}

	/**
	 * Get random products campaign data.
	 *
	 * @param int   $count Random product count.
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_random_products_data( $count = 10, $overrides = array() ) {
		$products_data = array(
			'product_selection_type' => 'random_products',
			'random_count'           => $count,
		);

		return array_merge( $products_data, $overrides );
	}

	/**
	 * Get smart selection campaign data.
	 *
	 * @param string $criteria Smart selection criteria.
	 * @param array  $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_smart_selection_data( $criteria = 'best_sellers', $overrides = array() ) {
		$products_data = array(
			'product_selection_type' => 'smart_selection',
			'smart_criteria'         => $criteria,
		);

		return array_merge( $products_data, $overrides );
	}

	/**
	 * Get edge case: minimum percentage discount.
	 *
	 * @return array Campaign data array.
	 */
	public static function get_minimum_percentage_discount() {
		return self::get_percentage_discount_data(
			array(
				'discount_value'            => 0.01,
				'discount_value_percentage' => 0.01,
			)
		);
	}

	/**
	 * Get edge case: maximum percentage discount.
	 *
	 * @return array Campaign data array.
	 */
	public static function get_maximum_percentage_discount() {
		return self::get_percentage_discount_data(
			array(
				'discount_value'            => 100,
				'discount_value_percentage' => 100,
			)
		);
	}

	/**
	 * Get edge case: minimum fixed discount.
	 *
	 * @return array Campaign data array.
	 */
	public static function get_minimum_fixed_discount() {
		return self::get_fixed_discount_data(
			array(
				'discount_value'       => 0.01,
				'discount_value_fixed' => 0.01,
			)
		);
	}

	/**
	 * Get campaign with all usage limits set.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_campaign_with_usage_limits( $overrides = array() ) {
		$limits_data = array(
			'usage_limit_per_customer' => 3,
			'total_usage_limit'        => 100,
			'lifetime_usage_cap'       => 500,
		);

		return self::get_percentage_discount_data( array_merge( $limits_data, $overrides ) );
	}

	/**
	 * Get campaign with minimum order requirements.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_campaign_with_minimums( $overrides = array() ) {
		$minimums_data = array(
			'minimum_quantity'     => 2,
			'minimum_order_amount' => 50.00,
		);

		return self::get_percentage_discount_data( array_merge( $minimums_data, $overrides ) );
	}

	/**
	 * Get campaign with badge enabled.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_campaign_with_badge( $overrides = array() ) {
		$badge_data = array(
			'badge_enabled'   => true,
			'badge_text'      => 'SALE!',
			'badge_bg_color'  => '#e74c3c',
			'badge_text_color'=> '#ffffff',
		);

		return self::get_percentage_discount_data( array_merge( $badge_data, $overrides ) );
	}

	/**
	 * Get campaign that stacks with others.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_stackable_campaign( $overrides = array() ) {
		$stackable_data = array(
			'stack_with_others' => true,
			'allow_coupons'     => true,
		);

		return self::get_percentage_discount_data( array_merge( $stackable_data, $overrides ) );
	}

	/**
	 * Get campaign that excludes sale items.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_campaign_excluding_sale_items( $overrides = array() ) {
		$exclude_data = array(
			'apply_to_sale_items' => false,
		);

		return self::get_percentage_discount_data( array_merge( $exclude_data, $overrides ) );
	}

	/**
	 * Get campaign with max discount amount cap.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return array Campaign data array.
	 */
	public static function get_campaign_with_max_discount_cap( $overrides = array() ) {
		$cap_data = array(
			'max_discount_amount' => 50.00,
		);

		return self::get_percentage_discount_data( array_merge( $cap_data, $overrides ) );
	}

	/**
	 * Get past date campaign (should fail validation).
	 *
	 * @return array Campaign data array.
	 */
	public static function get_past_date_campaign() {
		$past_timestamp = current_time( 'timestamp' ) - ( 2 * DAY_IN_SECONDS );

		return self::get_percentage_discount_data(
			array(
				'start_date' => gmdate( 'Y-m-d', $past_timestamp ),
				'start_time' => gmdate( 'H:i', $past_timestamp ),
			)
		);
	}

	/**
	 * Get campaign with invalid end date (before start date).
	 *
	 * @return array Campaign data array.
	 */
	public static function get_invalid_date_range_campaign() {
		$future_timestamp = current_time( 'timestamp' ) + ( 2 * DAY_IN_SECONDS );
		$past_timestamp   = $future_timestamp - ( 7 * DAY_IN_SECONDS );

		return self::get_percentage_discount_data(
			array(
				'start_date' => gmdate( 'Y-m-d', $future_timestamp ),
				'start_time' => gmdate( 'H:i', $future_timestamp ),
				'end_date'   => gmdate( 'Y-m-d', $past_timestamp ),
				'end_time'   => '23:59',
			)
		);
	}

	/**
	 * Get all discount types for parameterized testing.
	 *
	 * @return array Array of discount type configurations.
	 */
	public static function get_all_discount_types() {
		return array(
			'percentage'      => self::get_percentage_discount_data( array( 'name' => 'Test Percentage Discount' ) ),
			'fixed'           => self::get_fixed_discount_data( array( 'name' => 'Test Fixed Discount' ) ),
			'tiered'          => self::get_tiered_discount_data( array( 'name' => 'Test Tiered Discount' ) ),
			'bogo'            => self::get_bogo_discount_data( array( 'name' => 'Test BOGO Discount' ) ),
			'spend_threshold' => self::get_spend_threshold_discount_data( array( 'name' => 'Test Spend Threshold' ) ),
		);
	}

	/**
	 * Get all product selection types for parameterized testing.
	 *
	 * @return array Array of product selection configurations.
	 */
	public static function get_all_product_selections() {
		return array(
			'all_products'      => array( 'product_selection_type' => 'all_products' ),
			'specific_products' => self::get_specific_products_data(),
			'random_products'   => self::get_random_products_data( 15 ),
			'smart_selection'   => self::get_smart_selection_data( 'best_sellers' ),
		);
	}

	/**
	 * Generate test data matrix: discount types × product selections.
	 *
	 * Creates a comprehensive test matrix combining all discount types
	 * with all product selection methods.
	 *
	 * @return array Multi-dimensional array of test configurations.
	 */
	public static function get_comprehensive_test_matrix() {
		$discount_types      = self::get_all_discount_types();
		$product_selections  = self::get_all_product_selections();
		$test_matrix         = array();

		foreach ( $discount_types as $discount_key => $discount_config ) {
			foreach ( $product_selections as $product_key => $product_config ) {
				$test_key              = "{$discount_key}_{$product_key}";
				$test_matrix[ $test_key ] = array_merge( $discount_config, $product_config );
				$test_matrix[ $test_key ]['name'] = "Test {$discount_key} with {$product_key}";
			}
		}

		return $test_matrix;
	}
}
