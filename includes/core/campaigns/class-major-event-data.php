<?php
/**
 * Major Event Data Adapter
 *
 * Adapts Campaign Suggestions Registry data to WSSCD_Campaign_Data interface.
 * Enables unified insights building for seasonal events like Valentine's Day, Black Friday.
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
 * Major Event Data Adapter Class.
 *
 * Wraps raw event array from WSSCD_Campaign_Suggestions_Registry and provides
 * unified access through WSSCD_Campaign_Data interface.
 *
 * @since 1.3.0
 */
class WSSCD_Major_Event_Data implements WSSCD_Campaign_Data {

	/**
	 * Raw event data from registry.
	 *
	 * @since 1.3.0
	 * @var   array
	 */
	private $event;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @param array $event Event data from WSSCD_Campaign_Suggestions_Registry.
	 */
	public function __construct( array $event ) {
		$this->event = $event;
	}

	/**
	 * Get campaign identifier.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_id(): string {
		return $this->event['id'] ?? '';
	}

	/**
	 * Get campaign display name.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_name(): string {
		return $this->event['name'] ?? '';
	}

	/**
	 * Get campaign icon identifier.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_icon(): string {
		return $this->event['icon'] ?? 'calendar-alt';
	}

	/**
	 * Get campaign description.
	 *
	 * @since  1.3.0
	 * @return string
	 */
	public function get_description(): string {
		return $this->event['description'] ?? '';
	}

	/**
	 * Get suggested discount configuration.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_suggested_discount(): array {
		return $this->event['suggested_discount'] ?? array(
			'min'     => 10,
			'max'     => 20,
			'optimal' => 15,
		);
	}

	/**
	 * Get opportunity content items.
	 *
	 * Combines description and statistics into weighted content pool.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_opportunity_content(): array {
		$content = array();

		// Description always included with high weight.
		if ( ! empty( $this->event['description'] ) ) {
			$content[] = array(
				'icon'   => 'info',
				'text'   => $this->event['description'],
				'weight' => 2,
			);
		}

		// Add statistics.
		if ( ! empty( $this->event['statistics'] ) ) {
			foreach ( $this->event['statistics'] as $label => $value ) {
				$content[] = array(
					'icon'   => $this->get_stat_icon( $label ),
					'text'   => ucwords( str_replace( '_', ' ', $label ) ) . ': ' . $value,
					'weight' => 1,
				);
			}
		}

		return $content;
	}

	/**
	 * Get strategy content items.
	 *
	 * Combines discount info, recommendations, and tips.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_strategy_content(): array {
		$content  = array();
		$discount = $this->get_suggested_discount();

		// Discount recommendations.
		$content[] = array(
			'icon'   => 'percent',
			'text'   => sprintf(
				/* translators: %d: optimal discount percentage */
				__( 'Optimal Discount: %d%%', 'smart-cycle-discounts' ),
				$discount['optimal']
			),
			'weight' => 3,
		);

		$content[] = array(
			'icon'   => 'chart-bar',
			'text'   => sprintf(
				/* translators: %1$d: min discount, %2$d: max discount */
				__( 'Range: %1$d%% - %2$d%% based on industry performance', 'smart-cycle-discounts' ),
				$discount['min'],
				$discount['max']
			),
			'weight' => 2,
		);

		// Recommendations.
		if ( ! empty( $this->event['recommendations'] ) ) {
			foreach ( $this->event['recommendations'] as $rec ) {
				$content[] = array(
					'icon'   => 'check-circle',
					'text'   => $rec,
					'weight' => 2,
				);
			}
		}

		// Tips (major events only).
		if ( ! empty( $this->event['tips'] ) ) {
			foreach ( $this->event['tips'] as $tip ) {
				$content[] = array(
					'icon'   => 'lightbulb',
					'text'   => $tip,
					'weight' => 1,
				);
			}
		}

		return $content;
	}

	/**
	 * Get timeline content items.
	 *
	 * Includes event dates, duration, lead times, and best practices.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_timeline_content(): array {
		$content = array();

		// Event date.
		if ( ! empty( $this->event['event_date'] ) ) {
			$content[] = array(
				'icon'   => 'calendar',
				'text'   => sprintf(
					/* translators: %s: event date */
					__( 'Event Date: %s', 'smart-cycle-discounts' ),
					wp_date( 'F j, Y', $this->event['event_date'] )
				),
				'weight' => 3,
			);
		}

		// Campaign start date.
		if ( ! empty( $this->event['calculated_start_date'] ) ) {
			$content[] = array(
				'icon'   => 'play-circle',
				'text'   => sprintf(
					/* translators: %s: start date */
					__( 'Campaign Starts: %s', 'smart-cycle-discounts' ),
					wp_date( 'F j, Y', $this->event['calculated_start_date'] )
				),
				'weight' => 2,
			);
		}

		// Campaign end date.
		if ( ! empty( $this->event['calculated_end_date'] ) ) {
			$content[] = array(
				'icon'   => 'stop-circle',
				'text'   => sprintf(
					/* translators: %s: end date */
					__( 'Campaign Ends: %s', 'smart-cycle-discounts' ),
					wp_date( 'F j, Y', $this->event['calculated_end_date'] )
				),
				'weight' => 2,
			);
		}

		// Duration.
		if ( ! empty( $this->event['duration_days'] ) ) {
			$content[] = array(
				'icon'   => 'clock',
				'text'   => sprintf(
					/* translators: %d: number of days */
					__( 'Duration: %d days', 'smart-cycle-discounts' ),
					$this->event['duration_days']
				),
				'weight' => 1,
			);
		}

		// Lead times.
		if ( ! empty( $this->event['lead_time'] ) ) {
			$lead = $this->event['lead_time'];

			if ( ! empty( $lead['marketing'] ) ) {
				$content[] = array(
					'icon'   => 'bullhorn',
					'text'   => sprintf(
						/* translators: %d: number of weeks */
						__( 'Start marketing %d weeks before', 'smart-cycle-discounts' ),
						$lead['marketing']
					),
					'weight' => 1,
				);
			}

			if ( ! empty( $lead['inventory'] ) ) {
				$content[] = array(
					'icon'   => 'box',
					'text'   => sprintf(
						/* translators: %d: number of weeks */
						__( 'Order inventory %d weeks ahead', 'smart-cycle-discounts' ),
						$lead['inventory']
					),
					'weight' => 1,
				);
			}
		}

		// Best practices.
		if ( ! empty( $this->event['best_practices'] ) ) {
			foreach ( $this->event['best_practices'] as $practice ) {
				$content[] = array(
					'icon'   => 'star',
					'text'   => $practice,
					'weight' => 1,
				);
			}
		}

		return $content;
	}

	/**
	 * Check if this is a major event.
	 *
	 * @since  1.3.0
	 * @return bool
	 */
	public function is_major_event(): bool {
		return true;
	}

	/**
	 * Get raw event data.
	 *
	 * Provides access to original data for edge cases.
	 *
	 * @since  1.3.0
	 * @return array
	 */
	public function get_raw_data(): array {
		return $this->event;
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
			'total_spending'  => 'chart-line',
			'avg_per_person'  => 'users',
			'online_growth'   => 'trending-up',
			'peak_category'   => 'star',
			'last_minute'     => 'clock',
			'self_purchase'   => 'heart',
			'conversion_rate' => 'percent',
			'mobile_share'    => 'smartphone',
			'email_open_rate' => 'email',
		);

		return $icons[ $label ] ?? 'info';
	}
}
