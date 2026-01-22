<?php
/**
 * Style Registry Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets/class-style-registry.php
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
 * Style Registry Class
 *
 * @since 1.0.0
 */
class WSSCD_Style_Registry {

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
		do_action( 'wsscd_style_registry_init', $this );
	}

	/**
	 * Register vendor styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_vendor_styles(): void {
		// Tom Select v2.4.3 - bundled locally for WordPress.org compliance.
		$this->add_style(
			'tom-select',
			array(
				'src'       => 'resources/assets/vendor/tom-select/tom-select.min.css',
				'deps'      => array(),
				'pages'     => array( 'wsscd-campaigns' ),
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
		// Theme colors - Must load first (no dependencies)
		$this->add_style(
			'wsscd-theme-colors',
			array(
				'src'      => 'resources/assets/css/shared/_theme-colors.css',
				'deps'     => array(),
				'pages'    => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'media'    => 'all',
				'priority' => 1,
			)
		);

		$this->add_style(
			'wsscd-variables',
			array(
				'src'      => 'resources/assets/css/shared/_variables.css',
				'deps'     => array( 'wsscd-theme-colors' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'media'    => 'all',
				'priority' => 2,
			)
		);

		$this->add_style(
			'wsscd-utilities',
			array(
				'src'      => 'resources/assets/css/shared/_utilities.css',
				'deps'     => array( 'wsscd-variables' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'media'    => 'all',
				'priority' => 3,
			)
		);

		$this->add_style(
			'wsscd-badges',
			array(
				'src'      => 'resources/assets/css/shared/_badges.css',
				'deps'     => array( 'wsscd-variables' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'media'    => 'all',
				'priority' => 4,
			)
		);

		$this->add_style(
			'wsscd-loader',
			array(
				'src'      => 'resources/assets/css/shared/loader.css',
				'deps'     => array( 'wsscd-variables' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'media'    => 'all',
				'priority' => 4,
			)
		);

		$this->add_style(
			'wsscd-components',
			array(
				'src'      => 'resources/assets/css/shared/_components.css',
				'deps'     => array( 'wsscd-variables', 'wsscd-utilities', 'wsscd-badges' ),
				'pages'    => array(
					'smart-cycle-discounts',
					'wsscd-campaigns',
					'wsscd-analytics',
					'wsscd-notifications',
					'wsscd-settings',
					'wsscd-tools',
					'wsscd-dashboard',
				),
				'media'    => 'all',
				'priority' => 5,
			)
		);

		$this->add_style(
			'wsscd-forms',
			array(
				'src'      => 'resources/assets/css/shared/_forms.css',
				'deps'     => array( 'wsscd-variables', 'wsscd-theme-colors' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics', 'wsscd-settings', 'wsscd-tools', 'wsscd-dashboard' ),
				'media'    => 'all',
				'priority' => 6,
			)
		);

		$this->add_style(
			'wsscd-buttons',
			array(
				'src'      => 'resources/assets/css/shared/_buttons.css',
				'deps'     => array( 'wsscd-variables', 'wsscd-theme-colors' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics', 'wsscd-settings', 'wsscd-tools', 'wsscd-dashboard' ),
				'media'    => 'all',
				'priority' => 7,
			)
		);

		$this->add_style(
			'wsscd-pro-feature-unavailable',
			array(
				'src'      => 'resources/assets/css/shared/pro-feature-unavailable.css',
				'deps'     => array( 'wsscd-variables', 'wsscd-components' ),
				'pages'    => array( 'wsscd-campaigns' ),
				'media'    => 'all',
				'priority' => 8,
			)
		);

		// Main admin styles (now depends on the shared styles)
		$this->add_style(
			'wsscd-admin',
			array(
				'src'      => 'resources/assets/css/admin/admin.css',
				'deps'     => array( 'wsscd-variables', 'wsscd-utilities', 'wsscd-components', 'wsscd-forms', 'wsscd-buttons', 'wsscd-badges' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'media'    => 'all',
				'priority' => 10,
			)
		);

		// Validation styles - complete validation system for all plugin admin pages
		// Includes field states, error messages, validation summary, and UI components
		$this->add_style(
			'wsscd-validation',
			array(
				'src'   => 'resources/assets/css/admin/validation.css',
				'deps'  => array( 'wsscd-admin' ),
				'pages' => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'media' => 'all',
			)
		);

		// Tom Select custom styling
		$this->add_style(
			'wsscd-tom-select-custom',
			array(
				'src'       => 'resources/assets/css/wizard/tom-select-custom.css',
				'deps'      => array( 'tom-select', 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
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
			'wsscd-main-dashboard',
			array(
				'src'      => 'resources/assets/css/admin/dashboard/main-dashboard.css',
				'deps'     => array( 'wsscd-theme-colors', 'wsscd-variables', 'wsscd-utilities', 'wsscd-badges', 'wsscd-components', 'wsscd-forms', 'wsscd-buttons' ),
				'pages'    => array( 'wsscd-dashboard' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);

		// Planner styles - weekly campaign planner feature
		$this->add_style(
			'wsscd-planner-styles',
			array(
				'src'      => 'resources/assets/css/admin/dashboard/planner-styles.css',
				'deps'     => array( 'wsscd-main-dashboard' ),
				'pages'    => array( 'wsscd-dashboard' ),
				'media'    => 'all',
				'priority' => 16,
			)
		);

		// Notification styles - loaded on all plugin pages
		$this->add_style(
			'wsscd-notifications',
			array(
				'src'      => 'resources/assets/css/admin/notifications.css',
				'deps'     => array( 'wsscd-admin' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				'priority' => 5,
			)
		);

		// Notifications page styles
		$this->add_style(
			'wsscd-notifications-page',
			array(
				'src'      => 'resources/assets/css/admin/notifications-page.css',
				'deps'     => array(),
				'pages'    => array( 'wsscd-notifications' ),
				'media'    => 'all',
				'priority' => 10,
			)
		);

		// WordPress admin color scheme compatibility
		$this->add_style(
			'wsscd-wordpress-color-schemes',
			array(
				'src'      => 'resources/assets/css/admin/wordpress-color-schemes.css',
				'deps'     => array( 'wsscd-theme-colors' ),
				'pages'    => array( 'wsscd-campaigns', 'wsscd-analytics', 'wsscd-settings', 'wsscd-dashboard', 'wsscd-notifications', 'wsscd-tools' ),
				'priority' => 2,
			)
		);

		// Settings page styles - loads on all tabs with full design system
		$this->add_style(
			'wsscd-settings',
			array(
				'src'      => 'resources/assets/css/admin/settings.css',
				'deps'     => array( 'wsscd-theme-colors', 'wsscd-variables', 'wsscd-utilities', 'wsscd-badges', 'wsscd-components', 'wsscd-forms', 'wsscd-buttons' ),
				'pages'    => array( 'wsscd-settings' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);

		// Tools page styles
		$this->add_style(
			'wsscd-tools',
			array(
				'src'      => 'resources/assets/css/admin/tools.css',
				'deps'     => array( 'wsscd-theme-colors', 'wsscd-variables', 'wsscd-utilities', 'wsscd-forms', 'wsscd-buttons' ),
				'pages'    => array( 'wsscd-tools' ),
				'media'    => 'all',
				'priority' => 15,
			)
		);

		// Analytics upgrade prompt styles (for free users)
		$this->add_style(
			'wsscd-analytics-upgrade',
			array(
				'src'      => 'resources/assets/css/admin/analytics-upgrade.css',
				'deps'     => array(),
				'pages'    => array( 'wsscd-analytics', 'wsscd-dashboard' ),
				'media'    => 'all',
				'priority' => 10,
			)
		);

		// Dashboard upgrade banner styles (professional SaaS design)
		$this->add_style(
			'wsscd-dashboard-upgrade-banner',
			array(
				'src'      => 'resources/assets/css/admin/dashboard-upgrade-banner.css',
				'deps'     => array(),
				'pages'    => array( 'wsscd-dashboard' ),
				'media'    => 'all',
				'priority' => 11,
			)
		);

		// Recurring campaigns badge styles
		$this->add_style(
			'wsscd-recurring-badges',
			array(
				'src'      => 'resources/assets/css/admin/recurring-badges.css',
				'deps'     => array( 'wsscd-admin' ),
				'pages'    => array( 'wsscd-campaigns' ),
				'priority' => 10,
			)
		);

		// Campaigns list table styles
		$this->add_style(
			'wsscd-campaigns-list',
			array(
				'src'      => 'resources/assets/css/admin/campaigns-list.css',
				'deps'     => array( 'wsscd-admin' ),
				'pages'    => array( 'wsscd-campaigns' ),
				'priority' => 10,
			)
		);

		// Draft conflict modal styles
		$this->add_style(
			'wsscd-draft-conflict-modal',
			array(
				'src'      => 'resources/assets/css/admin/draft-conflict-modal.css',
				'deps'     => array( 'wsscd-admin' ),
				'pages'    => array( 'wsscd-campaigns' ),
				'priority' => 10,
			)
		);

		// Campaign overview panel styles
		$this->add_style(
			'wsscd-campaign-overview-panel',
			array(
				'src'       => 'resources/assets/css/admin/campaign-overview-panel.css',
				'deps'      => array( 'wsscd-admin', 'wsscd-badges' ),
				'pages'     => array( 'wsscd-campaigns', 'wsscd-analytics' ),
				// No action condition - loads on all actions for wsscd-campaigns page
				// Panel HTML is rendered on list view and action=view
				'priority'  => 10,
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
			'wsscd-wizard-navigation',
			array(
				'src'       => 'resources/assets/css/wizard/wizard-navigation.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Wizard completion modal styles
		$this->add_style(
			'wsscd-wizard-completion-modal',
			array(
				'src'       => 'resources/assets/css/wizard/wizard-completion-modal.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Session expiration warning modal styles
		$this->add_style(
			'wsscd-session-expiration-modal',
			array(
				'src'       => 'resources/assets/css/wizard/session-expiration-modal.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// PRO feature required modal styles
		$this->add_style(
			'wsscd-pro-feature-modal',
			array(
				'src'       => 'resources/assets/css/wizard/pro-feature-modal.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Calculator import modal (campaigns list and wizard pages)
		$this->add_style(
			'wsscd-calculator-import',
			array(
				'src'       => 'resources/assets/css/admin/calculator-import.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array(), // No condition - load on list and wizard pages
			)
		);

		// Wizard steps container
		$this->add_style(
			'wsscd-wizard-steps',
			array(
				'src'       => 'resources/assets/css/wizard/wizard-steps.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Contextual sidebar system
		$this->add_style(
			'wsscd-sidebar-contextual',
			array(
				'src'       => 'resources/assets/css/wizard/sidebar-contextual.css',
				'deps'      => array( 'wsscd-wizard-steps' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Wizard fullscreen mode
		$this->add_style(
			'wsscd-wizard-fullscreen',
			array(
				'src'       => 'resources/assets/css/wizard/wizard-fullscreen.css',
				'deps'      => array( 'wsscd-admin', 'wsscd-variables', 'wsscd-utilities', 'wsscd-components', 'wsscd-tom-select-custom' ),
				'pages'     => array( 'wsscd-campaigns' ),
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
			'wsscd-wizard-step-basic',
			array(
				'src'       => 'resources/assets/css/wizard/step-basic.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
				'priority'  => 20,  // Higher priority to ensure it loads after base styles
			)
		);

		$this->add_style(
			'wsscd-wizard-step-products',
			array(
				'src'       => 'resources/assets/css/wizard/step-products.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'wsscd-wizard-step-discounts',
			array(
				'src'       => 'resources/assets/css/wizard/step-discounts.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		// Badge settings styles
		$this->add_style(
			'wsscd-badge-settings',
			array(
				'src'       => 'resources/assets/css/wizard/badge-settings.css',
				'deps'      => array( 'wsscd-admin', 'wp-color-picker' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'wsscd-wizard-step-schedule',
			array(
				'src'       => 'resources/assets/css/wizard/step-schedule.css',
				'deps'      => array( 'wsscd-admin', 'wp-jquery-ui-dialog', 'wsscd-wordpress-color-schemes' ),
				'pages'     => array( 'wsscd-campaigns' ),
				'condition' => array( 'action' => 'wizard' ),
			)
		);

		$this->add_style(
			'wsscd-wizard-step-review',
			array(
				'src'       => 'resources/assets/css/wizard/step-review.css',
				'deps'      => array( 'wsscd-admin' ),
				'pages'     => array( 'wsscd-campaigns' ),
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
			'wsscd-analytics',
			array(
				'src'      => 'resources/assets/css/admin/analytics.css',
				'deps'     => array( 'wsscd-admin' ),
				'pages'    => array( 'wsscd-analytics' ),
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
		// Tooltip styles are now consolidated in shared/_components.css
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
		// Skip get_style_path if src is a callable (dynamic source)
		if ( ! empty( $config['src'] ) && empty( $config['external'] ) && ! is_callable( $config['src'] ) ) {
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

		$page_match = in_array( $page, $style['pages'], true ) ||
						in_array( 'all', $style['pages'], true );

		if ( ! $page_match ) {
			return false;
		}

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
