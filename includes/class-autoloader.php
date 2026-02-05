<?php
/**
 * Autoloader Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-autoloader.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


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
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Autoloader {

	/**
	 * Class map for direct file mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $class_map    Class to file mapping.
	 */
	private static array $class_map = array(
		// Core classes
		'WSSCD_Loader'                       => 'class-loader.php',
		'WSSCD_i18n'                         => 'class-i18n.php',
		'WSSCD_Container'                    => 'bootstrap/class-container.php',
		'WSSCD_Service_Registry'             => 'bootstrap/class-service-registry.php',
		'WSSCD_Logger'                       => 'utilities/class-logger.php',
		'WSSCD_Error_Handler'                => 'utilities/class-error-handler.php',
		'WSSCD_HTML_Helper'                  => 'utilities/class-html-helper.php',

		// Security classes
		'WSSCD_Security_Manager'             => 'security/class-security-manager.php',
		'WSSCD_Nonce_Manager'                => 'security/class-nonce-manager.php',
		'WSSCD_Rate_Limiter'                 => 'security/class-rate-limiter.php',

		// Admin classes
		'WSSCD_Admin'                        => 'admin/class-admin.php',
		'WSSCD_Admin_Asset_Manager'          => 'admin/class-admin-asset-manager.php',
		'WSSCD_Menu_Manager'                 => 'admin/class-menu-manager.php',
		'WSSCD_Admin_Manager'                => 'admin/class-admin-manager.php',
		'WSSCD_Admin_Capability_Manager'     => 'admin/class-capability-manager.php',
		'WSSCD_Notice_Suppressor'            => 'admin/class-notice-suppressor.php',

		// Wizard classes
		'WSSCD_Wizard_Manager'               => 'core/wizard/class-wizard-manager.php',
		'WSSCD_Wizard_Intent_Handler'        => 'core/wizard/class-wizard-intent-handler.php',
		'WSSCD_Wizard_Sidebar'               => 'core/wizard/class-wizard-sidebar.php',
		'WSSCD_Wizard_Sidebar_Base'          => 'core/wizard/class-sidebar-base.php',
		'WSSCD_Wizard_Sidebar_Basic'         => 'core/wizard/sidebars/class-sidebar-basic.php',
		'WSSCD_Wizard_Sidebar_Products'      => 'core/wizard/sidebars/class-sidebar-products.php',
		'WSSCD_Wizard_Sidebar_Discounts'     => 'core/wizard/sidebars/class-sidebar-discounts.php',
		'WSSCD_Wizard_Sidebar_Schedule'      => 'core/wizard/sidebars/class-sidebar-schedule.php',
		'WSSCD_Wizard_Sidebar_Review'        => 'core/wizard/sidebars/class-sidebar-review.php',
		'WSSCD_Sidebar_Help_Topics'          => 'core/wizard/class-sidebar-help-topics.php',
		'WSSCD_Sidebar_Renderer'             => 'core/wizard/class-sidebar-renderer.php',
		'WSSCD_Session_Service'              => 'utilities/class-session-service.php',

		// AJAX classes
		'WSSCD_Abstract_Ajax_Handler'        => 'admin/ajax/abstract-class-ajax-handler.php',
		'WSSCD_AJAX_Response'                => 'admin/ajax/class-wsscd-ajax-response.php',
		'WSSCD_Ajax_Router'                  => 'admin/ajax/class-ajax-router.php',

		// Database classes
		'WSSCD_Database_Manager'             => 'database/class-database-manager.php',
		'WSSCD_Base_Repository'              => 'database/repositories/class-base-repository.php',
		'WSSCD_Migration_Manager'            => 'database/class-migration-manager.php',
		'WSSCD_Campaign'                     => 'core/campaigns/class-campaign.php',
		'WSSCD_Campaign_Repository'          => 'database/repositories/class-campaign-repository.php',
		'WSSCD_Discount_Repository'          => 'database/repositories/class-discount-repository.php',

		// Campaign classes
		'WSSCD_Campaign_Manager'             => 'core/campaigns/class-campaign-manager.php',
		'WSSCD_Campaign_Compiler_Service'    => 'core/campaigns/class-campaign-compiler-service.php',
		'WSSCD_Campaign_Event_Scheduler'     => 'core/campaigns/class-campaign-event-scheduler.php',

		// Scheduling classes
		'WSSCD_Action_Scheduler_Service'     => 'core/scheduling/class-action-scheduler-service.php',
		'WSSCD_Cron_Scheduler'               => 'core/cron/class-cron-scheduler.php',

		// Manager classes
		'WSSCD_Customer_Usage_Manager'       => 'core/managers/class-customer-usage-manager.php',

		'WSSCD_Cache_Manager'                => 'cache/class-cache-manager.php',

		// Validation classes
		'WSSCD_Validation'                   => 'core/validation/class-validation.php',
		'WSSCD_Condition_Validator'          => 'core/validation/class-condition-validator.php',
		'WSSCD_Products_Step_Validator'      => 'core/validation/step-validators/class-products-step-validator.php',
		'WSSCD_Discounts_Step_Validator'     => 'core/validation/step-validators/class-discounts-step-validator.php',
		'WSSCD_Schedule_Step_Validator'      => 'core/validation/step-validators/class-schedule-step-validator.php',
		'WSSCD_Campaign_Cross_Validator'     => 'core/validation/class-campaign-cross-validator.php',
		'WSSCD_Discount_Rules_Enforcer'      => 'core/validation/class-discount-rules-enforcer.php',

		// Page classes
		'WSSCD_Campaigns_Page'               => 'admin/pages/class-campaigns-page.php',
		'WSSCD_Abstract_Campaign_Controller' => 'core/campaigns/abstract-campaign-controller.php',
		'WSSCD_Campaign_List_Controller'     => 'core/campaigns/class-campaign-list-controller.php',
		'WSSCD_Campaign_View_Renderer'       => 'core/campaigns/class-campaign-view-renderer.php',
		'WSSCD_Campaign_Wizard_Controller'   => 'core/campaigns/class-campaign-wizard-controller.php',
		'WSSCD_Campaign_Action_Handler'      => 'core/campaigns/class-campaign-action-handler.php',
		'WSSCD_Campaigns_List_Table'         => 'admin/components/class-campaigns-list-table.php',

		// Settings classes
		'WSSCD_Settings_Manager'             => 'admin/settings/class-settings-manager.php',
		'WSSCD_Settings_Page_Base'           => 'admin/settings/class-settings-page-base.php',
		'WSSCD_General_Settings'             => 'admin/settings/tabs/class-general-settings.php',
		'WSSCD_Advanced_Settings'            => 'admin/settings/tabs/class-advanced-settings.php',

		// Notifications page classes
		'WSSCD_Notifications_Page'           => 'admin/pages/notifications/class-notifications-page.php',
		'WSSCD_Notifications_Tab_Base'       => 'admin/pages/notifications/class-notifications-tab-base.php',
		'WSSCD_Notifications_Settings_Tab'   => 'admin/pages/notifications/tabs/class-notifications-settings-tab.php',
		'WSSCD_Queue_Status_Tab'             => 'admin/pages/notifications/tabs/class-queue-status-tab.php',

		// Performance classes
		'WSSCD_Performance_Optimizer'        => 'utilities/class-performance-optimizer.php',
		'WSSCD_Performance_Monitor'          => 'utilities/class-performance-monitor.php',
		'WSSCD_Reference_Data_Cache'         => 'cache/class-reference-data-cache.php',
		'WSSCD_Performance_Bootstrapper'     => 'utilities/class-performance-bootstrapper.php',

		// Enhanced handlers

		// Integration classes
		'WSSCD_WooCommerce_Integration'      => 'integrations/woocommerce/class-woocommerce-integration.php',

		// WooCommerce sub-integrations (Phase 2 & 3)
		'WSSCD_WC_Discount_Map_Service'      => 'integrations/woocommerce/class-wc-discount-map-service.php',
		'WSSCD_WC_Discount_Query_Service'    => 'integrations/woocommerce/class-wc-discount-query-service.php',
		'WSSCD_WC_Price_Integration'         => 'integrations/woocommerce/class-wc-price-integration.php',
		'WSSCD_WC_Display_Integration'       => 'integrations/woocommerce/class-wc-display-integration.php',
		'WSSCD_WC_Cart_Message_Service'      => 'integrations/woocommerce/class-wc-cart-message-service.php',
		'WSSCD_WC_Admin_Integration'         => 'integrations/woocommerce/class-wc-admin-integration.php',
		'WSSCD_WC_Order_Integration'         => 'integrations/woocommerce/class-wc-order-integration.php',
		'WSSCD_WC_Coupon_Restriction'        => 'integrations/woocommerce/class-wc-coupon-restriction.php',
		'WSSCD_WC_Free_Shipping_Handler'     => 'integrations/woocommerce/class-wc-free-shipping-handler.php',

		'WSSCD_Blocks_Manager'               => 'integrations/blocks/class-blocks-manager.php',
		'WSSCD_Email_Manager'                => 'integrations/email/class-email-manager.php',
		'WSSCD_Alert_Monitor'                => 'integrations/email/class-alert-monitor.php',
		'WSSCD_Email_Provider'               => 'integrations/email/interface-email-provider.php',
		'WSSCD_WPMail_Provider'              => 'integrations/email/providers/class-wpmail-provider.php',
		'WSSCD_SendGrid_Provider'            => 'integrations/email/providers/class-sendgrid-provider.php',
		'WSSCD_AmazonSES_Provider'           => 'integrations/email/providers/class-amazonses-provider.php',
		'WSSCD_Integration_Manager'          => 'integrations/class-integration-manager.php',

		// Discount classes
		'WSSCD_Discount_Engine'              => 'core/discounts/class-discount-engine.php',
		'WSSCD_Discount_Applicator'          => 'core/discounts/class-discount-applicator.php',
		'WSSCD_Discount'                     => 'core/discounts/class-discount.php',
		'WSSCD_Recurring_Handler'            => 'class-recurring-handler.php',
		'WSSCD_Discount_Strategy_Interface'  => 'core/discounts/interface-discount-strategy.php',
		'WSSCD_Discount_Result'              => 'core/discounts/interface-discount-strategy.php',
		'WSSCD_Percentage_Discount_Strategy' => 'core/discounts/strategies/class-percentage-strategy.php',
		'WSSCD_Fixed_Discount_Strategy'      => 'core/discounts/strategies/class-fixed-strategy.php',
		'WSSCD_BOGO_Discount_Strategy'       => 'core/discounts/strategies/class-bogo-strategy.php',
		'WSSCD_Tiered_Discount_Strategy'     => 'core/discounts/strategies/class-tiered-strategy.php',
		// Actual strategy class names (used by discount engine)
		'WSSCD_Percentage_Strategy'          => 'core/discounts/strategies/class-percentage-strategy.php',
		'WSSCD_Fixed_Strategy'               => 'core/discounts/strategies/class-fixed-strategy.php',
		'WSSCD_Bogo_Strategy'                => 'core/discounts/strategies/class-bogo-strategy.php',
		'WSSCD_Tiered_Strategy'              => 'core/discounts/strategies/class-tiered-strategy.php',
		'WSSCD_Spend_Threshold_Strategy'     => 'core/discounts/strategies/class-spend-threshold-strategy.php',

		// Analytics classes
		'WSSCD_Abstract_Analytics_Handler'   => 'core/analytics/abstract-analytics-handler.php',
		'WSSCD_Analytics_Collector'          => 'core/analytics/class-analytics-collector.php',
		'WSSCD_Metrics_Calculator'           => 'core/analytics/class-metrics-calculator.php',
		'WSSCD_Report_Generator'             => 'core/analytics/class-report-generator.php',
		'WSSCD_Analytics_Repository'         => 'database/repositories/class-analytics-repository.php',
		'WSSCD_Analytics_Page'               => 'admin/pages/class-analytics-page.php',
		'WSSCD_Analytics_Dashboard'          => 'admin/pages/class-analytics-dashboard.php',
		'WSSCD_Activity_Tracker'             => 'core/analytics/class-activity-tracker.php',
		'WSSCD_Export_Service'               => 'core/analytics/class-export-service.php',

		// AJAX Handlers
		'WSSCD_Save_Step_Handler'               => 'admin/ajax/handlers/class-save-step-handler.php',
		'WSSCD_Load_Data_Handler'               => 'admin/ajax/handlers/class-load-data-handler.php',
		'WSSCD_Product_Search_Handler'          => 'admin/ajax/handlers/class-product-search-handler.php',
		'WSSCD_Test_Provider_Connection_Handler' => 'admin/ajax/handlers/class-test-provider-connection-handler.php',
		'WSSCD_Get_Summary_Handler'          => 'admin/ajax/handlers/class-get-summary-handler.php',
		'WSSCD_Check_Campaign_Name_Handler'  => 'admin/ajax/handlers/class-check-campaign-name-handler.php',
		'WSSCD_Get_Product_Stats_Handler'    => 'admin/ajax/handlers/class-get-product-stats-handler.php',
		'WSSCD_Get_Shipping_Methods_Handler' => 'admin/ajax/handlers/class-get-shipping-methods-handler.php',
		'WSSCD_Health_Check_Handler'         => 'admin/ajax/handlers/class-health-check-handler.php',
		'WSSCD_Campaign_Health_Handler'      => 'admin/ajax/handlers/class-campaign-health-handler.php',
		'WSSCD_Preview_Coverage_Handler'     => 'admin/ajax/handlers/class-preview-coverage-handler.php',
		'WSSCD_Check_Conflicts_Handler'      => 'admin/ajax/handlers/class-check-conflicts-handler.php',
		'WSSCD_Sidebar_Ajax_Handler'         => 'admin/ajax/handlers/class-sidebar-ajax-handler.php',
		'WSSCD_Toggle_Campaign_Status_Handler' => 'admin/ajax/handlers/class-toggle-campaign-status-handler.php',

		// Draft Handler (consolidated)
		'WSSCD_Draft_Handler'                => 'admin/ajax/handlers/class-draft-handler.php',

		// Tools Handlers
		'WSSCD_Tools_Handler'                => 'admin/ajax/handlers/class-tools-handler.php',
		'WSSCD_Import_Export_Handler'        => 'admin/ajax/handlers/class-import-export-handler.php',
		'WSSCD_Import_Handler'               => 'admin/ajax/handlers/class-import-handler.php',
		'WSSCD_Log_Viewer_Handler'           => 'admin/ajax/handlers/class-log-viewer-handler.php',

		// Analytics Handlers
		'WSSCD_Overview_Handler'             => 'admin/ajax/handlers/class-overview-handler.php',
		'WSSCD_Campaign_Performance_Handler' => 'admin/ajax/handlers/class-campaign-performance-handler.php',
		'WSSCD_Revenue_Trend_Handler'        => 'admin/ajax/handlers/class-revenue-trend-handler.php',
		'WSSCD_Activity_Feed_Handler'        => 'admin/ajax/handlers/class-activity-feed-handler.php',
		'WSSCD_Export_Handler'               => 'admin/ajax/handlers/class-export-handler.php',

		// Service classes
		'WSSCD_Wizard_State_Service'         => 'core/wizard/class-wizard-state-service.php',
		'WSSCD_Campaign_Compiler_Service'    => 'core/campaigns/class-campaign-compiler-service.php',
		'WSSCD_Campaign_Health_Service'      => 'core/services/class-campaign-health-service.php',
		'WSSCD_Campaign_Summary_Service'     => 'services/class-campaign-summary-service.php',
		'WSSCD_Session_Lock'                 => 'utilities/class-session-lock-service.php',
		'WSSCD_Service_Definitions'          => 'bootstrap/class-service-definitions.php',

		// Security classes
		'WSSCD_Ajax_Security'                => 'admin/ajax/class-ajax-security.php',
		// Product classes
		'WSSCD_Product_Selector'             => 'core/products/class-product-selector.php',
		'WSSCD_Product_Service'              => 'core/products/class-product-service.php',
		'WSSCD_Condition_Engine'             => 'core/products/class-condition-engine.php',

		// Category classes
		'WSSCD_Category_Metadata_Service'    => 'core/categories/class-category-metadata-service.php',

		// Frontend classes
		'WSSCD_Frontend_Manager'             => 'frontend/class-frontend-manager.php',
		'WSSCD_Discount_Display'             => 'frontend/class-discount-display.php',
		'WSSCD_Discount_Display_Rules'       => 'frontend/class-discount-display-rules.php',
		'WSSCD_Shortcodes'                   => 'frontend/class-shortcodes.php',
		'WSSCD_Template_Loader'              => 'frontend/class-template-loader.php',
		'WSSCD_Frontend_Ajax_Handler'        => 'frontend/class-frontend-ajax-handler.php',

		// Asset management classes
		'WSSCD_Frontend_Asset_Manager'       => 'frontend/class-frontend-asset-manager.php',
		'WSSCD_Asset_Loader'                 => 'admin/assets/class-asset-loader.php',
		'WSSCD_Asset_Localizer'              => 'admin/assets/class-asset-localizer.php',
		'WSSCD_Script_Registry'              => 'admin/assets/class-script-registry.php',
		'WSSCD_Style_Registry'               => 'admin/assets/class-style-registry.php',
		'WSSCD_Theme_Color_Inline_Styles'    => 'admin/assets/class-theme-color-inline-styles.php',

		// Utility classes
		'WSSCD_Log'                          => 'utilities/class-wsscd-log.php',
		'WSSCD_Translation_Handler'          => 'utilities/class-translation-handler.php',
		'WSSCD_Service_Health_Check'         => 'utilities/class-service-health-check.php',
		'WSSCD_Requirements_Checker'         => 'utilities/class-requirements-checker.php',

		// Constant classes
		'WSSCD_Schedule_Field_Names'         => 'constants/class-wsscd-schedule-field-names.php',
		'WSSCD_Product_Selection_Types'      => 'constants/class-wsscd-product-selection-types.php',


		// Additional security classes
		'WSSCD_Audit_Logger'                 => 'security/class-audit-logger.php',

		// Database utilities
		'WSSCD_Query_Builder'                => 'database/class-query-builder.php',

		// Campaign utilities
		'WSSCD_Campaign_Formatter'           => 'core/campaigns/class-campaign-formatter.php',
		'WSSCD_Campaign_State_Manager'       => 'core/campaigns/class-campaign-state-manager.php',


		// Additional helpers
		'WSSCD_Tooltip_Helper'               => 'admin/helpers/class-tooltip-helper.php',
		'WSSCD_Icon_Helper'                  => 'admin/helpers/class-icon-helper.php',
		'WSSCD_Button_Helper'                => 'admin/helpers/class-button-helper.php',
		'WSSCD_Card_Helper'                  => 'admin/helpers/class-card-helper.php',
		'WSSCD_Chart_Renderer'               => 'admin/components/class-chart-renderer.php',
		'WSSCD_Badge_Helper'                 => 'admin/components/class-badge-helper.php',
		'WSSCD_Campaign_Overview_Panel'      => 'admin/components/class-campaign-overview-panel.php',
		'WSSCD_Modal_Component'              => 'admin/components/class-modal-component.php',
		'WSSCD_Wizard_Navigation'            => 'core/wizard/class-wizard-navigation.php',
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
	 * Autoload class.
	 *
	 * @since    1.0.0
	 * @param    string $class_name    Class name to load.
	 * @return   void
	 */
	public static function autoload( string $class_name ): void {
		// Only handle our plugin classes
		if ( 0 !== strpos( $class_name, 'WSSCD_' ) ) {
			return;
		}

		if ( isset( self::$class_map[ $class_name ] ) ) {
			$file = WSSCD_INCLUDES_DIR . self::$class_map[ $class_name ];
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
		// Remove WSSCD_ prefix
		$class = str_replace( 'WSSCD_', '', $class_name );

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
			'constants/class-' . $class . '.php',
			'core/class-' . $class . '.php',
			'core/campaigns/class-' . $class . '.php',
			'core/discounts/class-' . $class . '.php',
			'core/analytics/class-' . $class . '.php',
			'core/scheduling/class-' . $class . '.php',
			'core/cron/class-' . $class . '.php',
			'core/managers/class-' . $class . '.php',
			'core/wizard/class-' . $class . '.php',
			'core/wizard/sidebars/class-' . $class . '.php',
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
			$file = WSSCD_INCLUDES_DIR . $path;
			if ( file_exists( $file ) ) {
				return $file;
			}
		}

		return null;
	}

}
