<?php
/**
 * Basic Step Sidebar
 *
 * Static sidebar content for the basic information step
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic step sidebar class
 */
class SCD_Wizard_Sidebar_Basic extends SCD_Wizard_Sidebar_Base {
	
	/**
	 * Get sidebar content
	 *
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Campaign Setup Guide', 'smart-cycle-discounts' ),
			__( 'Best practices for naming and organizing your campaigns', 'smart-cycle-discounts' )
		);
	}
	
	/**
	 * Render sidebar sections
	 */
	protected function render_sections() {
		// Naming Best Practices section
		ob_start();
		?>
		<div class="scd-naming-tips">
			<p class="scd-naming-intro">
				<?php esc_html_e( 'Use descriptive names to easily identify and manage your campaigns.', 'smart-cycle-discounts' ); ?>
			</p>

			<div class="scd-naming-do">
				<p class="scd-naming-label">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Good Examples:', 'smart-cycle-discounts' ); ?>
				</p>
				<ul class="scd-naming-examples">
					<li><?php esc_html_e( '"Summer Sale 2025 - 20% Off"', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( '"BOGO Women\'s Shoes"', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( '"Flash Sale - Electronics 50%"', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( '"New Arrivals - 15% Discount"', 'smart-cycle-discounts' ); ?></li>
				</ul>
			</div>

			<div class="scd-naming-dont">
				<p class="scd-naming-label">
					<span class="dashicons dashicons-dismiss"></span>
					<?php esc_html_e( 'Avoid:', 'smart-cycle-discounts' ); ?>
				</p>
				<ul class="scd-naming-examples">
					<li><?php esc_html_e( 'Generic names: "Sale1", "Campaign"', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( 'Special characters: "@#$%"', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( 'Vague names: "Discount", "Promo"', 'smart-cycle-discounts' ); ?></li>
				</ul>
			</div>

			<div class="scd-naming-tip">
				<span class="dashicons dashicons-lightbulb"></span>
				<p><?php esc_html_e( 'Include the discount type and amount in the name for quick reference', 'smart-cycle-discounts' ); ?></p>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Naming Best Practices', 'smart-cycle-discounts' ),
			'edit',
			ob_get_clean(),
			'open' // Important guidance - keep open
		);

		// Getting Started section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li><?php esc_html_e( 'Enter a descriptive campaign name for internal reference', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Set priority (1-10) to control which campaign applies first', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Add an optional description to remember campaign details', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Getting Started', 'smart-cycle-discounts' ),
			'info',
			ob_get_clean(),
			'open' // Critical first step info - keep open
		);
		
		// Priority Guide section
		ob_start();
		?>
		<div class="scd-priority-guide">
			<div class="scd-priority-explainer">
				<p class="scd-priority-description">
					<?php esc_html_e( 'When products match multiple campaigns, the highest priority wins. If priorities are equal, the first created campaign wins.', 'smart-cycle-discounts' ); ?>
				</p>

				<div class="scd-priority-levels">
					<div class="scd-priority-level scd-priority-high">
						<div class="scd-priority-range">10</div>
						<div class="scd-priority-label"><?php esc_html_e( 'Critical', 'smart-cycle-discounts' ); ?></div>
						<div class="scd-priority-use"><?php esc_html_e( 'VIP sales, urgent promotions', 'smart-cycle-discounts' ); ?></div>
					</div>

					<div class="scd-priority-level scd-priority-medium-high">
						<div class="scd-priority-range">7-9</div>
						<div class="scd-priority-label"><?php esc_html_e( 'High', 'smart-cycle-discounts' ); ?></div>
						<div class="scd-priority-use"><?php esc_html_e( 'Flash sales, time-sensitive', 'smart-cycle-discounts' ); ?></div>
					</div>

					<div class="scd-priority-level scd-priority-medium">
						<div class="scd-priority-range">4-6</div>
						<div class="scd-priority-label"><?php esc_html_e( 'Medium', 'smart-cycle-discounts' ); ?></div>
						<div class="scd-priority-use"><?php esc_html_e( 'Regular campaigns, defaults', 'smart-cycle-discounts' ); ?></div>
					</div>

					<div class="scd-priority-level scd-priority-low">
						<div class="scd-priority-range">1-3</div>
						<div class="scd-priority-label"><?php esc_html_e( 'Low', 'smart-cycle-discounts' ); ?></div>
						<div class="scd-priority-use"><?php esc_html_e( 'Background, fallback discounts', 'smart-cycle-discounts' ); ?></div>
					</div>
				</div>

				<p class="scd-priority-tip">
					<span class="dashicons dashicons-lightbulb"></span>
					<?php esc_html_e( 'Tip: Use priority 8+ for time-sensitive campaigns that should override regular discounts', 'smart-cycle-discounts' ); ?>
				</p>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Priority System', 'smart-cycle-discounts' ),
			'sort',
			ob_get_clean(),
			'open' // Important concept - keep open
		);
		
		// Tips section
		ob_start();
		?>
		<ul class="scd-sidebar-tips">
			<li><?php esc_html_e( 'Use clear, descriptive names to easily identify campaigns later', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Lower numbers mean higher priority when multiple campaigns overlap', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Pro Tips', 'smart-cycle-discounts' ),
			'lightbulb',
			ob_get_clean(),
			'collapsed' // Reference material - collapse by default
		);

		// Help & Resources section
		ob_start();
		?>
		<div class="scd-sidebar-resources">
			<p class="scd-sidebar-intro">
				<?php esc_html_e( 'Need assistance? Access our comprehensive help resources.', 'smart-cycle-discounts' ); ?>
			</p>

			<div class="scd-sidebar-links">
				<a href="#" class="scd-sidebar-link" target="_blank">
					<span class="dashicons dashicons-book"></span>
					<span><?php esc_html_e( 'Documentation', 'smart-cycle-discounts' ); ?></span>
				</a>
				<a href="#" class="scd-sidebar-link" target="_blank">
					<span class="dashicons dashicons-video-alt3"></span>
					<span><?php esc_html_e( 'Video Tutorials', 'smart-cycle-discounts' ); ?></span>
				</a>
				<a href="#" class="scd-sidebar-link" target="_blank">
					<span class="dashicons dashicons-sos"></span>
					<span><?php esc_html_e( 'Support Forum', 'smart-cycle-discounts' ); ?></span>
				</a>
			</div>

			<div class="scd-sidebar-faq">
				<p class="scd-sidebar-faq-title"><?php esc_html_e( 'Common Questions:', 'smart-cycle-discounts' ); ?></p>
				<ul class="scd-sidebar-faq-list">
					<li><?php esc_html_e( 'How does priority work?', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( 'How to avoid campaign conflicts?', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( 'What\'s the best discount type?', 'smart-cycle-discounts' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Help & Resources', 'smart-cycle-discounts' ),
			'info-outline',
			ob_get_clean(),
			'collapsed' // Reference material - collapse by default
		);
	}
}