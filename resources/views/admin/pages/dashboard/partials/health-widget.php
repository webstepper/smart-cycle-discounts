<?php
/**
 * Dashboard Partial: Campaign Health Widget
 *
 * Displays campaign health status with quick stats and issue breakdown.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/pages/dashboard/partials
 *
 * @var array                        $campaign_health          Campaign health data
 * @var int                          $total_campaigns          Total campaigns count
 * @var SCD_Feature_Gate             $feature_gate             Feature gate instance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prepare health widget variables
$health_status = $campaign_health['status'];
$health_icon = 'success' === $health_status ? 'yes-alt' : ( 'warning' === $health_status ? 'warning' : 'dismiss' );
$health_class = 'scd-health-' . $health_status;
$quick_stats = isset( $campaign_health['quick_stats'] ) ? $campaign_health['quick_stats'] : array( 'total_analyzed' => 0, 'issues_count' => 0, 'warnings_count' => 0 );
$categories = isset( $campaign_health['categories'] ) ? $campaign_health['categories'] : array();
?>

<div class="scd-campaign-health-widget <?php echo esc_attr( $health_class ); ?>" id="scd-health-widget">
	<div class="scd-health-header">
		<div class="scd-health-header-left">
			<div class="scd-health-icon">
				<span class="dashicons dashicons-<?php echo esc_attr( $health_icon ); ?>"></span>
			</div>
			<div class="scd-health-title">
				<h3>
					<?php
					if ( 'success' === $health_status ) {
						esc_html_e( 'Campaign Health: All Systems Running Smoothly', 'smart-cycle-discounts' );
					} elseif ( 'warning' === $health_status ) {
						esc_html_e( 'Campaign Health: Needs Attention', 'smart-cycle-discounts' );
					} else {
						esc_html_e( 'Campaign Health: Critical Issues', 'smart-cycle-discounts' );
					}
					?>
				</h3>
				<div class="scd-health-subtitle">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of campaigns analyzed */
							_n( '%d campaign analyzed', '%d campaigns analyzed', $quick_stats['total_analyzed'], 'smart-cycle-discounts' ),
							$quick_stats['total_analyzed']
						)
					);
					?>
					<span class="scd-health-divider">•</span>
					<span class="scd-health-quick-stats">
						<span class="scd-health-stat-critical"><?php echo esc_html( $quick_stats['issues_count'] ); ?> <?php esc_html_e( 'critical', 'smart-cycle-discounts' ); ?></span>
						<span class="scd-health-divider">•</span>
						<span class="scd-health-stat-warning"><?php echo esc_html( $quick_stats['warnings_count'] ); ?> <?php esc_html_e( 'warnings', 'smart-cycle-discounts' ); ?></span>
					</span>
				</div>
			</div>
		</div>
	</div>

	<div class="scd-health-content">
		<!-- Quick Health Categories -->
		<?php if ( ! empty( $categories ) && $quick_stats['total_analyzed'] > 0 ) : ?>
			<div class="scd-health-categories">
				<?php foreach ( $categories as $category_key => $category_data ) : ?>
					<?php
					$category_status = $category_data['status'];
					$category_count = $category_data['count'];
					$category_icon = '';
					$category_label = '';

					switch ( $category_key ) {
						case 'configuration':
							$category_icon = 'admin-settings';
							$category_label = __( 'Configuration', 'smart-cycle-discounts' );
							break;
						case 'coverage':
							$category_icon = 'visibility';
							$category_label = __( 'Coverage', 'smart-cycle-discounts' );
							break;
						case 'schedule':
							$category_icon = 'calendar-alt';
							$category_label = __( 'Schedule', 'smart-cycle-discounts' );
							break;
						case 'discount':
							$category_icon = 'tag';
							$category_label = __( 'Discount', 'smart-cycle-discounts' );
							break;
						case 'stock':
							$category_icon = 'products';
							$category_label = __( 'Stock', 'smart-cycle-discounts' );
							break;
						case 'conflicts':
							$category_icon = 'randomize';
							$category_label = __( 'Conflicts', 'smart-cycle-discounts' );
							break;
					}
					?>
					<div class="scd-health-category scd-health-category-<?php echo esc_attr( $category_status ); ?>">
						<div class="scd-health-category-icon">
							<span class="dashicons dashicons-<?php echo esc_attr( $category_icon ); ?>"></span>
						</div>
						<div class="scd-health-category-content">
							<div class="scd-health-category-label"><?php echo esc_html( $category_label ); ?></div>
							<div class="scd-health-category-status">
								<?php if ( 'healthy' === $category_status ) : ?>
									<span class="scd-badge scd-badge-success"><?php esc_html_e( 'Healthy', 'smart-cycle-discounts' ); ?></span>
								<?php elseif ( 'warning' === $category_status ) : ?>
									<span class="scd-badge scd-badge-warning">
										<?php echo esc_html( sprintf( _n( '%d warning', '%d warnings', $category_count, 'smart-cycle-discounts' ), $category_count ) ); ?>
									</span>
								<?php else : ?>
									<span class="scd-badge scd-badge-critical">
										<?php echo esc_html( sprintf( _n( '%d issue', '%d issues', $category_count, 'smart-cycle-discounts' ), $category_count ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Success State -->
		<?php if ( 'success' === $health_status && empty( $campaign_health['issues'] ) && empty( $campaign_health['warnings'] ) ) : ?>
			<?php if ( 0 === $total_campaigns ) : ?>
				<p class="scd-health-empty-message">
					<?php esc_html_e( 'You don\'t have any campaigns yet. Create your first campaign to start tracking its health.', 'smart-cycle-discounts' ); ?>
				</p>
			<?php else : ?>
				<p class="scd-health-success-message">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'All campaigns are configured correctly and running as expected. Great work!', 'smart-cycle-discounts' ); ?>
				</p>
				<?php if ( ! empty( $campaign_health['success_messages'] ) ) : ?>
					<div class="scd-health-details">
						<?php foreach ( $campaign_health['success_messages'] as $message ) : ?>
							<div class="scd-health-detail-item">
								<span class="dashicons dashicons-yes"></span>
								<span><?php echo esc_html( $message ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Critical Issues -->
		<?php if ( ! empty( $campaign_health['issues'] ) ) : ?>
			<div class="scd-health-issues">
				<div class="scd-health-section-title">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?>
				</div>
				<?php foreach ( $campaign_health['issues'] as $issue ) : ?>
					<div class="scd-health-item scd-health-critical">
						<div class="scd-health-item-content">
							<span class="dashicons dashicons-warning"></span>
							<span><?php echo esc_html( $issue['message'] ); ?></span>
						</div>
						<?php if ( isset( $issue['campaign_id'] ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $issue['campaign_id'] ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Fix Now', 'smart-cycle-discounts' ); ?>
							</a>
						<?php elseif ( 'limit_reached' === $issue['type'] ) : ?>
							<a href="<?php echo esc_url( $feature_gate->get_upgrade_url() ); ?>" class="button button-primary button-small">
								<?php esc_html_e( 'Upgrade', 'smart-cycle-discounts' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Warnings -->
		<?php if ( ! empty( $campaign_health['warnings'] ) ) : ?>
			<div class="scd-health-warnings">
				<div class="scd-health-section-title">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Warnings', 'smart-cycle-discounts' ); ?>
				</div>
				<?php foreach ( $campaign_health['warnings'] as $warning ) : ?>
					<div class="scd-health-item scd-health-warning">
						<div class="scd-health-item-content">
							<span class="dashicons dashicons-info"></span>
							<span><?php echo esc_html( $warning['message'] ); ?></span>
						</div>
						<?php if ( isset( $warning['campaign_id'] ) && in_array( $warning['type'], array( 'ending_soon', 'scheduled_past' ), true ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $warning['campaign_id'] ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Review', 'smart-cycle-discounts' ); ?>
							</a>
						<?php elseif ( 'approaching_limit' === $warning['type'] ) : ?>
							<a href="<?php echo esc_url( $feature_gate->get_upgrade_url() ); ?>" class="button button-secondary button-small">
								<?php esc_html_e( 'Learn More', 'smart-cycle-discounts' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
