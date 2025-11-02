<?php
/**
 * Script Registry Class
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets
 */

declare(strict_types=1);


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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Script_Registry {

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
		do_action( 'scd_script_registry_init', $this );
	}

	/**
	 * Register vendor scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_vendor_scripts(): void {
		// Tom Select
		$this->add_script(
			'tom-select',
			array(
				'src'       => 'resources/assets/vendor/tom-select/tom-select.js',
				'deps'      => array( 'jquery' ), // Explicit dependency to ensure proper load order
				'pages'     => array( 'scd-campaigns' ),
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
			'scd-console-logger',
			array(
				'src'       => 'resources/assets/js/admin/console-logger.js',
				'deps'      => array(),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// wp.i18n polyfill - only for plugin admin pages
		$this->add_script(
			'scd-i18n-polyfill',
			array(
				'src'       => 'resources/assets/js/polyfills/wp-i18n-polyfill.js',
				'deps'      => array(),
				'pages'     => array( 'scd-campaigns', 'scd-analytics' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Component initialization script - only for plugin admin pages
		$this->add_script(
			'scd-init',
			array(
				'src'       => 'resources/assets/js/components/init.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns', 'scd-analytics' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Constants - only for wizard pages
		$this->add_script(
			'scd-constants-product-selection',
			array(
				'src'       => 'resources/assets/js/constants/product-selection-types.js',
				'deps'      => array(),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Initialize shared namespace - only for plugin admin pages
		$this->add_script(
			'scd-init-shared',
			array(
				'src'       => 'resources/assets/js/admin/init-shared.js',
				'deps'      => array(),
				'pages'     => array( 'scd-campaigns', 'scd-analytics' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Shared utils - for plugin admin pages (always load on wizard pages)
		$this->add_script(
			'scd-shared-utils',
			array(
				'src'      => 'resources/assets/js/shared/utils.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics' ),
				'localize' => null,
			)
		);

		// Debug logger - for wizard pages (file-based logging)
		$this->add_script(
			'scd-debug-logger',
			array(
				'src'       => 'resources/assets/js/shared/debug-logger.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Theme color service - only for plugin admin pages
		$this->add_script(
			'scd-theme-color-service',
			array(
				'src'      => 'resources/assets/js/shared/theme-color-service.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics' ),
				'localize' => null,
			)
		);

		// Theme color initialization - only for plugin admin pages
		$this->add_script(
			'scd-theme-color-init',
			array(
				'src'      => 'resources/assets/js/shared/theme-color-init.js',
				'deps'     => array( 'jquery', 'scd-theme-color-service' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics' ),
				'localize' => null,
			)
		);

		// Admin script - only for plugin admin pages
		$this->add_script(
			'scd-admin',
			array(
				'src'      => 'resources/assets/js/admin/admin.js',
				'deps'     => array( 'jquery', 'scd-init', 'wp-util', 'wp-api' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics' ),
				'localize' => 'scdAdmin',
			)
		);

		// Validation error component - only for wizard pages
		$this->add_script(
			'scd-validation-error',
			array(
				'src'       => 'resources/assets/js/validation/validation-error.js',
				'deps'      => array( 'jquery', 'scd-shared-utils' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'in_footer' => false,
			)
		);

		// Validation manager - only for wizard pages
		$this->add_script(
			'scd-validation-manager',
			array(
				'src'       => 'resources/assets/js/validation/validation-manager.js',
				'deps'      => array( 'jquery', 'scd-shared-utils', 'scd-field-definitions', 'scd-validation-error', 'scd-debug-logger' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => array( 'scdValidationMessages', 'scdValidationConfig' ),
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
			'scd-main-dashboard',
			array(
				'src'      => 'resources/assets/js/admin/dashboard/main-dashboard.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'scd-dashboard' ),
				'localize' => 'scdDashboard',
			)
		);

		// Timeline interactions - weekly campaign timeline feature
		$this->add_script(
			'scd-timeline-interactions',
			array(
				'src'      => 'resources/assets/js/admin/timeline-interactions.js',
				'deps'     => array( 'jquery', 'scd-main-dashboard' ),
				'pages'    => array( 'scd-dashboard' ),
				'localize' => 'scdAdmin',
			)
		);

		// Upgrade banner dismiss handler
		$this->add_script(
			'scd-upgrade-banner-dismiss',
			array(
				'src'   => 'resources/assets/js/admin/upgrade-banner-dismiss.js',
				'deps'  => array( 'jquery' ),
				'pages' => array( 'scd-dashboard' ),
			)
		);

		// Settings tooltips removed - now using centralized scd-tooltips

		// General settings page
		$this->add_script(
			'scd-settings-general',
			array(
				'src'       => 'resources/assets/js/admin/settings-general.js',
				'deps'      => array( 'jquery', 'scd-tooltips' ),
				'pages'     => array( 'scd-settings' ),
				'condition' => array( 'tab' => 'general' ),
				'localize'  => 'scdSettingsGeneral',
			)
		);

		// Notifications settings page
		$this->add_script(
			'scd-notifications-settings',
			array(
				'src'      => 'assets/js/admin/notifications-settings.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'scd-notifications' ),
				'localize' => 'scdNotificationsL10n',
			)
		);

		// Queue management
		$this->add_script(
			'scd-queue-management',
			array(
				'src'       => 'assets/js/admin/queue-management.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-notifications' ),
				'condition' => array( 'tab' => 'queue' ),
				'localize'  => 'scdQueueL10n',
			)
		);

		// Advanced settings page
		$this->add_script(
			'scd-settings-advanced',
			array(
				'src'       => 'resources/assets/js/admin/settings-advanced.js',
				'deps'      => array( 'jquery', 'scd-tooltips' ),
				'pages'     => array( 'scd-settings' ),
				'condition' => array( 'tab' => 'advanced' ),
				'localize'  => 'scdSettingsAdvanced',
			)
		);

		// Performance settings page
		$this->add_script(
			'scd-settings-performance',
			array(
				'src'       => 'resources/assets/js/admin/settings-performance.js',
				'deps'      => array( 'jquery', 'scd-tooltips' ),
				'pages'     => array( 'scd-settings' ),
				'condition' => array( 'tab' => 'performance' ),
				'localize'  => 'scdSettingsPerformance',
			)
		);

		// Campaign list modals
		$this->add_script(
			'scd-campaign-list-modals',
			array(
				'src'       => 'resources/assets/js/admin/campaign-list-modals.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => null ), // Load on list page (no action parameter)
				'in_footer' => true,
			)
		);

		// Tools page
		$this->add_script(
			'scd-tools',
			array(
				'src'      => 'resources/assets/js/admin/tools.js',
				'deps'     => array( 'jquery' ),
				'pages'    => array( 'scd-tools' ),
				'localize' => 'scdAdmin',
			)
		);

		// Campaign management - only load on list view, not wizard
		$this->add_script(
			'scd-campaigns-list',
			array(
				'src'       => 'resources/assets/js/admin/campaigns-list.js',
				'deps'      => array( 'jquery', 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => null ), // Exclude wizard pages
			)
		);

		// Bulk actions - only load on list view, not wizard
		$this->add_script(
			'scd-bulk-actions',
			array(
				'src'       => 'resources/assets/js/admin/bulk-actions.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
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
			'scd-module-loader',
			array(
				'src'       => 'resources/assets/js/shared/module-loader.js',
				'deps'      => array(),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);

		// Wizard core modules
		$this->add_script(
			'scd-wizard-event-bus',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-event-bus.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		$this->add_script(
			'scd-wizard-state-manager',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-state-manager.js',
				'deps'      => array( 'jquery', 'scd-wizard-event-bus' ),
				'pages'     => array( 'scd-campaigns' ),
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
			'scd-wizard-lifecycle',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-lifecycle.js',
				'deps'      => array( 'jquery', 'scd-wizard-event-bus' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		$wizard_orchestrator_deps = array(
			'jquery',
			'scd-shared-base-orchestrator',
			'scd-wizard-event-bus',
			'scd-wizard-state-manager',
			'scd-validation-manager', // Direct dependency on ValidationManager
		);
		if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
			$wizard_orchestrator_deps[] = 'scd-debug-logger';
		}
		$this->add_script(
			'scd-wizard-orchestrator',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-orchestrator.js',
				'deps'      => array_merge(
					$wizard_orchestrator_deps,
					array(
						'scd-wizard-navigation',
						'scd-wizard-lifecycle',
						'scd-wizard-step-loader-factory',
					)
				),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Core wizard files
		$this->add_script(
			'scd-wizard',
			array(
				'src'       => 'resources/assets/js/wizard/wizard.js',
				'deps'      => array(
					'jquery',
					'scd-i18n-polyfill',
					'scd-module-loader',
					'scd-step-bridge',
					'scd-shared-utils', // Required for SCD.Utils.extend
					'scd-shared-ajax-service',
					'scd-shared-ui',
					'scd-shared-notification-service',
					'scd-validation-error',
					'scd-wizard-save-indicator',
					'scd-wizard-orchestrator',
					'scd-tooltips', // Add tooltips as core dependency
					'scd-sidebar-collapse', // Required for collapsible sidebar sections
				),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => array( 'scdWizardData', 'scdAdmin' ),
			)
		);

		// Wizard navigation
		$this->add_script(
			'scd-wizard-navigation',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-navigation.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => 'scdNavigation',
				'in_footer' => false,
			)
		);

		// Wizard completion modal
		$this->add_script(
			'scd-wizard-completion-modal',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-completion-modal.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
				'in_footer' => false,
			)
		);

		// Wizard session monitor
		$this->add_script(
			'scd-wizard-session-monitor',
			array(
				'src'       => 'resources/assets/js/wizard/wizard-session-monitor.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
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
			'scd-shared-ajax-service',
			array(
				'src'       => 'resources/assets/js/admin/ajax-service.js',
				'deps'      => array( 'jquery', 'scd-init-shared', 'scd-error-handler' ),
				'pages'     => array( 'scd-campaigns', 'scd-analytics' ),
				'condition' => array(), // No condition - load on both wizard and analytics pages
				'in_footer' => false,
			)
		);

		$shared_modules = array(
			'scd-shared-base-state'           => array(
				'src'  => 'resources/assets/js/shared/base-state.js',
				'deps' => array( 'jquery', 'scd-shared-utils', 'scd-field-definitions' ),
			),
			'scd-shared-base-api'             => array(
				'src'  => 'resources/assets/js/shared/base-api.js',
				'deps' => array( 'jquery', 'scd-shared-ajax-service', 'scd-error-handler' ),
			),
			'scd-step-persistence'            => array(
				'src'  => 'resources/assets/js/shared/mixins/step-persistence.js',
				'deps' => array( 'jquery', 'scd-shared-utils', 'scd-shared-ajax-service', 'scd-error-handler', 'scd-validation-manager' ),
			),
			'scd-shared-base-orchestrator'    => array(
				'src'  => 'resources/assets/js/shared/base-orchestrator.js',
				'deps' => array( 'jquery', 'scd-shared-base-state', 'scd-shared-utils', 'scd-module-utilities', 'scd-error-handler', 'scd-event-manager-mixin' ),
			),
			'scd-shared-ui'                   => array(
				'src'  => 'resources/assets/js/admin/ui-utilities.js',
				'deps' => array( 'jquery', 'scd-init-shared' ),
			),
			'scd-shared-system-check'         => array(
				'src'  => 'resources/assets/js/admin/system-check.js',
				'deps' => array( 'jquery' ),
			),
			'scd-step-registry'               => array(
				'src'  => 'resources/assets/js/wizard/step-registry.js',
				'deps' => array( 'jquery' ),
			),
			'scd-step-bridge'                 => array(
				'src'  => 'resources/assets/js/wizard/step-bridge.js',
				'deps' => array( 'jquery', 'scd-step-registry' ),
			),
			'scd-shared-notification-service' => array(
				'src'  => 'resources/assets/js/admin/notification-service.js',
				'deps' => array( 'jquery', 'scd-init-shared' ),
			),
			'scd-wizard-step-config'          => array(
				'src'  => 'resources/assets/js/wizard/step-config.js',
				'deps' => array(),
			),
			'scd-wizard-step-loader-factory'  => array(
				'src'  => 'resources/assets/js/wizard/step-loader-factory.js',
				'deps' => array( 'jquery', 'scd-wizard-step-config' ),
			),
			'scd-module-utilities'            => array(
				'src'  => 'resources/assets/js/shared/module-utilities.js',
				'deps' => array( 'jquery' ),
			),
			'scd-error-handler'               => array(
				'src'  => 'resources/assets/js/shared/error-handler.js',
				'deps' => array( 'jquery', 'scd-module-utilities' ),
			),
			'scd-event-manager-mixin'         => array(
				'src'  => 'resources/assets/js/shared/event-manager-mixin.js',
				'deps' => array( 'jquery', 'scd-module-utilities' ),
			),
			'scd-wizard-save-indicator'       => array(
				'src'  => 'resources/assets/js/wizard/wizard-save-indicator.js',
				'deps' => array( 'jquery' ),
			),
			'scd-field-definitions'           => array(
				'src'  => 'resources/assets/js/shared/field-definitions.js',
				'deps' => array( 'jquery', 'scd-admin' ), // Depends on scd-admin for localized data
			),
			'scd-tom-select-base'             => array(
				'src'  => 'resources/assets/js/shared/tom-select-base.js',
				'deps' => array( 'jquery', 'tom-select', 'scd-shared-utils' ),
			),
			'scd-sidebar-collapse'            => array(
				'src'  => 'resources/assets/js/wizard/sidebar-collapse.js',
				'deps' => array( 'jquery' ),
			),
		);

		foreach ( $shared_modules as $handle => $config ) {
			// Modules needed by analytics page (via scd-shared-ajax-service dependency chain)
			// Note: scd-shared-utils is already registered separately for both pages
			$analytics_shared_modules = array(
				'scd-module-utilities',
				'scd-error-handler',
			);

			if ( in_array( $handle, $analytics_shared_modules ) ) {
				$config['pages']     = array( 'scd-campaigns', 'scd-analytics' );
				$config['condition'] = array(); // No condition for analytics
			} else {
				$config['pages']     = array( 'scd-campaigns' );
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
		// Note: scd-shared-ajax-service is already registered in register_wizard_shared_modules()
		// with pages set to both 'scd-campaigns' and 'scd-analytics'

		// Chart.js - use local copy for reliability (previously CDN-only)
		$this->add_script(
			'chart-js',
			array(
				'src'       => 'resources/assets/vendor/chart-js/chart.umd.min.js',
				'deps'      => array(),
				'pages'     => array( 'scd-analytics' ),
				'condition' => array(),
				'external'  => false, // Local copy for better reliability
			)
		);

		$this->add_script(
			'scd-analytics-theme-colors',
			array(
				'src'     => 'resources/assets/js/analytics/theme-color-init.js',
				'deps'    => array( 'jquery', 'scd-theme-color-service' ),
				'pages'   => array( 'scd-analytics' ),
				'version' => '1.0.0',
			)
		);

		$this->add_script(
			'scd-analytics-dashboard',
			array(
				'src'      => 'resources/assets/js/analytics/analytics-dashboard.js',
				'deps'     => array( 'jquery', 'chart-js', 'scd-shared-utils', 'scd-shared-ajax-service', 'scd-analytics-theme-colors' ),
				'pages'    => array( 'scd-analytics' ),
				'localize' => 'scdAnalytics',
			)
		);

		// Frontend analytics tracking script
		$this->add_script(
			'scd-analytics-tracking',
			array(
				'src'       => 'resources/assets/js/analytics/scd-analytics-tracking.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'frontend' ),
				'localize'  => 'scdAnalyticsTracking',
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
			'scd-tooltips',
			array(
				'src'       => 'assets/js/scd-tooltips.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
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
		// Process the src path
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
	 * @since 1.0.0
	 * @param string $page Page identifier.
	 * @return array Scripts for the page.
	 */
	public function get_scripts_for_page( string $page ): array {
		// Asset loader already checks for SCD pages, so no need for whitelist here
		// This allows all SCD pages (campaigns, analytics, tools, settings, etc.)

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : null;

		$filtered_scripts = array_filter(
			$this->scripts,
			function ( $script ) use ( $page, $action ) {
				// Check if script is for this page
				if ( ! in_array( $page, $script['pages'], true ) ) {
					return false;
				}

				// Check action conditions
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
			'scd-basic-state'        => 'basic-state.js',
			'scd-basic-api'          => 'basic-api.js',
			'scd-basic-fields'       => 'basic-fields.js',
			'scd-basic-orchestrator' => 'basic-orchestrator.js',
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
			'scd-products-state'        => 'products-state.js',
			'scd-products-api'          => 'products-api.js',
			'scd-products-picker'       => 'products-picker.js',
			'scd-products-orchestrator' => 'products-orchestrator.js',
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
		// Add config first (replaces constants, fields)
		$this->add_script(
			'scd-discounts-config',
			array(
				'src'       => 'resources/assets/js/steps/discounts/discounts-config.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);

		$modules = array(
			'scd-discounts-api'           => 'discounts-api.js',
			'scd-discounts-state'         => 'discounts-state.js',
			'scd-discounts-conditions'    => 'discounts-conditions.js',
			'scd-discounts-type-registry' => 'discounts-type-registry.js',
			'scd-complex-field-handler'   => 'complex-field-handler.js',
			'scd-discounts-integration'   => 'discounts-integration.js',
			'scd-discounts-orchestrator'  => 'discounts-orchestrator.js',
		);

		// Register the base step modules first
		$this->register_step_module_group( 'discounts', $modules );

		// Register discount type scripts after base modules
		$this->register_discount_type_scripts();

		// Register review sidebar script for quick edit links
		$this->add_script(
			'scd-review-sidebar',
			array(
				'src'       => 'resources/assets/js/wizard/review-sidebar.js',
				'deps'      => array( 'jquery', 'scd-wizard-orchestrator' ),
				'pages'     => array( 'scd-campaigns' ),
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
			'scd-discount-type-base',
			array(
				'src'       => 'resources/assets/js/steps/discounts/base-discount.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'localize'  => null,
			)
		);

		// Specific discount types
		$discount_types = array(
			'scd-discount-type-percentage'      => array(
				'file' => 'percentage-discount.js',
				'deps' => array( 'jquery', 'scd-discount-type-base' ),
			),
			'scd-discount-type-fixed'           => array(
				'file' => 'fixed-discount.js',
				'deps' => array( 'jquery', 'scd-discount-type-base' ),
			),
			'scd-discount-type-tiered'          => array(
				'file' => 'tiered-discount.js',
				'deps' => array( 'jquery', 'scd-discount-type-base' ),
			),
			'scd-discount-type-bogo'            => array(
				'file' => 'bogo-discount.js',
				'deps' => array( 'jquery', 'scd-discount-type-base' ),
			),
			'scd-discount-type-spend-threshold' => array(
				'file' => 'spend-threshold.js',
				'deps' => array( 'jquery', 'scd-discount-type-base' ),
			),
		);

		foreach ( $discount_types as $handle => $config ) {
			$this->add_script(
				$handle,
				array(
					'src'       => 'resources/assets/js/steps/discounts/' . $config['file'],
					'deps'      => $config['deps'],
					'pages'     => array( 'scd-campaigns' ),
					'condition' => array( 'action' => 'wizard' ),
					'localize'  => null,
				)
			);
		}

		// Note: Type registry script will be registered after all discount types

		// Type registry that depends on all types (register after all dependencies)
		$this->add_script(
			'scd-discounts-type-registry',
			array(
				'src'       => 'resources/assets/js/steps/discounts/discounts-type-registry.js',
				'deps'      => array(
					'jquery',
					'scd-discounts-config',
					'scd-discount-type-base',
				),
				'pages'     => array( 'scd-campaigns' ),
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
		// Register timeline visualizer separately
		$this->add_script(
			'scd-timeline-visualizer',
			array(
				'src'       => 'resources/assets/js/analytics/timeline-visualizer.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Register date-time picker component
		$this->add_script(
			'scd-date-time-picker',
			array(
				'src'       => 'resources/assets/js/components/date-time-picker.js',
				'deps'      => array( 'jquery' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$modules = array(
			'scd-schedule-debug'        => 'schedule-debug.js',
			'scd-schedule-state'        => 'schedule-state.js',
			'scd-schedule-api'          => 'schedule-api.js',
			'scd-schedule-config'       => 'schedule-config.js',
			'scd-schedule-orchestrator' => 'schedule-orchestrator.js',
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
			'scd-review-state'        => 'review-state.js',
			'scd-review-api'          => 'review-api.js',
			'scd-review-components'   => 'review-components.js',
			'scd-review-orchestrator' => 'review-orchestrator.js',
		);

		$this->register_step_module_group( 'review', $modules );

		// Register review health check script for dynamic health analysis
		$this->add_script(
			'scd-review-health-check',
			array(
				'src'       => 'resources/assets/js/wizard/review-health-check.js',
				'deps'      => array( 'jquery', 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
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
				'pages' => array( 'scd-campaigns' ),
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
			'state'        => array( 'jquery', 'scd-shared-base-state', 'scd-event-manager-mixin' ),
			'api'          => array( 'jquery', 'scd-shared-ajax-service', 'scd-shared-base-api', 'scd-error-handler' ),
			'orchestrator' => array( 'jquery', 'scd-shared-base-orchestrator' ),
			'loader'       => array( 'jquery' ),
			'validator'    => array( 'jquery', 'scd-validation-manager' ),
		);

		// Special handling for orchestrator modules - they need all their step modules
		if ( strpos( $handle, '-orchestrator' ) !== false ) {
			$base_deps = array( 'jquery', 'scd-shared-base-orchestrator', 'scd-shared-utils', 'scd-event-manager-mixin', 'scd-step-persistence', 'scd-step-registry' );

			// Extract step name from handle (e.g., 'scd-basic-orchestrator' -> 'basic')
			$step = str_replace( array( 'scd-', '-orchestrator' ), '', $handle );

			// Add dependencies for all modules of this step
			$step_modules = array(
				'basic'     => array( 'scd-basic-state', 'scd-basic-api', 'scd-basic-fields' ),
				'products'  => array( 'scd-constants-product-selection', 'scd-tom-select-base', 'scd-products-state', 'scd-products-api', 'scd-products-picker' ),
				'discounts' => array( 'scd-discounts-config', 'scd-discounts-state', 'scd-discounts-api', 'scd-discounts-conditions', 'scd-discounts-type-registry' ),
				'schedule'  => array( 'jquery-ui-datepicker', 'scd-schedule-debug', 'scd-schedule-state', 'scd-schedule-api', 'scd-schedule-config' ),
				'review'    => array( 'scd-review-state', 'scd-review-api', 'scd-review-components' ),
			);

			if ( isset( $step_modules[ $step ] ) ) {
				$base_deps = array_merge( $base_deps, $step_modules[ $step ] );
			}

			return $base_deps;
		}

		// Scripts that need scd-shared-utils for utility functions
		$utils_required = array(
			'scd-discount-type-fixed',
		);

		// Picker module needs tom-select library, base, and utilities
		if ( $handle === 'scd-products-picker' ) {
			return array( 'jquery', 'tom-select', 'scd-tom-select-base', 'scd-module-utilities', 'scd-event-manager-mixin', 'scd-error-handler' );
		}

		// Products modules that need constants
		$products_modules_with_constants = array(
			'scd-products-orchestrator',
			'scd-products-state',
		);

		if ( in_array( $handle, $products_modules_with_constants ) ) {
			$base_deps = $this->get_base_deps_for_type( $handle, $dependency_map );
			array_unshift( $base_deps, 'scd-constants-product-selection' );
			return $base_deps;
		}

		// Check if this script requires utils
		if ( in_array( $handle, $utils_required ) ) {
			// Find base dependencies
			$base_deps = array( 'jquery' );
			foreach ( $dependency_map as $type => $deps ) {
				if ( strpos( $handle, $type ) !== false ) {
					$base_deps = $deps;
					break;
				}
			}
			// Add scd-shared-utils
			if ( ! in_array( 'scd-shared-utils', $base_deps ) ) {
				$base_deps[] = 'scd-shared-utils';
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
