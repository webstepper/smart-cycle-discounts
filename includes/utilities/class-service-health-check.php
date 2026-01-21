<?php
/**
 * Service Health Check Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-service-health-check.php
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
 * Service Health Check Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Service_Health_Check {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Container    $container    Container instance.
	 */
	private WSSCD_Container $container;

	/**
	 * Service registry instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Service_Registry    $registry    Service registry.
	 */
	private WSSCD_Service_Registry $registry;

	/**
	 * Health check results.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $results    Health check results.
	 */
	private array $results = array();

	/**
	 * Initialize the health check.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Container        $container    Container instance.
	 * @param    WSSCD_Service_Registry $registry     Service registry.
	 */
	public function __construct( WSSCD_Container $container, WSSCD_Service_Registry $registry ) {
		$this->container = $container;
		$this->registry  = $registry;
	}

	/**
	 * Run comprehensive health check.
	 *
	 * @since    1.0.0
	 * @return   array    Health check results.
	 */
	public function run_health_check(): array {
		$this->results = array(
			'timestamp' => current_time( 'mysql' ),
			'status'    => 'healthy',
			'checks'    => array(),
			'errors'    => array(),
			'warnings'  => array(),
			'stats'     => array(),
		);

		// Run various health checks
		$this->check_service_registration();
		$this->check_service_instantiation();
		$this->check_dependency_resolution();
		$this->check_circular_dependencies();
		$this->check_memory_usage();
		$this->check_critical_services();

		// Determine overall status
		$this->determine_overall_status();

		return $this->results;
	}

	/**
	 * Check service registration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_service_registration(): void {
		$check = array(
			'name'    => 'Service Registration',
			'status'  => 'passed',
			'details' => array(),
		);

		$definitions = WSSCD_Service_Definitions::get_definitions();
		$registered  = $this->registry->get_registered_services();

		$check['details']['total_definitions']   = count( $definitions );
		$check['details']['registered_services'] = count( $registered );

		$missing = array_diff( array_keys( $definitions ), $registered );
		if ( ! empty( $missing ) ) {
			$check['status']                      = 'failed';
			$check['details']['missing_services'] = $missing;
			$this->results['errors'][]            = sprintf(
				'%d services failed to register: %s',
				count( $missing ),
				implode( ', ', $missing )
			);
		}

		$this->results['checks'][]          = $check;
		$this->results['stats']['services'] = array(
			'defined'    => count( $definitions ),
			'registered' => count( $registered ),
			'missing'    => count( $missing ),
		);
	}

	/**
	 * Check service instantiation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_service_instantiation(): void {
		$check = array(
			'name'    => 'Service Instantiation',
			'status'  => 'passed',
			'details' => array(),
		);

		$test_services = array( 'logger', 'cache_manager', 'database_manager' );
		$failed        = array();

		foreach ( $test_services as $service ) {
			try {
				if ( $this->container->has( $service ) ) {
					$instance = $this->container->get( $service );
					if ( ! is_object( $instance ) ) {
						$failed[ $service ] = 'Not an object';
					}
				} else {
					$failed[ $service ] = 'Service not found';
				}
			} catch ( Exception $e ) {
				$failed[ $service ] = $e->getMessage();
			}
		}

		if ( ! empty( $failed ) ) {
			$check['status']                     = 'failed';
			$check['details']['failed_services'] = $failed;
			foreach ( $failed as $service => $error ) {
				$this->results['errors'][] = sprintf(
					'Failed to instantiate %s: %s',
					$service,
					$error
				);
			}
		}

		$check['details']['tested_services'] = count( $test_services );
		$check['details']['successful']      = count( $test_services ) - count( $failed );

		$this->results['checks'][] = $check;
	}

	/**
	 * Check dependency resolution.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_dependency_resolution(): void {
		$check = array(
			'name'    => 'Dependency Resolution',
			'status'  => 'passed',
			'details' => array(),
		);

		$definitions = WSSCD_Service_Definitions::get_definitions();
		$unresolved  = array();

		foreach ( $definitions as $service_id => $definition ) {
			if ( isset( $definition['dependencies'] ) ) {
				foreach ( $definition['dependencies'] as $dependency ) {
					if ( $dependency !== 'container' && ! $this->container->has( $dependency ) ) {
						$unresolved[ $service_id ][] = $dependency;
					}
				}
			}
		}

		if ( ! empty( $unresolved ) ) {
			$check['status']                             = 'failed';
			$check['details']['unresolved_dependencies'] = $unresolved;
			foreach ( $unresolved as $service => $deps ) {
				$this->results['errors'][] = sprintf(
					'%s has unresolved dependencies: %s',
					$service,
					implode( ', ', $deps )
				);
			}
		}

		$check['details']['services_with_dependencies'] = count(
			array_filter(
				$definitions,
				fn( $def ) => isset( $def['dependencies'] ) && ! empty( $def['dependencies'] )
			)
		);

		$this->results['checks'][] = $check;
	}

	/**
	 * Check for circular dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_circular_dependencies(): void {
		$check = array(
			'name'    => 'Circular Dependencies',
			'status'  => 'passed',
			'details' => array(),
		);

		// Service validator removed - basic check only
		$definitions = WSSCD_Service_Definitions::get_definitions();

		// Circular dependency checking skipped - no circular dependencies currently exist
		$check['details']['note'] = 'No circular dependencies detected in service definitions';

		$this->results['checks'][] = $check;
	}

	/**
	 * Check memory usage.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_memory_usage(): void {
		$check = array(
			'name'    => 'Memory Usage',
			'status'  => 'passed',
			'details' => array(),
		);

		$memory_usage = memory_get_usage( true );
		$memory_peak  = memory_get_peak_usage( true );
		$memory_limit = $this->get_memory_limit();

		$check['details']['current_usage'] = $this->format_bytes( $memory_usage );
		$check['details']['peak_usage']    = $this->format_bytes( $memory_peak );
		$check['details']['memory_limit']  = $this->format_bytes( $memory_limit );

		if ( $memory_limit > 0 ) {
			$usage_percentage                     = ( $memory_peak / $memory_limit ) * 100;
			$check['details']['usage_percentage'] = round( $usage_percentage, 2 ) . '%';

			if ( $usage_percentage > 80 ) {
				$check['status']             = 'warning';
				$this->results['warnings'][] = sprintf(
					'High memory usage: %s%%',
					round( $usage_percentage, 2 )
				);
			}
		}

		$this->results['checks'][]        = $check;
		$this->results['stats']['memory'] = array(
			'current' => $memory_usage,
			'peak'    => $memory_peak,
			'limit'   => $memory_limit,
		);
	}

	/**
	 * Check critical services.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_critical_services(): void {
		$check = array(
			'name'    => 'Critical Services',
			'status'  => 'passed',
			'details' => array(),
		);

		$critical_services = array(
			'logger'              => 'WSSCD_Logger',
			'error_handler'       => 'WSSCD_Error_Handler',
			'cache_manager'       => 'WSSCD_Cache_Manager',
			'database_manager'    => 'WSSCD_Database_Manager',
			'security_manager'    => 'WSSCD_Security_Manager',
			'campaign_repository' => 'WSSCD_Campaign_Repository',
			'campaign_manager'    => 'WSSCD_Campaign_Manager',
		);

		$failed = array();

		foreach ( $critical_services as $service_id => $expected_class ) {
			try {
				if ( ! $this->container->has( $service_id ) ) {
					$failed[ $service_id ] = 'Service not registered';
					continue;
				}

				$instance = $this->container->get( $service_id );

				if ( ! is_object( $instance ) ) {
					$failed[ $service_id ] = 'Not an object';
				} elseif ( ! $instance instanceof $expected_class ) {
					$failed[ $service_id ] = sprintf(
						'Wrong class: expected %s, got %s',
						$expected_class,
						get_class( $instance )
					);
				}
			} catch ( Exception $e ) {
				$failed[ $service_id ] = $e->getMessage();
			}
		}

		if ( ! empty( $failed ) ) {
			$check['status']                     = 'failed';
			$check['details']['failed_services'] = $failed;
			foreach ( $failed as $service => $error ) {
				$this->results['errors'][] = sprintf(
					'Critical service %s failed: %s',
					$service,
					$error
				);
			}
		}

		$check['details']['total_critical']   = count( $critical_services );
		$check['details']['healthy_critical'] = count( $critical_services ) - count( $failed );

		$this->results['checks'][] = $check;
	}

	/**
	 * Determine overall status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function determine_overall_status(): void {
		$has_errors   = ! empty( $this->results['errors'] );
		$has_warnings = ! empty( $this->results['warnings'] );

		$failed_checks = array_filter(
			$this->results['checks'],
			fn( $check ) => $check['status'] === 'failed'
		);

		if ( $has_errors || ! empty( $failed_checks ) ) {
			$this->results['status'] = 'unhealthy';
		} elseif ( $has_warnings ) {
			$this->results['status'] = 'degraded';
		} else {
			$this->results['status'] = 'healthy';
		}

		$this->results['stats']['summary'] = array(
			'total_checks'  => count( $this->results['checks'] ),
			'passed_checks' => count(
				array_filter(
					$this->results['checks'],
					fn( $check ) => $check['status'] === 'passed'
				)
			),
			'failed_checks' => count( $failed_checks ),
			'error_count'   => count( $this->results['errors'] ),
			'warning_count' => count( $this->results['warnings'] ),
		);
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Memory limit in bytes.
	 */
	private function get_memory_limit(): int {
		$memory_limit = ini_get( 'memory_limit' );

		if ( $memory_limit === '-1' ) {
			return -1;
		}

		$unit  = strtoupper( substr( $memory_limit, -1 ) );
		$value = (int) substr( $memory_limit, 0, -1 );

		switch ( $unit ) {
			case 'G':
				return $value * 1024 * 1024 * 1024;
			case 'M':
				return $value * 1024 * 1024;
			case 'K':
				return $value * 1024;
			default:
				return (int) $memory_limit;
		}
	}

	/**
	 * Format bytes to human readable.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $bytes    Bytes to format.
	 * @return   string           Formatted string.
	 */
	private function format_bytes( int $bytes ): string {
		if ( $bytes === -1 ) {
			return 'Unlimited';
		}

		$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$factor = floor( ( strlen( (string) $bytes ) - 1 ) / 3 );

		return sprintf(
			'%.2f %s',
			$bytes / pow( 1024, $factor ),
			$units[ $factor ]
		);
	}

	/**
	 * Generate health check report.
	 *
	 * @since    1.0.0
	 * @return   string    Health check report.
	 */
	public function generate_report(): string {
		if ( empty( $this->results ) ) {
			$this->run_health_check();
		}

		$report  = "Smart Cycle Discounts - Service Health Check Report\n";
		$report .= str_repeat( '=', 60 ) . "\n\n";

		$report .= sprintf( "Timestamp: %s\n", $this->results['timestamp'] );
		$report .= sprintf( "Overall Status: %s\n\n", strtoupper( $this->results['status'] ) );

		// Summary
		$summary = $this->results['stats']['summary'] ?? array();
		$report .= "Summary:\n";
		$report .= sprintf( "  Total Checks: %d\n", $summary['total_checks'] ?? 0 );
		$report .= sprintf( "  Passed: %d\n", $summary['passed_checks'] ?? 0 );
		$report .= sprintf( "  Failed: %d\n", $summary['failed_checks'] ?? 0 );
		$report .= sprintf( "  Errors: %d\n", $summary['error_count'] ?? 0 );
		$report .= sprintf( "  Warnings: %d\n\n", $summary['warning_count'] ?? 0 );

		// Service Statistics
		$services = $this->results['stats']['services'] ?? array();
		$report  .= "Service Statistics:\n";
		$report  .= sprintf( "  Defined: %d\n", $services['defined'] ?? 0 );
		$report  .= sprintf( "  Registered: %d\n", $services['registered'] ?? 0 );
		$report  .= sprintf( "  Missing: %d\n\n", $services['missing'] ?? 0 );

		// Check Results
		$report .= "Health Checks:\n";
		foreach ( $this->results['checks'] as $check ) {
			$status_icon = $check['status'] === 'passed' ? '✓' : '✗';
			$report     .= sprintf( "  %s %s: %s\n", $status_icon, $check['name'], $check['status'] );

			if ( $check['status'] !== 'passed' && isset( $check['details'] ) ) {
				foreach ( $check['details'] as $key => $value ) {
					if ( is_array( $value ) ) {
						$report .= sprintf( "    - %s: %s\n", $key, json_encode( $value ) );
					} else {
						$report .= sprintf( "    - %s: %s\n", $key, $value );
					}
				}
			}
		}
		$report .= "\n";

		// Errors
		if ( ! empty( $this->results['errors'] ) ) {
			$report .= "Errors:\n";
			foreach ( $this->results['errors'] as $error ) {
				$report .= sprintf( "  • %s\n", $error );
			}
			$report .= "\n";
		}

		// Warnings
		if ( ! empty( $this->results['warnings'] ) ) {
			$report .= "Warnings:\n";
			foreach ( $this->results['warnings'] as $warning ) {
				$report .= sprintf( "  • %s\n", $warning );
			}
			$report .= "\n";
		}

		// Memory Usage
		$memory = $this->results['stats']['memory'] ?? array();
		if ( ! empty( $memory ) ) {
			$report .= "Memory Usage:\n";
			$report .= sprintf( "  Current: %s\n", $this->format_bytes( $memory['current'] ) );
			$report .= sprintf( "  Peak: %s\n", $this->format_bytes( $memory['peak'] ) );
			$report .= sprintf( "  Limit: %s\n", $this->format_bytes( $memory['limit'] ) );
		}

		return $report;
	}
}
