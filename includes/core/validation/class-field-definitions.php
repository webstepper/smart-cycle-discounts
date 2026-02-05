<?php
/**
 * Field Definitions Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/class-field-definitions.php
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
 * WSSCD_Field_Definitions Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/fields
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Field_Definitions {

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
				'max_length'  => 100,
				'default'     => '',
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_text_length' ),
				'attributes'  => array(
					'placeholder'     => __( 'Enter a descriptive campaign name', 'smart-cycle-discounts' ),
					'data-help-topic' => 'campaign-name',
				),
				'description' => __( 'A clear name helps you identify this campaign later', 'smart-cycle-discounts' ),
				'tooltip'     => __( 'Give your campaign a unique, descriptive name (3-100 characters)', 'smart-cycle-discounts' ),
				'field_name'  => 'name', // Maps to database column
			),
			'description' => array(
				'type'       => 'textarea',
				'label'      => __( 'Description', 'smart-cycle-discounts' ),
				'required'   => false,
				'max_length' => 5000,
				'default'    => '',
				'sanitizer'  => 'sanitize_textarea_field',
				'validator'  => array( __CLASS__, 'validate_text_length' ),
				'attributes' => array(
					'rows'            => 4,
					'placeholder'     => __( 'Describe the purpose of this campaign (optional)', 'smart-cycle-discounts' ),
					'data-help-topic' => 'campaign-description',
				),
				'tooltip'    => __( 'Optional description to help you remember the campaign purpose', 'smart-cycle-discounts' ),
				'field_name' => 'description',
			),
		'priority'    => array(
			'type'        => 'select',
			'label'       => __( 'Priority', 'smart-cycle-discounts' ),
			'required'    => true,
			'default'     => 3,
			'sanitizer'   => 'absint',
			'validator'   => array( __CLASS__, 'validate_number_range' ),
			'options'     => array(
				5 => __( '5 - Highest Priority', 'smart-cycle-discounts' ),
				4 => __( '4 - High Priority', 'smart-cycle-discounts' ),
				3 => __( '3 - Medium Priority (Default)', 'smart-cycle-discounts' ),
				2 => __( '2 - Low Priority', 'smart-cycle-discounts' ),
				1 => __( '1 - Lowest Priority', 'smart-cycle-discounts' ),
			),
			'attributes'  => array(
				'data-help-topic' => 'priority',
			),
			'description' => __( 'Controls which campaign applies when multiple campaigns target the same products', 'smart-cycle-discounts' ),
			'tooltip'     => __( 'Higher priority campaigns override lower priority ones when products overlap', 'smart-cycle-discounts' ),
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
				'attributes' => array(
					'data-help-topic' => 'product-selection-type',
				),
				'tooltip'    => __( 'Choose how to select products for this discount campaign', 'smart-cycle-discounts' ),
				'field_name' => 'product_selection_type',
			),
			'category_ids'           => array(
				'type'       => 'complex',
				'label'      => __( 'Categories', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => array(),
				'sanitizer'  => array( __CLASS__, 'sanitize_array_values' ),
				'validator'  => array( __CLASS__, 'validate_category_ids' ),
				'handler'    => 'WSSCD.Modules.Products.Picker',
				'methods'    => array(
					'collect'  => 'getCategoryIds',
					'populate' => 'setCategoryIds',
				),
				'attributes' => array(
					'multiple' => true,
					'class'    => 'wsscd-category-select',
				),
				'tooltip'    => __( 'Category filter creates the product pool. All selection types choose products FROM this pool', 'smart-cycle-discounts' ),
				'field_name' => 'category_ids',
			),
			'product_ids'            => array(
				'type'        => 'complex',
				'label'       => __( 'Products', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => array(),
				'sanitizer'   => array( __CLASS__, 'sanitize_product_ids_csv' ),
				'validator'   => array( __CLASS__, 'validate_product_ids' ),
				'handler'     => 'WSSCD.Modules.Products.Picker',
				'methods'     => array(
					'collect'  => 'getProductIds',
					'populate' => 'setValue',
				),
				'selector'    => '#wsscd-product-ids-hidden',
				'conditional' => array(
					'field' => 'product_selection_type',
					'value' => 'specific_products',
				),
				'tooltip'     => __( 'Search and select specific products to include in this campaign', 'smart-cycle-discounts' ),
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
				'tooltip'     => __( 'Number of random products to select from the chosen categories (1-100)', 'smart-cycle-discounts' ),
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
				'tooltip'     => __( 'Automatically select products based on smart criteria like sales performance or stock levels', 'smart-cycle-discounts' ),
				'field_name'  => 'smart_criteria',
			),
			'conditions'             => array(
				'type'        => 'complex',
				'label'       => __( 'Product Conditions', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => array(),
				'sanitizer'   => array( __CLASS__, 'sanitize_conditions' ),
				'validator'   => array( __CLASS__, 'validate_conditions' ),
				'handler'     => 'WSSCD.Modules.Products.ConditionsHandler',
				'methods'     => array(
					'collect'  => 'getConditions',
					'populate' => 'setConditions',
				),
				'tooltip'     => __( 'Add conditions to filter products by price, stock, attributes, or other criteria', 'smart-cycle-discounts' ),
				'field_name'  => 'conditions',
				'pro_feature' => 'advanced_filters',
			),
			'conditions_logic'       => array(
				'type'        => 'radio',
				'label'       => __( 'Conditions Logic', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => 'all',
				'options'     => array(
					'all' => __( 'All conditions (AND)', 'smart-cycle-discounts' ),
					'any' => __( 'Any condition (OR)', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'tooltip'     => __( 'ALL: products must match every condition. ANY: products matching at least one condition qualify', 'smart-cycle-discounts' ),
				'field_name'  => 'conditions_logic',
				'pro_feature' => 'advanced_filters',
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
				'attributes' => array(
					'data-help-topic' => 'discount-type',
				),
				'tooltip'    => __( 'Select the type of discount to apply to selected products', 'smart-cycle-discounts' ),
				'field_name' => 'discount_type',
			),
			'discount_value_percentage' => array(
				'type'        => 'number',
				'label'       => __( 'Percentage Value', 'smart-cycle-discounts' ),
				'required'    => true,
				'min'         => WSSCD_Validation_Rules::PERCENTAGE_MIN,
				'max'         => WSSCD_Validation_Rules::PERCENTAGE_MAX,
				'default'     => 10,
				'sanitizer'   => 'floatval',
				'validator'   => array( __CLASS__, 'validate_percentage' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'percentage',
				),
				'attributes'  => array(
					'step'            => 0.01,
					'inputmode'       => 'numeric',
					'data-input-type' => 'percentage',
				),
				'tooltip'     => __( 'Percentage discount to apply (e.g., 10 for 10% off)', 'smart-cycle-discounts' ),
				'field_name'  => 'discount_value_percentage',
			),
			'discount_value_fixed'      => array(
				'type'        => 'number',
				'label'       => __( 'Fixed Amount', 'smart-cycle-discounts' ),
				'required'    => true,
				'min'         => WSSCD_Validation_Rules::FIXED_MIN,
				'max'         => WSSCD_Validation_Rules::FIXED_MAX,
				'default'     => 5,
				'sanitizer'   => 'floatval',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'conditional' => array(
					'field' => 'discount_type',
					'value' => 'fixed',
				),
				'attributes'  => array(
					'step'            => 0.01,
					'inputmode'       => 'decimal',
					'data-input-type' => 'decimal',
				),
				'tooltip'     => __( 'Fixed dollar amount to deduct from price', 'smart-cycle-discounts' ),
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
				'type'        => 'number',
				'label'       => __( 'Usage Limit Per Customer', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 999,
				'default'     => '',
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'tooltip'     => __( 'Maximum number of times each customer can use this discount (leave empty for unlimited)', 'smart-cycle-discounts' ),
				'field_name'  => 'usage_limit_per_customer',
				'pro_feature' => 'discount_configurations',
			),
			'total_usage_limit'         => array(
				'type'        => 'number',
				'label'       => __( 'Total Usage Limit', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 999999,
				'default'     => '',
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'tooltip'     => __( 'Maximum total number of times this discount can be used across all customers', 'smart-cycle-discounts' ),
				'field_name'  => 'total_usage_limit',
				'pro_feature' => 'discount_configurations',
			),
			'lifetime_usage_cap'        => array(
				'type'        => 'number',
				'label'       => __( 'Lifetime Usage Cap', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 999999,
				'default'     => '',
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'field_name'  => 'lifetime_usage_cap',
				'pro_feature' => 'discount_configurations',
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
				'type'        => 'number',
				'label'       => __( 'Maximum Discount Amount', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 0.01,
				'max'         => 9999999,
				'default'     => '',
				'sanitizer'   => 'floatval',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'attributes'  => array(
					'step' => 0.01,
				),
				'field_name'  => 'max_discount_amount',
				'pro_feature' => 'discount_configurations',
			),
			'minimum_quantity'          => array(
				'type'        => 'number',
				'label'       => __( 'Minimum Quantity', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 1,
				'max'         => 999999,
				'default'     => '',
				'sanitizer'   => 'absint',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'field_name'  => 'minimum_quantity',
				'pro_feature' => 'discount_configurations',
			),
			'minimum_order_amount'      => array(
				'type'        => 'number',
				'label'       => __( 'Minimum Order Amount', 'smart-cycle-discounts' ),
				'required'    => false,
				'min'         => 0.01,
				'max'         => 9999999,
				'default'     => '',
				'sanitizer'   => 'floatval',
				'validator'   => array( __CLASS__, 'validate_number_range' ),
				'attributes'  => array(
					'step' => 0.01,
				),
				'field_name'  => 'minimum_order_amount',
				'pro_feature' => 'discount_configurations',
			),
			'stack_with_others'         => array(
				'type'        => 'boolean',
				'label'       => __( 'Stack With Other Discounts', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => false,
				'sanitizer'   => 'rest_sanitize_boolean',
				'validator'   => array( __CLASS__, 'validate_boolean' ),
				'field_name'  => 'stack_with_others',
				'pro_feature' => 'discount_configurations',
			),
			'allow_coupons'             => array(
				'type'        => 'boolean',
				'label'       => __( 'Allow Coupons', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => true,
				'sanitizer'   => 'rest_sanitize_boolean',
				'validator'   => array( __CLASS__, 'validate_boolean' ),
				'field_name'  => 'allow_coupons',
				'pro_feature' => 'discount_configurations',
			),
			'apply_to_sale_items'       => array(
				'type'        => 'boolean',
				'label'       => __( 'Apply To Sale Items', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => true,
				'sanitizer'   => 'rest_sanitize_boolean',
				'validator'   => array( __CLASS__, 'validate_boolean' ),
				'field_name'  => 'apply_to_sale_items',
				'pro_feature' => 'discount_configurations',
			),
			'badge_enabled'             => array(
				'type'       => 'boolean',
				'label'      => __( 'Enable Badge', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => true,
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
				'default'     => 'auto',
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_text_length' ),
				'field_name'  => 'badge_text',
			),
			'badge_bg_color'            => array(
				'type'        => 'text',
				'label'       => __( 'Badge Background Color', 'smart-cycle-discounts' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'badge_enabled',
					'value' => true,
				),
				'default'     => '#ff0000',
				'sanitizer'   => 'sanitize_hex_color',
				'validator'   => array( __CLASS__, 'validate_hex_color' ),
				'field_name'  => 'badge_bg_color',
			),
			'badge_text_color'          => array(
				'type'        => 'text',
				'label'       => __( 'Badge Text Color', 'smart-cycle-discounts' ),
				'required'    => false,
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
				'required'    => false,
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
			'free_shipping_config'      => array(
				'type'        => 'complex',
				'label'       => __( 'Free Shipping Configuration', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => array(
					'enabled' => false,
					'methods' => 'all',
				),
				'handler'     => 'WSSCD.Modules.Discounts.FreeShipping',
				'methods'     => array(
					'collect'  => 'getData',
					'populate' => 'setData',
				),
				'sanitizer'   => array( __CLASS__, 'sanitize_free_shipping_config' ),
				'field_name'  => 'free_shipping_config',
			),
			'user_roles_mode'           => array(
				'type'       => 'select',
				'label'      => __( 'User Role Targeting', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => 'all',
				'options'    => array(
					'all'     => __( 'All Users', 'smart-cycle-discounts' ),
					'include' => __( 'Include Only', 'smart-cycle-discounts' ),
					'exclude' => __( 'Exclude', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'tooltip'    => __( 'Control which user roles can see and use this discount', 'smart-cycle-discounts' ),
				'field_name' => 'user_roles_mode',
			),
			'user_roles'                => array(
				'type'        => 'complex',
				'label'       => __( 'User Roles', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => array(),
				'sanitizer'   => array( __CLASS__, 'sanitize_user_roles' ),
				'validator'   => array( __CLASS__, 'validate_user_roles' ),
				'conditional' => array(
					'field'    => 'user_roles_mode',
					'operator' => 'not_equals',
					'value'    => 'all',
				),
				'tooltip'     => __( 'Select which user roles to include or exclude from this discount', 'smart-cycle-discounts' ),
				'field_name'  => 'user_roles',
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
				'type'        => 'date',
				'label'       => __( 'Start Date', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => '',
				'sanitizer'   => array( __CLASS__, 'sanitize_date' ),
				'validator'   => array( __CLASS__, 'validate_datetime' ),
				'attributes'  => array(
					'min'             => current_time( 'Y-m-d' ),
					'data-help-topic' => 'start-date',
				),
				'tooltip'     => __( 'Date when the campaign should begin', 'smart-cycle-discounts' ),
				'field_name'  => 'start_date',
				'conditional' => array(
					'field' => 'start_type',
					'value' => 'scheduled',
				),
			),
			'start_time'          => array(
				'type'        => 'time',
				'label'       => __( 'Start Time', 'smart-cycle-discounts' ),
				'required'    => true,
				'default'     => '00:00',
				'sanitizer'   => array( __CLASS__, 'sanitize_time' ),
				'validator'   => array( __CLASS__, 'validate_time_format' ),
				'tooltip'     => __( 'Time when the campaign should start on the start date', 'smart-cycle-discounts' ),
				'field_name'  => 'start_time',
				'conditional' => array(
					'field' => 'start_type',
					'value' => 'scheduled',
				),
			),
			'end_date'            => array(
				'type'       => 'date',
				'label'      => __( 'End Date', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => '',
				'sanitizer'  => array( __CLASS__, 'sanitize_date' ),
				'validator'  => array( __CLASS__, 'validate_end_date' ),
				'tooltip'    => __( 'Date when the campaign should end (leave empty for no end date)', 'smart-cycle-discounts' ),
				'field_name' => 'end_date',
			),
			'end_time'            => array(
				'type'       => 'time',
				'label'      => __( 'End Time', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => '',
				'sanitizer'  => array( __CLASS__, 'sanitize_time' ),
				'validator'  => array( __CLASS__, 'validate_time_format' ),
				'tooltip'    => __( 'Time when the campaign should end on the end date', 'smart-cycle-discounts' ),
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
				'tooltip'    => __( 'Timezone for campaign scheduling (defaults to your site timezone)', 'smart-cycle-discounts' ),
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
				'tooltip'    => __( 'Start immediately after saving or schedule for a specific date/time', 'smart-cycle-discounts' ),
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
				'type'       => 'boolean',
				'label'      => __( 'Enable Recurring Schedule', 'smart-cycle-discounts' ),
				'required'   => false,
				'default'    => false,
				'sanitizer'  => array( __CLASS__, 'sanitize_boolean' ),
				'validator'  => array( __CLASS__, 'validate_boolean' ),
				'field_name' => 'enable_recurring',
			),
			'recurrence_mode'     => array(
				'type'        => 'radio',
				'label'       => __( 'Recurrence Mode', 'smart-cycle-discounts' ),
				'required'    => false,
				'default'     => 'continuous',
				'options'     => array(
					'continuous' => __( 'Continuous (Same Campaign)', 'smart-cycle-discounts' ),
					'instances'  => __( 'Instances (Separate Campaigns)', 'smart-cycle-discounts' ),
				),
				'sanitizer'   => 'sanitize_text_field',
				'validator'   => array( __CLASS__, 'validate_in_array' ),
				'conditional' => array(
					'field' => 'enable_recurring',
					'value' => true,
				),
				'field_name'  => 'recurrence_mode',
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
				'required'    => true,
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
				'required'    => true,
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
				'required'    => true,
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
				'type'       => 'boolean',
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
				'required'    => true,
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
				'default'    => 'active',
				'options'    => array(
					'draft'  => __( 'Save as Draft', 'smart-cycle-discounts' ),
					'active' => __( 'Launch Campaign', 'smart-cycle-discounts' ),
				),
				'sanitizer'  => 'sanitize_text_field',
				'validator'  => array( __CLASS__, 'validate_in_array' ),
				'tooltip'    => __( 'Save as draft to review later, or launch to activate immediately', 'smart-cycle-discounts' ),
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

				if ( isset( $field_schema['handler'] ) ) {
					$js_field['handler'] = $field_schema['handler'];
					if ( isset( $field_schema['methods'] ) ) {
						$js_field['methods'] = $field_schema['methods'];
					}
				}

				if ( isset( $field_schema['selector'] ) ) {
					$js_field['selector'] = $field_schema['selector'];
				}

				if ( isset( $field_schema['conditional'] ) ) {
					$js_field['conditional'] = array(
						'field' => $field_schema['conditional']['field'], // Keep snake_case to match form field names
						'value' => $field_schema['conditional']['value'],
					);
					if ( isset( $field_schema['conditional']['operator'] ) ) {
						$js_field['conditional']['operator'] = $field_schema['conditional']['operator'];
					}
				}

				$js_schemas[ $step ][ $js_field_key ] = $js_field;
			}
		}

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
					'<div class="wsscd-complex-field" data-field="%s" data-handler="%s"></div>',
					esc_attr( $field_name ),
					esc_attr( $field['handler'] )
				);
				break;
		}

		// Wrap in field container
		$container_html = '<div class="form-field wsscd-field-' . esc_attr( $field_name ) . '">';

		if ( ! empty( $field['label'] ) && 'checkbox' !== $field['type'] ) {
			$container_html .= sprintf(
				'<label for="%s">%s%s</label>',
				esc_attr( $field_name ),
				esc_html( $field['label'] ),
				$field['required'] ? ' <span class="required">*</span>' : ''
			);
		}

		$container_html .= $html;

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
			$conditional_met = false;
			if ( isset( $field_schema['conditional'] ) ) {
				$condition_field = $field_schema['conditional']['field'];
				$condition_value = $field_schema['conditional']['value'];
				$condition_op    = isset( $field_schema['conditional']['operator'] ) ? $field_schema['conditional']['operator'] : 'equals';
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

				// Support optional operator: when 'not_equals', condition is met when value differs
				if ( 'not_equals' === $condition_op ) {
					$condition_matches = ! $condition_matches;
				}

				// Skip validation if conditional not met
				if ( ! $condition_matches ) {
					continue;
				}
				$conditional_met = true;
			}

			$value = isset( $data[ $field_key ] ) ? $data[ $field_key ] : null;

			// Skip non-required fields that are not present in the data
			// When conditional is set, we only validate this field when condition matches (skip above).
			if ( empty( $field_schema['required'] ) && ! isset( $data[ $field_key ] ) ) {
				continue;
			}

			// Apply sanitizer
			if ( isset( $field_schema['sanitizer'] ) ) {
				$value = call_user_func( $field_schema['sanitizer'], $value );
			}

			// Required is enforced only when this field is validated (conditional met if any).
			// So PRO/conditional fields are "required when enabled" by using required=true + conditional.
			$is_required = ! empty( $field_schema['required'] );
			if ( $is_required && ( ! isset( $value ) || '' === $value || array() === $value ) ) {
				$errors->add(
					$field_key . '_required',
					/* translators: %s: field label */
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
					}
					$errors->add( $field_key . '_invalid', $validation_result->get_error_message() );
					continue;
				}
			}

			$sanitized[ $field_key ] = $value;
		}

		// Cross-field validation for discounts step
		if ( 'discounts' === $step && ! $errors->has_errors() ) {
			// Load discounts step validator
			if ( ! class_exists( 'WSSCD_Discounts_Step_Validator' ) ) {
				require_once WSSCD_INCLUDES_DIR . 'core/validation/step-validators/class-discounts-step-validator.php';
			}
			WSSCD_Discounts_Step_Validator::validate( $sanitized, $errors );

			self::validate_discounts_feature_gate( $sanitized, $errors );
		}

		// Feature gate validation for products step
		if ( 'products' === $step && ! $errors->has_errors() ) {
			self::validate_products_feature_gate( $sanitized, $errors );

			// Transform conditions to engine format for Products_Step_Validator
			$data_for_validator = $sanitized;
			if ( isset( $sanitized['conditions'] ) && is_array( $sanitized['conditions'] ) ) {
				$engine_conditions = array();
				foreach ( $sanitized['conditions'] as $condition ) {
					$values = array();
					if ( isset( $condition['value'] ) && '' !== $condition['value'] ) {
						$values[] = $condition['value'];
					}
					if ( isset( $condition['value2'] ) && '' !== $condition['value2'] ) {
						$values[] = $condition['value2'];
					}

					$engine_conditions[] = array(
						'property' => $condition['condition_type'] ?? '',
						'operator' => $condition['operator'] ?? '',
						'values'   => $values,
						'mode'     => $condition['mode'] ?? 'include',
					);
				}
				$data_for_validator['conditions'] = $engine_conditions;
			}

			// Load products step validator
			if ( ! class_exists( 'WSSCD_Products_Step_Validator' ) ) {
				require_once WSSCD_INCLUDES_DIR . 'core/validation/step-validators/class-products-step-validator.php';
			}
			WSSCD_Products_Step_Validator::validate( $data_for_validator, $errors );
		}

		// Cross-field validation for schedule step
		if ( 'schedule' === $step && ! $errors->has_errors() ) {
			// Load schedule step validator
			if ( ! class_exists( 'WSSCD_Schedule_Step_Validator' ) ) {
				require_once WSSCD_INCLUDES_DIR . 'core/validation/step-validators/class-schedule-step-validator.php';
			}
			WSSCD_Schedule_Step_Validator::validate( $sanitized, $errors );
		}

		// Only block on critical errors, allow warnings/info to pass through
		return self::has_critical_errors( $errors ) ? $errors : $sanitized;
	}

	/**
	 * Sanitize step data without validation
	 *
	 * Used during AJAX navigation for lightweight processing.
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
	 *
	 * Coerces value to string for length check so that array/object from session
	 * or API do not cause TypeError. Non-scalar values are treated as invalid.
	 */
	public static function validate_text_length( $value, $schema, $field_key ) {
		if ( ! is_scalar( $value ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Value must be text.', 'smart-cycle-discounts' )
			);
		}

		$str = (string) $value;
		$len = strlen( $str );

		if ( isset( $schema['min_length'] ) && $len < $schema['min_length'] ) {
			return new WP_Error(
				'min_length',
				/* translators: %d: minimum character length required */
				sprintf( __( 'Must be at least %d characters', 'smart-cycle-discounts' ), $schema['min_length'] )
			);
		}

		if ( isset( $schema['max_length'] ) && $len > $schema['max_length'] ) {
			return new WP_Error(
				'max_length',
				/* translators: %d: maximum character length allowed */
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
				/* translators: %s: minimum value allowed */
				sprintf( __( 'Must be at least %s', 'smart-cycle-discounts' ), $schema['min'] )
			);
		}

		if ( isset( $schema['max'] ) && $value > $schema['max'] ) {
			return new WP_Error(
				'max_value',
				/* translators: %s: maximum value allowed */
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
		if ( $value < WSSCD_Validation_Rules::PERCENTAGE_MIN || $value > WSSCD_Validation_Rules::PERCENTAGE_MAX ) {
			return new WP_Error(
				'invalid_percentage',
				sprintf(
					/* translators: %1$d: minimum percentage, %2$d: maximum percentage */
					__( 'Percentage must be between %1$d and %2$d', 'smart-cycle-discounts' ),
					WSSCD_Validation_Rules::PERCENTAGE_MIN,
					WSSCD_Validation_Rules::PERCENTAGE_MAX
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
	 * Sanitize time value.
	 *
	 * Handles placeholder values from disabled fields and ensures proper HH:MM format.
	 *
	 * @since    1.0.0
	 * @param    string $value    Time value to sanitize.
	 * @return   string              Sanitized time in HH:MM format, or empty string if invalid/placeholder.
	 */
	public static function sanitize_time( $value ) {
		// Treat placeholder value as empty (used when end_time field is disabled)
		if ( '--:--' === $value || empty( $value ) ) {
			return '';
		}

		// Sanitize and validate format
		$value = sanitize_text_field( $value );

		// Accept HH:MM format only
		if ( preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize conditions array
	 *
	 * @since 1.0.0
	 * @param mixed $value Conditions array to sanitize.
	 * @return array Sanitized conditions array.
	 */
	public static function sanitize_conditions( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		// Limit maximum conditions
		if ( count( $value ) > 20 ) {
			$value = array_slice( $value, 0, 20 );
		}

		// Allowed operators (whitelist for security)
		// Must match all operators exposed in UI (get_operator_mappings) + engine-supported operators
		$allowed_operators = array(
			'=',
			'!=',
			'>',
			'>=',
			'<',
			'<=',
			'between',
			'not_between',
			'in',
			'not_in',
			'contains',
			'not_contains',
			'starts_with',
			'ends_with',
		);

		// Allowed condition types (whitelist for security)
		// Must match all types exposed in UI (get_condition_types)
		$allowed_types = array(
			// Price & Inventory
			'price',
			'sale_price',
			'current_price',
			'stock_quantity',
			'stock_status',
			'low_stock_amount',
			// Product Attributes
			'weight',
			'length',
			'width',
			'height',
			'sku',
			// Product Status
			'featured',
			'on_sale',
			'virtual',
			'downloadable',
			'product_type',
			// Shipping & Tax
			'tax_status',
			'tax_class',
			'shipping_class',
			// Reviews & Ratings
			'average_rating',
			'review_count',
			// Sales Data
			'total_sales',
			'date_created',
			'date_modified',
		);

		// Allowed modes
		$allowed_modes = array( 'include', 'exclude' );

		$sanitized = array();
		foreach ( $value as $condition ) {
			if ( is_array( $condition ) ) {
				// Sanitize condition_type (AJAX Router converts conditionType → condition_type)
				$type = isset( $condition['condition_type'] ) ? sanitize_key( $condition['condition_type'] ) : '';
				if ( ! in_array( $type, $allowed_types, true ) ) {
					continue; // Skip invalid condition type
				}

				// Sanitize operator (use whitelist, do NOT use sanitize_text_field which HTML-encodes < and >)
				// Normalize operator to handle potential HTML encoding from database
				$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
				$operator = self::normalize_operator( $operator );
				if ( ! in_array( $operator, $allowed_operators, true ) ) {
					continue; // Skip invalid operator
				}

				// Type-specific value sanitization
				$value1 = isset( $condition['value'] ) ? $condition['value'] : '';
				$value2 = isset( $condition['value2'] ) ? $condition['value2'] : '';

				// Use WSSCD_Condition_Validator for type-specific sanitization
				if ( class_exists( 'WSSCD_Condition_Validator' ) ) {
					$value1 = WSSCD_Condition_Validator::sanitize_value( $type, $value1 );
					$value2 = WSSCD_Condition_Validator::sanitize_value( $type, $value2 );
				} else {
					// Fallback to text sanitization
					$value1 = sanitize_text_field( $value1 );
					$value2 = sanitize_text_field( $value2 );
				}

				// Validate required values - reject conditions with empty values for operators that require them
				// Allow '0' as valid value for numeric conditions
				$requires_value1 = ! in_array( $operator, array( 'in', 'not_in' ), true );
				$requires_value2 = in_array( $operator, array( 'between', 'not_between' ), true );

				if ( $requires_value1 && '' === $value1 && '0' !== $value1 && 0 !== $value1 ) {
					continue; // Skip conditions with missing required value
				}

				if ( $requires_value2 && '' === $value2 && '0' !== $value2 && 0 !== $value2 ) {
					continue; // Skip conditions with missing required value2
				}

				// Sanitize mode
				$mode = isset( $condition['mode'] ) ? $condition['mode'] : 'include';
				if ( ! in_array( $mode, $allowed_modes, true ) ) {
					$mode = 'include'; // Default to include if invalid
				}

				$sanitized[] = array(
					'condition_type' => $type,
					'operator'       => $operator,
					'value'          => $value1,
					'value2'         => $value2,
					'mode'           => $mode,
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Normalize operator to handle HTML encoding
	 *
	 * Converts HTML-encoded operators (&lt;, &gt;) back to their original form.
	 * This handles cases where operators may be HTML-encoded during storage/retrieval.
	 *
	 * @since 1.0.0
	 * @param string $operator Operator to normalize.
	 * @return string Normalized operator.
	 */
	private static function normalize_operator( $operator ) {
		$operator_map = array(
			'&lt;'  => '<',
			'&gt;'  => '>',
			'&lt;=' => '<=',
			'&gt;=' => '>=',
		);

		return isset( $operator_map[ $operator ] ) ? $operator_map[ $operator ] : $operator;
	}

	/**
	 * Validate conditions array using comprehensive validation
	 *
	 * @since 1.0.0
	 * @param mixed  $value      Conditions array to validate.
	 * @param array  $schema     Field schema.
	 * @param string $field_key  Field key.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_conditions( $value, $schema, $field_key ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Conditions must be an array', 'smart-cycle-discounts' )
			);
		}

		// Use comprehensive validator if available
		if ( class_exists( 'WSSCD_Condition_Validator' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a validation callback; nonce verified before calling validate().
			$logic      = isset( $_POST['conditions_logic'] ) ? sanitize_key( wp_unslash( $_POST['conditions_logic'] ) ) : 'all';
			$validation = WSSCD_Condition_Validator::validate( $value, $logic );

			if ( ! $validation['valid'] ) {
				return new WP_Error(
					'invalid_conditions',
					implode( ' ', $validation['errors'] )
				);
			}
		}

		return true;
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

		if ( in_array( $value, $valid_timezones, true ) ) {
			return true;
		}

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
					/* translators: %1$s: deprecated timezone identifier, %2$s: recommended replacement timezone identifier */
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
					/* translators: %d: tier number */
					sprintf( __( 'Tier %d is incomplete', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			$quantity = $tier['min_quantity'];
			$discount = $tier['discount_value'];

			if ( ! is_numeric( $quantity ) ) {
				return new WP_Error(
					'invalid_tier_quantity_type',
					/* translators: %d: tier number */
					sprintf( __( 'Tier %d: Quantity must be a number', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( $quantity < 2 ) {
				return new WP_Error(
					'invalid_tier_quantity',
					/* translators: %d: tier number */
					sprintf( __( 'Tier %d: Minimum quantity must be at least 2 for volume discounts', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( in_array( $quantity, $seen_quantities, true ) ) {
				return new WP_Error(
					'duplicate_tier_quantity',
					/* translators: %1$d: tier number, %2$d: duplicate quantity value */
					sprintf( __( 'Tier %1$d: Duplicate quantity %2$d', 'smart-cycle-discounts' ), $index + 1, $quantity )
				);
			}
			$seen_quantities[] = $quantity;

			if ( ! is_numeric( $discount ) ) {
				return new WP_Error(
					'invalid_tier_discount_type',
					/* translators: %d: tier number */
					sprintf( __( 'Tier %d: Discount must be a number', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( $discount <= 0 ) {
				return new WP_Error(
					'invalid_tier_discount',
					/* translators: %d: tier number */
					sprintf( __( 'Tier %d: Discount must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( 'percentage' === $tier['discount_type'] && $discount > 100 ) {
				return new WP_Error(
					'invalid_tier_percentage',
					/* translators: %d: tier number */
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

		$seen_amounts = array();
		foreach ( $value as $index => $threshold ) {
			if ( ! isset( $threshold['spend_amount'] ) || '' === $threshold['spend_amount'] ||
				 ! isset( $threshold['discount_value'] ) || '' === $threshold['discount_value'] ) {
				return new WP_Error(
					'incomplete_threshold',
					/* translators: %d: threshold number */
					sprintf( __( 'Threshold %d is incomplete', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( $threshold['spend_amount'] <= 0 ) {
				return new WP_Error(
					'invalid_threshold_amount',
					/* translators: %d: threshold number */
					sprintf( __( 'Threshold %d: Amount must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			if ( in_array( $threshold['spend_amount'], $seen_amounts, true ) ) {
				return new WP_Error(
					'duplicate_threshold',
					/* translators: %d: threshold number */
					sprintf( __( 'Threshold %d: Duplicate threshold amount', 'smart-cycle-discounts' ), $index + 1 )
				);
			}
			$seen_amounts[] = $threshold['spend_amount'];

			if ( $threshold['discount_value'] <= 0 ) {
				return new WP_Error(
					'invalid_discount_value',
					/* translators: %d: threshold number */
					sprintf( __( 'Threshold %d: Discount value must be greater than 0', 'smart-cycle-discounts' ), $index + 1 )
				);
			}

			$discount_type = $threshold['discount_type'] ?? 'percentage';
			if ( 'percentage' === $discount_type && $threshold['discount_value'] > 100 ) {
				return new WP_Error(
					'invalid_percentage',
					/* translators: %d: threshold number */
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
		// Treat placeholder value as empty (used when end_time is disabled)
		if ( '--:--' === $value ) {
			$value = '';
		}

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
					/* translators: %s: invalid day name */
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
				$sanitized[] = array(
					'spend_amount'   => isset( $threshold['spend_amount'] ) ? floatval( $threshold['spend_amount'] ) : 0,
					'discount_value' => isset( $threshold['discount_value'] ) ? floatval( $threshold['discount_value'] ) : 0,
					'discount_type'  => isset( $threshold['discount_type'] ) ? sanitize_text_field( $threshold['discount_type'] ) : 'percentage',
				);
			}
		}

		usort(
			$sanitized,
			function ( $a, $b ) {
				return $a['spend_amount'] <=> $b['spend_amount'];
			}
		);

		return $sanitized;
	}

	/**
	 * Sanitize free shipping configuration
	 *
	 * @since 1.2.0
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized free shipping config.
	 */
	public static function sanitize_free_shipping_config( $value ) {
		if ( ! is_array( $value ) ) {
			return array(
				'enabled' => false,
				'methods' => 'all',
			);
		}

		$sanitized = array(
			'enabled' => isset( $value['enabled'] ) ? rest_sanitize_boolean( $value['enabled'] ) : false,
		);

		// Handle methods - can be 'all' or an array of method IDs.
		if ( isset( $value['methods'] ) ) {
			if ( 'all' === $value['methods'] ) {
				$sanitized['methods'] = 'all';
			} elseif ( is_array( $value['methods'] ) ) {
				$sanitized['methods'] = array_map( 'sanitize_text_field', $value['methods'] );
			} else {
				$sanitized['methods'] = 'all';
			}
		} else {
			$sanitized['methods'] = 'all';
		}

		return $sanitized;
	}

	/**
	 * Sanitize user roles array.
	 *
	 * Validates and sanitizes an array of WordPress user role slugs.
	 *
	 * @since  1.3.0
	 * @param  mixed $value The value to sanitize.
	 * @return array Sanitized array of role slugs.
	 */
	public static function sanitize_user_roles( $value ) {
		if ( ! is_array( $value ) ) {
			// Handle JSON string input.
			if ( is_string( $value ) && ! empty( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$value = $decoded;
				} else {
					return array();
				}
			} else {
				return array();
			}
		}

		// Use Role Helper if available.
		if ( class_exists( 'WSSCD_Role_Helper' ) ) {
			return WSSCD_Role_Helper::sanitize_roles( $value );
		}

		// Fallback: sanitize each role slug.
		return array_map( 'sanitize_key', array_filter( $value ) );
	}

	/**
	 * Validate user roles array.
	 *
	 * Ensures all provided role slugs are valid WordPress roles.
	 *
	 * @since  1.3.0
	 * @param  mixed $value The value to validate.
	 * @param  array $field The field definition.
	 * @param  array $data  The complete data array.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_user_roles( $value, $field = array(), $data = array() ) {
		// Empty is valid - means no role restriction.
		if ( empty( $value ) ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'User roles must be an array', 'smart-cycle-discounts' )
			);
		}

		// Use Role Helper if available.
		if ( class_exists( 'WSSCD_Role_Helper' ) ) {
			$invalid_roles = WSSCD_Role_Helper::validate_roles( $value );
			if ( ! empty( $invalid_roles ) ) {
				return new WP_Error(
					'invalid_roles',
					sprintf(
						/* translators: %s: comma-separated list of invalid role slugs */
						__( 'Invalid user roles: %s', 'smart-cycle-discounts' ),
						implode( ', ', $invalid_roles )
					)
				);
			}
			return true;
		}

		// Fallback: validate against wp_roles().
		$available_roles = array_keys( wp_roles()->get_names() );
		$invalid_roles   = array_diff( $value, $available_roles );

		if ( ! empty( $invalid_roles ) ) {
			return new WP_Error(
				'invalid_roles',
				sprintf(
					/* translators: %s: comma-separated list of invalid role slugs */
					__( 'Invalid user roles: %s', 'smart-cycle-discounts' ),
					implode( ', ', $invalid_roles )
				)
			);
		}

		return true;
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
					'current_price'    => __( 'Current Price (Active)', 'smart-cycle-discounts' ),
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
				'types'     => array( 'price', 'sale_price', 'current_price', 'stock_quantity', 'low_stock_amount', 'weight', 'length', 'width', 'height', 'average_rating', 'review_count', 'total_sales' ),
				'operators' => array(
					'='            => __( 'Equals', 'smart-cycle-discounts' ),
					'!='           => __( 'Not equals', 'smart-cycle-discounts' ),
					'>'            => __( 'Greater than', 'smart-cycle-discounts' ),
					'>='           => __( 'Greater than or equal', 'smart-cycle-discounts' ),
					'<'            => __( 'Less than', 'smart-cycle-discounts' ),
					'<='           => __( 'Less than or equal', 'smart-cycle-discounts' ),
					'between'      => __( 'Between', 'smart-cycle-discounts' ),
					'not_between'  => __( 'Not between', 'smart-cycle-discounts' ),
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
					'='           => __( 'On', 'smart-cycle-discounts' ),
					'!='          => __( 'Not on', 'smart-cycle-discounts' ),
					'>'           => __( 'After', 'smart-cycle-discounts' ),
					'>='          => __( 'On or after', 'smart-cycle-discounts' ),
					'<'           => __( 'Before', 'smart-cycle-discounts' ),
					'<='          => __( 'On or before', 'smart-cycle-discounts' ),
					'between'     => __( 'Between dates', 'smart-cycle-discounts' ),
					'not_between' => __( 'Not between dates', 'smart-cycle-discounts' ),
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
		if ( ! isset( $data['conditions'] ) || empty( $data['conditions'] ) ) {
			return; // No advanced filters submitted
		}

		if ( ! class_exists( 'WSSCD_Container' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/bootstrap/class-container.php';
		}

		$container    = WSSCD_Container::get_instance();
		$feature_gate = $container->get( 'feature_gate' );

		if ( ! $feature_gate ) {
			// Feature gate not available - allow by default (fail open)
			return;
		}

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
		if ( ! isset( $data['discount_type'] ) || empty( $data['discount_type'] ) ) {
			return; // No discount type submitted
		}

		$discount_type = $data['discount_type'];

		// Free types don't need validation
		$free_types = array( 'percentage', 'fixed' );
		if ( in_array( $discount_type, $free_types, true ) ) {
			return;
		}

		if ( ! class_exists( 'WSSCD_Container' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/bootstrap/class-container.php';
		}

		$container    = WSSCD_Container::get_instance();
		$feature_gate = $container->get( 'feature_gate' );

		if ( ! $feature_gate ) {
			// Feature gate not available - allow by default (fail open)
			return;
		}

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

	/**
	 * Check if WP_Error contains critical errors (not just warnings/info).
	 *
	 * Severity levels:
	 * - 'critical' or no severity: Blocks form submission (default)
	 * - 'warning': Shows warning but allows submission
	 * - 'info': Informational message, allows submission
	 *
	 * @since    1.0.0
	 * @param    WP_Error $errors    Error object to check
	 * @return   bool                True if has critical errors, false if only warnings/info
	 */
	private static function has_critical_errors( WP_Error $errors ): bool {
		if ( ! $errors->has_errors() ) {
			return false;
		}

		// Check each error's severity
		foreach ( $errors->get_error_codes() as $code ) {
			$error_data = $errors->get_error_data( $code );

			// If no severity specified, treat as critical (default behavior)
			if ( ! is_array( $error_data ) || ! isset( $error_data['severity'] ) ) {
				return true;
			}

			// Only 'warning' and 'info' are non-blocking
			if ( ! in_array( $error_data['severity'], array( 'warning', 'info' ), true ) ) {
				return true;
			}
		}

		// All errors are warnings/info - not critical
		return false;
	}
}
