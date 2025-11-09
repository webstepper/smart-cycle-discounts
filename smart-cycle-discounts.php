<?php
/**
 * Plugin Name: Smart Cycle Discounts
 * Plugin URI: https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * Description: Revolutionary WooCommerce discount management with intelligent product rotation, multi-campaign organization, and advanced scheduling. Built with modern WordPress & WooCommerce standards.
 * Version: 1.0.0
 * Author: Webstepper
 * Author URI: https://webstepper.io
 * Text Domain: smart-cycle-discounts
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to: 6.7
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 * Woo: 8.0.0:9.5.0
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Network: false
 * Update URI: https://webstepper.io/wordpress-plugins/smart-cycle-discounts/updates/
 * Requires Plugins: woocommerce
 * 
 * @package SmartCycleDiscounts
 * @version 1.0.0
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access denied.' );
}

// Security: Enable SSL certificate verification for Freemius API
if ( ! defined( 'FS_SDK__SSLVERIFY' ) ) {
	define( 'FS_SDK__SSLVERIFY', true );
}

// Create a helper function for easy SDK access.
if ( ! function_exists( 'scd_fs' ) ) {
	/**
	 * Get Freemius SDK instance.
	 *
	 * Initialization is handled by SCD_Freemius_Integration class
	 * to ensure all hooks and configurations are properly registered.
	 *
	 * @since 1.0.0
	 * @return Freemius|null Freemius instance or null if not available.
	 */
	function scd_fs() {
		global $scd_fs;
		return $scd_fs;
	}
}

// Plugin constants
define( 'SCD_VERSION', '1.0.0' );
define( 'SCD_DB_VERSION', '1.0.0' );
define( 'SCD_MIN_PHP_VERSION', '8.0' );
define( 'SCD_MIN_WP_VERSION', '6.4' );
define( 'SCD_MIN_WC_VERSION', '8.0' );
define( 'SCD_PLUGIN_FILE', __FILE__ );

// Handle plugin paths safely
if ( function_exists( 'plugin_dir_path' ) ) {
    define( 'SCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
} else {
    // Fallback for CLI/testing
    define( 'SCD_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
}

if ( function_exists( 'plugin_dir_url' ) ) {
    define( 'SCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
} else {
    // Fallback for CLI/testing
    define( 'SCD_PLUGIN_URL', '' );
}

if ( function_exists( 'plugin_basename' ) ) {
    define( 'SCD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
} else {
    // Fallback for CLI/testing
    define( 'SCD_PLUGIN_BASENAME', 'smart-cycle-discounts/smart-cycle-discounts.php' );
}

define( 'SCD_INCLUDES_DIR', SCD_PLUGIN_DIR . 'includes/' );
define( 'SCD_TEMPLATES_DIR', SCD_PLUGIN_DIR . 'templates/' );
define( 'SCD_VIEWS_DIR', SCD_PLUGIN_DIR . 'resources/views/' );
define( 'SCD_ASSETS_URL', SCD_PLUGIN_URL . 'resources/assets/' );
define( 'SCD_LANGUAGES_DIR', SCD_PLUGIN_DIR . 'languages/' );
define( 'SCD_TEXT_DOMAIN', 'smart-cycle-discounts' );
define( 'SCD_TRANSIENT_PREFIX', 'scd_' );

// Debug constants with granular control
// Users can override SCD_DEBUG in wp-config.php to control plugin debugging independently
if ( ! defined( 'SCD_DEBUG' ) ) {
	define( 'SCD_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

// Development mode for asset loading (unminified assets)
define( 'SCD_DEV_MODE', SCD_DEBUG && defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

/**
 * Logging level control
 * Options: 'none', 'error', 'warning', 'notice', 'info', 'debug'
 * Default: 'debug' when SCD_DEBUG is true, 'error' otherwise
 */
if ( ! defined( 'SCD_LOG_LEVEL' ) ) {
	define( 'SCD_LOG_LEVEL', SCD_DEBUG ? 'debug' : 'error' );
}

/**
 * Control whether to write logs to WordPress debug.log via error_log()
 * Default: false (only write to plugin's own log files)
 * Set to true in wp-config.php to enable: define( 'SCD_LOG_TO_DEBUG_LOG', true );
 */
if ( ! defined( 'SCD_LOG_TO_DEBUG_LOG' ) ) {
	define( 'SCD_LOG_TO_DEBUG_LOG', false );
}

/**
 * Maximum log file size in bytes before rotation
 * Default: 10MB for regular logs, 50MB for debug logs
 */
if ( ! defined( 'SCD_LOG_MAX_SIZE' ) ) {
	define( 'SCD_LOG_MAX_SIZE', 10485760 ); // 10MB
}

/**
 * Maximum age of log files in days before auto-cleanup
 * Set to 0 to disable auto-cleanup
 */
if ( ! defined( 'SCD_LOG_MAX_AGE_DAYS' ) ) {
	define( 'SCD_LOG_MAX_AGE_DAYS', 7 );
}

// Debug console constants (enable by adding these to wp-config.php)
if ( ! defined( 'SCD_DEBUG_CONSOLE' ) ) {
	define( 'SCD_DEBUG_CONSOLE', false );
}
if ( ! defined( 'SCD_DEBUG_FRONTEND' ) ) {
	define( 'SCD_DEBUG_FRONTEND', false );
}

/**
 * ========================================
 * LOGGING SYSTEM CONFIGURATION GUIDE
 * ========================================
 *
 * The plugin uses a granular logging system with configurable levels and outputs.
 * All settings can be overridden in wp-config.php before this plugin loads.
 *
 * QUICK START - REDUCE LOG VOLUME:
 * Add to wp-config.php (before require_once ABSPATH . 'wp-settings.php'):
 *
 *     define( 'SCD_LOG_LEVEL', 'error' );        // Only log errors
 *     define( 'SCD_LOG_TO_DEBUG_LOG', false );   // Don't write to WordPress debug.log
 *
 * AVAILABLE CONSTANTS:
 *
 * 1. SCD_DEBUG (bool)
 *    - Controls plugin debugging features
 *    - Default: true when WP_DEBUG is true
 *    - Example: define( 'SCD_DEBUG', false );
 *
 * 2. SCD_LOG_LEVEL (string)
 *    - Controls minimum logging level
 *    - Options: 'none', 'error', 'warning', 'notice', 'info', 'debug'
 *    - Default: 'debug' when SCD_DEBUG is true, 'error' otherwise
 *    - Example: define( 'SCD_LOG_LEVEL', 'warning' );
 *
 * 3. SCD_LOG_TO_DEBUG_LOG (bool)
 *    - Write logs to WordPress debug.log via error_log()
 *    - Default: false (only writes to plugin's own log files)
 *    - Example: define( 'SCD_LOG_TO_DEBUG_LOG', true );
 *
 * 4. SCD_LOG_MAX_SIZE (int)
 *    - Maximum log file size in bytes before rotation
 *    - Default: 10485760 (10MB for regular logs, 50MB for debug logs)
 *    - Example: define( 'SCD_LOG_MAX_SIZE', 5242880 ); // 5MB
 *
 * 5. SCD_LOG_MAX_AGE_DAYS (int)
 *    - Maximum age of rotated log files before deletion
 *    - Default: 7 days
 *    - Set to 0 to disable age-based cleanup
 *    - Example: define( 'SCD_LOG_MAX_AGE_DAYS', 3 );
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
 * Production (minimal logging):
 *     define( 'SCD_LOG_LEVEL', 'error' );
 *     define( 'SCD_LOG_TO_DEBUG_LOG', false );
 *
 * Staging (moderate logging):
 *     define( 'SCD_LOG_LEVEL', 'warning' );
 *     define( 'SCD_LOG_TO_DEBUG_LOG', false );
 *
 * Development (full logging):
 *     define( 'SCD_DEBUG', true );
 *     define( 'SCD_LOG_LEVEL', 'debug' );
 *     define( 'SCD_LOG_TO_DEBUG_LOG', true );
 *
 * Troubleshooting specific issues:
 *     define( 'SCD_LOG_LEVEL', 'info' );
 *     define( 'SCD_LOG_MAX_AGE_DAYS', 1 ); // Clean up quickly
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
 */
add_filter( 'cron_schedules', function( $schedules ) {
	// Add 15-minute schedule for campaign safety checks
	if ( ! isset( $schedules['every_fifteen_minutes'] ) ) {
		$schedules['every_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'smart-cycle-discounts' ),
		);
	}

	// Add twice daily schedule
	if ( ! isset( $schedules['twice_daily'] ) ) {
		$schedules['twice_daily'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily', 'smart-cycle-discounts' ),
		);
	}

	// Add weekly schedule
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'smart-cycle-discounts' ),
		);
	}

	return $schedules;
}, 10 );

// Initialize Freemius SDK (after constants are defined, before other files load)
if ( ! class_exists( 'SCD_Freemius_Integration' ) ) {
	require_once SCD_INCLUDES_DIR . 'admin/licensing/class-freemius-integration.php';
}
SCD_Freemius_Integration::init();

// Load requirements checker
require_once SCD_INCLUDES_DIR . 'utilities/class-requirements-checker.php';

// Load debug functions if debugging is enabled
if ( SCD_DEBUG ) {
	require_once SCD_INCLUDES_DIR . 'utilities/scd-debug-functions.php';
}

// Load debug console functions if debug console is enabled
if ( defined( 'SCD_DEBUG_CONSOLE' ) && SCD_DEBUG_CONSOLE ) {
	require_once SCD_INCLUDES_DIR . 'utilities/scd-debug-console-functions.php';
}

/**
 * Check if WooCommerce is active and meets minimum requirements.
 */
function scd_check_requirements() {
	// Ensure the class is loaded
	if ( ! class_exists( 'SCD_Requirements_Checker' ) ) {
		require_once SCD_INCLUDES_DIR . 'utilities/class-requirements-checker.php';
	}
	
	$checker = new SCD_Requirements_Checker();
	return $checker->check_requirements();
}

/**
 * Load plugin text domain for internationalization.
 */
function scd_load_textdomain() {
	load_plugin_textdomain(
		'smart-cycle-discounts',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}
add_action( 'plugins_loaded', 'scd_load_textdomain' );

/**
 * Early AJAX handler function for immediate hook registration.
 */
function scd_handle_ajax_request() {
	
	// Verify this is our AJAX action
	if ( ! isset( $_REQUEST['action'] ) || $_REQUEST['action'] !== 'scd_ajax' ) {
		wp_send_json_error( array( 
			'message' => 'Invalid action',
			'received_action' => isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'none'
		) );
		return;
	}
	
	// Ensure WooCommerce is loaded for product-related AJAX requests
	// Only try to load if we're dealing with product-related actions
	$product_actions = array( 'product_search', 'get_product_stats', 'get_products_by_ids' );
	$scd_action = isset( $_REQUEST['scd_action'] ) ? sanitize_text_field( $_REQUEST['scd_action'] ) : '';
	
	if ( in_array( $scd_action, $product_actions, true ) ) {
		if ( ! function_exists( 'WC' ) && defined( 'WC_PLUGIN_FILE' ) && file_exists( WC_PLUGIN_FILE ) ) {
			include_once WC_PLUGIN_FILE;
		}
	}
	
	// Load and use the router directly
	require_once SCD_INCLUDES_DIR . 'admin/ajax/class-ajax-router.php';
	
	if ( class_exists( 'SCD_Ajax_Router' ) ) {
		$router = new SCD_Ajax_Router();
		$router->route_request();
	} else {
		wp_send_json_error( array( 'message' => 'AJAX router unavailable' ) );
	}
}

// Register AJAX hooks immediately after the handler function is defined
// This ensures they're available when WordPress processes AJAX requests
// SECURITY: Only authenticated users can access AJAX endpoints
// Non-privileged hook removed per WordPress.org security requirements
add_action( 'wp_ajax_scd_ajax', 'scd_handle_ajax_request' );

/**
 * Initialize the plugin.
 */
function scd_init_plugin() {
	// Check requirements first
	if ( ! scd_check_requirements() ) {
		return;
	}

	// Load core files
	require_once SCD_INCLUDES_DIR . 'class-smart-cycle-discounts.php';

	// Initialize the main plugin class using singleton pattern
	$GLOBALS['smart_cycle_discounts'] = Smart_Cycle_Discounts::get_instance();
	$GLOBALS['smart_cycle_discounts']->run();
}
add_action( 'plugins_loaded', 'scd_init_plugin', 10 );

/**
 * Handle activation redirect to dashboard.
 *
 * Redirects to plugin dashboard after first-time activation.
 * Skips redirect during bulk activations and reactivations.
 *
 * @since 1.0.0
 */
function scd_activation_redirect() {
	// Only proceed if transient exists.
	if ( ! get_transient( 'scd_activation_redirect' ) ) {
		return;
	}

	// Delete transient to prevent multiple redirects.
	delete_transient( 'scd_activation_redirect' );

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
add_action( 'admin_init', 'scd_activation_redirect' );

/**
 * CRITICAL: Ensure cart calculation hook is registered for WooCommerce Blocks (REST API).
 * This must run after WooCommerce is loaded and the plugin is initialized.
 */
function scd_register_blocks_cart_hooks() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$plugin = scd_get_instance();
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
add_action( 'woocommerce_loaded', 'scd_register_blocks_cart_hooks', 20 );

/**
 * Register REST API hooks for WooCommerce Blocks.
 * This must run during rest_api_init to work for Store API requests.
 */
function scd_register_rest_api_hooks() {
	// CRITICAL: For WooCommerce Blocks REST API cart updates, hook into product price directly
	// This ensures discounts apply when quantity changes dynamically
	add_filter( 'woocommerce_product_get_price', 'scd_blocks_dynamic_price', 999, 2 );
	add_filter( 'woocommerce_product_variation_get_price', 'scd_blocks_dynamic_price', 999, 2 );
}
add_action( 'rest_api_init', 'scd_register_rest_api_hooks' );

/**
 * Apply dynamic pricing for WooCommerce Blocks cart REST API updates.
 * This ensures tiered discounts work when quantity changes dynamically.
 *
 * @param float      $price   Product price.
 * @param WC_Product $product Product object.
 * @return float Modified price.
 */
function scd_blocks_dynamic_price( $price, $product ) {
	// Only apply in cart context during REST API requests
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return $price;
	}

	// Only for cart endpoints
	if ( false === strpos( $_SERVER['REQUEST_URI'] ?? '', '/wc/store' ) ) {
		return $price;
	}

	$plugin = scd_get_instance();
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
function scd_get_instance() {
	return isset( $GLOBALS['smart_cycle_discounts'] ) ? $GLOBALS['smart_cycle_discounts'] : null;
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
function scd_is_premium() {
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
		return function_exists( 'scd_fs' ) && scd_fs() && ( scd_fs()->is_premium() || scd_fs()->is_trial() );
	}

	// Default to free
	return false;
}

/**
 * Check if user is on trial.
 *
 * @since    1.0.0
 * @return   bool    True if on trial.
 */
function scd_is_trial() {
	return function_exists( 'scd_fs' ) && scd_fs() && scd_fs()->is_trial();
}

/**
 * Get Freemius upgrade URL.
 *
 * @since    1.0.0
 * @return   string    Upgrade URL.
 */
function scd_get_upgrade_url() {
	if ( function_exists( 'scd_fs' ) && scd_fs() ) {
		return scd_fs()->get_upgrade_url();
	}
	return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
}

/**
 * Get Freemius trial URL.
 *
 * @since    1.0.0
 * @return   string    Trial URL.
 */
function scd_get_trial_url() {
	if ( function_exists( 'scd_fs' ) && scd_fs() ) {
		return scd_fs()->get_trial_url();
	}
	return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
}

/**
 * Plugin activation hook.
 */
function scd_activate_plugin() {
	// Load translation handler for activation messages
	require_once SCD_INCLUDES_DIR . 'utilities/class-translation-handler.php';

	// Check requirements during activation (skip in test mode)
	if ( ! defined( 'SCD_TESTING' ) || ! SCD_TESTING ) {
		if ( ! scd_check_requirements() ) {
			$messages = SCD_Translation_Handler::get_requirements_messages();
			wp_die(
				esc_html( $messages['activation_error'] ),
				esc_html( $messages['activation_error_title'] ),
				array( 'back_link' => true )
			);
		}
	}

	// Load activator class
	require_once SCD_INCLUDES_DIR . 'class-activator.php';
	SCD_Activator::activate();
}
register_activation_hook( __FILE__, 'scd_activate_plugin' );
register_deactivation_hook( __FILE__, 'scd_deactivate_plugin' );

/**
 * Plugin deactivation hook.
 */
function scd_deactivate_plugin() {
	// Clear scheduled cron events
	wp_clear_scheduled_hook( 'scd_cleanup_expired_sessions' );
	wp_clear_scheduled_hook( 'scd_cleanup_old_analytics' );
	wp_clear_scheduled_hook( 'scd_check_campaign_schedules' );
	wp_clear_scheduled_hook( 'scd_cleanup_expired_campaigns' );
	wp_clear_scheduled_hook( 'scd_purge_old_trash' );
	wp_clear_scheduled_hook( 'scd_license_health_check' );
	require_once SCD_INCLUDES_DIR . 'class-deactivator.php';
	SCD_Deactivator::deactivate();
}

/**
 * Add plugin action links.
 */
function scd_plugin_action_links( $links ) {
	// Load translation handler if not already loaded
	if ( ! class_exists( 'SCD_Translation_Handler' ) ) {
		require_once SCD_INCLUDES_DIR . 'utilities/class-translation-handler.php';
	}
	
	$translations = SCD_Translation_Handler::get_action_links();
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=scd-campaigns' ) . '">' . esc_html( $translations['campaigns'] ) . '</a>',
		'<a href="' . admin_url( 'admin.php?page=scd-analytics' ) . '">' . esc_html( $translations['analytics'] ) . '</a>',
		'<a href="' . admin_url( 'admin.php?page=scd-settings' ) . '">' . esc_html( $translations['settings'] ) . '</a>',
	);
	
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'scd_plugin_action_links' );

/**
 * Add plugin meta links.
 */
function scd_plugin_meta_links( $links, $file ) {
	if ( plugin_basename( __FILE__ ) === $file ) {
		// Load translation handler if not already loaded
		if ( ! class_exists( 'SCD_Translation_Handler' ) ) {
			require_once SCD_INCLUDES_DIR . 'utilities/class-translation-handler.php';
		}
		
		$translations = SCD_Translation_Handler::get_meta_links();
		$meta_links = array(
			'<a href="https://webstepper.io/wordpress-plugins/smart-cycle-discounts/docs/" target="_blank">' . esc_html( $translations['documentation'] ) . '</a>',
			'<a href="https://webstepper.io/wordpress-plugins/smart-cycle-discounts/support/" target="_blank">' . esc_html( $translations['support'] ) . '</a>',
		);
		
		return array_merge( $links, $meta_links );
	}
	
	return $links;
}
add_filter( 'plugin_row_meta', 'scd_plugin_meta_links', 10, 2 );
