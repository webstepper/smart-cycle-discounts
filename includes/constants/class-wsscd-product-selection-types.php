<?php
/**
 * Scd Product Selection Types Class
 *
 * This class provides selection type constants for JavaScript localization.
 * The single source of truth for selection types is WSSCD_Campaign class.
 *
 * Product Selection Model (Products Step Flow):
 * 1. CATEGORY FILTER (first field) - Creates the product pool from selected categories
 * 2. SELECTION TYPE - Determines HOW to select products FROM the category pool
 * 3. ADVANCED FILTERS - Further refines the selection
 *
 * All 4 selection types select FROM the category pool.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants/class-wsscd-product-selection-types.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Ensure WSSCD_Campaign is loaded for constants.
require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign.php';

/**
 * Product Selection Types Class
 *
 * Delegates to WSSCD_Campaign for single source of truth.
 * This class exists for backward compatibility and JavaScript localization.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Product_Selection_Types {

	/**
	 * Selection type constants - delegating to WSSCD_Campaign.
	 *
	 * @since 1.0.0
	 */
	const ALL_PRODUCTS      = WSSCD_Campaign::SELECTION_TYPE_ALL_PRODUCTS;
	const SPECIFIC_PRODUCTS = WSSCD_Campaign::SELECTION_TYPE_SPECIFIC_PRODUCTS;
	const RANDOM_PRODUCTS   = WSSCD_Campaign::SELECTION_TYPE_RANDOM_PRODUCTS;
	const SMART_SELECTION   = WSSCD_Campaign::SELECTION_TYPE_SMART_SELECTION;

	/**
	 * Get all selection types with labels.
	 *
	 * @since  1.0.0
	 * @return array Array of selection types with labels.
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
	 * Check if a selection type is valid.
	 *
	 * @since  1.0.0
	 * @param  string $type Selection type to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, array_keys( self::get_types() ), true );
	}

	/**
	 * Get default selection type.
	 *
	 * @since  1.0.0
	 * @return string Default selection type.
	 */
	public static function get_default(): string {
		return self::ALL_PRODUCTS;
	}

	/**
	 * Check if selection type is pool-based.
	 *
	 * Pool-based types (all_products, random_products, smart_selection) select from
	 * the category pool without explicit product IDs.
	 * specific_products is NOT pool-based as it requires explicit product IDs.
	 *
	 * @since  1.0.0
	 * @param  string $type Selection type to check.
	 * @return bool True if pool-based, false otherwise.
	 */
	public static function is_pool_based( string $type ): bool {
		return WSSCD_Campaign::is_pool_based_selection( $type );
	}
}
