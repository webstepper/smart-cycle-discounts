<?php
/**
 * Analytics Collector Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-analytics-collector.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Load standardized response handler
require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-scd-ajax-response.php';

// Load analytics helpers trait
require_once SCD_PLUGIN_DIR . 'includes/core/analytics/trait-analytics-helpers.php';

/**
 * Analytics Collector
 *
 * Collects and processes analytics data for campaigns and discounts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Analytics_Collector {

	use SCD_Analytics_Helpers;

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $database_manager    Database manager.
	 */
	private SCD_Database_Manager $database_manager;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager    $cache_manager    Cache manager.
	 */
	private SCD_Cache_Manager $cache_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Analytics table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $analytics_table    Analytics table name.
	 */
	private string $analytics_table;

	/**
	 * Event types for analytics tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $event_types    Supported event types.
	 */
	private array $event_types = array(
		'campaign_view',
		'discount_view',
		'discount_click',
		'cart_add',
		'cart_remove',
		'checkout_start',
		'purchase_complete',
		'campaign_impression',
	);

	/**
	 * Initialize the analytics collector.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $database_manager    Database manager.
	 * @param    SCD_Cache_Manager    $cache_manager       Cache manager.
	 * @param    SCD_Logger           $logger              Logger instance.
	 */
	public function __construct(
		SCD_Database_Manager $database_manager,
		SCD_Cache_Manager $cache_manager,
		SCD_Logger $logger
	) {
		$this->database_manager = $database_manager;
		$this->cache_manager    = $cache_manager;
		$this->logger           = $logger;

		global $wpdb;
		$this->analytics_table = $wpdb->prefix . 'scd_analytics';
	}

	/**
	 * Initialize analytics collector.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Load AJAX security handler
		require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';

		$this->add_hooks();

		$this->logger->debug( 'Analytics collector initialized' );
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		// WooCommerce hooks for tracking
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_cart_add' ), 10, 6 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_cart_remove' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_purchase' ), 10, 3 );
		add_action( 'woocommerce_thankyou', array( $this, 'track_purchase_complete' ), 10, 1 );

		// Campaign tracking hooks
		add_action( 'scd_campaign_activated', array( $this, 'track_campaign_activation' ), 10, 1 );
		add_action( 'scd_campaign_deactivated', array( $this, 'track_campaign_deactivation' ), 10, 1 );
		add_action( 'scd_discount_applied', array( $this, 'track_discount_application' ), 10, 3 );

		// Frontend tracking hooks
		add_action( 'wp_footer', array( $this, 'add_tracking_script' ) );
		// AJAX tracking now handled by unified router

		// Analytics aggregation disabled - main analytics table is already pre-aggregated
		// @see includes/database/migrations/001-initial-schema.php
	}

	/**
	 * Track a generic analytics event.
	 *
	 * @since    1.0.0
	 * @param    string   $event_type     Event type.
	 * @param    array    $event_data     Event data.
	 * @param    int|null $campaign_id    Campaign ID.
	 * @param    int|null $user_id        User ID.
	 * @return   bool                      Success status.
	 */
	public function track_event(
		string $event_type,
		array $event_data = array(),
		?int $campaign_id = null,
		?int $user_id = null
	): bool {
		if ( ! in_array( $event_type, $this->event_types ) ) {
			$this->logger->warning(
				'Invalid event type for analytics tracking',
				array(
					'event_type'  => $event_type,
					'valid_types' => $this->event_types,
				)
			);
			return false;
		}

		try {
			$analytics_data = array(
				'event_type'   => $event_type,
				'campaign_id'  => $campaign_id,
				'user_id'      => $user_id ?: get_current_user_id(),
				'session_id'   => $this->get_session_id(),
				'event_data'   => wp_json_encode( $event_data ),
				'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'ip_address'   => $this->get_client_ip(),
				'referrer'     => $_SERVER['HTTP_REFERER'] ?? '',
				'page_url'     => $_SERVER['REQUEST_URI'] ?? '',
				'timestamp'    => current_time( 'mysql', true ),
				'date_created' => current_time( 'mysql' ),
			);

			$result = $this->database_manager->insert( $this->analytics_table, $analytics_data );

			if ( $result ) {
				$this->logger->debug(
					'Analytics event tracked',
					array(
						'event_type'  => $event_type,
						'campaign_id' => $campaign_id,
						'event_data'  => $event_data,
					)
				);

				// Invalidate relevant caches
				$this->invalidate_analytics_cache( $campaign_id );

				return true;
			}

			return false;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to track analytics event',
				array(
					'event_type' => $event_type,
					'error'      => $e->getMessage(),
					'event_data' => $event_data,
				)
			);

			return false;
		}
	}

	/**
	 * Track campaign view.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $context        Additional context.
	 * @return   bool                     Success status.
	 */
	public function track_campaign_view( int $campaign_id, array $context = array() ): bool {
		return $this->track_event( 'campaign_view', $context, $campaign_id );
	}

	/**
	 * Track discount view.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    int   $product_id     Product ID.
	 * @param    array $discount_data  Discount data.
	 * @return   bool                     Success status.
	 */
	public function track_discount_view( int $campaign_id, int $product_id, array $discount_data = array() ): bool {
		$event_data = array_merge(
			$discount_data,
			array(
				'product_id'       => $product_id,
				'discount_amount'  => $discount_data['discount_amount'] ?? 0,
				'original_price'   => $discount_data['original_price'] ?? 0,
				'discounted_price' => $discount_data['discounted_price'] ?? 0,
			)
		);

		return $this->track_event( 'discount_view', $event_data, $campaign_id );
	}

	/**
	 * Track discount click.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    int    $product_id     Product ID.
	 * @param    string $click_source   Click source (badge, banner, etc.).
	 * @return   bool                     Success status.
	 */
	public function track_discount_click( int $campaign_id, int $product_id, string $click_source = '' ): bool {
		$event_data = array(
			'product_id'   => $product_id,
			'click_source' => $click_source,
		);

		return $this->track_event( 'discount_click', $event_data, $campaign_id );
	}

	/**
	 * Track cart addition.
	 *
	 * @since    1.0.0
	 * @param    string $cart_item_key    Cart item key.
	 * @param    int    $product_id       Product ID.
	 * @param    int    $quantity         Quantity.
	 * @param    int    $variation_id     Variation ID.
	 * @param    array  $variation        Variation data.
	 * @param    array  $cart_item_data   Cart item data.
	 * @return   void
	 */
	public function track_cart_add(
		string $cart_item_key,
		int $product_id,
		int $quantity,
		int $variation_id,
		array $variation,
		array $cart_item_data
	): void {
		// Check if this product has an active discount
		$campaign_id = $this->get_active_campaign_for_product( $product_id );

		if ( $campaign_id ) {
			$event_data = array(
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'quantity'      => $quantity,
				'cart_item_key' => $cart_item_key,
				'has_discount'  => true,
			);

			$this->track_event( 'cart_add', $event_data, $campaign_id );
		}
	}

	/**
	 * Track cart removal.
	 *
	 * @since    1.0.0
	 * @param    string $cart_item_key    Cart item key.
	 * @param    object $cart             Cart object.
	 * @return   void
	 */
	public function track_cart_remove( string $cart_item_key, $cart ): void {
		if ( isset( $cart->cart_contents[ $cart_item_key ] ) ) {
			$cart_item  = $cart->cart_contents[ $cart_item_key ];
			$product_id = $cart_item['product_id'];

			$campaign_id = $this->get_active_campaign_for_product( $product_id );

			if ( $campaign_id ) {
				$event_data = array(
					'product_id'    => $product_id,
					'quantity'      => $cart_item['quantity'],
					'cart_item_key' => $cart_item_key,
				);

				$this->track_event( 'cart_remove', $event_data, $campaign_id );
			}
		}
	}

	/**
	 * Track purchase completion.
	 *
	 * @since    1.0.0
	 * @param    int    $order_id    Order ID.
	 * @param    array  $posted      Posted data.
	 * @param    object $order       Order object.
	 * @return   void
	 */
	public function track_purchase( int $order_id, array $posted, $order ): void {
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id  = $item->get_product_id();
			$campaign_id = $this->get_active_campaign_for_product( $product_id );

			if ( $campaign_id ) {
				$event_data = array(
					'order_id'        => $order_id,
					'product_id'      => $product_id,
					'quantity'        => $item->get_quantity(),
					'line_total'      => $item->get_total(),
					'line_subtotal'   => $item->get_subtotal(),
					'discount_amount' => $item->get_subtotal() - $item->get_total(),
				);

				$this->track_event( 'purchase_complete', $event_data, $campaign_id );
			}
		}
	}

	/**
	 * Track purchase completion (thank you page).
	 *
	 * @since    1.0.0
	 * @param    int $order_id    Order ID.
	 * @return   void
	 */
	public function track_purchase_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Track conversion for campaigns
		foreach ( $order->get_items() as $item ) {
			$product_id  = $item->get_product_id();
			$campaign_id = $this->get_active_campaign_for_product( $product_id );

			if ( $campaign_id ) {
				$this->update_campaign_conversion_metrics( $campaign_id, $order, $item );
			}
		}
	}

	/**
	 * Track campaign activation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	public function track_campaign_activation( $campaign ): void {
		if ( ! $campaign ) {
			return;
		}

		$this->logger->info(
			'Campaign activated',
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
			)
		);

		// Invalidate analytics cache for this campaign
		$this->invalidate_analytics_cache( $campaign->get_id() );
	}

	/**
	 * Track campaign deactivation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	public function track_campaign_deactivation( $campaign ): void {
		if ( ! $campaign ) {
			return;
		}

		$this->logger->info(
			'Campaign deactivated',
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
			)
		);

		// Invalidate analytics cache for this campaign
		$this->invalidate_analytics_cache( $campaign->get_id() );
	}

	/**
	 * Track discount application.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    int   $product_id        Product ID.
	 * @param    float $discount_amount   Discount amount.
	 * @return   void
	 */
	public function track_discount_application( int $campaign_id, int $product_id, float $discount_amount ): void {
		$event_data = array(
			'product_id'      => $product_id,
			'discount_amount' => $discount_amount,
		);

		$this->track_event( 'discount_view', $event_data, $campaign_id );
	}

	/**
	 * Handle AJAX event tracking.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_track_event(): void {
		// Security check using centralized handler
		$result = SCD_Ajax_Security::verify_ajax_request( 'scd_track_event', $_POST );

		if ( is_wp_error( $result ) ) {
			SCD_AJAX_Response::wp_error( $result );
			return;
		}

		$event_type  = sanitize_text_field( $_POST['event_type'] ?? '' );
		$campaign_id = absint( $_POST['campaign_id'] ?? 0 );
		$event_data  = $_POST['event_data'] ?? array();

		// Sanitize event data
		$sanitized_data = array();
		foreach ( $event_data as $key => $value ) {
			$sanitized_data[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		$result = $this->track_event( $event_type, $sanitized_data, $campaign_id ?: null );

		if ( $result ) {
			SCD_AJAX_Response::success( array( 'message' => 'Event tracked successfully' ) );
		} else {
			SCD_AJAX_Response::error( 'event_tracking_failed', __( 'Failed to track event', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Add tracking script to frontend.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_tracking_script(): void {
		if ( is_admin() ) {
			return;
		}

		// Tracking is now handled by the centralized asset management system
		// The script is registered as 'scd-analytics-tracking' and will be enqueued
		// by the frontend asset manager with proper localization data
	}

	/**
	 * Get analytics data for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $date_range     Date range.
	 * @param    array  $metrics        Specific metrics to retrieve.
	 * @return   array                     Analytics data.
	 */
	public function get_campaign_analytics(
		int $campaign_id,
		string $date_range = '7days',
		array $metrics = array()
	): array {
		$cache_key = "scd_analytics_campaign_{$campaign_id}_{$date_range}_" . md5( serialize( $metrics ) );

		$cached_data = $this->cache_manager->get( $cache_key );
		if ( $cached_data !== false ) {
			return $cached_data;
		}

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			$analytics_data = array(
				'campaign_id' => $campaign_id,
				'date_range'  => $date_range,
				'metrics'     => $this->calculate_campaign_metrics( $campaign_id, $date_conditions ),
				'events'      => $this->get_campaign_events( $campaign_id, $date_conditions ),
				'performance' => $this->get_campaign_performance( $campaign_id, $date_conditions ),
				'trends'      => $this->get_campaign_trends( $campaign_id, $date_conditions ),
			);

			// Cache for 1 hour
			$this->cache_manager->set( $cache_key, $analytics_data, 3600 );

			return $analytics_data;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign analytics',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return array();
		}
	}

	/**
	 * Get session ID for tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Session ID.
	 */
	private function get_session_id(): string {
		if ( ! session_id() ) {
			session_start();
		}
		return session_id();
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Get active campaign for product.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $product_id    Product ID.
	 * @return   int|null              Campaign ID or null.
	 */
	private function get_active_campaign_for_product( int $product_id ): ?int {
		// Try cache first for performance
		$cache_key          = 'active_campaign_for_product_' . $product_id;
		$cached_campaign_id = $this->cache_manager->get( $cache_key );

		if ( false !== $cached_campaign_id ) {
			return $cached_campaign_id ? (int) $cached_campaign_id : null;
		}

		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$now_utc         = gmdate( 'Y-m-d H:i:s' );

		// Query active campaigns that include this product
		$query = $wpdb->prepare(
			"SELECT id, product_selection_type, product_ids
            FROM {$campaigns_table}
            WHERE status = 'active'
            AND deleted_at IS NULL
            AND ( starts_at IS NULL OR starts_at <= %s )
            AND ( ends_at IS NULL OR ends_at >= %s )
            ORDER BY priority DESC, created_at ASC
            LIMIT 1",
			$now_utc,
			$now_utc
		);

		$campaigns = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $campaigns ) ) {
			// Cache null result for 5 minutes
			$this->cache_manager->set( $cache_key, 0, 300 );
			return null;
		}

		// Check each campaign to see if it includes this product
		foreach ( $campaigns as $campaign ) {
			// If campaign targets all products, return it
			if ( 'all_products' === $campaign['product_selection_type'] ) {
				$this->cache_manager->set( $cache_key, $campaign['id'], 300 );
				return (int) $campaign['id'];
			}

			// If campaign has specific products, check if this product is included
			if ( 'specific_products' === $campaign['product_selection_type'] ) {
				$product_ids = json_decode( $campaign['product_ids'], true );

				if ( is_array( $product_ids ) && in_array( $product_id, $product_ids, true ) ) {
					$this->cache_manager->set( $cache_key, $campaign['id'], 300 );
					return (int) $campaign['id'];
				}
			}
		}

		// No matching campaign found - cache for 5 minutes
		$this->cache_manager->set( $cache_key, 0, 300 );
		return null;
	}

	/**
	 * Update campaign conversion metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    object $order          Order object.
	 * @param    object $item           Order item.
	 * @return   void
	 */
	private function update_campaign_conversion_metrics( int $campaign_id, $order, $item ): void {
		global $wpdb;

		// Get current date and hour in UTC for aggregation
		$date_recorded = gmdate( 'Y-m-d' );
		$hour_recorded = (int) gmdate( 'H' );

		// Calculate revenue for this item
		$item_revenue    = (float) $item->get_total();
		$discount_amount = (float) ( $item->get_subtotal() - $item->get_total() );

		// Get customer ID
		$customer_id = $order->get_customer_id();

		// Check if this is a new customer for this campaign
		$is_new_customer = $this->is_new_customer_for_campaign( $campaign_id, $customer_id );

		// Update analytics table using INSERT ... ON DUPLICATE KEY UPDATE
		// This aggregates metrics by campaign_id, date_recorded, and hour_recorded
		$query = $wpdb->prepare(
			"INSERT INTO {$this->analytics_table}
            (campaign_id, date_recorded, hour_recorded, conversions, revenue, discount_given, unique_customers, created_at)
            VALUES (%d, %s, %d, 1, %f, %f, %d, %s)
            ON DUPLICATE KEY UPDATE
                conversions = conversions + 1,
                revenue = revenue + %f,
                discount_given = discount_given + %f,
                unique_customers = unique_customers + %d",
			$campaign_id,
			$date_recorded,
			$hour_recorded,
			$item_revenue,
			$discount_amount,
			$is_new_customer ? 1 : 0,
			gmdate( 'Y-m-d H:i:s' ),
			$item_revenue,
			$discount_amount,
			$is_new_customer ? 1 : 0
		);

		$result = $wpdb->query( $query );

		if ( false === $result ) {
			$this->logger->error(
				'Failed to update campaign conversion metrics',
				array(
					'campaign_id' => $campaign_id,
					'order_id'    => $order->get_id(),
					'item_id'     => $item->get_id(),
					'error'       => $wpdb->last_error,
				)
			);
		} else {
			$this->logger->debug(
				'Campaign conversion metrics updated',
				array(
					'campaign_id'  => $campaign_id,
					'order_id'     => $order->get_id(),
					'revenue'      => $item_revenue,
					'discount'     => $discount_amount,
					'new_customer' => $is_new_customer,
				)
			);

			// Invalidate analytics cache
			$this->invalidate_analytics_cache( $campaign_id );
		}
	}

	/**
	 * Check if customer is new for this campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $campaign_id    Campaign ID.
	 * @param    int $customer_id    Customer ID.
	 * @return   bool                   True if new customer.
	 */
	private function is_new_customer_for_campaign( int $campaign_id, int $customer_id ): bool {
		if ( 0 === $customer_id ) {
			// Guest checkout - always count as new
			return true;
		}

		global $wpdb;
		$customer_usage_table = $wpdb->prefix . 'scd_customer_usage';

		// Check if customer has used this campaign before
		$usage_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$customer_usage_table}
                WHERE campaign_id = %d AND customer_id = %d",
				$campaign_id,
				$customer_id
			)
		);

		return 0 === (int) $usage_count;
	}

	/**
	 * Invalidate analytics cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int|null $campaign_id    Campaign ID.
	 * @return   void
	 */
	private function invalidate_analytics_cache( ?int $campaign_id = null ): void {
		if ( $campaign_id ) {
			// Clear specific campaign analytics caches
			$this->cache_manager->delete( "scd_analytics_campaign_{$campaign_id}_7days" );
			$this->cache_manager->delete( "scd_analytics_campaign_{$campaign_id}_30days" );
			$this->cache_manager->delete( "scd_analytics_campaign_{$campaign_id}_90days" );
			$this->cache_manager->delete( "active_campaign_for_product_{$campaign_id}" );
		} else {
			// Clear all analytics caches - use flush for broader clearing
			// Note: flush() clears all plugin caches, which may be too broad
			// For now, just log that we would clear all analytics
			$this->logger->debug( 'Analytics cache invalidation requested for all campaigns' );
		}
	}

	/**
	 * Calculate campaign metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   array                       Calculated metrics.
	 */
	private function calculate_campaign_metrics( int $campaign_id, array $date_conditions ): array {
		// Placeholder for metrics calculation
		return array(
			'views'           => 0,
			'clicks'          => 0,
			'conversions'     => 0,
			'revenue'         => 0,
			'ctr'             => 0,
			'conversion_rate' => 0,
			'roi'             => 0,
		);
	}

	/**
	 * Get campaign events.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   array                       Campaign events.
	 */
	private function get_campaign_events( int $campaign_id, array $date_conditions ): array {
		// Placeholder for events retrieval
		return array();
	}

	/**
	 * Get campaign performance (public method for handlers).
	 *
	 * @since    1.0.0
	 * @param    array  $args        Query arguments with date_range.
	 * @param    int    $limit       Number of campaigns to return.
	 * @param    string $sort_by     Sort field.
	 * @param    string $sort_order  Sort order (asc/desc).
	 * @return   array                  Performance data.
	 */
	public function get_campaign_performance( array $args = array(), int $limit = 10, string $sort_by = 'revenue', string $sort_order = 'desc' ): array {
		global $wpdb;

		try {
			// Get date range
			$date_range      = $args['date_range'] ?? '30days';
			$date_conditions = $this->get_date_range_conditions( $date_range );
			$start_date      = $date_conditions['start_date'];
			$end_date        = $date_conditions['end_date'];

			// Get all campaigns
			$campaigns_table = $wpdb->prefix . 'scd_campaigns';
			$analytics_table = $this->analytics_table;

			// Build SQL query to get campaign performance
			$sql = $wpdb->prepare(
				"SELECT
                    c.id,
                    c.name,
                    c.status,
                    c.discount_type,
                    c.discount_value,
                    COALESCE( SUM( CASE WHEN a.event_type = 'purchase_complete' THEN a.order_total ELSE 0 END ), 0 ) as revenue,
                    COALESCE( SUM( CASE WHEN a.event_type = 'purchase_complete' THEN 1 ELSE 0 END ), 0 ) as conversions,
                    COALESCE( SUM( CASE WHEN a.event_type = 'campaign_impression' THEN 1 ELSE 0 END ), 0 ) as impressions,
                    COALESCE( SUM( CASE WHEN a.event_type = 'discount_click' THEN 1 ELSE 0 END ), 0 ) as clicks,
                    COALESCE( SUM( CASE WHEN a.event_type = 'purchase_complete' THEN a.discount_amount ELSE 0 END ), 0 ) as total_discount
                FROM {$campaigns_table} c
                LEFT JOIN {$analytics_table} a ON c.id = a.campaign_id
                    AND a.date_recorded >= %s
                    AND a.date_recorded <= %s
                GROUP BY c.id
                HAVING conversions > 0 OR impressions > 0 OR clicks > 0
                ORDER BY %s %s
                LIMIT %d",
				$start_date,
				$end_date,
				$sort_by,
				strtoupper( $sort_order ),
				$limit
			);

			// Note: WordPress prepare() doesn't handle ORDER BY and LIMIT well, so we'll build those parts manually
			$order_field = 'revenue'; // Default
			if ( in_array( $sort_by, array( 'revenue', 'conversions', 'impressions', 'clicks' ), true ) ) {
				$order_field = $sort_by;
			}

			$order_direction = 'DESC';
			if ( in_array( strtoupper( $sort_order ), array( 'ASC', 'DESC' ), true ) ) {
				$order_direction = strtoupper( $sort_order );
			}

			// Rebuild SQL without using prepare() for ORDER BY and LIMIT
			$sql = "SELECT
                    c.id,
                    c.name,
                    c.status,
                    c.discount_type,
                    c.discount_value,
                    COALESCE( SUM( CASE WHEN a.event_type = 'purchase_complete' THEN a.order_total ELSE 0 END ), 0 ) as revenue,
                    COALESCE( SUM( CASE WHEN a.event_type = 'purchase_complete' THEN 1 ELSE 0 END ), 0 ) as conversions,
                    COALESCE( SUM( CASE WHEN a.event_type = 'campaign_impression' THEN 1 ELSE 0 END ), 0 ) as impressions,
                    COALESCE( SUM( CASE WHEN a.event_type = 'discount_click' THEN 1 ELSE 0 END ), 0 ) as clicks,
                    COALESCE( SUM( CASE WHEN a.event_type = 'purchase_complete' THEN a.discount_amount ELSE 0 END ), 0 ) as total_discount
                FROM {$campaigns_table} c
                LEFT JOIN {$analytics_table} a ON c.id = a.campaign_id
                    AND a.date_recorded >= %s
                    AND a.date_recorded <= %s
                GROUP BY c.id
                HAVING conversions > 0 OR impressions > 0 OR clicks > 0
                ORDER BY {$order_field} {$order_direction}
                LIMIT %d";

			$sql = $wpdb->prepare( $sql, $start_date, $end_date, $limit );

			$results = $wpdb->get_results( $sql, ARRAY_A );

			if ( empty( $results ) ) {
				return array();
			}

			// Format results for frontend
			$formatted_campaigns = array();
			foreach ( $results as $row ) {
				// Calculate CTR
				$ctr = 0;
				if ( $row['impressions'] > 0 ) {
					$ctr = ( $row['clicks'] / $row['impressions'] ) * 100;
				}

				// Calculate ROI
				$roi = 0;
				if ( $row['total_discount'] > 0 ) {
					$roi = ( ( $row['revenue'] - $row['total_discount'] ) / $row['total_discount'] ) * 100;
				}

				// Format status label
				$status_label = ucfirst( $row['status'] );
				if ( 'active' === $row['status'] ) {
					$status_label = __( 'Active', 'smart-cycle-discounts' );
				} elseif ( 'paused' === $row['status'] ) {
					$status_label = __( 'Paused', 'smart-cycle-discounts' );
				} elseif ( 'draft' === $row['status'] ) {
					$status_label = __( 'Draft', 'smart-cycle-discounts' );
				} elseif ( 'ended' === $row['status'] ) {
					$status_label = __( 'Ended', 'smart-cycle-discounts' );
				}

				$formatted_campaigns[] = array(
					'id'           => (int) $row['id'],
					'name'         => $row['name'],
					'status'       => $row['status'],
					'status_label' => $status_label,
					'revenue'      => (float) $row['revenue'],
					'conversions'  => (int) $row['conversions'],
					'impressions'  => (int) $row['impressions'],
					'clicks'       => (int) $row['clicks'],
					'ctr'          => round( $ctr, 2 ),
					'roi'          => round( $roi, 2 ),
					'edit_url'     => admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $row['id'] ),
					'view_url'     => admin_url( 'admin.php?page=scd-analytics&campaign_id=' . $row['id'] ),
				);
			}

			return $formatted_campaigns;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign performance',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			return array();
		}
	}

	/**
	 * Get revenue trend data.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range (e.g., '30days', '7days').
	 * @param    string $granularity   Data granularity (e.g., 'daily', 'weekly').
	 * @param    int    $campaign_id   Campaign ID (0 for all campaigns).
	 * @return   array                    Revenue trend data.
	 */
	public function get_revenue_trend( string $date_range = '30days', string $granularity = 'daily', int $campaign_id = 0 ): array {
		global $wpdb;

		try {
			// Get date range conditions
			$date_conditions = $this->get_date_range_conditions( $date_range );
			$start_date      = $date_conditions['start_date'];
			$end_date        = $date_conditions['end_date'];

			// Build SQL query based on granularity
			$analytics_table = $wpdb->prefix . 'scd_analytics';

			// Base WHERE conditions
			$where_parts  = array();
			$where_values = array();

			// Date range filter
			$where_parts[]  = 'date_recorded >= %s';
			$where_values[] = date( 'Y-m-d', strtotime( $start_date ) );

			$where_parts[]  = 'date_recorded <= %s';
			$where_values[] = date( 'Y-m-d', strtotime( $end_date ) );

			// Campaign filter (if specified)
			if ( $campaign_id > 0 ) {
				$where_parts[]  = 'campaign_id = %d';
				$where_values[] = $campaign_id;
			}

			$where_clause = implode( ' AND ', $where_parts );

			// Build SELECT and GROUP BY based on granularity
			switch ( $granularity ) {
				case 'hourly':
					$select_date = "DATE_FORMAT(date_recorded, '%%Y-%%m-%%d %%H:00') as period";
					$group_by    = 'DATE(date_recorded), hour_recorded';
					$order_by    = 'date_recorded ASC, hour_recorded ASC';
					break;

				case 'weekly':
					$select_date = "DATE_FORMAT(date_recorded, '%%Y-Week %%u') as period";
					$group_by    = 'YEARWEEK(date_recorded, 1)';
					$order_by    = 'YEARWEEK(date_recorded, 1) ASC';
					break;

				case 'monthly':
					$select_date = "DATE_FORMAT(date_recorded, '%%Y-%%m') as period";
					$group_by    = 'YEAR(date_recorded), MONTH(date_recorded)';
					$order_by    = 'YEAR(date_recorded) ASC, MONTH(date_recorded) ASC';
					break;

				case 'daily':
				default:
					$select_date = 'date_recorded as period';
					$group_by    = 'date_recorded';
					$order_by    = 'date_recorded ASC';
					break;
			}

			// Build the full query
			$sql = "SELECT
                        {$select_date},
                        SUM(revenue) as total_revenue,
                        SUM(conversions) as total_conversions,
                        SUM(impressions) as total_impressions,
                        SUM(clicks) as total_clicks
                    FROM {$analytics_table}
                    WHERE {$where_clause}
                    GROUP BY {$group_by}
                    ORDER BY {$order_by}";

			// Prepare and execute query
			$prepared_sql = $wpdb->prepare( $sql, $where_values );
			$results      = $wpdb->get_results( $prepared_sql, ARRAY_A );

			// Format results for Chart.js
			$labels = array();
			$values = array();

			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$labels[] = $this->format_chart_label( $row['period'], $granularity );
					$values[] = floatval( $row['total_revenue'] );
				}
			} else {
				// Return empty data structure if no results
				$labels = $this->generate_empty_labels( $start_date, $end_date, $granularity );
				$values = array_fill( 0, count( $labels ), 0.0 );
			}

			return array(
				'labels'   => $labels,
				'values'   => $values,
				'metadata' => array(
					'date_range'    => $date_range,
					'granularity'   => $granularity,
					'campaign_id'   => $campaign_id,
					'start_date'    => $start_date,
					'end_date'      => $end_date,
					'total_revenue' => array_sum( $values ),
					'data_points'   => count( $values ),
				),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get revenue trend',
				array(
					'error'       => $e->getMessage(),
					'date_range'  => $date_range,
					'granularity' => $granularity,
					'campaign_id' => $campaign_id,
				)
			);

			return array(
				'labels' => array(),
				'values' => array(),
				'error'  => $e->getMessage(),
			);
		}
	}

	/**
	 * Get campaign trends.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   array                       Trend data.
	 */
	private function get_campaign_trends( int $campaign_id, array $date_conditions ): array {
		// Placeholder for trend data
		return array();
	}

	/**
	 * Aggregate hourly metrics.
	 *
	 * Aggregates the last hour's analytics events into hourly summary table.
	 * This improves dashboard performance by pre-calculating metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function aggregate_hourly_metrics(): void {
		global $wpdb;

		// Get the previous hour to aggregate
		$current_hour  = date( 'Y-m-d H:00:00' );
		$previous_hour = date( 'Y-m-d H:00:00', strtotime( '-1 hour' ) );
		$hour_end      = date( 'Y-m-d H:59:59', strtotime( '-1 hour' ) );

		$analytics_table = $this->analytics_table;
		$hourly_table    = $wpdb->prefix . 'scd_analytics_hourly';

		// Aggregate data for each campaign
		$sql = $wpdb->prepare(
			"INSERT INTO {$hourly_table}
            (campaign_id, date_recorded, hour_recorded, impressions, clicks, conversions,
             revenue, discount_given, cart_adds, cart_removes, unique_visitors, created_at, updated_at)
            SELECT
                campaign_id,
                DATE(%s) as date_recorded,
                HOUR(%s) as hour_recorded,
                SUM(CASE WHEN event_type = 'campaign_impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN event_type = 'discount_click' THEN 1 ELSE 0 END) as clicks,
                SUM(CASE WHEN event_type = 'purchase_complete' THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN event_type = 'purchase_complete' THEN
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.line_total')) AS DECIMAL(10,2))
                    ELSE 0 END) as revenue,
                SUM(CASE WHEN event_type = 'purchase_complete' THEN
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.discount_amount')) AS DECIMAL(10,2))
                    ELSE 0 END) as discount_given,
                SUM(CASE WHEN event_type = 'cart_add' THEN 1 ELSE 0 END) as cart_adds,
                SUM(CASE WHEN event_type = 'cart_remove' THEN 1 ELSE 0 END) as cart_removes,
                COUNT(DISTINCT user_id) as unique_visitors,
                NOW() as created_at,
                NOW() as updated_at
            FROM {$analytics_table}
            WHERE timestamp >= %s
                AND timestamp < %s
                AND campaign_id IS NOT NULL
            GROUP BY campaign_id
            ON DUPLICATE KEY UPDATE
                impressions = VALUES(impressions),
                clicks = VALUES(clicks),
                conversions = VALUES(conversions),
                revenue = VALUES(revenue),
                discount_given = VALUES(discount_given),
                cart_adds = VALUES(cart_adds),
                cart_removes = VALUES(cart_removes),
                unique_visitors = VALUES(unique_visitors),
                updated_at = NOW()",
			$previous_hour,
			$previous_hour,
			$previous_hour,
			$hour_end
		);

		$result = $wpdb->query( $sql );

		if ( $result === false ) {
			$this->logger->error(
				'Hourly aggregation query failed',
				array(
					'error' => $wpdb->last_error,
					'hour'  => $previous_hour,
				)
			);
		} else {
			$this->logger->debug(
				'Hourly aggregation completed',
				array(
					'hour'          => $previous_hour,
					'rows_affected' => $result,
				)
			);
		}
	}

	/**
	 * Aggregate daily metrics.
	 *
	 * Aggregates yesterday's hourly data into daily summary table.
	 * Also calculates advanced metrics like CTR, conversion rate, ROI.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function aggregate_daily_metrics(): void {
		global $wpdb;

		// Get yesterday's date
		$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );

		$hourly_table = $wpdb->prefix . 'scd_analytics_hourly';
		$daily_table  = $wpdb->prefix . 'scd_analytics_daily';

		// Aggregate hourly data into daily summaries
		$sql = $wpdb->prepare(
			"INSERT INTO {$daily_table}
            (campaign_id, date_recorded, impressions, clicks, conversions, revenue, discount_given,
             cart_adds, cart_removes, unique_visitors, avg_order_value, click_through_rate,
             conversion_rate, roi_percent, created_at, updated_at)
            SELECT
                campaign_id,
                date_recorded,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue,
                SUM(discount_given) as discount_given,
                SUM(cart_adds) as cart_adds,
                SUM(cart_removes) as cart_removes,
                MAX(unique_visitors) as unique_visitors,
                CASE WHEN SUM(conversions) > 0
                    THEN SUM(revenue) / SUM(conversions)
                    ELSE 0
                END as avg_order_value,
                CASE WHEN SUM(impressions) > 0
                    THEN (SUM(clicks) / SUM(impressions)) * 100
                    ELSE 0
                END as click_through_rate,
                CASE WHEN SUM(clicks) > 0
                    THEN (SUM(conversions) / SUM(clicks)) * 100
                    ELSE 0
                END as conversion_rate,
                CASE WHEN SUM(discount_given) > 0
                    THEN ((SUM(revenue) - SUM(discount_given)) / SUM(discount_given)) * 100
                    ELSE 0
                END as roi_percent,
                NOW() as created_at,
                NOW() as updated_at
            FROM {$hourly_table}
            WHERE date_recorded = %s
            GROUP BY campaign_id, date_recorded
            ON DUPLICATE KEY UPDATE
                impressions = VALUES(impressions),
                clicks = VALUES(clicks),
                conversions = VALUES(conversions),
                revenue = VALUES(revenue),
                discount_given = VALUES(discount_given),
                cart_adds = VALUES(cart_adds),
                cart_removes = VALUES(cart_removes),
                unique_visitors = VALUES(unique_visitors),
                avg_order_value = VALUES(avg_order_value),
                click_through_rate = VALUES(click_through_rate),
                conversion_rate = VALUES(conversion_rate),
                roi_percent = VALUES(roi_percent),
                updated_at = NOW()",
			$yesterday
		);

		$result = $wpdb->query( $sql );

		if ( $result === false ) {
			$this->logger->error(
				'Daily aggregation query failed',
				array(
					'error' => $wpdb->last_error,
					'date'  => $yesterday,
				)
			);
		} else {
			$this->logger->debug(
				'Daily aggregation completed',
				array(
					'date'          => $yesterday,
					'rows_affected' => $result,
				)
			);

			// After successful daily aggregation, cleanup old hourly data (keep last 7 days)
			$this->cleanup_old_hourly_data();
		}
	}

	/**
	 * Cleanup old hourly aggregation data.
	 *
	 * Removes hourly data older than 7 days since we have daily summaries.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function cleanup_old_hourly_data(): void {
		global $wpdb;

		$hourly_table = $wpdb->prefix . 'scd_analytics_hourly';
		$cutoff_date  = date( 'Y-m-d', strtotime( '-7 days' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$hourly_table} WHERE date_recorded < %s",
				$cutoff_date
			)
		);

		if ( $deleted ) {
			$this->logger->debug(
				'Cleaned up old hourly data',
				array(
					'rows_deleted' => $deleted,
					'cutoff_date'  => $cutoff_date,
				)
			);
		}
	}

	/**
	 * Cleanup old analytics data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function cleanup_old_data(): void {
		// Remove analytics data older than 90 days
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->analytics_table} WHERE date_created < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Run hourly aggregation.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function run_hourly_aggregation(): void {
		try {
			$this->aggregate_hourly_metrics();
			$this->logger->info( 'Hourly analytics aggregation completed' );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Hourly analytics aggregation failed',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Run daily aggregation.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function run_daily_aggregation(): void {
		try {
			$this->aggregate_daily_metrics();
			$this->cleanup_old_data();
			$this->logger->info( 'Daily analytics aggregation completed' );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Daily analytics aggregation failed',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Format chart label based on granularity.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $period        Period string from database.
	 * @param    string $granularity   Data granularity.
	 * @return   string                   Formatted label.
	 */
	private function format_chart_label( string $period, string $granularity ): string {
		switch ( $granularity ) {
			case 'hourly':
				// Format: "2024-01-15 14:00" -> "Jan 15, 2pm"
				$timestamp = strtotime( $period );
				return date( 'M j, ga', $timestamp );

			case 'weekly':
				// Format: "2024-Week 03" -> "Week 3, 2024"
				if ( preg_match( '/(\d{4})-Week (\d+)/', $period, $matches ) ) {
					return 'Week ' . intval( $matches[2] ) . ', ' . $matches[1];
				}
				return $period;

			case 'monthly':
				// Format: "2024-01" -> "January 2024"
				$timestamp = strtotime( $period . '-01' );
				return date( 'F Y', $timestamp );

			case 'daily':
			default:
				// Format: "2024-01-15" -> "Jan 15"
				$timestamp = strtotime( $period );
				return date( 'M j', $timestamp );
		}
	}

	/**
	 * Generate empty labels for date range when no data exists.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $start_date    Start date.
	 * @param    string $end_date      End date.
	 * @param    string $granularity   Data granularity.
	 * @return   array                    Array of date labels.
	 */
	private function generate_empty_labels( string $start_date, string $end_date, string $granularity ): array {
		$labels  = array();
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );

		switch ( $granularity ) {
			case 'hourly':
				while ( $current <= $end ) {
					$labels[] = date( 'M j, ga', $current );
					$current  = strtotime( '+1 hour', $current );
				}
				break;

			case 'weekly':
				while ( $current <= $end ) {
					$week     = date( 'W', $current );
					$year     = date( 'Y', $current );
					$labels[] = 'Week ' . intval( $week ) . ', ' . $year;
					$current  = strtotime( '+1 week', $current );
				}
				break;

			case 'monthly':
				while ( $current <= $end ) {
					$labels[] = date( 'F Y', $current );
					$current  = strtotime( '+1 month', $current );
				}
				break;

			case 'daily':
			default:
				while ( $current <= $end ) {
					$labels[] = date( 'M j', $current );
					$current  = strtotime( '+1 day', $current );
				}
				break;
		}

		return $labels;
	}

	/**
	 * Get top products by discount revenue.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range (e.g., '30days', '7days').
	 * @param    int    $limit         Number of products to return.
	 * @return   array                    Top products data.
	 */
	public function get_top_products_by_revenue( string $date_range = '30days', int $limit = 10 ): array {
		global $wpdb;

		try {
			// Get date range conditions
			$date_conditions = $this->get_date_range_conditions( $date_range );
			$start_date      = $date_conditions['start_date'];
			$end_date        = $date_conditions['end_date'];

			// Query to get top products from WooCommerce orders with discount campaigns
			$orders_table         = $wpdb->prefix . 'wc_orders';
			$order_items_table    = $wpdb->prefix . 'woocommerce_order_items';
			$order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
			$posts_table          = $wpdb->prefix . 'posts';

			$query = $wpdb->prepare(
				"SELECT
                    p.ID as product_id,
                    p.post_title as product_name,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    SUM(oim_line_total.meta_value) as total_revenue,
                    SUM(oim_line_total.meta_value - oim_line_subtotal.meta_value) as total_discount,
                    AVG((oim_line_subtotal.meta_value - oim_line_total.meta_value) / NULLIF(oim_line_subtotal.meta_value, 0) * 100) as avg_discount_percent
                FROM {$order_items_table} oi
                INNER JOIN {$orders_table} o ON oi.order_id = o.id
                INNER JOIN {$order_itemmeta_table} oim_product ON oi.order_item_id = oim_product.order_item_id
                    AND oim_product.meta_key = '_product_id'
                INNER JOIN {$posts_table} p ON oim_product.meta_value = p.ID
                INNER JOIN {$order_itemmeta_table} oim_line_total ON oi.order_item_id = oim_line_total.order_item_id
                    AND oim_line_total.meta_key = '_line_total'
                INNER JOIN {$order_itemmeta_table} oim_line_subtotal ON oi.order_item_id = oim_line_subtotal.order_item_id
                    AND oim_line_subtotal.meta_key = '_line_subtotal'
                WHERE oi.order_item_type = 'line_item'
                    AND o.date_created_gmt >= %s
                    AND o.date_created_gmt <= %s
                    AND o.status IN ('wc-completed', 'wc-processing')
                    AND oim_line_total.meta_value < oim_line_subtotal.meta_value
                GROUP BY p.ID, p.post_title
                HAVING total_discount > 0
                ORDER BY total_revenue DESC
                LIMIT %d",
				$start_date,
				$end_date,
				$limit
			);

			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( empty( $results ) ) {
				return array(
					'products' => array(),
					'period'   => $date_range,
				);
			}

			// Format results
			$products = array();
			foreach ( $results as $row ) {
				$products[] = array(
					'product_id'           => (int) $row['product_id'],
					'name'                 => $row['product_name'],
					'revenue'              => floatval( $row['total_revenue'] ),
					'order_count'          => (int) $row['order_count'],
					'total_discount'       => floatval( $row['total_discount'] ),
					'avg_discount_percent' => round( floatval( $row['avg_discount_percent'] ), 1 ),
					'revenue_per_order'    => round( floatval( $row['total_revenue'] ) / (int) $row['order_count'], 2 ),
				);
			}

			return array(
				'products'     => $products,
				'period'       => $date_range,
				'generated_at' => current_time( 'timestamp' ),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get top products by revenue',
				array(
					'error'      => $e->getMessage(),
					'date_range' => $date_range,
				)
			);

			return array(
				'products' => array(),
				'period'   => $date_range,
				'error'    => $e->getMessage(),
			);
		}
	}
}
