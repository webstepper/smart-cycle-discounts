<?php
/**
 * Campaign List Controller
 *
 * Handles the campaign list display.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/campaigns
 * @since      1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign List Controller Class
 *
 * @since      1.0.0
 */
class SCD_Campaign_List_Controller extends SCD_Abstract_Campaign_Controller {

	/**
	 * List table instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaigns_List_Table|null
	 */
	private ?SCD_Campaigns_List_Table $list_table = null;

	/**
	 * Wizard state service.
	 *
	 * @since    1.0.0
	 * @var      SCD_Wizard_State_Service
	 */
	private SCD_Wizard_State_Service $wizard_state_service;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Feature_Gate
	 */
	private SCD_Feature_Gate $feature_gate;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager         $campaign_manager      Campaign manager.
	 * @param    SCD_Admin_Capability_Manager $capability_manager    Capability manager.
	 * @param    SCD_Logger                   $logger                Logger instance.
	 * @param    SCD_Wizard_State_Service     $wizard_state_service  Wizard state service.
	 * @param    SCD_Feature_Gate             $feature_gate          Feature gate.
	 */
	public function __construct(
		SCD_Campaign_Manager $campaign_manager,
		SCD_Admin_Capability_Manager $capability_manager,
		SCD_Logger $logger,
		SCD_Wizard_State_Service $wizard_state_service,
		SCD_Feature_Gate $feature_gate
	) {
		parent::__construct( $campaign_manager, $capability_manager, $logger );
		$this->wizard_state_service = $wizard_state_service;
		$this->feature_gate         = $feature_gate;
	}

	/**
	 * Handle the list display.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle(): void {
		if ( ! $this->check_capability( 'scd_view_campaigns' ) ) {
			wp_die( __( 'You do not have permission to view campaigns.', 'smart-cycle-discounts' ) );
		}

		// Handle discard draft action
		if ( isset( $_GET['action'] ) && 'discard_draft' === $_GET['action'] ) {
			$this->handle_discard_draft();
			return;
		}

		// Initialize list table
		$this->init_list_table();

		// Handle bulk actions
		$this->handle_bulk_actions();

		// Enqueue required scripts
		$this->enqueue_scripts();

		// Render the page
		$this->render();
	}

	/**
	 * Initialize list table.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function init_list_table(): void {
		if ( ! class_exists( 'SCD_Campaigns_List_Table' ) ) {
			require_once SCD_INCLUDES_DIR . 'admin/components/class-campaigns-list-table.php';
		}

		$this->list_table = new SCD_Campaigns_List_Table( $this->campaign_manager, $this->capability_manager );
		$this->list_table->prepare_items();
	}

	/**
	 * Handle bulk actions.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_bulk_actions(): void {
		if ( ! $this->list_table ) {
			return;
		}

		$action = $this->list_table->current_action();
		if ( ! $action ) {
			return;
		}

		// Check nonce
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'bulk-campaigns' ) ) {
			return;
		}

		$campaign_ids = array_map( 'absint', $_REQUEST['campaign'] ?? array() );
		if ( empty( $campaign_ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'delete':
				$this->handle_bulk_delete( $campaign_ids );
				break;
			case 'activate':
				$this->handle_bulk_activate( $campaign_ids );
				break;
			case 'deactivate':
				$this->handle_bulk_deactivate( $campaign_ids );
				break;
		}
	}

	/**
	 * Handle bulk delete.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Campaign IDs.
	 * @return   void
	 */
	private function handle_bulk_delete( array $campaign_ids ): void {
		if ( ! $this->check_capability( 'scd_delete_campaigns' ) ) {
			return;
		}

		$deleted = 0;
		foreach ( $campaign_ids as $id ) {
			if ( $this->campaign_manager->delete_campaign( $id ) ) {
				++$deleted;
			}
		}

		$this->add_notice(
			sprintf(
				_n(
					'%d campaign deleted.',
					'%d campaigns deleted.',
					$deleted,
					'smart-cycle-discounts'
				),
				$deleted
			),
			'success'
		);
	}

	/**
	 * Handle bulk activate.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Campaign IDs.
	 * @return   void
	 */
	private function handle_bulk_activate( array $campaign_ids ): void {
		if ( ! $this->check_capability( 'scd_edit_campaigns' ) ) {
			return;
		}

		$activated = 0;
		foreach ( $campaign_ids as $id ) {
			if ( $this->campaign_manager->activate_campaign( $id ) ) {
				++$activated;
			}
		}

		$this->add_notice(
			sprintf(
				_n(
					'%d campaign activated.',
					'%d campaigns activated.',
					$activated,
					'smart-cycle-discounts'
				),
				$activated
			),
			'success'
		);
	}

	/**
	 * Handle bulk deactivate.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Campaign IDs.
	 * @return   void
	 */
	private function handle_bulk_deactivate( array $campaign_ids ): void {
		if ( ! $this->check_capability( 'scd_edit_campaigns' ) ) {
			return;
		}

		$deactivated = 0;
		foreach ( $campaign_ids as $id ) {
			if ( $this->campaign_manager->deactivate_campaign( $id ) ) {
				++$deactivated;
			}
		}

		$this->add_notice(
			sprintf(
				_n(
					'%d campaign deactivated.',
					'%d campaigns deactivated.',
					$deactivated,
					'smart-cycle-discounts'
				),
				$deactivated
			),
			'success'
		);
	}

	/**
	 * Handle discard draft action.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_discard_draft(): void {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'scd_discard_draft' ) ) {
			wp_die( __( 'Security check failed.', 'smart-cycle-discounts' ) );
		}

		// Check capability
		if ( ! $this->check_capability( 'scd_create_campaigns' ) ) {
			wp_die( __( 'You do not have permission to discard drafts.', 'smart-cycle-discounts' ) );
		}

		// Discard the draft
		$this->wizard_state_service->clear_session();

		// Check if we have a redirect URL
		if ( isset( $_GET['redirect'] ) ) {
			$redirect_url = urldecode( $_GET['redirect'] );
			// Validate the redirect URL is within our admin area
			if ( strpos( $redirect_url, admin_url() ) === 0 ) {
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		// Default redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'scd-campaigns',
					'message' => 'draft_discarded',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue required scripts for the campaigns page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function enqueue_scripts(): void {
		// Ensure UI utilities are loaded for modal functionality
		wp_enqueue_script( 'scd-shared-ui' );
	}

	/**
	 * Render the list page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render(): void {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html__( 'Campaigns', 'smart-cycle-discounts' ); ?>
			</h1>
			
			<?php
			// Display success messages
			if ( isset( $_GET['message'] ) ) {
				$message  = sanitize_text_field( $_GET['message'] );
				$messages = array(
					'draft_discarded' => __( 'Draft campaign has been discarded.', 'smart-cycle-discounts' ),
					'activated'       => __( 'Campaign activated successfully.', 'smart-cycle-discounts' ),
					'deactivated'     => __( 'Campaign deactivated successfully.', 'smart-cycle-discounts' ),
					'deleted'         => __( 'Campaign moved to trash successfully.', 'smart-cycle-discounts' ),
				);
				if ( isset( $messages[ $message ] ) ) {
					?>
					<div class="notice notice-success is-dismissible">
						<p><?php echo esc_html( $messages[ $message ] ); ?></p>
					</div>
					<?php
				}
			}

			// Display error messages
			if ( isset( $_GET['error'] ) ) {
				$error  = sanitize_text_field( $_GET['error'] );
				$errors = array(
					'activate_failed'   => __( 'Failed to activate campaign.', 'smart-cycle-discounts' ),
					'deactivate_failed' => __( 'Failed to deactivate campaign.', 'smart-cycle-discounts' ),
					'delete_failed'     => __( 'Failed to delete campaign.', 'smart-cycle-discounts' ),
				);
				if ( isset( $errors[ $error ] ) ) {
					?>
					<div class="notice notice-error is-dismissible">
						<p><?php echo esc_html( $errors[ $error ] ); ?></p>
					</div>
					<?php
				}
			}

			// Display campaign count for free users
			$this->render_campaign_count();
			?>

			<?php
			// Check if viewing trash
			$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === $_REQUEST['status'];

			// Initialize draft info for later use
			$draft_info = null;
			?>

			<?php if ( ! $viewing_trash && $this->check_capability( 'scd_create_campaigns' ) ) : ?>
				<?php
				// Check for draft
				$draft_info = $this->wizard_state_service->get_draft_info();
				$has_draft  = $draft_info && empty( $draft_info['is_expired'] );

				// Prepare data for JavaScript
				if ( $has_draft ) {
					$session_type  = 'draft';
					$campaign_name = $draft_info['campaign_name'] ?? '';
					$last_activity = ! empty( $draft_info['last_updated'] ) ?
						human_time_diff( $draft_info['last_updated'], current_time( 'timestamp' ) ) : '';
				} else {
					$session_type  = 'none';
					$campaign_name = '';
					$last_activity = '';
				}
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=new' ) ); ?>"
					class="page-title-action scd-new-campaign-btn"
					data-has-session="<?php echo $has_draft ? 'true' : 'false'; ?>"
					data-session-type="<?php echo esc_attr( $session_type ); ?>"
					data-campaign-name="<?php echo esc_attr( $campaign_name ); ?>"
					data-last-activity="<?php echo esc_attr( $last_activity ); ?>">
					<?php echo esc_html__( 'Add New Campaign', 'smart-cycle-discounts' ); ?>
				</a>

				<?php if ( $has_draft ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=continue' ) ); ?>"
						class="page-title-action button-secondary">
						<span class="dashicons dashicons-edit"></span>
						<?php echo esc_html__( 'Continue Draft', 'smart-cycle-discounts' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php
			// Display draft notice if exists
			if ( $draft_info && $this->check_capability( 'scd_create_campaigns' ) ) {
				$this->render_draft_notice( $draft_info );
			}
			?>

			<?php $this->list_table->views(); ?>

			<form method="post">
				<?php
				$this->list_table->search_box( __( 'Search Campaigns', 'smart-cycle-discounts' ), 'campaign' );
				$this->list_table->display();
				?>
			</form>

			<?php
			// Output quick edit inline form template
			if ( method_exists( $this->list_table, 'inline_edit' ) ) {
				$this->list_table->inline_edit();
			}
			?>
		</div>
		
		<?php
		// Render draft conflict modal if user can create campaigns
		if ( $this->check_capability( 'scd_create_campaigns' ) ) {
			$this->render_draft_conflict_modal();
			$this->enqueue_modal_scripts();
		}
	}

	/**
	 * Render draft campaign notice.
	 *
	 * @since    1.0.0
	 * @param    array $draft_info    Draft campaign information.
	 * @return   void
	 */
	private function render_draft_notice( array $draft_info ): void {
		$nonce = wp_create_nonce( 'scd_discard_draft' );
		?>
		<div class="notice notice-info scd-draft-notice">
			<p>
				<strong><?php echo esc_html__( 'Draft Campaign Found', 'smart-cycle-discounts' ); ?></strong>
				<?php echo esc_html__( 'You have an incomplete campaign:', 'smart-cycle-discounts' ); ?>
				<strong><?php echo esc_html( $draft_info['campaign_name'] ); ?></strong>
				
				<?php if ( ! empty( $draft_info['last_updated'] ) ) : ?>
					<span class="scd-draft-meta">
						<?php
						printf(
							/* translators: %s: Human readable time difference */
							esc_html__( 'Last updated %s ago', 'smart-cycle-discounts' ),
							human_time_diff( $draft_info['last_updated'], current_time( 'timestamp' ) )
						);
						?>
					</span>
				<?php endif; ?>
				
				<span class="scd-draft-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=continue' ) ); ?>" 
						class="button button-small">
						<?php echo esc_html__( 'Continue Editing', 'smart-cycle-discounts' ); ?>
					</a>
					
					<a href="
					<?php
					echo esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=scd-campaigns&action=discard_draft' ),
							'scd_discard_draft',
							'_wpnonce'
						)
					);
					?>
					" 
						class="button-link-delete"
						onclick="return confirm('<?php echo esc_attr__( 'Are you sure you want to discard this draft campaign?', 'smart-cycle-discounts' ); ?>');">
						<?php echo esc_html__( 'Discard Draft', 'smart-cycle-discounts' ); ?>
					</a>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render draft conflict modal.
	 *
	 * Handles unsaved draft campaigns.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_draft_conflict_modal(): void {
		// Load modal component
		require_once SCD_INCLUDES_DIR . 'admin/components/class-modal-component.php';

		// Create modal configuration
		$modal_config = array(
			'id'             => 'scd-draft-conflict-modal',
			'title'          => '<span id="scd-modal-title">' . esc_html__( 'Draft Campaign Exists', 'smart-cycle-discounts' ) . '</span>',
			'content'        => '<div id="scd-modal-message"></div>',
			'icon'           => 'dashicons-warning',
			'classes'        => array( 'scd-modal__icon--warning' ),
			'buttons'        => array(
				array(
					'id'     => 'scd-save-and-new',
					'text'   => __( 'Save as Draft & Create New', 'smart-cycle-discounts' ),
					'class'  => 'button button-secondary scd-save-btn',
					'action' => 'save-new',
					'style'  => 'display:none;',
				),
				array(
					'id'     => 'scd-discard-and-new',
					'text'   => __( 'Discard & Create New', 'smart-cycle-discounts' ),
					'class'  => 'button button-secondary scd-discard-btn',
					'action' => 'discard-new',
					'style'  => 'display:none;',
				),
				array(
					'text'   => __( 'Cancel', 'smart-cycle-discounts' ),
					'class'  => 'button scd-modal-cancel',
					'action' => 'close',
				),
			),
			'escape_content' => false, // Already escaped above
		);

		// Create and render modal
		$modal = new SCD_Modal_Component( $modal_config );
		$modal->render();
	}

	/**
	 * Render campaign count display for free users.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_campaign_count(): void {
		// Only show for free users
		if ( $this->feature_gate->is_premium() ) {
			return;
		}

		// Get current campaign count (exclude deleted campaigns)
		$repository = $this->campaign_manager->get_repository();
		if ( ! $repository ) {
			return;
		}

		$current_count = $repository->count(
			array(
				'status__not' => 'deleted',
			)
		);

		$campaign_limit = $this->feature_gate->get_campaign_limit();
		$upgrade_url    = function_exists( 'scd_get_upgrade_url' ) ? scd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

		// Determine styling based on usage
		$percentage_used = ( $campaign_limit > 0 ) ? ( $current_count / $campaign_limit ) * 100 : 0;
		$notice_class    = 'notice-info';
		if ( $percentage_used >= 100 ) {
			$notice_class = 'notice-warning';
		} elseif ( $percentage_used >= 67 ) {
			$notice_class = 'notice-info';
		}

		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> inline scd-campaign-limit-notice">
			<p>
				<span class="dashicons dashicons-info"></span>
				<strong>
					<?php
					printf(
						/* translators: 1: current count, 2: limit */
						esc_html__( 'Using %1$d of %2$d campaigns', 'smart-cycle-discounts' ),
						$current_count,
						$campaign_limit
					);
					?>
				</strong>

				<?php if ( $current_count >= $campaign_limit ) : ?>
					<span class="scd-limit-message">
						<?php esc_html_e( '- You\'ve reached your campaign limit.', 'smart-cycle-discounts' ); ?>
						<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-small button-primary">
							<?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
						</a>
					</span>
				<?php else : ?>
					<span class="scd-upgrade-link">
						<?php esc_html_e( '-', 'smart-cycle-discounts' ); ?>
						<a href="<?php echo esc_url( $upgrade_url ); ?>">
							<?php esc_html_e( 'Upgrade for unlimited campaigns', 'smart-cycle-discounts' ); ?>
						</a>
					</span>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue modal scripts and localization.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function enqueue_modal_scripts(): void {
		// Enqueue campaign list modals script
		wp_enqueue_script( 'scd-campaign-list-modals' );

		// Localize script with translations and URLs
		wp_localize_script(
			'scd-campaign-list-modals',
			'scdCampaignListL10n',
			array(
				'unsavedDraftText'    => esc_html__( 'You have an unsaved draft campaign:', 'smart-cycle-discounts' ),
				'whatToDoText'        => esc_html__( 'What would you like to do?', 'smart-cycle-discounts' ),
				'savingDraftText'     => esc_html__( 'Saving Draft...', 'smart-cycle-discounts' ),
				'discardingText'      => esc_html__( 'Discarding...', 'smart-cycle-discounts' ),
				'saveDraftButtonText' => esc_html__( 'Save Draft & Create New', 'smart-cycle-discounts' ),
				'saveDraftErrorText'  => esc_html__( 'Failed to save draft. Please try again.', 'smart-cycle-discounts' ),
				'adminUrl'            => admin_url( 'admin.php' ),
				'nonce'               => wp_create_nonce( 'scd_wizard_nonce' ),
			)
		);
	}
}