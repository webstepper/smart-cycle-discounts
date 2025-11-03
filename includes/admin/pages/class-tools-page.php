<?php
/**
 * Tools Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-tools-page.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Tools Page Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages
 */
class SCD_Tools_Page {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate|null    $feature_gate    Feature gate instance.
	 */
	private ?SCD_Feature_Gate $feature_gate;

	/**
	 * Initialize tools page.
	 *
	 * @since    1.0.0
	 * @param    object                $container      Container instance.
	 * @param    SCD_Logger            $logger         Logger instance.
	 * @param    SCD_Feature_Gate|null $feature_gate   Feature gate instance.
	 */
	public function __construct( object $container, SCD_Logger $logger, ?SCD_Feature_Gate $feature_gate = null ) {
		$this->container    = $container;
		$this->logger       = $logger;
		$this->feature_gate = $feature_gate;
	}

	/**
	 * Render the tools page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		echo '<div class="wrap scd-tools-page">';
		echo '<h1>' . esc_html__( 'Tools & Maintenance', 'smart-cycle-discounts' ) . '</h1>';

		// Show admin notices
		settings_errors( 'scd_tools_messages' );

		// Render tools sections
		echo '<div class="scd-tools-container">';

		$this->render_import_export_section();
		$this->render_database_section();
		$this->render_cache_section();
		$this->render_debug_section();
		$this->render_diagnostics_section();

		echo '</div>'; // .scd-tools-container
		echo '</div>'; // .wrap
	}

	/**
	 * Render import/export section.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_import_export_section(): void {
		$can_export  = $this->feature_gate ? $this->feature_gate->can_export_data() : false;
		$upgrade_url = function_exists( 'scd_get_upgrade_url' ) ? scd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		?>
		<div class="scd-tools-section">
			<h2>
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Import & Export', 'smart-cycle-discounts' ); ?>
				<?php if ( ! $can_export ) : ?>
					<span class="scd-pro-badge"><?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Export campaigns and settings for backup, or import from a previously exported file.', 'smart-cycle-discounts' ); ?>
			</p>

			<?php if ( ! $can_export ) : ?>
				<div class="notice notice-info inline">
					<p>
						<strong><?php esc_html_e( 'Export functionality is available in Pro version', 'smart-cycle-discounts' ); ?></strong>
						<?php esc_html_e( '- Upgrade to export your campaigns and settings for backup or migration.', 'smart-cycle-discounts' ); ?>
						<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-small button-primary">
							<?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Export Campaigns', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Export all campaigns to a JSON file for backup or migration purposes.', 'smart-cycle-discounts' ); ?>
							</p>
							<?php if ( $can_export ) : ?>
								<button type="button" class="button button-secondary scd-export-campaigns-btn">
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e( 'Export Campaigns', 'smart-cycle-discounts' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="button button-secondary" disabled="disabled">
									<span class="dashicons dashicons-lock"></span>
									<?php esc_html_e( 'Export Campaigns (Pro)', 'smart-cycle-discounts' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Export Settings', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Export all plugin settings to a JSON file for backup or transfer to another site.', 'smart-cycle-discounts' ); ?>
							</p>
							<?php if ( $can_export ) : ?>
								<button type="button" class="button button-secondary scd-export-settings-btn">
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e( 'Export Settings', 'smart-cycle-discounts' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="button button-secondary" disabled="disabled">
									<span class="dashicons dashicons-lock"></span>
									<?php esc_html_e( 'Export Settings (Pro)', 'smart-cycle-discounts' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Import Data', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Import campaigns or settings from a previously exported JSON file.', 'smart-cycle-discounts' ); ?>
							</p>
							<input type="file" id="scd-import-file" accept=".json" class="regular-text">
							<button type="button" class="button button-primary scd-import-data-btn">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Import File', 'smart-cycle-discounts' ); ?>
							</button>
							<div class="scd-import-status" style="margin-top: 10px;"></div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render database section.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_database_section(): void {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$table_size      = $wpdb->get_var( "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$campaigns_table}'" );

		?>
		<div class="scd-tools-section">
			<h2>
				<span class="dashicons dashicons-database-export"></span>
				<?php esc_html_e( 'Database Maintenance', 'smart-cycle-discounts' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Optimize database tables, clean expired data, and manage analytics.', 'smart-cycle-discounts' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Optimize Tables', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Optimize database tables to improve performance and reclaim disk space.', 'smart-cycle-discounts' ); ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Current size:', 'smart-cycle-discounts' ); ?></strong>
								<?php echo esc_html( $table_size ? $table_size . ' MB' : __( 'Unknown', 'smart-cycle-discounts' ) ); ?>
							</p>
							<button type="button" class="button button-secondary scd-optimize-tables-btn">
								<span class="dashicons dashicons-admin-tools"></span>
								<?php esc_html_e( 'Optimize Now', 'smart-cycle-discounts' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clean Expired Data', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Remove expired campaigns and old analytics data to reduce database size.', 'smart-cycle-discounts' ); ?>
							</p>
							<button type="button" class="button button-secondary scd-cleanup-expired-btn" onclick="return confirm('<?php echo esc_js( __( 'This will permanently delete expired campaigns and old data. Continue?', 'smart-cycle-discounts' ) ); ?>');">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clean Up Now', 'smart-cycle-discounts' ); ?>
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render cache section.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_cache_section(): void {
		?>
		<div class="scd-tools-section">
			<h2>
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'Cache Management', 'smart-cycle-discounts' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Clear and rebuild all cached data including object cache, transients, and campaign data.', 'smart-cycle-discounts' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clear & Rebuild Cache', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Clears all cached data (object cache, transients, campaign data) and rebuilds the cache for optimal performance.', 'smart-cycle-discounts' ); ?>
							</p>
							<button type="button" class="button button-primary scd-rebuild-cache-btn">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Clear & Rebuild Cache', 'smart-cycle-discounts' ); ?>
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}


	/**
	 * Render log viewer section.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_debug_section(): void {
		require_once SCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new SCD_Log_Manager();
		$log_stats   = $log_manager->get_log_stats();

		?>
		<div class="scd-tools-section">
			<h2>
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Log Viewer', 'smart-cycle-discounts' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'View, download, and manage debug log files. To configure logging settings, visit Settings > Advanced.', 'smart-cycle-discounts' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'View Log File', 'smart-cycle-discounts' ); ?></th>
						<td>
							<div class="scd-log-viewer-wrapper">
								<p class="description">
									<?php esc_html_e( 'View debug log contents. Sensitive information is automatically redacted.', 'smart-cycle-discounts' ); ?>
									<br>
									<strong><?php esc_html_e( 'Showing last 500 lines (~10-30 minutes).', 'smart-cycle-discounts' ); ?></strong>
									<?php esc_html_e( 'Production mode logs errors only. Change log level in Settings > Advanced to see more detail.', 'smart-cycle-discounts' ); ?>
								</p>

								<div class="scd-log-stats" style="margin: 15px 0;">
									<strong><?php esc_html_e( 'Log File:', 'smart-cycle-discounts' ); ?></strong><br>
									<?php if ( $log_stats['exists'] ) : ?>
										<?php esc_html_e( 'Plugin Log:', 'smart-cycle-discounts' ); ?>
										<code><?php echo esc_html( $log_stats['size_formatted'] ); ?></code>
										(<?php echo esc_html( $log_stats['modified_date'] ); ?>)
										- <?php echo esc_html( number_format_i18n( $log_stats['lines'] ) ); ?> <?php esc_html_e( 'lines', 'smart-cycle-discounts' ); ?>
									<?php else : ?>
										<em><?php esc_html_e( 'No log file exists yet.', 'smart-cycle-discounts' ); ?></em>
									<?php endif; ?>
								</div>

								<div class="scd-log-actions" style="margin: 10px 0;">
									<button type="button" class="button button-secondary scd-view-logs-btn">
										<span class="dashicons dashicons-visibility"></span>
										<?php esc_html_e( 'View Log', 'smart-cycle-discounts' ); ?>
									</button>
									<button type="button" class="button button-secondary scd-download-logs-btn" style="margin-left: 4px;">
										<span class="dashicons dashicons-download"></span>
										<?php esc_html_e( 'Download', 'smart-cycle-discounts' ); ?>
									</button>
								</div>

								<div id="scd-log-viewer-modal" style="display:none; margin-top: 15px;">
									<textarea readonly class="large-text code" rows="20" style="font-family: monospace; font-size: 12px;"></textarea>
									<div style="margin-top: 10px;">
										<button type="button" class="button button-secondary scd-copy-log-btn">
											<span class="dashicons dashicons-clipboard"></span>
											<?php esc_html_e( 'Copy to Clipboard', 'smart-cycle-discounts' ); ?>
										</button>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clear Log File', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Delete all log file contents. This action cannot be undone.', 'smart-cycle-discounts' ); ?>
							</p>
							<button type="button" class="button button-secondary scd-clear-logs-btn">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear Log', 'smart-cycle-discounts' ); ?>
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render diagnostics section.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_diagnostics_section(): void {
		?>
		<div class="scd-tools-section">
			<h2>
				<span class="dashicons dashicons-admin-site"></span>
				<?php esc_html_e( 'System Diagnostics', 'smart-cycle-discounts' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Run system health checks and generate detailed diagnostic reports.', 'smart-cycle-discounts' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Health Check', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Run a comprehensive health check to identify potential issues with the plugin configuration.', 'smart-cycle-discounts' ); ?>
							</p>
							<button type="button" class="button button-secondary scd-health-check-btn">
								<span class="dashicons dashicons-heart"></span>
								<?php esc_html_e( 'Run Health Check', 'smart-cycle-discounts' ); ?>
							</button>
							<div id="scd-health-check-results" style="margin-top: 10px;"></div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'System Report', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Generate a detailed system report for troubleshooting and support purposes. This report can be shared with support to help diagnose issues.', 'smart-cycle-discounts' ); ?>
							</p>
							<button type="button" class="button button-secondary scd-generate-report-btn" data-action="generate">
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e( 'Generate Report', 'smart-cycle-discounts' ); ?>
							</button>
							<button type="button" class="button button-secondary scd-copy-report-btn" data-action="copy" style="display:none;">
								<span class="dashicons dashicons-clipboard"></span>
								<?php esc_html_e( 'Copy to Clipboard', 'smart-cycle-discounts' ); ?>
							</button>
							<button type="button" class="button button-secondary scd-download-report-btn" data-action="download" style="display:none;">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download Report', 'smart-cycle-discounts' ); ?>
							</button>
							<div id="scd-system-report" style="margin-top: 10px; display: none;">
								<textarea readonly class="large-text code" rows="20" style="font-family: monospace; font-size: 12px;"></textarea>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
