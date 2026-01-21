<?php
/**
 * Get Campaign Products AJAX Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Get Campaign Products AJAX Handler
 *
 * Returns HTML for all products in a campaign.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Get_Campaign_Products_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Campaign repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private $campaign_repository;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign_Repository  $campaign_repository    Campaign repository.
	 * @param    WSSCD_Logger|null          $logger                 Logger instance.
	 */
	public function __construct(
		$campaign_repository,
		$logger = null
	) {
		parent::__construct( $logger );
		$this->campaign_repository = $campaign_repository;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_get_campaign_products';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array             Response data.
	 */
	public function handle( array $request ) {
		// Get campaign ID
		$campaign_id = isset( $request['campaign_id'] ) ? absint( $request['campaign_id'] ) : 0;

		if ( empty( $campaign_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid campaign ID', 'smart-cycle-discounts' ),
			);
		}

		// Get campaign
		$campaign = $this->campaign_repository->find_by_id( $campaign_id );

		if ( ! $campaign ) {
			return array(
				'success' => false,
				'message' => __( 'Campaign not found', 'smart-cycle-discounts' ),
			);
		}

		// Get product IDs
		$product_ids = $campaign->get_product_ids();

		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return array(
				'success' => true,
				'data'    => array(
					'html'  => '<p class="wsscd-empty-message">' . esc_html__( 'No products found', 'smart-cycle-discounts' ) . '</p>',
					'count' => 0,
				),
			);
		}

		// Build HTML for all products
		$html = '';
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$image_id = $product->get_image_id();
			$has_image = ! empty( $image_id );

			$price       = $product->get_price();
			$product_url = $product->get_permalink();
			$tag         = ! empty( $product_url ) ? 'a' : 'div';
			$link_attrs  = ! empty( $product_url ) ? ' href="' . esc_url( $product_url ) . '" target="_blank" rel="noopener noreferrer"' : '';

			$html .= '<' . $tag . ' class="wsscd-product-card"' . $link_attrs . '>';

			// Product image or placeholder
			if ( $has_image ) {
				$html .= '<div class="wsscd-product-card-image">';
				$html .= $product->get_image( 'thumbnail' );
				$html .= '</div>';
			} else {
				$html .= '<div class="wsscd-product-card-image no-image">';
				$html .= '<span class="wsscd-product-placeholder">';
				// Use wp_kses with SVG allowed tags since wp_kses_post strips SVG elements.
				$html .= wp_kses( WSSCD_Icon_Helper::get( 'products', array( 'size' => 20 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() );
				$html .= '</span>';
				$html .= '</div>';
			}

			// Product content
			$html .= '<div class="wsscd-product-card-content">';
			$html .= '<div class="wsscd-product-card-name">' . esc_html( $product->get_name() ) . '</div>';
			if ( '' !== $price ) {
				$html .= '<div class="wsscd-product-card-price">' . wp_kses_post( wc_price( $price ) ) . '</div>';
			}
			$html .= '</div>';
			$html .= '</' . $tag . '>';
		}

		return array(
			'success' => true,
			'data'    => array(
				'html'  => $html,
				'count' => count( $product_ids ),
			),
		);
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	protected function get_required_capability() {
		return 'manage_woocommerce';
	}
}
