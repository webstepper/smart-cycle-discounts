# State Management Standard

## Overview

Smart Cycle Discounts uses a standardized state management pattern based on `SCD.Shared.BaseState` for consistency, predictability, and maintainability across all modules.

---

## Decision Tree

```
Need state management?
├─ YES
│  ├─ Is it wizard or step state?
│  │  └─ YES → Use BaseState (extends SCD.Shared.BaseState)
│  ├─ Does it need subscribers/history?
│  │  └─ YES → Use BaseState
│  ├─ Is it > 5 properties or complex state?
│  │  └─ YES → Use BaseState
│  └─ Simple utility with < 3 properties?
│     └─ YES → Plain object acceptable
└─ NO → Stateless function/utility
```

---

## Architecture

### BaseState (`resources/assets/js/shared/base-state.js`)

**Foundation class** providing:
- ✅ Change tracking
- ✅ Subscriber pattern (pub/sub)
- ✅ History/undo/redo support
- ✅ Batch updates
- ✅ Immutability helpers

### State Classes Hierarchy

```
SCD.Shared.BaseState (Foundation)
  │
  ├─ SCD.Wizard.StateManager (Wizard-level state)
  │
  ├─ SCD.Modules.Basic.State (Basic step state)
  ├─ SCD.Modules.Products.State (Products step state)
  ├─ SCD.Modules.Discounts.State (Discounts step state)
  ├─ SCD.Modules.Schedule.State (Schedule step state)
  └─ SCD.Modules.Review.State (Review step state)
```

---

## Implementation Examples

### 1. Wizard State Manager (Singleton Pattern)

**File:** `resources/assets/js/wizard/wizard-state-manager.js`

```javascript
/**
 * Wizard State Manager - Singleton extending BaseState
 */
SCD.Wizard.StateManager = function() {
    // Return existing instance (singleton)
    if (SCD.Wizard.StateManager._instance) {
        return SCD.Wizard.StateManager._instance;
    }

    // Define initial state
    var initialState = {
        hasUnsavedChanges: false,
        isSaving: false,
        stepData: {
            basic: {},
            products: {},
            discounts: {},
            schedule: {},
            review: {}
        }
    };

    // Call BaseState constructor
    SCD.Shared.BaseState.call(this, initialState);

    // Store singleton instance
    SCD.Wizard.StateManager._instance = this;
};

// Inherit from BaseState
SCD.Wizard.StateManager.prototype = Object.create(SCD.Shared.BaseState.prototype);
SCD.Wizard.StateManager.prototype.constructor = SCD.Wizard.StateManager;

// Singleton getter
SCD.Wizard.StateManager.getInstance = function() {
    if (!SCD.Wizard.StateManager._instance) {
        SCD.Wizard.StateManager._instance = new SCD.Wizard.StateManager();
    }
    return SCD.Wizard.StateManager._instance;
};
```

**Usage:**
```javascript
// Get singleton instance
var stateManager = SCD.Wizard.StateManager.getInstance();

// Update state
stateManager.setState({ hasUnsavedChanges: true });

// Get state
var state = stateManager.getState();
var isSaving = stateManager.get('isSaving');

// Subscribe to changes
stateManager.subscribe(function(changes) {
    // changes = {property: 'hasUnsavedChanges', newValue: true, oldValue: false, state: {...}}
    console.log('hasUnsavedChanges changed to:', changes.newValue);
}, 'hasUnsavedChanges'); // Filter: only notify when hasUnsavedChanges changes
```

### 2. Step State (Instance Pattern)

**File:** `resources/assets/js/steps/products/products-state.js`

```javascript
/**
 * Products State Constructor
 * Extends BaseState for state management
 */
SCD.Modules.Products.State = function() {
    // Define initial state
    var initialState = {
        productSelectionType: 'all_products',
        productIds: [],
        categoryIds: ['all'],
        conditions: []
    };

    // Call BaseState constructor
    SCD.Shared.BaseState.call(this, initialState);

    // Initialize event manager
    this.initEventManager();
};

// Inherit from BaseState
SCD.Modules.Products.State.prototype = Object.create(SCD.Shared.BaseState.prototype);
SCD.Modules.Products.State.prototype.constructor = SCD.Modules.Products.State;

// Custom methods
SCD.Utils.extend(SCD.Modules.Products.State.prototype, {
    /**
     * Override setState for custom normalization
     */
    setState: function(updates, batch) {
        // Normalize arrays
        if (updates.productIds !== undefined) {
            updates.productIds = Array.isArray(updates.productIds) ? updates.productIds : [];
        }

        // Call parent setState
        SCD.Shared.BaseState.prototype.setState.call(this, updates, batch);
    }
});
```

**Usage:**
```javascript
// Create instance
var productsState = new SCD.Modules.Products.State();

// Update state
productsState.setState({
    productSelectionType: 'specific_products',
    productIds: [1, 2, 3]
});

// Get state
var selectedProducts = productsState.get('productIds');

// Subscribe to specific changes
productsState.subscribe(function(changes) {
    // changes = {property: 'productIds', newValue: [1,2,3], oldValue: [], state: {...}}
    // React to productIds changes
    console.log('Selected products:', changes.newValue);
}, 'productIds');
```

---

## BaseState API Reference

### Core Methods

#### `setState(updates, batch)`
Update state with change tracking and notifications.

```javascript
// Single update
state.setState({ isSaving: true });

// Multiple updates
state.setState({
    isSaving: false,
    lastSavedAt: new Date().toISOString()
});

// Batch update (silent, no notifications)
state.setState({ debugFlag: true }, true);
```

#### `getState(key)`
Get current state or specific property.

```javascript
// Get entire state
var fullState = state.getState();

// Get specific property
var isSaving = state.getState('isSaving');
```

#### `subscribe(callback, filter)`
Subscribe to state changes.

**IMPORTANT**: The callback receives ONE parameter - a changes object with different structures:
- Individual updates: `{property, value, oldValue, newValue, state}`
- Batch updates: `{batch: true, changes: {...}, state: {...}}`
- Reset: `{reset: true, state: {...}}`

```javascript
// Subscribe to all changes
var unsubscribe = state.subscribe(function(changes) {
    console.log('State changed:', changes);

    // Access new state
    var newState = changes.state;

    // For individual updates
    if (changes.property) {
        console.log('Property:', changes.property);
        console.log('Old value:', changes.oldValue);
        console.log('New value:', changes.newValue);
    }

    // For batch updates
    if (changes.batch) {
        console.log('Batch changes:', changes.changes);
    }
});

// Subscribe to specific property (only fires for that property)
state.subscribe(function(changes) {
    // changes.property === 'isSaving'
    console.log('isSaving changed to:', changes.newValue);
}, 'isSaving');

// Subscribe to multiple properties
state.subscribe(function(changes) {
    // Fires when either property changes
    if ('isSaving' === changes.property) {
        console.log('isSaving:', changes.newValue);
    } else if ('hasUnsavedChanges' === changes.property) {
        console.log('hasUnsavedChanges:', changes.newValue);
    }
}, ['isSaving', 'hasUnsavedChanges']);

// Unsubscribe
unsubscribe();
```

#### `reset()`
Reset state to initial values.

```javascript
state.reset();
```

### History Methods

#### `undo()`
Undo last state change.

```javascript
state.undo();
```

#### `redo()`
Redo last undone change.

```javascript
state.redo();
```

#### `canUndo()`
Check if undo is available.

```javascript
if (state.canUndo()) {
    state.undo();
}
```

#### `canRedo()`
Check if redo is available.

```javascript
if (state.canRedo()) {
    state.redo();
}
```

---

## Patterns and Best Practices

### ✅ DO

```javascript
// Use setState() for mutations
state.setState({ counter: state.get('counter') + 1 });

// Subscribe to specific changes for performance
state.subscribe(callback, 'specificProperty');

// Use batch updates for multiple changes
state.setState({
    prop1: value1,
    prop2: value2,
    prop3: value3
}, true); // Batch mode - single notification

// Check state before mutations
if (state.get('isValid')) {
    state.setState({ isSaving: true });
}
```

### ❌ DON'T

```javascript
// Don't mutate state directly
state._state.counter++; // ❌ Bad

// Don't access _state property
var value = state._state.myProperty; // ❌ Bad - use getState()

// Don't create unnecessary subscriptions
state.subscribe(function() {
    // Heavy computation on every change
}); // ❌ Bad - filter for specific properties

// Don't forget to unsubscribe
var unsubscribe = state.subscribe(callback);
// ... later, when component unmounts
// unsubscribe(); // ❌ Forgot to call - memory leak
```

---

## Testing State

### Unit Test Example

```javascript
describe('Products State', function() {
    var state;

    beforeEach(function() {
        state = new SCD.Modules.Products.State();
    });

    it('should initialize with default state', function() {
        expect(state.get('productSelectionType')).toBe('all_products');
        expect(state.get('productIds')).toEqual([]);
    });

    it('should update state via setState', function() {
        state.setState({ productIds: [1, 2, 3] });
        expect(state.get('productIds')).toEqual([1, 2, 3]);
    });

    it('should notify subscribers on change', function() {
        var callback = jasmine.createSpy('callback');
        state.subscribe(callback, 'productIds');

        state.setState({ productIds: [1, 2, 3] });

        expect(callback).toHaveBeenCalled();
    });

    it('should support undo/redo', function() {
        state.setState({ productIds: [1] });
        state.setState({ productIds: [1, 2] });

        state.undo();
        expect(state.get('productIds')).toEqual([1]);

        state.redo();
        expect(state.get('productIds')).toEqual([1, 2]);
    });
});
```

---

## Migration Guide

### Migrating from Object Literal to BaseState

**Before (Object Literal):**
```javascript
var MyState = {
    state: {
        counter: 0
    },

    set: function(updates) {
        $.extend(this.state, updates);
    },

    get: function() {
        return this.state;
    }
};

// Usage
MyState.set({ counter: 1 });
var value = MyState.get().counter;
```

**After (BaseState):**
```javascript
var MyState = function() {
    var initialState = {
        counter: 0
    };

    SCD.Shared.BaseState.call(this, initialState);
};

MyState.prototype = Object.create(SCD.Shared.BaseState.prototype);
MyState.prototype.constructor = MyState;

// Usage
var myState = new MyState();
myState.setState({ counter: 1 });
var value = myState.get('counter');
```

---

## Common Pitfalls

### 1. Forgetting getInstance() for Singleton

```javascript
// ❌ Wrong
SCD.Wizard.StateManager.setState({ ... }); // Error: not a function

// ✅ Correct
SCD.Wizard.StateManager.getInstance().setState({ ... });
```

### 2. Mutating Nested Objects

```javascript
// ❌ Wrong
var state = stateManager.getState();
state.stepData.basic.name = 'New Name'; // Direct mutation!

// ✅ Correct
stateManager.setState({
    stepData: {
        basic: { name: 'New Name' }
    }
});
```

### 3. Over-Subscribing

```javascript
// ❌ Wrong - subscribes to ALL changes
state.subscribe(function(newState, oldState, updates) {
    if (updates.specificProperty) {
        // Only care about this one property
    }
});

// ✅ Correct - filter subscription
state.subscribe(function(newState, oldState, updates) {
    // Only called when specificProperty changes
}, 'specificProperty');
```

### 4. Prototype Inheritance Implementation

**IMPORTANT**: BaseState uses conditional initialization to support prototypal inheritance.

The BaseState constructor checks if initialization methods exist before calling them:

```javascript
// BaseState constructor (internal implementation)
SCD.Shared.BaseState = function( initialState ) {
    // ... property initialization ...

    // Conditional initialization for child class compatibility
    if ( this._createProxy ) {
        this._createProxy();
    }
    if ( this._saveHistory ) {
        this._saveHistory();
    }
};
```

**Why This Matters:**

When extending BaseState, the prototype methods are available because:
1. Your child class prototype is set up **before** any instances are created
2. The `BaseState.call(this, initialState)` executes with `this` having the full prototype chain
3. The conditional checks ensure safe initialization

**Example - Correct Pattern:**

```javascript
// 1. Define constructor
SCD.MyState = function() {
    var initialState = { count: 0 };

    // Call BaseState constructor
    SCD.Shared.BaseState.call(this, initialState);

    // Add custom properties AFTER BaseState initialization
    this.myCustomProperty = 'value';
};

// 2. Set up prototype inheritance BEFORE creating instances
SCD.MyState.prototype = Object.create(SCD.Shared.BaseState.prototype);
SCD.MyState.prototype.constructor = SCD.MyState;

// 3. Now safe to create instances
var myState = new SCD.MyState();
```

**❌ WRONG - Don't do this:**

```javascript
// ❌ Creating instance before prototype setup
var myState = new SCD.MyState();  // Prototype not set up yet!

// Prototype setup after instance creation (too late!)
SCD.MyState.prototype = Object.create(SCD.Shared.BaseState.prototype);
```

**Key Principle**: Always define the prototype chain **before** creating any instances.

---

## Performance Tips

1. **Use filtered subscriptions** - Subscribe to specific properties instead of all changes
2. **Batch updates** - Use batch mode for multiple setState() calls
3. **Unsubscribe** - Always unsubscribe when components unmount
4. **Avoid heavy computations** - Keep subscriber callbacks light

---

## Summary

- ✅ **ALL wizard and step state** uses BaseState pattern
- ✅ **Wizard.StateManager** uses singleton pattern via getInstance()
- ✅ **Step states** create instances via `new` keyword
- ✅ **Consistent API** across all state management
- ✅ **Change tracking** built-in for debugging
- ✅ **Subscriber pattern** for reactive updates
- ✅ **History support** for undo/redo functionality

This standardization provides:
- **Consistency** - Same pattern everywhere
- **Predictability** - Known behavior
- **Testability** - Easy to mock and test
- **Debuggability** - Change tracking and history
- **Maintainability** - Single pattern to understand
