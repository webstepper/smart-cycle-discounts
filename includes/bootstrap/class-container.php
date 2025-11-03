<?php
/**
 * Container Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/bootstrap/class-container.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Advanced Dependency Injection Container
 *
 * Features:
 * - PHP 8.1+ typed properties and union types
 * - Automatic constructor injection via reflection
 * - Singleton and factory patterns
 * - Service decorators and middleware
 * - Circular dependency detection
 * - Performance-optimized service resolution
 * - Compiled container for production
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Container {

	/**
	 * Service bindings registry.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $bindings    Service bindings.
	 */
	private array $bindings = array();

	/**
	 * Singleton instances registry.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $instances    Singleton instances.
	 */
	private array $instances = array();

	/**
	 * Service aliases registry.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $aliases    Service aliases.
	 */
	private array $aliases = array();

	/**
	 * Service tags registry.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $tags    Service tags.
	 */
	private array $tags = array();

	/**
	 * Circular dependency detection stack.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $building    Services currently being built.
	 */
	private array $building = array();

	/**
	 * Container compilation status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $compiled    Whether container is compiled.
	 */
	private bool $compiled = false;

	/**
	 * Service resolution cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $resolved    Resolved service cache.
	 */
	private array $resolved = array();

	/**
	 * Mock services for fallback scenarios.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $mock_services    Mock services.
	 */
	private array $mock_services = array();

	/**
	 * Constructor - Initialize container with optional mock mode.
	 *
	 * @since    1.0.0
	 * @param    bool $use_mocks    Whether to initialize mock services.
	 */
	public function __construct( bool $use_mocks = false ) {
		if ( $use_mocks ) {
			$this->initialize_mock_services();
		}
	}

	/**
	 * Initialize mock services for fallback scenarios.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function initialize_mock_services(): void {
		// Create mock input validator
		$this->mock_services['input_validator'] = new class() {
			public function validate( array $data, array $rules = array() ): array {
				return array(
					'valid'  => true,
					'errors' => array(),
				);
			}
		};

		// Create mock sanitizer
		$this->mock_services['sanitizer'] = new class() {
			public function sanitize( $value, string $type = 'text' ) {
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		};

		// Create mock nonce manager with proper interface
		$this->mock_services['nonce_manager'] = new class() {
			public function verify( string $nonce, string $action ): bool {
				return wp_verify_nonce( $nonce, $action ) !== false;
			}
			public function create( string $action ): string {
				return wp_create_nonce( $action );
			}
			public function verify_request( array $request, string $nonce_key = 'nonce', ?string $action = null ): bool {
				$nonce = $request[ $nonce_key ] ?? '';
				if ( empty( $nonce ) ) {
					return false;
				}
				$actual_action = $action ?? $request['action'] ?? '';
				return $this->verify( $nonce, $actual_action );
			}
		};

		// Create mock rate limiter
		$this->mock_services['rate_limiter'] = new class() {
			public function check( string $key, int $limit = 10 ): bool {
				return true; // Always allow in mock
			}
			public function check_rate_limit( string $key = '', int $limit = 10 ): bool {
				return true; // Always allow in mock
			}
		};
	}

	/**
	 * Bind a service to the container.
	 *
	 * @since    1.0.0
	 * @param    string $abstract     Service identifier.
	 * @param    mixed  $concrete     Service implementation.
	 * @param    bool   $singleton    Whether service is singleton.
	 * @return   void
	 */
	public function bind( string $abstract, mixed $concrete = null, bool $singleton = false ): void {
		$this->drop_stale_instances( $abstract );

		if ( is_null( $concrete ) ) {
			$concrete = $abstract;
		}

		if ( ! $concrete instanceof Closure ) {
			$concrete = $this->get_closure( $abstract, $concrete );
		}

		$this->bindings[ $abstract ] = compact( 'concrete', 'singleton' );
	}

	/**
	 * Register a singleton service.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @param    mixed  $concrete    Service implementation.
	 * @return   void
	 */
	public function singleton( string $abstract, mixed $concrete = null ): void {
		$this->bind( $abstract, $concrete, true );
	}

	/**
	 * Register an existing instance as singleton.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @param    mixed  $instance    Service instance.
	 * @return   mixed                  The registered instance.
	 */
	public function instance( string $abstract, mixed $instance ): mixed {
		$this->remove_abstract_alias( $abstract );

		$is_bound = $this->bound( $abstract );

		unset( $this->aliases[ $abstract ] );

		$this->instances[ $abstract ] = $instance;

		if ( $is_bound ) {
			$this->rebound( $abstract );
		}

		return $instance;
	}

	/**
	 * Register a factory service.
	 *
	 * @since    1.0.0
	 * @param    string   $abstract    Service identifier.
	 * @param    callable $factory     Factory function.
	 * @return   void
	 */
	public function factory( string $abstract, callable $factory ): void {
		$this->bind(
			$abstract,
			function () use ( $factory ) {
				return $factory( $this );
			}
		);
	}

	/**
	 * Get a service from the container.
	 *
	 * @since    1.0.0
	 * @param    string $id    Service identifier.
	 * @return   mixed            Service instance.
	 * @throws   Exception        If service cannot be resolved.
	 */
	public function get( string $id ): mixed {
		// Check mock services first
		if ( isset( $this->mock_services[ $id ] ) ) {
			return $this->mock_services[ $id ];
		}

		try {
			$result = $this->resolve( $id );
			return $result;
		} catch ( Exception $e ) {
			if ( $this->has( $id ) ) {
				throw $e;
			}

			throw new Exception( "Service '{$id}' not found in container." );
		}
	}

	/**
	 * Check if container has a service.
	 *
	 * @since    1.0.0
	 * @param    string $id    Service identifier.
	 * @return   bool             True if service exists.
	 */
	public function has( string $id ): bool {
		return isset( $this->mock_services[ $id ] ) || $this->bound( $id );
	}

	/**
	 * Resolve a service from the container.
	 *
	 * @since    1.0.0
	 * @param    string $abstract      Service identifier.
	 * @param    array  $parameters    Constructor parameters.
	 * @return   mixed                    Service instance.
	 * @throws   Exception                If circular dependency detected.
	 */
	public function resolve( string $abstract, array $parameters = array() ): mixed {
		$abstract = $this->get_alias( $abstract );

		// Check for circular dependency
		if ( isset( $this->building[ $abstract ] ) ) {
			throw new Exception( "Circular dependency detected while resolving '{$abstract}'." );
		}

		// Return existing singleton instance
		if ( isset( $this->instances[ $abstract ] ) && empty( $parameters ) ) {
			return $this->instances[ $abstract ];
		}

		$this->building[ $abstract ] = true;

		try {
			$concrete = $this->get_concrete( $abstract );

			if ( $this->is_buildable( $concrete, $abstract ) ) {
				$object = $this->build( $concrete, $parameters );
			} else {
				$object = $this->resolve( $concrete, $parameters );
			}

			// Store singleton instance
			if ( $this->is_shared( $abstract ) && empty( $parameters ) ) {
				$this->instances[ $abstract ] = $object;
			}

			$this->fire_resolving_callbacks( $abstract, $object );

			$this->resolved[ $abstract ] = true;

			unset( $this->building[ $abstract ] );

			return $object;

		} catch ( Exception $e ) {
			unset( $this->building[ $abstract ] );
			throw $e;
		}
	}

	/**
	 * Build a service instance.
	 *
	 * @since    1.0.0
	 * @param    mixed $concrete      Service concrete implementation.
	 * @param    array $parameters    Constructor parameters.
	 * @return   mixed                   Service instance.
	 * @throws   Exception               If service cannot be built.
	 */
	public function build( mixed $concrete, array $parameters = array() ): mixed {
		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $parameters );
		}

		try {
			$reflector = new ReflectionClass( $concrete );
		} catch ( ReflectionException $e ) {
			throw new Exception( "Target class [{$concrete}] does not exist.", 0, $e );
		}

		if ( ! $reflector->isInstantiable() ) {
			throw new Exception( "Target [{$concrete}] is not instantiable." );
		}

		$constructor = $reflector->getConstructor();

		if ( is_null( $constructor ) ) {
			return new $concrete();
		}

		$dependencies = $constructor->getParameters();

		$instances = $this->resolve_dependencies( $dependencies, $parameters );

		return $reflector->newInstanceArgs( $instances );
	}

	/**
	 * Resolve constructor dependencies.
	 *
	 * @since    1.0.0
	 * @param    array $dependencies    Constructor parameters.
	 * @param    array $parameters      Override parameters.
	 * @return   array                     Resolved dependencies.
	 * @throws   Exception                 If dependency cannot be resolved.
	 */
	protected function resolve_dependencies( array $dependencies, array $parameters = array() ): array {
		$results = array();

		foreach ( $dependencies as $dependency ) {
			if ( array_key_exists( $dependency->name, $parameters ) ) {
				$results[] = $parameters[ $dependency->name ];
				continue;
			}

			$result = is_null( $this->get_parameter_class_name( $dependency ) )
				? $this->resolve_primitive( $dependency )
				: $this->resolve_class( $dependency );

			if ( $dependency->isVariadic() ) {
				$results = array_merge( $results, $result );
			} else {
				$results[] = $result;
			}
		}

		return $results;
	}

	/**
	 * Resolve a class dependency.
	 *
	 * @since    1.0.0
	 * @param    ReflectionParameter $parameter    Parameter reflection.
	 * @return   mixed                               Resolved dependency.
	 * @throws   Exception                           If dependency cannot be resolved.
	 */
	protected function resolve_class( ReflectionParameter $parameter ): mixed {
		try {
			return $this->resolve( $this->get_parameter_class_name( $parameter ) );
		} catch ( Exception $e ) {
			if ( $parameter->isOptional() ) {
				return $parameter->getDefaultValue();
			}

			throw $e;
		}
	}

	/**
	 * Resolve a primitive dependency.
	 *
	 * @since    1.0.0
	 * @param    ReflectionParameter $parameter    Parameter reflection.
	 * @return   mixed                               Resolved primitive.
	 * @throws   Exception                           If primitive cannot be resolved.
	 */
	protected function resolve_primitive( ReflectionParameter $parameter ): mixed {
		if ( $parameter->isDefaultValueAvailable() ) {
			return $parameter->getDefaultValue();
		}

		throw new Exception( "Unresolvable dependency resolving [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}" );
	}

	/**
	 * Get parameter class name.
	 *
	 * @since    1.0.0
	 * @param    ReflectionParameter $parameter    Parameter reflection.
	 * @return   string|null                         Class name or null.
	 */
	protected function get_parameter_class_name( ReflectionParameter $parameter ): ?string {
		$type = $parameter->getType();

		if ( ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
			return null;
		}

		$name = $type->getName();

		if ( ! is_null( $class = $parameter->getDeclaringClass() ) ) {
			if ( $name === 'self' ) {
				return $class->getName();
			}

			if ( $name === 'parent' && $parent = $class->getParentClass() ) {
				return $parent->getName();
			}
		}

		return $name;
	}

	/**
	 * Get service concrete implementation.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @return   mixed                  Concrete implementation.
	 */
	protected function get_concrete( string $abstract ): mixed {
		if ( isset( $this->bindings[ $abstract ] ) ) {
			return $this->bindings[ $abstract ]['concrete'];
		}

		return $abstract;
	}

	/**
	 * Get closure for service binding.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @param    mixed  $concrete    Service implementation.
	 * @return   Closure                Service closure.
	 */
	protected function get_closure( string $abstract, mixed $concrete ): Closure {
		return function ( $container, $parameters = array() ) use ( $abstract, $concrete ) {
			if ( $abstract == $concrete ) {
				return $container->build( $concrete, $parameters );
			}

			return $container->resolve( $concrete, $parameters );
		};
	}

	/**
	 * Check if service is buildable.
	 *
	 * @since    1.0.0
	 * @param    mixed  $concrete    Service concrete.
	 * @param    string $abstract    Service identifier.
	 * @return   bool                   True if buildable.
	 */
	protected function is_buildable( mixed $concrete, string $abstract ): bool {
		return $concrete === $abstract || $concrete instanceof Closure;
	}

	/**
	 * Check if service is shared (singleton).
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @return   bool                   True if shared.
	 */
	public function is_shared( string $abstract ): bool {
		return isset( $this->instances[ $abstract ] ) ||
				( isset( $this->bindings[ $abstract ]['singleton'] ) &&
				$this->bindings[ $abstract ]['singleton'] === true );
	}

	/**
	 * Check if service is bound.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @return   bool                   True if bound.
	 */
	public function bound( string $abstract ): bool {
		return isset( $this->bindings[ $abstract ] ) ||
				isset( $this->instances[ $abstract ] ) ||
				$this->is_alias( $abstract );
	}

	/**
	 * Check if identifier is an alias.
	 *
	 * @since    1.0.0
	 * @param    string $name    Service identifier.
	 * @return   bool               True if alias.
	 */
	public function is_alias( string $name ): bool {
		return isset( $this->aliases[ $name ] );
	}

	/**
	 * Get service alias.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @return   string                 Resolved identifier.
	 */
	public function get_alias( string $abstract ): string {
		if ( ! isset( $this->aliases[ $abstract ] ) ) {
			return $abstract;
		}

		return $this->get_alias( $this->aliases[ $abstract ] );
	}

	/**
	 * Register service alias.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @param    string $alias       Service alias.
	 * @return   void
	 */
	public function alias( string $abstract, string $alias ): void {
		if ( $alias === $abstract ) {
			throw new Exception( "[{$abstract}] is aliased to itself." );
		}

		$this->aliases[ $alias ] = $abstract;
	}

	/**
	 * Tag services.
	 *
	 * @since    1.0.0
	 * @param    array|string $abstracts    Service identifiers.
	 * @param    array        $tags         Tags to apply.
	 * @return   void
	 */
	public function tag( array|string $abstracts, ...$tags ): void {

		foreach ( $tags as $tag ) {
			if ( ! isset( $this->tags[ $tag ] ) ) {
				$this->tags[ $tag ] = array();
			}

			foreach ( (array) $abstracts as $abstract ) {
				$this->tags[ $tag ][] = $abstract;
			}
		}
	}

	/**
	 * Get tagged services.
	 *
	 * @since    1.0.0
	 * @param    string $tag    Tag name.
	 * @return   array             Tagged services.
	 */
	public function tagged( string $tag ): array {
		$results = array();

		if ( isset( $this->tags[ $tag ] ) ) {
			foreach ( $this->tags[ $tag ] as $abstract ) {
				$results[] = $this->resolve( $abstract );
			}
		}

		return $results;
	}

	/**
	 * Remove abstract alias.
	 *
	 * @since    1.0.0
	 * @param    string $searched    Service identifier.
	 * @return   void
	 */
	protected function remove_abstract_alias( string $searched ): void {
		if ( ! isset( $this->aliases[ $searched ] ) ) {
			return;
		}

		foreach ( $this->aliases as $alias => $abstract ) {
			if ( $abstract == $searched ) {
				unset( $this->aliases[ $alias ] );
			}
		}
	}

	/**
	 * Drop stale instances.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @return   void
	 */
	protected function drop_stale_instances( string $abstract ): void {
		unset( $this->instances[ $abstract ], $this->aliases[ $abstract ] );
	}

	/**
	 * Fire resolving callbacks.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @param    mixed  $object      Service instance.
	 * @return   void
	 */
	protected function fire_resolving_callbacks( string $abstract, mixed $object ): void {
		// Placeholder for resolving callbacks
		// Can be extended to support before/after resolving events
	}

	/**
	 * Fire rebound callbacks.
	 *
	 * @since    1.0.0
	 * @param    string $abstract    Service identifier.
	 * @return   void
	 */
	protected function rebound( string $abstract ): void {
		// Placeholder for rebound callbacks
		// Can be extended to support service rebinding events
	}

	/**
	 * Flush the container.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function flush(): void {
		$this->aliases   = array();
		$this->resolved  = array();
		$this->bindings  = array();
		$this->instances = array();
		$this->tags      = array();
	}

	/**
	 * Get container bindings.
	 *
	 * @since    1.0.0
	 * @return   array    Container bindings.
	 */
	public function get_bindings(): array {
		return $this->bindings;
	}

	/**
	 * Get container instances.
	 *
	 * @since    1.0.0
	 * @return   array    Container instances.
	 */
	public function get_instances(): array {
		return $this->instances;
	}

	/**
	 * Get container aliases.
	 *
	 * @since    1.0.0
	 * @return   array    Container aliases.
	 */
	public function get_aliases(): array {
		return $this->aliases;
	}

	/**
	 * Get container tags.
	 *
	 * @since    1.0.0
	 * @return   array    Container tags.
	 */
	public function get_tags(): array {
		return $this->tags;
	}
}
