=== Smart Cycle Discounts ===
Contributors: smartcyclediscounts
Donate link: https://smartcyclediscounts.com/donate
Tags: woocommerce, discounts, campaigns, bulk discounts, product discounts
Requires at least: 6.4
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Powerful WooCommerce discount campaigns with advanced scheduling, bulk management, and performance analytics.

== Description ==

Smart Cycle Discounts revolutionizes WooCommerce discount management with intelligent product rotation, multi-campaign organization, and advanced scheduling. Built with modern WordPress & WooCommerce standards including HPOS compatibility.

= Key Features =

* **5-Step Campaign Wizard** - Create discount campaigns with guided workflow (Basic Info → Products → Discounts → Schedule → Review)
* **Flexible Product Selection** - Choose all products, specific products with search, or random product rotation
* **Smart Product Rotation** - Automatically rotate discounts across random products daily/weekly
* **Multiple Discount Types** - Percentage discounts, fixed amount reductions, or set custom sale prices
* **Advanced Scheduling** - Set start/end dates, specific times, and timezone-aware scheduling
* **Priority-Based System** - Control which campaigns take precedence with 1-10 priority levels
* **Bulk Campaign Management** - Enable, disable, duplicate, or delete multiple campaigns at once
* **Real-Time Product Search** - AJAX-powered search by product name, SKU, or ID with instant results
* **HPOS Compatible** - Full support for WooCommerce High-Performance Order Storage
* **Performance Optimized** - Efficient database queries with caching for large product catalogs

= Use Cases =

* **Seasonal Sales** - Schedule Black Friday, Christmas, or Summer clearance campaigns in advance
* **Flash Sales** - Time-limited discounts with specific start/end dates and times
* **Random Product Promotions** - Rotate "Deal of the Day" across different products automatically
* **Bulk Discounts** - Apply discounts to hundreds of products simultaneously with product search
* **Priority Management** - Run multiple campaigns with controlled priority to avoid conflicts
* **Scheduled Campaigns** - Set up future campaigns that activate and deactivate automatically

= Performance & Architecture =

* **Efficient Database Layer** - Custom query optimization with prepared statements
* **Service Container & DI** - Modern dependency injection architecture
* **AJAX-Powered UI** - Fast, responsive admin interface without page reloads
* **Asset Management System** - Intelligent script/style loading only where needed
* **Modular Wizard System** - Step-based architecture with state management
* **HPOS Ready** - Compatible with WooCommerce High-Performance Order Storage
* **Scales Efficiently** - Handles thousands of products with optimized queries

= Developer Friendly =

* **WordPress Standards** - Follows WordPress and WooCommerce coding standards
* **Extensive Hooks** - Actions and filters throughout for customization
* **Well-Documented Code** - Comprehensive PHPDoc blocks and inline comments
* **Modular Architecture** - MVC pattern with separated concerns
* **Security First** - Nonce verification, capability checks, sanitization, and escaping throughout

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or later
* WooCommerce 8.0 or later
* PHP 8.0 or later (PHP 8.3+ recommended)
* MySQL 5.6 or later / MariaDB 10.1 or later

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins > Add New
3. Search for "Smart Cycle Discounts"
4. Click "Install Now" and then "Activate"
5. You'll see a new "Campaigns" menu item in your WordPress admin sidebar
6. Click "Campaigns" > "Create Campaign" to launch the wizard and create your first discount campaign

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress dashboard
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the downloaded zip file and click "Install Now"
5. Click "Activate Plugin"
6. Navigate to Campaigns > Create Campaign to get started

== Frequently Asked Questions ==

= Is Smart Cycle Discounts compatible with my theme? =

Yes! Smart Cycle Discounts is a backend plugin that works with any properly coded WordPress theme. It integrates with WooCommerce's native sale price system.

= Can I schedule campaigns in advance? =

Absolutely! Use the Schedule step in the wizard to set specific start and end dates/times. Campaigns will activate and deactivate automatically based on your schedule.

= How many products can I discount at once? =

There's no hard limit. The plugin is optimized to handle thousands of products efficiently with AJAX-powered search and bulk selection tools.

= Will this slow down my site? =

No. Smart Cycle Discounts uses optimized database queries, efficient caching, and loads assets only on admin pages where needed. Frontend performance is not impacted.

= Can I have multiple campaigns running at the same time? =

Yes! You can run multiple campaigns simultaneously. Use the Priority field (1-10) to control which campaign takes precedence when multiple campaigns affect the same product.

= What's the difference between "All Products", "Specific Products", and "Random Products"? =

* **All Products** - Applies discount to your entire product catalog
* **Specific Products** - You manually select which products to discount using the product search
* **Random Products** - Plugin automatically selects X random products and can rotate them daily/weekly

= How does the random product rotation work? =

Set the number of products and rotation frequency (daily/weekly). The plugin will randomly select that many products from your catalog and automatically rotate to new random products based on your schedule.

= Does it work with WooCommerce HPOS (High-Performance Order Storage)? =

Yes! Smart Cycle Discounts is fully compatible with WooCommerce HPOS.

= Can I duplicate an existing campaign? =

Yes. On the Campaigns list page, use the "Duplicate" action to create a copy of any campaign, which you can then modify as needed.

== Screenshots ==

1. Campaign wizard - Step 1: Basic Information (name, description, priority)
2. Campaign wizard - Step 2: Product Selection (all products, specific products, or random rotation)
3. Campaign wizard - Step 3: Discount Configuration (percentage, fixed amount, or custom sale price)
4. Campaign wizard - Step 4: Schedule Settings (start/end dates, times, timezone)
5. Campaign wizard - Step 5: Review & Confirm (preview all settings before creating)
6. Campaigns list - Manage all campaigns with bulk actions (enable, disable, duplicate, delete)
7. Product search - Real-time AJAX search by product name, SKU, or ID
8. Campaign priority - Set priority levels 1-10 to control which discounts take precedence

== Changelog ==

= 1.0.0 =
* Initial release
* 5-step campaign wizard (Basic Info, Products, Discounts, Schedule, Review)
* Three product selection modes: All Products, Specific Products, Random Products
* Smart product rotation system (daily/weekly)
* Multiple discount types: Percentage, Fixed Amount, Custom Sale Price
* Advanced scheduling with date/time/timezone support
* Priority system (1-10) for campaign precedence
* AJAX-powered product search
* Bulk campaign management (enable, disable, duplicate, delete)
* Service container architecture with dependency injection
* Custom database abstraction layer
* Modular wizard system with state management
* Asset management system for optimized loading
* HPOS (High-Performance Order Storage) compatibility
* WordPress 6.4+ compatibility
* WooCommerce 8.0+ compatibility
* PHP 5.6+ compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Smart Cycle Discounts. Create intelligent WooCommerce discount campaigns with product rotation, advanced scheduling, and priority management.

== Additional Information ==

= Support & Documentation =

* Documentation: https://smartcyclediscounts.com/docs/
* Support: https://smartcyclediscounts.com/support/
* WordPress.org Support Forum: https://wordpress.org/support/plugin/smart-cycle-discounts

= Contributing =

Want to contribute? Visit our GitHub repository: https://github.com/smartcyclediscounts/smart-cycle-discounts

= Privacy Policy =

Smart Cycle Discounts does not collect, transmit, or store any customer data externally. All campaign data is stored locally in your WordPress database. The plugin does not make any external API calls or send data to third-party services.