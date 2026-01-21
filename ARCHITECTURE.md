# Smart Cycle Discounts Plugin - Architecture & Patterns Analysis

## Executive Summary

The Smart Cycle Discounts plugin is a sophisticated WordPress/WooCommerce discount management system built with modern enterprise-grade architecture. It employs:
- **Advanced Dependency Injection Container** with automatic constructor resolution
- **Service-Oriented Architecture** with 70+ registered services
- **Modular Admin System** with page controllers and AJAX routing
- **MVC-inspired Pattern** separating views from business logic
- **Centralized Asset Management** with registries and localization
- **Abstract AJAX Handler** pattern with built-in security

---

## 1. OVERALL PLUGIN ARCHITECTURE

### 1.1 Bootstrap & Initialization Flow

**Entry Point:** `/smart-cycle-discounts.php` (main plugin file)

```
Plugin File (smart-cycle-discounts.php)
    ↓ Defines constants (SCD_VERSION, SCD_PLUGIN_DIR, etc.)
    ↓
Main Class (SCD_Smart_Cycle_Discounts)
    ↓ Loads Loader, Service Container, Service Definitions
    ↓
SCD_Container (Dependency Injection)
    ↓ Resolves all services via reflection-based constructor injection
    ↓
SCD_Admin (Admin Manager)
    ↓ Registers all admin hooks, pages, AJAX routes
```

**Key Files:**
- `/includes/class-smart-cycle-discounts.php` - Main singleton class
- `/includes/class-loader.php` - Hook registration system
- `/includes/bootstrap/class-container.php` - DI container
- `/includes/bootstrap/class-service-definitions.php` - Service registry

### 1.2 Service Container (Dependency Injection)

**File:** `/includes/bootstrap/class-container.php`

**Features:**
- PHP 8.1+ typed properties and union types
- **Automatic Constructor Injection** via reflection
- Detects constructor parameters and resolves them automatically
- Supports singleton and factory patterns
- Circular dependency detection
- Service aliases and tagging system

**Usage Pattern:**
```php
// Register a service with automatic DI
$container->singleton('campaign_manager', SCD_Campaign_Manager::class);

// Resolve service (constructor dependencies auto-injected)
$campaign_manager = $container->get('campaign_manager');
// If SCD_Campaign_Manager has __construct(SCD_Campaign_Repository $repo, SCD_Logger $logger)
// Both are automatically resolved and injected
```

**Core Methods:**
- `bind($abstract, $concrete, $singleton)` - Register a service
- `singleton($abstract, $concrete)` - Register as singleton
- `factory($abstract, callable)` - Register factory function
- `get($id)` - Resolve service
- `has($id)` - Check if service exists
- `resolve($abstract, $parameters)` - Resolve with explicit params

### 1.3 Class Naming Conventions

**PHP Classes (snake_case with SCD_ prefix):**
```
SCD_Campaign_Manager           // Main business logic
SCD_Campaign_Repository        // Database abstraction
SCD_Admin_Asset_Manager        // Admin asset handling
WSSCD_Abstract_Ajax_Handler      // Base AJAX class
```

**File Organization:**
```
/includes/
├── /admin/                    # Admin-specific functionality
├── /ajax/                     # AJAX handlers and routing
├── /assets/                   # Asset management classes
├── /bootstrap/                # DI container and service definitions
├── /cache/                    # Caching layer
├── /core/                     # Core business logic
│   ├── /analytics/           # Analytics processing
│   ├── /campaigns/           # Campaign management
│   ├── /discounts/           # Discount application
│   ├── /products/            # Product selection
│   ├── /wizard/              # Campaign creation wizard
├── /database/                 # Database abstraction
│   ├── /repositories/        # Data access objects
│   └── /migrations/          # Schema versioning
├── /integrations/            # WooCommerce, email, blocks
├── /security/                # Security utilities
└── /utilities/               # Helper functions
```

### 1.4 Service Container Registry

**File:** `/includes/bootstrap/class-service-definitions.php`

**Structure:** Array of service definitions with metadata:
```php
'campaign_manager' => array(
    'class'        => 'SCD_Campaign_Manager',
    'singleton'    => true,
    'dependencies' => array('campaign_repository', 'logger', 'cache_manager'),
    'factory'      => function($container) {
        return new SCD_Campaign_Manager(
            $container->get('campaign_repository'),
            $container->get('logger'),
            $container->get('cache_manager'),
            $container
        );
    },
),
```

**Key Service Categories:**

1. **Core Services** (15+)
   - `logger` - Logging system
   - `cache_manager` - Caching layer
   - `database_manager` - DB abstraction
   - `security_manager` - Security utilities

2. **Campaign Services** (10+)
   - `campaign_manager` - CRUD operations
   - `campaign.calculator` - Analytics
   - `campaign.state_manager` - State tracking
   - `campaign_health_service` - Health monitoring

3. **Admin Services** (8+)
   - `admin_manager` - Admin orchestration
   - `admin_asset_manager` - Asset loading
   - `ajax_router` - AJAX routing
   - `campaigns_page`, `tools_page`, `notifications_page`

4. **Wizard Services** (5+)
   - `wizard_manager` - Wizard orchestration
   - `wizard_state_service` - Session state
   - `idempotency_service` - Replay protection

5. **Dashboard Services** (6+)
   - `main_dashboard_page` - Dashboard controller
   - `dashboard_service` - Data aggregation
   - `analytics_dashboard` - Analytics data
   - `campaign_health_service` - Health metrics

---

## 2. ADMIN COMPONENT PATTERNS

### 2.1 Page Architecture

**Pattern:** Page Class → Controller → View

**Example: Campaigns Page**

File: `/includes/admin/pages/class-campaigns-page.php`

```php
class SCD_Campaigns_Page {
    private $container;
    private $list_controller;
    private $edit_controller;
    private $wizard_controller;
    
    public function __construct($container) {
        $this->container = $container;
    }
    
    public function render() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        match($action) {
            'list' => $this->get_list_controller()->handle(),
            'edit' => $this->get_edit_controller()->handle(),
            'wizard' => $this->get_wizard_controller()->handle(),
            default => $this->show_list(),
        };
    }
    
    private function get_list_controller() {
        if (!$this->list_controller) {
            $this->list_controller = $this->container->get('campaign_list_controller');
        }
        return $this->list_controller;
    }
}
```

**Key Page Classes:**
1. `SCD_Campaigns_Page` - Campaign list, edit, wizard
2. `SCD_Main_Dashboard_Page` - Main dashboard
3. `SCD_Analytics_Page` - Analytics dashboard
4. `SCD_Tools_Page` - Maintenance and tools
5. `SCD_Notifications_Page` - Email notifications

### 2.2 Controller Pattern

**File:** `/includes/core/campaigns/class-campaign-list-controller.php`

Controllers handle:
1. **Capability checking** - Verify user permissions
2. **Data preparation** - Fetch data from services
3. **View rendering** - Load and render templates
4. **Error handling** - Catch and display errors

```php
class SCD_Campaign_List_Controller {
    private $campaign_manager;
    private $capability_manager;
    private $logger;
    
    public function __construct(
        $campaign_manager,
        $capability_manager,
        $logger
    ) {
        $this->campaign_manager = $campaign_manager;
        $this->capability_manager = $capability_manager;
        $this->logger = $logger;
    }
    
    public function handle() {
        // 1. Security check
        if (!$this->capability_manager->can_view_campaigns()) {
            wp_die('Access denied');
        }
        
        // 2. Get data
        $campaigns = $this->campaign_manager->get_all_campaigns();
        
        // 3. Prepare for view
        $data = $this->prepare_view_data($campaigns);
        
        // 4. Render view
        $this->render_view('campaigns-list', $data);
    }
    
    private function prepare_view_data($campaigns) {
        // Transform raw data for template
        return array_map(fn($campaign) => [
            'id' => $campaign->get_id(),
            'name' => $campaign->get_name(),
            'status' => $campaign->get_status(),
            'discount' => $campaign->get_discount_display(),
            'edit_url' => $this->get_edit_url($campaign->get_id()),
        ], $campaigns);
    }
}
```

### 2.3 Component Classes

**File:** `/includes/admin/components/`

Reusable UI components:

1. **class-campaigns-list-table.php**
   - Extends `WP_List_Table`
   - Handles sorting, filtering, pagination
   - Displays campaigns in WordPress table format

2. **class-chart-renderer.php**
   - Renders analytics charts
   - Supports multiple chart types
   - Integrates with analytics data

3. **class-condition-builder.php**
   - Dynamic condition builder UI
   - Product selection rules
   - Used in wizard discount step

4. **class-modal-component.php**
   - Reusable modal dialogs
   - AJAX-driven content loading
   - Customizable buttons and actions

5. **class-badge-helper.php**
   - Helper for rendering badges
   - Status indicators
   - Pro/free badges

### 2.4 Data Passing to Views

**Pattern:** Controller → Service → Data Array → View

**Example Flow:**

```php
// 1. Controller gets data from service
$data = $this->dashboard_service->get_dashboard_data(
    $date_range_start,
    $date_range_end
);

// 2. Data includes:
$data = array(
    'metrics' => array(
        'revenue' => 15000,
        'conversions' => 250,
        'ctr' => 3.5,
        'revenue_change' => 12.5,  // Pre-calculated trend
    ),
    'campaign_stats' => array(
        'total' => 5,
        'active' => 3,
        'inactive' => 2,
    ),
    'top_campaigns' => array(...),
    'campaign_health' => array(...),
);

// 3. View template receives data as local variables
// When loading: require 'dashboard.php' with extract($data);
// Each key becomes a variable: $metrics, $campaign_stats, etc.
```

### 2.5 View Rendering Pattern

**Files:** `/resources/views/admin/pages/`

**Structure:**
```
dashboard.php
    ├── Validates/defaults all variables
    ├── Includes partials (header, widgets)
    └── Outputs HTML with wp-proper escaping
```

**Example View:**
```php
<?php
// resources/views/admin/pages/dashboard/main-dashboard.php

if (!defined('ABSPATH')) exit;

// Default all variables (prevents undefined notice)
$metrics = $metrics ?? array();
$campaign_stats = $campaign_stats ?? array();
$feature_gate = $feature_gate ?? null;

// Extract conditional display state
$total_campaigns = $campaign_stats['total'] ?? 0;
$active_campaigns = $campaign_stats['active'] ?? 0;
?>

<div class="wrap scd-main-dashboard">
    <h1><?php esc_html_e('Dashboard', 'smart-cycle-discounts'); ?></h1>
    
    <!-- Health widget partial -->
    <?php require __DIR__ . '/partials/health-widget.php'; ?>
    
    <!-- Metrics display -->
    <div class="scd-metrics">
        <div class="scd-metric-card">
            <h3><?php esc_html_e('Revenue', 'smart-cycle-discounts'); ?></h3>
            <p><?php echo esc_html(wc_price($metrics['revenue'] ?? 0)); ?></p>
        </div>
    </div>
    
    <!-- Campaign overview -->
    <?php if ($total_campaigns > 0): ?>
        <div class="scd-campaigns">
            <!-- Campaign list -->
        </div>
    <?php else: ?>
        <p><?php esc_html_e('No campaigns yet', 'smart-cycle-discounts'); ?></p>
    <?php endif; ?>
</div>
```

---

## 3. AJAX SYSTEM

### 3.1 AJAX Router Architecture

**File:** `/includes/admin/ajax/class-ajax-router.php`

**Single Unified Router** for all AJAX requests:

```
AJAX Request (from JavaScript)
    ↓ Includes wsscdAction parameter
    ↓
WordPress Hook (wp_ajax_wsscd_ajax)
    ↓
WSSCD_Ajax_Router::route_request()
    ↓ Extract action from request
    ↓ Validate handler class name
    ↓ Load handler class
    ↓
WSSCD_Abstract_Ajax_Handler::execute()
    ↓ Verify nonce and capability
    ↓ Execute handler logic
    ↓
WSSCD_AJAX_Response::success() or error()
```

**Handler Registration:**

Handlers are registered in the router:
```php
$this->handlers = array(
    'save_step' => 'WSSCD_Save_Step_Handler',
    'load_data' => 'WSSCD_Load_Data_Handler',
    'get_summary' => 'WSSCD_Get_Summary_Handler',
    'main_dashboard_data' => 'WSSCD_Main_Dashboard_Data_Handler',
    // 40+ handlers...
);
```

**Security Measures:**
1. Handler class name validation (alphanumeric + underscore only)
2. File name validation (prevent directory traversal)
3. Absolute path validation using `realpath()`
4. File extension verification (.php only)
5. Whitelist of allowed directories

### 3.2 Abstract AJAX Handler Pattern

**File:** `/includes/admin/ajax/abstract-class-ajax-handler.php`

**Core Pattern:**

```php
abstract class WSSCD_Abstract_Ajax_Handler {
    protected $logger;
    
    public function __construct($logger = null) {
        $this->logger = $logger ?? new SCD_Logger('ajax');
    }
    
    /**
     * Main entry point - handles security and errors
     */
    final public function execute($request = array()) {
        try {
            // 1. Security verification
            $verification = $this->verify_request($request);
            if (is_wp_error($verification)) {
                return $this->handle_wp_error($verification);
            }
            
            // 2. Business logic
            $result = $this->handle($request);
            
            // 3. Ensure result is array
            if (!is_array($result)) {
                $result = array();
            }
            
            // 4. Handle WP_Error returns
            if (is_wp_error($result)) {
                return $this->handle_wp_error($result);
            }
            
            // 5. Ensure success flag
            if (!isset($result['success'])) {
                $result['success'] = true;
            }
            
            return $result;
            
        } catch (Exception $e) {
            return $this->handle_exception($e);
        }
    }
    
    /**
     * Subclasses implement this with business logic
     */
    abstract protected function handle($request);
    
    /**
     * Get required AJAX action name
     */
    abstract protected function get_action_name();
    
    /**
     * Get required user capability
     */
    protected function get_required_capability() {
        return 'manage_options';
    }
}
```

**Handler Implementation Example:**

```php
class WSSCD_Get_Summary_Handler extends WSSCD_Abstract_Ajax_Handler {
    private $state_service;
    
    public function __construct($state_service, $logger = null) {
        parent::__construct($logger);
        $this->state_service = $state_service;
    }
    
    protected function get_action_name() {
        return 'get_summary';  // Full action: scd_get_summary
    }
    
    protected function get_required_capability() {
        return 'manage_options';
    }
    
    protected function handle($request) {
        // Business logic here
        $steps = array(
            'basic' => $this->state_service->get_step_data('basic'),
            'products' => $this->state_service->get_step_data('products'),
            // ...
        );
        
        $summary = $this->build_summary($steps);
        
        return $this->success(array(
            'summary' => $summary,
            'progress' => $this->state_service->get_progress(),
        ));
    }
    
    protected function build_summary($steps) {
        // Transform step data into summary format
    }
}
```

### 3.3 AJAX Security (SCD_Ajax_Security)

**File:** `/includes/admin/ajax/class-ajax-security.php`

**Verification Checklist:**
1. **Nonce Verification** - `wp_verify_nonce()`
2. **Capability Check** - `current_user_can()`
3. **Input Validation** - Data type and content validation
4. **Rate Limiting** - Prevent abuse
5. **Error Handling** - Safe error messages (no sensitive data)

```php
protected function verify_request($request) {
    // 1. Check nonce
    if (!wp_verify_nonce($request['nonce'] ?? '', $this->get_nonce_action())) {
        return new WP_Error(
            'invalid_nonce',
            __('Security check failed', 'smart-cycle-discounts')
        );
    }
    
    // 2. Check capability
    if (!current_user_can($this->get_required_capability())) {
        return new WP_Error(
            'insufficient_capability',
            __('You do not have permission', 'smart-cycle-discounts')
        );
    }
    
    // 3. Check rate limit
    if (!$this->check_rate_limit()) {
        return new WP_Error(
            'rate_limit',
            __('Too many requests', 'smart-cycle-discounts')
        );
    }
    
    return true;
}
```

### 3.4 AJAX Response Format

**File:** `/includes/admin/ajax/class-scd-ajax-response.php`

**Standardized Response Structure:**

```php
class WSSCD_AJAX_Response {
    public static function success($data = null, $meta = array()) {
        $response = array(
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        );
        wp_send_json($response);
    }
    
    public static function error($message = '', $code = 'general_error', $data = null) {
        $response = array(
            'success' => false,
            'error' => array(
                'message' => self::get_safe_error_message($message, $code),
                'code' => $code,
            ),
            'data' => $data,
        );
        wp_send_json($response);
    }
}
```

**Response Examples:**

```javascript
// Success response
{
    success: true,
    data: {
        campaign_id: 123,
        message: 'Campaign saved'
    },
    meta: {
        version: '1.0.0'
    }
}

// Error response
{
    success: false,
    error: {
        message: 'Security check failed',
        code: 'invalid_nonce'
    }
}
```

---

## 4. ASSET MANAGEMENT SYSTEM

### 4.1 Asset Manager Architecture

**File:** `/includes/admin/class-admin-asset-manager.php`

**Orchestration Flow:**

```
SCD_Admin_Asset_Manager (Main Orchestrator)
    ├── SCD_Script_Registry - Define all scripts
    ├── SCD_Style_Registry - Define all styles
    ├── SCD_Asset_Loader - Conditionally load based on context
    └── SCD_Asset_Localizer - Handle localization data
```

**Initialization:**

```php
class SCD_Admin_Asset_Manager {
    private SCD_Script_Registry $script_registry;
    private SCD_Style_Registry $style_registry;
    private SCD_Asset_Loader $asset_loader;
    private SCD_Asset_Localizer $asset_localizer;
    
    public function __construct(
        SCD_Logger $logger,
        string $version,
        string $plugin_url
    ) {
        $this->logger = $logger;
        $this->version = $version;
        $this->plugin_url = $plugin_url;
    }
    
    public function init() {
        // 1. Initialize registries
        $this->script_registry = new SCD_Script_Registry($this->version, $this->plugin_url);
        $this->style_registry = new SCD_Style_Registry($this->version, $this->plugin_url);
        
        // 2. Initialize loader
        $this->asset_loader = new SCD_Asset_Loader($this->script_registry, $this->style_registry);
        
        // 3. Initialize localizer
        $this->asset_localizer = new SCD_Asset_Localizer($settings);
        
        // 4. Hook into WordPress
        add_action('admin_enqueue_scripts', array($this->asset_loader, 'load_assets'));
        add_action('admin_enqueue_scripts', array($this->asset_localizer, 'localize_scripts'));
    }
}
```

### 4.2 Script Registry Pattern

**File:** `/includes/admin/assets/class-script-registry.php`

**Structure:**

```php
class SCD_Script_Registry {
    private array $scripts = array();
    
    public function init() {
        $this->register_vendor_scripts();
        $this->register_core_scripts();
        $this->register_admin_scripts();
        $this->register_wizard_scripts();
        $this->register_analytics_scripts();
        $this->register_component_scripts();
    }
    
    private function register_admin_scripts() {
        // AJAX Service - used across admin
        $this->add_script('ajax-service', array(
            'src' => 'resources/assets/js/admin/ajax-service.js',
            'deps' => array('jquery', 'wp-i18n'),
            'pages' => array('scd-campaigns', 'scd-dashboard', 'scd-analytics'),
            'in_footer' => true,
        ));
        
        // Dashboard script
        $this->add_script('main-dashboard', array(
            'src' => 'resources/assets/js/admin/dashboard/main-dashboard.js',
            'deps' => array('jquery', 'scd-ajax-service'),
            'pages' => array('scd-dashboard'),
            'in_footer' => true,
        ));
        
        // Wizard scripts (only on wizard pages)
        $this->add_script('wizard', array(
            'src' => 'resources/assets/js/wizard/wizard.js',
            'deps' => array('jquery', 'scd-ajax-service'),
            'pages' => array('scd-campaigns'),
            'condition' => array('action' => 'wizard'),
            'in_footer' => true,
        ));
    }
    
    private function add_script($handle, $args) {
        $this->scripts[$handle] = wp_parse_args($args, array(
            'src' => '',
            'deps' => array(),
            'version' => $this->version,
            'in_footer' => true,
            'pages' => array(),
            'condition' => array(),
            'localize' => null,
        ));
    }
}
```

**Script Definition Format:**

```php
'wizard' => array(
    'src' => 'resources/assets/js/wizard/wizard.js',
    'deps' => array('jquery', 'scd-ajax-service', 'tom-select'),
    'version' => '1.0.0',
    'in_footer' => true,
    'pages' => array('scd-campaigns'),         // Only load on campaigns page
    'condition' => array(                      // Additional conditions
        'action' => 'wizard',                  // Only when action=wizard
    ),
    'localize' => array(                       // Data to localize
        'object_name' => 'SCD_Wizard',
        'data' => array(...),
    ),
)
```

### 4.3 Asset Loader (Conditional Loading)

**File:** `/includes/admin/assets/class-asset-loader.php`

**Context-Based Loading:**

```php
public function load_assets() {
    $current_page = $this->get_current_page();
    $current_action = $this->get_current_action();
    
    foreach ($this->scripts as $handle => $script_data) {
        // Check if script applies to current page
        if (!$this->is_applicable($script_data, $current_page, $current_action)) {
            continue;
        }
        
        // Register and enqueue script
        wp_register_script(
            'scd-' . $handle,
            $this->plugin_url . $script_data['src'],
            $script_data['deps'],
            $script_data['version'],
            $script_data['in_footer']
        );
        
        wp_enqueue_script('scd-' . $handle);
    }
}

private function is_applicable($script_data, $page, $action) {
    // Check page requirement
    if (!empty($script_data['pages']) && !in_array($page, $script_data['pages'])) {
        return false;
    }
    
    // Check action condition
    if (!empty($script_data['condition'])) {
        if (isset($script_data['condition']['action'])) {
            if ($script_data['condition']['action'] !== $action) {
                return false;
            }
        }
    }
    
    return true;
}
```

### 4.4 Asset Localizer Pattern

**File:** `/includes/admin/assets/class-asset-localizer.php`

**Data Localization (PHP → JavaScript):**

```php
class SCD_Asset_Localizer {
    private array $data = array();
    
    public function init() {
        add_action('init', array($this, 'register_data_providers'), 20);
        add_action('admin_enqueue_scripts', array($this, 'localize_scripts'));
    }
    
    public function register_data_providers() {
        // Register data providers that will be localized
        $this->add_data_provider('field_definitions', function() {
            return SCD_Field_Definitions::get_all();
        });
        
        $this->add_data_provider('product_selection_types', function() {
            return SCD_Product_Selection_Types::get_types();
        });
        
        $this->add_data_provider('nonce', function() {
            return array(
                'wizard_nonce' => wp_create_nonce('scd_wizard'),
                'ajax_nonce' => wp_create_nonce('wsscd_ajax'),
            );
        });
    }
    
    public function localize_scripts() {
        // Build localization data
        $localization = array(
            'nonce' => wp_create_nonce('wsscd_ajax'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_user' => array(
                'id' => get_current_user_id(),
                'can_manage_campaigns' => current_user_can('scd_manage_campaigns'),
            ),
        );
        
        // Add all registered data
        foreach ($this->data_providers as $key => $provider) {
            $localization[$key] = call_user_func($provider);
        }
        
        // Localize for JavaScript global
        wp_localize_script('scd-shared', 'SCD', $localization);
    }
}
```

**JavaScript Usage:**

```javascript
// In JavaScript files, data is accessible globally
console.log(SCD.nonce);                      // AJAX nonce
console.log(SCD.ajax_url);                   // AJAX endpoint
console.log(SCD.FieldDefinitions);           // Field definitions
console.log(SCD.ProductSelectionTypes);      // Enum values
```

### 4.5 Naming Conventions for Assets

**Style Handles:**
```php
'scd-admin' => ...              // Main admin styles
'scd-dashboard' => ...          // Dashboard specific
'scd-wizard' => ...             // Wizard specific
'scd-modals' => ...             // Modal styles
```

**Script Handles:**
```php
'scd-ajax-service' => ...       // AJAX service
'scd-wizard' => ...             // Wizard orchestrator
'scd-dashboard' => ...          // Dashboard
'scd-notification-service' => ...  // Notifications
```

**Localization Objects:**
```javascript
SCD.Shared.NotificationService  // Global notification system
SCD.Shared.ValidationError      // Field validation
SCD.FieldDefinitions            // Form field metadata
SCD.ProductSelectionTypes       // Enum values
SCD.Wizard                       // Wizard state and methods
```

---

## 5. DESIGN PATTERNS & CONVENTIONS

### 5.1 Service Pattern

**Purpose:** Encapsulate business logic separate from controllers/views

```php
class SCD_Campaign_Display_Service {
    private $campaign_repository;
    private $logger;
    
    public function __construct($campaign_repository, $logger) {
        $this->campaign_repository = $campaign_repository;
        $this->logger = $logger;
    }
    
    /**
     * Get campaigns formatted for display
     */
    public function get_campaigns_for_display($filters = array()) {
        $campaigns = $this->campaign_repository->get_campaigns($filters);
        
        return array_map(function($campaign) {
            return array(
                'id' => $campaign->get_id(),
                'name' => $campaign->get_name(),
                'status_label' => $this->get_status_label($campaign->get_status()),
                'discount_display' => $campaign->get_discount_display(),
                'edit_url' => $this->get_edit_url($campaign->get_id()),
            );
        }, $campaigns);
    }
}
```

### 5.2 Repository Pattern

**Purpose:** Abstract data access, allow storage-agnostic queries

```php
class SCD_Campaign_Repository {
    private $db_manager;
    private $cache_manager;
    
    /**
     * Get campaign by ID with caching
     */
    public function get_by_id($campaign_id) {
        $cache_key = 'campaign_' . $campaign_id;
        
        // Try cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from database
        $campaign = $this->db_manager->query(
            "SELECT * FROM scd_campaigns WHERE id = %d",
            $campaign_id
        );
        
        // Cache for 1 hour
        $this->cache_manager->set($cache_key, $campaign, 3600);
        
        return $campaign;
    }
}
```

### 5.3 Factory Pattern

**Purpose:** Create complex objects through a factory function

```php
$container->factory('discount_strategy', function($container) {
    $discount_type = $container->get('discount_type');
    
    return match($discount_type) {
        'percentage' => new SCD_Percentage_Strategy(),
        'fixed' => new SCD_Fixed_Strategy(),
        'bogo' => new SCD_Bogo_Strategy(),
        'tiered' => new SCD_Tiered_Strategy(),
        'spend_threshold' => new SCD_Spend_Threshold_Strategy(),
        default => new SCD_Fixed_Strategy(),
    };
});
```

### 5.4 Observer Pattern (WordPress Hooks)

**Purpose:** Decouple components via event system

```php
// In Campaign Manager when campaign is saved:
do_action('scd_campaign_saved', $campaign_id, $campaign_data);

// Other components listen and react:
add_action('scd_campaign_saved', array($cache_manager, 'clear_campaign_cache'));
add_action('scd_campaign_saved', array($analytics, 'invalidate_cache'));
add_action('scd_campaign_saved', array($health_service, 'recalculate_health'));
```

### 5.5 Traits for Code Reuse

**Admin Notice Trait:**
```php
// File: /includes/utilities/traits/trait-admin-notice.php
trait SCD_Admin_Notice_Trait {
    protected function add_notice($message, $type = 'success') {
        // Store in transient for display
    }
    
    protected function display_persistent_notices() {
        // Display all stored notices
    }
}

// Usage in Admin class:
class SCD_Admin {
    use SCD_Admin_Notice_Trait;
    
    public function display_admin_notices() {
        $this->display_persistent_notices();
    }
}
```

---

## 6. KEY CONVENTIONS FOR CAMPAIGN OVERVIEW PANEL

### 6.1 Page/Component Pattern

**To create a new admin panel widget:**

1. **Create a Service** - Handle data fetching and processing
   - File: `/includes/services/class-campaign-overview-service.php`
   - Inject repositories and utilities
   - Return structured data array

2. **Create a Page/Component** - Handle rendering
   - File: `/includes/admin/pages/dashboard/class-campaign-overview-widget.php`
   - Inject service
   - Call service method to get data
   - Load template with data

3. **Register in Service Container**
   - Add to `/includes/bootstrap/class-service-definitions.php`
   - Define dependencies
   - Use factory function if complex initialization

4. **Create View Template**
   - File: `/resources/views/admin/pages/dashboard/partials/campaign-overview.php`
   - Receive data as local variables
   - Use proper escaping (esc_html, esc_attr, etc.)

5. **Enqueue Assets** (if needed)
   - Add scripts to `/includes/admin/assets/class-script-registry.php`
   - Add styles to `/includes/admin/assets/class-style-registry.php`
   - Use page conditions to load only when needed

### 6.2 AJAX Handler for Panel Updates

**To handle panel updates via AJAX:**

1. **Create AJAX Handler**
   ```php
   class SCD_Campaign_Overview_Handler extends WSSCD_Abstract_Ajax_Handler {
       private $campaign_overview_service;
       
       protected function get_action_name() {
           return 'campaign_overview';
       }
       
       protected function handle($request) {
           $overview_data = $this->campaign_overview_service
               ->get_overview_data($request['filters'] ?? array());
           
           return $this->success($overview_data);
       }
   }
   ```

2. **Register Handler**
   - Add to router in `/includes/admin/ajax/class-ajax-router.php`
   - Map action to class: `'campaign_overview' => 'SCD_Campaign_Overview_Handler'`

3. **JavaScript Usage**
   ```javascript
   SCD.AjaxService.post('campaign_overview', {
       filters: { status: 'active' },
       nonce: SCD.nonce
   }).done(function(response) {
       // Update panel with response.data
   });
   ```

### 6.3 Data Caching Pattern

**Cache management:**

```php
class SCD_Campaign_Overview_Service {
    private $cache_manager;
    private $repository;
    
    const CACHE_KEY = 'campaign_overview_data';
    const CACHE_TTL = 300;  // 5 minutes
    
    public function get_overview_data($filters = array()) {
        $cache_key = $this->get_cache_key($filters);
        
        // Check cache
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch data
        $data = array(
            'active_count' => $this->repository->count_active(),
            'revenue' => $this->repository->sum_revenue(),
            'top_campaigns' => $this->repository->get_top_campaigns(),
        );
        
        // Cache with TTL
        $this->cache_manager->set(
            $cache_key,
            $data,
            self::CACHE_TTL
        );
        
        return $data;
    }
    
    private function get_cache_key($filters) {
        return self::CACHE_KEY . '_' . md5(json_encode($filters));
    }
}

// Invalidate cache when campaigns change:
// Hook: scd_campaign_saved, scd_campaign_deleted, etc.
add_action('scd_campaign_saved', function() {
    $cache_manager = $container->get('cache_manager');
    $cache_manager->delete_pattern('campaign_overview_data_*');
});
```

---

## 7. SUMMARY OF KEY PATTERNS

| Pattern | Location | Purpose |
|---------|----------|---------|
| **Dependency Injection Container** | `/includes/bootstrap/class-container.php` | Auto-resolve service dependencies via reflection |
| **Service Pattern** | `/includes/services/` | Encapsulate business logic |
| **Repository Pattern** | `/includes/database/repositories/` | Abstract data access |
| **Controller Pattern** | `/includes/core/campaigns/` | Handle page logic and routing |
| **Component Pattern** | `/includes/admin/components/` | Reusable UI components |
| **Abstract AJAX Handler** | `/includes/admin/ajax/abstract-class-ajax-handler.php` | Base for all AJAX handlers |
| **Registry Pattern** | `/includes/admin/assets/class-script-registry.php` | Define all assets in one place |
| **Conditional Asset Loading** | `/includes/admin/assets/class-asset-loader.php` | Load only needed assets per page |
| **Asset Localization** | `/includes/admin/assets/class-asset-localizer.php` | Pass PHP data to JavaScript |
| **Observer Pattern** | WordPress Hooks | Decouple components via events |
| **Trait Pattern** | `/includes/utilities/traits/` | Code reuse across classes |
| **Factory Pattern** | Service container factories | Complex object creation |

---

## 8. BEST PRACTICES FOR NEW COMPONENTS

When building new features like the campaign overview panel:

1. **Use Service Container** - Get dependencies injected, don't instantiate directly
2. **Separate Concerns** - Service for data, controller for logic, view for display
3. **Cache Appropriately** - Use cache manager for expensive operations
4. **Log Important Events** - Use logger service for debugging
5. **Follow Naming** - `SCD_` prefix, snake_case for methods/properties
6. **Implement Security** - Check capabilities, verify nonces, sanitize input
7. **Use AJAX Handlers** - Extend `WSSCD_Abstract_Ajax_Handler` for AJAX endpoints
8. **Conditional Asset Loading** - Only load scripts/styles when needed
9. **Localize Data** - Use asset localizer for PHP → JavaScript data
10. **Add to Service Definitions** - Register in bootstrap for DI resolution

