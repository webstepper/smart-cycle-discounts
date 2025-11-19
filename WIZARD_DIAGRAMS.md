# Campaign Wizard - Visual Architecture Diagrams

## 1. System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     WIZARD SYSTEM                            │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌───────────────────────────────────────────────────────┐   │
│  │          FRONTEND (JavaScript/Browser)                │   │
│  ├───────────────────────────────────────────────────────┤   │
│  │                                                        │   │
│  │  ┌─────────────────────────────────────────────────┐  │   │
│  │  │  SCD.Wizard.Orchestrator (Main Coordinator)     │  │   │
│  │  │  ├─ State Manager (App-level state)             │  │   │
│  │  │  ├─ Event Bus (Cross-component events)          │  │   │
│  │  │  ├─ Navigation Service (Step navigation)        │  │   │
│  │  │  └─ Step Orchestrators (Per-step managers)      │  │   │
│  │  │     ├─ Basic Step                               │  │   │
│  │  │     ├─ Products Step                            │  │   │
│  │  │     ├─ Discounts Step                           │  │   │
│  │  │     ├─ Schedule Step                            │  │   │
│  │  │     └─ Review Step                              │  │   │
│  │  │        ├─ State (Module)                        │  │   │
│  │  │        ├─ API (Module)                          │  │   │
│  │  │        └─ Fields (Module)                       │  │   │
│  │  └─────────────────────────────────────────────────┘  │   │
│  │                      │                                 │   │
│  │                      ↓                                 │   │
│  │  ┌─────────────────────────────────────────────────┐  │   │
│  │  │  Form UI Templates                              │  │   │
│  │  │  ├─ step-basic.php                              │  │   │
│  │  │  ├─ step-products.php                           │  │   │
│  │  │  ├─ step-discounts.php                          │  │   │
│  │  │  ├─ step-schedule.php                           │  │   │
│  │  │  └─ step-review.php                             │  │   │
│  │  └─────────────────────────────────────────────────┘  │   │
│  │                                                        │   │
│  └───────────────────────────────────────────────────────┘   │
│                            │                                  │
│                            ↓                                  │
│                    AJAX Communication                         │
│                 (scd_save_step action)                        │
│                (wizard_navigation action)                     │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│          BACKEND (PHP/WordPress)                            │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌───────────────────────────────────────────────────────┐   │
│  │         AJAX Handlers & Controllers                   │   │
│  ├───────────────────────────────────────────────────────┤   │
│  │                                                        │   │
│  │  ┌──────────────────────────────────────────────────┐ │   │
│  │  │  SCD_Save_Step_Handler                          │ │   │
│  │  │  ├─ Idempotency Check (prevent duplicates)      │ │   │
│  │  │  ├─ Data Transform (snake_case conversion)      │ │   │
│  │  │  ├─ Validation (using SCD_Validation)           │ │   │
│  │  │  ├─ License Check (Pro features)                │ │   │
│  │  │  └─ State Persistence (via state_service)       │ │   │
│  │  └──────────────────────────────────────────────────┘ │   │
│  │                                                        │   │
│  │  ┌──────────────────────────────────────────────────┐ │   │
│  │  │  SCD_Wizard_Manager                             │ │   │
│  │  │  ├─ Step Definition Registry                    │ │   │
│  │  │  ├─ Validation Rules (per step)                 │ │   │
│  │  │  ├─ Navigation Logic (can_navigate_to)          │ │   │
│  │  │  ├─ Progress Tracking                           │ │   │
│  │  │  └─ Campaign Compilation                        │ │   │
│  │  └──────────────────────────────────────────────────┘ │   │
│  │                                                        │   │
│  │  ┌──────────────────────────────────────────────────┐ │   │
│  │  │  SCD_Complete_Wizard_Handler                    │ │   │
│  │  │  ├─ Compile all step data                       │ │   │
│  │  │  ├─ Handle edit mode (merge DB + changes)       │ │   │
│  │  │  ├─ Create/Update campaign                      │ │   │
│  │  │  └─ Clear session                               │ │   │
│  │  └──────────────────────────────────────────────────┘ │   │
│  │                                                        │   │
│  └───────────────────────────────────────────────────────┘   │
│                            │                                  │
│                            ↓                                  │
│  ┌───────────────────────────────────────────────────────┐   │
│  │    State & Persistence Layer                        │   │
│  ├───────────────────────────────────────────────────────┤   │
│  │                                                        │   │
│  │  ┌──────────────────────────────────────────────────┐ │   │
│  │  │  SCD_Wizard_State_Service                        │ │   │
│  │  │  ├─ Session Management (create, load, save)      │ │   │
│  │  │  ├─ WordPress Transients (session storage)       │ │   │
│  │  │  ├─ Secure Cookies (session ID)                  │ │   │
│  │  │  ├─ Locking (prevent race conditions)            │ │   │
│  │  │  └─ Change Tracker Integration (edit mode)       │ │   │
│  │  └──────────────────────────────────────────────────┘ │   │
│  │                                                        │   │
│  │  ┌──────────────────────────────────────────────────┐ │   │
│  │  │  SCD_Campaign_Change_Tracker (Edit Mode)         │ │   │
│  │  │  ├─ Track field changes (not full campaign)      │ │   │
│  │  │  ├─ Merge with database values (on read)         │ │   │
│  │  │  ├─ Compile deltas on save                       │ │   │
│  │  │  └─ Timestamp each change                        │ │   │
│  │  └──────────────────────────────────────────────────┘ │   │
│  │                                                        │   │
│  └───────────────────────────────────────────────────────┘   │
│                            │                                  │
│                            ↓                                  │
│  ┌───────────────────────────────────────────────────────┐   │
│  │         Database Layer                               │   │
│  ├───────────────────────────────────────────────────────┤   │
│  │                                                        │   │
│  │  WordPress Transients (scd_wizard_session_*)          │   │
│  │  SCD Campaigns Table (scd_campaigns)                  │   │
│  │                                                        │   │
│  └───────────────────────────────────────────────────────┘   │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## 2. Step Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                      STEP LIFECYCLE                              │
└─────────────────────────────────────────────────────────────────┘

1. STEP LOAD
   └─> Wizard.loadCurrentStep(stepName)
       └─> createStepOrchestrator(stepName)
           └─> BaseOrchestrator.init()
               ├─> initializeStep()  (create state, api, fields modules)
               ├─> bindEvents()      (attach event handlers)
               ├─> onInit()          (custom initialization)
               └─> populateFields()  (load data from server)
                   └─> StepPersistence.populateFields(data)

2. USER INTERACTION
   └─> User fills form fields
       ├─> Field change → emit scd:step:field:changed
       │   └─> State.setData() → update state
       │       └─> State subscribers notified
       │           └─> ValidationManager validates field
       │               └─> Display error if invalid
       └─> (Optional) Auto-save triggered
           └─> StepPersistence.collectData()
               └─> State.getState()
           └─> StepPersistence.validateData()
               └─> State.validate()
           └─> StepPersistence.saveStep()
               └─> API.saveStepData(data)
                   └─> BaseAPI.request('scd_save_step', ...)
                       └─> AJAX to PHP handler
                           └─> SCD_Save_Step_Handler
                               └─> Persist to session

3. NAVIGATION (Next/Previous)
   └─> User clicks navigation button
       ├─> validateCurrentStep()
       ├─> collectData()
       ├─> saveStep()
       └─> NavigationService.navigateToStep(nextStep)
           └─> AJAX: wizard_navigation action
               └─> Server validates can_navigate_to()
               └─> Returns next step data
           └─> Unload current step orchestrator
               └─> destroy() → cleanup modules
           └─> Load next step orchestrator
               └─> Same as Step Load (above)

4. FINAL REVIEW & COMPLETION
   └─> User clicks "Create Campaign" button
       ├─> collectAllStepsData()
       ├─> validateAllSteps()
       └─> AJAX: scd_complete_wizard or similar
           └─> SCD_Complete_Wizard_Handler
               ├─> Get all session step data
               ├─> Compile into campaign data
               ├─> Handle edit mode (merge DB + changes)
               ├─> Validate compiled data
               ├─> Create/Update campaign in DB
               ├─> Clear wizard session
               └─> Return campaign_id, redirect_url
           └─> Frontend redirects to campaigns view

5. STEP DESTRUCTION
   └─> Orchestrator.destroy()
       ├─> Unsubscribe from all events
       ├─> Clear all timers
       ├─> Destroy all modules
       └─> Clean up DOM references
```

## 3. State Flow (Create Mode)

```
┌────────────────────────────────────────────────────────────┐
│               CREATE MODE STATE FLOW                        │
└────────────────────────────────────────────────────────────┘

FRONTEND STATE LAYERS:
┌─────────────────────────────────────────────────────────────┐
│ 1. WIZARD STATE (Application-level)                        │
│    SCD.Wizard.StateManager                                 │
│    ├─ completedSteps: ['basic', 'products']               │
│    ├─ campaignName: 'Summer Sale 2025'                    │
│    ├─ wizardMode: 'create'                                │
│    └─ stepData: (UI state only, loaded from server)       │
│                                                             │
│ 2. STEP STATE (Per-step local state)                      │
│    SCD.Modules.Basic.State                                │
│    ├─ name: 'Summer Sale 2025'                            │
│    ├─ description: '...'                                  │
│    └─ _isDirty: true                                      │
│                                                             │
│ 3. SESSION FORM STATE (Temporary)                         │
│    HTML form fields                                       │
│    ├─ <input name="campaign_name" value="..." />         │
│    └─ (Synced with Step State)                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
          AJAX Send to Backend (scd_save_step)
                            ↓
BACKEND STATE LAYERS:
┌─────────────────────────────────────────────────────────────┐
│ 1. SESSION (PHP-side)                                      │
│    SCD_Wizard_State_Service                               │
│    ├─ session_id: 'abc123...'                            │
│    ├─ created_at: 1234567890                             │
│    ├─ updated_at: 1234567900                             │
│    ├─ completed_steps: ['basic']                          │
│    └─ steps: {                                            │
│         'basic': {                                        │
│             'name': 'Summer Sale 2025',                  │
│             'description': '...'                         │
│         }                                                 │
│    }                                                      │
│                                                           │
│    Storage: WordPress Transient                          │
│    Key: scd_wizard_session_abc123...                    │
│    TTL: 7200 seconds (2 hours)                          │
│                                                           │
│ 2. COOKIE (Session ID only)                              │
│    scd_wizard_session: 'abc123...'                      │
│    Flags: HttpOnly, Secure, SameSite                    │
│                                                           │
│ 3. DATABASE (Not touched until completion)               │
│    scd_campaigns table                                   │
│    ← Campaign NOT created yet                           │
└─────────────────────────────────────────────────────────────┘
```

## 4. State Flow (Edit Mode)

```
┌────────────────────────────────────────────────────────────┐
│               EDIT MODE STATE FLOW                          │
└────────────────────────────────────────────────────────────┘

PHASE 1: INITIALIZATION
┌──────────────────────────────────────────────┐
│ User navigates to: ?page=campaigns&id=42    │
│                   &action=edit               │
└──────────────────────────────────────────────┘
                    ↓
    Wizard init with intent='edit', id=42
                    ↓
BACKEND:
┌──────────────────────────────────────────────────┐
│ 1. Load Campaign from DB                       │
│    SCD_Campaign_Manager::get(42)               │
│    ├─ Load full campaign object                │
│    ├─ Populate step data from campaign         │
│    └─ Return campaign_id, is_edit_mode=true   │
│                                                 │
│ 2. Create Change Tracker                      │
│    new SCD_Campaign_Change_Tracker(42, ...)   │
│                                                 │
│ 3. Initialize Session with Edit Context       │
│    ├─ Set campaign_id = 42                    │
│    ├─ Set is_edit_mode = true                │
│    ├─ Mark ALL steps as completed (can       │
│    │  navigate freely between any steps)      │
│    └─ Load campaign data to template          │
│                                                 │
│ Session Data:                                 │
│ {                                             │
│   'campaign_id': 42,                         │
│   'is_edit_mode': true,                      │
│   'completed_steps': ['basic','products',    │
│                       'discounts','schedule'],│
│   'steps': { /* initially empty */ },        │
│   'changes': {}  ← Change Tracker data       │
│ }                                             │
└──────────────────────────────────────────────────┘

PHASE 2: RENDERING STEP
┌──────────────────────────────────────────────────┐
│ Frontend loads step (e.g., basic)               │
│                                                 │
│ AJAX: Fetch step data                         │
│ GET /wp-admin/admin-ajax.php?action=load_step│
│     &step=basic&campaign_id=42                │
│                                                 │
│ BACKEND returns:                              │
│ {                                             │
│   'name': 'Existing Campaign',                │
│   'description': '...',                       │
│   'priority': 3,                              │
│   ... (all current field values from DB)     │
│ }                                             │
│                                                 │
│ FRONTEND populates fields with these values  │
└──────────────────────────────────────────────────┘

PHASE 3: EDITING & SAVING
┌──────────────────────────────────────────────────────────┐
│ User modifies: name "Existing" → "Updated"              │
│                                                          │
│ FRONTEND: collectData() → {name: 'Updated'}            │
│           saveStep() → AJAX scd_save_step              │
│                                                          │
│ BACKEND: SCD_Save_Step_Handler                         │
│          ├─ Idempotency check (no duplicate)           │
│          ├─ Data transform (snake_case)                │
│          ├─ Validate against rules                      │
│          ├─ License check                              │
│          └─ save_step_data('basic', data)              │
│                                                          │
│             In save_step_data():                        │
│             if(is_edit_mode && change_tracker) {       │
│                 change_tracker->track_step(...)        │
│                 // Stores ONLY delta:                  │
│                 // changes['basic']['name'] = ...      │
│                 // Database NOT modified               │
│             }                                           │
│                                                          │
│ SESSION after save:                                    │
│ {                                              │
│   'campaign_id': 42,                           │
│   'is_edit_mode': true,                        │
│   'changes': {                                 │
│       'basic': {                               │
│           'name': {                            │
│               'value': 'Updated',              │
│               'timestamp': 1234567900          │
│           }                                    │
│       }                                        │
│   }                                            │
│ }                                              │
│                                                │
│ DATABASE: ← Campaign 42 UNCHANGED              │
└──────────────────────────────────────────────────────────┘

PHASE 4: COMPLETING WIZARD
┌──────────────────────────────────────────────────────────┐
│ User clicks "Save Campaign"                             │
│                                                          │
│ AJAX: scd_complete_wizard                              │
│                                                          │
│ BACKEND: SCD_Complete_Wizard_Handler                   │
│          ├─ Get session data (campaign_id=42,          │
│          │  is_edit_mode=true)                         │
│          ├─ Load current campaign from DB              │
│          │  ├─ Get all field values from DB            │
│          │  └─ Create campaign object                  │
│          │                                              │
│          ├─ Get changes from Change Tracker            │
│          │  changes['basic']['name'] = 'Updated'       │
│          │                                              │
│          ├─ MERGE database values + changes            │
│          │  {                                          │
│          │    'name': 'Updated',    (from change)      │
│          │    'description': '...',  (from DB, no change)
│          │    'priority': 3,         (from DB, no change)
│          │    ... other fields unchanged from DB        │
│          │  }                                           │
│          │                                              │
│          ├─ Validate merged data                       │
│          ├─ UPDATE campaign 42 in DB with merged data  │
│          ├─ Clear session                              │
│          └─ Return success                             │
│                                                          │
│ DATABASE: ← Campaign 42 UPDATED with new values        │
│ SESSION: ← CLEARED                                      │
│                                                          │
│ FRONTEND redirects to campaign view                    │
└──────────────────────────────────────────────────────────┘
```

## 5. Data Structure Reference

```
┌────────────────────────────────────────────────────┐
│           SESSION DATA STRUCTURE                    │
└────────────────────────────────────────────────────┘

PHP (SCD_Wizard_State_Service):
{
    "session_id": "a1b2c3d4e5f6...",      // 32-char random
    "created_at": 1609459200,              // Unix timestamp
    "updated_at": 1609459300,              // Extended by activity
    "campaign_id": 0,                      // 0=create, >0=edit
    "is_edit_mode": false,                 // true in edit mode
    "completed_steps": [                   // Track progress
        "basic",
        "products"
    ],
    "steps": {                             // Step data
        "basic": {
            "name": "Summer Sale 2025",
            "description": "...",
            "priority": 3
        },
        "products": {
            "product_selection_type": "all_products",
            "selected_product_ids": []
        },
        "discounts": {
            "discount_type": "percentage",
            "discount_value_percentage": 15
        },
        "schedule": {
            "start_type": "immediate",
            "start_date": "2025-06-01",
            "end_date": "2025-06-30"
        }
    },
    "changes": {                           // Edit mode only
        "basic": {
            "name": {
                "value": "Updated Name",
                "timestamp": 1609459300
            }
        }
    },
    "prefilled_from_suggestion": false,    // Campaign suggestions
    "from_suggestion": null
}

JavaScript (SCD.Wizard.StateManager):
{
    sessionValid: true,
    sessionVersion: 0,
    completedSteps: ["basic", "products"],
    visitedSteps: ["basic", "products", "discounts"],
    availableSteps: ["basic", "products", "discounts", "schedule", "review"],
    hasUnsavedChanges: true,
    isDirty: true,
    isSaving: false,
    isLoading: false,
    isValid: false,
    validationErrors: {
        "campaign_name": "Campaign name is required"
    },
    stepData: {
        basic: {
            name: "Summer Sale 2025",
            description: "..."
        },
        products: {
            product_selection_type: "all_products",
            selected_product_ids: []
        },
        discounts: { ... },
        schedule: { ... },
        review: { ... }
    },
    sidebarCollapsed: false,
    debugMode: false,
    campaignId: null,
    campaignName: "Summer Sale 2025",
    wizardMode: "create",
    startedAt: "2025-01-01T12:00:00Z",
    lastSavedAt: null,
    lastActivityAt: "2025-01-01T12:05:00Z",
    features: {
        smartSave: true,
        validation: true,
        preview: true,
        debug: false
    }
}
```

## 6. AJAX Communication Flows

```
┌─────────────────────────────────────────────────────────┐
│         SAVE STEP AJAX FLOW                              │
└─────────────────────────────────────────────────────────┘

FRONTEND REQUEST:
┌────────────────────────────────────────────────┐
│ POST /wp-admin/admin-ajax.php                 │
│ Content-Type: application/x-www-form-urlencoded
│                                                │
│ action=scd_save_step                          │
│ nonce=ABC123...                               │
│ step=basic                                    │
│ data[name]=Campaign Name                     │
│ data[description]=...                         │
│ data[priority]=3                              │
└────────────────────────────────────────────────┘
            ↓
BACKEND PROCESSING:
┌────────────────────────────────────────────────────────┐
│ 1. VALIDATION                                         │
│    ├─ Check nonce (wp_verify_nonce)                  │
│    ├─ Check capability (current_user_can)            │
│    └─ Check action is registered                     │
│                                                       │
│ 2. HANDLER EXECUTION                                 │
│    SCD_Save_Step_Handler::handle()                  │
│    ├─ Idempotency check (is this a retry?)          │
│    ├─ Input validation                              │
│    ├─ Data transformation (camelCase ↔ snake_case) │
│    ├─ Business logic validation                     │
│    ├─ License check (Pro features)                  │
│    ├─ State service initialization                  │
│    └─ save_step_data() call                         │
│                                                       │
│ 3. STATE PERSISTENCE                                │
│    state_service->save_step_data('basic', data)    │
│    ├─ Prepare data                                  │
│    ├─ Check if edit mode                           │
│    ├─ If edit: use change_tracker->track_step()   │
│    ├─ If create: save to session['steps']['basic']│
│    └─ Save session to transient                    │
│                                                       │
│ 4. RESPONSE                                         │
│    wp_send_json_success({                          │
│        'message': 'Step saved',                    │
│        'step': 'basic',                            │
│        'progress': {                               │
│            'completed_steps': ['basic'],           │
│            'current_step': 'basic',               │
│            'percentage': 20                       │
│        },                                          │
│        'data': { ... }                            │
│    })                                              │
└────────────────────────────────────────────────────────┘
            ↓
FRONTEND RESPONSE HANDLING:
┌────────────────────────────────────────────────┐
│ $.ajax().done(function(response) {             │
│     if(response.success) {                     │
│         ├─ Update Wizard.StateManager          │
│         ├─ Emit EventBus('step:saved')         │
│         ├─ Show success notification           │
│         └─ Enable navigation to next step      │
│     } else {                                   │
│         ├─ Show validation errors              │
│         ├─ Display error notification          │
│         └─ Keep current step                   │
│     }                                          │
│ }).fail(function(error) {                      │
│     ├─ Log error                               │
│     ├─ Show error notification                 │
│     └─ Enable retry                            │
│ });                                            │
└────────────────────────────────────────────────┘
```

## 7. Navigation Logic

```
┌──────────────────────────────────────────────────────┐
│        NAVIGATION CONSTRAINT RULES                    │
└──────────────────────────────────────────────────────┘

Current: basic | Target: products
───────────────────────────────────

1. Is target a valid step?
   YES → Continue to step 2
   NO  → REJECT

2. Is target the first step?
   YES → ALLOW (can always go to first)
   NO  → Continue to step 3

3. Is target already completed?
   YES → ALLOW (can return to completed steps)
   NO  → Continue to step 4

4. Is target the next step?
   YES → Continue to step 5
   NO  → REJECT (can't skip steps)

5. Is current step completed?
   YES → ALLOW (can proceed to next if current is done)
   NO  → REJECT (must complete current step first)

═══════════════════════════════════════════════════════

EXAMPLE SCENARIOS:

Scenario 1: Navigate from Basic to Products (sequential)
├─ Valid step? YES
├─ First step? NO
├─ Already completed? NO (first visit)
├─ Next step? YES (basic → products is next)
├─ Current completed? YES (basic is marked complete)
└─ Result: ALLOWED ✓

Scenario 2: Navigate from Products to Discounts (sequential)
├─ Valid step? YES
├─ First step? NO
├─ Already completed? NO
├─ Next step? YES
├─ Current completed? YES
└─ Result: ALLOWED ✓

Scenario 3: Navigate from Basic to Schedule (skip steps)
├─ Valid step? YES
├─ First step? NO
├─ Already completed? NO
├─ Next step? NO (would skip products, discounts)
└─ Result: REJECTED ✗

Scenario 4: Return from Products to Basic (backtrack)
├─ Valid step? YES
├─ First step? YES
├─ Result: ALLOWED ✓

Scenario 5: Return from Discounts to Products (backtrack)
├─ Valid step? YES
├─ First step? NO
├─ Already completed? YES (visited before)
├─ Result: ALLOWED ✓
```

