<?php
/**
 * Metrics Calculator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-metrics-calculator.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Load analytics helpers trait
require_once SCD_PLUGIN_DIR . 'includes/core/analytics/trait-analytics-helpers.php';

/**
 * Metrics Calculator
 *
 * Calculates performance metrics and KPIs for campaigns and discounts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Metrics_Calculator {

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
	 * Initialize the metrics calculator.
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
		$cache_key = "scd_metrics_campaign_{$campaign_id}_{$date_range}";

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

				// Calculated KPIs
				'ctr'             => 0, // Click-through rate
				'conversion_rate' => 0, // Conversion rate
				'avg_order_value' => 0, // Average order value
				'roi'             => 0, // Return on investment
				'roas'            => 0, // Return on ad spend
			);

			// Calculate derived metrics
			$metrics = $this->calculate_derived_metrics( $metrics );

			// Cache for 30 minutes
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
		$cache_key = "scd_metrics_overall_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) && ! empty( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			$metrics = array(
				'date_range'          => $date_range,
				'period'              => $date_conditions,

				// Averages
				'avg_ctr'             => 0,
				'avg_conversion_rate' => 0,
				'avg_order_value'     => 0,
				'avg_roi'             => 0,
			);

			// Calculate derived metrics
			$metrics = $this->calculate_overall_derived_metrics( $metrics );

			// Cache for 1 hour
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
		$cache_key = "scd_metrics_product_{$product_id}_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) && ! empty( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			$date_conditions = $this->get_date_range_conditions( $date_range );

			$metrics = array(
				'product_id'              => $product_id,
				'date_range'              => $date_range,
				'period'                  => $date_conditions,

				// Product-specific metrics
				'discount_views'          => $this->calculate_product_discount_views( $product_id, $date_conditions ),
				'discount_clicks'         => $this->calculate_product_discount_clicks( $product_id, $date_conditions ),
				'cart_additions'          => $this->calculate_product_cart_additions( $product_id, $date_conditions ),
				'purchases'               => $this->calculate_product_purchases( $product_id, $date_conditions ),
				'revenue'                 => $this->calculate_product_revenue( $product_id, $date_conditions ),

				// Conversion funnel
				'view_to_cart_rate'       => 0,
				'cart_to_purchase_rate'   => 0,
				'overall_conversion_rate' => 0,

				// Campaign associations
				'active_campaigns'        => $this->get_product_active_campaigns( $product_id, $date_conditions ),
				'campaign_performance'    => $this->get_product_campaign_performance( $product_id, $date_conditions ),
			);

			// Calculate derived metrics
			$metrics = $this->calculate_product_derived_metrics( $metrics );

			// Cache for 30 minutes
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
			$current_hour    = date( 'Y-m-d H:00:00' );
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

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->analytics_table} 
             WHERE campaign_id = %d 
             AND event_type = 'campaign_impression'
             AND timestamp BETWEEN %s AND %s",
				$campaign_id,
				$date_conditions['start_date'],
				$date_conditions['end_date']
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

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->analytics_table} 
             WHERE campaign_id = %d 
             AND event_type IN ('campaign_view', 'discount_view')
             AND timestamp BETWEEN %s AND %s",
				$campaign_id,
				$date_conditions['start_date'],
				$date_conditions['end_date']
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

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->analytics_table} 
             WHERE campaign_id = %d 
             AND event_type = 'discount_click'
             AND timestamp BETWEEN %s AND %s",
				$campaign_id,
				$date_conditions['start_date'],
				$date_conditions['end_date']
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

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->analytics_table} 
             WHERE campaign_id = %d 
             AND event_type = 'purchase_complete'
             AND timestamp BETWEEN %s AND %s",
				$campaign_id,
				$date_conditions['start_date'],
				$date_conditions['end_date']
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

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_data FROM {$this->analytics_table} 
             WHERE campaign_id = %d 
             AND event_type = 'purchase_complete'
             AND timestamp BETWEEN %s AND %s",
				$campaign_id,
				$date_conditions['start_date'],
				$date_conditions['end_date']
			)
		);

		$total_revenue = 0.0;

		foreach ( $results as $result ) {
			$event_data = json_decode( $result->event_data, true );
			if ( isset( $event_data['line_total'] ) ) {
				$total_revenue += (float) $event_data['line_total'];
			}
		}

		return $total_revenue;
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
		if ( $metrics['cart_additions'] > 0 ) {
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
		$cache_key = "scd_metrics_all_campaigns_{$date_range}";

		if ( $use_cache ) {
			$cached_metrics = $this->cache_manager->get( $cache_key );
			if ( is_array( $cached_metrics ) ) {
				return $cached_metrics;
			}
		}

		try {
			// Get all active campaign IDs
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

			// Cache for 30 minutes
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

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';

		$campaign_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE deleted_at IS NULL AND status IN ('active', 'scheduled') ORDER BY created_at DESC",
				$campaigns_table
			)
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

		// Calculate Click-through Rate (CTR)
		if ( $metrics['total_impressions'] > 0 ) {
			$metrics['avg_ctr'] = round(
				( $metrics['total_clicks'] / $metrics['total_impressions'] ) * 100,
				2
			);
		} else {
			$metrics['avg_ctr'] = 0;
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

		return $metrics;
	}
}
