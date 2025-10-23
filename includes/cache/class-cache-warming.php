<?php
/**
 * Cache Warming Strategy
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache/strategies
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Cache Warming Strategy Class
 *
 * Implements intelligent cache warming strategies for optimal performance.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache/strategies
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Cache_Warming {

    /**
     * Cache manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Cache_Manager    $cache_manager    Cache manager.
     */
    private SCD_Cache_Manager $cache_manager;

    /**
     * Campaign manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
     */
    private SCD_Campaign_Manager $campaign_manager;

    /**
     * Analytics collector instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Analytics_Collector    $analytics_collector    Analytics collector.
     */
    private SCD_Analytics_Collector $analytics_collector;

    /**
     * Logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Logger    $logger    Logger instance.
     */
    private SCD_Logger $logger;

    /**
     * Warming strategies.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $strategies    Warming strategies.
     */
    private array $strategies = array();

    /**
     * Initialize the cache warming system.
     *
     * @since    1.0.0
     * @param    SCD_Cache_Manager         $cache_manager        Cache manager.
     * @param    SCD_Campaign_Manager      $campaign_manager     Campaign manager.
     * @param    SCD_Analytics_Collector   $analytics_collector  Analytics collector.
     * @param    SCD_Logger                $logger               Logger instance.
     */
    public function __construct(
        SCD_Cache_Manager $cache_manager,
        SCD_Campaign_Manager $campaign_manager,
        SCD_Analytics_Collector $analytics_collector,
        SCD_Logger $logger
    ) {
        $this->cache_manager = $cache_manager;
        $this->campaign_manager = $campaign_manager;
        $this->analytics_collector = $analytics_collector;
        $this->logger = $logger;

        $this->register_strategies();
    }

    /**
     * Initialize cache warming system.
     *
     * @since    1.0.0
     * @return   void
     */
    public function init(): void {
        // Schedule cache warming
        add_action('scd_cache_warm_up', array($this, 'execute_warming'));
        
        // Schedule daily cache warming
        if (!wp_next_scheduled('scd_cache_warm_up')) {
            wp_schedule_event(time(), 'daily', 'scd_cache_warm_up');
        }

        // Warm cache on campaign activation
        add_action('scd_campaign_activated', array($this, 'warm_campaign_cache'), 10, 1);
        
        // Warm cache on plugin activation
        add_action('scd_plugin_activated', array($this, 'warm_essential_cache'));

        $this->logger->debug('Cache warming system initialized');
    }

    /**
     * Execute cache warming.
     *
     * @since    1.0.0
     * @param    string    $strategy    Warming strategy to execute.
     * @return   array                  Warming results.
     */
    public function execute_warming(string $strategy = 'all'): array {
        $start_time = microtime(true);
        $results = array();

        try {
            if ($strategy === 'all') {
                foreach ($this->strategies as $strategy_name => $strategy_config) {
                    $results[$strategy_name] = $this->execute_strategy($strategy_name, $strategy_config);
                }
            } elseif (isset($this->strategies[$strategy])) {
                $results[$strategy] = $this->execute_strategy($strategy, $this->strategies[$strategy]);
            } else {
                throw new InvalidArgumentException("Unknown warming strategy: {$strategy}");
            }

            $execution_time = microtime(true) - $start_time;
            $total_warmed = array_sum(array_column($results, 'warmed'));

            $this->logger->info('Cache warming completed', array(
                'strategy' => $strategy,
                'total_warmed' => $total_warmed,
                'execution_time' => round($execution_time, 3),
                'results' => $results
            ));

            return array(
                'success' => true,
                'strategy' => $strategy,
                'total_warmed' => $total_warmed,
                'execution_time' => $execution_time,
                'results' => $results
            );

        } catch (Exception $e) {
            $this->logger->error('Cache warming failed', array(
                'strategy' => $strategy,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'strategy' => $strategy
            );
        }
    }

    /**
     * Warm essential cache data.
     *
     * @since    1.0.0
     * @return   array    Warming results.
     */
    public function warm_essential_cache(): array {
        return $this->execute_warming('essential');
    }

    /**
     * Warm campaign-specific cache.
     *
     * @since    1.0.0
     * @param    SCD_Campaign    $campaign    Campaign to warm cache for.
     * @return   array                        Warming results.
     */
    public function warm_campaign_cache(SCD_Campaign $campaign): array {
        $campaign_id = $campaign->get_id();
        
        $cache_keys = array(
            "campaign_{$campaign_id}" => function() use ($campaign_id) {
                return $this->campaign_manager->find($campaign_id);
            },
            "campaign_{$campaign_id}_products" => function() use ($campaign_id) {
                return $this->get_campaign_products($campaign_id);
            },
            "campaign_{$campaign_id}_analytics" => function() use ($campaign_id) {
                return $this->analytics_collector->get_campaign_metrics($campaign_id);
            }
        );

        $warmed = $this->cache_manager->warm_up($cache_keys, 'campaigns');

        $this->logger->info('Campaign cache warmed', array(
            'campaign_id' => $campaign_id, 'keys_warmed' => $warmed
        ));

        return array(
            'campaign_id' => $campaign_id,
            'warmed' => $warmed,
            'total_keys' => count($cache_keys)
        );
    }

    /**
     * Warm analytics cache.
     *
     * @since    1.0.0
     * @return   array    Warming results.
     */
    public function warm_analytics_cache(): array {
        return $this->execute_warming('analytics');
    }

    /**
     * Warm product cache.
     *
     * @since    1.0.0
     * @return   array    Warming results.
     */
    public function warm_product_cache(): array {
        return $this->execute_warming('products');
    }

    /**
     * Get warming statistics.
     *
     * @since    1.0.0
     * @return   array    Warming statistics.
     */
    public function get_warming_stats(): array {
        $stats = get_option('scd_cache_warming_stats', array(
            'last_run' => null,
            'total_runs' => 0,
            'total_warmed' => 0,
            'average_time' => 0,
            'strategies' => array()
        ));

        return $stats;
    }

    /**
     * Reset warming statistics.
     *
     * @since    1.0.0
     * @return   bool    Success status.
     */
    public function reset_warming_stats(): bool {
        return delete_option('scd_cache_warming_stats');
    }

    /**
     * Register warming strategies.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function register_strategies(): void {
        $this->strategies = array(
            'essential' => array(
                'description' => 'Essential cache data for core functionality',
                'priority' => 1,
                'keys' => array(
                    'active_campaigns' => array($this, 'get_active_campaigns'),
                    'plugin_settings' => array($this, 'get_plugin_settings'),
                    'discount_strategies' => array($this, 'get_discount_strategies')
                )
            ),
            'campaigns' => array(
                'description' => 'Campaign-related cache data',
                'priority' => 2,
                'keys' => array(
                    'all_campaigns' => array($this, 'get_all_campaigns'),
                    'campaign_stats' => array($this, 'get_campaign_stats'),
                    'scheduled_campaigns' => array($this, 'get_scheduled_campaigns')
                )
            ),
            'analytics' => array(
                'description' => 'Analytics and reporting cache data',
                'priority' => 3,
                'keys' => array(
                    'dashboard_metrics' => array($this, 'get_dashboard_metrics'),
                    'top_campaigns' => array($this, 'get_top_campaigns'),
                    'revenue_stats' => array($this, 'get_revenue_stats')
                )
            ),
            'products' => array(
                'description' => 'Product-related cache data',
                'priority' => 4,
                'keys' => array(
                    'discounted_products' => array($this, 'get_discounted_products'),
                    'product_categories' => array($this, 'get_product_categories'),
                    'popular_products' => array($this, 'get_popular_products')
                )
            )
        );

        // Allow filtering of strategies
        $this->strategies = apply_filters('scd_cache_warming_strategies', $this->strategies);
    }

    /**
     * Execute a specific warming strategy.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $strategy_name    Strategy name.
     * @param    array     $strategy_config  Strategy configuration.
     * @return   array                       Execution results.
     */
    private function execute_strategy(string $strategy_name, array $strategy_config): array {
        $start_time = microtime(true);
        
        try {
            $cache_group = $strategy_name;
            $warmed = $this->cache_manager->warm_up($strategy_config['keys'], $cache_group);
            
            $execution_time = microtime(true) - $start_time;
            
            // Update statistics
            $this->update_warming_stats($strategy_name, $warmed, $execution_time);
            
            return array(
                'strategy' => $strategy_name,
                'warmed' => $warmed,
                'total_keys' => count($strategy_config['keys']),
                'execution_time' => $execution_time,
                'success' => true
            );
            
        } catch (Exception $e) {
            $this->logger->error('Strategy execution failed', array(
                'strategy' => $strategy_name,
                'error' => $e->getMessage()
            ));
            
            return array(
                'strategy' => $strategy_name,
                'warmed' => 0,
                'total_keys' => count($strategy_config['keys']),
                'execution_time' => microtime(true) - $start_time,
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Update warming statistics.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $strategy    Strategy name.
     * @param    int       $warmed      Number of keys warmed.
     * @param    float     $time        Execution time.
     * @return   void
     */
    private function update_warming_stats(string $strategy, int $warmed, float $time): void {
        $stats = $this->get_warming_stats();
        
        $stats['last_run'] = current_time('mysql');
        $stats['total_runs']++;
        $stats['total_warmed'] += $warmed;
        $stats['average_time'] = ($stats['average_time'] * ($stats['total_runs'] - 1) + $time) / $stats['total_runs'];
        
        if (!isset($stats['strategies'][$strategy])) {
            $stats['strategies'][$strategy] = array(
                'runs' => 0,
                'warmed' => 0,
                'average_time' => 0
            );
        }
        
        $strategy_stats = &$stats['strategies'][$strategy];
        $strategy_stats['runs']++;
        $strategy_stats['warmed'] += $warmed;
        $strategy_stats['average_time'] = ($strategy_stats['average_time'] * ($strategy_stats['runs'] - 1) + $time) / $strategy_stats['runs'];
        
        update_option('scd_cache_warming_stats', $stats);
    }

    /**
     * Cache warming callback methods.
     */

    /**
     * Get active campaigns.
     *
     * @since    1.0.0
     * @return   array    Active campaigns.
     */
    public function get_active_campaigns(): array {
        return $this->campaign_manager->get_campaigns(array('status' => 'active'));
    }

    /**
     * Get plugin settings.
     *
     * @since    1.0.0
     * @return   array    Plugin settings.
     */
    public function get_plugin_settings(): array {
        return get_option('scd_settings', array());
    }

    /**
     * Get discount strategies.
     *
     * @since    1.0.0
     * @return   array    Available discount strategies.
     */
    public function get_discount_strategies(): array {
        return array(
            'percentage' => 'Percentage Discount',
            'fixed' => 'Fixed Amount Discount',
            'bogo' => 'Buy One Get One'
        );
    }

    /**
     * Get all campaigns.
     *
     * @since    1.0.0
     * @return   array    All campaigns.
     */
    public function get_all_campaigns(): array {
        return $this->campaign_manager->get_campaigns();
    }

    /**
     * Get campaign statistics.
     *
     * @since    1.0.0
     * @return   array    Campaign statistics.
     */
    public function get_campaign_stats(): array {
        return array(
            'total' => $this->campaign_manager->count_campaigns(),
            'active' => $this->campaign_manager->count_campaigns(array('status' => 'active')),
            'scheduled' => $this->campaign_manager->count_campaigns(array('status' => 'scheduled')), 
            'expired' => $this->campaign_manager->count_campaigns(array('status' => 'expired'))
        );
    }

    /**
     * Get scheduled campaigns.
     *
     * @since    1.0.0
     * @return   array    Scheduled campaigns.
     */
    public function get_scheduled_campaigns(): array {
        return $this->campaign_manager->get_campaigns(array('status' => 'scheduled'));
    }

    /**
     * Get dashboard metrics.
     *
     * @since    1.0.0
     * @return   array    Dashboard metrics.
     */
    public function get_dashboard_metrics(): array {
        return $this->analytics_collector->get_dashboard_metrics();
    }

    /**
     * Get top campaigns.
     *
     * @since    1.0.0
     * @return   array    Top performing campaigns.
     */
    public function get_top_campaigns(): array {
        return $this->analytics_collector->get_top_campaigns(10);
    }

    /**
     * Get revenue statistics.
     *
     * @since    1.0.0
     * @return   array    Revenue statistics.
     */
    public function get_revenue_stats(): array {
        return $this->analytics_collector->get_revenue_stats();
    }

    /**
     * Get discounted products.
     *
     * @since    1.0.0
     * @return   array    Products with active discounts.
     */
    public function get_discounted_products(): array {
        // This would integrate with WooCommerce
        return array();
    }

    /**
     * Get product categories.
     *
     * @since    1.0.0
     * @return   array    Product categories.
     */
    public function get_product_categories(): array {
        if (!function_exists('wc_get_product_categories')) {
            return array();
        }

        return wc_get_product_categories();
    }

    /**
     * Get popular products.
     *
     * @since    1.0.0
     * @return   array    Popular products.
     */
    public function get_popular_products(): array {
        // This would integrate with WooCommerce analytics
        return array();
    }

    /**
     * Get campaign products.
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $campaign_id    Campaign ID.
     * @return   array                  Campaign products.
     */
    private function get_campaign_products(int $campaign_id): array {
        // This would integrate with product selector
        return array();
    }

    /**
     * Schedule cache warming.
     *
     * @since    1.0.0
     * @param    string    $strategy    Strategy to schedule.
     * @param    string    $schedule    Schedule frequency.
     * @return   bool                   Success status.
     */
    public function schedule_warming(string $strategy, string $schedule = 'daily'): bool {
        $hook = "scd_cache_warm_up_{$strategy}";
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook);
        
        // Schedule new warming
        return wp_schedule_event(time(), $schedule, $hook, [$strategy]);
    }

    /**
     * Unschedule cache warming.
     *
     * @since    1.0.0
     * @param    string    $strategy    Strategy to unschedule.
     * @return   bool                   Success status.
     */
    public function unschedule_warming(string $strategy): bool {
        $hook = "scd_cache_warm_up_{$strategy}";
        return wp_clear_scheduled_hook($hook) !== false;
    }

    /**
     * Get warming configuration.
     *
     * @since    1.0.0
     * @return   array    Warming configuration.
     */
    public function get_warming_config(): array {
        return array(
            'strategies' => $this->strategies,
            'scheduled_events' => $this->get_scheduled_events(),
            'stats' => $this->get_warming_stats()
        );
    }

    /**
     * Get scheduled warming events.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Scheduled events.
     */
    private function get_scheduled_events(): array {
        $events = array();
        
        foreach (array_keys($this->strategies) as $strategy) {
            $hook = "scd_cache_warm_up_{$strategy}";
            $next_run = wp_next_scheduled($hook);
            
            if ($next_run) {
                $events[$strategy] = array(
                    'hook' => $hook,
                    'next_run' => $next_run,
                    'next_run_human' => human_time_diff($next_run)
                );
            }
        }
        
        return $events;
    }
}

