<?php
/**
 * Campaign Planner Insights Partial - 3-Column Layout
 *
 * Displays insights in a 3-column grid (Opportunity / Strategy / Timeline).
 * Each column shows 3 randomly selected insights using weighted selection.
 * Loaded via AJAX when user clicks different Campaign Planner cards.
 *
 * Note: The header is rendered in the parent template (main-dashboard.php)
 * and updated via JavaScript. This partial only renders the column content.
 *
 * @var array $insights_data Insights data from Dashboard Service
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle empty or missing data gracefully.
if ( empty( $insights_data ) || ! is_array( $insights_data ) ) {
	?>
	<div class="wsscd-insights-empty">
		<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 32 ) ); ?>
		<p><?php esc_html_e( 'Click on a campaign card above to view detailed insights.', 'smart-cycle-discounts' ); ?></p>
	</div>
	<?php
	return;
}

$insights_tabs = $insights_data['tabs'] ?? array();

// Check if we have valid tabs to display.
if ( empty( $insights_tabs ) ) {
	?>
	<div class="wsscd-insights-empty">
		<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 32 ) ); ?>
		<p><?php esc_html_e( 'No insights available for this campaign.', 'smart-cycle-discounts' ); ?></p>
	</div>
	<?php
	return;
}
?>

<!-- 3-Column Grid Layout -->
<div class="wsscd-insights-columns">
	<?php foreach ( $insights_tabs as $tab ) : ?>
		<div class="wsscd-insights-column">
			<!-- Column Header -->
			<div class="wsscd-insights-column-header">
				<?php WSSCD_Icon_Helper::render( $tab['icon'] ?? 'info', array( 'size' => 16 ) ); ?>
				<h4><?php echo esc_html( $tab['label'] ?? '' ); ?></h4>
			</div>

			<!-- Column Content (Info Items) -->
			<div class="wsscd-insights-items">
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
						$text     = $item['text'] ?? '';
						$is_pro   = false !== strpos( $text, '[PRO]' );
						$item_class = $is_pro ? 'wsscd-insights-item wsscd-insights-item--pro' : 'wsscd-insights-item';
						$item_icon  = $is_pro ? 'lock' : ( $item['icon'] ?? 'info' );
						?>
						<div class="<?php echo esc_attr( $item_class ); ?>"<?php echo $is_pro ? ' title="' . esc_attr__( 'PRO Feature - Upgrade to unlock', 'smart-cycle-discounts' ) . '"' : ''; ?>>
							<?php WSSCD_Icon_Helper::render( $item_icon, array( 'size' => 16 ) ); ?>
							<span class="wsscd-insights-item-text">
								<?php
								// Remove [PRO] marker from text.
								$text = str_replace( '[PRO]', '', $text );
								echo wp_kses_post( $text );
								?>
							</span>
						</div>
						<?php
					endif;
				endforeach;
				?>
			</div>

			<!-- Column CTA (Separate Section) -->
			<?php if ( $cta_item ) : ?>
				<div class="wsscd-insights-column-cta">
					<?php
					WSSCD_Button_Helper::primary(
						$cta_item['text'] ?? __( 'Take Action', 'smart-cycle-discounts' ),
						array(
							'href'    => esc_url( $cta_item['url'] ?? '#' ),
							'classes' => array( 'wsscd-insights-cta-button' ),
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
