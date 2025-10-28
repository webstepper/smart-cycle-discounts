# Critical Security Fixes Required

## Summary
3 CRITICAL vulnerabilities found that MUST be fixed before WordPress.org submission.
All fixes are in `/includes/admin/ajax/handlers/class-quick-edit-handler.php`

---

## Fix #1: Float Validation (Line 158-162)

### Current Code (VULNERABLE):
```php
// Line 158-161
if ( isset( $request['discount_value'] ) ) {
    $discount_value = floatval( $request['discount_value'] );
    if ( $discount_value > 0 ) {
        $update_data['discount_value'] = $discount_value;
```

### Fixed Code:
```php
// Line 158-167 - FIXED
if ( isset( $request['discount_value'] ) ) {
    // Validate as numeric string first to prevent scientific notation
    $discount_input = sanitize_text_field( $request['discount_value'] );
    if ( is_numeric( $discount_input ) && ! preg_match( '/[eE]/', $discount_input ) ) {
        $discount_value = floatval( $discount_input );
        if ( $discount_value > 0 && $discount_value <= 100 && is_finite( $discount_value ) ) {
            $update_data['discount_value'] = $discount_value;
        }
    }
}
```

### Why This Fix?
- Prevents INF/NAN injection via scientific notation (e.g., "1e308")
- Adds upper bound validation (discounts rarely exceed 100%)
- Uses `is_finite()` to catch edge cases

---

## Fix #2: Date Validation (Lines 166-177)

### Current Code (VULNERABLE):
```php
// Line 166-170
if ( isset( $request['start_date'] ) ) {
    $start_date = $this->sanitize_text( $request['start_date'] );
    if ( ! empty( $start_date ) ) {
        $update_data['start_date'] = $start_date;
    }
}

// Line 173-177
if ( isset( $request['end_date'] ) ) {
    $end_date = $this->sanitize_text( $request['end_date'] );
    if ( ! empty( $end_date ) ) {
        $update_data['end_date'] = $end_date;
    }
}
```

### Fixed Code:
```php
// Line 166-178 - FIXED
if ( isset( $request['start_date'] ) ) {
    $start_date = sanitize_text_field( $request['start_date'] );
    if ( ! empty( $start_date ) ) {
        // Validate MySQL datetime format
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $start_date ) ) {
            $parsed = strtotime( $start_date );
            if ( false !== $parsed && $parsed > 0 ) {
                $update_data['start_date'] = gmdate( 'Y-m-d H:i:s', $parsed );
            }
        }
    }
}

// Line 180-191 - FIXED
if ( isset( $request['end_date'] ) ) {
    $end_date = sanitize_text_field( $request['end_date'] );
    if ( ! empty( $end_date ) ) {
        // Validate MySQL datetime format
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $end_date ) ) {
            $parsed = strtotime( $end_date );
            if ( false !== $parsed && $parsed > 0 ) {
                $update_data['end_date'] = gmdate( 'Y-m-d H:i:s', $parsed );
            }
        }
    }
}
```

### Why This Fix?
- Prevents XSS via stored malicious date strings
- Validates proper MySQL datetime format
- Normalizes dates to GMT for consistency
- Prevents DateTime parsing exploits

---

## Fix #3: IP Spoofing Protection (RECOMMENDED)

### File: `/includes/admin/ajax/class-ajax-router.php` or `/includes/admin/ajax/class-ajax-security.php`

### Current Code (Line 664-680):
```php
public static function get_client_ip() {
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
```

### Fixed Code:
```php
public static function get_client_ip() {
    // Only trust proxy headers if behind known proxy
    $trusted_proxies = apply_filters( 'scd_trusted_proxies', array() );
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // If not behind trusted proxy, use REMOTE_ADDR only
    if ( empty( $trusted_proxies ) || ! in_array( $remote_addr, $trusted_proxies, true ) ) {
        return $remote_addr;
    }

    // Behind trusted proxy - check proxy-specific headers
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $remote_addr;
}
```

### Why This Fix?
- Prevents rate limit bypass via forged X-Forwarded-For headers
- Only trusts proxy headers when explicitly configured
- Admins can whitelist trusted proxies using filter:
  ```php
  add_filter( 'scd_trusted_proxies', function() {
      return array( '123.45.67.89' ); // CloudFlare IP
  });
  ```

---

## Testing Instructions

### Test Fix #1 (Float Validation):
```bash
# Test malicious scientific notation
curl -X POST 'https://yoursite.local/wp-admin/admin-ajax.php' \
  -d 'action=scd_quick_edit' \
  -d 'nonce=VALID_NONCE' \
  -d 'campaign_id=1' \
  -d 'discount_value=1e308'

# Expected: Error or rejection
# Before fix: Would insert INF into database
```

### Test Fix #2 (Date Validation):
```bash
# Test XSS payload in date
curl -X POST 'https://yoursite.local/wp-admin/admin-ajax.php' \
  -d 'action=scd_quick_edit' \
  -d 'nonce=VALID_NONCE' \
  -d 'campaign_id=1' \
  -d 'start_date=<script>alert(1)</script>'

# Expected: Error or rejection
# Before fix: Would store malicious script
```

### Test Fix #3 (IP Spoofing):
```bash
# Test forged IP header
curl -X POST 'https://yoursite.local/wp-admin/admin-ajax.php' \
  -H 'X-Forwarded-For: 1.2.3.4' \
  -d 'action=scd_product_search' \
  -d 'nonce=VALID_NONCE'

# Make 100 requests with different fake IPs
# Expected: Rate limit should still trigger (using REMOTE_ADDR)
# Before fix: Could bypass rate limit
```

---

## Implementation Checklist

- [ ] Apply Fix #1 (Float validation) to class-quick-edit-handler.php
- [ ] Apply Fix #2 (Date validation) to class-quick-edit-handler.php
- [ ] Apply Fix #3 (IP spoofing) to class-ajax-security.php
- [ ] Run security tests above
- [ ] Test normal quick edit functionality still works
- [ ] Test with valid dates (2025-01-01, 2025-12-31 23:59:59)
- [ ] Test with valid discount values (1, 10, 50, 99.99)
- [ ] Run full regression test on wizard and campaigns list

---

## Additional Recommended Fixes

### Medium Priority:

**Cache Invalidation Enhancement** (class-campaign-repository.php):
```php
public function delete( $id ) {
    $result = $this->soft_delete( $id );
    if ( $result ) {
        // Invalidate ALL cache variants
        $this->cache->forget( "campaign_{$id}" );
        $this->cache->forget( "campaign_{$id}_with_trashed" );
    }
    return $result;
}
```

**Request Size Optimization** (class-save-step-handler.php):
```php
// Replace serialize() with wp_json_encode() for faster validation
$request_size = strlen( wp_json_encode( $data ) );
```

---

## Post-Fix Verification

After applying all fixes, run:

1. **WordPress Plugin Check**: Install and run the official Plugin Check plugin
2. **Security Scan**: Use Wordfence or Sucuri to scan the plugin
3. **Manual Testing**: Test all AJAX endpoints with the test cases above
4. **Code Review**: Have a second developer review the changes

---

## WordPress.org Submission Status

**Before Fixes**: ❌ NOT READY (3 critical vulnerabilities)
**After Fixes**: ✅ READY FOR SUBMISSION

The plugin has excellent overall security architecture. These 3 fixes address minor implementation gaps in the quick edit feature and rate limiting system.

---

**Priority**: CRITICAL - Fix before production deployment
**Estimated Time**: 30-60 minutes
**Complexity**: Low (straightforward validation additions)
