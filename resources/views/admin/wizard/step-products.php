<?php
/**
 * Campaign wizard - Product Selection step
 *
 * Comprehensive product selection with categories, smart filters,
 * and advanced conditions for targeted discount campaigns
 *
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/views/campaigns/wizard
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security: Verify user capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-cycle-discounts' ) );
}

// Initialize variables using shared function
wsscd_wizard_init_step_vars( $step_data, $validation_errors );

// Extract values with defaults handled by field schema
// Product Selection Model:
// 1. Category filter (first field) - Creates the product pool. Empty = all categories
// 2. Selection type - Determines HOW to select from the category pool
// 3. Advanced filters - Further refines the selection
$product_selection_type = $step_data['product_selection_type'] ?? 'all_products';
$selected_categories = $step_data['category_ids'] ?? array();
$product_ids = $step_data['product_ids'] ?? array();
$random_count = $step_data['random_count'] ?? 10;
$conditions = $step_data['conditions'] ?? array();
$conditions_logic = $step_data['conditions_logic'] ?? 'all';
$smart_criteria = $step_data['smart_criteria'] ?? '';

// Ensure arrays are proper type for template usage
// Empty array = all categories (no filter applied)
$selected_categories = is_array( $selected_categories ) ? $selected_categories : array();
$product_ids = is_array( $product_ids ) ? $product_ids : array();
$conditions = is_array( $conditions ) ? $conditions : array();

// Normalize conditions to ensure all required fields are present with defaults
// Database format: {condition_type, operator, value, value2, mode}
$conditions = array_map( function( $condition ) {
	if ( ! is_array( $condition ) ) {
		return $condition;
	}

	// Ensure all required fields are present with defaults
	return array(
		'condition_type' => isset( $condition['condition_type'] ) ? $condition['condition_type'] : '',
		'operator'       => isset( $condition['operator'] ) ? $condition['operator'] : '',
		'mode'           => isset( $condition['mode'] ) ? $condition['mode'] : 'include',
		'value'          => isset( $condition['value'] ) ? $condition['value'] : '',
		'value2'         => isset( $condition['value2'] ) ? $condition['value2'] : '',
	);
}, $conditions );

// Get selected products for display with error handling
$selected_product_objects = array();
if ( ! empty( $product_ids ) && function_exists( 'wc_get_products' ) ) {
	$selected_product_objects = wc_get_products( array(
		'include' => array_map( 'absint', $product_ids ),
		'limit' => -1,
		'return' => 'objects'
	) );
}

// Get category data for saved categories (Tom Select options format)
// Empty array = all categories (no filter), only load options for specific category IDs
$category_options = array();
if ( ! empty( $selected_categories ) ) {
	foreach ( $selected_categories as $cat_id ) {
		// Only process valid numeric category IDs
		if ( ! is_numeric( $cat_id ) || intval( $cat_id ) <= 0 ) {
			continue;
		}

		$category = get_term( absint( $cat_id ), 'product_cat' );
		if ( $category && ! is_wp_error( $category ) ) {
			$category_options[] = array(
				'value' => (string) $category->term_id,
				'text'  => $category->name,
				'count' => (int) $category->count,
				'level' => 0,
				'$order' => 1
			);
		}
	}
}

// Condition types are now defined in WSSCD_Field_Definitions::get_condition_types()
$condition_types = class_exists( 'WSSCD_Field_Definitions' ) ? WSSCD_Field_Definitions::get_condition_types() : array();

// Operator mappings are now defined in WSSCD_Field_Definitions::get_operator_mappings()
$operator_mappings = class_exists( 'WSSCD_Field_Definitions' ) ? WSSCD_Field_Definitions::get_operator_mappings() : array();

// Function to get operators for a condition type
$get_operators_for_type = function( $type ) use ( $operator_mappings ) {
	foreach ( $operator_mappings as $mapping ) {
		if ( in_array( $type, $mapping['types'], true ) ) {
			return $mapping['operators'];
		}
	}
	// Return numeric operators as default
	return $operator_mappings['numeric']['operators'];
};

// Prepare strings for JavaScript
$js_strings = array(
	'select_condition' => esc_html__( 'Select condition type', 'smart-cycle-discounts' ),
	'select_operator' => esc_html__( 'Select operator', 'smart-cycle-discounts' ),
	'enter_value' => esc_html__( 'Enter value', 'smart-cycle-discounts' ),
	'remove_condition' => esc_html__( 'Remove this condition', 'smart-cycle-discounts' ),
	'search_placeholder' => esc_html__( 'Search for products...', 'smart-cycle-discounts' ),
	'all_categories' => esc_html__( 'All', 'smart-cycle-discounts' ),
	'all_products' => esc_html__( 'All Products', 'smart-cycle-discounts' ),
	'random_selection' => esc_html__( 'Random Selection', 'smart-cycle-discounts' ),
	'specific_products' => esc_html__( 'Specific Products', 'smart-cycle-discounts' )
);

// PRO feature access for dual-block pattern (WordPress.org compliance)
$has_pro_access = wsscd_fs()->can_use_premium_code();
$upgrade_url = $feature_gate ? $feature_gate->get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

// Prepare content for template wrapper
ob_start();
?>
	<?php wsscd_wizard_validation_notice( $validation_errors ); ?>
	
	<?php wp_nonce_field( 'wsscd_wizard_products_step', 'wsscd_products_nonce' ); ?>
	
	<!-- Category Selection -->
	<?php
	ob_start();
	?>
	<div class="wsscd-form-field wsscd-form-field--full">
		<label for="wsscd-campaign-categories">
			<?php esc_html_e( 'Categories', 'smart-cycle-discounts' ); ?>
			<?php WSSCD_Tooltip_Helper::render( __( 'Filter products by category. Select multiple categories to include products from any of them.', 'smart-cycle-discounts' ) ); ?>
		</label>
		<select id="wsscd-campaign-categories"
				name="category_ids[]"
				multiple="multiple"
				class="wsscd-category-select"
				data-help-topic="category-ids">
			<!-- Preload selected categories for form submission -->
			<!-- Empty selection = all categories (no filter applied to the product pool) -->
			<?php if ( ! empty( $selected_categories ) ) : ?>
				<?php foreach ( $selected_categories as $cat_id ) : ?>
					<?php
					// Only render valid numeric category IDs
					if ( ! is_numeric( $cat_id ) || intval( $cat_id ) <= 0 ) {
						continue;
					}
					$category = get_term( absint( $cat_id ), 'product_cat' );
					if ( $category && ! is_wp_error( $category ) ) :
					?>
						<option value="<?php echo esc_attr( $cat_id ); ?>" selected>
							<?php echo esc_html( $category->name ); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</select>
		<!-- FOUC Prevention: Skeleton loader shown while Tom Select initializes (~100-200ms) -->
		<div class="wsscd-loading-skeleton" aria-hidden="true"></div>
	</div>
	<?php
	$category_content = ob_get_clean();
	
	wsscd_wizard_card( array(
		'title' => esc_html__( 'Product Categories', 'smart-cycle-discounts' ),
		'subtitle' => esc_html__( 'Select categories to include in this campaign', 'smart-cycle-discounts' ),
		'icon' => 'category',
		'content' => $category_content,
		'class' => 'wsscd-category-selection',
		'help_topic' => 'card-category-selection'
	) );
	?>
	
	<!-- Product Selection Method -->
	<?php
	ob_start();
	?>
	<div class="wsscd-product-selection-cards">
		<!-- All Products Card -->
		<div class="wsscd-card wsscd-card--interactive wsscd-card-option <?php echo esc_attr( 'all_products' === $product_selection_type ? 'wsscd-card-option--selected' : '' ); ?>"
			 data-help-topic="option-product-all">
			<input type="radio"
				   name="product_selection_type"
				   value="all_products"
				   id="product_selection_all"
				   data-help-topic="product-selection-type"
				   <?php checked( $product_selection_type, 'all_products' ); ?>>
			<label for="product_selection_all" class="wsscd-card__content">
				<h4 class="wsscd-card__title"><?php esc_html_e( 'All Products', 'smart-cycle-discounts' ); ?></h4>
				<p class="wsscd-card__subtitle"><?php esc_html_e( 'Apply discount to all products in selected categories', 'smart-cycle-discounts' ); ?></p>
			</label>
		</div>

		<!-- Random Products Card -->
		<div class="wsscd-card wsscd-card--interactive wsscd-card-option <?php echo esc_attr( 'random_products' === $product_selection_type ? 'wsscd-card-option--selected' : '' ); ?>"
			 data-help-topic="option-product-random">
			<input type="radio"
				   name="product_selection_type"
				   value="random_products"
				   id="product_selection_random"
				   data-help-topic="product-selection-type"
				   <?php checked( $product_selection_type, 'random_products' ); ?>>
			<label for="product_selection_random" class="wsscd-card__content">
				<h4 class="wsscd-card__title"><?php esc_html_e( 'Random Products', 'smart-cycle-discounts' ); ?></h4>
				<p class="wsscd-card__subtitle"><?php esc_html_e( 'Randomly select a specific number of products', 'smart-cycle-discounts' ); ?></p>
			</label>
			
			<div class="wsscd-random-count">
				<label for="wsscd-random-count">
					<?php esc_html_e( 'Number of products:', 'smart-cycle-discounts' ); ?>
					<?php WSSCD_Tooltip_Helper::render( __('Specify how many random products to select from the chosen categories.', 'smart-cycle-discounts') ); ?>
				</label>
				<input type="number"
					   id="wsscd-random-count"
					   name="random_count"
					   value="<?php echo esc_attr( $random_count ); ?>"
					   min="1"
					   max="100"
					   step="1"
					   inputmode="numeric"
					   data-label="Random Product Count"
					   data-input-type="integer"
					   data-help-topic="random-count"
					   placeholder="1-100"
					   class="wsscd-enhanced-input wsscd-input-small">
			</div>
		</div>
		
		<!-- Specific Products Card -->
		<div class="wsscd-card wsscd-card--interactive wsscd-card-option <?php echo esc_attr( 'specific_products' === $product_selection_type ? 'wsscd-card-option--selected' : '' ); ?>"
			 data-help-topic="option-product-specific">
			<input type="radio"
				   name="product_selection_type"
				   value="specific_products"
				   id="product_selection_specific"
				   data-help-topic="product-selection-type"
				   <?php checked( $product_selection_type, 'specific_products' ); ?>>
			<label for="product_selection_specific" class="wsscd-card__content">
				<h4 class="wsscd-card__title"><?php esc_html_e( 'Specific Products', 'smart-cycle-discounts' ); ?></h4>
				<p class="wsscd-card__subtitle"><?php esc_html_e( 'Hand-pick individual products to discount', 'smart-cycle-discounts' ); ?></p>
			</label>
			
			<div class="wsscd-specific-products">
				<div class="wsscd-product-search-container">
					<label for="wsscd-product-search">
						<?php esc_html_e( 'Search and select products:', 'smart-cycle-discounts' ); ?>
						<?php WSSCD_Tooltip_Helper::render( __('Search for specific products by name, SKU, or ID. You can select multiple products.', 'smart-cycle-discounts') ); ?>
					</label>
					<!-- Hidden field stores actual product IDs (single source of truth) -->
					<input type="hidden"
						   id="wsscd-product-ids-hidden"
						   name="product_ids"
						   value="<?php echo esc_attr( ! empty( $product_ids ) ? implode( ',', array_map( 'absint', $product_ids ) ) : '' ); ?>" />
					<!-- TomSelect UI (syncs to hidden field) -->
					<select id="wsscd-product-search"
							multiple="multiple"
							class="wsscd-product-search-select"
							data-help-topic="product-ids"
							placeholder="<?php esc_attr_e( 'Type to search products by name or SKU...', 'smart-cycle-discounts' ); ?>">
						<?php foreach ( $selected_product_objects as $product ) : ?>
							<?php
							// Additional security: Validate product object
							if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $product->get_id() ); ?>" selected>
								<?php echo esc_html( wp_strip_all_tags( $product->get_name() ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Start typing to search for products by name or SKU.', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
		</div>
		
		<!-- Smart Selection Card -->
		<div class="wsscd-card wsscd-card--interactive wsscd-card-option <?php echo esc_attr( 'smart_selection' === $product_selection_type ? 'wsscd-card-option--selected' : '' ); ?>">
			<input type="radio"
				   name="product_selection_type"
				   value="smart_selection"
				   id="product_selection_smart"
				   <?php checked( $product_selection_type, 'smart_selection' ); ?>>
			<label for="product_selection_smart" class="wsscd-card__content">
				<h4 class="wsscd-card__title"><?php esc_html_e( 'Smart Selection', 'smart-cycle-discounts' ); ?></h4>
				<p class="wsscd-card__subtitle"><?php esc_html_e( 'Auto-select products based on business criteria', 'smart-cycle-discounts' ); ?></p>
			</label>
			
			<div class="wsscd-smart-criteria">
				<div class="wsscd-smart-label">
					<?php esc_html_e( 'Select products based on:', 'smart-cycle-discounts' ); ?>
					<?php WSSCD_Tooltip_Helper::render( __('Automatically select products based on predefined criteria like best sellers, featured products, or inventory levels.', 'smart-cycle-discounts') ); ?>
				</div>
				<div class="wsscd-smart-options">
					<label class="wsscd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="best_sellers" 
							   <?php checked( $smart_criteria, 'best_sellers' ); ?>>
						<div class="wsscd-smart-option-content">
							<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 20 ) ); ?>
							<div class="wsscd-smart-option-text">
								<strong><?php esc_html_e( 'Best Sellers', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Top performing products by sales', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
					<label class="wsscd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="featured"
							   <?php checked( $smart_criteria, 'featured' ); ?>>
						<div class="wsscd-smart-option-content">
							<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16 ) ); ?>
							<div class="wsscd-smart-option-text">
								<strong><?php esc_html_e( 'Featured Products', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Hand-picked showcase products', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
					<label class="wsscd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="low_stock"
							   <?php checked( $smart_criteria, 'low_stock' ); ?>>
						<div class="wsscd-smart-option-content">
							<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) ); ?>
							<div class="wsscd-smart-option-text">
								<strong><?php esc_html_e( 'Low Stock', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Products with 10 or fewer items in stock', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
					<label class="wsscd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="new_arrivals"
							   <?php checked( $smart_criteria, 'new_arrivals' ); ?>>
						<div class="wsscd-smart-option-content">
							<?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
							<div class="wsscd-smart-option-text">
								<strong><?php esc_html_e( 'New Arrivals', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Recently added products (last 30 days)', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
				</div>
			</div>
		</div>
	</div>
	<?php
	$selection_content = ob_get_clean();
	
	wsscd_wizard_card( array(
		'title' => esc_html__( 'Select Products', 'smart-cycle-discounts' ),
		'subtitle' => esc_html__( 'Choose which products will receive discounts in this campaign', 'smart-cycle-discounts' ),
		'icon' => 'products',
		'content' => $selection_content,
		'class' => 'wsscd-product-selection-method',
		'help_topic' => 'card-product-selection'
	) );
	?>
	
	<!-- Advanced Conditions (PRO) - Dual-block pattern for WordPress.org compliance -->
	<?php if ( ! $has_pro_access ) : ?>
	<?php
	// Block 1: Promotional locked card (always in both ZIPs) - shown to free users
	ob_start();
	?>
	<div class="wsscd-pro-feature-locked">
		<div class="wsscd-pro-feature-locked__content">
			<?php echo wp_kses_post( WSSCD_Badge_Helper::pro_badge( __( 'PRO Feature', 'smart-cycle-discounts' ) ) ); ?>
			<h4 class="wsscd-pro-feature-locked__title">
				<?php esc_html_e( 'Advanced Product Filters', 'smart-cycle-discounts' ); ?>
			</h4>
			<p class="wsscd-pro-feature-locked__description">
				<?php esc_html_e( 'Create precise product targeting with powerful filter conditions to maximize campaign effectiveness.', 'smart-cycle-discounts' ); ?>
			</p>
			<ul class="wsscd-pro-feature-locked__features">
				<li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Filter by price range, stock level, and attributes', 'smart-cycle-discounts' ); ?></li>
				<li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Include or exclude products based on multiple criteria', 'smart-cycle-discounts' ); ?></li>
				<li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Combine conditions with AND/OR logic', 'smart-cycle-discounts' ); ?></li>
				<li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Filter by product tags, attributes, and more', 'smart-cycle-discounts' ); ?></li>
			</ul>
			<?php
			WSSCD_Button_Helper::primary(
				__( 'Upgrade to Pro', 'smart-cycle-discounts' ),
				array(
					'size'    => 'medium',
					'href'    => esc_url( $upgrade_url ),
					'classes' => array( 'wsscd-pro-feature-locked__upgrade-btn' ),
				)
			);
			?>
		</div>
	</div>
	<?php
	$conditions_content = ob_get_clean();

	wsscd_wizard_card( array(
		'title'      => __( 'Advanced Filters', 'smart-cycle-discounts' ),
		'icon'       => 'filter',
		'badge'      => array( 'text' => __( 'PRO', 'smart-cycle-discounts' ), 'type' => 'pro' ),
		'subtitle'   => esc_html__( 'Add conditions to filter products based on specific criteria', 'smart-cycle-discounts' ),
		'content'    => $conditions_content,
		'class'      => 'wsscd-conditions-section wsscd-wizard-card--locked',
		'help_topic' => 'card-advanced-filters',
	) );
	?>
	<?php endif; ?>

	<?php if ( wsscd_fs()->is__premium_only() ) : ?>
	<?php if ( $has_pro_access ) : ?>
	<?php
	// Block 2: Functional card (only in PRO ZIP, shown when licensed)
	ob_start();
	?>

	<div id="wsscd-advanced-filters-container">
		<div>
			<fieldset class="wsscd-conditions-logic-fieldset">
			<legend class="screen-reader-text"><?php esc_html_e( 'Condition Logic', 'smart-cycle-discounts' ); ?></legend>
			<div class="wsscd-conditions-logic">
				<span class="wsscd-logic-label">
					<?php esc_html_e( 'Filter products that match', 'smart-cycle-discounts' ); ?>
					<?php WSSCD_Tooltip_Helper::render( __('Choose whether products must meet all criteria or just one to be included', 'smart-cycle-discounts') ); ?>
				</span>
				<div class="wsscd-logic-selector" role="radiogroup" aria-label="<?php esc_attr_e( 'Condition matching logic', 'smart-cycle-discounts' ); ?>">
					<label class="wsscd-logic-option">
						<input type="radio"
							   name="conditions_logic"
							   value="all"
							   <?php checked( $conditions_logic, 'all' ); ?>>
						<span class="wsscd-logic-text">
							<?php esc_html_e( 'All conditions', 'smart-cycle-discounts' ); ?>
							<span class="wsscd-logic-hint" aria-label="<?php esc_attr_e( 'AND logic', 'smart-cycle-discounts' ); ?>"><?php esc_html_e( '(AND)', 'smart-cycle-discounts' ); ?></span>
						</span>
					</label>

					<label class="wsscd-logic-option">
						<input type="radio"
							   name="conditions_logic"
							   value="any"
							   <?php checked( $conditions_logic, 'any' ); ?>>
						<span class="wsscd-logic-text">
							<?php esc_html_e( 'Any condition', 'smart-cycle-discounts' ); ?>
							<span class="wsscd-logic-hint" aria-label="<?php esc_attr_e( 'OR logic', 'smart-cycle-discounts' ); ?>"><?php esc_html_e( '(OR)', 'smart-cycle-discounts' ); ?></span>
						</span>
					</label>
				</div>
			</div>
		</fieldset>

		<!-- Conditions List -->
		<div id="wsscd-conditions-list" class="wsscd-conditions-list" data-logic="<?php echo esc_attr( $conditions_logic ); ?>">
		<?php 
		// Define a function to render condition row
		$render_condition_row = function( $index, $condition = array() ) use ( $condition_types, $get_operators_for_type ) {
			$condition_type = isset( $condition['condition_type'] ) ? sanitize_text_field( $condition['condition_type'] ) : '';
			$condition_mode = isset( $condition['mode'] ) ? sanitize_text_field( $condition['mode'] ) : 'include';
			$condition_operator = isset( $condition['operator'] ) ? sanitize_text_field( $condition['operator'] ) : '';
			$condition_value = isset( $condition['value'] ) ? sanitize_text_field( $condition['value'] ) : '';
			$condition_value2 = isset( $condition['value2'] ) ? sanitize_text_field( $condition['value2'] ) : '';
			
			// Get operators for this condition type
			$operators = $condition_type ? $get_operators_for_type( $condition_type ) : array();
			$has_type = ! empty( $condition_type );
			$has_operator = ! empty( $condition_operator );
			$is_between = in_array( $condition_operator, array( 'between', 'not_between' ), true );
			?>
			<div class="wsscd-condition-wrapper" data-index="<?php echo esc_attr( $index ); ?>">
				<div class="wsscd-condition-row">
					<div class="wsscd-condition-fields">
						<select name="conditions[<?php echo esc_attr( $index ); ?>][mode]"
								class="wsscd-condition-mode wsscd-enhanced-select"
								data-index="<?php echo esc_attr( $index ); ?>">
							<option value="include" <?php selected( $condition_mode, 'include' ); ?>>
								<?php esc_html_e( 'Include', 'smart-cycle-discounts' ); ?>
							</option>
							<option value="exclude" <?php selected( $condition_mode, 'exclude' ); ?>>
								<?php esc_html_e( 'Exclude', 'smart-cycle-discounts' ); ?>
							</option>
						</select>

						<select name="conditions[<?php echo esc_attr( $index ); ?>][condition_type]"
								class="wsscd-condition-type wsscd-enhanced-select"
								data-index="<?php echo esc_attr( $index ); ?>">
							<option value=""><?php esc_html_e( 'Select condition type', 'smart-cycle-discounts' ); ?></option>
							<?php foreach ( $condition_types as $group_key => $group ) : ?>
								<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
									<?php foreach ( $group['options'] as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $condition_type, $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>

						<select name="conditions[<?php echo esc_attr( $index ); ?>][operator]"
								class="wsscd-condition-operator wsscd-enhanced-select"
								data-index="<?php echo esc_attr( $index ); ?>"
								<?php echo esc_attr( ( ! $has_type ) ? 'disabled' : '' ); ?>>
							<option value=""><?php esc_html_e( 'Select operator', 'smart-cycle-discounts' ); ?></option>
							<?php if ( $has_type ) : ?>
								<?php foreach ( $operators as $op_value => $op_label ) : ?>
									<option value="<?php echo esc_attr( $op_value ); ?>" <?php selected( $condition_operator, $op_value ); ?>>
										<?php echo esc_html( $op_label ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>

						<div class="wsscd-condition-value-wrapper" data-index="<?php echo esc_attr( $index ); ?>">
							<input type="text"
								   name="conditions[<?php echo esc_attr( $index ); ?>][value]"
								   class="wsscd-condition-value wsscd-condition-value-single wsscd-enhanced-input"
								   value="<?php echo esc_attr( $condition_value ); ?>"
								   placeholder="<?php esc_attr_e( 'Enter value', 'smart-cycle-discounts' ); ?>"
								   <?php disabled( ! $has_operator ); ?>>
							<span class="wsscd-condition-value-separator<?php echo esc_attr( $is_between ? '' : ' wsscd-hidden' ); ?>">
								<?php esc_html_e( 'and', 'smart-cycle-discounts' ); ?>
							</span>
							<input type="text"
								   name="conditions[<?php echo esc_attr( $index ); ?>][value2]"
								   value="<?php echo esc_attr( $condition_value2 ); ?>"
								   placeholder="<?php esc_attr_e( 'Max value', 'smart-cycle-discounts' ); ?>"
								   class="wsscd-condition-value wsscd-condition-value-between wsscd-enhanced-input<?php echo esc_attr( $is_between ? '' : ' wsscd-hidden' ); ?>"
								   <?php disabled( ! $has_operator ); ?>>
						</div>
					</div>

					<div class="wsscd-condition-actions">
						<?php
						WSSCD_Button_Helper::icon(
							'trash',
							__( 'Remove this condition', 'smart-cycle-discounts' ),
							array(
								'style'   => 'secondary',
								'classes' => array( 'wsscd-remove-condition' ),
							)
						);
						?>
					</div>
				</div>

				<!-- Inline validation error container - now outside flex row -->
				<div class="wsscd-condition-error-container"></div>
			</div>
            <?php
		};
        
        // Render existing conditions only if there are any
        if ( ! empty( $conditions ) ) {
            foreach ( $conditions as $index => $condition ) {
                $render_condition_row( $index, $condition );
            }
        }
        // No default empty condition row - let JavaScript handle that based on state
        ?>
		</div>

		<!-- Condition Actions -->
		<div class="wsscd-condition-actions-wrapper">
			<?php
			WSSCD_Button_Helper::secondary(
				__( 'Add Condition', 'smart-cycle-discounts' ),
				array(
					'icon'    => 'plus-alt',
					'classes' => array( 'wsscd-add-condition' ),
				)
			);
			?>

			<div class="wsscd-condition-help">
				<p class="description">
					<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Conditions are automatically applied as you create them', 'smart-cycle-discounts' ); ?>
				</p>
			</div>
		</div>

		<!-- Conditions Summary Panel -->
		<div class="wsscd-conditions-summary wsscd-hidden" role="region" aria-label="<?php esc_attr_e( 'Active Filters Summary', 'smart-cycle-discounts' ); ?>">
			<div class="wsscd-summary-header">
				<h4>
					<?php WSSCD_Icon_Helper::render( 'filter', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Active Filters', 'smart-cycle-discounts' ); ?>
				</h4>
				<?php
				WSSCD_Button_Helper::icon(
					'arrow-up-alt2',
					__( 'Toggle summary', 'smart-cycle-discounts' ),
					array(
						'style'   => 'link',
						'classes' => array( 'wsscd-toggle-summary' ),
					)
				);
				?>
			</div>
			<div class="wsscd-summary-content">
				<div class="wsscd-summary-logic">
					<strong><?php esc_html_e( 'Logic:', 'smart-cycle-discounts' ); ?></strong>
					<span class="wsscd-summary-logic-value"></span>
				</div>
				<div class="wsscd-summary-conditions">
					<ul class="wsscd-summary-list" role="list"></ul>
				</div>
				<div class="wsscd-summary-count">
					<span class="wsscd-condition-count">0</span>
					<span><?php esc_html_e( 'conditions active', 'smart-cycle-discounts' ); ?></span>
				</div>
			</div>
		</div>
		</div>
	</div><!-- #wsscd-advanced-filters-container -->
	<?php
	$conditions_content = ob_get_clean();

	wsscd_wizard_card( array(
		'title' => __( 'Advanced Filters', 'smart-cycle-discounts' ),
		'icon' => 'filter',
		'badge' => array(
			'text' => __( 'Optional', 'smart-cycle-discounts' ),
			'type' => 'optional'
		),
		'subtitle' => esc_html__( 'Add conditions to filter products based on specific criteria', 'smart-cycle-discounts' ),
		'content' => $conditions_content,
		'class' => 'wsscd-conditions-section',
		'help_topic' => 'card-advanced-filters'
	) );
	?>
	<?php endif; // End $has_pro_access ?>
	<?php endif; // End is__premium_only() ?>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
wsscd_wizard_render_step( array(
    'title' => esc_html__( 'Product Selection', 'smart-cycle-discounts' ),
    'description' => esc_html__( 'Choose which products will be eligible for this discount', 'smart-cycle-discounts' ),
    'content' => $content,
    'step' => 'products'
) );
?>

<!-- Initialize state data for Products step -->
<?php
// Build saved data for JavaScript
$saved_data_for_js = array(
    'selection_type' => $product_selection_type,
    'category_ids' => $selected_categories,
    'category_options' => $category_options,
    'product_ids' => $product_ids,
    'random_count' => $random_count,
    'conditions' => $conditions,
    'conditions_logic' => $conditions_logic,
    'smart_criteria' => $smart_criteria
);

// Add selected products data if we have selected products
if ( ! empty( $selected_product_objects ) ) {
	$saved_data_for_js['selected_products_data'] = array_map( function( $product ) {
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'sku' => $product->get_sku(),
            'image' => wp_get_attachment_url( $product->get_image_id() )
		);
    }, $selected_product_objects );
}

// Validation rules are now handled by the centralized field schema system

wsscd_wizard_state_script( 'products', $saved_data_for_js, array(
    'strings' => $js_strings,
    'condition_types' => $condition_types,
    'operator_mappings' => $operator_mappings
) );
?>


