# WordPress.org Submission Testing Checklist

## Status: Pre-Submission Testing
**Plugin:** Smart Cycle Discounts
**Version:** 1.0.0
**WordPress Required:** 6.0+
**PHP Required:** 7.4+
**WooCommerce Required:** 6.0+

---

## 1. CORE FUNCTIONALITY TESTING

### Campaign Creation & Management
- [ ] **Create new campaign via wizard**
  - [ ] Basic step: Name, description validation
  - [ ] Products step: All selection types work
  - [ ] Discounts step: All discount types calculate correctly
  - [ ] Schedule step: Date/time selection and validation
  - [ ] Review step: Summary displays correctly
  - [ ] Campaign saves successfully

- [ ] **Edit existing campaign**
  - [ ] Load campaign data correctly
  - [ ] Modify each wizard step
  - [ ] Changes persist after save
  - [ ] Version control works (optimistic locking)

- [ ] **Campaign List Page**
  - [ ] Campaigns display in table
  - [ ] Bulk actions work (activate, deactivate, delete)
  - [ ] Quick edit functionality
  - [ ] Search and filters work
  - [ ] Pagination works

- [ ] **Campaign Status Transitions**
  - [ ] Draft → Active
  - [ ] Active → Paused
  - [ ] Scheduled activation (cron)
  - [ ] Automatic expiration

### Discount Types
- [ ] **Percentage Discount**
  - [ ] Applies correctly to cart
  - [ ] Displays on product pages
  - [ ] Min/max limits enforced

- [ ] **Fixed Amount Discount**
  - [ ] Currency formatting correct
  - [ ] Applies to cart total
  - [ ] Multi-currency support (if applicable)

- [ ] **BOGO (Buy One Get One)**
  - [ ] Quantity calculations correct
  - [ ] Free item added to cart
  - [ ] Conditions validated

- [ ] **Tiered Pricing**
  - [ ] Tier thresholds work
  - [ ] Pricing changes at breakpoints
  - [ ] Display shows all tiers

- [ ] **Spend Threshold**
  - [ ] Minimum cart value enforced
  - [ ] Discount applies when threshold met
  - [ ] Messages display correctly

### Product Selection
- [ ] **All Products**
  - [ ] Applies to entire catalog
  - [ ] Respects exclusions

- [ ] **Specific Products**
  - [ ] Product picker works
  - [ ] Selected products receive discount
  - [ ] Non-selected products don't

- [ ] **Categories**
  - [ ] Category selection works
  - [ ] All products in category get discount
  - [ ] Subcategories handled correctly

- [ ] **Tags**
  - [ ] Tag selection works
  - [ ] Tagged products get discount

- [ ] **Custom Conditions**
  - [ ] Price range filters work
  - [ ] Stock status filters work
  - [ ] Sale items inclusion/exclusion
  - [ ] Multiple conditions combine correctly (AND/OR)

---

## 2. SECURITY TESTING

### Input Validation & Sanitization
- [ ] **AJAX Handlers**
  - [ ] Nonce verification on ALL endpoints
  - [ ] Capability checks enforce permissions
  - [ ] Input sanitized before processing
  - [ ] Output escaped before display

- [ ] **SQL Queries**
  - [ ] All queries use $wpdb->prepare()
  - [ ] No raw SQL concatenation
  - [ ] Table names properly prefixed
  - [ ] No SQL injection vulnerabilities

- [ ] **XSS Prevention**
  - [ ] All user input escaped on output
  - [ ] esc_html(), esc_attr(), esc_url() used correctly
  - [ ] JavaScript variables properly escaped
  - [ ] No eval() or dangerous functions

- [ ] **CSRF Protection**
  - [ ] Forms have nonces
  - [ ] Nonces verified on submission
  - [ ] wp_verify_nonce() on all state changes

### Authentication & Authorization
- [ ] **Capability Checks**
  - [ ] Admin pages require 'manage_woocommerce'
  - [ ] AJAX actions check current_user_can()
  - [ ] No privilege escalation possible

- [ ] **Data Access Control**
  - [ ] Users can't access other user's data
  - [ ] Shop managers have appropriate permissions
  - [ ] Administrators have full access

### File Security
- [ ] **Directory Index Prevention**
  - [ ] index.php in all directories
  - [ ] No directory listing possible

- [ ] **Direct File Access Prevention**
  - [ ] All PHP files have ABSPATH check
  - [ ] defined('ABSPATH') || exit; present

---

## 3. WORDPRESS.ORG REQUIREMENTS

### Plugin Header
- [ ] **Main Plugin File** (smart-cycle-discounts.php)
  - [ ] Correct plugin header format
  - [ ] Version number matches readme.txt
  - [ ] License specified (GPL-3.0+)
  - [ ] Text domain matches slug
  - [ ] Domain Path specified

### Readme.txt
- [ ] **Required Sections Present**
  - [ ] === Plugin Name ===
  - [ ] Contributors listed
  - [ ] Tags (max 12)
  - [ ] Requires at least: 6.0
  - [ ] Tested up to: (latest WP version)
  - [ ] Stable tag: (version number)
  - [ ] License: GPL-3.0+
  - [ ] License URI

- [ ] **Content Quality**
  - [ ] Short Description (under 150 chars)
  - [ ] Long Description explains features
  - [ ] Installation instructions
  - [ ] FAQ section
  - [ ] Changelog
  - [ ] Screenshots described

### Code Quality
- [ ] **No WordPress.org Violations**
  - [ ] No phone-home code
  - [ ] No obfuscated code
  - [ ] No crypto miners
  - [ ] No tracking without consent
  - [ ] No paid upgrades in free version code

- [ ] **Third-Party Libraries**
  - [ ] All libraries GPL-compatible
  - [ ] Licenses documented
  - [ ] No unnecessary dependencies

### Internationalization (i18n)
- [ ] **Translation Ready**
  - [ ] All strings wrapped in __(), _e(), esc_html__()
  - [ ] Text domain consistent ('smart-cycle-discounts')
  - [ ] POT file generated and included
  - [ ] Numbers/dates use WordPress functions

---

## 4. COMPATIBILITY TESTING

### WordPress Versions
- [ ] **WordPress 6.0** - Minimum required version
- [ ] **WordPress 6.3** - Current stable
- [ ] **WordPress 6.4** - Latest tested
- [ ] **WordPress latest** - Bleeding edge

### PHP Versions
- [ ] **PHP 7.4** - Minimum required
- [ ] **PHP 8.0** - Common version
- [ ] **PHP 8.1** - Current stable
- [ ] **PHP 8.2** - Latest

### WooCommerce Versions
- [ ] **WooCommerce 6.0** - Minimum
- [ ] **WooCommerce 7.x** - Previous major
- [ ] **WooCommerce 8.x** - Current major
- [ ] **WooCommerce latest** - Bleeding edge

### Theme Compatibility
- [ ] **Twenty Twenty-Three** - Default block theme
- [ ] **Twenty Twenty-Two** - Previous default
- [ ] **Storefront** - Official WooCommerce theme
- [ ] **Astra** - Popular multipurpose theme
- [ ] **Popular theme of choice** - (test your market)

### Plugin Conflicts
- [ ] **WooCommerce Subscriptions**
- [ ] **WooCommerce Memberships**
- [ ] **Yoast SEO** - Common plugin
- [ ] **Contact Form 7** - Common plugin
- [ ] **Popular cache plugins** (WP Rocket, W3 Total Cache)

---

## 5. PERFORMANCE TESTING

### Database Queries
- [ ] **Query Monitor Plugin Installed**
- [ ] **Check for:**
  - [ ] No N+1 queries
  - [ ] Queries are indexed
  - [ ] No slow queries (>0.1s)
  - [ ] Total query time acceptable

### Page Load Times
- [ ] **Admin Pages**
  - [ ] Campaign list: < 2s
  - [ ] Edit campaign: < 2s
  - [ ] Analytics: < 3s

- [ ] **Frontend**
  - [ ] Product pages: < 1s added time
  - [ ] Cart page: < 1s added time
  - [ ] Checkout: No slowdown

### Asset Loading
- [ ] **CSS Files**
  - [ ] Only loaded where needed
  - [ ] Minified for production
  - [ ] No unused CSS

- [ ] **JavaScript Files**
  - [ ] Only loaded where needed
  - [ ] Dependencies correct
  - [ ] No console errors
  - [ ] No JavaScript conflicts

---

## 6. USER EXPERIENCE TESTING

### Admin Interface
- [ ] **Navigation**
  - [ ] Menu items logical
  - [ ] Breadcrumbs work
  - [ ] Back buttons functional

- [ ] **Forms**
  - [ ] Clear labels
  - [ ] Helpful tooltips
  - [ ] Error messages clear
  - [ ] Success confirmations

- [ ] **Responsiveness**
  - [ ] Works on desktop
  - [ ] Works on tablet
  - [ ] Works on mobile
  - [ ] No horizontal scroll

### Frontend Display
- [ ] **Discount Badges**
  - [ ] Display on products
  - [ ] Styling consistent
  - [ ] Mobile friendly

- [ ] **Cart Messages**
  - [ ] Discount applied message shows
  - [ ] Clear wording
  - [ ] Removal option available

- [ ] **Checkout**
  - [ ] Discount line item visible
  - [ ] Total calculates correctly
  - [ ] Order notes include discount

---

## 7. ACCESSIBILITY (WCAG 2.1 AA)

### Keyboard Navigation
- [ ] **All interactive elements**
  - [ ] Focusable via Tab key
  - [ ] Focus indicators visible
  - [ ] Logical tab order
  - [ ] Escape key closes modals

### Screen Readers
- [ ] **ARIA Attributes**
  - [ ] aria-label on icon buttons
  - [ ] aria-describedby for help text
  - [ ] aria-live for dynamic updates
  - [ ] Role attributes on custom elements

- [ ] **Semantic HTML**
  - [ ] Proper heading hierarchy (h1, h2, h3)
  - [ ] Form labels associated
  - [ ] Button vs link usage correct
  - [ ] Tables have headers

### Visual Accessibility
- [ ] **Color Contrast**
  - [ ] Text meets 4.5:1 ratio
  - [ ] Large text meets 3:1 ratio
  - [ ] Focus indicators visible
  - [ ] Error states clear

- [ ] **Text Sizing**
  - [ ] Readable at 100% zoom
  - [ ] Scales to 200% without breaking
  - [ ] No fixed pixel heights

---

## 8. ERROR HANDLING

### Graceful Degradation
- [ ] **WooCommerce Not Active**
  - [ ] Admin notice displays
  - [ ] Plugin doesn't fatal error
  - [ ] Activation hook prevents activation

- [ ] **JavaScript Disabled**
  - [ ] Forms still submit
  - [ ] Fallback UI displays
  - [ ] Core functionality works

- [ ] **Database Errors**
  - [ ] Caught and logged
  - [ ] User-friendly messages
  - [ ] No sensitive info exposed

### Edge Cases
- [ ] **Empty States**
  - [ ] No campaigns message
  - [ ] No products message
  - [ ] Helpful CTAs

- [ ] **Large Datasets**
  - [ ] Pagination works with 1000+ campaigns
  - [ ] Product picker handles 10,000+ products
  - [ ] No timeouts or memory errors

---

## 9. DATA INTEGRITY

### Activation/Deactivation
- [ ] **Plugin Activation**
  - [ ] Database tables created
  - [ ] Default options set
  - [ ] Migrations run successfully
  - [ ] No PHP errors

- [ ] **Plugin Deactivation**
  - [ ] Data preserved
  - [ ] Cron jobs removed
  - [ ] Transients cleared
  - [ ] No orphaned data

### Uninstall
- [ ] **Clean Uninstall** (via uninstall.php)
  - [ ] Database tables dropped
  - [ ] Options removed
  - [ ] Transients cleared
  - [ ] User meta cleaned
  - [ ] No traces left

### Data Export/Import
- [ ] **Campaign Export**
  - [ ] Exports as JSON
  - [ ] All fields included
  - [ ] File downloads correctly

- [ ] **Campaign Import**
  - [ ] Validates JSON structure
  - [ ] Imports successfully
  - [ ] Handles errors gracefully

---

## 10. DOCUMENTATION

### User Documentation
- [ ] **Getting Started Guide**
  - [ ] Installation steps
  - [ ] First campaign walkthrough
  - [ ] Common tasks

- [ ] **Feature Documentation**
  - [ ] Each discount type explained
  - [ ] Product selection guide
  - [ ] Analytics interpretation

### Developer Documentation
- [ ] **Hooks & Filters**
  - [ ] Documented in code
  - [ ] Examples provided
  - [ ] Use cases explained

- [ ] **Code Comments**
  - [ ] Functions documented
  - [ ] Complex logic explained
  - [ ] @param and @return tags

---

## SUBMISSION CHECKLIST

Before submitting to WordPress.org:

- [ ] All tests above passed
- [ ] No fatal errors or warnings
- [ ] readme.txt complete and validated
- [ ] Screenshots included (PNG, optimized)
- [ ] Plugin banner created (772x250px, 1544x500px)
- [ ] Plugin icon created (128x128px, 256x256px)
- [ ] License clearly stated (GPL-3.0+)
- [ ] No trademarks violated
- [ ] All third-party code properly attributed
- [ ] Version number consistent everywhere
- [ ] Changelog updated
- [ ] Git repository clean (no debug files)

---

## NOTES & ISSUES

### Issues Found:
*(Document any issues discovered during testing)*

### Resolved:
*(Track fixed issues)*

### Known Limitations:
*(Document intentional limitations)*

---

**Last Updated:** 2025-11-09
**Tested By:** _(your name)_
**Test Environment:** Local by Flywheel / WSL2
**WordPress Version:** _(version)_
**PHP Version:** _(version)_
**WooCommerce Version:** _(version)_
