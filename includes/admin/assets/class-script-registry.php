<?php
/**
 * Script Registry Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets/class-script-registry.php
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
 * Script Registry Class
 *
 * Manages all script registrations for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Script_Registry {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $version;

	/**
	 * Plugin URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Script suffix (min or not).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $suffix;

	/**
	 * Registered scripts.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $scripts = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $version    Plugin version.
	 * @param string $plugin_url Plugin URL.
	 */
	public function __construct( string $version, string $plugin_url ) {
		$this->version    = $version;
		$this->plugin_url = $plugin_url;
		// Always use non-minified files for development
		$this->suffix = '';
	}

	/**
	 * Initialize script registry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->register_vendor_scripts();
		$this->register_core_scripts();
		$this->register_admin_scripts();
		$this->register_wizard_scripts();
		$this->register_analytics_scripts();
		$this->register_component_scripts();

		// Allow extensions
		do_action( 'wsscd_script_registry_init', $this );
	}

	/**
	 * Register vendor scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_vendor_scripts(): void {
		// Tom Select v2.4.3 - bundled locally for WordPress.org compliance.
		$this->add_script(
			'tom-select',
			array(
				'src'       => 'resources/assets/vendor/tom-select/tom-select.complete.min.js',
				'deps'      => array( 'jquery' ), // Explicit dependency to ensure proper load order
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'in_footer' => false, // Load in header to ensure it's available early
			)
		);
	}

	/**
	 * Register core scripts used across admin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_core_scripts(): void {
		// Console logger - captures JS console logs and sends to server debug.log
		$this->add_script(
			'wsscd-console-logger',
			array(
				'src'       => 'resources/assets/js/shared/console-logger.js',
				'deps'      => array(),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Component initialization script - only for plugin admin pages
		$this->add_script(
			'wsscd-init',
			array(
				'src'       => 'resources/assets/js/components/init.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Constants - only for wizard pages
		$this->add_script(
			'wsscd-constants-product-selection',
			array(
				'src'       => 'resources/assets/js/constants/product-selection-types.js',
				'deps'      => array(),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		$this->add_script(
			'wsscd-init-shared',
			array(
				'src'       => 'resources/assets/js/admin/init-shared.js',
				'deps'      => array(),
				'pages'     => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Shared utils - for plugin admin pages (always load on wizard pages)
		$this->add_script(
			'wsscd-shared-utils',
			array(
				'src'      => 'resources/assets/js/shared/utils.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'localize' => null,
			)
		);

		// Icon helper - SVG icon generation for JavaScript
		$this->add_script(
			'wsscd-icon-helper',
			array(
				'src'      => 'resources/assets/js/shared/icon-helper.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics', 'wsscd-dashboard', 'wsscd-settings', 'wsscd-tools', 'wsscd-notifications' ),
				'localize' => array(
					'object_name' => 'wsscdIcons',
					'data'        => array(
						'paths' => WSSCD_Icon_Helper::get_all_icons(),
					),
				),
			)
		);

		// Loader utility - unified loader system for consistent loading states
		$this->add_script(
			'wsscd-loader-utility',
			array(
				'src'      => 'resources/assets/js/shared/loader-utility.js',
				'deps'     => array( 'jquery', 'wsscd-icon-helper' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics', 'wsscd-dashboard', 'wsscd-tools', 'wsscd-settings' ),
				'localize' => null,
			)
		);

		// Debug logger - for wizard pages (file-based logging)
		$this->add_script(
			'wsscd-debug-logger',
			array(
				'src'       => 'resources/assets/js/shared/debug-logger.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Module Registry - declarative module instantiation system
		$this->add_script(
			'wsscd-module-registry',
			array(
				'src'       => 'resources/assets/js/shared/module-registry.js',
				'deps'      => array( 'jquery', 'wsscd-debug-logger' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Auto Events - convention-based event binding system
		$this->add_script(
			'wsscd-auto-events',
			array(
				'src'       => 'resources/assets/js/shared/auto-events.js',
				'deps'      => array( 'jquery', 'wsscd-debug-logger' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Row Factory - dynamic row generation system
		$this->add_script(
			'wsscd-row-factory',
			array(
				'src'       => 'resources/assets/js/shared/row-factory.js',
				'deps'      => array( 'jquery', 'wsscd-debug-logger', 'wsscd-icon-helper' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Input Filter - real-time input validation and filtering
		$this->add_script(
			'wsscd-input-filter',
			array(
				'src'       => 'resources/assets/js/shared/input-filter.js',
				'deps'      => array( 'jquery', 'wsscd-shared-notification-service' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// UI State Manager - declarative state-driven UI system
		$this->add_script(
			'wsscd-ui-state-manager',
			array(
				'src'       => 'resources/assets/js/shared/ui-state-manager.js',
				'deps'      => array( 'jquery', 'wsscd-debug-logger' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Theme color service - only for plugin admin pages
		$this->add_script(
			'wsscd-theme-color-service',
			array(
				'src'      => 'resources/assets/js/shared/theme-color-service.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'localize' => null,
			)
		);

		// Theme color initialization - only for plugin admin pages
		$this->add_script(
			'wsscd-theme-color-init',
			array(
				'src'      => 'resources/assets/js/shared/theme-color-init.js',
				'deps'     => array( 'jquery', 'wsscd-theme-color-service' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'localize' => null,
			)
		);

		// Admin script - only for plugin admin pages
		$this->add_script(
			'wsscd-admin',
			array(
				'src'      => 'resources/assets/js/admin/admin.js',
				'deps'     => array( 'jquery', 'wsscd-init', 'wp-util', 'wp-api' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'localize' => 'wsscdAdmin',
			)
		);

		// Validation error component - only for wizard pages
		$this->add_script(
			'wsscd-validation-error',
			array(
				'src'       => 'resources/assets/js/validation/validation-error.js',
				'deps'      => array( 'jquery', 'wsscd-shared-utils' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'in_footer' => false,
			)
		);

		// Validation manager - only for wizard pages
		$this->add_script(
			'wsscd-validation-manager',
			array(
				'src'       => 'resources/assets/js/validation/validation-manager.js',
				'deps'      => array( 'jquery', 'wsscd-shared-utils', 'wsscd-field-definitions', 'wsscd-validation-error', 'wsscd-debug-logger' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => array( 'wsscdValidationMessages', 'wsscdValidationConfig' ),
				'in_footer' => false,
			)
		);
	}

	/**
	 * Register admin-specific scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_admin_scripts(): void {
		// Main dashboard (free tier)
		$this->add_script(
			'wsscd-main-dashboard',
			array(
				'src'      => 'resources/assets/js/admin/dashboard/main-dashboard.js',
				'deps'     => array( 'jquery', 'wsscd-loader-utility' ),
				'pages'    => array( 'wsscd-dashboard' ),
				'localize' => 'wsscdDashboard',
			)
		);

		// Planner interactions - weekly campaign planner feature
		$this->add_script(
			'wsscd-planner-interactions',
			array(
				'src'      => 'resources/assets/js/admin/planner-interactions.js',
				'deps'     => array( 'jquery', 'wsscd-main-dashboard' ),
				'pages'    => array( 'wsscd-dashboard' ),
				'localize' => 'wsscdAdmin',
			)
		);

		// Upgrade banner dismiss handler
		$this->add_script(
			'wsscd-upgrade-banner-dismiss',
			array(
				'src'   => 'resources/assets/js/admin/upgrade-banner-dismiss.js',
				'deps'  => array( 'jquery' ),
				'pages' => array( 'wsscd-dashboard' ),
			)
		);

		// Admin notices dismiss handler (campaign expiration, currency change, etc.)
		$this->add_script(
			'wsscd-admin-notices-dismiss',
			array(
				'src'      => 'resources/assets/js/admin/admin-notices-dismiss.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'wsscd-dashboard', 'wsscd-campaigns', 'wsscd-analytics', 'wsscd-settings', 'wsscd-tools', 'wsscd-notifications' ),
				'localize' => 'wsscdAdminNotices',
			)
		);

		// Settings tooltips removed - now using centralized wsscd-tooltips

		// General settings page
		$this->add_script(
			'wsscd-settings-general',
			array(
				'src'       => 'resources/assets/js/admin/settings-general.js',
				'deps'      => array( 'jquery', 'wsscd-tooltips' ),
				'pages'     => array( 'wsscd-settings' ),
				'condition' => array( 'tab' => 'general' ),
				'localize'  => 'wsscdSettingsGeneral',
			)
		);

		// Notifications settings page
		$this->add_script(
			'wsscd-notifications-settings',
			array(
				'src'      => 'resources/assets/js/admin/notifications-settings.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'wsscd-notifications' ),
				'localize' => 'wsscdNotificationsL10n',
			)
		);

		// Queue management
		$this->add_script(
			'wsscd-queue-management',
			array(
				'src'       => 'resources/assets/js/admin/queue-management.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-notifications' ),
				'condition' => array( 'tab' => 'queue' ),
				'localize'  => 'wsscdQueueL10n',
			)
		);

		// Advanced settings page
		$this->add_script(
			'wsscd-settings-advanced',
			array(
				'src'       => 'resources/assets/js/admin/settings-advanced.js',
				'deps'      => array( 'jquery', 'wsscd-tooltips' ),
				'pages'     => array( 'wsscd-settings' ),
				'condition' => array( 'tab' => 'advanced' ),
				'localize'  => 'wsscdSettingsAdvanced',
			)
		);

		// Campaign list modals
		$this->add_script(
			'wsscd-campaign-list-modals',
			array(
				'src'       => 'resources/assets/js/admin/campaign-list-modals.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => null ), // Load on list page (no action parameter)
				'in_footer' => true,
			)
		);

		// Campaign overview panel
		$this->add_script(
			'wsscd-campaign-overview-panel',
			array(
				'src'       => 'resources/assets/js/admin/campaign-overview-panel.js',
				'deps'      => array( 'jquery', 'wsscd-admin', 'wsscd-loader-utility' ),
				'pages'     => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				// No action condition - loads on all actions for wsscd-campaigns page
				// JS checks URL and only opens panel when action=view
				'localize'  => 'wsscdOverviewPanel',
				'in_footer' => true,
			)
		);

		// Tools page
		$this->add_script(
			'wsscd-tools',
			array(
				'src'      => 'resources/assets/js/admin/tools.js',
				'deps'     => array( 'jquery', 'wsscd-icon-helper', 'wsscd-loader-utility' ),
				'pages'    => array( 'wsscd-tools' ),
				'localize' => 'wsscdAdmin',
			)
		);

		// Bulk actions - only load on list view, not wizard
		$this->add_script(
			'wsscd-bulk-actions',
			array(
				'src'       => 'resources/assets/js/admin/bulk-actions.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => null ), // Exclude wizard pages
			)
		);

		// Note: Draft management scripts removed - functionality handled by PHP AJAX handler
	}

	/**
	 * Register wizard module scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_wizard_scripts(): void {
		// Module loader - required for wizard
		$this->add_script(
			'wsscd-module-loader',
			array(
				'src'       => 'resources/assets/js/shared/module-loader.js',
				'deps'      => array(),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);

		// Wizard core modules
		$this->add_script(
			'wsscd-wizard-event-bus',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-event-bus.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		$this->add_script(
			'wsscd-wizard-state-manager',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-state-manager.js',
				'deps'      => array( 'jquery', 'wsscd-shared-base-state', 'wsscd-wizard-event-bus' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// wizard-validation-service.js removed - ValidationManager handles all validation directly
		// wizard-persistence-service.js removed - Navigation saves handle all data persistence
		// wizard-smart-save.js removed - Simplified to navigation saves only (WordPress standard)
		// wizard-session-keeper.js removed - WordPress native session management sufficient

		$this->add_script(
			'wsscd-wizard-lifecycle',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-lifecycle.js',
				'deps'      => array( 'jquery', 'wsscd-wizard-event-bus' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		$wizard_orchestrator_deps = array(
			'jquery',
			'wsscd-shared-base-orchestrator',
			'wsscd-wizard-event-bus',
			'wsscd-wizard-state-manager',
			'wsscd-validation-manager', // Direct dependency on ValidationManager
			'wsscd-loader-utility', // Unified loader system for consistent loading states
		);
		if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			$wizard_orchestrator_deps[] = 'wsscd-debug-logger';
		}
		$this->add_script(
			'wsscd-wizard-orchestrator',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-orchestrator.js',
				'deps'      => array_merge(
					$wizard_orchestrator_deps,
					array(
						'wsscd-wizard-navigation',
						'wsscd-wizard-lifecycle',
						'wsscd-wizard-step-loader-factory',
					)
				),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Core wizard files
		$this->add_script(
			'wsscd-wizard',
			array(
				'src'       => 'resources/assets/js/wizard/wizard.js',
				'deps'      => array(
					'jquery',
					'wp-i18n',
					'wsscd-module-loader',
					'wsscd-shared-utils', // Required for WSSCD.Utils.extend
					'wsscd-shared-ajax-service',
					'wsscd-shared-ui',
					'wsscd-shared-notification-service',
					'wsscd-validation-error',
					'wsscd-wizard-save-indicator',
					'wsscd-wizard-session-monitor', // Session expiration monitoring
					'wsscd-wizard-orchestrator',
					'wsscd-tooltips', // Add tooltips as core dependency
				),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => array( 'wsscdWizardData', 'wsscdAdmin', 'wsscdSettings' ),
			)
		);

		// Wizard navigation
		$this->add_script(
			'wsscd-wizard-navigation',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-navigation.js',
				'deps'      => array(
					'jquery',
					'wsscd-pro-feature-gate',
					'wsscd-skeleton-templates',
				),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => 'wsscdNavigation',
				'in_footer' => false,
			)
		);

		// Wizard completion modal
		$this->add_script(
			'wsscd-wizard-completion-modal',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-completion-modal.js',
				'deps'      => array( 'jquery', 'wsscd-icon-helper' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Wizard session monitor
		$this->add_script(
			'wsscd-wizard-session-monitor',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-session-monitor.js',
				'deps'      => array(
					'jquery',
					'wsscd-shared-ajax-service', // Required for session status checks
					'wsscd-shared-notification-service', // Required for warnings/errors
					'wsscd-wizard-event-bus', // Required for event communication
				),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Shared modules
		$this->register_wizard_shared_modules();

		// Step-specific modules
		$this->register_wizard_step_modules();
	}

	/**
	 * Register wizard shared modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_wizard_shared_modules(): void {
		// Register AJAX service separately for both wizard AND analytics pages
		// This must be done before registering other shared modules that depend on it
		$this->add_script(
			'wsscd-shared-ajax-service',
			array(
				'src'       => 'resources/assets/js/shared/ajax-service.js',
				'deps'      => array( 'jquery', 'wsscd-init-shared', 'wsscd-error-handler' ),
				'pages'     => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'condition' => array(), // No condition - load on both wizard and analytics pages
				'in_footer' => false,
			)
		);

		// Register PRO feature gate (independent service used by navigation and other modules)
		$this->add_script(
			'wsscd-pro-feature-gate',
			array(
				'src'       => 'resources/assets/js/shared/pro-feature-gate.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'in_footer' => false,
			)
		);

		$this->add_script(
			'wsscd-skeleton-templates',
			array(
				'src'       => 'resources/assets/js/wizard/skeleton-templates.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'in_footer' => false,
			)
		);

		$shared_modules = array(
			'wsscd-shared-base-state'           => array(
				'src'  => 'resources/assets/js/shared/base-state.js',
				'deps' => array( 'jquery', 'wsscd-shared-utils', 'wsscd-field-definitions' ),
			),
			'wsscd-shared-base-api'             => array(
				'src'  => 'resources/assets/js/shared/base-api.js',
				'deps' => array( 'jquery', 'wsscd-shared-ajax-service', 'wsscd-error-handler' ),
			),
			'wsscd-step-persistence'            => array(
				'src'  => 'resources/assets/js/shared/mixins/step-persistence.js',
				'deps' => array( 'jquery', 'wsscd-shared-utils', 'wsscd-shared-ajax-service', 'wsscd-error-handler', 'wsscd-validation-manager' ),
			),
			'wsscd-shared-base-orchestrator'    => array(
				'src'  => 'resources/assets/js/shared/base-orchestrator.js',
				'deps' => array( 'jquery', 'wsscd-shared-base-state', 'wsscd-shared-utils', 'wsscd-module-utilities', 'wsscd-error-handler', 'wsscd-event-manager-mixin' ),
			),
			'wsscd-shared-ui'                   => array(
				'src'  => 'resources/assets/js/shared/ui-utilities.js',
				'deps' => array( 'jquery', 'wsscd-init-shared' ),
			),
			'wsscd-shared-system-check'         => array(
				'src'  => 'resources/assets/js/admin/system-check.js',
				'deps' => array( 'jquery' ),
			),
			'wsscd-step-registry'               => array(
				'src'  => 'resources/assets/js/wizard/step-registry.js',
				'deps' => array( 'jquery' ),
			),
			'wsscd-shared-notification-service' => array(
				'src'  => 'resources/assets/js/shared/notification-service.js',
				'deps' => array( 'jquery', 'wsscd-init-shared' ),
			),
			'wsscd-wizard-step-config'          => array(
				'src'  => 'resources/assets/js/wizard/step-config.js',
				'deps' => array(),
			),
			'wsscd-wizard-step-loader-factory'  => array(
				'src'  => 'resources/assets/js/wizard/step-loader-factory.js',
				'deps' => array( 'jquery', 'wsscd-wizard-step-config' ),
			),
			'wsscd-module-utilities'            => array(
				'src'  => 'resources/assets/js/shared/module-utilities.js',
				'deps' => array( 'jquery' ),
			),
			'wsscd-error-handler'               => array(
				'src'  => 'resources/assets/js/shared/error-handler.js',
				'deps' => array( 'jquery', 'wsscd-module-utilities' ),
			),
			'wsscd-event-manager-mixin'         => array(
				'src'  => 'resources/assets/js/shared/mixins/event-manager-mixin.js',
				'deps' => array( 'jquery', 'wsscd-module-utilities' ),
			),
			'wsscd-wizard-save-indicator'       => array(
				'src'  => 'resources/assets/js/wizard/wizard-save-indicator.js',
				'deps' => array( 'jquery', 'wsscd-icon-helper' ),
			),
			'wsscd-field-definitions'           => array(
				'src'  => 'resources/assets/js/shared/field-definitions.js',
				'deps' => array( 'jquery', 'wsscd-admin' ), // Depends on wsscd-admin for localized data
			),
			'wsscd-tom-select-base'             => array(
				'src'  => 'resources/assets/js/shared/tom-select-base.js',
				'deps' => array( 'jquery', 'tom-select', 'wsscd-shared-utils', 'wsscd-icon-helper' ),
			),
			'wsscd-sidebar-contextual'          => array(
				'src'       => 'resources/assets/js/wizard/sidebar-contextual.js',
				'deps'      => array( 'jquery', 'wsscd-shared-notification-service', 'wsscd-icon-helper' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => array( 'wsscdWizardData', 'wsscdAdmin' ),
			),
			'wsscd-card-collapse'               => array(
				'src'  => 'resources/assets/js/shared/card-collapse.js',
				'deps' => array( 'jquery' ),
			),
		);

		foreach ( $shared_modules as $handle => $config ) {
			// Modules needed by analytics page (via wsscd-shared-ajax-service dependency chain)
			// Note: wsscd-shared-utils is already registered separately for both pages
			$analytics_shared_modules = array(
				'wsscd-module-utilities',
				'wsscd-error-handler',
				'wsscd-shared-notification-service',
			);

			if ( in_array( $handle, $analytics_shared_modules ) ) {
				$config['pages']     = array( 'wsscd-campaigns', 'wsscd-analytics' );
				$config['condition'] = array(); // No condition for analytics
			} else {
				$config['pages']     = array( 'wsscd-campaigns' );
				$config['condition'] = array( 'action' => 'wizard' );
			}

			$config['lazy']      = false;
			$config['in_footer'] = false;
			$this->add_script( $handle, $config );
		}
	}

	/**
	 * Register wizard step modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_wizard_step_modules(): void {
		// Basic step modules
		$this->register_basic_step_modules();

		// Products step modules
		$this->register_products_step_modules();

		// Discounts step modules
		$this->register_discounts_step_modules();

		// Schedule step modules
		$this->register_schedule_step_modules();

		// Review step modules
		$this->register_review_step_modules();
	}

	/**
	 * Register analytics scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_analytics_scripts(): void {
		// Note: wsscd-shared-ajax-service is already registered in register_wizard_shared_modules()
		// with pages set to both 'wsscd-campaigns' and 'wsscd-analytics'

		// Chart.js v4.5.1 - bundled locally for WordPress.org compliance.
		$this->add_script(
			'chart-js',
			array(
				'src'       => 'resources/assets/vendor/chart-js/chart.umd.min.js',
				'deps'      => array(),
				'pages'     => array( 'wsscd-analytics' ),
				'condition' => array(),
			)
		);

		// Pro-only: Analytics dashboard script (only register if file exists)
		$analytics_dashboard_file = WSSCD_PLUGIN_DIR . 'resources/assets/js/analytics/analytics-dashboard.js';
		if ( file_exists( $analytics_dashboard_file ) ) {
			$this->add_script(
				'wsscd-analytics-dashboard',
				array(
					'src'      => 'resources/assets/js/analytics/analytics-dashboard.js',
					'deps'     => array( 'jquery', 'chart-js', 'wsscd-shared-utils', 'wsscd-loader-utility', 'wsscd-shared-ajax-service', 'wsscd-shared-notification-service', 'wsscd-theme-color-init' ),
					'pages'    => array( 'wsscd-analytics' ),
					'localize' => 'wsscdAnalytics',
				)
			);
		}

		// Frontend analytics tracking script
		$this->add_script(
			'wsscd-analytics-tracking',
			array(
				'src'       => 'resources/assets/js/analytics/wsscd-analytics-tracking.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'frontend' ),
				'localize'  => 'wsscdAnalyticsTracking',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Register component scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_component_scripts(): void {
		// Tooltip component - Global (available on all SCD pages)
		$this->add_script(
			'wsscd-tooltips',
			array(
				'src'       => 'resources/assets/js/shared/wsscd-tooltips.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'condition' => null,
				'localize'  => null,
				'in_footer' => true,
			)
		);
	}

	/**
	 * Add a script definition.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @param array  $config Script configuration.
	 * @return void
	 */
	public function add_script( string $handle, array $config ): void {
		if ( ! empty( $config['src'] ) && empty( $config['external'] ) ) {
			$config['src'] = $this->get_script_path( $config['src'] );
		}

		$this->scripts[ $handle ] = wp_parse_args(
			$config,
			array(
				'src'       => '',
				'deps'      => array(),
				'pages'     => array(),
				'condition' => array(),
				'localize'  => null,
				'lazy'      => false,
				'async'     => false,
				'defer'     => false,
				'in_footer' => true,
				'external'  => false,
			)
		);
	}

	/**
	 * Get the correct script path based on debug mode and file availability.
	 *
	 * @since 1.0.0
	 * @param string $src The script source path.
	 * @return string The resolved script path.
	 */
	private function get_script_path( string $src ): string {
		// For development, always use source files
		// Simply return the source path as-is
		return $src;
	}

	/**
	 * Get script configuration.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @return array|null Script config or null if not found.
	 */
	public function get_script( string $handle ): ?array {
		return $this->scripts[ $handle ] ?? null;
	}

	/**
	 * Get all scripts.
	 *
	 * @since 1.0.0
	 * @return array All registered scripts.
	 */
	public function get_all_scripts(): array {
		return $this->scripts;
	}

	/**
	 * Get scripts for a specific page.
	 *
	 * SECURITY: This method ONLY reads URL params for script filtering.
	 * No data processing occurs. Capability is checked for defense in depth.
	 *
	 * @since 1.0.0
	 * @param string $page Page identifier.
	 * @return array Scripts for the page.
	 */
	public function get_scripts_for_page( string $page ): array {
		// Defense in depth: verify user has admin capability.
		// Asset loader already enforces this via menu registration.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for script filtering only. Capability checked above. Value validated against whitelist ('wizard').
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : null;

		$filtered_scripts = array_filter(
			$this->scripts,
			function ( $script ) use ( $page, $action ) {
				if ( ! in_array( $page, $script['pages'], true ) ) {
					return false;
				}

				if ( ! empty( $script['condition']['action'] ) ) {
					if ( 'wizard' === $script['condition']['action'] ) {
						return 'wizard' === $action;
					} elseif ( null === $script['condition']['action'] ) {
						return 'wizard' !== $action;
					}
				}

				return true;
			}
		);

		return $filtered_scripts;
	}

	/**
	 * Get scripts by condition.
	 *
	 * @since 1.0.0
	 * @param array $condition Condition to match.
	 * @return array Matching scripts.
	 */
	public function get_scripts_by_condition( array $condition ): array {
		return array_filter(
			$this->scripts,
			function ( $script ) use ( $condition ) {
				foreach ( $condition as $key => $value ) {
					if ( ! isset( $script['condition'][ $key ] ) || $script['condition'][ $key ] !== $value ) {
						return false;
					}
				}
				return true;
			}
		);
	}

	/**
	 * Register basic step modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_basic_step_modules(): void {
		$modules = array(
			'wsscd-basic-state'        => 'basic-state.js',
			'wsscd-basic-api'          => 'basic-api.js',
			'wsscd-basic-fields'       => 'basic-fields.js',
			'wsscd-basic-orchestrator' => 'basic-orchestrator.js',
		);

		$this->register_step_module_group( 'basic', $modules );
	}

	/**
	 * Register products step modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_products_step_modules(): void {
		$modules = array(
			'wsscd-products-state'                => 'products-state.js',
			'wsscd-products-api'                  => 'products-api.js',
			'wsscd-products-picker'               => 'products-picker.js',
			'wsscd-products-conditions-validator' => 'products-conditions-validator.js',
			'wsscd-products-orchestrator'         => 'products-orchestrator.js',
		);

		$this->register_step_module_group( 'products', $modules );
	}

	/**
	 * Register discounts step modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_discounts_step_modules(): void {
		$this->add_script(
			'wsscd-discounts-config',
			array(
				'src'       => 'resources/assets/js/steps/discounts/discounts-config.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);

		$modules = array(
			'wsscd-discounts-api'           => 'discounts-api.js',
			'wsscd-discounts-state'         => 'discounts-state.js',
			'wsscd-discounts-conditions'    => 'discounts-conditions.js',
			'wsscd-discounts-type-registry' => 'discounts-type-registry.js',
			'wsscd-complex-field-handler'   => 'complex-field-handler.js',
			'wsscd-discounts-integration'   => 'discounts-integration.js',
			'wsscd-discounts-orchestrator'  => 'discounts-orchestrator.js',
		);

		$this->register_step_module_group( 'discounts', $modules );

		$this->register_discount_type_scripts();

		// Badge settings - for configuring discount badges
		$this->add_script(
			'wsscd-badge-settings',
			array(
				'src'       => 'resources/assets/js/wizard/badge-settings.js',
				'deps'      => array( 'jquery', 'wp-color-picker' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);
	}

	/**
	 * Register discount type scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_discount_type_scripts(): void {
		// Base discount type
		$this->add_script(
			'wsscd-discount-type-base',
			array(
				'src'       => 'resources/assets/js/steps/discounts/base-discount.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);

		// Specific discount types
		$discount_types = array(
			'wsscd-discount-type-percentage'      => array(
				'file' => 'percentage-discount.js',
				'deps' => array( 'jquery', 'wsscd-discount-type-base' ),
			),
			'wsscd-discount-type-fixed'           => array(
				'file' => 'fixed-discount.js',
				'deps' => array( 'jquery', 'wsscd-discount-type-base' ),
			),
			'wsscd-discount-type-tiered'          => array(
				'file' => 'tiered-discount.js',
				'deps' => array( 'jquery', 'wsscd-discount-type-base' ),
			),
			'wsscd-discount-type-bogo'            => array(
				'file' => 'bogo-discount.js',
				'deps' => array( 'jquery', 'wsscd-discount-type-base' ),
			),
			'wsscd-discount-type-spend-threshold' => array(
				'file' => 'spend-threshold.js',
				'deps' => array( 'jquery', 'wsscd-discount-type-base' ),
			),
		);

		foreach ( $discount_types as $handle => $config ) {
			// Pro-only discount types: only register if file exists
			$file_path = WSSCD_PLUGIN_DIR . 'resources/assets/js/steps/discounts/' . $config['file'];
			if ( ! file_exists( $file_path ) ) {
				continue;
			}

			$this->add_script(
				$handle,
				array(
					'src'       => 'resources/assets/js/steps/discounts/' . $config['file'],
					'deps'      => $config['deps'],
					'pages'     => array( 'wsscd-campaigns' ),
					'condition' => array( 'action' => 'wizard' ),
					'localize'  => null,
				)
			);
		}

		// Note: Type registry script will be registered after all discount types

		// Type registry that depends on all types (register after all dependencies)
		$this->add_script(
			'wsscd-discounts-type-registry',
			array(
				'src'       => 'resources/assets/js/steps/discounts/discounts-type-registry.js',
				'deps'      => array(
					'jquery',
					'wsscd-discounts-config',
					'wsscd-discount-type-base',
				),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);
	}

	/**
	 * Register schedule step modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_schedule_step_modules(): void {
		$this->add_script(
			'wsscd-timeline-visualizer',
			array(
				'src'       => 'resources/assets/js/components/timeline-visualizer.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_script(
			'wsscd-date-time-picker',
			array(
				'src'       => 'resources/assets/js/components/date-time-picker.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$modules = array(
			'wsscd-schedule-debug'        => 'schedule-debug.js',
			'wsscd-schedule-state'        => 'schedule-state.js',
			'wsscd-schedule-api'          => 'schedule-api.js',
			'wsscd-schedule-config'       => 'schedule-config.js',
			'wsscd-schedule-orchestrator' => 'schedule-orchestrator.js',
		);

		$this->register_step_module_group( 'schedule', $modules );
	}

	/**
	 * Register review step modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_review_step_modules(): void {
		$modules = array(
			'wsscd-review-state'        => 'review-state.js',
			'wsscd-review-api'          => 'review-api.js',
			'wsscd-review-components'   => 'review-components.js',
			'wsscd-review-orchestrator' => 'review-orchestrator.js',
		);

		$this->register_step_module_group( 'review', $modules );

		$this->add_script(
			'wsscd-review-health-check',
			array(
				'src'       => 'resources/assets/js/wizard/review-health-check.js',
				'deps'      => array( 'jquery', 'wsscd-admin', 'wsscd-icon-helper' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);
	}

	/**
	 * Register a group of step modules.
	 *
	 * @since 1.0.0
	 * @param string $step    Step name.
	 * @param array  $modules Module mappings.
	 * @return void
	 */
	private function register_step_module_group( string $step, array $modules ): void {
		foreach ( $modules as $handle => $filename ) {
			$config = array(
				'src'   => "resources/assets/js/steps/{$step}/{$filename}",
				'deps'  => $this->get_module_dependencies( $handle ),
				'pages' => array( 'wsscd-campaigns' ),
				'lazy'  => false, // Disable lazy loading to ensure proper initialization
			);

			// All step modules need to load on all wizard pages since orchestrators depend on them
			$config['condition'] = array( 'action' => 'wizard' );

			$this->add_script( $handle, $config );
		}
	}

	/**
	 * Get module dependencies based on handle.
	 *
	 * @since 1.0.0
	 * @param string $handle Module handle.
	 * @return array Dependencies.
	 */
	private function get_module_dependencies( string $handle ): array {
		// Define dependency mappings
		$dependency_map = array(
			'state'        => array( 'jquery', 'wsscd-shared-base-state', 'wsscd-event-manager-mixin' ),
			'api'          => array( 'jquery', 'wsscd-shared-ajax-service', 'wsscd-shared-base-api', 'wsscd-error-handler' ),
			'orchestrator' => array( 'jquery', 'wsscd-shared-base-orchestrator' ),
			'loader'       => array( 'jquery' ),
			'validator'    => array( 'jquery', 'wsscd-validation-manager' ),
		);

		// Special handling for orchestrator modules - they need all their step modules
		if ( strpos( $handle, '-orchestrator' ) !== false ) {
			$base_deps = array( 'jquery', 'wsscd-shared-base-orchestrator', 'wsscd-module-registry', 'wsscd-shared-utils', 'wsscd-event-manager-mixin', 'wsscd-step-persistence', 'wsscd-step-registry' );

			$step = str_replace( array( 'wsscd-', '-orchestrator' ), '', $handle );

			$step_modules = array(
				'basic'     => array( 'wsscd-basic-state', 'wsscd-basic-api', 'wsscd-basic-fields' ),
				'products'  => array( 'wsscd-constants-product-selection', 'wsscd-tom-select-base', 'wsscd-products-state', 'wsscd-products-api', 'wsscd-products-picker' ),
				'discounts' => array( 'wsscd-discounts-config', 'wsscd-discounts-state', 'wsscd-discounts-api', 'wsscd-discounts-conditions', 'wsscd-discounts-type-registry' ),
				'schedule'  => array( 'jquery-ui-datepicker', 'wsscd-schedule-debug', 'wsscd-schedule-state', 'wsscd-schedule-api', 'wsscd-schedule-config' ),
				'review'    => array( 'wsscd-review-state', 'wsscd-review-api', 'wsscd-review-components' ),
			);

			if ( isset( $step_modules[ $step ] ) ) {
				$base_deps = array_merge( $base_deps, $step_modules[ $step ] );
			}

			return $base_deps;
		}

		// Scripts that need wsscd-shared-utils for utility functions
		$utils_required = array(
			'wsscd-discount-type-fixed',
		);

		// Picker module needs tom-select library, base, utilities, and icon helper
		if ( 'wsscd-products-picker' === $handle ) {
			return array( 'jquery', 'tom-select', 'wsscd-tom-select-base', 'wsscd-module-utilities', 'wsscd-event-manager-mixin', 'wsscd-error-handler', 'wsscd-icon-helper' );
		}

		// Step modules that need icon helper
		$modules_needing_icons = array(
			'wsscd-products-orchestrator',
			'wsscd-products-conditions-validator',
			'wsscd-schedule-orchestrator',
			'wsscd-review-components',
			'wsscd-discount-type-tiered',
			'wsscd-discount-type-spend-threshold',
		);

		// Products modules that need constants
		$products_modules_with_constants = array(
			'wsscd-products-orchestrator',
			'wsscd-products-state',
		);

		if ( in_array( $handle, $products_modules_with_constants ) ) {
			$base_deps = $this->get_base_deps_for_type( $handle, $dependency_map );
			array_unshift( $base_deps, 'wsscd-constants-product-selection' );
			return $base_deps;
		}

		if ( in_array( $handle, $utils_required ) ) {
			// Find base dependencies
			$base_deps = array( 'jquery' );
			foreach ( $dependency_map as $type => $deps ) {
				if ( strpos( $handle, $type ) !== false ) {
					$base_deps = $deps;
					break;
				}
			}
			if ( ! in_array( 'wsscd-shared-utils', $base_deps ) ) {
				$base_deps[] = 'wsscd-shared-utils';
			}
			return $base_deps;
		}

		// Add icon-helper to modules that use it
		if ( in_array( $handle, $modules_needing_icons ) ) {
			$base_deps = $this->get_base_deps_for_type( $handle, $dependency_map );
			if ( ! in_array( 'wsscd-icon-helper', $base_deps ) ) {
				$base_deps[] = 'wsscd-icon-helper';
			}
			return $base_deps;
		}

		// Determine dependencies based on module type
		foreach ( $dependency_map as $type => $deps ) {
			if ( strpos( $handle, $type ) !== false ) {
				return $deps;
			}
		}

		return array( 'jquery' );
	}

	/**
	 * Get base dependencies for a handle based on its type.
	 *
	 * @since 1.0.0
	 * @param string $handle Module handle.
	 * @param array  $dependency_map Dependency mappings.
	 * @return array Base dependencies.
	 */
	private function get_base_deps_for_type( string $handle, array $dependency_map ): array {
		foreach ( $dependency_map as $type => $deps ) {
			if ( strpos( $handle, '-' . $type ) !== false ) {
				return $deps;
			}
		}
		return array( 'jquery' );
	}
}
