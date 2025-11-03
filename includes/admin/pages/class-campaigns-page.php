<?php
/**
 * Campaigns Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-campaigns-page.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

/**
 * Campaigns Page Class
 *
 * @since      1.0.0
 */
class SCD_Campaigns_Page {

	/**
	 * Service container.
	 *
	 * @since    1.0.0
	 * @var      SCD_Container
	 */
	private $container;

	/**
	 * List controller.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_List_Controller|null
	 */
	private $list_controller = null;

	/**
	 * Edit controller.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Edit_Controller|null
	 */
	private $edit_controller = null;

	/**
	 * Wizard controller.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Wizard_Controller|null
	 */
	private $wizard_controller = null;

	/**
	 * Action handler.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Action_Handler|null
	 */
	private $action_handler = null;

	/**
	 * Initialize the page.
	 *
	 * @since    1.0.0
	 * @param    SCD_Container $container    Service container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Get list controller.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_List_Controller
	 */
	private function get_list_controller() {
		if ( ! $this->list_controller ) {
			$this->list_controller = $this->container->get( 'campaign_list_controller' );
		}
		return $this->list_controller;
	}

	/**
	 * Get edit controller.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_Edit_Controller
	 */
	private function get_edit_controller() {
		if ( ! $this->edit_controller ) {
			$this->edit_controller = $this->container->get( 'campaign_edit_controller' );
		}
		return $this->edit_controller;
	}

	/**
	 * Get wizard controller.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_Wizard_Controller
	 */
	private function get_wizard_controller() {
		if ( ! $this->wizard_controller ) {
			if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
			}
			try {
				$this->wizard_controller = $this->container->get( 'campaign_wizard_controller' );
				if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
				}
			} catch ( Throwable $e ) {
				if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
				}
				throw $e;
			}
		}
		return $this->wizard_controller;
	}

	/**
	 * Get action handler.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_Action_Handler
	 */
	private function get_action_handler() {
		if ( ! $this->action_handler ) {
			$this->action_handler = $this->container->get( 'campaign_action_handler' );
		}
		return $this->action_handler;
	}

	/**
	 * Render the page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';

		// Debug logging
		if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
		}

		switch ( $action ) {
			case 'edit':
				$campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
				if ( $campaign_id ) {
					$this->get_edit_controller()->handle( $campaign_id );
				} else {
					$this->redirect_to_list();
				}
				break;

			case 'new':
				$this->get_edit_controller()->handle( 0 );
				break;

			case 'wizard':
				if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
				}
				try {
					$wizard_controller = $this->get_wizard_controller();
					if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
					}
					$wizard_controller->handle();
				} catch ( Throwable $e ) {
					if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
					}
					throw $e;
				}
				break;

			case 'delete':
				$this->get_action_handler()->handle_delete();
				break;

			case 'restore':
				$this->get_action_handler()->handle_restore();
				break;

			case 'delete_permanently':
				$this->get_action_handler()->handle_delete_permanently();
				break;

			case 'duplicate':
				$this->get_action_handler()->handle_duplicate();
				break;

			case 'activate':
				$this->get_action_handler()->handle_activate();
				break;

			case 'deactivate':
				$this->get_action_handler()->handle_deactivate();
				break;

			case 'stop_recurring':
				$this->get_action_handler()->handle_stop_recurring();
				break;

			case 'empty_trash':
				$this->get_action_handler()->handle_empty_trash();
				break;

			case 'currency-review':
				require_once SCD_INCLUDES_DIR . 'admin/pages/class-currency-review-page.php';
				require_once SCD_INCLUDES_DIR . 'core/services/class-currency-change-service.php';
				$currency_service     = new SCD_Currency_Change_Service( $this->container->get( 'campaign_repository' ) );
				$currency_review_page = new SCD_Currency_Review_Page( $currency_service );
				$currency_review_page->render_page();
				break;

			case 'list':
			default:
				$this->get_list_controller()->handle();
				break;
		}
	}

	/**
	 * Handle save action.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_save() {
		$this->get_edit_controller()->handle_save();
	}

	/**
	 * Redirect to list page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function redirect_to_list() {
		wp_safe_redirect( admin_url( 'admin.php?page=scd-campaigns' ) );
		exit;
	}
}
