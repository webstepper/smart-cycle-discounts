<?php
/**
 * Campaign Wizard - Basic Information Step
 *
 * This template collects essential campaign details including name, description,
 * and priority level. It provides real-time validation and helpful guidance
 * to ensure users create well-structured discount campaigns.
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/views/campaigns/wizard
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Initialize variables using shared function
scd_wizard_init_step_vars($step_data, $validation_errors);

// Field schema handles default values now - no need to set them here
// Extract values with defaults handled by field schema
$name = $step_data['name'] ?? '';
$description = $step_data['description'] ?? '';
$priority = $step_data['priority'] ?? 3;

// Debug logging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[SCD Basic Step] Step data initialized: ' . print_r($step_data, true));
}

// Prepare content for template wrapper
ob_start();
?>
    <?php scd_wizard_validation_notice($validation_errors); ?>

    <!-- Campaign Details Section -->
    <?php
    ob_start();

    // Campaign Name Field - Phase 2: Simplified syntax reading from field definitions
    scd_wizard_form_field(array(
        'step' => 'basic',
        'field' => 'name',
        'value' => $name,
        'validation_errors' => $validation_errors
    ));

    // Campaign Description Field - Phase 2: Simplified syntax
    scd_wizard_form_field(array(
        'step' => 'basic',
        'field' => 'description',
        'value' => $description,
        'validation_errors' => $validation_errors
    ));

    $details_content = ob_get_clean();
    
    scd_wizard_card(array(
        'title' => __('Campaign Details', 'smart-cycle-discounts'),
        'subtitle' => __('Provide the basic information that will help you identify and manage this discount campaign.', 'smart-cycle-discounts'),
        'icon' => 'edit',
        'content' => $details_content,
        'help_topic' => 'card-campaign-details'
    ));
    ?>

    <!-- Campaign Priority Section -->
    <?php
    ob_start();

    // Priority Field - Phase 2: Simplified syntax
    scd_wizard_form_field(array(
        'step' => 'basic',
        'field' => 'priority',
        'value' => intval($priority),
        'validation_errors' => $validation_errors
    ));

    $priority_content = ob_get_clean();

    scd_wizard_card(array(
        'title' => __('Campaign Priority', 'smart-cycle-discounts'),
        'icon' => 'sort',
        'subtitle' => __('Set the priority level to control which campaign applies when multiple campaigns target the same products.', 'smart-cycle-discounts'),
        'content' => $priority_content,
        'help_topic' => 'card-campaign-priority'
    ));
    ?>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
scd_wizard_render_step( array(
    'title'     => __( 'Campaign Setup', 'smart-cycle-discounts' ),
    'description' => __( 'Set up the basic information for your discount campaign', 'smart-cycle-discounts' ),
    'content'   => $content,
    'step'      => 'basic',
    'step_data' => $step_data,
) );
?>

<!-- Initialize state data for Basic step -->
<?php
// Validation rules are now handled by the centralized field schema system

scd_wizard_state_script('basic', array(
    'name' => $name,
    'description' => $description,
    'priority' => $priority
));
?>


