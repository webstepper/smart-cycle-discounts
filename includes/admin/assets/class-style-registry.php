<?php
/**
 * Style Registry
 *
 * Handles registration of CSS assets following modular architecture.
 *
 * @package SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/admin/assets
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Style Registry Class
 *
 * @since 1.0.0
 */
class SCD_Style_Registry {

	/**
	 * Registered styles.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $styles = array();

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
	 * Style suffix (min or not).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $suffix;

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
	 * Initialize style registry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->register_vendor_styles();
		$this->register_core_styles();
		$this->register_admin_styles();
		$this->register_wizard_styles();
		$this->register_analytics_styles();
		$this->register_component_styles();

		// Allow extensions
		do_action( 'scd_style_registry_init', $this );
	}

	/**
	 * Register vendor styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_vendor_styles(): void {
		// Tom Select
		$this->add_style(
			'tom-select',
			array(
				'src'       => 'resources/assets/vendor/tom-select/tom-select.css',
				'deps'      => array(),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);
	}

	/**
	 * Register core styles used across admin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_core_styles(): void {
		// Register shared styles first (these need to load before admin.css)
		// Theme colors - Must load first (no dependencies)
		$this->add_style(
			'scd-theme-colors',
			array(
				'src'      => 'resources/assets/css/shared/_theme-colors.css',
				'deps'     => array(),
				'pages'    => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
					'scd-dashboard',
				),
				'media'    => 'all',
				'priority' => 1,
			)
		);

		$this->add_style(
			'scd-variables',
			array(
				'src'      => 'resources/assets/css/shared/_variables.css',
				'deps'     => array( 'scd-theme-colors' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
					'scd-dashboard',
				),
				'media'    => 'all',
				'priority' => 2,
			)
		);

		$this->add_style(
			'scd-utilities',
			array(
				'src'      => 'resources/assets/css/shared/_utilities.css',
				'deps'     => array( 'scd-variables' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
					'scd-dashboard',
				),
				'media'    => 'all',
				'priority' => 3,
			)
		);

		$this->add_style(
			'scd-badges',
			array(
				'src'      => 'resources/assets/css/shared/_badges.css',
				'deps'     => array( 'scd-variables' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
					'scd-dashboard',
				),
				'media'    => 'all',
				'priority' => 4,
			)
		);

		$this->add_style(
			'scd-components',
			array(
				'src'      => 'resources/assets/css/shared/_components.css',
				'deps'     => array( 'scd-variables', 'scd-utilities', 'scd-badges' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
					'scd-dashboard',
				),
				'media'    => 'all',
				'priority' => 5,
			)
		);

		$this->add_style(
			'scd-forms',
			array(
				'src'      => 'resources/assets/css/shared/_forms.css',
				'deps'     => array( 'scd-variables', 'scd-theme-colors' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics', 'scd-settings', 'scd-tools', 'scd-dashboard' ),
				'media'    => 'all',
				'priority' => 6,
			)
		);

		$this->add_style(
			'scd-buttons',
			array(
				'src'      => 'resources/assets/css/shared/_buttons.css',
				'deps'     => array( 'scd-variables', 'scd-theme-colors' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics', 'scd-settings', 'scd-tools', 'scd-dashboard' ),
				'media'    => 'all',
				'priority' => 7,
			)
		);

		$this->add_style(
			'scd-pro-feature-unavailable',
			array(
				'src'      => 'resources/assets/css/shared/pro-feature-unavailable.css',
				'deps'     => array( 'scd-variables', 'scd-components' ),
				'pages'    => array( 'scd-campaigns' ),
				'media'    => 'all',
				'priority' => 8,
			)
		);

		// Main admin styles (now depends on the shared styles)
		$this->add_style(
			'scd-admin',
			array(
				'src'      => 'resources/assets/css/admin/admin.css',
				'deps'     => array( 'scd-variables', 'scd-utilities', 'scd-components', 'scd-forms', 'scd-buttons' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics' ),
				'media'    => 'all',
				'priority' => 10,
			)
		);

		// Validation UI styles - only for plugin admin pages
		$this->add_style(
			'scd-validation-ui',
			array(
				'src'   => 'resources/assets/css/admin/validation-ui.css',
				'deps'  => array( 'scd-admin' ),
				'pages' => array( 'scd-campaigns', 'scd-analytics' ),
				'media' => 'all',
			)
		);

		// Validation styles - only for wizard pages where form validation occurs
		$this->add_style(
			'scd-validation',
			array(
				'src'       => 'resources/assets/css/admin/validation.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'media'     => 'all',
			)
		);

		// Tom Select custom styling
		$this->add_style(
			'scd-tom-select-custom',
			array(
				'src'       => 'resources/assets/css/admin/tom-select-custom.css',
				'deps'      => array( 'tom-select', 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'media'     => 'all',
			)
		);
	}

	/**
	 * Register admin-specific styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_admin_styles(): void {
		// Main dashboard (free tier) - needs full design system
		$this->add_style(
			'scd-main-dashboard',
			array(
				'src'      => 'resources/assets/css/admin/dashboard/main-dashboard.css',
				'deps'     => array( 'scd-theme-colors', 'scd-variables', 'scd-utilities', 'scd-badges', 'scd-components', 'scd-forms', 'scd-buttons' ),
				'pages'    => array( 'scd-dashboard' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);

		// Timeline styles - weekly campaign timeline feature
		$this->add_style(
			'scd-timeline-styles',
			array(
				'src'      => 'resources/assets/css/admin/dashboard/timeline-styles.css',
				'deps'     => array( 'scd-main-dashboard' ),
				'pages'    => array( 'scd-dashboard' ),
				'media'    => 'all',
				'priority' => 16,
			)
		);

		// Notification styles - loaded on all plugin pages
		$this->add_style(
			'scd-notifications',
			array(
				'src'      => 'resources/assets/css/admin/notifications.css',
				'deps'     => array( 'scd-admin' ),
				'pages'    => array( 'scd-campaigns', 'scd-analytics' ),
				'priority' => 5,
			)
		);

		// Notifications page styles
		$this->add_style(
			'scd-notifications-page',
			array(
				'src'      => 'assets/css/admin/notifications.css',
				'deps'     => array(),
				'pages'    => array( 'scd-notifications' ),
				'media'    => 'all',
				'priority' => 10,
			)
		);

		// WordPress admin color scheme compatibility
		$this->add_style(
			'scd-wordpress-color-schemes',
			array(
				'src'      => 'resources/assets/css/admin/wordpress-color-schemes.css',
				'deps'     => array(),
				'pages'    => array( 'scd-campaigns', 'scd-analytics', 'scd-settings', 'scd-dashboard' ),
				'priority' => 1,
			)
		);

		// Settings page styles - loads on all tabs with full design system
		$this->add_style(
			'scd-settings',
			array(
				'src'      => 'resources/assets/css/admin/settings.css',
				'deps'     => array( 'scd-theme-colors', 'scd-variables', 'scd-utilities', 'scd-badges', 'scd-components', 'scd-forms', 'scd-buttons' ),
				'pages'    => array( 'scd-settings' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);

		// Tools page styles
		$this->add_style(
			'scd-tools',
			array(
				'src'      => 'resources/assets/css/admin/tools.css',
				'deps'     => array( 'scd-theme-colors', 'scd-variables', 'scd-utilities', 'scd-forms', 'scd-buttons' ),
				'pages'    => array( 'scd-tools' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);

		// Analytics upgrade prompt styles (for free users)
		$this->add_style(
			'scd-analytics-upgrade',
			array(
				'src'      => 'resources/assets/css/admin/analytics-upgrade.css',
				'deps'     => array(),
				'pages'    => array( 'scd-analytics', 'scd-dashboard' ),
				'media'    => 'all',
				'priority' => 10,
			)
		);

		// Dashboard upgrade banner styles (professional SaaS design)
		$this->add_style(
			'scd-dashboard-upgrade-banner',
			array(
				'src'      => 'resources/assets/css/admin/dashboard-upgrade-banner.css',
				'deps'     => array(),
				'pages'    => array( 'scd-dashboard' ),
				'media'    => 'all',
				'priority' => 11,
			)
		);

		// Recurring campaigns badge styles
		$this->add_style(
			'scd-recurring-badges',
			array(
				'src'      => 'resources/assets/css/admin/recurring-badges.css',
				'deps'     => array( 'scd-admin' ),
				'pages'    => array( 'scd-campaigns' ),
				'priority' => 10,
			)
		);

		// Campaigns list table styles
		$this->add_style(
			'scd-campaigns-list',
			array(
				'src'      => 'resources/assets/css/admin/campaigns-list.css',
				'deps'     => array( 'scd-admin' ),
				'pages'    => array( 'scd-campaigns' ),
				'priority' => 10,
			)
		);

		// Note: Modal styles removed - not implemented yet
	}

	/**
	 * Register wizard styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_wizard_styles(): void {
		// Main wizard navigation styles
		$this->add_style(
			'scd-wizard-navigation',
			array(
				'src'       => 'resources/assets/css/admin/wizard-navigation.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Wizard completion modal styles
		$this->add_style(
			'scd-wizard-completion-modal',
			array(
				'src'       => 'resources/assets/css/admin/wizard-completion-modal.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Session expiration warning modal styles
		$this->add_style(
			'scd-session-expiration-modal',
			array(
				'src'       => 'resources/assets/css/admin/session-expiration-modal.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// PRO feature required modal styles
		$this->add_style(
			'scd-pro-feature-modal',
			array(
				'src'       => 'resources/assets/css/admin/pro-feature-modal.css',
				'deps'      => array( 'scd-admin', 'dashicons' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Wizard steps container
		$this->add_style(
			'scd-wizard-steps',
			array(
				'src'       => 'resources/assets/css/admin/wizard-steps.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Wizard fullscreen mode
		$this->add_style(
			'scd-wizard-fullscreen',
			array(
				'src'       => 'resources/assets/css/admin/wizard-fullscreen.css',
				'deps'      => array( 'scd-admin', 'scd-variables', 'scd-utilities', 'scd-components', 'scd-tom-select-custom' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Step-specific styles
		$this->register_wizard_step_styles();
	}

	/**
	 * Register wizard step styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_wizard_step_styles(): void {
		// Main step styles
		$this->add_style(
			'scd-wizard-step-basic',
			array(
				'src'       => 'resources/assets/css/admin/step-basic.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'priority'  => 20,  // Higher priority to ensure it loads after base styles
			)
		);

		$this->add_style(
			'scd-wizard-step-products',
			array(
				'src'       => 'resources/assets/css/admin/step-products.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-discounts',
			array(
				'src'       => 'resources/assets/css/admin/step-discounts.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-schedule',
			array(
				'src'       => 'resources/assets/css/admin/step-schedule.css',
				'deps'      => array( 'scd-admin', 'wp-jquery-ui-dialog', 'scd-wordpress-color-schemes' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-review',
			array(
				'src'       => 'resources/assets/css/admin/step-review.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Sidebar styles for each step
		$this->add_style(
			'scd-wizard-step-basic-sidebar',
			array(
				'src'       => 'resources/assets/css/admin/step-basic-sidebar.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-products-sidebar',
			array(
				'src'       => 'resources/assets/css/admin/step-products-sidebar.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-discounts-sidebar',
			array(
				'src'       => 'resources/assets/css/admin/step-discounts-sidebar.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-schedule-sidebar',
			array(
				'src'       => 'resources/assets/css/admin/step-schedule-sidebar.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'scd-wizard-step-review-sidebar',
			array(
				'src'       => 'resources/assets/css/admin/step-review-sidebar.css',
				'deps'      => array( 'scd-admin' ),
				'pages'     => array( 'scd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);
	}

	/**
	 * Register analytics styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_analytics_styles(): void {
		// Analytics dashboard styles - premium styling
		$this->add_style(
			'scd-analytics',
			array(
				'src'      => 'resources/assets/css/admin/analytics.css',
				'deps'     => array( 'scd-admin' ),
				'pages'    => array( 'scd-analytics' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);
	}

	/**
	 * Register reusable component styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_component_styles(): void {
		// Tooltip styles - Global (available on all SCD pages)
		$this->add_style(
			'scd-tooltips',
			array(
				'src'       => 'resources/assets/css/admin/tooltips.css',
				'deps'      => array( 'scd-components' ),
				'pages'     => array(
					'smart-cycle-discounts',
					'scd-campaigns',
					'scd-analytics',
					'scd-notifications',
					'scd-settings',
					'scd-tools',
				),
				'condition' => null,
				'priority'  => 15,
			)
		);
	}

	/**
	 * Add a style definition.
	 *
	 * @since 1.0.0
	 * @param string $handle Style handle.
	 * @param array  $config Style configuration.
	 * @return void
	 */
	public function add_style( string $handle, array $config ): void {
		// Process the src path
		if ( ! empty( $config['src'] ) && empty( $config['external'] ) ) {
			$config['src'] = $this->get_style_path( $config['src'] );
		}

		$this->styles[ $handle ] = wp_parse_args(
			$config,
			array(
				'src'       => '',
				'deps'      => array(),
				'pages'     => array(),
				'condition' => array(),
				'media'     => 'all',
				'lazy'      => false,
				'component' => false,
				'priority'  => 10,
				'inline'    => false,
				'external'  => false,
			)
		);
	}

	/**
	 * Get the correct style path based on debug mode and file availability.
	 *
	 * @since 1.0.0
	 * @param string $src The style source path.
	 * @return string The resolved style path.
	 */
	private function get_style_path( string $src ): string {
		// For development, always use source files
		// Simply return the source path as-is
		return $src;
	}

	/**
	 * Get style configuration.
	 *
	 * @since 1.0.0
	 * @param string $handle Style handle.
	 * @return array|null Style config or null if not found.
	 */
	public function get_style( string $handle ): ?array {
		return $this->styles[ $handle ] ?? null;
	}

	/**
	 * Get all styles.
	 *
	 * @since 1.0.0
	 * @return array All registered styles.
	 */
	public function get_all_styles(): array {
		return $this->styles;
	}

	/**
	 * Get styles for a specific page.
	 *
	 * @since 1.0.0
	 * @param string $page Page identifier.
	 * @return array Styles for the page, sorted by priority.
	 */
	public function get_styles_for_page( string $page ): array {
		$page_styles = array_filter(
			$this->styles,
			function ( $style ) use ( $page ) {
				return in_array( $page, $style['pages'], true );
			}
		);

		// Sort by priority
		uasort(
			$page_styles,
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		return $page_styles;
	}

	/**
	 * Get component styles.
	 *
	 * @since 1.0.0
	 * @return array Component styles.
	 */
	public function get_component_styles(): array {
		return array_filter(
			$this->styles,
			function ( $style ) {
				return $style['component'] === true;
			}
		);
	}

	/**
	 * Get styles by condition.
	 *
	 * @since 1.0.0
	 * @param array $conditions Conditions to match.
	 * @return array Matching styles.
	 */
	public function get_styles_by_condition( array $conditions ): array {
		return array_filter(
			$this->styles,
			function ( $style ) use ( $conditions ) {
				if ( empty( $style['condition'] ) ) {
					return true;
				}

				foreach ( $style['condition'] as $key => $value ) {
					if ( ! isset( $conditions[ $key ] ) || $conditions[ $key ] !== $value ) {
						return false;
					}
				}

				return true;
			}
		);
	}

	/**
	 * Check if style should be loaded.
	 *
	 * @since 1.0.0
	 * @param string $handle Style handle.
	 * @param string $page   Current page.
	 * @param array  $conditions Current conditions.
	 * @return bool True if should load.
	 */
	public function should_load_style( string $handle, string $page, array $conditions = array() ): bool {
		$style = $this->get_style( $handle );

		if ( ! $style ) {
			return false;
		}

		// Check page match
		$page_match = in_array( $page, $style['pages'], true ) ||
						in_array( 'all', $style['pages'], true );

		if ( ! $page_match ) {
			return false;
		}

		// Check conditions
		if ( ! empty( $style['condition'] ) ) {
			foreach ( $style['condition'] as $key => $value ) {
				if ( ! isset( $conditions[ $key ] ) || $conditions[ $key ] !== $value ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get inline styles.
	 *
	 * @since 1.0.0
	 * @return array Inline styles.
	 */
	public function get_inline_styles(): array {
		return array_filter(
			$this->styles,
			function ( $style ) {
				return $style['inline'] === true;
			}
		);
	}
}
