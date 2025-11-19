<?php
/**
 * Wc Admin Integration Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-admin-integration.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Admin Integration class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */
class SCD_WC_Admin_Integration {

	/**
	 * Logger.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger
	 */
	private ?object $logger;

	/**
	 * Initialize admin integration.
	 *
	 * @since    1.0.0
	 * @param    object|null $logger    Logger.
	 */
	public function __construct( ?object $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_product_discount_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_discount_fields' ) );
	}

	/**
	 * Add product discount fields to admin.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_product_discount_fields(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		$exclude_from_discounts = get_post_meta( $post->ID, '_scd_exclude_from_discounts', true );

		woocommerce_wp_checkbox(
			array(
				'id'          => '_scd_exclude_from_discounts',
				'label'       => __( 'Exclude from Smart Cycle Discounts', 'smart-cycle-discounts' ),
				'description' => __( 'Check this to exclude this product from all Smart Cycle Discount campaigns.', 'smart-cycle-discounts' ),
				'value'       => $exclude_from_discounts,
			)
		);
	}

	/**
	 * Save product discount fields.
	 *
	 * @since    1.0.0
	 * @param    int $post_id    Product ID.
	 * @return   void
	 */
	public function save_product_discount_fields( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		$exclude_from_discounts = isset( $_POST['_scd_exclude_from_discounts'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_scd_exclude_from_discounts', $exclude_from_discounts );

		$this->log(
			'debug',
			'Product discount fields saved',
			array(
				'post_id'                => $post_id,
				'exclude_from_discounts' => $exclude_from_discounts,
			)
		);
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Admin] ' . $message, $context );
		}
	}
}
