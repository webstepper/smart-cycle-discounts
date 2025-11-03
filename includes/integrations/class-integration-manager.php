<?php
/**
 * Integration Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/class-integration-manager.php
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
 * Integration Manager
 *
 * Handles third-party integrations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Integration_Manager {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Registered integrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $integrations    Registered integrations.
	 */
	private array $integrations = array();

	/**
	 * Initialize the integration manager.
	 *
	 * @since    1.0.0
	 * @param    object $container    Container instance.
	 */
	public function __construct( object $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize integrations.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->register_integrations();
		$this->load_integrations();
	}

	/**
	 * Register available integrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_integrations(): void {
		$this->integrations = array(
			'woocommerce' => array(
				'class'  => 'SCD_WooCommerce_Integration',
				'file'   => 'includes/integrations/woocommerce/class-woocommerce-integration.php',
				'active' => class_exists( 'WooCommerce' ),
			),
			'blocks'      => array(
				'class'  => 'SCD_Blocks_Manager',
				'file'   => 'includes/integrations/blocks/class-blocks-manager.php',
				'active' => function_exists( 'register_block_type' ),
			),
			'email'       => array(
				'class'  => 'SCD_Email_Manager',
				'file'   => 'includes/integrations/email/class-email-manager.php',
				'active' => true,
			),
		);
	}

	/**
	 * Load active integrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_integrations(): void {
		foreach ( $this->integrations as $key => $integration ) {
			if ( $integration['active'] ) {
				$this->load_integration( $key, $integration );
			}
		}
	}

	/**
	 * Load a specific integration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $key           Integration key.
	 * @param    array  $integration   Integration config.
	 * @return   void
	 */
	private function load_integration( string $key, array $integration ): void {
		$file_path = SCD_INCLUDES_DIR . 'integrations/' . $integration['file'];

		if ( file_exists( $file_path ) ) {
			require_once $file_path;

			if ( class_exists( $integration['class'] ) ) {
				$instance = $this->create_integration_instance( $integration['class'] );

				if ( $instance && method_exists( $instance, 'init' ) ) {
					$instance->init();
				}

				if ( $instance ) {
					$this->integrations[ $key ]['instance'] = $instance;
				}
			}
		}
	}

	/**
	 * Create integration instance with proper dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $class    Integration class name.
	 * @return   object|null         Integration instance or null.
	 */
	private function create_integration_instance( string $class ): ?object {
		try {
			switch ( $class ) {
				case 'SCD_Blocks_Manager':
					return new SCD_Blocks_Manager(
						$this->container->get( 'logger' ),
						$this->container->get( 'admin_asset_manager' ),
						$this->container->get( 'campaign_manager' )
					);

				case 'SCD_Email_Manager':
					// Reuse email_manager from container if available
					if ( $this->container->has( 'email_manager' ) ) {
						return $this->container->get( 'email_manager' );
					}
					// Fallback to creating new instance
					return new SCD_Email_Manager(
						$this->container->get( 'logger' ),
						$this->container->get( 'campaign_manager' ),
						$this->container->get( 'action_scheduler' )
					);

				case 'SCD_WooCommerce_Integration':
					// WooCommerce integration accepts container
					return new SCD_WooCommerce_Integration( $this->container );

				default:
					// Default behavior: pass container
					return new $class( $this->container );
			}
		} catch ( Exception $e ) {
			if ( method_exists( $this->container->get( 'logger' ), 'error' ) ) {
				$this->container->get( 'logger' )->error(
					'Failed to create integration instance',
					array(
						'class' => $class,
						'error' => $e->getMessage(),
					)
				);
			}
			return null;
		}
	}

	/**
	 * Get integration instance.
	 *
	 * @since    1.0.0
	 * @param    string $key    Integration key.
	 * @return   object|null       Integration instance or null.
	 */
	public function get_integration( string $key ): ?object {
		return $this->integrations[ $key ]['instance'] ?? null;
	}

	/**
	 * Check if integration is active.
	 *
	 * @since    1.0.0
	 * @param    string $key    Integration key.
	 * @return   bool              True if active.
	 */
	public function is_integration_active( string $key ): bool {
		return isset( $this->integrations[ $key ] ) && $this->integrations[ $key ]['active'];
	}
}
