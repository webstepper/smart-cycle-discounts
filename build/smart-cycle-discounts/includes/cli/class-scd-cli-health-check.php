<?php
/**
 * Scd Cli Health Check Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cli/class-scd-cli-health-check.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * CLI Health Check Command Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cli
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_CLI_Health_Check {

	/**
	 * Run service health check.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml, csv)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * [--report]
	 * : Generate detailed report
	 *
	 * [--fix]
	 * : Attempt to fix issues
	 *
	 * ## EXAMPLES
	 *
	 *     # Run basic health check
	 *     $ wp scd health-check
	 *
	 *     # Run with detailed report
	 *     $ wp scd health-check --report
	 *
	 *     # Output as JSON
	 *     $ wp scd health-check --format=json
	 *
	 *     # Attempt to fix issues
	 *     $ wp scd health-check --fix
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$format      = $assoc_args['format'] ?? 'table';
		$show_report = isset( $assoc_args['report'] );
		$fix_issues  = isset( $assoc_args['fix'] );

		WP_CLI::log( 'Running Smart Cycle Discounts service health check...' );

		try {
			// Get plugin instance
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();

			// Create health check instance
			require_once SCD_INCLUDES_DIR . 'utilities/class-service-health-check.php';

			// Get the service registry
			$registry = null;
			if ( class_exists( 'SCD_Service_Registry' ) ) {
				$registry = new SCD_Service_Registry( $container );
				$registry->register_all_services();
			} else {
				WP_CLI::warning( 'Service Registry not found. Using basic health check.' );
			}

			if ( $registry ) {
				$health_check = new SCD_Service_Health_Check( $container, $registry );
				$results      = $health_check->run_health_check();

				// Display results based on format
				switch ( $format ) {
					case 'json':
						WP_CLI::log( json_encode( $results, JSON_PRETTY_PRINT ) );
						break;

					case 'yaml':
						WP_CLI::log( yaml_emit( $results ) );
						break;

					case 'csv':
						$this->output_csv( $results );
						break;

					default:
						$this->output_table( $results );
						break;
				}

				// Show detailed report if requested
				if ( $show_report ) {
					WP_CLI::log( "\n" . $health_check->generate_report() );
				}

				// Attempt fixes if requested
				if ( $fix_issues && $results['status'] !== 'healthy' ) {
					WP_CLI::log( "\nAttempting to fix issues..." );
					$this->attempt_fixes( $results, $container );
				}

				// Exit with appropriate code
				if ( $results['status'] === 'unhealthy' ) {
					WP_CLI::error( 'Service health check failed!' );
				} elseif ( $results['status'] === 'degraded' ) {
					WP_CLI::warning( 'Service health check completed with warnings.' );
				} else {
					WP_CLI::success( 'Service health check passed!' );
				}
			} else {
				// Basic health check without registry
				$this->run_basic_health_check( $container );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( 'Health check failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Output results as table.
	 *
	 * @param array $results Health check results
	 */
	private function output_table( $results ) {
		// Summary table
		WP_CLI::log( "\nHealth Check Summary:" );
		WP_CLI::log( sprintf( 'Status: %s', strtoupper( $results['status'] ) ) );
		WP_CLI::log( sprintf( 'Timestamp: %s', $results['timestamp'] ) );

		// Check results table
		if ( ! empty( $results['checks'] ) ) {
			$table_data = array();
			foreach ( $results['checks'] as $check ) {
				$table_data[] = array(
					'Check'   => $check['name'],
					'Status'  => $check['status'],
					'Details' => $this->format_check_details( $check['details'] ?? array() ),
				);
			}

			WP_CLI\Utils\format_items( 'table', $table_data, array( 'Check', 'Status', 'Details' ) );
		}

		// Errors
		if ( ! empty( $results['errors'] ) ) {
			WP_CLI::log( "\nErrors:" );
			foreach ( $results['errors'] as $error ) {
				WP_CLI::log( '  • ' . $error );
			}
		}

		// Warnings
		if ( ! empty( $results['warnings'] ) ) {
			WP_CLI::log( "\nWarnings:" );
			foreach ( $results['warnings'] as $warning ) {
				WP_CLI::log( '  • ' . $warning );
			}
		}
	}

	/**
	 * Output results as CSV.
	 *
	 * @param array $results Health check results
	 */
	private function output_csv( $results ) {
		$output = fopen( 'php://output', 'w' );

		// Headers
		fputcsv( $output, array( 'Type', 'Name', 'Status', 'Details' ) );

		// Summary
		fputcsv( $output, array( 'Summary', 'Overall Status', $results['status'], '' ) );

		// Checks
		foreach ( $results['checks'] as $check ) {
			fputcsv(
				$output,
				array(
					'Check',
					$check['name'],
					$check['status'],
					json_encode( $check['details'] ?? array() ),
				)
			);
		}

		// Errors
		foreach ( $results['errors'] as $error ) {
			fputcsv( $output, array( 'Error', '', 'error', $error ) );
		}

		// Warnings
		foreach ( $results['warnings'] as $warning ) {
			fputcsv( $output, array( 'Warning', '', 'warning', $warning ) );
		}

		fclose( $output );
	}

	/**
	 * Format check details for display.
	 *
	 * @param array $details Check details
	 * @return string Formatted details
	 */
	private function format_check_details( $details ) {
		if ( empty( $details ) ) {
			return '-';
		}

		$formatted = array();
		foreach ( $details as $key => $value ) {
			if ( is_array( $value ) ) {
				$formatted[] = $key . ': ' . count( $value ) . ' items';
			} else {
				$formatted[] = $key . ': ' . $value;
			}
		}

		return implode( ', ', array_slice( $formatted, 0, 3 ) );
	}

	/**
	 * Attempt to fix issues.
	 *
	 * @param array  $results Health check results
	 * @param object $container Container instance
	 */
	private function attempt_fixes( $results, $container ) {
		$fixes_applied = 0;

		// Clear cache if memory usage is high
		if ( isset( $results['stats']['memory'] ) ) {
			$memory_limit = $results['stats']['memory']['limit'];
			$memory_peak  = $results['stats']['memory']['peak'];

			if ( $memory_limit > 0 && ( $memory_peak / $memory_limit ) > 0.8 ) {
				WP_CLI::log( 'Clearing cache to reduce memory usage...' );
				if ( $container->has( 'cache_manager' ) ) {
					$container->get( 'cache_manager' )->flush_all();
					++$fixes_applied;
				}
			}
		}

		// Re-register missing services
		$missing_services = array();
		foreach ( $results['checks'] as $check ) {
			if ( $check['name'] === 'Service Registration' &&
				isset( $check['details']['missing_services'] ) ) {
				$missing_services = $check['details']['missing_services'];
				break;
			}
		}

		if ( ! empty( $missing_services ) ) {
			WP_CLI::log( 'Attempting to re-register missing services...' );
			// Try legacy registry
			if ( class_exists( 'SCD_Service_Registry' ) ) {
				$legacy_registry = new SCD_Service_Registry( $container );
				$legacy_registry->register_all_services();
				++$fixes_applied;
			}
		}

		if ( $fixes_applied > 0 ) {
			WP_CLI::success( sprintf( 'Applied %d fixes. Please run health check again.', $fixes_applied ) );
		} else {
			WP_CLI::warning( 'No automatic fixes available. Manual intervention required.' );
		}
	}

	/**
	 * Run basic health check without registry.
	 *
	 * @param object $container Container instance
	 */
	private function run_basic_health_check( $container ) {
		$checks = array(
			'logger'           => 'SCD_Logger',
			'cache_manager'    => 'SCD_Cache_Manager',
			'database_manager' => 'SCD_Database_Manager',
		);

		$passed = 0;
		$failed = 0;

		WP_CLI::log( "\nBasic Service Health Check:" );

		foreach ( $checks as $service => $expected_class ) {
			if ( $container->has( $service ) ) {
				try {
					$instance = $container->get( $service );
					if ( is_object( $instance ) && $instance instanceof $expected_class ) {
						WP_CLI::log( sprintf( '✓ %s: OK', $service ) );
						++$passed;
					} else {
						WP_CLI::log( sprintf( '✗ %s: Wrong type', $service ) );
						++$failed;
					}
				} catch ( Exception $e ) {
					WP_CLI::log( sprintf( '✗ %s: %s', $service, $e->getMessage() ) );
					++$failed;
				}
			} else {
				WP_CLI::log( sprintf( '✗ %s: Not registered', $service ) );
				++$failed;
			}
		}

		WP_CLI::log(
			sprintf(
				"\nTotal: %d, Passed: %d, Failed: %d",
				count( $checks ),
				$passed,
				$failed
			)
		);

		if ( $failed > 0 ) {
			WP_CLI::error( 'Basic health check failed!' );
		} else {
			WP_CLI::success( 'Basic health check passed!' );
		}
	}
}

// Register command
WP_CLI::add_command( 'scd health-check', 'SCD_CLI_Health_Check' );
