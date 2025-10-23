<?php
/**
 * Asset Optimizer
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Asset Optimizer Class
 *
 * Optimizes JavaScript and CSS loading for performance.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Asset_Optimizer {

    /**
     * Asset manager instance
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Asset_Manager    $asset_manager    Asset manager
     */
    private SCD_Asset_Manager $asset_manager;

    /**
     * Optimization configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $config    Configuration
     */
    private array $config;

    /**
     * Constructor
     *
     * @since    1.0.0
     * @param    SCD_Asset_Manager    $asset_manager    Asset manager
     * @param    array                $config           Configuration
     */
    public function __construct(SCD_Asset_Manager $asset_manager, array $config = array()) {
        $this->asset_manager = $asset_manager;
        
        $this->config = wp_parse_args($config, array(
            'conditional_loading' => true,
            'lazy_loading' => true,
            'minification' => !defined('SCRIPT_DEBUG') || !SCRIPT_DEBUG,
            'concatenation' => false,
            'async_scripts' => array(),
            'defer_scripts' => array(),
            'preload_assets' => array(),
            'critical_css' => true
        ));
    }

    /**
     * Initialize optimizer
     *
     * @since    1.0.0
     * @return   void
     */
    public function init(): void {
        // Add filters for script loading
        add_filter('script_loader_tag', array($this, 'optimize_script_tag'), 10, 3);
        add_filter('style_loader_tag', array($this, 'optimize_style_tag'), 10, 4);
        
        // Add preload hints
        add_action('admin_head', array($this, 'add_preload_hints'), 5);
        
        // Conditional loading
        if ($this->config['conditional_loading']) {
            add_action('admin_enqueue_scripts', array($this, 'conditional_enqueue'), 100);
        }
    }

    /**
     * Optimize script tags
     *
     * @since    1.0.0
     * @param    string    $tag       Script tag
     * @param    string    $handle    Script handle
     * @param    string    $src       Script source
     * @return   string               Optimized tag
     */
    public function optimize_script_tag(string $tag, string $handle, string $src): string {
        // Skip if not our script
        if (strpos($handle, 'scd-') !== 0) {
            return $tag;
        }

        // Add async attribute
        if (in_array($handle, $this->config['async_scripts'], true)) {
            $tag = str_replace(' src=', ' async src=', $tag);
        }

        // Add defer attribute
        if (in_array($handle, $this->config['defer_scripts'], true)) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }

        // Add module type for ES6 modules
        if ($this->is_module($handle)) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }

        return $tag;
    }

    /**
     * Optimize style tags
     *
     * @since    1.0.0
     * @param    string    $tag       Style tag
     * @param    string    $handle    Style handle
     * @param    string    $href      Style href
     * @param    string    $media     Media attribute
     * @return   string               Optimized tag
     */
    public function optimize_style_tag(string $tag, string $handle, string $href, string $media): string {
        // Skip if not our style
        if (strpos($handle, 'scd-') !== 0) {
            return $tag;
        }

        // Implement critical CSS loading
        if ($this->config['critical_css'] && $this->is_non_critical($handle)) {
            // Load non-critical CSS asynchronously
            $tag = sprintf(
                '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" />',
                esc_url($href)
            );
            $tag .= '<noscript>' . str_replace('stylesheet', 'stylesheet', $tag) . '</noscript>';
        }

        return $tag;
    }

    /**
     * Add preload hints
     *
     * @since    1.0.0
     * @return   void
     */
    public function add_preload_hints(): void {
        foreach ($this->config['preload_assets'] as $asset) {
            $type = $asset['type'] ?? 'script';
            $href = $asset['href'] ?? '';
            
            if (empty($href)) {
                continue;
            }

            printf(
                '<link rel="preload" href="%s" as="%s"%s />',
                esc_url($href),
                esc_attr($type),
                isset($asset['crossorigin']) ? ' crossorigin' : ''
            );
        }
    }

    /**
     * Conditional asset enqueueing
     *
     * @since    1.0.0
     * @return   void
     */
    public function conditional_enqueue(): void {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Define asset loading rules
        $rules = $this->get_conditional_rules();
        
        foreach ($rules as $rule) {
            if ($this->evaluate_condition($rule['condition'], $screen)) {
                // Load assets
                foreach ($rule['scripts'] ?? array() as $script) {
                    wp_enqueue_script($script);
                }
                
                foreach ($rule['styles'] ?? array() as $style) {
                    wp_enqueue_style($style);
                }
            } else {
                // Dequeue assets if condition not met
                foreach ($rule['scripts'] ?? array() as $script) {
                    wp_dequeue_script($script);
                }
                
                foreach ($rule['styles'] ?? array() as $style) {
                    wp_dequeue_style($style);
                }
            }
        }
    }

    /**
     * Get conditional loading rules
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Loading rules
     */
    private function get_conditional_rules(): array {
        return array(
            // Wizard assets - only on wizard pages
            array(
                'condition' => array(
                    'page' => 'scd-campaigns',
                    'action' => 'wizard'
                ),
                'scripts' => array(
                    'scd-wizard',
                    'scd-wizard-navigation',
                    'scd-validation-manager',
                    'scd-discounts-loader',
                    'scd-schedule-loader'
                ),
                'styles' => array(
                    'scd-wizard',
                    'scd-wizard-steps'
                )
            ),
            
            // Campaign list assets
            array(
                'condition' => array(
                    'page' => 'scd-campaigns',
                    'action' => null
                ),
                'scripts' => array(
                    'scd-campaigns-list',
                    'scd-bulk-actions'
                ),
                'styles' => array(
                    'scd-campaigns-list'
                )
            ),
            
            // Analytics assets
            array(
                'condition' => array(
                    'page' => 'scd-analytics'
                ),
                'scripts' => array(
                    'scd-analytics',
                    'scd-charts'
                ),
                'styles' => array(
                    'scd-analytics'
                )
            ),
            
            // Settings assets
            array(
                'condition' => array(
                    'page' => 'scd-settings'
                ),
                'scripts' => array(
                    'scd-settings'
                ),
                'styles' => array(
                    'scd-settings'
                )
            )
        );
    }

    /**
     * Evaluate loading condition
     *
     * @since    1.0.0
     * @access   private
     * @param    array          $condition    Condition to evaluate
     * @param    WP_Screen      $screen       Current screen
     * @return   bool                         True if condition met
     */
    private function evaluate_condition(array $condition, WP_Screen $screen): bool {
        // Check page
        if (isset($condition['page'])) {
            $page = $_GET['page'] ?? '';
            if ($page !== $condition['page']) {
                return false;
            }
        }

        // Check action
        if (array_key_exists('action', $condition)) {
            $action = $_GET['action'] ?? null;
            if ($action !== $condition['action']) {
                return false;
            }
        }

        // Check screen ID
        if (isset($condition['screen_id']) && $screen->id !== $condition['screen_id']) {
            return false;
        }

        // Check post type
        if (isset($condition['post_type']) && $screen->post_type !== $condition['post_type']) {
            return false;
        }

        return true;
    }

    /**
     * Check if script is ES6 module
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $handle    Script handle
     * @return   bool                 True if module
     */
    private function is_module(string $handle): bool {
        $modules = array(
            'scd-shared-utils',
            'scd-validation-manager'
        );

        return in_array($handle, $modules, true);
    }

    /**
     * Check if style is non-critical
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $handle    Style handle
     * @return   bool                 True if non-critical
     */
    private function is_non_critical(string $handle): bool {
        $critical = array(
            'scd-admin-core',
            'scd-admin-layout'
        );

        return !in_array($handle, $critical, true);
    }

    /**
     * Generate asset chunks for lazy loading
     *
     * @since    1.0.0
     * @return   array    Asset chunks
     */
    public function get_lazy_load_chunks(): array {
        return array(
            'wizard' => array(
                'trigger' => '.scd-wizard-container',
                'scripts' => array(
                    'scd-discounts-loader',
                    'scd-schedule-loader',
                    'scd-review-loader'
                )
            ),
            'analytics' => array(
                'trigger' => '.scd-analytics-container',
                'scripts' => array(
                    'scd-charts',
                    'scd-analytics-reports'
                )
            )
        );
    }

    /**
     * Get minified asset path
     *
     * @since    1.0.0
     * @param    string    $path    Original path
     * @return   string             Minified path
     */
    public function get_minified_path(string $path): string {
        if (!$this->config['minification']) {
            return $path;
        }

        // Check if minified version exists
        $minified = str_replace('.js', '.min.js', $path);
        $minified = str_replace('.css', '.min.css', $minified);

        if (file_exists(SCD_PLUGIN_DIR . $minified)) {
            return $minified;
        }

        return $path;
    }
}