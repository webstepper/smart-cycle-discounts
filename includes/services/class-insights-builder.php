<?php
/**
 * Insights Builder Class
 *
 * Unified builder for campaign insights. Replaces duplicate build_* methods
 * with single polymorphic implementation using WSSCD_Campaign_Data interface.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insights Builder Class.
 *
 * Builds 3-column insights layout from any WSSCD_Campaign_Data source.
 * Provides consistent insights structure for both major events and weekly campaigns.
 *
 * @since 1.3.0
 */
class WSSCD_Insights_Builder {

	/**
	 * Number of items to display per column.
	 *
	 * @since 1.3.0
	 * @var   int
	 */
	const ITEMS_PER_COLUMN = 3;

	/**
	 * Build complete insights data structure.
	 *
	 * @since  1.3.0
	 * @param  WSSCD_Campaign_Data $campaign Campaign data source.
	 * @param  string              $position Timeline position (past/active/future).
	 * @param  string              $state    Campaign state (past/active/future).
	 * @return array Insights data with title, icon, and tabs.
	 */
	public function build( WSSCD_Campaign_Data $campaign, string $position, string $state ): array {
		return array(
			'title' => $campaign->get_name(),
			'icon'  => $campaign->get_icon(),
			'tabs'  => array(
				$this->build_column(
					'opportunity',
					__( 'Opportunity', 'smart-cycle-discounts' ),
					'trending-up',
					$campaign->get_opportunity_content()
				),
				$this->build_column(
					'strategy',
					__( 'Strategy', 'smart-cycle-discounts' ),
					'admin-tools',
					$campaign->get_strategy_content(),
					$this->build_cta( $campaign, $position, $state )
				),
				$this->build_column(
					'timeline',
					__( 'Timeline', 'smart-cycle-discounts' ),
					'calendar',
					$campaign->get_timeline_content()
				),
			),
		);
	}

	/**
	 * Build fallback insights when no campaign data found.
	 *
	 * @since  1.3.0
	 * @param  string $position Timeline position (past/active/future).
	 * @return array Insights data with generic content.
	 */
	public function build_fallback( string $position ): array {
		$titles = array(
			'past'   => __( 'Campaign Results', 'smart-cycle-discounts' ),
			'active' => __( 'Ready to Launch', 'smart-cycle-discounts' ),
			'future' => __( 'Planning Ahead', 'smart-cycle-discounts' ),
		);

		$icons = array(
			'past'   => 'chart-line',
			'active' => 'star-filled',
			'future' => 'calendar',
		);

		return array(
			'title' => $titles[ $position ] ?? $titles['future'],
			'icon'  => $icons[ $position ] ?? 'calendar',
			'tabs'  => array(
				array(
					'id'      => 'opportunity',
					'label'   => __( 'Opportunity', 'smart-cycle-discounts' ),
					'icon'    => 'trending-up',
					'content' => array(
						array(
							'type' => 'info',
							'icon' => 'calendar',
							'text' => __( 'Create a targeted campaign for this sales opportunity', 'smart-cycle-discounts' ),
						),
						array(
							'type' => 'info',
							'icon' => 'chart-line',
							'text' => __( 'Plan ahead to maximize your campaign impact', 'smart-cycle-discounts' ),
						),
						array(
							'type' => 'info',
							'icon' => 'users',
							'text' => __( 'Identify your target audience for better conversions', 'smart-cycle-discounts' ),
						),
					),
				),
				array(
					'id'      => 'strategy',
					'label'   => __( 'Strategy', 'smart-cycle-discounts' ),
					'icon'    => 'admin-tools',
					'content' => array(
						array(
							'type' => 'info',
							'icon' => 'tag',
							'text' => __( 'Consider 10-20% discounts for optimal conversions', 'smart-cycle-discounts' ),
						),
						array(
							'type' => 'info',
							'icon' => 'yes',
							'text' => __( 'Bundle complementary products for higher order value', 'smart-cycle-discounts' ),
						),
						array(
							'type' => 'info',
							'icon' => 'percent',
							'text' => __( 'Test different discount levels to find your sweet spot', 'smart-cycle-discounts' ),
						),
					),
				),
				array(
					'id'      => 'timeline',
					'label'   => __( 'Timeline', 'smart-cycle-discounts' ),
					'icon'    => 'calendar',
					'content' => array(
						array(
							'type' => 'info',
							'icon' => 'clock',
							'text' => __( 'Start preparing your campaign early for best results', 'smart-cycle-discounts' ),
						),
						array(
							'type' => 'info',
							'icon' => 'lightbulb',
							'text' => __( 'Use countdown timers to create urgency', 'smart-cycle-discounts' ),
						),
						array(
							'type' => 'info',
							'icon' => 'flag',
							'text' => __( 'Set clear start and end dates for your promotion', 'smart-cycle-discounts' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Build a single column.
	 *
	 * @since  1.3.0
	 * @param  string     $id           Column ID.
	 * @param  string     $label        Column label.
	 * @param  string     $icon         Column header icon.
	 * @param  array      $content_pool Content items with weights.
	 * @param  array|null $cta          Optional CTA button data.
	 * @return array Column data structure.
	 */
	private function build_column( string $id, string $label, string $icon, array $content_pool, ?array $cta = null ): array {
		$content = $this->weighted_random_select( $content_pool, self::ITEMS_PER_COLUMN );

		// Ensure content items have 'type' => 'info'.
		$content = array_map(
			function ( $item ) {
				if ( ! isset( $item['type'] ) ) {
					$item['type'] = 'info';
				}
				return $item;
			},
			$content
		);

		if ( $cta ) {
			$content[] = $cta;
		}

		return array(
			'id'      => $id,
			'label'   => $label,
			'icon'    => $icon,
			'content' => $content,
		);
	}

	/**
	 * Build position-aware CTA button.
	 *
	 * @since  1.3.0
	 * @param  WSSCD_Campaign_Data $campaign Campaign data.
	 * @param  string              $position Timeline position.
	 * @param  string              $state    Campaign state.
	 * @return array|null CTA data structure or null if no CTA.
	 */
	private function build_cta( WSSCD_Campaign_Data $campaign, string $position, string $state ): ?array {
		// Past position - no CTA (card already has "Plan Next" link).
		if ( 'past' === $position ) {
			return null;
		}

		$wizard_url  = admin_url( 'admin.php?page=wsscd-campaign-wizard' );
		$campaign_id = $campaign->get_id();
		$is_major    = $campaign->is_major_event();

		// Active state - manage campaign.
		if ( 'active' === $state ) {
			return array(
				'type' => 'cta',
				'text' => __( 'Manage Campaign', 'smart-cycle-discounts' ),
				'url'  => add_query_arg(
					array(
						'action'   => 'edit',
						'campaign' => $campaign_id,
					),
					$wizard_url
				),
			);
		}

		// Future position - create new campaign.
		$url_args = array( 'suggestion' => $campaign_id );
		if ( $is_major ) {
			$url_args['type'] = 'event';
		}

		return array(
			'type' => 'cta',
			'text' => sprintf(
				/* translators: %s: campaign name */
				__( 'Create %s Campaign', 'smart-cycle-discounts' ),
				$campaign->get_name()
			),
			'url'  => add_query_arg( $url_args, $wizard_url ),
		);
	}

	/**
	 * Select random items from pool using weighted probability.
	 *
	 * Higher weight = higher probability of selection.
	 *
	 * @since  1.3.0
	 * @param  array $pool  Items with 'weight' property.
	 * @param  int   $count Number of items to select.
	 * @return array Selected items (weight removed).
	 */
	private function weighted_random_select( array $pool, int $count ): array {
		if ( empty( $pool ) ) {
			return array();
		}

		// Return all if pool is smaller than requested.
		if ( count( $pool ) <= $count ) {
			return array_map(
				function ( $item ) {
					unset( $item['weight'] );
					return $item;
				},
				$pool
			);
		}

		$selected  = array();
		$remaining = $pool;

		for ( $i = 0; $i < $count && ! empty( $remaining ); $i++ ) {
			$total_weight = array_sum( array_column( $remaining, 'weight' ) );

			// Handle edge case where all weights are 0.
			if ( 0 === $total_weight ) {
				$selected_index = array_rand( $remaining );
			} else {
				$random         = wp_rand( 0, $total_weight );
				$cumulative     = 0;
				$selected_index = 0;

				foreach ( $remaining as $index => $item ) {
					$cumulative += ( $item['weight'] ?? 1 );
					if ( $random <= $cumulative ) {
						$selected_index = $index;
						break;
					}
				}
			}

			$item = $remaining[ $selected_index ];
			unset( $item['weight'] );
			$selected[] = $item;

			array_splice( $remaining, $selected_index, 1 );
			$remaining = array_values( $remaining );
		}

		return $selected;
	}
}
