<?php
/**
 * Validation Rules Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/class-validation-rules.php
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
 * Validation Rules Class
 *
 * Defines all validation constants used throughout the plugin.
 * Separates business rules from validation logic for better maintainability.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Validation_Rules {

	/**
	 * Campaign constants.
	 *
	 * @since    1.0.0
	 */
	const CAMPAIGN_NAME_MIN        = 3;
	const CAMPAIGN_NAME_MAX        = 255;
	const CAMPAIGN_DESCRIPTION_MAX = 1000;
	const CAMPAIGN_PRIORITY_MIN    = 1;
	const CAMPAIGN_PRIORITY_MAX    = 5;
	const CAMPAIGN_NAME_PATTERN    = '/^[a-zA-Z0-9\s\-_&]+$/';

	/**
	 * Discount constants.
	 *
	 * @since    1.0.0
	 */
	const DISCOUNT_TYPES        = array( 'percentage', 'fixed', 'bogo', 'tiered', 'bundle', 'spend_threshold' );
	const PERCENTAGE_MIN        = 1;
	const PERCENTAGE_MAX        = 100;
	const PERCENTAGE_WARNING    = 50;
	const FIXED_MIN             = 1;
	const FIXED_MAX             = 1000000;
	const FIXED_WARNING         = 1000;
	const BOGO_BUY_MIN          = 1;
	const BOGO_BUY_MAX          = 100;
	const BOGO_GET_MIN          = 1;
	const BOGO_GET_MAX          = 100;
	const BOGO_DISCOUNT_DEFAULT = 100;
	const BUNDLE_SIZE_MIN       = 2;
	const BUNDLE_SIZE_MAX       = 20;
	const BUNDLE_DISCOUNT_MIN   = 0.01;
	const BUNDLE_DISCOUNT_MAX   = 100;

	/**
	 * Discount rules constants.
	 *
	 * @since    1.0.0
	 */
	const USAGE_LIMIT_MIN              = 0;
	const USAGE_LIMIT_MAX              = 1000000;
	const MINIMUM_QUANTITY_MIN         = 1;
	const MINIMUM_QUANTITY_MAX         = 10000;
	const MINIMUM_ORDER_AMOUNT_MIN     = 0;
	const MINIMUM_ORDER_AMOUNT_MAX     = 1000000;
	const MAX_DISCOUNT_AMOUNT_MIN      = 0;
	const MAX_DISCOUNT_AMOUNT_MAX      = 1000000;
	const APPLY_TO_OPTIONS             = array( 'per_item', 'cart_total' );

	/**
	 * Product selection constants.
	 *
	 * @since    1.0.0
	 */
	const SELECTION_TYPES                = array( 'all_products', 'random_products', 'specific_products', 'smart_selection' );
	const RANDOM_COUNT_MIN               = 1;
	const RANDOM_COUNT_MAX               = 100;
	const RANDOM_COUNT_WARNING_THRESHOLD = 50;
	const PRODUCT_IDS_MIN                = 1;
	const PRODUCT_IDS_MAX                = 10000;

	/**
	 * Schedule constants.
	 *
	 * @since    1.0.0
	 */
	const ROTATION_INTERVAL_MIN      = 1;
	const ROTATION_INTERVAL_MAX      = 168;
	const ROTATION_INTERVAL_DEFAULT  = 24;
	const SCHEDULE_TYPES             = array( 'daily', 'weekly', 'monthly', 'custom' );
	const WEEKDAYS                   = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
	const TIMEZONE_DEFAULT           = 'UTC';
	const RECURRENCE_MIN             = 1;
	const RECURRENCE_MAX             = 365;
	const RECURRENCE_COUNT_MIN       = 1;
	const RECURRENCE_COUNT_MAX       = 1000;
	const SCHEDULE_MAX_DURATION_DAYS = 365;

	/**
	 * System constants.
	 *
	 * @since    1.0.0
	 */
	const MAX_REQUEST_SIZE        = 102400; // 100KB
	const NONCE_LIFETIME          = 14400;  // 4 hours
	const SEARCH_PER_PAGE_MIN     = 1;
	const SEARCH_PER_PAGE_MAX     = 100;
	const SEARCH_PER_PAGE_DEFAULT = 50;
	const SEARCH_CATEGORIES_MAX   = 50;
}
