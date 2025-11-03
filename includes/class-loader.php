<?php
/**
 * Loader Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-loader.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	 */
	protected array $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
	 */
	protected array $filters;

	/**
	 * The array of shortcodes registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $shortcodes    The shortcodes registered with WordPress.
	 */
	protected array $shortcodes;

	/**
	 * Initialize the collections used to maintain the actions, filters, and shortcodes.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->actions    = array();
		$this->filters    = array();
		$this->shortcodes = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook             The name of the WordPress action that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the action is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook             The name of the WordPress filter that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new shortcode to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $tag              The name of the new shortcode.
	 * @param    object $component        A reference to the instance of the object on which the shortcode is defined.
	 * @param    string $callback         The name of the function that defines the shortcode.
	 */
	public function add_shortcode( string $tag, object $component, string $callback ): void {
		$this->shortcodes = $this->add( $this->shortcodes, $tag, $component, $callback );
	}

	/**
	 * A utility function that is used to register the actions, filters, and shortcodes into a single
	 * collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $hooks            The collection of hooks that is being registered (that is, actions, filters, or shortcodes).
	 * @param    string $hook             The name of the WordPress filter that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         The priority at which the function should be fired.
	 * @param    int    $accepted_args    The number of arguments that should be passed to the $callback.
	 * @return   array                                  The collection of actions, filters, and shortcodes registered with WordPress.
	 */
	private function add( array $hooks, string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): array {
		array_push(
			$hooks,
			array(
				'hook'          => $hook,
				'component'     => $component,
				'callback'      => $callback,
				'priority'      => $priority,
				'accepted_args' => $accepted_args,
			)
		);

		return $hooks;
	}

	/**
	 * Register the filters, actions, and shortcodes with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void {
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			SCD_Log::debug(
				'Loader: Running hook registration',
				array(
					'actions_count' => count( $this->actions ),
					'filters_count' => count( $this->filters ),
				)
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);

			// Debug specific hooks
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $hook['hook'], 'woocommerce_product_get_price' ) !== false ) {
				SCD_Log::debug(
					'Loader: Registered filter',
					array(
						'hook'      => $hook['hook'],
						'component' => get_class( $hook['component'] ),
						'callback'  => $hook['callback'],
					)
				);
			}
		}

		foreach ( $this->shortcodes as $hook ) {
			add_shortcode(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] )
			);
		}
	}

	/**
	 * Remove a specific action from the collection.
	 *
	 * @since    1.0.0
	 * @param    string $hook        The name of the WordPress action.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @param    int    $priority    The priority of the action.
	 * @return   bool                   True if removed, false otherwise.
	 */
	public function remove_action( string $hook, object $component, string $callback, int $priority = 10 ): bool {
		return $this->remove_hook( $this->actions, $hook, $component, $callback, $priority );
	}

	/**
	 * Remove a specific filter from the collection.
	 *
	 * @since    1.0.0
	 * @param    string $hook        The name of the WordPress filter.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @param    int    $priority    The priority of the filter.
	 * @return   bool                   True if removed, false otherwise.
	 */
	public function remove_filter( string $hook, object $component, string $callback, int $priority = 10 ): bool {
		return $this->remove_hook( $this->filters, $hook, $component, $callback, $priority );
	}

	/**
	 * Remove a specific shortcode from the collection.
	 *
	 * @since    1.0.0
	 * @param    string $tag         The shortcode tag.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @return   bool                   True if removed, false otherwise.
	 */
	public function remove_shortcode( string $tag, object $component, string $callback ): bool {
		return $this->remove_hook( $this->shortcodes, $tag, $component, $callback );
	}

	/**
	 * Utility function to remove a hook from a collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $hooks       The collection of hooks.
	 * @param    string $hook        The hook name.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @param    int    $priority    The priority (optional for shortcodes).
	 * @return   bool                   True if removed, false otherwise.
	 */
	private function remove_hook( array &$hooks, string $hook, object $component, string $callback, int $priority = 10 ): bool {
		foreach ( $hooks as $key => $registered_hook ) {
			if ( isset( $registered_hook['hook'] ) && $registered_hook['hook'] === $hook &&
				isset( $registered_hook['component'] ) && $registered_hook['component'] === $component &&
				isset( $registered_hook['callback'] ) && $registered_hook['callback'] === $callback &&
				( isset( $registered_hook['priority'] ) ? $registered_hook['priority'] === $priority : true ) ) {
				unset( $hooks[ $key ] );
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all registered actions.
	 *
	 * @since    1.0.0
	 * @return   array    The registered actions.
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Get all registered filters.
	 *
	 * @since    1.0.0
	 * @return   array    The registered filters.
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	/**
	 * Get all registered shortcodes.
	 *
	 * @since    1.0.0
	 * @return   array    The registered shortcodes.
	 */
	public function get_shortcodes(): array {
		return $this->shortcodes;
	}

	/**
	 * Check if a specific action is registered.
	 *
	 * @since    1.0.0
	 * @param    string $hook        The action hook name.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @param    int    $priority    The priority.
	 * @return   bool                   True if registered, false otherwise.
	 */
	public function has_action( string $hook, object $component, string $callback, int $priority = 10 ): bool {
		return $this->has_hook( $this->actions, $hook, $component, $callback, $priority );
	}

	/**
	 * Check if a specific filter is registered.
	 *
	 * @since    1.0.0
	 * @param    string $hook        The filter hook name.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @param    int    $priority    The priority.
	 * @return   bool                   True if registered, false otherwise.
	 */
	public function has_filter( string $hook, object $component, string $callback, int $priority = 10 ): bool {
		return $this->has_hook( $this->filters, $hook, $component, $callback, $priority );
	}

	/**
	 * Check if a specific shortcode is registered.
	 *
	 * @since    1.0.0
	 * @param    string $tag         The shortcode tag.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @return   bool                   True if registered, false otherwise.
	 */
	public function has_shortcode( string $tag, object $component, string $callback ): bool {
		return $this->has_hook( $this->shortcodes, $tag, $component, $callback );
	}

	/**
	 * Utility function to check if a hook exists in a collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $hooks       The collection of hooks.
	 * @param    string $hook        The hook name.
	 * @param    object $component   The component object.
	 * @param    string $callback    The callback method name.
	 * @param    int    $priority    The priority (optional for shortcodes).
	 * @return   bool                   True if exists, false otherwise.
	 */
	private function has_hook( array $hooks, string $hook, object $component, string $callback, int $priority = 10 ): bool {
		foreach ( $hooks as $registered_hook ) {
			if ( isset( $registered_hook['hook'] ) && $registered_hook['hook'] === $hook &&
				isset( $registered_hook['component'] ) && $registered_hook['component'] === $component &&
				isset( $registered_hook['callback'] ) && $registered_hook['callback'] === $callback &&
				( isset( $registered_hook['priority'] ) ? $registered_hook['priority'] === $priority : true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the total count of registered hooks.
	 *
	 * @since    1.0.0
	 * @return   array    Array with counts of actions, filters, and shortcodes.
	 */
	public function get_hook_counts(): array {
		return array(
			'actions'    => count( $this->actions ),
			'filters'    => count( $this->filters ),
			'shortcodes' => count( $this->shortcodes ),
			'total'      => count( $this->actions ) + count( $this->filters ) + count( $this->shortcodes ),
		);
	}

	/**
	 * Clear all registered hooks.
	 *
	 * @since    1.0.0
	 */
	public function clear_all_hooks(): void {
		$this->actions    = array();
		$this->filters    = array();
		$this->shortcodes = array();
	}

	/**
	 * Get hooks by component.
	 *
	 * @since    1.0.0
	 * @param    object $component    The component to search for.
	 * @return   array                   Array of hooks for the component.
	 */
	public function get_hooks_by_component( object $component ): array {
		$component_hooks = array(
			'actions'    => array(),
			'filters'    => array(),
			'shortcodes' => array(),
		);

		foreach ( $this->actions as $hook ) {
			if ( $hook['component'] === $component ) {
				array_push( $component_hooks['actions'], $hook );
			}
		}

		foreach ( $this->filters as $hook ) {
			if ( $hook['component'] === $component ) {
				array_push( $component_hooks['filters'], $hook );
			}
		}

		foreach ( $this->shortcodes as $hook ) {
			if ( $hook['component'] === $component ) {
				array_push( $component_hooks['shortcodes'], $hook );
			}
		}

		return $component_hooks;
	}
}
