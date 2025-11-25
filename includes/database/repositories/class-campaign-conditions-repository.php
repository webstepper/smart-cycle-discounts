<?php
/**
 * Campaign Conditions Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-campaign-conditions-repository.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign Conditions Repository Class
 *
 * Handles CRUD operations for campaign product conditions.
 * Conditions define filter rules for selecting products (price, stock, category, etc).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories
 */
class SCD_Campaign_Conditions_Repository {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private SCD_Database_Manager $db;

	/**
	 * Table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $table_name    Conditions table name.
	 */
	private string $table_name;

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $db    Database manager.
	 */
	public function __construct( SCD_Database_Manager $db ) {
		$this->db         = $db;
		$this->table_name = $this->db->get_table_name( 'campaign_conditions' );
	}

	/**
	 * Get all conditions for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   array                 Array of condition arrays.
	 */
	public function get_conditions_for_campaign( int $campaign_id ): array {
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE campaign_id = %d
				ORDER BY sort_order ASC, id ASC",
				$campaign_id
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Save conditions for a campaign.
	 *
	 * Replaces all existing conditions with new ones.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $conditions     Array of condition arrays.
	 * @return   bool                   True on success.
	 */
	public function save_conditions( int $campaign_id, array $conditions ): bool {
		// Start transaction
		$result = $this->db->transaction(
			function () use ( $campaign_id, $conditions ) {
				// Delete existing conditions
				$deleted = $this->delete_conditions( $campaign_id );
				if ( ! $deleted ) {
					return false;
				}

				// Insert new conditions
				foreach ( $conditions as $index => $condition ) {
					if ( ! is_array( $condition ) ) {
						continue;
					}

					$data = array(
						'campaign_id'    => $campaign_id,
						'condition_type' => $condition['condition_type'] ?? '',
						'operator'       => $condition['operator'] ?? '=',
						'value'          => $condition['value'] ?? '',
						'value2'         => $condition['value2'] ?? null,
						'mode'           => $condition['mode'] ?? 'include',
						'sort_order'     => $index,
					);

					$inserted = $this->db->insert(
						'campaign_conditions',
						$data,
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
					);

					if ( ! $inserted ) {
						return false;
					}
				}

				return true;
			}
		);

		return (bool) $result;
	}

	/**
	 * Delete all conditions for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                  True on success.
	 */
	public function delete_conditions( int $campaign_id ): bool {
		$result = $this->db->delete(
			'campaign_conditions',
			array( 'campaign_id' => $campaign_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get campaigns with a specific condition type.
	 *
	 * Useful for analytics and reporting.
	 *
	 * @since    1.0.0
	 * @param    string $condition_type    Condition type (price, stock, etc).
	 * @return   array                       Array of campaign IDs.
	 */
	public function get_campaigns_with_condition_type( string $condition_type ): array {
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT DISTINCT campaign_id FROM {$this->table_name}
				WHERE condition_type = %s",
				$condition_type
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		return array_column( $results, 'campaign_id' );
	}

	/**
	 * Count conditions for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int                   Number of conditions.
	 */
	public function count_conditions( int $campaign_id ): int {
		$count = $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				WHERE campaign_id = %d",
				$campaign_id
			)
		);

		return intval( $count );
	}

	/**
	 * Check if campaign has any conditions.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                  True if has conditions.
	 */
	public function has_conditions( int $campaign_id ): bool {
		return $this->count_conditions( $campaign_id ) > 0;
	}

	/**
	 * Get all unique condition types in use.
	 *
	 * Useful for filtering and UI.
	 *
	 * @since    1.0.0
	 * @return   array    Array of condition types.
	 */
	public function get_all_condition_types(): array {
		$results = $this->db->get_results(
			"SELECT DISTINCT condition_type FROM {$this->table_name}
			ORDER BY condition_type ASC",
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		return array_column( $results, 'condition_type' );
	}
}
