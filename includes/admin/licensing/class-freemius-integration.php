<?php
/**
 * Freemius Integration Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/class-freemius-integration.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward compatibility: Define legacy uninstall callback function.
 *
 * This function was registered as an uninstall callback in older versions.
 * It must exist globally for WordPress to call it during plugin deletion.
 * Without this, users cannot delete the plugin if they had an older version installed.
 *
 * @since 1.0.0
 */
if ( ! function_exists( 'wsscd_fs_uninstall_cleanup' ) ) {
	/**
	 * Legacy uninstall cleanup function.
	 *
	 * Delegates to the class method or performs basic cleanup.
	 */
	function wsscd_fs_uninstall_cleanup() {
		// Delegate to the class method if available.
		if ( class_exists( 'WSSCD_Freemius_Integration' ) && method_exists( 'WSSCD_Freemius_Integration', 'handle_uninstall' ) ) {
			WSSCD_Freemius_Integration::handle_uninstall();
			return;
		}

		// Fallback: Try to load and run the uninstaller directly.
		if ( class_exists( 'WSSCD_Uninstaller' ) ) {
			WSSCD_Uninstaller::uninstall();
			return;
		}

		// Last resort: Basic cleanup.
		global $wpdb;

		delete_option( 'wsscd_settings' );
		delete_option( 'wsscd_version' );
		delete_option( 'wsscd_db_version' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient cleanup during uninstall; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_wsscd_%'
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient cleanup during uninstall; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_wsscd_%'
			)
		);
	}
}

/**
 * Freemius Integration Class
 *
 * Initializes and configures Freemius SDK for licensing.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Freemius_Integration {

	/**
	 * Freemius instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Freemius|null    Freemius SDK instance.
	 */
	private static $freemius = null;

	/**
	 * Hidden Freemius admin notice types.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    Notice types to suppress.
	 */
	private static $hidden_notices = array(
		'trial_promotion',
		'promotion',
	);

	/**
	 * Initialize Freemius SDK.
	 *
	 * @since    1.0.0
	 * @return   Freemius|null    Freemius SDK instance or null if not available.
	 */
	public static function init() {
		global $wsscd_fs;

		if ( isset( $wsscd_fs ) && is_object( $wsscd_fs ) ) {
			return $wsscd_fs;
		}

		if ( ! file_exists( WSSCD_PLUGIN_DIR . 'vendor/freemius/start.php' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'freemius_missing_notice' ) );
			return null;
		}

		// Note: Freemius SDK debug/dev mode constants (WP_FS__DEV_MODE, WP_FS__DEBUG_SDK, etc.)
		// should be set in wp-config.php by site administrators when needed for debugging.
		// We don't define them here to avoid using the reserved WP_ prefix in plugin code.

		require_once WSSCD_PLUGIN_DIR . 'vendor/freemius/start.php';

		$wsscd_fs = fs_dynamic_init(
			array(
				'id'                  => '21492',
				'slug'                => 'smart-cycle-discounts',
				'type'                => 'plugin',
				'public_key'          => 'pk_4adf9836495f54c692369525c1000',
				'is_premium'          => true, // This plugin contains premium features (freemium model).
				'is_premium_only'     => false, // Works without a license - users unlock features by purchasing.
				'has_premium_version' => true, // Premium version with additional Pro-only files marked with @fs_premium_only.
				'has_addons'          => false,
				'has_paid_plans'      => true, // Plugin HAS paid plans available.
				'is_live'             => true, // Enable live mode (production).
				'is_org_compliant'    => true, // WordPress.org compliant - shows skip button, allows anonymous usage.
				'anonymous_mode'      => false, // Show opt-in on first install (with Skip button).
				'opt_in'              => true, // Show opt-in screen on activation.
				'enable_anonymous'    => true, // Allow anonymous usage if user skips.
				'anonymous_mode_after_days' => 14, // Show opt-in reminder after 14 days if user skipped.
				'menu'                => array(
					'slug'       => 'smart-cycle-discounts',
					'first-path' => 'admin.php?page=smart-cycle-discounts',
					'support'    => false, // Support Forum link removed; use in-plugin Help & Support page.
					'contact'    => true, // Show Contact Us submenu.
				),
				'support'             => array(
					'forum_url'       => 'https://wordpress.org/support/plugin/smart-cycle-discounts/',
					'support_url'     => 'https://webstepper.io/contact-us/',
				),
				'navigation'          => 'menu', // Show Freemius submenu items (Account, Contact, etc.).
			)
		);

		self::$freemius = $wsscd_fs;
		self::setup_hooks();

		return $wsscd_fs;
	}

	/**
	 * Setup Freemius hooks and filters.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function setup_hooks() {
		// Disable Freemius debug mode.
		add_filter( 'fs_is_dev_mode', '__return_false' );
		add_filter( 'fs_is_debug_mode', '__return_false' );
		add_filter( 'fs_show_trial_as_pricing_option', '__return_false' );

		// Ensure SSL verification is enabled for all Freemius API requests.
		add_filter( 'http_request_args', array( __CLASS__, 'ensure_ssl_verification' ), 10, 2 );

		// Account and license change hooks.
		self::$freemius->add_action( 'after_account_connection', array( __CLASS__, 'after_activation' ) );
		self::$freemius->add_action( 'after_premium_subscription_change', array( __CLASS__, 'after_plan_change' ) );

		// License activation/deactivation (specific events with user notices).
		self::$freemius->add_action( 'after_license_activation', array( __CLASS__, 'after_license_activated' ) );
		self::$freemius->add_action( 'after_license_deactivation', array( __CLASS__, 'after_license_deactivated' ) );

		// License monitoring for security.
		self::$freemius->add_filter( 'license_key_maxed', array( __CLASS__, 'handle_license_maxed' ), 10, 2 );

		// Admin notice filters.
		add_filter( 'fs_show_admin_notice', array( __CLASS__, 'filter_admin_notices' ), 10, 2 );

		// Uninstall hook - use static method to ensure it's available during uninstall.
		self::$freemius->add_action( 'after_uninstall', array( __CLASS__, 'handle_uninstall' ) );

		// Opt-in screen customizations.
		self::setup_optin_customizations();

		// Force redirect after opt-in/skip to plugin dashboard (correct URL and capability).
		self::$freemius->add_filter( 'after_connect_url', array( __CLASS__, 'filter_after_activation_redirect_url' ) );
		self::$freemius->add_filter( 'after_skip_url', array( __CLASS__, 'filter_after_activation_redirect_url' ) );

		// Style the reminder admin notice.
		add_action( 'admin_head', array( __CLASS__, 'inject_reminder_notice_styles' ) );
	}

	/**
	 * Setup opt-in screen customizations.
	 *
	 * Customizes the Freemius opt-in screen with modern styling and messaging.
	 *
	 * @since    1.2.1
	 * @access   private
	 * @return   void
	 */
	private static function setup_optin_customizations() {
		// Custom plugin icon.
		self::$freemius->add_filter( 'plugin_icon', array( __CLASS__, 'custom_optin_icon' ) );

		// Custom opt-in messages for new users.
		self::$freemius->add_filter( 'connect_header', array( __CLASS__, 'custom_connect_header' ) );
		self::$freemius->add_filter( 'connect_message', array( __CLASS__, 'custom_connect_message' ), 10, 6 );

		// Custom opt-in messages for existing users (on update).
		self::$freemius->add_filter( 'connect_header_on_update', array( __CLASS__, 'custom_connect_header_on_update' ) );
		self::$freemius->add_filter( 'connect_message_on_update', array( __CLASS__, 'custom_connect_message_on_update' ), 10, 6 );

		// Custom button labels.
		self::setup_optin_button_labels();

		// Inject custom styling.
		self::$freemius->add_action( 'connect/before', array( __CLASS__, 'inject_optin_styles' ) );
	}

	/**
	 * Filter redirect URL after opt-in or skip so users land on the plugin dashboard.
	 *
	 * Ensures the post-activation redirect uses the correct admin page URL and capability.
	 *
	 * @since    1.2.1
	 * @param    string $url    Redirect URL from Freemius (may be wrong in some setups).
	 * @return   string         Plugin dashboard URL.
	 */
	public static function filter_after_activation_redirect_url( $url ) {
		return admin_url( 'admin.php?page=smart-cycle-discounts' );
	}

	/**
	 * Get custom plugin icon URL.
	 *
	 * @since    1.2.1
	 * @return   string    URL to custom icon.
	 */
	public static function custom_optin_icon() {
		return 'https://webstepper.io/wp-content/uploads/2025/12/smart-cycle-discounts.svg';
	}

	/**
	 * Custom connect header for new users.
	 *
	 * @since    1.2.1
	 * @param    string $header_html    Default header HTML.
	 * @return   string                 Custom header HTML.
	 */
	public static function custom_connect_header( $header_html ) {
		return esc_html__( 'Welcome to Smart Cycle Discounts!', 'smart-cycle-discounts' );
	}

	/**
	 * Custom connect message for new users.
	 *
	 * @since    1.2.1
	 * @param    string $message          Default message.
	 * @param    string $user_first_name  User's first name.
	 * @param    string $product_title    Product title.
	 * @param    string $user_login       User login.
	 * @param    string $site_link        Site link.
	 * @param    string $freemius_link    Freemius link.
	 * @return   string                   Custom message.
	 */
	public static function custom_connect_message( $message, $user_first_name, $product_title, $user_login, $site_link, $freemius_link ) {
		return sprintf(
			/* translators: %1$s: User's first name, %2$s: Product title (bold), %3$s: Freemius link */
			__(
				'Hey %1$s, thanks for installing %2$s!<br><br>Opt in to receive security updates and help us improve compatibility. We only collect basic environment info — your store data stays private.<br><br>Powered by %3$s.',
				'smart-cycle-discounts'
			),
			'<strong>' . esc_html( $user_first_name ) . '</strong>',
			'<strong>' . esc_html( $product_title ) . '</strong>',
			$freemius_link
		);
	}

	/**
	 * Custom connect header for existing users (on update).
	 *
	 * @since    1.2.1
	 * @param    string $header_html    Default header HTML.
	 * @return   string                 Custom header HTML.
	 */
	public static function custom_connect_header_on_update( $header_html ) {
		$user = wp_get_current_user();
		return sprintf(
			/* translators: %s: User's first name */
			esc_html__( 'Hey %s, quick update!', 'smart-cycle-discounts' ),
			esc_html( $user->user_firstname ? $user->user_firstname : $user->display_name )
		);
	}

	/**
	 * Custom connect message for existing users (on update).
	 *
	 * @since    1.2.1
	 * @param    string $message          Default message.
	 * @param    string $user_first_name  User's first name.
	 * @param    string $product_title    Product title.
	 * @param    string $user_login       User login.
	 * @param    string $site_link        Site link.
	 * @param    string $freemius_link    Freemius link.
	 * @return   string                   Custom message.
	 */
	public static function custom_connect_message_on_update( $message, $user_first_name, $product_title, $user_login, $site_link, $freemius_link ) {
		return sprintf(
			/* translators: %1$s: Product title (bold), %2$s: Freemius link */
			__(
				'We\'ve added a one-time opt-in to %1$s.<br><br>Opt in to get notified about updates and help us ensure compatibility. Skip if you prefer — the plugin works great either way.<br><br>Powered by %2$s.',
				'smart-cycle-discounts'
			),
			'<strong>' . esc_html( $product_title ) . '</strong>',
			$freemius_link
		);
	}

	/**
	 * Setup custom opt-in button labels.
	 *
	 * Defers the fs_override_i18n() call to the 'init' action to ensure
	 * translations are loaded (WordPress 6.7+ requirement).
	 *
	 * @since    1.2.1
	 * @access   private
	 * @return   void
	 */
	private static function setup_optin_button_labels() {
		// Defer translation override until init to avoid loading translations too early (WP 6.7+).
		add_action(
			'init',
			function() {
				if ( function_exists( 'fs_override_i18n' ) ) {
					fs_override_i18n(
						array(
							'opt-in-connect' => __( 'Yes, count me in!', 'smart-cycle-discounts' ),
							'skip'           => __( 'Skip for now', 'smart-cycle-discounts' ),
						),
						'smart-cycle-discounts'
					);
				}
			},
			1
		);
	}

	/**
	 * Inject custom styles for the opt-in screen.
	 *
	 * @since    1.2.1
	 * @param    array $activation_state    Activation state data.
	 * @return   string                     Custom HTML/CSS to inject.
	 */
	public static function inject_optin_styles( $activation_state ) {
		ob_start();
		?>
		<style>
			/* Modern opt-in styling for Smart Cycle Discounts */
			.fs-content {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.fs-content .fs-visual {
				background: linear-gradient(135deg, #2271b1 0%, #0a4b78 100%) !important;
				border-radius: 12px 12px 0 0 !important;
				padding: 30px !important;
			}
			.fs-content .fs-visual .fs-site-icon,
			.fs-content .fs-visual .fs-plugin-icon {
				border-radius: 12px !important;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
			}
			.fs-content .fs-message {
				font-size: 14px !important;
				line-height: 1.7 !important;
				color: #1e1e1e !important;
			}
			.fs-content .fs-actions .button.button-primary {
				background: linear-gradient(135deg, #2271b1 0%, #135e96 100%) !important;
				border: none !important;
				border-radius: 6px !important;
				padding: 10px 24px !important;
				font-size: 14px !important;
				font-weight: 600 !important;
				text-transform: none !important;
				box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3) !important;
				transition: all 0.2s ease !important;
			}
			.fs-content .fs-actions .button.button-primary:hover {
				background: linear-gradient(135deg, #135e96 0%, #0a4b78 100%) !important;
				box-shadow: 0 4px 12px rgba(34, 113, 177, 0.4) !important;
				transform: translateY(-1px) !important;
			}
			.fs-content .fs-actions .button:not(.button-primary) {
				border-radius: 6px !important;
				padding: 10px 20px !important;
				font-size: 14px !important;
				transition: all 0.2s ease !important;
			}
			.fs-content .fs-permissions {
				border-radius: 8px !important;
				margin-top: 20px !important;
			}
			.fs-content .fs-permissions .fs-permission {
				padding: 12px 15px !important;
				border-bottom: 1px solid #f0f0f0 !important;
			}
			.fs-content .fs-permissions .fs-permission:last-child {
				border-bottom: none !important;
			}
			.fs-content .fs-permissions .fs-permission-description {
				color: #646970 !important;
				font-size: 13px !important;
			}
			/* Freemius logo subtle styling */
			.fs-content .fs-freemius-link {
				opacity: 0.7 !important;
				transition: opacity 0.2s !important;
			}
			.fs-content .fs-freemius-link:hover {
				opacity: 1 !important;
			}
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inject custom styles for Freemius reminder admin notices.
	 *
	 * @since    1.2.1
	 * @return   void
	 */
	public static function inject_reminder_notice_styles() {
		?>
		<style>
			/* Modern styling for Freemius reminder notices */
			.fs-notice.fs-slug-smart-cycle-discounts,
			.notice[data-plugin="smart-cycle-discounts"] {
				border: none !important;
				border-left: 4px solid #2271b1 !important;
				border-radius: 0 8px 8px 0 !important;
				background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
				padding: 16px 20px !important;
				margin: 20px 20px 20px 0 !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body,
			.notice[data-plugin="smart-cycle-discounts"] .fs-notice-body {
				display: flex !important;
				align-items: center !important;
				gap: 16px !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body p,
			.notice[data-plugin="smart-cycle-discounts"] p {
				font-size: 14px !important;
				line-height: 1.6 !important;
				color: #1d2327 !important;
				margin: 0 !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body .button,
			.notice[data-plugin="smart-cycle-discounts"] .button {
				border-radius: 6px !important;
				padding: 8px 16px !important;
				font-size: 13px !important;
				font-weight: 600 !important;
				transition: all 0.2s ease !important;
				text-decoration: none !important;
				margin-left: 8px !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body .button-primary,
			.notice[data-plugin="smart-cycle-discounts"] .button-primary {
				background: linear-gradient(135deg, #2271b1 0%, #135e96 100%) !important;
				border: none !important;
				color: #fff !important;
				box-shadow: 0 2px 6px rgba(34, 113, 177, 0.25) !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body .button-primary:hover,
			.notice[data-plugin="smart-cycle-discounts"] .button-primary:hover {
				background: linear-gradient(135deg, #135e96 0%, #0a4b78 100%) !important;
				box-shadow: 0 4px 10px rgba(34, 113, 177, 0.35) !important;
				transform: translateY(-1px) !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body .button:not(.button-primary),
			.notice[data-plugin="smart-cycle-discounts"] .button:not(.button-primary) {
				background: #f0f0f1 !important;
				border: 1px solid #c3c4c7 !important;
				color: #50575e !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .fs-notice-body .button:not(.button-primary):hover,
			.notice[data-plugin="smart-cycle-discounts"] .button:not(.button-primary):hover {
				background: #e0e0e1 !important;
				border-color: #8c8f94 !important;
			}
			/* Plugin icon in notice */
			.fs-notice.fs-slug-smart-cycle-discounts .fs-plugin-icon,
			.notice[data-plugin="smart-cycle-discounts"] .fs-plugin-icon {
				width: 40px !important;
				height: 40px !important;
				border-radius: 8px !important;
				box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;
			}
			/* Dismiss button */
			.fs-notice.fs-slug-smart-cycle-discounts .notice-dismiss,
			.notice[data-plugin="smart-cycle-discounts"] .notice-dismiss {
				top: 50% !important;
				transform: translateY(-50%) !important;
				padding: 10px !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .notice-dismiss:before,
			.notice[data-plugin="smart-cycle-discounts"] .notice-dismiss:before {
				color: #646970 !important;
				transition: color 0.2s ease !important;
			}
			.fs-notice.fs-slug-smart-cycle-discounts .notice-dismiss:hover:before,
			.notice[data-plugin="smart-cycle-discounts"] .notice-dismiss:hover:before {
				color: #d63638 !important;
			}
		</style>
		<?php
	}

	/**
	 * Handle actions after Freemius activation.
	 *
	 * @since    1.0.0
	 * @param    WP_User $user    WordPress user object.
	 * @return   void
	 */
	public static function after_activation( $user ) {
		if ( function_exists( 'wsscd_log_info' ) ) {
			wsscd_log_info(
				'Freemius account connected',
				array(
					'user_id'    => $user->ID,
					'user_email' => $user->user_email,
				)
			);
		}

		self::clear_feature_gate_cache();

		// Force immediate validation after account connection.
		if ( class_exists( 'WSSCD_License_Manager' ) ) {
			$license_manager = WSSCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}
	}

	/**
	 * Handle actions after premium plan change.
	 *
	 * Clears caches, validates license, and shows success notice to user.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function after_plan_change() {
		self::clear_feature_gate_cache();

		if ( function_exists( 'wsscd_log_info' ) ) {
			wsscd_log_info(
				'Freemius plan changed',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		// Force immediate validation after plan change.
		if ( class_exists( 'WSSCD_License_Manager' ) ) {
			$license_manager = WSSCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}

		// Show upgrade success notice.
		add_action( 'admin_notices', array( __CLASS__, 'show_upgrade_success_notice' ) );
	}

	/**
	 * Show upgrade success notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function show_upgrade_success_notice() {
		// Only show if user just upgraded to premium.
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() || ! wsscd_fs()->is_premium() ) {
			return;
		}

		// Don't show on wizard pages - they break the layout.
		if ( self::is_wizard_page() ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Welcome to Smart Cycle Discounts Pro!', 'smart-cycle-discounts' ); ?></strong><br>
				<?php esc_html_e( 'All Pro features are now active. You now have advanced discount types, detailed analytics, priority support, and more!', 'smart-cycle-discounts' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle actions after license activation.
	 *
	 * Fired when user manually enters and activates a license key.
	 * Ensures immediate cache clear and feature unlock.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function after_license_activated() {
		self::clear_feature_gate_cache();

		if ( function_exists( 'wsscd_log_info' ) ) {
			wsscd_log_info(
				'License key activated',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		// Force immediate validation after license activation.
		if ( class_exists( 'WSSCD_License_Manager' ) ) {
			$license_manager = WSSCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}

		/**
		 * Fires after a license key is activated.
		 *
		 * Used by cache manager and dashboard service to invalidate caches
		 * so pro features are immediately available.
		 *
		 * @since 1.1.9
		 */
		do_action( 'wsscd_license_activated' );

		// Show activation success notice.
		add_action( 'admin_notices', array( __CLASS__, 'show_license_activated_notice' ) );
	}

	/**
	 * Handle actions after license deactivation.
	 *
	 * Fired when user deactivates their license key.
	 * Ensures immediate cache clear and feature lock.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function after_license_deactivated() {
		self::clear_feature_gate_cache();

		if ( function_exists( 'wsscd_log_info' ) ) {
			wsscd_log_info(
				'License key deactivated',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		// Force immediate validation after license deactivation.
		if ( class_exists( 'WSSCD_License_Manager' ) ) {
			$license_manager = WSSCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}

		/**
		 * Fires after a license key is deactivated.
		 *
		 * Used by cache manager and dashboard service to invalidate caches
		 * so pro features are immediately locked.
		 *
		 * @since 1.1.9
		 */
		do_action( 'wsscd_license_deactivated' );
	}

	/**
	 * Show license activated success notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function show_license_activated_notice() {
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() || ! wsscd_fs()->is_premium() ) {
			return;
		}

		// Don't show on wizard pages - they break the layout.
		if ( self::is_wizard_page() ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'License Activated Successfully!', 'smart-cycle-discounts' ); ?></strong><br>
				<?php esc_html_e( 'All Pro features are now available. Thank you for upgrading!', 'smart-cycle-discounts' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Clear Feature Gate and License Manager caches.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function clear_feature_gate_cache() {
		// Clear Feature Gate cache.
		if ( class_exists( 'WSSCD_Feature_Gate' ) && function_exists( 'wsscd_get_instance' ) ) {
			try {
				$container = wsscd_get_instance()->get_container();
				if ( $container && $container->has( 'feature_gate' ) ) {
					$feature_gate = $container->get( 'feature_gate' );
					if ( method_exists( $feature_gate, 'clear_cache' ) ) {
						$feature_gate->clear_cache();
					}
				}
			} catch ( Exception $e ) {
				// Log cache clearing failure.
				if ( function_exists( 'wsscd_log_warning' ) ) {
					wsscd_log_warning(
						'Failed to clear feature gate cache',
						array(
							'error' => $e->getMessage(),
						)
					);
				}
			}
		}

		// Clear License Manager cache.
		if ( class_exists( 'WSSCD_License_Manager' ) ) {
			$license_manager = WSSCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'clear_validation_cache' ) ) {
				$license_manager->clear_validation_cache();
			}
		}

		// Clear cache groups affected by license changes.
		if ( function_exists( 'wsscd_get_instance' ) ) {
			try {
				$container = wsscd_get_instance()->get_container();
				if ( $container && $container->has( 'cache' ) ) {
					$cache = $container->get( 'cache' );
					if ( method_exists( $cache, 'delete_group' ) ) {
						$cache->delete_group( 'settings' );
						$cache->delete_group( 'campaigns' );
						$cache->delete_group( 'analytics' );
					}
				}
			} catch ( Exception $e ) {
				if ( function_exists( 'wsscd_log_warning' ) ) {
					wsscd_log_warning(
						'Failed to clear cache groups after license change',
						array(
							'error' => $e->getMessage(),
						)
					);
				}
			}
		}
	}

	/**
	 * Handle license activation limit reached.
	 *
	 * @since    1.0.0
	 * @param    bool   $is_maxed    Whether license reached activation limit.
	 * @param    object $license     License object.
	 * @return   bool                Unchanged value.
	 */
	public static function handle_license_maxed( $is_maxed, $license ) {
		if ( $is_maxed && function_exists( 'wsscd_log_warning' ) ) {
			wsscd_log_warning(
				'License activation limit reached',
				array(
					'license_id' => isset( $license->id ) ? $license->id : 'unknown',
				)
			);
		}

		return $is_maxed;
	}

	/**
	 * Filter Freemius admin notices.
	 *
	 * @since    1.0.0
	 * @param    bool   $show    Whether to show notice.
	 * @param    string $type    Notice type.
	 * @return   bool            Filtered value.
	 */
	public static function filter_admin_notices( $show, $type ) {
		if ( in_array( $type, self::$hidden_notices, true ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Check if current page is the wizard page.
	 *
	 * Used to exclude admin notices from wizard pages as they break the layout.
	 *
	 * @since    1.4.0
	 * @access   private
	 * @return   bool    True if on wizard page.
	 */
	private static function is_wizard_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display context check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display context check.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		return 'wsscd-campaigns' === $page && 'wizard' === $action;
	}

	/**
	 * Ensure SSL certificate verification for Freemius API requests.
	 *
	 * Security measure: Always verify SSL certificates for API calls.
	 *
	 * @since    1.0.0
	 * @param    array  $args    HTTP request arguments.
	 * @param    string $url     Request URL.
	 * @return   array           Modified arguments.
	 */
	public static function ensure_ssl_verification( $args, $url ) {
		if ( false !== strpos( $url, 'api.freemius.com' ) ) {
			$args['sslverify'] = true;
		}

		return $args;
	}

	/**
	 * Show Freemius missing notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function freemius_missing_notice() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Smart Cycle Discounts', 'smart-cycle-discounts' ); ?>:</strong>
				<?php esc_html_e( 'Freemius SDK is not installed. Please download and install it to enable Pro features and licensing.', 'smart-cycle-discounts' ); ?>
				<a href="https://github.com/Freemius/wordpress-sdk/archive/refs/heads/master.zip" target="_blank">
					<?php esc_html_e( 'Download Freemius SDK', 'smart-cycle-discounts' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle plugin uninstall via Freemius.
	 *
	 * This method is called by Freemius during the uninstall process.
	 * It delegates to WSSCD_Uninstaller if available, or performs basic cleanup.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function handle_uninstall() {
		// Try to load and run the uninstaller if available.
		if ( class_exists( 'WSSCD_Uninstaller' ) ) {
			WSSCD_Uninstaller::uninstall();
			return;
		}

		// Fallback: Try to include the uninstaller file directly.
		$uninstaller_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/class-uninstaller.php';
		if ( file_exists( $uninstaller_file ) ) {
			require_once $uninstaller_file;
			if ( class_exists( 'WSSCD_Uninstaller' ) ) {
				WSSCD_Uninstaller::uninstall();
				return;
			}
		}

		// Last resort: Basic cleanup if uninstaller class is not available.
		global $wpdb;

		delete_option( 'wsscd_settings' );
		delete_option( 'wsscd_version' );
		delete_option( 'wsscd_db_version' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient cleanup during uninstall; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_wsscd_%'
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient cleanup during uninstall; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_wsscd_%'
			)
		);
	}

	/**
	 * Check if user has premium access.
	 *
	 * Handles localhost development licenses which Freemius marks as "free"
	 * even when linked to paid plans. This override ensures proper premium
	 * detection in all environments.
	 *
	 * @since    1.0.0
	 * @return   bool    True if premium or trial.
	 */
	public static function is_premium() {
		if ( ! self::$freemius || ! is_object( self::$freemius ) ) {
			return false;
		}

		if ( self::$freemius->is_premium() || self::$freemius->is_trial() ) {
			return true;
		}

		// Handle localhost licenses with paid plans.
		// Freemius provides free localhost licenses for development that aren't
		// flagged as "premium" even when linked to a paid plan. We check manually.
		if ( self::$freemius->is_registered() ) {
			$license = self::$freemius->_get_license();

			if ( $license && $license->is_active() ) {
				if ( isset( $license->is_free_localhost ) && $license->is_free_localhost ) {
					// Verify paid plan assignment via plan_id and pricing_id.
					if ( isset( $license->plan_id ) && ! empty( $license->plan_id ) &&
						isset( $license->pricing_id ) && ! empty( $license->pricing_id ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get Freemius instance.
	 *
	 * @since    1.0.0
	 * @return   Freemius|null    Freemius instance or null.
	 */
	public static function get_instance() {
		return self::$freemius;
	}
}
