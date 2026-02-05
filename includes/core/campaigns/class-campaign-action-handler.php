<?php
/**
 * Campaign Action Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-action-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

/**
 * Campaign Action Handler Class
 *
 * @since      1.0.0
 */
class WSSCD_Campaign_Action_Handler extends WSSCD_Abstract_Campaign_Controller {

	/**
	 * Handle delete action.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_delete() {
		// SECURITY: ID is read first to construct the dynamic nonce action name.
		// Nonce verification happens at line 42 using wp_verify_nonce() with action 'wsscd-campaign-action-delete-{id}'.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID required to build nonce action; nonce verified at line 42 below.
		$campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $campaign_id ) {
			$this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '', 'wsscd-campaign-action-delete-' . $campaign_id ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( 'wsscd_delete_campaigns' ) ) {
			$this->redirect_with_error( __( 'You do not have permission to delete campaigns.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_campaign_ownership( $campaign_id ) ) {
			$this->redirect_with_error( __( 'You can only delete campaigns you created.', 'smart-cycle-discounts' ) );
			return;
		}

		$result = $this->campaign_manager->delete( $campaign_id );
		if ( ! is_wp_error( $result ) && $result ) {
			$this->redirect_with_message(
				admin_url( 'admin.php?page=wsscd-campaigns' ),
				__( 'Campaign moved to trash.', 'smart-cycle-discounts' ),
				'success'
			);
		} else {
			$this->redirect_with_error( __( 'Failed to move campaign to trash.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle duplicate action.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_duplicate() {
		// SECURITY: ID is read first to construct the dynamic nonce action name.
		// Nonce verification happens at line 84 using wp_verify_nonce() with action 'duplicate_campaign_{id}'.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID required to build nonce action; nonce verified at line 84 below.
		$campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $campaign_id ) {
			$this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '', 'duplicate_campaign_' . $campaign_id ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( 'wsscd_create_campaigns' ) ) {
			$this->redirect_with_error( __( 'You do not have permission to duplicate campaigns.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_campaign_ownership( $campaign_id ) ) {
			$this->redirect_with_error( __( 'You can only duplicate campaigns you created.', 'smart-cycle-discounts' ) );
			return;
		}

		try {
			// Duplicate campaign
			$new_campaign = $this->campaign_manager->duplicate( $campaign_id );

			if ( ! is_wp_error( $new_campaign ) && $new_campaign ) {
				$this->redirect_with_message(
					admin_url( 'admin.php?page=wsscd-campaigns' ),
					sprintf(
						/* translators: %s: name of the duplicated campaign */
						__( 'Campaign duplicated successfully as "%s". Click Edit to configure it.', 'smart-cycle-discounts' ),
						esc_html( $new_campaign->get_name() )
					),
					'success'
				);
			} else {
				$error_message = is_wp_error( $new_campaign ) ? $new_campaign->get_error_message() : __( 'Failed to duplicate campaign.', 'smart-cycle-discounts' );
				throw new Exception( $error_message );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign duplication failed',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			$this->redirect_with_error( $e->getMessage() );
		}
	}

	/**
	 * Handle activate action.
	 *
	 * Activates a campaign, making it live and applying discounts to products.
	 * Can activate campaigns from draft, scheduled, or paused status.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_activate() {
		$this->handle_status_change( 'active', 'wsscd_activate_campaigns' );
	}

	/**
	 * Handle deactivate action.
	 *
	 * IMPORTANT: "Deactivate" means PAUSE, not expire.
	 * - Paused campaigns can be reactivated without editing
	 * - Expired campaigns require editing before reactivation
	 * - This is a manual user action (not time-based like expiration)
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_deactivate() {
		$this->handle_status_change( 'paused', 'wsscd_activate_campaigns' );
	}

	/**
	 * Handle status change.
	 *
	 * @since    1.0.0
	 * @param    string $new_status    New status.
	 * @param    string $capability    Required capability.
	 * @return   void
	 */
	private function handle_status_change( $new_status, $capability ) {
		// SECURITY: ID is read first to construct the dynamic nonce action name.
		// Nonce verification happens at line 180 using wp_verify_nonce() with action 'wsscd-campaign-action-{action}-{id}'.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID required to build nonce action; nonce verified at line 180 below.
		$campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $campaign_id ) {
			$this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Determine action for nonce
		$action      = ( 'active' === $new_status ) ? 'activate' : 'deactivate';
		$nonce_name  = 'wsscd-campaign-action-' . $action . '-' . $campaign_id;
		$nonce_value = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce_value, $nonce_name ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( $capability ) ) {
			$this->redirect_with_error( __( 'You do not have permission to change campaign status.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_campaign_ownership( $campaign_id ) ) {
			$this->redirect_with_error( __( 'You can only modify campaigns you created.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( 'active' === $new_status ) {
			$result = $this->campaign_manager->activate( $campaign_id );
		} elseif ( 'paused' === $new_status ) {
			$result = $this->campaign_manager->pause( $campaign_id );
		} else {
			$result = $this->campaign_manager->update( $campaign_id, array( 'status' => $new_status ) );
		}

		if ( ! is_wp_error( $result ) && $result ) {
			$message = 'active' === $new_status
				? __( 'Campaign activated successfully.', 'smart-cycle-discounts' )
				: __( 'Campaign deactivated successfully.', 'smart-cycle-discounts' );

			// Add helpful information about campaign duration
			if ( 'active' === $new_status ) {
				$campaign = $this->campaign_manager->find( $campaign_id );
				if ( $campaign ) {
					$ends_at = $campaign->get_ends_at();
					if ( $ends_at ) {
						$now                  = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
						$diff                 = $ends_at->getTimestamp() - $now->getTimestamp();
						$hours_until_expiry   = $diff / 3600;
						$end_date_display     = wp_date( 'F j, Y \a\t g:i A', $ends_at->getTimestamp() );

						// Warn if expires within 24 hours
						if ( $hours_until_expiry < 24 && $hours_until_expiry > 0 ) {
							$message .= ' ' . sprintf(
								/* translators: %1$s: formatted end date, %2$s: human readable time difference */
								__( 'Note: This campaign will expire soon on %1$s (in %2$s).', 'smart-cycle-discounts' ),
								$end_date_display,
								human_time_diff( $now->getTimestamp(), $ends_at->getTimestamp() )
							);
						} else {
							$message .= ' ' . sprintf(
								/* translators: %s: formatted end date */
								__( 'Campaign will run until %s.', 'smart-cycle-discounts' ),
								$end_date_display
							);
						}
					}
				}
			}

			if ( 'paused' === $new_status ) {
				$campaign = $this->campaign_manager->find( $campaign_id );
				if ( $campaign ) {
					$ends_at = $campaign->get_ends_at();
					if ( $ends_at ) {
						$end_date_display = wp_date( 'F j, Y \a\t g:i A', $ends_at->getTimestamp() );
						$message         .= ' ' . sprintf(
							/* translators: %s: formatted expiration date */
							__( 'Note: This campaign will still expire on %s.', 'smart-cycle-discounts' ),
							$end_date_display
						);
					}
				}
			}

			$this->redirect_with_message(
				wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wsscd-campaigns' ),
				$message,
				'success'
			);
		} else {
			$error_message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Failed to update campaign status.', 'smart-cycle-discounts' );
			$this->redirect_with_error( $error_message );
		}
	}

	/**
	 * Handle restore action.
	 *
	 * WordPress trash system: Restore campaign from trash.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_restore() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID needed to construct nonce action for verification below.
		$campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $campaign_id ) {
			$this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '', 'wsscd_restore_campaign_' . $campaign_id ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( 'wsscd_edit_campaigns' ) ) {
			$this->redirect_with_error( __( 'You do not have permission to restore campaigns.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_campaign_ownership( $campaign_id, true ) ) {
			$this->redirect_with_error( __( 'You can only restore campaigns you created.', 'smart-cycle-discounts' ) );
			return;
		}

		// Restore campaign
		$repository = $this->campaign_manager->get_repository();
		$result     = $repository->restore( $campaign_id );

		if ( $result ) {
			$this->redirect_with_message(
				admin_url( 'admin.php?page=wsscd-campaigns' ),
				__( 'Campaign restored successfully.', 'smart-cycle-discounts' ),
				'success'
			);
		} else {
			$this->redirect_with_error( __( 'Failed to restore campaign.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle permanent delete action.
	 *
	 * WordPress trash system: Permanently delete campaign (cannot be undone).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_delete_permanently() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID needed to construct nonce action for verification below.
		$campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $campaign_id ) {
			$this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '', 'wsscd_delete_permanently_' . $campaign_id ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( 'wsscd_delete_campaigns' ) ) {
			$this->redirect_with_error( __( 'You do not have permission to delete campaigns.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_campaign_ownership( $campaign_id, true ) ) {
			$this->redirect_with_error( __( 'You can only delete campaigns you created.', 'smart-cycle-discounts' ) );
			return;
		}

		// Permanently delete campaign
		$repository = $this->campaign_manager->get_repository();
		$result     = $repository->force_delete( $campaign_id );

		if ( $result ) {
			$this->redirect_with_message(
				admin_url( 'admin.php?page=wsscd-campaigns&status=trash' ),
				__( 'Campaign permanently deleted.', 'smart-cycle-discounts' ),
				'success'
			);
		} else {
			$this->redirect_with_error( __( 'Failed to delete campaign permanently.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle empty trash action.
	 *
	 * Permanently deletes all campaigns in the trash.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_empty_trash() {
		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '', 'wsscd_empty_trash' ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( 'wsscd_delete_campaigns' ) ) {
			$this->redirect_with_error( __( 'You do not have permission to empty trash.', 'smart-cycle-discounts' ) );
			return;
		}

		$repository        = $this->campaign_manager->get_repository();
		$trashed_campaigns = $repository->find_trashed( array() );

		if ( empty( $trashed_campaigns ) ) {
			$this->redirect_with_message(
				admin_url( 'admin.php?page=wsscd-campaigns&status=trash' ),
				__( 'Trash is already empty.', 'smart-cycle-discounts' ),
				'info'
			);
			return;
		}

		$deleted_count = 0;
		$errors        = array();

		foreach ( $trashed_campaigns as $campaign ) {
			$campaign_id = $campaign->get_id();

			if ( ! $this->check_campaign_ownership( $campaign_id, true ) ) {
				continue; // Skip campaigns user doesn't own
			}

			$result = $repository->force_delete( $campaign_id );

			if ( $result ) {
				++$deleted_count;
			} else {
				$errors[] = sprintf(
					/* translators: %d: campaign ID */
					__( 'Failed to delete campaign ID %d', 'smart-cycle-discounts' ),
					$campaign_id
				);
			}
		}

		// Redirect with appropriate message
		if ( $deleted_count > 0 ) {
			$message = sprintf(
				/* translators: %d: number of campaigns permanently deleted */
				_n(
					'Trash emptied: %d campaign permanently deleted.',
					'Trash emptied: %d campaigns permanently deleted.',
					$deleted_count,
					'smart-cycle-discounts'
				),
				$deleted_count
			);

			if ( ! empty( $errors ) ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of campaigns that could not be deleted */
					_n(
						'However, %d campaign could not be deleted.',
						'However, %d campaigns could not be deleted.',
						count( $errors ),
						'smart-cycle-discounts'
					),
					count( $errors )
				);
			}

			$this->redirect_with_message(
				admin_url( 'admin.php?page=wsscd-campaigns&status=trash' ),
				$message,
				'success'
			);
		} else {
			$this->redirect_with_error( __( 'No campaigns were deleted. You may not have permission to delete some campaigns.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle stop recurring action.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_stop_recurring() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID needed to construct nonce action for verification below.
		$campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $campaign_id ) {
			$this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '', 'wsscd_stop_recurring' ) ) {
			$this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->check_capability( 'wsscd_edit_campaigns' ) ) {
			$this->redirect_with_error( __( 'You do not have permission to edit campaigns.', 'smart-cycle-discounts' ) );
			return;
		}

		$container = $this->get_container();
		if ( $container && $container->has( 'database_manager' ) ) {
			$db = $container->get( 'database_manager' );

			$result = $db->update(
				'campaign_recurring',
				array( 'is_active' => 0 ),
				array(
					'campaign_id'        => $campaign_id,
					'parent_campaign_id' => 0,
				)
			);

			if ( false !== $result ) {
				$this->redirect_with_message(
					admin_url( 'admin.php?page=wsscd-campaigns' ),
					__( 'Campaign recurring stopped successfully.', 'smart-cycle-discounts' ),
					'success'
				);
			} else {
				$this->redirect_with_error( __( 'Failed to stop recurring for this campaign.', 'smart-cycle-discounts' ) );
			}
		} else {
			$this->redirect_with_error( __( 'System error: Unable to access database.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Get service container.
	 *
	 * @since    1.0.0
	 * @return   object|null    Container instance or null.
	 */
	private function get_container() {
		if ( class_exists( 'SmartCycleDiscounts' ) ) {
			$plugin = SmartCycleDiscounts::get_instance();
			return $plugin->get_container();
		}
		return null;
	}

	/**
	 * Redirect with error message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @return   void
	 */
	protected function redirect_with_error( $message ) {
		$this->redirect_with_message(
			wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wsscd-campaigns' ),
			$message,
			'error'
		);
	}

	/**
	 * Check if current user owns the campaign.
	 *
	 * @since    1.0.0
	 * @param    int  $campaign_id       Campaign ID.
	 * @param    bool $include_trashed   Include trashed campaigns (needed for restore/permanent delete).
	 * @return   bool                       True if user owns campaign or is admin.
	 */
	protected function check_campaign_ownership( $campaign_id, $include_trashed = false ) {
		$current_user_id = get_current_user_id();

		// Admins can modify any campaign
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$campaign = $this->campaign_manager->find( $campaign_id, $include_trashed );
		if ( ! $campaign ) {
			return false;
		}

		return $campaign->get_created_by() === $current_user_id;
	}
}
