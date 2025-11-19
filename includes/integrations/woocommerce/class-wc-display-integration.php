<?php
/**
 * Wc Display Integration Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-display-integration.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Display Integration class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */
class SCD_WC_Display_Integration {

	/**
	 * Discount query service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Discount_Query_Service    $discount_query
	 */
	private SCD_WC_Discount_Query_Service $discount_query;

	/**
	 * Logger.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger
	 */
	private ?object $logger;

	/**
	 * Initialize display integration.
	 *
	 * @since    1.0.0
	 * @param    SCD_WC_Discount_Query_Service $discount_query    Discount query service.
	 * @param    object|null                   $logger            Logger.
	 */
	public function __construct( SCD_WC_Discount_Query_Service $discount_query, ?object $logger = null ) {
		$this->discount_query = $discount_query;
		$this->logger         = $logger;
	}

	/**
	 * Register display hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_hooks(): void {
		// Product page display
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_discount_badge' ), 15 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_discount_details' ), 25 );

		// Shop loop display
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_shop_discount_badge' ), 15 );

		// Cart display
		add_filter( 'woocommerce_cart_item_price', array( $this, 'display_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_cart_item_subtotal' ), 10, 3 );

		// Theme compatibility
		add_filter( 'woocommerce_sale_flash', array( $this, 'maybe_hide_sale_badge' ), 10, 3 );
		add_filter( 'body_class', array( $this, 'add_discount_body_class' ) );
		add_filter( 'post_class', array( $this, 'add_discount_post_class' ), 10, 3 );
	}

	/**
	 * Display discount badge on single product page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_discount_badge(): void {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		$this->render_badge( $product, 'single' );
	}

	/**
	 * Display discount badge on shop loop.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_shop_discount_badge(): void {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		$this->render_badge( $product, 'shop' );
	}

	/**
	 * Display detailed discount information on product page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_product_discount_details(): void {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		try {
			$product_id = $product->get_id();

			if ( $this->discount_query->has_active_discount( $product_id ) ) {
				$discount_info = $this->discount_query->get_discount_info( $product_id );

				if ( $discount_info ) {
					$discount_type = $discount_info['type'];

					if ( in_array( $discount_type, array( 'tiered', 'bogo', 'spend_threshold' ), true ) ) {
						echo wp_kses_post( $this->render_discount_details( $discount_info ) );
					}
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to display product discount details',
				array(
					'product_id' => $product_id,
					'error'      => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Render discount badge.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product    Product object.
	 * @param    string     $context    Display context.
	 * @return   void
	 */
	private function render_badge( WC_Product $product, string $context ): void {
		$product_id = $product->get_id();

		try {
			// Get campaign badge info (works even when discount doesn't apply at quantity=1)
			$badge_info = $this->discount_query->get_campaign_badge_info( $product_id );

			if ( $badge_info ) {
				$html = $this->get_badge_html( $badge_info, $context );
				echo wp_kses_post( $html );
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to render badge',
				array(
					'product_id' => $product_id,
					'context'    => $context,
					'error'      => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get badge HTML.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $badge_info    Badge information from campaign.
	 * @param    string $context       Display context.
	 * @return   string                   Badge HTML.
	 */
	private function get_badge_html( array $badge_info, string $context ): string {
		$type = $badge_info['type'] ?? 'percentage';
		$text = '';

		switch ( $type ) {
			case 'bogo':
				$buy_qty     = $badge_info['buy_quantity'] ?? 1;
				$get_qty     = $badge_info['get_quantity'] ?? 1;
				$get_percent = $badge_info['get_discount_percentage'] ?? 100;

				if ( 100 === $get_percent ) {
					if ( 1 === $buy_qty && 1 === $get_qty ) {
						$text = __( 'Buy 1 Get 1 Free', 'smart-cycle-discounts' );
					} else {
						$text = sprintf(
							/* translators: 1: buy quantity, 2: get quantity */
							__( 'Buy %1$d Get %2$d Free', 'smart-cycle-discounts' ),
							$buy_qty,
							$get_qty
						);
					}
				} else {
					$text = sprintf(
						/* translators: 1: buy quantity, 2: get quantity, 3: discount percentage */
						__( 'Buy %1$d Get %2$d at %3$d%% Off', 'smart-cycle-discounts' ),
						$buy_qty,
						$get_qty,
						absint( $get_percent )
					);
				}
				break;

			case 'tiered':
				$text = __( 'Volume Discounts', 'smart-cycle-discounts' );
				break;

			case 'fixed':
				$value = $badge_info['value'] ?? 0;
				if ( $value > 0 ) {
					$text = sprintf(
						/* translators: %s: discount amount */
						__( 'Save %s', 'smart-cycle-discounts' ),
						wc_price( $value )
					);
				}
				break;

			case 'percentage':
			default:
				$percentage = absint( $badge_info['value'] ?? 0 );
				if ( $percentage > 0 ) {
					$text = sprintf(
						/* translators: %d: discount percentage */
						__( 'Save %d%%', 'smart-cycle-discounts' ),
						$percentage
					);
				}
				break;
		}

		if ( empty( $text ) ) {
			return '';
		}

		// Get badge styling from campaign settings
		$bg_color   = $badge_info['badge_bg_color'] ?? '#ff0000';
		$text_color = $badge_info['badge_text_color'] ?? '#ffffff';
		$position   = $badge_info['badge_position'] ?? 'top-right';

		// Build CSS classes
		$classes = array(
			'scd-discount-badge',
			'scd-badge-' . esc_attr( $context ),
			'scd-badge-' . esc_attr( $type ),
			'scd-badge-position-' . esc_attr( $position ),
		);

		// Build inline styles
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
	 * Render discount details.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_info    Discount information.
	 * @return   string                     Details HTML.
	 */
	private function render_discount_details( array $discount_info ): string {
		$type = $discount_info['type'] ?? '';

		ob_start();
		?>
		<div class="scd-discount-details">
			<h4><?php esc_html_e( 'Discount Details', 'smart-cycle-discounts' ); ?></h4>
			<?php
			switch ( $type ) {
				case 'tiered':
					echo wp_kses_post( $this->render_tiered_details( $discount_info ) );
					break;
				case 'bogo':
					echo wp_kses_post( $this->render_bogo_details( $discount_info ) );
					break;
				case 'spend_threshold':
					echo wp_kses_post( $this->render_spend_threshold_details( $discount_info ) );
					break;
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render tiered discount details.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_info    Discount information.
	 * @return   string                     HTML.
	 */
	private function render_tiered_details( array $discount_info ): string {
		return '<p>' . esc_html__( 'Tiered discount available', 'smart-cycle-discounts' ) . '</p>';
	}

	/**
	 * Render BOGO details.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_info    Discount information.
	 * @return   string                     HTML.
	 */
	private function render_bogo_details( array $discount_info ): string {
		return '<p>' . esc_html__( 'Buy one, get one discount available', 'smart-cycle-discounts' ) . '</p>';
	}

	/**
	 * Render spend threshold details.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_info    Discount information.
	 * @return   string                     HTML.
	 */
	private function render_spend_threshold_details( array $discount_info ): string {
		return '<p>' . esc_html__( 'Spend threshold discount available', 'smart-cycle-discounts' ) . '</p>';
	}

	/**
	 * Display cart item price with discount strikethrough.
	 *
	 * @since    1.0.0
	 * @param    string $price           Price HTML.
	 * @param    array  $cart_item       Cart item data.
	 * @param    string $cart_item_key   Cart item key.
	 * @return   string                     Modified price HTML.
	 */
	public function display_cart_item_price( string $price, array $cart_item, string $cart_item_key ): string {
		if ( isset( $cart_item['scd_discount'] ) ) {
			$discount   = $cart_item['scd_discount'];
			$original   = wc_price( $discount['original_price'] );
			$discounted = wc_price( $discount['discounted_price'] );

			return sprintf( '<del>%s</del> <ins>%s</ins>', $original, $discounted );
		}

		return $price;
	}

	/**
	 * Display cart item subtotal with discount strikethrough.
	 *
	 * @since    1.0.0
	 * @param    string $subtotal        Subtotal HTML.
	 * @param    array  $cart_item       Cart item data.
	 * @param    string $cart_item_key   Cart item key.
	 * @return   string                     Modified subtotal HTML.
	 */
	public function display_cart_item_subtotal( string $subtotal, array $cart_item, string $cart_item_key ): string {
		if ( isset( $cart_item['scd_discount'] ) ) {
			$discount = $cart_item['scd_discount'];
			$quantity = $cart_item['quantity'];

			$original_total   = $discount['original_price'] * $quantity;
			$discounted_total = $discount['discounted_price'] * $quantity;

			$original   = wc_price( $original_total );
			$discounted = wc_price( $discounted_total );

			return sprintf( '<del>%s</del> <ins>%s</ins>', $original, $discounted );
		}

		return $subtotal;
	}

	/**
	 * Maybe hide WooCommerce sale badge.
	 *
	 * @since    1.0.0
	 * @param    string     $html      Badge HTML.
	 * @param    WP_Post    $post      Post object.
	 * @param    WC_Product $product   Product object.
	 * @return   string                 Modified HTML.
	 */
	public function maybe_hide_sale_badge( string $html, $post, WC_Product $product ): string {
		if ( $this->discount_query->has_active_discount( $product->get_id() ) ) {
			return '';
		}

		return $html;
	}

	/**
	 * Add discount body class.
	 *
	 * @since    1.0.0
	 * @param    array $classes    Body classes.
	 * @return   array                Modified classes.
	 */
	public function add_discount_body_class( array $classes ): array {
		if ( is_product() ) {
			$product = wc_get_product( get_the_ID() );

			if ( $product && $this->discount_query->has_active_discount( $product->get_id() ) ) {
				$classes[] = 'scd-has-discount';
			}
		}

		return $classes;
	}

	/**
	 * Add discount post class.
	 *
	 * @since    1.0.0
	 * @param    array  $classes    Post classes.
	 * @param    string $class      Class string.
	 * @param    int    $post_id    Post ID.
	 * @return   array                Modified classes.
	 */
	public function add_discount_post_class( array $classes, $class, int $post_id ): array {
		if ( 'product' === get_post_type( $post_id ) ) {
			if ( $this->discount_query->has_active_discount( $post_id ) ) {
				$classes[] = 'scd-has-discount';
			}
		}

		return $classes;
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Display] ' . $message, $context );
		}
	}
}
