<?php
/**
 * Import Data AJAX Handler
 *
 * Handles importing campaigns and settings from JSON files
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import Handler Class
 *
 * @since 1.0.0
 */
class SCD_Import_Handler extends SCD_Abstract_Ajax_Handler {

	use SCD_License_Validation_Trait;

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param object     $container Container instance.
	 * @param SCD_Logger $logger    Logger instance.
	 */
	public function __construct( $container, $logger ) {
		parent::__construct( $logger );
		$this->container = $container;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		return 'scd_ajax';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		$start_time = microtime( true );

		// Check license (critical tier - import is sensitive operation)
		$license_check = $this->validate_license( 'critical' );
		if ( $this->license_validation_failed( $license_check ) ) {
			return $this->license_error_response( $license_check );
		}

		// Get import data
		$import_data = isset( $request['import_data'] ) ? $request['import_data'] : '';

		// Log request start
		$this->logger->flow( 'info', 'AJAX START', 'Processing import request', array(
			'data_size' => strlen( $import_data ),
			'user_id' => get_current_user_id()
		) );

		if ( empty( $import_data ) ) {
			$this->logger->flow( 'error', 'AJAX ERROR', 'No import data provided', array(
				'_start_time' => $start_time
			) );
			return $this->error( __( 'No import data provided', 'smart-cycle-discounts' ) );
		}

		// Decode JSON
		$data = json_decode( $import_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->flow( 'error', 'AJAX ERROR', 'Invalid JSON data', array(
				'json_error' => json_last_error_msg(),
				'_start_time' => $start_time
			) );
			return $this->error( __( 'Invalid JSON data', 'smart-cycle-discounts' ) );
		}

		// Validate data structure
		if ( ! isset( $data['type'] ) || ! isset( $data['data'] ) ) {
			$this->logger->flow( 'error', 'AJAX ERROR', 'Invalid import file format', array(
				'has_type' => isset( $data['type'] ),
				'has_data' => isset( $data['data'] ),
				'_start_time' => $start_time
			) );
			return $this->error( __( 'Invalid import file format', 'smart-cycle-discounts' ) );
		}

		// Route to appropriate import handler
		if ( 'campaigns' === $data['type'] ) {
			return $this->import_campaigns( $data['data'], $start_time );
		} elseif ( 'settings' === $data['type'] ) {
			return $this->import_settings( $data['data'], $start_time );
		} else {
			$this->logger->flow( 'error', 'AJAX ERROR', 'Unknown import type', array(
				'import_type' => $data['type'],
				'_start_time' => $start_time
			) );
			return $this->error( __( 'Unknown import type', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Import campaigns.
	 *
	 * @param array $campaigns  Campaigns data.
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function import_campaigns( $campaigns, $start_time ) {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$imported = 0;
		$failed = 0;
		$errors = array();

		foreach ( $campaigns as $campaign ) {
			// Remove ID to create new campaigns
			unset( $campaign['id'] );

			// Insert campaign
			$result = $wpdb->insert( $campaigns_table, $campaign );

			if ( $result ) {
				$imported++;
			} else {
				$failed++;
				// Only store first 3 errors to avoid bloating logs
				if ( count( $errors ) < 3 ) {
					$errors[] = $wpdb->last_error;
				}
			}
		}

		// Log import results
		if ( $failed > 0 ) {
			$this->logger->flow( 'warning', 'CAMPAIGN CREATE', 'Campaigns imported with errors', array(
				'total' => count( $campaigns ),
				'imported' => $imported,
				'failed' => $failed,
				'sample_errors' => $errors,
				'user_id' => get_current_user_id(),
				'_start_time' => $start_time,
				'_include_memory' => true
			) );
		} else {
			$this->logger->flow( 'notice', 'CAMPAIGN CREATE', 'Campaigns imported successfully', array(
				'total' => count( $campaigns ),
				'imported' => $imported,
				'user_id' => get_current_user_id(),
				'_start_time' => $start_time,
				'_include_memory' => true
			) );
		}

		return $this->success( array(
			'message' => sprintf(
				/* translators: %d: number of campaigns imported */
				_n( '%d campaign imported successfully', '%d campaigns imported successfully', $imported, 'smart-cycle-discounts' ),
				$imported
			)
		) );
	}

	/**
	 * Import settings.
	 *
	 * @param array $settings   Settings data.
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function import_settings( $settings, $start_time ) {
		// Get current settings
		$current_settings = get_option( 'scd_settings', array() );

		// Merge with imported settings
		$merged_settings = array_replace_recursive( $current_settings, $settings );

		// Update settings
		$result = update_option( 'scd_settings', $merged_settings );

		// Log import results
		if ( $result ) {
			$this->logger->flow( 'notice', 'AJAX SUCCESS', 'Settings imported successfully', array(
				'settings_count' => count( $settings ),
				'merged_count' => count( $merged_settings ),
				'user_id' => get_current_user_id(),
				'_start_time' => $start_time,
				'_include_memory' => true
			) );
		} else {
			$this->logger->flow( 'warning', 'AJAX SUCCESS', 'Settings import completed (no changes)', array(
				'settings_count' => count( $settings ),
				'user_id' => get_current_user_id(),
				'_start_time' => $start_time
			) );
		}

		return $this->success( array(
			'message' => __( 'Settings imported successfully', 'smart-cycle-discounts' )
		) );
	}
}
