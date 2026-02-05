<?php
/**
 * WooCommerce Coupon Restriction Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-coupon-restriction.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Coupon Restriction
 *
 * Blocks WooCommerce coupons when campaigns have allow_coupons=false.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_WC_Coupon_Restriction {

	/**
	 * Campaign manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private WSSCD_Campaign_Manager $campaign_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger.
	 */
	private ?object $logger;

	/**
	 * Initialize coupon restriction.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign_Manager $campaign_manager    Campaign manager.
	 * @param    object|null          $logger              Logger.
	 */
	public function __construct( WSSCD_Campaign_Manager $campaign_manager, ?object $logger = null ) {
		$this->campaign_manager = $campaign_manager;
		$this->logger           = $logger;
	}

	/**
	 * Register coupon restriction hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon_with_campaign' ), 10, 2 );
	}

	/**
	 * Validate coupon compatibility with active campaigns.
	 *
	 * @since    1.0.0
	 * @param    mixed $valid     Whether coupon is valid (may be other types from some filters).
	 * @param    mixed $coupon    Coupon object (may be other types from some filters).
	 * @return   bool              Whether coupon is valid.
	 * @throws   Exception         If coupon conflicts with campaign.
	 */
	public function validate_coupon_with_campaign( $valid, $coupon ): bool {
		// Ensure we have a valid WC_Coupon instance.
		if ( ! $coupon instanceof WC_Coupon ) {
			return (bool) $valid;
		}

		if ( ! $valid ) {
			return (bool) $valid;
		}

		try {
			// Get cart items to check for active campaigns
			if ( ! WC()->cart ) {
				return $valid;
			}

			$blocking_campaigns = array();

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = $cart_item['data']->get_id();

				// Get active campaigns for this product
				$campaigns = $this->campaign_manager->get_active_campaigns_for_product( $product_id );

				foreach ( $campaigns as $campaign ) {
					$discount_rules = $campaign->get_discount_rules();

					// Check if campaign blocks coupons
					$allow_coupons = isset( $discount_rules['allow_coupons'] ) ?
						(bool) $discount_rules['allow_coupons'] : true;

					if ( ! $allow_coupons ) {
						$blocking_campaigns[] = $campaign->get_name();
						break 2; // Exit both loops
					}
				}
			}

			if ( ! empty( $blocking_campaigns ) ) {
				$campaign_name = $blocking_campaigns[0];

				throw new Exception(
					sprintf(
						/* translators: %s: campaign name */
						__( 'This coupon cannot be used with the active "%s" discount.', 'smart-cycle-discounts' ),
						$campaign_name
					)
				);
			}

			return $valid;

		} catch ( Exception $e ) {
			// WooCommerce expects exceptions for invalid coupons
			throw $e;
		}
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Log level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Coupon_Restriction] ' . $message, $context );
		}
	}
}
