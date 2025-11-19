<?php
/**
 * Customer Usage Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/managers/class-customer-usage-manager.php
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
 * Customer Usage Manager
 *
 * Manages customer discount usage tracking and enforcement.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/managers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Customer_Usage_Manager {

	/**
	 * Customer usage repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Customer_Usage_Repository    $repository    Repository instance.
	 */
	private SCD_Customer_Usage_Repository $repository;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Session manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Session_Service    $session    Session service.
	 */
	private SCD_Session_Service $session;

	/**
	 * Initialize the manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Customer_Usage_Repository $repository    Repository instance.
	 * @param    SCD_Logger                    $logger        Logger instance.
	 * @param    SCD_Session_Service           $session       Session service.
	 */
	public function __construct(
		SCD_Customer_Usage_Repository $repository,
		SCD_Logger $logger,
		SCD_Session_Service $session
	) {
		$this->repository = $repository;
		$this->logger     = $logger;
		$this->session    = $session;
	}

	/**
	 * Check if customer can use discount.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $campaign_data  Campaign data.
	 * @return   array                     Validation result.
	 */
	public function validate_customer_usage( int $campaign_id, array $campaign_data ): array {
		try {
			$customer_email = $this->get_customer_email();

			if ( ! $customer_email ) {
				return array(
					'valid' => false,
					'error' => __( 'Unable to identify customer', 'smart-cycle-discounts' ),
				);
			}

			$max_uses = isset( $campaign_data['max_uses_per_customer'] ) ?
				intval( $campaign_data['max_uses_per_customer'] ) : 0;

			// If no limit configured, allow usage
			if ( $max_uses <= 0 ) {
				return array(
					'valid'          => true,
					'customer_email' => $customer_email,
				);
			}

			$can_use = $this->repository->can_customer_use_discount(
				$campaign_id,
				$customer_email,
				$max_uses
			);

			if ( ! $can_use ) {
				$usage = $this->repository->get_customer_usage( $campaign_id, $customer_email );

				return array(
					'valid'       => false,
					'error'       => sprintf(
						__( 'You have already used this discount %1$d time(s). Maximum allowed: %2$d', 'smart-cycle-discounts' ),
						$usage['usage_count'] ?? 0,
						$max_uses
					),
					'usage_count' => $usage['usage_count'] ?? 0,
					'max_uses'    => $max_uses,
				);
			}

			return array(
				'valid'          => true,
				'customer_email' => $customer_email,
				'customer_id'    => $this->get_customer_id(),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to validate customer usage',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return array(
				'valid' => false,
				'error' => __( 'An error occurred while validating discount usage', 'smart-cycle-discounts' ),
			);
		}
	}

	/**
	 * Record customer usage when order is placed.
	 *
	 * @since    1.0.0
	 * @param    int      $order_id       Order ID.
	 * @param    WC_Order $order          Order object.
	 * @return   void
	 */
	public function record_order_usage( int $order_id, WC_Order $order ): void {
		try {
			foreach ( $order->get_items() as $item ) {
				$campaign_id = $item->get_meta( '_scd_campaign_id' );

				if ( ! $campaign_id ) {
					continue;
				}

				$customer_email = $order->get_billing_email();
				$customer_id    = $order->get_customer_id();

				if ( ! $customer_email ) {
					continue;
				}

				$original_price = floatval( $item->get_meta( '_scd_original_price', true ) );
				$quantity       = max( 1, intval( $item->get_quantity() ) );
				$item_total     = floatval( $item->get_total() );

				if ( $original_price <= 0 || $item_total < 0 ) {
					$this->logger->warning(
						'Invalid price data for usage tracking',
						array(
							'order_id'       => $order_id,
							'item_id'        => $item->get_id(),
							'original_price' => $original_price,
							'item_total'     => $item_total,
						)
					);
					continue;
				}

				$discounted_price = $item_total / $quantity;
				$discount_amount  = max( 0, ( $original_price - $discounted_price ) * $quantity );

				// Record usage
				$usage_data = array(
					'campaign_id'     => intval( $campaign_id ),
					'customer_id'     => $customer_id ?: null,
					'customer_email'  => $customer_email,
					'order_id'        => $order_id,
					'discount_amount' => $discount_amount,
					'order_value'     => floatval( $item->get_total() ),
					'session_id'      => $this->get_session_id(),
					'ip_address'      => $this->get_customer_ip(),
					'user_agent'      => $this->get_user_agent(),
				);

				$result = $this->repository->record_usage( $usage_data );

				if ( $result ) {
					$this->logger->info(
						'Customer usage recorded',
						array(
							'order_id'       => $order_id,
							'campaign_id'    => $campaign_id,
							'customer_email' => $customer_email,
						)
					);
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to record customer usage',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get current customer email.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    Customer email or null.
	 */
	private function get_customer_email(): ?string {
		try {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				if ( $user && $user->user_email ) {
					return sanitize_email( $user->user_email );
				}
			}

			// Check WooCommerce session
			if ( function_exists( 'WC' ) && WC()->session ) {
				$customer = WC()->customer;
				if ( $customer && is_object( $customer ) ) {
					$email = $customer->get_billing_email();
					if ( $email ) {
						return sanitize_email( $email );
					}
				}

				$billing_email = WC()->session->get( 'billing_email' );
				if ( $billing_email ) {
					return sanitize_email( $billing_email );
				}
			}

			if ( is_checkout() && ! empty( $_POST['billing_email'] ) ) {
				// Verify nonce if available
				if ( isset( $_POST['woocommerce-process-checkout-nonce'] ) &&
					! wp_verify_nonce( $_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout' ) ) {
					return null;
				}
				return sanitize_email( $_POST['billing_email'] );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get customer email',
				array(
					'error' => $e->getMessage(),
				)
			);
		}

		return null;
	}

	/**
	 * Get current customer ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int|null    Customer ID or null.
	 */
	private function get_customer_id(): ?int {
		if ( is_user_logged_in() ) {
			return get_current_user_id();
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			return $customer_id ?: null;
		}

		return null;
	}

	/**
	 * Get session ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    Session ID or null.
	 */
	private function get_session_id(): ?string {
		// Try to get from our session service
		try {
			$session_data = $this->session->get_session_data();
			if ( $session_data && isset( $session_data['session_id'] ) ) {
				return $session_data['session_id'];
			}
		} catch ( Exception $e ) {
			// Continue to WooCommerce session
		}

		// Fallback to WooCommerce session
		if ( function_exists( 'WC' ) && WC()->session ) {
			return WC()->session->get_customer_id();
		}

		return null;
	}

	/**
	 * Get customer IP address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    IP address or null.
	 */
	private function get_customer_ip(): ?string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',             // Proxy
			'HTTP_X_FORWARDED_FOR',       // Load balancer
			'HTTP_X_FORWARDED',           // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
			'HTTP_FORWARDED_FOR',         // Proxy
			'HTTP_FORWARDED',             // Proxy
			'REMOTE_ADDR',                 // Standard
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );

				// Handle comma-separated IPs
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}

				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return null;
	}

	/**
	 * Get user agent.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    User agent or null.
	 */
	private function get_user_agent(): ?string {
		return ! empty( $_SERVER['HTTP_USER_AGENT'] ) ?
			substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 255 ) : null;
	}

	/**
	 * Get usage statistics for a customer.
	 *
	 * @since    1.0.0
	 * @param    string $customer_email    Customer email.
	 * @return   array                        Usage statistics.
	 */
	public function get_customer_statistics( string $customer_email ): array {
		try {
			$stats = $this->repository->get_customer_all_usage( $customer_email );

			return array(
				'total_discounts_used' => count( $stats ),
				'total_savings'        => array_sum( array_column( $stats, 'total_discount_amount' ) ),
				'campaigns'            => $stats,
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get customer statistics',
				array(
					'customer_email' => $customer_email,
					'error'          => $e->getMessage(),
				)
			);

			return array(
				'total_discounts_used' => 0,
				'total_savings'        => 0.0,
				'campaigns'            => array(),
			);
		}
	}

	/**
	 * Get total usage count for a campaign (per cycle).
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int                    Total usage count.
	 */
	public function get_total_usage( int $campaign_id ): int {
		try {
			return $this->repository->get_campaign_total_usage( $campaign_id );
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
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int                    Lifetime usage count.
	 */
	public function get_lifetime_usage( int $campaign_id ): int {
		try {
			return $this->repository->get_campaign_lifetime_usage( $campaign_id );
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
	 * Clean up expired sessions.
	 *
	 * @since    1.0.0
	 * @return   int    Number of records cleaned.
	 */
	public function cleanup_expired_sessions(): int {
		return $this->repository->cleanup_expired_sessions( 30 );
	}
}
