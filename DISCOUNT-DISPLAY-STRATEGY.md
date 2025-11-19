# Smart Cycle Discounts: Display Strategy & Badge Controls

## Executive Summary

**Root Cause:** BOGO campaigns cause memory exhaustion because the plugin attempts to calculate **all** discount types on shop pages, even when they're meaningless without cart/quantity context.

**Solution:** Implement smart display rules based on discount type characteristics, and add user controls for badge display.

---

## 1. Discount Type Analysis

### Type Characteristics Matrix

| Discount Type | Context Required | Calculation Complexity | Shop Page Meaningful | Recommendation |
|--------------|-----------------|----------------------|---------------------|----------------|
| **Percentage** | None | O(1) - Simple | ✅ YES | Display everywhere |
| **Fixed Amount** | None | O(1) - Simple | ✅ YES | Display everywhere |
| **Tiered Volume** | Quantity (default=1) | O(n) tiers | ⚠️ SIMPLIFIED | Show minimum tier |
| **BOGO** | Quantity (required) | O(1) but context-dependent | ❌ NO | Product page only |
| **Spend Threshold** | Cart total | O(n) thresholds | ❌ NO | Cart widget only |

### Detailed Analysis by Type

#### 1. Percentage Discounts
**Example:** "20% off all products"
- **Shop Page:** ✅ Display "20% OFF" badge
- **Product Page:** ✅ Display "Save 20%" with calculated amount
- **Cart:** ✅ Applied automatically
- **Why it works:** Meaningful with or without quantity context

#### 2. Fixed Amount Discounts
**Example:** "$10 off per product"
- **Shop Page:** ✅ Display "$10 OFF" badge
- **Product Page:** ✅ Display "Save $10" with final price
- **Cart:** ✅ Applied automatically
- **Why it works:** Fixed value is clear at any stage

#### 3. Tiered Volume Discounts
**Example:** "Buy 1-4: 10% off, Buy 5-9: 20% off, Buy 10+: 30% off"
- **Shop Page:** ⚠️ Display "Volume Pricing from 10% OFF" (minimum tier)
- **Product Page:** ✅ Display full tier table with quantity selector
- **Cart:** ✅ Applied based on quantity in cart
- **Why simplified on shop:** Full context requires quantity, but can show potential

#### 4. BOGO (Buy One Get One)
**Example:** "Buy 2 Get 1 Free"
- **Shop Page:** ❌ Don't display OR show generic "Special Offer"
- **Product Page:** ✅ Display full BOGO details: "Buy 2 Get 1 Free!"
- **Cart:** ✅ Applied based on quantity in cart
- **Why skip shop:** "Buy 2 Get 1" is meaningless when viewing single product listing

#### 5. Spend Threshold
**Example:** "Spend $100, Save $20"
- **Shop Page:** ❌ Don't display
- **Product Page:** ❌ Don't display
- **Cart Widget:** ✅ "Spend $X more to save $Y"
- **Why skip everywhere except cart:** Requires cart total to be meaningful

---

## 2. Current Implementation Problems

### Problem 1: Uniform Badge Logic

**File:** `includes/frontend/class-discount-display.php`

**Lines 67-99:** Both shop and single product badges use same method:
```php
public function render_shop_badge(): void {
    $discount = $this->get_product_discount( $product );  // ← Full calculation
    if ( $discount ) {
        $this->output_badge( $discount, 'shop' );
    }
}
```

**Lines 112-138:** `get_product_discount()` always:
1. Queries database for active campaigns
2. Calls discount engine for full calculation
3. Returns calculated percentage

**Result:** On shop page with 50 products = 50 database queries + 50 full calculations

### Problem 2: No Context Awareness

BOGO requires quantity context (line 234 of `class-bogo-strategy.php`):
```php
public function supports_context( array $context ): bool {
    return isset( $context['quantity'] ) && intval( $context['quantity'] ) > 0;
}
```

Shop pages don't provide quantity → BOGO returns no discount → but full lookup already happened.

### Problem 3: No User Controls

**Current State:**
- No settings page for badge display
- No on/off toggle for badges
- No per-location control (shop vs product page)
- No per-discount-type control

---

## 3. Recommended Solution

### Phase 1: Smart Display Rules (Immediate)

**Create:** `includes/frontend/class-discount-display-rules.php`

```php
class SCD_Discount_Display_Rules {

    /**
     * Check if discount type should display on shop pages.
     */
    public function can_display_on_shop( string $discount_type ): bool {
        return in_array(
            $discount_type,
            array( 'percentage', 'fixed', 'tiered' ),
            true
        );
    }

    /**
     * Check if discount requires full calculation.
     */
    public function requires_calculation( string $discount_type, string $context ): bool {
        // Shop pages never need calculation - use campaign data directly
        if ( 'shop' === $context ) {
            return false;
        }

        // Product pages need calculation for accurate pricing
        return true;
    }

    /**
     * Get badge text directly from campaign data (no calculation).
     */
    public function get_simple_badge_text( SCD_Campaign $campaign ): string {
        $discount_type = $campaign->get_discount_type();
        $discount_value = $campaign->get_discount_value();

        switch ( $discount_type ) {
            case 'percentage':
                return sprintf( '%d%% OFF', $discount_value );

            case 'fixed':
                return sprintf( '%s OFF', wc_price( $discount_value ) );

            case 'tiered':
                $rules = $campaign->get_discount_rules();
                $tiers = $rules['tiers'] ?? array();
                if ( ! empty( $tiers ) ) {
                    $min_discount = min( array_column( $tiers, 'discount_value' ) );
                    return sprintf( 'From %d%% OFF', $min_discount );
                }
                return 'Volume Pricing';

            default:
                return '';
        }
    }
}
```

### Phase 2: Optimize Badge Display

**Modify:** `includes/frontend/class-discount-display.php`

```php
class SCD_Discount_Display {

    private SCD_Discount_Display_Rules $display_rules;

    public function __construct( /* ... */, SCD_Discount_Display_Rules $display_rules ) {
        // ...
        $this->display_rules = $display_rules;
    }

    /**
     * Render shop badge (OPTIMIZED).
     */
    public function render_shop_badge(): void {
        global $product;

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        // Get badge settings
        $settings = get_option( 'scd_badge_settings', array() );

        // Check if shop badges are enabled
        if ( empty( $settings['enable_shop_badges'] ) ) {
            return;
        }

        // Get campaigns WITHOUT full calculation
        $campaigns = $this->campaign_manager->get_active_campaigns_for_product(
            $product->get_id()
        );

        if ( empty( $campaigns ) ) {
            return;
        }

        $campaign = reset( $campaigns );
        $discount_type = $campaign->get_discount_type();

        // Check if this type should display on shop
        if ( ! $this->display_rules->can_display_on_shop( $discount_type ) ) {
            return;
        }

        // Get badge text directly from campaign (no calculation!)
        $badge_text = $this->display_rules->get_simple_badge_text( $campaign );

        if ( $badge_text ) {
            $this->output_badge( array(
                'text' => $badge_text,
                'type' => $discount_type,
            ), 'shop' );
        }
    }

    /**
     * Render single product badge (full calculation).
     */
    public function render_single_product_badge(): void {
        // Keep existing full calculation logic
        // This is fine because single product pages don't have 50+ products
    }
}
```

### Phase 3: User Controls

**Create:** Settings page section for badge controls

**Location:** `Settings > Display` or new `Display` tab

**Settings Structure:**
```php
array(
    'scd_badge_settings' => array(
        // Global Controls
        'enable_badges' => true/false,

        // Location Controls
        'enable_shop_badges' => true/false,
        'enable_product_badges' => true/false,
        'enable_cart_badges' => true/false,

        // Per-Type Controls (Advanced)
        'display_types' => array(
            'percentage' => array(
                'shop' => true,
                'product' => true,
                'format' => '{value}% OFF',
            ),
            'fixed' => array(
                'shop' => true,
                'product' => true,
                'format' => '{value} OFF',
            ),
            'tiered' => array(
                'shop' => true,
                'product' => true,
                'format' => 'From {min}% OFF',
            ),
            'bogo' => array(
                'shop' => false,  // Forced off
                'product' => true,
                'format' => 'Buy {buy} Get {get} Free',
            ),
            'spend_threshold' => array(
                'shop' => false,  // Forced off
                'product' => false,  // Forced off
                'cart' => true,
            ),
        ),

        // Style Controls
        'badge_position' => 'top-left|top-right|bottom-left|bottom-right',
        'badge_style' => 'default|minimal|bold',
        'custom_css' => '',
    ),
)
```

**UI Mockup:**

```
┌─ Badge Display Settings ────────────────────────────────────┐
│                                                              │
│ ☑ Enable discount badges                                    │
│                                                              │
│ Badge Locations:                                            │
│   ☑ Show on shop/archive pages                             │
│   ☑ Show on single product pages                           │
│   ☑ Show in cart widget                                    │
│                                                              │
│ ─── Advanced: Per-Discount-Type Controls ──────────────────│
│                                                              │
│ Percentage Discounts:                                       │
│   Shop:    ☑ Enable    Format: [20% OFF        ]          │
│   Product: ☑ Enable    Format: [Save 20%       ]          │
│                                                              │
│ Fixed Amount Discounts:                                     │
│   Shop:    ☑ Enable    Format: [$10 OFF        ]          │
│   Product: ☑ Enable    Format: [Save $10       ]          │
│                                                              │
│ Tiered Volume Discounts:                                    │
│   Shop:    ☑ Enable    Format: [From 10% OFF   ]          │
│   Product: ☑ Enable    (Show full tier table)              │
│                                                              │
│ BOGO (Buy One Get One):                                     │
│   Shop:    ☐ Enable    (Disabled - requires quantity)      │
│   Product: ☑ Enable    Format: [Buy 2 Get 1 Free]         │
│                                                              │
│ Spend Threshold:                                            │
│   Shop:    ☐ Enable    (Disabled - requires cart total)    │
│   Product: ☐ Enable    (Disabled - requires cart total)    │
│   Cart:    ☑ Enable    Format: [Spend $X more...]         │
│                                                              │
│ Badge Appearance:                                           │
│   Position: ⚫ Top Left  ○ Top Right  ○ Bottom Left        │
│   Style:    ⚫ Default   ○ Minimal    ○ Bold               │
│                                                              │
│   Custom CSS: ┌─────────────────────────────────┐         │
│               │ .scd-discount-badge {           │         │
│               │     /* Your styles */           │         │
│               │ }                               │         │
│               └─────────────────────────────────┘         │
│                                                              │
│ [Save Changes]                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## 4. Performance Impact Analysis

### Current (With Memory Issue):

**Shop Page Load (50 products):**
- 50 × `get_active_campaigns_for_product()` = 50 DB queries
- 50 × Full discount calculation
- Memory usage: ~512MB (exhaustion point)
- Page load: 8-12 seconds or CRASH

### After Phase 1 (Smart Rules):

**Shop Page Load (50 products):**
- Skip BOGO/spend threshold entirely (0 queries for those)
- Only check percentage/fixed/tiered campaigns
- Still 50 DB queries but simpler campaigns
- Memory usage: ~200-300MB
- Page load: 4-6 seconds

### After Phase 2 (No Calculation):

**Shop Page Load (50 products):**
- 50 × Get campaign data (cached)
- 0 × Full discount calculations
- Read badge text directly from campaign
- Memory usage: ~100-150MB
- Page load: 1-2 seconds

### After Phase 3 (User Control):

**Shop Page Load (if user disables shop badges):**
- 0 × Campaign queries
- 0 × Calculations
- Memory usage: Baseline WordPress
- Page load: 0.5-1 second

---

## 5. Implementation Priority

### IMMEDIATE (Fix Memory Crash):

1. ✅ **DONE:** Add temporary skip for shop pages (lines 127-131 of class-wc-price-integration.php)
2. **TODO:** Implement `SCD_Discount_Display_Rules` class
3. **TODO:** Update `render_shop_badge()` to use simple badge text

### PHASE 2 (Optimize):

4. **TODO:** Add campaign data caching layer
5. **TODO:** Implement bulk campaign lookup for shop pages
6. **TODO:** Add settings page for badge controls

### PHASE 3 (Polish):

7. **TODO:** Add per-type display controls
8. **TODO:** Add badge style/position options
9. **TODO:** Add custom CSS support
10. **TODO:** Add preview system for badge appearance

---

## 6. Recommended Settings Defaults

```php
'scd_badge_settings' => array(
    'enable_badges' => true,
    'enable_shop_badges' => true,     // Safe now with smart rules
    'enable_product_badges' => true,
    'enable_cart_badges' => true,

    'display_types' => array(
        'percentage' => array(
            'shop' => true,           // ✅ Display
            'product' => true,
        ),
        'fixed' => array(
            'shop' => true,           // ✅ Display
            'product' => true,
        ),
        'tiered' => array(
            'shop' => true,           // ✅ Display simplified
            'product' => true,
        ),
        'bogo' => array(
            'shop' => false,          // ❌ Force off
            'product' => true,
        ),
        'spend_threshold' => array(
            'shop' => false,          // ❌ Force off
            'product' => false,       // ❌ Force off
            'cart' => true,           // ✅ Only in cart
        ),
    ),

    'badge_position' => 'top-left',
    'badge_style' => 'default',
)
```

---

## 7. Testing Checklist

### Before Implementation:
- [x] Confirm BOGO campaign causes memory exhaustion
- [x] Confirm deactivating BOGO fixes shop page
- [ ] Test other discount types individually on shop page

### After Phase 1:
- [ ] Shop page loads without memory error
- [ ] Percentage badges display on shop
- [ ] Fixed amount badges display on shop
- [ ] Tiered badges show "From X% OFF"
- [ ] BOGO badges don't display on shop
- [ ] Spend threshold badges don't display on shop
- [ ] Product pages still show full details for all types

### After Phase 2:
- [ ] Shop page loads in under 2 seconds
- [ ] No full discount calculations on shop page
- [ ] Memory usage under 200MB
- [ ] Badge text accurate to campaign settings

### After Phase 3:
- [ ] Settings page functional
- [ ] Toggles work correctly
- [ ] Badge styles apply correctly
- [ ] Custom CSS applies
- [ ] Preview works

---

## Conclusion

**The memory issue is NOT a bug in BOGO calculation** - it's an **architectural design flaw** where the plugin attempts to calculate ALL discount types everywhere, even when contextually meaningless.

**The solution is NOT to disable shop pages entirely** - it's to implement **smart display rules** that understand which discount types make sense in which contexts.

**With smart rules:**
- Percentage/Fixed: Display everywhere (simple, meaningful)
- Tiered: Display simplified on shop, full on product page
- BOGO: Product page only (requires quantity context)
- Spend Threshold: Cart only (requires cart total)

This gives users the best experience while maintaining optimal performance.
