<?php
/**
 * Campaign Event Scheduler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-event-scheduler.php
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
 * Campaign Event Scheduler Class
 *
 * Manages one-time scheduled actions for precise campaign activation/deactivation
 * using WooCommerce ActionScheduler.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Event_Scheduler {

	/**
	 * Campaign Manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign Manager instance.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger|null    $logger    Logger instance.
	 */
	private ?SCD_Logger $logger;

	/**
	 * ActionScheduler service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Action_Scheduler_Service    $scheduler    ActionScheduler service.
	 */
	private SCD_Action_Scheduler_Service $scheduler;

	/**
	 * Initialize the event scheduler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager         $campaign_manager    Campaign Manager instance.
	 * @param    SCD_Action_Scheduler_Service $scheduler           ActionScheduler service.
	 * @param    SCD_Logger|null              $logger              Logger instance.
	 */
	public function __construct( SCD_Campaign_Manager $campaign_manager, SCD_Action_Scheduler_Service $scheduler, ?SCD_Logger $logger = null ) {
		$this->campaign_manager = $campaign_manager;
		$this->scheduler        = $scheduler;
		$this->logger           = $logger;
	}

	/**
	 * Schedule events for a campaign.
	 *
	 * Creates one-time WordPress cron events for campaign activation and deactivation.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success, false on failure.
	 */
	public function schedule_campaign_events( int $campaign_id ): bool {
		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				return false;
			}

			$status = $campaign->get_status();

			// Don't schedule events for expired/archived campaigns
			if ( in_array( $status, array( 'expired', 'archived' ), true ) ) {
				return true;
			}

			$this->clear_campaign_events( $campaign_id );

			// Schedule activation event (only for scheduled/draft campaigns)
			if ( in_array( $status, array( 'scheduled', 'draft' ), true ) ) {
				$starts_at = $campaign->get_starts_at();
				if ( $starts_at ) {
					$this->schedule_activation_event( $campaign_id, $starts_at );
				}
			}

			// ALWAYS schedule deactivation event if campaign has end date
			// This is critical for active campaigns to expire properly
			$ends_at = $campaign->get_ends_at();
			if ( $ends_at ) {
				$this->schedule_deactivation_event( $campaign_id, $ends_at );
			}

			return true;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to schedule campaign events',
					array(
						'campaign_id' => $campaign_id,
						'error'       => $e->getMessage(),
					)
				);
			}
			return false;
		}
	}

	/**
	 * Schedule activation event for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int      $campaign_id    Campaign ID.
	 * @param    DateTime $starts_at      Start datetime (UTC).
	 * @return   bool                        True on success, false on failure.
	 */
	private function schedule_activation_event( int $campaign_id, DateTime $starts_at ): bool {
		$hook      = 'scd_activate_campaign';
		$timestamp = $starts_at->getTimestamp();

		// Only schedule if in the future (compare in UTC)
		$now_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		if ( $timestamp <= $now_utc->getTimestamp() ) {
			return false;
		}

		$result = $this->scheduler->schedule_single_action(
			$timestamp,
			$hook,
			array( 'campaign_id' => $campaign_id )
		);

		if ( false !== $result && $this->logger ) {
			$this->logger->info(
				'Scheduled campaign activation action',
				array(
					'campaign_id' => $campaign_id,
					'action_id'   => $result,
					'timestamp'   => $starts_at->format( 'Y-m-d H:i:s' ) . ' UTC',
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Schedule deactivation event for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int      $campaign_id    Campaign ID.
	 * @param    DateTime $ends_at        End datetime (UTC).
	 * @return   bool                        True on success, false on failure.
	 */
	private function schedule_deactivation_event( int $campaign_id, DateTime $ends_at ): bool {
		$hook      = 'scd_deactivate_campaign';
		$timestamp = $ends_at->getTimestamp();

		// Only schedule if in the future (compare in UTC)
		$now_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		if ( $timestamp <= $now_utc->getTimestamp() ) {
			return false;
		}

		$result = $this->scheduler->schedule_single_action(
			$timestamp,
			$hook,
			array( 'campaign_id' => $campaign_id )
		);

		if ( false !== $result && $this->logger ) {
			$this->logger->info(
				'Scheduled campaign deactivation action',
				array(
					'campaign_id' => $campaign_id,
					'action_id'   => $result,
					'timestamp'   => $ends_at->format( 'Y-m-d H:i:s' ) . ' UTC',
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Clear all scheduled events for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function clear_campaign_events( int $campaign_id ): void {
		$this->scheduler->unschedule_action(
			'scd_activate_campaign',
			array( 'campaign_id' => $campaign_id )
		);

		$this->scheduler->unschedule_action(
			'scd_deactivate_campaign',
			array( 'campaign_id' => $campaign_id )
		);
	}

	/**
	 * Handle campaign activation action.
	 *
	 * Called by ActionScheduler at exact scheduled time.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function handle_activation_event( int $campaign_id ): void {
		try {
			if ( $this->logger ) {
				$this->logger->info(
					'Processing scheduled campaign activation',
					array(
						'campaign_id' => $campaign_id,
						'time'        => gmdate( 'Y-m-d H:i:s' ),
					)
				);
			}

			$result = $this->campaign_manager->activate( $campaign_id );

			if ( is_wp_error( $result ) ) {
				if ( $this->logger ) {
					$this->logger->error(
						'Failed to activate campaign',
						array(
							'campaign_id' => $campaign_id,
							'error'       => $result->get_error_message(),
						)
					);
				}
			} elseif ( $this->logger ) {
					$this->logger->info(
						'Campaign activated successfully',
						array(
							'campaign_id' => $campaign_id,
						)
					);
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Exception during campaign activation',
					array(
						'campaign_id' => $campaign_id,
						'error'       => $e->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Handle campaign deactivation action.
	 *
	 * Called by ActionScheduler at exact scheduled time.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function handle_deactivation_event( int $campaign_id ): void {
		try {
			if ( $this->logger ) {
				$this->logger->info(
					'Processing scheduled campaign deactivation',
					array(
						'campaign_id' => $campaign_id,
						'time'        => gmdate( 'Y-m-d H:i:s' ),
					)
				);
			}

			$result = $this->campaign_manager->expire( $campaign_id );

			if ( is_wp_error( $result ) ) {
				if ( $this->logger ) {
					$this->logger->error(
						'Failed to deactivate campaign',
						array(
							'campaign_id' => $campaign_id,
							'error'       => $result->get_error_message(),
						)
					);
				}
			} elseif ( $this->logger ) {
					$this->logger->info(
						'Campaign deactivated successfully',
						array(
							'campaign_id' => $campaign_id,
						)
					);
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Exception during campaign deactivation',
					array(
						'campaign_id' => $campaign_id,
						'error'       => $e->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Run safety check for missed events.
	 *
	 * This is a fallback that catches any campaigns that should have activated/deactivated
	 * but missed their scheduled event (e.g., due to server downtime).
	 *
	 * @since    1.0.0
	 * @return   array    Results of the safety check.
	 */
	public function run_safety_check(): array {
		try {
			if ( $this->logger ) {
				$this->logger->debug( 'Running campaign event safety check' );
			}

			$results = $this->campaign_manager->process_scheduled_campaigns();

			if ( $this->logger && ( $results['activated'] > 0 || $results['expired'] > 0 ) ) {
				$this->logger->info(
					'Safety check caught missed events',
					array(
						'activated' => $results['activated'],
						'expired'   => $results['expired'],
					)
				);
			}

			return $results;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Safety check failed',
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			return array(
				'activated' => 0,
				'expired'   => 0,
				'errors'    => array( $e->getMessage() ),
			);
		}
	}
}
