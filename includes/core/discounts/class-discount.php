<?php
/**
 * Discount Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/class-discount.php
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
 * Discount Model
 *
 * Represents a discount data model with validation and manipulation methods.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/models
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Discount {

	/**
	 * Note: Validation constants are now centralized in SCD_Validation class
	 * Use SCD_Validation_Rules::PERCENTAGE_MIN, SCD_Validation_Rules::PERCENTAGE_MAX, etc.
	 *
	 * @since    1.0.0
	 */

	/**
	 * Discount ID.
	 *
	 * @since    1.0.0
	 * @var      int|null    $id    Discount ID.
	 */
	private ?int $id = null;

	/**
	 * Campaign ID.
	 *
	 * @since    1.0.0
	 * @var      int    $campaign_id    Campaign ID.
	 */
	private int $campaign_id;

	/**
	 * Product ID.
	 *
	 * @since    1.0.0
	 * @var      int    $product_id    Product ID.
	 */
	private int $product_id;

	/**
	 * Discount type.
	 *
	 * @since    1.0.0
	 * @var      string    $discount_type    Discount type.
	 */
	private string $discount_type;

	/**
	 * Discount value.
	 *
	 * @since    1.0.0
	 * @var      float    $discount_value    Discount value.
	 */
	private float $discount_value;

	/**
	 * Original price.
	 *
	 * @since    1.0.0
	 * @var      float    $original_price    Original price.
	 */
	private float $original_price;

	/**
	 * Discounted price.
	 *
	 * @since    1.0.0
	 * @var      float    $discounted_price    Discounted price.
	 */
	private float $discounted_price;

	/**
	 * Discount status.
	 *
	 * @since    1.0.0
	 * @var      string    $status    Discount status.
	 */
	private string $status;

	/**
	 * Start date.
	 *
	 * @since    1.0.0
	 * @var      string|null    $start_date    Start date.
	 */
	private ?string $start_date = null;

	/**
	 * End date.
	 *
	 * @since    1.0.0
	 * @var      string|null    $end_date    End date.
	 */
	private ?string $end_date = null;

	/**
	 * Discount metadata.
	 *
	 * @since    1.0.0
	 * @var      array    $metadata    Discount metadata.
	 */
	private array $metadata = array();

	/**
	 * Created date.
	 *
	 * @since    1.0.0
	 * @var      string|null    $created_at    Created date.
	 */
	private ?string $created_at = null;

	/**
	 * Updated date.
	 *
	 * @since    1.0.0
	 * @var      string|null    $updated_at    Updated date.
	 */
	private ?string $updated_at = null;

	/**
	 * Initialize the discount model.
	 *
	 * @since    1.0.0
	 * @param    array $data    Discount data.
	 */
	public function __construct( array $data = array() ) {
		$this->fill( $data );
	}

	/**
	 * Fill the model with data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Data to fill.
	 * @return   void
	 */
	public function fill( array $data ): void {
		if ( isset( $data['id'] ) ) {
			$this->id = intval( $data['id'] );
		}

		if ( isset( $data['campaign_id'] ) ) {
			$this->campaign_id = intval( $data['campaign_id'] );
		}

		if ( isset( $data['product_id'] ) ) {
			$this->product_id = intval( $data['product_id'] );
		}

		if ( isset( $data['discount_type'] ) ) {
			$this->discount_type = sanitize_text_field( $data['discount_type'] );
		}

		if ( isset( $data['discount_value'] ) ) {
			$this->discount_value = floatval( $data['discount_value'] );
		}

		if ( isset( $data['original_price'] ) ) {
			$this->original_price = floatval( $data['original_price'] );
		}

		if ( isset( $data['discounted_price'] ) ) {
			$this->discounted_price = floatval( $data['discounted_price'] );
		}

		if ( isset( $data['status'] ) ) {
			$this->status = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['start_date'] ) ) {
			$this->start_date = sanitize_text_field( $data['start_date'] );
		}

		if ( isset( $data['end_date'] ) ) {
			$this->end_date = sanitize_text_field( $data['end_date'] );
		}

		if ( isset( $data['metadata'] ) ) {
			$this->metadata = is_array( $data['metadata'] ) ? $data['metadata'] : array();
		}

		if ( isset( $data['created_at'] ) ) {
			$this->created_at = sanitize_text_field( $data['created_at'] );
		}

		if ( isset( $data['updated_at'] ) ) {
			$this->updated_at = sanitize_text_field( $data['updated_at'] );
		}
	}

	/**
	 * Validate the discount data.
	 *
	 * @since    1.0.0
	 * @return   array    Validation result.
	 */
	public function validate(): array {
		$data = $this->to_array();

		// Use centralized validation
		$result = SCD_Validation::validate( $data, 'wizard_discounts' );

		// If WP_Error, convert to array of error messages
		if ( is_wp_error( $result ) ) {
			$errors = array();
			foreach ( $result->get_error_messages() as $message ) {
				$errors[] = $message;
			}
			return $errors;
		}

		return array(); // No errors
	}

	/**
	 * Check if the discount is valid.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid.
	 */
	public function is_valid(): bool {
		return empty( $this->validate() );
	}

	/**
	 * Convert to array.
	 *
	 * @since    1.0.0
	 * @return   array    Discount data as array.
	 */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'campaign_id'      => $this->campaign_id ?? 0,
			'product_id'       => $this->product_id ?? 0,
			'discount_type'    => $this->discount_type ?? '',
			'discount_value'   => $this->discount_value ?? 0.0,
			'original_price'   => $this->original_price ?? 0.0,
			'discounted_price' => $this->discounted_price ?? 0.0,
			'status'           => $this->status ?? 'inactive',
			'start_date'       => $this->start_date,
			'end_date'         => $this->end_date,
			'metadata'         => $this->metadata,
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
		);
	}

	/**
	 * Convert to JSON.
	 *
	 * @since    1.0.0
	 * @return   string    Discount data as JSON.
	 */
	public function to_json(): string {
		return wp_json_encode( $this->to_array() );
	}

	/**
	 * Get discount savings amount.
	 *
	 * @since    1.0.0
	 * @return   float    Savings amount.
	 */
	public function get_savings_amount(): float {
		if ( ! isset( $this->original_price ) || ! isset( $this->discounted_price ) ) {
			return 0.0;
		}

		return max( 0, $this->original_price - $this->discounted_price );
	}

	/**
	 * Get discount savings percentage.
	 *
	 * @since    1.0.0
	 * @return   float    Savings percentage.
	 */
	public function get_savings_percentage(): float {
		if ( ! isset( $this->original_price ) || $this->original_price <= 0 ) {
			return 0.0;
		}

		$savings = $this->get_savings_amount();
		return ( $savings / $this->original_price ) * 100;
	}

	/**
	 * Check if discount is currently active.
	 *
	 * @since    1.0.0
	 * @return   bool    True if active.
	 */
	public function is_active(): bool {
		if ( $this->status !== 'active' ) {
			return false;
		}

		$current_time = current_time( 'timestamp' );

		if ( ! empty( $this->start_date ) ) {
			$start_time = strtotime( $this->start_date );
			if ( $start_time !== false && $current_time < $start_time ) {
				return false;
			}
		}

		if ( ! empty( $this->end_date ) ) {
			$end_time = strtotime( $this->end_date );
			if ( $end_time !== false && $current_time > $end_time ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if discount has expired.
	 *
	 * @since    1.0.0
	 * @return   bool    True if expired.
	 */
	public function is_expired(): bool {
		if ( empty( $this->end_date ) ) {
			return false;
		}

		$end_time = strtotime( $this->end_date );
		if ( $end_time === false ) {
			return false;
		}

		return current_time( 'timestamp' ) > $end_time;
	}

	/**
	 * Get formatted discount description.
	 *
	 * @since    1.0.0
	 * @return   string    Formatted description.
	 */
	public function get_description(): string {
		switch ( $this->discount_type ) {
			case 'percentage':
				return sprintf(
					__( '%s%% off', 'smart-cycle-discounts' ),
					number_format( $this->discount_value, 1 )
				);

			case 'fixed':
				return sprintf(
					__( '%s off', 'smart-cycle-discounts' ),
					wc_price( $this->discount_value )
				);

			case 'bogo':
				return __( 'Buy-One-Get-One offer', 'smart-cycle-discounts' );

			case 'tiered':
				return __( 'Tiered pricing discount', 'smart-cycle-discounts' );

			default:
				return __( 'Discount applied', 'smart-cycle-discounts' );
		}
	}

	// Getters and Setters

	/**
	 * Get ID.
	 *
	 * @since    1.0.0
	 * @return   int|null    Discount ID.
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	/**
	 * Set ID.
	 *
	 * @since    1.0.0
	 * @param    int $id    Discount ID.
	 * @return   void
	 */
	public function set_id( int $id ): void {
		$this->id = $id;
	}

	/**
	 * Get campaign ID.
	 *
	 * @since    1.0.0
	 * @return   int    Campaign ID.
	 */
	public function get_campaign_id(): int {
		return $this->campaign_id ?? 0;
	}

	/**
	 * Set campaign ID.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function set_campaign_id( int $campaign_id ): void {
		$this->campaign_id = $campaign_id;
	}

	/**
	 * Get product ID.
	 *
	 * @since    1.0.0
	 * @return   int    Product ID.
	 */
	public function get_product_id(): int {
		return $this->product_id ?? 0;
	}

	/**
	 * Set product ID.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   void
	 */
	public function set_product_id( int $product_id ): void {
		$this->product_id = $product_id;
	}

	/**
	 * Get discount type.
	 *
	 * @since    1.0.0
	 * @return   string    Discount type.
	 */
	public function get_discount_type(): string {
		return $this->discount_type ?? '';
	}

	/**
	 * Set discount type.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type.
	 * @return   void
	 */
	public function set_discount_type( string $discount_type ): void {
		$this->discount_type = $discount_type;
	}

	/**
	 * Get discount value.
	 *
	 * @since    1.0.0
	 * @return   float    Discount value.
	 */
	public function get_discount_value(): float {
		return $this->discount_value ?? 0.0;
	}

	/**
	 * Set discount value.
	 *
	 * @since    1.0.0
	 * @param    float $discount_value    Discount value.
	 * @return   void
	 */
	public function set_discount_value( float $discount_value ): void {
		$this->discount_value = $discount_value;
	}

	/**
	 * Get original price.
	 *
	 * @since    1.0.0
	 * @return   float    Original price.
	 */
	public function get_original_price(): float {
		return $this->original_price ?? 0.0;
	}

	/**
	 * Set original price.
	 *
	 * @since    1.0.0
	 * @param    float $original_price    Original price.
	 * @return   void
	 */
	public function set_original_price( float $original_price ): void {
		$this->original_price = $original_price;
	}

	/**
	 * Get discounted price.
	 *
	 * @since    1.0.0
	 * @return   float    Discounted price.
	 */
	public function get_discounted_price(): float {
		return $this->discounted_price ?? 0.0;
	}

	/**
	 * Set discounted price.
	 *
	 * @since    1.0.0
	 * @param    float $discounted_price    Discounted price.
	 * @return   void
	 */
	public function set_discounted_price( float $discounted_price ): void {
		$this->discounted_price = $discounted_price;
	}

	/**
	 * Get status.
	 *
	 * @since    1.0.0
	 * @return   string    Status.
	 */
	public function get_status(): string {
		return $this->status ?? 'inactive';
	}

	/**
	 * Set status.
	 *
	 * @since    1.0.0
	 * @param    string $status    Status.
	 * @return   void
	 */
	public function set_status( string $status ): void {
		$this->status = $status;
	}

	/**
	 * Get start date.
	 *
	 * @since    1.0.0
	 * @return   string|null    Start date.
	 */
	public function get_start_date(): ?string {
		return $this->start_date;
	}

	/**
	 * Set start date.
	 *
	 * @since    1.0.0
	 * @param    string|null $start_date    Start date.
	 * @return   void
	 */
	public function set_start_date( ?string $start_date ): void {
		$this->start_date = $start_date;
	}

	/**
	 * Get end date.
	 *
	 * @since    1.0.0
	 * @return   string|null    End date.
	 */
	public function get_end_date(): ?string {
		return $this->end_date;
	}

	/**
	 * Set end date.
	 *
	 * @since    1.0.0
	 * @param    string|null $end_date    End date.
	 * @return   void
	 */
	public function set_end_date( ?string $end_date ): void {
		$this->end_date = $end_date;
	}

	/**
	 * Get metadata.
	 *
	 * @since    1.0.0
	 * @return   array    Metadata.
	 */
	public function get_metadata(): array {
		return $this->metadata;
	}

	/**
	 * Set metadata.
	 *
	 * @since    1.0.0
	 * @param    array $metadata    Metadata.
	 * @return   void
	 */
	public function set_metadata( array $metadata ): void {
		$this->metadata = $metadata;
	}

	/**
	 * Get metadata value.
	 *
	 * @since    1.0.0
	 * @param    string $key        Metadata key.
	 * @param    mixed  $default    Default value.
	 * @return   mixed                 Metadata value.
	 */
	public function get_metadata_value( string $key, mixed $default = null ): mixed {
		return $this->metadata[ $key ] ?? $default;
	}

	/**
	 * Set metadata value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Metadata key.
	 * @param    mixed  $value    Metadata value.
	 * @return   void
	 */
	public function set_metadata_value( string $key, mixed $value ): void {
		$this->metadata[ $key ] = $value;
	}

	/**
	 * Get created date.
	 *
	 * @since    1.0.0
	 * @return   string|null    Created date.
	 */
	public function get_created_at(): ?string {
		return $this->created_at;
	}

	/**
	 * Get updated date.
	 *
	 * @since    1.0.0
	 * @return   string|null    Updated date.
	 */
	public function get_updated_at(): ?string {
		return $this->updated_at;
	}

	/**
	 * Create discount from SCD_Discount_Result.
	 *
	 * @since    1.0.0
	 * @param    SCD_Discount_Result $result        Discount result.
	 * @param    int                 $campaign_id   Campaign ID.
	 * @param    int                 $product_id    Product ID.
	 * @return   SCD_Discount                          Discount instance.
	 */
	public static function from_discount_result( SCD_Discount_Result $result, int $campaign_id, int $product_id ): self {
		$discount = new self();

		$discount->set_campaign_id( $campaign_id );
		$discount->set_product_id( $product_id );
		$discount->set_discount_type( $result->get_strategy_id() );
		$discount->set_original_price( $result->get_original_price() );
		$discount->set_discounted_price( $result->get_discounted_price() );
		$discount->set_discount_value( $result->get_discount_amount() );
		$discount->set_status( $result->is_applied() ? 'active' : 'inactive' );
		$discount->set_metadata( $result->get_metadata() );

		return $discount;
	}
}
