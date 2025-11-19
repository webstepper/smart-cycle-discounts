<?php
/**
 * Discount Engine Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/class-discount-engine.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Discount Engine
 *
 * Main engine for calculating and applying discounts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Discount_Engine {

	/**
	 * Registered discount strategies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $strategies    Registered strategies.
	 */
	private array $strategies = array();

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger instance.
	 */
	private ?object $logger;

	/**
	 * Cache manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $cache    Cache manager.
	 */
	private ?object $cache;

	/**
	 * Strategies initialized flag.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $strategies_initialized    Whether strategies are initialized.
	 */
	private bool $strategies_initialized = false;

	/**
	 * Initialize the discount engine.
	 *
	 * @since    1.0.0
	 * @param    object|null $logger    Logger instance.
	 * @param    object|null $cache     Cache manager.
	 */
	public function __construct( ?object $logger = null, ?object $cache = null ) {
		$this->logger = $logger;
		$this->cache  = $cache;
	}

	/**
	 * Ensure strategies are initialized.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function ensure_strategies_initialized(): void {
		if ( ! $this->strategies_initialized ) {
			$this->register_default_strategies();
			$this->strategies_initialized = true;
		}
	}

	/**
	 * Register default discount strategies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_default_strategies(): void {
		$this->register_strategy( new SCD_Percentage_Strategy() );
		$this->register_strategy( new SCD_Fixed_Strategy() );
		$this->register_strategy( new SCD_Tiered_Strategy() );
		$this->register_strategy( new SCD_Bogo_Strategy() );
		$this->register_strategy( new SCD_Spend_Threshold_Strategy() );
	}

	/**
	 * Register a discount strategy.
	 *
	 * @since    1.0.0
	 * @param    SCD_Discount_Strategy_Interface $strategy    Strategy to register.
	 * @return   void
	 */
	public function register_strategy( SCD_Discount_Strategy_Interface $strategy ): void {
		$this->strategies[ $strategy->get_strategy_id() ] = $strategy;

		if ( $this->logger && method_exists( $this->logger, 'debug' ) ) {
			$this->logger->debug(
				'Discount strategy registered',
				array(
					'strategy_id'   => $strategy->get_strategy_id(),
					'strategy_name' => $strategy->get_strategy_name(),
				)
			);
		}
	}

	/**
	 * Get registered strategy.
	 *
	 * @since    1.0.0
	 * @param    string $strategy_id    Strategy identifier.
	 * @return   SCD_Discount_Strategy_Interface|null    Strategy or null if not found.
	 */
	public function get_strategy( string $strategy_id ): ?SCD_Discount_Strategy_Interface {
		$this->ensure_strategies_initialized();
		return $this->strategies[ $strategy_id ] ?? null;
	}

	/**
	 * Get all registered strategies.
	 *
	 * @since    1.0.0
	 * @return   array    Array of strategies.
	 */
	public function get_strategies(): array {
		$this->ensure_strategies_initialized();
		return $this->strategies;
	}

	/**
	 * Calculate discounted price for a product.
	 *
	 * @since    1.0.0
	 * @param    int   $product_id        Product ID.
	 * @param    float $original_price    Original product price.
	 * @return   float                       Discounted price.
	 */
	public function calculate_discounted_price( int $product_id, float $original_price ): float {
		// For now, return the original price since we don't have access to the repository
		// This should be handled by the integration layer that has access to the repository
		return $original_price;
	}

	/**
	 * Calculate discount for a product.
	 *
	 * @since    1.0.0
	 * @param    float $original_price    Original product price.
	 * @param    array $discount_config   Discount configuration.
	 * @param    array $context          Additional context.
	 * @return   SCD_Discount_Result        Discount calculation result.
	 */
	public function calculate_discount( float $original_price, array $discount_config, array $context = array() ): SCD_Discount_Result {
		$strategy_id = $discount_config['type'] ?? '';

		if ( empty( $strategy_id ) ) {
			return SCD_Discount_Result::no_discount( $original_price, 'unknown', 'No discount type specified' );
		}

		$strategy = $this->get_strategy( $strategy_id );
		if ( ! $strategy ) {
			return SCD_Discount_Result::no_discount( $original_price, $strategy_id, 'Strategy not found' );
		}

		if ( ! $strategy->supports_context( $context ) ) {
			return SCD_Discount_Result::no_discount( $original_price, $strategy_id, 'Strategy does not support context' );
		}

		try {
			$result = $strategy->calculate_discount( $original_price, $discount_config, $context );

			// Log discount calculation
			if ( $result->is_applied() ) {
				if ( $this->logger && method_exists( $this->logger, 'debug' ) ) {
					$this->logger->debug(
						'Discount calculated',
						array(
							'strategy_id'      => $strategy_id,
							'original_price'   => $original_price,
							'discounted_price' => $result->get_discounted_price(),
							'discount_amount'  => $result->get_discount_amount(),
							'context'          => $context,
						)
					);
				}
			}

			return $result;

		} catch ( Exception $e ) {
			if ( $this->logger && method_exists( $this->logger, 'error' ) ) {
				$this->logger->error(
					'Discount calculation failed',
					array(
						'strategy_id'    => $strategy_id,
						'original_price' => $original_price,
						'error'          => $e->getMessage(),
						'context'        => $context,
					)
				);
			}

			return SCD_Discount_Result::no_discount( $original_price, $strategy_id, 'Calculation error: ' . $e->getMessage() );
		}
	}

	/**
	 * Calculate discount for multiple products.
	 *
	 * @since    1.0.0
	 * @param    array $products          Array of products with prices.
	 * @param    array $discount_config   Discount configuration.
	 * @param    array $context          Additional context.
	 * @return   array                      Array of discount results.
	 */
	public function calculate_bulk_discount( array $products, array $discount_config, array $context = array() ): array {
		$results = array();

		foreach ( $products as $product_id => $product_data ) {
			$original_price  = (float) $product_data['price'];
			$product_context = array_merge(
				$context,
				array(
					'product_id'   => $product_id,
					'product_data' => $product_data,
				)
			);

			$result                 = $this->calculate_discount( $original_price, $discount_config, $product_context );
			$results[ $product_id ] = $result;
		}

		return $results;
	}

	/**
	 * Apply discount to WooCommerce product.
	 *
	 * @since    1.0.0
	 * @param    WC_Product $product           WooCommerce product.
	 * @param    array      $discount_config   Discount configuration.
	 * @param    array      $context          Additional context.
	 * @return   SCD_Discount_Result             Discount result.
	 */
	public function apply_to_wc_product( WC_Product $product, array $discount_config, array $context = array() ): SCD_Discount_Result {
		$original_price = (float) $product->get_price();

		$wc_context = array_merge(
			$context,
			array(
				'product'      => $product,
				'product_id'   => $product->get_id(),
				'product_type' => $product->get_type(),
				'categories'   => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) ),
				'tags'         => wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) ),
			)
		);

		return $this->calculate_discount( $original_price, $discount_config, $wc_context );
	}

	/**
	 * Get best discount from multiple configurations.
	 *
	 * @since    1.0.0
	 * @param    float $original_price      Original price.
	 * @param    array $discount_configs    Array of discount configurations.
	 * @param    array $context            Additional context.
	 * @return   SCD_Discount_Result          Best discount result.
	 */
	public function get_best_discount( float $original_price, array $discount_configs, array $context = array() ): SCD_Discount_Result {
		$best_result  = SCD_Discount_Result::no_discount( $original_price, 'none', 'No valid discounts' );
		$best_savings = 0;

		foreach ( $discount_configs as $config ) {
			$result = $this->calculate_discount( $original_price, $config, $context );

			if ( $result->is_applied() && $result->get_discount_amount() > $best_savings ) {
				$best_result  = $result;
				$best_savings = $result->get_discount_amount();
			}
		}

		return $best_result;
	}

	/**
	 * Validate discount configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   array                       Validation errors.
	 */
	public function validate_discount_config( array $discount_config ): array {
		$strategy_id = $discount_config['type'] ?? '';

		if ( empty( $strategy_id ) ) {
			return array( 'type' => 'Discount type is required.' );
		}

		$strategy = $this->get_strategy( $strategy_id );
		if ( ! $strategy ) {
			return array( 'type' => "Unknown discount strategy: {$strategy_id}" );
		}

		return $strategy->validate_config( $discount_config );
	}

	/**
	 * Get discount preview.
	 *
	 * @since    1.0.0
	 * @param    float $original_price    Original price.
	 * @param    array $discount_config   Discount configuration.
	 * @return   array                      Preview data.
	 */
	public function get_discount_preview( float $original_price, array $discount_config ): array {
		$strategy_id = $discount_config['type'] ?? '';
		$strategy    = $this->get_strategy( $strategy_id );

		if ( ! $strategy ) {
			return array(
				'error'            => "Unknown discount strategy: {$strategy_id}",
				'original_price'   => $original_price,
				'discounted_price' => $original_price,
				'discount_amount'  => 0,
				'applied'          => false,
			);
		}

		if ( method_exists( $strategy, 'preview_discount' ) ) {
			return $strategy->preview_discount( $original_price, $discount_config );
		}

		// Fallback to regular calculation
		$result = $this->calculate_discount( $original_price, $discount_config );

		return array(
			'strategy_name'       => $strategy->get_strategy_name(),
			'original_price'      => $original_price,
			'discounted_price'    => $result->get_discounted_price(),
			'discount_amount'     => $result->get_discount_amount(),
			'discount_percentage' => $result->get_discount_percentage(),
			'applied'             => $result->is_applied(),
			'formatted'           => array(
				'original_price'   => wc_price( $original_price ),
				'discounted_price' => wc_price( $result->get_discounted_price() ),
				'discount_amount'  => wc_price( $result->get_discount_amount() ),
			),
		);
	}

	/**
	 * Get strategy configuration schema.
	 *
	 * @since    1.0.0
	 * @param    string $strategy_id    Strategy identifier.
	 * @return   array                     Configuration schema.
	 */
	public function get_strategy_config_schema( string $strategy_id ): array {
		$strategy = $this->get_strategy( $strategy_id );

		if ( ! $strategy || ! method_exists( $strategy, 'get_config_schema' ) ) {
			return array();
		}

		return $strategy->get_config_schema();
	}

	/**
	 * Get strategy default configuration.
	 *
	 * @since    1.0.0
	 * @param    string $strategy_id    Strategy identifier.
	 * @return   array                     Default configuration.
	 */
	public function get_strategy_default_config( string $strategy_id ): array {
		$strategy = $this->get_strategy( $strategy_id );

		if ( ! $strategy || ! method_exists( $strategy, 'get_default_config' ) ) {
			return array();
		}

		return $strategy->get_default_config();
	}

	/**
	 * Get available strategies for admin interface.
	 *
	 * @since    1.0.0
	 * @return   array    Array of strategy information.
	 */
	public function get_strategies_for_admin(): array {
		$strategies = array();

		foreach ( $this->strategies as $strategy ) {
			if ( ! $strategy ) {
				continue;
			}

			$strategy_info = array(
				'id'             => method_exists( $strategy, 'get_strategy_id' ) ? $strategy->get_strategy_id() : '',
				'name'           => method_exists( $strategy, 'get_strategy_name' ) ? $strategy->get_strategy_name() : '',
				'description'    => method_exists( $strategy, 'get_strategy_description' ) ? $strategy->get_strategy_description() : '',
				'config_schema'  => method_exists( $strategy, 'get_config_schema' ) ? $strategy->get_config_schema() : array(),
				'default_config' => method_exists( $strategy, 'get_default_config' ) ? $strategy->get_default_config() : array(),
			);

			// Only add if we have at least an ID
			if ( ! empty( $strategy_info['id'] ) ) {
				$strategies[] = $strategy_info;
			}
		}

		return $strategies;
	}

	/**
	 * Calculate discount statistics.
	 *
	 * @since    1.0.0
	 * @param    array $results    Array of discount results.
	 * @return   array               Statistics.
	 */
	public function calculate_statistics( array $results ): array {
		$total_original   = 0;
		$total_discounted = 0;
		$total_savings    = 0;
		$applied_count    = 0;
		$strategy_usage   = array();

		foreach ( $results as $result ) {
			if ( ! ( $result instanceof SCD_Discount_Result ) ) {
				continue;
			}

			$total_original   += $result->get_original_price();
			$total_discounted += $result->get_discounted_price();
			$total_savings    += $result->get_discount_amount();

			if ( $result->is_applied() ) {
				++$applied_count;
				$strategy_id                    = $result->get_strategy_id();
				$strategy_usage[ $strategy_id ] = ( $strategy_usage[ $strategy_id ] ?? 0 ) + 1;
			}
		}

		$total_count                = count( $results );
		$application_rate           = 0 < $total_count ? ( $applied_count / $total_count ) * 100 : 0;
		$average_savings_percentage = 0 < $total_original ? ( $total_savings / $total_original ) * 100 : 0;

		return array(
			'total_products'             => $total_count,
			'discounts_applied'          => $applied_count,
			'application_rate'           => $application_rate,
			'total_original_value'       => $total_original,
			'total_discounted_value'     => $total_discounted,
			'total_savings'              => $total_savings,
			'average_savings_percentage' => $average_savings_percentage,
			'strategy_usage'             => $strategy_usage,
		);
	}

	/**
	 * Clear discount cache.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache(): void {
		$this->cache->delete( 'discount_*' );

		if ( $this->logger && method_exists( $this->logger, 'debug' ) ) {
			$this->logger->debug( 'Discount cache cleared' );
		}
	}

	/**
	 * Get engine statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Engine statistics.
	 */
	public function get_engine_stats(): array {
		return array(
			'registered_strategies' => count( $this->strategies ),
			'strategy_list'         => array_keys( $this->strategies ),
			'cache_enabled'         => $this->cache !== null,
		);
	}
}
