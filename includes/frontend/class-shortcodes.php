<?php
/**
 * Shortcodes Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-shortcodes.php
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
 * Shortcodes Class
 *
 * Handles all frontend shortcodes for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Shortcodes {

	/**
	 * Discount display instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Discount_Display    $discount_display    Discount display.
	 */
	private WSSCD_Discount_Display $discount_display;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private WSSCD_Campaign_Manager $campaign_manager;

	/**
	 * Initialize the shortcodes.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Discount_Display $discount_display    Discount display.
	 * @param    WSSCD_Campaign_Manager $campaign_manager    Campaign manager.
	 */
	public function __construct(
		WSSCD_Discount_Display $discount_display,
		WSSCD_Campaign_Manager $campaign_manager
	) {
		$this->discount_display = $discount_display;
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Register all shortcodes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register(): void {
		// Product discount shortcodes
		add_shortcode( 'wsscd_product_discount', array( $this, 'product_discount' ) );
		add_shortcode( 'wsscd_product_badge', array( $this, 'product_badge' ) );

		// Campaign shortcodes
		add_shortcode( 'wsscd_campaign_countdown', array( $this, 'campaign_countdown' ) );
		add_shortcode( 'wsscd_campaign_products', array( $this, 'campaign_products' ) );

		// General shortcodes
		add_shortcode( 'wsscd_active_campaigns', array( $this, 'active_campaigns' ) );
		add_shortcode( 'wsscd_discount_timer', array( $this, 'discount_timer' ) );
	}

	/**
	 * Product discount shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Shortcode output.
	 */
	public function product_discount( $atts ): string {
		$atts = shortcode_atts(
			array(
				'product_id'    => 0,
				'show_original' => 'yes',
				'format'        => 'badge',
			),
			$atts
		);

		$product_id = intval( $atts['product_id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		ob_start();

		if ( $atts['format'] === 'badge' ) {
			$this->discount_display->render_single_product_badge( $product_id );
		} else {
			// Simple price display
			echo wp_kses_post( $this->discount_display->get_discount_price_html( $product_id ) );
		}

		return ob_get_clean();
	}

	/**
	 * Product badge shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Shortcode output.
	 */
	public function product_badge( $atts ): string {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'style'      => 'default',
			),
			$atts
		);

		$product_id = intval( $atts['product_id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		ob_start();
		$this->discount_display->render_shop_badge( $product_id );
		return ob_get_clean();
	}

	/**
	 * Campaign countdown shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Shortcode output.
	 */
	public function campaign_countdown( $atts ): string {
		$atts = shortcode_atts(
			array(
				'campaign_id'   => 0,
				'campaign_slug' => '',
				'show_title'    => 'yes',
				'style'         => 'default',
			),
			$atts
		);

		$campaign = null;

		if ( $atts['campaign_id'] ) {
			$campaign = $this->campaign_manager->find( intval( $atts['campaign_id'] ) );
		} elseif ( $atts['campaign_slug'] ) {
			$campaign = $this->campaign_manager->find_by_slug( $atts['campaign_slug'] );
		}

		if ( ! $campaign || ! $campaign->is_active() ) {
			return '';
		}

		$end_date = $campaign->get_ends_at();
		if ( ! $end_date ) {
			return '';
		}

		ob_start();
		?>
		<div class="wsscd-campaign-countdown" data-campaign-id="<?php echo esc_attr( $campaign->get_id() ); ?>">
			<?php if ( $atts['show_title'] === 'yes' ) : ?>
				<h3><?php echo esc_html( $campaign->get_name() ); ?></h3>
			<?php endif; ?>
			<div class="wsscd-countdown-timer" data-end-time="<?php echo esc_attr( $end_date->format( 'c' ) ); ?>">
				<span class="wsscd-countdown-loading">Loading countdown...</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Campaign products shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Shortcode output.
	 */
	public function campaign_products( $atts ): string {
		$atts = shortcode_atts(
			array(
				'campaign_id'   => 0,
				'campaign_slug' => '',
				'limit'         => 12,
				'columns'       => 4,
				'orderby'       => 'date',
				'order'         => 'DESC',
			),
			$atts
		);

		$campaign = null;

		if ( $atts['campaign_id'] ) {
			$campaign = $this->campaign_manager->find( intval( $atts['campaign_id'] ) );
		} elseif ( $atts['campaign_slug'] ) {
			$campaign = $this->campaign_manager->find_by_slug( $atts['campaign_slug'] );
		}

		if ( ! $campaign || ! $campaign->is_active() ) {
			return '';
		}

		$product_ids = $campaign->get_product_ids();

		if ( empty( $product_ids ) ) {
			return '';
		}

		// Use WooCommerce shortcode to display products
		return do_shortcode(
			sprintf(
				'[products ids="%s" limit="%d" columns="%d" orderby="%s" order="%s"]',
				implode( ',', $product_ids ),
				intval( $atts['limit'] ),
				intval( $atts['columns'] ),
				esc_attr( $atts['orderby'] ),
				esc_attr( $atts['order'] )
			)
		);
	}

	/**
	 * Active campaigns shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Shortcode output.
	 */
	public function active_campaigns( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit'         => 10,
				'show_products' => 'no',
				'style'         => 'list',
			),
			$atts
		);

		$campaigns = $this->campaign_manager->get_active_campaigns(
			array(
				'limit' => intval( $atts['limit'] ),
			)
		);

		if ( empty( $campaigns ) ) {
			return '<p>' . esc_html__( 'No active campaigns at the moment.', 'smart-cycle-discounts' ) . '</p>';
		}

		ob_start();
		?>
		<div class="wsscd-active-campaigns wsscd-style-<?php echo esc_attr( $atts['style'] ); ?>">
			<?php foreach ( $campaigns as $campaign ) : ?>
				<?php
				if ( ! $campaign ) {
					continue;}
				?>
				<div class="wsscd-campaign-item">
					<h3><?php echo esc_html( $campaign->get_name() ); ?></h3>
					<?php if ( $campaign->get_description() ) : ?>
						<p><?php echo wp_kses_post( $campaign->get_description() ); ?></p>
					<?php endif; ?>
					<?php if ( $atts['show_products'] === 'yes' ) : ?>
						<?php
						echo wp_kses_post(
							$this->campaign_products(
								array(
									'campaign_id' => $campaign->get_id(),
									'limit'       => 4,
									'columns'     => 4,
								)
							)
						);
						?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Discount timer shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Shortcode output.
	 */
	public function discount_timer( $atts ): string {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'style'      => 'inline',
			),
			$atts
		);

		$product_id = intval( $atts['product_id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$campaigns       = $this->campaign_manager->get_active_campaigns();
		$active_campaign = null;

		foreach ( $campaigns as $campaign ) {
			if ( ! $campaign ) {
				continue;
			}
			if ( $campaign->can_apply_to_product( $product_id ) ) {
				$active_campaign = $campaign;
				break;
			}
		}

		if ( ! $active_campaign || ! $active_campaign->get_ends_at() ) {
			return '';
		}

		ob_start();
		?>
		<div class="wsscd-discount-timer wsscd-style-<?php echo esc_attr( $atts['style'] ); ?>" 
			data-end-time="<?php echo esc_attr( $active_campaign->get_ends_at()->format( 'c' ) ); ?>">
			<span class="wsscd-timer-label"><?php esc_html_e( 'Offer ends in:', 'smart-cycle-discounts' ); ?></span>
			<span class="wsscd-timer-countdown">--:--:--</span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get discount price HTML.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $product_id    Product ID.
	 * @return   string                Price HTML.
	 */
	private function get_discount_price_html( int $product_id ): string {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		// This would integrate with discount engine
		// For now, return regular price
		return $product->get_price_html();
	}
}
