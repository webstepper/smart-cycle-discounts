<?php
/**
 * Import Calculator Preset Handler
 *
 * Handles AJAX requests to import discount presets from the external
 * Profit Calculator tool into the campaign wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import Calculator Preset Handler Class
 *
 * @since      1.0.0
 */
class WSSCD_Import_Calculator_Preset_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Get the AJAX action name for this handler.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_import_calculator_preset';
	}

	/**
	 * Handle the calculator preset import request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array|WP_Error    Response data or error.
	 */
	protected function handle( $request ) {
		// Validate required field.
		if ( empty( $request['code'] ) ) {
			return new WP_Error(
				'missing_code',
				__( 'Preset code is required.', 'smart-cycle-discounts' ),
				array( 'status' => 400 )
			);
		}

		$code = sanitize_text_field( wp_unslash( $request['code'] ) );

		// Validate code format before processing.
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-calculator-preset-decoder.php';

		if ( ! WSSCD_Calculator_Preset_Decoder::is_valid( $code ) ) {
			return new WP_Error(
				'invalid_code',
				__( 'Invalid preset code format. Please copy the code from the calculator again.', 'smart-cycle-discounts' ),
				array( 'status' => 400 )
			);
		}

		// Get or create wizard session.
		// Load dependencies required by Wizard State Service.
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-step-registry.php';
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';

		$state_service = new WSSCD_Wizard_State_Service();

		// Start fresh session for calculator import.
		$session_id = $state_service->create();

		// Pre-fill from calculator code.
		$result = $state_service->prefill_from_calculator( $code );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Mark session as fresh so JS clears sessionStorage.
		// This is done via the state service's mark_as_fresh method called during create.

		// Build redirect URL to wizard review step.
		$redirect_url = add_query_arg(
			array(
				'page'   => 'wsscd-campaigns',
				'action' => 'wizard',
			),
			admin_url( 'admin.php' )
		);

		// Log the import.
		if ( $this->logger ) {
			$this->logger->info(
				'Calculator preset imported',
				array(
					'code'       => $code,
					'session_id' => $session_id,
				)
			);
		}

		return array(
			'success'     => true,
			'message'     => __( 'Preset imported successfully!', 'smart-cycle-discounts' ),
			'sessionId'   => $session_id,
			'redirectUrl' => $redirect_url,
		);
	}
}
