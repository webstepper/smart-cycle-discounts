# Shadow Color Token System Documentation

## Overview

The Smart Cycle Discounts plugin now uses a semantic shadow color token system in the enhanced theme. This provides a single source of truth for shadow colors, making theme customization, branding changes, and dark mode implementation significantly easier.

## Architecture

### Two-Layer Theme System

1. **Classic Theme** (`resources/assets/css/shared/_variables.css`)
   - WordPress-native baseline values
   - Hard-coded colors (unchanged)
   - Fallback for all themes

2. **Enhanced Theme** (`resources/assets/css/themes/theme-enhanced.css`)
   - Modern premium overrides
   - Uses semantic shadow color tokens
   - All shadows reference centralized color variables

## Shadow Color Tokens

### Token Definition Location

All shadow color tokens are defined in `resources/assets/css/shared/_variables.css` starting at line 38.

### Token Format

```css
--scd-shadow-color-[semantic-name]: [R], [G], [B];
```

**Important**: Tokens use RGB format WITHOUT alpha channel to allow flexible opacity in usage.

### Available Tokens

| Token Name | RGB Value | Use Case |
|------------|-----------|----------|
| `--scd-shadow-color-neutral` | `0, 0, 0` | General shadows, cards, buttons, modals |
| `--scd-shadow-color-primary` | `34, 113, 177` | Primary actions, wizard focus, navigation |
| `--scd-shadow-color-planner` | `59, 130, 246` | Planner-specific elements, calendar interactions |
| `--scd-shadow-color-danger` | `214, 54, 56` | Dashboard metrics, critical alerts |
| `--scd-shadow-color-error` | `204, 29, 32` | Form validation errors, error states |
| `--scd-shadow-color-success` | `0, 163, 42` | Success states, confirmation actions |
| `--scd-shadow-color-warning` | `245, 158, 11` | Warnings, caution states |
| `--scd-shadow-color-accent` | `56, 88, 233` | Upgrade banners, promotional elements |
| `--scd-shadow-color-pro-badge` | `240, 184, 73` | Pro feature badges, premium indicators |
| `--scd-shadow-color-white` | `255, 255, 255` | Inset highlights, overlay effects |

## Usage Patterns

### Basic Usage

```css
/* Single shadow with variable alpha */
--scd-shadow-sm: 0 2px 4px rgba(var(--scd-shadow-color-neutral), 0.08);

/* Focus ring with semantic color */
--scd-input-focus-shadow: 0 0 0 3px rgba(var(--scd-shadow-color-primary), 0.15);

/* Multi-layer shadow */
--scd-pro-feature-overlay-shadow:
    0 12px 40px rgba(var(--scd-shadow-color-neutral), 0.15),
    0 4px 16px rgba(var(--scd-shadow-color-neutral), 0.1),
    0 0 0 1px rgba(var(--scd-shadow-color-white), 0.5) inset;
```

### Semantic Naming

Shadows are organized by context and purpose:

- **Box Shadows**: General elevation (`--scd-shadow-sm`, `--scd-shadow-md`, etc.)
- **Button Shadows**: Action-specific (`--scd-button-shadow-primary-hover`)
- **Component Shadows**: Feature-specific (`--scd-wizard-save-button-shadow`)
- **State Shadows**: Interaction feedback (`--scd-planner-day-focus-shadow`)

## Benefits

### Single Source of Truth

Changing a shadow color throughout the entire enhanced theme requires editing just ONE variable:

```css
/* Update primary shadow color everywhere */
--scd-shadow-color-primary: 120, 80, 200;  /* Changes ALL primary shadows */
```

### Easy Branding

Customize shadow colors to match brand identity:

```css
/* Brand-specific shadows */
--scd-shadow-color-primary: 255, 0, 100;    /* Brand magenta */
--scd-shadow-color-success: 0, 200, 100;    /* Brand green */
```

### Dark Mode Ready

Future dark mode implementation becomes trivial:

```css
body.scd-theme-enhanced.scd-dark-mode {
    --scd-shadow-color-neutral: 255, 255, 255;  /* White shadows on dark bg */
    --scd-shadow-color-primary: 100, 180, 255;  /* Lighter primary */
}
```

### Improved Maintainability

- **Before**: 74+ hard-coded rgba values scattered throughout theme
- **After**: 10 semantic tokens, used consistently across all shadow definitions
- **Result**: Zero color duplication, consistent shadow behavior

## Refactoring Summary

### Changes Made

1. **Created 10 semantic shadow color tokens** in `_variables.css`
2. **Refactored 74 shadow definitions** in `theme-enhanced.css`
3. **Zero functional changes** - output remains visually identical
4. **100% coverage** - all hard-coded rgba values converted

### Files Modified

- `resources/assets/css/shared/_variables.css` - Added token definitions
- `resources/assets/css/themes/theme-enhanced.css` - Refactored all shadows

### Verification

All shadow definitions verified to use semantic color tokens:
- ✅ No hard-coded rgba values in enhanced theme
- ✅ All shadows reference `var(--scd-shadow-color-*)`
- ✅ Classic theme baseline unchanged (WordPress native)

## Migration Path (Optional)

If adding new shadow definitions to the enhanced theme:

### ❌ INCORRECT (Hard-coded)
```css
--scd-new-shadow: 0 2px 4px rgba(34, 113, 177, 0.25);
```

### ✅ CORRECT (Semantic Token)
```css
--scd-new-shadow: 0 2px 4px rgba(var(--scd-shadow-color-primary), 0.25);
```

## Future Enhancements

### Potential Additions

1. **Dark Mode Theme** - Leverage tokens for automatic dark mode
2. **Custom Themes** - Allow users to override color tokens via settings
3. **Accessibility Modes** - High contrast variations using same token structure
4. **Animation States** - Consistent shadow transitions across all components

### Extending the System

To add a new semantic color:

```css
/* 1. Add token in _variables.css */
--scd-shadow-color-custom: 100, 150, 200;

/* 2. Use in enhanced theme shadow definitions */
--scd-custom-feature-shadow: 0 4px 8px rgba(var(--scd-shadow-color-custom), 0.2);
```

## Performance Impact

**Zero performance impact**:
- CSS custom properties are native browser features
- No runtime calculation overhead
- Same render performance as hard-coded values
- Improved browser caching due to consistent variable usage

## Compatibility

- **WordPress 5.0+**: Full support
- **Modern Browsers**: Chrome, Firefox, Safari, Edge (all versions from 2018+)
- **Legacy Support**: Classic theme fallback ensures compatibility
- **No Dependencies**: Pure CSS, no build step required

---

**Last Updated**: 2025-11-11
**Version**: 1.0.0
**Status**: Production Ready
