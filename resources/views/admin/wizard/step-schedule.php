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

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Security: Verify user capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( __( 'You do not have permission to access this page.', 'smart-cycle-discounts' ) );
}

// Initialize variables using shared function
scd_wizard_init_step_vars( $step_data, $validation_errors );

// Get current timezone and locale info
$timezone = wp_timezone_string();
$current_time = current_time( 'Y-m-d\TH:i' );
$locale = get_locale();
$is_rtl = is_rtl();

// Extract values with defaults handled by field schema
$start_type = $step_data['start_type'] ?? 'immediate';
$start_date = $step_data['start_date'] ?? date( 'Y-m-d' );
$end_date = $step_data['end_date'] ?? '';  // Default empty = runs indefinitely
$start_time = $step_data['start_time'] ?? '00:00';
$end_time = ! empty( $end_date ) ? ( $step_data['end_time'] ?? '23:59' ) : '';
$timezone_value = $step_data['timezone'] ?? $timezone;
// Force enable_recurring to false for free users (server-side safeguard)
$can_use_recurring_init = $feature_gate ? $feature_gate->can_use_recurring_campaigns() : false;
$enable_recurring = ( $step_data['enable_recurring'] ?? false ) && $can_use_recurring_init;
$recurrence_pattern = $step_data['recurrence_pattern'] ?? 'daily';
$recurrence_interval = $step_data['recurrence_interval'] ?? 1;
$recurrence_days = $step_data['recurrence_days'] ?? array();
$recurrence_end_type = $step_data['recurrence_end_type'] ?? 'never';
$recurrence_count = $step_data['recurrence_count'] ?? 10;
$recurrence_end_date = $step_data['recurrence_end_date'] ?? '';

// Prepare content for template wrapper
ob_start();
?>
    <?php scd_wizard_validation_notice( $validation_errors ); ?>

    <!-- Screen reader announcement area -->
    <div id="scd-schedule-announcements" 
         class="sr-only" 
         aria-live="polite" 
         aria-atomic="true">
    </div>

    <!-- Loading state overlay -->
    <?php scd_wizard_loading_indicator( 'scd-schedule-loading', __( 'Loading schedule options', 'smart-cycle-discounts' ) ); ?>

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
    <div class="scd-preset-grid" id="scd-preset-recommendations" role="group" aria-label="<?php esc_attr_e( 'Campaign duration presets', 'smart-cycle-discounts' ); ?>">
        <!-- Preset options will be populated by JavaScript -->
        <div class="scd-preset-loading">
            <span class="spinner is-active"></span>
            <?php esc_html_e( 'Loading recommendations...', 'smart-cycle-discounts' ); ?>
        </div>
    </div>
    <?php
    $preset_content = ob_get_clean();
    
    scd_wizard_card( array(
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
    <div class="scd-form-section">
        <div class="scd-form-section-header">
            <h3 class="scd-form-section-title">
                <?php esc_html_e( 'Campaign Start Type', 'smart-cycle-discounts' ); ?>
            </h3>
        </div>
        <div class="scd-schedule-selection-cards">
            <label class="scd-card-option">
                <input type="radio" 
                       name="start_type" 
                       value="immediate" 
                       <?php checked( $start_type, 'immediate' ); ?>>
                <div class="scd-card__content">
                    <h4>
                        <?php echo SCD_Icon_Helper::get( 'play', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e( 'Start Immediately', 'smart-cycle-discounts' ); ?>
                    </h4>
                    <p><?php esc_html_e( 'Campaign begins as soon as it\'s launched. Perfect for urgent promotions or flash sales.', 'smart-cycle-discounts' ); ?></p>
                </div>
            </label>
            <label class="scd-card-option">
                <input type="radio" 
                       name="start_type" 
                       value="scheduled" 
                       <?php checked( $start_type, 'scheduled' ); ?>>
                <div class="scd-card__content">
                    <h4>
                        <?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e( 'Scheduled Start', 'smart-cycle-discounts' ); ?>
                    </h4>
                    <p><?php esc_html_e( 'Set a specific date and time to begin. Ideal for planned campaigns and seasonal promotions.', 'smart-cycle-discounts' ); ?></p>
                </div>
            </label>
        </div>
    </div>

    <!-- Schedule Dates Section -->
    <div class="scd-form-section">
        <div class="scd-form-section-header">
            <h4 class="scd-form-section-title">
                <?php esc_html_e( 'Schedule Dates', 'smart-cycle-discounts' ); ?>
            </h4>
        </div>

    <!-- Start Date - Only visible when "scheduled" is selected -->
    <div class="scd-scheduled-start-fields"<?php if ( 'immediate' === $start_type ) { echo ' style="display: none;"'; } ?>>
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
        <div class="scd-datetime-wrapper">
            <!-- Date picker -->
            <div class="scd-date-picker-wrapper">
                <input type="text"
                       id="start_date_display"
                       class="scd-date-picker <?php echo isset( $validation_errors['start_date'] ) ? 'error' : ''; ?>"
                       placeholder="<?php esc_attr_e( 'Select date', 'smart-cycle-discounts' ); ?>"
                       readonly
                       value="<?php echo esc_attr( $start_date ); ?>"
                       aria-describedby="start_date_help"
                       <?php if ( 'immediate' === $start_type ) { echo 'tabindex="-1"'; } ?>>
                <button type="button"
                        class="scd-calendar-icon"
                        data-target="start_date_display"
                        aria-label="<?php esc_attr_e( 'Choose start date', 'smart-cycle-discounts' ); ?>"
                        <?php if ( 'immediate' === $start_type ) { echo 'tabindex="-1"'; } ?>>
                    <?php
                    if ( class_exists( 'SCD_Icon_Helper' ) ) {
                        echo SCD_Icon_Helper::get(
                            'calendar-alt',
                            array(
                                'size'        => 18,
                                'class'       => 'scd-icon',
                                'aria_hidden' => true,
                            )
                        );
                    }
                    ?>
                </button>
            </div>

            <!-- Time picker -->
            <div class="scd-time-picker-wrapper">
                <span class="scd-at-label"><?php esc_html_e( 'at', 'smart-cycle-discounts' ); ?></span>
                <input type="time"
                       id="start_time"
                       name="start_time"
                       value="<?php echo esc_attr( $start_time ); ?>"
                       class="scd-time-picker <?php echo isset( $validation_errors['start_time'] ) ? 'error' : ''; ?>"
                       aria-label="<?php esc_attr_e( 'Start time', 'smart-cycle-discounts' ); ?>"
                       <?php if ( 'immediate' === $start_type ) { echo 'tabindex="-1"'; } ?>>
            </div>
        </div>
        <?php scd_wizard_field_errors( $validation_errors, 'start_date' ); ?>
        <?php $start_date_content = ob_get_clean(); ?>

        <!-- Modern flexbox field layout -->
        <div class="scd-field-group">
            <div class="scd-field-header">
                <label class="scd-field-label" for="start_date_display">
                    <?php esc_html_e( 'Start Date', 'smart-cycle-discounts' ); ?>
                    <span class="scd-required-indicator" aria-label="<?php esc_attr_e( 'Required', 'smart-cycle-discounts' ); ?>">*</span>
                </label>
                <?php
                SCD_Tooltip_Helper::render(
                    __( 'When your discount campaign begins. Recent dates (within 5 minutes) are allowed to account for clock differences and processing time.', 'smart-cycle-discounts' )
                );
                ?>
            </div>
            <div class="scd-field-content">
                <?php echo $start_date_content; ?>
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
        <div class="scd-datetime-wrapper">
            <!-- Date picker -->
            <div class="scd-date-picker-wrapper">
                <input type="text"
                       id="end_date_display"
                       class="scd-date-picker <?php echo isset( $validation_errors['end_date'] ) ? 'error' : ''; ?>"
                       placeholder="<?php esc_attr_e( 'Runs indefinitely', 'smart-cycle-discounts' ); ?>"
                       readonly
                       value="<?php echo esc_attr( $end_date ); ?>"
                       aria-describedby="end_date_help">
                <button type="button"
                        class="scd-calendar-icon"
                        data-target="end_date_display"
                        aria-label="<?php esc_attr_e( 'Choose end date', 'smart-cycle-discounts' ); ?>">
                    <?php
                    if ( class_exists( 'SCD_Icon_Helper' ) ) {
                        echo SCD_Icon_Helper::get(
                            'calendar-alt',
                            array(
                                'size'        => 18,
                                'class'       => 'scd-icon',
                                'aria_hidden' => true,
                            )
                        );
                    }
                    ?>
                </button>
            </div>

            <!-- Time picker -->
            <div class="scd-time-picker-wrapper">
                <span class="scd-at-label"><?php esc_html_e( 'at', 'smart-cycle-discounts' ); ?></span>
                <input type="<?php echo empty( $end_date ) ? 'text' : 'time'; ?>"
                       id="end_time"
                       name="end_time"
                       value="<?php echo empty( $end_date ) ? '--:--' : esc_attr( $end_time ); ?>"
                       class="scd-time-picker <?php echo empty( $end_date ) ? 'scd-time-placeholder' : ''; ?><?php echo isset( $validation_errors['end_time'] ) ? ' error' : ''; ?>"
                       <?php echo empty( $end_date ) ? 'disabled' : ''; ?>
                       aria-label="<?php esc_attr_e( 'End time', 'smart-cycle-discounts' ); ?>">
            </div>

            <!-- Option to clear end date -->
            <?php
            SCD_Button_Helper::icon(
                'no-alt',
                __( 'Clear end date and time', 'smart-cycle-discounts' ),
                array(
                    'style'      => 'ghost-danger',
                    'size'       => 'small',
                    'classes'    => array( 'scd-clear-end-date' ),
                    'attributes' => array( 'title' => __( 'Run indefinitely', 'smart-cycle-discounts' ) ),
                )
            );
            ?>
        </div>

        <?php scd_wizard_field_errors( $validation_errors, 'end_date' ); ?>
        <?php $end_date_content = ob_get_clean(); ?>

        <!-- Modern flexbox field layout -->
        <div class="scd-field-group">
            <div class="scd-field-header">
                <label class="scd-field-label" for="end_date_display">
                    <?php esc_html_e( 'End Date', 'smart-cycle-discounts' ); ?>
                </label>
                <?php
                SCD_Tooltip_Helper::render(
                    __( 'When your discount campaign ends. Leave empty to run indefinitely', 'smart-cycle-discounts' )
                );
                ?>
            </div>
            <div class="scd-field-content">
                <?php echo $end_date_content; ?>
            </div>
        </div>
    </div><!-- .scd-form-section (Schedule Dates) -->
    
    <!-- Duration Display -->
    <div id="scd-duration-display" 
         class="scd-duration-display"
         role="status"
         aria-live="polite">
        <div class="scd-duration-content">
            <?php echo SCD_Icon_Helper::get( 'clock', array( 'size' => 16, 'class' => 'scd-duration-icon' ) ); ?>
            <span class="scd-duration-label"><?php esc_html_e( 'Duration:', 'smart-cycle-discounts' ); ?></span>
            <span id="scd-duration-text" class="scd-duration-value">
                <?php esc_html_e( '7 days (168 hours)', 'smart-cycle-discounts' ); ?>
            </span>
        </div>
        <div id="scd-duration-hint" class="scd-duration-hint">
            <?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16, 'class' => 'scd-hint-icon' ) ); ?>
            <span class="scd-hint-text"><?php esc_html_e( 'Perfect for weekly promotions', 'smart-cycle-discounts' ); ?></span>
        </div>
    </div>
    
    <!-- Timezone indicator -->
    <div class="scd-timezone-indicator scd-timezone-indicator--prominent">
        <div class="scd-timezone-content">
            <?php echo SCD_Icon_Helper::get( 'admin-site-alt3', array( 'size' => 16, 'class' => 'scd-timezone-icon' ) ); ?>
            <span class="scd-timezone-label"><?php esc_html_e( 'Campaign timezone:', 'smart-cycle-discounts' ); ?></span>
            <strong class="scd-timezone-value"><?php echo esc_html( $timezone ); ?></strong>
        </div>
        <div class="scd-timezone-note">
            <?php
            if ( 'immediate' === $start_type ) {
                printf(
                    /* translators: %s: WordPress site timezone */
                    esc_html__( 'This campaign will start immediately using the server time in %s timezone.', 'smart-cycle-discounts' ),
                    '<strong>' . esc_html( $timezone ) . '</strong>'
                );
            } else {
                printf(
                    /* translators: %s: WordPress site timezone */
                    esc_html__( 'All dates and times use the %s timezone. Please schedule accordingly.', 'smart-cycle-discounts' ),
                    '<strong>' . esc_html( $timezone ) . '</strong>'
                );
            }
            ?>
        </div>
    </div>
    <?php
    $schedule_content = ob_get_clean();
    
    scd_wizard_card( array(
        'title' => __( 'Schedule Configuration', 'smart-cycle-discounts' ),
        'subtitle' => __( 'Configure when your campaign should run. All times are in your store timezone.', 'smart-cycle-discounts' ),
        'icon' => 'calendar-alt',
        'content' => $schedule_content,
        'help_topic' => 'card-schedule-config'
    ) );
    ?>

    <!-- Recurring Schedule Card -->
    <?php
    // Check if user can use recurring campaigns
    $can_use_recurring = $feature_gate ? $feature_gate->can_use_recurring_campaigns() : false;
    $upgrade_url = $feature_gate ? $feature_gate->get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

    ob_start();
    ?>

    <div class="scd-pro-container <?php echo $can_use_recurring ? '' : 'scd-pro-container--locked'; ?>" id="scd-recurring-container">
        <?php if ( ! $can_use_recurring ) : ?>
            <?php
            // Use centralized PRO overlay template
            $description = __( 'Automated recurring campaign schedules', 'smart-cycle-discounts' );
            $features = array(
                __( 'Daily, weekly, monthly patterns', 'smart-cycle-discounts' ),
                __( 'Custom recurrence intervals', 'smart-cycle-discounts' ),
                __( 'Specific days of week selection', 'smart-cycle-discounts' ),
                __( 'End date or occurrence limits', 'smart-cycle-discounts' ),
            );
            include SCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-overlay.php';
            ?>
        <?php endif; ?>

        <!-- Actual Recurring UI (blurred for free users) -->
        <div class="scd-pro-background">
    <!-- Enable Recurring Toggle -->
    <div class="scd-form-row">
        <label class="scd-toggle-control">
            <input type="checkbox"
                   id="enable_recurring"
                   name="enable_recurring"
                   value="1"
                   <?php checked( $enable_recurring && $can_use_recurring, true ); ?>
                   <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>>
            <span class="scd-toggle-slider" aria-hidden="true"></span>
            <span class="scd-toggle-label">
                <?php esc_html_e( 'Enable recurring schedule', 'smart-cycle-discounts' ); ?>
            </span>
        </label>
    </div>

    <!-- Recurring Campaign Important Notice -->
    <div id="scd-recurring-warning"
         class="scd-recurring-warning scd-notice scd-notice-info"
         style="display: <?php echo ( $enable_recurring ) ? 'block' : 'none'; ?>;"
         role="alert">
        <?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
        <div class="scd-notice-content">
            <strong><?php esc_html_e( 'Important: Recurring Campaign Considerations', 'smart-cycle-discounts' ); ?></strong>
            <p>
                <?php
                esc_html_e(
                    'Recurring campaigns create future occurrences based on your current campaign configuration. For best results:',
                    'smart-cycle-discounts'
                );
                ?>
            </p>
            <ul>
                <li><?php esc_html_e( 'Keep recurring periods to 6 months or less to avoid issues with product and price changes', 'smart-cycle-discounts' ); ?></li>
                <li><?php esc_html_e( 'Set an end date for your campaign (required for recurring campaigns)', 'smart-cycle-discounts' ); ?></li>
                <li><?php esc_html_e( 'Review future occurrences if products, categories, or prices change significantly', 'smart-cycle-discounts' ); ?></li>
            </ul>
        </div>
    </div>

    <!-- Recurring Options (hidden by default) -->
    <div id="scd-recurring-options"
         class="scd-recurring-options"
         style="display: <?php echo ( $enable_recurring ) ? 'block' : 'none'; ?>;"
         aria-hidden="<?php echo ( $enable_recurring ) ? 'false' : 'true'; ?>">

        <!-- Repeat Every Field -->
        <div class="scd-recurring-field-group">
            <div class="scd-recurring-field-header">
                <?php echo SCD_Icon_Helper::get( 'update', array( 'size' => 16, 'class' => 'scd-recurring-field-icon' ) ); ?>
                <label for="recurrence_interval" class="scd-recurring-field-label">
                    <?php esc_html_e( 'Repeat Every', 'smart-cycle-discounts' ); ?>
                    <span class="scd-required-indicator" aria-label="<?php esc_attr_e( 'Required when recurring is enabled', 'smart-cycle-discounts' ); ?>">*</span>
                </label>
                <?php
                SCD_Tooltip_Helper::render(
                    __( 'How often the discount campaign repeats', 'smart-cycle-discounts' ),
                    'scd-tooltip--right'
                );
                ?>
            </div>
            <div class="scd-recurring-field-content">
                <div class="scd-recurrence-input-group">
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
                           class="scd-input-small scd-enhanced-input"
                           <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>
                           aria-label="<?php esc_attr_e( 'Interval number', 'smart-cycle-discounts' ); ?>"
                           aria-describedby="recurrence-pattern-help">
                    <select id="recurrence_pattern"
                            name="recurrence_pattern"
                            class="scd-select-medium scd-enhanced-select"
                            <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>
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
                <p id="recurrence-pattern-help" class="scd-field-help">
                    <?php esc_html_e( 'Example: "2 Week(s)" means the campaign repeats every 2 weeks', 'smart-cycle-discounts' ); ?>
                </p>
            </div>
        </div>


        <!-- On Days Field (for weekly pattern) -->
        <div id="scd-weekly-options"
             class="scd-recurring-field-group scd-weekly-options"
             style="display: <?php echo ( 'weekly' === $recurrence_pattern ) ? 'block' : 'none'; ?>;">
            <div class="scd-recurring-field-header">
                <?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16, 'class' => 'scd-recurring-field-icon' ) ); ?>
                <label class="scd-recurring-field-label">
                    <?php esc_html_e( 'On Days', 'smart-cycle-discounts' ); ?>
                </label>
                <?php
                SCD_Tooltip_Helper::render(
                    __( 'Select which days of the week to run the campaign', 'smart-cycle-discounts' ),
                    'scd-tooltip--right'
                );
                ?>
            </div>
            <div class="scd-recurring-field-content">
                <div class="scd-days-selector" role="group" aria-label="<?php esc_attr_e( 'Select days of the week', 'smart-cycle-discounts' ); ?>">
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
                        <label class="scd-day-checkbox">
                            <input type="checkbox"
                                   name="recurrence_days[]"
                                   value="<?php echo esc_attr( $value ); ?>"
                                   <?php checked( in_array( $value, $selected_days, true ) ); ?>
                                   <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>
                                   aria-label="<?php echo esc_attr( $label ); ?>">
                            <span class="scd-day-label"><?php echo esc_html( $label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="scd-field-help">
                    <?php esc_html_e( 'Select one or more days for weekly recurring campaigns', 'smart-cycle-discounts' ); ?>
                </p>
            </div>
        </div>


        <!-- End Condition Field -->
        <div class="scd-recurring-field-group">
            <div class="scd-recurring-field-header">
                <?php echo SCD_Icon_Helper::get( 'controls-skipforward', array( 'size' => 16, 'class' => 'scd-recurring-field-icon' ) ); ?>
                <label class="scd-recurring-field-label">
                    <?php esc_html_e( 'Ends', 'smart-cycle-discounts' ); ?>
                </label>
                <?php
                SCD_Tooltip_Helper::render(
                    __( 'When the recurring schedule should stop', 'smart-cycle-discounts' ),
                    'scd-tooltip--right'
                );
                ?>
            </div>
            <div class="scd-recurring-field-content">
                <div class="scd-recurrence-end-options" role="radiogroup" aria-label="<?php esc_attr_e( 'Recurrence end condition', 'smart-cycle-discounts' ); ?>">
                    <label class="scd-radio-inline scd-end-option">
                        <input type="radio"
                               name="recurrence_end_type"
                               value="never"
                               <?php checked( $recurrence_end_type, 'never' ); ?>
                               <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>
                               aria-describedby="recurrence-end-help">
                        <span class="scd-radio-label-text">
                            <?php echo SCD_Icon_Helper::get( 'infinity', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e( 'Never', 'smart-cycle-discounts' ); ?>
                        </span>
                    </label>

                    <label class="scd-radio-inline scd-end-option">
                        <input type="radio"
                               name="recurrence_end_type"
                               value="after"
                               <?php checked( $recurrence_end_type, 'after' ); ?>
                               <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>
                               aria-describedby="recurrence-end-help">
                        <span class="scd-radio-label-text">
                            <?php echo SCD_Icon_Helper::get( 'marker', array( 'size' => 16 ) ); ?>
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
                               class="scd-input-tiny scd-enhanced-input"
                               <?php disabled( $recurrence_end_type !== 'after' || ! $can_use_recurring ); ?>
                               aria-label="<?php esc_attr_e( 'Number of occurrences', 'smart-cycle-discounts' ); ?>">
                        <span class="scd-occurrence-suffix"><?php esc_html_e( 'occurrences', 'smart-cycle-discounts' ); ?></span>
                    </label>

                    <label class="scd-radio-inline scd-end-option">
                        <input type="radio"
                               name="recurrence_end_type"
                               value="on"
                               <?php checked( $recurrence_end_type, 'on' ); ?>
                               <?php if ( ! $can_use_recurring ): ?>disabled<?php endif; ?>
                               aria-describedby="recurrence-end-help">
                        <span class="scd-radio-label-text">
                            <?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16 ) ); ?>
                            <?php esc_html_e( 'On', 'smart-cycle-discounts' ); ?>
                        </span>
                        <div class="scd-date-picker-wrapper">
                            <input type="text"
                                   id="recurrence_end_date"
                                   name="recurrence_end_date"
                                   class="scd-date-picker scd-input-date"
                                   placeholder="<?php esc_attr_e( 'Select end date', 'smart-cycle-discounts' ); ?>"
                                   readonly
                                   value="<?php echo esc_attr( $recurrence_end_date ); ?>"
                                   <?php disabled( $recurrence_end_type !== 'on' || ! $can_use_recurring ); ?>
                                   aria-label="<?php esc_attr_e( 'Recurrence end date', 'smart-cycle-discounts' ); ?>">
                            <button type="button"
                                    class="scd-calendar-icon"
                                    data-target="recurrence_end_date"
                                    aria-label="<?php esc_attr_e( 'Choose recurrence end date', 'smart-cycle-discounts' ); ?>">
                                <?php
                                if ( class_exists( 'SCD_Icon_Helper' ) ) {
                                    echo SCD_Icon_Helper::get(
                                        'calendar-alt',
                                        array(
                                            'size'        => 18,
                                            'class'       => 'scd-icon',
                                            'aria_hidden' => true,
                                        )
                                    );
                                }
                                ?>
                            </button>
                        </div>
                    </label>
                </div>
                <p id="recurrence-end-help" class="scd-field-help">
                    <?php esc_html_e( 'Choose when the recurring campaign should stop running', 'smart-cycle-discounts' ); ?>
                </p>
            </div>
        </div>


        <!-- Next Occurrences Preview -->
        <div class="scd-recurrence-preview" role="region" aria-label="<?php esc_attr_e( 'Recurring campaign preview', 'smart-cycle-discounts' ); ?>">
            <div class="scd-preview-header">
                <?php echo SCD_Icon_Helper::get( 'visibility', array( 'size' => 16, 'class' => 'scd-preview-icon' ) ); ?>
                <h4 class="scd-preview-title"><?php esc_html_e( 'Next Occurrences Preview', 'smart-cycle-discounts' ); ?></h4>
            </div>
            <div id="scd-recurrence-preview-text" class="scd-preview-text" aria-live="polite">
                <div class="scd-preview-empty">
                    <?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16 ) ); ?>
                    <p><?php esc_html_e( 'Configure recurrence settings above to see a preview of when your campaign will run', 'smart-cycle-discounts' ); ?></p>
                </div>
            </div>
            <div class="scd-preview-footer">
                <?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
                <span class="scd-preview-footer-text"><?php esc_html_e( 'Each occurrence will use the same campaign duration as configured in your schedule', 'smart-cycle-discounts' ); ?></span>
            </div>
        </div>
    </div>
        </div><!-- .scd-pro-background -->
    </div><!-- .scd-pro-container -->
    <?php
    $recurring_content = ob_get_clean();

    scd_wizard_card( array(
        'title' => __( 'Recurring Schedule', 'smart-cycle-discounts' ),
        'icon' => 'backup',
        'badge' => array(
            'text' => __( 'Optional', 'smart-cycle-discounts' ),
            'type' => 'optional'
        ),
        'subtitle' => __( 'Set up your discount to repeat automatically on a regular schedule.', 'smart-cycle-discounts' ),
        'content' => $recurring_content,
        'class' => 'scd-card--recurring',
        'help_topic' => 'card-recurring-schedule'
    ) );
    ?>

    <!-- Schedule Status & Validation -->
    <div id="scd-schedule-status" 
         class="scd-status-panel"
         role="status"
         aria-live="polite"
         style="display: none;">
        <div class="scd-status-content">
            <span class="scd-status-icon" aria-hidden="true"></span>
            <span class="scd-status-message"></span>
        </div>
    </div>

    <!-- Save Progress Indicator -->
    <div id="scd-save-indicator" 
         class="scd-save-status"
         role="status"
         aria-live="polite"
         style="display: none;">
        <?php echo SCD_Icon_Helper::get( 'update', array( 'size' => 16, 'class' => 'scd-spin' ) ); ?>
        <span class="scd-save-text">
            <?php esc_html_e( 'Saving changes...', 'smart-cycle-discounts' ); ?>
        </span>
    </div>
<?php
// Get the content
$content = ob_get_clean();

// Render using template wrapper with sidebar
scd_wizard_render_step( array(
    'title' => __( 'Schedule & Timing', 'smart-cycle-discounts' ),
    'description' => __( 'Set when your discount campaign will be active', 'smart-cycle-discounts' ),
    'content' => $content,
    'step' => 'schedule'
) );
?>

<!-- Initialize state data for Schedule step -->
<?php
// Validation rules are now handled by the centralized field schema system

scd_wizard_state_script( 'schedule', array(
    'start_type' => $start_type,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'timezone' => $timezone_value,
    'enable_recurring' => $enable_recurring,
    'recurrence_pattern' => $recurrence_pattern,
    'recurrence_interval' => $recurrence_interval,
    'recurrence_days' => $recurrence_days,
    'recurrence_end_type' => $recurrence_end_type,
    'recurrence_count' => $recurrence_count,
    'recurrence_end_date' => $recurrence_end_date
) );
?>


