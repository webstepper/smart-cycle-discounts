<?php
/**
 * Main Dashboard View (Refactored)
 *
 * Streamlined dashboard with clear visual hierarchy and action-oriented design.
 * Displays: health alerts, hero metrics, campaign planner, campaign overview, and quick actions.
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
 * Data is provided by Dashboard Service with 5-minute caching (PHASE 3).
 * Cache automatically invalidates when campaigns change.
 *
 * @var array                        $metrics                  Dashboard metrics (includes pre-calculated trends)
 * @var array                        $campaign_stats           Campaign status breakdown
 * @var array                        $top_campaigns            Top campaigns by revenue
 * @var array                        $campaign_health          Campaign health data
 * @var array                        $campaign_suggestions     Campaign suggestions
 * @var array                        $planner_data            Campaign Planner data (weekly + major event campaigns)
 * @var bool                         $is_premium               Premium status
 * @var int                          $campaign_limit           Campaign limit
 * @var SCD_Feature_Gate             $feature_gate             Feature gate instance
 * @var SCD_Upgrade_Prompt_Manager   $upgrade_prompt_manager   Upgrade prompt manager
 */

// Default values
$metrics              = $metrics ?? array();
$campaign_stats       = $campaign_stats ?? array();
$top_campaigns        = $top_campaigns ?? array();
$campaign_health      = $campaign_health ?? array(
	'status'           => 'success',
	'issues'           => array(),
	'warnings'         => array(),
	'success_messages' => array(),
);
$campaign_suggestions = $campaign_suggestions ?? array();
$is_premium           = $is_premium ?? false;
$campaign_limit       = $campaign_limit ?? 3;

// Extract pre-calculated trends from metrics (calculated by analytics dashboard)
$revenue_trend     = $metrics['revenue_change'] ?? 0;
$conversions_trend = $metrics['conversions_change'] ?? 0;
$ctr_trend         = $metrics['ctr_change'] ?? 0;

// Determine dashboard state for dynamic CTAs
$total_campaigns     = $campaign_stats['total'] ?? 0;
$active_campaigns    = $campaign_stats['active'] ?? 0;
$has_critical_issues = 'critical' === $campaign_health['status'];
$has_warnings        = 'warning' === $campaign_health['status'];
$approaching_limit   = ! $is_premium && 0 !== $campaign_limit && $active_campaigns >= ( $campaign_limit * 0.67 );
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
	// 1. SMART CAMPAIGN HEALTH WIDGET (PHASE 4: Extracted to Partial)
	require __DIR__ . '/partials/health-widget.php';
	?>

	<?php
	// 2. UPGRADE BANNER (Compact, Dismissable)
	if ( ! $is_premium ) :
		// Base upgrade arguments
		$upgrade_args = array(
			'banner_id'    => 'dashboard_analytics',
			'title'        => __( 'Unlock Advanced Analytics with Pro', 'smart-cycle-discounts' ),
			'message'      => __( 'Get detailed insights with custom date ranges, trend analysis, campaign comparisons, revenue attribution, and exportable reports. See exactly which campaigns drive the most revenue.', 'smart-cycle-discounts' ),
			'bypass_limit' => true, // Dashboard banner doesn't count toward daily limit
		);

		// Merge promotional settings (if active)
		$promotion_settings = $upgrade_prompt_manager->get_promotion_settings();
		$upgrade_args       = array_merge( $upgrade_args, $promotion_settings );

		echo wp_kses_post( $upgrade_prompt_manager->get_upgrade_prompt( 'Advanced Analytics', 'banner', $upgrade_args ) );
	endif;
	?>

	<?php
	// 3. CAMPAIGN PLANNER
	$planner_data = $planner_data ?? array();
	$campaigns     = $planner_data['campaigns'] ?? array();

	if ( ! empty( $campaigns ) ) :
		?>
		<div class="scd-campaign-planner dashboard-section">
			<div class="scd-planner-header">
				<div class="scd-planner-header-content">
					<div class="scd-planner-header-icon">
						<span class="dashicons dashicons-calendar-alt"></span>
					</div>
					<div class="scd-planner-header-text">
						<h2><?php esc_html_e( 'Campaign Planner', 'smart-cycle-discounts' ); ?></h2>
						<p><?php esc_html_e( 'Smart suggestions for your next campaigns - weekly promotions + major events', 'smart-cycle-discounts' ); ?></p>
					</div>
				</div>
			</div>

			<!-- 3-Card Timeline Grid -->
			<div class="scd-planner-grid">
				<!-- Timeline Track -->
				<div class="scd-planner-timeline">
					<div class="scd-timeline-track">
						<div class="scd-timeline-item">
							<div class="scd-timeline-dot scd-timeline-dot--past" title="<?php esc_attr_e( 'Past Campaign', 'smart-cycle-discounts' ); ?>"></div>
							<span class="scd-timeline-label"><?php esc_html_e( 'Past', 'smart-cycle-discounts' ); ?></span>
						</div>
						<div class="scd-timeline-segment scd-timeline-segment--past"></div>
						<div class="scd-timeline-item">
							<div class="scd-timeline-dot scd-timeline-dot--active" title="<?php esc_attr_e( 'Active Campaign', 'smart-cycle-discounts' ); ?>"></div>
							<span class="scd-timeline-label scd-timeline-label--active"><?php esc_html_e( 'Active', 'smart-cycle-discounts' ); ?></span>
						</div>
						<div class="scd-timeline-segment scd-timeline-segment--active"></div>
						<div class="scd-timeline-item">
							<div class="scd-timeline-dot scd-timeline-dot--future" title="<?php esc_attr_e( 'Upcoming Campaign', 'smart-cycle-discounts' ); ?>"></div>
							<span class="scd-timeline-label"><?php esc_html_e( 'Upcoming', 'smart-cycle-discounts' ); ?></span>
						</div>
					</div>
				</div>

				<?php
				foreach ( $campaigns as $campaign ) :
					$state          = $campaign['state'];
					$is_major_event = ! empty( $campaign['is_major_event'] );
					$state_class    = 'scd-planner-card--' . $state;

					if ( $is_major_event ) {
						$state_class .= ' scd-planner-card--major';
					}

					$state_labels = array(
						'past'   => __( 'Ended', 'smart-cycle-discounts' ),
						'active' => __( 'Active Now', 'smart-cycle-discounts' ),
						'future' => __( 'Coming Soon', 'smart-cycle-discounts' ),
					);
					$state_label  = $state_labels[ $state ] ?? '';
					?>
					<div class="scd-planner-card <?php echo esc_attr( $state_class ); ?>"
						role="button"
						tabindex="0"
						aria-label="<?php echo esc_attr( sprintf( '%s - %s', $campaign['name'], $state_label ) ); ?>"
						data-campaign-id="<?php echo esc_attr( $campaign['id'] ); ?>"
						data-state="<?php echo esc_attr( $state ); ?>"
						data-is-major-event="<?php echo $is_major_event ? '1' : '0'; ?>">

						<!-- Card Header -->
						<div class="scd-planner-card-header">
							<div class="scd-planner-card-title">
								<h3>
									<?php echo esc_html( $campaign['name'] ); ?>
									<span class="scd-planner-icon"><?php echo esc_html( $campaign['icon'] ); ?></span>
								</h3>
								<?php if ( $is_major_event ) : ?>
									<span class="scd-planner-major-badge"><?php esc_html_e( 'Major Event', 'smart-cycle-discounts' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="scd-planner-card-badge scd-badge-<?php echo esc_attr( $state ); ?>">
								<?php
								$badge_icons = array(
									'past'   => 'clock',
									'active' => 'star-filled',
									'future' => 'calendar',
								);
								$badge_icon  = $badge_icons[ $state ] ?? 'info';
								?>
								<span class="dashicons dashicons-<?php echo esc_attr( $badge_icon ); ?>"></span>
								<?php echo esc_html( $state_label ); ?>
							</div>
						</div>

						<!-- Card Description -->
						<div class="scd-planner-card-content">
							<p class="scd-planner-card-description">
								<?php echo esc_html( $campaign['description'] ); ?>
							</p>

							<?php if ( 'active' === $state ) : ?>
								<!-- Active campaign - show discount -->
								<div class="scd-planner-card-discount">
									<span class="dashicons dashicons-tag"></span>
									<span>
										<?php
										$discount = $campaign['suggested_discount'];
										echo esc_html(
											sprintf(
												/* translators: 1: min discount, 2: max discount */
												__( 'Suggested: %1$d-%2$d%% off', 'smart-cycle-discounts' ),
												$discount['min'],
												$discount['max']
											)
										);
										?>
									</span>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $campaign['statistics'] ) && 'future' === $state ) : ?>
								<!-- Future campaign - show key stat -->
								<div class="scd-planner-card-stat">
									<span class="dashicons dashicons-chart-line"></span>
									<span><?php echo esc_html( reset( $campaign['statistics'] ) ); ?></span>
								</div>
							<?php endif; ?>
						</div>

						<!-- Card Actions -->
						<div class="scd-planner-card-actions">
							<?php if ( 'active' === $state ) : ?>
								<a href="<?php echo esc_url( $campaign['wizard_url'] ); ?>" class="button button-primary scd-planner-create-cta">
									<?php echo $is_major_event ? esc_html__( '✨ Create Major Campaign', 'smart-cycle-discounts' ) : esc_html__( '⚡ Create Campaign', 'smart-cycle-discounts' ); ?>
								</a>
							<?php elseif ( 'future' === $state ) : ?>
								<button type="button" class="button button-secondary scd-planner-view-details">
									<span class="dashicons dashicons-visibility"></span>
									<?php esc_html_e( 'View Details', 'smart-cycle-discounts' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="button button-link scd-planner-view-insights">
									<?php esc_html_e( 'See What Happened', 'smart-cycle-discounts' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Unified Insights Section (Bottom) -->
			<div class="scd-planner-insights">
				<div class="scd-insights-content" role="tabpanel">
					<?php
					// Load default insights for active campaign.
					$active_campaign = null;
					foreach ( $campaigns as $campaign ) {
						if ( 'active' === $campaign['state'] ) {
							$active_campaign = $campaign;
							break;
						}
					}

					if ( $active_campaign ) {
						$insights_data = $dashboard_service->get_unified_insights(
							$active_campaign['id'],
							'active',
							! empty( $active_campaign['is_major_event'] )
						);
						require __DIR__ . '/partials/planner-insights.php';
					}
					?>
				</div>
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
		$active_count    = isset( $campaign_stats['active'] ) ? $campaign_stats['active'] : 0;
		$scheduled_count = isset( $campaign_stats['scheduled'] ) ? $campaign_stats['scheduled'] : 0;
		$paused_count    = isset( $campaign_stats['paused'] ) ? $campaign_stats['paused'] : 0;
		$draft_count     = isset( $campaign_stats['draft'] ) ? $campaign_stats['draft'] : 0;
		$expired_count   = isset( $campaign_stats['expired'] ) ? $campaign_stats['expired'] : 0;

		$active_percent    = 0 < $total_campaigns ? ( $active_count / $total_campaigns ) * 100 : 0;
		$scheduled_percent = 0 < $total_campaigns ? ( $scheduled_count / $total_campaigns ) * 100 : 0;
		$paused_percent    = 0 < $total_campaigns ? ( $paused_count / $total_campaigns ) * 100 : 0;
		$draft_percent     = 0 < $total_campaigns ? ( $draft_count / $total_campaigns ) * 100 : 0;
		$expired_percent   = 0 < $total_campaigns ? ( $expired_count / $total_campaigns ) * 100 : 0;
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
							<div class="scd-status-segment scd-status-active" style="width: <?php echo esc_attr( $active_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Active: %1$d campaigns (%2$s%%)', 'smart-cycle-discounts' ), $active_count, number_format_i18n( $active_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $scheduled_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-scheduled" style="width: <?php echo esc_attr( $scheduled_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Scheduled: %1$d campaigns (%2$s%%)', 'smart-cycle-discounts' ), $scheduled_count, number_format_i18n( $scheduled_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $paused_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-paused" style="width: <?php echo esc_attr( $paused_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Paused: %1$d campaigns (%2$s%%)', 'smart-cycle-discounts' ), $paused_count, number_format_i18n( $paused_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $draft_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-draft" style="width: <?php echo esc_attr( $draft_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Draft: %1$d campaigns (%2$s%%)', 'smart-cycle-discounts' ), $draft_count, number_format_i18n( $draft_percent, 1 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $expired_percent > 0 ) : ?>
							<div class="scd-status-segment scd-status-expired" style="width: <?php echo esc_attr( $expired_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Expired: %1$d campaigns (%2$s%%)', 'smart-cycle-discounts' ), $expired_count, number_format_i18n( $expired_percent, 1 ) ) ); ?>"></div>
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
				// $all_campaigns is provided by Dashboard Service with pre-computed display data (PHASE 2)
				// Sorted by urgency, with all time calculations and urgency flags already computed
				// No database queries needed here - all calculations done in service layer

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
							<?php
							foreach ( $all_campaigns as $campaign ) :
								$campaign_id     = $campaign['id'];
								$performance     = isset( $campaign_performance[ $campaign_id ] ) ? $campaign_performance[ $campaign_id ] : null;
								$has_performance = null !== $performance;


								// Use pre-computed display data from Dashboard Service (PHASE 2)
								// All DateTime calculations done in service layer
								$is_ending_soon   = $campaign['is_ending_soon'] ?? false;
								$is_starting_soon = $campaign['is_starting_soon'] ?? false;
								$end_time_text    = $campaign['time_remaining_text'] ?? '';
								$start_time_text  = $campaign['time_until_start_text'] ?? '';
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
			</div>
		</div>
	<?php endif; ?>

	<?php
	// 5. QUICK ACTIONS (Dynamic Based on Dashboard State)
	// Determine primary action dynamically
	$primary_action_url  = admin_url( 'admin.php?page=scd-campaigns&action=new' );
	$primary_action_text = __( 'Create New Campaign', 'smart-cycle-discounts' );
	$primary_action_icon = 'plus-alt';

	if ( $has_critical_issues && ! empty( $campaign_health['issues'] ) ) {
		// Critical issues - prioritize fixing
		$first_issue = $campaign_health['issues'][0];
		if ( isset( $first_issue['campaign_id'] ) ) {
			$primary_action_url  = admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $first_issue['campaign_id'] );
			$primary_action_text = __( 'Fix Critical Issues', 'smart-cycle-discounts' );
			$primary_action_icon = 'warning';
		}
	} elseif ( ! empty( $campaign_suggestions ) ) {
		// Suggestions available - prioritize creating suggested campaign
		$first_suggestion   = $campaign_suggestions[0];
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
