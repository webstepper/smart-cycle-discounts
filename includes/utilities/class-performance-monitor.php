<?php
/**
 * Performance Monitor Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-performance-monitor.php
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
 * Performance Monitor class.
 *
 * Tracks and logs performance metrics for optimization.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/performance
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Performance_Monitor {

	/**
	 * Performance metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $metrics    Current request metrics.
	 */
	private static array $metrics = array();

	/**
	 * Start timers.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $timers    Active timers.
	 */
	private static array $timers = array();

	/**
	 * Start a performance timer.
	 *
	 * @since    1.0.0
	 * @param    string $name    Timer name.
	 * @return   void
	 */
	public static function start_timer( string $name ): void {
		self::$timers[ $name ] = microtime( true );
	}

	/**
	 * Stop a performance timer.
	 *
	 * @since    1.0.0
	 * @param    string $name    Timer name.
	 * @return   float              Elapsed time in milliseconds.
	 */
	public static function stop_timer( string $name ): float {
		if ( ! isset( self::$timers[ $name ] ) ) {
			return 0.0;
		}

		$elapsed = ( microtime( true ) - self::$timers[ $name ] ) * 1000;
		unset( self::$timers[ $name ] );

		// Record metric
		self::record_metric( $name . '_time', $elapsed, 'ms' );

		return $elapsed;
	}

	/**
	 * Record a performance metric.
	 *
	 * @since    1.0.0
	 * @param    string $name     Metric name.
	 * @param    mixed  $value    Metric value.
	 * @param    string $unit     Unit of measurement.
	 * @return   void
	 */
	public static function record_metric( string $name, $value, string $unit = '' ): void {
		if ( ! isset( self::$metrics[ $name ] ) ) {
			self::$metrics[ $name ] = array(
				'value' => $value,
				'unit'  => $unit,
				'count' => 1,
			);
		} else {
			// Aggregate multiple recordings
			++self::$metrics[ $name ]['count'];
			self::$metrics[ $name ]['value'] =
				( self::$metrics[ $name ]['value'] * ( self::$metrics[ $name ]['count'] - 1 ) + $value )
				/ self::$metrics[ $name ]['count'];
		}
	}

	/**
	 * Track database query.
	 *
	 * @since    1.0.0
	 * @param    string $query    SQL query.
	 * @param    float  $time     Execution time.
	 * @return   void
	 */
	public static function track_query( string $query, float $time ): void {
		// Don't track in production unless debug is enabled
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Track slow queries
		if ( $time > 50 ) { // 50ms threshold
			self::record_metric( 'slow_queries', 1, 'count' );

			if ( get_option( 'wsscd_debug', false ) ) {
				WSSCD_Log::warning(
					'Slow Query',
					array(
						'time_ms' => $time,
						'query'   => substr( $query, 0, 200 ),
					)
				);
			}
		}

		// Track query count
		self::record_metric( 'db_queries', 1, 'count' );
		self::record_metric( 'db_time', $time, 'ms' );
	}

	/**
	 * Track cache hit/miss.
	 *
	 * @since    1.0.0
	 * @param    string $cache_key    Cache key.
	 * @param    bool   $hit          True for hit, false for miss.
	 * @return   void
	 */
	public static function track_cache( string $cache_key, bool $hit ): void {
		$metric_name = $hit ? 'cache_hits' : 'cache_misses';
		self::record_metric( $metric_name, 1, 'count' );

		// Track hit rate
		$hits   = self::$metrics['cache_hits']['value'] ?? 0;
		$misses = self::$metrics['cache_misses']['value'] ?? 0;
		$total  = $hits + $misses;

		if ( $total > 0 ) {
			$hit_rate = ( $hits / $total ) * 100;
			self::record_metric( 'cache_hit_rate', $hit_rate, '%' );
		}
	}

	/**
	 * Track memory usage.
	 *
	 * @since    1.0.0
	 * @param    string $checkpoint    Checkpoint name.
	 * @return   void
	 */
	public static function track_memory( string $checkpoint ): void {
		$memory = memory_get_usage( true ) / 1024 / 1024; // MB
		$peak   = memory_get_peak_usage( true ) / 1024 / 1024; // MB

		self::record_metric( $checkpoint . '_memory', $memory, 'MB' );
		self::record_metric( 'peak_memory', $peak, 'MB' );
	}

	/**
	 * Get all metrics.
	 *
	 * @since    1.0.0
	 * @return   array    All recorded metrics.
	 */
	public static function get_metrics(): array {
		return self::$metrics;
	}

	/**
	 * Get summary report.
	 *
	 * @since    1.0.0
	 * @return   array    Summary of performance metrics.
	 */
	public static function get_summary(): array {
		$summary = array(
			'total_time'     => 0,
			'db_queries'     => 0,
			'db_time'        => 0,
			'cache_hits'     => 0,
			'cache_misses'   => 0,
			'cache_hit_rate' => 0,
			'memory_usage'   => 0,
			'peak_memory'    => 0,
		);

		foreach ( self::$metrics as $name => $metric ) {
			if ( str_ends_with( $name, '_time' ) && $name !== 'db_time' ) {
				$summary['total_time'] += $metric['value'];
			}

			switch ( $name ) {
				case 'db_queries':
					$summary['db_queries'] = $metric['value'] * $metric['count'];
					break;
				case 'db_time':
					$summary['db_time'] = $metric['value'];
					break;
				case 'cache_hits':
					$summary['cache_hits'] = $metric['value'] * $metric['count'];
					break;
				case 'cache_misses':
					$summary['cache_misses'] = $metric['value'] * $metric['count'];
					break;
				case 'cache_hit_rate':
					$summary['cache_hit_rate'] = $metric['value'];
					break;
				case 'peak_memory':
					$summary['peak_memory'] = $metric['value'];
					break;
			}
		}

		return $summary;
	}

	/**
	 * Log performance report.
	 *
	 * @since    1.0.0
	 * @param    string $context    Context for the report.
	 * @return   void
	 */
	public static function log_report( string $context ): void {
		if ( ! get_option( 'wsscd_debug', false ) ) {
			return;
		}

		$summary = self::get_summary();

		WSSCD_Log::info(
			'Performance Report - ' . $context,
			array(
				'total_time_ms'  => $summary['total_time'],
				'db_queries'     => $summary['db_queries'],
				'db_time_ms'     => $summary['db_time'],
				'cache_hit_rate' => $summary['cache_hit_rate'],
				'peak_memory_mb' => $summary['peak_memory'],
			)
		);
	}

	/**
	 * Reset metrics.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function reset(): void {
		self::$metrics = array();
		self::$timers  = array();
	}

	/**
	 * Monitor a function execution.
	 *
	 * @since    1.0.0
	 * @param    callable $callback    Function to monitor.
	 * @param    string   $name        Metric name.
	 * @param    array    $args        Function arguments.
	 * @return   mixed                    Function result.
	 */
	public static function monitor( callable $callback, string $name, array $args = array() ) {
		self::start_timer( $name );
		$memory_before = memory_get_usage( true );

		try {
			$result = call_user_func_array( $callback, $args );

			self::stop_timer( $name );

			$memory_after = memory_get_usage( true );
			$memory_used  = ( $memory_after - $memory_before ) / 1024 / 1024; // MB
			self::record_metric( $name . '_memory', $memory_used, 'MB' );

			return $result;

		} catch ( Exception $e ) {
			self::stop_timer( $name );
			self::record_metric( $name . '_errors', 1, 'count' );
			throw $e;
		}
	}

	/**
	 * Add performance headers to response.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function add_performance_headers(): void {
		if ( ! get_option( 'wsscd_debug', false ) ) {
			return;
		}

		$summary = self::get_summary();

		header( sprintf( 'X-WSSCD-Performance-Time: %.2fms', $summary['total_time'] ) );
		header( sprintf( 'X-WSSCD-Performance-DB: %d queries', $summary['db_queries'] ) );
		header( sprintf( 'X-WSSCD-Performance-Cache: %.1f%% hits', $summary['cache_hit_rate'] ) );
		header( sprintf( 'X-WSSCD-Performance-Memory: %.2fMB', $summary['peak_memory'] ) );
	}
}
