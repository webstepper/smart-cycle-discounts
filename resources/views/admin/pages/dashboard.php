<?php
/**
 * Analytics Dashboard View
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/views/analytics
 */

// Prevent direct access
defined('ABSPATH') || exit('Direct access denied.');

/**
 * Analytics Dashboard Template
 *
 * @var SCD_Chart_Renderer $chart_renderer Chart renderer instance
 * @var array $overview_metrics Overview metrics data
 * @var array $campaigns_data Campaigns performance data
 * @var string $current_period Current selected period
 */

// Default values if not provided
$chart_renderer = $chart_renderer ?? new SCD_Chart_Renderer(new SCD_Logger());
$overview_metrics = $overview_metrics ?? array();
$campaigns_data = $campaigns_data ?? array();
$current_period = $current_period ?? '7days';
?>

<div class="wrap scd-analytics-dashboard">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Analytics Dashboard', 'smart-cycle-discounts'); ?>
    </h1>
    
    <div class="scd-dashboard-header">
        <div class="scd-dashboard-controls">
            <div class="scd-date-range-selector">
                <label for="scd-date-range" class="screen-reader-text">
                    <?php esc_html_e('Select date range', 'smart-cycle-discounts'); ?>
                </label>
                <select id="scd-date-range" class="scd-date-range-select">
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
            
            <div class="scd-custom-date-range" style="display: none;">
                <input type="date" id="scd-start-date" class="scd-date-input" />
                <span class="scd-date-separator"><?php esc_html_e('to', 'smart-cycle-discounts'); ?></span>
                <input type="date" id="scd-end-date" class="scd-date-input" />
                <button type="button" id="scd-apply-date-range" class="button">
                    <?php esc_html_e('Apply', 'smart-cycle-discounts'); ?>
                </button>
            </div>
            
            <div class="scd-dashboard-actions">
                <button type="button" id="scd-refresh-data" class="button">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh', 'smart-cycle-discounts'); ?>
                </button>
                
                <div class="scd-export-dropdown">
                    <button type="button" id="scd-export-toggle" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export', 'smart-cycle-discounts'); ?>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="scd-export-menu" style="display: none;">
                        <a href="#" data-format="csv" class="scd-export-link">
                            <?php esc_html_e('Export as CSV', 'smart-cycle-discounts'); ?>
                        </a>
                        <a href="#" data-format="json" class="scd-export-link">
                            <?php esc_html_e('Export as JSON', 'smart-cycle-discounts'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="scd-dashboard-status">
            <span class="scd-last-updated">
                <?php esc_html_e('Last updated:', 'smart-cycle-discounts'); ?>
                <span id="scd-last-updated-time"><?php echo esc_html(current_time('M j, Y g:i A')); ?></span>
            </span>
            <span class="scd-auto-refresh">
                <label>
                    <input type="checkbox" id="scd-auto-refresh" />
                    <?php esc_html_e('Auto-refresh', 'smart-cycle-discounts'); ?>
                </label>
            </span>
        </div>
    </div>

    <!-- Overview Metrics Cards -->
    <div class="scd-metrics-grid">
        <div class="scd-metrics-row">
            <?php
            $default_metrics = array(
                array(
                    'title' => __('Total Revenue', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'dashicons-money-alt',
                    'format' => 'currency',
                    'description' => __('Revenue generated from discount campaigns', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Conversions', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'dashicons-cart',
                    'format' => 'number',
                    'description' => __('Number of successful purchases', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Avg Order Value', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'dashicons-chart-line',
                    'format' => 'currency',
                    'description' => __('Average value per order with discount applied', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Click-through Rate', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'dashicons-performance',
                    'format' => 'percentage',
                    'description' => __('Percentage of clicks that led to purchases', 'smart-cycle-discounts')
                ),
                array(
                    'title' => __('Active Campaigns', 'smart-cycle-discounts'),
                    'value' => 0,
                    'change' => 0,
                    'change_type' => 'neutral',
                    'icon' => 'dashicons-megaphone',
                    'format' => 'number',
                    'description' => __('Currently running discount campaigns', 'smart-cycle-discounts')
                )
            );

            // Use overview metrics if provided and not empty, otherwise use defaults
            $metrics = ! empty( $overview_metrics ) ? $overview_metrics : $default_metrics;

            foreach ($metrics as $metric):
                echo $chart_renderer->render_metrics_card($metric);
            endforeach;
            ?>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="scd-dashboard-content">
        <div class="scd-dashboard-row">
            <!-- Revenue Trend Chart -->
            <div class="scd-dashboard-col scd-dashboard-col--8">
                <div class="scd-card scd-card--chart">
                    <div class="scd-card__header">
                        <div class="scd-card__title-group">
                            <h3 class="scd-card__title">
                                <?php esc_html_e('Revenue Trend', 'smart-cycle-discounts'); ?>
                            </h3>
                            <p class="scd-card__subtitle">
                                <?php esc_html_e('Track your revenue performance over time', 'smart-cycle-discounts'); ?>
                            </p>
                        </div>
                        <div class="scd-card__actions">
                            <select id="scd-revenue-chart-type" class="scd-chart-type-selector">
                                <option value="line"><?php esc_html_e('Line Chart', 'smart-cycle-discounts'); ?></option>
                                <option value="bar"><?php esc_html_e('Bar Chart', 'smart-cycle-discounts'); ?></option>
                                <option value="area"><?php esc_html_e('Area Chart', 'smart-cycle-discounts'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="scd-card__content">
                        <?php
                        echo $chart_renderer->render_line_chart(
                            'scd-revenue-trend-chart', array(), // Data will be loaded via AJAX
                            array(
                                'height' => 350,
                                'title' => ''
                            )
                        );
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Performance Summary -->
            <div class="scd-dashboard-col scd-dashboard-col--4">
                <div class="scd-card">
                    <div class="scd-card__header">
                        <div class="scd-card__title-group">
                            <h3 class="scd-card__title">
                                <?php esc_html_e('Performance Summary', 'smart-cycle-discounts'); ?>
                            </h3>
                            <p class="scd-card__subtitle">
                                <?php esc_html_e('Key metrics showing overall campaign effectiveness', 'smart-cycle-discounts'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="scd-card__content">
                        <div id="scd-performance-summary">
                            <?php
                            $summary_data = array(
                                'title' => __('Performance Summary', 'smart-cycle-discounts'),
                                'period' => '7 days',
                                'metrics' => array(
                                    array(
                                        'label' => __('Avg. Order Value', 'smart-cycle-discounts'),
                                        'value' => 0,
                                        'format' => 'currency',
                                        'change' => 0,
                                        'change_type' => 'neutral'
                                    ),
                                    array(
                                        'label' => __('Conversion Rate', 'smart-cycle-discounts'),
                                        'value' => 0,
                                        'format' => 'percentage',
                                        'change' => 0,
                                        'change_type' => 'neutral'
                                    ),
                                    array(
                                        'label' => __('Total Discounts', 'smart-cycle-discounts'),
                                        'value' => 0,
                                        'format' => 'currency',
                                        'change' => 0,
                                        'change_type' => 'neutral'
                                    ),
                                    array(
                                        'label' => __('ROI', 'smart-cycle-discounts'),
                                        'value' => 0,
                                        'format' => 'percentage',
                                        'change' => 0,
                                        'change_type' => 'neutral'
                                    )
                                )
                            );
                            echo $chart_renderer->render_performance_summary($summary_data);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="scd-dashboard-row">
            <!-- Top Products by Discount Revenue -->
            <div class="scd-dashboard-col scd-dashboard-col--12">
                <div class="scd-card">
                    <div class="scd-card__header">
                        <div class="scd-card__title-group">
                            <h3 class="scd-card__title">
                                <?php esc_html_e('Top Products by Discount Revenue', 'smart-cycle-discounts'); ?>
                            </h3>
                            <p class="scd-card__subtitle">
                                <?php esc_html_e('Products generating the most revenue through discount campaigns', 'smart-cycle-discounts'); ?>
                            </p>
                        </div>
                        <div class="scd-card__actions">
                            <select id="scd-top-products-period" class="scd-metric-selector">
                                <option value="7days"><?php esc_html_e('Last 7 Days', 'smart-cycle-discounts'); ?></option>
                                <option value="30days" selected><?php esc_html_e('Last 30 Days', 'smart-cycle-discounts'); ?></option>
                                <option value="90days"><?php esc_html_e('Last 90 Days', 'smart-cycle-discounts'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="scd-card__content">
                        <div class="scd-table-container">
                            <table class="scd-top-products-table">
                                <thead>
                                    <tr>
                                        <th class="scd-table-col-product"><?php esc_html_e('Product', 'smart-cycle-discounts'); ?></th>
                                        <th class="scd-table-col-revenue"><?php esc_html_e('Revenue', 'smart-cycle-discounts'); ?></th>
                                        <th class="scd-table-col-orders"><?php esc_html_e('Orders', 'smart-cycle-discounts'); ?></th>
                                        <th class="scd-table-col-discount"><?php esc_html_e('Avg Discount', 'smart-cycle-discounts'); ?></th>
                                        <th class="scd-table-col-trend"><?php esc_html_e('Trend', 'smart-cycle-discounts'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="scd-top-products-tbody">
                                    <tr class="scd-loading-row">
                                        <td colspan="5" class="scd-loading-cell">
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
        <div class="scd-dashboard-row">
            <div class="scd-dashboard-col scd-dashboard-col--12">
                <div class="scd-card">
                    <div class="scd-card__header">
                        <div class="scd-card__title-group">
                            <h3 class="scd-card__title">
                                <?php esc_html_e('Top Performing Campaigns', 'smart-cycle-discounts'); ?>
                            </h3>
                            <p class="scd-card__subtitle">
                                <?php esc_html_e('Campaigns generating the highest returns and engagement', 'smart-cycle-discounts'); ?>
                            </p>
                        </div>
                        <div class="scd-card__actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=scd-campaigns')); ?>" class="button">
                                <?php esc_html_e('View All Campaigns', 'smart-cycle-discounts'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="scd-card__content">
                        <div class="scd-table-container">
                            <table class="wp-list-table widefat fixed striped" id="scd-top-campaigns-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('Campaign', 'smart-cycle-discounts'); ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('Status', 'smart-cycle-discounts'); ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('Revenue', 'smart-cycle-discounts'); ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('Conversions', 'smart-cycle-discounts'); ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('CTR', 'smart-cycle-discounts'); ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('ROI', 'smart-cycle-discounts'); ?>
                                        </th>
                                        <th scope="col" class="manage-column">
                                            <?php esc_html_e('Actions', 'smart-cycle-discounts'); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="scd-top-campaigns-tbody">
                                    <tr class="scd-loading-row">
                                        <td colspan="7" class="scd-loading-cell">
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
        <div class="scd-dashboard-row">
            <div class="scd-dashboard-col scd-dashboard-col--12">
                <div class="scd-card">
                    <div class="scd-card__header">
                        <div class="scd-card__title-group">
                            <h3 class="scd-card__title">
                                <?php esc_html_e('Real-time Activity', 'smart-cycle-discounts'); ?>
                            </h3>
                            <p class="scd-card__subtitle">
                                <?php esc_html_e('Live feed of discount applications and customer interactions', 'smart-cycle-discounts'); ?>
                            </p>
                        </div>
                        <div class="scd-card__actions">
                            <span class="scd-activity-status">
                                <span class="scd-activity-indicator"></span>
                                <?php esc_html_e('Live', 'smart-cycle-discounts'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="scd-card__content">
                        <div id="scd-activity-feed" class="scd-activity-feed">
                            <div class="scd-activity-item scd-activity-placeholder">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e('Loading recent activity...', 'smart-cycle-discounts'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="scd-dashboard-loading" class="scd-loading-overlay" style="display: none;">
    <div class="scd-loading-content">
        <span class="spinner is-active"></span>
        <span class="scd-loading-text"><?php esc_html_e('Updating dashboard...', 'smart-cycle-discounts'); ?></span>
    </div>
</div>

<!-- Export Modal -->
<div id="scd-export-modal" class="scd-modal" style="display: none;">
    <div class="scd-modal-content">
        <div class="scd-modal-header">
            <h3><?php esc_html_e('Export Analytics Data', 'smart-cycle-discounts'); ?></h3>
            <button type="button" class="scd-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="scd-modal-body">
            <form id="scd-export-form">
                <div class="scd-form-group">
                    <label for="scd-export-format">
                        <?php esc_html_e('Export Format', 'smart-cycle-discounts'); ?>
                    </label>
                    <select id="scd-export-format" name="format">
                        <option value="csv"><?php esc_html_e('CSV', 'smart-cycle-discounts'); ?></option>
                        <option value="json"><?php esc_html_e('JSON', 'smart-cycle-discounts'); ?></option>
                    </select>
                </div>
                
                <div class="scd-form-group">
                    <label for="scd-export-data-type">
                        <?php esc_html_e('Data Type', 'smart-cycle-discounts'); ?>
                    </label>
                    <select id="scd-export-data-type" name="data_type">
                        <option value="overview"><?php esc_html_e('Overview Metrics', 'smart-cycle-discounts'); ?></option>
                        <option value="campaigns"><?php esc_html_e('Campaign Data', 'smart-cycle-discounts'); ?></option>
                        <option value="events"><?php esc_html_e('Event Data', 'smart-cycle-discounts'); ?></option>
                    </select>
                </div>
                
                <div class="scd-form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Data', 'smart-cycle-discounts'); ?>
                    </button>
                    <button type="button" class="button scd-modal-close">
                        <?php esc_html_e('Cancel', 'smart-cycle-discounts'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
