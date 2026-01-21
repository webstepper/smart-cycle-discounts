<?php
/**
 * Notifications Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/class-notifications-page.php
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
 * Notifications Page Class
 *
 * Orchestrates all notifications functionality including:
 * - Email provider settings
 * - Notification preferences
 * - Email queue management
 * - Email logs and statistics
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Notifications_Page {

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
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate    $feature_gate    Feature gate instance.
	 */
	private WSSCD_Feature_Gate $feature_gate;

	/**
	 * Registered tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $tabs    Registered notification tabs.
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
	 * Initialize notifications page.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger       $logger        Logger instance.
	 * @param    object           $container     Container instance.
	 * @param    WSSCD_Feature_Gate $feature_gate  Feature gate instance.
	 */
	public function __construct( WSSCD_Logger $logger, object $container, WSSCD_Feature_Gate $feature_gate ) {
		$this->logger       = $logger;
		$this->container    = $container;
		$this->feature_gate = $feature_gate;
		$this->current_tab  = $this->get_current_tab();
	}

	/**
	 * Initialize notifications page.
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
			'settings' => 'notifications_settings_tab',
			'queue'    => 'notifications_queue_tab',
		);

		foreach ( $tab_services as $tab_slug => $service_id ) {
			// Hook into register_settings to register sections
			// Use closure with lazy loading to avoid circular dependency
			add_action(
				'wsscd_register_notifications_sections',
				function ( $current_tab ) use ( $service_id, $tab_slug ) {
					if ( $current_tab === $tab_slug && $this->container->has( $service_id ) ) {
						$tab_instance = $this->container->get( $service_id );
						if ( method_exists( $tab_instance, 'register_sections' ) ) {
							$tab_instance->register_sections( $current_tab );
						}
					}
				},
				10,
				1
			);

			// Hook into render to show tab content
			// Use closure with lazy loading to avoid circular dependency
			add_action(
				'wsscd_render_notifications_tab',
				function ( $current_tab ) use ( $service_id, $tab_slug ) {
					if ( $current_tab === $tab_slug && $this->container->has( $service_id ) ) {
						$tab_instance = $this->container->get( $service_id );
						if ( method_exists( $tab_instance, 'render_tab_content' ) ) {
							$tab_instance->render_tab_content( $current_tab );
						}
					}
				},
				10,
				1
			);
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
			'settings' => array(
				'title'    => __( 'Settings', 'smart-cycle-discounts' ),
				'priority' => 10,
				'icon'     => 'admin-settings',
			),
			'queue'    => array(
				'title'    => __( 'Queue Status', 'smart-cycle-discounts' ),
				'priority' => 20,
				'icon'     => 'list-view',
			),
		);

		// Allow filtering of tabs
		$this->tabs = apply_filters( 'wsscd_notifications_tabs', $this->tabs );

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
			'wsscd_notifications_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Let each tab register its sections and fields
		do_action( 'wsscd_register_notifications_sections', $this->current_tab );

		$this->logger->debug( 'Notification settings registered with WordPress Settings API' );
	}

	/**
	 * Get current active tab.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Current tab slug.
	 */
	private function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for tab navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		$valid_tabs = array( 'settings', 'queue' );
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			$tab = 'settings';
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
	 * Render notifications page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Debug output

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		echo '<div class="wrap wsscd-notifications">';
		// Use wp_kses with SVG allowed tags since wp_kses_post strips SVG elements.
		echo '<h1>' . wp_kses( WSSCD_Icon_Helper::get( 'email', array( 'size' => 16 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() ) . ' ' . esc_html__( 'Email Notifications', 'smart-cycle-discounts' ) . '</h1>';

		// Show admin notices
		settings_errors( 'wsscd_notifications_messages' );

		$this->render_tab_navigation();

		if ( 'settings' === $this->current_tab ) {
			echo '<form method="post" action="options.php" class="wsscd-notifications-form">';

			settings_fields( 'wsscd_notifications_group' );

			do_action( 'wsscd_render_notifications_tab', $this->current_tab );

			// Submit button
			echo '<div class="wsscd-notifications-submit">';
			submit_button( __( 'Save Settings', 'smart-cycle-discounts' ), 'primary', 'submit', false );
			echo '</div>';

			echo '</form>';
		} else {
			// Non-settings tabs don't need form wrapper
			do_action( 'wsscd_render_notifications_tab', $this->current_tab );
		}

		echo '</div>';
	}

	/**
	 * Render tab navigation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_tab_navigation(): void {
		echo '<nav class="wsscd-notifications-tabs nav-tab-wrapper">';

		foreach ( $this->get_tabs() as $tab_slug => $tab_data ) {
			$active_class = ( $this->current_tab === $tab_slug ) ? ' nav-tab-active' : '';
			$tab_url      = add_query_arg(
				array(
					'page' => 'wsscd-notifications',
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

		$current = get_option( $this->option_name, array() );

		// Apply guaranteed per-field sanitization based on field type
		// This ensures data is always sanitized, even if filters don't run
		$input = $this->sanitize_fields_by_type( $input );

		// Merge with current settings to preserve other sections
		$sanitized = array_replace_recursive( $current, $input );

		// Apply tab-specific sanitization
		$sanitized = apply_filters( 'wsscd_sanitize_notifications', $sanitized, $this->current_tab );

		// Log settings update
		$this->logger->info( 'Notification settings updated', array( 'tab' => $this->current_tab ) );

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
		// Define field types for notification settings
		$field_definitions = array(
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

		foreach ( $input as $section => $section_data ) {
			if ( ! is_array( $section_data ) ) {
				continue;
			}

			$sanitized[ $section ] = array();
			$section_definitions   = isset( $field_definitions[ $section ] ) ? $field_definitions[ $section ] : array();

			foreach ( $section_data as $field => $value ) {
				$field_type = isset( $section_definitions[ $field ] ) ? $section_definitions[ $field ] : 'text';
				$sanitized[ $section ][ $field ] = $this->sanitize_field_value( $value, $field_type );
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
	 * @return   mixed                       Sanitized value.
	 */
	private function sanitize_field_value( $value, $field_type ) {
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
				$emails       = array_map( 'trim', explode( ',', $value ) );
				$valid_emails = array();
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
	 * Get option name.
	 *
	 * @since    1.0.0
	 * @return   string    Option name.
	 */
	public function get_option_name(): string {
		return $this->option_name;
	}

	/**
	 * Get feature gate instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Feature_Gate    Feature gate instance.
	 */
	public function get_feature_gate(): WSSCD_Feature_Gate {
		return $this->feature_gate;
	}
}
