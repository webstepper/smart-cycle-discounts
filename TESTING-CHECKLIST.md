# Theme System Testing Checklist

## Quick Visual Test

### Enhanced Theme (Default)
1. Navigate to any admin page
2. Check browser DevTools → body should have `scd-theme-enhanced`
3. Visual characteristics:
   - Input fields: **38px height**, **6px rounded corners**
   - Buttons: **32-36px height**, **subtle shadows**
   - Cards: **Soft shadows**, **8px rounded corners**
   - Spacing: **Generous** (12-16px gaps)
   - Hover effects: **Smooth animations**

### Classic Theme
1. Go to Settings → General → Admin Interface
2. Change to "Classic Theme"
3. Save and refresh
4. Check browser DevTools → body should have `scd-theme-classic`
5. Visual characteristics:
   - Input fields: **30px height**, **4px rounded corners**
   - Buttons: **30px height**, **minimal/no shadows**
   - Cards: **Flat** or **very subtle shadows**
   - Spacing: **Compact** (5-10px gaps)
   - Hover effects: **None** (instant transitions disabled)

## Comprehensive Page Testing

Test BOTH themes on these pages:

- [ ] Dashboard
- [ ] Campaigns List
- [ ] Campaign Wizard - Basic Step
- [ ] Campaign Wizard - Products Step
- [ ] Campaign Wizard - Discounts Step
- [ ] Campaign Wizard - Schedule Step
- [ ] Campaign Wizard - Review Step
- [ ] Analytics
- [ ] Settings (all tabs)
- [ ] Notifications
- [ ] Tools

## Component-Specific Tests

### Inputs & Forms
- [ ] Text inputs adapt height (38px → 30px)
- [ ] Border radius changes (6px → 4px)
- [ ] Input shadows change (soft → inset)

### Buttons
- [ ] Button height changes (32-36px → 30px)
- [ ] Button shadows removed in Classic
- [ ] Hover animations disabled in Classic

### Cards & Panels
- [ ] Card shadows change (soft → flat/minimal)
- [ ] Border radius changes (8px → 4px)
- [ ] Card hover lift disabled in Classic

### Spacing
- [ ] Gaps between elements reduce in Classic
- [ ] Padding becomes more compact
- [ ] Section spacing tightens

## Browser Compatibility
- [ ] Chrome/Edge
- [ ] Firefox  
- [ ] Safari

## Performance Check
- [ ] Only one theme CSS loads (not both)
- [ ] Theme CSS loads after variables.css
- [ ] No console errors
- [ ] Fast page load (< 100ms for theme CSS)

## Edge Cases
- [ ] Theme persists after logout/login
- [ ] Theme respects WordPress color schemes
- [ ] Theme switch works without cache clear
- [ ] Settings save properly
- [ ] Invalid theme values rejected (sanitization)

