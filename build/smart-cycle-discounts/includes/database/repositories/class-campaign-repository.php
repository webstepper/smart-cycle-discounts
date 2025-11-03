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

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once SCD_INCLUDES_DIR . 'database/repositories/class-base-repository.php';

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
class SCD_Campaign_Repository extends SCD_Base_Repository {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private $db;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager    $cache    Cache manager.
	 */
	private $cache;

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $db       Database manager.
	 * @param    SCD_Cache_Manager    $cache    Cache manager.
	 */
	public function __construct( SCD_Database_Manager $db, SCD_Cache_Manager $cache ) {
		global $wpdb;
		$this->db          = $db;
		$this->cache       = $cache;
		$this->table_name  = $wpdb->prefix . 'scd_campaigns';
		$this->json_fields = array( 'conditions', 'category_ids', 'tag_ids', 'attributes', 'product_ids', 'variation_rules' );
		$this->date_fields = array( 'created_at', 'updated_at', 'starts_at', 'ends_at', 'deleted_at' );
	}

	/**
	 * Find campaign by ID.
	 *
	 * @since    1.0.0
	 * @param    int  $id                Campaign ID.
	 * @param    bool $include_trashed   Include trashed campaigns (for ownership checks).
	 * @return   SCD_Campaign|null         Campaign or null if not found.
	 */
	public function find( $id, $include_trashed = false ) {
		$cache_key = $include_trashed ? "campaign_{$id}_with_trashed" : "campaign_{$id}";

		return $this->cache->remember(
			$cache_key,
			function () use ( $id, $include_trashed ) {
				// Build query based on whether to include trashed campaigns
				if ( $include_trashed ) {
					$query = "SELECT * FROM {$this->table_name} WHERE id = %d";
				} else {
					$query = "SELECT * FROM {$this->table_name} WHERE id = %d AND deleted_at IS NULL";
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
	 * @return   SCD_Campaign|null   Campaign or null if not found or unauthorized.
	 */
	public function find_for_user( $id, $user_id ) {
		$campaign = $this->find( $id );

		if ( ! $campaign ) {
			return null;
		}

		// Check ownership
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
	 * @return   SCD_Campaign|null      Campaign or null if not found.
	 */
	public function find_by_uuid( string $uuid ): ?SCD_Campaign {
		$cache_key = "campaign_uuid_{$uuid}";

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
	 * @return   SCD_Campaign|null      Campaign or null if not found.
	 */
	public function find_by_slug( string $slug ): ?SCD_Campaign {
		$cache_key = "campaign_slug_{$slug}";

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

		// Sanitize meta_key to prevent SQL injection (only allow alphanumeric and underscores)
		$meta_key = preg_replace( '/[^a-zA-Z0-9_]/', '', $meta_key );
		if ( empty( $meta_key ) ) {
			return array();
		}

		$order_by = $this->build_order_by_clause( $options );
		$limit    = $this->build_limit_clause( $options );

		// Query using JSON_EXTRACT for metadata (MySQL 5.7+)
		// Meta key is sanitized above, meta value is prepared via %s placeholder
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->table_name}
			WHERE deleted_at IS NULL
			AND JSON_EXTRACT(metadata, '$.{$meta_key}') = %s
			{$order_by} {$limit}",
			$meta_value
		);

		return $this->execute_and_hydrate( $query, array() );
	}

	/**
	 * Get active campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $options    Query options.
	 * @return   array               Array of active campaigns.
	 */
	public function get_active( array $options = array() ): array {
		$cache_key = 'active_campaigns_' . md5( serialize( $options ) );

		return $this->cache->remember(
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

				// Filter out null results
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
	 * Get scheduled campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Array of scheduled campaigns.
	 */
	public function get_scheduled(): array {
		$cache_key = 'scheduled_campaigns';

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

				// Filter out null results
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

		// Filter out null results
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
		$cache_key = 'paused_campaigns';

		return $this->cache->remember(
			$cache_key,
			function () {
				$query = "SELECT * FROM {$this->table_name}
                     WHERE status = 'paused'
                     AND deleted_at IS NULL
                     ORDER BY updated_at DESC";

				$results = $this->db->get_results( $query );

				// Filter out null results
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
	 * @param    SCD_Campaign $campaign    Campaign to save.
	 * @return   bool                         True on success, false on failure.
	 */
	public function save( SCD_Campaign $campaign ): bool {
		if ( ! $campaign->is_valid() ) {
			return false;
		}

		// Wrap save operation in transaction for data integrity
		$result = $this->db->transaction(
			function () use ( $campaign ) {
				$data = $this->dehydrate( $campaign );

				if ( $campaign->get_id() ) {
					// Update existing campaign - verify ownership first
					$existing = $this->find( $campaign->get_id() );
					if ( ! $existing ) {
						return false;
					}

					// Check ownership - only creator, admin, or system operations can update
					$current_user_id = get_current_user_id();
					// Allow updates if:
					// 1. User is the creator
					// 2. User is an admin (has manage_options capability)
					// 3. User is 0 (system operation like cron, which needs to activate/deactivate campaigns)
					if ( $current_user_id !== 0 && $existing->get_created_by() !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
						return false;
					}

					// Optimistic locking: Store expected version
					$expected_version = $existing->get_version();

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

					// Check if update failed due to concurrent modification
					if ( $result === 0 ) {
						// Throw exception for concurrent modification
						throw new SCD_Concurrent_Modification_Exception(
							$campaign->get_id(),
							$expected_version,
							$expected_version + 1  // Current version must be higher
						);
					}
				} else {
					// Create new campaign
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

		return (bool) $result;
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

		// Check ownership - only creator or admin can delete
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

		// Filter out null results
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

		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		// Only process valid database fields
		$valid_fields = array( 'status', 'created_by', 'discount_type' );

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
		$query        = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause";

		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
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
		// Check for slug existence INCLUDING soft-deleted campaigns
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

		// Extract base slug by removing trailing -number if present
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
	 * @return   SCD_Campaign|false         Saved campaign or false on failure.
	 */
	public function save_campaign_with_products( array $campaign_data ): SCD_Campaign|false {
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

		}

		// Create campaign instance
		$campaign = new SCD_Campaign( $campaign_data );

		if ( ! $campaign->is_valid() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$validation_errors = $campaign->validate();

			}
			return false;
		}

		// Save campaign
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
				// Get all active campaigns
				$active_campaigns = $this->get_active_campaigns();

				// Filter campaigns that can apply to this product
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

		// Filter out null results
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

		// Filter out null results
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
	 * @param    SCD_Campaign $campaign    Campaign to check conflicts for.
	 * @return   array                        Array of conflicting campaigns.
	 */
	public function get_conflicting_campaigns( SCD_Campaign $campaign ): array {
		$conflicts = array();

		// Get campaigns with overlapping date ranges
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

			// Filter out null results
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
	 * @return   SCD_Campaign       Campaign instance.
	 */
	private function hydrate( object $data ): SCD_Campaign {
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

			// Discount Configuration
			'discount_type'          => $data->discount_type ?? 'percentage',
			'discount_value'         => (float) ( $data->discount_value ?? 0.0 ),
			'discount_rules'         => isset( $data->discount_rules ) ? json_decode( $data->discount_rules ?: '[]', true ) : array(),

			// Scheduling
			'starts_at'              => $data->starts_at,
			'ends_at'                => $data->ends_at,
			'timezone'               => $data->timezone,

			// Timestamps
			'created_at'             => $data->created_at,
			'updated_at'             => $data->updated_at,
			'deleted_at'             => $data->deleted_at,
		);

		// Note: Database has additional fields (color_theme, icon, rotation_*,
		// performance metrics, etc.) that are not in the simplified Campaign entity.
		// These are stored in settings/metadata or tracked by separate services.

		return new SCD_Campaign( $campaign_data );
	}

	/**
	 * Dehydrate campaign to database format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   array                        Database data.
	 */
	private function dehydrate( SCD_Campaign $campaign ): array {
		$data = $campaign->to_array();

		// Remove legacy duplicate fields - they're redundant with product_ids/category_ids/tag_ids
		unset( $data['selected_products'], $data['selected_categories'], $data['selected_tags'] );

		// Convert arrays to JSON
		$json_fields = array(
			'settings',
			'metadata',
			'product_ids',
			'category_ids',
			'tag_ids',
			'discount_rules',
			'usage_limits',
			'discount_configuration',
			'schedule_configuration',
		);

		foreach ( $json_fields as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		// Remove ID for new campaigns
		if ( ! $campaign->get_id() ) {
			unset( $data['id'] );
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
	 * Clear campaign cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   void
	 */
	private function clear_campaign_cache( SCD_Campaign $campaign ): void {
		global $wpdb;

		// Clear individual campaign caches
		$this->cache->delete( "campaign_{$campaign->get_id()}" );
		$this->cache->delete( "campaign_uuid_{$campaign->get_uuid()}" );
		$this->cache->delete( "campaign_slug_{$campaign->get_slug()}" );

		// Clear campaign list caches
		$this->cache->delete( 'active_campaigns' );
		$this->cache->delete( 'scheduled_campaigns' );
		$this->cache->delete( 'paused_campaigns' );
		wp_cache_delete( 'active_campaigns', 'scd' );
		wp_cache_delete( 'scheduled_campaigns', 'scd' );
		wp_cache_delete( 'paused_campaigns', 'scd' );

		// Clear transient-based campaign list caches
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s',
				$wpdb->options,
				'_transient_scd_active_campaigns_%',
				'_transient_timeout_scd_active_campaigns_%'
			)
		);

		// Clear product-specific campaign caches (both object cache and transients)
		$product_ids = $campaign->get_product_ids();
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				// Clear object cache
				wp_cache_delete( 'campaigns_by_product_' . $product_id, 'scd' );
				wp_cache_delete( 'active_campaigns_product_' . $product_id, 'scd' );

				// Clear transient cache
				delete_transient( 'scd_campaigns_by_product_' . $product_id );

				// Clear WooCommerce product transients to force price recalculation
				if ( function_exists( 'wc_delete_product_transients' ) ) {
					wc_delete_product_transients( $product_id );
				}
			}
		}

		// Also bulk clear all product-specific transients (for condition-based campaigns)
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s',
				$wpdb->options,
				'_transient_scd_campaigns_by_product_%',
				'_transient_timeout_scd_campaigns_by_product_%'
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

		// Parse arguments into criteria and options
		if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
			$criteria['status'] = $args['status'];
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

		// Add total count
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
		$search_term   = $this->db->esc_like( $args['search'] );
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		// Add search conditions
		$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
		$where_values[]  = '%' . $search_term . '%';
		$where_values[]  = '%' . $search_term . '%';

		// Add status filter if provided
		if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $args['status'];
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
		$search_term   = $this->db->esc_like( $criteria['search'] );
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_values  = array();

		// Add search conditions
		$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
		$where_values[]  = '%' . $search_term . '%';
		$where_values[]  = '%' . $search_term . '%';

		// Add status filter if provided
		if ( isset( $criteria['status'] ) && ! empty( $criteria['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $criteria['status'];
		}

		$where_clause = implode( ' AND ', $where_clauses );
		$query        = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause";

		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
		}

		return (int) $this->db->get_var( $query );
	}

	/**
	 * Apply custom WHERE conditions for campaigns.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    SCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	protected function apply_custom_where_conditions( $query_builder, $args ) {
		// Add campaign-specific WHERE conditions
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

		// Set default values
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

		$order_by_field  = $options['order_by'] ?? $options['orderby'] ?? '';
		$order_direction = $options['order_direction'] ?? $options['order'] ?? 'ASC';

		return "ORDER BY {$order_by_field} $order_direction";
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
		if ( empty( $options['limit'] ) ) {
			return '';
		}

		$offset = $options['offset'] ?? 0;
		return "LIMIT $offset, {$options['limit']}";
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
		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
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
}
