<?php
/**
 * Query builder
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Query Builder
 *
 * Provides a fluent interface for building database queries.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Query_Builder {

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
		'group_by' => array(),
		'having'   => array(),
		'order_by' => array(),
		'limit'    => '',
		'offset'   => '',
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
	 * @param    string $table    Table name.
	 * @return   self                Query builder instance.
	 */
	public function from( string $table ): self {
		$this->query_parts['from'] = $this->wpdb->prefix . $table;
		return $this;
	}

	/**
	 * Add WHERE clause.
	 *
	 * @since    1.0.0
	 * @param    string $column     Column name.
	 * @param    string $operator   Comparison operator.
	 * @param    mixed  $value      Value to compare.
	 * @return   self                  Query builder instance.
	 * @throws   InvalidArgumentException  If operator is not whitelisted.
	 */
	public function where( string $column, string $operator, $value ): self {
		// Whitelist allowed operators to prevent SQL injection
		$allowed_operators = array( '=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE' );
		$operator          = strtoupper( trim( $operator ) );

		if ( ! in_array( $operator, $allowed_operators, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid SQL operator: %s. Allowed operators: %s',
					$operator,
					implode( ', ', $allowed_operators )
				)
			);
		}

		$this->query_parts['where'][] = $this->wpdb->prepare(
			"{$column} {$operator} %s",
			$value
		);
		return $this;
	}

	/**
	 * Add WHERE IN clause.
	 *
	 * @since    1.0.0
	 * @param    string $column    Column name.
	 * @param    array  $values    Values array.
	 * @return   self                 Query builder instance.
	 */
	public function where_in( string $column, array $values ): self {
		if ( ! empty( $values ) ) {
			$placeholders                 = implode( ',', array_fill( 0, count( $values ), '%s' ) );
			$this->query_parts['where'][] = $this->wpdb->prepare(
				"{$column} IN ({$placeholders})",
				...$values
			);
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
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		$direction                       = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
		$this->query_parts['order_by'][] = "{$column} {$direction}";
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
		$this->query_parts['limit'] = "LIMIT {$limit}";
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
		$this->query_parts['offset'] = "OFFSET {$offset}";
		return $this;
	}

	/**
	 * Build the query string.
	 *
	 * @since    1.0.0
	 * @return   string    Built query string.
	 */
	public function build(): string {
		$query = 'SELECT ';

		// SELECT
		if ( empty( $this->query_parts['select'] ) ) {
			$query .= '*';
		} else {
			$query .= implode( ', ', $this->query_parts['select'] );
		}

		// FROM
		if ( ! empty( $this->query_parts['from'] ) ) {
			$query .= ' FROM ' . $this->query_parts['from'];
		}

		// WHERE
		if ( ! empty( $this->query_parts['where'] ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $this->query_parts['where'] );
		}

		// ORDER BY
		if ( ! empty( $this->query_parts['order_by'] ) ) {
			$query .= ' ORDER BY ' . implode( ', ', $this->query_parts['order_by'] );
		}

		// LIMIT
		if ( ! empty( $this->query_parts['limit'] ) ) {
			$query .= ' ' . $this->query_parts['limit'];
		}

		// OFFSET
		if ( ! empty( $this->query_parts['offset'] ) ) {
			$query .= ' ' . $this->query_parts['offset'];
		}

		return $query;
	}

	/**
	 * Execute the query and get results.
	 *
	 * @since    1.0.0
	 * @return   array    Query results.
	 */
	public function get(): array {
		$query   = $this->build();
		$results = $this->wpdb->get_results( $query, ARRAY_A );
		return $results ?: array();
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
	 * @since    1.0.0
	 * @return   int    Count of results.
	 */
	public function count(): int {
		$original_select             = $this->query_parts['select'];
		$this->query_parts['select'] = array( 'COUNT(*) as count' );

		$query  = $this->build();
		$result = $this->wpdb->get_var( $query );

		$this->query_parts['select'] = $original_select;

		return (int) $result;
	}

	/**
	 * Add GROUP BY clause.
	 *
	 * @since    1.0.0
	 * @param    string $field    Field to group by.
	 * @return   self                For method chaining.
	 */
	public function group_by( $field ) {
		$this->query_parts['group_by'][] = $field;
		return $this;
	}

	/**
	 * Get value format for wpdb prepare.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    mixed $value    Value to check.
	 * @return   string             Format specifier.
	 */
	protected function get_value_format( $value ) {
		if ( is_int( $value ) ) {
			return '%d';
		} elseif ( is_float( $value ) ) {
			return '%f';
		} else {
			return '%s';
		}
	}
}
