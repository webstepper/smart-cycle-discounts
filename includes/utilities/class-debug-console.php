<?php
/**
 * Debug Console Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-debug-console.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Debug Console
 *
 * Provides a browser-based debug console for real-time debugging
 * and inspection of plugin operations during development.
 *
 * PRODUCTION READY: Code execution (eval) has been disabled for WordPress.org compliance.
 * The console still provides:
 * - Log file viewer (read debug logs)
 * - Variable inspector (predefined system info, wizard state, campaigns)
 * - System information (PHP version, memory usage, query count)
 *
 * Activation Requirements:
 * - Must define WP_DEBUG as true
 * - Must define WSSCD_DEBUG_CONSOLE as true
 * - User must have 'manage_options' capability
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */
class WSSCD_Debug_Console {

	/**
	 * Debug logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Debug_Logger    $logger    Debug logger.
	 */
	private WSSCD_Debug_Logger $logger;

	/**
	 * Debug mode flag.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $debug_mode    Debug mode enabled.
	 */
	private bool $debug_mode;

	/**
	 * Console output buffer.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $console_buffer    Console messages buffer.
	 */
	private array $console_buffer = array();

	/**
	 * Initialize the debug console.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG &&
							defined( 'WSSCD_DEBUG_CONSOLE' ) && WSSCD_DEBUG_CONSOLE;

		if ( $this->debug_mode ) {
			$this->logger = new WSSCD_Debug_Logger();
			$this->init_hooks();
		}
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function init_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_console_assets' ) );
		add_action( 'wp_ajax_wsscd_debug_console', array( $this, 'handle_console_ajax' ) );
		add_action( 'admin_footer', array( $this, 'render_console_html' ) );
		add_action( 'wp_footer', array( $this, 'render_console_html' ) );
	}

	/**
	 * Enqueue console assets.
	 *
	 * @since    1.0.0
	 * @param    string $hook_suffix    Current page hook.
	 * @return   void
	 */
	public function enqueue_console_assets( string $hook_suffix ): void {
		if ( ! $this->should_show_console() ) {
			return;
		}

		wp_enqueue_script(
			'wsscd-debug-console',
			WSSCD_PLUGIN_URL . 'resources/assets/js/utilities/debug-console.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_enqueue_style(
			'wsscd-debug-console',
			WSSCD_PLUGIN_URL . 'resources/assets/css/admin/debug-console.css',
			array(),
			'1.0.0'
		);

		wp_localize_script(
			'wsscd-debug-console',
			'WSSCD_Debug_Console',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wsscd_debug_console' ),
				'enabled'  => $this->debug_mode,
				'buffer'   => $this->console_buffer,
			)
		);
	}

	/**
	 * Handle console AJAX requests.
	 *
	 * NOTE: This handler intentionally bypasses the unified AJAX router because:
	 * 1. Development-only debugging tool (not production feature)
	 * 2. Direct, low-level operations (execute code, get logs, inspect variables)
	 * 3. Performance-critical for debug operations
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_console_ajax(): void {
		// Validate and sanitize nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is being extracted for verification on next line.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wsscd_debug_console' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$action = isset( $_POST['console_action'] ) ? sanitize_text_field( wp_unslash( $_POST['console_action'] ) ) : '';

		switch ( $action ) {
			case 'execute_code':
				$this->execute_debug_code();
				break;
			case 'get_logs':
				$this->get_debug_logs();
				break;
			case 'clear_logs':
				$this->clear_debug_logs();
				break;
			case 'inspect_variable':
				$this->inspect_variable();
				break;
			default:
				wp_send_json_error( 'Invalid console action' );
		}
	}

	/**
	 * Execute debug code safely.
	 *
	 * SECURITY NOTE: Code execution via eval() has been disabled for production release
	 * to comply with WordPress.org plugin guidelines and security best practices.
	 *
	 * This functionality can be re-enabled in local development environments by uncommenting
	 * the eval() block below, but should NEVER be enabled in production.
	 *
	 * Alternative: Use the Inspector tab to view predefined variables and system info,
	 * or use the Logs tab to view debug logs.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function execute_debug_code(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_console_ajax().
		$code = isset( $_POST['code'] ) ? sanitize_textarea_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( empty( $code ) ) {
			wp_send_json_error( 'No code provided' );
		}

		$result = array(
			'executed' => false,
			'output'   => '',
			'error'    => __( 'Code execution is disabled for security compliance. Use the Inspector tab to view system information.', 'smart-cycle-discounts' ),
		);

		wp_send_json_success( $result );
	}

	/**
	 * Get debug logs.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function get_debug_logs(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_console_ajax().
		$lines = isset( $_POST['lines'] ) ? absint( wp_unslash( $_POST['lines'] ) ) : 50;
		$lines = max( 10, min( 500, $lines ) ); // Limit between 10-500 lines

		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/smart-cycle-discounts/logs/debug.log';

		if ( ! file_exists( $log_file ) ) {
			wp_send_json_success( array( 'logs' => 'No debug logs found' ) );
		}

		$logs = $this->tail_file( $log_file, $lines );

		wp_send_json_success(
			array(
				'logs'      => $logs,
				'file_size' => filesize( $log_file ),
				'modified'  => filemtime( $log_file ),
			)
		);
	}

	/**
	 * Clear debug logs.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function clear_debug_logs(): void {
		global $wp_filesystem;

		// Initialize WP_Filesystem if needed.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/smart-cycle-discounts/logs/debug.log';

		if ( $wp_filesystem && $wp_filesystem->exists( $log_file ) ) {
			$wp_filesystem->put_contents( $log_file, '', FS_CHMOD_FILE );
			wp_send_json_success( array( 'message' => 'Debug logs cleared' ) );
		} else {
			wp_send_json_error( 'Log file not found' );
		}
	}

	/**
	 * Inspect variable or object.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function inspect_variable(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_console_ajax().
		$var_name = isset( $_POST['variable'] ) ? sanitize_text_field( wp_unslash( $_POST['variable'] ) ) : '';

		$result = array();

		switch ( $var_name ) {
			case 'session_data':
				$result = $_SESSION ?? array();
				break;
			case 'wizard_state':
				$result = get_transient( 'wsscd_wizard_state_' . get_current_user_id() );
				break;
			case 'active_campaigns':
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Debug console query; table identifier prepared with %i.
				$result = $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL LIMIT 10', $wpdb->prefix . 'wsscd_campaigns' )
				);
				break;
			case 'debug_info':
				$result = $this->get_system_debug_info();
				break;
			default:
				$result = array( 'error' => 'Unknown variable: ' . $var_name );
		}

		wp_send_json_success( array( 'data' => $result ) );
	}

	/**
	 * Get system debug information.
	 *
	 * @since    1.0.0
	 * @return   array    Debug information.
	 */
	private function get_system_debug_info(): array {
		return array(
			'php_version'    => PHP_VERSION,
			'wp_version'     => get_bloginfo( 'version' ),
			'memory_limit'   => ini_get( 'memory_limit' ),
			'memory_usage'   => memory_get_usage( true ),
			'peak_memory'    => memory_get_peak_usage( true ),
			'db_queries'     => get_num_queries(),
			'plugins_loaded' => count( get_option( 'active_plugins', array() ) ),
			'user_id'        => get_current_user_id(),
			'current_screen' => get_current_screen()->id ?? 'unknown',
			'debug_mode'     => $this->debug_mode,
		);
	}

	/**
	 * Render console HTML.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_console_html(): void {
		if ( ! $this->should_show_console() ) {
			return;
		}
		?>
		<div id="wsscd-debug-console" class="wsscd-debug-console">
			<div class="wsscd-debug-console-header">
				<h3>Smart Cycle Discounts Debug Console</h3>
				<div class="wsscd-debug-console-controls">
					<button id="wsscd-debug-toggle" class="button">Show/Hide</button>
					<button id="wsscd-debug-clear" class="button">Clear</button>
					<button id="wsscd-debug-refresh" class="button">Refresh Logs</button>
				</div>
			</div>
			<div class="wsscd-debug-console-content">
				<div class="wsscd-debug-tabs">
					<button class="wsscd-debug-tab active" data-tab="logs">Logs</button>
					<button class="wsscd-debug-tab" data-tab="console">Console</button>
					<button class="wsscd-debug-tab" data-tab="inspector">Inspector</button>
				</div>
				
				<div class="wsscd-debug-panel" id="wsscd-debug-logs">
					<div class="wsscd-debug-log-controls">
						<select id="wsscd-debug-log-lines">
							<option value="50">Last 50 lines</option>
							<option value="100">Last 100 lines</option>
							<option value="200">Last 200 lines</option>
						</select>
					</div>
					<pre id="wsscd-debug-log-output"></pre>
				</div>
				
				<div class="wsscd-debug-panel" id="wsscd-debug-console" style="display: none;">
					<div class="wsscd-debug-console-input">
						<textarea id="wsscd-debug-code-input" placeholder="Enter debug code... (e.g., wsscd_get_debug_info())"></textarea>
						<button id="wsscd-debug-execute" class="button button-primary">Execute</button>
					</div>
					<pre id="wsscd-debug-console-output"></pre>
				</div>
				
				<div class="wsscd-debug-panel" id="wsscd-debug-inspector" style="display: none;">
					<div class="wsscd-debug-inspector-controls">
						<select id="wsscd-debug-inspect-var">
							<option value="">Select variable to inspect</option>
							<option value="session_data">Session Data</option>
							<option value="wizard_state">Wizard State</option>
							<option value="active_campaigns">Active Campaigns</option>
							<option value="debug_info">System Info</option>
						</select>
						<button id="wsscd-debug-inspect" class="button">Inspect</button>
					</div>
					<pre id="wsscd-debug-inspector-output"></pre>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Check if console should be shown.
	 *
	 * @since    1.0.0
	 * @return   bool    True if should show.
	 */
	private function should_show_console(): bool {
		return $this->debug_mode &&
				current_user_can( 'manage_options' ) &&
				( is_admin() || ( defined( 'WSSCD_DEBUG_FRONTEND' ) && WSSCD_DEBUG_FRONTEND ) );
	}

	/**
	 * Get last N lines from file.
	 *
	 * @since    1.0.0
	 * @param    string $file     File path.
	 * @param    int    $lines    Number of lines.
	 * @return   string              File contents.
	 */
	private function tail_file( string $file, int $lines ): string {
		global $wp_filesystem;

		// Initialize WP_Filesystem if needed.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $file ) ) {
			return '';
		}

		$contents = $wp_filesystem->get_contents( $file );
		if ( false === $contents ) {
			return '';
		}

		$all_lines   = explode( "\n", $contents );
		$total_lines = count( $all_lines );

		// Get last N lines.
		$start_line  = max( 0, $total_lines - $lines );
		$line_buffer = array_slice( $all_lines, $start_line );

		// Trim each line.
		$line_buffer = array_map( 'rtrim', $line_buffer );

		return implode( "\n", $line_buffer );
	}

	/**
	 * Add message to console buffer.
	 *
	 * @since    1.0.0
	 * @param    string $message    Console message.
	 * @param    string $type       Message type.
	 * @return   void
	 */
	public function add_console_message( string $message, string $type = 'info' ): void {
		if ( ! $this->debug_mode ) {
			return;
		}

		$this->console_buffer[] = array(
			'message'   => $message,
			'type'      => $type,
			'timestamp' => microtime( true ),
		);
	}

	/**
	 * Log debug message to console and file.
	 *
	 * @since    1.0.0
	 * @param    string $message    Debug message.
	 * @param    array  $context    Additional context.
	 * @return   void
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->debug_mode ) {
			return;
		}

		$this->logger->debug( $message, $context );
		$this->add_console_message( $message, 'debug' );
	}

	/**
	 * Log info message to console and file.
	 *
	 * @since    1.0.0
	 * @param    string $message    Info message.
	 * @param    array  $context    Additional context.
	 * @return   void
	 */
	public function info( string $message, array $context = array() ): void {
		if ( ! $this->debug_mode ) {
			return;
		}

		$this->logger->info( $message, $context );
		$this->add_console_message( $message, 'info' );
	}

	/**
	 * Log warning to console and file.
	 *
	 * @since    1.0.0
	 * @param    string $message    Warning message.
	 * @param    array  $context    Additional context.
	 * @return   void
	 */
	public function warning( string $message, array $context = array() ): void {
		if ( ! $this->debug_mode ) {
			return;
		}

		$this->logger->warning( $message, $context );
		$this->add_console_message( $message, 'warning' );
	}

	/**
	 * Log error to console and file.
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @param    array  $context    Additional context.
	 * @return   void
	 */
	public function error( string $message, array $context = array() ): void {
		if ( ! $this->debug_mode ) {
			return;
		}

		$this->logger->error( $message, $context );
		$this->add_console_message( $message, 'error' );
	}
}