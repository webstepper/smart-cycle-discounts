<?php
/**
 * Settings Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/class-settings-manager.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Settings Manager Class
 *
 * Orchestrates all settings functionality including:
 * - Settings registration with WordPress Settings API
 * - Tab management and navigation
 * - Settings storage and retrieval
 * - Validation and sanitization
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Settings_Manager {

	/**
	 * Settings option name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $option_name    Settings option name.
	 */
	private string $option_name = 'wsscd_settings';

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Registered tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $tabs    Registered settings tabs.
	 */
	private array $tabs = array();

	/**
	 * Current active tab.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_tab    Current active tab.
	 */
	private string $current_tab;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager|null    $cache    Cache manager.
	 */
	private ?WSSCD_Cache_Manager $cache = null;

	/**
	 * Initialize settings manager.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger $logger       Logger instance.
	 * @param    object     $container    Container instance.
	 */
	public function __construct( WSSCD_Logger $logger, object $container ) {
		$this->logger      = $logger;
		$this->container   = $container;
		$this->current_tab = $this->get_current_tab();

		// Get cache manager if available
		if ( $container->has( 'cache' ) ) {
			$this->cache = $container->get( 'cache' );
		}
	}

	/**
	 * Initialize settings manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->init_tab_classes();
		$this->add_hooks();
	}

	/**
	 * Initialize tab classes from container.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function init_tab_classes(): void {
		$tab_services = array(
			'general'  => 'settings_general',
			'advanced' => 'settings_advanced',
		);

		foreach ( $tab_services as $tab_slug => $service_id ) {
			if ( $this->container->has( $service_id ) ) {
				$tab_instance = $this->container->get( $service_id );

				// The base class init() handles all hook registration
				if ( method_exists( $tab_instance, 'init' ) ) {
					$tab_instance->init();
				}
			}
		}
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_tabs(): void {
		$this->tabs = array(
			'general'  => array(
				'title'    => __( 'General', 'smart-cycle-discounts' ),
				'priority' => 10,
				'icon'     => 'admin-settings',
			),
			'advanced' => array(
				'title'    => __( 'Advanced', 'smart-cycle-discounts' ),
				'priority' => 20,
				'icon'     => 'admin-generic',
			),
		);

		// Allow filtering of tabs
		$this->tabs = apply_filters( 'wsscd_settings_tabs', $this->tabs );

		uasort(
			$this->tabs,
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);
	}

	/**
	 * Register settings with WordPress Settings API.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_settings(): void {
		register_setting(
			'wsscd_settings_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		// Let each tab register its sections and fields
		do_action( 'wsscd_register_settings_sections', $this->current_tab );

		$this->logger->debug( 'Settings registered with WordPress Settings API' );
	}

	/**
	 * Get current active tab.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Current tab slug.
	 */
	private function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for tab navigation, not form processing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		$valid_tabs = array( 'general', 'advanced' );
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			$tab = 'general';
		}

		return $tab;
	}

	/**
	 * Get all registered tabs.
	 *
	 * @since    1.0.0
	 * @return   array    Registered tabs.
	 */
	public function get_tabs(): array {
		// Lazy load tabs on first access
		if ( empty( $this->tabs ) ) {
			$this->register_tabs();
		}
		return $this->tabs;
	}

	/**
	 * Get current tab slug.
	 *
	 * @since    1.0.0
	 * @return   string    Current tab slug.
	 */
	public function get_current_tab_slug(): string {
		return $this->current_tab;
	}

	/**
	 * Render settings page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure with escaped dynamic content.
		echo '<div class="wrap wsscd-settings">';
		// Use wp_kses with SVG allowed tags since wp_kses_post strips SVG elements.
		echo '<h1>' . wp_kses( WSSCD_Icon_Helper::get( 'admin-settings', array( 'size' => 16 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() ) . ' ' . esc_html( get_admin_page_title() ) . '</h1>';

		// Show admin notices
		settings_errors( 'wsscd_settings_messages' );

		$this->render_tab_navigation();

		echo '<form method="post" action="options.php" class="wsscd-settings-form">';

		settings_fields( 'wsscd_settings_group' );

		do_action( 'wsscd_render_settings_tab', $this->current_tab );

		// Submit button
		echo '<div class="wsscd-settings-submit">';
		submit_button( __( 'Save Settings', 'smart-cycle-discounts' ), 'primary', 'submit', false );
		echo '</div>';

		echo '</form>';
		echo '</div>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render tab navigation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_tab_navigation(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML.
		echo '<nav class="wsscd-settings-tabs nav-tab-wrapper">';

		foreach ( $this->get_tabs() as $tab_slug => $tab_data ) {
			$active_class = ( $this->current_tab === $tab_slug ) ? ' nav-tab-active' : '';
			$tab_url      = add_query_arg(
				array(
					'page' => 'wsscd-settings',
					'tab'  => $tab_slug,
				),
				admin_url( 'admin.php' )
			);

			$icon_name = str_replace( 'dashicons-', '', $tab_data['icon'] );
			$icon_html = WSSCD_Icon_Helper::get( $icon_name, array( 'size' => 16 ) );

			printf(
				'<a href="%s" class="nav-tab%s">%s %s</a>',
				esc_url( $tab_url ),
				esc_attr( $active_class ),
				wp_kses( $icon_html, WSSCD_Icon_Helper::get_allowed_svg_tags() ),
				esc_html( $tab_data['title'] )
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML.
		echo '</nav>';
	}

	/**
	 * Sanitize settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data.
	 * @return   array             Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		$current = $this->get_settings();

		// Detect which tab is being saved from the input structure
		// When form submits, only the active tab's data is in $input
		$active_tab = $this->current_tab;
		$valid_tabs = array( 'general', 'advanced', 'notifications' );

		foreach ( $valid_tabs as $tab ) {
			if ( isset( $input[ $tab ] ) ) {
				$active_tab = $tab;
				break;
			}
		}

		$raw_tab_input = isset( $input[ $active_tab ] ) ? $input[ $active_tab ] : array();

		// Apply guaranteed per-field sanitization based on field type
		// This ensures data is always sanitized, even if filters don't run
		$input = $this->sanitize_fields_by_type( $input );

		// Merge with current settings to preserve other tabs
		$sanitized = array_replace_recursive( $current, $input );

		// Apply tab-specific sanitization with RAW input
		// Pass the full merged settings but with raw input for active tab
		$sanitized = apply_filters( 'wsscd_sanitize_settings', $sanitized, $active_tab, $raw_tab_input );

		// Log settings update
		$this->logger->info( 'Settings updated', array( 'tab' => $active_tab ) );

		// Clear cache after settings save
		if ( $this->cache ) {
			$this->cache->delete_group( 'settings' );
		}

		return $sanitized;
	}

	/**
	 * Sanitize fields by their expected type.
	 *
	 * Provides guaranteed per-field sanitization based on field definitions.
	 * This ensures WordPress.org compliance by sanitizing all fields appropriately.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $input    Raw input data.
	 * @return   array             Sanitized data.
	 */
	private function sanitize_fields_by_type( array $input ): array {
		// Define field types for each setting
		$field_definitions = array(
			'general'       => array(
				'trash_retention_days' => 'absint',
				'trash_auto_purge'     => 'boolean',
			),
			'advanced'      => array(
				'enable_debug_mode'     => 'boolean',
				'debug_mode_enabled_at' => 'absint',
				'log_level'             => array( 'enum', array( 'none', 'error', 'warning', 'info', 'debug' ) ),
				'log_retention_days'    => 'absint',
				'uninstall_data'        => 'boolean',
			),
			'notifications' => array(
				'email_provider'           => array( 'enum', array( 'wpmail', 'sendgrid', 'amazonses' ) ),
				'from_email'               => 'email',
				'from_name'                => 'text',
				'additional_recipients'    => 'email_list',
				'sendgrid_api_key'         => 'text',
				'amazonses_access_key'     => 'text',
				'amazonses_secret_key'     => 'text',
				'amazonses_region'         => array( 'enum', array( 'us-east-1', 'us-west-2', 'eu-west-1', 'eu-central-1', 'ap-southeast-1' ) ),
				'notify_campaign_started'  => 'boolean',
				'notify_campaign_ended'    => 'boolean',
				'notify_errors'            => 'boolean',
				'notify_campaign_ending'   => 'boolean',
				'notify_daily_report'      => 'boolean',
				'notify_weekly_report'     => 'boolean',
				'notify_performance_alert' => 'boolean',
				'notify_low_stock_alert'   => 'boolean',
				'notify_milestone_alert'   => 'boolean',
			),
		);

		$sanitized = array();

		foreach ( $input as $tab => $tab_data ) {
			if ( ! is_array( $tab_data ) ) {
				continue;
			}

			$sanitized[ $tab ] = array();
			$tab_definitions   = isset( $field_definitions[ $tab ] ) ? $field_definitions[ $tab ] : array();

			foreach ( $tab_data as $field => $value ) {
				$field_type = isset( $tab_definitions[ $field ] ) ? $tab_definitions[ $field ] : 'text';
				$sanitized[ $tab ][ $field ] = $this->sanitize_field_value( $value, $field_type, $field );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single field value based on its type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed        $value       Raw field value.
	 * @param    string|array $field_type  Field type or array with enum values.
	 * @param    string       $field_name  Field name for context.
	 * @return   mixed                       Sanitized value.
	 */
	private function sanitize_field_value( $value, $field_type, string $field_name ) {
		// Handle enum type (array format: ['enum', ['option1', 'option2']])
		if ( is_array( $field_type ) && 'enum' === $field_type[0] ) {
			$allowed_values = $field_type[1];
			$value          = sanitize_key( $value );
			return in_array( $value, $allowed_values, true ) ? $value : $allowed_values[0];
		}

		// Handle standard types
		switch ( $field_type ) {
			case 'boolean':
				return rest_sanitize_boolean( $value );

			case 'absint':
				return absint( $value );

			case 'email':
				$sanitized = sanitize_email( $value );
				return is_email( $sanitized ) ? $sanitized : '';

			case 'email_list':
				// Sanitize comma-separated email list
				if ( empty( $value ) ) {
					return '';
				}
				$emails          = array_map( 'trim', explode( ',', $value ) );
				$valid_emails    = array();
				foreach ( $emails as $email ) {
					$sanitized = sanitize_email( $email );
					if ( is_email( $sanitized ) ) {
						$valid_emails[] = $sanitized;
					}
				}
				return implode( ', ', $valid_emails );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Get all settings.
	 *
	 * @since    1.0.0
	 * @return   array    All settings.
	 */
	public function get_settings(): array {
		$settings = get_option( $this->option_name, array() );

		// Merge with defaults
		$settings = wp_parse_args( $settings, $this->get_default_settings() );

		return $settings;
	}

	/**
	 * Get settings for specific tab.
	 *
	 * @since    1.0.0
	 * @param    string $tab    Tab slug.
	 * @return   array             Tab settings.
	 */
	public function get_tab_settings( string $tab ): array {
		$all_settings = $this->get_settings();
		$defaults     = $this->get_default_settings();

		$tab_settings = isset( $all_settings[ $tab ] ) ? $all_settings[ $tab ] : array();
		$tab_defaults = isset( $defaults[ $tab ] ) ? $defaults[ $tab ] : array();

		return wp_parse_args( $tab_settings, $tab_defaults );
	}

	/**
	 * Get default settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Default settings.
	 */
	private function get_default_settings(): array {
		$defaults = array(
			'general'       => array(
				'trash_retention_days' => 30,
				'trash_auto_purge'     => true,
			),
			'advanced'      => array(
				'enable_debug_mode'     => false,
				'debug_mode_enabled_at' => 0,
				'log_level'             => 'warning',
				'log_retention_days'    => 7,
				'uninstall_data'        => false,
			),
			// Notifications settings moved to separate page
			'notifications' => array(
				'email_provider'           => 'wpmail',
				'from_email'               => get_option( 'admin_email' ),
				'from_name'                => get_bloginfo( 'name' ),
				'additional_recipients'    => '',
				'sendgrid_api_key'         => '',
				'amazonses_access_key'     => '',
				'amazonses_secret_key'     => '',
				'amazonses_region'         => 'us-east-1',
				// FREE notifications - ON by default
				'notify_campaign_started'  => true,
				'notify_campaign_ended'    => true,
				'notify_errors'            => true,
				// PRO notifications - OFF by default
				'notify_campaign_ending'   => false,
				'notify_daily_report'      => false,
				'notify_weekly_report'     => false,
				'notify_performance_alert' => false,
				'notify_low_stock_alert'   => false,
				'notify_milestone_alert'   => false,
			),
		);

		// Allow filtering of defaults
		$defaults = apply_filters( 'wsscd_default_settings', $defaults );

		return $defaults;
	}

	/**
	 * Get option name.
	 *
	 * @since    1.0.0
	 * @return   string    Option name.
	 */
	public function get_option_name(): string {
		return $this->option_name;
	}
}
