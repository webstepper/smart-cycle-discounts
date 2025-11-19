<?php
/**
 * Occurrence Cache Manager
 *
 * Manages cached recurring campaign occurrences for performance and preview
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @author     Webstepper <contact@webstepper.io>
 * @since      1.1.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Occurrence Cache Manager
 *
 * @since 1.1.0
 */
class SCD_Occurrence_Cache {

	/**
	 * Database instance
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Cache table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Logger instance
	 *
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * How many days ahead to cache
	 *
	 * @var int
	 */
	private $cache_horizon_days = 90;

	/**
	 * Initialize cache manager
	 *
	 * @since 1.1.0
	 * @param SCD_Logger $logger Logger instance.
	 */
	public function __construct( SCD_Logger $logger ) {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'scd_recurring_cache';
		$this->logger     = $logger;
	}

	/**
	 * Regenerate occurrence cache for parent campaign
	 *
	 * @since  1.1.0
	 * @param  int   $parent_id Parent campaign ID.
	 * @param  array $recurring Recurring settings.
	 * @param  array $schedule  Campaign schedule.
	 * @return int              Number of occurrences cached.
	 */
	public function regenerate( int $parent_id, array $recurring, array $schedule ): int {
		// Delete existing pending occurrences
		$this->wpdb->delete(
			$this->table_name,
			array(
				'parent_campaign_id' => $parent_id,
				'status'             => 'pending',
			),
			array( '%d', '%s' )
		);

		// Calculate occurrences
		$occurrences = $this->calculate_occurrences( $recurring, $schedule );

		if ( empty( $occurrences ) ) {
			return 0;
		}

		// Get next occurrence number
		$next_number = $this->get_next_occurrence_number( $parent_id );

		// Insert into cache
		$inserted = 0;
		foreach ( $occurrences as $dates ) {
			$result = $this->wpdb->insert(
				$this->table_name,
				array(
					'parent_campaign_id' => $parent_id,
					'occurrence_number'  => $next_number,
					'occurrence_start'   => $dates['start'],
					'occurrence_end'     => $dates['end'],
					'status'             => 'pending',
					'created_at'         => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( $result ) {
				++$inserted;
				++$next_number;
			}
		}

		$this->logger->info(
			'Generated occurrence cache',
			array(
				'parent_id'   => $parent_id,
				'occurrences' => $inserted,
			)
		);

		return $inserted;
	}

	/**
	 * Calculate occurrence dates using simple date math
	 *
	 * @since  1.1.0
	 * @param  array $recurring Recurring settings.
	 * @param  array $schedule  Parent schedule.
	 * @return array            Array of occurrence dates.
	 */
	private function calculate_occurrences( array $recurring, array $schedule ): array {
		$occurrences = array();
		$pattern     = $recurring['recurrence_pattern'] ?? 'daily';
		$interval    = isset( $recurring['recurrence_interval'] ) ? (int) $recurring['recurrence_interval'] : 1;

		// Validate required fields - must have start_date
		if ( empty( $schedule['start_date'] ) ) {
			$this->logger->error( 'Cannot calculate occurrences: missing start_date', array( 'parent_id' => $parent_id ) );
			return array();
		}

		// Calculate campaign duration - support both end_date and duration_seconds
		$start_time = $schedule['start_time'] ?? '00:00';
		$end_time   = $schedule['end_time'] ?? '23:59';
		$duration   = null;

		try {
			$start = new DateTime( $schedule['start_date'] . ' ' . $start_time, new DateTimeZone( wp_timezone_string() ) );

			// Option 1: Explicit end_date (most common)
			if ( ! empty( $schedule['end_date'] ) ) {
				$end      = new DateTime( $schedule['end_date'] . ' ' . $end_time, new DateTimeZone( wp_timezone_string() ) );
				$duration = $start->diff( $end );
			} elseif ( ! empty( $schedule['duration_seconds'] ) && $schedule['duration_seconds'] > 0 ) {
				// Option 2: Duration-based (timeline presets like "Flash Sale - 6 Hours", "3 Day Weekend")
				$duration = new DateInterval( 'PT' . (int) $schedule['duration_seconds'] . 'S' );
				$end      = clone $start;
				$end->add( $duration );
			} else {
				// Option 3: Invalid - no way to determine duration
				$this->logger->error(
					'Cannot calculate occurrences: no end_date or duration_seconds',
					array(
						'parent_id' => $parent_id,
						'schedule'  => $schedule,
					)
				);
				return array();
			}

			// Start from end of current campaign (occurrences begin after parent ends)
			$next_start  = clone $end;
			$cache_limit = new DateTime( '+' . $this->cache_horizon_days . ' days', new DateTimeZone( wp_timezone_string() ) );

			// Generate occurrences
			$count = 0;
			while ( $next_start < $cache_limit && $count < 100 ) { // Safety limit.
				// Calculate next occurrence start
				switch ( $pattern ) {
					case 'daily':
						$next_start->modify( '+' . $interval . ' days' );
						break;
					case 'weekly':
						$next_start->modify( '+' . ( $interval * 7 ) . ' days' );
						break;
					case 'monthly':
						$next_start->modify( '+' . $interval . ' months' );
						break;
					default:
						return array(); // Invalid pattern.
				}

				// Check end conditions
				if ( ! $this->should_create_occurrence( $recurring, $next_start, $count + 1 ) ) {
					break;
				}

				// Calculate end date (same duration as parent)
				$next_end = clone $next_start;
				$next_end->add( $duration );

				$occurrences[] = array(
					'start' => $next_start->format( 'Y-m-d H:i:s' ),
					'end'   => $next_end->format( 'Y-m-d H:i:s' ),
				);

				++$count;
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate occurrences',
				array( 'error' => $e->getMessage() )
			);
			return array();
		}

		return $occurrences;
	}

	/**
	 * Check if should create occurrence based on end conditions
	 *
	 * @since  1.1.0
	 * @param  array    $recurring Recurring settings.
	 * @param  DateTime $date      Occurrence date.
	 * @param  int      $count     Current occurrence count.
	 * @return bool                Whether to create occurrence.
	 */
	private function should_create_occurrence( array $recurring, DateTime $date, int $count ): bool {
		$end_type = $recurring['recurrence_end_type'] ?? 'never';

		if ( 'never' === $end_type ) {
			return true;
		}

		if ( 'after' === $end_type ) {
			$max_count = isset( $recurring['recurrence_count'] ) ? (int) $recurring['recurrence_count'] : 10;
			return $count <= $max_count;
		}

		if ( 'on' === $end_type && ! empty( $recurring['recurrence_end_date'] ) ) {
			try {
				$end_date = new DateTime( $recurring['recurrence_end_date'], new DateTimeZone( wp_timezone_string() ) );
				return $date <= $end_date;
			} catch ( Exception $e ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Get pending occurrences due for materialization
	 *
	 * @since  1.1.0
	 * @param  int $lookahead_minutes Minutes to look ahead (default: 10).
	 * @return array                  Array of pending occurrences.
	 */
	public function get_due_occurrences( int $lookahead_minutes = 10 ): array {
		$threshold = gmdate( 'Y-m-d H:i:s', time() + ( $lookahead_minutes * MINUTE_IN_SECONDS ) );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				 WHERE status = 'pending'
				 AND occurrence_start <= %s
				 ORDER BY occurrence_start ASC
				 LIMIT 100",
				$threshold
			),
			ARRAY_A
		);
	}

	/**
	 * Get single occurrence by parent and number
	 *
	 * @since  1.1.0
	 * @param  int $parent_id         Parent campaign ID.
	 * @param  int $occurrence_number Occurrence number.
	 * @return array|null             Occurrence data or null.
	 */
	public function get_occurrence( int $parent_id, int $occurrence_number ): ?array {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				 WHERE parent_campaign_id = %d
				 AND occurrence_number = %d",
				$parent_id,
				$occurrence_number
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Mark occurrence as materialized
	 *
	 * @since  1.1.0
	 * @param  int $cache_id    Cache record ID.
	 * @param  int $instance_id Created instance campaign ID.
	 * @return bool             Success status.
	 */
	public function mark_materialized( int $cache_id, int $instance_id ): bool {
		return (bool) $this->wpdb->update(
			$this->table_name,
			array(
				'status'       => 'active',
				'instance_id'  => $instance_id,
				'scheduled_at' => current_time( 'mysql' ),
			),
			array( 'id' => $cache_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark occurrence as failed
	 *
	 * @since  1.1.0
	 * @param  int    $cache_id      Cache record ID.
	 * @param  string $error_message Error message.
	 * @return bool                  Success status.
	 */
	public function mark_failed( int $cache_id, string $error_message ): bool {
		return (bool) $this->wpdb->update(
			$this->table_name,
			array(
				'status'        => 'failed',
				'error_message' => $error_message,
			),
			array( 'id' => $cache_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get preview of next N occurrences
	 *
	 * @since  1.1.0
	 * @param  int $parent_id Parent campaign ID.
	 * @param  int $limit     Number of occurrences to return.
	 * @return array          Array of occurrence previews.
	 */
	public function get_preview( int $parent_id, int $limit = 5 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT occurrence_number, occurrence_start, occurrence_end, status
				 FROM {$this->table_name}
				 WHERE parent_campaign_id = %d
				 ORDER BY occurrence_number ASC
				 LIMIT %d",
				$parent_id,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get occurrence count by status
	 *
	 * @since  1.1.0
	 * @param  int    $parent_id Parent campaign ID.
	 * @param  string $status    Status to count.
	 * @return int               Count of occurrences.
	 */
	public function count_by_status( int $parent_id, string $status ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				 WHERE parent_campaign_id = %d
				 AND status = %s",
				$parent_id,
				$status
			)
		);
	}

	/**
	 * Delete all occurrences for parent campaign
	 *
	 * @since  1.1.0
	 * @param  int $parent_id Parent campaign ID.
	 * @return int            Number of deleted records.
	 */
	public function delete_by_parent( int $parent_id ): int {
		return (int) $this->wpdb->delete(
			$this->table_name,
			array( 'parent_campaign_id' => $parent_id ),
			array( '%d' )
		);
	}

	/**
	 * Get next occurrence number for parent
	 *
	 * @since  1.1.0
	 * @param  int $parent_id Parent campaign ID.
	 * @return int            Next occurrence number.
	 */
	private function get_next_occurrence_number( int $parent_id ): int {
		$max = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MAX(occurrence_number) FROM {$this->table_name}
				 WHERE parent_campaign_id = %d",
				$parent_id
			)
		);

		return $max ? (int) $max + 1 : 1;
	}
}
