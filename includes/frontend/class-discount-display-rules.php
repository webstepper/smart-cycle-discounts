<?php
/**
 * Discount Display Rules Class
 *
 * Smart display logic for discount badges based on discount type and context.
 * Prevents memory exhaustion by skipping inappropriate discount types on shop pages.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
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
 * Discount Display Rules Class.
 *
 * Implements smart display logic based on discount type characteristics:
 * - Simple types (percentage/fixed) display everywhere
 * - Context-dependent types (BOGO/spend threshold) only where meaningful
 * - Performance optimization by avoiding calculations on shop pages
 *
 * @since      1.0.0
 */
class SCD_Discount_Display_Rules {

	/**
	 * Discount types that can display on shop pages.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private array $shop_compatible_types = array(
		'percentage',
		'fixed',
		'tiered',
	);

	/**
	 * Discount types that require cart context.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private array $cart_only_types = array(
		'spend_threshold',
	);

	/**
	 * Discount types that require quantity context.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private array $quantity_dependent_types = array(
		'bogo',
		'tiered',
	);

	/**
	 * Check if discount type should display on shop pages.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   bool                       True if can display on shop.
	 */
	public function can_display_on_shop( string $discount_type ): bool {
		// Check global settings
		$settings = get_option( 'scd_badge_settings', array() );

		// Global badge toggle
		if ( empty( $settings['enable_badges'] ) || empty( $settings['enable_shop_badges'] ) ) {
			return false;
		}

		// Check per-type settings
		$display_types = $settings['display_types'] ?? array();
		if ( isset( $display_types[ $discount_type ]['shop'] ) ) {
			return (bool) $display_types[ $discount_type ]['shop'];
		}

		// Default: Allow shop-compatible types
		return in_array( $discount_type, $this->shop_compatible_types, true );
	}

	/**
	 * Check if discount type should display on product pages.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   bool                       True if can display on product page.
	 */
	public function can_display_on_product( string $discount_type ): bool {
		// Check global settings
		$settings = get_option( 'scd_badge_settings', array() );

		// Global badge toggle
		if ( empty( $settings['enable_badges'] ) || empty( $settings['enable_product_badges'] ) ) {
			return false;
		}

		// Check per-type settings
		$display_types = $settings['display_types'] ?? array();
		if ( isset( $display_types[ $discount_type ]['product'] ) ) {
			return (bool) $display_types[ $discount_type ]['product'];
		}

		// Default: All types can display on product page
		return true;
	}

	/**
	 * Check if discount type should display in cart.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   bool                       True if can display in cart.
	 */
	public function can_display_in_cart( string $discount_type ): bool {
		// Check global settings
		$settings = get_option( 'scd_badge_settings', array() );

		// Global badge toggle
		if ( empty( $settings['enable_badges'] ) || empty( $settings['enable_cart_badges'] ) ) {
			return false;
		}

		// Check per-type settings
		$display_types = $settings['display_types'] ?? array();
		if ( isset( $display_types[ $discount_type ]['cart'] ) ) {
			return (bool) $display_types[ $discount_type ]['cart'];
		}

		// Default: Allow cart display
		return true;
	}

	/**
	 * Check if discount requires full calculation.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @param    string $context          Display context (shop, product, cart).
	 * @return   bool                       True if requires calculation.
	 */
	public function requires_calculation( string $discount_type, string $context ): bool {
		// Shop pages never need calculation - use campaign data directly
		if ( 'shop' === $context ) {
			return false;
		}

		// Product pages and cart need accurate calculations
		return true;
	}

	/**
	 * Get simple badge text from campaign data without calculation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                      Badge text.
	 */
	public function get_simple_badge_text( SCD_Campaign $campaign ): string {
		// Check if campaign has custom badge text
		$badge_text = $campaign->get_badge_text();
		if ( ! empty( $badge_text ) && 'auto' !== $badge_text ) {
			return $badge_text;
		}

		// Generate automatic badge text based on discount type
		$discount_type  = $campaign->get_discount_type();
		$discount_value = $campaign->get_discount_value();

		switch ( $discount_type ) {
			case 'percentage':
				return sprintf( '%d%% OFF', absint( $discount_value ) );

			case 'fixed':
				return sprintf( '%s OFF', wc_price( $discount_value ) );

			case 'tiered':
				$rules = $campaign->get_discount_rules();
				$tiers = $rules['tiers'] ?? array();
				if ( ! empty( $tiers ) ) {
					// Find minimum discount value
					$discount_values = array_column( $tiers, 'discount_value' );
					$min_discount    = min( $discount_values );

					// Check tier mode
					$tier_mode = $rules['tier_mode'] ?? 'percentage';
					if ( 'percentage' === $tier_mode ) {
						return sprintf( __( 'From %d%% OFF', 'smart-cycle-discounts' ), absint( $min_discount ) );
					} else {
						return sprintf( __( 'From %s OFF', 'smart-cycle-discounts' ), wc_price( $min_discount ) );
					}
				}
				return __( 'Volume Pricing', 'smart-cycle-discounts' );

			case 'bogo':
				$rules = $campaign->get_discount_rules();
				$buy   = $rules['buy_quantity'] ?? 1;
				$get   = $rules['get_quantity'] ?? 1;
				return sprintf( __( 'Buy %d Get %d', 'smart-cycle-discounts' ), $buy, $get );

			case 'spend_threshold':
				return __( 'Spend & Save', 'smart-cycle-discounts' );

			default:
				return __( 'Special Offer', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Check if discount type is context-dependent.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   bool                       True if context-dependent.
	 */
	public function is_context_dependent( string $discount_type ): bool {
		return in_array( $discount_type, array_merge( $this->cart_only_types, $this->quantity_dependent_types ), true );
	}

	/**
	 * Get context requirement for discount type.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   string                     Context requirement (none, cart, quantity).
	 */
	public function get_context_requirement( string $discount_type ): string {
		if ( in_array( $discount_type, $this->cart_only_types, true ) ) {
			return 'cart';
		}

		if ( in_array( $discount_type, $this->quantity_dependent_types, true ) ) {
			return 'quantity';
		}

		return 'none';
	}

	/**
	 * Get user-friendly context requirement message.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   string                     Context requirement message.
	 */
	public function get_context_requirement_message( string $discount_type ): string {
		$requirement = $this->get_context_requirement( $discount_type );

		switch ( $requirement ) {
			case 'cart':
				return __( 'This discount type requires cart total context and will only display in cart/checkout.', 'smart-cycle-discounts' );

			case 'quantity':
				return __( 'This discount type requires quantity context and will only display badges on product pages, not shop pages.', 'smart-cycle-discounts' );

			default:
				return '';
		}
	}

	/**
	 * Get default badge settings.
	 *
	 * @since    1.0.0
	 * @return   array    Default badge settings.
	 */
	public static function get_default_settings(): array {
		return array(
			'enable_badges'         => true,
			'enable_shop_badges'    => true,
			'enable_product_badges' => true,
			'enable_cart_badges'    => true,

			'display_types' => array(
				'percentage' => array(
					'shop'    => true,
					'product' => true,
					'cart'    => true,
					'format'  => '{value}% OFF',
				),
				'fixed' => array(
					'shop'    => true,
					'product' => true,
					'cart'    => true,
					'format'  => '{value} OFF',
				),
				'tiered' => array(
					'shop'    => true,
					'product' => true,
					'cart'    => true,
					'format'  => 'From {min}% OFF',
				),
				'bogo' => array(
					'shop'    => false,
					'product' => true,
					'cart'    => true,
					'format'  => 'Buy {buy} Get {get}',
				),
				'spend_threshold' => array(
					'shop'    => false,
					'product' => false,
					'cart'    => true,
					'format'  => 'Spend & Save',
				),
			),

			'default_position'    => 'top-right',
			'default_bg_color'    => '#ff0000',
			'default_text_color'  => '#ffffff',
			'default_style'       => 'default',
			'custom_css'          => '',
		);
	}
}
