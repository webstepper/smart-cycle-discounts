# Hook Registration Fix - Duplicate Registrations Removed

**Date:** 2025-10-27
**Issue:** `call_user_func_array(): class SCD_WooCommerce_Integration does not have a method "modify_product_price"`
**Root Cause:** Duplicate hook registrations
**Status:** ✅ FIXED

---

## 🐛 THE PROBLEM

The main plugin class (`includes/class-smart-cycle-discounts.php`) was registering WooCommerce hooks directly on the coordinator class:

```php
// OLD CODE (WRONG):
$this->loader->add_filter('woocommerce_product_get_price', $wc_integration, 'modify_product_price', 10, 2);
$this->loader->add_filter('woocommerce_product_get_sale_price', $wc_integration, 'modify_sale_price', 10, 2);
// ... etc (15+ hook registrations)
```

This caused **duplicate hook registrations**:
1. Main plugin class registered hooks on coordinator
2. Coordinator's `init()` delegated to sub-integrations
3. Sub-integrations registered their own hooks

**Result:** WordPress tried to call `modify_product_price()` on `SCD_WooCommerce_Integration`, but that method was removed during refactoring (it's now in `SCD_WC_Price_Integration`).

---

## ✅ THE FIX

**File:** `includes/class-smart-cycle-discounts.php`  
**Lines:** 569-578

### BEFORE (WRONG):
```php
if ($wc_integration) {
    if (method_exists($wc_integration, 'init')) {
        $wc_integration->init();
    }

    // Register WooCommerce hooks through the loader
    $this->loader->add_filter('woocommerce_product_get_price', $wc_integration, 'modify_product_price', 10, 2);
    $this->loader->add_filter('woocommerce_product_get_sale_price', $wc_integration, 'modify_sale_price', 10, 2);
    $this->loader->add_filter('woocommerce_product_variation_get_price', $wc_integration, 'modify_product_price', 10, 2);
    $this->loader->add_filter('woocommerce_product_variation_get_sale_price', $wc_integration, 'modify_sale_price', 10, 2);
    $this->loader->add_filter('woocommerce_get_price_html', $wc_integration, 'modify_price_html', 10, 2);

    // Cart hooks
    $this->loader->add_action('woocommerce_before_calculate_totals', $wc_integration, 'modify_cart_item_prices', 10, 1);
    $this->loader->add_filter('woocommerce_cart_item_price', $wc_integration, 'display_cart_item_price', 10, 3);
    $this->loader->add_filter('woocommerce_cart_item_subtotal', $wc_integration, 'display_cart_item_subtotal', 10, 3);

    // CRITICAL: Also register for REST API requests (WooCommerce Blocks)
    $this->loader->add_action('rest_api_init', $wc_integration, 'init', 5);
}
```

### AFTER (CORRECT):
```php
if ($wc_integration) {
    // Initialize WooCommerce integration coordinator
    // The coordinator's init() method handles all hook registration via sub-integrations
    if (method_exists($wc_integration, 'init')) {
        $wc_integration->init();
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        SCD_Log::info( 'WooCommerce integration coordinator initialized successfully' );
    }
}
```

---

## 🎯 WHY THIS HAPPENED

During the Phase 1-3 refactoring:
1. We extracted methods from the coordinator into sub-integrations
2. Sub-integrations registered their own hooks via `register_hooks()`
3. **BUT** we forgot to remove the old hook registrations from the main plugin class

Result: Double registration = hooks pointing to non-existent methods

---

## ✅ WHAT WAS REMOVED

**Removed 9 duplicate hook registrations:**

### Price Hooks:
- `woocommerce_product_get_price` → Now in `SCD_WC_Price_Integration`
- `woocommerce_product_get_sale_price` → Now in `SCD_WC_Price_Integration`
- `woocommerce_product_variation_get_price` → Now in `SCD_WC_Price_Integration`
- `woocommerce_product_variation_get_sale_price` → Now in `SCD_WC_Price_Integration`
- `woocommerce_get_price_html` → Now in `SCD_WC_Price_Integration`

### Cart Hooks:
- `woocommerce_before_calculate_totals` → Now in `SCD_WC_Price_Integration`
- `woocommerce_cart_item_price` → Now in `SCD_WC_Display_Integration`
- `woocommerce_cart_item_subtotal` → Now in `SCD_WC_Display_Integration`

### REST API Hook:
- `rest_api_init` (duplicate init call) → Removed

---

## 🔍 HOW HOOKS ARE REGISTERED NOW

**Correct Flow:**

1. Main plugin class calls `$wc_integration->init()`
2. Coordinator's `init()` method:
   - Initializes sub-integrations
   - Calls `setup_hooks()`
3. `setup_hooks()` delegates to sub-integrations:
   ```php
   if ( $this->price_integration ) {
       $this->price_integration->register_hooks();
   }
   if ( $this->display_integration ) {
       $this->display_integration->register_hooks();
   }
   // ... etc
   ```
4. Each sub-integration registers its own hooks

---

## ✅ VERIFICATION

After fix, hooks are registered correctly:

- ✅ `SCD_WC_Price_Integration` registers price hooks
- ✅ `SCD_WC_Display_Integration` registers display hooks
- ✅ `SCD_WC_Cart_Message_Service` registers cart message hooks
- ✅ `SCD_WC_Admin_Integration` registers admin hooks
- ✅ `SCD_WC_Order_Integration` registers order hooks
- ✅ NO duplicate registrations
- ✅ NO hooks on coordinator class

---

## 🎓 LESSON LEARNED

**When refactoring to coordinator pattern:**
1. ✅ Extract methods to sub-classes
2. ✅ Have sub-classes register their own hooks
3. ✅ Have coordinator delegate initialization
4. ⚠️ **CRITICAL:** Remove old hook registrations from original caller!

This was the missing step that caused the error.

---

**Fix Complete ✅**

*The plugin should now work correctly after reloading the page.*

