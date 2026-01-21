<?php
/**
 * Analytics Collector Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-analytics-collector.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once WSSCD_PLUGIN_DIR . 'includes/admin/ajax/class-wsscd-ajax-response.php';

require_once WSSCD_PLUGIN_DIR . 'includes/core/analytics/trait-analytics-helpers.php';

/**
 * Analytics Collector
 *
 * Collects and processes analytics data for campaigns and discounts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Analytics_Collector {

	use WSSCD_Analytics_Helpers;

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Database_Manager    $database_manager    Database manager.
	 */
	private $database_manager;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache_manager    Cache manager.
	 */
	private $cache_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private $logger;

	/**
	 * E-commerce integration instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Ecommerce_Integration    $ecommerce_integration    E-commerce integration.
	 */
	private $ecommerce_integration;

	/**
	 * Analytics table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $analytics_table    Analytics table name.
	 */
	private $analytics_table;


	/**
	 * Initialize the analytics collector.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Database_Manager      $database_manager       Database manager.
	 * @param    WSSCD_Cache_Manager         $cache_manager          Cache manager.
	 * @param    WSSCD_Logger                $logger                 Logger instance.
	 * @param    WSSCD_Ecommerce_Integration $ecommerce_integration  E-commerce integration.
	 */
	public function __construct(
		WSSCD_Database_Manager $database_manager,
		WSSCD_Cache_Manager $cache_manager,
		WSSCD_Logger $logger,
		WSSCD_Ecommerce_Integration $ecommerce_integration
	) {
		$this->database_manager      = $database_manager;
		$this->cache_manager         = $cache_manager;
		$this->logger                = $logger;
		$this->ecommerce_integration = $ecommerce_integration;

		global $wpdb;
		$this->analytics_table = $wpdb->prefix . 'wsscd_analytics';
	}

	/**
	 * Initialize analytics collector.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Load AJAX security handler
		require_once WSSCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';

		$this->add_hooks();
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		// Only register hooks if e-commerce platform is active
		if ( ! $this->ecommerce_integration->is_active() ) {
			// Log warning once per day (not on every request)
			if ( ! get_transient( 'wsscd_woocommerce_warning_logged' ) ) {
				$this->logger->warning(
					'E-commerce platform is not active - analytics tracking disabled',
					array(
						'platform' => $this->ecommerce_integration->get_platform_name(),
					)
				);
				set_transient( 'wsscd_woocommerce_warning_logged', true, DAY_IN_SECONDS );
			}
			return;
		}

		// Register order completion tracking using platform-agnostic integration
		$this->ecommerce_integration->register_order_complete_hook( array( $this, 'track_purchase_complete' ) );

		// Campaign tracking hooks
		add_action( 'wsscd_campaign_activated', array( $this, 'track_campaign_activation' ), 10, 1 );
		add_action( 'wsscd_campaign_deactivated', array( $this, 'track_campaign_deactivation' ), 10, 1 );

		// AJAX tracking handled by specialized handlers (track-impression, track-click)

		$this->logger->debug(
			'Analytics hooks registered',
			array(
				'platform' => $this->ecommerce_integration->get_platform_name(),
			)
		);
	}


	/**
	 * Track campaign impression.
	 *
	 * Directly updates aggregated impressions count (no event-level storage).
	 * Optimized for high-volume frontend tracking.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $context        Additional context (product_id, source, etc.).
	 * @return   bool                     Success status.
	 */
	public function track_impression( int $campaign_id, array $context = array() ): bool {
		global $wpdb;

		try {
			$date_recorded = gmdate( 'Y-m-d' );
			$hour_recorded = (int) gmdate( 'H' );
			$product_id    = isset( $context['product_id'] ) ? absint( $context['product_id'] ) : 0;

			// Track campaign-level impression.
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics INSERT/UPDATE on plugin's custom table.
			$query = $wpdb->prepare(
				'INSERT INTO %i
                (campaign_id, date_recorded, hour_recorded, impressions, created_at)
                VALUES (%d, %s, %d, 1, %s)
                ON DUPLICATE KEY UPDATE
                    impressions = impressions + 1',
				$this->analytics_table,
				$campaign_id,
				$date_recorded,
				$hour_recorded,
				gmdate( 'Y-m-d H:i:s' )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
			$result = $wpdb->query( $query );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

			// Track product-level impression if product_id provided
			if ( $product_id > 0 ) {
				$this->track_product_impression( $campaign_id, $product_id, $date_recorded, $hour_recorded );
			}

			if ( false !== $result ) {
				$this->logger->debug(
					'Campaign impression tracked',
					array(
						'campaign_id' => $campaign_id,
						'product_id'  => $product_id,
						'platform'    => $this->ecommerce_integration->get_platform_name(),
					)
				);

				$this->invalidate_analytics_cache( $campaign_id );

				return true;
			}

			return false;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to track impression',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Track discount click.
	 *
	 * Directly updates aggregated clicks count (no event-level storage).
	 * Optimized for high-volume frontend tracking.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    int    $product_id     Product ID (optional).
	 * @param    string $click_source   Click source (optional).
	 * @return   bool                     Success status.
	 */
	public function track_click( int $campaign_id, int $product_id = 0, string $click_source = '' ): bool {
		global $wpdb;

		try {
			$date_recorded = gmdate( 'Y-m-d' );
			$hour_recorded = (int) gmdate( 'H' );

			// Track campaign-level click
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics INSERT/UPDATE on plugin's custom table.
			$query = $wpdb->prepare(
				'INSERT INTO %i
                (campaign_id, date_recorded, hour_recorded, clicks, created_at)
                VALUES (%d, %s, %d, 1, %s)
                ON DUPLICATE KEY UPDATE
                    clicks = clicks + 1',
				$this->analytics_table,
				$campaign_id,
				$date_recorded,
				$hour_recorded,
				gmdate( 'Y-m-d H:i:s' )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
			$result = $wpdb->query( $query );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

			// Track product-level click if product_id provided
			if ( $product_id > 0 ) {
				$this->track_product_click( $campaign_id, $product_id, $date_recorded, $hour_recorded );
			}

			if ( false !== $result ) {
				$this->logger->debug(
					'Campaign click tracked',
					array(
						'campaign_id'  => $campaign_id,
						'product_id'   => $product_id,
						'click_source' => $click_source,
						'platform'     => $this->ecommerce_integration->get_platform_name(),
					)
				);

				$this->invalidate_analytics_cache( $campaign_id );

				return true;
			}

			return false;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to track click',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Track purchase completion.
	 *
	 * Uses e-commerce integration to work with any platform (WooCommerce, EDD, etc.).
	 *
	 * @since    1.0.0
	 * @param    int $order_id    Order ID.
	 * @return   void
	 */
	public function track_purchase_complete( int $order_id ): void {
		// Get order using platform-agnostic integration
		$order = $this->ecommerce_integration->get_order( $order_id );

		if ( ! $order ) {
			$this->logger->warning(
				'Order not found for analytics tracking',
				array(
					'order_id' => $order_id,
					'platform' => $this->ecommerce_integration->get_platform_name(),
				)
			);
			return;
		}

		// Get order items using integration
		$items = $this->ecommerce_integration->get_order_items( $order );

		if ( empty( $items ) ) {
			$this->logger->debug(
				'No items found in order',
				array( 'order_id' => $order_id )
			);
			return;
		}

		// Track conversion for each item's campaign
		foreach ( $items as $item ) {
			$product_id  = $this->ecommerce_integration->get_item_product_id( $item );
			$campaign_id = $this->get_active_campaign_for_product( $product_id );

			if ( $campaign_id ) {
				$this->update_campaign_conversion_metrics( $campaign_id, $order, $item );

				$this->logger->debug(
					'Purchase tracked for campaign',
					array(
						'campaign_id' => $campaign_id,
						'order_id'    => $order_id,
						'product_id'  => $product_id,
					)
				);
			}
		}
	}

	/**
	 * Track campaign activation.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
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
	 * @param    WSSCD_Campaign $campaign    Campaign object.
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
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$now_utc         = gmdate( 'Y-m-d H:i:s' );

		// Query active campaigns that include this product
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Campaign lookup for analytics.
		$query = $wpdb->prepare(
			'SELECT id, product_selection_type, product_ids
            FROM %i
            WHERE status = %s
            AND deleted_at IS NULL
            AND ( starts_at IS NULL OR starts_at <= %s )
            AND ( ends_at IS NULL OR ends_at >= %s )
            ORDER BY priority DESC, created_at ASC
            LIMIT 1',
			$campaigns_table,
			'active',
			$now_utc,
			$now_utc
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$campaigns = $wpdb->get_results( $query, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		if ( empty( $campaigns ) ) {
			$this->cache_manager->set( $cache_key, 0, 300 );
			return null;
		}

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
	 * Uses e-commerce integration for platform-agnostic order/item access.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    mixed  $order          Order object (platform-specific).
	 * @param    mixed  $item           Order item (platform-specific).
	 * @return   void
	 */
	private function update_campaign_conversion_metrics( int $campaign_id, $order, $item ): void {
		global $wpdb;

		$date_recorded = gmdate( 'Y-m-d' );
		$hour_recorded = (int) gmdate( 'H' );

		// Use integration methods to get item data
		$product_id      = $this->ecommerce_integration->get_item_product_id( $item );
		$quantity        = $this->ecommerce_integration->get_item_quantity( $item );
		$item_revenue    = $this->ecommerce_integration->get_item_total( $item );
		$discount_amount = $this->ecommerce_integration->get_item_discount( $item );

		// Get product cost and calculate profit
		$product_cost = $this->get_product_cost( $product_id, $quantity );
		$profit       = $item_revenue - $product_cost;

		// Get customer ID using integration
		$customer_id = $this->ecommerce_integration->get_order_customer_id( $order );

		$is_new_customer = $this->is_new_customer_for_campaign( $campaign_id, $customer_id );

		// Track campaign-level aggregated metrics
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics INSERT/UPDATE on plugin's custom table.
		$query = $wpdb->prepare(
			'INSERT INTO %i
            (campaign_id, date_recorded, hour_recorded, conversions, revenue, discount_given, product_cost, profit_margin, unique_customers, created_at)
            VALUES (%d, %s, %d, 1, %f, %f, %f, %f, %d, %s)
            ON DUPLICATE KEY UPDATE
                conversions = conversions + 1,
                revenue = revenue + %f,
                discount_given = discount_given + %f,
                product_cost = product_cost + %f,
                profit_margin = profit_margin + %f,
                unique_customers = unique_customers + %d',
			$this->analytics_table,
			$campaign_id,
			$date_recorded,
			$hour_recorded,
			$item_revenue,
			$discount_amount,
			$product_cost,
			$profit,
			$is_new_customer ? 1 : 0,
			gmdate( 'Y-m-d H:i:s' ),
			$item_revenue,
			$discount_amount,
			$product_cost,
			$profit,
			$is_new_customer ? 1 : 0
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$result = $wpdb->query( $query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		// Track product-level metrics
		$this->track_product_conversion(
			$campaign_id,
			$product_id,
			$date_recorded,
			$hour_recorded,
			$item_revenue,
			$discount_amount,
			$product_cost,
			$profit,
			$quantity,
			$is_new_customer
		);

		if ( false === $result ) {
			$this->logger->error(
				'Failed to update campaign conversion metrics',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $wpdb->last_error,
					'platform'    => $this->ecommerce_integration->get_platform_name(),
				)
			);
		} else {
			$this->logger->debug(
				'Campaign conversion metrics updated',
				array(
					'campaign_id'  => $campaign_id,
					'product_id'   => $product_id,
					'revenue'      => $item_revenue,
					'discount'     => $discount_amount,
					'profit'       => $profit,
					'new_customer' => $is_new_customer,
					'platform'     => $this->ecommerce_integration->get_platform_name(),
				)
			);

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
		$customer_usage_table = $wpdb->prefix . 'wsscd_customer_usage';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Customer usage lookup; must be real-time.
		$usage_count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i
                WHERE campaign_id = %d AND customer_id = %d',
				$customer_usage_table,
				$campaign_id,
				$customer_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

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
			$this->cache_manager->delete( "wsscd_analytics_campaign_{$campaign_id}_7days" );
			$this->cache_manager->delete( "wsscd_analytics_campaign_{$campaign_id}_30days" );
			$this->cache_manager->delete( "wsscd_analytics_campaign_{$campaign_id}_90days" );
			$this->cache_manager->delete( "active_campaign_for_product_{$campaign_id}" );
		}

		// Clear analytics cache using centralized invalidation
		$this->cache_manager->invalidate_analytics();
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
			$date_conditions = $this->get_date_range_conditions( $date_range );
			$start_date      = $date_conditions['start_date'];
			$end_date        = $date_conditions['end_date'];

			// Build SQL query based on granularity
			$analytics_table = $wpdb->prefix . 'wsscd_analytics';

			// Base WHERE conditions
			$where_parts  = array();
			$where_values = array();

			// Date range filter
			$where_parts[]  = 'date_recorded >= %s';
			$where_values[] = gmdate( 'Y-m-d', strtotime( $start_date ) );

			$where_parts[]  = 'date_recorded <= %s';
			$where_values[] = gmdate( 'Y-m-d', strtotime( $end_date ) );

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

			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SELECT fields ($select_date), GROUP BY ($group_by), and ORDER BY ($order_by) are whitelisted expressions built from switch statements above. All user values go through prepare placeholders.
			$sql = "SELECT
                        {$select_date},
                        SUM(revenue) as total_revenue,
                        SUM(conversions) as total_conversions,
                        SUM(impressions) as total_impressions,
                        SUM(clicks) as total_clicks
                    FROM %i
                    WHERE {$where_clause}
                    GROUP BY {$group_by}
                    ORDER BY {$order_by}";

			$prepared_sql = $wpdb->prepare( $sql, array_merge( array( $analytics_table ), $where_values ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query prepared above with wpdb->prepare(); analytics chart data.
			$results      = $wpdb->get_results( $prepared_sql, ARRAY_A );

			$labels = array();
			$values = array();

			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$labels[] = $this->format_chart_label( $row['period'], $granularity );
					$values[] = floatval( $row['total_revenue'] );
				}
			} else {
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
				return wp_date( 'M j, ga', $timestamp );

			case 'weekly':
				// Format: "2024-Week 03" -> "Week 3, 2024"
				if ( preg_match( '/(\d{4})-Week (\d+)/', $period, $matches ) ) {
					return 'Week ' . intval( $matches[2] ) . ', ' . $matches[1];
				}
				return $period;

			case 'monthly':
				// Format: "2024-01" -> "January 2024"
				$timestamp = strtotime( $period . '-01' );
				return wp_date( 'F Y', $timestamp );

			case 'daily':
			default:
				// Format: "2024-01-15" -> "Jan 15"
				$timestamp = strtotime( $period );
				return wp_date( 'M j', $timestamp );
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
					$labels[] = wp_date( 'M j, ga', $current );
					$current  = strtotime( '+1 hour', $current );
				}
				break;

			case 'weekly':
				while ( $current <= $end ) {
					$week     = gmdate( 'W', $current );
					$year     = gmdate( 'Y', $current );
					$labels[] = 'Week ' . intval( $week ) . ', ' . $year;
					$current  = strtotime( '+1 week', $current );
				}
				break;

			case 'monthly':
				while ( $current <= $end ) {
					$labels[] = wp_date( 'F Y', $current );
					$current  = strtotime( '+1 month', $current );
				}
				break;

			case 'daily':
			default:
				while ( $current <= $end ) {
					$labels[] = wp_date( 'M j', $current );
					$current  = strtotime( '+1 day', $current );
				}
				break;
		}

		return $labels;
	}

	/**
	 * Track product-level impression.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id     Campaign ID.
	 * @param    int    $product_id      Product ID.
	 * @param    string $date_recorded   Date recorded.
	 * @param    int    $hour_recorded   Hour recorded.
	 * @return   void
	 */
	private function track_product_impression( int $campaign_id, int $product_id, string $date_recorded, int $hour_recorded ): void {
		global $wpdb;
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics INSERT/UPDATE; caching not appropriate for real-time tracking.
		$query = $wpdb->prepare(
			'INSERT INTO %i
            (campaign_id, product_id, date_recorded, hour_recorded, impressions, created_at)
            VALUES (%d, %d, %s, %d, 1, %s)
            ON DUPLICATE KEY UPDATE
                impressions = impressions + 1',
			$product_analytics_table,
			$campaign_id,
			$product_id,
			$date_recorded,
			$hour_recorded,
			gmdate( 'Y-m-d H:i:s' )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$wpdb->query( $query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls
	}

	/**
	 * Track product-level click.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id     Campaign ID.
	 * @param    int    $product_id      Product ID.
	 * @param    string $date_recorded   Date recorded.
	 * @param    int    $hour_recorded   Hour recorded.
	 * @return   void
	 */
	private function track_product_click( int $campaign_id, int $product_id, string $date_recorded, int $hour_recorded ): void {
		global $wpdb;
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics INSERT/UPDATE; caching not appropriate for real-time tracking.
		$query = $wpdb->prepare(
			'INSERT INTO %i
            (campaign_id, product_id, date_recorded, hour_recorded, clicks, created_at)
            VALUES (%d, %d, %s, %d, 1, %s)
            ON DUPLICATE KEY UPDATE
                clicks = clicks + 1',
			$product_analytics_table,
			$campaign_id,
			$product_id,
			$date_recorded,
			$hour_recorded,
			gmdate( 'Y-m-d H:i:s' )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$wpdb->query( $query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls
	}

	/**
	 * Track product-level conversion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id      Campaign ID.
	 * @param    int    $product_id       Product ID.
	 * @param    string $date_recorded    Date recorded.
	 * @param    int    $hour_recorded    Hour recorded.
	 * @param    float  $revenue          Revenue.
	 * @param    float  $discount         Discount given.
	 * @param    float  $cost             Product cost.
	 * @param    float  $profit           Profit.
	 * @param    int    $quantity         Quantity sold.
	 * @param    bool   $is_new_customer  Is new customer.
	 * @return   void
	 */
	private function track_product_conversion(
		int $campaign_id,
		int $product_id,
		string $date_recorded,
		int $hour_recorded,
		float $revenue,
		float $discount,
		float $cost,
		float $profit,
		int $quantity,
		bool $is_new_customer
	): void {
		global $wpdb;
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics INSERT/UPDATE; caching not appropriate for real-time tracking.
		$query = $wpdb->prepare(
			'INSERT INTO %i
            (campaign_id, product_id, date_recorded, hour_recorded, conversions, revenue, discount_given, product_cost, profit, quantity_sold, unique_customers, created_at)
            VALUES (%d, %d, %s, %d, 1, %f, %f, %f, %f, %d, %d, %s)
            ON DUPLICATE KEY UPDATE
                conversions = conversions + 1,
                revenue = revenue + %f,
                discount_given = discount_given + %f,
                product_cost = product_cost + %f,
                profit = profit + %f,
                quantity_sold = quantity_sold + %d,
                unique_customers = unique_customers + %d',
			$product_analytics_table,
			$campaign_id,
			$product_id,
			$date_recorded,
			$hour_recorded,
			$revenue,
			$discount,
			$cost,
			$profit,
			$quantity,
			$is_new_customer ? 1 : 0,
			gmdate( 'Y-m-d H:i:s' ),
			$revenue,
			$discount,
			$cost,
			$profit,
			$quantity,
			$is_new_customer ? 1 : 0
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$wpdb->query( $query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls
	}

	/**
	 * Get product cost from WooCommerce.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $product_id    Product ID.
	 * @param    int $quantity      Quantity.
	 * @return   float               Total product cost.
	 */
	private function get_product_cost( int $product_id, int $quantity ): float {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0.0;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return 0.0;
		}

		// Get cost from WooCommerce Cost of Goods plugin or custom _cost meta
		$cost_per_unit = (float) $product->get_meta( '_cost', true );

		if ( ! $cost_per_unit || $cost_per_unit <= 0 ) {
			// Try wc_cog_cost (Cost of Goods for WooCommerce plugin)
			$cost_per_unit = (float) $product->get_meta( '_wc_cog_cost', true );
		}

		return $cost_per_unit * $quantity;
	}

	/**
	 * Get top products by revenue.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range (e.g., '30days', '7days', 'all').
	 * @param    int    $limit         Number of products to return.
	 * @return   array                  Array with 'products' and 'period' keys.
	 */
	public function get_top_products_by_revenue( string $date_range = '30days', int $limit = 10 ): array {
		// Create analytics repository instance
		$repository = new WSSCD_Analytics_Repository( $this->database_manager );

		// Calculate date range
		$dates = $this->parse_date_range( $date_range );

		// Get product performance data from repository
		$products = $repository->get_product_performance( 0, $dates['start'], $dates['end'], $limit );

		return array(
			'products'     => $products,
			'period'       => $dates,
			'generated_at' => current_time( 'timestamp' ),
		);
	}

	/**
	 * Get campaign performance data.
	 *
	 * @since    1.0.0
	 * @param    array  $args      Query arguments (date_range, etc.).
	 * @param    int    $limit     Number of campaigns to return.
	 * @param    string $metric    Sort metric (revenue, clicks, conversions).
	 * @param    string $order     Sort order (asc, desc).
	 * @return   array              Campaign performance data.
	 */
	public function get_campaign_performance( array $args = array(), int $limit = 10, string $metric = 'revenue', string $order = 'desc' ): array {
		global $wpdb;

		// Parse date range
		$date_range = isset( $args['date_range'] ) ? $args['date_range'] : '30days';
		$dates      = $this->parse_date_range( $date_range );

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// Get campaign IDs
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Campaign performance lookup.
		$campaign_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i
				WHERE status = %s OR status = %s
				ORDER BY created_at DESC',
				$campaigns_table,
				'active',
				'completed'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		if ( empty( $campaign_ids ) ) {
			return array();
		}

		// Create analytics repository instance
		$repository = new WSSCD_Analytics_Repository( $this->database_manager );
		$results    = array();

		foreach ( $campaign_ids as $campaign_id ) {
			$data = $repository->get_campaign_performance( $campaign_id, $dates['start'], $dates['end'] );

			if ( ! empty( $data ) ) {
				// Get campaign details
				// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Campaign lookup.
				$campaign = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT name, status FROM %i WHERE id = %d',
						$campaigns_table,
						$campaign_id
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

				// Calculate ROI (Return on Investment)
				$revenue = isset( $data['revenue'] ) ? floatval( $data['revenue'] ) : 0;
				$cost    = isset( $data['discount_given'] ) ? floatval( $data['discount_given'] ) : 0;
				$roi     = $cost > 0 ? ( ( $revenue - $cost ) / $cost ) * 100 : 0;

				// Build campaign URLs
				$edit_url = add_query_arg(
					array(
						'page'   => 'wsscd-campaigns',
						'action' => 'wizard',
						'id'     => $campaign_id,
					),
					admin_url( 'admin.php' )
				);

				$view_url = add_query_arg(
					array(
						'page'        => 'wsscd-analytics',
						'campaign_id' => $campaign_id,
					),
					admin_url( 'admin.php' )
				);

				$results[] = array_merge(
					$data,
					array(
						'campaign_id'  => $campaign_id,
						'name'         => $campaign ? $campaign->name : 'Unknown',
						'status'       => $campaign ? $campaign->status : 'unknown',
						'status_label' => $campaign ? ucfirst( $campaign->status ) : 'Unknown',
						'roi'          => round( $roi, 2 ),
						'edit_url'     => $edit_url,
						'view_url'     => $view_url,
					)
				);
			}
		}

		// Sort by metric
		usort(
			$results,
			function ( $a, $b ) use ( $metric, $order ) {
				$val_a = isset( $a[ $metric ] ) ? floatval( $a[ $metric ] ) : 0;
				$val_b = isset( $b[ $metric ] ) ? floatval( $b[ $metric ] ) : 0;

				if ( 'desc' === $order ) {
					return $val_b <=> $val_a;
				}
				return $val_a <=> $val_b;
			}
		);

		// Limit results
		return array_slice( $results, 0, $limit );
	}

	/**
	 * Parse date range string into start and end dates.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range string (e.g., '30days', '7days', 'all').
	 * @return   array                  Array with 'start' and 'end' keys.
	 */
	private function parse_date_range( string $date_range ): array {
		$end_date = gmdate( 'Y-m-d' );

		switch ( $date_range ) {
			case '7days':
				$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				break;
			case '30days':
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				break;
			case '90days':
				$start_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
				break;
			case 'all':
				$start_date = gmdate( 'Y-m-d', strtotime( '-1 year' ) );
				break;
			default:
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}

		return array(
			'start' => $start_date,
			'end'   => $end_date,
		);
	}

	/**
	 * Get daily metrics for a date range.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range (e.g., '30days', '7days').
	 * @return   array                    Daily metrics data.
	 */
	public function get_daily_metrics( string $date_range = '30days' ): array {
		global $wpdb;

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Daily metrics query; no caching needed.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT
						date_recorded,
						COALESCE(SUM(impressions), 0) as impressions,
						COALESCE(SUM(clicks), 0) as clicks,
						COALESCE(SUM(conversions), 0) as conversions,
						COALESCE(SUM(revenue), 0) as revenue,
						COALESCE(SUM(discount_given), 0) as discount_given
					FROM %i
					WHERE date_recorded BETWEEN %s AND %s
					GROUP BY date_recorded
					ORDER BY date_recorded ASC',
					$this->analytics_table,
					gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
					gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

			if ( ! $results ) {
				return array();
			}

			// Transform results into more usable format
			$daily_data = array();
			foreach ( $results as $row ) {
				$impressions = (int) $row['impressions'];
				$clicks      = (int) $row['clicks'];
				$conversions = (int) $row['conversions'];
				$revenue     = (float) $row['revenue'];

				$daily_data[] = array(
					'date'            => $row['date_recorded'],
					'impressions'     => $impressions,
					'clicks'          => $clicks,
					'conversions'     => $conversions,
					'revenue'         => $revenue,
					'discount_given'  => (float) $row['discount_given'],
					'ctr'             => $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0,
					'conversion_rate' => $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0,
					'aov'             => $conversions > 0 ? round( $revenue / $conversions, 2 ) : 0,
				);
			}

			return $daily_data;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get daily metrics',
				array(
					'error'      => $e->getMessage(),
					'date_range' => $date_range,
				)
			);

			return array();
		}
	}

}
