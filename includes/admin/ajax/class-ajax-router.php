<?php
/**
 * Unified AJAX Router
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

// Load AJAX Response class
if ( ! class_exists( 'SCD_AJAX_Response' ) ) {
	require_once __DIR__ . '/class-scd-ajax-response.php';
}

/**
 * Unified AJAX Router Class
 *
 * Single router for all AJAX requests.
 *
 * Security measures implemented:
 * - Handler class name validation (alphanumeric + underscore only)
 * - File name validation (prevent directory traversal)
 * - Absolute path validation using realpath()
 * - File extension verification
 * - Whitelist of allowed directories
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Ajax_Router {

	/**
	 * Registered handlers mapping
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $handlers    Action to handler class mapping
	 */
	private $handlers = array();

	/**
	 * Handler instances cache
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $handler_instances    Instantiated handlers
	 */
	private $handler_instances = array();

	/**
	 * Initialize the AJAX router
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->register_handlers();
	}

	/**
	 * Initialize AJAX hooks
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		// Register WordPress AJAX hooks for each handler
		foreach ( $this->handlers as $action => $handler_class ) {
			// Register for logged-in users
			add_action( 'wp_ajax_scd_' . $action, array( $this, 'route_request' ) );
			// Register for logged-out users (if needed for specific actions)
			// add_action( 'wp_ajax_nopriv_scd_' . $action, array( $this, 'route_request' ) );
		}
	}

	/**
	 * Route AJAX request to appropriate handler
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function route_request() {
		$start_time = microtime( true );

		// Add console debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD AJAX] ===== AJAX REQUEST START =====' );
			error_log( '[SCD AJAX] POST data: ' . json_encode( $_POST ) );
			error_log( '[SCD AJAX] REQUEST data: ' . json_encode( $_REQUEST ) );
		}

		// Get action from request
		// Priority order (CRITICAL FIX):
		// 1. Custom scdAction parameter (unified endpoint sends this)
		// 2. Custom scd_action parameter (backward compatibility)
		// 3. WordPress standard 'action' parameter (only for direct hook calls)
		//
		// When called via unified endpoint (wp_ajax_scd_ajax), the WordPress 'action'
		// parameter will always be 'scd_ajax', so we must check scdAction FIRST.
		$action = '';
		if ( isset( $_POST['scdAction'] ) ) {
			$action = sanitize_text_field( $_POST['scdAction'] );
		} elseif ( isset( $_REQUEST['scdAction'] ) ) {
			$action = sanitize_text_field( $_REQUEST['scdAction'] );
		} elseif ( isset( $_POST['scd_action'] ) ) {
			$action = sanitize_text_field( $_POST['scd_action'] );
		} elseif ( isset( $_REQUEST['scd_action'] ) ) {
			$action = sanitize_text_field( $_REQUEST['scd_action'] );
		} elseif ( isset( $_POST['action'] ) ) {
			$action = sanitize_text_field( $_POST['action'] );
		} elseif ( isset( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( $_REQUEST['action'] );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD AJAX] Action extracted: ' . $action );
		}

		// Debug: Log AJAX request received
		if ( function_exists( 'scd_debug_ajax' ) ) {
			$nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '' );
			scd_debug_ajax( $action ? $action : 'no_action', $_POST, $nonce );
		}

		if ( empty( $action ) ) {
			// Debug: Log no action error
			if ( function_exists( 'scd_debug_ajax_response' ) ) {
				scd_debug_ajax_response( 'no_action', array( 'error' => 'No action specified' ), false, microtime( true ) - $start_time );
			}

			SCD_AJAX_Response::error(
				'No action specified',
				'no_action'
			);
		}

		// Strip scd_ prefix for handler lookup if present
		$handler_action = preg_replace( '/^scd_/', '', $action );

		// Debug: Log before handler check
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD AJAX Router] About to check handler existence' );
			error_log( '[SCD AJAX Router] Handler action: ' . $handler_action );
			error_log( '[SCD AJAX Router] Handlers array: ' . print_r( array_keys( $this->handlers ), true ) );
		}

		// Check if handler exists
		if ( ! isset( $this->handlers[ $handler_action ] ) ) {
			// Debug: Log invalid action with more details
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Invalid action requested' );
				error_log( '[SCD AJAX Router] Original action: ' . $action );
				error_log( '[SCD AJAX Router] Handler action after stripping: ' . $handler_action );
				error_log( '[SCD AJAX Router] Available handlers: ' . print_r( array_keys( $this->handlers ), true ) );
			}

			if ( function_exists( 'scd_debug_ajax_response' ) ) {
				scd_debug_ajax_response( $action, array( 'error' => 'Invalid action' ), false, microtime( true ) - $start_time );
			}

			SCD_AJAX_Response::error(
				sprintf( 'Invalid action: %s', $action ),
				'invalid_action'
			);
		}

		// Centralized security validation - ensure we use full action name with scd_ prefix
		if ( class_exists( 'SCD_Ajax_Security' ) ) {
			// Debug: Log security check start
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Starting security validation' );
			}

			// Security expects the full action name with scd_ prefix
			$full_action = strpos( $action, 'scd_' ) === 0 ? $action : 'scd_' . $action;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Full action for security: ' . $full_action );
			}

			$security_check = SCD_Ajax_Security::verify_ajax_request( $full_action, $_POST );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Security check result: ' . ( is_wp_error( $security_check ) ? 'FAILED - ' . $security_check->get_error_message() : 'PASSED' ) );
			}

			if ( is_wp_error( $security_check ) ) {
				// Debug: Log security failure
				if ( function_exists( 'scd_debug_ajax_response' ) ) {
					scd_debug_ajax_response(
						$action,
						array(
							'error' => $security_check->get_error_message(),
							'code'  => $security_check->get_error_code(),
						),
						false,
						microtime( true ) - $start_time
					);
				}

				SCD_AJAX_Response::wp_error( $security_check );
			}
		}

		// Debug: Log before handler instantiation
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD AJAX Router] Security passed, getting handler instance' );
		}

		// Get or create handler instance
		$handler = $this->get_handler_instance( $handler_action );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD AJAX Router] Handler action: ' . $handler_action );
			error_log( '[SCD AJAX Router] Handler class: ' . ( isset( $this->handlers[ $handler_action ] ) ? $this->handlers[ $handler_action ] : 'not found' ) );
			error_log( '[SCD AJAX Router] Handler instance: ' . ( $handler ? get_class( $handler ) : 'null' ) );
		}

		if ( ! $handler ) {
			// Add more detailed error logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Handler initialization failed for action: ' . $handler_action );
				error_log( '[SCD AJAX Router] Handler class expected: ' . ( isset( $this->handlers[ $handler_action ] ) ? $this->handlers[ $handler_action ] : 'not found' ) );
			}

			SCD_AJAX_Response::error(
				'Handler initialization failed',
				'handler_init_failed'
			);
		}

		// Execute handler
		try {
			// Debug: Log handler execution start
			if ( function_exists( 'scd_debug' ) && scd_debug() ) {
				scd_debug()->info(
					'AJAX handler execution start',
					array(
						'handler' => get_class( $handler ),
						'action'  => $action,
					)
				);
			}

			// Add extra error logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Executing handler: ' . get_class( $handler ) . ' for action: ' . $action );
			}

			// Prepare request data for handler
			$request_data = array_merge(
				$_POST,
				array(
					'action' => $action,
					'method' => $_SERVER['REQUEST_METHOD'],
				)
			);

			// Convert camelCase keys from JavaScript to snake_case for PHP
			$request_data = self::camel_to_snake_keys( $request_data );

			// Call handler - check if it extends SCD_Abstract_Ajax_Handler
			if ( is_a( $handler, 'SCD_Abstract_Ajax_Handler' ) ) {
				// New base class - use execute() method with built-in security
				$result = $handler->execute( $request_data );
			} else {
				// Legacy handler - check if it expects parameters
				$reflection = new ReflectionMethod( $handler, 'handle' );
				$params     = $reflection->getParameters();

				if ( count( $params ) > 0 ) {
					// Handler expects request data
					$result = $handler->handle( $request_data );
				} else {
					// Legacy handler without parameters
					$result = $handler->handle();
				}
			}

			// Check if handler returned data
			if ( $result !== null ) {
				$duration = microtime( true ) - $start_time;

				if ( is_wp_error( $result ) ) {
					// Debug: Log WP_Error response
					if ( function_exists( 'scd_debug_ajax_response' ) ) {
						scd_debug_ajax_response(
							$action,
							array(
								'error' => $result->get_error_message(),
								'code'  => $result->get_error_code(),
							),
							false,
							$duration
						);
					}

					SCD_AJAX_Response::wp_error( $result );
				} elseif ( is_array( $result ) ) {
					if ( isset( $result['success'] ) && false === $result['success'] ) {
						// Debug: Log error response
						if ( function_exists( 'scd_debug_ajax_response' ) ) {
							scd_debug_ajax_response( $action, $result, false, $duration );
						}

						SCD_AJAX_Response::error(
							isset( $result['message'] ) ? $result['message'] : 'Request failed',
							isset( $result['code'] ) ? $result['code'] : 'request_failed',
							isset( $result['data'] ) ? $result['data'] : null
						);
					} else {
						// Debug: Log success response
						if ( function_exists( 'scd_debug_ajax_response' ) ) {
							scd_debug_ajax_response( $action, $result, true, $duration );
						}

						// CRITICAL FIX: Handlers already wrap responses with success/data structure
						// Extract just the data to avoid double-wrapping in SCD_AJAX_Response
						$response_data = isset( $result['data'] ) ? $result['data'] : $result;
						SCD_AJAX_Response::success( $response_data );
					}
				} else {
					// Debug: Log success response
					if ( function_exists( 'scd_debug_ajax_response' ) ) {
						scd_debug_ajax_response( $action, $result, true, $duration );
					}

					SCD_AJAX_Response::success( $result );
				}
			}
		} catch ( Exception $e ) {
			// Debug: Log exception
			if ( function_exists( 'scd_debug_error' ) ) {
				scd_debug_error(
					'AJAX handler exception',
					$e,
					array(
						'action'  => $action,
						'handler' => isset( $handler ) ? get_class( $handler ) : 'unknown',
					)
				);
			}

			// Additional error logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Exception caught: ' . $e->getMessage() );
				error_log( '[SCD AJAX Router] Stack trace: ' . $e->getTraceAsString() );
				error_log( '[SCD AJAX Router] Action: ' . $action );
				error_log( '[SCD AJAX Router] Handler: ' . ( isset( $handler ) ? get_class( $handler ) : 'unknown' ) );
			}

			// Debug: Log error response
			if ( function_exists( 'scd_debug_ajax_response' ) ) {
				scd_debug_ajax_response( $action, array( 'error' => $e->getMessage() ), false, microtime( true ) - $start_time );
			}

			SCD_AJAX_Response::error(
				$e->getMessage(),
				'exception_error'
			);
		}
	}

	/**
	 * Handle non-privileged AJAX requests
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function route_request_nopriv() {
		SCD_AJAX_Response::unauthorized();
	}

	/**
	 * Register all AJAX handlers
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_handlers() {
		$this->handlers = array(
			// Wizard handlers
			'save_step'                      => 'SCD_Save_Step_Handler',
			'load_data'                      => 'SCD_Load_Data_Handler',
			'load_session'                   => 'SCD_Load_Data_Handler', // Map load_session to same handler
			'product_search'                 => 'SCD_Product_Search_Handler',
			'get_summary'                    => 'SCD_Get_Summary_Handler',
			'check_campaign_name'            => 'SCD_Check_Campaign_Name_Handler',
			'get_product_stats'              => 'SCD_Get_Product_Stats_Handler',
			'recover_session'                => 'SCD_Recover_Session_Handler',
			'health_check'                   => 'SCD_Health_Check_Handler',
			'check_session'                  => 'SCD_Check_Session_Handler',
			'session_status'                 => 'SCD_Session_Status_Handler',
			'check_conflicts'                => 'SCD_Check_Conflicts_Handler',
			'preview_coverage'               => 'SCD_Preview_Coverage_Handler',
			'campaign_health'                => 'SCD_Campaign_Health_Handler',
			'calculate_discount_impact'      => 'SCD_Calculate_Discount_Impact_Handler',
			'sale_items_filter'              => 'SCD_Sale_Items_Filter_Handler',
			'profit_margin_warning'          => 'SCD_Profit_Margin_Warning_Handler',
			'apply_recommendation'           => 'SCD_Apply_Recommendation_Handler',

			// Campaign handlers
			'get_active_campaigns'           => 'SCD_Get_Active_Campaigns_Handler',
			'quick_edit'                     => 'SCD_Quick_Edit_Handler',

			// Debug handlers
			'debug_log'                      => 'SCD_Ajax_Debug_Log',
			'log_console'                    => 'SCD_Console_Logger_Handler',
			'write_debug_log'                => 'SCD_Debug_Log_Handler',
			'log_viewer'                     => 'SCD_Log_Viewer_Handler',

			// Tools handlers
			'export'                         => 'SCD_Import_Export_Handler',
			'import'                         => 'SCD_Import_Handler',
			'database_maintenance'           => 'SCD_Tools_Handler',
			'cache_management'               => 'SCD_Tools_Handler',
			'clear_cache'                    => 'SCD_Clear_Cache_Handler',

			// Draft handlers (consolidated)
			'complete_wizard'                => 'SCD_Draft_Handler',
			'save_draft'                     => 'SCD_Draft_Handler',
			'delete_draft'                   => 'SCD_Draft_Handler',
			'draft_list'                     => 'SCD_Draft_Handler',
			'draft_preview'                  => 'SCD_Draft_Handler',

			// Dashboard handlers
			'main_dashboard_data'            => 'SCD_Main_Dashboard_Data_Handler',

			// Analytics handlers
			'analytics_overview'             => 'SCD_Overview_Handler',
			'analytics_campaign_performance' => 'SCD_Campaign_Performance_Handler',
			'analytics_revenue_trend'        => 'SCD_Revenue_Trend_Handler',
			'analytics_top_products'         => 'SCD_Top_Products_Handler',
			'analytics_activity_feed'        => 'SCD_Activity_Feed_Handler',
			'analytics_export'               => 'SCD_Export_Handler',

			// Event tracking
			'track_event'                    => 'SCD_Track_Event_Handler',

			// Discount API handlers
			'validate_discount_rules'        => 'SCD_Discount_API_Handler',
			'get_discount_preview'           => 'SCD_Discount_API_Handler',
			'calculate_discount_impact'      => 'SCD_Discount_API_Handler',

			// Email/Notification handlers
			'send_test_email'                => 'SCD_Send_Test_Email_Handler',
			'process_queue'                  => 'SCD_Process_Queue_Handler',
			'retry_failed_emails'            => 'SCD_Retry_Failed_Emails_Handler',
			'clear_queue'                    => 'SCD_Clear_Queue_Handler',

			// License/Debug handlers
			'clear_license_cache'            => 'SCD_Clear_License_Cache_Handler',
		);
	}

	/**
	 * Get handler instance
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action    Action name
	 * @return   object|null           Handler instance or null
	 */
	private function get_handler_instance( $action ) {
		// Check cache first
		if ( isset( $this->handler_instances[ $action ] ) ) {
			return $this->handler_instances[ $action ];
		}

		$handler_class = $this->handlers[ $action ];

		// Load handler file if needed
		if ( ! class_exists( $handler_class ) ) {
			$file = $this->get_handler_file( $handler_class );

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Looking for handler file for class: ' . $handler_class );
				error_log( '[SCD AJAX Router] Handler file path: ' . ( $file ? $file : 'not found' ) );
			}

			// Security: Only load file if it's properly validated
			if ( $file && is_string( $file ) && file_exists( $file ) && is_readable( $file ) ) {
				// Additional security check: Verify file extension
				if ( substr( $file, -4 ) === '.php' ) {
					// Log before requiring
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX Router] Loading handler file: ' . $file );
					}
					require_once $file;
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX Router] Handler file does not have .php extension: ' . $file );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[SCD AJAX Router] Handler file not valid or not readable: ' . ( $file ? $file : 'null' ) );
			}
		}

		// Create instance if class exists
		if ( class_exists( $handler_class ) ) {
			// Special handling for handlers that require dependencies
			if ( 'SCD_Save_Step_Handler' === $handler_class ) {
				// Get the services from the container
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it
					$class_path = SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					if ( ! file_exists( $class_path ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD Wizard:AJAX] State service class file not found: ' . $class_path );
						}
						return null;
					}

					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once $class_path;
					}

					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD Wizard:AJAX] State service class not available after require' );
						}
						return null;
					}

					try {
						$state_service = new SCD_Wizard_State_Service();
						if ( ! $state_service || ! method_exists( $state_service, 'initialize_with_intent' ) ) {
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( '[SCD Wizard:AJAX] Failed to instantiate state service or missing method' );
							}
							return null;
						}
						// Initialize the state service with existing session from cookie
						// CRITICAL FIX: Check if we're in edit mode by reading from existing session
						// AJAX requests don't have $_GET params, so we check the session data
						$state_service->initialize_with_intent( 'continue' );

						// Now check if the session indicates edit mode
						$campaign_id  = $state_service->get( 'campaign_id', 0 );
						$is_edit_mode = $state_service->get( 'is_edit_mode', false );

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD AJAX Router] Session data: campaign_id=' . $campaign_id . ', is_edit_mode=' . ( $is_edit_mode ? 'yes' : 'no' ) );
							error_log( '[SCD AJAX Router] All session data: ' . print_r( $state_service->get_all_data(), true ) );
						}

						// If we have a campaign_id, assume edit mode and initialize Change Tracker
						// The presence of campaign_id means we're editing an existing campaign
						if ( $campaign_id > 0 ) {
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( '[SCD AJAX Router] Detected edit mode - initializing Change Tracker for campaign ' . $campaign_id );
							}
							$state_service->initialize_with_intent( 'edit' );
						}
					} catch ( Exception $e ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD Wizard:AJAX] Exception creating state service: ' . $e->getMessage() );
						}
						return null;
					}
				}

				// Verify state service is valid before using
				if ( ! $state_service ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD Wizard:AJAX] State service is null, cannot instantiate handler' );
					}
					return null;
				}

				// Get feature gate for PRO feature validation
				$feature_gate = $container::get_service( 'feature_gate' );

				// Get idempotency and transformer services
				$idempotency_service = null;
				$transformer         = null;

				try {
					$idempotency_service = $container::get_service( 'idempotency_service' );
					$transformer         = $container::get_service( 'step_data_transformer' );
				} catch ( Exception $e ) {
					// Services will be created in handler constructor if not available
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX] Optional services not available, will be created: ' . $e->getMessage() );
					}
				}

				// Save_Step_Handler with all services
				$this->handler_instances[ $action ] = new $handler_class(
					$state_service,
					null,  // logger
					$feature_gate,
					$idempotency_service,
					$transformer
				);
			} elseif ( 'SCD_Load_Data_Handler' === $handler_class ) {
				// Load Data handler requires state service
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
					// Initialize the state service with existing session from cookie
					$state_service->initialize_with_intent( 'continue' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'SCD_Session_Status_Handler' === $handler_class ) {
				// Session Status handler requires session service
				$container       = Smart_Cycle_Discounts::get_instance();
				$session_service = $container::get_service( 'session_service' );

				if ( ! $session_service ) {
					// If service not available, create it
					if ( ! class_exists( 'SCD_Session_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-session-service.php';
					}
					$session_service = new SCD_Session_Service();
				}

				$this->handler_instances[ $action ] = new $handler_class( $session_service );
			} elseif ( 'SCD_Apply_Recommendation_Handler' === $handler_class ) {
				// Apply Recommendation handler requires state service
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
					// Initialize the state service with existing session from cookie
					$state_service->initialize_with_intent( 'continue' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'SCD_Get_Summary_Handler' === $handler_class ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[SCD AJAX Router] ===== GET SUMMARY HANDLER DETECTED =====' );
				}
				// CRITICAL FIX: Get Summary handler requires state service with Change Tracker in edit mode
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
					// Initialize the state service with existing session from cookie
					$state_service->initialize_with_intent( 'continue' );
				}

				// CRITICAL: Always check if we're in edit mode and ensure Change Tracker is initialized
				// This handles both fresh state service and container-provided service
				$campaign_id  = $state_service->get( 'campaign_id', 0 );
				$is_edit_mode = $state_service->get( 'is_edit_mode', false );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[SCD AJAX Router:Get Summary] Session data: campaign_id=' . $campaign_id . ', is_edit_mode=' . ( $is_edit_mode ? 'yes' : 'no' ) );
					error_log( '[SCD AJAX Router:Get Summary] Has change tracker: ' . ( $state_service->get_change_tracker() ? 'yes' : 'no' ) );
				}

				// If we have a campaign_id but no Change Tracker, initialize edit mode
				if ( $campaign_id > 0 && ! $state_service->get_change_tracker() ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX Router:Get Summary] Detected edit mode without Change Tracker - initializing for campaign ' . $campaign_id );
					}
					$state_service->initialize_with_intent( 'edit' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'SCD_Draft_Handler' === $handler_class ) {
				// Draft handler requires multiple services
				$container = Smart_Cycle_Discounts::get_instance();

				// Get wizard state service
				$state_service = $container::get_service( 'wizard_state' );
				if ( ! $state_service ) {
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
				}

				// Get campaign manager from container (required)
				$campaign_manager = $container::get_service( 'campaign_manager' );
				if ( ! $campaign_manager ) {
					SCD_AJAX_Response::error(
						'service_unavailable',
						__( 'Campaign manager service not available', 'smart-cycle-discounts' )
					);
					return;
				}

				// Get campaign compiler
				$compiler = $container::get_service( 'campaign_compiler' );
				if ( ! $compiler ) {
					if ( ! class_exists( 'SCD_Campaign_Compiler_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
					}
					$compiler = new SCD_Campaign_Compiler_Service();
				}

				// Get logger
				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new SCD_Logger();
				}

				// Get audit logger (optional)
				$audit_logger = $container::get_service( 'audit_logger' );
				if ( ! $audit_logger && class_exists( 'SCD_Audit_Logger' ) ) {
					$audit_logger = new SCD_Audit_Logger();
				}

				// Get feature gate
				$feature_gate = $container::get_service( 'feature_gate' );
				if ( ! $feature_gate && class_exists( 'SCD_Feature_Gate' ) ) {
					$feature_gate = new SCD_Feature_Gate();
				}

				$this->handler_instances[ $action ] = new $handler_class(
					$state_service,
					$campaign_manager,
					$compiler,
					$logger,
					$audit_logger,
					$feature_gate
				);
			} elseif ( strpos( $handler_class, 'SCD_Overview_Handler' ) !== false ||
						strpos( $handler_class, 'SCD_Revenue_Trend_Handler' ) !== false ||
						strpos( $handler_class, 'SCD_Campaign_Performance_Handler' ) !== false ||
						strpos( $handler_class, 'SCD_Top_Products_Handler' ) !== false ||
						strpos( $handler_class, 'SCD_Activity_Feed_Handler' ) !== false ||
						strpos( $handler_class, 'SCD_Export_Handler' ) !== false ||
						strpos( $handler_class, 'SCD_Refresh_Cache_Handler' ) !== false ) {
				// Analytics handlers require metrics calculator, logger, and possibly other services
				$container = Smart_Cycle_Discounts::get_instance();

				// Get metrics calculator (required for all analytics handlers)
				$metrics_calculator = $container::get_service( 'metrics_calculator' );
				if ( ! $metrics_calculator ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX Router] Metrics calculator service not available for analytics handler: ' . $handler_class );
					}
					SCD_AJAX_Response::error(
						'service_unavailable',
						__( 'Metrics calculator service not available', 'smart-cycle-discounts' )
					);
					return null;
				}

				// Get logger (required for all analytics handlers)
				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new SCD_Logger();
				}

				// Handle specific handler requirements
				if ( 'SCD_Overview_Handler' === $handler_class ) {
					// Overview handler only needs metrics_calculator and logger
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger );

				} elseif ( 'SCD_Revenue_Trend_Handler' === $handler_class ||
							'SCD_Campaign_Performance_Handler' === $handler_class ||
							'SCD_Top_Products_Handler' === $handler_class ) {
					// These handlers need analytics_collector
					$analytics_collector = $container::get_service( 'analytics_collector' );
					if ( ! $analytics_collector ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD AJAX Router] Analytics collector service not available for: ' . $handler_class );
						}
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Analytics collector service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $analytics_collector );

				} elseif ( 'SCD_Activity_Feed_Handler' === $handler_class ) {
					// Activity feed handler needs activity_tracker
					$activity_tracker = $container::get_service( 'activity_tracker' );
					if ( ! $activity_tracker ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD AJAX Router] Activity tracker service not available for: ' . $handler_class );
						}
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Activity tracker service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $activity_tracker );

				} elseif ( 'SCD_Export_Handler' === $handler_class ) {
					// Export handler needs export_service
					$export_service = $container::get_service( 'export_service' );
					if ( ! $export_service ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD AJAX Router] Export service not available for: ' . $handler_class );
						}
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Export service not available', 'smart-cycle-discounts' )
						);
						return null;
					}

					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $export_service );

				} elseif ( 'SCD_Refresh_Cache_Handler' === $handler_class ) {
					// Refresh cache handler needs cache_manager
					$cache_manager = $container::get_service( 'cache_manager' );
					if ( ! $cache_manager ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[SCD AJAX Router] Cache manager service not available for: ' . $handler_class );
						}
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Cache manager service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $cache_manager );
				}
			} elseif ( 'SCD_Import_Export_Handler' === $handler_class ||
						'SCD_Import_Handler' === $handler_class ||
						'SCD_Tools_Handler' === $handler_class ||
						'SCD_Clear_Cache_Handler' === $handler_class ||
						'SCD_Clear_License_Cache_Handler' === $handler_class ) {
				// Tools and debug handlers require container and logger
				$container = Smart_Cycle_Discounts::get_instance();

				// Get logger
				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new SCD_Logger();
				}

				// Get feature gate for Import/Export Handler
				if ( 'SCD_Import_Export_Handler' === $handler_class ) {
					$feature_gate = $container::get_service( 'feature_gate' );
					if ( ! $feature_gate && class_exists( 'SCD_Feature_Gate' ) ) {
						$feature_gate = new SCD_Feature_Gate();
					}
					$this->handler_instances[ $action ] = new $handler_class( $container, $logger, $feature_gate );
				} else {
					$this->handler_instances[ $action ] = new $handler_class( $container, $logger );
				}
			} elseif ( 'SCD_Main_Dashboard_Data_Handler' === $handler_class ) {
				// Main dashboard data handler requires dashboard page and logger from service container
				$container = Smart_Cycle_Discounts::get_instance();
				$handler   = $container::get_service( 'main_dashboard_data_handler' );

				if ( ! $handler ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX Router] Failed to get main_dashboard_data_handler from service container' );
					}
					return null;
				}

				$this->handler_instances[ $action ] = $handler;
			} else {
				// Default instantiation for handlers without dependencies
				try {
					$this->handler_instances[ $action ] = new $handler_class();
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[SCD AJAX Router] Failed to instantiate handler ' . $handler_class . ': ' . $e->getMessage() );
					}
					return null;
				}
			}

			return $this->handler_instances[ $action ];
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD AJAX Router] Handler class does not exist after loading: ' . $handler_class );
		}

		return null;
	}

	/**
	 * Get handler file path
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $handler_class    Handler class name
	 * @return   string|null                 File path or null
	 */
	private function get_handler_file( $handler_class ) {
		// Security: Validate handler class name format
		if ( ! preg_match( '/^SCD_[A-Za-z0-9_]+_Handler$/', $handler_class ) ) {
			return null;
		}

		// Convert class name to file name
		$file_name = 'class-' . str_replace( '_', '-', strtolower( str_replace( 'SCD_', '', $handler_class ) ) ) . '.php';

		// Security: Validate file name format (prevent directory traversal)
		if ( ! preg_match( '/^class-[a-z0-9-]+\.php$/', $file_name ) ) {
			return null;
		}

		// Define allowed directories (absolute paths for security)
		$allowed_dirs = array(
			__DIR__ . '/handlers/',
			__DIR__ . '/analytics/',
			dirname( dirname( __DIR__ ) ) . '/core/validation/',
			dirname( dirname( __DIR__ ) ) . '/core/wizard/',
			dirname( dirname( __DIR__ ) ) . '/core/campaigns/',
			dirname( dirname( __DIR__ ) ) . '/core/analytics/',
		);

		// Check each allowed directory
		foreach ( $allowed_dirs as $dir ) {
			$full_path = $dir . $file_name;

			// Security: Use realpath to prevent directory traversal
			$real_path = realpath( $full_path );
			$real_dir  = realpath( $dir );

			// Verify the file exists and is within the allowed directory
			if ( $real_path && $real_dir && strpos( $real_path, $real_dir ) === 0 && file_exists( $real_path ) ) {
				return $real_path;
			}
		}

		return null;
	}

	/**
	 * Convert array keys from camelCase to snake_case recursively
	 * Normalizes data from JavaScript for PHP consumption
	 *
	 * Delegates to SCD_Case_Converter utility for actual conversion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $data    Data to convert.
	 * @param    string $path    Internal path tracking (unused, kept for BC).
	 * @return   mixed             Converted data.
	 */
	private static function camel_to_snake_keys( $data, $path = '' ) {
		// Ensure utility class is loaded
		if ( ! class_exists( 'SCD_Case_Converter' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/utilities/class-case-converter.php';
		}

		return SCD_Case_Converter::camel_to_snake( $data );
	}
}
