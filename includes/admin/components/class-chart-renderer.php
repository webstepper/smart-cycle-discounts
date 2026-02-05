<?php
/**
 * Chart Renderer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components/class-chart-renderer.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Chart Renderer Component
 *
 * Handles rendering of various chart types for the analytics dashboard.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Chart_Renderer {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Initialize the chart renderer.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger $logger    Logger instance.
	 */
	public function __construct( WSSCD_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Render a line chart.
	 *
	 * @since    1.0.0
	 * @param    string $chart_id    Chart container ID.
	 * @param    array  $data        Chart data.
	 * @param    array  $options     Chart options.
	 * @return   string                 Chart HTML.
	 */
	public function render_line_chart( string $chart_id, array $data, array $options = array() ): string {
		$default_options = array(
			'title'               => '',
			'height'              => 400,
			'responsive'          => true,
			'maintainAspectRatio' => false,
			'tension'             => 0.4,
			'fill'                => false,
		);

		$chart_options = wp_parse_args( $options, $default_options );

		return $this->render_chart_container( $chart_id, 'line', $data, $chart_options );
	}

	/**
	 * Render a bar chart.
	 *
	 * @since    1.0.0
	 * @param    string $chart_id    Chart container ID.
	 * @param    array  $data        Chart data.
	 * @param    array  $options     Chart options.
	 * @return   string                 Chart HTML.
	 */
	public function render_bar_chart( string $chart_id, array $data, array $options = array() ): string {
		$default_options = array(
			'title'               => '',
			'height'              => 400,
			'responsive'          => true,
			'maintainAspectRatio' => false,
			'indexAxis'           => 'x',
		);

		$chart_options = wp_parse_args( $options, $default_options );

		return $this->render_chart_container( $chart_id, 'bar', $data, $chart_options );
	}

	/**
	 * Render a doughnut chart.
	 *
	 * @since    1.0.0
	 * @param    string $chart_id    Chart container ID.
	 * @param    array  $data        Chart data.
	 * @param    array  $options     Chart options.
	 * @return   string                 Chart HTML.
	 */
	public function render_doughnut_chart( string $chart_id, array $data, array $options = array() ): string {
		$default_options = array(
			'title'               => '',
			'height'              => 300,
			'responsive'          => true,
			'maintainAspectRatio' => false,
			'cutout'              => '60%',
		);

		$chart_options = wp_parse_args( $options, $default_options );

		return $this->render_chart_container( $chart_id, 'doughnut', $data, $chart_options );
	}

	/**
	 * Render a metrics card.
	 *
	 * @since    1.0.0
	 * @param    array $metric    Metric data.
	 * @return   string             Card HTML.
	 */
	public function render_metrics_card( array $metric ): string {
		$defaults = array(
			'title'       => '',
			'value'       => 0,
			'change'      => 0,
			'change_type' => 'neutral', // positive, negative, neutral
			'icon'        => 'chart-line',
			'format'      => 'number', // number, currency, percentage
			'description' => '',
			'help_text'   => '', // Tooltip help text
		);

		$metric = wp_parse_args( $metric, $defaults );

		$formatted_value = $this->format_metric_value( $metric['value'], $metric['format'] );
		$change_class    = $this->get_change_class( $metric['change_type'] );
		$change_icon     = $this->get_change_icon( $metric['change_type'] );

		// Determine card type modifier based on title
		$card_type   = '';
		$title_lower = strtolower( $metric['title'] );
		if ( strpos( $title_lower, 'revenue' ) !== false ) {
			$card_type = 'wsscd-metric-card--revenue';
		} elseif ( strpos( $title_lower, 'conversion' ) !== false ) {
			$card_type = 'wsscd-metric-card--conversions';
		} elseif ( strpos( $title_lower, 'click' ) !== false || strpos( $title_lower, 'ctr' ) !== false ) {
			$card_type = 'wsscd-metric-card--ctr';
		} elseif ( strpos( $title_lower, 'campaign' ) !== false ) {
			$card_type = 'wsscd-metric-card--campaigns';
		}

		ob_start();
		?>
		<div class="wsscd-metric-card <?php echo esc_attr( $card_type ); ?>">
			<div class="wsscd-metric-card__header">
				<div class="wsscd-metric-card__icon">
					<?php
					WSSCD_Icon_Helper::render( str_replace( 'dashicons-', '', $metric['icon'] ), array( 'size' => 20 ) );
					?>
				</div>
			</div>

			<div class="wsscd-metric-card__title">
				<?php
				echo esc_html( $metric['title'] );

				// Add tooltip if help text provided
				if ( ! empty( $metric['help_text'] ) && class_exists( 'WSSCD_Tooltip_Helper' ) ) {
					WSSCD_Tooltip_Helper::render( $metric['help_text'] );
				}
				?>
			</div>

			<div class="wsscd-metric-card__value">
				<?php
				// Currency format contains HTML from wc_price(), use wp_kses_post
				// Other formats are plain text, use esc_html
				if ( 'currency' === $metric['format'] ) {
					echo wp_kses_post( $formatted_value );
				} else {
					echo esc_html( $formatted_value );
				}
				?>
			</div>

			<?php if ( $metric['change'] !== 0 ) : ?>
				<div class="wsscd-metric-card__change <?php echo esc_attr( $change_class ); ?>">
					<?php
					WSSCD_Icon_Helper::render( str_replace( 'dashicons-', '', $change_icon ), array( 'size' => 16 ) );
					?>
					<?php echo esc_html( $this->format_change( $metric['change'] ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $metric['description'] ) ) : ?>
				<div class="wsscd-metric-card__description">
					<?php echo esc_html( $metric['description'] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a performance summary card.
	 *
	 * @since    1.0.0
	 * @param    array $summary    Summary data.
	 * @return   string              Card HTML.
	 */
	public function render_performance_summary( array $summary ): string {
		$defaults = array(
			'title'   => __( 'Performance Summary', 'smart-cycle-discounts' ),
			'metrics' => array(),
			'period'  => '7 days',
		);

		$summary = wp_parse_args( $summary, $defaults );

		ob_start();
		?>
		<div class="wsscd-performance-summary">
			<?php foreach ( $summary['metrics'] as $metric ) : ?>
				<div class="wsscd-performance-item">
					<div class="wsscd-performance-item__content">
						<div class="wsscd-performance-item__label">
							<?php
							echo esc_html( $metric['label'] );

							// Add tooltip if help text provided
							if ( ! empty( $metric['help_text'] ) && class_exists( 'WSSCD_Tooltip_Helper' ) ) {
								WSSCD_Tooltip_Helper::render( $metric['help_text'] );
							}
							?>
						</div>
						<div class="wsscd-performance-item__value">
							<?php
							// Currency format contains HTML from wc_price(), use wp_kses_post
							$format    = $metric['format'] ?? 'number';
							$formatted = $this->format_metric_value( $metric['value'], $format );
							if ( 'currency' === $format ) {
								echo wp_kses_post( $formatted );
							} else {
								echo esc_html( $formatted );
							}
							?>
							<?php if ( isset( $metric['change'] ) && 0 !== $metric['change'] ) : ?>
								<span class="wsscd-performance-item__change <?php echo esc_attr( $this->get_change_class( $metric['change_type'] ?? 'neutral' ) ); ?>">
									<?php
									WSSCD_Icon_Helper::render( str_replace( 'dashicons-', '', $this->get_change_icon( $metric['change_type'] ?? 'neutral' ) ), array( 'size' => 16 ) );
									?>
									<?php echo esc_html( $this->format_change( $metric['change'] ) ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render chart container.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $chart_id    Chart container ID.
	 * @param    string $type        Chart type.
	 * @param    array  $data        Chart data.
	 * @param    array  $options     Chart options.
	 * @return   string                 Chart HTML.
	 */
	private function render_chart_container( string $chart_id, string $type, array $data, array $options ): string {
		$chart_config = array(
			'type'    => $type,
			'data'    => $data,
			'options' => $this->build_chart_options( $options ),
		);

		ob_start();
		?>
		<div class="wsscd-chart-container" style="height: <?php echo esc_attr( $options['height'] ); ?>px;">
			<?php if ( ! empty( $options['title'] ) ) : ?>
				<h4 class="wsscd-chart-title"><?php echo esc_html( $options['title'] ); ?></h4>
			<?php endif; ?>

			<div class="wsscd-chart-wrapper">
				<canvas
					id="<?php echo esc_attr( $chart_id ); ?>"
					data-chart-config="<?php echo esc_attr( wp_json_encode( $chart_config ) ); ?>"
					data-chart-type="<?php echo esc_attr( $type ); ?>"
				></canvas>
			</div>

			<div class="wsscd-chart-loading" style="display: none;">
				<span class="spinner is-active"></span>
				<span><?php esc_html_e( 'Loading chart data...', 'smart-cycle-discounts' ); ?></span>
			</div>

			<div class="wsscd-chart-error" style="display: none;">
				<?php
				WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) );
				?>
				<span><?php esc_html_e( 'Failed to load chart data.', 'smart-cycle-discounts' ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build Chart.js options.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $options    Custom options.
	 * @return   array               Chart.js options.
	 */
	private function build_chart_options( array $options ): array {
		$chart_options = array(
			'responsive'          => $options['responsive'] ?? true,
			'maintainAspectRatio' => $options['maintainAspectRatio'] ?? false,
			'plugins'             => array(
				'legend'  => array(
					'display'  => true,
					'position' => 'top',
				),
				'tooltip' => array(
					'enabled'   => true,
					'mode'      => 'index',
					'intersect' => false,
				),
			),
			'scales'              => array(
				'x' => array(
					'display' => true,
					'grid'    => array(
						'display' => false,
					),
				),
				'y' => array(
					'display'     => true,
					'beginAtZero' => true,
					'grid'        => array(
						'color' => 'rgba(0, 0, 0, 0.1)',
					),
				),
			),
			'interaction'         => array(
				'mode'      => 'nearest',
				'axis'      => 'x',
				'intersect' => false,
			),
		);

		if ( isset( $options['tension'] ) ) {
			$chart_options['elements'] = array(
				'line' => array(
					'tension' => $options['tension'],
				),
			);
		}

		if ( isset( $options['indexAxis'] ) ) {
			$chart_options['indexAxis'] = $options['indexAxis'];
		}

		if ( isset( $options['cutout'] ) ) {
			$chart_options['cutout'] = $options['cutout'];
		}

		return $chart_options;
	}

	/**
	 * Format metric value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed  $value     Value to format.
	 * @param    string $format    Format type.
	 * @return   string               Formatted value.
	 */
	private function format_metric_value( $value, string $format ): string {
		switch ( $format ) {
			case 'currency':
				return wc_price( $value );

			case 'percentage':
				return number_format( (float) $value, 1 ) . '%';

			case 'number':
			default:
				if ( $value >= 1000000 ) {
					return number_format( $value / 1000000, 1 ) . 'M';
				} elseif ( $value >= 1000 ) {
					return number_format( $value / 1000, 1 ) . 'K';
				}
				return number_format( (float) $value );
		}
	}

	/**
	 * Format change value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $change    Change value.
	 * @return   string             Formatted change.
	 */
	private function format_change( float $change ): string {
		$sign = $change >= 0 ? '+' : '';
		return $sign . number_format( $change, 1 ) . '%';
	}

	/**
	 * Get change CSS class.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Change type.
	 * @return   string            CSS class.
	 */
	private function get_change_class( string $type ): string {
		switch ( $type ) {
			case 'positive':
				return 'wsscd-change--positive';
			case 'negative':
				return 'wsscd-change--negative';
			default:
				return 'wsscd-change--neutral';
		}
	}

	/**
	 * Get change icon.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Change type.
	 * @return   string            Icon class.
	 */
	private function get_change_icon( string $type ): string {
		switch ( $type ) {
			case 'positive':
				return 'arrow-up-alt';
			case 'negative':
				return 'arrow-down-alt';
			default:
				return 'minus';
		}
	}

	/**
	 * Generate chart colors.
	 *
	 * @since    1.0.0
	 * @param    int   $count    Number of colors needed.
	 * @param    float $alpha    Alpha transparency.
	 * @return   array             Array of colors.
	 */
	public function generate_chart_colors( int $count, float $alpha = 1.0 ): array {
		$base_colors = array(
			array( 54, 162, 235 ),   // Blue
			array( 255, 99, 132 ),   // Red
			array( 255, 205, 86 ),   // Yellow
			array( 75, 192, 192 ),   // Green
			array( 153, 102, 255 ),  // Purple
			array( 255, 159, 64 ),   // Orange
			array( 199, 199, 199 ),  // Grey
			array( 83, 102, 255 ),   // Indigo
			array( 255, 99, 255 ),   // Pink
			array( 99, 255, 132 ),    // Light Green
		);

		$colors = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$color    = $base_colors[ $i % count( $base_colors ) ];
			$colors[] = sprintf( 'rgba(%d, %d, %d, %.2f)', $color[0], $color[1], $color[2], $alpha );
		}

		return $colors;
	}

	/**
	 * Prepare chart data for campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaigns    Campaign data.
	 * @return   array                  Chart data.
	 */
	public function prepare_campaigns_chart_data( array $campaigns ): array {
		$labels           = array();
		$revenue_data     = array();
		$conversions_data = array();
		$colors           = $this->generate_chart_colors( count( $campaigns ), 0.8 );
		$border_colors    = $this->generate_chart_colors( count( $campaigns ), 1.0 );

		foreach ( $campaigns as $index => $campaign ) {
			$labels[]           = $campaign['name'] ?? 'Campaign ' . ( $index + 1 );
			$revenue_data[]     = $campaign['revenue'] ?? 0;
			$conversions_data[] = $campaign['conversions'] ?? 0;
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Revenue', 'smart-cycle-discounts' ),
					'data'            => $revenue_data,
					'backgroundColor' => $colors,
					'borderColor'     => $border_colors,
					'borderWidth'     => 2,
				),
			),
		);
	}

	/**
	 * Prepare time series chart data.
	 *
	 * @since    1.0.0
	 * @param    array  $data         Time series data.
	 * @param    string $date_format  Date format.
	 * @return   array                   Chart data.
	 */
	public function prepare_time_series_data( array $data, string $date_format = 'M j' ): array {
		$labels = array();
		$values = array();

		foreach ( $data as $point ) {
			$labels[] = wp_date( $date_format, strtotime( $point['date'] ) );
			$values[] = $point['value'] ?? 0;
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Value', 'smart-cycle-discounts' ),
					'data'            => $values,
					'borderColor'     => 'rgba(54, 162, 235, 1)',
					'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
					'borderWidth'     => 2,
					'fill'            => true,
					'tension'         => 0.4,
				),
			),
		);
	}
}
