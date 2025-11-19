<?php
/**
 * Customer Usage Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-customer-usage-repository.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Customer Usage Repository
 *
 * Handles database operations for customer discount usage tracking.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Customer_Usage_Repository {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private SCD_Database_Manager $db;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $table_name    Table name.
	 */
	private string $table_name;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager|null    $cache    Cache manager.
	 */
	private ?SCD_Cache_Manager $cache = null;

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager   $db        Database manager.
	 * @param    SCD_Logger             $logger    Logger instance.
	 * @param    SCD_Cache_Manager|null $cache     Cache manager (optional).
	 */
	public function __construct( SCD_Database_Manager $db, SCD_Logger $logger, ?SCD_Cache_Manager $cache = null ) {
		$this->db         = $db;
		$this->logger     = $logger;
		$this->cache      = $cache;
		$this->table_name = $this->db->get_table_name( 'customer_usage' );
	}

	/**
	 * Get customer usage for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id       Campaign ID.
	 * @param    string $customer_email    Customer email.
	 * @return   array|null                   Usage data or null if not found.
	 */
	public function get_customer_usage( int $campaign_id, string $customer_email ): ?array {
		try {
			$result = $this->db->get_row(
				$this->db->prepare(
					"SELECT * FROM {$this->table_name} 
                    WHERE campaign_id = %d AND customer_email = %s",
					$campaign_id,
					$customer_email
				),
				ARRAY_A
			);

			return $result ? $this->format_usage_data( $result ) : null;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get customer usage',
				array(
					'campaign_id'    => $campaign_id,
					'customer_email' => $customer_email,
					'error'          => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * Check if customer can use discount.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id           Campaign ID.
	 * @param    string $customer_email        Customer email.
	 * @param    int    $max_uses_per_customer Maximum uses per customer.
	 * @return   bool                           True if can use, false otherwise.
	 */
	public function can_customer_use_discount( int $campaign_id, string $customer_email, int $max_uses_per_customer ): bool {
		try {
			$usage = $this->get_customer_usage( $campaign_id, $customer_email );

			// If no usage recorded, customer can use the discount
			if ( ! $usage ) {
				return true;
			}

			if ( $usage['status'] === 'blocked' ) {
				return false;
			}

			return $usage['usage_count'] < $max_uses_per_customer;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to check customer discount eligibility',
				array(
					'campaign_id'    => $campaign_id,
					'customer_email' => $customer_email,
					'error'          => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Record customer usage.
	 *
	 * @since    1.0.0
	 * @param    array $usage_data    Usage data.
	 * @return   int|false               Insert ID or false on failure.
	 */
	public function record_usage( array $usage_data ): int|false {
		try {
			$existing = $this->get_customer_usage(
				intval( $usage_data['campaign_id'] ),
				strval( $usage_data['customer_email'] )
			);

			if ( $existing ) {
				return $this->update_usage( $existing['id'], $usage_data );
			} else {
				// Insert new record
				return $this->insert_usage( $usage_data );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to record customer usage',
				array(
					'usage_data' => $usage_data,
					'error'      => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Insert new usage record.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $usage_data    Usage data.
	 * @return   int|false               Insert ID or false on failure.
	 */
	private function insert_usage( array $usage_data ): int|false {
		$defaults = array(
			'usage_count'           => 1,
			'first_used_at'         => current_time( 'mysql' ),
			'last_used_at'          => current_time( 'mysql' ),
			'total_discount_amount' => 0.0000,
			'total_order_value'     => 0.0000,
			'status'                => 'active',
		);

		$data = wp_parse_args( $usage_data, $defaults );

		$insert_data = array(
			'campaign_id'           => intval( $data['campaign_id'] ),
			'customer_id'           => ! empty( $data['customer_id'] ) ? intval( $data['customer_id'] ) : null,
			'customer_email'        => sanitize_email( $data['customer_email'] ),
			'usage_count'           => intval( $data['usage_count'] ),
			'first_used_at'         => $data['first_used_at'],
			'last_used_at'          => $data['last_used_at'],
			'total_discount_amount' => floatval( $data['total_discount_amount'] ),
			'total_order_value'     => floatval( $data['total_order_value'] ),
			'order_ids'             => ! empty( $data['order_ids'] ) ? wp_json_encode( $data['order_ids'] ) : null,
			'session_id'            => ! empty( $data['session_id'] ) ? sanitize_text_field( $data['session_id'] ) : null,
			'ip_address'            => ! empty( $data['ip_address'] ) ? sanitize_text_field( $data['ip_address'] ) : null,
			'user_agent'            => ! empty( $data['user_agent'] ) ? sanitize_text_field( $data['user_agent'] ) : null,
			'status'                => $data['status'],
		);

		$result = $this->db->insert( $this->table_name, $insert_data );

		if ( $result && $this->cache ) {
			$this->cache->invalidate_analytics();
		}

		return $result ? $this->db->insert_id : false;
	}

	/**
	 * Update existing usage record.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $id            Record ID.
	 * @param    array $usage_data    Usage data.
	 * @return   int|false               Updated ID or false on failure.
	 */
	private function update_usage( int $id, array $usage_data ): int|false {
		$existing = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $existing ) {
			return false;
		}

		$existing_order_ids = ! empty( $existing['order_ids'] ) ?
			json_decode( $existing['order_ids'], true ) : array();

		if ( ! empty( $usage_data['order_id'] ) ) {
			$existing_order_ids[] = intval( $usage_data['order_id'] );
			$existing_order_ids   = array_unique( $existing_order_ids );
		}

		$update_data = array(
			'usage_count'           => intval( $existing['usage_count'] ) + 1,
			'last_used_at'          => current_time( 'mysql' ),
			'total_discount_amount' => floatval( $existing['total_discount_amount'] ) +
									floatval( $usage_data['discount_amount'] ?? 0 ),
			'total_order_value'     => floatval( $existing['total_order_value'] ) +
								floatval( $usage_data['order_value'] ?? 0 ),
			'order_ids'             => wp_json_encode( $existing_order_ids ),
		);

		if ( ! empty( $usage_data['session_id'] ) ) {
			$update_data['session_id'] = sanitize_text_field( $usage_data['session_id'] );
		}

		if ( ! empty( $usage_data['ip_address'] ) ) {
			$update_data['ip_address'] = sanitize_text_field( $usage_data['ip_address'] );
		}

		if ( ! empty( $usage_data['user_agent'] ) ) {
			$update_data['user_agent'] = sanitize_text_field( $usage_data['user_agent'] );
		}

		$result = $this->db->update(
			$this->table_name,
			$update_data,
			array( 'id' => $id )
		);

		if ( $result !== false && $this->cache ) {
			$this->cache->invalidate_analytics();
		}

		return $result !== false ? $id : false;
	}

	/**
	 * Get usage statistics for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   array                  Usage statistics.
	 */
	public function get_campaign_usage_stats( int $campaign_id ): array {
		try {
			$stats = $this->db->get_row(
				$this->db->prepare(
					"SELECT 
                        COUNT(*) as total_customers,
                        SUM(usage_count) as total_uses,
                        SUM(total_discount_amount) as total_discount_given,
                        SUM(total_order_value) as total_revenue,
                        AVG(usage_count) as avg_uses_per_customer,
                        MAX(usage_count) as max_uses_by_single_customer
                    FROM {$this->table_name}
                    WHERE campaign_id = %d AND status = 'active'",
					$campaign_id
				),
				ARRAY_A
			);

			return $stats ?: array(
				'total_customers'             => 0,
				'total_uses'                  => 0,
				'total_discount_given'        => 0.0,
				'total_revenue'               => 0.0,
				'avg_uses_per_customer'       => 0.0,
				'max_uses_by_single_customer' => 0,
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign usage stats',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Block customer from using discount.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id       Campaign ID.
	 * @param    string $customer_email    Customer email.
	 * @return   bool                         True on success, false on failure.
	 */
	public function block_customer( int $campaign_id, string $customer_email ): bool {
		try {
			$result = $this->db->update(
				$this->table_name,
				array( 'status' => 'blocked' ),
				array(
					'campaign_id'    => $campaign_id,
					'customer_email' => $customer_email,
				)
			);

			return $result !== false;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to block customer',
				array(
					'campaign_id'    => $campaign_id,
					'customer_email' => $customer_email,
					'error'          => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Clean up expired sessions.
	 *
	 * @since    1.0.0
	 * @param    int $days_old    Number of days to consider expired.
	 * @return   int                 Number of records updated.
	 */
	public function cleanup_expired_sessions( int $days_old = 30 ): int {
		try {
			$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

			$result = $this->db->query(
				$this->db->prepare(
					"UPDATE {$this->table_name} 
                    SET status = 'expired' 
                    WHERE last_used_at < %s AND status = 'active'",
					$cutoff_date
				)
			);

			return intval( $result );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to cleanup expired sessions',
				array(
					'days_old' => $days_old,
					'error'    => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Get all usage records for a customer.
	 *
	 * @since    1.0.0
	 * @param    string $customer_email    Customer email.
	 * @return   array                        Array of usage records.
	 */
	public function get_customer_all_usage( string $customer_email ): array {
		try {
			$results = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->table_name} 
                    WHERE customer_email = %s",
					$customer_email
				),
				ARRAY_A
			);

			return array_map( array( $this, 'format_usage_data' ), $results ?: array() );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get customer all usage',
				array(
					'customer_email' => $customer_email,
					'error'          => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Get total usage count for a campaign (per cycle).
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int                    Total usage count.
	 */
	public function get_campaign_total_usage( int $campaign_id ): int {
		try {
			$count = $this->db->get_var(
				$this->db->prepare(
					"SELECT SUM(usage_count) FROM {$this->table_name}
                    WHERE campaign_id = %d",
					$campaign_id
				)
			);

			return $count ? intval( $count ) : 0;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign total usage',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Get lifetime usage count for a campaign (across all cycles).
	 *
	 * This counts all historical usage regardless of cycle.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int                    Lifetime usage count.
	 */
	public function get_campaign_lifetime_usage( int $campaign_id ): int {
		try {
			$count = $this->db->get_var(
				$this->db->prepare(
					"SELECT SUM(usage_count) FROM {$this->table_name}
                    WHERE campaign_id = %d",
					$campaign_id
				)
			);

			return $count ? intval( $count ) : 0;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign lifetime usage',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Format usage data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Raw database data.
	 * @return   array              Formatted data.
	 */
	private function format_usage_data( array $data ): array {
		$data['order_ids'] = ! empty( $data['order_ids'] ) ?
			json_decode( $data['order_ids'], true ) : array();

		$data['usage_count']           = intval( $data['usage_count'] );
		$data['campaign_id']           = intval( $data['campaign_id'] );
		$data['customer_id']           = ! empty( $data['customer_id'] ) ? intval( $data['customer_id'] ) : null;
		$data['total_discount_amount'] = floatval( $data['total_discount_amount'] );
		$data['total_order_value']     = floatval( $data['total_order_value'] );

		return $data;
	}
}
