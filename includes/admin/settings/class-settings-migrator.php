<?php
/**
 * Settings Migrator
 *
 * Handles migration from old settings structure to new unified settings system.
 *
 * Old Structure:
 * - scd_advanced_settings (array with various settings)
 * - scd_general_options (array with general settings)
 * - Individual options (scd_trash_retention_days, scd_trash_auto_purge, etc.)
 *
 * New Structure:
 * - scd_settings (single array with nested tab structure)
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Migrator Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings
 */
class SCD_Settings_Migrator {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Migration version option name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $migration_version_key    Migration version option name.
	 */
	private string $migration_version_key = 'scd_settings_migration_version';

	/**
	 * Current migration version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_version    Current migration version.
	 */
	private string $current_version = '1.0.0';

	/**
	 * Initialize migrator.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger    $logger    Logger instance.
	 */
	public function __construct( SCD_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if migration is needed.
	 *
	 * @since    1.0.0
	 * @return   bool    True if migration needed.
	 */
	public function needs_migration(): bool {
		$migrated_version = get_option( $this->migration_version_key, '' );

		// If never migrated or version mismatch, migration needed
		if ( empty( $migrated_version ) || version_compare( $migrated_version, $this->current_version, '<' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Run migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True if migration successful.
	 */
	public function migrate(): bool {
		if ( ! $this->needs_migration() ) {
			$this->logger->info( 'Settings migration not needed' );
			return true;
		}

		$this->logger->info( 'Starting settings migration' );

		try {
			// Get existing unified settings (if any)
			$unified_settings = get_option( 'scd_settings', array() );

			// Migrate each tab
			$unified_settings['general'] = $this->migrate_general_settings();
			$unified_settings['advanced'] = $this->migrate_advanced_settings();

			// Save unified settings
			update_option( 'scd_settings', $unified_settings );

			// Mark migration as complete
			update_option( $this->migration_version_key, $this->current_version );

			$this->logger->info( 'Settings migration completed successfully' );

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Settings migration failed', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			) );

			return false;
		}
	}

	/**
	 * Migrate general settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Migrated general settings.
	 */
	private function migrate_general_settings(): array {
		return array(
			'trash_auto_purge' => 'yes' === get_option( 'scd_trash_auto_purge', 'yes' ),
			'trash_retention_days' => absint( get_option( 'scd_trash_retention_days', 30 ) ),
		);
	}

	/**
	 * Migrate advanced settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Migrated advanced settings.
	 */
	private function migrate_advanced_settings(): array {
		$old_advanced = get_option( 'scd_advanced_settings', array() );

		return array(
			'enable_debug_mode' => isset( $old_advanced['enable_debug_mode'] ) ? (bool) $old_advanced['enable_debug_mode'] : false,
			'debug_mode_enabled_at' => isset( $old_advanced['debug_mode_enabled_at'] ) ? absint( $old_advanced['debug_mode_enabled_at'] ) : 0,
			'log_level' => isset( $old_advanced['log_level'] ) ? sanitize_text_field( $old_advanced['log_level'] ) : 'error',
			'log_retention_days' => isset( $old_advanced['log_retention_days'] ) ? absint( $old_advanced['log_retention_days'] ) : 7,
			'uninstall_data' => isset( $old_advanced['uninstall_data'] ) ? (bool) $old_advanced['uninstall_data'] : false,
		);
	}

	/**
	 * Backup old settings before migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True if backup successful.
	 */
	public function backup_old_settings(): bool {
		$old_settings = array(
			'scd_advanced_settings' => get_option( 'scd_advanced_settings', array() ),
			'scd_general_options' => get_option( 'scd_general_options', array() ),
			'scd_campaign_options' => get_option( 'scd_campaign_options', array() ),
			'scd_trash_retention_days' => get_option( 'scd_trash_retention_days', 30 ),
			'scd_trash_auto_purge' => get_option( 'scd_trash_auto_purge', 'yes' ),
		);

		$backup_option = 'scd_settings_backup_' . time();
		$result = update_option( $backup_option, $old_settings );

		if ( $result ) {
			$this->logger->info( 'Settings backup created', array( 'backup_option' => $backup_option ) );
		}

		return $result;
	}

	/**
	 * Cleanup old settings after successful migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function cleanup_old_settings(): void {
		// Delete old option keys
		$old_options = array(
			'scd_advanced_settings',
			'scd_general_options',
			'scd_campaign_options',
			'scd_trash_retention_days',
			'scd_trash_auto_purge',
		);

		foreach ( $old_options as $option ) {
			delete_option( $option );
		}

		$this->logger->info( 'Old settings cleaned up successfully' );
	}

	/**
	 * Get migration status information.
	 *
	 * @since    1.0.0
	 * @return   array    Migration status information.
	 */
	public function get_migration_status(): array {
		$migrated_version = get_option( $this->migration_version_key, '' );
		$needs_migration = $this->needs_migration();

		return array(
			'needs_migration' => $needs_migration,
			'current_version' => $this->current_version,
			'migrated_version' => $migrated_version,
			'has_old_settings' => ! empty( get_option( 'scd_advanced_settings', array() ) ) || ! empty( get_option( 'scd_general_options', array() ) ),
			'has_new_settings' => ! empty( get_option( 'scd_settings', array() ) ),
		);
	}
}
