<?php
/**
 * Campaign Suggestions Registry
 *
 * Central repository for campaign suggestion event definitions.
 * Shared by Dashboard Service and other components that need event data.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Campaign Suggestions Registry Class
 *
 * @since      1.0.0
 */
class SCD_Campaign_Suggestions_Registry {

	/**
	 * Get all event definitions.
	 *
	 * @since    1.0.0
	 * @return   array    Array of event definitions.
	 */
	public static function get_event_definitions(): array {
		return array(
			array(
				'id'                 => 'valentines',
			'name'               => __( 'Valentine\047s Day', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '❤️',
			'month'              => 2,
			'day'                => 14,
			'duration_days'      => 7,
			'start_offset'       => -7,
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 9,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Check inventory levels for popular gift items', 'smart-cycle-discounts' ),
				__( 'Plan your Valentine\'s Day product selection', 'smart-cycle-discounts' ),
				__( 'Review last year\'s performance data', 'smart-cycle-discounts' ),
				__( 'Prepare gift wrapping or romantic packaging options', 'smart-cycle-discounts' ),
				__( 'Set up gift guides for different budgets', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 15,
				'max'     => 25,
				'optimal' => 20,
			),
			'description'        => __( 'Perfect time for gift promotions and special offers', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'total_spending' => __( '$25.8 billion Valentine\'s spending (2023)', 'smart-cycle-discounts' ),
				'avg_per_person' => __( '$192 average spending per person', 'smart-cycle-discounts' ),
				'peak_category'  => __( 'Jewelry: 34% of purchases', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promoting 1 week before Valentine\'s Day', 'smart-cycle-discounts' ),
				__( 'Create "Gifts for Him" and "Gifts for Her" categories', 'smart-cycle-discounts' ),
				__( 'Offer free gift wrapping or romantic packaging', 'smart-cycle-discounts' ),
				__( 'Highlight same-day or express shipping options', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Launch 7-10 days before Valentine\'s Day', 'smart-cycle-discounts' ),
				__( 'Create last-minute gift section 2 days before', 'smart-cycle-discounts' ),
				__( 'Promote express/same-day shipping prominently', 'smart-cycle-discounts' ),
				__( 'Bundle complementary gifts (flowers + chocolate)', 'smart-cycle-discounts' ),
			),
			'priority'           => 70,
		),
		array(
			'id'                 => 'easter',
			'name'               => __( 'Easter', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '🐰',
			'duration_days'      => 7,
			'start_offset'       => -7,
			'calculate_date'     => function ( $year ) {
				// Easter calculation using Computus algorithm
				// Easter is the first Sunday after the first full moon after March 21
				$a = $year % 19;
				$b = intval( $year / 100 );
				$c = $year % 100;
				$d = intval( $b / 4 );
				$e = $b % 4;
				$f = intval( ( $b + 8 ) / 25 );
				$g = intval( ( $b - $f + 1 ) / 3 );
				$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
				$i = intval( $c / 4 );
				$k = $c % 4;
				$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
				$m = intval( ( $a + 11 * $h + 22 * $l ) / 451 );
				$month = intval( ( $h + $l - 7 * $m + 114 ) / 31 );
				$day = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

				return mktime( 0, 0, 0, $month, $day, $year );
			},
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 9,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Stock spring-themed gift baskets and candy', 'smart-cycle-discounts' ),
				__( 'Feature pastel colors and spring imagery', 'smart-cycle-discounts' ),
				__( 'Create bundles for Easter egg hunts and decorations', 'smart-cycle-discounts' ),
				__( 'Offer last-minute gift wrapping services', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 15,
				'max'     => 25,
				'optimal' => 20,
			),
			'description'        => __( 'Spring celebration with family-focused shopping', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'spending'   => __( '$20 billion in Easter spending (2023)', 'smart-cycle-discounts' ),
				'avg_spend'  => __( '$192 per household average', 'smart-cycle-discounts' ),
				'categories' => __( 'Candy (90%), gifts (50%), clothing (38%)', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promoting 2 weeks before Easter Sunday', 'smart-cycle-discounts' ),
				__( 'Bundle candy with toys and gifts', 'smart-cycle-discounts' ),
				__( 'Highlight spring fashion and outdoor items', 'smart-cycle-discounts' ),
			),
			'priority'           => 65,
		),
		array(
			'id'                 => 'spring_sale',
			'name'               => __( 'Spring Sale', 'smart-cycle-discounts' ),
			'category'           => 'seasonal',
			'icon'               => '🌸',
			'month'              => 3,
			'day'                => 20,
			'duration_days'      => 14,
			'start_offset'       => 0,
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 5,
				'flexibility' => 3,
			),
			'recommendations'    => array(
				__( 'Review winter inventory for clearance opportunities', 'smart-cycle-discounts' ),
				__( 'Identify spring products to feature', 'smart-cycle-discounts' ),
				__( 'Plan seasonal category updates', 'smart-cycle-discounts' ),
				__( 'Update homepage banners with spring themes', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 20,
				'max'     => 30,
				'optimal' => 25,
			),
			'description'        => __( 'Welcome spring with fresh discounts and clearance', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'seasonal_spending' => __( '8 billion spring shopping spending', 'smart-cycle-discounts' ),
				'clearance_demand'  => __( '65% of shoppers seek spring clearance deals', 'smart-cycle-discounts' ),
				'refresh_mindset'   => __( 'Spring cleaning drives 40% purchase decisions', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Promote winter clearance alongside new spring arrivals', 'smart-cycle-discounts' ),
				__( 'Create "Spring Refresh" themed bundles', 'smart-cycle-discounts' ),
				__( 'Target outdoor and garden products', 'smart-cycle-discounts' ),
				__( 'Use fresh, bright imagery in marketing', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Start clearance early to make room for spring inventory', 'smart-cycle-discounts' ),
				__( 'Bundle winter clearance with spring must-haves', 'smart-cycle-discounts' ),
				__( 'Update site colors and themes for seasonal appeal', 'smart-cycle-discounts' ),
				__( 'Promote renewal and fresh start messaging', 'smart-cycle-discounts' ),
			),
			'priority'           => 40,
		),
		array(
			'id'                 => 'mothers_day',
			'name'               => __( 'Mother\047s Day', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '👩',
			'duration_days'      => 7,
			'start_offset'       => -7,
			'calculate_date'     => function ( $year ) {
				// Mother's Day is 2nd Sunday in May
				$first_day_of_may = mktime( 0, 0, 0, 5, 1, $year );
				$first_day_of_week = intval( gmdate( 'w', $first_day_of_may ) );

				// Calculate days until first Sunday
				$days_until_first_sunday = ( 0 === $first_day_of_week ) ? 0 : ( 7 - $first_day_of_week );

				// 2nd Sunday is first Sunday + 7 days
				$second_sunday_day = 1 + $days_until_first_sunday + 7;

				return mktime( 0, 0, 0, 5, $second_sunday_day, $year );
			},
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 9,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Check stock levels for popular Mother\'s Day gifts', 'smart-cycle-discounts' ),
				__( 'Plan gift sets and curated collections', 'smart-cycle-discounts' ),
				__( 'Review shipping deadlines for timely delivery', 'smart-cycle-discounts' ),
				__( 'Prepare personalization options if available', 'smart-cycle-discounts' ),
				__( 'Create Mother\'s Day gift guides for different budgets', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 15,
				'max'     => 25,
				'optimal' => 20,
			),
			'description'        => __( 'Help customers find perfect gifts for Mom', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'total_spending' => __( '$35.7 billion Mother\'s Day spending (2023)', 'smart-cycle-discounts' ),
				'celebrating'    => __( '84% of Americans celebrate', 'smart-cycle-discounts' ),
				'top_gifts'      => __( 'Flowers, jewelry, and gift cards', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Promote gift sets and curated collections', 'smart-cycle-discounts' ),
				__( 'Offer personalization options if possible', 'smart-cycle-discounts' ),
				__( 'Create Mother\'s Day gift guide', 'smart-cycle-discounts' ),
				__( 'Emphasize shipping deadlines for timely delivery', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Start campaign 7-10 days before Mother\'s Day', 'smart-cycle-discounts' ),
				__( 'Offer gift wrapping and personalization', 'smart-cycle-discounts' ),
				__( 'Create tiered gift guides by budget', 'smart-cycle-discounts' ),
				__( 'Promote last-minute digital gift cards', 'smart-cycle-discounts' ),
			),
			'priority'           => 70,
		),
		array(
			'id'                 => 'fathers_day',
			'name'               => __( 'Father\047s Day', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '👨',
			'duration_days'      => 7,
			'start_offset'       => -7,
			'calculate_date'     => function ( $year ) {
				// Father's Day is 3rd Sunday in June
				$first_day_of_june = mktime( 0, 0, 0, 6, 1, $year );
				$first_day_of_week = intval( gmdate( 'w', $first_day_of_june ) );

				// Calculate days until first Sunday
				$days_until_first_sunday = ( 0 === $first_day_of_week ) ? 0 : ( 7 - $first_day_of_week );

				// 3rd Sunday is first Sunday + 14 days (2 weeks)
				$third_sunday_day = 1 + $days_until_first_sunday + 14;

				return mktime( 0, 0, 0, 6, $third_sunday_day, $year );
			},
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 9,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Feature tech, tools, and outdoor gear', 'smart-cycle-discounts' ),
				__( 'Create gift bundles for different interests', 'smart-cycle-discounts' ),
				__( 'Offer gift wrapping and greeting cards', 'smart-cycle-discounts' ),
				__( 'Promote last-minute digital gift options', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 15,
				'max'     => 25,
				'optimal' => 20,
			),
			'description'        => __( 'Celebrate dads with targeted gift promotions', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'spending'  => __( '$20 billion in Father\'s Day spending (2023)', 'smart-cycle-discounts' ),
				'avg_spend' => __( '$171 per household average', 'smart-cycle-discounts' ),
				'top_gifts' => __( 'Clothing, gift cards, electronics', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promoting 2 weeks before Father\'s Day', 'smart-cycle-discounts' ),
				__( 'Create curated gift guides by interest', 'smart-cycle-discounts' ),
				__( 'Bundle complementary products (grill + accessories)', 'smart-cycle-discounts' ),
			),
			'priority'           => 70,
		),
		array(
			'id'                 => 'summer_sale',
			'name'               => __( 'Summer Sale', 'smart-cycle-discounts' ),
			'category'           => 'seasonal',
			'icon'               => '☀️',
			'month'              => 6,
			'day'                => 21,
			'duration_days'      => 21,
			'start_offset'       => 0,
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 5,
				'flexibility' => 3,
			),
			'recommendations'    => array(
				__( 'Review spring inventory for clearance opportunities', 'smart-cycle-discounts' ),
				__( 'Identify summer products to promote', 'smart-cycle-discounts' ),
				__( 'Plan seasonal promotions and bundles', 'smart-cycle-discounts' ),
				__( 'Update homepage with summer themes', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 25,
				'max'     => 35,
				'optimal' => 30,
			),
			'description'        => __( 'Kick off summer with hot deals', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'summer_spending'  => __( '20 billion summer retail spending', 'smart-cycle-discounts' ),
				'travel_impact'    => __( '60% plan summer vacations, boosting outdoor gear', 'smart-cycle-discounts' ),
				'clearance_timing' => __( 'Mid-summer clearance drives 35% more sales', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Promote outdoor, travel, and vacation products', 'smart-cycle-discounts' ),
				__( 'Create "Summer Essentials" bundles', 'smart-cycle-discounts' ),
				__( 'Offer early clearance on spring items', 'smart-cycle-discounts' ),
				__( 'Use bright, sunny imagery in promotions', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Launch sale at summer solstice for maximum impact', 'smart-cycle-discounts' ),
				__( 'Bundle seasonal items with evergreen products', 'smart-cycle-discounts' ),
				__( 'Target Memorial Day to July 4th corridor', 'smart-cycle-discounts' ),
				__( 'Promote free shipping for summer convenience', 'smart-cycle-discounts' ),
			),
			'priority'           => 40,
		),
		array(
			'id'                 => 'july_4th',
			'name'               => __( 'Independence Day Sale', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '🎆',
			'month'              => 7,
			'day'                => 4,
			'duration_days'      => 3,
			'start_offset'       => -1,
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 7,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Feature red, white, and blue themed products', 'smart-cycle-discounts' ),
				__( 'Promote outdoor and BBQ-related items', 'smart-cycle-discounts' ),
				__( 'Create party supply bundles', 'smart-cycle-discounts' ),
				__( 'Offer special patriotic gift sets', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 20,
				'max'     => 40,
				'optimal' => 30,
			),
			'description'        => __( 'Patriotic celebration with summer shopping peak', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'spending'     => __( '$7.5 billion in July 4th spending (2023)', 'smart-cycle-discounts' ),
				'celebrations' => __( '87% of Americans celebrate', 'smart-cycle-discounts' ),
				'categories'   => __( 'Food (68%), apparel (14%), decorations (12%)', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promoting 1-2 weeks before holiday', 'smart-cycle-discounts' ),
				__( 'Bundle summer and patriotic items together', 'smart-cycle-discounts' ),
				__( 'Extend sale through the full holiday weekend', 'smart-cycle-discounts' ),
			),
			'priority'           => 60,
		),
		array(
			'id'                 => 'back_to_school',
			'name'               => __( 'Back to School Sale', 'smart-cycle-discounts' ),
			'category'           => 'ongoing',
			'icon'               => '🎒',
			'month'              => 8,
			'day'                => 15,
			'duration_days'      => 21,
			'start_offset'       => 0,
			'lead_time'          => array(
				'base_prep'   => 3,
				'inventory'   => 3,
				'marketing'   => 7,
				'flexibility' => 3,
			),
			'recommendations'    => array(
				__( 'Review school and office supply inventory', 'smart-cycle-discounts' ),
				__( 'Identify products popular with students and parents', 'smart-cycle-discounts' ),
				__( 'Plan bundle deals for back-to-school needs', 'smart-cycle-discounts' ),
				__( 'Promote early-bird discounts', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 15,
				'max'     => 25,
				'optimal' => 20,
			),
			'description'        => __( 'Help students and parents gear up for the new school year', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'school_spending' => __( '7 billion back-to-school spending (2023)', 'smart-cycle-discounts' ),
				'avg_per_family'  => __( '90 average per family spending', 'smart-cycle-discounts' ),
				'peak_shopping'   => __( 'July-August: 85% of shopping occurs', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promotions in late July for early shoppers', 'smart-cycle-discounts' ),
				__( 'Create grade-specific product bundles', 'smart-cycle-discounts' ),
				__( 'Offer bulk discounts for multiple children', 'smart-cycle-discounts' ),
				__( 'Promote tax-free shopping days where applicable', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Run campaign for full 3 weeks in August', 'smart-cycle-discounts' ),
				__( 'Target parents with age-appropriate bundles', 'smart-cycle-discounts' ),
				__( 'Increase inventory 2x for popular school items', 'smart-cycle-discounts' ),
				__( 'Clear seasonal inventory before September', 'smart-cycle-discounts' ),
			),
			'priority'           => 60,
		),
		array(
			'id'                 => 'labor_day',
			'name'               => __( 'Labor Day Sale', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '💼',
			'duration_days'      => 3,
			'start_offset'       => -1,
			'calculate_date'     => function ( $year ) {
				// Labor Day is the 1st Monday in September
				$first_day_of_september = mktime( 0, 0, 0, 9, 1, $year );
				$first_day_of_week = intval( gmdate( 'w', $first_day_of_september ) );

				// Calculate days until first Monday (Monday = 1)
				$days_until_first_monday = ( 1 === $first_day_of_week ) ? 0 : ( ( 1 - $first_day_of_week + 7 ) % 7 );

				$first_monday_day = 1 + $days_until_first_monday;

				return mktime( 0, 0, 0, 9, $first_monday_day, $year );
			},
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 7,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Feature end-of-summer clearance items', 'smart-cycle-discounts' ),
				__( 'Promote furniture, appliances, and home goods', 'smart-cycle-discounts' ),
				__( 'Bundle back-to-work and fall transition items', 'smart-cycle-discounts' ),
				__( 'Offer extended weekend sale (Fri-Mon)', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 25,
				'max'     => 40,
				'optimal' => 35,
			),
			'description'        => __( 'End of summer with major shopping weekend', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'spending'   => __( '$4.6 billion in Labor Day weekend spending (2023)', 'smart-cycle-discounts' ),
				'shoppers'   => __( '42% of Americans shop Labor Day sales', 'smart-cycle-discounts' ),
				'categories' => __( 'Furniture (33%), appliances (28%), clothing (25%)', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promoting 1-2 weeks before the holiday', 'smart-cycle-discounts' ),
				__( 'Clear summer inventory with deep discounts', 'smart-cycle-discounts' ),
				__( 'Extend sale through the full 3-day weekend', 'smart-cycle-discounts' ),
			),
			'priority'           => 65,
		),
		array(
			'id'                 => 'halloween',
			'name'               => __( 'Halloween', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '🎃',
			'month'              => 10,
			'day'                => 31,
			'duration_days'      => 7,
			'start_offset'       => -7,
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 7,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Check inventory for Halloween-themed products', 'smart-cycle-discounts' ),
				__( 'Plan themed bundles and party packages', 'smart-cycle-discounts' ),
				__( 'Review last year\'s Halloween performance', 'smart-cycle-discounts' ),
				__( 'Prepare costume and decor item highlights', 'smart-cycle-discounts' ),
				__( 'Schedule clearance pricing for Nov 1st', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 20,
				'max'     => 30,
				'optimal' => 25,
			),
			'description'        => __( 'Spooky savings for Halloween shoppers', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'total_spending' => __( '$12.2 billion Halloween spending (2023)', 'smart-cycle-discounts' ),
				'participation'  => __( '73% of Americans plan to celebrate', 'smart-cycle-discounts' ),
				'peak_shopping'  => __( 'Oct 15-25: peak shopping period', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promotions 10-14 days before Halloween', 'smart-cycle-discounts' ),
				__( 'Create themed bundles and party packages', 'smart-cycle-discounts' ),
				__( 'Highlight last-minute costume and decor items', 'smart-cycle-discounts' ),
				__( 'Clear inventory with deeper discounts after Oct 25', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Launch sale 10-14 days before Halloween', 'smart-cycle-discounts' ),
				__( 'Create themed product bundles for parties', 'smart-cycle-discounts' ),
				__( 'Offer express shipping in final 3 days', 'smart-cycle-discounts' ),
				__( 'Start 50% off clearance on November 1st', 'smart-cycle-discounts' ),
			),
			'priority'           => 70,
		),
		array(
			'id'                 => 'black_friday',
			'name'               => __( 'Black Friday / Cyber Monday', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '🛍️',
			'duration_days'      => 4,
			'start_offset'       => 0,
			'calculate_date'     => function ( $year ) {
				// Black Friday is the Friday after Thanksgiving
				// Thanksgiving is the 4th Thursday in November
				$first_day_of_november = mktime( 0, 0, 0, 11, 1, $year );
				$first_day_of_week = intval( gmdate( 'w', $first_day_of_november ) );

				// Calculate days until first Thursday (Thursday = 4)
				$days_until_first_thursday = ( 4 - $first_day_of_week + 7 ) % 7;
				if ( 0 === $days_until_first_thursday && 4 !== $first_day_of_week ) {
					$days_until_first_thursday = 7;
				}

				// 4th Thursday is first Thursday + 21 days (3 weeks)
				$fourth_thursday_day = 1 + $days_until_first_thursday + 21;

				// Black Friday is the day after (Friday)
				$black_friday_day = $fourth_thursday_day + 1;

				return mktime( 0, 0, 0, 11, $black_friday_day, $year );
			},
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 7,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Order inventory NOW - suppliers get overwhelmed', 'smart-cycle-discounts' ),
				__( 'Stock 2-3x normal levels for best sellers', 'smart-cycle-discounts' ),
				__( 'Test checkout flow and payment processing capacity', 'smart-cycle-discounts' ),
				__( 'Schedule extra customer support staff', 'smart-cycle-discounts' ),
				__( 'Prepare email sequences for multi-day promotion', 'smart-cycle-discounts' ),
				__( 'Set up abandoned cart recovery for high traffic', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 30,
				'max'     => 50,
				'optimal' => 40,
			),
			'description'        => __( 'Biggest shopping event of the year - maximize sales!', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'global_revenue'  => __( '$9.8 billion in online sales (2023)', 'smart-cycle-discounts' ),
				'avg_discount'    => __( '37% average discount across retailers', 'smart-cycle-discounts' ),
				'conversion_lift' => __( '3.2x normal conversion rate', 'smart-cycle-discounts' ),
				'mobile_share'    => __( '54% of sales from mobile devices', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promoting 2-3 weeks early to build anticipation', 'smart-cycle-discounts' ),
				__( 'Use countdown timers to create urgency', 'smart-cycle-discounts' ),
				__( 'Bundle products for higher average order value', 'smart-cycle-discounts' ),
				__( 'Extend through Cyber Monday for maximum reach', 'smart-cycle-discounts' ),
				__( 'Prepare 2-3x normal inventory levels', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Launch at midnight EST for early bird shoppers', 'smart-cycle-discounts' ),
				__( 'Offer tiered discounts (spend more, save more)', 'smart-cycle-discounts' ),
				__( 'Send VIP early access emails 24 hours before', 'smart-cycle-discounts' ),
				__( 'Ensure mobile checkout is optimized', 'smart-cycle-discounts' ),
				__( 'Monitor inventory in real-time to prevent stockouts', 'smart-cycle-discounts' ),
			),
			'priority'           => 100,
		),
		array(
			'id'                 => 'small_business_saturday',
			'name'               => __( 'Small Business Saturday', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '🏪',
			'duration_days'      => 1,
			'start_offset'       => 0,
			'calculate_date'     => function ( $year ) {
				// Small Business Saturday is the Saturday after Thanksgiving
				// Thanksgiving is the 4th Thursday in November
				$first_day_of_november = mktime( 0, 0, 0, 11, 1, $year );
				$first_day_of_week = intval( gmdate( 'w', $first_day_of_november ) );

				// Calculate days until first Thursday (Thursday = 4)
				$days_until_first_thursday = ( 4 - $first_day_of_week + 7 ) % 7;
				if ( 0 === $days_until_first_thursday && 4 !== $first_day_of_week ) {
					$days_until_first_thursday = 7;
				}

				// 4th Thursday is first Thursday + 21 days (3 weeks)
				$fourth_thursday_day = 1 + $days_until_first_thursday + 21;

				// Small Business Saturday is 2 days after Thanksgiving (Saturday)
				$small_business_saturday_day = $fourth_thursday_day + 2;

				return mktime( 0, 0, 0, 11, $small_business_saturday_day, $year );
			},
			'lead_time'          => array(
				'base_prep'   => 2,
				'inventory'   => 3,
				'marketing'   => 5,
				'flexibility' => 5,
			),
			'recommendations'    => array(
				__( 'Highlight your local/small business story', 'smart-cycle-discounts' ),
				__( 'Offer exclusive in-store or online-only deals', 'smart-cycle-discounts' ),
				__( 'Partner with other small businesses for bundles', 'smart-cycle-discounts' ),
				__( 'Promote same-day pickup or local delivery', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 15,
				'max'     => 30,
				'optimal' => 20,
			),
			'description'        => __( 'Support local businesses with community-focused shopping', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'spending'  => __( '$17.9 billion in Small Business Saturday spending (2023)', 'smart-cycle-discounts' ),
				'shoppers'  => __( '122 million shoppers participate', 'smart-cycle-discounts' ),
				'awareness' => __( '77% of Americans aware of the day', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Emphasize your small business identity', 'smart-cycle-discounts' ),
				__( 'Share your story and community impact', 'smart-cycle-discounts' ),
				__( 'Offer personalized service and experiences', 'smart-cycle-discounts' ),
			),
			'priority'           => 85,
		),
		array(
			'id'                 => 'christmas',
			'name'               => __( 'Christmas Season', 'smart-cycle-discounts' ),
			'category'           => 'major',
			'icon'               => '🎄',
			'month'              => 12,
			'day'                => 25,
			'duration_days'      => 21,
			'start_offset'       => -21,
			'lead_time'          => array(
				'base_prep'   => 3,
				'inventory'   => 5,
				'marketing'   => 10,
				'flexibility' => 7,
			),
			'recommendations'    => array(
				__( 'Finalize holiday inventory - suppliers have long lead times', 'smart-cycle-discounts' ),
				__( 'Stock gift-friendly items and wrapping supplies', 'smart-cycle-discounts' ),
				__( 'Create gift guides for different recipient types', 'smart-cycle-discounts' ),
				__( 'Set up holiday shipping deadline banners', 'smart-cycle-discounts' ),
				__( 'Plan extended customer service hours', 'smart-cycle-discounts' ),
				__( 'Test gift card and gift message features', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 20,
				'max'     => 40,
				'optimal' => 30,
			),
			'description'        => __( 'Holiday shopping season - peak sales opportunity', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'season_revenue' => __( '$936 billion holiday season spending (2023)', 'smart-cycle-discounts' ),
				'online_growth'  => __( '14% year-over-year online growth', 'smart-cycle-discounts' ),
				'peak_days'      => __( 'Dec 17-23: highest shopping days', 'smart-cycle-discounts' ),
				'gift_purchases' => __( '76% of purchases are gifts', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Start promotions December 1st at the latest', 'smart-cycle-discounts' ),
				__( 'Offer gift wrapping or gift messages', 'smart-cycle-discounts' ),
				__( 'Highlight last-minute shipping deadlines', 'smart-cycle-discounts' ),
				__( 'Create gift guides for different budgets', 'smart-cycle-discounts' ),
				__( 'Extend returns policy through January', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Run campaign for full 3 weeks before Christmas', 'smart-cycle-discounts' ),
				__( 'Increase discounts in final week (Dec 18-24)', 'smart-cycle-discounts' ),
				__( 'Promote gift cards heavily in last 3 days', 'smart-cycle-discounts' ),
				__( 'Prepare customer service for high volume', 'smart-cycle-discounts' ),
				__( 'Stock popular items 2x normal levels', 'smart-cycle-discounts' ),
			),
			'priority'           => 100,
		),
		array(
			'id'                 => 'new_year',
			'name'               => __( 'New Year Sale', 'smart-cycle-discounts' ),
			'category'           => 'ongoing',
			'icon'               => '🎊',
			'month'              => 1,
			'day'                => 1,
			'duration_days'      => 14,
			'start_offset'       => 0,
			'lead_time'          => array(
				'base_prep'   => 3,
				'inventory'   => 3,
				'marketing'   => 7,
				'flexibility' => 3,
			),
			'recommendations'    => array(
				__( 'Review holiday season inventory and plan clearance', 'smart-cycle-discounts' ),
				__( 'Identify products for New Year fresh start themes', 'smart-cycle-discounts' ),
				__( 'Plan New Year, New You marketing campaigns', 'smart-cycle-discounts' ),
				__( 'Promote fitness, wellness, and organization products', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 25,
				'max'     => 35,
				'optimal' => 30,
			),
			'description'        => __( 'Start the year fresh with clearance and new beginnings', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'resolution_market' => __( '$10 billion spent on New Year resolutions', 'smart-cycle-discounts' ),
				'fitness_surge'     => __( '12% of annual gym memberships sold in January', 'smart-cycle-discounts' ),
				'organization'      => __( '80% of resolutions involve self-improvement', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Promote fitness, wellness, and organization products', 'smart-cycle-discounts' ),
				__( 'Create "New Year, New You" themed bundles', 'smart-cycle-discounts' ),
				__( 'Offer discounts on goal-setting and productivity items', 'smart-cycle-discounts' ),
				__( 'Clear holiday inventory with extended clearance pricing', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Launch New Year sale on January 1st', 'smart-cycle-discounts' ),
				__( 'Target health, fitness, and self-improvement products', 'smart-cycle-discounts' ),
				__( 'Use fresh start and new beginnings messaging', 'smart-cycle-discounts' ),
				__( 'Combine with post-holiday clearance strategy', 'smart-cycle-discounts' ),
			),
			'priority'           => 50,
		),
		array(
			'id'                 => 'weekend_sale',
			'name'               => __( 'Weekend Sale', 'smart-cycle-discounts' ),
			'category'           => 'flexible',
			'icon'               => '🎉',
			'duration_days'      => 3,
			'start_offset'       => 0,
			'calculate_date'     => function ( $year ) {
				// Calculate current or next upcoming Friday
				$now = current_time( 'timestamp' );
				$current_day_of_week = intval( wp_date( 'w', $now ) ); // 0 = Sunday, 5 = Friday

				// Weekend Sale is quick & flexible - include THIS weekend if it's Friday
				if ( 5 === $current_day_of_week ) {
					// Today is Friday - return today (campaign can start immediately)
					$days_until_friday = 0;
				} elseif ( $current_day_of_week < 5 ) {
					// Before Friday this week - show this coming Friday
					$days_until_friday = 5 - $current_day_of_week;
				} else {
					// Saturday (6) or Sunday (0) - current weekend already started, show next Friday
					$days_until_friday = ( 7 - $current_day_of_week ) + 5;
				}

				return strtotime( "+{$days_until_friday} days", $now );
			},
			'lead_time'          => array(
				'base_prep'   => 3,
				'inventory'   => 0,
				'marketing'   => 2,
				'flexibility' => 2,
			),
			'recommendations'    => array(
				__( 'Create urgency with countdown timers', 'smart-cycle-discounts' ),
				__( 'Bundle slow-moving inventory with bestsellers', 'smart-cycle-discounts' ),
				__( 'Offer free shipping threshold to increase order value', 'smart-cycle-discounts' ),
				__( 'Promote on social media Thursday evening for maximum reach', 'smart-cycle-discounts' ),
				__( 'Highlight weekend-only deals prominently', 'smart-cycle-discounts' ),
			),
			'suggested_discount' => array(
				'min'     => 10,
				'max'     => 20,
				'optimal' => 15,
			),
			'description'        => __( 'Quick weekend flash sale - perfect for filling quiet calendar gaps', 'smart-cycle-discounts' ),
			'statistics'         => array(
				'weekend_traffic' => __( 'Weekend traffic averages 20-30% higher than weekdays', 'smart-cycle-discounts' ),
				'flash_sales'     => __( 'Flash sales increase conversion rates by 35%', 'smart-cycle-discounts' ),
				'urgency'         => __( '70% of purchases happen in first 24 hours of limited-time offers', 'smart-cycle-discounts' ),
			),
			'tips'               => array(
				__( 'Launch Friday morning to catch early weekend shoppers', 'smart-cycle-discounts' ),
				__( 'Use "Weekend Only" messaging to create urgency', 'smart-cycle-discounts' ),
				__( 'Feature products that benefit from quick decision-making', 'smart-cycle-discounts' ),
				__( 'Keep discounts modest (10-20%) for sustainable profitability', 'smart-cycle-discounts' ),
			),
			'best_practices'     => array(
				__( 'Promote Thursday evening on social media', 'smart-cycle-discounts' ),
				__( 'Send email campaign Friday morning', 'smart-cycle-discounts' ),
				__( 'Add countdown timer to create urgency', 'smart-cycle-discounts' ),
				__( 'Feature fast-selling or impulse-buy products', 'smart-cycle-discounts' ),
			),
			'priority'           => 15,
		),
	);
	}

	/**
	 * Get single event definition by ID.
	 *
	 * @since    1.0.0
	 * @param    string $event_id    Event ID (e.g., 'valentines', 'black_friday').
	 * @return   array|null              Event definition or null if not found.
	 */
	public static function get_event_by_id( string $event_id ): ?array {
		$all_events = self::get_event_definitions();

		foreach ( $all_events as $event ) {
			if ( $event['id'] === $event_id ) {
				// Calculate event date for current year.
				$current_year = intval( wp_date( 'Y' ) );
				$now          = time();
				$event_date   = self::calculate_event_date( $event, $current_year );

				// If event already passed this year, check next year.
				if ( $event_date < $now ) {
					$event_date = self::calculate_event_date( $event, $current_year + 1 );
				}

				$event['event_date'] = $event_date;

				// Calculate campaign start and end dates.
				$start_date = strtotime( $event['start_offset'] . ' days', $event_date );
				$end_date   = strtotime( '+' . $event['duration_days'] . ' days', $start_date );

				$event['calculated_start_date'] = $start_date;
				$event['calculated_end_date']   = $end_date;

				return $event;
			}
		}

		return null;
	}

	/**
	 * Calculate actual event date for a given year.
	 *
	 * @since    1.0.0
	 * @param    array $event Event definition.
	 * @param    int   $year  Year to calculate for.
	 * @return   int          Event timestamp.
	 */
	private static function calculate_event_date( array $event, int $year ): int {
		// Check if this is a dynamic date calculation.
		if ( isset( $event['calculate_date'] ) && is_callable( $event['calculate_date'] ) ) {
			return call_user_func( $event['calculate_date'], $year );
		}

		// Default: fixed date calculation.
		return mktime( 0, 0, 0, $event['month'], $event['day'], $year );
	}
}
