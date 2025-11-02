<?php
/**
 * Campaign Planner Insights Sections Rendering
 *
 * Renders sections loop - used by both tabbed and legacy modes.
 * Expects $sections variable to be defined.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( $sections as $section ) :
	$section_class = 'scd-insights-section';
	if ( ! empty( $section['default_open'] ) ) {
		$section_class .= ' scd-insights-section--open';
	}
	?>
	<div class="<?php echo esc_attr( $section_class ); ?>">
		<button type="button"
				class="scd-insights-section-toggle"
				aria-expanded="<?php echo ! empty( $section['default_open'] ) ? 'true' : 'false'; ?>">
			<span class="dashicons dashicons-<?php echo ! empty( $section['default_open'] ) ? 'arrow-down' : 'arrow-right'; ?>"></span>
			<span class="dashicons dashicons-<?php echo esc_attr( $section['icon'] ?? 'info' ); ?>"></span>
			<span class="scd-insights-section-heading"><?php echo esc_html( $section['heading'] ); ?></span>
		</button>

		<div class="scd-insights-section-content" <?php echo empty( $section['default_open'] ) ? 'style="display:none;"' : ''; ?>>
			<?php
			$content = $section['content'] ?? array();
			foreach ( $content as $item ) :
				$item_type = $item['type'] ?? 'text';

				switch ( $item_type ) {
					case 'message':
						?>
						<div class="scd-insights-message">
							<?php if ( ! empty( $item['icon'] ) ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
							<?php endif; ?>
							<span><?php echo esc_html( $item['text'] ); ?></span>
						</div>
						<?php
						break;

					case 'stat':
						?>
						<div class="scd-insights-stat">
							<span class="scd-insights-stat-label"><?php echo esc_html( $item['label'] ); ?></span>
							<span class="scd-insights-stat-value"><?php echo esc_html( $item['value'] ); ?></span>
						</div>
						<?php
						break;

					case 'stat_text':
						?>
						<div class="scd-insights-stat-text">
							<?php if ( ! empty( $item['icon'] ) ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
							<?php endif; ?>
							<span><?php echo esc_html( $item['text'] ); ?></span>
						</div>
						<?php
						break;

					case 'tip':
						?>
						<div class="scd-insights-tip">
							<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ?? 'yes' ); ?>"></span>
							<span><?php echo esc_html( $item['text'] ); ?></span>
						</div>
						<?php
						break;

					case 'checklist_item':
						?>
						<div class="scd-insights-checklist-item">
							<input type="checkbox" disabled <?php checked( ! empty( $item['checked'] ) ); ?>>
							<span><?php echo esc_html( $item['text'] ); ?></span>
						</div>
						<?php
						break;

					case 'cta':
						?>
						<div class="scd-insights-cta">
							<a href="<?php echo esc_url( $item['url'] ); ?>" class="button button-primary">
								<?php echo esc_html( $item['text'] ); ?> →
							</a>
						</div>
						<?php
						break;

					case 'heading':
						?>
						<h4 class="scd-insights-subheading"><?php echo esc_html( $item['text'] ); ?></h4>
						<?php
						break;

					case 'text':
						?>
						<p class="scd-insights-text"><?php echo esc_html( $item['text'] ); ?></p>
						<?php
						break;

					case 'button_link':
						?>
						<div class="scd-insights-button">
							<a href="<?php echo esc_url( $item['url'] ); ?>" class="button button-secondary">
								<?php if ( ! empty( $item['icon'] ) ) : ?>
									<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
								<?php endif; ?>
								<?php echo esc_html( $item['text'] ); ?>
							</a>
						</div>
						<?php
						break;

					case 'timeline_header':
						?>
						<div class="scd-insights-when-header">
							<span class="scd-when-days-badge"><?php echo esc_html( $item['days'] ); ?></span>
							<span><?php echo esc_html( $item['text'] ); ?></span>
						</div>
						<?php
						break;

					case 'timeline_section':
						?>
						<div class="scd-insights-when-section">
							<h5><?php echo esc_html( $item['heading'] ); ?></h5>
							<ul>
								<?php foreach ( $item['items'] as $timeline_item ) : ?>
									<li><?php echo esc_html( $timeline_item ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
						<?php
						break;
				}
			endforeach;
			?>
		</div>
	</div>
<?php endforeach; ?>
