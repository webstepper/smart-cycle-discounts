<?php
/**
 * Migration Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/class-migration-manager.php
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
 * Migration Manager
 *
 * Handles database migrations for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Migration_Manager {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private SCD_Database_Manager $db;

	/**
	 * Migration directory path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $migration_dir    Migration directory.
	 */
	private string $migration_dir;

	/**
	 * Available migrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $migrations    Available migrations.
	 */
	private array $migrations = array();

	/**
	 * Initialize the migration manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $db    Database manager.
	 */
	public function __construct( SCD_Database_Manager $db ) {
		$this->db            = $db;
		$this->migration_dir = SCD_PLUGIN_DIR . 'includes/database/migrations/';

		$this->load_migrations();
		$this->ensure_migrations_table();
	}

	/**
	 * Load available migrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_migrations(): void {
		if ( ! is_dir( $this->migration_dir ) ) {
			return;
		}

		$files = glob( $this->migration_dir . '*.php' );

		foreach ( $files as $file ) {
			$filename = basename( $file, '.php' );

			if ( preg_match( '/^(\d+)-(.+)$/', $filename, $matches ) ) {
				$this->migrations[ $matches[1] ] = array(
					'number' => $matches[1],
					'name'   => $matches[2],
					'file'   => $file,
					'class'  => $this->get_migration_class_name( $filename ),
				);
			}
		}

		ksort( $this->migrations );
	}

	/**
	 * Get migration class name from filename.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $filename    Migration filename.
	 * @return   string                 Class name.
	 */
	private function get_migration_class_name( string $filename ): string {
		$parts       = explode( '-', $filename );
		$class_parts = array_map(
			'ucfirst',
			array_map(
				function ( $part ) {
					return str_replace( '_', ' ', $part );
				},
				$parts
			)
		);

		return 'SCD_Migration_' . str_replace( ' ', '_', implode( '_', $class_parts ) );
	}

	/**
	 * Ensure migrations table exists.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function ensure_migrations_table(): void {
		if ( $this->db->table_exists( 'migrations' ) ) {
			return;
		}

		$table_name      = $this->db->get_table_name( 'migrations' );
		$charset_collate = $this->db->get_charset_collate();

		// Use raw SQL instead of dbDelta for reliability
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            migration varchar(255) NOT NULL,
            batch int(11) NOT NULL,
            executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_migration (migration),
            KEY idx_batch (batch)
        ) {$charset_collate}";

		$this->db->query( $sql );
	}

	/**
	 * Run pending migrations.
	 *
	 * @since    1.0.0
	 * @return   array    Migration results.
	 */
	public function migrate(): array {
		$pending = $this->get_pending_migrations();

		if ( empty( $pending ) ) {
			return array(
				'status'     => 'success',
				'message'    => 'No pending migrations.',
				'migrations' => array(),
			);
		}

		$batch   = $this->get_next_batch_number();
		$results = array();

		foreach ( $pending as $migration ) {
			$result    = $this->run_migration( $migration, $batch );
			$results[] = $result;

			if ( ! $result['success'] ) {
				break; // Stop on first failure
			}
		}

		$has_failures = ! empty( array_filter( $results, fn( $r ) => ! $r['success'] ) );

		return array(
			'status'     => $has_failures ? 'error' : 'success',
			'message'    => $has_failures ? 'One or more migrations failed to execute.' : 'All migrations executed successfully.',
			'batch'      => $batch,
			'migrations' => $results,
		);
	}

	/**
	 * Rollback migrations.
	 *
	 * @since    1.0.0
	 * @param    int $steps    Number of batches to rollback.
	 * @return   array            Rollback results.
	 */
	public function rollback( int $steps = 1 ): array {
		$batches = $this->get_executed_batches();

		if ( empty( $batches ) ) {
			return array(
				'status'     => 'success',
				'message'    => 'No migrations to rollback.',
				'migrations' => array(),
			);
		}

		$batches_to_rollback = array_slice( $batches, -$steps );
		$results             = array();

		foreach ( $batches_to_rollback as $batch ) {
			$migrations = $this->get_migrations_in_batch( $batch );

			// Rollback in reverse order
			foreach ( array_reverse( $migrations ) as $migration_name ) {
				$migration = $this->migrations[ $this->extract_migration_number( $migration_name ) ] ?? null;

				if ( $migration ) {
					$result    = $this->rollback_migration( $migration );
					$results[] = $result;

					if ( ! $result['success'] ) {
						break 2; // Stop on first failure
					}
				}
			}
		}

		return array(
			'status'     => empty( array_filter( $results, fn( $r ) => ! $r['success'] ) ) ? 'success' : 'error',
			'migrations' => $results,
		);
	}

	/**
	 * Get pending migrations.
	 *
	 * @since    1.0.0
	 * @return   array    Pending migrations.
	 */
	public function get_pending_migrations(): array {
		$executed = $this->get_executed_migrations();
		$pending  = array();

		foreach ( $this->migrations as $migration ) {
			if ( ! in_array( $migration['number'] . '-' . $migration['name'], $executed ) ) {
				$pending[] = $migration;
			}
		}

		return $pending;
	}

	/**
	 * Get executed migrations.
	 *
	 * @since    1.0.0
	 * @return   array    Executed migration names.
	 */
	public function get_executed_migrations(): array {
		$table_name = $this->db->get_table_name( 'migrations' );

		$results = $this->db->get_results(
			"SELECT migration FROM $table_name ORDER BY id ASC"
		);

		return array_column( $results, 'migration' );
	}

	/**
	 * Run a single migration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $migration    Migration data.
	 * @param    int   $batch        Batch number.
	 * @return   array                  Migration result.
	 */
	private function run_migration( array $migration, int $batch ): array {
		try {
			// Ensure interface is loaded
			if ( ! interface_exists( 'SCD_Migration_Interface' ) ) {
				require_once $this->migration_dir . '/../interface-migration.php';
			}

			require_once $migration['file'];

			if ( ! class_exists( $migration['class'] ) ) {
				throw new Exception( 'Migration class ' . $migration['class'] . ' not found.' );
			}

			$instance = new $migration['class']( $this->db );

			if ( ! method_exists( $instance, 'up' ) ) {
				throw new Exception( 'Migration ' . $migration['class'] . ' does not have an "up" method.' );
			}

			// Execute migration in transaction
			$result = $this->db->transaction(
				function () use ( $instance, $migration, $batch ) {
					$instance->up();

					// Record migration
					return $this->db->insert(
						'migrations',
						array(
							'migration' => $migration['number'] . '-' . $migration['name'],
							'batch'     => $batch,
						)
					);
				}
			);

			if ( $result === false ) {
				throw new Exception( 'Failed to execute migration ' . $migration['name'] . '.' );
			}

			return array(
				'success'   => true,
				'migration' => $migration['name'],
				'message'   => 'Migration ' . $migration['name'] . ' executed successfully.',
			);

		} catch ( Exception $e ) {
			return array(
				'success'   => false,
				'migration' => $migration['name'],
				'message'   => "Migration {$migration['name']} failed: " . $e->getMessage(),
			);
		}
	}

	/**
	 * Rollback a single migration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $migration    Migration data.
	 * @return   array                  Rollback result.
	 */
	private function rollback_migration( array $migration ): array {
		try {
			require_once $migration['file'];

			if ( ! class_exists( $migration['class'] ) ) {
				throw new Exception( 'Migration class ' . $migration['class'] . ' not found.' );
			}

			$instance = new $migration['class']( $this->db );

			if ( ! method_exists( $instance, 'down' ) ) {
				throw new Exception( 'Migration ' . $migration['class'] . ' does not have a "down" method.' );
			}

			// Execute rollback in transaction
			$result = $this->db->transaction(
				function () use ( $instance, $migration ) {
					$instance->down();

					return $this->db->delete(
						'migrations',
						array(
							'migration' => $migration['number'] . '-' . $migration['name'],
						)
					);
				}
			);

			if ( $result === false ) {
				throw new Exception( 'Failed to rollback migration ' . $migration['name'] . '.' );
			}

			return array(
				'success'   => true,
				'migration' => $migration['name'],
				'message'   => 'Migration ' . $migration['name'] . ' rolled back successfully.',
			);

		} catch ( Exception $e ) {
			return array(
				'success'   => false,
				'migration' => $migration['name'],
				'message'   => "Migration {$migration['name']} rollback failed: " . $e->getMessage(),
			);
		}
	}

	/**
	 * Get next batch number.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Next batch number.
	 */
	private function get_next_batch_number(): int {
		$table_name = $this->db->get_table_name( 'migrations' );

		$result = $this->db->get_var(
			"SELECT MAX(batch) FROM $table_name"
		);

		return $result ? (int) $result + 1 : 1;
	}

	/**
	 * Get executed batches.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Executed batch numbers.
	 */
	private function get_executed_batches(): array {
		$table_name = $this->db->get_table_name( 'migrations' );

		$results = $this->db->get_results(
			"SELECT DISTINCT batch FROM $table_name ORDER BY batch DESC"
		);

		return array_column( $results, 'batch' );
	}

	/**
	 * Get migrations in a specific batch.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $batch    Batch number.
	 * @return   array            Migration names in batch.
	 */
	private function get_migrations_in_batch( int $batch ): array {
		$table_name = $this->db->get_table_name( 'migrations' );

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT migration FROM $table_name WHERE batch = %d ORDER BY id ASC",
				$batch
			)
		);

		return array_column( $results, 'migration' );
	}

	/**
	 * Extract migration number from migration name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $migration_name    Migration name.
	 * @return   string                       Migration number.
	 */
	private function extract_migration_number( string $migration_name ): string {
		$parts = explode( '-', $migration_name, 2 );
		return $parts[0] ?? '';
	}

	/**
	 * Get migration status.
	 *
	 * @since    1.0.0
	 * @return   array    Migration status.
	 */
	public function get_status(): array {
		$total_migrations    = count( $this->migrations );
		$executed_migrations = count( $this->get_executed_migrations() );
		$pending_migrations  = count( $this->get_pending_migrations() );

		return array(
			'total'      => $total_migrations,
			'executed'   => $executed_migrations,
			'pending'    => $pending_migrations,
			'up_to_date' => $pending_migrations === 0,
			'migrations' => array_map(
				function ( $migration ) {
					$migration_name = $migration['number'] . '-' . $migration['name'];
					$executed = in_array( $migration_name, $this->get_executed_migrations() );

					return array(
						'name'     => $migration['name'],
						'number'   => $migration['number'],
						'executed' => $executed,
						'file'     => basename( $migration['file'] ),
					);
				},
				$this->migrations
			),
		);
	}

	/**
	 * Reset all migrations.
	 *
	 * @since    1.0.0
	 * @return   array    Reset results.
	 */
	public function reset(): array {
		$executed = $this->get_executed_migrations();

		if ( empty( $executed ) ) {
			return array(
				'status'  => 'success',
				'message' => 'No migrations to reset.',
			);
		}

		// Rollback all migrations
		$rollback_result = $this->rollback( count( $this->get_executed_batches() ) );

		if ( $rollback_result['status'] === 'success' ) {
			return array(
				'status'  => 'success',
				'message' => 'All migrations reset successfully.',
			);
		}

		return $rollback_result;
	}

	/**
	 * Fresh migration (reset and migrate).
	 *
	 * @since    1.0.0
	 * @return   array    Fresh migration results.
	 */
	public function fresh(): array {
		$reset_result = $this->reset();

		if ( $reset_result['status'] !== 'success' ) {
			return $reset_result;
		}

		return $this->migrate();
	}

	/**
	 * Get available migrations.
	 *
	 * @since    1.0.0
	 * @return   array    Available migrations.
	 */
	public function get_available_migrations(): array {
		return $this->migrations;
	}
}
