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
			role="dialog"
			aria-modal="true"
			aria-labelledby="<?php echo esc_attr( $this->config['id'] . '-title' ); ?>"
			aria-describedby="<?php echo esc_attr( $this->config['id'] . '-message' ); ?>"
			<?php echo $attributes; ?>
			style="display:none;">
			<div class="scd-modal__overlay" aria-hidden="true"></div>
			<div class="scd-modal__container" role="document">
				<div class="scd-modal__content">
					<?php if ( $this->config['dismissible'] ) : ?>
						<button class="scd-modal__close" type="button" aria-label="<?php echo esc_attr__( 'Close modal', 'smart-cycle-discounts' ); ?>">
							<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
						</button>
					<?php endif; ?>

					<?php if ( ! empty( $this->config['icon'] ) ) : ?>
						<div class="scd-modal__icon" aria-hidden="true">
							<?php $this->render_icon(); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $this->config['title'] ) ) : ?>
						<h2 id="<?php echo esc_attr( $this->config['id'] . '-title' ); ?>" class="scd-modal__title">
							<?php echo esc_html( $this->config['title'] ); ?>
						</h2>
					<?php endif; ?>

					<div id="<?php echo esc_attr( $this->config['id'] . '-message' ); ?>" class="scd-modal__message">
						<?php
						if ( $this->config['escape_content'] ) {
							echo wp_kses_post( $this->config['content'] );
						} else {
							echo $this->config['content'];
						}
						?>
					</div>

					<?php if ( ! empty( $this->config['buttons'] ) ) : ?>
						<div class="scd-modal__actions" role="group" aria-label="<?php echo esc_attr__( 'Modal actions', 'smart-cycle-discounts' ); ?>">
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
	 * Uses SCD_Icon_Helper to render SVG icons.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_icon(): void {
		if ( ! empty( $this->config['icon'] ) ) {
			echo SCD_Icon_Helper::get( $this->config['icon'], array( 'size' => 48 ) );
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
					'icon'       => '',
					'style'      => '',
				)
			);

			$classes    = is_array( $button['class'] ) ? $button['class'] : array( $button['class'] );
			$attributes = $this->build_attributes( $button['attributes'] );

			if ( ! empty( $button['action'] ) ) {
				$attributes .= ' data-action="' . esc_attr( $button['action'] ) . '"';
			}

			if ( ! empty( $button['style'] ) ) {
				$attributes .= ' style="' . esc_attr( $button['style'] ) . '"';
			}
			?>
			<button type="<?php echo esc_attr( $button['type'] ); ?>"
					<?php
					if ( ! empty( $button['id'] ) ) :
						?>
						id="<?php echo esc_attr( $button['id'] ); ?>"<?php endif; ?>
					class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					<?php echo $attributes; ?>>
				<?php
				// Render icon if provided
				if ( ! empty( $button['icon'] ) ) {
					echo SCD_Icon_Helper::get( $button['icon'], array( 'size' => 16 ) );
				}
				echo esc_html( $button['text'] );
				?>
			</button>
			<?php
		}
	}

	/**
	 * Render modal styles.
	 *
	 * Modal styles are now loaded from dedicated CSS file:
	 * resources/assets/css/admin/draft-conflict-modal.css
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_modal_styles(): void {
		// Styles are now enqueued via Style_Registry
		// No inline styles needed
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

				// Store the last focused element before opening modal
				var lastFocusedElement = null;

				// Only define SCD.Modal if it doesn't already exist from ui-utilities.js
				if (!window.SCD.Modal) {
					window.SCD.Modal = {
						show: function(modalId) {
							var $modal = $('#' + modalId);

							// Store current focus
							lastFocusedElement = document.activeElement;

							// Show modal
							$modal.addClass('scd-modal--visible').fadeIn(200).css('display', 'flex');
							$('body').addClass('scd-modal-open');

							// Focus first button
							setTimeout(function() {
								var $firstButton = $modal.find('.scd-modal__actions button:first');
								if ($firstButton.length) {
									$firstButton.focus();
								}
							}, 250);

							// Setup focus trap
							this.setupFocusTrap($modal);
						},
						hide: function(modalId) {
							var $modal = $('#' + modalId);
							$modal.removeClass('scd-modal--visible').fadeOut(200);
							$('body').removeClass('scd-modal-open');

							// Restore focus to trigger element
							if (lastFocusedElement) {
								setTimeout(function() {
									lastFocusedElement.focus();
									lastFocusedElement = null;
								}, 250);
							}
						},
						hideAll: function() {
							$('.scd-modal').removeClass('scd-modal--visible').hide();
							$('body').removeClass('scd-modal-open');

							// Restore focus
							if (lastFocusedElement) {
								lastFocusedElement.focus();
								lastFocusedElement = null;
							}
						},
						setupFocusTrap: function($modal) {
							var focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
							var firstElement = focusableElements.first();
							var lastElement = focusableElements.last();

							// Remove previous handlers
							$modal.off('keydown.focustrap');

							// Trap focus
							$modal.on('keydown.focustrap', function(e) {
								if (e.key === 'Tab') {
									if (e.shiftKey) {
										// Shift + Tab
										if (document.activeElement === firstElement[0]) {
											e.preventDefault();
											lastElement.focus();
										}
									} else {
										// Tab
										if (document.activeElement === lastElement[0]) {
											e.preventDefault();
											firstElement.focus();
										}
									}
								}
							});
						}
					};
				} else {
					// Extend existing SCD.Modal to work with our modal component's CSS classes
					var originalShow = window.SCD.Modal.show;
					var originalHide = window.SCD.Modal.hide;

					window.SCD.Modal.show = function(modalId) {
						var $modal = $('#' + modalId);
						if ($modal.hasClass('scd-modal')) {
							// Store current focus
							lastFocusedElement = document.activeElement;

							// Our modal component - use our approach
							$modal.addClass('scd-modal--visible').fadeIn(200).css('display', 'flex');
							$('body').addClass('scd-modal-open');

							// Focus first button
							setTimeout(function() {
								var $firstButton = $modal.find('.scd-modal__actions button:first');
								if ($firstButton.length) {
									$firstButton.focus();
								}
							}, 250);

							// Setup focus trap
							this.setupFocusTrap($modal);
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

							// Restore focus
							if (lastFocusedElement) {
								setTimeout(function() {
									lastFocusedElement.focus();
									lastFocusedElement = null;
								}, 250);
							}
						} else if (originalHide) {
							// Other modals - use original approach
							originalHide.call(this, modalId);
						}
					};

					// Add focus trap setup if not exists
					if (!window.SCD.Modal.setupFocusTrap) {
						window.SCD.Modal.setupFocusTrap = function($modal) {
							var focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
							var firstElement = focusableElements.first();
							var lastElement = focusableElements.last();

							// Remove previous handlers
							$modal.off('keydown.focustrap');

							// Trap focus
							$modal.on('keydown.focustrap', function(e) {
								if (e.key === 'Tab') {
									if (e.shiftKey) {
										if (document.activeElement === firstElement[0]) {
											e.preventDefault();
											lastElement.focus();
										}
									} else {
										if (document.activeElement === lastElement[0]) {
											e.preventDefault();
											firstElement.focus();
										}
									}
								}
							});
						};
					}
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