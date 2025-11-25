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
class SCD_Sidebar_Renderer {

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
		<div class="scd-sidebar-help-topic" data-topic-type="<?php echo esc_attr( $type ); ?>">
			<div class="scd-sidebar-topic-header">
				<?php if ( isset( $topic['icon'] ) ) : ?>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon helper handles escaping
					echo SCD_Icon_Helper::get( $topic['icon'], array( 'size' => 16, 'class' => 'scd-topic-icon' ) );
					?>
				<?php endif; ?>
				<h4 class="scd-topic-title"><?php echo esc_html( $topic['title'] ); ?></h4>
			</div>

			<div class="scd-sidebar-topic-content">
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['bullets'] ) && is_array( $content['bullets'] ) ) : ?>
			<ul class="scd-bullet-list">
				<?php foreach ( $content['bullets'] as $bullet ) : ?>
					<li><?php echo esc_html( $bullet ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="scd-pro-tip">
				<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['common_mistakes'] ) && is_array( $content['common_mistakes'] ) ) : ?>
			<div class="scd-common-mistakes">
				<div class="scd-mistakes-header">
					<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 14 ) ); ?>
					<strong><?php esc_html_e( 'Common Mistakes', 'smart-cycle-discounts' ); ?></strong>
				</div>
				<ul class="scd-mistakes-list">
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pros'] ) || isset( $content['cons'] ) ) : ?>
			<div class="scd-pros-cons-grid">
				<?php if ( isset( $content['pros'] ) && is_array( $content['pros'] ) ) : ?>
					<div class="scd-pros-section">
						<div class="scd-pros-header">
							<?php echo SCD_Icon_Helper::get( 'yes', array( 'size' => 14 ) ); ?>
							<strong><?php esc_html_e( 'Pros', 'smart-cycle-discounts' ); ?></strong>
						</div>
						<ul class="scd-pros-list">
							<?php foreach ( $content['pros'] as $pro ) : ?>
								<li><?php echo esc_html( $pro ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( isset( $content['cons'] ) && is_array( $content['cons'] ) ) : ?>
					<div class="scd-cons-section">
						<div class="scd-cons-header">
							<?php echo SCD_Icon_Helper::get( 'no-alt', array( 'size' => 14 ) ); ?>
							<strong><?php esc_html_e( 'Cons', 'smart-cycle-discounts' ); ?></strong>
						</div>
						<ul class="scd-cons-list">
							<?php foreach ( $content['cons'] as $con ) : ?>
								<li><?php echo esc_html( $con ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['best_for'] ) ) : ?>
			<div class="scd-usage-info scd-usage-best">
				<?php echo SCD_Icon_Helper::get( 'yes-alt', array( 'size' => 14 ) ); ?>
				<div>
					<strong><?php esc_html_e( 'Best for:', 'smart-cycle-discounts' ); ?></strong>
					<span><?php echo esc_html( $content['best_for'] ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['avoid'] ) ) : ?>
			<div class="scd-usage-info scd-usage-avoid">
				<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 14 ) ); ?>
				<div>
					<strong><?php esc_html_e( 'Avoid when:', 'smart-cycle-discounts' ); ?></strong>
					<span><?php echo esc_html( $content['avoid'] ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="scd-pro-tip">
				<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16 ) ); ?>
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['examples'] ) && is_array( $content['examples'] ) ) : ?>
			<div class="scd-examples-grid">
				<?php foreach ( $content['examples'] as $example_group ) : ?>
					<div class="scd-example-group scd-example-<?php echo esc_attr( $example_group['type'] ); ?>">
						<div class="scd-example-header">
							<?php
							$icon = 'success' === $example_group['type'] ? 'yes' : 'no-alt';
							echo SCD_Icon_Helper::get( $icon, array( 'size' => 16 ) );
							?>
							<strong><?php echo esc_html( $example_group['label'] ); ?></strong>
						</div>
						<ul class="scd-example-items">
							<?php foreach ( $example_group['items'] as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="scd-pro-tip">
				<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16 ) ); ?>
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['scale'] ) && is_array( $content['scale'] ) ) : ?>
			<div class="scd-priority-scale">
				<?php foreach ( $content['scale'] as $level => $info ) : ?>
					<div class="scd-priority-level scd-priority-<?php echo esc_attr( $level ); ?> <?php echo isset( $info['default'] ) && $info['default'] ? 'scd-priority-default' : ''; ?>">
						<div class="scd-priority-number"><?php echo esc_html( $level ); ?></div>
						<div class="scd-priority-stars">
							<?php
							for ( $i = 0; $i < $info['stars']; $i++ ) {
								echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 12 ) );
							}
							?>
						</div>
						<div class="scd-priority-info">
							<strong><?php echo esc_html( $info['label'] ); ?></strong>
							<span class="scd-priority-use"><?php echo esc_html( $info['use'] ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="scd-pro-tip">
				<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16 ) ); ?>
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['methods'] ) && is_array( $content['methods'] ) ) : ?>
			<div class="scd-methods-list">
				<?php foreach ( $content['methods'] as $method_id => $method ) : ?>
					<div class="scd-method-item" data-method="<?php echo esc_attr( $method_id ); ?>">
						<?php echo SCD_Icon_Helper::get( $method['icon'], array( 'size' => 16, 'class' => 'scd-method-icon' ) ); ?>
						<div class="scd-method-content">
							<strong><?php echo esc_html( $method['label'] ); ?></strong>
							<p class="scd-method-desc"><?php echo esc_html( $method['desc'] ); ?></p>
							<?php if ( isset( $method['when'] ) ) : ?>
								<p class="scd-method-when"><em><?php esc_html_e( 'Use for:', 'smart-cycle-discounts' ); ?></em> <?php echo esc_html( $method['when'] ); ?></p>
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['methods'] ) && is_array( $content['methods'] ) ) : ?>
			<div class="scd-dynamic-methods">
				<?php foreach ( $content['methods'] as $method_id => $method ) : ?>
					<div class="scd-dynamic-method" data-method="<?php echo esc_attr( $method_id ); ?>" style="display: none;">
						<div class="scd-method-header">
							<?php echo SCD_Icon_Helper::get( $method['icon'], array( 'size' => 16 ) ); ?>
							<strong><?php echo esc_html( $method['label'] ); ?></strong>
						</div>
						<div class="scd-method-details">
							<div class="scd-method-row">
								<span class="scd-method-label"><?php esc_html_e( 'Best for:', 'smart-cycle-discounts' ); ?></span>
								<span class="scd-method-value"><?php echo esc_html( $method['when'] ); ?></span>
							</div>
							<div class="scd-method-row">
								<span class="scd-method-label"><?php esc_html_e( 'Pros:', 'smart-cycle-discounts' ); ?></span>
								<span class="scd-method-value"><?php echo esc_html( $method['pros'] ); ?></span>
							</div>
							<div class="scd-method-row">
								<span class="scd-method-label"><?php esc_html_e( 'Cons:', 'smart-cycle-discounts' ); ?></span>
								<span class="scd-method-value"><?php echo esc_html( $method['cons'] ); ?></span>
							</div>
							<?php if ( isset( $method['tip'] ) ) : ?>
								<div class="scd-method-tip">
									<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 14 ) ); ?>
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
		<div class="scd-sidebar-contextual" data-step="<?php echo esc_attr( $step ); ?>">
			<!-- Context Breadcrumb -->
			<div class="scd-sidebar-breadcrumb" id="scd-sidebar-breadcrumb">
				<span class="scd-breadcrumb-step"><?php echo esc_html( $step_title ); ?></span>
				<span class="scd-breadcrumb-separator">›</span>
				<span class="scd-breadcrumb-topic" id="scd-breadcrumb-topic">
					<?php esc_html_e( 'Overview', 'smart-cycle-discounts' ); ?>
				</span>
			</div>

			<!-- Dynamic Help Content Area -->
			<div class="scd-sidebar-help-area" id="scd-sidebar-help-content">
				<?php
				// Render initial topic if provided
				if ( ! empty( $current_topic ) ) {
					$topic = SCD_Sidebar_Help_Topics::get_topic( $current_topic );
					if ( $topic ) {
						echo self::render_help_topic( $topic );
					}
				} else {
					// Show default welcome message
					?>
					<div class="scd-sidebar-welcome">
						<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 24 ) ); ?>
						<h4><?php esc_html_e( 'Contextual Help', 'smart-cycle-discounts' ); ?></h4>
						<p><?php esc_html_e( 'Focus on any field to see relevant tips and guidance. Help automatically updates based on what you\'re working on.', 'smart-cycle-discounts' ); ?></p>
					</div>
					<?php
				}
				?>
			</div>

			<!-- PERFORMANCE: Pre-render most common topics for instant display (no AJAX) -->
			<div id="scd-prerendered-topics" style="display: none;">
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
					$topic = SCD_Sidebar_Help_Topics::get_topic( $topic_id );
					if ( $topic ) {
						echo '<div class="scd-prerendered-topic" data-topic-id="' . esc_attr( $topic_id ) . '">';
						echo self::render_help_topic( $topic );
						echo '</div>';
					}
				}
				?>
			</div>
		</div><!-- .scd-sidebar-contextual -->
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
			<div class="scd-quick-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php echo esc_html( $content['quick_tip'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['how_it_works'] ) && is_array( $content['how_it_works'] ) ) : ?>
			<div class="scd-help-section scd-help-how-works">
				<div class="scd-help-header">
					<?php echo SCD_Icon_Helper::get( 'admin-settings', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'How It Works', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="scd-help-body">
					<ul class="scd-help-list">
						<?php foreach ( $content['how_it_works'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['use_cases'] ) && is_array( $content['use_cases'] ) ) : ?>
			<div class="scd-help-section scd-help-use-cases">
				<div class="scd-help-header">
					<?php echo SCD_Icon_Helper::get( 'yes-alt', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'When To Use This', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="scd-help-body">
					<ul class="scd-help-list scd-checklist">
						<?php foreach ( $content['use_cases'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['watch_out'] ) && is_array( $content['watch_out'] ) ) : ?>
			<div class="scd-help-section scd-help-watch-out">
				<div class="scd-help-header">
					<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'Watch Out For', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="scd-help-body">
					<ul class="scd-help-list scd-warning-list">
						<?php foreach ( $content['watch_out'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['setup_tips'] ) && is_array( $content['setup_tips'] ) ) : ?>
			<div class="scd-help-section scd-help-setup-tips">
				<div class="scd-help-header">
					<?php echo SCD_Icon_Helper::get( 'admin-tools', array( 'size' => 14 ) ); ?>
					<span><?php esc_html_e( 'Setup Tips', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="scd-help-body">
					<ul class="scd-help-list scd-steps-list">
						<?php foreach ( $content['setup_tips'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['highlight'] ) ) : ?>
			<div class="scd-help-highlight">
				<?php echo wp_kses_post( $content['highlight'] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( isset( $content['pro_tip'] ) ) : ?>
			<div class="scd-pro-tip">
				<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php echo esc_html( $content['pro_tip'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}
}
