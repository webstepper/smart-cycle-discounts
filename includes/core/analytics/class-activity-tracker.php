<?php
/**
 * Activity Tracker Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-activity-tracker.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Activity Tracker Class
 *
 * @since      1.0.0
 */
class WSSCD_Activity_Tracker {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Database_Manager
	 */
	private WSSCD_Database_Manager $database_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Logger
	 */
	private WSSCD_Logger $logger;

	/**
	 * Initialize the activity tracker.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Database_Manager $database_manager    Database manager.
	 * @param    WSSCD_Logger           $logger              Logger instance.
	 */
	public function __construct(
		WSSCD_Database_Manager $database_manager,
		WSSCD_Logger $logger
	) {
		$this->database_manager = $database_manager;
		$this->logger           = $logger;
	}

	/**
	 * Get activities.
	 *
	 * @since    1.0.0
	 * @param    array $args    Query arguments.
	 * @return   array             Activity data.
	 */
	public function get_activities( array $args = array() ): array {
		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'type'   => 'all',
		);

		$args = array_merge( $defaults, $args );

		try {
			$query   = $this->build_activity_query( $args );
			$results = $this->database_manager->get_results( $query );

			if ( empty( $results ) ) {
				return array();
			}

			$activities = array();
			foreach ( $results as $row ) {
				$activities[] = $this->format_activity( $row );
			}

			return $activities;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get activities',
				array(
					'args'  => $args,
					'error' => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Build activity query.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $args    Query arguments.
	 * @return   string             SQL query.
	 */
	private function build_activity_query( array $args ): string {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$type_filter     = '';

		if ( 'all' !== $args['type'] ) {
			$type_filter = $wpdb->prepare( ' AND type = %s', $args['type'] );
		}

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// $type_filter is empty or already prepared above with $wpdb->prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$query = $wpdb->prepare(
			'SELECT
				id as campaign_id,
				name as campaign_name,
				status,
				created_at as timestamp,
				%s as activity_type
			FROM %i
			WHERE 1=1 ' . $type_filter . '
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d',
			'campaign_created',
			$campaigns_table,
			$args['limit'],
			$args['offset']
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $query;
	}

	/**
	 * Format activity data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $row    Database row.
	 * @return   array             Formatted activity.
	 */
	private function format_activity( $row ): array {
		$type      = isset( $row->activity_type ) ? sanitize_text_field( $row->activity_type ) : 'unknown';
		$timestamp = isset( $row->timestamp ) ? strtotime( $row->timestamp ) : time();

		return array(
			'id'        => isset( $row->campaign_id ) ? (int) $row->campaign_id : 0,
			'type'      => $type,
			'icon'      => $this->get_activity_icon( $type ),
			'message'   => $this->get_activity_description( $row ),
			'time_ago'  => human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'smart-cycle-discounts' ),
			'timestamp' => $timestamp,
			'meta'      => array(
				'status' => isset( $row->status ) ? sanitize_text_field( $row->status ) : 'unknown',
			),
		);
	}

	/**
	 * Get activity description.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $row    Database row.
	 * @return   string            Activity description.
	 */
	private function get_activity_description( $row ): string {
		$type = isset( $row->activity_type ) ? $row->activity_type : '';
		$name = isset( $row->campaign_name ) ? $row->campaign_name : __( 'Unknown', 'smart-cycle-discounts' );

		switch ( $type ) {
			case 'campaign_created':
				return sprintf(
					/* translators: %s: campaign name */
					__( 'Campaign "%s" was created', 'smart-cycle-discounts' ),
					$name
				);
			case 'campaign_updated':
				return sprintf(
					/* translators: %s: campaign name */
					__( 'Campaign "%s" was updated', 'smart-cycle-discounts' ),
					$name
				);
			case 'campaign_activated':
				return sprintf(
					/* translators: %s: campaign name */
					__( 'Campaign "%s" was activated', 'smart-cycle-discounts' ),
					$name
				);
			case 'campaign_deactivated':
				return sprintf(
					/* translators: %s: campaign name */
					__( 'Campaign "%s" was deactivated', 'smart-cycle-discounts' ),
					$name
				);
			default:
				return sprintf(
					/* translators: %s: campaign name */
					__( 'Activity for campaign "%s"', 'smart-cycle-discounts' ),
					$name
				);
		}
	}

	/**
	 * Get activity icon based on type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Activity type.
	 * @return   string             Dashicons class.
	 */
	private function get_activity_icon( string $type ): string {
		switch ( $type ) {
			case 'campaign_created':
				return 'add';
			case 'campaign_updated':
				return 'edit';
			case 'campaign_activated':
				return 'check';
			case 'campaign_deactivated':
				return 'no-alt';
			case 'discount_applied':
				return 'tag';
			case 'order_completed':
				return 'cart';
			default:
				return 'info';
		}
	}
}
