<?php
/**
 * Asset Localizer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets/class-asset-localizer.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once SCD_INCLUDES_DIR . 'constants/class-scd-product-selection-types.php';

/**
 * Asset Localizer Class
 *
 * @since 1.0.0
 */
class SCD_Asset_Localizer {

	/**
	 * Localization data.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $data = array();

	/**
	 * Localized handles tracking.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $localized = array();

	/**
	 * Plugin settings.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $settings;

	/**
	 * Flag to track if data providers have been registered.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $data_registered = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings = array() ) {
		$this->settings = $settings;
	}

	/**
	 * Initialize localizer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		// Defer data provider registration until after init to avoid translation warnings
		add_action( 'init', array( $this, 'register_data_providers' ), 20 );

		// Handle script localization requests - use earlier priority
		add_action( 'scd_localize_script', array( $this, 'localize_script' ), 5, 3 );

		// Print global data before scripts are printed
		add_action( 'admin_print_scripts', array( $this, 'print_global_data' ), 1 );
	}

	/**
	 * Register data providers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_data_providers(): void {
		// Don't register data providers during AJAX requests
		// They're only needed for initial page load
		if ( wp_doing_ajax() ) {
			return;
		}

		// Prevent double registration
		if ( $this->data_registered ) {
			return;
		}

		$this->data_registered = true;

		// Admin data
		$this->data['scdAdmin']               = array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'plugin_url'          => trailingslashit( plugins_url( '', SCD_PLUGIN_FILE ) ),
			'admin_url'           => admin_url(),
			'site_url'            => site_url(),
			'nonce'               => wp_create_nonce( 'scd_admin_nonce' ),
			'user_id'             => get_current_user_id(),
			'locale'              => get_locale(),
			'is_rtl'              => is_rtl(),
			'version'             => SCD_VERSION,
			'debug_mode'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug'               => defined( 'SCD_DEBUG' ) && SCD_DEBUG,
			'i18n'                => $this->get_i18n_strings(),
			'colors'              => $this->get_theme_colors(),
			// Include field definitions within scdAdmin object
			'scdFieldDefinitions' => $this->get_field_definitions(),
		);
		$this->data['scdValidationMessages']  = $this->get_validation_messages();
		$this->data['scdValidationConstants'] = $this->get_validation_constants();

		// Admin notices data (nonces for dismiss handlers)
		$this->data['scdAdminNotices'] = array(
			'nonces' => array(
				'expiration' => wp_create_nonce( 'scd_dismiss_expiration_notice' ),
				'currency'   => wp_create_nonce( 'scd_dismiss_currency_notice' ),
			),
		);

		// Validation config
		$this->data['scdValidationConfig'] = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'scd_validation_nonce' ),
		);

		// Settings data
		$this->data['scdSettings'] = array(
			'currency'           => get_woocommerce_currency(),
			'currency_symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'date_format'        => get_option( 'date_format' ),
			'time_format'        => get_option( 'time_format' ),
			'start_of_week'      => get_option( 'start_of_week' ),
			'timezone'           => wp_timezone_string(),
			'decimal_separator'  => wc_get_price_decimal_separator(),
			'thousand_separator' => wc_get_price_thousand_separator(),
			'price_format'       => get_woocommerce_price_format(),
		);

		// Feature flags
		$this->data['scdFeatures'] = array(
			'analytics'         => $this->settings['enable_analytics'] ?? true,
			'bulk_actions'      => $this->settings['enable_bulk_actions'] ?? true,
			'export'            => $this->settings['enable_export'] ?? true,
			'import'            => $this->settings['enable_import'] ?? false,
			'advanced_schedule' => $this->settings['enable_advanced_schedule'] ?? true,
			'multi_currency'    => $this->settings['enable_multi_currency'] ?? false,
		);

		// Permissions
		$this->data['scdPermissions'] = array(
			'create_campaigns' => current_user_can( 'manage_woocommerce' ),
			'edit_campaigns'   => current_user_can( 'manage_woocommerce' ),
			'delete_campaigns' => current_user_can( 'manage_woocommerce' ),
			'view_analytics'   => current_user_can( 'view_woocommerce_reports' ),
			'manage_settings'  => current_user_can( 'manage_options' ),
		);

		// Dashboard data (only on main dashboard page)
		if ( isset( $_GET['page'] ) && 'smart-cycle-discounts' === $_GET['page'] ) {
			$this->data['scdDashboard'] = array(
				'nonce'          => wp_create_nonce( 'scd_main_dashboard' ),
				'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'dateFormat'     => get_option( 'date_format' ),
				'timeFormat'     => get_option( 'time_format' ),
				'i18n'           => array(
					'loading'      => __( 'Loading analytics...', 'smart-cycle-discounts' ),
					'error'        => __( 'Failed to load dashboard data', 'smart-cycle-discounts' ),
					'success'      => __( 'Dashboard updated', 'smart-cycle-discounts' ),
					'networkError' => __( 'Network error occurred', 'smart-cycle-discounts' ),
				),
			);
		}

		// Wizard data (only on wizard pages)
		if ( isset( $_GET['action'] ) && 'wizard' === $_GET['action'] ) {
			$this->data['scdWizardData'] = $this->get_wizard_data();
		}

		// Analytics data (only on analytics pages)
		if ( isset( $_GET['page'] ) && 'scd-analytics' === $_GET['page'] ) {
			$current_period = sanitize_text_field( $_GET['date_range'] ?? '30days' );
			$valid_ranges   = array( '24hours', '7days', '30days', '90days', 'custom' );
			if ( ! in_array( $current_period, $valid_ranges, true ) ) {
				$current_period = '30days';
			}

			$this->localize_analytics_data(
				array(
					'currentPeriod'   => $current_period,
					'refreshInterval' => 30000, // 30 seconds
				)
			);
		}

		// Settings page data (tab-specific)
		if ( isset( $_GET['page'] ) && 'scd-settings' === $_GET['page'] ) {
			$tab = sanitize_text_field( $_GET['tab'] ?? 'general' );
			if ( 'general' === $tab ) {
				$this->localize_general_settings_data();
			} elseif ( 'performance' === $tab ) {
				$this->localize_performance_settings_data();
			} elseif ( 'advanced' === $tab ) {
				$this->localize_advanced_settings_data();
			}
		}

		// Campaigns page data
		if ( isset( $_GET['page'] ) && 'scd-campaigns' === $_GET['page'] ) {
			// Campaign overview panel data (on list view and view action)
			$action = isset( $_GET['action'] ) ? $_GET['action'] : null;
			if ( in_array( $action, array( null, 'view' ), true ) ) {
				$this->data['scdOverviewPanel'] = array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'scd_admin_nonce' ),
					'editUrl' => admin_url( 'admin.php?page=scd-campaigns' ),
					'i18n'     => array(
						'loading'       => __( 'Loading campaign details...', 'smart-cycle-discounts' ),
						'error'         => __( 'Failed to load campaign details', 'smart-cycle-discounts' ),
						'networkError'  => __( 'Network error occurred', 'smart-cycle-discounts' ),
						'retry'         => __( 'Retry', 'smart-cycle-discounts' ),
						'editCampaign'  => __( 'Edit Campaign', 'smart-cycle-discounts' ),
						'close'         => __( 'Close', 'smart-cycle-discounts' ),
						'campaignPanel' => __( 'Campaign Overview', 'smart-cycle-discounts' ),
					),
				);
			}
		}

		// Analytics page data
		if ( isset( $_GET['page'] ) && 'scd-analytics' === $_GET['page'] ) {
			// Campaign overview panel data
			$this->data['scdOverviewPanel'] = array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'scd_admin_nonce' ),
				'editUrl' => admin_url( 'admin.php?page=scd-campaigns' ),
				'i18n'     => array(
					'loading'       => __( 'Loading campaign details...', 'smart-cycle-discounts' ),
					'error'         => __( 'Failed to load campaign details', 'smart-cycle-discounts' ),
					'networkError'  => __( 'Network error occurred', 'smart-cycle-discounts' ),
					'retry'         => __( 'Retry', 'smart-cycle-discounts' ),
					'editCampaign'  => __( 'Edit Campaign', 'smart-cycle-discounts' ),
					'close'         => __( 'Close', 'smart-cycle-discounts' ),
					'campaignPanel' => __( 'Campaign Overview', 'smart-cycle-discounts' ),
				),
			);
		}

		// Notifications page data
		if ( isset( $_GET['page'] ) && 'scd-notifications' === $_GET['page'] ) {
			$this->data['scdNotificationsL10n'] = array(
				'nonce'                  => wp_create_nonce( 'scd_admin_nonce' ),
				'sending'                => __( 'Sending...', 'smart-cycle-discounts' ),
				'testing'                => __( 'Testing...', 'smart-cycle-discounts' ),
				'testEmailSent'          => __( 'Test email sent successfully! Check your inbox.', 'smart-cycle-discounts' ),
				'testEmailFailed'        => __( 'Failed to send test email. Please check your settings.', 'smart-cycle-discounts' ),
				'connectionSuccess'      => __( 'Connection test successful!', 'smart-cycle-discounts' ),
				'connectionFailed'       => __( 'Connection test failed. Please check your settings.', 'smart-cycle-discounts' ),
				'sendgridApiKeyRequired' => __( 'SendGrid API key is required.', 'smart-cycle-discounts' ),
				'awsKeysRequired'        => __( 'Amazon SES access and secret keys are required.', 'smart-cycle-discounts' ),
				'invalidFromEmail'       => __( 'Please enter a valid from email address.', 'smart-cycle-discounts' ),
				'invalidRecipientEmail'  => __( 'Invalid recipient email address: %s', 'smart-cycle-discounts' ),
			);

			// Queue management data (only on queue tab)
			$tab = sanitize_text_field( $_GET['tab'] ?? 'settings' );
			if ( 'queue' === $tab ) {
				$this->data['scdQueueL10n'] = array(
					'nonce'         => wp_create_nonce( 'scd_admin_nonce' ),
					'processing'    => __( 'Processing...', 'smart-cycle-discounts' ),
					'retrying'      => __( 'Retrying...', 'smart-cycle-discounts' ),
					'clearing'      => __( 'Clearing...', 'smart-cycle-discounts' ),
					'processFailed' => __( 'Failed to process queue. Please try again.', 'smart-cycle-discounts' ),
					'retryFailed'   => __( 'Failed to retry emails. Please try again.', 'smart-cycle-discounts' ),
					'clearFailed'   => __( 'Failed to clear queue. Please try again.', 'smart-cycle-discounts' ),
					'confirmClear'  => __( 'Are you sure you want to clear old queue items? This action cannot be undone.', 'smart-cycle-discounts' ),
				);
			}
		}
	}

	/**
	 * Get i18n strings.
	 *
	 * @since 1.0.0
	 * @return array I18n strings.
	 */
	private function get_i18n_strings(): array {
		return array(
			// Common
			'loading'             => __( 'Loading...', 'smart-cycle-discounts' ),
			'saving'              => __( 'Saving...', 'smart-cycle-discounts' ),
			'saved'               => __( 'Saved', 'smart-cycle-discounts' ),
			'save_failed'         => __( 'Save failed', 'smart-cycle-discounts' ),
			'error'               => __( 'Error', 'smart-cycle-discounts' ),
			'success'             => __( 'Success', 'smart-cycle-discounts' ),
			'warning'             => __( 'Warning', 'smart-cycle-discounts' ),
			'info'                => __( 'Info', 'smart-cycle-discounts' ),
			'confirm'             => __( 'Confirm', 'smart-cycle-discounts' ),
			'cancel'              => __( 'Cancel', 'smart-cycle-discounts' ),
			'close'               => __( 'Close', 'smart-cycle-discounts' ),
			'yes'                 => __( 'Yes', 'smart-cycle-discounts' ),
			'no'                  => __( 'No', 'smart-cycle-discounts' ),

			// Actions
			'save'                => __( 'Save', 'smart-cycle-discounts' ),
			'delete'              => __( 'Delete', 'smart-cycle-discounts' ),
			'edit'                => __( 'Edit', 'smart-cycle-discounts' ),
			'view'                => __( 'View', 'smart-cycle-discounts' ),
			'duplicate'           => __( 'Duplicate', 'smart-cycle-discounts' ),
			'export'              => __( 'Export', 'smart-cycle-discounts' ),
			'import'              => __( 'Import', 'smart-cycle-discounts' ),

			// Validation
			'required'            => __( 'This field is required', 'smart-cycle-discounts' ),
			'invalid_email'       => __( 'Please enter a valid email address', 'smart-cycle-discounts' ),
			'invalid_number'      => __( 'Please enter a valid number', 'smart-cycle-discounts' ),
			'min_length'          => __( 'Minimum length is %d characters', 'smart-cycle-discounts' ),
			'max_length'          => __( 'Maximum length is %d characters', 'smart-cycle-discounts' ),

			// Confirmations
			'confirm_delete'      => __( 'Are you sure you want to delete this item?', 'smart-cycle-discounts' ),
			'confirm_bulk_delete' => __( 'Are you sure you want to delete %d items?', 'smart-cycle-discounts' ),
			'unsaved_changes'     => __( 'You have unsaved changes. Are you sure you want to leave?', 'smart-cycle-discounts' ),

			// Errors
			'generic_error'       => __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ),
			'network_error'       => __( 'Network error. Please check your connection.', 'smart-cycle-discounts' ),
			'permission_error'    => __( 'You do not have permission to perform this action.', 'smart-cycle-discounts' ),
			'validation_error'    => __( 'Please fix the errors below.', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Localize script.
	 *
	 * @since 1.0.0
	 * @param string $handle    Script handle.
	 * @param mixed  $localize  Localization config.
	 * @param array  $context   Current context.
	 * @return void
	 */
	public function localize_script( string $handle, $localize, array $context ): void {
		if ( is_string( $localize ) ) {
			// Simple object name
			$this->localize_simple( $handle, $localize );
		} elseif ( is_array( $localize ) ) {
			// Check if this is a structured localization with 'object_name' and 'data'
			if ( isset( $localize['object_name'] ) && isset( $localize['data'] ) ) {
				// Structured localization - add data to registry and localize
				$object_name = $localize['object_name'];
				$this->data[ $object_name ] = $localize['data'];
				$this->localize_simple( $handle, $object_name );
			} else {
				// Multiple objects (array of strings)
				foreach ( $localize as $object_name ) {
					if ( is_string( $object_name ) && ! empty( $object_name ) ) {
						$this->localize_simple( $handle, $object_name );
					}
				}
			}
		}

		// Mark as localized
		$this->localized[] = $handle;
	}

	/**
	 * Localize simple object.
	 *
	 * @since 1.0.0
	 * @param string $handle      Script handle.
	 * @param string $object_name Object name.
	 * @return void
	 */
	private function localize_simple( string $handle, string $object_name ): void {
		if ( ! isset( $this->data[ $object_name ] ) ) {
			// Don't generate data during AJAX requests
			if ( wp_doing_ajax() ) {
				return;
			}

			// Ensure data providers are registered if not already done
			if ( empty( $this->data ) ) {
				$this->register_data_providers();
			}

			// Special handling for wizard data - generate on demand
			if ( 'scdWizardData' === $object_name && isset( $_GET['action'] ) && 'wizard' === $_GET['action'] ) {
				$this->data[ $object_name ] = $this->get_wizard_data();
			} elseif ( 'scdNavigation' === $object_name && isset( $_GET['action'] ) && 'wizard' === $_GET['action'] ) {
				// Navigation data for wizard pages
				if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
					require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
				}
				$this->data[ $object_name ] = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'scd_wizard_nonce' ),
					'steps'    => array( 'basic', 'products', 'discounts', 'schedule', 'review' ),
				);
			} elseif ( 'scdAnalyticsTracking' === $object_name ) {
				// Analytics tracking localization
				if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
					require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
				}
				$this->data[ $object_name ] = array(
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'scd_public_tracking_nonce' ),
					'tracking_token' => wp_hash( 'scd_tracking_' . date( 'Y-m-d' ) . '_' . SCD_Ajax_Security::get_client_ip() ),
				);
			} else {
				// Try to generate data dynamically
				$this->data[ $object_name ] = apply_filters(
					'scd_localize_' . $object_name,
					array(),
					$handle
				);
			}
		}

		if ( ! empty( $this->data[ $object_name ] ) ) {
			$localized_data = $this->snake_to_camel_keys( $this->data[ $object_name ] );
			wp_localize_script( $handle, $object_name, $localized_data );
		}
	}

	/**
	 * Add custom localization data.
	 *
	 * @since 1.0.0
	 * @param string $object_name Object name.
	 * @param array  $data        Data to localize.
	 * @return void
	 */
	public function add_data( string $object_name, array $data ): void {
		if ( isset( $this->data[ $object_name ] ) ) {
			$this->data[ $object_name ] = array_merge( $this->data[ $object_name ], $data );
		} else {
			$this->data[ $object_name ] = $data;
		}
	}

	/**
	 * Get localization data.
	 *
	 * @since 1.0.0
	 * @param string $object_name Object name.
	 * @return array|null Data or null if not found.
	 */
	public function get_data( string $object_name ): ?array {
		return $this->data[ $object_name ] ?? null;
	}

	/**
	 * Convert snake_case keys to camelCase for JavaScript.
	 * Delegates to SCD_Case_Converter utility.
	 *
	 * @since 1.0.0
	 * @param array $data Data to convert.
	 * @return array Converted data with camelCase keys.
	 */
	private function snake_to_camel_keys( $data ) {
		// Ensure utility class is loaded
		if ( ! class_exists( 'SCD_Case_Converter' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/utilities/class-case-converter.php';
		}

		return SCD_Case_Converter::snake_to_camel( $data );
	}

	/**
	 * Print global data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function print_global_data(): void {
		// Don't print during AJAX requests
		if ( wp_doing_ajax() ) {
			return;
		}

		// Only on SCD pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'scd' ) === false ) {
			return;
		}

		?>
		<script type="text/javascript">
		/* SCD Global Configuration */
		window.SCD = window.SCD || {};
		window.SCD.config = <?php echo wp_json_encode( $this->get_global_config() ); ?>;
		</script>
		<?php
	}

	/**
	 * Get global configuration.
	 *
	 * @since 1.0.0
	 * @return array Global config.
	 */
	private function get_global_config(): array {
		return array(
			'version'     => SCD_VERSION,
			'api'         => array(
				'root'      => esc_url_raw( rest_url() ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'namespace' => 'scd/v1',
			),
			'assets'      => array(
				'images' => plugins_url( 'resources/assets/images/', SCD_PLUGIN_FILE ),
				'icons'  => plugins_url( 'resources/assets/images/icons/', SCD_PLUGIN_FILE ),
			),
			'debug'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'environment' => wp_get_environment_type(),
		);
	}

	/**
	 * Get wizard data for localization.
	 *
	 * @since 1.0.0
	 * @return array Wizard data.
	 */
	private function get_wizard_data(): array {
		// Phase 2: No session ID needed in JavaScript - handled by secure cookies

		$nonces = array();

		// Ensure security class is loaded
		if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
		}

		if ( class_exists( 'SCD_Ajax_Security' ) ) {
			$nonces = SCD_Ajax_Security::get_nonce_config();
		}

		$wizard_data = array(
			// Phase 2: Session management via secure cookies only - no sessionId needed in JS
			'nonce'             => wp_create_nonce( 'scd_wizard_nonce' ), // Wizard nonce for AJAX security
			'nonces'            => $nonces, // Include all nonces from security configuration
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'admin_url'         => admin_url(),
			'campaigns_url'     => admin_url( 'admin.php?page=scd-campaigns' ),
			'campaign_list_url' => admin_url( 'admin.php?page=scd-campaigns' ),
			'edit_draft_url'    => admin_url( 'admin.php?page=scd-campaigns&action=edit&id={id}' ),
			'plugin_url'        => trailingslashit( plugins_url( '', SCD_PLUGIN_FILE ) ),
			'steps'             => array( 'basic', 'products', 'discounts', 'schedule', 'review' ),
			'debug'             => defined( 'SCD_DEBUG' ) && SCD_DEBUG, // Add debug flag for JavaScript debug logger
			'navigation'        => array(
				'steps'    => array(
					'basic'     => array(
						'title'                    => __( 'Basic Information', 'smart-cycle-discounts' ),
						'position'                 => 1,
						'can_proceed_without_save' => false,
					),
					'products'  => array(
						'title'                    => __( 'Product Selection', 'smart-cycle-discounts' ),
						'position'                 => 2,
						'can_proceed_without_save' => true,
					),
					'discounts' => array(
						'title'                    => __( 'Discount Configuration', 'smart-cycle-discounts' ),
						'position'                 => 3,
						'can_proceed_without_save' => false,
					),
					'schedule'  => array(
						'title'                    => __( 'Schedule & Rotation', 'smart-cycle-discounts' ),
						'position'                 => 4,
						'can_proceed_without_save' => false,
					),
					'review'    => array(
						'title'                    => __( 'Review & Launch', 'smart-cycle-discounts' ),
						'position'                 => 5,
						'can_proceed_without_save' => false,
					),
				),
				'messages' => array(
					'confirm_complete'    => __( 'Are you ready to create this campaign?', 'smart-cycle-discounts' ),
					'processing_next'     => __( 'Processing...', 'smart-cycle-discounts' ),
					'processing_previous' => __( 'Loading previous step...', 'smart-cycle-discounts' ),
					'error_generic'       => __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ),
					'error_validation'    => __( 'Please fix the errors before continuing.', 'smart-cycle-discounts' ),
				),
			),
			'i18n'              => array(
				'confirm_leave' => __( 'You have unsaved changes. Are you sure you want to leave?', 'smart-cycle-discounts' ),
				'saving_data'   => __( 'Saving...', 'smart-cycle-discounts' ),
				'saved'         => __( 'Saved', 'smart-cycle-discounts' ),
				'error'         => __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ),
			),
			'strings'           => array(
				'unsaved_changes' => __( 'You have unsaved changes. Are you sure you want to leave?', 'smart-cycle-discounts' ),
				'session_expired' => __( 'Your session has expired. Please refresh the page.', 'smart-cycle-discounts' ),
				'save_error'      => __( 'Failed to save changes', 'smart-cycle-discounts' ),
				'save_success'    => __( 'Changes saved', 'smart-cycle-discounts' ),
				'loading_step'    => __( 'Loading step...', 'smart-cycle-discounts' ),
				'saving_data'     => __( 'Saving data...', 'smart-cycle-discounts' ),
				'validating_step' => __( 'Validating...', 'smart-cycle-discounts' ),
			),
			'sessionExpiration' => array(
				'modalTitle'            => __( 'Session Expiring Soon', 'smart-cycle-discounts' ),
				'warningStrong'         => __( 'Your wizard session will expire in 10 minutes due to inactivity.', 'smart-cycle-discounts' ),
				'draftSaved'            => __( 'Make sure to save your work before the session expires.', 'smart-cycle-discounts' ),
				'continuePrompt'        => __( 'Would you like to continue working?', 'smart-cycle-discounts' ),
				'continueButton'        => __( 'Continue Working', 'smart-cycle-discounts' ),
				'viewDraftsButton'      => __( 'View Drafts', 'smart-cycle-discounts' ),
				'sessionExpiredNoDraft' => __( 'Your session expired. Check your drafts to recover your work.', 'smart-cycle-discounts' ),
			),
			'validation'        => array(
				'messages' => $this->get_validation_messages(),
			),
			'constants'         => array(
				'product_selection_types' => array(
					'ALL_PRODUCTS'      => SCD_Product_Selection_Types::ALL_PRODUCTS,
					'SPECIFIC_PRODUCTS' => SCD_Product_Selection_Types::SPECIFIC_PRODUCTS,
					'RANDOM_PRODUCTS'   => SCD_Product_Selection_Types::RANDOM_PRODUCTS,
				),
			),
			'timezone'          => wp_timezone_string(),
			'debug_persistence' => true,  // Enable debug logging for wizard
		);

		$session_data = $this->load_wizard_session_data();

		if ( ! empty( $session_data ) ) {
			$wizard_data['current_campaign'] = $session_data;
		}

		return $wizard_data;
	}

	/**
	 * Load wizard session data for frontend use.
	 *
	 * @since 1.0.0
	 * @return array Wizard session data.
	 */
	private function load_wizard_session_data(): array {
		// Don't load session data during AJAX requests
		// The data will be loaded by the AJAX handlers themselves
		if ( wp_doing_ajax() ) {
			return array();
		}

		// Only load session data on wizard pages
		if ( ! isset( $_GET['action'] ) || ( $_GET['action'] !== 'wizard' && $_GET['action'] !== 'edit' ) ) {
			return array();
		}

		// Don't load session data if intent is to start fresh
		if ( isset( $_GET['intent'] ) && $_GET['intent'] === 'new' ) {
			return array();
		}

		try {
			if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
				require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
			}

			$state_service = new SCD_Wizard_State_Service();

			// CRITICAL FIX: Initialize change tracker for edit mode
			// The wizard controller calls initialize_with_intent() but Asset Localizer doesn't,
			// so we need to manually ensure the change tracker is initialized if in edit mode
			$intent = isset( $_GET['intent'] ) ? sanitize_text_field( $_GET['intent'] ) : '';
			if ( 'edit' === $intent ) {
				$state_service->initialize_with_intent( 'edit' );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			$all_data     = $state_service->get_all_data();
			$session_data = array();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// In edit mode, steps array exists but is empty - we need to load from change tracker
			$is_edit_mode  = isset( $all_data['is_edit_mode'] ) && $all_data['is_edit_mode'];
			$has_step_data = isset( $all_data['steps'] ) && is_array( $all_data['steps'] ) && ! empty( $all_data['steps'] );

			if ( $has_step_data && ! $is_edit_mode ) {
				// Create/continue mode: use session data
				$session_data = $all_data['steps'];

				// Include completed_steps for progress tracking
				if ( isset( $all_data['completed_steps'] ) && is_array( $all_data['completed_steps'] ) ) {
					$session_data['_completed_steps'] = $all_data['completed_steps'];
				}
			} else {
				// Fallback: try to get individual step data
				$steps = array( 'basic', 'products', 'discounts', 'schedule', 'review' );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$is_edit_mode = isset( $all_data['is_edit_mode'] ) && $all_data['is_edit_mode'];
				}

				foreach ( $steps as $step ) {
					$step_data = $state_service->get_step_data( $step );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					}

					if ( ! empty( $step_data ) ) {
						$session_data[ $step ] = $step_data;
					}
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			$campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			$intent      = isset( $_GET['intent'] ) ? sanitize_text_field( $_GET['intent'] ) : '';

			// Edit mode data loading is now handled by Change Tracker
			// Data is loaded on-demand from database, not decomposed into session
			if ( 'edit' === $intent && $campaign_id > 0 ) {
				$session_data['campaign_id']  = $campaign_id;
				$session_data['is_edit_mode'] = true;
			}

			// For products step, only load product IDs - not full product data
			// This prevents loading hundreds of products into frontend memory
			if ( isset( $session_data['products'] ) ) {
				$products_data  = $session_data['products'];
				$selection_type = $products_data['product_selection_type'] ?? 'all_products';

				// Only load specific product data if needed
				if ( 'specific_products' === $selection_type || 'individual' === $selection_type || 'manual' === $selection_type ) {
					$product_ids = $products_data['product_ids'] ?? array();
					// Limit to reasonable number for initial load
					$product_ids = array_slice( $product_ids, 0, 50 );
					if ( ! empty( $product_ids ) ) {
						$products = $this->load_products_by_selection( $selection_type, array( 'product_ids' => $product_ids ) );
						$session_data['products']['selected_products_data'] = $products;
					}
				}
			}

			return $session_data;

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Load products based on selection type.
	 *
	 * @since 1.0.0
	 * @param string $selection_type Product selection type.
	 * @param array  $products_data  Products step data.
	 * @return array Formatted products array.
	 */
	private function load_products_by_selection( string $selection_type, array $products_data ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = array();

		try {
			switch ( $selection_type ) {
				case 'specific_products':
				case 'individual':
				case 'manual':
					$product_ids = $products_data['product_ids'] ?? array();
					if ( ! empty( $product_ids ) ) {
						$products = $this->get_wc_products_by_ids( $product_ids );
					}
					break;

				case 'categories':
					$categories = $products_data['category_ids'] ?? array();
					if ( ! empty( $categories ) ) {
						$products = $this->get_wc_products_by_categories( $categories );
					}
					break;

				case 'all_products':
				case 'all':
				case 'random_products':
					// For these types, load all available products (limited for performance)
					$products = $this->get_all_wc_products();
					break;
			}
		} catch ( Exception $e ) {
		}

		return $products;
	}

	/**
	 * Get WooCommerce products by IDs.
	 *
	 * @since 1.0.0
	 * @param array $product_ids Product IDs.
	 * @return array Formatted products.
	 */
	private function get_wc_products_by_ids( array $product_ids ): array {
		$product_ids = array_map( 'intval', $product_ids );
		$product_ids = array_filter(
			$product_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $product_ids ) ) {
			return array();
		}

		try {
			$products = wc_get_products(
				array(
					'include' => $product_ids,
					'status'  => 'publish',
					'limit'   => -1,
					'return'  => 'objects',
				)
			);

			if ( is_wp_error( $products ) ) {
				return array();
			}

			return $this->format_products_for_frontend( $products );
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get WooCommerce products by categories.
	 *
	 * @since 1.0.0
	 * @param array $category_ids Category IDs.
	 * @return array Formatted products.
	 */
	private function get_wc_products_by_categories( array $category_ids ): array {
		$category_ids = array_map( 'intval', $category_ids );
		$category_ids = array_filter(
			$category_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $category_ids ) ) {
			return array();
		}

		try {
			$products = wc_get_products(
				array(
					'category' => $category_ids,
					'status'   => 'publish',
					'limit'    => 500, // Limit for performance
					'return'   => 'objects',
				)
			);

			if ( is_wp_error( $products ) ) {
				return array();
			}

			return $this->format_products_for_frontend( $products );
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get all WooCommerce products.
	 *
	 * @since 1.0.0
	 * @return array Formatted products.
	 */
	private function get_all_wc_products(): array {
		// Never load all products to frontend - this is a performance issue
		// Products should be loaded via AJAX as needed
		return array();
	}

	/**
	 * Format products for frontend consumption.
	 *
	 * @since 1.0.0
	 * @param array $products WooCommerce product objects.
	 * @return array Formatted products.
	 */
	private function format_products_for_frontend( array $products ): array {
		$formatted = array();

		foreach ( $products as $product ) {
			if ( ! ( $product instanceof WC_Product ) ) {
				continue;
			}

			$category_ids       = array();
			$product_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $product_categories ) && is_array( $product_categories ) ) {
				$category_ids = array_map( 'intval', $product_categories );
			}

			$formatted[] = array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'sku'          => $product->get_sku(),
				'price'        => (float) $product->get_regular_price(),
				'sale_price'   => (float) $product->get_sale_price(),
				'stock_status' => $product->get_stock_status(),
				'type'         => $product->get_type(),
				'categoryIds'  => $category_ids,  // JavaScript uses camelCase
			);
		}

		usort(
			$formatted,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $formatted;
	}

	/**
	 * Get validation messages.
	 *
	 * @since 1.0.0
	 * @return array Validation messages.
	 */
	private function get_validation_messages(): array {
		return array(
			// General validation messages
			'required'               => __( 'This field is required', 'smart-cycle-discounts' ),
			'invalid_email'          => __( 'Please enter a valid email address', 'smart-cycle-discounts' ),
			'invalid_number'         => __( 'Please enter a valid number', 'smart-cycle-discounts' ),
			'invalid_integer'        => __( 'Please enter a whole number', 'smart-cycle-discounts' ),
			'invalid_format'         => __( 'Invalid format', 'smart-cycle-discounts' ),
			'invalid_date'           => __( 'Please enter a valid date', 'smart-cycle-discounts' ),
			'min_value'              => __( 'Must be at least %s', 'smart-cycle-discounts' ),
			'max_value'              => __( 'Cannot exceed %s', 'smart-cycle-discounts' ),
			'min_length'             => __( 'Must be at least %s characters', 'smart-cycle-discounts' ),
			'max_length'             => __( 'Must not exceed %s characters', 'smart-cycle-discounts' ),

			// Basic step - Campaign Name
			'campaign_name'          => array(
				'required'   => __( 'Campaign name is required', 'smart-cycle-discounts' ),
				'min_length' => __( 'Campaign name must be at least 3 characters', 'smart-cycle-discounts' ),
				'max_length' => __( 'Campaign name cannot exceed 100 characters', 'smart-cycle-discounts' ),
			),

			// Basic step - Description
			'description'            => array(
				'max_length' => __( 'Description cannot exceed 500 characters', 'smart-cycle-discounts' ),
			),

			// Basic step - Priority
			'priority'               => array(
				'required' => __( 'Priority is required', 'smart-cycle-discounts' ),
				'min'      => __( 'Priority must be at least 1', 'smart-cycle-discounts' ),
				'max'      => __( 'Priority cannot exceed 5', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Priority must be a number between 1 and 5', 'smart-cycle-discounts' ),
			),

			// Basic step - Status
			'status'                 => array(
				'required' => __( 'Campaign status is required', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid campaign status', 'smart-cycle-discounts' ),
			),

			// Products step - Selection Type
			'product_selection_type' => array(
				'required' => __( 'Product selection type is required', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid product selection type', 'smart-cycle-discounts' ),
			),

			// Products step - Specific Products
			'specific_products'      => array(
				'required' => __( 'Please select at least one product', 'smart-cycle-discounts' ),
				'invalid'  => __( 'One or more selected products are invalid', 'smart-cycle-discounts' ),
			),

			// Products step - Categories
			'categories'             => array(
				'required' => __( 'Please select at least one category', 'smart-cycle-discounts' ),
				'invalid'  => __( 'One or more selected categories are invalid', 'smart-cycle-discounts' ),
			),

			// Products step - Random Count
			'random_count'           => array(
				'required' => __( 'Number of random products is required', 'smart-cycle-discounts' ),
				'min'      => __( 'Must select at least 1 product', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Number of products must be a positive integer', 'smart-cycle-discounts' ),
			),

			// Discounts step - Discount Type
			'discount_type'          => array(
				'required' => __( 'Discount type is required', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid discount type selected', 'smart-cycle-discounts' ),
			),

			// Discounts step - Percentage Value
			'percentage_value'       => array(
				'required' => __( 'Discount percentage is required', 'smart-cycle-discounts' ),
				'min'      => __( 'Discount percentage must be greater than 0', 'smart-cycle-discounts' ),
				'max'      => __( 'Discount percentage cannot exceed 100%', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Please enter a valid percentage between 0 and 100', 'smart-cycle-discounts' ),
			),

			// Discounts step - Fixed Value
			'fixed_value'            => array(
				'required' => __( 'Discount amount is required', 'smart-cycle-discounts' ),
				'min'      => __( 'Discount amount must be greater than 0', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Please enter a valid discount amount', 'smart-cycle-discounts' ),
			),

			// Discounts step - Tiered Config
			'tiered_config'          => array(
				'required'      => __( 'At least one tier is required', 'smart-cycle-discounts' ),
				'invalid'       => __( 'One or more tiers contain invalid data', 'smart-cycle-discounts' ),
				'tier_quantity' => __( 'Tier quantity must be greater than 0', 'smart-cycle-discounts' ),
				'tier_discount' => __( 'Tier discount must be greater than 0', 'smart-cycle-discounts' ),
			),

			// Discounts step - BOGO Config
			'bogo_config'            => array(
				'required'         => __( 'BOGO configuration is required', 'smart-cycle-discounts' ),
				'invalid'          => __( 'BOGO configuration is invalid', 'smart-cycle-discounts' ),
				'buy_quantity'     => __( 'Buy quantity must be at least 1', 'smart-cycle-discounts' ),
				'get_quantity'     => __( 'Get quantity must be at least 1', 'smart-cycle-discounts' ),
				'discount_percent' => __( 'Discount percentage must be between 0 and 100', 'smart-cycle-discounts' ),
			),

			// Discounts step - Threshold Config
			'threshold_config'       => array(
				'required'           => __( 'At least one threshold is required', 'smart-cycle-discounts' ),
				'invalid'            => __( 'One or more thresholds contain invalid data', 'smart-cycle-discounts' ),
				'threshold_amount'   => __( 'Threshold amount must be greater than 0', 'smart-cycle-discounts' ),
				'threshold_discount' => __( 'Threshold discount must be greater than 0', 'smart-cycle-discounts' ),
			),

			// Discounts step - Apply To
			'apply_to'               => array(
				'required' => __( 'Discount application method is required', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid discount application method', 'smart-cycle-discounts' ),
			),

			// Discounts step - Minimum Quantity
			'minimum_quantity'       => array(
				'min'     => __( 'Minimum quantity must be at least 1', 'smart-cycle-discounts' ),
				'invalid' => __( 'Please enter a valid minimum quantity', 'smart-cycle-discounts' ),
			),

			// Schedule step - Schedule Type
			'schedule_type'          => array(
				'required' => __( 'Schedule type is required', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid schedule type', 'smart-cycle-discounts' ),
			),

			// Schedule step - Start Date
			'start_date'             => array(
				'required'  => __( 'Start date is required', 'smart-cycle-discounts' ),
				'invalid'   => __( 'Please enter a valid start date', 'smart-cycle-discounts' ),
				'past_date' => __( 'Start date cannot be in the past', 'smart-cycle-discounts' ),
			),

			// Schedule step - End Date
			'end_date'               => array(
				'required'     => __( 'End date is required', 'smart-cycle-discounts' ),
				'invalid'      => __( 'Please enter a valid end date', 'smart-cycle-discounts' ),
				'before_start' => __( 'End date must be after start date', 'smart-cycle-discounts' ),
			),

			// Schedule step - Days of Week
			'days_of_week'           => array(
				'required' => __( 'Please select at least one day', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid day selection', 'smart-cycle-discounts' ),
			),

			// Schedule step - Usage Limits
			'usage_limit_per_user'   => array(
				'min'           => __( 'Usage limit per user must be at least 1', 'smart-cycle-discounts' ),
				'invalid'       => __( 'Please enter a valid usage limit', 'smart-cycle-discounts' ),
				'exceeds_total' => __( 'Per-user limit cannot exceed total usage limit', 'smart-cycle-discounts' ),
			),

			'usage_limit_total'      => array(
				'min'     => __( 'Total usage limit must be at least 1', 'smart-cycle-discounts' ),
				'invalid' => __( 'Please enter a valid total usage limit', 'smart-cycle-discounts' ),
			),

			'lifetime_usage_cap'     => array(
				'min'             => __( 'Lifetime usage cap must be at least 1', 'smart-cycle-discounts' ),
				'invalid'         => __( 'Please enter a valid lifetime usage cap', 'smart-cycle-discounts' ),
				'less_than_cycle' => __( 'Lifetime cap cannot be less than per-cycle limit', 'smart-cycle-discounts' ),
			),

			'per_cycle_usage_limit'  => array(
				'min'     => __( 'Per-cycle usage limit must be at least 1', 'smart-cycle-discounts' ),
				'invalid' => __( 'Please enter a valid per-cycle usage limit', 'smart-cycle-discounts' ),
			),

			// Review step - Launch Option
			'launch_option'          => array(
				'required' => __( 'Please select how to launch your campaign', 'smart-cycle-discounts' ),
				'invalid'  => __( 'Invalid launch option selected', 'smart-cycle-discounts' ),
			),

			// Navigation/Wizard messages
			'navigation'             => array(
				'incomplete_step' => __( 'Please complete all required fields before proceeding', 'smart-cycle-discounts' ),
				'critical_issues' => __( 'Critical issues must be fixed before launch', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Get field definitions.
	 *
	 * @since 1.0.0
	 * @return array Field definitions.
	 */
	private function get_field_definitions(): array {
		// Ensure the field definitions class is loaded
		if ( ! class_exists( 'SCD_Field_Definitions' ) ) {
			$schema_file = SCD_PLUGIN_DIR . 'includes/core/validation/class-field-definitions.php';
			if ( file_exists( $schema_file ) ) {
				require_once $schema_file;
			}
		}

		if ( class_exists( 'SCD_Field_Definitions' ) && method_exists( 'SCD_Field_Definitions', 'export_for_js' ) ) {
			return SCD_Field_Definitions::export_for_js();
		}

		return array();
	}

	/**
	 * Get validation constants.
	 *
	 * @since 1.0.0
	 * @return array Validation constants.
	 */
	private function get_validation_constants(): array {
		$validation_data = array();

		// Ensure the validation class is loaded
		if ( ! class_exists( 'SCD_Validation' ) ) {
			$validation_file = SCD_PLUGIN_DIR . 'includes/core/validation/class-validation.php';
			if ( file_exists( $validation_file ) ) {
				require_once $validation_file;
			}
		}

		if ( class_exists( 'SCD_Validation' ) && method_exists( 'SCD_Validation', 'get_js_data' ) ) {
			$validation_data = SCD_Validation::get_js_data();
		}

		// Merge with UI/UX constants that aren't validation rules
		return array(
			// Include all constants from PHP validation (min/max values, etc.)
			'constants'   => isset( $validation_data['constants'] ) ? $validation_data['constants'] : array(),

			// Include validation messages from PHP
			'messages'    => isset( $validation_data['messages'] ) ? $validation_data['messages'] : array(),

			// UI/UX timing settings (not validation rules)
			'timing'      => array(
				'debounce_delay'     => 150,
				'cache_duration'     => 300000,
				'request_timeout'    => 30000,
				'animation_duration' => 200,
			),

			// System limits
			'limits'      => array(
				'max_file_size'       => wp_max_upload_size(),
				'max_field_length'    => 255,
				'max_textarea_length' => 2000,
				'max_array_items'     => 100,
			),

			// CSS classes for validation UI
			'css_classes' => array(
				'error'         => 'error',
				'valid'         => 'valid',
				'validating'    => 'validating',
				'has_error'     => 'has-error',
				'field_error'   => 'scd-field-error',
				'error_message' => 'scd-error-message',
			),

			// JavaScript events
			'events'      => array(
				'field_valid'   => 'scd:validation:field:valid',
				'field_invalid' => 'scd:validation:field:invalid',
				'form_valid'    => 'scd:validation:form:valid',
				'form_invalid'  => 'scd:validation:form:invalid',
			),
		);
	}

	/**
	 * Localize analytics data.
	 *
	 * @since 1.0.0
	 * @param array $analytics_data Analytics data.
	 * @return void
	 */
	public function localize_analytics_data( array $analytics_data ): void {
		// Get all nonces from security configuration
		$nonces = array();

		// Ensure security class is loaded
		if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
		}

		if ( class_exists( 'SCD_Ajax_Security' ) ) {
			$nonces = SCD_Ajax_Security::get_nonce_config();
		}

		$this->data['scdAnalytics'] = array_merge(
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'scd_analytics_nonce' ),
				'nonces'            => $nonces,
				'currency'          => get_woocommerce_currency(),
				'currency_symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'currency_pos'      => get_option( 'woocommerce_currency_pos', 'left' ),
				'price_decimals'    => wc_get_price_decimals(),
				'decimal_separator' => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'endpoints'         => array(
					'overview'             => 'analytics_overview',
					'campaign_performance' => 'analytics_campaign_performance',
					'revenue_trend'        => 'analytics_revenue_trend',
					'top_products'         => 'analytics_top_products',
					'activity_feed'        => 'analytics_activity_feed',
					'export'               => 'analytics_export',
					'refresh_cache'        => 'analytics_refresh_cache',
				),
				'chart_config'      => array(
					'colors'             => $this->get_theme_colors(),
					'default_type'       => 'line',
					'animation_duration' => 750,
				),
				'date_ranges'       => $this->get_date_range_options(),
				'strings'           => array(
					'noData'        => __( 'No data available', 'smart-cycle-discounts' ),
					'exportSuccess' => __( 'Export completed successfully', 'smart-cycle-discounts' ),
					'exportError'   => __( 'Export failed. Please try again.', 'smart-cycle-discounts' ),
					'loading'       => __( 'Loading...', 'smart-cycle-discounts' ),
					'error'         => __( 'An error occurred', 'smart-cycle-discounts' ),
				),
			),
			$analytics_data
		);
	}

	/**
	 * Localize general settings data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function localize_general_settings_data(): void {
		$this->data['scdSettingsGeneral'] = array(
			'strings'  => array(
				'confirmReset' => __( 'Are you sure you want to reset all settings to defaults? This cannot be undone.', 'smart-cycle-discounts' ),
			),
			'defaults' => array(
				'enable_plugin'                => true,
				'default_discount_type'        => 'percentage',
				'default_priority'             => 3,
				'enable_analytics'             => true,
				'enable_logging'               => true,
				'log_level'                    => 'info',
				'cache_duration'               => 3600,
				'enable_frontend_display'      => true,
				'discount_badge_position'      => 'before_price',
				'countdown_timer_style'        => 'modern',
				'enable_email_notifications'   => false,
				'admin_email_notifications'    => true,
				'customer_email_notifications' => false,
				'currency_position'            => 'before',
				'decimal_places'               => 2,
				'enable_debug_mode'            => false,
				'cleanup_data_on_uninstall'    => false,
			),
		);
	}

	/**
	 * Localize performance settings data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function localize_performance_settings_data(): void {
		$this->data['scdSettingsPerformance'] = array(
			'nonce'   => wp_create_nonce( 'scd_admin_nonce' ),
			'strings' => array(
				'confirmClearCache' => __( 'Are you sure you want to clear all plugin cache? This will temporarily reduce performance until the cache rebuilds.', 'smart-cycle-discounts' ),
			),
		);
	}

	/**
	 * Localize advanced settings data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function localize_advanced_settings_data(): void {
		$this->data['scdSettingsAdvanced'] = array(
			'strings'  => array(
				'confirmRegenerateWebhook' => __( 'Are you sure you want to regenerate the webhook secret? This will invalidate existing webhook configurations.', 'smart-cycle-discounts' ),
				'confirmReset'             => __( 'Are you sure you want to reset all advanced settings to defaults? This cannot be undone.', 'smart-cycle-discounts' ),
			),
			'defaults' => array(
				'enable_rest_api'              => true,
				'api_rate_limit'               => 100,
				'api_rate_limit_window'        => 3600,
				'enable_webhooks'              => false,
				'enable_database_optimization' => true,
				'auto_cleanup_logs'            => true,
				'log_retention_days'           => 30,
				'enable_query_optimization'    => true,
				'enable_object_cache'          => true,
				'cache_compression'            => false,
				'enable_cdn_support'           => false,
				'cdn_url'                      => '',
				'enable_custom_css'            => false,
				'custom_css'                   => '',
				'enable_custom_js'             => false,
				'custom_js'                    => '',
				'enable_advanced_security'     => true,
				'security_headers'             => true,
				'enable_ip_blocking'           => false,
				'blocked_ips'                  => '',
				'enable_advanced_analytics'    => false,
				'analytics_retention_days'     => 365,
				'enable_a_b_testing'           => false,
				'enable_export_import'         => true,
				'enable_backup_restore'        => false,
				'backup_frequency'             => 'weekly',
			),
		);
	}

	/**
	 * Get date range options.
	 *
	 * @since 1.0.0
	 * @return array Date range options.
	 */
	private function get_date_range_options(): array {
		return array(
			'today'         => __( 'Today', 'smart-cycle-discounts' ),
			'yesterday'     => __( 'Yesterday', 'smart-cycle-discounts' ),
			'last_7_days'   => __( 'Last 7 Days', 'smart-cycle-discounts' ),
			'last_30_days'  => __( 'Last 30 Days', 'smart-cycle-discounts' ),
			'this_month'    => __( 'This Month', 'smart-cycle-discounts' ),
			'last_month'    => __( 'Last Month', 'smart-cycle-discounts' ),
			'last_3_months' => __( 'Last 3 Months', 'smart-cycle-discounts' ),
			'last_6_months' => __( 'Last 6 Months', 'smart-cycle-discounts' ),
			'this_year'     => __( 'This Year', 'smart-cycle-discounts' ),
			'custom'        => __( 'Custom Range', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Get theme colors from WordPress admin color scheme and WooCommerce.
	 *
	 * @since 1.0.0
	 * @return array Theme colors.
	 */
	private function get_theme_colors(): array {
		// Get WordPress admin color scheme colors from shared utility
		$colors             = SCD_Theme_Colors::get_theme_colors();
		$admin_color_scheme = get_user_meta( get_current_user_id(), 'admin_color', true );

		$theme_primary = get_theme_mod( 'primary_color' );
		$theme_accent  = get_theme_mod( 'accent_color' );

		if ( ! empty( $theme_primary ) ) {
			$colors['primary'] = $theme_primary;
		}
		if ( ! empty( $theme_accent ) ) {
			$colors['accent'] = $theme_accent;
		}

		// Apply filter for custom theme integration
		return apply_filters( 'scd_theme_colors', $colors, $admin_color_scheme );
	}


	/**
	 * Check if handle is localized.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @return bool True if localized.
	 */
	public function is_localized( string $handle ): bool {
		return in_array( $handle, $this->localized, true );
	}
}