<?php
/**
 * Campaign wizard - Modern Schedule & timing step
 *
 * Comprehensive, secure, accessible schedule configuration
 *
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
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

    <!-- Primary Schedule Configuration - Campaign Period -->
    <?php
    ob_start();
    ?>
    <!-- Hidden inputs for form submission -->
    <input type="hidden" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" data-field="start_date">
    <input type="hidden" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" data-field="end_date">

    <!-- Start Type Toggle -->
    <div class="wsscd-period-start-toggle">
        <span class="wsscd-period-start-toggle__label"><?php esc_html_e( 'Start:', 'smart-cycle-discounts' ); ?></span>
        <div class="wsscd-toggle-buttons" role="radiogroup" aria-label="<?php esc_attr_e( 'Campaign start type', 'smart-cycle-discounts' ); ?>">
            <label class="wsscd-toggle-btn <?php echo 'immediate' === $start_type ? 'wsscd-toggle-btn--active' : ''; ?>">
                <input type="radio" name="start_type" value="immediate" <?php checked( $start_type, 'immediate' ); ?>>
                <?php WSSCD_Icon_Helper::render( 'controls-play', array( 'size' => 14 ) ); ?>
                <span><?php esc_html_e( 'Immediately', 'smart-cycle-discounts' ); ?></span>
            </label>
            <label class="wsscd-toggle-btn <?php echo 'scheduled' === $start_type ? 'wsscd-toggle-btn--active' : ''; ?>">
                <input type="radio" name="start_type" value="scheduled" <?php checked( $start_type, 'scheduled' ); ?>>
                <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 14 ) ); ?>
                <span><?php esc_html_e( 'Scheduled', 'smart-cycle-discounts' ); ?></span>
            </label>
        </div>
    </div>

    <!-- Horizontal Date Range -->
    <div class="wsscd-date-range" id="wsscd-schedule-date-range">
        <!-- Start Date Box -->
        <div class="wsscd-date-box wsscd-date-box--start <?php echo 'immediate' === $start_type ? 'wsscd-date-box--immediate' : ''; ?>">
            <div class="wsscd-date-box__label">
                <?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 16 ) ); ?>
                <span><?php esc_html_e( 'From', 'smart-cycle-discounts' ); ?></span>
            </div>

            <!-- Immediate state display -->
            <div class="wsscd-date-box__immediate" <?php echo 'scheduled' === $start_type ? 'style="display: none;"' : ''; ?>>
                <span class="wsscd-date-box__immediate-icon" aria-hidden="true"><?php WSSCD_Icon_Helper::render( 'play', array( 'size' => 24 ) ); ?></span>
                <span class="wsscd-date-box__date-text"><?php esc_html_e( 'Now', 'smart-cycle-discounts' ); ?></span>
                <span class="wsscd-date-box__time-text"><?php esc_html_e( 'Upon activation', 'smart-cycle-discounts' ); ?></span>
            </div>

            <!-- Scheduled state inputs -->
            <div class="wsscd-date-box__scheduled wsscd-scheduled-start-fields" <?php echo 'immediate' === $start_type ? 'style="display: none;"' : ''; ?>>
                <div class="wsscd-date-box__date">
                    <input type="text"
                           id="start_date_display"
                           class="wsscd-date-input <?php echo esc_attr( isset( $validation_errors['start_date'] ) ? 'error' : '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Select date', 'smart-cycle-discounts' ); ?>"
                           readonly
                           value="<?php echo esc_attr( $start_date ); ?>"
                           aria-label="<?php esc_attr_e( 'Start date', 'smart-cycle-discounts' ); ?>">
                    <button type="button" class="wsscd-date-box__calendar-btn" data-target="start_date_display" aria-label="<?php esc_attr_e( 'Choose start date', 'smart-cycle-discounts' ); ?>">
                        <?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 16 ) ); ?>
                    </button>
                </div>
                <div class="wsscd-time-dropdown-wrap">
                    <input type="hidden"
                           id="start_time"
                           name="start_time"
                           value="<?php echo esc_attr( $start_time ); ?>"
                           data-field="start_time">
                    <div class="wsscd-time-trigger" data-target="start_time" role="group" aria-label="<?php esc_attr_e( 'Start time', 'smart-cycle-discounts' ); ?>">
                        <input type="text"
                               class="wsscd-time-trigger__input"
                               value="<?php echo esc_attr( $start_time ); ?>"
                               placeholder="--:--"
                               autocomplete="off"
                               aria-label="<?php esc_attr_e( 'Start time', 'smart-cycle-discounts' ); ?>"
                               aria-haspopup="listbox"
                               aria-expanded="false">
                        <button type="button"
                                class="wsscd-time-trigger__btn"
                                aria-label="<?php esc_attr_e( 'Choose time', 'smart-cycle-discounts' ); ?>">
                            <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 12, 'class' => 'wsscd-time-trigger__icon' ) ); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php wsscd_wizard_field_errors( $validation_errors, 'start_date' ); ?>
        </div>

        <!-- Arrow Connector -->
        <div class="wsscd-date-range__connector">
            <span class="wsscd-date-range__arrow">→</span>
        </div>

        <!-- End Date Box -->
        <div class="wsscd-date-box wsscd-date-box--end <?php echo empty( $end_date ) ? 'wsscd-date-box--indefinite' : ''; ?>">
            <div class="wsscd-date-box__label">
                <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
                <span><?php esc_html_e( 'Until', 'smart-cycle-discounts' ); ?></span>
            </div>

            <div class="wsscd-date-box__content">
                <div class="wsscd-date-box__date">
                    <input type="text"
                           id="end_date_display"
                           class="wsscd-date-input <?php echo esc_attr( isset( $validation_errors['end_date'] ) ? 'error' : '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Runs Indefinitely', 'smart-cycle-discounts' ); ?>"
                           readonly
                           value="<?php echo esc_attr( $end_date ); ?>"
                           aria-label="<?php esc_attr_e( 'End date', 'smart-cycle-discounts' ); ?>">
                    <button type="button" class="wsscd-date-box__calendar-btn" data-target="end_date_display" aria-label="<?php esc_attr_e( 'Choose end date', 'smart-cycle-discounts' ); ?>">
                        <?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 16 ) ); ?>
                    </button>
                </div>
                <div class="wsscd-time-dropdown-wrap <?php echo esc_attr( empty( $end_date ) ? 'wsscd-time-dropdown-wrap--disabled' : '' ); ?>">
                    <input type="hidden"
                           id="end_time"
                           name="end_time"
                           value="<?php echo esc_attr( empty( $end_date ) ? '' : $end_time ); ?>"
                           data-field="end_time">
                    <div class="wsscd-time-trigger <?php echo empty( $end_date ) ? 'wsscd-time-trigger--disabled' : ''; ?>" data-target="end_time" role="group" aria-label="<?php esc_attr_e( 'End time', 'smart-cycle-discounts' ); ?>">
                        <input type="text"
                               class="wsscd-time-trigger__input <?php echo empty( $end_date ) ? 'wsscd-time-trigger__input--placeholder' : ''; ?>"
                               value="<?php echo esc_attr( empty( $end_date ) ? '' : $end_time ); ?>"
                               placeholder="--:--"
                               autocomplete="off"
                               aria-label="<?php esc_attr_e( 'End time', 'smart-cycle-discounts' ); ?>"
                               aria-haspopup="listbox"
                               aria-expanded="false"
                               <?php echo empty( $end_date ) ? ' disabled' : ''; ?>>
                        <button type="button"
                                class="wsscd-time-trigger__btn"
                                aria-label="<?php esc_attr_e( 'Choose time', 'smart-cycle-discounts' ); ?>"
                                <?php echo empty( $end_date ) ? ' disabled' : ''; ?>>
                            <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 12, 'class' => 'wsscd-time-trigger__icon' ) ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Clear button -->
            <button type="button"
                    class="wsscd-date-box__clear wsscd-clear-end-date"
                    title="<?php esc_attr_e( 'Run indefinitely', 'smart-cycle-discounts' ); ?>"
                    aria-label="<?php esc_attr_e( 'Clear end date', 'smart-cycle-discounts' ); ?>"
                    <?php echo empty( $end_date ) ? 'style="display: none;"' : ''; ?>>
                <?php WSSCD_Icon_Helper::render( 'no-alt', array( 'size' => 14 ) ); ?>
            </button>
            <?php wsscd_wizard_field_errors( $validation_errors, 'end_date' ); ?>
        </div>
    </div>

    <!-- Compact Duration Bar -->
    <div id="wsscd-duration-display" class="wsscd-duration-bar" role="status" aria-live="polite">
        <?php WSSCD_Icon_Helper::render( 'clock', array( 'size' => 16, 'class' => 'wsscd-duration-bar__icon' ) ); ?>
        <span id="wsscd-duration-text" class="wsscd-duration-bar__text"><?php esc_html_e( '7 days', 'smart-cycle-discounts' ); ?></span>
        <span class="wsscd-duration-bar__separator">•</span>
        <span id="wsscd-duration-hint" class="wsscd-duration-bar__hint"><?php esc_html_e( 'Perfect for weekly promotions', 'smart-cycle-discounts' ); ?></span>
    </div>

    <!-- Subtle Timezone -->
    <div class="wsscd-timezone-subtle">
        <?php WSSCD_Icon_Helper::render( 'admin-site-alt3', array( 'size' => 14 ) ); ?>
        <span><?php echo esc_html( $timezone ); ?></span>
    </div>
    <?php
    $wsscd_schedule_content = ob_get_clean();

    wsscd_wizard_card( array(
        'title'      => __( 'Campaign Period', 'smart-cycle-discounts' ),
        'subtitle'   => __( 'Set when your discount campaign runs', 'smart-cycle-discounts' ),
        'icon'       => 'calendar-alt',
        'content'    => $wsscd_schedule_content,
        'help_topic' => 'card-schedule-config',
    ) );
    ?>

    <!-- Recurring Schedule Card - Redesigned -->
    <?php
    // Recurring is now FREE for all users - no premium check needed
    ob_start();
    ?>

    <div id="wsscd-recurring-container" class="wsscd-recurring-redesign">

        <!-- Schedule Type Selection: One-time vs Recurring -->
        <div class="wsscd-schedule-type-selection">
            <div class="wsscd-schedule-type-cards">
                <label class="wsscd-schedule-type-card <?php echo ! $enable_recurring ? 'selected' : ''; ?>">
                    <input type="radio"
                           name="schedule_type"
                           value="one-time"
                           <?php checked( ! $enable_recurring ); ?>>
                    <div class="wsscd-schedule-type-card__icon">
                        <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 24 ) ); ?>
                    </div>
                    <div class="wsscd-schedule-type-card__content">
                        <h5><?php esc_html_e( 'One-time', 'smart-cycle-discounts' ); ?></h5>
                        <p><?php esc_html_e( 'Runs once on the scheduled dates', 'smart-cycle-discounts' ); ?></p>
                    </div>
                </label>

                <label class="wsscd-schedule-type-card <?php echo $enable_recurring ? 'selected' : ''; ?>">
                    <input type="radio"
                           name="schedule_type"
                           value="recurring"
                           <?php checked( $enable_recurring ); ?>>
                    <div class="wsscd-schedule-type-card__icon">
                        <?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 24 ) ); ?>
                    </div>
                    <div class="wsscd-schedule-type-card__content">
                        <h5><?php esc_html_e( 'Recurring', 'smart-cycle-discounts' ); ?></h5>
                        <p><?php esc_html_e( 'Repeats on a regular schedule', 'smart-cycle-discounts' ); ?></p>
                    </div>
                </label>
            </div>
            <!-- Hidden field for backend compatibility -->
            <input type="hidden"
                   id="enable_recurring"
                   name="enable_recurring"
                   value="<?php echo esc_attr( $enable_recurring ? '1' : '0' ); ?>">
        </div>

        <!-- Recurring Configuration (shown when recurring is selected) -->
        <div id="wsscd-recurring-options"
             class="wsscd-recurring-config"
             style="display: <?php echo esc_attr( $enable_recurring ? 'block' : 'none' ); ?>;"
             aria-hidden="<?php echo esc_attr( $enable_recurring ? 'false' : 'true' ); ?>">

            <!-- Frequency Row: Repeat every [X] [pattern] -->
            <div class="wsscd-recurring-frequency">
                <span class="wsscd-recurring-frequency__label">
                    <?php esc_html_e( 'Repeat every', 'smart-cycle-discounts' ); ?>
                </span>
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
                       class="wsscd-recurring-frequency__input"
                       aria-label="<?php esc_attr_e( 'Interval number', 'smart-cycle-discounts' ); ?>">
                <select id="recurrence_pattern"
                        name="recurrence_pattern"
                        class="wsscd-recurring-frequency__select"
                        aria-label="<?php esc_attr_e( 'Recurrence pattern', 'smart-cycle-discounts' ); ?>">
                    <option value="daily" <?php selected( $recurrence_pattern, 'daily' ); ?>>
                        <?php esc_html_e( 'day(s)', 'smart-cycle-discounts' ); ?>
                    </option>
                    <option value="weekly" <?php selected( $recurrence_pattern, 'weekly' ); ?>>
                        <?php esc_html_e( 'week(s)', 'smart-cycle-discounts' ); ?>
                    </option>
                    <option value="monthly" <?php selected( $recurrence_pattern, 'monthly' ); ?>>
                        <?php esc_html_e( 'month(s)', 'smart-cycle-discounts' ); ?>
                    </option>
                </select>
            </div>

            <!-- Days Selector (for weekly pattern) -->
            <div id="wsscd-weekly-options"
                 class="wsscd-recurring-days"
                 style="display: <?php echo esc_attr( 'weekly' === $recurrence_pattern ? 'block' : 'none' ); ?>;">
                <span class="wsscd-recurring-days__label">
                    <?php esc_html_e( 'On', 'smart-cycle-discounts' ); ?>
                </span>
                <div class="wsscd-recurring-days__selector" role="group" aria-label="<?php esc_attr_e( 'Select days of the week', 'smart-cycle-discounts' ); ?>">
                    <?php
                    $days = array(
                        'mon' => __( 'M', 'smart-cycle-discounts' ),
                        'tue' => __( 'T', 'smart-cycle-discounts' ),
                        'wed' => __( 'W', 'smart-cycle-discounts' ),
                        'thu' => __( 'T', 'smart-cycle-discounts' ),
                        'fri' => __( 'F', 'smart-cycle-discounts' ),
                        'sat' => __( 'S', 'smart-cycle-discounts' ),
                        'sun' => __( 'S', 'smart-cycle-discounts' ),
                    );
                    $days_full = array(
                        'mon' => __( 'Monday', 'smart-cycle-discounts' ),
                        'tue' => __( 'Tuesday', 'smart-cycle-discounts' ),
                        'wed' => __( 'Wednesday', 'smart-cycle-discounts' ),
                        'thu' => __( 'Thursday', 'smart-cycle-discounts' ),
                        'fri' => __( 'Friday', 'smart-cycle-discounts' ),
                        'sat' => __( 'Saturday', 'smart-cycle-discounts' ),
                        'sun' => __( 'Sunday', 'smart-cycle-discounts' ),
                    );
                    $selected_days = (array) $recurrence_days;
                    foreach ( $days as $value => $label ) :
                        ?>
                        <label class="wsscd-day-chip <?php echo in_array( $value, $selected_days, true ) ? 'selected' : ''; ?>"
                               title="<?php echo esc_attr( $days_full[ $value ] ); ?>">
                            <input type="checkbox"
                                   name="recurrence_days[]"
                                   value="<?php echo esc_attr( $value ); ?>"
                                   <?php checked( in_array( $value, $selected_days, true ) ); ?>
                                   aria-label="<?php echo esc_attr( $days_full[ $value ] ); ?>">
                            <span><?php echo esc_html( $label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Stop Repeating Dropdown -->
            <div class="wsscd-recurring-until">
                <span class="wsscd-recurring-until__label">
                    <?php esc_html_e( 'Until', 'smart-cycle-discounts' ); ?>
                </span>
                <div class="wsscd-recurring-until__controls">
                    <select id="recurrence_end_type_select"
                            class="wsscd-recurring-until__select"
                            aria-label="<?php esc_attr_e( 'Stop repeating condition', 'smart-cycle-discounts' ); ?>">
                        <option value="never" <?php selected( $recurrence_end_type, 'never' ); ?>>
                            <?php esc_html_e( 'Forever', 'smart-cycle-discounts' ); ?>
                        </option>
                        <option value="after" <?php selected( $recurrence_end_type, 'after' ); ?>>
                            <?php esc_html_e( 'After...', 'smart-cycle-discounts' ); ?>
                        </option>
                        <option value="on" <?php selected( $recurrence_end_type, 'on' ); ?>>
                            <?php esc_html_e( 'On date...', 'smart-cycle-discounts' ); ?>
                        </option>
                    </select>
                    <!-- Hidden field for backend compatibility -->
                    <input type="hidden"
                           id="recurrence_end_type"
                           name="recurrence_end_type"
                           value="<?php echo esc_attr( $recurrence_end_type ); ?>">

                    <!-- After X occurrences (conditional) -->
                    <div class="wsscd-recurring-until__after" style="display: <?php echo 'after' === $recurrence_end_type ? 'flex' : 'none'; ?>;">
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
                               class="wsscd-recurring-until__count"
                               aria-label="<?php esc_attr_e( 'Number of occurrences', 'smart-cycle-discounts' ); ?>">
                        <span class="wsscd-recurring-until__suffix"><?php esc_html_e( 'occurrences', 'smart-cycle-discounts' ); ?></span>
                    </div>

                    <!-- On date (conditional) -->
                    <div class="wsscd-recurring-until__on" style="display: <?php echo 'on' === $recurrence_end_type ? 'flex' : 'none'; ?>;">
                        <div class="wsscd-date-picker-wrapper wsscd-date-picker-wrapper--compact">
                            <input type="text"
                                   id="recurrence_end_date"
                                   name="recurrence_end_date"
                                   class="wsscd-date-picker"
                                   placeholder="<?php esc_attr_e( 'Select date', 'smart-cycle-discounts' ); ?>"
                                   readonly
                                   value="<?php echo esc_attr( $recurrence_end_date ); ?>"
                                   aria-label="<?php esc_attr_e( 'Recurrence end date', 'smart-cycle-discounts' ); ?>">
                            <button type="button"
                                    class="wsscd-calendar-icon"
                                    data-target="recurrence_end_date"
                                    aria-label="<?php esc_attr_e( 'Choose end date', 'smart-cycle-discounts' ); ?>">
                                <?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 16 ) ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Summary -->
            <div class="wsscd-schedule-summary" role="region" aria-label="<?php esc_attr_e( 'Schedule summary', 'smart-cycle-discounts' ); ?>">
                <!-- Natural Language Summary -->
                <div class="wsscd-schedule-summary__text" id="wsscd-schedule-summary-text">
                    <span class="wsscd-schedule-summary__icon">
                        <?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 18 ) ); ?>
                    </span>
                    <span class="wsscd-schedule-summary__description">
                        <?php esc_html_e( 'Configure your recurring schedule above', 'smart-cycle-discounts' ); ?>
                    </span>
                </div>

                <!-- Visual Timeline -->
                <div class="wsscd-schedule-timeline" id="wsscd-schedule-timeline" aria-live="polite">
                    <div class="wsscd-schedule-timeline__track">
                        <div class="wsscd-schedule-timeline__placeholder">
                            <?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 16 ) ); ?>
                            <span><?php esc_html_e( 'Timeline preview will appear here', 'smart-cycle-discounts' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Options (Collapsible) -->
            <details class="wsscd-recurring-advanced" id="wsscd-recurrence-mode-options">
                <summary class="wsscd-recurring-advanced__toggle">
                    <?php WSSCD_Icon_Helper::render( 'admin-settings', array( 'size' => 14 ) ); ?>
                    <span><?php esc_html_e( 'Advanced: Recurrence Mode', 'smart-cycle-discounts' ); ?></span>
                    <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 12, 'class' => 'wsscd-recurring-advanced__arrow' ) ); ?>
                </summary>
                <div class="wsscd-recurring-advanced__content">
                    <div class="wsscd-recurrence-mode-compact">
                        <label class="wsscd-mode-option <?php echo 'continuous' === $recurrence_mode ? 'selected' : ''; ?>">
                            <input type="radio"
                                   name="recurrence_mode"
                                   value="continuous"
                                   <?php checked( $recurrence_mode, 'continuous' ); ?>>
                            <span class="wsscd-mode-option__radio"></span>
                            <span class="wsscd-mode-option__content">
                                <strong>
                                    <?php esc_html_e( 'Continuous', 'smart-cycle-discounts' ); ?>
                                    <span class="wsscd-badge wsscd-badge--small wsscd-badge--success"><?php esc_html_e( 'Recommended', 'smart-cycle-discounts' ); ?></span>
                                </strong>
                                <span class="wsscd-mode-option__desc"><?php esc_html_e( 'Same campaign toggles on/off. Best for daily specials, happy hours.', 'smart-cycle-discounts' ); ?></span>
                            </span>
                        </label>
                        <label class="wsscd-mode-option <?php echo 'instances' === $recurrence_mode ? 'selected' : ''; ?>">
                            <input type="radio"
                                   name="recurrence_mode"
                                   value="instances"
                                   <?php checked( $recurrence_mode, 'instances' ); ?>>
                            <span class="wsscd-mode-option__radio"></span>
                            <span class="wsscd-mode-option__content">
                                <strong><?php esc_html_e( 'Instances', 'smart-cycle-discounts' ); ?></strong>
                                <span class="wsscd-mode-option__desc"><?php esc_html_e( 'Creates separate copies for each occurrence with individual analytics.', 'smart-cycle-discounts' ); ?></span>
                            </span>
                        </label>
                    </div>

                    <!-- Instance Mode Warning -->
                    <div id="wsscd-recurring-warning"
                         class="wsscd-instance-mode-notice"
                         style="display: <?php echo 'instances' === $recurrence_mode ? 'flex' : 'none'; ?>;"
                         role="alert">
                        <?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 14 ) ); ?>
                        <span><?php esc_html_e( 'Instance mode requires an end date and works best for periods under 6 months.', 'smart-cycle-discounts' ); ?></span>
                    </div>
                </div>
            </details>

        </div><!-- #wsscd-recurring-options -->

    </div><!-- #wsscd-recurring-container -->
    <?php
    $recurring_content = ob_get_clean();

    wsscd_wizard_card( array(
        'title'      => __( 'Does It Repeat?', 'smart-cycle-discounts' ),
        'icon'       => 'update',
        'subtitle'   => __( 'Choose whether this campaign runs once or repeats on a schedule.', 'smart-cycle-discounts' ),
        'content'    => $recurring_content,
        'class'      => 'wsscd-card--recurring wsscd-card--recurring-redesign',
        'help_topic' => 'card-recurring-schedule',
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


