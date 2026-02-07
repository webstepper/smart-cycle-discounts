# WooCommerce Blocks Integration - Testing & Verification Guide

## Quick Verification Commands

### 1. Check PHP Integration (via WP-CLI or Code Snippets)

```php
// Verify class is loaded
if ( class_exists( 'WSSCD_WC_Blocks_Integration' ) ) {
    echo "✅ WSSCD_WC_Blocks_Integration class loaded\n";
} else {
    echo "❌ Class not found - check autoloader\n";
}

// Verify WooCommerce integration has blocks component
$container = wsscd_get_container();
if ( $container && $container->has( 'woocommerce_integration' ) ) {
    $wc_integration = $container->get( 'woocommerce_integration' );

    // Use reflection to check private property
    $reflection = new ReflectionClass( $wc_integration );
    $property = $reflection->getProperty( 'blocks_integration' );
    $property->setAccessible( true );
    $blocks_integration = $property->getValue( $wc_integration );

    if ( $blocks_integration instanceof WSSCD_WC_Blocks_Integration ) {
        echo "✅ Blocks integration initialized\n";
    } else {
        echo "⚠️ Blocks integration not initialized (WC Blocks may not be active)\n";
    }
}

// Check if WooCommerce Blocks is active
if ( class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
    echo "✅ WooCommerce Blocks is active\n";
} else {
    echo "⚠️ WooCommerce Blocks not detected\n";
}

// Check compatibility declaration
if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
    echo "✅ FeaturesUtil available for compatibility declaration\n";
}
```

### 2. Browser Console Tests

Open block cart page and run in console:

```javascript
// Check if WC Blocks API is available
console.log('WC Blocks:', window.wc?.blocksCheckout ? '✅' : '❌');

// Check if our data is localized
console.log('SCD Data:', window.wsscdBlocksData ? '✅' : '❌');

// Check currency settings
if (window.wsscdBlocksData) {
    console.log('Currency:', window.wsscdBlocksData.currency);
    console.log('Locale:', window.wsscdBlocksData.locale);
}

// Check if filters are registered
console.log('Filters registered:',
    window.wc?.blocksCheckout?.__experimentalRegisteredFilters ? '✅' : '❌'
);
```

### 3. Network Tab Verification

1. Open DevTools → Network tab
2. Navigate to block cart page
3. Verify these files load:
   - `wsscd-blocks-checkout.js` (Status: 200)
   - `wsscd-blocks-checkout.css` (Status: 200)

### 4. Store API Response Check

```bash
# Get cart via Store API (requires valid session)
curl -X GET 'https://yoursite.local/wp-json/wc/store/v1/cart' \
  -H 'Nonce: YOUR_NONCE_HERE' \
  -H 'Cookie: YOUR_COOKIE_HERE' \
  | jq '.items[].extensions.wsscd'
```

Expected response for discounted item:
```json
{
  "has_discount": true,
  "original_price": 100,
  "discounted_price": 80,
  "discount_amount": 20,
  "campaign_id": 123
}
```

## Manual Testing Steps

### Setup: Enable Block Cart/Checkout

1. Go to **WooCommerce → Settings → Advanced → Features**
2. Enable "Cart and Checkout Blocks" (if available)
3. Edit **Cart page** (Pages → Cart → Edit)
   - Remove `[woocommerce_cart]` shortcode
   - Add "Cart" block (search for "Cart" in block inserter)
4. Edit **Checkout page** (Pages → Checkout → Edit)
   - Remove `[woocommerce_checkout]` shortcode
   - Add "Checkout" block

### Test Case 1: Simple Product Discount

**Setup:**
1. Create campaign: 20% off "Test Product"
2. Set campaign to active

**Test:**
1. Add "Test Product" to cart
2. View classic cart (`/cart/` with shortcode) → Verify strikethrough works
3. View block cart (`/cart/` with Cart block) → Verify strikethrough appears
4. Expected display: `~~$100.00~~ **$80.00**`

**Verify:**
- [ ] Original price has strikethrough (del tag)
- [ ] Discounted price is bold and green
- [ ] CSS class `wsscd-discounted-item` applied to line item
- [ ] Cart total is correct ($80.00)

### Test Case 2: Variable Product

**Setup:**
1. Create campaign: 15% off "Variable Product"
2. Variable product has variations at $50, $75, $100

**Test:**
1. Add $100 variation to cart
2. View block cart
3. Expected: `~~$100.00~~ **$85.00**`

**Verify:**
- [ ] Correct variation price shown
- [ ] Discount applies to selected variation
- [ ] Total calculates correctly

### Test Case 3: Tiered Quantity Discount

**Setup:**
1. Create tiered campaign:
   - 1-4 items: 10% off
   - 5-9 items: 20% off
   - 10+ items: 30% off

**Test:**
1. Add 3 items to cart → Expect 10% off
2. Increase to 6 items → Expect 20% off
3. Increase to 12 items → Expect 30% off

**Verify:**
- [ ] Price updates dynamically as quantity changes
- [ ] Strikethrough reflects correct tier
- [ ] No page refresh needed for tier changes

### Test Case 4: BOGO Discount

**Setup:**
1. Create BOGO campaign: Buy 2 Get 1 at 50% off

**Test:**
1. Add 3 items to cart
2. Expected: 2 at full price, 1 at 50% off

**Verify:**
- [ ] Discount applies to correct items
- [ ] Strikethrough shows on discounted items
- [ ] Total is correct

### Test Case 5: Mix of Discounted & Regular Items

**Setup:**
1. Campaign applies to "Product A" only

**Test:**
1. Add "Product A" (with discount) to cart
2. Add "Product B" (no discount) to cart

**Verify:**
- [ ] Product A shows strikethrough
- [ ] Product B shows regular price (no strikethrough)
- [ ] Total is correct

### Test Case 6: No Active Campaigns

**Test:**
1. Deactivate all campaigns
2. Add products to cart
3. View block cart

**Verify:**
- [ ] No JavaScript errors in console
- [ ] Regular prices displayed
- [ ] No strikethrough elements
- [ ] No `wsscd-discounted-item` class applied

### Test Case 7: Free Shipping Threshold

**Setup:**
1. Campaign includes free shipping at $100

**Test:**
1. Add $110 worth of discounted products to cart
2. Cart total after discount: $88

**Verify:**
- [ ] Free shipping does/doesn't apply (based on your rules)
- [ ] Shipping updates in block checkout

### Test Case 8: Checkout Flow

**Test:**
1. Add discounted product to cart
2. Proceed to block checkout
3. Complete purchase

**Verify:**
- [ ] Strikethrough pricing shows in checkout
- [ ] Order completes successfully
- [ ] View order in WP Admin → Orders
- [ ] Order meta contains `_wsscd_campaign_id`
- [ ] Order meta contains `_wsscd_original_price`
- [ ] Analytics tracked correctly

### Test Case 9: Currency Formatting

**Setup:**
1. Change currency to EUR (WooCommerce → Settings → General)
2. Set decimal separator to `,` and thousand separator to `.`

**Test:**
1. Add €1.234,56 product with 20% discount
2. Expected: `€1.234,56 €987,65`

**Verify:**
- [ ] Currency symbol correct
- [ ] Decimal separator correct
- [ ] Thousand separator correct

### Test Case 10: Mobile Responsiveness

**Test:**
1. Open block cart on mobile device (or DevTools mobile view)

**Verify:**
- [ ] Strikethrough pricing readable
- [ ] No layout issues
- [ ] Touch targets adequate
- [ ] Font sizes appropriate

## Edge Case Testing

### Edge Case 1: Guest Checkout
- Add discounted item as guest
- Complete checkout
- Verify discount applies

### Edge Case 2: Usage Limits
- Campaign limited to 1 use per customer
- First purchase: discount applies
- Second purchase: discount doesn't apply

### Edge Case 3: Campaign Expires During Session
- Add item with discount to cart
- Wait for campaign to expire
- Refresh cart
- Verify discount removed

### Edge Case 4: Stock Limits
- Product with 5 items in stock
- Campaign requires minimum 10 items
- Verify discount doesn't apply

## Browser Compatibility

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## Accessibility Testing

### Screen Reader
1. Enable screen reader (NVDA/JAWS/VoiceOver)
2. Navigate to block cart
3. Verify:
   - [ ] Original price announced
   - [ ] Discounted price announced
   - [ ] "Sale" or "Discount" indicator announced

### Keyboard Navigation
1. Tab through block cart
2. Verify:
   - [ ] All interactive elements focusable
   - [ ] Focus indicators visible
   - [ ] No keyboard traps

### Color Contrast
1. Use browser inspector
2. Check contrast ratio:
   - [ ] Green discounted price: minimum 4.5:1 ratio
   - [ ] Strikethrough text: readable at 0.6 opacity

## Performance Testing

### Page Load Time
1. Measure with DevTools Performance tab
2. Before blocks integration: ___ ms
3. After blocks integration: ___ ms
4. Impact: < 50ms is acceptable

### JavaScript Execution
1. Check JavaScript execution time in Performance tab
2. `wsscd-blocks-checkout.js` execution: < 10ms

### Network Requests
1. Count additional network requests
2. Expected: +2 (JS + CSS)

## Debugging Common Issues

### Issue: "Filters not registered"

**Symptom:** Console error or warning about filters
**Check:**
1. WooCommerce Blocks version (need 8.3+)
2. `window.wc.blocksCheckout` exists in console
3. JavaScript file loaded (Network tab)

**Solution:**
- Update WooCommerce to 8.3+
- Clear cache
- Verify file paths in PHP class

### Issue: "Strikethrough not showing"

**Symptom:** Regular price displayed, no strikethrough
**Check:**
1. Campaign is active
2. Product is eligible for discount
3. Browser console for JavaScript errors
4. `extensions.wsscd` in Store API response

**Debug:**
```javascript
// In browser console
fetch('/wp-json/wc/store/v1/cart')
  .then(r => r.json())
  .then(data => {
    console.log('Cart items:', data.items);
    data.items.forEach(item => {
      console.log('Item:', item.name);
      console.log('Extensions:', item.extensions);
      console.log('WSSCD:', item.extensions.wsscd);
    });
  });
```

### Issue: "Wrong currency format"

**Symptom:** $100,00 instead of $100.00
**Check:**
1. WooCommerce currency settings
2. `window.wsscdBlocksData.currency` in console
3. Browser locale

**Solution:**
- Verify WooCommerce → Settings → General → Currency Options
- Clear browser cache

### Issue: "CSS not loading"

**Symptom:** Strikethrough works but no green color
**Check:**
1. Network tab shows `wsscd-blocks-checkout.css` (200 status)
2. File exists at `resources/assets/css/frontend/wsscd-blocks-checkout.css`
3. File permissions (755)

**Solution:**
```bash
# Check file
ls -la resources/assets/css/frontend/wsscd-blocks-checkout.css

# Fix permissions if needed
chmod 755 resources/assets/css/frontend/wsscd-blocks-checkout.css
```

## Success Criteria

All items must pass:

- [x] PHP syntax valid
- [x] JavaScript syntax valid
- [x] CSS syntax valid
- [x] Autoloader registers class
- [x] WooCommerce integration initializes blocks component
- [x] Hooks registered correctly
- [x] Compatibility declared
- [ ] Strikethrough appears in block cart
- [ ] Strikethrough appears in block checkout
- [ ] Currency formatting correct
- [ ] No JavaScript errors
- [ ] No PHP errors
- [ ] Classic cart still works
- [ ] Order completion works
- [ ] Analytics tracked
- [ ] All edge cases pass
- [ ] All browsers supported
- [ ] Accessibility compliant
- [ ] Performance acceptable

## Rollback Plan

If issues are found:

1. **Quick disable:**
   ```php
   // In class-woocommerce-integration.php, line 358
   // Comment out this block:
   /*
   if ( class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
       $this->blocks_integration = new WSSCD_WC_Blocks_Integration( $this->logger );
   }
   */
   ```

2. **Full removal:**
   - Delete: `includes/integrations/woocommerce/class-wc-blocks-integration.php`
   - Delete: `resources/assets/js/frontend/wsscd-blocks-checkout.js`
   - Delete: `resources/assets/css/frontend/wsscd-blocks-checkout.css`
   - Remove autoloader line 154
   - Remove blocks_integration property from class-woocommerce-integration.php
   - Remove blocks_integration initialization
   - Remove blocks_integration hook registration
   - Remove cart_checkout_blocks compatibility declaration

3. **Git revert:**
   ```bash
   git revert HEAD
   ```

## Post-Deployment Monitoring

Monitor for 48 hours:
- [ ] Error logs (PHP and JavaScript)
- [ ] Support tickets mentioning cart/checkout
- [ ] Conversion rate changes
- [ ] Cart abandonment rate
- [ ] User feedback

## Sign-off

- [ ] Developer tested: ___________
- [ ] QA tested: ___________
- [ ] Client approved: ___________
- [ ] Production deployed: ___________

---

**Integration Version:** 1.5.70
**Test Date:** __________
**Tested By:** __________
