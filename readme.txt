=== Smart Cycle Discounts ===
Contributors: webstepper
Tags: woocommerce, discount rules, bulk discounts, dynamic pricing, percentage discount, bogo, tiered pricing, campaign, sale, flash sale, scheduling, pricing
Requires at least: 6.4
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce Discount Rules, Bulk Pricing, Campaign Scheduling & Dynamic Pricing - Percentage Discounts, BOGO, Tiered Pricing, Flash Sales

== Description ==

Smart Cycle Discounts is the most powerful WooCommerce discount rules and dynamic pricing plugin with advanced campaign scheduling, bulk discount management, and percentage discounts. Create tiered pricing, BOGO deals, quantity discounts, and flash sales with an intuitive 5-step wizard. Perfect for store owners who need to schedule seasonal sales, manage multiple discount campaigns, and automate product pricing strategies.

Unlike basic discount plugins, Smart Cycle Discounts gives you campaign-level control with built-in scheduling, priority management, and health monitoring - making it the complete WooCommerce discount solution for serious store owners.

= Free Version Features =

**Campaign Management:**
* **5-Step Campaign Wizard** - Guided workflow (Basic Info → Products → Discounts → Schedule → Review)
* **Up to 3 Active Campaigns** - Run up to 3 active campaigns simultaneously
* **Priority System** - Control which campaigns take precedence with 1-5 priority levels
* **Bulk Actions** - Enable, disable, duplicate, or delete multiple campaigns at once
* **Campaign Health Monitoring** - Real-time validation and health scoring

**Product Selection:**
* **All Products Mode** - Apply discounts to your entire catalog
* **Specific Products Mode** - Hand-pick products with AJAX-powered search by name, SKU, or ID
* **Random Products Mode** - Automatically select X random products from your catalog

**Discount Types (Free):**
* **Percentage Discounts** - Set percentage off (e.g., 25% off)
* **Fixed Amount Discounts** - Set fixed dollar/currency amount off (e.g., $10 off)

**Scheduling & Automation:**
* **Advanced Scheduling** - Set start/end dates, specific times, and timezone-aware scheduling
* **Automatic Activation** - Campaigns activate and deactivate automatically based on schedule
* **Draft & Scheduled Status** - Prepare campaigns in advance

**Technical Features:**
* **HPOS Compatible** - Full support for WooCommerce High-Performance Order Storage
* **Performance Optimized** - Efficient database queries with caching for large catalogs
* **Security First** - Nonce verification, capability checks, sanitization throughout
* **WordPress Standards** - Follows WordPress and WooCommerce coding standards

= Premium Features (Pro Version) =

Upgrade to **Smart Cycle Discounts Pro** for advanced discount types, unlimited campaigns, and powerful analytics:

**Advanced Discount Types:**
* **Tiered Volume Pricing** - Quantity-based discount tiers (e.g., Buy 5 get 10% off, Buy 10 get 20% off)
* **Buy One Get One (BOGO)** - Flexible BOGO configurations with percentage discounts
* **Spend Threshold Discounts** - Cart total-based discounts (e.g., Spend $100 get 15% off)

**Unlimited Campaigns:**
* **No Campaign Limits** - Run unlimited active campaigns simultaneously
* **Recurring Campaigns** - Set campaigns to repeat daily, weekly, or monthly
* **Advanced Product Filters** - Smart Selection mode with Best Sellers, Featured Products, Low Stock, and New Arrivals

**Analytics & Reporting:**
* **Analytics Dashboard** - Detailed performance metrics and insights
* **Traffic Breakdown** - Source and device analytics
* **Geographic Data** - Location-based campaign performance
* **Export Features** - CSV and JSON export with scheduled reports

**Advanced Notifications:**
* **Proactive Alerts** - Campaign ending warnings (24 hours before)
* **Performance Monitoring** - Smart alerts for underperforming campaigns
* **Daily & Weekly Reports** - Automated email reports with insights
* **Low Stock Alerts** - Get notified when discounted products run low
* **Milestone Notifications** - Celebrate campaign achievements

**Professional Support:**
* **Priority Support** - Get help faster with dedicated support queue
* **API Access** - Programmatic campaign management via REST API

[**Upgrade to Pro →**](https://webstepper.io/wordpress-plugins/smart-cycle-discounts/pricing)

= Use Cases =

* **Seasonal Sales** - Schedule Black Friday, Christmas, or Summer clearance campaigns in advance
* **Flash Sales** - Time-limited discounts with specific start/end dates and times
* **Random Promotions** - Rotate discounts across different products automatically
* **Bulk Discounts** - Apply discounts to hundreds of products simultaneously
* **Priority Management** - Run multiple campaigns with controlled priority to avoid conflicts

= Performance & Architecture =

* **Service Container & DI** - Modern dependency injection architecture
* **Efficient Database Layer** - Custom query optimization with prepared statements
* **Asset Management System** - Intelligent script/style loading only where needed
* **Modular Wizard System** - Step-based architecture with state management
* **AJAX-Powered UI** - Fast, responsive admin interface without page reloads
* **Scales Efficiently** - Handles thousands of products with optimized queries

= Developer Friendly =

* **Extensive Hooks** - Actions and filters throughout for customization
* **Well-Documented Code** - Comprehensive PHPDoc blocks and inline comments
* **Modular Architecture** - MVC pattern with separated concerns
* **REST API Ready** - API endpoints for external integrations (Pro)

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or later
* WooCommerce 8.0 or later
* PHP 8.0 or later (PHP 8.3+ recommended)
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

**Create Your First Campaign in 5 Minutes**

After activating the plugin, you'll see a new "Campaigns" menu item in your WordPress admin sidebar. Here's how to create your first discount campaign:

= Step 1: Launch the Campaign Wizard =

1. Click **Campaigns > Create Campaign** in your WordPress admin sidebar
2. The 5-step Campaign Wizard will open in fullscreen mode

= Step 2: Configure Basic Information =

1. **Campaign Name**: Enter a descriptive name (e.g., "Summer Sale 2025")
2. **Description**: Add notes about the campaign (optional, for internal reference)
3. **Priority**: Set priority level 1-5 (5 is highest)
   - Use priority when running multiple campaigns simultaneously
   - Higher priority campaigns override lower priority ones on the same product
4. Click **Next** to continue

= Step 3: Select Products =

Choose which products to discount:

* **All Products**: Apply discount to your entire catalog
* **Specific Products**: Use the search box to find and select individual products
  - Search by product name, SKU, or ID
  - Click products to add/remove from selection
  - Selected count shows at the bottom
* **Random Products**: Automatically select X random products from your catalog
  - Set how many products to discount
  - Products are randomly selected each time the campaign runs

Click **Next** when products are selected.

= Step 4: Configure Discount =

Choose your discount type:

**Free Version:**
* **Percentage Discount**: Enter percentage (e.g., 25 for 25% off)
* **Fixed Amount**: Enter fixed discount amount (e.g., 10.00 for $10 off)

**Pro Version Only:**
* **Tiered Discount**: Create quantity-based pricing tiers
* **BOGO**: Configure Buy One Get One offers
* **Spend Threshold**: Discount based on cart total

Click **Next** to continue.

= Step 5: Schedule Campaign =

* **Start Date & Time**: When campaign should begin
* **End Date & Time**: When campaign should end (optional - leave blank for no end date)
* **Timezone**: Select your timezone (defaults to WordPress timezone)
* **Recurring** (Pro): Set daily, weekly, or monthly recurring patterns
* **Status**: Set initial status (Draft, Scheduled, or Active)

Click **Next** to review.

= Step 6: Review & Create =

* Review all campaign settings in the sidebar
* Check the **Campaign Health** indicator (shows any potential issues)
* Click **Create Campaign** to activate your discount campaign

**That's it!** Your discount campaign is now live and automatically applying to the selected products.

= Managing Campaigns =

Access the **Campaigns** page to:

* **View All Campaigns**: See status, priority, products, and schedule at a glance
* **Bulk Actions**: Enable, disable, duplicate, or delete multiple campaigns
* **Quick Edit**: Click campaign name to edit settings
* **Duplicate**: Clone existing campaign to create similar ones quickly
* **Campaign Health**: Monitor issues and optimization opportunities

= Common Use Cases =

**Flash Sale (Time-Limited)**
1. Select "Specific Products" or "All Products"
2. Set percentage discount (e.g., 30%)
3. Set start/end date and time
4. Status: Scheduled (activates automatically)

**Weekend Sale (Recurring - Pro)**
1. Select products to discount
2. Set percentage discount
3. Choose "Weekly" recurring pattern
4. Select Saturday & Sunday
5. Set start time Friday 12:01 AM, end time Sunday 11:59 PM

**Seasonal Sale (Long-Running)**
1. Select "All Products" or specific categories
2. Set percentage discount
3. Set start date (e.g., Dec 1) and end date (e.g., Dec 31)
4. Priority: 3 (medium) to allow other campaigns to override if needed

= Need Help? =

* **Documentation**: Visit our [documentation site](https://webstepper.io/wordpress-plugins/smart-cycle-discounts)
* **Support Forum**: Get help at [WordPress.org support forum](https://wordpress.org/support/plugin/smart-cycle-discounts/)
* **Video Tutorial**: Watch our [5-minute video tutorial](https://webstepper.io/wordpress-plugins/smart-cycle-discounts/getting-started)

== Frequently Asked Questions ==

= Is Smart Cycle Discounts compatible with my theme? =

Yes! Smart Cycle Discounts is a backend plugin that works with any properly coded WordPress theme. It integrates with WooCommerce's native sale price system, so your theme's existing sale price styling will work automatically.

= Can I schedule campaigns in advance? =

Absolutely! Use the Schedule step in the wizard to set specific start and end dates/times. Campaigns will activate and deactivate automatically based on your schedule. You can create campaigns weeks or months in advance.

= How many campaigns can I create with the free version? =

The free version allows up to **3 active campaigns** running simultaneously. You can create unlimited draft and scheduled campaigns. Upgrade to Pro for unlimited active campaigns.

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

Yes! You can run multiple campaigns simultaneously (up to 3 active in free version, unlimited in Pro). Use the Priority field (1-5, where 5 is highest priority) to control which campaign takes precedence when multiple campaigns affect the same product.

= What's the difference between "All Products", "Specific Products", and "Random Products"? =

* **All Products** - Applies discount to your entire product catalog
* **Specific Products** - You manually select which products to discount using the AJAX-powered product search
* **Random Products** - Plugin automatically selects X random products from your catalog when the campaign runs

= Does it work with WooCommerce HPOS (High-Performance Order Storage)? =

Yes! Smart Cycle Discounts is fully compatible with WooCommerce HPOS. We've declared HPOS compatibility and tested thoroughly with custom order tables.

= Can I duplicate an existing campaign? =

Yes. On the Campaigns list page, hover over any campaign and click the "Duplicate" action. This creates a copy of the campaign with all settings intact, which you can then modify as needed. Great for creating similar campaigns quickly!

= How does the priority system work? =

When multiple campaigns affect the same product, the campaign with the highest priority (5 = highest, 1 = lowest) takes precedence. This lets you run multiple campaigns without conflicts - for example, a high-priority flash sale can override a lower-priority seasonal sale.

= Can I export campaign data? =

Campaign analytics and export features are available in the **Pro version**. Pro users can export campaign performance data in CSV or JSON format, and schedule automated reports.

= What happens when a campaign ends? =

When a campaign reaches its end date/time, it automatically deactivates and removes the sale prices from affected products. The campaign remains in your list as "Ended" and can be duplicated or reactivated with new dates.

= Is there a way to test campaigns before activating them? =

Yes! Use the **Draft** status to create and configure campaigns without activating them. You can review the campaign settings, check the health score, and preview which products will be affected. When ready, simply change the status to Active or Scheduled.

== Screenshots ==

1. Campaign wizard - Step 1: Basic Information (name, description, priority)
2. Campaign wizard - Step 2: Product Selection (all products, specific products, or random selection)
3. Campaign wizard - Step 3: Discount Configuration (percentage or fixed amount in free version)
4. Campaign wizard - Step 4: Schedule Settings (start/end dates, times, timezone, recurring patterns)
5. Campaign wizard - Step 5: Review & Confirm with health scoring (preview all settings before creating)
6. Campaigns list - Manage all campaigns with bulk actions (enable, disable, duplicate, delete)
7. Product search - Real-time AJAX search by product name, SKU, or ID with instant results
8. Campaign priority - Set priority levels 1-5 to control which discounts take precedence

== Changelog ==

= 1.0.0 =
* Initial release
* 5-step campaign wizard (Basic Info, Products, Discounts, Schedule, Review)
* Three product selection modes: All Products, Specific Products, Random Products
* Two discount types (Free): Percentage and Fixed Amount
* Three advanced discount types (Pro): Tiered, BOGO, Spend Threshold
* Up to 3 active campaigns (Free), unlimited campaigns (Pro)
* Advanced scheduling with date/time/timezone support
* Recurring campaigns (Pro): Daily, weekly, and monthly patterns
* Priority system (1-5) for campaign precedence
* AJAX-powered product search by name, SKU, or ID
* Bulk campaign management (activate, deactivate, duplicate, delete)
* Campaign health monitoring and validation
* Analytics dashboard (Pro)
* Email notifications: Campaign started/ended (Free), Advanced alerts (Pro)
* Service container architecture with dependency injection
* Custom database abstraction layer
* Modular wizard system with state management
* Asset management system for optimized loading
* HPOS (High-Performance Order Storage) compatibility
* WordPress 6.4+ compatibility
* WooCommerce 8.0+ compatibility
* PHP 8.0+ compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Smart Cycle Discounts. Create intelligent WooCommerce discount campaigns with advanced scheduling, priority management, and flexible product selection.

== Additional Information ==

= Support & Documentation =

* Official Website: https://webstepper.io
* Plugin Page: https://webstepper.io/wordpress-plugins/smart-cycle-discounts
* WordPress.org Page: https://wordpress.org/plugins/smart-cycle-discounts/
* Support Forum: https://wordpress.org/support/plugin/smart-cycle-discounts/
* Email Support: contact@webstepper.io
* Rate this plugin: https://wordpress.org/support/plugin/smart-cycle-discounts/reviews/

= Privacy & Data Collection =

Smart Cycle Discounts stores all campaign data locally in your WordPress database. The plugin does not collect or transmit customer data to external services by default.

**Optional Third-Party Services**: If you choose to enable optional email notification features (available in Pro version), the plugin can integrate with external email delivery services (SendGrid or Amazon SES). These services are completely optional and disabled by default. See "Third-Party Services" section below for details.

This plugin respects your privacy and follows WordPress.org privacy guidelines.

= Third-Party Services =

Smart Cycle Discounts includes **optional** integrations with third-party email delivery services. These services are **disabled by default** and only activated if you explicitly configure them in the plugin settings.

**SendGrid Email Service** (Optional - Pro Feature)

* **Service**: SendGrid API
* **Website**: https://sendgrid.com/
* **API Endpoint**: https://api.sendgrid.com/v3/mail/send
* **When Used**: Only if you configure SendGrid API credentials in Settings > Email Notifications (Pro version)
* **Data Sent**: Email addresses, email content (campaign notifications only)
* **Purpose**: Deliver email notifications about campaign status and results
* **Privacy Policy**: https://www.twilio.com/legal/privacy
* **Terms of Service**: https://www.twilio.com/legal/tos

**Amazon SES (Simple Email Service)** (Optional - Pro Feature)

* **Service**: Amazon Web Services - Simple Email Service
* **Website**: https://aws.amazon.com/ses/
* **API Endpoint**: https://email.{region}.amazonaws.com/
* **When Used**: Only if you configure AWS SES credentials in Settings > Email Notifications (Pro version)
* **Data Sent**: Email addresses, email content (campaign notifications only)
* **Purpose**: Deliver email notifications about campaign status and results
* **Privacy Policy**: https://aws.amazon.com/privacy/
* **Terms of Service**: https://aws.amazon.com/service-terms/

**Important Notes**:

* Both services are completely optional and disabled by default
* Available only in Pro version
* No data is sent to these services unless you explicitly configure API credentials
* You can use the plugin's full functionality without enabling any email integrations
* The free version uses standard WordPress email (wp_mail) for basic campaign notifications
* If you enable these services, you are responsible for compliance with their terms of service
* Email notifications only contain campaign-related data, never customer personal information
