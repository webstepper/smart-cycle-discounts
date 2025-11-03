<?php
/**
 * Campaign Planner Insights Partial - 3-Column Layout
 *
 * Displays insights in a 3-column grid (Opportunity / Strategy / Timeline).
 * Each column shows 3 randomly selected insights using weighted selection.
 * Loaded via AJAX when user clicks different Campaign Planner cards.
 *
 * @var array $insights_data Insights data from Dashboard Service
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$insights_title = $insights_data['title'] ?? '';
$insights_icon  = $insights_data['icon'] ?? 'info';
$insights_tabs  = $insights_data['tabs'] ?? array();
?>

<div class="scd-insights-wrapper">
	<div class="scd-insights-header">
		<span class="dashicons dashicons-<?php echo esc_attr( $insights_icon ); ?>"></span>
		<h3><?php echo esc_html( $insights_title ); ?></h3>
	</div>

	<?php if ( ! empty( $insights_tabs ) ) : ?>
		<!-- 3-Column Grid Layout -->
		<div class="scd-insights-columns">
			<?php foreach ( $insights_tabs as $tab ) : ?>
				<div class="scd-insights-column">
					<!-- Column Header -->
					<div class="scd-insights-column-header">
						<span class="dashicons dashicons-<?php echo esc_attr( $tab['icon'] ?? 'info' ); ?>"></span>
						<h4><?php echo esc_html( $tab['label'] ?? '' ); ?></h4>
					</div>

					<!-- Column Content (Info Items) -->
					<div class="scd-insights-content-list">
						<?php
						$content_items = $tab['content'] ?? array();
						$cta_item      = null;

						// Render info items only.
						foreach ( $content_items as $item ) :
							$item_type = $item['type'] ?? 'info';

							if ( 'cta' === $item_type ) :
								// Store CTA for later rendering.
								$cta_item = $item;
							else :
								// Render info item.
								?>
								<div class="scd-insights-info-item">
									<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ?? 'info' ); ?>"></span>
									<span class="scd-insights-info-text"><?php echo esc_html( $item['text'] ?? '' ); ?></span>
								</div>
								<?php
							endif;
						endforeach;
						?>
					</div>

					<!-- Column CTA (Separate Section) -->
					<?php if ( $cta_item ) : ?>
						<div class="scd-insights-cta">
							<a href="<?php echo esc_url( $cta_item['url'] ?? '#' ); ?>" class="button button-primary scd-insights-cta-button">
								<?php echo esc_html( $cta_item['text'] ?? __( 'Take Action', 'smart-cycle-discounts' ) ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
