# SVG Icon CSS Fixes - Icons Not Appearing

## Problem

Icons in `.scd-callout-icon` containers (and other locations) were not appearing.

## Root Cause

**CSS was overriding inline styles from `Icon_Helper::get()`**

The `SCD_Icon_Helper::get()` method generates SVG icons with inline styles:
```php
style="display:inline-block;vertical-align:middle;flex-shrink:0;width:16px;height:16px"
```

But CSS rules were overriding these inline styles with hardcoded values:

```css
/* WRONG - This overrides inline styles! */
.scd-icon {
    width: 20px;   /* ❌ Overrides inline width */
    height: 20px;  /* ❌ Overrides inline height */
}

.scd-callout-icon .scd-icon {
    width: var(--scd-icon-medium);   /* ❌ Overrides inline width */
    height: var(--scd-icon-medium);  /* ❌ Overrides inline height */
    font-size: var(--scd-icon-medium); /* ❌ Doesn't apply to SVG (Dashicons leftover) */
}
```

**Result**: Icons that should be 16px were forced to 20px, or completely hidden.

## Dashicons Legacy Issues

These CSS rules were leftovers from when the plugin used WordPress Dashicons (icon fonts):

1. **`font-size`** - Icon fonts use font-size, but SVG doesn't
2. **Hardcoded sizes** - Dashicons needed CSS sizes, but SVG uses inline styles
3. **Nested selectors** - `.scd-icon svg` assumed a wrapper, but the SVG IS the icon

## Files Fixed

### 1. `/resources/assets/css/shared/_utilities.css`

**Before** (lines 525-546):
```css
.scd-icon {
    display: inline-block;
    vertical-align: middle;
    width: 20px;   /* ❌ Hardcoded - overrides inline styles */
    height: 20px;  /* ❌ Hardcoded - overrides inline styles */
    flex-shrink: 0;
    fill: currentColor;
    transition: transform var(--scd-transition-base) var(--scd-ease-in-out);
}

/* Wrong structure - SVG IS the icon, not inside it */
.scd-icon svg {
    width: 100%;
    height: 100%;
    display: block;
}

.scd-icon {
    align-self: center;
}
```

**After**:
```css
.scd-icon {
    display: inline-block;
    vertical-align: middle;
    /* Size set by Icon_Helper inline styles (default 20px) - don't override */
    flex-shrink: 0;
    fill: currentColor;
    /* Smooth rotation for collapse/expand animations */
    transition: transform var(--scd-transition-base) var(--scd-ease-in-out);
    /* Fix alignment in flexbox containers */
    align-self: center;
}
```

**Changes**:
- ✅ Removed `width: 20px` and `height: 20px` (let inline styles work)
- ✅ Removed nested `.scd-icon svg` selector (wrong structure)
- ✅ Consolidated duplicate `.scd-icon` rules

---

**Before** (lines 556-575):
```css
/* Small dashicons (16px) */
.scd-icon-sm,
.scd-icon--sm .scd-icon {
    width: var(--scd-icon-small);
    height: var(--scd-icon-small);
    font-size: var(--scd-icon-small);  /* ❌ Doesn't apply to SVG */
}

/* Medium dashicons (20px) */
.scd-icon-md,
.scd-icon--md .scd-icon {
    width: var(--scd-icon-medium);
    height: var(--scd-icon-medium);
    font-size: var(--scd-icon-medium);  /* ❌ Doesn't apply to SVG */
}

/* Large dashicons (24px) */
.scd-icon-lg,
.scd-icon--lg .scd-icon {
    width: var(--scd-icon-large);
    height: var(--scd-icon-large);
    font-size: var(--scd-icon-large);  /* ❌ Doesn't apply to SVG */
}
```

**After**:
```css
/* Small SVG icons (16px) - use Icon_Helper with size=>16 instead */
.scd-icon-sm,
.scd-icon--sm .scd-icon {
    width: var(--scd-icon-small);
    height: var(--scd-icon-small);
}

/* Medium SVG icons (20px) - default size */
.scd-icon-md,
.scd-icon--md .scd-icon {
    width: var(--scd-icon-medium);
    height: var(--scd-icon-medium);
}

/* Large SVG icons (24px) */
.scd-icon-lg,
.scd-icon--lg .scd-icon {
    width: var(--scd-icon-large);
    height: var(--scd-icon-large);
}
```

**Changes**:
- ✅ Removed `font-size` from all size modifier classes (doesn't apply to SVG)
- ✅ Updated comments to reflect SVG usage

### 2. `/resources/assets/css/admin/wizard-sidebar-components.css`

**Before** (lines 59-66):
```css
/* Icon containers - dashicon sizing (20px) */
.scd-list-icon .scd-icon,
.scd-tip-icon .scd-icon,
.scd-callout-icon .scd-icon {
    width: var(--scd-icon-medium);   /* ❌ Overrides inline styles */
    height: var(--scd-icon-medium);  /* ❌ Overrides inline styles */
    font-size: var(--scd-icon-medium); /* ❌ Doesn't apply to SVG */
}
```

**After**:
```css
/* Icons inherit size from inline styles (Icon_Helper generates width/height) */
.scd-list-icon .scd-icon,
.scd-tip-icon .scd-icon,
.scd-callout-icon .scd-icon {
    /* Size set by Icon_Helper inline styles - don't override */
}
```

**Changes**:
- ✅ Removed all size overrides (let inline styles work)
- ✅ Removed `font-size` (doesn't apply to SVG)

## How Icon Sizing Works Now

### PHP (Correct Way)
```php
// Icon_Helper generates inline styles with correct size
echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) );
// Output: <svg ... style="width:16px;height:16px;...">...</svg>
```

### CSS (No Override)
```css
.scd-icon {
    /* No width/height here - respects inline styles */
    display: inline-block;
    vertical-align: middle;
    fill: currentColor;
}
```

### Result
✅ Icons display at the correct size (16px, 20px, 24px, etc.)
✅ Inline styles from `Icon_Helper::get()` are respected
✅ No CSS overrides

## Icon Sizing Best Practices

### ✅ Preferred: Use Icon_Helper with size parameter
```php
// Best - size set by PHP inline styles
echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) );
echo SCD_Icon_Helper::get( 'warning', array( 'size' => 20 ) );
echo SCD_Icon_Helper::get( 'dashboard', array( 'size' => 24 ) );
```

### ⚠️ Alternative: Use CSS modifier classes
```php
// Works, but less flexible
echo '<span class="scd-icon-sm">';
echo SCD_Icon_Helper::get( 'check' ); // Gets default 20px, but CSS forces 16px
echo '</span>';
```

**Why PHP is better**:
- Inline styles always work (no CSS conflicts)
- Single source of truth
- Easier to maintain
- Matches JavaScript implementation

## Testing

### Visual Test
1. Open wizard sidebar (Discounts step or Products step)
2. Check "Do's & Don'ts" section
3. Verify icons appear in red callout boxes (close icons, size 16px)

### Browser DevTools
1. Inspect `.scd-callout-icon .scd-icon` element
2. Check computed styles - should see `width: 16px; height: 16px`
3. Verify no CSS overrides showing in Styles panel

## Summary

**Problem**: Icons not appearing in `.scd-callout-icon` and other containers

**Cause**: CSS overriding inline styles with hardcoded sizes and Dashicons leftovers (`font-size`)

**Solution**:
- Removed hardcoded `width`/`height` from base `.scd-icon`
- Removed `font-size` from size modifier classes
- Removed nested `.scd-icon svg` selector
- Let `Icon_Helper` inline styles work correctly

**Result**: All icons now display at correct sizes ✅
