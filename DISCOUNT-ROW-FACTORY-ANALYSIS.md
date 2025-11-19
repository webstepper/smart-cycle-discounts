# Discount Step Row Factory Migration Analysis

## Executive Summary

All three discount types have significant HTML string building duplication that can be eliminated using the Row Factory pattern. Combined, these represent approximately 250+ lines of manual HTML concatenation that can be replaced with declarative configurations.

---

## 1. TIERED DISCOUNT (`tiered-discount.js`)

### HTML String Building Location: Lines 309-351

**Method:** `renderTierRow(tier, index, tierType, mode)`

#### Field Structure Analysis:

```
1. Threshold Field (Lines 317-327)
   - Type: number
   - Name: scd-tier-threshold (class: scd-tier-input)
   - Data attrs: data-index, data-field="threshold"
   - Min: 2 (quantity) OR 0.01 (value)
   - Step: 1 (quantity) OR 0.01 (value)
   - Placeholder: "e.g., 5" or "e.g., 50.00"
   - Value: tier.quantity OR tier.value
   - Label: "Minimum Quantity" OR "Minimum Order Value"
   - Suffix: Currency symbol (for value mode)

2. Discount Value Field (Lines 332-344)
   - Type: number
   - Name: scd-tier-discount (class: scd-tier-input)
   - Data attrs: data-index, data-field="discount"
   - Min: 0
   - Step: 0.01
   - Placeholder: "e.g., 10" or "e.g., 5.00"
   - Value: tier.discount
   - Label: "Discount Value"
   - Prefix: "%" OR currency symbol (determined by mode)

3. Remove Button (Line 346)
   - Class: scd-remove-tier
   - Data attr: data-index
   - Text: "Remove"
```

#### Current Implementation Details:

- **Row selector:** `.scd-tier-row` (line 240)
- **Row data attributes:** `data-index`, `data-mode`
- **Rendered into:** `#percentage-tiers-list` or `#fixed-tiers-list` (line 291)
- **Max rows:** `this.maxTiers = 5` (line 23)
- **Button state management:** Lines 294-299 (disabled when max reached)

#### HTML String Building Code:
- **Lines 313-348:** Manual concatenation of nested divs and inputs
- **Duplication:** Threshold and discount field creation with inline HTML
- **Escaping:** Manual `data-index` and value attribute injection (vulnerable to XSS)

#### Add/Remove Implementation:
- **Add handler:** Lines 229-235 (click on `.scd-add-tier`)
  - Calls `addTier(mode)` which pushes to `_percentageTiers` or `_fixedTiers`
  - Calls `renderTiers()` to regenerate HTML
  
- **Remove handler:** Lines 237-245 (click on `.scd-remove-tier`)
  - Calls `removeTier(index, mode)` which splices array
  - Calls `renderTiers()` to regenerate HTML

#### Estimated Lines to Eliminate:
- **renderTierRow():** 42 lines (309-351)
- **Helper HTML building:** 15-20 lines
- **Total:** ~60 lines can be replaced with Row Factory config

---

## 2. BOGO DISCOUNT (`bogo-discount.js`)

### HTML String Building Location: Lines 218-304

**Method:** `renderBogoRuleRow(rule, index)`

#### Field Structure Analysis:

```
1. Preset Selector (Lines 239-250)
   - Type: select
   - Class: scd-bogo-preset-select
   - Data attr: data-index
   - Options: 5 presets (dynamic from array at line 219)
   - Value: rule.buyQuantity_rule.getQuantity_rule.discountPercent
   - Label: "Quick Select"

2. Buy Quantity Field (Lines 254-257)
   - Type: number
   - Name: buyQuantity (class: scd-bogo-input)
   - Data attrs: data-index, data-field="buyQuantity"
   - Min: 1
   - Step: 1
   - Value: rule.buyQuantity OR 1
   - Label: "Buy Quantity"

3. Get Quantity Field (Lines 259-262)
   - Type: number
   - Name: getQuantity (class: scd-bogo-input)
   - Data attrs: data-index, data-field="getQuantity"
   - Min: 1
   - Step: 1
   - Value: rule.getQuantity OR 1
   - Label: "Get Quantity"

4. Discount Percent Field (Lines 264-268)
   - Type: number
   - Name: discountPercent (class: scd-bogo-input)
   - Data attrs: data-index, data-field="discountPercent"
   - Min: 0, Max: 100
   - Step: 1
   - Value: rule.discountPercent OR 100
   - Label: "Discount on Free Items"
   - Suffix: "%"

5. Apply To Selector (Lines 270-276)
   - Type: select
   - Class: scd-bogo-apply-to
   - Data attr: data-index
   - Options: ["same", "different"]
   - Value: rule.applyTo
   - Label: "Apply To"

6. Conditional Get Products Field (Lines 278-297)
   - Type: custom (product selector)
   - Only rendered if rule.applyTo === "different"
   - Dynamic: renders selected products with remove buttons
   - Classes: scd-product-selector, scd-selected-products

7. Remove Button (Line 299)
   - Class: scd-remove-bogo-rule
   - Data attr: data-index
   - Text: "Remove Rule"
```

#### Current Implementation Details:

- **Row selector:** `.scd-bogo-rule` (line 236)
- **Row data attribute:** `data-index`
- **Rendered into:** `.scd-bogo-rules-container` (line 190)
- **Max rows:** `this.maxRules = 5` (line 23)
- **Button state management:** Lines 204-208 (disabled when max reached)

#### HTML String Building Code:
- **Lines 236-302:** Manual HTML concatenation with 66 lines
- **Duplication:** Preset options generated dynamically (lines 243-247)
- **Conditional rendering:** Get Products field (lines 278-297)
- **XSS Risk:** Direct attribute injection without escaping

#### Add/Remove Implementation:
- **Add handler:** Lines 129-132 (click on `.scd-add-bogo-rule`)
  - Calls `addBogoRule()` which creates new rule object and pushes to config.rules
  - Calls `renderBogoRules()` to regenerate HTML
  
- **Remove handler:** Lines 134-138 (click on `.scd-remove-bogo-rule`)
  - Calls `removeBogoRule(index)` which splices config.rules
  - Calls `renderBogoRules()` to regenerate HTML

#### Estimated Lines to Eliminate:
- **renderBogoRuleRow():** 66 lines (218-304)
- **Preset option generation:** 10-15 lines
- **Conditional logic for products:** 15-20 lines
- **Total:** ~85 lines can be replaced

**Note:** Conditional field rendering (get products) may require Row Factory enhancement or handled separately.

---

## 3. SPEND THRESHOLD (`spend-threshold.js`)

### HTML String Building Location: Lines 372-441

**Method:** `renderThresholds()` (combined with inline row building)

#### Field Structure Analysis:

```
1. Spend Amount Field (Lines 404-412)
   - Type: number
   - Class: scd-threshold-input
   - Data attrs: data-field="threshold", data-index
   - Name: {mode}_spend_thresholds[{index}][threshold]
   - Min: 0.01
   - Step: 0.01
   - Value: threshold.threshold
   - Label: "Spend Amount"
   - Prefix: Currency symbol

2. Discount Value Field (Lines 415-432)
   - Type: number
   - Class: scd-threshold-input
   - Data attrs: data-field="discount_value", data-index
   - Name: {mode}_spend_thresholds[{index}][discount_value]
   - Min: 0.01
   - Step: 0.01 (percentage) OR 0.01 (fixed)
   - Max: 100 (percentage only)
   - Value: threshold.discountValue
   - Label: "Discount Value"
   - Prefix: "%" OR currency symbol (based on mode)

3. Remove Button (Line 434)
   - Class: scd-remove-threshold
   - No data attr (found via parent .scd-threshold-row)
   - Text: "Remove"
```

#### Current Implementation Details:

- **Row selector:** `.scd-threshold-row` (line 206)
- **Row data attributes:** `data-index`, `data-threshold-type`
- **Rendered into:** `#percentage-thresholds-list` or `#fixed-thresholds-list` (line 380)
- **Max rows:** `this.maxThresholds = 5` (line 23)
- **Sorting:** Thresholds sorted before rendering (lines 391-393)
- **Index mapping:** Original index tracked separately from sorted display index (line 398)

#### HTML String Building Code:
- **Lines 372-441:** Combined renderThresholds() method (~70 lines)
- **Inline row building:** Lines 400-438 (38 lines of HTML concatenation)
- **Complex escaping:** Custom escapeAttr() method (lines 357-367) for attribute safety
- **Dynamic names:** Name attributes include mode and index

#### Add/Remove Implementation:
- **Add handler:** Lines 198-202 (click on `.scd-add-threshold`)
  - Calls `addThreshold(thresholdType)` which creates new threshold and pushes to array
  - Calls `renderThresholds()` to regenerate HTML
  
- **Remove handler:** Lines 204-210 (click on `.scd-remove-threshold`)
  - Calls `removeThreshold(index, thresholdType)` which splices array
  - Calls `renderThresholds()` to regenerate HTML

#### Estimated Lines to Eliminate:
- **renderThresholds() HTML building:** 38 lines (400-438)
- **escapeAttr() workaround:** 11 lines (357-367)
- **Sort/index logic:** 9 lines (391-399)
- **Total:** ~45-55 lines can be replaced

---

## Row Factory Configuration Templates

### Tiered Discount Configuration

```javascript
{
    rowClass: 'scd-tier-row',
    dataAttributes: {
        index: '{index}',
        mode: '{mode}'
    },
    fields: [
        {
            name: 'threshold',
            label: '{thresholdLabel}',
            type: 'number',
            class: 'scd-tier-input scd-tier-threshold',
            min: '{thresholdMin}',
            step: '{thresholdStep}',
            placeholder: '{thresholdPlaceholder}',
            wrapperClass: 'scd-field-group'
        },
        {
            name: 'discount',
            label: 'Discount Value',
            type: 'number',
            class: 'scd-tier-input scd-tier-discount',
            min: 0,
            step: 0.01,
            placeholder: '{discountPlaceholder}',
            prefix: '{discountPrefix}',
            wrapperClass: 'scd-field-group'
        }
    ],
    removeButton: {
        enabled: true,
        label: 'Remove',
        class: 'scd-remove-tier'
    }
}
```

### BOGO Discount Configuration

```javascript
{
    rowClass: 'scd-bogo-rule',
    dataAttributes: {
        index: '{index}'
    },
    fields: [
        {
            name: 'preset',
            label: 'Quick Select',
            type: 'select',
            class: 'scd-bogo-preset-select',
            options: {
                '1_1_100': 'Buy 1 Get 1 Free',
                '2_1_100': 'Buy 2 Get 1 Free',
                '1_1_50': 'Buy 1 Get 1 at 50% Off',
                '3_1_100': 'Buy 3 Get 1 Free',
                'custom': 'Custom'
            }
        },
        {
            name: 'buyQuantity',
            label: 'Buy Quantity',
            type: 'number',
            class: 'scd-bogo-input',
            min: 1,
            step: 1
        },
        {
            name: 'getQuantity',
            label: 'Get Quantity',
            type: 'number',
            class: 'scd-bogo-input',
            min: 1,
            step: 1
        },
        {
            name: 'discountPercent',
            label: 'Discount on Free Items',
            type: 'number',
            class: 'scd-bogo-input',
            min: 0,
            max: 100,
            step: 1,
            suffix: '%'
        },
        {
            name: 'applyTo',
            label: 'Apply To',
            type: 'select',
            class: 'scd-bogo-apply-to',
            options: {
                'same': 'Same Product',
                'different': 'Different Products'
            }
        }
    ],
    removeButton: {
        enabled: true,
        label: 'Remove Rule',
        class: 'scd-remove-bogo-rule'
    }
}
```

### Spend Threshold Configuration

```javascript
{
    rowClass: 'scd-threshold-row',
    dataAttributes: {
        index: '{index}',
        thresholdType: '{mode}'
    },
    fields: [
        {
            name: 'threshold',
            label: 'Spend Amount',
            type: 'number',
            class: 'scd-threshold-input',
            min: 0.01,
            step: 0.01,
            placeholder: '100.00',
            prefix: '{currencySymbol}',
            wrapperClass: 'scd-field-group'
        },
        {
            name: 'discountValue',
            label: 'Discount Value',
            type: 'number',
            class: 'scd-threshold-input',
            min: 0.01,
            step: 0.01,
            max: '{maxDiscount}',
            placeholder: '{discountPlaceholder}',
            prefix: '{discountPrefix}',
            wrapperClass: 'scd-field-group'
        }
    ],
    removeButton: {
        enabled: true,
        label: 'Remove',
        class: 'scd-remove-threshold'
    }
}
```

---

## Implementation Complexity Matrix

| Feature | Tiered | BOGO | Spend Threshold |
|---------|--------|------|-----------------|
| Basic field rendering | ✓ Easy | ✓ Easy | ✓ Easy |
| Conditional fields | - | Complex* | - |
| Dynamic field names | - | - | Yes (indexed array syntax) |
| Custom escaping | - | - | Yes (handled by RF) |
| Sorting logic | Simple | N/A | Simple (pre-render) |
| Max row enforcement | Lines 294-299 | Lines 204-208 | Auto (UI logic) |
| XSS vulnerability | Potential | High (presets) | Mitigated (escapeAttr) |

*BOGO has conditional "Get Products" field that only renders for "different" apply-to value

---

## Benefits of Row Factory Migration

1. **Code Reduction:** ~190 total lines of HTML string building eliminated
2. **Security:** Unified HTML escaping prevents XSS attacks
3. **Maintainability:** Declarative configs easier to understand and modify
4. **DRY Principle:** Removes duplicate field creation patterns
5. **Consistency:** All rows use same generation mechanism
6. **Extensibility:** Easy to add new field types or attributes
7. **Testing:** Row Factory is testable independent of discount logic

---

## Implementation Order (Recommended)

1. **Tiered Discount** (Easiest)
   - Simplest field structure
   - No conditional fields
   - No dynamic naming complexity
   
2. **Spend Threshold** (Medium)
   - Dynamic field name handling
   - Similar to tiered (parallel implementation)
   - Already has escaping utilities
   
3. **BOGO Discount** (Complex)
   - Requires conditional field support in Row Factory
   - Complex preset logic
   - Dynamic product selector

---

## Known Challenges

1. **BOGO Conditional Fields**
   - The `.scd-get-products` field only renders when `applyTo === 'different'`
   - Row Factory needs enhancement or custom rendering logic
   - Workaround: Render all fields, use CSS to hide/show

2. **Dynamic Field Names (Spend Threshold)**
   - Name attribute includes mode and index: `percentage_spend_thresholds[0][threshold]`
   - Row Factory can support via template variables
   - Needs interpolation in config

3. **Preset Synchronization (BOGO)**
   - Preset dropdown must auto-detect and select matching values
   - Requires handler logic during setValue()
   - Separate from Row Factory, handled in updateBogoRuleFromInput()

---

## Testing Checklist

- [ ] Rows render correctly with all field values
- [ ] Add/Remove buttons work and trigger state updates
- [ ] Field change events properly update internal state
- [ ] Input values persist when switching discount modes
- [ ] Remove button disables when max rows reached
- [ ] Form submission sends correct data to backend
- [ ] XSS prevention (no script injection via field values)
- [ ] Accessibility (ARIA labels, form semantics)
- [ ] Mobile responsiveness (if applicable)
