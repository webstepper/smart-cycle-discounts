<?php
/**
 * Analytics Data Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-analytics-data.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Analytics Data Model
 *
 * Represents analytics data with validation and manipulation methods.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/models
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Analytics_Data {

	/**
	 * Analytics ID.
	 *
	 * @since    1.0.0
	 * @var      int|null    $id    Analytics ID.
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
	 * Event type.
	 *
	 * @since    1.0.0
	 * @var      string    $event_type    Event type.
	 */
	private string $event_type;

	/**
	 * Event data.
	 *
	 * @since    1.0.0
	 * @var      array    $event_data    Event data.
	 */
	private array $event_data = array();

	/**
	 * Product ID (optional).
	 *
	 * @since    1.0.0
	 * @var      int|null    $product_id    Product ID.
	 */
	private ?int $product_id = null;

	/**
	 * User ID (optional).
	 *
	 * @since    1.0.0
	 * @var      int|null    $user_id    User ID.
	 */
	private ?int $user_id = null;

	/**
	 * Session ID.
	 *
	 * @since    1.0.0
	 * @var      string|null    $session_id    Session ID.
	 */
	private ?string $session_id = null;

	/**
	 * IP address.
	 *
	 * @since    1.0.0
	 * @var      string|null    $ip_address    IP address.
	 */
	private ?string $ip_address = null;

	/**
	 * User agent.
	 *
	 * @since    1.0.0
	 * @var      string|null    $user_agent    User agent.
	 */
	private ?string $user_agent = null;

	/**
	 * Referrer URL.
	 *
	 * @since    1.0.0
	 * @var      string|null    $referrer    Referrer URL.
	 */
	private ?string $referrer = null;

	/**
	 * Event timestamp.
	 *
	 * @since    1.0.0
	 * @var      string|null    $event_timestamp    Event timestamp.
	 */
	private ?string $event_timestamp = null;

	/**
	 * Created date.
	 *
	 * @since    1.0.0
	 * @var      string|null    $created_at    Created date.
	 */
	private ?string $created_at = null;

	/**
	 * Valid event types.
	 *
	 * @since    1.0.0
	 * @var      array    $valid_event_types    Valid event types.
	 */
	private static array $valid_event_types = array(
		'campaign_view',
		'product_view',
		'discount_applied',
		'discount_removed',
		'cart_add',
		'cart_remove',
		'checkout_start',
		'checkout_complete',
		'purchase',
		'conversion',
		'click',
		'impression',
	);

	/**
	 * Initialize the analytics data model.
	 *
	 * @since    1.0.0
	 * @param    array $data    Analytics data.
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

		if ( isset( $data['event_type'] ) ) {
			$this->event_type = sanitize_text_field( $data['event_type'] );
		}

		if ( isset( $data['event_data'] ) ) {
			$this->event_data = is_array( $data['event_data'] ) ? $data['event_data'] : array();
		}

		if ( isset( $data['product_id'] ) ) {
			$this->product_id = intval( $data['product_id'] );
		}

		if ( isset( $data['user_id'] ) ) {
			$this->user_id = intval( $data['user_id'] );
		}

		if ( isset( $data['session_id'] ) ) {
			$this->session_id = sanitize_text_field( $data['session_id'] );
		}

		if ( isset( $data['ip_address'] ) ) {
			$this->ip_address = sanitize_text_field( $data['ip_address'] );
		}

		if ( isset( $data['user_agent'] ) ) {
			$this->user_agent = sanitize_text_field( $data['user_agent'] );
		}

		if ( isset( $data['referrer'] ) ) {
			$this->referrer = esc_url_raw( $data['referrer'] );
		}

		if ( isset( $data['event_timestamp'] ) ) {
			$this->event_timestamp = sanitize_text_field( $data['event_timestamp'] );
		}

		if ( isset( $data['created_at'] ) ) {
			$this->created_at = sanitize_text_field( $data['created_at'] );
		}
	}

	/**
	 * Validate the analytics data.
	 *
	 * @since    1.0.0
	 * @return   array    Validation result.
	 */
	public function validate(): array {
		$errors = array();

		if ( ! isset( $this->campaign_id ) || $this->campaign_id <= 0 ) {
			$errors[] = __( 'Campaign ID is required and must be positive', 'smart-cycle-discounts' );
		}

		if ( ! isset( $this->event_type ) || empty( $this->event_type ) ) {
			$errors[] = __( 'Event type is required', 'smart-cycle-discounts' );
		} elseif ( ! in_array( $this->event_type, self::$valid_event_types ) ) {
			$errors[] = __( 'Invalid event type', 'smart-cycle-discounts' );
		}

		if ( isset( $this->product_id ) && $this->product_id <= 0 ) {
			$errors[] = __( 'Product ID must be positive if provided', 'smart-cycle-discounts' );
		}

		if ( isset( $this->user_id ) && $this->user_id <= 0 ) {
			$errors[] = __( 'User ID must be positive if provided', 'smart-cycle-discounts' );
		}

		// Validate IP address format if provided
		if ( ! empty( $this->ip_address ) && ! filter_var( $this->ip_address, FILTER_VALIDATE_IP ) ) {
			$errors[] = __( 'Invalid IP address format', 'smart-cycle-discounts' );
		}

		if ( ! empty( $this->event_timestamp ) && strtotime( $this->event_timestamp ) === false ) {
			$errors[] = __( 'Invalid event timestamp format', 'smart-cycle-discounts' );
		}

		return $errors;
	}

	/**
	 * Check if the analytics data is valid.
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
	 * @return   array    Analytics data as array.
	 */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'campaign_id'     => $this->campaign_id ?? 0,
			'event_type'      => $this->event_type ?? '',
			'event_data'      => $this->event_data,
			'product_id'      => $this->product_id,
			'user_id'         => $this->user_id,
			'session_id'      => $this->session_id,
			'ip_address'      => $this->ip_address,
			'user_agent'      => $this->user_agent,
			'referrer'        => $this->referrer,
			'event_timestamp' => $this->event_timestamp,
			'created_at'      => $this->created_at,
		);
	}

	/**
	 * Convert to JSON.
	 *
	 * @since    1.0.0
	 * @return   string    Analytics data as JSON.
	 */
	public function to_json(): string {
		return wp_json_encode( $this->to_array() );
	}

	/**
	 * Get event value from event data.
	 *
	 * @since    1.0.0
	 * @param    string $key        Event data key.
	 * @param    mixed  $default    Default value.
	 * @return   mixed                 Event data value.
	 */
	public function get_event_value( string $key, mixed $default = null ): mixed {
		return $this->event_data[ $key ] ?? $default;
	}

	/**
	 * Set event value in event data.
	 *
	 * @since    1.0.0
	 * @param    string $key      Event data key.
	 * @param    mixed  $value    Event data value.
	 * @return   void
	 */
	public function set_event_value( string $key, mixed $value ): void {
		$this->event_data[ $key ] = $value;
	}

	/**
	 * Get revenue value from event data.
	 *
	 * @since    1.0.0
	 * @return   float    Revenue value.
	 */
	public function get_revenue(): float {
		return floatval( $this->get_event_value( 'revenue', 0 ) );
	}

	/**
	 * Set revenue value in event data.
	 *
	 * @since    1.0.0
	 * @param    float $revenue    Revenue value.
	 * @return   void
	 */
	public function set_revenue( float $revenue ): void {
		$this->set_event_value( 'revenue', $revenue );
	}

	/**
	 * Get discount amount from event data.
	 *
	 * @since    1.0.0
	 * @return   float    Discount amount.
	 */
	public function get_discount_amount(): float {
		return floatval( $this->get_event_value( 'discount_amount', 0 ) );
	}

	/**
	 * Set discount amount in event data.
	 *
	 * @since    1.0.0
	 * @param    float $amount    Discount amount.
	 * @return   void
	 */
	public function set_discount_amount( float $amount ): void {
		$this->set_event_value( 'discount_amount', $amount );
	}

	/**
	 * Get quantity from event data.
	 *
	 * @since    1.0.0
	 * @return   int    Quantity.
	 */
	public function get_quantity(): int {
		return intval( $this->get_event_value( 'quantity', 1 ) );
	}

	/**
	 * Set quantity in event data.
	 *
	 * @since    1.0.0
	 * @param    int $quantity    Quantity.
	 * @return   void
	 */
	public function set_quantity( int $quantity ): void {
		$this->set_event_value( 'quantity', $quantity );
	}

	/**
	 * Check if event is a conversion event.
	 *
	 * @since    1.0.0
	 * @return   bool    True if conversion event.
	 */
	public function is_conversion_event(): bool {
		return in_array( $this->event_type, array( 'purchase', 'conversion', 'checkout_complete' ) );
	}

	/**
	 * Check if event is a revenue event.
	 *
	 * @since    1.0.0
	 * @return   bool    True if revenue event.
	 */
	public function is_revenue_event(): bool {
		return $this->is_conversion_event() && $this->get_revenue() > 0;
	}

	/**
	 * Get event description.
	 *
	 * @since    1.0.0
	 * @return   string    Event description.
	 */
	public function get_event_description(): string {
		switch ( $this->event_type ) {
			case 'campaign_view':
				return __( 'Campaign viewed', 'smart-cycle-discounts' );
			case 'product_view':
				return __( 'Product viewed', 'smart-cycle-discounts' );
			case 'discount_applied':
				return __( 'Discount applied', 'smart-cycle-discounts' );
			case 'discount_removed':
				return __( 'Discount removed', 'smart-cycle-discounts' );
			case 'cart_add':
				return __( 'Added to cart', 'smart-cycle-discounts' );
			case 'cart_remove':
				return __( 'Removed from cart', 'smart-cycle-discounts' );
			case 'checkout_start':
				return __( 'Checkout started', 'smart-cycle-discounts' );
			case 'checkout_complete':
				return __( 'Checkout completed', 'smart-cycle-discounts' );
			case 'purchase':
				return __( 'Purchase made', 'smart-cycle-discounts' );
			case 'conversion':
				return __( 'Conversion tracked', 'smart-cycle-discounts' );
			case 'click':
				return __( 'Click tracked', 'smart-cycle-discounts' );
			case 'impression':
				return __( 'Impression tracked', 'smart-cycle-discounts' );
			default:
				return __( 'Event tracked', 'smart-cycle-discounts' );
		}
	}

	// Getters and Setters

	/**
	 * Get ID.
	 *
	 * @since    1.0.0
	 * @return   int|null    Analytics ID.
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	/**
	 * Set ID.
	 *
	 * @since    1.0.0
	 * @param    int $id    Analytics ID.
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
	 * Get event type.
	 *
	 * @since    1.0.0
	 * @return   string    Event type.
	 */
	public function get_event_type(): string {
		return $this->event_type ?? '';
	}

	/**
	 * Set event type.
	 *
	 * @since    1.0.0
	 * @param    string $event_type    Event type.
	 * @return   void
	 */
	public function set_event_type( string $event_type ): void {
		$this->event_type = $event_type;
	}

	/**
	 * Get event data.
	 *
	 * @since    1.0.0
	 * @return   array    Event data.
	 */
	public function get_event_data(): array {
		return $this->event_data;
	}

	/**
	 * Set event data.
	 *
	 * @since    1.0.0
	 * @param    array $event_data    Event data.
	 * @return   void
	 */
	public function set_event_data( array $event_data ): void {
		$this->event_data = $event_data;
	}

	/**
	 * Get product ID.
	 *
	 * @since    1.0.0
	 * @return   int|null    Product ID.
	 */
	public function get_product_id(): ?int {
		return $this->product_id;
	}

	/**
	 * Set product ID.
	 *
	 * @since    1.0.0
	 * @param    int|null $product_id    Product ID.
	 * @return   void
	 */
	public function set_product_id( ?int $product_id ): void {
		$this->product_id = $product_id;
	}

	/**
	 * Get user ID.
	 *
	 * @since    1.0.0
	 * @return   int|null    User ID.
	 */
	public function get_user_id(): ?int {
		return $this->user_id;
	}

	/**
	 * Set user ID.
	 *
	 * @since    1.0.0
	 * @param    int|null $user_id    User ID.
	 * @return   void
	 */
	public function set_user_id( ?int $user_id ): void {
		$this->user_id = $user_id;
	}

	/**
	 * Get session ID.
	 *
	 * @since    1.0.0
	 * @return   string|null    Session ID.
	 */
	public function get_session_id(): ?string {
		return $this->session_id;
	}

	/**
	 * Set session ID.
	 *
	 * @since    1.0.0
	 * @param    string|null $session_id    Session ID.
	 * @return   void
	 */
	public function set_session_id( ?string $session_id ): void {
		$this->session_id = $session_id;
	}

	/**
	 * Get IP address.
	 *
	 * @since    1.0.0
	 * @return   string|null    IP address.
	 */
	public function get_ip_address(): ?string {
		return $this->ip_address;
	}

	/**
	 * Set IP address.
	 *
	 * @since    1.0.0
	 * @param    string|null $ip_address    IP address.
	 * @return   void
	 */
	public function set_ip_address( ?string $ip_address ): void {
		$this->ip_address = $ip_address;
	}

	/**
	 * Get user agent.
	 *
	 * @since    1.0.0
	 * @return   string|null    User agent.
	 */
	public function get_user_agent(): ?string {
		return $this->user_agent;
	}

	/**
	 * Set user agent.
	 *
	 * @since    1.0.0
	 * @param    string|null $user_agent    User agent.
	 * @return   void
	 */
	public function set_user_agent( ?string $user_agent ): void {
		$this->user_agent = $user_agent;
	}

	/**
	 * Get referrer.
	 *
	 * @since    1.0.0
	 * @return   string|null    Referrer URL.
	 */
	public function get_referrer(): ?string {
		return $this->referrer;
	}

	/**
	 * Set referrer.
	 *
	 * @since    1.0.0
	 * @param    string|null $referrer    Referrer URL.
	 * @return   void
	 */
	public function set_referrer( ?string $referrer ): void {
		$this->referrer = $referrer;
	}

	/**
	 * Get event timestamp.
	 *
	 * @since    1.0.0
	 * @return   string|null    Event timestamp.
	 */
	public function get_event_timestamp(): ?string {
		return $this->event_timestamp;
	}

	/**
	 * Set event timestamp.
	 *
	 * @since    1.0.0
	 * @param    string|null $event_timestamp    Event timestamp.
	 * @return   void
	 */
	public function set_event_timestamp( ?string $event_timestamp ): void {
		$this->event_timestamp = $event_timestamp;
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
	 * Get valid event types.
	 *
	 * @since    1.0.0
	 * @return   array    Valid event types.
	 */
	public static function get_valid_event_types(): array {
		return self::$valid_event_types;
	}

	/**
	 * Create analytics data from event.
	 *
	 * @since    1.0.0
	 * @param    string $event_type     Event type.
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    array  $event_data     Event data.
	 * @param    array  $context        Additional context.
	 * @return   SCD_Analytics_Data         Analytics data instance.
	 */
	public static function create_from_event( string $event_type, int $campaign_id, array $event_data = array(), array $context = array() ): self {
		$analytics = new self();

		$analytics->set_event_type( $event_type );
		$analytics->set_campaign_id( $campaign_id );
		$analytics->set_event_data( $event_data );
		$analytics->set_event_timestamp( current_time( 'mysql' ) );

		if ( isset( $context['product_id'] ) ) {
			$analytics->set_product_id( intval( $context['product_id'] ) );
		}

		if ( isset( $context['user_id'] ) ) {
			$analytics->set_user_id( intval( $context['user_id'] ) );
		}

		if ( isset( $context['session_id'] ) ) {
			$analytics->set_session_id( $context['session_id'] );
		}

		if ( isset( $context['ip_address'] ) ) {
			$analytics->set_ip_address( $context['ip_address'] );
		}

		if ( isset( $context['user_agent'] ) ) {
			$analytics->set_user_agent( $context['user_agent'] );
		}

		if ( isset( $context['referrer'] ) ) {
			$analytics->set_referrer( $context['referrer'] );
		}

		return $analytics;
	}
}
