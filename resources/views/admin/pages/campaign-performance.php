<?php
/**
 * Campaign Performance Report Template
 *
 * This is a STANDALONE HTML document for export/print functionality.
 * It is NOT an admin page loaded within the WordPress dashboard.
 * When exported or printed, this document is viewed outside WordPress
 * and has no access to wp_enqueue_style() stylesheets.
 * Inline CSS is REQUIRED for the report to display correctly.
 *
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/templates/reports
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

// Prevent direct access.
defined( 'ABSPATH' ) || exit( 'Direct access denied.' );

/**
 * Campaign Performance Report Template
 *
 * @var array $report_data Report data
 * @var array $options Report options
 */

$metadata        = $report_data['metadata'] ?? array();
$campaigns       = $report_data['data'] ?? array();
$summary         = $metadata['summary'] ?? array();
$date_range      = $metadata['date_range'] ?? '30days';
$total_campaigns = $metadata['total_campaigns'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Performance Report</title>
    <?php
    /**
     * INLINE STYLES JUSTIFICATION:
     *
     * This file generates a STANDALONE HTML report for:
     * 1. PDF export - Users download/print reports for offline use
     * 2. Email attachments - Reports sent via email to stakeholders
     * 3. Browser print - Direct browser printing without WordPress context
     *
     * When opened outside WordPress (PDF viewer, email client, print dialog):
     * - wp_enqueue_style() stylesheets are NOT available
     * - External CSS files cannot be linked (security/CORS restrictions)
     * - Inline CSS is the ONLY way to style the document
     *
     * This follows the same pattern as WooCommerce invoice templates,
     * WordPress email templates, and other export functionality.
     *
     * @see https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code
     * Note: Inline styles for export templates are explicitly allowed.
     */
    // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone HTML export template for PDF/print/email. Viewed outside WordPress; wp_enqueue_style() not available.
    ?>
    <style>
        :root {
            /* Import theme colors if available */
            --wsscd-color-primary: #2271b1;
            --wsscd-color-success: #00a32a;
            --wsscd-color-warning: #dba617;
            --wsscd-color-danger: #d63638;
            --wsscd-color-accent: #3858e9;
            --wsscd-color-text: #1d2327;
            --wsscd-color-text-muted: #646970;
            --wsscd-color-background: #ffffff;
            --wsscd-color-surface: #f6f7f7;
            --wsscd-color-surface-light: #f0f0f1;
            --wsscd-color-border: #c3c4c7;
            --wsscd-color-border-light: #dcdcde;
            --wsscd-spacing-xs: 4px;
            --wsscd-spacing-sm: 8px;
            --wsscd-spacing-md: 16px;
            --wsscd-spacing-lg: 24px;
            --wsscd-spacing-xl: 32px;
            --wsscd-font-size-sm: 12px;
            --wsscd-font-size-base: 14px;
            --wsscd-font-size-lg: 16px;
            --wsscd-font-size-xl: 20px;
            --wsscd-font-size-xxl: 28px;
            --wsscd-radius-sm: 4px;
            --wsscd-radius-md: 6px;
            --wsscd-radius-lg: 8px;
            --wsscd-shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--wsscd-color-text);
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--wsscd-spacing-lg);
            background-color: var(--wsscd-color-surface-light);
        }
        
        .report-header {
            background: var(--wsscd-color-background);
            padding: var(--wsscd-spacing-xl);
            border-radius: var(--wsscd-radius-lg);
            box-shadow: var(--wsscd-shadow-sm);
            margin-bottom: var(--wsscd-spacing-xl);
            text-align: center;
        }
        
        .report-title {
            font-size: var(--wsscd-font-size-xxl);
            font-weight: 600;
            color: var(--wsscd-color-text);
            margin: 0 0 var(--wsscd-spacing-xs) 0;
        }
        
        .report-subtitle {
            font-size: var(--wsscd-font-size-lg);
            color: var(--wsscd-color-text-muted);
            margin: 0;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--wsscd-spacing-lg);
            padding-top: var(--wsscd-spacing-lg);
            border-top: 1px solid var(--wsscd-color-border-light);
        }
        
        .summary-section {
            background: var(--wsscd-color-background);
            padding: var(--wsscd-spacing-xl);
            border-radius: var(--wsscd-radius-lg);
            box-shadow: var(--wsscd-shadow-sm);
            margin-bottom: var(--wsscd-spacing-xl);
        }
        
        .summary-title {
            font-size: var(--wsscd-font-size-xl);
            font-weight: 600;
            color: var(--wsscd-color-text);
            margin: 0 0 var(--wsscd-spacing-lg) 0;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--wsscd-spacing-lg);
        }
        
        .summary-card {
            background: var(--wsscd-color-surface);
            padding: var(--wsscd-spacing-lg);
            border-radius: var(--wsscd-radius-md);
            text-align: center;
            border-left: 4px solid var(--wsscd-color-primary);
        }
        
        .summary-card--revenue {
            border-left-color: var(--wsscd-color-success);
        }
        
        .summary-card--conversions {
            border-left-color: var(--wsscd-color-danger);
        }
        
        .summary-card--ctr {
            border-left-color: var(--wsscd-color-warning);
        }
        
        .summary-card--aov {
            border-left-color: var(--wsscd-color-accent);
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--wsscd-color-text);
            margin: 0 0 var(--wsscd-spacing-xs) 0;
        }
        
        .summary-label {
            font-size: var(--wsscd-font-size-base);
            color: var(--wsscd-color-text-muted);
            margin: 0;
        }
        
        .campaigns-section {
            background: var(--wsscd-color-background);
            padding: var(--wsscd-spacing-xl);
            border-radius: var(--wsscd-radius-lg);
            box-shadow: var(--wsscd-shadow-sm);
        }
        
        .campaigns-title {
            font-size: var(--wsscd-font-size-xl);
            font-weight: 600;
            color: var(--wsscd-color-text);
            margin: 0 0 var(--wsscd-spacing-lg) 0;
        }
        
        .campaigns-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--wsscd-spacing-lg);
        }
        
        .campaigns-table th,
        .campaigns-table td {
            padding: var(--wsscd-spacing-sm);
            text-align: left;
            border-bottom: 1px solid var(--wsscd-color-border-light);
        }
        
        .campaigns-table th {
            background-color: var(--wsscd-color-surface);
            font-weight: 600;
            color: var(--wsscd-color-text);
            font-size: var(--wsscd-font-size-base);
        }
        
        .campaigns-table td {
            font-size: var(--wsscd-font-size-base);
            color: var(--wsscd-color-text);
        }
        
        .campaigns-table tr:hover {
            background-color: var(--wsscd-color-surface);
        }
        
        .status-badge {
            display: inline-block;
            padding: var(--wsscd-spacing-xs) var(--wsscd-spacing-sm);
            border-radius: var(--wsscd-radius-sm);
            font-size: var(--wsscd-font-size-sm);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge--active {
            background-color: var(--wsscd-color-success-bg, #d4edda);
            color: var(--wsscd-color-success-dark, #155724);
        }
        
        .status-badge--paused {
            background-color: var(--wsscd-color-warning-bg, #fff3cd);
            color: var(--wsscd-color-warning-dark, #856404);
        }
        
        .status-badge--expired {
            background-color: var(--wsscd-color-error-bg, #f8d7da);
            color: var(--wsscd-color-error-dark, #721c24);
        }
        
        .currency {
            font-weight: 600;
            color: var(--wsscd-color-success);
        }
        
        .percentage {
            font-weight: 600;
        }
        
        .percentage--positive {
            color: var(--wsscd-color-success);
        }
        
        .percentage--negative {
            color: var(--wsscd-color-danger);
        }
        
        .no-campaigns {
            text-align: center;
            padding: 40px;
            color: var(--wsscd-color-text-muted);
            font-style: italic;
        }
        
        .report-footer {
            background: var(--wsscd-color-background);
            padding: var(--wsscd-spacing-lg);
            border-radius: var(--wsscd-radius-lg);
            box-shadow: var(--wsscd-shadow-sm);
            margin-top: var(--wsscd-spacing-xl);
            text-align: center;
            font-size: var(--wsscd-font-size-sm);
            color: var(--wsscd-color-text-muted);
        }
        
        @media print {
            body {
                background-color: var(--wsscd-color-background);
                padding: 0;
            }
            
            .report-header,
            .summary-section,
            .campaigns-section,
            .report-footer {
                box-shadow: none;
                border: 1px solid var(--wsscd-color-border);
            }
        }
        
        @media (max-width: 768px) {
            .report-meta {
                flex-direction: column;
                gap: var(--wsscd-spacing-xs);
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .campaigns-table {
                font-size: var(--wsscd-font-size-sm);
            }
            
            .campaigns-table th,
            .campaigns-table td {
                padding: var(--wsscd-spacing-sm) var(--wsscd-spacing-xs);
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
                        <?php
                                                echo wp_kses_post( WSSCD_Badge_Helper::status_badge( $campaign['status'], ucfirst( $campaign['status'] ) ) );
                        ?>
                    </td>
                    <td>
                        <?php
                        echo esc_html($campaign['start_date'] ?
                            wp_date('M j, Y', strtotime($campaign['start_date'])) :
                            'Not set'
                        );
                        ?>
                    </td>
                    <td>
                        <?php
                        echo esc_html($campaign['end_date'] ?
                            wp_date('M j, Y', strtotime($campaign['end_date'])) :
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
                    <td class="percentage <?php echo esc_attr( $campaign['roi'] >= 0 ? 'percentage--positive' : 'percentage--negative' ); ?>">
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
