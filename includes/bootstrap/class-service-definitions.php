<?php
/**
 * Service Definitions Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/bootstrap/class-service-definitions.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Service Definitions Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Service_Definitions {

	/**
	 * Get all service definitions.
	 *
	 * @since    1.0.0
	 * @return   array    Service definitions.
	 */
	public static function get_definitions(): array {
		return array(
			// Core Services
			'logger'                       => array(
				'class'     => 'SCD_Logger',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Logger();
				},
			),

			'action_scheduler'             => array(
				'class'        => 'SCD_Action_Scheduler_Service',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Action_Scheduler_Service(
						$container->get( 'logger' )
					);
				},
			),

			'debug_logger'                 => array(
				'class'     => 'SCD_Debug_Logger',
				'singleton' => true,
				'factory'   => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'utilities/class-debug-logger.php';
					return new SCD_Debug_Logger();
				},
			),

			'debug_console'                => array(
				'class'        => 'SCD_Debug_Console',
				'singleton'    => true,
				'dependencies' => array(),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'utilities/class-debug-console.php';
					return new SCD_Debug_Console();
				},
			),

			'error_handler'                => array(
				'class'        => 'SCD_Error_Handler',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Error_Handler( $container->get( 'logger' ) );
				},
			),

			'cache_manager'                => array(
				'class'     => 'SCD_Cache_Manager',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Cache_Manager();
				},
			),

			// Alias for backward compatibility
			'cache'                        => array(
				'class'        => 'SCD_Cache_Manager',
				'singleton'    => true,
				'dependencies' => array( 'cache_manager' ),
				'factory'      => function ( $container ) {
					return $container->get( 'cache_manager' );
				},
			),

			'reference_data_cache'         => array(
				'class'        => 'SCD_Reference_Data_Cache',
				'singleton'    => true,
				'dependencies' => array( 'cache_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Reference_Data_Cache( $container->get( 'cache_manager' ) );
				},
			),

			'database_manager'             => array(
				'class'     => 'SCD_Database_Manager',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Database_Manager();
				},
			),

			'security_manager'             => array(
				'class'        => 'SCD_Security_Manager',
				'singleton'    => true,
				'dependencies' => array( 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_Security_Manager( $container );
				},
			),

			// Campaign Model Services (New modular components)

			'campaign.calculator'          => array(
				'class'        => 'SCD_Campaign_Calculator',
				'singleton'    => true,
				'dependencies' => array( 'analytics_repository', 'product_selector' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Calculator(
						$container->get( 'analytics_repository' ),
						$container->get( 'product_selector' )
					);
				},
			),

			'campaign.formatter'           => array(
				'class'     => 'SCD_Campaign_Formatter',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Campaign_Formatter();
				},
			),

			'campaign.state_manager'       => array(
				'class'        => 'SCD_Campaign_State_Manager',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_State_Manager(
						$container->get( 'logger' ),
						null // Event dispatcher if available
					);
				},
			),

			'campaign_health_service'      => array(
				'class'        => 'SCD_Campaign_Health_Service',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'recurring_handler' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Health_Service(
						$container->get( 'logger' ),
						$container->get( 'recurring_handler' )
					);
				},
			),


			// Repository Services
			'campaign_repository'          => array(
				'class'        => 'SCD_Campaign_Repository',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'cache_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Repository(
						$container->get( 'database_manager' ),
						$container->get( 'cache_manager' )
					);
				},
			),

		'campaign_conditions_repository' => array(
			'class'        => 'SCD_Campaign_Conditions_Repository',
			'singleton'    => true,
			'dependencies' => array( 'database_manager' ),
			'factory'      => function ( $container ) {
				require_once SCD_INCLUDES_DIR . 'database/repositories/class-campaign-conditions-repository.php';
				return new SCD_Campaign_Conditions_Repository(
					$container->get( 'database_manager' )
				);
			},
		),

			'discount_repository'          => array(
				'class'        => 'SCD_Discount_Repository',
				'singleton'    => true,
				'dependencies' => array( 'database_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Discount_Repository( $container->get( 'database_manager' ) );
				},
			),

			'analytics_repository'         => array(
				'class'        => 'SCD_Analytics_Repository',
				'singleton'    => true,
				'dependencies' => array( 'database_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Analytics_Repository( $container->get( 'database_manager' ) );
				},
			),

			'customer_usage_repository'    => array(
				'class'        => 'SCD_Customer_Usage_Repository',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'logger', 'cache' ),
				'factory'      => function ( $container ) {
					return new SCD_Customer_Usage_Repository(
						$container->get( 'database_manager' ),
						$container->get( 'logger' ),
						$container->get( 'cache' )
					);
				},
			),

			'session_service'              => array(
				'class'     => 'SCD_Session_Service',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Session_Service();
				},
			),

			// Business Logic Services
			'campaign_manager'             => array(
				'class'        => 'SCD_Campaign_Manager',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository', 'logger', 'cache_manager', 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Manager(
						$container->get( 'campaign_repository' ),
						$container->get( 'logger' ),
						$container->get( 'cache_manager' ),
						$container
					);
				},
			),

			'discount_engine'              => array(
				'class'        => 'SCD_Discount_Engine',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'cache_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Discount_Engine(
						$container->get( 'logger' ),
						$container->get( 'cache_manager' )
					);
				},
			),

			'customer_usage_manager'       => array(
				'class'        => 'SCD_Customer_Usage_Manager',
				'singleton'    => true,
				'dependencies' => array( 'customer_usage_repository', 'logger', 'session_service' ),
				'factory'      => function ( $container ) {
					return new SCD_Customer_Usage_Manager(
						$container->get( 'customer_usage_repository' ),
						$container->get( 'logger' ),
						$container->get( 'session_service' )
					);
				},
			),

			'discount_rules_enforcer'      => array(
				'class'        => 'SCD_Discount_Rules_Enforcer',
				'singleton'    => true,
				'dependencies' => array( 'customer_usage_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Discount_Rules_Enforcer(
						$container->get( 'customer_usage_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			'cron_scheduler'               => array(
				'class'        => 'SCD_Cron_Scheduler',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'action_scheduler', 'customer_usage_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Cron_Scheduler(
						$container->get( 'logger' ),
						$container->get( 'action_scheduler' ),
						$container->get( 'customer_usage_manager' )
					);
				},
			),

			'discount_applicator'          => array(
				'class'        => 'SCD_Discount_Applicator',
				'singleton'    => true,
				'dependencies' => array( 'discount_engine', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Discount_Applicator(
						$container->get( 'discount_engine' ),
						$container->get( 'logger' )
					);
				},
			),

			'occurrence_cache'             => array(
				'class'        => 'SCD_Occurrence_Cache',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'core/campaigns/class-occurrence-cache.php';
					return new SCD_Occurrence_Cache(
						$container->get( 'logger' )
					);
				},
			),

			'recurring_handler'            => array(
				'class'        => 'SCD_Recurring_Handler',
				'singleton'    => true,
				'dependencies' => array( 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_Recurring_Handler( $container );
				},
			),

			// Admin Services
			'admin_manager'                => array(
				'class'        => 'SCD_Admin_Manager',
				'singleton'    => true,
				'dependencies' => array( 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_Admin_Manager( $container );
				},
			),

			'menu_manager'                 => array(
				'class'        => 'SCD_Menu_Manager',
				'singleton'    => true,
				'dependencies' => array( 'capability_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Menu_Manager(
						$container->get( 'capability_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			'ajax_router'                  => array(
				'class'     => 'SCD_Ajax_Router',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Ajax_Router();
				},
			),

			'admin_asset_manager'          => array(
				'class'        => 'SCD_Admin_Asset_Manager',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Admin_Asset_Manager(
						$container->get( 'logger' ),
						SCD_VERSION,
						SCD_PLUGIN_URL
					);
				},
			),

			'capability_manager'           => array(
				'class'        => 'SCD_Admin_Capability_Manager',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Admin_Capability_Manager( $container->get( 'logger' ) );
				},
			),

			// Licensing Services
			'feature_gate'                 => array(
				'class'        => 'SCD_Feature_Gate',
				'singleton'    => true,
				'dependencies' => array(),
				'factory'      => function ( $container ) {
					return new SCD_Feature_Gate();
				},
			),

			'license_manager'              => array(
				'class'        => 'SCD_License_Manager',
				'singleton'    => true,
				'dependencies' => array(),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/licensing/class-license-manager.php';
					return SCD_License_Manager::instance();
				},
			),

			'license_notices'              => array(
				'class'        => 'SCD_License_Notices',
				'singleton'    => true,
				'dependencies' => array(),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/licensing/class-license-notices.php';
					return new SCD_License_Notices();
				},
			),

			'upgrade_prompt_manager'       => array(
				'class'        => 'SCD_Upgrade_Prompt_Manager',
				'singleton'    => true,
				'dependencies' => array( 'feature_gate' ),
				'factory'      => function ( $container ) {
					return new SCD_Upgrade_Prompt_Manager(
						$container->get( 'feature_gate' )
					);
				},
			),

			'campaigns_page'               => array(
				'class'        => 'SCD_Campaigns_Page',
				'singleton'    => true,
				'dependencies' => array( 'campaign_manager', 'capability_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaigns_Page( $container );
				},
			),

			'tools_page'                   => array(
				'class'        => 'SCD_Tools_Page',
				'singleton'    => true,
				'dependencies' => array( 'container', 'logger', 'feature_gate' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/pages/class-tools-page.php';
					return new SCD_Tools_Page(
						$container,
						$container->get( 'logger' ),
						$container->get( 'feature_gate' )
					);
				},
			),

			// Notifications Page Services
			'notifications_page'           => array(
				'class'        => 'SCD_Notifications_Page',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'container', 'feature_gate' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/pages/notifications/class-notifications-page.php';
					return new SCD_Notifications_Page(
						$container->get( 'logger' ),
						$container,
						$container->get( 'feature_gate' )
					);
				},
			),

			'notifications_settings_tab'   => array(
				'class'        => 'SCD_Notifications_Settings_Tab',
				'singleton'    => true,
				'dependencies' => array( 'notifications_page', 'logger' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/pages/notifications/class-notifications-tab-base.php';
					require_once SCD_INCLUDES_DIR . 'admin/pages/notifications/tabs/class-notifications-settings-tab.php';
					return new SCD_Notifications_Settings_Tab(
						'settings',
						$container->get( 'notifications_page' ),
						$container->get( 'logger' )
					);
				},
			),

			'notifications_queue_tab'      => array(
				'class'        => 'SCD_Queue_Status_Tab',
				'singleton'    => true,
				'dependencies' => array( 'notifications_page', 'logger', 'container' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/pages/notifications/class-notifications-tab-base.php';
					require_once SCD_INCLUDES_DIR . 'admin/pages/notifications/tabs/class-queue-status-tab.php';
					return new SCD_Queue_Status_Tab(
						'queue',
						$container->get( 'notifications_page' ),
						$container->get( 'logger' ),
						$container
					);
				},
			),

			// Settings Services
			'settings_manager'             => array(
				'class'        => 'SCD_Settings_Manager',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'container' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/settings/class-settings-manager.php';
					return new SCD_Settings_Manager( $container->get( 'logger' ), $container );
				},
			),

			'settings_general'             => array(
				'class'        => 'SCD_General_Settings',
				'singleton'    => true,
				'dependencies' => array( 'settings_manager', 'logger', 'container' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/settings/class-settings-page-base.php';
					require_once SCD_INCLUDES_DIR . 'admin/settings/tabs/class-general-settings.php';
					return new SCD_General_Settings(
						$container->get( 'settings_manager' ),
						$container->get( 'logger' ),
						$container
					);
				},
			),

			'settings_advanced'            => array(
				'class'        => 'SCD_Advanced_Settings',
				'singleton'    => true,
				'dependencies' => array( 'settings_manager', 'logger' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'admin/settings/class-settings-page-base.php';
					require_once SCD_INCLUDES_DIR . 'admin/settings/tabs/class-advanced-settings.php';
					return new SCD_Advanced_Settings(
						'advanced',
						$container->get( 'settings_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			// Campaign Page Services
			'campaign_view_renderer'       => array(
				'class'        => 'SCD_Campaign_View_Renderer',
				'singleton'    => true,
				'dependencies' => array( 'template_loader' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_View_Renderer( $container->get( 'template_loader' ) );
				},
			),

			'campaign_list_controller'     => array(
				'class'        => 'SCD_Campaign_List_Controller',
				'singleton'    => false,
				'dependencies' => array( 'campaign_manager', 'capability_manager', 'logger', 'wizard_state_service', 'feature_gate' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_List_Controller(
						$container->get( 'campaign_manager' ),
						$container->get( 'capability_manager' ),
						$container->get( 'logger' ),
						$container->get( 'wizard_state_service' ),
						$container->get( 'feature_gate' )
					);
				},
			),

			'campaign_edit_controller'     => array(
				'class'        => 'SCD_Campaign_Edit_Controller',
				'singleton'    => false,
				'dependencies' => array( 'cache_manager', 'campaign_manager', 'capability_manager', 'logger', 'campaign_view_renderer' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Edit_Controller(
						$container->get( 'cache_manager' ),
						$container->get( 'campaign_manager' ),
						$container->get( 'capability_manager' ),
						$container->get( 'logger' ),
						$container->get( 'campaign_view_renderer' )
					);
				},
			),

			'campaign_wizard_controller'   => array(
				'class'        => 'SCD_Campaign_Wizard_Controller',
				'singleton'    => false,
				'dependencies' => array( 'campaign_manager', 'capability_manager', 'logger', 'wizard_state_service', 'feature_gate' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Wizard_Controller(
						$container->get( 'campaign_manager' ),
						$container->get( 'capability_manager' ),
						$container->get( 'logger' ),
						$container->get( 'wizard_state_service' ),
						$container->get( 'feature_gate' )
					);
				},
			),

			'campaign_action_handler'      => array(
				'class'        => 'SCD_Campaign_Action_Handler',
				'singleton'    => false,
				'dependencies' => array( 'campaign_manager', 'capability_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Action_Handler(
						$container->get( 'campaign_manager' ),
						$container->get( 'capability_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			// Wizard Services

			'wizard_state_service'         => array(
				'class'     => 'SCD_Wizard_State_Service',
				'singleton' => true,
				'factory'   => function ( $container ) {
					try {
						return new SCD_Wizard_State_Service();
					} catch ( Throwable $e ) {
						throw $e;
					}
				},
			),

			// Contextual Sidebar System
			'sidebar_help_topics'          => array(
				'class'     => 'SCD_Sidebar_Help_Topics',
				'singleton' => false,
				'factory'   => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'core/wizard/class-sidebar-help-topics.php';
					return new SCD_Sidebar_Help_Topics();
				},
			),

			'sidebar_progress'             => array(
				'class'     => 'SCD_Sidebar_Progress',
				'singleton' => false,
				'factory'   => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'core/wizard/class-sidebar-progress.php';
					return new SCD_Sidebar_Progress();
				},
			),

			'sidebar_quick_actions'        => array(
				'class'     => 'SCD_Sidebar_Quick_Actions',
				'singleton' => false,
				'factory'   => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'core/wizard/class-sidebar-quick-actions.php';
					return new SCD_Sidebar_Quick_Actions();
				},
			),

			'sidebar_renderer'             => array(
				'class'     => 'SCD_Sidebar_Renderer',
				'singleton' => false,
				'factory'   => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'core/wizard/class-sidebar-renderer.php';
					return new SCD_Sidebar_Renderer();
				},
			),

			'idempotency_service'          => array(
				'class'        => 'SCD_Idempotency_Service',
				'singleton'    => false,
				'dependencies' => array( 'cache_manager', 'wizard_state_service' ),
				'factory'      => function ( $container ) {
					return new SCD_Idempotency_Service(
						$container->get( 'cache_manager' ),
						$container->get( 'wizard_state_service' )
					);
				},
			),

			'step_data_transformer'        => array(
				'class'     => 'SCD_Step_Data_Transformer',
				'singleton' => false,
				'factory'   => function ( $container ) {
					return new SCD_Step_Data_Transformer();
				},
			),

			'wizard_manager'               => array(
				'class'        => 'SCD_Wizard_Manager',
				'singleton'    => true,
				'dependencies' => array( 'wizard_state_service', 'security_manager', 'campaign_repository' ),
				'factory'      => function ( $container ) {
					return new SCD_Wizard_Manager(
						$container->get( 'wizard_state_service' ),
						$container->get( 'security_manager' ),
						$container->get( 'campaign_repository' )
					);
				},
			),

			// Template Services
			'template_loader'              => array(
				'class'        => 'SCD_Template_Loader',
				'singleton'    => true,
				'dependencies' => array(),
				'factory'      => function ( $container ) {
					return new SCD_Template_Loader();
				},
			),

			// Frontend Services
			'frontend_manager'             => array(
				'class'        => 'SCD_Frontend_Manager',
				'singleton'    => true,
				'dependencies' => array( 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_Frontend_Manager( 'smart-cycle-discounts', SCD_VERSION, $container );
				},
			),

			'frontend_asset_manager'       => array(
				'class'        => 'SCD_Frontend_Asset_Manager',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Frontend_Asset_Manager(
						'smart-cycle-discounts',
						SCD_VERSION,
						$container->get( 'logger' )
					);
				},
			),

			'discount_display_rules'       => array(
				'class'     => 'SCD_Discount_Display_Rules',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Discount_Display_Rules();
				},
			),

			'discount_display'             => array(
				'class'        => 'SCD_Discount_Display',
				'singleton'    => true,
				'dependencies' => array( 'discount_engine', 'campaign_manager', 'discount_display_rules' ),
				'factory'      => function ( $container ) {
					return new SCD_Discount_Display(
						$container->get( 'discount_engine' ),
						$container->get( 'campaign_manager' ),
						$container->get( 'discount_display_rules' )
					);
				},
			),

			'countdown_timer'              => array(
				'class'     => 'SCD_Countdown_Timer',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Countdown_Timer();
				},
			),

			'shortcodes'                   => array(
				'class'        => 'SCD_Shortcodes',
				'singleton'    => true,
				'dependencies' => array( 'discount_display', 'campaign_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Shortcodes(
						$container->get( 'discount_display' ),
						$container->get( 'campaign_manager' )
					);
				},
			),

			// Integration Services
			'woocommerce_integration'      => array(
				'class'        => 'SCD_WooCommerce_Integration',
				'singleton'    => true,
				'dependencies' => array( 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_WooCommerce_Integration( $container );
				},
			),

			// Alias for interface-based dependency injection
			'ecommerce_integration'        => array(
				'class'     => 'SCD_WooCommerce_Integration',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return $container->get( 'woocommerce_integration' );
				},
			),

			'integration_manager'          => array(
				'class'        => 'SCD_Integration_Manager',
				'singleton'    => true,
				'dependencies' => array( 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_Integration_Manager( $container );
				},
			),

			'email_manager'                => array(
				'class'        => 'SCD_Email_Manager',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'campaign_manager', 'action_scheduler', 'feature_gate', 'analytics_repository' ),
				'factory'      => function ( $container ) {
					require_once SCD_INCLUDES_DIR . 'integrations/email/class-email-manager.php';
					return new SCD_Email_Manager(
						$container->get( 'logger' ),
						$container->get( 'campaign_manager' ),
						$container->get( 'action_scheduler' ),
						$container->get( 'feature_gate' ),
						$container->get( 'analytics_repository' )
					);
				},
			),

			'alert_monitor'                => array(
				'class'        => 'SCD_Alert_Monitor',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'campaign_manager', 'analytics_repository', 'action_scheduler', 'feature_gate' ),
				'factory'      => function ( $container ) {
					return new SCD_Alert_Monitor(
						$container->get( 'logger' ),
						$container->get( 'campaign_manager' ),
						$container->get( 'analytics_repository' ),
						$container->get( 'action_scheduler' ),
						$container->get( 'feature_gate' )
					);
				},
			),

			// API Services
			'rest_api_manager'             => array(
				'class'        => 'SCD_REST_API_Manager',
				'singleton'    => true,
				'dependencies' => array( 'cache_manager', 'container' ),
				'factory'      => function ( $container ) {
					return new SCD_REST_API_Manager( $container );
				},
			),

			// Security Services

			'nonce_manager'                => array(
				'class'     => 'SCD_Nonce_Manager',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Nonce_Manager();
				},
			),

			'rate_limiter'                 => array(
				'class'        => 'SCD_Rate_Limiter',
				'singleton'    => true,
				'dependencies' => array( 'cache_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Rate_Limiter( $container->get( 'cache_manager' ) );
				},
			),

			'audit_logger'                 => array(
				'class'        => 'SCD_Audit_Logger',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Audit_Logger( $container->get( 'logger' ) );
				},
			),

			// Analytics Services
			'analytics_collector'          => array(
				'class'        => 'SCD_Analytics_Collector',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'cache_manager', 'logger', 'ecommerce_integration' ),
				'factory'      => function ( $container ) {
					return new SCD_Analytics_Collector(
						$container->get( 'database_manager' ),
						$container->get( 'cache_manager' ),
						$container->get( 'logger' ),
						$container->get( 'ecommerce_integration' )
					);
				},
			),

			'metrics_calculator'           => array(
				'class'        => 'SCD_Metrics_Calculator',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'cache_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Metrics_Calculator(
						$container->get( 'database_manager' ),
						$container->get( 'cache_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			'report_generator'             => array(
				'class'        => 'SCD_Report_Generator',
				'singleton'    => true,
				'dependencies' => array( 'analytics_collector', 'metrics_calculator', 'campaign_manager', 'cache_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Report_Generator(
						$container->get( 'analytics_collector' ),
						$container->get( 'metrics_calculator' ),
						$container->get( 'campaign_manager' ),
						$container->get( 'cache_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			'chart_renderer'               => array(
				'class'        => 'SCD_Chart_Renderer',
				'singleton'    => true,
				'dependencies' => array( 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Chart_Renderer(
						$container->get( 'logger' )
					);
				},
			),

			'analytics_dashboard'          => array(
				'class'        => 'SCD_Analytics_Dashboard',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'cache_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Analytics_Dashboard(
						$container->get( 'database_manager' ),
						$container->get( 'cache_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			'campaign_overview_panel'      => array(
				'class'        => 'SCD_Campaign_Overview_Panel',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository', 'campaign.formatter', 'analytics_repository', 'recurring_handler', 'product_selector', 'campaign_health_service' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Overview_Panel(
						$container->get( 'campaign_repository' ),
						$container->get( 'campaign.formatter' ),
						$container->get( 'analytics_repository' ),
						$container->get( 'recurring_handler' ),
						$container->get( 'product_selector' ),
						$container->get( 'campaign_health_service' )
					);
				},
			),

			'campaign_overview_handler'    => array(
				'class'        => 'SCD_Campaign_Overview_Handler',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository', 'campaign_overview_panel', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Overview_Handler(
						$container->get( 'campaign_repository' ),
						$container->get( 'campaign_overview_panel' ),
						$container->get( 'logger' )
					);
				},
			),

			'analytics_page'               => array(
				'class'        => 'SCD_Analytics_Page',
				'singleton'    => true,
				'dependencies' => array( 'analytics_collector', 'metrics_calculator', 'chart_renderer', 'logger', 'campaign_overview_panel' ),
				'factory'      => function ( $container ) {
					return new SCD_Analytics_Page(
						$container->get( 'analytics_collector' ),
						$container->get( 'metrics_calculator' ),
						$container->get( 'chart_renderer' ),
						$container->get( 'logger' ),
						$container->get( 'campaign_overview_panel' )
					);
				},
			),

			'main_dashboard_page'          => array(
				'class'        => 'SCD_Main_Dashboard_Page',
				'singleton'    => true,
				'dependencies' => array( 'analytics_dashboard', 'campaign_repository', 'feature_gate', 'upgrade_prompt_manager', 'logger', 'campaign_health_service', 'dashboard_service' ),
				'factory'      => function ( $container ) {
					return new SCD_Main_Dashboard_Page(
						$container->get( 'analytics_dashboard' ),
						$container->get( 'campaign_repository' ),
						$container->get( 'feature_gate' ),
						$container->get( 'upgrade_prompt_manager' ),
						$container->get( 'logger' ),
						$container->get( 'campaign_health_service' ),
						$container->get( 'dashboard_service' )
					);
				},
			),

			'campaign_suggestions_service' => array(
				'class'        => 'SCD_Campaign_Suggestions_Service',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Suggestions_Service(
						$container->get( 'campaign_repository' ),
						$container->get( 'logger' )
					);
				},
			),

			'campaign_display_service'     => array(
				'class'        => 'SCD_Campaign_Display_Service',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Display_Service(
						$container->get( 'campaign_repository' ),
						$container->get( 'logger' )
					);
				},
			),

			'campaign_planner_service'     => array(
				'class'        => 'SCD_Campaign_Planner_Service',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository', 'campaign_suggestions_service', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Planner_Service(
						$container->get( 'campaign_repository' ),
						$container->get( 'campaign_suggestions_service' ),
						$container->get( 'logger' )
					);
				},
			),

			'dashboard_service'            => array(
				'class'        => 'SCD_Dashboard_Service',
				'singleton'    => true,
				'dependencies' => array( 'cache_manager', 'analytics_dashboard', 'campaign_repository', 'campaign_health_service', 'feature_gate', 'logger', 'campaign_suggestions_service', 'campaign_display_service', 'campaign_planner_service' ),
				'factory'      => function ( $container ) {
					return new SCD_Dashboard_Service(
						$container->get( 'cache_manager' ),
					$container->get( 'analytics_dashboard' ),
						$container->get( 'campaign_repository' ),
						$container->get( 'campaign_health_service' ),
						$container->get( 'feature_gate' ),
						$container->get( 'logger' ),
						$container->get( 'campaign_suggestions_service' ),
						$container->get( 'campaign_display_service' ),
						$container->get( 'campaign_planner_service' )
					);
				},
			),

			'main_dashboard_data_handler'  => array(
				'class'        => 'SCD_Main_Dashboard_Data_Handler',
				'singleton'    => false,
				'dependencies' => array( 'dashboard_service', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Main_Dashboard_Data_Handler(
						$container->get( 'dashboard_service' ),
						$container->get( 'logger' )
					);
				},
			),

			'activity_tracker'             => array(
				'class'        => 'SCD_Activity_Tracker',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Activity_Tracker(
						$container->get( 'database_manager' ),
						$container->get( 'logger' )
					);
				},
			),

			'export_service'               => array(
				'class'        => 'SCD_Export_Service',
				'singleton'    => true,
				'dependencies' => array( 'metrics_calculator', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Export_Service(
						$container->get( 'metrics_calculator' ),
						$container->get( 'logger' )
					);
				},
			),

			// Analytics AJAX Handlers
			'activity_feed_handler'        => array(
				'class'        => 'SCD_Activity_Feed_Handler',
				'singleton'    => false,
				'dependencies' => array( 'metrics_calculator', 'logger', 'activity_tracker' ),
				'factory'      => function ( $container ) {
					return new SCD_Activity_Feed_Handler(
						$container->get( 'metrics_calculator' ),
						$container->get( 'logger' ),
						$container->get( 'activity_tracker' )
					);
				},
			),

			'campaign_performance_handler' => array(
				'class'        => 'SCD_Campaign_Performance_Handler',
				'singleton'    => false,
				'dependencies' => array( 'metrics_calculator', 'logger', 'analytics_collector' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Performance_Handler(
						$container->get( 'metrics_calculator' ),
						$container->get( 'logger' ),
						$container->get( 'analytics_collector' )
					);
				},
			),

			'revenue_trend_handler'        => array(
				'class'        => 'SCD_Revenue_Trend_Handler',
				'singleton'    => false,
				'dependencies' => array( 'metrics_calculator', 'logger', 'analytics_collector' ),
				'factory'      => function ( $container ) {
					return new SCD_Revenue_Trend_Handler(
						$container->get( 'metrics_calculator' ),
						$container->get( 'logger' ),
						$container->get( 'analytics_collector' )
					);
				},
			),

			'top_products_handler'         => array(
				'class'        => 'SCD_Top_Products_Handler',
				'singleton'    => false,
				'dependencies' => array( 'metrics_calculator', 'logger', 'analytics_collector' ),
				'factory'      => function ( $container ) {
					return new SCD_Top_Products_Handler(
						$container->get( 'metrics_calculator' ),
						$container->get( 'logger' ),
						$container->get( 'analytics_collector' )
					);
				},
			),

			// Product Services
			'condition_engine'             => array(
				'class'        => 'SCD_Condition_Engine',
				'singleton'    => true,
				'dependencies' => array( 'logger', 'cache_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Condition_Engine(
						$container->get( 'logger' ),
						$container->get( 'cache_manager' )
					);
				},
			),

			'product_selector'             => array(
				'class'        => 'SCD_Product_Selector',
				'singleton'    => true,
				'dependencies' => array( 'database_manager', 'logger', 'cache_manager', 'condition_engine' ),
				'factory'      => function ( $container ) {
					return new SCD_Product_Selector(
						$container->get( 'database_manager' ),
						$container->get( 'logger' ),
						$container->get( 'cache_manager' ),
						$container->get( 'condition_engine' )
					);
				},
			),

			'product_filter'               => array(
				'class'        => 'SCD_Product_Filter',
				'singleton'    => true,
				'dependencies' => array( 'product_selector', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Product_Filter(
						$container->get( 'product_selector' ),
						$container->get( 'logger' )
					);
				},
			),

			// Campaign Services

			'campaign_compiler'            => array(
				'class'        => 'SCD_Campaign_Compiler_Service',
				'singleton'    => true,
				'dependencies' => array( 'campaign_repository' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Compiler_Service(
						$container->get( 'campaign_repository' )
					);
				},
			),

			'campaign_event_scheduler'     => array(
				'class'        => 'SCD_Campaign_Event_Scheduler',
				'singleton'    => true,
				'dependencies' => array( 'campaign_manager', 'action_scheduler', 'logger' ),
				'factory'      => function ( $container ) {
					return new SCD_Campaign_Event_Scheduler(
						$container->get( 'campaign_manager' ),
						$container->get( 'action_scheduler' ),
						$container->get( 'logger' )
					);
				},
			),

			// Migration Services
			'migration_manager'            => array(
				'class'        => 'SCD_Migration_Manager',
				'singleton'    => true,
				'dependencies' => array( 'database_manager' ),
				'factory'      => function ( $container ) {
					return new SCD_Migration_Manager( $container->get( 'database_manager' ) );
				},
			),

			// Quality Services
			'requirements_checker'         => array(
				'class'     => 'SCD_Requirements_Checker',
				'singleton' => true,
				'factory'   => function ( $container ) {
					return new SCD_Requirements_Checker();
				},
			),
		);
	}

	/**
	 * Get service aliases.
	 *
	 * @since    1.0.0
	 * @return   array    Service aliases.
	 */
	public static function get_aliases(): array {
		return array(
			// No aliases currently in use
		);
	}
}
