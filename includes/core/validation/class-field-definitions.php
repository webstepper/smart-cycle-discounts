<?php
/**
 * Field Definitions Class
 *
 * Single source of truth for all field definitions, validation rules,
 * and rendering logic. Follows WordPress coding standards and DRY principles.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SCD_Field_Definitions Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/fields
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Field_Definitions {

	/**
	 * Field schema definitions
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $schemas = array();

	/**
	 * Initialize the field schemas
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::define_basic_fields();
		self::define_products_fields();
		self::define_discounts_fields();
		self::define_schedule_fields();
		self::define_review_fields();
	}

	/**
	 * Define basic step fields
	 *
	 * @since 1.0.0
	 */
	private static function define_basic_fields() {
		self::$schemas['basic'] = array(
			'name'        => array(
				'type'        => 'text',
				'label'       => __( 'Campaign Name', 'smart-cycle-discounts' ),
				'required'    => true,
				'min_length'  => 3,
				'max_length'  => 255,
				'default'     => '',
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_text_length' ),
				'attributes'  => array(
					'placeholder' => __( 'Enter a descriptive campaign name', 'smart-cycle-discounts' ),
				),
				'description' => __( 'A clear name helps you identify this campaign later', 'smart-cycle-discounts' ),
				'field_name'  => 'name', // Maps to database column
			),
			'description' => array(
				'type'       => 'textarea',
				'label'      => __( 'Description', 'smart-cycle-discounts' ),
				'required'   => false,
				'max_length' => 1000,
				'default'    => '',
				'sanitizer'  => 'sanitize_textarea_field',
				'validator'  => array( __CLASS__, 'validate_text_length' ),
				'attributes' => array(
					'rows'        => 4,
					'placeholder' => __( 'Describe the purpose of this campaign (optional)', 'smart-cycle-discounts' ),
				),
				'field_name' => 'description',
			),
			'priority'    => array(
				'type'        => 'number',
				'label'       => __( 'Priority', 'smart-cycle-discounts' ),
				'required'    => true,
				'min'         => 1,
				'max'         => 10,
				'default'     => 5,
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'attributes'  => array(
					'step' => 1,
				),
				'description' => __( 'Lower numbers = higher priority (1 = highest, 10 = lowest)', 'smart-cycle-discounts' ),
				'field_name'  => 'priority',
			),
		);
	}

	/**
	 * Define products step fields
	 *
	 * @since 1.0.0
	 */
	private static function define_products_fields() {
		self::$schemas['products'] = array(
			'product_selection_type' => array(
				'type'       => 'radio',
				'label'      => __( 'Product Selection Method', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => 'all_products',
				'options'    => array(
					'all_products'      => __( 'All Products', 'smart-cycle-discounts' ),
					'random_products'   => __( 'Random Products', 'smart-cycle-discounts' ),
					'specific_products' => __( 'Specific Products', 'smart-cycle-discounts' ),
					'smart_selection'   => __( 'Smart Selection', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'field_name' => 'product_selection_type',
			),
			'category_ids'           => array(
				'type'       => 'complex',
				'label'      => __( 'Categories', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => array( 'all' ),
				'sanitizer'  => array( __CLASS__, 'sanitize_array_values' ),
				'validator'  => array( __CLASS__, 'validate_category_ids' ),
				'handler'    => 'SCD.Modules.Products.Picker',
				'methods'    => array(
					'collect'  => 'getCategoryIds',
					'populate' => 'setCategoryIds',
				),
				'attributes' => array(
					'multiple' => true,
					'class'    => 'scd-category-select',
				),
				'field_name' => 'category_ids',
			),
			'product_ids'            => array(
				'type'        => 'hidden',
				'label'       => __( 'Products', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => '',
				'sanitizer'   => array( __CLASS__, 'sanitize_product_ids_csv' ),
				'validator'   => array( __CLASS__, 'validate_product_ids' ),
				'selector'    => '#scd-product-ids-hidden',
				'conditional' => array(
					'field' => 'product_selection_type',
					'value' => 'specific_products',
				),
				'field_name'  => 'product_ids',
			),
			'random_count'           => array(
				'type'        => 'number',
				'label'       => __( 'Random Product Count', 'smart-cycle-discounts' ),
				'required'    => true,
				'min'         => 1,
				'max'         => 100,
				'default'     => 10,
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'product_selection_type',
					'value' => 'random_products',
				),
				'field_name'  => 'random_count',
			),
			'smart_criteria'         => array(
				'type'        => 'radio',
				'label'       => __( 'Smart Selection Criteria', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => '',
				'options'     => array(
					'best_sellers' => __( 'Best Sellers', 'smart-cycle-discounts' ),
					'featured'     => __( 'Featured Products', 'smart-cycle-discounts' ),
					'low_stock'    => __( 'Low Stock', 'smart-cycle-discounts' ),
					'new_arrivals' => __( 'New Arrivals', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'conditional' => array(
					'field' => 'product_selection_type',
					'value' => 'smart_selection',
				),
				'field_name'  => 'smart_criteria',
			),
			'conditions'             => array(
				'type'       => 'nested_array',
				'label'      => __( 'Product Conditions', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => array(),
				'sanitizer'  => array( __CLASS__, 'sanitize_conditions' ),
				'validator'  => array( __CLASS__, 'validate_conditions' ),
				'field_name' => 'conditions',
			),
			'conditions_logic'       => array(
				'type'       => 'radio',
				'label'      => __( 'Conditions Logic', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => 'all',
				'options'    => array(
					'all' => __( 'All conditions (AND)', 'smart-cycle-discounts' ),
					'any' => __( 'Any condition (OR)', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'field_name' => 'conditions_logic',
			),
		);
	}

	/**
	 * Define discounts step fields
	 *
	 * @since 1.0.0
	 */
	private static function define_discounts_fields() {
		self::$schemas['discounts'] = array(
			'discount_type'             => array(
				'type'       => 'select',
				'label'      => __( 'Discount Type', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => 'percentage',
				'options'    => array(
					'percentage'      => __( 'Percentage Discount', 'smart-cycle-discounts' ),
					'fixed'           => __( 'Fixed Amount', 'smart-cycle-discounts' ),
					'tiered'          => __( 'Tiered Discount', 'smart-cycle-discounts' ),
					'bogo'            => __( 'Buy One Get One', 'smart-cycle-discounts' ),
					'spend_threshold' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'field_name' => 'discount_type',
			),
			'discount_value_percentage' => array(
				'type'        => 'number',
				'label'       => __( 'Percentage Value', 'smart-cycle-discounts' ),
				'required'    => true,
				'min'         => SCD_Validation_Rules::PERCENTAGE_MIN,
				'max'         => SCD_Validation_Rules::PERCENTAGE_MAX,
				'default'     => 10,
				'sanitizer'   => 'floatval',
				'validator'   => array( __CLASS__, 'validate_percentage' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'percentage',
				),
				'attributes'  => array(
					'step' => 0.01,
				),
				'field_name'  => 'discount_value_percentage',
			),
			'discount_value_fixed'      => array(
				'type'        => 'number',
				'label'       => __( 'Fixed Amount', 'smart-cycle-discounts' ),
				'required'    => true,
				'min'         => SCD_Validation_Rules::FIXED_MIN,
				'max'         => SCD_Validation_Rules::FIXED_MAX,
				'default'     => 5,
				'sanitizer'   => 'floatval',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'fixed',
				),
				'attributes'  => array(
					'step' => 0.01,
				),
				'field_name'  => 'discount_value_fixed',
			),
			'tiers'                     => array(
				'type'        => 'complex',
				'label'       => __( 'Discount Tiers', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => array(),
				'handler'     => 'tieredDiscount',
				'methods'     => array(
					'collect'  => 'getValue',
					'populate' => 'setValue',
				),
				'sanitizer'   => array( __CLASS__, 'sanitize_tiers' ),
				'validator'   => array( __CLASS__, 'validate_tiers' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'tiered',
				),
				'field_name'  => 'tiers',
			),
			'bogo_config'               => array(
				'type'        => 'complex',
				'label'       => __( 'BOGO Configuration', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => array(
					'buy_quantity'     => 1,
					'get_quantity'     => 1,
					'discount_percent' => 100,
				),
				'handler'     => 'bogoDiscount',
				'methods'     => array(
					'collect'  => 'getValue',
					'populate' => 'setValue',
				),
				'sanitizer'   => array( __CLASS__, 'sanitize_bogo_config' ),
				'validator'   => array( __CLASS__, 'validate_bogo_config' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'bogo',
				),
				'field_name'  => 'bogo_config',
			),
			'threshold_mode'            => array(
				'type'        => 'select',
				'label'       => __( 'Threshold Mode', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => 'percentage',
				'options'     => array(
					'percentage' => __( 'Percentage Discounts', 'smart-cycle-discounts' ),
					'fixed'      => __( 'Fixed Amount Off', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'spend_threshold',
				),
				'field_name'  => 'threshold_mode',
			),
			'thresholds'                => array(
				'type'        => 'complex',
				'label'       => __( 'Spend Thresholds', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => array(),
				'handler'     => 'spendThreshold',
				'methods'     => array(
					'collect'  => 'getValue',
					'populate' => 'setValue',
				),
				'sanitizer'   => array( __CLASS__, 'sanitize_thresholds' ),
				'validator'   => array( __CLASS__, 'validate_thresholds' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'spend_threshold',
				),
				'field_name'  => 'thresholds',
			),
			'usage_limit_per_customer'  => array(
				'type'       => 'number',
				'label'      => __( 'Usage Limit Per Customer', 'smart-cycle-discounts' ),
				'required'   => false,
				'min'        => 1,
				'max'        => 999,
				'default'    => '',
				'sanitizer'  => 'absint',
				'validator'  => array( __CLASS__, 'validate_number_range' ),
				'field_name' => 'usage_limit_per_customer',
			),
			'total_usage_limit'         => array(
				'type'       => 'number',
				'label'      => __( 'Total Usage Limit', 'smart-cycle-discounts' ),
				'required'   => false,
				'min'        => 1,
				'max'        => 999999,
				'default'    => '',
				'sanitizer'  => 'absint',
				'validator'  => array( __CLASS__, 'validate_number_range' ),
				'field_name' => 'total_usage_limit',
			),
			'lifetime_usage_cap'        => array(
				'type'       => 'number',
				'label'      => __( 'Lifetime Usage Cap', 'smart-cycle-discounts' ),
				'required'   => false,
				'min'        => 1,
				'max'        => 999999,
				'default'    => '',
				'sanitizer'  => 'absint',
				'validator'  => array( __CLASS__, 'validate_number_range' ),
				'field_name' => 'lifetime_usage_cap',
			),
			'apply_to'                  => array(
				'type'       => 'select',
				'label'      => __( 'Apply To', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => 'per_item',
				'options'    => array(
					'per_item'   => __( 'Per Item', 'smart-cycle-discounts' ),
					'cart_total' => __( 'Cart Total', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'field_name' => 'apply_to',
			),
			'max_discount_amount'       => array(
				'type'       => 'number',
				'label'      => __( 'Maximum Discount Amount', 'smart-cycle-discounts' ),
				'required'   => false,
				'min'        => 0.01,
				'max'        => 9999999,
				'default'    => '',
				'sanitizer'  => 'floatval',
				'validator'  => array( __CLASS__, 'validate_number_range' ),
				'attributes' => array(
					'step' => 0.01,
				),
				'field_name' => 'max_discount_amount',
			),
			'minimum_quantity'          => array(
				'type'       => 'number',
				'label'      => __( 'Minimum Quantity', 'smart-cycle-discounts' ),
				'required'   => false,
				'min'        => 1,
				'max'        => 999999,
				'default'    => '',
				'sanitizer'  => 'absint',
				'validator'  => array( __CLASS__, 'validate_number_range' ),
				'field_name' => 'minimum_quantity',
			),
			'minimum_order_amount'      => array(
				'type'       => 'number',
				'label'      => __( 'Minimum Order Amount', 'smart-cycle-discounts' ),
				'required'   => false,
				'min'        => 0.01,
				'max'        => 9999999,
				'default'    => '',
				'sanitizer'  => 'floatval',
				'validator'  => array( __CLASS__, 'validate_number_range' ),
				'attributes' => array(
					'step' => 0.01,
				),
				'field_name' => 'minimum_order_amount',
			),
			'stack_with_others'         => array(
				'type'       => 'boolean',
				'label'      => __( 'Stack With Other Discounts', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => false,
				'sanitizer'  => 'rest_sanitize_boolean',
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'stack_with_others',
			),
			'allow_coupons'             => array(
				'type'       => 'boolean',
				'label'      => __( 'Allow Coupons', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => true,
				'sanitizer'  => 'rest_sanitize_boolean',
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'allow_coupons',
			),
			'apply_to_sale_items'       => array(
				'type'       => 'boolean',
				'label'      => __( 'Apply To Sale Items', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => true,
				'sanitizer'  => 'rest_sanitize_boolean',
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'apply_to_sale_items',
			),
			'badge_enabled'             => array(
				'type'       => 'boolean',
				'label'      => __( 'Enable Badge', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => false,
				'sanitizer'  => 'rest_sanitize_boolean',
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'badge_enabled',
			),
			'badge_text'                => array(
				'type'        => 'text',
				'label'       => __( 'Badge Text', 'smart-cycle-discounts' ),
				'required'    => true,
				'conditional' => array(
					'field' => 'badge_enabled',
					'value' => true,
				),
				'max_length'  => 50,
				'default'     => '',
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_text_length' ),
				'field_name'  => 'badge_text',
			),
			'badge_bg_color'            => array(
				'type'        => 'text',
				'label'       => __( 'Badge Background Color', 'smart-cycle-discounts' ),
				'required'    => true,
				'conditional' => array(
					'field' => 'badge_enabled',
					'value' => true,
				),
				'default'     => '#e74c3c',
				'sanitizer'   => 'sanitize_hex_color',
				'validator'   => array( __CLASS__, 'validate_hex_color' ),
				'field_name'  => 'badge_bg_color',
			),
			'badge_text_color'          => array(
				'type'        => 'text',
				'label'       => __( 'Badge Text Color', 'smart-cycle-discounts' ),
				'required'    => true,
				'conditional' => array(
					'field' => 'badge_enabled',
					'value' => true,
				),
				'default'     => '#ffffff',
				'sanitizer'   => 'sanitize_hex_color',
				'validator'   => array( __CLASS__, 'validate_hex_color' ),
				'field_name'  => 'badge_text_color',
			),
			'badge_position'            => array(
				'type'        => 'select',
				'label'       => __( 'Badge Position', 'smart-cycle-discounts' ),
				'required'    => true,
				'conditional' => array(
					'field' => 'badge_enabled',
					'value' => true,
				),
				'default'     => 'top-right',
				'options'     => array(
					'top-left'     => __( 'Top Left', 'smart-cycle-discounts' ),
					'top-right'    => __( 'Top Right', 'smart-cycle-discounts' ),
					'bottom-left'  => __( 'Bottom Left', 'smart-cycle-discounts' ),
					'bottom-right' => __( 'Bottom Right', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'field_name'  => 'badge_position',
			),
		);
	}

	/**
	 * Define schedule step fields
	 *
	 * @since 1.0.0
	 */
	private static function define_schedule_fields() {
		self::$schemas['schedule'] = array(
			'start_date'          => array(
				'type'       => 'date',
				'label'      => __( 'Start Date', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => '',
				'sanitizer'  => array( __CLASS__, 'sanitize_date' ),
				'validator'  => array( __CLASS__, 'validate_datetime' ),
				'attributes' => array(
					'min' => current_time( 'Y-m-d' ),
				),
				'field_name' => 'start_date',
			),
			'start_time'          => array(
				'type'       => 'time',
				'label'      => __( 'Start Time', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => '00:00',
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_time_format' ),
				'field_name' => 'start_time',
			),
			'end_date'            => array(
				'type'       => 'date',
				'label'      => __( 'End Date', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => '',
				'sanitizer'  => array( __CLASS__, 'sanitize_date' ),
				'validator'  => array( __CLASS__, 'validate_end_date' ),
				'field_name' => 'end_date',
			),
			'end_time'            => array(
				'type'       => 'time',
				'label'      => __( 'End Time', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => '23:59',
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_time_format' ),
				'field_name' => 'end_time',
			),
			'timezone'            => array(
				'type'       => 'select',
				'label'      => __( 'Timezone', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => wp_timezone_string(),
				'options'    => wp_timezone_choice( wp_timezone_string() ),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_timezone' ),
				'field_name' => 'timezone',
			),
			'start_type'          => array(
				'type'       => 'radio',
				'label'      => __( 'Start Type', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => 'immediate',
				'options'    => array(
					'immediate' => __( 'Start Immediately', 'smart-cycle-discounts' ),
					'scheduled' => __( 'Schedule Start', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'field_name' => 'start_type',
			),
			'duration_seconds'    => array(
				'type'        => 'number',
				'label'       => __( 'Campaign Duration (seconds)', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 60,
				'max'         => 31536000,
				'default'     => 3600,
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'start_type',
					'value' => 'immediate',
				),
				'field_name'  => 'duration_seconds',
			),
			'enable_recurring'    => array(
				'type'       => 'checkbox',
				'label'      => __( 'Enable Recurring Schedule', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => false,
				'sanitizer'  => array( __CLASS__, 'sanitize_boolean' ),
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'enable_recurring',
			),
			'recurrence_pattern'  => array(
				'type'        => 'select',
				'label'       => __( 'Recurrence Pattern', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => 'daily',
				'options'     => array(
					'daily'   => __( 'Daily', 'smart-cycle-discounts' ),
					'weekly'  => __( 'Weekly', 'smart-cycle-discounts' ),
					'monthly' => __( 'Monthly', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'conditional' => array(
					'field' => 'enable_recurring',
					'value' => true,
				),
				'field_name'  => 'recurrence_pattern',
			),
			'recurrence_interval' => array(
				'type'        => 'number',
				'label'       => __( 'Recurrence Interval', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 365,
				'default'     => 1,
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'enable_recurring',
					'value' => true,
				),
				'field_name'  => 'recurrence_interval',
			),
			'recurrence_days'     => array(
				'type'        => 'array',
				'label'       => __( 'Recurrence Days', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => array(),
				'sanitizer'   => array( __CLASS__, 'sanitize_array_values' ),
				'validator'   => array( __CLASS__, 'validate_recurrence_days' ),
				'conditional' => array(
					'field' => 'recurrence_pattern',
					'value' => 'weekly',
				),
				'field_name'  => 'recurrence_days',
			),
			'recurrence_end_type' => array(
				'type'        => 'radio',
				'label'       => __( 'Recurrence End Type', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => 'never',
				'options'     => array(
					'never' => __( 'Never', 'smart-cycle-discounts' ),
					'after' => __( 'After', 'smart-cycle-discounts' ),
					'on'    => __( 'On Date', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'conditional' => array(
					'field' => 'enable_recurring',
					'value' => true,
				),
				'field_name'  => 'recurrence_end_type',
			),
			'recurrence_count'    => array(
				'type'        => 'number',
				'label'       => __( 'Recurrence Count', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 100,
				'default'     => 10,
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'recurrence_end_type',
					'value' => 'after',
				),
				'field_name'  => 'recurrence_count',
			),
			'recurrence_end_date' => array(
				'type'        => 'date',
				'label'       => __( 'Recurrence End Date', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => '',
				'sanitizer'   => array( __CLASS__, 'sanitize_date' ),
				'validator'   => array( __CLASS__, 'validate_datetime' ),
				'conditional' => array(
					'field' => 'recurrence_end_type',
					'value' => 'on',
				),
				'field_name'  => 'recurrence_end_date',
			),
			'rotation_enabled'    => array(
				'type'       => 'checkbox',
				'label'      => __( 'Enable Product Rotation', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => false,
				'sanitizer'  => array( __CLASS__, 'sanitize_boolean' ),
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'rotation_enabled',
			),
			'rotation_interval'   => array(
				'type'        => 'number',
				'label'       => __( 'Rotation Interval (hours)', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 168,
				'default'     => 24,
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'rotation_enabled',
					'value' => true,
				),
				'field_name'  => 'rotation_interval',
			),
		);
	}

	/**
	 * Define review step fields
	 *
	 * @since 1.0.0
	 */
	private static function define_review_fields() {
		self::$schemas['review'] = array(
			'launch_option' => array(
				'type'       => 'radio',
				'label'      => __( 'Launch Option', 'smart-cycle-discounts' ),
				'required'   => true,
				'default'    => 'draft',
				'options'    => array(
					'draft'  => __( 'Save as Draft', 'smart-cycle-discounts' ),
					'active' => __( 'Launch Campaign', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'field_name' => 'launch_option',
			),
		);
	}

	/**
	 * Get field schema for a specific step
	 *
	 * @since  1.0.0
	 * @param  string $step Step name
	 * @return array Field schemas
	 */
	public static function get_step_fields( $step ) {
		if ( empty( self::$schemas ) ) {
			self::init();
		}

		return isset( self::$schemas[ $step ] ) ? self::$schemas[ $step ] : array();
	}

	/**
	 * Get a specific field schema
	 *
	 * @since  1.0.0
	 * @param  string $step  Step name
	 * @param  string $field Field name
	 * @return array|null Field schema or null
	 */
	public static function get_field( $step, $field ) {
		$step_fields = self::get_step_fields( $step );
		return isset( $step_fields[ $field ] ) ? $step_fields[ $field ] : null;
	}

	/**
	 * Export field schemas for JavaScript
	 *
	 * @since  1.0.0
	 * @return array Field schemas formatted for JS
	 */
	public static function export_for_js() {
		if ( empty( self::$schemas ) ) {
			self::init();
		}

		$js_schemas = array();

		foreach ( self::$schemas as $step => $fields ) {
			$js_schemas[ $step ] = array();

			foreach ( $fields as $field_key => $field_schema ) {
				// Convert PHP field names to camelCase for JavaScript
				$js_field_key = self::to_camel_case( $field_key );

				// Basic field properties
				$js_field = array(
					'type'      => $field_schema['type'],
					'required'  => isset( $field_schema['required'] ) ? $field_schema['required'] : false,
					'default'   => isset( $field_schema['default'] ) ? $field_schema['default'] : '',
					'fieldName' => $field_key, // Snake case for form fields
					'label'     => isset( $field_schema['label'] ) ? $field_schema['label'] : '', // Field label for error messages
				);

				// Add validation rules
				if ( isset( $field_schema['min'] ) ) {
					$js_field['min'] = $field_schema['min'];
				}
				if ( isset( $field_schema['max'] ) ) {
					$js_field['max'] = $field_schema['max'];
				}
				if ( isset( $field_schema['min_length'] ) ) {
					$js_field['minLength'] = $field_schema['min_length'];
				}
				if ( isset( $field_schema['max_length'] ) ) {
					$js_field['maxLength'] = $field_schema['max_length'];
				}
				if ( isset( $field_schema['options'] ) ) {
					$js_field['options'] = $field_schema['options'];
				}

				// Add handler information
				if ( isset( $field_schema['handler'] ) ) {
					$js_field['handler'] = $field_schema['handler'];
					if ( isset( $field_schema['methods'] ) ) {
						$js_field['methods'] = $field_schema['methods'];
					}
				}

				// Add selector (for hidden fields and custom selectors)
				if ( isset( $field_schema['selector'] ) ) {
					$js_field['selector'] = $field_schema['selector'];
				}

				// Add conditional information
				if ( isset( $field_schema['conditional'] ) ) {
					$js_field['conditional'] = array(
						'field' => $field_schema['conditional']['field'], // Keep snake_case to match form field names
						'value' => $field_schema['conditional']['value'],
					);
				}

				$js_schemas[ $step ][ $js_field_key ] = $js_field;
			}
		}

		// Add condition types and operator mappings for products step
		if ( isset( $js_schemas['products'] ) ) {
			$js_schemas['products']['_conditionTypes']   = self::get_condition_types();
			$js_schemas['products']['_operatorMappings'] = self::get_operator_mappings();
		}

		return $js_schemas;
	}

	/**
	 * Render a field HTML
	 *
	 * @since  1.0.0
	 * @param  string $step       Step name
	 * @param  string $field_name Field name
	 * @param  mixed  $value      Current value
	 * @param  array  $args       Additional arguments
	 * @return string HTML output
	 */
	public static function render_field( $step, $field_name, $value = null, $args = array() ) {
		$field = self::get_field( $step, $field_name );
		if ( ! $field ) {
			return '';
		}

		// Use default if value is null
		if ( null === $value ) {
			$value = $field['default'];
		}

		// Merge additional attributes
		$attributes = isset( $field['attributes'] ) ? $field['attributes'] : array();
		if ( isset( $args['attributes'] ) ) {
			$attributes = array_merge( $attributes, $args['attributes'] );
		}

		// Build HTML based on field type
		$html = '';
		switch ( $field['type'] ) {
			case 'text':
				$html = self::render_text_field( $field_name, $value, $field, $attributes );
				break;

			case 'textarea':
				$html = self::render_textarea_field( $field_name, $value, $field, $attributes );
				break;

			case 'number':
				$html = self::render_number_field( $field_name, $value, $field, $attributes );
				break;

			case 'select':
				$html = self::render_select_field( $field_name, $value, $field, $attributes );
				break;

			case 'select_multiple':
				$html = self::render_select_multiple_field( $field_name, $value, $field, $attributes );
				break;

			case 'radio':
				$html = self::render_radio_field( $field_name, $value, $field, $attributes );
				break;

			case 'checkbox':
				$html = self::render_checkbox_field( $field_name, $value, $field, $attributes );
				break;

			case 'datetime':
				$html = self::render_datetime_field( $field_name, $value, $field, $attributes );
				break;

			case 'complex':
				// Complex fields are handled by JavaScript modules
				$html = sprintf(
					'<div class="scd-complex-field" data-field="%s" data-handler="%s"></div>',
					esc_attr( $field_name ),
					esc_attr( $field['handler'] )
				);
				break;
		}

		// Wrap in field container
		$container_html = '<div class="form-field scd-field-' . esc_attr( $field_name ) . '">';

		// Add label
		if ( ! empty( $field['label'] ) && 'checkbox' !== $field['type'] ) {
			$container_html .= sprintf(
				'<label for="%s">%s%s</label>',
				esc_attr( $field_name ),
				esc_html( $field['label'] ),
				$field['required'] ? ' <span class="required">*</span>' : ''
			);
		}

		// Add field HTML
		$container_html .= $html;

		// Add description
		if ( ! empty( $field['description'] ) ) {
			$container_html .= sprintf(
				'<p class="description">%s</p>',
				esc_html( $field['description'] )
			);
		}

		$container_html .= '</div>';

		return $container_html;
	}

	/**
	 * Render text field
	 */
	private static function render_text_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'type'  => 'text',
					'id'    => $name,
					'name'  => $name,
					'value' => $value,
					'class' => 'regular-text',
				)
			)
		);

		return sprintf( '<input %s />', $attr_string );
	}

	/**
	 * Render textarea field
	 */
	private static function render_textarea_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'id'    => $name,
					'name'  => $name,
					'class' => 'large-text',
				)
			)
		);

		return sprintf( '<textarea %s>%s</textarea>', $attr_string, esc_textarea( $value ) );
	}

	/**
	 * Render number field
	 */
	private static function render_number_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'type'  => 'number',
					'id'    => $name,
					'name'  => $name,
					'value' => $value,
					'class' => 'small-text',
					'min'   => isset( $schema['min'] ) ? $schema['min'] : '',
					'max'   => isset( $schema['max'] ) ? $schema['max'] : '',
					'step'  => isset( $attributes['step'] ) ? $attributes['step'] : 1,
				)
			)
		);

		return sprintf( '<input %s />', $attr_string );
	}

	/**
	 * Render select field
	 */
	private static function render_select_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'id'   => $name,
					'name' => $name,
				)
			)
		);

		$html = sprintf( '<select %s>', $attr_string );

		if ( isset( $schema['options'] ) ) {
			foreach ( $schema['options'] as $option_value => $option_label ) {
				$html .= sprintf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $option_value ),
					selected( $value, $option_value, false ),
					esc_html( $option_label )
				);
			}
		}

		$html .= '</select>';

		return $html;
	}

	/**
	 * Render select multiple field
	 */
	private static function render_select_multiple_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'id'       => $name,
					'name'     => $name . '[]',
					'multiple' => 'multiple',
				)
			)
		);

		return sprintf( '<select %s></select>', $attr_string );
	}

	/**
	 * Render radio field
	 */
	private static function render_radio_field( $name, $value, $schema, $attributes ) {
		$html = '';

		if ( isset( $schema['options'] ) ) {
			foreach ( $schema['options'] as $option_value => $option_label ) {
				$id    = $name . '_' . $option_value;
				$html .= sprintf(
					'<label><input type="radio" id="%s" name="%s" value="%s"%s /> %s</label><br/>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $option_value ),
					checked( $value, $option_value, false ),
					esc_html( $option_label )
				);
			}
		}

		return $html;
	}

	/**
	 * Render checkbox field
	 */
	private static function render_checkbox_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'type'  => 'checkbox',
					'id'    => $name,
					'name'  => $name,
					'value' => '1',
				)
			)
		);

		$html = sprintf(
			'<label><input %s%s /> %s</label>',
			$attr_string,
			checked( $value, true, false ),
			esc_html( $schema['label'] )
		);

		return $html;
	}

	/**
	 * Render datetime field
	 */
	private static function render_datetime_field( $name, $value, $schema, $attributes ) {
		$attr_string = self::build_attributes(
			array_merge(
				$attributes,
				array(
					'type'  => 'datetime-local',
					'id'    => $name,
					'name'  => $name,
					'value' => $value,
					'class' => 'regular-text',
				)
			)
		);

		return sprintf( '<input %s />', $attr_string );
	}

	/**
	 * Build HTML attributes string
	 */
	private static function build_attributes( $attributes ) {
		$attr_strings = array();

		foreach ( $attributes as $key => $value ) {
			if ( '' !== $value && null !== $value && false !== $value ) {
				if ( true === $value ) {
					$attr_strings[] = esc_attr( $key );
				} else {
					$attr_strings[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
				}
			}
		}

		return implode( ' ', $attr_strings );
	}

	/**
	 * Validate field data
	 *
	 * @since  1.0.0
	 * @param  string $step Step name
	 * @param  array  $data Data to validate
	 * @return array|WP_Error Sanitized data or error
	 */
	public static function validate( $step, $data ) {
		$fields    = self::get_step_fields( $step );
		$errors    = new WP_Error();
		$sanitized = array();

		foreach ( $fields as $field_key => $field_schema ) {
			// Check if field should be validated based on conditionals
			$conditional_met = false;
			if ( isset( $field_schema['conditional'] ) ) {
				$condition_field = $field_schema['conditional']['field'];
				$condition_value = $field_schema['conditional']['value'];
				$actual_value    = isset( $data[ $condition_field ] ) ? $data[ $condition_field ] : null;

				// Handle boolean comparison with proper normalization
				$condition_matches = false;
				if ( is_bool( $condition_value ) ) {
					// Normalize boolean string values from JavaScript
					$normalized_actual = $actual_value;
					if ( 'true' === $actual_value || '1' === $actual_value || 1 === $actual_value ) {
						$normalized_actual = true;
					} elseif ( 'false' === $actual_value || '0' === $actual_value || 0 === $actual_value || '' === $actual_value ) {
						$normalized_actual = false;
					} else {
						// Cast non-string values to boolean
						$normalized_actual = (bool) $actual_value;
					}
					$condition_matches = $normalized_actual === $condition_value;
				} else {
					$condition_matches = $actual_value === $condition_value;
				}

				// Skip validation if conditional not met
				if ( ! $condition_matches ) {
					continue;
				}
				$conditional_met = true;
			}

			$value = isset( $data[ $field_key ] ) ? $data[ $field_key ] : null;

			// Skip non-required fields that are not present in the data
			// Conditional fields are skipped if not visible (conditional not met)
			if ( empty( $field_schema['required'] ) && ! isset( $data[ $field_key ] ) ) {
				continue;
			}

			// Apply sanitizer
			if ( isset( $field_schema['sanitizer'] ) ) {
				$value = call_user_func( $field_schema['sanitizer'], $value );
			}

			// Check required - use isset() and !== to handle 0 values correctly
			// Fields are required based on their 'required' flag
			// Conditional only controls visibility, not required status
			$is_required = ! empty( $field_schema['required'] );
			if ( $is_required && ( ! isset( $value ) || '' === $value || array() === $value ) ) {
				$errors->add(
					$field_key . '_required',
					sprintf( __( '%s is required', 'smart-cycle-discounts' ), $field_schema['label'] )
				);
				continue;
			}

			// Determine if value is empty (for optional field handling)
			$is_empty = ! isset( $value ) || '' === $value || array() === $value;
			// For optional number fields, treat 0 as empty (handles int 0, float 0.0, string '0')
			if ( ! $is_required && isset( $field_schema['type'] ) && 'number' === $field_schema['type'] ) {
				if ( is_numeric( $value ) && 0.0 === (float) $value ) {
					$is_empty = true;
				}
			}

			// Skip validation and storage for optional empty fields
			if ( ! $is_required && $is_empty ) {
				continue;
			}

			// Apply validator if value is not empty and validator exists
			if ( isset( $field_schema['validator'] ) && ! $is_empty ) {
				$validation_result = call_user_func(
					$field_schema['validator'],
					$value,
					$field_schema,
					$field_key
				);

				if ( is_wp_error( $validation_result ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( '[SCD Validation] Field "%s" failed validation: %s (value: %s)', $field_key, $validation_result->get_error_message(), var_export( $value, true ) ) );
					}
					$errors->add( $field_key . '_invalid', $validation_result->get_error_message() );
					continue;
				}
			}

			$sanitized[ $field_key ] = $value;
		}

		// Cross-field validation for discounts step
		if ( 'discounts' === $step && ! $errors->has_errors() ) {
			self::validate_discounts_cross_fields( $sanitized, $errors );
			self::validate_discounts_feature_gate( $sanitized, $errors );
		}

		// Feature gate validation for products step
		if ( 'products' === $step && ! $errors->has_errors() ) {
			self::validate_products_feature_gate( $sanitized, $errors );
		}

		return $errors->has_errors() ? $errors : $sanitized;
	}

	/**
	 * Sanitize step data without validation
	 *
	 * Used during AJAX navigation and auto-saves for lightweight processing.
	 * Only sanitizes data without applying validation rules.
	 *
	 * Design Notes:
	 * - Sanitizes ALL fields present in the request, including complex fields
	 * - Complex field sanitizers (sanitize_tiers, sanitize_conditions, etc.) ARE called
	 * - Fields not in the request are preserved from existing state (via state merge)
	 * - Preserved fields were already sanitized when originally saved
	 * - This approach prevents duplicate sanitization and maintains data integrity
	 *
	 * @since    1.0.0
	 * @param    array  $data    Raw step data from form submission
	 * @param    string $step    Step name
	 * @return   array|WP_Error     Sanitized data or error
	 */
	public static function sanitize_only( $data, $step ) {
		$step   = sanitize_key( $step );
		$fields = self::get_step_fields( $step );

		if ( ! $fields ) {
			return new WP_Error( 'invalid_step', __( 'Invalid step', 'smart-cycle-discounts' ) );
		}

		$sanitized = array();

		foreach ( $fields as $field_key => $field_schema ) {
			if ( ! isset( $data[ $field_key ] ) ) {
				continue;
			}

			$value = $data[ $field_key ];

			// Apply sanitizer (including complex field sanitizers like sanitize_tiers)
			if ( isset( $field_schema['sanitizer'] ) ) {
				$value = call_user_func( $field_schema['sanitizer'], $value );
			}

			$sanitized[ $field_key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Convert snake_case to camelCase
	 */
	private static function to_camel_case( $string ) {
		return lcfirst( str_replace( '_', '', ucwords( $string, '_' ) ) );
	}

	// Validation methods

	/**
	 * Validate text length
	 */
	public static function validate_text_length( $value, $schema, $field_key ) {
		if ( isset( $schema['min_length'] ) && strlen( $value ) < $schema['min_length'] ) {
			return new WP_Error(
				'min_length',
				sprintf( __( 'Must be at least %d characters', 'smart-cycle-discounts' ), $schema['min_length'] )
			);
		}

		if ( isset( $schema['max_length'] ) && strlen( $value ) > $schema['max_length'] ) {
			return new WP_Error(
				'max_length',
				sprintf( __( 'Must not exceed %d characters', 'smart-cycle-discounts' ), $schema['max_length'] )
			);
		}

		return true;
	}

	/**
	 * Validate number range
	 */
	public static function validate_number_range( $value, $schema, $field_key ) {
		if ( isset( $schema['min'] ) && $value < $schema['min'] ) {
			return new WP_Error(
				'min_value',
				sprintf( __( 'Must be at least %s', 'smart-cycle-discounts' ), $schema['min'] )
			);
		}

		if ( isset( $schema['max'] ) && $value > $schema['max'] ) {
			return new WP_Error(
				'max_value',
				sprintf( __( 'Must not exceed %s', 'smart-cycle-discounts' ), $schema['max'] )
			);
		}

		return true;
	}

	/**
	 * Validate in array
	 */
	public static function validate_in_array( $value, $schema, $field_key ) {
		if ( ! isset( $schema['options'] ) || ! array_key_exists( $value, $schema['options'] ) ) {
			return new WP_Error(
				'invalid_option',
				__( 'Invalid selection', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Validate percentage
	 */
	public static function validate_percentage( $value, $schema, $field_key ) {
		// Use proper validation constants
		if ( $value < SCD_Validation_Rules::PERCENTAGE_MIN || $value > SCD_Validation_Rules::PERCENTAGE_MAX ) {
			return new WP_Error(
				'invalid_percentage',
				sprintf(
					__( 'Percentage must be between %1$d and %2$d', 'smart-cycle-discounts' ),
					SCD_Validation_Rules::PERCENTAGE_MIN,
					SCD_Validation_Rules::PERCENTAGE_MAX
				)
			);
		}

		return true;
	}

	// Sanitizers

	/**
	 * Sanitize array values
	 */
	public static function sanitize_array_values( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Sanitize product IDs from CSV string to array
	 *
	 * Converts comma-separated product IDs from hidden field to array of integers.
	 *
	 * @since    1.0.0
	 * @param    string|array $value    CSV string or array of product IDs.
	 * @return   array                     Array of sanitized product IDs.
	 */
	public static function sanitize_product_ids_csv( $value ) {
		// If already an array, sanitize it
		if ( is_array( $value ) ) {
			return array_filter( array_map( 'absint', $value ) );
		}

		// If empty string, return empty array
		if ( empty( $value ) || ! is_string( $value ) ) {
			return array();
		}

		// Split CSV, sanitize, filter out zeros
		$ids = explode( ',', $value );
		$ids = array_map( 'trim', $ids );
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( $ids );
	}

	/**
	 * Sanitize boolean
	 */
	public static function sanitize_boolean( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize date (date only, no time component)
	 *
	 * Ensures date fields store ONLY dates in YYYY-MM-DD format.
	 * This is critical for proper date comparison in health checks and validations.
	 *
	 * @since    1.0.0
	 * @param    string $value    Date value to sanitize.
	 * @return   string              Sanitized date in Y-m-d format, or empty string if invalid.
	 */
	public static function sanitize_date( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// HIGH: Only accept date formats (no time component)
		// This ensures date fields store ONLY dates, not timestamps
		$valid_formats = array(
			'Y-m-d',
			'Y/m/d',
			'm/d/Y',
			'd/m/Y',
		);

		$datetime = false;
		foreach ( $valid_formats as $format ) {
			$datetime = DateTime::createFromFormat( $format, $value, wp_timezone() );
			if ( $datetime && $datetime->format( $format ) === $value ) {
				break; // Valid format found
			}
			$datetime = false;
		}

		if ( ! $datetime ) {
			// Try to extract date from datetime string (e.g., "2025-10-17 19:26:16" -> "2025-10-17")
			if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $matches ) ) {
				return $matches[1];
			}
			return '';
		}

		// Always return YYYY-MM-DD format (date only, no time)
		return $datetime->format( 'Y-m-d' );
	}

	/**
	 * Sanitize datetime (full timestamp with time component)
	 *
	 * Returns full datetime in YYYY-MM-DD HH:MM:SS format.
	 * Use sanitize_date() if you only need the date component.
	 *
	 * @since    1.0.0
	 * @param    string $value    Datetime value to sanitize.
	 * @return   string              Sanitized datetime in Y-m-d H:i:s format, or empty string if invalid.
	 */
	public static function sanitize_datetime( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// HIGH: Only accept specific datetime formats to prevent timezone/relative date attacks
		$valid_formats = array(
			'Y-m-d H:i:s',
			'Y-m-d H:i',
			'Y-m-d\TH:i:s',
			'Y-m-d\TH:i',
			'Y-m-d',
		);

		$datetime = false;
		foreach ( $valid_formats as $format ) {
			$datetime = DateTime::createFromFormat( $format, $value, wp_timezone() );
			if ( $datetime && $datetime->format( $format ) === $value ) {
				break; // Valid format found
			}
			$datetime = false;
		}

		if ( ! $datetime ) {
			return '';
		}

		// HIGH: Always use site timezone for consistency
		$datetime->setTimezone( wp_timezone() );
		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Sanitize conditions array
	 */
	public static function sanitize_conditions( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $condition ) {
			if ( is_array( $condition ) ) {
				$sanitized[] = array(
					'type'     => isset( $condition['type'] ) ? sanitize_text_field( $condition['type'] ) : '',
					'operator' => isset( $condition['operator'] ) ? sanitize_text_field( $condition['operator'] ) : '',
					'value'    => isset( $condition['value'] ) ? sanitize_text_field( $condition['value'] ) : '',
					'value2'   => isset( $condition['value2'] ) ? sanitize_text_field( $condition['value2'] ) : '',
					'mode'     => isset( $condition['mode'] ) ? sanitize_text_field( $condition['mode'] ) : 'include',
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Validate category IDs
	 */
	public static function validate_category_ids( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Category IDs must be an array', 'smart-cycle-discounts' )
			);
		}

		// Check if 'all' is selected with other categories
		if ( in_array( 'all', $value, true ) && count( $value ) > 1 ) {
			return new WP_Error(
				'invalid_selection',
				__( 'Cannot select specific categories when "All" is selected', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Validate product IDs
	 */
	public static function validate_product_ids( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Product IDs must be an array', 'smart-cycle-discounts' )
			);
		}

		// Validate each product ID
		foreach ( $value as $product_id ) {
			if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
				return new WP_Error(
					'invalid_product_id',
					__( 'Invalid product ID', 'smart-cycle-discounts' )
				);
			}

			// Verify product exists in WooCommerce
			$product = wc_get_product( $product_id );
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return new WP_Error(
					'product_not_found',
					sprintf(
						/* translators: %d: Product ID */
						__( 'Product with ID %d does not exist', 'smart-cycle-discounts' ),
						$product_id
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate datetime
	 */
	public static function validate_datetime( $value, $schema, $field_key ) {
		if ( empty( $value ) ) {
			return true; // Empty is valid for non-required fields
		}

		$datetime = date_create( $value );
		if ( ! $datetime ) {
			return new WP_Error(
				'invalid_datetime',
				__( 'Invalid date/time format', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Validate end date
	 */
	public static function validate_end_date( $value, $schema, $field_key ) {
		// First validate datetime format
		$datetime_valid = self::validate_datetime( $value, $schema, $field_key );
		if ( is_wp_error( $datetime_valid ) ) {
			return $datetime_valid;
		}

		// Additional validation would check against start_date
		// This would need access to other field values

		return true;
	}

	/**
	 * Validate timezone
	 */
	public static function validate_timezone( $value, $schema, $field_key ) {
		// LOW: Get valid timezones (excluding deprecated ones)
		$valid_timezones = timezone_identifiers_list();

		// Check if it's a valid timezone identifier
		if ( in_array( $value, $valid_timezones, true ) ) {
			return true;
		}

		// Check if it's a valid UTC offset format (e.g., '+00:00', '-05:00', 'UTC+3')
		if ( preg_match( '/^(UTC)?[+-]\d{1,2}(:\d{2})?$/', $value ) ) {
			return true;
		}

		// LOW: Check if it's a deprecated abbreviation and suggest alternative
		$deprecated_map = array(
			'EST' => 'America/New_York',
			'PST' => 'America/Los_Angeles',
			'MST' => 'America/Denver',
			'CST' => 'America/Chicago',
			'EDT' => 'America/New_York',
			'PDT' => 'America/Los_Angeles',
			'MDT' => 'America/Denver',
			'CDT' => 'America/Chicago',
		);

		if ( isset( $deprecated_map[ $value ] ) ) {
			return new WP_Error(
				'deprecated_timezone',
				sprintf(
					__( 'Timezone "%1$s" is deprecated. Please use "%2$s" instead.', 'smart-cycle-discounts' ),
					$value,
					$deprecated_map[ $value ]
				)
			);
		}

		return new WP_Error(
			'invalid_timezone',
			__( 'Invalid timezone', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Validate conditions array
	 */
	public static function validate_conditions( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) ) {
			return true; // Empty conditions are valid
		}

		foreach ( $value as $index => $condition ) {
			if ( ! is_array( $condition ) ) {
				return new WP_Error(
					'invalid_condition',
					sprintf( __( 'Condition %d is invalid', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Validate condition structure
			if ( empty( $condition['type'] ) || empty( $condition['operator'] ) ) {
				return new WP_Error(
					'incomplete_condition',
					sprintf( __( 'Condition %d is incomplete', 'smart-cycle-discounts' ), $index + 1 )
				);
			}
		}

		return true;
	}

	/**
	 * Validate tiers array
	 */
	public static function validate_tiers( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return new WP_Error(
				'tiers_required',
				__( 'At least one tier is required', 'smart-cycle-discounts' )
			);
		}

		$seen_quantities = array();
		foreach ( $value as $index => $tier ) {
			if ( ! isset( $tier['min_quantity'] ) || ! isset( $tier['discount_value'] ) ) {
				return new WP_Error(
					'incomplete_tier',
					sprintf( __( 'Tier %d is incomplete', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Validate min_quantity is numeric and positive
			if ( ! is_numeric( $tier['min_quantity'] ) ) {
				return new WP_Error(
					'invalid_tier_quantity_type',
					sprintf( __( 'Tier %d: Quantity must be a number', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( $tier['min_quantity'] <= 0 ) {
				return new WP_Error(
					'invalid_tier_quantity',
					sprintf( __( 'Tier %d: Quantity must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Check for duplicate quantities
			if ( in_array( $tier['min_quantity'], $seen_quantities, true ) ) {
				return new WP_Error(
					'duplicate_tier_quantity',
					sprintf( __( 'Tier %1$d: Duplicate quantity %2$d', 'smart-cycle-discounts' ), $index + 1, $tier['min_quantity'] )
				);
			}
			$seen_quantities[] = $tier['min_quantity'];

			// Validate discount value is numeric and positive
			if ( ! is_numeric( $tier['discount_value'] ) ) {
				return new WP_Error(
					'invalid_tier_discount_type',
					sprintf( __( 'Tier %d: Discount must be a number', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( $tier['discount_value'] <= 0 ) {
				return new WP_Error(
					'invalid_tier_discount',
					sprintf( __( 'Tier %d: Discount must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Validate percentage doesn't exceed 100
			if ( 'percentage' === $tier['discount_type'] && $tier['discount_value'] > 100 ) {
				return new WP_Error(
					'invalid_tier_percentage',
					sprintf( __( 'Tier %d: Percentage cannot exceed 100', 'smart-cycle-discounts' ), $index + 1 )
				);
			}
		}

		return true;
	}

	/**
	 * Validate BOGO configuration
	 */
	public static function validate_bogo_config( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_bogo_config',
				__( 'BOGO configuration is invalid', 'smart-cycle-discounts' )
			);
		}

		// Validate required fields
		if ( ! isset( $value['buy_quantity'] ) || $value['buy_quantity'] < 1 ) {
			return new WP_Error(
				'invalid_buy_quantity',
				__( 'Buy quantity must be at least 1', 'smart-cycle-discounts' )
			);
		}

		if ( ! isset( $value['get_quantity'] ) || $value['get_quantity'] < 1 ) {
			return new WP_Error(
				'invalid_get_quantity',
				__( 'Get quantity must be at least 1', 'smart-cycle-discounts' )
			);
		}

		if ( ! isset( $value['discount_percent'] ) ||
			$value['discount_percent'] < 0 ||
			$value['discount_percent'] > 100 ) {
			return new WP_Error(
				'invalid_discount_percent',
				__( 'Discount percent must be between 0 and 100', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Validate thresholds array
	 */
	public static function validate_thresholds( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return new WP_Error(
				'thresholds_required',
				__( 'At least one threshold is required', 'smart-cycle-discounts' )
			);
		}

		$seen_thresholds = array();
		foreach ( $value as $index => $threshold ) {
			// Validate required fields
			if ( ! isset( $threshold['threshold'] ) || ! isset( $threshold['discount_value'] ) ) {
				return new WP_Error(
					'incomplete_threshold',
					sprintf( __( 'Threshold %d is incomplete', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Validate threshold is positive
			if ( $threshold['threshold'] <= 0 ) {
				return new WP_Error(
					'invalid_threshold_amount',
					sprintf( __( 'Threshold %d: Amount must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Check for duplicate thresholds
			if ( in_array( $threshold['threshold'], $seen_thresholds, true ) ) {
				return new WP_Error(
					'duplicate_threshold',
					sprintf( __( 'Threshold %d: Duplicate threshold amount', 'smart-cycle-discounts' ), $index + 1 )
				);
			}
			$seen_thresholds[] = $threshold['threshold'];

			// Validate discount value
			if ( $threshold['discount_value'] <= 0 ) {
				return new WP_Error(
					'invalid_discount_value',
					sprintf( __( 'Threshold %d: Discount value must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			// Validate percentage doesn't exceed 100
			$discount_type = $threshold['discount_type'] ?? 'percentage';
			if ( 'percentage' === $discount_type && $threshold['discount_value'] > 100 ) {
				return new WP_Error(
					'invalid_percentage',
					sprintf( __( 'Threshold %d: Percentage cannot exceed 100', 'smart-cycle-discounts' ), $index + 1 )
				);
			}
		}

		return true;
	}

	/**
	 * Validate time format (HH:MM in 24-hour format)
	 *
	 * @since  1.0.0
	 * @param  mixed  $value      Value to validate
	 * @param  array  $schema     Field schema
	 * @param  string $field_key  Field key
	 * @return true|WP_Error      True if valid, WP_Error otherwise
	 */
	public static function validate_time_format( $value, $schema, $field_key ) {
		if ( empty( $value ) && empty( $schema['required'] ) ) {
			return true;
		}

		if ( ! preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value ) ) {
			return new WP_Error(
				'invalid_time',
				__( 'Invalid time format. Use HH:MM (24-hour format)', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Validate recurrence days array
	 *
	 * @since  1.0.0
	 * @param  mixed  $value      Value to validate
	 * @param  array  $schema     Field schema
	 * @param  string $field_key  Field key
	 * @return true|WP_Error      True if valid, WP_Error otherwise
	 */
	public static function validate_recurrence_days( $value, $schema, $field_key ) {
		if ( empty( $value ) && empty( $schema['required'] ) ) {
			return true;
		}

		if ( ! is_array( $value ) || empty( $value ) ) {
			return new WP_Error(
				'invalid_days',
				__( 'At least one day must be selected', 'smart-cycle-discounts' )
			);
		}

		$valid_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		foreach ( $value as $day ) {
			if ( ! in_array( strtolower( $day ), $valid_days, true ) ) {
				return new WP_Error(
					'invalid_day',
					sprintf( __( 'Invalid day: %s', 'smart-cycle-discounts' ), $day )
				);
			}
		}

		return true;
	}

	/**
	 * Validate boolean type
	 *
	 * @since  1.0.0
	 * @param  mixed  $value      Value to validate
	 * @param  array  $schema     Field schema
	 * @param  string $field_key  Field key
	 * @return true|WP_Error      True if valid, WP_Error otherwise
	 */
	public static function validate_boolean( $value, $schema, $field_key ) {
		// After sanitization with rest_sanitize_boolean, value should be boolean
		// But we accept common boolean representations for robustness
		if ( ! is_bool( $value ) && ! in_array( $value, array( '0', '1', 0, 1, true, false ), true ) ) {
			return new WP_Error(
				'invalid_boolean',
				__( 'Value must be boolean', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Validate hex color format
	 *
	 * @since  1.0.0
	 * @param  mixed  $value      Value to validate
	 * @param  array  $schema     Field schema
	 * @param  string $field_key  Field key
	 * @return true|WP_Error      True if valid, WP_Error otherwise
	 */
	public static function validate_hex_color( $value, $schema, $field_key ) {
		if ( empty( $value ) && empty( $schema['required'] ) ) {
			return true;
		}

		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $value ) ) {
			return new WP_Error(
				'invalid_hex_color',
				__( 'Invalid hex color format. Use #RRGGBB', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Sanitize tiers array
	 */
	public static function sanitize_tiers( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $tier ) {
			if ( is_array( $tier ) ) {
				$sanitized[] = array(
					'min_quantity'   => isset( $tier['min_quantity'] ) ? absint( $tier['min_quantity'] ) : 0,
					'discount_value' => isset( $tier['discount_value'] ) ? floatval( $tier['discount_value'] ) : 0,
					'discount_type'  => isset( $tier['discount_type'] ) ? sanitize_text_field( $tier['discount_type'] ) : 'percentage',
				);
			}
		}

		// Sort tiers by min_quantity in ascending order (normalization)
		usort(
			$sanitized,
			function ( $a, $b ) {
				return $a['min_quantity'] - $b['min_quantity'];
			}
		);

		return $sanitized;
	}

	/**
	 * Sanitize BOGO configuration
	 */
	public static function sanitize_bogo_config( $value ) {
		if ( ! is_array( $value ) ) {
			return array(
				'buy_quantity'     => 1,
				'get_quantity'     => 1,
				'discount_percent' => 100,
			);
		}

		return array(
			'buy_quantity'     => isset( $value['buy_quantity'] ) ? absint( $value['buy_quantity'] ) : 1,
			'get_quantity'     => isset( $value['get_quantity'] ) ? absint( $value['get_quantity'] ) : 1,
			'discount_percent' => isset( $value['discount_percent'] ) ? floatval( $value['discount_percent'] ) : 100,
		);
	}

	/**
	 * Sanitize thresholds array
	 */
	public static function sanitize_thresholds( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $threshold ) {
			if ( is_array( $threshold ) ) {
				// Support both 'threshold' and legacy 'min_amount' field names
				$threshold_amount = isset( $threshold['threshold'] ) ? $threshold['threshold'] : ( isset( $threshold['min_amount'] ) ? $threshold['min_amount'] : 0 );

				// Support both 'discount' and 'discount_value' field names
				$discount_amount = isset( $threshold['discount_value'] ) ? $threshold['discount_value'] : ( isset( $threshold['discount'] ) ? $threshold['discount'] : 0 );

				$sanitized[] = array(
					'threshold'      => floatval( $threshold_amount ),
					'discount_value' => floatval( $discount_amount ),
					'discount_type'  => isset( $threshold['discount_type'] ) ? sanitize_text_field( $threshold['discount_type'] ) : 'percentage',
				);
			}
		}

		// Sort thresholds by threshold amount in ascending order (normalization)
		usort(
			$sanitized,
			function ( $a, $b ) {
				return $a['threshold'] <=> $b['threshold'];
			}
		);

		return $sanitized;
	}

	/**
	 * Get product condition types
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_condition_types() {
		return array(
			'price_inventory'    => array(
				'label'   => __( 'Price & Inventory', 'smart-cycle-discounts' ),
				'options' => array(
					'price'            => __( 'Regular Price', 'smart-cycle-discounts' ),
					'sale_price'       => __( 'Sale Price', 'smart-cycle-discounts' ),
					'stock_quantity'   => __( 'Stock Quantity', 'smart-cycle-discounts' ),
					'stock_status'     => __( 'Stock Status', 'smart-cycle-discounts' ),
					'low_stock_amount' => __( 'Low Stock Amount', 'smart-cycle-discounts' ),
				),
			),
			'product_attributes' => array(
				'label'   => __( 'Product Attributes', 'smart-cycle-discounts' ),
				'options' => array(
					'weight' => __( 'Weight', 'smart-cycle-discounts' ),
					'length' => __( 'Length', 'smart-cycle-discounts' ),
					'width'  => __( 'Width', 'smart-cycle-discounts' ),
					'height' => __( 'Height', 'smart-cycle-discounts' ),
					'sku'    => __( 'SKU', 'smart-cycle-discounts' ),
				),
			),
			'product_status'     => array(
				'label'   => __( 'Product Status', 'smart-cycle-discounts' ),
				'options' => array(
					'featured'     => __( 'Featured Product', 'smart-cycle-discounts' ),
					'on_sale'      => __( 'On Sale', 'smart-cycle-discounts' ),
					'virtual'      => __( 'Virtual Product', 'smart-cycle-discounts' ),
					'downloadable' => __( 'Downloadable', 'smart-cycle-discounts' ),
					'product_type' => __( 'Product Type', 'smart-cycle-discounts' ),
				),
			),
			'shipping_tax'       => array(
				'label'   => __( 'Shipping & Tax', 'smart-cycle-discounts' ),
				'options' => array(
					'tax_status'     => __( 'Tax Status', 'smart-cycle-discounts' ),
					'tax_class'      => __( 'Tax Class', 'smart-cycle-discounts' ),
					'shipping_class' => __( 'Shipping Class', 'smart-cycle-discounts' ),
				),
			),
			'reviews_ratings'    => array(
				'label'   => __( 'Reviews & Ratings', 'smart-cycle-discounts' ),
				'options' => array(
					'average_rating' => __( 'Average Rating', 'smart-cycle-discounts' ),
					'review_count'   => __( 'Review Count', 'smart-cycle-discounts' ),
				),
			),
			'sales_data'         => array(
				'label'   => __( 'Sales Data', 'smart-cycle-discounts' ),
				'options' => array(
					'total_sales'   => __( 'Total Sales', 'smart-cycle-discounts' ),
					'date_created'  => __( 'Date Created', 'smart-cycle-discounts' ),
					'date_modified' => __( 'Date Modified', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get operator mappings for conditions
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_operator_mappings() {
		return array(
			'boolean' => array(
				'types'     => array( 'featured', 'on_sale', 'virtual', 'downloadable' ),
				'operators' => array(
					'='  => __( 'Is', 'smart-cycle-discounts' ),
					'!=' => __( 'Is not', 'smart-cycle-discounts' ),
				),
			),
			'numeric' => array(
				'types'     => array( 'price', 'sale_price', 'stock_quantity', 'low_stock_amount', 'weight', 'length', 'width', 'height', 'average_rating', 'review_count', 'total_sales' ),
				'operators' => array(
					'='  => __( 'Equals', 'smart-cycle-discounts' ),
					'!=' => __( 'Not equals', 'smart-cycle-discounts' ),
					'>'  => __( 'Greater than', 'smart-cycle-discounts' ),
					'>=' => __( 'Greater than or equal', 'smart-cycle-discounts' ),
					'<'  => __( 'Less than', 'smart-cycle-discounts' ),
					'<=' => __( 'Less than or equal', 'smart-cycle-discounts' ),
				),
			),
			'text'    => array(
				'types'     => array( 'sku', 'tax_class', 'shipping_class' ),
				'operators' => array(
					'='            => __( 'Equals', 'smart-cycle-discounts' ),
					'!='           => __( 'Not equals', 'smart-cycle-discounts' ),
					'contains'     => __( 'Contains', 'smart-cycle-discounts' ),
					'not_contains' => __( 'Does not contain', 'smart-cycle-discounts' ),
					'starts_with'  => __( 'Starts with', 'smart-cycle-discounts' ),
					'ends_with'    => __( 'Ends with', 'smart-cycle-discounts' ),
				),
			),
			'select'  => array(
				'types'     => array( 'stock_status', 'product_type', 'tax_status' ),
				'operators' => array(
					'='  => __( 'Is', 'smart-cycle-discounts' ),
					'!=' => __( 'Is not', 'smart-cycle-discounts' ),
				),
			),
			'date'    => array(
				'types'     => array( 'date_created', 'date_modified' ),
				'operators' => array(
					'='  => __( 'On', 'smart-cycle-discounts' ),
					'!=' => __( 'Not on', 'smart-cycle-discounts' ),
					'>'  => __( 'After', 'smart-cycle-discounts' ),
					'>=' => __( 'On or after', 'smart-cycle-discounts' ),
					'<'  => __( 'Before', 'smart-cycle-discounts' ),
					'<=' => __( 'On or before', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Validate cross-field dependencies for discounts step
	 *
	 * Checks for logical conflicts between Configure Discount Rules fields
	 * that would create impossible or invalid configurations.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Sanitized field data
	 * @param    WP_Error $errors    Error object to add errors to
	 * @return   void
	 */
	private static function validate_discounts_cross_fields( array $data, WP_Error $errors ) {
		// Validation 1: Customer limit cannot exceed total limit
		if ( isset( $data['usage_limit_per_customer'], $data['total_usage_limit'] ) ) {
			$customer_limit = absint( $data['usage_limit_per_customer'] );
			$total_limit    = absint( $data['total_usage_limit'] );

			if ( $customer_limit > 0 && $total_limit > 0 && $customer_limit > $total_limit ) {
				$errors->add(
					'usage_limits_conflict',
					sprintf(
						__( 'Customer limit (%1$d) cannot exceed total usage limit (%2$d). Customers would never reach their limit.', 'smart-cycle-discounts' ),
						$customer_limit,
						$total_limit
					)
				);
			}
		}

		// Validation 2: Lifetime cap cannot be less than per-cycle total limit
		if ( isset( $data['lifetime_usage_cap'], $data['total_usage_limit'] ) ) {
			$lifetime_cap = absint( $data['lifetime_usage_cap'] );
			$total_limit  = absint( $data['total_usage_limit'] );

			if ( $lifetime_cap > 0 && $total_limit > 0 && $lifetime_cap < $total_limit ) {
				$errors->add(
					'lifetime_cap_conflict',
					sprintf(
						__( 'Lifetime usage cap (%1$d) cannot be less than per-cycle total limit (%2$d). This creates an impossible configuration.', 'smart-cycle-discounts' ),
						$lifetime_cap,
						$total_limit
					)
				);
			}
		}

		// Validation 3: Minimum quantity is incompatible with cart total discount
		// Only applies to percentage and fixed discount types (BOGO and tiered have different mechanics)
		if ( isset( $data['discount_type'], $data['apply_to'], $data['minimum_quantity'] ) ) {
			$discount_type = $data['discount_type'];
			$apply_to      = $data['apply_to'];
			$min_quantity  = absint( $data['minimum_quantity'] );

			// Only validate for simple discount types where apply_to and minimum_quantity both apply
			$simple_types = array( 'percentage', 'fixed' );
			if ( in_array( $discount_type, $simple_types, true ) && 'cart_total' === $apply_to && $min_quantity > 0 ) {
				$errors->add(
					'apply_to_quantity_conflict',
					__( 'Minimum quantity cannot be used with "Cart Total" discount application. Cart total discounts apply to the entire cart, not individual items. Please clear the minimum quantity or change the application method to "Per Item".', 'smart-cycle-discounts' )
				);
			}
		}
	}

	/**
	 * Validate products step feature gate restrictions.
	 *
	 * Ensures free users cannot submit advanced product filters (conditions).
	 *
	 * @since    1.0.0
	 * @param    array    $data      Sanitized field data
	 * @param    WP_Error $errors    Error object to add errors to
	 * @return   void
	 */
	private static function validate_products_feature_gate( array $data, WP_Error $errors ) {
		// Check if conditions (advanced filters) are present
		if ( ! isset( $data['conditions'] ) || empty( $data['conditions'] ) ) {
			return; // No advanced filters submitted
		}

		// Get feature gate instance from service container
		if ( ! class_exists( 'SCD_Service_Container' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/bootstrap/class-service-container.php';
		}

		$container    = SCD_Service_Container::get_instance();
		$feature_gate = $container->get( 'feature_gate' );

		if ( ! $feature_gate ) {
			// Feature gate not available - allow by default (fail open)
			return;
		}

		// Check if user can use advanced product filters
		if ( ! $feature_gate->can_use_advanced_product_filters() ) {
			$errors->add(
				'advanced_filters_premium',
				__( 'Advanced product filters are only available in the Pro version. Please upgrade to use condition-based product filtering.', 'smart-cycle-discounts' )
			);
		}
	}

	/**
	 * Validate discounts step feature gate restrictions.
	 *
	 * Ensures free users cannot submit premium discount types (tiered, bogo, spend_threshold).
	 *
	 * @since    1.0.0
	 * @param    array    $data      Sanitized field data
	 * @param    WP_Error $errors    Error object to add errors to
	 * @return   void
	 */
	private static function validate_discounts_feature_gate( array $data, WP_Error $errors ) {
		// Check if discount_type is present
		if ( ! isset( $data['discount_type'] ) || empty( $data['discount_type'] ) ) {
			return; // No discount type submitted
		}

		$discount_type = $data['discount_type'];

		// Free types don't need validation
		$free_types = array( 'percentage', 'fixed' );
		if ( in_array( $discount_type, $free_types, true ) ) {
			return;
		}

		// Get feature gate instance from service container
		if ( ! class_exists( 'SCD_Service_Container' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/bootstrap/class-service-container.php';
		}

		$container    = SCD_Service_Container::get_instance();
		$feature_gate = $container->get( 'feature_gate' );

		if ( ! $feature_gate ) {
			// Feature gate not available - allow by default (fail open)
			return;
		}

		// Check if user can use this discount type
		if ( ! $feature_gate->can_use_discount_type( $discount_type ) ) {
			$discount_type_labels = array(
				'tiered'          => __( 'Volume Discounts', 'smart-cycle-discounts' ),
				'bogo'            => __( 'BOGO Deals', 'smart-cycle-discounts' ),
				'spend_threshold' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
			);

			$type_label = isset( $discount_type_labels[ $discount_type ] ) ? $discount_type_labels[ $discount_type ] : $discount_type;

			$errors->add(
				'discount_type_premium',
				sprintf(
					/* translators: %s: discount type label */
					__( '%s discount type is only available in the Pro version. Please upgrade or choose Percentage Off or Fixed Amount Off.', 'smart-cycle-discounts' ),
					$type_label
				)
			);
		}
	}
}
