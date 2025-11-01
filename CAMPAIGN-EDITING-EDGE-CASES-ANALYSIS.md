# Campaign Editing Workflow: Edge Cases & Bug Analysis

## Executive Summary

This comprehensive analysis examines the campaign editing workflow for edge cases, race conditions, data integrity issues, and potential bugs. The analysis covers 10 critical edge cases across PHP and JavaScript layers.

**Overall Assessment**: The system has STRONG foundations with optimistic locking, session-based change tracking, and transaction support. However, there are CRITICAL GAPS in concurrent editing detection, session expiration handling, and user feedback for conflict scenarios.

---

## Analysis Results by Edge Case

### 1. Concurrent Editing ⚠️ NEEDS ATTENTION

**Status**: Partially Handled - Backend protected, frontend blind

#### What IS Handled ✅

**File**: `/includes/database/repositories/class-campaign-repository.php` (Lines 447-492)

```php
// Optimistic locking: Store expected version
$expected_version = $existing->get_version();

// Increment version for optimistic locking
$campaign->increment_version();
$data['version'] = $campaign->get_version();

// Optimistic locking: Only update if version matches expected
$result = $this->db->update(
    'campaigns',
    $data,
    array(
        'id'      => $campaign->get_id(),
        'version' => $expected_version,  // Prevents concurrent edits
    ),
    $this->get_data_format( $data ),
    array( '%d', '%d' )
);

// Check if update failed due to concurrent modification
if ( $result === 0 ) {
    throw new SCD_Concurrent_Modification_Exception(
        $campaign->get_id(),
        $expected_version,
        $expected_version + 1
    );
}
```

**How it works**:
- Campaign entity has `version` field (default 1)
- On load: Store current version
- On update: Check database version matches stored version
- If mismatch: Throw `SCD_Concurrent_Modification_Exception`
- Increment version on successful save

#### What NEEDS Attention ⚠️

1. **No Frontend Detection**:
   - JavaScript has no awareness of campaign version
   - User A and User B can both edit the same campaign
   - User A saves → Success
   - User B saves → Backend throws exception, but frontend handling unclear

2. **Missing User Feedback**:
   - Location: `/includes/core/wizard/class-complete-wizard-handler.php` (Lines 155-162)
   - Generic error handling catches the exception
   - User sees: "Failed to save campaign" (not helpful)
   - User doesn't know: "Another user modified this campaign, please refresh"

3. **No Polling Mechanism**:
   - Frontend doesn't check if campaign was modified by others
   - No periodic version checks
   - No "This campaign was updated by [User] at [Time]" banner

4. **No Auto-Refresh Strategy**:
   - When version conflict detected, no UX flow to:
     - Show diff of changes
     - Let user choose to override or merge
     - Automatically reload campaign with latest data

#### Recommendations:

1. **Add Frontend Version Tracking**:
```javascript
// In wizard-orchestrator.js
this.campaignVersion = window.scdWizardData.campaignVersion || 1;

// Before completing wizard, verify version unchanged
SCD.Ajax.post('scd_check_campaign_version', {
    campaign_id: this.getCampaignId(),
    expected_version: this.campaignVersion
}).done(function(response) {
    if (!response.valid) {
        // Show conflict resolution modal
        self.showVersionConflictModal(response);
    }
});
```

2. **Enhanced Error Handling**:
```php
// In complete-wizard-handler.php
catch ( SCD_Concurrent_Modification_Exception $e ) {
    return array(
        'success' => false,
        'error_code' => 'version_conflict',
        'message' => sprintf(
            __('This campaign was modified by another user. Expected version %d, current version %d. Please refresh to see latest changes.', 'smart-cycle-discounts'),
            $e->get_expected_version(),
            $e->get_actual_version()
        ),
        'current_version' => $e->get_actual_version(),
        'expected_version' => $e->get_expected_version()
    );
}
```

3. **Add Periodic Version Check**:
```javascript
// Poll every 30 seconds when editing
setInterval(function() {
    if (self.isEditMode()) {
        self.checkCampaignVersion();
    }
}, 30000);
```

---

### 2. Session Expiration ⚠️ NEEDS ATTENTION

**Status**: Basic handling exists, needs improvement

#### What IS Handled ✅

**File**: `/includes/core/wizard/class-wizard-state-service.php` (Lines 43, 1088-1099)

```php
const SESSION_LIFETIME = 7200; // 2 hours

private function is_expired(): bool {
    $last_activity = isset( $this->data['updated_at'] )
        ? $this->data['updated_at']
        : $this->data['created_at'];

    if ( ! $last_activity ) {
        return false;
    }

    $age = time() - $last_activity;
    return $age > self::SESSION_LIFETIME;
}
```

**File**: `/resources/assets/js/wizard/wizard-orchestrator.js` (Lines 63, 311-313, 1029-1041)

```javascript
config: {
    sessionTimeout: 3600000  // 1 hour (JavaScript)
},

eventBus.on('session:expired', function() {
    self.handleSessionExpired();
});

handleSessionExpired: function() {
    this.cleanup();
    this.showError('Your session has expired. Please refresh the page.', 'error', 0);
    $('.scd-wizard-wrapper').addClass('session-expired');
}
```

#### What NEEDS Attention ⚠️

1. **Mismatch Between PHP and JavaScript Timeouts**:
   - PHP session: 2 hours (7200 seconds)
   - JavaScript warning: 1 hour (3600000 ms)
   - Gap: User gets warning 1 hour before actual expiry
   - **Issue**: Confusing UX - session still valid when warning shows

2. **No Auto-Save During Session Extension**:
   - Session extends on activity (`updated_at` bumps)
   - But what triggers "activity"?
   - User typing doesn't trigger save
   - User could lose work if not manually saving

3. **No Grace Period**:
   - Session expires → Hard stop
   - No "Your session is about to expire, would you like to continue?" prompt
   - No auto-save before expiration

4. **Session Restoration Not Implemented**:
   - User refreshes page after expiration
   - Session data gone (transient deleted)
   - No recovery mechanism

5. **PHP Nonce Expiration Separate**:
   - WordPress nonces expire independently (12-24 hours)
   - If nonce expires but session valid → AJAX fails
   - If session expires but nonce valid → State lost
   - No unified expiration handling

#### Recommendations:

1. **Align Timeouts**:
```php
// Make JavaScript timeout match PHP (or vice versa)
const SESSION_LIFETIME = 7200; // 2 hours
```

```javascript
// Pass from PHP to ensure consistency
config: {
    sessionTimeout: window.scdWizardData.sessionLifetime || 7200000  // 2 hours
}
```

2. **Add Session Warning + Extension**:
```javascript
// Warn 10 minutes before expiration
var warnTime = this.config.sessionTimeout - (10 * 60 * 1000);

setTimeout(function() {
    self.showSessionExpirationWarning();
}, warnTime);

showSessionExpirationWarning: function() {
    if (confirm('Your session will expire in 10 minutes. Would you like to extend it?')) {
        this.extendSession();
    }
},

extendSession: function() {
    SCD.Ajax.post('scd_extend_session', {})
        .done(function() {
            // Reset warning timer
        });
}
```

3. **Auto-Save on Session Activity**:
```javascript
// Save step data on blur (every 30 seconds throttled)
$(document).on('blur', '.scd-wizard-step input, .scd-wizard-step select, .scd-wizard-step textarea',
    _.throttle(function() {
        self.autoSaveCurrentStep();
    }, 30000)
);
```

4. **Session Recovery**:
```php
// Store backup in WordPress options table
update_user_meta(get_current_user_id(), '_scd_wizard_backup_' . $session_id, $data);

// Try to recover on load
$backup = get_user_meta(get_current_user_id(), '_scd_wizard_backup_' . $session_id, true);
if ($backup && !$this->has_session()) {
    $this->restore_from_backup($backup);
}
```

---

### 3. Data Loss Prevention ⚠️ NEEDS ATTENTION

**Status**: Partial protection via state manager, no browser-level safeguards

#### What IS Handled ✅

**File**: `/resources/assets/js/wizard/wizard-orchestrator.js` (Lines 323-344)

```javascript
// Track form changes to set hasUnsavedChanges (for UI state only)
$(document).on('change.wizard input.wizard', 'input, select, textarea', function(e) {
    // Skip if this is a navigation button or non-form element
    if (0 < $(e.target).closest('.scd-wizard-navigation').length) {
        return;
    }

    // Mark as having unsaved changes
    if (self.modules.stateManager) {
        var currentState = self.modules.stateManager.get('hasUnsavedChanges');
        if (!currentState) {
            self.modules.stateManager.set({ hasUnsavedChanges: true });
        } else {
            // Silently update without logging
            self.modules.stateManager.set({ hasUnsavedChanges: true }, { silent: true });
        }
    }
});
```

**File**: `/resources/assets/js/shared/mixins/step-persistence.js` (Lines 405-424)

```javascript
// Update wizard state manager with the saved data
if (window.SCD && window.SCD.Wizard && window.SCD.Wizard.modules && window.SCD.Wizard.modules.stateManager) {
    var currentStepData = window.SCD.Wizard.modules.stateManager.get('stepData') || {};
    currentStepData[self.stepName] = data;
    window.SCD.Wizard.modules.stateManager.set('stepData', currentStepData);
}

// Update window.scdWizardData to prevent stale data
if (window.scdWizardData && window.scdWizardData.currentCampaign) {
    if (!window.scdWizardData.currentCampaign[self.stepName]) {
        window.scdWizardData.currentCampaign[self.stepName] = {};
    }
    // Deep copy to prevent reference issues
    window.scdWizardData.currentCampaign[self.stepName] = $.extend(true, {}, data);
}
```

#### What NEEDS Attention ⚠️

1. **No `beforeunload` Warning** (Intentionally Removed):
   - Line 323-324: "beforeunload warning removed - navigation saves handle data protection"
   - **Issue**: User closes tab/browser → All unsaved changes lost
   - **Rationale in code**: "Navigation saves work perfectly without false warnings"
   - **Problem**: Works for navigation WITHIN wizard, not for browser close/tab close

2. **No LocalStorage Backup**:
   - All data in memory (state manager)
   - Browser crash → Data gone
   - No persistent client-side backup

3. **Session Storage Not Used**:
   - Line 107: `sessionStorage.removeItem('scd_wizard_state');` on `intent=new`
   - But session storage not used as backup mechanism
   - Opportunity missed for crash recovery

4. **No Periodic Auto-Save**:
   - User must manually save or navigate to trigger save
   - Long editing session without navigation → Risk of data loss
   - No background "draft save" every N minutes

5. **Change Tracker Only Tracks, Doesn't Persist**:
   - Location: `/includes/core/wizard/class-campaign-change-tracker.php`
   - Stores changes in session: `$this->session->set('changes', $this->changes);` (Line 108)
   - But session itself is volatile (transient with 2-hour TTL)
   - Browser crash or force quit → Session lost

#### Recommendations:

1. **Re-Add `beforeunload` for External Navigation Only**:
```javascript
// Warn only when navigating away from wizard, not within wizard
$(window).on('beforeunload', function(e) {
    // Don't warn for internal wizard navigation
    if (self.isInternalNavigation) {
        return undefined;
    }

    // Only warn if truly unsaved (not just navigating between steps)
    if (self.modules.stateManager && self.modules.stateManager.get('hasUnsavedChanges')) {
        var message = 'You have unsaved changes. Are you sure you want to leave?';
        e.returnValue = message;
        return message;
    }
});

// Mark internal navigation
this.navigateToStep = function(step) {
    this.isInternalNavigation = true;
    // ... navigation logic
    setTimeout(function() { self.isInternalNavigation = false; }, 1000);
};
```

2. **Add LocalStorage Backup**:
```javascript
// Save to localStorage every 10 seconds
setInterval(function() {
    if (self.modules.stateManager) {
        var state = self.modules.stateManager.get();
        localStorage.setItem('scd_wizard_backup_' + self.getCampaignId(), JSON.stringify({
            state: state,
            timestamp: Date.now()
        }));
    }
}, 10000);

// Restore on load
var backup = localStorage.getItem('scd_wizard_backup_' + this.getCampaignId());
if (backup) {
    try {
        var parsed = JSON.parse(backup);
        // Check if backup is recent (< 1 hour old)
        if (Date.now() - parsed.timestamp < 3600000) {
            if (confirm('Found unsaved changes. Would you like to restore them?')) {
                this.modules.stateManager.set(parsed.state);
            }
        }
    } catch (e) {
        console.error('Failed to restore backup:', e);
    }
}
```

3. **Implement Auto-Save**:
```javascript
// Auto-save every 60 seconds if changes detected
setInterval(function() {
    if (self.modules.stateManager && self.modules.stateManager.get('hasUnsavedChanges')) {
        self.autoSaveCurrentStep();
    }
}, 60000);

autoSaveCurrentStep: function() {
    var stepName = this.getCurrentStep();
    var stepOrchestrator = this.stepOrchestrators[stepName];

    if (stepOrchestrator && typeof stepOrchestrator.saveStep === 'function') {
        stepOrchestrator.saveStep().done(function() {
            // Silent save - no notification
            if (self.modules.stateManager) {
                self.modules.stateManager.set({
                    hasUnsavedChanges: false,
                    lastAutoSave: new Date().toISOString()
                }, { silent: true });
            }
        });
    }
}
```

4. **Database-Level Draft Recovery**:
```php
// Store incomplete edits in database
function save_draft_changes($user_id, $campaign_id, $changes) {
    global $wpdb;
    $wpdb->replace(
        $wpdb->prefix . 'scd_draft_changes',
        array(
            'user_id' => $user_id,
            'campaign_id' => $campaign_id,
            'changes' => json_encode($changes),
            'updated_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s')
    );
}

// Restore on wizard load
function load_draft_changes($user_id, $campaign_id) {
    global $wpdb;
    $draft = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}scd_draft_changes
         WHERE user_id = %d AND campaign_id = %d
         ORDER BY updated_at DESC LIMIT 1",
        $user_id,
        $campaign_id
    ));

    return $draft ? json_decode($draft->changes, true) : null;
}
```

---

### 4. Partial Updates ✅ HANDLED PROPERLY

**Status**: Well-designed architecture handles this correctly

#### How It Works ✅

**File**: `/includes/core/wizard/class-wizard-state-service.php` (Lines 556-610)

```php
public function save_step_data( string $step, array $data ): bool {
    $step = sanitize_key( $step );

    if ( ! $this->is_valid_step( $step ) ) {
        return false;
    }

    // Prepare data
    $this->prepare_step_data( $step, $data );

    // Edit mode: use Change Tracker (stores only deltas)
    $campaign_id = $this->get( 'campaign_id' );
    $is_edit     = $this->is_edit_mode() || $campaign_id;

    if ( $is_edit ) {
        if ( ! $this->change_tracker ) {
            // Fall back to session storage
        } else {
            // Track changes via Change Tracker (session only)
            $this->change_tracker->track_step( $step, $data );
            return true;
        }
    }

    // Create mode: store full data in session
    $existing_data = $this->get_step_data( $step );
    $merged_data   = array_merge( $existing_data, $data );
    $this->set_step_data( $step, $merged_data );

    return $this->save();
}
```

**File**: `/includes/core/wizard/class-campaign-change-tracker.php` (Lines 120-128, 190-214)

```php
// Tracks only changed fields
public function track_step( $step, $data ) {
    if ( ! is_array( $data ) ) {
        return;
    }

    foreach ( $data as $field => $value ) {
        $this->track( $step, $field, $value );
    }
}

// Compiles changes with database baseline
public function compile() {
    $campaign = $this->load_campaign();
    if ( ! $campaign ) {
        // No base campaign - return changes only
        $compiled = array();
        foreach ( $this->changes as $step => $fields ) {
            foreach ( $fields as $field => $change ) {
                $compiled[ $field ] = $change['value'];
            }
        }
        return $compiled;
    }

    // Start with campaign data
    $campaign_data = $campaign->to_array();

    // Apply all changes
    foreach ( $this->changes as $step => $fields ) {
        foreach ( $fields as $field => $change ) {
            $campaign_data[ $field ] = $change['value'];
        }
    }

    return $campaign_data;
}
```

#### Why This Works ✅

1. **Delta Storage**: Only modified fields stored in session
2. **Baseline from DB**: Original campaign data read from database
3. **Merge on Compile**: Changes merged with DB baseline when completing wizard
4. **No Data Loss**: Unchanged steps remain untouched in database

#### Example Flow:

```
Initial State (Database):
{
    name: "Summer Sale",
    discount_value: 20,
    start_date: "2025-01-01",
    end_date: "2025-12-31"
}

User edits only "Discounts" step:
- Changes discount_value: 20 → 25

Change Tracker stores:
{
    discounts: {
        discount_value: { value: 25, timestamp: 1234567890 }
    }
}

User completes wizard:
1. Load campaign from DB
2. Apply changes from tracker
3. Final data:
{
    name: "Summer Sale",          // Unchanged (from DB)
    discount_value: 25,            // Changed (from tracker)
    start_date: "2025-01-01",      // Unchanged (from DB)
    end_date: "2025-12-31"         // Unchanged (from DB)
}
```

#### Edge Case Handled ✅

**Scenario**: User navigates to "Products" step, changes nothing, then to "Review"

**Result**:
- No changes tracked for Products step
- Database value remains unchanged
- No unnecessary writes

---

### 5. Validation Edge Cases ⚠️ MOSTLY HANDLED, MINOR GAPS

**Status**: Strong validation system, missing some boundary checks

#### What IS Handled ✅

**File**: `/includes/core/validation/class-field-definitions.php` (Extensive validation rules)

**File**: `/resources/assets/js/validation/validation-manager.js` (Client-side validation)

**File**: `/resources/assets/js/shared/mixins/step-persistence.js` (Lines 174-237)

```javascript
validateData: function(data) {
    try {
        // Check for data collection errors first
        if (data && data._error) {
            return {
                valid: false,
                errors: [{
                    field: 'system',
                    message: data._message || 'Data collection failed'
                }]
            };
        }

        // Use new pure ValidationManager.validateStep method
        var result = window.SCD.ValidationManager.validateStep(this.stepName, data);

        // Convert new format to legacy format for backward compatibility
        var legacyErrors = [];
        if (!result.ok) {
            for (var fieldName in result.errors) {
                if (result.errors.hasOwnProperty(fieldName)) {
                    var fieldErrors = result.errors[fieldName];
                    // Handle array of error objects
                    if (Array.isArray(fieldErrors)) {
                        for (var i = 0; i < fieldErrors.length; i++) {
                            legacyErrors.push({
                                field: fieldName,
                                message: fieldErrors[i].message || fieldErrors[i]
                            });
                        }
                    } else {
                        legacyErrors.push({
                            field: fieldName,
                            message: fieldErrors.message || fieldErrors
                        });
                    }
                }
            }
        }

        return {
            valid: result.ok,
            errors: legacyErrors,
            clean: result.clean
        };
    } catch (error) {
        return {
            valid: false,
            errors: [{ field: 'system', message: error.message }]
        };
    }
}
```

#### What NEEDS Attention ⚠️

1. **Empty String vs Null vs Undefined**:
   - Validation checks for "required" but how?
   - Empty string `""` might pass where `null` fails
   - JavaScript `undefined` vs PHP `null` handling inconsistent

2. **Zero as Boundary Value**:
   - Discount value of `0` valid? (0% discount = no discount)
   - Usage limit of `0` valid? (Unlimited? Or invalid?)
   - Not explicitly validated

3. **Negative Numbers**:
   - Can discount_value be `-10`? (Increase price by 10%)
   - Can priority be negative?
   - No explicit min value checks in some fields

4. **Maximum Value Checks**:
   - Discount percentage > 100%? (125% off?)
   - Usage limit > PHP_INT_MAX?
   - Priority > 10?

5. **Date Range Validation**:
   - End date before start date?
   - Start date in the past?
   - End date 100 years in the future?

6. **Special Characters in Strings**:
   - Campaign name with emojis? HTML tags? SQL injection attempts?
   - Sanitization vs Validation distinction unclear

#### Current Validation Strengths ✅

1. **Type checking**: String, int, float enforced
2. **Required field checks**: Present
3. **Enum validation**: Status, discount type validated against allowed values
4. **Conditional visibility**: Fields hidden/shown based on other field values (Lines 832-890 in step-persistence.js)

#### Recommendations:

1. **Add Explicit Boundary Validation**:
```php
// In field definitions
'discount_value' => array(
    'type' => 'number',
    'required' => true,
    'validate' => array(
        'min' => 0,
        'max' => 100,  // For percentage
        'step' => 0.01
    )
)
```

2. **Sanitize All Inputs**:
```php
// Already done in step-persistence.js (Lines 144-155)
// But ensure backend also sanitizes
$data['name'] = sanitize_text_field($data['name']);
```

3. **Add Date Range Validation**:
```javascript
if (data.start_date && data.end_date) {
    var startDate = new Date(data.start_date);
    var endDate = new Date(data.end_date);

    if (endDate <= startDate) {
        errors.push({
            field: 'end_date',
            message: 'End date must be after start date'
        });
    }
}
```

4. **Test Edge Cases**:
   - Submit form with `discount_value = ""`
   - Submit with `discount_value = 0`
   - Submit with `discount_value = -10`
   - Submit with `discount_value = 999999999`
   - Submit with `name = "<script>alert('xss')</script>"`

---

### 6. Network Failures ⚠️ PARTIAL HANDLING

**Status**: Basic retry logic exists, needs improvement

#### What IS Handled ✅

**File**: `/includes/core/wizard/class-wizard-state-service.php` (Lines 389-529)

```php
// Save with locking to prevent race conditions
public function save( bool $force = false ): bool {
    if ( ! $this->session_id ) {
        return false;
    }

    // Acquire lock using transient
    $lock_key      = 'scd_state_lock_' . $this->session_id;
    $lock_acquired = false;

    if ( $force ) {
        $lock_acquired = true;
    } else {
        $lock_acquired = set_transient( $lock_key, time(), 5 ); // 5 second lock
    }

    if ( ! $lock_acquired ) {
        // Lock not acquired - queue for deferred save
        return $this->queue_deferred_save();
    }

    try {
        $this->update_timestamp();
        $success = $this->persist_session_data();

        if ( $success ) {
            $this->dirty = false;
            $this->process_deferred_saves();
        }

        return $success;

    } finally {
        if ( ! $force ) {
            delete_transient( $lock_key );
        }
    }
}

private function queue_deferred_save(): bool {
    // Add current timestamp to deferred saves queue
    $this->deferred_saves[] = time();

    // Schedule a single deferred save attempt using WordPress shutdown hook
    if ( ! has_action( 'shutdown', array( $this, 'attempt_deferred_save' ) ) ) {
        add_action( 'shutdown', array( $this, 'attempt_deferred_save' ) );
    }

    return false; // Indicate immediate save failed
}
```

#### What NEEDS Attention ⚠️

1. **No Client-Side Retry**:
   - AJAX request fails → User sees error
   - No automatic retry
   - User must manually retry

2. **No Exponential Backoff**:
   - Deferred save retries immediately on shutdown
   - No delay between retries
   - Could overwhelm database during outage

3. **Max Retry Count Only on Complex Fields**:
   - Line 746 in step-persistence.js: `maxRetries = 50` for complex fields
   - But no retry for standard AJAX saves

4. **No Offline Detection**:
   - No check for `navigator.onLine`
   - No "You are offline" message
   - Network requests fail silently

5. **No Request Queueing**:
   - Multiple AJAX requests in flight
   - If network flaky, all fail
   - No queue to retry in order

6. **Transaction Rollback Unclear**:
   - Line 416 in campaign-repository.php: `$this->db->transaction(...)`
   - If transaction fails midway, is rollback automatic?
   - Error handling for transaction failure?

#### Recommendations:

1. **Add Client-Side Retry with Exponential Backoff**:
```javascript
SCD.Ajax = {
    post: function(action, data, retries) {
        retries = retries || 0;
        var maxRetries = 3;
        var backoffMs = Math.pow(2, retries) * 1000; // 1s, 2s, 4s

        return $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: $.extend({}, data, {
                action: action,
                nonce: SCD.nonce
            })
        }).fail(function(xhr) {
            if (retries < maxRetries && xhr.status >= 500) {
                // Server error - retry
                console.log('Retrying request in ' + backoffMs + 'ms...');
                return new Promise(function(resolve) {
                    setTimeout(function() {
                        resolve(SCD.Ajax.post(action, data, retries + 1));
                    }, backoffMs);
                });
            } else {
                // Client error or max retries - fail
                throw xhr;
            }
        });
    }
};
```

2. **Add Offline Detection**:
```javascript
$(window).on('offline', function() {
    SCD.Shared.NotificationService.warning(
        'You are offline. Changes will be saved when connection is restored.',
        0
    );
    $('.scd-wizard-wrapper').addClass('offline-mode');
});

$(window).on('online', function() {
    SCD.Shared.NotificationService.success('Connection restored');
    $('.scd-wizard-wrapper').removeClass('offline-mode');

    // Retry any failed saves
    if (self.modules.stateManager && self.modules.stateManager.get('hasUnsavedChanges')) {
        self.autoSaveCurrentStep();
    }
});
```

3. **Queue Failed Requests**:
```javascript
SCD.FailedRequests = [];

SCD.Ajax.post(...).fail(function(xhr) {
    SCD.FailedRequests.push({
        action: action,
        data: data,
        timestamp: Date.now()
    });
});

// Retry on network restore
$(window).on('online', function() {
    SCD.FailedRequests.forEach(function(request) {
        SCD.Ajax.post(request.action, request.data);
    });
    SCD.FailedRequests = [];
});
```

4. **Explicit Transaction Error Handling**:
```php
// Already exists in campaign-repository.php
try {
    $result = $this->db->transaction(function() use ($campaign) {
        // ... save logic
    });

    if (!$result) {
        error_log('[Repository] Transaction failed - automatic rollback triggered');
        return false;
    }
} catch (Exception $e) {
    error_log('[Repository] Transaction exception: ' . $e->getMessage());
    // Rollback automatic in SCD_Database_Manager
    return false;
}
```

---

### 7. Version Conflicts (External Modification) ⚠️ NEEDS ATTENTION

**Status**: Same as Edge Case #1 - Backend protected, no frontend awareness

#### Scenario:

1. User A opens campaign #123 in wizard (version 5)
2. Admin bulk-edits campaign #123 via WP-CLI or REST API (version → 6)
3. User A completes wizard with changes
4. Backend update query: `WHERE id = 123 AND version = 5` → Fails (version is now 6)

#### Current Behavior:

- Exception thrown: `SCD_Concurrent_Modification_Exception`
- User sees generic error message
- User's changes lost
- No way to resolve conflict

#### What's Missing:

1. **No Conflict Resolution UI**: Show diff, let user choose
2. **No "Reload and Retry"**: Force user to refresh manually
3. **No Change Preservation**: User's edits discarded
4. **No Lock Mechanism**: No way to prevent external modification during editing

#### Recommendations:

Same as Edge Case #1, plus:

**Add Advisory Lock**:
```php
// When user starts editing
function acquire_campaign_lock($campaign_id, $user_id) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'scd_campaign_locks',
        array(
            'campaign_id' => $campaign_id,
            'user_id' => $user_id,
            'locked_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600) // 1 hour
        )
    );
}

// Check before external edit
function is_campaign_locked($campaign_id) {
    global $wpdb;
    $lock = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}scd_campaign_locks
         WHERE campaign_id = %d AND expires_at > NOW()",
        $campaign_id
    ));

    if ($lock) {
        $user = get_userdata($lock->user_id);
        throw new Exception(sprintf(
            'Campaign is currently being edited by %s',
            $user->display_name
        ));
    }
}

// Release lock on wizard complete or abandon
function release_campaign_lock($campaign_id, $user_id) {
    global $wpdb;
    $wpdb->delete(
        $wpdb->prefix . 'scd_campaign_locks',
        array('campaign_id' => $campaign_id, 'user_id' => $user_id)
    );
}
```

---

### 8. Change Tracker Cleanup ⚠️ NEEDS ATTENTION

**Status**: Partial cleanup, potential memory leaks

#### What IS Handled ✅

**File**: `/includes/core/wizard/class-wizard-state-service.php` (Lines 1030-1040)

```php
public function clear_changes(): void {
    if ( $this->change_tracker ) {
        $this->change_tracker->clear();
    }
}
```

**File**: `/includes/core/wizard/class-campaign-change-tracker.php` (Lines 245-250)

```php
public function clear() {
    $this->changes = array();
    $this->session->set( 'changes', array() );
    $this->session->save();
}
```

**File**: `/includes/core/wizard/class-complete-wizard-handler.php` (Line 174)

```php
// Clear the wizard session
$this->state_service->clear_session();
```

#### What NEEDS Attention ⚠️

1. **Clear on Success Only**:
   - Line 174 in complete-wizard-handler.php clears session
   - But only after successful save
   - If save fails → Session remains
   - User retries → Old session data reused
   - **Potential issue**: Stale data accumulates

2. **No Clear on Abandon**:
   - User navigates away from wizard without completing
   - Session remains in database (transient) for 2 hours
   - Change tracker data remains in session
   - Memory accumulation if many users abandon

3. **No Clear on Error**:
   - Exception thrown during save
   - Session not cleared
   - User sees error, refreshes page
   - Old session loaded again

4. **Transient Cleanup Relies on WordPress**:
   - Expired transients deleted by WordPress cron
   - But only when `delete_expired_transients()` runs
   - Could be delayed if cron not working
   - Database could accumulate expired transients

5. **No User-Facing "Discard Changes" Button**:
   - User wants to cancel editing
   - No way to explicitly discard changes and clear session
   - Must wait for session to expire

#### Recommendations:

1. **Clear Session on Any Exit Path**:
```php
// In complete-wizard-handler.php
try {
    // ... save logic
    $this->state_service->clear_session();
    return array('success' => true, ...);
} catch (Exception $e) {
    // Clear session even on failure to prevent stale data
    $this->state_service->clear_session();
    return array('success' => false, ...);
}
```

2. **Add "Discard Changes" Button**:
```javascript
// In wizard UI
<button class="scd-button-secondary" id="scd-discard-changes">
    Discard Changes
</button>

$('#scd-discard-changes').on('click', function() {
    if (confirm('Are you sure you want to discard all changes?')) {
        SCD.Ajax.post('scd_clear_wizard_session', {})
            .done(function() {
                window.location.href = '/wp-admin/admin.php?page=scd-campaigns';
            });
    }
});
```

3. **Add Session Cleanup Cron**:
```php
// Register cron event
add_action('wp', function() {
    if (!wp_next_scheduled('scd_cleanup_expired_sessions')) {
        wp_schedule_event(time(), 'hourly', 'scd_cleanup_expired_sessions');
    }
});

// Cleanup handler
add_action('scd_cleanup_expired_sessions', function() {
    global $wpdb;

    // Delete expired wizard sessions
    $expired = $wpdb->get_results(
        "SELECT option_name FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_timeout_scd_wizard_session_%'
         AND option_value < UNIX_TIMESTAMP()"
    );

    foreach ($expired as $transient) {
        $key = str_replace('_transient_timeout_', '', $transient->option_name);
        delete_transient($key);
    }
});
```

4. **Track Session Age and Warn**:
```javascript
// In wizard initialization
var sessionAge = Date.now() - window.scdWizardData.sessionCreatedAt;
var maxAge = 2 * 60 * 60 * 1000; // 2 hours

if (sessionAge > maxAge * 0.9) {
    SCD.Shared.NotificationService.warning(
        'Your editing session is about to expire. Please save your changes soon.',
        0
    );
}
```

---

### 9. Timezone Edge Cases ⚠️ NEEDS ATTENTION

**Status**: Good foundation, missing DST and validation

#### What IS Handled ✅

**File**: `/includes/core/wizard/class-campaign-change-tracker.php` (Lines 352-377)

```php
case 'schedule':
    $starts_at = $campaign->get_starts_at();
    $ends_at   = $campaign->get_ends_at();
    $timezone  = $campaign->get_timezone();

    $start_split = $starts_at ? SCD_DateTime_Splitter::for_editing( $starts_at, $timezone ) : array();
    $end_split   = $ends_at ? SCD_DateTime_Splitter::for_editing( $ends_at, $timezone ) : array();

    // Debug logging for schedule data extraction
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[Change Tracker] - timezone: ' . $timezone );
        error_log( '[Change Tracker] - end_date extracted: ' . ( $end_split['date'] ?? 'empty' ) );
        error_log( '[Change Tracker] - end_time extracted: ' . ( $end_split['time'] ?? 'empty' ) );
    }

    return array(
        'start_type' => $starts_at ? 'scheduled' : 'immediate',
        'start_date' => $start_split['date'] ?? '',
        'start_time' => $start_split['time'] ?? '00:00',
        'end_date'   => $end_split['date'] ?? '',
        'end_time'   => $end_split['time'] ?? '23:59',
        'timezone'   => $timezone,
    );
```

#### What NEEDS Attention ⚠️

1. **DST Transitions Not Validated**:
   - Scenario: User schedules campaign at 2:30 AM on DST "spring forward" day
   - In many timezones, 2:00 AM - 3:00 AM doesn't exist on DST transition
   - What happens?
   - No validation for invalid times

2. **Invalid Timezone Not Caught Early**:
   - User could manually enter `timezone = "Wakanda/BlackPanther"`
   - PHP `new DateTimeZone($invalid)` throws exception
   - But when? During save? During display?
   - No upfront validation

3. **Timezone Migration Not Handled**:
   - Campaign created with `America/New_York`
   - User moves to `Europe/London`
   - What timezone for editing? Original? Current?
   - No clear behavior

4. **Ambiguous Times in Fall DST**:
   - "Fall back" DST: 2:30 AM occurs twice
   - First occurrence: Before DST ends
   - Second occurrence: After DST ends
   - Which one is used? Not documented

5. **No UTC Offset Display**:
   - User selects timezone "America/New_York"
   - UI shows: "America/New_York"
   - Better: "America/New_York (EST, UTC-5)" or "(EDT, UTC-4)" depending on date

#### Recommendations:

1. **Validate Timezone on Input**:
```php
function validate_timezone($timezone) {
    try {
        $tz = new DateTimeZone($timezone);
        return true;
    } catch (Exception $e) {
        return new WP_Error(
            'invalid_timezone',
            sprintf(__('Invalid timezone: %s', 'smart-cycle-discounts'), $timezone)
        );
    }
}

// In field validation
if (isset($data['timezone'])) {
    $result = validate_timezone($data['timezone']);
    if (is_wp_error($result)) {
        return $result;
    }
}
```

2. **Validate Date/Time Against DST**:
```php
function is_valid_datetime_in_timezone($date, $time, $timezone) {
    try {
        $datetime_string = $date . ' ' . $time;
        $dt = new DateTimeImmutable($datetime_string, new DateTimeZone($timezone));

        // Check if time was adjusted due to DST
        $formatted_time = $dt->format('H:i');
        if ($formatted_time !== $time) {
            return new WP_Error(
                'invalid_dst_time',
                sprintf(
                    __('The time %s does not exist in timezone %s on %s (DST transition)', 'smart-cycle-discounts'),
                    $time,
                    $timezone,
                    $date
                )
            );
        }

        return true;
    } catch (Exception $e) {
        return new WP_Error('invalid_datetime', $e->getMessage());
    }
}
```

3. **Show UTC Offset in UI**:
```javascript
// When timezone changes, update display
$('#timezone-select').on('change', function() {
    var timezone = $(this).val();
    var startDate = $('#start_date').val() || new Date().toISOString().split('T')[0];

    $.ajax({
        url: ajaxurl,
        data: {
            action: 'scd_get_timezone_info',
            timezone: timezone,
            date: startDate
        }
    }).done(function(response) {
        $('#timezone-offset').text(response.offset + ' (' + response.abbreviation + ')');
    });
});
```

4. **DST Warning**:
```javascript
// Check if date falls on DST transition
function checkDSTTransition(date, time, timezone) {
    // Server-side check via AJAX
    $.post(ajaxurl, {
        action: 'scd_check_dst',
        date: date,
        time: time,
        timezone: timezone
    }).done(function(response) {
        if (response.is_dst_transition) {
            SCD.Shared.NotificationService.warning(
                'The selected time falls on a DST transition. Time will be adjusted to: ' + response.adjusted_time,
                10000
            );
        }
    });
}
```

5. **Store Dates in UTC Internally**:
```php
// Already done! Campaign entity stores DateTime objects
// Database stores in UTC: gmdate('Y-m-d H:i:s')
// Conversion happens in SCD_DateTime_Splitter

// Good practice: Always convert user input to UTC for storage
$user_datetime = new DateTime($date . ' ' . $time, new DateTimeZone($user_timezone));
$utc_datetime = $user_datetime->setTimezone(new DateTimeZone('UTC'));
$stored_value = $utc_datetime->format('Y-m-d H:i:s');
```

---

### 10. Product/Category Deletion ⚠️ NEEDS ATTENTION

**Status**: No orphan detection or cleanup

#### Scenario:

1. Campaign created with `product_ids = [10, 20, 30]`
2. Admin deletes product #20
3. User edits campaign in wizard
4. Product picker shows products 10, 20 (deleted), 30
5. User saves campaign
6. What happens?

#### Current Behavior (Inferred):

- No explicit handling in code
- Likely: Deleted product ID remains in `product_ids` array
- Campaign tries to apply discount to non-existent product
- Discount silently fails for that product
- No error shown to user

#### What's Missing:

1. **No Orphan Product Detection**:
   - When loading campaign for editing
   - No check if product IDs still exist
   - Deleted products not filtered out

2. **No UI Indication**:
   - Product picker loads products by ID
   - If product deleted, WooCommerce API returns empty
   - But ID still in array
   - UI shows: "Product #20" (no name, no data)
   - User confused

3. **No Category Validation**:
   - Same issue for `category_ids`
   - Category deleted → IDs remain in campaign
   - Discount fails silently

4. **No Cleanup on Save**:
   - Opportunity to clean orphaned IDs when user saves
   - Not implemented

5. **No Historical Data**:
   - If product deleted, no record of what it was
   - Campaign logs/reports show: "Product #20 (Unknown)"
   - Better: Store product name/SKU for historical reference

#### Recommendations:

1. **Validate Product IDs on Load**:
```php
// In change-tracker or wizard-state-service
function get_step_data($step) {
    $data = // ... load from DB or session

    if ($step === 'products' && isset($data['product_ids'])) {
        $data['product_ids'] = $this->filter_valid_products($data['product_ids']);
    }

    return $data;
}

function filter_valid_products($product_ids) {
    return array_filter($product_ids, function($product_id) {
        $product = wc_get_product($product_id);
        return $product && $product->exists();
    });
}
```

2. **Show Deleted Products in UI**:
```javascript
// In products-picker.js
function loadSelectedProducts(productIds) {
    $.ajax({
        url: ajaxurl,
        data: {
            action: 'scd_get_products_by_ids',
            ids: productIds
        }
    }).done(function(response) {
        response.products.forEach(function(product) {
            if (product.deleted) {
                // Show deleted product with warning
                var $item = $('<div class="product-item deleted">')
                    .append('<span class="product-name">' + product.name + ' (Deleted)</span>')
                    .append('<button class="remove-product">Remove</button>');
                $('.selected-products').append($item);
            } else {
                // Show normal product
                // ...
            }
        });
    });
}
```

3. **Clean Orphans on Save**:
```php
// In campaign-compiler-service or wizard complete handler
function clean_product_data($data) {
    if (isset($data['product_ids'])) {
        $valid_products = array_filter($data['product_ids'], function($id) {
            $product = wc_get_product($id);
            return $product && $product->exists();
        });

        $removed_count = count($data['product_ids']) - count($valid_products);
        if ($removed_count > 0) {
            error_log(sprintf(
                '[Campaign Save] Removed %d deleted product(s) from campaign',
                $removed_count
            ));
        }

        $data['product_ids'] = array_values($valid_products);
    }

    return $data;
}
```

4. **Store Product Metadata**:
```php
// When campaign created/updated, store product info
function store_product_metadata($campaign_id, $product_ids) {
    $metadata = array();

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $metadata[$product_id] = array(
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'stored_at' => current_time('mysql')
            );
        }
    }

    update_post_meta($campaign_id, '_scd_product_metadata', $metadata);
}

// For historical display
function get_product_display_name($product_id, $campaign_id) {
    $product = wc_get_product($product_id);

    if ($product && $product->exists()) {
        return $product->get_name();
    }

    // Product deleted, try to get from metadata
    $metadata = get_post_meta($campaign_id, '_scd_product_metadata', true);
    if (isset($metadata[$product_id])) {
        return $metadata[$product_id]['name'] . ' (Deleted)';
    }

    return 'Unknown Product #' . $product_id;
}
```

5. **Category Validation** (Same Pattern):
```php
function filter_valid_categories($category_ids) {
    return array_filter($category_ids, function($category_id) {
        $category = get_term($category_id, 'product_cat');
        return $category && !is_wp_error($category);
    });
}
```

---

## Summary of Findings

### Critical Issues (Fix Immediately) 🔴

1. **Concurrent Editing** (#1, #7):
   - Backend has optimistic locking ✅
   - Frontend has NO awareness ⚠️
   - Users get generic error on conflict
   - No conflict resolution UI
   - **Impact**: Data loss, user frustration

2. **Session Expiration** (#2):
   - PHP timeout (2 hours) ≠ JavaScript timeout (1 hour)
   - No grace period or extension prompt
   - No auto-save before expiration
   - **Impact**: Unexpected data loss

3. **Data Loss Prevention** (#3):
   - No `beforeunload` warning
   - No localStorage backup
   - No periodic auto-save
   - **Impact**: Browser crash = lost work

4. **Change Tracker Cleanup** (#8):
   - Session cleared on success only
   - No cleanup on failure or abandon
   - **Impact**: Memory leaks, stale data

### High Priority (Fix Soon) 🟡

5. **Network Failures** (#6):
   - No client-side retry logic
   - No offline detection
   - No request queueing
   - **Impact**: Poor UX during network issues

6. **Timezone Validation** (#9):
   - DST transitions not validated
   - Invalid timezones not caught early
   - **Impact**: Campaigns scheduled at invalid times

7. **Orphaned Products** (#10):
   - Deleted products remain in campaigns
   - No cleanup or validation
   - **Impact**: Discounts fail silently

### Well-Handled ✅

8. **Partial Updates** (#4):
   - Change tracker stores deltas only ✅
   - Merges with DB baseline on compile ✅
   - No unnecessary overwrites ✅

9. **Validation** (#5):
   - Strong type checking ✅
   - Required fields enforced ✅
   - Conditional visibility ✅
   - Minor gaps: boundary values

---

## Recommendations Priority

### Phase 1 (Critical - Do First)

1. **Add frontend version conflict handling**
   - Detect version mismatches
   - Show user-friendly resolution modal
   - Allow refresh with merge option

2. **Align session timeouts**
   - Make PHP and JavaScript match
   - Add 10-minute warning before expiration
   - Implement session extension

3. **Implement auto-save**
   - Every 60 seconds if changes detected
   - localStorage backup every 10 seconds
   - Restore on crash recovery

4. **Fix change tracker cleanup**
   - Clear on success AND failure
   - Add "Discard Changes" button
   - Implement cleanup cron

### Phase 2 (High Priority)

5. **Add network resilience**
   - Client-side retry with exponential backoff
   - Offline mode detection
   - Request queueing

6. **Enhance timezone handling**
   - Validate DST transitions
   - Show UTC offset in UI
   - Warn about ambiguous times

7. **Product/category validation**
   - Filter deleted products on load
   - Show "Deleted" badge in UI
   - Clean orphans on save

### Phase 3 (Nice to Have)

8. **Improve validation edge cases**
   - Add explicit min/max bounds
   - Better empty string vs null handling
   - Date range validation

9. **Add campaign locking**
   - Advisory lock on edit start
   - Show "User X is editing" banner
   - Prevent concurrent edits proactively

---

## Testing Checklist

To verify fixes, test these scenarios:

### Concurrent Editing
- [ ] Two users edit same campaign simultaneously
- [ ] Second user sees conflict message
- [ ] Conflict resolution modal shows diff
- [ ] User can reload and merge changes

### Session Expiration
- [ ] Session warning appears 10 min before expiry
- [ ] User can extend session
- [ ] Auto-save prevents data loss
- [ ] Graceful handling of expired session

### Data Loss Prevention
- [ ] Browser close warns of unsaved changes
- [ ] Browser crash → Data restored on reload
- [ ] Auto-save runs every 60 seconds
- [ ] localStorage backup works

### Network Failures
- [ ] Offline mode activates when network drops
- [ ] Failed requests retry automatically
- [ ] User sees clear error messages
- [ ] Requests queue and retry on reconnect

### Timezone Edge Cases
- [ ] DST transition times rejected with clear error
- [ ] Invalid timezone shows error
- [ ] UTC offset displayed in UI
- [ ] Times converted correctly across timezones

### Orphaned Products
- [ ] Deleted products filtered out on load
- [ ] Deleted products show "Deleted" badge in UI
- [ ] Campaign save removes orphaned IDs
- [ ] Historical data preserved for reports

---

## Code Locations Reference

| Component | File Path |
|-----------|-----------|
| Optimistic Locking | `/includes/database/repositories/class-campaign-repository.php` (Lines 447-492) |
| Change Tracker | `/includes/core/wizard/class-campaign-change-tracker.php` |
| State Service | `/includes/core/wizard/class-wizard-state-service.php` |
| Complete Handler | `/includes/core/wizard/class-complete-wizard-handler.php` |
| Wizard Orchestrator | `/resources/assets/js/wizard/wizard-orchestrator.js` |
| Step Persistence | `/resources/assets/js/shared/mixins/step-persistence.js` |
| Validation Manager | `/resources/assets/js/validation/validation-manager.js` |
| Campaign Entity | `/includes/core/campaigns/class-campaign.php` (Lines 228-235 for version) |

---

## Conclusion

The campaign editing workflow has a **solid foundation** with optimistic locking, session-based change tracking, and transaction support. However, there are **critical gaps** in:

1. **User feedback** for concurrent edits and version conflicts
2. **Data loss prevention** for unexpected exits
3. **Network resilience** for unstable connections
4. **Cleanup mechanisms** for abandoned sessions

Implementing the Phase 1 recommendations will significantly improve data integrity and user experience. The system is production-ready with these fixes in place.

**Estimated Effort**:
- Phase 1 (Critical): 2-3 days
- Phase 2 (High Priority): 2-3 days
- Phase 3 (Nice to Have): 1-2 days

**Total**: 5-8 days of development work.
