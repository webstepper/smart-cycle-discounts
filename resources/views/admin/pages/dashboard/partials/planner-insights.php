<?php
/**
 * Campaign Planner Insights Partial
 *
 * Displays collapsible insight sections for a campaign (Why/How/When tabs).
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

// Backward compatibility: if no tabs, check for sections.
$insights_sections = $insights_data['sections'] ?? array();
$has_tabs          = ! empty( $insights_tabs );
?>

<div class="scd-insights-wrapper">
	<div class="scd-insights-header">
		<span class="dashicons dashicons-<?php echo esc_attr( $insights_icon ); ?>"></span>
		<h3><?php echo esc_html( $insights_title ); ?></h3>
	</div>

	<?php if ( $has_tabs ) : ?>
		<!-- Tab Navigation -->
		<div class="scd-insights-tabs" role="tablist">
			<?php foreach ( $insights_tabs as $index => $tab ) : ?>
				<button type="button"
						class="scd-insights-tab <?php echo 0 === $index ? 'scd-insights-tab--active' : ''; ?>"
						data-tab-id="<?php echo esc_attr( $tab['id'] ); ?>"
						role="tab"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						aria-controls="scd-tab-panel-<?php echo esc_attr( $tab['id'] ); ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $tab['icon'] ); ?>"></span>
					<?php echo esc_html( $tab['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- Tab Panels -->
		<div class="scd-insights-tab-panels">
			<?php foreach ( $insights_tabs as $index => $tab ) : ?>
				<div id="scd-tab-panel-<?php echo esc_attr( $tab['id'] ); ?>"
					 class="scd-insights-tab-panel <?php echo 0 === $index ? 'scd-insights-tab-panel--active' : ''; ?>"
					 role="tabpanel"
					 aria-labelledby="tab-<?php echo esc_attr( $tab['id'] ); ?>"
					 <?php echo 0 !== $index ? 'style="display:none;"' : ''; ?>>

					<div class="scd-insights-sections">
						<?php
						$sections = $tab['sections'] ?? array();
						require __DIR__ . '/planner-insights-sections.php';
						?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<!-- Legacy: Direct sections without tabs -->
		<div class="scd-insights-sections">
			<?php
			$sections = $insights_sections;
			require __DIR__ . '/planner-insights-sections.php';
			?>
		</div>
	<?php endif; ?>
</div>
