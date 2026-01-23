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
class WSSCD_WC_Display_Integration {

	/**
	 * Discount query service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_WC_Discount_Query_Service    $discount_query
	 */
	private WSSCD_WC_Discount_Query_Service $discount_query;

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
	 * @param    WSSCD_WC_Discount_Query_Service $discount_query    Discount query service.
	 * @param    object|null                   $logger            Logger.
	 */
	public function __construct( WSSCD_WC_Discount_Query_Service $discount_query, ?object $logger = null ) {
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
		// Reset badge injection flag before each product gallery renders
		add_action( 'woocommerce_before_single_product_summary', array( $this, 'reset_badge_injection_flag' ), 1 );

		// Product page display - badges on product image
		// Use woocommerce_single_product_image_thumbnail_html filter to inject badge
		// into the main gallery image (first image only)
		add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'inject_badge_into_gallery_image' ), 10, 2 );

		// Product page display - discount details (tiered, bogo, etc.)
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_discount_details' ), 25 );

		// Shop loop display - badges on product image
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'display_shop_badge_on_image' ), 15 );

		// Cart display
		add_filter( 'woocommerce_cart_item_price', array( $this, 'display_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_cart_item_subtotal' ), 10, 3 );

		// Theme compatibility - use high priority to run after themes.
		add_filter( 'woocommerce_sale_flash', array( $this, 'maybe_hide_sale_badge' ), 99, 3 );
		add_filter( 'body_class', array( $this, 'add_discount_body_class' ) );
		add_filter( 'post_class', array( $this, 'add_discount_post_class' ), 10, 3 );
	}

	/**
	 * Reset badge injection flag.
	 *
	 * Called before each product gallery renders to ensure the badge
	 * is only injected into the first image of each product.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function reset_badge_injection_flag(): void {
		$this->badge_injected_into_gallery = false;
	}

	/**
	 * Track if we've already injected the badge into the first gallery image.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private bool $badge_injected_into_gallery = false;

	/**
	 * Inject badge into gallery image HTML (single product page).
	 *
	 * Uses woocommerce_single_product_image_thumbnail_html filter to inject
	 * the badge directly into the first gallery image's wrapper div.
	 * This ensures proper positioning within the gallery container.
	 *
	 * @since    1.0.0
	 * @param    mixed      $html             The image HTML (can be null from some filters).
	 * @param    int|string $attachment_id    The attachment ID (can be string from WooCommerce).
	 * @return   string                       Modified HTML with badge.
	 */
	public function inject_badge_into_gallery_image( $html, $attachment_id ): string {
		// Handle null or non-string HTML from some themes/plugins.
		if ( null === $html ) {
			$html = '';
		}

		// Only inject badge into the first image (main product image).
		if ( $this->badge_injected_into_gallery ) {
			return (string) $html;
		}

		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return $html;
		}

		// Get badge HTML
		$badge_html = $this->get_badge_for_context( $product, 'single-image' );

		if ( empty( $badge_html ) ) {
			return $html;
		}

		// Mark as injected so we don't add to subsequent gallery images
		$this->badge_injected_into_gallery = true;

		// Wrap the image and badge in a positioned container
		return '<div class="wsscd-gallery-image-wrapper" style="position: relative;">' . $html . $badge_html . '</div>';
	}

	/**
	 * Get badge HTML for a specific context.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product    Product object.
	 * @param    string     $context    Display context (single-image, shop).
	 * @return   string                 Badge HTML or empty string.
	 */
	private function get_badge_for_context( WC_Product $product, string $context ): string {
		$product_id = $product->get_id();

		try {
			$badge_info = $this->discount_query->get_campaign_badge_info( $product_id );

			if ( ! $badge_info ) {
				return '';
			}

			// Check if badge display is enabled for this campaign.
			if ( empty( $badge_info['badge_enabled'] ) ) {
				return '';
			}

			return $this->get_badge_html( $badge_info, $context );

		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to get badge for context',
				array(
					'product_id' => $product_id,
					'context'    => $context,
					'error'      => $e->getMessage(),
				)
			);
			return '';
		}
	}

	/**
	 * Display discount badge on shop loop product image.
	 *
	 * Hooked to woocommerce_before_shop_loop_item_title to position badge on product cards.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_shop_badge_on_image(): void {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		$badge_html = $this->get_badge_for_context( $product, 'shop' );

		if ( ! empty( $badge_html ) ) {
			echo wp_kses_post( $badge_html );
		}
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

			// Use badge info to get full tier/threshold data
			$badge_info = $this->discount_query->get_campaign_badge_info( $product_id );

			if ( $badge_info ) {
				$discount_type = $badge_info['type'];

				if ( in_array( $discount_type, array( 'tiered', 'bogo', 'spend_threshold' ), true ) ) {
					echo wp_kses_post( $this->render_discount_details( $badge_info ) );
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to display product discount details',
				array(
					'product_id' => $product_id ?? 0,
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

		// Check for custom badge text first
		$badge_text = $badge_info['badge_text'] ?? 'auto';
		if ( 'auto' !== $badge_text && ! empty( $badge_text ) ) {
			// Use custom text
			$text = $badge_text;
		} else {
			// Auto-generate badge text based on discount type
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
						// Use plain text currency formatting (not HTML) for badge text.
						$currency_symbol = get_woocommerce_currency_symbol();
						$formatted_value = number_format( (float) $value, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
						$currency_pos    = get_option( 'woocommerce_currency_pos', 'left' );
						$price_text      = in_array( $currency_pos, array( 'left', 'left_space' ), true )
							? $currency_symbol . $formatted_value
							: $formatted_value . $currency_symbol;

						$text = sprintf(
							/* translators: %s: discount amount */
							__( 'Save %s', 'smart-cycle-discounts' ),
							$price_text
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
			'wsscd-discount-badge',
			'wsscd-badge-' . esc_attr( $context ),
			'wsscd-badge-' . esc_attr( $type ),
			'wsscd-badge-position-' . esc_attr( $position ),
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
		$type    = $discount_info['type'] ?? '';
		$content = '';

		switch ( $type ) {
			case 'tiered':
				$content = $this->render_tiered_details( $discount_info );
				break;
			case 'bogo':
				$content = $this->render_bogo_details( $discount_info );
				break;
			case 'spend_threshold':
				$content = $this->render_spend_threshold_details( $discount_info );
				break;
		}

		if ( empty( $content ) ) {
			return '';
		}

		return '<div class="wsscd-discount-details">' . $content . '</div>';
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
		$tiers = $discount_info['tiers'] ?? array();

		if ( empty( $tiers ) ) {
			return '<p>' . esc_html__( 'Volume discount available', 'smart-cycle-discounts' ) . '</p>';
		}

		// Sort tiers by min_quantity ascending
		usort(
			$tiers,
			function ( $a, $b ) {
				return ( $a['min_quantity'] ?? 0 ) <=> ( $b['min_quantity'] ?? 0 );
			}
		);

		ob_start();
		?>
		<div class="wsscd-tier-table-wrapper">
			<p class="wsscd-tier-table-title">
				<?php esc_html_e( 'Buy More, Save More!', 'smart-cycle-discounts' ); ?>
			</p>
			<table class="wsscd-tier-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Quantity', 'smart-cycle-discounts' ); ?></th>
						<th><?php esc_html_e( 'Discount', 'smart-cycle-discounts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tiers as $tier ) : ?>
						<?php
						$min_qty       = isset( $tier['min_quantity'] ) ? absint( $tier['min_quantity'] ) : 0;
						$discount_type = $tier['discount_type'] ?? 'percentage';
						$discount_val  = isset( $tier['discount_value'] ) ? floatval( $tier['discount_value'] ) : 0;

						// Format discount display
						if ( 'percentage' === $discount_type ) {
							$discount_display = sprintf(
								/* translators: %s: discount percentage */
								__( '%s%% off', 'smart-cycle-discounts' ),
								number_format( $discount_val, $discount_val == floor( $discount_val ) ? 0 : 2 )
							);
						} else {
							$discount_display = sprintf(
								/* translators: %s: discount amount */
								__( '%s off', 'smart-cycle-discounts' ),
								wp_strip_all_tags( wc_price( $discount_val ) )
							);
						}

						// Format quantity display
						$qty_display = sprintf(
							/* translators: %d: minimum quantity */
							__( '%d+ items', 'smart-cycle-discounts' ),
							$min_qty
						);
						?>
						<tr>
							<td><?php echo esc_html( $qty_display ); ?></td>
							<td class="wsscd-tier-discount"><?php echo esc_html( $discount_display ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
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
		$buy_qty     = isset( $discount_info['buy_quantity'] ) ? absint( $discount_info['buy_quantity'] ) : 1;
		$get_qty     = isset( $discount_info['get_quantity'] ) ? absint( $discount_info['get_quantity'] ) : 1;
		$get_percent = isset( $discount_info['get_discount_percentage'] ) ? absint( $discount_info['get_discount_percentage'] ) : 100;

		// Build the offer description
		if ( 100 === $get_percent ) {
			$offer_text = sprintf(
				/* translators: 1: buy quantity, 2: get quantity */
				__( 'Buy %1$d, Get %2$d FREE!', 'smart-cycle-discounts' ),
				$buy_qty,
				$get_qty
			);
		} else {
			$offer_text = sprintf(
				/* translators: 1: buy quantity, 2: get quantity, 3: discount percentage */
				__( 'Buy %1$d, Get %2$d at %3$d%% OFF!', 'smart-cycle-discounts' ),
				$buy_qty,
				$get_qty,
				$get_percent
			);
		}

		ob_start();
		?>
		<div class="wsscd-bogo-details">
			<p class="wsscd-bogo-offer"><?php echo esc_html( $offer_text ); ?></p>
		</div>
		<?php
		return ob_get_clean();
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
		$thresholds = $discount_info['thresholds'] ?? array();

		if ( empty( $thresholds ) ) {
			return '<p>' . esc_html__( 'Spend threshold discount available', 'smart-cycle-discounts' ) . '</p>';
		}

		// Sort thresholds by spend_amount ascending
		usort(
			$thresholds,
			function ( $a, $b ) {
				return ( $a['spend_amount'] ?? 0 ) <=> ( $b['spend_amount'] ?? 0 );
			}
		);

		ob_start();
		?>
		<div class="wsscd-tier-table-wrapper">
			<p class="wsscd-tier-table-title">
				<?php esc_html_e( 'Spend More, Save More!', 'smart-cycle-discounts' ); ?>
			</p>
			<table class="wsscd-tier-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Spend', 'smart-cycle-discounts' ); ?></th>
						<th><?php esc_html_e( 'You Get', 'smart-cycle-discounts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $thresholds as $threshold ) : ?>
						<?php
						$spend_amount  = isset( $threshold['spend_amount'] ) ? floatval( $threshold['spend_amount'] ) : 0;
						$discount_type = $threshold['discount_type'] ?? 'percentage';
						$discount_val  = isset( $threshold['discount_value'] ) ? floatval( $threshold['discount_value'] ) : 0;

						// Format discount display
						if ( 'percentage' === $discount_type ) {
							$discount_display = sprintf(
								/* translators: %s: discount percentage */
								__( '%s%% off', 'smart-cycle-discounts' ),
								number_format( $discount_val, $discount_val == floor( $discount_val ) ? 0 : 2 )
							);
						} else {
							$discount_display = sprintf(
								/* translators: %s: discount amount */
								__( '%s off', 'smart-cycle-discounts' ),
								wp_strip_all_tags( wc_price( $discount_val ) )
							);
						}

						// Format spend display
						$spend_display = sprintf(
							/* translators: %s: spend amount */
							__( '%s+', 'smart-cycle-discounts' ),
							wp_strip_all_tags( wc_price( $spend_amount ) )
						);
						?>
						<tr>
							<td><?php echo esc_html( $spend_display ); ?></td>
							<td class="wsscd-tier-discount"><?php echo esc_html( $discount_display ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Display cart item price with discount strikethrough.
	 *
	 * @since    1.0.0
	 * @param    mixed $price           Price HTML (can be null from some filters).
	 * @param    mixed $cart_item       Cart item data (may be other types from some filters).
	 * @param    mixed $cart_item_key   Cart item key.
	 * @return   string                 Modified price HTML.
	 */
	public function display_cart_item_price( $price, $cart_item, $cart_item_key ): string {
		// Handle null or non-string price.
		if ( null === $price ) {
			$price = '';
		}

		// Ensure we have valid cart item data.
		if ( ! is_array( $cart_item ) ) {
			return (string) $price;
		}

		if ( isset( $cart_item['wsscd_discount'] ) ) {
			$discount   = $cart_item['wsscd_discount'];
			$original   = wc_price( $discount['original_price'] );
			$discounted = wc_price( $discount['discounted_price'] );

			return sprintf( '<del>%s</del> <ins>%s</ins>', $original, $discounted );
		}

		return (string) $price;
	}

	/**
	 * Display cart item subtotal with discount strikethrough.
	 *
	 * @since    1.0.0
	 * @param    mixed $subtotal        Subtotal HTML (can be null from some filters).
	 * @param    mixed $cart_item       Cart item data (may be other types from some filters).
	 * @param    mixed $cart_item_key   Cart item key.
	 * @return   string                 Modified subtotal HTML.
	 */
	public function display_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ): string {
		// Handle null or non-string subtotal.
		if ( null === $subtotal ) {
			$subtotal = '';
		}

		// Ensure we have valid cart item data.
		if ( ! is_array( $cart_item ) ) {
			return (string) $subtotal;
		}

		if ( isset( $cart_item['wsscd_discount'] ) ) {
			$discount = $cart_item['wsscd_discount'];
			$quantity = $cart_item['quantity'];

			$original_total   = $discount['original_price'] * $quantity;
			$discounted_total = $discount['discounted_price'] * $quantity;

			$original   = wc_price( $original_total );
			$discounted = wc_price( $discounted_total );

			return sprintf( '<del>%s</del> <ins>%s</ins>', $original, $discounted );
		}

		return (string) $subtotal;
	}

	/**
	 * Maybe hide WooCommerce sale badge.
	 *
	 * Only hides the theme sale badge if the product has an active SCD discount
	 * AND the campaign has badge display enabled. If badge is disabled,
	 * the theme's default sale badge will show instead.
	 *
	 * @since    1.0.0
	 * @param    string|null $html      Badge HTML (can be null from some themes).
	 * @param    WP_Post     $post      Post object.
	 * @param    WC_Product  $product   Product object (may be other types from some filters).
	 * @return   string                 Modified HTML.
	 */
	public function maybe_hide_sale_badge( $html, $post, $product ): string {
		// Handle null or non-string HTML from some themes/plugins.
		if ( null === $html ) {
			$html = '';
		}

		// Ensure we have a valid WC_Product instance.
		if ( ! $product instanceof WC_Product ) {
			return (string) $html;
		}

		// Only hide theme badge if WSSCD badge is enabled for this product.
		if ( $this->discount_query->has_wsscd_badge_enabled( $product->get_id() ) ) {
			return '';
		}

		return (string) $html;
	}

	/**
	 * Add discount body class.
	 *
	 * Only adds 'wsscd-has-discount' class if the campaign has badge display enabled.
	 * This ensures CSS rules for hiding theme badges only apply when SCD badge shows.
	 *
	 * @since    1.0.0
	 * @param    mixed $classes    Body classes (may be other types from some filters).
	 * @return   array             Modified classes.
	 */
	public function add_discount_body_class( $classes ): array {
		// Ensure we have an array.
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}

		if ( is_product() ) {
			$product = wc_get_product( get_the_ID() );

			if ( $product && $this->discount_query->has_wsscd_badge_enabled( $product->get_id() ) ) {
				$classes[] = 'wsscd-has-discount';
			}
		}

		return $classes;
	}

	/**
	 * Add discount post class.
	 *
	 * Only adds 'wsscd-has-discount' class if the campaign has badge display enabled.
	 * This ensures CSS rules for hiding theme badges only apply when SCD badge shows.
	 *
	 * @since    1.0.0
	 * @param    mixed $classes    Post classes (may be other types from some filters).
	 * @param    mixed $class      Class string.
	 * @param    mixed $post_id    Post ID (may be other types from some filters).
	 * @return   array             Modified classes.
	 */
	public function add_discount_post_class( $classes, $class, $post_id ): array {
		// Ensure we have an array.
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}

		// Ensure post_id is valid.
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return $classes;
		}

		if ( 'product' === get_post_type( $post_id ) ) {
			if ( $this->discount_query->has_wsscd_badge_enabled( $post_id ) ) {
				$classes[] = 'wsscd-has-discount';
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
