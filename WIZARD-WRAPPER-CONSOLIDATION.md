# Wizard Wrapper Class Consolidation

## Summary

Consolidated wizard wrapper class naming from duplicate `.scd-wizard-wrapper` and `.scd-wizard-wrap` to a single, consistent `.scd-wizard-wrap` class throughout the codebase.

## Problem

The plugin had two wrapper classes being used inconsistently:
- **`.scd-wizard-wrap`** - Used in HTML output (PHP)
- **`.scd-wizard-wrapper`** - Referenced in CSS and JavaScript but not in actual HTML

This caused several bugs:
1. Navigation padding using CSS variables wasn't applied (fell back to hardcoded 100px)
2. Session-expired UI state didn't work (no element to target)
3. Code inconsistency and maintenance confusion

## Solution

Removed all `.scd-wizard-wrapper` references and standardized on `.scd-wizard-wrap` (WordPress convention for admin page wrappers).

## Files Modified

### CSS Files (3 files)

1. **resources/assets/css/admin/wizard-fullscreen.css**
   - Line 24: Removed `.scd-wizard-wrapper,` from reset styles
   - Line 54: Removed `.scd-wizard-wrapper,` from main wrapper styles
   - Lines 892-909: Added `.scd-wizard-wrap.session-expired` state styling

2. **resources/assets/css/admin/wizard-navigation.css**
   - Line 80: Changed `.scd-wizard-wrapper` to `.scd-wizard-wrap`
   - Line 297: Changed `.scd-wizard-wrapper` to `.scd-wizard-wrap` (mobile media query)

### JavaScript Files (2 files)

3. **resources/assets/js/wizard/wizard-orchestrator.js**
   - Line 1123: Changed `$( '.scd-wizard-wrapper' )` to `$( '.scd-wizard-wrap' )`

4. **resources/assets/js/admin/notification-service.js**
   - Line 466: Changed `.scd-wizard-wrapper` to `.scd-wizard-wrap` in selector

## Benefits

1. **Fixed Navigation Spacing**: Now properly uses CSS variables (`--scd-nav-height`, `--scd-nav-height-mobile`)
2. **Fixed Session Expired State**: Visual feedback now works when sessions expire
3. **Code Consistency**: Single source of truth for wrapper class
4. **WordPress Standards**: Follows WordPress admin `.wrap` naming convention
5. **Maintainability**: Clearer, easier to understand and maintain

## Testing Checklist

- [ ] Wizard pages load correctly
- [ ] Navigation bar has proper spacing (desktop and mobile)
- [ ] Session expiration shows visual feedback (grayed out overlay)
- [ ] All wizard steps render correctly
- [ ] No console errors related to missing elements

## Technical Details

**Before:**
- HTML: `<div class="wrap scd-wizard-wrap scd-wizard-page">`
- CSS: `.scd-wizard-wrapper, .scd-wizard-wrap { }`
- JavaScript: Mixed usage of both classes

**After:**
- HTML: `<div class="wrap scd-wizard-wrap scd-wizard-page">` (unchanged)
- CSS: `.scd-wizard-wrap { }` (consistent)
- JavaScript: `.scd-wizard-wrap` (consistent)

**Session Expired State:**
```css
.scd-wizard-wrap.session-expired {
    pointer-events: none;
    opacity: 0.6;
    filter: grayscale(0.5);
}
```

## WordPress Coding Standards Compliance

- ✅ Uses WordPress `.wrap` convention
- ✅ CSS follows BEM-like naming (`.scd-wizard-wrap`)
- ✅ No hardcoded values (uses CSS variables)
- ✅ Proper commenting and documentation
- ✅ Follows plugin's established patterns

---
**Date**: 2025-11-17
**Task**: Wizard wrapper class consolidation and cleanup
**Status**: Complete
