<?php
/**
 * Ajax Router Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/class-ajax-router.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Removed strict types for PHP compatibility.

// Load AJAX Response class.
if ( ! class_exists( 'WSSCD_AJAX_Response' ) ) {
	require_once __DIR__ . '/class-wsscd-ajax-response.php';
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
class WSSCD_Ajax_Router {

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
		// Load AJAX security class (used by abstract handler and router).
		if ( ! class_exists( 'WSSCD_Ajax_Security' ) ) {
			require_once __DIR__ . '/class-ajax-security.php';
		}

		if ( ! class_exists( 'WSSCD_Abstract_Ajax_Handler' ) ) {
			require_once __DIR__ . '/abstract-class-ajax-handler.php';
		}

		$this->register_handlers();
	}

	/**
	 * Initialize AJAX hooks
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		// Register unified AJAX endpoint
		add_action( 'wp_ajax_wsscd_ajax', array( $this, 'route_request' ) );

		// Register WordPress AJAX hooks for each handler.
		foreach ( $this->handlers as $action => $handler_class ) {
			add_action( 'wp_ajax_wsscd_' . $action, array( $this, 'route_request' ) );
			// add_action( 'wp_ajax_nopriv_wsscd_' . $action, array( $this, 'route_request' ) ).
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

		// Priority order (CRITICAL FIX).
		// 1. Custom wsscdAction parameter (unified endpoint sends this).
		// 2. Custom wsscd_action parameter (backward compatibility).
		// 3. WordPress standard action parameter (only for direct hook calls).
		//
		// When called via unified endpoint (wp_ajax_wsscd_ajax), the WordPress action.
		// parameter will always be 'wsscd_ajax', so we must check wsscdAction FIRST.
		//
		// NOTE: We must access $_POST/$_REQUEST here to determine the action BEFORE nonce verification.
		// because we need the action name to know which nonce to verify (chicken-and-egg problem).
		// This is safe because: 1) immediately sanitized, 2) only used for routing, 3) nonce verified at line 165.
		// before any handler processes user data.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Action extracted for routing only, nonce verified below before handler processes data.
		$action = '';
		if ( isset( $_POST['wsscdAction'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['wsscdAction'] ) );
		} elseif ( isset( $_REQUEST['wsscdAction'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['wsscdAction'] ) );
		} elseif ( isset( $_POST['wsscd_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['wsscd_action'] ) );
		} elseif ( isset( $_REQUEST['wsscd_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['wsscd_action'] ) );
		} elseif ( isset( $_POST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		} elseif ( isset( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		}

		// Debug: Log AJAX request received (development only).
		// Only extract minimal debug fields - not the entire $_POST array.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug logging only; action already determined.
		if ( function_exists( 'wsscd_debug_ajax' ) ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ( isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '' );
			// Extract only debug-relevant fields, not entire $_POST.
			$debug_fields = array( 'action', 'wsscdAction', 'wsscd_action', 'step', 'campaign_id', 'campaignId' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Extracting only specific debug fields.
			$debug_data = WSSCD_Case_Converter::extract_and_sanitize( $debug_fields, $_POST );
			wsscd_debug_ajax( $action ? $action : 'no_action', $debug_data, $nonce );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		if ( empty( $action ) ) {
			// Debug: Log no action error.
			if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
				wsscd_debug_ajax_response( 'no_action', array( 'error' => 'No action specified' ), false, microtime( true ) - $start_time );
			}

			WSSCD_AJAX_Response::error(
				'No action specified',
				'no_action'
			);
		}

		// Strip wsscd_ prefix for handler lookup if present.
		$handler_action = preg_replace( '/^wsscd_/', '', $action );

		if ( ! isset( $this->handlers[ $handler_action ] ) ) {
			if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
				wsscd_debug_ajax_response( $action, array( 'error' => 'Invalid action' ), false, microtime( true ) - $start_time );
			}

			WSSCD_AJAX_Response::error(
				sprintf( 'Invalid action: %s', $action ),
				'invalid_action'
			);
		}

		// Centralized security validation - ensure we use full action name with wsscd_ prefix.
		if ( class_exists( 'WSSCD_Ajax_Security' ) ) {
			// Security expects the full action name with wsscd_ prefix.
			$full_action = 0 === strpos( $action, 'wsscd_' ) ? $action : 'wsscd_' . $action;

			// SECURITY: Extract and sanitize only security-relevant fields for verification.
			// The security function only needs: nonce, _wpnonce, _signature for verification.
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Extracting nonce for verification in verify_ajax_request().
			$security_data = array(
				'nonce'      => isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '',
				'_wpnonce'   => isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '',
				'_signature' => isset( $_POST['_signature'] ) ? sanitize_text_field( wp_unslash( $_POST['_signature'] ) ) : '',
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			// SECURITY: Nonce verification is performed inside WSSCD_Ajax_Security::verify_ajax_request().
			// That method calls verify_nonce() at line 341, which calls wp_verify_nonce() at line 414 of class-ajax-security.php.
			$security_check = WSSCD_Ajax_Security::verify_ajax_request( $full_action, $security_data );

			if ( is_wp_error( $security_check ) ) {
				// Debug: Log security failure.
				if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
					wsscd_debug_ajax_response(
						$action,
						array(
							'error' => $security_check->get_error_message(),
							'code'  => $security_check->get_error_code(),
						),
						false,
						microtime( true ) - $start_time
					);
				}

				WSSCD_AJAX_Response::wp_error( $security_check );
			}
		}

		$handler = $this->get_handler_instance( $handler_action );

		if ( ! $handler ) {
			WSSCD_AJAX_Response::error(
				'Handler initialization failed',
				'handler_init_failed'
			);
		}

		// Execute handler.
		try {
			// Debug: Log handler execution start.
			if ( function_exists( 'wsscd_debug' ) && wsscd_debug() ) {
				wsscd_debug()->info(
					'AJAX handler execution start',
					array(
						'handler' => get_class( $handler ),
						'action'  => $action,
					)
				);
			}

			// Nonce already verified above at line 185-187.
			// Extract and sanitize only allowed fields from $_POST - not the entire array.
			// This addresses WordPress.org requirements to process only required fields.
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above at line 187.
			$allowed_fields = WSSCD_Case_Converter::get_allowed_ajax_fields();
			$request_data   = WSSCD_Case_Converter::extract_and_sanitize( $allowed_fields, $_POST );
			$request_data   = array_merge(
				$request_data,
				array(
					'action' => $action,
					'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'POST',
				)
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$request_data = self::camel_to_snake_keys( $request_data );

			// Call handler - check if it extends WSSCD_Abstract_Ajax_Handler.

			if ( is_a( $handler, 'WSSCD_Abstract_Ajax_Handler' ) ) {
				// Set the current action if handler supports it (for multi-action handlers).
				if ( method_exists( $handler, 'set_action' ) ) {
					$handler->set_action( 'wsscd_' . $handler_action );
				}

				// New base class - use execute() method with built-in security.
				$result = $handler->execute( $request_data );
			} else {
				// Legacy handler - check if it expects parameters.
				$reflection = new ReflectionMethod( $handler, 'handle' );
				$params     = $reflection->getParameters();

				if ( count( $params ) > 0 ) {
					// Handler expects request data.
					$result = $handler->handle( $request_data );
				} else {
					// Legacy handler without parameters.
					$result = $handler->handle();
				}
			}

			if ( null !== $result ) {
				$duration = microtime( true ) - $start_time;

				if ( is_wp_error( $result ) ) {
					// Debug: Log WP_Error response.
					if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
						wsscd_debug_ajax_response(
							$action,
							array(
								'error' => $result->get_error_message(),
								'code'  => $result->get_error_code(),
							),
							false,
							$duration
						);
					}

					WSSCD_AJAX_Response::wp_error( $result );
				} elseif ( is_array( $result ) ) {
					if ( isset( $result['success'] ) && false === $result['success'] ) {
						// Debug: Log error response.
						if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
							wsscd_debug_ajax_response( $action, $result, false, $duration );
						}

						// Extract error message and code from nested 'error' array or top-level.
						$error_message = 'Request failed';
						$error_code    = 'request_failed';

						if ( isset( $result['error'] ) && is_array( $result['error'] ) ) {
							// Handler returned error in nested 'error' array structure.
							$error_message = isset( $result['error']['message'] ) ? $result['error']['message'] : $error_message;
							$error_code    = isset( $result['error']['code'] ) ? $result['error']['code'] : $error_code;
						} else {
							// Fallback to top-level keys.
							$error_message = isset( $result['message'] ) ? $result['message'] : $error_message;
							$error_code    = isset( $result['code'] ) ? $result['code'] : $error_code;
						}

						WSSCD_AJAX_Response::error(
							$error_message,
							$error_code,
							isset( $result['data'] ) ? $result['data'] : null
						);
					} else {
						// Debug: Log success response.
						if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
							wsscd_debug_ajax_response( $action, $result, true, $duration );
						}

						// CRITICAL FIX: Handlers already wrap responses with success/data structure.
						$response_data = isset( $result['data'] ) ? $result['data'] : $result;
						WSSCD_AJAX_Response::success( $response_data );
					}
				} else {
					// Debug: Log success response.
					if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
						wsscd_debug_ajax_response( $action, $result, true, $duration );
					}

					WSSCD_AJAX_Response::success( $result );
				}
			}
		} catch ( Exception $e ) {
			// Debug: Log exception.
			if ( function_exists( 'wsscd_debug_error' ) ) {
				wsscd_debug_error(
					'AJAX handler exception',
					$e,
					array(
						'action'  => $action,
						'handler' => isset( $handler ) ? get_class( $handler ) : 'unknown',
					)
				);
			}

			// Debug: Log error response.
			if ( function_exists( 'wsscd_debug_ajax_response' ) ) {
				wsscd_debug_ajax_response( $action, array( 'error' => $e->getMessage() ), false, microtime( true ) - $start_time );
			}

			WSSCD_AJAX_Response::error(
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
		WSSCD_AJAX_Response::unauthorized();
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
			// Wizard handlers.
			'save_step'                      => 'WSSCD_Save_Step_Handler',
			'load_data'                      => 'WSSCD_Load_Data_Handler',
			'load_session'                   => 'WSSCD_Load_Data_Handler', // Map load_session to same handler.
			'product_search'                 => 'WSSCD_Product_Search_Handler',
			'get_summary'                    => 'WSSCD_Get_Summary_Handler',
			'check_campaign_name'            => 'WSSCD_Check_Campaign_Name_Handler',
			'get_product_stats'              => 'WSSCD_Get_Product_Stats_Handler',
			'recover_session'                => 'WSSCD_Recover_Session_Handler',
			'health_check'                   => 'WSSCD_Health_Check_Handler',
			'check_session'                  => 'WSSCD_Check_Session_Handler',
			'session_status'                 => 'WSSCD_Session_Status_Handler',
			'check_conflicts'                => 'WSSCD_Check_Conflicts_Handler',
			'preview_coverage'               => 'WSSCD_Preview_Coverage_Handler',
			'campaign_health'                => 'WSSCD_Campaign_Health_Handler',
			'calculate_discount_impact'      => 'WSSCD_Calculate_Discount_Impact_Handler',
			'sale_items_filter'              => 'WSSCD_Sale_Items_Filter_Handler',
			'profit_margin_warning'          => 'WSSCD_Profit_Margin_Warning_Handler',
			'apply_recommendation'           => 'WSSCD_Apply_Recommendation_Handler',
			'occurrence_preview'             => 'WSSCD_Occurrence_Preview_Handler',

			// Campaign handlers.
			'get_active_campaigns'           => 'WSSCD_Get_Active_Campaigns_Handler',
			'campaign_overview'              => 'WSSCD_Campaign_Overview_Handler',
			'get_campaign_products'          => 'WSSCD_Get_Campaign_Products_Handler',

			// Contextual Sidebar handlers.
			'get_help_topic'                 => 'WSSCD_Sidebar_Ajax_Handler',

			// Debug handlers.
			'debug_log'                      => 'WSSCD_Ajax_Debug_Log',
			'log_console'                    => 'WSSCD_Console_Logger_Handler',
			'write_debug_log'                => 'WSSCD_Debug_Log_Handler',
			'log_viewer'                     => 'WSSCD_Log_Viewer_Handler',

			// Tools handlers.
			'export'                         => 'WSSCD_Import_Export_Handler',
			'import'                         => 'WSSCD_Import_Handler',
			'database_maintenance'           => 'WSSCD_Tools_Handler',
			'cache_management'               => 'WSSCD_Tools_Handler',
			'clear_cache'                    => 'WSSCD_Clear_Cache_Handler',

			// Draft handlers (consolidated).
			'complete_wizard'                => 'WSSCD_Draft_Handler',
			'save_draft'                     => 'WSSCD_Draft_Handler',
			'delete_draft'                   => 'WSSCD_Draft_Handler',
			'draft_list'                     => 'WSSCD_Draft_Handler',
			'draft_preview'                  => 'WSSCD_Draft_Handler',

			// Dashboard handlers.
			'main_dashboard_data'            => 'WSSCD_Main_Dashboard_Data_Handler',
			'get_planner_insights'           => 'WSSCD_Get_Planner_Insights_Handler',

			// Analytics handlers.
			'analytics_overview'             => 'WSSCD_Overview_Handler',
			'analytics_campaign_performance' => 'WSSCD_Campaign_Performance_Handler',
			'analytics_revenue_trend'        => 'WSSCD_Revenue_Trend_Handler',
			'analytics_top_products'         => 'WSSCD_Top_Products_Handler',
			'analytics_activity_feed'        => 'WSSCD_Activity_Feed_Handler',
			'analytics_export'               => 'WSSCD_Export_Handler',

			// Event tracking.
			'track_impression'               => 'WSSCD_Track_Impression_Handler',
			'track_click'                    => 'WSSCD_Track_Click_Handler',

			// Discount API handlers.
			'validate_discount_rules'        => 'WSSCD_Discount_API_Handler',
			'get_discount_preview'           => 'WSSCD_Discount_API_Handler',
			'calculate_discount_impact'      => 'WSSCD_Discount_API_Handler',

			// Email/Notification handlers.
			'send_test_email'                => 'WSSCD_Send_Test_Email_Handler',
			'test_provider_connection'       => 'WSSCD_Test_Provider_Connection_Handler',
			'process_queue'                  => 'WSSCD_Process_Queue_Handler',
			'retry_failed_emails'            => 'WSSCD_Retry_Failed_Emails_Handler',
			'clear_queue'                    => 'WSSCD_Clear_Queue_Handler',

			// License/Debug handlers.
			'clear_license_cache'            => 'WSSCD_Clear_License_Cache_Handler',
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
		if ( isset( $this->handler_instances[ $action ] ) ) {
			return $this->handler_instances[ $action ];
		}

		$handler_class = $this->handlers[ $action ];

		if ( ! class_exists( $handler_class ) ) {
			$file = $this->get_handler_file( $handler_class );

			// Security: Only load file if it is properly validated.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_readable -- Security check before requiring handler file.
			if ( $file && is_string( $file ) && file_exists( $file ) && is_readable( $file ) ) {
				// Additional security check: Verify file extension.
				if ( substr( $file, -4 ) === '.php' ) {
					require_once $file;
				}
			}
		}

		if ( class_exists( $handler_class ) ) {
			// Special handling for handlers that require dependencies.
			if ( 'WSSCD_Save_Step_Handler' === $handler_class ) {
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					$class_path = WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					if ( ! file_exists( $class_path ) ) {
						return null;
					}

					if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
						require_once $class_path;
					}

					if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
						return null;
					}

					try {
						$state_service = new WSSCD_Wizard_State_Service();
						if ( ! $state_service || ! method_exists( $state_service, 'initialize_with_intent' ) ) {
							return null;
						}
						// CRITICAL FIX: Check if we are in edit mode by reading from existing session.
						// AJAX requests do not have $_GET params, so we check the session data.
						$state_service->initialize_with_intent( 'continue' );

						// Now check if the session indicates edit mode.
						$campaign_id = $state_service->get( 'campaign_id', 0 );

						// If we have a campaign_id, assume edit mode and initialize Change Tracker.
						// The presence of campaign_id means we are editing an existing campaign.
						if ( $campaign_id > 0 ) {
							$state_service->initialize_with_intent( 'edit' );
						}
					} catch ( Exception $e ) {
						return null;
					}
				}

				// Verify state service is valid before using.
				if ( ! $state_service ) {
					return null;
				}

				$feature_gate = $container::get_service( 'feature_gate' );

				$idempotency_service = null;
				$transformer         = null;

				try {
					$idempotency_service = $container::get_service( 'idempotency_service' );
					$transformer         = $container::get_service( 'step_data_transformer' );
				} catch ( Exception $e ) {
					// Services will be created in handler constructor if not available.
				}

				// Save_Step_Handler with all services.
				$this->handler_instances[ $action ] = new $handler_class(
					$state_service,
					null,  // logger.
					$feature_gate,
					$idempotency_service,
					$transformer
				);
			} elseif ( 'WSSCD_Load_Data_Handler' === $handler_class ) {
				// Load Data handler requires state service.
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new WSSCD_Wizard_State_Service();
					$state_service->initialize_with_intent( 'continue' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'WSSCD_Session_Status_Handler' === $handler_class ) {
				// Session Status handler requires session service.
				$container       = Smart_Cycle_Discounts::get_instance();
				$session_service = $container::get_service( 'session_service' );

				if ( ! $session_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'WSSCD_Session_Service' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'utilities/class-session-service.php';
					}
					$session_service = new WSSCD_Session_Service();
				}

				$this->handler_instances[ $action ] = new $handler_class( $session_service );
			} elseif ( 'WSSCD_Apply_Recommendation_Handler' === $handler_class ) {
				// Apply Recommendation handler requires state service.
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new WSSCD_Wizard_State_Service();
					$state_service->initialize_with_intent( 'continue' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'WSSCD_Get_Summary_Handler' === $handler_class ) {
				// CRITICAL FIX: Get Summary handler requires state service with Change Tracker in edit mode.
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new WSSCD_Wizard_State_Service();
					$state_service->initialize_with_intent( 'continue' );
				}

				// CRITICAL: Always check if we are in edit mode and ensure Change Tracker is initialized.
				// This handles both fresh state service and container-provided service.
				$campaign_id = $state_service->get( 'campaign_id', 0 );

				// If we have a campaign_id but no Change Tracker, initialize edit mode.
				if ( $campaign_id > 0 && ! $state_service->get_change_tracker() ) {
					$state_service->initialize_with_intent( 'edit' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'WSSCD_Draft_Handler' === $handler_class ) {
				// Draft handler requires multiple services.
				$container = Smart_Cycle_Discounts::get_instance();

				$state_service = $container::get_service( 'wizard_state' );
				if ( ! $state_service ) {
					if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new WSSCD_Wizard_State_Service();
				}

				$campaign_manager = $container::get_service( 'campaign_manager' );
				if ( ! $campaign_manager ) {
					WSSCD_AJAX_Response::error(
						'service_unavailable',
						__( 'Campaign manager service not available', 'smart-cycle-discounts' )
					);
					return;
				}

				$compiler = $container::get_service( 'campaign_compiler' );
				if ( ! $compiler ) {
					if ( ! class_exists( 'WSSCD_Campaign_Compiler_Service' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
					}
					$compiler = new WSSCD_Campaign_Compiler_Service();
				}

				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'WSSCD_Logger' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new WSSCD_Logger();
				}

				$audit_logger = $container::get_service( 'audit_logger' );
				if ( ! $audit_logger && class_exists( 'WSSCD_Audit_Logger' ) ) {
					$audit_logger = new WSSCD_Audit_Logger();
				}

				$feature_gate = $container::get_service( 'feature_gate' );
				if ( ! $feature_gate && class_exists( 'WSSCD_Feature_Gate' ) ) {
					$feature_gate = new WSSCD_Feature_Gate();
				}

				$this->handler_instances[ $action ] = new $handler_class(
					$state_service,
					$campaign_manager,
					$compiler,
					$logger,
					$audit_logger,
					$feature_gate
				);
			} elseif ( in_array(
				$handler_class,
				array(
					'WSSCD_Overview_Handler',
					'WSSCD_Revenue_Trend_Handler',
					'WSSCD_Campaign_Performance_Handler',
					'WSSCD_Top_Products_Handler',
					'WSSCD_Activity_Feed_Handler',
					'WSSCD_Export_Handler',
				),
				true
			) ) {
				// Analytics handlers require metrics calculator, logger, and possibly other services.
				$container = Smart_Cycle_Discounts::get_instance();

				$metrics_calculator = $container::get_service( 'metrics_calculator' );
				if ( ! $metrics_calculator ) {
					WSSCD_AJAX_Response::error(
						'service_unavailable',
						__( 'Metrics calculator service not available', 'smart-cycle-discounts' )
					);
					return null;
				}

				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'WSSCD_Logger' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new WSSCD_Logger();
				}

				// Handle specific handler requirements.
				if ( 'WSSCD_Overview_Handler' === $handler_class ) {
					// Overview handler only needs metrics_calculator and logger.
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger );

				} elseif ( 'WSSCD_Revenue_Trend_Handler' === $handler_class ||
							'WSSCD_Campaign_Performance_Handler' === $handler_class ||
							'WSSCD_Top_Products_Handler' === $handler_class ) {
					// These handlers need analytics_collector.
					$analytics_collector = $container::get_service( 'analytics_collector' );
					if ( ! $analytics_collector ) {
						WSSCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Analytics collector service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $analytics_collector );

				} elseif ( 'WSSCD_Activity_Feed_Handler' === $handler_class ) {
					// Activity feed handler needs activity_tracker.
					$activity_tracker = $container::get_service( 'activity_tracker' );
					if ( ! $activity_tracker ) {
						WSSCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Activity tracker service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $activity_tracker );

				} elseif ( 'WSSCD_Export_Handler' === $handler_class ) {
					// Export handler needs export_service.
					$export_service = $container::get_service( 'export_service' );
					if ( ! $export_service ) {
						WSSCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Export service not available', 'smart-cycle-discounts' )
						);
						return null;
					}

					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $export_service );
				}
			} elseif ( 'WSSCD_Import_Export_Handler' === $handler_class ||
						'WSSCD_Import_Handler' === $handler_class ||
						'WSSCD_Tools_Handler' === $handler_class ||
						'WSSCD_Clear_Cache_Handler' === $handler_class ||
						'WSSCD_Clear_License_Cache_Handler' === $handler_class ) {
				// Tools and debug handlers require container and logger.
				$container = Smart_Cycle_Discounts::get_instance();

				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'WSSCD_Logger' ) ) {
						require_once WSSCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new WSSCD_Logger();
				}

				if ( 'WSSCD_Import_Export_Handler' === $handler_class ) {
					$feature_gate = $container::get_service( 'feature_gate' );
					if ( ! $feature_gate && class_exists( 'WSSCD_Feature_Gate' ) ) {
						$feature_gate = new WSSCD_Feature_Gate();
					}
					$this->handler_instances[ $action ] = new $handler_class( $container, $logger, $feature_gate );
				} else {
					$this->handler_instances[ $action ] = new $handler_class( $container, $logger );
				}
			} elseif ( 'WSSCD_Main_Dashboard_Data_Handler' === $handler_class ) {
				// Main dashboard data handler requires dashboard page and logger from service container.
				$container = Smart_Cycle_Discounts::get_instance();
				$handler   = $container::get_service( 'main_dashboard_data_handler' );

				if ( ! $handler ) {
					return null;
				}

				$this->handler_instances[ $action ] = $handler;
			} elseif ( 'WSSCD_Get_Planner_Insights_Handler' === $handler_class ) {
				// Timeline insights handler requires dashboard service.
				$container         = Smart_Cycle_Discounts::get_instance();
				$dashboard_service = $container::get_service( 'dashboard_service' );

				if ( ! $dashboard_service ) {
					return null;
				}

				$logger = $container::get_service( 'logger' );

				$this->handler_instances[ $action ] = new $handler_class( $dashboard_service, $logger );
		} elseif ( 'WSSCD_Campaign_Overview_Handler' === $handler_class ) {
			// Campaign overview handler requires campaign repository and panel component.
			$container = Smart_Cycle_Discounts::get_instance();

			$handler   = $container::get_service( 'campaign_overview_handler' );

			if ( ! $handler ) {
				// Fallback: manually instantiate with dependencies.
				$campaign_repository = $container::get_service( 'campaign_repository' );

				$panel               = $container::get_service( 'campaign_overview_panel' );

				$logger              = $container::get_service( 'logger' );

				if ( ! $campaign_repository || ! $panel ) {
					return null;
				}

				$handler = new $handler_class( $campaign_repository, $panel, $logger );
			}

			$this->handler_instances[ $action ] = $handler;
		} elseif ( 'WSSCD_Get_Campaign_Products_Handler' === $handler_class ) {
			// Get campaign products handler requires campaign repository.
			$container           = Smart_Cycle_Discounts::get_instance();
			$campaign_repository = $container::get_service( 'campaign_repository' );
			$logger              = $container::get_service( 'logger' );

			if ( ! $campaign_repository ) {
				return null;
			}

			$this->handler_instances[ $action ] = new $handler_class( $campaign_repository, $logger );
		} elseif ( 'WSSCD_Get_Active_Campaigns_Handler' === $handler_class ) {
			// Get Active Campaigns handler requires cache manager.
			$container     = Smart_Cycle_Discounts::get_instance();
			$cache_manager = $container::get_service( 'cache_manager' );
			$logger        = $container::get_service( 'logger' );

			if ( ! $cache_manager ) {
				return null;
			}

			$this->handler_instances[ $action ] = new $handler_class( $cache_manager, $logger );
			} elseif ( 'WSSCD_Occurrence_Preview_Handler' === $handler_class ) {
				// Occurrence Preview handler requires occurrence cache.
				$container        = Smart_Cycle_Discounts::get_instance();
				$occurrence_cache = $container::get_service( 'occurrence_cache' );
				$logger           = $container::get_service( 'logger' );

				if ( ! $occurrence_cache ) {
					return null;
				}

				$this->handler_instances[ $action ] = new $handler_class( $occurrence_cache, $logger );
			} else {
				// Default instantiation for handlers without dependencies.
				try {
					$this->handler_instances[ $action ] = new $handler_class();
				} catch ( Exception $e ) {
					return null;
				}
			}

			return $this->handler_instances[ $action ];
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
		// Security: Validate handler class name format.
		if ( ! preg_match( '/^WSSCD_[A-Za-z0-9_]+_Handler$/', $handler_class ) ) {
			return null;
		}

		$file_name = 'class-' . str_replace( '_', '-', strtolower( str_replace( 'WSSCD_', '', $handler_class ) ) ) . '.php';

		// Security: Validate file name format (prevent directory traversal).
		if ( ! preg_match( '/^class-[a-z0-9-]+\.php$/', $file_name ) ) {
			return null;
		}

		// Define allowed directories (absolute paths for security).
		$allowed_dirs = array(
			__DIR__ . '/handlers/',
			__DIR__ . '/analytics/',
			dirname( dirname( __DIR__ ) ) . '/core/validation/',
			dirname( dirname( __DIR__ ) ) . '/core/wizard/',
			dirname( dirname( __DIR__ ) ) . '/core/campaigns/',
			dirname( dirname( __DIR__ ) ) . '/core/analytics/',
		);

		foreach ( $allowed_dirs as $dir ) {
			$full_path = $dir . $file_name;

			// Security: Use realpath to prevent directory traversal.
			$real_path = realpath( $full_path );
			$real_dir  = realpath( $dir );

			// Verify the file exists and is within the allowed directory.
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
	 * Delegates to WSSCD_Case_Converter utility for actual conversion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $data    Data to convert.
	 * @param    string $path    Internal path tracking (unused, kept for BC).
	 * @return   mixed             Converted data.
	 */
	private static function camel_to_snake_keys( $data, $path = '' ) {
		// Ensure utility class is loaded.
		if ( ! class_exists( 'WSSCD_Case_Converter' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/utilities/class-case-converter.php';
		}

		return WSSCD_Case_Converter::camel_to_snake( $data );
	}

}
