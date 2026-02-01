<?php
/**
 * Plugin Name: Smart Cycle Discounts – Automated Sale Campaigns for WooCommerce
 * Plugin URI: https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * Description: Schedule WooCommerce discounts that run themselves. BOGO, tiered pricing, flash sales - campaigns activate automatically.
 * Version: 1.5.1
 * Author: Webstepper
 * Author URI: https://webstepper.io
 * Text Domain: smart-cycle-discounts
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 * Woo: 8.0.0:9.5.0
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: woocommerce
 *
 * @fs_premium_only /includes/core/discounts/strategies/class-tiered-strategy.php, /includes/core/discounts/strategies/class-bogo-strategy.php, /includes/core/discounts/strategies/class-spend-threshold-strategy.php, /includes/core/analytics/class-export-service.php, /includes/core/analytics/class-report-generator.php, /includes/admin/pages/class-analytics-dashboard.php, /includes/admin/licensing/class-license-manager.php, /templates/emails/campaign-ending.php, /templates/emails/daily-report.php, /templates/emails/weekly-report.php, /templates/emails/performance-alert.php, /templates/emails/low-stock-alert.php, /templates/emails/milestone-alert.php, /resources/assets/js/steps/discounts/tiered-discount.js, /resources/assets/js/steps/discounts/bogo-discount.js, /resources/assets/js/steps/discounts/spend-threshold.js, /resources/assets/js/analytics/analytics-dashboard.js
 *
 * @package SmartCycleDiscounts
 * @version 1.5.1
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access denied.' );
}

/**
 * Plugin SSL verification configuration.
 *
 * This prefixed constant controls whether SSL verification is enabled
 * for external API communications. Set to true for production security.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WSSCD_FREEMIUS_SSL_VERIFY' ) ) {
	define( 'WSSCD_FREEMIUS_SSL_VERIFY', true );
}

/**
 * Freemius SDK SSL Configuration.
 *
 * WORDPRESS.ORG REVIEWER NOTE:
 * ============================================================================
 * FS_SDK__SSLVERIFY is a REQUIRED constant name by the third-party Freemius SDK.
 * The SDK checks for this EXACT constant name internally - it cannot be renamed.
 * We define our own prefixed constant (WSSCD_FREEMIUS_SSL_VERIFY) above and pass
 * its value to the SDK's required constant. This is the standard integration
 * pattern documented by Freemius for all WordPress plugins using their SDK.
 *
 * Without this constant, Freemius defaults to SSL verification DISABLED (false),
 * which is a security risk. We explicitly enable it (true) for secure API calls.
 *
 * @see https://freemius.com/help/documentation/wordpress-sdk/
 * @see https://github.com/Freemius/wordpress-sdk
 * @see vendor/freemius/includes/sdk/FreemiusWordPress.php line 65-66
 * ============================================================================
 */
if ( ! defined( 'FS_SDK__SSLVERIFY' ) ) {
	define( 'FS_SDK__SSLVERIFY', WSSCD_FREEMIUS_SSL_VERIFY );
}

/**
 * Freemius SDK Auto-Deactivation Mechanism
 *
 * This structure ensures the free version auto-deactivates when
 * the premium version is activated. Required by Freemius SDK.
 *
 * @see https://freemius.com/help/documentation/selling-with-freemius/freemius-wordpress-sdk-integration-guide/
 */
if ( function_exists( 'wsscd_fs' ) ) {
	// Premium version is already active - register this version's basename
	// so Freemius can properly handle the free/premium version switching.
	wsscd_fs()->set_basename( true, __FILE__ );
} else {
	/**
	 * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
	 * `function_exists` CALL ABOVE TO PROPERLY WORK.
	 *
	 * Create a helper function for easy SDK access.
	 */
	if ( ! function_exists( 'wsscd_fs' ) ) {
		/**
		 * Get Freemius SDK instance.
		 *
		 * Initialization is handled by WSSCD_Freemius_Integration class
		 * to ensure all hooks and configurations are properly registered.
		 *
		 * @since 1.0.0
		 * @return Freemius|null Freemius instance or null if not available.
		 */
		function wsscd_fs() {
			global $wsscd_fs;
			return $wsscd_fs;
		}
	}
}

// Plugin constants
define( 'WSSCD_VERSION', '1.5.1' );
define( 'WSSCD_DB_VERSION', '1.0.0' );
define( 'WSSCD_MIN_PHP_VERSION', '7.4' );
define( 'WSSCD_MIN_WP_VERSION', '6.4' );
define( 'WSSCD_MIN_WC_VERSION', '8.0' );
define( 'WSSCD_PLUGIN_FILE', __FILE__ );

// Handle plugin paths safely
if ( function_exists( 'plugin_dir_path' ) ) {
    define( 'WSSCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
} else {
    // Fallback for CLI/testing
    define( 'WSSCD_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
}

if ( function_exists( 'plugin_dir_url' ) ) {
    define( 'WSSCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
} else {
    // Fallback for CLI/testing
    define( 'WSSCD_PLUGIN_URL', '' );
}

if ( function_exists( 'plugin_basename' ) ) {
    define( 'WSSCD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
} else {
    // Fallback for CLI/testing
    define( 'WSSCD_PLUGIN_BASENAME', 'smart-cycle-discounts/smart-cycle-discounts.php' );
}

define( 'WSSCD_INCLUDES_DIR', WSSCD_PLUGIN_DIR . 'includes/' );
define( 'WSSCD_TEMPLATES_DIR', WSSCD_PLUGIN_DIR . 'templates/' );
define( 'WSSCD_VIEWS_DIR', WSSCD_PLUGIN_DIR . 'resources/views/' );
define( 'WSSCD_ASSETS_URL', WSSCD_PLUGIN_URL . 'resources/assets/' );
define( 'WSSCD_LANGUAGES_DIR', WSSCD_PLUGIN_DIR . 'languages/' );
define( 'WSSCD_TEXT_DOMAIN', 'smart-cycle-discounts' );
define( 'WSSCD_TRANSIENT_PREFIX', 'wsscd_' );

// Debug constants with granular control
// WSSCD_DEBUG controls verbose debug logging - OFF by default for production
// Users can enable via: define( 'WSSCD_DEBUG', true ); in wp-config.php
// Or via Admin Settings > Advanced > Debug Mode toggle (auto-expires in 24h)
if ( ! defined( 'WSSCD_DEBUG' ) ) {
	define( 'WSSCD_DEBUG', false );
}

// Development mode for asset loading (unminified assets)
define( 'WSSCD_DEV_MODE', WSSCD_DEBUG && defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

/**
 * Logging level control
 * Options: 'none', 'error', 'warning', 'notice', 'info', 'debug'
 * Default: 'warning' - captures errors and warnings (useful for support)
 * Users can adjust via Admin Settings > Advanced > Logging Level
 */
if ( ! defined( 'WSSCD_LOG_LEVEL' ) ) {
	define( 'WSSCD_LOG_LEVEL', WSSCD_DEBUG ? 'debug' : 'warning' );
}

/**
 * Control whether to write logs to WordPress debug.log via error_log()
 * Default: false (only write to plugin's own log files)
 * Set to true in wp-config.php to enable: define( 'WSSCD_LOG_TO_DEBUG_LOG', true );
 */
if ( ! defined( 'WSSCD_LOG_TO_DEBUG_LOG' ) ) {
	define( 'WSSCD_LOG_TO_DEBUG_LOG', false );
}

/**
 * Maximum log file size in bytes before rotation
 * Default: 10MB for regular logs, 50MB for debug logs
 */
if ( ! defined( 'WSSCD_LOG_MAX_SIZE' ) ) {
	define( 'WSSCD_LOG_MAX_SIZE', 10485760 ); // 10MB
}

/**
 * Maximum age of log files in days before auto-cleanup
 * Set to 0 to disable auto-cleanup
 */
if ( ! defined( 'WSSCD_LOG_MAX_AGE_DAYS' ) ) {
	define( 'WSSCD_LOG_MAX_AGE_DAYS', 7 );
}

// Debug console constants (enable by adding these to wp-config.php)
if ( ! defined( 'WSSCD_DEBUG_CONSOLE' ) ) {
	define( 'WSSCD_DEBUG_CONSOLE', false );
}
if ( ! defined( 'WSSCD_DEBUG_FRONTEND' ) ) {
	define( 'WSSCD_DEBUG_FRONTEND', false );
}

/**
 * ========================================
 * LOGGING SYSTEM CONFIGURATION GUIDE
 * ========================================
 *
 * The plugin uses a granular logging system with configurable levels and outputs.
 * All settings can be overridden in wp-config.php before this plugin loads.
 *
 * DEFAULT BEHAVIOR (no configuration needed):
 * - Log level: 'warning' - captures errors and warnings for support
 * - Debug mode: OFF - no verbose logging
 * - Logs written to: wp-content/uploads/smart-cycle-discounts/logs/plugin.log
 *
 * AVAILABLE CONSTANTS (add to wp-config.php before 'wp-settings.php'):
 *
 * 1. WSSCD_DEBUG (bool)
 *    - Controls verbose debug logging (detailed traces, AJAX requests, etc.)
 *    - Default: false (production-safe)
 *    - Can also enable via Admin Settings > Advanced > Debug Mode (24h auto-expire)
 *    - Example: define( 'WSSCD_DEBUG', true );
 *
 * 2. WSSCD_LOG_LEVEL (string)
 *    - Controls minimum logging level
 *    - Options: 'none', 'error', 'warning', 'notice', 'info', 'debug'
 *    - Default: 'warning' (or 'debug' when WSSCD_DEBUG is true)
 *    - Can also set via Admin Settings > Advanced > Logging Level
 *    - Example: define( 'WSSCD_LOG_LEVEL', 'error' );
 *
 * 3. WSSCD_LOG_TO_DEBUG_LOG (bool)
 *    - Write logs to WordPress debug.log via error_log()
 *    - Default: false (only writes to plugin's own log files)
 *    - Example: define( 'WSSCD_LOG_TO_DEBUG_LOG', true );
 *
 * 4. WSSCD_LOG_MAX_SIZE (int)
 *    - Maximum log file size in bytes before rotation
 *    - Default: 10485760 (10MB for regular logs, 50MB for debug logs)
 *    - Example: define( 'WSSCD_LOG_MAX_SIZE', 5242880 ); // 5MB
 *
 * 5. WSSCD_LOG_MAX_AGE_DAYS (int)
 *    - Maximum age of rotated log files before deletion
 *    - Default: 7 days
 *    - Set to 0 to disable age-based cleanup
 *    - Example: define( 'WSSCD_LOG_MAX_AGE_DAYS', 3 );
 *
 * LOG LEVELS EXPLAINED:
 *
 * - none:      No logging at all
 * - error:     Only critical errors (recommended for production)
 * - warning:   Errors + warnings about potential issues
 * - notice:    Warnings + notices about normal but significant events
 * - info:      Notices + informational messages
 * - debug:     Everything including detailed debug information (verbose)
 *
 * LOG FILE LOCATIONS:
 *
 * - Regular logs:  wp-content/uploads/smart-cycle-discounts/logs/plugin.log
 * - Debug logs:    wp-content/uploads/smart-cycle-discounts/logs/debug.log
 * - Rotated logs:  Same directory with .YYYYMMDD-HHMMSS suffix
 *
 * RECOMMENDED CONFIGURATIONS:
 *
 * Production (default - no changes needed):
 *     // Uses default 'warning' level - logs errors and warnings only
 *
 * Production (minimal logging):
 *     define( 'WSSCD_LOG_LEVEL', 'error' );  // Only critical errors
 *
 * Development (full logging):
 *     define( 'WSSCD_DEBUG', true );
 *     define( 'WSSCD_LOG_TO_DEBUG_LOG', true );  // Also write to WP debug.log
 *
 * Troubleshooting user issues:
 *     // Enable via Admin Settings > Advanced > Debug Mode
 *     // Auto-expires after 24 hours for security
 *
 * ========================================
 */

/**
 * Declare WooCommerce HPOS compatibility.
 * This must be called before WooCommerce initializes.
 */
add_action( 'before_woocommerce_init', function() {
	// Only declare compatibility if the class is available
	// This prevents triggering autoloaders too early
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil', false ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
}, 999 ); // Lower priority to run later

/**
 * Register custom cron schedules early.
 * This MUST run before the activator tries to use them.
 *
 * Note: Display strings are not translated here because the cron_schedules filter
 * runs before 'init', which is when translations are loaded (WordPress 6.7+).
 * These display strings are only visible in developer/debug contexts.
 */
add_filter( 'cron_schedules', function( $schedules ) {
	// Add 15-minute schedule for campaign safety checks.
	if ( ! isset( $schedules['every_fifteen_minutes'] ) ) {
		$schedules['every_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Every 15 Minutes',
		);
	}

	// Add twice daily schedule.
	if ( ! isset( $schedules['twice_daily'] ) ) {
		$schedules['twice_daily'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => 'Twice Daily',
		);
	}

	// Add weekly schedule.
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => 'Once Weekly',
		);
	}

	return $schedules;
}, 10 );

// Initialize Freemius SDK (after constants are defined, before other files load)
if ( ! class_exists( 'WSSCD_Freemius_Integration' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'admin/licensing/class-freemius-integration.php';
}
WSSCD_Freemius_Integration::init();

// Load requirements checker
require_once WSSCD_INCLUDES_DIR . 'utilities/class-requirements-checker.php';

// Load debug functions if debugging is enabled
if ( WSSCD_DEBUG ) {
	require_once WSSCD_INCLUDES_DIR . 'utilities/wsscd-debug-functions.php';
}

// Load debug console functions if debug console is enabled
if ( defined( 'WSSCD_DEBUG_CONSOLE' ) && WSSCD_DEBUG_CONSOLE ) {
	require_once WSSCD_INCLUDES_DIR . 'utilities/wsscd-debug-console-functions.php';
}

/**
 * Check if WooCommerce is active and meets minimum requirements.
 */
function wsscd_check_requirements() {
	// Ensure the class is loaded
	if ( ! class_exists( 'WSSCD_Requirements_Checker' ) ) {
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-requirements-checker.php';
	}
	
	$checker = new WSSCD_Requirements_Checker();
	return $checker->check_requirements();
}

// Note: load_plugin_textdomain() is not needed for WordPress.org hosted plugins.
// WordPress automatically loads translations for plugins with slug 'smart-cycle-discounts' since WP 4.6.
// See: https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/

/**
 * Early AJAX handler function for immediate hook registration.
 */
function wsscd_handle_ajax_request() {

	// Verify this is our AJAX action
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Initial action check before routing to handler that verifies nonce.
	if ( ! isset( $_REQUEST['action'] ) || 'wsscd_ajax' !== $_REQUEST['action'] ) {
		wp_send_json_error( array(
			'message'         => 'Invalid action',
			'received_action' => isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'none',
		) );
		return;
	}

	// Ensure WooCommerce is loaded for product-related AJAX requests
	// Only try to load if we're dealing with product-related actions
	$product_actions = array( 'product_search', 'get_product_stats', 'get_products_by_ids' );
	$wsscd_action      = isset( $_REQUEST['wsscd_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wsscd_action'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	
	if ( in_array( $wsscd_action, $product_actions, true ) ) {
		if ( ! function_exists( 'WC' ) && defined( 'WC_PLUGIN_FILE' ) && file_exists( WC_PLUGIN_FILE ) ) {
			include_once WC_PLUGIN_FILE;
		}
	}
	
	// Load and use the router directly
	require_once WSSCD_INCLUDES_DIR . 'admin/ajax/class-ajax-router.php';
	
	if ( class_exists( 'WSSCD_Ajax_Router' ) ) {
		$router = new WSSCD_Ajax_Router();
		$router->route_request();
	} else {
		wp_send_json_error( array( 'message' => 'AJAX router unavailable' ) );
	}
}

// Register AJAX hooks immediately after the handler function is defined
// This ensures they're available when WordPress processes AJAX requests
// SECURITY: Only authenticated users can access AJAX endpoints
// Non-privileged hook removed per WordPress.org security requirements
add_action( 'wp_ajax_wsscd_ajax', 'wsscd_handle_ajax_request' );

/**
 * Initialize the plugin.
 */
function wsscd_init_plugin() {
	// Check requirements first (skip in test mode)
	if ( ! defined( 'WSSCD_TESTING' ) || ! WSSCD_TESTING ) {
		if ( ! wsscd_check_requirements() ) {
			return;
		}
	}

	// Load core files
	require_once WSSCD_INCLUDES_DIR . 'class-smart-cycle-discounts.php';

	// Initialize the main plugin class using singleton pattern
	$GLOBALS['wsscd_plugin'] = Smart_Cycle_Discounts::get_instance();
	$GLOBALS['wsscd_plugin']->run();
}
add_action( 'plugins_loaded', 'wsscd_init_plugin', 10 );

/**
 * Handle activation redirect to dashboard.
 *
 * Redirects to plugin dashboard after every activation.
 * Skips redirect during bulk activations.
 *
 * @since 1.0.0
 */
function wsscd_activation_redirect() {
	// Only proceed if transient exists.
	if ( ! get_transient( 'wsscd_activation_redirect' ) ) {
		return;
	}

	// Delete transient to prevent multiple redirects.
	delete_transient( 'wsscd_activation_redirect' );

	// Don't redirect if already on plugin page.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking current page, no form submission.
	if ( isset( $_GET['page'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'smart-cycle-discounts' ) ) {
		return;
	}

	// Don't redirect during network activation.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core WordPress parameter during activation, no nonce available.
	if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
		return;
	}

	// Redirect to plugin dashboard.
	wp_safe_redirect( admin_url( 'admin.php?page=smart-cycle-discounts' ) );
	exit;
}
add_action( 'admin_init', 'wsscd_activation_redirect' );

/**
 * CRITICAL: Ensure cart calculation hook is registered for WooCommerce Blocks (REST API).
 * This must run after WooCommerce is loaded and the plugin is initialized.
 */
function wsscd_register_blocks_cart_hooks() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$plugin = wsscd_get_instance();
	if ( ! $plugin ) {
		return;
	}

	$container = $plugin->get_container();
	if ( ! $container || ! $container->has( 'woocommerce_integration' ) ) {
		return;
	}

	$wc_integration = $container->get( 'woocommerce_integration' );
	if ( ! $wc_integration ) {
		return;
	}

	// Classic cart hook for page load
	add_action( 'woocommerce_before_calculate_totals', array( $wc_integration, 'modify_cart_item_prices' ), 10, 1 );
}
add_action( 'woocommerce_loaded', 'wsscd_register_blocks_cart_hooks', 20 );

/**
 * Register REST API hooks for WooCommerce Blocks.
 * This must run during rest_api_init to work for Store API requests.
 */
function wsscd_register_rest_api_hooks() {
	// CRITICAL: For WooCommerce Blocks REST API cart updates, hook into product price directly
	// This ensures discounts apply when quantity changes dynamically
	add_filter( 'woocommerce_product_get_price', 'wsscd_blocks_dynamic_price', 999, 2 );
	add_filter( 'woocommerce_product_variation_get_price', 'wsscd_blocks_dynamic_price', 999, 2 );
}
add_action( 'rest_api_init', 'wsscd_register_rest_api_hooks' );

/**
 * Apply dynamic pricing for WooCommerce Blocks cart REST API updates.
 * This ensures tiered discounts work when quantity changes dynamically.
 *
 * @param float      $price   Product price.
 * @param WC_Product $product Product object.
 * @return float Modified price.
 */
function wsscd_blocks_dynamic_price( $price, $product ) {
	// Only apply in cart context during REST API requests
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return $price;
	}

	// Only for cart endpoints
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( false === strpos( $request_uri, '/wc/store' ) ) {
		return $price;
	}

	$plugin = wsscd_get_instance();
	if ( ! $plugin ) {
		return $price;
	}

	$container = $plugin->get_container();
	if ( ! $container || ! $container->has( 'woocommerce_integration' ) ) {
		return $price;
	}

	$wc_integration = $container->get( 'woocommerce_integration' );
	if ( ! $wc_integration ) {
		return $price;
	}

	// Get cart to check quantity
	$cart = WC()->cart;
	if ( ! $cart ) {
		return $price;
	}

	// Find this product in cart to get current quantity
	$product_id = $product->get_id();
	$parent_id = $product->get_parent_id();
	$quantity = 0;

	foreach ( $cart->get_cart() as $cart_item ) {
		$item_product_id = $cart_item['product_id'];
		$item_variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;

		if ( $item_product_id === $product_id || $item_product_id === $parent_id || $item_variation_id === $product_id ) {
			$quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;
			break;
		}
	}

	if ( $quantity <= 0 ) {
		return $price;
	}

	// Build context with quantity
	$context = array(
		'quantity' => $quantity,
		'product' => $product,
		'product_id' => $product_id
	);

	// Get discount info
	$discount_info = $wc_integration->get_discount_info( $product_id, $context );

	if ( $discount_info && isset( $discount_info['discounted_price'] ) ) {
		$discounted_price = (float) $discount_info['discounted_price'];

		// Only apply if it's actually a discount
		if ( $discounted_price < $price && $discounted_price > 0 ) {
			return $discounted_price;
		}
	}

	return $price;
}

/**
 * Get the main plugin instance.
 *
 * @return Smart_Cycle_Discounts|null
 */
function wsscd_get_instance() {
	return isset( $GLOBALS['wsscd_plugin'] ) ? $GLOBALS['wsscd_plugin'] : null;
}

/**
 * Get the dependency injection container.
 *
 * Shorthand helper for accessing the DI container.
 *
 * @since 1.0.0
 * @return WSSCD_Container|null Container instance or null if not initialized.
 */
function wsscd() {
	$instance = wsscd_get_instance();
	if ( $instance && method_exists( $instance, 'get_container' ) ) {
		return $instance->get_container();
	}
	return null;
}

/**
 * Check if user has premium access.
 *
 * Centralized check that uses Feature Gate service for consistent PRO access logic.
 * This ensures development overrides and license checks work properly.
 *
 * @since    1.0.0
 * @return   bool    True if premium or trial.
 */
function wsscd_is_premium() {
	try {
		$container = Smart_Cycle_Discounts::get_instance();
		if ( $container ) {
			$feature_gate = Smart_Cycle_Discounts::get_service( 'feature_gate' );
			if ( $feature_gate ) {
				return $feature_gate->is_premium();
			}
		}
	} catch ( Exception $e ) {
		// Fallback to direct Freemius check if Feature Gate unavailable
		return function_exists( 'wsscd_fs' ) && wsscd_fs() && ( wsscd_fs()->is_premium() || wsscd_fs()->is_trial() );
	}

	// Default to free
	return false;
}

/**
 * Get Freemius upgrade URL.
 *
 * @since    1.0.0
 * @return   string    Upgrade URL.
 */
function wsscd_get_upgrade_url() {
	if ( function_exists( 'wsscd_fs' ) && wsscd_fs() ) {
		return wsscd_fs()->get_upgrade_url();
	}
	return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
}

/**
 * Plugin activation hook.
 */
function wsscd_activate_plugin() {
	// Load translation handler for activation messages
	require_once WSSCD_INCLUDES_DIR . 'utilities/class-translation-handler.php';

	// Check requirements during activation (skip in test mode)
	if ( ! defined( 'WSSCD_TESTING' ) || ! WSSCD_TESTING ) {
		if ( ! wsscd_check_requirements() ) {
			$messages = WSSCD_Translation_Handler::get_requirements_messages();
			wp_die(
				esc_html( $messages['activation_error'] ),
				esc_html( $messages['activation_error_title'] ),
				array( 'back_link' => true )
			);
		}
	}

	// Load activator class
	require_once WSSCD_INCLUDES_DIR . 'class-activator.php';
	WSSCD_Activator::activate();
}
register_activation_hook( __FILE__, 'wsscd_activate_plugin' );
register_deactivation_hook( __FILE__, 'wsscd_deactivate_plugin' );

/**
 * Plugin deactivation hook.
 */
function wsscd_deactivate_plugin() {
	// Clear scheduled cron events
	wp_clear_scheduled_hook( 'wsscd_cleanup_expired_sessions' );
	wp_clear_scheduled_hook( 'wsscd_cleanup_old_analytics' );
	wp_clear_scheduled_hook( 'wsscd_check_campaign_schedules' );
	wp_clear_scheduled_hook( 'wsscd_cleanup_expired_campaigns' );
	wp_clear_scheduled_hook( 'wsscd_purge_old_trash' );
	wp_clear_scheduled_hook( 'wsscd_license_health_check' );
	require_once WSSCD_INCLUDES_DIR . 'class-deactivator.php';
	WSSCD_Deactivator::deactivate();
}

/**
 * Add plugin action links.
 */
function wsscd_plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wsscd-dashboard' ) . '">' . esc_html__( 'Dashboard', 'smart-cycle-discounts' ) . '</a>',
		'<a href="' . admin_url( 'admin.php?page=smart-cycle-discounts' ) . '">' . esc_html__( 'Campaigns', 'smart-cycle-discounts' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wsscd_plugin_action_links' );

/**
 * Add plugin meta links.
 */
function wsscd_plugin_meta_links( $links, $file ) {
	if ( plugin_basename( __FILE__ ) === $file ) {
		// Load translation handler if not already loaded
		if ( ! class_exists( 'WSSCD_Translation_Handler' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'utilities/class-translation-handler.php';
		}
		
		$translations = WSSCD_Translation_Handler::get_meta_links();
		$meta_links = array(
			'<a href="https://webstepper.io/wordpress/plugins/smart-cycle-discounts/docs/" target="_blank">' . esc_html( $translations['documentation'] ) . '</a>',
			'<a href="https://webstepper.io/wordpress/plugins/smart-cycle-discounts/support/" target="_blank">' . esc_html( $translations['support'] ) . '</a>',
		);
		
		return array_merge( $links, $meta_links );
	}
	
	return $links;
}
add_filter( 'plugin_row_meta', 'wsscd_plugin_meta_links', 10, 2 );

/**
 * Load analytics test runners in admin (for development/debugging).
 * Access via: /wp-admin/admin.php?wsscd_run_analytics_test=1 (basic)
 * Access via: /wp-admin/admin.php?wsscd_run_comprehensive_analytics_test=1 (comprehensive)
 * Access via: /wp-admin/admin.php?wsscd_insert_demo_data=1 (insert demo data)
 */
if ( is_admin() ) {
	if ( file_exists( WSSCD_PLUGIN_DIR . 'tests/test-analytics-functionality.php' ) ) {
		require_once WSSCD_PLUGIN_DIR . 'tests/test-analytics-functionality.php';
	}
	if ( file_exists( WSSCD_PLUGIN_DIR . 'tests/test-analytics-comprehensive.php' ) ) {
		require_once WSSCD_PLUGIN_DIR . 'tests/test-analytics-comprehensive.php';
	}
	if ( file_exists( WSSCD_PLUGIN_DIR . 'tests/insert-demo-data.php' ) ) {
		require_once WSSCD_PLUGIN_DIR . 'tests/insert-demo-data.php';
	}
}
