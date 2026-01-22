<?php
/**
 * Calculator Preset Decoder
 *
 * Decodes preset codes from the external Profit Calculator tool
 * into wizard-compatible data structures.
 *
 * Code format: SCD:{type}|{data}
 * - SCD:p|20         = Percentage: 20% off
 * - SCD:f|5.00       = Fixed: $5.00 off
 * - SCD:t|5:10,10:15 = Tiered: 5+ units=10%, 10+ units=15%
 * - SCD:b|2:1:100    = BOGO: Buy 2, Get 1 at 100% off
 * - SCD:s|50:5,100:10 = Spend Threshold: $50+=5%, $100+=10%
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculator Preset Decoder Class
 *
 * @since      1.0.0
 */
class WSSCD_Calculator_Preset_Decoder {

	/**
	 * Prefix for valid preset codes.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const CODE_PREFIX = 'SCD:';

	/**
	 * Discount type mapping from code to plugin format.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private static $type_map = array(
		'p' => 'percentage',
		'f' => 'fixed',
		't' => 'tiered',
		'b' => 'bogo',
		's' => 'spend_threshold',
	);

	/**
	 * Discount type labels for campaign names.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private static $type_labels = array(
		'percentage'      => 'Percentage Discount',
		'fixed'           => 'Fixed Amount Discount',
		'tiered'          => 'Tiered Quantity Discount',
		'bogo'            => 'BOGO Deal',
		'spend_threshold' => 'Spend Threshold Discount',
	);

	/**
	 * Decode a preset code into wizard-compatible data.
	 *
	 * @since    1.0.0
	 * @param    string $code    The preset code to decode.
	 * @return   array|WP_Error  Decoded data or WP_Error on failure.
	 */
	public static function decode( string $code ) {
		$code = trim( $code );

		// Validate prefix.
		if ( 0 !== strpos( $code, self::CODE_PREFIX ) ) {
			return new WP_Error(
				'invalid_code_format',
				__( 'Invalid code format. Code must start with "SCD:"', 'smart-cycle-discounts' )
			);
		}

		// Remove prefix and parse.
		$payload = substr( $code, strlen( self::CODE_PREFIX ) );

		// Split type and data.
		$parts = explode( '|', $payload, 2 );
		if ( 2 !== count( $parts ) ) {
			return new WP_Error(
				'invalid_code_structure',
				__( 'Invalid code structure. Expected format: SCD:type|data', 'smart-cycle-discounts' )
			);
		}

		list( $type_code, $data ) = $parts;

		// Validate type.
		if ( ! isset( self::$type_map[ $type_code ] ) ) {
			return new WP_Error(
				'invalid_discount_type',
				__( 'Invalid discount type in code.', 'smart-cycle-discounts' )
			);
		}

		$discount_type = self::$type_map[ $type_code ];

		// Decode based on type.
		switch ( $type_code ) {
			case 'p':
				return self::decode_percentage( $data, $discount_type );

			case 'f':
				return self::decode_fixed( $data, $discount_type );

			case 't':
				return self::decode_tiered( $data, $discount_type );

			case 'b':
				return self::decode_bogo( $data, $discount_type );

			case 's':
				return self::decode_spend_threshold( $data, $discount_type );

			default:
				return new WP_Error(
					'unsupported_type',
					__( 'Unsupported discount type.', 'smart-cycle-discounts' )
				);
		}
	}

	/**
	 * Validate a preset code without fully decoding.
	 *
	 * @since    1.0.0
	 * @param    string $code    The preset code to validate.
	 * @return   bool            True if valid, false otherwise.
	 */
	public static function is_valid( string $code ): bool {
		$result = self::decode( $code );
		return ! is_wp_error( $result );
	}

	/**
	 * Decode percentage discount.
	 * Format: SCD:p|20 (20% off)
	 *
	 * @since    1.0.0
	 * @param    string $data           The data portion of the code.
	 * @param    string $discount_type  The discount type.
	 * @return   array|WP_Error         Decoded data or error.
	 */
	private static function decode_percentage( string $data, string $discount_type ) {
		$percentage = (float) $data;

		if ( $percentage <= 0 || $percentage > 100 ) {
			return new WP_Error(
				'invalid_percentage',
				__( 'Percentage must be between 1 and 100.', 'smart-cycle-discounts' )
			);
		}

		return array(
			'name'      => self::generate_name( $discount_type, $percentage . '% Off' ),
			'discounts' => array(
				'discount_type'             => $discount_type,
				'discount_value_percentage' => $percentage,
			),
		);
	}

	/**
	 * Decode fixed amount discount.
	 * Format: SCD:f|5.00 ($5 off)
	 *
	 * @since    1.0.0
	 * @param    string $data           The data portion of the code.
	 * @param    string $discount_type  The discount type.
	 * @return   array|WP_Error         Decoded data or error.
	 */
	private static function decode_fixed( string $data, string $discount_type ) {
		$amount = (float) $data;

		if ( $amount <= 0 ) {
			return new WP_Error(
				'invalid_amount',
				__( 'Fixed amount must be greater than 0.', 'smart-cycle-discounts' )
			);
		}

		return array(
			'name'      => self::generate_name( $discount_type, '$' . number_format( $amount, 2 ) . ' Off' ),
			'discounts' => array(
				'discount_type'        => $discount_type,
				'discount_value_fixed' => $amount,
			),
		);
	}

	/**
	 * Decode tiered discount.
	 * Format: SCD:t|5:10,10:15,25:20 (5+ units=10%, 10+ units=15%, 25+ units=20%)
	 *
	 * @since    1.0.0
	 * @param    string $data           The data portion of the code.
	 * @param    string $discount_type  The discount type.
	 * @return   array|WP_Error         Decoded data or error.
	 */
	private static function decode_tiered( string $data, string $discount_type ) {
		$tier_strings = explode( ',', $data );
		$tiers        = array();

		foreach ( $tier_strings as $tier_string ) {
			$parts = explode( ':', trim( $tier_string ) );

			if ( 2 !== count( $parts ) ) {
				return new WP_Error(
					'invalid_tier_format',
					__( 'Invalid tier format. Expected qty:discount pairs.', 'smart-cycle-discounts' )
				);
			}

			$quantity = (int) $parts[0];
			$discount = (float) $parts[1];

			if ( $quantity <= 0 ) {
				return new WP_Error(
					'invalid_tier_quantity',
					__( 'Tier quantity must be greater than 0.', 'smart-cycle-discounts' )
				);
			}

			if ( $discount <= 0 || $discount > 100 ) {
				return new WP_Error(
					'invalid_tier_discount',
					__( 'Tier discount must be between 1 and 100.', 'smart-cycle-discounts' )
				);
			}

			$tiers[] = array(
				'min_quantity'     => $quantity,
				'discount_percent' => $discount,
			);
		}

		if ( empty( $tiers ) ) {
			return new WP_Error(
				'no_tiers',
				__( 'At least one tier is required.', 'smart-cycle-discounts' )
			);
		}

		// Sort tiers by quantity.
		usort(
			$tiers,
			function ( $a, $b ) {
				return $a['min_quantity'] - $b['min_quantity'];
			}
		);

		// Generate descriptive name.
		$tier_count = count( $tiers );
		$min_disc   = $tiers[0]['discount_percent'];
		$max_disc   = $tiers[ $tier_count - 1 ]['discount_percent'];
		$name_desc  = $min_disc . '%-' . $max_disc . '% (' . $tier_count . ' tiers)';

		return array(
			'name'      => self::generate_name( $discount_type, $name_desc ),
			'discounts' => array(
				'discount_type' => $discount_type,
				'tiers'         => $tiers,
			),
		);
	}

	/**
	 * Decode BOGO discount.
	 * Format: SCD:b|2:1:100 (Buy 2, Get 1 at 100% off)
	 *
	 * @since    1.0.0
	 * @param    string $data           The data portion of the code.
	 * @param    string $discount_type  The discount type.
	 * @return   array|WP_Error         Decoded data or error.
	 */
	private static function decode_bogo( string $data, string $discount_type ) {
		$parts = explode( ':', $data );

		if ( 3 !== count( $parts ) ) {
			return new WP_Error(
				'invalid_bogo_format',
				__( 'Invalid BOGO format. Expected buy:get:discount.', 'smart-cycle-discounts' )
			);
		}

		$buy_qty     = (int) $parts[0];
		$get_qty     = (int) $parts[1];
		$discount    = (float) $parts[2];

		if ( $buy_qty <= 0 || $get_qty <= 0 ) {
			return new WP_Error(
				'invalid_bogo_quantities',
				__( 'BOGO quantities must be greater than 0.', 'smart-cycle-discounts' )
			);
		}

		if ( $discount <= 0 || $discount > 100 ) {
			return new WP_Error(
				'invalid_bogo_discount',
				__( 'BOGO discount must be between 1 and 100.', 'smart-cycle-discounts' )
			);
		}

		// Generate descriptive name.
		$discount_label = 100 === (int) $discount ? 'Free' : $discount . '% Off';
		$name_desc      = 'Buy ' . $buy_qty . ' Get ' . $get_qty . ' ' . $discount_label;

		return array(
			'name'      => self::generate_name( $discount_type, $name_desc ),
			'discounts' => array(
				'discount_type' => $discount_type,
				'bogo_config'   => array(
					'buy_quantity'     => $buy_qty,
					'get_quantity'     => $get_qty,
					'discount_percent' => $discount,
				),
			),
		);
	}

	/**
	 * Decode spend threshold discount.
	 * Format: SCD:s|50:5,100:10,200:15 ($50+=5%, $100+=10%, $200+=15%)
	 *
	 * @since    1.0.0
	 * @param    string $data           The data portion of the code.
	 * @param    string $discount_type  The discount type.
	 * @return   array|WP_Error         Decoded data or error.
	 */
	private static function decode_spend_threshold( string $data, string $discount_type ) {
		$threshold_strings = explode( ',', $data );
		$thresholds        = array();

		foreach ( $threshold_strings as $threshold_string ) {
			$parts = explode( ':', trim( $threshold_string ) );

			if ( 2 !== count( $parts ) ) {
				return new WP_Error(
					'invalid_threshold_format',
					__( 'Invalid threshold format. Expected amount:discount pairs.', 'smart-cycle-discounts' )
				);
			}

			$amount   = (float) $parts[0];
			$discount = (float) $parts[1];

			if ( $amount <= 0 ) {
				return new WP_Error(
					'invalid_threshold_amount',
					__( 'Threshold amount must be greater than 0.', 'smart-cycle-discounts' )
				);
			}

			if ( $discount <= 0 || $discount > 100 ) {
				return new WP_Error(
					'invalid_threshold_discount',
					__( 'Threshold discount must be between 1 and 100.', 'smart-cycle-discounts' )
				);
			}

			$thresholds[] = array(
				'min_spend'        => $amount,
				'discount_percent' => $discount,
			);
		}

		if ( empty( $thresholds ) ) {
			return new WP_Error(
				'no_thresholds',
				__( 'At least one threshold is required.', 'smart-cycle-discounts' )
			);
		}

		// Sort thresholds by amount.
		usort(
			$thresholds,
			function ( $a, $b ) {
				return $a['min_spend'] - $b['min_spend'];
			}
		);

		// Generate descriptive name.
		$tier_count = count( $thresholds );
		$min_disc   = $thresholds[0]['discount_percent'];
		$max_disc   = $thresholds[ $tier_count - 1 ]['discount_percent'];
		$name_desc  = $min_disc . '%-' . $max_disc . '% (' . $tier_count . ' thresholds)';

		return array(
			'name'      => self::generate_name( $discount_type, $name_desc ),
			'discounts' => array(
				'discount_type'    => $discount_type,
				'spend_thresholds' => $thresholds,
			),
		);
	}

	/**
	 * Generate a campaign name from discount type and description.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type  The discount type.
	 * @param    string $description    Additional description.
	 * @return   string                 Generated campaign name.
	 */
	private static function generate_name( string $discount_type, string $description ): string {
		$type_label = self::$type_labels[ $discount_type ] ?? 'Discount';
		return $type_label . ' - ' . $description;
	}
}
