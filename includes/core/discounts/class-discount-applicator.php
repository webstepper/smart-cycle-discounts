<?php
/**
 * Discount Applicator
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Discount Applicator
 *
 * Apply discounts to WooCommerce products and cart items.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Discount_Applicator {

	/**
	 * Discount engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Discount_Engine    $discount_engine    Discount engine.
	 */
	private SCD_Discount_Engine $discount_engine;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Applied discounts cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $applied_discounts    Cache of applied discounts.
	 */
	private array $applied_discounts = array();

	/**
	 * Discount rules cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $discount_rules    Cache of discount rules.
	 */
	private array $discount_rules = array();

	/**
	 * Initialize the discount applicator.
	 *
	 * @since    1.0.0
	 * @param    SCD_Discount_Engine $discount_engine    Discount engine.
	 * @param    SCD_Logger          $logger             Logger instance.
	 */
	public function __construct( SCD_Discount_Engine $discount_engine, SCD_Logger $logger ) {
		$this->discount_engine = $discount_engine;
		$this->logger          = $logger;
	}

	/**
	 * Apply discount to a product.
	 *
	 * @since    1.0.0
	 * @param    int   $product_id        Product ID.
	 * @param    array $discount_config   Discount configuration.
	 * @param    array $context          Additional context.
	 * @return   array                      Application result.
	 */
	public function apply_to_product( int $product_id, array $discount_config, array $context = array() ): array {
		$result = array(
			'success'          => false,
			'product_id'       => $product_id,
			'original_price'   => 0.0,
			'discounted_price' => 0.0,
			'discount_amount'  => 0.0,
			'discount_applied' => false,
			'errors'           => array(),
		);

		try {
			// Get product
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$result['errors'][] = __( 'Product not found', 'smart-cycle-discounts' );
				return $result;
			}

			// Check if product is eligible for discounts
			if ( ! $this->is_product_eligible( $product, $context ) ) {
				$result['errors'][] = __( 'Product is not eligible for discounts', 'smart-cycle-discounts' );
				return $result;
			}

			// Get original price
			$original_price = floatval( $product->get_regular_price() );
			if ( 0 >= $original_price ) {
				$result['errors'][] = __( 'Product has no valid price', 'smart-cycle-discounts' );
				return $result;
			}

			$result['original_price'] = $original_price;

			// Calculate discount
			$calculation_context = array_merge(
				$context,
				array(
					'product_id'   => $product_id,
					'product'      => $product,
					'product_type' => $product->get_type(),
				)
			);

			$calculation = $this->discount_engine->calculate_discount(
				$original_price,
				$discount_config,
				$calculation_context
			);

			if ( $calculation['valid'] && 0 < $calculation['discount_amount'] ) {
				$result['discounted_price'] = $calculation['discounted_price'];
				$result['discount_amount']  = $calculation['discount_amount'];
				$result['discount_applied'] = true;
				$result['success']          = true;

				// Cache the applied discount
				$this->cache_applied_discount( $product_id, $discount_config, $calculation );

				$this->logger->info(
					'Discount applied to product',
					array(
						'product_id'       => $product_id,
						'original_price'   => $original_price,
						'discounted_price' => $calculation['discounted_price'],
						'discount_amount'  => $calculation['discount_amount'],
					)
				);

			} else {
				$result['errors']           = array_merge( $result['errors'], $calculation['errors'] ?? array() );
				$result['discounted_price'] = $original_price;
			}
		} catch ( Exception $e ) {
			$result['errors'][] = $e->getMessage();
			$this->logger->error(
				'Failed to apply discount to product',
				array(
					'product_id' => $product_id,
					'error'      => $e->getMessage(),
				)
			);
		}

		return $result;
	}

	/**
	 * Modify cart item prices.
	 *
	 * @since    1.0.0
	 * @param    WC_Cart $cart               Cart object.
	 * @param    array   $discount_rules     Discount rules to apply.
	 * @return   array                          Modification results.
	 */
	public function modify_cart_prices( WC_Cart $cart, array $discount_rules ): array {
		$results = array(
			'items_processed'       => 0,
			'items_discounted'      => 0,
			'total_discount_amount' => 0.0,
			'errors'                => array(),
		);

		if ( empty( $discount_rules ) ) {
			return $results;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			try {
				$product    = $cart_item['data'];
				$product_id = $product->get_id();
				$quantity   = intval( $cart_item['quantity'] );

				++$results['items_processed'];

				// Find applicable discount rule
				$applicable_rule = $this->find_applicable_rule( $product_id, $discount_rules, $cart_item );

				if ( ! $applicable_rule ) {
					continue;
				}

				// Apply discount to cart item
				$item_result = $this->apply_to_cart_item( $cart_item_key, $cart_item, $applicable_rule );

				if ( $item_result['success'] ) {
					++$results['items_discounted'];
					$results['total_discount_amount'] += $item_result['discount_amount'] * $quantity;
				} else {
					$results['errors'] = array_merge( $results['errors'], $item_result['errors'] );
				}
			} catch ( Exception $e ) {
				$results['errors'][] = sprintf(
					__( 'Error processing cart item %1$s: %2$s', 'smart-cycle-discounts' ),
					$cart_item_key,
					$e->getMessage()
				);
			}
		}

		$this->logger->debug( 'Cart prices modified', $results );
		return $results;
	}

	/**
	 * Apply discount to a cart item.
	 *
	 * @since    1.0.0
	 * @param    string $cart_item_key    Cart item key.
	 * @param    array  $cart_item        Cart item data.
	 * @param    array  $discount_rule    Discount rule.
	 * @return   array                       Application result.
	 */
	public function apply_to_cart_item( string $cart_item_key, array $cart_item, array $discount_rule ): array {
		$result = array(
			'success'          => false,
			'cart_item_key'    => $cart_item_key,
			'original_price'   => 0.0,
			'discounted_price' => 0.0,
			'discount_amount'  => 0.0,
			'errors'           => array(),
		);

		try {
			$product        = $cart_item['data'];
			$quantity       = intval( $cart_item['quantity'] );
			$original_price = floatval( $product->get_regular_price() );

			if ( 0 >= $original_price ) {
				$result['errors'][] = __( 'Invalid product price', 'smart-cycle-discounts' );
				return $result;
			}

			$result['original_price'] = $original_price;

			// Calculate discount with cart context
			$context = array(
				'cart_item_key' => $cart_item_key,
				'cart_item'     => $cart_item,
				'quantity'      => $quantity,
				'product_id'    => $product->get_id(),
				'product'       => $product,
			);

			$calculation = $this->discount_engine->calculate_discount(
				$original_price,
				$discount_rule,
				$context
			);

			if ( $calculation['valid'] && 0 < $calculation['discount_amount'] ) {
				// Set the new price on the product
				$product->set_price( $calculation['discounted_price'] );

				$result['discounted_price'] = $calculation['discounted_price'];
				$result['discount_amount']  = $calculation['discount_amount'];
				$result['success']          = true;

				// Store discount info in cart item
				$cart_item['scd_discount_applied'] = true;
				$cart_item['scd_discount_amount']  = $calculation['discount_amount'];
				$cart_item['scd_original_price']   = $original_price;

			} else {
				$result['errors']           = array_merge( $result['errors'], $calculation['errors'] ?? array() );
				$result['discounted_price'] = $original_price;
			}
		} catch ( Exception $e ) {
			$result['errors'][] = $e->getMessage();
			$this->logger->error(
				'Failed to apply discount to cart item',
				array(
					'cart_item_key' => $cart_item_key,
					'error'         => $e->getMessage(),
				)
			);
		}

		return $result;
	}

	/**
	 * Update display price for a product.
	 *
	 * @since    1.0.0
	 * @param    WC_Product $product           Product object.
	 * @param    array      $discount_config   Discount configuration.
	 * @return   bool                             True if price was updated.
	 */
	public function update_display_price( WC_Product $product, array $discount_config ): bool {
		// Capability check: Only users with edit_products capability can update display prices
		if ( ! current_user_can( 'edit_products' ) && ! did_action( 'woocommerce_before_calculate_totals' ) ) {
			$this->logger->warning(
				'Unauthorized attempt to update display price',
				array(
					'product_id' => $product->get_id(),
					'user_id'    => get_current_user_id(),
				)
			);
			return false;
		}

		try {
			$product_id     = $product->get_id();
			$original_price = floatval( $product->get_regular_price() );

			if ( 0 >= $original_price ) {
				return false;
			}

			// Calculate discounted price
			$calculation = $this->discount_engine->calculate_discount(
				$original_price,
				$discount_config,
				array(
					'product_id' => $product_id,
					'product'    => $product,
				)
			);

			if ( $calculation['valid'] && 0 < $calculation['discount_amount'] ) {
				// Update product prices
				$product->set_price( $calculation['discounted_price'] );
				$product->set_sale_price( $calculation['discounted_price'] );

				$this->logger->debug(
					'Display price updated',
					array(
						'product_id'       => $product_id,
						'original_price'   => $original_price,
						'discounted_price' => $calculation['discounted_price'],
					)
				);

				return true;
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to update display price',
				array(
					'product_id' => $product->get_id(),
					'error'      => $e->getMessage(),
				)
			);
		}

		return false;
	}

	/**
	 * Check if product is eligible for discounts.
	 *
	 * @since    1.0.0
	 * @param    WC_Product $product    Product object.
	 * @param    array      $context    Additional context.
	 * @return   bool                      True if eligible.
	 */
	public function is_product_eligible( WC_Product $product, array $context = array() ): bool {
		// Check if product is excluded from discounts
		$excluded = get_post_meta( $product->get_id(), '_scd_exclude_from_discounts', true );
		if ( 'yes' === $excluded ) {
			return false;
		}

		// Check product type eligibility
		$allowed_types = apply_filters(
			'scd_allowed_product_types',
			array(
				'simple',
				'variable',
				'variation',
				'grouped',
			)
		);

		if ( ! in_array( $product->get_type(), $allowed_types ) ) {
			return false;
		}

		// Check if product is purchasable
		if ( ! $product->is_purchasable() ) {
			return false;
		}

		// Check stock status
		if ( ! $product->is_in_stock() ) {
			return false;
		}

		// Allow filtering
		return apply_filters( 'scd_is_product_eligible_for_discount', true, $product, $context );
	}

	/**
	 * Find applicable discount rule for a product.
	 *
	 * @since    1.0.0
	 * @param    int   $product_id       Product ID.
	 * @param    array $discount_rules   Available discount rules.
	 * @param    array $context         Additional context.
	 * @return   array|null                Applicable rule or null.
	 */
	public function find_applicable_rule( int $product_id, array $discount_rules, array $context = array() ): ?array {
		foreach ( $discount_rules as $rule ) {
			if ( $this->is_rule_applicable( $product_id, $rule, $context ) ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Check if a discount rule is applicable to a product.
	 *
	 * @since    1.0.0
	 * @param    int   $product_id    Product ID.
	 * @param    array $rule          Discount rule.
	 * @param    array $context       Additional context.
	 * @return   bool                    True if applicable.
	 */
	public function is_rule_applicable( int $product_id, array $rule, array $context = array() ): bool {
		// Check product inclusion/exclusion
		if ( ! empty( $rule['include_products'] ) ) {
			if ( ! in_array( $product_id, $rule['include_products'] ) ) {
				return false;
			}
		}

		if ( ! empty( $rule['exclude_products'] ) ) {
			if ( in_array( $product_id, $rule['exclude_products'] ) ) {
				return false;
			}
		}

		// Check category inclusion/exclusion
		if ( ! empty( $rule['include_categories'] ) || ! empty( $rule['exclude_categories'] ) ) {
			$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

			if ( ! empty( $rule['include_categories'] ) ) {
				if ( empty( array_intersect( $product_categories, $rule['include_categories'] ) ) ) {
					return false;
				}
			}

			if ( ! empty( $rule['exclude_categories'] ) ) {
				if ( ! empty( array_intersect( $product_categories, $rule['exclude_categories'] ) ) ) {
					return false;
				}
			}
		}

		// Check minimum/maximum price
		if ( isset( $rule['min_price'] ) || isset( $rule['max_price'] ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$price = floatval( $product->get_price() );

				if ( isset( $rule['min_price'] ) && floatval( $rule['min_price'] ) > $price ) {
					return false;
				}

				if ( isset( $rule['max_price'] ) && floatval( $rule['max_price'] ) < $price ) {
					return false;
				}
			}
		}

		// Check quantity requirements (for cart context)
		if ( isset( $rule['min_quantity'] ) && isset( $context['quantity'] ) ) {
			if ( intval( $rule['min_quantity'] ) > intval( $context['quantity'] ) ) {
				return false;
			}
		}

		// Allow custom rule validation
		return apply_filters( 'scd_is_discount_rule_applicable', true, $product_id, $rule, $context );
	}

	/**
	 * Get applied discount for a product.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array|null            Applied discount or null.
	 */
	public function get_applied_discount( int $product_id ): ?array {
		return $this->applied_discounts[ $product_id ] ?? null;
	}

	/**
	 * Check if product has applied discount.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool                  True if has discount.
	 */
	public function has_applied_discount( int $product_id ): bool {
		return isset( $this->applied_discounts[ $product_id ] );
	}

	/**
	 * Remove applied discount from a product.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool                  True if removed.
	 */
	public function remove_applied_discount( int $product_id ): bool {
		if ( isset( $this->applied_discounts[ $product_id ] ) ) {
			unset( $this->applied_discounts[ $product_id ] );

			$this->logger->debug( 'Applied discount removed', array( 'product_id' => $product_id ) );
			return true;
		}

		return false;
	}

	/**
	 * Cache applied discount.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $product_id        Product ID.
	 * @param    array $discount_config   Discount configuration.
	 * @param    array $calculation       Calculation result.
	 * @return   void
	 */
	private function cache_applied_discount( int $product_id, array $discount_config, array $calculation ): void {
		$this->applied_discounts[ $product_id ] = array(
			'product_id'      => $product_id,
			'discount_config' => $discount_config,
			'calculation'     => $calculation,
			'applied_at'      => current_time( 'timestamp' ),
		);
	}

	/**
	 * Get discount application statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Application statistics.
	 */
	public function get_statistics(): array {
		$total_discounts = count( $this->applied_discounts );
		$total_savings   = 0.0;

		foreach ( $this->applied_discounts as $discount ) {
			$total_savings += $discount['calculation']['discount_amount'] ?? 0;
		}

		return array(
			'total_applied_discounts'      => $total_discounts,
			'total_savings_amount'         => $total_savings,
			'average_discount_per_product' => $total_discounts > 0 ? $total_savings / $total_discounts : 0,
		);
	}

	/**
	 * Clear all applied discounts.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_applied_discounts(): void {
		$this->applied_discounts = array();
		$this->discount_rules    = array();

		$this->logger->debug( 'All applied discounts cleared' );
	}

	/**
	 * Bulk apply discounts to multiple products.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids       Product IDs.
	 * @param    array $discount_config   Discount configuration.
	 * @param    array $context          Additional context.
	 * @return   array                      Bulk application results.
	 */
	public function bulk_apply_discounts( array $product_ids, array $discount_config, array $context = array() ): array {
		$results = array(
			'total_products'          => count( $product_ids ),
			'successful_applications' => 0,
			'failed_applications'     => 0,
			'total_discount_amount'   => 0.0,
			'results'                 => array(),
			'errors'                  => array(),
		);

		foreach ( $product_ids as $product_id ) {
			$result                            = $this->apply_to_product( $product_id, $discount_config, $context );
			$results['results'][ $product_id ] = $result;

			if ( $result['success'] ) {
				++$results['successful_applications'];
				$results['total_discount_amount'] += $result['discount_amount'];
			} else {
				++$results['failed_applications'];
				$results['errors'] = array_merge( $results['errors'], $result['errors'] );
			}
		}

		$this->logger->info(
			'Bulk discount application completed',
			array(
				'total_products' => $results['total_products'],
				'successful'     => $results['successful_applications'],
				'failed'         => $results['failed_applications'],
			)
		);

		return $results;
	}

	/**
	 * Validate discount application context.
	 *
	 * @since    1.0.0
	 * @param    array $context    Application context.
	 * @return   bool                 True if valid.
	 */
	public function validate_application_context( array $context ): bool {
		// Add context validation logic here
		return true;
	}
}
