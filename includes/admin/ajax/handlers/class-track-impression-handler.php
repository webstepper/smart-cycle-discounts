<?php
/**
 * Track Impression Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
 * Track Impression Handler Class
 *
 * Handles campaign impression tracking via AJAX.
 * Uses lightweight aggregated storage for high-volume frontend tracking.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Track_Impression_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger $logger    Logger instance (optional).
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
	protected function get_action_name(): string {
		return 'wsscd_track_impression';
	}

	/**
	 * Check if authentication is required.
	 *
	 * @since    1.0.0
	 * @return   bool    False - impressions can be tracked by non-logged-in users.
	 */
	protected function requires_authentication(): bool {
		return false;
	}

	/**
	 * Handle track impression request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( array $request ): array {
		$container = WSSCD_Container::get_instance();

		if ( ! $container->has( 'analytics_collector' ) ) {
			return $this->error(
				__( 'Analytics service not available', 'smart-cycle-discounts' ),
				'analytics_not_available',
				503
			);
		}

		$analytics_collector = $container->get( 'analytics_collector' );

		$campaign_id_raw = isset( $request['campaign_id'] ) ? absint( $request['campaign_id'] ) : 0;

		if ( $campaign_id_raw <= 0 ) {
			return $this->error(
				__( 'Campaign ID is required', 'smart-cycle-discounts' ),
				'missing_campaign_id'
			);
		}

		// Optional context data
		$context = array();
		if ( isset( $request['product_id'] ) ) {
			$context['product_id'] = absint( $request['product_id'] );
		}
		if ( isset( $request['source'] ) ) {
			$context['source'] = sanitize_text_field( $request['source'] );
		}

		// Track impression using optimized method
		if ( method_exists( $analytics_collector, 'track_impression' ) ) {
			$result = $analytics_collector->track_impression( $campaign_id_raw, $context );

			if ( $result ) {
				return $this->success(
					array(
						'message'     => __( 'Impression tracked', 'smart-cycle-discounts' ),
						'campaign_id' => $campaign_id_raw,
					)
				);
			} else {
				return $this->error(
					__( 'Failed to track impression', 'smart-cycle-discounts' ),
					'tracking_failed'
				);
			}
		}

		return $this->error(
			__( 'Track impression method not found', 'smart-cycle-discounts' ),
			'method_not_found',
			501
		);
	}
}
