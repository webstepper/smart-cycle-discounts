=== Smart Cycle Discounts – WooCommerce Discount Campaigns, Dynamic Pricing & Scheduled Sales ===
Contributors: webstepper
Tags: woocommerce, discount, dynamic pricing, bulk discount, sale
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce dynamic pricing and discounts. Schedule sales, bulk discounts, BOGO, tiered pricing with automatic activation.

== Description ==

Smart Cycle Discounts is a WooCommerce dynamic pricing and discount plugin for creating scheduled sale campaigns. Set up percentage discounts, fixed amount discounts, bulk discounts, tiered pricing, and BOGO offers with a 5-step campaign wizard.

**Key capabilities:**

* Schedule WooCommerce sales with specific start and end dates
* Apply discounts to all products, specific products, or random product selections
* Set campaign priorities when running multiple promotions
* Automatic activation and deactivation based on your schedule

= Free Version Features =

**Campaign Wizard**

Create discount campaigns in minutes with a guided 5-step workflow:

1. **Basic Info** - Name your campaign and set priority
2. **Products** - Select which products to discount
3. **Discounts** - Choose discount type and amount
4. **Schedule** - Set start and end dates
5. **Review** - Verify settings and launch

**Product Selection**

* **All Products** - Apply discounts store-wide
* **Specific Products** - Search and select individual products by name, SKU, or ID
* **Random Products** - Automatically rotate discounts across random products

**Discount Types**

* **Percentage Off** - Reduce prices by a percentage (e.g., 25% off)
* **Fixed Amount Off** - Reduce prices by a fixed amount (e.g., $10 off)

**Scheduling**

* **Date and Time Control** - Set precise start and end times with timezone support
* **Automatic Activation** - Campaigns start and stop on schedule without manual intervention
* **Draft Mode** - Prepare campaigns in advance and activate when ready

**Campaign Management**

* **Unlimited Campaigns** - No restrictions on how many campaigns you can create
* **Priority System** - Control which discount applies when campaigns overlap (1-5 levels)
* **Bulk Actions** - Activate, deactivate, duplicate, or delete multiple campaigns at once
* **Health Monitoring** - Real-time validation alerts you to potential issues

**Technical**

* **WooCommerce HPOS Compatible** - Works with High-Performance Order Storage
* **Performance Optimized** - Efficient queries and caching for large catalogs
* **Secure** - Follows WordPress security best practices

= Pro Version =

Upgrade to Smart Cycle Discounts Pro for advanced discount types, analytics, and automation.

**Additional Discount Types**

* **Tiered Pricing** - Quantity-based discounts (e.g., Buy 5+ get 10% off, Buy 10+ get 20% off)
* **Buy One Get One** - Flexible BOGO offers with configurable quantities and discount percentages
* **Spend Threshold** - Cart total discounts (e.g., Spend $100 get 15% off entire order)

**Advanced Controls**

* **Usage Limits** - Set per-customer limits, total redemption caps, and lifetime thresholds
* **Application Rules** - Apply discounts to all items, cheapest item only, most expensive, or first X items
* **Smart Product Selection** - Auto-select Best Sellers, Featured Products, Low Stock, or New Arrivals

**Analytics**

* **Performance Dashboard** - Track revenue, conversions, and campaign effectiveness
* **Date Range Filtering** - Analyze any time period
* **Export Reports** - Download CSV or JSON data for external analysis

**Notifications**

* **Campaign Alerts** - Get notified when campaigns start, end, or need attention
* **Performance Warnings** - Automatic alerts for underperforming campaigns
* **Scheduled Reports** - Daily or weekly email summaries

**Developer Features**

* **REST API** - Programmatic campaign management for custom integrations
* **Priority Support** - Dedicated support queue for faster response

[View Pro Features](https://webstepper.io/wordpress/plugins/smart-cycle-discounts/)

= Common Use Cases =

* **Seasonal Promotions** - Schedule Black Friday, Christmas, or Summer sales weeks in advance
* **Flash Sales** - Run time-limited discounts that start and end automatically
* **Daily Deals** - Rotate discounts across different products each day
* **Clearance Events** - Apply bulk discounts to hundreds of products at once
* **Overlapping Campaigns** - Run multiple promotions simultaneously with priority control

= For Developers =

* **Hooks and Filters** - Extend functionality with actions and filters throughout
* **Documented Code** - PHPDoc blocks and inline comments
* **REST API** - Programmatic access to campaigns (Pro)

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or later
* WooCommerce 8.0 or later
* PHP 7.4 or later (PHP 8.3+ recommended)
* MySQL 5.6 or later / MariaDB 10.1 or later

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to **Plugins > Add New**
3. Search for "Smart Cycle Discounts"
4. Click **Install Now** and then **Activate**
5. You'll see a new **Campaigns** menu item in your WordPress admin sidebar
6. Click **Campaigns > Create Campaign** to launch the wizard and create your first discount campaign

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress dashboard
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Choose the downloaded zip file and click **Install Now**
5. Click **Activate Plugin**
6. Navigate to **Campaigns > Create Campaign** to get started

== Getting Started ==

= Quick Start =

1. Go to **Campaigns > Create Campaign** in your WordPress admin
2. Follow the 5-step wizard: Basic Info → Products → Discounts → Schedule → Review
3. Click **Create Campaign** to launch your discount

= Example: 25% Off Flash Sale =

1. **Basic Info**: Name it "Flash Sale" with priority 5
2. **Products**: Select "All Products" or pick specific items
3. **Discounts**: Choose "Percentage" and enter 25
4. **Schedule**: Set start date/time and end date/time
5. **Review**: Verify settings and click Create

Your discounts will activate and deactivate automatically on schedule.

= Managing Campaigns =

Access **Campaigns** in your admin menu to view, edit, duplicate, or delete campaigns. Use bulk actions to manage multiple campaigns at once.

= Need Help? =

* [Documentation](https://webstepper.io/docs-category/smart-cycle-discounts/)
* [Support Forum](https://wordpress.org/support/plugin/smart-cycle-discounts/)

== Frequently Asked Questions ==

= Is Smart Cycle Discounts compatible with my theme? =

Yes. Smart Cycle Discounts is a backend plugin that works with any properly coded WordPress theme. It integrates with WooCommerce's native sale price system, so your theme's existing sale price styling applies automatically.

= Can I schedule campaigns in advance? =

Yes. Use the Schedule step in the wizard to set specific start and end dates/times. Campaigns activate and deactivate automatically based on your schedule. You can create campaigns weeks or months in advance.

= How many campaigns can I create? =

There are no campaign limits - you can create and run **unlimited campaigns** in both the free and Pro versions.

= What's the difference between the discount types? =

**Free Version:**
- **Percentage**: Reduce price by a percentage (e.g., 25% off = $100 product becomes $75)
- **Fixed Amount**: Reduce price by fixed amount (e.g., $10 off = $100 product becomes $90)

**Pro Version:**
- **Tiered**: Quantity-based discounts (e.g., Buy 5 get 10% off, Buy 10 get 20% off)
- **BOGO**: Buy One Get One offers with flexible configurations
- **Spend Threshold**: Discount based on cart total (e.g., Spend $100 get 15% off)

= Will this slow down my site? =

No. Smart Cycle Discounts uses optimized database queries, efficient caching, and loads assets only on admin pages where needed. Frontend performance is not impacted. The plugin is designed to handle thousands of products efficiently.

= Can I run multiple campaigns at the same time? =

Yes. You can run unlimited campaigns simultaneously. Use the Priority field (1-5, where 5 is highest priority) to control which campaign takes precedence when multiple campaigns affect the same product.

= What's the difference between "All Products", "Specific Products", and "Random Products"? =

* **All Products** - Applies discount to your entire product catalog
* **Specific Products** - You manually select which products to discount using the AJAX-powered product search
* **Random Products** - Plugin automatically selects X random products from your catalog when the campaign runs

= Does it work with WooCommerce HPOS (High-Performance Order Storage)? =

Yes. Smart Cycle Discounts is fully compatible with WooCommerce HPOS and has been tested with custom order tables.

= Can I duplicate an existing campaign? =

Yes. On the Campaigns list page, hover over any campaign and click the "Duplicate" action. This creates a copy of the campaign with all settings intact, which you can then modify as needed.

= How does the priority system work? =

When multiple campaigns affect the same product, the campaign with the highest priority (5 = highest, 1 = lowest) takes precedence. This lets you run multiple campaigns without conflicts - for example, a high-priority flash sale can override a lower-priority seasonal sale.

= Can I export campaign data? =

Campaign analytics and export features are available in the **Pro version**. Pro users can export campaign performance data in CSV or JSON format, and schedule automated reports.

= What happens when a campaign ends? =

When a campaign reaches its end date/time, it automatically deactivates and removes the sale prices from affected products. The campaign remains in your list as "Ended" and can be duplicated or reactivated with new dates.

= Is there a way to test campaigns before activating them? =

Yes. Use the **Draft** status to create and configure campaigns without activating them. You can review the campaign settings, check the health score, and preview which products will be affected. When ready, change the status to Active or Scheduled.

= Does it work with variable products? =

Yes. Smart Cycle Discounts fully supports WooCommerce variable products. When you apply a discount to a variable product, all variations receive the discount automatically. The sale price displays correctly on both the main product page and individual variation selections.

= Can I exclude products that are already on sale? =

Yes. The plugin respects existing sale prices. If a product already has a manual sale price set in WooCommerce, you can configure your campaign to skip those products or override them based on your preference.

= Does it work alongside WooCommerce coupons? =

Yes. Campaign discounts and WooCommerce coupons work independently. Campaign discounts apply to product prices (shown as sale prices), while coupons apply at checkout. Customers can use both together unless you configure specific restrictions.

= What happens if two campaigns affect the same product? =

The campaign with the higher priority wins. Each campaign has a priority setting from 1 (lowest) to 5 (highest). When multiple active campaigns include the same product, only the highest-priority discount applies. This prevents discount stacking and gives you full control over which promotions take precedence.

== Screenshots ==

1. Campaigns list - Manage all campaigns with status, schedule, health scores, and quick actions
2. Campaign overview panel - Quick view of campaign settings and real-time performance metrics
3. Campaign wizard Step 1 - Basic information with campaign name, description, and contextual help
4. Campaign wizard Step 2 - Product selection with category tree and filtering options
5. Campaign wizard Step 3 - Discount configuration with live badge preview and positioning
6. Campaign wizard Step 4 - Schedule configuration with date/time picker and duration calculator
7. Campaign wizard Step 5 - Review with smart recommendations and campaign summary sidebar
8. Dashboard health widget - Monitor all campaigns with 6 health indicators at a glance
9. Campaign planner - Smart suggestions for upcoming events and seasonal promotions
10. Analytics dashboard - Track revenue, conversions, click-through rates, and performance trends
11. Main dashboard - Performance summary with campaign cards sorted by urgency

== Changelog ==

= 1.0.2 =
* Updated plugin banners for WordPress.org
* Simplified plugin name display in WP Admin

= 1.0.1 =
* Updated plugin name for better discoverability on WordPress.org
* Corrected plugin website URL

= 1.0.0 =
* Initial release
* 5-step campaign wizard for creating discount campaigns
* Product selection: All Products, Specific Products, Random Products
* Discount types: Percentage and Fixed Amount (Free), Tiered, BOGO, Spend Threshold (Pro)
* Scheduling with date, time, and timezone support
* Priority system for overlapping campaigns
* Campaign health monitoring
* Bulk campaign management
* Analytics dashboard (Pro)
* Email notifications
* WooCommerce HPOS compatibility
* WordPress 6.4+ and WooCommerce 8.0+ support

== Upgrade Notice ==

= 1.0.2 =
Updated plugin banners and cleaner plugin name in WP Admin.

= 1.0.1 =
Minor update with improved plugin naming for better WordPress.org search visibility.

= 1.0.0 =
Initial release of Smart Cycle Discounts. Create intelligent WooCommerce discount campaigns with advanced scheduling, priority management, and flexible product selection.

== External services ==

This plugin connects to external services for licensing, updates, and optional email delivery. Below is a complete list of all external services used, including the specific domains contacted.

= Freemius (License Management) =

This plugin uses Freemius for license management, plugin updates, and optional usage analytics.

* **Service provider**: Freemius, Inc.
* **Domains contacted**: api.freemius.com, wp.freemius.com, checkout.freemius.com, users.freemius.com
* **What it does**: Handles Pro license activation/deactivation, delivers plugin updates, processes payments via secure checkout, and collects anonymous usage data (if opted-in)
* **When data is sent**: On plugin activation, license validation, update checks, Pro checkout, and if you opt-in to usage tracking
* **What data is sent**: Site URL, plugin version, license key (if Pro), WordPress version, PHP version, and anonymous usage statistics (only if opted-in)
* **Terms of Service**: https://freemius.com/terms/
* **Privacy Policy**: https://freemius.com/privacy/

= Plugin Feedback API (Optional) =

When deactivating the plugin, you may optionally provide feedback to help us improve.

* **Service provider**: Webstepper
* **Domain contacted**: api.smartcyclediscounts.com
* **What it does**: Collects optional deactivation feedback to improve the plugin
* **When data is sent**: Only when you choose to submit the optional deactivation feedback form during plugin deactivation
* **What data is sent**: Feedback reason, site URL, WordPress version, WooCommerce version, PHP version, plugin version, and basic usage statistics (number of campaigns created)
* **Terms of Service**: https://webstepper.io/terms-of-service/
* **Privacy Policy**: https://webstepper.io/privacy-policy/

= SendGrid Email API (Optional) =

This plugin can optionally connect to the SendGrid API to send email notifications about your discount campaigns.

* **Service provider**: Twilio SendGrid
* **Domain contacted**: api.sendgrid.com
* **What it does**: Delivers email notifications (campaign started, campaign ended, performance alerts)
* **When data is sent**: Only when you configure SendGrid as your email provider in Settings > Email Notifications AND an email notification is triggered by campaign events
* **What data is sent**: Recipient email address, email subject, and email body content (campaign status information only - no customer personal data is transmitted)
* **Terms of Service**: https://www.twilio.com/legal/tos
* **Privacy Policy**: https://www.twilio.com/legal/privacy

= Amazon SES Email API (Optional) =

This plugin can optionally connect to Amazon Simple Email Service (SES) to send email notifications about your discount campaigns.

* **Service provider**: Amazon Web Services
* **Domain contacted**: email.[region].amazonaws.com (where [region] is your configured AWS region, e.g., email.us-east-1.amazonaws.com)
* **What it does**: Delivers email notifications (campaign started, campaign ended, performance alerts)
* **When data is sent**: Only when you configure Amazon SES as your email provider in Settings > Email Notifications AND an email notification is triggered by campaign events
* **What data is sent**: Recipient email address, email subject, and email body content (campaign status information only - no customer personal data is transmitted)
* **Terms of Service**: https://aws.amazon.com/service-terms/
* **Privacy Policy**: https://aws.amazon.com/privacy/

**Important**: All external services except Freemius (required for licensing) are completely optional. The plugin works fully without configuring SendGrid or Amazon SES. The Plugin Feedback API is only contacted if you explicitly choose to submit feedback during deactivation. No email data is transmitted unless you explicitly enable and configure email integrations in Settings > Email Notifications.

== Additional Information ==

= Support =

* [Documentation](https://webstepper.io/docs-category/smart-cycle-discounts/)
* [FAQ](https://webstepper.io/wordpress/plugins/smart-cycle-discounts/#faq)
* [Support Forum](https://wordpress.org/support/plugin/smart-cycle-discounts/)
* [Contact](https://webstepper.io/contact-us/)

= Privacy =

Campaign data is stored locally in your WordPress database. The plugin does not collect or transmit customer personal data. See the "External services" section for details about third-party integrations.

= Links =

* [Plugin Website](https://webstepper.io/wordpress/plugins/smart-cycle-discounts/)
* [Changelog](https://webstepper.io/plugins/smart-cycle-discounts/changelog/)
* [Terms of Service](https://webstepper.io/terms-of-service/)
* [Privacy Policy](https://webstepper.io/privacy-policy/)