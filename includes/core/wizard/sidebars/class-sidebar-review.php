<?php
/**
 * Review Step Sidebar Class
 *
 * Provides contextual help and dynamic campaign summary for the review step.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/sidebars
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
 * Review step sidebar class
 *
 * @since 1.0.0
 */
class SCD_Wizard_Sidebar_Review extends SCD_Wizard_Sidebar_Base {

	/**
	 * Wizard State Service instance
	 *
	 * @since  1.0.0
	 * @var    SCD_Wizard_State_Service
	 */
	private $state_service;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param SCD_Wizard_State_Service $state_service State service instance.
	 */
	public function __construct( SCD_Wizard_State_Service $state_service ) {
		$this->state_service = $state_service;
	}

	/**
	 * Get sidebar content
	 *
	 * @since  1.0.0
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
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function render_sections() {
		$basic_data     = $this->get_step_data_safe( 'basic' );
		$products_data  = $this->get_step_data_safe( 'products' );
		$discounts_data = $this->get_step_data_safe( 'discounts' );
		$schedule_data  = $this->get_step_data_safe( 'schedule' );

		// Campaign Overview section
		ob_start();
		?>
		<div class="scd-sidebar-summary">
			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Name', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( $this->get_campaign_name( $basic_data ) ); ?></div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Priority', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value">
					<span class="scd-priority-badge scd-priority-<?php echo esc_attr( $this->get_priority( $basic_data ) ); ?>">
						<?php echo esc_html( $this->get_priority( $basic_data ) ); ?>
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
			'open'
		);

		// Discount Details section
		ob_start();
		?>
		<div class="scd-sidebar-details">
			<?php echo wp_kses_post( $this->render_discount_details( $discounts_data ) ); ?>
		</div>
		<?php
		$this->render_section(
			__( 'Discount Details', 'smart-cycle-discounts' ),
			'tag',
			ob_get_clean(),
			'collapsed'
		);

		// Product Details section
		ob_start();
		?>
		<div class="scd-sidebar-details">
			<?php echo wp_kses_post( $this->render_product_details( $products_data ) ); ?>
		</div>
		<?php
		$this->render_section(
			__( 'Product Details', 'smart-cycle-discounts' ),
			'products',
			ob_get_clean(),
			'collapsed'
		);

		// Schedule Details section
		ob_start();
		?>
		<div class="scd-sidebar-details">
			<?php echo wp_kses_post( $this->render_schedule_details( $schedule_data ) ); ?>
		</div>
		<?php
		$this->render_section(
			__( 'Schedule Details', 'smart-cycle-discounts' ),
			'calendar-alt',
			ob_get_clean(),
			'collapsed'
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
			'open'
		);

		// Pre-Launch Checklist section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li>
				<strong><?php esc_html_e( 'Resolve Warnings', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Fix all validation errors shown above', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Check Conflicts', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Ensure no overlapping campaigns on same products', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Verify Margins', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Confirm discount doesn\'t exceed profit threshold', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Test First', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Save as draft and verify on storefront before publishing', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Set Limits', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Consider usage caps to prevent budget overruns', 'smart-cycle-discounts' ); ?>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'Pre-Launch Checklist', 'smart-cycle-discounts' ),
			'yes-alt',
			ob_get_clean(),
			'open'
		);
	}

	/**
	 * Get step data safely with error handling
	 *
	 * @since  1.0.0
	 * @param  string $step Step name.
	 * @return array         Step data or empty array
	 */
	private function get_step_data_safe( $step ) {
		if ( ! $this->state_service ) {
			return array();
		}

		try {
			$data = $this->state_service->get_step_data( $step );
			return is_array( $data ) ? $data : array();
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SCD Review Sidebar: Failed to get step data for ' . $step . ': ' . $e->getMessage() );
			}
			return array();
		}
	}

	/**
	 * Get campaign name with fallback
	 *
	 * @since  1.0.0
	 * @param  array $basic_data Basic step data.
	 * @return string             Campaign name
	 */
	private function get_campaign_name( $basic_data ) {
		if ( ! empty( $basic_data['name'] ) ) {
			return $basic_data['name'];
		}
		return __( 'Not set', 'smart-cycle-discounts' );
	}

	/**
	 * Get priority with fallback
	 *
	 * @since  1.0.0
	 * @param  array $basic_data Basic step data.
	 * @return string             Priority value
	 */
	private function get_priority( $basic_data ) {
		if ( isset( $basic_data['priority'] ) ) {
			return $basic_data['priority'];
		}
		return '5';
	}

	/**
	 * Get discount display string
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts data.
	 * @return string                 Discount display
	 */
	private function get_discount_display( $discounts_data ) {
		if ( empty( $discounts_data ) ) {
			return __( 'Not set', 'smart-cycle-discounts' );
		}

		$type = isset( $discounts_data['discount_type'] ) ? $discounts_data['discount_type'] : 'percentage';

		// Wizard stores percentage/fixed in separate fields before entity transformation
		$value = '0';
		if ( 'percentage' === $type && isset( $discounts_data['discount_value_percentage'] ) ) {
			$value = $discounts_data['discount_value_percentage'];
		} elseif ( 'fixed' === $type && isset( $discounts_data['discount_value_fixed'] ) ) {
			$value = $discounts_data['discount_value_fixed'];
		} elseif ( isset( $discounts_data['discount_value'] ) ) {
			// Fallback for already-transformed data
			$value = $discounts_data['discount_value'];
		}

		switch ( $type ) {
			case 'percentage':
				return $value . '%';

			case 'fixed':
				if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
					return get_woocommerce_currency_symbol() . $value;
				}
				return '$' . $value;

			case 'tiered':
				$tier_count = isset( $discounts_data['tiers'] ) && is_array( $discounts_data['tiers'] ) ? count( $discounts_data['tiers'] ) : 0;
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
	 * Get product count display string
	 *
	 * @since  1.0.0
	 * @param  array $products_data Products data.
	 * @return string                Product count display
	 */
	private function get_product_count( $products_data ) {
		if ( empty( $products_data ) ) {
			return __( 'Not set', 'smart-cycle-discounts' );
		}

		$selection = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';

		if ( 'all_products' === $selection ) {
			return __( 'All Products', 'smart-cycle-discounts' );
		} elseif ( 'specific_products' === $selection && ! empty( $products_data['product_ids'] ) && is_array( $products_data['product_ids'] ) ) {
			$count = count( $products_data['product_ids'] );
			return sprintf( _n( '%d Product', '%d Products', $count, 'smart-cycle-discounts' ), $count );
		} elseif ( 'categories' === $selection && ! empty( $products_data['category_ids'] ) && is_array( $products_data['category_ids'] ) ) {
			$count = count( $products_data['category_ids'] );
			return sprintf( _n( '%d Category', '%d Categories', $count, 'smart-cycle-discounts' ), $count );
		} else {
			return ucfirst( str_replace( '_', ' ', $selection ) );
		}
	}

	/**
	 * Get schedule duration display string
	 *
	 * @since  1.0.0
	 * @param  array $schedule_data Schedule data.
	 * @return string                Duration display
	 */
	private function get_schedule_duration( $schedule_data ) {
		if ( empty( $schedule_data ) ) {
			return __( 'Not set', 'smart-cycle-discounts' );
		}

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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SCD Review Sidebar: Invalid date format - ' . $e->getMessage() );
			}
			return __( 'Invalid dates', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Render detailed discount information
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts data.
	 * @return string                 HTML content
	 */
	private function render_discount_details( $discounts_data ) {
		if ( empty( $discounts_data ) ) {
			return '<p>' . esc_html__( 'No discount information available', 'smart-cycle-discounts' ) . '</p>';
		}

		$details = array();

		// Usage Limits
		if ( ! empty( $discounts_data['usage_limit_per_customer'] ) ) {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Per Customer Limit', 'smart-cycle-discounts' ),
				esc_html( $discounts_data['usage_limit_per_customer'] )
			);
		}

		if ( ! empty( $discounts_data['total_usage_limit'] ) ) {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Total Usage Limit', 'smart-cycle-discounts' ),
				esc_html( $discounts_data['total_usage_limit'] )
			);
		}

		// Order Requirements
		if ( ! empty( $discounts_data['minimum_order_amount'] ) ) {
			$symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
			$details[] = sprintf(
				'<strong>%s:</strong> %s%s',
				esc_html__( 'Minimum Order', 'smart-cycle-discounts' ),
				esc_html( $symbol ),
				esc_html( $discounts_data['minimum_order_amount'] )
			);
		}

		if ( ! empty( $discounts_data['minimum_quantity'] ) ) {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Minimum Quantity', 'smart-cycle-discounts' ),
				esc_html( $discounts_data['minimum_quantity'] )
			);
		}

		if ( ! empty( $discounts_data['max_discount_amount'] ) ) {
			$symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
			$details[] = sprintf(
				'<strong>%s:</strong> %s%s',
				esc_html__( 'Maximum Discount', 'smart-cycle-discounts' ),
				esc_html( $symbol ),
				esc_html( $discounts_data['max_discount_amount'] )
			);
		}

		// Combination Rules
		$stacking = isset( $discounts_data['stack_with_others'] ) && $discounts_data['stack_with_others'] ? __( 'Yes', 'smart-cycle-discounts' ) : __( 'No', 'smart-cycle-discounts' );
		$details[] = sprintf(
			'<strong>%s:</strong> %s',
			esc_html__( 'Stack with Others', 'smart-cycle-discounts' ),
			esc_html( $stacking )
		);

		$coupons = isset( $discounts_data['allow_coupons'] ) && $discounts_data['allow_coupons'] ? __( 'Yes', 'smart-cycle-discounts' ) : __( 'No', 'smart-cycle-discounts' );
		$details[] = sprintf(
			'<strong>%s:</strong> %s',
			esc_html__( 'Allow Coupons', 'smart-cycle-discounts' ),
			esc_html( $coupons )
		);

		$sale_items = isset( $discounts_data['apply_to_sale_items'] ) && $discounts_data['apply_to_sale_items'] ? __( 'Yes', 'smart-cycle-discounts' ) : __( 'No', 'smart-cycle-discounts' );
		$details[] = sprintf(
			'<strong>%s:</strong> %s',
			esc_html__( 'Apply to Sale Items', 'smart-cycle-discounts' ),
			esc_html( $sale_items )
		);

		return '<ul class="scd-details-list"><li>' . implode( '</li><li>', $details ) . '</li></ul>';
	}

	/**
	 * Render detailed product information
	 *
	 * @since  1.0.0
	 * @param  array $products_data Products data.
	 * @return string                HTML content
	 */
	private function render_product_details( $products_data ) {
		if ( empty( $products_data ) ) {
			return '<p>' . esc_html__( 'No product information available', 'smart-cycle-discounts' ) . '</p>';
		}

		$details = array();

		$selection = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';
		$details[] = sprintf(
			'<strong>%s:</strong> %s',
			esc_html__( 'Selection Type', 'smart-cycle-discounts' ),
			esc_html( ucwords( str_replace( '_', ' ', $selection ) ) )
		);

		// Specific Products
		if ( 'specific_products' === $selection && ! empty( $products_data['product_ids'] ) && is_array( $products_data['product_ids'] ) ) {
			$count     = count( $products_data['product_ids'] );
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Selected Products', 'smart-cycle-discounts' ),
				sprintf( esc_html( _n( '%d product', '%d products', $count, 'smart-cycle-discounts' ) ), $count )
			);
		}

		// Categories
		if ( 'categories' === $selection && ! empty( $products_data['category_ids'] ) && is_array( $products_data['category_ids'] ) ) {
			$count     = count( $products_data['category_ids'] );
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Selected Categories', 'smart-cycle-discounts' ),
				sprintf( esc_html( _n( '%d category', '%d categories', $count, 'smart-cycle-discounts' ) ), $count )
			);
		}

		// Conditions
		if ( ! empty( $products_data['conditions'] ) && is_array( $products_data['conditions'] ) ) {
			$count     = count( $products_data['conditions'] );
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Conditions', 'smart-cycle-discounts' ),
				sprintf( esc_html( _n( '%d condition', '%d conditions', $count, 'smart-cycle-discounts' ) ), $count )
			);
		}

		return '<ul class="scd-details-list"><li>' . implode( '</li><li>', $details ) . '</li></ul>';
	}

	/**
	 * Render detailed schedule information
	 *
	 * @since  1.0.0
	 * @param  array $schedule_data Schedule data.
	 * @return string                HTML content
	 */
	private function render_schedule_details( $schedule_data ) {
		if ( empty( $schedule_data ) ) {
			return '<p>' . esc_html__( 'No schedule information available', 'smart-cycle-discounts' ) . '</p>';
		}

		$details = array();

		// Start Date
		if ( ! empty( $schedule_data['start_date'] ) ) {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Start Date', 'smart-cycle-discounts' ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $schedule_data['start_date'] ) ) )
			);
		}

		// End Date
		if ( ! empty( $schedule_data['end_date'] ) ) {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'End Date', 'smart-cycle-discounts' ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $schedule_data['end_date'] ) ) )
			);
		} else {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'End Date', 'smart-cycle-discounts' ),
				esc_html__( 'No end date (ongoing)', 'smart-cycle-discounts' )
			);
		}

		// Recurring
		if ( ! empty( $schedule_data['rotation_enabled'] ) ) {
			$interval = isset( $schedule_data['rotation_interval'] ) ? $schedule_data['rotation_interval'] : '';
			$unit     = isset( $schedule_data['rotation_unit'] ) ? $schedule_data['rotation_unit'] : 'days';
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Recurring', 'smart-cycle-discounts' ),
				sprintf( esc_html__( 'Every %s %s', 'smart-cycle-discounts' ), esc_html( $interval ), esc_html( $unit ) )
			);
		} else {
			$details[] = sprintf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Recurring', 'smart-cycle-discounts' ),
				esc_html__( 'No', 'smart-cycle-discounts' )
			);
		}

		// Timezone
		$details[] = sprintf(
			'<strong>%s:</strong> %s',
			esc_html__( 'Timezone', 'smart-cycle-discounts' ),
			esc_html( wp_timezone_string() )
		);

		return '<ul class="scd-details-list"><li>' . implode( '</li><li>', $details ) . '</li></ul>';
	}
}
