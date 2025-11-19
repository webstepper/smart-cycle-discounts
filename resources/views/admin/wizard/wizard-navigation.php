<?php
/**
 * Wizard Navigation Template
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/templates/navigation
 * 
 * @var array $nav_data Navigation data from SCD_Wizard_Navigation::get_navigation_data()
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Extract navigation data
$current_step = $nav_data['current_step'] ?? '';
// Phase 3: No session_id needed - handled by secure cookies
$is_first = $nav_data['is_first'] ?? false;
$is_last = $nav_data['is_last'] ?? false;
$previous_step = $nav_data['previous_step'] ?? null;
$next_step = $nav_data['next_step'] ?? null;
$config = $nav_data['config'] ?? array();
$progress = $nav_data['progress'] ?? array();

// Button classes
$btn_classes = $config['button_classes'] ?? array();
$icons = $config['icons'] ?? array();

// Sanitize and validate query parameters
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display logic, nonce checked on form submission
$intent = isset( $_GET['intent'] ) ? sanitize_key( $_GET['intent'] ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display logic, nonce checked on form submission
$campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

// Detect edit mode - match JavaScript logic in wizard-orchestrator.js
// Edit mode only if: intent=edit OR (id exists AND intent is NOT 'new')
$is_edit_mode = ( 'edit' === $intent ) || ( $campaign_id > 0 && 'new' !== $intent );
$complete_button_text = $is_edit_mode ? __( 'Update Campaign', 'smart-cycle-discounts' ) : __( 'Create Campaign', 'smart-cycle-discounts' );
?>

<nav class="scd-wizard-navigation"
     data-current-step="<?php echo esc_attr($current_step); ?>"
     role="navigation"
     aria-label="<?php esc_attr_e('Wizard Navigation - Use Previous and Next buttons to navigate between steps', 'smart-cycle-discounts'); ?>"
     aria-live="polite"
     aria-atomic="true">

    <div class="scd-nav-container">
        <!-- Previous Button Section -->
        <div class="scd-nav-section scd-nav-section--left">
            <?php if ( ! $is_first && $previous_step ): ?>
                <?php
                SCD_Button_Helper::render( array(
                    'text'       => __( 'Previous', 'smart-cycle-discounts' ),
                    'type'       => 'button',
                    'icon'       => 'arrow-left',
                    'icon_position' => 'left',
                    'classes'    => (array) $btn_classes['previous'],
                    'attributes' => array(
                        'data-action' => 'previous',
                        'data-target' => esc_attr( $previous_step ),
                        'aria-label'  => __( 'Go to previous step', 'smart-cycle-discounts' ),
                    ),
                ) );
                ?>
            <?php endif; ?>
        </div>

        <!-- Center Status Section -->
        <div class="scd-nav-section scd-nav-section--center">
            <div class="scd-nav-status">
                <span class="scd-nav-status__step">
                    <?php 
                    echo esc_html(sprintf(
                        __('Step %d of %d', 'smart-cycle-discounts'),
                        $nav_data['current_index'] + 1,
                        $nav_data['total_steps']
                    )); 
                    ?>
                </span>
            </div>
        </div>

        <!-- Next/Complete Button Section -->
        <div class="scd-nav-section scd-nav-section--right">
            <?php if ( $is_last ): ?>
                <!-- Complete Campaign Button -->
                <?php
                ob_start();
                if ( class_exists( 'SCD_Loader_Helper' ) ) {
                    echo SCD_Loader_Helper::button( 'scd-launch-loader', $is_edit_mode ? __( 'Updating...', 'smart-cycle-discounts' ) : __( 'Creating...', 'smart-cycle-discounts' ), false );
                }
                $loader_html = ob_get_clean();

                SCD_Button_Helper::render( array(
                    'text'       => $complete_button_text,
                    'type'       => 'button',
                    'icon'       => 'check',
                    'icon_position' => 'left',
                    'classes'    => (array) $btn_classes['complete'],
                    'attributes' => array(
                        'data-action' => 'complete',
                        'aria-label'  => $is_edit_mode ? __( 'Complete wizard and update campaign', 'smart-cycle-discounts' ) : __( 'Complete wizard and create campaign', 'smart-cycle-discounts' ),
                    ),
                ) );

                // Output loader HTML after button
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by SCD_Loader_Helper
                echo $loader_html;
                ?>
            <?php elseif ( $next_step ): ?>
                <?php
                SCD_Button_Helper::render( array(
                    'text'       => __( 'Next', 'smart-cycle-discounts' ),
                    'type'       => 'button',
                    'icon'       => 'arrow-right',
                    'icon_position' => 'right',
                    'classes'    => (array) $btn_classes['next'],
                    'attributes' => array(
                        'data-action' => 'next',
                        'data-target' => esc_attr( $next_step ),
                        'aria-label'  => __( 'Go to next step', 'smart-cycle-discounts' ),
                    ),
                ) );
                ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fallback form removed - navigation handled client-side via JavaScript -->
    <!-- JavaScript is required for wizard functionality -->
</nav>