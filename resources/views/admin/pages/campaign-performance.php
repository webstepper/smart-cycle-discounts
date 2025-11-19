<?php
/**
 * Campaign Performance Report Template
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/templates/reports
 */

// Prevent direct access
defined('ABSPATH') || exit('Direct access denied.');

/**
 * Campaign Performance Report Template
 *
 * @var array $report_data Report data
 * @var array $options Report options
 */

$metadata = $report_data['metadata'] ?? array();
$campaigns = $report_data['data'] ?? array();
$summary = $metadata['summary'] ?? array();
$date_range = $metadata['date_range'] ?? '30days';
$total_campaigns = $metadata['total_campaigns'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Performance Report</title>
    <style>
        :root {
            /* Import theme colors if available */
            --scd-color-primary: #2271b1;
            --scd-color-success: #00a32a;
            --scd-color-warning: #dba617;
            --scd-color-danger: #d63638;
            --scd-color-accent: #3858e9;
            --scd-color-text: #1d2327;
            --scd-color-text-muted: #646970;
            --scd-color-background: #ffffff;
            --scd-color-surface: #f6f7f7;
            --scd-color-surface-light: #f0f0f1;
            --scd-color-border: #c3c4c7;
            --scd-color-border-light: #dcdcde;
            --scd-spacing-xs: 4px;
            --scd-spacing-sm: 8px;
            --scd-spacing-md: 16px;
            --scd-spacing-lg: 24px;
            --scd-spacing-xl: 32px;
            --scd-font-size-sm: 12px;
            --scd-font-size-base: 14px;
            --scd-font-size-lg: 16px;
            --scd-font-size-xl: 20px;
            --scd-font-size-xxl: 28px;
            --scd-radius-sm: 4px;
            --scd-radius-md: 6px;
            --scd-radius-lg: 8px;
            --scd-shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--scd-color-text);
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--scd-spacing-lg);
            background-color: var(--scd-color-surface-light);
        }
        
        .report-header {
            background: var(--scd-color-background);
            padding: var(--scd-spacing-xl);
            border-radius: var(--scd-radius-lg);
            box-shadow: var(--scd-shadow-sm);
            margin-bottom: var(--scd-spacing-xl);
            text-align: center;
        }
        
        .report-title {
            font-size: var(--scd-font-size-xxl);
            font-weight: 600;
            color: var(--scd-color-text);
            margin: 0 0 var(--scd-spacing-xs) 0;
        }
        
        .report-subtitle {
            font-size: var(--scd-font-size-lg);
            color: var(--scd-color-text-muted);
            margin: 0;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--scd-spacing-lg);
            padding-top: var(--scd-spacing-lg);
            border-top: 1px solid var(--scd-color-border-light);
        }
        
        .summary-section {
            background: var(--scd-color-background);
            padding: var(--scd-spacing-xl);
            border-radius: var(--scd-radius-lg);
            box-shadow: var(--scd-shadow-sm);
            margin-bottom: var(--scd-spacing-xl);
        }
        
        .summary-title {
            font-size: var(--scd-font-size-xl);
            font-weight: 600;
            color: var(--scd-color-text);
            margin: 0 0 var(--scd-spacing-lg) 0;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--scd-spacing-lg);
        }
        
        .summary-card {
            background: var(--scd-color-surface);
            padding: var(--scd-spacing-lg);
            border-radius: var(--scd-radius-md);
            text-align: center;
            border-left: 4px solid var(--scd-color-primary);
        }
        
        .summary-card--revenue {
            border-left-color: var(--scd-color-success);
        }
        
        .summary-card--conversions {
            border-left-color: var(--scd-color-danger);
        }
        
        .summary-card--ctr {
            border-left-color: var(--scd-color-warning);
        }
        
        .summary-card--aov {
            border-left-color: var(--scd-color-accent);
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--scd-color-text);
            margin: 0 0 var(--scd-spacing-xs) 0;
        }
        
        .summary-label {
            font-size: var(--scd-font-size-base);
            color: var(--scd-color-text-muted);
            margin: 0;
        }
        
        .campaigns-section {
            background: var(--scd-color-background);
            padding: var(--scd-spacing-xl);
            border-radius: var(--scd-radius-lg);
            box-shadow: var(--scd-shadow-sm);
        }
        
        .campaigns-title {
            font-size: var(--scd-font-size-xl);
            font-weight: 600;
            color: var(--scd-color-text);
            margin: 0 0 var(--scd-spacing-lg) 0;
        }
        
        .campaigns-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--scd-spacing-lg);
        }
        
        .campaigns-table th,
        .campaigns-table td {
            padding: var(--scd-spacing-sm);
            text-align: left;
            border-bottom: 1px solid var(--scd-color-border-light);
        }
        
        .campaigns-table th {
            background-color: var(--scd-color-surface);
            font-weight: 600;
            color: var(--scd-color-text);
            font-size: var(--scd-font-size-base);
        }
        
        .campaigns-table td {
            font-size: var(--scd-font-size-base);
            color: var(--scd-color-text);
        }
        
        .campaigns-table tr:hover {
            background-color: var(--scd-color-surface);
        }
        
        .status-badge {
            display: inline-block;
            padding: var(--scd-spacing-xs) var(--scd-spacing-sm);
            border-radius: var(--scd-radius-sm);
            font-size: var(--scd-font-size-sm);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge--active {
            background-color: var(--scd-color-success-bg, #d4edda);
            color: var(--scd-color-success-dark, #155724);
        }
        
        .status-badge--paused {
            background-color: var(--scd-color-warning-bg, #fff3cd);
            color: var(--scd-color-warning-dark, #856404);
        }
        
        .status-badge--expired {
            background-color: var(--scd-color-error-bg, #f8d7da);
            color: var(--scd-color-error-dark, #721c24);
        }
        
        .currency {
            font-weight: 600;
            color: var(--scd-color-success);
        }
        
        .percentage {
            font-weight: 600;
        }
        
        .percentage--positive {
            color: var(--scd-color-success);
        }
        
        .percentage--negative {
            color: var(--scd-color-danger);
        }
        
        .no-campaigns {
            text-align: center;
            padding: 40px;
            color: var(--scd-color-text-muted);
            font-style: italic;
        }
        
        .report-footer {
            background: var(--scd-color-background);
            padding: var(--scd-spacing-lg);
            border-radius: var(--scd-radius-lg);
            box-shadow: var(--scd-shadow-sm);
            margin-top: var(--scd-spacing-xl);
            text-align: center;
            font-size: var(--scd-font-size-sm);
            color: var(--scd-color-text-muted);
        }
        
        @media print {
            body {
                background-color: var(--scd-color-background);
                padding: 0;
            }
            
            .report-header,
            .summary-section,
            .campaigns-section,
            .report-footer {
                box-shadow: none;
                border: 1px solid var(--scd-color-border);
            }
        }
        
        @media (max-width: 768px) {
            .report-meta {
                flex-direction: column;
                gap: var(--scd-spacing-xs);
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .campaigns-table {
                font-size: var(--scd-font-size-sm);
            }
            
            .campaigns-table th,
            .campaigns-table td {
                padding: var(--scd-spacing-sm) var(--scd-spacing-xs);
            }
        }
    </style>
</head>
<body>
    <!-- Report Header -->
    <div class="report-header">
        <h1 class="report-title">Campaign Performance Report</h1>
        <p class="report-subtitle">
            Comprehensive analysis of discount campaign performance
        </p>
        
        <div class="report-meta">
            <div>
                <strong>Date Range:</strong> <?php echo esc_html(ucfirst(str_replace('days', ' Days', $date_range))); ?>
            </div>
            <div>
                <strong>Total Campaigns:</strong> <?php echo esc_html($total_campaigns); ?>
            </div>
            <div>
                <strong>Generated:</strong> <?php echo esc_html(current_time('F j, Y g:i A')); ?>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <?php if (!empty($summary)): ?>
    <div class="summary-section">
        <h2 class="summary-title">Performance Summary</h2>
        
        <div class="summary-grid">
            <div class="summary-card summary-card--revenue">
                <div class="summary-value">
                    <?php echo wp_kses_post( wc_price( $summary['total_revenue'] ?? 0 ) ); ?>
                </div>
                <div class="summary-label">Total Revenue</div>
            </div>
            
            <div class="summary-card summary-card--conversions">
                <div class="summary-value">
                    <?php echo esc_html(number_format($summary['total_conversions'] ?? 0)); ?>
                </div>
                <div class="summary-label">Total Conversions</div>
            </div>
            
            <div class="summary-card summary-card--ctr">
                <div class="summary-value">
                    <?php echo esc_html(number_format($summary['overall_ctr'] ?? 0, 2)); ?>%
                </div>
                <div class="summary-label">Overall CTR</div>
            </div>
            
            <div class="summary-card summary-card--aov">
                <div class="summary-value">
                    <?php echo wp_kses_post( wc_price( $summary['average_aov'] ?? 0 ) ); ?>
                </div>
                <div class="summary-label">Average Order Value</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Campaigns Section -->
    <div class="campaigns-section">
        <h2 class="campaigns-title">Campaign Details</h2>
        
        <?php if (!empty($campaigns)): ?>
        <table class="campaigns-table">
            <thead>
                <tr>
                    <th>Campaign Name</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Revenue</th>
                    <th>Conversions</th>
                    <th>CTR</th>
                    <th>Conv. Rate</th>
                    <th>AOV</th>
                    <th>ROI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($campaign['campaign_name']); ?></strong>
                    </td>
                    <td>
                        <?php echo SCD_Badge_Helper::status_badge( $campaign['status'], ucfirst( $campaign['status'] ) ); ?>
                    </td>
                    <td>
                        <?php 
                        echo esc_html($campaign['start_date'] ? 
                            date('M j, Y', strtotime($campaign['start_date'])) : 
                            'Not set'
                        ); 
                        ?>
                    </td>
                    <td>
                        <?php 
                        echo esc_html($campaign['end_date'] ? 
                            date('M j, Y', strtotime($campaign['end_date'])) : 
                            'No end date'
                        ); 
                        ?>
                    </td>
                    <td class="currency">
                        <?php echo wp_kses_post( wc_price( $campaign['revenue'] ) ); ?>
                    </td>
                    <td>
                        <?php echo esc_html(number_format($campaign['conversions'])); ?>
                    </td>
                    <td class="percentage">
                        <?php echo esc_html(number_format($campaign['click_through_rate'], 2)); ?>%
                    </td>
                    <td class="percentage">
                        <?php echo esc_html(number_format($campaign['conversion_rate'], 2)); ?>%
                    </td>
                    <td class="currency">
                        <?php echo wp_kses_post( wc_price( $campaign['average_order_value'] ) ); ?>
                    </td>
                    <td class="percentage <?php echo $campaign['roi'] >= 0 ? 'percentage--positive' : 'percentage--negative'; ?>">
                        <?php echo esc_html(number_format($campaign['roi'], 2)); ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-campaigns">
            No campaigns found for the selected date range.
        </div>
        <?php endif; ?>
    </div>

    <!-- Report Footer -->
    <div class="report-footer">
        <p>
            This report was generated by Smart Cycle Discounts plugin for WordPress.<br>
            Report generated on <?php echo esc_html(current_time('F j, Y \a\t g:i A T')); ?>
        </p>
    </div>
</body>
</html>
