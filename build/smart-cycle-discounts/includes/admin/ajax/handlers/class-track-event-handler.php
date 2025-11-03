<?php
/**
 * Track Event Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-track-event-handler.php
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
 * Track Event Handler Class
 *
 * Handles analytics event tracking via AJAX
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Track_Event_Handler extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_track_event';
	}

	/**
	 * Handle track event request
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data
	 * @return   array                Response data
	 */
	protected function handle( $request ) {
		// Get the analytics collector instance
		$container = SCD_Container::get_instance();

		if ( ! $container->has( 'analytics_collector' ) ) {
			return $this->error(
				__( 'Analytics service not available', 'smart-cycle-discounts' ),
				'analytics_not_available',
				503
			);
		}

		$analytics_collector = $container->get( 'analytics_collector' );

		// Extract event data from request
		$event_type      = isset( $request['event_type'] ) ? sanitize_text_field( $request['event_type'] ) : '';
		$event_data      = isset( $request['event_data'] ) ? $request['event_data'] : array();
		$campaign_id_raw = isset( $request['campaign_id'] ) ? absint( $request['campaign_id'] ) : 0;
		$campaign_id     = $campaign_id_raw > 0 ? $campaign_id_raw : null;
		$user_id_raw     = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : 0;
		$user_id         = $user_id_raw > 0 ? $user_id_raw : null;

		if ( empty( $event_type ) ) {
			return $this->error(
				__( 'Event type is required', 'smart-cycle-discounts' ),
				'missing_event_type'
			);
		}

		// Call the track_event method
		if ( method_exists( $analytics_collector, 'track_event' ) ) {
			$result = $analytics_collector->track_event( $event_type, $event_data, $campaign_id, $user_id );

			if ( $result ) {
				return $this->success(
					array(
						'message'    => __( 'Event tracked successfully', 'smart-cycle-discounts' ),
						'event_type' => $event_type,
					)
				);
			} else {
				return $this->error(
					__( 'Failed to track event', 'smart-cycle-discounts' ),
					'tracking_failed'
				);
			}
		}

		return $this->error(
			__( 'Track event method not found', 'smart-cycle-discounts' ),
			'method_not_found',
			501
		);
	}
}
