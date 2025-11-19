# Discount Rules Testing Guide

## 🚀 Quick Start

### Option 1: Run via Browser
1. Navigate to: `wp-admin/wp-content/plugins/smart-cycle-discounts/test-discount-rules.php?create_discount_test_campaigns=1`
2. View the results page
3. Go to Smart Cycle Discounts → Campaigns to see all test campaigns

### Option 2: Run via WP-CLI
```bash
cd /path/to/wordpress
wp eval-file wp-content/plugins/smart-cycle-discounts/test-discount-rules.php
```

---

## 📋 Test Campaigns Created

The script creates 10 test campaigns, each testing specific discount rules:

### Campaign 1: Minimum Order Amount
- **Name:** TEST: Minimum Order Amount ($50)
- **Rule Tested:** `minimum_order_amount`
- **Discount:** 15% off
- **Condition:** Cart total must be ≥ $50

### Campaign 2: Minimum Quantity
- **Name:** TEST: Minimum Quantity (3 items)
- **Rule Tested:** `minimum_quantity`
- **Discount:** 20% off
- **Condition:** Product quantity must be ≥ 3

### Campaign 3: Maximum Discount Cap
- **Name:** TEST: Max Discount Cap ($25)
- **Rule Tested:** `max_discount_amount`
- **Discount:** 50% off (capped at $25)
- **Condition:** Discount never exceeds $25

### Campaign 4: Usage Limit Per Customer
- **Name:** TEST: Per Customer Limit (2 uses)
- **Rule Tested:** `usage_limit_per_customer`
- **Discount:** 10% off
- **Condition:** Each customer can use 2 times only

### Campaign 5: Total Usage Limit
- **Name:** TEST: Total Usage Limit (10 uses)
- **Rule Tested:** `total_usage_limit`
- **Discount:** $5 off
- **Condition:** Stops after 10 total uses (all customers)

### Campaign 6: Lifetime Usage Cap
- **Name:** TEST: Lifetime Cap (50 uses)
- **Rule Tested:** `lifetime_usage_cap`
- **Discount:** 25% off
- **Condition:** Maximum 50 uses across all time/cycles

### Campaign 7: No Sale Items
- **Name:** TEST: No Sale Items
- **Rule Tested:** `apply_to_sale_items`
- **Discount:** 15% off
- **Condition:** Blocked on products already on sale

### Campaign 8: No Coupons Allowed
- **Name:** TEST: No Coupons
- **Rule Tested:** `allow_coupons`
- **Discount:** 30% off
- **Condition:** WooCommerce coupons blocked when active

### Campaign 9: No Stacking
- **Name:** TEST: No Stacking (Priority 5)
- **Rule Tested:** `stack_with_others`
- **Discount:** 20% off
- **Condition:** Prevents other campaigns from applying

### Campaign 10: Combined Rules
- **Name:** TEST: Combined Rules
- **Rule Tested:** Multiple rules together
- **Discount:** 40% off (max $15 off)
- **Conditions:**
  - Min $30 order
  - Min 2 quantity
  - Max $15 off
  - 5 uses per customer

---

## 🧪 Detailed Test Procedures

### Test 1: Minimum Order Amount ($50)

**Setup:**
1. Find Campaign: "TEST: Minimum Order Amount ($50)"
2. Ensure campaign is active

**Test Steps:**
1. Add a $20 product to cart
2. **Expected:** No discount shown
3. Add another $20 product (cart = $40)
4. **Expected:** Still no discount
5. Add another product to reach $50+
6. **Expected:** ✅ 15% discount now appears

**Pass Criteria:**
- ❌ Discount blocked when cart < $50
- ✅ Discount applies when cart ≥ $50

---

### Test 2: Minimum Quantity (3 items)

**Setup:**
1. Find Campaign: "TEST: Minimum Quantity (3 items)"
2. Ensure campaign is active

**Test Steps:**
1. Add 1 product to cart
2. **Expected:** No discount shown
3. Update quantity to 2
4. **Expected:** Still no discount
5. Update quantity to 3
6. **Expected:** ✅ 20% discount now appears
7. Update quantity to 4
8. **Expected:** ✅ Discount still applies

**Pass Criteria:**
- ❌ Discount blocked when qty < 3
- ✅ Discount applies when qty ≥ 3

---

### Test 3: Maximum Discount Cap ($25)

**Setup:**
1. Find Campaign: "TEST: Max Discount Cap ($25)"
2. Ensure campaign is active
3. Need a product priced at $100+

**Test Steps:**
1. Add $100 product to cart
2. **Expected:** 50% would be $50, but capped at $25
3. **Verify:** Discount shows as $25 off
4. Final price: $75 (not $50)

**Test with cheaper product:**
1. Add $40 product to cart
2. **Expected:** 50% = $20 (under cap)
3. **Verify:** Full $20 discount applies
4. Final price: $20

**Pass Criteria:**
- ✅ On $100 item: Discount capped at $25 (not $50)
- ✅ On $40 item: Full $20 discount (under cap)

---

### Test 4: Usage Limit Per Customer (2 uses)

**Setup:**
1. Find Campaign: "TEST: Per Customer Limit (2 uses)"
2. Ensure campaign is active
3. Use same email address for all orders

**Test Steps:**
1. **Order 1:** Add product, checkout with email test@example.com
2. **Expected:** ✅ 10% discount applies
3. **Order 2:** Add product, checkout with same email
4. **Expected:** ✅ 10% discount still applies
5. **Order 3:** Add product, checkout with same email
6. **Expected:** ❌ Discount blocked - "Usage limit exceeded"

**Test with different email:**
1. **Order 1:** Checkout with different@example.com
2. **Expected:** ✅ Discount applies (new customer)

**Pass Criteria:**
- ✅ First 2 orders: Discount applies
- ❌ Third order: Discount blocked
- ✅ Different customer: Gets fresh 2 uses

---

### Test 5: Total Usage Limit (10 uses)

**Setup:**
1. Find Campaign: "TEST: Total Usage Limit (10 uses)"
2. Ensure campaign is active
3. Use different email addresses

**Test Steps:**
1. Create orders 1-10 with different emails
2. **Expected:** All 10 get $5 discount
3. Create order 11 (any email)
4. **Expected:** ❌ Discount blocked - "Usage limit reached"

**Check admin:**
1. Go to campaign analytics
2. **Verify:** Shows 10 total uses
3. Campaign should show as "limit reached"

**Pass Criteria:**
- ✅ Orders 1-10: Discount applies
- ❌ Order 11+: Discount blocked
- ✅ Admin shows correct usage count

---

### Test 6: Lifetime Usage Cap (50 uses)

**Setup:**
1. Find Campaign: "TEST: Lifetime Cap (50 uses)"
2. This tests across multiple cycles (if recurring)

**Test Steps:**
1. Generate orders to reach 50 uses
2. **Expected:** All 50 get 25% discount
3. Attempt order 51
4. **Expected:** ❌ Discount blocked

**For recurring campaigns:**
1. Let campaign cycle expire
2. Start new cycle
3. **Expected:** Still blocked (lifetime = 50 reached)

**Pass Criteria:**
- ✅ First 50 uses: Discount applies
- ❌ After 50: Always blocked (even in new cycles)

---

### Test 7: No Sale Items

**Setup:**
1. Find Campaign: "TEST: No Sale Items"
2. Need products: one regular price, one on sale

**Test Steps:**
1. **Regular price product:**
   - Add to cart
   - **Expected:** ✅ 15% discount applies

2. **Put product on sale in WooCommerce:**
   - Set sale price in WooCommerce
   - Add to cart
   - **Expected:** ❌ Discount blocked - "Cannot be applied to sale items"

3. **Remove sale price:**
   - Remove sale price in WooCommerce
   - Refresh cart
   - **Expected:** ✅ Discount now applies

**Pass Criteria:**
- ✅ Regular price items: Discount applies
- ❌ Sale items: Discount blocked
- ✅ After removing sale: Discount applies

---

### Test 8: No Coupons Allowed

**Setup:**
1. Find Campaign: "TEST: No Coupons"
2. Create a WooCommerce coupon code: "TEST10"

**Test Steps:**
1. Add product to cart
2. **Expected:** ✅ 30% discount from campaign applies

3. **Try to apply coupon:**
   - Enter coupon code "TEST10"
   - Click Apply
   - **Expected:** ❌ Error: "This coupon cannot be used with the active TEST: No Coupons discount"

4. **Deactivate campaign:**
   - Disable "TEST: No Coupons" campaign
   - Try coupon again
   - **Expected:** ✅ Coupon now works

**Pass Criteria:**
- ❌ Coupon blocked when campaign active
- ✅ Clear error message shown
- ✅ Coupon works when campaign inactive

---

### Test 9: No Stacking (Campaign Priority)

**Setup:**
1. Find Campaign: "TEST: No Stacking (Priority 5)"
2. Ensure another test campaign is also active (lower priority)

**Test Steps:**
1. Add product to cart (eligible for multiple campaigns)
2. **Expected:** Only "TEST: No Stacking" applies (20% off)
3. **Verify:** Other campaigns are filtered out

**Check logs:**
1. Enable debug logging
2. Add to cart
3. **Expected:** Log shows: "Campaign stacking blocked"

**Deactivate high priority campaign:**
1. Disable "TEST: No Stacking"
2. Refresh cart
3. **Expected:** Now other campaign applies

**Pass Criteria:**
- ✅ Only highest priority non-stacking campaign applies
- ❌ Other campaigns filtered out
- ✅ After disabling: Other campaigns work

---

### Test 10: Combined Rules

**Setup:**
1. Find Campaign: "TEST: Combined Rules"
2. Test all rules together

**Test Steps:**

**Scenario A: Missing minimum order amount**
1. Add 2 items ($20 total)
2. **Expected:** ❌ Blocked - "Minimum order amount of $30 required"

**Scenario B: Missing minimum quantity**
1. Add 1 item ($35 value)
2. **Expected:** ❌ Blocked - "Minimum 2 items required"

**Scenario C: All conditions met**
1. Add 2 items ($40 total)
2. **Expected:** ✅ Discount applies
3. Calculation: 40% of $40 = $16
4. Cap: $15 maximum
5. **Verify:** Final discount = $15 (not $16)

**Scenario D: Usage limit**
1. Place 5 orders with same email
2. **Expected:** ✅ All 5 get discount
3. Place 6th order with same email
4. **Expected:** ❌ Blocked - "Usage limit exceeded"

**Pass Criteria:**
- ✅ All rules enforced independently
- ✅ All rules work together correctly
- ✅ Max discount cap applied after percentage calculation
- ✅ Usage limit tracked correctly

---

## 🔍 Verification Checklist

After running all tests, verify:

- [ ] All 10 campaigns created successfully
- [ ] Each campaign is active and visible
- [ ] Badges show on product pages
- [ ] Minimum order amount blocks correctly
- [ ] Minimum quantity enforced
- [ ] Maximum discount cap applied
- [ ] Per customer usage tracked
- [ ] Total usage limit enforced
- [ ] Lifetime cap working
- [ ] Sale items blocked when configured
- [ ] WooCommerce coupons blocked when configured
- [ ] Campaign stacking prevented
- [ ] Multiple rules work together
- [ ] Error messages are clear and user-friendly
- [ ] Admin analytics show usage correctly

---

## 🐛 Troubleshooting

### Discount Not Appearing
1. Check campaign is active (status = "active")
2. Verify start/end dates are correct
3. Check product is included in campaign
4. Clear WooCommerce caches
5. Check browser console for JavaScript errors

### Discount Not Being Blocked
1. Enable debug logging: `define('WP_DEBUG_LOG', true);`
2. Check `/wp-content/debug.log` for messages
3. Look for: `[Discount_Rules_Enforcer]` log entries
4. Verify rules are saved in campaign (`discount_rules` column)

### Coupon Blocking Not Working
1. Verify `SCD_WC_Coupon_Restriction` is loaded
2. Check hook is registered: `woocommerce_coupon_is_valid`
3. Ensure campaign has `allow_coupons = false`
4. Try different coupon code

### Usage Limits Not Tracking
1. Check database table exists: `wp_scd_customer_usage`
2. Verify orders are completing (not pending)
3. Check customer email is captured correctly
4. Review repository queries in logs

---

## 📊 Expected Results Summary

| Test | Input | Expected Output | Pass/Fail |
|------|-------|----------------|-----------|
| Min Order | Cart < $50 | ❌ Blocked | ⬜ |
| Min Order | Cart ≥ $50 | ✅ Applied | ⬜ |
| Min Qty | Qty < 3 | ❌ Blocked | ⬜ |
| Min Qty | Qty ≥ 3 | ✅ Applied | ⬜ |
| Max Cap | $100 item, 50% off | $25 off (capped) | ⬜ |
| Max Cap | $40 item, 50% off | $20 off (full) | ⬜ |
| Per Customer | Use #1-2 | ✅ Applied | ⬜ |
| Per Customer | Use #3 | ❌ Blocked | ⬜ |
| Total Usage | Use #1-10 | ✅ Applied | ⬜ |
| Total Usage | Use #11 | ❌ Blocked | ⬜ |
| Sale Items | Regular product | ✅ Applied | ⬜ |
| Sale Items | On-sale product | ❌ Blocked | ⬜ |
| Coupons | Apply coupon | ❌ Blocked | ⬜ |
| Stacking | Multiple campaigns | Only 1 applied | ⬜ |
| Combined | All rules met | ✅ Applied | ⬜ |
| Combined | Missing any rule | ❌ Blocked | ⬜ |

---

## 🎉 Success Criteria

All discount rules implementation is considered **100% functional** if:

✅ All 10 test campaigns create without errors
✅ Each rule blocks discounts when conditions not met
✅ Each rule allows discounts when conditions are met
✅ Error messages are clear and helpful
✅ Multiple rules work together correctly
✅ Usage limits track accurately
✅ Admin displays correct usage data
✅ No PHP errors in debug log
✅ No JavaScript errors in browser console
✅ Performance is acceptable (no noticeable slowdown)

---

## 📝 Clean Up

After testing, you can delete test campaigns:

1. Go to Smart Cycle Discounts → Campaigns
2. Filter by "TEST:" in name
3. Bulk select all test campaigns
4. Delete or deactivate

Or keep them for ongoing validation and demonstration purposes!
