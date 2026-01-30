<?php
/**
 * Weekly Campaign Data Adapter
 *
 * Adapts Weekly Campaign Definitions data to WSSCD_Campaign_Data interface.
 * Enables unified insights building for recurring campaigns like Monday Fresh Start.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly Campaign Data Adapter Class.
 *
 * Wraps raw campaign array from WSSCD_Weekly_Campaign_Definitions and provides
 * unified access through WSSCD_Campaign_Data interface.
 *
 * @since 1.3.0
 */
class WSSCD_Weekly_Campaign_Data implements WSSCD_Campaign_Data {

	/**
	 * Raw campaign data from definitions.
	 *
	 * @since 1.3.0
	 * @var   array
	 */
	private $campaign;

	/**
	 * Day names for schedule display.
	 *
	 * @since 1.3.0
	 * @var   array
	 */
	private static $day_names = array(
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
		7 => 'Sunday',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @param array $campaign Campaign data from WSSCD_Weekly_Campaign_Definitions.
	 */
	public function __construct( array $campaign ) {
		$this->campaign = $campaign;
	}

	/**
	 * Get campaign identifier.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_id(): string {
		return $this->campaign['id'] ?? '';
	}

	/**
	 * Get campaign display name.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_name(): string {
		return $this->campaign['name'] ?? '';
	}

	/**
	 * Get campaign icon identifier.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_icon(): string {
		return $this->campaign['icon'] ?? 'calendar-alt';
	}

	/**
	 * Get campaign description.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_description(): string {
		return $this->campaign['description'] ?? '';
	}

	/**
	 * Get suggested discount configuration.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_suggested_discount(): array {
		return $this->campaign['suggested_discount'] ?? array(
			'min'     => 10,
			'max'     => 20,
			'optimal' => 15,
		);
	}

	/**
	 * Get opportunity content items.
	 *
	 * Uses description, psychology, best_for, and statistics.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_opportunity_content(): array {
		$content = array();

		// Description.
		if ( ! empty( $this->campaign['description'] ) ) {
			$content[] = array(
				'icon'   => 'info',
				'text'   => $this->campaign['description'],
				'weight' => 2,
			);
		}

		// Psychology insights.
		if ( ! empty( $this->campaign['psychology'] ) ) {
			$content[] = array(
				'icon'   => 'lightbulb',
				'text'   => $this->campaign['psychology'],
				'weight' => 2,
			);
		}

		// Best for use cases.
		if ( ! empty( $this->campaign['best_for'] ) ) {
			foreach ( $this->campaign['best_for'] as $use_case ) {
				$content[] = array(
					'icon'   => 'yes-alt',
					'text'   => $use_case,
					'weight' => 1,
				);
			}
		}

		// Statistics.
		if ( ! empty( $this->campaign['statistics'] ) ) {
			foreach ( $this->campaign['statistics'] as $label => $value ) {
				$content[] = array(
					'icon'   => $this->get_stat_icon( $label ),
					'text'   => $value,
					'weight' => 1,
				);
			}
		}

		return $content;
	}

	/**
	 * Get strategy content items.
	 *
	 * Combines discount info and recommendations.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_strategy_content(): array {
		$content  = array();
		$discount = $this->get_suggested_discount();

		// Discount recommendations.
		$content[] = array(
			'icon'   => 'tag',
			'text'   => sprintf(
				/* translators: %d: optimal discount percentage */
				__( 'Recommended Discount: %d%%', 'smart-cycle-discounts' ),
				$discount['optimal']
			),
			'weight' => 3,
		);

		$content[] = array(
			'icon'   => 'chart-line',
			'text'   => sprintf(
				/* translators: %1$d: min discount, %2$d: max discount */
				__( 'Effective range: %1$d%% - %2$d%% for weekly promotions', 'smart-cycle-discounts' ),
				$discount['min'],
				$discount['max']
			),
			'weight' => 2,
		);

		// Recommendations.
		if ( ! empty( $this->campaign['recommendations'] ) ) {
			foreach ( $this->campaign['recommendations'] as $rec ) {
				$content[] = array(
					'icon'   => 'yes-alt',
					'text'   => $rec,
					'weight' => 2,
				);
			}
		}

		return $content;
	}

	/**
	 * Get timeline content items.
	 *
	 * Uses schedule information for recurring campaigns.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_timeline_content(): array {
		$content  = array();
		$schedule = $this->campaign['schedule'] ?? array();

		// Schedule days.
		if ( ! empty( $schedule['start_day'] ) ) {
			$start_day = self::$day_names[ $schedule['start_day'] ] ?? '';
			$end_day   = isset( $schedule['end_day'] ) ? ( self::$day_names[ $schedule['end_day'] ] ?? '' ) : $start_day;

			if ( $start_day === $end_day ) {
				$content[] = array(
					'icon'   => 'calendar',
					'text'   => sprintf(
						/* translators: %s: day of week */
						__( 'Runs every %s', 'smart-cycle-discounts' ),
						$start_day
					),
					'weight' => 3,
				);
			} else {
				$content[] = array(
					'icon'   => 'calendar',
					'text'   => sprintf(
						/* translators: %1$s: start day, %2$s: end day */
						__( 'Runs %1$s to %2$s', 'smart-cycle-discounts' ),
						$start_day,
						$end_day
					),
					'weight' => 3,
				);
			}
		}

		// Start time.
		if ( ! empty( $schedule['start_time'] ) ) {
			$content[] = array(
				'icon'   => 'clock',
				'text'   => sprintf(
					/* translators: %s: time */
					__( 'Starts at %s', 'smart-cycle-discounts' ),
					$this->format_time( $schedule['start_time'] )
				),
				'weight' => 2,
			);
		}

		// End time.
		if ( ! empty( $schedule['end_time'] ) ) {
			$content[] = array(
				'icon'   => 'backup',
				'text'   => sprintf(
					/* translators: %s: time */
					__( 'Ends at %s', 'smart-cycle-discounts' ),
					$this->format_time( $schedule['end_time'] )
				),
				'weight' => 2,
			);
		}

		// Peak time from statistics.
		if ( ! empty( $this->campaign['statistics']['peak_time'] ) ) {
			$content[] = array(
				'icon'   => 'star',
				'text'   => $this->campaign['statistics']['peak_time'],
				'weight' => 2,
			);
		}

		// Prep time.
		$prep_time = $this->campaign['prep_time'] ?? 0;
		if ( 0 === $prep_time ) {
			$content[] = array(
				'icon'   => 'yes',
				'text'   => __( 'Quick setup - can create same day', 'smart-cycle-discounts' ),
				'weight' => 1,
			);
		} else {
			$content[] = array(
				'icon'   => 'edit',
				'text'   => sprintf(
					/* translators: %d: number of days */
					_n(
						'Prepare %d day ahead',
						'Prepare %d days ahead',
						$prep_time,
						'smart-cycle-discounts'
					),
					$prep_time
				),
				'weight' => 1,
			);
		}

		// Recurring nature.
		$content[] = array(
			'icon'   => 'update',
			'text'   => __( 'Repeats weekly - set it and forget it', 'smart-cycle-discounts' ),
			'weight' => 1,
		);

		return $content;
	}

	/**
	 * Check if this is a major event.
	 *
	 * @since  1.3.0
	 * @return bool
	 */
	public function is_major_event(): bool {
		return false;
	}

	/**
	 * Get raw campaign data.
	 *
	 * Provides access to original data for edge cases.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_raw_data(): array {
		return $this->campaign;
	}

	/**
	 * Get appropriate icon for a statistic.
	 *
	 * @since  1.3.0
	 * @param  string $label Statistic label (snake_case).
	 * @return string Icon name.
	 */
	private function get_stat_icon( string $label ): string {
		$icons = array(
			'conversion_lift' => 'trending-up',
			'traffic_lift'    => 'chart-line',
			'peak_time'       => 'clock',
			'avg_order'       => 'cart',
			'basket_size'     => 'cart',
			'basket_value'    => 'cart',
			'engagement'      => 'heart',
			'mobile_traffic'  => 'smartphone',
			'mobile_share'    => 'smartphone',
			'session_length'  => 'clock',
			'impulse_rate'    => 'bolt',
			'conversion'      => 'percent',
			'motivation'      => 'star',
		);

		return $icons[ $label ] ?? 'info';
	}

	/**
	 * Format 24-hour time to readable format.
	 *
	 * @since  1.3.0
	 * @param  string $time Time in HH:MM format.
	 * @return string Formatted time.
	 */
	private function format_time( string $time ): string {
		$timestamp = strtotime( $time );
		if ( false === $timestamp ) {
			return $time;
		}
		return wp_date( get_option( 'time_format', 'g:i A' ), $timestamp );
	}
}
