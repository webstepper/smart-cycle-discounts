<?php
/**
 * Metrics Calculator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-metrics-calculator.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once WSSCD_PLUGIN_DIR . 'includes/core/analytics/trait-analytics-helpers.php';

/**
 * Metrics Calculator
 *
 * Calculates performance metrics and KPIs for campaigns and discounts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Metrics_Calculator {

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
	 * Analytics table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $analytics_table    Analytics table name.
	 */
	private $analytics_table;

	/**
	 * Initialize the metrics calculator.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Database_Manager $database_manager    Database manager.
	 * @param    WSSCD_Cache_Manager    $cache_manager       Cache manager.
	 * @param    WSSCD_Logger           $logger              Logger instance.
	 */
	public function __construct(
		WSSCD_Database_Manager $database_manager,
		WSSCD_Cache_Manager $cache_manager,
		WSSCD_Logger $logger
	) {
		$this->database_manager = $database_manager;
		$this->cache_manager    = $cache_manager;
		$this->logger           = $logger;

		global $wpdb;
		$this->analytics_table = $wpdb->prefix . 'wsscd_analytics';

		// Register cache invalidation hooks
		$this->register_cache_invalidation_hooks();
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * Ensures analytics cache is cleared when underlying data changes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_cache_invalidation_hooks(): void {
		// Clear cache when campaigns change
		add_action( 'wsscd_campaign_created', array( $this, 'clear_cache' ) );
		add_action( 'wsscd_campaign_updated', array( $this, 'clear_cache' ) );
		add_action( 'wsscd_campaign_deleted', array( $this, 'clear_cache' ) );
		add_action( 'wsscd_campaign_status_changed', array( $this, 'clear_cache' ) );

		// Clear cache when WooCommerce orders complete or are refunded
		add_action( 'woocommerce_order_status_completed', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'clear_cache' ) );
	}

	/**
	 * Calculate campaign metrics.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id       Campaign ID.
	 * @param    string $date_range        Date range.
	 * @param    bool   $use_cache         Whether to use cache.
	 * @return   array                        Calculated metrics.
	 */
	public function calculate_campaign_metrics(
		int $campaign_id,
		string $date_range = '7days',
		bool $use_cache = true
	): array {
		$cache_key = "analytics_metrics_campaign_{$campaign_id}_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) && ! empty( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			$metrics = array(
				'campaign_id'     => $campaign_id,
				'date_range'      => $date_range,
				'period'          => $date_conditions,

				// Core metrics
				'impressions'     => $this->calculate_impressions( $campaign_id, $date_conditions ),
				'views'           => $this->calculate_views( $campaign_id, $date_conditions ),
				'clicks'          => $this->calculate_clicks( $campaign_id, $date_conditions ),
				'conversions'     => $this->calculate_conversions( $campaign_id, $date_conditions ),
				'revenue'         => $this->calculate_revenue( $campaign_id, $date_conditions ),
				'cart_additions'  => 0, // Cart additions (tracked separately)

				// Calculated KPIs
				'ctr'                   => 0, // Click-through rate
				'conversion_rate'       => 0, // Conversion rate
				'avg_order_value'       => 0, // Average order value
				'cart_abandonment_rate' => 0, // Cart abandonment rate
				'roi'                   => 0, // Return on investment
				'roas'                  => 0, // Return on ad spend
			);

			$metrics = $this->calculate_derived_metrics( $metrics );

			if ( $use_cache ) {
				$this->cache_manager->set( $cache_key, $metrics, 1800 );
			}

			return $metrics;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate campaign metrics',
				array(
					'campaign_id' => $campaign_id,
					'date_range'  => $date_range,
					'error'       => $e->getMessage(),
				)
			);

			return $this->get_empty_metrics( $campaign_id, $date_range );
		}
	}

	/**
	 * Calculate overall analytics metrics.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range.
	 * @param    bool   $use_cache     Whether to use cache.
	 * @return   array                    Overall metrics.
	 */
	public function calculate_overall_metrics( string $date_range = '7days', bool $use_cache = true ): array {
		$cache_key = "analytics_metrics_overall_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) && ! empty( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			global $wpdb;

			// Calculate totals across all campaigns
			$start_date = gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) );
			$end_date   = gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) );

			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics aggregation query.
			$totals = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT
						COALESCE(SUM(impressions), 0) as total_impressions,
						COALESCE(SUM(clicks), 0) as total_clicks,
						COALESCE(SUM(conversions), 0) as total_conversions,
						COALESCE(SUM(revenue), 0) as total_revenue,
						COALESCE(SUM(discount_given), 0) as total_discount
					FROM %i
					WHERE date_recorded BETWEEN %s AND %s',
					$this->analytics_table,
					$start_date,
					$end_date
				),
				ARRAY_A
			);

			// Get previous period data for comparison
			$previous_period = $this->get_previous_period_dates( $date_range );
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics comparison query.
			$previous_totals = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT
						COALESCE(SUM(impressions), 0) as total_impressions,
						COALESCE(SUM(clicks), 0) as total_clicks,
						COALESCE(SUM(conversions), 0) as total_conversions,
						COALESCE(SUM(revenue), 0) as total_revenue
					FROM %i
					WHERE date_recorded BETWEEN %s AND %s',
					$this->analytics_table,
					$previous_period['start_date'],
					$previous_period['end_date']
				),
				ARRAY_A
			);

			// Get active campaigns count (current).
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			$campaigns_table  = $wpdb->prefix . 'wsscd_campaigns';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Active campaigns count.
			$active_campaigns = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i
					WHERE status = \'active\'
					AND deleted_at IS NULL
					AND ( starts_at IS NULL OR starts_at <= NOW() )
					AND ( ends_at IS NULL OR ends_at >= NOW() )',
					$campaigns_table
				)
			);

			// Get active campaigns count (previous period).
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics comparison query.
			$previous_campaigns = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i
					WHERE status = \'active\'
					AND deleted_at IS NULL
					AND ( starts_at IS NULL OR starts_at <= %s )
					AND ( ends_at IS NULL OR ends_at >= %s )',
					$campaigns_table,
					$previous_period['end_date'],
					$previous_period['start_date']
				)
			);

			$metrics = array(
				'date_range'          => $date_range,
				'period'              => $date_conditions,

				// Totals
				'total_impressions'   => (int) $totals['total_impressions'],
				'total_clicks'        => (int) $totals['total_clicks'],
				'total_conversions'   => (int) $totals['total_conversions'],
				'total_revenue'       => (float) $totals['total_revenue'],
				'total_discount'      => (float) ( $totals['total_discount'] ?? 0 ),
				'active_campaigns'    => (int) $active_campaigns,

				// Previous period data for comparison
				'previous_impressions' => (int) $previous_totals['total_impressions'],
				'previous_clicks'      => (int) $previous_totals['total_clicks'],
				'previous_conversions' => (int) $previous_totals['total_conversions'],
				'previous_revenue'     => (float) $previous_totals['total_revenue'],
				'previous_campaigns'   => (int) $previous_campaigns,

				// Averages (calculated by derived metrics)
				'avg_ctr'             => 0,
				'avg_conversion_rate' => 0,
				'avg_order_value'     => 0,
				'avg_roi'             => 0,
			);

			$metrics = $this->calculate_overall_derived_metrics( $metrics );

			if ( $use_cache ) {
				$this->cache_manager->set( $cache_key, $metrics, 3600 );
			}

			return $metrics;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate overall metrics',
				array(
					'date_range' => $date_range,
					'error'      => $e->getMessage(),
				)
			);

			return array();
		}
	}

	/**
	 * Calculate product performance metrics.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @param    string $date_range    Date range.
	 * @param    bool   $use_cache     Whether to use cache.
	 * @return   array                    Product metrics.
	 */
	public function calculate_product_metrics(
		int $product_id,
		string $date_range = '7days',
		bool $use_cache = true
	): array {
		$cache_key = "analytics_metrics_product_{$product_id}_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) && ! empty( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			global $wpdb;
			$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';

			// Calculate date range
			$start_date = gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) );
			$end_date   = gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) );

			// Get aggregated product metrics.
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Product analytics aggregation query.
			$totals = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT
						COALESCE(SUM(impressions), 0) as total_impressions,
						COALESCE(SUM(clicks), 0) as total_clicks,
						COALESCE(SUM(conversions), 0) as total_conversions,
						COALESCE(SUM(revenue), 0) as total_revenue,
						COALESCE(SUM(discount_given), 0) as total_discount,
						COALESCE(SUM(profit), 0) as total_profit,
						COALESCE(SUM(quantity_sold), 0) as total_quantity
					FROM %i
					WHERE product_id = %d
					AND date_recorded BETWEEN %s AND %s',
					$product_analytics_table,
					$product_id,
					$start_date,
					$end_date
				),
				ARRAY_A
			);

			// Calculate derived metrics
			$impressions = (int) $totals['total_impressions'];
			$clicks      = (int) $totals['total_clicks'];
			$conversions = (int) $totals['total_conversions'];
			$revenue     = (float) $totals['total_revenue'];

			$ctr              = $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0;
			$conversion_rate  = $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0;
			$avg_order_value  = $conversions > 0 ? round( $revenue / $conversions, 2 ) : 0;

			$metrics = array(
				'product_id'              => $product_id,
				'date_range'              => $date_range,
				'period'                  => $date_conditions,

				// Product-specific metrics
				'impressions'             => $impressions,
				'clicks'                  => $clicks,
				'conversions'             => $conversions,
				'revenue'                 => $revenue,
				'discount_given'          => (float) $totals['total_discount'],
				'profit'                  => (float) $totals['total_profit'],
				'quantity_sold'           => (int) $totals['total_quantity'],

				// Derived metrics
				'ctr'                     => $ctr,
				'conversion_rate'         => $conversion_rate,
				'avg_order_value'         => $avg_order_value,
			);

			if ( $use_cache ) {
				$this->cache_manager->set( $cache_key, $metrics, 1800 );
			}

			return $metrics;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate product metrics',
				array(
					'product_id' => $product_id,
					'date_range' => $date_range,
					'error'      => $e->getMessage(),
				)
			);

			return $this->get_empty_product_metrics( $product_id, $date_range );
		}
	}

	/**
	 * Calculate real-time metrics.
	 *
	 * @since    1.0.0
	 * @param    int|null $campaign_id    Campaign ID (optional).
	 * @return   array                       Real-time metrics.
	 */
	public function calculate_realtime_metrics( ?int $campaign_id = null ): array {
		try {
			$current_hour    = gmdate( 'Y-m-d H:00:00' );
			$date_conditions = array(
				'start_date' => $current_hour,
				'end_date'   => current_time( 'mysql' ),
			);

			if ( $campaign_id ) {
				return array(
					'campaign_id'              => $campaign_id,
					'current_hour_views'       => $this->calculate_views( $campaign_id, $date_conditions ),
					'current_hour_clicks'      => $this->calculate_clicks( $campaign_id, $date_conditions ),
					'current_hour_conversions' => $this->calculate_conversions( $campaign_id, $date_conditions ),
					'current_hour_revenue'     => $this->calculate_revenue( $campaign_id, $date_conditions ),
					'active_sessions'          => $this->get_active_sessions( $campaign_id ),
					'last_updated'             => current_time( 'mysql' ),
				);
			} else {
				return array(
					'current_hour_views'       => $this->calculate_total_views( $date_conditions ),
					'current_hour_clicks'      => $this->calculate_total_clicks( $date_conditions ),
					'current_hour_conversions' => $this->calculate_total_conversions( $date_conditions ),
					'current_hour_revenue'     => $this->calculate_total_revenue( $date_conditions ),
					'active_campaigns'         => $this->get_currently_active_campaigns(),
					'total_active_sessions'    => $this->get_total_active_sessions(),
					'last_updated'             => current_time( 'mysql' ),
				);
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate real-time metrics',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return array();
		}
	}

	/**
	 * Calculate impressions for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   int                         Number of impressions.
	 */
	private function calculate_impressions( int $campaign_id, array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics metric calculation.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(impressions), 0) FROM %i
				WHERE campaign_id = %d
				AND date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				$campaign_id,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate views for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   int                         Number of views.
	 */
	private function calculate_views( int $campaign_id, array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics metric calculation.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(impressions), 0) FROM %i
				WHERE campaign_id = %d
				AND date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				$campaign_id,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate clicks for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   int                         Number of clicks.
	 */
	private function calculate_clicks( int $campaign_id, array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics metric calculation.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(clicks), 0) FROM %i
				WHERE campaign_id = %d
				AND date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				$campaign_id,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate conversions for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   int                         Number of conversions.
	 */
	private function calculate_conversions( int $campaign_id, array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics metric calculation.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(conversions), 0) FROM %i
				WHERE campaign_id = %d
				AND date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				$campaign_id,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate revenue for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $date_conditions   Date conditions.
	 * @return   float                       Total revenue.
	 */
	private function calculate_revenue( int $campaign_id, array $date_conditions ): float {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics metric calculation.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(revenue), 0) FROM %i
				WHERE campaign_id = %d
				AND date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				$campaign_id,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (float) $result;
	}

	/**
	 * Calculate derived metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $metrics    Base metrics.
	 * @return   array                Metrics with derived values.
	 */
	private function calculate_derived_metrics( array $metrics ): array {
		// Click-through rate
		if ( $metrics['views'] > 0 ) {
			$metrics['ctr'] = round( ( $metrics['clicks'] / $metrics['views'] ) * 100, 2 );
		}

		// Conversion rate
		if ( $metrics['clicks'] > 0 ) {
			$metrics['conversion_rate'] = round( ( $metrics['conversions'] / $metrics['clicks'] ) * 100, 2 );
		}

		// Average order value
		if ( $metrics['conversions'] > 0 ) {
			$metrics['avg_order_value'] = round( $metrics['revenue'] / $metrics['conversions'], 2 );
		}

		// Cart abandonment rate
		if ( isset( $metrics['cart_additions'] ) && $metrics['cart_additions'] > 0 ) {
			$abandonment                      = $metrics['cart_additions'] - $metrics['conversions'];
			$metrics['cart_abandonment_rate'] = round( ( $abandonment / $metrics['cart_additions'] ) * 100, 2 );
		}

		return $metrics;
	}

	/**
	 * Get empty metrics structure.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $date_range     Date range.
	 * @return   array                     Empty metrics.
	 */
	private function get_empty_metrics( int $campaign_id, string $date_range ): array {
		return array(
			'campaign_id'           => $campaign_id,
			'date_range'            => $date_range,
			'impressions'           => 0,
			'views'                 => 0,
			'clicks'                => 0,
			'conversions'           => 0,
			'revenue'               => 0.0,
			'ctr'                   => 0.0,
			'conversion_rate'       => 0.0,
			'avg_order_value'       => 0.0,
			'roi'                   => 0.0,
			'roas'                  => 0.0,
			'unique_visitors'       => 0,
			'bounce_rate'           => 0.0,
			'time_to_conversion'    => 0,
			'products_viewed'       => 0,
			'cart_additions'        => 0,
			'cart_abandonment_rate' => 0.0,
			'daily_trends'          => array(),
			'hourly_trends'         => array(),
		);
	}

	/**
	 * Calculate metrics for all campaigns.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range.
	 * @param    bool   $use_cache     Whether to use cache.
	 * @return   array                    All campaigns metrics.
	 */
	public function calculate_all_campaigns_metrics( string $date_range = '7days', bool $use_cache = true ): array {
		$cache_key = "analytics_metrics_all_campaigns_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			$campaign_ids = $this->get_all_campaign_ids();

			if ( empty( $campaign_ids ) ) {
				return array();
			}

			$campaigns_metrics = array();

			foreach ( $campaign_ids as $campaign_id ) {
				$campaign_metrics = $this->calculate_campaign_metrics( $campaign_id, $date_range, $use_cache );

				if ( ! empty( $campaign_metrics ) ) {
					$campaigns_metrics[] = $campaign_metrics;
				}
			}

			if ( $use_cache ) {
				$this->cache_manager->set( $cache_key, $campaigns_metrics, 1800 );
			}

			return $campaigns_metrics;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate all campaigns metrics',
				array(
					'date_range' => $date_range,
					'error'      => $e->getMessage(),
				)
			);

			return array();
		}
	}

	/**
	 * Get all campaign IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Campaign IDs.
	 */
	private function get_all_campaign_ids(): array {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics query; table identifier prepared with %i.
		$campaign_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM %i WHERE deleted_at IS NULL AND status IN ('active', 'scheduled') ORDER BY created_at DESC", $campaigns_table )
		);

		return $campaign_ids ? array_map( 'intval', $campaign_ids ) : array();
	}

	/**
	 * Calculate overall derived metrics.
	 *
	 * @since    1.0.0
	 * @param    array $metrics    Base metrics.
	 * @return   array                Metrics with derived values.
	 */
	private function calculate_overall_derived_metrics( array $metrics ): array {
		// Calculate Average Order Value (AOV)
		if ( $metrics['total_conversions'] > 0 ) {
			$metrics['avg_order_value'] = round(
				$metrics['total_revenue'] / $metrics['total_conversions'],
				2
			);
		} else {
			$metrics['avg_order_value'] = 0;
		}

		// Calculate previous period AOV
		$previous_aov = 0;
		if ( isset( $metrics['previous_conversions'] ) && $metrics['previous_conversions'] > 0 ) {
			$previous_aov = round(
				$metrics['previous_revenue'] / $metrics['previous_conversions'],
				2
			);
		}

		// Calculate Click-through Rate (CTR)
		if ( $metrics['total_impressions'] > 0 ) {
			$metrics['avg_ctr'] = round(
				( $metrics['total_clicks'] / $metrics['total_impressions'] ) * 100,
				2
			);
		} else {
			$metrics['avg_ctr'] = 0;
		}

		// Calculate previous period CTR
		$previous_ctr = 0;
		if ( isset( $metrics['previous_impressions'] ) && $metrics['previous_impressions'] > 0 ) {
			$previous_ctr = round(
				( $metrics['previous_clicks'] / $metrics['previous_impressions'] ) * 100,
				2
			);
		}

		// Calculate Conversion Rate
		if ( $metrics['total_clicks'] > 0 ) {
			$metrics['avg_conversion_rate'] = round(
				( $metrics['total_conversions'] / $metrics['total_clicks'] ) * 100,
				2
			);
		} else {
			$metrics['avg_conversion_rate'] = 0;
		}

		// Calculate ROI (Return on Investment)
		// ROI = ((Revenue - Discount) / Discount) * 100
		if ( isset( $metrics['total_discount'] ) && $metrics['total_discount'] > 0 ) {
			$net_profit         = $metrics['total_revenue'] - $metrics['total_discount'];
			$metrics['avg_roi'] = round(
				( $net_profit / $metrics['total_discount'] ) * 100,
				2
			);
		} else {
			// If no discounts given, ROI is 0
			$metrics['avg_roi'] = 0;
		}

		// Calculate period comparison changes
		$metrics['revenue_change'] = $this->calculate_percentage_change(
			$metrics['previous_revenue'] ?? 0,
			$metrics['total_revenue'] ?? 0
		);

		$metrics['conversions_change'] = $this->calculate_percentage_change(
			$metrics['previous_conversions'] ?? 0,
			$metrics['total_conversions'] ?? 0
		);

		$metrics['aov_change'] = $this->calculate_percentage_change(
			$previous_aov,
			$metrics['avg_order_value']
		);

		$metrics['ctr_change'] = $this->calculate_percentage_change(
			$previous_ctr,
			$metrics['avg_ctr']
		);

		$metrics['campaigns_change'] = $this->calculate_percentage_change(
			$metrics['previous_campaigns'] ?? 0,
			$metrics['active_campaigns'] ?? 0
		);

		return $metrics;
	}

	/**
	 * Calculate percentage change between two values.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $old_value    Previous value.
	 * @param    float $new_value    Current value.
	 * @return   float                 Percentage change.
	 */
	private function calculate_percentage_change( float $old_value, float $new_value ): float {
		if ( 0.0 === $old_value ) {
			if ( 0.0 === $new_value ) {
				return 0.0;
			}
			// If old is 0 but new has value, return 100% increase
			return 100.0;
		}

		return round( ( ( $new_value - $old_value ) / $old_value ) * 100, 1 );
	}

	/**
	 * Get previous period dates for comparison.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Current date range.
	 * @return   array                    Previous period start and end dates.
	 */
	private function get_previous_period_dates( string $date_range ): array {
		switch ( $date_range ) {
			case '24hours':
				$days = 1;
				break;
			case '7days':
				$days = 7;
				break;
			case '30days':
				$days = 30;
				break;
			case '90days':
				$days = 90;
				break;
			case 'custom':
				$days = 30; // Default to 30 for custom
				break;
			default:
				$days = 7;
		}

		$previous_end   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$previous_start = gmdate( 'Y-m-d', strtotime( '-' . ( $days * 2 ) . ' days' ) );

		return array(
			'start_date' => $previous_start,
			'end_date'   => $previous_end,
		);
	}

	/**
	 * Clear all analytics metrics cache.
	 *
	 * Should be called when:
	 * - Analytics data is updated (e.g., discount_given values changed)
	 * - Campaign data changes
	 * - User clicks refresh
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache(): void {
		$cache_patterns = array(
			'analytics_metrics_overall_*',
			'analytics_metrics_campaign_*',
			'analytics_metrics_product_*',
			'analytics_metrics_all_campaigns_*',
		);

		foreach ( $cache_patterns as $pattern ) {
			$this->cache_manager->delete_by_pattern( $pattern );
		}

		$this->logger->info( 'Analytics metrics cache cleared' );
	}

	/**
	 * Calculate total views across all campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $date_conditions    Date conditions.
	 * @return   int                          Total views.
	 */
	private function calculate_total_views( array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Real-time analytics query.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(impressions), 0) FROM %i
				WHERE date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate total clicks across all campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $date_conditions    Date conditions.
	 * @return   int                          Total clicks.
	 */
	private function calculate_total_clicks( array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Real-time analytics query.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(clicks), 0) FROM %i
				WHERE date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate total conversions across all campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $date_conditions    Date conditions.
	 * @return   int                          Total conversions.
	 */
	private function calculate_total_conversions( array $date_conditions ): int {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Real-time analytics query.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(conversions), 0) FROM %i
				WHERE date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (int) $result;
	}

	/**
	 * Calculate total revenue across all campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $date_conditions    Date conditions.
	 * @return   float                        Total revenue.
	 */
	private function calculate_total_revenue( array $date_conditions ): float {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Real-time analytics query.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(revenue), 0) FROM %i
				WHERE date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d H:i:s', strtotime( $date_conditions['end_date'] ) )
			)
		);

		return (float) $result;
	}

	/**
	 * Get active sessions for a campaign.
	 *
	 * Counts unique sessions in the last 30 minutes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int                    Number of active sessions.
	 */
	private function get_active_sessions( int $campaign_id ): int {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'wsscd_sessions';

		// Check if sessions table exists.
		$check_table_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table existence check; query prepared above.
		$table_exists = $wpdb->get_var( $check_table_sql );

		if ( ! $table_exists ) {
			// Fall back to counting recent analytics entries as proxy.
			$fallback_sql = $wpdb->prepare(
				'SELECT COUNT(DISTINCT session_id) FROM %i
				WHERE campaign_id = %d
				AND date_recorded >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
				AND session_id IS NOT NULL',
				$this->analytics_table,
				$campaign_id
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Real-time session count; query prepared above.
			$result = $wpdb->get_var( $fallback_sql );

			return (int) $result;
		}

		// Count active sessions from sessions table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Real-time session count.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i
				WHERE campaign_id = %d
				AND last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)',
				$sessions_table,
				$campaign_id
			)
		);

		return (int) $result;
	}

	/**
	 * Get total active sessions across all campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Total active sessions.
	 */
	private function get_total_active_sessions(): int {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'wsscd_sessions';

		// Check if sessions table exists.
		$check_table_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table existence check; query prepared above.
		$table_exists = $wpdb->get_var( $check_table_sql );

		if ( ! $table_exists ) {
			// Fall back to counting recent analytics entries as proxy.
			$fallback_sql = $wpdb->prepare(
				'SELECT COUNT(DISTINCT session_id) FROM %i
				WHERE date_recorded >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
				AND session_id IS NOT NULL',
				$this->analytics_table
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Real-time session count; query prepared above.
			$result = $wpdb->get_var( $fallback_sql );

			return (int) $result;
		}

		// Count active sessions from sessions table.
		$session_count_sql = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i
			WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)',
			$sessions_table
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Real-time session count; query prepared above.
		$result = $wpdb->get_var( $session_count_sql );

		return (int) $result;
	}

	/**
	 * Get count of currently active campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Number of active campaigns.
	 */
	private function get_currently_active_campaigns(): int {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Active campaigns count.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i
				WHERE status = \'active\'
				AND deleted_at IS NULL
				AND ( starts_at IS NULL OR starts_at <= NOW() )
				AND ( ends_at IS NULL OR ends_at >= NOW() )',
				$campaigns_table
			)
		);

		return (int) $result;
	}

	/**
	 * Get empty product metrics structure.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $product_id    Product ID.
	 * @param    string $date_range    Date range.
	 * @return   array                    Empty product metrics.
	 */
	private function get_empty_product_metrics( int $product_id, string $date_range ): array {
		return array(
			'product_id'      => $product_id,
			'date_range'      => $date_range,
			'impressions'     => 0,
			'clicks'          => 0,
			'conversions'     => 0,
			'revenue'         => 0.0,
			'discount_given'  => 0.0,
			'profit'          => 0.0,
			'quantity_sold'   => 0,
			'ctr'             => 0.0,
			'conversion_rate' => 0.0,
			'avg_order_value' => 0.0,
		);
	}

	/**
	 * Get top performing campaigns.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range.
	 * @param    int    $limit         Number of campaigns to return.
	 * @return   array                    Top campaigns data.
	 */
	public function get_top_campaigns( string $date_range = '30days', int $limit = 10 ): array {
		global $wpdb;

		$date_conditions = $this->get_date_range_conditions( $date_range );
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// Get top campaigns by revenue.
		// SECURITY: Use %i placeholder for table identifiers (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Top campaigns query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					c.id,
					c.name,
					c.status,
					COALESCE(SUM(a.revenue), 0) as total_revenue,
					COALESCE(SUM(a.conversions), 0) as total_conversions,
					COALESCE(SUM(a.clicks), 0) as total_clicks,
					COALESCE(SUM(a.impressions), 0) as total_impressions
				FROM %i c
				LEFT JOIN %i a ON c.id = a.campaign_id
					AND a.date_recorded BETWEEN %s AND %s
				WHERE c.deleted_at IS NULL
				GROUP BY c.id, c.name, c.status
				ORDER BY total_revenue DESC
				LIMIT %d',
				$campaigns_table,
				$this->analytics_table,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) ),
				$limit
			),
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		// Calculate derived metrics for each campaign
		foreach ( $results as &$campaign ) {
			$impressions = (int) $campaign['total_impressions'];
			$clicks      = (int) $campaign['total_clicks'];
			$conversions = (int) $campaign['total_conversions'];
			$revenue     = (float) $campaign['total_revenue'];

			$campaign['ctr']             = $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0;
			$campaign['conversion_rate'] = $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0;
			$campaign['avg_order_value'] = $conversions > 0 ? round( $revenue / $conversions, 2 ) : 0;
		}

		return $results;
	}

	/**
	 * Get conversion funnel data.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range.
	 * @return   array                    Funnel data with views, clicks, cart_additions, conversions.
	 */
	public function get_conversion_funnel( string $date_range = '30days' ): array {
		global $wpdb;

		$date_conditions = $this->get_date_range_conditions( $date_range );

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Funnel query.
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					COALESCE(SUM(impressions), 0) as views,
					COALESCE(SUM(clicks), 0) as clicks,
					COALESCE(SUM(cart_additions), 0) as cart_additions,
					COALESCE(SUM(conversions), 0) as conversions
				FROM %i
				WHERE date_recorded BETWEEN %s AND %s',
				$this->analytics_table,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			),
			ARRAY_A
		);

		if ( ! $totals ) {
			return array(
				'views'          => 0,
				'clicks'         => 0,
				'cart_additions' => 0,
				'conversions'    => 0,
				'rates'          => array(
					'view_to_click'      => 0,
					'click_to_cart'      => 0,
					'cart_to_conversion' => 0,
				),
			);
		}

		$views          = (int) $totals['views'];
		$clicks         = (int) $totals['clicks'];
		$cart_additions = (int) $totals['cart_additions'];
		$conversions    = (int) $totals['conversions'];

		return array(
			'views'          => $views,
			'clicks'         => $clicks,
			'cart_additions' => $cart_additions,
			'conversions'    => $conversions,
			'rates'          => array(
				'view_to_click'      => $views > 0 ? round( ( $clicks / $views ) * 100, 2 ) : 0,
				'click_to_cart'      => $clicks > 0 ? round( ( $cart_additions / $clicks ) * 100, 2 ) : 0,
				'cart_to_conversion' => $cart_additions > 0 ? round( ( $conversions / $cart_additions ) * 100, 2 ) : 0,
			),
		);
	}

	/**
	 * Get revenue trend data.
	 *
	 * @since    1.0.0
	 * @param    string $date_range     Date range.
	 * @param    string $granularity    Data granularity (daily, weekly, monthly).
	 * @return   array                     Trend data with labels and values.
	 */
	public function get_revenue_trend( string $date_range = '30days', string $granularity = 'daily' ): array {
		global $wpdb;

		$date_conditions = $this->get_date_range_conditions( $date_range );

		// Determine date format for grouping
		switch ( $granularity ) {
			case 'weekly':
				$date_format = '%Y-%u';
				$label_format = 'Week %u, %Y';
				break;
			case 'monthly':
				$date_format = '%Y-%m';
				$label_format = '%M %Y';
				break;
			default:
				$date_format = '%Y-%m-%d';
				$label_format = '%b %d';
		}

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Revenue trend query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					DATE_FORMAT(date_recorded, %s) as period,
					COALESCE(SUM(revenue), 0) as revenue,
					COALESCE(SUM(conversions), 0) as conversions,
					COALESCE(SUM(discount_given), 0) as discount_given
				FROM %i
				WHERE date_recorded BETWEEN %s AND %s
				GROUP BY period
				ORDER BY period ASC',
				$date_format,
				$this->analytics_table,
				gmdate( 'Y-m-d', strtotime( $date_conditions['start_date'] ) ),
				gmdate( 'Y-m-d', strtotime( $date_conditions['end_date'] ) )
			),
			ARRAY_A
		);

		$labels = array();
		$values = array();
		$conversions = array();
		$discounts = array();

		if ( $results ) {
			foreach ( $results as $row ) {
				$labels[] = $row['period'];
				$values[] = (float) $row['revenue'];
				$conversions[] = (int) $row['conversions'];
				$discounts[] = (float) $row['discount_given'];
			}
		}

		return array(
			'labels'      => $labels,
			'values'      => $values,
			'conversions' => $conversions,
			'discounts'   => $discounts,
			'granularity' => $granularity,
		);
	}
}
