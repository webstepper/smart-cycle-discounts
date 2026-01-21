<?php
/**
 * Tools Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-tools-handler.php
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
 * Tools Actions Handler Class
 *
 * @since 1.0.0
 */
class WSSCD_Tools_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Current action being handled.
	 *
	 * @var string
	 */
	private $current_action = '';

	/**
	 * Constructor.
	 *
	 * @param object     $container Container instance.
	 * @param WSSCD_Logger $logger    Logger instance.
	 */
	public function __construct( $container, $logger ) {
		parent::__construct( $logger );
		$this->container = $container;
	}

	/**
	 * Set the current action for security verification.
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	public function set_action( $action ) {
		$this->current_action = $action;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		// Return the current action being handled for proper security verification.
		// The router already verified security, so this is used for logging.
		return $this->current_action ? $this->current_action : 'wsscd_ajax';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		$start_time = microtime( true );

		$operation = isset( $request['operation'] ) ? sanitize_text_field( $request['operation'] ) : '';

		// Log request start
		$this->logger->flow(
			'info',
			'AJAX START',
			'Processing tools operation',
			array(
				'operation' => $operation,
				'user_id'   => get_current_user_id(),
			)
		);

		if ( empty( $operation ) ) {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'No operation specified',
				array(
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'No operation specified', 'smart-cycle-discounts' ) );
		}

		// Route to the appropriate handler
		switch ( $operation ) {
			case 'optimize':
				return $this->handle_optimize_tables( $start_time );
			case 'cleanup_expired':
				return $this->handle_cleanup_expired( $start_time );
			case 'rebuild_cache':
				return $this->handle_rebuild_cache( $start_time );
			default:
				$this->logger->flow(
					'error',
					'AJAX ERROR',
					'Invalid operation',
					array(
						'operation'   => $operation,
						'_start_time' => $start_time,
					)
				);
				return $this->error( __( 'Invalid operation', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle optimize tables operation.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function handle_optimize_tables( $start_time ) {
		global $wpdb;

		// All plugin tables to optimize
		$tables = array(
			$wpdb->prefix . 'wsscd_campaigns',
			$wpdb->prefix . 'wsscd_campaign_conditions',
			$wpdb->prefix . 'wsscd_active_discounts',
			$wpdb->prefix . 'wsscd_analytics',
			$wpdb->prefix . 'wsscd_customer_usage',
			$wpdb->prefix . 'wsscd_campaign_recurring',
		);

		$total_size_before = 0;
		$total_size_after  = 0;
		$optimized_tables  = array();

		foreach ( $tables as $table ) {
			// Check if table exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; ephemeral check.
			$table_exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);

			if ( $table_exists !== $table ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- information_schema query has no WP abstraction.
			$size_before = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT data_length + index_length FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = %s',
					$table
				)
			);

			// Optimize table - table name prepared with %i identifier placeholder.
			$optimize_sql = $wpdb->prepare( 'OPTIMIZE TABLE %i', $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- OPTIMIZE TABLE on plugin's custom table; query prepared above.
			$wpdb->query( $optimize_sql );

			$size_after_sql = $wpdb->prepare(
				'SELECT data_length + index_length FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = %s',
				$table
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- information_schema query; query prepared above.
			$size_after = $wpdb->get_var( $size_after_sql );

			$total_size_before += (int) $size_before;
			$total_size_after  += (int) $size_after;
			$optimized_tables[] = str_replace( $wpdb->prefix . 'wsscd_', '', $table );
		}

		// Log with performance metrics
		$this->logger->flow(
			'notice',
			'DB OPTIMIZE',
			'Database tables optimized',
			array(
				'tables_optimized' => $optimized_tables,
				'size_before'      => size_format( $total_size_before, 2 ),
				'size_after'       => size_format( $total_size_after, 2 ),
				'user_id'          => get_current_user_id(),
				'_start_time'      => $start_time,
				'_include_memory'  => true,
			)
		);

		return $this->success(
			array(
				/* translators: %d: number of tables optimized */
				'message' => sprintf( __( '%d database tables optimized successfully', 'smart-cycle-discounts' ), count( $optimized_tables ) ),
			)
		);
	}

	/**
	 * Handle cleanup expired data operation.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function handle_cleanup_expired( $start_time ) {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Required for cleanup operation.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE status = %s AND ends_at IS NOT NULL AND ends_at < %s',
				$campaigns_table,
				'expired',
				current_time( 'mysql' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		if ( false === $deleted ) {
			$this->logger->flow(
				'error',
				'DB ERROR',
				'Failed to cleanup expired campaigns',
				array(
					'error'       => $wpdb->last_error,
					'query'       => $wpdb->last_query,
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Database error occurred', 'smart-cycle-discounts' ) );
		}

		// Log successful cleanup
		$this->logger->flow(
			'notice',
			'CAMPAIGN DELETE',
			'Expired campaigns cleaned up',
			array(
				'deleted_count'   => $deleted,
				'user_id'         => get_current_user_id(),
				'_start_time'     => $start_time,
				'_include_memory' => true,
			)
		);

		return $this->success(
			array(
				'message' => sprintf(
				/* translators: %d: number of campaigns deleted */
					_n( '%d expired campaign deleted', '%d expired campaigns deleted', $deleted, 'smart-cycle-discounts' ),
					$deleted
				),
			)
		);
	}

	/**
	 * Handle rebuild cache operation.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function handle_rebuild_cache( $start_time ) {
		$cache_manager = Smart_Cycle_Discounts::get_service( 'cache_manager' );

		// Get stats before clearing
		$stats_before = array(
			'transients' => 0,
			'object_cache' => false,
		);

		if ( $cache_manager && method_exists( $cache_manager, 'get_stats' ) ) {
			$stats = $cache_manager->get_stats();
			$stats_before['transients'] = $stats['transient_count'] ?? 0;
			$stats_before['object_cache'] = $stats['object_cache_available'] ?? false;
		}

		$operations = array();
		$details = array();

		// Use cache manager's flush method (handles both object cache and transients)
		if ( $cache_manager && method_exists( $cache_manager, 'flush' ) ) {
			$cache_manager->flush();
			$operations[] = 'cache_flush';
			$details[] = sprintf(
				/* translators: %d: number of transients cleared */
				__( '%d transients cleared', 'smart-cycle-discounts' ),
				$stats_before['transients']
			);
		} else {
			// Fallback: manual clearing
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
				$operations[] = 'object_cache';
			}

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bulk transient cleanup; no WP abstraction for pattern-based delete.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_wsscd_%',
					'_transient_timeout_wsscd_%'
				)
			);
			if ( false !== $deleted ) {
				$operations[] = 'transients';
				$details[] = sprintf(
					/* translators: %d: number of transients cleared */
					__( '%d transients cleared', 'smart-cycle-discounts' ),
					$deleted
				);
			}
		}

		// Warm cache with fresh data
		if ( $cache_manager && method_exists( $cache_manager, 'warm_cache' ) ) {
			$cache_manager->warm_cache();
			$operations[] = 'cache_warming';
			$details[] = __( 'Cache pre-warmed with active campaigns', 'smart-cycle-discounts' );
		}

		// Get stats after rebuilding
		$stats_after = array(
			'transients' => 0,
		);
		if ( $cache_manager && method_exists( $cache_manager, 'get_stats' ) ) {
			$stats = $cache_manager->get_stats();
			$stats_after['transients'] = $stats['transient_count'] ?? 0;
		}

		// Log cache clear with details
		$this->logger->flow(
			'notice',
			'CACHE CLEAR',
			'Cache cleared and rebuilt',
			array(
				'operations'        => $operations,
				'transients_before' => $stats_before['transients'],
				'transients_after'  => $stats_after['transients'],
				'user_id'           => get_current_user_id(),
				'_start_time'       => $start_time,
				'_include_memory'   => true,
			)
		);

		// Build detailed message
		$message = __( 'Cache cleared and rebuilt successfully.', 'smart-cycle-discounts' );
		if ( ! empty( $details ) ) {
			$message .= ' ' . implode( '. ', $details ) . '.';
		}

		return $this->success(
			array(
				'message'    => $message,
				'stats'      => array(
					'cleared'  => $stats_before['transients'],
					'rebuilt'  => $stats_after['transients'],
					'has_object_cache' => $stats_before['object_cache'],
				),
			)
		);
	}
}
