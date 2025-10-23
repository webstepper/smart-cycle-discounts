<?php
/**
 * Save Step Handler
 *
 * Handles AJAX requests for saving wizard step data.
 * This handler focuses on request coordination, delegating
 * validation and sanitization to the centralized SCD_Validation class.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Save Step Handler Class
 *
 * Handles saving wizard step data.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Save_Step_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Audit logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Audit_Logger    $audit_logger    Audit logger.
	 */
	private $audit_logger = null;

	/**
	 * Sanitized data from validation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $sanitized_data    Sanitized data.
	 */
	private $sanitized_data = array();

	/**
	 * Condition validation errors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $condition_errors    Condition errors and warnings.
	 */
	private $condition_errors = array();

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate|null    $feature_gate    Feature gate.
	 */
	private $feature_gate = null;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_State_Service    $state_service    State service.
	 * @param    SCD_Logger                  $logger           Logger instance.
	 * @param    SCD_Feature_Gate|null       $feature_gate     Feature gate instance.
	 */
	public function __construct( $state_service, $logger = null, $feature_gate = null ) {
		parent::__construct( $logger );
		$this->state_service = $state_service;
		$this->feature_gate = $feature_gate;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_save_step';
	}

	/**
	 * Handle the save step request.
	 *
	 * @since    1.0.0
	 * @param    array    $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		// Validate and sanitize input
		if ( ! is_array( $request ) ) {
			$request = array();
		}
		
		$this->_set_execution_limits();
		
		$step = $this->_sanitize_step_parameter( $request );
		if ( is_wp_error( $step ) ) {
			return $step;
		}
		
		$data = $this->_extract_step_data( $request );

		// Handle idempotency to prevent duplicate processing
		$idempotency_key = $this->_handle_idempotency( $step, $data );
		
		// Check for duplicate request
		$cached_response = $this->_get_idempotent_response( $idempotency_key );
		if ( $cached_response ) {
			return $cached_response;
		}
		
		// CRITICAL: Claim request atomically to prevent concurrent execution
		$claim_result = $this->_claim_idempotent_request( $idempotency_key );
		if ( is_wp_error( $claim_result ) ) {
			return $claim_result;
		}
		if ( is_array( $claim_result ) ) {
			// Another request completed while we waited
			return $claim_result;
		}
        
		// Early return optimization for empty data
		$early_return = $this->_check_early_return( $data, $step, $request );
		if ( $early_return ) {
			return $early_return;
		}
		
		// Validate request size
		$size_check = $this->_validate_request_size( $data );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}
        
		// Transform data for specific steps
		$data = $this->_transform_step_data( $step, $data );
		
		// Validate step data
		$validation_result = $this->_validate_step_data( $step, $data, $request );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}
        
		try {
			// Process and save step data
			$processed_data = $this->_process_step_data( $step, $data );
			if ( is_wp_error( $processed_data ) ) {
				return $processed_data;
			}
			
			$save_result = $this->_save_to_state( $step, $processed_data, $request );
			if ( is_wp_error( $save_result ) ) {
				return $save_result;
			}
			
		} catch ( Exception $e ) {
			return $this->_handle_save_exception( $e, $step );
		}
        
		// Build and return response
		$response = $this->_build_response( $step, $processed_data, $data, $request );
		
		// Log audit and cache response
		$this->_finalize_save( $step, $processed_data, $request, $idempotency_key, $response );
		
		return $response;
	}

	/**
	 * Set execution limits for save operations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function _set_execution_limits() {
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 30 );
		}
	}

	/**
	 * Sanitize step parameter from request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $request    Request data.
	 * @return   string|WP_Error     Sanitized step or error.
	 */
	private function _sanitize_step_parameter( $request ) {
		$step = isset( $request['step'] ) ? sanitize_key( $request['step'] ) : '';
		
		if ( empty( $step ) ) {
			return new WP_Error(
				'bad_request',
				__( 'Step is required', 'smart-cycle-discounts' ),
				array( 'field' => 'step', 'request_keys' => array_keys( $request ), 'status' => 400 )
			);
		}
		
		return $step;
	}

	/**
	 * Extract step data from request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $request    Request data.
	 * @return   array               Step data.
	 */
	private function _extract_step_data( $request ) {
		return isset( $request['data'] ) ? $request['data'] : ( isset( $request['step_data'] ) ? $request['step_data'] : array() );
	}

	/**
	 * Handle idempotency key generation and processing.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
	 * @return   string             Idempotency key.
	 */
	private function _handle_idempotency( $step, $data ) {
		$idempotency_key = isset( $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ) ? sanitize_text_field( $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ) : null;

		// HIGH: Validate provided key format
		if ( $idempotency_key ) {
			// Must be 32-64 character alphanumeric string with underscores/hyphens
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{32,64}$/', $idempotency_key ) ) {
				$idempotency_key = null;
			}
		}

		// Generate idempotency key if not provided (for backward compatibility)
		if ( ! $idempotency_key ) {
			$user_id = get_current_user_id();

			// HIGH: Validate session with state service (don't trust cookie alone)
			$state_service = $this->state_service;
			if ( ! $state_service || ! $state_service->get_session_id() ) {
				// No valid session - generate random key
				$session_id = wp_generate_password( 32, false );
			} else {
				$session_id = $state_service->get_session_id();
			}

			// HIGH: Use HMAC with secret for cryptographic security
			$secret = wp_salt( 'nonce' );
			$data_json = wp_json_encode( $data ); // More reliable than serialize()
			$data_hash = hash_hmac( 'sha256', $data_json, $secret );

			// HIGH: Include timestamp to prevent indefinite caching
			$timestamp = floor( time() / 60 ); // 1-minute buckets

			$idempotency_key = sprintf(
				'scd_save_%s_%s_%d_%s_%d',
				$step,
				substr( $data_hash, 0, 16 ),
				$user_id,
				substr( $session_id, 0, 8 ),
				$timestamp
			);
		}

		return $idempotency_key;
	}

	/**
	 * Check for early return conditions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array     $data       Step data.
	 * @param    string    $step       Step name.
	 * @param    array     $request    Request data.
	 * @return   array|null           Early return response or null.
	 */
	private function _check_early_return( $data, $step, $request ) {
		$is_navigation_save = isset( $request['is_navigation_save'] ) || isset( $request['save_and_continue'] );
		$is_auto_save = isset( $request['is_auto_save'] ) && $request['is_auto_save'];

		// Don't save empty data for any step except review
		// Empty auto-saves should also be skipped to prevent overwriting existing data
		if ( empty( $data ) && 'review' !== $step ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SCD Save Handler] Skipping save for step "%s" - empty data (auto_save: %s, navigation: %s)',
					$step,
					$is_auto_save ? 'yes' : 'no',
					$is_navigation_save ? 'yes' : 'no'
				) );
			}

			return array(
				'message' => __( 'No data to save', 'smart-cycle-discounts' ),
				'step' => $step,
				'next_step' => $this->_get_next_step( $step )
			);
		}

		return null;
	}

	/**
	 * Validate request size to prevent DOS attacks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data    Request data.
	 * @return   true|WP_Error     True if valid, error otherwise.
	 */
	private function _validate_request_size( $data ) {
		$request_size = strlen( serialize( $data ) );
		$max_request_size = 102400; // 100KB
		
		if ( $request_size > $max_request_size ) {
			return new WP_Error(
				'payload_too_large',
				sprintf(
					__( 'Request data too large (%s). Maximum allowed: %s', 'smart-cycle-discounts' ),
					size_format( $request_size ),
					size_format( $max_request_size )
				),
				array(
					'request_size' => $request_size,
					'max_size' => $max_request_size,
					'status' => 413
				)
			);
		}
		
		return true;
	}

	/**
	 * Transform step data based on step type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Raw data.
	 * @return   array             Transformed data.
	 */
	private function _transform_step_data( $step, $data ) {
		if ( 'products' === $step ) {
			return $this->_transform_products_data( $data );
		}
		
		return $data;
	}

	/**
	 * Transform products data for compatibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data    Raw data.
	 * @return   array             Transformed data.
	 */
	private function _transform_products_data( $data ) {
		// Handle product selection data format
		if ( isset( $data['product_ids'] ) && is_string( $data['product_ids'] ) ) {
			$product_ids = explode( ',', $data['product_ids'] );
			$data['product_ids'] = array_values( array_filter( $product_ids, function( $id ) {
				return '' !== $id && null !== $id && false !== $id;
			} ) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'SCD: Transformed product_ids from string to array - Count: %d, IDs: %s',
					count( $data['product_ids'] ),
					implode( ', ', $data['product_ids'] )
				) );
			}
		} elseif ( isset( $data['product_ids'] ) && is_array( $data['product_ids'] ) ) {
			$data['product_ids'] = array_values( $data['product_ids'] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'SCD: Reindexed existing product_ids array - Count: %d, IDs: %s',
					count( $data['product_ids'] ),
					implode( ', ', $data['product_ids'] )
				) );
			}
		}

		// Transform conditions from UI format to engine format
		if ( isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			$data['conditions'] = $this->_transform_conditions_for_engine( $data['conditions'] );
		}

		return $data;
	}

	/**
	 * Transform conditions from UI format to engine format.
	 *
	 * UI format: {type, operator, value, value2, mode}
	 * Engine format: {property, operator, values[], mode}
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $ui_conditions    Conditions from UI.
	 * @return   array                      Conditions for engine.
	 */
	private function _transform_conditions_for_engine( $ui_conditions ) {
		$engine_conditions = array();
		$this->condition_errors = array(
			'invalid' => array(),
			'warnings' => array()
		);

		foreach ( $ui_conditions as $index => $condition ) {
			// Skip if not an array
			if ( ! is_array( $condition ) ) {
				$this->condition_errors['invalid'][] = sprintf(
					__( 'Condition #%d is not valid (expected array)', 'smart-cycle-discounts' ),
					$index + 1
				);
				continue;
			}

			// Skip if already in engine format (has 'property' field)
			if ( isset( $condition['property'] ) ) {
				$engine_conditions[] = $condition;
				continue;
			}

			// Skip if missing required UI fields
			if ( ! isset( $condition['type'], $condition['operator'] ) ) {
				$this->condition_errors['invalid'][] = sprintf(
					__( 'Condition #%d is missing required fields (type and operator)', 'smart-cycle-discounts' ),
					$index + 1
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'SCD: Skipping invalid condition - missing type or operator' );
				}
				continue;
			}

			// Build values array from value and value2
			$values = array();
			if ( isset( $condition['value'] ) && '' !== $condition['value'] ) {
				$values[] = $condition['value'];
			}
			if ( isset( $condition['value2'] ) && '' !== $condition['value2'] ) {
				$values[] = $condition['value2'];
			}

			// Warn if no values provided
			if ( empty( $values ) ) {
				$this->condition_errors['warnings'][] = sprintf(
					__( 'Condition #%d has no values specified', 'smart-cycle-discounts' ),
					$index + 1
				);
			}

			// Transform to engine format
			$engine_condition = array(
				'property' => $condition['type'],
				'operator' => $condition['operator'],
				'values'   => $values,
				'mode'     => isset( $condition['mode'] ) ? $condition['mode'] : 'include',
			);

			$engine_conditions[] = $engine_condition;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'SCD: Transformed condition - Property: %s, Operator: %s, Values: %s',
					$engine_condition['property'],
					$engine_condition['operator'],
					implode( ', ', $engine_condition['values'] )
				) );
			}
		}

		return $engine_conditions;
	}

	/**
	 * Process step data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Raw data.
	 * @return   array|WP_Error     Processed data or error.
	 */
	private function _process_step_data( $step, $data ) {
		$data_to_process = ! empty( $this->sanitized_data ) ? $this->sanitized_data : $data;
		
		return $this->_sanitize_step_data( $step, $data_to_process );
	}

	/**
	 * Sanitize step data based on step type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Raw data.
	 * @return   array|WP_Error     Processed data or error.
	 */
	private function _sanitize_step_data( $step, $data ) {
		// Validate step is known
		$valid_steps = $this->_get_valid_steps();
		if ( ! in_array( $step, $valid_steps, true ) ) {
			return new WP_Error(
				'unknown_step',
				sprintf( __( 'Unknown step: %s', 'smart-cycle-discounts' ), $step ),
				array( 'status' => 400 )
			);
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[SCD Save Handler] %s - Raw data received: %s', 
				ucfirst( $step ), 
				print_r( $data, true ) 
			) );
		}
		
		// Sanitize based on step type
		switch ( $step ) {
			case 'basic':
			case 'products':
			case 'discounts':
			case 'schedule':
				$sanitized = SCD_Validation::sanitize_step_data( $data, $step );

				// Server-side safeguard: Strip recurring fields for free users
				// This ensures free users cannot save recurring data even if they bypass client restrictions
				if ( 'schedule' === $step && $this->feature_gate && ! $this->feature_gate->can_use_recurring_campaigns() ) {
					$recurring_fields = array( 'enable_recurring', 'recurrence_pattern', 'recurrence_interval', 'recurrence_days', 'recurrence_end_type', 'recurrence_count', 'recurrence_end_date' );
					foreach ( $recurring_fields as $field ) {
						unset( $sanitized[ $field ] );
					}
				}
				break;

			case 'review':
				$sanitized = $this->_sanitize_review_data( $data );
				break;

			default:
				$sanitized = $data;
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[SCD Save Handler] %s - Sanitized data: %s',
				ucfirst( $step ),
				print_r( $sanitized, true )
			) );
		}
		
		return $sanitized;
	}

	/**
	 * Sanitize review step data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data    Raw data.
	 * @return   array             Sanitized data.
	 */
	private function _sanitize_review_data( $data ) {
		$sanitized = array();
		if ( isset( $data['launch_option'] ) ) {
			$sanitized['launch_option'] = sanitize_key( $data['launch_option'] );
			$sanitized['status'] = ( 'active' === $sanitized['launch_option'] ) ? 'active' : 'draft';
		}
		return $sanitized;
	}

	/**
	 * Get valid wizard steps.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Valid step names.
	 */
	private function _get_valid_steps() {
		return array( 'basic', 'products', 'discounts', 'schedule', 'review' );
	}
	
	/**
	 * Get next step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $current    Current step.
	 * @return   string                Next step.
	 */
	private function _get_next_step( $current ) {
		$steps = $this->_get_valid_steps();
		$index = array_search( $current, $steps, true );
		
		if ( false === $index || $index >= count( $steps ) - 1 ) {
			return 'complete';
		}
		
		return $steps[ $index + 1 ];
	}

	/**
	 * Validate step data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
	 * @param    array     $request Request data.
	 * @return   true|WP_Error      True if valid, WP_Error otherwise.
	 */
	private function _validate_step_data( $step, $data, $request = array() ) {
		if ( 'review' === $step ) {
			return true;
		}

		// CRITICAL: Validate PRO features FIRST (security layer)
		// This prevents free users from bypassing UI restrictions
		$pro_validation = $this->_validate_pro_features( $step, $data );
		if ( is_wp_error( $pro_validation ) ) {
			return $pro_validation;
		}

		$this->_load_domain_classes( $step );

		$is_auto_save = ! empty( $request['is_auto_save'] );
		$is_navigation_save = ! empty( $request['is_navigation_save'] ) || ! empty( $request['save_and_continue'] );

		// Auto-save: Allow save but store warnings (lenient validation)
		if ( $is_auto_save ) {
			return $this->_validate_auto_save( $step, $data );
		}

		// Navigation save and full save: Enforce strict validation
		// User MUST pass validation to proceed to next step
		return $this->_validate_full_save( $step, $data );
	}

	/**
	 * Load domain classes if needed.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @return   void
	 */
	private function _load_domain_classes( $step ) {
		$classes_to_load = array(
			'products' => 'includes/core/products/class-product-selection.php',
			'discounts' => 'includes/core/discounts/class-discount.php',
			'schedule' => 'includes/core/scheduling/class-schedule.php',
		);
		
		if ( isset( $classes_to_load[ $step ] ) ) {
			$class_path = SCD_PLUGIN_DIR . $classes_to_load[ $step ];
			if ( file_exists( $class_path ) && ! class_exists( basename( $class_path, '.php' ) ) ) {
				require_once $class_path;
			}
		}
	}

	/**
	 * Validate auto save (always validate, but convert errors to warnings).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
	 * @return   true|WP_Error      True if valid, WP_Error otherwise.
	 */
	private function _validate_auto_save( $step, $data ) {
		// Always run full validation
		$validation_result = $this->_validate_full_save( $step, $data );

		if ( is_wp_error( $validation_result ) ) {
			// For auto-save, log validation warnings but still allow save
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Wizard:SaveHandler] Auto-save validation warnings for ' . $step . ': ' .
					implode( ', ', $validation_result->get_error_messages() ) );
			}

			// Store warnings in state for user notification (non-blocking)
			if ( $this->state_service ) {
				$all_data = $this->state_service->get_all_data();
				$current_warnings = isset( $all_data['validation_warnings'] ) ? $all_data['validation_warnings'] : array();
				if ( ! is_array( $current_warnings ) ) {
					$current_warnings = array();
				}
				$current_warnings[$step] = $validation_result->get_error_messages();
				$this->state_service->save_step_data( 'validation_warnings', $current_warnings );
			}

			// Return true to allow auto-save despite warnings
			return true;
		}

		return true;
	}

	/**
	 * Validate products step for auto save.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data    Step data.
	 * @return   true|WP_Error      True if valid, WP_Error otherwise.
	 */
	private function _validate_products_auto_save( $data ) {
		$this->sanitized_data = SCD_Validation::sanitize_step_data( $data, 'products' );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '=== SCD Products Auto Save Debug ===' );
			error_log( 'Sanitized data: ' . print_r( $this->sanitized_data, true ) );
		}
		
		// Critical validation: Check specific products requires selection
		if ( isset( $this->sanitized_data['product_selection_type'] ) && 
		     'specific_products' === $this->sanitized_data['product_selection_type'] &&
		     ( empty( $this->sanitized_data['product_ids'] ) || 
		       ( is_array( $this->sanitized_data['product_ids'] ) && 0 === count( $this->sanitized_data['product_ids'] ) ) ) ) {
			
			return new WP_Error(
				'products_required',
				__( 'Please select at least one product when using specific products selection.', 'smart-cycle-discounts' ),
				array( 'field' => 'product_ids', 'critical' => true )
			);
		}
		
		return true;
	}

	/**
	 * Validate full save (complete validation).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
	 * @return   true|WP_Error      True if valid, WP_Error otherwise.
	 */
	private function _validate_full_save( $step, $data ) {
		switch ( $step ) {
			case 'basic':
			case 'products':
			case 'discounts':
			case 'schedule':

				$this->sanitized_data = SCD_Validation::sanitize_step_data( $data, $step );
				$result = SCD_Validation::validate( $this->sanitized_data, 'wizard_' . $step );
				break;
				
			case 'review':
				$this->sanitized_data = $this->_sanitize_review_data( $data );

				// Validate complete campaign before allowing creation
				if ( ! $this->state_service ) {
					$result = new WP_Error( 'state_service_unavailable', __( 'Cannot validate campaign: state service unavailable', 'smart-cycle-discounts' ) );
					break;
				}

				$all_data = $this->state_service->get_all_data();
				$result = SCD_Validation::validate( $all_data, 'campaign_complete' );
				break;
				
			default:
				$result = new WP_Error( 'unknown_step', __( 'Unknown step', 'smart-cycle-discounts' ) );
		}
		
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Save data to state service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step           Step name.
	 * @param    array     $processed_data Processed data.
	 * @param    array     $request        Request data.
	 * @return   true|WP_Error             True if successful, error otherwise.
	 */
	private function _save_to_state( $step, $processed_data, $request ) {
		try {
			// Check database connection
			global $wpdb;
			if ( ! $wpdb->check_connection() ) {
				throw new Exception( 'Database connection lost' );
			}

			if ( ! $this->state_service ) {
				throw new Exception( 'State service is not available' );
			}

			$this->_log_save_attempt( $step, $processed_data, $request );

			// Save the data and check for errors
			$save_result = $this->state_service->save_step_data( $step, $processed_data );

			if ( false === $save_result ) {
				throw new Exception( 'Failed to save step data to state service' );
			}

			// Only mark step complete for navigation saves (not auto-saves)
			// Auto-saves store data but don't mark progress
			$is_auto_save = ! empty( $request['is_auto_save'] );
			$is_navigation_save = ! empty( $request['is_navigation_save'] ) || ! empty( $request['save_and_continue'] );

			if ( $is_navigation_save || ( ! $is_auto_save && ! $is_navigation_save ) ) {
				// Navigation save or full save: mark step complete
				$this->state_service->mark_step_complete( $step );
			}

			$this->_log_save_success( $step, $processed_data );

		} catch ( Exception $e ) {
			return $this->_handle_save_exception( $e, $step );
		}

		return true;
	}

	/**
	 * Log save attempt for debugging.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step           Step name.
	 * @param    array     $processed_data Processed data.
	 * @param    array     $request        Request data.
	 * @return   void
	 */
	private function _log_save_attempt( $step, $processed_data, $request ) {
		if ( function_exists( 'scd_debug_persistence' ) ) {
			$is_navigation_save = isset( $request['is_navigation_save'] ) || isset( $request['save_and_continue'] );
			scd_debug_persistence( 'save_attempt', $step, array(
				'step' => $step,
				'data_keys' => array_keys( $processed_data ),
				'is_auto_save' => isset( $request['is_auto_save'] ) ? $request['is_auto_save'] : false,
				'is_navigation_save' => $is_navigation_save,
			), true, 'Starting save operation' );
		}
	}

	/**
	 * Log save success for debugging.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step           Step name.
	 * @param    array     $processed_data Processed data.
	 * @return   void
	 */
	private function _log_save_success( $step, $processed_data ) {
		if ( function_exists( 'scd_debug_persistence' ) ) {
			scd_debug_persistence( 'save_success', $step, $processed_data, true, 'Step data saved successfully' );
		}
	}

	/**
	 * Handle save exception.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    Exception    $e       Exception.
	 * @param    string       $step    Step name.
	 * @return   WP_Error             Error response.
	 */
	private function _handle_save_exception( $e, $step ) {
		// Handle lock acquisition failures
		if ( false !== strpos( $e->getMessage(), 'session lock' ) ) {
			return new WP_Error(
				'session_lock',
				__( 'Another save operation is in progress. Please try again.', 'smart-cycle-discounts' ),
				array( 'status' => 409 )
			);
		}

		// Handle session size limit errors
		if ( false !== strpos( $e->getMessage(), 'too large' ) ) {
			return new WP_Error(
				'payload_too_large',
				$e->getMessage(),
				array( 'step' => $step, 'status' => 413 )
			);
		}
		
		// Database errors
		global $wpdb;
		if ( $wpdb->last_error ) {
			return new WP_Error(
				'database_error',
				__( 'Database temporarily unavailable. Please try again.', 'smart-cycle-discounts' ),
				array(
					'status' => 503,
					'retry_after' => 30,
					'error_type' => 'database_error',
					'debug_message' => WP_DEBUG ? $wpdb->last_error : null
				)
			);
		}
		
		// Generic error
		$this->_log_audit( 'step_save_error', array(
			'step' => $step,
			'error' => $e->getMessage()
		), 'error', $e->getMessage() );

		return new WP_Error(
			'save_step_error',
			$e->getMessage(),
			array(
				'step' => $step,
				'error_code' => $e->getCode() ? $e->getCode() : 500,
				'status' => 500
			)
		);
	}

	/**
	 * Build response array.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step           Step name.
	 * @param    array     $processed_data Processed data.
	 * @param    array     $original_data  Original data.
	 * @param    array     $request        Request data.
	 * @return   array                     Response array.
	 */
	private function _build_response( $step, $processed_data, $original_data, $request ) {
		$next_step = $this->_get_next_step( $step );
		$progress = $this->state_service ? $this->state_service->get_progress() : array(
			'completed_steps' => isset( $_SESSION['scd_wizard_completed_steps'] ) ? count( $_SESSION['scd_wizard_completed_steps'] ) : 0,
			'total_steps' => 5,
			'percentage' => 0
		);

		$response = array(
			'message' => __( 'Step data saved successfully', 'smart-cycle-discounts' ),
			'step' => $step,
			'next_step' => $next_step,
			'progress' => $progress
		);

		// Include condition errors if any
		if ( ! empty( $this->condition_errors['invalid'] ) || ! empty( $this->condition_errors['warnings'] ) ) {
			$response['condition_errors'] = $this->condition_errors;

			// Update message if there are errors
			if ( ! empty( $this->condition_errors['invalid'] ) ) {
				$response['message'] = sprintf(
					__( 'Step data saved with %d condition error(s)', 'smart-cycle-discounts' ),
					count( $this->condition_errors['invalid'] )
				);
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response['debug'] = array(
				'step' => $step,
				'data_keys' => array_keys( $original_data ),
				'data_count' => count( $original_data ),
				'processed_keys' => array_keys( $processed_data )
			);
		}

		return $response;
	}

	/**
	 * Finalize save operation with audit logging and caching.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step              Step name.
	 * @param    array     $processed_data    Processed data.
	 * @param    array     $request           Request data.
	 * @param    string    $idempotency_key   Idempotency key.
	 * @param    array     $response          Response data.
	 * @return   void
	 */

	/**
	 * Claim idempotent request atomically to prevent concurrent execution.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $key    Idempotency key.
	 * @return   true|WP_Error|array    True if claimed, error if duplicate, array if completed.
	 */
	private function _claim_idempotent_request( $key ) {
		if ( empty( $key ) ) {
			return true;
		}

		$processing_key = 'scd_idem_proc_' . md5( $key );

		// Try to claim request atomically (wp_cache_add only succeeds if key does not exist)
		$claimed = wp_cache_add( $processing_key, time(), '', 30 );

		if ( $claimed ) {
			// Successfully claimed - this request will process
			return true;
		}

		// Another request is already processing - check if result exists
		$result = $this->_get_idempotent_response( $key );
		if ( $result ) {
			// Another request completed successfully - return cached result
			return $result;
		}

		// Request is still processing - return 409 Conflict
		// Client will retry with exponential backoff (handled by AJAX service)
		return new WP_Error(
			'duplicate_request',
			__( 'Duplicate request detected. Please wait.', 'smart-cycle-discounts' ),
			array(
				'status' => 409,
				'retry_after' => 2 // Suggest retry after 2 seconds
			)
		);
	}
	private function _finalize_save( $step, $processed_data, $request, $idempotency_key, $response ) {
		// Log successful save
		try {
			$this->_log_audit( 'step_saved', array(
				'step' => $step,
				'data_size' => strlen( serialize( $processed_data ) ),
				'is_auto_save' => isset( $request['is_auto_save'] ) ? $request['is_auto_save'] : false
			) );
		} catch ( Exception $audit_exception ) {
			// Don't let audit logging failure break the save
		}
		
		// Cache response for idempotency
		if ( ! empty( $idempotency_key ) ) {
			$this->_cache_idempotent_response( $idempotency_key, $response );
		}
	}

	/**
	 * Get audit logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   SCD_Audit_Logger    Audit logger instance.
	 */
	private function _get_audit_logger() {
		if ( null === $this->audit_logger ) {
			$logger_path = SCD_PLUGIN_DIR . 'includes/security/class-audit-logger.php';
			if ( file_exists( $logger_path ) && ! class_exists( 'SCD_Audit_Logger' ) ) {
				require_once $logger_path;
			} elseif ( ! file_exists( $logger_path ) ) {
				throw new Exception( 'Audit logger file not found' );
			}
			
			if ( class_exists( 'SCD_Audit_Logger' ) ) {
				$this->audit_logger = new SCD_Audit_Logger();
			} else {
				throw new Exception( 'Audit logger class not found' );
			}
		}
		
		return $this->audit_logger;
	}
	/**
	 * Log audit event.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $action         Action name.
	 * @param    array     $details        Action details.
	 * @param    string    $status         Status (success/error/warning).
	 * @param    string    $error_message  Error message if applicable.
	 * @return   void
	 */
	private function _log_audit( $action, $details = array(), $status = 'success', $error_message = '' ) {
		try {
			$session_id = $this->state_service ? $this->state_service->get_session_id() : null;
			if ( $session_id ) {
				$logger = $this->_get_audit_logger();
				
				$resource = 'wizard_step_' . ( isset( $details['step'] ) ? $details['step'] : 'unknown' );
				$logger->log_access_event( $resource, $action, 'success' === $status );
			}
		} catch ( Exception $e ) {
			// Don't let audit logging failures affect the main operation
		}
	}

	/**
	 * Get cached idempotent response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $key    Idempotency key.
	 * @return   array|null        Cached response or null if not found.
	 */
	private function _get_idempotent_response( $key ) {
		if ( empty( $key ) ) {
			return null;
		}
		
		$cache_key = 'scd_idem_' . md5( $key );
		$cached = get_transient( $cache_key );
		
		if ( $cached && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SCD: Returning cached response for idempotency key: ' . $key );
		}
		
		return $cached ? $cached : null;
	}

	/**
	 * Cache idempotent response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $key       Idempotency key.
	 * @param    array     $response  Response to cache.
	 * @return   void
	 */
	private function _cache_idempotent_response( $key, $response ) {
		if ( empty( $key ) ) {
			return;
		}

		$cache_key = 'scd_idem_' . md5( $key );
		// Cache for 10 minutes
		set_transient( $cache_key, $response, 600 );
	}

	/**
	 * Validate PRO features for the step.
	 *
	 * This is a critical security layer that prevents free users from
	 * bypassing UI restrictions and using PRO features.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
	 * @return   true|WP_Error      True if valid, WP_Error if PRO feature detected.
	 */
	private function _validate_pro_features( $step, $data ) {
		// If feature gate not available, allow (fail open for safety)
		if ( ! $this->feature_gate ) {
			return true;
		}

		// Load PRO feature validator class
		$validator_path = SCD_PLUGIN_DIR . 'includes/core/validation/class-pro-feature-validator.php';
		if ( ! file_exists( $validator_path ) ) {
			// Validator file missing - log error but allow (fail open)
			if ( $this->logger ) {
				$this->logger->warning( 'PRO feature validator file not found', array(
					'path' => $validator_path
				) );
			}
			return true;
		}

		require_once $validator_path;

		if ( ! class_exists( 'SCD_PRO_Feature_Validator' ) ) {
			// Validator class missing - log error but allow (fail open)
			if ( $this->logger ) {
				$this->logger->warning( 'PRO feature validator class not found' );
			}
			return true;
		}

		// Create validator and validate step
		$validator = new SCD_PRO_Feature_Validator( $this->feature_gate );
		return $validator->validate_step( $step, $data );
	}

}