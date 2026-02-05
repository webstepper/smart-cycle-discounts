<?php
/**
 * Campaign Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-campaign-repository.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-base-repository.php';

/**
 * Campaign Repository
 *
 * Handles data access for campaigns using the base repository pattern.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class WSSCD_Campaign_Repository extends WSSCD_Base_Repository {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Database_Manager    $db    Database manager.
	 */
	private $db;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache    Cache manager.
	 */
	private $cache;

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Database_Manager $db       Database manager.
	 * @param    WSSCD_Cache_Manager    $cache    Cache manager.
	 */
	public function __construct( WSSCD_Database_Manager $db, WSSCD_Cache_Manager $cache ) {
		global $wpdb;
		$this->db          = $db;
		$this->cache       = $cache;
		$this->table_name  = $wpdb->prefix . 'wsscd_campaigns';
		$this->json_fields = array( 'conditions', 'category_ids', 'tag_ids', 'attributes', 'product_ids', 'variation_rules', 'free_shipping_config', 'user_roles' );
		$this->date_fields = array( 'created_at', 'updated_at', 'starts_at', 'ends_at', 'deleted_at' );
	}

	/**
	 * Get standardized cache key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type   Cache key type.
	 * @param    mixed  ...$params Parameters for cache key.
	 * @return   string            Standardized cache key.
	 */
	private function get_cache_key( string $type, ...$params ): string {
		$key_parts = array( 'campaigns', $type );
		foreach ( $params as $param ) {
			if ( is_array( $param ) ) {
				$key_parts[] = md5( serialize( $param ) );
			} else {
				$key_parts[] = $param;
			}
		}
		return implode( '_', $key_parts );
	}

	/**
	 * Invalidate all caches for a campaign.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign Campaign to invalidate.
	 * @return   void
	 */
	private function invalidate_campaign_cache( WSSCD_Campaign $campaign ): void {
		// Clear specific campaign caches
		$this->cache->delete( $this->get_cache_key( 'id', $campaign->get_id() ) );
		$this->cache->delete( $this->get_cache_key( 'id_trashed', $campaign->get_id() ) );

		// Clear UUID and slug caches if they exist
		if ( $campaign->get_uuid() ) {
			$this->cache->delete( $this->get_cache_key( 'uuid', $campaign->get_uuid() ) );
		}
		if ( $campaign->get_slug() ) {
			$this->cache->delete( $this->get_cache_key( 'slug', $campaign->get_slug() ) );
		}

		// Clear all campaign caches (uses group-based invalidation)
		$this->cache->delete_group( 'campaigns' );
	}

	/**
	 * Find campaign by ID with optional ownership check.
	 *
	 * @since    1.0.0
	 * @param    int  $id                Campaign ID.
	 * @param    bool $include_trashed   Include trashed campaigns.
	 * @param    bool $skip_auth_check   Skip ownership check (use with caution).
	 * @return   WSSCD_Campaign|null         Campaign or null if not found or unauthorized.
	 */
	public function find( $id, $include_trashed = false, $skip_auth_check = false ) {
		$campaign = $this->find_internal( $id, $include_trashed );

		if ( ! $campaign ) {
			return null;
		}

		// Skip auth check if explicitly requested
		if ( $skip_auth_check ) {
			return $campaign;
		}

		// Get current user ID - 0 indicates no user (cron/system operations)
		$user_id = get_current_user_id();

		// Skip auth check for system operations (no user context)
		if ( 0 === $user_id ) {
			return $campaign;
		}

		// Admins can access all campaigns
		if ( current_user_can( 'manage_options' ) ) {
			return $campaign;
		}

		// Enforce ownership for non-admin users
		if ( $campaign->get_created_by() !== $user_id ) {
			// Return null instead of WP_Error to avoid information disclosure
			return null;
		}

		return $campaign;
	}

	/**
	 * Find campaign by ID without authorization check.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int  $id                Campaign ID.
	 * @param    bool $include_trashed   Include trashed campaigns.
	 * @return   WSSCD_Campaign|null         Campaign or null if not found.
	 */
	private function find_internal( $id, $include_trashed = false ) {
		$cache_key = $include_trashed ?
			$this->get_cache_key( 'id_trashed', $id ) :
			$this->get_cache_key( 'id', $id );

		return $this->cache->remember(
			$cache_key,
			function () use ( $id, $include_trashed ) {
				global $wpdb;

				// JOIN with recurring table to load recurring configuration
				$recurring_table = $wpdb->prefix . 'wsscd_campaign_recurring';

				if ( $include_trashed ) {
					$query = "
						SELECT c.*,
							r.recurrence_pattern,
							r.recurrence_interval,
							r.recurrence_days,
							r.recurrence_end_type,
							r.recurrence_count,
							r.recurrence_end_date,
							r.recurrence_mode,
							r.is_active as recurring_is_active
						FROM {$this->table_name} c
						LEFT JOIN {$recurring_table} r
							ON c.id = r.campaign_id AND r.parent_campaign_id = 0
						WHERE c.id = %d
					";
				} else {
					$query = "
						SELECT c.*,
							r.recurrence_pattern,
							r.recurrence_interval,
							r.recurrence_days,
							r.recurrence_end_type,
							r.recurrence_count,
							r.recurrence_end_date,
							r.recurrence_mode,
							r.is_active as recurring_is_active
						FROM {$this->table_name} c
						LEFT JOIN {$recurring_table} r
							ON c.id = r.campaign_id AND r.parent_campaign_id = 0
						WHERE c.id = %d AND c.deleted_at IS NULL
					";
				}

				$data = $this->db->get_row(
					$this->db->prepare( $query, $id )
				);

				return $data ? $this->hydrate( $data ) : null;
			},
			3600
		);
	}

	/**
	 * Find campaign by ID with ownership check.
	 *
	 * @since    1.0.0
	 * @param    int $id        Campaign ID.
	 * @param    int $user_id   User ID to verify ownership.
	 * @return   WSSCD_Campaign|null   Campaign or null if not found or unauthorized.
	 */
	public function find_for_user( $id, $user_id ) {
		$campaign = $this->find_internal( $id, false );

		if ( ! $campaign ) {
			return null;
		}

		if ( $campaign->get_created_by() !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		return $campaign;
	}

	/**
	 * Find campaign by UUID.
	 *
	 * @since    1.0.0
	 * @param    string $uuid    Campaign UUID.
	 * @return   WSSCD_Campaign|null      Campaign or null if not found.
	 */
	public function find_by_uuid( string $uuid ): ?WSSCD_Campaign {
		$cache_key = "campaigns_uuid_{$uuid}";

		return $this->cache->remember(
			$cache_key,
			function () use ( $uuid ) {
				$data = $this->db->get_row(
					$this->db->prepare(
						"SELECT * FROM {$this->table_name} WHERE uuid = %s AND deleted_at IS NULL",
						$uuid
					)
				);

				return $data ? $this->hydrate( $data ) : null;
			},
			3600
		);
	}

	/**
	 * Find campaign by slug.
	 *
	 * @since    1.0.0
	 * @param    string $slug    Campaign slug.
	 * @return   WSSCD_Campaign|null      Campaign or null if not found.
	 */
	public function find_by_slug( string $slug ): ?WSSCD_Campaign {
		$cache_key = "campaigns_slug_{$slug}";

		return $this->cache->remember(
			$cache_key,
			function () use ( $slug ) {
				$data = $this->db->get_row(
					$this->db->prepare(
						"SELECT * FROM {$this->table_name} WHERE slug = %s AND deleted_at IS NULL",
						$slug
					)
				);

				return $data ? $this->hydrate( $data ) : null;
			},
			3600
		);
	}

	/**
	 * Find campaigns by criteria.
	 *
	 * @since    1.0.0
	 * @param    array $criteria    Search criteria.
	 * @param    array $options     Query options.
	 * @return   array                 Array of campaigns.
	 */
	public function find_by( array $criteria = array(), array $options = array() ): array {
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		// Only process valid database fields
		$valid_fields = array( 'status', 'created_by', 'discount_type', 'id', 'uuid', 'slug', 'name' );

		// Build WHERE clauses
		foreach ( $criteria as $field => $value ) {
			// Skip empty values and non-database fields
			if ( empty( $value ) || ! in_array( $field, $valid_fields ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$placeholders    = implode( ',', array_fill( 0, count( $value ), '%s' ) );
				$where_clauses[] = "$field IN ($placeholders)";
				$where_values    = array_merge( $where_values, $value );
			} else {
				$where_clauses[] = "$field = %s";
				$where_values[]  = $value;
			}
		}

		$order_by = $this->build_order_by_clause( $options );
		$limit    = $this->build_limit_clause( $options );

		$where_clause = implode( ' AND ', $where_clauses );
		$query        = "SELECT * FROM {$this->table_name} WHERE $where_clause $order_by $limit";

		return $this->execute_and_hydrate( $query, $where_values );
	}

	/**
	 * Find campaigns by metadata key/value.
	 *
	 * @since    1.0.0
	 * @param    string $meta_key      Metadata key.
	 * @param    string $meta_value    Metadata value.
	 * @param    array  $options       Query options.
	 * @return   array                   Array of campaigns.
	 */
	public function find_by_metadata( string $meta_key, string $meta_value, array $options = array() ): array {
		global $wpdb;

		// Whitelist allowed metadata keys to prevent SQL injection
		$allowed_meta_keys = array( 'suggestion_id', 'source', 'parent_id', 'import_id', 'template_id' );
		if ( ! in_array( $meta_key, $allowed_meta_keys, true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for invalid metadata key, only in WP_DEBUG mode.
				error_log( '[WSSCD_Repository] Invalid metadata key requested: ' . $meta_key );
			}
			return array();
		}

		$order_by = $this->build_order_by_clause( $options );
		$limit    = $this->build_limit_clause( $options );

		// Query using JSON_EXTRACT for metadata (MySQL 5.7+)
		// JSON path is now properly prepared as a parameter
		$json_path = '$.' . $meta_key;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- order_by and limit are built by sanitized helper methods with whitelisted values.
		$query = $wpdb->prepare(
			'SELECT * FROM %i
			WHERE deleted_at IS NULL
			AND JSON_EXTRACT(metadata, %s) = %s
			' . $order_by . ' ' . $limit,
			$this->table_name,
			$json_path,
			$meta_value
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $this->execute_and_hydrate( $query, array() );
	}

	/**
	 * Update campaign status atomically (prevents race conditions).
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id     Campaign ID.
	 * @param    string $new_status      New status.
	 * @param    string $expected_status Expected current status (for atomic check).
	 * @return   bool                       True if updated, false if status mismatch.
	 */
	public function update_status_atomic( int $campaign_id, string $new_status, string $expected_status ): bool {
		global $wpdb;

		// Atomic update: Only update if current status matches expected
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Atomic status update on plugin's custom table; cache invalidated below.
		$affected_rows = $wpdb->update(
			$this->table_name,
			array(
				'status'     => $new_status,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'id'     => $campaign_id,
				'status' => $expected_status,  // Only update if still in expected status
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( $affected_rows === 1 ) {
			// Clear cache for this campaign
			$this->cache->delete( "campaigns_{$campaign_id}" );
			$this->cache->delete( "campaigns_{$campaign_id}_with_trashed" );
			$this->cache->delete_group( 'campaigns' );
			return true;
		}

		return false; // Status mismatch or campaign not found
	}

	/**
	 * Get active campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $options    Query options.
	 * @return   array               Array of active campaigns.
	 */
	public function get_active( array $options = array() ): array {
		// Use standardized cache key
		$cache_key = $this->get_cache_key( 'active', $options );

		$campaigns = $this->cache->remember(
			$cache_key,
			function () use ( $options ) {
				// Use UTC time because starts_at and ends_at are stored in UTC
				$now = gmdate( 'Y-m-d H:i:s' );

				$query = "SELECT * FROM {$this->table_name}
                     WHERE status = 'active'
                     AND deleted_at IS NULL
                     AND (starts_at IS NULL OR starts_at <= %s)
                     AND (ends_at IS NULL OR ends_at >= %s)
                     ORDER BY priority DESC, created_at ASC";

				if ( ! empty( $options['limit'] ) ) {
					$query .= " LIMIT {$options['limit']}";
				}

				$results = $this->db->get_results(
					$this->db->prepare( $query, $now, $now )
				);

				if ( ! is_array( $results ) ) {
					return array();
				}

				$results = array_filter(
					$results,
					function ( $result ) {
						return $result !== null;
					}
				);

				return array_map( array( $this, 'hydrate' ), $results );
			},
			1800
		); // 30 minutes cache

		// CRITICAL SAFETY CHECK: Filter out campaigns that have expired since being cached.
		// This prevents stale cached campaigns from applying discounts after their end date.
		// Even if ActionScheduler fails to fire wsscd_deactivate_campaign, this ensures
		// expired campaigns never apply discounts.
		$campaigns = $this->filter_expired_campaigns( $campaigns );

		// Filter campaigns by user role eligibility.
		// This runs per-request (not cached) since user roles are user-specific.
		return $this->filter_by_user_eligibility( $campaigns );
	}

	/**
	 * Filter campaigns by current user's role eligibility.
	 *
	 * Runs after cache retrieval since user roles are user-specific.
	 * Campaigns with user_roles_mode='all' pass through unchanged.
	 *
	 * @since    1.3.0
	 * @access   private
	 * @param    array $campaigns    Array of campaign objects.
	 * @return   array                  Filtered campaigns (only user-eligible ones).
	 */
	private function filter_by_user_eligibility( array $campaigns ): array {
		if ( empty( $campaigns ) ) {
			return array();
		}

		return array_filter(
			$campaigns,
			function ( $campaign ) {
				return $campaign->is_user_eligible();
			}
		);
	}

	/**
	 * Filter out campaigns based on current time (expired or not yet started).
	 *
	 * This is a critical safety check that runs AFTER cache retrieval to ensure
	 * stale cached campaigns don't apply discounts outside their valid date range.
	 * Even if ActionScheduler fails to fire status change events, this ensures
	 * campaigns only apply discounts within their scheduled window.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Array of campaign objects.
	 * @return   array                  Filtered campaigns (only truly active ones).
	 */
	private function filter_expired_campaigns( array $campaigns ): array {
		if ( empty( $campaigns ) ) {
			return array();
		}

		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		return array_filter(
			$campaigns,
			function ( $campaign ) use ( $now ) {
				// Check if campaign has ended
				$ends_at = $campaign->get_ends_at();
				if ( null !== $ends_at && $ends_at <= $now ) {
					return false; // Campaign has expired
				}

				// Check if campaign hasn't started yet
				$starts_at = $campaign->get_starts_at();
				if ( null !== $starts_at && $starts_at > $now ) {
					return false; // Campaign hasn't started
				}

				return true; // Campaign is within valid date range
			}
		);
	}

	/**
	 * Get scheduled campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Array of scheduled campaigns.
	 */
	public function get_scheduled(): array {
		// Use proper cache key with campaigns_ prefix
		$cache_key = 'campaigns_scheduled';

		return $this->cache->remember(
			$cache_key,
			function () {
				$now = gmdate( 'Y-m-d H:i:s' );

				$query = "SELECT * FROM {$this->table_name}
                     WHERE (status = 'scheduled' OR (status = 'active' AND starts_at > %s))
                     AND deleted_at IS NULL
                     ORDER BY starts_at ASC";

				$results = $this->db->get_results(
					$this->db->prepare( $query, $now )
				);

				if ( ! is_array( $results ) ) {
					return array();
				}

				$results = array_filter(
					$results,
					function ( $result ) {
						return $result !== null;
					}
				);

				return array_map( array( $this, 'hydrate' ), $results );
			},
			1800
		);
	}

	/**
	 * Get expired campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Array of expired campaigns.
	 */
	public function get_expired(): array {
		$now = gmdate( 'Y-m-d H:i:s' );

		$query = "SELECT * FROM {$this->table_name}
                 WHERE (status = 'expired' OR (ends_at IS NOT NULL AND ends_at < %s))
                 AND deleted_at IS NULL
                 ORDER BY ends_at DESC";

		$results = $this->db->get_results(
			$this->db->prepare( $query, $now )
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$results = array_filter(
			$results,
			function ( $result ) {
				return $result !== null;
			}
		);

		return array_map( array( $this, 'hydrate' ), $results );
	}

	/**
	 * Get paused campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Array of paused campaigns.
	 */
	public function get_paused(): array {
		// Use proper cache key with campaigns_ prefix
		$cache_key = 'campaigns_paused';

		return $this->cache->remember(
			$cache_key,
			function () {
				$query = "SELECT * FROM {$this->table_name}
                     WHERE status = 'paused'
                     AND deleted_at IS NULL
                     ORDER BY updated_at DESC";

				$results = $this->db->get_results( $query );

				if ( ! is_array( $results ) ) {
					return array();
				}

				$results = array_filter(
					$results,
					function ( $result ) {
						return $result !== null;
					}
				);

				return array_map( array( $this, 'hydrate' ), $results );
			},
			1800
		); // 30 minutes cache
	}

	/**
	 * Get campaigns by user.
	 *
	 * @since    1.0.0
	 * @param    int   $user_id    User ID.
	 * @param    array $options    Query options.
	 * @return   array               Array of campaigns.
	 */
	public function get_by_user( int $user_id, array $options = array() ): array {
		$criteria = array( 'created_by' => $user_id );
		return $this->find_by( $criteria, $options );
	}

	/**
	 * Save campaign.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign to save.
	 * @return   bool                         True on success, false on failure.
	 */
	public function save( WSSCD_Campaign $campaign ): bool {
		if ( ! $campaign->is_valid() ) {
			return false;
		}

		// Extract conditions before saving - they go to a separate table
		$conditions = $campaign->get_conditions();

		// Wrap save operation in transaction for data integrity
		$result = $this->db->transaction(
			function () use ( $campaign, $conditions ) {

				// Declare variables at function scope
				$expected_version = null;
				$current_user_id  = get_current_user_id();

				if ( $campaign->get_id() ) {
					$existing = $this->find( $campaign->get_id() );
					if ( ! $existing ) {
						return false;
					}

					// Allow updates if:
					// 1. User is the creator
					// 2. User is an admin (has manage_options capability)
					// 3. User is 0 (system operation like cron, which needs to activate/deactivate campaigns)
					if ( $current_user_id !== 0 && $existing->get_created_by() !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
						return false;
					}

					// Optimistic locking: Store expected version from database
					$expected_version = $existing->get_version();

					// CRITICAL FIX: Sync the campaign's version with database BEFORE dehydration
					// This ensures dehydrate() captures the correct current version
					$campaign->set_version( $expected_version );
				}

				// Now dehydrate with correct version
				$data = $this->dehydrate( $campaign );

				if ( $campaign->get_id() ) {
					// Update existing campaign

					unset( $data['created_at'] ); // Don't update created_at
					$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

					// Increment version for optimistic locking
					$campaign->increment_version();
					$data['version'] = $campaign->get_version();

					// Respect updated_by if explicitly set to NULL (system action)
					// Otherwise set to current user (or NULL if cron with user_id = 0)
					if ( ! array_key_exists( 'updated_by', $data ) || null !== $data['updated_by'] ) {
						$data['updated_by'] = $current_user_id === 0 ? null : $current_user_id;
					}
					// If $data['updated_by'] is NULL from campaign object, it stays NULL

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

					if ( $result === 0 ) {
						// wpdb->update() returns 0 for both "no match" and "no changes"
						// Check if row exists with expected version to distinguish
						$current_version = $this->db->get_var(
							$this->db->prepare(
								"SELECT version FROM {$this->table_name} WHERE id = %d",
								$campaign->get_id()
							)
						);

						if ( null === $current_version ) {
							// Row doesn't exist - deleted by another process
							// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
							throw new WSSCD_Concurrent_Modification_Exception(
								absint( $campaign->get_id() ),
								absint( $expected_version ),
								null  // Unknown current version
							);
							// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
						} elseif ( (int) $current_version !== $expected_version ) {
							// Version mismatch - modified by another process
							// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
							throw new WSSCD_Concurrent_Modification_Exception(
								absint( $campaign->get_id() ),
								absint( $expected_version ),
								absint( $current_version )
							);
							// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
						}
						// Row exists with correct version but data unchanged - this is OK
					}
				} else {
					$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
					$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
					$data['version']    = 1;  // Initial version

					$result = $this->db->insert(
						'campaigns',
						$data,
						$this->get_data_format( $data )
					);

					if ( $result ) {
						$campaign->set_id( $result );
					}
				}

				if ( $result !== false ) {
					$this->clear_campaign_cache( $campaign );
					return true;
				}

				return false;
			}
		);

		// Transaction returns false on failure, result on success
		if ( $result === false ) {
			return false;
		}

		// Save conditions to separate table
		if ( $result && $campaign->get_id() && ! empty( $conditions ) ) {
			$conditions_repo = $this->get_conditions_repository();
			if ( $conditions_repo ) {
				$conditions_repo->save_conditions( $campaign->get_id(), $conditions );
			}
		}

		// Save recurring configuration if enabled; remove recurring row when disabled.
		if ( $result && $campaign->get_id() ) {
			if ( ! empty( $campaign->get_enable_recurring() ) ) {
				$recurring_config = $campaign->get_recurring_config();
				if ( ! empty( $recurring_config ) && is_array( $recurring_config ) ) {
					$this->save_recurring_config( $campaign->get_id(), $recurring_config );

					// Trigger recurring handler to generate occurrence cache
					do_action( 'wsscd_campaign_saved', $campaign->get_id(), $campaign->to_array() );
				}
			} else {
				$this->delete_recurring_config( $campaign->get_id() );
			}
		}

		return (bool) $result;
	}

	/**
	 * Save recurring campaign configuration.
	 *
	 * @since  1.1.0
	 * @param  int   $campaign_id      Campaign ID.
	 * @param  array $recurring_config Recurring configuration.
	 * @return bool  Success.
	 */
	private function save_recurring_config( int $campaign_id, array $recurring_config ): bool {
		global $wpdb;

		// Delete existing recurring config first (in case of update)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Delete from plugin's custom table before insert.
		$wpdb->delete(
			$wpdb->prefix . 'wsscd_campaign_recurring',
			array(
				'campaign_id'        => $campaign_id,
				'parent_campaign_id' => 0, // This IS the parent
			),
			array( '%d', '%d' )
		);

		// recurrence_days: store as JSON string when array (weekly pattern), else string
		$recurrence_days = $recurring_config['recurrence_days'] ?? '';
		if ( is_array( $recurrence_days ) ) {
			$recurrence_days = wp_json_encode( array_values( $recurrence_days ) );
		}

		// Prepare data for insertion
		$recurring_data = array(
			'campaign_id'         => $campaign_id,
			'parent_campaign_id'  => 0, // This IS the parent
			'recurrence_pattern'  => $recurring_config['recurrence_pattern'] ?? 'daily',
			'recurrence_interval' => isset( $recurring_config['recurrence_interval'] ) ? (int) $recurring_config['recurrence_interval'] : 1,
			'recurrence_days'     => $recurrence_days,
			'recurrence_end_type' => $recurring_config['recurrence_end_type'] ?? 'never',
			'recurrence_count'    => isset( $recurring_config['recurrence_count'] ) ? (int) $recurring_config['recurrence_count'] : null,
			'recurrence_end_date' => $recurring_config['recurrence_end_date'] ?? null,
			'recurrence_mode'     => $recurring_config['recurrence_mode'] ?? 'continuous',
			'is_active'           => 1,
			'created_at'          => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Insert into plugin's custom table.
		$result = $wpdb->insert(
			$wpdb->prefix . 'wsscd_campaign_recurring',
			$recurring_data,
			array(
				'%d', // campaign_id
				'%d', // parent_campaign_id
				'%s', // recurrence_pattern
				'%d', // recurrence_interval
				'%s', // recurrence_days
				'%s', // recurrence_end_type
				'%d', // recurrence_count
				'%s', // recurrence_end_date
				'%s', // recurrence_mode
				'%d', // is_active
				'%s', // created_at
			)
		);

		if ( false === $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Remove recurring configuration for a campaign (when recurring is disabled).
	 *
	 * @since  1.0.0
	 * @param  int $campaign_id Campaign ID.
	 * @return void
	 */
	private function delete_recurring_config( int $campaign_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Delete from plugin's custom table.
		$wpdb->delete(
			$wpdb->prefix . 'wsscd_campaign_recurring',
			array(
				'campaign_id'        => $campaign_id,
				'parent_campaign_id' => 0,
			),
			array( '%d', '%d' )
		);

		// Reset campaign_type so the campaign is no longer treated as recurring (otherwise it would be filtered out of active campaigns).
		$table = $wpdb->prefix . 'wsscd_campaigns';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from trusted prefix; reset type after removing recurring.
		$wpdb->update(
			$table,
			array( 'campaign_type' => 'standard' ),
			array( 'id' => $campaign_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete campaign (soft delete).
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool          True on success, false on failure.
	 */
	public function delete( $id ) {
		// Verify ownership before deletion
		$campaign = $this->find( $id );
		if ( ! $campaign ) {
			return false;
		}

		$current_user_id = get_current_user_id();
		if ( $campaign->get_created_by() !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$result = $this->db->update(
			'campaigns',
			array(
				'deleted_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_by' => $current_user_id,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			$this->clear_campaign_cache_by_id( $id );
			return true;
		}

		return false;
	}

	/**
	 * Permanently delete campaign.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool          True on success, false on failure.
	 */
	public function force_delete( int $id ): bool {
		// Use 'campaigns' table identifier, not full table name
		$result = $this->db->delete(
			'campaigns',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false !== $result && 0 < $result ) {
			$this->clear_campaign_cache_by_id( $id );
			return true;
		}

		return false;
	}

	/**
	 * Restore deleted campaign.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool          True on success, false on failure.
	 */
	public function restore( int $id ): bool {
		$result = $this->db->update(
			'campaigns',
			array(
				'deleted_at' => null,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			$this->clear_campaign_cache_by_id( $id );
			return true;
		}

		return false;
	}

	/**
	 * Find campaigns in trash.
	 *
	 * WordPress-style trash system: Get all campaigns that have been soft deleted.
	 *
	 * @since    1.0.0
	 * @param    array $options    Query options (order_by, order_direction, limit, offset).
	 * @return   array               Array of trashed campaigns.
	 */
	public function find_trashed( array $options = array() ): array {
		// Default to ORDER BY deleted_at DESC if no order specified
		if ( empty( $options['order_by'] ) && empty( $options['orderby'] ) ) {
			$options['order_by']        = 'deleted_at';
			$options['order_direction'] = 'DESC';
		}

		$order_by = $this->build_order_by_clause( $options );
		$limit    = $this->build_limit_clause( $options );

		$query = "SELECT * FROM {$this->table_name} WHERE deleted_at IS NOT NULL $order_by $limit";

		return $this->execute_and_hydrate( $query );
	}

	/**
	 * Count campaigns in trash.
	 *
	 * @since    1.0.0
	 * @return   int    Number of campaigns in trash.
	 */
	public function count_trashed(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE deleted_at IS NOT NULL";
		return (int) $this->db->get_var( $query );
	}

	/**
	 * Find campaigns in trash older than specified days.
	 *
	 * Used by auto-purge functionality to find campaigns
	 * that have been in trash longer than the retention period.
	 *
	 * @since    1.0.0
	 * @param    int $days    Number of days threshold.
	 * @return   array          Array of campaign IDs older than threshold.
	 */
	public function find_trashed_older_than( int $days ): array {
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$query = $this->db->prepare(
			"SELECT id FROM {$this->table_name} WHERE deleted_at IS NOT NULL AND deleted_at < %s",
			$cutoff_date
		);

		$results = $this->db->get_col( $query );

		return array_map( 'intval', $results );
	}

	/**
	 * Find campaigns by status.
	 *
	 * @since    1.0.0
	 * @param    string $status     Campaign status.
	 * @param    array  $options    Query options.
	 * @return   array                Array of campaigns with given status.
	 */
	public function find_by_status( string $status, array $options = array() ): array {
		$where_clauses = array(
			'deleted_at IS NULL',
			'status = %s',
		);
		$where_values  = array( $status );

		$order_by = $this->build_order_by_clause( $options );
		$limit    = $this->build_limit_clause( $options );

		$where_clause = implode( ' AND ', $where_clauses );
		$query        = "SELECT * FROM {$this->table_name} WHERE $where_clause $order_by $limit";

		return $this->execute_and_hydrate( $query, $where_values );
	}

	/**
	 * Find expired campaigns.
	 *
	 * Get campaigns that have been completed/expired before the cutoff date.
	 * Used by scheduler for automatic archiving.
	 *
	 * @since    1.0.0
	 * @param    string $cutoff_date    Cutoff date (Y-m-d H:i:s format).
	 * @return   array                    Array of expired campaigns.
	 */
	public function find_expired( string $cutoff_date ): array {
		$query = "SELECT * FROM {$this->table_name}
                 WHERE status = 'completed'
                 AND deleted_at IS NULL
                 AND completed_at IS NOT NULL
                 AND completed_at < %s
                 ORDER BY completed_at ASC";

		$results = $this->db->get_results(
			$this->db->prepare( $query, $cutoff_date )
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$results = array_filter(
			$results,
			function ( $result ) {
				return null !== $result;
			}
		);

		return array_map( array( $this, 'hydrate' ), $results );
	}

	/**
	 * Count campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $criteria    Search criteria.
	 * @return   int                   Campaign count.
	 */
	public function count( array $criteria = array() ) {
		// Handle search separately if provided
		if ( isset( $criteria['search'] ) && ! empty( $criteria['search'] ) ) {
			return $this->count_search_results( $criteria );
		}

		// Transform campaign_type to enable_recurring
		if ( isset( $criteria['campaign_type'] ) && ! empty( $criteria['campaign_type'] ) ) {
			if ( 'recurring' === $criteria['campaign_type'] ) {
				$criteria['enable_recurring'] = 1;
			} elseif ( 'standard' === $criteria['campaign_type'] ) {
				$criteria['enable_recurring'] = 0;
			}
			unset( $criteria['campaign_type'] );
		}

		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		// Only process valid database fields
		$valid_fields = array( 'status', 'created_by', 'discount_type', 'enable_recurring' );

		foreach ( $criteria as $field => $value ) {
			// Skip empty values and non-database fields
			if ( empty( $value ) || ! in_array( $field, $valid_fields ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$placeholders    = implode( ',', array_fill( 0, count( $value ), '%s' ) );
				$where_clauses[] = "$field IN ($placeholders)";
				$where_values    = array_merge( $where_values, $value );
			} else {
				$where_clauses[] = "$field = %s";
				$where_values[]  = $value;
			}
		}

		$where_clause = implode( ' AND ', $where_clauses );

		// SECURITY: Always use $wpdb->prepare() to satisfy WordPress.org review requirements.
		if ( ! empty( $where_values ) ) {
			$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause";
			$query = $this->db->prepare( $query, ...$where_values );
		} else {
			// No filter values - add dummy condition for prepare() compatibility.
			$query = $this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause AND %d = %d",
				1,
				1
			);
		}

		return (int) $this->db->get_var( $query );
	}

	/**
	 * Check if slug exists.
	 *
	 * @since    1.0.0
	 * @param    string   $slug    Campaign slug.
	 * @param    int|null $exclude_id    Campaign ID to exclude.
	 * @return   bool                     True if slug exists.
	 */
	public function slug_exists( string $slug, ?int $exclude_id = null ): bool {
		// because the database UNIQUE constraint applies to all rows
		$query  = "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s";
		$params = array( $slug );

		if ( $exclude_id ) {
			$query   .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		$count = (int) $this->db->get_var(
			$this->db->prepare( $query, ...$params )
		);

		return $count > 0;
	}

	/**
	 * Get unique slug.
	 *
	 * @since    1.0.0
	 * @param    string   $slug       Base slug.
	 * @param    int|null $exclude_id Campaign ID to exclude.
	 * @return   string               Unique slug.
	 */
	public function get_unique_slug( string $slug, ?int $exclude_id = null ): string {
		// If slug doesn't exist, return it as-is
		if ( ! $this->slug_exists( $slug, $exclude_id ) ) {
			return $slug;
		}

		$base_slug = preg_replace( '/-(\d+)$/', '', $slug );
		$counter   = 2;

		// Keep trying until we find a unique slug
		while ( $counter <= 100 ) {
			$new_slug = $base_slug . '-' . $counter;
			if ( ! $this->slug_exists( $new_slug, $exclude_id ) ) {
				return $new_slug;
			}
			++$counter;
		}

		// Fallback: append timestamp if we can't find unique slug after 100 attempts
		return $base_slug . '-' . time();
	}

	/**
	 * Save campaign with products.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_data    Campaign data including products.
	 * @return   WSSCD_Campaign|false         Saved campaign or false on failure.
	 */
	public function save_campaign_with_products( array $campaign_data ) {
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

		}

		$campaign = new WSSCD_Campaign( $campaign_data );

		if ( ! $campaign->is_valid() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$validation_errors = $campaign->validate();

			}
			return false;
		}

		if ( $this->save( $campaign ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

			}
			return $campaign;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

		}
		return false;
	}

	/**
	 * Get active campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Array of active campaigns.
	 */
	public function get_active_campaigns(): array {
		return $this->get_active();
	}

	/**
	 * Get campaigns by product.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array                 Array of campaigns affecting this product.
	 */
	public function get_campaigns_by_product( int $product_id ): array {
		$cache_key = "campaigns_by_product_{$product_id}";

		return $this->cache->remember(
			$cache_key,
			function () use ( $product_id ) {
				$active_campaigns = $this->get_active_campaigns();

				$applicable_campaigns = array();
				foreach ( $active_campaigns as $campaign ) {
					if ( $campaign->can_apply_to_product( $product_id ) ) {
						$applicable_campaigns[] = $campaign;
					}
				}

				return $applicable_campaigns;
			},
			1800
		);
	}

	/**
	 * Update campaign status.
	 *
	 * @since    1.0.0
	 * @param    int    $id        Campaign ID.
	 * @param    string $status    New status.
	 * @return   bool                 True on success, false on failure.
	 */
	public function update_campaign_status( int $id, string $status ): bool {
		$campaign = $this->find( $id );
		if ( ! $campaign ) {
			return false;
		}

		try {
			$campaign->set_status( $status );
			return $this->save( $campaign );
		} catch ( InvalidArgumentException $e ) {
			return false;
		}
	}

	/**
	 * Get campaigns by discount strategy.
	 *
	 * @since    1.0.0
	 * @param    string $strategy    Discount strategy.
	 * @return   array                  Array of campaigns using this strategy.
	 */
	public function get_campaigns_by_discount_type( string $strategy ): array {
		return $this->find_by(
			array(
				'discount_type' => $strategy,
				'status'        => 'active',
			)
		);
	}

	/**
	 * Get campaigns by product selection type.
	 *
	 * @since    1.0.0
	 * @param    string $type    Product selection type.
	 * @return   array              Array of campaigns using this selection type.
	 */
	public function get_campaigns_by_product_selection_type( string $type ): array {
		return $this->find_by(
			array(
				'product_selection_type' => $type,
				'status'                 => 'active',
			)
		);
	}

	/**
	 * Get campaigns with usage limits.
	 *
	 * @since    1.0.0
	 * @return   array    Array of campaigns with usage limits.
	 */
	public function get_campaigns_with_usage_limits(): array {
		$query = "SELECT * FROM {$this->table_name} 
                 WHERE status = 'active' 
                 AND (max_uses IS NOT NULL OR max_uses_per_customer IS NOT NULL)
                 AND deleted_at IS NULL
                 ORDER BY priority DESC";

		$results = $this->db->get_results( $query );

		if ( ! is_array( $results ) ) {
			return array();
		}

		$results = array_filter(
			$results,
			function ( $result ) {
				return $result !== null;
			}
		);

		return array_map( array( $this, 'hydrate' ), $results );
	}

	/**
	 * Get campaign performance metrics.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   array                  Performance metrics.
	 */
	public function get_campaign_performance( int $campaign_id ): array {
		$campaign = $this->find( $campaign_id );
		if ( ! $campaign ) {
			return array();
		}

		// Note: Performance metrics are managed by the Analytics service,
		// not the Campaign entity. The Campaign model is a simplified domain
		// object that doesn't track performance data directly.
		//
		// To get real performance data, use the Analytics repository or service.
		// This method returns placeholder data for backward compatibility.
		return array(
			'revenue_generated' => 0,
			'orders_count'      => 0,
			'conversion_rate'   => 0,
			'current_uses'      => 0,
			'products_count'    => count( $campaign->get_product_ids() ),
		);
	}

	/**
	 * Increment campaign usage.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success, false on failure.
	 */
	public function increment_campaign_usage( int $campaign_id ): bool {
		$query = "UPDATE {$this->table_name} 
                 SET current_uses = current_uses + 1, 
                     updated_at = %s 
                 WHERE id = %d";

		$result = $this->db->query(
			$this->db->prepare( $query, gmdate( 'Y-m-d H:i:s' ), $campaign_id )
		);

		if ( $result !== false ) {
			$this->clear_campaign_cache_by_id( $campaign_id );
			return true;
		}

		return false;
	}

	/**
	 * Get campaigns by priority range.
	 *
	 * @since    1.0.0
	 * @param    int $min_priority    Minimum priority.
	 * @param    int $max_priority    Maximum priority.
	 * @return   array                   Array of campaigns in priority range.
	 */
	public function get_campaigns_by_priority_range( int $min_priority, int $max_priority ): array {
		$query = "SELECT * FROM {$this->table_name}
                 WHERE priority BETWEEN %d AND %d
                 AND status = 'active'
                 AND deleted_at IS NULL
                 ORDER BY priority DESC, created_at ASC";

		$results = $this->db->get_results(
			$this->db->prepare( $query, $min_priority, $max_priority )
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$results = array_filter(
			$results,
			function ( $result ) {
				return $result !== null;
			}
		);

		return array_map( array( $this, 'hydrate' ), $results );
	}

	/**
	 * Get conflicting campaigns.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign to check conflicts for.
	 * @return   array                        Array of conflicting campaigns.
	 */
	public function get_conflicting_campaigns( WSSCD_Campaign $campaign ): array {
		$conflicts = array();

		if ( $campaign->get_starts_at() && $campaign->get_ends_at() ) {
			$query = "SELECT * FROM {$this->table_name} 
                     WHERE status IN ('active', 'scheduled')
                     AND deleted_at IS NULL
                     AND id != %d
                     AND (
                         (starts_at <= %s AND ends_at >= %s) OR
                         (starts_at <= %s AND ends_at >= %s) OR
                         (starts_at >= %s AND ends_at <= %s)
                     )";

			$start       = $campaign->get_starts_at()->format( 'Y-m-d H:i:s' );
			$end         = $campaign->get_ends_at()->format( 'Y-m-d H:i:s' );
			$campaign_id = $campaign->get_id() ?: 0;

			$results = $this->db->get_results(
				$this->db->prepare( $query, $campaign_id, $start, $start, $end, $end, $start, $end )
			);

			if ( ! is_array( $results ) ) {
				$conflicts = array();
			} else {
				$results   = array_filter(
					$results,
					function ( $result ) {
						return $result !== null;
					}
				);
				$conflicts = array_map( array( $this, 'hydrate' ), $results );
			}
		}

		return $conflicts;
	}

	/**
	 * Hydrate campaign from database data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $data    Database row data.
	 * @return   WSSCD_Campaign       Campaign instance.
	 */
	private function hydrate( object $data ): WSSCD_Campaign {
		// Only hydrate fields that exist in the Campaign entity
		$campaign_data = array(
			'id'                     => (int) $data->id,
			'uuid'                   => $data->uuid,
			'name'                   => $data->name,
			'slug'                   => $data->slug,
			'description'            => $data->description,
			'status'                 => $data->status,
			'priority'               => (int) $data->priority,
			'version'                => (int) $data->version,
			'settings'               => json_decode( $data->settings ?: '{}', true ),
			'metadata'               => json_decode( $data->metadata ?: '{}', true ),
			'template_id'            => $data->template_id,
			'created_by'             => (int) $data->created_by,
			'updated_by'             => $data->updated_by ? (int) $data->updated_by : null,

			// Product Selection
			'product_selection_type' => $data->product_selection_type ?? 'all_products',
			'product_ids'            => isset( $data->product_ids ) ? json_decode( $data->product_ids ?: '[]', true ) : array(),
			'category_ids'           => isset( $data->category_ids ) ? json_decode( $data->category_ids ?: '[]', true ) : array(),
			'tag_ids'                => isset( $data->tag_ids ) ? json_decode( $data->tag_ids ?: '[]', true ) : array(),

			// Conditions (logic stored in main table, conditions in separate table)
			'conditions_logic'       => $data->conditions_logic ?? 'all',

			// Discount Configuration
			'discount_type'          => $data->discount_type ?? 'percentage',
			'discount_value'         => (float) ( $data->discount_value ?? 0.0 ),
			'discount_rules'         => isset( $data->discount_rules ) ? json_decode( $data->discount_rules ?: '[]', true ) : array(),

			// Free Shipping Configuration
			'free_shipping_config'   => isset( $data->free_shipping_config ) ? json_decode( $data->free_shipping_config ?: '{}', true ) : array(),

			// User Role Targeting
			'user_roles'             => isset( $data->user_roles ) ? json_decode( $data->user_roles ?: '[]', true ) : array(),
			'user_roles_mode'        => $data->user_roles_mode ?? 'all',

			// Scheduling
			'starts_at'              => $data->starts_at,
			'ends_at'                => $data->ends_at,
			'timezone'               => $data->timezone,

			// Timestamps
			'created_at'             => $data->created_at,
			'updated_at'             => $data->updated_at,
			'deleted_at'             => $data->deleted_at,
		);

		// Load conditions from separate table
		$conditions_repo = $this->get_conditions_repository();
		if ( $conditions_repo ) {
			$campaign_data['conditions'] = $conditions_repo->get_conditions_for_campaign( (int) $data->id );
		} else {
			$campaign_data['conditions'] = array();
		}

		// Load recurring configuration if present (from JOIN with campaign_recurring table)
		$campaign_data['enable_recurring'] = ! empty( $data->enable_recurring );
		if ( ! empty( $data->recurrence_pattern ) ) {
			$campaign_data['recurring_config'] = array(
				'recurrence_pattern'  => $data->recurrence_pattern,
				'recurrence_interval' => isset( $data->recurrence_interval ) ? (int) $data->recurrence_interval : 1,
				'recurrence_days'     => $data->recurrence_days ?? '',
				'recurrence_end_type' => $data->recurrence_end_type ?? 'never',
				'recurrence_count'    => isset( $data->recurrence_count ) ? (int) $data->recurrence_count : null,
				'recurrence_end_date' => $data->recurrence_end_date ?? null,
				'recurrence_mode'     => $data->recurrence_mode ?? 'continuous',
			);
		}

		// Extract badge configuration from discount_rules and set on entity
		// Badge data is stored in discount_rules.badge by the compiler
		if ( isset( $campaign_data['discount_rules']['badge'] ) && is_array( $campaign_data['discount_rules']['badge'] ) ) {
			$badge_config = $campaign_data['discount_rules']['badge'];

			// Set badge properties directly on the campaign data
			$campaign_data['badge_enabled']    = $badge_config['enabled'] ?? false;
			$campaign_data['badge_text']       = $badge_config['text'] ?? 'auto';
			$campaign_data['badge_bg_color']   = $badge_config['bg_color'] ?? '#ff0000';
			$campaign_data['badge_text_color'] = $badge_config['text_color'] ?? '#ffffff';
			$campaign_data['badge_position']   = $badge_config['position'] ?? 'top-right';
		}

		// Note: Database has additional fields (color_theme, icon, rotation_*,
		// performance metrics, etc.) that are not in the simplified Campaign entity.
		// These are stored in settings/metadata or tracked by separate services.

		return new WSSCD_Campaign( $campaign_data );
	}

	/**
	 * Dehydrate campaign to database format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WSSCD_Campaign $campaign    Campaign instance.
	 * @return   array                        Database data.
	 */
	private function dehydrate( WSSCD_Campaign $campaign ): array {
		$data = $campaign->to_array();

		// Remove fields that are not columns in the main campaigns table
		unset( $data['selected_products'], $data['selected_categories'], $data['selected_tags'] );

		// Conditions are stored in a separate table, not in the campaigns table
		unset( $data['conditions'] );

		// Recurring config is stored in a separate table (campaign_recurring), not in campaigns table
		unset( $data['recurring_config'] );

		// Ensure tinyint(1) NOT NULL columns get integer 0/1 (MySQL strict mode rejects boolean/empty string)
		if ( array_key_exists( 'enable_recurring', $data ) ) {
			$data['enable_recurring'] = $data['enable_recurring'] ? 1 : 0;
		}

		$json_fields = array(
			'settings',
			'metadata',
			'product_ids',
			'category_ids',
			'tag_ids',
			'discount_rules',
			'free_shipping_config',
			'user_roles',
			'usage_limits',
			'discount_configuration',
			'schedule_configuration',
		);

		foreach ( $json_fields as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$encoded = wp_json_encode( $data[ $field ] );

				// Verify JSON encoding succeeded
				if ( false === $encoded || null === $encoded ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
					throw new RuntimeException(
						sprintf(
							'Failed to encode %s data for database storage. Data may contain invalid UTF-8 sequences.',
							esc_html( $field )
						)
					);
				}

				$data[ $field ] = $encoded;
			}
		}

		// Remove ID for new campaigns
		if ( ! $campaign->get_id() ) {
			unset( $data['id'] );
		}

		// Only persist columns that exist in the table (defensive: handles missing migrations).
		$existing_columns = $this->db->get_columns( $this->table_name );
		if ( ! empty( $existing_columns ) ) {
			$data = array_intersect_key( $data, array_flip( $existing_columns ) );
		}

		return $data;
	}

	/**
	 * Get data format for database operations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Data array.
	 * @return   array            Format array.
	 */
	private function get_data_format( array $data ): array {
		$format = array();

		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'id':
				case 'priority':
				case 'created_by':
				case 'updated_by':
				case 'products_count':
				case 'orders_count':
				case 'impressions_count':
				case 'clicks_count':
				case 'enable_recurring':
					$format[] = '%d';
					break;
				case 'revenue_generated':
				case 'conversion_rate':
					$format[] = '%f';
					break;
				default:
					$format[] = '%s';
					break;
			}
		}

		return $format;
	}

	/**
	 * Clear campaign cache (delegates to invalidate_campaign_cache).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WSSCD_Campaign $campaign    Campaign instance.
	 * @return   void
	 */
	private function clear_campaign_cache( WSSCD_Campaign $campaign ): void {
		$this->invalidate_campaign_cache( $campaign );

		// Also clear legacy wp_cache entries (WordPress object cache)
		wp_cache_delete( 'campaigns_active', 'wsscd' );
		wp_cache_delete( 'campaigns_scheduled', 'wsscd' );
		wp_cache_delete( 'campaigns_paused', 'wsscd' );

		// Clear all active campaign transients (with new naming pattern)
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient deletion for cache invalidation; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s',
				$wpdb->options,
				'_transient_wsscd_v1_campaigns_active_%',
				'_transient_timeout_wsscd_v1_campaigns_active_%'
			)
		);

		// Clear product-specific caches (with proper products_ prefix)
		$product_ids = $campaign->get_product_ids();
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				// Clear product-specific caches with proper prefix
				wp_cache_delete( 'products_campaigns_by_' . $product_id, 'wsscd' );
				wp_cache_delete( 'products_active_campaigns_' . $product_id, 'wsscd' );

				// Delete product-specific transients
				delete_transient( 'wsscd_v1_products_campaigns_by_' . $product_id );
				delete_transient( 'wsscd_v1_products_active_campaigns_' . $product_id );

				// Clear WooCommerce product transients to force price recalculation
				if ( function_exists( 'wc_delete_product_transients' ) ) {
					wc_delete_product_transients( $product_id );
				}
			}
		}

		// Bulk clear all product-specific transients (for condition-based campaigns)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient deletion for cache invalidation; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s',
				$wpdb->options,
				'_transient_wsscd_v1_products_%',
				'_transient_timeout_wsscd_v1_products_%'
			)
		);
	}

	/**
	 * Find all campaigns (alias for find_by).
	 *
	 * @since    1.0.0
	 * @param    array $args    Query arguments.
	 * @return   array             Array of campaigns.
	 */
	public function find_all( array $args = array() ) {
		// Handle search separately if provided
		if ( isset( $args['search'] ) && ! empty( $args['search'] ) ) {
			return $this->search_campaigns( $args );
		}

		$criteria = array();
		$options  = array();

		if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
			$criteria['status'] = $args['status'];
		}

		if ( isset( $args['campaign_type'] ) && ! empty( $args['campaign_type'] ) ) {
			if ( 'recurring' === $args['campaign_type'] ) {
				$criteria['enable_recurring'] = 1;
			} elseif ( 'standard' === $args['campaign_type'] ) {
				$criteria['enable_recurring'] = 0;
			}
		}

		if ( isset( $args['orderby'] ) ) {
			$options['order_by']        = $args['orderby'];
			$options['order_direction'] = $args['order'] ?? 'ASC';
		}

		if ( isset( $args['limit'] ) ) {
			$options['limit']  = $args['limit'];
			$options['offset'] = $args['offset'] ?? 0;
		}

		return $this->find_by( $criteria, $options );
	}

	/**
	 * Get status counts.
	 *
	 * @since    1.0.0
	 * @return   array    Status counts.
	 */
	public function get_status_counts(): array {
		$statuses = array( 'active', 'scheduled', 'expired', 'draft', 'paused' );
		$counts   = array();

		foreach ( $statuses as $status ) {
			$counts[ $status ] = $this->count( array( 'status' => $status ) );
		}

		$counts['total'] = $this->count();

		return $counts;
	}

	/**
	 * Get performance summary.
	 *
	 * @since    1.0.0
	 * @return   array    Performance summary.
	 */
	public function get_performance_summary(): array {
		// Mock implementation for now
		return array(
			'total_revenue'           => 0.0,
			'total_savings'           => 0.0,
			'total_orders'            => 0,
			'average_conversion_rate' => 0.0,
		);
	}


	/**
	 * Clear campaign cache by ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $id    Campaign ID.
	 * @return   void
	 */
	private function clear_campaign_cache_by_id( int $id ): void {
		$campaign = $this->find( $id );
		if ( $campaign ) {
			$this->clear_campaign_cache( $campaign );
		}
	}

	/**
	 * Search campaigns by name or description.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $args    Search arguments.
	 * @return   array             Array of campaigns matching search.
	 */
	private function search_campaigns( array $args ): array {
		global $wpdb;
		$search_term   = $wpdb->esc_like( $args['search'] );
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
		$where_values[]  = '%' . $search_term . '%';
		$where_values[]  = '%' . $search_term . '%';

		if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $args['status'];
		}

		if ( isset( $args['campaign_type'] ) && ! empty( $args['campaign_type'] ) ) {
			if ( 'recurring' === $args['campaign_type'] ) {
				$where_clauses[] = 'enable_recurring = %d';
				$where_values[]  = 1;
			} elseif ( 'standard' === $args['campaign_type'] ) {
				$where_clauses[] = 'enable_recurring = %d';
				$where_values[]  = 0;
			}
		}

		$order_by = $this->build_order_by_clause( $args );
		$limit    = $this->build_limit_clause( $args );

		$where_clause = implode( ' AND ', $where_clauses );
		$query        = "SELECT * FROM {$this->table_name} WHERE $where_clause $order_by $limit";

		return $this->execute_and_hydrate( $query, $where_values );
	}

	/**
	 * Count search results.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $criteria    Search criteria including search term.
	 * @return   int                   Number of campaigns matching search.
	 */
	private function count_search_results( array $criteria ): int {
		global $wpdb;
		$search_term   = $wpdb->esc_like( $criteria['search'] );
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
		$where_values[]  = '%' . $search_term . '%';
		$where_values[]  = '%' . $search_term . '%';

		if ( isset( $criteria['status'] ) && ! empty( $criteria['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $criteria['status'];
		}

		if ( isset( $criteria['campaign_type'] ) && ! empty( $criteria['campaign_type'] ) ) {
			if ( 'recurring' === $criteria['campaign_type'] ) {
				$where_clauses[] = 'enable_recurring = %d';
				$where_values[]  = 1;
			} elseif ( 'standard' === $criteria['campaign_type'] ) {
				$where_clauses[] = 'enable_recurring = %d';
				$where_values[]  = 0;
			}
		}

		$where_clause = implode( ' AND ', $where_clauses );

		// SECURITY: Always use $wpdb->prepare() to satisfy WordPress.org review requirements.
		if ( ! empty( $where_values ) ) {
			$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause";
			$query = $this->db->prepare( $query, ...$where_values );
		} else {
			// No filter values - add dummy condition for prepare() compatibility.
			$query = $this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause AND %d = %d",
				1,
				1
			);
		}

		return (int) $this->db->get_var( $query );
	}

	/**
	 * Apply custom WHERE conditions for campaigns.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WSSCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	protected function apply_custom_where_conditions( $query_builder, $args ) {
		if ( ! empty( $args['name'] ) ) {
			$query_builder->where( 'name', 'LIKE', '%' . $args['name'] . '%' );
		}

		if ( ! empty( $args['product_selection_type'] ) ) {
			$query_builder->where( 'product_selection_type', '=', $args['product_selection_type'] );
		}

		if ( ! empty( $args['discount_type'] ) ) {
			$query_builder->where( 'discount_type', '=', $args['discount_type'] );
		}

		if ( ! empty( $args['priority_min'] ) ) {
			$query_builder->where( 'priority', '>=', $args['priority_min'] );
		}

		// Active campaigns only
		if ( ! empty( $args['active_only'] ) && $args['active_only'] ) {
			$now = gmdate( 'Y-m-d H:i:s' );
			$query_builder->where( 'status', '=', 'active' );
			$query_builder->where( 'starts_at', '<=', $now );
			$query_builder->where( 'ends_at', '>=', $now );
		}

		// Exclude deleted
		$query_builder->where( 'deleted_at', '=', null );
	}

	/**
	 * Prepare campaign data for database storage.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Campaign data.
	 * @return   array             Prepared data.
	 */
	protected function prepare_data_for_database( array $data ) {
		// Handle JSON encoding for specific fields
		foreach ( $this->json_fields as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		// Ensure slug is set
		if ( ! empty( $data['name'] ) && empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		$data['priority'] = $data['priority'] ?? 0;
		$data['status']   = $data['status'] ?? 'draft';

		return $data;
	}

	/**
	 * Prepare campaign data for output.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Raw database data.
	 * @return   array             Prepared data.
	 */
	protected function prepare_item_output( array $data ) {
		// The hydrate method already handles this
		return (array) $this->hydrate( (object) $data )->to_array();
	}

	/**
	 * Get entity name for error messages.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   string    Entity name.
	 */
	protected function get_entity_name() {
		return __( 'campaign', 'smart-cycle-discounts' );
	}

	/**
	 * Build ORDER BY clause from options.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $options    Query options (order_by/orderby and order_direction/order).
	 * @return   string               ORDER BY clause or empty string.
	 */
	private function build_order_by_clause( array $options ): string {
		if ( empty( $options['order_by'] ) && empty( $options['orderby'] ) ) {
			return '';
		}

		$order_by_field = $options['order_by'] ?? $options['orderby'] ?? '';

		// Whitelist allowed order by fields to prevent SQL injection
		$allowed_fields = array(
			'id',
			'name',
			'status',
			'priority',
			'created_at',
			'updated_at',
			'starts_at',
			'ends_at',
			'discount_type',
			'created_by',
		);

		if ( ! in_array( $order_by_field, $allowed_fields, true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for invalid order_by field, only in WP_DEBUG mode.
				error_log( '[WSSCD_Repository] Invalid order_by field: ' . $order_by_field );
			}
			return '';
		}

		$order_direction = strtoupper( $options['order_direction'] ?? $options['order'] ?? 'ASC' );

		// Validate order direction
		if ( ! in_array( $order_direction, array( 'ASC', 'DESC' ), true ) ) {
			$order_direction = 'ASC';
		}

		return "ORDER BY {$order_by_field} {$order_direction}";
	}

	/**
	 * Build LIMIT clause from options.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $options    Query options (limit and offset).
	 * @return   string               LIMIT clause or empty string.
	 */
	private function build_limit_clause( array $options ): string {
		// Set default and maximum limits to prevent memory exhaustion
		$default_limit = 100;
		$max_limit     = 1000;

		// Get limit from options or use default
		$limit = ! empty( $options['limit'] ) ? (int) $options['limit'] : 0;

		// If no limit specified and no_limit flag not set, use default
		if ( 0 === $limit && empty( $options['no_limit'] ) ) {
			$limit = $default_limit;
		}

		// If no limit wanted (via no_limit flag), return empty string
		if ( 0 === $limit ) {
			return '';
		}

		// Enforce maximum limit
		$limit = min( $limit, $max_limit );

		// Validate and sanitize offset
		$offset = isset( $options['offset'] ) ? max( 0, (int) $options['offset'] ) : 0;

		return "LIMIT {$offset}, {$limit}";
	}

	/**
	 * Execute query and hydrate results.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $query          SQL query.
	 * @param    array  $where_values   Values for prepared statement.
	 * @return   array                     Array of hydrated campaigns.
	 */
	private function execute_and_hydrate( string $query, array $where_values = array() ): array {
		// SECURITY: Always use $wpdb->prepare() to satisfy WordPress.org review requirements.
		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
		} else {
			// No filter values - add dummy condition for prepare() compatibility.
			// Must insert before ORDER BY, GROUP BY, LIMIT, or at end.
			$insert_before = array( 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'FOR UPDATE' );
			$insert_pos    = strlen( $query );

			foreach ( $insert_before as $clause ) {
				$pos = stripos( $query, $clause );
				if ( false !== $pos && $pos < $insert_pos ) {
					$insert_pos = $pos;
				}
			}

			$before_part = substr( $query, 0, $insert_pos );
			$after_part  = substr( $query, $insert_pos );

			if ( stripos( $before_part, 'WHERE' ) !== false ) {
				$query = $this->db->prepare( $before_part . ' AND %d = %d ' . $after_part, 1, 1 );
			} else {
				$query = $this->db->prepare( $before_part . ' WHERE %d = %d ' . $after_part, 1, 1 );
			}
		}

		$results = $this->db->get_results( $query );

		if ( ! is_array( $results ) ) {
			return array();
		}

		$results = array_filter(
			$results,
			function ( $result ) {
				return null !== $result;
			}
		);

		return array_map( array( $this, 'hydrate' ), $results );
	}

	/**
	 * Get conditions repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   WSSCD_Campaign_Conditions_Repository|null    Conditions repository or null.
	 */
	private function get_conditions_repository(): ?object {
		static $conditions_repo = null;

		if ( null === $conditions_repo ) {
			// Ensure the class file is loaded
			if ( ! class_exists( 'WSSCD_Campaign_Conditions_Repository' ) ) {
				$conditions_repo_file = WSSCD_INCLUDES_DIR . 'database/repositories/class-campaign-conditions-repository.php';
				if ( file_exists( $conditions_repo_file ) ) {
					require_once $conditions_repo_file;
				}
			}

			// Try to get from service container first
			if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
				try {
					$conditions_repo = Smart_Cycle_Discounts::get_service( 'campaign_conditions_repository' );
				} catch ( Exception $e ) {
					// Service not available, create new instance
					if ( class_exists( 'WSSCD_Campaign_Conditions_Repository' ) ) {
						$conditions_repo = new WSSCD_Campaign_Conditions_Repository( $this->db );
					}
				}
			} elseif ( class_exists( 'WSSCD_Campaign_Conditions_Repository' ) ) {
				// Fallback: create directly
				$conditions_repo = new WSSCD_Campaign_Conditions_Repository( $this->db );
			}
		}

		return $conditions_repo;
	}
}
