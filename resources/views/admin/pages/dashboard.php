<?php
/**
 * Analytics Dashboard View
 *
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/views/analytics
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

// Prevent direct access
defined('ABSPATH') || exit('Direct access denied.');

/**
 * Analytics Dashboard Template
 *
 * @var WSSCD_Chart_Renderer $chart_renderer Chart renderer instance
 * @var array $overview_metrics Overview metrics data
 * @var array $campaigns_data Campaigns performance data
 * @var string $current_period Current selected period
 */

// Default values if not provided
$chart_renderer = $chart_renderer ?? new WSSCD_Chart_Renderer(new WSSCD_Logger());
$overview_metrics = $overview_metrics ?? array();
$campaigns_data = $campaigns_data ?? array();
$current_period = $current_period ?? '30days';
?>

<div class="wrap wsscd-analytics-dashboard">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Analytics Dashboard', 'smart-cycle-discounts'); ?>
    </h1>
    <p class="wsscd-currency-note">
        <?php
        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        $currency_code   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        printf(
            /* translators: %1$s: currency symbol, %2$s: currency code */
            esc_html__( 'All monetary values shown in %1$s (%2$s)', 'smart-cycle-discounts' ),
            esc_html( $currency_symbol ),
            esc_html( $currency_code )
        );
        ?>
    </p>

    <div class="wsscd-dashboard-header">
        <div class="wsscd-dashboard-controls">
            <!-- Campaign Filter -->
            <div class="wsscd-campaign-filter">
                <label for="wsscd-campaign-filter" class="wsscd-filter-label">
                    <?php WSSCD_Icon_Helper::render( 'megaphone', array( 'size' => 16 ) ); ?>
                    <?php esc_html_e( 'Campaign:', 'smart-cycle-discounts' ); ?>
                </label>
                <select id="wsscd-campaign-filter" class="wsscd-campaign-select">
                    <option value="all"><?php esc_html_e( 'All Campaigns', 'smart-cycle-discounts' ); ?></option>
                    <?php
                    // Get campaigns list
                    global $wpdb;
                    $campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dashboard filter query; table name is constructed with $wpdb->prefix.
                    $campaigns = $wpdb->get_results(
                        "SELECT id, name, status FROM {$campaigns_table}
                        WHERE deleted_at IS NULL
                        ORDER BY
                            CASE
                                WHEN status = 'active' THEN 1
                                WHEN status = 'scheduled' THEN 2
                                ELSE 3
                            END,
                            name ASC
                        LIMIT 100"
                    );
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

                    if ( $campaigns ) {
                        $current_status = '';
                        foreach ( $campaigns as $campaign ) {
                            // Group by status
                            if ( $current_status !== $campaign->status ) {
                                if ( $current_status !== '' ) {
                                    echo '</optgroup>';
                                }
                                $status_label = ucfirst( $campaign->status );
                                echo '<optgroup label="' . esc_attr( $status_label ) . '">';
                                $current_status = $campaign->status;
                            }

                            $status_indicator = '';
                            if ( 'active' === $campaign->status ) {
                                $status_indicator = '● ';
                            } elseif ( 'scheduled' === $campaign->status ) {
                                $status_indicator = '○ ';
                            }

                            echo '<option value="' . esc_attr( $campaign->id ) . '">' .
                                 esc_html( $status_indicator . $campaign->name ) .
                                 '</option>';
                        }
                        if ( $current_status !== '' ) {
                            echo '</optgroup>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="wsscd-date-range-selector">
                <!-- Quick Date Filter Buttons -->
                <div class="wsscd-quick-date-filters">
                    <button type="button" class="wsscd-quick-date-btn" data-range="24hours" aria-pressed="<?php echo esc_attr( '24hours' === $current_period ? 'true' : 'false' ); ?>">
                        <?php esc_html_e( 'Today', 'smart-cycle-discounts' ); ?>
                    </button>
                    <button type="button" class="wsscd-quick-date-btn" data-range="7days" aria-pressed="<?php echo esc_attr( '7days' === $current_period ? 'true' : 'false' ); ?>">
                        <?php esc_html_e( 'Last 7 Days', 'smart-cycle-discounts' ); ?>
                    </button>
                    <button type="button" class="wsscd-quick-date-btn" data-range="30days" aria-pressed="<?php echo esc_attr( '30days' === $current_period ? 'true' : 'false' ); ?>">
                        <?php esc_html_e( 'Last 30 Days', 'smart-cycle-discounts' ); ?>
                    </button>
                    <button type="button" class="wsscd-quick-date-btn" data-range="90days" aria-pressed="<?php echo esc_attr( '90days' === $current_period ? 'true' : 'false' ); ?>">
                        <?php esc_html_e( 'Last 90 Days', 'smart-cycle-discounts' ); ?>
                    </button>
                    <button type="button" class="wsscd-quick-date-btn" data-range="custom" aria-pressed="<?php echo esc_attr( 'custom' === $current_period ? 'true' : 'false' ); ?>">
                        <?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e( 'Custom', 'smart-cycle-discounts' ); ?>
                    </button>
                </div>

                <!-- Hidden select for backward compatibility -->
                <label for="wsscd-date-range" class="screen-reader-text">
                    <?php esc_html_e('Select date range', 'smart-cycle-discounts'); ?>
                </label>
                <select id="wsscd-date-range" class="wsscd-date-range-select" style="display: none;">
                    <option value="24hours" <?php selected($current_period, '24hours'); ?>>
                        <?php esc_html_e('Last 24 Hours', 'smart-cycle-discounts'); ?>
                    </option>
                    <option value="7days" <?php selected($current_period, '7days'); ?>>
                        <?php esc_html_e('Last 7 Days', 'smart-cycle-discounts'); ?>
                    </option>
                    <option value="30days" <?php selected($current_period, '30days'); ?>>
                        <?php esc_html_e('Last 30 Days', 'smart-cycle-discounts'); ?>
                    </option>
                    <option value="90days" <?php selected($current_period, '90days'); ?>>
                        <?php esc_html_e('Last 90 Days', 'smart-cycle-discounts'); ?>
                    </option>
                    <option value="custom" <?php selected($current_period, 'custom'); ?>>
                        <?php esc_html_e('Custom Range', 'smart-cycle-discounts'); ?>
                    </option>
                </select>
            </div>
            
            <div class="wsscd-custom-date-range" style="display: none;">
                <input
                    type="date"
                    id="wsscd-start-date"
                    class="wsscd-date-input"
                    max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
                    aria-label="<?php esc_attr_e( 'Start date', 'smart-cycle-discounts' ); ?>"
                />
                <span class="wsscd-date-separator"><?php esc_html_e('to', 'smart-cycle-discounts'); ?></span>
                <input
                    type="date"
                    id="wsscd-end-date"
                    class="wsscd-date-input"
                    max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
                    aria-label="<?php esc_attr_e( 'End date', 'smart-cycle-discounts' ); ?>"
                />
                <button type="button" id="wsscd-apply-date-range" class="button">
                    <?php esc_html_e('Apply', 'smart-cycle-discounts'); ?>
                </button>
            </div>
            
            <div class="wsscd-dashboard-actions">
                <button type="button" id="wsscd-refresh-data" class="button">
                    <?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 16 ) ); ?>
                    <?php esc_html_e('Refresh', 'smart-cycle-discounts'); ?>
                </button>
                
                <div class="wsscd-export-dropdown">
                    <button type="button" id="wsscd-export-toggle" class="button">
                        <?php WSSCD_Icon_Helper::render( 'download', array( 'size' => 16 ) ); ?>
                        <?php esc_html_e('Export', 'smart-cycle-discounts'); ?>
                        <?php WSSCD_Icon_Helper::render( 'arrow-down', array( 'size' => 16 ) ); ?>
                    </button>
                    <div class="wsscd-export-menu" style="display: none;">
                        <a href="#" data-format="csv" class="wsscd-export-link">
                            <?php esc_html_e('Export as CSV', 'smart-cycle-discounts'); ?>
                        </a>
                        <a href="#" data-format="json" class="wsscd-export-link">
                            <?php esc_html_e('Export as JSON', 'smart-cycle-discounts'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="wsscd-dashboard-status">
            <span class="wsscd-last-updated">
                <?php esc_html_e('Last updated:', 'smart-cycle-discounts'); ?>
                <span id="wsscd-last-updated-time"><?php echo esc_html(current_time('M j, Y g:i A')); ?></span>
            </span>
            <span class="wsscd-auto-refresh">
                <label>
                    <input type="checkbox" id="wsscd-auto-refresh" />
                    <?php esc_html_e('Auto-refresh', 'smart-cycle-discounts'); ?>
                </label>
            </span>
        </div>
    </div>

    <!-- Overview Metrics Cards -->
    <div class="wsscd-metrics-grid">
        <div class="wsscd-metrics-row">
            <?php
            $default_metrics = array(
                array(
                    'title' => __('Total Revenue', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'receipt',
                    'format' => 'currency',
                    'description' => __('Revenue generated from discount campaigns', 'smart-cycle-discounts'),
                    'help_text' => __('Total revenue from orders where discounts were applied. Calculated as sum of order totals after discounts.', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Conversions', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'cart',
                    'format' => 'number',
                    'description' => __('Number of successful purchases', 'smart-cycle-discounts'),
                    'help_text' => __('Total number of completed orders that used discount campaigns. Includes all order statuses except failed, cancelled, and pending.', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Avg Order Value', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'chart-line',
                    'format' => 'currency',
                    'description' => __('Average value per order with discount applied', 'smart-cycle-discounts'),
                    'help_text' => __('Average revenue per order calculated as Total Revenue divided by Conversions. Higher values indicate customers are purchasing more per transaction.', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Click-through Rate', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'performance',
                    'format' => 'percentage',
                    'description' => __('Percentage of clicks that led to purchases', 'smart-cycle-discounts'),
                    'help_text' => __('Percentage of discount impressions that resulted in completed orders. Calculated as (Conversions / Impressions) × 100. Higher rates indicate more effective campaigns.', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Active Campaigns', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'megaphone',
                    'format' => 'number',
                    'description' => __('Currently running discount campaigns', 'smart-cycle-discounts'),
                    'help_text' => __('Number of campaigns currently active and available to customers. This count includes campaigns that have started and not yet ended.', 'smart-cycle-discounts')
                )
            );

            // Use overview metrics if provided and not empty, otherwise use defaults
            $metrics = ! empty( $overview_metrics ) ? $overview_metrics : $default_metrics;

            foreach ($metrics as $metric):
                // Chart renderer output includes SVG icons from Icon_Helper.
                WSSCD_HTML_Helper::output( $chart_renderer->render_metrics_card($metric) );
            endforeach;
            ?>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="wsscd-dashboard-content">
        <div class="wsscd-dashboard-row">
            <!-- Revenue Trend Chart -->
            <div class="wsscd-dashboard-col wsscd-dashboard-col--8">
                <div class="wsscd-card wsscd-card--chart">
                    <div class="wsscd-card__header">
                        <div class="wsscd-card__header-content">
                            <?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 20 ) ); ?>
                            <div class="wsscd-card__header-text">
                                <h3 class="wsscd-card__title">
                                    <?php esc_html_e( 'Revenue Trend', 'smart-cycle-discounts' ); ?>
                                </h3>
                                <p class="wsscd-card__subtitle">
                                    <?php esc_html_e('Track your revenue performance over time', 'smart-cycle-discounts'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="wsscd-card__actions">
                            <select id="wsscd-revenue-chart-type" class="wsscd-chart-type-selector">
                                <option value="line"><?php esc_html_e('Line Chart', 'smart-cycle-discounts'); ?></option>
                                <option value="bar"><?php esc_html_e('Bar Chart', 'smart-cycle-discounts'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="wsscd-card__content">
                        <?php
                        // Chart renderer includes SVG icons.
                        WSSCD_HTML_Helper::output( $chart_renderer->render_line_chart(
                            'wsscd-revenue-trend-chart', array(), // Data will be loaded via AJAX
                            array(
                                'height' => 350,
                                'title' => ''
                            )
                        ) );
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Performance Summary -->
            <div class="wsscd-dashboard-col wsscd-dashboard-col--4">
                <div class="wsscd-card">
                    <div class="wsscd-card__header">
                        <div class="wsscd-card__header-content">
                            <?php WSSCD_Icon_Helper::render( 'dashboard', array( 'size' => 20 ) ); ?>
                            <div class="wsscd-card__header-text">
                                <h3 class="wsscd-card__title">
                                    <?php esc_html_e( 'Performance Summary', 'smart-cycle-discounts' ); ?>
                                </h3>
                                <p class="wsscd-card__subtitle">
                                    <?php esc_html_e('Key metrics showing overall campaign effectiveness', 'smart-cycle-discounts'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="wsscd-card__content">
                        <div id="wsscd-performance-summary">
                            <?php
                            $summary_data = array(
                                'title' => __('Performance Summary', 'smart-cycle-discounts'),
                                'period' => '7 days',
                                'metrics' => array(
                                    array(
                                        'label' => __('Avg. Order Value', 'smart-cycle-discounts'),
                                        'value' => $full_metrics['avg_order_value'] ?? 0,
                                        'format' => 'currency',
                                        'change' => 0,
                                        'change_type' => 'neutral',
                                        'help_text' => __('Average revenue per order across all campaigns. Calculated as Total Revenue divided by Total Orders.', 'smart-cycle-discounts')
                                    ),
                                    array(
                                        'label' => __('Conversion Rate', 'smart-cycle-discounts'),
                                        'value' => $full_metrics['avg_conversion_rate'] ?? 0,
                                        'format' => 'percentage',
                                        'change' => 0,
                                        'change_type' => 'neutral',
                                        'help_text' => __('Percentage of discount views that resulted in completed purchases. Calculated as (Total Conversions / Total Impressions) × 100.', 'smart-cycle-discounts')
                                    ),
                                    array(
                                        'label' => __('Total Discounts', 'smart-cycle-discounts'),
                                        'value' => $full_metrics['total_discount'] ?? 0,
                                        'format' => 'currency',
                                        'change' => 0,
                                        'change_type' => 'neutral',
                                        'help_text' => __('Total amount of discounts given across all campaigns. This represents the total savings provided to customers.', 'smart-cycle-discounts')
                                    ),
                                    array(
                                        'label' => __('ROI', 'smart-cycle-discounts'),
                                        'value' => $full_metrics['avg_roi'] ?? 0,
                                        'format' => 'percentage',
                                        'change' => 0,
                                        'change_type' => 'neutral',
                                        'help_text' => __('Return on Investment showing the profitability of discount campaigns. Calculated as ((Revenue - Discount Cost) / Discount Cost) × 100. Negative values indicate campaigns cost more than they generated.', 'smart-cycle-discounts')
                                    )
                                )
                            );
                            // Chart renderer includes SVG icons.
                            WSSCD_HTML_Helper::output( $chart_renderer->render_performance_summary($summary_data) );
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wsscd-dashboard-row">
            <!-- Top Products by Discount Revenue -->
            <div class="wsscd-dashboard-col wsscd-dashboard-col--12">
                <div class="wsscd-card">
                    <div class="wsscd-card__header">
                        <div class="wsscd-card__header-content">
                            <?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 20 ) ); ?>
                            <div class="wsscd-card__header-text">
                                <h3 class="wsscd-card__title">
                                    <?php esc_html_e( 'Top Products by Discount Revenue', 'smart-cycle-discounts' ); ?>
                                </h3>
                                <p class="wsscd-card__subtitle">
                                    <?php esc_html_e('Products generating the most revenue through discount campaigns', 'smart-cycle-discounts'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="wsscd-card__actions">
                            <select id="wsscd-top-products-period" class="wsscd-metric-selector">
                                <option value="7days"><?php esc_html_e('Last 7 Days', 'smart-cycle-discounts'); ?></option>
                                <option value="30days" selected><?php esc_html_e('Last 30 Days', 'smart-cycle-discounts'); ?></option>
                                <option value="90days"><?php esc_html_e('Last 90 Days', 'smart-cycle-discounts'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="wsscd-card__content">
                        <div class="wsscd-table-container">
                            <table class="wsscd-top-products-table">
                                <thead>
                                    <tr>
                                        <th class="wsscd-table-col-product">
                                            <?php
                                            esc_html_e('Product', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Product name with thumbnail image', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th class="wsscd-table-col-revenue">
                                            <?php
                                            esc_html_e('Revenue', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Total revenue generated from this product through discount campaigns', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th class="wsscd-table-col-orders">
                                            <?php
                                            esc_html_e('Orders', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Number of completed orders containing this product with discount applied', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th class="wsscd-table-col-discount">
                                            <?php
                                            esc_html_e('Avg Discount', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Average discount amount given per order for this product', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th class="wsscd-table-col-trend">
                                            <?php
                                            esc_html_e('Trend', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Performance trend compared to previous period - shows if sales are increasing or decreasing', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="wsscd-top-products-tbody">
                                    <tr class="wsscd-loading-row">
                                        <td colspan="5" class="wsscd-loading-cell">
                                            <span class="spinner is-active"></span>
                                            <?php esc_html_e('Loading product data...', 'smart-cycle-discounts'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Campaigns Table -->
        <div class="wsscd-dashboard-row">
            <div class="wsscd-dashboard-col wsscd-dashboard-col--12">
                <div class="wsscd-card">
                    <div class="wsscd-card__header">
                        <div class="wsscd-card__header-content">
                            <?php WSSCD_Icon_Helper::render( 'megaphone', array( 'size' => 20 ) ); ?>
                            <div class="wsscd-card__header-text">
                                <h3 class="wsscd-card__title">
                                    <?php esc_html_e( 'Top Performing Campaigns', 'smart-cycle-discounts' ); ?>
                                </h3>
                                <p class="wsscd-card__subtitle">
                                    <?php esc_html_e('Campaigns generating the highest returns and engagement', 'smart-cycle-discounts'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="wsscd-card__actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wsscd-campaigns')); ?>" class="button">
                                <?php esc_html_e('View All Campaigns', 'smart-cycle-discounts'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="wsscd-card__content">
                        <div class="wsscd-table-container">
                            <table class="wp-list-table widefat fixed striped" id="wsscd-top-campaigns-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('Campaign', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Campaign name and discount type', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('Status', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Current campaign status: Active, Scheduled, Paused, or Expired', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('Revenue', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Total revenue generated by this campaign after discounts were applied', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('Conversions', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Number of completed orders that used this campaign\'s discount', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('CTR', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Click-through Rate: percentage of discount impressions that resulted in conversions', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('ROI', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Return on Investment: profitability of this campaign calculated as ((Revenue - Discount Cost) / Discount Cost) × 100', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php
                                            esc_html_e('Actions', 'smart-cycle-discounts');
                                            if ( class_exists( 'WSSCD_Tooltip_Helper' ) ) {
                                                WSSCD_Tooltip_Helper::render( __( 'Quick actions: View details, Edit campaign, or Pause/Resume', 'smart-cycle-discounts' ) );
                                            }
                                            ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="wsscd-top-campaigns-tbody">
                                    <tr class="wsscd-loading-row">
                                        <td colspan="7" class="wsscd-loading-cell">
                                            <span class="spinner is-active"></span>
                                            <?php esc_html_e('Loading campaign data...', 'smart-cycle-discounts'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Real-time Activity Feed -->
        <div class="wsscd-dashboard-row">
            <div class="wsscd-dashboard-col wsscd-dashboard-col--12">
                <div class="wsscd-card">
                    <div class="wsscd-card__header">
                        <div class="wsscd-card__header-content">
                            <?php WSSCD_Icon_Helper::render( 'list-view', array( 'size' => 20 ) ); ?>
                            <div class="wsscd-card__header-text">
                                <h3 class="wsscd-card__title">
                                    <?php esc_html_e( 'Real-time Activity', 'smart-cycle-discounts' ); ?>
                                </h3>
                                <p class="wsscd-card__subtitle">
                                    <?php esc_html_e('Live feed of discount applications and customer interactions', 'smart-cycle-discounts'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="wsscd-card__actions">
                            <span class="wsscd-activity-status">
                                <span class="wsscd-activity-indicator"></span>
                                <?php esc_html_e('Live', 'smart-cycle-discounts'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="wsscd-card__content">
                        <div id="wsscd-activity-feed" class="wsscd-activity-feed">
                            <div class="wsscd-activity-item wsscd-activity-placeholder">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e('Loading recent activity...', 'smart-cycle-discounts'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <?php
    if ( class_exists( 'WSSCD_Loader_Helper' ) ) {
        WSSCD_Loader_Helper::render_fullscreen( 'wsscd-dashboard-loading', __( 'Loading analytics...', 'smart-cycle-discounts' ), true );
    }
    ?>
</div>

<!-- Export Modal - Centralized Component -->
<?php
// Build export form content
ob_start();
?>
<form id="wsscd-export-form">
	<div class="wsscd-form-group">
		<label for="wsscd-export-format">
			<?php esc_html_e( 'Export Format', 'smart-cycle-discounts' ); ?>
		</label>
		<select id="wsscd-export-format" name="format">
			<option value="csv"><?php esc_html_e( 'CSV', 'smart-cycle-discounts' ); ?></option>
			<option value="json"><?php esc_html_e( 'JSON', 'smart-cycle-discounts' ); ?></option>
		</select>
	</div>

	<div class="wsscd-form-group">
		<label for="wsscd-export-data-type">
			<?php esc_html_e( 'Data Type', 'smart-cycle-discounts' ); ?>
		</label>
		<select id="wsscd-export-data-type" name="data_type">
			<option value="overview"><?php esc_html_e( 'Overview Metrics', 'smart-cycle-discounts' ); ?></option>
			<option value="campaigns"><?php esc_html_e( 'Campaign Data', 'smart-cycle-discounts' ); ?></option>
			<option value="events"><?php esc_html_e( 'Event Data', 'smart-cycle-discounts' ); ?></option>
		</select>
	</div>

	<div class="wsscd-form-actions">
		<?php
		WSSCD_Button_Helper::primary(
			__( 'Export Data', 'smart-cycle-discounts' ),
			array(
				'type'       => 'submit',
				'icon'       => 'download',
				'attributes' => array( 'form' => 'wsscd-export-form' ),
			)
		);

		WSSCD_Button_Helper::secondary(
			__( 'Cancel', 'smart-cycle-discounts' ),
			array(
				'classes' => array( 'wsscd-modal-close' ),
			)
		);
		?>
	</div>
</form>
<?php
$export_form_content = ob_get_clean();

// Render modal using centralized component
if ( class_exists( 'WSSCD_Modal_Component' ) ) {
	$export_modal = new WSSCD_Modal_Component(
		array(
			'id'          => 'wsscd-export-modal',
			'title'       => __( 'Export Analytics Data', 'smart-cycle-discounts' ),
			'content'     => $export_form_content,
			'icon'        => 'download',
			'dismissible' => true,
			'classes'     => array( 'wsscd-export-modal' ),
		)
	);
	$export_modal->render();
}
?>

<?php
// Render Campaign Overview Panel (must be outside .wrap for proper fixed positioning)
if ( isset( $overview_panel ) && $overview_panel instanceof WSSCD_Campaign_Overview_Panel ) {
    $overview_panel->render();
}
?>
