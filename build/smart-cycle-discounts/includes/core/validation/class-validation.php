<?php
/**
 * Validation Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/class-validation.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 */
class SCD_Validation {

	/**
	 * Validators loaded flag.
	 *
	 * @since    1.0.0
	 * @var      bool
	 */
	private static $validators_loaded = false;

	/**
	 * Validate data based on context.
	 *
	 * @since    1.0.0
	 * @param    array  $data       Data to validate.
	 * @param    string $context    Validation context.
	 * @return   array|WP_Error         Sanitized data or error.
	 */
	public static function validate( array $data, $context ) {
		$context = sanitize_key( $context );
		self::load_validators();

		return self::route_validation( $data, $context );
	}

	/**
	 * Route validation to appropriate handler.
	 *
	 * @since    1.0.0
	 * @param    array  $data       Data to validate.
	 * @param    string $context    Validation context.
	 * @return   array|WP_Error         Sanitized data or error.
	 */
	private static function route_validation( array $data, string $context ) {
		// Map context patterns to handlers
		$routes = array(
			'wizard_' => array(
				'handler' => 'handle_wizard_validation',
				'prefix'  => 7,
			),
		);

		// Check prefixed routes
		foreach ( $routes as $prefix => $config ) {
			if ( 0 === strpos( $context, $prefix ) ) {
				$type = substr( $context, $config['prefix'] );
				return self::{$config['handler']}( $data, $type );
			}
		}

		// Check exact routes
		$exact_routes = array(
			'campaign_complete' => array(
				'class'  => 'SCD_Wizard_Validation',
				'method' => 'validate_complete_campaign',
			),
			'campaign_compiled' => array(
				'class'  => 'SCD_Wizard_Validation',
				'method' => 'validate_compiled_campaign',
			),
			'campaign_update'   => array(
				'class'  => 'SCD_Wizard_Validation',
				'method' => 'validate_campaign_update',
			),
			'ajax_action'       => array(
				'class'  => 'SCD_AJAX_Validation',
				'method' => 'validate',
			),
			'ajax_navigation'   => array(
				'class'  => 'SCD_Wizard_Validation',
				'method' => 'validate_navigation',
			),
			'product_meta'      => array(
				'class'  => 'SCD_AJAX_Validation',
				'method' => 'validate_product_meta',
			),
		);

		if ( isset( $exact_routes[ $context ] ) ) {
			$route = $exact_routes[ $context ];
			if ( isset( $route['handler'] ) ) {
				return self::{$route['handler']}( $data, $route['type'] );
			}
			return $route['class']::{$route['method']}( $data );
		}

		return self::create_unknown_context_error( $context );
	}

	/**
	 * Handle wizard validation.
	 *
	 * @since    1.0.0
	 * @param    array  $data    Data to validate.
	 * @param    string $step    Wizard step.
	 * @return   array|WP_Error      Sanitized data or error.
	 */
	private static function handle_wizard_validation( array $data, string $step ) {
		return SCD_Wizard_Validation::validate_step( $data, $step );
	}

	/**
	 * Create unknown context error.
	 *
	 * @since    1.0.0
	 * @param    string $context    Context name.
	 * @return   WP_Error               Error object.
	 */
	private static function create_unknown_context_error( string $context ): WP_Error {
		return new WP_Error(
			'unknown_context',
			sprintf( __( 'Unknown validation context: %s', 'smart-cycle-discounts' ), $context )
		);
	}

	/**
	 * Load specialized validators.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function load_validators(): void {
		if ( self::$validators_loaded ) {
			return;
		}

		$validators = array(
			'SCD_Wizard_Validation' => 'class-wizard-validation.php',
			'SCD_AJAX_Validation'   => 'class-ajax-validation.php',
		);

		$validation_dir = SCD_PLUGIN_DIR . 'includes/core/validation/';

		foreach ( $validators as $class => $file ) {
			if ( ! class_exists( $class ) ) {
				require_once $validation_dir . $file;
			}
		}

		self::$validators_loaded = true;
	}

	/**
	 * Extract error codes from WP_Error.
	 *
	 * @since    1.0.0
	 * @param    WP_Error $wp_error    WordPress error object.
	 * @return   array                    Array of errors.
	 */
	public static function extract_error_codes( $wp_error ): array {
		if ( ! is_wp_error( $wp_error ) ) {
			return array();
		}

		$errors = array();

		foreach ( $wp_error->get_error_codes() as $code ) {
			$messages = $wp_error->get_error_messages( $code );
			foreach ( $messages as $message ) {
				$errors[] = array(
					'code'    => $code,
					'message' => $message,
				);
			}
		}

		return $errors;
	}

	/**
	 * Register wizard settings.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function register_wizard_settings(): void {
		self::register_wizard_step_settings();
		self::register_campaign_meta();
		self::register_campaign_field_meta();
	}

	/**
	 * Register wizard step settings.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function register_wizard_step_settings(): void {
		$steps = array( 'basic', 'products', 'discounts', 'schedule' );

		foreach ( $steps as $step ) {
			register_setting(
				"scd_wizard_{$step}",
				"scd_{$step}_data",
				self::get_step_setting_args( $step )
			);
		}
	}

	/**
	 * Get step setting args.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   array              Setting args.
	 */
	private static function get_step_setting_args( string $step ): array {
		return array(
			'type'              => 'object',
			'sanitize_callback' => function ( $data ) use ( $step ) {
				return self::sanitize_step_data( $data, $step );
			},
			'default'           => array(),
			'show_in_rest'      => false,
		);
	}

	/**
	 * Register campaign meta.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function register_campaign_meta(): void {
		register_meta(
			'post',
			'scd_campaign_data',
			array(
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize_campaign_data' ),
				'auth_callback'     => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'single'            => true,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Register campaign field meta.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function register_campaign_field_meta(): void {
		$meta_fields = self::get_campaign_meta_fields();

		foreach ( $meta_fields as $meta_key => $config ) {
			register_meta( 'post', $meta_key, self::get_meta_args( $config ) );
		}
	}

	/**
	 * Get campaign meta fields.
	 *
	 * @since    1.0.0
	 * @return   array    Meta fields configuration.
	 */
	private static function get_campaign_meta_fields(): array {
		return array(
			// Basic fields
			'scd_campaign_name'        => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'scd_campaign_description' => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_textarea_field',
			),
			'scd_campaign_priority'    => array(
				'type'     => 'integer',
				'sanitize' => 'absint',
			),
			// Discount fields
			'scd_discount_type'        => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_key',
			),
			'scd_discount_value'       => array(
				'type'     => 'number',
				'sanitize' => 'floatval',
			),
			// Product fields
			'scd_selection_type'       => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_key',
			),
			'scd_product_ids'          => array(
				'type'     => 'array',
				'sanitize' => 'sanitize_product_ids',
			),
			// Schedule fields
			'scd_start_date'           => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'scd_end_date'             => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'scd_timezone'             => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get meta args.
	 *
	 * @since    1.0.0
	 * @param    array $config    Field configuration.
	 * @return   array               Registration args.
	 */
	private static function get_meta_args( array $config ): array {
		$args = array(
			'type'          => $config['type'],
			'auth_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'single'        => true,
		);

		if ( 'sanitize_product_ids' === $config['sanitize'] ) {
			$args['sanitize_callback'] = array( __CLASS__, 'sanitize_product_ids' );
		} else {
			$args['sanitize_callback'] = $config['sanitize'];
		}

		return $args;
	}

	/**
	 * Sanitize product IDs.
	 *
	 * @since    1.0.0
	 * @param    mixed $product_ids    Product IDs to sanitize.
	 * @return   array                    Sanitized IDs.
	 */
	public static function sanitize_product_ids( $product_ids ): array {
		if ( ! is_array( $product_ids ) ) {
			return array();
		}
		return array_map( 'absint', $product_ids );
	}

	/**
	 * Sanitize step data - WordPress Native Implementation
	 *
	 * @since    1.0.0
	 * @param    array  $data    Data to sanitize
	 * @param    string $step    Step name
	 * @return   array           Sanitized data
	 */
	public static function sanitize_step_data( array $data, $step ) {
		self::load_validators();
		return SCD_Wizard_Validation::sanitize_step_data( $data, $step );
	}

	/**
	 * Get JavaScript validation data - WordPress Native Implementation
	 *
	 * @since    1.0.0
	 * @return   array    Validation data for JavaScript localization
	 */
	public static function get_js_data() {
		return array(
			'constants' => self::get_js_constants(),
			'messages'  => self::get_js_messages(),
		);
	}

	/**
	 * Get JavaScript constants.
	 *
	 * @since    1.0.0
	 * @return   array    Constants for JavaScript.
	 */
	private static function get_js_constants(): array {
		return array(
			'basic'     => array(
				'CAMPAIGN_NAME_MIN'        => SCD_Validation_Rules::CAMPAIGN_NAME_MIN,
				'CAMPAIGN_NAME_MAX'        => SCD_Validation_Rules::CAMPAIGN_NAME_MAX,
				'CAMPAIGN_DESCRIPTION_MAX' => SCD_Validation_Rules::CAMPAIGN_DESCRIPTION_MAX,
				'CAMPAIGN_PRIORITY_MIN'    => SCD_Validation_Rules::CAMPAIGN_PRIORITY_MIN,
				'CAMPAIGN_PRIORITY_MAX'    => SCD_Validation_Rules::CAMPAIGN_PRIORITY_MAX,
			),
			'discounts' => array(
				'PERCENTAGE_MIN'  => SCD_Validation_Rules::PERCENTAGE_MIN,
				'PERCENTAGE_MAX'  => SCD_Validation_Rules::PERCENTAGE_MAX,
				'FIXED_MIN'       => SCD_Validation_Rules::FIXED_MIN,
				'FIXED_MAX'       => SCD_Validation_Rules::FIXED_MAX,
				'BOGO_BUY_MIN'    => SCD_Validation_Rules::BOGO_BUY_MIN,
				'BOGO_BUY_MAX'    => SCD_Validation_Rules::BOGO_BUY_MAX,
				'BOGO_GET_MIN'    => SCD_Validation_Rules::BOGO_GET_MIN,
				'BOGO_GET_MAX'    => SCD_Validation_Rules::BOGO_GET_MAX,
				'BUNDLE_SIZE_MIN' => SCD_Validation_Rules::BUNDLE_SIZE_MIN,
				'BUNDLE_SIZE_MAX' => SCD_Validation_Rules::BUNDLE_SIZE_MAX,
				'DISCOUNT_TYPES'  => SCD_Validation_Rules::DISCOUNT_TYPES,
			),
			'products'  => array(
				'RANDOM_COUNT_MIN'               => SCD_Validation_Rules::RANDOM_COUNT_MIN,
				'RANDOM_COUNT_MAX'               => SCD_Validation_Rules::RANDOM_COUNT_MAX,
				'RANDOM_COUNT_WARNING_THRESHOLD' => SCD_Validation_Rules::RANDOM_COUNT_WARNING_THRESHOLD,
				'PRODUCT_IDS_MIN'                => SCD_Validation_Rules::PRODUCT_IDS_MIN,
				'PRODUCT_IDS_MAX'                => SCD_Validation_Rules::PRODUCT_IDS_MAX,
				'SELECTION_TYPES'                => SCD_Validation_Rules::SELECTION_TYPES,
			),
			'schedule'  => array(
				'DATE_FORMAT'                => get_option( 'date_format' ),
				'TIME_FORMAT'                => get_option( 'time_format' ),
				'ROTATION_INTERVAL_MIN'      => SCD_Validation_Rules::ROTATION_INTERVAL_MIN,
				'ROTATION_INTERVAL_MAX'      => SCD_Validation_Rules::ROTATION_INTERVAL_MAX,
				'ROTATION_INTERVAL_DEFAULT'  => SCD_Validation_Rules::ROTATION_INTERVAL_DEFAULT,
				'SCHEDULE_TYPES'             => SCD_Validation_Rules::SCHEDULE_TYPES,
				'WEEKDAYS'                   => SCD_Validation_Rules::WEEKDAYS,
				'TIMEZONE_DEFAULT'           => SCD_Validation_Rules::TIMEZONE_DEFAULT,
				'RECURRENCE_MIN'             => SCD_Validation_Rules::RECURRENCE_MIN,
				'RECURRENCE_MAX'             => SCD_Validation_Rules::RECURRENCE_MAX,
				'RECURRENCE_COUNT_MIN'       => SCD_Validation_Rules::RECURRENCE_COUNT_MIN,
				'RECURRENCE_COUNT_MAX'       => SCD_Validation_Rules::RECURRENCE_COUNT_MAX,
				'SCHEDULE_MAX_DURATION_DAYS' => SCD_Validation_Rules::SCHEDULE_MAX_DURATION_DAYS,
			),
		);
	}

	/**
	 * Get JavaScript messages.
	 *
	 * @since    1.0.0
	 * @return   array    Messages for JavaScript.
	 */
	private static function get_js_messages(): array {
		return array(
			'basic'     => self::get_basic_messages(),
			'discounts' => self::get_discount_messages(),
			'products'  => self::get_product_messages(),
			'schedule'  => self::get_schedule_messages(),
		);
	}

	/**
	 * Get basic validation messages.
	 *
	 * @since    1.0.0
	 * @return   array    Basic messages.
	 */
	private static function get_basic_messages(): array {
		return array(
			'name_required'    => __( 'Campaign name is required', 'smart-cycle-discounts' ),
			'name_too_short'   => __( 'Campaign name must be at least 3 characters', 'smart-cycle-discounts' ),
			'name_too_long'    => __( 'Campaign name cannot exceed 255 characters', 'smart-cycle-discounts' ),
			'priority_range'   => __( 'Priority must be between 1 and 5', 'smart-cycle-discounts' ),
			'description_long' => __( 'Description cannot exceed 1000 characters', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Get discount validation messages.
	 *
	 * @since    1.0.0
	 * @return   array    Discount messages.
	 */
	private static function get_discount_messages(): array {
		return array(
			'type_required'            => __( 'Discount type is required', 'smart-cycle-discounts' ),
			'percentage_range'         => sprintf( __( 'Percentage must be between %1$d and %2$d', 'smart-cycle-discounts' ), SCD_Validation_Rules::PERCENTAGE_MIN, SCD_Validation_Rules::PERCENTAGE_MAX ),
			'fixed_range'              => sprintf( __( 'Fixed amount must be between %1$s and %2$s', 'smart-cycle-discounts' ), wc_price( SCD_Validation_Rules::FIXED_MIN ), wc_price( SCD_Validation_Rules::FIXED_MAX ) ),
			'value_required'           => __( 'Discount value is required', 'smart-cycle-discounts' ),
			'bogo_buy_required'        => __( 'Buy quantity is required for BOGO discount', 'smart-cycle-discounts' ),
			'bogo_get_required'        => __( 'Get quantity is required for BOGO discount', 'smart-cycle-discounts' ),
			'bundle_size_required'     => __( 'Bundle size is required', 'smart-cycle-discounts' ),
			'bundle_discount_required' => __( 'Bundle discount is required', 'smart-cycle-discounts' ),
			'tiers_required'           => __( 'At least one tier is required for tiered discount', 'smart-cycle-discounts' ),
			'tier_threshold_order'     => __( 'Tier thresholds must be in ascending order', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Get product validation messages.
	 *
	 * @since    1.0.0
	 * @return   array    Product messages.
	 */
	private static function get_product_messages(): array {
		return array(
			'selection_required'    => __( 'Product selection type is required', 'smart-cycle-discounts' ),
			'products_required'     => __( 'At least one product must be selected', 'smart-cycle-discounts' ),
			'categories_required'   => __( 'At least one category must be selected', 'smart-cycle-discounts' ),
			'random_count_required' => __( 'Random product count is required', 'smart-cycle-discounts' ),
			'random_count_range'    => sprintf( __( 'Random count must be between %1$d and %2$d', 'smart-cycle-discounts' ), SCD_Validation_Rules::RANDOM_COUNT_MIN, SCD_Validation_Rules::RANDOM_COUNT_MAX ),
			'too_many_products'     => sprintf( __( 'Maximum %d products allowed', 'smart-cycle-discounts' ), SCD_Validation_Rules::PRODUCT_IDS_MAX ),
		);
	}

	/**
	 * Get schedule validation messages.
	 *
	 * @since    1.0.0
	 * @return   array    Schedule messages.
	 */
	private static function get_schedule_messages(): array {
		return array(
			'start_date_required'     => __( 'Start date is required', 'smart-cycle-discounts' ),
			'invalid_date_range'      => __( 'End date must be after start date', 'smart-cycle-discounts' ),
			'invalid_start_date'      => __( 'Invalid start date format', 'smart-cycle-discounts' ),
			'invalid_end_date'        => __( 'Invalid end date format', 'smart-cycle-discounts' ),
			'invalid_timezone'        => __( 'Invalid timezone', 'smart-cycle-discounts' ),
			'duration_too_long'       => sprintf( __( 'Schedule duration cannot exceed %d days', 'smart-cycle-discounts' ), SCD_Validation_Rules::SCHEDULE_MAX_DURATION_DAYS ),
			'rotation_interval_range' => sprintf( __( 'Rotation interval must be between %1$d and %2$d hours', 'smart-cycle-discounts' ), SCD_Validation_Rules::ROTATION_INTERVAL_MIN, SCD_Validation_Rules::ROTATION_INTERVAL_MAX ),
		);
	}

	/**
	 * Sanitize campaign data - WordPress Settings API callback
	 *
	 * @since    1.0.0
	 * @param    array $data    Data to sanitize
	 * @return   array          Sanitized data
	 */
	public static function sanitize_campaign_data( array $data ) {
		$steps     = array( 'basic', 'products', 'discounts', 'schedule' );
		$sanitized = array();

		foreach ( $steps as $step ) {
			if ( isset( $data[ $step ] ) && is_array( $data[ $step ] ) ) {
				$sanitized[ $step ] = self::sanitize_step_data( $data[ $step ], $step );
			}
		}

		return $sanitized;
	}
}
