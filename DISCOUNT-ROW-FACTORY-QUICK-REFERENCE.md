# Discount Row Factory Migration - Quick Reference

## File Locations & Line Numbers

### Tiered Discount
- **File:** `/resources/assets/js/steps/discounts/tiered-discount.js`
- **Main method:** `renderTierRow()` - Lines 309-351
- **Container method:** `renderTierList()` - Lines 280-300
- **Add button:** `.scd-add-tier` - Line 229
- **Remove button:** `.scd-remove-tier` - Line 237
- **Render target:** `#percentage-tiers-list`, `#fixed-tiers-list` - Line 269-270

### BOGO Discount
- **File:** `/resources/assets/js/steps/discounts/bogo-discount.js`
- **Main method:** `renderBogoRuleRow()` - Lines 218-304
- **Container method:** `renderBogoRules()` - Lines 187-211
- **Add button:** `.scd-add-bogo-rule` - Line 129
- **Remove button:** `.scd-remove-bogo-rule` - Line 134
- **Render target:** `.scd-bogo-rules-container` - Line 190

### Spend Threshold
- **File:** `/resources/assets/js/steps/discounts/spend-threshold.js`
- **Main method:** `renderThresholds()` - Lines 372-441
- **HTML building:** Lines 400-438 (38 lines of concatenation)
- **Add button:** `.scd-add-threshold` - Line 198
- **Remove button:** `.scd-remove-threshold` - Line 204
- **Render target:** `#percentage-thresholds-list`, `#fixed-thresholds-list` - Line 378-380
- **Helper:** `escapeAttr()` - Lines 357-367

---

## Field Specifications by Discount Type

### Tiered Discount Fields

**Field 1: Threshold**
```javascript
{
    name: 'threshold',  // or data-field="threshold"
    type: 'number',
    label: 'Minimum Quantity' | 'Minimum Order Value',
    class: 'scd-tier-input scd-tier-threshold',
    min: 2 (quantity) | 0.01 (value),
    step: 1 (quantity) | 0.01 (value),
    placeholder: 'e.g., 5' | 'e.g., 50.00',
    value: tier.quantity | tier.value,
    dataAttributes: {
        index: index,
        field: 'threshold'
    }
}
```

**Field 2: Discount**
```javascript
{
    name: 'discount',  // or data-field="discount"
    type: 'number',
    label: 'Discount Value',
    class: 'scd-tier-input scd-tier-discount',
    min: 0,
    step: 0.01,
    placeholder: 'e.g., 10' | 'e.g., 5.00',
    value: tier.discount,
    prefix: '%' | '$' (currency symbol),
    dataAttributes: {
        index: index,
        field: 'discount'
    }
}
```

**Remove Button**
```javascript
{
    enabled: true,
    label: 'Remove',
    class: 'scd-remove-tier',
    dataAttribute: { index: index }
}
```

---

### BOGO Discount Fields

**Field 1: Preset Selector**
```javascript
{
    name: 'preset',
    type: 'select',
    label: 'Quick Select',
    class: 'scd-bogo-preset-select',
    options: {
        '1_1_100': 'Buy 1 Get 1 Free',
        '2_1_100': 'Buy 2 Get 1 Free',
        '1_1_50': 'Buy 1 Get 1 at 50% Off',
        '3_1_100': 'Buy 3 Get 1 Free',
        'custom': 'Custom'
    },
    dataAttributes: { index: index }
}
```

**Field 2: Buy Quantity**
```javascript
{
    name: 'buyQuantity',
    type: 'number',
    label: 'Buy Quantity',
    class: 'scd-bogo-input',
    min: 1,
    step: 1,
    value: rule.buyQuantity || 1,
    dataAttributes: {
        index: index,
        field: 'buyQuantity'
    }
}
```

**Field 3: Get Quantity**
```javascript
{
    name: 'getQuantity',
    type: 'number',
    label: 'Get Quantity',
    class: 'scd-bogo-input',
    min: 1,
    step: 1,
    value: rule.getQuantity || 1,
    dataAttributes: {
        index: index,
        field: 'getQuantity'
    }
}
```

**Field 4: Discount Percent**
```javascript
{
    name: 'discountPercent',
    type: 'number',
    label: 'Discount on Free Items',
    class: 'scd-bogo-input',
    min: 0,
    max: 100,
    step: 1,
    value: rule.discountPercent || 100,
    suffix: '%',
    dataAttributes: {
        index: index,
        field: 'discountPercent'
    }
}
```

**Field 5: Apply To**
```javascript
{
    name: 'applyTo',
    type: 'select',
    label: 'Apply To',
    class: 'scd-bogo-apply-to',
    options: {
        'same': 'Same Product',
        'different': 'Different Products'
    },
    value: rule.applyTo,
    dataAttributes: { index: index }
}
```

**Field 6: Get Products (CONDITIONAL)**
```javascript
// Only render if rule.applyTo === 'different'
{
    name: 'getProducts',
    type: 'custom',  // Custom product selector
    label: 'Free Products',
    class: 'scd-product-selector',
    conditional: true,
    condition: 'applyTo === "different"',
    value: rule.getProducts || []
}
```

**Remove Button**
```javascript
{
    enabled: true,
    label: 'Remove Rule',
    class: 'scd-remove-bogo-rule',
    dataAttribute: { index: index }
}
```

---

### Spend Threshold Fields

**Field 1: Spend Amount**
```javascript
{
    name: 'threshold',
    type: 'number',
    label: 'Spend Amount',
    class: 'scd-threshold-input',
    min: 0.01,
    step: 0.01,
    placeholder: '100.00',
    value: threshold.threshold,
    prefix: currencySymbol,
    // Dynamic name: percentage_spend_thresholds[0][threshold]
    dynamicName: function(mode, index) {
        return mode + '_spend_thresholds[' + index + '][threshold]';
    },
    dataAttributes: {
        index: index,
        field: 'threshold'
    }
}
```

**Field 2: Discount Value**
```javascript
{
    name: 'discountValue',
    type: 'number',
    label: 'Discount Value',
    class: 'scd-threshold-input',
    min: 0.01,
    step: 0.01,
    max: 100 (percentage) | unlimited (fixed),
    placeholder: '10' | '5.00',
    value: threshold.discountValue,
    prefix: '%' | currencySymbol,
    // Dynamic name: percentage_spend_thresholds[0][discount_value]
    dynamicName: function(mode, index) {
        return mode + '_spend_thresholds[' + index + '][discount_value]';
    },
    dataAttributes: {
        index: index,
        field: 'discount_value'
    }
}
```

**Remove Button**
```javascript
{
    enabled: true,
    label: 'Remove',
    class: 'scd-remove-threshold',
    dataAttribute: { index: index }
}
```

---

## Current HTML Output Examples

### Tiered Discount
```html
<div class="scd-tier-row" data-index="0" data-mode="percentage">
  <div class="scd-tier-fields">
    <div class="scd-field-group">
      <label>Minimum Quantity:</label>
      <input type="number" class="scd-tier-input scd-tier-threshold"
             data-index="0" data-field="threshold"
             value="5" min="2" step="1" placeholder="e.g., 5">
    </div>
    
    <div class="scd-field-group">
      <label>Discount Value:</label>
      <div class="scd-input-with-prefix">
        <span class="scd-input-prefix">%</span>
        <input type="number" class="scd-tier-input scd-tier-discount"
               data-index="0" data-field="discount"
               value="10" min="0" step="0.01" placeholder="e.g., 10">
      </div>
    </div>
    
    <button type="button" class="scd-remove-tier" data-index="0">Remove</button>
  </div>
</div>
```

### BOGO Discount
```html
<div class="scd-bogo-rule" data-index="0">
  <h4>BOGO Rule 1</h4>
  
  <div class="scd-bogo-preset">
    <label>Quick Select:</label>
    <select class="scd-bogo-preset-select" data-index="0">
      <option value="1_1_100" selected>Buy 1 Get 1 Free</option>
      <option value="2_1_100">Buy 2 Get 1 Free</option>
      <!-- ... more options ... -->
    </select>
  </div>
  
  <div class="scd-bogo-fields">
    <div class="scd-field-group">
      <label>Buy Quantity:</label>
      <input type="number" class="scd-bogo-input" data-index="0"
             data-field="buyQuantity" value="1" min="1" step="1">
    </div>
    <!-- ... more fields ... -->
  </div>
  
  <button type="button" class="scd-remove-bogo-rule" data-index="0">Remove Rule</button>
</div>
```

### Spend Threshold
```html
<div class="scd-threshold-row" data-index="0" data-threshold-type="percentage">
  <div class="scd-threshold-fields">
    <div class="scd-field-group">
      <label>Spend Amount:</label>
      <div class="scd-input-with-prefix">
        <span class="scd-input-prefix">$</span>
        <input type="number" class="scd-threshold-input" data-field="threshold"
               name="percentage_spend_thresholds[0][threshold]"
               value="50" min="0.01" step="0.01" placeholder="100.00">
      </div>
    </div>
    <!-- ... more fields ... -->
    <button type="button" class="scd-remove-threshold">Remove</button>
  </div>
</div>
```

---

## Event Handlers to Preserve

### Tiered Discount
- `.scd-add-tier` click → `addTier(tierType)`
- `.scd-remove-tier` click → `removeTier(index, mode)`
- `[name="tier_mode"]` change → mode switching
- `[name="apply_to"]` change → apply-to toggle
- `.scd-tier-input` change → `updateTierFromInput()`

### BOGO Discount
- `.scd-add-bogo-rule` click → `addBogoRule()`
- `.scd-remove-bogo-rule` click → `removeBogoRule(index)`
- `.scd-bogo-input` change → `updateBogoRuleFromInput()`
- `.scd-bogo-apply-to` change → `handleApplyToChange()`
- `.scd-bogo-preset-select` change → `setupPresetHandler()`

### Spend Threshold
- `.scd-add-threshold` click → `addThreshold(thresholdType)`
- `.scd-remove-threshold` click → `removeThreshold(index, thresholdType)`
- `.scd-threshold-input` change → `updateThreshold()`
- `[name="threshold_mode"]` change → mode switching

---

## Row Factory Integration Points

### RowFactory.create()
```javascript
// After Row Factory migration:
var $row = SCD.Shared.RowFactory.create(config, data, index);
$container.append($row);

// Instead of:
var html = self.renderTierRow(tier, index, tierType, mode);
$container.html(html);
```

### Data Sync Pattern
```javascript
// Collect data from rendered rows
var rowData = SCD.Shared.RowFactory.collectData($container, '.scd-tier-row');

// Update state
this.state.setState({ percentageTiers: rowData });
```

### Reindex After Remove
```javascript
// Update indices after removing row
SCD.Shared.RowFactory.reindex($container, '.scd-tier-row');
```

