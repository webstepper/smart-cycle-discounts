<?php
/**
 * Asset Dependency Resolver
 *
 * Handles dependency resolution and optimization for assets.
 *
 * @package SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/admin/assets
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Asset Dependency Resolver Class
 *
 * @since 1.0.0
 */
class SCD_Asset_Dependency_Resolver {

    /**
     * Dependency graph for scripts.
     *
     * @since 1.0.0
     * @var array
     */
    private array $script_graph = array();

    /**
     * Dependency graph for styles.
     *
     * @since 1.0.0
     * @var array
     */
    private array $style_graph = array();

    /**
     * Resolved dependencies cache.
     *
     * @since 1.0.0
     * @var array
     */
    private array $resolved_cache = array(
        'scripts' => array(),
        'styles' => array()
    );

    /**
     * Circular dependency detection.
     *
     * @since 1.0.0
     * @var array
     */
    private array $visiting = array();

    /**
     * Script registry.
     *
     * @since 1.0.0
     * @var SCD_Script_Registry
     */
    private SCD_Script_Registry $script_registry;

    /**
     * Style registry.
     *
     * @since 1.0.0
     * @var SCD_Style_Registry
     */
    private SCD_Style_Registry $style_registry;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param SCD_Script_Registry $script_registry Script registry.
     * @param SCD_Style_Registry  $style_registry  Style registry.
     */
    public function __construct(
        SCD_Script_Registry $script_registry,
        SCD_Style_Registry $style_registry
    ) {
        $this->script_registry = $script_registry;
        $this->style_registry = $style_registry;
    }

    /**
     * Initialize resolver.
     *
     * @since 1.0.0
     * @return void
     */
    public function init(): void {
        // Build dependency graphs
        $this->build_dependency_graphs();
        
        // Hook into asset loading
        add_filter('scd_before_enqueue_script', array($this, 'resolve_script_dependencies'), 10, 2);
        add_filter('scd_before_enqueue_style', array($this, 'resolve_style_dependencies'), 10, 2);
        
        // Optimization hooks
        add_action('scd_optimize_assets', array($this, 'optimize_asset_loading'));
    }

    /**
     * Build dependency graphs.
     *
     * @since 1.0.0
     * @return void
     */
    private function build_dependency_graphs(): void {
        // Build script graph
        $scripts = $this->script_registry->get_all_scripts();
        foreach ($scripts as $handle => $script) {
            $this->script_graph[$handle] = $script['deps'] ?? array();
        }

        // Build style graph
        $styles = $this->style_registry->get_all_styles();
        foreach ($styles as $handle => $style) {
            $this->style_graph[$handle] = $style['deps'] ?? array();
        }

        // Validate graphs
        $this->validate_graphs();
    }

    /**
     * Validate dependency graphs for circular dependencies.
     *
     * @since 1.0.0
     * @return void
     * @throws Exception If circular dependency detected.
     */
    private function validate_graphs(): void {
        // Validate scripts
        foreach (array_keys($this->script_graph) as $handle) {
            $this->visiting = array();
            if ($this->has_circular_dependency($handle, $this->script_graph)) {
                throw new Exception(
                    sprintf(
                        'Circular dependency detected in script: %s',
                        $handle
                    )
                );
            }
        }

        // Validate styles
        foreach (array_keys($this->style_graph) as $handle) {
            $this->visiting = array();
            if ($this->has_circular_dependency($handle, $this->style_graph)) {
                throw new Exception(
                    sprintf(
                        'Circular dependency detected in style: %s',
                        $handle
                    )
                );
            }
        }
    }

    /**
     * Check for circular dependencies.
     *
     * @since 1.0.0
     * @param string $node  Current node.
     * @param array  $graph Dependency graph.
     * @return bool True if circular dependency exists.
     */
    private function has_circular_dependency(string $node, array $graph): bool {
        if (isset($this->visiting[$node])) {
            return true;
        }

        $this->visiting[$node] = true;

        $dependencies = $graph[$node] ?? array();
        foreach ($dependencies as $dep) {
            if ($this->has_circular_dependency($dep, $graph)) {
                return true;
            }
        }

        unset($this->visiting[$node]);
        return false;
    }

    /**
     * Resolve script dependencies.
     *
     * @since 1.0.0
     * @param array  $script Script config.
     * @param string $handle Script handle.
     * @return array Modified script config.
     */
    public function resolve_script_dependencies(array $script, string $handle): array {
        if (isset($this->resolved_cache['scripts'][$handle])) {
            $script['deps'] = $this->resolved_cache['scripts'][$handle];
            return $script;
        }

        $resolved = $this->resolve_dependencies($handle, $this->script_graph);
        $this->resolved_cache['scripts'][$handle] = $resolved;
        
        $script['deps'] = $resolved;
        return $script;
    }

    /**
     * Resolve style dependencies.
     *
     * @since 1.0.0
     * @param array  $style  Style config.
     * @param string $handle Style handle.
     * @return array Modified style config.
     */
    public function resolve_style_dependencies(array $style, string $handle): array {
        if (isset($this->resolved_cache['styles'][$handle])) {
            $style['deps'] = $this->resolved_cache['styles'][$handle];
            return $style;
        }

        $resolved = $this->resolve_dependencies($handle, $this->style_graph);
        $this->resolved_cache['styles'][$handle] = $resolved;
        
        $style['deps'] = $resolved;
        return $style;
    }

    /**
     * Resolve dependencies for a node.
     *
     * @since 1.0.0
     * @param string $node  Node to resolve.
     * @param array  $graph Dependency graph.
     * @return array Resolved dependencies in order.
     */
    private function resolve_dependencies(string $node, array $graph): array {
        $resolved = array();
        $visited = array();
        
        $this->depth_first_search($node, $graph, $resolved, $visited);
        
        // Remove the node itself from dependencies
        $key = array_search($node, $resolved, true);
        if ($key !== false) {
            unset($resolved[$key]);
        }
        
        return array_values($resolved);
    }

    /**
     * Depth-first search for dependency resolution.
     *
     * @since 1.0.0
     * @param string $node     Current node.
     * @param array  $graph    Dependency graph.
     * @param array  $resolved Resolved nodes.
     * @param array  $visited  Visited nodes.
     * @return void
     */
    private function depth_first_search(
        string $node,
        array $graph,
        array &$resolved,
        array &$visited
    ): void {
        if (isset($visited[$node])) {
            return;
        }

        $visited[$node] = true;

        $dependencies = $graph[$node] ?? array();
        foreach ($dependencies as $dep) {
            $this->depth_first_search($dep, $graph, $resolved, $visited);
        }

        $resolved[] = $node;
    }

    /**
     * Optimize asset loading.
     *
     * @since 1.0.0
     * @return void
     */
    public function optimize_asset_loading(): void {
        // Get load order optimization
        $script_order = $this->get_optimized_load_order('scripts');
        $style_order = $this->get_optimized_load_order('styles');

        // Store optimization data
        set_transient('scd_optimized_script_order', $script_order, DAY_IN_SECONDS);
        set_transient('scd_optimized_style_order', $style_order, DAY_IN_SECONDS);
    }

    /**
     * Get optimized load order.
     *
     * @since 1.0.0
     * @param string $type Asset type (scripts|styles).
     * @return array Optimized load order.
     */
    private function get_optimized_load_order(string $type): array {
        $graph = $type === 'scripts' ? $this->script_graph : $this->style_graph;
        $order = array();
        $visited = array();

        // Topological sort
        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $this->topological_sort($node, $graph, $order, $visited);
            }
        }

        return array_reverse($order);
    }

    /**
     * Topological sort for optimization.
     *
     * @since 1.0.0
     * @param string $node    Current node.
     * @param array  $graph   Dependency graph.
     * @param array  $order   Load order.
     * @param array  $visited Visited nodes.
     * @return void
     */
    private function topological_sort(
        string $node,
        array $graph,
        array &$order,
        array &$visited
    ): void {
        $visited[$node] = true;

        $dependencies = $graph[$node] ?? array();
        foreach ($dependencies as $dep) {
            if (!isset($visited[$dep])) {
                $this->topological_sort($dep, $graph, $order, $visited);
            }
        }

        $order[] = $node;
    }

    /**
     * Get dependency tree for debugging.
     *
     * @since 1.0.0
     * @param string $handle Asset handle.
     * @param string $type   Asset type (script|style).
     * @return array Dependency tree.
     */
    public function get_dependency_tree(string $handle, string $type = 'script'): array {
        $graph = $type === 'script' ? $this->script_graph : $this->style_graph;
        
        return $this->build_tree($handle, $graph);
    }

    /**
     * Build dependency tree.
     *
     * @since 1.0.0
     * @param string $node  Current node.
     * @param array  $graph Dependency graph.
     * @param int    $depth Current depth.
     * @return array Tree structure.
     */
    private function build_tree(string $node, array $graph, int $depth = 0): array {
        if ($depth > 10) {
            return array(
                'handle' => $node,
                'error' => 'Max depth reached'
            );
        }

        $dependencies = $graph[$node] ?? array();
        $children = array();

        foreach ($dependencies as $dep) {
            $children[] = $this->build_tree($dep, $graph, $depth + 1);
        }

        return array(
            'handle' => $node,
            'dependencies' => $children
        );
    }

    /**
     * Get all dependents of an asset.
     *
     * @since 1.0.0
     * @param string $handle Asset handle.
     * @param string $type   Asset type (script|style).
     * @return array Assets that depend on this asset.
     */
    public function get_dependents(string $handle, string $type = 'script'): array {
        $graph = $type === 'script' ? $this->script_graph : $this->style_graph;
        $dependents = array();

        foreach ($graph as $node => $deps) {
            if (in_array($handle, $deps, true)) {
                $dependents[] = $node;
            }
        }

        return $dependents;
    }

    /**
     * Check if asset can be loaded.
     *
     * @since 1.0.0
     * @param string $handle Asset handle.
     * @param string $type   Asset type (script|style).
     * @param array  $loaded Already loaded assets.
     * @return bool True if can be loaded.
     */
    public function can_load(string $handle, string $type, array $loaded): bool {
        $graph = $type === 'script' ? $this->script_graph : $this->style_graph;
        $dependencies = $graph[$handle] ?? array();

        foreach ($dependencies as $dep) {
            if (!in_array($dep, $loaded, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing dependencies.
     *
     * @since 1.0.0
     * @param string $handle Asset handle.
     * @param string $type   Asset type (script|style).
     * @param array  $loaded Already loaded assets.
     * @return array Missing dependencies.
     */
    public function get_missing_dependencies(
        string $handle,
        string $type,
        array $loaded
    ): array {
        $graph = $type === 'script' ? $this->script_graph : $this->style_graph;
        $dependencies = $graph[$handle] ?? array();
        $missing = array();

        foreach ($dependencies as $dep) {
            if (!in_array($dep, $loaded, true)) {
                $missing[] = $dep;
            }
        }

        return $missing;
    }

    /**
     * Clear cache.
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_cache(): void {
        $this->resolved_cache = array(
            'scripts' => array(),
            'styles' => array()
        );
        
        delete_transient('scd_optimized_script_order');
        delete_transient('scd_optimized_style_order');
    }
}