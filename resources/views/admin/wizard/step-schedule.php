<?php
/**
 * Campaign wizard - Modern Schedule & timing step
 *
 * Comprehensive, secure, accessible schedule configuration
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/views/campaigns/wizard
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Security: Verify user capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-cycle-discounts' ) );
}

// Initialize variables using shared function
wsscd_wizard_init_step_vars( $step_data, $validation_errors );

// Get current timezone and locale info
$timezone = wp_timezone_string();
$current_time = current_time( 'Y-m-d\TH:i' );
$locale = get_locale();
$is_rtl = is_rtl();

// Extract values with defaults handled by field schema
$start_type = $step_data['start_type'] ?? 'immediate';
$start_date = $step_data['start_date'] ?? gmdate( 'Y-m-d' );
$end_date = $step_data['end_date'] ?? '';  // Default empty = runs indefinitely
$start_time = $step_data['start_time'] ?? '00:00';
$end_time = ! empty( $end_date ) ? ( $step_data['end_time'] ?? '23:59' ) : '';
$timezone_value = $step_data['timezone'] ?? $timezone;
// Recurring is now FREE - no premium check needed
$enable_recurring = $step_data['enable_recurring'] ?? false;
$recurrence_mode = $step_data['recurrence_mode'] ?? 'continuous';
$recurrence_pattern = $step_data['recurrence_pattern'] ?? 'daily';
$recurrence_interval = $step_data['recurrence_interval'] ?? 1;
$recurrence_days = $step_data['recurrence_days'] ?? array();
$recurrence_end_type = $step_data['recurrence_end_type'] ?? 'never';
$recurrence_count = $step_data['recurrence_count'] ?? 10;
$recurrence_end_date = $step_data['recurrence_end_date'] ?? '';

// Prepare content for template wrapper
ob_start();
?>
    <?php wsscd_wizard_validation_notice( $validation_errors ); ?>

    <!-- Screen reader announcement area -->
    <div id="wsscd-schedule-announcements" 
         class="sr-only" 
         aria-live="polite" 
         aria-atomic="true">
    </div>

    <!-- Loading state overlay -->
    <?php wsscd_wizard_loading_indicator( 'wsscd-schedule-loading', __( 'Loading schedule options', 'smart-cycle-discounts' ) ); ?>

    <!-- Step identifier for wizard framework -->
    <input type="hidden" 
           name="step" 
           value="schedule"
           data-step="schedule"
           data-locale="<?php echo esc_attr( $locale ); ?>"
           data-rtl="<?php echo esc_attr( $is_rtl ? '1' : '0' ); ?>">

    <!-- Quick Duration Presets Section -->
    <?php
    ob_start();
    ?>
    <div class="wsscd-preset-grid" id="wsscd-preset-recommendations" role="group" aria-label="<?php esc_attr_e( 'Campaign duration presets', 'smart-cycle-discounts' ); ?>">
        <!-- Preset options will be populated by JavaScript -->
        <div class="wsscd-preset-loading">
            <span class="spinner is-active"></span>
            <?php esc_html_e( 'Loading recommendations...', 'smart-cycle-discounts' ); ?>
        </div>
    </div>
    <?php
    $preset_content = ob_get_clean();
    
    wsscd_wizard_card( array(
        'title' => __( 'Quick Duration Setup', 'smart-cycle-discounts' ),
        'subtitle' => __( 'Select a preset duration or customize your campaign schedule below', 'smart-cycle-discounts' ),
        'icon' => 'clock',
        'content' => $preset_content,
        'help_topic' => 'card-duration-presets'
    ) );
    ?>

    <!-- Primary Schedule Configuration -->
    <?php
    ob_start();
    ?>
    <!-- Start Type Selection Cards -->
    <div class="wsscd-form-section">
        <div class="wsscd-form-section-header">
            <h3 class="wsscd-form-section-title">
                <?php esc_html_e( 'Campaign Start Type', 'smart-cycle-discounts' ); ?>
            </h3>
        </div>
        <div class="wsscd-schedule-selection-cards">
            <label class="wsscd-card-option">
                <input type="radio" 
                       name="start_type" 
                       value="immediate" 
                       <?php checked( $start_type, 'immediate' ); ?>>
                <div class="wsscd-card__content">
                    <h4>
                        <?php WSSCD_Icon_Helper::render( 'play', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e( 'Start Immediately', 'smart-cycle-discounts' ); ?>
                    </h4>
                    <p><?php esc_html_e( 'Campaign begins as soon as it\'s launched. Perfect for urgent promotions or flash sales.', 'smart-cycle-discounts' ); ?></p>
                </div>
            </label>
            <label class="wsscd-card-option">
                <input type="radio" 
                       name="start_type" 
                       value="scheduled" 
                       <?php checked( $start_type, 'scheduled' ); ?>>
                <div class="wsscd-card__content">
                    <h4>
                        <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e( 'Scheduled Start', 'smart-cycle-discounts' ); ?>
                    </h4>
                    <p><?php esc_html_e( 'Set a specific date and time to begin. Ideal for planned campaigns and seasonal promotions.', 'smart-cycle-discounts' ); ?></p>
                </div>
            </label>
        </div>
    </div>

    <!-- Schedule Dates Section -->
    <div class="wsscd-form-section">
        <div class="wsscd-form-section-header">
            <h4 class="wsscd-form-section-title">
                <?php esc_html_e( 'Schedule Dates', 'smart-cycle-discounts' ); ?>
            </h4>
        </div>

    <!-- Start Date - Only visible when "scheduled" is selected -->
    <div class="wsscd-scheduled-start-fields"<?php if ( 'immediate' === $start_type ) { echo ' style="display: none;"'; } ?>>
        <!-- Hidden actual input for form submission -->
        <input type="hidden"
               id="start_date"
               name="start_date"
               value="<?php echo esc_attr( $start_date ); ?>"
               data-field="start_date">

        <?php
        // Start date and time fields
        ob_start();
        ?>
        <div class="wsscd-datetime-wrapper">
            <!-- Date picker -->
            <div class="wsscd-date-picker-wrapper">
                <input type="text"
                       id="start_date_display"
                       class="wsscd-date-picker <?php echo esc_attr( isset( $validation_errors['start_date'] ) ? 'error' : '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Select date', 'smart-cycle-discounts' ); ?>"
                       readonly
                       value="<?php echo esc_attr( $start_date ); ?>"
                       aria-describedby="start_date_help"
                       <?php if ( 'immediate' === $start_type ) { echo 'tabindex="-1"'; } ?>>
                <button type="button"
                        class="wsscd-calendar-icon"
                        data-target="start_date_display"
                        aria-label="<?php esc_attr_e( 'Choose start date', 'smart-cycle-discounts' ); ?>"
                        <?php if ( 'immediate' === $start_type ) { echo 'tabindex="-1"'; } ?>>
                    <?php
                    if ( class_exists( 'WSSCD_Icon_Helper' ) ) {
                        WSSCD_Icon_Helper::render(
                            'calendar-alt',
                            array(
                                'size'        => 18,
                                'class'       => 'wsscd-icon',
                                'aria_hidden' => true,
                            )
                        );
                    }
                    ?>
                </button>
            </div>

            <!-- Time picker -->
            <div class="wsscd-time-picker-wrapper">
                <span class="wsscd-at-label"><?php esc_html_e( 'at', 'smart-cycle-discounts' ); ?></span>
                <input type="time"
                       id="start_time"
                       name="start_time"
                       value="<?php echo esc_attr( $start_time ); ?>"
                       class="wsscd-time-picker <?php echo esc_attr( isset( $validation_errors['start_time'] ) ? 'error' : '' ); ?>"
                       aria-label="<?php esc_attr_e( 'Start time', 'smart-cycle-discounts' ); ?>"
                       <?php if ( 'immediate' === $start_type ) { echo 'tabindex="-1"'; } ?>>
            </div>
        </div>
        <?php wsscd_wizard_field_errors( $validation_errors, 'start_date' ); ?>
        <?php $start_date_content = ob_get_clean(); ?>

        <!-- Modern flexbox field layout -->
        <div class="wsscd-field-group">
            <div class="wsscd-field-header">
                <label class="wsscd-field-label" for="start_date_display">
                    <?php esc_html_e( 'Start Date', 'smart-cycle-discounts' ); ?>
                    <span class="wsscd-required-indicator" aria-label="<?php esc_attr_e( 'Required', 'smart-cycle-discounts' ); ?>">*</span>
                </label>
                <?php
                WSSCD_Tooltip_Helper::render(
                    __( 'When your discount campaign begins. Recent dates (within 5 minutes) are allowed to account for clock differences and processing time.', 'smart-cycle-discounts' )
                );
                ?>
            </div>
            <div class="wsscd-field-content">
                <?php WSSCD_HTML_Helper::output( $start_date_content ); ?>
            </div>
        </div>
    </div>

    <!-- End Date - Always visible -->
        <!-- Hidden actual input for form submission -->
        <input type="hidden"
               id="end_date"
               name="end_date"
               value="<?php echo esc_attr( $end_date ); ?>"
               data-field="end_date">

        <?php
        // End date and time fields
        ob_start();
        ?>
        <div class="wsscd-datetime-wrapper">
            <!-- Date picker -->
            <div class="wsscd-date-picker-wrapper">
                <input type="text"
                       id="end_date_display"
                       class="wsscd-date-picker <?php echo esc_attr( isset( $validation_errors['end_date'] ) ? 'error' : '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Runs indefinitely', 'smart-cycle-discounts' ); ?>"
                       readonly
                       value="<?php echo esc_attr( $end_date ); ?>"
                       aria-describedby="end_date_help">
                <button type="button"
                        class="wsscd-calendar-icon"
                        data-target="end_date_display"
                        aria-label="<?php esc_attr_e( 'Choose end date', 'smart-cycle-discounts' ); ?>">
                    <?php
                    if ( class_exists( 'WSSCD_Icon_Helper' ) ) {
                        WSSCD_Icon_Helper::render(
                            'calendar-alt',
                            array(
                                'size'        => 18,
                                'class'       => 'wsscd-icon',
                                'aria_hidden' => true,
                            )
                        );
                    }
                    ?>
                </button>
            </div>

            <!-- Time picker -->
            <div class="wsscd-time-picker-wrapper">
                <span class="wsscd-at-label"><?php esc_html_e( 'at', 'smart-cycle-discounts' ); ?></span>
                <input type="<?php echo esc_attr( empty( $end_date ) ? 'text' : 'time' ); ?>"
                       id="end_time"
                       name="end_time"
                       value="<?php echo esc_attr( empty( $end_date ) ? '--:--' : $end_time ); ?>"
                       class="wsscd-time-picker <?php echo esc_attr( empty( $end_date ) ? 'wsscd-time-placeholder' : '' ); ?><?php echo esc_attr( isset( $validation_errors['end_time'] ) ? ' error' : '' ); ?>"
                       <?php echo esc_attr( empty( $end_date ) ? 'disabled' : '' ); ?>
                       aria-label="<?php esc_attr_e( 'End time', 'smart-cycle-discounts' ); ?>">
            </div>

            <!-- Option to clear end date -->
            <?php
            WSSCD_Button_Helper::icon(
                'no-alt',
                __( 'Clear end date and time', 'smart-cycle-discounts' ),
                array(
                    'style'      => 'ghost-danger',
                    'size'       => 'small',
                    'classes'    => array( 'wsscd-clear-end-date' ),
                    'attributes' => array( 'title' => __( 'Run indefinitely', 'smart-cycle-discounts' ) ),
                )
            );
            ?>
        </div>

        <?php wsscd_wizard_field_errors( $validation_errors, 'end_date' ); ?>
        <?php $end_date_content = ob_get_clean(); ?>

        <!-- Modern flexbox field layout -->
        <div class="wsscd-field-group">
            <div class="wsscd-field-header">
                <label class="wsscd-field-label" for="end_date_display">
                    <?php esc_html_e( 'End Date', 'smart-cycle-discounts' ); ?>
                </label>
                <?php
                WSSCD_Tooltip_Helper::render(
                    __( 'When your discount campaign ends. Leave empty to run indefinitely', 'smart-cycle-discounts' )
                );
                ?>
            </div>
            <div class="wsscd-field-content">
                <?php WSSCD_HTML_Helper::output( $end_date_content ); ?>
            </div>
        </div>
    </div><!-- .wsscd-form-section (Schedule Dates) -->
    
    <!-- Duration Display -->
    <div id="wsscd-duration-display" 
         class="wsscd-duration-display"
         role="status"
         aria-live="polite">
        <div class="wsscd-duration-content">
            <?php WSSCD_Icon_Helper::render( 'clock', array( 'size' => 16, 'class' => 'wsscd-duration-icon' ) ); ?>
            <span class="wsscd-duration-label"><?php esc_html_e( 'Duration:', 'smart-cycle-discounts' ); ?></span>
            <span id="wsscd-duration-text" class="wsscd-duration-value">
                <?php esc_html_e( '7 days (168 hours)', 'smart-cycle-discounts' ); ?>
            </span>
        </div>
        <div id="wsscd-duration-hint" class="wsscd-duration-hint">
            <?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16, 'class' => 'wsscd-hint-icon' ) ); ?>
            <span class="wsscd-hint-text"><?php esc_html_e( 'Perfect for weekly promotions', 'smart-cycle-discounts' ); ?></span>
        </div>
    </div>
    
    <!-- Timezone indicator -->
    <div class="wsscd-timezone-indicator wsscd-timezone-indicator--prominent">
        <div class="wsscd-timezone-content">
            <?php WSSCD_Icon_Helper::render( 'admin-site-alt3', array( 'size' => 16, 'class' => 'wsscd-timezone-icon' ) ); ?>
            <span class="wsscd-timezone-label"><?php esc_html_e( 'Campaign timezone:', 'smart-cycle-discounts' ); ?></span>
            <strong class="wsscd-timezone-value"><?php echo esc_html( $timezone ); ?></strong>
        </div>
        <div class="wsscd-timezone-note">
            <?php
            if ( 'immediate' === $start_type ) {
                printf(
                    wp_kses(
                        /* translators: %s: WordPress site timezone wrapped in strong tags */
                        __( 'This campaign will start immediately using the server time in %s timezone.', 'smart-cycle-discounts' ),
                        array( 'strong' => array() )
                    ),
                    '<strong>' . esc_html( $timezone ) . '</strong>'
                );
            } else {
                printf(
                    wp_kses(
                        /* translators: %s: WordPress site timezone wrapped in strong tags */
                        __( 'All dates and times use the %s timezone. Please schedule accordingly.', 'smart-cycle-discounts' ),
                        array( 'strong' => array() )
                    ),
                    '<strong>' . esc_html( $timezone ) . '</strong>'
                );
            }
            ?>
        </div>
    </div>
    <?php
    $schedule_content = ob_get_clean();
    
    wsscd_wizard_card( array(
        'title' => __( 'Schedule Configuration', 'smart-cycle-discounts' ),
        'subtitle' => __( 'Configure when your campaign should run. All times are in your store timezone.', 'smart-cycle-discounts' ),
        'icon' => 'calendar-alt',
        'content' => $schedule_content,
        'help_topic' => 'card-schedule-config'
    ) );
    ?>

    <!-- Recurring Schedule Card -->
    <?php
    // Recurring is now FREE for all users - no premium check needed
    ob_start();
    ?>

    <div id="wsscd-recurring-container">
    <!-- Enable Recurring Toggle -->
    <div class="wsscd-form-row">
        <label class="wsscd-toggle-control">
            <input type="checkbox"
                   id="enable_recurring"
                   name="enable_recurring"
                   value="1"
                   <?php checked( $enable_recurring, true ); ?>>
            <span class="wsscd-toggle-slider" aria-hidden="true"></span>
            <span class="wsscd-toggle-label">
                <?php esc_html_e( 'Enable recurring schedule', 'smart-cycle-discounts' ); ?>
            </span>
        </label>
    </div>

    <!-- Recurrence Mode Selection -->
    <div id="wsscd-recurrence-mode-options"
         class="wsscd-recurrence-mode-options wsscd-form-section"
         style="display: <?php echo esc_attr( ( $enable_recurring ) ? 'block' : 'none' ); ?>;"
         aria-hidden="<?php echo esc_attr( ( $enable_recurring ) ? 'false' : 'true' ); ?>">
        <div class="wsscd-form-section-header">
            <h4 class="wsscd-form-section-title">
                <?php WSSCD_Icon_Helper::render( 'admin-settings', array( 'size' => 16, 'class' => 'wsscd-section-icon' ) ); ?>
                <?php esc_html_e( 'Recurrence Mode', 'smart-cycle-discounts' ); ?>
            </h4>
            <?php
            WSSCD_Tooltip_Helper::render(
                __( 'Choose how recurring campaigns should work: Continuous mode toggles the same campaign on/off based on schedule (ideal for happy hours), while Instances mode creates separate campaign copies for each occurrence (ideal for tracking different periods separately).', 'smart-cycle-discounts' ),
                'wsscd-tooltip--right'
            );
            ?>
        </div>
        <div class="wsscd-recurrence-mode-cards">
            <label class="wsscd-card-option wsscd-recurrence-mode-card <?php echo 'continuous' === $recurrence_mode ? 'selected' : ''; ?>">
                <input type="radio"
                       name="recurrence_mode"
                       value="continuous"
                       <?php checked( $recurrence_mode, 'continuous' ); ?>>
                <div class="wsscd-card__content">
                    <div class="wsscd-card__header">
                        <?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 20, 'class' => 'wsscd-mode-icon' ) ); ?>
                        <h5><?php esc_html_e( 'Continuous', 'smart-cycle-discounts' ); ?></h5>
                        <span class="wsscd-badge wsscd-badge-recommended"><?php esc_html_e( 'Recommended', 'smart-cycle-discounts' ); ?></span>
                    </div>
                    <p><?php esc_html_e( 'Same campaign activates and deactivates based on time. Perfect for daily happy hours, weekend specials, or any repeating time-based promotion.', 'smart-cycle-discounts' ); ?></p>
                    <div class="wsscd-card__example">
                        <?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 14 ) ); ?>
                        <span><?php esc_html_e( 'Example: "Happy Hour 3-5 PM daily" - single campaign, toggles active during scheduled hours', 'smart-cycle-discounts' ); ?></span>
                    </div>
                </div>
            </label>

            <label class="wsscd-card-option wsscd-recurrence-mode-card <?php echo 'instances' === $recurrence_mode ? 'selected' : ''; ?>">
                <input type="radio"
                       name="recurrence_mode"
                       value="instances"
                       <?php checked( $recurrence_mode, 'instances' ); ?>>
                <div class="wsscd-card__content">
                    <div class="wsscd-card__header">
                        <?php WSSCD_Icon_Helper::render( 'backup', array( 'size' => 20, 'class' => 'wsscd-mode-icon' ) ); ?>
                        <h5><?php esc_html_e( 'Instances', 'smart-cycle-discounts' ); ?></h5>
                    </div>
                    <p><?php esc_html_e( 'Creates separate campaign copies for each occurrence. Useful when you need individual analytics and tracking for each period.', 'smart-cycle-discounts' ); ?></p>
                    <div class="wsscd-card__example">
                        <?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 14 ) ); ?>
                        <span><?php esc_html_e( 'Example: "Weekly Sale" - creates "Weekly Sale #1", "Weekly Sale #2", etc. with separate analytics', 'smart-cycle-discounts' ); ?></span>
                    </div>
                </div>
            </label>
        </div>
    </div>

    <!-- Recurring Campaign Important Notice (for instances mode) -->
    <div id="wsscd-recurring-warning"
         class="wsscd-recurring-warning wsscd-notice wsscd-notice-info"
         style="display: <?php echo esc_attr( ( $enable_recurring && 'instances' === $recurrence_mode ) ? 'block' : 'none' ); ?>;"
         role="alert">
        <?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
        <div class="wsscd-notice-content">
            <strong><?php esc_html_e( 'Important: Instance Mode Considerations', 'smart-cycle-discounts' ); ?></strong>
            <p>
                <?php
                esc_html_e(
                    'Instance mode creates separate campaign copies for each occurrence. For best results:',
                    'smart-cycle-discounts'
                );
                ?>
            </p>
            <ul>
                <li><?php esc_html_e( 'Keep recurring periods to 6 months or less to avoid issues with product and price changes', 'smart-cycle-discounts' ); ?></li>
                <li><?php esc_html_e( 'Set an end date for your campaign (required for instance mode)', 'smart-cycle-discounts' ); ?></li>
                <li><?php esc_html_e( 'Review future occurrences if products, categories, or prices change significantly', 'smart-cycle-discounts' ); ?></li>
            </ul>
        </div>
    </div>

    <!-- Recurring Options (hidden by default) -->
    <div id="wsscd-recurring-options"
         class="wsscd-recurring-options"
         style="display: <?php echo esc_attr( ( $enable_recurring ) ? 'block' : 'none' ); ?>;"
         aria-hidden="<?php echo esc_attr( ( $enable_recurring ) ? 'false' : 'true' ); ?>">

        <!-- Repeat Every Field -->
        <div class="wsscd-recurring-field-group">
            <div class="wsscd-recurring-field-header">
                <?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 16, 'class' => 'wsscd-recurring-field-icon' ) ); ?>
                <label for="recurrence_interval" class="wsscd-recurring-field-label">
                    <?php esc_html_e( 'Repeat Every', 'smart-cycle-discounts' ); ?>
                    <span class="wsscd-required-indicator" aria-label="<?php esc_attr_e( 'Required when recurring is enabled', 'smart-cycle-discounts' ); ?>">*</span>
                </label>
                <?php
                WSSCD_Tooltip_Helper::render(
                    __( 'How often the discount campaign repeats', 'smart-cycle-discounts' ),
                    'wsscd-tooltip--right'
                );
                ?>
            </div>
            <div class="wsscd-recurring-field-content">
                <div class="wsscd-recurrence-input-group">
                    <input type="number"
                           id="recurrence_interval"
                           name="recurrence_interval"
                           value="<?php echo esc_attr( $recurrence_interval ); ?>"
                           min="1"
                           max="365"
                           step="1"
                           inputmode="numeric"
                           data-label="Recurrence Interval"
                           data-input-type="integer"
                           class="wsscd-input-small wsscd-enhanced-input"
                           aria-label="<?php esc_attr_e( 'Interval number', 'smart-cycle-discounts' ); ?>"
                           aria-describedby="recurrence-pattern-help">
                    <select id="recurrence_pattern"
                            name="recurrence_pattern"
                            class="wsscd-select-medium wsscd-enhanced-select"
                            aria-label="<?php esc_attr_e( 'Recurrence pattern', 'smart-cycle-discounts' ); ?>">
                        <option value="daily" <?php selected( $recurrence_pattern, 'daily' ); ?>>
                            <?php esc_html_e( 'Day(s)', 'smart-cycle-discounts' ); ?>
                        </option>
                        <option value="weekly" <?php selected( $recurrence_pattern, 'weekly' ); ?>>
                            <?php esc_html_e( 'Week(s)', 'smart-cycle-discounts' ); ?>
                        </option>
                        <option value="monthly" <?php selected( $recurrence_pattern, 'monthly' ); ?>>
                            <?php esc_html_e( 'Month(s)', 'smart-cycle-discounts' ); ?>
                        </option>
                    </select>
                </div>
                <p id="recurrence-pattern-help" class="wsscd-field-help">
                    <?php esc_html_e( 'Example: "2 Week(s)" means the campaign repeats every 2 weeks', 'smart-cycle-discounts' ); ?>
                </p>
            </div>
        </div>


        <!-- On Days Field (for weekly pattern) -->
        <div id="wsscd-weekly-options"
             class="wsscd-recurring-field-group wsscd-weekly-options"
             style="display: <?php echo esc_attr( ( 'weekly' === $recurrence_pattern ) ? 'block' : 'none' ); ?>;">
            <div class="wsscd-recurring-field-header">
                <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16, 'class' => 'wsscd-recurring-field-icon' ) ); ?>
                <label class="wsscd-recurring-field-label">
                    <?php esc_html_e( 'On Days', 'smart-cycle-discounts' ); ?>
                </label>
                <?php
                WSSCD_Tooltip_Helper::render(
                    __( 'Select which days of the week to run the campaign', 'smart-cycle-discounts' ),
                    'wsscd-tooltip--right'
                );
                ?>
            </div>
            <div class="wsscd-recurring-field-content">
                <div class="wsscd-days-selector" role="group" aria-label="<?php esc_attr_e( 'Select days of the week', 'smart-cycle-discounts' ); ?>">
                    <?php
                    $days = array(
                        'mon' => __( 'Mon', 'smart-cycle-discounts' ),
                        'tue' => __( 'Tue', 'smart-cycle-discounts' ),
                        'wed' => __( 'Wed', 'smart-cycle-discounts' ),
                        'thu' => __( 'Thu', 'smart-cycle-discounts' ),
                        'fri' => __( 'Fri', 'smart-cycle-discounts' ),
                        'sat' => __( 'Sat', 'smart-cycle-discounts' ),
                        'sun' => __( 'Sun', 'smart-cycle-discounts' )
                    );
                    $selected_days = (array) $recurrence_days;
                    foreach ( $days as $value => $label ) : ?>
                        <label class="wsscd-day-checkbox">
                            <input type="checkbox"
                                   name="recurrence_days[]"
                                   value="<?php echo esc_attr( $value ); ?>"
                                   <?php checked( in_array( $value, $selected_days, true ) ); ?>
                                   aria-label="<?php echo esc_attr( $label ); ?>">
                            <span class="wsscd-day-label"><?php echo esc_html( $label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="wsscd-field-help">
                    <?php esc_html_e( 'Select one or more days for weekly recurring campaigns', 'smart-cycle-discounts' ); ?>
                </p>
            </div>
        </div>


        <!-- End Condition Field -->
        <div class="wsscd-recurring-field-group">
            <div class="wsscd-recurring-field-header">
                <?php WSSCD_Icon_Helper::render( 'controls-skipforward', array( 'size' => 16, 'class' => 'wsscd-recurring-field-icon' ) ); ?>
                <label class="wsscd-recurring-field-label">
                    <?php esc_html_e( 'Ends', 'smart-cycle-discounts' ); ?>
                </label>
                <?php
                WSSCD_Tooltip_Helper::render(
                    __( 'When the recurring schedule should stop', 'smart-cycle-discounts' ),
                    'wsscd-tooltip--right'
                );
                ?>
            </div>
            <div class="wsscd-recurring-field-content">
                <div class="wsscd-recurrence-end-options" role="radiogroup" aria-label="<?php esc_attr_e( 'Recurrence end condition', 'smart-cycle-discounts' ); ?>">
                    <label class="wsscd-radio-inline wsscd-end-option">
                        <input type="radio"
                               name="recurrence_end_type"
                               value="never"
                               <?php checked( $recurrence_end_type, 'never' ); ?>
                               aria-describedby="recurrence-end-help">
                        <span class="wsscd-radio-label-text">
                            <?php WSSCD_Icon_Helper::render( 'infinity', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e( 'Never', 'smart-cycle-discounts' ); ?>
                        </span>
                    </label>

                    <label class="wsscd-radio-inline wsscd-end-option">
                        <input type="radio"
                               name="recurrence_end_type"
                               value="after"
                               <?php checked( $recurrence_end_type, 'after' ); ?>
                               aria-describedby="recurrence-end-help">
                        <span class="wsscd-radio-label-text">
                            <?php WSSCD_Icon_Helper::render( 'marker', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e( 'After', 'smart-cycle-discounts' ); ?>
                        </span>
                        <input type="number"
                               id="recurrence_count"
                               name="recurrence_count"
                               value="<?php echo esc_attr( $recurrence_count ); ?>"
                               min="1"
                               max="365"
                               step="1"
                               inputmode="numeric"
                               data-label="Occurrence Count"
                               data-input-type="integer"
                               class="wsscd-input-tiny wsscd-enhanced-input"
                               <?php disabled( 'after' !== $recurrence_end_type ); ?>
                               aria-label="<?php esc_attr_e( 'Number of occurrences', 'smart-cycle-discounts' ); ?>">
                        <span class="wsscd-occurrence-suffix"><?php esc_html_e( 'occurrences', 'smart-cycle-discounts' ); ?></span>
                    </label>

                    <label class="wsscd-radio-inline wsscd-end-option">
                        <input type="radio"
                               name="recurrence_end_type"
                               value="on"
                               <?php checked( $recurrence_end_type, 'on' ); ?>
                               aria-describedby="recurrence-end-help">
                        <span class="wsscd-radio-label-text">
                            <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e( 'On', 'smart-cycle-discounts' ); ?>
                        </span>
                        <div class="wsscd-date-picker-wrapper">
                            <input type="text"
                                   id="recurrence_end_date"
                                   name="recurrence_end_date"
                                   class="wsscd-date-picker wsscd-input-date"
                                   placeholder="<?php esc_attr_e( 'Select end date', 'smart-cycle-discounts' ); ?>"
                                   readonly
                                   value="<?php echo esc_attr( $recurrence_end_date ); ?>"
                                   <?php disabled( 'on' !== $recurrence_end_type ); ?>
                                   aria-label="<?php esc_attr_e( 'Recurrence end date', 'smart-cycle-discounts' ); ?>">
                            <button type="button"
                                    class="wsscd-calendar-icon"
                                    data-target="recurrence_end_date"
                                    aria-label="<?php esc_attr_e( 'Choose recurrence end date', 'smart-cycle-discounts' ); ?>">
                                <?php
                                if ( class_exists( 'WSSCD_Icon_Helper' ) ) {
                                    WSSCD_Icon_Helper::render(
                                        'calendar-alt',
                                        array(
                                            'size'        => 18,
                                            'class'       => 'wsscd-icon',
                                            'aria_hidden' => true,
                                        )
                                    );
                                }
                                ?>
                            </button>
                        </div>
                    </label>
                </div>
                <p id="recurrence-end-help" class="wsscd-field-help">
                    <?php esc_html_e( 'Choose when the recurring campaign should stop running', 'smart-cycle-discounts' ); ?>
                </p>
            </div>
        </div>


        <!-- Next Occurrences Preview -->
        <div class="wsscd-recurrence-preview" role="region" aria-label="<?php esc_attr_e( 'Recurring campaign preview', 'smart-cycle-discounts' ); ?>">
            <div class="wsscd-preview-header">
                <?php WSSCD_Icon_Helper::render( 'visibility', array( 'size' => 16, 'class' => 'wsscd-preview-icon' ) ); ?>
                <h4 class="wsscd-preview-title"><?php esc_html_e( 'Next Occurrences Preview', 'smart-cycle-discounts' ); ?></h4>
            </div>
            <div id="wsscd-recurrence-preview-text" class="wsscd-preview-text" aria-live="polite">
                <div class="wsscd-preview-empty">
                    <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
                    <p><?php esc_html_e( 'Configure recurrence settings above to see a preview of when your campaign will run', 'smart-cycle-discounts' ); ?></p>
                </div>
            </div>
            <div class="wsscd-preview-footer">
                <?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
                <span class="wsscd-preview-footer-text"><?php esc_html_e( 'Each occurrence will use the same campaign duration as configured in your schedule', 'smart-cycle-discounts' ); ?></span>
            </div>
        </div>
    </div>
    </div><!-- #wsscd-recurring-container -->
    <?php
    $recurring_content = ob_get_clean();

    wsscd_wizard_card( array(
        'title' => __( 'Recurring Schedule', 'smart-cycle-discounts' ),
        'icon' => 'backup',
        'badge' => array(
            'text' => __( 'Optional', 'smart-cycle-discounts' ),
            'type' => 'optional'
        ),
        'subtitle' => __( 'Set up your discount to repeat automatically on a regular schedule.', 'smart-cycle-discounts' ),
        'content' => $recurring_content,
        'class' => 'wsscd-card--recurring',
        'help_topic' => 'card-recurring-schedule'
    ) );
    ?>

    <!-- Schedule Status & Validation -->
    <div id="wsscd-schedule-status" 
         class="wsscd-status-panel"
         role="status"
         aria-live="polite"
         style="display: none;">
        <div class="wsscd-status-content">
            <span class="wsscd-status-icon" aria-hidden="true"></span>
            <span class="wsscd-status-message"></span>
        </div>
    </div>

    <!-- Save Progress Indicator -->
    <div id="wsscd-save-indicator" 
         class="wsscd-save-status"
         role="status"
         aria-live="polite"
         style="display: none;">
        <?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 16, 'class' => 'wsscd-spin' ) ); ?>
        <span class="wsscd-save-text">
            <?php esc_html_e( 'Saving changes...', 'smart-cycle-discounts' ); ?>
        </span>
    </div>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
wsscd_wizard_render_step( array(
    'title' => __( 'Schedule & Timing', 'smart-cycle-discounts' ),
    'description' => __( 'Set when your discount campaign will be active', 'smart-cycle-discounts' ),
    'content' => $content,
    'step' => 'schedule'
) );
?>

<!-- Initialize state data for Schedule step -->
<?php
// Validation rules are now handled by the centralized field schema system

wsscd_wizard_state_script( 'schedule', array(
    'start_type' => $start_type,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'timezone' => $timezone_value,
    'enable_recurring' => $enable_recurring,
    'recurrence_mode' => $recurrence_mode,
    'recurrence_pattern' => $recurrence_pattern,
    'recurrence_interval' => $recurrence_interval,
    'recurrence_days' => $recurrence_days,
    'recurrence_end_type' => $recurrence_end_type,
    'recurrence_count' => $recurrence_count,
    'recurrence_end_date' => $recurrence_end_date
) );
?>


