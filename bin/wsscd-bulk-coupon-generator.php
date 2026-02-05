<?php
/**
 * Bulk Coupon Generator - Custom one-off tool for WooCommerce
 *
 * Usage: Upload this file as a must-use plugin, then go to:
 *   WP Admin → Tools → Bulk Coupon Generator
 * Or run once via: WP Admin → WooCommerce → Coupons, then add ?page=wsscd-bulk-coupons to URL
 * if you register the page under WooCommerce.
 *
 * SAFETY: Delete this file after use, or restrict by capability.
 *
 * @package SmartCycleDiscounts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'wsscd_bulk_coupon_add_menu' );
add_action( 'admin_init', 'wsscd_bulk_coupon_handle_post' );

function wsscd_bulk_coupon_add_menu() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	add_management_page(
		__( 'Bulk Coupon Generator', 'smart-cycle-discounts' ),
		__( 'Bulk Coupons', 'smart-cycle-discounts' ),
		'manage_woocommerce',
		'wsscd-bulk-coupons',
		'wsscd_bulk_coupon_render_page'
	);
}

function wsscd_bulk_coupon_handle_post() {
	if ( ! isset( $_POST['wsscd_bulk_coupon_nonce'] ) || ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsscd_bulk_coupon_nonce'] ) ), 'wsscd_bulk_coupon' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$prefix   = isset( $_POST['prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix'] ) ) : 'SALE';
	$count    = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 10;
	$type     = isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : 'percent';
	$amount   = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 10;
	$usage    = isset( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : 1;
	$expiry   = isset( $_POST['expiry_days'] ) ? absint( $_POST['expiry_days'] ) : 0;
	$created  = 0;
	$errors   = array();

	$prefix = preg_replace( '/[^a-zA-Z0-9_-]/', '', $prefix );
	if ( strlen( $prefix ) === 0 ) {
		$prefix = 'COUPON';
	}
	$count = min( max( 1, $count ), 500 );

	$valid_types = array( 'percent', 'fixed_cart', 'fixed_product' );
	if ( ! in_array( $type, $valid_types, true ) ) {
		$type = 'percent';
	}

	for ( $i = 1; $i <= $count; $i++ ) {
		$code = $prefix . '-' . $i;
		if ( wsscd_bulk_coupon_code_exists( $code ) ) {
			$errors[] = sprintf( __( 'Skipped (already exists): %s', 'smart-cycle-discounts' ), $code );
			continue;
		}
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_usage_limit( $usage );
		if ( $expiry > 0 ) {
			$coupon->set_date_expires( strtotime( '+' . $expiry . ' days' ) );
		}
		try {
			$coupon->save();
			$created++;
		} catch ( Exception $e ) {
			$errors[] = $code . ': ' . $e->getMessage();
		}
	}

	$redirect = add_query_arg(
		array(
			'page'    => 'wsscd-bulk-coupons',
			'created' => $created,
			'errors'  => count( $errors ),
		),
		admin_url( 'tools.php' )
	);
	if ( ! empty( $errors ) ) {
		set_transient( 'wsscd_bulk_coupon_errors', array_slice( $errors, 0, 20 ), 60 );
	}
	wp_safe_redirect( $redirect );
	exit;
}

function wsscd_bulk_coupon_code_exists( $code ) {
	$id = wc_get_coupon_id_by_code( $code );
	return (bool) $id;
}

function wsscd_bulk_coupon_render_page() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'WooCommerce is required.', 'smart-cycle-discounts' ) . '</p></div>';
		return;
	}

	$created = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0;
	$errors  = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;
	$messages = get_transient( 'wsscd_bulk_coupon_errors' );
	if ( is_array( $messages ) ) {
		delete_transient( 'wsscd_bulk_coupon_errors' );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bulk Coupon Generator', 'smart-cycle-discounts' ); ?></h1>
		<?php if ( $created > 0 ) : ?>
			<div class="notice notice-success"><p>
				<?php echo esc_html( sprintf( __( '%d coupon(s) created.', 'smart-cycle-discounts' ), $created ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>"><?php esc_html_e( 'View coupons', 'smart-cycle-discounts' ); ?></a>
			</p></div>
		<?php endif; ?>
		<?php if ( $errors > 0 && is_array( $messages ) && ! empty( $messages ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Some codes were skipped or failed:', 'smart-cycle-discounts' ); ?></p><ul>
				<?php foreach ( $messages as $msg ) : ?>
					<li><?php echo esc_html( $msg ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>

		<form method="post" action="" style="max-width: 400px; margin-top: 20px;">
			<?php wp_nonce_field( 'wsscd_bulk_coupon', 'wsscd_bulk_coupon_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="prefix"><?php esc_html_e( 'Code prefix', 'smart-cycle-discounts' ); ?></label></th>
					<td><input type="text" id="prefix" name="prefix" value="SALE" class="regular-text" /> <br><span class="description"><?php esc_html_e( 'e.g. SALE → SALE-1, SALE-2, …', 'smart-cycle-discounts' ); ?></span></td>
				</tr>
				<tr>
					<th><label for="count"><?php esc_html_e( 'Number of coupons', 'smart-cycle-discounts' ); ?></label></th>
					<td><input type="number" id="count" name="count" value="10" min="1" max="500" class="small-text" /></td>
				</tr>
				<tr>
					<th><label for="discount_type"><?php esc_html_e( 'Discount type', 'smart-cycle-discounts' ); ?></label></th>
					<td>
						<select id="discount_type" name="discount_type">
							<option value="percent"><?php esc_html_e( 'Percentage', 'smart-cycle-discounts' ); ?></option>
							<option value="fixed_cart"><?php esc_html_e( 'Fixed cart', 'smart-cycle-discounts' ); ?></option>
							<option value="fixed_product"><?php esc_html_e( 'Fixed product', 'smart-cycle-discounts' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="amount"><?php esc_html_e( 'Amount', 'smart-cycle-discounts' ); ?></label></th>
					<td><input type="number" id="amount" name="amount" value="10" min="0" step="0.01" class="small-text" /> <span class="description"><?php esc_html_e( '% or fixed value', 'smart-cycle-discounts' ); ?></span></td>
				</tr>
				<tr>
					<th><label for="usage_limit"><?php esc_html_e( 'Usage limit per coupon', 'smart-cycle-discounts' ); ?></label></th>
					<td><input type="number" id="usage_limit" name="usage_limit" value="1" min="0" class="small-text" /> <span class="description"><?php esc_html_e( '0 = unlimited', 'smart-cycle-discounts' ); ?></span></td>
				</tr>
				<tr>
					<th><label for="expiry_days"><?php esc_html_e( 'Expires in (days)', 'smart-cycle-discounts' ); ?></label></th>
					<td><input type="number" id="expiry_days" name="expiry_days" value="30" min="0" class="small-text" /> <span class="description"><?php esc_html_e( '0 = no expiry', 'smart-cycle-discounts' ); ?></span></td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate coupons', 'smart-cycle-discounts' ); ?></button>
			</p>
		</form>
		<p class="description"><?php esc_html_e( 'After use, you can remove this tool. It is for one-off bulk creation only.', 'smart-cycle-discounts' ); ?></p>
	</div>
	<?php
}
