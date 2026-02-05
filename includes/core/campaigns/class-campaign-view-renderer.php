<?php
/**
 * Campaign View Renderer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-view-renderer.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign View Renderer Class
 *
 * @since      1.0.0
 */
class WSSCD_Campaign_View_Renderer {

	/**
	 * Template loader instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Template_Loader
	 */
	private WSSCD_Template_Loader $template_loader;

	/**
	 * Initialize the renderer.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Template_Loader $template_loader    Template loader.
	 */
	public function __construct( WSSCD_Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Render campaign stats view.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @param    array        $stats       Campaign statistics.
	 * @return   void
	 */
	public function render_stats_view( WSSCD_Campaign $campaign, array $stats ): void {
		?>
		<div class="wrap">
			<h1>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: campaign name */
						__( 'Campaign Statistics: %s', 'smart-cycle-discounts' ),
						$campaign->get_name()
					)
				);
				?>
			</h1>
			
			<div class="wsscd-stats-grid">
				<?php foreach ( $stats as $key => $value ) : ?>
					<div class="wsscd-stat-box">
						<h3><?php echo esc_html( $this->get_stat_label( $key ) ); ?></h3>
						<p class="wsscd-stat-value"><?php echo esc_html( $this->format_stat_value( $key, $value ) ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get stat label.
	 *
	 * @since    1.0.0
	 * @param    string $key    Stat key.
	 * @return   string            Formatted label.
	 */
	private function get_stat_label( string $key ): string {
		$labels = array(
			'total_revenue'   => __( 'Total Revenue', 'smart-cycle-discounts' ),
			'total_orders'    => __( 'Total Orders', 'smart-cycle-discounts' ),
			'conversion_rate' => __( 'Conversion Rate', 'smart-cycle-discounts' ),
			'avg_order_value' => __( 'Average Order Value', 'smart-cycle-discounts' ),
		);

		return $labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Format stat value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Stat key.
	 * @param    mixed  $value    Stat value.
	 * @return   string              Formatted value.
	 */
	private function format_stat_value( string $key, $value ): string {
		switch ( $key ) {
			case 'total_revenue':
			case 'avg_order_value':
				return wc_price( $value );
			case 'conversion_rate':
				return sprintf( '%.2f%%', $value );
			default:
				return (string) $value;
		}
	}
}