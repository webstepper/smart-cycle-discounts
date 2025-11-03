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

// Detect edit mode - match JavaScript logic in wizard-orchestrator.js
// Edit mode only if: intent=edit OR (id exists AND intent is NOT 'new')
$is_edit_mode = ( isset( $_GET['intent'] ) && 'edit' === $_GET['intent'] )
                || ( isset( $_GET['id'] ) && ! empty( $_GET['id'] ) && ( ! isset( $_GET['intent'] ) || 'new' !== $_GET['intent'] ) );
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
            <?php if (!$is_first && $previous_step): ?>
                <button type="button" 
                        class="<?php echo esc_attr($btn_classes['previous']); ?>"
                        data-action="previous"
                        data-target="<?php echo esc_attr($previous_step); ?>"
                        aria-label="<?php esc_attr_e('Go to previous step', 'smart-cycle-discounts'); ?>">
                    <span class="dashicons <?php echo esc_attr($icons['previous']); ?>" aria-hidden="true"></span>
                    <span class="scd-nav-btn__text"><?php esc_html_e('Previous', 'smart-cycle-discounts'); ?></span>
                </button>
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
            <!-- Autosave Indicator -->
            <span class="scd-nav-status__autosave" style="display: none;" role="status" aria-live="polite">
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <span class="scd-nav-status__autosave-text"><?php esc_html_e('Saving...', 'smart-cycle-discounts'); ?></span>
            </span>

            <?php if ($is_last): ?>
                <!-- Complete Campaign Button -->
                <button type="button"
                        class="<?php echo esc_attr($btn_classes['complete']); ?>"
                        data-action="complete"
                        aria-label="<?php echo esc_attr( $is_edit_mode ? __( 'Complete wizard and update campaign', 'smart-cycle-discounts' ) : __( 'Complete wizard and create campaign', 'smart-cycle-discounts' ) ); ?>">
                    <span class="dashicons <?php echo esc_attr($icons['complete']); ?>" aria-hidden="true"></span>
                    <span class="scd-nav-btn__text"><?php echo esc_html( $complete_button_text ); ?></span>
                </button>
            <?php elseif ($next_step): ?>
                <button type="button" 
                        class="<?php echo esc_attr($btn_classes['next']); ?>"
                        data-action="next"
                        data-target="<?php echo esc_attr($next_step); ?>"
                        aria-label="<?php esc_attr_e('Go to next step', 'smart-cycle-discounts'); ?>">
                    <span class="scd-nav-btn__text"><?php esc_html_e('Next', 'smart-cycle-discounts'); ?></span>
                    <span class="dashicons <?php echo esc_attr($icons['next']); ?>" aria-hidden="true"></span>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fallback form removed - navigation handled client-side via JavaScript -->
    <!-- JavaScript is required for wizard functionality -->
</nav>