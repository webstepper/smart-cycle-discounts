<?php
/**
 * Get Active Campaigns Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-get-active-campaigns-handler.php
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
 * Get Active Campaigns Handler Class
 *
 * Provides active campaign data for conflict detection.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Get_Active_Campaigns_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache    Cache manager.
	 */
	private WSSCD_Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Cache_Manager $cache     Cache manager instance.
	 * @param    WSSCD_Logger        $logger    Logger instance (optional).
	 */
	public function __construct( WSSCD_Cache_Manager $cache, $logger = null ) {
		parent::__construct( $logger );
		$this->cache = $cache;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_get_active_campaigns';
	}

	/**
	 * Handle the get active campaigns request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wsscd_campaigns';
		$exclude_id = isset( $request['exclude_id'] ) ? intval( $request['exclude_id'] ) : 0;

		$cache_key = 'wsscd_active_campaigns_' . $exclude_id;
		$cached    = $this->cache->get( $cache_key );

		if ( null !== $cached ) {
			return $this->success( $cached );
		}

		// Query for active or scheduled campaigns
		// SECURITY: Use correct schema column names and prepared statements
		// INDEX OPTIMIZATION: Migration 006 adds index on (status, schedule_start, schedule_end)

		// Build query with optional exclude_id filter.
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		if ( $exclude_id > 0 ) {
			$query = $wpdb->prepare(
				'SELECT
					id,
					name,
					schedule_start,
					schedule_end,
					status,
					priority,
					product_selection_type,
					product_selection_data,
					enable_recurring
				FROM %i
				WHERE status IN (\'active\', \'scheduled\', \'draft\')
				AND (schedule_end IS NULL OR schedule_end >= NOW())
				AND id != %d
				ORDER BY schedule_start ASC, priority DESC',
				$table_name,
				$exclude_id
			);
		} else {
			$query = $wpdb->prepare(
				'SELECT
					id,
					name,
					schedule_start,
					schedule_end,
					status,
					priority,
					product_selection_type,
					product_selection_data,
					enable_recurring
				FROM %i
				WHERE status IN (\'active\', \'scheduled\', \'draft\')
				AND (schedule_end IS NULL OR schedule_end >= NOW())
				ORDER BY schedule_start ASC, priority DESC',
				$table_name
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$campaigns = $wpdb->get_results( $query, ARRAY_A );

		$processed_campaigns = array();
		foreach ( $campaigns as $campaign ) {
			// SECURITY: Decode JSON safely with error handling
			$product_data = array();
			if ( ! empty( $campaign['product_selection_data'] ) ) {
				$decoded = json_decode( $campaign['product_selection_data'], true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					$product_data = $decoded;
				}
			}

			$processed_campaigns[] = array(
				'id'                     => intval( $campaign['id'] ),
				'name'                   => sanitize_text_field( $campaign['name'] ),
				'start_date'             => $campaign['schedule_start'],
				'end_date'               => $campaign['schedule_end'],
				'status'                 => sanitize_text_field( $campaign['status'] ),
				'priority'               => intval( $campaign['priority'] ),
				'product_selection_type' => sanitize_text_field( $campaign['product_selection_type'] ),
				'categories'             => isset( $product_data['categories'] ) && is_array( $product_data['categories'] ) ? $product_data['categories'] : array(),
				'product_ids'            => isset( $product_data['product_ids'] ) && is_array( $product_data['product_ids'] ) ? $product_data['product_ids'] : array(),
				'duration_days'          => $this->calculate_duration_days( $campaign['schedule_start'], $campaign['schedule_end'] ),
				'is_recurring'           => ! empty( $campaign['enable_recurring'] ),
				'overlap_risk'           => $this->calculate_overlap_risk( $campaign ),
			);
		}

		$stats = array(
			'total_active'          => $this->count_campaigns_by_status( $processed_campaigns, 'active' ),
			'total_scheduled'       => $this->count_campaigns_by_status( $processed_campaigns, 'scheduled' ),
			'total_draft'           => $this->count_campaigns_by_status( $processed_campaigns, 'draft' ),
			'average_duration'      => $this->calculate_average_duration( $processed_campaigns ),
			'priority_distribution' => $this->calculate_priority_distribution( $processed_campaigns ),
		);

		$response = array(
			'campaigns' => $processed_campaigns,
			'stats'     => $stats,
			'timestamp' => current_time( 'timestamp' ),
		);

		$this->cache->set( $cache_key, $response, 300 );

		return $this->success( $response );
	}

	/**
	 * Count campaigns by status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaigns    Campaigns array.
	 * @param    string $status       Status to count.
	 * @return   int                     Count of campaigns with status.
	 */
	private function count_campaigns_by_status( $campaigns, $status ) {
		$count = 0;
		foreach ( $campaigns as $campaign ) {
			if ( isset( $campaign['status'] ) && $status === $campaign['status'] ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Calculate duration in days
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $start_date    Start date.
	 * @param    string $end_date      End date.
	 * @return   int|null                 Duration in days or null if indefinite.
	 */
	private function calculate_duration_days( $start_date, $end_date ) {
		if ( ! $end_date ) {
			return null; // Indefinite campaign
		}

		$start = new DateTime( $start_date );
		$end   = new DateTime( $end_date );
		$diff  = $start->diff( $end );

		return $diff->days;
	}


	/**
	 * Calculate overlap risk score
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data (from database).
	 * @return   string               Risk level: low, medium, high.
	 */
	private function calculate_overlap_risk( $campaign ) {
		$risk_factors = 0;

		// SECURITY: Validate input data
		$priority     = isset( $campaign['priority'] ) ? intval( $campaign['priority'] ) : 0;
		$product_type = isset( $campaign['product_selection_type'] ) ? sanitize_text_field( $campaign['product_selection_type'] ) : '';

		// High priority campaigns are more likely to conflict
		if ( $priority >= 5 ) {
			$risk_factors += 2;
		} elseif ( $priority >= 4 ) {
			$risk_factors += 1;
		}

		// All products selection has higher conflict risk
		if ( 'all_products' === $product_type ) {
			$risk_factors += 2;
		}

		// Long duration campaigns have higher risk
		$start    = isset( $campaign['schedule_start'] ) ? $campaign['schedule_start'] : null;
		$end      = isset( $campaign['schedule_end'] ) ? $campaign['schedule_end'] : null;
		$duration = $this->calculate_duration_days( $start, $end );

		if ( null === $duration || $duration > 30 ) {
			$risk_factors += 1;
		}

		// Determine risk level
		if ( $risk_factors >= 4 ) {
			return 'high';
		} elseif ( $risk_factors >= 2 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Calculate average campaign duration
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Campaign data array.
	 * @return   float                  Average duration in days.
	 */
	private function calculate_average_duration( $campaigns ) {
		$durations = array_filter( array_column( $campaigns, 'duration_days' ) );

		if ( empty( $durations ) ) {
			return 0.0;
		}

		return array_sum( $durations ) / count( $durations );
	}

	/**
	 * Calculate priority distribution
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Campaign data array.
	 * @return   array                  Priority distribution.
	 */
	private function calculate_priority_distribution( $campaigns ) {
		$priorities   = array_column( $campaigns, 'priority' );
		$distribution = array_count_values( $priorities );

		// Ensure all priority levels are represented
		for ( $i = 1; $i <= 10; $i++ ) {
			if ( ! isset( $distribution[ $i ] ) ) {
				$distribution[ $i ] = 0;
			}
		}

		ksort( $distribution );
		return $distribution;
	}
}
