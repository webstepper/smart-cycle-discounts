<?php
/**
 * Tools Actions AJAX Handler
 *
 * Handles all tools page AJAX actions including:
 * - Export/Import
 * - Database maintenance
 * - Cache management
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
class SCD_Tools_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param object     $container Container instance.
	 * @param SCD_Logger $logger    Logger instance.
	 */
	public function __construct( $container, $logger ) {
		parent::__construct( $logger );
		$this->container = $container;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		// This handler handles multiple operations, use the base action
		return 'scd_ajax';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		$start_time = microtime( true );

		// Get the operation type
		$operation = isset( $request['operation'] ) ? sanitize_text_field( $request['operation'] ) : '';

		// Log request start
		$this->logger->flow( 'info', 'AJAX START', 'Processing tools operation', array(
			'operation' => $operation,
			'user_id' => get_current_user_id()
		) );

		if ( empty( $operation ) ) {
			$this->logger->flow( 'error', 'AJAX ERROR', 'No operation specified', array(
				'_start_time' => $start_time
			) );
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
				$this->logger->flow( 'error', 'AJAX ERROR', 'Invalid operation', array(
					'operation' => $operation,
					'_start_time' => $start_time
				) );
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

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';

		// Get table size before optimization
		$size_before = $wpdb->get_var( "SELECT data_length + index_length FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$campaigns_table}'" );

		// Optimize table
		$wpdb->query( "OPTIMIZE TABLE {$campaigns_table}" );

		// Get table size after optimization
		$size_after = $wpdb->get_var( "SELECT data_length + index_length FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$campaigns_table}'" );

		// Log with performance metrics
		$this->logger->flow( 'notice', 'DB OPTIMIZE', 'Database tables optimized', array(
			'table' => 'scd_campaigns',
			'size_before' => size_format( $size_before, 2 ),
			'size_after' => size_format( $size_after, 2 ),
			'user_id' => get_current_user_id(),
			'_start_time' => $start_time,
			'_include_memory' => true
		) );

		return $this->success( array(
			'message' => __( 'Database tables optimized successfully', 'smart-cycle-discounts' )
		) );
	}

	/**
	 * Handle cleanup expired data operation.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function handle_cleanup_expired( $start_time ) {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';

		// Delete expired campaigns
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$campaigns_table} WHERE status = %s AND end_date < %s",
				'expired',
				current_time( 'mysql' )
			)
		);

		// Check for database errors
		if ( false === $deleted ) {
			$this->logger->flow( 'error', 'DB ERROR', 'Failed to cleanup expired campaigns', array(
				'error' => $wpdb->last_error,
				'query' => $wpdb->last_query,
				'_start_time' => $start_time
			) );
			return $this->error( __( 'Database error occurred', 'smart-cycle-discounts' ) );
		}

		// Log successful cleanup
		$this->logger->flow( 'notice', 'CAMPAIGN DELETE', 'Expired campaigns cleaned up', array(
			'deleted_count' => $deleted,
			'user_id' => get_current_user_id(),
			'_start_time' => $start_time,
			'_include_memory' => true
		) );

		return $this->success( array(
			'message' => sprintf(
				/* translators: %d: number of campaigns deleted */
				_n( '%d expired campaign deleted', '%d expired campaigns deleted', $deleted, 'smart-cycle-discounts' ),
				$deleted
			)
		) );
	}

	/**
	 * Handle rebuild cache operation.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function handle_rebuild_cache( $start_time ) {
		$operations = array();

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$operations[] = 'object_cache';
		}

		// Delete all plugin transients
		global $wpdb;
		$deleted = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_scd_%' OR option_name LIKE '_transient_timeout_scd_%'" );
		if ( false !== $deleted ) {
			$operations[] = 'transients';
		}

		// Get cache manager and rebuild
		if ( $this->container->has( 'cache_manager' ) ) {
			$cache_manager = $this->container->get( 'cache_manager' );
			// Trigger cache warming if method exists
			if ( method_exists( $cache_manager, 'warm_cache' ) ) {
				$cache_manager->warm_cache();
				$operations[] = 'cache_warming';
			}
		}

		// Log cache clear with details
		$this->logger->flow( 'notice', 'CACHE CLEAR', 'Cache cleared and rebuilt', array(
			'operations' => $operations,
			'transients_deleted' => $deleted,
			'user_id' => get_current_user_id(),
			'_start_time' => $start_time,
			'_include_memory' => true
		) );

		return $this->success( array(
			'message' => __( 'Cache cleared and rebuilt successfully', 'smart-cycle-discounts' )
		) );
	}
}
