<?php
/**
 * Occurrence Preview Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-occurrence-preview-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.1.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Occurrence Preview Handler
 *
 * Handles AJAX requests for previewing recurring campaign occurrences.
 *
 * @since      1.1.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Occurrence_Preview_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Occurrence cache instance
	 *
	 * @var WSSCD_Occurrence_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @since    1.1.0
	 * @param    WSSCD_Occurrence_Cache $cache   Occurrence cache instance.
	 * @param    WSSCD_Logger           $logger  Logger instance (optional).
	 */
	public function __construct( $cache, $logger = null ) {
		parent::__construct( $logger );
		$this->cache = $cache;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.1.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_occurrence_preview';
	}

	/**
	 * Handle occurrence preview request.
	 *
	 * @since    1.1.0
	 * @param    array $request    Request data.
	 * @return   array             Preview response.
	 */
	protected function handle( $request ) {
		// Get request data
		$recurring = isset( $request['recurring'] ) ? $request['recurring'] : array();
		$schedule  = isset( $request['schedule'] ) ? $request['schedule'] : array();
		$limit     = isset( $request['limit'] ) ? (int) $request['limit'] : 5;

		// Validate required fields
		if ( empty( $schedule['start_date'] ) || empty( $schedule['end_date'] ) ) {
			return array(
				'success'     => false,
				'occurrences' => array(),
				'message'     => 'Start date and end date are required',
			);
		}

		if ( empty( $recurring['recurrence_pattern'] ) ) {
			return array(
				'success'     => false,
				'occurrences' => array(),
				'message'     => 'Recurrence pattern is required',
			);
		}

		// Calculate preview occurrences
		$occurrences = $this->calculate_preview_occurrences( $recurring, $schedule, $limit );

		// Format occurrences for display
		$formatted = array_map( array( $this, 'format_occurrence' ), $occurrences );

		return array(
			'success'     => true,
			'occurrences' => $formatted,
			'total'       => count( $formatted ),
			'pattern'     => $this->get_pattern_description( $recurring ),
		);
	}

	/**
	 * Calculate preview occurrences
	 *
	 * @since  1.1.0
	 * @param  array $recurring Recurring settings.
	 * @param  array $schedule  Schedule settings.
	 * @param  int   $limit     Number of occurrences to return.
	 * @return array            Array of occurrence dates.
	 */
	private function calculate_preview_occurrences( array $recurring, array $schedule, int $limit ): array {
		$occurrences = array();
		$pattern     = $recurring['recurrence_pattern'] ?? 'daily';
		$interval    = isset( $recurring['recurrence_interval'] ) ? (int) $recurring['recurrence_interval'] : 1;

		// Calculate campaign duration
		$start_time = $schedule['start_time'] ?? '00:00';
		$end_time   = $schedule['end_time'] ?? '23:59';

		try {
			$start    = new DateTime( $schedule['start_date'] . ' ' . $start_time, new DateTimeZone( wp_timezone_string() ) );
			$end      = new DateTime( $schedule['end_date'] . ' ' . $end_time, new DateTimeZone( wp_timezone_string() ) );
			$duration = $start->diff( $end );

			// Start from end of current campaign
			$next_start = clone $end;

			// Generate occurrences
			$count = 0;
			while ( $count < $limit ) {
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
						return array(); // Invalid pattern
				}

				// Check end conditions
				if ( ! $this->should_create_occurrence( $recurring, $next_start, $count + 1 ) ) {
					break;
				}

				// Calculate end date (same duration as parent)
				$next_end = clone $next_start;
				$next_end->add( $duration );

				$occurrences[] = array(
					'number' => $count + 1,
					'start'  => $next_start->format( 'Y-m-d H:i:s' ),
					'end'    => $next_end->format( 'Y-m-d H:i:s' ),
				);

				++$count;
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate preview occurrences',
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
	 * Format occurrence for display
	 *
	 * @since  1.1.0
	 * @param  array $occurrence Occurrence data.
	 * @return array             Formatted occurrence.
	 */
	private function format_occurrence( array $occurrence ): array {
		try {
			$start = new DateTime( $occurrence['start'], new DateTimeZone( wp_timezone_string() ) );
			$end   = new DateTime( $occurrence['end'], new DateTimeZone( wp_timezone_string() ) );

			return array(
				'number'             => $occurrence['number'],
				'start_date'         => $start->format( 'Y-m-d' ),
				'start_time'         => $start->format( 'H:i' ),
				'end_date'           => $end->format( 'Y-m-d' ),
				'end_time'           => $end->format( 'H:i' ),
				'formatted_start'    => $start->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'formatted_end'      => $end->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'formatted_duration' => $this->format_duration( $start, $end ),
			);
		} catch ( Exception $e ) {
			return $occurrence;
		}
	}

	/**
	 * Format duration between two dates
	 *
	 * @since  1.1.0
	 * @param  DateTime $start Start date.
	 * @param  DateTime $end   End date.
	 * @return string          Formatted duration.
	 */
	private function format_duration( DateTime $start, DateTime $end ): string {
		$interval = $start->diff( $end );

		$parts = array();

		if ( $interval->d > 0 ) {
			/* translators: %d: number of days */
			$parts[] = sprintf( _n( '%d day', '%d days', $interval->d, 'smart-cycle-discounts' ), $interval->d );
		}

		if ( $interval->h > 0 ) {
			/* translators: %d: number of hours */
			$parts[] = sprintf( _n( '%d hour', '%d hours', $interval->h, 'smart-cycle-discounts' ), $interval->h );
		}

		if ( $interval->i > 0 ) {
			/* translators: %d: number of minutes */
			$parts[] = sprintf( _n( '%d minute', '%d minutes', $interval->i, 'smart-cycle-discounts' ), $interval->i );
		}

		return ! empty( $parts ) ? implode( ', ', $parts ) : '0 minutes';
	}

	/**
	 * Get pattern description
	 *
	 * @since  1.1.0
	 * @param  array $recurring Recurring settings.
	 * @return string           Pattern description.
	 */
	private function get_pattern_description( array $recurring ): string {
		$pattern  = $recurring['recurrence_pattern'] ?? 'daily';
		$interval = isset( $recurring['recurrence_interval'] ) ? (int) $recurring['recurrence_interval'] : 1;

		switch ( $pattern ) {
			case 'daily':
				if ( 1 === $interval ) {
					return __( 'Daily', 'smart-cycle-discounts' );
				}
				/* translators: %d: number of days */
				return sprintf( __( 'Every %d days', 'smart-cycle-discounts' ), $interval );

			case 'weekly':
				if ( 1 === $interval ) {
					return __( 'Weekly', 'smart-cycle-discounts' );
				}
				/* translators: %d: number of weeks */
				return sprintf( __( 'Every %d weeks', 'smart-cycle-discounts' ), $interval );

			case 'monthly':
				if ( 1 === $interval ) {
					return __( 'Monthly', 'smart-cycle-discounts' );
				}
				/* translators: %d: number of months */
				return sprintf( __( 'Every %d months', 'smart-cycle-discounts' ), $interval );

			default:
				return __( 'Unknown pattern', 'smart-cycle-discounts' );
		}
	}
}
