<?php
/**
 * Campaign wizard - Product Selection step
 *
 * Comprehensive product selection with categories, smart filters,
 * and advanced conditions for targeted discount campaigns
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/views/campaigns/wizard
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security: Verify user capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-cycle-discounts' ) );
}

// Initialize variables using shared function
scd_wizard_init_step_vars( $step_data, $validation_errors );

// Extract values with defaults handled by field schema
$product_selection_type = $step_data['product_selection_type'] ?? 'all_products';
$selected_categories = $step_data['category_ids'] ?? array( 'all' );
$product_ids = $step_data['product_ids'] ?? array();
$random_count = $step_data['random_count'] ?? 10;
$conditions = $step_data['conditions'] ?? array();
$conditions_logic = $step_data['conditions_logic'] ?? 'all';
$smart_criteria = $step_data['smart_criteria'] ?? '';

// Ensure arrays are proper type for template usage
$selected_categories = is_array( $selected_categories ) ? $selected_categories : array( 'all' );
$product_ids = is_array( $product_ids ) ? $product_ids : array();
$conditions = is_array( $conditions ) ? $conditions : array();

// Normalize conditions to ensure all required fields are present
// Database format: {condition_type, operator, value, value2, mode}
$conditions = array_map( function( $condition ) {
	if ( ! is_array( $condition ) ) {
		return $condition;
	}

	// Database format (standard format used throughout the system)
	// Ensure all required fields are present with defaults
	if ( isset( $condition['condition_type'] ) ) {
		return array(
			'condition_type' => $condition['condition_type'],
			'operator'       => isset( $condition['operator'] ) ? $condition['operator'] : '',
			'mode'           => isset( $condition['mode'] ) ? $condition['mode'] : 'include',
			'value'          => isset( $condition['value'] ) ? $condition['value'] : '',
			'value2'         => isset( $condition['value2'] ) ? $condition['value2'] : '',
		);
	}

	return $condition;
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

// Get category data for saved categories
$category_data = array();
if ( ! empty( $selected_categories ) && 'all' !== $selected_categories[0] ) {
	foreach ( $selected_categories as $cat_id ) {
		if ( 'all' === $cat_id ) {
			continue;
		}
		
		$category = get_term( $cat_id, 'product_cat' );
		if ( $category && ! is_wp_error( $category ) ) {
			$category_data[] = array(
				'id' => $category->term_id,
				'name' => $category->name,
				'count' => $category->count,
				'level' => 0
			);
		}
	}
}

// Condition types are now defined in SCD_Field_Definitions::get_condition_types()
$condition_types = class_exists( 'SCD_Field_Definitions' ) ? SCD_Field_Definitions::get_condition_types() : array();

// Operator mappings are now defined in SCD_Field_Definitions::get_operator_mappings()
$operator_mappings = class_exists( 'SCD_Field_Definitions' ) ? SCD_Field_Definitions::get_operator_mappings() : array();

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

// Prepare content for template wrapper
ob_start();
?>
	<?php scd_wizard_validation_notice( $validation_errors ); ?>
	
	<?php wp_nonce_field( 'scd_wizard_products_step', 'scd_products_nonce' ); ?>
	
	<!-- Category Selection -->
	<?php
	ob_start();
	?>
	<div class="scd-form-field scd-form-field--full">
		<label for="scd-campaign-categories">
			<?php esc_html_e( 'Categories', 'smart-cycle-discounts' ); ?>
			<?php SCD_Tooltip_Helper::render( __( 'Filter products by category. Select multiple categories to include products from any of them.', 'smart-cycle-discounts' ) ); ?>
		</label>
		<select id="scd-campaign-categories"
				name="category_ids[]"
				multiple="multiple"
				class="scd-category-select">
			<!-- Categories will be loaded via AJAX -->
		</select>
	</div>
	<?php
	$category_content = ob_get_clean();
	
	scd_wizard_card( array(
		'title' => esc_html__( 'Product Categories', 'smart-cycle-discounts' ),
		'subtitle' => esc_html__( 'Select categories to include in this campaign', 'smart-cycle-discounts' ),
		'icon' => 'category',
		'content' => $category_content,
		'class' => 'scd-category-selection'
	) );
	?>
	
	<!-- Product Selection Method -->
	<?php
	ob_start();
	?>
	<div class="scd-product-selection-cards">
		<!-- All Products Card -->
		<div class="scd-card scd-card--interactive scd-card-option <?php echo 'all_products' === $product_selection_type ? 'scd-card-option--selected' : ''; ?>">
			<input type="radio"
				   name="product_selection_type"
				   value="all_products"
				   id="product_selection_all"
				   <?php checked( $product_selection_type, 'all_products' ); ?>>
			<label for="product_selection_all" class="scd-card__content">
				<h4 class="scd-card__title"><?php esc_html_e( 'All Products', 'smart-cycle-discounts' ); ?></h4>
				<p class="scd-card__subtitle"><?php esc_html_e( 'Apply discount to all products in selected categories', 'smart-cycle-discounts' ); ?></p>
			</label>
		</div>

		<!-- Random Products Card -->
		<div class="scd-card scd-card--interactive scd-card-option <?php echo 'random_products' === $product_selection_type ? 'scd-card-option--selected' : ''; ?>">
			<input type="radio"
				   name="product_selection_type"
				   value="random_products"
				   id="product_selection_random"
				   <?php checked( $product_selection_type, 'random_products' ); ?>>
			<label for="product_selection_random" class="scd-card__content">
				<h4 class="scd-card__title"><?php esc_html_e( 'Random Products', 'smart-cycle-discounts' ); ?></h4>
				<p class="scd-card__subtitle"><?php esc_html_e( 'Randomly select a specific number of products', 'smart-cycle-discounts' ); ?></p>
			</label>
			
			<div class="scd-random-count">
				<label for="scd-random-count">
					<?php esc_html_e( 'Number of products:', 'smart-cycle-discounts' ); ?>
					<?php SCD_Tooltip_Helper::render( __('Specify how many random products to select from the chosen categories.', 'smart-cycle-discounts') ); ?>
				</label>
				<input type="number"
					   id="scd-random-count"
					   name="random_count"
					   value="<?php echo esc_attr( $random_count ); ?>"
					   min="1"
					   max="100"
					   class="scd-enhanced-input scd-input-small">
			</div>
		</div>
		
		<!-- Specific Products Card -->
		<div class="scd-card scd-card--interactive scd-card-option <?php echo 'specific_products' === $product_selection_type ? 'scd-card-option--selected' : ''; ?>">
			<input type="radio"
				   name="product_selection_type"
				   value="specific_products"
				   id="product_selection_specific"
				   <?php checked( $product_selection_type, 'specific_products' ); ?>>
			<label for="product_selection_specific" class="scd-card__content">
				<h4 class="scd-card__title"><?php esc_html_e( 'Specific Products', 'smart-cycle-discounts' ); ?></h4>
				<p class="scd-card__subtitle"><?php esc_html_e( 'Hand-pick individual products to discount', 'smart-cycle-discounts' ); ?></p>
			</label>
			
			<div class="scd-specific-products">
				<div class="scd-product-search-container">
					<label for="scd-product-search">
						<?php esc_html_e( 'Search and select products:', 'smart-cycle-discounts' ); ?>
						<?php SCD_Tooltip_Helper::render( __('Search for specific products by name, SKU, or ID. You can select multiple products.', 'smart-cycle-discounts') ); ?>
					</label>
					<!-- Hidden field stores actual product IDs (single source of truth) -->
					<input type="hidden"
						   id="scd-product-ids-hidden"
						   name="product_ids"
						   value="<?php echo esc_attr( ! empty( $product_ids ) ? implode( ',', array_map( 'absint', $product_ids ) ) : '' ); ?>" />
					<!-- TomSelect UI (syncs to hidden field) -->
					<select id="scd-product-search"
							multiple="multiple"
							class="scd-product-search-select"
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
		<div class="scd-card scd-card--interactive scd-card-option <?php echo 'smart_selection' === $product_selection_type ? 'scd-card-option--selected' : ''; ?>">
			<input type="radio"
				   name="product_selection_type"
				   value="smart_selection"
				   id="product_selection_smart"
				   <?php checked( $product_selection_type, 'smart_selection' ); ?>>
			<label for="product_selection_smart" class="scd-card__content">
				<h4 class="scd-card__title"><?php esc_html_e( 'Smart Selection', 'smart-cycle-discounts' ); ?></h4>
				<p class="scd-card__subtitle"><?php esc_html_e( 'Auto-select products based on business criteria', 'smart-cycle-discounts' ); ?></p>
			</label>
			
			<div class="scd-smart-criteria">
				<div class="scd-smart-label">
					<?php esc_html_e( 'Select products based on:', 'smart-cycle-discounts' ); ?>
					<?php SCD_Tooltip_Helper::render( __('Automatically select products based on predefined criteria like best sellers, featured products, or inventory levels.', 'smart-cycle-discounts') ); ?>
				</div>
				<div class="scd-smart-options">
					<label class="scd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="best_sellers" 
							   <?php checked( $smart_criteria, 'best_sellers' ); ?>>
						<div class="scd-smart-option-content">
							<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 20 ) ); ?>
							<div class="scd-smart-option-text">
								<strong><?php esc_html_e( 'Best Sellers', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Top performing products by sales', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
					<label class="scd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="featured"
							   <?php checked( $smart_criteria, 'featured' ); ?>>
						<div class="scd-smart-option-content">
							<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16 ) ); ?>
							<div class="scd-smart-option-text">
								<strong><?php esc_html_e( 'Featured Products', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Hand-picked showcase products', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
					<label class="scd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="low_stock"
							   <?php checked( $smart_criteria, 'low_stock' ); ?>>
						<div class="scd-smart-option-content">
							<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
							<div class="scd-smart-option-text">
								<strong><?php esc_html_e( 'Low Stock', 'smart-cycle-discounts' ); ?></strong>
								<span><?php esc_html_e( 'Products with 10 or fewer items in stock', 'smart-cycle-discounts' ); ?></span>
							</div>
						</div>
					</label>
					<label class="scd-smart-option">
						<input type="radio" 
							   name="smart_criteria" 
							   value="new_arrivals"
							   <?php checked( $smart_criteria, 'new_arrivals' ); ?>>
						<div class="scd-smart-option-content">
							<?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16 ) ); ?>
							<div class="scd-smart-option-text">
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
	
	scd_wizard_card( array(
		'title' => esc_html__( 'Select Products', 'smart-cycle-discounts' ),
		'subtitle' => esc_html__( 'Choose which products will receive discounts in this campaign', 'smart-cycle-discounts' ),
		'icon' => 'products',
		'content' => $selection_content,
		'class' => 'scd-product-selection-method'
	) );
	?>
	
	<!-- Advanced Conditions -->
	<?php
	// Check if user can use advanced product filters
	$can_use_filters = $feature_gate ? $feature_gate->can_use_advanced_product_filters() : false;
	$upgrade_url = $feature_gate ? $feature_gate->get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

	ob_start();
	?>

	<div class="scd-pro-container <?php echo $can_use_filters ? '' : 'scd-pro-container--locked'; ?>" id="scd-advanced-filters-container">
		<?php if ( ! $can_use_filters ) : ?>
			<?php
			// Use centralized PRO overlay template
			$description = __( 'Advanced filtering with custom conditions', 'smart-cycle-discounts' );
			$features = array(
				__( 'Price & inventory conditions', 'smart-cycle-discounts' ),
				__( 'Product attributes (weight, dimensions, SKU)', 'smart-cycle-discounts' ),
				__( 'Status & performance filters', 'smart-cycle-discounts' ),
				__( 'Complex AND/OR logic', 'smart-cycle-discounts' ),
			);
			include SCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
			?>
		<?php endif; ?>

		<!-- Actual Advanced Filters UI (blurred for free users) -->
		<div class="scd-pro-background">
			<fieldset class="scd-conditions-logic-fieldset" <?php echo $can_use_filters ? '' : 'disabled aria-hidden="true"'; ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Condition Logic', 'smart-cycle-discounts' ); ?></legend>
			<div class="scd-conditions-logic">
				<span class="scd-logic-label">
					<?php esc_html_e( 'Filter products that match', 'smart-cycle-discounts' ); ?>
					<?php SCD_Tooltip_Helper::render( __('Choose whether products must meet all criteria or just one to be included', 'smart-cycle-discounts') ); ?>
				</span>
				<div class="scd-logic-selector" role="radiogroup" aria-label="<?php esc_attr_e( 'Condition matching logic', 'smart-cycle-discounts' ); ?>">
					<label class="scd-logic-option">
						<input type="radio"
							   name="conditions_logic"
							   value="all"
							   <?php checked( $conditions_logic, 'all' ); ?>>
						<span class="scd-logic-text">
							<?php esc_html_e( 'All conditions', 'smart-cycle-discounts' ); ?>
							<span class="scd-logic-hint" aria-label="<?php esc_attr_e( 'AND logic', 'smart-cycle-discounts' ); ?>"><?php esc_html_e( '(AND)', 'smart-cycle-discounts' ); ?></span>
						</span>
					</label>

					<label class="scd-logic-option">
						<input type="radio"
							   name="conditions_logic"
							   value="any"
							   <?php checked( $conditions_logic, 'any' ); ?>>
						<span class="scd-logic-text">
							<?php esc_html_e( 'Any condition', 'smart-cycle-discounts' ); ?>
							<span class="scd-logic-hint" aria-label="<?php esc_attr_e( 'OR logic', 'smart-cycle-discounts' ); ?>"><?php esc_html_e( '(OR)', 'smart-cycle-discounts' ); ?></span>
						</span>
					</label>
				</div>
			</div>
		</fieldset>

		<!-- Conditions List -->
		<div id="scd-conditions-list" class="scd-conditions-list" data-logic="<?php echo esc_attr( $conditions_logic ); ?>">
		<?php 
		// Define a function to render condition row
		$render_condition_row = function( $index, $condition = array() ) use ( $condition_types, $get_operators_for_type ) {
			$condition_type = isset( $condition['type'] ) ? sanitize_text_field( $condition['type'] ) : '';
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
			<div class="scd-condition-wrapper" data-index="<?php echo esc_attr( $index ); ?>">
				<div class="scd-condition-row">
					<div class="scd-condition-fields">
						<select name="conditions[<?php echo esc_attr( $index ); ?>][mode]"
								class="scd-condition-mode scd-enhanced-select"
								data-index="<?php echo esc_attr( $index ); ?>">
							<option value="include" <?php selected( $condition_mode, 'include' ); ?>>
								<?php esc_html_e( 'Include', 'smart-cycle-discounts' ); ?>
							</option>
							<option value="exclude" <?php selected( $condition_mode, 'exclude' ); ?>>
								<?php esc_html_e( 'Exclude', 'smart-cycle-discounts' ); ?>
							</option>
						</select>

						<select name="conditions[<?php echo esc_attr( $index ); ?>][type]"
								class="scd-condition-type scd-enhanced-select"
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
								class="scd-condition-operator scd-enhanced-select"
								data-index="<?php echo esc_attr( $index ); ?>"
								<?php echo ( ! $has_type ) ? 'disabled="disabled"' : ''; ?>>
							<option value=""><?php esc_html_e( 'Select operator', 'smart-cycle-discounts' ); ?></option>
							<?php if ( $has_type ) : ?>
								<?php foreach ( $operators as $op_value => $op_label ) : ?>
									<option value="<?php echo esc_attr( $op_value ); ?>" <?php selected( $condition_operator, $op_value ); ?>>
										<?php echo esc_html( $op_label ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>

						<div class="scd-condition-value-wrapper" data-index="<?php echo esc_attr( $index ); ?>">
							<input type="text"
								   name="conditions[<?php echo esc_attr( $index ); ?>][value]"
								   class="scd-condition-value scd-condition-value-single scd-enhanced-input"
								   value="<?php echo esc_attr( $condition_value ); ?>"
								   placeholder="<?php esc_attr_e( 'Enter value', 'smart-cycle-discounts' ); ?>"
								   <?php echo ( ! $has_operator ) ? 'disabled="disabled"' : ''; ?>>
							<span class="scd-condition-value-separator<?php echo $is_between ? '' : ' scd-hidden'; ?>">
								<?php esc_html_e( 'and', 'smart-cycle-discounts' ); ?>
							</span>
							<input type="text"
								   name="conditions[<?php echo esc_attr( $index ); ?>][value2]"
								   value="<?php echo esc_attr( $condition_value2 ); ?>"
								   placeholder="<?php esc_attr_e( 'Max value', 'smart-cycle-discounts' ); ?>"
								   class="scd-condition-value scd-condition-value-between scd-enhanced-input<?php echo $is_between ? '' : ' scd-hidden'; ?>"
								   <?php echo ( ! $has_operator ) ? 'disabled="disabled"' : ''; ?>>
						</div>
					</div>

					<div class="scd-condition-actions">
						<?php
						SCD_Button_Helper::icon(
							'trash',
							__( 'Remove this condition', 'smart-cycle-discounts' ),
							array(
								'style'   => 'secondary',
								'classes' => array( 'scd-remove-condition' ),
							)
						);
						?>
					</div>
				</div>

				<!-- Inline validation error container - now outside flex row -->
				<div class="scd-condition-error-container"></div>
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
		<div class="scd-condition-actions-wrapper">
			<?php
			SCD_Button_Helper::secondary(
				__( 'Add Condition', 'smart-cycle-discounts' ),
				array(
					'icon'    => 'plus-alt',
					'classes' => array( 'scd-add-condition' ),
				)
			);
			?>

			<div class="scd-condition-help">
				<p class="description">
					<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Conditions are automatically applied as you create them', 'smart-cycle-discounts' ); ?>
				</p>
			</div>
		</div>

		<!-- Conditions Summary Panel -->
		<div class="scd-conditions-summary scd-hidden" role="region" aria-label="<?php esc_attr_e( 'Active Filters Summary', 'smart-cycle-discounts' ); ?>">
			<div class="scd-summary-header">
				<h4>
					<?php echo SCD_Icon_Helper::get( 'filter', array( 'size' => 16 ) ); ?>
					<?php esc_html_e( 'Active Filters', 'smart-cycle-discounts' ); ?>
				</h4>
				<?php
				SCD_Button_Helper::icon(
					'arrow-up-alt2',
					__( 'Toggle summary', 'smart-cycle-discounts' ),
					array(
						'style'   => 'link',
						'classes' => array( 'scd-toggle-summary' ),
					)
				);
				?>
			</div>
			<div class="scd-summary-content">
				<div class="scd-summary-logic">
					<strong><?php esc_html_e( 'Logic:', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-summary-logic-value"></span>
				</div>
				<div class="scd-summary-conditions">
					<ul class="scd-summary-list" role="list"></ul>
				</div>
				<div class="scd-summary-count">
					<span class="scd-condition-count">0</span>
					<span><?php esc_html_e( 'conditions active', 'smart-cycle-discounts' ); ?></span>
				</div>
			</div>
		</div>
		</div><!-- .scd-pro-background -->
	</div><!-- .scd-pro-container -->
	<?php
	$conditions_content = ob_get_clean();

	// Create the card with the optional badge in the title
	ob_start();
	?>
		<?php echo SCD_Icon_Helper::get( 'filter', array( 'size' => 16 ) ); ?>
		<?php esc_html_e( 'Advanced Filters', 'smart-cycle-discounts' ); ?>
		<span class="scd-badge scd-badge--optional"><?php esc_html_e( 'Optional', 'smart-cycle-discounts' ); ?></span>
	<?php
	$filters_title = ob_get_clean();

	scd_wizard_card( array(
		'title' => $filters_title,
		'subtitle' => $can_use_filters
			? esc_html__( 'Add conditions to filter products based on specific criteria', 'smart-cycle-discounts' )
			: esc_html__( 'Upgrade to Pro to unlock advanced product filtering capabilities', 'smart-cycle-discounts' ),
		'content' => $conditions_content,
		'class' => 'scd-conditions-section'
	) );
	?>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
scd_wizard_render_step( array(
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
    'category_data' => $category_data,
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

scd_wizard_state_script( 'products', $saved_data_for_js, array(
    'strings' => $js_strings,
    'condition_types' => $condition_types,
    'operator_mappings' => $operator_mappings
) );
?>


