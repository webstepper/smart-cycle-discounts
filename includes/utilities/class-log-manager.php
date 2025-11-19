<?php
/**
 * Log Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-log-manager.php
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
 * Log Manager Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Log_Manager {

	/**
	 * Log directory path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $log_dir    Log directory path.
	 */
	private string $log_dir;

	/**
	 * Sensitive patterns to sanitize.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $sensitive_patterns    Patterns to redact.
	 */
	private array $sensitive_patterns = array(
		'password'    => '/(["\']?password["\']?\s*[:=]\s*["\'])([^"\']+)(["\'])/i',
		'api_key'     => '/(["\']?api[_-]?key["\']?\s*[:=]\s*["\'])([^"\']+)(["\'])/i',
		'token'       => '/(["\']?token["\']?\s*[:=]\s*["\'])([^"\']+)(["\'])/i',
		'secret'      => '/(["\']?secret["\']?\s*[:=]\s*["\'])([^"\']+)(["\'])/i',
		'email'       => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
		'ip_address'  => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
		'credit_card' => '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
	);

	/**
	 * Initialize the log manager.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$upload_dir    = wp_upload_dir();
		$this->log_dir = $upload_dir['basedir'] . '/smart-cycle-discounts/logs';
	}

	/**
	 * Get log file contents.
	 *
	 * @since    1.0.0
	 * @param    int  $lines       Number of lines to retrieve (0 for all).
	 * @param    bool $sanitize    Whether to sanitize sensitive data.
	 * @return   string|WP_Error        Log contents or error.
	 */
	public function get_logs( int $lines = 100, bool $sanitize = true ) {
		$log_file = $this->get_log_file_path();

		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		if ( ! is_readable( $log_file ) ) {
			return new WP_Error(
				'log_read_error',
				__( 'Log file is not readable. Please check file permissions.', 'smart-cycle-discounts' )
			);
		}

		$contents = '';
		if ( 0 === $lines ) {
			// Read entire file
			$contents = file_get_contents( $log_file );
		} else {
			// Read last N lines
			$file_lines = file( $log_file );
			if ( false === $file_lines ) {
				return new WP_Error(
					'log_read_error',
					__( 'Failed to read log file.', 'smart-cycle-discounts' )
				);
			}
			$contents = implode( '', array_slice( $file_lines, -$lines ) );
		}

		if ( $sanitize ) {
			$contents = $this->sanitize_log_content( $contents );
		}

		return $contents;
	}

	/**
	 * Clear log file.
	 *
	 * @since    1.0.0
	 * @return   bool|WP_Error    True on success, WP_Error on failure.
	 */
	public function clear_logs() {
		$log_file = $this->get_log_file_path();

		if ( ! file_exists( $log_file ) ) {
			return true; // Already empty
		}

		if ( ! is_writable( $log_file ) ) {
			return new WP_Error(
				'log_clear_error',
				__( 'Cannot clear log. File is not writable.', 'smart-cycle-discounts' )
			);
		}

		// Truncate file instead of deleting (preserves permissions)
		if ( false === file_put_contents( $log_file, '' ) ) {
			return new WP_Error(
				'log_clear_error',
				__( 'Failed to clear log file.', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Get log statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Log statistics.
	 */
	public function get_log_stats(): array {
		$log_file = $this->get_log_file_path();

		$stats = array(
			'exists'         => file_exists( $log_file ),
			'size'           => file_exists( $log_file ) ? filesize( $log_file ) : 0,
			'size_formatted' => file_exists( $log_file ) ? size_format( filesize( $log_file ), 2 ) : '0 B',
			'last_modified'  => file_exists( $log_file ) ? filemtime( $log_file ) : 0,
			'modified_date'  => file_exists( $log_file ) ? date( 'Y-m-d H:i', filemtime( $log_file ) ) : 'N/A',
			'readable'       => file_exists( $log_file ) && is_readable( $log_file ),
			'writable'       => file_exists( $log_file ) && is_writable( $log_file ),
			'lines'          => 0,
		);

		// Count lines
		if ( $stats['readable'] ) {
			$line_count = 0;
			$handle     = fopen( $log_file, 'r' );
			if ( $handle ) {
				while ( ! feof( $handle ) ) {
					fgets( $handle );
					++$line_count;
				}
				fclose( $handle );
			}
			$stats['lines'] = $line_count;
		}

		$rotated_logs           = glob( $this->log_dir . '/*.log.*' );
		$stats['rotated_count'] = is_array( $rotated_logs ) ? count( $rotated_logs ) : 0;

		return $stats;
	}

	/**
	 * Download log file.
	 *
	 * @since    1.0.0
	 * @param    bool $sanitize    Whether to sanitize sensitive data.
	 * @return   void
	 */
	public function download_log( bool $sanitize = true ): void {
		$log_contents = $this->get_logs( 0, $sanitize );

		if ( is_wp_error( $log_contents ) ) {
			wp_die( esc_html( $log_contents->get_error_message() ) );
		}

		// Generate filename with timestamp
		$filename = 'scd-plugin-' . gmdate( 'Y-m-d-His' ) . '.log';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $log_contents ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $log_contents;
		exit;
	}

	/**
	 * Sanitize log content by removing/redacting sensitive data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $content    Log content to sanitize.
	 * @return   string                Sanitized content.
	 */
	private function sanitize_log_content( string $content ): string {
		// Replace sensitive patterns
		foreach ( $this->sensitive_patterns as $name => $pattern ) {
			if ( 'email' === $name ) {
				// Redact emails completely
				$content = preg_replace( $pattern, '[EMAIL_REDACTED]', $content );
			} elseif ( 'ip_address' === $name ) {
				// Partially redact IP addresses (keep first two octets)
				$content = preg_replace_callback(
					$pattern,
					function ( $matches ) {
						$parts = explode( '.', $matches[0] );
						return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
					},
					$content
				);
			} elseif ( 'credit_card' === $name ) {
				// Redact credit cards
				$content = preg_replace( $pattern, '[CARD_REDACTED]', $content );
			} else {
				// Redact value but keep structure
				$content = preg_replace( $pattern, '$1[REDACTED]$3', $content );
			}
		}

		// Redact file paths (keep relative paths only)
		$content = preg_replace( '/(' . preg_quote( ABSPATH, '/' ) . ')/', '[WP_ROOT]/', $content );

		return $content;
	}

	/**
	 * Get log file path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Log file path.
	 */
	private function get_log_file_path(): string {
		return $this->log_dir . '/plugin.log';
	}

	/**
	 * Generate system report.
	 *
	 * @since    1.0.0
	 * @return   string    System report content.
	 */
	public function generate_system_report(): string {
		$report = array();

		// Header
		$report[] = '========================================';
		$report[] = 'Smart Cycle Discounts - System Report';
		$report[] = '========================================';
		$report[] = '';
		$report[] = 'Generated: ' . current_time( 'Y-m-d H:i:s' );
		$report[] = '';

		// Plugin Info
		$report[] = '=== Plugin Information ===';
		$report[] = 'Version: ' . SCD_VERSION;
		$report[] = 'DB Version: ' . SCD_DB_VERSION;
		$report[] = '';

		// WordPress Environment
		$report[] = '=== WordPress Environment ===';
		$report[] = 'WP Version: ' . get_bloginfo( 'version' );
		$report[] = 'WP Debug: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled' );
		$report[] = 'WP Memory Limit: ' . WP_MEMORY_LIMIT;
		$report[] = 'WP Max Upload Size: ' . size_format( wp_max_upload_size() );
		$report[] = 'Site URL: ' . get_site_url();
		$report[] = 'Home URL: ' . get_home_url();
		$report[] = '';

		// Server Environment
		$report[] = '=== Server Environment ===';
		$report[] = 'PHP Version: ' . phpversion();
		$report[] = 'PHP Memory Limit: ' . ini_get( 'memory_limit' );
		$report[] = 'PHP Max Execution Time: ' . ini_get( 'max_execution_time' );
		$report[] = 'PHP Post Max Size: ' . ini_get( 'post_max_size' );
		$report[] = 'PHP Upload Max Filesize: ' . ini_get( 'upload_max_filesize' );
		$report[] = 'Server Software: ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' );
		$report[] = '';

		// Database
		global $wpdb;
		$report[] = '=== Database ===';
		$report[] = 'Database Type: MySQL';
		$report[] = 'Database Version: ' . $wpdb->db_version();
		$report[] = 'Database Prefix: ' . $wpdb->prefix;
		$report[] = 'Database Charset: ' . $wpdb->charset;
		$report[] = '';

		// WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			$report[] = '=== WooCommerce ===';
			$report[] = 'Version: ' . WC()->version;
			$report[] = 'HPOS Enabled: ' . ( wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? 'Yes' : 'No' );
			$report[] = 'Currency: ' . get_woocommerce_currency();
			$report[] = '';
		}

		// Plugin Settings
		$report[] = '=== Plugin Settings ===';
		$settings = get_option( 'scd_settings', array() );
		if ( isset( $settings['advanced'] ) ) {
			$report[] = 'Debug Mode: ' . ( isset( $settings['advanced']['enable_debug_mode'] ) && $settings['advanced']['enable_debug_mode'] ? 'Enabled' : 'Disabled' );
			$report[] = 'Log Level: ' . ( isset( $settings['advanced']['log_level'] ) ? $settings['advanced']['log_level'] : 'default' );
		}
		$report[] = '';

		// Log Files
		$report[]  = '=== Log Files ===';
		$log_stats = $this->get_log_stats();
		$report[]  = 'Plugin Log:';
		$report[]  = '  - Exists: ' . ( $log_stats['exists'] ? 'Yes' : 'No' );
		if ( $log_stats['exists'] ) {
			$report[] = '  - Size: ' . $log_stats['size_formatted'];
			$report[] = '  - Last Modified: ' . gmdate( 'Y-m-d H:i:s', $log_stats['last_modified'] );
			$report[] = '  - Lines: ' . $log_stats['lines'];
			$report[] = '  - Readable: ' . ( $log_stats['readable'] ? 'Yes' : 'No' );
			$report[] = '  - Writable: ' . ( $log_stats['writable'] ? 'Yes' : 'No' );
		}
		$report[] = 'Rotated Logs: ' . $log_stats['rotated_count'];
		$report[] = '';

		// Active Plugins
		$report[]       = '=== Active Plugins ===';
		$active_plugins = get_option( 'active_plugins', array() );
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$report[]    = $plugin_data['Name'] . ' - Version: ' . $plugin_data['Version'];
		}
		$report[] = '';

		$report[] = '========================================';

		return implode( "\n", $report );
	}
}
