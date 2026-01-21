<?php
/**
 * Campaign Overview Panel - Discounts Section
 *
 * Displays discount configuration with clean structure and CSS classes.
 * NO inline styles - all styling via CSS variables and classes.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components/partials
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Extract discount data
$wsscd_discount_type   = isset( $data['type'] ) ? $data['type'] : '';
$wsscd_formatted_value = isset( $data['formatted_value'] ) ? $data['formatted_value'] : '';
$wsscd_apply_to        = isset( $data['apply_to'] ) ? $data['apply_to'] : 'per_item';

// Type configuration
$wsscd_type_labels = array(
	'percentage'      => __( 'Percentage Discount', 'smart-cycle-discounts' ),
	'fixed'           => __( 'Fixed Amount Discount', 'smart-cycle-discounts' ),
	'bogo'            => __( 'Buy One Get One', 'smart-cycle-discounts' ),
	'tiered'          => __( 'Tiered Discount', 'smart-cycle-discounts' ),
	'spend_threshold' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
);

$wsscd_type_icons = array(
	'percentage'      => 'tag',
	'fixed'           => 'money-alt',
	'bogo'            => 'cart',
	'tiered'          => 'chart-line',
	'spend_threshold' => 'tag',
);

$wsscd_type_label = isset( $wsscd_type_labels[ $wsscd_discount_type ] ) ? $wsscd_type_labels[ $wsscd_discount_type ] : ucfirst( $wsscd_discount_type );
$wsscd_type_icon  = isset( $wsscd_type_icons[ $wsscd_discount_type ] ) ? $wsscd_type_icons[ $wsscd_discount_type ] : 'tag';

// Apply to labels
$wsscd_apply_to_labels = array(
	'per_item'   => __( 'Each Product Individually', 'smart-cycle-discounts' ),
	'cart_total' => __( 'Cart Subtotal', 'smart-cycle-discounts' ),
);
$wsscd_apply_to_label = isset( $wsscd_apply_to_labels[ $wsscd_apply_to ] ) ? $wsscd_apply_to_labels[ $wsscd_apply_to ] : $wsscd_apply_to;

// Complex types
$wsscd_is_simple_type   = ! in_array( $wsscd_discount_type, array( 'bogo', 'tiered', 'spend_threshold' ), true );
$wsscd_bogo_config      = isset( $data['bogo_config'] ) ? $data['bogo_config'] : null;
$wsscd_tiered_config    = isset( $data['tiered_config'] ) ? $data['tiered_config'] : null;
$wsscd_threshold_config = isset( $data['threshold_config'] ) ? $data['threshold_config'] : null;

// Usage limits
$wsscd_max_uses              = isset( $data['max_uses'] ) ? absint( $data['max_uses'] ) : null;
$wsscd_max_uses_per_customer = isset( $data['max_uses_per_customer'] ) ? absint( $data['max_uses_per_customer'] ) : null;
$wsscd_current_uses          = isset( $data['current_uses'] ) ? absint( $data['current_uses'] ) : 0;
$wsscd_usage_percentage      = isset( $data['usage_percentage'] ) ? floatval( $data['usage_percentage'] ) : 0;

// Minimum requirements
$wsscd_min_order_amount = isset( $data['min_order_amount'] ) ? floatval( $data['min_order_amount'] ) : null;
$wsscd_min_quantity     = isset( $data['min_quantity'] ) ? absint( $data['min_quantity'] ) : null;

// Restrictions
$wsscd_exclude_sale_items = isset( $data['exclude_sale_items'] ) ? $data['exclude_sale_items'] : false;
$wsscd_individual_use     = isset( $data['individual_use'] ) ? $data['individual_use'] : false;
$wsscd_free_shipping      = isset( $data['free_shipping'] ) ? $data['free_shipping'] : false;
?>

<div class="wsscd-overview-subsection">
	<div class="wsscd-subsection-header">
		<?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Discount Configuration', 'smart-cycle-discounts' ); ?></h5>
	</div>

	<!-- Discount Info Row -->
	<div class="wsscd-discount-info-row">
		<div class="wsscd-discount-info-item">
			<div class="wsscd-discount-info-label"><?php esc_html_e( 'Type', 'smart-cycle-discounts' ); ?></div>
			<div class="wsscd-discount-info-value">
				<?php WSSCD_Icon_Helper::render( $wsscd_type_icon, array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $wsscd_type_label ); ?></span>
			</div>
		</div>

		<?php if ( $wsscd_is_simple_type && ! empty( $wsscd_formatted_value ) ) : ?>
			<div class="wsscd-discount-info-item">
				<div class="wsscd-discount-info-label"><?php esc_html_e( 'Value', 'smart-cycle-discounts' ); ?></div>
				<div class="wsscd-discount-info-value wsscd-discount-value-highlight">
					<?php echo wp_kses_post( $wsscd_formatted_value ); ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="wsscd-discount-info-item">
			<div class="wsscd-discount-info-label"><?php esc_html_e( 'Apply To', 'smart-cycle-discounts' ); ?></div>
			<div class="wsscd-discount-info-value">
				<?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $wsscd_apply_to_label ); ?></span>
			</div>
		</div>
	</div>

	<!-- Complex Discount Types -->
	<?php if ( 'bogo' === $wsscd_discount_type && ! empty( $wsscd_bogo_config ) ) : ?>
		<div class="wsscd-bogo-display-card">
			<?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 20 ) ); ?>
			<div class="wsscd-bogo-description">
				<?php echo esc_html( $wsscd_bogo_config['description'] ); ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( 'tiered' === $wsscd_discount_type && ! empty( $wsscd_tiered_config['tiers'] ) ) : ?>
		<div class="wsscd-tiered-discounts">
			<div class="wsscd-tiered-header">
				<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $wsscd_tiered_config['description'] ); ?></span>
			</div>

			<?php
			// Calculate maximum savings for display
			$wsscd_max_tier         = end( $wsscd_tiered_config['tiers'] );
			$wsscd_max_discount     = ! empty( $wsscd_max_tier['discount_value'] ) ? $wsscd_max_tier['discount_value'] : 0;
			$wsscd_max_is_percentage = ! empty( $wsscd_max_tier['is_percentage'] );
			if ( $wsscd_max_discount > 0 ) :
				?>
				<div class="wsscd-discount-highlight">
					<?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 16 ) ); ?>
					<span>
						<?php
						if ( $wsscd_max_is_percentage ) {
							printf(
								/* translators: %s: maximum discount percentage */
								esc_html__( 'Save up to %s%%', 'smart-cycle-discounts' ),
								esc_html( number_format_i18n( $wsscd_max_discount, 0 ) )
							);
						} else {
							printf(
								/* translators: %s: maximum discount amount */
								esc_html__( 'Save up to %s', 'smart-cycle-discounts' ),
								wp_kses_post( wc_price( $wsscd_max_discount ) )
							);
						}
						?>
					</span>
				</div>
			<?php endif; ?>

			<ul class="wsscd-tiered-list">
				<?php foreach ( $wsscd_tiered_config['tiers'] as $wsscd_tier ) : ?>
					<li class="wsscd-tier-item">
						<span class="wsscd-tier-range">
							<?php
							if ( empty( $wsscd_tier['max_quantity'] ) ) {
								printf(
									/* translators: %d: minimum quantity */
									esc_html__( '%d+ items:', 'smart-cycle-discounts' ),
									absint( $wsscd_tier['min_quantity'] )
								);
							} else {
								printf(
									/* translators: 1: minimum quantity, 2: maximum quantity */
									esc_html__( '%1$d-%2$d items:', 'smart-cycle-discounts' ),
									absint( $wsscd_tier['min_quantity'] ),
									absint( $wsscd_tier['max_quantity'] )
								);
							}
							?>
						</span>
						<span class="wsscd-tier-discount"><?php echo wp_kses_post( $wsscd_tier['formatted'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( 'spend_threshold' === $wsscd_discount_type && ! empty( $wsscd_threshold_config['thresholds'] ) ) : ?>
		<div class="wsscd-threshold-discounts">
			<div class="wsscd-threshold-header">
				<?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $wsscd_threshold_config['description'] ); ?></span>
			</div>
			<ul class="wsscd-threshold-list">
				<?php foreach ( $wsscd_threshold_config['thresholds'] as $wsscd_threshold ) : ?>
					<li class="wsscd-threshold-item">
						<?php echo wp_kses_post( $wsscd_threshold['formatted'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Minimum Requirements -->
	<?php if ( $wsscd_min_order_amount || $wsscd_min_quantity ) : ?>
		<div class="wsscd-discount-requirements">
			<div class="wsscd-requirements-header">
				<?php WSSCD_Icon_Helper::render( 'saved', array( 'size' => 16 ) ); ?>
				<span><?php esc_html_e( 'Minimum Requirements', 'smart-cycle-discounts' ); ?></span>
			</div>
			<ul class="wsscd-requirements-list">
				<?php if ( $wsscd_min_order_amount ) : ?>
					<li>
						<?php WSSCD_Icon_Helper::render( 'money-alt', array( 'size' => 14 ) ); ?>
						<?php
						printf(
							/* translators: %s: minimum order amount */
							esc_html__( 'Minimum order: %s', 'smart-cycle-discounts' ),
							wp_kses_post( wc_price( $wsscd_min_order_amount ) )
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( $wsscd_min_quantity ) : ?>
					<li>
						<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 14 ) ); ?>
						<?php
						printf(
							/* translators: %d: minimum quantity */
							esc_html__( 'Minimum quantity: %d items', 'smart-cycle-discounts' ),
							absint( $wsscd_min_quantity )
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Restrictions -->
	<?php if ( $wsscd_exclude_sale_items || $wsscd_individual_use || $wsscd_free_shipping ) : ?>
		<div class="wsscd-discount-restrictions">
			<div class="wsscd-restrictions-header">
				<?php WSSCD_Icon_Helper::render( 'lock', array( 'size' => 16 ) ); ?>
				<span><?php esc_html_e( 'Restrictions', 'smart-cycle-discounts' ); ?></span>
			</div>
			<ul class="wsscd-restrictions-list">
				<?php if ( $wsscd_exclude_sale_items ) : ?>
					<li>
						<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Exclude sale items', 'smart-cycle-discounts' ); ?>
					</li>
				<?php endif; ?>
				<?php if ( $wsscd_individual_use ) : ?>
					<li>
						<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Individual use only', 'smart-cycle-discounts' ); ?>
					</li>
				<?php endif; ?>
				<?php if ( $wsscd_free_shipping ) : ?>
					<li>
						<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Free shipping', 'smart-cycle-discounts' ); ?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Usage Limits -->
	<?php if ( $wsscd_max_uses || $wsscd_max_uses_per_customer ) : ?>
		<div class="wsscd-discount-usage">
			<div class="wsscd-usage-header">
				<?php WSSCD_Icon_Helper::render( 'chart-bar', array( 'size' => 16 ) ); ?>
				<span><?php esc_html_e( 'Usage Limits', 'smart-cycle-discounts' ); ?></span>
			</div>

			<?php if ( $wsscd_max_uses ) : ?>
				<div class="wsscd-usage-limit-item">
					<div class="wsscd-usage-limit-label">
						<?php esc_html_e( 'Total uses:', 'smart-cycle-discounts' ); ?>
						<span class="wsscd-usage-count"><?php echo absint( $wsscd_current_uses ); ?> / <?php echo absint( $wsscd_max_uses ); ?></span>
					</div>
					<div class="wsscd-usage-progress-bar">
						<div class="wsscd-usage-progress-fill <?php echo esc_attr( $wsscd_usage_percentage >= 80 ? 'wsscd-usage-high' : '' ); ?>" style="width: <?php echo esc_attr( min( 100, $wsscd_usage_percentage ) ); ?>%;"></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $wsscd_max_uses_per_customer ) : ?>
				<div class="wsscd-usage-limit-text">
					<?php WSSCD_Icon_Helper::render( 'admin-users', array( 'size' => 14 ) ); ?>
					<?php
					printf(
						/* translators: %d: maximum uses per customer */
						esc_html__( 'Maximum %d uses per customer', 'smart-cycle-discounts' ),
						absint( $wsscd_max_uses_per_customer )
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
