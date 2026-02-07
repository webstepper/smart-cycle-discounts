<?php
/**
 * Condition Engine Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products/class-condition-engine.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Condition Engine
 *
 * Handles advanced product filtering conditions for campaigns.
 * Supports numeric and text-based comparisons with validation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Condition_Engine {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager|null    $cache    Cache manager.
	 */
	private ?WSSCD_Cache_Manager $cache = null;

	/**
	 * Supported condition properties.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $supported_properties    Supported properties.
	 */
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Configuration array defining WooCommerce meta keys; not actual queries.
	private array $supported_properties = array(
		// Price & Inventory
		'price'            => array(
			'type'     => 'numeric',
			'label'    => 'Price',
			'meta_key' => '_price', // Current effective price (what get_price() returns)
		),
		'regular_price'    => array(
			'type'     => 'numeric',
			'label'    => 'Regular Price',
			'meta_key' => '_regular_price',
		),
		'sale_price'       => array(
			'type'     => 'numeric',
			'label'    => 'Sale Price',
			'meta_key' => '_sale_price',
		),
		'stock_quantity'   => array(
			'type'     => 'numeric',
			'label'    => 'Stock Quantity',
			'meta_key' => '_stock',
		),
		'stock_status'     => array(
			'type'     => 'select',
			'label'    => 'Stock Status',
			'meta_key' => '_stock_status',
			'options'  => array( 'instock', 'outofstock', 'onbackorder' ),
		),
		'low_stock_amount' => array(
			'type'     => 'numeric',
			'label'    => 'Low Stock Amount',
			'meta_key' => '_low_stock_amount',
		),

		// Product Attributes
		'weight'           => array(
			'type'     => 'numeric',
			'label'    => 'Weight',
			'meta_key' => '_weight',
		),
		'length'           => array(
			'type'     => 'numeric',
			'label'    => 'Length',
			'meta_key' => '_length',
		),
		'width'            => array(
			'type'     => 'numeric',
			'label'    => 'Width',
			'meta_key' => '_width',
		),
		'height'           => array(
			'type'     => 'numeric',
			'label'    => 'Height',
			'meta_key' => '_height',
		),
		'sku'              => array(
			'type'     => 'text',
			'label'    => 'SKU',
			'meta_key' => '_sku',
		),

		// Product Status
		'featured'         => array(
			'type'     => 'boolean',
			'label'    => 'Featured Product',
			'meta_key' => '_featured',
		),
		'on_sale'          => array(
			'type'     => 'boolean',
			'label'    => 'On Sale',
			'callback' => 'is_on_sale',
		),
		'virtual'          => array(
			'type'     => 'boolean',
			'label'    => 'Virtual Product',
			'meta_key' => '_virtual',
		),
		'downloadable'     => array(
			'type'     => 'boolean',
			'label'    => 'Downloadable',
			'meta_key' => '_downloadable',
		),
		'product_type'     => array(
			'type'     => 'select',
			'label'    => 'Product Type',
			'callback' => 'get_type',
			'options'  => array( 'simple', 'variable', 'grouped', 'external', 'subscription', 'variable-subscription' ),
		),

		// Shipping & Tax
		'tax_status'       => array(
			'type'     => 'select',
			'label'    => 'Tax Status',
			'meta_key' => '_tax_status',
			'options'  => array( 'taxable', 'shipping', 'none' ),
		),
		'tax_class'        => array(
			'type'     => 'text',
			'label'    => 'Tax Class',
			'meta_key' => '_tax_class',
		),
		'shipping_class'   => array(
			'type'     => 'text',
			'label'    => 'Shipping Class',
			'callback' => 'get_shipping_class_id',
		),

		// Reviews & Ratings
		'average_rating'   => array(
			'type'     => 'numeric',
			'label'    => 'Average Rating',
			'meta_key' => '_wc_average_rating',
		),
		'review_count'     => array(
			'type'     => 'numeric',
			'label'    => 'Review Count',
			'meta_key' => '_wc_review_count',
		),

		// Sales Data
		'total_sales'      => array(
			'type'     => 'numeric',
			'label'    => 'Total Sales',
			'meta_key' => 'total_sales',
		),
		'date_created'     => array(
			'type'  => 'date',
			'label' => 'Date Created',
			'field' => 'post_date',
		),
		'date_modified'    => array(
			'type'  => 'date',
			'label' => 'Date Modified',
			'field' => 'post_modified',
		),
	);
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

	/**
	 * Supported comparison operators.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $supported_operators    Supported operators.
	 */
	private array $supported_operators = array(
		'equals'             => array(
			'symbol'      => '=',
			'label'       => 'Equals',
			'types'       => array( 'numeric', 'text' ),
			'value_count' => 1,
		),
		'not_equals'         => array(
			'symbol'      => '!=',
			'label'       => 'Not equals',
			'types'       => array( 'numeric', 'text' ),
			'value_count' => 1,
		),
		'greater_than'       => array(
			'symbol'      => '>',
			'label'       => 'Greater than',
			'types'       => array( 'numeric' ),
			'value_count' => 1,
		),
		'greater_than_equal' => array(
			'symbol'      => '>=',
			'label'       => 'Greater than or equal',
			'types'       => array( 'numeric' ),
			'value_count' => 1,
		),
		'less_than'          => array(
			'symbol'      => '<',
			'label'       => 'Less than',
			'types'       => array( 'numeric' ),
			'value_count' => 1,
		),
		'less_than_equal'    => array(
			'symbol'      => '<=',
			'label'       => 'Less than or equal',
			'types'       => array( 'numeric' ),
			'value_count' => 1,
		),
		'between'            => array(
			'symbol'      => 'BETWEEN',
			'label'       => 'Between two values',
			'types'       => array( 'numeric' ),
			'value_count' => 2,
		),
		'not_between'        => array(
			'symbol'      => 'NOT BETWEEN',
			'label'       => 'Not between two values',
			'types'       => array( 'numeric' ),
			'value_count' => 2,
		),
		'contains'           => array(
			'symbol'      => 'LIKE',
			'label'       => 'Text contains substring',
			'types'       => array( 'text' ),
			'value_count' => 1,
		),
		'not_contains'       => array(
			'symbol'      => 'NOT LIKE',
			'label'       => 'Text doesn\'t contain substring',
			'types'       => array( 'text' ),
			'value_count' => 1,
		),
		'starts_with'        => array(
			'symbol'      => 'LIKE',
			'label'       => 'Text starts with',
			'types'       => array( 'text' ),
			'value_count' => 1,
		),
		'ends_with'          => array(
			'symbol'      => 'LIKE',
			'label'       => 'Text ends with',
			'types'       => array( 'text' ),
			'value_count' => 1,
		),
		'in'                 => array(
			'symbol'      => 'IN',
			'label'       => 'Is one of',
			'types'       => array( 'select' ),
			'value_count' => -1, // Multiple values
		),
		'not_in'             => array(
			'symbol'      => 'NOT IN',
			'label'       => 'Is not one of',
			'types'       => array( 'select' ),
			'value_count' => -1, // Multiple values
		),
	);

	/**
	 * Initialize the condition engine.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger        $logger    Logger instance.
	 * @param    WSSCD_Cache_Manager $cache     Cache manager.
	 */
	public function __construct( WSSCD_Logger $logger, ?WSSCD_Cache_Manager $cache = null ) {
		$this->logger = $logger;
		$this->cache  = $cache;
	}

	/**
	 * Apply conditions to product query.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to filter.
	 * @param    array $conditions     Conditions to apply.
	 * @return   array                    Filtered product IDs.
	 */
	public function apply_conditions( array $product_ids, array $conditions, $logic = 'all' ): array {
		if ( empty( $product_ids ) || empty( $conditions ) ) {
			return $product_ids;
		}

		// Generate cache key with full condition content to prevent collisions
		// Using md5 hash of serialized conditions ensures different conditions with same count don't collide
		$conditions_hash = md5( serialize( $conditions ) );
		$cache_key       = sprintf(
			'products_conditions_%s_%s',
			$logic,
			$conditions_hash
		);

		// Try to get from cache first
		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( false !== $cached_result && is_array( $cached_result ) ) {
				$this->logger->debug(
					'Condition filtering retrieved from cache',
					array(
						'product_count'   => count( $product_ids ),
						'condition_count' => count( $conditions ),
					)
				);
				return $cached_result;
			}
		}

		$filtered_ids = array();

		try {
			if ( $logic === 'any' ) {
				// OR logic - collect products that match ANY condition
				foreach ( $conditions as $condition ) {
					if ( ! $this->validate_condition( $condition ) ) {
						$this->logger->warning( 'Invalid condition skipped', array( 'condition' => $condition ) );
						continue;
					}

					$matching_ids = $this->apply_single_condition( $product_ids, $condition );
					$filtered_ids = array_unique( array_merge( $filtered_ids, $matching_ids ) );
				}
			} else {
				// AND logic - products must match ALL conditions
				$filtered_ids = $product_ids;
						foreach ( $conditions as $condition ) {
							if ( ! $this->validate_condition( $condition ) ) {
								$this->logger->warning( 'Invalid condition skipped', array( 'condition' => $condition ) );
						continue;
					}
		
					$filtered_ids = $this->apply_single_condition( $filtered_ids, $condition );

					// If no products remain, break early
					if ( empty( $filtered_ids ) ) {
						break;
					}
				}
			}

			if ( $this->cache ) {
					$this->cache->set( $cache_key, $filtered_ids, 900 ); // Cache for 15 minutes - cleared on changes
			}

	
			$this->logger->info(
				'Conditions applied successfully',
				array(
					'original_count'  => count( $product_ids ),
					'filtered_count'  => count( $filtered_ids ),
					'condition_count' => count( $conditions ),
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Condition application failed',
				array(
					'error'      => $e->getMessage(),
					'conditions' => $conditions,
				)
			);
			return $product_ids; // Return original IDs on error
		}

			return $filtered_ids;
	}

	/**
	 * Apply a single condition to product IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    array $condition      Condition to apply.
	 * @return   array                    Filtered product IDs.
	 */
	private function apply_single_condition( array $product_ids, array $condition ): array {
		// Use database format: condition_type, value, value2
		$condition_type = $condition['condition_type'] ?? '';
		$operator       = $this->normalize_operator( $condition['operator'] );
		$value          = isset( $condition['value'] ) ? strval( $condition['value'] ) : '';
		$value2         = isset( $condition['value2'] ) ? strval( $condition['value2'] ) : '';
		$mode           = $condition['mode'] ?? 'include';


		if ( ! isset( $this->supported_properties[ $condition_type ] ) ) {
			return $product_ids;
		}

		if ( ! isset( $this->supported_operators[ $operator ] ) ) {
			return $product_ids;
		}

		$property_config = $this->supported_properties[ $condition_type ];
		$operator_config = $this->supported_operators[ $operator ];


		$match_count = 0;
		$filtered    = array_filter(
			$product_ids,
			function ( $product_id ) use ( $property_config, $operator_config, $operator, $value, $value2, $mode, &$match_count, $condition_type ) {
				$product_value = $this->get_product_property_value( $product_id, $property_config );
				$result        = $this->evaluate_condition( $product_value, $operator_config, $operator, $value, $value2, $property_config['type'] );

				// Debug first 3 products
				if ( $match_count < 3 ) {
					}

				if ( $result ) {
					$match_count++;
				}

				// If mode is exclude, invert the result
				return $mode === 'exclude' ? ! $result : $result;
			}
		);


		return $filtered;
	}

	/**
	 * Get product property value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $product_id        Product ID.
	 * @param    array $property_config   Property configuration.
	 * @return   mixed                       Property value.
	 */
	private function get_product_property_value( int $product_id, array $property_config ) {
		// Handle callback-based properties
		if ( isset( $property_config['callback'] ) ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return $this->get_default_value( $property_config['type'] );
			}

			$callback = $property_config['callback'];
			if ( method_exists( $product, $callback ) ) {
				$value = $product->$callback();
				return $this->normalize_value( $value, $property_config['type'] );
			}
		}

		// Handle meta key properties
		if ( isset( $property_config['meta_key'] ) ) {
			$value = get_post_meta( $product_id, $property_config['meta_key'], true );

			// Handle special cases
			if ( $property_config['meta_key'] === 'total_sales' ) {
				return intval( $value ?: 0 );
			}

			// Handle featured product (stored as 'yes'/'no')
			if ( $property_config['meta_key'] === '_featured' ) {
				return $value === 'yes' ? 1 : 0;
			}

			// Handle virtual and downloadable (stored as 'yes'/'no')
			if ( in_array( $property_config['meta_key'], array( '_virtual', '_downloadable' ) ) ) {
				return $value === 'yes' ? 1 : 0;
			}

			return $this->normalize_value( $value, $property_config['type'] );
		}

		// Handle post field properties
		if ( isset( $property_config['field'] ) ) {
			$post = get_post( $product_id );
			if ( ! $post ) {
				return $this->get_default_value( $property_config['type'] );
			}

			$value = $post->{$property_config['field']};

			// Handle date fields
			if ( $property_config['type'] === 'date' ) {
				return strtotime( $value ) ?: 0;
			}

			return $this->normalize_value( $value, $property_config['type'] );
		}

		return $this->get_default_value( $property_config['type'] );
	}

	/**
	 * Get default value for property type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Property type.
	 * @return   mixed              Default value.
	 */
	private function get_default_value( string $type ) {
		switch ( $type ) {
			case 'numeric':
			case 'boolean':
			case 'date':
				return 0;
			case 'select':
			case 'text':
			default:
				return '';
		}
	}

	/**
	 * Normalize value based on type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $value    Raw value.
	 * @param    string $type     Property type.
	 * @return   mixed               Normalized value.
	 */
	private function normalize_value( $value, string $type ) {
		switch ( $type ) {
			case 'numeric':
				return floatval( $value ?: 0 );
			case 'boolean':
				return $value ? 1 : 0;
			case 'date':
				return is_numeric( $value ) ? intval( $value ) : ( strtotime( $value ) ?: 0 );
			case 'select':
			case 'text':
			default:
				return strval( $value ?: '' );
		}
	}

	/**
	 * Evaluate condition against product value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $product_value     Product value.
	 * @param    array  $operator_config   Operator configuration.
	 * @param    array  $condition_values  Condition values.
	 * @param    string $type              Value type.
	 * @return   bool                        True if condition matches.
	 */
	private function evaluate_condition( $product_value, array $operator_config, string $operator_name, string $value, string $value2, string $type ): bool {
		$operator = $operator_config['symbol'];

		switch ( $type ) {
			case 'numeric':
				return $this->evaluate_numeric_condition( $product_value, $operator, $value, $value2 );
			case 'boolean':
				return $this->evaluate_boolean_condition( $product_value, $operator, $value, $value2 );
			case 'date':
				return $this->evaluate_date_condition( $product_value, $operator, $value, $value2 );
			case 'select':
				return $this->evaluate_select_condition( $product_value, $operator, $value, $value2 );
			case 'text':
			default:
				return $this->evaluate_text_condition( $product_value, $operator, $operator_name, $value, $value2 );
		}
	}

	/**
	 * Evaluate numeric condition.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float  $product_value     Product value.
	 * @param    string $operator          Comparison operator.
	 * @param    array  $condition_values  Condition values.
	 * @return   bool                        True if condition matches.
	 */
	private function evaluate_numeric_condition( float $product_value, string $operator, string $value, string $value2 ): bool {
		$value1 = floatval( $value );
		$value2 = floatval( $value2 );

		switch ( $operator ) {
			case '=':
				return abs( $product_value - $value1 ) < 0.01; // Float comparison with tolerance
			case '!=':
				return abs( $product_value - $value1 ) >= 0.01;
			case '>':
				return $product_value > $value1;
			case '>=':
				return $product_value >= $value1;
			case '<':
				return $product_value < $value1;
			case '<=':
				return $product_value <= $value1;
			case 'BETWEEN':
				return $product_value >= $value1 && $product_value <= $value2;
			case 'NOT BETWEEN':
				return $product_value < $value1 || $product_value > $value2;
			default:
				return false;
		}
	}

	/**
	 * Evaluate text condition.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $product_value     Product value.
	 * @param    string $operator          Comparison operator.
	 * @param    array  $condition_values  Condition values.
	 * @return   bool                        True if condition matches.
	 */
	private function evaluate_text_condition( string $product_value, string $operator, string $operator_name, string $value, string $value2 ): bool {
		$search_value  = strval( $value );
		$product_value = strtolower( $product_value );
		$search_value  = strtolower( $search_value );

		switch ( $operator ) {
			case '=':
				return $product_value === $search_value;
			case '!=':
				return $product_value !== $search_value;
			case 'LIKE':
				// Use operator name to distinguish between different LIKE variants
				if ( 'starts_with' === $operator_name ) {
					// Check if product value starts with search value
					return 0 === strpos( $product_value, $search_value );
				} elseif ( 'ends_with' === $operator_name ) {
					// Check if product value ends with search value
					$search_length = strlen( $search_value );
					return $search_length === 0 || substr( $product_value, -$search_length ) === $search_value;
				} elseif ( strpos( $search_value, '%' ) !== false ) {
					// Custom pattern with % wildcards
					$pattern = str_replace( '%', '.*', preg_quote( $search_value, '/' ) );
					return preg_match( '/^' . $pattern . '$/', $product_value ) === 1;
				} else {
					// Default: contains (substring search)
					return strpos( $product_value, $search_value ) !== false;
				}
			case 'NOT LIKE':
				// Use operator name for not_contains
				if ( 'not_contains' === $operator_name ) {
					return strpos( $product_value, $search_value ) === false;
				}
				return strpos( $product_value, $search_value ) === false;
			default:
				return false;
		}
	}

	/**
	 * Evaluate boolean condition.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $product_value     Product value (0 or 1).
	 * @param    string $operator          Comparison operator.
	 * @param    array  $condition_values  Condition values.
	 * @return   bool                        True if condition matches.
	 */
	private function evaluate_boolean_condition( int $product_value, string $operator, string $value, string $value2 ): bool {
		$expected_value = intval( $value );

		switch ( $operator ) {
			case '=':
				return $product_value === $expected_value;
			case '!=':
				return $product_value !== $expected_value;
			default:
				return false;
		}
	}

	/**
	 * Evaluate date condition.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $product_value     Product value (timestamp).
	 * @param    string $operator          Comparison operator.
	 * @param    array  $condition_values  Condition values (date strings).
	 * @return   bool                        True if condition matches.
	 */
	private function evaluate_date_condition( int $product_value, string $operator, string $value, string $value2 ): bool {
		$value1 = strtotime( $value ) ?: 0;
		$value2 = strtotime( $value2 ) ?: 0;

		// Compare only dates, not times
		$product_date = strtotime( gmdate( 'Y-m-d', $product_value ) );
		$value1_date  = strtotime( gmdate( 'Y-m-d', $value1 ) );
		$value2_date  = strtotime( gmdate( 'Y-m-d', $value2 ) );

		switch ( $operator ) {
			case '=':
				return $product_date === $value1_date;
			case '!=':
				return $product_date !== $value1_date;
			case '>':
				return $product_date > $value1_date;
			case '>=':
				return $product_date >= $value1_date;
			case '<':
				return $product_date < $value1_date;
			case '<=':
				return $product_date <= $value1_date;
			case 'BETWEEN':
				return $product_date >= $value1_date && $product_date <= $value2_date;
			case 'NOT BETWEEN':
				return $product_date < $value1_date || $product_date > $value2_date;
			default:
				return false;
		}
	}

	/**
	 * Evaluate select condition.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $product_value     Product value.
	 * @param    string $operator          Comparison operator.
	 * @param    array  $condition_values  Condition values.
	 * @return   bool                        True if condition matches.
	 */
	private function evaluate_select_condition( string $product_value, string $operator, string $value, string $value2 ): bool {
		switch ( $operator ) {
			case '=':
				return $product_value === $value;
			case '!=':
				return $product_value !== $value;
			case 'IN':
				// Split comma-separated values if needed
				$values = array_map( 'trim', explode( ',', $value ) );
				return in_array( $product_value, $values );
			case 'NOT IN':
				// Split comma-separated values if needed
				$values = array_map( 'trim', explode( ',', $value ) );
				return ! in_array( $product_value, $values );
			default:
				return false;
		}
	}

	/**
	 * Normalize operator from symbol to name.
	 *
	 * Converts operator symbols (>, <, =, etc.) to their named equivalents
	 * (greater_than, less_than, equals, etc.) for consistency.
	 *
	 * @since    1.0.0
	 * @param    string $operator    Operator symbol or name.
	 * @return   string                 Normalized operator name.
	 */
	private function normalize_operator( string $operator ): string {
		// First decode HTML entities (in case operator is stored as &lt; or &gt;)
		$operator = html_entity_decode( $operator, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Map of symbols to operator names
		// Must match all operators in Field_Definitions::sanitize_conditions whitelist
		$symbol_map = array(
			'='            => 'equals',
			'!='           => 'not_equals',
			'>'            => 'greater_than',
			'>='           => 'greater_than_equal',
			'<'            => 'less_than',
			'<='           => 'less_than_equal',
			'between'      => 'between',
			'not_between'  => 'not_between',
			'in'           => 'in',
			'not_in'       => 'not_in',
			'contains'     => 'contains',
			'not_contains' => 'not_contains',
			'starts_with'  => 'starts_with',
			'ends_with'    => 'ends_with',
		);

		// If it's a symbol, convert to name
		if ( isset( $symbol_map[ $operator ] ) ) {
			return $symbol_map[ $operator ];
		}

		// Already a name, return as-is
		return $operator;
	}

	/**
	 * Validate condition configuration.
	 *
	 * @since    1.0.0
	 * @param    array $condition    Condition to validate.
	 * @return   bool                   True if valid.
	 */
	public function validate_condition( array $condition ): bool {
		// Use database format: condition_type, value, value2
		$condition_type = $condition['condition_type'] ?? '';

		if ( empty( $condition_type ) || ! isset( $condition['operator'] ) ) {
			return false;
		}

		if ( ! isset( $this->supported_properties[ $condition_type ] ) ) {
			return false;
		}

		// Normalize operator (convert symbol to name if needed)
		$operator = $this->normalize_operator( $condition['operator'] );

		if ( ! isset( $this->supported_operators[ $operator ] ) ) {
			return false;
		}

		$property_config = $this->supported_properties[ $condition_type ];
		$operator_config = $this->supported_operators[ $operator ];

		if ( ! in_array( $property_config['type'], $operator_config['types'] ) ) {
			return false;
		}

		// Check if required values are provided (database format)
		$value  = isset( $condition['value'] ) ? strval( $condition['value'] ) : '';
		$value2 = isset( $condition['value2'] ) ? strval( $condition['value2'] ) : '';

		// Validate based on value_count
		if ( $operator_config['value_count'] === -1 ) {
			// Requires at least one value
			if ( '' === $value ) {
				return false;
			}
		} elseif ( $operator_config['value_count'] === 1 ) {
			// Requires exactly one value
			if ( '' === $value ) {
				return false;
			}
		} elseif ( $operator_config['value_count'] === 2 ) {
			// Requires exactly two values (e.g., BETWEEN)
			if ( '' === $value || '' === $value2 ) {
				return false;
			}
		}

		// Validate value types for numeric properties
		if ( $property_config['type'] === 'numeric' ) {
			if ( '' !== $value && ! is_numeric( $value ) ) {
				return false;
			}
			if ( '' !== $value2 && ! is_numeric( $value2 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get supported properties for UI.
	 *
	 * @since    1.0.0
	 * @return   array    Supported properties.
	 */
	public function get_supported_properties(): array {
		return apply_filters( 'wsscd_condition_engine_properties', $this->supported_properties );
	}

	/**
	 * Get supported operators for UI.
	 *
	 * @since    1.0.0
	 * @param    string $property_type    Property type filter.
	 * @return   array                       Supported operators.
	 */
	public function get_supported_operators( string $property_type = '' ): array {
		$operators = $this->supported_operators;

		if ( ! empty( $property_type ) ) {
			$operators = array_filter(
				$operators,
				function ( $operator ) use ( $property_type ) {
					return in_array( $property_type, $operator['types'] );
				}
			);
		}

		return apply_filters( 'wsscd_condition_engine_operators', $operators, $property_type );
	}

	/**
	 * Build condition query for WP_Query.
	 *
	 * @since    1.0.0
	 * @param    array $conditions    Conditions to convert.
	 * @return   array                   WP_Query compatible meta_query.
	 */
	public function build_meta_query( array $conditions, $logic = 'all' ): array {
		$relation   = ( $logic === 'any' ) ? 'OR' : 'AND';
		$meta_query = array( 'relation' => $relation );

		foreach ( $conditions as $condition ) {
			if ( ! $this->validate_condition( $condition ) ) {
				continue;
			}

			// Standardized format: use 'type'
			$property_type   = $condition['condition_type'] ?? null;
			$property_config = $this->supported_properties[ $property_type ];

			// Skip if property doesn't use meta_key (handled elsewhere)
			if ( ! isset( $property_config['meta_key'] ) ) {
				continue;
			}

			// Validate operator exists
			if ( ! isset( $condition['operator'] ) || ! isset( $this->supported_operators[ $condition['operator'] ] ) ) {
				continue;
			}

			$operator_config = $this->supported_operators[ $condition['operator'] ];
			$meta_key        = $property_config['meta_key'];
			$value           = $condition['value'] ?? '';
			$value2          = $condition['value2'] ?? '';

			$meta_condition = array(
				'key'  => $meta_key,
				'type' => $property_config['type'] === 'numeric' ? 'NUMERIC' : 'CHAR',
			);

			switch ( $operator_config['symbol'] ) {
				case '=':
					$meta_condition['value']   = $value;
					$meta_condition['compare'] = '=';
					break;
				case '!=':
					$meta_condition['value']   = $value;
					$meta_condition['compare'] = '!=';
					break;
				case '>':
					$meta_condition['value']   = $value;
					$meta_condition['compare'] = '>';
					break;
				case '>=':
					$meta_condition['value']   = $value;
					$meta_condition['compare'] = '>=';
					break;
				case '<':
					$meta_condition['value']   = $value;
					$meta_condition['compare'] = '<';
					break;
				case '<=':
					$meta_condition['value']   = $value;
					$meta_condition['compare'] = '<=';
					break;
				case 'BETWEEN':
					$meta_condition['value']   = array( $value, $value2 );
					$meta_condition['compare'] = 'BETWEEN';
					break;
				case 'NOT BETWEEN':
					$meta_condition['value']   = array( $value, $value2 );
					$meta_condition['compare'] = 'NOT BETWEEN';
					break;
				case 'LIKE':
					$meta_condition['value']   = '%' . $value . '%';
					$meta_condition['compare'] = 'LIKE';
					break;
				case 'NOT LIKE':
					$meta_condition['value']   = '%' . $value . '%';
					$meta_condition['compare'] = 'NOT LIKE';
					break;
			}

			$meta_query[] = $meta_condition;
		}

		return $meta_query;
	}

	/**
	 * Get condition summary for display.
	 *
	 * @since    1.0.0
	 * @param    array $conditions    Conditions to summarize.
	 * @return   array                   Condition summaries.
	 */
	public function get_condition_summaries( array $conditions ): array {
		$summaries = array();

		foreach ( $conditions as $index => $condition ) {
			if ( ! $this->validate_condition( $condition ) ) {
				continue;
			}

			// Standardized format: use 'type'
			$property_type   = $condition['condition_type'] ?? null;
			$property_config = $this->supported_properties[ $property_type ];
			$operator_config = $this->supported_operators[ $condition['operator'] ];
			$value           = $condition['value'] ?? '';
			$value2          = $condition['value2'] ?? '';

			$summary = $property_config['label'] . ' ' . $operator_config['label'];

			if ( $operator_config['value_count'] === 1 ) {
				$summary .= ' ' . $value;
			} elseif ( $operator_config['value_count'] === 2 ) {
				$summary .= ' ' . $value . ' and ' . $value2;
			}

			$summaries[] = array(
				'index'    => $index,
				'summary'  => $summary,
				'property' => $property_config['label'],
				'operator' => $operator_config['label'],
				'value'    => $value,
				'value2'   => $value2,
			);
		}

		return $summaries;
	}

	/**
	 * Clear condition cache.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache(): void {
		if ( $this->cache ) {
			$this->cache->flush();
			$this->logger->debug( 'Condition engine cache cleared' );
		}
	}

	/**
	 * Get condition statistics.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to analyze.
	 * @return   array                    Condition statistics.
	 */
	public function get_condition_statistics( array $product_ids ): array {
		$stats = array();

		foreach ( $this->supported_properties as $property => $config ) {
			$values = array();

			foreach ( $product_ids as $product_id ) {
				$value = $this->get_product_property_value( $product_id, $config );
				if ( $config['type'] === 'numeric' && $value > 0 ) {
					$values[] = $value;
				} elseif ( $config['type'] === 'text' && ! empty( $value ) ) {
					$values[] = $value;
				}
			}

			if ( ! empty( $values ) ) {
				if ( $config['type'] === 'numeric' ) {
					$stats[ $property ] = array(
						'type'    => 'numeric',
						'min'     => min( $values ),
						'max'     => max( $values ),
						'average' => array_sum( $values ) / count( $values ),
						'count'   => count( $values ),
					);
				} else {
					$stats[ $property ] = array(
						'type'          => 'text',
						'unique_count'  => count( array_unique( $values ) ),
						'total_count'   => count( $values ),
						'sample_values' => array_slice( array_unique( $values ), 0, 10 ),
					);
				}
			}
		}

		return $stats;
	}
}
