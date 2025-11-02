<?php
/**
 * Weekly Campaign Definitions
 *
 * Defines recurring weekly campaign templates that appear every week.
 * Separated from seasonal events for clarity.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly Campaign Definitions Class
 *
 * @since 1.0.0
 */
class SCD_Weekly_Campaign_Definitions {

	/**
	 * Get all weekly campaign definitions.
	 *
	 * @since  1.0.0
	 * @return array Array of weekly campaign definitions.
	 */
	public static function get_definitions(): array {
		return array(
			// Campaign 1: Early Week.
			array(
				'id'                 => 'weekly_early_boost',
				'name'               => __( 'Fresh Start Monday', 'smart-cycle-discounts' ),
				'icon'               => '🌅',
				'category'           => 'recurring_weekly',
				'position'           => 'first',
				'priority'           => 10, // Medium priority (major events = 100).
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 1, // Monday (1-7, where 1=Monday).
					'start_time' => '00:00',
					'end_day'    => 2, // Tuesday.
					'end_time'   => '23:59',
				),
				'suggested_discount' => array(
					'min'     => 10,
					'max'     => 15,
					'optimal' => 12,
				),
				'description'        => __( 'Start the week strong - capture motivated Monday shoppers', 'smart-cycle-discounts' ),
				'psychology'         => __( 'New week optimism - goal-oriented purchases', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'New product launches', 'smart-cycle-discounts' ),
					__( 'Self-improvement items', 'smart-cycle-discounts' ),
					__( 'Business/professional products', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'conversion_lift' => __( '18% higher conversion vs. other weekdays', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Monday 9-11AM (start-of-week planning)', 'smart-cycle-discounts' ),
					'avg_order'       => __( '$75 average order value', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature "New This Week" product collections', 'smart-cycle-discounts' ),
					__( 'Target goal-oriented messaging ("Start Strong")', 'smart-cycle-discounts' ),
					__( 'Launch new products on Monday for maximum visibility', 'smart-cycle-discounts' ),
					__( 'Send campaign email Monday 8AM', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 0, // Can create day-of.
			),

			// Campaign 2: Mid-Week.
			array(
				'id'                 => 'weekly_midweek_push',
				'name'               => __( 'Wednesday Wins', 'smart-cycle-discounts' ),
				'icon'               => '⚡',
				'category'           => 'recurring_weekly',
				'position'           => 'middle',
				'priority'           => 10,
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 3, // Wednesday.
					'start_time' => '00:00',
					'end_day'    => 5, // Friday.
					'end_time'   => '17:00', // Ends before weekend campaign.
				),
				'suggested_discount' => array(
					'min'     => 12,
					'max'     => 18,
					'optimal' => 15,
				),
				'description'        => __( 'Beat the hump day - mid-week rewards', 'smart-cycle-discounts' ),
				'psychology'         => __( '"Treat yourself" mentality - deserve a reward', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'Impulse purchases', 'smart-cycle-discounts' ),
					__( 'Small luxuries', 'smart-cycle-discounts' ),
					__( 'Pick-me-up items', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'conversion_lift' => __( '23% higher conversion than Mon-Tue', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Wednesday 12-2PM (lunch browsing)', 'smart-cycle-discounts' ),
					'impulse_rate'    => __( '35% of purchases are impulse buys', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature affordable "treat yourself" items ($20-50)', 'smart-cycle-discounts' ),
					__( 'Use mid-week reward messaging', 'smart-cycle-discounts' ),
					__( 'Post to social media Wednesday morning', 'smart-cycle-discounts' ),
					__( 'Highlight fast shipping for instant gratification', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 0,
			),

			// Campaign 3: Weekend.
			array(
				'id'                 => 'weekly_weekend_flash',
				'name'               => __( 'Weekend Flash Sale', 'smart-cycle-discounts' ),
				'icon'               => '🎉',
				'category'           => 'recurring_weekly',
				'position'           => 'last',
				'priority'           => 10,
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 5, // Friday.
					'start_time' => '18:00', // Evening start.
					'end_day'    => 7, // Sunday.
					'end_time'   => '23:59',
				),
				'suggested_discount' => array(
					'min'     => 15,
					'max'     => 25,
					'optimal' => 20,
				),
				'description'        => __( 'Weekend shoppers ready to spend - highest traffic days', 'smart-cycle-discounts' ),
				'psychology'         => __( 'Free time + browsing mindset = higher conversions', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'Bigger ticket items (more time to decide)', 'smart-cycle-discounts' ),
					__( 'Family/household products', 'smart-cycle-discounts' ),
					__( 'Entertainment/leisure items', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'traffic_lift'   => __( '40% more traffic than weekdays', 'smart-cycle-discounts' ),
					'session_length' => __( '2.3x longer browsing sessions', 'smart-cycle-discounts' ),
					'peak_time'      => __( 'Saturday 10AM-2PM', 'smart-cycle-discounts' ),
					'mobile_share'   => __( '62% of purchases from mobile', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature family bundles and household items', 'smart-cycle-discounts' ),
					__( 'Ensure mobile checkout is optimized', 'smart-cycle-discounts' ),
					__( 'Add countdown timer "Ends Sunday 11:59PM"', 'smart-cycle-discounts' ),
					__( 'Stock 1.5x normal inventory levels', 'smart-cycle-discounts' ),
					__( 'Promote Thursday evening on social media', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 1, // Prepare Thursday/Friday.
			),
		);
	}

	/**
	 * Get single weekly campaign by ID.
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id Campaign ID.
	 * @return array|null Campaign definition or null if not found.
	 */
	public static function get_by_id( string $campaign_id ): ?array {
		$campaigns = self::get_definitions();

		foreach ( $campaigns as $campaign ) {
			if ( $campaign['id'] === $campaign_id ) {
				return $campaign;
			}
		}

		return null;
	}
}
