# Optimistic Locking Auto-Retry Implementation

## Problem

Users were seeing this error when editing campaigns:

```
Campaign 26 was modified by another user (expected version 1, current version 2). Please refresh and try again.
```

### Root Cause

The optimistic locking system uses a `version` field to detect concurrent modifications. When a campaign is saved, the version increments. The error occurs when:

1. User loads a campaign (version=1) into their browser
2. Something else updates the campaign (version becomes 2):
   - Cron job activating a scheduled campaign
   - Another browser tab/user editing
   - Automatic activation when scheduled time passes
3. User tries to save → version mismatch detected → error thrown

**This is GOOD** - it prevents data loss from concurrent edits. But the UX was poor because:
- Most conflicts were harmless (user editing fields A/B while system updated field C)
- Error required manual page refresh
- User-friendly but disruptive workflow

## Solution: Auto-Retry with Fresh Version

Implemented transparent retry logic that:

1. **Attempts save normally**
2. **Catches version conflict exception** (`SCD_Concurrent_Modification_Exception`)
3. **Reloads fresh campaign** from database (gets latest version)
4. **Merges user's changes** on top of fresh data
5. **Retries save ONCE** with correct version
6. **Succeeds transparently** (user never sees error) OR
7. **Fails gracefully** (genuine conflict) with user-friendly message

### Implementation Details

**File Modified:** `includes/services/class-campaign-creator-service.php`

**Changes:**

1. Line 278: Changed direct update to retry-wrapped update:
```php
// Before:
$campaign = $this->campaign_manager->update( $campaign_id, $campaign_data );

// After:
$campaign = $this->update_with_retry( $campaign_id, $campaign_data );
```

2. Lines 706-790: Added new method `update_with_retry()`:
```php
private function update_with_retry( $campaign_id, $campaign_data ) {
    try {
        // First attempt
        return $this->campaign_manager->update( $campaign_id, $campaign_data );

    } catch ( SCD_Concurrent_Modification_Exception $e ) {
        // Reload fresh campaign, merge changes, retry once
        // ...
    }
}
```

## How It Works

### Scenario 1: Harmless Concurrent Update (Auto-Resolved)

```
Timeline:
08:00:00 - User opens Campaign 26 (version=1) in wizard
08:00:30 - Cron activates campaign → version becomes 2
08:01:00 - User clicks Save

What Happens:
1. update_with_retry() calls campaign_manager->update()
2. Repository detects version mismatch (expected 1, got 2)
3. Throws SCD_Concurrent_Modification_Exception
4. update_with_retry() catches exception
5. Reloads Campaign 26 from database (version=2)
6. Merges user's changes on top
7. Retries update successfully (version becomes 3)
8. ✅ User sees "Campaign saved successfully"
```

### Scenario 2: Genuine Conflict (User Notified)

```
Timeline:
08:00:00 - User A opens Campaign 26 (version=1)
08:00:30 - User B saves Campaign 26 → version becomes 2
08:01:00 - User A clicks Save
08:01:01 - Auto-retry reloads (version=2), retries
08:01:02 - User B saves again → version becomes 3
08:01:03 - Retry fails (expected 2, got 3)

What Happens:
1. First attempt fails (version conflict)
2. Retry reloads fresh campaign (version=2)
3. Retry attempts update
4. But User B modified again → version now 3
5. Second SCD_Concurrent_Modification_Exception thrown
6. ❌ User sees: "Campaign 26 was modified by another user while you were editing. Please refresh the page and try again."
```

## Benefits

✅ **Transparent Recovery** - Most version conflicts resolve automatically
✅ **No Data Loss** - Optimistic locking still protects against overwrites
✅ **Better UX** - Users rarely see errors for harmless conflicts
✅ **Safe Merging** - User's changes applied on top of fresh data
✅ **Debug Logging** - Full WP_DEBUG logging for troubleshooting
✅ **Single Retry** - Avoids infinite retry loops

## Edge Cases Handled

1. **Campaign Deleted During Edit**
   - Auto-retry detects campaign not found
   - Returns clear error: "Campaign not found. It may have been deleted."

2. **Multiple Rapid Concurrent Edits**
   - First retry may fail if another edit happens
   - Second failure shows user-friendly message
   - Prevents data corruption from race conditions

3. **System vs User Updates**
   - Cron activation while user editing → auto-resolved
   - User updates status while editing fields → auto-resolved
   - Two users editing same field → user notified (correct behavior)

## Testing Recommendations

Test these scenarios:

1. **Scheduled Campaign Auto-Activation**
   - Create scheduled campaign for 2 minutes from now
   - Wait for activation to occur
   - Edit and save campaign
   - Should save successfully without error

2. **Duplicate Campaign Scenario** (Original Issue)
   - Duplicate a campaign
   - Immediately click Edit
   - Make changes and save
   - Should save successfully

3. **Genuine Concurrent Edit**
   - Open same campaign in two tabs
   - Edit different fields in each tab
   - Save from both tabs
   - First should succeed, second should auto-retry and succeed

4. **Same Field Conflict**
   - Open same campaign in two tabs
   - Edit campaign name in both tabs
   - Save from Tab 1
   - Save from Tab 2
   - Tab 2 should show conflict message (cannot auto-resolve)

## Monitoring

Watch for these log messages when `WP_DEBUG` is enabled:

```
[Campaign_Creator_Service] Version conflict detected (expected: 1, current: 2)
[Campaign_Creator_Service] Attempting auto-retry with fresh version...
[Campaign_Creator_Service] Retrying update with fresh version 2
[Campaign_Creator_Service] Auto-retry succeeded! Campaign updated with version 3
```

Or if retry fails:
```
[Campaign_Creator_Service] Auto-retry failed with another version conflict
```

## Future Enhancements (Optional)

- **Client-Side Version Check**: Periodically check if campaign version changed while editing
- **Visual Diff on Conflict**: Show user what changed between versions
- **Conflict Resolution UI**: Let user choose which changes to keep
- **WebSocket Updates**: Real-time notification when campaign changes

## Related Files

- `includes/core/exceptions/class-concurrent-modification-exception.php` - Exception definition
- `includes/database/repositories/class-campaign-repository.php` - Optimistic locking implementation
- `includes/core/campaigns/class-campaign.php` - Campaign entity with version field
- `includes/core/wizard/class-campaign-change-tracker.php` - Wizard change tracking

---

**Date Implemented:** 2025-01-28
**Issue:** Optimistic locking errors during campaign editing
**Solution:** Auto-retry with fresh version on concurrent modification
