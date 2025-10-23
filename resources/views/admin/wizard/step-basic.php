<?php
/**
 * Campaign Wizard - Basic Information Step
 *
 * This template collects essential campaign details including name, description,
 * and priority level. It provides real-time validation and helpful guidance
 * to ensure users create well-structured discount campaigns.
 *
 * @link       https://smartcyclediscounts.com
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
$priority = $step_data['priority'] ?? 5;

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
    
    // Campaign Name Field
    scd_wizard_form_field(array(
        'id' => 'campaign_name',
        'name' => 'name',
        'label' => __('Campaign Name', 'smart-cycle-discounts'),
        'type' => 'text',
        'value' => $name,
        'placeholder' => __('e.g., Summer Sale 2024', 'smart-cycle-discounts'),
        'required' => true,
        'class' => 'regular-text scd-campaign-name-field',
        'validation_errors' => $validation_errors,
        'tooltip' => __('Give your campaign a clear, descriptive name that will help you identify it in your campaigns list.', 'smart-cycle-discounts'),
        'attributes' => array(
            'maxlength' => '100',
            'autocomplete' => 'off'
        )
    ));
    
    // Campaign Description Field
    scd_wizard_form_field(array(
        'id' => 'campaign_description',
        'name' => 'description',
        'label' => __('Description', 'smart-cycle-discounts'),
        'type' => 'textarea',
        'value' => $description,
        'placeholder' => __('Optional internal notes about campaign goals, target audience, etc...', 'smart-cycle-discounts'),
        'required' => false,
        'class' => 'large-text',
        'validation_errors' => $validation_errors,
        'tooltip' => __('This description is for internal use only and will not be visible to customers. Use it to document strategy, goals, or special instructions for your team.', 'smart-cycle-discounts'),
        'attributes' => array(
            'rows' => '4',
            'maxlength' => '1000'
        )
    ));
    
    $details_content = ob_get_clean();
    
    scd_wizard_card(array(
        'title' => __('Campaign Details', 'smart-cycle-discounts'),
        'subtitle' => __('Provide the basic information that will help you identify and manage this discount campaign.', 'smart-cycle-discounts'),
        'icon' => 'edit',
        'content' => $details_content
    ));
    ?>

    <!-- Campaign Priority Section -->
    <?php
    ob_start();
    
    scd_wizard_form_field(array(
        'id' => 'campaign_priority',
        'name' => 'priority',
        'label' => __('Priority Level', 'smart-cycle-discounts'),
        'type' => 'select',
        'value' => intval($priority),
        'required' => true,
        'validation_errors' => $validation_errors,
        'tooltip' => __('When multiple campaigns apply to the same product, the campaign with the highest priority (lowest number) takes precedence. Use priority levels to ensure your most important campaigns are applied first.', 'smart-cycle-discounts'),
        'options' => array(
            1 => __('1 - Highest Priority', 'smart-cycle-discounts'),
            2 => __('2 - Very High Priority', 'smart-cycle-discounts'),
            3 => __('3 - High Priority', 'smart-cycle-discounts'),
            4 => __('4 - Above Normal Priority', 'smart-cycle-discounts'),
            5 => __('5 - Normal Priority (Recommended)', 'smart-cycle-discounts'),
            6 => __('6 - Below Normal Priority', 'smart-cycle-discounts'),
            7 => __('7 - Low Priority', 'smart-cycle-discounts'),
            8 => __('8 - Very Low Priority', 'smart-cycle-discounts'),
            9 => __('9 - Lower Priority', 'smart-cycle-discounts'),
            10 => __('10 - Lowest Priority', 'smart-cycle-discounts')
        )
    ));
    
    $priority_content = ob_get_clean();

    scd_wizard_card(array(
        'title' => __('Campaign Priority', 'smart-cycle-discounts'),
        'subtitle' => __('Set the priority level to control which campaign applies when multiple campaigns target the same products.', 'smart-cycle-discounts'),
        'icon' => 'sort',
        'content' => $priority_content
    ));
    ?>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
scd_wizard_render_step( array(
    'title' => __( 'Campaign Setup', 'smart-cycle-discounts' ),
    'description' => __( 'Set up the basic information for your discount campaign', 'smart-cycle-discounts' ),
    'content' => $content,
    'step' => 'basic'
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


