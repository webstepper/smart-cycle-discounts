<?php
/**
 * Wc Cart Message Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-cart-message-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Cart Message Service class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */
class SCD_WC_Cart_Message_Service {

	/**
	 * Discount query service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Discount_Query_Service    $discount_query
	 */
	private SCD_WC_Discount_Query_Service $discount_query;

	/**
	 * Campaign manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Logger.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger
	 */
	private ?object $logger;

	/**
	 * Initialize cart message service.
	 *
	 * @since    1.0.0
	 * @param    SCD_WC_Discount_Query_Service $discount_query      Discount query service.
	 * @param    SCD_Campaign_Manager          $campaign_manager    Campaign manager.
	 * @param    object|null                   $logger              Logger.
	 */
	public function __construct(
		SCD_WC_Discount_Query_Service $discount_query,
		SCD_Campaign_Manager $campaign_manager,
		?object $logger = null
	) {
		$this->discount_query   = $discount_query;
		$this->campaign_manager = $campaign_manager;
		$this->logger           = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_before_cart', array( $this, 'display_cart_discount_messages' ), 10 );
	}

	/**
	 * Display cart discount messages.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_cart_discount_messages(): void {
		if ( ! WC()->cart ) {
			return;
		}

		try {
			$messages = $this->get_cart_messages();

			if ( ! empty( $messages ) ) {
				foreach ( $messages as $message ) {
					wc_print_notice( $message, 'notice' );
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to display cart messages',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get cart messages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Messages.
	 */
	private function get_cart_messages(): array {
		$messages = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['data']->get_id();

			if ( $this->discount_query->has_active_discount( $product_id ) ) {
				$discount_info = $this->discount_query->get_discount_info(
					$product_id,
					array(
						'quantity' => $cart_item['quantity'],
					)
				);

				if ( $discount_info && isset( $discount_info['metadata']['upsell_message'] ) ) {
					$messages[] = $discount_info['metadata']['upsell_message'];
				}
			}
		}

		return array_unique( $messages );
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Cart_Message] ' . $message, $context );
		}
	}
}
