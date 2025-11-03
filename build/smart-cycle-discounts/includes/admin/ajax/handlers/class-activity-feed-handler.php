<?php
/**
 * Activity Feed Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-activity-feed-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Activity Feed Handler Class
 *
 * @since      1.0.0
 */
class SCD_Activity_Feed_Handler extends SCD_Abstract_Analytics_Handler {
	use SCD_License_Validation_Trait;

	/**
	 * Activity tracker instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Activity_Tracker
	 */
	private $activity_tracker;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Metrics_Calculator $metrics_calculator    Metrics calculator.
	 * @param    SCD_Logger             $logger                Logger instance.
	 * @param    SCD_Activity_Tracker   $activity_tracker      Activity tracker.
	 */
	public function __construct( $metrics_calculator, $logger, $activity_tracker ) {
		parent::__construct( $metrics_calculator, $logger );
		$this->activity_tracker = $activity_tracker;
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	protected function get_required_capability() {
		return 'scd_view_analytics';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( $request ) {
		// Check license (logic tier - analytics data is premium feature)
		$license_check = $this->validate_license( 'logic' );
		if ( $this->license_validation_failed( $license_check ) ) {
			return $this->license_error_response( $license_check );
		}

		// Verify request
		$verification = $this->verify_request( $request, 'scd_analytics_activity_feed' );
		if ( is_wp_error( $verification ) ) {
			return $this->error(
				$verification->get_error_message(),
				$verification->get_error_code()
			);
		}

		// Sanitize inputs
		$limit  = absint( isset( $request['limit'] ) ? $request['limit'] : 20 );
		$offset = absint( isset( $request['offset'] ) ? $request['offset'] : 0 );
		$type   = sanitize_text_field( isset( $request['type'] ) ? $request['type'] : 'all' );

		try {
			// Get activities from tracker
			$activities = $this->activity_tracker->get_activities(
				array(
					'limit'  => $limit,
					'offset' => $offset,
					'type'   => $type,
				)
			);

			return $this->success(
				array(
					'activities' => $activities,
					'has_more'   => count( $activities ) === $limit,
					'offset'     => $offset + count( $activities ),
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Get activity feed failed',
				array(
					'error' => $e->getMessage(),
				)
			);

			return $this->error(
				sprintf( __( 'Failed to load activity feed: %s', 'smart-cycle-discounts' ), $e->getMessage() ),
				'activity_feed_failed'
			);
		}
	}
}
