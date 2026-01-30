<?php
/**
 * Campaign Suggestions Registry Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-suggestions-registry.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
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
class WSSCD_Campaign_Suggestions_Registry {

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
				'name'               => __( 'Valentine\'s Day', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'heart',
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
					__( 'Analyze last year\'s Valentine\'s Day performance data', 'smart-cycle-discounts' ),
					__( 'Set up gift guides segmented by price points', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts to incentivize larger purchases [PRO]', 'smart-cycle-discounts' ),
					__( 'Set discount priority to ensure Valentine\'s offers override other campaigns', 'smart-cycle-discounts' ),
					__( 'Create couples bundles from complementary products', 'smart-cycle-discounts' ),
					__( 'Enable product rotation to keep offers fresh throughout campaign [PRO]', 'smart-cycle-discounts' ),
					__( 'Use BOGO discounts for couple gift sets [PRO]', 'smart-cycle-discounts' ),
					__( 'Plan "Gifts for Him" and "Gifts for Her" categories', 'smart-cycle-discounts' ),
					__( 'Schedule campaign to start 7 days before Valentine\'s Day', 'smart-cycle-discounts' ),
					__( 'Optimize product pages for gift-giving keywords', 'smart-cycle-discounts' ),
					__( 'Target customer segments for VIP early access [PRO]', 'smart-cycle-discounts' ),
					__( 'Prepare last-minute gift section for procrastinators', 'smart-cycle-discounts' ),
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
					'online_growth'  => __( '28% prefer shopping online for Valentine\'s', 'smart-cycle-discounts' ),
					'last_minute'    => __( '15% shop on Valentine\'s Day itself', 'smart-cycle-discounts' ),
					'self_purchase'  => __( '22% buy gifts for themselves (self-love trend)', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promoting 1 week before Valentine\'s Day', 'smart-cycle-discounts' ),
					__( 'Create "Gifts for Him" and "Gifts for Her" categories', 'smart-cycle-discounts' ),
					__( 'Use smart product selection to feature romantic gift items', 'smart-cycle-discounts' ),
					__( 'Set usage limits per customer to spread deals across more shoppers', 'smart-cycle-discounts' ),
					__( 'Build "Self-Love" gift section for solo shoppers', 'smart-cycle-discounts' ),
					__( 'Use countdown timers for shipping cutoff dates', 'smart-cycle-discounts' ),
					__( 'Feature budget-friendly options under $50', 'smart-cycle-discounts' ),
					__( 'Monitor campaign analytics to adjust discount levels in real-time', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch 7-10 days before Valentine\'s Day', 'smart-cycle-discounts' ),
					__( 'Create last-minute gift section 2 days before', 'smart-cycle-discounts' ),
					__( 'Use performance-based rotation to prioritize top converting products [PRO]', 'smart-cycle-discounts' ),
					__( 'Bundle complementary gifts (flowers + chocolate)', 'smart-cycle-discounts' ),
					__( 'Set campaign end time to 11:59PM on Valentine\'s Day', 'smart-cycle-discounts' ),
					__( 'Offer gift cards as backup for procrastinators', 'smart-cycle-discounts' ),
				),
				'priority'           => 70,
			),
			array(
				'id'                 => 'easter',
				'name'               => __( 'Easter', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'rabbit',
				'duration_days'      => 7,
				'start_offset'       => -7,
				'date_calculator'    => 'easter',
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 9,
					'flexibility' => 5,
				),
				'recommendations'    => array(
					__( 'Analyze which products performed best last Easter', 'smart-cycle-discounts' ),
					__( 'Create family-oriented product bundles and collections', 'smart-cycle-discounts' ),
					__( 'Use advanced product filters to segment by age groups [PRO]', 'smart-cycle-discounts' ),
					__( 'Configure gift wrapping and delivery services', 'smart-cycle-discounts' ),
					__( 'Plan spring-themed visual merchandising updates', 'smart-cycle-discounts' ),
					__( 'Set up BOGO discounts for family bundle deals [PRO]', 'smart-cycle-discounts' ),
					__( 'Prepare countdown promotions (2 weeks, 1 week, 3 days)', 'smart-cycle-discounts' ),
					__( 'Optimize site for "Easter gifts" search queries', 'smart-cycle-discounts' ),
					__( 'Schedule inventory review 2 weeks before holiday', 'smart-cycle-discounts' ),
					__( 'Create complementary product bundling strategy', 'smart-cycle-discounts' ),
					__( 'Plan post-Easter clearance pricing structure', 'smart-cycle-discounts' ),
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
					'families'   => __( '78% of households celebrate Easter', 'smart-cycle-discounts' ),
					'peak_week'  => __( 'Week before Easter: 65% of shopping occurs', 'smart-cycle-discounts' ),
					'basket_avg' => __( '$150 average Easter basket spending', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promoting 1 week before Easter Sunday', 'smart-cycle-discounts' ),
					__( 'Bundle candy with toys and gifts', 'smart-cycle-discounts' ),
					__( 'Highlight spring fashion and outdoor items', 'smart-cycle-discounts' ),
					__( 'Create pastel-themed product photography', 'smart-cycle-discounts' ),
					__( 'Offer Easter egg hunt kits and supplies', 'smart-cycle-discounts' ),
					__( 'Feature brunch and entertaining products', 'smart-cycle-discounts' ),
					__( 'Promote spring cleaning and organization items', 'smart-cycle-discounts' ),
					__( 'Use family-friendly messaging throughout campaign', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch campaign 1 week before Easter', 'smart-cycle-discounts' ),
					__( 'Create dedicated Easter landing page', 'smart-cycle-discounts' ),
					__( 'Bundle candy with non-candy items for variety', 'smart-cycle-discounts' ),
					__( 'Increase candy inventory 3x normal levels', 'smart-cycle-discounts' ),
					__( 'Offer express shipping 5 days before holiday', 'smart-cycle-discounts' ),
					__( 'Start 50% clearance on Easter Monday', 'smart-cycle-discounts' ),
				),
				'priority'           => 65,
			),
			array(
				'id'                 => 'spring_sale',
				'name'               => __( 'Spring Sale', 'smart-cycle-discounts' ),
				'category'           => 'seasonal',
				'icon'               => 'flower',
				'month'              => 3,
				'day'                => 20,
				'duration_days'      => 10,
				'start_offset'       => 0,
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 5,
					'flexibility' => 3,
				),
				'recommendations'    => array(
					__( 'Review winter inventory for clearance opportunities', 'smart-cycle-discounts' ),
					__( 'Identify seasonal products to feature prominently', 'smart-cycle-discounts' ),
					__( 'Update homepage banners with spring themes', 'smart-cycle-discounts' ),
					__( 'Create "Spring Refresh" bundling strategy', 'smart-cycle-discounts' ),
					__( 'Set up email campaigns for seasonal transition', 'smart-cycle-discounts' ),
					__( 'Identify slow-moving inventory for deep discount', 'smart-cycle-discounts' ),
					__( 'Plan tiered discount structure (clearance vs new)', 'smart-cycle-discounts' ),
					__( 'Optimize category pages for spring search terms', 'smart-cycle-discounts' ),
					__( 'Schedule social media content calendar for spring', 'smart-cycle-discounts' ),
					__( 'Configure cross-sell rules for complementary items', 'smart-cycle-discounts' ),
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
					'outdoor_growth'    => __( '55% increase in outdoor product searches', 'smart-cycle-discounts' ),
					'fashion_turnover'  => __( '70% update wardrobe with spring fashion', 'smart-cycle-discounts' ),
					'home_refresh'      => __( '48% redecorate or refresh home in spring', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Promote winter clearance alongside new spring arrivals', 'smart-cycle-discounts' ),
					__( 'Create "Spring Refresh" themed bundles', 'smart-cycle-discounts' ),
					__( 'Target outdoor and garden products', 'smart-cycle-discounts' ),
					__( 'Use fresh, bright imagery in marketing', 'smart-cycle-discounts' ),
					__( 'Feature pastel colors and floral patterns', 'smart-cycle-discounts' ),
					__( 'Promote eco-friendly and sustainable products', 'smart-cycle-discounts' ),
					__( 'Create spring cleaning checklists with products', 'smart-cycle-discounts' ),
					__( 'Highlight athlet and activewear for outdoor season', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Start clearance early to make room for spring inventory', 'smart-cycle-discounts' ),
					__( 'Bundle winter clearance with spring must-haves', 'smart-cycle-discounts' ),
					__( 'Run for 10 days ending before Easter campaign starts', 'smart-cycle-discounts' ),
					__( 'Update site colors and themes for seasonal appeal', 'smart-cycle-discounts' ),
					__( 'Promote renewal and fresh start messaging', 'smart-cycle-discounts' ),
					__( 'Increase outdoor product inventory 2x', 'smart-cycle-discounts' ),
					__( 'Create dedicated spring landing page', 'smart-cycle-discounts' ),
				),
				'priority'           => 40,
			),
			array(
				'id'                 => 'mothers_day',
				'name'               => __( 'Mother\'s Day', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'mother',
				'duration_days'      => 7,
				'start_offset'       => -7,
				'date_calculator'    => 'mothers_day',
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 9,
					'flexibility' => 5,
				),
				'recommendations'    => array(
					__( 'Analyze last year\'s Mother\'s Day top sellers', 'smart-cycle-discounts' ),
					__( 'Create tiered gift guides by budget ($25, $50, $100+)', 'smart-cycle-discounts' ),
					__( 'Configure gift wrapping and personalization options', 'smart-cycle-discounts' ),
					__( 'Set up shipping deadline banners and notifications', 'smart-cycle-discounts' ),
					__( 'Plan curated gift sets from complementary products', 'smart-cycle-discounts' ),
					__( 'Prepare experience or digital gift options', 'smart-cycle-discounts' ),
					__( 'Set up "last-minute gifts" category 3 days before', 'smart-cycle-discounts' ),
					__( 'Create recipient-segmented collections (mom, grandma, etc)', 'smart-cycle-discounts' ),
					__( 'Schedule reminder emails at 7, 5, 3, 1 day intervals', 'smart-cycle-discounts' ),
					__( 'Optimize product descriptions for gift-giving language', 'smart-cycle-discounts' ),
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
					'avg_per_person' => __( '$274 average spending per person', 'smart-cycle-discounts' ),
					'peak_shopping'  => __( 'Week before: 55% of shopping occurs', 'smart-cycle-discounts' ),
					'self_care'      => __( '38% prefer spa and wellness gifts', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Promote gift sets and curated collections', 'smart-cycle-discounts' ),
					__( 'Offer personalization options if possible', 'smart-cycle-discounts' ),
					__( 'Create Mother\'s Day gift guide', 'smart-cycle-discounts' ),
					__( 'Emphasize shipping deadlines for timely delivery', 'smart-cycle-discounts' ),
					__( 'Feature "Mom deserves it" messaging', 'smart-cycle-discounts' ),
					__( 'Bundle products for spa day at home', 'smart-cycle-discounts' ),
					__( 'Promote brunch and entertaining items', 'smart-cycle-discounts' ),
					__( 'Use testimonials from satisfied gift-givers', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Start campaign 7-10 days before Mother\'s Day', 'smart-cycle-discounts' ),
					__( 'Offer gift wrapping and personalization', 'smart-cycle-discounts' ),
					__( 'Create tiered gift guides by budget', 'smart-cycle-discounts' ),
					__( 'Promote last-minute digital gift cards', 'smart-cycle-discounts' ),
					__( 'Set up recurring campaign template for next year\'s event [PRO]', 'smart-cycle-discounts' ),
					__( 'Use random product rotation to showcase variety throughout campaign [PRO]', 'smart-cycle-discounts' ),
				),
				'priority'           => 70,
			),
			array(
				'id'                 => 'fathers_day',
				'name'               => __( 'Father\'s Day', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'father',
				'duration_days'      => 7,
				'start_offset'       => -7,
				'date_calculator'    => 'fathers_day',
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 9,
					'flexibility' => 5,
				),
				'recommendations'    => array(
					__( 'Analyze last year\'s Father\'s Day best-performing products', 'smart-cycle-discounts' ),
					__( 'Create interest-based gift guides (hobbies, activities)', 'smart-cycle-discounts' ),
					__( 'Configure gift wrapping and greeting card options', 'smart-cycle-discounts' ),
					__( 'Prepare digital/experience gift alternatives', 'smart-cycle-discounts' ),
					__( 'Set up practical gift collections for different budgets', 'smart-cycle-discounts' ),
					__( 'Plan complementary product bundles', 'smart-cycle-discounts' ),
					__( 'Configure personalization or engraving services', 'smart-cycle-discounts' ),
					__( 'Optimize for "gifts for dad" search queries', 'smart-cycle-discounts' ),
					__( 'Schedule reminder emails at 7, 5, 3, 1 day before', 'smart-cycle-discounts' ),
					__( 'Set up same-day pickup option for procrastinators', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min'     => 15,
					'max'     => 25,
					'optimal' => 20,
				),
				'description'        => __( 'Celebrate dads with targeted gift promotions', 'smart-cycle-discounts' ),
				'statistics'         => array(
					'spending'        => __( '$20 billion in Father\'s Day spending (2023)', 'smart-cycle-discounts' ),
					'avg_spend'       => __( '$171 per household average', 'smart-cycle-discounts' ),
					'top_gifts'       => __( 'Clothing, gift cards, electronics', 'smart-cycle-discounts' ),
					'celebrating'     => __( '75% of Americans celebrate Father\'s Day', 'smart-cycle-discounts' ),
					'peak_shopping'   => __( 'Week before: 48% of shopping occurs', 'smart-cycle-discounts' ),
					'practical_gifts' => __( '62% prefer practical over sentimental gifts', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promoting 2 weeks before Father\'s Day', 'smart-cycle-discounts' ),
					__( 'Create curated gift guides by interest', 'smart-cycle-discounts' ),
					__( 'Bundle complementary products (grill + accessories)', 'smart-cycle-discounts' ),
					__( 'Feature "Dad Jokes" themed messaging', 'smart-cycle-discounts' ),
					__( 'Promote tech gadgets and tools', 'smart-cycle-discounts' ),
					__( 'Offer same-day pickup for procrastinators', 'smart-cycle-discounts' ),
					__( 'Create activity and experience gift options', 'smart-cycle-discounts' ),
					__( 'Use masculine colors and bold designs', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch campaign 7-10 days before Father\'s Day', 'smart-cycle-discounts' ),
					__( 'Create interest-based gift guides (golfer, griller, etc)', 'smart-cycle-discounts' ),
					__( 'Use sequential rotation to cycle through interest categories [PRO]', 'smart-cycle-discounts' ),
					__( 'Promote digital gift cards for last-minute shoppers', 'smart-cycle-discounts' ),
					__( 'Create recurring campaign template for next year [PRO]', 'smart-cycle-discounts' ),
					__( 'Set max 5 concurrent products for focused promotion [PRO]', 'smart-cycle-discounts' ),
				),
				'priority'           => 70,
			),
			array(
				'id'                 => 'summer_sale',
				'name'               => __( 'Summer Sale', 'smart-cycle-discounts' ),
				'category'           => 'seasonal',
				'icon'               => 'sun',
				'month'              => 6,
				'day'                => 21,
				'duration_days'      => 14,
				'start_offset'       => 0,
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 5,
					'flexibility' => 3,
				),
				'recommendations'    => array(
					__( 'Review spring inventory for clearance opportunities', 'smart-cycle-discounts' ),
					__( 'Identify seasonal products to feature prominently', 'smart-cycle-discounts' ),
					__( 'Update homepage with summer-themed visuals', 'smart-cycle-discounts' ),
					__( 'Create summer-oriented product bundles', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts for progressive spring clearance [PRO]', 'smart-cycle-discounts' ),
					__( 'Prepare mid-summer clearance pricing strategy', 'smart-cycle-discounts' ),
					__( 'Set spend threshold discounts to increase average order value [PRO]', 'smart-cycle-discounts' ),
					__( 'Set up vacation/travel-focused collections', 'smart-cycle-discounts' ),
					__( 'Optimize product pages for summer search terms', 'smart-cycle-discounts' ),
					__( 'Schedule email campaigns for seasonal transition', 'smart-cycle-discounts' ),
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
					'outdoor_surge'    => __( '80% increase in outdoor product demand', 'smart-cycle-discounts' ),
					'peak_month'       => __( 'June: highest summer shopping activity', 'smart-cycle-discounts' ),
					'vacation_ready'   => __( '52% shop for vacation essentials', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Promote outdoor, travel, and vacation products', 'smart-cycle-discounts' ),
					__( 'Create "Summer Essentials" bundles', 'smart-cycle-discounts' ),
					__( 'Offer early clearance on spring items', 'smart-cycle-discounts' ),
					__( 'Use bright, sunny imagery in promotions', 'smart-cycle-discounts' ),
					__( 'Feature beach and pool party supplies', 'smart-cycle-discounts' ),
					__( 'Promote cooling and hydration products', 'smart-cycle-discounts' ),
					__( 'Create travel and vacation packing guides', 'smart-cycle-discounts' ),
					__( 'Highlight sun protection and outdoor safety items', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch sale at summer solstice (June 21)', 'smart-cycle-discounts' ),
					__( 'Bundle seasonal items with evergreen products', 'smart-cycle-discounts' ),
					__( 'Run for 2 weeks ending before July 4th weekend', 'smart-cycle-discounts' ),
					__( 'Promote free shipping for summer convenience', 'smart-cycle-discounts' ),
					__( 'Increase outdoor inventory 2.5x normal levels', 'smart-cycle-discounts' ),
					__( 'Create urgency for July 4th preparations', 'smart-cycle-discounts' ),
				),
				'priority'           => 40,
			),
			array(
				'id'                 => 'july_4th',
				'name'               => __( 'Independence Day Sale', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'fireworks',
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
					__( 'Plan themed product collections around celebration', 'smart-cycle-discounts' ),
					__( 'Create complementary product bundles for entertaining', 'smart-cycle-discounts' ),
					__( 'Set up extended 4-day weekend sale (Fri-Mon)', 'smart-cycle-discounts' ),
					__( 'Configure patriotic-themed visual merchandising', 'smart-cycle-discounts' ),
					__( 'Use geographic restrictions to target US customers only [PRO]', 'smart-cycle-discounts' ),
					__( 'Set up email notifications for flash deals and hourly promotions [PRO]', 'smart-cycle-discounts' ),
					__( 'Optimize shipping cutoffs for holiday delivery', 'smart-cycle-discounts' ),
					__( 'Schedule social media posts for peak engagement times', 'smart-cycle-discounts' ),
					__( 'Create urgency with countdown timers', 'smart-cycle-discounts' ),
					__( 'Plan inventory levels for 3-day surge', 'smart-cycle-discounts' ),
					__( 'Set up email sequence (2 weeks, 1 week, 2 days before)', 'smart-cycle-discounts' ),
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
					'weekend'      => __( '3-day weekend drives 45% more traffic', 'smart-cycle-discounts' ),
					'peak_day'     => __( 'July 3rd: highest pre-holiday shopping day', 'smart-cycle-discounts' ),
					'mobile'       => __( '58% shop via mobile during holiday weekend', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promoting 1-2 weeks before holiday', 'smart-cycle-discounts' ),
					__( 'Create complementary product bundles', 'smart-cycle-discounts' ),
					__( 'Extend sale through full 4-day weekend', 'smart-cycle-discounts' ),
					__( 'Use patriotic themes in email and site design', 'smart-cycle-discounts' ),
					__( 'Feature "Last Chance" messaging on July 3rd', 'smart-cycle-discounts' ),
					__( 'Optimize mobile checkout for weekend shoppers', 'smart-cycle-discounts' ),
					__( 'Schedule flash sales at peak traffic times', 'smart-cycle-discounts' ),
					__( 'Promote free shipping for convenience', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch campaign 2 weeks before July 4th', 'smart-cycle-discounts' ),
					__( 'Run extended 4-day sale (July 1-4)', 'smart-cycle-discounts' ),
					__( 'Use countdown timers to create urgency', 'smart-cycle-discounts' ),
					__( 'Optimize for mobile shopping (peak 58%)', 'smart-cycle-discounts' ),
					__( 'Prepare customer service for holiday hours', 'smart-cycle-discounts' ),
					__( 'Stock popular items 1.5x normal levels', 'smart-cycle-discounts' ),
				),
				'priority'           => 60,
			),
			array(
				'id'                 => 'back_to_school',
				'name'               => __( 'Back to School Sale', 'smart-cycle-discounts' ),
				'category'           => 'ongoing',
				'icon'               => 'backpack',
				'month'              => 8,
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
					__( 'Analyze which products appeal to students/parents', 'smart-cycle-discounts' ),
					__( 'Create age-segmented product bundles', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts for bulk purchases (buy more, save more) [PRO]', 'smart-cycle-discounts' ),
					__( 'Use advanced filters to segment by grade levels and subjects [PRO]', 'smart-cycle-discounts' ),
					__( 'Plan 2-week campaign structure (Aug 1-14)', 'smart-cycle-discounts' ),
					__( 'Optimize category pages for back-to-school keywords', 'smart-cycle-discounts' ),
					__( 'Schedule weekly email campaigns with fresh deals', 'smart-cycle-discounts' ),
					__( 'Promote tax-free shopping days where applicable', 'smart-cycle-discounts' ),
					__( 'Create checklists with product recommendations', 'smart-cycle-discounts' ),
					__( 'End campaign before Labor Day weekend begins', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min'     => 15,
					'max'     => 25,
					'optimal' => 20,
				),
				'description'        => __( 'Help students and parents gear up for the new school year', 'smart-cycle-discounts' ),
				'statistics'         => array(
					'school_spending' => __( '$41.5 billion back-to-school spending (2023)', 'smart-cycle-discounts' ),
					'avg_per_family'  => __( '$890 average per family spending', 'smart-cycle-discounts' ),
					'peak_shopping'   => __( 'July-August: 85% of shopping occurs', 'smart-cycle-discounts' ),
					'early_shoppers'  => __( '55% start shopping in July', 'smart-cycle-discounts' ),
					'peak_week'       => __( 'First week of August: highest activity', 'smart-cycle-discounts' ),
					'online_growth'   => __( '32% prefer online shopping for convenience', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promotions in late July for early shoppers', 'smart-cycle-discounts' ),
					__( 'Create age and grade-specific bundles', 'smart-cycle-discounts' ),
					__( 'Offer bulk discounts for families with multiple children', 'smart-cycle-discounts' ),
					__( 'Promote tax-free shopping days prominently', 'smart-cycle-discounts' ),
					__( 'Use "Get Ready for School" messaging', 'smart-cycle-discounts' ),
					__( 'Create shopping checklists to increase basket size', 'smart-cycle-discounts' ),
					__( 'Feature deals that refresh weekly', 'smart-cycle-discounts' ),
					__( 'Highlight free shipping for bulk orders', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch August 1st to capture early school starters', 'smart-cycle-discounts' ),
					__( 'Target parents with age-appropriate bundles', 'smart-cycle-discounts' ),
					__( 'Increase inventory 2x for popular school items', 'smart-cycle-discounts' ),
					__( 'Run for 2 weeks ending before Labor Day weekend', 'smart-cycle-discounts' ),
				),
				'priority'           => 60,
			),
			array(
				'id'                 => 'labor_day',
				'name'               => __( 'Labor Day Sale', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'briefcase',
				'duration_days'      => 3,
				'start_offset'       => -1,
				'date_calculator'    => 'labor_day',
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 7,
					'flexibility' => 5,
				),
				'recommendations'    => array(
					__( 'Plan end-of-summer clearance pricing strategy', 'smart-cycle-discounts' ),
					__( 'Create fall transition product collections', 'smart-cycle-discounts' ),
					__( 'Set up extended 4-day sale (Friday-Monday)', 'smart-cycle-discounts' ),
					__( 'Configure tiered discounts by inventory age', 'smart-cycle-discounts' ),
					__( 'Prepare flash sales for peak weekend traffic', 'smart-cycle-discounts' ),
					__( 'Optimize big-ticket item promotions', 'smart-cycle-discounts' ),
					__( 'Schedule social media countdown campaign', 'smart-cycle-discounts' ),
					__( 'Plan inventory liquidation for summer stock', 'smart-cycle-discounts' ),
					__( 'Set up email sequence (2 weeks, 1 week, 2 days)', 'smart-cycle-discounts' ),
					__( 'Configure free shipping thresholds strategically', 'smart-cycle-discounts' ),
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
					'weekend'    => __( '3-day weekend drives 38% more traffic', 'smart-cycle-discounts' ),
					'clearance'  => __( '65% actively seek end-of-season deals', 'smart-cycle-discounts' ),
					'big_ticket' => __( 'Higher average order value (35% increase)', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promoting 1-2 weeks before the holiday', 'smart-cycle-discounts' ),
					__( 'Clear summer inventory with deep discounts', 'smart-cycle-discounts' ),
					__( 'Extend sale through the full 3-day weekend', 'smart-cycle-discounts' ),
					__( 'Feature "Last Chance Summer" messaging', 'smart-cycle-discounts' ),
					__( 'Promote big-ticket items with higher discounts', 'smart-cycle-discounts' ),
					__( 'Bundle summer clearance with fall essentials', 'smart-cycle-discounts' ),
					__( 'Use countdown timers for urgency', 'smart-cycle-discounts' ),
					__( 'Highlight free shipping for larger purchases', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch campaign 2 weeks before Labor Day', 'smart-cycle-discounts' ),
					__( 'Run extended 4-day sale (Fri-Mon)', 'smart-cycle-discounts' ),
					__( 'Increase discounts on aging summer inventory', 'smart-cycle-discounts' ),
					__( 'Promote higher-value items prominently', 'smart-cycle-discounts' ),
					__( 'Prepare customer service for 3-day surge', 'smart-cycle-discounts' ),
					__( 'Clear 80%+ of summer stock before September', 'smart-cycle-discounts' ),
				),
				'priority'           => 65,
			),
			array(
				'id'                 => 'halloween',
				'name'               => __( 'Halloween', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'pumpkin',
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
					__( 'Analyze last year\'s Halloween top-performing products', 'smart-cycle-discounts' ),
					__( 'Plan themed product bundles and party packages', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts for early-bird vs last-minute shoppers [PRO]', 'smart-cycle-discounts' ),
					__( 'Set up October 25th "Last Chance" promotional push', 'smart-cycle-discounts' ),
					__( 'Schedule Nov 1st clearance pricing (50-75% off)', 'smart-cycle-discounts' ),
					__( 'Use inventory-based rotation to prioritize in-stock items [PRO]', 'smart-cycle-discounts' ),
					__( 'Use advanced filters to segment by costume sizes and themes [PRO]', 'smart-cycle-discounts' ),
					__( 'Schedule multiple campaigns (2 weeks, 1 week, last day) with increasing discounts', 'smart-cycle-discounts' ),
					__( 'Optimize category pages for Halloween keywords', 'smart-cycle-discounts' ),
					__( 'Create Halloween-themed visual merchandising', 'smart-cycle-discounts' ),
					__( 'Set up social media countdown campaign', 'smart-cycle-discounts' ),
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
					'last_minute'    => __( '35% shop in final 3 days before Halloween', 'smart-cycle-discounts' ),
					'avg_per_person' => __( '$108 average spending per celebrator', 'smart-cycle-discounts' ),
					'online_growth'  => __( '42% increase in online Halloween shopping', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promotions 10-14 days before Halloween', 'smart-cycle-discounts' ),
					__( 'Create themed bundles for parties and events', 'smart-cycle-discounts' ),
					__( 'Feature "Last-Minute" collection starting Oct 27', 'smart-cycle-discounts' ),
					__( 'Increase discounts progressively as Oct 31 approaches', 'smart-cycle-discounts' ),
					__( 'Use Halloween-themed email subject lines', 'smart-cycle-discounts' ),
					__( 'Promote express shipping prominently in final week', 'smart-cycle-discounts' ),
					__( 'Create urgency with "Only X Days Until Halloween"', 'smart-cycle-discounts' ),
					__( 'Start aggressive clearance on November 1st morning', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch sale 10-14 days before Halloween', 'smart-cycle-discounts' ),
					__( 'Create themed product bundles for celebrations', 'smart-cycle-discounts' ),
					__( 'Offer express shipping options in final 3 days', 'smart-cycle-discounts' ),
					__( 'Increase inventory 2x for peak Oct 15-25 period', 'smart-cycle-discounts' ),
					__( 'Plan aggressive 50-75% clearance for Nov 1st', 'smart-cycle-discounts' ),
					__( 'Target last-minute shoppers with urgency messaging', 'smart-cycle-discounts' ),
				),
				'priority'           => 70,
			),
			array(
				'id'                 => 'black_friday',
				'name'               => __( 'Black Friday / Cyber Monday', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'shopping-bag',
				'duration_days'      => 4,
				'start_offset'       => 0,
				'date_calculator'    => 'black_friday',
				'lead_time'          => array(
					'base_prep'   => 2,
					'inventory'   => 3,
					'marketing'   => 7,
					'flexibility' => 5,
				),
				'recommendations'    => array(
					__( 'Track detailed analytics to adjust strategy in real-time [PRO]', 'smart-cycle-discounts' ),
					__( 'Use performance-based rotation to prioritize top converting products [PRO]', 'smart-cycle-discounts' ),
					__( 'Load test checkout flow and payment processing', 'smart-cycle-discounts' ),
					__( 'Schedule additional customer support coverage', 'smart-cycle-discounts' ),
					__( 'Run multiple campaigns for separate doorbusters and flash deals', 'smart-cycle-discounts' ),
					__( 'Set higher campaign priority to ensure Black Friday deals override others', 'smart-cycle-discounts' ),
					__( 'Target VIP customer segments for 24-hour early access [PRO]', 'smart-cycle-discounts' ),
					__( 'Set up email notifications for hourly flash deal alerts [PRO]', 'smart-cycle-discounts' ),
					__( 'Optimize server capacity for traffic surge', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts to drive higher average order values [PRO]', 'smart-cycle-discounts' ),
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
					'peak_hour'       => __( 'Thursday 9PM-midnight: highest traffic surge', 'smart-cycle-discounts' ),
					'cyber_monday'    => __( '$12.4 billion Cyber Monday (record high)', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Start promoting 2-3 weeks early to build anticipation', 'smart-cycle-discounts' ),
					__( 'Use countdown timers throughout the site', 'smart-cycle-discounts' ),
					__( 'Bundle complementary products strategically', 'smart-cycle-discounts' ),
					__( 'Extend through Cyber Monday for 4-day event', 'smart-cycle-discounts' ),
					__( 'Use inventory-based rotation to automatically pause out-of-stock items [PRO]', 'smart-cycle-discounts' ),
					__( 'Create separate "VIP Early Access" campaign starting 24 hours before', 'smart-cycle-discounts' ),
					__( 'Optimize mobile experience (54% of traffic)', 'smart-cycle-discounts' ),
					__( 'Track campaign analytics hourly, adjust strategy as needed', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch at midnight EST for early bird shoppers', 'smart-cycle-discounts' ),
					__( 'Offer tiered discounts (spend more, save more)', 'smart-cycle-discounts' ),
					__( 'Create VIP early access campaign starting 24 hours before main event', 'smart-cycle-discounts' ),
					__( 'Ensure mobile checkout is fully optimized', 'smart-cycle-discounts' ),
					__( 'Enable inventory-based rotation to automatically feature available stock [PRO]', 'smart-cycle-discounts' ),
					__( 'Prepare customer service for 4x normal volume', 'smart-cycle-discounts' ),
				),
				'priority'           => 100,
			),
			array(
				'id'                 => 'christmas',
				'name'               => __( 'Christmas Season', 'smart-cycle-discounts' ),
				'category'           => 'major',
				'icon'               => 'tree',
				'month'              => 12,
				'day'                => 25,
				'duration_days'      => 23,
				'start_offset'       => -24,
				'lead_time'          => array(
					'base_prep'   => 3,
					'inventory'   => 5,
					'marketing'   => 10,
					'flexibility' => 7,
				),
				'recommendations'    => array(
					__( 'Finalize holiday inventory (suppliers face delays)', 'smart-cycle-discounts' ),
					__( 'Create recipient-based gift guides (him/her/kids/etc)', 'smart-cycle-discounts' ),
					__( 'Set up shipping deadline countdown banners', 'smart-cycle-discounts' ),
					__( 'Configure gift wrapping and messaging services', 'smart-cycle-discounts' ),
					__( 'Plan extended customer service coverage', 'smart-cycle-discounts' ),
					__( 'Test and promote gift card functionality', 'smart-cycle-discounts' ),
					__( 'Use tiered discounts for budget gift collections ($25/$50/$100) [PRO]', 'smart-cycle-discounts' ),
					__( 'Set up BOGO deals for popular gift bundle combinations [PRO]', 'smart-cycle-discounts' ),
					__( 'Use advanced filters to segment by age groups and interests [PRO]', 'smart-cycle-discounts' ),
					__( 'Target loyal customer segments with exclusive early offers [PRO]', 'smart-cycle-discounts' ),
					__( 'Prepare last-minute gift section (Dec 20+)', 'smart-cycle-discounts' ),
					__( 'Schedule progressive discount strategy (increase weekly)', 'smart-cycle-discounts' ),
					__( 'Set up automated email notifications for daily deals [PRO]', 'smart-cycle-discounts' ),
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
					'mobile_peak'    => __( '61% shop via mobile during holidays', 'smart-cycle-discounts' ),
					'late_shoppers'  => __( '42% complete shopping in final 2 weeks', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Launch promotions December 1st at the latest', 'smart-cycle-discounts' ),
					__( 'Feature gift wrapping and messaging options', 'smart-cycle-discounts' ),
					__( 'Display shipping deadline countdown prominently', 'smart-cycle-discounts' ),
					__( 'Create tiered gift guides by budget', 'smart-cycle-discounts' ),
					__( 'Extend returns policy through January 31st', 'smart-cycle-discounts' ),
					__( 'Increase discounts progressively each week', 'smart-cycle-discounts' ),
					__( 'Promote gift cards heavily Dec 20-24', 'smart-cycle-discounts' ),
					__( 'Optimize mobile experience (61% traffic)', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch December 1st for full holiday shopping season ending Christmas Eve', 'smart-cycle-discounts' ),
					__( 'Increase discounts in final week (Dec 18-24)', 'smart-cycle-discounts' ),
					__( 'Promote gift cards and digital gifts Dec 20+', 'smart-cycle-discounts' ),
					__( 'Prepare customer service for 3x normal volume', 'smart-cycle-discounts' ),
					__( 'Stock popular gift items 2-3x normal levels', 'smart-cycle-discounts' ),
					__( 'Schedule daily email reminders final week', 'smart-cycle-discounts' ),
				),
				'priority'           => 100,
			),
			array(
				'id'                 => 'new_year',
				'name'               => __( 'New Year Sale', 'smart-cycle-discounts' ),
				'category'           => 'ongoing',
				'icon'               => 'party',
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
					__( 'Analyze holiday inventory for clearance strategy', 'smart-cycle-discounts' ),
					__( 'Create "New Year, New You" themed collections', 'smart-cycle-discounts' ),
					__( 'Plan resolution-focused marketing campaigns', 'smart-cycle-discounts' ),
					__( 'Identify products aligned with common resolutions', 'smart-cycle-discounts' ),
					__( 'Set up tiered clearance for remaining holiday stock', 'smart-cycle-discounts' ),
					__( 'Configure "Fresh Start" bundling strategy', 'smart-cycle-discounts' ),
					__( 'Schedule 2-week email campaign (Jan 1-14)', 'smart-cycle-discounts' ),
					__( 'Optimize for self-improvement search keywords', 'smart-cycle-discounts' ),
					__( 'Create motivational messaging for new year theme', 'smart-cycle-discounts' ),
					__( 'Plan social media content around goal-setting', 'smart-cycle-discounts' ),
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
					'january_shopping'  => __( '62% actively shop for fresh start items', 'smart-cycle-discounts' ),
					'clearance_appeal'  => __( '71% seek post-holiday clearance deals', 'smart-cycle-discounts' ),
					'engagement_peak'   => __( 'First 2 weeks: highest new year motivation', 'smart-cycle-discounts' ),
				),
				'tips'               => array(
					__( 'Feature products aligned with popular resolutions', 'smart-cycle-discounts' ),
					__( 'Create "New Year, New You" themed product bundles', 'smart-cycle-discounts' ),
					__( 'Use motivational fresh start messaging throughout', 'smart-cycle-discounts' ),
					__( 'Combine with aggressive holiday clearance pricing', 'smart-cycle-discounts' ),
					__( 'Target self-improvement and goal-oriented shoppers', 'smart-cycle-discounts' ),
					__( 'Schedule email campaigns for Jan 1, 7, and 14', 'smart-cycle-discounts' ),
					__( 'Create before/after or transformation messaging', 'smart-cycle-discounts' ),
					__( 'Promote "Start Strong" limited-time offers', 'smart-cycle-discounts' ),
				),
				'best_practices'     => array(
					__( 'Launch New Year sale on January 1st', 'smart-cycle-discounts' ),
					__( 'Run dual strategy: resolution + clearance', 'smart-cycle-discounts' ),
					__( 'Use fresh start and new beginnings messaging', 'smart-cycle-discounts' ),
					__( 'Clear 90%+ of holiday inventory by Jan 14', 'smart-cycle-discounts' ),
					__( 'Target first 2 weeks when motivation is highest', 'smart-cycle-discounts' ),
					__( 'Feature transformation and goal-achievement themes', 'smart-cycle-discounts' ),
				),
				'priority'           => 50,
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
				$current_year = intval( wp_date( 'Y' ) );
				$now          = time();
				$event_date   = self::calculate_event_date( $event, $current_year );

				// If event already passed this year, check next year.
				if ( $event_date < $now ) {
					$event_date = self::calculate_event_date( $event, $current_year + 1 );
				}

				$event['event_date'] = $event_date;

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
		if ( isset( $event['date_calculator'] ) ) {
			return self::call_date_calculator( $event['date_calculator'], $year );
		}

		// Default: fixed date calculation.
		return mktime( 0, 0, 0, $event['month'], $event['day'], $year );
	}

	/**
	 * Call appropriate date calculator method.
	 *
	 * @since    1.0.0
	 * @param    string $calculator Calculator name.
	 * @param    int    $year       Year.
	 * @return   int                Timestamp.
	 */
	public static function call_date_calculator( string $calculator, int $year ): int {
		switch ( $calculator ) {
			case 'easter':
				return self::calculate_easter_date( $year );
			case 'mothers_day':
				return self::calculate_mothers_day( $year );
			case 'fathers_day':
				return self::calculate_fathers_day( $year );
			case 'labor_day':
				return self::calculate_labor_day( $year );
			case 'black_friday':
				return self::calculate_black_friday( $year );
			default:
				return 0;
		}
	}

	/**
	 * Calculate Easter date using Computus algorithm.
	 *
	 * @since    1.0.0
	 * @param    int $year Year.
	 * @return   int       Timestamp.
	 */
	private static function calculate_easter_date( int $year ): int {
		$a     = $year % 19;
		$b     = intval( $year / 100 );
		$c     = $year % 100;
		$d     = intval( $b / 4 );
		$e     = $b % 4;
		$f     = intval( ( $b + 8 ) / 25 );
		$g     = intval( ( $b - $f + 1 ) / 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intval( $c / 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intval( ( $a + 11 * $h + 22 * $l ) / 451 );
		$month = intval( ( $h + $l - 7 * $m + 114 ) / 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

		return mktime( 0, 0, 0, $month, $day, $year );
	}

	/**
	 * Calculate Mother's Day (2nd Sunday in May).
	 *
	 * @since    1.0.0
	 * @param    int $year Year.
	 * @return   int       Timestamp.
	 */
	private static function calculate_mothers_day( int $year ): int {
		$first_day_of_may  = mktime( 0, 0, 0, 5, 1, $year );
		$first_day_of_week = intval( gmdate( 'w', $first_day_of_may ) );

		$days_until_first_sunday = ( 0 === $first_day_of_week ) ? 0 : ( 7 - $first_day_of_week );
		$second_sunday_day       = 1 + $days_until_first_sunday + 7;

		return mktime( 0, 0, 0, 5, $second_sunday_day, $year );
	}

	/**
	 * Calculate Father's Day (3rd Sunday in June).
	 *
	 * @since    1.0.0
	 * @param    int $year Year.
	 * @return   int       Timestamp.
	 */
	private static function calculate_fathers_day( int $year ): int {
		$first_day_of_june = mktime( 0, 0, 0, 6, 1, $year );
		$first_day_of_week = intval( gmdate( 'w', $first_day_of_june ) );

		$days_until_first_sunday = ( 0 === $first_day_of_week ) ? 0 : ( 7 - $first_day_of_week );
		$third_sunday_day        = 1 + $days_until_first_sunday + 14;

		return mktime( 0, 0, 0, 6, $third_sunday_day, $year );
	}

	/**
	 * Calculate Labor Day (1st Monday in September).
	 *
	 * @since    1.0.0
	 * @param    int $year Year.
	 * @return   int       Timestamp.
	 */
	private static function calculate_labor_day( int $year ): int {
		$first_day_of_september = mktime( 0, 0, 0, 9, 1, $year );
		$first_day_of_week      = intval( gmdate( 'w', $first_day_of_september ) );

		$days_until_first_monday = ( 1 === $first_day_of_week ) ? 0 : ( ( 1 - $first_day_of_week + 7 ) % 7 );
		$first_monday_day        = 1 + $days_until_first_monday;

		return mktime( 0, 0, 0, 9, $first_monday_day, $year );
	}

	/**
	 * Calculate Black Friday (Friday after Thanksgiving).
	 *
	 * @since    1.0.0
	 * @param    int $year Year.
	 * @return   int       Timestamp.
	 */
	private static function calculate_black_friday( int $year ): int {
		$first_day_of_november = mktime( 0, 0, 0, 11, 1, $year );
		$first_day_of_week     = intval( gmdate( 'w', $first_day_of_november ) );

		$days_until_first_thursday = ( 4 - $first_day_of_week + 7 ) % 7;
		if ( 0 === $days_until_first_thursday && 4 !== $first_day_of_week ) {
			$days_until_first_thursday = 7;
		}

		$fourth_thursday_day = 1 + $days_until_first_thursday + 21;
		$black_friday_day    = $fourth_thursday_day + 1;

		return mktime( 0, 0, 0, 11, $black_friday_day, $year );
	}
}
