<?php
/**
 * Countdown Timer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-countdown-timer.php
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
 * Countdown Timer Component Class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Countdown_Timer {

	/**
	 * Render countdown timer.
	 *
	 * @since    1.0.0
	 * @param    array $args    Timer arguments.
	 * @return   string            Timer HTML.
	 */
	public function render( array $args = array() ): string {
		$defaults = array(
			'end_date'     => '',
			'campaign_id'  => 0,
			'show_days'    => true,
			'show_hours'   => true,
			'show_minutes' => true,
			'show_seconds' => true,
			'labels'       => array(
				'days'    => __( 'Days', 'smart-cycle-discounts' ),
				'hours'   => __( 'Hours', 'smart-cycle-discounts' ),
				'minutes' => __( 'Minutes', 'smart-cycle-discounts' ),
				'seconds' => __( 'Seconds', 'smart-cycle-discounts' ),
			),
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['end_date'] ) ) {
			return '';
		}

		$timer_id = 'scd-timer-' . ( $args['campaign_id'] ?: wp_rand( 1000, 9999 ) );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $timer_id ); ?>" 
			class="scd-countdown-timer" 
			data-end-date="<?php echo esc_attr( $args['end_date'] ); ?>">
			<?php if ( $args['show_days'] ) : ?>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="days">00</span>
					<span class="scd-timer-label"><?php echo esc_html( $args['labels']['days'] ); ?></span>
				</div>
			<?php endif; ?>
			
			<?php if ( $args['show_hours'] ) : ?>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="hours">00</span>
					<span class="scd-timer-label"><?php echo esc_html( $args['labels']['hours'] ); ?></span>
				</div>
			<?php endif; ?>
			
			<?php if ( $args['show_minutes'] ) : ?>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="minutes">00</span>
					<span class="scd-timer-label"><?php echo esc_html( $args['labels']['minutes'] ); ?></span>
				</div>
			<?php endif; ?>
			
			<?php if ( $args['show_seconds'] ) : ?>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="seconds">00</span>
					<span class="scd-timer-label"><?php echo esc_html( $args['labels']['seconds'] ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get timer shortcode output.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string            Timer HTML.
	 */
	public function shortcode( array $atts = array() ): string {
		return $this->render( $atts );
	}
}
