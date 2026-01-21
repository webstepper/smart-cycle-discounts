<?php
/**
 * Campaign Overview Panel - Products Section
 *
 * Enhanced structure with logical flow and better visual hierarchy.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components/partials
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Extract data
$selection_type          = isset( $data['selection_type'] ) ? $data['selection_type'] : '';
$categories              = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$all_categories_selected = isset( $data['all_categories_selected'] ) ? $data['all_categories_selected'] : false;
$products                = isset( $data['products'] ) && is_array( $data['products'] ) ? $data['products'] : array();
$total_products          = isset( $data['total_products'] ) ? absint( $data['total_products'] ) : 0;
$conditions              = isset( $data['conditions'] ) && is_array( $data['conditions'] ) ? $data['conditions'] : array();
$has_conditions          = isset( $data['has_conditions'] ) ? $data['has_conditions'] : false;
$conditions_logic        = isset( $data['conditions_logic'] ) ? $data['conditions_logic'] : 'all';
$category_count          = isset( $data['category_count'] ) ? absint( $data['category_count'] ) : count( $categories );
$tag_count               = isset( $data['tag_count'] ) ? absint( $data['tag_count'] ) : 0;

// Calculate enhanced product stats from products array
$products_on_sale    = 0;
$products_in_stock   = 0;
$products_out_stock  = 0;
$products_backorder  = 0;
$variable_products   = 0;
$total_variations    = 0;

foreach ( $products as $product ) {
	if ( isset( $product['on_sale'] ) && $product['on_sale'] ) {
		++$products_on_sale;
	}
	if ( isset( $product['stock_status'] ) ) {
		switch ( $product['stock_status'] ) {
			case 'instock':
				++$products_in_stock;
				break;
			case 'outofstock':
				++$products_out_stock;
				break;
			case 'onbackorder':
				++$products_backorder;
				break;
		}
	}
	if ( isset( $product['type'] ) && 'variable' === $product['type'] ) {
		++$variable_products;
		if ( isset( $product['variation_count'] ) ) {
			$total_variations += absint( $product['variation_count'] );
		}
	}
}

// Selection type configuration
$type_config = array(
	'all_products'      => array(
		'label' => __( 'All Products', 'smart-cycle-discounts' ),
		'icon'  => 'products',
		'desc'  => __( 'All products in selected categories', 'smart-cycle-discounts' ),
	),
	'specific_products' => array(
		'label' => __( 'Specific Products', 'smart-cycle-discounts' ),
		'icon'  => 'admin-post',
		'desc'  => __( 'Hand-picked individual products', 'smart-cycle-discounts' ),
	),
	'random_products'   => array(
		'label' => __( 'Random Selection', 'smart-cycle-discounts' ),
		'icon'  => 'randomize',
		'desc'  => __( 'Random products from pool', 'smart-cycle-discounts' ),
	),
	'smart_selection'   => array(
		'label' => __( 'Smart Selection', 'smart-cycle-discounts' ),
		'icon'  => 'chart-line',
		'desc'  => __( 'Auto-selected by criteria', 'smart-cycle-discounts' ),
	),
);

$type_info = isset( $type_config[ $selection_type ] ) ? $type_config[ $selection_type ] : array(
	'label' => $selection_type,
	'icon'  => 'products',
	'desc'  => '',
);

// Group conditions by mode for better display
$include_conditions = array();
$exclude_conditions = array();
foreach ( $conditions as $condition ) {
	$mode = isset( $condition['mode'] ) ? $condition['mode'] : 'include';
	if ( 'exclude' === $mode ) {
		$exclude_conditions[] = $condition;
	} else {
		$include_conditions[] = $condition;
	}
}
?>

<!-- Products Stats Bar -->
<div class="wsscd-products-stats-bar">
	<div class="wsscd-stat-item">
		<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 16 ) ); ?>
		<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $total_products ) ); ?></span>
		<span class="wsscd-stat-label"><?php echo esc_html( _n( 'Product', 'Products', $total_products, 'smart-cycle-discounts' ) ); ?></span>
	</div>
	<?php if ( ! $all_categories_selected && $category_count > 0 ) : ?>
		<div class="wsscd-stat-item">
			<?php WSSCD_Icon_Helper::render( 'category', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $category_count ) ); ?></span>
			<span class="wsscd-stat-label"><?php echo esc_html( _n( 'Category', 'Categories', $category_count, 'smart-cycle-discounts' ) ); ?></span>
		</div>
	<?php endif; ?>
	<?php if ( $has_conditions && count( $conditions ) > 0 ) : ?>
		<div class="wsscd-stat-item">
			<?php WSSCD_Icon_Helper::render( 'filter', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( count( $conditions ) ) ); ?></span>
			<span class="wsscd-stat-label"><?php echo esc_html( _n( 'Filter', 'Filters', count( $conditions ), 'smart-cycle-discounts' ) ); ?></span>
		</div>
	<?php endif; ?>
	<?php if ( $products_on_sale > 0 ) : ?>
		<div class="wsscd-stat-item wsscd-stat-sale">
			<?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $products_on_sale ) ); ?></span>
			<span class="wsscd-stat-label"><?php echo esc_html( _n( 'On Sale', 'On Sale', $products_on_sale, 'smart-cycle-discounts' ) ); ?></span>
		</div>
	<?php endif; ?>
	<?php if ( $variable_products > 0 && $total_variations > 0 ) : ?>
		<div class="wsscd-stat-item wsscd-stat-variations">
			<?php WSSCD_Icon_Helper::render( 'admin-settings', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $total_variations ) ); ?></span>
			<span class="wsscd-stat-label"><?php echo esc_html( _n( 'Variation', 'Variations', $total_variations, 'smart-cycle-discounts' ) ); ?></span>
		</div>
	<?php endif; ?>
	<?php if ( $products_in_stock > 0 || $products_out_stock > 0 || $products_backorder > 0 ) : ?>
		<div class="wsscd-stat-item wsscd-stat-stock-in">
			<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $products_in_stock ) ); ?></span>
			<span class="wsscd-stat-label"><?php esc_html_e( 'In Stock', 'smart-cycle-discounts' ); ?></span>
		</div>
	<?php endif; ?>
	<?php if ( $products_out_stock > 0 ) : ?>
		<div class="wsscd-stat-item wsscd-stat-stock-out">
			<?php WSSCD_Icon_Helper::render( 'no-alt', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $products_out_stock ) ); ?></span>
			<span class="wsscd-stat-label"><?php esc_html_e( 'Out of Stock', 'smart-cycle-discounts' ); ?></span>
		</div>
	<?php endif; ?>
	<?php if ( $products_backorder > 0 ) : ?>
		<div class="wsscd-stat-item wsscd-stat-stock-backorder">
			<?php WSSCD_Icon_Helper::render( 'backup', array( 'size' => 16 ) ); ?>
			<span class="wsscd-stat-value"><?php echo esc_html( number_format_i18n( $products_backorder ) ); ?></span>
			<span class="wsscd-stat-label"><?php esc_html_e( 'Backorder', 'smart-cycle-discounts' ); ?></span>
		</div>
	<?php endif; ?>
</div>

<!-- Selection Method & Scope (Combined) -->
<div class="wsscd-overview-subsection">
	<div class="wsscd-subsection-header">
		<?php WSSCD_Icon_Helper::render( 'admin-settings', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Selection Method', 'smart-cycle-discounts' ); ?></h5>
	</div>

	<div class="wsscd-selection-method-card">
		<!-- Selection Type -->
		<div class="wsscd-selection-method-header">
			<?php WSSCD_Icon_Helper::render( $type_info['icon'], array( 'size' => 20 ) ); ?>
			<div class="wsscd-selection-method-info">
				<div class="wsscd-selection-method-title"><?php echo esc_html( $type_info['label'] ); ?></div>
			</div>
		</div>

		<!-- Category Scope Integrated -->
		<?php if ( $all_categories_selected || ! empty( $categories ) ) : ?>
		<div class="wsscd-selection-scope">
			<?php if ( $all_categories_selected ) : ?>
				<div class="wsscd-scope-item">
					<?php WSSCD_Icon_Helper::render( 'category', array( 'size' => 14 ) ); ?>
					<span class="wsscd-scope-label"><?php esc_html_e( 'Scope:', 'smart-cycle-discounts' ); ?></span>
					<span class="wsscd-scope-value"><?php esc_html_e( 'All product categories', 'smart-cycle-discounts' ); ?></span>
				</div>
			<?php elseif ( ! empty( $categories ) ) : ?>
				<div class="wsscd-scope-item">
					<?php WSSCD_Icon_Helper::render( 'category', array( 'size' => 14 ) ); ?>
					<span class="wsscd-scope-label"><?php esc_html_e( 'Categories:', 'smart-cycle-discounts' ); ?></span>
					<span class="wsscd-scope-value">
						<?php
						$category_names = array_column( $categories, 'name' );
						if ( count( $category_names ) <= 3 ) {
							echo esc_html( implode( ', ', $category_names ) );
						} else {
							echo esc_html( implode( ', ', array_slice( $category_names, 0, 3 ) ) );
							printf(
								/* translators: %d: number of additional categories */
								esc_html__( ' +%d more', 'smart-cycle-discounts' ),
								count( $category_names ) - 3
							);
						}
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- Additional Selection Details -->
		<?php
		// Random products additional info
		if ( 'random_products' === $selection_type && isset( $data['random_count'] ) ) :
			$random_count   = absint( $data['random_count'] );
			$actual_count   = $total_products;
			$showing_actual = ( $actual_count === $random_count || $actual_count < $random_count );
			?>
			<div class="wsscd-selection-method-detail">
				<?php WSSCD_Icon_Helper::render( 'randomize', array( 'size' => 14 ) ); ?>
				<?php
				if ( $showing_actual ) {
					printf(
						/* translators: %d: number of randomly selected products */
						esc_html__( 'Showing %d randomly selected products', 'smart-cycle-discounts' ),
						intval( $actual_count )
					);
				} else {
					printf(
						/* translators: 1: number of products shown, 2: requested random count */
						esc_html__( 'Showing %1$d of %2$d requested random products (filtered by conditions)', 'smart-cycle-discounts' ),
						intval( $actual_count ),
						intval( $random_count )
					);
				}
				?>
			</div>
			<?php
		// Smart selection additional info
		elseif ( 'smart_selection' === $selection_type && ! empty( $data['smart_criteria'] ) ) :
			$criteria_labels = array(
				'best_sellers' => __( 'Best Sellers - Top performing products by sales', 'smart-cycle-discounts' ),
				'featured'     => __( 'Featured Products - Hand-picked showcase products', 'smart-cycle-discounts' ),
				'low_stock'    => __( 'Low Stock - Products with 10 or fewer items in stock', 'smart-cycle-discounts' ),
				'new_arrivals' => __( 'New Arrivals - Recently added products (last 30 days)', 'smart-cycle-discounts' ),
			);
			$criteria_label = isset( $criteria_labels[ $data['smart_criteria'] ] ) ? $criteria_labels[ $data['smart_criteria'] ] : $data['smart_criteria'];
			?>
			<div class="wsscd-selection-method-detail">
				<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 14 ) ); ?>
				<?php echo esc_html( $criteria_label ); ?>
			</div>
			<?php
		endif;
		?>
	</div>
</div>

<!-- Category Details (Only if specific categories selected and worth showing) -->
<?php if ( ! $all_categories_selected && ! empty( $categories ) && count( $categories ) > 3 ) : ?>
	<div class="wsscd-overview-subsection">
		<div class="wsscd-subsection-header">
			<?php WSSCD_Icon_Helper::render( 'category', array( 'size' => 16 ) ); ?>
			<h5><?php esc_html_e( 'Category Details', 'smart-cycle-discounts' ); ?></h5>
			<span class="wsscd-count-badge"><?php echo absint( count( $categories ) ); ?></span>
		</div>

		<div class="wsscd-category-grid">
			<?php foreach ( $categories as $category ) : ?>
				<div class="wsscd-category-card">
					<?php WSSCD_Icon_Helper::render( 'category', array( 'size' => 16 ) ); ?>
					<div class="wsscd-category-card-content">
						<div class="wsscd-category-card-name"><?php echo esc_html( $category['name'] ); ?></div>
						<?php if ( isset( $category['count'] ) && $category['count'] > 0 ) : ?>
							<div class="wsscd-category-card-count">
								<?php
								printf(
									/* translators: %d: number of products */
									esc_html( _n( '%d product', '%d products', $category['count'], 'smart-cycle-discounts' ) ),
									absint( $category['count'] )
								);
								?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>

<!-- Advanced Filters (Beautiful New Design) -->
<?php if ( $has_conditions && ! empty( $conditions ) ) : ?>
	<div class="wsscd-overview-subsection">
		<div class="wsscd-subsection-header">
			<?php WSSCD_Icon_Helper::render( 'filter', array( 'size' => 16 ) ); ?>
			<h5><?php esc_html_e( 'Advanced Filters', 'smart-cycle-discounts' ); ?></h5>
		</div>

		<div class="wsscd-filter-logic-indicator wsscd-filter-logic-indicator--<?php echo esc_attr( 'all' === $conditions_logic ? 'and' : 'or' ); ?>">
			<div class="wsscd-filter-logic-header">
				<div class="wsscd-filter-logic-icon">
					<?php
					if ( 'all' === $conditions_logic ) {
						WSSCD_Icon_Helper::render( 'yes', array( 'size' => 18 ) );
					} else {
						WSSCD_Icon_Helper::render( 'menu', array( 'size' => 18 ) );
					}
					?>
				</div>
				<div class="wsscd-filter-logic-content">
					<div class="wsscd-filter-logic-label">
						<?php
						if ( 'all' === $conditions_logic ) {
							esc_html_e( 'Match ALL', 'smart-cycle-discounts' );
						} else {
							esc_html_e( 'Match ANY', 'smart-cycle-discounts' );
						}
						?>
					</div>
					<div class="wsscd-filter-logic-desc">
						<?php
						if ( 'all' === $conditions_logic ) {
							esc_html_e( 'Products must satisfy all rules below', 'smart-cycle-discounts' );
						} else {
							esc_html_e( 'Products satisfy at least one rule', 'smart-cycle-discounts' );
						}
						?>
					</div>
				</div>
			</div>

			<!-- Conditions List -->
			<div class="wsscd-filter-rules">
				<?php foreach ( $conditions as $index => $condition ) : ?>
					<?php
					$mode = isset( $condition['mode'] ) ? $condition['mode'] : 'include';
					$is_include = 'include' === $mode;
					?>
					<div class="wsscd-filter-rule wsscd-filter-rule--<?php echo esc_attr( $mode ); ?>">
						<div class="wsscd-filter-rule-indicator">
							<?php
							if ( $is_include ) {
								WSSCD_Icon_Helper::render( 'saved', array( 'size' => 16 ) );
							} else {
								WSSCD_Icon_Helper::render( 'dismiss', array( 'size' => 16 ) );
							}
							?>
						</div>
						<div class="wsscd-filter-rule-content">
							<div class="wsscd-filter-rule-label">
								<?php
								if ( $is_include ) {
									esc_html_e( 'Include', 'smart-cycle-discounts' );
								} else {
									esc_html_e( 'Exclude', 'smart-cycle-discounts' );
								}
								?>
							</div>
							<div class="wsscd-filter-rule-desc">
								<?php
								if ( 'price' === $condition['type'] ) {
									echo wp_kses_post( $condition['description'] );
								} else {
									echo esc_html( $condition['description'] );
								}
								?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php endif; ?>

<!-- Products Grid/List -->
<?php if ( ! empty( $products ) ) : ?>
	<?php
	$product_count = count( $products );

	// Determine section title based on selection type
	if ( 'specific_products' === $selection_type ) {
		$section_title = __( 'Selected Products', 'smart-cycle-discounts' );
	} else {
		$section_title = __( 'Discounted Products', 'smart-cycle-discounts' );
	}
	?>
	<div class="wsscd-overview-subsection">
		<div class="wsscd-subsection-header">
			<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 16 ) ); ?>
			<h5><?php echo esc_html( $section_title ); ?></h5>
			<span class="wsscd-count-badge"><?php echo absint( $product_count ); ?></span>
		</div>

		<div class="wsscd-product-grid-container">
			<div class="wsscd-product-grid">
				<?php foreach ( $products as $product ) : ?>
					<?php
					$product_url = isset( $product['url'] ) ? esc_url( $product['url'] ) : '';
					$has_link    = ! empty( $product_url );
					$tag         = $has_link ? 'a' : 'div';
					$link_attrs  = $has_link ? ' href="' . $product_url . '" target="_blank" rel="noopener noreferrer"' : '';
					$has_image   = ! empty( $product['has_image'] );
					?>
					<<?php echo esc_html( $tag ); ?> class="wsscd-product-card"<?php echo wp_kses_post( $link_attrs ); ?>>
						<?php if ( $has_image ) : ?>
							<div class="wsscd-product-card-image">
								<img src="<?php echo esc_url( $product['image_url'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" loading="lazy">
							</div>
						<?php else : ?>
							<div class="wsscd-product-card-image no-image">
								<span class="wsscd-product-placeholder">
									<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 20 ) ); ?>
								</span>
							</div>
						<?php endif; ?>
						<div class="wsscd-product-card-content">
							<div class="wsscd-product-card-name"><?php echo esc_html( $product['name'] ); ?></div>
							<?php if ( isset( $product['price'] ) && '' !== $product['price'] ) : ?>
								<div class="wsscd-product-card-price"><?php echo wp_kses_post( wc_price( $product['price'] ) ); ?></div>
							<?php endif; ?>
						</div>
					</<?php echo esc_html( $tag ); ?>>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php elseif ( $total_products > 0 ) : ?>
	<div class="wsscd-overview-subsection">
		<div class="wsscd-subsection-header">
			<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 16 ) ); ?>
			<h5><?php esc_html_e( 'Products Included', 'smart-cycle-discounts' ); ?></h5>
			<span class="wsscd-count-badge"><?php echo absint( $total_products ); ?></span>
		</div>

		<div class="wsscd-product-count-card">
			<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 20 ) ); ?>
			<div class="wsscd-product-count-content">
				<span class="wsscd-product-count-value"><?php echo absint( $total_products ); ?></span>
				<?php
				echo ' ' . esc_html( _n( 'product will receive this discount', 'products will receive this discount', $total_products, 'smart-cycle-discounts' ) );
				?>
			</div>
			<button type="button" class="wsscd-view-all-products-btn" data-campaign-id="<?php echo esc_attr( isset( $data['campaign_id'] ) ? $data['campaign_id'] : '' ); ?>">
				<?php WSSCD_Icon_Helper::render( 'visibility', array( 'size' => 16 ) ); ?>
				<?php esc_html_e( 'View All Products', 'smart-cycle-discounts' ); ?>
			</button>
		</div>
	</div>
<?php endif; ?>
