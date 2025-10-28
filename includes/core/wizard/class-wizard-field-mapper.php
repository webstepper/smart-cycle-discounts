<?php
/**
 * Wizard Field Mapper
 *
 * Maps form field names to standardized internal field names.
 * Eliminates the need for multiple fallbacks and ensures consistency.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Wizard Field Mapper Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Wizard_Field_Mapper {

	/**
	 * Field mapping configuration
	 *
	 * Maps form field names to standardized internal names
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static array $field_map = array(
		// Products step fields
		'selected_products'         => 'product_ids',
		'product_ids'               => 'product_ids',
		'selected_product_ids'      => 'product_ids',
		'product_selection_type'    => 'product_selection_type',
		'random_count'              => 'random_count',
		'categories'                => 'category_ids',
		'category_ids'              => 'category_ids',
		'product_categories'        => 'category_ids',
		'tags'                      => 'tag_ids',
		'tag_ids'                   => 'tag_ids',
		'product_tags'              => 'tag_ids',
		'conditions'                => 'conditions',
		'conditions_logic'          => 'conditions_logic',

		// Basic step fields - no mapping needed since forms now use database column names directly

		// Discount step fields - Core discount configuration
		'discount_type'             => 'discount_type',
		'discount_value'            => 'discount_value',
		'discount_value_percentage' => 'discount_value_percentage', // Keep separate for proper persistence
		'discount_value_fixed'      => 'discount_value_fixed',        // Keep separate for proper persistence
		'discount_tiers'            => 'tiers',
		'tier_mode'                 => 'tier_mode',
		'minimum_quantity'          => 'minimum_quantity',
		'bogo_type'                 => 'bogo_type',
		'bogo_buy_quantity'         => 'bogo_buy_quantity',
		'bogo_get_quantity'         => 'bogo_get_quantity',
		'spend_threshold'           => 'spend_threshold',

		// Discount step fields - Usage Limits
		'usage_limit_per_customer'  => 'usage_limit_per_customer',
		'total_usage_limit'         => 'total_usage_limit',
		'lifetime_usage_cap'        => 'lifetime_usage_cap',

		// Discount step fields - Application Rules
		'apply_to'                  => 'apply_to',
		'max_discount_amount'       => 'max_discount_amount',
		'minimum_order_amount'      => 'minimum_order_amount',

		// Discount step fields - Combination Policy
		'stack_with_others'         => 'stack_with_others',
		'allow_coupons'             => 'allow_coupons',
		'apply_to_sale_items'       => 'apply_to_sale_items',

		// Discount step fields - Badge Settings
		'badge_enabled'             => 'badge_enabled',
		'badge_text'                => 'badge_text',
		'badge_bg_color'            => 'badge_bg_color',
		'badge_text_color'          => 'badge_text_color',
		'badge_position'            => 'badge_position',

		// Discount step fields - Additional type-specific fields
		'conditions'                => 'conditions',
		'conditions_logic'          => 'conditions_logic',
		'tiers'                     => 'tiers',
		'tier_mode'                 => 'tier_mode',
		'tier_type'                 => 'tier_type',
		'bogo_discount_percentage'  => 'bogo_discount_percentage',
		'bogo_apply_to'             => 'bogo_apply_to',
		'threshold_mode'            => 'threshold_mode',
		'thresholds'                => 'thresholds',

		// Schedule step fields
		'rotation_enabled'          => 'rotation_enabled',
		'rotation_interval'         => 'rotation_interval',
		'rotation_unit'             => 'rotation_unit',
		'start_date'                => 'start_date',
		'end_date'                  => 'end_date',
	);

	/**
	 * Map form data to standardized field names
	 *
	 * @since  1.0.0
	 * @param  array $form_data Raw form data.
	 * @return array Mapped data with standardized field names
	 */
	public static function map_form_data( array $form_data ): array {
		$mapped_data = array();

		foreach ( $form_data as $field_name => $value ) {
			$mapped_name                 = self::get_mapped_name( $field_name );
			$mapped_data[ $mapped_name ] = $value;
		}

		return $mapped_data;
	}

	/**
	 * Get mapped field name
	 *
	 * @since  1.0.0
	 * @param  string $field_name Original field name.
	 * @return string Mapped field name
	 */
	public static function get_mapped_name( string $field_name ): string {
		return self::$field_map[ $field_name ] ?? $field_name;
	}

	/**
	 * Map step data for validation
	 *
	 * @since  1.0.0
	 * @param  string $step      Step name.
	 * @param  array  $step_data Step data to map.
	 * @return array Mapped step data
	 */
	public static function map_step_data( string $step, array $step_data ): array {
		// Map the data first
		$mapped_data = self::map_form_data( $step_data );

		// Apply step-specific transformations
		switch ( $step ) {
			case 'products':
				// Ensure product_ids is always an array
				if ( isset( $mapped_data['product_ids'] ) && ! is_array( $mapped_data['product_ids'] ) ) {
					$mapped_data['product_ids'] = array( $mapped_data['product_ids'] );
				}

				// Ensure category_ids is always an array
				if ( isset( $mapped_data['category_ids'] ) && ! is_array( $mapped_data['category_ids'] ) ) {
					$mapped_data['category_ids'] = array( $mapped_data['category_ids'] );
				}

				// Ensure tag_ids is always an array
				if ( isset( $mapped_data['tag_ids'] ) && ! is_array( $mapped_data['tag_ids'] ) ) {
					$mapped_data['tag_ids'] = array( $mapped_data['tag_ids'] );
				}

				// Ensure conditions is always an array
				if ( isset( $mapped_data['conditions'] ) && ! is_array( $mapped_data['conditions'] ) ) {
					$mapped_data['conditions'] = array();
				}
				break;

			case 'discounts':
				// Ensure arrays are always arrays (now uses combined format)
				$array_fields = array( 'tiers', 'conditions', 'thresholds' );
				foreach ( $array_fields as $field ) {
					if ( isset( $mapped_data[ $field ] ) && ! is_array( $mapped_data[ $field ] ) ) {
						$mapped_data[ $field ] = array();
					}
				}

				// Ensure boolean fields are boolean
				$boolean_fields = array( 'allow_coupons', 'apply_to_sale_items', 'badge_enabled' );
				foreach ( $boolean_fields as $field ) {
					if ( isset( $mapped_data[ $field ] ) ) {
						$mapped_data[ $field ] = filter_var( $mapped_data[ $field ], FILTER_VALIDATE_BOOLEAN );
					}
				}

				// Ensure numeric fields are numeric
				$numeric_fields = array(
					'discount_value',
					'discount_value_percentage',
					'discount_value_fixed',
					'usage_limit_per_customer',
					'total_usage_limit',
					'lifetime_usage_cap',
					'max_discount_amount',
					'minimum_quantity',
					'minimum_order_amount',
					'bogo_buy_quantity',
					'bogo_get_quantity',
					'bogo_discount_percentage',
				);
				foreach ( $numeric_fields as $field ) {
					if ( isset( $mapped_data[ $field ] ) && $mapped_data[ $field ] !== '' ) {
						$mapped_data[ $field ] = is_numeric( $mapped_data[ $field ] ) ? $mapped_data[ $field ] : '';
					}
				}

				// Preserve all fields from step_data that aren't already mapped
				// This ensures no data is lost during the mapping process
				foreach ( $step_data as $key => $value ) {
					if ( ! isset( $mapped_data[ $key ] ) ) {
						$mapped_data[ $key ] = $value;
					}
				}
				break;
		}

		return $mapped_data;
	}

	/**
	 * Get required fields for a step
	 *
	 * @since  1.0.0
	 * @param  string $step Step name.
	 * @return array Array of required field names (mapped names)
	 */
	public static function get_required_fields( string $step ): array {
		$required_fields = array(
			'basic'     => array( 'name' ),
			'products'  => array( 'product_selection_type' ),
			'discounts' => array( 'discount_type', 'discount_value' ),
			'schedule'  => array( 'rotation_enabled' ),
			'review'    => array(),
		);

		return $required_fields[ $step ] ?? array();
	}
}
