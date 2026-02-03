<?php
/**
 * Save Step Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-save-step-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save Step Handler Class
 *
 * Orchestrates wizard step save operations using:
 * - Idempotency Service (duplicate prevention)
 * - Step Data Transformer (format conversion)
 * - PRO Feature Validator (license enforcement)
 * - Wizard State Service (persistence)
 *
 * @since      1.0.0
 */
class WSSCD_Save_Step_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Wizard_State_Service
	 */
	private $state_service;

	/**
	 * Idempotency service instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Idempotency_Service
	 */
	private $idempotency_service;

	/**
	 * Step data transformer instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Step_Data_Transformer
	 */
	private $transformer;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Feature_Gate|null
	 */
	private $feature_gate;

	/**
	 * Sanitized data from validation.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $sanitized_data = array();

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Wizard_State_Service  $state_service         State service (required).
	 * @param    WSSCD_Logger|null           $logger                Logger instance.
	 * @param    WSSCD_Feature_Gate|null     $feature_gate          Feature gate.
	 * @param    WSSCD_Idempotency_Service   $idempotency_service   Idempotency service (required).
	 * @param    WSSCD_Step_Data_Transformer $transformer           Data transformer (required).
	 */
	public function __construct(
		$state_service,
		$logger = null,
		$feature_gate = null,
		$idempotency_service = null,
		$transformer = null
	) {
		parent::__construct( $logger );

		// Required services.
		if ( ! $state_service ) {
			throw new InvalidArgumentException( 'State service is required' );
		}

		$this->state_service = $state_service;
		$this->feature_gate  = $feature_gate;

		// Idempotency service requires cache manager - get from container or create.
		if ( $idempotency_service ) {
			$this->idempotency_service = $idempotency_service;
		} else {
			// Try to get cache manager from container.
			$cache_manager = null;
			try {
				$container     = Smart_Cycle_Discounts::get_instance();
				$cache_manager = $container::get_service( 'cache_manager' );
			} catch ( Exception $e ) {
				// Fall back to creating new cache manager.
			}
			if ( ! $cache_manager ) {
				// Ensure WSSCD_Cache_Manager class is loaded.
				if ( ! class_exists( 'WSSCD_Cache_Manager' ) ) {
					$cache_path = WSSCD_PLUGIN_DIR . 'includes/cache/class-cache-manager.php';
					if ( file_exists( $cache_path ) ) {
						require_once $cache_path;
					}
				}
				if ( class_exists( 'WSSCD_Cache_Manager' ) ) {
					$cache_manager = new WSSCD_Cache_Manager();
				}
			}
			// Only create idempotency service if we have a valid cache manager.
			if ( $cache_manager instanceof WSSCD_Cache_Manager ) {
				$this->idempotency_service = new WSSCD_Idempotency_Service( $cache_manager, $state_service );
			} else {
				$this->idempotency_service = null;
			}
		}
		$this->transformer = $transformer ?: new WSSCD_Step_Data_Transformer();
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_save_step';
	}

	/**
	 * Handle the save step request.
	 *
	 * Clean orchestration - delegates everything to services.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array|WP_Error       Response data or error.
	 */
	protected function handle( $request ) {
		$this->set_execution_limits();

		$step = $this->extract_step( $request );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		if ( ! WSSCD_Wizard_Step_Registry::is_valid_step( $step ) ) {
			return new WP_Error(
				'invalid_step',
				sprintf(
					/* translators: %s: step name */
					__( 'Invalid step: %s', 'smart-cycle-discounts' ),
					$step
				),
				array( 'status' => 400 )
			);
		}

		$data = $this->extract_data( $request );

		// Handle idempotency (only if service is available)
		$user_id         = get_current_user_id();
		$idempotency_key = null;

		if ( $this->idempotency_service ) {
			$idempotency_key = $this->idempotency_service->generate_key( $step, $data, $user_id );

			// Check for cached response (validated by idempotency service).
			$cached = $this->idempotency_service->get_cached_response( $idempotency_key );
			if ( $cached ) {
				return $cached;
			}

			// Claim request atomically
			$claim_result = $this->idempotency_service->claim_request( $idempotency_key );
			if ( is_wp_error( $claim_result ) ) {
				return $claim_result;
			}
			if ( is_array( $claim_result ) ) {
				return $claim_result; // Another request completed
			}
		}

		// Early return for empty data (except review step)
		if ( empty( $data ) && 'review' !== $step ) {
			return array(
				'message'   => __( 'No data to save', 'smart-cycle-discounts' ),
				'step'      => $step,
				'next_step' => WSSCD_Wizard_Step_Registry::get_next_step( $step ),
			);
		}

		$size_check = $this->validate_request_size( $data );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		$validation_result = $this->validate_step_data( $step, $data );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		try {
			$processed_data = $this->process_step_data( $step, $data );
			if ( is_wp_error( $processed_data ) ) {
				return $processed_data;
			}

			$save_result = $this->save_to_state( $step, $processed_data, $request );
			if ( is_wp_error( $save_result ) ) {
				return $save_result;
			}
		} catch ( Exception $e ) {
			return $this->handle_save_exception( $e, $step );
		}

		$response = $this->build_response( $step, $processed_data, $request );

		if ( $this->idempotency_service && $idempotency_key ) {
			$this->idempotency_service->cache_response( $idempotency_key, $response );
		}

		return $response;
	}

	/**
	 * Set execution limits.
	 *
	 * Note: Removed set_time_limit() call per WordPress.org guidelines.
	 * PHP limits should not be modified by plugins. If timeouts occur,
	 * server-level configuration should be adjusted instead.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function set_execution_limits() {
		// Intentionally empty - PHP limits should not be modified by plugins.
		// Server configuration should handle timeout settings.
	}

	/**
	 * Extract step from request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   string|WP_Error      Step name or error.
	 */
	private function extract_step( $request ) {
		$step = isset( $request['step'] ) ? sanitize_key( $request['step'] ) : '';

		if ( empty( $step ) ) {
			return new WP_Error(
				'bad_request',
				__( 'Step is required', 'smart-cycle-discounts' ),
				array( 'status' => 400 )
			);
		}

		return $step;
	}

	/**
	 * Extract data from request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Step data.
	 */
	private function extract_data( $request ) {
		$data = isset( $request['data'] ) ? $request['data'] : (
			isset( $request['step_data'] ) ? $request['step_data'] : array()
		);

		return $data;
	}

	/**
	 * Validate request size.
	 *
	 * @since    1.0.0
	 * @param    array $data    Request data.
	 * @return   true|WP_Error     True if valid, error otherwise.
	 */
	private function validate_request_size( $data ) {
		$request_size = strlen( serialize( $data ) );
		$max_size     = 102400; // 100KB

		if ( $request_size > $max_size ) {
			return new WP_Error(
				'payload_too_large',
				sprintf(
					/* translators: 1: current size, 2: max size */
					__( 'Request data too large (%1$s). Maximum: %2$s', 'smart-cycle-discounts' ),
					size_format( $request_size ),
					size_format( $max_size )
				),
				array( 'status' => 413 )
			);
		}

		return true;
	}

	/**
	 * Validate step data.
	 *
	 * Single validation path - no auto-save bypass for security.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   true|WP_Error      True if valid, error otherwise.
	 */
	private function validate_step_data( $step, &$data ) {
		if ( 'review' === $step ) {
			return true;
		}

		// Strip PRO features for free users before validation.
		$this->strip_pro_features_for_free_users( $step, $data );

		// Validate PRO features first
		$pro_validation = $this->validate_pro_features( $step, $data );
		if ( is_wp_error( $pro_validation ) ) {
			return $pro_validation;
		}

		// Always use strict validation
		return $this->validate_full_save( $step, $data );
	}

	/**
	 * Strip PRO features from step data for free users.
	 *
	 * Removes PRO-only fields before validation to prevent false rejections.
	 * This is a server-side safeguard that allows free users to save without
	 * errors when the form sends fields that should have been hidden.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data (passed by reference).
	 * @return   void
	 */
	private function strip_pro_features_for_free_users( $step, &$data ) {
		if ( ! $this->feature_gate ) {
			return;
		}

		// Strip discount configurations for free users on discounts step.
		if ( 'discounts' === $step && ! $this->feature_gate->can_use_discount_configurations() ) {
			$pro_config_fields = array(
				'usage_limit_per_customer',
				'total_usage_limit',
				'lifetime_usage_cap',
				'max_discount_amount',
				'minimum_quantity',
				'minimum_order_amount',
			);

			foreach ( $pro_config_fields as $field ) {
				unset( $data[ $field ] );
			}

			// Reset boolean configs to free-tier defaults.
			$data['stack_with_others']   = false; // Free users cannot stack.
			$data['allow_coupons']       = true;  // Free users must allow coupons.
			$data['apply_to_sale_items'] = true;  // Free users must apply to sale items.
		}

		// Note: Recurring campaigns are FREE - no stripping needed for schedule step.

		// Strip advanced filters for free users on products step.
		if ( 'products' === $step && ! $this->feature_gate->can_use_advanced_product_filters() ) {
			// Clear conditions array to prevent PRO filter validation.
			$data['conditions']       = array();
			$data['conditions_logic'] = 'all'; // Reset to default.
		}
	}

	/**
	 * Validate PRO features.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   true|WP_Error      True if valid, error otherwise.
	 */
	private function validate_pro_features( $step, $data ) {
		if ( ! $this->feature_gate ) {
			return true; // Fail open if no feature gate
		}

		$validator_path = WSSCD_PLUGIN_DIR . 'includes/core/validation/class-pro-feature-validator.php';
		if ( ! file_exists( $validator_path ) ) {
			return true; // Fail open
		}

		require_once $validator_path;

		if ( ! class_exists( 'WSSCD_PRO_Feature_Validator' ) ) {
			return true; // Fail open
		}

		$validator = new WSSCD_PRO_Feature_Validator( $this->feature_gate );
		return $validator->validate_step( $step, $data );
	}

	/**
	 * Validate full save (strict).
	 *
	 * Always enforces validation - removed auto-save bypass for security.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   true|WP_Error      True if valid, error otherwise.
	 */
	private function validate_full_save( $step, $data ) {
		switch ( $step ) {
			case 'basic':
			case 'products':
			case 'discounts':
			case 'schedule':
				$this->sanitized_data = WSSCD_Validation::sanitize_step_data( $data, $step );
				return WSSCD_Validation::validate( $this->sanitized_data, 'wizard_' . $step );

			case 'review':
				if ( ! $this->state_service ) {
					return new WP_Error(
						'state_service_unavailable',
						__( 'Cannot validate campaign', 'smart-cycle-discounts' )
					);
				}

				$all_data = $this->state_service->get_all_data();
				return WSSCD_Validation::validate( $all_data, 'campaign_complete' );

			default:
				return new WP_Error( 'unknown_step', __( 'Unknown step', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Process step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Raw data.
	 * @return   array|WP_Error     Processed data or error.
	 */
	private function process_step_data( $step, $data ) {
		$data_to_process = ! empty( $this->sanitized_data ) ? $this->sanitized_data : $data;

		switch ( $step ) {
			case 'basic':
			case 'products':
			case 'discounts':
			case 'schedule':
				$sanitized = WSSCD_Validation::sanitize_step_data( $data_to_process, $step );

				// Strip PRO features for free users (server-side safeguard)
				if ( 'schedule' === $step && $this->feature_gate && ! $this->feature_gate->can_use_recurring_campaigns() ) {
					$recurring_fields = array(
						'enable_recurring',
						'recurrence_mode',
						'recurrence_pattern',
						'recurrence_interval',
						'recurrence_days',
						'recurrence_end_type',
						'recurrence_count',
						'recurrence_end_date',
					);
					foreach ( $recurring_fields as $field ) {
						unset( $sanitized[ $field ] );
					}
				}
				return $sanitized;

			case 'review':
				if ( isset( $data['launch_option'] ) ) {
					return array(
						'launch_option' => sanitize_key( $data['launch_option'] ),
						'status'        => ( 'active' === $data['launch_option'] ) ? 'active' : 'draft',
					);
				}
				return array();

			default:
				return $data_to_process;
		}
	}

	/**
	 * Save data to state service.
	 *
	 * @since    1.0.0
	 * @param    string $step             Step name.
	 * @param    array  $processed_data   Processed data.
	 * @param    array  $request          Request data.
	 * @return   true|WP_Error               True if successful, error otherwise.
	 */
	private function save_to_state( $step, $processed_data, $request ) {
		try {
			global $wpdb;
			if ( ! $wpdb->check_connection() ) {
				throw new Exception( 'Database connection lost' );
			}

			if ( ! $this->state_service ) {
				throw new Exception( 'State service unavailable' );
			}

			$save_result = $this->state_service->save_step_data( $step, $processed_data );

			if ( false === $save_result ) {
				throw new Exception( 'Failed to save step data' );
			}

			// Mark step complete (all saves now mark complete since we removed auto-save)
			$this->state_service->mark_step_complete( $step );

			return true;

		} catch ( Exception $e ) {
			return $this->handle_save_exception( $e, $step );
		}
	}

	/**
	 * Build response.
	 *
	 * @since    1.0.0
	 * @param    string $step             Step name.
	 * @param    array  $processed_data   Processed data.
	 * @param    array  $request          Request data.
	 * @return   array                       Response array.
	 */
	private function build_response( $step, $processed_data, $request ) {
		$next_step = WSSCD_Wizard_Step_Registry::get_next_step( $step );
		$progress  = $this->state_service ? $this->state_service->get_progress() : array(
			'completed_steps' => array(),
			'total_steps'     => WSSCD_Wizard_Step_Registry::get_step_count(),
			'percentage'      => 0,
		);

		return array(
			'message'   => __( 'Step data saved successfully', 'smart-cycle-discounts' ),
			'step'      => $step,
			'next_step' => $next_step,
			'progress'  => $progress,
			'stepData'  => $processed_data,
		);
	}

	/**
	 * Handle save exception.
	 *
	 * @since    1.0.0
	 * @param    Exception $e       Exception.
	 * @param    string    $step    Step name.
	 * @return   WP_Error             Error response.
	 */
	private function handle_save_exception( $e, $step ) {
		// Session lock failures
		if ( false !== strpos( $e->getMessage(), 'session lock' ) ) {
			return new WP_Error(
				'session_lock',
				__( 'Another save operation in progress. Please try again.', 'smart-cycle-discounts' ),
				array( 'status' => 409 )
			);
		}

		return new WP_Error(
			'save_step_error',
			$e->getMessage(),
			array(
				'step'   => $step,
				'status' => 500,
			)
		);
	}
}
