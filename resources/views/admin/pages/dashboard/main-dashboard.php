<?php
/**
 * Main Dashboard View (Refactored)
 *
 * Streamlined dashboard with clear visual hierarchy and action-oriented design.
 * Displays: health alerts, hero metrics, campaign planner, campaign overview, and quick actions.
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/pages/dashboard
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

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
 * @var WSSCD_Feature_Gate             $feature_gate             Feature gate instance
 * @var WSSCD_Upgrade_Prompt_Manager   $upgrade_prompt_manager   Upgrade prompt manager
 */

// Default values
$metrics              = $metrics ?? array();
$campaign_stats       = $campaign_stats ?? array();
$top_campaigns        = $top_campaigns ?? array();
$all_campaigns        = $all_campaigns ?? array();
$campaign_health      = $campaign_health ?? array(
	'status'           => 'excellent',
	'issues'           => array(),
	'warnings'         => array(),
	'success_messages' => array(),
);
$campaign_suggestions = $campaign_suggestions ?? array();
$planner_data         = $planner_data ?? array();
$is_premium           = $is_premium ?? false;

// Extract pre-calculated trends from metrics (calculated by analytics dashboard)
$revenue_trend     = $metrics['revenue_change'] ?? 0;
$conversions_trend = $metrics['conversions_change'] ?? 0;
$ctr_trend         = $metrics['ctr_change'] ?? 0;

// Determine dashboard state for dynamic CTAs
$total_campaigns     = $campaign_stats['total'] ?? 0;
$active_campaigns    = $campaign_stats['active'] ?? 0;
$has_critical_issues = 'critical' === $campaign_health['status'];
$has_warnings        = in_array( $campaign_health['status'], array( 'fair', 'poor' ), true );
?>

<div class="wrap wsscd-main-dashboard">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Dashboard', 'smart-cycle-discounts' ); ?>
	</h1>

	<?php
	if ( $is_premium ) {
		echo wp_kses_post( WSSCD_Badge_Helper::pro_badge( __( 'Premium Version Active', 'smart-cycle-discounts' ) ) );
	}
	?>

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

		// Upgrade prompt contains SVG icons from Icon_Helper. Use wp_kses with merged allowed tags.
		$upgrade_allowed_tags = array_merge(
			wp_kses_allowed_html( 'post' ),
			class_exists( 'WSSCD_Icon_Helper' ) ? WSSCD_Icon_Helper::get_allowed_svg_tags() : array()
		);
		echo wp_kses( $upgrade_prompt_manager->get_upgrade_prompt( 'Advanced Analytics', 'banner', $upgrade_args ), $upgrade_allowed_tags );
	endif;
	?>

	<?php
	// 3. YOUR CAMPAIGNS (Status Distribution + Analytics Teaser/Performance + Campaign List)
	if ( 0 === $total_campaigns ) :
		// Empty state: No campaigns at all
		?>
		<div class="wsscd-dashboard-section wsscd-campaign-overview-empty">
			<div class="wsscd-empty-state">
				<?php WSSCD_Icon_Helper::render( 'megaphone', array( 'size' => 16 ) ); ?>
				<h3><?php esc_html_e( 'Ready to Get Started?', 'smart-cycle-discounts' ); ?></h3>
				<p><?php esc_html_e( 'Create your first discount campaign in 3 simple steps: choose products, set discount amount, and schedule dates.', 'smart-cycle-discounts' ); ?></p>
				<?php
				WSSCD_Button_Helper::primary(
					__( 'Create Your First Campaign', 'smart-cycle-discounts' ),
					array(
						'size' => 'large',
						'icon' => 'plus-alt',
						'href' => admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' ),
					)
				);
				?>
				<p class="wsscd-empty-help">
					<?php
					printf(
						/* translators: %s: Help Center URL. */
						esc_html__( 'Need help? Visit the %s.', 'smart-cycle-discounts' ),
						'<a href="' . esc_url( 'https://webstepper.io/contact-us/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Help Center', 'smart-cycle-discounts' ) . '</a>'
					);
					?>
				</p>
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
		<div class="wsscd-dashboard-section wsscd-campaign-overview">
			<div class="wsscd-section-header">
				<div class="wsscd-section-header-content">
					<div class="wsscd-section-header-icon">
						<?php WSSCD_Icon_Helper::render( 'megaphone', array( 'size' => 20 ) ); ?>
					</div>
					<div class="wsscd-section-header-text">
						<h2><?php esc_html_e( 'Your Campaigns', 'smart-cycle-discounts' ); ?></h2>
					</div>
					<div class="wsscd-section-header-actions">
					<?php
					WSSCD_Button_Helper::primary(
						__( 'Create Campaign', 'smart-cycle-discounts' ),
						array(
							'icon' => 'plus-alt',
							'href' => admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' ),
						)
					);

					WSSCD_Button_Helper::secondary(
						__( 'View All', 'smart-cycle-discounts' ),
						array(
							'href' => admin_url( 'admin.php?page=wsscd-campaigns' ),
						)
					);
					?>
					</div>
				</div>
				<p class="wsscd-section-header-description"><?php esc_html_e( 'Manage and monitor your discount campaigns', 'smart-cycle-discounts' ); ?></p>
			</div>
			<div class="wsscd-section-content">
				<?php
				// PREMIUM ONLY: Performance Summary with full analytics
				if ( $is_premium ) :
					?>
					<div class="wsscd-performance-summary">
						<h3 class="wsscd-subsection-title">
							<?php esc_html_e( 'Performance Summary', 'smart-cycle-discounts' ); ?>
							<span class="wsscd-subsection-meta"><?php esc_html_e( '(Last 30 days)', 'smart-cycle-discounts' ); ?></span>
						</h3>
						<div class="wsscd-hero-stats">
							<div class="wsscd-stat-card">
								<div class="wsscd-stat-icon">
									<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 20 ) ); ?>
								</div>
								<div class="wsscd-stat-content">
									<div class="wsscd-stat-label"><?php esc_html_e( 'Total Revenue', 'smart-cycle-discounts' ); ?></div>
									<div class="wsscd-stat-value" id="wsscd-stat-revenue">
										<?php echo wp_kses_post( isset( $metrics['revenue'] ) ? wc_price( $metrics['revenue'] ) : wc_price( 0 ) ); ?>
									</div>
									<div class="wsscd-stat-meta">
										<?php esc_html_e( 'Last 30 days', 'smart-cycle-discounts' ); ?>
										<?php if ( 0 !== $revenue_trend ) : ?>
											<span class="wsscd-trend <?php echo esc_attr( 0 < $revenue_trend ? 'wsscd-trend-up' : 'wsscd-trend-down' ); ?>">
												<?php echo esc_html( 0 < $revenue_trend ? '↑' : '↓' ); ?>
												<?php echo esc_html( number_format_i18n( abs( $revenue_trend ), 1 ) ); ?>%
											</span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div class="wsscd-stat-card">
								<div class="wsscd-stat-icon">
									<?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 20 ) ); ?>
								</div>
								<div class="wsscd-stat-content">
									<div class="wsscd-stat-label"><?php esc_html_e( 'Conversions', 'smart-cycle-discounts' ); ?></div>
									<div class="wsscd-stat-value" id="wsscd-stat-conversions">
										<?php echo esc_html( isset( $metrics['conversions'] ) ? number_format_i18n( $metrics['conversions'] ) : 0 ); ?>
									</div>
									<div class="wsscd-stat-meta">
										<?php esc_html_e( 'Last 30 days', 'smart-cycle-discounts' ); ?>
										<?php if ( 0 !== $conversions_trend ) : ?>
											<span class="wsscd-trend <?php echo esc_attr( 0 < $conversions_trend ? 'wsscd-trend-up' : 'wsscd-trend-down' ); ?>">
												<?php echo esc_html( 0 < $conversions_trend ? '↑' : '↓' ); ?>
												<?php echo esc_html( number_format_i18n( abs( $conversions_trend ), 1 ) ); ?>%
											</span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div class="wsscd-stat-card">
								<div class="wsscd-stat-icon">
									<?php WSSCD_Icon_Helper::render( 'performance', array( 'size' => 20 ) ); ?>
								</div>
								<div class="wsscd-stat-content">
									<div class="wsscd-stat-label"><?php esc_html_e( 'Click Rate', 'smart-cycle-discounts' ); ?></div>
									<div class="wsscd-stat-value" id="wsscd-stat-ctr">
										<?php
										$ctr = 0;
										if ( isset( $metrics['clicks'] ) && isset( $metrics['impressions'] ) && $metrics['impressions'] > 0 ) {
											$ctr = ( $metrics['clicks'] / $metrics['impressions'] ) * 100;
										}
										echo esc_html( number_format_i18n( $ctr, 2 ) . '%' );
										?>
									</div>
									<div class="wsscd-stat-meta">
										<?php esc_html_e( 'Last 30 days', 'smart-cycle-discounts' ); ?>
										<?php if ( 0 !== $ctr_trend ) : ?>
											<span class="wsscd-trend <?php echo esc_attr( 0 < $ctr_trend ? 'wsscd-trend-up' : 'wsscd-trend-down' ); ?>">
												<?php echo esc_html( 0 < $ctr_trend ? '↑' : '↓' ); ?>
												<?php echo esc_html( number_format_i18n( abs( $ctr_trend ), 1 ) ); ?>%
											</span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div class="wsscd-stat-card">
								<div class="wsscd-stat-icon">
									<?php WSSCD_Icon_Helper::render( 'chart-area', array( 'size' => 20 ) ); ?>
								</div>
								<div class="wsscd-stat-content">
									<div class="wsscd-stat-label"><?php esc_html_e( 'View Analytics', 'smart-cycle-discounts' ); ?></div>
									<div class="wsscd-stat-value wsscd-stat-link">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-analytics' ) ); ?>">
											<?php esc_html_e( 'Full Report', 'smart-cycle-discounts' ); ?>
											<?php WSSCD_Icon_Helper::render( 'arrow-right-alt2', array( 'size' => 16 ) ); ?>
										</a>
									</div>
									<div class="wsscd-stat-meta"><?php esc_html_e( 'Detailed insights', 'smart-cycle-discounts' ); ?></div>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- Compact Status Distribution -->
				<div class="wsscd-status-summary">
					<div class="wsscd-status-header">
						<div class="wsscd-status-total">
							<strong><?php echo esc_html( $total_campaigns ); ?></strong>
							<?php echo esc_html( _n( 'campaign', 'campaigns', $total_campaigns, 'smart-cycle-discounts' ) ); ?>
						</div>
					</div>

					<!-- Visual Status Bar -->
					<div class="wsscd-status-bar" role="img" aria-label="<?php esc_attr_e( 'Campaign status distribution', 'smart-cycle-discounts' ); ?>">
						<?php if ( $active_percent > 0 ) : ?>
							<?php /* translators: 1: campaign count, 2: percentage */ ?>
							<div class="wsscd-status-segment wsscd-status-active" style="width: <?php echo esc_attr( $active_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Active: %1$d (%2$s%%)', 'smart-cycle-discounts' ), $active_count, number_format_i18n( $active_percent, 0 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $scheduled_percent > 0 ) : ?>
							<?php /* translators: 1: campaign count, 2: percentage */ ?>
							<div class="wsscd-status-segment wsscd-status-scheduled" style="width: <?php echo esc_attr( $scheduled_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Scheduled: %1$d (%2$s%%)', 'smart-cycle-discounts' ), $scheduled_count, number_format_i18n( $scheduled_percent, 0 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $paused_percent > 0 ) : ?>
							<?php /* translators: 1: campaign count, 2: percentage */ ?>
							<div class="wsscd-status-segment wsscd-status-paused" style="width: <?php echo esc_attr( $paused_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Paused: %1$d (%2$s%%)', 'smart-cycle-discounts' ), $paused_count, number_format_i18n( $paused_percent, 0 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $draft_percent > 0 ) : ?>
							<?php /* translators: 1: campaign count, 2: percentage */ ?>
							<div class="wsscd-status-segment wsscd-status-draft" style="width: <?php echo esc_attr( $draft_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Draft: %1$d (%2$s%%)', 'smart-cycle-discounts' ), $draft_count, number_format_i18n( $draft_percent, 0 ) ) ); ?>"></div>
						<?php endif; ?>
						<?php if ( $expired_percent > 0 ) : ?>
							<?php /* translators: 1: campaign count, 2: percentage */ ?>
							<div class="wsscd-status-segment wsscd-status-expired" style="width: <?php echo esc_attr( $expired_percent ); ?>%;" title="<?php echo esc_attr( sprintf( __( 'Expired: %1$d (%2$s%%)', 'smart-cycle-discounts' ), $expired_count, number_format_i18n( $expired_percent, 0 ) ) ); ?>"></div>
						<?php endif; ?>
					</div>

					<!-- Inline Status Legend -->
					<div class="wsscd-status-legend">
						<?php if ( $active_count > 0 ) : ?>
							<div class="wsscd-status-legend-item">
								<span class="wsscd-status-indicator wsscd-status-active"></span>
								<span class="wsscd-status-label"><?php esc_html_e( 'Active', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-status-count"><?php echo esc_html( $active_count ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( $scheduled_count > 0 ) : ?>
							<div class="wsscd-status-legend-item">
								<span class="wsscd-status-indicator wsscd-status-scheduled"></span>
								<span class="wsscd-status-label"><?php esc_html_e( 'Scheduled', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-status-count"><?php echo esc_html( $scheduled_count ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( $paused_count > 0 ) : ?>
							<div class="wsscd-status-legend-item">
								<span class="wsscd-status-indicator wsscd-status-paused"></span>
								<span class="wsscd-status-label"><?php esc_html_e( 'Paused', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-status-count"><?php echo esc_html( $paused_count ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( $draft_count > 0 ) : ?>
							<div class="wsscd-status-legend-item">
								<span class="wsscd-status-indicator wsscd-status-draft"></span>
								<span class="wsscd-status-label"><?php esc_html_e( 'Draft', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-status-count"><?php echo esc_html( $draft_count ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( $expired_count > 0 ) : ?>
							<div class="wsscd-status-legend-item">
								<span class="wsscd-status-indicator wsscd-status-expired"></span>
								<span class="wsscd-status-label"><?php esc_html_e( 'Expired', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-status-count"><?php echo esc_html( $expired_count ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php
				// FREE USERS: Analytics Teaser Section
				if ( ! $is_premium ) :
					?>
					<div class="wsscd-analytics-teaser">
						<div class="wsscd-analytics-teaser-content">
							<div class="wsscd-analytics-teaser-icon">
								<?php WSSCD_Icon_Helper::render( 'chart-area', array( 'size' => 32 ) ); ?>
							</div>
							<div class="wsscd-analytics-teaser-text">
								<h4><?php esc_html_e( 'Track Your Campaign Performance', 'smart-cycle-discounts' ); ?></h4>
								<p><?php esc_html_e( 'Upgrade to Pro to see revenue, conversions, click rates, trend analysis, and detailed per-campaign performance metrics.', 'smart-cycle-discounts' ); ?></p>
							</div>
							<div class="wsscd-analytics-teaser-preview">
								<div class="wsscd-teaser-stat">
									<span class="wsscd-teaser-stat-icon"><?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 16 ) ); ?></span>
									<span class="wsscd-teaser-stat-label"><?php esc_html_e( 'Revenue', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-teaser-stat">
									<span class="wsscd-teaser-stat-icon"><?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 16 ) ); ?></span>
									<span class="wsscd-teaser-stat-label"><?php esc_html_e( 'Conversions', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-teaser-stat">
									<span class="wsscd-teaser-stat-icon"><?php WSSCD_Icon_Helper::render( 'performance', array( 'size' => 16 ) ); ?></span>
									<span class="wsscd-teaser-stat-label"><?php esc_html_e( 'Click Rate', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-teaser-stat">
									<span class="wsscd-teaser-stat-icon"><?php WSSCD_Icon_Helper::render( 'trending-up', array( 'size' => 16 ) ); ?></span>
									<span class="wsscd-teaser-stat-label"><?php esc_html_e( 'Trends', 'smart-cycle-discounts' ); ?></span>
								</div>
							</div>
						</div>
						<div class="wsscd-analytics-teaser-actions">
							<?php
							WSSCD_Button_Helper::primary(
								__( 'Upgrade to Pro', 'smart-cycle-discounts' ),
								array(
									'size' => 'medium',
									'icon' => 'star-filled',
									'href' => $feature_gate->get_upgrade_url(),
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>

				<?php
				// $all_campaigns is provided by Dashboard Service with pre-computed display data (PHASE 2)
				// Sorted by urgency, with all time calculations and urgency flags already computed
				// No database queries needed here - all calculations done in service layer

				// Merge performance data from top_campaigns if available (PREMIUM ONLY)
				$campaign_performance = array();
				if ( $is_premium && ! empty( $top_campaigns ) ) {
					foreach ( $top_campaigns as $top_campaign ) {
						if ( isset( $top_campaign['id'] ) ) {
							$campaign_performance[ $top_campaign['id'] ] = $top_campaign;
						}
					}
				}
				?>

				<!-- Campaigns List -->
				<?php if ( ! empty( $all_campaigns ) ) : ?>
					<div class="wsscd-campaigns-list-section">
						<div class="wsscd-subsection-header">
							<h3 class="wsscd-subsection-title">
								<?php WSSCD_Icon_Helper::render( 'list-view', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Campaign List', 'smart-cycle-discounts' ); ?>
								<span class="wsscd-subsection-meta"><?php esc_html_e( '(Sorted by urgency)', 'smart-cycle-discounts' ); ?></span>
							</h3>
						</div>
						<div class="wsscd-campaigns-grid">
							<?php
							foreach ( $all_campaigns as $campaign ) :
								$campaign_id     = $campaign['id'];
								$performance     = isset( $campaign_performance[ $campaign_id ] ) ? $campaign_performance[ $campaign_id ] : null;
								$has_performance = null !== $performance;

								// Pre-computed display data from Campaign Display Service
								$is_ending_soon      = $campaign['is_ending_soon'] ?? false;
								$is_starting_soon    = $campaign['is_starting_soon'] ?? false;
								$end_time_text       = $campaign['time_remaining_text'] ?? '';
								$start_time_text     = $campaign['time_until_start_text'] ?? '';
								$scope_text          = $campaign['scope_text'] ?? '';
								$scope_icon          = $campaign['scope_icon'] ?? 'products';
								$schedule_text       = $campaign['schedule_text'] ?? '';
								$discount_type_icon  = $campaign['discount_type_icon'] ?? 'tickets-alt';
								$discount_type_label = $campaign['discount_type_label'] ?? '';
								$has_priority        = $campaign['has_priority'] ?? false;
								?>
								<div class="wsscd-campaign-card wsscd-campaign-status-<?php echo esc_attr( $campaign['status'] ); ?><?php echo esc_attr( $is_ending_soon ? ' wsscd-campaign-urgent' : '' ); ?>">
									<!-- Header: Status + Title + Priority -->
									<div class="wsscd-campaign-card-header">
										<?php echo wp_kses_post( WSSCD_Badge_Helper::status_badge( $campaign['status'], ucfirst( $campaign['status'] ) ) ); ?>
										<h4 class="wsscd-campaign-card-name"><?php echo esc_html( $campaign['name'] ); ?></h4>
										<?php if ( $has_priority ) : ?>
											<span class="wsscd-priority-indicator" title="<?php echo esc_attr( $campaign['priority_label'] ); ?>">
												<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 12 ) ); ?>
											</span>
										<?php endif; ?>
									</div>

									<!-- Discount: Prominent display -->
									<div class="wsscd-campaign-card-discount">
										<?php
										if ( 'percentage' === $campaign['discount_type'] ) {
											printf(
												'<span class="wsscd-discount-value">%s%%</span><span class="wsscd-discount-label">%s</span>',
												esc_html( $campaign['discount_value'] ),
												esc_html__( 'off', 'smart-cycle-discounts' )
											);
										} elseif ( 'fixed' === $campaign['discount_type'] ) {
											printf(
												'<span class="wsscd-discount-value">%s</span><span class="wsscd-discount-label">%s</span>',
												wp_kses_post( wc_price( $campaign['discount_value'] ) ),
												esc_html__( 'off', 'smart-cycle-discounts' )
											);
										} elseif ( 'tiered' === $campaign['discount_type'] ) {
											WSSCD_Icon_Helper::render( 'chart-bar', array( 'size' => 16 ) );
											printf( '<span class="wsscd-discount-value wsscd-discount-tiered">%s</span>', esc_html( $discount_type_label ) );
										} elseif ( 'bogo' === $campaign['discount_type'] ) {
											WSSCD_Icon_Helper::render( 'cart', array( 'size' => 16 ) );
											printf( '<span class="wsscd-discount-value wsscd-discount-bogo">%s</span>', esc_html__( 'BOGO', 'smart-cycle-discounts' ) );
										} elseif ( 'spend_threshold' === $campaign['discount_type'] ) {
											WSSCD_Icon_Helper::render( 'money-alt', array( 'size' => 16 ) );
											printf( '<span class="wsscd-discount-value wsscd-discount-threshold">%s</span>', esc_html( $discount_type_label ) );
										}
										?>
									</div>

									<!-- Meta: Scope + Schedule in compact layout -->
									<div class="wsscd-campaign-card-meta">
										<div class="wsscd-meta-item" title="<?php esc_attr_e( 'Products', 'smart-cycle-discounts' ); ?>">
											<?php WSSCD_Icon_Helper::render( $scope_icon, array( 'size' => 12 ) ); ?>
											<span><?php echo esc_html( $scope_text ); ?></span>
										</div>
										<div class="wsscd-meta-item" title="<?php esc_attr_e( 'Schedule', 'smart-cycle-discounts' ); ?>">
											<?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 12 ) ); ?>
											<span><?php echo esc_html( $schedule_text ); ?></span>
										</div>
									</div>

									<?php
									// Status Indicator - always shown for visual consistency.
									$status_class = 'wsscd-status-neutral';
									$status_icon  = 'info';
									$status_text  = '';

									if ( $is_ending_soon ) {
										$status_class = 'wsscd-status-ending';
										$status_icon  = 'warning';
										$status_text  = $end_time_text;
									} elseif ( $is_starting_soon ) {
										$status_class = 'wsscd-status-starting';
										$status_icon  = 'calendar';
										$status_text  = $start_time_text;
									} elseif ( 'active' === $campaign['status'] ) {
										$status_class = 'wsscd-status-running';
										$status_icon  = 'yes-alt';
										$status_text  = __( 'Running', 'smart-cycle-discounts' );
									} elseif ( 'scheduled' === $campaign['status'] ) {
										$status_class = 'wsscd-status-scheduled';
										$status_icon  = 'clock';
										$status_text  = $start_time_text ? $start_time_text : __( 'Scheduled', 'smart-cycle-discounts' );
									} elseif ( 'paused' === $campaign['status'] ) {
										$status_class = 'wsscd-status-paused';
										$status_icon  = 'controls-pause';
										$status_text  = __( 'Paused', 'smart-cycle-discounts' );
									} elseif ( 'draft' === $campaign['status'] ) {
										$status_class = 'wsscd-status-draft';
										$status_icon  = 'edit';
										$status_text  = __( 'Draft', 'smart-cycle-discounts' );
									} elseif ( 'expired' === $campaign['status'] ) {
										$status_class = 'wsscd-status-expired';
										$status_icon  = 'dismiss';
										$status_text  = __( 'Ended', 'smart-cycle-discounts' );
									}
									?>
									<div class="wsscd-campaign-card-status <?php echo esc_attr( $status_class ); ?>">
										<?php WSSCD_Icon_Helper::render( $status_icon, array( 'size' => 12 ) ); ?>
										<span><?php echo esc_html( $status_text ); ?></span>
									</div>

									<?php if ( $is_premium ) : ?>
										<?php
										$revenue     = $has_performance ? ( $performance['revenue'] ?? 0 ) : 0;
										$conversions = $has_performance ? ( $performance['conversions'] ?? 0 ) : 0;
										$has_data    = $revenue > 0 || $conversions > 0;
										$status      = $campaign['status'];
										?>
										<!-- Performance Stats (PRO) -->
										<div class="wsscd-campaign-card-stats<?php echo esc_attr( ! $has_data ? ' wsscd-stats-empty' : '' ); ?>">
											<?php if ( $has_data ) : ?>
												<div class="wsscd-stat-item">
													<span class="wsscd-stat-value"><?php echo wp_kses_post( wc_price( $revenue ) ); ?></span>
													<span class="wsscd-stat-label"><?php esc_html_e( 'revenue', 'smart-cycle-discounts' ); ?></span>
												</div>
												<div class="wsscd-stat-item">
													<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $conversions ) ); ?></span>
													<span class="wsscd-stat-label"><?php esc_html_e( 'orders', 'smart-cycle-discounts' ); ?></span>
												</div>
											<?php elseif ( 'scheduled' === $status ) : ?>
												<div class="wsscd-stats-empty-state">
													<?php WSSCD_Icon_Helper::render( 'clock', array( 'size' => 14 ) ); ?>
													<span><?php esc_html_e( 'Starts soon', 'smart-cycle-discounts' ); ?></span>
												</div>
											<?php elseif ( 'draft' === $status ) : ?>
												<div class="wsscd-stats-empty-state">
													<?php WSSCD_Icon_Helper::render( 'edit', array( 'size' => 14 ) ); ?>
													<span><?php esc_html_e( 'Draft - not tracking', 'smart-cycle-discounts' ); ?></span>
												</div>
											<?php elseif ( 'paused' === $status ) : ?>
												<div class="wsscd-stats-empty-state">
													<?php WSSCD_Icon_Helper::render( 'controls-pause', array( 'size' => 14 ) ); ?>
													<span><?php esc_html_e( 'Paused', 'smart-cycle-discounts' ); ?></span>
												</div>
											<?php else : ?>
												<div class="wsscd-stats-empty-state">
													<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 14 ) ); ?>
													<span><?php esc_html_e( 'No data yet', 'smart-cycle-discounts' ); ?></span>
												</div>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<!-- Footer: Actions -->
									<div class="wsscd-campaign-card-footer">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=edit&id=' . $campaign_id ) ); ?>" class="wsscd-card-action wsscd-card-action-edit">
											<?php WSSCD_Icon_Helper::render( 'edit', array( 'size' => 14 ) ); ?>
											<?php esc_html_e( 'Edit', 'smart-cycle-discounts' ); ?>
										</a>
										<?php if ( 'active' === $campaign['status'] ) : ?>
											<button type="button" class="wsscd-card-action wsscd-card-action-pause wsscd-pause-campaign" data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>">
												<?php WSSCD_Icon_Helper::render( 'controls-pause', array( 'size' => 14 ) ); ?>
												<?php esc_html_e( 'Pause', 'smart-cycle-discounts' ); ?>
											</button>
										<?php elseif ( 'paused' === $campaign['status'] ) : ?>
											<button type="button" class="wsscd-card-action wsscd-card-action-resume wsscd-resume-campaign" data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>">
												<?php WSSCD_Icon_Helper::render( 'controls-play', array( 'size' => 14 ) ); ?>
												<?php esc_html_e( 'Resume', 'smart-cycle-discounts' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( count( $all_campaigns ) >= 5 ) : ?>
							<div class="wsscd-campaigns-view-more">
								<?php
								WSSCD_Button_Helper::secondary(
									__( 'View All Campaigns', 'smart-cycle-discounts' ),
									array(
										'size'          => 'large',
										'icon'          => 'arrow-right-alt2',
										'icon_position' => 'right',
										'href'          => admin_url( 'admin.php?page=wsscd-campaigns' ),
									)
								);
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// 4. CAMPAIGN PLANNER
	$planner_data = $planner_data ?? array();
	$campaigns     = $planner_data['campaigns'] ?? array();

	if ( ! empty( $campaigns ) ) :
		?>
		<div class="wsscd-dashboard-section wsscd-campaign-planner">
			<div class="wsscd-section-header">
				<div class="wsscd-section-header-content">
					<div class="wsscd-section-header-icon">
						<?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 20 ) ); ?>
					</div>
					<div class="wsscd-section-header-text">
						<h2><?php esc_html_e( 'Campaign Planner', 'smart-cycle-discounts' ); ?></h2>
					</div>
				</div>
				<p class="wsscd-section-header-description"><?php esc_html_e( 'Optimize your promotional calendar with smart scheduling insights', 'smart-cycle-discounts' ); ?></p>
			</div>

			<!-- 3-Card Timeline Grid -->
			<div class="wsscd-planner-grid">
				<!-- Timeline Track -->
				<div class="wsscd-planner-timeline">
					<div class="wsscd-timeline-track">
						<?php
						// Map campaigns by POSITION (slot index) for smart timeline.
						// Smart timeline always returns 3 campaigns in order:
						// [0] = Slot 1 (PAST position) - most recently ended
						// [1] = Slot 2 (ACTIVE/NEXT position) - current OR next upcoming
						// [2] = Slot 3 (FUTURE position) - next after Slot 2
						$campaigns_by_position = array(
							'past'   => $campaigns[0] ?? null, // Slot 1
							'active' => $campaigns[1] ?? null, // Slot 2 (ACTIVE/NEXT)
							'future' => $campaigns[2] ?? null, // Slot 3
						);

						// Timeline position configuration (UI labels).
						$timeline_positions = array(
							'past'   => array(
								'label' => __( 'Past', 'smart-cycle-discounts' ),
								'title' => __( 'Past Campaign', 'smart-cycle-discounts' ),
							),
							'active' => array(
								'label' => __( 'Active', 'smart-cycle-discounts' ),
								'title' => __( 'Active Campaign', 'smart-cycle-discounts' ),
							),
							'future' => array(
								'label' => __( 'Upcoming', 'smart-cycle-discounts' ),
								'title' => __( 'Upcoming Campaign', 'smart-cycle-discounts' ),
							),
						);

						$first = true;
						foreach ( $timeline_positions as $position => $position_config ) :
							$campaign = $campaigns_by_position[ $position ] ?? null;

							// Add segment before item (except for first).
							if ( ! $first ) {
								$segment_class = 'past' === $position ? 'past' : 'active';
								?>
								<div class="wsscd-timeline-segment wsscd-timeline-segment--<?php echo esc_attr( $segment_class ); ?>"></div>
								<?php
							}
							$first = false;

							// Timeline item with campaign data.
							// NOTE: Uses POSITION class (timeline UI position), not campaign state.
							?>
							<div class="wsscd-timeline-item wsscd-timeline-item--<?php echo esc_attr( $position ); ?>"
								<?php if ( $campaign ) : ?>
									data-campaign-id="<?php echo esc_attr( $campaign['id'] ); ?>"
									data-position="<?php echo esc_attr( $position ); ?>"
									data-state="<?php echo esc_attr( $campaign['state'] ); ?>"
									data-is-major-event="<?php echo esc_attr( ! empty( $campaign['is_major_event'] ) ? '1' : '0' ); ?>"
									role="button"
									tabindex="0"
									aria-label="<?php echo esc_attr( sprintf( '%s - %s', $campaign['name'], $position_config['title'] ) ); ?>"
								<?php endif; ?>>
								<div class="wsscd-timeline-dot wsscd-timeline-dot--<?php echo esc_attr( $position ); ?>"
									title="<?php echo esc_attr( $position_config['title'] ); ?>"></div>
								<span class="wsscd-timeline-label <?php echo esc_attr( 'active' === $position ? 'wsscd-timeline-label--active' : '' ); ?>">
									<?php echo esc_html( $position_config['label'] ); ?>
								</span>
							</div>
							<?php
						endforeach;
						?>
					</div>
				</div>

				<?php
				// Map slot index to timeline position name.
				$position_names = array( 'past', 'active', 'future' );

				foreach ( $campaigns as $index => $campaign ) :
					$state          = $campaign['state'];
					$is_major_event = ! empty( $campaign['is_major_event'] );
					$position       = $position_names[ $index ] ?? 'future'; // Slot 0=past, 1=active, 2=future

					$state_labels = array(
						'past'   => __( 'Ended', 'smart-cycle-discounts' ),
						'active' => __( 'Active Now', 'smart-cycle-discounts' ),
						'future' => __( 'Coming Soon', 'smart-cycle-discounts' ),
					);
					$state_label  = $state_labels[ $state ] ?? '';
					?>
					<div class="wsscd-planner-card"
						data-state="<?php echo esc_attr( $state ); ?>"
						data-position="<?php echo esc_attr( $position ); ?>"
						data-major-event="<?php echo esc_attr( $is_major_event ? 'true' : 'false' ); ?>"
						data-campaign-id="<?php echo esc_attr( $campaign['id'] ); ?>"
						role="button"
						tabindex="0"
						aria-label="<?php echo esc_attr( sprintf( '%s - %s', $campaign['name'], $state_label ) ); ?>">

						<!-- Card Header -->
						<div class="wsscd-planner-card-header">
							<div class="wsscd-planner-card-title">
								<h3>
									<span class="wsscd-planner-icon"><?php WSSCD_Icon_Helper::render( $campaign['icon'], array( 'size' => 20 ) ); ?></span>
									<?php echo esc_html( $campaign['name'] ); ?>
								</h3>
							</div>
							<div class="wsscd-planner-card-badges">
								<?php if ( $is_major_event ) : ?>
									<?php
									// Combined badge for major events showing both state and importance
									$major_event_labels = array(
										'past'   => __( 'Past Major Event', 'smart-cycle-discounts' ),
										'active' => __( 'Active Major Event', 'smart-cycle-discounts' ),
										'future' => __( 'Upcoming Major Event', 'smart-cycle-discounts' ),
									);
									$combined_label = $major_event_labels[ $state ] ?? __( 'Major Event', 'smart-cycle-discounts' );
									?>
									<span class="wsscd-planner-card-badge wsscd-badge-major wsscd-badge-combined wsscd-badge-major-<?php echo esc_attr( $state ); ?>">
										<?php WSSCD_Icon_Helper::render( 'awards', array( 'size' => 16 ) ); ?>
										<?php echo esc_html( $combined_label ); ?>
									</span>
								<?php else : ?>
									<?php
									// Regular state badge for non-major events
									$badge_icons = array(
										'past'   => 'clock',
										'active' => 'star-filled',
										'future' => 'calendar',
									);
									$badge_icon  = $badge_icons[ $state ] ?? 'info';
									?>
									<span class="wsscd-planner-card-badge wsscd-badge-<?php echo esc_attr( $state ); ?>">
										<?php WSSCD_Icon_Helper::render( $badge_icon, array( 'size' => 16 ) ); ?>
										<?php echo esc_html( $state_label ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>

						<!-- Card Content -->
						<div class="wsscd-planner-card-content">
							<?php if ( ! empty( $campaign['description'] ) ) : ?>
								<p class="wsscd-planner-card-description"><?php echo esc_html( $campaign['description'] ); ?></p>
							<?php endif; ?>

							<div class="wsscd-planner-card-meta">
								<?php
								$content_parts = array();

								if ( ! empty( $campaign['date_range'] ) ) {
									$content_parts[] = '<span class="wsscd-planner-card-date">' . esc_html( $campaign['date_range'] ) . '</span>';
								}

								if ( ! empty( $campaign['time_relative'] ) ) {
									$content_parts[] = '<span class="wsscd-planner-card-relative">' . esc_html( $campaign['time_relative'] ) . '</span>';
								}

								if ( ! empty( $campaign['discount_suggestion'] ) ) {
									$content_parts[] = '<span class="wsscd-planner-card-discount">' . esc_html( $campaign['discount_suggestion'] ) . '</span>';
								}

								echo wp_kses_post( implode( ' <span class="wsscd-planner-card-separator">·</span> ', $content_parts ) );
								?>
							</div>
						</div>

						<!-- Card Actions -->
						<div class="wsscd-planner-card-actions">
							<?php
							/*
							 * Button Logic:
							 * - Slot 2 (active position): Always show action button (Create/Schedule)
							 * - Slots 1 & 3: Only show buttons for weekly campaigns (recurring)
							 *   Major events in past/future slots are informational only
							 *   (e.g., "Plan next Christmas" doesn't make sense - it's 12 months away)
							 */
							$is_focus_slot = 'active' === $position;

							if ( $is_focus_slot ) :
								// Slot 2: Always actionable
								if ( 'active' === $state ) :
									// Campaign is currently running - create now.
									?>
									<a href="<?php echo esc_url( $campaign['wizard_url'] ); ?>" class="wsscd-card-link">
										<?php echo esc_html( $is_major_event ? __( 'Create Campaign', 'smart-cycle-discounts' ) : __( 'Create Campaign', 'smart-cycle-discounts' ) ); ?>
										<span class="wsscd-card-link-arrow">&rarr;</span>
									</a>
									<?php
								else :
									// Future campaign in focus slot - schedule ahead.
									$schedule_url = add_query_arg( 'schedule', '1', $campaign['wizard_url'] );
									?>
									<a href="<?php echo esc_url( $schedule_url ); ?>" class="wsscd-card-link">
										<?php esc_html_e( 'Schedule Campaign', 'smart-cycle-discounts' ); ?>
										<span class="wsscd-card-link-arrow">&rarr;</span>
									</a>
									<?php
								endif;
							elseif ( ! $is_major_event ) :
								// Slots 1 & 3: Only weekly campaigns get action buttons.
								if ( 'past' === $position ) :
									// Past weekly campaign - plan the next occurrence.
									?>
									<a href="<?php echo esc_url( $campaign['wizard_url'] ); ?>" class="wsscd-card-link">
										<?php esc_html_e( 'Plan Next', 'smart-cycle-discounts' ); ?>
										<span class="wsscd-card-link-arrow">&rarr;</span>
									</a>
									<?php
								else :
									// Future weekly campaign - plan ahead.
									?>
									<a href="<?php echo esc_url( $campaign['wizard_url'] ); ?>" class="wsscd-card-link">
										<?php esc_html_e( 'Plan Ahead', 'smart-cycle-discounts' ); ?>
										<span class="wsscd-card-link-arrow">&rarr;</span>
									</a>
									<?php
								endif;
							endif;
							// Major events in slots 1 & 3: No button (informational only).
							?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Unified Insights Section (Bottom) -->
			<?php
			// Get focus campaign for insights (slot 2 = index 1).
			// This is always the most relevant campaign (active or next upcoming).
			$focus_campaign  = isset( $campaigns[1] ) ? $campaigns[1] : null;
			$insights_data   = null;
			$focus_position  = 'active'; // Default position for slot 2.

			if ( $focus_campaign ) {
				$insights_data = $dashboard_service->get_unified_insights(
					$focus_campaign['id'],
					$focus_position,
					! empty( $focus_campaign['is_major_event'] ),
					$focus_campaign['state'] // Actual campaign state.
				);
			}
			?>
			<div class="wsscd-planner-insights" data-position="<?php echo esc_attr( $focus_position ); ?>">
				<!-- Dynamic Content (replaced by AJAX) -->
				<div class="wsscd-insights-body" role="region" aria-label="<?php esc_attr_e( 'Campaign Insights', 'smart-cycle-discounts' ); ?>">
					<?php
					if ( $insights_data ) {
						require __DIR__ . '/partials/planner-insights.php';
					}
					?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// 5. QUICK ACTIONS (Dynamic Based on Dashboard State)
	// Determine primary action dynamically
	$primary_action_url  = admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' );
	$primary_action_text = __( 'Create New Campaign', 'smart-cycle-discounts' );
	$primary_action_icon = 'plus-alt';

	if ( $has_critical_issues && ! empty( $campaign_health['issues'] ) ) {
		// Critical issues - prioritize fixing
		$first_issue = $campaign_health['issues'][0];
		if ( isset( $first_issue['campaign_id'] ) ) {
			$primary_action_url  = admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=edit&id=' . $first_issue['campaign_id'] );
			$primary_action_text = __( 'Fix Critical Issues', 'smart-cycle-discounts' );
			$primary_action_icon = 'warning';
		}
	} elseif ( ! empty( $campaign_suggestions ) ) {
		// Suggestions available - prioritize creating suggested campaign
		$first_suggestion   = $campaign_suggestions[0];
		$primary_action_url = admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new&suggestion=' . $first_suggestion['id'] );
		/* translators: %s: suggestion name */
		$primary_action_text = sprintf( __( 'Create %s Campaign', 'smart-cycle-discounts' ), $first_suggestion['name'] );
		$primary_action_icon = 'lightbulb';
	} elseif ( 0 === $total_campaigns ) {
		// No campaigns - encourage first campaign
		$primary_action_text = __( 'Create Your First Campaign', 'smart-cycle-discounts' );
	}
	?>
	<div class="wsscd-dashboard-section wsscd-quick-actions">
		<div class="wsscd-section-header">
			<div class="wsscd-section-header-content">
				<div class="wsscd-section-header-icon">
					<?php WSSCD_Icon_Helper::render( 'admin-tools', array( 'size' => 20 ) ); ?>
				</div>
				<div class="wsscd-section-header-text">
					<h2><?php esc_html_e( 'Quick Actions', 'smart-cycle-discounts' ); ?></h2>
				</div>
			</div>
			<p class="wsscd-section-header-description"><?php esc_html_e( 'Get started with your most important tasks', 'smart-cycle-discounts' ); ?></p>
		</div>
		<div class="wsscd-section-content">
			<div class="wsscd-action-buttons">
			<?php
			WSSCD_Button_Helper::primary(
				$primary_action_text,
				array(
					'size' => 'hero',
					'icon' => $primary_action_icon,
					'href' => esc_url( $primary_action_url ),
				)
			);

			if ( $total_campaigns > 0 ) :
				WSSCD_Button_Helper::secondary(
					__( 'View All Campaigns', 'smart-cycle-discounts' ),
					array(
						'size' => 'large',
						'icon' => 'list-view',
						'href' => admin_url( 'admin.php?page=wsscd-campaigns' ),
					)
				);
			endif;

			if ( $is_premium ) :
				WSSCD_Button_Helper::secondary(
					__( 'View Analytics', 'smart-cycle-discounts' ),
					array(
						'size' => 'large',
						'icon' => 'chart-area',
						'href' => admin_url( 'admin.php?page=wsscd-analytics' ),
					)
				);
			endif;
			?>
			</div>
		</div>
	</div>

	<!-- Loading Overlay -->
	<?php
	if ( class_exists( 'WSSCD_Loader_Helper' ) ) {
		WSSCD_Loader_Helper::render_container( 'wsscd-dashboard-loading', __( 'Loading dashboard...', 'smart-cycle-discounts' ), false );
	}
	?>
</div>
