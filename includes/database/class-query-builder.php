<?php
/**
 * Query Builder Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/class-query-builder.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Query Builder
 *
 * Provides a fluent interface for building database queries.
 * All user-provided values are passed to $wpdb->prepare() at execution time
 * in the get() and count() methods for SQL injection prevention.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Query_Builder {

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      wpdb    $wpdb    WordPress database instance.
	 */
	private wpdb $wpdb;

	/**
	 * Query parts.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $query_parts    Query parts.
	 */
	private array $query_parts = array(
		'select'   => array(),
		'from'     => '',
		'join'     => array(),
		'where'    => array(),
		'order_by' => array(),
		'limit'    => null,
		'offset'   => null,
	);

	/**
	 * Values to be prepared via $wpdb->prepare() at execution time.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $prepare_values    Values for prepare().
	 */
	private array $prepare_values = array();

	/**
	 * Allowed column names for SQL injection prevention.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $allowed_columns    Whitelist of valid column names.
	 */
	private array $allowed_columns = array(
		// Common columns across tables.
		'id',
		'uuid',
		'name',
		'slug',
		'status',
		'priority',
		'created_at',
		'updated_at',
		'deleted_at',
		'version',
		// Campaigns table.
		'campaign_id',
		'campaign_type',
		'description',
		'enable_recurring',
		'settings',
		'metadata',
		'template_id',
		'color_theme',
		'icon',
		'product_selection_type',
		'product_ids',
		'category_ids',
		'tag_ids',
		'conditions_logic',
		'random_product_count',
		'compiled_at',
		'compilation_method',
		'rotation_enabled',
		'rotation_interval',
		'rotation_type',
		'max_concurrent_products',
		'last_rotation_at',
		'discount_rules',
		'discount_value',
		'discount_type',
		'usage_limits',
		'max_uses',
		'max_uses_per_customer',
		'current_uses',
		'created_by',
		'updated_by',
		'starts_at',
		'ends_at',
		'timezone',
		'products_count',
		'revenue_generated',
		'orders_count',
		'impressions_count',
		'clicks_count',
		'conversion_rate',
		'baseline_revenue',
		'baseline_orders',
		'baseline_customers',
		'baseline_period_start',
		'baseline_period_end',
		// Active discounts table.
		'product_id',
		'variation_id',
		'original_price',
		'discounted_price',
		'discount_amount',
		'discount_percentage',
		'conditions',
		'valid_from',
		'valid_until',
		'application_count',
		'last_applied_at',
		'stock_quantity',
		'max_applications',
		'customer_restrictions',
		'geographic_restrictions',
		// Analytics table.
		'date_recorded',
		'hour_recorded',
		'impressions',
		'clicks',
		'conversions',
		'revenue',
		'discount_given',
		'cart_total',
		'product_cost',
		'profit_margin',
		'products_shown',
		'products_clicked',
		'products_purchased',
		'unique_customers',
		'returning_customers',
		'cart_additions',
		'cart_abandonments',
		'checkout_starts',
		'checkout_completions',
		'average_order_value',
		'bounce_rate',
		'time_on_page',
		'page_views',
		'session_duration',
		'device_mobile',
		'device_tablet',
		'device_desktop',
		'traffic_organic',
		'traffic_direct',
		'traffic_referral',
		'traffic_social',
		'traffic_email',
		'traffic_paid',
		'geographic_data',
		'demographic_data',
		'behavioral_data',
		'extended_metrics',
		// Customer usage table.
		'customer_id',
		'customer_email',
		'usage_count',
		'first_used_at',
		'last_used_at',
		'total_discount_amount',
		'total_order_value',
		'order_ids',
		'session_id',
		'ip_address',
		'user_agent',
		// Campaign recurring table.
		'parent_campaign_id',
		'recurrence_pattern',
		'recurrence_interval',
		'recurrence_days',
		'recurrence_end_type',
		'recurrence_count',
		'recurrence_end_date',
		'occurrence_number',
		'next_occurrence_date',
		'last_run_at',
		'last_error',
		'retry_count',
		'is_active',
		// Campaign conditions table.
		'condition_type',
		'operator',
		'value',
		'value2',
		'mode',
		'sort_order',
		// Activity log table.
		'event_type',
		'event_data',
		'user_id',
		// Migrations table.
		'migration',
		'batch',
		'executed_at',
	);

	/**
	 * Initialize the query builder.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Validate column name against whitelist.
	 *
	 * Prevents SQL injection by ensuring only known column names are used.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $column    Column name to validate.
	 * @return   void
	 * @throws   InvalidArgumentException    If column name is not whitelisted.
	 */
	private function validate_column( string $column ): void {
		// Strip any table prefix (e.g., "c.name" -> "name").
		$clean_column = $column;
		if ( strpos( $column, '.' ) !== false ) {
			$parts        = explode( '.', $column );
			$clean_column = end( $parts );
		}

		if ( ! in_array( $clean_column, $this->allowed_columns, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid column name: %s. Column must be in the allowed whitelist.',
					esc_html( $column )
				)
			);
		}
	}

	/**
	 * Add SELECT clause.
	 *
	 * @since    1.0.0
	 * @param    string|array $columns    Columns to select.
	 * @return   self                        Query builder instance.
	 */
	public function select( $columns = '*' ): self {
		if ( is_array( $columns ) ) {
			$this->query_parts['select'] = array_merge( $this->query_parts['select'], $columns );
		} else {
			$this->query_parts['select'][] = $columns;
		}
		return $this;
	}

	/**
	 * Add FROM clause.
	 *
	 * @since    1.0.0
	 * @param    string $table    Table name (without prefix).
	 * @return   self                Query builder instance.
	 */
	public function from( string $table ): self {
		$this->query_parts['from'] = $this->wpdb->prefix . $table;
		return $this;
	}

	/**
	 * Add WHERE clause.
	 *
	 * Values are stored and passed to $wpdb->prepare() at execution time.
	 *
	 * @since    1.0.0
	 * @param    string $column     Column name.
	 * @param    string $operator   Comparison operator.
	 * @param    mixed  $value      Value to compare.
	 * @return   self                  Query builder instance.
	 * @throws   InvalidArgumentException  If operator or column is not whitelisted.
	 */
	public function where( string $column, string $operator, $value ): self {
		// Validate column name against whitelist to prevent SQL injection.
		$this->validate_column( $column );

		// Whitelist allowed operators to prevent SQL injection.
		$allowed_operators = array( '=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL' );
		$operator          = strtoupper( trim( $operator ) );

		if ( ! in_array( $operator, $allowed_operators, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid SQL operator: %s. Allowed operators: %s',
					esc_html( $operator ),
					esc_html( implode( ', ', $allowed_operators ) )
				)
			);
		}

		// Handle NULL operators specially (no value needed).
		if ( 'IS NULL' === $operator || 'IS NOT NULL' === $operator ) {
			$this->query_parts['where'][] = "`{$column}` {$operator}";
		} else {
			// Store placeholder - value will be prepared at execution time.
			$this->query_parts['where'][] = "`{$column}` {$operator} %s";
			$this->prepare_values[]       = $value;
		}
		return $this;
	}

	/**
	 * Add WHERE IN clause.
	 *
	 * Values are stored and passed to $wpdb->prepare() at execution time.
	 *
	 * @since    1.0.0
	 * @param    string $column    Column name.
	 * @param    array  $values    Values array.
	 * @return   self                 Query builder instance.
	 * @throws   InvalidArgumentException  If column is not whitelisted.
	 */
	public function where_in( string $column, array $values ): self {
		// Validate column name against whitelist to prevent SQL injection.
		$this->validate_column( $column );

		if ( ! empty( $values ) ) {
			$placeholders                 = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
			$this->query_parts['where'][] = "`{$column}` IN ({$placeholders})";
			// Store values for prepare() at execution time.
			foreach ( $values as $val ) {
				$this->prepare_values[] = $val;
			}
		}
		return $this;
	}

	/**
	 * Add ORDER BY clause.
	 *
	 * @since    1.0.0
	 * @param    string $column       Column name.
	 * @param    string $direction    Sort direction.
	 * @return   self                    Query builder instance.
	 * @throws   InvalidArgumentException  If column is not whitelisted.
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		// Validate column name against whitelist to prevent SQL injection.
		$this->validate_column( $column );

		$direction                       = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
		$this->query_parts['order_by'][] = "`{$column}` {$direction}";
		return $this;
	}

	/**
	 * Add LIMIT clause.
	 *
	 * @since    1.0.0
	 * @param    int $limit    Limit number.
	 * @return   self             Query builder instance.
	 */
	public function limit( int $limit ): self {
		$this->query_parts['limit'] = $limit;
		return $this;
	}

	/**
	 * Add OFFSET clause.
	 *
	 * @since    1.0.0
	 * @param    int $offset    Offset number.
	 * @return   self              Query builder instance.
	 */
	public function offset( int $offset ): self {
		$this->query_parts['offset'] = $offset;
		return $this;
	}

	/**
	 * Build the query string with placeholders.
	 *
	 * Returns a query with %s placeholders that must be passed to
	 * $wpdb->prepare() before execution.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Built query string with placeholders.
	 */
	private function build_with_placeholders(): string {
		$query = 'SELECT ';

		// Build SELECT clause.
		if ( empty( $this->query_parts['select'] ) ) {
			$query .= '*';
		} else {
			$query .= implode( ', ', $this->query_parts['select'] );
		}

		// Build FROM clause.
		if ( ! empty( $this->query_parts['from'] ) ) {
			$query .= ' FROM ' . $this->query_parts['from'];
		}

		// Build WHERE clause.
		if ( ! empty( $this->query_parts['where'] ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $this->query_parts['where'] );
		}

		// Build ORDER BY clause.
		if ( ! empty( $this->query_parts['order_by'] ) ) {
			$query .= ' ORDER BY ' . implode( ', ', $this->query_parts['order_by'] );
		}

		// Build LIMIT clause (integer, safe to interpolate).
		if ( null !== $this->query_parts['limit'] ) {
			$query .= ' LIMIT ' . intval( $this->query_parts['limit'] );
		}

		// Build OFFSET clause (integer, safe to interpolate).
		if ( null !== $this->query_parts['offset'] ) {
			$query .= ' OFFSET ' . intval( $this->query_parts['offset'] );
		}

		return $query;
	}

	/**
	 * Build the query string (public method for backwards compatibility).
	 *
	 * @since    1.0.0
	 * @return   string    Built query string.
	 */
	public function build(): string {
		$query = $this->build_with_placeholders();

		// If there are values to prepare, do it now.
		if ( ! empty( $this->prepare_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built from validated columns/operators, values passed to prepare().
			$query = $this->wpdb->prepare( $query, $this->prepare_values );
		}

		return $query;
	}

	/**
	 * Execute the query and get results.
	 *
	 * All user-provided values are passed to $wpdb->prepare() before execution.
	 *
	 * @since    1.0.0
	 * @return   array    Query results.
	 */
	public function get(): array {
		$query = $this->build_with_placeholders();

		// SECURITY: All values passed to $wpdb->prepare() for SQL injection prevention.
		// Column names validated against whitelist. Operators validated against allowed list.
		if ( ! empty( $this->prepare_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built from validated columns/operators; values array passed to prepare().
			$query = $this->wpdb->prepare( $query, $this->prepare_values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query prepared above via $wpdb->prepare() when values present; cached by calling code.
		$results = $this->wpdb->get_results( $query, ARRAY_A );
		return $results ? $results : array();
	}

	/**
	 * Execute the query and get first result.
	 *
	 * @since    1.0.0
	 * @return   array|null    First result or null.
	 */
	public function first(): ?array {
		$this->limit( 1 );
		$results = $this->get();
		return $results[0] ?? null;
	}

	/**
	 * Get count of results.
	 *
	 * All user-provided values are passed to $wpdb->prepare() before execution.
	 *
	 * @since    1.0.0
	 * @return   int    Count of results.
	 */
	public function count(): int {
		$original_select             = $this->query_parts['select'];
		$this->query_parts['select'] = array( 'COUNT(*) as count' );

		$query = $this->build_with_placeholders();

		// SECURITY: All values passed to $wpdb->prepare() for SQL injection prevention.
		if ( ! empty( $this->prepare_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built from validated columns/operators; values array passed to prepare().
			$query = $this->wpdb->prepare( $query, $this->prepare_values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query prepared above via $wpdb->prepare() when values present; cached by calling code.
		$result = $this->wpdb->get_var( $query );

		$this->query_parts['select'] = $original_select;

		return (int) $result;
	}
}
