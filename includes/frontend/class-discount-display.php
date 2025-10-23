<?php
/**
 * Discount Display Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Discount Display Handler Class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
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
     * Initialize the discount display handler.
     *
     * @since    1.0.0
     * @param    SCD_Discount_Engine     $discount_engine     Discount engine instance.
     * @param    SCD_Campaign_Manager    $campaign_manager    Campaign manager instance.
     */
    public function __construct(SCD_Discount_Engine $discount_engine, SCD_Campaign_Manager $campaign_manager) {
        $this->discount_engine = $discount_engine;
        $this->campaign_manager = $campaign_manager;
    }

    /**
     * Render single product badge.
     *
     * @since    1.0.0
     * @return   void
     */
    public function render_single_product_badge(): void {
        global $product;
        
        if (!$product instanceof WC_Product) {
            return;
        }
        
        $discount = $this->get_product_discount($product);
        
        if ($discount) {
            $this->output_badge($discount);
        }
    }

    /**
     * Render shop badge.
     *
     * @since    1.0.0
     * @return   void
     */
    public function render_shop_badge(): void {
        global $product;
        
        if (!$product instanceof WC_Product) {
            return;
        }
        
        $discount = $this->get_product_discount($product);
        
        if ($discount) {
            $this->output_badge($discount, 'shop');
        }
    }

    /**
     * Get product discount.
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product    Product object.
     * @return   array|null                Discount data or null.
     */
    private function get_product_discount(WC_Product $product): ?array {
        $product_id = $product->get_id();
        
        // Get active campaigns for the product
        $campaigns = $this->campaign_manager->get_active_campaigns_for_product($product_id);
        
        if (empty($campaigns)) {
            return null;
        }
        
        // Get the highest priority campaign (first one)
        $campaign = reset($campaigns);
        
        // Get original price
        $original_price = (float) $product->get_regular_price();
        
        if ($original_price <= 0) {
            return null;
        }
        
        // Get discount configuration from campaign
        $discount_config = array(
            'type' => $campaign->get_discount_type(),
            'value' => $campaign->get_discount_value()
        );
        
        // Calculate discount
        $result = $this->discount_engine->calculate_discount($original_price, $discount_config, array(
            'product_id' => $product_id,
            'campaign_id' => $campaign->get_id()
        ));
        
        if (!$result->is_applied()) {
            return null;
        }
        
        return array(
            'campaign_id' => $campaign->get_id(),
            'campaign_name' => $campaign->get_name(),
            'discount_type' => $campaign->get_discount_type(),
            'discount_value' => $campaign->get_discount_value(),
            'original_price' => $original_price,
            'discounted_price' => $result->get_discounted_price(),
            'discount_amount' => $result->get_discount_amount(),
            'percentage' => round(($result->get_discount_amount() / $original_price) * 100)
        );
    }

    /**
     * Output discount badge.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $discount    Discount data.
     * @param    string    $context     Display context.
     * @return   void
     */
    private function output_badge(array $discount, string $context = 'single'): void {
        $badge_text = sprintf(
            __('Save %s%%', 'smart-cycle-discounts'),
            $discount['percentage'] ?? 0
        );
        
        printf(
            '<span class="scd-discount-badge scd-badge-%s">%s</span>',
            esc_attr($context),
            esc_html($badge_text)
        );
    }
}