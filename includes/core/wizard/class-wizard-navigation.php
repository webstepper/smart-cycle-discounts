<?php
/**
 * Wizard Navigation Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-wizard-navigation.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Wizard Navigation Component Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Wizard_Navigation {

	/**
	 * Navigation configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $config    Navigation configuration.
	 */
	private $config = array(
		'steps'          => array(
			'basic'     => array(
				'title'    => 'Basic Information',
				'position' => 1,
			),
			'products'  => array(
				'title'                    => 'Product Selection',
				'position'                 => 2,
				'can_proceed_without_save' => true,
			),
			'discounts' => array(
				'title'    => 'Discount Configuration',
				'position' => 3,
			),
			'schedule'  => array(
				'title'    => 'Schedule & Rotation',
				'position' => 4,
			),
			'review'    => array(
				'title'    => 'Review & Launch',
				'position' => 5,
			),
		),
		'button_classes' => array(
			'previous'  => 'button scd-nav-btn scd-nav-btn--previous',
			'next'      => 'button button-primary scd-nav-btn scd-nav-btn--next',
			'complete'  => 'button button-primary scd-nav-btn scd-nav-btn--complete',
			'secondary' => 'button button-secondary scd-nav-btn scd-nav-btn--draft',
		),
		'icons'          => array(
			'previous' => 'dashicons-arrow-left-alt2',
			'next'     => 'dashicons-arrow-right-alt2',
			'complete' => 'dashicons-yes-alt',
		),
	);

	/**
	 * Wizard manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_Manager    $wizard_manager    Wizard manager.
	 */
	private $wizard_manager = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_Manager $wizard_manager    Wizard manager instance.
	 */
	public function __construct( $wizard_manager = null ) {
		$this->wizard_manager = $wizard_manager;
	}

	/**
	 * Initialize navigation.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		add_action( 'scd_wizard_render_navigation', array( $this, 'render_navigation' ), 10, 2 );
	}

	/**
	 * Render navigation.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current wizard step.
	 * @param    int    $campaign_id     Campaign ID (0 for new).
	 * @return   void
	 */
	public function render_navigation( $current_step, $campaign_id = 0 ) {
		$nav_data = $this->get_navigation_data( $current_step );

		$template_file = SCD_PLUGIN_DIR . 'resources/views/admin/wizard/wizard-navigation.php';

		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			$this->render_inline_navigation( $nav_data );
		}
	}

	/**
	 * Get navigation data.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current step.
	 * @return   array                      Navigation data.
	 */
	public function get_navigation_data( $current_step ) {
		$steps         = array_keys( $this->config['steps'] );
		$current_index = array_search( $current_step, $steps, true );

		return array(
			'current_step'  => $current_step,
			'current_index' => $current_index,
			'total_steps'   => count( $steps ),
			'previous_step' => $current_index > 0 ? $steps[ $current_index - 1 ] : null,
			'next_step'     => $current_index < count( $steps ) - 1 ? $steps[ $current_index + 1 ] : null,
			'is_first'      => $current_index === 0,
			'is_last'       => $current_index === count( $steps ) - 1,
			'progress'      => $this->wizard_manager ? $this->wizard_manager->get_progress() : array(),
			'config'        => $this->config,
		);
	}

	/**
	 * Render inline navigation (fallback).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $nav_data    Navigation data.
	 * @return   void
	 */
	private function render_inline_navigation( $nav_data ) {
		?>
		<nav class="scd-wizard-navigation">
			<div class="scd-nav-container">
				<!-- Previous Button -->
				<div class="scd-nav-section scd-nav-section--left">
					<?php if ( ! $nav_data['is_first'] ) : ?>
						<button type="button"
								class="<?php echo esc_attr( $nav_data['config']['button_classes']['previous'] ); ?>"
								data-action="previous"
								data-target="<?php echo esc_attr( $nav_data['previous_step'] ); ?>">
							<span class="dashicons <?php echo esc_attr( $nav_data['config']['icons']['previous'] ); ?>"></span>
							<span class="scd-nav-btn__text"><?php esc_html_e( 'Previous', 'smart-cycle-discounts' ); ?></span>
						</button>
					<?php endif; ?>
				</div>

				<!-- Center Section -->
				<div class="scd-nav-section scd-nav-section--center">
					<div class="scd-nav-status">
						<span class="scd-nav-status__step">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: current step number, 2: total steps */
									__( 'Step %1$d of %2$d', 'smart-cycle-discounts' ),
									$nav_data['current_index'] + 1,
									$nav_data['total_steps']
								)
							);
							?>
						</span>
						<span class="scd-nav-status__autosave" style="display: none;">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Saved', 'smart-cycle-discounts' ); ?>
						</span>
					</div>
				</div>

				<!-- Next/Complete Button -->
				<div class="scd-nav-section scd-nav-section--right">
					<?php if ( $nav_data['is_last'] ) : ?>
						<button type="button"
								class="<?php echo esc_attr( $nav_data['config']['button_classes']['complete'] ); ?>"
								data-action="complete">
							<span class="dashicons <?php echo esc_attr( $nav_data['config']['icons']['complete'] ); ?>"></span>
							<span class="scd-nav-btn__text"><?php esc_html_e( 'Create Campaign', 'smart-cycle-discounts' ); ?></span>
						</button>
					<?php else : ?>
						<button type="button"
								class="<?php echo esc_attr( $nav_data['config']['button_classes']['next'] ); ?>"
								data-action="next"
								data-target="<?php echo esc_attr( $nav_data['next_step'] ); ?>">
							<span class="scd-nav-btn__text"><?php esc_html_e( 'Next', 'smart-cycle-discounts' ); ?></span>
							<span class="dashicons <?php echo esc_attr( $nav_data['config']['icons']['next'] ); ?>"></span>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</nav>
		<?php
	}
}
