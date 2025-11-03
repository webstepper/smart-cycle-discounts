<?php
/**
 * Campaign Health Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/services/class-campaign-health-service.php
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
 * Campaign Health Service Class
 *
 * Analyzes campaign health and provides consistent scoring, status, and recommendations
 * across all plugin locations (dashboard, list table, wizard review step).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/services
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Health_Service {

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
	 * Penalty Values
	 */
	const PENALTY_CRITICAL_ISSUE     = 15;
	const PENALTY_HIGH_ISSUE         = 10;
	const PENALTY_MEDIUM_ISSUE       = 5;
	const PENALTY_DUPLICATE_NAME     = 10;
	const PENALTY_EXCESSIVE_DISCOUNT = 10;

	/**
	 * Status Thresholds
	 */
	const STATUS_EXCELLENT_MIN = 80;
	const STATUS_GOOD_MIN      = 60;
	const STATUS_FAIR_MIN      = 40;

	/**
	 * Stock Risk Thresholds
	 */
	const STOCK_RISK_HIGH_MULTIPLIER   = 0.5; // Stock < 50% of estimated demand
	const STOCK_RISK_MEDIUM_MULTIPLIER = 0.8; // Stock < 80% of estimated demand
	const DISCOUNT_BOOST_PERCENTAGE    = 1.5; // 50% boost in sales from discounts
	const DISCOUNT_BOOST_FIXED         = 1.3; // 30% boost for fixed discounts
	const HISTORICAL_DAYS_LOOKBACK     = 30;  // Days to analyze for sales history

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private $logger;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
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
	 * @param    mixed  $campaign      Campaign data (array from DB or SCD_Campaign object).
	 * @param    string $mode          Analysis mode: 'quick', 'standard', 'comprehensive'.
	 * @param    array  $context       Additional context (coverage_data, conflicts_data, view_context).
	 * @return   array                    Health analysis results.
	 */
	public function analyze_health( $campaign, $mode = 'standard', $context = array() ) {
		// Normalize campaign data
		$campaign_data = $this->normalize_campaign_data( $campaign );

		// Determine view context (dashboard vs review)
		$view_context = isset( $context['view_context'] ) ? $context['view_context'] : 'dashboard';

		// Initialize health analysis
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
		$health = $this->check_discount_reasonableness( $campaign_data, $health, $view_context );

		// Mode-specific checks
		if ( 'standard' === $mode || 'comprehensive' === $mode ) {
			$health = $this->check_products( $campaign_data, $health, $context, $view_context );
			if ( ! empty( $health['warnings'] ) ) {
			}
			$health = $this->check_coverage( $campaign_data, $health, $context, $view_context );
			$health = $this->check_stock_risk( $campaign_data, $health, $context, $view_context );
			$health = $this->check_conflicts( $campaign_data, $health, $context, $view_context );
			if ( ! empty( $health['warnings'] ) ) {
			}
		}

		// Generate recommendations for comprehensive mode OR review context
		if ( 'comprehensive' === $mode || 'review' === $view_context ) {
			$health['recommendations'] = $this->generate_recommendations( $campaign_data, $health, $context );
		}

		// Calculate final status
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
	 * @param    mixed $campaign    Campaign data (array or SCD_Campaign object).
	 * @return   array                 Normalized campaign data array.
	 */
	private function normalize_campaign_data( $campaign ) {
		if ( is_object( $campaign ) && method_exists( $campaign, 'get_id' ) ) {
			// SCD_Campaign object
			return array(
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
					$penalty             += 3;
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
					$penalty             += 3;
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

		// DEBUG: Log product IDs for dashboard
		if ( 'dashboard' === $view_context ) {
			$campaign_id   = isset( $campaign['id'] ) ? $campaign['id'] : 'unknown';
			$campaign_name = isset( $campaign['name'] ) ? $campaign['name'] : 'unknown';
			if ( ! empty( $product_ids ) ) {
			}
		}

		// Check if random count exceeds total products (can happen if products deleted from catalog)
		if ( 'random' === $selection_type ) {
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
					$penalty                    += 15;
					$status                      = 'critical';
				}
			}
		}

		// For "all_products" campaigns with category filters, get actual matching products for stock check
		if ( 'all_products' === $selection_type && empty( $product_ids ) ) {
			// Check if campaign has category filter
			$category_ids = isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : array();
			if ( is_string( $category_ids ) ) {
				$decoded      = json_decode( $category_ids, true );
				$category_ids = ( null !== $decoded && is_array( $decoded ) ) ? $decoded : array();
			}

			if ( ! empty( $category_ids ) ) {
				// Get products in these categories (limit to 100 for performance)
				$args        = array(
					'status'   => 'publish',
					'limit'    => 100,
					'category' => $category_ids,
					'return'   => 'ids',
				);
				$product_ids = wc_get_products( $args );

				if ( 'dashboard' === $view_context ) {
				}
			}
		}

		// Check specific products OR resolved all_products (from category filter)
		if ( ! empty( $product_ids ) && ( 'specific_products' === $selection_type || 'all_products' === $selection_type ) ) {
			$product_count = count( $product_ids );
			$unique_ids    = array_unique( $product_ids );

			// Check for products deleted from catalog (post-creation issue)
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
				$health['critical_issues'][] = array(
					'code'     => 'products_deleted_from_catalog',
					'message'  => sprintf(
						/* translators: %d: number of deleted products */
						_n( '%d product no longer exists in catalog', '%d products no longer exist in catalog', $invalid_count, 'smart-cycle-discounts' ),
						$invalid_count
					),
					'category' => 'products',
				);
				$penalty                    += 15;
				$status                      = 'critical';
			}

			// Use valid IDs for stock checks
			$product_ids   = $valid_product_ids;
			$product_count = count( $product_ids );

			if ( $product_count > 0 ) {
				// Analyze stock status (can change after creation)
				$check_products     = array_slice( $product_ids, 0, 100 );
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
							// DEBUG: Log each low stock product
							if ( 'dashboard' === $view_context ) {
							}
						}
					} elseif ( ! $product->is_in_stock() ) {
							++$out_of_stock_count;
					}

					// Product status (can change after creation)
					if ( 'publish' !== get_post_status( $product_id ) ) {
						++$draft_count;
					}
				}

				// DEBUG: Log stock analysis results
				if ( 'dashboard' === $view_context ) {
					$campaign_id = isset( $campaign['id'] ) ? $campaign['id'] : 'unknown';
				}

				// Critical: All products out of stock
				if ( $out_of_stock_count === count( $check_products ) && $out_of_stock_count > 0 ) {
					$health['critical_issues'][] = array(
						'code'     => 'all_products_out_of_stock',
						'message'  => sprintf(
							/* translators: %d: number of products */
							__( 'All %d products are out of stock', 'smart-cycle-discounts' ),
							$product_count
						),
						'category' => 'products',
					);
					$penalty                    += 25;
					$status                      = 'critical';
				} elseif ( $out_of_stock_count > count( $check_products ) / 2 && $out_of_stock_count > 0 ) {
					// Warning: Most products out of stock
					$out_of_stock_percent = round( ( $out_of_stock_count / count( $check_products ) ) * 100 );
					$health['warnings'][] = array(
						'code'     => 'most_products_out_of_stock',
						'message'  => sprintf(
							/* translators: 1: number out of stock, 2: total, 3: percentage */
							__( '%1$d of %2$d products (%3$d%%) out of stock', 'smart-cycle-discounts' ),
							$out_of_stock_count,
							count( $check_products ),
							$out_of_stock_percent
						),
						'category' => 'products',
					);
					$penalty             += 10;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}

				// Warning: Low stock (only on dashboard - actionable)
				if ( 'dashboard' === $view_context && $low_stock_count > 0 ) {
					$campaign_name = isset( $campaign['name'] ) ? $campaign['name'] : 'Unknown';
					$campaign_id   = isset( $campaign['id'] ) ? $campaign['id'] : 0;
					$health['warnings'][] = array(
						'code'     => 'low_stock_products',
						'message'  => sprintf(
							/* translators: %d: number of products */
							_n( '%d product low on stock (<10 units)', '%d products low on stock (<10 units)', $low_stock_count, 'smart-cycle-discounts' ),
							$low_stock_count
						),
						'category' => 'products',
					);
					$penalty             += 5;
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
					$penalty             += 8;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// POST-CREATION ISSUE: Check category/tag selection expansion or emptiness
		if ( 'categories' === $selection_type || 'tags' === $selection_type ) {
			$selected_ids = array();

			if ( 'categories' === $selection_type ) {
				$selected_ids = isset( $campaign['selected_category_ids'] ) ? $campaign['selected_category_ids'] : ( isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : array() );
			} else {
				$selected_ids = isset( $campaign['selected_tag_ids'] ) ? $campaign['selected_tag_ids'] : ( isset( $campaign['tag_ids'] ) ? $campaign['tag_ids'] : array() );
			}

			// Handle serialized data
			if ( is_string( $selected_ids ) ) {
				$selected_ids = maybe_unserialize( $selected_ids );
			}
			if ( ! is_array( $selected_ids ) ) {
				$selected_ids = array();
			}

			if ( ! empty( $selected_ids ) ) {
				// Get current product count from these categories/tags
				$args = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				);

				if ( 'categories' === $selection_type ) {
					$args['tax_query'] = array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $selected_ids,
						),
					);
				} else {
					$args['tax_query'] = array(
						array(
							'taxonomy' => 'product_tag',
							'field'    => 'term_id',
							'terms'    => $selected_ids,
						),
					);
				}

				$current_products = get_posts( $args );
				$current_count    = count( $current_products );

				// Critical: Selection resulted in zero products
				if ( 0 === $current_count ) {
					$taxonomy_label              = ( 'categories' === $selection_type ) ? __( 'categories', 'smart-cycle-discounts' ) : __( 'tags', 'smart-cycle-discounts' );
					$health['critical_issues'][] = array(
						'code'     => 'taxonomy_selection_empty',
						'message'  => sprintf(
							/* translators: %s: taxonomy label (categories/tags) */
							__( 'Selected %s contain 0 published products', 'smart-cycle-discounts' ),
							$taxonomy_label
						),
						'category' => 'products',
					);
					$penalty                    += 25;
					$status                      = 'critical';
				} elseif ( $current_count > 500 ) {
					// Warning: Selection expanded to too many products
					$taxonomy_label       = ( 'categories' === $selection_type ) ? __( 'category', 'smart-cycle-discounts' ) : __( 'tag', 'smart-cycle-discounts' );
					$health['warnings'][] = array(
						'code'     => 'taxonomy_selection_too_broad',
						'message'  => sprintf(
							/* translators: 1: taxonomy label, 2: product count */
							__( '%1$s selection now includes %2$d products (may impact performance)', 'smart-cycle-discounts' ),
							ucfirst( $taxonomy_label ),
							$current_count
						),
						'category' => 'products',
					);
					$penalty             += 8;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
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
	 * Uses current_time('timestamp') and scd_combine_date_time() which respect WordPress timezone settings.
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
		// and the scd_update_campaign_status cron job automatically expires campaigns.
		// If this state occurs, it's a SYSTEM issue (cron not running), not a campaign health issue.
		// Users cannot fix this - it requires checking WordPress cron health.
		//
		// KEPT: "Ending soon" warning - this is useful information for planning
		if ( 'active' === $campaign_status && ! empty( $end_date ) ) {
			$end_dt        = scd_combine_date_time( $end_date, $end_time_only, wp_timezone_string() );
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
				$penalty             += 5;
				if ( 'critical' !== $status ) {
					$status = 'warning';
				}
			}
		}

		// REMOVED: "Start date passed" warning for scheduled campaigns
		// Reason: This is EXPECTED behavior - scheduled campaigns automatically
		// transition to 'active' status via cron when their start date arrives.
		// Showing this as a warning is illogical and confusing to users.
		// The scd_update_campaign_status cron job handles this transition automatically.

		// POST-CREATION ISSUE: No activity warning (dashboard only)
		if ( 'dashboard' === $view_context && 'active' === $campaign_status && ! empty( $start_date ) ) {
			$start_dt        = scd_combine_date_time( $start_date, $start_time_only, wp_timezone_string() );
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
					$penalty             += 8;
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
			$penalty                    += 20;
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
			$penalty             += 10;
			$status               = 'warning';
		}
		// REMOVED: Low discount penalty (<5%) - Valid business strategy, not unhealthy
		// Small discounts (2-5%) are legitimate for customer loyalty, subtle promotions, etc.

		// POST-CREATION ISSUE: Fixed discount exceeds product price (if prices changed)
		if ( 'fixed' === $discount_type && $discount_value > 0 ) {
			$product_ids = $this->normalize_product_ids( $campaign );

			if ( ! empty( $product_ids ) ) {
				$prices = $this->get_product_prices( $product_ids, 50 );

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
						$penalty                    += 20;
						$status                      = 'critical';
					}
				}
			}
		}

		// POST-CREATION ISSUE: Check for significant price changes (dashboard only)
		if ( 'dashboard' === $view_context && isset( $campaign['original_avg_price'] ) && $campaign['original_avg_price'] > 0 ) {
			$product_ids = $this->normalize_product_ids( $campaign );

			if ( ! empty( $product_ids ) ) {
				$current_prices = $this->get_product_prices( $product_ids, 50 );

				if ( ! empty( $current_prices ) ) {
					$current_avg  = array_sum( $current_prices ) / count( $current_prices );
					$original_avg = floatval( $campaign['original_avg_price'] );

					if ( $original_avg > 0 ) {
						$price_change_percent = abs( ( ( $current_avg - $original_avg ) / $original_avg ) * 100 );

						// Warning: Significant price decrease (40%+ drop)
						if ( $current_avg < $original_avg && $price_change_percent >= 40 ) {
							$health['warnings'][] = array(
								'code'     => 'price_decreased_significantly',
								'message'  => sprintf(
									/* translators: %d: percentage decrease */
									__( 'Product prices decreased %d%% since campaign creation - verify discount still appropriate', 'smart-cycle-discounts' ),
									round( $price_change_percent )
								),
								'category' => 'discount',
							);
							$penalty             += 8;
							if ( 'critical' !== $status ) {
								$status = 'warning';
							}
						} elseif ( $current_avg > $original_avg && $price_change_percent >= 40 ) {
							// Warning: Significant price increase (40%+ increase)
							$health['warnings'][] = array(
								'code'     => 'price_increased_significantly',
								'message'  => sprintf(
									/* translators: %d: percentage increase */
									__( 'Product prices increased %d%% since campaign creation - consider adjusting discount', 'smart-cycle-discounts' ),
									round( $price_change_percent )
								),
								'category' => 'discount',
							);
							$penalty             += 5;
							if ( 'critical' !== $status ) {
								$status = 'warning';
							}
						}
					}
				}
			}
		}

		// BUSINESS WARNING: Tiered discount logic issues
		if ( 'tiered' === $discount_type ) {
			$tiers = isset( $campaign['discount_config']['tiered']['tiers'] ) ? $campaign['discount_config']['tiered']['tiers'] : array();
			if ( is_string( $tiers ) ) {
				$tiers = maybe_unserialize( $tiers );
			}

			if ( ! empty( $tiers ) && is_array( $tiers ) && count( $tiers ) >= 2 ) {
				$prev_quantity = 0;
				$prev_discount = 0;
				$is_illogical  = false;

				foreach ( $tiers as $tier ) {
					$quantity = isset( $tier['quantity'] ) ? intval( $tier['quantity'] ) : 0;
					$discount = isset( $tier['discount'] ) ? floatval( $tier['discount'] ) : 0;

					// Check if discounts increase
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
					$penalty             += 8;
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
					$penalty             += 5;
					if ( 'critical' !== $status ) {
						$status = 'warning';
					}
				}
			}
		}

		// BUSINESS WARNING: Spend threshold reasonableness
		if ( 'spend_threshold' === $discount_type ) {
			$thresholds = isset( $campaign['discount_config']['spend_threshold']['thresholds'] ) ? $campaign['discount_config']['spend_threshold']['thresholds'] : array();
			if ( is_string( $thresholds ) ) {
				$thresholds = maybe_unserialize( $thresholds );
			}

			if ( ! empty( $thresholds ) && is_array( $thresholds ) ) {
				$first_threshold = isset( $thresholds[0]['amount'] ) ? floatval( $thresholds[0]['amount'] ) : 0;

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
					$penalty             += 5;
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
					$penalty             += 3;
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
			$penalty             += 3;
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
			$penalty             += 3;
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
				$penalty                    += 25;
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
					$penalty             += 10;
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
			$penalty             += 3;
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
			$penalty             += 5;
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
			$penalty             += 5;
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

		// Check coverage percentage if available in context
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
				$penalty             += 10;
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

		// Get product IDs
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

		// Store stock risk data in context for AJAX response
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
			$penalty             += self::PENALTY_HIGH_ISSUE;
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
			$penalty         += self::PENALTY_MEDIUM_ISSUE;
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

		// Check if conflicts data provided in context
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
				$penalty             += 10;
				$status               = 'warning';
			}
		} else {
			// Smart conflict detection: check for actual product overlap with other campaigns
			if ( ! isset( $campaign['id'] ) || ! isset( $campaign['priority'] ) ) {
				// Skip if campaign doesn't have ID or priority
				return $health;
			}

			global $wpdb;
			$table_name     = $wpdb->prefix . 'scd_campaigns';
			$campaign_id    = $campaign['id'];
			$priority       = intval( $campaign['priority'] );
			$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';

			// Get all other active/scheduled campaigns
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

				// Check if campaigns target overlapping products
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
				$penalty                    += 20;
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
				$penalty             += 10;
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
		// If either campaign targets all products, they overlap
		if ( 'all_products' === $type1 || 'all_products' === $type2 ) {
			return true;
		}

		// Both specific products - check for intersection
		if ( 'specific_products' === $type1 && 'specific_products' === $type2 ) {
			// Check both field names (selected_product_ids for normalized data, product_ids for DB results)
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

		// Both categories - check for intersection
		if ( 'categories' === $type1 && 'categories' === $type2 ) {
			// Check both field names (selected_category_ids for normalized data, category_ids for DB results)
			$cats1 = isset( $campaign1['selected_category_ids'] ) ? $campaign1['selected_category_ids'] : ( isset( $campaign1['category_ids'] ) ? $campaign1['category_ids'] : array() );
			$cats2 = isset( $campaign2['selected_category_ids'] ) ? $campaign2['selected_category_ids'] : ( isset( $campaign2['category_ids'] ) ? $campaign2['category_ids'] : array() );

			// Handle serialized data
			if ( is_string( $cats1 ) ) {
				$cats1 = maybe_unserialize( $cats1 );
			}
			if ( is_string( $cats2 ) ) {
				$cats2 = maybe_unserialize( $cats2 );
			}

			if ( ! is_array( $cats1 ) ) {
				$cats1 = array();
			}
			if ( ! is_array( $cats2 ) ) {
				$cats2 = array();
			}

			$intersection = array_intersect( $cats1, $cats2 );
			return ! empty( $intersection );
		}

		// Both tags - check for intersection
		if ( 'tags' === $type1 && 'tags' === $type2 ) {
			// Check both field names (selected_tag_ids for normalized data, tag_ids for DB results)
			$tags1 = isset( $campaign1['selected_tag_ids'] ) ? $campaign1['selected_tag_ids'] : ( isset( $campaign1['tag_ids'] ) ? $campaign1['tag_ids'] : array() );
			$tags2 = isset( $campaign2['selected_tag_ids'] ) ? $campaign2['selected_tag_ids'] : ( isset( $campaign2['tag_ids'] ) ? $campaign2['tag_ids'] : array() );

			// Handle serialized data
			if ( is_string( $tags1 ) ) {
				$tags1 = maybe_unserialize( $tags1 );
			}
			if ( is_string( $tags2 ) ) {
				$tags2 = maybe_unserialize( $tags2 );
			}

			if ( ! is_array( $tags1 ) ) {
				$tags1 = array();
			}
			if ( ! is_array( $tags2 ) ) {
				$tags2 = array();
			}

			$intersection = array_intersect( $tags1, $tags2 );
			return ! empty( $intersection );
		}

		// Different selection types - would need product expansion to determine overlap
		// For now, assume no overlap (conservative approach)
		return false;
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
		// Get dates from both campaigns
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

			// Check if date ranges overlap
			return ( $start1_time <= $end2_time ) && ( $start2_time <= $end1_time );
		} catch ( Exception $e ) {
			// If date parsing fails, assume no overlap
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SCD Date overlap check error: ' . $e->getMessage() );
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
		if ( empty( $campaign['end_date'] ) ) {
			$recommendations[] = array(
				'category' => 'timing',
				'priority' => 'high',
				'message'  => __( 'Set a 7-14 day duration - limited-time offers create urgency', 'smart-cycle-discounts' ),
			);
		}

		// Badge recommendation
		$badge_enabled = isset( $campaign['badge_enabled'] ) ? $campaign['badge_enabled'] : false;
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
				$day_of_week = date( 'N', $start_timestamp );
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
			$prices = $this->get_product_prices( $product_ids, 50 );

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
				// Get products in these categories
				$args        = array(
					'status'   => 'publish',
					'limit'    => 100, // Limit for performance
					'category' => $category_ids,
					'return'   => 'ids',
				);
				$product_ids = wc_get_products( $args );
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
	 * - Critical issues force "critical" or "poor" status
	 * - Prevents minor issues from hiding critical problems
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

		// CRITICAL issues = Always poor/critical (campaign is broken)
		if ( $has_critical ) {
			return 'critical';
		}

		// WARNINGS only = Score-based but capped at "good" (can't be excellent with warnings)
		if ( $has_warnings ) {
			if ( $score >= 70 ) {
				return 'good';
			} elseif ( $score >= 50 ) {
				return 'fair';
			} else {
				return 'poor';
			}
		}

		// NO issues = Score-based (excellent possible)
		if ( $score >= 90 ) {
			return 'excellent';
		} elseif ( $score >= 70 ) {
			return 'good';
		} elseif ( $score >= 50 ) {
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

			// Calculate coverage data to resolve products (same as campaigns list)
			$coverage_data = $this->calculate_simple_coverage( $campaign );

			// Pass context with coverage data to ensure consistent health analysis
			$context  = array(
				'coverage_data' => $coverage_data,
				'view_context'  => 'dashboard',
			);
			$analysis = $this->analyze_health( $campaign, $mode, $context );

			// Aggregate scores
			$total_score += $analysis['score'];

			// Get campaign info for issue context
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

			// Create unique key for this issue type
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

		// Build deduplicated list
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

			// Create unique key for this warning type
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

		// Build deduplicated list
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

		// Calculate overall health using improved severity-based logic
		$average_score = $campaign_count > 0 ? round( $total_score / $campaign_count ) : 100;

		if ( ! empty( $deduplicated_warnings ) ) {
		}

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
		// Validate inputs
		if ( ! $product_id || $days <= 0 ) {
			return 0;
		}

		// Check if WooCommerce is available
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		try {
			// Calculate date range (use gmdate for UTC consistency)
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

			// Calculate total quantity sold
			$total_quantity = 0;

			foreach ( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					continue;
				}

				// Check each order item
				foreach ( $order->get_items() as $item ) {
					$item_product_id   = $item->get_product_id();
					$item_variation_id = $item->get_variation_id();

					// Match product ID or variation ID
					if ( $item_product_id === $product_id || $item_variation_id === $product_id ) {
						$total_quantity += $item->get_quantity();
					}
				}
			}

			// Calculate average per day
			return $total_quantity / $days;

		} catch ( Exception $e ) {
			// Log error but don't break the health check
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SCD Health Service: Error calculating average daily sales - ' . $e->getMessage() );
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

			// Calculate estimated demand
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
}
