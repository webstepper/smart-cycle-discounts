# Tom-Select Integration Tests

Manual test suite to verify Tom-Select fixes for race condition and click handler functionality.

## 🎯 What's Being Tested

### Issues Fixed
1. **Race Condition**: `can't access property "filter", t.items is undefined` error
   - Caused by: `openOnFocus: true` + `preload: true` combination
   - Fixed by: Setting `openOnFocus: false` by default

2. **Tom-Select Bug #701**: Dropdowns not opening with `openOnFocus: false`
   - Caused by: Library bug where `openOnFocus: false` disables ALL opening
   - Fixed by: Custom `onClick` handler that manually opens dropdown

### Test Coverage
- ✅ Prerequisites (jQuery, SCD namespace, Tom-Select library)
- ✅ Configuration (default values, onClick handler, validation)
- ✅ Race condition prevention
- ✅ Product search functionality
- ✅ Category filter functionality
- ✅ Integration between components

---

## 🚀 How to Run Tests

### Method 1: In Campaign Wizard (Recommended)

This method tests the actual implementation in the live environment.

1. **Navigate to Products Step**
   ```
   WordPress Admin → Smart Cycle Discounts → Add New Campaign → Products Step
   ```

2. **Open Browser Console**
   - Press `F12` (or `Cmd+Option+I` on Mac)
   - Go to the "Console" tab

3. **Load Test Script**
   - Copy the entire contents of `tom-select-integration-test.js`
   - Paste into console and press Enter

4. **Run Tests**
   ```javascript
   runTomSelectTests()
   ```

5. **Watch Results**
   - Tests will run automatically
   - Results displayed in console with color coding:
     - 🟢 Green = Passed
     - 🔴 Red = Failed
   - Final summary shows total passed/failed

---

### Method 2: Using Test Runner HTML

This method provides a visual interface for running tests.

1. **Open Test Runner**
   ```
   Open: tests/manual/tom-select-test-runner.html in browser
   ```

2. **Load Script**
   - Click "1. Load Test Script" button

3. **Run Tests**
   - Click "2. Run Tests" button
   - Open console (F12) to see detailed output

4. **Review Results**
   - Results displayed both in console and on page
   - Summary shows passed/failed counts

---

## 📋 Manual Verification Checklist

After automated tests pass, verify these manually:

### Product Search Field
- [ ] Click on field → Dropdown opens
- [ ] Start typing → Search works
- [ ] Select products → Saves correctly
- [ ] Focus field (without click) → Dropdown stays closed
- [ ] No console errors

### Category Filter
- [ ] Click on field → Dropdown opens
- [ ] Select category → Multi-select works
- [ ] Select "All Categories" → Other categories deselected
- [ ] Select specific category → "All Categories" removed
- [ ] No console errors

### Integration
- [ ] Select category → Product list filters correctly
- [ ] Select products → Count updates
- [ ] Both dropdowns work simultaneously
- [ ] No race condition errors in console

---

## ✅ Expected Test Results

All tests should pass with output similar to:

```
=== PREREQUISITES TESTS ===
✓ PASS: jQuery is loaded
✓ PASS: SCD namespace exists
✓ PASS: TomSelectBase exists
✓ PASS: Tom-Select library is loaded
✓ PASS: Product search element exists
✓ PASS: Category filter element exists

=== CONFIGURATION TESTS ===
✓ PASS: TomSelectBase has getDefaultConfig method
✓ PASS: Default config has openOnFocus: false
✓ PASS: Default config has preload: false
✓ PASS: Default config has onClick handler
✓ PASS: Configuration validation warns about dangerous combination

=== RACE CONDITION TESTS ===
✓ PASS: No console errors related to "items is undefined"
✓ PASS: Click handler prevents opening during preload

=== PRODUCT SEARCH TESTS ===
✓ PASS: Product search Tom-Select instance exists
✓ PASS: Product search has correct configuration
✓ PASS: Product search items array is initialized
✓ PASS: Product search opens on click
✓ PASS: Product search does not auto-open on focus

=== CATEGORY FILTER TESTS ===
✓ PASS: Category filter Tom-Select instance exists
✓ PASS: Category filter has correct configuration
✓ PASS: Category filter items array is initialized
✓ PASS: Category filter opens on click
✓ PASS: Category filter has "All Categories" option

=== INTEGRATION TESTS ===
✓ PASS: Product search and category filter work together

=== TEST SUMMARY ===
Total Tests: 22
✓ Passed: 22
✗ Failed: 0

🎉 ALL TESTS PASSED! 🎉
```

---

## 🐛 Troubleshooting

### Tests Fail to Load
**Problem**: "Test function not found"
**Solution**: Make sure you copied the entire `tom-select-integration-test.js` file

### Tom-Select Not Initialized
**Problem**: "Tom-Select instance not initialized"
**Solution**:
- Make sure you're on the Products step
- Check if product selection type is "Specific Products"
- Try refreshing the page

### Console Errors
**Problem**: Race condition errors still appear
**Solution**:
- Check if changes were saved to `tom-select-base.js`
- Clear browser cache
- Verify `openOnFocus: false` in config

### Dropdowns Don't Open
**Problem**: Clicking fields doesn't open dropdown
**Solution**:
- Check if `onClick` handler was added
- Verify `_handleClick` method exists in base class
- Check console for JavaScript errors

---

## 📁 Test Files

- **tom-select-integration-test.js**: Main test suite
- **tom-select-test-runner.html**: Visual test runner interface
- **README.md**: This documentation file

---

## 🔗 Related Documentation

- [Tom-Select Bug #701](https://github.com/orchidjs/tom-select/issues/701)
- [Smart Cycle Discounts CLAUDE.md](../../CLAUDE.md)
- [Tom-Select Documentation](https://tom-select.js.org/)

---

## 📝 Notes

- Tests use Promise-based async execution
- Console output is color-coded for readability
- Each test is independent and can be run separately
- Test results are programmatically accessible via return value

---

## 🤝 Contributing

If you add new Tom-Select functionality:

1. Add corresponding tests to `tom-select-integration-test.js`
2. Update manual verification checklist in this README
3. Run full test suite before committing
4. Document any new configuration options or behaviors

---

**Created**: 2025-01-22
**Last Updated**: 2025-01-22
**Version**: 1.0.0
