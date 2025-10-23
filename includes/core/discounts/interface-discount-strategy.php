<?php
/**
 * Discount strategy interface
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Discount Strategy Interface
 *
 * Defines the contract for discount calculation strategies.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
interface SCD_Discount_Strategy_Interface {

    /**
     * Calculate discount for a product.
     *
     * @since    1.0.0
     * @param    float    $original_price    Original product price.
     * @param    array    $discount_config   Discount configuration.
     * @param    array    $context          Additional context (product, cart, etc.).
     * @return   SCD_Discount_Result        Discount calculation result.
     */
    public function calculate_discount(float $original_price, array $discount_config, array $context = array()): SCD_Discount_Result;

    /**
     * Validate discount configuration.
     *
     * @since    1.0.0
     * @param    array    $discount_config    Discount configuration.
     * @return   array                       Validation errors (empty if valid).
     */
    public function validate_config(array $discount_config): array;

    /**
     * Get strategy identifier.
     *
     * @since    1.0.0
     * @return   string    Strategy identifier.
     */
    public function get_strategy_id(): string;

    /**
     * Get strategy name.
     *
     * @since    1.0.0
     * @return   string    Human-readable strategy name.
     */
    public function get_strategy_name(): string;

    /**
     * Get strategy description.
     *
     * @since    1.0.0
     * @return   string    Strategy description.
     */
    public function get_strategy_description(): string;

    /**
     * Check if strategy supports given context.
     *
     * @since    1.0.0
     * @param    array    $context    Context to check.
     * @return   bool                 True if strategy supports context.
     */
    public function supports_context(array $context): bool;

    /**
     * Get minimum discount amount.
     *
     * @since    1.0.0
     * @param    array    $discount_config    Discount configuration.
     * @return   float                        Minimum discount amount.
     */
    public function get_minimum_discount(array $discount_config): float;

    /**
     * Get maximum discount amount.
     *
     * @since    1.0.0
     * @param    array    $discount_config    Discount configuration.
     * @return   float                        Maximum discount amount.
     */
    public function get_maximum_discount(array $discount_config): float;
}

/**
 * Discount Result Class
 *
 * Represents the result of a discount calculation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 */
class SCD_Discount_Result {

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
     * Discount amount.
     *
     * @since    1.0.0
     * @var      float    $discount_amount    Discount amount.
     */
    private float $discount_amount;

    /**
     * Discount percentage.
     *
     * @since    1.0.0
     * @var      float    $discount_percentage    Discount percentage.
     */
    private float $discount_percentage;

    /**
     * Strategy used.
     *
     * @since    1.0.0
     * @var      string    $strategy_id    Strategy identifier.
     */
    private string $strategy_id;

    /**
     * Whether discount was applied.
     *
     * @since    1.0.0
     * @var      bool    $applied    Whether discount was applied.
     */
    private bool $applied;

    /**
     * Additional metadata.
     *
     * @since    1.0.0
     * @var      array    $metadata    Additional metadata.
     */
    private array $metadata;

    /**
     * Initialize discount result.
     *
     * @since    1.0.0
     * @param    float     $original_price       Original price.
     * @param    float     $discounted_price     Discounted price.
     * @param    string    $strategy_id          Strategy identifier.
     * @param    bool      $applied              Whether discount was applied.
     * @param    array     $metadata             Additional metadata.
     */
    public function __construct(
        float $original_price,
        float $discounted_price,
        string $strategy_id,
        bool $applied = true,
        array $metadata = array()
    ) {
        $this->original_price = $original_price;
        $this->discounted_price = max(0, $discounted_price);
        $this->discount_amount = $original_price - $this->discounted_price;
        $this->discount_percentage = $original_price > 0 ? ($this->discount_amount / $original_price) * 100 : 0;
        $this->strategy_id = $strategy_id;
        $this->applied = $applied;
        $this->metadata = $metadata;
    }

    /**
     * Create a no-discount result.
     *
     * @since    1.0.0
     * @param    float     $original_price    Original price.
     * @param    string    $strategy_id       Strategy identifier.
     * @param    string    $reason            Reason for no discount.
     * @return   SCD_Discount_Result          No-discount result.
     */
    public static function no_discount(float $original_price, string $strategy_id, string $reason = ''): self {
        return new self(
            $original_price,
            $original_price,
            $strategy_id,
            false,
            array('reason' => $reason)
        );
    }

    /**
     * Get original price.
     *
     * @since    1.0.0
     * @return   float    Original price.
     */
    public function get_original_price(): float {
        return $this->original_price;
    }

    /**
     * Get discounted price.
     *
     * @since    1.0.0
     * @return   float    Discounted price.
     */
    public function get_discounted_price(): float {
        return $this->discounted_price;
    }

    /**
     * Get discount amount.
     *
     * @since    1.0.0
     * @return   float    Discount amount.
     */
    public function get_discount_amount(): float {
        return $this->discount_amount;
    }

    /**
     * Get discount percentage.
     *
     * @since    1.0.0
     * @return   float    Discount percentage.
     */
    public function get_discount_percentage(): float {
        return $this->discount_percentage;
    }

    /**
     * Get strategy ID.
     *
     * @since    1.0.0
     * @return   string    Strategy identifier.
     */
    public function get_strategy_id(): string {
        return $this->strategy_id;
    }

    /**
     * Check if discount was applied.
     *
     * @since    1.0.0
     * @return   bool    True if discount was applied.
     */
    public function is_applied(): bool {
        return $this->applied;
    }

    /**
     * Get metadata.
     *
     * @since    1.0.0
     * @return   array    Metadata array.
     */
    public function get_metadata(): array {
        return $this->metadata;
    }

    /**
     * Get specific metadata value.
     *
     * @since    1.0.0
     * @param    string    $key        Metadata key.
     * @param    mixed     $default    Default value.
     * @return   mixed                 Metadata value.
     */
    public function get_metadata_value(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get reason why discount was not applied.
     *
     * @since    1.0.0
     * @return   string    Reason for no discount.
     */
    public function get_reason(): string {
        return $this->metadata['reason'] ?? '';
    }

    /**
     * Set metadata value.
     *
     * @since    1.0.0
     * @param    string    $key      Metadata key.
     * @param    mixed     $value    Metadata value.
     * @return   void
     */
    public function set_metadata_value(string $key, mixed $value): void {
        $this->metadata[$key] = $value;
    }

    /**
     * Check if discount has savings.
     *
     * @since    1.0.0
     * @return   bool    True if there are savings.
     */
    public function has_savings(): bool {
        return $this->applied && $this->discount_amount > 0;
    }

    /**
     * Get savings percentage.
     *
     * @since    1.0.0
     * @return   float    Savings percentage.
     */
    public function get_savings_percentage(): float {
        return $this->discount_percentage;
    }

    /**
     * Convert to array.
     *
     * @since    1.0.0
     * @return   array    Result as array.
     */
    public function to_array(): array {
        return array(
            'original_price' => $this->original_price,
            'discounted_price' => $this->discounted_price,
            'discount_amount' => $this->discount_amount,
            'discount_percentage' => $this->discount_percentage,
            'strategy_id' => $this->strategy_id,
            'applied' => $this->applied,
            'has_savings' => $this->has_savings(),
            'metadata' => $this->metadata
        );
    }

    /**
     * Convert to JSON.
     *
     * @since    1.0.0
     * @return   string    Result as JSON.
     */
    public function to_json(): string {
        return wp_json_encode($this->to_array());
    }
}

/**
 * Discount Preview Trait
 *
 * Provides shared preview_discount() implementation for all strategy classes.
 * Eliminates code duplication across strategy implementations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts
 */
trait SCD_Discount_Preview_Trait {

	/**
	 * Preview discount calculation.
	 *
	 * Shared implementation across all strategy classes.
	 * Formats discount preview data for display purposes.
	 *
	 * @since    1.0.0
	 * @param    float $original_price    Original price.
	 * @param    array $discount_config   Discount configuration.
	 * @return   array                    Preview data with formatted values.
	 */
	public function preview_discount( float $original_price, array $discount_config ): array {
		$result = $this->calculate_discount( $original_price, $discount_config );

		return array(
			'strategy_name'       => $this->get_strategy_name(),
			'original_price'      => $original_price,
			'discounted_price'    => $result->get_discounted_price(),
			'discount_amount'     => $result->get_discount_amount(),
			'discount_percentage' => $result->get_discount_percentage(),
			'savings'             => $result->get_discount_amount(),
			'applied'             => $result->is_applied(),
			'formatted'           => array(
				'original_price'   => wc_price( $original_price ),
				'discounted_price' => wc_price( $result->get_discounted_price() ),
				'discount_amount'  => wc_price( $result->get_discount_amount() ),
				'savings_text'     => $this->format_savings_text( $result ),
			),
		);
	}

	/**
	 * Format savings text for display.
	 *
	 * Override in strategy classes to customize savings text format.
	 *
	 * @since    1.0.0
	 * @param    SCD_Discount_Result $result    Discount calculation result.
	 * @return   string                         Formatted savings text.
	 */
	protected function format_savings_text( SCD_Discount_Result $result ): string {
		return sprintf(
			/* translators: %s: discount amount */
			__( 'Save %s', 'smart-cycle-discounts' ),
			wc_price( $result->get_discount_amount() )
		);
	}

	/**
	 * Get common discount type field schema.
	 *
	 * Shared field definition for percentage/fixed discount type selectors.
	 *
	 * @since    1.0.0
	 * @return   array    Discount type field schema.
	 */
	protected function get_discount_type_field_schema(): array {
		return array(
			'type'     => 'select',
			'label'    => __( 'Discount Type', 'smart-cycle-discounts' ),
			'options'  => array(
				'percentage' => __( 'Percentage', 'smart-cycle-discounts' ),
				'fixed'      => __( 'Fixed Amount', 'smart-cycle-discounts' ),
			),
			'default'  => 'percentage',
			'required' => true,
		);
	}

	/**
	 * Get common discount value field schema.
	 *
	 * Shared field definition for discount value inputs.
	 *
	 * @since    1.0.0
	 * @return   array    Discount value field schema.
	 */
	protected function get_discount_value_field_schema(): array {
		return array(
			'type'        => 'number',
			'label'       => __( 'Discount Value', 'smart-cycle-discounts' ),
			'description' => __( 'Discount percentage or fixed amount', 'smart-cycle-discounts' ),
			'min'         => 0,
			'step'        => 0.01,
			'required'    => true,
		);
	}
}
