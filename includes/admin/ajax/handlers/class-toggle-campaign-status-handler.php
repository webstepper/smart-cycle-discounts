<?php
/**
 * Toggle Campaign Status Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-toggle-campaign-status-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.1.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Toggle Campaign Status Handler Class
 *
 * Handles AJAX requests to pause or resume campaigns from the dashboard.
 *
 * @since      1.1.8
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Toggle_Campaign_Status_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.1.8
	 * @access   private
	 * @var      WSSCD_Campaign_Manager    $campaign_manager    Campaign manager instance.
	 */
	private $campaign_manager;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.1.8
	 * @param    WSSCD_Campaign_Manager $campaign_manager    Campaign manager instance.
	 * @param    WSSCD_Logger           $logger              Logger instance.
	 */
	public function __construct( $campaign_manager, $logger = null ) {
		parent::__construct( $logger );
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.1.8
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_toggle_campaign_status';
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.1.8
	 * @return   string    Required capability.
	 */
	protected function get_required_capability() {
		return 'wsscd_activate_campaigns';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.1.8
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( $request ) {
		$campaign_id = $this->sanitize_int( $this->get_param( $request, 'campaign_id' ) );
		$new_status  = $this->sanitize_text( $this->get_param( $request, 'status' ) );

		if ( ! $campaign_id ) {
			return $this->error(
				__( 'Invalid campaign ID', 'smart-cycle-discounts' ),
				'invalid_campaign_id'
			);
		}

		if ( ! in_array( $new_status, array( 'active', 'paused' ), true ) ) {
			return $this->error(
				__( 'Invalid status. Must be "active" or "paused".', 'smart-cycle-discounts' ),
				'invalid_status'
			);
		}

		if ( 'active' === $new_status ) {
			$result = $this->campaign_manager->activate( $campaign_id );
		} else {
			$result = $this->campaign_manager->pause( $campaign_id );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = 'active' === $new_status
			? __( 'Campaign activated successfully.', 'smart-cycle-discounts' )
			: __( 'Campaign paused successfully.', 'smart-cycle-discounts' );

		return $this->success(
			array(
				'message'     => $message,
				'campaign_id' => $campaign_id,
				'new_status'  => $new_status,
			)
		);
	}
}
