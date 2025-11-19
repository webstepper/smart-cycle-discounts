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
$health_icon_name = 'success' === $health_status ? 'check' : ( 'warning' === $health_status ? 'warning' : 'close' );
$health_class = 'scd-health-' . $health_status;
$quick_stats = isset( $campaign_health['quick_stats'] ) ? $campaign_health['quick_stats'] : array( 'total_analyzed' => 0, 'issues_count' => 0, 'warnings_count' => 0 );
$categories = isset( $campaign_health['categories'] ) ? $campaign_health['categories'] : array();
?>

<div class="scd-dashboard-section scd-campaign-health-widget <?php echo esc_attr( $health_class ); ?>" id="scd-health-widget">
	<div class="scd-section-header">
		<div class="scd-section-header-content">
			<div class="scd-section-header-icon">
				<?php echo SCD_Icon_Helper::get( $health_icon_name, array( 'size' => 16 ) ); ?>
			</div>
			<div class="scd-section-header-text">
				<h2>
					<?php
					if ( 'success' === $health_status ) {
						esc_html_e( 'Campaign Health: All Systems Running Smoothly', 'smart-cycle-discounts' );
					} elseif ( 'warning' === $health_status ) {
						esc_html_e( 'Campaign Health: Needs Attention', 'smart-cycle-discounts' );
					} else {
						esc_html_e( 'Campaign Health: Critical Issues', 'smart-cycle-discounts' );
					}
					?>
				</h2>
				<p>
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
				</p>
			</div>
		</div>
	</div>
	<div class="scd-section-content">
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
							<?php echo SCD_Icon_Helper::get( $category_icon, array( 'size' => 16 ) ); ?>
						</div>
						<div class="scd-health-category-content">
							<div class="scd-health-category-label"><?php echo esc_html( $category_label ); ?></div>
							<div class="scd-health-category-status">
								<?php if ( 'healthy' === $category_status ) : ?>
									<?php echo SCD_Badge_Helper::health_badge( 'healthy', __( 'Healthy', 'smart-cycle-discounts' ) ); ?>
								<?php elseif ( 'warning' === $category_status ) : ?>
									<?php echo SCD_Badge_Helper::health_badge( 'warning', sprintf( _n( '%d warning', '%d warnings', $category_count, 'smart-cycle-discounts' ), $category_count ) ); ?>
								<?php else : ?>
									<?php echo SCD_Badge_Helper::health_badge( 'alert', sprintf( _n( '%d issue', '%d issues', $category_count, 'smart-cycle-discounts' ), $category_count ) ); ?>
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
					<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'All campaigns are configured correctly and running as expected. Great work!', 'smart-cycle-discounts' ); ?>
				</p>
				<?php if ( ! empty( $campaign_health['success_messages'] ) ) : ?>
					<div class="scd-health-details">
						<?php foreach ( $campaign_health['success_messages'] as $message ) : ?>
							<div class="scd-health-detail-item">
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
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
					<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?>
				</div>
				<?php foreach ( $campaign_health['issues'] as $issue ) : ?>
					<div class="scd-health-item scd-health-critical">
						<div class="scd-health-item-content">
							<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
							<span><?php echo esc_html( $issue['message'] ); ?></span>
						</div>
						<?php if ( isset( $issue['campaign_id'] ) ) : ?>
							<?php
							SCD_Button_Helper::link(
								__( 'Fix Now', 'smart-cycle-discounts' ),
								admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $issue['campaign_id'] ),
								array( 'size' => 'small' )
							);
							?>
						<?php elseif ( 'limit_reached' === $issue['type'] ) : ?>
							<?php
							SCD_Button_Helper::primary(
								__( 'Upgrade', 'smart-cycle-discounts' ),
								array(
									'size' => 'small',
									'href' => esc_url( $feature_gate->get_upgrade_url() ),
								)
							);
							?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Warnings -->
		<?php if ( ! empty( $campaign_health['warnings'] ) ) : ?>
			<div class="scd-health-warnings">
				<div class="scd-health-section-title">
					<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Warnings', 'smart-cycle-discounts' ); ?>
				</div>
				<?php foreach ( $campaign_health['warnings'] as $warning ) : ?>
					<div class="scd-health-item scd-health-warning">
						<div class="scd-health-item-content">
							<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
							<span><?php echo esc_html( $warning['message'] ); ?></span>
						</div>
						<?php if ( isset( $warning['campaign_id'] ) && in_array( $warning['type'], array( 'ending_soon', 'scheduled_past' ), true ) ) : ?>
							<?php
							SCD_Button_Helper::link(
								__( 'Review', 'smart-cycle-discounts' ),
								admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $warning['campaign_id'] ),
								array( 'size' => 'small' )
							);
							?>
						<?php elseif ( 'approaching_limit' === $warning['type'] ) : ?>
							<?php
							SCD_Button_Helper::secondary(
								__( 'Learn More', 'smart-cycle-discounts' ),
								array(
									'size' => 'small',
									'href' => esc_url( $feature_gate->get_upgrade_url() ),
								)
							);
							?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		</div>
	</div>
</div>
