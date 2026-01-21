<?php
/**
 * Wc Price Integration Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-price-integration.php
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
 * WooCommerce Price Integration class.
 *
 * Responsible for:
 * - Product page price modifications
 * - Shop page price modifications
 * - Cart item price modifications
 * - Price HTML formatting
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */
class WSSCD_WC_Price_Integration {

	/**
	 * Discount query service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_WC_Discount_Query_Service    $discount_query    Discount query service.
	 */
	private WSSCD_WC_Discount_Query_Service $discount_query;

	/**
	 * Customer usage manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Customer_Usage_Manager|null    $usage_manager    Usage manager.
	 */
	private ?WSSCD_Customer_Usage_Manager $usage_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger.
	 */
	private ?object $logger;

	/**
	 * Recursion prevention flag.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $processing    Whether currently processing a price modification.
	 */
	private bool $processing = false;

	/**
	 * Initialize price integration.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_WC_Discount_Query_Service   $discount_query    Discount query service.
	 * @param    WSSCD_Customer_Usage_Manager|null $usage_manager     Usage manager.
	 * @param    object|null                     $logger            Logger.
	 */
	public function __construct(
		WSSCD_WC_Discount_Query_Service $discount_query,
		?WSSCD_Customer_Usage_Manager $usage_manager = null,
		?object $logger = null
	) {
		$this->discount_query = $discount_query;
		$this->usage_manager  = $usage_manager;
		$this->logger         = $logger;
	}

	/**
	 * Register price modification hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_hooks(): void {
		// Product price hooks
		add_filter( 'woocommerce_product_get_price', array( $this, 'modify_product_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'modify_sale_price' ), 10, 2 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'modify_price_html' ), 10, 2 );

		// Variation price hooks
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'modify_product_price' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'modify_sale_price' ), 10, 2 );

		// Cart price hooks
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'modify_cart_item_prices' ), 10, 1 );
	}

	/**
	 * Modify product price based on active discounts.
	 *
	 * @since    1.0.0
	 * @param    mixed      $price      Original price (can be various types from filters).
	 * @param    mixed      $product    Product object (may be other types from some filters).
	 * @return   string                  Modified price.
	 */
	public function modify_product_price( $price, $product ): string {
		// Prevent infinite recursion.
		if ( $this->processing ) {
			return strval( $price );
		}

		// Ensure we have a valid WC_Product instance.
		if ( ! $product instanceof WC_Product ) {
			return strval( $price );
		}

		$product_id = $product->get_id();

		// Skip in admin
		if ( is_admin() && ! wp_doing_ajax() ) {
			return strval( $price );
		}

		// Never modify during cart calculations
		$in_cart_calc = did_action( 'woocommerce_before_calculate_totals' ) && ! did_action( 'woocommerce_after_calculate_totals' );
		if ( $in_cart_calc ) {
			return strval( $price );
		}

		// Skip on cart/checkout pages
		if ( is_cart() || is_checkout() ) {
			return strval( $price );
		}

		if ( ! $price ) {
			return strval( $price );
		}

		// Set processing flag to prevent recursion
		$this->processing = true;

		try {
			$regular_price = floatval( $product->get_regular_price() );

			if ( $regular_price > 0 && $this->discount_query->has_active_discount( $product_id ) ) {
				$discount_info = $this->discount_query->get_discount_info( $product_id, array( 'quantity' => 1 ) );

				if ( $discount_info ) {
					$this->processing = false;
					return strval( $discount_info['discounted_price'] );
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to modify product price',
				array(
					'product_id' => $product_id,
					'error'      => $e->getMessage(),
				)
			);
		}

		$this->processing = false;
		return strval( $price );
	}

	/**
	 * Modify sale price based on active discounts.
	 *
	 * @since    1.0.0
	 * @param    mixed $sale_price    Original sale price (can be various types from filters).
	 * @param    mixed $product       Product object (may be other types from some filters).
	 * @return   string                Modified sale price.
	 */
	public function modify_sale_price( $sale_price, $product ): string {
		// Prevent infinite recursion.
		if ( $this->processing ) {
			return strval( $sale_price );
		}

		// Ensure we have a valid WC_Product instance.
		if ( ! $product instanceof WC_Product ) {
			return strval( $sale_price );
		}

		// Never modify during cart calculations.
		if ( did_action( 'woocommerce_before_calculate_totals' ) && ! did_action( 'woocommerce_after_calculate_totals' ) ) {
			return strval( $sale_price );
		}

		// Skip on cart/checkout pages.
		if ( is_cart() || is_checkout() ) {
			return strval( $sale_price );
		}

		// Set processing flag to prevent recursion.
		$this->processing = true;

		try {
			$product_id    = $product->get_id();
			$regular_price = floatval( $product->get_regular_price() );

			if ( $regular_price > 0 && $this->discount_query->has_active_discount( $product_id ) ) {
				$discount_info = $this->discount_query->get_discount_info( $product_id, array( 'quantity' => 1 ) );

				if ( $discount_info ) {
					$this->processing = false;
					return strval( $discount_info['discounted_price'] );
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to modify sale price',
				array(
					'product_id' => $product->get_id(),
					'error'      => $e->getMessage(),
				)
			);
		}

		$this->processing = false;
		return strval( $sale_price );
	}

	/**
	 * Modify price HTML display.
	 *
	 * @since    1.0.0
	 * @param    mixed $html      Original price HTML (can be null from some filters).
	 * @param    mixed $product   Product object (may be other types from some filters).
	 * @return   string            Modified price HTML.
	 */
	public function modify_price_html( $html, $product ): string {
		// Handle null or non-string HTML from some themes/plugins.
		if ( null === $html ) {
			$html = '';
		}

		// Prevent infinite recursion.
		if ( $this->processing ) {
			return (string) $html;
		}

		// Ensure we have a valid WC_Product instance.
		if ( ! $product instanceof WC_Product ) {
			return (string) $html;
		}

		// Set processing flag to prevent recursion.
		$this->processing = true;

		try {
			$product_id = $product->get_id();

			if ( $this->discount_query->has_active_discount( $product_id ) ) {
				$discount_info = $this->discount_query->get_discount_info( $product_id );

				if ( $discount_info ) {
					$regular_price    = floatval( $product->get_regular_price() );
					$discounted_price = floatval( $discount_info['discounted_price'] );

					if ( $regular_price > $discounted_price ) {
						$html = wc_format_sale_price( $regular_price, $discounted_price );
					}
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to modify price HTML',
				array(
					'product_id' => $product->get_id(),
					'error'      => $e->getMessage(),
				)
			);
		}

		$this->processing = false;
		return (string) $html;
	}

	/**
	 * Modify cart item prices before calculation.
	 *
	 * @since    1.0.0
	 * @param    mixed $cart    Cart object (may be other types from some hooks).
	 * @return   void
	 */
	public function modify_cart_item_prices( $cart ): void {
		// Ensure we have a valid WC_Cart instance.
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		if ( ! WC()->cart || ! is_object( WC()->cart ) ) {
			return;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Prevent recursion
		static $calculating = false;
		if ( $calculating ) {
			return;
		}

		$calculating = true;

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			try {
				$product    = $cart_item['data'];
				$product_id = $product->get_id();
				$quantity   = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

				$original_price = (float) get_post_meta( $product_id, '_regular_price', true );

				if ( 0 >= $original_price ) {
					$original_price = (float) $product->get_regular_price();
				}

				if ( 0 >= $original_price ) {
					continue;
				}

				$context = array(
					'quantity'      => $quantity,
					'cart_item'     => $cart_item,
					'cart_item_key' => $cart_item_key,
					'cart_total'    => floatval( $cart->get_subtotal() ),
				);

				$discount_info = $this->discount_query->get_discount_info( $product_id, $context );

				// Apply discount if eligible
				if ( $discount_info && $this->should_apply_discount( $product, $discount_info ) ) {
					$discounted_price = (float) $discount_info['discounted_price'];

					if ( $original_price > $discounted_price && 0 < $discounted_price ) {
						$product->set_price( $discounted_price );

						WC()->cart->cart_contents[ $cart_item_key ]['wsscd_discount'] = array(
							'original_price'   => $original_price,
							'discounted_price' => $discounted_price,
							'discount_amount'  => $original_price - $discounted_price,
							'campaign_id'      => $discount_info['campaign_id'] ?? 0,
						);
					}
				} else {
					$product->set_price( $original_price );

					if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wsscd_discount'] ) ) {
						unset( WC()->cart->cart_contents[ $cart_item_key ]['wsscd_discount'] );
					}
				}
			} catch ( Exception $e ) {
				$this->log(
					'error',
					'Failed to modify cart item price',
					array(
						'cart_item_key' => $cart_item_key,
						'product_id'    => isset( $product_id ) ? $product_id : 0,
						'error'         => $e->getMessage(),
					)
				);
			}
		}

		$calculating = false;
	}

	/**
	 * Check if discount should be applied.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product        Product object.
	 * @param    array      $discount_info  Discount information.
	 * @return   bool                          True if should apply.
	 */
	private function should_apply_discount( WC_Product $product, array $discount_info ): bool {
		$exclude = get_post_meta( $product->get_id(), '_wsscd_exclude_from_discounts', true );
		if ( 'yes' === $exclude ) {
			return false;
		}

		if ( $this->usage_manager && isset( $discount_info['campaign_id'] ) ) {
			$campaign_id = $discount_info['campaign_id'];

			$campaign_data = array();
			if ( isset( $discount_info['campaign_data'] ) ) {
				$campaign_data = $discount_info['campaign_data'];
			}

			$validation_result = $this->usage_manager->validate_customer_usage( $campaign_id, $campaign_data );

			// If validation failed, do not apply discount
			if ( ! isset( $validation_result['valid'] ) || ! $validation_result['valid'] ) {
				return false;
			}
		}

		return true;
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
			$this->logger->$level( '[WC_Price] ' . $message, $context );
		}
	}
}
