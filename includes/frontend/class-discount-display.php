<?php
/**
 * Discount Display Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-discount-display.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Discount Display Handler Class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Discount_Display {

	/**
	 * Discount engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Discount_Engine    $discount_engine    Discount engine instance.
	 */
	private SCD_Discount_Engine $discount_engine;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager instance.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Display rules instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Discount_Display_Rules    $display_rules    Display rules instance.
	 */
	private SCD_Discount_Display_Rules $display_rules;

	/**
	 * Initialize the discount display handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Discount_Engine        $discount_engine     Discount engine instance.
	 * @param    SCD_Campaign_Manager       $campaign_manager    Campaign manager instance.
	 * @param    SCD_Discount_Display_Rules $display_rules       Display rules instance.
	 */
	public function __construct( SCD_Discount_Engine $discount_engine, SCD_Campaign_Manager $campaign_manager, SCD_Discount_Display_Rules $display_rules ) {
		$this->discount_engine  = $discount_engine;
		$this->campaign_manager = $campaign_manager;
		$this->display_rules    = $display_rules;
	}

	/**
	 * Render single product badge.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_single_product_badge(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$discount = $this->get_product_discount( $product );

		if ( $discount ) {
			$this->output_badge( $discount );
		}
	}

	/**
	 * Render shop badge (OPTIMIZED - No calculations).
	 *
	 * Uses smart display rules to avoid memory exhaustion on shop pages.
	 * Gets badge text directly from campaign data without full calculation.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_shop_badge(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$product_id = $product->get_id();

		// Get campaigns WITHOUT full calculation (performance optimization)
		$campaigns = $this->campaign_manager->get_active_campaigns_for_product( $product_id );

		if ( empty( $campaigns ) ) {
			return;
		}

		// Use first applicable campaign
		$campaign      = reset( $campaigns );
		$discount_type = $campaign->get_discount_type();

		// Check if campaign has badges enabled
		if ( ! $campaign->is_badge_enabled() ) {
			return;
		}

		// Check if this type should display on shop pages (CRITICAL for performance)
		if ( ! $this->display_rules->can_display_on_shop( $discount_type ) ) {
			return;
		}

		// Get badge text directly from campaign (no calculation!)
		$badge_text = $this->display_rules->get_simple_badge_text( $campaign );

		if ( $badge_text ) {
			$this->output_badge_simple(
				array(
					'text'     => $badge_text,
					'type'     => $discount_type,
					'campaign' => $campaign,
				),
				'shop'
			);
		}
	}

	/**
	 * Get product discount.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product    Product object.
	 * @return   array|null                Discount data or null.
	 */
	private function get_product_discount( WC_Product $product ): ?array {
		$product_id = $product->get_id();

		$campaigns = $this->campaign_manager->get_active_campaigns_for_product( $product_id );

		if ( empty( $campaigns ) ) {
			return null;
		}

		$campaign = reset( $campaigns );

		$original_price = (float) $product->get_regular_price();

		if ( $original_price <= 0 ) {
			return null;
		}

		$discount_config = array(
			'type'  => $campaign->get_discount_type(),
			'value' => $campaign->get_discount_value(),
		);

		$result = $this->discount_engine->calculate_discount(
			$original_price,
			$discount_config,
			array(
				'product_id'  => $product_id,
				'campaign_id' => $campaign->get_id(),
			)
		);

		if ( ! $result->is_applied() ) {
			return null;
		}

		return array(
			'campaign_id'      => $campaign->get_id(),
			'campaign_name'    => $campaign->get_name(),
			'discount_type'    => $campaign->get_discount_type(),
			'discount_value'   => $campaign->get_discount_value(),
			'original_price'   => $original_price,
			'discounted_price' => $result->get_discounted_price(),
			'discount_amount'  => $result->get_discount_amount(),
			'percentage'       => round( ( $result->get_discount_amount() / $original_price ) * 100 ),
		);
	}

	/**
	 * Output discount badge.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $discount    Discount data.
	 * @param    string $context     Display context.
	 * @return   void
	 */
	private function output_badge( array $discount, string $context = 'single' ): void {
		$badge_text = sprintf(
			__( 'Save %s%%', 'smart-cycle-discounts' ),
			$discount['percentage'] ?? 0
		);

		printf(
			'<span class="scd-discount-badge scd-badge-%s">%s</span>',
			esc_attr( $context ),
			esc_html( $badge_text )
		);
	}

	/**
	 * Output simple badge with campaign styling.
	 *
	 * Uses campaign's badge configuration for colors and position.
	 * Optimized for shop pages - no calculations required.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $badge_data    Badge data array.
	 * @param    string $context        Display context.
	 * @return   void
	 */
	private function output_badge_simple( array $badge_data, string $context = 'shop' ): void {
		$text     = $badge_data['text'] ?? '';
		$type     = $badge_data['type'] ?? '';
		$campaign = $badge_data['campaign'] ?? null;

		if ( ! $text || ! $campaign ) {
			return;
		}

		// Get campaign badge settings
		$bg_color    = $campaign->get_badge_bg_color() ?: '#ff0000';
		$text_color  = $campaign->get_badge_text_color() ?: '#ffffff';
		$position    = $campaign->get_badge_position() ?: 'top-right';

		// Build inline styles
		$styles = sprintf(
			'background-color: %s; color: %s;',
			esc_attr( $bg_color ),
			esc_attr( $text_color )
		);

		printf(
			'<span class="scd-discount-badge scd-badge-%s scd-badge-%s scd-badge-position-%s" style="%s" data-discount-type="%s">%s</span>',
			esc_attr( $context ),
			esc_attr( $type ),
			esc_attr( $position ),
			esc_attr( $styles ),
			esc_attr( $type ),
			esc_html( $text )
		);
	}
}
