<?php
/**
 * Sidebar Help Topics Registry
 *
 * Centralized registry for all contextual help topics shown in wizard sidebar.
 * Maps field names to help content for focus-aware sidebar system.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Help Topics Registry
 *
 * @since 1.0.0
 */
class SCD_Sidebar_Help_Topics {


	/**
	 * Get campaign name help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_campaign_name_topic() {
		return array(
			'title'   => __( 'Naming Your Campaign', 'smart-cycle-discounts' ),
			'icon'    => 'edit',
			'type'    => 'examples',
			'content' => array(
				'quick_tip' => __( 'Use descriptive names for easy identification', 'smart-cycle-discounts' ),
				'examples'  => array(
					array(
						'label' => __( 'Good Examples', 'smart-cycle-discounts' ),
						'type'  => 'success',
						'items' => array(
							__( 'Summer Sale 2025 - 20% Off', 'smart-cycle-discounts' ),
							__( 'BOGO Women\'s Shoes', 'smart-cycle-discounts' ),
						),
					),
					array(
						'label' => __( 'Avoid', 'smart-cycle-discounts' ),
						'type'  => 'danger',
						'items' => array(
							__( '"Sale1", "Campaign" (too vague)', 'smart-cycle-discounts' ),
						),
					),
				),
				'pro_tip'          => __( 'Include discount type and amount in the name', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Using duplicate names - makes tracking impossible', 'smart-cycle-discounts' ),
					__( 'Omitting dates for seasonal campaigns', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get campaign description help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_campaign_description_topic() {
		return array(
			'title'   => __( 'Campaign Description', 'smart-cycle-discounts' ),
			'icon'    => 'welcome-write-blog',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Internal notes for your team (not shown to customers)', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Document campaign goals, target audience, and expected results', 'smart-cycle-discounts' ),
					__( 'Include margin limits, budget constraints, or approval notes', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Note the business goal: clearance, seasonal, etc.', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Leaving it blank - team members won\'t understand campaign purpose', 'smart-cycle-discounts' ),
					__( 'Not documenting approval or budget limits', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get priority help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_priority_topic() {
		return array(
			'title'   => __( 'Campaign Priority', 'smart-cycle-discounts' ),
			'icon'    => 'sort',
			'type'    => 'scale',
			'content' => array(
				'quick_tip' => __( 'Higher priority wins when products match multiple campaigns', 'smart-cycle-discounts' ),
				'scale'     => array(
					5 => array(
						'label'   => __( 'Critical', 'smart-cycle-discounts' ),
						'use'     => __( 'VIP sales, exclusive', 'smart-cycle-discounts' ),
						'stars'   => 5,
					),
					3 => array(
						'label'   => __( 'Normal', 'smart-cycle-discounts' ),
						'use'     => __( 'Regular campaigns', 'smart-cycle-discounts' ),
						'stars'   => 3,
						'default' => true,
					),
					1 => array(
						'label'   => __( 'Fallback', 'smart-cycle-discounts' ),
						'use'     => __( 'Always-on background', 'smart-cycle-discounts' ),
						'stars'   => 1,
					),
				),
				'pro_tip'   => __( 'Equal priority? First created wins', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get product selection type help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_product_selection_type_topic() {
		return array(
			'title'   => __( 'Product Selection Methods', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'methods',
			'content' => array(
				'quick_tip' => __( 'Choose how to select which products get discounted', 'smart-cycle-discounts' ),
				'methods'   => array(
					'all_products'       => array(
						'icon'  => 'cart',
						'label' => __( 'All Products', 'smart-cycle-discounts' ),
						'desc'  => __( 'Apply to entire catalog or specific categories', 'smart-cycle-discounts' ),
						'when'  => __( 'Site-wide sales, seasonal promotions', 'smart-cycle-discounts' ),
					),
					'specific_products'  => array(
						'icon'  => 'marker',
						'label' => __( 'Specific Products', 'smart-cycle-discounts' ),
						'desc'  => __( 'Hand-pick products for precise control', 'smart-cycle-discounts' ),
						'when'  => __( 'Clearance items, featured products, flash sales', 'smart-cycle-discounts' ),
					),
					'random_products'    => array(
						'icon'  => 'randomize',
						'label' => __( 'Random Products', 'smart-cycle-discounts' ),
						'desc'  => __( 'Randomly select X products from catalog', 'smart-cycle-discounts' ),
						'when'  => __( 'Daily deals, surprise sales, gamification', 'smart-cycle-discounts' ),
					),
					'smart_selection'    => array(
						'icon'  => 'filter',
						'label' => __( 'Smart Selection', 'smart-cycle-discounts' ),
						'desc'  => __( 'Use conditions to filter products automatically', 'smart-cycle-discounts' ),
						'when'  => __( 'Inventory management, margin-based pricing', 'smart-cycle-discounts' ),
					),
				),
			),
		);
	}

	/**
	 * Get category IDs help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_category_ids_topic() {
		return array(
			'title'   => __( 'Category Selection', 'smart-cycle-discounts' ),
			'icon'    => 'category',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose "All" or pick specific categories', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Seasonal: "Winter Clothing" for timely sales', 'smart-cycle-discounts' ),
					__( 'Includes all products in subcategories automatically', 'smart-cycle-discounts' ),
					__( 'Works with product selection method below', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Select multiple for bundle promotions', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Forgetting parent category includes all children', 'smart-cycle-discounts' ),
					__( 'Selecting too many unrelated categories - dilutes campaign focus', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get product IDs help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_product_ids_topic() {
		return array(
			'title'   => __( 'Product Selection', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Search by name, SKU, or category', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Check stock badges and sale indicators', 'smart-cycle-discounts' ),
					__( 'Type to search - results update as you type', 'smart-cycle-discounts' ),
					__( 'Selected products show with count and remove option', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Use wildcards (*) in SKU for pattern matching', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Not verifying stock levels before launch', 'smart-cycle-discounts' ),
					__( 'Selecting products already on sale elsewhere', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get random count help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_random_count_topic() {
		return array(
			'title'   => __( 'Random Selection Count', 'smart-cycle-discounts' ),
			'icon'    => 'randomize',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'How many products to randomly select daily', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( '5-10: Daily deals, 20-50: Surprise sales', 'smart-cycle-discounts' ),
					__( 'Selection refreshes automatically each day at midnight', 'smart-cycle-discounts' ),
					__( 'Respects category filters if any are set', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Changes daily for urgency and discovery', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Selecting more products than you have in stock', 'smart-cycle-discounts' ),
					__( 'Not setting category filters - may pick premium items', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get conditions help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_conditions_topic() {
		return array(
			'title'   => __( 'Smart Selection Conditions', 'smart-cycle-discounts' ),
			'icon'    => 'filter',
			'type'    => 'examples',
			'content' => array(
				'quick_tip' => __( 'Auto-filter products by their properties', 'smart-cycle-discounts' ),
				'examples'  => array(
					array(
						'label' => __( 'Common Patterns', 'smart-cycle-discounts' ),
						'type'  => 'info',
						'items' => array(
							__( 'Price > $50: Exclude low-margin', 'smart-cycle-discounts' ),
							__( 'Stock > 50: Target overstock', 'smart-cycle-discounts' ),
						),
					),
				),
				'pro_tip'   => __( 'Combine conditions for precise targeting', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get conditions logic help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_conditions_logic_topic() {
		return array(
			'title'   => __( 'Condition Logic', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'How to combine multiple conditions', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'ALL: Meet every condition (precise targeting)', 'smart-cycle-discounts' ),
					__( 'ANY: Meet at least one condition (broader reach)', 'smart-cycle-discounts' ),
					__( 'Example: Price > $50 AND Stock > 20 (strict)', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get discount type help topic (dynamic based on selection)
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_discount_type_topic() {
		return array(
			'title'   => __( 'Discount Types', 'smart-cycle-discounts' ),
			'icon'    => 'tag',
			'type'    => 'dynamic_methods',
			'dynamic' => true,
			'content' => array(
				'quick_tip' => __( 'Choose the discount structure that fits your campaign goals', 'smart-cycle-discounts' ),
				'methods'   => array(
					'percentage' => array(
						'icon'  => 'tag',
						'label' => __( 'Percentage', 'smart-cycle-discounts' ),
						'when'  => __( 'Broad campaigns, seasonal sales', 'smart-cycle-discounts' ),
						'pros'  => __( 'Simple, scales with price', 'smart-cycle-discounts' ),
						'cons'  => __( 'Less control on margins', 'smart-cycle-discounts' ),
						'tip'   => __( 'Start with 10-20% and test response', 'smart-cycle-discounts' ),
					),
					'fixed'      => array(
						'icon'  => 'money-alt',
						'label' => __( 'Fixed Amount', 'smart-cycle-discounts' ),
						'when'  => __( 'Specific value promotions', 'smart-cycle-discounts' ),
						'pros'  => __( 'Predictable discount cost', 'smart-cycle-discounts' ),
						'cons'  => __( 'May not scale with product price', 'smart-cycle-discounts' ),
						'tip'   => __( 'Set minimum purchase to protect margins', 'smart-cycle-discounts' ),
					),
					'tiered'     => array(
						'icon'  => 'chart-line',
						'label' => __( 'Volume/Tiered', 'smart-cycle-discounts' ),
						'when'  => __( 'Encourage bulk purchases', 'smart-cycle-discounts' ),
						'pros'  => __( 'Rewards quantity, increases order value', 'smart-cycle-discounts' ),
						'cons'  => __( 'More complex setup', 'smart-cycle-discounts' ),
						'tip'   => __( 'Set thresholds 20-30% above average order', 'smart-cycle-discounts' ),
					),
					'bogo'       => array(
						'icon'  => 'products',
						'label' => __( 'Buy One Get One (BOGO)', 'smart-cycle-discounts' ),
						'when'  => __( 'Drive volume, clear inventory', 'smart-cycle-discounts' ),
						'pros'  => __( 'Psychologically compelling', 'smart-cycle-discounts' ),
						'cons'  => __( 'Can reduce perceived value', 'smart-cycle-discounts' ),
						'tip'   => __( 'Buy 2 Get 1 at 50% is safer than full free', 'smart-cycle-discounts' ),
					),
					'spend_threshold' => array(
						'icon'  => 'money',
						'label' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
						'when'  => __( 'Increase average order value', 'smart-cycle-discounts' ),
						'pros'  => __( 'Encourages larger purchases', 'smart-cycle-discounts' ),
						'cons'  => __( 'Threshold too high discourages', 'smart-cycle-discounts' ),
						'tip'   => __( 'Set threshold slightly above average order', 'smart-cycle-discounts' ),
					),
				),
			),
		);
	}

	/**
	 * Get discount value help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_discount_value_topic() {
		return array(
			'title'   => __( 'Discount Value', 'smart-cycle-discounts' ),
			'icon'    => 'money-alt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Set discount amount carefully to protect profit margins', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Start conservative: 10-20% for testing', 'smart-cycle-discounts' ),
					__( 'Know your margins: Never discount below cost', 'smart-cycle-discounts' ),
					__( 'Avoid 50%+ frequently: Trains customers to wait', 'smart-cycle-discounts' ),
					__( 'Round numbers (10%, 15%, 20%) are easier to understand', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Test different values and track conversion rates', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Setting discount without checking product margins', 'smart-cycle-discounts' ),
					__( 'Using odd numbers like 17% or 23% - confuses customers', 'smart-cycle-discounts' ),
					__( 'Launching at 50%+ as first discount - no room to escalate', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get tiers help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_tiers_topic() {
		return array(
			'title'   => __( 'Volume Tiers', 'smart-cycle-discounts' ),
			'icon'    => 'chart-line',
			'type'    => 'examples',
			'content' => array(
				'quick_tip' => __( 'Create quantity-based discount tiers to reward bulk purchases', 'smart-cycle-discounts' ),
				'examples'  => array(
					array(
						'label' => __( 'Example Tier Structure', 'smart-cycle-discounts' ),
						'type'  => 'info',
						'items' => array(
							__( '3-4 items: 10% discount', 'smart-cycle-discounts' ),
							__( '5-9 items: 15% discount', 'smart-cycle-discounts' ),
							__( '10+ items: 20% discount', 'smart-cycle-discounts' ),
						),
					),
				),
				'pro_tip'   => __( 'Space tiers to encourage stepping up to next level', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get BOGO config help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_bogo_config_topic() {
		return array(
			'title'   => __( 'BOGO Configuration', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'examples',
			'content' => array(
				'quick_tip' => __( 'Configure "Buy X Get Y" promotional structure', 'smart-cycle-discounts' ),
				'examples'  => array(
					array(
						'label' => __( 'Popular BOGO Patterns', 'smart-cycle-discounts' ),
						'type'  => 'info',
						'items' => array(
							__( 'Buy 1 Get 1 Free (100% off)', 'smart-cycle-discounts' ),
							__( 'Buy 2 Get 1 at 50% (safer for margins)', 'smart-cycle-discounts' ),
							__( 'Buy 3 Get 1 Free (volume incentive)', 'smart-cycle-discounts' ),
						),
					),
				),
				'pro_tip'   => __( 'Partial discounts (50%) are safer than full free items', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get spend amount help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_spend_amount_topic() {
		return array(
			'title'   => __( 'Spend Threshold', 'smart-cycle-discounts' ),
			'icon'    => 'money',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Set minimum spend to unlock the discount', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Set 20-30% above average order value', 'smart-cycle-discounts' ),
					__( 'Round to psychological price points ($50, $100)', 'smart-cycle-discounts' ),
					__( 'Test and adjust based on conversion data', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Too high discourages, too low doesn\'t increase order value', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Setting threshold just $5-10 above current average - not motivating enough', 'smart-cycle-discounts' ),
					__( 'Using odd amounts like $47.83 - use round numbers for clarity', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get start date help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_start_date_topic() {
		return array(
			'title'   => __( 'Campaign Start Date', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose when the campaign should begin (midnight in your timezone)', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Leave empty to start immediately', 'smart-cycle-discounts' ),
					__( 'Launch 1-2 hours before peak traffic', 'smart-cycle-discounts' ),
					__( 'Monday-Thursday best for regular campaigns', 'smart-cycle-discounts' ),
					__( 'Friday for weekend kickoff promotions', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => sprintf(
					/* translators: %s: timezone setting */
					__( 'Your timezone: %s', 'smart-cycle-discounts' ),
					wp_timezone_string()
				),
				'common_mistakes'  => array(
					__( 'Forgetting to announce campaign before start date', 'smart-cycle-discounts' ),
					__( 'Starting during low-traffic hours (2am-6am)', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get end date help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_end_date_topic() {
		return array(
			'title'   => __( 'Campaign End Date', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose when the campaign should end (11:59 PM in your timezone)', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Leave empty for ongoing campaigns', 'smart-cycle-discounts' ),
					__( 'Flash sales: 2-24 hours', 'smart-cycle-discounts' ),
					__( 'Seasonal: 1-4 weeks', 'smart-cycle-discounts' ),
					__( 'Clearance: Until stock depletes', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Set end date for urgency, leave empty for always-on campaigns', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Forgetting to set end date - campaign runs forever accidentally', 'smart-cycle-discounts' ),
					__( 'Setting flash sales too short (<6 hours) - not enough time to generate traffic', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get recurring type help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_recurring_type_topic() {
		return array(
			'title'   => __( 'Recurring Schedule', 'smart-cycle-discounts' ),
			'icon'    => 'backup',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Set up automatic recurring campaigns', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Daily: Flash deals, daily specials', 'smart-cycle-discounts' ),
					__( 'Weekly: Weekend sales, weekly promotions', 'smart-cycle-discounts' ),
					__( 'Monthly: Monthly member discounts', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Recurring campaigns save time on regular promotions', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Using daily recurrence for deep discounts - trains customers to expect it', 'smart-cycle-discounts' ),
					__( 'Not monitoring recurring campaigns - they keep running even when unprofitable', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get recurring days help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_recurring_days_topic() {
		return array(
			'title'   => __( 'Recurring Days', 'smart-cycle-discounts' ),
			'icon'    => 'calendar-alt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Select which days the campaign should run', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Weekdays: Target business customers', 'smart-cycle-discounts' ),
					__( 'Weekends: Target leisure shoppers', 'smart-cycle-discounts' ),
					__( 'Specific days: Flash Friday, Monday Madness', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Match recurring days to your peak traffic patterns', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Selecting all 7 days - defeats the purpose of creating urgency', 'smart-cycle-discounts' ),
					__( 'Not checking analytics to see which days have highest conversion rates', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get recurring start help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_recurring_start_topic() {
		return array(
			'title'   => __( 'Recurring Period Start', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'When should recurring start (leave empty for immediate)', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Empty: Starts next occurrence', 'smart-cycle-discounts' ),
					__( 'Future date: Schedule in advance', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Use start date to align with business calendar', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Setting start date in the past - campaign won\'t run until next cycle', 'smart-cycle-discounts' ),
					__( 'Scheduling too far in advance without testing first', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get recurring end help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_recurring_end_topic() {
		return array(
			'title'   => __( 'Recurring Period End', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'When should recurring stop (leave empty for indefinite)', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Empty: Runs indefinitely', 'smart-cycle-discounts' ),
					__( 'End date: Auto-stops after date', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Set end date for seasonal recurring campaigns', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Leaving end date empty for test campaigns - they run forever', 'smart-cycle-discounts' ),
					__( 'Setting end date during active promotion period instead of after', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * ========================================================================
	 * CARD-LEVEL HELP TOPICS
	 * ========================================================================
	 * These topics provide context about entire card sections, focusing on
	 * WHY the section exists and WHEN to use different options.
	 */

	/**
	 * Get Campaign Details card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_campaign_details_topic() {
		return array(
			'title'   => __( 'Campaign Details', 'smart-cycle-discounts' ),
			'icon'    => 'edit',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'This section captures the essential information that identifies your campaign', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Name: Helps you quickly identify the campaign in your campaign list', 'smart-cycle-discounts' ),
					__( 'Description: Internal notes about campaign goals and strategy', 'smart-cycle-discounts' ),
					__( 'These fields are for your reference only - customers won\'t see them', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use consistent naming conventions to organize campaigns by season, product line, or discount type', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Campaign Priority card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_campaign_priority_topic() {
		return array(
			'title'   => __( 'Campaign Priority', 'smart-cycle-discounts' ),
			'icon'    => 'sort',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Priority determines which discount applies when multiple campaigns target the same products', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Higher priority (5) = stronger - wins conflicts with lower priority campaigns', 'smart-cycle-discounts' ),
					__( 'Default (3) = standard priority for most campaigns', 'smart-cycle-discounts' ),
					__( 'Lower priority (1) = weaker - allows other campaigns to override', 'smart-cycle-discounts' ),
					__( 'Only matters when campaigns overlap - otherwise priority has no effect', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use priority 5 for exclusive sales, priority 3 for regular promotions, and priority 1 for fallback discounts', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Category Selection card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_category_selection_topic() {
		return array(
			'title'   => __( 'Product Categories', 'smart-cycle-discounts' ),
			'icon'    => 'category',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Narrow down which products are eligible for the discount by selecting specific categories', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Leave empty to include all categories in your store', 'smart-cycle-discounts' ),
					__( 'Select one or more categories to limit the discount scope', 'smart-cycle-discounts' ),
					__( 'Works together with the product selection method below', 'smart-cycle-discounts' ),
					__( 'Useful for department-specific or seasonal promotions', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Combine category filtering with specific product selection for precise campaign targeting', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Product Selection card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_product_selection_topic() {
		return array(
			'title'   => __( 'Product Selection Methods', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose how you want to select which products receive the discount', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All Products: Store-wide sale - simplest option for general promotions', 'smart-cycle-discounts' ),
					__( 'Specific Products: Hand-pick exact products - best for targeted campaigns', 'smart-cycle-discounts' ),
					__( 'Random Selection: Automatically choose X products - great for "mystery sales"', 'smart-cycle-discounts' ),
					__( 'Selection respects categories chosen above if any were selected', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use specific product selection with Advanced Filters below for maximum control over campaign targeting', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Advanced Filters card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_advanced_filters_topic() {
		return array(
			'title'   => __( 'Advanced Filters', 'smart-cycle-discounts' ),
			'icon'    => 'filter',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Add sophisticated conditions to automatically include or exclude products based on attributes', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Only use filters when you need precise product targeting', 'smart-cycle-discounts' ),
					__( 'Price filters: Target products in specific price ranges', 'smart-cycle-discounts' ),
					__( 'Stock filters: Exclude low-stock items or prioritize overstocked products', 'smart-cycle-discounts' ),
					__( 'Tag filters: Use product tags for flexible campaign organization', 'smart-cycle-discounts' ),
					__( 'Filters work with your selection method above to refine product list', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'PRO FEATURE: Combine multiple filters with AND/OR logic for complex targeting like "Electronics under $100 with high stock"', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Discount Type card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_discount_type_topic() {
		return array(
			'title'   => __( 'Discount Type Strategy', 'smart-cycle-discounts' ),
			'icon'    => 'tag',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Your discount type determines how customers will perceive and respond to your promotion', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Percentage: Best for predictable margins (20% off always protects profit ratios)', 'smart-cycle-discounts' ),
					__( 'Fixed Amount: Great for high-value items (Save $50 sounds bigger on $200 product)', 'smart-cycle-discounts' ),
					__( 'BOGO: Drives volume and clears inventory while maintaining perceived value', 'smart-cycle-discounts' ),
					__( 'Tiered: Increases average order value by rewarding larger purchases', 'smart-cycle-discounts' ),
					__( 'Spend Threshold: Guarantees minimum order value before discount applies', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Match discount type to your goal: percentage for margin protection, BOGO for volume, tiered for AOV increase', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Discount Value card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_discount_value_topic() {
		return array(
			'title'   => __( 'Discount Configuration', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Configure the specific values and conditions for your selected discount type', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Fields shown here change based on the discount type you selected', 'smart-cycle-discounts' ),
					__( 'Percentage: Enter the discount percentage (10 = 10% off)', 'smart-cycle-discounts' ),
					__( 'Fixed Amount: Enter the dollar amount to subtract from price', 'smart-cycle-discounts' ),
					__( 'BOGO: Configure buy/get quantities and discount on "get" items', 'smart-cycle-discounts' ),
					__( 'Tiered: Set quantity breakpoints and corresponding discount levels', 'smart-cycle-discounts' ),
					__( 'Spend Threshold: Define minimum spend required for discount', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Test your discount values with real product prices to ensure profitability before launching', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Badge Display card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_badge_display_topic() {
		return array(
			'title'   => __( 'Badge Display Settings', 'smart-cycle-discounts' ),
			'icon'    => 'tag',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Control how your discount is visually promoted on product pages and listings', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Badges are not required - prices will still reflect the discount', 'smart-cycle-discounts' ),
					__( 'Badge Text: Customize the label shown on products (e.g., "20% OFF", "SALE")', 'smart-cycle-discounts' ),
					__( 'Badge Position: Choose where badges appear on product images', 'smart-cycle-discounts' ),
					__( 'Badge Style: Select colors and styling to match your brand or promotion urgency', 'smart-cycle-discounts' ),
					__( 'Badges increase visibility and can improve conversion rates', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use red badges for urgency (flash sales), blue for value messaging, and green for eco/sustainable promotions', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Discount Rules card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_discount_rules_topic() {
		return array(
			'title'   => __( 'Advanced Discount Rules', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Fine-tune your discount behavior to protect margins and control customer experience', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: These rules add guardrails but aren\'t required for basic campaigns', 'smart-cycle-discounts' ),
					__( 'Minimum Price: Prevent discounts from dropping prices below your floor price', 'smart-cycle-discounts' ),
					__( 'Maximum Discount: Cap the total discount amount per product to protect margins', 'smart-cycle-discounts' ),
					__( 'Rounding Rules: Control how final prices are rounded (e.g., $19.99 vs $20.00)', 'smart-cycle-discounts' ),
					__( 'Usage Limits: Restrict how many times each customer can use the discount', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Always set minimum price rules on high-discount campaigns to avoid accidentally selling at a loss', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Duration Presets card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_duration_presets_topic() {
		return array(
			'title'   => __( 'Quick Duration Setup', 'smart-cycle-discounts' ),
			'icon'    => 'clock',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose a common campaign duration with a single click, or customize the schedule below', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( '24 Hours: Perfect for flash sales creating urgency', 'smart-cycle-discounts' ),
					__( '3 Days: Weekend promotions (Friday-Sunday)', 'smart-cycle-discounts' ),
					__( '1 Week: Standard promotional period balancing urgency and reach', 'smart-cycle-discounts' ),
					__( '2 Weeks: Extended campaigns for major sales events', 'smart-cycle-discounts' ),
					__( '1 Month: Long-running promotions or seasonal campaigns', 'smart-cycle-discounts' ),
					__( 'Presets are shortcuts - you can still customize dates/times below', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Match duration to customer decision-making speed: short for impulse buys, longer for considered purchases', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Schedule Config card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_schedule_config_topic() {
		return array(
			'title'   => __( 'Schedule Configuration', 'smart-cycle-discounts' ),
			'icon'    => 'calendar-alt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Control exactly when your campaign starts and stops to align with your marketing calendar', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Start Immediately: Campaign activates as soon as you save (or at scheduled time)', 'smart-cycle-discounts' ),
					__( 'Scheduled Start: Set a future date/time for automatic activation', 'smart-cycle-discounts' ),
					__( 'End Date Optional: Leave empty for indefinite campaigns you\'ll manually stop', 'smart-cycle-discounts' ),
					__( 'Timezone: All times use your WordPress site timezone setting', 'smart-cycle-discounts' ),
					__( 'Precision: Use specific times (10:00 AM) for coordinated email sends', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Schedule campaigns to start during peak traffic hours and end on Sunday evenings to maximize exposure', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Recurring Schedule card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_recurring_schedule_topic() {
		return array(
			'title'   => __( 'Recurring Campaign Schedule', 'smart-cycle-discounts' ),
			'icon'    => 'backup',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Set up your campaign to automatically repeat on a regular schedule without manual intervention', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Only use recurring for campaigns you want to repeat automatically', 'smart-cycle-discounts' ),
					__( 'Daily: Repeat every day (or every X days) - good for limited-time daily deals', 'smart-cycle-discounts' ),
					__( 'Weekly: Repeat specific days each week - perfect for "Weekend Sales"', 'smart-cycle-discounts' ),
					__( 'Monthly: Repeat on specific dates each month - ideal for "First Friday Sales"', 'smart-cycle-discounts' ),
					__( 'Duration: Each occurrence uses the same campaign duration from your schedule above', 'smart-cycle-discounts' ),
					__( 'PRO FEATURE: Advanced recurrence patterns for sophisticated promotional calendars', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Keep recurring periods under 6 months to avoid issues with product catalog changes', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Launch Options card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_launch_options_topic() {
		return array(
			'title'   => __( 'Campaign Launch Options', 'smart-cycle-discounts' ),
			'icon'    => 'controls-play',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose whether to activate your campaign immediately or save it as a draft for later', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Launch Campaign: Makes the discount live immediately (or at scheduled time)', 'smart-cycle-discounts' ),
					__( 'Save as Draft: Stores configuration without activating - useful for review or future use', 'smart-cycle-discounts' ),
					__( 'Edit Mode: If editing existing campaign, you can change status between active/draft', 'smart-cycle-discounts' ),
					__( 'Scheduled Campaigns: Will show "scheduled" status until start time arrives', 'smart-cycle-discounts' ),
					__( 'Draft campaigns can be activated anytime from the campaigns list', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use draft status to prepare campaigns in advance, then activate when coordinating with marketing emails or ads', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * OPTION-LEVEL HELP TOPICS - PRODUCT SELECTION
	 * ========================================================================
	 * These topics compare options within a choice, focusing on when to
	 * choose THIS option over the OTHERS.
	 */

	/**
	 * Get All Products option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_product_all_topic() {
		return array(
			'title'   => __( 'All Products', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Entire catalog gets discounted automatically', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Store-wide holiday sales, flash sales, clearance events', 'smart-cycle-discounts' ),
					__( 'Warning: All items get same discount (including premium products)', 'smart-cycle-discounts' ),
					__( 'Tip: Combine with category filters to exclude certain products', 'smart-cycle-discounts' ),
					__( 'Simplest option - no product management needed', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Forgetting to exclude loss leaders or premium items', 'smart-cycle-discounts' ),
					__( 'Running store-wide sales too frequently - devalues brand', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get Specific Products option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_product_specific_topic() {
		return array(
			'title'   => __( 'Specific Products', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Hand-pick exact products for maximum control', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Promoting bestsellers, testing strategies, curated selections', 'smart-cycle-discounts' ),
					__( 'Warning: Impractical for 100+ products', 'smart-cycle-discounts' ),
					__( 'Tip: Start with 5-10, check margins first', 'smart-cycle-discounts' ),
					__( 'Precision targeting - select exactly what you want to discount', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Selecting slow-moving items thinking discount will help', 'smart-cycle-discounts' ),
					__( 'Not updating list when products go out of stock', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get Random Products option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_product_random_topic() {
		return array(
			'title'   => __( 'Random Selection', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Randomly picks products daily for discovery', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Daily Deals, Mystery Sales, product discovery', 'smart-cycle-discounts' ),
					__( 'Warning: Unpredictable - may pick low-margin items', 'smart-cycle-discounts' ),
					__( 'Tip: Use stock/category filters to limit pool', 'smart-cycle-discounts' ),
					__( 'Creates urgency - selection changes daily at midnight', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Not excluding low-margin or premium products', 'smart-cycle-discounts' ),
					__( 'Setting count higher than available inventory', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * ========================================================================
	 * OPTION-LEVEL HELP TOPICS - DISCOUNT TYPES
	 * ========================================================================
	 */

	/**
	 * Get Percentage Discount option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_discount_percentage_topic() {
		return array(
			'title'   => __( 'Percentage', 'smart-cycle-discounts' ),
			'icon'    => 'tag',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'X% off - maintains margins, scales with price', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Store-wide sales, seasonal promotions, clearance', 'smart-cycle-discounts' ),
					__( 'Warning: Low impact on cheap items (20% off $10 = $2 only)', 'smart-cycle-discounts' ),
					__( 'Tip: 25% converts 2x better than 20% psychologically', 'smart-cycle-discounts' ),
					__( 'Easy to calculate and understand for customers', 'smart-cycle-discounts' ),
					__( 'Industry: Fashion retailers average 15-30% for seasonal sales', 'smart-cycle-discounts' ),
					__( 'Example: $100 item at 20% = $80 (maintains 20% margin ratio)', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Going too low (5%) - customers don\'t notice', 'smart-cycle-discounts' ),
					__( 'Using on low-priced items where fixed is better', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get Fixed Amount Discount option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_discount_fixed_topic() {
		return array(
			'title'   => __( 'Fixed Amount', 'smart-cycle-discounts' ),
			'icon'    => 'receipt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Flat $X off - best for expensive items', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: High-ticket items ($100+), first purchase incentives', 'smart-cycle-discounts' ),
					__( 'Warning: Destroys margins on cheap items ($50 off $60 = 83%)', 'smart-cycle-discounts' ),
					__( 'Tip: "$50 off" beats "25% off" on $200+ items psychologically', 'smart-cycle-discounts' ),
					__( 'Set product price minimums to protect margins', 'smart-cycle-discounts' ),
					__( 'Industry: Electronics stores use $25-$100 off for appliances', 'smart-cycle-discounts' ),
					__( 'Example: $250 item - "$50 off" feels bigger than "20% off"', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Applying to low-priced products - causes losses', 'smart-cycle-discounts' ),
					__( 'Not setting minimum purchase requirements', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get Tiered Discount option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_discount_tiered_topic() {
		return array(
			'title'   => __( 'Tiered', 'smart-cycle-discounts' ),
			'icon'    => 'chart-line',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Better discounts for buying more quantity', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: B2B wholesale, consumables, subscription tiers', 'smart-cycle-discounts' ),
					__( 'Warning: Customers may wait to bulk buy, reducing order frequency', 'smart-cycle-discounts' ),
					__( 'Tip: "Add 2 more for 15% off" boosts conversions 30%', 'smart-cycle-discounts' ),
					__( 'Increases average order value by rewarding larger purchases', 'smart-cycle-discounts' ),
					__( 'Industry: Office supplies use 3-tier (5/10/25+ items)', 'smart-cycle-discounts' ),
					__( 'Example: Buy 3=10% off, 6=15% off, 12=25% off (clear incentive)', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Setting tiers too close together (5 items vs 6 items)', 'smart-cycle-discounts' ),
					__( 'Making first tier unreachable (starting at 50 items)', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get BOGO Discount option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_discount_bogo_topic() {
		return array(
			'title'   => __( 'BOGO', 'smart-cycle-discounts' ),
			'icon'    => 'cart',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Buy X, get Y discounted - drives volume fast', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Urgent clearance, fashion/seasonal inventory', 'smart-cycle-discounts' ),
					__( 'Warning: Cuts margins significantly - calculate break-even first', 'smart-cycle-discounts' ),
					__( 'Tip: Clears stock 2x faster than 25% off at similar margin cost', 'smart-cycle-discounts' ),
					__( 'Highly effective for moving inventory quickly', 'smart-cycle-discounts' ),
					__( 'Industry: Fashion uses B2G1 50% off for end-of-season clearance', 'smart-cycle-discounts' ),
					__( 'Example: Buy 2 Get 1 Free = 33% off 3 items (feels better than "33% off")', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Offering 100% free without checking if you can afford it', 'smart-cycle-discounts' ),
					__( 'Not clearly communicating the quantities (Buy 2 Get 1)', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get Spend Threshold Discount option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_discount_spend_threshold_topic() {
		return array(
			'title'   => __( 'Spend Threshold', 'smart-cycle-discounts' ),
			'icon'    => 'receipt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Require minimum spend to qualify - boosts order value', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Free shipping threshold, AOV optimization', 'smart-cycle-discounts' ),
					__( 'Warning: Too high = cart abandonment. Too low = no impact', 'smart-cycle-discounts' ),
					__( 'Tip: "Spend $50 = Free Ship + 10% Off" boosts AOV 45%', 'smart-cycle-discounts' ),
					__( 'Set threshold 20-30% above current average order value', 'smart-cycle-discounts' ),
					__( 'Industry: E-commerce uses $50-$75 threshold for free shipping tier', 'smart-cycle-discounts' ),
					__( 'Example: AOV $40 → Set $50 threshold = customers add $10 more items', 'smart-cycle-discounts' ),
				),
				'common_mistakes' => array(
					__( 'Setting threshold unrealistically high ($500 when AOV is $50)', 'smart-cycle-discounts' ),
					__( 'Not showing progress bar ("You need $10 more for discount")', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get topic by ID
	 *
	 * PERFORMANCE: Uses lazy loading - only calls the specific topic method needed
	 * instead of instantiating all 40 topics. Reduces memory usage by ~50KB per request.
	 *
	 * @since  1.0.0
	 * @param  string $topic_id Topic identifier.
	 * @return array|null       Topic configuration or null if not found
	 */
	public static function get_topic( $topic_id ) {
		// Map topic IDs to their method names (lazy loading)
		$topic_map = array(
			// Basic Step Topics
			'campaign-name'               => 'get_campaign_name_topic',
			'campaign-description'        => 'get_campaign_description_topic',
			'priority'                    => 'get_priority_topic',
			'card-campaign-details'       => 'get_card_campaign_details_topic',
			'card-campaign-priority'      => 'get_card_campaign_priority_topic',
			// Products Step Topics
			'product-selection-type'      => 'get_product_selection_type_topic',
			'category-ids'                => 'get_category_ids_topic',
			'product-ids'                 => 'get_product_ids_topic',
			'random-count'                => 'get_random_count_topic',
			'conditions'                  => 'get_conditions_topic',
			'conditions-logic'            => 'get_conditions_logic_topic',
			'card-category-selection'     => 'get_card_category_selection_topic',
			'card-product-selection'      => 'get_card_product_selection_topic',
			'card-advanced-filters'       => 'get_card_advanced_filters_topic',
			'option-product-all'          => 'get_option_product_all_topic',
			'option-product-specific'     => 'get_option_product_specific_topic',
			'option-product-random'       => 'get_option_product_random_topic',
			// Discounts Step Topics
			'discount-type'               => 'get_discount_type_topic',
			'discount-value'              => 'get_discount_value_topic',
			'tiers'                       => 'get_tiers_topic',
			'bogo-config'                 => 'get_bogo_config_topic',
			'spend-amount'                => 'get_spend_amount_topic',
			'card-discount-type'          => 'get_card_discount_type_topic',
			'card-discount-value'         => 'get_card_discount_value_topic',
			'card-badge-display'          => 'get_card_badge_display_topic',
			'card-discount-rules'         => 'get_card_discount_rules_topic',
			'option-discount-percentage'  => 'get_option_discount_percentage_topic',
			'option-discount-fixed'       => 'get_option_discount_fixed_topic',
			'option-discount-tiered'      => 'get_option_discount_tiered_topic',
			'option-discount-bogo'        => 'get_option_discount_bogo_topic',
			'option-discount-spend-threshold' => 'get_option_discount_spend_threshold_topic',
			// Schedule Step Topics
			'start-date'                  => 'get_start_date_topic',
			'end-date'                    => 'get_end_date_topic',
			'recurring-type'              => 'get_recurring_type_topic',
			'recurring-days'              => 'get_recurring_days_topic',
			'recurring-start'             => 'get_recurring_start_topic',
			'recurring-end'               => 'get_recurring_end_topic',
			'card-duration-presets'       => 'get_card_duration_presets_topic',
			'card-schedule-config'        => 'get_card_schedule_config_topic',
			'card-recurring-schedule'     => 'get_card_recurring_schedule_topic',
			// Review Step Topics
			'card-launch-options'         => 'get_card_launch_options_topic',
		);

		// Check if topic exists in map
		if ( ! isset( $topic_map[ $topic_id ] ) ) {
			return null;
		}

		// Lazy load: Only call the specific method for this topic
		$method = $topic_map[ $topic_id ];
		if ( method_exists( __CLASS__, $method ) ) {
			return self::$method();
		}

		return null;
	}
}
