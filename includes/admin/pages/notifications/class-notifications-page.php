<?php
/**
 * Notifications Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/class-notifications-page.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Notifications_Page {

	/**
	 * Settings option name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $option_name    Settings option name.
	 */
	private string $option_name = 'scd_settings';

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
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate    $feature_gate    Feature gate instance.
	 */
	private SCD_Feature_Gate $feature_gate;

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
	 * @param    SCD_Logger       $logger        Logger instance.
	 * @param    object           $container     Container instance.
	 * @param    SCD_Feature_Gate $feature_gate  Feature gate instance.
	 */
	public function __construct( SCD_Logger $logger, object $container, SCD_Feature_Gate $feature_gate ) {
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

		$this->logger->debug( 'Notifications page initialized' );
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
				'scd_register_notifications_sections',
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
				'scd_render_notifications_tab',
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

		$this->logger->debug( 'Notification tab classes initialized' );
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
				'icon'     => 'dashicons-admin-settings',
			),
			'queue'    => array(
				'title'    => __( 'Queue Status', 'smart-cycle-discounts' ),
				'priority' => 20,
				'icon'     => 'dashicons-list-view',
			),
		);

		// Allow filtering of tabs
		$this->tabs = apply_filters( 'scd_notifications_tabs', $this->tabs );

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
			'scd_notifications_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Let each tab register its sections and fields
		do_action( 'scd_register_notifications_sections', $this->current_tab );

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
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

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

		echo '<div class="wrap scd-notifications">';
		echo '<h1><span class="dashicons dashicons-email"></span> ' . esc_html__( 'Email Notifications', 'smart-cycle-discounts' ) . '</h1>';

		// Show admin notices
		settings_errors( 'scd_notifications_messages' );

		$this->render_tab_navigation();

		if ( 'settings' === $this->current_tab ) {
			echo '<form method="post" action="options.php" class="scd-notifications-form">';

			settings_fields( 'scd_notifications_group' );

			do_action( 'scd_render_notifications_tab', $this->current_tab );

			// Submit button
			echo '<div class="scd-notifications-submit">';
			submit_button( __( 'Save Settings', 'smart-cycle-discounts' ), 'primary', 'submit', false );
			echo '</div>';

			echo '</form>';
		} else {
			// Non-settings tabs don't need form wrapper
			do_action( 'scd_render_notifications_tab', $this->current_tab );
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
		echo '<nav class="scd-notifications-tabs nav-tab-wrapper">';

		foreach ( $this->get_tabs() as $tab_slug => $tab_data ) {
			$active_class = ( $this->current_tab === $tab_slug ) ? ' nav-tab-active' : '';
			$tab_url      = add_query_arg(
				array(
					'page' => 'scd-notifications',
					'tab'  => $tab_slug,
				),
				admin_url( 'admin.php' )
			);

			printf(
				'<a href="%s" class="nav-tab%s"><span class="dashicons %s"></span> %s</a>',
				esc_url( $tab_url ),
				esc_attr( $active_class ),
				esc_attr( $tab_data['icon'] ),
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

		// Merge with current settings to preserve other sections
		$sanitized = array_replace_recursive( $current, $input );

		// Apply tab-specific sanitization
		$sanitized = apply_filters( 'scd_sanitize_notifications', $sanitized, $this->current_tab );

		// Log settings update
		$this->logger->info( 'Notification settings updated', array( 'tab' => $this->current_tab ) );

		return $sanitized;
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
	 * @return   SCD_Feature_Gate    Feature gate instance.
	 */
	public function get_feature_gate(): SCD_Feature_Gate {
		return $this->feature_gate;
	}
}
