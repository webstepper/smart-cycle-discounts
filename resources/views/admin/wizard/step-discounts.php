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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Initialize variables using shared function
wsscd_wizard_init_step_vars($step_data, $validation_errors);

// Field schema handles default values now - no need to set them here

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
$badge_enabled = $step_data['badge_enabled'] ?? true;
$badge_text = $step_data['badge_text'] ?? 'auto';
$badge_bg_color = $step_data['badge_bg_color'] ?? '#ff0000';
$badge_text_color = $step_data['badge_text_color'] ?? '#ffffff';
$badge_position = $step_data['badge_position'] ?? 'top-right';

// Extract BOGO config from grouped structure
$bogo_config = $step_data['bogo_config'] ?? array();
$bogo_buy_quantity = $bogo_config['buy_quantity'] ?? 1;
$bogo_get_quantity = $bogo_config['get_quantity'] ?? 1;
$bogo_discount_percentage = $bogo_config['discount_percent'] ?? 100;
$bogo_apply_to = $step_data['bogo_apply_to'] ?? 'cheapest';

// Get WooCommerce currency settings
$currency_symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
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
if ( class_exists( 'WSSCD_Core_Container' ) ) {
    try {
        $container = WSSCD_Core_Container::get_instance();
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
global $wsscd_discount_step_data;
$wsscd_discount_step_data = array_merge($currency_data, array(
    'selected_products' => $products_for_display
));

// Helper function to format price with currency
if ( ! function_exists( 'wsscd_format_price' ) ) {
    function wsscd_format_price($amount) {
        return wc_price($amount);
    }
}

// Helper function to format example price
if ( ! function_exists( 'wsscd_format_example_price' ) ) {
    function wsscd_format_example_price($amount) {
        return wp_strip_all_tags(wc_price($amount));
    }
}


// Prepare content for template wrapper
ob_start();
?>
        <!-- Step 1: Discount Type Selection -->
        <?php
        ob_start();
        ?>
                <div class="wsscd-field-wrapper wsscd-field-required">
                    <input type="hidden"
                           id="discount_type"
                           name="discount_type"
                           value="<?php echo esc_attr($discount_type); ?>"
                           class="wsscd-field"
                           data-required="true"
                           data-label="Discount Type"
                           data-pattern-message="Please select a discount type"
                           data-help-topic="discount-type"
                           aria-required="true">
                </div>
                
                <div class="wsscd-discount-type-grid">
                    <!-- Percentage Discount -->
                    <div class="wsscd-discount-type-card <?php echo esc_attr( $discount_type === 'percentage' ? 'selected' : '' ); ?>"
                         data-type="percentage"
                         data-help-topic="option-discount-percentage">
                        <div class="wsscd-discount-type-card__icon">
                            <?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 24 ) ); ?>
                        </div>
                        <h4 class="wsscd-discount-type-card__title"><?php esc_html_e('Percentage Off', 'smart-cycle-discounts'); ?></h4>
                        <p class="wsscd-discount-type-card__description">
                            <?php esc_html_e('Take a percentage off the original price', 'smart-cycle-discounts'); ?>
                        </p>
                        <div class="wsscd-discount-type-card__example">
                            <?php
                            $example = sprintf(
                                /* translators: %1$s: original price (e.g., "$100"), %2$s: discounted price (e.g., "$80") */
                                __('20%% off %1$s = %2$s', 'smart-cycle-discounts'),
                                wsscd_format_example_price(100),
                                wsscd_format_example_price(80)
                            );
                            echo esc_html($example);
                            ?>
                        </div>
                    </div>
                    
                    <!-- Fixed Amount Discount -->
                    <div class="wsscd-discount-type-card <?php echo esc_attr( $discount_type === 'fixed' ? 'selected' : '' ); ?>"
                         data-type="fixed"
                         data-help-topic="option-discount-fixed">
                        <div class="wsscd-discount-type-card__icon">
                            <?php WSSCD_Icon_Helper::render( 'receipt', array( 'size' => 24 ) ); ?>
                        </div>
                        <h4 class="wsscd-discount-type-card__title"><?php esc_html_e('Fixed Amount Off', 'smart-cycle-discounts'); ?></h4>
                        <p class="wsscd-discount-type-card__description">
                            <?php esc_html_e('Subtract a fixed dollar amount', 'smart-cycle-discounts'); ?>
                        </p>
                        <div class="wsscd-discount-type-card__example">
                            <?php
                            $example = sprintf(
                                /* translators: %1$s: discount amount (e.g., "$10"), %2$s: original price (e.g., "$100"), %3$s: discounted price (e.g., "$90") */
                                __('%1$s off %2$s = %3$s', 'smart-cycle-discounts'),
                                wsscd_format_example_price(10),
                                wsscd_format_example_price(100),
                                wsscd_format_example_price(90)
                            );
                            echo esc_html($example);
                            ?>
                        </div>
                    </div>

                    <?php
                    // PRO discount types - dual-block pattern for WordPress.org compliance
                    // Block 1: Promotional UI (always in both ZIPs) - shown to free users
                    // Block 2: Functional UI (only in PRO ZIP) - shown to licensed PRO users
                    $has_pro_access = wsscd_fs()->can_use_premium_code();
                    $upgrade_url = $feature_gate ? $feature_gate->get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
                    ?>

                    <!-- Tiered Discount -->
                    <?php if ( ! $has_pro_access ) : ?>
                    <!-- Promotional locked card (always in both ZIPs) -->
                    <div class="wsscd-discount-type-card wsscd-discount-type-card--locked"
                         data-type="tiered"
                         data-locked="true"
                         data-help-topic="option-discount-tiered">
                        <div class="wsscd-discount-type-card__locked-content">
                            <?php echo wp_kses_post( WSSCD_Badge_Helper::pro_badge( __( 'PRO Feature', 'smart-cycle-discounts' ) ) ); ?>
                            <h4 class="wsscd-discount-type-card__title">
                                <?php esc_html_e( 'Volume Discounts', 'smart-cycle-discounts' ); ?>
                            </h4>
                            <p class="wsscd-discount-type-card__description">
                                <?php esc_html_e( 'Reward bulk purchases with tiered pricing', 'smart-cycle-discounts' ); ?>
                            </p>
                            <ul class="wsscd-discount-type-card__features">
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Set multiple quantity tiers', 'smart-cycle-discounts' ); ?></li>
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Increase discounts at each tier', 'smart-cycle-discounts' ); ?></li>
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Perfect for wholesale & B2B', 'smart-cycle-discounts' ); ?></li>
                            </ul>
                            <?php
                            WSSCD_Button_Helper::primary(
                                __( 'Upgrade to Pro', 'smart-cycle-discounts' ),
                                array(
                                    'size'    => 'small',
                                    'href'    => esc_url( $upgrade_url ),
                                    'classes' => array( 'wsscd-discount-type-card__upgrade-btn' ),
                                )
                            );
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( wsscd_fs()->is__premium_only() ) : ?>
                    <?php if ( $has_pro_access ) : ?>
                    <!-- Functional card (only in PRO ZIP, shown when licensed) -->
                    <div class="wsscd-discount-type-card <?php echo esc_attr( $discount_type === 'tiered' ? 'selected' : '' ); ?>"
                         data-type="tiered"
                         data-help-topic="option-discount-tiered">
                        <div class="wsscd-discount-type-card__icon">
                            <?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 24 ) ); ?>
                        </div>
                        <h4 class="wsscd-discount-type-card__title">
                            <?php esc_html_e( 'Volume Discounts', 'smart-cycle-discounts' ); ?>
                        </h4>
                        <p class="wsscd-discount-type-card__description">
                            <?php esc_html_e( 'More items = bigger discounts', 'smart-cycle-discounts' ); ?>
                        </p>
                        <div class="wsscd-discount-type-card__example">
                            <?php esc_html_e( 'Buy 2+ save 10%, Buy 5+ save 20%', 'smart-cycle-discounts' ); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- BOGO Discount -->
                    <?php if ( ! $has_pro_access ) : ?>
                    <!-- Promotional locked card (always in both ZIPs) -->
                    <div class="wsscd-discount-type-card wsscd-discount-type-card--locked"
                         data-type="bogo"
                         data-locked="true"
                         data-help-topic="option-discount-bogo">
                        <div class="wsscd-discount-type-card__locked-content">
                            <?php echo wp_kses_post( WSSCD_Badge_Helper::pro_badge( __( 'PRO Feature', 'smart-cycle-discounts' ) ) ); ?>
                            <h4 class="wsscd-discount-type-card__title">
                                <?php esc_html_e( 'BOGO Deals', 'smart-cycle-discounts' ); ?>
                            </h4>
                            <p class="wsscd-discount-type-card__description">
                                <?php esc_html_e( 'Create compelling buy-one-get-one promotions', 'smart-cycle-discounts' ); ?>
                            </p>
                            <ul class="wsscd-discount-type-card__features">
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Flexible BOGO ratios', 'smart-cycle-discounts' ); ?></li>
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Percentage or free gift options', 'smart-cycle-discounts' ); ?></li>
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Drive sales & clear inventory', 'smart-cycle-discounts' ); ?></li>
                            </ul>
                            <?php
                            WSSCD_Button_Helper::primary(
                                __( 'Upgrade to Pro', 'smart-cycle-discounts' ),
                                array(
                                    'size'    => 'small',
                                    'href'    => esc_url( $upgrade_url ),
                                    'classes' => array( 'wsscd-discount-type-card__upgrade-btn' ),
                                )
                            );
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( wsscd_fs()->is__premium_only() ) : ?>
                    <?php if ( $has_pro_access ) : ?>
                    <!-- Functional card (only in PRO ZIP, shown when licensed) -->
                    <div class="wsscd-discount-type-card <?php echo esc_attr( $discount_type === 'bogo' ? 'selected' : '' ); ?>"
                         data-type="bogo"
                         data-help-topic="option-discount-bogo">
                        <div class="wsscd-discount-type-card__icon">
                            <?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 24 ) ); ?>
                        </div>
                        <h4 class="wsscd-discount-type-card__title">
                            <?php esc_html_e( 'BOGO Deals', 'smart-cycle-discounts' ); ?>
                        </h4>
                        <p class="wsscd-discount-type-card__description">
                            <?php esc_html_e( 'Buy one get one offers', 'smart-cycle-discounts' ); ?>
                        </p>
                        <div class="wsscd-discount-type-card__example">
                            <?php esc_html_e( 'Buy 2 Get 1 Free', 'smart-cycle-discounts' ); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- Spend Threshold Discount -->
                    <?php if ( ! $has_pro_access ) : ?>
                    <!-- Promotional locked card (always in both ZIPs) -->
                    <div class="wsscd-discount-type-card wsscd-discount-type-card--locked"
                         data-type="spend_threshold"
                         data-locked="true"
                         data-help-topic="option-discount-spend-threshold">
                        <div class="wsscd-discount-type-card__locked-content">
                            <?php echo wp_kses_post( WSSCD_Badge_Helper::pro_badge( __( 'PRO Feature', 'smart-cycle-discounts' ) ) ); ?>
                            <h4 class="wsscd-discount-type-card__title">
                                <?php esc_html_e( 'Spend Threshold', 'smart-cycle-discounts' ); ?>
                            </h4>
                            <p class="wsscd-discount-type-card__description">
                                <?php esc_html_e( 'Reward customers who spend more', 'smart-cycle-discounts' ); ?>
                            </p>
                            <ul class="wsscd-discount-type-card__features">
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Minimum purchase triggers', 'smart-cycle-discounts' ); ?></li>
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Spend $X get Y% off deals', 'smart-cycle-discounts' ); ?></li>
                                <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Increase average order value', 'smart-cycle-discounts' ); ?></li>
                            </ul>
                            <?php
                            WSSCD_Button_Helper::primary(
                                __( 'Upgrade to Pro', 'smart-cycle-discounts' ),
                                array(
                                    'size'    => 'small',
                                    'href'    => esc_url( $upgrade_url ),
                                    'classes' => array( 'wsscd-discount-type-card__upgrade-btn' ),
                                )
                            );
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( wsscd_fs()->is__premium_only() ) : ?>
                    <?php if ( $has_pro_access ) : ?>
                    <!-- Functional card (only in PRO ZIP, shown when licensed) -->
                    <div class="wsscd-discount-type-card <?php echo esc_attr( $discount_type === 'spend_threshold' ? 'selected' : '' ); ?>"
                         data-type="spend_threshold"
                         data-help-topic="option-discount-spend-threshold">
                        <div class="wsscd-discount-type-card__icon">
                            <?php WSSCD_Icon_Helper::render( 'money', array( 'size' => 24 ) ); ?>
                        </div>
                        <h4 class="wsscd-discount-type-card__title">
                            <?php esc_html_e( 'Spend Threshold', 'smart-cycle-discounts' ); ?>
                        </h4>
                        <p class="wsscd-discount-type-card__description">
                            <?php esc_html_e( 'Reward customers who spend more', 'smart-cycle-discounts' ); ?>
                        </p>
                        <div class="wsscd-discount-type-card__example">
                            <?php esc_html_e( 'Spend $100 get 10% off', 'smart-cycle-discounts' ); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($validation_errors['discount_type'])): ?>
                    <div class="wsscd-field-error">
                        <?php foreach ((array)$validation_errors['discount_type'] as $error): ?>
                            <p class="error-message"><?php echo esc_html($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
        <?php
        $type_selection_content = ob_get_clean();
        
        wsscd_wizard_card(array(
            'title' => __('Choose Your Discount Type', 'smart-cycle-discounts'),
            'subtitle' => __('Select the discount strategy that best fits your campaign goals.', 'smart-cycle-discounts'),
            'icon' => 'tag',
            'content' => $type_selection_content,
            'help_topic' => 'card-discount-type'
        ));
        ?>

        <!-- Step 2: Configure Discount Value -->
        <?php
        ob_start();

        // Determine if the details container should be locked
        $pro_discount_types = array( 'tiered', 'bogo', 'spend_threshold' );
        $is_pro_type_selected = in_array( $discount_type, $pro_discount_types, true );
        $should_lock_container = $is_pro_type_selected && ! $has_pro_access;
        ?>

                <div class="wsscd-pro-container <?php echo esc_attr( $should_lock_container ? 'wsscd-pro-container--locked' : '' ); ?>" id="wsscd-discount-details-container"<?php echo $should_lock_container ? ' data-active-type="' . esc_attr( $discount_type ) . '"' : ''; ?>>
                    <?php
                    // PRO discount type overlays (shown when locked)
                    // Note: The wrapper div uses data-discount-type attribute for CSS targeting
                    // The included template adds wsscd-pro-feature-unavailable class
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
                        include WSSCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
                        ?>
                    </div>

                    <div data-discount-type="bogo">
                        <?php
                        $description = __( 'Buy One Get One deal configurations', 'smart-cycle-discounts' );
                        $features = array(
                            __( 'BOGO and BOGOF deals', 'smart-cycle-discounts' ),
                            __( 'Buy X Get Y promotions', 'smart-cycle-discounts' ),
                            __( 'Mix and match product combinations', 'smart-cycle-discounts' ),
                            __( 'Flexible quantity ratios', 'smart-cycle-discounts' ),
                        );
                        include WSSCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
                        ?>
                    </div>

                    <div data-discount-type="spend_threshold">
                        <?php
                        $description = __( 'Cart total-based discount rewards', 'smart-cycle-discounts' );
                        $features = array(
                            __( 'Minimum purchase amount triggers', 'smart-cycle-discounts' ),
                            __( 'Spend $X get Y% off deals', 'smart-cycle-discounts' ),
                            __( 'Progressive spending rewards', 'smart-cycle-discounts' ),
                            __( 'Cart value-based incentives', 'smart-cycle-discounts' ),
                        );
                        include WSSCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
                        ?>
                    </div>

                    <!-- Actual discount configuration (blurred for PRO types when locked) -->
                    <div class="wsscd-pro-background">
                        <table class="form-table">
                    <!-- Percentage Discount Configuration -->
                    <tr class="wsscd-strategy-options wsscd-strategy-percentage <?php echo esc_attr( $discount_type === 'percentage' ? 'active' : '' ); ?>" data-strategy-type="percentage">
                        <th scope="row">
                            <label for="discount_value_percentage">
                                <?php esc_html_e('Discount Percentage', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <?php WSSCD_Tooltip_Helper::render( __('Enter a value between 0.01 and 100', 'smart-cycle-discounts') ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wsscd-field-wrapper wsscd-field-required">
                                <div class="wsscd-input-wrapper wsscd-input-with-prefix">
                                    <span class="wsscd-input-prefix">%</span>
                                    <input type="number"
                                           id="discount_value_percentage"
                                           name="discount_value_percentage"
                                           value="<?php echo esc_attr($discount_value_percentage); ?>"
                                           min="1"
                                           max="100"
                                           step="1"
                                           inputmode="numeric"
                                           class="wsscd-enhanced-input wsscd-field wsscd-discount-value-field"
                                           placeholder="1-100"
                                           required
                                           data-required="true"
                                           data-label="Discount Percentage"
                                           data-discount-type="percentage"
                                           data-input-type="percentage"
                                           data-help-topic="discount-value"
                                           aria-required="true"
                                           aria-invalid="false">
                                </div>
                                <div class="wsscd-inline-preview" id="percentage-preview">
                                    <span class="preview-text"></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Fixed Amount Configuration -->
                    <tr class="wsscd-strategy-options wsscd-strategy-fixed <?php echo esc_attr( $discount_type === 'fixed' ? 'active' : '' ); ?>" data-strategy-type="fixed">
                        <th scope="row">
                            <label for="discount_value_fixed">
                                <?php esc_html_e('Discount Amount', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <?php WSSCD_Tooltip_Helper::render( __('Enter the fixed amount to subtract from prices', 'smart-cycle-discounts') ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wsscd-field-wrapper wsscd-field-required">
                                <div class="wsscd-input-wrapper wsscd-input-with-prefix">
                                    <span class="wsscd-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                                    <input type="number"
                                           id="discount_value_fixed"
                                           name="discount_value_fixed"
                                           value="<?php echo esc_attr( $discount_value_fixed ); ?>"
                                           min="<?php echo esc_attr( WSSCD_Validation_Rules::FIXED_MIN ); ?>"
                                           max="<?php echo esc_attr( WSSCD_Validation_Rules::FIXED_MAX ); ?>"
                                           step="0.01"
                                           inputmode="decimal"
                                           class="wsscd-enhanced-input wsscd-field wsscd-discount-value-field"
                                           placeholder="e.g. 5.00"
                                           required
                                           data-required="true"
                                           data-label="Discount Amount"
                                           data-discount-type="fixed"
                                           data-input-type="decimal"
                                           data-help-topic="discount-value"
                                           aria-required="true"
                                           aria-invalid="false">
                                </div>
                                <div class="wsscd-inline-preview" id="fixed-preview">
                                    <span class="preview-text"></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Tiered Discount Configuration -->
                    <tr class="wsscd-strategy-options wsscd-strategy-tiered <?php echo esc_attr( ( $step_data['discount_type'] ?? '' ) === 'tiered' ? 'active' : '' ); ?>" data-strategy-type="tiered">
                        <th scope="row">
                            <label>
                                <?php esc_html_e('Volume Tiers', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <?php WSSCD_Tooltip_Helper::render( __('Create quantity-based discount levels. Customers buying more get bigger discounts.', 'smart-cycle-discounts') ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wsscd-tiered-discounts">
                                <!-- Apply Discount To Selection -->
                                <div class="wsscd-tier-section">
                                    <label class="wsscd-tier-section-label">
                                        <?php esc_html_e('Apply Discount To', 'smart-cycle-discounts'); ?>
                                        <span class="required">*</span>
                                        <?php WSSCD_Tooltip_Helper::render( __('Choose how the discount applies: per-item (volume pricing) or order total (promotional)', 'smart-cycle-discounts') ); ?>
                                    </label>
                                    <div class="wsscd-tier-mode-selector">
                                        <label class="wsscd-tier-mode-option">
                                            <input type="radio" name="apply_to" value="per_item" <?php checked(($step_data['apply_to'] ?? 'per_item'), 'per_item'); ?>>
                                            <span class="wsscd-tier-mode-card">
                                                <?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 20 ) ); ?>
                                                <strong><?php esc_html_e('Each Item', 'smart-cycle-discounts'); ?></strong>
                                                <small class="wsscd-tier-mode-description">
                                                    <?php esc_html_e('Unit price decreases (volume/bulk pricing)', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                        <label class="wsscd-tier-mode-option">
                                            <input type="radio" name="apply_to" value="order_total" <?php checked(($step_data['apply_to'] ?? ''), 'order_total'); ?>>
                                            <span class="wsscd-tier-mode-card">
                                                <?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 20 ) ); ?>
                                                <strong><?php esc_html_e('Order Total', 'smart-cycle-discounts'); ?></strong>
                                                <small class="wsscd-tier-mode-description">
                                                    <?php esc_html_e('Fixed discount on order (promotional)', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Tier Mode Selection -->
                                <div class="wsscd-tier-section">
                                    <label class="wsscd-tier-section-label">
                                        <?php esc_html_e('Discount Type', 'smart-cycle-discounts'); ?>
                                        <span class="required">*</span>
                                        <?php WSSCD_Tooltip_Helper::render( __('Choose between percentage or fixed amount discounts', 'smart-cycle-discounts') ); ?>
                                    </label>
                                    <div class="wsscd-tier-mode-selector">
                                        <label class="wsscd-tier-mode-option">
                                            <input type="radio" name="tier_mode" value="percentage" <?php checked(($step_data['tier_mode'] ?? 'percentage'), 'percentage'); ?>>
                                            <span class="wsscd-tier-mode-card">
                                                <?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 20 ) ); ?>
                                                <strong><?php esc_html_e('Percentage', 'smart-cycle-discounts'); ?></strong>
                                                <small class="wsscd-tier-mode-description">
                                                    <?php esc_html_e('e.g., 10% off, 20% off', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                        <label class="wsscd-tier-mode-option">
                                            <input type="radio" name="tier_mode" value="fixed" <?php checked(($step_data['tier_mode'] ?? ''), 'fixed'); ?>>
                                            <span class="wsscd-tier-mode-card">
                                                <?php WSSCD_Icon_Helper::render( 'receipt', array( 'size' => 20 ) ); ?>
                                                <strong><?php esc_html_e('Fixed Amount', 'smart-cycle-discounts'); ?></strong>
                                                <small class="wsscd-tier-mode-description">
                                                    <?php esc_html_e('e.g., $5 off, $10 off', 'smart-cycle-discounts'); ?>
                                                </small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Percentage Tiers -->
                                <div class="wsscd-tier-group" id="percentage-tiers-group">
                                    <div class="wsscd-tiers-list" id="percentage-tiers-list">
                                        <!-- Percentage tiers will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary wsscd-add-tier" data-tier-type="percentage">
                                        <?php WSSCD_Icon_Helper::render( 'add', array( 'size' => 16 ) ); ?>
                                        <?php esc_html_e('Add Percentage Tier', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>
                                
                                <!-- Fixed Amount Tiers -->
                                <div class="wsscd-tier-group wsscd-hidden" id="fixed-tiers-group">
                                    <div class="wsscd-tiers-list" id="fixed-tiers-list">
                                        <!-- Fixed amount tiers will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary wsscd-add-tier" data-tier-type="fixed">
                                        <?php WSSCD_Icon_Helper::render( 'add', array( 'size' => 16 ) ); ?>
                                        <?php esc_html_e('Add Fixed Amount Tier', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>

                                <!-- Hidden input for validation -->
                                <input type="hidden" name="tiers" id="tiers" value="">
                            </div>
                            <div class="wsscd-inline-preview" id="tiered-preview">
                                <span class="preview-text"></span>
                            </div>
                        </td>
                    </tr>

                    <!-- BOGO Configuration -->
                    <tr class="wsscd-strategy-options wsscd-strategy-bogo <?php echo esc_attr( ( $step_data['discount_type'] ?? '' ) === 'bogo' ? 'active' : '' ); ?>" data-strategy-type="bogo">
                        <th scope="row">
                            <label>
                                <?php esc_html_e('BOGO Configuration', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <?php WSSCD_Tooltip_Helper::render( __('Set to 100% for free items, or any percentage for partial discounts.', 'smart-cycle-discounts') ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wsscd-bogo-config">
                                <div class="wsscd-bogo-row">
                                    <div class="wsscd-bogo-field">
                                        <label for="bogo_buy_quantity"><?php esc_html_e('Customer Buys', 'smart-cycle-discounts'); ?></label>
                                        <input type="number"
                                               id="bogo_buy_quantity"
                                               name="bogo_buy_quantity"
                                               value="<?php echo esc_attr( $bogo_buy_quantity ); ?>"
                                               min="1"
                                               max="1000"
                                               step="1"
                                               inputmode="numeric"
                                               placeholder="e.g. 2"
                                               data-label="Buy Quantity"
                                               data-input-type="integer"
                                               class="wsscd-enhanced-input">
                                    </div>
                                    <div class="wsscd-bogo-field">
                                        <label for="bogo_get_quantity"><?php esc_html_e('Customer Gets', 'smart-cycle-discounts'); ?></label>
                                        <input type="number"
                                               id="bogo_get_quantity"
                                               name="bogo_get_quantity"
                                               value="<?php echo esc_attr( $bogo_get_quantity ); ?>"
                                               min="1"
                                               max="1000"
                                               step="1"
                                               inputmode="numeric"
                                               placeholder="e.g. 1"
                                               data-label="Get Quantity"
                                               data-input-type="integer"
                                               class="wsscd-enhanced-input">
                                    </div>
                                    <div class="wsscd-bogo-field">
                                        <label for="bogo_discount_percentage"><?php esc_html_e('At Discount', 'smart-cycle-discounts'); ?></label>
                                        <div class="wsscd-input-wrapper wsscd-input-with-prefix">
                                            <span class="wsscd-input-prefix">%</span>
                                            <input type="number"
                                                   id="bogo_discount_percentage"
                                                   name="bogo_discount_percentage"
                                                   value="<?php echo esc_attr( $bogo_discount_percentage ); ?>"
                                                   min="0"
                                                   max="100"
                                                   step="0.01"
                                                   inputmode="decimal"
                                                   placeholder="0-100"
                                                   data-label="Discount Percentage"
                                                   data-input-type="percentage"
                                                   class="wsscd-enhanced-input">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="wsscd-inline-preview" id="bogo-preview">
                                <span class="preview-text"></span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Spend Threshold Configuration -->
                    <tr class="wsscd-strategy-options wsscd-strategy-spend_threshold <?php echo esc_attr( ( $step_data['discount_type'] ?? '' ) === 'spend_threshold' ? 'active' : '' ); ?>" data-strategy-type="spend_threshold">
                        <th scope="row">
                            <label>
                                <?php esc_html_e('Spending Tiers', 'smart-cycle-discounts'); ?>
                                <span class="required">*</span>
                                <?php WSSCD_Tooltip_Helper::render( __('Create spending-based discount levels. Customers spending more get bigger discounts.', 'smart-cycle-discounts') ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wsscd-spend-thresholds">
                                <!-- Threshold Mode Selection -->
                                <div class="wsscd-threshold-mode-selector">
                                    <label class="wsscd-threshold-mode-option">
                                        <input type="radio" name="threshold_mode" value="percentage" <?php checked( ( $step_data['threshold_mode'] ?? 'percentage' ), 'percentage' ); ?>>
                                        <span class="wsscd-threshold-mode-card">
                                            <?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 20 ) ); ?>
                                            <strong><?php esc_html_e( 'Percentage Discounts', 'smart-cycle-discounts' ); ?></strong>
                                            <small class="wsscd-mode-description">
                                                <?php esc_html_e( 'Scales with order value.', 'smart-cycle-discounts' ); ?>
                                            </small>
                                        </span>
                                    </label>
                                    <label class="wsscd-threshold-mode-option">
                                        <input type="radio" name="threshold_mode" value="fixed" <?php checked( ( $step_data['threshold_mode'] ?? '' ), 'fixed' ); ?>>
                                        <span class="wsscd-threshold-mode-card">
                                            <?php WSSCD_Icon_Helper::render( 'receipt', array( 'size' => 20 ) ); ?>
                                            <strong><?php esc_html_e( 'Fixed Amount Off', 'smart-cycle-discounts' ); ?></strong>
                                            <small class="wsscd-mode-description">
                                                <?php esc_html_e( 'Best for high-value or fixed shipping.', 'smart-cycle-discounts' ); ?>
                                            </small>
                                        </span>
                                    </label>
                                </div>
                                
                                <!-- Percentage Thresholds -->
                                <div class="wsscd-threshold-group" id="percentage-thresholds-group">
                                    <div class="wsscd-thresholds-list" id="percentage-thresholds-list" data-empty-message="<?php esc_attr_e('No percentage thresholds added yet', 'smart-cycle-discounts'); ?>">
                                        <!-- Percentage thresholds will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary wsscd-add-threshold" data-threshold-type="percentage">
                                        <?php WSSCD_Icon_Helper::render( 'add', array( 'size' => 16 ) ); ?>
                                        <?php esc_html_e('Add Percentage Threshold', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>
                                
                                <!-- Fixed Amount Thresholds -->
                                <div class="wsscd-threshold-group wsscd-hidden" id="fixed-thresholds-group">
                                    <div class="wsscd-thresholds-list" id="fixed-thresholds-list" data-empty-message="<?php esc_attr_e('No fixed amount thresholds added yet', 'smart-cycle-discounts'); ?>">
                                        <!-- Fixed amount thresholds will be populated by JavaScript -->
                                    </div>
                                    <button type="button" class="button button-secondary wsscd-add-threshold" data-threshold-type="fixed">
                                        <?php WSSCD_Icon_Helper::render( 'add', array( 'size' => 16 ) ); ?>
                                        <?php esc_html_e('Add Fixed Amount Threshold', 'smart-cycle-discounts'); ?>
                                    </button>
                                </div>

                                <!-- Hidden input for validation -->
                                <input type="hidden" name="thresholds" id="thresholds" value="">
                            </div>
                            <div class="wsscd-inline-preview" id="spend-threshold-preview">
                                <span class="preview-text"></span>
                            </div>
                        </td>
                    </tr>
                        </table>
                    </div>
                </div><!-- #wsscd-discount-details-container -->
        <?php
        $discount_value_content = ob_get_clean();
        
        wsscd_wizard_card(array(
            'title' => __('Configure Discount Details', 'smart-cycle-discounts'),
            'subtitle' => __('Set specific values and conditions for your selected discount type.', 'smart-cycle-discounts'),
            'icon' => 'admin-settings',
            'content' => $discount_value_content,
            'id' => 'discount-value-card',
            'help_topic' => 'card-discount-value'
        ));
        ?>

        <!-- Step 3: Configure Badge Display -->
        <?php
        ob_start();
        ?>
            <!-- Enable Badge Toggle -->
            <div class="wsscd-badge-enable-section">
                <div class="wsscd-badge-enable-row">
                    <label class="wsscd-toggle" for="badge_enabled">
                        <input type="checkbox"
                               id="badge_enabled"
                               name="badge_enabled"
                               value="1"
                               <?php checked( $badge_enabled ); ?>>
                        <span class="wsscd-toggle-slider"></span>
                    </label>
                    <div class="wsscd-badge-enable-text">
                        <label for="badge_enabled" class="wsscd-badge-enable-title"><?php esc_html_e('Show promotional badges on products', 'smart-cycle-discounts'); ?></label>
                        <span class="wsscd-badge-enable-description"><?php esc_html_e('Display custom badges on discounted items. When disabled, theme\'s default sale badge will show.', 'smart-cycle-discounts'); ?></span>
                    </div>
                </div>

                <!-- Context Warning for BOGO/Spend Threshold -->
                <div class="wsscd-badge-context-warning wsscd-hidden">
                    <?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
                    <span class="wsscd-context-warning-text"></span>
                </div>
            </div>

            <!-- Badge Configuration (shown when enabled) -->
            <div class="wsscd-badge-config-wrapper wsscd-badge-setting" data-depends-on="badge_enabled">

                <!-- Left Column: Controls -->
                <div class="wsscd-badge-config-controls">

                    <!-- Badge Text Section -->
                    <div class="wsscd-badge-config-section">
                        <h4 class="wsscd-badge-section-title">
                            <?php WSSCD_Icon_Helper::render( 'edit', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Badge Text', 'smart-cycle-discounts'); ?>
                        </h4>

                        <div class="wsscd-badge-text-mode">
                            <select id="badge_text_mode" name="badge_text_mode" class="wsscd-enhanced-select">
                                <option value="auto" <?php selected( 'auto' === $badge_text || empty( $badge_text ) ); ?>>
                                    <?php esc_html_e('🤖 Auto-generate (e.g., "20% OFF")', 'smart-cycle-discounts'); ?>
                                </option>
                                <option value="custom" <?php selected( 'auto' !== $badge_text && ! empty( $badge_text ) ); ?>>
                                    <?php esc_html_e('✏️ Custom Text', 'smart-cycle-discounts'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="wsscd-custom-badge-text<?php echo esc_attr( 'auto' === $badge_text || empty( $badge_text ) ? ' wsscd-hidden' : '' ); ?>">
                            <input type="text"
                                   id="badge_text_custom"
                                   name="badge_text_custom"
                                   value="<?php echo esc_attr( 'auto' === $badge_text ? '' : $badge_text ); ?>"
                                   class="wsscd-enhanced-input"
                                   placeholder="<?php esc_attr_e('e.g., SALE, LIMITED TIME', 'smart-cycle-discounts'); ?>"
                                   maxlength="50">
                            <p class="description">
                                <?php esc_html_e('Keep it short for better visibility (max 50 characters).', 'smart-cycle-discounts'); ?>
                            </p>
                        </div>

                        <input type="hidden" id="badge_text" name="badge_text" value="<?php echo esc_attr( $badge_text ); ?>">
                    </div>

                    <!-- Position Section -->
                    <div class="wsscd-badge-config-section">
                        <h4 class="wsscd-badge-section-title">
                            <?php WSSCD_Icon_Helper::render( 'move', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Position', 'smart-cycle-discounts'); ?>
                        </h4>

                        <div class="wsscd-badge-position-grid">
                            <label class="wsscd-position-box">
                                <input type="radio" name="badge_position" value="top-left" <?php checked( $badge_position, 'top-left' ); ?>>
                                <span class="wsscd-position-visual">
                                    <span class="wsscd-position-indicator"></span>
                                </span>
                                <span class="wsscd-position-name"><?php esc_html_e('Top Left', 'smart-cycle-discounts'); ?></span>
                            </label>

                            <label class="wsscd-position-box">
                                <input type="radio" name="badge_position" value="top-right" <?php checked( $badge_position, 'top-right' ); ?>>
                                <span class="wsscd-position-visual">
                                    <span class="wsscd-position-indicator"></span>
                                </span>
                                <span class="wsscd-position-name"><?php esc_html_e('Top Right', 'smart-cycle-discounts'); ?></span>
                            </label>

                            <label class="wsscd-position-box">
                                <input type="radio" name="badge_position" value="bottom-left" <?php checked( $badge_position, 'bottom-left' ); ?>>
                                <span class="wsscd-position-visual">
                                    <span class="wsscd-position-indicator"></span>
                                </span>
                                <span class="wsscd-position-name"><?php esc_html_e('Bottom Left', 'smart-cycle-discounts'); ?></span>
                            </label>

                            <label class="wsscd-position-box">
                                <input type="radio" name="badge_position" value="bottom-right" <?php checked( $badge_position, 'bottom-right' ); ?>>
                                <span class="wsscd-position-visual">
                                    <span class="wsscd-position-indicator"></span>
                                </span>
                                <span class="wsscd-position-name"><?php esc_html_e('Bottom Right', 'smart-cycle-discounts'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Colors Section -->
                    <div class="wsscd-badge-config-section">
                        <h4 class="wsscd-badge-section-title">
                            <?php WSSCD_Icon_Helper::render( 'admin-appearance', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Colors', 'smart-cycle-discounts'); ?>
                        </h4>

                        <div class="wsscd-badge-colors-grid">
                            <div class="wsscd-color-picker-box">
                                <label for="badge_bg_color" class="wsscd-color-box-label">
                                    <?php esc_html_e('Background', 'smart-cycle-discounts'); ?>
                                </label>
                                <input type="text"
                                       id="badge_bg_color"
                                       name="badge_bg_color"
                                       value="<?php echo esc_attr( $badge_bg_color ); ?>"
                                       class="wsscd-color-picker"
                                       data-default-color="#ff0000">
                            </div>

                            <div class="wsscd-color-picker-box">
                                <label for="badge_text_color" class="wsscd-color-box-label">
                                    <?php esc_html_e('Text', 'smart-cycle-discounts'); ?>
                                </label>
                                <input type="text"
                                       id="badge_text_color"
                                       name="badge_text_color"
                                       value="<?php echo esc_attr( $badge_text_color ); ?>"
                                       class="wsscd-color-picker"
                                       data-default-color="#ffffff">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Preview -->
                <div class="wsscd-badge-config-preview">
                    <div class="wsscd-badge-preview-header">
                        <h4 class="wsscd-badge-section-title">
                            <?php WSSCD_Icon_Helper::render( 'visibility', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Live Preview', 'smart-cycle-discounts'); ?>
                        </h4>
                    </div>

                    <div class="wsscd-badge-preview-wrapper">
                        <div class="wsscd-badge-preview-mock-product">
                            <div class="wsscd-badge-preview-placeholder">
                                <?php WSSCD_Icon_Helper::render( 'images-alt2', array( 'size' => 16 ) ); ?>
                                <span><?php esc_html_e('Product Image', 'smart-cycle-discounts'); ?></span>
                            </div>
                            <span class="wsscd-badge-preview-badge"
                                  data-position="<?php echo esc_attr( $badge_position ); ?>"
                                  style="background-color: <?php echo esc_attr( $badge_bg_color ); ?>; color: <?php echo esc_attr( $badge_text_color ); ?>;">
                                <?php
                                if ( 'auto' === $badge_text || empty( $badge_text ) ) {
                                    if ( 'percentage' === $discount_type && ! empty( $discount_value_percentage ) ) {
                                        printf( '%d%% %s', absint( $discount_value_percentage ), esc_html__( 'OFF', 'smart-cycle-discounts' ) );
                                    } elseif ( 'fixed' === $discount_type && ! empty( $discount_value_fixed ) ) {
                                        echo wp_kses_post( wc_price( $discount_value_fixed ) ) . ' ' . esc_html__( 'OFF', 'smart-cycle-discounts' );
                                    } else {
                                        esc_html_e( 'SALE', 'smart-cycle-discounts' );
                                    }
                                } else {
                                    echo esc_html( $badge_text );
                                }
                                ?>
                            </span>
                        </div>
                        <p class="wsscd-preview-note">
                            <?php esc_html_e('This is how your badge will appear on product images in your shop.', 'smart-cycle-discounts'); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php
        $badge_display_content = ob_get_clean();

        wsscd_wizard_card(array(
            'title' => __( 'Badge Display', 'smart-cycle-discounts' ),
            'icon' => 'tag',
            'badge' => array(
                'text' => __( 'Optional', 'smart-cycle-discounts' ),
                'type' => 'optional'
            ),
            'subtitle' => __('Configure how your discount appears on products to attract customer attention.', 'smart-cycle-discounts'),
            'content' => $badge_display_content,
            'id' => 'badge-display-card',
            'help_topic' => 'card-badge-display'
        ));
        ?>

        <!-- Free Shipping Configuration -->
        <?php
        // Get free shipping config from step data
        $free_shipping_config = $step_data['free_shipping_config'] ?? array();
        $free_shipping_enabled = ! empty( $free_shipping_config['enabled'] );
        $free_shipping_methods = $free_shipping_config['methods'] ?? 'all';

        ob_start();
        ?>
            <!-- Enable Free Shipping Toggle -->
            <div class="wsscd-free-shipping-enable-section" data-help-topic="free-shipping-toggle">
                <div class="wsscd-free-shipping-enable-row">
                    <label class="wsscd-toggle" for="free_shipping_enabled">
                        <input type="checkbox"
                               id="free_shipping_enabled"
                               name="free_shipping_enabled"
                               value="1"
                               <?php checked( $free_shipping_enabled ); ?>>
                        <span class="wsscd-toggle-slider"></span>
                    </label>
                    <div class="wsscd-free-shipping-enable-text">
                        <label for="free_shipping_enabled" class="wsscd-free-shipping-enable-title"><?php esc_html_e( 'Include Free Shipping', 'smart-cycle-discounts' ); ?></label>
                        <span class="wsscd-free-shipping-enable-description"><?php esc_html_e( 'Customers get free shipping when their cart contains products from this campaign.', 'smart-cycle-discounts' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Shipping Methods Selection (shown when enabled) -->
            <div class="wsscd-free-shipping-config-wrapper wsscd-free-shipping-setting<?php echo ! $free_shipping_enabled ? ' wsscd-hidden' : ''; ?>" data-depends-on="free_shipping_enabled">
                <div class="wsscd-free-shipping-methods-section">
                    <h4 class="wsscd-free-shipping-section-title">
                        <?php WSSCD_Icon_Helper::render( 'admin-settings', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e( 'Apply To', 'smart-cycle-discounts' ); ?>
                    </h4>

                    <div class="wsscd-user-roles-mode-selector" data-help-topic="free-shipping-methods">
                        <label class="wsscd-user-roles-mode-option">
                            <input type="radio"
                                   name="free_shipping_method_type"
                                   value="all"
                                   <?php checked( 'all' === $free_shipping_methods || ! is_array( $free_shipping_methods ) ); ?>>
                            <span class="wsscd-user-roles-mode-card" data-help-topic="option-free-shipping-all">
                                <?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 20 ) ); ?>
                                <strong><?php esc_html_e( 'All Shipping Methods', 'smart-cycle-discounts' ); ?></strong>
                                <small class="wsscd-mode-description">
                                    <?php esc_html_e( 'Make all shipping options free.', 'smart-cycle-discounts' ); ?>
                                </small>
                            </span>
                        </label>
                        <label class="wsscd-user-roles-mode-option">
                            <input type="radio"
                                   name="free_shipping_method_type"
                                   value="selected"
                                   <?php checked( is_array( $free_shipping_methods ) ); ?>>
                            <span class="wsscd-user-roles-mode-card" data-help-topic="option-free-shipping-selected">
                                <?php WSSCD_Icon_Helper::render( 'list-view', array( 'size' => 20 ) ); ?>
                                <strong><?php esc_html_e( 'Selected Methods Only', 'smart-cycle-discounts' ); ?></strong>
                                <small class="wsscd-mode-description">
                                    <?php esc_html_e( 'Choose which shipping methods become free.', 'smart-cycle-discounts' ); ?>
                                </small>
                            </span>
                        </label>
                    </div>

                    <!-- Specific Methods Selection -->
                    <div class="wsscd-shipping-methods-list<?php echo 'all' === $free_shipping_methods || ! is_array( $free_shipping_methods ) ? ' wsscd-hidden' : ''; ?>" id="wsscd-shipping-methods-list" data-help-topic="free-shipping-selection">
                        <div class="wsscd-shipping-methods-loading">
                            <span class="spinner is-active"></span>
                            <?php esc_html_e( 'Loading shipping methods...', 'smart-cycle-discounts' ); ?>
                        </div>
                        <div class="wsscd-shipping-methods-checkboxes">
                            <!-- Populated via JavaScript -->
                        </div>
                    </div>

                    <!-- Hidden input to store selected methods -->
                    <input type="hidden"
                           id="free_shipping_methods"
                           name="free_shipping_methods"
                           value="<?php echo esc_attr( is_array( $free_shipping_methods ) ? wp_json_encode( $free_shipping_methods ) : 'all' ); ?>">
                </div>

                <!-- Spend Threshold Note -->
                <div class="wsscd-free-shipping-note wsscd-hidden" id="wsscd-free-shipping-threshold-note">
                    <?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
                    <span><?php esc_html_e( 'Free shipping will only apply when the spend threshold is met.', 'smart-cycle-discounts' ); ?></span>
                </div>
            </div>
        <?php
        $free_shipping_content = ob_get_clean();

        wsscd_wizard_card( array(
            'title'      => __( 'Free Shipping', 'smart-cycle-discounts' ),
            'icon'       => 'shipping',
            'badge'      => array(
                'text' => __( 'Optional', 'smart-cycle-discounts' ),
                'type' => 'optional',
            ),
            'subtitle'   => __( 'Offer free shipping as an additional incentive for customers.', 'smart-cycle-discounts' ),
            'content'    => $free_shipping_content,
            'id'         => 'free-shipping-card',
            'help_topic' => 'card-free-shipping',
        ) );
        ?>

        <!-- User Role Targeting -->
        <?php
        // Get user roles config from step data.
        $user_roles_mode = $step_data['user_roles_mode'] ?? 'all';
        $user_roles = $step_data['user_roles'] ?? array();
        $show_roles_selector = 'all' !== $user_roles_mode;

        ob_start();
        ?>
            <!-- User Role Mode Selection -->
            <div class="wsscd-user-roles-mode-section">
                <div class="wsscd-user-roles-mode-row">
                    <div class="wsscd-user-roles-mode-field">
                        <label for="user_roles_mode" class="wsscd-field-label">
                            <?php esc_html_e( 'Who can use this discount?', 'smart-cycle-discounts' ); ?>
                        </label>
                        <?php
                        WSSCD_Tooltip_Helper::render(
                            __( 'Control which users can see and use this discount based on their WordPress role.', 'smart-cycle-discounts' )
                        );
                        ?>
                    </div>
                    <div class="wsscd-user-roles-mode-selector" data-help-topic="user-roles-mode">
                        <label class="wsscd-user-roles-mode-option">
                            <input type="radio"
                                   name="user_roles_mode"
                                   value="all"
                                   <?php checked( 'all' === $user_roles_mode ); ?>>
                            <span class="wsscd-user-roles-mode-card" data-help-topic="option-user-roles-all">
                                <?php WSSCD_Icon_Helper::render( 'groups', array( 'size' => 20 ) ); ?>
                                <strong><?php esc_html_e( 'All Users', 'smart-cycle-discounts' ); ?></strong>
                                <small class="wsscd-mode-description">
                                    <?php esc_html_e( 'Everyone can use this discount.', 'smart-cycle-discounts' ); ?>
                                </small>
                            </span>
                        </label>
                        <label class="wsscd-user-roles-mode-option">
                            <input type="radio"
                                   name="user_roles_mode"
                                   value="include"
                                   <?php checked( 'include' === $user_roles_mode ); ?>>
                            <span class="wsscd-user-roles-mode-card" data-help-topic="option-user-roles-include">
                                <?php WSSCD_Icon_Helper::render( 'yes-alt', array( 'size' => 20 ) ); ?>
                                <strong><?php esc_html_e( 'Include Only', 'smart-cycle-discounts' ); ?></strong>
                                <small class="wsscd-mode-description">
                                    <?php esc_html_e( 'Only selected roles can use this.', 'smart-cycle-discounts' ); ?>
                                </small>
                            </span>
                        </label>
                        <label class="wsscd-user-roles-mode-option">
                            <input type="radio"
                                   name="user_roles_mode"
                                   value="exclude"
                                   <?php checked( 'exclude' === $user_roles_mode ); ?>>
                            <span class="wsscd-user-roles-mode-card" data-help-topic="option-user-roles-exclude">
                                <?php WSSCD_Icon_Helper::render( 'dismiss', array( 'size' => 20 ) ); ?>
                                <strong><?php esc_html_e( 'Exclude', 'smart-cycle-discounts' ); ?></strong>
                                <small class="wsscd-mode-description">
                                    <?php esc_html_e( 'Hide from selected roles.', 'smart-cycle-discounts' ); ?>
                                </small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Role Selection (shown when mode is include/exclude) -->
            <div class="wsscd-user-roles-selection-wrapper<?php echo ! $show_roles_selector ? ' wsscd-hidden' : ''; ?>"
                 id="wsscd-user-roles-selection"
                 data-depends-on="user_roles_mode">
                <div class="wsscd-user-roles-selection-section">
                    <h4 class="wsscd-user-roles-section-title">
                        <?php WSSCD_Icon_Helper::render( 'admin-users', array( 'size' => 16 ) ); ?>
                        <span id="wsscd-user-roles-section-label">
                            <?php
                            if ( 'include' === $user_roles_mode ) {
                                esc_html_e( 'Select Roles to Include', 'smart-cycle-discounts' );
                            } else {
                                esc_html_e( 'Select Roles to Exclude', 'smart-cycle-discounts' );
                            }
                            ?>
                        </span>
                    </h4>

                    <div class="wsscd-user-roles-checkboxes"
                         role="group"
                         aria-label="<?php esc_attr_e( 'Select user roles', 'smart-cycle-discounts' ); ?>"
                         data-help-topic="user-roles-selection">
                        <?php
                        // Get available roles.
                        $available_roles = array();
                        if ( class_exists( 'WSSCD_Role_Helper' ) ) {
                            $available_roles = WSSCD_Role_Helper::get_available_roles();
                        } else {
                            $wp_roles = wp_roles();
                            foreach ( $wp_roles->get_names() as $slug => $name ) {
                                $available_roles[ $slug ] = translate_user_role( $name );
                            }
                        }

                        foreach ( $available_roles as $slug => $name ) :
                            $is_checked = in_array( $slug, (array) $user_roles, true );
                        ?>
                            <label class="wsscd-role-checkbox">
                                <input type="checkbox"
                                       name="user_roles[]"
                                       value="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( $is_checked ); ?>
                                       aria-label="<?php echo esc_attr( $name ); ?>">
                                <span class="wsscd-role-label"><?php echo esc_html( $name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="wsscd-field-help" id="wsscd-user-roles-help">
                        <?php
                        if ( 'include' === $user_roles_mode ) {
                            esc_html_e( 'Only users with the selected roles will see this discount.', 'smart-cycle-discounts' );
                        } else {
                            esc_html_e( 'Users with the selected roles will NOT see this discount.', 'smart-cycle-discounts' );
                        }
                        ?>
                    </p>
                </div>

                <!-- Hidden input to store selected roles as JSON -->
                <input type="hidden"
                       id="user_roles_json"
                       name="user_roles_json"
                       value="<?php echo esc_attr( wp_json_encode( (array) $user_roles ) ); ?>">
            </div>
        <?php
        $user_roles_content = ob_get_clean();

        wsscd_wizard_card( array(
            'title'      => __( 'User Role Targeting', 'smart-cycle-discounts' ),
            'icon'       => 'admin-users',
            'badge'      => array(
                'text' => __( 'Optional', 'smart-cycle-discounts' ),
                'type' => 'optional',
            ),
            'subtitle'   => __( 'Restrict this discount to specific user roles like wholesalers, subscribers, or VIP customers.', 'smart-cycle-discounts' ),
            'content'    => $user_roles_content,
            'id'         => 'user-roles-card',
            'help_topic' => 'card-user-roles',
        ) );
        ?>

        <!-- Step 4: Configure Discount Rules (PRO Feature) - Dual-block pattern for WordPress.org compliance -->
        <?php if ( ! $has_pro_access ) : ?>
        <?php
        // Block 1: Promotional locked card (always in both ZIPs) - shown to free users
        ob_start();
        ?>
        <div class="wsscd-pro-feature-locked">
            <div class="wsscd-pro-feature-locked__content">
                <?php echo wp_kses_post( WSSCD_Badge_Helper::pro_badge( __( 'PRO Feature', 'smart-cycle-discounts' ) ) ); ?>
                <h4 class="wsscd-pro-feature-locked__title">
                    <?php esc_html_e( 'Advanced Discount Rules', 'smart-cycle-discounts' ); ?>
                </h4>
                <p class="wsscd-pro-feature-locked__description">
                    <?php esc_html_e( 'Fine-tune your discounts with powerful configuration options to maximize impact while protecting margins.', 'smart-cycle-discounts' ); ?>
                </p>
                <ul class="wsscd-pro-feature-locked__features">
                    <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Set per-customer and total usage limits', 'smart-cycle-discounts' ); ?></li>
                    <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Configure minimum quantity and order amount', 'smart-cycle-discounts' ); ?></li>
                    <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Control campaign stacking and coupon combinations', 'smart-cycle-discounts' ); ?></li>
                    <li><?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Set maximum discount caps to protect margins', 'smart-cycle-discounts' ); ?></li>
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
        $discount_rules_content = ob_get_clean();

        wsscd_wizard_card( array(
            'title'      => __( 'Configure Discount Rules', 'smart-cycle-discounts' ),
            'icon'       => 'admin-settings',
            'badge'      => array( 'text' => __( 'PRO', 'smart-cycle-discounts' ), 'type' => 'pro' ),
            'subtitle'   => __( 'Fine-tune how your discount works to maximize impact while protecting margins.', 'smart-cycle-discounts' ),
            'content'    => $discount_rules_content,
            'id'         => 'discount-rules-card',
            'class'      => 'wsscd-wizard-card--locked',
            'help_topic' => 'card-discount-rules',
        ) );
        ?>
        <?php endif; ?>

        <?php if ( wsscd_fs()->is__premium_only() ) : ?>
        <?php if ( $has_pro_access ) : ?>
        <?php
        // Block 2: Functional card (only in PRO ZIP, shown when licensed)
        ob_start();
        ?>
        <div id="wsscd-discount-rules-container">
                <fieldset class="wsscd-discount-rules-fieldset">
                <legend class="screen-reader-text"><?php esc_html_e( 'Discount Configuration Rules', 'smart-cycle-discounts' ); ?></legend>
                <!-- Usage Limits Section -->
                <div class="wsscd-discount-rules-section wsscd-collapsible" data-section="usage-limits">
                    <h4 class="wsscd-rules-section-title wsscd-collapsible-trigger">
                        <span class="wsscd-section-text">
                            <?php WSSCD_Icon_Helper::render( 'admin-users', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Usage Limits', 'smart-cycle-discounts'); ?>
                        </span>
                        <span class="wsscd-collapse-icon">
                            <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 16 ) ); ?>
                        </span>
                    </h4>
                    
                    <div class="wsscd-collapsible-content">
                        <table class="form-table wsscd-rules-table">
                            <tr>
                                <th scope="row">
                                    <label for="usage_limit_per_customer">
                                        <span class="wsscd-label-icon" title="<?php esc_attr_e('Per customer usage limit', 'smart-cycle-discounts'); ?>">
                                            <?php WSSCD_Icon_Helper::render( 'admin-users', array( 'size' => 16 ) ); ?>
                                        </span>
                                        <?php esc_html_e('Per Customer', 'smart-cycle-discounts'); ?>
                                        <?php WSSCD_Tooltip_Helper::render( __('Set how many times each individual customer can redeem this discount during each rotation cycle. Leave empty for unlimited uses.', 'smart-cycle-discounts') ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="wsscd-input-wrapper">
                                        <input type="number"
                                               id="usage_limit_per_customer"
                                               name="usage_limit_per_customer"
                                               value="<?php echo esc_attr( $usage_limit_per_customer ); ?>"
                                               min="0"
                                               max="999999"
                                               step="1"
                                               inputmode="numeric"
                                               data-label="Per Customer Limit"
                                               data-input-type="integer"
                                               class="wsscd-enhanced-input"
                                               placeholder="∞">
                                        <span class="wsscd-field-suffix"><?php esc_html_e('uses per cycle', 'smart-cycle-discounts'); ?></span>
                                    </div>
                                </td>
                            </tr>
                        
                            <tr>
                                <th scope="row">
                                    <label for="total_usage_limit">
                                        <span class="wsscd-label-icon" title="<?php esc_attr_e('Total usage limit', 'smart-cycle-discounts'); ?>">
                                            <?php WSSCD_Icon_Helper::render( 'chart-pie', array( 'size' => 16 ) ); ?>
                                        </span>
                                        <?php esc_html_e('Total Uses', 'smart-cycle-discounts'); ?>
                                        <?php WSSCD_Tooltip_Helper::render( __('Total times this discount can be used by all customers combined per cycle. Great for flash sales or limited inventory.', 'smart-cycle-discounts') ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="wsscd-input-wrapper">
                                        <input type="number"
                                               id="total_usage_limit"
                                               name="total_usage_limit"
                                               value="<?php echo esc_attr( $step_data['total_usage_limit'] ?? '' ); ?>"
                                               min="0"
                                               max="999999"
                                               step="1"
                                               inputmode="numeric"
                                               data-label="Total Usage Limit"
                                               data-input-type="integer"
                                               class="wsscd-enhanced-input"
                                               placeholder="∞">
                                        <span class="wsscd-field-suffix"><?php esc_html_e('redemptions per cycle', 'smart-cycle-discounts'); ?></span>
                                    </div>
                                </td>
                            </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="lifetime_usage_cap">
                                    <?php esc_html_e('Lifetime Usage Cap', 'smart-cycle-discounts'); ?>
                                    <?php WSSCD_Tooltip_Helper::render( __('Total uses allowed across all campaign cycles. Ends campaign when reached.', 'smart-cycle-discounts') ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="lifetime_usage_cap"
                                       name="lifetime_usage_cap"
                                       value="<?php echo esc_attr( $step_data['lifetime_usage_cap'] ?? '' ); ?>"
                                       min="0"
                                       max="999999"
                                       step="1"
                                       inputmode="numeric"
                                       data-label="Lifetime Usage Cap"
                                       data-input-type="integer"
                                       class="wsscd-enhanced-input"
                                       placeholder="<?php esc_attr_e('Unlimited', 'smart-cycle-discounts'); ?>">
                                <span class="wsscd-field-suffix"><?php esc_html_e('uses across all cycles', 'smart-cycle-discounts'); ?></span>
                            </td>
                        </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Application Rules Section -->
                <div class="wsscd-discount-rules-section wsscd-collapsible" data-section="application-rules">
                    <h4 class="wsscd-rules-section-title wsscd-collapsible-trigger">
                        <span class="wsscd-section-text">
                            <?php WSSCD_Icon_Helper::render( 'admin-generic', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Application Rules', 'smart-cycle-discounts'); ?>
                        </span>
                        <span class="wsscd-collapse-icon">
                            <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 16 ) ); ?>
                        </span>
                    </h4>
                    
                    <div class="wsscd-collapsible-content">
                        <table class="form-table wsscd-rules-table">
                            <tr data-hide-for-types="fixed,bogo" class="wsscd-conditional-rule">
                                <th scope="row">
                                    <label for="apply_to">
                                        <span class="wsscd-label-icon" title="<?php esc_attr_e('Application method', 'smart-cycle-discounts'); ?>">
                                            <?php WSSCD_Icon_Helper::render( 'admin-generic', array( 'size' => 16 ) ); ?>
                                        </span>
                                        <?php esc_html_e('Apply To', 'smart-cycle-discounts'); ?>
                                        <?php WSSCD_Tooltip_Helper::render( __('Example: 10% off each item vs. 10% off total order', 'smart-cycle-discounts') ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="wsscd-select-wrapper">
                                        <select id="apply_to" name="apply_to" class="wsscd-enhanced-select">
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
                                    <?php WSSCD_Tooltip_Helper::render( __('Cap the maximum discount regardless of percentage. Protects margins on expensive items.', 'smart-cycle-discounts') ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="wsscd-input-wrapper wsscd-input-with-prefix">
                                    <span class="wsscd-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                                    <input type="number"
                                           id="max_discount_amount"
                                           name="max_discount_amount"
                                           value="<?php echo esc_attr( $step_data['max_discount_amount'] ?? '' ); ?>"
                                           min="0"
                                           max="999999"
                                           step="0.01"
                                           inputmode="decimal"
                                           data-label="Max Discount Amount"
                                           data-input-type="decimal"
                                           class="wsscd-enhanced-input"
                                           placeholder="<?php esc_attr_e('No limit', 'smart-cycle-discounts'); ?>">
                                </div>
                            </td>
                        </tr>
                        
                        <tr data-hide-for-types="bogo" class="wsscd-conditional-rule">
                            <th scope="row">
                                <label for="minimum_quantity">
                                    <?php esc_html_e('Minimum Quantity', 'smart-cycle-discounts'); ?>
                                    <?php WSSCD_Tooltip_Helper::render( __('Requires this many items from the selected products to activate discount.', 'smart-cycle-discounts') ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="minimum_quantity"
                                       name="minimum_quantity"
                                       value="<?php echo esc_attr( $step_data['minimum_quantity'] ?? '' ); ?>"
                                       min="0"
                                       max="10000"
                                       step="1"
                                       inputmode="numeric"
                                       data-label="Minimum Quantity"
                                       data-input-type="integer"
                                       class="wsscd-enhanced-input"
                                       placeholder="<?php esc_attr_e('No minimum', 'smart-cycle-discounts'); ?>">
                                <span class="wsscd-field-suffix"><?php esc_html_e('items', 'smart-cycle-discounts'); ?></span>
                            </td>
                        </tr>
                        
                        <tr data-hide-for-types="spend_threshold" class="wsscd-conditional-rule">
                            <th scope="row">
                                <label for="minimum_order_amount">
                                    <?php esc_html_e('Minimum Order Amount', 'smart-cycle-discounts'); ?>
                                    <?php WSSCD_Tooltip_Helper::render( __('Cart subtotal must meet this amount for discount to apply.', 'smart-cycle-discounts') ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="wsscd-input-wrapper wsscd-input-with-prefix">
                                    <span class="wsscd-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                                    <input type="number"
                                           id="minimum_order_amount"
                                           name="minimum_order_amount"
                                           value="<?php echo esc_attr( $step_data['minimum_order_amount'] ?? '' ); ?>"
                                           min="0"
                                           max="999999"
                                           step="0.01"
                                           inputmode="decimal"
                                           data-label="Minimum Order Amount"
                                           data-input-type="decimal"
                                           class="wsscd-enhanced-input"
                                           placeholder="<?php esc_attr_e('No minimum', 'smart-cycle-discounts'); ?>">
                                </div>
                            </td>
                        </tr>
                    </table>
                </div><!-- .wsscd-collapsible-content -->
                </div><!-- .wsscd-discount-rules-section -->
                
                <!-- Combination Policy Section -->
                <div class="wsscd-discount-rules-section wsscd-collapsible" data-section="combination-policy">
                    <h4 class="wsscd-rules-section-title wsscd-collapsible-trigger">
                        <span class="wsscd-section-text">
                            <?php WSSCD_Icon_Helper::render( 'admin-links', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e('Combination Policy', 'smart-cycle-discounts'); ?>
                        </span>
                        <span class="wsscd-collapse-icon">
                            <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 16 ) ); ?>
                        </span>
                    </h4>
                    
                    <div class="wsscd-collapsible-content">
                        <table class="form-table wsscd-rules-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Campaign Stacking', 'smart-cycle-discounts'); ?>
                                <?php WSSCD_Tooltip_Helper::render( __('Whether this discount can be used with other active campaigns.', 'smart-cycle-discounts') ); ?>
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
                    </div><!-- .wsscd-collapsible-content -->
                </div><!-- .wsscd-discount-rules-section -->
                </fieldset><!-- .wsscd-discount-rules-fieldset -->
        </div><!-- #wsscd-discount-rules-container -->
        <?php
        $discount_rules_content = ob_get_clean();

        wsscd_wizard_card(array(
            'title' => __( 'Configure Discount Rules', 'smart-cycle-discounts' ),
            'icon' => 'admin-settings',
            'badge' => array( 'text' => __( 'Optional', 'smart-cycle-discounts' ), 'type' => 'optional' ),
            'subtitle' => __('Fine-tune how your discount works to maximize impact while protecting margins.', 'smart-cycle-discounts'),
            'content' => $discount_rules_content,
            'id' => 'discount-rules-card',
            'class' => empty($step_data['discount_type']) ? 'wsscd-hidden' : '',
            'help_topic' => 'card-discount-rules'
        ));
        ?>
        <?php endif; // End $has_pro_access ?>
        <?php endif; // End is__premium_only() ?>

        <!-- Hidden fields for complex discount data - follow field definitions pattern -->
        <div class="wsscd-hidden-fields wsscd-visually-hidden">
            <!-- Tier Mode -->
            <input type="hidden" name="tier_mode" id="tier_mode"
                   value="<?php echo esc_attr( $step_data['tier_mode'] ?? 'percentage' ); ?>"
                   class="wsscd-field" data-type="select">
            <!-- Threshold Mode -->
            <input type="hidden" name="threshold_mode" id="threshold_mode" 
                   value="<?php echo esc_attr( $step_data['threshold_mode'] ?? 'percentage' ); ?>" 
                   class="wsscd-field" data-type="select">
            <!-- BOGO Configuration as hidden fields -->
            <input type="hidden" name="bogo_config" id="bogo_config" 
                   data-value="<?php echo esc_attr( json_encode( $step_data['bogo_config'] ?? array() ) ); ?>" 
                   class="wsscd-field" data-type="array">
        </div>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
wsscd_wizard_render_step( array(
    'title' => __( 'Discount Configuration', 'smart-cycle-discounts' ),
    'description' => __( 'Configure the discount type, value, and rules for your campaign', 'smart-cycle-discounts' ),
    'content' => $content,
    'step' => 'discounts'
) );
?>

<?php
// Validation rules are now handled by the centralized field schema system

// Initialize state data for Discounts step
wsscd_wizard_state_script('discounts', array(
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
    'thresholds' => $step_data['thresholds'] ?? array(),
    'tier_mode' => $step_data['tier_mode'] ?? 'percentage',
    // BOGO Settings
    'bogo_buy_quantity' => $bogo_buy_quantity,
    'bogo_get_quantity' => $bogo_get_quantity,
    'bogo_discount_percentage' => $bogo_discount_percentage,
    'bogo_apply_to' => $bogo_apply_to,
    // Tiered Data
    'tiered_data' => $step_data['tiered_data'] ?? array(),
    // Conditions Logic
    'conditions_logic' => $conditions_logic,
    // Free Shipping
    'free_shipping_config' => $free_shipping_config,
    // User Role Targeting
    'user_roles_mode' => $user_roles_mode,
    'user_roles' => $user_roles
), array(
    'selected_products' => $wsscd_discount_step_data['selected_products'] ?? array(),
    'currency_data' => $currency_data
));
// Currency data is now provided via wsscdSettings (Asset Localizer with auto case conversion)
