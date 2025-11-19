<?php
/**
 * Campaign Overview Panel Component
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
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
 * Campaign Overview Panel Component
 *
 * Provides a slide-out panel for viewing campaign details without
 * leaving the campaigns list page.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Campaign_Overview_Panel {

	/**
	 * Campaign repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private $campaign_repository;

	/**
	 * Campaign formatter.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Formatter|null    $formatter    Campaign formatter.
	 */
	private $formatter;

	/**
	 * Analytics repository (optional).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Analytics_Repository|null    $analytics_repository    Analytics repository.
	 */
	private $analytics_repository;

	/**
	 * Recurring handler (optional).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Recurring_Handler|null    $recurring_handler    Recurring handler.
	 */
	private $recurring_handler;

	/**
	 * Product selector service (optional).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Product_Selector|null    $product_selector    Product selector service.
	 */
	private $product_selector;

	/**
	 * Initialize the component.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Repository         $campaign_repository      Campaign repository.
	 * @param    SCD_Campaign_Formatter|null     $formatter                Campaign formatter.
	 * @param    SCD_Analytics_Repository|null   $analytics_repository     Analytics repository.
	 * @param    SCD_Recurring_Handler|null      $recurring_handler        Recurring handler.
	 * @param    SCD_Product_Selector|null       $product_selector         Product selector service.
	 */
	public function __construct(
		$campaign_repository,
		$formatter = null,
		$analytics_repository = null,
		$recurring_handler = null,
		$product_selector = null
	) {
		$this->campaign_repository  = $campaign_repository;
		$this->formatter            = $formatter;
		$this->analytics_repository = $analytics_repository;
		$this->recurring_handler    = $recurring_handler;
		$this->product_selector     = $product_selector;
	}

	/**
	 * Render the panel HTML structure.
	 *
	 * This is rendered on page load (hidden) and populated via AJAX when opened.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render() {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/campaign-overview-panel.php';


		if ( ! file_exists( $template_path ) ) {
			return;
		}

		// Pass component instance to template for method access
		$panel = $this;

		include $template_path;
	}

	/**
	 * Prepare campaign data for panel display.
	 *
	 * Called by AJAX handler to format campaign data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Formatted campaign data.
	 */
	public function prepare_campaign_data( $campaign ) {
		$data = array(
			'id'                 => $campaign->get_id(),
			'uuid'               => $campaign->get_uuid(),
			'name'               => $campaign->get_name(),
			'status'             => $campaign->get_status(),
			'basic'              => $this->prepare_basic_section( $campaign ),
			'schedule'           => $this->prepare_schedule_section( $campaign ),
			'recurring_schedule' => $this->prepare_recurring_schedule_section( $campaign ),
			'products'           => $this->prepare_products_section( $campaign ),
			'discounts'          => $this->prepare_discounts_section( $campaign ),
			'performance'        => $this->prepare_performance_section( $campaign ),
		);

		return $data;
	}

	/**
	 * Prepare basic info section data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Basic info data.
	 */
	private function prepare_basic_section( $campaign ) {
		return array(
			'name'        => $campaign->get_name(),
			'description' => $campaign->get_description(),
			'status'      => $campaign->get_status(),
			'priority'    => $campaign->get_priority(),
			'created_by'  => $campaign->get_created_by(),
			'created_at'  => $campaign->get_created_at(),
			'updated_at'  => $campaign->get_updated_at(),
		);
	}

	/**
	 * Prepare schedule section data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Schedule data.
	 */
	private function prepare_schedule_section( $campaign ) {
		$starts_at = $campaign->get_starts_at();
		$ends_at   = $campaign->get_ends_at();
		$timezone  = $campaign->get_timezone();

		// Convert UTC to site timezone for display
		if ( $starts_at ) {
			$starts_at = clone $starts_at;
			$starts_at->setTimezone( new DateTimeZone( $timezone ) );
		}

		if ( $ends_at ) {
			$ends_at = clone $ends_at;
			$ends_at->setTimezone( new DateTimeZone( $timezone ) );
		}

		// Calculate duration with improved formatting
		$duration        = null;
		$duration_detail = null;
		if ( $starts_at && $ends_at ) {
			$interval = $starts_at->diff( $ends_at );
			$days     = absint( $interval->days );
			$hours    = absint( $interval->h );
			$minutes  = absint( $interval->i );

			if ( 0 === $days && 0 === $hours ) {
				// Less than 1 hour
				$duration = sprintf(
					/* translators: %d: number of minutes */
					_n( '%d minute', '%d minutes', $minutes, 'smart-cycle-discounts' ),
					$minutes
				);
			} elseif ( 0 === $days ) {
				// Same day - show hours
				$duration = sprintf(
					/* translators: %d: number of hours */
					_n( '%d hour', '%d hours', $hours, 'smart-cycle-discounts' ),
					$hours
				);
				if ( $minutes > 0 ) {
					$duration_detail = sprintf(
						/* translators: 1: number of hours, 2: number of minutes */
						__( '%1$d hours, %2$d minutes', 'smart-cycle-discounts' ),
						$hours,
						$minutes
					);
				}
			} elseif ( 1 === $days ) {
				// Exactly 1 day
				$duration = __( '1 day', 'smart-cycle-discounts' );
				if ( $hours > 0 ) {
					$duration_detail = sprintf(
						/* translators: %d: number of hours */
						_n( '1 day, %d hour', '1 day, %d hours', $hours, 'smart-cycle-discounts' ),
						$hours
					);
				}
			} else {
				// Multiple days
				$duration = sprintf(
					/* translators: %d: number of days */
					_n( '%d day', '%d days', $days, 'smart-cycle-discounts' ),
					$days
				);
				if ( $hours > 0 ) {
					$duration_detail = sprintf(
						/* translators: 1: number of days, 2: number of hours */
						__( '%1$d days, %2$d hours', 'smart-cycle-discounts' ),
						$days,
						$hours
					);
				}
			}
		}

		return array(
			'starts_at'       => $starts_at,
			'ends_at'         => $ends_at,
			'timezone'        => $timezone,
			'duration'        => $duration,
			'duration_detail' => $duration_detail,
		);
	}

	/**
	 * Prepare products section data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Products data.
	 */
	private function prepare_products_section( $campaign ) {
		$selection_type   = $campaign->get_product_selection_type();
		$product_ids      = $campaign->get_product_ids();
		$category_ids     = $campaign->get_category_ids();
		$tag_ids          = $campaign->get_tag_ids();
		$settings         = $campaign->get_settings();
		$conditions       = $campaign->get_conditions();
		$conditions_logic = $campaign->get_conditions_logic();

		$products        = array();
		$compiled_ids    = array();

		// For non-specific selection types, compile the actual product list
		if ( 'specific_products' !== $selection_type && $this->product_selector ) {
			try {
				// Build selection criteria for Product Selector
				$criteria = array(
					'product_selection_type' => $selection_type,
					'categories'             => $category_ids,
					'tags'                   => $tag_ids,
					'conditions'             => $conditions,
					'conditions_logic'       => $conditions_logic,
				);

				// Add excluded products from settings
				if ( ! empty( $settings['excluded_product_ids'] ) ) {
					$criteria['exclude_ids'] = $settings['excluded_product_ids'];
				}

				// Compile products using Product Selector
				$compiled_ids = $this->product_selector->select_products( $criteria );

				// Use compiled IDs for display
				$product_ids = $compiled_ids;
			} catch ( Exception $e ) {
				SCD_Log::warning(
					'Failed to compile products for campaign overview panel',
					array(
						'campaign_id'    => $campaign->get_id(),
						'selection_type' => $selection_type,
						'error'          => $e->getMessage(),
					)
				);
				// Fall back to using $product_ids from campaign (may be empty for non-specific types)
			}
		}

		// Load all product details
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					// Get product image URL (not WooCommerce HTML)
					// Returns empty string if no custom image (e-commerce agnostic approach)
					$image_id  = $product->get_image_id();
					$image_url = '';

					if ( $image_id && absint( $image_id ) > 0 ) {
						$image_data = wp_get_attachment_image_src( $image_id, 'thumbnail' );
						if ( $image_data && ! empty( $image_data[0] ) ) {
							$image_url = $image_data[0];
						}
					}

					$products[] = array(
						'id'        => $product_id,
						'name'      => $product->get_name(),
						'price'     => $product->get_price(),
						'image_url' => $image_url,
						'has_image' => ! empty( $image_url ),
						'url'       => $product->get_permalink(),
					);
				}
			}
		}

		// Load category names
		$categories              = array();
		$all_categories_selected = false;

		// Check if no categories were selected or if 'all' was selected (empty array or contains 0)
		if ( empty( $category_ids ) || ( is_array( $category_ids ) && in_array( 0, $category_ids, true ) ) ) {
			$all_categories_selected = true;
		} elseif ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
			foreach ( $category_ids as $cat_id ) {
				// Skip invalid IDs (0, negative, or 'all' converted to 0)
				if ( empty( $cat_id ) || ! is_numeric( $cat_id ) || $cat_id <= 0 ) {
					continue;
				}

				$category = get_term( absint( $cat_id ), 'product_cat' );
				if ( $category && ! is_wp_error( $category ) ) {
					$categories[] = array(
						'id'    => absint( $cat_id ),
						'name'  => $category->name,
						'count' => isset( $category->count ) ? absint( $category->count ) : 0,
					);
				}
			}
		}

		// Load tag names
		$tags = array();
		if ( ! empty( $tag_ids ) ) {
			foreach ( $tag_ids as $tag_id ) {
				$tag = get_term( $tag_id, 'product_tag' );
				if ( $tag && ! is_wp_error( $tag ) ) {
					$tags[] = array(
						'id'   => $tag_id,
						'name' => $tag->name,
					);
				}
			}
		}

		// Extract rotation settings from settings
		$rotation_enabled     = $settings['rotation_enabled'] ?? false;
		$rotation_interval    = $settings['rotation_interval'] ?? null;
		$rotation_type        = $settings['rotation_type'] ?? null;
		$max_concurrent       = $settings['max_concurrent_products'] ?? null;

		// Extract exclusions from settings
		$excluded_product_ids  = $settings['excluded_product_ids'] ?? array();
		$excluded_category_ids = $settings['excluded_category_ids'] ?? array();

		// Parse conditions for display
		$parsed_conditions = $this->parse_conditions( $conditions );

		// Extract additional settings
		$random_count   = $settings['random_count'] ?? 10;
		$smart_criteria = $settings['smart_criteria'] ?? '';

		return array(
			'campaign_id'             => $campaign->get_id(),
			'selection_type'          => $selection_type,
			'total_products'          => count( $product_ids ),
			'products'                => $products,
			'categories'              => $categories,
			'all_categories_selected' => $all_categories_selected,
			'category_count'          => count( $category_ids ),
			'tags'                    => $tags,
			'tag_count'               => count( $tag_ids ),
			'rotation_enabled'        => $rotation_enabled,
			'rotation_interval'       => $rotation_interval,
			'rotation_type'           => $rotation_type,
			'max_concurrent'          => $max_concurrent,
			'excluded_product_ids'    => $excluded_product_ids,
			'excluded_category_ids'   => $excluded_category_ids,
			'conditions'              => $parsed_conditions,
			'conditions_logic'        => $conditions_logic,
			'has_conditions'          => ! empty( $parsed_conditions ),
			'random_count'            => $random_count,
			'smart_criteria'          => $smart_criteria,
		);
	}

	/**
	 * Prepare discounts section data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Discounts data.
	 */
	private function prepare_discounts_section( $campaign ) {
		$discount_type  = $campaign->get_discount_type();
		$discount_value = $campaign->get_discount_value();
		$discount_rules = $campaign->get_discount_rules();
		$settings       = $campaign->get_settings();
		$metadata       = $campaign->get_metadata();

		// Format discount value based on type
		$formatted_value = $discount_value;
		if ( 'percentage' === $discount_type ) {
			$formatted_value = $discount_value . '%';
		} elseif ( 'fixed' === $discount_type ) {
			$formatted_value = wc_price( $discount_value );
		} elseif ( 'tiered' === $discount_type ) {
			$formatted_value = __( 'Multiple tiers', 'smart-cycle-discounts' );
		} elseif ( 'bogo' === $discount_type ) {
			$formatted_value = __( 'BOGO offer', 'smart-cycle-discounts' );
		} elseif ( 'spend_threshold' === $discount_type ) {
			$formatted_value = __( 'Spend thresholds', 'smart-cycle-discounts' );
		}

		// Extract usage limits - always show (with defaults)
		$max_uses              = $settings['max_uses'] ?? null;
		$max_uses_per_customer = $settings['max_uses_per_customer'] ?? null;
		$current_uses          = $metadata['current_uses'] ?? 0;

		// Calculate usage percentage for progress indicator
		$usage_percentage = 0;
		if ( $max_uses && $max_uses > 0 ) {
			$usage_percentage = min( 100, ( $current_uses / $max_uses ) * 100 );
		}

		// Extract minimum requirements - always show (with defaults)
		$min_order_amount = $discount_rules['min_order_amount'] ?? null;
		$min_quantity     = $discount_rules['min_quantity'] ?? null;

		// Extract restrictions - always show with proper defaults
		$exclude_sale_items   = $discount_rules['exclude_sale_items'] ?? false;
		$individual_use       = $discount_rules['individual_use'] ?? false;
		$free_shipping        = $discount_rules['free_shipping'] ?? false;
		$allowed_combinations = $discount_rules['allowed_combinations'] ?? array();

		// Extract badge configuration
		$badge_config = $this->extract_badge_config( $discount_rules );

		// Extract apply_to setting - always show with default
		$apply_to = $discount_rules['apply_to'] ?? 'per_item';

		// Parse complex discount types
		$bogo_config      = null;
		$tiered_config    = null;
		$threshold_config = null;

		if ( 'bogo' === $discount_type ) {
			$bogo_config = $this->parse_bogo_config( $discount_rules );
		} elseif ( 'tiered' === $discount_type ) {
			$tiered_config = $this->parse_tiered_config( $discount_rules );
		} elseif ( 'spend_threshold' === $discount_type ) {
			$threshold_config = $this->parse_threshold_config( $discount_rules );
		}

		return array(
			// Core discount info
			'type'                  => $discount_type,
			'value'                 => $discount_value,
			'formatted_value'       => $formatted_value,
			'apply_to'              => $apply_to,

			// Usage limits (always show)
			'max_uses'              => $max_uses,
			'max_uses_per_customer' => $max_uses_per_customer,
			'current_uses'          => $current_uses,
			'usage_percentage'      => $usage_percentage,

			// Minimum requirements (always show)
			'min_order_amount'      => $min_order_amount,
			'min_quantity'          => $min_quantity,

			// Restrictions and features (always show)
			'exclude_sale_items'    => $exclude_sale_items,
			'individual_use'        => $individual_use,
			'free_shipping'         => $free_shipping,
			'allowed_combinations'  => $allowed_combinations,

			// Badge configuration
			'badge_config'          => $badge_config,

			// Complex discount types
			'bogo_config'           => $bogo_config,
			'tiered_config'         => $tiered_config,
			'threshold_config'      => $threshold_config,
		);
	}

	/**
	 * Extract badge configuration from discount rules.
	 *
	 * @since    1.0.0
	 * @param    array $discount_rules    Discount rules array.
	 * @return   array                      Badge configuration.
	 */
	private function extract_badge_config( $discount_rules ) {
		return array(
			'enabled'    => $discount_rules['badge_enabled'] ?? true,
			'text'       => $discount_rules['badge_text'] ?? 'auto',
			'bg_color'   => $discount_rules['badge_bg_color'] ?? '#ff0000',
			'text_color' => $discount_rules['badge_text_color'] ?? '#ffffff',
			'position'   => $discount_rules['badge_position'] ?? 'top-right',
			'style'      => $discount_rules['badge_style'] ?? 'default',
		);
	}

	/**
	 * Parse BOGO configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_rules    Discount rules array.
	 * @return   array                      BOGO configuration.
	 */
	private function parse_bogo_config( $discount_rules ) {
		$buy_quantity            = absint( $discount_rules['buy_quantity'] ?? 1 );
		$get_quantity            = absint( $discount_rules['get_quantity'] ?? 1 );
		$get_discount_percentage = floatval( $discount_rules['get_discount_percentage'] ?? 100 );

		return array(
			'buy_quantity'            => $buy_quantity,
			'get_quantity'            => $get_quantity,
			'get_discount_percentage' => $get_discount_percentage,
			'description'             => sprintf(
				/* translators: 1: buy quantity, 2: get quantity, 3: discount percentage */
				__( 'Buy %1$d, Get %2$d at %3$s%% off', 'smart-cycle-discounts' ),
				$buy_quantity,
				$get_quantity,
				number_format_i18n( $get_discount_percentage, 0 )
			),
		);
	}

	/**
	 * Parse tiered discount configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_rules    Discount rules array.
	 * @return   array                      Tiered configuration.
	 */
	private function parse_tiered_config( $discount_rules ) {
		$tiers = $discount_rules['tiers'] ?? array();

		if ( empty( $tiers ) ) {
			return array(
				'tiers'       => array(),
				'tier_count'  => 0,
				'description' => __( 'No tiers configured', 'smart-cycle-discounts' ),
			);
		}

		$tier_descriptions = array();
		foreach ( $tiers as $tier ) {
			$min_qty        = absint( $tier['min_quantity'] ?? 0 );
			$discount_value = floatval( $tier['discount_value'] ?? 0 );
			$is_percentage  = ! empty( $tier['is_percentage'] );

			$tier_descriptions[] = array(
				'min_quantity'   => $min_qty,
				'discount_value' => $discount_value,
				'is_percentage'  => $is_percentage,
				'formatted'      => sprintf(
					/* translators: 1: minimum quantity, 2: discount value */
					__( '%1$d+ items: %2$s', 'smart-cycle-discounts' ),
					$min_qty,
					$is_percentage ? $discount_value . '%' : wc_price( $discount_value )
				),
			);
		}

		return array(
			'tiers'       => $tier_descriptions,
			'tier_count'  => count( $tiers ),
			'description' => sprintf(
				/* translators: %d: number of tiers */
				_n( '%d pricing tier', '%d pricing tiers', count( $tiers ), 'smart-cycle-discounts' ),
				count( $tiers )
			),
		);
	}

	/**
	 * Parse spend threshold configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_rules    Discount rules array.
	 * @return   array                      Threshold configuration.
	 */
	private function parse_threshold_config( $discount_rules ) {
		$thresholds     = $discount_rules['thresholds'] ?? array();
		$threshold_mode = $discount_rules['threshold_mode'] ?? 'percentage';

		if ( empty( $thresholds ) ) {
			return array(
				'thresholds'      => array(),
				'threshold_count' => 0,
				'mode'            => $threshold_mode,
				'description'     => __( 'No thresholds configured', 'smart-cycle-discounts' ),
			);
		}

		$threshold_descriptions = array();
		foreach ( $thresholds as $threshold ) {
			$min_amount     = floatval( $threshold['min_amount'] ?? 0 );
			$discount_value = floatval( $threshold['discount_value'] ?? 0 );

			$threshold_descriptions[] = array(
				'min_amount'     => $min_amount,
				'discount_value' => $discount_value,
				'formatted'      => sprintf(
					/* translators: 1: minimum amount, 2: discount value */
					__( 'Spend %1$s: %2$s off', 'smart-cycle-discounts' ),
					wc_price( $min_amount ),
					'percentage' === $threshold_mode ? $discount_value . '%' : wc_price( $discount_value )
				),
			);
		}

		return array(
			'thresholds'      => $threshold_descriptions,
			'threshold_count' => count( $thresholds ),
			'mode'            => $threshold_mode,
			'mode_label'      => 'percentage' === $threshold_mode ? __( 'Percentage', 'smart-cycle-discounts' ) : __( 'Fixed Amount', 'smart-cycle-discounts' ),
			'description'     => sprintf(
				/* translators: %d: number of thresholds */
				_n( '%d spend threshold', '%d spend thresholds', count( $thresholds ), 'smart-cycle-discounts' ),
				count( $thresholds )
			),
		);
	}

	/**
	 * Parse conditions array into display-friendly format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $conditions    Raw conditions array.
	 * @return   array                 Parsed conditions.
	 */
	private function parse_conditions( $conditions ) {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return array();
		}

		$parsed = array();

		$condition_type_labels = array(
			'price'       => __( 'Price', 'smart-cycle-discounts' ),
			'stock'       => __( 'Stock Level', 'smart-cycle-discounts' ),
			'category'    => __( 'Category', 'smart-cycle-discounts' ),
			'tag'         => __( 'Tag', 'smart-cycle-discounts' ),
			'attribute'   => __( 'Attribute', 'smart-cycle-discounts' ),
			'sale_status' => __( 'Sale Status', 'smart-cycle-discounts' ),
		);

		$operator_labels = array(
			'>'        => __( 'greater than', 'smart-cycle-discounts' ),
			'<'        => __( 'less than', 'smart-cycle-discounts' ),
			'='        => __( 'equals', 'smart-cycle-discounts' ),
			'>='       => __( 'greater than or equal to', 'smart-cycle-discounts' ),
			'<='       => __( 'less than or equal to', 'smart-cycle-discounts' ),
			'between'  => __( 'between', 'smart-cycle-discounts' ),
			'in'       => __( 'in', 'smart-cycle-discounts' ),
			'not_in'   => __( 'not in', 'smart-cycle-discounts' ),
		);

		foreach ( $conditions as $condition ) {
			$type     = $condition['condition_type'] ?? $condition['type'] ?? '';
			$operator = $condition['operator'] ?? '';
			$value    = $condition['value'] ?? '';
			$value2   = $condition['value2'] ?? '';
			$mode     = $condition['mode'] ?? 'include';

			if ( empty( $type ) ) {
				continue;
			}

			$type_label     = $condition_type_labels[ $type ] ?? ucfirst( $type );
			$operator_label = $operator_labels[ $operator ] ?? $operator;

			// Format value based on type
			if ( 'price' === $type ) {
				$formatted_value = wc_price( $value );
				if ( 'between' === $operator && ! empty( $value2 ) ) {
					$formatted_value = wc_price( $value ) . ' - ' . wc_price( $value2 );
				}
			} else {
				$formatted_value = $value;
				if ( 'between' === $operator && ! empty( $value2 ) ) {
					$formatted_value = $value . ' - ' . $value2;
				}
			}

			$parsed[] = array(
				'type'            => $type,
				'type_label'      => $type_label,
				'operator'        => $operator,
				'operator_label'  => $operator_label,
				'value'           => $value,
				'value2'          => $value2,
				'formatted_value' => $formatted_value,
				'mode'            => $mode,
				'description'     => sprintf(
					'%s %s %s',
					$type_label,
					$operator_label,
					$formatted_value
				),
			);
		}

		return $parsed;
	}

	/**
	 * Prepare performance section data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Performance data.
	 */
	private function prepare_performance_section( $campaign ) {
		// Default empty metrics
		$metrics = array(
			'revenue'     => 0,
			'conversions' => 0,
			'impressions' => 0,
			'clicks'      => 0,
			'ctr'         => 0,
			'avg_order'   => 0,
		);

		// Try to load analytics if service available
		if ( $this->analytics_repository ) {
			try {
				// Calculate date range for last 30 days
				$end_date   = current_time( 'mysql' );
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-30 days', strtotime( $end_date ) ) );

				$analytics = $this->analytics_repository->get_campaign_performance(
					$campaign->get_id(),
					$start_date,
					$end_date
				);

				if ( ! empty( $analytics ) ) {
					$metrics = array(
						'revenue'     => $analytics['revenue'] ?? 0,
						'conversions' => $analytics['conversions'] ?? 0,
						'impressions' => $analytics['impressions'] ?? 0,
						'clicks'      => $analytics['clicks'] ?? 0,
						'ctr'         => $analytics['ctr'] ?? 0,
						'avg_order'   => $analytics['avg_order_value'] ?? 0,
					);
				}
			} catch ( Exception $e ) {
				// Analytics unavailable - return empty metrics
				SCD_Log::warning(
					'Failed to load campaign analytics for overview panel',
					array(
						'campaign_id' => $campaign->get_id(),
						'error'       => $e->getMessage(),
					)
				);
			}
		}

		return $metrics;
	}

	/**
	 * Prepare recurring schedule section data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                       Recurring schedule data.
	 */
	private function prepare_recurring_schedule_section( $campaign ) {
		$data = array(
			'enabled' => false,
		);

		// Check if recurring is enabled
		$enable_recurring = $campaign->get_enable_recurring();
		if ( ! $enable_recurring ) {
			return $data;
		}

		$data['enabled'] = true;
		$campaign_id     = $campaign->get_id();

		// Get recurring settings from handler if available
		if ( $this->recurring_handler && $campaign_id ) {
			try {
				$recurring_settings = $this->recurring_handler->get_recurring_settings( $campaign_id );

				if ( $recurring_settings ) {
					// Check if this is a parent or child campaign
					$is_parent         = empty( $recurring_settings['parent_campaign_id'] );
					$data['is_parent'] = $is_parent;

					if ( $is_parent ) {
						// Parent campaign data
						$data['is_active']              = ! empty( $recurring_settings['is_active'] );
						$data['recurrence_pattern']     = $recurring_settings['recurrence_pattern'] ?? '';
						$data['interval']               = $recurring_settings['interval'] ?? 1;
						$data['next_occurrence_date']   = $recurring_settings['next_occurrence_date'] ?? null;
						$data['recurrence_end_date']    = $recurring_settings['recurrence_end_date'] ?? null;
						$data['recurrence_count']       = $recurring_settings['recurrence_count'] ?? 0;
						$data['occurrence_number']      = $recurring_settings['occurrence_number'] ?? 0;
						$data['last_occurrence_status'] = $recurring_settings['last_occurrence_status'] ?? '';
						$data['last_error']             = $recurring_settings['last_error'] ?? '';

						// Format pattern for display
						$pattern_map = array(
							'daily'   => __( 'Daily', 'smart-cycle-discounts' ),
							'weekly'  => __( 'Weekly', 'smart-cycle-discounts' ),
							'monthly' => __( 'Monthly', 'smart-cycle-discounts' ),
						);

						$data['pattern_label'] = $pattern_map[ $data['recurrence_pattern'] ] ?? ucfirst( $data['recurrence_pattern'] );

						// Format next occurrence
						if ( $data['next_occurrence_date'] ) {
							$next_timestamp                    = strtotime( $data['next_occurrence_date'] );
							$data['next_occurrence_formatted'] = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_timestamp );
							$data['next_occurrence_relative']  = human_time_diff( current_time( 'timestamp' ), $next_timestamp );
						}

						// Format end date
						if ( $data['recurrence_end_date'] ) {
							$end_timestamp                    = strtotime( $data['recurrence_end_date'] );
							$data['recurrence_end_formatted'] = wp_date( get_option( 'date_format' ), $end_timestamp );
						}

						// Get child campaigns count
						$data['child_campaigns_count'] = $this->get_child_campaigns_count( $campaign_id );
					} else {
						// Child campaign data
						$data['parent_campaign_id'] = $recurring_settings['parent_campaign_id'];
						$data['occurrence_number']  = $recurring_settings['occurrence_number'] ?? 0;

						// Get parent campaign name
						$parent = $this->campaign_repository->find( intval( $data['parent_campaign_id'] ) );
						if ( $parent ) {
							$data['parent_campaign_name'] = $parent->get_name();
						}
					}
				}
			} catch ( Exception $e ) {
				// Recurring data unavailable
				SCD_Log::warning(
					'Failed to load recurring settings for overview panel',
					array(
						'campaign_id' => $campaign_id,
						'error'       => $e->getMessage(),
					)
				);
			}
		}

		return $data;
	}

	/**
	 * Get child campaigns count for a parent recurring campaign.
	 *
	 * @since    1.0.0
	 * @param    int $parent_id    Parent campaign ID.
	 * @return   int                 Number of child campaigns.
	 */
	private function get_child_campaigns_count( $parent_id ) {
		$count = 0;

		try {
			// Return 0 for now - child campaign count display is not critical for overview panel
			// If needed in future, implement: $this->recurring_handler->count_child_campaigns( $parent_id );
		} catch ( Exception $e ) {
			SCD_Log::warning(
				'Failed to count child campaigns for overview panel',
				array(
					'parent_id' => $parent_id,
					'error'     => $e->getMessage(),
				)
			);
		}

		return $count;
	}

	/**
	 * Render basic info section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Basic info data.
	 * @return   void
	 */
	public function render_basic_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-basic.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render schedule section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Schedule data.
	 * @return   void
	 */
	public function render_schedule_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-schedule.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render products section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Products data.
	 * @return   void
	 */
	public function render_products_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-products.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render discounts section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Discounts data.
	 * @return   void
	 */
	public function render_discounts_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-discounts.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render performance section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Performance data.
	 * @return   void
	 */
	public function render_performance_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-performance.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render recurring schedule section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Recurring schedule data.
	 * @return   void
	 */
	public function render_recurring_schedule_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-recurring-schedule.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render metrics section.
	 *
	 * @since    1.0.0
	 * @param    array $data    Performance metrics data.
	 * @return   void
	 */
	public function render_metrics_section( $data ) {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-metrics.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}
