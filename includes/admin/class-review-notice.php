<?php
/**
 * Review Notice Class
 *
 * Displays non-intrusive admin notices asking for a plugin review
 * after the user has had meaningful engagement with the plugin.
 *
 * Two trigger types:
 * 1. Time-based: After 7+ days installed and 2+ campaigns created
 * 2. Success-based: After first sale is made through a campaign (priority)
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Review Notice Class
 *
 * Prompts users to leave a review after positive experiences:
 *
 * Success-based trigger (priority):
 * - First sale made through an SCD campaign
 * - Shows celebratory message with actual savings
 *
 * Time-based trigger (fallback):
 * - Plugin installed for at least 7 days
 * - User has created at least 2 campaigns
 *
 * Follows WordPress.org guidelines:
 * - Does NOT ask for specific star rating
 * - Provides permanent dismiss option
 * - Only shown once per trigger type per user
 * - Respectful, non-intrusive messaging
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class WSSCD_Review_Notice {

	/*
	|--------------------------------------------------------------------------
	| Time-Based Notice Constants
	|--------------------------------------------------------------------------
	*/

	/**
	 * Option key for installation timestamp.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const INSTALLED_OPTION = 'wsscd_installed_timestamp';

	/**
	 * User meta key for time-based notice dismissed state.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const DISMISSED_META = 'wsscd_review_notice_dismissed';

	/**
	 * User meta key for "remind later" timestamp.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const REMIND_LATER_META = 'wsscd_review_notice_remind_later';

	/**
	 * Minimum days since installation before showing time-based notice.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const MIN_DAYS_INSTALLED = 7;

	/**
	 * Minimum campaigns created before showing time-based notice.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const MIN_CAMPAIGNS = 2;

	/**
	 * Days to wait when user clicks "Remind Later".
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const REMIND_LATER_DAYS = 14;

	/*
	|--------------------------------------------------------------------------
	| Success-Based Notice Constants
	|--------------------------------------------------------------------------
	*/

	/**
	 * Option key for first sale data.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const FIRST_SALE_OPTION = 'wsscd_first_sale_data';

	/**
	 * User meta key for success notice dismissed state.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const FIRST_SALE_DISMISSED_META = 'wsscd_first_sale_notice_dismissed';

	/**
	 * Order item meta key used by SCD to indicate discount was applied.
	 *
	 * The SCD plugin stores discount data on ORDER ITEMS with these meta keys:
	 * - _wsscd_discount_applied = 'yes' (primary indicator)
	 * - _wsscd_campaign_id = campaign ID
	 * - _wsscd_discount_amount = discount amount
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const ORDER_ITEM_DISCOUNT_META = '_wsscd_discount_applied';

	/*
	|--------------------------------------------------------------------------
	| Properties
	|--------------------------------------------------------------------------
	*/

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null
	 */
	private $container = null;

	/**
	 * Which notice type to show (cached for current request).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string|null    'success', 'time', or null
	 */
	private $notice_type_to_show = null;

	/*
	|--------------------------------------------------------------------------
	| Initialization
	|--------------------------------------------------------------------------
	*/

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    object|null $container    Optional container instance.
	 */
	public function __construct( $container = null ) {
		$this->container = $container;
	}

	/**
	 * Initialize the review notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		// Record installation time if not set.
		$this->maybe_record_install_time();

		// Hook into WooCommerce order completion to track first sale.
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_track_first_sale' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_track_first_sale' ), 10, 2 );

		// Register admin notice hooks.
		add_action( 'admin_notices', array( $this, 'maybe_display_notice' ) );

		// Time-based notice AJAX handlers.
		add_action( 'wp_ajax_wsscd_dismiss_review_notice', array( $this, 'handle_dismiss' ) );
		add_action( 'wp_ajax_wsscd_remind_later_review', array( $this, 'handle_remind_later' ) );

		// Success-based notice AJAX handlers.
		add_action( 'wp_ajax_wsscd_dismiss_first_sale_notice', array( $this, 'handle_first_sale_dismiss' ) );

		// Enqueue inline script for AJAX handling.
		add_action( 'admin_footer', array( $this, 'output_dismiss_script' ) );
	}

	/**
	 * Record installation timestamp if not already set.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function maybe_record_install_time() {
		if ( ! get_option( self::INSTALLED_OPTION ) ) {
			update_option( self::INSTALLED_OPTION, time(), false );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| First Sale Tracking (WooCommerce Integration)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Track first sale when an order with SCD discount is completed.
	 *
	 * @since    1.0.0
	 * @param    int      $order_id    Order ID.
	 * @param    WC_Order $order       Order object (optional, for newer WC versions).
	 * @return   void
	 */
	public function maybe_track_first_sale( $order_id, $order = null ) {
		// Already tracked a first sale? Skip.
		if ( get_option( self::FIRST_SALE_OPTION ) ) {
			return;
		}

		// Get the order object if not provided.
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		// Check if this order has SCD discount data.
		$discount_data = $this->get_order_discount_data( $order );

		if ( empty( $discount_data ) ) {
			return;
		}

		// Extract sale details for the notice.
		$sale_data = array(
			'order_id'      => $order_id,
			'discount'      => $this->calculate_total_discount( $order, $discount_data ),
			'campaign_name' => $this->get_campaign_name_from_discount( $discount_data ),
			'currency'      => $order->get_currency(),
			'recorded_at'   => time(),
		);

		// Store the first sale data.
		update_option( self::FIRST_SALE_OPTION, $sale_data, false );
	}

	/**
	 * Get SCD discount data from an order.
	 *
	 * The SCD plugin stores discount data on ORDER ITEMS (line items), not on the order itself.
	 * It uses the following meta keys on each item:
	 * - _wsscd_discount_applied = 'yes' (primary indicator)
	 * - _wsscd_campaign_id = campaign ID
	 * - _wsscd_discount_amount = discount amount for this item
	 * - _wsscd_original_price = original price
	 * - _wsscd_discounted_price = discounted price
	 *
	 * @since    1.0.0
	 * @param    WC_Order $order    Order object.
	 * @return   array              Discount data or empty array.
	 */
	private function get_order_discount_data( $order ) {
		$discount_data = array(
			'has_scd_discount' => false,
			'campaign_id'      => 0,
			'total_discount'   => 0,
			'items'            => array(),
		);

		// Check order items for SCD discount meta.
		// This is where the SCD plugin actually stores discount data.
		foreach ( $order->get_items() as $item_id => $item ) {
			// Check the primary indicator: _wsscd_discount_applied = 'yes'.
			$discount_applied = $item->get_meta( '_wsscd_discount_applied' );

			if ( 'yes' === $discount_applied ) {
				$discount_data['has_scd_discount'] = true;

				// Get the campaign ID (use first found if multiple items).
				$campaign_id = $item->get_meta( '_wsscd_campaign_id' );
				if ( $campaign_id && ! $discount_data['campaign_id'] ) {
					$discount_data['campaign_id'] = absint( $campaign_id );
				}

				// Get the discount amount for this item.
				$item_discount = $item->get_meta( '_wsscd_discount_amount' );
				if ( $item_discount ) {
					$discount_data['total_discount'] += (float) $item_discount;
				}

				// Store item details.
				$discount_data['items'][ $item_id ] = array(
					'campaign_id'      => absint( $campaign_id ),
					'discount_amount'  => (float) $item_discount,
					'original_price'   => (float) $item->get_meta( '_wsscd_original_price' ),
					'discounted_price' => (float) $item->get_meta( '_wsscd_discounted_price' ),
				);
			}
		}

		// Return empty array if no SCD discount was found.
		if ( ! $discount_data['has_scd_discount'] ) {
			return array();
		}

		return $discount_data;
	}

	/**
	 * Calculate total discount from order.
	 *
	 * The discount data returned by get_order_discount_data() already includes
	 * the total_discount calculated from all item-level _wsscd_discount_amount values.
	 *
	 * @since    1.0.0
	 * @param    WC_Order $order          Order object.
	 * @param    array    $discount_data  Discount data from get_order_discount_data().
	 * @return   float                    Total discount amount.
	 */
	private function calculate_total_discount( $order, $discount_data ) {
		// Primary: use the pre-calculated total from item meta.
		if ( isset( $discount_data['total_discount'] ) && $discount_data['total_discount'] > 0 ) {
			return (float) $discount_data['total_discount'];
		}

		// Fallback: if we have items data, sum their discounts.
		if ( ! empty( $discount_data['items'] ) ) {
			$total = 0;
			foreach ( $discount_data['items'] as $item_data ) {
				if ( isset( $item_data['discount_amount'] ) ) {
					$total += (float) $item_data['discount_amount'];
				}
			}
			if ( $total > 0 ) {
				return $total;
			}
		}

		// Last fallback: re-scan order items directly.
		$total = 0;
		foreach ( $order->get_items() as $item ) {
			if ( 'yes' === $item->get_meta( '_wsscd_discount_applied' ) ) {
				$item_discount = $item->get_meta( '_wsscd_discount_amount' );
				if ( $item_discount ) {
					$total += (float) $item_discount;
				}
			}
		}

		return $total;
	}

	/**
	 * Get campaign name from discount data.
	 *
	 * The discount data includes campaign_id from the _wsscd_campaign_id item meta.
	 *
	 * @since    1.0.0
	 * @param    array $discount_data    Discount data from get_order_discount_data().
	 * @return   string                  Campaign name or empty string.
	 */
	private function get_campaign_name_from_discount( $discount_data ) {
		// Check if campaign_id is directly available in the data.
		if ( isset( $discount_data['campaign_id'] ) && $discount_data['campaign_id'] > 0 ) {
			return $this->get_campaign_name_by_id( absint( $discount_data['campaign_id'] ) );
		}

		// Fallback: check items for campaign_id.
		if ( ! empty( $discount_data['items'] ) ) {
			foreach ( $discount_data['items'] as $item_data ) {
				if ( isset( $item_data['campaign_id'] ) && $item_data['campaign_id'] > 0 ) {
					return $this->get_campaign_name_by_id( absint( $item_data['campaign_id'] ) );
				}
			}
		}

		return '';
	}

	/**
	 * Get campaign name by ID.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   string              Campaign name or empty string.
	 */
	private function get_campaign_name_by_id( $campaign_id ) {
		// Try container first.
		if ( $this->container && $this->container->has( 'campaign_repository' ) ) {
			try {
				$repo     = $this->container->get( 'campaign_repository' );
				$campaign = $repo->find( $campaign_id );
				if ( $campaign && method_exists( $campaign, 'get_name' ) ) {
					return $campaign->get_name();
				}
			} catch ( Exception $e ) {
				// Fall through to direct query.
			}
		}

		// Direct query fallback.
		global $wpdb;
		$table = $wpdb->prefix . 'wsscd_campaigns';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time lookup; table name from trusted $wpdb->prefix.
		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$table} WHERE id = %d",
				$campaign_id
			)
		);

		return $name ? sanitize_text_field( $name ) : '';
	}

	/*
	|--------------------------------------------------------------------------
	| Notice Display Logic
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if notice should be displayed and display it.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function maybe_display_notice() {
		// Only show on SCD admin pages.
		if ( ! $this->is_scd_admin_page() ) {
			return;
		}

		// Determine which notice to show (if any).
		$this->notice_type_to_show = $this->get_notice_type_to_show();

		if ( ! $this->notice_type_to_show ) {
			return;
		}

		// Display the appropriate notice.
		if ( 'success' === $this->notice_type_to_show ) {
			$this->render_success_notice();
		} else {
			$this->render_time_based_notice();
		}
	}

	/**
	 * Determine which notice type to show (if any).
	 *
	 * Success-based notice has priority over time-based.
	 *
	 * @since    1.0.0
	 * @return   string|null    'success', 'time', or null
	 */
	private function get_notice_type_to_show() {
		// Check user capability first.
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}

		// Priority 1: Success-based notice (first sale).
		if ( $this->should_show_success_notice( $user_id ) ) {
			return 'success';
		}

		// Priority 2: Time-based notice.
		if ( $this->should_show_time_based_notice( $user_id ) ) {
			return 'time';
		}

		return null;
	}

	/**
	 * Check if success-based notice should be shown.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    User ID.
	 * @return   bool
	 */
	private function should_show_success_notice( $user_id ) {
		// Check if first sale was tracked.
		$first_sale = get_option( self::FIRST_SALE_OPTION );
		if ( empty( $first_sale ) ) {
			return false;
		}

		// Check if already dismissed.
		if ( get_user_meta( $user_id, self::FIRST_SALE_DISMISSED_META, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if time-based notice should be shown.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    User ID.
	 * @return   bool
	 */
	private function should_show_time_based_notice( $user_id ) {
		// Check if permanently dismissed.
		if ( get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return false;
		}

		// Check "remind later" status.
		$remind_later = get_user_meta( $user_id, self::REMIND_LATER_META, true );
		if ( $remind_later && time() < (int) $remind_later ) {
			return false;
		}

		// Check installation time.
		$installed_time = get_option( self::INSTALLED_OPTION );
		if ( ! $installed_time ) {
			return false;
		}

		$days_installed = ( time() - (int) $installed_time ) / DAY_IN_SECONDS;
		if ( $days_installed < self::MIN_DAYS_INSTALLED ) {
			return false;
		}

		// Check campaign count.
		$campaign_count = $this->get_campaign_count();
		if ( $campaign_count < self::MIN_CAMPAIGNS ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if current page is an SCD admin page.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	private function is_scd_admin_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Don't show on wizard pages - they break the layout.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display context check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display context check.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'wsscd-campaigns' === $page && 'wizard' === $action ) {
			return false;
		}

		return strpos( $screen->id, 'smart-cycle-discounts' ) !== false ||
			   strpos( $screen->id, 'wsscd' ) !== false;
	}

	/**
	 * Get the total number of campaigns created.
	 *
	 * @since    1.0.0
	 * @return   int    Campaign count.
	 */
	private function get_campaign_count() {
		// Try to get from container first.
		if ( $this->container && $this->container->has( 'campaign_repository' ) ) {
			try {
				$repo = $this->container->get( 'campaign_repository' );
				if ( method_exists( $repo, 'count' ) ) {
					return (int) $repo->count();
				}
			} catch ( Exception $e ) {
				// Fall through to direct query.
			}
		}

		// Fallback: direct database query.
		global $wpdb;
		$table = $wpdb->prefix . 'wsscd_campaigns';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time check for review notice.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( ! $table_exists ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time count; table name from trusted $wpdb->prefix, no user input.
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL"
		);

		return (int) $count;
	}

	/*
	|--------------------------------------------------------------------------
	| Notice Rendering
	|--------------------------------------------------------------------------
	*/

	/**
	 * Output shared notice styles.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function output_notice_styles() {
		?>
		<style>
			.wsscd-review-notice {
				border-left-color: var(--wsscd-color-primary, var(--wp-admin-theme-color, #2271b1));
				padding: var(--wsscd-padding-compact, 12px) var(--wsscd-padding-medium, 15px);
				background: var(--wsscd-color-white, #fff);
			}
			.wsscd-review-notice.wsscd-review-notice--success {
				border-left-color: var(--wsscd-color-success, #00a32a);
			}
			.wsscd-review-notice-content {
				display: flex;
				align-items: flex-start;
				gap: var(--wsscd-gap-comfortable, 15px);
			}
			.wsscd-review-notice-icon {
				font-size: var(--wsscd-icon-display, 32px);
				line-height: 1;
				flex-shrink: 0;
			}
			.wsscd-review-notice-text {
				flex: 1;
			}
			.wsscd-review-notice-text p {
				margin: 0 0 var(--wsscd-spacing-sm, 10px) 0;
				color: var(--wsscd-color-text, #1d2327);
			}
			.wsscd-review-notice-text p:last-of-type {
				margin-bottom: var(--wsscd-spacing-md, 12px);
			}
			.wsscd-review-notice-highlight {
				background: var(--wsscd-color-success-alpha-15, rgba(0, 163, 42, 0.15));
				padding: 2px 8px;
				border-radius: var(--wsscd-radius-sm, 4px);
				font-weight: 600;
				color: var(--wsscd-color-success-dark, #008a20);
			}
			.wsscd-review-notice-actions {
				display: flex;
				gap: var(--wsscd-gap-sm-medium, 10px);
				flex-wrap: wrap;
			}
			.wsscd-review-notice-actions .button {
				display: inline-flex;
				align-items: center;
				gap: var(--wsscd-spacing-xs, 5px);
			}
			.wsscd-review-notice-actions .button-primary {
				background: var(--wsscd-color-primary, var(--wp-admin-theme-color, #2271b1));
				border-color: var(--wsscd-color-primary, var(--wp-admin-theme-color, #2271b1));
			}
			.wsscd-review-notice-actions .button-primary:hover,
			.wsscd-review-notice-actions .button-primary:focus {
				background: var(--wsscd-color-primary-dark, var(--wp-admin-theme-color-darker-10, #135e96));
				border-color: var(--wsscd-color-primary-dark, var(--wp-admin-theme-color-darker-10, #135e96));
			}
			.wsscd-review-notice--success .button-primary {
				background: var(--wsscd-color-success, #00a32a);
				border-color: var(--wsscd-color-success, #00a32a);
			}
			.wsscd-review-notice--success .button-primary:hover,
			.wsscd-review-notice--success .button-primary:focus {
				background: var(--wsscd-color-success-dark, #008a20);
				border-color: var(--wsscd-color-success-dark, #008a20);
			}
		</style>
		<?php
	}

	/**
	 * Render the success-based notice (first sale celebration).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_success_notice() {
		$first_sale = get_option( self::FIRST_SALE_OPTION );
		$review_url = 'https://wordpress.org/support/plugin/smart-cycle-discounts/reviews/#new-post';
		$nonce      = wp_create_nonce( 'wsscd_first_sale_notice' );

		// Format the discount amount.
		$discount      = isset( $first_sale['discount'] ) ? (float) $first_sale['discount'] : 0;
		$currency      = isset( $first_sale['currency'] ) ? $first_sale['currency'] : get_woocommerce_currency();
		$campaign_name = isset( $first_sale['campaign_name'] ) ? $first_sale['campaign_name'] : '';

		// Format discount for display.
		$formatted_discount = wc_price( $discount, array( 'currency' => $currency ) );

		$this->output_notice_styles();
		?>
		<div class="notice notice-success wsscd-review-notice wsscd-review-notice--success" id="wsscd-first-sale-notice">
			<div class="wsscd-review-notice-content">
				<div class="wsscd-review-notice-icon">🎉</div>
				<div class="wsscd-review-notice-text">
					<p>
						<strong>
							<?php esc_html_e( 'Congratulations! Your first sale with Smart Cycle Discounts!', 'smart-cycle-discounts' ); ?>
						</strong>
					</p>
					<p>
						<?php
						if ( $campaign_name && $discount > 0 ) {
							echo wp_kses(
								sprintf(
									/* translators: 1: formatted discount amount, 2: campaign name */
									__( 'A customer just saved %1$s using your "%2$s" campaign. Your discounts are working!', 'smart-cycle-discounts' ),
									'<span class="wsscd-review-notice-highlight">' . wp_kses_post( $formatted_discount ) . '</span>',
									esc_html( $campaign_name )
								),
								array(
									'span' => array( 'class' => array() ),
								)
							);
						} elseif ( $discount > 0 ) {
							echo wp_kses(
								sprintf(
									/* translators: %s: formatted discount amount */
									__( 'A customer just saved %s with your discount campaign. Your discounts are working!', 'smart-cycle-discounts' ),
									'<span class="wsscd-review-notice-highlight">' . wp_kses_post( $formatted_discount ) . '</span>'
								),
								array(
									'span' => array( 'class' => array() ),
								)
							);
						} else {
							esc_html_e( 'A customer just used one of your discount campaigns. Your discounts are working!', 'smart-cycle-discounts' );
						}
						?>
					</p>
					<p>
						<?php esc_html_e( 'If you\'re enjoying the plugin, would you share your experience with a quick review?', 'smart-cycle-discounts' ); ?>
					</p>
					<div class="wsscd-review-notice-actions">
						<a href="<?php echo esc_url( $review_url ); ?>"
						   class="button button-primary"
						   target="_blank"
						   rel="noopener noreferrer"
						   id="wsscd-first-sale-review">
							<span class="dashicons dashicons-star-filled" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Celebrate with a Review', 'smart-cycle-discounts' ); ?>
						</a>
						<button type="button"
								class="button button-link"
								id="wsscd-first-sale-dismiss"
								data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<?php esc_html_e( 'Dismiss', 'smart-cycle-discounts' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the time-based notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_time_based_notice() {
		$campaign_count = $this->get_campaign_count();
		$review_url     = 'https://wordpress.org/support/plugin/smart-cycle-discounts/reviews/#new-post';
		$nonce          = wp_create_nonce( 'wsscd_review_notice' );

		$this->output_notice_styles();
		?>
		<div class="notice notice-info wsscd-review-notice" id="wsscd-review-notice">
			<div class="wsscd-review-notice-content">
				<div class="wsscd-review-notice-icon">💪</div>
				<div class="wsscd-review-notice-text">
					<p>
						<strong>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of campaigns created */
									_n(
										"You've created %d campaign with Smart Cycle Discounts!",
										"You've created %d campaigns with Smart Cycle Discounts!",
										$campaign_count,
										'smart-cycle-discounts'
									),
									$campaign_count
								)
							);
							?>
						</strong>
					</p>
					<p>
						<?php esc_html_e( 'If the plugin has been helpful for your store, would you consider leaving a quick review? It helps other store owners discover us and keeps development going.', 'smart-cycle-discounts' ); ?>
					</p>
					<div class="wsscd-review-notice-actions">
						<a href="<?php echo esc_url( $review_url ); ?>"
						   class="button button-primary"
						   target="_blank"
						   rel="noopener noreferrer"
						   id="wsscd-review-now">
							<span class="dashicons dashicons-star-filled" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Leave a Review', 'smart-cycle-discounts' ); ?>
						</a>
						<button type="button"
								class="button button-secondary"
								id="wsscd-review-later"
								data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<span class="dashicons dashicons-clock" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Maybe Later', 'smart-cycle-discounts' ); ?>
						</button>
						<button type="button"
								class="button button-link"
								id="wsscd-review-dismiss"
								data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<?php esc_html_e( "Don't Show Again", 'smart-cycle-discounts' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| JavaScript
	|--------------------------------------------------------------------------
	*/

	/**
	 * Output the dismiss script in admin footer.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function output_dismiss_script() {
		// Only output if a notice is shown.
		if ( ! $this->is_scd_admin_page() || ! $this->notice_type_to_show ) {
			return;
		}
		?>
		<script>
		(function() {
			'use strict';

			/**
			 * Hide a notice with fade animation.
			 *
			 * @param {HTMLElement} notice The notice element to hide.
			 */
			function hideNotice(notice) {
				if (!notice) return;
				notice.style.transition = 'opacity 0.3s ease';
				notice.style.opacity = '0';
				setTimeout(function() {
					notice.style.display = 'none';
				}, 300);
			}

			/**
			 * Send AJAX request.
			 *
			 * @param {string} action The AJAX action.
			 * @param {string} nonce  The security nonce.
			 */
			function sendAjax(action, nonce) {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxurl, true);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.send('action=' + action + '&_wpnonce=' + nonce);
			}

			// Success-based notice (first sale).
			var firstSaleNotice = document.getElementById('wsscd-first-sale-notice');
			if (firstSaleNotice) {
				var firstSaleReview = document.getElementById('wsscd-first-sale-review');
				var firstSaleDismiss = document.getElementById('wsscd-first-sale-dismiss');

				if (firstSaleReview) {
					firstSaleReview.addEventListener('click', function() {
						sendAjax('wsscd_dismiss_first_sale_notice', firstSaleDismiss.dataset.nonce);
						hideNotice(firstSaleNotice);
					});
				}

				if (firstSaleDismiss) {
					firstSaleDismiss.addEventListener('click', function() {
						sendAjax('wsscd_dismiss_first_sale_notice', this.dataset.nonce);
						hideNotice(firstSaleNotice);
					});
				}
			}

			// Time-based notice.
			var reviewNotice = document.getElementById('wsscd-review-notice');
			if (reviewNotice) {
				var reviewNow = document.getElementById('wsscd-review-now');
				var remindLater = document.getElementById('wsscd-review-later');
				var dismiss = document.getElementById('wsscd-review-dismiss');

				if (reviewNow && dismiss) {
					reviewNow.addEventListener('click', function() {
						sendAjax('wsscd_dismiss_review_notice', dismiss.dataset.nonce);
						hideNotice(reviewNotice);
					});
				}

				if (remindLater) {
					remindLater.addEventListener('click', function() {
						sendAjax('wsscd_remind_later_review', this.dataset.nonce);
						hideNotice(reviewNotice);
					});
				}

				if (dismiss) {
					dismiss.addEventListener('click', function() {
						sendAjax('wsscd_dismiss_review_notice', this.dataset.nonce);
						hideNotice(reviewNotice);
					});
				}
			}
		})();
		</script>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| AJAX Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Handle permanent dismiss AJAX request for time-based notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_dismiss() {
		check_ajax_referer( 'wsscd_review_notice', '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-cycle-discounts' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'smart-cycle-discounts' ) ) );
		}

		update_user_meta( $user_id, self::DISMISSED_META, time() );
		delete_user_meta( $user_id, self::REMIND_LATER_META );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'smart-cycle-discounts' ) ) );
	}

	/**
	 * Handle "remind later" AJAX request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_remind_later() {
		check_ajax_referer( 'wsscd_review_notice', '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-cycle-discounts' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'smart-cycle-discounts' ) ) );
		}

		$remind_time = time() + ( self::REMIND_LATER_DAYS * DAY_IN_SECONDS );
		update_user_meta( $user_id, self::REMIND_LATER_META, $remind_time );

		wp_send_json_success( array( 'message' => __( 'Reminder set.', 'smart-cycle-discounts' ) ) );
	}

	/**
	 * Handle dismiss AJAX request for first sale notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_first_sale_dismiss() {
		check_ajax_referer( 'wsscd_first_sale_notice', '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-cycle-discounts' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'smart-cycle-discounts' ) ) );
		}

		update_user_meta( $user_id, self::FIRST_SALE_DISMISSED_META, time() );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'smart-cycle-discounts' ) ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Static Methods (Activation/Uninstall)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Static method to record installation time.
	 * Called during plugin activation.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function on_activation() {
		if ( ! get_option( self::INSTALLED_OPTION ) ) {
			update_option( self::INSTALLED_OPTION, time(), false );
		}
	}

	/**
	 * Static method to clean up on plugin uninstall.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function on_uninstall() {
		global $wpdb;

		// Remove options.
		delete_option( self::INSTALLED_OPTION );
		delete_option( self::FIRST_SALE_OPTION );

		// Remove user meta for all users.
		$meta_keys = array(
			self::DISMISSED_META,
			self::REMIND_LATER_META,
			self::FIRST_SALE_DISMISSED_META,
		);

		foreach ( $meta_keys as $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => $meta_key ),
				array( '%s' )
			);
		}
	}
}
