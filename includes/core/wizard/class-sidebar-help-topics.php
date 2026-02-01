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
class WSSCD_Sidebar_Help_Topics {

	/**
	 * ========================================================================
	 * BASIC STEP - FIELD-LEVEL TOPICS
	 * ========================================================================
	 */

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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'The campaign name is your internal identifier - customers never see it', 'smart-cycle-discounts' ),
					__( 'Used in your campaign list, reports, and analytics to identify this promotion', 'smart-cycle-discounts' ),
					__( 'Good names make it easy to find and analyze campaigns later', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( '"Summer 2025 - Electronics 20%" - clear season, category, and discount', 'smart-cycle-discounts' ),
					__( '"Flash Sale - Jan 15 Weekend" - identifies timing and type', 'smart-cycle-discounts' ),
					__( '"BOGO Shoes - Clearance Q1" - shows discount type and purpose', 'smart-cycle-discounts' ),
					__( '"VIP Members - Early Access" - identifies target audience', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Avoid generic names like "Sale1" or "Test" - you\'ll have many campaigns eventually', 'smart-cycle-discounts' ),
					__( 'Don\'t skip dates for seasonal campaigns - "Summer Sale" is unclear after 2 years', 'smart-cycle-discounts' ),
					__( 'Never reuse names - causes confusion in reports and makes tracking impossible', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Format suggestion: [Season/Event] [Year] - [Category] [Discount%]', 'smart-cycle-discounts' ),
					__( 'Include the discount amount - easier to compare performance later', 'smart-cycle-discounts' ),
					__( 'Add "TEST" prefix during development, remove when going live', 'smart-cycle-discounts' ),
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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Internal documentation for you and your team - never shown to customers', 'smart-cycle-discounts' ),
					__( 'Appears in campaign details view and export reports', 'smart-cycle-discounts' ),
					__( 'Perfect for recording business context that field values can\'t capture', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Document the business goal: "Clear winter inventory before spring arrivals"', 'smart-cycle-discounts' ),
					__( 'Record approval status: "Approved by Marketing - Max budget $5,000"', 'smart-cycle-discounts' ),
					__( 'Note margin constraints: "DO NOT exceed 25% - breaks even at 30%"', 'smart-cycle-discounts' ),
					__( 'Track coordination: "Matches email blast scheduled for 10am Monday"', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Empty descriptions cause confusion when revisiting campaigns months later', 'smart-cycle-discounts' ),
					__( 'Don\'t include customer-facing copy here - this is internal only', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Include: Goal, Target audience, Budget/margin limits, Approval info', 'smart-cycle-discounts' ),
					__( 'Future you will thank present you for detailed notes', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'When a product matches multiple campaigns, the highest priority wins. Only matters for overlapping campaigns.', 'smart-cycle-discounts' ),
				'scale'     => array(
					5 => array(
						'label'   => __( 'Critical (5)', 'smart-cycle-discounts' ),
						'use'     => __( 'VIP exclusive sales, time-sensitive flash deals, special member pricing', 'smart-cycle-discounts' ),
						'stars'   => 5,
					),
					4 => array(
						'label'   => __( 'High (4)', 'smart-cycle-discounts' ),
						'use'     => __( 'Major seasonal sales, holiday promotions, clearance events', 'smart-cycle-discounts' ),
						'stars'   => 4,
					),
					3 => array(
						'label'   => __( 'Normal (3)', 'smart-cycle-discounts' ),
						'use'     => __( 'Standard promotions, regular weekly deals (recommended default)', 'smart-cycle-discounts' ),
						'stars'   => 3,
						'default' => true,
					),
					2 => array(
						'label'   => __( 'Low (2)', 'smart-cycle-discounts' ),
						'use'     => __( 'Secondary campaigns, category-specific deals', 'smart-cycle-discounts' ),
						'stars'   => 2,
					),
					1 => array(
						'label'   => __( 'Fallback (1)', 'smart-cycle-discounts' ),
						'use'     => __( 'Always-on background discounts, loyalty rewards, baseline pricing', 'smart-cycle-discounts' ),
						'stars'   => 1,
					),
				),
				'pro_tip'   => __( 'Equal priority? The first-created campaign wins. Use unique priorities to avoid surprises.', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * PRODUCTS STEP - FIELD-LEVEL TOPICS
	 * ========================================================================
	 */

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
				'quick_tip' => __( 'Choose how products qualify for this discount. Each method suits different campaign goals.', 'smart-cycle-discounts' ),
				'methods'   => array(
					'all_products'       => array(
						'icon'  => 'cart',
						'label' => __( 'All Products', 'smart-cycle-discounts' ),
						'desc'  => __( 'Every product in your store (or selected categories) gets the discount', 'smart-cycle-discounts' ),
						'when'  => __( 'Store-wide sales, Black Friday, seasonal clearance, anniversary sales', 'smart-cycle-discounts' ),
					),
					'specific_products'  => array(
						'icon'  => 'marker',
						'label' => __( 'Specific Products', 'smart-cycle-discounts' ),
						'desc'  => __( 'Hand-pick exact products - full control over what\'s discounted', 'smart-cycle-discounts' ),
						'when'  => __( 'Featured product promotions, clearance of specific items, curated collections', 'smart-cycle-discounts' ),
					),
					'random_products'    => array(
						'icon'  => 'randomize',
						'label' => __( 'Random Selection', 'smart-cycle-discounts' ),
						'desc'  => __( 'System randomly picks X products daily - creates discovery and urgency', 'smart-cycle-discounts' ),
						'when'  => __( 'Daily deals, mystery sales, product discovery campaigns, gamification', 'smart-cycle-discounts' ),
					),
					'smart_selection'    => array(
						'icon'  => 'filter',
						'label' => __( 'Smart Selection', 'smart-cycle-discounts' ),
						'desc'  => __( 'Auto-filter products based on rules (price, stock, attributes)', 'smart-cycle-discounts' ),
						'when'  => __( 'Inventory management, margin-based pricing, dynamic promotions', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Category Filtering', 'smart-cycle-discounts' ),
			'icon'    => 'category',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Categories act as a pre-filter - only products in these categories can qualify', 'smart-cycle-discounts' ),
					__( 'Leave empty to include ALL categories in your store', 'smart-cycle-discounts' ),
					__( 'Selecting a parent category automatically includes all subcategories', 'smart-cycle-discounts' ),
					__( 'Works together with product selection method below', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Department sales: Select "Women\'s Clothing" for a targeted promotion', 'smart-cycle-discounts' ),
					__( 'Seasonal clearance: Select "Winter Gear" to clear seasonal inventory', 'smart-cycle-discounts' ),
					__( 'Cross-selling: Select multiple related categories for bundle promotions', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Products in multiple categories only need to match ONE selected category', 'smart-cycle-discounts' ),
					__( 'Verify subcategory products are included when selecting parent category', 'smart-cycle-discounts' ),
					__( 'Too many unrelated categories dilutes your campaign message', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Start with 1-3 related categories for focused campaigns', 'smart-cycle-discounts' ),
					__( 'Check product count after selection to verify scope', 'smart-cycle-discounts' ),
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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Search by product name, SKU, or category to find products', 'smart-cycle-discounts' ),
					__( 'Results show stock status, current price, and sale indicators', 'smart-cycle-discounts' ),
					__( 'Click products to add, click again to remove from selection', 'smart-cycle-discounts' ),
					__( 'Selected products appear with count at top of list', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Clearance: Select specific overstocked or end-of-life products', 'smart-cycle-discounts' ),
					__( 'Featured: Promote your best-sellers or new arrivals', 'smart-cycle-discounts' ),
					__( 'Testing: Start with 5-10 products to test discount effectiveness', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Check stock levels - don\'t promote out-of-stock items', 'smart-cycle-discounts' ),
					__( 'Products already on sale elsewhere may show unexpected prices', 'smart-cycle-discounts' ),
					__( 'Variable products: discount applies to all variations', 'smart-cycle-discounts' ),
					__( 'Impractical for 100+ products - use categories or smart selection instead', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Use SKU search for faster selection if you know product codes', 'smart-cycle-discounts' ),
					__( 'Review product margins before including in discount campaign', 'smart-cycle-discounts' ),
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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'System randomly picks this many products from your eligible catalog', 'smart-cycle-discounts' ),
					__( 'Selection refreshes automatically at midnight (your site timezone)', 'smart-cycle-discounts' ),
					__( 'Respects category filters if any are set', 'smart-cycle-discounts' ),
					__( 'Only selects products that are in stock', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( '3-5 products: "Deal of the Day" - creates urgency and exclusivity', 'smart-cycle-discounts' ),
					__( '10-20 products: "Daily Deals" section - good variety for browsing', 'smart-cycle-discounts' ),
					__( '30-50 products: "Mystery Sale" - surprise element for returning visitors', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Don\'t set count higher than available in-stock products', 'smart-cycle-discounts' ),
					__( 'Random may pick premium/high-margin items - use category filters to exclude', 'smart-cycle-discounts' ),
					__( 'Customers may see different products on different days', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Start with 5-10 products and increase based on customer engagement', 'smart-cycle-discounts' ),
					__( 'Combine with category filters to control which products can be selected', 'smart-cycle-discounts' ),
					__( 'Consider your catalog size - 10% of products works well', 'smart-cycle-discounts' ),
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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Conditions automatically filter products based on their attributes', 'smart-cycle-discounts' ),
					__( 'Products must meet your conditions to qualify for the discount', 'smart-cycle-discounts' ),
					__( 'Conditions are evaluated in real-time - changes to products auto-update eligibility', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Price > $50: Target higher-value products where discount has bigger impact', 'smart-cycle-discounts' ),
					__( 'Stock > 100: Clear overstocked inventory automatically', 'smart-cycle-discounts' ),
					__( 'Price < $30: Promote impulse-buy items for quick conversions', 'smart-cycle-discounts' ),
					__( 'Days Since Added > 90: Discount older inventory automatically', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Complex conditions may exclude more products than expected - check count', 'smart-cycle-discounts' ),
					__( 'Stock conditions depend on accurate inventory management', 'smart-cycle-discounts' ),
					__( 'Price conditions use regular price, not sale price', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Start with one simple condition, add more after verifying results', 'smart-cycle-discounts' ),
					__( 'Preview which products match before launching campaign', 'smart-cycle-discounts' ),
				),
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
			'title'   => __( 'Condition Logic (AND/OR)', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'examples',
			'content' => array(
				'quick_tip' => __( 'Choose how multiple conditions work together to filter products.', 'smart-cycle-discounts' ),
				'examples'  => array(
					array(
						'label' => __( 'ALL (AND Logic) - Strict', 'smart-cycle-discounts' ),
						'type'  => 'info',
						'items' => array(
							__( 'Product must meet EVERY condition to qualify', 'smart-cycle-discounts' ),
							__( 'Example: Price > $50 AND Stock > 20 = Only expensive items with high stock', 'smart-cycle-discounts' ),
							__( 'Best for: Precise targeting, protecting margins on specific items', 'smart-cycle-discounts' ),
						),
					),
					array(
						'label' => __( 'ANY (OR Logic) - Flexible', 'smart-cycle-discounts' ),
						'type'  => 'success',
						'items' => array(
							__( 'Product qualifies if it meets ANY one condition', 'smart-cycle-discounts' ),
							__( 'Example: Stock > 100 OR Days > 60 = Overstocked OR old items', 'smart-cycle-discounts' ),
							__( 'Best for: Broader reach, clearance campaigns, multiple criteria', 'smart-cycle-discounts' ),
						),
					),
				),
				'pro_tip' => __( 'Use ALL for precise targeting, ANY for broader clearance campaigns.', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * DISCOUNTS STEP - FIELD-LEVEL TOPICS
	 * ========================================================================
	 */

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
				'quick_tip' => __( 'Each discount type creates different customer psychology and affects margins differently.', 'smart-cycle-discounts' ),
				'methods'   => array(
					'percentage'      => array(
						'icon'  => 'tag',
						'label' => __( 'Percentage Off', 'smart-cycle-discounts' ),
						'when'  => __( 'Store-wide sales, seasonal promotions, fashion/apparel', 'smart-cycle-discounts' ),
						'pros'  => __( 'Scales with price, maintains margin ratios, easy to understand', 'smart-cycle-discounts' ),
						'cons'  => __( 'Small savings on cheap items, less impact below $30', 'smart-cycle-discounts' ),
						'tip'   => __( 'Industry standard: 10-15% regular, 20-30% seasonal, 40-50% clearance', 'smart-cycle-discounts' ),
					),
					'fixed'           => array(
						'icon'  => 'money-alt',
						'label' => __( 'Fixed Amount Off', 'smart-cycle-discounts' ),
						'when'  => __( 'High-ticket items ($100+), first-purchase incentives, referral rewards', 'smart-cycle-discounts' ),
						'pros'  => __( 'Clear value proposition, works great on expensive items', 'smart-cycle-discounts' ),
						'cons'  => __( 'Can destroy margins on cheap items, needs min purchase rules', 'smart-cycle-discounts' ),
						'tip'   => __( '"$50 off" beats "20% off" psychologically on items above $200', 'smart-cycle-discounts' ),
					),
					'tiered'          => array(
						'icon'  => 'chart-line',
						'label' => __( 'Volume/Tiered Pricing', 'smart-cycle-discounts' ),
						'when'  => __( 'B2B sales, consumables, wholesale, subscription products', 'smart-cycle-discounts' ),
						'pros'  => __( 'Increases AOV, rewards bulk buying, reduces per-unit costs', 'smart-cycle-discounts' ),
						'cons'  => __( 'Complex to set up, may reduce purchase frequency', 'smart-cycle-discounts' ),
						'tip'   => __( 'Set first tier 20% above current average quantity purchased', 'smart-cycle-discounts' ),
					),
					'bogo'            => array(
						'icon'  => 'products',
						'label' => __( 'Buy One Get One', 'smart-cycle-discounts' ),
						'when'  => __( 'Inventory clearance, fashion/seasonal, impulse categories', 'smart-cycle-discounts' ),
						'pros'  => __( 'Psychologically powerful, moves inventory fast, viral appeal', 'smart-cycle-discounts' ),
						'cons'  => __( 'High margin impact, can reduce perceived value long-term', 'smart-cycle-discounts' ),
						'tip'   => __( 'Buy 2 Get 1 at 50% off is safer than full BOGO Free', 'smart-cycle-discounts' ),
					),
					'spend_threshold' => array(
						'icon'  => 'money',
						'label' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
						'when'  => __( 'Free shipping thresholds, AOV optimization, upselling', 'smart-cycle-discounts' ),
						'pros'  => __( 'Guarantees minimum order value, great for AOV growth', 'smart-cycle-discounts' ),
						'cons'  => __( 'Too high = cart abandonment, too low = no impact', 'smart-cycle-discounts' ),
						'tip'   => __( 'Set threshold 20-30% above current average order value', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Setting Discount Value', 'smart-cycle-discounts' ),
			'icon'    => 'money-alt',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Enter the discount amount - format depends on discount type selected', 'smart-cycle-discounts' ),
					__( 'Percentage: Enter 20 for 20% off (not 0.20)', 'smart-cycle-discounts' ),
					__( 'Fixed: Enter the dollar amount to subtract from price', 'smart-cycle-discounts' ),
					__( 'Discount applies to each eligible product individually', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( '5-10%: Loyalty rewards, first-time buyer incentives', 'smart-cycle-discounts' ),
					__( '15-20%: Standard promotional campaigns, seasonal sales', 'smart-cycle-discounts' ),
					__( '25-35%: Major sales events, holiday promotions', 'smart-cycle-discounts' ),
					__( '40-50%+: Clearance, end-of-season, inventory liquidation', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Know your margins BEFORE setting discount - never sell below cost', 'smart-cycle-discounts' ),
					__( 'Avoid 50%+ frequently - trains customers to wait for deep discounts', 'smart-cycle-discounts' ),
					__( 'Odd numbers (17%, 23%) confuse customers - use 10, 15, 20, 25, etc.', 'smart-cycle-discounts' ),
					__( 'Starting too high leaves no room to escalate during slow periods', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Test with lower discount first, increase if conversion is low', 'smart-cycle-discounts' ),
					__( 'Round to numbers customers easily calculate (10%, 20%, 25%, 50%)', 'smart-cycle-discounts' ),
					__( 'Track conversion rate at different discount levels to find sweet spot', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Volume Tier Configuration', 'smart-cycle-discounts' ),
			'icon'    => 'chart-line',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Create quantity breakpoints with increasing discounts', 'smart-cycle-discounts' ),
					__( 'Customer gets the discount for the highest tier they reach', 'smart-cycle-discounts' ),
					__( 'Tiers encourage customers to buy more to reach next discount level', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Simple 3-tier: Buy 3 = 10% | Buy 6 = 15% | Buy 10+ = 20%', 'smart-cycle-discounts' ),
					__( 'Aggressive clearance: Buy 5 = 20% | Buy 10 = 30% | Buy 20+ = 40%', 'smart-cycle-discounts' ),
					__( 'B2B wholesale: Buy 25 = 10% | Buy 50 = 15% | Buy 100+ = 25%', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Don\'t set tiers too close - "Buy 5" vs "Buy 6" isn\'t motivating', 'smart-cycle-discounts' ),
					__( 'First tier should be achievable - not 50 items for most products', 'smart-cycle-discounts' ),
					__( 'Maximum discount should still protect your margins', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Space tiers so reaching next level feels achievable (2x multiplier works well)', 'smart-cycle-discounts' ),
					__( 'Show "Add X more for Y% off" messaging to encourage tier-ups', 'smart-cycle-discounts' ),
					__( '3-4 tiers is optimal - more creates decision fatigue', 'smart-cycle-discounts' ),
				),
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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( '"Buy X" = How many items customer must purchase at full price', 'smart-cycle-discounts' ),
					__( '"Get Y" = How many additional items receive the discount', 'smart-cycle-discounts' ),
					__( '"At Z%" = Discount on the "Get" items (100% = free)', 'smart-cycle-discounts' ),
					__( 'Customer must add all items to cart - discount applies to cheapest items', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Buy 1 Get 1 Free (B1G1): Classic BOGO, 50% effective discount on 2 items', 'smart-cycle-discounts' ),
					__( 'Buy 2 Get 1 at 50%: Safer - only 16.7% effective discount on 3 items', 'smart-cycle-discounts' ),
					__( 'Buy 3 Get 1 Free: Volume BOGO - 25% effective discount on 4 items', 'smart-cycle-discounts' ),
					__( 'Buy 1 Get 1 at 50%: Half-off second item - 25% effective discount', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'B1G1 Free = 50% margin hit - verify you can afford this', 'smart-cycle-discounts' ),
					__( 'Customers may split orders to maximize free items - consider limits', 'smart-cycle-discounts' ),
					__( 'Frequent BOGO can devalue brand - use strategically', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Start with "Buy 2 Get 1 at 50%" - balances appeal with margin protection', 'smart-cycle-discounts' ),
					__( 'Calculate effective discount: (Get * Discount%) / (Buy + Get) = effective %', 'smart-cycle-discounts' ),
					__( 'Clear inventory? Go aggressive. Protect brand? Use partial discounts.', 'smart-cycle-discounts' ),
				),
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
			'title'   => __( 'Spend Threshold Amount', 'smart-cycle-discounts' ),
			'icon'    => 'money',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Customer must reach this cart subtotal to unlock the discount', 'smart-cycle-discounts' ),
					__( 'Discount applies to eligible products once threshold is met', 'smart-cycle-discounts' ),
					__( 'Progress bar shows customer how close they are to unlocking discount', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Free shipping trigger: "Spend $50, get free shipping + 10% off"', 'smart-cycle-discounts' ),
					__( 'AOV booster: Set threshold 25% above current average order', 'smart-cycle-discounts' ),
					__( 'Tiered thresholds: $50 = 10% | $100 = 15% | $150 = 20%', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Too high threshold = cart abandonment (customers give up)', 'smart-cycle-discounts' ),
					__( 'Too low threshold = no AOV impact (already spending that much)', 'smart-cycle-discounts' ),
					__( 'Test different thresholds to find conversion sweet spot', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Check your current AOV in WooCommerce reports first', 'smart-cycle-discounts' ),
					__( 'Set threshold 20-30% above AOV for optimal lift', 'smart-cycle-discounts' ),
					__( 'Use round numbers ($50, $75, $100) - easier for customers to calculate', 'smart-cycle-discounts' ),
					__( 'Show progress: "Add $12.50 more to save 15%"', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get badge text help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_badge_text_topic() {
		return array(
			'title'   => __( 'Badge Text', 'smart-cycle-discounts' ),
			'icon'    => 'tag',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Badge text appears on product images in your store', 'smart-cycle-discounts' ),
					__( 'Keep it short - 2-4 words maximum for best visibility', 'smart-cycle-discounts' ),
					__( 'Use placeholders like {discount} to show actual discount amount', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( '"SALE" - Simple, classic, universally understood', 'smart-cycle-discounts' ),
					__( '"{discount} OFF" - Shows exact discount (e.g., "20% OFF")', 'smart-cycle-discounts' ),
					__( '"FLASH DEAL" - Creates urgency for time-limited sales', 'smart-cycle-discounts' ),
					__( '"CLEARANCE" - Signals deeper discounts, inventory clearing', 'smart-cycle-discounts' ),
					__( '"LIMITED TIME" - Encourages immediate action', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Long text gets cut off or becomes unreadable on small images', 'smart-cycle-discounts' ),
					__( 'All caps works best for badges - easier to read quickly', 'smart-cycle-discounts' ),
					__( 'Too many badges on one page reduces their impact', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Test how badge looks on both desktop and mobile product images', 'smart-cycle-discounts' ),
					__( 'Match badge messaging to your email/ad campaign copy', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get badge position help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_badge_position_topic() {
		return array(
			'title'   => __( 'Badge Position', 'smart-cycle-discounts' ),
			'icon'    => 'move',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose where the badge appears on product images', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Top-Left: Most common, high visibility, industry standard', 'smart-cycle-discounts' ),
					__( 'Top-Right: Good alternative if theme uses top-left for other badges', 'smart-cycle-discounts' ),
					__( 'Bottom-Left: Less prominent, good for secondary messaging', 'smart-cycle-discounts' ),
					__( 'Bottom-Right: Least common, may conflict with add-to-cart buttons', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Check your theme\'s existing badge placement - use a different corner to avoid overlap', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Placing badge where it covers important product details', 'smart-cycle-discounts' ),
					__( 'Using same position as theme\'s "New" or "Out of Stock" badges', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get usage limit help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_usage_limit_topic() {
		return array(
			'title'   => __( 'Usage Limit Per Customer', 'smart-cycle-discounts' ),
			'icon'    => 'groups',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Limits how many times each customer can use this discount', 'smart-cycle-discounts' ),
					__( 'Tracked by customer account (or email for guests)', 'smart-cycle-discounts' ),
					__( 'Leave empty or 0 for unlimited uses per customer', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( '1 use: First purchase discounts, one-time welcome offers', 'smart-cycle-discounts' ),
					__( '3-5 uses: Limited repeat purchases, controlled promotions', 'smart-cycle-discounts' ),
					__( 'Unlimited: Regular promotions, store-wide sales', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Guest customers tracked by email - may circumvent with different emails', 'smart-cycle-discounts' ),
					__( 'Too restrictive limits may frustrate loyal customers', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Use limits for acquisition campaigns (first purchase)', 'smart-cycle-discounts' ),
					__( 'Leave unlimited for retention campaigns (reward loyalty)', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get user roles help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_user_roles_topic() {
		return array(
			'title'   => __( 'User Role Targeting', 'smart-cycle-discounts' ),
			'icon'    => 'groups',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Restrict which users can see and use this discount based on their WordPress role', 'smart-cycle-discounts' ),
					__( 'All Users: No restriction - everyone sees the discount (default)', 'smart-cycle-discounts' ),
					__( 'Include Only: Only users with selected roles see the discount', 'smart-cycle-discounts' ),
					__( 'Exclude: Users with selected roles are blocked from the discount', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Wholesaler pricing: Include only "Wholesaler" role for B2B discounts', 'smart-cycle-discounts' ),
					__( 'Member exclusives: Include only "Subscriber" or "VIP Member" roles', 'smart-cycle-discounts' ),
					__( 'Retail only: Exclude "Wholesaler" to keep wholesale pricing separate', 'smart-cycle-discounts' ),
					__( 'Staff discounts: Include only "Shop Manager" or "Employee" roles', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Guest (non-logged-in) users have no role - they won\'t match any include filter', 'smart-cycle-discounts' ),
					__( 'Users with multiple roles only need ONE matching role for include/exclude', 'smart-cycle-discounts' ),
					__( 'Test with a user account that has the target role before going live', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Use "Include" for exclusive member pricing tiers', 'smart-cycle-discounts' ),
					__( 'Use "Exclude" when most users should see the discount except specific groups', 'smart-cycle-discounts' ),
					__( 'Combine with other restrictions like usage limits for maximum control', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get card user roles help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_card_user_roles_topic() {
		return array(
			'title'   => __( 'User Role Targeting', 'smart-cycle-discounts' ),
			'icon'    => 'groups',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Control which user roles can access this discount - perfect for B2B, member-only, or staff pricing.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All Users: Default - no role restrictions, everyone sees the discount', 'smart-cycle-discounts' ),
					__( 'Include Only: Whitelist specific roles (e.g., wholesalers, VIP members)', 'smart-cycle-discounts' ),
					__( 'Exclude: Blacklist specific roles (e.g., exclude staff from customer promos)', 'smart-cycle-discounts' ),
					__( 'Works with all WordPress roles including custom roles from plugins', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Create tiered pricing by running multiple campaigns with different role targets and priorities', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get user roles mode help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_user_roles_mode_topic() {
		return array(
			'title'   => __( 'Role Targeting Mode', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose how to filter users by role: allow everyone, whitelist specific roles, or blacklist specific roles.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All Users: No filtering - every visitor can use this discount', 'smart-cycle-discounts' ),
					__( 'Include Only: Whitelist mode - only selected roles see the discount', 'smart-cycle-discounts' ),
					__( 'Exclude: Blacklist mode - selected roles cannot see the discount', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get user roles "All" option help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_option_user_roles_all_topic() {
		return array(
			'title'   => __( 'All Users Mode', 'smart-cycle-discounts' ),
			'icon'    => 'groups',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'No role restrictions - every user (including guests) can see and use this discount.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Public promotions, site-wide sales, seasonal discounts', 'smart-cycle-discounts' ),
					__( 'Guest users (not logged in) will see the discount', 'smart-cycle-discounts' ),
					__( 'This is the default setting for new campaigns', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get user roles "Include" option help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_option_user_roles_include_topic() {
		return array(
			'title'   => __( 'Include Only Mode', 'smart-cycle-discounts' ),
			'icon'    => 'yes-alt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Whitelist mode - only users with selected roles can see and use this discount.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: B2B pricing, member exclusives, VIP-only deals', 'smart-cycle-discounts' ),
					__( 'Guest users will NOT see the discount (no role = no match)', 'smart-cycle-discounts' ),
					__( 'Users with multiple roles only need ONE matching role', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Create a "Wholesaler" role with a membership plugin for tiered B2B pricing', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get user roles "Exclude" option help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_option_user_roles_exclude_topic() {
		return array(
			'title'   => __( 'Exclude Mode', 'smart-cycle-discounts' ),
			'icon'    => 'dismiss',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Blacklist mode - users with selected roles cannot see this discount. Everyone else can.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Best for: Excluding staff from customer promos, separating B2B from retail', 'smart-cycle-discounts' ),
					__( 'Guest users will see the discount (they have no role to exclude)', 'smart-cycle-discounts' ),
					__( 'Users with multiple roles are excluded if ANY role matches', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Exclude "Wholesaler" from retail discounts to keep pricing tiers separate', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get user roles selection help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_user_roles_selection_topic() {
		return array(
			'title'   => __( 'Select User Roles', 'smart-cycle-discounts' ),
			'icon'    => 'admin-users',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Click roles to include or exclude them from this discount campaign.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All WordPress roles are shown, including custom roles from plugins', 'smart-cycle-discounts' ),
					__( 'Select multiple roles - users matching ANY selected role are affected', 'smart-cycle-discounts' ),
					__( 'Common roles: Customer, Subscriber, Shop Manager, Administrator', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Test with a user account that has the target role before publishing', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get card free shipping help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_card_free_shipping_topic() {
		return array(
			'title'   => __( 'Free Shipping', 'smart-cycle-discounts' ),
			'icon'    => 'shipping',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Add free shipping as a bonus incentive alongside your product discounts.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Free shipping applies when cart contains products from this campaign', 'smart-cycle-discounts' ),
					__( 'Choose to make all shipping methods free, or only specific ones', 'smart-cycle-discounts' ),
					__( 'Works with spend threshold discounts for "Spend $X, get free shipping"', 'smart-cycle-discounts' ),
					__( 'Stacks with product discounts for maximum customer value', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Combine free shipping with a spend threshold to increase average order value', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get free shipping toggle help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_free_shipping_toggle_topic() {
		return array(
			'title'   => __( 'Enable Free Shipping', 'smart-cycle-discounts' ),
			'icon'    => 'yes-alt',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Toggle this on to include free shipping as part of your campaign offer.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'When enabled, customers get free shipping on eligible products', 'smart-cycle-discounts' ),
					__( 'Free shipping is applied in addition to any product discounts', 'smart-cycle-discounts' ),
					__( 'The cart must contain products from this campaign to qualify', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get free shipping methods help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_free_shipping_methods_topic() {
		return array(
			'title'   => __( 'Shipping Method Options', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose whether to make all shipping methods free, or only specific ones.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All Methods: Every available shipping option becomes free', 'smart-cycle-discounts' ),
					__( 'Selected Only: Choose which specific methods to make free', 'smart-cycle-discounts' ),
					__( 'Use "Selected Only" to offer free standard shipping while charging for express', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get free shipping "All Methods" option help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_option_free_shipping_all_topic() {
		return array(
			'title'   => __( 'All Shipping Methods', 'smart-cycle-discounts' ),
			'icon'    => 'yes',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Make every available shipping option free for qualifying orders.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All shipping methods in all zones become free', 'smart-cycle-discounts' ),
					__( 'Simplest option - no additional configuration needed', 'smart-cycle-discounts' ),
					__( 'Best for: Site-wide promotions, clearance sales, VIP campaigns', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get free shipping "Selected Methods" option help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_option_free_shipping_selected_topic() {
		return array(
			'title'   => __( 'Selected Methods Only', 'smart-cycle-discounts' ),
			'icon'    => 'list-view',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Choose specific shipping methods to make free while others remain paid.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Pick exactly which shipping methods become free', 'smart-cycle-discounts' ),
					__( 'Other methods remain at their normal price', 'smart-cycle-discounts' ),
					__( 'Best for: Free standard shipping only, local pickup incentives', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Offer free standard shipping while keeping express/overnight as paid options', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get free shipping selection help topic
	 *
	 * @since  1.3.0
	 * @return array Topic configuration
	 */
	private static function get_free_shipping_selection_topic() {
		return array(
			'title'   => __( 'Select Shipping Methods', 'smart-cycle-discounts' ),
			'icon'    => 'admin-settings',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Check the shipping methods you want to make free for this campaign.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Methods are loaded from your WooCommerce shipping zones', 'smart-cycle-discounts' ),
					__( 'Select multiple methods if needed', 'smart-cycle-discounts' ),
					__( 'Unchecked methods will remain at their normal price', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * ========================================================================
	 * SCHEDULE STEP - FIELD-LEVEL TOPICS
	 * ========================================================================
	 */

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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Campaign activates at midnight on this date (your site timezone)', 'smart-cycle-discounts' ),
					__( 'Leave empty to start immediately when you save the campaign', 'smart-cycle-discounts' ),
					__( 'Use specific time for precise launch coordination', 'smart-cycle-discounts' ),
					sprintf(
						/* translators: %s: site timezone */
						__( 'Your site timezone: %s', 'smart-cycle-discounts' ),
						wp_timezone_string()
					),
				),
				'use_cases'    => array(
					__( 'Immediate: Testing, urgent promotions, quick fixes', 'smart-cycle-discounts' ),
					__( 'Scheduled: Coordinated with email blasts, social media posts', 'smart-cycle-discounts' ),
					__( 'Future: Holiday campaigns set up weeks in advance', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Verify timezone is correct in WordPress settings', 'smart-cycle-discounts' ),
					__( 'Don\'t forget to announce campaign through marketing channels', 'smart-cycle-discounts' ),
					__( 'Launching at 2am-6am wastes first day of campaign reach', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Launch 1-2 hours before peak traffic for maximum first-day impact', 'smart-cycle-discounts' ),
					__( 'Tuesday-Thursday often have highest engagement for B2C', 'smart-cycle-discounts' ),
					__( 'Friday morning for weekend promotion kickoffs', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * Get start time help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_start_time_topic() {
		return array(
			'title'   => __( 'Campaign Start Time', 'smart-cycle-discounts' ),
			'icon'    => 'clock',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'Set specific hour for campaign to activate (optional)', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Default is midnight (00:00) if not specified', 'smart-cycle-discounts' ),
					__( '9am-10am: Catch morning shoppers at work', 'smart-cycle-discounts' ),
					__( '12pm-1pm: Lunch break browsing peak', 'smart-cycle-discounts' ),
					__( '7pm-9pm: Evening shopping prime time', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Coordinate start time with email blast delivery for maximum impact', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Starting at random times instead of peak traffic hours', 'smart-cycle-discounts' ),
					__( 'Not accounting for timezone differences in your customer base', 'smart-cycle-discounts' ),
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
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Campaign deactivates at 11:59 PM on this date (your site timezone)', 'smart-cycle-discounts' ),
					__( 'Leave empty for campaigns that run indefinitely (until manually stopped)', 'smart-cycle-discounts' ),
					__( 'Status changes to "Expired" after end date passes', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Flash sales: 6-24 hours - creates extreme urgency', 'smart-cycle-discounts' ),
					__( 'Weekend sales: 2-3 days - "This Weekend Only"', 'smart-cycle-discounts' ),
					__( 'Weekly promotions: 5-7 days - standard promotional window', 'smart-cycle-discounts' ),
					__( 'Seasonal: 2-4 weeks - major sales events', 'smart-cycle-discounts' ),
					__( 'Clearance: No end date - run until inventory clears', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Indefinite campaigns can run forever if you forget about them', 'smart-cycle-discounts' ),
					__( 'Very short campaigns (<6 hours) may not generate enough traffic', 'smart-cycle-discounts' ),
					__( 'End dates create urgency - use strategically', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Match duration to your marketing reach capability', 'smart-cycle-discounts' ),
					__( 'Shorter campaigns = higher urgency = faster conversion', 'smart-cycle-discounts' ),
					__( 'Plan "last chance" reminders for final 24-48 hours', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Recurring Schedule Type', 'smart-cycle-discounts' ),
			'icon'    => 'backup',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Campaign automatically repeats on your chosen schedule', 'smart-cycle-discounts' ),
					__( 'Each occurrence uses the same discount rules and products', 'smart-cycle-discounts' ),
					__( 'Saves time on promotions you run regularly', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Daily: "Deal of the Day" - fresh products each day', 'smart-cycle-discounts' ),
					__( 'Weekly: "Weekend Sale" every Friday-Sunday', 'smart-cycle-discounts' ),
					__( 'Monthly: "First Friday Sale" or member appreciation days', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Don\'t use daily recurrence for deep discounts - trains customers to wait', 'smart-cycle-discounts' ),
					__( 'Monitor recurring campaigns - they keep running even if unprofitable', 'smart-cycle-discounts' ),
					__( 'Product selection may include new products added after creation', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Set a recurrence end date for seasonal campaigns', 'smart-cycle-discounts' ),
					__( 'Review performance monthly and adjust as needed', 'smart-cycle-discounts' ),
					__( 'Weekly recurrence most common for sustainable promotions', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Recurring Days Selection', 'smart-cycle-discounts' ),
			'icon'    => 'calendar-alt',
			'type'    => 'help_guide',
			'content' => array(
				'how_it_works' => array(
					__( 'Select which days of the week the campaign should be active', 'smart-cycle-discounts' ),
					__( 'Campaign activates at start time on selected days', 'smart-cycle-discounts' ),
					__( 'Deactivates at end time or midnight if not specified', 'smart-cycle-discounts' ),
				),
				'use_cases'    => array(
					__( 'Weekends only: Friday + Saturday + Sunday for leisure shoppers', 'smart-cycle-discounts' ),
					__( 'Weekdays only: Monday-Friday for business customers', 'smart-cycle-discounts' ),
					__( 'Single day: "Taco Tuesday" / "Flash Friday" / "Monday Madness"', 'smart-cycle-discounts' ),
					__( 'Mid-week boost: Wednesday + Thursday for slow period sales', 'smart-cycle-discounts' ),
				),
				'watch_out'    => array(
					__( 'Selecting all 7 days removes urgency - defeats purpose of limited-time', 'smart-cycle-discounts' ),
					__( 'Check your analytics - which days have highest conversion rates?', 'smart-cycle-discounts' ),
				),
				'setup_tips'   => array(
					__( 'Align with your traffic patterns - promote when customers are shopping', 'smart-cycle-discounts' ),
					__( '2-3 days creates urgency while giving enough time to convert', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Recurrence Start Date', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'When should the recurring campaign series begin?', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Empty: Starts with next scheduled occurrence', 'smart-cycle-discounts' ),
					__( 'Future date: Delays all occurrences until this date', 'smart-cycle-discounts' ),
					__( 'Past date: Campaign already active - begins immediately', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Use future start date to prepare seasonal recurring campaigns in advance', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Setting start date during off-season for seasonal campaigns', 'smart-cycle-discounts' ),
					__( 'Not coordinating with other marketing calendar events', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Recurrence End Date', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'When should the recurring campaign series stop repeating?', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Empty: Runs indefinitely until manually stopped', 'smart-cycle-discounts' ),
					__( 'Specific date: Last occurrence before this date, then campaign expires', 'smart-cycle-discounts' ),
					__( 'Seasonal campaigns: Set end date for season end', 'smart-cycle-discounts' ),
				),
				'pro_tip'          => __( 'Set end dates for seasonal campaigns to prevent off-season promotions', 'smart-cycle-discounts' ),
				'common_mistakes'  => array(
					__( 'Leaving test campaigns running indefinitely - forgot to set end date', 'smart-cycle-discounts' ),
					__( 'Not reviewing performance before renewal period', 'smart-cycle-discounts' ),
				),
			),
		);
	}

	/**
	 * ========================================================================
	 * CARD-LEVEL HELP TOPICS - BASIC STEP
	 * ========================================================================
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
				'quick_tip' => __( 'This section captures internal information that helps you organize and track campaigns.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Name: Your internal identifier - make it descriptive and unique', 'smart-cycle-discounts' ),
					__( 'Description: Notes about goals, strategy, approvals - for team reference', 'smart-cycle-discounts' ),
					__( 'Neither field is shown to customers - these are administrative only', 'smart-cycle-discounts' ),
					__( 'Good naming helps when you have 50+ campaigns to manage', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use consistent naming convention: [Season] [Year] - [Category] [Discount%]', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Priority determines which discount applies when a product matches multiple campaigns.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Higher priority (5) always wins over lower priority (1)', 'smart-cycle-discounts' ),
					__( 'Only matters when campaigns overlap - no overlap means priority is irrelevant', 'smart-cycle-discounts' ),
					__( 'Default (3) is fine for most campaigns', 'smart-cycle-discounts' ),
					__( 'Equal priority? First-created campaign wins', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use 5 for VIP/exclusive sales, 3 for standard promos, 1 for always-on fallback discounts', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * CARD-LEVEL HELP TOPICS - PRODUCTS STEP
	 * ========================================================================
	 */

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
				'quick_tip' => __( 'Filter which products are eligible by narrowing down to specific categories.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Leave empty = all categories in your store are eligible', 'smart-cycle-discounts' ),
					__( 'Select categories = only products in these categories can be discounted', 'smart-cycle-discounts' ),
					__( 'Parent categories include all their subcategories automatically', 'smart-cycle-discounts' ),
					__( 'Works together with product selection method below', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use for department-specific promotions or to exclude premium product lines', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Choose how you want to select which products receive the discount.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'All Products: Entire catalog (or selected categories) - store-wide sales', 'smart-cycle-discounts' ),
					__( 'Specific Products: Hand-pick exact products - targeted campaigns', 'smart-cycle-discounts' ),
					__( 'Random Selection: System picks X products daily - mystery/discovery sales', 'smart-cycle-discounts' ),
					__( 'Smart Selection: Filter by rules (price, stock) - automated targeting', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Click on an option card for detailed guidance about when to use that method', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Add sophisticated conditions to automatically filter eligible products.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Skip this section for simple campaigns', 'smart-cycle-discounts' ),
					__( 'Price filters: Target products in specific price ranges', 'smart-cycle-discounts' ),
					__( 'Stock filters: Exclude low-stock or target overstocked items', 'smart-cycle-discounts' ),
					__( 'Combine multiple filters with AND/OR logic for precise targeting', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Great for automated clearance: "Stock > 100 OR Days Listed > 90"', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * CARD-LEVEL HELP TOPICS - DISCOUNTS STEP
	 * ========================================================================
	 */

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
				'quick_tip' => __( 'Your discount type determines customer psychology and margin impact.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Percentage: Best for maintaining margin ratios, scales with price', 'smart-cycle-discounts' ),
					__( 'Fixed Amount: Clear value on expensive items ("$50 off $200 product")', 'smart-cycle-discounts' ),
					__( 'BOGO: Drives volume fast, high psychological appeal', 'smart-cycle-discounts' ),
					__( 'Tiered: Rewards bulk buying, increases average order value', 'smart-cycle-discounts' ),
					__( 'Spend Threshold: Guarantees minimum order value', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Match type to goal: % for margins, BOGO for volume, tiered for AOV', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Configure the specific values for your selected discount type.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Fields change based on discount type selected above', 'smart-cycle-discounts' ),
					__( 'Percentage: Enter 20 for 20% off', 'smart-cycle-discounts' ),
					__( 'Fixed: Enter dollar amount to subtract', 'smart-cycle-discounts' ),
					__( 'BOGO: Set buy quantity, get quantity, and discount %', 'smart-cycle-discounts' ),
					__( 'Tiered: Configure quantity breakpoints and discount levels', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Test with real product prices to verify profitability before launching', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Control how your discount is visually promoted on product pages.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Badges enhance visibility but aren\'t required', 'smart-cycle-discounts' ),
					__( 'Badge Text: Customize label (e.g., "20% OFF", "SALE", "CLEARANCE")', 'smart-cycle-discounts' ),
					__( 'Badge Position: Choose corner placement on product images', 'smart-cycle-discounts' ),
					__( 'Badges can increase click-through and conversion rates', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use urgent colors (red/orange) for flash sales, calmer tones for regular promotions', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Add guardrails to protect margins and control discount behavior.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Skip for simple campaigns, use for margin protection', 'smart-cycle-discounts' ),
					__( 'Minimum Price: Prevent discounts from dropping below your floor', 'smart-cycle-discounts' ),
					__( 'Maximum Discount: Cap total discount amount per product', 'smart-cycle-discounts' ),
					__( 'Usage Limits: Restrict uses per customer', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Always set minimum price rules on deep discount campaigns to avoid selling at a loss', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * CARD-LEVEL HELP TOPICS - SCHEDULE STEP
	 * ========================================================================
	 */

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
				'quick_tip' => __( 'Choose a common campaign duration with one click, or customize below.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( '24 Hours: Flash sales - maximum urgency', 'smart-cycle-discounts' ),
					__( '3 Days: Weekend promotions (Fri-Sun)', 'smart-cycle-discounts' ),
					__( '1 Week: Standard promotional window', 'smart-cycle-discounts' ),
					__( '2 Weeks: Major sales events', 'smart-cycle-discounts' ),
					__( '1 Month: Extended seasonal campaigns', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Presets are shortcuts - you can still customize exact dates/times in the fields below', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Set exact start and end dates/times for precise campaign control.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Start: Leave empty for immediate, or set future date/time', 'smart-cycle-discounts' ),
					__( 'End: Leave empty for indefinite, or set specific end date/time', 'smart-cycle-discounts' ),
					sprintf(
						/* translators: %s: site timezone */
						__( 'All times use your site timezone: %s', 'smart-cycle-discounts' ),
						wp_timezone_string()
					),
					__( 'End dates create urgency - use strategically', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Schedule starts during peak traffic hours and ends on Sunday evening for maximum exposure', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Set up campaigns that automatically repeat on a regular schedule.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Optional: Only use if you want the campaign to repeat automatically', 'smart-cycle-discounts' ),
					__( 'Daily: Every day (or every X days)', 'smart-cycle-discounts' ),
					__( 'Weekly: Specific days each week (e.g., "Weekend Sales")', 'smart-cycle-discounts' ),
					__( 'Monthly: Specific dates each month (e.g., "First Friday")', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Great for regular promotions like "Taco Tuesday" or "Flash Friday" - set once and forget', 'smart-cycle-discounts' ),
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
				'quick_tip' => __( 'Choose whether to activate immediately or save as draft for later.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( 'Launch Campaign: Makes discount live (or scheduled if start date is future)', 'smart-cycle-discounts' ),
					__( 'Save as Draft: Stores config without activating - review/edit anytime', 'smart-cycle-discounts' ),
					__( 'Drafts can be launched from the campaign list when ready', 'smart-cycle-discounts' ),
					__( 'Scheduled campaigns show "Scheduled" status until start time', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use drafts to prepare campaigns ahead of marketing coordination (email blasts, social posts)', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get Health Score card help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_card_health_score_topic() {
		return array(
			'title'   => __( 'Campaign Health Score', 'smart-cycle-discounts' ),
			'icon'    => 'heart',
			'type'    => 'simple',
			'content' => array(
				'quick_tip' => __( 'A comprehensive score showing how well-configured your campaign is.', 'smart-cycle-discounts' ),
				'bullets'   => array(
					__( '90-100%: Excellent - Campaign is fully optimized and ready to launch', 'smart-cycle-discounts' ),
					__( '70-89%: Good - Minor improvements possible but campaign will work well', 'smart-cycle-discounts' ),
					__( '50-69%: Fair - Review suggestions to improve effectiveness', 'smart-cycle-discounts' ),
					__( 'Below 50%: Needs attention - Check required fields and configuration', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Click on any issue to jump directly to the step where you can fix it', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * OPTION-LEVEL HELP TOPICS - PRODUCT SELECTION
	 * ========================================================================
	 */

	/**
	 * Get All Products option help topic
	 *
	 * @since  1.0.0
	 * @return array Topic configuration
	 */
	private static function get_option_product_all_topic() {
		return array(
			'title'   => __( 'All Products Selection', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'Every product in your store (or selected categories) receives the discount.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Simplest setup - no product management needed', 'smart-cycle-discounts' ),
					__( 'Perfect for store-wide sales, Black Friday, anniversary events', 'smart-cycle-discounts' ),
					__( 'New products automatically included', 'smart-cycle-discounts' ),
					__( 'Great for seasonal clearance events', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'All items get same discount including premium products', 'smart-cycle-discounts' ),
					__( 'May discount products you didn\'t intend to', 'smart-cycle-discounts' ),
					__( 'Loss leaders get discounted too (use category filters to exclude)', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Combine with category filters to exclude premium or low-margin product lines', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Specific Products Selection', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'Hand-pick exactly which products receive the discount.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Complete control over what gets discounted', 'smart-cycle-discounts' ),
					__( 'Perfect for featured product promotions', 'smart-cycle-discounts' ),
					__( 'Great for testing - start with 5-10 products', 'smart-cycle-discounts' ),
					__( 'Ideal for clearance of specific slow-moving items', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'Impractical for 100+ products - too time consuming', 'smart-cycle-discounts' ),
					__( 'Requires manual updates when adding new products', 'smart-cycle-discounts' ),
					__( 'Need to check stock levels for each selected product', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Start with your best-sellers or items with highest margin for testing', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Random Products Selection', 'smart-cycle-discounts' ),
			'icon'    => 'products',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'System randomly selects X products daily - creates discovery and urgency.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Creates "treasure hunt" shopping experience', 'smart-cycle-discounts' ),
					__( 'Selection changes daily - drives repeat visits', 'smart-cycle-discounts' ),
					__( 'Perfect for "Deal of the Day" or mystery sales', 'smart-cycle-discounts' ),
					__( 'Exposes customers to products they might not find', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'Unpredictable - may pick premium or low-margin items', 'smart-cycle-discounts' ),
					__( 'Customers may see different products on different days', 'smart-cycle-discounts' ),
					__( 'Harder to coordinate with marketing campaigns', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Use category filters to limit the pool of products that can be randomly selected', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Percentage Discount', 'smart-cycle-discounts' ),
			'icon'    => 'tag',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'X% off - the most common and versatile discount type.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Scales proportionally with product price', 'smart-cycle-discounts' ),
					__( 'Maintains margin ratios across all products', 'smart-cycle-discounts' ),
					__( 'Easy for customers to calculate and understand', 'smart-cycle-discounts' ),
					__( 'Industry standard - customers expect percentage discounts', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'Small savings on cheap items (20% off $10 = only $2)', 'smart-cycle-discounts' ),
					__( 'Less impact on items under $30', 'smart-cycle-discounts' ),
					__( '5-10% often not motivating enough to drive action', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Industry benchmarks: 10-15% regular sales, 20-30% seasonal, 40-50% clearance', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Fixed Amount Discount', 'smart-cycle-discounts' ),
			'icon'    => 'receipt',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'Flat $X off - works best on higher-priced items.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Clear, tangible value proposition ("Save $50")', 'smart-cycle-discounts' ),
					__( '"$50 off" sounds bigger than "20% off" on $250 items', 'smart-cycle-discounts' ),
					__( 'Predictable discount cost per item', 'smart-cycle-discounts' ),
					__( 'Great for first-purchase incentives or referral rewards', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'Can destroy margins on cheap items ($50 off $60 = 83%!)', 'smart-cycle-discounts' ),
					__( 'Needs minimum price rules to protect against losses', 'smart-cycle-discounts' ),
					__( 'May not make sense across mixed-price catalogs', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Best for items $100+. Always set minimum product price rules.', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Tiered/Volume Discount', 'smart-cycle-discounts' ),
			'icon'    => 'chart-line',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'Better discounts for buying more - rewards bulk purchases.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Increases average order value significantly', 'smart-cycle-discounts' ),
					__( 'Rewards loyal customers who buy more', 'smart-cycle-discounts' ),
					__( '"Add 2 more for 15% off" messaging drives conversions', 'smart-cycle-discounts' ),
					__( 'Perfect for consumables, B2B, wholesale', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'More complex to set up correctly', 'smart-cycle-discounts' ),
					__( 'Customers may reduce purchase frequency, waiting to bulk buy', 'smart-cycle-discounts' ),
					__( 'First tier must be achievable or customers ignore it', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Set first tier 20% above current average quantity. Space tiers at 2x multiplier.', 'smart-cycle-discounts' ),
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
			'title'   => __( 'BOGO Discount', 'smart-cycle-discounts' ),
			'icon'    => 'cart',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'Buy X, Get Y free/discounted - psychologically powerful.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( '"Free" is incredibly compelling - drives immediate action', 'smart-cycle-discounts' ),
					__( 'Moves inventory 2x faster than equivalent percentage discount', 'smart-cycle-discounts' ),
					__( 'High viral/shareable appeal - customers tell friends', 'smart-cycle-discounts' ),
					__( 'Great for clearing seasonal or end-of-life inventory', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'B1G1 Free = 50% margin hit - verify profitability', 'smart-cycle-discounts' ),
					__( 'Frequent use can devalue your brand perception', 'smart-cycle-discounts' ),
					__( 'Customers may game system with multiple small orders', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Safer option: "Buy 2 Get 1 at 50%" (only 16.7% effective discount)', 'smart-cycle-discounts' ),
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
			'title'   => __( 'Spend Threshold Discount', 'smart-cycle-discounts' ),
			'icon'    => 'receipt',
			'type'    => 'pros_cons',
			'content' => array(
				'quick_tip' => __( 'Unlock discount by reaching minimum cart value.', 'smart-cycle-discounts' ),
				'pros'      => array(
					__( 'Guarantees minimum order value before discount applies', 'smart-cycle-discounts' ),
					__( 'Proven to increase AOV by 20-45%', 'smart-cycle-discounts' ),
					__( '"Add $12 more to save 15%" drives add-on purchases', 'smart-cycle-discounts' ),
					__( 'Works well combined with free shipping thresholds', 'smart-cycle-discounts' ),
				),
				'cons'      => array(
					__( 'Too high threshold = cart abandonment', 'smart-cycle-discounts' ),
					__( 'Too low threshold = no AOV impact', 'smart-cycle-discounts' ),
					__( 'Requires testing to find optimal threshold', 'smart-cycle-discounts' ),
				),
				'pro_tip'   => __( 'Check your current AOV first. Set threshold 20-30% higher.', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * ========================================================================
	 * TOPIC REGISTRY
	 * ========================================================================
	 */

	/**
	 * Get topic by ID
	 *
	 * PERFORMANCE: Uses lazy loading - only calls the specific topic method needed
	 * instead of instantiating all topics. Reduces memory usage significantly.
	 *
	 * @since  1.0.0
	 * @param  string $topic_id Topic identifier.
	 * @return array|null       Topic configuration or null if not found
	 */
	public static function get_topic( $topic_id ) {
		// Map topic IDs to their method names (lazy loading)
		$topic_map = array(
			// Basic Step - Field Topics
			'campaign-name'               => 'get_campaign_name_topic',
			'campaign-description'        => 'get_campaign_description_topic',
			'priority'                    => 'get_priority_topic',

			// Basic Step - Card Topics
			'card-campaign-details'       => 'get_card_campaign_details_topic',
			'card-campaign-priority'      => 'get_card_campaign_priority_topic',

			// Products Step - Field Topics
			'product-selection-type'      => 'get_product_selection_type_topic',
			'category-ids'                => 'get_category_ids_topic',
			'product-ids'                 => 'get_product_ids_topic',
			'random-count'                => 'get_random_count_topic',
			'conditions'                  => 'get_conditions_topic',
			'conditions-logic'            => 'get_conditions_logic_topic',

			// Products Step - Card Topics
			'card-category-selection'     => 'get_card_category_selection_topic',
			'card-product-selection'      => 'get_card_product_selection_topic',
			'card-advanced-filters'       => 'get_card_advanced_filters_topic',

			// Products Step - Option Topics
			'option-product-all'          => 'get_option_product_all_topic',
			'option-product-specific'     => 'get_option_product_specific_topic',
			'option-product-random'       => 'get_option_product_random_topic',

			// Discounts Step - Field Topics
			'discount-type'               => 'get_discount_type_topic',
			'discount-value'              => 'get_discount_value_topic',
			'tiers'                       => 'get_tiers_topic',
			'bogo-config'                 => 'get_bogo_config_topic',
			'spend-amount'                => 'get_spend_amount_topic',
			'badge-text'                  => 'get_badge_text_topic',
			'badge-position'              => 'get_badge_position_topic',
			'usage-limit'                 => 'get_usage_limit_topic',
			'user-roles'                  => 'get_user_roles_topic',

			// Discounts Step - Card Topics
			'card-discount-type'          => 'get_card_discount_type_topic',
			'card-discount-value'         => 'get_card_discount_value_topic',
			'card-badge-display'          => 'get_card_badge_display_topic',
			'card-discount-rules'         => 'get_card_discount_rules_topic',
			'card-user-roles'             => 'get_card_user_roles_topic',

			// Discounts Step - Option Topics (Discount Types)
			'option-discount-percentage'  => 'get_option_discount_percentage_topic',
			'option-discount-fixed'       => 'get_option_discount_fixed_topic',
			'option-discount-tiered'      => 'get_option_discount_tiered_topic',
			'option-discount-bogo'        => 'get_option_discount_bogo_topic',
			'option-discount-spend-threshold' => 'get_option_discount_spend_threshold_topic',

			// Discounts Step - User Roles Option Topics
			'user-roles-mode'             => 'get_user_roles_mode_topic',
			'option-user-roles-all'       => 'get_option_user_roles_all_topic',
			'option-user-roles-include'   => 'get_option_user_roles_include_topic',
			'option-user-roles-exclude'   => 'get_option_user_roles_exclude_topic',
			'user-roles-selection'        => 'get_user_roles_selection_topic',

			// Discounts Step - Free Shipping Topics
			'card-free-shipping'          => 'get_card_free_shipping_topic',
			'free-shipping-toggle'        => 'get_free_shipping_toggle_topic',
			'free-shipping-methods'       => 'get_free_shipping_methods_topic',
			'option-free-shipping-all'    => 'get_option_free_shipping_all_topic',
			'option-free-shipping-selected' => 'get_option_free_shipping_selected_topic',
			'free-shipping-selection'     => 'get_free_shipping_selection_topic',

			// Schedule Step - Field Topics
			'start-date'                  => 'get_start_date_topic',
			'start-time'                  => 'get_start_time_topic',
			'end-date'                    => 'get_end_date_topic',
			'recurring-type'              => 'get_recurring_type_topic',
			'recurring-days'              => 'get_recurring_days_topic',
			'recurring-start'             => 'get_recurring_start_topic',
			'recurring-end'               => 'get_recurring_end_topic',

			// Schedule Step - Card Topics
			'card-duration-presets'       => 'get_card_duration_presets_topic',
			'card-schedule-config'        => 'get_card_schedule_config_topic',
			'card-recurring-schedule'     => 'get_card_recurring_schedule_topic',

			// Review Step - Card Topics
			'card-launch-options'         => 'get_card_launch_options_topic',
			'card-health-score'           => 'get_card_health_score_topic',
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

	/**
	 * Get all topic IDs
	 *
	 * @since  1.0.0
	 * @return array List of all registered topic IDs
	 */
	public static function get_all_topic_ids() {
		return array(
			// Basic Step
			'campaign-name',
			'campaign-description',
			'priority',
			'card-campaign-details',
			'card-campaign-priority',

			// Products Step
			'product-selection-type',
			'category-ids',
			'product-ids',
			'random-count',
			'conditions',
			'conditions-logic',
			'card-category-selection',
			'card-product-selection',
			'card-advanced-filters',
			'option-product-all',
			'option-product-specific',
			'option-product-random',

			// Discounts Step
			'discount-type',
			'discount-value',
			'tiers',
			'bogo-config',
			'spend-amount',
			'badge-text',
			'badge-position',
			'usage-limit',
			'user-roles',
			'card-discount-type',
			'card-discount-value',
			'card-badge-display',
			'card-discount-rules',
			'card-user-roles',
			'option-discount-percentage',
			'option-discount-fixed',
			'option-discount-tiered',
			'option-discount-bogo',
			'option-discount-spend-threshold',
			'user-roles-mode',
			'option-user-roles-all',
			'option-user-roles-include',
			'option-user-roles-exclude',
			'user-roles-selection',
			'card-free-shipping',
			'free-shipping-toggle',
			'free-shipping-methods',
			'option-free-shipping-all',
			'option-free-shipping-selected',
			'free-shipping-selection',

			// Schedule Step
			'start-date',
			'start-time',
			'end-date',
			'recurring-type',
			'recurring-days',
			'recurring-start',
			'recurring-end',
			'card-duration-presets',
			'card-schedule-config',
			'card-recurring-schedule',

			// Review Step
			'card-launch-options',
		);
	}
}
