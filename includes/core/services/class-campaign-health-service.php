<?php
/**
 * Campaign Health Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/services/class-campaign-health-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Campaign Health Service Class
 *
 * Analyzes campaign health and provides consistent scoring, status, and recommendations
 * across all plugin locations (dashboard, list table, wizard review step).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/services
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Campaign_Health_Service {

	/**
	 * Health Score Thresholds
	 */
	const COVERAGE_EXCELLENT_THRESHOLD = 70;
	const COVERAGE_GOOD_THRESHOLD      = 50;
	const COVERAGE_FAIR_THRESHOLD      = 30;

	const DISCOUNT_SWEET_SPOT_MAX      = 50;
	const DISCOUNT_HIGH_THRESHOLD      = 70;
	const DISCOUNT_VERY_HIGH_THRESHOLD = 90;

	const LOW_STOCK_THRESHOLD = 10;

	/**
	 * Penalty Values - Severity-based scoring system
	 *
	 * CRITICAL_SEVERE: Campaign-breaking issues (deleted products, impossible config)
	 * CRITICAL_STANDARD: Critical but recoverable (invalid IDs, draft products)
	 * HIGH: Significant issues (very high discounts, out-of-stock)
	 * MEDIUM_HIGH: Medium-severity concerns (price changes, stock warnings)
	 * MEDIUM: Minor configuration issues (low stock, small concerns)
	 * LOW: Cosmetic/best practice suggestions (generic names)
	 */
	const PENALTY_CRITICAL_SEVERE   = 25;
	const PENALTY_CRITICAL_STANDARD = 15;
	const PENALTY_HIGH              = 10;
	const PENALTY_MEDIUM_HIGH       = 8;
	const PENALTY_MEDIUM            = 5;
	const PENALTY_LOW               = 3;

	/**
	 * Status Thresholds
	 *
	 * These thresholds determine the campaign status badge based on health score.
	 * Note: Campaigns with critical issues always get 'critical' status regardless of score.
	 * Campaigns with warnings are capped at 'good' status to prevent false excellent ratings.
	 */
	const STATUS_EXCELLENT_MIN = 90;
	const STATUS_GOOD_MIN      = 70;
	const STATUS_FAIR_MIN      = 50;

	/**
	 * Stock Risk Thresholds
	 */
	const STOCK_RISK_HIGH_MULTIPLIER   = 0.5; // Stock < 50% of estimated demand
	const STOCK_RISK_MEDIUM_MULTIPLIER = 0.8; // Stock < 80% of estimated demand
	const DISCOUNT_BOOST_PERCENTAGE    = 1.5; // 50% boost in sales from discounts
	const DISCOUNT_BOOST_FIXED         = 1.3; // 30% boost for fixed discounts
	const HISTORICAL_DAYS_LOOKBACK     = 30;  // Days to analyze for sales history

	/**
	 * Product Analysis Limits
	 *
	 * Limits for batch processing to prevent performance issues.
	 */
	const PRODUCT_CHECK_LIMIT   = 100; // Maximum products to check for stock/status
	const PRICE_ANALYSIS_LIMIT  = 50;  // Maximum products to analyze for pricing

	/**
	 * Proportional Penalty Thresholds
	 *
	 * Percentage thresholds for scaling penalties based on impact.
	 */
	const IMPACT_CRITICAL_THRESHOLD = 75; // 75%+ affected = full penalty
	const IMPACT_HIGH_THRESHOLD     = 50; // 50-74% affected = 75% penalty
	const IMPACT_MEDIUM_THRESHOLD   = 25; // 25-49% affected = 50% penalty
	// < 25% affected = 25% penalty

	/**
	 * Pagination Limits
	 *
	 * Limits for chunked product loading to prevent memory exhaustion.
	 */
	const PAGINATION_PER_PAGE = 100; // Products per page when chunking
	const PAGINATION_MAX_PAGES = 100; // Safety limit for maximum pages

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private $logger;

	/**
	 * Recurring handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Recurring_Handler    $recurring_handler    Recurring handler instance.
	 */
	private $recurring_handler;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger             $logger              Logger instance.
	 * @param    WSSCD_Recurring_Handler  $recurring_handler   Recurring handler instance.
	 */
	public function __construct( $logger, $recurring_handler ) {
		$this->logger            = $logger;
		$this->recurring_handler = $recurring_handler;
	}

	/**
	 * Apply penalty with immediate score clamping.
	 *
	 * Prevents negative scores during calculation and tracks penalty breakdown.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $health    Health data array (passed by reference).
	 * @param    int    $penalty   Penalty amount to apply.
	 * @param    string $category  Category for breakdown tracking.
	 * @return   void
	 */
	private function apply_penalty( &$health, $penalty, $category = '' ) {
		$health['score'] -= $penalty;
		$health['score']  = max( 0, $health['score'] ); // Clamp immediately

		// Track penalty breakdown by category
		if ( $category ) {
			if ( ! isset( $health['breakdown'][ $category ] ) ) {
				$health['breakdown'][ $category ] = array(
					'penalty' => 0,
					'status'  => 'healthy',
				);
			}
			$health['breakdown'][ $category ]['penalty'] += $penalty;
		}
	}

	/**
	 * Calculate proportional penalty based on impact percentage.
	 *
	 * Scales penalty from 25% to 100% based on percentage of items affected.
	 * Uses threshold-based scaling for predictable results.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $affected_count   Number of items affected.
	 * @param    int $total_count      Total number of items.
	 * @param    int $base_penalty     Base penalty amount.
	 * @return   int                      Scaled penalty amount.
	 */
	private function calculate_proportional_penalty( $affected_count, $total_count, $base_penalty ) {
		if ( $total_count === 0 || $affected_count === 0 ) {
			return 0;
		}

		$percentage = ( $affected_count / $total_count ) * 100;

		// Threshold-based scaling
		if ( $percentage >= self::IMPACT_CRITICAL_THRESHOLD ) {
			return $base_penalty; // Full penalty (75%+ affected)
		} elseif ( $percentage >= self::IMPACT_HIGH_THRESHOLD ) {
			return (int) ( $base_penalty * 0.75 ); // 75% of penalty (50-74% affected)
		} elseif ( $percentage >= self::IMPACT_MEDIUM_THRESHOLD ) {
			return (int) ( $base_penalty * 0.5 ); // 50% of penalty (25-49% affected)
		} else {
			return (int) ( $base_penalty * 0.25 ); // 25% of penalty (<25% affected)
		}
	}

	/**
	 * Calculate logarithmic penalty for multiple similar issues.
	 *
	 * Uses logarithmic scaling to apply diminishing returns for multiple
	 * instances of the same issue type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $issue_count    Number of similar issues.
	 * @param    int $base_penalty   Base penalty for single issue.
	 * @return   int                    Scaled penalty with diminishing returns.
	 */
	private function calculate_logarithmic_penalty( $issue_count, $base_penalty ) {
		if ( $issue_count <= 0 ) {
			return 0;
		}

		if ( $issue_count === 1 ) {
			return $base_penalty;
		}

		// Logarithmic scaling: log2(n + 1) * base_penalty
		// 1 issue = 1x penalty
		// 2 issues = 1.58x penalty
		// 5 issues = 2.58x penalty
		// 10 issues = 3.46x penalty
		$multiplier = log( $issue_count + 1, 2 );
		return (int) ( $base_penalty * $multiplier );
	}

	/**
	 * Analyze campaign health.
	 *
	 * Main entry point for health analysis. Accepts campaign as array or object.
	 *
	 * CONTEXT TYPES & SCORE DIFFERENCES:
	 *
	 * 'review' (Wizard Review Step):
	 * - Campaign is still being configured (not yet saved)
	 * - Shows validation-style warnings user can fix
	 * - More lenient scoring to encourage completion
	 * - Typical score: 90-100 for well-configured campaigns
	 * - Example: Score 97 = "Good configuration, minor recommendations"
	 *
	 * 'dashboard' (Campaigns List/Dashboard):
	 * - Campaign is saved and may be active
	 * - Shows post-creation issues (conflicts, stock changes, deleted products, usage patterns)
	 * - More strict scoring reflecting real-world conditions
	 * - Typical score: 80-95 after campaign runs for a while
	 * - Example: Score 85 = "Healthy but has some warnings (conflicts, stock changes)"
	 *
	 * NOTE: It is NORMAL and EXPECTED for dashboard scores to be 10-15 points lower than
	 * wizard scores due to real-world factors that develop after campaign creation.
	 *
	 * @since    1.0.0
	 * @param    mixed  $campaign      Campaign data (array from DB or WSSCD_Campaign object).
	 * @param    string $mode          Analysis mode: 'quick', 'standard', 'comprehensive'.
	 * @param    array  $context       Additional context (coverage_data, conflicts_data, view_context).
	 * @return   array                    Health analysis results.
	 */
	public function analyze_health( $campaign, $mode = 'standard', $context = array() ) {
		// Normalize campaign data
		$campaign_data = $this->normalize_campaign_data( $campaign );

		// Determine view context (dashboard vs review)
		$view_context = isset( $context['view_context'] ) ? $context['view_context'] : 'dashboard';

		$health = array(
			'score'           => 100,
			'status'          => 'excellent',
			'critical_issues' => array(),
			'warnings'        => array(),
			'info'            => array(),
			'recommendations' => array(),
			'breakdown'       => array(),
		);

		// Run health checks
		$health = $this->check_configuration( $campaign_data, $health, $view_context );
		$health = $this->check_schedule( $campaign_data, $health, $view_context );
		$health = $this->check_recurring( $campaign_data, $health, $view_context );
		$health = $this->check_discount_reasonableness( $campaign_data, $health, $view_context );
		$health = $this->check_cross_step_validation( $campaign_data, $health, $view_context );

		// Mode-specific checks
		if ( 'standard' === $mode || 'comprehensive' === $mode ) {
			$health = $this->check_products( $campaign_data, $health, $context, $view_context );
			$health = $this->check_coverage( $campaign_data, $health, $context, $view_context );
			$health = $this->check_stock_risk( $campaign_data, $health, $context, $view_context );
			$health = $this->check_conflicts( $campaign_data, $health, $context, $view_context );
		}

		// Generate recommendations for comprehensive mode OR review context
		if ( 'comprehensive' === $mode || 'review' === $view_context ) {
			$health['recommendations'] = $this->generate_recommendations( $campaign_data, $health, $context );
		}

		// PRACTICAL INTELLIGENCE FEATURES (comprehensive mode only)
		if ( 'comprehensive' === $mode ) {
			// Multi-dimensional risk assessment
			$health['risk_assessment'] = $this->assess_multi_dimensional_risk( $campaign_data, $context );

			// Smart benchmarking against historical campaigns
			$performance_data = isset( $context['performance_data'] ) ? $context['performance_data'] : array();
			$health['benchmark'] = $this->get_smart_benchmark( $campaign_data, $context, $performance_data );

			// Statistical performance forecasting
			$health['forecast'] = $this->generate_performance_forecast( $campaign_data, $context );

			// Historical pattern analysis
			$health['pattern_analysis'] = $this->analyze_historical_patterns( $campaign_data, $context );

			// Merge pattern-based recommendations with existing recommendations
			if ( ! empty( $health['pattern_analysis']['recommendations'] ) ) {
				foreach ( $health['pattern_analysis']['recommendations'] as $pattern_rec ) {
					$health['recommendations'][] = $pattern_rec['message'];
				}
			}
		}

		// IMPROVED LOGIC: Severity-based status with score as secondary metric
		$health['score']    = max( 0, min( 100, $health['score'] ) );
		$health['status']   = $this->get_status_from_issues( $health );
		$health['is_ready'] = empty( $health['critical_issues'] );

		return $health;
	}

	/**
	 * Normalize campaign data to consistent array format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $campaign    Campaign data (array or WSSCD_Campaign object).
	 * @return   array                 Normalized campaign data array.
	 */
	private function normalize_campaign_data( $campaign ) {
		if ( is_object( $campaign ) && method_exists( $campaign, 'get_id' ) ) {
			// WSSCD_Campaign object
			$data = array(
				'id'                     => $campaign->get_id(),
				'name'                   => $campaign->get_name(),
				'description'            => $campaign->get_description(),
				'status'                 => $campaign->get_status(),
				'priority'               => $campaign->get_priority(),
				'discount_type'          => $campaign->get_discount_type(),
				'discount_value'         => $campaign->get_discount_value(),
				'product_selection_type' => $campaign->get_product_selection_type(),
				'selected_product_ids'   => $campaign->get_product_ids(),
				'start_date'             => $campaign->get_starts_at() ? $campaign->get_starts_at()->format( 'Y-m-d' ) : null,
				'end_date'               => $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'Y-m-d' ) : null,
				'metadata'               => $campaign->get_metadata(),
			);
			if ( method_exists( $campaign, 'get_discount_rules' ) ) {
				$data['discount_rules'] = $campaign->get_discount_rules();
			}
			return $data;
		} elseif ( is_array( $campaign ) ) {
			// Array format from database - normalize field names and decode JSON fields
			// Database uses 'product_ids' but health checks expect 'selected_product_ids'
			if ( isset( $campaign['product_ids'] ) && ! isset( $campaign['selected_product_ids'] ) ) {
				// Decode JSON if it's a string
				if ( is_string( $campaign['product_ids'] ) ) {
					$decoded = json_decode( $campaign['product_ids'], true );
					if ( null !== $decoded && is_array( $decoded ) ) {
						// FIXED: Preserve 0 as valid product ID (rare but possible)
						// array_map ensures integers, doesn't filter out 0 like array_filter would
						$campaign['selected_product_ids'] = array_map( 'intval', $decoded );
					} else {
						$campaign['selected_product_ids'] = array();
					}
				} else {
					$campaign['selected_product_ids'] = is_array( $campaign['product_ids'] ) ? $campaign['product_ids'] : array();
				}
			}

			// Decode other JSON fields if needed
			if ( isset( $campaign['category_ids'] ) && is_string( $campaign['category_ids'] ) ) {
				$decoded                  = json_decode( $campaign['category_ids'], true );
				$campaign['category_ids'] = ( null !== $decoded && is_array( $decoded ) ) ? $decoded : array();
			}

			if ( isset( $campaign['tag_ids'] ) && is_string( $campaign['tag_ids'] ) ) {
				$decoded             = json_decode( $campaign['tag_ids'], true );
				$campaign['tag_ids'] = ( null !== $decoded && is_array( $decoded ) ) ? $decoded : array();
			}

			return $campaign;
		}

		// Invalid input
		$this->logger->warning( 'Invalid campaign data provided to health service', array( 'type' => gettype( $campaign ) ) );
		return array();
	}

	/**
	 * Check configuration health.
	 *
	 * DASHBOARD CONTEXT: No checks (wizard validates all configuration)
	 * REVIEW CONTEXT: Show generic name/description warnings (user can still fix)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign       Campaign data.
	 * @param    array  $health         Health data array.
	 * @param    string $view_context   View context ('dashboard' or 'review').
	 * @return   array                     Updated health data.
	 */
	private function check_configuration( $campaign, $health, $view_context ) {
		// Dashboard context: No configuration checks (wizard already validated everything)
		if ( 'dashboard' === $view_context ) {
			return $health;
		}

		// Review context: Only show warnings about generic names/descriptions (user can still change)
		$penalty = 0;
		$status  = 'healthy';

		if ( ! empty( $campaign['name'] ) ) {
			$name          = trim( $campaign['name'] );
			$generic_names = array( 'test', 'new campaign', 'campaign', 'discount', 'sale', 'temp', 'untitled' );
			$name_lower    = strtolower( $name );

			foreach ( $generic_names as $generic ) {
				if ( $generic === $name_lower || strlen( $name ) < 10 ) {
					$health['warnings'][] = array(
						'code'     => 'generic_name',
						'message'  => sprintf(
							/* translators: %s: campaign name */
							__( 'Campaign name "%s" is generic - use a descriptive name with timeframe', 'smart-cycle-discounts' ),
							$name
						),
						'category' => 'configuration',
					);
					$penalty             += self::PENALTY_LOW;
					$status               = 'warning';
					break;
				}
			}
		}

		if ( ! empty( $campaign['description'] ) ) {
			$description          = $campaign['description'];
			$generic_descriptions = array( 'test', 'description', 'campaign description', 'discount', 'sale' );
			$desc_lower           = strtolower( trim( $description ) );

			foreach ( $generic_descriptions as $generic ) {
				if ( $generic === $desc_lower ) {
					$health['warnings'][] = array(
						'code'     => 'generic_description',
						'message'  => sprintf(
							/* translators: %s: description text */
							__( 'Description "%s" is too generic - add specific campaign details', 'smart-cycle-discounts' ),
							$description
						),
						'category' => 'configuration',
					);
					$penalty             += self::PENALTY_LOW;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
					break;
				}
			}
		}

		// Track breakdown
		$health['breakdown']['configuration'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check products selection health.
	 *
	 * DASHBOARD CONTEXT: Only show issues that develop AFTER creation:
	 * - Products deleted from catalog
	 * - Products out of stock
	 * - Random count exceeds total (if products deleted)
	 * - Draft products (if status changed after creation)
	 *
	 * REVIEW CONTEXT: Can show recommendations about product selection
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign       Campaign data.
	 * @param    array  $health         Health data array.
	 * @param    array  $context        Additional context data.
	 * @param    string $view_context   View context ('dashboard' or 'review').
	 * @return   array                     Updated health data.
	 */
	private function check_products( $campaign, $health, $context, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';

		$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';

		// PRIORITY 1: Use pre-resolved product IDs from coverage_data if available (campaigns list)
		// This includes products resolved from category filters, conflicts, etc.
		if ( isset( $context['coverage_data']['product_ids'] ) && is_array( $context['coverage_data']['product_ids'] ) ) {
			$product_ids = $context['coverage_data']['product_ids'];

			if ( 'dashboard' === $view_context ) {
				$campaign_id = isset( $campaign['id'] ) ? $campaign['id'] : 'unknown';
			}
		} else {
			// PRIORITY 2: Resolve product IDs ourselves (dashboard fallback)
			$product_ids = $this->normalize_product_ids( $campaign );
		}

		// Log product IDs for dashboard debugging if needed
		if ( 'dashboard' === $view_context && ! empty( $product_ids ) ) {
			$campaign_id   = isset( $campaign['id'] ) ? $campaign['id'] : 'unknown';
			$campaign_name = isset( $campaign['name'] ) ? $campaign['name'] : 'unknown';
			$this->logger->debug(
				'Health check analyzing products',
				array(
					'campaign_id'   => $campaign_id,
					'campaign_name' => $campaign_name,
					'product_count' => count( $product_ids ),
				)
			);
		}

		if ( WSSCD_Campaign::SELECTION_TYPE_RANDOM_PRODUCTS === $selection_type ) {
			$random_count = isset( $campaign['random_count'] ) ? intval( $campaign['random_count'] ) : 0;
			if ( $random_count > 0 ) {
				// IMPROVED: Safe error handling for wp_count_posts
				$post_counts    = wp_count_posts( 'product' );
				$total_products = isset( $post_counts->publish ) ? intval( $post_counts->publish ) : 0;

				if ( $random_count > $total_products ) {
					$health['critical_issues'][] = array(
						'code'     => 'random_count_exceeds_total',
						'message'  => sprintf(
							/* translators: 1: random count, 2: total products */
							__( 'Random count (%1$d) exceeds available products (%2$d)', 'smart-cycle-discounts' ),
							$random_count,
							$total_products
						),
						'category' => 'products',
						'severity' => 'critical',
						'step'     => 'products',
					);
					$penalty += self::PENALTY_CRITICAL_STANDARD;
					$status                      = 'critical';
				}
			}
		}

		// For "all_products" campaigns with category filters, get actual matching products for stock check
		if ( 'all_products' === $selection_type && empty( $product_ids ) ) {
			$category_ids = isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : array();
			if ( is_string( $category_ids ) ) {
				$decoded      = json_decode( $category_ids, true );
				$category_ids = ( null !== $decoded && is_array( $decoded ) ) ? $decoded : array();
			}

			if ( ! empty( $category_ids ) ) {
				// Convert category IDs to slugs (wc_get_products 'category' param expects slugs, not IDs)
				$terms = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'include'    => array_map( 'intval', $category_ids ),
						'hide_empty' => false,
						'fields'     => 'id=>slug',
					)
				);

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$category_slugs = array_values( $terms );
					$args           = array(
						'status'   => 'publish',
						'limit'    => self::PRODUCT_CHECK_LIMIT,
						'category' => $category_slugs,
						'return'   => 'ids',
					);
					$product_ids    = wc_get_products( $args );
				}
			}
		}

		if ( ! empty( $product_ids ) && ( 'specific_products' === $selection_type || 'all_products' === $selection_type ) ) {
			$product_count = count( $product_ids );
			$unique_ids    = array_unique( $product_ids );

			$valid_product_ids = array();
			$invalid_count     = 0;
			foreach ( $unique_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				// IMPROVED: Check for WP_Error and NULL
				if ( $product && ! is_wp_error( $product ) ) {
					$valid_product_ids[] = $product_id;
				} else {
					++$invalid_count;
					if ( is_wp_error( $product ) ) {
						$this->logger->warning(
							'WP_Error loading product for health check',
							array(
								'product_id' => $product_id,
								'error'      => $product->get_error_message(),
							)
						);
					}
				}
			}

			if ( $invalid_count > 0 ) {
				// SMART PENALTY: Scale based on percentage of products deleted
				$proportional_penalty = $this->calculate_proportional_penalty(
					$invalid_count,
					$product_count,
					self::PENALTY_CRITICAL_STANDARD
				);

				$health['critical_issues'][] = array(
					'code'     => 'products_deleted_from_catalog',
					'message'  => sprintf(
						/* translators: %d: number of deleted products */
						_n( '%d product no longer exists in catalog', '%d products no longer exist in catalog', $invalid_count, 'smart-cycle-discounts' ),
						$invalid_count
					),
					'category' => 'products',
				);
				$penalty += $proportional_penalty;
				$status   = 'critical';
			}

			// Use valid IDs for stock checks
			$product_ids   = $valid_product_ids;
			$product_count = count( $product_ids );

			if ( $product_count > 0 ) {
				// Analyze stock status (can change after creation)
				$check_products     = array_slice( $product_ids, 0, self::PRODUCT_CHECK_LIMIT );
				$out_of_stock_count = 0;
				$low_stock_count    = 0;
				$draft_count        = 0;

				foreach ( $check_products as $product_id ) {
					$product = wc_get_product( $product_id );
					// IMPROVED: Check for WP_Error
					if ( ! $product || is_wp_error( $product ) ) {
						continue;
					}

					// Stock analysis (FIXED: NULL stock bug - check for NULL before comparison)
					if ( $product->managing_stock() ) {
						$stock = $product->get_stock_quantity();
						if ( null !== $stock && $stock <= 0 ) {
							++$out_of_stock_count;
						} elseif ( null !== $stock && $stock < self::LOW_STOCK_THRESHOLD ) {
							++$low_stock_count;
						}
					} elseif ( ! $product->is_in_stock() ) {
						++$out_of_stock_count;
					}

					// Product status (can change after creation)
					if ( 'publish' !== get_post_status( $product_id ) ) {
						++$draft_count;
					}
				}

				// Log stock analysis results for debugging
				if ( 'dashboard' === $view_context ) {
					$campaign_id = isset( $campaign['id'] ) ? $campaign['id'] : 'unknown';
					$this->logger->debug(
						'Stock analysis complete',
						array(
							'campaign_id'        => $campaign_id,
							'out_of_stock_count' => $out_of_stock_count,
							'low_stock_count'    => $low_stock_count,
							'draft_count'        => $draft_count,
							'checked_products'   => count( $check_products ),
						)
					);
				}

				// SMART PENALTY: Stock issues with proportional penalties
				if ( $out_of_stock_count > 0 ) {
					$out_of_stock_percent = round( ( $out_of_stock_count / count( $check_products ) ) * 100 );

					// All products out of stock = critical
					if ( $out_of_stock_count === count( $check_products ) ) {
						$health['critical_issues'][] = array(
							'code'     => 'all_products_out_of_stock',
							'message'  => sprintf(
								/* translators: %d: number of products */
								__( 'All %d products are out of stock', 'smart-cycle-discounts' ),
								$product_count
							),
							'category' => 'products',
						);
						$penalty += self::PENALTY_CRITICAL_SEVERE;
						$status   = 'critical';
					} else {
						// Proportional penalty based on percentage out of stock
						$proportional_penalty = $this->calculate_proportional_penalty(
							$out_of_stock_count,
							count( $check_products ),
							self::PENALTY_HIGH
						);

						$health['warnings'][] = array(
							'code'     => 'products_out_of_stock',
							'message'  => sprintf(
								/* translators: 1: number out of stock, 2: total, 3: percentage */
								__( '%1$d of %2$d products (%3$d%%) out of stock', 'smart-cycle-discounts' ),
								$out_of_stock_count,
								count( $check_products ),
								$out_of_stock_percent
							),
							'category' => 'products',
						);
						$penalty += $proportional_penalty;
						if ( 'critical' !== $status ) {
							$status = 'warning';
						}
					}
				}

				// Warning: Low stock (only on dashboard - actionable)
				// SMART PENALTY: Proportional to percentage of products with low stock
				if ( 'dashboard' === $view_context && $low_stock_count > 0 ) {
					$proportional_penalty = $this->calculate_proportional_penalty(
						$low_stock_count,
						count( $check_products ),
						self::PENALTY_MEDIUM
					);

					$health['warnings'][] = array(
						'code'     => 'low_stock_products',
						'message'  => sprintf(
							/* translators: %d: number of products */
							_n( '%d product low on stock (<10 units)', '%d products low on stock (<10 units)', $low_stock_count, 'smart-cycle-discounts' ),
							$low_stock_count
						),
						'category' => 'products',
					);
					$penalty += $proportional_penalty;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}

				// Warning: Draft products (status changed after creation)
				if ( $draft_count > 0 ) {
					$health['warnings'][] = array(
						'code'     => 'products_not_published',
						'message'  => sprintf(
							/* translators: %d: number of products */
							_n( '%d product is not published', '%d products are not published', $draft_count, 'smart-cycle-discounts' ),
							$draft_count
						),
						'category' => 'products',
					);
					$penalty += self::PENALTY_MEDIUM_HIGH;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// POST-CREATION ISSUE: Check category filter expansion or emptiness
		// Category filter applies to ALL selection types (categories are a FILTER, not selection type)
		$category_ids = isset( $campaign['selected_category_ids'] ) ? $campaign['selected_category_ids'] : ( isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : array() );

		// Handle serialized data
		if ( is_string( $category_ids ) ) {
			$category_ids = maybe_unserialize( $category_ids );
		}
		if ( ! is_array( $category_ids ) ) {
			$category_ids = array();
		}

		// Filter valid numeric IDs only
		$category_ids = array_filter(
			$category_ids,
			function ( $id ) {
				return is_numeric( $id ) && intval( $id ) > 0;
			}
		);

		if ( ! empty( $category_ids ) ) {
			// Use chunked loading to avoid memory exhaustion
			$current_products = array();
			$page             = 1;
			$per_page         = self::PAGINATION_PER_PAGE;

			if ( function_exists( 'wc_get_products' ) ) {
				// Convert category IDs to slugs (wc_get_products 'category' param expects slugs, not IDs)
				$category_slugs = array();
				$terms          = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'include'    => array_map( 'intval', $category_ids ),
						'hide_empty' => false,
						'fields'     => 'id=>slug',
					)
				);
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$category_slugs = array_values( $terms );
				}

				// If valid slugs found, use WooCommerce API with chunking
				if ( ! empty( $category_slugs ) ) {
					while ( true ) {
						$batch = wc_get_products(
							array(
								'limit'    => $per_page,
								'page'     => $page,
								'status'   => 'publish',
								'category' => $category_slugs,
								'return'   => 'ids',
							)
						);

						if ( empty( $batch ) ) {
							break;
						}

						$current_products = array_merge( $current_products, $batch );
						$page++;

						if ( $page > self::PAGINATION_MAX_PAGES ) {
							break; // Safety limit
						}
					}
				}
			}

			// Fallback: use get_posts with tax_query if WC API unavailable or no slugs found
			if ( empty( $current_products ) ) {
				// Reset pagination for fallback
				$page = 1;
				// Fallback with chunking
				while ( true ) {
					$args = array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => $per_page,
						'fields'         => 'ids',
						'paged'          => $page,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category-based product filtering.
						'tax_query'      => array(
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => array_map( 'intval', $category_ids ),
							),
						),
					);

					$batch = get_posts( $args );

					if ( empty( $batch ) ) {
						break;
					}

					$current_products = array_merge( $current_products, $batch );
					$page++;

					if ( $page > self::PAGINATION_MAX_PAGES ) {
						break; // Safety limit
					}
				}
			}

			$current_count = count( $current_products );

			// Critical: Category filter resulted in zero products
			if ( 0 === $current_count ) {
				$health['critical_issues'][] = array(
					'code'     => 'category_filter_empty',
					'message'  => __( 'Selected category filter contains 0 published products', 'smart-cycle-discounts' ),
					'category' => 'products',
				);
				$penalty += self::PENALTY_CRITICAL_SEVERE;
				$status                      = 'critical';
			} elseif ( $current_count > 500 ) {
				// Warning: Category filter includes too many products
				$health['warnings'][] = array(
					'code'     => 'category_filter_too_broad',
					'message'  => sprintf(
						/* translators: %d: product count */
						__( 'Category filter includes %d products (may impact performance)', 'smart-cycle-discounts' ),
						$current_count
					),
					'category' => 'products',
				);
				$penalty += self::PENALTY_MEDIUM_HIGH;
				if ( 'critical' !== $status ) {
					$status = 'warning';
				}
			}
		}

		// Track breakdown
		$health['breakdown']['products'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check schedule health.
	 *
	 * PHILOSOPHY: Only flag issues that users can and should act on.
	 * Do NOT flag system-managed state transitions as health issues.
	 *
	 * CHECKS PERFORMED:
	 * - Campaign ending soon (3 days) - Informational planning warning
	 * - No activity detected (7+ days active) - Performance monitoring
	 *
	 * REMOVED ILLOGICAL CHECKS:
	 * - "Campaign expired but still active" - Impossible due to wizard validation
	 *   and cron auto-expiration. If it occurs, it's a system issue, not campaign health.
	 * - "Start date passed for scheduled" - Expected behavior, cron handles transition.
	 *
	 * REVIEW CONTEXT: Wizard already validates schedule, no additional checks needed
	 *
	 * TIMEZONE HANDLING:
	 * Uses current_time('timestamp') and wsscd_combine_date_time() which respect WordPress timezone settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign       Campaign data.
	 * @param    array  $health         Health data array.
	 * @param    string $view_context   View context ('dashboard' or 'review').
	 * @return   array                     Updated health data.
	 */
	private function check_schedule( $campaign, $health, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';
		$now     = current_time( 'timestamp' ); // WordPress timezone-aware timestamp

		$campaign_status = isset( $campaign['status'] ) ? $campaign['status'] : '';
		$end_date        = isset( $campaign['end_date'] ) ? $campaign['end_date'] : null;
		$start_date      = isset( $campaign['start_date'] ) ? $campaign['start_date'] : null;
		$end_time_only   = isset( $campaign['end_time'] ) ? $campaign['end_time'] : '23:59';
		$start_time_only = isset( $campaign['start_time'] ) ? $campaign['start_time'] : '00:00';

		// REMOVED: "Campaign expired but still active" check
		// Reason: This is IMPOSSIBLE due to wizard validation (prevents past end dates)
		// and the wsscd_update_campaign_status cron job automatically expires campaigns.
		// If this state occurs, it's a SYSTEM issue (cron not running), not a campaign health issue.
		// Users cannot fix this - it requires checking WordPress cron health.
		//
		// KEPT: "Ending soon" warning - this is useful information for planning
		if ( 'active' === $campaign_status && ! empty( $end_date ) ) {
			$end_dt        = wsscd_combine_date_time( $end_date, $end_time_only, wp_timezone_string() );
			$end_timestamp = $end_dt ? $end_dt->getTimestamp() : false;

			// Informational warning: Campaign ending within 3 days
			if ( $end_timestamp && $end_timestamp < ( $now + ( 3 * DAY_IN_SECONDS ) ) && $end_timestamp >= $now ) {
				$days_left            = ceil( ( $end_timestamp - $now ) / DAY_IN_SECONDS );
				$health['warnings'][] = array(
					'code'     => 'ending_soon',
					'message'  => sprintf(
						/* translators: %d: days until campaign ends */
						_n( 'Ends in %d day', 'Ends in %d days', $days_left, 'smart-cycle-discounts' ),
						$days_left
					),
					'category' => 'schedule',
				);
				$penalty += self::PENALTY_MEDIUM;
				if ( 'critical' !== $status ) {
					$status = 'warning';
				}
			}
		}

		// REMOVED: "Start date passed" warning for scheduled campaigns
		// Reason: This is EXPECTED behavior - scheduled campaigns automatically
		// transition to 'active' status via cron when their start date arrives.
		// Showing this as a warning is illogical and confusing to users.
		// The wsscd_update_campaign_status cron job handles this transition automatically.

		// POST-CREATION ISSUE: No activity warning (dashboard only)
		if ( 'dashboard' === $view_context && 'active' === $campaign_status && ! empty( $start_date ) ) {
			$start_dt        = wsscd_combine_date_time( $start_date, $start_time_only, wp_timezone_string() );
			$start_timestamp = $start_dt ? $start_dt->getTimestamp() : false;

			if ( $start_timestamp && $start_timestamp < ( $now - ( 7 * DAY_IN_SECONDS ) ) ) {
				// Campaign has been active for 7+ days
				$usage_count = isset( $campaign['usage_count'] ) ? intval( $campaign['usage_count'] ) : 0;

				// Warning: No activity detected
				if ( 0 === $usage_count ) {
					$days_active          = floor( ( $now - $start_timestamp ) / DAY_IN_SECONDS );
					$health['warnings'][] = array(
						'code'     => 'no_activity_detected',
						'message'  => sprintf(
							/* translators: %d: number of days campaign has been active */
							_n( 'No discount usage in %d day - verify campaign is working correctly', 'No discount usage in %d days - verify campaign is working correctly', $days_active, 'smart-cycle-discounts' ),
							$days_active
						),
						'category' => 'schedule',
					);
					$penalty += self::PENALTY_MEDIUM_HIGH;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// Track breakdown
		$health['breakdown']['schedule'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check recurring campaign health.
	 *
	 * DASHBOARD CONTEXT: Post-creation recurring issues:
	 * - Recurring enabled but no next occurrence scheduled
	 * - Inactive recurring campaigns
	 * - Past end date but recurring still active
	 * - Occurrence cache integrity issues
	 * - Last occurrence failed with error
	 *
	 * REVIEW CONTEXT: Pre-creation validation:
	 * - Recurring pattern conflicts with schedule duration
	 * - Recurring enabled with indefinite end date (should be caught by wizard)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign       Campaign data.
	 * @param    array  $health         Health data array.
	 * @param    string $view_context   View context ('dashboard' or 'review').
	 * @return   array                     Updated health data.
	 */
	private function check_recurring( $campaign, $health, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';

		// Skip if recurring not enabled
		if ( empty( $campaign['enable_recurring'] ) ) {
			$health['breakdown']['recurring'] = array(
				'penalty' => 0,
				'status'  => 'healthy',
			);
			return $health;
		}

		$campaign_id = isset( $campaign['id'] ) ? intval( $campaign['id'] ) : 0;

		// Can't check recurring data without campaign ID (new campaigns in review)
		if ( 0 === $campaign_id ) {
			$health['breakdown']['recurring'] = array(
				'penalty' => 0,
				'status'  => 'healthy',
			);
			return $health;
		}

		// Get recurring settings from database
		$recurring_settings = $this->recurring_handler->get_recurring_settings( $campaign_id );

		// CRITICAL: Recurring enabled but no settings in database
		if ( ! $recurring_settings ) {
			$health['critical_issues'][] = array(
				'code'     => 'recurring_settings_missing',
				'message'  => __( 'Recurring is enabled but configuration is missing - resave campaign to fix', 'smart-cycle-discounts' ),
				'category' => 'recurring',
			);
			$penalty                    += self::PENALTY_CRITICAL_STANDARD;
			$status                      = 'critical';

			$health['breakdown']['recurring'] = array(
				'penalty' => $penalty,
				'status'  => $status,
			);
			$health['score']                 -= $penalty;
			return $health;
		}

		// Dashboard-specific checks (post-creation issues)
		if ( 'dashboard' === $view_context ) {
			$is_parent   = empty( $recurring_settings['parent_campaign_id'] );
			$is_active   = ! empty( $recurring_settings['is_active'] );
			$next_occur  = isset( $recurring_settings['next_occurrence_date'] ) ? $recurring_settings['next_occurrence_date'] : null;
			$last_error  = isset( $recurring_settings['last_error'] ) ? $recurring_settings['last_error'] : '';
			$retry_count = isset( $recurring_settings['retry_count'] ) ? intval( $recurring_settings['retry_count'] ) : 0;

			// Check 1: Active recurring parent with no next occurrence
			if ( $is_parent && $is_active && empty( $next_occur ) ) {
				$end_date = isset( $recurring_settings['recurrence_end_date'] ) ? $recurring_settings['recurrence_end_date'] : null;

				// If no end date, this is a critical issue
				if ( empty( $end_date ) ) {
					$health['critical_issues'][] = array(
						'code'     => 'recurring_no_next_occurrence',
						'message'  => __( 'Recurring campaign has no next occurrence scheduled - check occurrence cache', 'smart-cycle-discounts' ),
						'category' => 'recurring',
					);
					$penalty += self::PENALTY_CRITICAL_STANDARD;
					$status   = 'critical';
				} else {
					// Has end date - might have naturally ended
					$end_timestamp = strtotime( $end_date );
					$now           = current_time( 'timestamp' );

					if ( $end_timestamp >= $now ) {
						// End date is future but no next occurrence - this is a problem
						$health['warnings'][] = array(
							'code'     => 'recurring_schedule_gap',
							'message'  => __( 'Recurring schedule has gaps - no next occurrence scheduled before end date', 'smart-cycle-discounts' ),
							'category' => 'recurring',
						);
						$penalty += self::PENALTY_HIGH;
						if ( 'critical' !== $status ) {
							$status = 'warning';
						}
					}
				}
			}

			// Check 2: Inactive recurring campaign warning
			if ( $is_parent && ! $is_active ) {
				$health['info'][] = array(
					'code'     => 'recurring_stopped',
					'message'  => __( 'Recurring schedule has been stopped - future occurrences will not be created', 'smart-cycle-discounts' ),
					'category' => 'recurring',
				);
				// Info only, no penalty for user-initiated stop
			}

			// Check 3: Last occurrence failed with error
			if ( ! empty( $last_error ) && $retry_count > 0 ) {
				$health['warnings'][] = array(
					'code'     => 'recurring_occurrence_failed',
					'message'  => sprintf(
						/* translators: 1: error message, 2: retry count */
						__( 'Last occurrence failed: %1$s (Retries: %2$d)', 'smart-cycle-discounts' ),
						$last_error,
						$retry_count
					),
					'category' => 'recurring',
				);
				$penalty += self::PENALTY_MEDIUM_HIGH;
				if ( 'critical' !== $status ) {
					$status = 'warning';
				}
			}

			// Check 4: Recurring end date passed but still marked active
			if ( $is_parent && $is_active ) {
				$end_date = isset( $recurring_settings['recurrence_end_date'] ) ? $recurring_settings['recurrence_end_date'] : null;

				if ( ! empty( $end_date ) ) {
					$end_timestamp = strtotime( $end_date . ' 23:59:59' );
					$now           = current_time( 'timestamp' );

					if ( $end_timestamp < $now ) {
						$health['warnings'][] = array(
							'code'     => 'recurring_ended_but_active',
							'message'  => __( 'Recurring end date has passed but schedule is still marked active - will stop after final occurrence', 'smart-cycle-discounts' ),
							'category' => 'recurring',
						);
						$penalty += self::PENALTY_MEDIUM;
						if ( 'critical' !== $status ) {
							$status = 'warning';
						}
					}
				}
			}

			// Check 5: Recurrence count limit reached
			if ( $is_parent && $is_active ) {
				$recurrence_count = isset( $recurring_settings['recurrence_count'] ) ? intval( $recurring_settings['recurrence_count'] ) : 0;
				$occurrence_num   = isset( $recurring_settings['occurrence_number'] ) ? intval( $recurring_settings['occurrence_number'] ) : 0;

				if ( $recurrence_count > 0 && $occurrence_num >= $recurrence_count ) {
					$health['info'][] = array(
						'code'     => 'recurring_count_limit_reached',
						'message'  => sprintf(
							/* translators: %d: maximum occurrence count */
							__( 'Recurring limit reached (%d occurrences) - no more instances will be created', 'smart-cycle-discounts' ),
							$recurrence_count
						),
						'category' => 'recurring',
					);
					// Info only - this is expected behavior
				}
			}
		}

		// Track breakdown
		$health['breakdown']['recurring'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check discount reasonableness.
	 *
	 * DASHBOARD CONTEXT: Show business warnings and post-creation issues:
	 * - High discount warnings (90%, 70% - may cause profit loss)
	 * - Fixed discount exceeds price (if prices changed)
	 * - Low discount warnings (may not be compelling)
	 *
	 * REMOVED: Validation errors wizard prevents (discount = 0, percentage > 100%)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign       Campaign data.
	 * @param    array  $health         Health data array.
	 * @param    string $view_context   View context ('dashboard' or 'review').
	 * @return   array                     Updated health data.
	 */
	private function check_discount_reasonableness( $campaign, $health, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';

		$discount_type  = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : '';
		$discount_value = isset( $campaign['discount_value'] ) ? floatval( $campaign['discount_value'] ) : 0;

		// BUSINESS WARNING: Extremely high percentage discount
		if ( 'percentage' === $discount_type && $discount_value >= 90 ) {
			$health['critical_issues'][] = array(
				'code'     => 'extreme_discount',
				'message'  => sprintf(
					/* translators: %d: discount percentage */
					__( '%d%% discount is extremely high - may cause profit loss', 'smart-cycle-discounts' ),
					$discount_value
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_CRITICAL_SEVERE;
			$status                      = 'critical';
		} elseif ( 'percentage' === $discount_type && $discount_value >= 70 ) {
			// BUSINESS WARNING: Very high discount
			$health['warnings'][] = array(
				'code'     => 'very_high_discount',
				'message'  => sprintf(
					/* translators: %d: discount percentage */
					__( '%d%% discount is very high - verify intentional', 'smart-cycle-discounts' ),
					$discount_value
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_HIGH;
			$status               = 'warning';
		}
		// REMOVED: Low discount penalty (<5%) - Valid business strategy, not unhealthy
		// Small discounts (2-5%) are legitimate for customer loyalty, subtle promotions, etc.

		// POST-CREATION ISSUE: Fixed discount exceeds product price (if prices changed)
		if ( 'fixed' === $discount_type && $discount_value > 0 ) {
			$product_ids = $this->normalize_product_ids( $campaign );

			if ( ! empty( $product_ids ) ) {
				$prices = $this->get_product_prices( $product_ids, self::PRICE_ANALYSIS_LIMIT );

				if ( ! empty( $prices ) ) {
					$min_price = min( $prices );
					if ( $discount_value > $min_price ) {
						$health['critical_issues'][] = array(
							'code'     => 'fixed_exceeds_price',
							'message'  => sprintf(
								/* translators: 1: fixed discount, 2: product price */
								__( 'Fixed discount $%1$.2f exceeds product price $%2$.2f', 'smart-cycle-discounts' ),
								$discount_value,
								$min_price
							),
							'category' => 'discount',
						);
						$penalty += self::PENALTY_CRITICAL_SEVERE;
						$status                      = 'critical';
					}
				}
			}
		}

		// POST-CREATION ISSUE: Check for significant price changes (dashboard only)
		if ( 'dashboard' === $view_context && isset( $campaign['original_avg_price'] ) && $campaign['original_avg_price'] > 0 ) {
			$product_ids = $this->normalize_product_ids( $campaign );

			if ( ! empty( $product_ids ) ) {
				$current_prices = $this->get_product_prices( $product_ids, self::PRICE_ANALYSIS_LIMIT );

				if ( ! empty( $current_prices ) ) {
					$current_avg  = array_sum( $current_prices ) / count( $current_prices );
					$original_avg = floatval( $campaign['original_avg_price'] );

					if ( $original_avg > 0 ) {
						$price_change_percent = abs( ( ( $current_avg - $original_avg ) / $original_avg ) * 100 );

						// SMART PENALTY: Both price increases and decreases are equally concerning
						// Unified threshold (40%+) with equal severity
						if ( $price_change_percent >= 40 ) {
							$direction = $current_avg > $original_avg ? 'increased' : 'decreased';
							$advice    = $current_avg > $original_avg ? 'consider adjusting discount' : 'verify discount still appropriate';

							$health['warnings'][] = array(
								'code'     => 'price_changed_significantly',
								'message'  => sprintf(
									/* translators: 1: direction (increased/decreased), 2: percentage, 3: advice */
									__( 'Product prices %1$s %2$d%% since campaign creation - %3$s', 'smart-cycle-discounts' ),
									$direction,
									round( $price_change_percent ),
									$advice
								),
								'category' => 'discount',
							);
							// Equal penalty for both directions - both affect campaign effectiveness
							$penalty += self::PENALTY_MEDIUM_HIGH;
							if ( 'critical' !== $status ) {
								$status = 'warning';
							}
						}
					}
				}
			}
		}

		// BUSINESS WARNING: Tiered discount logic issues (tiers live in discount_rules or discount_config)
		if ( 'tiered' === $discount_type ) {
			$tiers = isset( $campaign['discount_rules']['tiers'] ) ? $campaign['discount_rules']['tiers'] : ( isset( $campaign['discount_config']['tiers'] ) ? $campaign['discount_config']['tiers'] : array() );
			if ( is_string( $tiers ) ) {
				$tiers = maybe_unserialize( $tiers );
			}

			if ( ! empty( $tiers ) && is_array( $tiers ) && count( $tiers ) >= 2 ) {
				$prev_quantity = 0;
				$prev_discount = 0.0;
				$is_illogical  = false;

				foreach ( $tiers as $tier ) {
					$quantity = isset( $tier['min_quantity'] ) ? intval( $tier['min_quantity'] ) : 0;
					$discount = isset( $tier['discount_value'] ) ? floatval( $tier['discount_value'] ) : 0.0;

					if ( $prev_discount > 0 && $discount < $prev_discount ) {
						$is_illogical = true;
					}

					$prev_quantity = $quantity;
					$prev_discount = $discount;
				}

				// BUSINESS WARNING: Illogical tier structure
				if ( $is_illogical ) {
					$health['warnings'][] = array(
						'code'     => 'tiered_illogical',
						'message'  => __( 'Tiered discounts should increase with quantity', 'smart-cycle-discounts' ),
						'category' => 'discount',
					);
					$penalty += self::PENALTY_MEDIUM_HIGH;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// BUSINESS WARNING: BOGO low value
		if ( 'bogo' === $discount_type ) {
			$buy_quantity        = isset( $campaign['discount_config']['bogo']['buy_quantity'] ) ? intval( $campaign['discount_config']['bogo']['buy_quantity'] ) : 0;
			$get_quantity        = isset( $campaign['discount_config']['bogo']['get_quantity'] ) ? intval( $campaign['discount_config']['bogo']['get_quantity'] ) : 0;
			$discount_percentage = isset( $campaign['discount_config']['bogo']['discount_percentage'] ) ? intval( $campaign['discount_config']['bogo']['discount_percentage'] ) : 100;

			if ( $buy_quantity >= 1 && $get_quantity >= 1 ) {
				$effective_discount = ( $get_quantity * $discount_percentage ) / ( $buy_quantity + $get_quantity );
				if ( $effective_discount < 15 ) {
					$health['warnings'][] = array(
						'code'     => 'bogo_low_value',
						'message'  => sprintf(
							/* translators: 1: buy quantity, 2: get quantity, 3: effective discount percentage */
							__( 'BOGO buy %1$d get %2$d = %3$d%% discount may be too low', 'smart-cycle-discounts' ),
							$buy_quantity,
							$get_quantity,
							round( $effective_discount )
						),
						'category' => 'discount',
					);
					$penalty += self::PENALTY_MEDIUM;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// BUSINESS WARNING: Spend threshold reasonableness (thresholds in discount_rules or discount_config)
		if ( 'spend_threshold' === $discount_type ) {
			$thresholds = isset( $campaign['discount_rules']['thresholds'] ) ? $campaign['discount_rules']['thresholds'] : ( isset( $campaign['discount_config']['thresholds'] ) ? $campaign['discount_config']['thresholds'] : array() );
			if ( is_string( $thresholds ) ) {
				$thresholds = maybe_unserialize( $thresholds );
			}

			if ( ! empty( $thresholds ) && is_array( $thresholds ) ) {
				$first_threshold = isset( $thresholds[0]['spend_amount'] ) ? floatval( $thresholds[0]['spend_amount'] ) : 0;

				// BUSINESS WARNING: Threshold may be too high
				if ( $first_threshold > 500 ) {
					$health['warnings'][] = array(
						'code'     => 'threshold_too_high',
						'message'  => sprintf(
							/* translators: %s: threshold amount */
							__( 'Spend $%s threshold may be too high', 'smart-cycle-discounts' ),
							number_format( $first_threshold, 2 )
						),
						'category' => 'discount',
					);
					$penalty += self::PENALTY_MEDIUM;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				} elseif ( $first_threshold < 20 ) {
					// BUSINESS WARNING: Threshold may be too low
					$health['warnings'][] = array(
						'code'     => 'threshold_too_low',
						'message'  => sprintf(
							/* translators: %s: threshold amount */
							__( 'Spend $%s threshold is very low - most orders may qualify', 'smart-cycle-discounts' ),
							number_format( $first_threshold, 2 )
						),
						'category' => 'discount',
					);
					$penalty += self::PENALTY_LOW;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// Usage limits checks
		$usage_limit_per_customer = isset( $campaign['usage_limit_per_customer'] ) ? intval( $campaign['usage_limit_per_customer'] ) : 0;
		$total_usage_limit        = isset( $campaign['total_usage_limit'] ) ? intval( $campaign['total_usage_limit'] ) : 0;

		// Warning: Very low usage limit per customer
		if ( $usage_limit_per_customer > 0 && $usage_limit_per_customer < 3 ) {
			$health['warnings'][] = array(
				'code'     => 'usage_limit_very_low',
				'message'  => sprintf(
					/* translators: %d: usage limit */
					__( 'Usage limit of %d per customer is very restrictive', 'smart-cycle-discounts' ),
					$usage_limit_per_customer
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_LOW;
			if ( 'critical' !== $status ) {
				$status = 'warning';
			}
		}

		// Warning: Low total usage limit
		if ( $total_usage_limit > 0 && $total_usage_limit < 50 ) {
			$health['warnings'][] = array(
				'code'     => 'total_usage_low',
				'message'  => sprintf(
					/* translators: %d: total usage limit */
					__( 'Total usage limit of %d may be reached quickly', 'smart-cycle-discounts' ),
					$total_usage_limit
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_LOW;
			if ( 'critical' !== $status ) {
				$status = 'warning';
			}
		}

		// POST-CREATION ISSUE: Check usage limit exhaustion (only for dashboard)
		if ( 'dashboard' === $view_context && $total_usage_limit > 0 ) {
			$usage_count = isset( $campaign['usage_count'] ) ? intval( $campaign['usage_count'] ) : 0;

			// Critical: Usage limit exhausted
			if ( $usage_count >= $total_usage_limit ) {
				$health['critical_issues'][] = array(
					'code'     => 'usage_limit_exhausted',
					'message'  => sprintf(
						/* translators: %d: usage limit */
						__( 'Usage limit reached (%d uses) - campaign no longer applying discounts', 'smart-cycle-discounts' ),
						$total_usage_limit
					),
					'category' => 'discount',
				);
				$penalty += self::PENALTY_CRITICAL_SEVERE;
				$status                      = 'critical';
			} elseif ( $usage_count > 0 && $total_usage_limit > 0 ) {
				// Warning: Nearly exhausted (90%+ used)
				$usage_percentage = ( $usage_count / $total_usage_limit ) * 100;
				if ( $usage_percentage >= 90 ) {
					$remaining            = $total_usage_limit - $usage_count;
					$health['warnings'][] = array(
						'code'     => 'usage_limit_nearly_exhausted',
						'message'  => sprintf(
							/* translators: 1: uses remaining, 2: total limit */
							_n( 'Only %1$d use remaining (limit: %2$d)', 'Only %1$d uses remaining (limit: %2$d)', $remaining, 'smart-cycle-discounts' ),
							$remaining,
							$total_usage_limit
						),
						'category' => 'discount',
					);
					$penalty += self::PENALTY_HIGH;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// Application rules checks
		$max_discount_amount  = isset( $campaign['max_discount_amount'] ) ? floatval( $campaign['max_discount_amount'] ) : 0;
		$minimum_quantity     = isset( $campaign['minimum_quantity'] ) ? intval( $campaign['minimum_quantity'] ) : 0;
		$minimum_order_amount = isset( $campaign['minimum_order_amount'] ) ? floatval( $campaign['minimum_order_amount'] ) : 0;

		// Warning: Max discount too low
		if ( $max_discount_amount > 0 && $max_discount_amount < 10 ) {
			$health['warnings'][] = array(
				'code'     => 'max_discount_low',
				'message'  => sprintf(
					/* translators: %s: max discount amount */
					__( 'Maximum discount $%s limits savings significantly', 'smart-cycle-discounts' ),
					number_format( $max_discount_amount, 2 )
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_LOW;
			if ( 'critical' !== $status ) {
				$status = 'warning';
			}
		}

		// Warning: Minimum quantity too high
		if ( $minimum_quantity > 5 ) {
			$health['warnings'][] = array(
				'code'     => 'min_quantity_high',
				'message'  => sprintf(
					/* translators: %d: minimum quantity */
					__( 'Minimum quantity %d may prevent single-item purchases', 'smart-cycle-discounts' ),
					$minimum_quantity
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_MEDIUM;
			if ( 'critical' !== $status ) {
				$status = 'warning';
			}
		}

		// Warning: Minimum order amount too high
		if ( $minimum_order_amount > 100 ) {
			$health['warnings'][] = array(
				'code'     => 'min_order_high',
				'message'  => sprintf(
					/* translators: %s: minimum order amount */
					__( 'Minimum order $%s may be above average order value', 'smart-cycle-discounts' ),
					number_format( $minimum_order_amount, 2 )
				),
				'category' => 'discount',
			);
			$penalty += self::PENALTY_MEDIUM;
			if ( 'critical' !== $status ) {
				$status = 'warning';
			}
		}

		// Track breakdown
		$health['breakdown']['discount'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check cross-step validation.
	 *
	 * Validates compatibility and consistency across wizard steps using
	 * the centralized Campaign_Cross_Validator. This catches integration
	 * issues that span multiple steps (discounts + products, discounts + schedule, etc.).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign       Campaign data.
	 * @param    array  $health         Health data array.
	 * @param    string $view_context   View context (dashboard or review).
	 * @return   array                     Updated health data.
	 */
	private function check_cross_step_validation( $campaign, $health, $view_context ) {
		// Load the cross-validator
		if ( ! class_exists( 'WSSCD_Campaign_Cross_Validator' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/validation/class-campaign-cross-validator.php';
		}

		// DOUBLE-COUNTING PREVENTION: Track which issues have already been penalized
		$already_penalized = array();
		foreach ( $health['critical_issues'] as $issue ) {
			if ( isset( $issue['code'] ) ) {
				$already_penalized[] = $issue['code'];
			}
		}
		foreach ( $health['warnings'] as $issue ) {
			if ( isset( $issue['code'] ) ) {
				$already_penalized[] = $issue['code'];
			}
		}

		// Create a WP_Error object to collect validation errors
		$errors = new WP_Error();

		// Run cross-step validation
		WSSCD_Campaign_Cross_Validator::validate( $campaign, $errors );

		// Convert WP_Error to health issues
		if ( $errors->has_errors() ) {
			$error_codes = $errors->get_error_codes();

			foreach ( $error_codes as $code ) {
				// SKIP if this issue was already penalized in previous checks
				if ( in_array( $code, $already_penalized, true ) ) {
					continue;
				}

				$messages = $errors->get_error_messages( $code );
				$data     = $errors->get_error_data( $code );
				$severity = isset( $data['severity'] ) ? $data['severity'] : 'warning';

				foreach ( $messages as $message ) {
					// Map severity to health issue type
					switch ( $severity ) {
						case 'critical':
							$health['critical_issues'][] = array(
								'code'     => $code,
								'message'  => $message,
								'category' => 'cross_step',
							);
							$health['score'] -= self::PENALTY_CRITICAL_STANDARD;
							break;

						case 'warning':
							$health['warnings'][] = array(
								'code'     => $code,
								'message'  => $message,
								'category' => 'cross_step',
							);
							$health['score'] -= self::PENALTY_MEDIUM;
							break;

						case 'info':
						default:
							$health['info'][] = array(
								'code'     => $code,
								'message'  => $message,
								'category' => 'cross_step',
							);
							// Info messages don't reduce score
							break;
					}
				}
			}
		}

		return $health;
	}

	/**
	 * Check coverage health.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $health      Health data array.
	 * @param    array $context     Additional context data.
	 * @return   array                 Updated health data.
	 */
	private function check_coverage( $campaign, $health, $context, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';

		// Note: Product selection validation is handled by check_products() method.
		// This method focuses on coverage percentage analysis only.

		if ( isset( $context['coverage_data']['coverage_percentage'] ) ) {
			$coverage_percentage = $context['coverage_data']['coverage_percentage'];
			if ( $coverage_percentage < 50 && $coverage_percentage > 0 ) {
				$health['warnings'][] = array(
					'code'     => 'low_coverage',
					'message'  => sprintf(
						/* translators: %d: coverage percentage */
						__( 'Only %d%% of selected products will receive discounts', 'smart-cycle-discounts' ),
						$coverage_percentage
					),
					'category' => 'coverage',
				);
				$penalty += self::PENALTY_HIGH;
				$status               = 'warning';
			}
		}

		// Track breakdown
		$health['breakdown']['coverage'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check stock risk.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $health      Health data array.
	 * @param    array $context     Additional context data.
	 * @return   array                 Updated health data.
	 */
	private function check_stock_risk( $campaign, $health, $context, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';

		$product_ids = isset( $campaign['selected_product_ids'] ) ? $campaign['selected_product_ids'] : array();

		if ( empty( $product_ids ) ) {
			// Track breakdown
			$health['breakdown']['stock'] = array(
				'penalty' => 0,
				'status'  => 'healthy',
			);
			return $health;
		}

		// IMPROVED: Calculate real stock depletion risk
		$stock_risk_data = $this->calculate_stock_risk( $product_ids, $campaign );

		if ( isset( $context['view_context'] ) && 'review' === $context['view_context'] ) {
			$health['stock_risk'] = $stock_risk_data;
		}

		// Apply penalties based on risk level
		if ( $stock_risk_data['high_risk_count'] > 0 ) {
			$health['warnings'][] = array(
				'code'     => 'high_stock_depletion_risk',
				'message'  => sprintf(
					/* translators: %d: number of products */
					_n( '%d product at high risk of stockout during campaign', '%d products at high risk of stockout during campaign', $stock_risk_data['high_risk_count'], 'smart-cycle-discounts' ),
					$stock_risk_data['high_risk_count']
				),
				'category' => 'stock',
			);
			$penalty             += self::PENALTY_HIGH;
			$status               = 'warning';
		}

		if ( $stock_risk_data['medium_risk_count'] > 0 ) {
			$health['info'][] = array(
				'code'     => 'medium_stock_depletion_risk',
				'message'  => sprintf(
					/* translators: %d: number of products */
					_n( '%d product may run low on stock during campaign', '%d products may run low on stock during campaign', $stock_risk_data['medium_risk_count'], 'smart-cycle-discounts' ),
					$stock_risk_data['medium_risk_count']
				),
				'category' => 'stock',
			);
			$penalty         += self::PENALTY_MEDIUM;
		}

		// Track breakdown
		$health['breakdown']['stock'] = array(
			'penalty'           => $penalty,
			'status'            => $status,
			'high_risk_count'   => $stock_risk_data['high_risk_count'],
			'medium_risk_count' => $stock_risk_data['medium_risk_count'],
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check conflicts with other campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $health      Health data array.
	 * @param    array $context     Additional context data.
	 * @return   array                 Updated health data.
	 */
	private function check_conflicts( $campaign, $health, $context, $view_context ) {
		$penalty = 0;
		$status  = 'healthy';

		if ( isset( $context['conflicts_data']['has_conflicts'] ) && $context['conflicts_data']['has_conflicts'] ) {
			$total_blocked = isset( $context['conflicts_data']['total_products_blocked'] ) ? $context['conflicts_data']['total_products_blocked'] : 0;

			if ( $total_blocked > 0 ) {
				$health['warnings'][] = array(
					'code'     => 'campaign_conflicts',
					'message'  => sprintf(
						/* translators: %d: number of products blocked */
						_n( '%d product blocked by higher-priority campaigns', '%d products blocked by higher-priority campaigns', $total_blocked, 'smart-cycle-discounts' ),
						$total_blocked
					),
					'category' => 'conflicts',
				);
				$penalty += self::PENALTY_HIGH;
				$status               = 'warning';
			}
		} else {
			// Smart conflict detection: check for actual product overlap with other campaigns
			if ( ! isset( $campaign['id'] ) || ! isset( $campaign['priority'] ) ) {
				// Skip if campaign doesn't have ID or priority
				return $health;
			}

			global $wpdb;
			$table_name     = $wpdb->prefix . 'wsscd_campaigns';
			$campaign_id    = $campaign['id'];
			$priority       = intval( $campaign['priority'] );
			$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Conflict detection query; results are cached at higher level. Table name is constructed with $wpdb->prefix, not user input.
			$other_campaigns = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, priority, product_selection_type, product_ids, category_ids, tag_ids, starts_at, ends_at
					FROM {$table_name}
					WHERE id != %d
					AND status IN ('active', 'scheduled')
					AND deleted_at IS NULL",
					$campaign_id
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter

			// Normalize field names for compatibility with health check code
			foreach ( $other_campaigns as &$other_campaign ) {
				if ( isset( $other_campaign['starts_at'] ) ) {
					$other_campaign['start_date'] = $other_campaign['starts_at'];
				}
				if ( isset( $other_campaign['ends_at'] ) ) {
					$other_campaign['end_date'] = $other_campaign['ends_at'];
				}
			}
			unset( $other_campaign );

			if ( empty( $other_campaigns ) ) {
				// No other campaigns to conflict with
				return $health;
			}

			$same_priority_overlaps   = array();
			$higher_priority_overlaps = array();
			$date_overlap_campaigns   = array();

			foreach ( $other_campaigns as $other ) {
				$other_priority       = intval( $other['priority'] );
				$other_selection_type = $other['product_selection_type'];

				$has_product_overlap = $this->check_product_overlap( $campaign, $other, $selection_type, $other_selection_type );

				if ( $has_product_overlap ) {
					// CRITICAL FIX: Check date overlap for actual conflicts
					// Scheduled campaigns that run at different times don't conflict!
					$has_date_overlap = $this->check_date_overlap( $campaign, $other );

					// Only report conflicts if BOTH product AND date overlap
					if ( $has_date_overlap ) {
						if ( $other_priority === $priority ) {
							$same_priority_overlaps[] = $other['name'];
						} elseif ( $other_priority > $priority ) {
							// Higher numbers = higher priority (e.g., priority 5 blocks priority 1)
							$higher_priority_overlaps[] = $other['name'];
						}

						// Track for date overlap awareness message
						$date_overlap_campaigns[] = array(
							'name'     => $other['name'],
							'priority' => $other_priority,
						);
					}
				}
			}

			// Critical: Same priority with product overlap (ambiguous which one wins)
			if ( ! empty( $same_priority_overlaps ) ) {
				$health['critical_issues'][] = array(
					'code'                  => 'same_priority_product_conflict',
					'message'               => sprintf(
						/* translators: 1: number of campaigns, 2: priority level */
						_n( 'Conflicts with %1$d campaign at priority %2$d targeting the same products - change priority to resolve', '%1$d campaigns at priority %2$d target the same products - change priority to resolve', count( $same_priority_overlaps ), 'smart-cycle-discounts' ),
						count( $same_priority_overlaps ),
						$priority
					),
					'category'              => 'conflicts',
					'conflicting_campaigns' => $same_priority_overlaps,
				);
				$penalty += self::PENALTY_CRITICAL_STANDARD;
				$status                      = 'critical';
			}

			// Warning: Higher priority campaigns blocking this one
			if ( ! empty( $higher_priority_overlaps ) ) {
				$health['warnings'][] = array(
					'code'               => 'blocked_by_higher_priority',
					'message'            => sprintf(
						/* translators: %d: number of campaigns */
						_n( '%d higher-priority campaign targets the same products and will take precedence', '%d higher-priority campaigns target the same products and will take precedence', count( $higher_priority_overlaps ), 'smart-cycle-discounts' ),
						count( $higher_priority_overlaps )
					),
					'category'           => 'conflicts',
					'blocking_campaigns' => $higher_priority_overlaps,
				);
				$penalty += self::PENALTY_HIGH;
				if ( 'critical' !== $status ) {
					$status = 'warning';
				}
			}

			// Info: Date overlap awareness (not necessarily a problem - priorities handle it)
			if ( ! empty( $date_overlap_campaigns ) ) {
				$campaign_names = array_map(
					function ( $c ) {
						return $c['name'];
					},
					$date_overlap_campaigns
				);

				$health['info'][] = array(
					'code'                  => 'date_overlap_awareness',
					'message'               => sprintf(
						/* translators: %d: number of campaigns */
						_n( 'Runs simultaneously with %d other campaign on same products (priority determines which applies)', 'Runs simultaneously with %d other campaigns on same products (priority determines which applies)', count( $date_overlap_campaigns ), 'smart-cycle-discounts' ),
						count( $date_overlap_campaigns )
					),
					'category'              => 'conflicts',
					'overlapping_campaigns' => $campaign_names,
				);
				// No penalty for info messages
			}
		}

		// Track breakdown
		$health['breakdown']['conflicts'] = array(
			'penalty' => $penalty,
			'status'  => $status,
		);

		$health['score'] -= $penalty;
		return $health;
	}

	/**
	 * Check if two campaigns have overlapping product selections.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign1        First campaign data.
	 * @param    array  $campaign2        Second campaign data.
	 * @param    string $type1            Selection type of first campaign.
	 * @param    string $type2            Selection type of second campaign.
	 * @return   bool                        True if campaigns overlap.
	 */
	private function check_product_overlap( $campaign1, $campaign2, $type1, $type2 ) {
		// Handle pool-based selections - must check category filters!
		// Pool-based with category filter = products in those categories
		// Pool-based without category filter = all products in the store
		if ( WSSCD_Campaign::is_pool_based_selection( $type1 ) || WSSCD_Campaign::is_pool_based_selection( $type2 ) ) {
			$cats1 = $this->get_campaign_category_ids( $campaign1 );
			$cats2 = $this->get_campaign_category_ids( $campaign2 );

			// Both have no category filter = entire store, definitely overlap
			if ( empty( $cats1 ) && empty( $cats2 ) ) {
				return true;
			}

			// One has no filter (entire store), other has filter = overlap
			// (products in any category are a subset of all products)
			if ( empty( $cats1 ) || empty( $cats2 ) ) {
				return true;
			}

			// Both have category filters - check for category intersection
			$cat_intersection = array_intersect( $cats1, $cats2 );
			return ! empty( $cat_intersection );
		}

		// Both specific products - check for intersection
		if ( 'specific_products' === $type1 && 'specific_products' === $type2 ) {
			$products1 = isset( $campaign1['selected_product_ids'] ) ? $campaign1['selected_product_ids'] : ( isset( $campaign1['product_ids'] ) ? $campaign1['product_ids'] : array() );
			$products2 = isset( $campaign2['selected_product_ids'] ) ? $campaign2['selected_product_ids'] : ( isset( $campaign2['product_ids'] ) ? $campaign2['product_ids'] : array() );

			// Handle serialized data
			if ( is_string( $products1 ) ) {
				$products1 = maybe_unserialize( $products1 );
			}
			if ( is_string( $products2 ) ) {
				$products2 = maybe_unserialize( $products2 );
			}

			if ( ! is_array( $products1 ) ) {
				$products1 = array();
			}
			if ( ! is_array( $products2 ) ) {
				$products2 = array();
			}

			$intersection = array_intersect( $products1, $products2 );
			return ! empty( $intersection );
		}

		// Note: 'categories' and 'tags' are NOT selection types in the current model.
		// They are FILTERS applied to pool-based selections.
		// The pool-based check above handles category filtering correctly.

		// Mixed selection types or unknown - assume no overlap (conservative approach)
		return false;
	}

	/**
	 * Get category IDs from campaign data.
	 *
	 * Extracts and normalizes category IDs from various campaign data formats.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data array.
	 * @return   array                 Array of category IDs (integers).
	 */
	private function get_campaign_category_ids( $campaign ) {
		// Try different field names used in different contexts
		$cats = isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : ( isset( $campaign['selected_category_ids'] ) ? $campaign['selected_category_ids'] : array() );

		// Handle JSON-encoded data from database
		if ( is_string( $cats ) ) {
			$decoded = json_decode( $cats, true );
			if ( is_array( $decoded ) ) {
				$cats = $decoded;
			} else {
				$cats = maybe_unserialize( $cats );
			}
		}

		if ( ! is_array( $cats ) ) {
			return array();
		}

		// Filter out 'all' marker and empty values, convert to integers
		$cats = array_filter( $cats, function( $id ) {
			return 'all' !== $id && '' !== $id && null !== $id;
		} );

		return array_map( 'intval', $cats );
	}

	/**
	 * Check if two campaigns have overlapping date ranges.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign1    First campaign data.
	 * @param    array $campaign2    Second campaign data.
	 * @return   bool                   True if date ranges overlap.
	 */
	private function check_date_overlap( $campaign1, $campaign2 ) {
		$start1 = isset( $campaign1['start_date'] ) ? $campaign1['start_date'] : '';
		$end1   = isset( $campaign1['end_date'] ) ? $campaign1['end_date'] : '';
		$start2 = isset( $campaign2['start_date'] ) ? $campaign2['start_date'] : '';
		$end2   = isset( $campaign2['end_date'] ) ? $campaign2['end_date'] : '';

		// If any campaign has no dates, can't determine overlap
		if ( empty( $start1 ) || empty( $start2 ) ) {
			return false;
		}

		// If either campaign has no end date (indefinite), check if starts overlap
		if ( empty( $end1 ) || empty( $end2 ) ) {
			// Indefinite campaigns - if one started before the other ends, they overlap
			return true;
		}

		// Both campaigns have start and end dates - check for overlap
		// Overlap exists if: start1 <= end2 AND start2 <= end1
		try {
			$timezone    = wp_timezone();
			$start1_time = new DateTime( $start1, $timezone );
			$end1_time   = new DateTime( $end1, $timezone );
			$start2_time = new DateTime( $start2, $timezone );
			$end2_time   = new DateTime( $end2, $timezone );

			return ( $start1_time <= $end2_time ) && ( $start2_time <= $end1_time );
		} catch ( Exception $e ) {
			// If date parsing fails, assume no overlap
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
				error_log( 'WSSCD Date overlap check error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Generate recommendations for campaign improvement.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $health      Current health analysis.
	 * @param    array $context     Additional context data.
	 * @return   array                 Array of recommendations.
	 */
	private function generate_recommendations( $campaign, $health, $context ) {
		$recommendations = array();

		// Generic campaign name recommendation
		if ( isset( $campaign['name'] ) && ! empty( $campaign['name'] ) ) {
			$name          = trim( $campaign['name'] );
			$generic_names = array( 'test', 'new campaign', 'campaign', 'discount', 'sale', 'temp', 'untitled' );
			$name_lower    = strtolower( $name );

			foreach ( $generic_names as $generic ) {
				if ( $generic === $name_lower || 0 === strpos( $name_lower, $generic ) || 10 > strlen( $name ) ) {
					$recommendations[] = array(
						'category' => 'management',
						'priority' => 'medium',
						'message'  => sprintf(
							/* translators: %s: campaign name */
							__( 'Campaign name "%s" is too generic - use a descriptive name with timeframe', 'smart-cycle-discounts' ),
							$name
						),
					);
					break;
				}
			}
		}

		// Missing description recommendation
		if ( empty( $campaign['description'] ) ) {
			$recommendations[] = array(
				'category' => 'management',
				'priority' => 'low',
				'message'  => __( 'Add description to document campaign goals and expected outcomes', 'smart-cycle-discounts' ),
			);
		}

		// No end date recommendation
		// NOTE: Priority is 'medium' not 'high' because:
		// 1. Indefinite campaigns are a valid choice (evergreen promotions, loyalty programs)
		// 2. This is a best practice suggestion, not a requirement
		// 3. Consistent with other similar recommendations (usage limits, badges, min order)
		if ( empty( $campaign['end_date'] ) ) {
			$recommendations[] = array(
				'category' => 'timing',
				'priority' => 'medium',
				'message'  => __( 'Consider a 7-14 day duration - limited-time offers create urgency', 'smart-cycle-discounts' ),
			);
		}

		// Badge recommendation
		$badge_enabled = isset( $campaign['badge_enabled'] ) ? $campaign['badge_enabled'] : true;
		if ( ! $badge_enabled ) {
			$recommendations[] = array(
				'category' => 'visibility',
				'priority' => 'medium',
				'message'  => __( 'Enable product badge to highlight discounted items (increases visibility by 40%)', 'smart-cycle-discounts' ),
			);
		}

		// Usage limits recommendation
		$usage_limit = isset( $campaign['usage_limit_per_customer'] ) ? intval( $campaign['usage_limit_per_customer'] ) : 0;
		if ( 0 === $usage_limit ) {
			$recommendations[] = array(
				'category' => 'protection',
				'priority' => 'medium',
				'message'  => __( 'Set per-customer usage limits to prevent abuse', 'smart-cycle-discounts' ),
			);
		}

		// Minimum order recommendation for higher discounts
		$discount_type  = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : '';
		$discount_value = isset( $campaign['discount_value'] ) ? floatval( $campaign['discount_value'] ) : 0;
		$minimum_order  = isset( $campaign['minimum_order_amount'] ) ? floatval( $campaign['minimum_order_amount'] ) : 0;

		if ( 'percentage' === $discount_type && $discount_value >= 20 && 0 === $minimum_order ) {
			$recommendations[] = array(
				'category' => 'protection',
				'priority' => 'medium',
				'message'  => __( 'Add minimum order amount to protect margins on high discounts', 'smart-cycle-discounts' ),
			);
		}

		// Conditions recommendation
		$conditions = isset( $campaign['conditions'] ) ? $campaign['conditions'] : array();
		if ( empty( $conditions ) && $discount_value >= 30 ) {
			$recommendations[] = array(
				'category' => 'targeting',
				'priority' => 'low',
				'message'  => __( 'Add conditions (e.g., customer role, cart value) for targeted discounts', 'smart-cycle-discounts' ),
			);
		}

		// Product organization recommendation
		$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';
		if ( 'specific_products' === $selection_type ) {
			$recommendations[] = array(
				'category' => 'organization',
				'priority' => 'low',
				'message'  => __( 'Use product tags for better organization and filtering of campaigns', 'smart-cycle-discounts' ),
			);
		}

		// Timing strategy recommendations
		if ( ! empty( $campaign['start_date'] ) ) {
			$start_timestamp = strtotime( $campaign['start_date'] );
			if ( $start_timestamp ) {
				$day_of_week = gmdate( 'N', $start_timestamp );
				// Monday = 1, Sunday = 7
				if ( $day_of_week >= 5 ) {
					// Friday, Saturday, Sunday
					$recommendations[] = array(
						'category' => 'timing',
						'priority' => 'low',
						'message'  => __( 'Campaigns starting Monday-Wednesday see 15% higher engagement than weekend launches', 'smart-cycle-discounts' ),
					);
				}
			}
		}

		// Discount strategy recommendations based on product analysis
		$product_ids = $this->normalize_product_ids( $campaign );

		if ( count( $product_ids ) > 0 && count( $product_ids ) <= 50 ) {
			$prices = $this->get_product_prices( $product_ids, self::PRICE_ANALYSIS_LIMIT );

			if ( ! empty( $prices ) ) {
				$avg_price = array_sum( $prices ) / count( $prices );

				// Tiered discount recommendation for mid-high priced products
				if ( 'percentage' === $discount_type && $avg_price >= 30 ) {
					$recommendations[] = array(
						'category' => 'strategy',
						'priority' => 'medium',
						'message'  => sprintf(
							/* translators: %s: average product price */
							__( 'Products average $%s - consider tiered discount to encourage bulk purchases', 'smart-cycle-discounts' ),
							number_format( $avg_price, 2 )
						),
					);
				}

				// BOGO recommendation for low-priced products
				if ( 'percentage' === $discount_type && $avg_price < 20 ) {
					$recommendations[] = array(
						'category' => 'strategy',
						'priority' => 'medium',
						'message'  => sprintf(
							/* translators: %s: average product price */
							__( 'Low-priced products ($%s avg) - BOGO often outperforms percentage discounts', 'smart-cycle-discounts' ),
							number_format( $avg_price, 2 )
						),
					);
				}

				// Spend threshold for high-value products
				if ( 'percentage' === $discount_type && $avg_price > 100 ) {
					$recommendations[] = array(
						'category' => 'strategy',
						'priority' => 'medium',
						'message'  => __( 'High-value products - spend threshold discount rewards big spenders effectively', 'smart-cycle-discounts' ),
					);
				}
			}
		}

		// Recurring schedule recommendation for long campaigns
		if ( ! empty( $campaign['start_date'] ) && ! empty( $campaign['end_date'] ) ) {
			$start_timestamp = strtotime( $campaign['start_date'] );
			$end_timestamp   = strtotime( $campaign['end_date'] );
			if ( $start_timestamp && $end_timestamp ) {
				$duration_days    = ( $end_timestamp - $start_timestamp ) / DAY_IN_SECONDS;
				$enable_recurring = isset( $campaign['enable_recurring'] ) ? $campaign['enable_recurring'] : false;

				if ( $duration_days > 30 && ! $enable_recurring ) {
					$recommendations[] = array(
						'category' => 'management',
						'priority' => 'low',
						'message'  => __( 'For ongoing promotions, use recurring schedule instead of long duration', 'smart-cycle-discounts' ),
					);
				}
			}
		}

		return $recommendations;
	}

	/**
	 * Normalize product IDs from campaign data.
	 *
	 * Helper method to eliminate code duplication.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @return   array                 Normalized product IDs array.
	 */
	private function normalize_product_ids( $campaign ) {
		$product_ids = isset( $campaign['selected_product_ids'] ) ? $campaign['selected_product_ids'] : array();

		if ( is_string( $product_ids ) ) {
			// Try JSON decode first (database format), then fall back to unserialize
			$decoded = json_decode( $product_ids, true );
			if ( null !== $decoded && is_array( $decoded ) ) {
				$product_ids = $decoded;
			} else {
				$product_ids = maybe_unserialize( $product_ids );
			}
		}

		if ( ! is_array( $product_ids ) ) {
			$product_ids = array();
		}

		return $product_ids;
	}

	/**
	 * Get product prices for analysis.
	 *
	 * Helper method to fetch product prices with limit for performance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Array of product IDs.
	 * @param    int   $limit          Maximum products to check (default 50).
	 * @return   array                    Array of prices.
	 */
	private function get_product_prices( $product_ids, $limit = 50 ) {
		$prices = array();

		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return $prices;
		}

		$check_limit = min( count( $product_ids ), $limit );

		for ( $i = 0; $i < $check_limit; $i++ ) {
			$product = wc_get_product( $product_ids[ $i ] );
			if ( $product ) {
				$price = $product->get_price();
				if ( $price > 0 ) {
					$prices[] = floatval( $price );
				}
			}
		}

		return $prices;
	}

	/**
	 * Calculate simple coverage for campaign (resolve products from filters).
	 *
	 * Simpler version of Preview_Coverage_Handler for dashboard health checks.
	 * Just resolves which products match the campaign criteria.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data array.
	 * @return   array                 Coverage data with resolved product_ids.
	 */
	private function calculate_simple_coverage( $campaign ) {
		$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : 'all_products';
		$product_ids    = array();

		// For "all_products" with category filter, get products in those categories
		if ( 'all_products' === $selection_type ) {
			$category_ids = isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : array();
			if ( is_string( $category_ids ) ) {
				$decoded      = json_decode( $category_ids, true );
				$category_ids = ( null !== $decoded && is_array( $decoded ) ) ? $decoded : array();
			}

			if ( ! empty( $category_ids ) ) {
				// Convert category IDs to slugs (wc_get_products 'category' param expects slugs, not IDs)
				$terms = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'include'    => array_map( 'intval', $category_ids ),
						'hide_empty' => false,
						'fields'     => 'id=>slug',
					)
				);

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$category_slugs = array_values( $terms );
					$args           = array(
						'status'   => 'publish',
						'limit'    => 100, // Limit for performance
						'category' => $category_slugs,
						'return'   => 'ids',
					);
					$product_ids    = wc_get_products( $args );
				}
			}
		}
		// For "specific_products", use the stored product_ids
		elseif ( 'specific_products' === $selection_type ) {
			$product_ids = $this->normalize_product_ids( $campaign );
		}

		return array(
			'product_ids'    => $product_ids,
			'selection_type' => $selection_type,
		);
	}

	/**
	 * Get status from issues (severity-based).
	 *
	 * IMPROVED LOGIC:
	 * - Status determined by WORST issue severity (critical > warning > none)
	 * - Score used as secondary metric within same severity level
	 * - Critical issues force "critical" status
	 * - Prevents minor issues from hiding critical problems
	 * - Uses defined constants for consistency
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $health    Health analysis data.
	 * @return   string              Status label.
	 */
	private function get_status_from_issues( $health ) {
		$score        = $health['score'];
		$has_critical = ! empty( $health['critical_issues'] );
		$has_warnings = ! empty( $health['warnings'] );

		// CRITICAL issues = Always critical status (campaign is broken)
		if ( $has_critical ) {
			return 'critical';
		}

		// WARNINGS only = Score-based but capped at "good" (can't be excellent with warnings)
		if ( $has_warnings ) {
			if ( $score >= self::STATUS_GOOD_MIN ) {
				return 'good';
			} elseif ( $score >= self::STATUS_FAIR_MIN ) {
				return 'fair';
			} else {
				return 'poor';
			}
		}

		// NO issues = Score-based (excellent possible)
		if ( $score >= self::STATUS_EXCELLENT_MIN ) {
			return 'excellent';
		} elseif ( $score >= self::STATUS_GOOD_MIN ) {
			return 'good';
		} elseif ( $score >= self::STATUS_FAIR_MIN ) {
			return 'fair';
		} else {
			return 'poor';
		}
	}

	/**
	 * Analyze multiple campaigns and return aggregated health data.
	 *
	 * Used by dashboard to get overall health metrics across all campaigns.
	 *
	 * @since    1.0.0
	 * @param    array  $campaigns    Array of campaign data.
	 * @param    string $mode         Analysis mode.
	 * @return   array                   Aggregated health data.
	 */
	public function analyze_campaigns( $campaigns, $mode = 'standard' ) {
		if ( empty( $campaigns ) ) {
			return $this->get_empty_aggregate_health();
		}

		$total_score         = 0;
		$campaign_count      = count( $campaigns );
		$all_critical_issues = array();
		$all_warnings        = array();
		$categories_data     = array(
			'configuration' => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
			'products'      => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
			'schedule'      => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
			'discount'      => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
			'coverage'      => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
			'stock'         => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
			'conflicts'     => array(
				'healthy'  => 0,
				'warning'  => 0,
				'critical' => 0,
			),
		);

		foreach ( $campaigns as $campaign ) {
			$campaign_name   = isset( $campaign['name'] ) ? $campaign['name'] : 'Unknown';
			$campaign_status = isset( $campaign['status'] ) ? $campaign['status'] : 'unknown';
			$campaign_id     = isset( $campaign['id'] ) ? $campaign['id'] : 0;

			$coverage_data = $this->calculate_simple_coverage( $campaign );

			// Pass context with coverage data to ensure consistent health analysis
			$context  = array(
				'coverage_data' => $coverage_data,
				'view_context'  => 'dashboard',
			);
			$analysis = $this->analyze_health( $campaign, $mode, $context );

			// Aggregate scores
			$total_score += $analysis['score'];

			$campaign_data = $this->normalize_campaign_data( $campaign );
			$campaign_id   = isset( $campaign_data['id'] ) ? $campaign_data['id'] : 0;
			$campaign_name = isset( $campaign_data['name'] ) ? $campaign_data['name'] : __( 'Unknown Campaign', 'smart-cycle-discounts' );

			// Collect issues (limit to prevent overload) and add campaign context
			foreach ( $analysis['critical_issues'] as $issue ) {
				if ( count( $all_critical_issues ) < 10 ) {
					$issue['campaign_id']   = $campaign_id;
					$issue['campaign_name'] = $campaign_name;
					$all_critical_issues[]  = $issue;
				}
			}

			foreach ( $analysis['warnings'] as $warning ) {
				if ( count( $all_warnings ) < 10 ) {
					$warning['campaign_id']   = $campaign_id;
					$warning['campaign_name'] = $campaign_name;
					$all_warnings[]           = $warning;
				}
			}

			// Aggregate category statuses
			if ( isset( $analysis['breakdown'] ) ) {
				foreach ( $analysis['breakdown'] as $category => $data ) {
					$status = isset( $data['status'] ) ? $data['status'] : 'healthy';
					if ( isset( $categories_data[ $category ][ $status ] ) ) {
						++$categories_data[ $category ][ $status ];
					}
				}
			}
		}

		// Deduplicate critical issues - group by code
		$deduplicated_critical_issues = array();
		$critical_groups              = array();

		foreach ( $all_critical_issues as $issue ) {
			$code    = isset( $issue['code'] ) ? $issue['code'] : 'unknown';
			$message = isset( $issue['message'] ) ? $issue['message'] : '';

			$key = $code . '|' . $message;

			if ( ! isset( $critical_groups[ $key ] ) ) {
				$critical_groups[ $key ] = array(
					'issue'     => $issue,
					'campaigns' => array(),
				);
			}

			// Track which campaigns have this issue
			if ( isset( $issue['campaign_name'] ) ) {
				$critical_groups[ $key ]['campaigns'][] = $issue['campaign_name'];
			}
		}

		foreach ( $critical_groups as $group ) {
			$issue = $group['issue'];

			// For issues affecting multiple campaigns, append campaign list
			if ( count( $group['campaigns'] ) > 1 ) {
				$issue['affected_campaigns'] = $group['campaigns'];
				$issue['campaign_name']      = sprintf(
					/* translators: %d: number of campaigns */
					_n( '%d campaign', '%d campaigns', count( $group['campaigns'] ), 'smart-cycle-discounts' ),
					count( $group['campaigns'] )
				);
			}

			$deduplicated_critical_issues[] = $issue;
		}

		// Deduplicate warnings - group by code
		$deduplicated_warnings = array();
		$warning_groups        = array();

		foreach ( $all_warnings as $warning ) {
			$code    = isset( $warning['code'] ) ? $warning['code'] : 'unknown';
			$message = isset( $warning['message'] ) ? $warning['message'] : '';

			$key = $code . '|' . $message;

			if ( ! isset( $warning_groups[ $key ] ) ) {
				$warning_groups[ $key ] = array(
					'warning'   => $warning,
					'campaigns' => array(),
				);
			}

			// Track which campaigns have this warning
			if ( isset( $warning['campaign_name'] ) ) {
				$warning_groups[ $key ]['campaigns'][] = $warning['campaign_name'];
			}
		}

		foreach ( $warning_groups as $group ) {
			$warning = $group['warning'];

			// For warnings affecting multiple campaigns, append campaign list
			if ( count( $group['campaigns'] ) > 1 ) {
				$warning['affected_campaigns'] = $group['campaigns'];
				$warning['campaign_name']      = sprintf(
					/* translators: %d: number of campaigns */
					_n( '%d campaign', '%d campaigns', count( $group['campaigns'] ), 'smart-cycle-discounts' ),
					count( $group['campaigns'] )
				);
			}

			$deduplicated_warnings[] = $warning;
		}

		$average_score = $campaign_count > 0 ? round( $total_score / $campaign_count ) : 100;


		$aggregate_health = array(
			'score'           => $average_score,
			'critical_issues' => $deduplicated_critical_issues,
			'warnings'        => $deduplicated_warnings,
		);
		$overall_status   = $this->get_status_from_issues( $aggregate_health );

		return array(
			'overall_status'           => $overall_status,
			'overall_score'            => $average_score,
			'total_campaigns_analyzed' => $campaign_count,
			'critical_issues'          => $deduplicated_critical_issues,
			'warnings'                 => $deduplicated_warnings,
			'categories_data'          => $categories_data,
			'timestamp'                => current_time( 'timestamp' ),
		);
	}

	/**
	 * Calculate average daily sales for a product.
	 *
	 * Uses WooCommerce HPOS-compatible wc_get_orders() API to calculate
	 * sales velocity. Handles both simple products and variations.
	 * Falls back gracefully to 0 if data is unavailable.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $product_id    Product ID.
	 * @param    int $days          Number of days to look back.
	 * @return   float                 Average daily sales.
	 */
	private function get_average_daily_sales( $product_id, $days = 30 ) {
		if ( ! $product_id || $days <= 0 ) {
			return 0;
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		try {
			$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			// Use HPOS-compatible wc_get_orders API
			$orders = wc_get_orders(
				array(
					'limit'        => -1,
					'status'       => array( 'wc-completed', 'wc-processing' ),
					'date_created' => '>' . $date_from,
					'return'       => 'ids',
				)
			);

			if ( empty( $orders ) ) {
				return 0;
			}

			$total_quantity = 0;

			foreach ( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					continue;
				}

				foreach ( $order->get_items() as $item ) {
					$item_product_id   = $item->get_product_id();
					$item_variation_id = $item->get_variation_id();

					// Match product ID or variation ID
					if ( $item_product_id === $product_id || $item_variation_id === $product_id ) {
						$total_quantity += $item->get_quantity();
					}
				}
			}

			return $total_quantity / $days;

		} catch ( Exception $e ) {
			// Log error but don't break the health check
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
				error_log( 'WSSCD Health Service: Error calculating average daily sales - ' . $e->getMessage() );
			}
			return 0;
		}
	}

	/**
	 * Get campaign duration in days.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @return   int                   Duration in days (default 7 if no end date).
	 */
	private function get_campaign_duration_days( $campaign ) {
		$start_date = isset( $campaign['start_date'] ) ? $campaign['start_date'] : null;
		$end_date   = isset( $campaign['end_date'] ) ? $campaign['end_date'] : null;

		if ( ! $end_date ) {
			return 7; // Default 7 days if no end date
		}

		if ( ! $start_date ) {
			$start_date = current_time( 'Y-m-d' );
		}

		$start = strtotime( $start_date );
		$end   = strtotime( $end_date );

		if ( false === $start || false === $end ) {
			return 7;
		}

		$days = max( 1, ceil( ( $end - $start ) / DAY_IN_SECONDS ) );
		return $days;
	}

	/**
	 * Estimate discount boost factor on sales.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $discount_type     Discount type.
	 * @param    float  $discount_value    Discount value.
	 * @return   float                        Boost multiplier.
	 */
	private function estimate_discount_boost( $discount_type, $discount_value ) {
		if ( 'percentage' === $discount_type ) {
			// Higher discounts = bigger boost
			if ( $discount_value >= self::DISCOUNT_VERY_HIGH_THRESHOLD ) {
				return 2.0; // 100% boost for 90%+ discounts
			} elseif ( $discount_value >= self::DISCOUNT_HIGH_THRESHOLD ) {
				return self::DISCOUNT_BOOST_PERCENTAGE; // 50% boost for 70%+ discounts
			} elseif ( $discount_value >= self::DISCOUNT_SWEET_SPOT_MAX ) {
				return 1.3; // 30% boost for 50%+ discounts
			} else {
				return 1.2; // 20% boost for smaller discounts
			}
		} elseif ( 'fixed' === $discount_type ) {
			return self::DISCOUNT_BOOST_FIXED; // 30% boost for fixed discounts
		}

		return 1.1; // 10% boost for BOGO/tiered
	}

	/**
	 * Calculate stock depletion risk for products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids          Product IDs to check.
	 * @param    array $campaign             Campaign data.
	 * @return   array                          Stock risk data.
	 */
	private function calculate_stock_risk( $product_ids, $campaign ) {
		if ( empty( $product_ids ) ) {
			return array(
				'has_risk'          => false,
				'high_risk_count'   => 0,
				'medium_risk_count' => 0,
				'products'          => array(),
			);
		}

		$discount_type  = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : 'percentage';
		$discount_value = isset( $campaign['discount_value'] ) ? floatval( $campaign['discount_value'] ) : 0;
		$campaign_days  = $this->get_campaign_duration_days( $campaign );
		$discount_boost = $this->estimate_discount_boost( $discount_type, $discount_value );

		$high_risk_count   = 0;
		$medium_risk_count = 0;
		$risk_products     = array();

		// Only check first 100 products for performance
		$check_products = array_slice( $product_ids, 0, 100 );

		foreach ( $check_products as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || is_wp_error( $product ) || ! $product->managing_stock() ) {
				continue;
			}

			$stock = $product->get_stock_quantity();

			if ( null === $stock || $stock <= 0 ) {
				continue; // Already out of stock or not managing stock
			}

			$avg_daily_sales  = $this->get_average_daily_sales( $product_id, self::HISTORICAL_DAYS_LOOKBACK );
			$estimated_demand = $avg_daily_sales * $campaign_days * $discount_boost;

			// Skip if no sales history
			if ( $estimated_demand < 1 ) {
				continue;
			}

			// Determine risk level
			$risk_level = null;

			if ( $stock < $estimated_demand * self::STOCK_RISK_HIGH_MULTIPLIER ) {
				$risk_level = 'high';
				++$high_risk_count;
			} elseif ( $stock < $estimated_demand * self::STOCK_RISK_MEDIUM_MULTIPLIER ) {
				$risk_level = 'medium';
				++$medium_risk_count;
			}

			if ( $risk_level ) {
				$risk_products[] = array(
					'product_id'       => $product_id,
					'name'             => $product->get_name(),
					'stock'            => $stock,
					'estimated_demand' => round( $estimated_demand ),
					'risk_level'       => $risk_level,
				);
			}
		}

		return array(
			'has_risk'          => $high_risk_count > 0 || $medium_risk_count > 0,
			'high_risk_count'   => $high_risk_count,
			'medium_risk_count' => $medium_risk_count,
			'products'          => $risk_products,
		);
	}

	/**
	 * Get empty aggregate health data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Empty health data structure.
	 */
	private function get_empty_aggregate_health() {
		return array(
			'overall_status'           => 'excellent',
			'overall_score'            => 100,
			'total_campaigns_analyzed' => 0,
			'critical_issues'          => array(),
			'warnings'                 => array(),
			'categories_data'          => array(
				'configuration' => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
				'products'      => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
				'schedule'      => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
				'discount'      => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
				'coverage'      => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
				'stock'         => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
				'conflicts'     => array(
					'healthy'  => 0,
					'warning'  => 0,
					'critical' => 0,
				),
			),
			'timestamp'                => current_time( 'timestamp' ),
		);
	}

	/**
	 * ========================================================================
	 * PRACTICAL INTELLIGENCE FEATURES
	 * ========================================================================
	 * Statistical analysis and pattern recognition without AI/ML
	 */

	/**
	 * Assess multi-dimensional risk for campaign.
	 *
	 * Analyzes 5 key risk dimensions using statistical methods and historical data.
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context (analytics_repo, campaign_repo).
	 * @return   array                 Risk assessment data.
	 */
	public function assess_multi_dimensional_risk( $campaign, $context = array() ) {
		$risk_assessment = array(
			'overall_risk_level' => 'low',
			'overall_risk_score' => 100,
			'dimensions'         => array(),
		);

		// 1. Profit Margin Risk
		$risk_assessment['dimensions']['profit_margin'] = $this->assess_profit_margin_risk( $campaign, $context );

		// 2. Inventory Risk
		$risk_assessment['dimensions']['inventory'] = $this->assess_inventory_risk( $campaign, $context );

		// 3. Market Timing Risk
		$risk_assessment['dimensions']['market_timing'] = $this->assess_market_timing_risk( $campaign, $context );

		// 4. Competitive Risk
		$risk_assessment['dimensions']['competitive'] = $this->assess_competitive_risk( $campaign, $context );

		// 5. Execution Risk
		$risk_assessment['dimensions']['execution'] = $this->assess_execution_risk( $campaign, $context );

		// Calculate overall risk
		$total_score    = 0;
		$dimension_count = 0;
		foreach ( $risk_assessment['dimensions'] as $dimension ) {
			$total_score += $dimension['score'];
			$dimension_count++;
		}

		$risk_assessment['overall_risk_score'] = $dimension_count > 0 ? round( $total_score / $dimension_count ) : 100;

		// Determine overall risk level
		if ( $risk_assessment['overall_risk_score'] >= 80 ) {
			$risk_assessment['overall_risk_level'] = 'low';
		} elseif ( $risk_assessment['overall_risk_score'] >= 60 ) {
			$risk_assessment['overall_risk_level'] = 'medium';
		} elseif ( $risk_assessment['overall_risk_score'] >= 40 ) {
			$risk_assessment['overall_risk_level'] = 'high';
		} else {
			$risk_assessment['overall_risk_level'] = 'critical';
		}

		return $risk_assessment;
	}

	/**
	 * Assess profit margin risk.
	 *
	 * Analyzes risk of discount eroding profit margins below acceptable levels.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Profit margin risk assessment.
	 */
	private function assess_profit_margin_risk( $campaign, $context ) {
		$risk = array(
			'level'   => 'low',
			'score'   => 100,
			'factors' => array(),
		);

		$discount_type  = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : '';
		$discount_value = isset( $campaign['discount_value'] ) ? floatval( $campaign['discount_value'] ) : 0;

		// Fixed discount risk (harder to assess without knowing product prices)
		if ( 'fixed' === $discount_type ) {
			// Get product prices if available
			$product_ids = $this->normalize_product_ids( $campaign );
			if ( ! empty( $product_ids ) ) {
				$prices        = $this->get_product_prices( $product_ids, self::PRICE_ANALYSIS_LIMIT );
				$low_price_count = 0;
				$total_products  = count( $prices );

				foreach ( $prices as $price ) {
					// If fixed discount is more than 50% of product price, flag it
					if ( $discount_value > ( $price * 0.5 ) ) {
						$low_price_count++;
					}
				}

				if ( $total_products > 0 ) {
					$risk_percentage = ( $low_price_count / $total_products ) * 100;

					if ( $risk_percentage > 50 ) {
						$risk['level']     = 'high';
						$risk['score']     = 40;
						$risk['factors'][] = sprintf(
							'Fixed discount of %s affects %d%% of products (>50%% of their price)',
							wc_price( $discount_value ),
							round( $risk_percentage )
						);
					} elseif ( $risk_percentage > 25 ) {
						$risk['level']     = 'medium';
						$risk['score']     = 65;
						$risk['factors'][] = sprintf(
							'Fixed discount of %s affects %d%% of products (>50%% of their price)',
							wc_price( $discount_value ),
							round( $risk_percentage )
						);
					}
				}
			} else {
				// All products - high risk with fixed discount
				$risk['level']     = 'medium';
				$risk['score']     = 60;
				$risk['factors'][] = 'Fixed discount applied to all products - difficult to assess margin impact';
			}
		} elseif ( 'percentage' === $discount_type ) {
			// Percentage discount risk assessment
			if ( $discount_value >= 50 ) {
				$risk['level']     = 'high';
				$risk['score']     = 35;
				$risk['factors'][] = sprintf( '%d%% discount may significantly erode profit margins', $discount_value );
			} elseif ( $discount_value >= 30 ) {
				$risk['level']     = 'medium';
				$risk['score']     = 70;
				$risk['factors'][] = sprintf( '%d%% discount is substantial - monitor margin impact', $discount_value );
			} else {
				$risk['factors'][] = sprintf( '%d%% discount is within safe range for most products', $discount_value );
			}
		}

		// Check if campaign targets low-margin products (if we have that data)
		// This would require product metadata/categories analysis in future enhancement

		if ( empty( $risk['factors'] ) ) {
			$risk['factors'][] = 'Discount level appears safe for profit margins';
		}

		return $risk;
	}

	/**
	 * Assess inventory risk.
	 *
	 * Analyzes risk of running out of stock during campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Inventory risk assessment.
	 */
	private function assess_inventory_risk( $campaign, $context ) {
		$risk = array(
			'level'   => 'low',
			'score'   => 100,
			'factors' => array(),
		);

		$product_ids = $this->normalize_product_ids( $campaign );

		if ( empty( $product_ids ) || 'all_products' === ( isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '' ) ) {
			$risk['factors'][] = 'All products selected - inventory risk distributed across entire catalog';
			return $risk;
		}

		// Check stock levels for specific products
		$low_stock_count  = 0;
		$out_of_stock_count = 0;
		$total_checked    = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$total_checked++;
			$stock_quantity = $product->get_stock_quantity();

			if ( null === $stock_quantity ) {
				// Stock management disabled - skip
				continue;
			}

			if ( 0 === $stock_quantity ) {
				$out_of_stock_count++;
			} elseif ( $stock_quantity <= self::LOW_STOCK_THRESHOLD ) {
				$low_stock_count++;
			}
		}

		if ( $total_checked > 0 ) {
			$out_of_stock_percentage = ( $out_of_stock_count / $total_checked ) * 100;
			$low_stock_percentage    = ( $low_stock_count / $total_checked ) * 100;

			if ( $out_of_stock_percentage > 20 ) {
				$risk['level']     = 'critical';
				$risk['score']     = 20;
				$risk['factors'][] = sprintf( '%d%% of products are out of stock', round( $out_of_stock_percentage ) );
			} elseif ( $out_of_stock_percentage > 5 ) {
				$risk['level']     = 'high';
				$risk['score']     = 50;
				$risk['factors'][] = sprintf( '%d%% of products are out of stock', round( $out_of_stock_percentage ) );
			}

			if ( $low_stock_percentage > 30 ) {
				$current_score = $risk['score'];
				$risk['score'] = min( $current_score, 60 );
				if ( 'low' === $risk['level'] ) {
					$risk['level'] = 'medium';
				}
				$risk['factors'][] = sprintf( '%d%% of products have low stock (≤%d units)', round( $low_stock_percentage ), self::LOW_STOCK_THRESHOLD );
			} elseif ( $low_stock_percentage > 10 ) {
				$current_score = $risk['score'];
				$risk['score'] = min( $current_score, 80 );
				$risk['factors'][] = sprintf( '%d%% of products have low stock (≤%d units)', round( $low_stock_percentage ), self::LOW_STOCK_THRESHOLD );
			}
		}

		if ( empty( $risk['factors'] ) ) {
			$risk['factors'][] = 'Product inventory levels appear healthy';
		}

		return $risk;
	}

	/**
	 * Assess market timing risk.
	 *
	 * Analyzes whether campaign timing aligns with market conditions and seasonality.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Market timing risk assessment.
	 */
	private function assess_market_timing_risk( $campaign, $context ) {
		$risk = array(
			'level'   => 'low',
			'score'   => 100,
			'factors' => array(),
		);

		$start_date = isset( $campaign['start_date'] ) ? $campaign['start_date'] : '';

		if ( empty( $start_date ) ) {
			$risk['factors'][] = 'No start date set - unable to assess timing';
			return $risk;
		}

		$start_timestamp = strtotime( $start_date );
		$start_month     = (int) gmdate( 'n', $start_timestamp );
		$start_day       = (int) gmdate( 'j', $start_timestamp );

		// Check for major shopping seasons
		$is_peak_season = false;
		$season_name    = '';

		// Black Friday / Cyber Monday (last week of November)
		if ( 11 === $start_month && $start_day >= 22 ) {
			$is_peak_season = true;
			$season_name    = 'Black Friday / Cyber Monday';
		}
		// Christmas season (December 1-25)
		elseif ( 12 === $start_month && $start_day <= 25 ) {
			$is_peak_season = true;
			$season_name    = 'Christmas shopping season';
		}
		// Back to School (August 1-31)
		elseif ( 8 === $start_month ) {
			$is_peak_season = true;
			$season_name    = 'Back to School season';
		}
		// Valentine's Day (Feb 1-14)
		elseif ( 2 === $start_month && $start_day <= 14 ) {
			$is_peak_season = true;
			$season_name    = 'Valentine\'s Day season';
		}
		// Mother's Day / Father's Day (May/June)
		elseif ( 5 === $start_month || 6 === $start_month ) {
			$is_peak_season = true;
			$season_name    = 'Mother\'s/Father\'s Day season';
		}

		if ( $is_peak_season ) {
			$risk['factors'][] = sprintf( 'Campaign scheduled during %s - expect high competition', $season_name );
			// Note: Not necessarily a risk, but something to be aware of
		}

		// Check for slow retail months (typically January after holidays)
		if ( 1 === $start_month ) {
			$risk['factors'][] = 'January is typically a slow retail month - campaign may perform below average';
			$risk['score']     = 85; // Minor impact
		}

		// Check day of week (if we can determine it)
		$day_of_week = (int) gmdate( 'N', $start_timestamp ); // 1 (Monday) to 7 (Sunday)

		// Starting on weekend might be strategic for B2C
		if ( 6 === $day_of_week || 7 === $day_of_week ) {
			$risk['factors'][] = 'Campaign starts on weekend - good timing for consumer products';
		}

		if ( empty( $risk['factors'] ) ) {
			$risk['factors'][] = 'Campaign timing appears neutral - no significant seasonal factors detected';
		}

		return $risk;
	}

	/**
	 * Assess competitive risk.
	 *
	 * Analyzes risk from overlapping campaigns competing for same products/customers.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Competitive risk assessment.
	 */
	private function assess_competitive_risk( $campaign, $context ) {
		$risk = array(
			'level'   => 'low',
			'score'   => 100,
			'factors' => array(),
		);

		// Check for overlapping campaigns (use existing conflict detection)
		if ( ! empty( $context['conflicts_data'] ) && ! empty( $context['conflicts_data']['conflicts'] ) ) {
			$conflict_count = count( $context['conflicts_data']['conflicts'] );

			if ( $conflict_count > 5 ) {
				$risk['level']     = 'high';
				$risk['score']     = 50;
				$risk['factors'][] = sprintf( '%d overlapping campaigns detected - high internal competition', $conflict_count );
			} elseif ( $conflict_count > 2 ) {
				$risk['level']     = 'medium';
				$risk['score']     = 70;
				$risk['factors'][] = sprintf( '%d overlapping campaigns detected - moderate internal competition', $conflict_count );
			} elseif ( $conflict_count > 0 ) {
				$risk['score']     = 85;
				$risk['factors'][] = sprintf( '%d overlapping campaign(s) detected', $conflict_count );
			}
		}

		// Check campaign saturation (how many campaigns running in same timeframe)
		// This could be enhanced with campaign repository queries

		if ( empty( $risk['factors'] ) ) {
			$risk['factors'][] = 'No significant campaign conflicts detected';
		}

		return $risk;
	}

	/**
	 * Assess execution risk.
	 *
	 * Analyzes risk based on campaign complexity and operational challenges.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Execution risk assessment.
	 */
	private function assess_execution_risk( $campaign, $context ) {
		$risk = array(
			'level'   => 'low',
			'score'   => 100,
			'factors' => array(),
		);

		$complexity_score = 0;

		// Complex discount types
		$discount_type = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : '';
		if ( in_array( $discount_type, array( 'bogo', 'tiered', 'spend_threshold' ), true ) ) {
			$complexity_score += 2;
			$risk['factors'][] = sprintf( '%s discount type requires careful monitoring', ucfirst( str_replace( '_', ' ', $discount_type ) ) );
		}

		// Product selection complexity
		$product_selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';
		if ( 'random_products' === $product_selection_type ) {
			$complexity_score += 2;
			$risk['factors'][] = 'Random product selection requires rotation management';
		}

		// Filter conditions complexity
		$metadata = isset( $campaign['metadata'] ) ? $campaign['metadata'] : array();
		if ( is_string( $metadata ) ) {
			$metadata = json_decode( $metadata, true );
		}

		if ( ! empty( $metadata['filter_conditions'] ) ) {
			$filter_count = is_array( $metadata['filter_conditions'] ) ? count( $metadata['filter_conditions'] ) : 0;
			if ( $filter_count > 5 ) {
				$complexity_score += 3;
				$risk['factors'][] = sprintf( '%d filter conditions increase campaign complexity', $filter_count );
			} elseif ( $filter_count > 2 ) {
				$complexity_score += 1;
			}
		}

		// Recurring campaigns add complexity
		if ( ! empty( $metadata['schedule'] ) ) {
			$schedule = $metadata['schedule'];
			if ( isset( $schedule['recurrence_enabled'] ) && $schedule['recurrence_enabled'] ) {
				$complexity_score += 2;
				$risk['factors'][] = 'Recurring schedule requires ongoing monitoring';
			}
		}

		// Usage limits complexity
		if ( ! empty( $metadata['usage_limits'] ) ) {
			$complexity_score += 1;
		}

		// Determine risk level based on complexity
		if ( $complexity_score >= 8 ) {
			$risk['level'] = 'high';
			$risk['score'] = 55;
		} elseif ( $complexity_score >= 5 ) {
			$risk['level'] = 'medium';
			$risk['score'] = 75;
		} elseif ( $complexity_score >= 3 ) {
			$risk['score'] = 85;
		}

		if ( empty( $risk['factors'] ) ) {
			$risk['factors'][] = 'Campaign configuration is straightforward to execute';
		}

		return $risk;
	}

	/**
	 * Get smart benchmark data comparing campaign to historical performance.
	 *
	 * Uses statistical analysis to compare against similar past campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaign           Campaign data.
	 * @param    array $context            Additional context (analytics_repo, campaign_repo).
	 * @param    array $performance_data   Actual performance data (for completed campaigns).
	 * @return   array                        Benchmark comparison data.
	 */
	public function get_smart_benchmark( $campaign, $context = array(), $performance_data = array() ) {
		$benchmark = array(
			'has_historical_data' => false,
			'similar_campaigns'   => array(),
			'percentile_ranking'  => array(),
			'performance_vs_avg'  => array(),
			'insights'            => array(),
		);

		// Need campaign repository to find similar campaigns
		if ( empty( $context['campaign_repo'] ) ) {
			$benchmark['insights'][] = 'Historical comparison unavailable - no campaign repository provided';
			return $benchmark;
		}

		// Find similar campaigns for comparison
		$similar_campaigns = $this->find_similar_campaigns( $campaign, $context );

		if ( empty( $similar_campaigns ) ) {
			$benchmark['insights'][] = 'No similar historical campaigns found for comparison';
			return $benchmark;
		}

		$benchmark['has_historical_data'] = true;
		$benchmark['similar_campaigns']   = array(
			'count'             => count( $similar_campaigns ),
			'date_range'        => $this->get_campaigns_date_range( $similar_campaigns ),
			'discount_type'     => isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : 'unknown',
			'selection_type'    => isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : 'unknown',
		);

		// Calculate aggregate statistics from similar campaigns
		$stats = $this->calculate_aggregate_statistics( $similar_campaigns, $context );

		// If we have performance data for current campaign, calculate percentile ranking
		if ( ! empty( $performance_data ) ) {
			$benchmark['percentile_ranking'] = $this->calculate_performance_percentile(
				$performance_data,
				$stats
			);

			$benchmark['performance_vs_avg'] = array(
				'revenue' => array(
					'current'    => isset( $performance_data['revenue'] ) ? $performance_data['revenue'] : 0,
					'average'    => $stats['avg_revenue'],
					'percentile' => isset( $benchmark['percentile_ranking']['revenue'] ) ? $benchmark['percentile_ranking']['revenue'] : 50,
				),
				'conversion_rate' => array(
					'current'    => isset( $performance_data['conversion_rate'] ) ? $performance_data['conversion_rate'] : 0,
					'average'    => $stats['avg_conversion_rate'],
					'percentile' => isset( $benchmark['percentile_ranking']['conversion_rate'] ) ? $benchmark['percentile_ranking']['conversion_rate'] : 50,
				),
			);

			// Generate insights based on percentile performance
			$revenue_percentile = isset( $benchmark['percentile_ranking']['revenue'] ) ? $benchmark['percentile_ranking']['revenue'] : 50;
			if ( $revenue_percentile >= 75 ) {
				$benchmark['insights'][] = sprintf(
					'Outstanding performance - revenue in top %d%% of similar campaigns',
					100 - $revenue_percentile
				);
			} elseif ( $revenue_percentile >= 50 ) {
				$benchmark['insights'][] = 'Above average performance compared to similar campaigns';
			} elseif ( $revenue_percentile >= 25 ) {
				$benchmark['insights'][] = 'Below average performance - review optimization opportunities';
			} else {
				$benchmark['insights'][] = sprintf(
					'Underperforming - revenue in bottom %d%% of similar campaigns',
					$revenue_percentile
				);
			}
		} else {
			// No performance data yet - provide expected performance based on historical data
			$benchmark['expected_performance'] = array(
				'revenue' => array(
					'conservative' => $stats['percentile_25_revenue'],
					'likely'       => $stats['avg_revenue'],
					'optimistic'   => $stats['percentile_75_revenue'],
				),
				'conversion_rate' => array(
					'conservative' => $stats['percentile_25_conversion'],
					'likely'       => $stats['avg_conversion_rate'],
					'optimistic'   => $stats['percentile_75_conversion'],
				),
			);

			$benchmark['insights'][] = sprintf(
				'Based on %d similar campaigns, expected revenue range: %s - %s',
				count( $similar_campaigns ),
				wc_price( $stats['percentile_25_revenue'] ),
				wc_price( $stats['percentile_75_revenue'] )
			);
		}

		// Success rate of similar campaigns
		if ( isset( $stats['success_rate'] ) ) {
			$benchmark['insights'][] = sprintf(
				'Similar campaigns have %.1f%% success rate (health score ≥80)',
				$stats['success_rate']
			);
		}

		return $benchmark;
	}

	/**
	 * Find similar campaigns for benchmarking.
	 *
	 * Uses similarity scoring based on discount type, selection type, and product characteristics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Array of similar campaigns.
	 */
	private function find_similar_campaigns( $campaign, $context ) {
		if ( empty( $context['campaign_repo'] ) ) {
			return array();
		}

		$campaign_repo = $context['campaign_repo'];

		// Get all completed campaigns
		$all_campaigns = $campaign_repo->find_by(
			array(
				'status' => 'completed',
			),
			array(
				'limit' => 100,
				'order' => 'DESC',
			)
		);

		if ( empty( $all_campaigns ) ) {
			return array();
		}

		$similar_campaigns  = array();
		$discount_type      = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : '';
		$selection_type     = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';

		foreach ( $all_campaigns as $historical_campaign ) {
			$similarity_score = 0;

			// Same discount type (most important) = 50 points
			if ( $historical_campaign->get_discount_type() === $discount_type ) {
				$similarity_score += 50;
			}

			// Same product selection type = 30 points
			if ( $historical_campaign->get_product_selection_type() === $selection_type ) {
				$similarity_score += 30;
			}

			// Similar discount value range = 20 points
			$campaign_discount   = isset( $campaign['discount_value'] ) ? floatval( $campaign['discount_value'] ) : 0;
			$historical_discount = $historical_campaign->get_discount_value();

			if ( 'percentage' === $discount_type ) {
				// Within 10 percentage points
				if ( abs( $campaign_discount - $historical_discount ) <= 10 ) {
					$similarity_score += 20;
				} elseif ( abs( $campaign_discount - $historical_discount ) <= 20 ) {
					$similarity_score += 10;
				}
			} else {
				// Within 50% of value for fixed/other types
				$lower_bound = $campaign_discount * 0.5;
				$upper_bound = $campaign_discount * 1.5;
				if ( $historical_discount >= $lower_bound && $historical_discount <= $upper_bound ) {
					$similarity_score += 20;
				}
			}

			// Include campaigns with similarity score >= 50 (at least matching discount type)
			if ( $similarity_score >= 50 ) {
				$similar_campaigns[] = $historical_campaign;
			}
		}

		return $similar_campaigns;
	}

	/**
	 * Calculate aggregate statistics from similar campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Array of WSSCD_Campaign objects.
	 * @param    array $context      Additional context (analytics_repo).
	 * @return   array                  Aggregate statistics.
	 */
	private function calculate_aggregate_statistics( $campaigns, $context ) {
		$stats = array(
			'count'                    => count( $campaigns ),
			'avg_revenue'              => 0,
			'avg_conversion_rate'      => 0,
			'percentile_25_revenue'    => 0,
			'percentile_75_revenue'    => 0,
			'percentile_25_conversion' => 0,
			'percentile_75_conversion' => 0,
			'success_rate'             => 0,
		);

		if ( empty( $campaigns ) ) {
			return $stats;
		}

		$revenues         = array();
		$conversion_rates = array();
		$successful_count = 0;

		// Need analytics repository to get performance data
		$analytics_repo = isset( $context['analytics_repo'] ) ? $context['analytics_repo'] : null;

		foreach ( $campaigns as $campaign ) {
			if ( $analytics_repo ) {
				$performance = $analytics_repo->get_campaign_performance( $campaign->get_id() );

				if ( ! empty( $performance ) ) {
					$revenue = isset( $performance['total_revenue'] ) ? floatval( $performance['total_revenue'] ) : 0;
					$conversion = isset( $performance['conversion_rate'] ) ? floatval( $performance['conversion_rate'] ) : 0;

					$revenues[]         = $revenue;
					$conversion_rates[] = $conversion;

					// Count successful campaigns (revenue > 0 and conversion > average)
					if ( $revenue > 0 && $conversion > 2.0 ) {
						$successful_count++;
					}
				}
			}
		}

		// Calculate averages
		if ( ! empty( $revenues ) ) {
			$stats['avg_revenue'] = array_sum( $revenues ) / count( $revenues );

			// Sort for percentile calculations
			sort( $revenues );
			$count_25_index = (int) floor( count( $revenues ) * 0.25 );
			$count_75_index = (int) floor( count( $revenues ) * 0.75 );

			$stats['percentile_25_revenue'] = $revenues[ $count_25_index ];
			$stats['percentile_75_revenue'] = $revenues[ $count_75_index ];
		}

		if ( ! empty( $conversion_rates ) ) {
			$stats['avg_conversion_rate'] = array_sum( $conversion_rates ) / count( $conversion_rates );

			sort( $conversion_rates );
			$count_25_index = (int) floor( count( $conversion_rates ) * 0.25 );
			$count_75_index = (int) floor( count( $conversion_rates ) * 0.75 );

			$stats['percentile_25_conversion'] = $conversion_rates[ $count_25_index ];
			$stats['percentile_75_conversion'] = $conversion_rates[ $count_75_index ];
		}

		// Calculate success rate
		if ( count( $campaigns ) > 0 ) {
			$stats['success_rate'] = ( $successful_count / count( $campaigns ) ) * 100;
		}

		return $stats;
	}

	/**
	 * Calculate performance percentile ranking.
	 *
	 * Determines where current performance ranks among historical campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $performance_data    Current campaign performance.
	 * @param    array $stats               Aggregate statistics from similar campaigns.
	 * @return   array                         Percentile rankings.
	 */
	private function calculate_performance_percentile( $performance_data, $stats ) {
		$percentiles = array();

		// Revenue percentile
		if ( isset( $performance_data['revenue'] ) && isset( $stats['avg_revenue'] ) ) {
			$current_revenue = floatval( $performance_data['revenue'] );
			$avg_revenue     = $stats['avg_revenue'];
			$p25_revenue     = $stats['percentile_25_revenue'];
			$p75_revenue     = $stats['percentile_75_revenue'];

			if ( $current_revenue >= $p75_revenue ) {
				// Top quartile
				$percentiles['revenue'] = 75 + ( ( $current_revenue - $p75_revenue ) / max( $p75_revenue, 1 ) ) * 25;
				$percentiles['revenue'] = min( 99, $percentiles['revenue'] );
			} elseif ( $current_revenue >= $avg_revenue ) {
				// Above average
				$percentiles['revenue'] = 50 + ( ( $current_revenue - $avg_revenue ) / max( $p75_revenue - $avg_revenue, 1 ) ) * 25;
			} elseif ( $current_revenue >= $p25_revenue ) {
				// Below average
				$percentiles['revenue'] = 25 + ( ( $current_revenue - $p25_revenue ) / max( $avg_revenue - $p25_revenue, 1 ) ) * 25;
			} else {
				// Bottom quartile
				$percentiles['revenue'] = ( $current_revenue / max( $p25_revenue, 1 ) ) * 25;
			}
		}

		// Conversion rate percentile
		if ( isset( $performance_data['conversion_rate'] ) && isset( $stats['avg_conversion_rate'] ) ) {
			$current_conversion = floatval( $performance_data['conversion_rate'] );
			$avg_conversion     = $stats['avg_conversion_rate'];
			$p25_conversion     = $stats['percentile_25_conversion'];
			$p75_conversion     = $stats['percentile_75_conversion'];

			if ( $current_conversion >= $p75_conversion ) {
				$percentiles['conversion_rate'] = 75 + ( ( $current_conversion - $p75_conversion ) / max( $p75_conversion, 0.1 ) ) * 25;
				$percentiles['conversion_rate'] = min( 99, $percentiles['conversion_rate'] );
			} elseif ( $current_conversion >= $avg_conversion ) {
				$percentiles['conversion_rate'] = 50 + ( ( $current_conversion - $avg_conversion ) / max( $p75_conversion - $avg_conversion, 0.1 ) ) * 25;
			} elseif ( $current_conversion >= $p25_conversion ) {
				$percentiles['conversion_rate'] = 25 + ( ( $current_conversion - $p25_conversion ) / max( $avg_conversion - $p25_conversion, 0.1 ) ) * 25;
			} else {
				$percentiles['conversion_rate'] = ( $current_conversion / max( $p25_conversion, 0.1 ) ) * 25;
			}
		}

		return $percentiles;
	}

	/**
	 * Get date range for array of campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Array of WSSCD_Campaign objects.
	 * @return   array                  Date range (earliest, latest).
	 */
	private function get_campaigns_date_range( $campaigns ) {
		if ( empty( $campaigns ) ) {
			return array(
				'earliest' => null,
				'latest'   => null,
			);
		}

		$earliest = null;
		$latest   = null;

		foreach ( $campaigns as $campaign ) {
			$start_date = $campaign->get_starts_at();
			if ( $start_date ) {
				$timestamp = $start_date->getTimestamp();

				if ( null === $earliest || $timestamp < $earliest ) {
					$earliest = $timestamp;
				}
				if ( null === $latest || $timestamp > $latest ) {
					$latest = $timestamp;
				}
			}
		}

		return array(
			'earliest' => $earliest ? gmdate( 'Y-m-d', $earliest ) : null,
			'latest'   => $latest ? gmdate( 'Y-m-d', $latest ) : null,
		);
	}

	/**
	 * Generate statistical performance forecast for campaign.
	 *
	 * Predicts expected performance based on historical patterns and statistical analysis.
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context (analytics_repo, campaign_repo).
	 * @return   array                 Performance forecast.
	 */
	public function generate_performance_forecast( $campaign, $context = array() ) {
		$forecast = array(
			'confidence_level'  => 'low',
			'data_points'       => 0,
			'revenue_forecast'  => array(),
			'conversion_forecast' => array(),
			'factors'           => array(),
			'methodology'       => 'statistical_analysis',
		);

		// Get historical data from similar campaigns
		$benchmark_data = $this->get_smart_benchmark( $campaign, $context );

		if ( ! $benchmark_data['has_historical_data'] ) {
			$forecast['factors'][] = 'Insufficient historical data for statistical forecast';
			return $forecast;
		}

		$similar_count = $benchmark_data['similar_campaigns']['count'];
		$forecast['data_points'] = $similar_count;

		// Confidence level based on sample size
		if ( $similar_count >= 20 ) {
			$forecast['confidence_level'] = 'high';
		} elseif ( $similar_count >= 10 ) {
			$forecast['confidence_level'] = 'medium';
		}

		// Use expected performance from benchmark
		if ( ! empty( $benchmark_data['expected_performance'] ) ) {
			$expected = $benchmark_data['expected_performance'];

			$forecast['revenue_forecast'] = array(
				'pessimistic' => $expected['revenue']['conservative'],
				'realistic'   => $expected['revenue']['likely'],
				'optimistic'  => $expected['revenue']['optimistic'],
				'range'       => $expected['revenue']['optimistic'] - $expected['revenue']['conservative'],
			);

			$forecast['conversion_forecast'] = array(
				'pessimistic' => $expected['conversion_rate']['conservative'],
				'realistic'   => $expected['conversion_rate']['likely'],
				'optimistic'  => $expected['conversion_rate']['optimistic'],
				'range'       => $expected['conversion_rate']['optimistic'] - $expected['conversion_rate']['conservative'],
			);
		}

		// Apply adjustment factors based on campaign characteristics
		$forecast = $this->apply_forecast_adjustments( $forecast, $campaign, $context );

		// Add methodology explanation
		$forecast['factors'][] = sprintf(
			'Forecast based on %d similar historical campaigns',
			$similar_count
		);

		$forecast['factors'][] = 'Percentile method: 25th (pessimistic), 50th (realistic), 75th (optimistic)';

		return $forecast;
	}

	/**
	 * Apply adjustments to forecast based on campaign-specific factors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $forecast    Base forecast.
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context.
	 * @return   array                 Adjusted forecast.
	 */
	private function apply_forecast_adjustments( $forecast, $campaign, $context ) {
		$adjustment_factor = 1.0;

		// Seasonal adjustment
		$start_date = isset( $campaign['start_date'] ) ? $campaign['start_date'] : '';
		if ( ! empty( $start_date ) ) {
			$start_month = (int) gmdate( 'n', strtotime( $start_date ) );

			// Peak shopping months get positive adjustment
			if ( in_array( $start_month, array( 11, 12 ), true ) ) {
				$adjustment_factor *= 1.15; // 15% boost for Nov/Dec
				$forecast['factors'][] = 'Seasonal boost applied: Holiday shopping season (+15%)';
			} elseif ( in_array( $start_month, array( 8 ), true ) ) {
				$adjustment_factor *= 1.08; // 8% boost for Back to School
				$forecast['factors'][] = 'Seasonal boost applied: Back to school season (+8%)';
			} elseif ( in_array( $start_month, array( 1 ), true ) ) {
				$adjustment_factor *= 0.92; // 8% reduction for January
				$forecast['factors'][] = 'Seasonal adjustment: Post-holiday slowdown (-8%)';
			}
		}

		// Competition adjustment
		if ( ! empty( $context['conflicts_data'] ) && ! empty( $context['conflicts_data']['conflicts'] ) ) {
			$conflict_count = count( $context['conflicts_data']['conflicts'] );
			if ( $conflict_count > 5 ) {
				$adjustment_factor *= 0.85; // 15% reduction for high competition
				$forecast['factors'][] = 'Competition adjustment: High internal competition (-15%)';
			} elseif ( $conflict_count > 2 ) {
				$adjustment_factor *= 0.92; // 8% reduction for moderate competition
				$forecast['factors'][] = 'Competition adjustment: Moderate competition (-8%)';
			}
		}

		// Apply adjustments to revenue forecast
		if ( ! empty( $forecast['revenue_forecast'] ) ) {
			$forecast['revenue_forecast']['pessimistic'] *= $adjustment_factor;
			$forecast['revenue_forecast']['realistic']   *= $adjustment_factor;
			$forecast['revenue_forecast']['optimistic']  *= $adjustment_factor;
			$forecast['revenue_forecast']['range']       = $forecast['revenue_forecast']['optimistic'] - $forecast['revenue_forecast']['pessimistic'];
		}

		return $forecast;
	}

	/**
	 * Analyze historical patterns for campaign configuration.
	 *
	 * Identifies patterns in successful vs unsuccessful campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data.
	 * @param    array $context     Additional context (campaign_repo, analytics_repo).
	 * @return   array                 Pattern analysis results.
	 */
	public function analyze_historical_patterns( $campaign, $context = array() ) {
		$analysis = array(
			'patterns_found'   => false,
			'success_patterns' => array(),
			'failure_patterns' => array(),
			'recommendations'  => array(),
			'sample_size'      => 0,
		);

		if ( empty( $context['campaign_repo'] ) || empty( $context['analytics_repo'] ) ) {
			return $analysis;
		}

		$campaign_repo  = $context['campaign_repo'];
		$analytics_repo = $context['analytics_repo'];

		// Get completed campaigns
		$completed_campaigns = $campaign_repo->find_by(
			array(
				'status' => 'completed',
			),
			array(
				'limit' => 100,
			)
		);

		if ( empty( $completed_campaigns ) ) {
			return $analysis;
		}

		$analysis['sample_size'] = count( $completed_campaigns );
		$analysis['patterns_found'] = true;

		// Categorize campaigns by performance
		$high_performers = array();
		$low_performers  = array();

		foreach ( $completed_campaigns as $completed_campaign ) {
			$performance = $analytics_repo->get_campaign_performance( $completed_campaign->get_id() );

			if ( empty( $performance ) ) {
				continue;
			}

			$revenue    = isset( $performance['total_revenue'] ) ? floatval( $performance['total_revenue'] ) : 0;
			$conversion = isset( $performance['conversion_rate'] ) ? floatval( $performance['conversion_rate'] ) : 0;

			// Classify as high or low performer
			if ( $revenue > 1000 && $conversion > 3.0 ) {
				$high_performers[] = array(
					'campaign'    => $completed_campaign,
					'performance' => $performance,
				);
			} elseif ( $revenue < 100 || $conversion < 1.0 ) {
				$low_performers[] = array(
					'campaign'    => $completed_campaign,
					'performance' => $performance,
				);
			}
		}

		// Analyze patterns in high performers
		if ( ! empty( $high_performers ) ) {
			$analysis['success_patterns'] = $this->extract_common_patterns( $high_performers, 'success' );
		}

		// Analyze patterns in low performers
		if ( ! empty( $low_performers ) ) {
			$analysis['failure_patterns'] = $this->extract_common_patterns( $low_performers, 'failure' );
		}

		// Generate recommendations based on patterns
		$analysis['recommendations'] = $this->generate_pattern_based_recommendations(
			$campaign,
			$analysis['success_patterns'],
			$analysis['failure_patterns']
		);

		return $analysis;
	}

	/**
	 * Extract common patterns from campaign group.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $campaign_group    Array of campaigns with performance data.
	 * @param    string $group_type        'success' or 'failure'.
	 * @return   array                        Common patterns found.
	 */
	private function extract_common_patterns( $campaign_group, $group_type ) {
		$patterns = array(
			'discount_types'    => array(),
			'selection_types'   => array(),
			'avg_discount'      => 0,
			'common_day_of_week' => null,
			'avg_duration_days' => 0,
		);

		if ( empty( $campaign_group ) ) {
			return $patterns;
		}

		$discount_type_counts  = array();
		$selection_type_counts = array();
		$discount_values       = array();
		$day_of_week_counts    = array();
		$durations             = array();

		foreach ( $campaign_group as $item ) {
			$campaign = $item['campaign'];

			// Track discount types
			$discount_type = $campaign->get_discount_type();
			if ( ! isset( $discount_type_counts[ $discount_type ] ) ) {
				$discount_type_counts[ $discount_type ] = 0;
			}
			$discount_type_counts[ $discount_type ]++;

			// Track selection types
			$selection_type = $campaign->get_product_selection_type();
			if ( ! isset( $selection_type_counts[ $selection_type ] ) ) {
				$selection_type_counts[ $selection_type ] = 0;
			}
			$selection_type_counts[ $selection_type ]++;

			// Track discount values
			$discount_values[] = $campaign->get_discount_value();

			// Track start day of week
			$start_date = $campaign->get_starts_at();
			if ( $start_date ) {
				$day_of_week = (int) $start_date->format( 'N' );
				if ( ! isset( $day_of_week_counts[ $day_of_week ] ) ) {
					$day_of_week_counts[ $day_of_week ] = 0;
				}
				$day_of_week_counts[ $day_of_week ]++;
			}

			// Track duration
			$start = $campaign->get_starts_at();
			$end   = $campaign->get_ends_at();
			if ( $start && $end ) {
				$duration = $end->diff( $start )->days;
				$durations[] = $duration;
			}
		}

		// Identify most common patterns
		arsort( $discount_type_counts );
		$patterns['discount_types'] = array_slice( $discount_type_counts, 0, 3, true );

		arsort( $selection_type_counts );
		$patterns['selection_types'] = array_slice( $selection_type_counts, 0, 3, true );

		if ( ! empty( $discount_values ) ) {
			$patterns['avg_discount'] = array_sum( $discount_values ) / count( $discount_values );
		}

		if ( ! empty( $day_of_week_counts ) ) {
			arsort( $day_of_week_counts );
			$patterns['common_day_of_week'] = key( $day_of_week_counts );
		}

		if ( ! empty( $durations ) ) {
			$patterns['avg_duration_days'] = array_sum( $durations ) / count( $durations );
		}

		return $patterns;
	}

	/**
	 * Generate recommendations based on pattern analysis.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign           Current campaign data.
	 * @param    array $success_patterns   Patterns from successful campaigns.
	 * @param    array $failure_patterns   Patterns from failed campaigns.
	 * @return   array                        Pattern-based recommendations.
	 */
	private function generate_pattern_based_recommendations( $campaign, $success_patterns, $failure_patterns ) {
		$recommendations = array();

		if ( empty( $success_patterns ) ) {
			return $recommendations;
		}

		// Discount type recommendation
		if ( ! empty( $success_patterns['discount_types'] ) ) {
			$current_type = isset( $campaign['discount_type'] ) ? $campaign['discount_type'] : '';
			$best_type    = key( $success_patterns['discount_types'] );

			if ( $current_type !== $best_type ) {
				$recommendations[] = array(
					'type'    => 'discount_type',
					'message' => sprintf(
						'Consider using %s discount - it has been most successful in similar campaigns',
						$best_type
					),
					'priority' => 'medium',
				);
			}
		}

		// Discount value recommendation
		if ( isset( $success_patterns['avg_discount'] ) ) {
			$current_value = isset( $campaign['discount_value'] ) ? floatval( $campaign['discount_value'] ) : 0;
			$optimal_value = $success_patterns['avg_discount'];

			$difference_pct = abs( ( $current_value - $optimal_value ) / max( $optimal_value, 1 ) ) * 100;

			if ( $difference_pct > 30 ) {
				$recommendations[] = array(
					'type'    => 'discount_value',
					'message' => sprintf(
						'Successful campaigns typically use %.1f%% discount (you have %.1f%%)',
						$optimal_value,
						$current_value
					),
					'priority' => 'medium',
				);
			}
		}

		// Start day recommendation
		if ( isset( $success_patterns['common_day_of_week'] ) ) {
			$optimal_day = $success_patterns['common_day_of_week'];
			$day_names = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );

			$recommendations[] = array(
				'type'    => 'start_timing',
				'message' => sprintf(
					'Successful campaigns often start on %s - consider this for optimal performance',
					$day_names[ $optimal_day ]
				),
				'priority' => 'low',
			);
		}

		// Duration recommendation
		if ( isset( $success_patterns['avg_duration_days'] ) ) {
			$optimal_duration = round( $success_patterns['avg_duration_days'] );

			$recommendations[] = array(
				'type'    => 'duration',
				'message' => sprintf(
					'Successful campaigns typically run for %d days',
					$optimal_duration
				),
				'priority' => 'low',
			);
		}

		return $recommendations;
	}
}
