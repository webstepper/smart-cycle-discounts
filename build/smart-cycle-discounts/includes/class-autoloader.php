<?php
/**
 * Autoloader Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-autoloader.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Class Autoloader
 *
 * Handles automatic loading of plugin classes.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Autoloader {

	/**
	 * Class map for direct file mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $class_map    Class to file mapping.
	 */
	private static array $class_map = array(
		// Core classes
		'SCD_Loader'                       => 'class-loader.php',
		'SCD_i18n'                         => 'class-i18n.php',
		'SCD_Container'                    => 'bootstrap/class-container.php',
		'SCD_Service_Registry'             => 'bootstrap/class-service-registry.php',
		'SCD_Logger'                       => 'utilities/class-logger.php',
		'SCD_Error_Handler'                => 'utilities/class-error-handler.php',
		'SCD_Error'                        => 'core/errors/class-error.php',
		'SCD_Error_Collection'             => 'core/errors/class-error-collection.php',
		'SCD_Error_Logger'                 => 'core/errors/class-error-logger.php',

		// Security classes
		'SCD_Security_Manager'             => 'security/class-security-manager.php',
		'SCD_Nonce_Manager'                => 'security/class-nonce-manager.php',
		'SCD_Rate_Limiter'                 => 'security/class-rate-limiter.php',

		// Admin classes
		'SCD_Admin'                        => 'admin/class-admin.php',
		'SCD_Admin_Asset_Manager'          => 'admin/class-admin-asset-manager.php',
		'SCD_Menu_Manager'                 => 'admin/class-menu-manager.php',
		'SCD_Admin_Manager'                => 'admin/class-admin-manager.php',
		'SCD_Admin_Capability_Manager'     => 'admin/class-capability-manager.php',
		'SCD_Notice_Suppressor'            => 'admin/class-notice-suppressor.php',

		// Wizard classes
		'SCD_Wizard_Manager'               => 'core/wizard/class-wizard-manager.php',
		'SCD_Wizard_Sidebar'               => 'core/wizard/class-wizard-sidebar.php',
		'SCD_Sidebar_Base'                 => 'core/wizard/class-sidebar-base.php',
		'SCD_Session_Service'              => 'utilities/class-session-service.php',

		// AJAX classes
		'SCD_Abstract_Ajax_Handler'        => 'admin/ajax/abstract-class-ajax-handler.php',
		'SCD_AJAX_Response'                => 'admin/ajax/class-scd-ajax-response.php',
		'SCD_Ajax_Router'                  => 'admin/ajax/class-ajax-router.php',
		'SCD_Validate_Step_Handler'        => 'core/validation/class-validate-step-handler.php',

		// Database classes
		'SCD_Database_Manager'             => 'database/class-database-manager.php',
		'SCD_Base_Repository'              => 'database/repositories/class-base-repository.php',
		'SCD_Migration_Manager'            => 'database/class-migration-manager.php',
		'SCD_Campaign'                     => 'core/campaigns/class-campaign.php',
		'SCD_Campaign_Repository'          => 'database/repositories/class-campaign-repository.php',
		'SCD_Discount_Repository'          => 'database/repositories/class-discount-repository.php',

		// Campaign classes
		'SCD_Campaign_Manager'             => 'core/campaigns/class-campaign-manager.php',
		'SCD_Campaign_Compiler_Service'    => 'core/campaigns/class-campaign-compiler-service.php',
		'SCD_Campaign_Event_Scheduler'     => 'core/campaigns/class-campaign-event-scheduler.php',

		// Scheduling classes
		'SCD_Action_Scheduler_Service'     => 'core/scheduling/class-action-scheduler-service.php',
		'SCD_Cron_Scheduler'               => 'core/cron/class-cron-scheduler.php',

		// Manager classes
		'SCD_Customer_Usage_Manager'       => 'core/managers/class-customer-usage-manager.php',

		// Cache classes
		'SCD_Cache_Manager'                => 'cache/class-cache-manager.php',

		// Validation classes
		'SCD_Validation'                   => 'core/validation/class-validation.php',

		// Domain model classes for validation
		'SCD_Schedule'                     => 'core/scheduling/class-schedule.php',

		// Page classes
		'SCD_Campaigns_Page'               => 'admin/pages/class-campaigns-page.php',
		'SCD_Abstract_Campaign_Controller' => 'core/campaigns/abstract-campaign-controller.php',
		'SCD_Campaign_List_Controller'     => 'core/campaigns/class-campaign-list-controller.php',
		'SCD_Campaign_Edit_Controller'     => 'core/campaigns/class-campaign-edit-controller.php',
		'SCD_Campaign_View_Renderer'       => 'core/campaigns/class-campaign-view-renderer.php',
		'SCD_Campaign_Wizard_Controller'   => 'core/campaigns/class-campaign-wizard-controller.php',
		'SCD_Campaign_Action_Handler'      => 'core/campaigns/class-campaign-action-handler.php',
		'SCD_Campaigns_List_Table'         => 'admin/components/class-campaigns-list-table.php',

		// Settings classes
		'SCD_Settings_Manager'             => 'admin/settings/class-settings-manager.php',
		'SCD_Settings_Page_Base'           => 'admin/settings/class-settings-page-base.php',
		'SCD_General_Settings'             => 'admin/settings/tabs/class-general-settings.php',
		'SCD_Performance_Settings'         => 'admin/settings/tabs/class-performance-settings.php',
		'SCD_Advanced_Settings'            => 'admin/settings/tabs/class-advanced-settings.php',

		// Notifications page classes
		'SCD_Notifications_Page'           => 'admin/pages/notifications/class-notifications-page.php',
		'SCD_Notifications_Tab_Base'       => 'admin/pages/notifications/class-notifications-tab-base.php',
		'SCD_Notifications_Settings_Tab'   => 'admin/pages/notifications/tabs/class-notifications-settings-tab.php',
		'SCD_Queue_Status_Tab'             => 'admin/pages/notifications/tabs/class-queue-status-tab.php',

		// Performance classes
		'SCD_Performance_Optimizer'        => 'utilities/class-performance-optimizer.php',
		'SCD_Performance_Monitor'          => 'utilities/class-performance-monitor.php',
		'SCD_Reference_Data_Cache'         => 'cache/class-reference-data-cache.php',
		'SCD_Performance_Bootstrapper'     => 'utilities/class-performance-bootstrapper.php',

		// Enhanced handlers

		// Integration classes
		'SCD_WooCommerce_Integration'      => 'integrations/woocommerce/class-woocommerce-integration.php',

		// WooCommerce sub-integrations (Phase 2 & 3)
		'SCD_WC_Discount_Query_Service'    => 'integrations/woocommerce/class-wc-discount-query-service.php',
		'SCD_WC_Price_Integration'         => 'integrations/woocommerce/class-wc-price-integration.php',
		'SCD_WC_Display_Integration'       => 'integrations/woocommerce/class-wc-display-integration.php',
		'SCD_WC_Cart_Message_Service'      => 'integrations/woocommerce/class-wc-cart-message-service.php',
		'SCD_WC_Admin_Integration'         => 'integrations/woocommerce/class-wc-admin-integration.php',
		'SCD_WC_Order_Integration'         => 'integrations/woocommerce/class-wc-order-integration.php',

		'SCD_Blocks_Manager'               => 'integrations/blocks/class-blocks-manager.php',
		'SCD_Email_Manager'                => 'integrations/email/class-email-manager.php',
		'SCD_Email_Provider'               => 'integrations/email/interface-email-provider.php',
		'SCD_WPMail_Provider'              => 'integrations/email/providers/class-wpmail-provider.php',
		'SCD_SendGrid_Provider'            => 'integrations/email/providers/class-sendgrid-provider.php',
		'SCD_AmazonSES_Provider'           => 'integrations/email/providers/class-amazonses-provider.php',
		'SCD_Integration_Manager'          => 'integrations/class-integration-manager.php',

		// Discount classes
		'SCD_Discount_Engine'              => 'core/discounts/class-discount-engine.php',
		'SCD_Discount_Applicator'          => 'core/discounts/class-discount-applicator.php',
		'SCD_Discount'                     => 'core/discounts/class-discount.php',
		'SCD_Recurring_Handler'            => 'class-recurring-handler.php',
		'SCD_Discount_Strategy_Interface'  => 'core/discounts/interface-discount-strategy.php',
		'SCD_Discount_Result'              => 'core/discounts/interface-discount-strategy.php',
		'SCD_Percentage_Discount_Strategy' => 'core/discounts/strategies/class-percentage-strategy.php',
		'SCD_Fixed_Discount_Strategy'      => 'core/discounts/strategies/class-fixed-strategy.php',
		'SCD_BOGO_Discount_Strategy'       => 'core/discounts/strategies/class-bogo-strategy.php',
		'SCD_Tiered_Discount_Strategy'     => 'core/discounts/strategies/class-tiered-strategy.php',
		// Actual strategy class names (used by discount engine)
		'SCD_Percentage_Strategy'          => 'core/discounts/strategies/class-percentage-strategy.php',
		'SCD_Fixed_Strategy'               => 'core/discounts/strategies/class-fixed-strategy.php',
		'SCD_Bogo_Strategy'                => 'core/discounts/strategies/class-bogo-strategy.php',
		'SCD_Tiered_Strategy'              => 'core/discounts/strategies/class-tiered-strategy.php',
		'SCD_Spend_Threshold_Strategy'     => 'core/discounts/strategies/class-spend-threshold-strategy.php',

		// Analytics classes
		'SCD_Abstract_Analytics_Handler'   => 'core/analytics/abstract-analytics-handler.php',
		'SCD_Analytics_Controller'         => 'core/analytics/class-analytics-controller.php',
		'SCD_Analytics_Collector'          => 'core/analytics/class-analytics-collector.php',
		'SCD_Analytics_Data'               => 'core/analytics/class-analytics-data.php',
		'SCD_Metrics_Calculator'           => 'core/analytics/class-metrics-calculator.php',
		'SCD_Report_Generator'             => 'core/analytics/class-report-generator.php',
		'SCD_Analytics_Repository'         => 'database/repositories/class-analytics-repository.php',
		'SCD_Analytics_Page'               => 'admin/pages/class-analytics-page.php',
		'SCD_Analytics_Dashboard'          => 'admin/pages/class-analytics-dashboard.php',
		'SCD_Activity_Tracker'             => 'core/analytics/class-activity-tracker.php',
		'SCD_Export_Service'               => 'core/analytics/class-export-service.php',

		// AJAX Handlers
		'SCD_Save_Step_Handler'            => 'admin/ajax/handlers/class-save-step-handler.php',
		'SCD_Load_Data_Handler'            => 'admin/ajax/handlers/class-load-data-handler.php',
		'SCD_Product_Search_Handler'       => 'admin/ajax/handlers/class-product-search-handler.php',
		'SCD_Clear_Cache_Handler'          => 'admin/ajax/handlers/class-clear-cache-handler.php',
		'SCD_Get_Summary_Handler'          => 'admin/ajax/handlers/class-get-summary-handler.php',
		'SCD_Check_Campaign_Name_Handler'  => 'admin/ajax/handlers/class-check-campaign-name-handler.php',
		'SCD_Get_Product_Stats_Handler'    => 'admin/ajax/handlers/class-get-product-stats-handler.php',
		'SCD_Recover_Session_Handler'      => 'admin/ajax/handlers/class-recover-session-handler.php',
		'SCD_Health_Check_Handler'         => 'admin/ajax/handlers/class-health-check-handler.php',
		'SCD_Campaign_Health_Handler'      => 'admin/ajax/handlers/class-campaign-health-handler.php',
		'SCD_Preview_Coverage_Handler'     => 'admin/ajax/handlers/class-preview-coverage-handler.php',
		'SCD_Check_Conflicts_Handler'      => 'admin/ajax/handlers/class-check-conflicts-handler.php',
		'SCD_Campaign_Validation_Handler'  => 'admin/ajax/handlers/class-campaign-validation-handler.php',
		'SCD_Get_Active_Campaigns_Handler' => 'admin/ajax/handlers/class-get-active-campaigns-handler.php',
		'SCD_Wizard_Navigate_Handler'      => 'core/wizard/class-wizard-navigate-handler.php',

		// Draft Handler (consolidated)
		'SCD_Draft_Handler'                => 'admin/ajax/handlers/class-draft-handler.php',

		// Analytics Handlers
		'SCD_Overview_Handler'             => 'admin/ajax/handlers/class-overview-handler.php',
		'SCD_Campaign_Performance_Handler' => 'core/campaigns/class-campaign-performance-handler.php',
		'SCD_Revenue_Trend_Handler'        => 'admin/ajax/handlers/class-revenue-trend-handler.php',
		'SCD_Activity_Feed_Handler'        => 'admin/ajax/handlers/class-activity-feed-handler.php',
		'SCD_Export_Handler'               => 'admin/ajax/handlers/class-export-handler.php',
		'SCD_Refresh_Cache_Handler'        => 'admin/ajax/handlers/class-refresh-cache-handler.php',

		// Service classes
		'SCD_Wizard_State_Service'         => 'core/wizard/class-wizard-state-service.php',
		'SCD_Campaign_Compiler_Service'    => 'core/campaigns/class-campaign-compiler-service.php',
		'SCD_Campaign_Health_Service'      => 'core/services/class-campaign-health-service.php',
		'SCD_Session_Lock'                 => 'utilities/class-session-lock-service.php',
		'SCD_Service_Definitions'          => 'bootstrap/class-service-definitions.php',

		// Security classes
		'SCD_Ajax_Security'                => 'admin/ajax/class-ajax-security.php',
		'SCD_Request_Schemas'              => 'api/class-request-schemas.php',

		// Wizard specific classes


		// Interfaces/Contracts
		'SCD_Interface_Nonce_Manager'      => 'security/interface-nonce-manager.php',

		// Product classes
		'SCD_Product_Filter'               => 'core/products/class-product-filter.php',
		'SCD_Product_Selector'             => 'core/products/class-product-selector.php',
		'SCD_Product_Service'              => 'core/products/class-product-service.php',
		'SCD_Condition_Engine'             => 'core/products/class-condition-engine.php',

		// Frontend classes
		'SCD_Frontend_Manager'             => 'frontend/class-frontend-manager.php',
		'SCD_Discount_Display'             => 'frontend/class-discount-display.php',
		'SCD_Countdown_Timer'              => 'frontend/class-countdown-timer.php',
		'SCD_Shortcodes'                   => 'frontend/class-shortcodes.php',
		'SCD_Template_Loader'              => 'frontend/class-template-loader.php',

		// Asset management classes
		'SCD_Frontend_Asset_Manager'       => 'frontend/class-frontend-asset-manager.php',
		'SCD_Asset_Loader'                 => 'admin/assets/class-asset-loader.php',
		'SCD_Asset_Localizer'              => 'admin/assets/class-asset-localizer.php',
		'SCD_Asset_Optimizer'              => 'admin/assets/class-asset-optimizer.php',
		'SCD_Asset_Dependency_Resolver'    => 'admin/assets/class-asset-dependency-resolver.php',
		'SCD_Script_Registry'              => 'admin/assets/class-script-registry.php',
		'SCD_Style_Registry'               => 'admin/assets/class-style-registry.php',
		'SCD_Theme_Color_Inline_Styles'    => 'admin/assets/class-theme-color-inline-styles.php',

		// Utility classes
		'SCD_Log'                          => 'utilities/class-scd-log.php',
		'SCD_Time_Helpers'                 => 'utilities/class-time-helpers.php',
		'SCD_Translation_Handler'          => 'utilities/class-translation-handler.php',
		'SCD_Service_Health_Check'         => 'utilities/class-service-health-check.php',
		'SCD_Requirements_Checker'         => 'utilities/class-requirements-checker.php',

		// API classes
		'SCD_REST_API_Manager'             => 'api/class-rest-api-manager.php',
		'SCD_API_Authentication'           => 'api/class-api-authentication.php',
		'SCD_API_Permissions'              => 'api/class-api-permissions.php',
		'SCD_Campaigns_Controller'         => 'api/endpoints/class-campaigns-controller.php',
		'SCD_Discounts_Controller'         => 'api/endpoints/class-discounts-controller.php',

		// Additional security classes
		'SCD_Security_Headers'             => 'security/class-security-headers.php',
		'SCD_Audit_Logger'                 => 'security/class-audit-logger.php',

		// Database utilities
		'SCD_Query_Builder'                => 'database/class-query-builder.php',

		// Campaign utilities
		'SCD_Campaign_Formatter'           => 'core/campaigns/class-campaign-formatter.php',
		'SCD_Campaign_Serializer'          => 'core/campaigns/class-campaign-serializer.php',
		'SCD_Campaign_State_Manager'       => 'core/campaigns/class-campaign-state-manager.php',
		'SCD_Campaign_Calculator'          => 'core/campaigns/class-campaign-calculator.php',

		// Additional helpers
		'SCD_Tooltip_Helper'               => 'admin/helpers/class-tooltip-helper.php',
		'SCD_Chart_Renderer'               => 'admin/components/class-chart-renderer.php',
		'SCD_Condition_Builder'            => 'admin/components/class-condition-builder.php',
		'SCD_Cache_Warming'                => 'cache/class-cache-warming.php',
		'SCD_Cache_Factory'                => 'cache/class-cache-factory.php',
		'SCD_Task_Manager'                 => 'core/scheduling/class-task-manager.php',
		'SCD_Wizard_Navigation'            => 'core/wizard/class-wizard-navigation.php',
	);

	/**
	 * Register autoloader.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Unregister autoloader.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function unregister(): void {
		spl_autoload_unregister( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload class.
	 *
	 * @since    1.0.0
	 * @param    string $class_name    Class name to load.
	 * @return   void
	 */
	public static function autoload( string $class_name ): void {
		// Only handle our plugin classes
		if ( strpos( $class_name, 'SCD_' ) !== 0 ) {
			return;
		}

		// Check class map first
		if ( isset( self::$class_map[ $class_name ] ) ) {
			$file = SCD_INCLUDES_DIR . self::$class_map[ $class_name ];
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Try to build path from class name
		$file = self::get_file_path_from_class( $class_name );
		if ( $file && file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Get file path from class name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $class_name    Class name.
	 * @return   string|null              File path or null.
	 */
	private static function get_file_path_from_class( string $class_name ): ?string {
		// Remove SCD_ prefix
		$class = str_replace( 'SCD_', '', $class_name );

		// Convert to lowercase with hyphens
		$class = strtolower( str_replace( '_', '-', $class ) );

		// Common paths to check
		$paths = array(
			'admin/class-' . $class . '.php',
			'admin/ajax/class-' . $class . '.php',
			'admin/ajax/handlers/class-' . $class . '.php',
			'admin/components/class-' . $class . '.php',
			'admin/pages/class-' . $class . '.php',
			'admin/pages/campaigns/class-' . $class . '.php',
			'admin/pages/campaigns/abstract-' . $class . '.php',
			'admin/tables/class-' . $class . '.php',
			'core/class-' . $class . '.php',
			'core/campaigns/class-' . $class . '.php',
			'core/discounts/class-' . $class . '.php',
			'core/analytics/class-' . $class . '.php',
			'core/scheduling/class-' . $class . '.php',
			'core/cron/class-' . $class . '.php',
			'core/managers/class-' . $class . '.php',
			'database/class-' . $class . '.php',
			'database/models/class-' . $class . '.php',
			'database/repositories/class-' . $class . '.php',
			'security/class-' . $class . '.php',
			'cache/class-' . $class . '.php',
			'core/validation/class-' . $class . '.php',
			'validation/class-' . $class . '.php',
			'validation/validators/class-' . $class . '.php',
			'validation/handlers/class-' . $class . '.php',
			'validation/security/class-' . $class . '.php',
			'admin/validation/class-' . $class . '.php',
			'services/class-' . $class . '.php',
			'contracts/class-' . $class . '.php',
			'contracts/interface-' . $class . '.php',
			'api/class-' . $class . '.php',
			'api/controllers/class-' . $class . '.php',
			'utilities/class-' . $class . '.php',
		);

		foreach ( $paths as $path ) {
			$file = SCD_INCLUDES_DIR . $path;
			if ( file_exists( $file ) ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Check if class can be autoloaded.
	 *
	 * @since    1.0.0
	 * @param    string $class_name    Class name.
	 * @return   bool                     True if can be autoloaded.
	 */
	public static function can_autoload( string $class_name ): bool {
		if ( strpos( $class_name, 'SCD_' ) !== 0 ) {
			return false;
		}

		return isset( self::$class_map[ $class_name ] ) ||
				self::get_file_path_from_class( $class_name ) !== null;
	}
}
