<?php
/**
 * Debug Console Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-debug-console.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Debug Console
 *
 * Provides a browser-based debug console for real-time debugging
 * and inspection of plugin operations during development.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */
class SCD_Debug_Console {

	/**
	 * Debug logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Debug_Logger    $logger    Debug logger.
	 */
	private SCD_Debug_Logger $logger;

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
							defined( 'SCD_DEBUG_CONSOLE' ) && SCD_DEBUG_CONSOLE;

		if ( $this->debug_mode ) {
			$this->logger = new SCD_Debug_Logger();
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
		add_action( 'wp_ajax_scd_debug_console', array( $this, 'handle_console_ajax' ) );
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
			'scd-debug-console',
			plugins_url( 'assets/js/utilities/debug-console.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_enqueue_style(
			'scd-debug-console',
			plugins_url( 'assets/css/admin/debug-console.css', dirname( __DIR__ ) ),
			array(),
			'1.0.0'
		);

		wp_localize_script(
			'scd-debug-console',
			'SCD_Debug_Console',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'scd_debug_console' ),
				'enabled'  => $this->debug_mode,
				'buffer'   => $this->console_buffer,
			)
		);
	}

	/**
	 * Handle console AJAX requests.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_console_ajax(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'scd_debug_console' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$action = sanitize_text_field( $_POST['console_action'] ?? '' );

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
	 * @since    1.0.0
	 * @return   void
	 */
	private function execute_debug_code(): void {
		$code = sanitize_textarea_field( $_POST['code'] ?? '' );

		if ( empty( $code ) ) {
			wp_send_json_error( 'No code provided' );
		}

		// Security: Only allow safe debug functions
		$allowed_functions = array(
			'scd_get_campaign',
			'scd_get_debug_info',
			'scd_test_validation',
			'scd_run_validation_tests',
			'scd_get_validation_rules',
			'scd_inspect_state',
			'var_dump',
			'print_r',
			'get_option',
			'current_user_can',
		);

		$result = array(
			'executed' => false,
			'output'   => '',
			'error'    => '',
		);

		try {
			// Start output buffering
			ob_start();

			// Very basic security check
			foreach ( $allowed_functions as $func ) {
				if ( strpos( $code, $func ) === 0 ) {
					$result['executed'] = true;
					eval( $code );
					break;
				}
			}

			if ( ! $result['executed'] ) {
				$result['error'] = 'Function not allowed for security reasons';
			} else {
				$result['output'] = ob_get_contents();
			}
		} catch ( \Throwable $e ) {
			$result['error'] = $e->getMessage();
		} finally {
			ob_end_clean();
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get debug logs.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function get_debug_logs(): void {
		$lines = (int) ( $_POST['lines'] ?? 50 );
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
		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/smart-cycle-discounts/logs/debug.log';

		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
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
		$var_name = sanitize_text_field( $_POST['variable'] ?? '' );

		$result = array();

		switch ( $var_name ) {
			case 'session_data':
				$result = $_SESSION ?? array();
				break;
			case 'wizard_state':
				$result = get_transient( 'scd_wizard_state_' . get_current_user_id() );
				break;
			case 'active_campaigns':
				global $wpdb;
				$result = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}scd_campaigns WHERE deleted_at IS NULL LIMIT 10"
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
		<div id="scd-debug-console" class="scd-debug-console">
			<div class="scd-debug-console-header">
				<h3>SCD Debug Console</h3>
				<div class="scd-debug-console-controls">
					<button id="scd-debug-toggle" class="button">Show/Hide</button>
					<button id="scd-debug-clear" class="button">Clear</button>
					<button id="scd-debug-refresh" class="button">Refresh Logs</button>
				</div>
			</div>
			<div class="scd-debug-console-content">
				<div class="scd-debug-tabs">
					<button class="scd-debug-tab active" data-tab="logs">Logs</button>
					<button class="scd-debug-tab" data-tab="console">Console</button>
					<button class="scd-debug-tab" data-tab="inspector">Inspector</button>
				</div>
				
				<div class="scd-debug-panel" id="scd-debug-logs">
					<div class="scd-debug-log-controls">
						<select id="scd-debug-log-lines">
							<option value="50">Last 50 lines</option>
							<option value="100">Last 100 lines</option>
							<option value="200">Last 200 lines</option>
						</select>
					</div>
					<pre id="scd-debug-log-output"></pre>
				</div>
				
				<div class="scd-debug-panel" id="scd-debug-console" style="display: none;">
					<div class="scd-debug-console-input">
						<textarea id="scd-debug-code-input" placeholder="Enter debug code... (e.g., scd_get_debug_info())"></textarea>
						<button id="scd-debug-execute" class="button button-primary">Execute</button>
					</div>
					<pre id="scd-debug-console-output"></pre>
				</div>
				
				<div class="scd-debug-panel" id="scd-debug-inspector" style="display: none;">
					<div class="scd-debug-inspector-controls">
						<select id="scd-debug-inspect-var">
							<option value="">Select variable to inspect</option>
							<option value="session_data">Session Data</option>
							<option value="wizard_state">Wizard State</option>
							<option value="active_campaigns">Active Campaigns</option>
							<option value="debug_info">System Info</option>
						</select>
						<button id="scd-debug-inspect" class="button">Inspect</button>
					</div>
					<pre id="scd-debug-inspector-output"></pre>
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
				( is_admin() || ( defined( 'SCD_DEBUG_FRONTEND' ) && SCD_DEBUG_FRONTEND ) );
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
		if ( ! file_exists( $file ) ) {
			return '';
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return '';
		}

		$line_buffer = array();
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$line_buffer[] = rtrim( $line );
			if ( count( $line_buffer ) > $lines ) {
				array_shift( $line_buffer );
			}
		}

		fclose( $handle );
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