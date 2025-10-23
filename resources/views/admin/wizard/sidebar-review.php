<?php
/**
 * Review Step Sidebar - Compact Summary
 *
 * Compact campaign summary for the review step sidebar.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review step sidebar class
 */
class SCD_Wizard_Sidebar_Review extends SCD_Wizard_Sidebar_Base {

	/**
	 * Get sidebar content
	 *
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Campaign Summary', 'smart-cycle-discounts' ),
			__( 'Review your campaign settings before launching', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Render sidebar sections
	 */
	protected function render_sections() {
		// Get campaign data from all steps
		require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
		$state_service = new SCD_Wizard_State_Service();

		$basic_data     = $state_service->get_step_data( 'basic' );
		$products_data  = $state_service->get_step_data( 'products' );
		$discounts_data = $state_service->get_step_data( 'discounts' );
		$schedule_data  = $state_service->get_step_data( 'schedule' );

		// Campaign Overview section
		ob_start();
		?>
		<div class="scd-sidebar-summary">
			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Name', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( ! empty( $basic_data['name'] ) ? $basic_data['name'] : __( 'Not set', 'smart-cycle-discounts' ) ); ?></div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Priority', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value">
					<span class="scd-priority-badge scd-priority-<?php echo esc_attr( isset( $basic_data['priority'] ) ? $basic_data['priority'] : '5' ); ?>">
						<?php echo esc_html( isset( $basic_data['priority'] ) ? $basic_data['priority'] : '5' ); ?>
					</span>
				</div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Discount', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value scd-summary-highlight">
					<?php echo esc_html( $this->get_discount_display( $discounts_data ) ); ?>
				</div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Products', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( $this->get_product_count( $products_data ) ); ?></div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Duration', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( $this->get_schedule_duration( $schedule_data ) ); ?></div>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Overview', 'smart-cycle-discounts' ),
			'admin-settings',
			ob_get_clean(),
			'open' // Critical review info - keep open
		);

		// Quick Edit Links section
		ob_start();
		?>
		<div class="scd-quick-edit-links">
			<a href="#" class="scd-edit-link" data-step="basic">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit Basic Info', 'smart-cycle-discounts' ); ?>
			</a>
			<a href="#" class="scd-edit-link" data-step="products">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit Products', 'smart-cycle-discounts' ); ?>
			</a>
			<a href="#" class="scd-edit-link" data-step="discounts">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit Discounts', 'smart-cycle-discounts' ); ?>
			</a>
			<a href="#" class="scd-edit-link" data-step="schedule">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit Schedule', 'smart-cycle-discounts' ); ?>
			</a>
		</div>
		<?php
		$this->render_section(
			__( 'Quick Edit', 'smart-cycle-discounts' ),
			'admin-tools',
			ob_get_clean(),
			'open' // Useful navigation - keep open
		);

		// Help section
		ob_start();
		?>
		<ul class="scd-sidebar-tips">
			<li><?php esc_html_e( 'Review all warnings before launching', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Check for campaign conflicts', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Verify product coverage is sufficient', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Save as draft to test first', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Launch Tips', 'smart-cycle-discounts' ),
			'lightbulb',
			ob_get_clean(),
			'open' // Critical pre-launch tips - keep open
		);
	}

	/**
	 * Get discount display string.
	 *
	 * @param array $discounts_data Discounts data.
	 * @return string Discount display.
	 */
	private function get_discount_display( $discounts_data ) {
		$type  = isset( $discounts_data['discount_type'] ) ? $discounts_data['discount_type'] : 'percentage';
		$value = isset( $discounts_data['discount_value'] ) ? $discounts_data['discount_value'] : '0';

		switch ( $type ) {
			case 'percentage':
				return $value . '%';
			case 'fixed':
				return get_woocommerce_currency_symbol() . $value;
			case 'tiered':
				$tier_count = isset( $discounts_data['tiers'] ) ? count( $discounts_data['tiers'] ) : 0;
				return sprintf( _n( '%d Tier', '%d Tiers', $tier_count, 'smart-cycle-discounts' ), $tier_count );
			case 'bogo':
				$buy = isset( $discounts_data['bogo_buy_quantity'] ) ? $discounts_data['bogo_buy_quantity'] : 1;
				$get = isset( $discounts_data['bogo_get_quantity'] ) ? $discounts_data['bogo_get_quantity'] : 1;
				return sprintf( __( 'Buy %d Get %d', 'smart-cycle-discounts' ), $buy, $get );
			default:
				return ucfirst( str_replace( '_', ' ', $type ) );
		}
	}

	/**
	 * Get product count display string.
	 *
	 * @param array $products_data Products data.
	 * @return string Product count display.
	 */
	private function get_product_count( $products_data ) {
		$selection = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';

		if ( 'all_products' === $selection ) {
			return __( 'All Products', 'smart-cycle-discounts' );
		} elseif ( 'specific_products' === $selection && ! empty( $products_data['product_ids'] ) ) {
			$count = count( $products_data['product_ids'] );
			return sprintf( _n( '%d Product', '%d Products', $count, 'smart-cycle-discounts' ), $count );
		} elseif ( 'categories' === $selection && ! empty( $products_data['category_ids'] ) ) {
			$count = count( $products_data['category_ids'] );
			return sprintf( _n( '%d Category', '%d Categories', $count, 'smart-cycle-discounts' ), $count );
		} else {
			return ucfirst( str_replace( '_', ' ', $selection ) );
		}
	}

	/**
	 * Get schedule duration display string.
	 *
	 * @param array $schedule_data Schedule data.
	 * @return string Duration display.
	 */
	private function get_schedule_duration( $schedule_data ) {
		if ( ! empty( $schedule_data['enable_recurring'] ) ) {
			return __( 'Recurring', 'smart-cycle-discounts' );
		}

		if ( empty( $schedule_data['start_date'] ) || empty( $schedule_data['end_date'] ) ) {
			return __( 'No End Date', 'smart-cycle-discounts' );
		}

		try {
			$start    = new DateTime( $schedule_data['start_date'] );
			$end      = new DateTime( $schedule_data['end_date'] );
			$interval = $start->diff( $end );

			if ( $interval->days > 0 ) {
				return sprintf( _n( '%d Day', '%d Days', $interval->days, 'smart-cycle-discounts' ), $interval->days );
			} elseif ( $interval->h > 0 ) {
				return sprintf( _n( '%d Hour', '%d Hours', $interval->h, 'smart-cycle-discounts' ), $interval->h );
			} else {
				return __( 'Less than 1 hour', 'smart-cycle-discounts' );
			}
		} catch ( Exception $e ) {
			return __( 'Invalid dates', 'smart-cycle-discounts' );
		}
	}
}
