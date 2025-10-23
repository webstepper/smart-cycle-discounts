<?php
/**
 * Complete Wizard Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

/**
 * Complete Wizard Handler Class
 *
 * Handles the completion of the campaign wizard.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Complete_Wizard_Handler {

    /**
     * The wizard state service.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Wizard_State_Service    $state_service    State service instance.
     */
    private $state_service;

    /**
     * Constructor.
     *
     * @since    1.0.0
     * @param    SCD_Wizard_State_Service    $state_service    State service instance.
     */
    public function __construct( $state_service = null ) {
        if ( $state_service ) {
            $this->state_service = $state_service;
        } else {
            // Create new instance if not provided
            if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
                require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
            }
            $this->state_service = new SCD_Wizard_State_Service();
        }
    }

    /**
     * Handle the completion request.
     *
     * @since    1.0.0
     * @param    array    $request    Request data.
     * @return   array               Response data.
     */
    public function handle( $request = array() ) {
        try {
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Starting campaign creation...' );
            }

            // Get all steps data
            $steps_data = $this->state_service->get_all_data();

            if ( empty( $steps_data ) || empty( $steps_data['steps'] ) ) {
                throw new Exception( __( 'No campaign data found in session.', 'smart-cycle-discounts' ) );
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Raw steps data: ' . print_r( $steps_data['steps'], true ) );
            }

            // Get campaign repository from service container
            if ( ! class_exists( 'Smart_Cycle_Discounts' ) ) {
                throw new Exception( __( 'Plugin not initialized.', 'smart-cycle-discounts' ) );
            }

            $campaign_repository = Smart_Cycle_Discounts::get_service( 'campaign_repository' );

            // Use the existing Campaign Compiler Service to transform wizard data
            if ( ! class_exists( 'SCD_Campaign_Compiler_Service' ) ) {
                require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
            }

            $compiler = new SCD_Campaign_Compiler_Service( $campaign_repository );
            $campaign_data = $compiler->compile( $steps_data['steps'] );

            // Get launch option from review step
            $review_data = $this->state_service->get_step_data( 'review' );
            $launch_option = isset( $review_data['launch_option'] ) ? $review_data['launch_option'] :
                            ( isset( $campaign_data['launch_option'] ) ? $campaign_data['launch_option'] : 'active' );

            // Status is already correctly set by compiler based on start time
            // No need to override it here

            // Add skip_wizard_validation flag to bypass incompatible validation
            $campaign_data['_skip_wizard_validation'] = true;

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Compiled campaign data: ' . print_r( $campaign_data, true ) );
                error_log( '[Complete Wizard] Launch option: ' . $launch_option );
            }

            // Get campaign manager from service container
            $campaign_manager = Smart_Cycle_Discounts::get_service( 'campaign_manager' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Campaign manager: ' . ( $campaign_manager ? 'Found' : 'Not found' ) );
            }

            if ( ! $campaign_manager ) {
                throw new Exception( __( 'Campaign manager service not available.', 'smart-cycle-discounts' ) );
            }

            // Create campaign
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Creating campaign with data: ' . json_encode( $campaign_data ) );
            }

            $campaign = $campaign_manager->create( $campaign_data );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Campaign result type: ' . gettype( $campaign ) );
                if ( is_wp_error( $campaign ) ) {
                    error_log( '[Complete Wizard] WP_Error message: ' . $campaign->get_error_message() );
                    error_log( '[Complete Wizard] WP_Error codes: ' . print_r( $campaign->get_error_codes(), true ) );
                    error_log( '[Complete Wizard] WP_Error messages: ' . print_r( $campaign->get_error_messages(), true ) );
                    error_log( '[Complete Wizard] WP_Error data: ' . print_r( $campaign->get_error_data(), true ) );
                }
            }

            if ( is_wp_error( $campaign ) ) {
                $error_message = $campaign->get_error_message();
                $all_messages = $campaign->get_error_messages();
                if ( count( $all_messages ) > 1 ) {
                    $error_message .= ' Details: ' . implode( ', ', $all_messages );
                }
                throw new Exception( $error_message );
            }

            $campaign_id = $campaign->get_id();
            $campaign_status = $campaign->get_status();
            $campaign_name = $campaign->get_name();

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Campaign created - ID: ' . $campaign_id . ', Name: ' . $campaign_name . ', Status: ' . $campaign_status );
            }

            // Clear the wizard session
            $this->state_service->clear_session();

            // Build redirect URL
            $redirect_url = add_query_arg( array(
                'page' => 'scd-campaigns',
                'message' => 'campaign_created'
            ), admin_url( 'admin.php' ) );

            // Build success message based on status
            if ( 'active' === $campaign_status ) {
                $message = __( 'Campaign launched successfully!', 'smart-cycle-discounts' );
            } elseif ( 'scheduled' === $campaign_status ) {
                $message = __( 'Campaign scheduled successfully!', 'smart-cycle-discounts' );
            } else {
                $message = __( 'Campaign saved as draft!', 'smart-cycle-discounts' );
            }

            return array(
                'success' => true,
                'campaign_id' => $campaign_id,
                'campaign_name' => $campaign_name,
                'status' => $campaign_status,
                'redirect_url' => $redirect_url,
                'message' => $message
            );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Complete Wizard] Exception: ' . $e->getMessage() );
                error_log( '[Complete Wizard] Trace: ' . $e->getTraceAsString() );
            }

            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}