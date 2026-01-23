<?php
/**
 * Campaign Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign.php
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
 * Campaign Model
 *
 * Pure data model representing a discount campaign entity.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/models
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Campaign {

	/**
	 * Product Selection Model.
	 *
	 * Products step selection flow:
	 * 1. CATEGORY FILTER (first field) - Creates the product pool from selected categories
	 * 2. SELECTION TYPE - Determines HOW to select products FROM the category pool
	 * 3. ADVANCED FILTERS - Further refines the selection
	 *
	 * Selection Types (all select FROM the category pool):
	 * - all_products: All products from the category pool
	 * - specific_products: Manually selected products from the category pool
	 * - random_products: Random subset from the category pool
	 * - smart_selection: Algorithm-selected from the category pool
	 *
	 * @since 1.0.0
	 */
	const SELECTION_TYPE_ALL_PRODUCTS      = 'all_products';
	const SELECTION_TYPE_SPECIFIC_PRODUCTS = 'specific_products';
	const SELECTION_TYPE_RANDOM_PRODUCTS   = 'random_products';
	const SELECTION_TYPE_SMART_SELECTION   = 'smart_selection';

	/**
	 * Pool-based selection types.
	 *
	 * These selection types select from the category pool without explicit product IDs.
	 * specific_products is NOT pool-based as it uses explicit product IDs.
	 *
	 * @since 1.0.0
	 */
	const POOL_BASED_SELECTION_TYPES = array(
		self::SELECTION_TYPE_ALL_PRODUCTS,
		self::SELECTION_TYPE_RANDOM_PRODUCTS,
		self::SELECTION_TYPE_SMART_SELECTION,
	);

	/**
	 * Get all valid selection types.
	 *
	 * @since    1.0.0
	 * @return   array    Valid selection types.
	 */
	public static function get_valid_selection_types(): array {
		return array(
			self::SELECTION_TYPE_ALL_PRODUCTS,
			self::SELECTION_TYPE_SPECIFIC_PRODUCTS,
			self::SELECTION_TYPE_RANDOM_PRODUCTS,
			self::SELECTION_TYPE_SMART_SELECTION,
		);
	}

	/**
	 * Check if selection type is pool-based.
	 *
	 * Pool-based types select from the category pool without explicit product IDs.
	 * specific_products is NOT pool-based as it requires explicit product IDs.
	 *
	 * @since    1.0.0
	 * @param    string $type    Selection type.
	 * @return   bool              True if pool-based.
	 */
	public static function is_pool_based_selection( string $type ): bool {
		return in_array( $type, self::POOL_BASED_SELECTION_TYPES, true );
	}

	/**
	 * Campaign ID.
	 *
	 * @since    1.0.0
	 * @var      int|null    $id    Campaign ID.
	 */
	private ?int $id = null;

	/**
	 * Campaign UUID.
	 *
	 * @since    1.0.0
	 * @var      string    $uuid    Campaign UUID.
	 */
	private string $uuid;

	/**
	 * Campaign name.
	 *
	 * @since    1.0.0
	 * @var      string    $name    Campaign name.
	 */
	private string $name = '';

	/**
	 * Campaign slug.
	 *
	 * @since    1.0.0
	 * @var      string    $slug    Campaign slug.
	 */
	private string $slug = '';

	/**
	 * Campaign description.
	 *
	 * @since    1.0.0
	 * @var      string|null    $description    Campaign description.
	 */
	private ?string $description = null;

	/**
	 * Campaign status.
	 *
	 * @since    1.0.0
	 * @var      string    $status    Campaign status.
	 */
	private string $status = 'draft';

	/**
	 * Campaign priority.
	 *
	 * @since    1.0.0
	 * @var      int    $priority    Campaign priority.
	 */
	private int $priority = 3;

	/**
	 * Campaign settings.
	 *
	 * @since    1.0.0
	 * @var      array    $settings    Campaign settings.
	 */
	private array $settings = array();

	/**
	 * Campaign metadata.
	 *
	 * @since    1.0.0
	 * @var      array    $metadata    Campaign metadata.
	 */
	private array $metadata = array();

	/**
	 * Template ID.
	 *
	 * @since    1.0.0
	 * @var      int|null    $template_id    Template ID.
	 */
	private ?int $template_id = null;

	/**
	 * Created by user ID.
	 *
	 * @since    1.0.0
	 * @var      int    $created_by    Created by user ID.
	 */
	private int $created_by = 0;

	/**
	 * Updated by user ID.
	 *
	 * @since    1.0.0
	 * @var      int|null    $updated_by    Updated by user ID.
	 */
	private ?int $updated_by = null;

	/**
	 * Start date.
	 *
	 * @since    1.0.0
	 * @var      DateTime|null    $starts_at    Start date.
	 */
	private ?DateTime $starts_at = null;

	/**
	 * End date.
	 *
	 * @since    1.0.0
	 * @var      DateTime|null    $ends_at    End date.
	 */
	private ?DateTime $ends_at = null;

	/**
	 * Timezone.
	 *
	 * @since    1.0.0
	 * @var      string    $timezone    Timezone.
	 */
	private string $timezone;

	/**
	 * Enable recurring flag.
	 *
	 * @since    1.0.0
	 * @var      bool    $enable_recurring    Whether recurring schedule is enabled.
	 */
	private bool $enable_recurring = false;

	/**
	 * Recurring configuration.
	 *
	 * @since    1.1.0
	 * @var      array    $recurring_config    Recurring campaign configuration.
	 */
	private array $recurring_config = array();

	/**
	 * Product selection type.
	 *
	 * @since    1.0.0
	 * @var      string    $product_selection_type    Product selection type.
	 */
	private string $product_selection_type = 'all_products';

	/**
	 * Product IDs.
	 *
	 * @since    1.0.0
	 * @var      array    $product_ids    Product IDs.
	 */
	private array $product_ids = array();

	/**
	 * Category IDs.
	 *
	 * @since    1.0.0
	 * @var      array    $category_ids    Category IDs.
	 */
	private array $category_ids = array();

	/**
	 * Tag IDs.
	 *
	 * @since    1.0.0
	 * @var      array    $tag_ids    Tag IDs.
	 */
	private array $tag_ids = array();

	/**
	 * Product conditions.
	 *
	 * Array of conditions for filtering products (e.g., price > 100, stock < 10).
	 * Each condition is stored as an array with: condition_type, operator, value, value2, mode, sort_order.
	 *
	 * @since    1.0.0
	 * @var      array    $conditions    Product conditions.
	 */
	private array $conditions = array();

	/**
	 * Conditions logic.
	 *
	 * How multiple conditions are combined: 'all' (AND) or 'any' (OR).
	 *
	 * @since    1.0.0
	 * @var      string    $conditions_logic    Conditions logic.
	 */
	private string $conditions_logic = 'all';

	/**
	 * Random product count.
	 *
	 * Number of products to randomly select when using random_products selection type.
	 *
	 * @since    1.0.0
	 * @var      int    $random_product_count    Random product count.
	 */
	private int $random_product_count = 5;

	/**
	 * Compiled at timestamp.
	 *
	 * When product_ids were last compiled/calculated.
	 *
	 * @since    1.0.0
	 * @var      DateTime|null    $compiled_at    Compilation timestamp.
	 */
	private ?DateTime $compiled_at = null;

	/**
	 * Compilation method.
	 *
	 * How product_ids were compiled: 'static', 'random', 'smart', 'conditional'.
	 *
	 * @since    1.0.0
	 * @var      string|null    $compilation_method    Compilation method.
	 */
	private ?string $compilation_method = null;

	/**
	 * Discount type.
	 *
	 * @since    1.0.0
	 * @var      string    $discount_type    Discount type.
	 */
	private string $discount_type = 'percentage';

	/**
	 * Discount value.
	 *
	 * @since    1.0.0
	 * @var      float    $discount_value    Discount value.
	 */
	private float $discount_value = 0.0;

	/**
	 * Discount rules.
	 *
	 * @since    1.0.0
	 * @var      array    $discount_rules    Discount rules.
	 */
	private array $discount_rules = array();

	/**
	 * Badge enabled flag.
	 *
	 * @since    1.0.0
	 * @var      bool    $badge_enabled    Whether badge display is enabled.
	 */
	private bool $badge_enabled = true;

	/**
	 * Badge text.
	 *
	 * @since    1.0.0
	 * @var      string    $badge_text    Custom badge text or 'auto' for automatic.
	 */
	private string $badge_text = 'auto';

	/**
	 * Badge background color.
	 *
	 * @since    1.0.0
	 * @var      string    $badge_bg_color    Badge background color (hex).
	 */
	private string $badge_bg_color = '#ff0000';

	/**
	 * Badge text color.
	 *
	 * @since    1.0.0
	 * @var      string    $badge_text_color    Badge text color (hex).
	 */
	private string $badge_text_color = '#ffffff';

	/**
	 * Badge position.
	 *
	 * @since    1.0.0
	 * @var      string    $badge_position    Badge position (top-left, top-right, etc).
	 */
	private string $badge_position = 'top-right';

	/**
	 * Created date.
	 *
	 * @since    1.0.0
	 * @var      DateTime    $created_at    Created date.
	 */
	private DateTime $created_at;

	/**
	 * Updated date.
	 *
	 * @since    1.0.0
	 * @var      DateTime    $updated_at    Updated date.
	 */
	private DateTime $updated_at;

	/**
	 * Version number for optimistic locking.
	 *
	 * Incremented on each update to prevent concurrent modification conflicts.
	 *
	 * @since    1.0.0
	 * @var      int    $version    Version number.
	 */
	private int $version = 1;

	/**
	 * Deleted date.
	 *
	 * @since    1.0.0
	 * @var      DateTime|null    $deleted_at    Deleted date.
	 */
	private ?DateTime $deleted_at = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    array $data    Initial data.
	 */
	public function __construct( array $data = array() ) {
		$this->uuid     = $this->generate_uuid();
		$this->timezone = wp_timezone_string();
		// Use UTC timezone for timestamps (consistent with how dates are stored)
		$this->created_at = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$this->updated_at = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		// Fill with provided data
		if ( ! empty( $data ) ) {
			$this->fill( $data );
		}
	}

	/**
	 * Fill model with data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Data to fill.
	 * @return   void
	 */
	public function fill( array $data ): void {
		if ( isset( $data['slug'] ) ) {
			$this->set_slug( $data['slug'] );
			unset( $data['slug'] );
		}

		foreach ( $data as $key => $value ) {
			$setter = 'set_' . $key;
			if ( method_exists( $this, $setter ) ) {
				$this->$setter( $value );
			}
		}

	}

	// Simple getters and setters

	public function get_id(): ?int {
		return $this->id;
	}

	public function set_id( int $id ): void {
		$this->id = $id;
	}

	public function get_uuid(): string {
		return $this->uuid;
	}

	public function set_uuid( string $uuid ): void {
		$this->uuid = $uuid;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function set_name( string $name ): void {
		$this->name = $name;
		// Auto-generate slug if empty
		if ( empty( $this->slug ) ) {
			$this->slug = sanitize_title( $name );
		}
	}

	public function get_slug(): string {
		return $this->slug;
	}

	public function set_slug( string $slug ): void {
		$this->slug = sanitize_title( $slug );
	}

	public function get_description(): ?string {
		return $this->description;
	}

	public function set_description( ?string $description ): void {
		$this->description = $description;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function set_status( string $status ): void {
		$this->status = $status;
	}

	/**
	 * Check if campaign can transition to a given status.
	 *
	 * @since    1.0.0
	 * @param    string $to_status    Target status.
	 * @return   bool                    True if transition is allowed.
	 */
	public function can_transition_to( string $to_status ): bool {
		$current_status = $this->get_status();

		// Define allowed status transitions (must match State Manager rules)
		$allowed_transitions = array(
			'draft'     => array( 'active', 'scheduled', 'archived' ),
			'active'    => array( 'paused', 'expired', 'archived' ),
			'paused'    => array( 'active', 'scheduled', 'draft', 'expired', 'archived' ),
			'scheduled' => array( 'active', 'paused', 'draft', 'archived' ),
			'expired'   => array( 'draft', 'archived' ),
			'archived'  => array( 'draft' ),
		);

		if ( ! isset( $allowed_transitions[ $current_status ] ) ) {
			return false;
		}

		return in_array( $to_status, $allowed_transitions[ $current_status ], true );
	}

	public function get_priority(): int {
		return $this->priority;
	}

	public function set_priority( int $priority ): void {
		$this->priority = max( 1, min( 5, $priority ) );
	}

	public function get_settings(): array {
		return $this->settings;
	}

	public function set_settings( array $settings ): void {
		$this->settings = $settings;
	}

	public function get_setting( string $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	public function set_setting( string $key, $value ): void {
		$this->settings[ $key ] = $value;
	}

	public function get_metadata(): array {
		return $this->metadata;
	}

	public function set_metadata( array $metadata ): void {
		$this->metadata = $metadata;
	}

	public function get_meta( string $key, $default = null ) {
		return $this->metadata[ $key ] ?? $default;
	}

	public function set_meta( string $key, $value ): void {
		$this->metadata[ $key ] = $value;
	}

	public function get_template_id(): ?int {
		return $this->template_id;
	}

	public function set_template_id( ?int $template_id ): void {
		$this->template_id = $template_id;
	}

	public function get_created_by(): int {
		return $this->created_by;
	}

	public function set_created_by( int $created_by ): void {
		$this->created_by = $created_by;
	}

	public function get_updated_by(): ?int {
		return $this->updated_by;
	}

	public function set_updated_by( ?int $updated_by ): void {
		$this->updated_by = $updated_by;
	}

	public function get_starts_at(): ?DateTime {
		return $this->starts_at;
	}

	public function set_starts_at( $starts_at ): void {
		if ( is_string( $starts_at ) ) {
			// Dates from database are always in UTC (already stored as UTC)
			$this->starts_at = new DateTime( $starts_at, new DateTimeZone( 'UTC' ) );
		} elseif ( $starts_at instanceof DateTime ) {
			$this->starts_at = clone $starts_at;
			$this->starts_at->setTimezone( new DateTimeZone( 'UTC' ) );
		} else {
			$this->starts_at = null;
		}
	}

	public function get_ends_at(): ?DateTime {
		return $this->ends_at;
	}

	public function set_ends_at( $ends_at ): void {
		if ( is_string( $ends_at ) ) {
			// Dates from database are always in UTC (already stored as UTC)
			$this->ends_at = new DateTime( $ends_at, new DateTimeZone( 'UTC' ) );
		} elseif ( $ends_at instanceof DateTime ) {
			$this->ends_at = clone $ends_at;
			$this->ends_at->setTimezone( new DateTimeZone( 'UTC' ) );
		} else {
			$this->ends_at = null;
		}
	}

	public function get_timezone(): string {
		return $this->timezone;
	}

	public function set_timezone( string $timezone ): void {
		$this->timezone = $timezone;
	}

	public function get_enable_recurring(): bool {
		return $this->enable_recurring;
	}

	public function set_enable_recurring( bool $enable_recurring ): void {
		$this->enable_recurring = $enable_recurring;
	}

	public function get_recurring_config(): array {
		return $this->recurring_config;
	}

	public function set_recurring_config( array $recurring_config ): void {
		$this->recurring_config = $recurring_config;
	}

	public function get_product_selection_type(): string {
		return $this->product_selection_type;
	}

	public function set_product_selection_type( string $type ): void {
		$valid_types = self::get_valid_selection_types();

		if ( ! in_array( $type, $valid_types, true ) ) {
			// Default to all_products for invalid types.
			$type = self::SELECTION_TYPE_ALL_PRODUCTS;
		}

		$this->product_selection_type = $type;
	}

	public function get_product_ids(): array {
		return $this->product_ids;
	}

	public function set_product_ids( array $products ): void {
		$this->product_ids = array_map( 'intval', $products );
	}

	public function get_category_ids(): array {
		return $this->category_ids;
	}

	public function set_category_ids( array $categories ): void {
		// Filter empty values and convert to integers.
		$this->category_ids = array_values( array_filter(
			array_map( 'intval', $categories ),
			function( $id ) {
				return $id > 0;
			}
		) );
	}

	public function get_tag_ids(): array {
		return $this->tag_ids;
	}

	public function set_tag_ids( array $tags ): void {
		// Filter empty values and convert to integers.
		$this->tag_ids = array_values( array_filter(
			array_map( 'intval', $tags ),
			function( $id ) {
				return $id > 0;
			}
		) );
	}

	public function get_conditions(): array {
		return $this->conditions;
	}

	public function set_conditions( array $conditions ): void {
		$this->conditions = $conditions;
	}

	public function get_conditions_logic(): string {
		return $this->conditions_logic;
	}

	public function set_conditions_logic( string $logic ): void {
		if ( ! in_array( $logic, array( 'all', 'any' ), true ) ) {
			$logic = 'all';
		}
		$this->conditions_logic = $logic;
	}

	public function get_random_product_count(): int {
		return $this->random_product_count;
	}

	public function set_random_product_count( int $count ): void {
		$this->random_product_count = max( 1, $count );
	}

	public function get_compiled_at(): ?DateTime {
		return $this->compiled_at;
	}

	public function set_compiled_at( $compiled_at ): void {
		if ( is_string( $compiled_at ) && ! empty( $compiled_at ) ) {
			$this->compiled_at = new DateTime( $compiled_at, new DateTimeZone( 'UTC' ) );
		} elseif ( $compiled_at instanceof DateTime ) {
			$this->compiled_at = clone $compiled_at;
			$this->compiled_at->setTimezone( new DateTimeZone( 'UTC' ) );
		} else {
			$this->compiled_at = null;
		}
	}

	public function get_compilation_method(): ?string {
		return $this->compilation_method;
	}

	public function set_compilation_method( ?string $method ): void {
		$valid_methods = array( 'static', 'random', 'smart', 'conditional' );
		if ( null !== $method && ! in_array( $method, $valid_methods, true ) ) {
			$method = 'static';
		}
		$this->compilation_method = $method;
	}

	/**
	 * Check if campaign needs recompilation.
	 *
	 * @since    1.0.0
	 * @return   bool    True if needs recompilation.
	 */
	public function needs_recompilation(): bool {
		if ( ! $this->compiled_at ) {
			return true;
		}

		if ( 'random_products' === $this->product_selection_type ) {
			return true;
		}

		return false;
	}

	/**
	 * Mark campaign as compiled.
	 *
	 * Sets compiled_at timestamp and compilation_method.
	 *
	 * @since    1.0.0
	 * @param    string $method    Compilation method used.
	 * @return   void
	 */
	public function mark_compiled( string $method ): void {
		$this->compiled_at        = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$this->compilation_method = $method;
	}

	public function get_discount_type(): string {
		return $this->discount_type;
	}

	public function set_discount_type( string $type ): void {
		$this->discount_type = $type;
	}

	public function get_discount_value(): float {
		return $this->discount_value;
	}

	public function set_discount_value( float $value ): void {
		$this->discount_value = $value;
	}

	public function get_discount_rules(): array {
		return $this->discount_rules;
	}

	public function set_discount_rules( array $rules ): void {
		$this->discount_rules = $rules;
	}

	/**
	 * Check if badge is enabled.
	 *
	 * @since    1.0.0
	 * @return   bool    True if badge enabled.
	 */
	public function is_badge_enabled(): bool {
		return $this->badge_enabled;
	}

	/**
	 * Set badge enabled flag.
	 *
	 * @since    1.0.0
	 * @param    bool $enabled    Badge enabled flag.
	 * @return   void
	 */
	public function set_badge_enabled( bool $enabled ): void {
		$this->badge_enabled = $enabled;
	}

	/**
	 * Get badge text.
	 *
	 * @since    1.0.0
	 * @return   string    Badge text.
	 */
	public function get_badge_text(): string {
		return $this->badge_text;
	}

	/**
	 * Set badge text.
	 *
	 * @since    1.0.0
	 * @param    string $text    Badge text.
	 * @return   void
	 */
	public function set_badge_text( string $text ): void {
		$this->badge_text = $text;
	}

	/**
	 * Get badge background color.
	 *
	 * @since    1.0.0
	 * @return   string    Badge background color.
	 */
	public function get_badge_bg_color(): string {
		return $this->badge_bg_color;
	}

	/**
	 * Set badge background color.
	 *
	 * @since    1.0.0
	 * @param    string $color    Badge background color (hex).
	 * @return   void
	 */
	public function set_badge_bg_color( string $color ): void {
		$this->badge_bg_color = $color;
	}

	/**
	 * Get badge text color.
	 *
	 * @since    1.0.0
	 * @return   string    Badge text color.
	 */
	public function get_badge_text_color(): string {
		return $this->badge_text_color;
	}

	/**
	 * Set badge text color.
	 *
	 * @since    1.0.0
	 * @param    string $color    Badge text color (hex).
	 * @return   void
	 */
	public function set_badge_text_color( string $color ): void {
		$this->badge_text_color = $color;
	}

	/**
	 * Get badge position.
	 *
	 * @since    1.0.0
	 * @return   string    Badge position.
	 */
	public function get_badge_position(): string {
		return $this->badge_position;
	}

	/**
	 * Set badge position.
	 *
	 * @since    1.0.0
	 * @param    string $position    Badge position.
	 * @return   void
	 */
	public function set_badge_position( string $position ): void {
		$this->badge_position = $position;
	}

	public function get_created_at(): DateTime {
		return $this->created_at;
	}

	public function set_created_at( $created_at ): void {
		if ( is_string( $created_at ) ) {
			// Dates from database are in UTC
			$this->created_at = new DateTime( $created_at, new DateTimeZone( 'UTC' ) );
		} elseif ( $created_at instanceof DateTime ) {
			$this->created_at = $created_at;
		}
	}

	public function get_updated_at(): DateTime {
		return $this->updated_at;
	}

	public function set_updated_at( $updated_at ): void {
		if ( is_string( $updated_at ) ) {
			// Dates from database are in UTC
			$this->updated_at = new DateTime( $updated_at, new DateTimeZone( 'UTC' ) );
		} elseif ( $updated_at instanceof DateTime ) {
			$this->updated_at = $updated_at;
		}
	}

	public function get_version(): int {
		return $this->version;
	}

	public function set_version( int $version ): void {
		$this->version = $version;
	}

	/**
	 * Increment version for optimistic locking.
	 *
	 * @since    1.0.0
	 * @return   int    New version number.
	 */
	public function increment_version(): int {
		++$this->version;
		return $this->version;
	}

	public function get_deleted_at(): ?DateTime {
		return $this->deleted_at;
	}

	public function set_deleted_at( $deleted_at ): void {
		if ( is_string( $deleted_at ) ) {
			// Dates from database are in UTC
			$this->deleted_at = new DateTime( $deleted_at, new DateTimeZone( 'UTC' ) );
		} elseif ( $deleted_at instanceof DateTime ) {
			$this->deleted_at = $deleted_at;
		} else {
			$this->deleted_at = null;
		}
	}

	/**
	 * Convert to array.
	 *
	 * @since    1.0.0
	 * @return   array    Campaign data as array.
	 */
	public function to_array(): array {
		return array(
			'id'                     => $this->id,
			'uuid'                   => $this->uuid,
			'name'                   => $this->name,
			'slug'                   => $this->slug,
			'description'            => $this->description,
			'status'                 => $this->status,
			'priority'               => $this->priority,
			'settings'               => $this->settings,
			'metadata'               => $this->metadata,
			'template_id'            => $this->template_id,
			'created_by'             => $this->created_by,
			'updated_by'             => $this->updated_by,
			'starts_at'              => $this->starts_at ? $this->starts_at->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null,
			'ends_at'                => $this->ends_at ? $this->ends_at->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null,
			'timezone'               => $this->timezone,
			'enable_recurring'       => $this->enable_recurring,
			'recurring_config'       => $this->recurring_config,
			'product_selection_type' => $this->product_selection_type,
			'product_ids'            => $this->product_ids,
			'category_ids'           => $this->category_ids,
			'tag_ids'                => $this->tag_ids,
			'conditions'             => $this->conditions,
			'conditions_logic'       => $this->conditions_logic,
			'random_product_count'   => $this->random_product_count,
			'compiled_at'            => $this->compiled_at ? $this->compiled_at->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null,
			'compilation_method'     => $this->compilation_method,
			'discount_type'          => $this->discount_type,
			'discount_value'         => $this->discount_value,
			'discount_rules'         => $this->discount_rules,
			'created_at'             => $this->created_at->format( 'Y-m-d H:i:s' ),
			'updated_at'             => $this->updated_at->format( 'Y-m-d H:i:s' ),
			'version'                => $this->version,
			'deleted_at'             => null !== $this->deleted_at ? $this->deleted_at->format( 'Y-m-d H:i:s' ) : null,
		);
	}

	/**
	 * Generate UUID.
	 *
	 * @since    1.0.0
	 * @return   string    Generated UUID.
	 */
	private function generate_uuid(): string {
		return wp_generate_uuid4();
	}

	/**
	 * Validate the campaign data.
	 *
	 * @since    1.0.0
	 * @return   array    Array of validation errors (empty if valid).
	 */
	public function validate(): array {
		$step_data = array(
			'campaign_name'          => $this->name,
			'campaign_description'   => $this->description,
			'product_selection_type' => $this->product_selection_type,
			'selected_products'      => $this->product_ids,
			'selected_categories'    => $this->category_ids,
			'selected_tags'          => $this->tag_ids,
			'discount_type'          => $this->discount_type,
			'discount_value'         => $this->discount_value,
			'start_date'             => $this->starts_at ? $this->starts_at->format( 'Y-m-d' ) : '',
			'start_time'             => $this->starts_at ? $this->starts_at->format( 'H:i:s' ) : '',
			'end_date'               => $this->ends_at ? $this->ends_at->format( 'Y-m-d' ) : '',
			'end_time'               => $this->ends_at ? $this->ends_at->format( 'H:i:s' ) : '',
			'timezone'               => $this->timezone,
		);

		// Data already validated by Campaign Manager before creation
		// No need for additional validation here
		return array();
	}

	/**
	 * Check if the campaign is valid.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid, false otherwise.
	 */
	public function is_valid(): bool {
		$errors = $this->validate();
		return empty( $errors );
	}

	/**
	 * Get performance metrics.
	 *
	 * Note: The Campaign entity is a simplified domain model that doesn't directly
	 * track performance metrics. Performance data (revenue_generated, orders_count,
	 * impressions_count, etc.) exists in the database campaigns table but is managed
	 * by the Analytics service, not the Campaign entity.
	 *
	 * Returns metrics from metadata if stored there, otherwise empty array.
	 * Used by campaigns list table to display performance data.
	 *
	 * @since    1.0.0
	 * @return   array    Performance metrics from metadata or empty array.
	 */
	public function get_performance_metrics(): array {
		$metadata = $this->get_metadata();
		if ( isset( $metadata['performance_metrics'] ) && is_array( $metadata['performance_metrics'] ) ) {
			return $metadata['performance_metrics'];
		}

		return array();
	}
}
