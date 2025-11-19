<?php
/**
 * Modal Component Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components/class-modal-component.php
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
 * Modal Component Class
 *
 * Provides a reusable modal component following WordPress patterns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Modal_Component {

	/**
	 * Modal configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $config    Modal configuration.
	 */
	private array $config;

	/**
	 * Default configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $defaults    Default configuration.
	 */
	private array $defaults = array(
		'id'             => '',
		'title'          => '',
		'content'        => '',
		'icon'           => '',
		'icon_type'      => 'svg',
		'buttons'        => array(),
		'classes'        => array(),
		'attributes'     => array(),
		'dismissible'    => true,
		'escape_content' => true,
	);

	/**
	 * Initialize the modal component.
	 *
	 * @since    1.0.0
	 * @param    array $config    Modal configuration.
	 */
	public function __construct( array $config ) {
		$this->config = wp_parse_args( $config, $this->defaults );

		// Ensure ID is set
		if ( empty( $this->config['id'] ) ) {
			$this->config['id'] = 'scd-modal-' . wp_generate_uuid4();
		}
	}

	/**
	 * Render the modal.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		$this->render_modal_html();
		$this->render_modal_styles();
		$this->render_modal_scripts();
	}

	/**
	 * Get modal HTML.
	 *
	 * @since    1.0.0
	 * @return   string    Modal HTML.
	 */
	public function get_html(): string {
		ob_start();
		$this->render_modal_html();
		return ob_get_clean();
	}

	/**
	 * Render modal HTML.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_modal_html(): void {
		$classes    = array_merge( array( 'scd-modal' ), $this->config['classes'] );
		$attributes = $this->build_attributes( $this->config['attributes'] );
		?>
		<div id="<?php echo esc_attr( $this->config['id'] ); ?>" 
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
			<?php echo $attributes; ?>
			style="display:none;">
			<div class="scd-modal__overlay"></div>
			<div class="scd-modal__container">
				<div class="scd-modal__content">
					<?php if ( $this->config['dismissible'] ) : ?>
						<button class="scd-modal__close" type="button" aria-label="<?php echo esc_attr__( 'Close modal', 'smart-cycle-discounts' ); ?>">
							<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
						</button>
					<?php endif; ?>
					
					<?php if ( ! empty( $this->config['icon'] ) ) : ?>
						<div class="scd-modal__icon">
							<?php $this->render_icon(); ?>
						</div>
					<?php endif; ?>
					
					<?php if ( ! empty( $this->config['title'] ) ) : ?>
						<h2 class="scd-modal__title">
							<?php echo esc_html( $this->config['title'] ); ?>
						</h2>
					<?php endif; ?>
					
					<div class="scd-modal__message">
						<?php
						if ( $this->config['escape_content'] ) {
							echo wp_kses_post( $this->config['content'] );
						} else {
							echo $this->config['content'];
						}
						?>
					</div>
					
					<?php if ( ! empty( $this->config['buttons'] ) ) : ?>
						<div class="scd-modal__actions">
							<?php $this->render_buttons(); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render modal icon.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_icon(): void {
		if ( $this->config['icon_type'] === 'dashicons' ) {
			$icon_name = str_replace( 'dashicons-', '', $this->config['icon'] );
			echo SCD_Icon_Helper::get( $icon_name, array( 'size' => 16 ) );
		} elseif ( $this->config['icon_type'] === 'svg' ) {
			echo wp_kses(
				$this->config['icon'],
				array(
					'svg'  => array(
						'class'       => true,
						'aria-hidden' => true,
						'aria-label'  => true,
						'role'        => true,
						'xmlns'       => true,
						'width'       => true,
						'height'      => true,
						'viewbox'     => true,
					),
					'path' => array(
						'd'      => true,
						'fill'   => true,
						'stroke' => true,
					),
				)
			);
		}
	}

	/**
	 * Render modal buttons.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_buttons(): void {
		foreach ( $this->config['buttons'] as $button ) {
			$button = wp_parse_args(
				$button,
				array(
					'text'       => '',
					'type'       => 'button',
					'class'      => 'button',
					'id'         => '',
					'attributes' => array(),
					'action'     => '',
				)
			);

			$classes    = is_array( $button['class'] ) ? $button['class'] : array( $button['class'] );
			$attributes = $this->build_attributes( $button['attributes'] );

			if ( ! empty( $button['action'] ) ) {
				$attributes .= ' data-action="' . esc_attr( $button['action'] ) . '"';
			}
			?>
			<button type="<?php echo esc_attr( $button['type'] ); ?>"
					<?php
					if ( ! empty( $button['id'] ) ) :
						?>
						id="<?php echo esc_attr( $button['id'] ); ?>"<?php endif; ?>
					class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					<?php echo $attributes; ?>>
				<?php echo esc_html( $button['text'] ); ?>
			</button>
			<?php
		}
	}

	/**
	 * Render modal styles.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_modal_styles(): void {
		static $styles_rendered = false;

		if ( $styles_rendered ) {
			return;
		}

		$styles_rendered = true;
		?>
		<style>
		/* SCD Modal Component Styles */
		.scd-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			z-index: 100000;
			display: none; /* Hidden by default */
			align-items: center;
			justify-content: center;
		}
		
		/* When modal is shown, use flex display */
		.scd-modal.scd-modal--visible {
			display: flex;
		}
		
		.scd-modal__overlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.6);
		}
		
		.scd-modal__container {
			position: relative;
			max-width: 500px;
			width: 90%;
			background: #fff;
			border-radius: 4px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
			animation: scd-modal-slide-in 0.3s ease-out;
		}
		
		.scd-modal__content {
			padding: 30px;
			text-align: center;
		}
		
		.scd-modal__close {
			position: absolute;
			top: 10px;
			right: 10px;
			background: none;
			border: none;
			font-size: 20px;
			cursor: pointer;
			color: #666;
			padding: 5px;
			transition: color 0.2s;
		}
		
		.scd-modal__close:hover {
			color: #000;
		}
		
		.scd-modal__icon {
			font-size: 48px;
			margin-bottom: 20px;
		}
		
		.scd-modal__icon .dashicons {
			width: 48px;
			height: 48px;
			font-size: 48px;
		}
		
		.scd-modal__icon--warning { color: #f0ad4e; }
		.scd-modal__icon--error { color: #dc3545; }
		.scd-modal__icon--success { color: #28a745; }
		.scd-modal__icon--info { color: #17a2b8; }
		
		.scd-modal__title {
			font-size: 20px;
			margin: 0 0 15px;
			color: #23282d;
		}
		
		.scd-modal__message {
			margin-bottom: 25px;
			color: #555;
			line-height: 1.6;
		}
		
		.scd-modal__message p {
			margin: 10px 0;
		}
		
		.scd-modal__actions {
			display: flex;
			gap: 10px;
			justify-content: center;
			flex-wrap: wrap;
		}
		
		.scd-modal__actions .button {
			margin: 0;
		}
		
		/* Animation */
		@keyframes scd-modal-slide-in {
			from {
				transform: translateY(-30px);
				opacity: 0;
			}
			to {
				transform: translateY(0);
				opacity: 1;
			}
		}
		
		/* Body overlay state */
		body.scd-modal-open {
			overflow: hidden;
		}
		
		/* Responsive */
		@media screen and (max-width: 600px) {
			.scd-modal__container {
				width: 95%;
				margin: 20px;
			}
			
			.scd-modal__content {
				padding: 20px;
			}
			
			.scd-modal__actions {
				flex-direction: column;
			}
			
			.scd-modal__actions .button {
				width: 100%;
				margin: 5px 0;
			}
		}
		</style>
		<?php
	}

	/**
	 * Render modal scripts.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_modal_scripts(): void {
		static $base_scripts_rendered = false;

		if ( ! $base_scripts_rendered ) {
			$base_scripts_rendered = true;
			?>
			<script>
			jQuery(document).ready(function($) {
				window.SCD = window.SCD || {};
				
				// Only define SCD.Modal if it doesn't already exist from ui-utilities.js
				if (!window.SCD.Modal) {
					window.SCD.Modal = {
						show: function(modalId) {
							var $modal = $('#' + modalId);
							// Use both approaches to ensure compatibility
							$modal.addClass('scd-modal--visible').fadeIn(200).css('display', 'flex');
							$('body').addClass('scd-modal-open');
						},
						hide: function(modalId) {
							var $modal = $('#' + modalId);
							$modal.removeClass('scd-modal--visible').fadeOut(200);
							$('body').removeClass('scd-modal-open');
						},
						hideAll: function() {
							$('.scd-modal').removeClass('scd-modal--visible').hide();
							$('body').removeClass('scd-modal-open');
						}
					};
				} else {
					// Extend existing SCD.Modal to work with our modal component's CSS classes
					var originalShow = window.SCD.Modal.show;
					var originalHide = window.SCD.Modal.hide;
					
					window.SCD.Modal.show = function(modalId) {
						var $modal = $('#' + modalId);
						if ($modal.hasClass('scd-modal')) {
							// Our modal component - use our approach
							$modal.addClass('scd-modal--visible').fadeIn(200).css('display', 'flex');
							$('body').addClass('scd-modal-open');
						} else if (originalShow) {
							// Other modals - use original approach
							originalShow.call(this, modalId);
						}
					};
					
					window.SCD.Modal.hide = function(modalId) {
						var $modal = $('#' + modalId);
						if ($modal.hasClass('scd-modal')) {
							// Our modal component - use our approach
							$modal.removeClass('scd-modal--visible').fadeOut(200);
							$('body').removeClass('scd-modal-open');
						} else if (originalHide) {
							// Other modals - use original approach
							originalHide.call(this, modalId);
						}
					};
				}
				
				// Global modal event handlers
				$(document).on('click', '.scd-modal__close, .scd-modal__overlay', function(e) {
					e.preventDefault();
					var $modal = $(this).closest('.scd-modal');
					SCD.Modal.hide($modal.attr('id'));
				});
				
				$(document).on('click', '.scd-modal__actions button[data-action="close"]', function(e) {
					e.preventDefault();
					var $modal = $(this).closest('.scd-modal');
					SCD.Modal.hide($modal.attr('id'));
				});
			});
			</script>
			<?php
		}
	}

	/**
	 * Build HTML attributes string.
	 *
	 * @since    1.0.0
	 * @param    array $attributes    Attributes array.
	 * @return   string                  Attributes string.
	 */
	private function build_attributes( array $attributes ): string {
		$output = '';

		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$output .= ' ' . esc_attr( $key );
				}
			} else {
				$output .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		return $output;
	}

	/**
	 * Static factory method for common modal types.
	 *
	 * @since    1.0.0
	 * @param    string $type      Modal type.
	 * @param    array  $config    Additional configuration.
	 * @return   self                 Modal instance.
	 */
	public static function create( string $type, array $config = array() ): self {
		$defaults = array();

		switch ( $type ) {
			case 'confirm':
				$defaults = array(
					'icon'    => 'warning',
					'buttons' => array(
						array(
							'text'   => __( 'Confirm', 'smart-cycle-discounts' ),
							'class'  => 'button button-primary',
							'action' => 'confirm',
						),
						array(
							'text'   => __( 'Cancel', 'smart-cycle-discounts' ),
							'class'  => 'button',
							'action' => 'close',
						),
					),
				);
				break;

			case 'error':
				$defaults = array(
					'icon'    => 'dismiss',
					'classes' => array( 'scd-modal--error' ),
					'buttons' => array(
						array(
							'text'   => __( 'OK', 'smart-cycle-discounts' ),
							'class'  => 'button button-primary',
							'action' => 'close',
						),
					),
				);
				break;

			case 'success':
				$defaults = array(
					'icon'    => 'yes-alt',
					'classes' => array( 'scd-modal--success' ),
					'buttons' => array(
						array(
							'text'   => __( 'OK', 'smart-cycle-discounts' ),
							'class'  => 'button button-primary',
							'action' => 'close',
						),
					),
				);
				break;
		}

		$config = wp_parse_args( $config, $defaults );
		return new self( $config );
	}
}