<?php
/**
 * Main Dashboard View (Refactored)
 *
 * Streamlined dashboard with clear visual hierarchy and action-oriented design.
 * Displays: health alerts, hero metrics, campaign suggestions, campaign overview, and quick actions.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/pages/dashboard
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Dashboard Template
 *
 * @var array                        $metrics                  Dashboard metrics (includes pre-calculated trends)
 * @var array                        $campaign_stats           Campaign status breakdown
 * @var array                        $top_campaigns            Top campaigns by revenue
 * @var array                        $campaign_health          Campaign health data
 * @var array                        $campaign_suggestions     Campaign suggestions
 * @var bool                         $is_premium               Premium status
 * @var int                          $campaign_limit           Campaign limit
 * @var SCD_Feature_Gate             $feature_gate             Feature gate instance
 * @var SCD_Upgrade_Prompt_Manager   $upgrade_prompt_manager   Upgrade prompt manager
 */

// Default values
$metrics = $metrics ?? array();
$campaign_stats = $campaign_stats ?? array();
$top_campaigns = $top_campaigns ?? array();
$campaign_health = $campaign_health ?? array( 'status' => 'success', 'issues' => array(), 'warnings' => array(), 'success_messages' => array() );
$campaign_suggestions = $campaign_suggestions ?? array();
$is_premium = $is_premium ?? false;
$campaign_limit = $campaign_limit ?? 3;

// Extract pre-calculated trends from metrics (calculated by analytics dashboard)
$revenue_trend = $metrics['revenue_change'] ?? 0;
$conversions_trend = $metrics['conversions_change'] ?? 0;
$ctr_trend = $metrics['ctr_change'] ?? 0;

// Determine dashboard state for dynamic CTAs
$total_campaigns = $campaign_stats['total'] ?? 0;
$active_campaigns = $campaign_stats['active'] ?? 0;
$has_critical_issues = 'critical' === $campaign_health['status'];
$has_warnings = 'warning' === $campaign_health['status'];
$approaching_limit = ! $is_premium && 0 !== $campaign_limit && $active_campaigns >= ( $campaign_limit * 0.67 );
?>

<div class="wrap scd-main-dashboard">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Dashboard', 'smart-cycle-discounts' ); ?>
	</h1>

	<?php if ( $is_premium ) : ?>
		<span class="scd-pro-badge-header"><?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?></span>
	<?php endif; ?>

	<hr class="wp-header-end">

	<?php
	// 1. SMART CAMPAIGN HEALTH WIDGET (Always Visible - Basic + Full Analysis)
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

	<?php
	// 2. UPGRADE BANNER (Compact, Dismissable)
	if ( ! $is_premium ) :
		// Base upgrade arguments
		$upgrade_args = array(
			'banner_id' => 'dashboard_analytics',
			'title' => __( 'Unlock Advanced Analytics with Pro', 'smart-cycle-discounts' ),
			'message' => __( 'Get detailed insights with custom date ranges, trend analysis, campaign comparisons, revenue attribution, and exportable reports. See exactly which campaigns drive the most revenue.', 'smart-cycle-discounts' ),
			'bypass_limit' => true, // Dashboard banner doesn't count toward daily limit
		);

		// Merge promotional settings (if active)
		$promotion_settings = $upgrade_prompt_manager->get_promotion_settings();
		$upgrade_args = array_merge( $upgrade_args, $promotion_settings );

		echo wp_kses_post( $upgrade_prompt_manager->get_upgrade_prompt( 'Advanced Analytics', 'banner', $upgrade_args ) );
	endif;
	?>

	<?php
	// 3. CAMPAIGN SUGGESTIONS (Actionable Ideas)
	if ( ! empty( $campaign_suggestions ) ) :
	?>

	<div class="scd-campaign-suggestions dashboard-section">
		<div class="scd-suggestions-header">
			<div class="scd-suggestions-header-content">
				<div class="scd-suggestions-header-icon">
					<span class="dashicons dashicons-lightbulb"></span>
				</div>
				<div class="scd-suggestions-header-text">
					<h2><?php esc_html_e( 'Campaign Ideas for You', 'smart-cycle-discounts' ); ?></h2>
					<p><?php esc_html_e( 'Smart suggestions shown at optimal creation times based on industry data', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
		</div>
		<div class="scd-suggestions-layout">
			<?php foreach ( $campaign_suggestions as $suggestion ) : ?>
				<div class="scd-suggestion-card scd-suggestion-<?php echo esc_attr( $suggestion['category'] ); ?>">
					<!-- Column 1: Campaign Details -->
					<div class="scd-suggestion-col-1">
						<div class="scd-column-header">
							<h4><?php echo esc_html( $suggestion['name'] ); ?> <?php echo esc_html( $suggestion['icon'] ); ?></h4>
							<?php if ( $suggestion['days_left_in_window'] <= 3 ) : ?>
								<span class="scd-suggestion-badge scd-badge-urgent">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Urgent', 'smart-cycle-discounts' ); ?>
								</span>
							<?php elseif ( 'major' === $suggestion['category'] ) : ?>
								<span class="scd-suggestion-badge scd-badge-major">
									<span class="dashicons dashicons-star-filled"></span>
									<?php esc_html_e( 'Major Event', 'smart-cycle-discounts' ); ?>
								</span>
							<?php endif; ?>
						</div>

						<div class="scd-suggestion-timing">
							<span class="dashicons dashicons-clock"></span>
							<strong><?php echo esc_html( $suggestion['timing_message'] ); ?></strong>
						</div>

						<div class="scd-suggestion-details-list">
							<div class="scd-detail-item">
								<span class="dashicons dashicons-calendar"></span>
								<span>
									<?php
									/* translators: 1: start date, 2: end date */
									echo esc_html( sprintf( __( '%1$s - %2$s', 'smart-cycle-discounts' ), gmdate( 'M j', strtotime( $suggestion['start_date'] ) ), gmdate( 'M j, Y', strtotime( $suggestion['end_date'] ) ) ) );
									?>
								</span>
							</div>

							<div class="scd-detail-item">
								<span class="dashicons dashicons-tag"></span>
								<span><strong><?php echo esc_html( $suggestion['suggested_discount'] ); ?></strong> discount</span>
							</div>

							<div class="scd-detail-item scd-detail-countdown">
								<div class="scd-countdown-number"><?php echo esc_html( $suggestion['days_until'] ); ?></div>
								<div class="scd-countdown-label">
									<?php echo esc_html( _n( 'Day Away', 'Days Away', $suggestion['days_until'], 'smart-cycle-discounts' ) ); ?>
								</div>
							</div>
						</div>

						<p class="scd-suggestion-description"><?php echo esc_html( $suggestion['description'] ); ?></p>

						<div class="scd-suggestion-action">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=new&suggestion=' . $suggestion['id'] ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Create Campaign', 'smart-cycle-discounts' ); ?> →
							</a>
						</div>
					</div>

					<!-- Column 2: Recommendations -->
					<div class="scd-suggestion-col-2">
						<div class="scd-column-header">
							<h5><?php esc_html_e( 'Recommendations', 'smart-cycle-discounts' ); ?></h5>
						</div>

						<?php if ( ! empty( $suggestion['recommendations'] ) ) : ?>
							<ul class="scd-recommendations-list">
								<?php foreach ( $suggestion['recommendations'] as $recommendation ) : ?>
									<li>
										<span class="dashicons dashicons-yes"></span>
										<?php echo esc_html( $recommendation ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="scd-no-recommendations"><?php esc_html_e( 'Start planning your campaign strategy', 'smart-cycle-discounts' ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Column 3: Insights (3 Random Cards) -->
					<div class="scd-suggestion-col-3">
						<div class="scd-column-header">
							<h5><?php esc_html_e( 'Insights', 'smart-cycle-discounts' ); ?></h5>
						</div>

						<?php if ( isset( $suggestion['random_statistic'] ) ) : ?>
							<div class="scd-insight-card scd-insight-statistic">
								<div class="scd-insight-icon">
									<span class="dashicons dashicons-chart-bar"></span>
								</div>
								<div class="scd-insight-content">
									<div class="scd-insight-value"><?php echo esc_html( $suggestion['random_statistic']['value'] ); ?></div>
									<div class="scd-insight-label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $suggestion['random_statistic']['label'] ) ) ); ?></div>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( isset( $suggestion['random_tip'] ) ) : ?>
							<div class="scd-insight-card scd-insight-tip">
								<div class="scd-insight-icon">
									<span class="dashicons dashicons-lightbulb"></span>
								</div>
								<div class="scd-insight-content">
									<div class="scd-insight-title"><?php esc_html_e( 'Pro Tip', 'smart-cycle-discounts' ); ?></div>
									<div class="scd-insight-text"><?php echo esc_html( $suggestion['random_tip'] ); ?></div>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( isset( $suggestion['random_best_practice'] ) ) : ?>
							<div class="scd-insight-card scd-insight-practice">
								<div class="scd-insight-icon">
									<span class="dashicons dashicons-yes-alt"></span>
								</div>
								<div class="scd-insight-content">
									<div class="scd-insight-title"><?php esc_html_e( 'Best Practice', 'smart-cycle-discounts' ); ?></div>
									<div class="scd-insight-text"><?php echo esc_html( $suggestion['random_best_practice'] ); ?></div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php endif; ?>

	<?php
	// 4. CAMPAIGN PERFORMANCE & OVERVIEW (Performance + Status + Campaigns + Timeline)
	if ( 0 === $total_campaigns ) :
		// Empty state: No campaigns at all
		?>
		<div class="scd-dashboard-section scd-campaign-overview-empty">
			<div class="scd-empty-state">
				<span class="dashicons dashicons-megaphone"></span>
				<h3><?php esc_html_e( 'Ready to Get Started?', 'smart-cycle-discounts' ); ?></h3>
				<p><?php esc_html_e( 'Create your first discount campaign in 3 simple steps: choose products, set discount amount, and schedule dates.', 'smart-cycle-discounts' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=new' ) ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Create Your First Campaign', 'smart-cycle-discounts' ); ?>
				</a>
			</div>
		</div>
		<?php
	else :
		// Campaign overview with enhanced status visualization and campaign list

		// Calculate status percentages for visual distribution
		$active_count = isset( $campaign_stats['active'] ) ? $campaign_stats['active'] : 0;
		$scheduled_count = isset( $campaign_stats['scheduled'] ) ? $campaign_stats['scheduled'] : 0;
		$paused_count = isset( $campaign_stats['paused'] ) ? $campaign_stats['paused'] : 0;
		$draft_count = isset( $campaign_stats['draft'] ) ? $campaign_stats['draft'] : 0;
		$expired_count = isset( $campaign_stats['expired'] ) ? $campaign_stats['expired'] : 0;

		$active_percent = 0 < $total_campaigns ? ( $active_count / $total_campaigns ) * 100 : 0;
		$scheduled_percent = 0 < $total_campaigns ? ( $scheduled_count / $total_campaigns ) * 100 : 0;
		$paused_percent = 0 < $total_campaigns ? ( $paused_count / $total_campaigns ) * 100 : 0;
		$draft_percent = 0 < $total_campaigns ? ( $draft_count / $total_campaigns ) * 100 : 0;
		$expired_percent = 0 < $total_campaigns ? ( $expired_count / $total_campaigns ) * 100 : 0;
		?>
		<div class="scd-dashboard-section scd-campaign-overview">
			<div class="scd-section-header">
				<h2><?php esc_html_e( 'Campaign Performance & Overview', 'smart-cycle-discounts' ); ?></h2>
				<div class="scd-section-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=new' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Create Campaign', 'smart-cycle-discounts' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View All', 'smart-cycle-discounts' ); ?>
					</a>
				</div>
			</div>
			<div class="scd-section-content">
				<!-- Performance Summary -->
				<div class="scd-performance-summary">
					<h3 class="scd-subsection-title">
						<?php esc_html_e( 'Performance Summary', 'smart-cycle-discounts' ); ?>
						<span class="scd-subsection-meta">
							<?php
							if ( $is_premium ) {
								esc_html_e( '(Last 30 days)', 'smart-cycle-discounts' );
							} else {
								esc_html_e( '(Last 7 days)', 'smart-cycle-discounts' );
							}
							?>
						</span>
					</h3>
					<div class="scd-hero-stats">
						<div class="scd-stat-card">
							<div class="scd-stat-icon">
								<span class="dashicons dashicons-chart-line"></span>
							</div>
							<div class="scd-stat-content">
								<div class="scd-stat-label"><?php esc_html_e( 'Total Revenue', 'smart-cycle-discounts' ); ?></div>
								<div class="scd-stat-value" id="scd-stat-revenue">
									<?php echo wp_kses_post( isset( $metrics['revenue'] ) ? wc_price( $metrics['revenue'] ) : wc_price( 0 ) ); ?>
								</div>
								<div class="scd-stat-meta">
									<?php
									if ( $is_premium ) {
										esc_html_e( 'Last 30 days', 'smart-cycle-discounts' );
									} else {
										esc_html_e( 'Last 7 days', 'smart-cycle-discounts' );
									}
									?>
									<?php if ( $is_premium && 0 !== $revenue_trend ) : ?>
										<span class="scd-trend <?php echo 0 < $revenue_trend ? 'scd-trend-up' : 'scd-trend-down'; ?>">
											<?php echo 0 < $revenue_trend ? '↑' : '↓'; ?>
											<?php echo esc_html( number_format_i18n( abs( $revenue_trend ), 1 ) ); ?>%
										</span>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<div class="scd-stat-card">
							<div class="scd-stat-icon">
								<span class="dashicons dashicons-megaphone"></span>
							</div>
							<div class="scd-stat-content">
								<div class="scd-stat-label"><?php esc_html_e( 'Active Campaigns', 'smart-cycle-discounts' ); ?></div>
								<div class="scd-stat-value" id="scd-stat-active-campaigns">
									<?php echo esc_html( $active_campaigns ); ?>
								</div>
								<div class="scd-stat-meta">
									<?php
									if ( 0 === $campaign_limit ) {
										esc_html_e( 'Unlimited', 'smart-cycle-discounts' );
									} else {
										/* translators: 1: active campaigns, 2: campaign limit */
										echo esc_html( sprintf( __( '%1$d of %2$d', 'smart-cycle-discounts' ), $active_campaigns, $campaign_limit ) );
									}
									?>
								</div>
							</div>
						</div>

						<div class="scd-stat-card">
							<div class="scd-stat-icon">
								<span class="dashicons dashicons-cart"></span>
							</div>
							<div class="scd-stat-content">
								<div class="scd-stat-label"><?php esc_html_e( 'Conversions', 'smart-cycle-discounts' ); ?></div>
								<div class="scd-stat-value" id="scd-stat-conversions">
									<?php echo esc_html( isset( $metrics['conversions'] ) ? number_format_i18n( $metrics['conversions'] ) : 0 ); ?>
								</div>
								<div class="scd-stat-meta">
									<?php
									if ( $is_premium ) {
										esc_html_e( 'Last 30 days', 'smart-cycle-discounts' );
									} else {
										esc_html_e( 'Last 7 days', 'smart-cycle-discounts' );
									}
									?>
									<?php if ( $is_premium && 0 !== $conversions_trend ) : ?>
										<span class="scd-trend <?php echo 0 < $conversions_trend ? 'scd-trend-up' : 'scd-trend-down'; ?>">
											<?php echo 0 < $conversions_trend ? '↑' : '↓'; ?>
											<?php echo esc_html( number_format_i18n( abs( $conversions_trend ), 1 ) ); ?>%
										</span>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<div class="scd-stat-card">
							<div class="scd-stat-icon">
								<span class="dashicons dashicons-performance"></span>
							</div>
							<div class="scd-stat-content">
								<div class="scd-stat-label"><?php esc_html_e( 'Click Rate', 'smart-cycle-discounts' ); ?></div>
								<div class="scd-stat-value" id="scd-stat-ctr">
									<?php
									$ctr = 0;
									if ( isset( $metrics['clicks'] ) && isset( $metrics['impressions'] ) && $metrics['impressions'] > 0 ) {
										$ctr = ( $metrics['clicks'] / $metrics['impressions'] ) * 100;
									}
									echo esc_html( number_format_i18n( $ctr, 2 ) . '%' );
									?>
								</div>
								<div class="scd-stat-meta">
									<?php
									if ( $is_premium ) {
										esc_html_e( 'Last 30 days', 'smart-cycle-discounts' );
									} else {
										esc_html_e( 'Last 7 days', 'smart-cycle-discounts' );
									}
									?>
									<?php if ( $is_premium && 0 !== $ctr_trend ) : ?>
										<span class="scd-trend <?php echo 0 < $ctr_trend ? 'scd-trend-up' : 'scd-trend-down'; ?>">
											<?php echo 0 < $ctr_trend ? '↑' : '↓'; ?>
											<?php echo esc_html( number_format_i18n( abs( $ctr_trend ), 1 ) ); ?>%
										</span>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Status Distribution -->
				<div class="scd-status-summary">
					<div class="scd-status-header">
						<h3><?php esc_html_e( 'Status Distribution', 'smart-cycle-discounts' ); ?></h3>
						<div class="scd-status-total">
							<strong><?php echo esc_html( $total_campaigns ); ?></strong>
							<?php echo esc_html( _n( 'campaign', 'campaigns', $total_campaigns, 'smart-cycle-discounts' ) ); ?>
						</div>
					</div>

					<!-- Visual Status Bar -->
					<div class="scd-status-bar" role="img" aria-label="<?php esc_attr_e( 'Campaign status distribution', 'smart-cycle-discounts' ); ?>">
						<?php if ( $active_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-active" style="width: <?php echo esc_attr( $active_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Active: %d campaigns (%s%%)', 'smart-cycle-discounts' ), $active_count, number_format_i18n( $active_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $scheduled_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-scheduled" style="width: <?php echo esc_attr( $scheduled_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Scheduled: %d campaigns (%s%%)', 'smart-cycle-discounts' ), $scheduled_count, number_format_i18n( $scheduled_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $paused_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-paused" style="width: <?php echo esc_attr( $paused_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Paused: %d campaigns (%s%%)', 'smart-cycle-discounts' ), $paused_count, number_format_i18n( $paused_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $draft_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-draft" style="width: <?php echo esc_attr( $draft_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Draft: %d campaigns (%s%%)', 'smart-cycle-discounts' ), $draft_count, number_format_i18n( $draft_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $expired_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-expired" style="width: <?php echo esc_attr( $expired_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Expired: %d campaigns (%s%%)', 'smart-cycle-discounts' ), $expired_count, number_format_i18n( $expired_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
					</div>

					<!-- Status Legend with Counts -->
					<div class="scd-status-legend">
						<div class="scd-status-legend-item">
							<span class="scd-status-indicator scd-status-active"></span>
							<span class="scd-status-label"><?php esc_html_e( 'Active', 'smart-cycle-discounts' ); ?></span>
							<span class="scd-status-count"><?php echo esc_html( $active_count ); ?></span>
						</div>
						<div class="scd-status-legend-item">
							<span class="scd-status-indicator scd-status-scheduled"></span>
							<span class="scd-status-label"><?php esc_html_e( 'Scheduled', 'smart-cycle-discounts' ); ?></span>
							<span class="scd-status-count"><?php echo esc_html( $scheduled_count ); ?></span>
						</div>
						<div class="scd-status-legend-item">
							<span class="scd-status-indicator scd-status-paused"></span>
							<span class="scd-status-label"><?php esc_html_e( 'Paused', 'smart-cycle-discounts' ); ?></span>
							<span class="scd-status-count"><?php echo esc_html( $paused_count ); ?></span>
						</div>
						<div class="scd-status-legend-item">
							<span class="scd-status-indicator scd-status-draft"></span>
							<span class="scd-status-label"><?php esc_html_e( 'Draft', 'smart-cycle-discounts' ); ?></span>
							<span class="scd-status-count"><?php echo esc_html( $draft_count ); ?></span>
						</div>
						<div class="scd-status-legend-item">
							<span class="scd-status-indicator scd-status-expired"></span>
							<span class="scd-status-label"><?php esc_html_e( 'Expired', 'smart-cycle-discounts' ); ?></span>
							<span class="scd-status-count"><?php echo esc_html( $expired_count ); ?></span>
						</div>
					</div>
				</div>

				<?php
				// Get all campaigns and sort by urgency for display
				// Fetch campaigns using the repository
				global $wpdb;
				$table_name = $wpdb->prefix . 'scd_campaigns';
				$all_campaigns = $wpdb->get_results(
					"SELECT id, name, status, starts_at, ends_at, discount_type, discount_value
					FROM {$table_name}
					WHERE deleted_at IS NULL
					ORDER BY
						CASE
							WHEN status = 'active' AND ends_at IS NOT NULL AND ends_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1
							WHEN status = 'active' THEN 2
							WHEN status = 'scheduled' AND starts_at IS NOT NULL AND starts_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 3
							WHEN status = 'scheduled' THEN 4
							WHEN status = 'paused' THEN 5
							ELSE 6
						END,
						starts_at ASC
					LIMIT 5",
					ARRAY_A
				);

				// Merge performance data from top_campaigns if available
				$campaign_performance = array();
				if ( ! empty( $top_campaigns ) ) {
					foreach ( $top_campaigns as $top_campaign ) {
						if ( isset( $top_campaign['id'] ) ) {
							$campaign_performance[ $top_campaign['id'] ] = $top_campaign;
						}
					}
				}
				?>

				<!-- Campaigns List -->
				<?php if ( ! empty( $all_campaigns ) ) : ?>
					<div class="scd-campaigns-list-section">
						<h3 class="scd-subsection-title">
							<?php esc_html_e( 'Your Campaigns', 'smart-cycle-discounts' ); ?>
							<span class="scd-subsection-meta"><?php esc_html_e( '(Sorted by urgency)', 'smart-cycle-discounts' ); ?></span>
						</h3>
						<div class="scd-campaigns-grid">
							<?php foreach ( $all_campaigns as $campaign ) :
								$campaign_id = $campaign['id'];
								$performance = isset( $campaign_performance[ $campaign_id ] ) ? $campaign_performance[ $campaign_id ] : null;
								$has_performance = null !== $performance;

								// Calculate urgency
								$is_ending_soon = false;
								$is_starting_soon = false;
								$days_until_end = null;
								$days_until_start = null;
								$end_time_text = '';
								$start_time_text = '';

								if ( ! empty( $campaign['ends_at'] ) && 'active' === $campaign['status'] ) {
									// Create DateTime object from UTC database string (same as Campaign class)
									$end_date = new DateTime( $campaign['ends_at'], new DateTimeZone( 'UTC' ) );
									$now_date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

									$diff_seconds = $end_date->getTimestamp() - $now_date->getTimestamp();
									$days_until_end = max( 0, floor( $diff_seconds / DAY_IN_SECONDS ) );
									$is_ending_soon = 0 <= $days_until_end && 7 >= $days_until_end && 0 < $diff_seconds;

									// Calculate time text for display
									if ( 0 === $days_until_end && 0 < $diff_seconds ) {
										// Less than 1 day - show hours and minutes
										$hours = floor( $diff_seconds / HOUR_IN_SECONDS );
										$minutes = floor( ( $diff_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

										if ( 0 < $hours ) {
											$end_time_text = sprintf(
												/* translators: 1: hours, 2: minutes */
												_n( 'Ends in %1$d hour %2$d min', 'Ends in %1$d hours %2$d min', $hours, 'smart-cycle-discounts' ),
												$hours,
												$minutes
											);
										} else {
											$end_time_text = sprintf(
												/* translators: %d: minutes */
												_n( 'Ends in %d minute', 'Ends in %d minutes', $minutes, 'smart-cycle-discounts' ),
												$minutes
											);
										}
									} else {
										$end_time_text = sprintf(
											/* translators: %d: days */
											_n( 'Ends in %d day', 'Ends in %d days', $days_until_end, 'smart-cycle-discounts' ),
											$days_until_end
										);
									}
								}

								if ( ! empty( $campaign['starts_at'] ) && 'scheduled' === $campaign['status'] ) {
									// Create DateTime object from UTC database string (same as Campaign class)
									$start_date = new DateTime( $campaign['starts_at'], new DateTimeZone( 'UTC' ) );
									$now_date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

									$diff_seconds = $start_date->getTimestamp() - $now_date->getTimestamp();
									$days_until_start = max( 0, floor( $diff_seconds / DAY_IN_SECONDS ) );
									$is_starting_soon = 0 <= $days_until_start && 7 >= $days_until_start && 0 < $diff_seconds;

									// Calculate time text for display
									if ( 0 === $days_until_start && 0 < $diff_seconds ) {
										// Less than 1 day - show hours and minutes
										$hours = floor( $diff_seconds / HOUR_IN_SECONDS );
										$minutes = floor( ( $diff_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

										if ( 0 < $hours ) {
											$start_time_text = sprintf(
												/* translators: 1: hours, 2: minutes */
												_n( 'Starts in %1$d hour %2$d min', 'Starts in %1$d hours %2$d min', $hours, 'smart-cycle-discounts' ),
												$hours,
												$minutes
											);
										} else {
											$start_time_text = sprintf(
												/* translators: %d: minutes */
												_n( 'Starts in %d minute', 'Starts in %d minutes', $minutes, 'smart-cycle-discounts' ),
												$minutes
											);
										}
									} else {
										$start_time_text = sprintf(
											/* translators: %d: days */
											_n( 'Starts in %d day', 'Starts in %d days', $days_until_start, 'smart-cycle-discounts' ),
											$days_until_start
										);
									}
								}
								?>
								<div class="scd-campaign-card scd-campaign-status-<?php echo esc_attr( $campaign['status'] ); ?> <?php echo $is_ending_soon ? 'scd-campaign-urgent' : ''; ?>">
									<div class="scd-campaign-card-header">
										<div class="scd-campaign-card-title">
											<h4><?php echo esc_html( $campaign['name'] ); ?></h4>
											<span class="scd-campaign-status-badge scd-status-<?php echo esc_attr( $campaign['status'] ); ?>">
												<?php echo esc_html( ucfirst( $campaign['status'] ) ); ?>
											</span>
										</div>
										<?php if ( $is_ending_soon || $is_starting_soon ) : ?>
											<span class="scd-campaign-urgency-badge <?php echo $is_ending_soon ? 'scd-urgency-ending' : 'scd-urgency-starting'; ?>">
												<span class="dashicons <?php echo $is_ending_soon ? 'dashicons-clock' : 'dashicons-calendar-alt'; ?>"></span>
												<?php
												if ( $is_ending_soon ) {
													echo esc_html( $end_time_text );
												} else {
													echo esc_html( $start_time_text );
												}
												?>
											</span>
										<?php endif; ?>
									</div>

									<div class="scd-campaign-card-body">
										<!-- Consolidated Key Metrics (One Line) -->
										<div class="scd-campaign-metrics-inline">
											<?php
											// Discount
											if ( 'percentage' === $campaign['discount_type'] ) {
												/* translators: %s: discount percentage */
												printf( '<span class="scd-metric"><strong>%s%%</strong> off</span>', esc_html( $campaign['discount_value'] ) );
											} elseif ( 'fixed' === $campaign['discount_type'] ) {
												printf( '<span class="scd-metric"><strong>%s</strong> off</span>', wp_kses_post( wc_price( $campaign['discount_value'] ) ) );
											}

											// Performance metrics (if available)
											if ( $has_performance ) {
												echo '<span class="scd-metric-separator">•</span>';
												printf( '<span class="scd-metric">%s</span>', wp_kses_post( wc_price( $performance['revenue'] ?? 0 ) ) );
												echo '<span class="scd-metric-separator">•</span>';
												/* translators: %s: number of conversions */
												printf( '<span class="scd-metric">%s %s</span>', esc_html( number_format_i18n( $performance['conversions'] ?? 0 ) ), esc_html__( 'orders', 'smart-cycle-discounts' ) );
											}
											?>
										</div>
									</div>

									<div class="scd-campaign-card-footer">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $campaign_id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'smart-cycle-discounts' ); ?>
										</a>
										<?php if ( 'active' === $campaign['status'] ) : ?>
											<button class="button button-small scd-pause-campaign" data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>">
												<?php esc_html_e( 'Pause', 'smart-cycle-discounts' ); ?>
											</button>
										<?php elseif ( 'paused' === $campaign['status'] ) : ?>
											<button class="button button-small scd-resume-campaign" data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>">
												<?php esc_html_e( 'Resume', 'smart-cycle-discounts' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( count( $all_campaigns ) >= 5 ) : ?>
							<div class="scd-campaigns-view-more">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ); ?>" class="button button-secondary button-large">
									<?php esc_html_e( 'View All Campaigns', 'smart-cycle-discounts' ); ?>
									<span class="dashicons dashicons-arrow-right-alt2"></span>
								</a>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Campaign Timeline Visualization -->
				<?php
				// Get active and scheduled campaigns for timeline.
				global $wpdb;
				$table_name = $wpdb->prefix . 'scd_campaigns';

				$timeline_campaigns = $wpdb->get_results(
					"SELECT id, name, status, starts_at, ends_at
					FROM {$table_name}
					WHERE deleted_at IS NULL
						AND status IN ('active', 'scheduled')
					ORDER BY
						CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END,
						starts_at ASC
					LIMIT 10",
					ARRAY_A
				);

				// Debug: Show campaign count (remove this after testing).
				// echo '<!-- Timeline query returned: ' . count( $timeline_campaigns ) . ' campaigns -->';
				?>

				<div class="scd-campaign-timeline-section">
					<h3 class="scd-subsection-title">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Campaign Timeline', 'smart-cycle-discounts' ); ?>
						<span class="scd-subsection-meta"><?php esc_html_e( '(Next 30 days)', 'smart-cycle-discounts' ); ?></span>
					</h3>

				<?php if ( ! empty( $timeline_campaigns ) ) :
					$now = current_time( 'timestamp' );
					$timeline_start = $now;
					$timeline_end = $now + ( 30 * DAY_IN_SECONDS );

					// Calculate date markers (every 7 days approximately).
					$date_markers = array();
					for ( $i = 0; $i <= 4; $i++ ) {
						$marker_time = $timeline_start + ( $i * 7 * DAY_IN_SECONDS );
						$date_markers[] = array(
							'timestamp' => $marker_time,
							'label' => wp_date( 'M j', $marker_time ),
							'position' => ( $i / 4 ) * 100,
						);
					}
				?>
					<!-- Date Axis -->
					<div class="scd-timeline-axis">
							<?php foreach ( $date_markers as $marker ) : ?>
								<span class="scd-timeline-date-marker" style="left: <?php echo esc_attr( $marker['position'] ); ?>%;">
									<?php echo esc_html( $marker['label'] ); ?>
								</span>
							<?php endforeach; ?>
						</div>

						<!-- Timeline List -->
						<div class="scd-timeline-list">
							<?php
							foreach ( $timeline_campaigns as $timeline_campaign ) :
								// Create DateTime objects from UTC database strings (same as Campaign class)
								$start_date = ! empty( $timeline_campaign['starts_at'] ) ? new DateTime( $timeline_campaign['starts_at'], new DateTimeZone( 'UTC' ) ) : null;
								$end_date = ! empty( $timeline_campaign['ends_at'] ) ? new DateTime( $timeline_campaign['ends_at'], new DateTimeZone( 'UTC' ) ) : null;
								$start_time = $start_date ? $start_date->getTimestamp() : null;
								$end_time = $end_date ? $end_date->getTimestamp() : null;

								// Calculate position and width for progress bar.
								$start_pos = 0;
								$width = 100;

								if ( null !== $start_time && $timeline_start < $start_time ) {
									$start_pos = ( ( $start_time - $timeline_start ) / ( $timeline_end - $timeline_start ) ) * 100;
								}

								if ( null !== $end_time && $timeline_end > $end_time ) {
									$end_pos = ( ( $end_time - $timeline_start ) / ( $timeline_end - $timeline_start ) ) * 100;
									$width = $end_pos - $start_pos;
								} elseif ( null !== $start_time ) {
									$width = 100 - $start_pos;
								}

								// Ensure minimum visible width (2%).
								$width = max( 2, $width );

								// Format dates.
								$start_date = null !== $start_time ? wp_date( 'M j', $start_time ) : __( 'Ongoing', 'smart-cycle-discounts' );
								$end_date = null !== $end_time ? wp_date( 'M j', $end_time ) : __( 'No end', 'smart-cycle-discounts' );
								$date_range = $start_date . ' - ' . $end_date;

								// Get campaign status.
								$status = $timeline_campaign['status'];

								// Translatable status labels.
								$status_labels = array(
									'active'    => __( 'Active', 'smart-cycle-discounts' ),
									'scheduled' => __( 'Scheduled', 'smart-cycle-discounts' ),
									'paused'    => __( 'Paused', 'smart-cycle-discounts' ),
									'expired'   => __( 'Expired', 'smart-cycle-discounts' ),
									'draft'     => __( 'Draft', 'smart-cycle-discounts' ),
								);
								$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
								?>
								<div class="scd-timeline-campaign">
									<div class="scd-timeline-campaign-info">
										<span class="scd-timeline-status-dot scd-status-<?php echo esc_attr( $status ); ?>" title="<?php echo esc_attr( $status_label ); ?>"></span>
										<strong class="scd-timeline-campaign-name"><?php echo esc_html( $timeline_campaign['name'] ); ?></strong>
										<span class="scd-timeline-dates"><?php echo esc_html( $date_range ); ?></span>
										<span class="scd-timeline-badge scd-status-<?php echo esc_attr( $status ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</span>
									</div>
									<div class="scd-timeline-bar-track">
										<div class="scd-timeline-bar scd-timeline-bar-<?php echo esc_attr( $status ); ?>"
											style="left: <?php echo esc_attr( $start_pos ); ?>%; width: <?php echo esc_attr( $width ); ?>%;"
											title="<?php echo esc_attr( sprintf( '%s: %s', $timeline_campaign['name'], $date_range ) ); ?>">
										</div>
										<!-- Today marker line -->
										<div class="scd-timeline-today-marker" title="<?php esc_attr_e( 'Today', 'smart-cycle-discounts' ); ?>"></div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<!-- Empty State -->
						<div class="scd-timeline-empty-state">
							<div class="scd-empty-icon">
								<span class="dashicons dashicons-calendar-alt"></span>
							</div>
							<p class="scd-empty-message">
								<?php esc_html_e( 'No active or scheduled campaigns in the next 30 days.', 'smart-cycle-discounts' ); ?>
							</p>
							<p class="scd-empty-hint">
								<?php esc_html_e( 'Create a campaign with start and end dates to see it on the timeline.', 'smart-cycle-discounts' ); ?>
							</p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=new' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Create Campaign', 'smart-cycle-discounts' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// 5. QUICK ACTIONS (Dynamic Based on Dashboard State)
	// Determine primary action dynamically
	$primary_action_url = admin_url( 'admin.php?page=scd-campaigns&action=new' );
	$primary_action_text = __( 'Create New Campaign', 'smart-cycle-discounts' );
	$primary_action_icon = 'plus-alt';

	if ( $has_critical_issues && ! empty( $campaign_health['issues'] ) ) {
		// Critical issues - prioritize fixing
		$first_issue = $campaign_health['issues'][0];
		if ( isset( $first_issue['campaign_id'] ) ) {
			$primary_action_url = admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $first_issue['campaign_id'] );
			$primary_action_text = __( 'Fix Critical Issues', 'smart-cycle-discounts' );
			$primary_action_icon = 'warning';
		}
	} elseif ( ! empty( $campaign_suggestions ) ) {
		// Suggestions available - prioritize creating suggested campaign
		$first_suggestion = $campaign_suggestions[0];
		$primary_action_url = admin_url( 'admin.php?page=scd-campaigns&action=new&suggestion=' . $first_suggestion['id'] );
		/* translators: %s: suggestion name */
		$primary_action_text = sprintf( __( 'Create %s Campaign', 'smart-cycle-discounts' ), $first_suggestion['name'] );
		$primary_action_icon = 'lightbulb';
	} elseif ( 0 === $total_campaigns ) {
		// No campaigns - encourage first campaign
		$primary_action_text = __( 'Create Your First Campaign', 'smart-cycle-discounts' );
	}
	?>
	<div class="scd-quick-actions">
		<h2 class="scd-quick-actions-title"><?php esc_html_e( 'Quick Actions', 'smart-cycle-discounts' ); ?></h2>
		<div class="scd-action-buttons">
			<a href="<?php echo esc_url( $primary_action_url ); ?>" class="button button-primary button-hero">
				<span class="dashicons dashicons-<?php echo esc_attr( $primary_action_icon ); ?>"></span>
				<?php echo esc_html( $primary_action_text ); ?>
			</a>
			<?php if ( $total_campaigns > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ); ?>" class="button button-secondary button-large">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'View All Campaigns', 'smart-cycle-discounts' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $is_premium ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-analytics' ) ); ?>" class="button button-secondary button-large">
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'View Analytics', 'smart-cycle-discounts' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Loading Overlay -->
	<div id="scd-dashboard-loading" class="scd-loading-overlay" style="display: none;">
		<div class="scd-loading-spinner"></div>
	</div>
</div>
