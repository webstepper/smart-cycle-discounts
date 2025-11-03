<?php
/**
 * Scd Product Selection Types Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants/class-scd-product-selection-types.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Product Selection Types Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Product_Selection_Types {

	/**
	 * Selection type constants
	 *
	 * @since 1.0.0
	 */
	const ALL_PRODUCTS      = 'all_products';
	const SPECIFIC_PRODUCTS = 'specific_products';
	const RANDOM_PRODUCTS   = 'random_products';
	const SMART_SELECTION   = 'smart_selection';

	/**
	 * Get all selection types
	 *
	 * @since  1.0.0
	 * @return array Array of selection types
	 */
	public static function get_types(): array {
		return array(
			self::ALL_PRODUCTS      => __( 'All Products', 'smart-cycle-discounts' ),
			self::SPECIFIC_PRODUCTS => __( 'Specific Products', 'smart-cycle-discounts' ),
			self::RANDOM_PRODUCTS   => __( 'Random Products', 'smart-cycle-discounts' ),
			self::SMART_SELECTION   => __( 'Smart Selection', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Check if a selection type is valid
	 *
	 * @since  1.0.0
	 * @param  string $type Selection type to validate.
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, array_keys( self::get_types() ), true );
	}

	/**
	 * Get default selection type
	 *
	 * @since  1.0.0
	 * @return string Default selection type
	 */
	public static function get_default(): string {
		return self::ALL_PRODUCTS;
	}
}
