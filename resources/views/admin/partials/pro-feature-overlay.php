<?php
/**
 * PRO Feature Unavailable Overlay Template
 *
 * Displays a glassmorphism overlay for locked PRO features.
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 *
 * @var string $description Short description of the feature
 * @var array  $features    Array of feature benefits (strings)
 * @var string $upgrade_url URL to upgrade page
 * @var array  $button_args Optional button arguments (text, size, icon)
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Default values
$description = isset( $description ) ? $description : __( 'Advanced PRO features', 'smart-cycle-discounts' );
$features = isset( $features ) ? $features : array();
$upgrade_url = isset( $upgrade_url ) ? $upgrade_url : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
$button_args = isset( $button_args ) ? $button_args : array();

// Button defaults
$button_text = isset( $button_args['text'] ) ? $button_args['text'] : __( 'Upgrade to Pro', 'smart-cycle-discounts' );
$button_size = isset( $button_args['size'] ) ? $button_args['size'] : 'medium';
$button_icon = isset( $button_args['icon'] ) ? $button_args['icon'] : 'star-filled';
?>

<div class="wsscd-pro-feature-unavailable">
	<?php if ( ! empty( $description ) ) : ?>
		<p class="wsscd-pro-feature-unavailable__description">
			<?php echo esc_html( $description ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $features ) ) : ?>
		<ul class="wsscd-pro-feature-unavailable__features">
			<?php foreach ( $features as $feature ) : ?>
				<li>
					<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
					<?php echo esc_html( $feature ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-<?php echo esc_attr( $button_size ); ?>">
		<?php if ( ! empty( $button_icon ) ) : ?>
			<?php WSSCD_Icon_Helper::render( $button_icon, array( 'size' => 16 ) ); ?>
		<?php endif; ?>
		<?php echo esc_html( $button_text ); ?>
	</a>
</div>
