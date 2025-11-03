<?php
/**
 * Wizard Helpers Trait
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/trait-wizard-helpers.php
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
 * Wizard Helpers Trait
 *
 * @since      1.0.0
 */
trait SCD_Wizard_Helpers {

	/**
	 * Get wizard state service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   SCD_Wizard_State_Service|null    State service instance or null on failure.
	 */
	private function _get_state_service() {
		if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
		}

		try {
			$state_service = new SCD_Wizard_State_Service();
			return $state_service;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get all product IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of product IDs.
	 */
	private function _get_all_product_ids() {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		return get_posts( $args );
	}

	/**
	 * Get products in categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $category_ids    Category IDs.
	 * @return   array                     Array of product IDs.
	 */
	private function _get_products_in_categories( $category_ids ) {
		if ( empty( $category_ids ) || ! is_array( $category_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', $category_ids ),
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Get product IDs from wizard data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products_data    Products data from wizard.
	 * @return   array                      Array of product IDs.
	 */
	private function _get_product_ids( $products_data ) {
		if ( empty( $products_data ) || ! is_array( $products_data ) ) {
			return array();
		}

		// Handle different data formats
		if ( isset( $products_data['product_selection_type'] ) ) {
			$selection_type = $products_data['product_selection_type'];

			if ( 'all_products' === $selection_type ) {
				return $this->_get_all_product_ids();
			}

			if ( 'specific_categories' === $selection_type && ! empty( $products_data['category_ids'] ) ) {
				return $this->_get_products_in_categories( $products_data['category_ids'] );
			}

			if ( 'specific_products' === $selection_type && ! empty( $products_data['product_ids'] ) ) {
				return is_array( $products_data['product_ids'] ) ? array_map( 'intval', $products_data['product_ids'] ) : array();
			}
		}

		// Fallback: assume it's an array of product IDs
		return array_map( 'intval', $products_data );
	}

	/**
	 * Get campaign products based on campaign data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object|array $campaign    Campaign object or array.
	 * @return   array                        Array of product IDs.
	 */
	private function _get_campaign_products( $campaign ) {
		if ( empty( $campaign ) ) {
			return array();
		}

		// Handle campaign object
		if ( is_object( $campaign ) && method_exists( $campaign, 'get_product_ids' ) ) {
			return $campaign->get_product_ids();
		}

		// Handle campaign array
		if ( is_array( $campaign ) ) {
			$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';

			if ( 'all_products' === $selection_type ) {
				return $this->_get_all_product_ids();
			}

			if ( 'specific_categories' === $selection_type && ! empty( $campaign['category_ids'] ) ) {
				return $this->_get_products_in_categories( $campaign['category_ids'] );
			}

			if ( 'specific_products' === $selection_type && ! empty( $campaign['product_ids'] ) ) {
				return is_array( $campaign['product_ids'] ) ? array_map( 'intval', $campaign['product_ids'] ) : array();
			}
		}

		return array();
	}

	/**
	 * Get active campaigns from repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of campaign objects.
	 */
	private function _get_active_campaigns() {
		// Get campaign repository
		if ( ! class_exists( 'Smart_Cycle_Discounts' ) ) {
			return array();
		}

		$campaign_repository = Smart_Cycle_Discounts::get_service( 'campaign_repository' );
		if ( ! $campaign_repository ) {
			return array();
		}

		// Get active campaigns
		$campaigns = $campaign_repository->get_active_campaigns();

		return is_array( $campaigns ) ? $campaigns : array();
	}

	/**
	 * Apply discount to price.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float  $price             Original price.
	 * @param    string $discount_type     Discount type (percentage or fixed).
	 * @param    float  $discount_value    Discount value.
	 * @return   float                        Discounted price.
	 */
	private function _apply_discount( $price, $discount_type, $discount_value ) {
		switch ( $discount_type ) {
			case 'percentage':
				$discount_amount = ( $price * $discount_value ) / 100;
				return max( 0, $price - $discount_amount );

			case 'fixed':
				return max( 0, $price - $discount_value );

			default:
				return $price;
		}
	}

	/**
	 * Get wizard step data with validation.
	 *
	 * Retrieves state service, gets products and discounts data, and validates
	 * that discount configuration exists. Returns error array or data array.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $no_discount_message    Message to show if no discount configured.
	 * @return   array|WP_Error                   Error array or data array with state_service, products_data, discounts_data, product_ids.
	 */
	private function _get_validated_wizard_state( $no_discount_message = '' ) {
		// Get state service
		$state_service = $this->_get_state_service();
		if ( ! $state_service ) {
			return new WP_Error(
				'state_service_unavailable',
				__( 'Could not load wizard data', 'smart-cycle-discounts' )
			);
		}

		// Get step data
		$products_data  = $state_service->get_step_data( 'products' );
		$discounts_data = $state_service->get_step_data( 'discounts' );

		if ( empty( $discounts_data ) || empty( $discounts_data['discount_type'] ) ) {
			// No discount configured yet
			if ( empty( $no_discount_message ) ) {
				$no_discount_message = __( 'Configure discount first', 'smart-cycle-discounts' );
			}
			return new WP_Error( 'no_discount_configured', $no_discount_message );
		}

		// Get product IDs
		$product_ids = $this->_get_product_ids( $products_data );

		return array(
			'state_service'  => $state_service,
			'products_data'  => $products_data,
			'discounts_data' => $discounts_data,
			'product_ids'    => $product_ids,
		);
	}
}
