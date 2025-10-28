<?php
/**
 * Factory helper class for creating test objects
 *
 * @package SmartCycleDiscounts\Tests\Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Factory helper.
 *
 * @since 1.0.0
 */
class SCD_Factory_Helper {

	/**
	 * Create a validator instance.
	 *
	 * @param string $type Validator type.
	 * @param array  $dependencies Optional dependencies.
	 * @return object Validator instance.
	 */
	public static function create_validator( string $type, array $dependencies = array() ) {
		// Return a mock validator that delegates to unified validation system
		return new class( $type ) {
			private $type;

			public function __construct( $type ) {
				$this->type = $type;
			}

			public function validate( $value, array $context = array() ) {
				// Map old validator types to new validation contexts
				$context_map = array(
					'campaign' => 'campaign_complete',
					'discount' => 'wizard_discounts',
					'step'     => 'wizard_basic',
				);

				$validation_context = $context_map[ $this->type ] ?? $this->type;
				// SCD_Validation_Rules::validate only accepts 2 parameters (data, context)
				return SCD_Validation::validate( $value, $validation_context );
			}
		};
	}

	/**
	 * Create a discount engine instance.
	 *
	 * @param array $strategies Optional strategies.
	 * @return SCD_Discount_Engine
	 */
	public static function create_discount_engine( array $strategies = array() ) {
		$engine = new SCD_Discount_Engine();

		// Add default strategies if none provided
		if ( empty( $strategies ) ) {
			$strategies = array(
				'percentage' => new SCD_Percentage_Discount_Strategy(),
				'fixed'      => new SCD_Fixed_Discount_Strategy(),
				'bogo'       => new SCD_BOGO_Discount_Strategy(),
				'tiered'     => new SCD_Tiered_Discount_Strategy(),
				'bundle'     => new SCD_Bundle_Discount_Strategy(),
			);
		}

		foreach ( $strategies as $type => $strategy ) {
			$engine->register_strategy( $type, $strategy );
		}

		return $engine;
	}

	/**
	 * Create a mock logger.
	 *
	 * @return object Mock logger.
	 */
	public static function create_mock_logger() {
		return new class() {
			public $logs = array();

			public function log( $level, $message, $context = array() ) {
				$this->logs[] = array(
					'level'     => $level,
					'message'   => $message,
					'context'   => $context,
					'timestamp' => time(),
				);
			}

			public function debug( $message, $context = array() ) {
				$this->log( 'debug', $message, $context );
			}

			public function info( $message, $context = array() ) {
				$this->log( 'info', $message, $context );
			}

			public function warning( $message, $context = array() ) {
				$this->log( 'warning', $message, $context );
			}

			public function error( $message, $context = array() ) {
				$this->log( 'error', $message, $context );
			}

			public function get_logs( $level = null ) {
				if ( $level ) {
					return array_filter(
						$this->logs,
						function ( $log ) use ( $level ) {
							return $log['level'] === $level;
						}
					);
				}
				return $this->logs;
			}

			public function clear_logs() {
				$this->logs = array();
			}
		};
	}

	/**
	 * Create a campaign repository instance.
	 *
	 * @param object $db Optional database connection.
	 * @return SCD_Campaign_Repository
	 */
	public static function create_campaign_repository( $db = null ) {
		return new SCD_Campaign_Repository( $db ?: $GLOBALS['wpdb'] );
	}

	/**
	 * Create a mock WooCommerce product.
	 *
	 * @param array $properties Product properties.
	 * @return object Mock product.
	 */
	public static function create_mock_product( array $properties = array() ) {
		$defaults = array(
			'id'            => 1,
			'name'          => 'Test Product',
			'price'         => '10.00',
			'regular_price' => '10.00',
			'sale_price'    => '',
			'sku'           => 'TEST-001',
			'stock_status'  => 'instock',
			'categories'    => array(),
			'tags'          => array(),
		);

		$properties = array_merge( $defaults, $properties );

		return new class( $properties ) {
			private $properties;

			public function __construct( $properties ) {
				$this->properties = $properties;
			}

			public function get_id() {
				return $this->properties['id'];
			}

			public function get_name() {
				return $this->properties['name'];
			}

			public function get_price() {
				return $this->properties['price'];
			}

			public function get_regular_price() {
				return $this->properties['regular_price'];
			}

			public function get_sale_price() {
				return $this->properties['sale_price'];
			}

			public function get_sku() {
				return $this->properties['sku'];
			}

			public function get_stock_status() {
				return $this->properties['stock_status'];
			}

			public function get_category_ids() {
				return $this->properties['categories'];
			}

			public function get_tag_ids() {
				return $this->properties['tags'];
			}

			public function set_price( $price ) {
				$this->properties['price'] = $price;
			}

			public function is_on_sale() {
				return ! empty( $this->properties['sale_price'] );
			}

			public function is_in_stock() {
				return $this->properties['stock_status'] === 'instock';
			}
		};
	}

	/**
	 * Create a mock WooCommerce cart.
	 *
	 * @param array $items Cart items.
	 * @return object Mock cart.
	 */
	public static function create_mock_cart( array $items = array() ) {
		return new class( $items ) {
			private $items;
			private $total = 0;

			public function __construct( $items ) {
				$this->items = $items;
				$this->calculate_totals();
			}

			public function get_cart() {
				return $this->items;
			}

			public function get_total() {
				return $this->total;
			}

			public function calculate_totals() {
				$this->total = 0;
				foreach ( $this->items as $item ) {
					$price        = isset( $item['data'] ) ? $item['data']->get_price() : 0;
					$quantity     = $item['quantity'] ?? 1;
					$this->total += $price * $quantity;
				}
			}

			public function add_to_cart( $product_id, $quantity = 1 ) {
				$key                 = md5( $product_id );
				$this->items[ $key ] = array(
					'product_id' => $product_id,
					'quantity'   => $quantity,
					'data'       => SCD_Factory_Helper::create_mock_product( array( 'id' => $product_id ) ),
				);
				$this->calculate_totals();
				return $key;
			}

			public function empty_cart() {
				$this->items = array();
				$this->total = 0;
			}
		};
	}
}
