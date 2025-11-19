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
$discount_type   = isset( $data['type'] ) ? $data['type'] : '';
$formatted_value = isset( $data['formatted_value'] ) ? $data['formatted_value'] : '';
$apply_to        = isset( $data['apply_to'] ) ? $data['apply_to'] : 'per_item';

// Type configuration
$type_labels = array(
	'percentage'      => __( 'Percentage Discount', 'smart-cycle-discounts' ),
	'fixed'           => __( 'Fixed Amount Discount', 'smart-cycle-discounts' ),
	'bogo'            => __( 'Buy One Get One', 'smart-cycle-discounts' ),
	'tiered'          => __( 'Tiered Discount', 'smart-cycle-discounts' ),
	'spend_threshold' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
);

$type_icons = array(
	'percentage'      => 'tag',
	'fixed'           => 'money-alt',
	'bogo'            => 'cart',
	'tiered'          => 'chart-line',
	'spend_threshold' => 'tag',
);

$type_label = isset( $type_labels[ $discount_type ] ) ? $type_labels[ $discount_type ] : ucfirst( $discount_type );
$type_icon  = isset( $type_icons[ $discount_type ] ) ? $type_icons[ $discount_type ] : 'tag';

// Apply to labels
$apply_to_labels = array(
	'per_item'   => __( 'Each Product Individually', 'smart-cycle-discounts' ),
	'cart_total' => __( 'Cart Subtotal', 'smart-cycle-discounts' ),
);
$apply_to_label = isset( $apply_to_labels[ $apply_to ] ) ? $apply_to_labels[ $apply_to ] : $apply_to;

// Complex types
$is_simple_type = ! in_array( $discount_type, array( 'bogo', 'tiered', 'spend_threshold' ), true );
$bogo_config    = isset( $data['bogo_config'] ) ? $data['bogo_config'] : null;
$tiered_config  = isset( $data['tiered_config'] ) ? $data['tiered_config'] : null;
$threshold_config = isset( $data['threshold_config'] ) ? $data['threshold_config'] : null;

// Usage limits
$max_uses              = isset( $data['max_uses'] ) ? absint( $data['max_uses'] ) : null;
$max_uses_per_customer = isset( $data['max_uses_per_customer'] ) ? absint( $data['max_uses_per_customer'] ) : null;
$current_uses          = isset( $data['current_uses'] ) ? absint( $data['current_uses'] ) : 0;
$usage_percentage      = isset( $data['usage_percentage'] ) ? floatval( $data['usage_percentage'] ) : 0;

// Minimum requirements
$min_order_amount = isset( $data['min_order_amount'] ) ? floatval( $data['min_order_amount'] ) : null;
$min_quantity     = isset( $data['min_quantity'] ) ? absint( $data['min_quantity'] ) : null;

// Restrictions
$exclude_sale_items = isset( $data['exclude_sale_items'] ) ? $data['exclude_sale_items'] : false;
$individual_use     = isset( $data['individual_use'] ) ? $data['individual_use'] : false;
$free_shipping      = isset( $data['free_shipping'] ) ? $data['free_shipping'] : false;
?>

<div class="scd-overview-subsection">
	<div class="scd-subsection-header">
		<?php echo SCD_Icon_Helper::get( 'tag', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Discount Configuration', 'smart-cycle-discounts' ); ?></h5>
	</div>

	<!-- Discount Info Row -->
	<div class="scd-discount-info-row">
		<div class="scd-discount-info-item">
			<div class="scd-discount-info-label"><?php esc_html_e( 'Type', 'smart-cycle-discounts' ); ?></div>
			<div class="scd-discount-info-value">
				<?php echo SCD_Icon_Helper::get( $type_icon, array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $type_label ); ?></span>
			</div>
		</div>

		<?php if ( $is_simple_type && ! empty( $formatted_value ) ) : ?>
			<div class="scd-discount-info-item">
				<div class="scd-discount-info-label"><?php esc_html_e( 'Value', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-discount-info-value scd-discount-value-highlight">
					<?php echo wp_kses_post( $formatted_value ); ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="scd-discount-info-item">
			<div class="scd-discount-info-label"><?php esc_html_e( 'Apply To', 'smart-cycle-discounts' ); ?></div>
			<div class="scd-discount-info-value">
				<?php echo SCD_Icon_Helper::get( 'cart', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $apply_to_label ); ?></span>
			</div>
		</div>
	</div>

	<!-- Complex Discount Types -->
	<?php if ( 'bogo' === $discount_type && ! empty( $bogo_config ) ) : ?>
		<div class="scd-bogo-display-card">
			<?php echo SCD_Icon_Helper::get( 'cart', array( 'size' => 20 ) ); ?>
			<div class="scd-bogo-description">
				<?php echo esc_html( $bogo_config['description'] ); ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( 'tiered' === $discount_type && ! empty( $tiered_config['tiers'] ) ) : ?>
		<div class="scd-tiered-discounts">
			<div class="scd-tiered-header">
				<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $tiered_config['description'] ); ?></span>
			</div>
			<ul class="scd-tiered-list">
				<?php foreach ( $tiered_config['tiers'] as $tier ) : ?>
					<li class="scd-tier-item">
						<span class="scd-tier-range">
							<?php
							if ( empty( $tier['max_quantity'] ) ) {
								printf(
									/* translators: %d: minimum quantity */
									esc_html__( '%d+ items:', 'smart-cycle-discounts' ),
									absint( $tier['min_quantity'] )
								);
							} else {
								printf(
									/* translators: 1: minimum quantity, 2: maximum quantity */
									esc_html__( '%1$d-%2$d items:', 'smart-cycle-discounts' ),
									absint( $tier['min_quantity'] ),
									absint( $tier['max_quantity'] )
								);
							}
							?>
						</span>
						<span class="scd-tier-discount"><?php echo esc_html( $tier['formatted'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( 'spend_threshold' === $discount_type && ! empty( $threshold_config['thresholds'] ) ) : ?>
		<div class="scd-threshold-discounts">
			<div class="scd-threshold-header">
				<?php echo SCD_Icon_Helper::get( 'tag', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $threshold_config['description'] ); ?></span>
			</div>
			<ul class="scd-threshold-list">
				<?php foreach ( $threshold_config['thresholds'] as $threshold ) : ?>
					<li class="scd-threshold-item">
						<?php echo esc_html( $threshold['formatted'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Minimum Requirements -->
	<?php if ( $min_order_amount || $min_quantity ) : ?>
		<div class="scd-discount-requirements">
			<div class="scd-requirements-header">
				<?php echo SCD_Icon_Helper::get( 'saved', array( 'size' => 16 ) ); ?>
				<span><?php esc_html_e( 'Minimum Requirements', 'smart-cycle-discounts' ); ?></span>
			</div>
			<ul class="scd-requirements-list">
				<?php if ( $min_order_amount ) : ?>
					<li>
						<?php echo SCD_Icon_Helper::get( 'money-alt', array( 'size' => 14 ) ); ?>
						<?php
						printf(
							/* translators: %s: minimum order amount */
							esc_html__( 'Minimum order: %s', 'smart-cycle-discounts' ),
							wp_kses_post( wc_price( $min_order_amount ) )
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( $min_quantity ) : ?>
					<li>
						<?php echo SCD_Icon_Helper::get( 'products', array( 'size' => 14 ) ); ?>
						<?php
						printf(
							/* translators: %d: minimum quantity */
							esc_html__( 'Minimum quantity: %d items', 'smart-cycle-discounts' ),
							absint( $min_quantity )
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Restrictions -->
	<?php if ( $exclude_sale_items || $individual_use || $free_shipping ) : ?>
		<div class="scd-discount-restrictions">
			<div class="scd-restrictions-header">
				<?php echo SCD_Icon_Helper::get( 'lock', array( 'size' => 16 ) ); ?>
				<span><?php esc_html_e( 'Restrictions', 'smart-cycle-discounts' ); ?></span>
			</div>
			<ul class="scd-restrictions-list">
				<?php if ( $exclude_sale_items ) : ?>
					<li>
						<?php echo SCD_Icon_Helper::get( 'yes', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Exclude sale items', 'smart-cycle-discounts' ); ?>
					</li>
				<?php endif; ?>
				<?php if ( $individual_use ) : ?>
					<li>
						<?php echo SCD_Icon_Helper::get( 'yes', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Individual use only', 'smart-cycle-discounts' ); ?>
					</li>
				<?php endif; ?>
				<?php if ( $free_shipping ) : ?>
					<li>
						<?php echo SCD_Icon_Helper::get( 'yes', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Free shipping', 'smart-cycle-discounts' ); ?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Usage Limits -->
	<?php if ( $max_uses || $max_uses_per_customer ) : ?>
		<div class="scd-discount-usage">
			<div class="scd-usage-header">
				<?php echo SCD_Icon_Helper::get( 'chart-bar', array( 'size' => 16 ) ); ?>
				<span><?php esc_html_e( 'Usage Limits', 'smart-cycle-discounts' ); ?></span>
			</div>

			<?php if ( $max_uses ) : ?>
				<div class="scd-usage-limit-item">
					<div class="scd-usage-limit-label">
						<?php esc_html_e( 'Total uses:', 'smart-cycle-discounts' ); ?>
						<span class="scd-usage-count"><?php echo absint( $current_uses ); ?> / <?php echo absint( $max_uses ); ?></span>
					</div>
					<div class="scd-usage-progress-bar">
						<div class="scd-usage-progress-fill <?php echo $usage_percentage >= 80 ? 'scd-usage-high' : ''; ?>" style="width: <?php echo esc_attr( min( 100, $usage_percentage ) ); ?>%;"></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $max_uses_per_customer ) : ?>
				<div class="scd-usage-limit-text">
					<?php echo SCD_Icon_Helper::get( 'admin-users', array( 'size' => 14 ) ); ?>
					<?php
					printf(
						/* translators: %d: maximum uses per customer */
						esc_html__( 'Maximum %d uses per customer', 'smart-cycle-discounts' ),
						absint( $max_uses_per_customer )
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
