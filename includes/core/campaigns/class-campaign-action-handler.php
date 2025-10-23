<?php
/**
 * Campaign Action Handler
 *
 * Handles campaign actions (delete, duplicate, activate, etc).
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/campaigns
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
class SCD_Campaign_Action_Handler extends SCD_Abstract_Campaign_Controller {

    /**
     * Handle delete action.
     *
     * @since    1.0.0
     * @return   void
     */
    public function handle_delete() {
        // Get campaign ID
        $campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        if ( ! $campaign_id ) {
            $this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'scd_delete_campaign_' . $campaign_id ) ) {
            $this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check capability
        if ( ! $this->check_capability( 'scd_delete_campaigns' ) ) {
            $this->redirect_with_error( __( 'You do not have permission to delete campaigns.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check campaign ownership
        if ( ! $this->check_campaign_ownership( $campaign_id ) ) {
            $this->redirect_with_error( __( 'You can only delete campaigns you created.', 'smart-cycle-discounts' ) );
            return;
        }

        // Delete campaign (soft delete - moves to trash)
        $result = $this->campaign_manager->delete( $campaign_id );
        if ( ! is_wp_error( $result ) && $result ) {
            $this->redirect_with_message(
                admin_url( 'admin.php?page=scd-campaigns' ),
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
        // Get campaign ID
        $campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        if ( ! $campaign_id ) {
            $this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'duplicate_campaign_' . $campaign_id ) ) {
            $this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check capability
        if ( ! $this->check_capability( 'scd_create_campaigns' ) ) {
            $this->redirect_with_error( __( 'You do not have permission to duplicate campaigns.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check campaign ownership (user can only duplicate campaigns they created)
        if ( ! $this->check_campaign_ownership( $campaign_id ) ) {
            $this->redirect_with_error( __( 'You can only duplicate campaigns you created.', 'smart-cycle-discounts' ) );
            return;
        }

        try {
            // Duplicate campaign
            $new_campaign = $this->campaign_manager->duplicate( $campaign_id );

            if ( ! is_wp_error( $new_campaign ) && $new_campaign ) {
                $this->redirect_with_message(
                    admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $new_campaign->get_id() ),
                    __( 'Campaign duplicated successfully.', 'smart-cycle-discounts' ),
                    'success'
                );
            } else {
                $error_message = is_wp_error( $new_campaign ) ? $new_campaign->get_error_message() : __( 'Failed to duplicate campaign.', 'smart-cycle-discounts' );
                throw new Exception( $error_message );
            }
        } catch ( Exception $e ) {
            $this->logger->error( 'Campaign duplication failed', array(
                'campaign_id' => $campaign_id,
                'error'       => $e->getMessage()
            ) );

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
        $this->handle_status_change( 'active', 'scd_activate_campaigns' );
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
        $this->handle_status_change( 'paused', 'scd_activate_campaigns' );
    }

    /**
     * Handle status change.
     *
     * @since    1.0.0
     * @param    string    $new_status    New status.
     * @param    string    $capability    Required capability.
     * @return   void
     */
    private function handle_status_change( $new_status, $capability ) {
        // Get campaign ID
        $campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        if ( ! $campaign_id ) {
            $this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'campaign_status_' . $campaign_id ) ) {
            $this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check capability
        if ( ! $this->check_capability( $capability ) ) {
            $this->redirect_with_error( __( 'You do not have permission to change campaign status.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check campaign ownership
        if ( ! $this->check_campaign_ownership( $campaign_id ) ) {
            $this->redirect_with_error( __( 'You can only modify campaigns you created.', 'smart-cycle-discounts' ) );
            return;
        }

        // Update status using appropriate method
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

            // Add expiration warning for paused campaigns with end dates
            if ( 'paused' === $new_status ) {
                $campaign = $this->campaign_manager->find( $campaign_id );
                if ( $campaign ) {
                    $ends_at = $campaign->get_ends_at();
                    if ( $ends_at ) {
                        // Format the end date in WordPress timezone for display
                        $end_date_display = wp_date( 'F j, Y \a\t g:i A', $ends_at->getTimestamp() );
                        $message .= ' ' . sprintf(
                            __( 'Note: This campaign will still expire on %s.', 'smart-cycle-discounts' ),
                            $end_date_display
                        );
                    }
                }
            }

            $this->redirect_with_message(
                wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=scd-campaigns' ),
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
        // Get campaign ID
        $campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        if ( ! $campaign_id ) {
            $this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'scd_restore_campaign_' . $campaign_id ) ) {
            $this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check capability
        if ( ! $this->check_capability( 'scd_edit_campaigns' ) ) {
            $this->redirect_with_error( __( 'You do not have permission to restore campaigns.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check campaign ownership (include trashed campaigns)
        if ( ! $this->check_campaign_ownership( $campaign_id, true ) ) {
            $this->redirect_with_error( __( 'You can only restore campaigns you created.', 'smart-cycle-discounts' ) );
            return;
        }

        // Restore campaign
        $repository = $this->campaign_manager->get_repository();
        $result = $repository->restore( $campaign_id );

        if ( $result ) {
            $this->redirect_with_message(
                admin_url( 'admin.php?page=scd-campaigns' ),
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
        // Get campaign ID
        $campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        if ( ! $campaign_id ) {
            $this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'scd_delete_permanently_' . $campaign_id ) ) {
            $this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check capability
        if ( ! $this->check_capability( 'scd_delete_campaigns' ) ) {
            $this->redirect_with_error( __( 'You do not have permission to delete campaigns.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check campaign ownership (include trashed campaigns)
        if ( ! $this->check_campaign_ownership( $campaign_id, true ) ) {
            $this->redirect_with_error( __( 'You can only delete campaigns you created.', 'smart-cycle-discounts' ) );
            return;
        }

        // Permanently delete campaign
        $repository = $this->campaign_manager->get_repository();
        $result = $repository->force_delete( $campaign_id );

        if ( $result ) {
            $this->redirect_with_message(
                admin_url( 'admin.php?page=scd-campaigns&status=trash' ),
                __( 'Campaign permanently deleted.', 'smart-cycle-discounts' ),
                'success'
            );
        } else {
            $this->redirect_with_error( __( 'Failed to delete campaign permanently.', 'smart-cycle-discounts' ) );
        }
    }

    /**
     * Handle stop recurring action.
     *
     * @since    1.0.0
     * @return   void
     */
    public function handle_stop_recurring() {
        // Get campaign ID
        $campaign_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        if ( ! $campaign_id ) {
            $this->redirect_with_error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'scd_stop_recurring' ) ) {
            $this->redirect_with_error( __( 'Security check failed.', 'smart-cycle-discounts' ) );
            return;
        }

        // Check capability
        if ( ! $this->check_capability( 'scd_edit_campaigns' ) ) {
            $this->redirect_with_error( __( 'You do not have permission to edit campaigns.', 'smart-cycle-discounts' ) );
            return;
        }

        // Get container and stop recurring
        $container = $this->get_container();
        if ( $container && $container->has( 'database_manager' ) ) {
            $db = $container->get( 'database_manager' );

            $result = $db->update(
                'campaign_recurring',
                array( 'is_active' => 0 ),
                array(
                    'campaign_id'        => $campaign_id,
                    'parent_campaign_id' => 0
                )
            );

            if ( false !== $result ) {
                $this->redirect_with_message(
                    admin_url( 'admin.php?page=scd-campaigns' ),
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
     * @param    string    $message    Error message.
     * @return   void
     */
    protected function redirect_with_error( $message ) {
        $this->redirect_with_message(
            wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=scd-campaigns' ),
            $message,
            'error'
        );
    }

    /**
     * Check if current user owns the campaign.
     *
     * @since    1.0.0
     * @param    int     $campaign_id       Campaign ID.
     * @param    bool    $include_trashed   Include trashed campaigns (needed for restore/permanent delete).
     * @return   bool                       True if user owns campaign or is admin.
     */
    protected function check_campaign_ownership( $campaign_id, $include_trashed = false ) {
        $current_user_id = get_current_user_id();

        // Admins can modify any campaign
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Get campaign to check ownership (include trashed if specified)
        $campaign = $this->campaign_manager->find( $campaign_id, $include_trashed );
        if ( ! $campaign ) {
            return false;
        }

        // Check if current user created the campaign
        return $campaign->get_created_by() === $current_user_id;
    }
}