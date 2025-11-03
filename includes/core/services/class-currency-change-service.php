<?php
/**
 * Currency Change Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/services/class-currency-change-service.php
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
 * Currency Change Service Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/services
 */
class SCD_Currency_Change_Service {

	/**
	 * Campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $campaign_repository    Campaign repository.
	 */
	private $campaign_repository;

	/**
	 * Current currency code.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_currency    Current currency.
	 */
	private $current_currency;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    object $campaign_repository    Campaign repository instance.
	 */
	public function __construct( $campaign_repository = null ) {
		$this->campaign_repository = $campaign_repository;
		$this->current_currency    = get_woocommerce_currency();
	}

	/**
	 * Initialize service.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		// Hook into WooCommerce settings update
		add_action( 'update_option_woocommerce_currency', array( $this, 'handle_currency_change' ), 10, 2 );

		// Hook into campaign creation to track currency
		add_action( 'scd_campaign_created', array( $this, 'track_campaign_currency' ), 10, 1 );
		add_action( 'scd_campaign_updated', array( $this, 'track_campaign_currency' ), 10, 1 );
	}

	/**
	 * Handle currency change event.
	 *
	 * @since    1.0.0
	 * @param    string $old_value    Old currency code.
	 * @param    string $new_value    New currency code.
	 * @return   void
	 */
	public function handle_currency_change( $old_value, $new_value ) {
		// Skip if currency hasn't actually changed
		if ( $old_value === $new_value ) {
			return;
		}

		$affected_campaigns = $this->get_affected_campaigns( $old_value );

		if ( empty( $affected_campaigns ) ) {
			return;
		}

		// Pause affected campaigns
		$paused_count = $this->pause_affected_campaigns( $affected_campaigns, $old_value, $new_value );

		$this->set_currency_change_notice( $paused_count, $old_value, $new_value );

		// Fire action for extensibility
		do_action( 'scd_currency_changed', $old_value, $new_value, $paused_count );
	}

	/**
	 * Get campaigns affected by currency change.
	 *
	 * @since    1.0.0
	 * @param    string $old_currency    Old currency code.
	 * @return   array                      Array of affected campaign objects.
	 */
	private function get_affected_campaigns( $old_currency ) {
		if ( ! $this->campaign_repository ) {
			require_once SCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
			$this->campaign_repository = new SCD_Campaign_Repository();
		}

		$campaigns = $this->campaign_repository->find_by_status( array( 'active', 'scheduled', 'paused' ) );

		if ( empty( $campaigns ) ) {
			return array();
		}

		$affected = array();
		foreach ( $campaigns as $campaign ) {
			if ( $this->campaign_needs_review( $campaign, $old_currency ) ) {
				$affected[] = $campaign;
			}
		}

		return $affected;
	}

	/**
	 * Check if campaign needs review due to currency change.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign        Campaign object.
	 * @param    string       $old_currency    Old currency code.
	 * @return   bool                             True if needs review.
	 */
	private function campaign_needs_review( $campaign, $old_currency ) {
		$campaign_currency = $campaign->get_meta( 'currency_code' );
		if ( $campaign_currency && $campaign_currency !== $old_currency ) {
			// Campaign was created in different currency, doesn't need review
			return false;
		}

		$discount_type = $campaign->get_discount_type();

		// Percentage-based discounts are safe (10% = 10% in any currency)
		$safe_types = array( 'percentage' );

		// BOGO deals are safe too
		if ( 'bogo' === $discount_type ) {
			return false;
		}

		// Fixed, tiered, and spend threshold discounts need review
		$needs_review_types = array( 'fixed', 'tiered', 'spend_threshold', 'bulk' );

		return in_array( $discount_type, $needs_review_types, true );
	}

	/**
	 * Pause affected campaigns.
	 *
	 * @since    1.0.0
	 * @param    array  $campaigns      Array of campaign objects.
	 * @param    string $old_currency   Old currency code.
	 * @param    string $new_currency   New currency code.
	 * @return   int                       Number of campaigns paused.
	 */
	private function pause_affected_campaigns( $campaigns, $old_currency, $new_currency ) {
		$paused_count = 0;

		foreach ( $campaigns as $campaign ) {
			// Skip if already paused
			if ( 'paused' === $campaign->get_status() ) {
				// Just update metadata
				$this->mark_for_currency_review( $campaign, $old_currency, $new_currency );
				continue;
			}

			$original_status = $campaign->get_status();
			$campaign->set_meta( 'pre_currency_change_status', $original_status );

			// Mark for review
			$this->mark_for_currency_review( $campaign, $old_currency, $new_currency );

			// Pause the campaign
			if ( $campaign->can_transition_to( 'paused' ) ) {
				$campaign->set_status( 'paused' );

				try {
					$this->campaign_repository->save( $campaign );
					++$paused_count;

					// Log the pause
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log(
							sprintf(
								'[SCD Currency Change] Paused campaign #%d (%s) - Discount type: %s',
								$campaign->get_id(),
								$campaign->get_name(),
								$campaign->get_discount_type()
							)
						);
					}
				} catch ( Exception $e ) {
					error_log(
						sprintf(
							'[SCD Currency Change] Failed to pause campaign #%d: %s',
							$campaign->get_id(),
							$e->getMessage()
						)
					);
				}
			}
		}

		return $paused_count;
	}

	/**
	 * Mark campaign for currency review.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign        Campaign object.
	 * @param    string       $old_currency    Old currency code.
	 * @param    string       $new_currency    New currency code.
	 * @return   void
	 */
	private function mark_for_currency_review( $campaign, $old_currency, $new_currency ) {
		$campaign->set_meta( 'requires_currency_review', true );
		$campaign->set_meta( 'currency_change_date', current_time( 'mysql' ) );
		$campaign->set_meta( 'old_currency', $old_currency );
		$campaign->set_meta( 'new_currency', $new_currency );
		$campaign->set_meta( 'original_discount_value', $campaign->get_discount_value() );
	}

	/**
	 * Track campaign currency on creation/update.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	public function track_campaign_currency( $campaign ) {
		$current_currency = get_woocommerce_currency();
		$campaign->set_meta( 'currency_code', $current_currency );
		$campaign->set_meta( 'currency_symbol', get_woocommerce_currency_symbol() );

		if ( $this->campaign_repository ) {
			try {
				$this->campaign_repository->save( $campaign );
			} catch ( Exception $e ) {
				error_log(
					sprintf(
						'[SCD Currency Tracking] Failed to save currency metadata for campaign #%d: %s',
						$campaign->get_id(),
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Set admin notice for currency change.
	 *
	 * @since    1.0.0
	 * @param    int    $paused_count    Number of campaigns paused.
	 * @param    string $old_currency    Old currency code.
	 * @param    string $new_currency    New currency code.
	 * @return   void
	 */
	private function set_currency_change_notice( $paused_count, $old_currency, $new_currency ) {
		$notice_data = array(
			'paused_count' => $paused_count,
			'old_currency' => $old_currency,
			'new_currency' => $new_currency,
			'timestamp'    => time(),
		);

		set_transient( 'scd_currency_change_notice', $notice_data, DAY_IN_SECONDS );
	}

	/**
	 * Get campaigns requiring currency review.
	 *
	 * @since    1.0.0
	 * @return   array    Array of campaign objects.
	 */
	public function get_campaigns_needing_review() {
		if ( ! $this->campaign_repository ) {
			require_once SCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
			$this->campaign_repository = new SCD_Campaign_Repository();
		}

		$all_campaigns = $this->campaign_repository->find_all();

		if ( empty( $all_campaigns ) ) {
			return array();
		}

		$needing_review = array();
		foreach ( $all_campaigns as $campaign ) {
			if ( $campaign->get_meta( 'requires_currency_review' ) ) {
				$needing_review[] = $campaign;
			}
		}

		return $needing_review;
	}

	/**
	 * Clear currency review flag from campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success.
	 */
	public function clear_review_flag( $campaign_id ) {
		if ( ! $this->campaign_repository ) {
			require_once SCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
			$this->campaign_repository = new SCD_Campaign_Repository();
		}

		try {
			$campaign = $this->campaign_repository->find_by_id( $campaign_id );

			if ( ! $campaign ) {
				return false;
			}

			$campaign->set_meta( 'requires_currency_review', false );

			// Save
			$this->campaign_repository->save( $campaign );

			return true;
		} catch ( Exception $e ) {
			error_log(
				sprintf(
					'[SCD Currency Review] Failed to clear review flag for campaign #%d: %s',
					$campaign_id,
					$e->getMessage()
				)
			);
			return false;
		}
	}

	/**
	 * Restore campaign to pre-currency-change status.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success.
	 */
	public function restore_campaign_status( $campaign_id ) {
		if ( ! $this->campaign_repository ) {
			require_once SCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
			$this->campaign_repository = new SCD_Campaign_Repository();
		}

		try {
			$campaign = $this->campaign_repository->find_by_id( $campaign_id );

			if ( ! $campaign ) {
				return false;
			}

			$original_status = $campaign->get_meta( 'pre_currency_change_status' );

			if ( ! $original_status ) {
				return false;
			}

			// Restore status
			if ( $campaign->can_transition_to( $original_status ) ) {
				$campaign->set_status( $original_status );

				$campaign->set_meta( 'requires_currency_review', false );
				$campaign->set_meta( 'pre_currency_change_status', null );

				// Save
				$this->campaign_repository->save( $campaign );

				return true;
			}

			return false;
		} catch ( Exception $e ) {
			error_log(
				sprintf(
					'[SCD Currency Review] Failed to restore campaign #%d: %s',
					$campaign_id,
					$e->getMessage()
				)
			);
			return false;
		}
	}
}
