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
     * @param    SCD_Campaign_Manager          $campaign_manager      Campaign manager.
     * @param    SCD_Admin_Capability_Manager  $capability_manager    Capability manager.
     * @param    SCD_Logger                    $logger                Logger instance.
     * @param    SCD_Wizard_State_Service      $wizard_state_service  Wizard state service.
     * @param    SCD_Feature_Gate              $feature_gate          Feature gate.
     */
    public function __construct(
        SCD_Campaign_Manager $campaign_manager,
        SCD_Admin_Capability_Manager $capability_manager,
        SCD_Logger $logger,
        SCD_Wizard_State_Service $wizard_state_service,
        SCD_Feature_Gate $feature_gate
    ) {
        parent::__construct($campaign_manager, $capability_manager, $logger);
        $this->wizard_state_service = $wizard_state_service;
        $this->feature_gate = $feature_gate;
    }

    /**
     * Handle the list display.
     *
     * @since    1.0.0
     * @return   void
     */
    public function handle(): void {
        if (!$this->check_capability('scd_view_campaigns')) {
            wp_die(__('You do not have permission to view campaigns.', 'smart-cycle-discounts'));
        }

        // Handle discard draft action
        if (isset($_GET['action']) && 'discard_draft' === $_GET['action']) {
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
        if (!class_exists('SCD_Campaigns_List_Table')) {
            require_once SCD_INCLUDES_DIR . 'admin/components/class-campaigns-list-table.php';
        }
        
        $this->list_table = new SCD_Campaigns_List_Table($this->campaign_manager, $this->capability_manager);
        $this->list_table->prepare_items();
    }

    /**
     * Handle bulk actions.
     *
     * @since    1.0.0
     * @return   void
     */
    private function handle_bulk_actions(): void {
        if (!$this->list_table) {
            return;
        }

        $action = $this->list_table->current_action();
        if (!$action) {
            return;
        }

        // Check nonce
        if (!wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'bulk-campaigns')) {
            return;
        }

        $campaign_ids = array_map('absint', $_REQUEST['campaign'] ?? array());
        if (empty($campaign_ids)) {
            return;
        }

        switch ($action) {
            case 'delete':
                $this->handle_bulk_delete($campaign_ids);
                break;
            case 'activate':
                $this->handle_bulk_activate($campaign_ids);
                break;
            case 'deactivate':
                $this->handle_bulk_deactivate($campaign_ids);
                break;
        }
    }

    /**
     * Handle bulk delete.
     *
     * @since    1.0.0
     * @param    array    $campaign_ids    Campaign IDs.
     * @return   void
     */
    private function handle_bulk_delete(array $campaign_ids): void {
        if (!$this->check_capability('scd_delete_campaigns')) {
            return;
        }

        $deleted = 0;
        foreach ($campaign_ids as $id) {
            if ($this->campaign_manager->delete_campaign($id)) {
                $deleted++;
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
     * @param    array    $campaign_ids    Campaign IDs.
     * @return   void
     */
    private function handle_bulk_activate(array $campaign_ids): void {
        if (!$this->check_capability('scd_edit_campaigns')) {
            return;
        }

        $activated = 0;
        foreach ($campaign_ids as $id) {
            if ($this->campaign_manager->activate_campaign($id)) {
                $activated++;
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
     * @param    array    $campaign_ids    Campaign IDs.
     * @return   void
     */
    private function handle_bulk_deactivate(array $campaign_ids): void {
        if (!$this->check_capability('scd_edit_campaigns')) {
            return;
        }

        $deactivated = 0;
        foreach ($campaign_ids as $id) {
            if ($this->campaign_manager->deactivate_campaign($id)) {
                $deactivated++;
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
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'scd_discard_draft')) {
            wp_die(__('Security check failed.', 'smart-cycle-discounts'));
        }

        // Check capability
        if (!$this->check_capability('scd_create_campaigns')) {
            wp_die(__('You do not have permission to discard drafts.', 'smart-cycle-discounts'));
        }

        // Discard the draft
        $this->wizard_state_service->cleanup();

        // Check if we have a redirect URL
        if (isset($_GET['redirect'])) {
            $redirect_url = urldecode($_GET['redirect']);
            // Validate the redirect URL is within our admin area
            if (strpos($redirect_url, admin_url()) === 0) {
                wp_safe_redirect($redirect_url);
                exit;
            }
        }

        // Default redirect with success message
        wp_safe_redirect(add_query_arg(array(
            'page' => 'scd-campaigns',
            'message' => 'draft_discarded'
        ), admin_url('admin.php')));
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
        wp_enqueue_script('scd-shared-ui');
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
                <?php echo esc_html__('Campaigns', 'smart-cycle-discounts'); ?>
            </h1>
            
            <?php
            // Display success messages
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
                if ('draft_discarded' === $message) {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php echo esc_html__('Draft campaign has been discarded.', 'smart-cycle-discounts'); ?></p>
                    </div>
                    <?php
                }
            }

            // Display campaign count for free users
            $this->render_campaign_count();
            ?>

            <?php if ($this->check_capability('scd_create_campaigns')): ?>
                <?php
                // Check for draft campaign
                $draft_info = $this->wizard_state_service->get_draft_info();
                
                // Debug output (remove in production)
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    echo '<!-- Draft Debug: ';
                    echo 'Session ID: ' . ($this->wizard_state_service->get_session_id() ?: 'none') . ', ';
                    echo 'Has session: ' . ($this->wizard_state_service->has_session() ? 'yes' : 'no') . ', ';
                    echo 'Has draft: ' . ($this->wizard_state_service->has_draft() ? 'yes' : 'no') . ', ';
                    echo 'Draft info: ' . json_encode($draft_info);
                    echo ' -->';
                    
                    // Log session data for debugging
                }
                ?>
                
                <?php
                // Prepare draft data for modal
                $has_draft = $draft_info && empty($draft_info['is_expired']);
                $draft_name = $has_draft ? ($draft_info['campaign_name'] ?? '') : '';
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=scd-campaigns&action=wizard&intent=new')); ?>" 
                   class="page-title-action scd-new-campaign-btn"
                   data-has-draft="<?php echo $has_draft ? 'true' : 'false'; ?>"
                   data-draft-name="<?php echo esc_attr($draft_name); ?>">
                    <?php echo esc_html__('Add New Campaign', 'smart-cycle-discounts'); ?>
                </a>
                
                <?php if ($has_draft): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=scd-campaigns&action=wizard&intent=continue')); ?>" 
                       class="page-title-action button-secondary">
                        <span class="dashicons dashicons-edit"></span>
                        <?php echo esc_html__('Continue Draft', 'smart-cycle-discounts'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <hr class="wp-header-end">
            
            <?php 
            // Display draft notice if exists
            if ($draft_info && $this->check_capability('scd_create_campaigns')) {
                $this->render_draft_notice($draft_info);
            }
            ?>
            
            <?php $this->list_table->views(); ?>
            
            <form method="post">
                <?php
                $this->list_table->search_box(__('Search Campaigns', 'smart-cycle-discounts'), 'campaign');
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
        if ($this->check_capability('scd_create_campaigns')) {
            $this->render_draft_conflict_modal();
        }
        ?>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Draft Conflict Modal Handler
            var DraftConflictModal = {
                init: function() {
                    this.bindEvents();
                },
                
                bindEvents: function() {
                    $('.scd-new-campaign-btn').on('click', function(e) {
                        var hasDraft = $(this).data('has-draft') === 'true' || $(this).data('has-draft') === true;
                        var draftName = $(this).data('draft-name');
                        
                        // Only show modal if we actually have a draft
                        if (hasDraft === true && draftName) {
                            e.preventDefault();
                            $('#scd-draft-name').text(draftName);
                            if (typeof SCD !== 'undefined' && SCD.Modal) {
                                SCD.Modal.show('scd-draft-conflict-modal');
                            }
                        }
                    });
                }
            };
            
            // Initialize modal handler
            DraftConflictModal.init();
            
            // Modal button handlers
            $(document).on('click', '.scd-modal__actions button[data-action], .scd-modal-cancel, .scd-modal__close', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                var adminUrl = '<?php echo admin_url('admin.php'); ?>';
                
                switch(action) {
                    case 'save-new':
                        // Save current draft as campaign first, then create new
                        var $button = $(this);
                        $button.prop('disabled', true).text('<?php echo esc_js(__('Saving Draft...', 'smart-cycle-discounts')); ?>');
                        
                        // Make AJAX call to save the current draft
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'scd_ajax',
                                scd_action: 'save_draft',
                                save_as_draft: true,
                                nonce: '<?php echo wp_create_nonce('scd_wizard_nonce'); ?>'
                            },
                            success: function(response) {
                                // After saving, redirect to create new
                                window.location.href = adminUrl + '?page=scd-campaigns&action=wizard&intent=new';
                            },
                            error: function(xhr, status, error) {
                                // Enable button and show error
                                $button.prop('disabled', false).text('<?php echo esc_js(__('Save Draft & Create New', 'smart-cycle-discounts')); ?>');
                                alert('<?php echo esc_js(__('Failed to save draft. Please try again.', 'smart-cycle-discounts')); ?>');
                            }
                        });
                        break;
                        
                    case 'discard-new':
                        var $button = $(this);
                        $button.prop('disabled', true).text('<?php echo esc_js(__('Discarding...', 'smart-cycle-discounts')); ?>');
                        
                        // Make AJAX call to discard the draft
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'scd_ajax',
                                scd_action: 'delete_draft',
                                draft_action: 'delete',
                                draft_type: 'session',
                                draft_id: 'current',
                                nonce: '<?php echo wp_create_nonce('scd_wizard_nonce'); ?>'
                            },
                            success: function(response) {
                                window.location.href = adminUrl + '?page=scd-campaigns&action=wizard&intent=new';
                            },
                            error: function(xhr, status, error) {
                                // Still redirect even on error
                                window.location.href = adminUrl + '?page=scd-campaigns&action=wizard&intent=new';
                            }
                        });
                        break;
                        
                    case 'close':
                        SCD.Modal.hide('scd-draft-conflict-modal');
                        break;
                        
                    default:
                        // Handle cancel button and close button
                        if ($(this).hasClass('scd-modal-cancel') || $(this).hasClass('scd-modal__close')) {
                            SCD.Modal.hide('scd-draft-conflict-modal');
                        }
                        break;
                }
            });
        });
        </script>
        
        <?php
    }

    /**
     * Render draft campaign notice.
     *
     * @since    1.0.0
     * @param    array    $draft_info    Draft campaign information.
     * @return   void
     */
    private function render_draft_notice(array $draft_info): void {
        $nonce = wp_create_nonce('scd_discard_draft');
        ?>
        <div class="notice notice-info scd-draft-notice">
            <p>
                <strong><?php echo esc_html__('Draft Campaign Found', 'smart-cycle-discounts'); ?></strong>
                <?php echo esc_html__('You have an incomplete campaign:', 'smart-cycle-discounts'); ?>
                <strong><?php echo esc_html($draft_info['campaign_name']); ?></strong>
                
                <?php if (!empty($draft_info['last_updated'])): ?>
                    <span class="scd-draft-meta">
                        <?php
                        printf(
                            /* translators: %s: Human readable time difference */
                            esc_html__('Last updated %s ago', 'smart-cycle-discounts'),
                            human_time_diff($draft_info['last_updated'], current_time('timestamp'))
                        );
                        ?>
                    </span>
                <?php endif; ?>
                
                <span class="scd-draft-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=scd-campaigns&action=wizard&intent=continue')); ?>" 
                       class="button button-small">
                        <?php echo esc_html__('Continue Editing', 'smart-cycle-discounts'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(wp_nonce_url(
                        admin_url('admin.php?page=scd-campaigns&action=discard_draft'),
                        'scd_discard_draft',
                        '_wpnonce'
                    )); ?>" 
                       class="button-link-delete"
                       onclick="return confirm('<?php echo esc_attr__('Are you sure you want to discard this draft campaign?', 'smart-cycle-discounts'); ?>');">
                        <?php echo esc_html__('Discard Draft', 'smart-cycle-discounts'); ?>
                    </a>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * Render draft conflict modal.
     *
     * @since    1.0.0
     * @return   void
     */
    private function render_draft_conflict_modal(): void {
        // Load modal component
        require_once SCD_INCLUDES_DIR . 'admin/components/class-modal-component.php';
        
        // Create modal configuration
        $modal_config = array(
            'id' => 'scd-draft-conflict-modal',
            'title' => __('Draft Campaign Exists', 'smart-cycle-discounts'),
            'content' => sprintf(
                '<p>%s <strong id="scd-draft-name"></strong></p><p>%s</p>',
                __('You have an unsaved draft campaign:', 'smart-cycle-discounts'),
                __('What would you like to do?', 'smart-cycle-discounts')
            ),
            'icon' => 'dashicons-warning',
            'classes' => array('scd-modal__icon--warning'),
            'buttons' => array(
                array(
                    'id' => 'scd-save-and-new',
                    'text' => __('Save Draft & Create New', 'smart-cycle-discounts'),
                    'class' => 'button button-primary',
                    'action' => 'save-new'
                ),
                array(
                    'id' => 'scd-discard-and-new',
                    'text' => __('Discard Draft & Create New', 'smart-cycle-discounts'),
                    'class' => 'button button-secondary',
                    'action' => 'discard-new'
                ),
                array(
                    'text' => __('Cancel', 'smart-cycle-discounts'),
                    'class' => 'button scd-modal-cancel',
                    'action' => 'close'
                )
            ),
            'escape_content' => false // Already escaped above
        );
        
        // Create and render modal
        $modal = new SCD_Modal_Component($modal_config);
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

        $current_count = $repository->count( array(
            'status__not' => 'deleted'
        ) );

        $campaign_limit = $this->feature_gate->get_campaign_limit();
        $upgrade_url = function_exists( 'scd_get_upgrade_url' ) ? scd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

        // Determine styling based on usage
        $percentage_used = ( $campaign_limit > 0 ) ? ( $current_count / $campaign_limit ) * 100 : 0;
        $notice_class = 'notice-info';
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

                <?php if ( $current_count >= $campaign_limit ): ?>
                    <span class="scd-limit-message">
                        <?php esc_html_e( '- You\'ve reached your campaign limit.', 'smart-cycle-discounts' ); ?>
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-small button-primary">
                            <?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
                        </a>
                    </span>
                <?php else: ?>
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
}