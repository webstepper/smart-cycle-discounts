<?php
/**
 * Quick Edit AJAX Handler
 *
 * Handles inline quick edit of campaigns.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Quick Edit Handler Class
 *
 * @since      1.0.0
 */
class SCD_Quick_Edit_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private $campaign_manager;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 * @param    SCD_Logger              $logger              Logger instance.
	 */
	public function __construct( $campaign_manager, $logger = null ) {
		parent::__construct( $logger );
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_quick_edit';
	}

	/**
	 * Handle quick edit request.
	 *
	 * @since    1.0.0
	 * @param    array    $request    Request data.
	 * @return   array                Response array.
	 */
	protected function handle( $request ) {
		// NOTE: Quick edit is FREE (core freemium feature)
		// Users can edit their own campaigns regardless of license

		// Validate campaign ID
		$campaign_id = $this->sanitize_int( $this->get_param( $request, 'campaign_id' ) );
		if ( ! $campaign_id ) {
			return $this->error(
				__( 'Invalid campaign ID.', 'smart-cycle-discounts' ),
				'invalid_campaign_id'
			);
		}

		// Get campaign
		$campaign = $this->campaign_manager->find( $campaign_id );
		if ( ! $campaign ) {
			return $this->error(
				__( 'Campaign not found.', 'smart-cycle-discounts' ),
				'campaign_not_found',
				404
			);
		}

		// Prepare update data
		$update_data = $this->prepare_update_data( $request );

		// Validate update data
		if ( empty( $update_data ) ) {
			return $this->error(
				__( 'No valid data to update.', 'smart-cycle-discounts' ),
				'no_update_data'
			);
		}

		// Update campaign
		$result = $this->campaign_manager->update( $campaign_id, $update_data );

		// Handle update errors
		if ( is_wp_error( $result ) ) {
			return $this->handle_wp_error( $result );
		}

		// Log successful update
		$this->log_info( 'Campaign quick edit successful', array(
			'campaign_id'  => $campaign_id,
			'updated_fields' => array_keys( $update_data ),
		) );

		// Return success response
		return $this->success( array(
			'message'  => __( 'Campaign updated successfully.', 'smart-cycle-discounts' ),
			'campaign' => array(
				'id'       => $campaign_id,
				'name'     => isset( $update_data['name'] ) ? $update_data['name'] : $campaign->get_name(),
				'status'   => isset( $update_data['status'] ) ? $update_data['status'] : $campaign->get_status(),
				'priority' => isset( $update_data['priority'] ) ? $update_data['priority'] : $campaign->get_priority(),
			),
		) );
	}

	/**
	 * Prepare update data from request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $request    Request data.
	 * @return   array                Update data.
	 */
	private function prepare_update_data( $request ) {
		$update_data = array();

		// Campaign name
		if ( isset( $request['name'] ) ) {
			$name = $this->sanitize_text( $request['name'] );
			if ( ! empty( $name ) ) {
				$update_data['name'] = $name;
			}
		}

		// Status
		if ( isset( $request['status'] ) ) {
			$status = sanitize_key( $request['status'] );
			$valid_statuses = array( 'draft', 'active', 'paused', 'scheduled', 'expired', 'archived' );
			if ( in_array( $status, $valid_statuses, true ) ) {
				$update_data['status'] = $status;
			}
		}

		// Priority
		if ( isset( $request['priority'] ) ) {
			$priority = $this->sanitize_int( $request['priority'] );
			if ( $priority >= 1 && $priority <= 100 ) {
				$update_data['priority'] = $priority;
			}
		}

		// Discount value
		if ( isset( $request['discount_value'] ) ) {
			$discount_value = floatval( $request['discount_value'] );
			if ( $discount_value > 0 ) {
				$update_data['discount_value'] = $discount_value;
			}
		}

		// Start date
		if ( isset( $request['start_date'] ) ) {
			$start_date = $this->sanitize_text( $request['start_date'] );
			if ( ! empty( $start_date ) ) {
				$update_data['start_date'] = $start_date;
			}
		}

		// End date
		if ( isset( $request['end_date'] ) ) {
			$end_date = $this->sanitize_text( $request['end_date'] );
			if ( ! empty( $end_date ) ) {
				$update_data['end_date'] = $end_date;
			}
		}

		return $update_data;
	}
}
