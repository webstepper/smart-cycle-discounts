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
		// Load AJAX security class (used by abstract handler and router).
		if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
			require_once __DIR__ . '/class-ajax-security.php';
		}

		if ( ! class_exists( 'SCD_Abstract_Ajax_Handler' ) ) {
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
		// Register WordPress AJAX hooks for each handler.
		foreach ( $this->handlers as $action => $handler_class ) {
			add_action( 'wp_ajax_scd_' . $action, array( $this, 'route_request' ) );
			// add_action( 'wp_ajax_nopriv_scd_' . $action, array( $this, 'route_request' ) ).
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
		// 1. Custom scdAction parameter (unified endpoint sends this).
		// 2. Custom scd_action parameter (backward compatibility).
		// 3. WordPress standard action parameter (only for direct hook calls).
		//
		// When called via unified endpoint (wp_ajax_scd_ajax), the WordPress action.
		// parameter will always be 'scd_ajax', so we must check scdAction FIRST.
		//
		// NOTE: We must access $_POST/$_REQUEST here to determine the action BEFORE nonce verification.
		// because we need the action name to know which nonce to verify (chicken-and-egg problem).
		// This is safe because: 1) immediately sanitized, 2) only used for routing, 3) nonce verified at line 165.
		// before any handler processes user data.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Action extracted for routing only, nonce verified below at line 165
		$action = '';
		if ( isset( $_POST['scdAction'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['scdAction'] ) );
		} elseif ( isset( $_REQUEST['scdAction'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['scdAction'] ) );
		} elseif ( isset( $_POST['scd_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['scd_action'] ) );
		} elseif ( isset( $_REQUEST['scd_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['scd_action'] ) );
		} elseif ( isset( $_POST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		} elseif ( isset( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		}

		// Debug: Log AJAX request received (development only).
		if ( function_exists( 'scd_debug_ajax' ) ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ( isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '' );
			scd_debug_ajax( $action ? $action : 'no_action', $_POST, $nonce );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $action ) ) {
			// Debug: Log no action error.
			if ( function_exists( 'scd_debug_ajax_response' ) ) {
				scd_debug_ajax_response( 'no_action', array( 'error' => 'No action specified' ), false, microtime( true ) - $start_time );
			}

			SCD_AJAX_Response::error(
				'No action specified',
				'no_action'
			);
		}

		// Strip scd_ prefix for handler lookup if present.
		$handler_action = preg_replace( '/^scd_/', '', $action );

		if ( ! isset( $this->handlers[ $handler_action ] ) ) {
			if ( function_exists( 'scd_debug_ajax_response' ) ) {
				scd_debug_ajax_response( $action, array( 'error' => 'Invalid action' ), false, microtime( true ) - $start_time );
			}

			SCD_AJAX_Response::error(
				sprintf( 'Invalid action: %s', $action ),
				'invalid_action'
			);
		}

		// Centralized security validation - ensure we use full action name with scd_ prefix.
		if ( class_exists( 'SCD_Ajax_Security' ) ) {
			// Security expects the full action name with scd_ prefix.
			$full_action = 0 === strpos( $action, 'scd_' ) ? $action : 'scd_' . $action;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens inside verify_ajax_request()
			$security_check = SCD_Ajax_Security::verify_ajax_request( $full_action, $_POST );

			if ( is_wp_error( $security_check ) ) {
				// Debug: Log security failure.
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

		$handler = $this->get_handler_instance( $handler_action );

		if ( ! $handler ) {
			SCD_AJAX_Response::error(
				'Handler initialization failed',
				'handler_init_failed'
			);
		}

		// Execute handler.
		try {
			// Debug: Log handler execution start.
			if ( function_exists( 'scd_debug' ) && scd_debug() ) {
				scd_debug()->info(
					'AJAX handler execution start',
					array(
						'handler' => get_class( $handler ),
						'action'  => $action,
					)
				);
			}

			// Nonce already verified above at line 173.
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$request_data = array_merge(
				$_POST, // Input data from form submission.
				array(
					'action' => $action,
					'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'POST',
				)
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$request_data = self::camel_to_snake_keys( $request_data );

			// Call handler - check if it extends SCD_Abstract_Ajax_Handler.

			if ( is_a( $handler, 'SCD_Abstract_Ajax_Handler' ) ) {
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
				if ( is_array( $result ) ) {
					if ( isset( $result['success'] ) ) {
					}
					if ( isset( $result['message'] ) ) {
					}
				}
				$duration = microtime( true ) - $start_time;

				if ( is_wp_error( $result ) ) {
					// Debug: Log WP_Error response.
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
						// Debug: Log error response.
						if ( function_exists( 'scd_debug_ajax_response' ) ) {
							scd_debug_ajax_response( $action, $result, false, $duration );
						}

						SCD_AJAX_Response::error(
							isset( $result['message'] ) ? $result['message'] : 'Request failed',
							isset( $result['code'] ) ? $result['code'] : 'request_failed',
							isset( $result['data'] ) ? $result['data'] : null
						);
					} else {
						// Debug: Log success response.
						if ( function_exists( 'scd_debug_ajax_response' ) ) {
							scd_debug_ajax_response( $action, $result, true, $duration );
						}

						// CRITICAL FIX: Handlers already wrap responses with success/data structure.
						$response_data = isset( $result['data'] ) ? $result['data'] : $result;
						SCD_AJAX_Response::success( $response_data );
					}
				} else {
					// Debug: Log success response.
					if ( function_exists( 'scd_debug_ajax_response' ) ) {
						scd_debug_ajax_response( $action, $result, true, $duration );
					}

					SCD_AJAX_Response::success( $result );
				}
			}
		} catch ( Exception $e ) {
			// Debug: Log exception.
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

			// Debug: Log error response.
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
			// Wizard handlers.
			'save_step'                      => 'SCD_Save_Step_Handler',
			'load_data'                      => 'SCD_Load_Data_Handler',
			'load_session'                   => 'SCD_Load_Data_Handler', // Map load_session to same handler.
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
			'occurrence_preview'             => 'SCD_Occurrence_Preview_Handler',

			// Campaign handlers.
			'get_active_campaigns'           => 'SCD_Get_Active_Campaigns_Handler',
			'campaign_overview'              => 'SCD_Campaign_Overview_Handler',
			'get_campaign_products'          => 'SCD_Get_Campaign_Products_Handler',
			// Debug handlers.
			'debug_log'                      => 'SCD_Ajax_Debug_Log',
			'log_console'                    => 'SCD_Console_Logger_Handler',
			'write_debug_log'                => 'SCD_Debug_Log_Handler',
			'log_viewer'                     => 'SCD_Log_Viewer_Handler',

			// Tools handlers.
			'export'                         => 'SCD_Import_Export_Handler',
			'import'                         => 'SCD_Import_Handler',
			'database_maintenance'           => 'SCD_Tools_Handler',
			'cache_management'               => 'SCD_Tools_Handler',
			'clear_cache'                    => 'SCD_Clear_Cache_Handler',

			// Draft handlers (consolidated).
			'complete_wizard'                => 'SCD_Draft_Handler',
			'save_draft'                     => 'SCD_Draft_Handler',
			'delete_draft'                   => 'SCD_Draft_Handler',
			'draft_list'                     => 'SCD_Draft_Handler',
			'draft_preview'                  => 'SCD_Draft_Handler',

			// Dashboard handlers.
			'main_dashboard_data'            => 'SCD_Main_Dashboard_Data_Handler',
			'get_planner_insights'           => 'SCD_Get_Planner_Insights_Handler',

			// Analytics handlers.
			'analytics_overview'             => 'SCD_Overview_Handler',
			'analytics_campaign_performance' => 'SCD_Campaign_Performance_Handler',
			'analytics_revenue_trend'        => 'SCD_Revenue_Trend_Handler',
			'analytics_top_products'         => 'SCD_Top_Products_Handler',
			'analytics_activity_feed'        => 'SCD_Activity_Feed_Handler',
			'analytics_export'               => 'SCD_Export_Handler',

			// Event tracking.
			'track_impression'               => 'SCD_Track_Impression_Handler',
			'track_click'                    => 'SCD_Track_Click_Handler',

			// Discount API handlers.
			'validate_discount_rules'        => 'SCD_Discount_API_Handler',
			'get_discount_preview'           => 'SCD_Discount_API_Handler',
			'calculate_discount_impact'      => 'SCD_Discount_API_Handler',

			// Email/Notification handlers.
			'send_test_email'                => 'SCD_Send_Test_Email_Handler',
			'test_provider_connection'       => 'SCD_Test_Provider_Connection_Handler',
			'process_queue'                  => 'SCD_Process_Queue_Handler',
			'retry_failed_emails'            => 'SCD_Retry_Failed_Emails_Handler',
			'clear_queue'                    => 'SCD_Clear_Queue_Handler',

			// License/Debug handlers.
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
		if ( isset( $this->handler_instances[ $action ] ) ) {
			return $this->handler_instances[ $action ];
		}

		$handler_class = $this->handlers[ $action ];

		if ( ! class_exists( $handler_class ) ) {
			$file = $this->get_handler_file( $handler_class );

			// Security: Only load file if it is properly validated.
			if ( $file && is_string( $file ) && file_exists( $file ) && is_readable( $file ) ) {
				// Additional security check: Verify file extension.
				if ( substr( $file, -4 ) === '.php' ) {
					require_once $file;
				}
			}
		}

		if ( class_exists( $handler_class ) ) {
			// Special handling for handlers that require dependencies.
			if ( 'SCD_Save_Step_Handler' === $handler_class ) {
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					$class_path = SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					if ( ! file_exists( $class_path ) ) {
						return null;
					}

					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once $class_path;
					}

					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						return null;
					}

					try {
						$state_service = new SCD_Wizard_State_Service();
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
			} elseif ( 'SCD_Load_Data_Handler' === $handler_class ) {
				// Load Data handler requires state service.
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
					$state_service->initialize_with_intent( 'continue' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'SCD_Session_Status_Handler' === $handler_class ) {
				// Session Status handler requires session service.
				$container       = Smart_Cycle_Discounts::get_instance();
				$session_service = $container::get_service( 'session_service' );

				if ( ! $session_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'SCD_Session_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-session-service.php';
					}
					$session_service = new SCD_Session_Service();
				}

				$this->handler_instances[ $action ] = new $handler_class( $session_service );
			} elseif ( 'SCD_Apply_Recommendation_Handler' === $handler_class ) {
				// Apply Recommendation handler requires state service.
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
					$state_service->initialize_with_intent( 'continue' );
				}

				$this->handler_instances[ $action ] = new $handler_class( $state_service );
			} elseif ( 'SCD_Get_Summary_Handler' === $handler_class ) {
				// CRITICAL FIX: Get Summary handler requires state service with Change Tracker in edit mode.
				$container     = Smart_Cycle_Discounts::get_instance();
				$state_service = $container::get_service( 'wizard_state' );

				if ( ! $state_service ) {
					// If service not available, create it.
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
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
			} elseif ( 'SCD_Draft_Handler' === $handler_class ) {
				// Draft handler requires multiple services.
				$container = Smart_Cycle_Discounts::get_instance();

				$state_service = $container::get_service( 'wizard_state' );
				if ( ! $state_service ) {
					if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
					}
					$state_service = new SCD_Wizard_State_Service();
				}

				$campaign_manager = $container::get_service( 'campaign_manager' );
				if ( ! $campaign_manager ) {
					SCD_AJAX_Response::error(
						'service_unavailable',
						__( 'Campaign manager service not available', 'smart-cycle-discounts' )
					);
					return;
				}

				$compiler = $container::get_service( 'campaign_compiler' );
				if ( ! $compiler ) {
					if ( ! class_exists( 'SCD_Campaign_Compiler_Service' ) ) {
						require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
					}
					$compiler = new SCD_Campaign_Compiler_Service();
				}

				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new SCD_Logger();
				}

				$audit_logger = $container::get_service( 'audit_logger' );
				if ( ! $audit_logger && class_exists( 'SCD_Audit_Logger' ) ) {
					$audit_logger = new SCD_Audit_Logger();
				}

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
			} elseif ( in_array(
				$handler_class,
				array(
					'SCD_Overview_Handler',
					'SCD_Revenue_Trend_Handler',
					'SCD_Campaign_Performance_Handler',
					'SCD_Top_Products_Handler',
					'SCD_Activity_Feed_Handler',
					'SCD_Export_Handler',
					'SCD_Refresh_Cache_Handler',
				),
				true
			) ) {
				// Analytics handlers require metrics calculator, logger, and possibly other services.
				$container = Smart_Cycle_Discounts::get_instance();

				$metrics_calculator = $container::get_service( 'metrics_calculator' );
				if ( ! $metrics_calculator ) {
					SCD_AJAX_Response::error(
						'service_unavailable',
						__( 'Metrics calculator service not available', 'smart-cycle-discounts' )
					);
					return null;
				}

				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new SCD_Logger();
				}

				// Handle specific handler requirements.
				if ( 'SCD_Overview_Handler' === $handler_class ) {
					// Overview handler only needs metrics_calculator and logger.
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger );

				} elseif ( 'SCD_Revenue_Trend_Handler' === $handler_class ||
							'SCD_Campaign_Performance_Handler' === $handler_class ||
							'SCD_Top_Products_Handler' === $handler_class ) {
					// These handlers need analytics_collector.
					$analytics_collector = $container::get_service( 'analytics_collector' );
					if ( ! $analytics_collector ) {
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Analytics collector service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $analytics_collector );

				} elseif ( 'SCD_Activity_Feed_Handler' === $handler_class ) {
					// Activity feed handler needs activity_tracker.
					$activity_tracker = $container::get_service( 'activity_tracker' );
					if ( ! $activity_tracker ) {
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Activity tracker service not available', 'smart-cycle-discounts' )
						);
						return null;
					}
					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $activity_tracker );

				} elseif ( 'SCD_Export_Handler' === $handler_class ) {
					// Export handler needs export_service.
					$export_service = $container::get_service( 'export_service' );
					if ( ! $export_service ) {
						SCD_AJAX_Response::error(
							'service_unavailable',
							__( 'Export service not available', 'smart-cycle-discounts' )
						);
						return null;
					}

					$this->handler_instances[ $action ] = new $handler_class( $metrics_calculator, $logger, $export_service );

				} elseif ( 'SCD_Refresh_Cache_Handler' === $handler_class ) {
					// Refresh cache handler needs cache_manager.
					$cache_manager = $container::get_service( 'cache_manager' );
					if ( ! $cache_manager ) {
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
				// Tools and debug handlers require container and logger.
				$container = Smart_Cycle_Discounts::get_instance();

				$logger = $container::get_service( 'logger' );
				if ( ! $logger ) {
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					$logger = new SCD_Logger();
				}

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
				// Main dashboard data handler requires dashboard page and logger from service container.
				$container = Smart_Cycle_Discounts::get_instance();
				$handler   = $container::get_service( 'main_dashboard_data_handler' );

				if ( ! $handler ) {
					return null;
				}

				$this->handler_instances[ $action ] = $handler;
			} elseif ( 'SCD_Get_Planner_Insights_Handler' === $handler_class ) {
				// Timeline insights handler requires dashboard service.
				$container         = Smart_Cycle_Discounts::get_instance();
				$dashboard_service = $container::get_service( 'dashboard_service' );

				if ( ! $dashboard_service ) {
					return null;
				}

				$logger = $container::get_service( 'logger' );

				$this->handler_instances[ $action ] = new $handler_class( $dashboard_service, $logger );
		} elseif ( 'SCD_Campaign_Overview_Handler' === $handler_class ) {
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
		} elseif ( 'SCD_Get_Campaign_Products_Handler' === $handler_class ) {
			// Get campaign products handler requires campaign repository.
			$container           = Smart_Cycle_Discounts::get_instance();
			$campaign_repository = $container::get_service( 'campaign_repository' );
			$logger              = $container::get_service( 'logger' );

			if ( ! $campaign_repository ) {
				return null;
			}

			$this->handler_instances[ $action ] = new $handler_class( $campaign_repository, $logger );
		} elseif ( 'SCD_Get_Active_Campaigns_Handler' === $handler_class ) {
			// Get Active Campaigns handler requires cache manager.
			$container     = Smart_Cycle_Discounts::get_instance();
			$cache_manager = $container::get_service( 'cache_manager' );
			$logger        = $container::get_service( 'logger' );

			if ( ! $cache_manager ) {
				return null;
			}

			$this->handler_instances[ $action ] = new $handler_class( $cache_manager, $logger );
			} elseif ( 'SCD_Occurrence_Preview_Handler' === $handler_class ) {
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
		if ( ! preg_match( '/^SCD_[A-Za-z0-9_]+_Handler$/', $handler_class ) ) {
			return null;
		}

		$file_name = 'class-' . str_replace( '_', '-', strtolower( str_replace( 'SCD_', '', $handler_class ) ) ) . '.php';

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
	 * Delegates to SCD_Case_Converter utility for actual conversion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $data    Data to convert.
	 * @param    string $path    Internal path tracking (unused, kept for BC).
	 * @return   mixed             Converted data.
	 */
	private static function camel_to_snake_keys( $data, $path = '' ) {
		// Ensure utility class is loaded.
		if ( ! class_exists( 'SCD_Case_Converter' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/utilities/class-case-converter.php';
		}

		return SCD_Case_Converter::camel_to_snake( $data );
	}
}
