<?php
/**
 * Privacy Integration Class
 *
 * Handles GDPR/CCPA compliance and WordPress privacy tools integration.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Privacy Integration Class
 *
 * Integrates with WordPress privacy tools to provide GDPR/CCPA compliance.
 *
 * Features:
 * - Privacy policy suggested text
 * - Personal data export
 * - Personal data erasure
 * - Data retention policies
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Privacy_Integration {

	/**
	 * Initialize privacy integration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Add privacy policy content
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Register personal data exporters
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );

		// Register personal data erasers
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
	}

	/**
	 * Add suggested privacy policy content.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = $this->get_privacy_policy_content();

		wp_add_privacy_policy_content(
			__( 'Smart Cycle Discounts', 'smart-cycle-discounts' ),
			$content
		);
	}

	/**
	 * Get privacy policy content.
	 *
	 * @since    1.0.0
	 * @return   string    Privacy policy content.
	 */
	private function get_privacy_policy_content(): string {
		ob_start();
		?>
		<h2><?php esc_html_e( 'What personal data we collect and why', 'smart-cycle-discounts' ); ?></h2>

		<h3><?php esc_html_e( 'Campaign Analytics', 'smart-cycle-discounts' ); ?></h3>
		<p><?php esc_html_e( 'When you view or interact with discount campaigns on our site, we may collect the following information:', 'smart-cycle-discounts' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Campaign impressions (when you view a discount)', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Campaign clicks (when you click on a discount)', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Purchase data (when you complete an order with a discount)', 'smart-cycle-discounts' ); ?></li>
		</ul>

		<p><strong><?php esc_html_e( 'Purpose:', 'smart-cycle-discounts' ); ?></strong> <?php esc_html_e( 'We collect this data to measure campaign effectiveness, calculate discount ROI, and improve our promotional strategies.', 'smart-cycle-discounts' ); ?></p>

		<p><strong><?php esc_html_e( 'Data stored:', 'smart-cycle-discounts' ); ?></strong> <?php esc_html_e( 'Campaign interaction data is stored in aggregated form (total impressions, clicks, conversions) without personally identifiable information.', 'smart-cycle-discounts' ); ?></p>

		<h3><?php esc_html_e( 'Order Data', 'smart-cycle-discounts' ); ?></h3>
		<p><?php esc_html_e( 'When you make a purchase using a discount:', 'smart-cycle-discounts' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Order total and discount amount', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Products purchased', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Campaign used', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Customer ID (linked to your user account)', 'smart-cycle-discounts' ); ?></li>
		</ul>

		<p><strong><?php esc_html_e( 'Purpose:', 'smart-cycle-discounts' ); ?></strong> <?php esc_html_e( 'This data is used to track discount usage, prevent abuse, and generate revenue reports.', 'smart-cycle-discounts' ); ?></p>

		<h2><?php esc_html_e( 'How long we retain your data', 'smart-cycle-discounts' ); ?></h2>
		<p><?php esc_html_e( 'Analytics data is retained for up to 90 days for active campaigns and reporting purposes. Aggregated campaign performance data (impressions, clicks, conversions) is retained indefinitely for historical reporting.', 'smart-cycle-discounts' ); ?></p>

		<p><?php esc_html_e( 'Order-related discount data is retained as long as the order record exists in accordance with your e-commerce platform\'s data retention policies.', 'smart-cycle-discounts' ); ?></p>

		<h2><?php esc_html_e( 'Your rights over your data', 'smart-cycle-discounts' ); ?></h2>
		<p><?php esc_html_e( 'If you have an account on this site or have made purchases, you can request to:', 'smart-cycle-discounts' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Export your personal data (via WordPress privacy tools)', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Erase your personal data (via WordPress privacy tools)', 'smart-cycle-discounts' ); ?></li>
		</ul>

		<p><?php esc_html_e( 'Note: This does not include aggregated, anonymized analytics data used for campaign performance metrics.', 'smart-cycle-discounts' ); ?></p>

		<h2><?php esc_html_e( 'Data sharing', 'smart-cycle-discounts' ); ?></h2>
		<p><?php esc_html_e( 'We do not share your personal discount usage data with third parties. Analytics are used internally for business intelligence only.', 'smart-cycle-discounts' ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register personal data exporters.
	 *
	 * @since    1.0.0
	 * @param    array $exporters    Existing exporters.
	 * @return   array                  Updated exporters.
	 */
	public function register_exporters( array $exporters ): array {
		$exporters['smart-cycle-discounts'] = array(
			'exporter_friendly_name' => __( 'Smart Cycle Discounts', 'smart-cycle-discounts' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Export personal data.
	 *
	 * @since    1.0.0
	 * @param    string $email_address    User email address.
	 * @param    int    $page             Page number.
	 * @return   array                       Export data.
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$export_items = array();

		// Get user by email
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		// Export customer usage data
		$usage_data = $this->get_customer_usage_data( $user->ID );
		if ( ! empty( $usage_data ) ) {
			foreach ( $usage_data as $usage ) {
				$export_items[] = array(
					'group_id'    => 'smart-cycle-discounts',
					'group_label' => __( 'Discount Usage', 'smart-cycle-discounts' ),
					'item_id'     => 'usage-' . $usage['id'],
					'data'        => array(
						array(
							'name'  => __( 'Campaign', 'smart-cycle-discounts' ),
							'value' => $usage['campaign_name'],
						),
						array(
							'name'  => __( 'Usage Count', 'smart-cycle-discounts' ),
							'value' => $usage['usage_count'],
						),
						array(
							'name'  => __( 'First Used', 'smart-cycle-discounts' ),
							'value' => $usage['first_used_at'],
						),
						array(
							'name'  => __( 'Last Used', 'smart-cycle-discounts' ),
							'value' => $usage['last_used_at'],
						),
					),
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Register personal data erasers.
	 *
	 * @since    1.0.0
	 * @param    array $erasers    Existing erasers.
	 * @return   array                Updated erasers.
	 */
	public function register_erasers( array $erasers ): array {
		$erasers['smart-cycle-discounts'] = array(
			'eraser_friendly_name' => __( 'Smart Cycle Discounts', 'smart-cycle-discounts' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Erase personal data.
	 *
	 * @since    1.0.0
	 * @param    string $email_address    User email address.
	 * @param    int    $page             Page number.
	 * @return   array                       Erasure result.
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		// Get user by email
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		// Erase customer usage data
		$usage_table = $wpdb->prefix . 'scd_customer_usage';
		$deleted     = $wpdb->delete(
			$usage_table,
			array( 'customer_id' => $user->ID ),
			array( '%d' )
		);

		if ( false !== $deleted && $deleted > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d: number of records removed */
				__( 'Removed %d discount usage record(s).', 'smart-cycle-discounts' ),
				$deleted
			);
		}

		// Note: Aggregated analytics are retained as they cannot be traced to individuals
		$messages[] = __( 'Aggregated campaign analytics data is retained for business intelligence.', 'smart-cycle-discounts' );

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Get customer usage data.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    User ID.
	 * @return   array              Usage data.
	 */
	private function get_customer_usage_data( int $user_id ): array {
		global $wpdb;

		$usage_table    = $wpdb->prefix . 'scd_customer_usage';
		$campaigns_table = $wpdb->prefix . 'scd_campaigns';

		$query = $wpdb->prepare(
			"SELECT
				u.id,
				u.campaign_id,
				c.name as campaign_name,
				u.usage_count,
				u.first_used_at,
				u.last_used_at
			FROM {$usage_table} u
			LEFT JOIN {$campaigns_table} c ON u.campaign_id = c.id
			WHERE u.customer_id = %d
			ORDER BY u.last_used_at DESC",
			$user_id
		);

		$results = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $results ) ? $results : array();
	}
}
