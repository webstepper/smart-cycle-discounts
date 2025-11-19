<?php
/**
 * Campaign Edit Controller Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-edit-controller.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign Edit Controller Class
 *
 * @since      1.0.0
 */
class SCD_Campaign_Edit_Controller extends SCD_Abstract_Campaign_Controller {

	/**
	 * View renderer instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_View_Renderer
	 */
	private SCD_Campaign_View_Renderer $view_renderer;

	// Validator removed - using consolidated SCD_Validation class directly

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager         $campaign_manager     Campaign manager.
	 * @param    SCD_Admin_Capability_Manager $capability_manager   Capability manager.
	 * @param    SCD_Logger                   $logger               Logger instance.
	 * @param    SCD_Campaign_View_Renderer   $view_renderer        View renderer.
	 */
	public function __construct(SCD_Cache_Manager $cache, SCD_Campaign_Manager $campaign_manager,
		SCD_Admin_Capability_Manager $capability_manager,
		SCD_Logger $logger,
		SCD_Campaign_View_Renderer $view_renderer) {
		$this->cache = $cache;
		parent::__construct( $campaign_manager, $capability_manager, $logger );
		$this->view_renderer = $view_renderer;
	}

	/**
	 * Handle the edit action.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function handle( int $campaign_id ): void {
		if ( ! $this->check_capability( 'scd_edit_campaigns' ) ) {
			wp_die( __( 'You do not have permission to edit campaigns.', 'smart-cycle-discounts' ) );
		}

		$campaign = $this->campaign_manager->find( $campaign_id );
		if ( ! $campaign ) {
			wp_die( __( 'Campaign not found.', 'smart-cycle-discounts' ) );
		}

		if ( ! $this->can_edit_campaign( $campaign ) ) {
			wp_die( __( 'You do not have permission to edit this campaign.', 'smart-cycle-discounts' ) );
		}

		$this->render( $campaign );
	}

	/**
	 * Handle save campaign.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_save(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['scd_campaign_nonce'] ?? '', 'scd_save_campaign' ) ) {
			wp_die( __( 'Security check failed.', 'smart-cycle-discounts' ) );
		}

		if ( ! $this->check_capability( 'scd_edit_campaigns' ) ) {
			wp_die( __( 'You do not have permission to save campaigns.', 'smart-cycle-discounts' ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		// For existing campaigns, verify ownership
		if ( $campaign_id ) {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				wp_die( __( 'Campaign not found.', 'smart-cycle-discounts' ) );
			}

			if ( ! $this->can_edit_campaign( $campaign ) ) {
				wp_die( __( 'You do not have permission to edit this campaign.', 'smart-cycle-discounts' ) );
			}
		}

		$validation_result = SCD_Validation::validate( $_POST, 'campaign_complete' );
		if ( is_wp_error( $validation_result ) ) {
			// Convert WP_Error to array format expected by handle_validation_errors
			$errors = array();
			foreach ( $validation_result->get_error_codes() as $code ) {
				$errors[ $code ] = $validation_result->get_error_messages( $code );
			}
			$this->handle_validation_errors( $errors, $campaign_id );
			return;
		}

		try {
			$campaign_data = $this->prepare_campaign_data( $_POST );

			if ( $campaign_id ) {
				$result = $this->campaign_manager->update_campaign( $campaign_id, $campaign_data );
			} else {
				$result      = $this->campaign_manager->create_campaign( $campaign_data );
				$campaign_id = $result;
			}

			if ( $result ) {
				$this->redirect_with_message(
					admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $campaign_id ),
					__( 'Campaign saved successfully.', 'smart-cycle-discounts' ),
					'success'
				);
			} else {
				throw new Exception( __( 'Failed to save campaign.', 'smart-cycle-discounts' ) );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign save failed',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			$this->add_notice( $e->getMessage(), 'error' );
			$this->render( $campaign_id ? $this->campaign_manager->find( $campaign_id ) : null );
		}
	}

	/**
	 * Check if user can edit campaign.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   bool                         True if can edit.
	 */
	private function can_edit_campaign( SCD_Campaign $campaign ): bool {
		$current_user_id = get_current_user_id();

		// Administrators can edit any campaign
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( $this->check_capability( 'scd_edit_campaigns' ) ) {
			return $campaign->get_created_by() === $current_user_id;
		}

		if ( $this->check_capability( 'scd_edit_own_campaigns' ) ) {
			return $campaign->get_created_by() === $current_user_id;
		}

		return false;
	}

	/**
	 * Prepare campaign data for saving.
	 *
	 * @since    1.0.0
	 * @param    array $post_data    POST data.
	 * @return   array                  Prepared campaign data.
	 */
	private function prepare_campaign_data( array $post_data ): array {
		$sanitized = array();

		// Text fields
		$sanitized['name']          = sanitize_text_field( $post_data['name'] ?? '' );
		$sanitized['description']   = sanitize_textarea_field( $post_data['description'] ?? '' );
		$sanitized['status']        = sanitize_key( $post_data['status'] ?? '' );
		$sanitized['target_type']   = sanitize_text_field( $post_data['target_type'] ?? '' );
		$sanitized['discount_type'] = sanitize_key( $post_data['discount_type'] ?? '' );

		// Numeric fields
		$sanitized['discount_value'] = isset( $post_data['discount_value'] ) ? floatval( $post_data['discount_value'] ) : 0;
		$sanitized['min_quantity']   = isset( $post_data['min_quantity'] ) ? absint( $post_data['min_quantity'] ) : 0;
		$sanitized['max_discount']   = isset( $post_data['max_discount'] ) ? floatval( $post_data['max_discount'] ) : 0;
		$sanitized['usage_limit']    = isset( $post_data['usage_limit'] ) ? absint( $post_data['usage_limit'] ) : 0;

		// Date fields
		$sanitized['start_date'] = sanitize_text_field( $post_data['start_date'] ?? '' );
		$sanitized['end_date']   = sanitize_text_field( $post_data['end_date'] ?? '' );

		// Array fields
		$sanitized['product_ids']  = isset( $post_data['product_ids'] ) ? array_map( 'absint', (array) $post_data['product_ids'] ) : array();
		$sanitized['category_ids'] = isset( $post_data['category_ids'] ) ? array_map( 'absint', (array) $post_data['category_ids'] ) : array();
		$sanitized['conditions']   = isset( $post_data['conditions'] ) ? (array) $post_data['conditions'] : array();

		$sanitized['product_ids']  = array_filter( $sanitized['product_ids'] );
		$sanitized['category_ids'] = array_filter( $sanitized['category_ids'] );

		return $sanitized;
	}

	/**
	 * Prepare conditions data.

	/**
	 * Handle validation errors.
	 *
	 * @since    1.0.0
	 * @param    array $errors         Validation errors.
	 * @param    int   $campaign_id    Campaign ID.
	 * @return   void
	 */
	private function handle_validation_errors( array $errors, int $campaign_id ): void {
		foreach ( $errors as $field => $messages ) {
			foreach ( $messages as $message ) {
				$this->add_notice( $message, 'error' );
			}
		}

		// Preserve form data
		$this->cache->set( 'scd_campaign_form_data', $_POST, 60 );

		// Redirect back to edit page
		$url = $campaign_id
			? admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $campaign_id )
			: admin_url( 'admin.php?page=scd-campaigns&action=new' );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the edit form.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign|null $campaign    Campaign object or null for new.
	 * @return   void
	 */
	private function render( ?SCD_Campaign $campaign ): void {
		$form_data = $this->cache->get( 'scd_campaign_form_data' );
		if ( $form_data ) {
			$this->cache->delete( 'scd_campaign_form_data' );
		}

		$this->view_renderer->render_edit_form( $campaign, $form_data ?: array() );
	}
}
