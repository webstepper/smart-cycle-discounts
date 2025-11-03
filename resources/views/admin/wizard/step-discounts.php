<?php
/**
 * Campaign wizard - Discount configuration step (Refactored)
 *
 * Improved architecture with sidebar preview, contextual guidance,
 * and enhanced user experience
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

// Initialize variables using shared function
scd_wizard_init_step_vars($step_data, $validation_errors);

// Field schema handles default values now - no need to set them here

// Debug: Check what's in step_data after wp_parse_args
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( '[SCD Discounts Template] Step data after defaults: ' . print_r( $step_data, true ) );
}

// Extract values for easier use with defaults handled by field schema
$discount_type = $step_data['discount_type'] ?? 'percentage';
$discount_value_percentage = $step_data['discount_value_percentage'] ?? '';
$discount_value_fixed = $step_data['discount_value_fixed'] ?? '';
$conditions = $step_data['conditions'] ?? array();
$conditions_logic = $step_data['conditions_logic'] ?? 'all';
$usage_limit_per_customer = $step_data['usage_limit_per_customer'] ?? '';
$total_usage_limit = $step_data['total_usage_limit'] ?? '';
$lifetime_usage_cap = $step_data['lifetime_usage_cap'] ?? '';
$apply_to = $step_data['apply_to'] ?? 'per_item';
$max_discount_amount = $step_data['max_discount_amount'] ?? '';
$minimum_quantity = $step_data['minimum_quantity'] ?? '';
$minimum_order_amount = $step_data['minimum_order_amount'] ?? '';
$stack_with_others = $step_data['stack_with_others'] ?? false;
$allow_coupons = $step_data['allow_coupons'] ?? false;
$apply_to_sale_items = $step_data['apply_to_sale_items'] ?? false;
$badge_enabled = $step_data['badge_enabled'] ?? false;
$badge_text = $step_data['badge_text'] ?? 'auto';
$badge_bg_color = $step_data['badge_bg_color'] ?? '#ff0000';
$badge_text_color = $step_data['badge_text_color'] ?? '#ffffff';
$badge_position = $step_data['badge_position'] ?? 'top-right';
$bogo_buy_quantity = $step_data['bogo_buy_quantity'] ?? 1;
$bogo_get_quantity = $step_data['bogo_get_quantity'] ?? 1;
$bogo_discount = $step_data['bogo_discount'] ?? 100;
$bogo_apply_to = $step_data['bogo_apply_to'] ?? 'cheapest';

// Get WooCommerce currency settings
$currency_symbol = get_woocommerce_currency_symbol();
$currency_pos = get_option( 'woocommerce_currency_pos' );
$price_decimals = wc_get_price_decimals();
$decimal_separator = wc_get_price_decimal_separator();
$thousand_separator = wc_get_price_thousand_separator();

// Currency data for JavaScript - handled by Asset Manager's localization
$currency_data = array(
    'currency_symbol' => $currency_symbol,
    'currency_pos' => $currency_pos,
    'price_decimals' => $price_decimals,
    'decimal_separator' => $decimal_separator,
    'thousand_separator' => $thousand_separator,
);

// Get selected products from wizard session
$products_for_display = array();
if ( class_exists( 'SCD_Core_Container' ) ) {
    try {
        $container = SCD_Core_Container::get_instance();
        $state_service = $container->get('wizard.state_service');
        $products_data = $state_service->get_step_data('products');
        
        // Get product IDs from session
        $product_ids = $products_data['product_ids'] ?? array();
        
        if ( ! empty( $product_ids ) ) {
            // Get product details
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if ( $product ) {
                    $products_for_display[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => $product->get_regular_price(),
                        'formatted_price' => $product->get_price_html()
                    );
                }
            }
        }
    } catch (Exception $e) {
        // Fallback if container not available
    }
}

// Make currency data and selected products available to Asset Manager
global $scd_discount_step_data;
$scd_discount_step_data = array_merge($currency_data, array(
    'selected_products' => $products_for_display
));

// Helper function to format price with currency
if ( ! function_exists( 'scd_format_price' ) ) {
    function scd_format_price($amount) {
        return wc_price($amount);
    }
}

// Helper function to format example price
if ( ! function_exists( 'scd_format_example_price' ) ) {
    function scd_format_example_price($amount) {
        return strip_tags(wc_price($amount));
    }
}


// Prepare content for template wrapper
ob_start();
?>
        <!-- Step 1: Discount Type Selection -->
        <?php
        ob_start();
        ?>
                <div class="scd-field-wrapper scd-field-required">
                    <input type="hidden" 
                           id="discount_type" 
                           name="discount_type" 
                           value="<?php echo esc_attr($discount_type); ?>"
                           class="scd-field"
                           data-required="true"
                           data-label="Discount Type"
                           data-pattern-message="Please select a discount type"
                           aria-required="true">
                </div>
                
                <div class="scd-discount-type-grid">
                    <?php
                    // Get upgrade URL once for all locked discount types
                    $upgrade_url = $feature_gate ? $feature_gate->get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
                    ?>

                    <!-- Percentage Discount -->
                    <div class="scd-discount-type-card <?php echo esc_attr( $discount_type === 'percentage' ? 'selected' : '' ); ?>"
                         data-type="percentage">
                        <div class="scd-discount-type-card__icon">
                            <span class="dashicons dashicons-tag"></span>
                        </div>
                        <h4 class="scd-discount-type-card__title"><?php esc_html_e('Percentage Off', 'smart-cycle-discounts'); ?></h4>
                        <p class="scd-discount-type-card__description">
                            <?php esc_html_e('Take a percentage off the original price', 'smart-cycle-discounts'); ?>
                        </p>
                        <div class="scd-discount-type-card__example">
                            <?php 
                            $example = sprintf(
                                __('20%% off %s = %s', 'smart-cycle-discounts'),
                                scd_format_example_price(100),
                                scd_format_example_price(80)
                            );
                            echo esc_html($example);
                            ?>
                        </div>
                    </div>
                    
                    <!-- Fixed Amount Discount -->
                    <div class="scd-discount-type-card <?php echo esc_attr( $discount_type === 'fixed' ? 'selected' : '' ); ?>" 
                         data-type="fixed">
                        <div class="scd-discount-type-card__icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <h4 class="scd-discount-type-card__title"><?php esc_html_e('Fixed Amount Off', 'smart-cycle-discounts'); ?></h4>
                        <p class="scd-discount-type-card__description">
                            <?php esc_html_e('Subtract a fixed dollar amount', 'smart-cycle-discounts'); ?>
                        </p>
                        <div class="scd-discount-type-card__example">
                            <?php 
                            $example = sprintf(
                                __('%s off %s = %s', 'smart-cycle-discounts'),
                                scd_format_example_price(10),
                                scd_format_example_price(100),
                                scd_format_example_price(90)
                            );
                            echo esc_html($example);
                            ?>
                        </div>
                    </div>
                    
                    <!-- Tiered Discount -->
                    <?php
                    $can_use_tiered = $feature_gate ? $feature_gate->can_use_discount_type( 'tiered' ) : false;
                    $tiered_classes = 'scd-discount-type-card';
                    if ( $discount_type === 'tiered' ) {
                        $tiered_classes .= ' selected';
                    }
                    if ( ! $can_use_tiered ) {
                        $tiered_classes .= ' scd-discount-type-card--locked';
                    }
                    ?>
                    <div class="<?php echo esc_attr( $tiered_classes ); ?>"
                         data-type="tiered"
                         <?php if ( ! $can_use_tiered ) : ?>data-locked="true"<?php endif; ?>>
                        <?php if ( $can_use_tiered ) : ?>
                            <!-- Available: Show normal card -->
                            <div class="scd-discount-type-card__icon">
                                <span class="dashicons dashicons-chart-line"></span>
                            </div>
                            <h4 class="scd-discount-type-card__title">
                                <?php esc_html_e('Volume Discounts', 'smart-cycle-discounts'); ?>
                            </h4>
                            <p class="scd-discount-type-card__description">
                                <?php esc_html_e('More items = bigger discounts', 'smart-cycle-discounts'); ?>
                            </p>
                            <div class="scd-discount-type-card__example">
                                <?php esc_html_e('Buy 2+ save 10%, Buy 5+ save 20%', 'smart-cycle-discounts'); ?>
                            </div>
                        <?php else : ?>
                            <!-- Locked: Show informative upgrade content -->
                            <div class="scd-discount-type-card__locked-content">
                                <span class="scd-badge scd-badge--pro">
                                    🔒 <?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?>
                                </span>
                                <h4 class="scd-discount-type-card__title">
                                    <?php esc_html_e('Volume Discounts', 'smart-cycle-discounts'); ?>
                                </h4>
                                <p class="scd-discount-type-card__description">
                                    <?php esc_html_e('Reward bulk purchases with tiered pricing', 'smart-cycle-discounts'); ?>
                                </p>
                                <ul class="scd-discount-type-card__features">
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Set multiple quantity tiers', 'smart-cycle-discounts'); ?></li>
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Increase discounts at each tier', 'smart-cycle-discounts'); ?></li>
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Perfect for wholesale & B2B', 'smart-cycle-discounts'); ?></li>
                                </ul>
                                <a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-small scd-discount-type-card__upgrade-btn">
                                    <?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- BOGO Discount -->
                    <?php
                    $can_use_bogo = $feature_gate ? $feature_gate->can_use_discount_type( 'bogo' ) : false;
                    $bogo_classes = 'scd-discount-type-card';
                    if ( $discount_type === 'bogo' ) {
                        $bogo_classes .= ' selected';
                    }
                    if ( ! $can_use_bogo ) {
                        $bogo_classes .= ' scd-discount-type-card--locked';
                    }
                    ?>
                    <div class="<?php echo esc_attr( $bogo_classes ); ?>"
                         data-type="bogo"
                         <?php if ( ! $can_use_bogo ) : ?>data-locked="true"<?php endif; ?>>
                        <?php if ( $can_use_bogo ) : ?>
                            <!-- Available: Show normal card -->
                            <div class="scd-discount-type-card__icon">
                                <span class="dashicons dashicons-products"></span>
                            </div>
                            <h4 class="scd-discount-type-card__title">
                                <?php esc_html_e('BOGO Deals', 'smart-cycle-discounts'); ?>
                            </h4>
                            <p class="scd-discount-type-card__description">
                                <?php esc_html_e('Buy one get one offers', 'smart-cycle-discounts'); ?>
                            </p>
                            <div class="scd-discount-type-card__example">
                                <?php esc_html_e('Buy 2 Get 1 Free', 'smart-cycle-discounts'); ?>
                            </div>
                        <?php else : ?>
                            <!-- Locked: Show informative upgrade content -->
                            <div class="scd-discount-type-card__locked-content">
                                <span class="scd-badge scd-badge--pro">
                                    🔒 <?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?>
                                </span>
                                <h4 class="scd-discount-type-card__title">
                                    <?php esc_html_e('BOGO Deals', 'smart-cycle-discounts'); ?>
                                </h4>
                                <p class="scd-discount-type-card__description">
                                    <?php esc_html_e('Create compelling buy-one-get-one promotions', 'smart-cycle-discounts'); ?>
                                </p>
                                <ul class="scd-discount-type-card__features">
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Flexible BOGO ratios (Buy 2 Get 1, etc.)', 'smart-cycle-discounts'); ?></li>
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Percentage or free gift options', 'smart-cycle-discounts'); ?></li>
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Drive sales & clear inventory', 'smart-cycle-discounts'); ?></li>
                                </ul>
                                <a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-small scd-discount-type-card__upgrade-btn">
                                    <?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Spend Threshold Discount -->
                    <?php
                    $can_use_spend_threshold = $feature_gate ? $feature_gate->can_use_discount_type( 'spend_threshold' ) : false;
                    $spend_threshold_classes = 'scd-discount-type-card';
                    if ( $discount_type === 'spend_threshold' ) {
                        $spend_threshold_classes .= ' selected';
                    }
                    if ( ! $can_use_spend_threshold ) {
                        $spend_threshold_classes .= ' scd-discount-type-card--locked';
                    }
                    ?>
                    <div class="<?php echo esc_attr( $spend_threshold_classes ); ?>"
                         data-type="spend_threshold"
                         <?php if ( ! $can_use_spend_threshold ) : ?>data-locked="true"<?php endif; ?>>
                        <?php if ( $can_use_spend_threshold ) : ?>
                            <!-- Available: Show normal card -->
                            <div class="scd-discount-type-card__icon">
                                <span class="dashicons dashicons-money"></span>
                            </div>
                            <h4 class="scd-discount-type-card__title">
                                <?php esc_html_e('Spend Threshold', 'smart-cycle-discounts'); ?>
                            </h4>
                            <p class="scd-discount-type-card__description">
                                <?php esc_html_e('Reward customers who spend more', 'smart-cycle-discounts'); ?>
                            </p>
                            <div class="scd-discount-type-card__example">
                                <?php esc_html_e('Spend $100 get 10% off', 'smart-cycle-discounts'); ?>
                            </div>
                        <?php else : ?>
                            <!-- Locked: Show informative upgrade content -->
                            <div class="scd-discount-type-card__locked-content">
                                <span class="scd-badge scd-badge--pro">
                                    🔒 <?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?>
                                </span>
                                <h4 class="scd-discount-type-card__title">
                                    <?php esc_html_e('Spend Threshold', 'smart-cycle-discounts'); ?>
                                </h4>
                                <p class="scd-discount-type-card__description">
                                    <?php esc_html_e('Encourage higher cart values with spend-based rewards', 'smart-cycle-discounts'); ?>
                                </p>
                                <ul class="scd-discount-type-card__features">
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Multiple spending tiers', 'smart-cycle-discounts'); ?></li>
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Progressive rewards structure', 'smart-cycle-discounts'); ?></li>
                                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Boost average order value', 'smart-cycle-discounts'); ?></li>
                                </ul>
                                <a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-small scd-discount-type-card__upgrade-btn">
                                    <?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($validation_errors['discount_type'])): ?>
                    <div class="scd-field-error">
                        <?php foreach ((array)$validation_errors['discount_type'] as $error): ?>
                            <p class="error-message"><?php echo esc_html($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
        <?php
        $type_selection_content = ob_get_clean();
        
        scd_wizard_card(array(
            'title' => __('Choose Your Discount Type', 'smart-cycle-discounts'),
            'subtitle' => __('Select the discount strategy that best fits your campaign goals.', 'smart-cycle-discounts'),
            'icon' => 'tag',
            'content' => $type_selection_content
        ));
        ?>

        <!-- Step 2: Configure Discount Value -->
        <?php
        ob_start();

        // Check if selected discount type is PRO and user doesn't have access
        $selected_discount_type = $discount_type ?? '';
        $pro_discount_types = array( 'tiered', 'bogo', 'spend_threshold' );
        $is_pro_type_selected = in_array( $selected_discount_type, $pro_discount_types, true );

        // Check if user has access to the selected PRO type
        $has_access_to_selected_type = true;
        if ( $is_pro_type_selected && $feature_gate ) {
            $has_access_to_selected_type = $feature_gate->can_use_discount_type( $selected_discount_type );
        }

        // Lock container if PRO type selected AND user doesn't have access
        $should_lock_container = $is_pro_type_selected && ! $has_access_to_selected_type;
        ?>

                <div class="scd-pro-container <?php echo $should_lock_container ? 'scd-pro-container--locked' : ''; ?>" id="scd-discount-details-container"<?php echo $should_lock_container ? ' data-active-type="' . esc_attr( $selected_discount_type ) . '"' : ''; ?>>
                    <?php
                    // Render specific overlay for each PRO discount type using centralized template

                    // Tiered Pricing Overlay
                    ?>
                    <div data-discount-type="tiered">
                        <?php
                        $description = __( 'Volume-based tiered discount pricing', 'smart-cycle-discounts' );
                        $features = array(
                            __( 'Create quantity-based pricing tiers', 'smart-cycle-discounts' ),
                            __( 'Progressive discount structures', 'smart-cycle-discounts' ),
                            __( 'Bulk purchase incentives', 'smart-cycle-discounts' ),
                            __( 'Wholesale pricing automation', 'smart-cycle-discounts' ),
                        );
                        include SCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
                        ?>
                    </div>

                    <?php
                    // BOGO Overlay
                    ?>
                    <div data-discount-type="bogo">
                        <?php
                        $description = __( 'Buy One Get One deal configurations', 'smart-cycle-discounts' );
                        $features = array(
                            __( 'BOGO and BOGOF deals', 'smart-cycle-discounts' ),
                            __( 'Buy X Get Y promotions', 'smart-cycle-discounts' ),
                            __( 'Mix and match product combinations', 'smart-cycle-discounts' ),
                            __( 'Flexible quantity ratios', 'smart-cycle-discounts' ),
                        );
                        include SCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
                        ?>
                    </div>

                    <?php
                    // Spend Threshold Overlay
                    ?>
                    <div data-discount-type="spend_threshold">
                        <?php
                        $description = __( 'Cart total-based discount rewards', 'smart-cycle-discounts' );
                        $features = array(
                            __( 'Minimum purchase amount triggers', 'smart-cycle-discounts' ),
                            __( 'Spend $X get Y% off deals', 'smart-cycle-discounts' ),
                            __( 'Progressive spending rewards', 'smart-cycle-discounts' ),
                            __( 'Cart value-based incentives', 'smart-cycle-discounts' ),
                        );
                        include SCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
                        ?>
                    </div>

                    <!-- Actual discount configuration (blurred for PRO types) -->
                    <div class="scd-pro-background">
                        <table class="form-table">
                    <!-- Percentage Discount Configuration -->
                    <tr class="scd-strategy-options scd-strategy-percentage <?php echo esc_attr( $discount_type === 'percentage' ? 'active' : '' ); ?>" data-strategy-type="percentage">
                        <th scope="row">
                            <label for="discount_value_percentage">
                                <?php esc_html_e('Discount Percentage', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Enter a value between 0.01 and 100', 'smart-cycle-discounts'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                        </th>
                        <td>
                            <div class="scd-field-wrapper scd-field-required">
                                <div class="scd-input-group scd-input-with-prefix">
                                    <span class="scd-input-prefix">%</span>
                                    <input type="number" 
                                           id="discount_value_percentage" 
                                           name="discount_value_percentage"
                                           value="<?php echo esc_attr($discount_value_percentage); ?>" 
                                           min="1" 
                                           max="100" 
                                           step="1" 
                                           class="scd-enhanced-input scd-field scd-discount-value-field"
                                           placeholder="e.g. 15"
                                           required
                                           data-required="true"
                                           data-label="Discount Value"
                                           data-discount-type="percentage"
                                           aria-required="true"
                                           aria-invalid="false">
                                </div>
                                <div class="scd-inline-preview" id="percentage-preview">
                                    <span class="preview-text"></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Fixed Amount Configuration -->
                    <tr class="scd-strategy-options scd-strategy-fixed <?php echo esc_attr( $discount_type === 'fixed' ? 'active' : '' ); ?>" data-strategy-type="fixed">
                        <th scope="row">
                            <label for="discount_value_fixed">
                                <?php esc_html_e('Discount Amount', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Enter the fixed amount to subtract from prices', 'smart-cycle-discounts'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                        </th>
                        <td>
                            <div class="scd-field-wrapper scd-field-required">
                                <div class="scd-input-group">
                                    <span class="scd-input-prefix"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span>
                                    <input type="number"
                                           id="discount_value_fixed"
                                           name="discount_value_fixed"
                                           value="<?php echo esc_attr( $discount_value_fixed ); ?>"
                                           min="<?php echo esc_attr( SCD_Validation_Rules::FIXED_MIN ); ?>"
                                           max="<?php echo esc_attr( SCD_Validation_Rules::FIXED_MAX ); ?>"
                                           step="0.01"
                                           class="scd-enhanced-input scd-field scd-discount-value-field"
                                           placeholder="e.g. 5.00"
                                           required
                                           data-required="true"
                                           data-label="Discount Value"
                                           data-discount-type="fixed"
                                           aria-required="true"
                                           aria-invalid="false">
                                </div>
                                <div class="scd-inline-preview" id="fixed-preview">
                                    <span class="preview-text"></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Tiered Discount Configuration -->
                    <tr class="scd-strategy-options scd-strategy-tiered <?php echo esc_attr( ( $step_data['discount_type'] ?? '' ) === 'tiered' ? 'active' : '' ); ?>" data-strategy-type="tiered">
                        <th scope="row">
                            <label>
                                <?php esc_html_e('Volume Tiers', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Create quantity-based discount levels. Customers buying more get bigger discounts.', 'smart-cycle-discounts'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                        </th>
                        <td>
                            <div class="scd-tiered-discounts">
                                <!-- Apply Discount To Selection -->
                                <div class="scd-field-group scd-tier-section" style="margin-bottom: 20px;">
                                    <label class="scd-tier-section-label">
                                        <?php esc_html_e('Apply Discount To', 'smart-cycle-discounts'); ?>
                                        <span class="required">*</span>
                                        <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Choose how the discount applies: per-item (volume pricing) or order total (promotional)', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </span>
                                    </label>
                                    <div class="scd-tier-mode-selector">
                                        <label class="scd-tier-mode-option">
                                            <input type="radio" name="apply_to" value="per_item" <?php checked(($step_data['apply_to'] ?? 'per_item'), 'per_item'); ?>>
                                            <span class="scd-tier-mode-card">
                                                <span class="dashicons dashicons-cart"></span>
                                                <strong><?php esc_html_e('Each Item', 'smart-cycle-discounts'); ?></strong>
                                                <small class="scd-tier-mode-description">
                                                    <?php esc_html_e('Unit price decreases (volume/bulk pricing)', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                        <label class="scd-tier-mode-option">
                                            <input type="radio" name="apply_to" value="order_total" <?php checked(($step_data['apply_to'] ?? ''), 'order_total'); ?>>
                                            <span class="scd-tier-mode-card">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <strong><?php esc_html_e('Order Total', 'smart-cycle-discounts'); ?></strong>
                                                <small class="scd-tier-mode-description">
                                                    <?php esc_html_e('Fixed discount on order (promotional)', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Tier Mode Selection -->
                                <div class="scd-field-group scd-tier-section" style="margin-bottom: 20px;">
                                    <label class="scd-tier-section-label">
                                        <?php esc_html_e('Discount Type', 'smart-cycle-discounts'); ?>
                                        <span class="required">*</span>
                                        <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Choose between percentage or fixed amount discounts', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </span>
                                    </label>
                                    <div class="scd-tier-mode-selector">
                                        <label class="scd-tier-mode-option">
                                            <input type="radio" name="tier_mode" value="percentage" <?php checked(($step_data['tier_mode'] ?? 'percentage'), 'percentage'); ?>>
                                            <span class="scd-tier-mode-card">
                                                <span class="dashicons dashicons-tag"></span>
                                                <strong><?php esc_html_e('Percentage', 'smart-cycle-discounts'); ?></strong>
                                                <small class="scd-tier-mode-description">
                                                    <?php esc_html_e('e.g., 10% off, 20% off', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                        <label class="scd-tier-mode-option">
                                            <input type="radio" name="tier_mode" value="fixed" <?php checked(($step_data['tier_mode'] ?? ''), 'fixed'); ?>>
                                            <span class="scd-tier-mode-card">
                                                <span class="dashicons dashicons-money-alt"></span>
                                                <strong><?php esc_html_e('Fixed Amount', 'smart-cycle-discounts'); ?></strong>
                                                <small class="scd-tier-mode-description">
                                                    <?php esc_html_e('e.g., $5 off, $10 off', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Percentage Tiers -->
                                <div class="scd-tier-group" id="percentage-tiers-group">
                                    <div class="scd-tiers-list" id="percentage-tiers-list">
                                        <!-- Percentage tiers will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary scd-add-tier" data-tier-type="percentage">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php esc_html_e('Add Percentage Tier', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>
                                
                                <!-- Fixed Amount Tiers -->
                                <div class="scd-tier-group" id="fixed-tiers-group" style="display: none;">
                                    <div class="scd-tiers-list" id="fixed-tiers-list">
                                        <!-- Fixed amount tiers will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary scd-add-tier" data-tier-type="fixed">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php esc_html_e('Add Fixed Amount Tier', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="scd-inline-preview" id="tiered-preview">
                                <span class="preview-text"></span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- BOGO Configuration -->
                    <tr class="scd-strategy-options scd-strategy-bogo <?php echo esc_attr( ( $step_data['discount_type'] ?? '' ) === 'bogo' ? 'active' : '' ); ?>" data-strategy-type="bogo">
                        <th scope="row">
                            <label>
                                <?php esc_html_e('BOGO Configuration', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Set to 100% for free items, or any percentage for partial discounts.', 'smart-cycle-discounts'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                        </th>
                        <td>
                            <div class="scd-bogo-config">
                                <div class="scd-bogo-row">
                                    <div class="scd-bogo-field">
                                        <label for="bogo_buy_quantity"><?php esc_html_e('Customer Buys', 'smart-cycle-discounts'); ?></label>
                                        <input type="number" 
                                               id="bogo_buy_quantity" 
                                               name="bogo_buy_quantity" 
                                               value="<?php echo esc_attr( $step_data['bogo_buy_quantity'] ?? 2 ); ?>" 
                                               min="1" 
                                               placeholder="e.g. 2"
                                               class="scd-enhanced-input">
                                    </div>
                                    <div class="scd-bogo-field">
                                        <label for="bogo_get_quantity"><?php esc_html_e('Customer Gets', 'smart-cycle-discounts'); ?></label>
                                        <input type="number" 
                                               id="bogo_get_quantity" 
                                               name="bogo_get_quantity" 
                                               value="<?php echo esc_attr( $step_data['bogo_get_quantity'] ?? 1 ); ?>" 
                                               min="1" 
                                               placeholder="e.g. 1"
                                               class="scd-enhanced-input">
                                    </div>
                                    <div class="scd-bogo-field">
                                        <label for="bogo_discount"><?php esc_html_e('At Discount', 'smart-cycle-discounts'); ?></label>
                                        <div class="scd-input-group scd-input-with-prefix">
                                            <span class="scd-input-prefix">%</span>
                                            <input type="number" 
                                                   id="bogo_discount" 
                                                   name="bogo_discount" 
                                                   value="<?php echo esc_attr( $step_data['bogo_discount'] ?? 100 ); ?>" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.01" 
                                                   placeholder="100"
                                                   class="scd-enhanced-input">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="scd-inline-preview" id="bogo-preview">
                                <span class="preview-text"></span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Spend Threshold Configuration -->
                    <tr class="scd-strategy-options scd-strategy-spend_threshold <?php echo esc_attr( ( $step_data['discount_type'] ?? '' ) === 'spend_threshold' ? 'active' : '' ); ?>" data-strategy-type="spend_threshold">
                        <th scope="row">
                            <label>
                                <?php esc_html_e('Spending Tiers', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Create spending-based discount levels. Customers spending more get bigger discounts.', 'smart-cycle-discounts'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                        </th>
                        <td>
                            <div class="scd-spend-thresholds">
                                <!-- Threshold Mode Selection -->
                                <div class="scd-threshold-mode-selector">
                                    <label class="scd-threshold-mode-option">
                                        <input type="radio" name="threshold_mode" value="percentage" <?php checked( ( $step_data['threshold_mode'] ?? 'percentage' ), 'percentage' ); ?>>
                                        <span class="scd-threshold-mode-card">
                                            <span class="dashicons dashicons-tag"></span>
                                            <strong><?php esc_html_e( 'Percentage Discounts', 'smart-cycle-discounts' ); ?></strong>
                                            <small class="scd-mode-description">
                                                <?php esc_html_e( 'Scales with order value.', 'smart-cycle-discounts' ); ?>
                                            </small>
                                        </span>
                                    </label>
                                    <label class="scd-threshold-mode-option">
                                        <input type="radio" name="threshold_mode" value="fixed" <?php checked( ( $step_data['threshold_mode'] ?? '' ), 'fixed' ); ?>>
                                        <span class="scd-threshold-mode-card">
                                            <span class="dashicons dashicons-money-alt"></span>
                                            <strong><?php esc_html_e( 'Fixed Amount Off', 'smart-cycle-discounts' ); ?></strong>
                                            <small class="scd-mode-description">
                                                <?php esc_html_e( 'Best for high-value or fixed shipping.', 'smart-cycle-discounts' ); ?>
                                            </small>
                                        </span>
                                    </label>
                                </div>
                                
                                <!-- Percentage Thresholds -->
                                <div class="scd-threshold-group" id="percentage-thresholds-group">
                                    <div class="scd-thresholds-list" id="percentage-thresholds-list" data-empty-message="<?php esc_attr_e('No percentage thresholds added yet', 'smart-cycle-discounts'); ?>">
                                        <!-- Percentage thresholds will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary scd-add-threshold" data-threshold-type="percentage">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php esc_html_e('Add Percentage Threshold', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>
                                
                                <!-- Fixed Amount Thresholds -->
                                <div class="scd-threshold-group" id="fixed-thresholds-group" style="display: none;">
                                    <div class="scd-thresholds-list" id="fixed-thresholds-list" data-empty-message="<?php esc_attr_e('No fixed amount thresholds added yet', 'smart-cycle-discounts'); ?>">
                                        <!-- Fixed amount thresholds will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary scd-add-threshold" data-threshold-type="fixed">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php esc_html_e('Add Fixed Amount Threshold', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="scd-inline-preview" id="spend-threshold-preview">
                                <span class="preview-text"></span>
                            </div>
                        </td>
                    </tr>
                        </table>
                    </div><!-- .scd-pro-background -->
                </div><!-- .scd-pro-container -->
        <?php
        $discount_value_content = ob_get_clean();
        
        scd_wizard_card(array(
            'title' => __('Configure Discount Details', 'smart-cycle-discounts'),
            'subtitle' => __('Set specific values and conditions for your selected discount type.', 'smart-cycle-discounts'),
            'icon' => 'admin-settings',
            'content' => $discount_value_content,
            'id' => 'discount-value-card'
        ));
        ?>

        <!-- Step 3: Configure Discount Rules -->
        <?php
        ob_start();
        ?>
                <!-- Usage Limits Section -->
                <div class="scd-discount-rules-section scd-collapsible" data-section="usage-limits">
                    <h4 class="scd-rules-section-title scd-collapsible-trigger">
                        <span class="scd-section-text">
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php esc_html_e('Usage Limits', 'smart-cycle-discounts'); ?>
                        </span>
                        <span class="scd-collapse-icon">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </span>
                    </h4>
                    
                    <div class="scd-collapsible-content">
                        <table class="form-table scd-rules-table">
                            <tr>
                                <th scope="row">
                                    <label for="usage_limit_per_customer">
                                        <span class="scd-label-icon" title="<?php esc_attr_e('Per customer usage limit', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-admin-users"></span>
                                        </span>
                                        <?php esc_html_e('Per Customer', 'smart-cycle-discounts'); ?>
                                        <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Set how many times each individual customer can redeem this discount during each rotation cycle. Leave empty for unlimited uses.', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </span>
                                    </label>
                                </th>
                                <td>
                                    <div class="scd-input-wrapper">
                                        <input type="number" 
                                               id="usage_limit_per_customer" 
                                               name="usage_limit_per_customer" 
                                               value="<?php echo esc_attr( $usage_limit_per_customer ); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="scd-enhanced-input"
                                               placeholder="∞">
                                        <span class="scd-field-suffix"><?php esc_html_e('uses per cycle', 'smart-cycle-discounts'); ?></span>
                                    </div>
                                </td>
                            </tr>
                        
                            <tr>
                                <th scope="row">
                                    <label for="total_usage_limit">
                                        <span class="scd-label-icon" title="<?php esc_attr_e('Total usage limit', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-chart-pie"></span>
                                        </span>
                                        <?php esc_html_e('Total Uses', 'smart-cycle-discounts'); ?>
                                        <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Total times this discount can be used by all customers combined per cycle. Great for flash sales or limited inventory.', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </span>
                                    </label>
                                </th>
                                <td>
                                    <div class="scd-input-wrapper">
                                        <input type="number" 
                                               id="total_usage_limit" 
                                               name="total_usage_limit" 
                                               value="<?php echo esc_attr( $step_data['total_usage_limit'] ?? '' ); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="scd-enhanced-input"
                                               placeholder="∞">
                                        <span class="scd-field-suffix"><?php esc_html_e('redemptions per cycle', 'smart-cycle-discounts'); ?></span>
                                    </div>
                                </td>
                            </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="lifetime_usage_cap">
                                    <?php esc_html_e('Lifetime Usage Cap', 'smart-cycle-discounts'); ?>
                                    <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Total uses allowed across all campaign cycles. Ends campaign when reached.', 'smart-cycle-discounts'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="lifetime_usage_cap" 
                                       name="lifetime_usage_cap" 
                                       value="<?php echo esc_attr( $step_data['lifetime_usage_cap'] ?? '' ); ?>" 
                                       min="0" 
                                       step="1" 
                                       class="scd-enhanced-input"
                                       placeholder="<?php esc_attr_e('Unlimited', 'smart-cycle-discounts'); ?>">
                                <span class="scd-field-suffix"><?php esc_html_e('uses across all cycles', 'smart-cycle-discounts'); ?></span>
                            </td>
                        </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Application Rules Section -->
                <div class="scd-discount-rules-section scd-collapsible" data-section="application-rules">
                    <h4 class="scd-rules-section-title scd-collapsible-trigger">
                        <span class="scd-section-text">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e('Application Rules', 'smart-cycle-discounts'); ?>
                        </span>
                        <span class="scd-collapse-icon">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </span>
                    </h4>
                    
                    <div class="scd-collapsible-content">
                        <table class="form-table scd-rules-table">
                            <tr>
                                <th scope="row">
                                    <label for="apply_to">
                                        <span class="scd-label-icon" title="<?php esc_attr_e('Application method', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                        </span>
                                        <?php esc_html_e('Apply To', 'smart-cycle-discounts'); ?>
                                        <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Example: 10% off each item vs. 10% off total order', 'smart-cycle-discounts'); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </span>
                                    </label>
                                </th>
                                <td>
                                    <div class="scd-select-wrapper">
                                        <select id="apply_to" name="apply_to" class="scd-enhanced-select">
                                            <option value="per_item" <?php selected( $step_data['apply_to'] ?? 'per_item', 'per_item' ); ?>>
                                                <?php esc_html_e('🛒 Each Product Individually', 'smart-cycle-discounts'); ?>
                                            </option>
                                            <option value="cart_total" <?php selected( $step_data['apply_to'] ?? 'per_item', 'cart_total' ); ?>>
                                                <?php esc_html_e('🧾 Cart Subtotal', 'smart-cycle-discounts'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_discount_amount">
                                    <?php esc_html_e('MAX Discount Amount', 'smart-cycle-discounts'); ?>
                                    <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Cap the maximum discount regardless of percentage. Protects margins on expensive items.', 'smart-cycle-discounts'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                            </th>
                            <td>
                                <div class="scd-input-group">
                                    <span class="scd-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="number" 
                                           id="max_discount_amount" 
                                           name="max_discount_amount" 
                                           value="<?php echo esc_attr( $step_data['max_discount_amount'] ?? '' ); ?>" 
                                           min="0" 
                                           step="0.01" 
                                           class="scd-enhanced-input"
                                           placeholder="<?php esc_attr_e('No limit', 'smart-cycle-discounts'); ?>">
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="minimum_quantity">
                                    <?php esc_html_e('Minimum Quantity', 'smart-cycle-discounts'); ?>
                                    <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Requires this many items from the selected products to activate discount.', 'smart-cycle-discounts'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="minimum_quantity" 
                                       name="minimum_quantity" 
                                       value="<?php echo esc_attr( $step_data['minimum_quantity'] ?? '' ); ?>" 
                                       min="0" 
                                       step="1" 
                                       class="scd-enhanced-input"
                                       placeholder="<?php esc_attr_e('No minimum', 'smart-cycle-discounts'); ?>">
                                <span class="scd-field-suffix"><?php esc_html_e('items', 'smart-cycle-discounts'); ?></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="minimum_order_amount">
                                    <?php esc_html_e('Minimum Order Amount', 'smart-cycle-discounts'); ?>
                                    <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Cart subtotal must meet this amount for discount to apply.', 'smart-cycle-discounts'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                            </th>
                            <td>
                                <div class="scd-input-group">
                                    <span class="scd-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="number" 
                                           id="minimum_order_amount" 
                                           name="minimum_order_amount" 
                                           value="<?php echo esc_attr( $step_data['minimum_order_amount'] ?? '' ); ?>" 
                                           min="0" 
                                           step="0.01" 
                                           class="scd-enhanced-input"
                                           placeholder="<?php esc_attr_e('No minimum', 'smart-cycle-discounts'); ?>">
                                </div>
                            </td>
                        </tr>
                    </table>
                </div><!-- .scd-collapsible-content -->
                </div><!-- .scd-discount-rules-section -->
                
                <!-- Combination Policy Section -->
                <div class="scd-discount-rules-section scd-collapsible" data-section="combination-policy">
                    <h4 class="scd-rules-section-title scd-collapsible-trigger">
                        <span class="scd-section-text">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php esc_html_e('Combination Policy', 'smart-cycle-discounts'); ?>
                        </span>
                        <span class="scd-collapse-icon">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </span>
                    </h4>
                    
                    <div class="scd-collapsible-content">
                        <table class="form-table scd-rules-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Campaign Stacking', 'smart-cycle-discounts'); ?>
                                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e('Whether this discount can be used with other active campaigns.', 'smart-cycle-discounts'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <td>
                                <label for="stack_with_others">
                                    <input type="checkbox"
                                           id="stack_with_others"
                                           name="stack_with_others"
                                           value="1"
                                           <?php checked( $step_data['stack_with_others'] ?? false ); ?>>
                                    <?php esc_html_e('Allow this discount to be combined with other active campaigns', 'smart-cycle-discounts'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Manual Coupons', 'smart-cycle-discounts'); ?>
                            </th>
                            <td>
                                <label for="allow_coupons">
                                    <input type="checkbox" 
                                           id="allow_coupons" 
                                           name="allow_coupons" 
                                           value="1"
                                           <?php checked( $allow_coupons ); ?>>
                                    <?php esc_html_e('Allow customers to use coupon codes with this discount', 'smart-cycle-discounts'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Sale Items', 'smart-cycle-discounts'); ?>
                            </th>
                            <td>
                                <label for="apply_to_sale_items">
                                    <input type="checkbox" 
                                           id="apply_to_sale_items" 
                                           name="apply_to_sale_items" 
                                           value="1"
                                           <?php checked( $apply_to_sale_items ); ?>>
                                    <?php esc_html_e('Apply discount to items already on sale', 'smart-cycle-discounts'); ?>
                                </label>
                            </td>
                        </tr>
                        </table>
                    </div><!-- .scd-collapsible-content -->
                </div><!-- .scd-discount-rules-section -->
        <?php
        $discount_rules_content = ob_get_clean();
        
        scd_wizard_card(array(
            'title' => __('Configure Discount Rules', 'smart-cycle-discounts'),
            'subtitle' => __('Fine-tune how your discount works to maximize impact while protecting margins.', 'smart-cycle-discounts'),
            'icon' => 'admin-settings',
            'content' => $discount_rules_content,
            'id' => 'discount-rules-card',
            'class' => empty($step_data['discount_type']) ? 'scd-hidden' : ''
        ));
        ?>

        <!-- Hidden fields for complex discount data - follow field definitions pattern -->
        <div class="scd-hidden-fields" style="display: none;">
            <!-- Tier Mode -->
            <input type="hidden" name="tier_mode" id="tier_mode" 
                   value="<?php echo esc_attr( $step_data['tier_mode'] ?? 'percentage' ); ?>" 
                   class="scd-field" data-type="select">
            
            <!-- Tier Arrays -->
            <input type="hidden" name="percentage_tiers" id="percentage_tiers" 
                   data-value="<?php echo esc_attr( json_encode( $step_data['percentage_tiers'] ?? array() ) ); ?>" 
                   class="scd-field" data-type="array">
            
            <input type="hidden" name="fixed_tiers" id="fixed_tiers" 
                   data-value="<?php echo esc_attr( json_encode( $step_data['fixed_tiers'] ?? array() ) ); ?>" 
                   class="scd-field" data-type="array">
            
            <!-- Threshold Mode -->
            <input type="hidden" name="threshold_mode" id="threshold_mode" 
                   value="<?php echo esc_attr( $step_data['threshold_mode'] ?? 'percentage' ); ?>" 
                   class="scd-field" data-type="select">
            
            <!-- Threshold Arrays -->
            <input type="hidden" name="percentage_spend_thresholds" id="percentage_spend_thresholds" 
                   data-value="<?php echo esc_attr( json_encode( $step_data['percentage_spend_thresholds'] ?? array() ) ); ?>" 
                   class="scd-field" data-type="array">
            
            <input type="hidden" name="fixed_spend_thresholds" id="fixed_spend_thresholds" 
                   data-value="<?php echo esc_attr( json_encode( $step_data['fixed_spend_thresholds'] ?? array() ) ); ?>" 
                   class="scd-field" data-type="array">
            
            <!-- BOGO Configuration as hidden fields -->
            <input type="hidden" name="bogo_config" id="bogo_config" 
                   data-value="<?php echo esc_attr( json_encode( $step_data['bogo_config'] ?? array() ) ); ?>" 
                   class="scd-field" data-type="array">
        </div>

        <!-- Auto-save indicator -->
        <div class="scd-auto-save-indicator" style="display: none;">
            <span class="dashicons dashicons-saved"></span>
            <span class="text"><?php esc_html_e('Auto-saved', 'smart-cycle-discounts'); ?></span>
        </div>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
scd_wizard_render_step( array(
    'title' => __( 'Discount Configuration', 'smart-cycle-discounts' ),
    'description' => __( 'Configure the discount type, value, and rules for your campaign', 'smart-cycle-discounts' ),
    'content' => $content,
    'step' => 'discounts'
) );
?>

<?php
// Validation rules are now handled by the centralized field schema system

// Initialize state data for Discounts step
scd_wizard_state_script('discounts', array(
    'discount_type' => $discount_type,
    'discount_value_percentage' => $discount_value_percentage,
    'discount_value_fixed' => $discount_value_fixed,
    'conditions' => $conditions,
    'tiers' => $step_data['tiers'] ?? array(),
    'discount_config' => $step_data['discount_config'] ?? array(),
    // Usage Limits
    'usage_limit_per_customer' => $usage_limit_per_customer,
    'total_usage_limit' => $total_usage_limit,
    'lifetime_usage_cap' => $lifetime_usage_cap,
    // Application Rules
    'apply_to' => $apply_to,
    'max_discount_amount' => $max_discount_amount,
    'minimum_quantity' => $minimum_quantity,
    'minimum_order_amount' => $minimum_order_amount,
    // Combination Policy
    'stack_with_others' => $stack_with_others,
    'allow_coupons' => $allow_coupons,
    'apply_to_sale_items' => $apply_to_sale_items,
    // Badge Settings
    'badge_enabled' => $badge_enabled,
    'badge_text' => $badge_text,
    'badge_bg_color' => $badge_bg_color,
    'badge_text_color' => $badge_text_color,
    'badge_position' => $badge_position,
    // Threshold and Tier Settings
    'threshold_mode' => $step_data['threshold_mode'] ?? 'percentage',
    'percentage_spend_thresholds' => $step_data['percentage_spend_thresholds'] ?? array(),
    'fixed_spend_thresholds' => $step_data['fixed_spend_thresholds'] ?? array(),
    'tier_mode' => $step_data['tier_mode'] ?? 'percentage',
    'percentage_tiers' => $step_data['percentage_tiers'] ?? array(),
    'fixed_tiers' => $step_data['fixed_tiers'] ?? array(),
    // BOGO Settings
    'bogo_buy_quantity' => $bogo_buy_quantity,
    'bogo_get_quantity' => $bogo_get_quantity,
    'bogo_discount_percentage' => $bogo_discount,
    'bogo_apply_to' => $bogo_apply_to,
    // Tiered Data
    'tiered_data' => $step_data['tiered_data'] ?? array(),
    // Conditions Logic
    'conditions_logic' => $conditions_logic
), array(
    'selected_products' => $scd_discount_step_data['selected_products'] ?? array(),
    'currency_data' => $currency_data
));
?>

<script type="text/javascript">

// Legacy support for existing code
window.scdDiscountStepData = window.scdDiscountStepData || {};
window.scdDiscountStepData.currency_symbol = <?php echo wp_json_encode( $currency_symbol ); ?>;
window.scdDiscountStepData.currency_pos = <?php echo wp_json_encode( $currency_pos ); ?>;
window.scdDiscountStepData.price_decimals = <?php echo absint( $price_decimals ); ?>;
window.scdDiscountStepData.decimal_separator = <?php echo wp_json_encode( $decimal_separator ); ?>;
window.scdDiscountStepData.thousand_separator = <?php echo wp_json_encode( $thousand_separator ); ?>;
<?php if ( ! empty( $scd_discount_step_data['selected_products'] ) ): ?>
window.scdDiscountStepData.selected_products = <?php echo wp_json_encode( $scd_discount_step_data['selected_products'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
<?php endif; ?>

// Fallback JavaScript for discount type card interactions
jQuery(document).ready(function($) {
    'use strict';
    
    function handleDiscountTypeSelection() {
        // Handle discount type card clicks
        $('.scd-discount-type-card').on('click', function() {
            var $card = $(this);
            var discountType = $card.data('type');
            
            if (!discountType) return;
            
            // Update visual state
            $('.scd-discount-type-card').removeClass('selected');
            $card.addClass('selected');
            
            // Update hidden input
            $('#discount_type').val(discountType).trigger('change');
            
            // Show/hide strategy options
            $('.scd-strategy-options').removeClass('active').hide();
            $('.scd-strategy-options[data-strategy-type="' + discountType + '"]').addClass('active').show();
            
            // Show/hide discount rules card
            $('#discount-rules-card').removeClass('scd-hidden');
            
            console.log('[SCD] Discount type changed to:', discountType);
        });
        
        // Handle collapsible sections
        $('.scd-collapsible-trigger').on('click', function(e) {
            e.preventDefault();
            var $section = $(this).closest('.scd-collapsible');
            $section.toggleClass('scd-collapsed');
        });
        
        // Set initial state
        var currentType = $('#discount_type').val();
        if (currentType) {
            $('.scd-discount-type-card[data-type="' + currentType + '"]').addClass('selected');
            $('.scd-strategy-options[data-strategy-type="' + currentType + '"]').addClass('active').show();
            $('#discount-rules-card').removeClass('scd-hidden');
        }
    }
    
    // Initialize immediately and also after a delay for safety
    handleDiscountTypeSelection();
    setTimeout(handleDiscountTypeSelection, 100);
});

</script>


<?php
// Step complete
