<?php
/**
 * Abstract Campaign Controller
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/abstract-campaign-controller.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

/**
 * Abstract Campaign Controller Class
 *
 * @since      1.0.0
 */
abstract class WSSCD_Abstract_Campaign_Controller {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Campaign_Manager
	 */
	protected $campaign_manager;

	/**
	 * Capability manager instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Admin_Capability_Manager
	 */
	protected $capability_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Logger
	 */
	protected $logger;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign_Manager         $campaign_manager     Campaign manager.
	 * @param    WSSCD_Admin_Capability_Manager $capability_manager   Capability manager.
	 * @param    WSSCD_Logger                   $logger               Logger instance.
	 */
	public function __construct(
		$campaign_manager,
		$capability_manager,
		$logger
	) {
		$this->campaign_manager   = $campaign_manager;
		$this->capability_manager = $capability_manager;
		$this->logger             = $logger;
	}

	/**
	 * Check if user has required capability.
	 *
	 * @since    1.0.0
	 * @param    string $capability    Required capability.
	 * @return   bool                     True if user has capability.
	 */
	protected function check_capability( $capability ) {
		return $this->capability_manager->current_user_can( $capability );
	}

	/**
	 * Add admin notice.
	 *
	 * @since    1.0.0
	 * @param    string $message    Notice message.
	 * @param    string $type       Notice type.
	 * @return   void
	 */
	protected function add_notice( $message, $type = 'info' ) {
		add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $type ),
					esc_html( $message )
				);
			}
		);
	}

	/**
	 * Redirect with message.
	 *
	 * @since    1.0.0
	 * @param    string $url        Redirect URL.
	 * @param    string $message    Message.
	 * @param    string $type       Message type.
	 * @return   void
	 */
	protected function redirect_with_message( $url, $message, $type = 'success' ) {
		$notices   = get_transient( 'wsscd_admin_notices' ) ?: array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'wsscd_admin_notices', $notices, 300 );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect with error message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @return   void
	 */
	protected function redirect_with_error( $message ) {
		$this->redirect_with_message(
			wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wsscd-campaigns' ),
			$message,
			'error'
		);
	}

	/**
	 * Check if current user owns the campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True if user owns campaign.
	 */
	protected function check_campaign_ownership( $campaign_id ) {
		$campaign = $this->campaign_manager->find( $campaign_id );
		if ( ! $campaign ) {
			return false;
		}

		$author_id = $campaign->get_created_by();

		return $author_id === get_current_user_id();
	}
}
