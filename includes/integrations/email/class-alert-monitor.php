<?php
/**
 * Alert Monitor Class
 *
 * Monitors campaign performance and triggers email alerts.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Alert Monitor Class
 *
 * Monitors campaigns for performance issues, low stock, and milestones.
 * Triggers appropriate email alerts via WordPress hooks.
 *
 * @since 1.0.0
 */
class SCD_Alert_Monitor {

	/**
	 * Logger instance.
	 *
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * Campaign Manager instance.
	 *
	 * @var SCD_Campaign_Manager
	 */
	private $campaign_manager;

	/**
	 * Analytics Repository instance.
	 *
	 * @var SCD_Analytics_Repository
	 */
	private $analytics_repository;

	/**
	 * Action Scheduler Service instance.
	 *
	 * @var SCD_Action_Scheduler_Service
	 */
	private $action_scheduler;

	/**
	 * Feature Gate instance.
	 *
	 * @var SCD_Feature_Gate
	 */
	private $feature_gate;

	/**
	 * Alert thresholds and settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SCD_Logger                   $logger               Logger instance.
	 * @param SCD_Campaign_Manager         $campaign_manager     Campaign Manager instance.
	 * @param SCD_Analytics_Repository     $analytics_repository Analytics Repository instance.
	 * @param SCD_Action_Scheduler_Service $action_scheduler     Action Scheduler Service instance.
	 * @param SCD_Feature_Gate             $feature_gate         Feature Gate instance.
	 */
	public function __construct(
		SCD_Logger $logger,
		SCD_Campaign_Manager $campaign_manager,
		SCD_Analytics_Repository $analytics_repository,
		SCD_Action_Scheduler_Service $action_scheduler,
		SCD_Feature_Gate $feature_gate
	) {
		$this->logger               = $logger;
		$this->campaign_manager     = $campaign_manager;
		$this->analytics_repository = $analytics_repository;
		$this->action_scheduler     = $action_scheduler;
		$this->feature_gate         = $feature_gate;

		$this->load_settings();
		$this->init_hooks();
	}

	/**
	 * Load alert settings.
	 *
	 * @return void
	 */
	private function load_settings(): void {
		$defaults = array(
			'performance' => array(
				'enabled'                => true,
				'check_interval'         => 'hourly',
				'low_conversion_rate'    => 1.0,    // Below 1% conversion rate
				'low_ctr'                => 2.0,    // Below 2% click-through rate
				'high_discount_usage'    => 80.0,   // Above 80% usage limit
			),
			'stock'       => array(
				'enabled'        => true,
				'check_interval' => 'hourly',
				'low_stock_threshold' => 10,     // Below 10 items in stock
			),
			'milestones'  => array(
				'enabled'    => true,
				'thresholds' => array(
					'revenue'     => array( 1000, 5000, 10000, 50000, 100000 ),
					'conversions' => array( 10, 50, 100, 500, 1000 ),
				),
			),
		);

		$saved_settings = get_option( 'scd_alert_settings', array() );
		$this->settings = wp_parse_args( $saved_settings, $defaults );
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Schedule monitoring tasks
		add_action( 'init', array( $this, 'schedule_monitoring' ) );

		// Monitoring cron hooks
		add_action( 'scd_monitor_performance', array( $this, 'check_performance_alerts' ) );
		add_action( 'scd_monitor_stock', array( $this, 'check_stock_alerts' ) );
		add_action( 'scd_monitor_milestones', array( $this, 'check_milestone_alerts' ) );
	}

	/**
	 * Schedule monitoring tasks.
	 *
	 * @return void
	 */
	public function schedule_monitoring(): void {
		// Only schedule if PRO features are available
		if ( ! $this->feature_gate->is_premium() ) {
			return;
		}

		// Schedule performance monitoring
		if ( $this->settings['performance']['enabled'] ) {
			$interval = $this->settings['performance']['check_interval'];
			if ( ! wp_next_scheduled( 'scd_monitor_performance' ) ) {
				wp_schedule_event( time(), $interval, 'scd_monitor_performance' );
			}
		}

		// Schedule stock monitoring
		if ( $this->settings['stock']['enabled'] ) {
			$interval = $this->settings['stock']['check_interval'];
			if ( ! wp_next_scheduled( 'scd_monitor_stock' ) ) {
				wp_schedule_event( time(), $interval, 'scd_monitor_stock' );
			}
		}

		// Milestone monitoring runs with performance monitoring
	}

	/**
	 * Check for performance alerts.
	 *
	 * Monitors conversion rates, CTR, and discount usage.
	 *
	 * @return void
	 */
	public function check_performance_alerts(): void {
		if ( ! $this->feature_gate->is_premium() ) {
			return;
		}

		try {
			$campaigns = $this->campaign_manager->get_campaigns();

			foreach ( $campaigns as $campaign ) {
				// Only monitor active campaigns
				if ( 'active' !== $campaign->get_status() ) {
					continue;
				}

				$campaign_id = $campaign->get_id();

				// Get campaign start date for analytics range
				$starts_at  = $campaign->get_starts_at();
				$start_date = $starts_at ? $starts_at->format( 'Y-m-d' ) : '';
				$end_date   = date( 'Y-m-d' );

				// Get performance data
				$performance = $this->analytics_repository->get_campaign_performance(
					$campaign_id,
					$start_date,
					$end_date
				);

				$alerts = array();

				// Check conversion rate
				$conversion_rate = $performance['conversion_rate'] ?? 0;
				if ( $conversion_rate > 0 && $conversion_rate < $this->settings['performance']['low_conversion_rate'] ) {
					$alerts[] = array(
						'type'    => 'low_conversion',
						'message' => sprintf(
							/* translators: %s: conversion rate percentage */
							__( 'Conversion rate is only %s%%, which is below the %s%% threshold.', 'smart-cycle-discounts' ),
							number_format( $conversion_rate, 2 ),
							number_format( $this->settings['performance']['low_conversion_rate'], 2 )
						),
						'actions' => array(
							__( 'Review campaign targeting and product selection', 'smart-cycle-discounts' ),
							__( 'Consider increasing discount value', 'smart-cycle-discounts' ),
							__( 'Check campaign visibility and promotion', 'smart-cycle-discounts' ),
						),
					);
				}

				// Check click-through rate
				$ctr = $performance['ctr'] ?? 0;
				if ( $ctr > 0 && $ctr < $this->settings['performance']['low_ctr'] ) {
					$alerts[] = array(
						'type'    => 'low_ctr',
						'message' => sprintf(
							/* translators: %s: CTR percentage */
							__( 'Click-through rate is only %s%%, which is below the %s%% threshold.', 'smart-cycle-discounts' ),
							number_format( $ctr, 2 ),
							number_format( $this->settings['performance']['low_ctr'], 2 )
						),
						'actions' => array(
							__( 'Improve campaign badge design and messaging', 'smart-cycle-discounts' ),
							__( 'Verify badges are displaying correctly on product pages', 'smart-cycle-discounts' ),
							__( 'Consider adjusting campaign placement', 'smart-cycle-discounts' ),
						),
					);
				}

				// Check discount usage if max uses is set
				$discount    = $campaign->get_discount();
				$max_uses    = $discount->get_max_uses();
				$current_uses = $performance['conversions'] ?? 0;

				if ( $max_uses > 0 && $current_uses > 0 ) {
					$usage_percentage = ( $current_uses / $max_uses ) * 100;

					if ( $usage_percentage >= $this->settings['performance']['high_discount_usage'] ) {
						$alerts[] = array(
							'type'    => 'high_usage',
							'message' => sprintf(
								/* translators: 1: current uses, 2: max uses, 3: percentage */
								__( 'Discount has been used %1$d times out of %2$d maximum (%3$s%%).', 'smart-cycle-discounts' ),
								$current_uses,
								$max_uses,
								number_format( $usage_percentage, 1 )
							),
							'actions' => array(
								__( 'Consider increasing usage limit', 'smart-cycle-discounts' ),
								__( 'Plan replacement campaign', 'smart-cycle-discounts' ),
								__( 'Review campaign end date', 'smart-cycle-discounts' ),
							),
						);
					}
				}

				// Fire performance alert if any issues found
				if ( ! empty( $alerts ) ) {
					$this->fire_performance_alert( $campaign_id, $alerts, $performance );
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to check performance alerts',
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Check for low stock alerts.
	 *
	 * Monitors product stock levels for active campaigns.
	 *
	 * @return void
	 */
	public function check_stock_alerts(): void {
		if ( ! $this->feature_gate->is_premium() ) {
			return;
		}

		try {
			$campaigns = $this->campaign_manager->get_campaigns();

			foreach ( $campaigns as $campaign ) {
				// Only monitor active campaigns
				if ( 'active' !== $campaign->get_status() ) {
					continue;
				}

				$low_stock_products = $this->get_low_stock_products( $campaign );

				if ( ! empty( $low_stock_products ) ) {
					$this->fire_low_stock_alert( $campaign->get_id(), $low_stock_products );
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to check stock alerts',
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Check for milestone alerts.
	 *
	 * Monitors revenue and conversion milestones.
	 *
	 * @return void
	 */
	public function check_milestone_alerts(): void {
		if ( ! $this->feature_gate->is_premium() ) {
			return;
		}

		if ( ! $this->settings['milestones']['enabled'] ) {
			return;
		}

		try {
			$campaigns = $this->campaign_manager->get_campaigns();

			foreach ( $campaigns as $campaign ) {
				// Only monitor active campaigns
				if ( 'active' !== $campaign->get_status() ) {
					continue;
				}

				$campaign_id = $campaign->get_id();

				// Get campaign start date for analytics range
				$starts_at  = $campaign->get_starts_at();
				$start_date = $starts_at ? $starts_at->format( 'Y-m-d' ) : '';
				$end_date   = date( 'Y-m-d' );

				// Get performance data
				$performance = $this->analytics_repository->get_campaign_performance(
					$campaign_id,
					$start_date,
					$end_date
				);

				$revenue     = $performance['revenue'] ?? 0;
				$conversions = $performance['conversions'] ?? 0;

				// Check for milestone achievements
				$this->check_revenue_milestones( $campaign_id, $revenue, $performance );
				$this->check_conversion_milestones( $campaign_id, $conversions, $performance );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to check milestone alerts',
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Get low stock products for a campaign.
	 *
	 * @param SCD_Campaign $campaign Campaign instance.
	 * @return array Low stock products with details.
	 */
	private function get_low_stock_products( SCD_Campaign $campaign ): array {
		$low_stock_products = array();
		$threshold          = $this->settings['stock']['low_stock_threshold'];

		// Get campaign product IDs
		$product_ids = $campaign->get_product_ids();

		if ( empty( $product_ids ) ) {
			return $low_stock_products;
		}

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$stock_quantity = $product->get_stock_quantity();

			if ( null !== $stock_quantity && $stock_quantity <= $threshold ) {
				$low_stock_products[] = array(
					'product_id'   => $product_id,
					'product_name' => $product->get_name(),
					'sku'          => $product->get_sku(),
					'stock_qty'    => $stock_quantity,
					'status'       => $stock_quantity > 0 ? 'low' : 'out',
				);
			}
		}

		return $low_stock_products;
	}

	/**
	 * Check revenue milestones and fire alerts.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param float $revenue     Current revenue.
	 * @param array $performance Performance data.
	 * @return void
	 */
	private function check_revenue_milestones( int $campaign_id, float $revenue, array $performance ): void {
		$thresholds         = $this->settings['milestones']['thresholds']['revenue'];
		$reached_milestones = get_option( "scd_milestones_revenue_{$campaign_id}", array() );

		foreach ( $thresholds as $milestone ) {
			// Check if milestone reached and not already notified
			if ( $revenue >= $milestone && ! in_array( $milestone, $reached_milestones, true ) ) {
				$this->fire_milestone_alert(
					$campaign_id,
					'revenue',
					$milestone,
					sprintf(
						/* translators: %s: milestone amount */
						__( 'Congratulations! Your campaign has reached %s in revenue!', 'smart-cycle-discounts' ),
						wc_price( $milestone )
					),
					$performance
				);

				// Mark milestone as reached
				$reached_milestones[] = $milestone;
				update_option( "scd_milestones_revenue_{$campaign_id}", $reached_milestones, false );
			}
		}
	}

	/**
	 * Check conversion milestones and fire alerts.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param int   $conversions Current conversions.
	 * @param array $performance Performance data.
	 * @return void
	 */
	private function check_conversion_milestones( int $campaign_id, int $conversions, array $performance ): void {
		$thresholds         = $this->settings['milestones']['thresholds']['conversions'];
		$reached_milestones = get_option( "scd_milestones_conversions_{$campaign_id}", array() );

		foreach ( $thresholds as $milestone ) {
			// Check if milestone reached and not already notified
			if ( $conversions >= $milestone && ! in_array( $milestone, $reached_milestones, true ) ) {
				$this->fire_milestone_alert(
					$campaign_id,
					'conversions',
					$milestone,
					sprintf(
						/* translators: %s: milestone number */
						__( 'Congratulations! Your campaign has reached %s conversions!', 'smart-cycle-discounts' ),
						number_format( $milestone )
					),
					$performance
				);

				// Mark milestone as reached
				$reached_milestones[] = $milestone;
				update_option( "scd_milestones_conversions_{$campaign_id}", $reached_milestones, false );
			}
		}
	}

	/**
	 * Fire performance alert action.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $alerts      Alert details.
	 * @param array $performance Performance data.
	 * @return void
	 */
	private function fire_performance_alert( int $campaign_id, array $alerts, array $performance ): void {
		$alert_types    = wp_list_pluck( $alerts, 'type' );
		$alert_messages = wp_list_pluck( $alerts, 'message' );
		$all_actions    = array();

		foreach ( $alerts as $alert ) {
			if ( ! empty( $alert['actions'] ) ) {
				$all_actions = array_merge( $all_actions, $alert['actions'] );
			}
		}

		$alert_data = array(
			'alert_type'    => implode( ', ', $alert_types ),
			'alert_message' => implode( ' ', $alert_messages ),
			'performance'   => $this->format_performance_data( $performance ),
			'actions'       => array_unique( $all_actions ),
		);

		do_action( 'scd_performance_alert', $campaign_id, $alert_data );

		$this->logger->info(
			'Performance alert triggered',
			array(
				'campaign_id' => $campaign_id,
				'alert_types' => $alert_types,
			)
		);
	}

	/**
	 * Fire low stock alert action.
	 *
	 * @param int   $campaign_id        Campaign ID.
	 * @param array $low_stock_products Low stock products.
	 * @return void
	 */
	private function fire_low_stock_alert( int $campaign_id, array $low_stock_products ): void {
		$stock_data = array(
			'products' => $low_stock_products,
			'actions'  => array(
				__( 'Restock low inventory products', 'smart-cycle-discounts' ),
				__( 'Consider pausing campaign if products are out of stock', 'smart-cycle-discounts' ),
				__( 'Update campaign product selection', 'smart-cycle-discounts' ),
			),
		);

		do_action( 'scd_low_stock_alert', $campaign_id, $stock_data );

		$this->logger->info(
			'Low stock alert triggered',
			array(
				'campaign_id'   => $campaign_id,
				'product_count' => count( $low_stock_products ),
			)
		);
	}

	/**
	 * Fire milestone alert action.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $type        Milestone type (revenue/conversions).
	 * @param mixed  $value       Milestone value.
	 * @param string $message     Achievement message.
	 * @param array  $performance Performance data.
	 * @return void
	 */
	private function fire_milestone_alert( int $campaign_id, string $type, $value, string $message, array $performance ): void {
		$milestone_data = array(
			'type'        => ucfirst( $type ),
			'value'       => $value,
			'message'     => $message,
			'performance' => $this->format_performance_data( $performance ),
		);

		do_action( 'scd_milestone_alert', $campaign_id, $milestone_data );

		$this->logger->info(
			'Milestone alert triggered',
			array(
				'campaign_id'    => $campaign_id,
				'milestone_type' => $type,
				'milestone_value' => $value,
			)
		);
	}

	/**
	 * Format performance data for email display.
	 *
	 * @param array $performance Performance data from analytics.
	 * @return string Formatted HTML.
	 */
	private function format_performance_data( array $performance ): string {
		$html  = '<ul>';
		$html .= '<li>' . sprintf(
			/* translators: %s: revenue amount */
			__( 'Revenue: %s', 'smart-cycle-discounts' ),
			wc_price( $performance['revenue'] ?? 0 )
		) . '</li>';
		$html .= '<li>' . sprintf(
			/* translators: %s: conversion count */
			__( 'Conversions: %s', 'smart-cycle-discounts' ),
			number_format( $performance['conversions'] ?? 0 )
		) . '</li>';
		$html .= '<li>' . sprintf(
			/* translators: %s: conversion rate percentage */
			__( 'Conversion Rate: %s%%', 'smart-cycle-discounts' ),
			number_format( $performance['conversion_rate'] ?? 0, 2 )
		) . '</li>';
		$html .= '<li>' . sprintf(
			/* translators: %s: CTR percentage */
			__( 'Click-Through Rate: %s%%', 'smart-cycle-discounts' ),
			number_format( $performance['ctr'] ?? 0, 2 )
		) . '</li>';
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Unschedule all monitoring tasks.
	 *
	 * Called on plugin deactivation.
	 *
	 * @return void
	 */
	public function unschedule_monitoring(): void {
		wp_clear_scheduled_hook( 'scd_monitor_performance' );
		wp_clear_scheduled_hook( 'scd_monitor_stock' );
	}
}
