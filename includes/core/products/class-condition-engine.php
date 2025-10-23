<?php
/**
 * Condition Engine
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products
 */

declare(strict_types=1);


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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Condition_Engine {

    /**
     * Logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Logger    $logger    Logger instance.
     */
    private SCD_Logger $logger;

    /**
     * Cache manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Cache_Manager|null    $cache    Cache manager.
     */
    private ?SCD_Cache_Manager $cache = null;

    /**
     * Supported condition properties.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $supported_properties    Supported properties.
     */
    private array $supported_properties = array(
        // Price & Inventory
        'price' => array(
            'type' => 'numeric',
            'label' => 'Regular Price',
            'meta_key' => '_regular_price'
        ),
        'sale_price' => array(
            'type' => 'numeric',
            'label' => 'Sale Price',
            'meta_key' => '_sale_price'
        ),
        'current_price' => array(
            'type' => 'numeric',
            'label' => 'Current Price',
            'meta_key' => '_price'
        ),
        'stock_quantity' => array(
            'type' => 'numeric',
            'label' => 'Stock Quantity',
            'meta_key' => '_stock'
        ),
        'stock_status' => array(
            'type' => 'select',
            'label' => 'Stock Status',
            'meta_key' => '_stock_status',
            'options' => array( 'instock', 'outofstock', 'onbackorder' )
        ),
        'low_stock_amount' => array(
            'type' => 'numeric',
            'label' => 'Low Stock Amount',
            'meta_key' => '_low_stock_amount'
        ),
        
        // Product Attributes
        'weight' => array(
            'type' => 'numeric',
            'label' => 'Weight',
            'meta_key' => '_weight'
        ),
        'length' => array(
            'type' => 'numeric',
            'label' => 'Length',
            'meta_key' => '_length'
        ),
        'width' => array(
            'type' => 'numeric',
            'label' => 'Width',
            'meta_key' => '_width'
        ),
        'height' => array(
            'type' => 'numeric',
            'label' => 'Height',
            'meta_key' => '_height'
        ),
        'sku' => array(
            'type' => 'text',
            'label' => 'SKU',
            'meta_key' => '_sku'
        ),
        
        // Product Status
        'featured' => array(
            'type' => 'boolean',
            'label' => 'Featured Product',
            'meta_key' => '_featured'
        ),
        'on_sale' => array(
            'type' => 'boolean',
            'label' => 'On Sale',
            'callback' => 'is_on_sale'
        ),
        'virtual' => array(
            'type' => 'boolean',
            'label' => 'Virtual Product',
            'meta_key' => '_virtual'
        ),
        'downloadable' => array(
            'type' => 'boolean',
            'label' => 'Downloadable',
            'meta_key' => '_downloadable'
        ),
        'product_type' => array(
            'type' => 'select',
            'label' => 'Product Type',
            'callback' => 'get_type',
            'options' => array( 'simple', 'variable', 'grouped', 'external' )
        ),
        
        // Shipping & Tax
        'tax_status' => array(
            'type' => 'select',
            'label' => 'Tax Status',
            'meta_key' => '_tax_status',
            'options' => array( 'taxable', 'shipping', 'none' )
        ),
        'tax_class' => array(
            'type' => 'text',
            'label' => 'Tax Class',
            'meta_key' => '_tax_class'
        ),
        'shipping_class' => array(
            'type' => 'text',
            'label' => 'Shipping Class',
            'callback' => 'get_shipping_class_id'
        ),
        
        // Reviews & Ratings
        'average_rating' => array(
            'type' => 'numeric',
            'label' => 'Average Rating',
            'meta_key' => '_wc_average_rating'
        ),
        'review_count' => array(
            'type' => 'numeric',
            'label' => 'Review Count',
            'meta_key' => '_wc_review_count'
        ),
        
        // Sales Data
        'total_sales' => array(
            'type' => 'numeric',
            'label' => 'Total Sales',
            'meta_key' => 'total_sales'
        ),
        'date_created' => array(
            'type' => 'date',
            'label' => 'Date Created',
            'field' => 'post_date'
        ),
        'date_modified' => array(
            'type' => 'date',
            'label' => 'Date Modified',
            'field' => 'post_modified'
        ),
        
        // Legacy mappings for backward compatibility
        'product_name' => array(
            'type' => 'text',
            'label' => 'Product Name',
            'field' => 'post_title'
        ),
        'rating' => array(
            'type' => 'numeric',
            'label' => 'Average Rating',
            'meta_key' => '_wc_average_rating'
        ),
        'regular_price' => array(
            'type' => 'numeric',
            'label' => 'Regular Price',
            'meta_key' => '_regular_price'
        )
    );

    /**
     * Supported comparison operators.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $supported_operators    Supported operators.
     */
    private array $supported_operators = array(
        'equals' => array(
            'symbol' => '=',
            'label' => 'Equals',
            'types' => array('numeric', 'text'),
            'value_count' => 1
        ),
        'not_equals' => array(
            'symbol' => '!=',
            'label' => 'Not equals',
            'types' => array('numeric', 'text'),
            'value_count' => 1
        ),
        'greater_than' => array(
            'symbol' => '>',
            'label' => 'Greater than',
            'types' => array('numeric'),
            'value_count' => 1
        ),
        'greater_than_equal' => array(
            'symbol' => '>=',
            'label' => 'Greater than or equal',
            'types' => array('numeric'),
            'value_count' => 1
        ),
        'less_than' => array(
            'symbol' => '<',
            'label' => 'Less than',
            'types' => array('numeric'),
            'value_count' => 1
        ),
        'less_than_equal' => array(
            'symbol' => '<=',
            'label' => 'Less than or equal',
            'types' => array('numeric'),
            'value_count' => 1
        ),
        'between' => array(
            'symbol' => 'BETWEEN',
            'label' => 'Between two values',
            'types' => array('numeric'),
            'value_count' => 2
        ),
        'not_between' => array(
            'symbol' => 'NOT BETWEEN',
            'label' => 'Not between two values',
            'types' => array('numeric'),
            'value_count' => 2
        ),
        'contains' => array(
            'symbol' => 'LIKE',
            'label' => 'Text contains substring',
            'types' => array('text'),
            'value_count' => 1
        ),
        'not_contains' => array(
            'symbol' => 'NOT LIKE',
            'label' => 'Text doesn\'t contain substring',
            'types' => array('text'),
            'value_count' => 1
        ),
        'starts_with' => array(
            'symbol' => 'LIKE',
            'label' => 'Text starts with',
            'types' => array('text'),
            'value_count' => 1
        ),
        'ends_with' => array(
            'symbol' => 'LIKE',
            'label' => 'Text ends with',
            'types' => array('text'),
            'value_count' => 1
        ),
        'in' => array(
            'symbol' => 'IN',
            'label' => 'Is one of',
            'types' => array('select'),
            'value_count' => -1 // Multiple values
        ),
        'not_in' => array(
            'symbol' => 'NOT IN',
            'label' => 'Is not one of',
            'types' => array('select'),
            'value_count' => -1 // Multiple values
        )
    );

    /**
     * Initialize the condition engine.
     *
     * @since    1.0.0
     * @param    SCD_Logger              $logger    Logger instance.
     * @param    SCD_Cache_Manager       $cache     Cache manager.
     */
    public function __construct(SCD_Logger $logger, ?SCD_Cache_Manager $cache = null) {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Apply conditions to product query.
     *
     * @since    1.0.0
     * @param    array    $product_ids    Product IDs to filter.
     * @param    array    $conditions     Conditions to apply.
     * @return   array                    Filtered product IDs.
     */
    public function apply_conditions(array $product_ids, array $conditions, $logic = 'all'): array {
        if (empty($product_ids) || empty($conditions)) {
            return $product_ids;
        }

        $cache_key = 'scd_condition_filter_' . md5(serialize($product_ids) . serialize($conditions));

        // Try to get from cache first
        if ($this->cache) {
            $cached_result = $this->cache->get($cache_key);
            if ($cached_result !== false && is_array($cached_result)) {
                $this->logger->debug('Condition filtering retrieved from cache', array(
                    'product_count' => count($product_ids),
                    'condition_count' => count($conditions)
                ));
                return $cached_result;
            }
        }

        $filtered_ids = array();

        try {
            if ( $logic === 'any' ) {
                // OR logic - collect products that match ANY condition
                foreach ($conditions as $condition) {
                    if (!$this->validate_condition($condition)) {
                        $this->logger->warning('Invalid condition skipped', array('condition' => $condition));
                        continue;
                    }

                    $matching_ids = $this->apply_single_condition($product_ids, $condition);
                    $filtered_ids = array_unique(array_merge($filtered_ids, $matching_ids));
                }
            } else {
                // AND logic - products must match ALL conditions
                $filtered_ids = $product_ids;
                foreach ($conditions as $condition) {
                    if (!$this->validate_condition($condition)) {
                        $this->logger->warning('Invalid condition skipped', array('condition' => $condition));
                        continue;
                    }

                    $filtered_ids = $this->apply_single_condition($filtered_ids, $condition);
                    
                    // If no products remain, break early
                    if (empty($filtered_ids)) {
                        break;
                    }
                }
            }

            // Cache the result
            if ($this->cache) {
                $this->cache->set($cache_key, $filtered_ids, 1800); // Cache for 30 minutes
            }

            $this->logger->info('Conditions applied successfully', array(
                'original_count' => count($product_ids),
                'filtered_count' => count($filtered_ids),
                'condition_count' => count($conditions)
            ));

        } catch (Exception $e) {
            $this->logger->error('Condition application failed', array(
                'error' => $e->getMessage(), 'conditions' => $conditions
            ));
            return $product_ids; // Return original IDs on error
        }

        return $filtered_ids;
    }

    /**
     * Apply a single condition to product IDs.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $product_ids    Product IDs.
     * @param    array    $condition      Condition to apply.
     * @return   array                    Filtered product IDs.
     */
    private function apply_single_condition(array $product_ids, array $condition): array {
        $property = $condition['property'];
        $operator = $condition['operator'];
        $values = $condition['values'];
        $mode = $condition['mode'] ?? 'include';

        if ( ! isset( $this->supported_properties[$property] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] ERROR: Property not found: ' . $property );
            }
            return $product_ids; // Return all products if property invalid
        }

        if ( ! isset( $this->supported_operators[$operator] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] ERROR: Operator not found: ' . $operator );
            }
            return $product_ids; // Return all products if operator invalid
        }

        $property_config = $this->supported_properties[$property];
        $operator_config = $this->supported_operators[$operator];

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Condition Engine] Applying condition: ' . $property . ' ' . $operator . ' ' . implode( ', ', $values ) );
            error_log( '[Condition Engine] Property type: ' . $property_config['type'] );
            error_log( '[Condition Engine] Operator symbol: ' . $operator_config['symbol'] );
            error_log( '[Condition Engine] Mode: ' . $mode );
        }

        $filtered = array_filter($product_ids, function($product_id) use ($property_config, $operator_config, $values, $mode, $property, $operator) {
            $product_value = $this->get_product_property_value($product_id, $property_config);
            $result = $this->evaluate_condition($product_value, $operator_config, $values, $property_config['type']);

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $product_id <= 85 ) { // Only log first few products
                error_log( sprintf(
                    '[Condition Engine] Product #%d: value=%s, condition=%s %s %s, result=%s, mode=%s, keep=%s',
                    $product_id,
                    $product_value,
                    $property,
                    $operator,
                    implode(',', $values),
                    $result ? 'TRUE' : 'FALSE',
                    $mode,
                    ( $mode === 'exclude' ? !$result : $result ) ? 'YES' : 'NO'
                ) );
            }

            // If mode is exclude, invert the result
            return $mode === 'exclude' ? !$result : $result;
        });

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Condition Engine] Filtered from ' . count( $product_ids ) . ' to ' . count( $filtered ) . ' products' );
        }

        return $filtered;
    }

    /**
     * Get product property value.
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id        Product ID.
     * @param    array    $property_config   Property configuration.
     * @return   mixed                       Property value.
     */
    private function get_product_property_value(int $product_id, array $property_config): mixed {
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
     * @param    string   $type    Property type.
     * @return   mixed              Default value.
     */
    private function get_default_value( string $type ): mixed {
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
     * @param    mixed    $value    Raw value.
     * @param    string   $type     Property type.
     * @return   mixed               Normalized value.
     */
    private function normalize_value( mixed $value, string $type ): mixed {
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
     * @param    mixed    $product_value     Product value.
     * @param    array    $operator_config   Operator configuration.
     * @param    array    $condition_values  Condition values.
     * @param    string   $type              Value type.
     * @return   bool                        True if condition matches.
     */
    private function evaluate_condition(mixed $product_value, array $operator_config, array $condition_values, string $type): bool {
        $operator = $operator_config['symbol'];
        
        switch ( $type ) {
            case 'numeric':
                return $this->evaluate_numeric_condition( $product_value, $operator, $condition_values );
            case 'boolean':
                return $this->evaluate_boolean_condition( $product_value, $operator, $condition_values );
            case 'date':
                return $this->evaluate_date_condition( $product_value, $operator, $condition_values );
            case 'select':
                return $this->evaluate_select_condition( $product_value, $operator, $condition_values );
            case 'text':
            default:
                return $this->evaluate_text_condition( $product_value, $operator, $condition_values );
        }
    }

    /**
     * Evaluate numeric condition.
     *
     * @since    1.0.0
     * @access   private
     * @param    float    $product_value     Product value.
     * @param    string   $operator          Comparison operator.
     * @param    array    $condition_values  Condition values.
     * @return   bool                        True if condition matches.
     */
    private function evaluate_numeric_condition(float $product_value, string $operator, array $condition_values): bool {
        $value1 = floatval($condition_values[0] ?? 0);
        $value2 = floatval($condition_values[1] ?? 0);

        switch ($operator) {
            case '=':
                return abs($product_value - $value1) < 0.01; // Float comparison with tolerance
            case '!=':
                return abs($product_value - $value1) >= 0.01;
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
     * @param    string   $product_value     Product value.
     * @param    string   $operator          Comparison operator.
     * @param    array    $condition_values  Condition values.
     * @return   bool                        True if condition matches.
     */
    private function evaluate_text_condition(string $product_value, string $operator, array $condition_values): bool {
        $search_value = strval($condition_values[0] ?? '');
        $product_value = strtolower($product_value);
        $search_value = strtolower($search_value);

        switch ($operator) {
            case '=':
                return $product_value === $search_value;
            case '!=':
                return $product_value !== $search_value;
            case 'LIKE':
                // Handle different LIKE patterns based on context
                if (strpos($search_value, '%') !== false) {
                    // Custom pattern
                    $pattern = str_replace('%', '.*', preg_quote($search_value, '/'));
                    return preg_match('/^' . $pattern . '$/', $product_value) === 1;
                } else {
                    // Default contains
                    return strpos($product_value, $search_value) !== false;
                }
            case 'NOT LIKE':
                return strpos($product_value, $search_value) === false;
            default:
                return false;
        }
    }
    
    /**
     * Evaluate boolean condition.
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_value     Product value (0 or 1).
     * @param    string   $operator          Comparison operator.
     * @param    array    $condition_values  Condition values.
     * @return   bool                        True if condition matches.
     */
    private function evaluate_boolean_condition( int $product_value, string $operator, array $condition_values ): bool {
        $expected_value = intval( $condition_values[0] ?? 0 );
        
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
     * @param    int      $product_value     Product value (timestamp).
     * @param    string   $operator          Comparison operator.
     * @param    array    $condition_values  Condition values (date strings).
     * @return   bool                        True if condition matches.
     */
    private function evaluate_date_condition( int $product_value, string $operator, array $condition_values ): bool {
        $value1 = strtotime( $condition_values[0] ?? '' ) ?: 0;
        $value2 = strtotime( $condition_values[1] ?? '' ) ?: 0;
        
        // Compare only dates, not times
        $product_date = strtotime( date( 'Y-m-d', $product_value ) );
        $value1_date = strtotime( date( 'Y-m-d', $value1 ) );
        $value2_date = strtotime( date( 'Y-m-d', $value2 ) );
        
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
     * @param    string   $product_value     Product value.
     * @param    string   $operator          Comparison operator.
     * @param    array    $condition_values  Condition values.
     * @return   bool                        True if condition matches.
     */
    private function evaluate_select_condition( string $product_value, string $operator, array $condition_values ): bool {
        switch ( $operator ) {
            case '=':
                return $product_value === ( $condition_values[0] ?? '' );
            case '!=':
                return $product_value !== ( $condition_values[0] ?? '' );
            case 'IN':
                return in_array( $product_value, $condition_values );
            case 'NOT IN':
                return ! in_array( $product_value, $condition_values );
            default:
                return false;
        }
    }

    /**
     * Validate condition configuration.
     *
     * @since    1.0.0
     * @param    array    $condition    Condition to validate.
     * @return   bool                   True if valid.
     */
    public function validate_condition(array $condition): bool {
        // Check required fields
        if (!isset($condition['property'], $condition['operator'], $condition['values'])) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] VALIDATION FAILED: Missing required fields' );
                error_log( '[Condition Engine] Condition: ' . print_r( $condition, true ) );
                error_log( '[Condition Engine] Has property: ' . ( isset( $condition['property'] ) ? 'YES' : 'NO' ) );
                error_log( '[Condition Engine] Has operator: ' . ( isset( $condition['operator'] ) ? 'YES' : 'NO' ) );
                error_log( '[Condition Engine] Has values: ' . ( isset( $condition['values'] ) ? 'YES' : 'NO' ) );
            }
            return false;
        }

        // Validate property
        if (!isset($this->supported_properties[$condition['property']])) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] VALIDATION FAILED: Property not supported: ' . $condition['property'] );
            }
            return false;
        }

        // Validate operator
        if (!isset($this->supported_operators[$condition['operator']])) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] VALIDATION FAILED: Operator not supported: ' . $condition['operator'] );
            }
            return false;
        }

        $property_config = $this->supported_properties[$condition['property']];
        $operator_config = $this->supported_operators[$condition['operator']];

        // Check if operator is compatible with property type
        if ( ! in_array( $property_config['type'], $operator_config['types'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] VALIDATION FAILED: Operator incompatible with property type' );
                error_log( '[Condition Engine] Property type: ' . $property_config['type'] );
                error_log( '[Condition Engine] Operator types: ' . implode( ', ', $operator_config['types'] ) );
            }
            return false;
        }

        // Validate values count
        if ( ! is_array( $condition['values'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Condition Engine] VALIDATION FAILED: values is not an array' );
                error_log( '[Condition Engine] values type: ' . gettype( $condition['values'] ) );
                error_log( '[Condition Engine] values content: ' . print_r( $condition['values'], true ) );
            }
            return false;
        }

        // Handle multiple values (value_count = -1)
        if ( $operator_config['value_count'] === -1 ) {
            if ( count( $condition['values'] ) < 1 ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Condition Engine] VALIDATION FAILED: No values provided for multi-value operator' );
                }
                return false;
            }
        } else {
            if ( count( $condition['values'] ) !== $operator_config['value_count'] ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Condition Engine] VALIDATION FAILED: Wrong number of values' );
                    error_log( '[Condition Engine] Expected: ' . $operator_config['value_count'] );
                    error_log( '[Condition Engine] Received: ' . count( $condition['values'] ) );
                    error_log( '[Condition Engine] Values: ' . print_r( $condition['values'], true ) );
                }
                return false;
            }
        }

        // Validate value types
        foreach ($condition['values'] as $value) {
            if ($property_config['type'] === 'numeric' && !is_numeric($value)) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Condition Engine] VALIDATION FAILED: Non-numeric value for numeric property' );
                    error_log( '[Condition Engine] Value: ' . $value . ' (type: ' . gettype( $value ) . ')' );
                }
                return false;
            }
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Condition Engine] VALIDATION PASSED for condition: ' . $condition['property'] . ' ' . $condition['operator'] . ' ' . implode( ', ', $condition['values'] ) );
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
        return apply_filters('scd_condition_engine_properties', $this->supported_properties);
    }

    /**
     * Get supported operators for UI.
     *
     * @since    1.0.0
     * @param    string    $property_type    Property type filter.
     * @return   array                       Supported operators.
     */
    public function get_supported_operators(string $property_type = ''): array {
        $operators = $this->supported_operators;

        if (!empty($property_type)) {
            $operators = array_filter($operators, function($operator) use ($property_type) {
                return in_array($property_type, $operator['types']);
            });
        }

        return apply_filters('scd_condition_engine_operators', $operators, $property_type);
    }

    /**
     * Build condition query for WP_Query.
     *
     * @since    1.0.0
     * @param    array    $conditions    Conditions to convert.
     * @return   array                   WP_Query compatible meta_query.
     */
    public function build_meta_query(array $conditions, $logic = 'all'): array {
        $relation = ( $logic === 'any' ) ? 'OR' : 'AND';
        $meta_query = array('relation' => $relation);

        foreach ($conditions as $condition) {
            if (!$this->validate_condition($condition)) {
                continue;
            }

            $property_config = $this->supported_properties[$condition['property']];
            
            // Skip if property doesn't use meta_key (handled elsewhere)
            if (!isset($property_config['meta_key'])) {
                continue;
            }

            $operator_config = $this->supported_operators[$condition['operator']];
            $meta_key = $property_config['meta_key'];
            $values = $condition['values'];

            $meta_condition = array(
                'key' => $meta_key,
                'type' => $property_config['type'] === 'numeric' ? 'NUMERIC' : 'CHAR'
            );

            switch ($operator_config['symbol']) {
                case '=':
                    $meta_condition['value'] = $values[0];
                    $meta_condition['compare'] = '=';
                    break;
                case '!=':
                    $meta_condition['value'] = $values[0];
                    $meta_condition['compare'] = '!=';
                    break;
                case '>':
                    $meta_condition['value'] = $values[0];
                    $meta_condition['compare'] = '>';
                    break;
                case '>=':
                    $meta_condition['value'] = $values[0];
                    $meta_condition['compare'] = '>=';
                    break;
                case '<':
                    $meta_condition['value'] = $values[0];
                    $meta_condition['compare'] = '<';
                    break;
                case '<=':
                    $meta_condition['value'] = $values[0];
                    $meta_condition['compare'] = '<=';
                    break;
                case 'BETWEEN':
                    $meta_condition['value'] = array($values[0], $values[1]);
                    $meta_condition['compare'] = 'BETWEEN';
                    break;
                case 'NOT BETWEEN':
                    $meta_condition['value'] = array($values[0], $values[1]);
                    $meta_condition['compare'] = 'NOT BETWEEN';
                    break;
                case 'LIKE':
                    $meta_condition['value'] = '%' . $values[0] . '%';
                    $meta_condition['compare'] = 'LIKE';
                    break;
                case 'NOT LIKE':
                    $meta_condition['value'] = '%' . $values[0] . '%';
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
     * @param    array    $conditions    Conditions to summarize.
     * @return   array                   Condition summaries.
     */
    public function get_condition_summaries(array $conditions): array {
        $summaries = array();

        foreach ($conditions as $index => $condition) {
            if (!$this->validate_condition($condition)) {
                continue;
            }

            $property_config = $this->supported_properties[$condition['property']];
            $operator_config = $this->supported_operators[$condition['operator']];
            $values = $condition['values'];

            $summary = $property_config['label'] . ' ' . $operator_config['label'];

            if ($operator_config['value_count'] === 1) {
                $summary .= ' ' . $values[0];
            } elseif ($operator_config['value_count'] === 2) {
                $summary .= ' ' . $values[0] . ' and ' . $values[1];
            }

            $summaries[] = array(
                'index' => $index,
                'summary' => $summary,
                'property' => $property_config['label'],
                'operator' => $operator_config['label'],
                'values' => $values
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
            // Clear cache using flush since we don't have delete_group
            $this->cache->flush();
            $this->logger->debug( 'Condition engine cache cleared' );
        }
    }

    /**
     * Get condition statistics.
     *
     * @since    1.0.0
     * @param    array    $product_ids    Product IDs to analyze.
     * @return   array                    Condition statistics.
     */
    public function get_condition_statistics(array $product_ids): array {
        $stats = array();

        foreach ($this->supported_properties as $property => $config) {
            $values = array();
            
            foreach ($product_ids as $product_id) {
                $value = $this->get_product_property_value($product_id, $config);
                if ($config['type'] === 'numeric' && $value > 0) {
                    $values[] = $value;
                } elseif ($config['type'] === 'text' && !empty($value)) {
                    $values[] = $value;
                }
            }

            if (!empty($values)) {
                if ($config['type'] === 'numeric') {
                    $stats[$property] = array(
                        'type' => 'numeric',
                        'min' => min($values),
                        'max' => max($values),
                        'average' => array_sum($values) / count($values),
                        'count' => count($values)
                    );
                } else {
                    $stats[$property] = array(
                        'type' => 'text',
                        'unique_count' => count(array_unique($values)),
                        'total_count' => count($values),
                        'sample_values' => array_slice(array_unique($values), 0, 10)
                    );
                }
            }
        }

        return $stats;
    }
}

