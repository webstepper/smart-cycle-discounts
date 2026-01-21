<?php
/**
 * Sidebar Content Renderer
 *
 * Renders different types of sidebar content based on declarative configuration.
 * Handles all visual rendering for help topics, progress, and quick actions.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Content Renderer
 *
 * @since 1.0.0
 */
class WSSCD_Sidebar_Renderer {

	/**
	 * Render help topic
	 *
	 * @since  1.0.0
	 * @param  array $topic Topic configuration.
	 * @return string       Rendered HTML
	 */
	public static function render_help_topic( $topic ) {

		if ( empty( $topic ) ) {
			return '';
		}

		$type = isset( $topic['type'] ) ? $topic['type'] : 'simple';

		ob_start();
		?>
		<div class="wsscd-sidebar-help-topic" data-topic-type="<?php echo esc_attr( $type ); ?>">
			<div class="wsscd-sidebar-topic-header">
				<?php if ( isset( $topic['icon'] ) ) : ?>
					<?php WSSCD_Icon_Helper::render( $topic['icon'], array( 'size' => 16, 'class' => 'wsscd-topic-icon' ) ); ?>
				<?php endif; ?>
				<h4 class="wsscd-topic-title"><?php echo esc_html( $topic['title'] ); ?></h4>
			</div>

			<div class="wsscd-sidebar-topic-content">
				<?php
				switch ( $type ) {
					case 'examples':
						self::render_examples_content( $topic['content'] );
						break;
					case 'scale':
						self::render_scale_content( $topic['content'] );
						break;
					case 'methods':
						self::render_methods_content( $topic['content'] );
						break;
					case 'dynamic_methods':
						self::render_dynamic_methods_content( $topic['content'] );
						break;
					case 'pros_cons':
						self::render_pros_cons_content( $topic['content'] );
						break;
					case 'help_guide':
						self::render_help_guide_content( $topic['content'] );
						break;
					case 'simple':
					default:
						self::render_simple_content( $topic['content'] );
						break;
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render simple content type
	 *
	 * @since  1.0.0
	 * @param  array $content Content configuration.
	 * @return void
	 */
	private static function render_simple_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['bullets'] ) && is_array( $content['bullets'] ) ) : ?>
			<ul class="wsscd-bullet-list">
				<?php foreach ( $content['bullets'] as $bullet ) : ?>
					<li><?php echo esc_html( $bullet ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="wsscd-pro-tip">
				<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['common_mistakes'] ) && is_array( $content['common_mistakes'] ) ) : ?>
			<div class="wsscd-common-mistakes">
				<div class="wsscd-mistakes-header">
					<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 14 ) ); ?>
					<strong><?php esc_html_e( 'Common Mistakes', 'smart-cycle-discounts' ); ?></strong>
				</div>
				<ul class="wsscd-mistakes-list">
					<?php foreach ( $content['common_mistakes'] as $mistake ) : ?>
						<li><?php echo esc_html( $mistake ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render pros/cons content type
	 *
	 * @since  1.0.0
	 * @param  array $content Content configuration.
	 * @return void
	 */
	private static function render_pros_cons_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pros'] ) || isset( $content['cons'] ) ) : ?>
			<div class="wsscd-pros-cons-grid">
				<?php if ( isset( $content['pros'] ) && is_array( $content['pros'] ) ) : ?>
					<div class="wsscd-pros-section">
						<div class="wsscd-pros-header">
							<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?>
							<strong><?php esc_html_e( 'Pros', 'smart-cycle-discounts' ); ?></strong>
						</div>
						<ul class="wsscd-pros-list">
							<?php foreach ( $content['pros'] as $pro ) : ?>
								<li><?php echo esc_html( $pro ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( isset( $content['cons'] ) && is_array( $content['cons'] ) ) : ?>
					<div class="wsscd-cons-section">
						<div class="wsscd-cons-header">
							<?php WSSCD_Icon_Helper::render( 'no-alt', array( 'size' => 14 ) ); ?>
							<strong><?php esc_html_e( 'Cons', 'smart-cycle-discounts' ); ?></strong>
						</div>
						<ul class="wsscd-cons-list">
							<?php foreach ( $content['cons'] as $con ) : ?>
								<li><?php echo esc_html( $con ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['best_for'] ) ) : ?>
			<div class="wsscd-usage-info wsscd-usage-best">
				<?php WSSCD_Icon_Helper::render( 'yes-alt', array( 'size' => 14 ) ); ?>
				<div>
					<strong><?php esc_html_e( 'Best for:', 'smart-cycle-discounts' ); ?></strong>
					<span><?php echo esc_html( $content['best_for'] ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['avoid'] ) ) : ?>
			<div class="wsscd-usage-info wsscd-usage-avoid">
				<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 14 ) ); ?>
				<div>
					<strong><?php esc_html_e( 'Avoid when:', 'smart-cycle-discounts' ); ?></strong>
					<span><?php echo esc_html( $content['avoid'] ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="wsscd-pro-tip">
				<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render examples content type
	 *
	 * @since  1.0.0
	 * @param  array $content Configuration.
	 * @return void
	 */
	private static function render_examples_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['examples'] ) && is_array( $content['examples'] ) ) : ?>
			<div class="wsscd-examples-grid">
				<?php foreach ( $content['examples'] as $example_group ) : ?>
					<div class="wsscd-example-group wsscd-example-<?php echo esc_attr( $example_group['type'] ); ?>">
						<div class="wsscd-example-header">
							<?php
							$icon = 'success' === $example_group['type'] ? 'yes' : 'no-alt';
							WSSCD_Icon_Helper::render( $icon, array( 'size' => 16 ) );
							?>
							<strong><?php echo esc_html( $example_group['label'] ); ?></strong>
						</div>
						<ul class="wsscd-example-items">
							<?php foreach ( $example_group['items'] as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="wsscd-pro-tip">
				<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render scale content type (priority levels, ratings, etc)
	 *
	 * @since  1.0.0
	 * @param  array $content Content configuration.
	 * @return void
	 */
	private static function render_scale_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['scale'] ) && is_array( $content['scale'] ) ) : ?>
			<div class="wsscd-priority-scale">
				<?php foreach ( $content['scale'] as $level => $info ) : ?>
					<div class="wsscd-priority-level wsscd-priority-<?php echo esc_attr( $level ); ?> <?php echo esc_attr( isset( $info['default'] ) && $info['default'] ? 'wsscd-priority-default' : '' ); ?>">
						<div class="wsscd-priority-number"><?php echo esc_html( $level ); ?></div>
						<div class="wsscd-priority-stars">
							<?php
							for ( $i = 0; $i < $info['stars']; $i++ ) {
								WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 12 ) );
							}
							?>
						</div>
						<div class="wsscd-priority-info">
							<strong><?php echo esc_html( $info['label'] ); ?></strong>
							<span class="wsscd-priority-use"><?php echo esc_html( $info['use'] ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="wsscd-pro-tip">
				<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render methods content type (selection methods, etc)
	 *
	 * @since  1.0.0
	 * @param  array $content Content configuration.
	 * @return void
	 */
	private static function render_methods_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['methods'] ) && is_array( $content['methods'] ) ) : ?>
			<div class="wsscd-methods-list">
				<?php foreach ( $content['methods'] as $method_id => $method ) : ?>
					<div class="wsscd-method-item" data-method="<?php echo esc_attr( $method_id ); ?>">
						<?php WSSCD_Icon_Helper::render( $method['icon'], array( 'size' => 16, 'class' => 'wsscd-method-icon' ) ); ?>
						<div class="wsscd-method-content">
							<strong><?php echo esc_html( $method['label'] ); ?></strong>
							<p class="wsscd-method-desc"><?php echo esc_html( $method['desc'] ); ?></p>
							<?php if ( isset( $method['when'] ) ) : ?>
								<p class="wsscd-method-when"><em><?php esc_html_e( 'Use for:', 'smart-cycle-discounts' ); ?></em> <?php echo esc_html( $method['when'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render dynamic methods content (changes based on selection)
	 *
	 * @since  1.0.0
	 * @param  array $content Content configuration.
	 * @return void
	 */
	private static function render_dynamic_methods_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['methods'] ) && is_array( $content['methods'] ) ) : ?>
			<div class="wsscd-dynamic-methods">
				<?php foreach ( $content['methods'] as $method_id => $method ) : ?>
					<div class="wsscd-dynamic-method" data-method="<?php echo esc_attr( $method_id ); ?>" style="display: none;">
						<div class="wsscd-method-header">
							<?php WSSCD_Icon_Helper::render( $method['icon'], array( 'size' => 16 ) ); ?>
							<strong><?php echo esc_html( $method['label'] ); ?></strong>
						</div>
						<div class="wsscd-method-details">
							<div class="wsscd-method-row">
								<span class="wsscd-method-label"><?php esc_html_e( 'Best for:', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-method-value"><?php echo esc_html( $method['when'] ); ?></span>
							</div>
							<div class="wsscd-method-row">
								<span class="wsscd-method-label"><?php esc_html_e( 'Pros:', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-method-value"><?php echo esc_html( $method['pros'] ); ?></span>
							</div>
							<div class="wsscd-method-row">
								<span class="wsscd-method-label"><?php esc_html_e( 'Cons:', 'smart-cycle-discounts' ); ?></span>
								<span class="wsscd-method-value"><?php echo esc_html( $method['cons'] ); ?></span>
							</div>
							<?php if ( isset( $method['tip'] ) ) : ?>
								<div class="wsscd-method-tip">
									<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 14 ) ); ?>
									<span><?php echo esc_html( $method['tip'] ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render complete sidebar
	 *
	 * @since  1.0.0
	 * @param  string $step         Step identifier.
	 * @param  array  $step_data    Current step data.
	 * @param  string $current_topic Optional. Currently focused topic ID.
	 * @return string               Complete sidebar HTML
	 */
	public static function render_complete_sidebar( $step, $step_data = array(), $current_topic = '' ) {

		ob_start();

		// Get step title for breadcrumb
		$step_titles = array(
			'basic'     => __( 'Basic Information', 'smart-cycle-discounts' ),
			'products'  => __( 'Product Selection', 'smart-cycle-discounts' ),
			'discounts' => __( 'Discount Rules', 'smart-cycle-discounts' ),
			'schedule'  => __( 'Schedule & Limits', 'smart-cycle-discounts' ),
			'review'    => __( 'Review & Publish', 'smart-cycle-discounts' ),
		);
		$step_title = isset( $step_titles[ $step ] ) ? $step_titles[ $step ] : ucfirst( $step );
		?>
		<!-- Contextual Sidebar Wrapper (Required for JavaScript) -->
		<div class="wsscd-sidebar-contextual" data-step="<?php echo esc_attr( $step ); ?>">
			<!-- Context Breadcrumb -->
			<div class="wsscd-sidebar-breadcrumb" id="wsscd-sidebar-breadcrumb">
				<span class="wsscd-breadcrumb-step"><?php echo esc_html( $step_title ); ?></span>
				<span class="wsscd-breadcrumb-separator">›</span>
				<span class="wsscd-breadcrumb-topic" id="wsscd-breadcrumb-topic">
					<?php esc_html_e( 'Overview', 'smart-cycle-discounts' ); ?>
				</span>
			</div>

			<!-- Dynamic Help Content Area -->
			<div class="wsscd-sidebar-help-area">
				<div id="wsscd-sidebar-help-content">
					<?php
					// Render initial topic if provided
					if ( ! empty( $current_topic ) ) {
						$topic = WSSCD_Sidebar_Help_Topics::get_topic( $current_topic );
						if ( $topic ) {
							WSSCD_HTML_Helper::output( self::render_help_topic( $topic ) );
						}
					} else {
						// Show default welcome message
						?>
						<div class="wsscd-sidebar-welcome">
							<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 24 ) ); ?>
							<h4><?php esc_html_e( 'Contextual Help', 'smart-cycle-discounts' ); ?></h4>
							<p><?php esc_html_e( 'Focus on any field to see relevant tips and guidance. Help automatically updates based on what you\'re working on.', 'smart-cycle-discounts' ); ?></p>
						</div>
						<?php
					}
					?>
				</div>
			</div>

			<!-- PERFORMANCE: Pre-render most common topics for instant display (no AJAX) -->
			<div id="wsscd-prerendered-topics" style="display: none;">
				<?php
				// Pre-render the 3 most commonly used topics per step
				$common_topics = array();
				switch ( $step ) {
					case 'products':
						$common_topics = array( 'product-selection-type', 'category-ids', 'product-ids' );
						break;
					case 'discounts':
						$common_topics = array( 'discount-type', 'discount-value', 'option-discount-percentage' );
						break;
					case 'basic':
						$common_topics = array( 'campaign-name', 'campaign-description', 'priority' );
						break;
					case 'schedule':
						$common_topics = array( 'start-date', 'end-date', 'recurring-type' );
						break;
					case 'review':
						$common_topics = array( 'card-launch-options' );
						break;
				}

				foreach ( $common_topics as $topic_id ) {
					$topic = WSSCD_Sidebar_Help_Topics::get_topic( $topic_id );
					if ( $topic ) {
						echo '<div class="wsscd-prerendered-topic" data-topic-id="' . esc_attr( $topic_id ) . '">';
						WSSCD_HTML_Helper::output( self::render_help_topic( $topic ) );
						echo '</div>';
					}
				}
				?>
			</div>
		</div><!-- .wsscd-sidebar-contextual -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Render help guide content type
	 *
	 * @since  1.0.0
	 * @param  array $content Content configuration.
	 * @return void
	 */
	private static function render_help_guide_content( $content ) {
		?>
		<?php if ( isset( $content['quick_tip'] ) ) : ?>
			<div class="wsscd-quick-tip">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['how_it_works'] ) && is_array( $content['how_it_works'] ) ) : ?>
			<div class="wsscd-help-section wsscd-help-how-works">
				<div class="wsscd-help-header">
					<?php WSSCD_Icon_Helper::render( 'admin-settings', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'How It Works', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="wsscd-help-body">
					<ul class="wsscd-help-list">
						<?php foreach ( $content['how_it_works'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['use_cases'] ) && is_array( $content['use_cases'] ) ) : ?>
			<div class="wsscd-help-section wsscd-help-use-cases">
				<div class="wsscd-help-header">
					<?php WSSCD_Icon_Helper::render( 'yes-alt', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'When To Use This', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="wsscd-help-body">
					<ul class="wsscd-help-list wsscd-checklist">
						<?php foreach ( $content['use_cases'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['watch_out'] ) && is_array( $content['watch_out'] ) ) : ?>
			<div class="wsscd-help-section wsscd-help-watch-out">
				<div class="wsscd-help-header">
					<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'Watch Out For', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="wsscd-help-body">
					<ul class="wsscd-help-list wsscd-warning-list">
						<?php foreach ( $content['watch_out'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['setup_tips'] ) && is_array( $content['setup_tips'] ) ) : ?>
			<div class="wsscd-help-section wsscd-help-setup-tips">
				<div class="wsscd-help-header">
					<?php WSSCD_Icon_Helper::render( 'admin-tools', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'Setup Tips', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="wsscd-help-body">
					<ul class="wsscd-help-list wsscd-steps-list">
						<?php foreach ( $content['setup_tips'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['highlight'] ) ) : ?>
			<div class="wsscd-help-highlight">
				<?php echo wp_kses_post( $content['highlight'] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="wsscd-pro-tip">
				<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}
}
