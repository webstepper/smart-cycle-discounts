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
 * @var WSSCD_Feature_Gate             $feature_gate             Feature gate instance
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prepare health widget variables
$health_status = $campaign_health['status'];
$quick_stats = isset( $campaign_health['quick_stats'] ) ? $campaign_health['quick_stats'] : array( 'total_analyzed' => 0, 'issues_count' => 0, 'warnings_count' => 0 );
$categories = isset( $campaign_health['categories'] ) ? $campaign_health['categories'] : array();

// Map status to icon and CSS class (consistent with Campaign List, Overview Panel, Wizard)
$status_config = array(
	'excellent' => array( 'icon' => 'yes-alt', 'class' => 'excellent' ),
	'good'      => array( 'icon' => 'yes', 'class' => 'good' ),
	'fair'      => array( 'icon' => 'info', 'class' => 'fair' ),
	'poor'      => array( 'icon' => 'warning', 'class' => 'poor' ),
	'critical'  => array( 'icon' => 'dismiss', 'class' => 'critical' ),
);
$current_config = isset( $status_config[ $health_status ] ) ? $status_config[ $health_status ] : $status_config['fair'];
$health_icon_name = $current_config['icon'];
$health_class = 'wsscd-health-' . $current_config['class'];
?>

<div class="wsscd-dashboard-section wsscd-campaign-health-widget <?php echo esc_attr( $health_class ); ?>" id="wsscd-health-widget">
	<div class="wsscd-section-header">
		<div class="wsscd-section-header-content">
			<div class="wsscd-section-header-icon">
				<?php WSSCD_Icon_Helper::render( $health_icon_name, array( 'size' => 20 ) ); ?>
			</div>
			<div class="wsscd-section-header-text">
				<h2>
					<?php
					if ( 0 === $total_campaigns ) {
						esc_html_e( 'Campaign Health: No Campaigns Yet', 'smart-cycle-discounts' );
					} elseif ( 'excellent' === $health_status ) {
						esc_html_e( 'Campaign Health: Excellent', 'smart-cycle-discounts' );
					} elseif ( 'good' === $health_status ) {
						esc_html_e( 'Campaign Health: Good', 'smart-cycle-discounts' );
					} elseif ( 'fair' === $health_status ) {
						esc_html_e( 'Campaign Health: Fair', 'smart-cycle-discounts' );
					} elseif ( 'poor' === $health_status ) {
						esc_html_e( 'Campaign Health: Needs Attention', 'smart-cycle-discounts' );
					} else {
						esc_html_e( 'Campaign Health: Critical Issues', 'smart-cycle-discounts' );
					}
					?>
				</h2>
			</div>
		</div>
		<p class="wsscd-section-header-description">
			<?php if ( 0 === $total_campaigns ) : ?>
				<?php esc_html_e( 'Create your first campaign to start tracking health metrics.', 'smart-cycle-discounts' ); ?>
			<?php else : ?>
				<?php
				// Build status breakdown parts.
				$status_parts = array();
				$active_count = isset( $quick_stats['active_count'] ) ? $quick_stats['active_count'] : 0;
				$scheduled_count = isset( $quick_stats['scheduled_count'] ) ? $quick_stats['scheduled_count'] : 0;
				$paused_count = isset( $quick_stats['paused_count'] ) ? $quick_stats['paused_count'] : 0;

				if ( $active_count > 0 ) {
					$status_parts[] = sprintf(
						/* translators: %d: number of active campaigns */
						_n( '%d active', '%d active', $active_count, 'smart-cycle-discounts' ),
						$active_count
					);
				}
				if ( $scheduled_count > 0 ) {
					$status_parts[] = sprintf(
						/* translators: %d: number of scheduled campaigns */
						_n( '%d scheduled', '%d scheduled', $scheduled_count, 'smart-cycle-discounts' ),
						$scheduled_count
					);
				}
				if ( $paused_count > 0 ) {
					$status_parts[] = sprintf(
						/* translators: %d: number of paused campaigns */
						_n( '%d paused', '%d paused', $paused_count, 'smart-cycle-discounts' ),
						$paused_count
					);
				}

				echo esc_html(
					sprintf(
						/* translators: %d: number of campaigns analyzed */
						_n( '%d campaign analyzed', '%d campaigns analyzed', $quick_stats['total_analyzed'], 'smart-cycle-discounts' ),
						$quick_stats['total_analyzed']
					)
				);

				// Show status breakdown after bullet.
				if ( ! empty( $status_parts ) ) :
					?>
					<span class="wsscd-health-divider">•</span>
					<span class="wsscd-health-status-breakdown"><?php echo esc_html( implode( ', ', $status_parts ) ); ?></span>
				<?php endif; ?>
				<br>
				<span class="wsscd-health-quick-stats">
					<span class="wsscd-health-stat-critical"><?php echo esc_html( $quick_stats['issues_count'] ); ?> <?php esc_html_e( 'critical', 'smart-cycle-discounts' ); ?></span>
					<span class="wsscd-health-divider">•</span>
					<span class="wsscd-health-stat-warning"><?php echo esc_html( $quick_stats['warnings_count'] ); ?> <?php esc_html_e( 'warnings', 'smart-cycle-discounts' ); ?></span>
				</span>
			<?php endif; ?>
		</p>
	</div>
	<div class="wsscd-section-content">
		<div class="wsscd-health-content">
		<!-- Quick Health Categories -->
		<?php if ( ! empty( $categories ) && $quick_stats['total_analyzed'] > 0 ) : ?>
			<div class="wsscd-health-categories">
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
					<div class="wsscd-health-category wsscd-health-category-<?php echo esc_attr( $category_status ); ?>">
						<div class="wsscd-health-category-icon">
							<?php WSSCD_Icon_Helper::render( $category_icon, array( 'size' => 16 ) ); ?>
						</div>
						<div class="wsscd-health-category-content">
							<div class="wsscd-health-category-label"><?php echo esc_html( $category_label ); ?></div>
							<div class="wsscd-health-category-status">
								<?php if ( 'healthy' === $category_status ) : ?>
									<?php echo wp_kses_post( WSSCD_Badge_Helper::health_badge( 'healthy', __( 'Healthy', 'smart-cycle-discounts' ) ) ); ?>
								<?php elseif ( 'warning' === $category_status ) : ?>
									<?php /* translators: %d: number of warnings */ ?>
									<?php echo wp_kses_post( WSSCD_Badge_Helper::health_badge( 'warning', sprintf( _n( '%d warning', '%d warnings', $category_count, 'smart-cycle-discounts' ), $category_count ) ) ); ?>
								<?php else : ?>
									<?php /* translators: %d: number of issues */ ?>
									<?php echo wp_kses_post( WSSCD_Badge_Helper::health_badge( 'alert', sprintf( _n( '%d issue', '%d issues', $category_count, 'smart-cycle-discounts' ), $category_count ) ) ); ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Success State (excellent or good with no issues) -->
		<?php if ( in_array( $health_status, array( 'excellent', 'good' ), true ) && empty( $campaign_health['issues'] ) && empty( $campaign_health['warnings'] ) ) : ?>
			<?php if ( 0 === $total_campaigns ) : ?>
				<p class="wsscd-health-empty-message">
					<?php esc_html_e( 'You don\'t have any campaigns yet. Create your first campaign to start tracking its health.', 'smart-cycle-discounts' ); ?>
				</p>
			<?php else : ?>
				<p class="wsscd-health-success-message">
					<?php WSSCD_Icon_Helper::render( 'yes-alt', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'All campaigns are configured correctly and running as expected.', 'smart-cycle-discounts' ); ?>
				</p>
				<?php if ( ! empty( $campaign_health['success_messages'] ) ) : ?>
					<div class="wsscd-health-details">
						<?php foreach ( $campaign_health['success_messages'] as $message ) : ?>
							<div class="wsscd-health-detail-item">
								<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 16 ) ); ?>
								<span><?php echo esc_html( $message ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Critical Issues -->
		<?php if ( ! empty( $campaign_health['issues'] ) ) : ?>
			<div class="wsscd-health-issues">
				<div class="wsscd-health-section-title">
					<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?>
				</div>
				<?php foreach ( $campaign_health['issues'] as $issue ) : ?>
					<div class="wsscd-health-item wsscd-health-critical">
						<div class="wsscd-health-item-content">
							<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) ); ?>
							<span><?php echo esc_html( $issue['message'] ); ?></span>
						</div>
						<?php if ( isset( $issue['campaign_id'] ) ) : ?>
							<?php
							WSSCD_Button_Helper::link(
								__( 'Fix Now', 'smart-cycle-discounts' ),
								admin_url( 'admin.php?page=wsscd-campaigns&action=edit&id=' . $issue['campaign_id'] ),
								array( 'size' => 'small' )
							);
							?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Warnings -->
		<?php if ( ! empty( $campaign_health['warnings'] ) ) : ?>
			<div class="wsscd-health-warnings">
				<div class="wsscd-health-section-title">
					<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Warnings', 'smart-cycle-discounts' ); ?>
				</div>
				<?php foreach ( $campaign_health['warnings'] as $warning ) : ?>
					<div class="wsscd-health-item wsscd-health-warning">
						<div class="wsscd-health-item-content">
							<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
							<span><?php echo esc_html( $warning['message'] ); ?></span>
						</div>
						<?php if ( isset( $warning['campaign_id'] ) && in_array( $warning['type'], array( 'ending_soon', 'scheduled_past' ), true ) ) : ?>
							<?php
							WSSCD_Button_Helper::link(
								__( 'Review', 'smart-cycle-discounts' ),
								admin_url( 'admin.php?page=wsscd-campaigns&action=edit&id=' . $warning['campaign_id'] ),
								array( 'size' => 'small' )
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
