<?php
/**
 * @fs_premium_only
 *
 * Frontend AJAX Handler Class
 *
 * Handles frontend AJAX requests for dynamic badge updates.
 * Primarily used for spend threshold progress which is a Pro feature.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-frontend-ajax-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend AJAX Handler class.
 *
 * Provides AJAX endpoints for frontend badge updates.
 *
 * @since      1.5.2
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 */
class WSSCD_Frontend_Ajax_Handler {

	/**
	 * Discount query service.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @var      WSSCD_WC_Discount_Query_Service|null    $discount_query
	 */
	private ?WSSCD_WC_Discount_Query_Service $discount_query = null;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.5.2
	 */
	public function __construct() {
		// Get discount query service from container if available.
		if ( function_exists( 'wsscd_container' ) ) {
			$container = wsscd_container();
			if ( $container && $container->has( 'wc_discount_query' ) ) {
				$this->discount_query = $container->get( 'wc_discount_query' );
			}
		}
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @since    1.5.2
	 * @return   void
	 */
	public function init(): void {
		// Spend threshold progress update - works for both logged-in and guests.
		add_action( 'wp_ajax_wsscd_get_spend_threshold_progress', array( $this, 'get_spend_threshold_progress' ) );
		add_action( 'wp_ajax_nopriv_wsscd_get_spend_threshold_progress', array( $this, 'get_spend_threshold_progress' ) );

		// Badge update for specific product.
		add_action( 'wp_ajax_wsscd_get_product_badge', array( $this, 'get_product_badge' ) );
		add_action( 'wp_ajax_nopriv_wsscd_get_product_badge', array( $this, 'get_product_badge' ) );
	}

	/**
	 * Get spend threshold progress based on current cart total.
	 *
	 * Returns progress toward spend threshold discounts.
	 *
	 * @since    1.5.2
	 * @return   void
	 */
	public function get_spend_threshold_progress(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wsscd_frontend_nonce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'smart-cycle-discounts' ) ),
				403
			);
		}

		// Get cart total.
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array( 'message' => __( 'Cart not available.', 'smart-cycle-discounts' ) ),
				400
			);
		}

		$cart_total = (float) WC()->cart->get_subtotal();

		// Get product IDs in cart.
		$cart_product_ids = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$cart_product_ids[] = $cart_item['product_id'];
		}

		// Find active spend threshold campaigns for products in cart.
		$threshold_data = $this->get_active_spend_thresholds( $cart_product_ids, $cart_total );

		wp_send_json_success(
			array(
				'cart_total'      => $cart_total,
				'cart_total_html' => wc_price( $cart_total ),
				'thresholds'      => $threshold_data,
			)
		);
	}

	/**
	 * Get product badge HTML via AJAX.
	 *
	 * @since    1.5.2
	 * @return   void
	 */
	public function get_product_badge(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wsscd_frontend_nonce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'smart-cycle-discounts' ) ),
				403
			);
		}

		// Get product ID.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid product ID.', 'smart-cycle-discounts' ) ),
				400
			);
		}

		// Get badge info.
		if ( ! $this->discount_query ) {
			wp_send_json_error(
				array( 'message' => __( 'Service not available.', 'smart-cycle-discounts' ) ),
				500
			);
		}

		$badge_info = $this->discount_query->get_campaign_badge_info( $product_id );

		if ( ! $badge_info || empty( $badge_info['badge_enabled'] ) ) {
			wp_send_json_success(
				array(
					'has_badge'  => false,
					'badge_html' => '',
				)
			);
		}

		// Generate badge HTML.
		$badge_html = $this->generate_badge_html( $badge_info );

		wp_send_json_success(
			array(
				'has_badge'   => true,
				'badge_html'  => $badge_html,
				'badge_info'  => array(
					'type'     => $badge_info['type'],
					'text'     => $badge_info['badge_text'] ?? '',
					'bg_color' => $badge_info['badge_bg_color'] ?? '#ff0000',
					'color'    => $badge_info['badge_text_color'] ?? '#ffffff',
					'position' => $badge_info['badge_position'] ?? 'top-right',
				),
			)
		);
	}

	/**
	 * Get active spend threshold campaigns for products.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @param    array $product_ids    Product IDs in cart.
	 * @param    float $cart_total     Current cart total.
	 * @return   array                 Threshold progress data.
	 */
	private function get_active_spend_thresholds( array $product_ids, float $cart_total ): array {
		$threshold_data = array();

		if ( ! $this->discount_query || empty( $product_ids ) ) {
			return $threshold_data;
		}

		// Get campaigns for products in cart.
		foreach ( $product_ids as $product_id ) {
			$badge_info = $this->discount_query->get_campaign_badge_info( $product_id );

			if ( ! $badge_info || 'spend_threshold' !== ( $badge_info['type'] ?? '' ) ) {
				continue;
			}

			$campaign_id = $badge_info['campaign_id'] ?? 0;

			// Skip if we already processed this campaign.
			if ( isset( $threshold_data[ $campaign_id ] ) ) {
				continue;
			}

			$thresholds = $badge_info['thresholds'] ?? array();

			if ( empty( $thresholds ) ) {
				continue;
			}

			// Sort thresholds by spend_amount ascending.
			usort(
				$thresholds,
				function ( $a, $b ) {
					return ( $a['spend_amount'] ?? 0 ) <=> ( $b['spend_amount'] ?? 0 );
				}
			);

			// Calculate progress.
			$progress = $this->calculate_threshold_progress( $thresholds, $cart_total );

			$threshold_data[ $campaign_id ] = array(
				'campaign_id'        => $campaign_id,
				'campaign_name'      => $badge_info['campaign_name'] ?? '',
				'thresholds'         => $thresholds,
				'current_total'      => $cart_total,
				'current_tier'       => $progress['current_tier'],
				'next_tier'          => $progress['next_tier'],
				'amount_to_next'     => $progress['amount_to_next'],
				'progress_percent'   => $progress['progress_percent'],
				'current_discount'   => $progress['current_discount'],
				'next_discount'      => $progress['next_discount'],
				'badge_bg_color'     => $badge_info['badge_bg_color'] ?? '#ff0000',
				'badge_text_color'   => $badge_info['badge_text_color'] ?? '#ffffff',
			);
		}

		return array_values( $threshold_data );
	}

	/**
	 * Calculate progress toward next threshold.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @param    array $thresholds    Sorted thresholds array.
	 * @param    float $cart_total    Current cart total.
	 * @return   array                Progress data.
	 */
	private function calculate_threshold_progress( array $thresholds, float $cart_total ): array {
		$progress = array(
			'current_tier'     => null,
			'next_tier'        => null,
			'amount_to_next'   => 0,
			'progress_percent' => 0,
			'current_discount' => null,
			'next_discount'    => null,
		);

		$previous_amount = 0;

		foreach ( $thresholds as $index => $threshold ) {
			$spend_amount = (float) ( $threshold['spend_amount'] ?? 0 );

			if ( $cart_total >= $spend_amount ) {
				// Customer qualifies for this tier.
				$progress['current_tier']     = $index;
				$progress['current_discount'] = $this->format_threshold_discount( $threshold );
				$previous_amount              = $spend_amount;
			} else {
				// This is the next tier to reach.
				$progress['next_tier']       = $index;
				$progress['next_discount']   = $this->format_threshold_discount( $threshold );
				$progress['amount_to_next']  = $spend_amount - $cart_total;

				// Calculate progress percentage toward next tier.
				$tier_range = $spend_amount - $previous_amount;
				if ( $tier_range > 0 ) {
					$progress_in_tier            = $cart_total - $previous_amount;
					$progress['progress_percent'] = min( 100, ( $progress_in_tier / $tier_range ) * 100 );
				}

				break;
			}
		}

		// If all tiers are reached.
		if ( null === $progress['next_tier'] && null !== $progress['current_tier'] ) {
			$progress['progress_percent'] = 100;
		}

		return $progress;
	}

	/**
	 * Format threshold discount for display.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @param    array $threshold    Threshold data.
	 * @return   array               Formatted discount data.
	 */
	private function format_threshold_discount( array $threshold ): array {
		$discount_type  = $threshold['discount_type'] ?? 'percentage';
		$discount_value = (float) ( $threshold['discount_value'] ?? 0 );
		$spend_amount   = (float) ( $threshold['spend_amount'] ?? 0 );

		if ( 'percentage' === $discount_type ) {
			$display = sprintf(
				/* translators: %s: discount percentage */
				__( '%s%% off', 'smart-cycle-discounts' ),
				number_format( $discount_value, $discount_value == floor( $discount_value ) ? 0 : 2 )
			);
		} else {
			$display = sprintf(
				/* translators: %s: discount amount */
				__( '%s off', 'smart-cycle-discounts' ),
				wp_strip_all_tags( wc_price( $discount_value ) )
			);
		}

		return array(
			'type'         => $discount_type,
			'value'        => $discount_value,
			'spend_amount' => $spend_amount,
			'display'      => $display,
			'spend_html'   => wc_price( $spend_amount ),
		);
	}

	/**
	 * Generate badge HTML.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @param    array $badge_info    Badge information.
	 * @return   string               Badge HTML.
	 */
	private function generate_badge_html( array $badge_info ): string {
		$type       = $badge_info['type'] ?? 'percentage';
		$bg_color   = $badge_info['badge_bg_color'] ?? '#ff0000';
		$text_color = $badge_info['badge_text_color'] ?? '#ffffff';
		$position   = $badge_info['badge_position'] ?? 'top-right';

		// Get badge text.
		$text = $this->get_badge_text( $badge_info );

		if ( empty( $text ) ) {
			return '';
		}

		$classes = array(
			'wsscd-discount-badge',
			'wsscd-badge-dynamic',
			'wsscd-badge-' . esc_attr( $type ),
			'wsscd-badge-position-' . esc_attr( $position ),
		);

		$styles = sprintf(
			'background-color: %s; color: %s;',
			esc_attr( $bg_color ),
			esc_attr( $text_color )
		);

		return sprintf(
			'<span class="%s" style="%s" data-discount-type="%s">%s</span>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $styles ),
			esc_attr( $type ),
			esc_html( $text )
		);
	}

	/**
	 * Get badge text based on discount type.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @param    array $badge_info    Badge information.
	 * @return   string               Badge text.
	 */
	private function get_badge_text( array $badge_info ): string {
		// Check for custom text first.
		$badge_text = $badge_info['badge_text'] ?? 'auto';
		if ( 'auto' !== $badge_text && ! empty( $badge_text ) ) {
			return $badge_text;
		}

		$type = $badge_info['type'] ?? 'percentage';

		switch ( $type ) {
			case 'percentage':
				$value = absint( $badge_info['value'] ?? 0 );
				return $value > 0 ? sprintf( __( 'Save %d%%', 'smart-cycle-discounts' ), $value ) : '';

			case 'fixed':
				$value = (float) ( $badge_info['value'] ?? 0 );
				if ( $value > 0 ) {
					$currency_symbol = get_woocommerce_currency_symbol();
					return sprintf( __( 'Save %s', 'smart-cycle-discounts' ), $currency_symbol . number_format( $value, 0 ) );
				}
				return '';

			case 'bogo':
				$buy_qty = $badge_info['buy_quantity'] ?? 1;
				$get_qty = $badge_info['get_quantity'] ?? 1;
				return sprintf( __( 'Buy %1$d Get %2$d', 'smart-cycle-discounts' ), $buy_qty, $get_qty );

			case 'tiered':
				return __( 'Volume Discounts', 'smart-cycle-discounts' );

			case 'spend_threshold':
				return __( 'Spend & Save', 'smart-cycle-discounts' );

			default:
				return __( 'Sale', 'smart-cycle-discounts' );
		}
	}
}
