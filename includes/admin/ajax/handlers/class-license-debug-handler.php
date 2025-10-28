<?php
/**
 * License Debug Handler
 *
 * Provides debugging information for Freemius license status and feature gate.
 * Helps troubleshoot PRO feature access issues.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Debug Handler Class
 *
 * @since 1.0.0
 */
class SCD_License_Debug_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Logger instance.
	 *
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param object     $container Container instance.
	 * @param SCD_Logger $logger    Logger instance.
	 */
	public function __construct( $container, $logger ) {
		parent::__construct( $logger );
		$this->container = $container;
		$this->logger    = $logger;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		return 'scd_license_debug';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		// Only admins can access debug information
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( __( 'Insufficient permissions', 'smart-cycle-discounts' ) );
		}

		$debug_info = array();

		// 1. Check if Freemius is loaded
		$debug_info['freemius_loaded'] = function_exists( 'scd_fs' ) && is_object( scd_fs() );

		if ( $debug_info['freemius_loaded'] ) {
			$freemius = scd_fs();

			// 2. Get Freemius status
			$debug_info['freemius_status'] = array(
				'is_registered' => $freemius->is_registered(),
				'is_anonymous'  => $freemius->is_anonymous(),
				'is_premium'    => $freemius->is_premium(),
				'is_trial'      => $freemius->is_trial(),
				'is_free_plan'  => $freemius->is_free_plan(),
				'is_paying'     => $freemius->is_paying(),
			);

			// 3. Get user info
			if ( $freemius->is_registered() ) {
				$user = $freemius->get_user();
				if ( $user ) {
					$debug_info['freemius_user'] = array(
						'id'         => $user->id,
						'email'      => $user->email,
						'first_name' => $user->first_name,
						'last_name'  => $user->last_name,
					);
				}
			}

			// 4. Get site info
			if ( $freemius->is_registered() ) {
				$site = $freemius->get_site();
				if ( $site ) {
					$debug_info['freemius_site'] = array(
						'id'         => $site->id,
						'public_key' => $site->public_key,
						'plan_id'    => isset( $site->plan_id ) ? $site->plan_id : null,
						'trial_ends' => isset( $site->trial_ends ) ? $site->trial_ends : null,
					);
				}
			}

			// 5. Get license info
			if ( $freemius->is_registered() ) {
				$license = $freemius->_get_license();
				if ( $license ) {
					$debug_info['freemius_license'] = array(
						'id'         => isset( $license->id ) ? $license->id : null,
						'plan_id'    => isset( $license->plan_id ) ? $license->plan_id : null,
						'is_active'  => isset( $license->is_active ) ? $license->is_active : null,
						'is_expired' => isset( $license->is_expired ) ? $license->is_expired : null,
						'expiration' => isset( $license->expiration ) ? $license->expiration : null,
					);
				}
			}
		} else {
			$debug_info['freemius_error'] = 'Freemius SDK not loaded or scd_fs() function not available';
		}

		// 6. Get Feature Gate status
		try {
			$feature_gate = $this->container::get_service( 'feature_gate' );
			if ( $feature_gate ) {
				$debug_info['feature_gate'] = array(
					'is_premium'               => $feature_gate->is_premium(),
					'is_trial'                 => $feature_gate->is_trial(),
					'campaign_limit'           => $feature_gate->get_campaign_limit(),
					'can_export_data'          => $feature_gate->can_export_data(),
					'can_access_analytics'     => $feature_gate->can_access_analytics(),
					'available_discount_types' => $feature_gate->get_available_discount_types(),
				);

				// Get sample PRO features status
				$pro_features                      = $feature_gate->get_pro_features();
				$debug_info['pro_features_sample'] = array();
				foreach ( array_slice( $pro_features, 0, 5 ) as $feature ) {
					$debug_info['pro_features_sample'][ $feature ] = $feature_gate->can_use_feature( $feature );
				}
			} else {
				$debug_info['feature_gate_error'] = 'Feature Gate service not available from container';
			}
		} catch ( Exception $e ) {
			$debug_info['feature_gate_error'] = $e->getMessage();
		}

		// 7. WordPress transient/option cache check
		$debug_info['wordpress_cache'] = array(
			'scd_settings' => get_option( 'scd_settings' ) !== false,
		);

		// 8. Container availability check
		$debug_info['container_info'] = array(
			'container_class'  => get_class( $this->container ),
			'has_feature_gate' => method_exists( $this->container, 'has' ) && $this->container::has( 'feature_gate' ),
		);

		// Log debug request
		$this->logger->flow(
			'info',
			'LICENSE DEBUG',
			'License status requested',
			array(
				'user_id'         => get_current_user_id(),
				'freemius_loaded' => $debug_info['freemius_loaded'],
			)
		);

		return $this->success(
			array(
				'debug_info'   => $debug_info,
				'timestamp'    => current_time( 'mysql' ),
				'instructions' => array(
					'If is_premium is false but you just upgraded:',
					'1. Try clearing the cache using the "Clear Cache" action',
					'2. Check if Freemius webhook received the upgrade event',
					'3. Verify license is active in freemius_license section',
					'4. Contact support if issue persists',
				),
			)
		);
	}
}
