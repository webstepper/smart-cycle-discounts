<?php
/**
 * Campaign Summary Service Class
 *
 * Provides formatted campaign summaries and details from wizard step data.
 * Extracts business logic from the Review sidebar for reusability.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
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
 * Campaign Summary Service Class
 *
 * Centralized service for generating campaign summaries from wizard step data.
 * Handles all data formatting, calculations, and display logic.
 *
 * @since 1.0.0
 */
class WSSCD_Campaign_Summary_Service {

	/**
	 * Get campaign name with fallback
	 *
	 * @since  1.0.0
	 * @param  array $basic_data Basic step data.
	 * @return string             Campaign name
	 */
	public function get_campaign_name( $basic_data ) {
		if ( ! empty( $basic_data['name'] ) ) {
			return $basic_data['name'];
		}
		return __( 'Not set', 'smart-cycle-discounts' );
	}

	/**
	 * Get priority with fallback
	 *
	 * @since  1.0.0
	 * @param  array $basic_data Basic step data.
	 * @return int                Priority value
	 */
	public function get_priority( $basic_data ) {
		if ( isset( $basic_data['priority'] ) ) {
			return absint( $basic_data['priority'] );
		}
		return 3; // Default priority
	}

	/**
	 * Get campaign description
	 *
	 * @since  1.0.0
	 * @param  array $basic_data Basic step data.
	 * @return string             Campaign description or empty string
	 */
	public function get_description( $basic_data ) {
		return isset( $basic_data['description'] ) ? $basic_data['description'] : '';
	}

	/**
	 * Get discount display string
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts data.
	 * @return string                 Discount display
	 */
	public function get_discount_display( $discounts_data ) {
		if ( empty( $discounts_data ) ) {
			return __( 'Not set', 'smart-cycle-discounts' );
		}

		$type = isset( $discounts_data['discount_type'] ) ? $discounts_data['discount_type'] : 'percentage';

		// Get value based on discount type
		$value = $this->get_discount_value( $discounts_data, $type );

		// Format based on type
		switch ( $type ) {
			case 'percentage':
				return $value . '%';

			case 'fixed':
				return $this->format_currency( $value );

			case 'tiered':
				return $this->format_tiered_display( $discounts_data );

			case 'bogo':
				return $this->format_bogo_display( $discounts_data );

			case 'spend_threshold':
				return $this->format_spend_threshold_display( $discounts_data );

			default:
				return ucfirst( str_replace( '_', ' ', $type ) );
		}
	}

	/**
	 * Get product count display string
	 *
	 * @since  1.0.0
	 * @param  array $products_data Products data.
	 * @return string                Product count display
	 */
	public function get_product_count( $products_data ) {
		if ( empty( $products_data ) ) {
			return __( 'Not set', 'smart-cycle-discounts' );
		}

		$selection = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';

		switch ( $selection ) {
			case 'all_products':
				return $this->format_all_products_display( $products_data );

			case 'specific_products':
				return $this->format_specific_products_display( $products_data );

			case 'random_products':
				return $this->format_random_products_display( $products_data );

			case 'smart_selection':
				return __( 'Smart Selection', 'smart-cycle-discounts' );

			default:
				return ucfirst( str_replace( '_', ' ', $selection ) );
		}
	}

	/**
	 * Get schedule duration display string
	 *
	 * @since  1.0.0
	 * @param  array $schedule_data Schedule data.
	 * @return string                Duration display
	 */
	public function get_schedule_duration( $schedule_data ) {
		if ( empty( $schedule_data ) ) {
			return __( 'Not set', 'smart-cycle-discounts' );
		}

		// Recurring campaigns
		if ( ! empty( $schedule_data['enable_recurring'] ) || ! empty( $schedule_data['rotation_enabled'] ) ) {
			return __( 'Recurring', 'smart-cycle-discounts' );
		}

		// No end date = ongoing
		if ( empty( $schedule_data['start_date'] ) || empty( $schedule_data['end_date'] ) ) {
			return __( 'No End Date', 'smart-cycle-discounts' );
		}

		// Calculate duration
		return $this->calculate_duration( $schedule_data['start_date'], $schedule_data['end_date'] );
	}

	/**
	 * Get detailed discount information
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts data.
	 * @return array                  Array of detail items
	 */
	public function get_discount_details( $discounts_data ) {
		if ( empty( $discounts_data ) ) {
			return array();
		}

		$details = array();

		// Discount Type and Value (most important - show first)
		$type = isset( $discounts_data['discount_type'] ) ? $discounts_data['discount_type'] : 'percentage';
		$details['discount_type'] = array(
			'label' => __( 'Discount Type', 'smart-cycle-discounts' ),
			'value' => $this->get_discount_type_label( $type ),
		);

		$details['discount_value'] = array(
			'label' => __( 'Discount Value', 'smart-cycle-discounts' ),
			'value' => $this->get_discount_display( $discounts_data ),
		);

		// Usage Limits
		if ( ! empty( $discounts_data['usage_limit_per_customer'] ) ) {
			$details['per_customer_limit'] = array(
				'label' => __( 'Per Customer Limit', 'smart-cycle-discounts' ),
				'value' => $discounts_data['usage_limit_per_customer'],
			);
		}

		if ( ! empty( $discounts_data['total_usage_limit'] ) ) {
			$details['total_usage_limit'] = array(
				'label' => __( 'Total Usage Limit', 'smart-cycle-discounts' ),
				'value' => $discounts_data['total_usage_limit'],
			);
		}

		// Order Requirements
		if ( ! empty( $discounts_data['minimum_order_amount'] ) ) {
			$details['minimum_order'] = array(
				'label' => __( 'Minimum Order', 'smart-cycle-discounts' ),
				'value' => $this->format_currency( $discounts_data['minimum_order_amount'] ),
			);
		}

		if ( ! empty( $discounts_data['minimum_quantity'] ) ) {
			$details['minimum_quantity'] = array(
				'label' => __( 'Minimum Quantity', 'smart-cycle-discounts' ),
				'value' => $discounts_data['minimum_quantity'],
			);
		}

		if ( ! empty( $discounts_data['max_discount_amount'] ) ) {
			$details['maximum_discount'] = array(
				'label' => __( 'Maximum Discount', 'smart-cycle-discounts' ),
				'value' => $this->format_currency( $discounts_data['max_discount_amount'] ),
			);
		}

		// Combination Rules
		$details['stack_with_others'] = array(
			'label' => __( 'Stack with Others', 'smart-cycle-discounts' ),
			'value' => $this->format_boolean( $discounts_data, 'stack_with_others' ),
		);

		$details['allow_coupons'] = array(
			'label' => __( 'Allow Coupons', 'smart-cycle-discounts' ),
			'value' => $this->format_boolean( $discounts_data, 'allow_coupons' ),
		);

		$details['apply_to_sale_items'] = array(
			'label' => __( 'Apply to Sale Items', 'smart-cycle-discounts' ),
			'value' => $this->format_boolean( $discounts_data, 'apply_to_sale_items' ),
		);

		// Lifetime Usage Cap
		if ( ! empty( $discounts_data['lifetime_usage_cap'] ) ) {
			$details['lifetime_usage_cap'] = array(
				'label' => __( 'Lifetime Usage Cap', 'smart-cycle-discounts' ),
				'value' => $discounts_data['lifetime_usage_cap'],
			);
		}

		// Apply To (per item vs cart total)
		if ( ! empty( $discounts_data['apply_to'] ) && 'per_item' !== $discounts_data['apply_to'] ) {
			$apply_to_labels = array(
				'per_item'   => __( 'Each Item', 'smart-cycle-discounts' ),
				'cart_total' => __( 'Cart Total', 'smart-cycle-discounts' ),
				'order_total' => __( 'Order Total', 'smart-cycle-discounts' ),
			);
			$details['apply_to'] = array(
				'label' => __( 'Apply To', 'smart-cycle-discounts' ),
				'value' => isset( $apply_to_labels[ $discounts_data['apply_to'] ] ) ? $apply_to_labels[ $discounts_data['apply_to'] ] : $discounts_data['apply_to'],
			);
		}

		return $details;
	}

	/**
	 * Get badge configuration details
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts data.
	 * @return array                  Array of badge detail items
	 */
	public function get_badge_details( $discounts_data ) {
		$details = array();

		// Check if badge is enabled
		$badge_enabled = isset( $discounts_data['badge_enabled'] ) ? (bool) $discounts_data['badge_enabled'] : true;

		$details['badge_enabled'] = array(
			'label' => __( 'Show Badge', 'smart-cycle-discounts' ),
			'value' => $badge_enabled ? __( 'Yes', 'smart-cycle-discounts' ) : __( 'No', 'smart-cycle-discounts' ),
		);

		// Only show other badge details if enabled
		if ( $badge_enabled ) {
			// Badge text
			$badge_text = isset( $discounts_data['badge_text'] ) ? $discounts_data['badge_text'] : 'auto';
			$details['badge_text'] = array(
				'label' => __( 'Badge Text', 'smart-cycle-discounts' ),
				'value' => 'auto' === $badge_text ? __( 'Auto-generated', 'smart-cycle-discounts' ) : $badge_text,
			);

			// Badge position
			if ( ! empty( $discounts_data['badge_position'] ) ) {
				$position_labels = array(
					'top-left'     => __( 'Top Left', 'smart-cycle-discounts' ),
					'top-right'    => __( 'Top Right', 'smart-cycle-discounts' ),
					'bottom-left'  => __( 'Bottom Left', 'smart-cycle-discounts' ),
					'bottom-right' => __( 'Bottom Right', 'smart-cycle-discounts' ),
				);
				$details['badge_position'] = array(
					'label' => __( 'Badge Position', 'smart-cycle-discounts' ),
					'value' => isset( $position_labels[ $discounts_data['badge_position'] ] ) ? $position_labels[ $discounts_data['badge_position'] ] : $discounts_data['badge_position'],
				);
			}
		}

		return $details;
	}

	/**
	 * Get detailed product information
	 *
	 * @since  1.0.0
	 * @param  array $products_data Products data.
	 * @return array                 Array of detail items
	 */
	public function get_product_details( $products_data ) {
		if ( empty( $products_data ) ) {
			return array();
		}

		$details = array();

		// Selection Type
		$selection = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';
		$details['selection_type'] = array(
			'label' => __( 'Selection Type', 'smart-cycle-discounts' ),
			'value' => ucwords( str_replace( '_', ' ', $selection ) ),
		);

		// Specific Products
		if ( 'specific_products' === $selection && ! empty( $products_data['product_ids'] ) && is_array( $products_data['product_ids'] ) ) {
			$count                      = count( $products_data['product_ids'] );
			$details['selected_products'] = array(
				'label' => __( 'Selected Products', 'smart-cycle-discounts' ),
				/* translators: %d: number of products */
				'value' => sprintf( _n( '%d product', '%d products', $count, 'smart-cycle-discounts' ), $count ),
			);
		}

		// Categories
		if ( ! empty( $products_data['category_ids'] ) && is_array( $products_data['category_ids'] ) ) {
			$category_ids = array_filter(
				$products_data['category_ids'],
				function ( $id ) {
					return 'all' !== $id;
				}
			);

			if ( ! empty( $category_ids ) ) {
				$count                        = count( $category_ids );
				$details['selected_categories'] = array(
					'label' => __( 'Selected Categories', 'smart-cycle-discounts' ),
					/* translators: %d: number of categories */
					'value' => sprintf( _n( '%d category', '%d categories', $count, 'smart-cycle-discounts' ), $count ),
				);
			}
		}

		// Conditions
		if ( ! empty( $products_data['conditions'] ) && is_array( $products_data['conditions'] ) ) {
			$count                 = count( $products_data['conditions'] );
			$details['conditions'] = array(
				'label' => __( 'Conditions', 'smart-cycle-discounts' ),
				/* translators: %d: number of conditions */
				'value' => sprintf( _n( '%d condition', '%d conditions', $count, 'smart-cycle-discounts' ), $count ),
			);

			// Conditions Logic (AND/OR)
			if ( ! empty( $products_data['conditions_logic'] ) ) {
				$logic_labels = array(
					'and' => __( 'All conditions (AND)', 'smart-cycle-discounts' ),
					'or'  => __( 'Any condition (OR)', 'smart-cycle-discounts' ),
				);
				$details['conditions_logic'] = array(
					'label' => __( 'Match', 'smart-cycle-discounts' ),
					'value' => isset( $logic_labels[ $products_data['conditions_logic'] ] ) ? $logic_labels[ $products_data['conditions_logic'] ] : $products_data['conditions_logic'],
				);
			}
		}

		// Smart Selection Criteria
		if ( 'smart_selection' === $selection && ! empty( $products_data['smart_criteria'] ) ) {
			$criteria_labels = array(
				'best_sellers'   => __( 'Best Sellers', 'smart-cycle-discounts' ),
				'low_stock'      => __( 'Low Stock', 'smart-cycle-discounts' ),
				'new_arrivals'   => __( 'New Arrivals', 'smart-cycle-discounts' ),
				'slow_movers'    => __( 'Slow Movers', 'smart-cycle-discounts' ),
				'high_margin'    => __( 'High Margin', 'smart-cycle-discounts' ),
			);
			$details['smart_criteria'] = array(
				'label' => __( 'Smart Criteria', 'smart-cycle-discounts' ),
				'value' => isset( $criteria_labels[ $products_data['smart_criteria'] ] ) ? $criteria_labels[ $products_data['smart_criteria'] ] : ucwords( str_replace( '_', ' ', $products_data['smart_criteria'] ) ),
			);
		}

		return $details;
	}

	/**
	 * Get detailed schedule information
	 *
	 * @since  1.0.0
	 * @param  array $schedule_data Schedule data.
	 * @return array                 Array of detail items
	 */
	public function get_schedule_details( $schedule_data ) {
		if ( empty( $schedule_data ) ) {
			return array();
		}

		$details = array();

		// Start Type
		$start_type = isset( $schedule_data['start_type'] ) ? $schedule_data['start_type'] : 'immediate';
		$details['start_type'] = array(
			'label' => __( 'Start Type', 'smart-cycle-discounts' ),
			'value' => 'immediate' === $start_type ? __( 'Immediately', 'smart-cycle-discounts' ) : __( 'Scheduled', 'smart-cycle-discounts' ),
		);

		// Start Date and Time (only if scheduled)
		if ( 'scheduled' === $start_type && ! empty( $schedule_data['start_date'] ) ) {
			$start_time = isset( $schedule_data['start_time'] ) ? $schedule_data['start_time'] : '00:00';
			$details['start_date'] = array(
				'label' => __( 'Start Date', 'smart-cycle-discounts' ),
				'value' => date_i18n( get_option( 'date_format' ), strtotime( $schedule_data['start_date'] ) ) . ' ' . $this->format_time( $start_time ),
			);
		}

		// End Date and Time
		if ( ! empty( $schedule_data['end_date'] ) ) {
			$end_time = isset( $schedule_data['end_time'] ) ? $schedule_data['end_time'] : '23:59';
			$details['end_date'] = array(
				'label' => __( 'End Date', 'smart-cycle-discounts' ),
				'value' => date_i18n( get_option( 'date_format' ), strtotime( $schedule_data['end_date'] ) ) . ' ' . $this->format_time( $end_time ),
			);
		} else {
			$details['end_date'] = array(
				'label' => __( 'End Date', 'smart-cycle-discounts' ),
				'value' => __( 'No end date (ongoing)', 'smart-cycle-discounts' ),
			);
		}

		// Timezone
		$timezone = isset( $schedule_data['timezone'] ) ? $schedule_data['timezone'] : wp_timezone_string();
		$details['timezone'] = array(
			'label' => __( 'Timezone', 'smart-cycle-discounts' ),
			'value' => $timezone,
		);

		// Recurring Configuration
		$enable_recurring = ! empty( $schedule_data['enable_recurring'] );
		$details['recurring'] = array(
			'label' => __( 'Recurring', 'smart-cycle-discounts' ),
			'value' => $enable_recurring ? __( 'Yes', 'smart-cycle-discounts' ) : __( 'No', 'smart-cycle-discounts' ),
		);

		// Recurring Details (if enabled)
		if ( $enable_recurring ) {
			$pattern  = isset( $schedule_data['recurrence_pattern'] ) ? $schedule_data['recurrence_pattern'] : 'daily';
			$interval = isset( $schedule_data['recurrence_interval'] ) ? absint( $schedule_data['recurrence_interval'] ) : 1;

			$pattern_labels = array(
				'daily'   => _n( 'day', 'days', $interval, 'smart-cycle-discounts' ),
				'weekly'  => _n( 'week', 'weeks', $interval, 'smart-cycle-discounts' ),
				'monthly' => _n( 'month', 'months', $interval, 'smart-cycle-discounts' ),
			);
			$pattern_label = isset( $pattern_labels[ $pattern ] ) ? $pattern_labels[ $pattern ] : $pattern;

			$details['recurrence_pattern'] = array(
				'label' => __( 'Repeat Every', 'smart-cycle-discounts' ),
				'value' => sprintf( '%d %s', $interval, $pattern_label ),
			);

			// Weekly days
			if ( 'weekly' === $pattern && ! empty( $schedule_data['recurrence_days'] ) && is_array( $schedule_data['recurrence_days'] ) ) {
				$day_labels = array(
					'mon' => __( 'Mon', 'smart-cycle-discounts' ),
					'tue' => __( 'Tue', 'smart-cycle-discounts' ),
					'wed' => __( 'Wed', 'smart-cycle-discounts' ),
					'thu' => __( 'Thu', 'smart-cycle-discounts' ),
					'fri' => __( 'Fri', 'smart-cycle-discounts' ),
					'sat' => __( 'Sat', 'smart-cycle-discounts' ),
					'sun' => __( 'Sun', 'smart-cycle-discounts' ),
				);
				$selected_days = array();
				foreach ( $schedule_data['recurrence_days'] as $day ) {
					if ( isset( $day_labels[ $day ] ) ) {
						$selected_days[] = $day_labels[ $day ];
					}
				}
				if ( ! empty( $selected_days ) ) {
					$details['recurrence_days'] = array(
						'label' => __( 'On Days', 'smart-cycle-discounts' ),
						'value' => implode( ', ', $selected_days ),
					);
				}
			}

			// End condition
			$end_type = isset( $schedule_data['recurrence_end_type'] ) ? $schedule_data['recurrence_end_type'] : 'never';
			if ( 'after' === $end_type && ! empty( $schedule_data['recurrence_count'] ) ) {
				$details['recurrence_ends'] = array(
					'label' => __( 'Ends After', 'smart-cycle-discounts' ),
					/* translators: %d: number of occurrences */
					'value' => sprintf( _n( '%d occurrence', '%d occurrences', $schedule_data['recurrence_count'], 'smart-cycle-discounts' ), $schedule_data['recurrence_count'] ),
				);
			} elseif ( 'on' === $end_type && ! empty( $schedule_data['recurrence_end_date'] ) ) {
				$details['recurrence_ends'] = array(
					'label' => __( 'Ends On', 'smart-cycle-discounts' ),
					'value' => date_i18n( get_option( 'date_format' ), strtotime( $schedule_data['recurrence_end_date'] ) ),
				);
			} elseif ( 'never' === $end_type ) {
				$details['recurrence_ends'] = array(
					'label' => __( 'Ends', 'smart-cycle-discounts' ),
					'value' => __( 'Never', 'smart-cycle-discounts' ),
				);
			}
		}

		return $details;
	}

	/**
	 * Format time for display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $time Time in H:i format.
	 * @return string        Formatted time
	 */
	private function format_time( $time ) {
		if ( empty( $time ) || '--:--' === $time ) {
			return '';
		}
		return date_i18n( get_option( 'time_format' ), strtotime( 'today ' . $time ) );
	}

	/**
	 * Get discount value based on type
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array  $discounts_data Discounts data.
	 * @param  string $type           Discount type.
	 * @return string                  Discount value
	 */
	private function get_discount_value( $discounts_data, $type ) {
		// Wizard stores percentage/fixed in separate fields before entity transformation
		if ( 'percentage' === $type && isset( $discounts_data['discount_value_percentage'] ) ) {
			return $discounts_data['discount_value_percentage'];
		}

		if ( 'fixed' === $type && isset( $discounts_data['discount_value_fixed'] ) ) {
			return $discounts_data['discount_value_fixed'];
		}

		// Fallback for already-transformed data
		if ( isset( $discounts_data['discount_value'] ) ) {
			return $discounts_data['discount_value'];
		}

		return '0';
	}

	/**
	 * Format tiered discount display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $discounts_data Discounts data.
	 * @return string                 Formatted display
	 */
	private function format_tiered_display( $discounts_data ) {
		$tier_count = isset( $discounts_data['tiers'] ) && is_array( $discounts_data['tiers'] ) ? count( $discounts_data['tiers'] ) : 0;
		/* translators: %d: number of pricing tiers */
		return sprintf( _n( '%d Tier', '%d Tiers', $tier_count, 'smart-cycle-discounts' ), $tier_count );
	}

	/**
	 * Format BOGO discount display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $discounts_data Discounts data.
	 * @return string                 Formatted display
	 */
	private function format_bogo_display( $discounts_data ) {
		$bogo_config = isset( $discounts_data['bogo_config'] ) && is_array( $discounts_data['bogo_config'] ) ? $discounts_data['bogo_config'] : array();
		$buy         = isset( $bogo_config['buy_quantity'] ) ? $bogo_config['buy_quantity'] : 1;
		$get         = isset( $bogo_config['get_quantity'] ) ? $bogo_config['get_quantity'] : 1;
		/* translators: 1: quantity to buy, 2: quantity to get free */
		return sprintf( __( 'Buy %1$d Get %2$d', 'smart-cycle-discounts' ), $buy, $get );
	}

	/**
	 * Format spend threshold display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $discounts_data Discounts data.
	 * @return string                 Formatted display
	 */
	private function format_spend_threshold_display( $discounts_data ) {
		$threshold = isset( $discounts_data['spend_threshold'] ) ? $discounts_data['spend_threshold'] : 0;
		/* translators: %s: formatted currency amount */
		return sprintf( __( 'Spend %s', 'smart-cycle-discounts' ), $this->format_currency( $threshold ) );
	}

	/**
	 * Format all products display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $products_data Products data.
	 * @return string                Formatted display
	 */
	private function format_all_products_display( $products_data ) {
		// If specific categories are selected, show count
		if ( ! empty( $products_data['category_ids'] ) && is_array( $products_data['category_ids'] ) ) {
			$category_ids = array_filter(
				$products_data['category_ids'],
				function ( $id ) {
					return 'all' !== $id;
				}
			);

			if ( ! empty( $category_ids ) ) {
				$count = count( $category_ids );
				/* translators: %d: number of product categories */
				return sprintf( _n( 'All Products in %d Category', 'All Products in %d Categories', $count, 'smart-cycle-discounts' ), $count );
			}
		}
		return __( 'All Products', 'smart-cycle-discounts' );
	}

	/**
	 * Format specific products display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $products_data Products data.
	 * @return string                Formatted display
	 */
	private function format_specific_products_display( $products_data ) {
		if ( ! empty( $products_data['product_ids'] ) && is_array( $products_data['product_ids'] ) ) {
			$count = count( $products_data['product_ids'] );
			/* translators: %d: number of products */
			return sprintf( _n( '%d Product', '%d Products', $count, 'smart-cycle-discounts' ), $count );
		}
		return __( 'Specific Products', 'smart-cycle-discounts' );
	}

	/**
	 * Format random products display
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $products_data Products data.
	 * @return string                Formatted display
	 */
	private function format_random_products_display( $products_data ) {
		if ( ! empty( $products_data['random_count'] ) ) {
			/* translators: %d: number of random products */
			return sprintf( __( '%d Random Products', 'smart-cycle-discounts' ), intval( $products_data['random_count'] ) );
		}
		return __( 'Random Products', 'smart-cycle-discounts' );
	}

	/**
	 * Calculate duration between two dates
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @return string              Duration display
	 */
	private function calculate_duration( $start_date, $end_date ) {
		try {
			$start    = new DateTime( $start_date );
			$end      = new DateTime( $end_date );
			$interval = $start->diff( $end );

			if ( $interval->days > 0 ) {
				/* translators: %d: number of days */
				return sprintf( _n( '%d Day', '%d Days', $interval->days, 'smart-cycle-discounts' ), $interval->days );
			} elseif ( $interval->h > 0 ) {
				/* translators: %d: number of hours */
				return sprintf( _n( '%d Hour', '%d Hours', $interval->h, 'smart-cycle-discounts' ), $interval->h );
			} else {
				return __( 'Less than 1 hour', 'smart-cycle-discounts' );
			}
		} catch ( Exception $e ) {
			return __( 'Invalid dates', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Format currency value
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $value Value to format.
	 * @return string        Formatted currency
	 */
	private function format_currency( $value ) {
		$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		return $symbol . $value;
	}

	/**
	 * Format boolean value
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array  $data Data array.
	 * @param  string $key  Key to check.
	 * @return string        'Yes' or 'No'
	 */
	private function format_boolean( $data, $key ) {
		return isset( $data[ $key ] ) && $data[ $key ] ? __( 'Yes', 'smart-cycle-discounts' ) : __( 'No', 'smart-cycle-discounts' );
	}

	/**
	 * Get human-readable discount type label
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $type Discount type identifier.
	 * @return string        Human-readable label
	 */
	private function get_discount_type_label( $type ) {
		$labels = array(
			'percentage'      => __( 'Percentage Off', 'smart-cycle-discounts' ),
			'fixed'           => __( 'Fixed Amount Off', 'smart-cycle-discounts' ),
			'tiered'          => __( 'Tiered Pricing', 'smart-cycle-discounts' ),
			'bogo'            => __( 'Buy One Get One', 'smart-cycle-discounts' ),
			'spend_threshold' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
	}
}
