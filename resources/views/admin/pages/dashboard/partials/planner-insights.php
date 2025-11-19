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
		<?php echo SCD_Icon_Helper::get( $insights_icon, array( 'size' => 16 ) ); ?>
		<h3><?php echo esc_html( $insights_title ); ?></h3>
	</div>

	<?php if ( ! empty( $insights_tabs ) ) : ?>
		<!-- 3-Column Grid Layout -->
		<div class="scd-insights-columns">
			<?php foreach ( $insights_tabs as $tab ) : ?>
				<div class="scd-insights-column">
					<!-- Column Header -->
					<div class="scd-insights-column-header">
						<?php echo SCD_Icon_Helper::get( $tab['icon'] ?? 'info', array( 'size' => 16 ) ); ?>
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
								$text = $item['text'] ?? '';
								$is_pro = false !== strpos( $text, '[PRO]' );
								$item_class = $is_pro ? 'scd-insights-pro-info-item' : 'scd-insights-info-item';
								$item_icon = $is_pro ? 'lock' : ( $item['icon'] ?? 'info' );
								?>
								<div class="<?php echo esc_attr( $item_class ); ?>"<?php echo $is_pro ? ' title="' . esc_attr__( 'PRO Feature - Upgrade to unlock', 'smart-cycle-discounts' ) . '"' : ''; ?>>
									<?php echo SCD_Icon_Helper::get( $item_icon, array( 'size' => 16 ) ); ?>
									<span class="scd-insights-info-text">
										<?php
										// Remove [PRO] marker from text
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
						<div class="scd-insights-cta">
							<?php
							SCD_Button_Helper::primary(
								$cta_item['text'] ?? __( 'Take Action', 'smart-cycle-discounts' ),
								array(
									'href'    => esc_url( $cta_item['url'] ?? '#' ),
									'classes' => array( 'scd-insights-cta-button' ),
								)
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
