<?php
/**
 * Request Schema Definitions
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Request Schema Definitions class.
 *
 * Defines schemas for REST and AJAX request validation
 * with proper types, validation callbacks, and sanitization.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Request_Schemas {

	/**
	 * Get schema for campaign creation/update.
	 *
	 * @since    1.0.0
	 * @return   array    Campaign schema.
	 */
	public static function get_campaign_schema(): array {
		return array(
			'name'        => array(
				'type'              => 'string',
				'required'          => true,
				'description'       => __( 'Campaign name', 'smart-cycle-discounts' ),
				'validate_callback' => function ( $value ) {
					$result = SCD_Validation::validate( $value, 'campaign_name' );
					return is_wp_error( $result ) ? $result : true;
				},
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'required'          => false,
				'description'       => __( 'Campaign description', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'status'      => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'draft',
				'enum'              => array( 'draft', 'active', 'paused', 'scheduled' ),
				'description'       => __( 'Campaign status', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'priority'    => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 5,
				'minimum'           => 1,
				'maximum'           => 10,
				'description'       => __( 'Campaign priority (1-10)', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get schema for discount configuration.
	 *
	 * @since    1.0.0
	 * @return   array    Discount schema.
	 */
	public static function get_discount_schema(): array {
		return array(
			'discount_type'    => array(
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'percentage', 'fixed', 'bogo', 'tiered', 'bundle' ),
				'description'       => __( 'Type of discount', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'discount_value'   => array(
				'type'              => 'number',
				'required'          => false,
				'description'       => __( 'Discount value', 'smart-cycle-discounts' ),
				'validate_callback' => function ( $value, $request ) {
					$type = $request['discount_type'] ?? '';

					if ( $type === 'percentage' ) {
						$value = floatval( $value );
						if ( $value < SCD_Validation_Rules::DISCOUNT_PERCENTAGE_MIN || $value > SCD_Validation_Rules::DISCOUNT_PERCENTAGE_MAX ) {
							return new WP_Error(
								'invalid_percentage',
								sprintf(
									__( 'Percentage must be between %1$s and %2$s', 'smart-cycle-discounts' ),
									SCD_Validation_Rules::DISCOUNT_PERCENTAGE_MIN,
									SCD_Validation_Rules::DISCOUNT_PERCENTAGE_MAX
								)
							);
						}
					} elseif ( $type === 'fixed' ) {
						$value = floatval( $value );
						if ( $value < SCD_Validation_Rules::DISCOUNT_FIXED_MIN || $value > SCD_Validation_Rules::DISCOUNT_FIXED_MAX ) {
							return new WP_Error(
								'invalid_amount',
								sprintf(
									__( 'Fixed amount must be between %1$s and %2$s', 'smart-cycle-discounts' ),
									SCD_Validation_Rules::DISCOUNT_FIXED_MIN,
									SCD_Validation_Rules::DISCOUNT_FIXED_MAX
								)
							);
						}
					}

					return true;
				},
				'sanitize_callback' => function ( $value, $request ) {
					$type = $request['discount_type'] ?? '';

					if ( $type === 'percentage' && class_exists( 'SCD_Validation' ) ) {
						return SCD_Validation_Rules::sanitize_percentage( $value );
					}

					return wc_format_decimal( $value );
				},
			),
			'minimum_quantity' => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'minimum'           => 1,
				'description'       => __( 'Minimum quantity for discount', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'absint',
			),
			'maximum_discount' => array(
				'type'              => 'number',
				'required'          => false,
				'description'       => __( 'Maximum discount amount (for percentage discounts)', 'smart-cycle-discounts' ),
				'sanitize_callback' => function ( $value ) {
					return wc_format_decimal( $value );
				},
			),
		);
	}

	/**
	 * Get schema for product selection.
	 *
	 * @since    1.0.0
	 * @return   array    Product selection schema.
	 */
	public static function get_product_selection_schema(): array {
		return array(
			'selection_type'   => array(
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'all', 'specific', 'categories', 'tags', 'attributes' ),
				'description'       => __( 'Product selection method', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'product_ids'      => array(
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'integer',
				),
				'description'       => __( 'Selected product IDs', 'smart-cycle-discounts' ),
				'sanitize_callback' => function ( $value ) {
					return array_map( 'absint', (array) $value );
				},
			),
			'category_ids'     => array(
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'integer',
				),
				'description'       => __( 'Selected category IDs', 'smart-cycle-discounts' ),
				'sanitize_callback' => function ( $value ) {
					return array_map( 'absint', (array) $value );
				},
			),
			'exclude_products' => array(
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'integer',
				),
				'description'       => __( 'Excluded product IDs', 'smart-cycle-discounts' ),
				'sanitize_callback' => function ( $value ) {
					return array_map( 'absint', (array) $value );
				},
			),
		);
	}

	/**
	 * Get schema for schedule configuration.
	 *
	 * @since    1.0.0
	 * @return   array    Schedule schema.
	 */
	public static function get_schedule_schema(): array {
		return array(
			'start_date'        => array(
				'type'              => 'string',
				'format'            => 'date-time',
				'required'          => false,
				'description'       => __( 'Campaign start date', 'smart-cycle-discounts' ),
				'validate_callback' => function ( $value ) {
					if ( ! empty( $value ) && ! strtotime( $value ) ) {
						return new WP_Error( 'invalid_date', __( 'Invalid start date format', 'smart-cycle-discounts' ) );
					}
					return true;
				},
				'sanitize_callback' => function ( $value ) {
					return ! empty( $value ) ? date( 'Y-m-d H:i:s', strtotime( $value ) ) : '';
				},
			),
			'end_date'          => array(
				'type'              => 'string',
				'format'            => 'date-time',
				'required'          => false,
				'description'       => __( 'Campaign end date', 'smart-cycle-discounts' ),
				'validate_callback' => function ( $value, $request ) {
					if ( ! empty( $value ) ) {
						if ( ! strtotime( $value ) ) {
							return new WP_Error( 'invalid_date', __( 'Invalid end date format', 'smart-cycle-discounts' ) );
						}

						$start = $request['start_date'] ?? '';
						if ( ! empty( $start ) && strtotime( $value ) <= strtotime( $start ) ) {
							return new WP_Error( 'invalid_date_range', __( 'End date must be after start date', 'smart-cycle-discounts' ) );
						}
					}
					return true;
				},
				'sanitize_callback' => function ( $value ) {
					return ! empty( $value ) ? date( 'Y-m-d H:i:s', strtotime( $value ) ) : '';
				},
			),
			'timezone'          => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => wp_timezone_string(),
				'description'       => __( 'Campaign timezone', 'smart-cycle-discounts' ),
				'validate_callback' => function ( $value ) {
					try {
						new DateTimeZone( $value );
						return true;
					} catch ( Exception $e ) {
						return new WP_Error( 'invalid_timezone', __( 'Invalid timezone', 'smart-cycle-discounts' ) );
					}
				},
				'sanitize_callback' => 'sanitize_text_field',
			),
			'recurring'         => array(
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
				'description' => __( 'Enable recurring schedule', 'smart-cycle-discounts' ),
			),
			'recurring_pattern' => array(
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'daily', 'weekly', 'monthly' ),
				'description'       => __( 'Recurring pattern', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Get schema for analytics request.
	 *
	 * @since    1.0.0
	 * @return   array    Analytics schema.
	 */
	public static function get_analytics_schema(): array {
		return array(
			'campaign_id' => array(
				'type'              => 'integer',
				'required'          => false,
				'description'       => __( 'Campaign ID', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'absint',
			),
			'date_range'  => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => '7days',
				'enum'              => array( 'today', 'yesterday', '7days', '30days', '90days', 'custom' ),
				'description'       => __( 'Date range for analytics', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'start_date'  => array(
				'type'              => 'string',
				'format'            => 'date',
				'required'          => false,
				'description'       => __( 'Custom start date', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end_date'    => array(
				'type'              => 'string',
				'format'            => 'date',
				'required'          => false,
				'description'       => __( 'Custom end date', 'smart-cycle-discounts' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'metrics'     => array(
				'type'              => 'array',
				'required'          => false,
				'default'           => array( 'revenue', 'conversions', 'clicks' ),
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'revenue', 'conversions', 'clicks', 'impressions', 'profit' ),
				),
				'description'       => __( 'Metrics to retrieve', 'smart-cycle-discounts' ),
				'sanitize_callback' => function ( $value ) {
					return array_map( 'sanitize_key', (array) $value );
				},
			),
		);
	}

	/**
	 * Validate request against schema.
	 *
	 * @since    1.0.0
	 * @param    array           $data      Request data.
	 * @param    array           $schema    Schema definition.
	 * @param    WP_REST_Request $request   Optional REST request object.
	 * @return   true|WP_Error              True on success, error on failure.
	 */
	public static function validate_request( array $data, array $schema, ?WP_REST_Request $request = null ): bool|WP_Error {
		$errors = array();

		foreach ( $schema as $field => $rules ) {
			$value = $data[ $field ] ?? null;

			// Check required fields
			if ( ! empty( $rules['required'] ) && ( $value === null || $value === '' ) ) {
				$errors[ $field ] = sprintf( __( '%s is required', 'smart-cycle-discounts' ), $rules['description'] ?? $field );
				continue;
			}

			// Skip validation for empty optional fields
			if ( ! $rules['required'] && ( $value === null || $value === '' ) ) {
				continue;
			}

			// Type validation
			$type_valid = self::validate_type( $value, $rules['type'] ?? 'string' );
			if ( is_wp_error( $type_valid ) ) {
				$errors[ $field ] = $type_valid->get_error_message();
				continue;
			}

			// Enum validation
			if ( ! empty( $rules['enum'] ) && ! in_array( $value, $rules['enum'], true ) ) {
				$errors[ $field ] = sprintf( __( 'Invalid value. Must be one of: %s', 'smart-cycle-discounts' ), implode( ', ', $rules['enum'] ) );
				continue;
			}

			// Min/Max validation for numbers
			if ( $rules['type'] === 'integer' || $rules['type'] === 'number' ) {
				if ( isset( $rules['minimum'] ) && $value < $rules['minimum'] ) {
					$errors[ $field ] = sprintf( __( 'Must be at least %s', 'smart-cycle-discounts' ), $rules['minimum'] );
					continue;
				}
				if ( isset( $rules['maximum'] ) && $value > $rules['maximum'] ) {
					$errors[ $field ] = sprintf( __( 'Cannot exceed %s', 'smart-cycle-discounts' ), $rules['maximum'] );
					continue;
				}
			}

			// Custom validation callback
			if ( ! empty( $rules['validate_callback'] ) && is_callable( $rules['validate_callback'] ) ) {
				$validation_result = call_user_func( $rules['validate_callback'], $value, $request ?? $data );
				if ( is_wp_error( $validation_result ) ) {
					$errors[ $field ] = $validation_result->get_error_message();
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', __( 'Validation failed', 'smart-cycle-discounts' ), $errors );
		}

		return true;
	}

	/**
	 * Validate value type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $value    Value to validate.
	 * @param    string $type     Expected type.
	 * @return   true|WP_Error       True on success, error on failure.
	 */
	private static function validate_type( $value, string $type ): bool|WP_Error {
		switch ( $type ) {
			case 'string':
				if ( ! is_string( $value ) ) {
					return new WP_Error( 'invalid_type', __( 'Must be a string', 'smart-cycle-discounts' ) );
				}
				break;

			case 'integer':
				if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
					return new WP_Error( 'invalid_type', __( 'Must be an integer', 'smart-cycle-discounts' ) );
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return new WP_Error( 'invalid_type', __( 'Must be a number', 'smart-cycle-discounts' ) );
				}
				break;

			case 'boolean':
				if ( ! is_bool( $value ) && ! in_array( $value, array( '0', '1', 0, 1, 'true', 'false' ), true ) ) {
					return new WP_Error( 'invalid_type', __( 'Must be true or false', 'smart-cycle-discounts' ) );
				}
				break;

			case 'array':
				if ( ! is_array( $value ) ) {
					return new WP_Error( 'invalid_type', __( 'Must be an array', 'smart-cycle-discounts' ) );
				}
				break;
		}

		return true;
	}
}
