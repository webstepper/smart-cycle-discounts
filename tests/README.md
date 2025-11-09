# Smart Cycle Discounts - Automated Tests

This directory contains automated tests for the Smart Cycle Discounts plugin.

## Test Structure

```
tests/
├── unit/                           # Unit tests (test individual functions/classes)
│   ├── test-schedule-validator.php    # Schedule validation datetime tests
│   └── test-field-collection.php      # Field validation and sanitization tests
├── integration/                    # Integration tests (test components working together)
│   └── test-campaign-creation.php     # End-to-end campaign creation tests
├── bootstrap.php                   # PHPUnit bootstrap file
└── README.md                       # This file
```

## What These Tests Do

### Unit Tests (4 tests)

**test-schedule-validator.php** - Tests the critical datetime bug fix:
1. ✅ `test_validates_datetime_not_just_date()` - Verifies time component is included in validation
2. ✅ `test_detects_past_dates()` - Ensures actual past dates are caught
3. ✅ `test_detects_inverted_dates()` - Validates start > end detection
4. ✅ `test_schedule_defaults_correct()` - Confirms defaults are '00:00' not current_time()

**test-field-collection.php** - Tests field validation and dependency handling:
1. ✅ `test_field_definitions_available()` - Verifies field definitions system is loaded
2. ✅ `test_fails_loudly_on_missing_required_field()` - Ensures missing required fields cause errors (no silent fallback)
3. ✅ `test_field_level_validation_enforced()` - Validates individual field rules work
4. ✅ `test_valid_data_passes_validation()` - Ensures valid data isn't rejected (no false positives)

### Integration Tests (3 tests)

**test-campaign-creation.php** - Tests complete campaign creation workflow:
1. ✅ `test_create_scheduled_campaign()` - Verifies scheduled campaigns are created correctly
2. ✅ `test_campaign_status_correct()` - Ensures scheduled campaigns don't activate immediately
3. ✅ `test_field_name_backward_compatibility()` - Tests old/new field name compatibility

**Total: 11 tests** (not 8 - we added 3 extra field collection tests for thoroughness)

## Prerequisites

### 1. Install PHPUnit

Using Composer (recommended):
```bash
cd /path/to/smart-cycle-discounts
composer require --dev phpunit/phpunit:^9.0
composer require --dev yoast/phpunit-polyfills
```

### 2. Install WordPress Test Library

The WordPress test suite requires the WordPress test library:

```bash
# Set environment variables
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress

# Install WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Note:** If `bin/install-wp-tests.sh` doesn't exist, create it:

```bash
mkdir -p bin
curl -o bin/install-wp-tests.sh https://raw.githubusercontent.com/wp-cli/scaffold-command/master/templates/install-wp-tests.sh
chmod +x bin/install-wp-tests.sh
```

### 3. Configure Database

The test suite needs a **separate test database**:

```bash
# Create test database (WordPress test suite will use this)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
```

**⚠️ WARNING:** The test database will be **completely wiped** before each test run. Never use your production database!

## Running Tests

### Run All Tests

```bash
cd /path/to/smart-cycle-discounts
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Run only unit tests
vendor/bin/phpunit --testsuite "Smart Cycle Discounts Unit Tests"

# Run only integration tests
vendor/bin/phpunit --testsuite "Smart Cycle Discounts Integration Tests"
```

### Run Specific Test File

```bash
# Run schedule validator tests only
vendor/bin/phpunit tests/unit/test-schedule-validator.php

# Run field collection tests only
vendor/bin/phpunit tests/unit/test-field-collection.php

# Run campaign creation tests only
vendor/bin/phpunit tests/integration/test-campaign-creation.php
```

### Run Specific Test Method

```bash
# Run only the datetime validation test
vendor/bin/phpunit --filter test_validates_datetime_not_just_date
```

### Verbose Output

```bash
# See detailed output for debugging
vendor/bin/phpunit --verbose

# See even more details
vendor/bin/phpunit --debug
```

## Expected Output

Successful test run:
```
PHPUnit 9.6.x by Sebastian Bergmann and contributors.

...........                                                       11 / 11 (100%)

Time: 00:02.345, Memory: 45.00 MB

OK (11 tests, 28 assertions)
```

Failed test example:
```
PHPUnit 9.6.x by Sebastian Bergmann and contributors.

F..........                                                       11 / 11 (100%)

Time: 00:02.123, Memory: 45.00 MB

There was 1 failure:

1) Test_Schedule_Validator::test_validates_datetime_not_just_date
Future datetime should not be rejected as past date
Failed asserting that false is true.

/path/to/test-schedule-validator.php:45

FAILURES!
Tests: 11, Assertions: 28, Failures: 1.
```

## What These Tests Prevent

These 11 tests specifically prevent the bugs we just fixed:

### 🐛 Bug #1: Scheduled Campaign Activated Immediately
**Prevented by:** `test_create_scheduled_campaign()`, `test_campaign_status_correct()`

The datetime bug where "2025-11-09 03:15" was compared as "2025-11-09 00:00" (midnight), causing scheduled campaigns to be rejected as "past dates" or activated immediately.

### 🐛 Bug #2: Field Name Mismatch After Refactoring
**Prevented by:** `test_field_name_backward_compatibility()`, field collection tests

The refactoring changed `discount_value` to `discount_value_percentage`/`discount_value_fixed`, breaking validation.

### 🐛 Bug #3: Silent Fallback Hiding Errors
**Prevented by:** `test_fails_loudly_on_missing_required_field()`

Defensive fallbacks that silently hide missing dependencies instead of failing with clear errors.

### 🐛 Bug #4: Schedule Defaults Using current_time()
**Prevented by:** `test_schedule_defaults_correct()`

Schedule step defaults changed from '00:00' to `current_time('H:i')` during refactoring, causing inconsistent behavior.

## Continuous Integration

These tests should be run:
- ✅ Before every commit
- ✅ Before every pull request merge
- ✅ After every refactoring
- ✅ Before every release

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'

      - name: Install dependencies
        run: composer install

      - name: Install WordPress test suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Run tests
        run: vendor/bin/phpunit
```

## Troubleshooting

### "Class SCD_Schedule_Step_Validator not found"

The bootstrap file loads the plugin. If classes aren't found:
1. Check that `bootstrap.php` is loading `smart-cycle-discounts.php`
2. Verify plugin autoloader is working
3. Check that class files exist in `includes/` directory

### "Cannot find WordPress test library"

Set the `WP_TESTS_DIR` environment variable:
```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
vendor/bin/phpunit
```

### "Table doesn't exist" errors

The test database schema isn't created. Ensure:
1. WordPress test suite is properly installed
2. Plugin activation hooks run during bootstrap
3. Database migrations are executed

### Tests fail locally but pass on CI (or vice versa)

Check:
1. PHP version differences
2. WordPress version differences
3. Timezone settings (`current_time()` is timezone-sensitive)
4. Database state (tests should be isolated and clean up after themselves)

## Next Steps: Growing the Test Suite

This is the foundation. Next priorities:

1. **Expand to 44 tests** (critical areas):
   - Discount calculation tests
   - Product selection tests
   - Campaign conflict detection tests

2. **Add JavaScript tests** (QUnit or Jest):
   - Field collection dependency tests
   - Wizard navigation tests
   - Validation manager tests

3. **Add E2E tests** (Playwright or Puppeteer):
   - Complete wizard workflow
   - Campaign activation/deactivation
   - Admin UI interactions

4. **Code coverage analysis**:
   ```bash
   vendor/bin/phpunit --coverage-html coverage/
   ```
   Target: 80%+ code coverage

## Contributing

When adding new features:
1. ✅ Write tests FIRST (TDD approach)
2. ✅ Ensure tests fail without your code
3. ✅ Write code to make tests pass
4. ✅ Refactor with tests as safety net
5. ✅ Run full test suite before committing

## Resources

- [WordPress PHPUnit Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WP Test Suite on GitHub](https://github.com/WordPress/wordpress-develop)
