<?php
/**
 * 001 Initial Schema
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations/001-initial-schema.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Initial Schema Migration
 *
 * Creates all database tables for the Smart Cycle Discounts plugin.
 * Includes campaigns, active_discounts, analytics, customer_usage, and campaign_recurring tables.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Migration_001_Initial_Schema {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private SCD_Database_Manager $db;

	/**
	 * Initialize the migration.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $db    Database manager.
	 */
	public function __construct( SCD_Database_Manager $db ) {
		$this->db = $db;
	}

	/**
	 * Run the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function up(): void {
		$charset_collate = $this->db->get_charset_collate();

		$this->create_campaigns_table( $charset_collate );
		$this->create_active_discounts_table( $charset_collate );
		$this->create_analytics_table( $charset_collate );
		$this->create_customer_usage_table( $charset_collate );
		$this->create_campaign_recurring_table( $charset_collate );

		$this->add_all_foreign_keys();
	}

	/**
	 * Create campaigns table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $charset_collate    Charset collate.
	 * @return   void
	 */
	private function create_campaigns_table( string $charset_collate ): void {
		$table_name = $this->db->get_table_name( 'campaigns' );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			description longtext,
			status enum('draft','scheduled','active','paused','expired','archived') DEFAULT 'draft',
			priority tinyint(3) unsigned DEFAULT 3,
			settings longtext,
			metadata longtext,
			template_id varchar(100) DEFAULT NULL,
			color_theme varchar(7) DEFAULT '#2271b1',
			icon varchar(100) DEFAULT 'dashicons-tag',

			product_selection_type enum('all_products','random_products','specific_products','smart_selection') DEFAULT 'all_products',
			product_ids longtext COMMENT 'JSON array of product IDs',
			category_ids longtext COMMENT 'JSON array of category IDs',
			tag_ids longtext COMMENT 'JSON array of tag IDs',

			rotation_enabled tinyint(1) DEFAULT 0,
			rotation_interval int(11) unsigned DEFAULT 24 COMMENT 'Hours between rotations',
			rotation_type enum('sequential','random','performance_based','inventory_based') DEFAULT 'sequential',
			max_concurrent_products int(11) unsigned DEFAULT 5,
			last_rotation_at datetime DEFAULT NULL,

			discount_rules longtext COMMENT 'JSON configuration for discount rules',
			discount_value decimal(10,4) DEFAULT 0.0000,
			discount_type enum('percentage','fixed','bogo','tiered','spend_threshold') DEFAULT 'percentage',

			usage_limits longtext COMMENT 'JSON configuration for usage limits',
			max_uses int(11) unsigned DEFAULT NULL,
			max_uses_per_customer int(11) unsigned DEFAULT NULL,
			current_uses int(11) unsigned DEFAULT 0,

			created_by bigint(20) unsigned NOT NULL,
			updated_by bigint(20) unsigned DEFAULT NULL,

			starts_at datetime DEFAULT NULL,
			ends_at datetime DEFAULT NULL,
			timezone varchar(50) DEFAULT 'UTC',

			products_count int(11) unsigned DEFAULT 0,
			revenue_generated decimal(15,4) DEFAULT 0.0000,
			orders_count int(11) unsigned DEFAULT 0,
			impressions_count int(11) unsigned DEFAULT 0,
			clicks_count int(11) unsigned DEFAULT 0,
			conversion_rate decimal(5,2) DEFAULT 0.00,

			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_uuid (uuid),
			UNIQUE KEY unique_slug (slug),
			KEY idx_name (name),
			KEY idx_status (status),
			KEY idx_priority (priority),
			KEY idx_created_by (created_by),
			KEY idx_updated_by (updated_by),
			KEY idx_template_id (template_id),
			KEY idx_schedule (starts_at, ends_at),
			KEY idx_performance (revenue_generated, orders_count),
			KEY idx_engagement (impressions_count, clicks_count),
			KEY idx_created_at (created_at),
			KEY idx_updated_at (updated_at),
			KEY idx_deleted_at (deleted_at),
			KEY idx_active_campaigns (status, starts_at, ends_at, deleted_at),
			KEY idx_user_campaigns (created_by, status, deleted_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create active_discounts table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $charset_collate    Charset collate.
	 * @return   void
	 */
	private function create_active_discounts_table( string $charset_collate ): void {
		$table_name = $this->db->get_table_name( 'active_discounts' );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			campaign_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned DEFAULT NULL,
			original_price decimal(15,4) NOT NULL,
			discounted_price decimal(15,4) NOT NULL,
			discount_amount decimal(15,4) NOT NULL,
			discount_percentage decimal(5,2) DEFAULT NULL,
			discount_type enum('percentage','fixed','bogo','tiered','spend_threshold') NOT NULL,
			discount_rules longtext,
			conditions longtext,
			valid_from datetime NOT NULL,
			valid_until datetime DEFAULT NULL,
			timezone varchar(50) DEFAULT 'UTC',
			status enum('active','paused','expired','removed') DEFAULT 'active',
			priority tinyint(3) unsigned DEFAULT 3,
			application_count int(11) unsigned DEFAULT 0,
			revenue_generated decimal(15,4) DEFAULT 0.0000,
			last_applied_at datetime DEFAULT NULL,
			stock_quantity int(11) DEFAULT NULL,
			max_applications int(11) DEFAULT NULL,
			customer_restrictions longtext,
			geographic_restrictions longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_uuid (uuid),
			UNIQUE KEY unique_campaign_product (campaign_id, product_id, variation_id),
			KEY idx_campaign_id (campaign_id),
			KEY idx_product_id (product_id),
			KEY idx_variation_id (variation_id),
			KEY idx_status (status),
			KEY idx_priority (priority),
			KEY idx_discount_type (discount_type),
			KEY idx_validity (valid_from, valid_until),
			KEY idx_performance (revenue_generated, application_count),
			KEY idx_last_applied (last_applied_at),
			KEY idx_active_discounts (status, valid_from, valid_until),
			KEY idx_product_discounts (product_id, status, valid_from, valid_until),
			KEY idx_campaign_status (campaign_id, status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create analytics table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $charset_collate    Charset collate.
	 * @return   void
	 */
	private function create_analytics_table( string $charset_collate ): void {
		$table_name = $this->db->get_table_name( 'analytics' );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			date_recorded date NOT NULL,
			hour_recorded tinyint(2) unsigned DEFAULT NULL,
			impressions int(11) unsigned DEFAULT 0,
			clicks int(11) unsigned DEFAULT 0,
			conversions int(11) unsigned DEFAULT 0,
			revenue decimal(15,4) DEFAULT 0.0000,
			discount_given decimal(15,4) DEFAULT 0.0000,
			profit_margin decimal(15,4) DEFAULT 0.0000,
			products_shown int(11) unsigned DEFAULT 0,
			products_clicked int(11) unsigned DEFAULT 0,
			products_purchased int(11) unsigned DEFAULT 0,
			unique_customers int(11) unsigned DEFAULT 0,
			returning_customers int(11) unsigned DEFAULT 0,
			cart_additions int(11) unsigned DEFAULT 0,
			cart_abandonments int(11) unsigned DEFAULT 0,
			checkout_starts int(11) unsigned DEFAULT 0,
			checkout_completions int(11) unsigned DEFAULT 0,
			average_order_value decimal(15,4) DEFAULT 0.0000,
			bounce_rate decimal(5,2) DEFAULT 0.00,
			time_on_page int(11) unsigned DEFAULT 0,
			page_views int(11) unsigned DEFAULT 0,
			session_duration int(11) unsigned DEFAULT 0,
			device_mobile int(11) unsigned DEFAULT 0,
			device_tablet int(11) unsigned DEFAULT 0,
			device_desktop int(11) unsigned DEFAULT 0,
			traffic_organic int(11) unsigned DEFAULT 0,
			traffic_direct int(11) unsigned DEFAULT 0,
			traffic_referral int(11) unsigned DEFAULT 0,
			traffic_social int(11) unsigned DEFAULT 0,
			traffic_email int(11) unsigned DEFAULT 0,
			traffic_paid int(11) unsigned DEFAULT 0,
			geographic_data longtext,
			demographic_data longtext,
			behavioral_data longtext,
			extended_metrics longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_campaign_date_hour (campaign_id, date_recorded, hour_recorded),
			KEY idx_campaign_id (campaign_id),
			KEY idx_date_recorded (date_recorded),
			KEY idx_hour_recorded (hour_recorded),
			KEY idx_performance (revenue, conversions),
			KEY idx_engagement (impressions, clicks),
			KEY idx_customer_metrics (unique_customers, returning_customers),
			KEY idx_conversion_funnel (cart_additions, checkout_starts, checkout_completions),
			KEY idx_traffic_sources (traffic_organic, traffic_direct, traffic_referral),
			KEY idx_device_breakdown (device_mobile, device_tablet, device_desktop),
			KEY idx_daily_analytics (campaign_id, date_recorded),
			KEY idx_hourly_analytics (campaign_id, date_recorded, hour_recorded)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create customer_usage table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $charset_collate    Charset collate.
	 * @return   void
	 */
	private function create_customer_usage_table( string $charset_collate ): void {
		$table_name = $this->db->get_table_name( 'customer_usage' );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned DEFAULT NULL,
			customer_email varchar(100) NOT NULL,
			usage_count int(11) unsigned NOT NULL DEFAULT 1,
			first_used_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_used_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			total_discount_amount decimal(15,4) DEFAULT 0.0000,
			total_order_value decimal(15,4) DEFAULT 0.0000,
			order_ids longtext,
			session_id varchar(255) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			status enum('active','blocked','expired') DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_campaign_customer (campaign_id, customer_email),
			KEY idx_campaign_id (campaign_id),
			KEY idx_customer_id (customer_id),
			KEY idx_customer_email (customer_email),
			KEY idx_session_id (session_id),
			KEY idx_usage_count (usage_count),
			KEY idx_last_used (last_used_at),
			KEY idx_status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create campaign_recurring table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $charset_collate    Charset collate.
	 * @return   void
	 */
	private function create_campaign_recurring_table( string $charset_collate ): void {
		$table_name = $this->db->get_table_name( 'campaign_recurring' );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			parent_campaign_id bigint(20) unsigned DEFAULT 0,
			recurrence_pattern varchar(20) NOT NULL DEFAULT 'daily',
			recurrence_interval int(11) NOT NULL DEFAULT 1,
			recurrence_days text,
			recurrence_end_type varchar(20) NOT NULL DEFAULT 'never',
			recurrence_count int(11) DEFAULT NULL,
			recurrence_end_date date DEFAULT NULL,
			occurrence_number int(11) NOT NULL DEFAULT 1,
			next_occurrence_date datetime DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_campaign_recurring (campaign_id),
			KEY idx_parent_campaign (parent_campaign_id),
			KEY idx_next_occurrence (next_occurrence_date, is_active),
			KEY idx_active_recurring (is_active, recurrence_end_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add all foreign key constraints.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_all_foreign_keys(): void {
		global $wpdb;

		// Suppress errors as foreign keys might fail on some MySQL configurations
		$wpdb->suppress_errors();

		try {
			// Campaigns table foreign keys
			$this->add_campaigns_foreign_keys();

			// Active discounts table foreign keys
			$this->add_active_discounts_foreign_keys();

			// Analytics table foreign keys
			$this->add_analytics_foreign_keys();

			// Customer usage table foreign keys
			$this->add_customer_usage_foreign_keys();

		} catch ( Exception $e ) {
			// Silently catch foreign key errors
			// Don't output anything during activation to prevent "headers already sent" errors
		}

		// Re-enable error display
		$wpdb->suppress_errors( false );
	}

	/**
	 * Add foreign keys for campaigns table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_campaigns_foreign_keys(): void {
		global $wpdb;
		$table_name = $this->db->get_table_name( 'campaigns' );

		// Foreign key for created_by (references wp_users)
		if ( ! $this->foreign_key_exists( $table_name, 'fk_campaigns_created_by' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_campaigns_created_by
					FOREIGN KEY (created_by) REFERENCES %i(ID)
					ON DELETE RESTRICT ON UPDATE CASCADE',
					$table_name,
					$wpdb->users
				)
			);
		}

		// Foreign key for updated_by (references wp_users)
		if ( ! $this->foreign_key_exists( $table_name, 'fk_campaigns_updated_by' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_campaigns_updated_by
					FOREIGN KEY (updated_by) REFERENCES %i(ID)
					ON DELETE SET NULL ON UPDATE CASCADE',
					$table_name,
					$wpdb->users
				)
			);
		}
	}

	/**
	 * Add foreign keys for active_discounts table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_active_discounts_foreign_keys(): void {
		global $wpdb;
		$table_name      = $this->db->get_table_name( 'active_discounts' );
		$campaigns_table = $this->db->get_table_name( 'campaigns' );

		// Foreign key for campaign_id
		if ( ! $this->foreign_key_exists( $table_name, 'fk_active_discounts_campaign_id' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_active_discounts_campaign_id
					FOREIGN KEY (campaign_id) REFERENCES %i(id)
					ON DELETE CASCADE ON UPDATE CASCADE',
					$table_name,
					$campaigns_table
				)
			);
		}

		// Foreign key for product_id (references wp_posts)
		if ( ! $this->foreign_key_exists( $table_name, 'fk_active_discounts_product_id' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_active_discounts_product_id
					FOREIGN KEY (product_id) REFERENCES %i(ID)
					ON DELETE CASCADE ON UPDATE CASCADE',
					$table_name,
					$wpdb->posts
				)
			);
		}

		// Foreign key for variation_id (references wp_posts)
		if ( ! $this->foreign_key_exists( $table_name, 'fk_active_discounts_variation_id' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_active_discounts_variation_id
					FOREIGN KEY (variation_id) REFERENCES %i(ID)
					ON DELETE CASCADE ON UPDATE CASCADE',
					$table_name,
					$wpdb->posts
				)
			);
		}
	}

	/**
	 * Add foreign keys for analytics table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_analytics_foreign_keys(): void {
		global $wpdb;
		$table_name      = $this->db->get_table_name( 'analytics' );
		$campaigns_table = $this->db->get_table_name( 'campaigns' );

		// Foreign key for campaign_id
		if ( ! $this->foreign_key_exists( $table_name, 'fk_analytics_campaign_id' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_analytics_campaign_id
					FOREIGN KEY (campaign_id) REFERENCES %i(id)
					ON DELETE CASCADE ON UPDATE CASCADE',
					$table_name,
					$campaigns_table
				)
			);
		}
	}

	/**
	 * Add foreign keys for customer_usage table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_customer_usage_foreign_keys(): void {
		global $wpdb;
		$table_name      = $this->db->get_table_name( 'customer_usage' );
		$campaigns_table = $this->db->get_table_name( 'campaigns' );

		// Foreign key for campaign_id
		if ( ! $this->foreign_key_exists( $table_name, 'fk_customer_usage_campaign_id' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_customer_usage_campaign_id
					FOREIGN KEY (campaign_id) REFERENCES %i(id)
					ON DELETE CASCADE ON UPDATE CASCADE',
					$table_name,
					$campaigns_table
				)
			);
		}

		// Foreign key for customer_id (references wp_users)
		if ( ! $this->foreign_key_exists( $table_name, 'fk_customer_usage_customer_id' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i
					ADD CONSTRAINT fk_customer_usage_customer_id
					FOREIGN KEY (customer_id) REFERENCES %i(ID)
					ON DELETE SET NULL ON UPDATE CASCADE',
					$table_name,
					$wpdb->users
				)
			);
		}
	}

	/**
	 * Check if a foreign key exists.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $table_name        Table name.
	 * @param    string $constraint_name   Constraint name.
	 * @return   bool                       True if exists.
	 */
	private function foreign_key_exists( string $table_name, string $constraint_name ): bool {
		$result = $this->db->get_var(
			$this->db->prepare(
				'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
				WHERE CONSTRAINT_SCHEMA = DATABASE()
				AND TABLE_NAME = %s
				AND CONSTRAINT_NAME = %s',
				$table_name,
				$constraint_name
			)
		);
		return ! empty( $result );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function down(): void {
		global $wpdb;

		// Drop tables in reverse order (respecting foreign key dependencies)
		$tables = array(
			'customer_usage',
			'campaign_recurring',
			'analytics',
			'active_discounts',
			'campaigns',
		);

		foreach ( $tables as $table ) {
			$table_name = $this->db->get_table_name( $table );

			// Drop foreign keys first
			$this->drop_foreign_keys( $table_name );

			// Drop the table
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
		}
	}

	/**
	 * Drop foreign key constraints.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $table_name    Table name.
	 * @return   void
	 */
	private function drop_foreign_keys( string $table_name ): void {
		global $wpdb;

		$constraints = $this->get_foreign_key_constraints( $table_name );

		foreach ( $constraints as $constraint ) {
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP FOREIGN KEY %i',
					$table_name,
					$constraint
				)
			);
		}
	}

	/**
	 * Get foreign key constraints for a table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $table_name    Table name.
	 * @return   array                    Foreign key constraint names.
	 */
	private function get_foreign_key_constraints( string $table_name ): array {
		$constraints = array();

		$results = $this->db->get_results(
			$this->db->prepare(
				'SELECT CONSTRAINT_NAME
				FROM information_schema.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND REFERENCED_TABLE_NAME IS NOT NULL',
				DB_NAME,
				$table_name
			)
		);

		foreach ( $results as $result ) {
			$constraints[] = $result->CONSTRAINT_NAME;
		}

		return $constraints;
	}
}
