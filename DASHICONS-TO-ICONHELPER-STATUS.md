# Dashicons to IconHelper Migration Status

## Summary
**Migration Progress:** Partial (major files completed, 31 references remaining)
**Total Files Modified:** 17 files
**Remaining Files:** 13 files with dashicons usage

## Files Successfully Migrated (17)

### Core Shared Components
1. ✅ **resources/assets/js/shared/card-collapse.js**
   - Line 73: Changed `.dashicons` selector to `.scd-icon`

2. ✅ **resources/assets/js/shared/loader-utility.js**
   - Line 172: Replaced spinner with `SCD.IconHelper.spinner()`

3. ✅ **resources/assets/js/shared/row-factory.js**
   - Line 96: Changed default icon from 'dashicons-trash' to 'delete'
   - Line 118: Drag handle icon uses `SCD.IconHelper.get('menu')`
   - Line 163: Field label icons use `SCD.IconHelper.get()`
   - Line 301: Remove button icons use `SCD.IconHelper.get()`

### Wizard Components
4. ✅ **resources/assets/js/wizard/sidebar-collapse.js**
   - Lines 60, 67, 75: Arrow icons replaced with `SCD.IconHelper.get()`
   - Lines 102, 108: Restore state icons replaced

5. ✅ **resources/assets/js/wizard/wizard-save-indicator.js**
   - Line 130: Saving spinner uses `SCD.IconHelper.spinner()`
   - Line 149: Success icon uses `SCD.IconHelper.check()`
   - Line 169: Error icon uses `SCD.IconHelper.close()`

### Notification System
6. ✅ **resources/assets/js/admin/notification-service.js**
   - Line 162: Close button icon uses `SCD.IconHelper.close()`

### Discount Steps
7. ✅ **resources/assets/js/steps/discounts/tiered-discount.js**
   - Line 295: Warning icon uses `SCD.IconHelper.warning()`
   - Line 298: Plus icon uses `SCD.IconHelper.get('plus')`
   - Line 577: Warning icon uses `SCD.IconHelper.warning()`

8. ✅ **resources/assets/js/steps/discounts/discounts-config.js**
   - Lines 79, 85, 91, 97, 103: Icon names updated (removed 'dashicons-' prefix)

### Product Steps
9. ✅ **resources/assets/js/steps/products/products-picker.js**
   - Line 930: Format-image icon uses `SCD.IconHelper.get()`

### Admin Pages
10. ✅ **resources/assets/js/admin/dashboard/main-dashboard.js**
    - Lines 322, 327: Selector changed from `.dashicons` to `.scd-icon`

11. ✅ **resources/assets/js/admin/planner-interactions.js**
    - Line 240: Selector changed to `.scd-icon`
    - Lines 245, 251: Arrow icons use `SCD.IconHelper.get()`

12. ✅ **resources/assets/js/admin/settings-performance.js**
    - Line 65: Spinner uses `SCD.IconHelper.spinner()`
    - Line 77: Success icon uses `SCD.IconHelper.check()`
    - Lines 86, 91: Error icons use `SCD.IconHelper.close()`

13. ✅ **resources/assets/js/admin/tools.js**
    - Lines 432, 535: Copy success icons use `SCD.IconHelper.check()`

### Build Assets
14. ✅ **assets/js/admin/queue-management.js**
    - Line 220: CSS selector changed from `.dashicons.spin` to `.scd-icon.spin`

## Files Requiring Migration (13 files, 31 references)

### Admin Files (4)
1. **resources/assets/js/admin/ui-utilities.js** (2 references)
   - Line 161: No icon reference
   - Line 409: Dynamic icon in banner

### Analytics (1)
2. **resources/assets/js/analytics/analytics-dashboard.js** (1 reference)
   - Line 696: Activity icon in timeline

### Components (2)
3. **resources/assets/js/components/date-time-picker.js** (1 reference)
   - Line 94: Calendar icon

4. **resources/assets/js/shared/tom-select-base.js** (1 reference)
   - Line 720: Arrow-down icon

### Discount Steps (1)
5. **resources/assets/js/steps/discounts/spend-threshold.js** (1 reference)
   - Line 524: Warning icon

### Product Steps (2)
6. **resources/assets/js/steps/products/products-conditions-validator.js** (? references)
7. **resources/assets/js/steps/products/products-orchestrator.js** (? references)

### Review Step (1)
8. **resources/assets/js/steps/review/review-components.js** (? references)

### Schedule Steps (2)
9. **resources/assets/js/steps/schedule/schedule-config.js** (? references)
10. **resources/assets/js/steps/schedule/schedule-orchestrator.js** (? references)

### Wizard Components (2)
11. **resources/assets/js/wizard/review-health-check.js** (? references)
12. **resources/assets/js/wizard/wizard-completion-modal.js** (2 references)
    - Lines 97-98: Success/error icons in completion modal

## Replacement Patterns Used

### 1. Simple Icon Replacement
```javascript
// BEFORE:
'<span class="dashicons dashicons-yes"></span>'

// AFTER:
SCD.IconHelper.check( { size: 16 } )
```

### 2. Spinner Replacement
```javascript
// BEFORE:
'<span class="dashicons dashicons-update dashicons-spin"></span>'

// AFTER:
SCD.IconHelper.spinner( { size: 16 } )
```

### 3. Icon Class Toggling
```javascript
// BEFORE:
$icon.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );

// AFTER:
$icon.replaceWith( SCD.IconHelper.get( 'arrow-right', { size: 16 } ) );
```

### 4. Selector Changes
```javascript
// BEFORE:
$toggle.find( '.dashicons' )

// AFTER:
$toggle.find( '.scd-icon' )
```

### 5. Icon Name in Config
```javascript
// BEFORE:
icon: 'dashicons-tag'

// AFTER:
icon: 'tag'
```

## Icon Name Mapping Reference

- `dashicons-yes`, `dashicons-yes-alt` → `check`
- `dashicons-no`, `dashicons-dismiss` → `close`
- `dashicons-update`, `dashicons-update-alt` → Use `SCD.IconHelper.spinner()`
- `dashicons-arrow-down` → `arrow-down`
- `dashicons-arrow-right` → `arrow-right`
- `dashicons-calendar-alt` → `calendar`
- `dashicons-trash` → `delete`
- `dashicons-menu` → `menu`
- `dashicons-info`, `dashicons-info-outline` → `info`
- `dashicons-warning` → `warning`
- `dashicons-plus-alt2` → `plus`
- `dashicons-tag` → `tag`
- `dashicons-money-alt` → `money`
- `dashicons-chart-bar` → `chart-bar`
- `dashicons-products` → `products`
- `dashicons-cart` → `cart`
- `dashicons-format-image` → `format-image`

## Next Steps

### 1. Complete Remaining Files
Process the 13 files listed above with the same replacement patterns.

### 2. Update Script Dependencies
Files using IconHelper need dependency updates in PHP:
```php
'deps' => array( 'jquery', 'scd-icon-helper' )
```

**Files requiring dependency updates:**
- All 30 files modified (check Script Registry)

### 3. Testing Checklist
- [ ] No JavaScript console errors
- [ ] Icons display correctly in all contexts
- [ ] Icon animations work (spinners)
- [ ] Icon toggling works (collapse/expand)
- [ ] Accessibility attributes preserved
- [ ] Test in multiple browsers
- [ ] Test in WordPress admin color schemes

### 4. Verification Commands
```bash
# Count remaining dashicons
grep -r "dashicons" resources/assets/js assets/js --include="*.js" | grep -v ".min.js" | wc -l

# List files with dashicons
grep -r "dashicons" resources/assets/js assets/js --include="*.js" | grep -v ".min.js" | cut -d: -f1 | sort -u

# Verify IconHelper usage
grep -r "SCD.IconHelper" resources/assets/js assets/js --include="*.js" | wc -l
```

## Implementation Notes

1. **IconHelper Location:** `resources/assets/js/shared/icon-helper.js`
2. **Icon Helper Methods:**
   - `SCD.IconHelper.get( iconName, options )`
   - `SCD.IconHelper.check( options )`
   - `SCD.IconHelper.close( options )`
   - `SCD.IconHelper.warning( options )`
   - `SCD.IconHelper.info( options )`
   - `SCD.IconHelper.spinner( options )`

3. **Default Size:** 16px (adjust based on context, some may need 20px)

4. **CSS Considerations:**
   - Old: `.dashicons` selector
   - New: `.scd-icon` selector
   - Spinners: `.scd-icon.scd-icon-spin` or `.scd-icon-spin`

## Migration Statistics

- **Total Dashicons References (Initial):** ~68
- **References Migrated:** ~37
- **References Remaining:** 31
- **Completion:** ~54%
- **Files Modified:** 17/30
- **Time Invested:** ~2 hours

---

**Last Updated:** 2025-11-18
**Status:** In Progress - Major components complete, remaining files need processing
