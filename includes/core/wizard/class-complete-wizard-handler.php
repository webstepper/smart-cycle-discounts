<?php
/**
 * Complete Wizard Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-complete-wizard-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Complete Wizard Handler Class
 *
 * Handles the completion of the campaign wizard.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Complete_Wizard_Handler {

	/**
	 * Campaign status constants.
	 *
	 * @since 1.0.0
	 */
	const STATUS_DRAFT     = 'draft';
	const STATUS_ACTIVE    = 'active';
	const STATUS_SCHEDULED = 'scheduled';

	/**
	 * Launch option constants.
	 *
	 * @since 1.0.0
	 */
	const LAUNCH_DRAFT  = 'draft';
	const LAUNCH_ACTIVE = 'active';

	/**
	 * The wizard state service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service    $state_service    State service instance.
	 */
	private $state_service;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_State_Service $state_service    State service instance.
	 */
	public function __construct( $state_service = null ) {
		if ( $state_service ) {
			$this->state_service = $state_service;
		} else {
			if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
				require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
			}
			$this->state_service = new SCD_Wizard_State_Service();
		}
	}

	/**
	 * Handle the completion request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array             Response data.
	 * @throws   Exception         When campaign creation or update fails.
	 */
	public function handle( $request = array() ) {
		try {
			$this->log_debug( 'Starting campaign creation...' );

			$steps_data = $this->get_validated_steps_data();

			$campaign_repository = $this->get_campaign_repository();
			$campaign_manager    = $this->get_campaign_manager();

			// Determine edit mode early.
			$is_edit_mode = $this->state_service->is_edit_mode();
			$campaign_id  = $this->state_service->get( 'campaign_id', 0 );

			// Compile wizard data into campaign data.
			$campaign_data = $this->compile_campaign_data( $steps_data, $campaign_repository );

			$launch_option = $this->get_launch_option( $steps_data, $campaign_data );

			$old_status = $this->get_old_campaign_status( $is_edit_mode, $campaign_id, $campaign_repository );

			// Log status transition.
			$this->log_status_transition( $campaign_data, $old_status, $launch_option );

			$campaign_data['_validation_context'] = 'campaign_compiled';

			$this->log_debug( 'Compiled campaign data: ' . wp_json_encode( $campaign_data ) );
			$this->log_debug( 'Launch option: ' . $launch_option );

			$campaign = $this->save_campaign( $campaign_manager, $is_edit_mode, $campaign_id, $campaign_data );

			$campaign_id     = $campaign->get_id();
			$campaign_status = $campaign->get_status();
			$campaign_name   = $campaign->get_name();

			$this->log_campaign_result( $is_edit_mode, $campaign_id, $campaign_name, $campaign_status );

			$this->state_service->clear_session();

			return $this->build_success_response( $is_edit_mode, $campaign_id, $campaign_name, $campaign_status );

		} catch ( Exception $e ) {
			return $this->handle_exception( $e );
		}
	}

	/**
	 * Get and validate steps data from session.
	 *
	 * @since  1.0.0
	 * @return array Steps data.
	 * @throws Exception When no session data found.
	 */
	private function get_validated_steps_data() {
		$steps_data = $this->state_service->get_all_data();

		if ( empty( $steps_data ) || empty( $steps_data['steps'] ) ) {
			throw new Exception( esc_html__( 'No campaign data found in session.', 'smart-cycle-discounts' ) );
		}

		$this->log_debug( 'Raw steps data: ' . print_r( $steps_data['steps'], true ) );

		return $steps_data;
	}

	/**
	 * Get campaign repository from service container.
	 *
	 * @since  1.0.0
	 * @return object Campaign repository instance.
	 * @throws Exception When plugin not initialized.
	 */
	private function get_campaign_repository() {
		if ( ! class_exists( 'Smart_Cycle_Discounts' ) ) {
			throw new Exception( esc_html__( 'Plugin not initialized.', 'smart-cycle-discounts' ) );
		}

		return Smart_Cycle_Discounts::get_service( 'campaign_repository' );
	}

	/**
	 * Get campaign manager from service container.
	 *
	 * @since  1.0.0
	 * @return object Campaign manager instance.
	 * @throws Exception When campaign manager not available.
	 */
	private function get_campaign_manager() {
		$campaign_manager = Smart_Cycle_Discounts::get_service( 'campaign_manager' );

		$this->log_debug( 'Campaign manager: ' . ( $campaign_manager ? 'Found' : 'Not found' ) );

		if ( ! $campaign_manager ) {
			throw new Exception( esc_html__( 'Campaign manager service not available.', 'smart-cycle-discounts' ) );
		}

		return $campaign_manager;
	}

	/**
	 * Compile campaign data using compiler service.
	 *
	 * @since  1.0.0
	 * @param  array  $steps_data           Steps data from wizard.
	 * @param  object $campaign_repository  Campaign repository instance.
	 * @return array  Compiled campaign data.
	 */
	private function compile_campaign_data( $steps_data, $campaign_repository ) {
		if ( ! class_exists( 'SCD_Campaign_Compiler_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
		}

		$compiler = new SCD_Campaign_Compiler_Service( $campaign_repository );
		return $compiler->compile( $steps_data['steps'] );
	}

	/**
	 * Get launch option with proper fallback chain.
	 *
	 * @since  1.0.0
	 * @param  array $steps_data     Steps data from wizard.
	 * @param  array $campaign_data  Compiled campaign data.
	 * @return string Launch option (active or draft).
	 */
	private function get_launch_option( $steps_data, $campaign_data ) {
		$review_data = $this->state_service->get_step_data( 'review' );

		if ( isset( $review_data['launch_option'] ) && '' !== $review_data['launch_option'] ) {
			$launch_option = $review_data['launch_option'];
		} elseif ( isset( $campaign_data['launch_option'] ) && '' !== $campaign_data['launch_option'] ) {
			$launch_option = $campaign_data['launch_option'];
		} else {
			$launch_option = self::LAUNCH_ACTIVE;
		}

		if ( ! isset( $launch_option ) || '' === $launch_option ) {
			$this->log_debug( 'WARNING: launch_option is empty, defaulting to active' );
			$launch_option = self::LAUNCH_ACTIVE;
		}

		return $launch_option;
	}

	/**
	 * Get old campaign status for transition validation.
	 *
	 * @since  1.0.0
	 * @param  bool   $is_edit_mode         Whether in edit mode.
	 * @param  int    $campaign_id          Campaign ID being edited.
	 * @param  object $campaign_repository  Campaign repository instance.
	 * @return string|null Old campaign status or null if not editing.
	 */
	private function get_old_campaign_status( $is_edit_mode, $campaign_id, $campaign_repository ) {
		if ( ! $is_edit_mode || ! $campaign_id ) {
			return null;
		}

		$existing_campaign = $campaign_repository->find_by_id( $campaign_id );

		if ( is_wp_error( $existing_campaign ) ) {
			$this->log_debug( 'Failed to retrieve campaign ' . $campaign_id . ': ' . $existing_campaign->get_error_message() );
			return null;
		}

		if ( ! $existing_campaign ) {
			$this->log_debug( 'Campaign ' . $campaign_id . ' not found for status transition validation' );
			return null;
		}

		return $existing_campaign->get_status();
	}

	/**
	 * Log status transition for debugging.
	 *
	 * @since 1.0.0
	 * @param array       $campaign_data  Compiled campaign data.
	 * @param string|null $old_status     Old campaign status.
	 * @param string      $launch_option  Launch option selected.
	 */
	private function log_status_transition( $campaign_data, $old_status, $launch_option ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$future_start = 'no';
		if ( ! empty( $campaign_data['starts_at'] ) && strtotime( $campaign_data['starts_at'] ) > time() ) {
			$future_start = 'yes';
		}

	}

	/**
	 * Save campaign (create or update).
	 *
	 * @since  1.0.0
	 * @param  object $campaign_manager  Campaign manager instance.
	 * @param  bool   $is_edit_mode      Whether in edit mode.
	 * @param  int    $campaign_id       Campaign ID (0 for new).
	 * @param  array  $campaign_data     Campaign data to save.
	 * @return object Campaign instance.
	 * @throws Exception When save operation fails.
	 */
	private function save_campaign( $campaign_manager, $is_edit_mode, $campaign_id, $campaign_data ) {
		if ( $is_edit_mode && $campaign_id ) {
			$this->log_debug( 'Updating campaign ' . $campaign_id . ' with data: ' . wp_json_encode( $campaign_data ) );
			$campaign = $campaign_manager->update( $campaign_id, $campaign_data );
		} else {
			$this->log_debug( 'Creating new campaign with data: ' . wp_json_encode( $campaign_data ) );
			$campaign = $campaign_manager->create( $campaign_data );
		}

		// Handle errors.
		if ( is_wp_error( $campaign ) ) {
			$error_message = $campaign->get_error_message();
			$all_messages  = $campaign->get_error_messages();
			if ( count( $all_messages ) > 1 ) {
				$error_message .= ' Details: ' . implode( ', ', $all_messages );
			}
			throw new Exception( esc_html( $error_message ) );
		}

		return $campaign;
	}

	/**
	 * Log campaign save result.
	 *
	 * @since 1.0.0
	 * @param bool   $is_edit_mode     Whether in edit mode.
	 * @param int    $campaign_id      Campaign ID.
	 * @param string $campaign_name    Campaign name.
	 * @param string $campaign_status  Campaign status.
	 */
	private function log_campaign_result( $is_edit_mode, $campaign_id, $campaign_name, $campaign_status ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$action = $is_edit_mode ? 'updated' : 'created';
		error_log( '[Complete Wizard] Campaign ' . $action . ' - ID: ' . $campaign_id . ', Name: ' . $campaign_name . ', Status: ' . $campaign_status );
	}

	/**
	 * Build success response.
	 *
	 * @since  1.0.0
	 * @param  bool   $is_edit_mode     Whether in edit mode.
	 * @param  int    $campaign_id      Campaign ID.
	 * @param  string $campaign_name    Campaign name.
	 * @param  string $campaign_status  Campaign status.
	 * @return array  Success response data.
	 */
	private function build_success_response( $is_edit_mode, $campaign_id, $campaign_name, $campaign_status ) {
		$message_param = $is_edit_mode ? 'campaign_updated' : 'campaign_created';
		$redirect_url  = add_query_arg(
			array(
				'page'    => 'scd-campaigns',
				'message' => $message_param,
			),
			admin_url( 'admin.php' )
		);

		return array(
			'success'       => true,
			'campaign_id'   => $campaign_id,
			'campaign_name' => $campaign_name,
			'status'        => $campaign_status,
			'redirect_url'  => $redirect_url,
			'message'       => $this->build_success_message( $is_edit_mode, $campaign_status ),
		);
	}

	/**
	 * Build success message based on campaign status and mode.
	 *
	 * @since  1.0.0
	 * @param  bool   $is_edit_mode     Whether editing existing campaign.
	 * @param  string $campaign_status  The campaign status.
	 * @return string Success message.
	 */
	private function build_success_message( $is_edit_mode, $campaign_status ) {
		if ( $is_edit_mode ) {
			return $this->build_update_message( $campaign_status );
		}

		return $this->build_create_message( $campaign_status );
	}

	/**
	 * Build success message for campaign updates.
	 *
	 * @since  1.0.0
	 * @param  string $campaign_status  Campaign status.
	 * @return string Update success message.
	 */
	private function build_update_message( $campaign_status ) {
		switch ( $campaign_status ) {
			case self::STATUS_ACTIVE:
				return __( 'Campaign updated and is now active!', 'smart-cycle-discounts' );

			case self::STATUS_SCHEDULED:
				return __( 'Campaign updated and scheduled successfully!', 'smart-cycle-discounts' );

			default:
				return __( 'Campaign updated successfully!', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Build success message for campaign creation.
	 *
	 * @since  1.0.0
	 * @param  string $campaign_status  Campaign status.
	 * @return string Create success message.
	 */
	private function build_create_message( $campaign_status ) {
		switch ( $campaign_status ) {
			case self::STATUS_ACTIVE:
				return __( 'Campaign launched successfully!', 'smart-cycle-discounts' );

			case self::STATUS_SCHEDULED:
				return __( 'Campaign scheduled successfully!', 'smart-cycle-discounts' );

			default:
				return __( 'Campaign saved as draft!', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Handle exception and build error response.
	 *
	 * @since  1.0.0
	 * @param  Exception $e  Exception instance.
	 * @return array  Error response data.
	 */
	private function handle_exception( Exception $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Complete Wizard] Exception: ' . $e->getMessage() );
			error_log( '[Complete Wizard] Trace: ' . $e->getTraceAsString() );
		}

		try {
			$this->state_service->clear_session();
			$this->log_debug( 'Session cleared after failure' );
		} catch ( Exception $clear_exception ) {
			// Log but don't fail - the original error is more important.
			$this->log_debug( 'Failed to clear session: ' . $clear_exception->getMessage() );
		}

		return array(
			'success' => false,
			'message' => $e->getMessage(),
		);
	}

	/**
	 * Log debug message if WP_DEBUG is enabled.
	 *
	 * @since 1.0.0
	 * @param string $message  Debug message.
	 */
	private function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Complete Wizard] ' . $message );
		}
	}
}
