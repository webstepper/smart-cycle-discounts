<?php
/**
 * Get Active Campaigns Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-get-active-campaigns-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Get_Active_Campaigns_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_get_active_campaigns';
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

		$table_name = $wpdb->prefix . 'scd_campaigns';
		$exclude_id = isset( $request['exclude_id'] ) ? intval( $request['exclude_id'] ) : 0;

		// Check cache first
		$cache_key = 'scd_active_campaigns_' . $exclude_id;
		$cached    = wp_cache_get( $cache_key, 'scd_campaigns' );

		if ( false !== $cached ) {
			return $this->success( $cached );
		}

		// Query for active or scheduled campaigns
		// SECURITY: Use correct schema column names and prepared statements
		// INDEX OPTIMIZATION: Migration 006 adds index on (status, schedule_start, schedule_end)

		// Build base query with explicit column names (prevent SQL injection)
		$query = "
            SELECT
                id,
                name,
                schedule_start,
                schedule_end,
                status,
                priority,
                product_selection_type,
                product_selection_data
            FROM {$table_name}
            WHERE status IN ('active', 'scheduled', 'draft')
            AND (schedule_end IS NULL OR schedule_end >= NOW())
        ";

		$params = array();

		// Exclude current campaign if editing
		if ( $exclude_id > 0 ) {
			$query   .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		// SECURITY: Use only validated column names for ORDER BY
		$query .= ' ORDER BY schedule_start ASC, priority DESC';

		// Always use prepared statement, even if params is empty (best practice)
		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, ...$params );
		}

		$campaigns = $wpdb->get_results( $query, ARRAY_A );

		// Process campaign data
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
				'is_recurring'           => $this->is_recurring( intval( $campaign['id'] ) ),
				'overlap_risk'           => $this->calculate_overlap_risk( $campaign ),
			);
		}

		// Calculate campaign statistics
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

		// Cache for 5 minutes
		wp_cache_set( $cache_key, $response, 'scd_campaigns', 300 );

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
	 * Check if campaign is recurring
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True if recurring.
	 */
	private function is_recurring( $campaign_id ) {
		global $wpdb;

		$meta_table = $wpdb->prefix . 'scd_campaign_meta';
		$recurring  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$meta_table} WHERE campaign_id = %d AND meta_key = 'recurring'",
				$campaign_id
			)
		);

		return '1' === $recurring || 'true' === $recurring;
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
