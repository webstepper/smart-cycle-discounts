<?php
/**
 * Service Registry Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/bootstrap/class-service-registry.php
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
 * Service Registry Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Service_Registry {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Container    $container    Container instance.
	 */
	private SCD_Container $container;

	/**
	 * Service definitions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $definitions    Service definitions.
	 */
	private array $definitions = array();

	/**
	 * Registration errors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $errors    Registration errors.
	 */
	private array $errors = array();

	/**
	 * Registered services tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $registered    Registered services.
	 */
	private array $registered = array();

	/**
	 * Initialize the service registry.
	 *
	 * @since    1.0.0
	 * @param    SCD_Container $container    Container instance.
	 */
	public function __construct( SCD_Container $container ) {
		$this->container = $container;
		$this->load_definitions();
	}

	/**
	 * Load service definitions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_definitions(): void {
		$this->definitions = SCD_Service_Definitions::get_definitions();
	}

	/**
	 * Register all services.
	 *
	 * @since    1.0.0
	 * @return   bool    True if all services registered successfully.
	 */
	public function register_all_services(): bool {
		$this->errors = array();

		// First, validate all definitions
		if ( ! $this->validate_all_definitions() ) {
			$this->log_validation_errors();
			return false;
		}

		// Sort services by dependencies
		$sorted_services = $this->sort_by_dependencies();

		// Register services in dependency order
		foreach ( $sorted_services as $service_id ) {
			if ( ! $this->register_service( $service_id ) ) {
				SCD_Log::error( 'Failed to register service', array( 'service_id' => $service_id ) );
			}
		}

		// Register aliases
		$this->register_aliases();

		return empty( $this->errors );
	}

	/**
	 * Register a single service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $service_id    Service identifier.
	 * @return   bool                     True if registered successfully.
	 */
	private function register_service( string $service_id ): bool {
		// Skip if already registered
		if ( isset( $this->registered[ $service_id ] ) ) {
			return true;
		}

		if ( ! isset( $this->definitions[ $service_id ] ) ) {
			$this->errors[ $service_id ] = 'Service definition not found';
			return false;
		}

		$definition = $this->definitions[ $service_id ];

		try {
			// Check if class exists
			if ( ! class_exists( $definition['class'] ) ) {
				// Try to load the class file for repositories
				if ( strpos( $definition['class'], '_Repository' ) !== false ) {
					$class_file = str_replace( '_', '-', strtolower( $definition['class'] ) ) . '.php';
					$repo_path  = SCD_INCLUDES_DIR . 'database/repositories/class-' . str_replace( 'scd-', '', $class_file );

					if ( file_exists( $repo_path ) ) {
						require_once $repo_path;
					}
				}

				// Check again after attempting to load
				if ( ! class_exists( $definition['class'] ) ) {
					throw new Exception(
						sprintf(
							'Class %s not found. Make sure it is loaded. File path checked: %s',
							$definition['class'],
							isset( $repo_path ) ? $repo_path : 'N/A'
						)
					);
				}
			}

			// Register dependencies first
			if ( isset( $definition['dependencies'] ) ) {
				foreach ( $definition['dependencies'] as $dependency ) {
					if ( $dependency !== 'container' && ! $this->register_service( $dependency ) ) {
						throw new Exception(
							sprintf(
								'Failed to register dependency "%s"',
								$dependency
							)
						);
					}
				}
			}

			// Register the service
			if ( isset( $definition['singleton'] ) && $definition['singleton'] ) {
				$this->container->singleton( $service_id, $definition['factory'] );
			} else {
				$this->container->bind( $service_id, $definition['factory'] );
			}

			$this->registered[ $service_id ] = true;

			return true;

		} catch ( Exception $e ) {
			$this->errors[ $service_id ] = $e->getMessage();
			SCD_Log::exception( $e, 'Failed to register service: ' . $service_id );
			return false;
		}
	}

	/**
	 * Validate all service definitions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if all definitions are valid.
	 */
	private function validate_all_definitions(): bool {
		if ( empty( $this->definitions ) ) {
			$this->errors[] = 'No service definitions found';
			return false;
		}

		return true;
	}

	/**
	 * Sort services by dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Sorted service IDs.
	 */
	private function sort_by_dependencies(): array {
		$sorted  = array();
		$visited = array();

		foreach ( array_keys( $this->definitions ) as $service_id ) {
			$this->topological_sort( $service_id, $visited, $sorted );
		}

		return $sorted;
	}

	/**
	 * Perform topological sort for dependency resolution.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $service_id    Service identifier.
	 * @param    array  $visited       Visited services.
	 * @param    array  $sorted        Sorted services.
	 * @return   void
	 */
	private function topological_sort( string $service_id, array &$visited, array &$sorted ): void {
		if ( isset( $visited[ $service_id ] ) ) {
			return;
		}

		$visited[ $service_id ] = true;

		if ( isset( $this->definitions[ $service_id ]['dependencies'] ) ) {
			foreach ( $this->definitions[ $service_id ]['dependencies'] as $dependency ) {
				if ( $dependency !== 'container' && isset( $this->definitions[ $dependency ] ) ) {
					$this->topological_sort( $dependency, $visited, $sorted );
				}
			}
		}

		$sorted[] = $service_id;
	}

	/**
	 * Register service aliases.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_aliases(): void {
		$aliases = SCD_Service_Definitions::get_aliases();

		foreach ( $aliases as $alias => $service ) {
			if ( $this->container->has( $service ) ) {
				$this->container->alias( $service, $alias );
			}
		}
	}

	/**
	 * Log validation errors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function log_validation_errors(): void {
		foreach ( $this->errors as $error ) {
			SCD_Log::error( 'Registry Error', array( 'error' => $error ) );
		}
	}

	/**
	 * Get registration errors.
	 *
	 * @since    1.0.0
	 * @return   array    Registration errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get registered services.
	 *
	 * @since    1.0.0
	 * @return   array    Registered service IDs.
	 */
	public function get_registered_services(): array {
		return array_keys( $this->registered );
	}

	/**
	 * Health check for all services.
	 *
	 * @since    1.0.0
	 * @return   array    Health check results.
	 */
	public function health_check(): array {
		$results = array(
			'total_services'      => count( $this->definitions ),
			'registered_services' => count( $this->registered ),
			'failed_services'     => array(),
			'missing_classes'     => array(),
			'status'              => 'healthy',
		);

		foreach ( $this->definitions as $service_id => $definition ) {
			if ( ! isset( $this->registered[ $service_id ] ) ) {
				$results['failed_services'][] = $service_id;
			}

			if ( ! class_exists( $definition['class'] ) ) {
				$results['missing_classes'][] = $definition['class'];
			}
		}

		if ( ! empty( $results['failed_services'] ) || ! empty( $results['missing_classes'] ) ) {
			$results['status'] = 'unhealthy';
		}

		return $results;
	}

	/**
	 * Get a service from the container.
	 *
	 * @since    1.0.0
	 * @param    string $service_id    Service identifier.
	 * @return   mixed                    Service instance or null if not found.
	 */
	public function get( string $service_id ) {
		return $this->container->has( $service_id ) ? $this->container->get( $service_id ) : null;
	}

	/**
	 * Check if a service exists in the container.
	 *
	 * @since    1.0.0
	 * @param    string $service_id    Service identifier.
	 * @return   bool                     True if service exists.
	 */
	public function has( string $service_id ): bool {
		return $this->container->has( $service_id );
	}

	/**
	 * Generate service report.
	 *
	 * @since    1.0.0
	 * @return   string    Service report.
	 */
	public function generate_report(): string {
		$health = $this->health_check();

		$report  = "Smart Cycle Discounts - Service Registry Report\n";
		$report .= str_repeat( '=', 50 ) . "\n\n";

		$report .= sprintf( "Status: %s\n", strtoupper( $health['status'] ) );
		$report .= sprintf( "Total Services: %d\n", $health['total_services'] );
		$report .= sprintf( "Registered Services: %d\n", $health['registered_services'] );
		$report .= "\n";

		if ( ! empty( $health['failed_services'] ) ) {
			$report .= "Failed Services:\n";
			foreach ( $health['failed_services'] as $service ) {
				$report .= sprintf( "  - %s\n", $service );
				if ( isset( $this->errors[ $service ] ) ) {
					$report .= sprintf( "    Error: %s\n", $this->errors[ $service ] );
				}
			}
			$report .= "\n";
		}

		if ( ! empty( $health['missing_classes'] ) ) {
			$report .= "Missing Classes:\n";
			foreach ( $health['missing_classes'] as $class ) {
				$report .= sprintf( "  - %s\n", $class );
			}
			$report .= "\n";
		}

		return $report;
	}
}
