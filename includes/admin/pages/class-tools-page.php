<?php
/**
 * Tools Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-tools-page.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
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
class WSSCD_Tools_Page {

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
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate|null    $feature_gate    Feature gate instance.
	 */
	private ?WSSCD_Feature_Gate $feature_gate;

	/**
	 * Initialize tools page.
	 *
	 * @since    1.0.0
	 * @param    object                $container      Container instance.
	 * @param    WSSCD_Logger            $logger         Logger instance.
	 * @param    WSSCD_Feature_Gate|null $feature_gate   Feature gate instance.
	 */
	public function __construct( object $container, WSSCD_Logger $logger, ?WSSCD_Feature_Gate $feature_gate = null ) {
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		echo '<div class="wrap wsscd-tools-page">';
		echo '<h1>' . esc_html__( 'Tools & Maintenance', 'smart-cycle-discounts' ) . '</h1>';

		echo '<p class="description">';
		printf(
			/* translators: %s: Help Center URL. */
			esc_html__( 'Need help using these tools? Visit the %s.', 'smart-cycle-discounts' ),
			'<a href="' . esc_url( 'https://webstepper.io/contact-us/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Help Center', 'smart-cycle-discounts' ) . '</a>'
		);
		echo '</p>';

		// Show admin notices
		settings_errors( 'wsscd_tools_messages' );

		echo '<div class="wsscd-tools-container">';

		$this->render_import_export_section();
		$this->render_database_section();
		$this->render_cache_section();
		$this->render_debug_section();
		$this->render_diagnostics_section();

		echo '</div>'; // .wsscd-tools-container
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
		$upgrade_url = function_exists( 'wsscd_get_upgrade_url' ) ? wsscd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		?>
		<div class="wsscd-tools-section">
			<h2>
				<?php WSSCD_Icon_Helper::render( 'upload', array( 'size' => 16 ) ); ?>
				<?php esc_html_e( 'Import & Export', 'smart-cycle-discounts' ); ?>
				<?php if ( ! $can_export ) : ?>
					<?php echo wp_kses_post( WSSCD_Badge_Helper::pro_badge() ); ?>
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
						<?php
						WSSCD_Button_Helper::primary(
							__( 'Upgrade to Pro', 'smart-cycle-discounts' ),
							array(
								'size' => 'small',
								'href' => esc_url( $upgrade_url ),
							)
						);
						?>
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
								<?php
								WSSCD_Button_Helper::secondary(
									__( 'Export Campaigns', 'smart-cycle-discounts' ),
									array(
										'type'    => 'button',
										'icon'    => 'download',
										'classes' => array( 'wsscd-export-campaigns-btn' ),
									)
								);
								?>
							<?php else : ?>
								<?php
								WSSCD_Button_Helper::secondary(
									__( 'Export Campaigns (Pro)', 'smart-cycle-discounts' ),
									array(
										'type'     => 'button',
										'icon'     => 'lock',
										'disabled' => true,
									)
								);
								?>
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
								<?php
								WSSCD_Button_Helper::secondary(
									__( 'Export Settings', 'smart-cycle-discounts' ),
									array(
										'type'    => 'button',
										'icon'    => 'download',
										'classes' => array( 'wsscd-export-settings-btn' ),
									)
								);
								?>
							<?php else : ?>
								<?php
								WSSCD_Button_Helper::secondary(
									__( 'Export Settings (Pro)', 'smart-cycle-discounts' ),
									array(
										'type'     => 'button',
										'icon'     => 'lock',
										'disabled' => true,
									)
								);
								?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Import Data', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Import campaigns or settings from a previously exported JSON file.', 'smart-cycle-discounts' ); ?>
							</p>
							<input type="file" id="wsscd-import-file" accept=".json" class="regular-text">
							<?php
							WSSCD_Button_Helper::primary(
								__( 'Import File', 'smart-cycle-discounts' ),
								array(
									'type'    => 'button',
									'icon'    => 'upload',
									'classes' => array( 'wsscd-import-data-btn' ),
								)
							);
							?>
							<div class="wsscd-import-status" style="margin-top: 10px;"></div>
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

		// Calculate total size of all plugin tables
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- information_schema query has no WP abstraction.
		$table_size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
				FROM information_schema.TABLES
				WHERE table_schema = DATABASE()
				AND table_name LIKE %s",
				$wpdb->esc_like( $wpdb->prefix . 'wsscd_' ) . '%'
			)
		);

		?>
		<div class="wsscd-tools-section">
			<h2>
				<?php WSSCD_Icon_Helper::render( 'database-export', array( 'size' => 16 ) ); ?>
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
								<?php esc_html_e( 'Optimize all plugin database tables to improve performance and reclaim disk space.', 'smart-cycle-discounts' ); ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Total database size:', 'smart-cycle-discounts' ); ?></strong>
								<?php echo esc_html( $table_size ? $table_size . ' MB' : __( 'Unknown', 'smart-cycle-discounts' ) ); ?>
							</p>
							<?php
							WSSCD_Button_Helper::secondary(
								__( 'Optimize Now', 'smart-cycle-discounts' ),
								array(
									'type'    => 'button',
									'icon'    => 'admin-tools',
									'classes' => array( 'wsscd-optimize-tables-btn' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clean Expired Data', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Remove expired campaigns and old analytics data to reduce database size.', 'smart-cycle-discounts' ); ?>
							</p>
							<?php
							$confirm_message = esc_js( __( 'This will permanently delete expired campaigns and old data. Continue?', 'smart-cycle-discounts' ) );
							WSSCD_Button_Helper::secondary(
								__( 'Clean Up Now', 'smart-cycle-discounts' ),
								array(
									'type'       => 'button',
									'icon'       => 'trash',
									'classes'    => array( 'wsscd-cleanup-expired-btn' ),
									'attributes' => array( 'onclick' => "return confirm('" . $confirm_message . "');" ),
								)
							);
							?>
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
		// Get cache statistics
		$cache_manager = Smart_Cycle_Discounts::get_service( 'cache_manager' );
		$cache_stats   = array(
			'transient_count'        => 0,
			'object_cache_available' => false,
			'enabled'                => true,
		);

		if ( $cache_manager && method_exists( $cache_manager, 'get_stats' ) ) {
			$cache_stats = array_merge( $cache_stats, $cache_manager->get_stats() );
		}

		?>
		<div class="wsscd-tools-section">
			<h2>
				<?php WSSCD_Icon_Helper::render( 'performance', array( 'size' => 20 ) ); ?>
				<?php esc_html_e( 'Cache Management', 'smart-cycle-discounts' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Clear all cached data including object cache, transients, and campaign data.', 'smart-cycle-discounts' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache Status', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p>
								<strong><?php esc_html_e( 'Cached transients:', 'smart-cycle-discounts' ); ?></strong>
								<?php echo esc_html( $cache_stats['transient_count'] ); ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Object cache:', 'smart-cycle-discounts' ); ?></strong>
								<?php
								if ( $cache_stats['object_cache_available'] ) {
									echo wp_kses_post( WSSCD_Badge_Helper::health_badge( 'good', __( 'Available', 'smart-cycle-discounts' ) ) );
								} else {
									echo wp_kses_post( WSSCD_Badge_Helper::health_badge( 'neutral', __( 'Not available', 'smart-cycle-discounts' ) ) );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clear Cache', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Clears all cached data. Fresh data will be loaded automatically on next page visit.', 'smart-cycle-discounts' ); ?>
							</p>
							<?php
							WSSCD_Button_Helper::primary(
								__( 'Clear Cache', 'smart-cycle-discounts' ),
								array(
									'type'    => 'button',
									'icon'    => 'update',
									'classes' => array( 'wsscd-rebuild-cache-btn' ),
								)
							);
							?>
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
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-log-manager.php';
		$log_manager = new WSSCD_Log_Manager();
		$log_stats   = $log_manager->get_log_stats();

		?>
		<div class="wsscd-tools-section">
			<h2>
				<?php WSSCD_Icon_Helper::render( 'search', array( 'size' => 16 ) ); ?>
				<?php esc_html_e( 'Log Viewer', 'smart-cycle-discounts' ); ?>
			</h2>
			<p class="description">
				<?php
				$settings_url = admin_url( 'admin.php?page=wsscd-settings&tab=advanced' );
				printf(
					wp_kses(
						/* translators: %s: URL to Settings > Advanced page */
						__( 'View, download, and manage debug log files. To configure logging settings, visit <a href="%s">Settings &gt; Advanced</a>.', 'smart-cycle-discounts' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( $settings_url )
				);
				?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'View Log File', 'smart-cycle-discounts' ); ?></th>
						<td>
							<div class="wsscd-log-viewer-wrapper">
								<p class="description">
									<?php esc_html_e( 'View debug log contents. Sensitive information is automatically redacted.', 'smart-cycle-discounts' ); ?>
									<br>
									<strong><?php esc_html_e( 'Showing last 500 lines (~10-30 minutes).', 'smart-cycle-discounts' ); ?></strong>
									<?php
									printf(
										wp_kses(
											/* translators: %s: URL to Settings > Advanced page */
											__( 'Production mode logs errors and warnings. Change log level in <a href="%s">Settings &gt; Advanced</a> to see more detail.', 'smart-cycle-discounts' ),
											array( 'a' => array( 'href' => array() ) )
										),
										esc_url( $settings_url )
									);
									?>
								</p>

								<div class="wsscd-log-stats" style="margin: 15px 0;">
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

								<div class="wsscd-log-actions" style="margin: 10px 0;">
									<?php
									WSSCD_Button_Helper::secondary(
										__( 'View Log', 'smart-cycle-discounts' ),
										array(
											'type'    => 'button',
											'icon'    => 'visibility',
											'classes' => array( 'wsscd-view-logs-btn' ),
										)
									);

									WSSCD_Button_Helper::secondary(
										__( 'Download', 'smart-cycle-discounts' ),
										array(
											'type'       => 'button',
											'icon'       => 'download',
											'classes'    => array( 'wsscd-download-logs-btn' ),
											'attributes' => array( 'style' => 'margin-left: 4px;' ),
										)
									);
									?>
								</div>

								<div id="wsscd-log-viewer-modal" style="display:none; margin-top: 15px;">
									<textarea readonly class="large-text code" rows="20" style="font-family: monospace; font-size: 12px;"></textarea>
									<div style="margin-top: 10px;">
										<?php
										WSSCD_Button_Helper::secondary(
											__( 'Copy to Clipboard', 'smart-cycle-discounts' ),
											array(
												'type'    => 'button',
												'icon'    => 'clipboard',
												'classes' => array( 'wsscd-copy-log-btn' ),
											)
										);
										?>
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
							<?php
							WSSCD_Button_Helper::secondary(
								__( 'Clear Log', 'smart-cycle-discounts' ),
								array(
									'type'    => 'button',
									'icon'    => 'trash',
									'classes' => array( 'wsscd-clear-logs-btn' ),
								)
							);
							?>
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
		<div class="wsscd-tools-section">
			<h2>
				<?php WSSCD_Icon_Helper::render( 'admin-site', array( 'size' => 16 ) ); ?>
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
							<?php
							WSSCD_Button_Helper::secondary(
								__( 'Run Health Check', 'smart-cycle-discounts' ),
								array(
									'type'    => 'button',
									'icon'    => 'heart',
									'classes' => array( 'wsscd-health-check-btn' ),
								)
							);
							?>
							<div id="wsscd-health-check-results" style="margin-top: 10px;"></div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'System Report', 'smart-cycle-discounts' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Generate a detailed system report for troubleshooting and support purposes. This report can be shared with support to help diagnose issues.', 'smart-cycle-discounts' ); ?>
							</p>
							<?php
							WSSCD_Button_Helper::secondary(
								__( 'Generate Report', 'smart-cycle-discounts' ),
								array(
									'type'       => 'button',
									'icon'       => 'media-text',
									'classes'    => array( 'wsscd-generate-report-btn' ),
									'attributes' => array( 'data-action' => 'generate' ),
								)
							);

							WSSCD_Button_Helper::secondary(
								__( 'Copy to Clipboard', 'smart-cycle-discounts' ),
								array(
									'type'       => 'button',
									'icon'       => 'clipboard',
									'classes'    => array( 'wsscd-copy-report-btn' ),
									'attributes' => array(
										'data-action' => 'copy',
										'style'       => 'display:none;',
									),
								)
							);

							WSSCD_Button_Helper::secondary(
								__( 'Download Report', 'smart-cycle-discounts' ),
								array(
									'type'       => 'button',
									'icon'       => 'download',
									'classes'    => array( 'wsscd-download-report-btn' ),
									'attributes' => array(
										'data-action' => 'download',
										'style'       => 'display:none;',
									),
								)
							);
							?>
							<div id="wsscd-system-report" style="margin-top: 10px; display: none;">
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
