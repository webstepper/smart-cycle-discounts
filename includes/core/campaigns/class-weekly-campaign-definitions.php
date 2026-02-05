<?php
/**
 * Weekly Campaign Definitions Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-weekly-campaign-definitions.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
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
class WSSCD_Weekly_Campaign_Definitions {

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
				'icon'               => 'sunrise',
				'category'           => 'recurring_weekly',
				'position'           => 'first',
				'priority'           => 10, // Medium priority (major events = 100).
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 1, // Monday (1-7, where 1=Monday).
					'start_time' => '00:00',
					'end_day'    => 1, // Monday (24-hour campaign).
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
					__( 'New product launches and introductions', 'smart-cycle-discounts' ),
					__( 'Goal-oriented and self-improvement items', 'smart-cycle-discounts' ),
					__( 'Business and professional products', 'smart-cycle-discounts' ),
					__( 'Productivity and organization tools', 'smart-cycle-discounts' ),
					__( 'Educational and skill-building products', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'conversion_lift' => __( '18% higher conversion vs. other weekdays', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Monday 9-11AM (start-of-week planning)', 'smart-cycle-discounts' ),
					'avg_order'       => __( '$75 average order value', 'smart-cycle-discounts' ),
					'engagement'      => __( '22% higher email open rates on Monday', 'smart-cycle-discounts' ),
					'basket_size'     => __( '15% larger cart size vs weekend', 'smart-cycle-discounts' ),
					'motivation'      => __( 'Fresh start mindset drives 28% more intent', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature "New This Week" product collections prominently', 'smart-cycle-discounts' ),
					__( 'Use goal-oriented messaging ("Start Strong", "Begin Fresh")', 'smart-cycle-discounts' ),
					__( 'Launch new products on Monday for maximum visibility', 'smart-cycle-discounts' ),
					__( 'Schedule campaign to auto-activate Monday 8AM every week [PRO]', 'smart-cycle-discounts' ),
					__( 'Use product rotation to showcase new launches weekly [PRO]', 'smart-cycle-discounts' ),
					__( 'Send Monday morning motivation emails to drive early engagement [PRO]', 'smart-cycle-discounts' ),
					__( 'Use advanced filters to segment by business and productivity categories [PRO]', 'smart-cycle-discounts' ),
					__( 'Create "Monday Motivation" themed bundles', 'smart-cycle-discounts' ),
					__( 'Optimize homepage for fresh start psychology', 'smart-cycle-discounts' ),
					__( 'Schedule social media posts 7-9AM Monday', 'smart-cycle-discounts' ),
					__( 'Feature productivity-focused products first', 'smart-cycle-discounts' ),
					__( 'Track Monday launch performance trends with detailed analytics [PRO]', 'smart-cycle-discounts' ),
					__( 'Use countdown timers ending Monday midnight', 'smart-cycle-discounts' ),
					__( 'Target "new week, new goals" search keywords', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 0, // Can create day-of.
			),

			// Campaign 2: Mid-Week.
			array(
				'id'                 => 'weekly_midweek_push',
				'name'               => __( 'Wednesday Wins', 'smart-cycle-discounts' ),
				'icon'               => 'trophy',
				'category'           => 'recurring_weekly',
				'position'           => 'middle',
				'priority'           => 10,
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 3, // Wednesday.
					'start_time' => '00:00',
					'end_day'    => 4, // Thursday (48-hour mid-week campaign).
					'end_time'   => '23:59',
				),
				'suggested_discount' => array(
					'min'     => 12,
					'max'     => 18,
					'optimal' => 15,
				),
				'description'        => __( 'Beat the hump day - mid-week rewards', 'smart-cycle-discounts' ),
				'psychology'         => __( '"Treat yourself" mentality - deserve a reward', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'Impulse purchases and spontaneous buys', 'smart-cycle-discounts' ),
					__( 'Small luxuries and personal treats', 'smart-cycle-discounts' ),
					__( 'Pick-me-up and mood-boosting items', 'smart-cycle-discounts' ),
					__( 'Affordable self-reward products', 'smart-cycle-discounts' ),
					__( 'Entertainment and leisure items', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'conversion_lift' => __( '23% higher conversion than Mon-Tue', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Wednesday 12-2PM (lunch browsing)', 'smart-cycle-discounts' ),
					'impulse_rate'    => __( '35% of purchases are impulse buys', 'smart-cycle-discounts' ),
					'mobile_traffic'  => __( '58% shop via mobile during lunch hours', 'smart-cycle-discounts' ),
					'basket_value'    => __( '$45 average order (lower, more frequent)', 'smart-cycle-discounts' ),
					'engagement'      => __( '27% higher social media engagement Wed', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature affordable treats and rewards ($20-50)', 'smart-cycle-discounts' ),
					__( 'Use "Hump Day Reward" and "Treat Yourself" messaging', 'smart-cycle-discounts' ),
					__( 'Post to social media Wednesday 11AM-1PM', 'smart-cycle-discounts' ),
					__( 'Set max uses per customer to spread deals across more shoppers', 'smart-cycle-discounts' ),
					__( 'Optimize for mobile checkout (58% traffic)', 'smart-cycle-discounts' ),
					__( 'Schedule campaign to auto-activate Wednesday 10AM every week [PRO]', 'smart-cycle-discounts' ),
					__( 'Use product rotation to keep impulse offers fresh throughout campaign [PRO]', 'smart-cycle-discounts' ),
					__( 'Set spend threshold discounts to encourage larger treat baskets [PRO]', 'smart-cycle-discounts' ),
					__( 'Send lunch-time browsing alerts (Wed 11AM-1PM) [PRO]', 'smart-cycle-discounts' ),
					__( 'Use advanced filters to segment by price range ($20-50 sweet spot) [PRO]', 'smart-cycle-discounts' ),
					__( 'Create impulse-friendly product displays', 'smart-cycle-discounts' ),
					__( 'Feature "Mid-Week Pick-Me-Up" collections', 'smart-cycle-discounts' ),
					__( 'Track mid-week impulse buying patterns with detailed analytics [PRO]', 'smart-cycle-discounts' ),
					__( 'Use countdown ending Thursday 11:59PM', 'smart-cycle-discounts' ),
					__( 'Promote stress-relief and relaxation products', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 0,
			),

			// Campaign 3: Weekend.
			array(
				'id'                 => 'weekly_weekend_flash',
				'name'               => __( 'Weekend Flash Sale', 'smart-cycle-discounts' ),
				'icon'               => 'bolt',
				'category'           => 'recurring_weekly',
				'position'           => 'last',
				'priority'           => 10,
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 5, // Friday.
					'start_time' => '19:00', // Evening start (optimized for post-commute browsing).
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
					__( 'Higher-value items (shoppers have time to decide)', 'smart-cycle-discounts' ),
					__( 'Family and household bundles', 'smart-cycle-discounts' ),
					__( 'Entertainment and leisure products', 'smart-cycle-discounts' ),
					__( 'Considered purchases requiring research', 'smart-cycle-discounts' ),
					__( 'Gift items and special occasion products', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'traffic_lift'   => __( '40% more traffic than weekdays', 'smart-cycle-discounts' ),
					'session_length' => __( '2.3x longer browsing sessions', 'smart-cycle-discounts' ),
					'peak_time'      => __( 'Saturday 10AM-2PM (peak browsing)', 'smart-cycle-discounts' ),
					'mobile_share'   => __( '62% of purchases from mobile devices', 'smart-cycle-discounts' ),
					'basket_size'    => __( '$95 average order (higher than weekday)', 'smart-cycle-discounts' ),
					'conversion'     => __( '18% higher conversion rate than weekdays', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature family bundles and considered purchases', 'smart-cycle-discounts' ),
					__( 'Optimize mobile checkout experience (62% traffic)', 'smart-cycle-discounts' ),
					__( 'Add prominent countdown timer "Ends Sunday 11:59PM"', 'smart-cycle-discounts' ),
					__( 'Use smart product selection to feature high-value items', 'smart-cycle-discounts' ),
					__( 'Begin promotion Thursday evening on social media', 'smart-cycle-discounts' ),
					__( 'Schedule campaign to auto-activate Friday 7PM every week [PRO]', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts to reward larger weekend purchases [PRO]', 'smart-cycle-discounts' ),
					__( 'Set up BOGO discounts for family bundle deals [PRO]', 'smart-cycle-discounts' ),
					__( 'Use product rotation to keep weekend offers fresh across 3 days [PRO]', 'smart-cycle-discounts' ),
					__( 'Use advanced filters to segment family-friendly products [PRO]', 'smart-cycle-discounts' ),
					__( 'Send automated email notifications Friday morning for weekend deals [PRO]', 'smart-cycle-discounts' ),
					__( 'Create "Weekend Only" exclusive product collections', 'smart-cycle-discounts' ),
					__( 'Feature higher-value items prominently', 'smart-cycle-discounts' ),
					__( 'Use urgency messaging ("This Weekend Only")', 'smart-cycle-discounts' ),
					__( 'Optimize for longer browsing sessions with details', 'smart-cycle-discounts' ),
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
