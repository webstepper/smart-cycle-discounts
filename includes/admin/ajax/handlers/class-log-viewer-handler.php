<?php
/**
 * Log Viewer Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-log-viewer-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Log Viewer Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */
class SCD_Log_Viewer_Handler {

	/**
	 * Logger instance.
	 *
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		if ( null === $logger ) {
			$logger = SCD_Logger::with_context( 'ajax' );
		}
		$this->logger = $logger;
	}

	/**
	 * Handle log viewer request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( $request = array() ) {
		// Note: Security is handled by AJAX router
		// This is a legacy handler that returns data directly (not wrapped in success/data)
		$start_time = microtime( true );

		$action = isset( $request['log_action'] ) ? sanitize_text_field( $request['log_action'] ) : '';

		// Log request start
		$this->logger->flow(
			'info',
			'AJAX START',
			'Processing log viewer request',
			array(
				'log_action' => $action,
				'user_id'    => get_current_user_id(),
			)
		);

		switch ( $action ) {
			case 'view':
				return $this->handle_view_logs( $request, $start_time );

			case 'clear':
				return $this->handle_clear_logs( $request, $start_time );

			case 'download':
				return $this->handle_download_logs( $request, $start_time );

			case 'stats':
				return $this->handle_get_stats( $start_time );

			case 'system_report':
				return $this->handle_system_report( $start_time );

			default:
				// Log invalid action
				$this->logger->flow(
					'error',
					'AJAX ERROR',
					'Invalid log action',
					array(
						'log_action'  => $action,
						'_start_time' => $start_time,
					)
				);

				return array(
					'success' => false,
					'message' => __( 'Invalid action', 'smart-cycle-discounts' ),
				);
		}
	}

	/**
	 * Handle view logs request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request      Request data.
	 * @param    float $start_time   Request start time.
	 * @return   array                  Response data.
	 */
	private function handle_view_logs( array $request, float $start_time ): array {
		require_once SCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new SCD_Log_Manager();

		$lines = isset( $request['lines'] ) ? absint( $request['lines'] ) : 100;

		$logs = $log_manager->get_logs( $lines, true );

		if ( is_wp_error( $logs ) ) {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Failed to view logs',
				array(
					'error'           => $logs->get_error_message(),
					'lines_requested' => $lines,
					'_start_time'     => $start_time,
				)
			);

			return array(
				'success' => false,
				'message' => $logs->get_error_message(),
			);
		}

		// Log successful retrieval
		$this->logger->flow(
			'info',
			'AJAX SUCCESS',
			'Logs retrieved successfully',
			array(
				'lines_requested' => $lines,
				'log_size'        => strlen( $logs ),
				'_start_time'     => $start_time,
			)
		);

		return array(
			'logs'  => $logs,
			'lines' => $lines,
		);
	}

	/**
	 * Handle clear logs request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request      Request data.
	 * @param    float $start_time   Request start time.
	 * @return   array                  Response data.
	 */
	private function handle_clear_logs( array $request, float $start_time ): array {
		require_once SCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new SCD_Log_Manager();

		$result = $log_manager->clear_logs();

		if ( is_wp_error( $result ) ) {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Failed to clear logs',
				array(
					'error'       => $result->get_error_message(),
					'_start_time' => $start_time,
				)
			);

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Log successful clear
		$this->logger->flow(
			'notice',
			'CACHE CLEAR',
			'Logs cleared successfully',
			array(
				'user_id'     => get_current_user_id(),
				'_start_time' => $start_time,
			)
		);

		return array(
			'message' => __( 'Log cleared successfully', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Handle download logs request.
	 *
	 * This triggers a file download, so it doesn't return JSON.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request      Request data.
	 * @param    float $start_time   Request start time.
	 * @return   array                  Response data (or exits with download).
	 */
	private function handle_download_logs( array $request, float $start_time ): array {
		require_once SCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new SCD_Log_Manager();

		// Log download request
		$this->logger->flow(
			'info',
			'AJAX SUCCESS',
			'Log download initiated',
			array(
				'user_id'     => get_current_user_id(),
				'_start_time' => $start_time,
			)
		);

		// This will exit after sending the file
		$log_manager->download_log( true );

		// This code is never reached due to exit in download_log
		return array(
			'success' => true,
		);
	}

	/**
	 * Handle get stats request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $start_time   Request start time.
	 * @return   array                  Response data.
	 */
	private function handle_get_stats( float $start_time ): array {
		require_once SCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new SCD_Log_Manager();

		$stats = $log_manager->get_log_stats();

		// Log stats retrieval
		$this->logger->flow(
			'debug',
			'AJAX SUCCESS',
			'Log stats retrieved',
			array(
				'log_exists'  => $stats['exists'] ?? false,
				'log_size'    => $stats['size_formatted'] ?? 'N/A',
				'_start_time' => $start_time,
			)
		);

		return array(
			'stats' => $stats,
		);
	}

	/**
	 * Handle system report request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $start_time   Request start time.
	 * @return   array                  Response data.
	 */
	private function handle_system_report( float $start_time ): array {
		require_once SCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new SCD_Log_Manager();

		$report = $log_manager->generate_system_report();

		// Log report generation
		$this->logger->flow(
			'info',
			'AJAX SUCCESS',
			'System report generated',
			array(
				'report_size'     => strlen( $report ),
				'user_id'         => get_current_user_id(),
				'_start_time'     => $start_time,
				'_include_memory' => true,
			)
		);

		return array(
			'report' => $report,
		);
	}
}
