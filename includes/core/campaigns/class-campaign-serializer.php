<?php
/**
 * Campaign Serializer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-serializer.php
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
 * Campaign Serializer Class
 *
 * Handles serialization and deserialization of campaign data for API responses.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/serializers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Serializer {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the serializer.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance.
	 */
	public function __construct( SCD_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Serialize a campaign for API response.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign to serialize.
	 * @param    array        $context     Serialization context.
	 * @return   array                        Serialized campaign data.
	 */
	public function serialize( SCD_Campaign $campaign, array $context = array() ): array {
		try {
			$include_meta           = isset( $context['include_meta'] ) ? $context['include_meta'] : true;
			$include_stats          = isset( $context['include_stats'] ) ? $context['include_stats'] : false;
			$include_products       = isset( $context['include_products'] ) ? $context['include_products'] : false;
			$include_split_datetime = isset( $context['include_split_datetime'] ) ? $context['include_split_datetime'] : false;

			$data = array(
				'id'             => $campaign->get_id(),
				'uuid'           => $campaign->get_uuid(),
				'name'           => $campaign->get_name(),
				'slug'           => $campaign->get_slug(),
				'description'    => $campaign->get_description(),
				'status'         => $campaign->get_status(),
				'priority'       => $campaign->get_priority(),
				'discount_type'  => $campaign->get_discount_type(),
				'discount_value' => $campaign->get_discount_value(),
				'starts_at'      => $campaign->get_starts_at() ? $campaign->get_starts_at()->format( 'c' ) : null,
				'ends_at'        => $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'c' ) : null,
				'timezone'       => $campaign->get_timezone(),
				'created_at'     => $campaign->get_created_at()->format( 'c' ),
				'updated_at'     => $campaign->get_updated_at()->format( 'c' ),
				'_links'         => $this->generate_links( $campaign ),
			);

			// Include separated date/time for UI convenience
			if ( $include_split_datetime ) {
				$data['schedule'] = array(
					'start' => SCD_DateTime_Splitter::for_api(
						$campaign->get_starts_at(),
						$campaign->get_timezone()
					),
					'end'   => SCD_DateTime_Splitter::for_api(
						$campaign->get_ends_at(),
						$campaign->get_timezone()
					),
				);
			}

			// Note: Meta information (is_active, is_scheduled, etc.) requires methods
			// that don't exist in Campaign class yet. Uncomment when implemented.
			if ( $include_meta ) {
				$data['meta'] = array(
					'can_edit'   => $this->can_edit_campaign( $campaign ),
					'can_delete' => $this->can_delete_campaign( $campaign ),
				);
			}

			if ( $include_stats ) {
				$data['stats'] = $this->get_campaign_stats( $campaign );
			}

			if ( $include_products ) {
				$data['products'] = $this->get_campaign_products( $campaign );
			}

			return $data;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign serialization failed',
				array(
					'campaign_id' => $campaign->get_id(),
					'error'       => $e->getMessage(),
				)
			);

			throw new RuntimeException( 'Failed to serialize campaign data' );
		}
	}

	/**
	 * Serialize multiple campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaigns    Array of campaigns.
	 * @param    array $context      Serialization context.
	 * @return   array                  Serialized campaigns data.
	 */
	public function serialize_collection( array $campaigns, array $context = array() ): array {
		$serialized = array();

		foreach ( $campaigns as $campaign ) {
			if ( $campaign instanceof SCD_Campaign ) {
				array_push( $serialized, $this->serialize( $campaign, $context ) );
			}
		}

		return $serialized;
	}

	/**
	 * Deserialize campaign data from API request.
	 *
	 * @since    1.0.0
	 * @param    array $data    Campaign data to deserialize.
	 * @return   array             Validated campaign data.
	 */
	public function deserialize( array $data ): array {
		try {
			$validated = array();

			// Required fields
			if ( isset( $data['name'] ) ) {
				$validated['name'] = sanitize_text_field( $data['name'] );
			}

			if ( isset( $data['discount_type'] ) ) {
				$validated['discount_type'] = sanitize_text_field( $data['discount_type'] );
			}

			if ( isset( $data['discount_value'] ) ) {
				$validated['discount_value'] = (float) $data['discount_value'];
			}

			// Optional fields
			if ( isset( $data['description'] ) ) {
				$validated['description'] = sanitize_textarea_field( $data['description'] );
			}

			if ( isset( $data['status'] ) ) {
				$validated['status'] = sanitize_text_field( $data['status'] );
			}

			if ( isset( $data['type'] ) ) {
				$validated['type'] = sanitize_text_field( $data['type'] );
			}

			if ( isset( $data['priority'] ) ) {
				$validated['priority'] = absint( $data['priority'] );
			}

			if ( isset( $data['discount_settings'] ) && is_array( $data['discount_settings'] ) ) {
				$validated['discount_settings'] = $this->sanitize_discount_settings( $data['discount_settings'] );
			}

			if ( isset( $data['starts_at'] ) ) {
				$validated['starts_at'] = $this->parse_datetime( $data['starts_at'] );
			}

			if ( isset( $data['ends_at'] ) ) {
				$validated['ends_at'] = $this->parse_datetime( $data['ends_at'] );
			}

			if ( isset( $data['product_ids'] ) && is_array( $data['product_ids'] ) ) {
				$validated['product_ids'] = array_map( 'absint', $data['product_ids'] );
			}

			if ( isset( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
				$validated['category_ids'] = array_map(
					function ( $id ) {
						if ( 'all' === $id ) {
							return 'all';
						}
						return (string) absint( $id );
					},
					$data['category_ids']
				);
			}

			return $validated;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign deserialization failed',
				array(
					'data'  => $data,
					'error' => $e->getMessage(),
				)
			);

			throw new InvalidArgumentException( 'Invalid campaign data provided' );
		}
	}

	/**
	 * Validate campaign data for API operations.
	 *
	 * @since    1.0.0
	 * @param    array  $data         Campaign data to validate.
	 * @param    string $operation    Operation type (create, update).
	 * @return   array                  Validation result.
	 */
	public function validate( array $data, string $operation = 'create' ): array {
		$errors = array();

		if ( $operation === 'create' ) {
			if ( empty( $data['name'] ) ) {
				$errors['name'] = __( 'Campaign name is required.', 'smart-cycle-discounts' );
			}

			if ( empty( $data['discount_type'] ) ) {
				$errors['discount_type'] = __( 'Discount type is required.', 'smart-cycle-discounts' );
			}

			if ( ! isset( $data['discount_value'] ) || $data['discount_value'] <= 0 ) {
				$errors['discount_value'] = __( 'Discount value must be greater than 0.', 'smart-cycle-discounts' );
			}
		}

		if ( isset( $data['name'] ) && strlen( $data['name'] ) > SCD_Validation_Rules::CAMPAIGN_NAME_MAX ) {
			$errors['name'] = sprintf(
				__( 'Campaign name cannot exceed %d characters.', 'smart-cycle-discounts' ),
				SCD_Validation_Rules::CAMPAIGN_NAME_MAX
			);
		}

		if ( isset( $data['discount_type'] ) && ! in_array( $data['discount_type'], array( 'percentage', 'fixed', 'bogo' ), true ) ) {
			$errors['discount_type'] = __( 'Invalid discount type.', 'smart-cycle-discounts' );
		}

		if ( isset( $data['discount_value'] ) ) {
			if ( $data['discount_type'] === 'percentage' && $data['discount_value'] > 100 ) {
				$errors['discount_value'] = __( 'Percentage discount cannot exceed 100%.', 'smart-cycle-discounts' );
			}
		}

		if ( isset( $data['status'] ) && ! in_array( $data['status'], array( 'draft', 'active', 'paused', 'expired' ), true ) ) {
			$errors['status'] = __( 'Invalid campaign status.', 'smart-cycle-discounts' );
		}

		if ( isset( $data['priority'] ) && ( $data['priority'] < 1 || $data['priority'] > 5 ) ) {
			$errors['priority'] = __( 'Priority must be between 1 and 5.', 'smart-cycle-discounts' );
		}

		if ( isset( $data['starts_at'] ) && isset( $data['ends_at'] ) ) {
			$starts_at = $this->parse_datetime( $data['starts_at'] );
			$ends_at   = $this->parse_datetime( $data['ends_at'] );

			if ( $starts_at && $ends_at && $starts_at >= $ends_at ) {
				$errors['ends_at'] = __( 'End date must be after start date.', 'smart-cycle-discounts' );
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Generate HATEOAS links for campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   array                        HATEOAS links.
	 */
	private function generate_links( SCD_Campaign $campaign ): array {
		$base_url    = rest_url( 'scd/v1/campaigns' );
		$campaign_id = $campaign->get_id();

		$links = array(
			'self'       => array(
				'href' => "{$base_url}/{$campaign_id}",
			),
			'collection' => array(
				'href' => $base_url,
			),
			'edit'       => array(
				'href'   => "{$base_url}/{$campaign_id}",
				'method' => 'PUT',
			),
			'delete'     => array(
				'href'   => "{$base_url}/{$campaign_id}",
				'method' => 'DELETE',
			),
			'analytics'  => array(
				'href' => rest_url( "scd/v1/analytics/campaigns/{$campaign_id}" ),
			),
		);

		if ( $campaign->get_status() === 'draft' ) {
			$links['activate'] = array(
				'href'   => "{$base_url}/{$campaign_id}/activate",
				'method' => 'POST',
			);
		}

		if ( $campaign->get_status() === 'active' ) {
			$links['pause'] = array(
				'href'   => "{$base_url}/{$campaign_id}/pause",
				'method' => 'POST',
			);
		}

		if ( $campaign->get_status() === 'paused' ) {
			$links['resume'] = array(
				'href'   => "{$base_url}/{$campaign_id}/resume",
				'method' => 'POST',
			);
		}

		return $links;
	}

	/**
	 * Get campaign statistics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   array                        Campaign statistics.
	 */
	private function get_campaign_stats( SCD_Campaign $campaign ): array {
		// This would integrate with the metrics calculator
		// For now, return placeholder data
		return array(
			'views'               => 0,
			'clicks'              => 0,
			'conversions'         => 0,
			'revenue'             => 0.0,
			'click_through_rate'  => 0.0,
			'conversion_rate'     => 0.0,
			'average_order_value' => 0.0,
			'roi'                 => 0.0,
		);
	}

	/**
	 * Get campaign products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   array                        Campaign products.
	 */
	private function get_campaign_products( SCD_Campaign $campaign ): array {
		// For specific products, return the product IDs
		$product_ids = array();
		if ( $campaign->get_product_selection_type() === 'specific_products' ) {
			$product_ids = $campaign->get_product_ids();
		}

		return array(
			'included_products'      => $product_ids,
			'excluded_products'      => array(),
			'included_categories'    => $campaign->get_category_ids(),
			'excluded_categories'    => array(),
			'product_selection_type' => $campaign->get_product_selection_type(),
			'metadata'               => $campaign->get_metadata(),
		);
	}

	/**
	 * Check if user can edit campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   bool                         Can edit.
	 */
	private function can_edit_campaign( SCD_Campaign $campaign ): bool {
		return current_user_can( 'edit_campaigns' ) ||
				( current_user_can( 'edit_own_campaigns' ) && $campaign->get_created_by() === get_current_user_id() );
	}

	/**
	 * Check if user can delete campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign instance.
	 * @return   bool                         Can delete.
	 */
	private function can_delete_campaign( SCD_Campaign $campaign ): bool {
		return current_user_can( 'delete_campaigns' ) ||
				( current_user_can( 'delete_own_campaigns' ) && $campaign->get_created_by() === get_current_user_id() );
	}

	/**
	 * Sanitize discount settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $settings    Discount settings.
	 * @return   array                 Sanitized settings.
	 */
	private function sanitize_discount_settings( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = (float) $value;
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_discount_settings( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Parse datetime string.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $datetime    Datetime string.
	 * @return   DateTime|null          Parsed datetime or null.
	 */
	private function parse_datetime( string $datetime ): ?DateTime {
		try {
			return new DateTime( $datetime, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			$this->logger->warning(
				'Invalid datetime format',
				array(
					'datetime' => $datetime,
					'error'    => $e->getMessage(),
				)
			);
			return null;
		}
	}
}
