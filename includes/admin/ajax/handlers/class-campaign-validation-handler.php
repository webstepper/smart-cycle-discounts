<?php
/**
 * Campaign Validation Handler
 *
 * Comprehensive validation for schedule conflicts, product conflicts, and business rules.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign Validation Handler Class
 *
 * Performs comprehensive validation checks for campaigns during wizard review.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */
class SCD_Campaign_Validation_Handler {

	/**
	 * Validate campaign configuration.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_data    Campaign data from all steps.
	 * @return   array                      Validation results.
	 */
	public function validate( $campaign_data ) {
		$errors   = array();
		$warnings = array();

		// Schedule validation
		$schedule_validation = $this->_validate_schedule( $campaign_data );
		$errors              = array_merge( $errors, $schedule_validation['errors'] );
		$warnings            = array_merge( $warnings, $schedule_validation['warnings'] );

		// Product validation
		$product_validation = $this->_validate_products( $campaign_data );
		$errors             = array_merge( $errors, $product_validation['errors'] );
		$warnings           = array_merge( $warnings, $product_validation['warnings'] );

		// Business rules validation
		$business_validation = $this->_validate_business_rules( $campaign_data );
		$errors              = array_merge( $errors, $business_validation['errors'] );
		$warnings            = array_merge( $warnings, $business_validation['warnings'] );

		// Discount edge cases validation
		$edge_case_validation = $this->_validate_discount_edge_cases( $campaign_data );
		$errors               = array_merge( $errors, $edge_case_validation['errors'] );
		$warnings             = array_merge( $warnings, $edge_case_validation['warnings'] );

		return array(
			'is_valid'       => empty( $errors ),
			'errors'         => $errors,
			'warnings'       => $warnings,
			'total_errors'   => count( $errors ),
			'total_warnings' => count( $warnings ),
		);
	}

	/**
	 * Validate schedule configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign_data    Campaign data.
	 * @return   array                      Validation results.
	 */
	private function _validate_schedule( $campaign_data ) {
		$errors   = array();
		$warnings = array();

		$schedule = isset( $campaign_data['schedule'] ) ? $campaign_data['schedule'] : array();

		if ( empty( $schedule ) ) {
			return array(
				'errors'   => $errors,
				'warnings' => $warnings,
			);
		}

		$start_date        = isset( $schedule['start_date'] ) ? $schedule['start_date'] : '';
		$end_date          = isset( $schedule['end_date'] ) ? $schedule['end_date'] : '';
		$start_type        = isset( $schedule['start_type'] ) ? $schedule['start_type'] : 'immediate';
		$start_time        = isset( $schedule['start_time'] ) ? $schedule['start_time'] : '00:00';
		$end_time          = isset( $schedule['end_time'] ) ? $schedule['end_time'] : '23:59';
		$campaign_timezone = isset( $schedule['timezone'] ) ? $schedule['timezone'] : wp_timezone_string();

		// Convert to timestamps for comparison using proper timezone handling
		// CRITICAL: Combine date + time fields for accurate comparison
		$timezone = new DateTimeZone( $campaign_timezone );
		$now_dt   = new DateTime( 'now', $timezone );
		$now      = $now_dt->getTimestamp();

		if ( ! empty( $start_date ) ) {
			$start_dt = scd_combine_date_time( $start_date, $start_time, $campaign_timezone );
			if ( $start_dt ) {
				$start_timestamp = $start_dt->getTimestamp();
			} else {
				// Invalid date/time - use now as fallback
				$start_timestamp = $now;
			}
		} else {
			$start_timestamp = $now;
		}

		if ( ! empty( $end_date ) ) {
			$end_dt = scd_combine_date_time( $end_date, $end_time, $campaign_timezone );
			if ( $end_dt ) {
				$end_timestamp = $end_dt->getTimestamp();
			} else {
				// Invalid date/time - use 0 as fallback (no end time)
				$end_timestamp = 0;
			}
		} else {
			$end_timestamp = 0;
		}

		// Check: Starting in the past
		if ( 'immediate' !== $start_type && $start_timestamp < $now ) {
			$warnings[] = array(
				'code'     => 'schedule_past_start',
				'severity' => 'medium',
				'title'    => __( 'Past Start Date', 'smart-cycle-discounts' ),
				'message'  => __( 'Campaign start date is in the past. It will start immediately when launched.', 'smart-cycle-discounts' ),
				'step'     => 'schedule',
				'actions'  => array(
					array(
						'label' => __( 'Update Schedule', 'smart-cycle-discounts' ),
						'step'  => 'schedule',
					),
				),
			);
		}

		// Check: Ending before starting
		if ( $end_timestamp > 0 && $end_timestamp <= $start_timestamp ) {
			$errors[] = array(
				'code'     => 'schedule_end_before_start',
				'severity' => 'critical',
				'title'    => __( 'Invalid Schedule', 'smart-cycle-discounts' ),
				'message'  => __( 'Campaign end date must be after start date.', 'smart-cycle-discounts' ),
				'step'     => 'schedule',
			);
		}

		// Check: Duration too short (< 1 hour)
		if ( $end_timestamp > 0 ) {
			$duration_hours = ( $end_timestamp - $start_timestamp ) / 3600;

			if ( $duration_hours < 1 ) {
				$warnings[] = array(
					'code'     => 'schedule_too_short',
					'severity' => 'medium',
					'title'    => __( 'Very Short Duration', 'smart-cycle-discounts' ),
					'message'  => sprintf(
						__( 'Campaign duration is only %d minutes. This may not give customers enough time to respond.', 'smart-cycle-discounts' ),
						round( $duration_hours * 60 )
					),
					'step'     => 'schedule',
					'actions'  => array(
						array(
							'label' => __( 'Extend Duration', 'smart-cycle-discounts' ),
							'step'  => 'schedule',
						),
					),
				);
			}

			// Check: Duration too long (> 1 year)
			$duration_days = $duration_hours / 24;

			if ( $duration_days > 365 ) {
				$warnings[] = array(
					'code'     => 'schedule_too_long',
					'severity' => 'low',
					'title'    => __( 'Very Long Duration', 'smart-cycle-discounts' ),
					'message'  => sprintf(
						__( 'Campaign runs for %d days. Consider breaking into shorter campaigns for better control.', 'smart-cycle-discounts' ),
						round( $duration_days )
					),
					'step'     => 'schedule',
					'actions'  => array(
						array(
							'label' => __( 'Adjust Schedule', 'smart-cycle-discounts' ),
							'step'  => 'schedule',
						),
					),
				);
			}
		}

		// Check: Overlapping campaigns
		$overlapping = $this->_check_schedule_overlap( $start_timestamp, $end_timestamp );

		if ( ! empty( $overlapping ) ) {
			$warnings[] = array(
				'code'     => 'schedule_overlap',
				'severity' => 'high',
				'title'    => __( 'Schedule Overlap', 'smart-cycle-discounts' ),
				'message'  => sprintf(
					__( 'This campaign overlaps with %d other active campaign(s).', 'smart-cycle-discounts' ),
					count( $overlapping )
				),
				'details'  => $overlapping,
				'step'     => 'schedule',
				'actions'  => array(
					array(
						'label' => __( 'Adjust Schedule', 'smart-cycle-discounts' ),
						'step'  => 'schedule',
					),
					array(
						'label' => __( 'Change Priority', 'smart-cycle-discounts' ),
						'step'  => 'basic',
					),
				),
			);
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate product selection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign_data    Campaign data.
	 * @return   array                      Validation results.
	 */
	private function _validate_products( $campaign_data ) {
		$errors   = array();
		$warnings = array();

		$products = isset( $campaign_data['products'] ) ? $campaign_data['products'] : array();

		if ( empty( $products ) ) {
			return array(
				'errors'   => $errors,
				'warnings' => $warnings,
			);
		}

		$selection_type = isset( $products['product_selection_type'] ) ? $products['product_selection_type'] : '';
		$product_ids    = isset( $products['product_ids'] ) ? $products['product_ids'] : array();
		$category_ids   = isset( $products['category_ids'] ) ? $products['category_ids'] : array();

		// Check: No products selected
		if ( 'specific_products' === $selection_type && empty( $product_ids ) ) {
			$errors[] = array(
				'code'     => 'no_products_selected',
				'severity' => 'critical',
				'title'    => __( 'No Products Selected', 'smart-cycle-discounts' ),
				'message'  => __( 'You must select at least one product for this campaign.', 'smart-cycle-discounts' ),
				'step'     => 'products',
			);
		}

		if ( in_array( $selection_type, array( 'categories', 'random_products' ), true ) && empty( $category_ids ) ) {
			$errors[] = array(
				'code'     => 'no_categories_selected',
				'severity' => 'critical',
				'title'    => __( 'No Categories Selected', 'smart-cycle-discounts' ),
				'message'  => __( 'You must select at least one category for this campaign.', 'smart-cycle-discounts' ),
				'step'     => 'products',
			);
		}

		// Check: Products already on sale
		$sale_products = $this->_get_sale_products( $selection_type, $product_ids, $category_ids );

		if ( ! empty( $sale_products ) ) {
			$warnings[] = array(
				'code'     => 'products_on_sale',
				'severity' => 'medium',
				'title'    => __( 'Products Already on Sale', 'smart-cycle-discounts' ),
				'message'  => sprintf(
					__( '%d product(s) are already on sale. Campaign discount may not apply.', 'smart-cycle-discounts' ),
					count( $sale_products )
				),
				'step'     => 'products',
				'actions'  => array(
					array(
						'label' => __( 'Review Products', 'smart-cycle-discounts' ),
						'step'  => 'products',
					),
					array(
						'label' => __( 'Enable Sale Discount', 'smart-cycle-discounts' ),
						'step'  => 'discounts',
					),
				),
			);
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate business rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign_data    Campaign data.
	 * @return   array                      Validation results.
	 */
	private function _validate_business_rules( $campaign_data ) {
		$errors   = array();
		$warnings = array();

		// Check: Campaign name quality
		$basic = isset( $campaign_data['basic'] ) ? $campaign_data['basic'] : array();
		$name  = isset( $basic['name'] ) ? trim( $basic['name'] ) : '';

		if ( ! empty( $name ) ) {
			$generic_names = array( 'test', 'new campaign', 'campaign', 'discount', 'sale', 'temp', 'untitled' );
			$name_lower    = strtolower( $name );

			foreach ( $generic_names as $generic ) {
				if ( $name_lower === $generic || strpos( $name_lower, $generic ) === 0 ) {
					$warnings[] = array(
						'code'     => 'generic_campaign_name',
						'severity' => 'low',
						'title'    => __( 'Generic Campaign Name', 'smart-cycle-discounts' ),
						'message'  => sprintf(
							__( 'Campaign name "%s" is too generic. Use a descriptive name for easier management.', 'smart-cycle-discounts' ),
							$name
						),
						'step'     => 'basic',
						'actions'  => array(
							array(
								'label' => __( 'Edit Name', 'smart-cycle-discounts' ),
								'step'  => 'basic',
							),
						),
					);
					break;
				}
			}
		}

		// Check: Discount value validation
		$discounts     = isset( $campaign_data['discounts'] ) ? $campaign_data['discounts'] : array();
		$discount_type = isset( $discounts['discount_type'] ) ? $discounts['discount_type'] : 'percentage';

		// CRITICAL FIX: Better discount value extraction with proper fallback chain
		// In edit mode, the Change Tracker returns wizard-specific fields (discount_value_percentage/fixed)
		// We need to check multiple possible field locations
		$discount_value = 0;

		if ( 'percentage' === $discount_type ) {
			// Check wizard-specific field first (most common in edit mode)
			if ( isset( $discounts['discount_value_percentage'] ) && '' !== $discounts['discount_value_percentage'] ) {
				$discount_value = floatval( $discounts['discount_value_percentage'] );
			} elseif ( isset( $discounts['discount_value'] ) && '' !== $discounts['discount_value'] ) {
				// Fallback to entity field (less common but possible)
				$discount_value = floatval( $discounts['discount_value'] );
			}

			// Debug logging for discount value extraction
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Validation] Discount value extraction for percentage type:' );
				error_log( '[Validation] - discount_value_percentage field: ' . ( isset( $discounts['discount_value_percentage'] ) ? $discounts['discount_value_percentage'] : 'not set' ) );
				error_log( '[Validation] - discount_value field: ' . ( isset( $discounts['discount_value'] ) ? $discounts['discount_value'] : 'not set' ) );
				error_log( '[Validation] - Final extracted value: ' . $discount_value );
			}
		} elseif ( 'fixed' === $discount_type ) {
			// Check wizard-specific field first
			if ( isset( $discounts['discount_value_fixed'] ) && '' !== $discounts['discount_value_fixed'] ) {
				$discount_value = floatval( $discounts['discount_value_fixed'] );
			} elseif ( isset( $discounts['discount_value'] ) && '' !== $discounts['discount_value'] ) {
				// Fallback to entity field
				$discount_value = floatval( $discounts['discount_value'] );
			}

			// Debug logging for discount value extraction
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Validation] Discount value extraction for fixed type:' );
				error_log( '[Validation] - discount_value_fixed field: ' . ( isset( $discounts['discount_value_fixed'] ) ? $discounts['discount_value_fixed'] : 'not set' ) );
				error_log( '[Validation] - discount_value field: ' . ( isset( $discounts['discount_value'] ) ? $discounts['discount_value'] : 'not set' ) );
				error_log( '[Validation] - Final extracted value: ' . $discount_value );
			}
		}

		// Only run percentage validations for percentage discounts
		if ( 'percentage' === $discount_type ) {
			// Check: Discount > 70% but < 100% (high discount warning)
			// Note: 100% is handled as critical error in edge case validation
			if ( $discount_value > 70 && $discount_value < 100 ) {
				$warnings[] = array(
					'code'     => 'discount_very_high',
					'severity' => 'high',
					'title'    => __( 'Very High Discount', 'smart-cycle-discounts' ),
					'message'  => sprintf(
						__( '%d%% discount is unusually high. Verify this is intentional and won\'t cause profit loss.', 'smart-cycle-discounts' ),
						$discount_value
					),
					'step'     => 'discounts',
					'actions'  => array(
						array(
							'label' => __( 'Adjust Discount', 'smart-cycle-discounts' ),
							'step'  => 'discounts',
						),
					),
				);
			}

			// Check: Discount < 5% (not compelling)
			if ( $discount_value > 0 && $discount_value < 5 ) {
				$warnings[] = array(
					'code'     => 'discount_too_low',
					'severity' => 'medium',
					'title'    => __( 'Low Discount Value', 'smart-cycle-discounts' ),
					'message'  => sprintf(
						__( '%d%% discount may not be compelling enough to drive conversions. Consider increasing it.', 'smart-cycle-discounts' ),
						$discount_value
					),
					'step'     => 'discounts',
					'actions'  => array(
						array(
							'label' => __( 'Increase Discount', 'smart-cycle-discounts' ),
							'step'  => 'discounts',
						),
					),
				);
			}

			// Check: Discount = 0
			// CRITICAL FIX: Only flag as error if truly zero after checking all possible sources
			if ( 0 === $discount_value || 0.0 === $discount_value ) {
				$errors[] = array(
					'code'     => 'discount_zero',
					'severity' => 'critical',
					'title'    => __( 'No Discount Value', 'smart-cycle-discounts' ),
					'message'  => __( 'Discount value cannot be zero. Set a valid discount amount.', 'smart-cycle-discounts' ),
					'step'     => 'discounts',
				);
			}
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Check for schedule overlaps with active campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $start_timestamp    Start timestamp.
	 * @param    int $end_timestamp      End timestamp.
	 * @return   array                        Array of overlapping campaigns.
	 */
	private function _check_schedule_overlap( $start_timestamp, $end_timestamp ) {
		return SCD_Campaign_Schedule_Validator::check_schedule_overlap(
			$start_timestamp,
			$end_timestamp,
			true // Include full campaign details.
		);
	}


	/**
	 * Get products currently on sale.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $selection_type   Selection type.
	 * @param    array  $product_ids      Product IDs.
	 * @param    array  $category_ids     Category IDs.
	 * @return   array                       Array of product IDs on sale.
	 */
	private function _get_sale_products( $selection_type, $product_ids, $category_ids ) {
		// Get products based on selection
		$products = $this->_get_selected_products( $selection_type, $product_ids, $category_ids );

		if ( empty( $products ) ) {
			return array();
		}

		$sale_products = array();

		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product && $product->is_on_sale() ) {
				$sale_products[] = $product_id;
			}
		}

		return $sale_products;
	}

	/**
	 * Get selected product IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $selection_type   Selection type.
	 * @param    array  $product_ids      Product IDs.
	 * @param    array  $category_ids     Category IDs.
	 * @return   array                       Array of product IDs.
	 */
	private function _get_selected_products( $selection_type, $product_ids, $category_ids ) {
		if ( 'all_products' === $selection_type ) {
			$args = array(
				'post_type'      => 'product',
				'posts_per_page' => 100, // Limit for performance
				'post_status'    => 'publish',
				'fields'         => 'ids',
			);

			return get_posts( $args );
		} elseif ( 'specific_products' === $selection_type ) {
			return is_array( $product_ids ) ? array_map( 'intval', $product_ids ) : array();
		} elseif ( in_array( $selection_type, array( 'categories', 'random_products' ), true ) ) {
			if ( empty( $category_ids ) || ! is_array( $category_ids ) ) {
				return array();
			}

			$args = array(
				'post_type'      => 'product',
				'posts_per_page' => 100, // Limit for performance
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

		return array();
	}

	/**
	 * Validate discount edge cases.
	 *
	 * Checks for problematic discount configurations that could lead to:
	 * - Products given away for free
	 * - Inconsistent discount experiences
	 * - Revenue loss
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign_data    Campaign data.
	 * @return   array                      Validation results.
	 */
	private function _validate_discount_edge_cases( $campaign_data ) {
		$errors   = array();
		$warnings = array();

		$discounts = isset( $campaign_data['discounts'] ) ? $campaign_data['discounts'] : array();
		$products  = isset( $campaign_data['products'] ) ? $campaign_data['products'] : array();

		if ( empty( $discounts ) || empty( $products ) ) {
			return array(
				'errors'   => $errors,
				'warnings' => $warnings,
			);
		}

		$discount_type = isset( $discounts['discount_type'] ) ? $discounts['discount_type'] : '';

		// W1: Fixed discount exceeds product prices
		if ( 'fixed' === $discount_type ) {
			// CRITICAL FIX: Check wizard-specific field first with proper empty string handling
			$discount_value = 0;
			if ( isset( $discounts['discount_value_fixed'] ) && '' !== $discounts['discount_value_fixed'] ) {
				$discount_value = floatval( $discounts['discount_value_fixed'] );
			} elseif ( isset( $discounts['discount_value'] ) && '' !== $discounts['discount_value'] ) {
				$discount_value = floatval( $discounts['discount_value'] );
			}

			if ( $discount_value > 0 ) {
				$product_prices = $this->_get_product_prices( $products );

				if ( ! empty( $product_prices ) ) {
					$min_price   = min( $product_prices );
					$max_price   = max( $product_prices );
					$total_count = count( $product_prices );

					// Count products that will become free or nearly free
					$affected_count  = 0;
					$affected_prices = array();
					foreach ( $product_prices as $price ) {
						if ( $price <= $discount_value ) {
							++$affected_count;
							$affected_prices[] = $price;
						}
					}

					if ( $affected_count > 0 ) {
						$percentage_affected = ( $affected_count / $total_count ) * 100;

						// Critical if >= 50% affected, High if >= 25%
						if ( $percentage_affected >= 25 ) {
							$severity           = $percentage_affected >= 50 ? 'critical' : 'high';
							$max_affected_price = ! empty( $affected_prices ) ? max( $affected_prices ) : 0;

							$warnings[] = array(
								'code'     => 'fixed_discount_exceeds_price',
								'severity' => $severity,
								'title'    => __( 'Fixed Discount Makes Products Free', 'smart-cycle-discounts' ),
								'message'  => sprintf(
									__( 'Your %1$s fixed discount exceeds the price of %2$d products (%3$d%% of selection). These products will be given away for FREE.', 'smart-cycle-discounts' ),
									$this->_format_price_plain( $discount_value ),
									$affected_count,
									round( $percentage_affected )
								),
								'details'  => array(
									'discount_amount'      => $discount_value,
									'affected_products'    => $affected_count,
									'total_products'       => $total_count,
									'percentage_affected'  => round( $percentage_affected, 1 ),
									'price_range_affected' => $this->_format_price_plain( $min_price ) . ' - ' . $this->_format_price_plain( $max_affected_price ),
								),
								'step'     => 'discounts',
								'actions'  => array(
									array(
										'label' => sprintf( __( 'Reduce to %s', 'smart-cycle-discounts' ), $this->_format_price_plain( $min_price - 0.01 ) ),
										'step'  => 'discounts',
									),
									array(
										'label' => __( 'Switch to Percentage', 'smart-cycle-discounts' ),
										'step'  => 'discounts',
									),
									array(
										'label' => __( 'Review Products', 'smart-cycle-discounts' ),
										'step'  => 'products',
									),
								),
							);
						}
					}

					// W3: Wide price range with fixed discount
					if ( $min_price > 0 && $max_price > 0 ) {
						$price_variance = ( $max_price - $min_price ) / $min_price;

						// Warn if price range is very wide (5x or more difference)
						if ( $price_variance >= 5.0 && $discount_value > 0 ) {
							$min_discount_percentage = ( $discount_value / $max_price ) * 100;
							$max_discount_percentage = $min_price > $discount_value ? ( $discount_value / $min_price ) * 100 : 100;

							$warnings[] = array(
								'code'     => 'wide_price_range_fixed_discount',
								'severity' => 'high',
								'title'    => __( 'Inconsistent Discount Impact', 'smart-cycle-discounts' ),
								'message'  => sprintf(
									__( 'Your %1$s fixed discount will have dramatically different effects across your price range (%2$s - %3$s). Cheap products get %4$d%% off while expensive products only get %5$d%% off.', 'smart-cycle-discounts' ),
									$this->_format_price_plain( $discount_value ),
									$this->_format_price_plain( $min_price ),
									$this->_format_price_plain( $max_price ),
									round( $max_discount_percentage ),
									round( $min_discount_percentage )
								),
								'details'  => array(
									'discount_amount'      => $discount_value,
									'min_price'            => $min_price,
									'max_price'            => $max_price,
									'price_variance_ratio' => round( $price_variance + 1, 1 ),
									'min_discount_percentage' => round( $min_discount_percentage, 1 ),
									'max_discount_percentage' => round( $max_discount_percentage, 1 ),
								),
								'step'     => 'discounts',
								'actions'  => array(
									array(
										'label' => __( 'Switch to Percentage Discount', 'smart-cycle-discounts' ),
										'step'  => 'discounts',
									),
									array(
										'label' => __( 'Narrow Price Range', 'smart-cycle-discounts' ),
										'step'  => 'products',
									),
								),
							);
						}
					}
				}
			}
		}

		// W2: 100% percentage discount (critical error)
		if ( 'percentage' === $discount_type ) {
			// CRITICAL FIX: Check wizard-specific field first with proper empty string handling
			$discount_value = 0;
			if ( isset( $discounts['discount_value_percentage'] ) && '' !== $discounts['discount_value_percentage'] ) {
				$discount_value = floatval( $discounts['discount_value_percentage'] );
			} elseif ( isset( $discounts['discount_value'] ) && '' !== $discounts['discount_value'] ) {
				$discount_value = floatval( $discounts['discount_value'] );
			}

			if ( 100.0 === $discount_value || 100 === $discount_value ) {
				$errors[] = array(
					'code'     => 'percentage_discount_100',
					'severity' => 'critical',
					'title'    => __( '100% Discount = Free Products', 'smart-cycle-discounts' ),
					'message'  => __( 'A 100% discount gives all products away for FREE. This will result in zero revenue. Please set a percentage below 100% (e.g., 50%, 75%, or 90%).', 'smart-cycle-discounts' ),
					'step'     => 'discounts',
				);
			}

			// W4: Very high percentage (90-99%)
			if ( $discount_value >= 90 && $discount_value < 100 ) {
				$product_prices = $this->_get_product_prices( $products );

				if ( ! empty( $product_prices ) ) {
					$min_price = min( $product_prices );
					$max_price = max( $product_prices );
					$avg_price = array_sum( $product_prices ) / count( $product_prices );

					// Calculate example final prices
					$min_final = $min_price * ( ( 100 - $discount_value ) / 100 );
					$avg_final = $avg_price * ( ( 100 - $discount_value ) / 100 );

					$warnings[] = array(
						'code'     => 'percentage_very_high_90_plus',
						'severity' => 'high',
						'title'    => __( 'Extremely High Discount Percentage', 'smart-cycle-discounts' ),
						'message'  => sprintf(
							__( 'A %1$d%% discount is extremely high and will significantly impact profit margins. For example, a %2$s product becomes %3$s. Verify this discount level is intentional and sustainable.', 'smart-cycle-discounts' ),
							$discount_value,
							$this->_format_price_plain( $avg_price ),
							$this->_format_price_plain( $avg_final )
						),
						'details'  => array(
							'discount_percentage'    => $discount_value,
							'example_original_price' => $avg_price,
							'example_final_price'    => $avg_final,
							'min_final_price'        => $min_final,
							'price_reduction_factor' => round( $discount_value, 1 ),
						),
						'step'     => 'discounts',
						'actions'  => array(
							array(
								'label' => __( 'Reduce Discount', 'smart-cycle-discounts' ),
								'step'  => 'discounts',
							),
							array(
								'label' => __( 'Review Profit Margins', 'smart-cycle-discounts' ),
								'step'  => 'discounts',
							),
						),
					);
				}
			}
		}

		// W5: BOGO insufficient stock
		if ( 'bogo' === $discount_type ) {
			$buy_quantity      = isset( $discounts['buy_quantity'] ) ? intval( $discounts['buy_quantity'] ) : 1;
			$get_quantity      = isset( $discounts['get_quantity'] ) ? intval( $discounts['get_quantity'] ) : 1;
			$required_quantity = $buy_quantity + $get_quantity;

			$product_stock = $this->_get_product_stock( $products );

			if ( ! empty( $product_stock ) ) {
				$low_stock_products = array();

				foreach ( $product_stock as $product_id => $stock_quantity ) {
					if ( null !== $stock_quantity && $stock_quantity < $required_quantity ) {
						$product              = wc_get_product( $product_id );
						$low_stock_products[] = array(
							'id'       => $product_id,
							'name'     => $product ? $product->get_name() : sprintf( __( 'Product #%d', 'smart-cycle-discounts' ), $product_id ),
							'stock'    => $stock_quantity,
							'required' => $required_quantity,
						);
					}
				}

				if ( ! empty( $low_stock_products ) ) {
					$count           = count( $low_stock_products );
					$sample_products = array_slice( $low_stock_products, 0, 3 );

					$product_list = array();
					foreach ( $sample_products as $prod ) {
						$product_list[] = sprintf(
							__( '%1$s (Stock: %2$d, Required: %3$d)', 'smart-cycle-discounts' ),
							$prod['name'],
							$prod['stock'],
							$prod['required']
						);
					}

					$message = sprintf(
						__( 'Your "Buy %1$d Get %2$d" campaign includes %3$d product(s) with insufficient stock. Customers will not be able to complete the offer for these items.', 'smart-cycle-discounts' ),
						$buy_quantity,
						$get_quantity,
						$count
					);

					if ( $count > 3 ) {
						$message .= ' ' . sprintf( __( 'Showing first 3 of %d affected products.', 'smart-cycle-discounts' ), $count );
					}

					$warnings[] = array(
						'code'     => 'bogo_insufficient_stock',
						'severity' => 'high',
						'title'    => __( 'BOGO Campaign Has Low Stock Items', 'smart-cycle-discounts' ),
						'message'  => $message,
						'details'  => array(
							'buy_quantity'      => $buy_quantity,
							'get_quantity'      => $get_quantity,
							'required_quantity' => $required_quantity,
							'affected_products' => $low_stock_products,
							'sample_products'   => $product_list,
						),
						'step'     => 'discounts',
						'actions'  => array(
							array(
								'label' => __( 'Remove Low Stock Products', 'smart-cycle-discounts' ),
								'step'  => 'products',
							),
							array(
								'label' => __( 'Adjust BOGO Terms', 'smart-cycle-discounts' ),
								'step'  => 'discounts',
							),
						),
					);
				}
			}
		}

		// W6: Unreachable tiered discounts
		if ( 'tiered' === $discount_type ) {
			$tier_type = isset( $discounts['tier_type'] ) ? $discounts['tier_type'] : 'quantity';
			$tiers     = isset( $discounts['tiers'] ) ? $discounts['tiers'] : array();

			if ( 'quantity' === $tier_type && ! empty( $tiers ) ) {
				$product_stock = $this->_get_product_stock( $products );

				if ( ! empty( $product_stock ) ) {
					// Filter out null stock values
					$valid_stock = array_filter(
						$product_stock,
						function ( $stock ) {
							return null !== $stock;
						}
					);

					// Skip if no valid stock values (prevents max() empty array error)
					if ( empty( $valid_stock ) ) {
						return array(
							'errors'   => $errors,
							'warnings' => $warnings,
						);
					}

					$max_stock = max( $valid_stock );

					if ( $max_stock > 0 ) {
						$unreachable_tiers = array();

						foreach ( $tiers as $tier ) {
							$threshold = isset( $tier['threshold'] ) ? intval( $tier['threshold'] ) : 0;

							if ( $threshold > $max_stock ) {
								$discount_type_tier  = isset( $tier['discount_type'] ) ? $tier['discount_type'] : 'percentage';
								$discount_value_tier = isset( $tier['discount_value'] ) ? $tier['discount_value'] : 0;

								$tier_description = 'percentage' === $discount_type_tier
									? sprintf( __( '%d%% off', 'smart-cycle-discounts' ), $discount_value_tier )
									: sprintf( __( '%s off', 'smart-cycle-discounts' ), $this->_format_price_plain( $discount_value_tier ) );

								$unreachable_tiers[] = array(
									'threshold'   => $threshold,
									'description' => sprintf(
										__( 'Buy %1$d+ get %2$s', 'smart-cycle-discounts' ),
										$threshold,
										$tier_description
									),
								);
							}
						}

						if ( ! empty( $unreachable_tiers ) ) {
							$count      = count( $unreachable_tiers );
							$first_tier = $unreachable_tiers[0];

							$warnings[] = array(
								'code'     => 'tiered_discount_unreachable',
								'severity' => 'high',
								'title'    => __( 'Tiered Discount Cannot Be Reached', 'smart-cycle-discounts' ),
								'message'  => sprintf(
									__( '%1$d discount tier(s) cannot be reached because the highest stock level is %2$d units. For example, the tier "%3$s" requires %4$d items but maximum available stock is only %5$d.', 'smart-cycle-discounts' ),
									$count,
									$max_stock,
									$first_tier['description'],
									$first_tier['threshold'],
									$max_stock
								),
								'details'  => array(
									'max_stock'         => $max_stock,
									'unreachable_tiers' => $unreachable_tiers,
									'tier_count'        => $count,
								),
								'step'     => 'discounts',
								'actions'  => array(
									array(
										'label' => sprintf( __( 'Lower Threshold to %d', 'smart-cycle-discounts' ), $max_stock ),
										'step'  => 'discounts',
									),
									array(
										'label' => __( 'Remove Unreachable Tiers', 'smart-cycle-discounts' ),
										'step'  => 'discounts',
									),
								),
							);
						}
					}
				}
			}
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Get product prices from selection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products    Products configuration.
	 * @return   array                 Array of product prices.
	 */
	private function _get_product_prices( $products ) {
		$selection_type = isset( $products['product_selection_type'] ) ? $products['product_selection_type'] : '';
		$product_ids    = isset( $products['product_ids'] ) ? $products['product_ids'] : array();
		$category_ids   = isset( $products['category_ids'] ) ? $products['category_ids'] : array();

		// Get selected product IDs
		$selected_ids = $this->_get_selected_products( $selection_type, $product_ids, $category_ids );

		if ( empty( $selected_ids ) ) {
			return array();
		}

		$prices = array();

		foreach ( $selected_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$price = $product->get_price();

			// Skip products with no price or zero price
			if ( '' === $price || null === $price ) {
				continue;
			}

			$price = floatval( $price );

			// Only include products with positive prices
			if ( $price > 0 ) {
				$prices[] = $price;
			}
		}

		return $prices;
	}

	/**
	 * Get product stock levels from selection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products    Products configuration.
	 * @return   array                 Array of product ID => stock quantity (null if not managing stock).
	 */
	private function _get_product_stock( $products ) {
		$selection_type = isset( $products['product_selection_type'] ) ? $products['product_selection_type'] : '';
		$product_ids    = isset( $products['product_ids'] ) ? $products['product_ids'] : array();
		$category_ids   = isset( $products['category_ids'] ) ? $products['category_ids'] : array();

		// Get selected product IDs
		$selected_ids = $this->_get_selected_products( $selection_type, $product_ids, $category_ids );

		if ( empty( $selected_ids ) ) {
			return array();
		}

		$stock_levels = array();

		foreach ( $selected_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Check if product manages stock
			if ( $product->managing_stock() ) {
				$stock_quantity              = $product->get_stock_quantity();
				$stock_levels[ $product_id ] = null !== $stock_quantity ? intval( $stock_quantity ) : null;
			} else {
				// Product doesn't manage stock - consider as unlimited
				$stock_levels[ $product_id ] = null;
			}
		}

		return $stock_levels;
	}

	/**
	 * Format price as plain text (no HTML) for use in validation messages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $price    Price to format.
	 * @return   string             Formatted price string.
	 */
	private function _format_price_plain( $price ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '$' . number_format( $price, 2 );
		}

		$currency_symbol    = get_woocommerce_currency_symbol();
		$decimal_separator  = wc_get_price_decimal_separator();
		$thousand_separator = wc_get_price_thousand_separator();
		$decimals           = wc_get_price_decimals();

		// Decode HTML entities (&#036; -> $)
		$currency_symbol = html_entity_decode( $currency_symbol, ENT_QUOTES, 'UTF-8' );

		return $currency_symbol . number_format( $price, $decimals, $decimal_separator, $thousand_separator );
	}
}
