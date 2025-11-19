# Smart Cycle Discounts Campaign Wizard System - Comprehensive Analysis

## 1. WIZARD ARCHITECTURE OVERVIEW

### Core Components

The wizard is a multi-step form system for creating and editing WooCommerce discount campaigns. It consists of:

1. **PHP Backend (Orchestration)**: `SCD_Wizard_Manager`
2. **PHP Session State**: `SCD_Wizard_State_Service`
3. **JavaScript Frontend**: `SCD.Wizard.Orchestrator` + Step-specific modules
4. **AJAX Communication**: `SCD_Save_Step_Handler` and navigation handlers

### Step Flow
```
Basic Info → Products → Discounts → Schedule → Review → Campaign Creation
```

---

## 2. PHP WIZARD CONTROLLER SYSTEM

### 2.1 Main Wizard Manager (`class-wizard-manager.php`)

**Responsibilities:**
- Manages wizard lifecycle (initialize, process, navigate)
- Defines step definitions with validation rules
- Handles step completion and navigation logic
- Compiles wizard data into campaign data
- Manages session/state service

**Key Methods:**
```php
public function __construct(
    ?SCD_Wizard_State_Service $state_service = null,
    ?object $security = null,
    ?object $campaign_repository = null
)

public function initialize( array $options = array() ): string
    // Returns session_id, creates new wizard session
    
public function process_step( string $step, array $data ): array
    // Validates and saves step data, returns success/error
    // Validates using: SCD_Validation::validate()
    // Marks step as completed
    // Auto-saves progress
    
public function navigate_to_step( string $step ): array
    // Navigates to target step if allowed
    // Checks can_navigate_to() constraints
    
public function complete_wizard(): array
    // Final step: compiles all data, creates campaign
    // Returns campaign_id, redirect_url
    
public function get_all_campaign_data(): array
    // Collects data from basic, products, discounts, schedule steps
    
public function get_progress(): array
    // Returns: completed_steps, percentage, steps_info
```

**Step Definitions Structure:**
```php
$steps = array(
    'basic'     => array(
        'title'            => 'Basic Information',
        'description'      => '...',
        'template'         => 'step-basic.php',
        'required_fields'  => array( 'name' ),
        'validation_rules' => array(
            'name' => 'required|min:3|max:100',
            'description' => 'max:1000'
        ),
    ),
    // ... other steps
);
```

### 2.2 Wizard State Service (`class-wizard-state-service.php`)

**Responsibility**: Manages all wizard session state with database persistence

**Key Characteristics:**
- Uses WordPress transients as session storage (prefix: `scd_wizard_session_`)
- Session lifetime: 7200 seconds (2 hours), extends with activity
- Secure HTTP-only cookies for session ID
- Locking mechanism to prevent race conditions
- Deferred save queue for high-concurrency scenarios

**Key Methods:**
```php
public function create(): string
    // Creates new session, returns session_id
    // Stores in transient + cookie
    
public function initialize_with_intent( 
    string $intent = 'continue',
    ?string $suggestion_id = null
): void
    // Intent: 'new', 'continue', or 'edit'
    // Handles fresh sessions, edit mode, campaign pre-filling
    
public function save_step_data( string $step, array $data ): bool
    // Saves data for a step
    // In edit mode: uses Change Tracker to store only deltas
    // In create mode: stores full data in session
    
public function get_step_data( string $step ): array
    // Gets data for step
    // In edit mode: merges DB data + Change Tracker deltas
    // In create mode: returns session data
    
public function mark_step_complete( string $step ): bool
    // Marks step as completed in progress tracking
    
public function get_progress(): array
    // Returns: completed_steps, percentage, can_complete
    
public function save( bool $force = false ): bool
    // Persists session to database with locking
    // Uses transient-based locking to prevent race conditions
    // force=true bypasses lock for critical operations
    
public function clear_session(): void
    // Clears all session data and cookies
```

**Session Data Structure:**
```php
array(
    'session_id'      => '...',      // Random 32-char string
    'created_at'      => time(),
    'updated_at'      => time(),
    'steps'           => array(      // Step-specific data
        'basic'     => array( ... ),
        'products'  => array( ... ),
        ...
    ),
    'completed_steps' => array( 'basic', 'products' ),
    'campaign_id'     => 0,           // Only in edit mode
    'is_edit_mode'    => false,
    'changes'         => array(),     // Edit mode deltas (Change Tracker)
)
```

### 2.3 Campaign Change Tracker (Edit Mode)

**Purpose**: Solves "session as database" anti-pattern by tracking only field changes

**When Used:**
- User is editing an existing campaign
- `is_edit_mode = true` in session
- `campaign_id` is set

**How It Works:**
```php
public function track_step( $step, $data )
    // Stores only changed fields with timestamp
    // Reads from database on demand
    
public function get_step_data( $step ): array
    // Returns: merged(database values + changed values)
    // Deltas override database values
    
public function compile(): array
    // Merges database campaign with accumulated changes
    // Used when completing the wizard
```

**Data Structure:**
```php
changes: array(
    'basic' => array(
        'name' => array(
            'value'     => 'New Name',
            'timestamp' => 1234567890
        ),
        ...
    ),
    'products' => array( ... )
)
```

### 2.4 Wizard Step Registry (`class-wizard-step-registry.php`)

**Simple static registry** for step metadata:
```php
public static function get_steps()              // array
public static function get_step_labels()        // array
public static function get_step_label($step)    // string
public static function is_valid_step($step)     // bool
public static function get_next_step($current)  // string|'complete'
public static function get_step_index($step)    // int|false
public static function get_progress_percentage($step) // float 0-100
```

---

## 3. JAVASCRIPT FRONTEND SYSTEM

### 3.1 Wizard Architecture Pattern

**Layer Structure:**
```
Wizard Facade (SCD.Wizard)
    ↓
Wizard Orchestrator (SCD.Wizard.Orchestrator)
    ↓
Base Orchestrator (SCD.Shared.BaseOrchestrator)
    ├── State Manager (SCD.Wizard.StateManager)
    ├── Event Bus (SCD.Wizard.EventBus)
    ├── Navigation Service (SCD.Wizard.Navigation)
    └── Step Orchestrators (SCD.Steps.BasicOrchestrator, etc.)
        ├── State (SCD.Modules.Basic.State)
        ├── API (SCD.Modules.Basic.API)
        └── Fields (SCD.Modules.Basic.Fields)
```

### 3.2 Wizard Orchestrator (`wizard-orchestrator.js`)

**Main Entry Point for Wizard Coordination**

```javascript
SCD.Wizard.Orchestrator = function() {
    // Extends BaseOrchestrator
    
    this.stepOrchestrators = {};      // Per-step managers
    this.isInternalNavigation = false; // Track navigation type
    this.config = {
        steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
        sessionTimeout: 7200000  // 2 hours
    };
};

// Core Methods:
WizardOrchestrator.prototype.init() 
    // Initializes all modules + loads current step

WizardOrchestrator.prototype.loadCurrentStep()
    // Creates and initializes the current step orchestrator

WizardOrchestrator.prototype.createStepOrchestrator(stepName)
    // Factory: creates step-specific orchestrator

WizardOrchestrator.prototype.onStepLoaded(stepName)
    // Called after step is loaded - populates fields from server data
```

### 3.3 Base Orchestrator (`shared/base-orchestrator.js`)

**Common behavior for all orchestrators** (wizard + steps)

```javascript
SCD.Shared.BaseOrchestrator = function(stepName, moduleFactories)
    // stepName: 'basic', 'products', etc.
    // moduleFactories: object with factory functions for modules

// Key Methods:
prototype.init(wizard, config)
    // Initializes modules, binds events, calls onInit()

prototype.bindEvents()
    // Binds DOM events + custom events

prototype.populateFields(data)
    // Populates form fields from data
    // Calls StepPersistence mixin

prototype.collectData()
    // Collects all step data from form
    // Calls StepPersistence mixin

prototype.validateData()
    // Validates collected data
    // Returns { valid: bool, errors: object }

prototype.saveStep()
    // AJAX call to save step data
    // Calls modules.api.saveStepData()

prototype.destroy()
    // Cleanup: removes event handlers, clears modules
```

### 3.4 Step Pattern - Example: Basic Step

**Three-Module Pattern per Step:**

#### State Module (basic-state.js)
```javascript
SCD.Modules.Basic.State = function() {
    // Extends BaseState with initial state from field definitions
    SCD.Shared.BaseState.call(this, initialState);
    
    // Subscribe to changes for validation
    this.subscribe(function(changes) {
        // Validate field when it changes
        ValidationManager.validateField(changes.property, changes.newValue);
    });
};

// Methods inherited from BaseState:
prototype.getState()              // Returns state copy
prototype.setData(updates)        // Updates state
prototype.validate()              // Full validation
prototype.reset()                 // Clear state
```

#### API Module (basic-api.js)
```javascript
SCD.Modules.Basic.API = function(config) {
    SCD.Shared.BaseAPI.call(this, config);
    this.stepName = 'basic';
};

// Methods (inherit from BaseAPI):
prototype.checkCampaignName(name, excludeId)
    // AJAX: verify campaign name uniqueness
    // Returns: jQuery Promise

prototype.saveStepData(data)
    // AJAX: save step to session via scd_save_step action
    // Returns: jQuery Promise
```

#### Orchestrator Module (basic-orchestrator.js)
```javascript
SCD.Steps.BasicOrchestrator = BaseOrchestrator.createStep('basic', {
    
    initializeStep: function() {
        // Create the three modules
        this.modules.state = new SCD.Modules.Basic.State();
        this.modules.api = new SCD.Modules.Basic.API();
        this.modules.fields = new SCD.Modules.Basic.Fields(this.modules.state);
    },
    
    onBindEvents: function() {
        // Listen for field changes: scd:basic:field:changed
        // Update state when field changes
    },
    
    onStepLoaded: function() {
        // Initialize fields, trigger scd:basic:ready event
    },
    
    reset: function() {
        // Reset state and fields
    }
});
```

### 3.5 State Management Pattern (`shared/base-state.js`)

**Reactive State Container**

```javascript
SCD.Shared.BaseState = function(initialState) {
    this._state = {};              // Internal state
    this._subscribers = [];         // Change listeners
    this._isDirty = false;
    this._history = [];            // Undo/redo support
};

// Methods:
prototype.getState()              // Get copy of state
prototype.getData(fieldName)       // Get single field
prototype.setState(updates, batch) // Update state
prototype.setData(key, value)      // Set single field

prototype.subscribe(callback, filter)
    // Watch for changes
    // filter: fieldName or array of names
    // Returns: unsubscribe function

prototype.validate()               // Full validation
prototype.reset()                  // Clear to initial
prototype.isDirty()                // Has unsaved changes
```

**Subscriber Notifications:**
```javascript
// When state changes:
this._notifySubscribers({
    property: 'campaign_name',
    oldValue: 'Old',
    newValue: 'New',
    timestamp: now
});
```

### 3.6 API Communication Pattern (`shared/base-api.js`)

**Centralized AJAX with Error Handling**

```javascript
SCD.Shared.BaseAPI = function(config) {
    this.config = {
        ajaxUrl: scdAjax.ajaxUrl,
        nonce: scdAjax.nonce,
        timeout: 30000
    };
    this._pendingRequests = {};  // Deduplication
};

// Core Method (used by all step APIs):
prototype.request(action, data, options)
    // action: AJAX action name (e.g., 'scd_save_step')
    // data: request payload
    // options: { timeout, retry }
    
    // Returns: jQuery Promise
    // 
    // Features:
    // - Deduplication: same request only fires once
    // - Auto error handling via ErrorHandler
    // - Tracking of pending requests
    // - Timeout handling

// Shortcut Methods:
prototype.post(action, data, options)
prototype.get(action, data, options)

prototype.cancelPendingRequests()
    // Abort all in-flight requests
```

**Usage in Step API:**
```javascript
SCD.Modules.Basic.API.prototype.saveStepData = function(data) {
    return this.request('scd_save_step', {
        step: 'basic',
        data: data
    });
};
```

### 3.7 Wizard State Manager (`wizard/wizard-state-manager.js`)

**Application-level State** (separate from step states)

```javascript
SCD.Wizard.StateManager = {
    state: {
        // Session
        sessionValid: true,
        sessionVersion: 0,
        
        // Navigation
        completedSteps: [],      // ['basic', 'products']
        visitedSteps: [],
        availableSteps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
        
        // Data
        hasUnsavedChanges: false,
        isDirty: false,
        isSaving: false,
        isLoading: false,
        
        // Validation
        isValid: true,
        validationErrors: {},
        
        // Step data
        stepData: {
            basic: {},
            products: {},
            discounts: {},
            schedule: {},
            review: {}
        },
        
        // UI
        sidebarCollapsed: false,
        debugMode: false,
        
        // Metadata
        campaignId: null,
        campaignName: '',
        wizardMode: 'create'  // or 'edit'
    },
    
    // Methods:
    init(initialState),
    get(key),                    // Get state value (dot notation)
    set(key, value),             // Set state value
    subscribe(callback),         // Watch for changes
    loadFromStorage(),            // Load from sessionStorage
    saveToStorage(),              // Save to sessionStorage
    clearStorage(),
    undo(),                      // Undo last change
    redo()                       // Redo
};
```

### 3.8 Event Bus Pattern (`wizard/wizard-event-bus.js`)

**Centralized Event System** for wizard-wide communication

```javascript
SCD.Wizard.EventBus = {
    namespaces: {
        wizard: 'scd-wizard',
        steps: 'scd-wizard-steps',
        validation: 'scd-wizard-validation',
        ajax: 'scd-wizard-ajax',
        ui: 'scd-wizard-ui'
    },
    
    // Methods:
    init(),
    emit(eventName, data, options),
        // Emits event
        // Triggers jQuery event: $(doc).trigger()
        // Calls direct listeners
    
    on(eventName, handler, options),
        // Listen to event
        // Returns: unsubscribe function
    
    off(eventName, handler),
    once(eventName, handler),
    
    // Event History:
    history,          // Log of all events
    stats             // { emitted, handled, errors }
};

// Example Usage:
EventBus.emit('step:saved', {
    step: 'basic',
    data: {...}
}, { source: 'save-handler' });

EventBus.on('step:saved', function(e, data) {
    console.log('Step saved:', data);
});
```

### 3.9 Wizard Navigation Service (`wizard/wizard-navigation.js`)

**Handles Step Navigation Logic**

```javascript
NavigationService = {
    config: {
        steps: [ 'basic', 'products', 'discounts', 'schedule', 'review' ],
        ajaxAction: 'wizard_navigation'
    },
    
    // Methods:
    init(),
    navigateToStep(stepName),
        // AJAX call to wizard_navigation action
        // Validates can navigate
        // Loads step
        // Returns: jQuery Promise
    
    canNavigateTo(step),
        // Checks if navigation allowed
        
    validateCurrentStep(),
        // Validates current step before navigation
        // Returns: jQuery Promise
};
```

### 3.10 StepPersistence Mixin Pattern

**Automatic Data Persistence** for steps (added to BaseOrchestrator)

```javascript
SCD.Shared.StepPersistence = {
    // Automatically added to step orchestrators
    
    // Methods:
    collectData(),        // Gather from form fields
    populateFields(data), // Fill form fields
    validateData(),       // Validate form
    saveStep(),           // Save to server
    getData(),            // Get current step data
    setData(updates),     // Update step data
    isDirty(),            // Has unsaved changes
    isValid(),            // Is currently valid
    showErrors(errors)    // Display validation errors
};
```

---

## 4. DATA FLOW & COMMUNICATION

### 4.1 Save Step Flow

**Frontend to Backend:**

1. **Collect**: `orchestrator.collectData()` gathers data from form fields
2. **Validate**: `orchestrator.validateData()` validates against rules
3. **Save**: `orchestrator.saveStep()` calls `api.saveStepData(data)`
4. **AJAX**: `BaseAPI.request()` sends to `scd_save_step` action
5. **Payload**:
   ```javascript
   {
       action: 'scd_save_step',
       nonce: '...',
       data: {
           step: 'basic',
           data: {
               name: 'Campaign Name',
               description: '...',
               // ... step fields
           }
       }
   }
   ```

**Backend Processing:**

1. **Handler**: `SCD_Save_Step_Handler::handle()` receives request
2. **Idempotency**: Check duplicate via `SCD_Idempotency_Service`
3. **Transform**: `SCD_Step_Data_Transformer` converts data format
4. **Validate**: `SCD_Validation::validate()` with step context
5. **License**: `SCD_Feature_Gate` checks PRO features
6. **Save**: `state_service->save_step_data($step, $data)`
   - In edit mode: uses `SCD_Campaign_Change_Tracker` to store deltas
   - In create mode: stores in session
7. **Response**: Returns success + progress data

### 4.2 Navigation Flow

**User clicks Next/Previous:**

1. **Click Handler**: Navigation button clicked
2. **Validate**: Current step validated (if enabled)
3. **Save**: Current step saved (if has changes)
4. **AJAX**: Call `wizard_navigation` action with target step
5. **Server Validation**: Checks step order, permissions
6. **Response**: Returns new step data + progress
7. **Frontend**: Unloads current step, loads new step orchestrator

### 4.3 Complete Campaign Flow

**User clicks "Create Campaign" (final step):**

1. **Collect**: All step data collected
2. **Compile**: `SCD_Campaign_Compiler_Service` merges step data
3. **AJAX**: Call `scd_complete_wizard` or `scd_complete_campaign_creation`
4. **Handler**: `SCD_Complete_Wizard_Handler::handle()`
   - Gets all step data from session
   - Compiles into campaign data structure
   - Handles edit mode: merges database + changes
   - Creates/updates campaign via `SCD_Campaign_Manager`
   - Clears session
5. **Response**: Returns campaign_id, redirect_url
6. **Frontend**: Redirect to campaigns list or campaign view

### 4.4 Editing Campaign Flow

**User edits existing campaign:**

1. **Init**: `SCD_Wizard_State_Service->initialize_with_intent('edit', campaign_id)`
2. **Load Campaign**: Fetches campaign from database
3. **Change Tracker**: Creates `SCD_Campaign_Change_Tracker` instance
4. **Mark Steps Complete**: All steps marked completed (can navigate freely)
5. **Session Data**: Store campaign_id, is_edit_mode = true
6. **Save Changes**: When step saved:
   - Only deltas stored in Change Tracker (not full campaign)
   - Database not updated yet
7. **Complete**: When wizard completed:
   - Compiles: database values + change deltas
   - Updates campaign in database
   - Clears session

---

## 5. STEP IMPLEMENTATION PATTERN

### 5.1 Creating a New Step

**Template files needed:**
```
resources/views/admin/wizard/step-{stepname}.php     # HTML template
resources/assets/js/steps/{stepname}/{stepname}-orchestrator.js
resources/assets/js/steps/{stepname}/{stepname}-state.js
resources/assets/js/steps/{stepname}/{stepname}-api.js
resources/assets/js/steps/{stepname}/{stepname}-fields.js (optional)
```

**Step Orchestrator (required):**
```javascript
SCD.Steps.YourStepOrchestrator = BaseOrchestrator.createStep('yourstep', {
    initializeStep: function() {
        this.modules.state = new SCD.Modules.YourStep.State();
        this.modules.api = new SCD.Modules.YourStep.API();
        this.modules.fields = new SCD.Modules.YourStep.Fields(this.modules.state);
    },
    
    onBindEvents: function() {
        var self = this;
        this.bindCustomEvent('scd:yourstep:field:changed', function(e, data) {
            if(data && data.field && self.modules.state) {
                var update = {};
                update[data.field] = data.value;
                self.modules.state.setData(update);
            }
        });
    },
    
    onStepLoaded: function() {
        if(!this.modules || 0 === Object.keys(this.modules).length) {
            this.initializeStep();
        }
        if(this.modules.fields && 'function' === typeof this.modules.fields.init) {
            this.modules.fields.init();
        }
        this.triggerCustomEvent('scd:yourstep:ready', [this.modules]);
    },
    
    reset: function() {
        if(this.modules.state) {
            this.modules.state.reset();
        }
        if(this.modules.fields) {
            this.modules.fields.resetFields();
        }
        this.triggerCustomEvent('scd:yourstep:reset', []);
    }
});
```

**Step State (required):**
```javascript
SCD.Modules.YourStep.State = function() {
    var initialState = {};
    var fields = SCD.FieldDefinitions.yourstep;
    if(fields) {
        for(var fieldName in fields) {
            if(Object.prototype.hasOwnProperty.call(fields, fieldName)) {
                initialState[fieldName] = $.extend(
                    true, 
                    {}, 
                    fields[fieldName].default
                );
            }
        }
    }
    
    SCD.Shared.BaseState.call(this, initialState);
};

SCD.Modules.YourStep.State.prototype = Object.create(
    SCD.Shared.BaseState.prototype
);
SCD.Modules.YourStep.State.prototype.constructor = SCD.Modules.YourStep.State;

$.extend(SCD.Modules.YourStep.State.prototype, {
    validate: function() {
        // Validation logic
        return { valid: true, errors: {} };
    }
});
```

**Step API (required):**
```javascript
SCD.Modules.YourStep.API = function(config) {
    if(window.SCD && window.SCD.Shared && window.SCD.Shared.BaseAPI) {
        SCD.Shared.BaseAPI.call(this, config);
    }
    this.stepName = 'yourstep';
};

if(window.SCD && window.SCD.Shared && window.SCD.Shared.BaseAPI) {
    SCD.Modules.YourStep.API.prototype = Object.create(
        SCD.Shared.BaseAPI.prototype
    );
    SCD.Modules.YourStep.API.prototype.constructor = SCD.Modules.YourStep.API;
}

SCD.Modules.YourStep.API.prototype.saveStepData = function(data) {
    return this.request('scd_save_step', {
        step: 'yourstep',
        data: data
    });
};
```

### 5.2 Sidebar Pattern (step-review)

Some steps have sidebars for additional information:

```javascript
// In class-sidebar-base.php
SCD_Wizard_Sidebar::register_sidebar('review', array(
    'title' => 'Campaign Review',
    'class' => 'SCD_Sidebar_Review',
    'dependency' => 'state_service'
));
```

---

## 6. KEY DESIGN PATTERNS

### 6.1 Module Factory Pattern

```javascript
// Creating modules using factory
SCD.Shared.BaseOrchestrator.createStep('basic', {
    // Module factories that are called once during init
    stateManager: function() { return instance; }
});
```

### 6.2 Mixin Pattern

Step orchestrators get mixins:
- `EventManager`: emit/bindCustomEvent/triggerCustomEvent
- `StepPersistence`: collectData/populateFields/validateData/saveStep

### 6.3 Service Locator

```javascript
// Access modules through orchestrator
orchestrator.modules.state
orchestrator.modules.api
orchestrator.modules.fields
```

### 6.4 Observer/Subscriber Pattern

State changes trigger callbacks:
```javascript
state.subscribe(function(changes) {
    // Called when state changes
    // changes: { property, oldValue, newValue }
});
```

### 6.5 Lazy Loading

- Modules loaded on-demand
- Step orchestrators created when step activated
- Campaign data loaded from database when entering edit mode

---

## 7. VALIDATION SYSTEM

### Field Validation Rules (PHP)

Defined in `class-wizard-manager.php`:
```php
'validation_rules' => array(
    'name' => 'required|min:3|max:100',
    'description' => 'max:1000',
    'product_selection_type' => 'required|in:all_products,specific_products'
)
```

### Runtime Validation (JavaScript)

Uses centralized `ValidationManager`:
```javascript
ValidationManager.validateField('campaign_name', value, {
    stepId: 'basic',
    allValues: state,
    visibilityMap: null
});
```

### Display Validation Errors

Using `ValidationError` component:
```javascript
SCD.ValidationError.show($field, 'This field is required');
SCD.ValidationError.showMultiple(errors, $container, {
    clearFirst: true,
    showSummary: true  // Also shows NotificationService banner
});
```

---

## 8. CRITICAL PATTERNS TO FOLLOW

### When Creating New Features

**1. Don't use `sessionStorage` for campaign data**
   - Use PHP session service
   - JavaScript state is for UI only

**2. Always use Change Tracker in edit mode**
   - Track deltas, not full campaign copies
   - Merge with DB on completion

**3. Event-driven architecture**
   - Use EventBus for cross-component communication
   - Never access orchestrator modules directly from other steps

**4. State is immutable**
   - Call setState() with new objects
   - Don't mutate state directly

**5. Always handle AJAX errors**
   - Use BaseAPI.request() for auto error handling
   - Errors automatically logged to NotificationService

**6. Namespacing is critical**
   - Wizard steps: `SCD.Modules.StepName.*`
   - Wizard-wide: `SCD.Wizard.*`
   - Shared: `SCD.Shared.*`

**7. Proper cleanup**
   - Unsubscribe from events
   - Clear timers
   - Call destroy() on orchestrators

**8. Use centralized services**
   - ErrorHandler for errors
   - NotificationService for messages
   - ValidationManager for validation
   - No ad-hoc implementations

---

## 9. DATA TRANSFORMATION REFERENCES

### Step Data Transform (`SCD_Step_Data_Transformer`)
```php
// Converts between field names:
// JavaScript: camelCase (campaignName)
// PHP/Database: snake_case (campaign_name)
// Form inputs: snake_case (for WordPress compatibility)

// Automatic conversion happens in transformer
// No manual conversion needed in handlers
```

### Field Definitions
```javascript
// JavaScript (camelCase)
SCD.FieldDefinitions.basic = {
    campaignName: { default: '', type: 'text' },
    description: { default: '', type: 'textarea' }
};

// Map to HTML form (snake_case)
<input name="campaign_name" value="" />
```

---

## 10. TESTING CONSIDERATIONS

### Test Points

1. **Step Navigation**: Verify can_navigate_to() logic
2. **Session Persistence**: Data survives page reload
3. **Edit Mode**: Changes tracked correctly, DB not affected until save
4. **Draft Campaigns**: Can resume incomplete wizards
5. **Validation**: Rules enforced, errors displayed
6. **Pro Features**: Gated behind license check
7. **Idempotency**: Duplicate saves handled
8. **Race Conditions**: Session locking prevents conflicts

### Session Timeout
- 2 hours (7200 seconds)
- Extended by activity (updated_at timestamp)
- Auto-save keeps sessions alive

---

## 11. COMMON ISSUES & SOLUTIONS

### Issue: Campaign data not saving

**Debug:**
1. Check session validity: `state_service->has_session()`
2. Check state_service initialization: must be injected in handler
3. Verify AJAX action registered
4. Check nonce validation

### Issue: Edit mode doesn't preserve original data

**Solution:**
- Change Tracker must be initialized
- Campaign loaded before changes tracked
- get_step_data() must check Change Tracker first

### Issue: Field values not populating

**Debug:**
1. Check field definitions exist
2. Verify populateFields() called
3. Check data structure matches field names
4. Confirm StepPersistence mixin present

### Issue: Validation not working

**Verify:**
1. ValidationManager loaded
2. Field definitions include validation rules
3. Validation context set correctly
4. ValidationError component initialized

---

## Summary

The wizard is a sophisticated, modular system with:
- **Separation of concerns**: PHP backend, JavaScript frontend, AJAX communication
- **Reusable patterns**: BaseOrchestrator, BaseState, BaseAPI, modules
- **State management**: Session server-side, UI state client-side
- **Edit mode support**: Change Tracker for non-destructive editing
- **Validation system**: Centralized rules, distributed error display
- **Event-driven**: No tight coupling between steps
- **Service locator**: All components injectable and testable

