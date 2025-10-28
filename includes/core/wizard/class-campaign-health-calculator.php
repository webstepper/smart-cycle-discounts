<?php
/**
 * Campaign Health Calculator
 *
 * Calculates campaign health score and identifies issues.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign Health Calculator Class
 *
 * Analyzes campaign configuration and calculates health score (0-100).
 *
 * Health Score Breakdown:
 * - All required fields filled: 20 points
 * - No critical issues: 30 points
 * - Good coverage (>70%): 20 points
 * - Reasonable discount (<50%): 15 points
 * - No conflicts: 15 points
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class SCD_Campaign_Health_Calculator {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Campaign data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $campaign_data    Campaign data from all steps.
	 */
	private $campaign_data;

	/**
	 * Coverage data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $coverage_data    Coverage analysis data.
	 */
	private $coverage_data;

	/**
	 * Conflicts data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $conflicts_data    Conflicts analysis data.
	 */
	private $conflicts_data;

	/**
	 * Validation handler.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Validation_Handler    $validation_handler    Validation handler.
	 */
	private $validation_handler;

	/**
	 * Validation results.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $validation_results    Validation results.
	 */
	private $validation_results;

	/**
	 * Campaign health service instance (optional).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Health_Service    $health_service    Campaign health service.
	 */
	private $health_service;

	/**
	 * Initialize the calculator.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_State_Service    $state_service     State service instance.
	 * @param    SCD_Campaign_Health_Service $health_service    Optional health service instance.
	 */
	public function __construct( $state_service, $health_service = null ) {
		$this->state_service  = $state_service;
		$this->health_service = $health_service;
		$this->campaign_data  = array(
			'basic'     => $state_service->get_step_data( 'basic' ),
			'products'  => $state_service->get_step_data( 'products' ),
			'discounts' => $state_service->get_step_data( 'discounts' ),
			'schedule'  => $state_service->get_step_data( 'schedule' ),
		);

		// Initialize validation handler
		$this->validation_handler = new SCD_Campaign_Validation_Handler();

		// Run validation
		$this->validation_results = $this->validation_handler->validate( $this->campaign_data );
	}

	/**
	 * Calculate campaign health score and analysis.
	 *
	 * @since    1.0.0
	 * @param    array $coverage_data    Coverage data from handler.
	 * @param    array $conflicts_data   Conflicts data from handler.
	 * @return   array                      Health analysis.
	 */
	public function calculate( $coverage_data, $conflicts_data ) {
		$this->coverage_data  = $coverage_data;
		$this->conflicts_data = $conflicts_data;

		$score     = 0;
		$max_score = 100;

		// 1. All required fields filled (20 points)
		$fields_score = $this->_calculate_fields_score();
		$score       += $fields_score;

		// 2. No critical issues (30 points)
		$critical_score = $this->_calculate_critical_score();
		$score         += $critical_score;

		// 3. Good coverage >70% (20 points)
		$coverage_score = $this->_calculate_coverage_score();
		$score         += $coverage_score;

		// 4. Reasonable discount <50% (15 points)
		$discount_score = $this->_calculate_discount_score();
		$score         += $discount_score;

		// 5. No conflicts (15 points)
		$conflict_score = $this->_calculate_conflict_score();
		$score         += $conflict_score;

		// Get all issues
		$critical_issues = $this->_get_critical_issues();
		$recommendations = $this->_get_enhanced_recommendations();

		// Calculate readiness
		$is_ready = empty( $critical_issues );

		// Calculate enhanced data
		$conflict_preview    = $this->_get_conflict_preview();
		$enhanced_exclusions = $this->_get_enhanced_exclusions();
		$stock_risk          = $this->_assess_stock_risk();

		return array(
			'score'               => min( $score, $max_score ),
			'max_score'           => $max_score,
			'percentage'          => round( ( min( $score, $max_score ) / $max_score ) * 100 ),
			'status'              => $this->_get_status_from_score( $score ),
			'is_ready'            => $is_ready,
			'critical_issues'     => $critical_issues,
			'recommendations'     => $recommendations,
			'stock_risk'          => $stock_risk,
			'breakdown'           => array(
				'fields'    => array(
					'score'  => $fields_score,
					'max'    => 20,
					'label'  => __( 'Required Fields Complete', 'smart-cycle-discounts' ),
					'status' => 20 === $fields_score ? 'positive' : 'negative',
				),
				'critical'  => array(
					'score'  => $critical_score,
					'max'    => 30,
					'label'  => __( 'No Critical Issues', 'smart-cycle-discounts' ),
					'status' => 30 === $critical_score ? 'positive' : 'negative',
				),
				'coverage'  => array(
					'score'  => $coverage_score,
					'max'    => 20,
					'label'  => __( 'Product Coverage', 'smart-cycle-discounts' ),
					'status' => $coverage_score >= 15 ? 'positive' : 'negative',
				),
				'discount'  => array(
					'score'  => $discount_score,
					'max'    => 15,
					'label'  => __( 'Discount Reasonableness', 'smart-cycle-discounts' ),
					'status' => $discount_score >= 10 ? 'positive' : 'negative',
				),
				'conflicts' => array(
					'score'  => $conflict_score,
					'max'    => 15,
					'label'  => __( 'No Conflicts', 'smart-cycle-discounts' ),
					'status' => 15 === $conflict_score ? 'positive' : 'negative',
				),
			),
			'conflict_preview'    => $conflict_preview,
			'enhanced_exclusions' => $enhanced_exclusions,
		);
	}

	/**
	 * Calculate fields completion score.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Score (0-20).
	 */
	private function _calculate_fields_score() {
		$required_fields = array(
			'basic'     => array( 'name', 'priority' ),
			'products'  => array( 'product_selection_type' ),
			'discounts' => array( 'discount_type', 'discount_value' ),
			'schedule'  => array( 'start_type' ),
		);

		$total_required = 0;
		$filled         = 0;

		foreach ( $required_fields as $step => $fields ) {
			foreach ( $fields as $field ) {
				++$total_required;
				if ( isset( $this->campaign_data[ $step ][ $field ] ) && ! empty( $this->campaign_data[ $step ][ $field ] ) ) {
					++$filled;
				}
			}
		}

		return $total_required > 0 ? round( ( $filled / $total_required ) * 20 ) : 0;
	}

	/**
	 * Calculate critical issues score.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Score (0-30).
	 */
	private function _calculate_critical_score() {
		$critical_issues = $this->_get_critical_issues();
		return empty( $critical_issues ) ? 30 : 0;
	}

	/**
	 * Calculate coverage score.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Score (0-20).
	 */
	private function _calculate_coverage_score() {
		if ( empty( $this->coverage_data ) ) {
			return 0;
		}

		$coverage_percentage = isset( $this->coverage_data['coverage_percentage'] ) ? $this->coverage_data['coverage_percentage'] : 0;

		if ( $coverage_percentage >= 70 ) {
			return 20;
		} elseif ( $coverage_percentage >= 50 ) {
			return 15;
		} elseif ( $coverage_percentage >= 30 ) {
			return 10;
		} else {
			return 5;
		}
	}

	/**
	 * Calculate discount reasonableness score.
	 *
	 * Aligned with recommendation severity to ensure score matches warnings shown.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Score (0-15).
	 */
	private function _calculate_discount_score() {
		$discount_type = isset( $this->campaign_data['discounts']['discount_type'] ) ? $this->campaign_data['discounts']['discount_type'] : 'percentage';

		// Get discount value based on type (forms use separate fields)
		$discount_value = 0;
		if ( 'percentage' === $discount_type ) {
			$discount_value = isset( $this->campaign_data['discounts']['discount_value_percentage'] ) ? floatval( $this->campaign_data['discounts']['discount_value_percentage'] ) : 0;
		} elseif ( 'fixed' === $discount_type ) {
			$discount_value = isset( $this->campaign_data['discounts']['discount_value_fixed'] ) ? floatval( $this->campaign_data['discounts']['discount_value_fixed'] ) : 0;
		}

		if ( 'percentage' !== $discount_type ) {
			return 15; // Can't easily judge fixed/tiered/bogo
		}

		// Aligned with recommendation thresholds
		if ( $discount_value < 5 ) {
			return 5; // Too low (HIGH priority warning)
		} elseif ( $discount_value <= 50 ) {
			return 15; // Sweet spot
		} elseif ( $discount_value <= 70 ) {
			return 8; // Getting high (may trigger HIGH priority warnings)
		} elseif ( $discount_value < 90 ) {
			return 3; // Very high (HIGH priority warning)
		} else {
			return 0; // Extremely high (CRITICAL warning)
		}
	}

	/**
	 * Calculate conflicts score.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Score (0-15).
	 */
	private function _calculate_conflict_score() {
		if ( empty( $this->conflicts_data ) ) {
			return 15;
		}

		$has_conflicts = isset( $this->conflicts_data['has_conflicts'] ) ? $this->conflicts_data['has_conflicts'] : false;

		return $has_conflicts ? 0 : 15;
	}

	/**
	 * Get status label from score.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $score    Health score.
	 * @return   string              Status label.
	 */
	private function _get_status_from_score( $score ) {
		if ( $score >= 80 ) {
			return 'excellent';
		} elseif ( $score >= 60 ) {
			return 'good';
		} elseif ( $score >= 40 ) {
			return 'fair';
		} else {
			return 'poor';
		}
	}

	/**
	 * Get critical issues.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of critical issues.
	 */
	private function _get_critical_issues() {
		$issues = array();

		// Get critical errors from validation handler (form-level validation)
		if ( ! empty( $this->validation_results['errors'] ) ) {
			foreach ( $this->validation_results['errors'] as $error ) {
				$issues[] = array(
					'code'    => $error['code'],
					'message' => $error['message'],
					'step'    => isset( $error['step'] ) ? $error['step'] : '',
				);
			}
		}

		// Use unified health service for configuration checks (business rules)
		if ( $this->health_service ) {
			// Prepare campaign data for health service
			$campaign_for_service = $this->_prepare_campaign_data_for_service();

			// Get health analysis from unified service with review context
			$health_analysis = $this->health_service->analyze_health(
				$campaign_for_service,
				'comprehensive',
				array( 'view_context' => 'review' )
			);

			// Add service critical issues with step mapping
			if ( ! empty( $health_analysis['critical_issues'] ) ) {
				foreach ( $health_analysis['critical_issues'] as $service_issue ) {
					$issues[] = array(
						'code'    => isset( $service_issue['code'] ) ? $service_issue['code'] : 'validation_error',
						'message' => isset( $service_issue['message'] ) ? $service_issue['message'] : '',
						'step'    => $this->_map_issue_to_step( isset( $service_issue['code'] ) ? $service_issue['code'] : '' ),
					);
				}
			}
		}

		// Remove duplicates by code
		$unique_issues = array();
		$seen_codes    = array();

		foreach ( $issues as $issue ) {
			if ( ! in_array( $issue['code'], $seen_codes, true ) ) {
				$unique_issues[] = $issue;
				$seen_codes[]    = $issue['code'];
			}
		}

		return $unique_issues;
	}

	/**
	 * Prepare campaign data for health service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Campaign data in format expected by health service.
	 */
	private function _prepare_campaign_data_for_service() {
		$start_date = isset( $this->campaign_data['schedule']['start_date'] ) ? $this->campaign_data['schedule']['start_date'] : null;
		$end_date   = isset( $this->campaign_data['schedule']['end_date'] ) ? $this->campaign_data['schedule']['end_date'] : null;

		return array(
			'name'                   => isset( $this->campaign_data['basic']['name'] ) ? $this->campaign_data['basic']['name'] : '',
			'description'            => isset( $this->campaign_data['basic']['description'] ) ? $this->campaign_data['basic']['description'] : '',
			'discount_type'          => isset( $this->campaign_data['discounts']['discount_type'] ) ? $this->campaign_data['discounts']['discount_type'] : '',
			'discount_value'         => $this->_get_discount_value_for_service(),
			'product_selection_type' => isset( $this->campaign_data['products']['product_selection_type'] ) ? $this->campaign_data['products']['product_selection_type'] : '',
			'selected_product_ids'   => isset( $this->campaign_data['products']['selected_product_ids'] ) ? $this->campaign_data['products']['selected_product_ids'] : array(),
			'start_date'             => $start_date,
			'end_date'               => $end_date,
			'start_time'             => isset( $this->campaign_data['schedule']['start_time'] ) ? $this->campaign_data['schedule']['start_time'] : '00:00',
			'end_time'               => isset( $this->campaign_data['schedule']['end_time'] ) ? $this->campaign_data['schedule']['end_time'] : '23:59',
			'start_type'             => isset( $this->campaign_data['schedule']['start_type'] ) ? $this->campaign_data['schedule']['start_type'] : 'scheduled',
			'status'                 => 'draft', // Wizard campaigns are not yet saved
		);
	}

	/**
	 * Get discount value for health service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   float    Discount value.
	 */
	private function _get_discount_value_for_service() {
		$discount_type = isset( $this->campaign_data['discounts']['discount_type'] ) ? $this->campaign_data['discounts']['discount_type'] : '';

		if ( 'percentage' === $discount_type ) {
			return isset( $this->campaign_data['discounts']['discount_value_percentage'] ) ? floatval( $this->campaign_data['discounts']['discount_value_percentage'] ) : 0;
		} elseif ( 'fixed' === $discount_type ) {
			return isset( $this->campaign_data['discounts']['discount_value_fixed'] ) ? floatval( $this->campaign_data['discounts']['discount_value_fixed'] ) : 0;
		}

		return 0;
	}

	/**
	 * Map issue code to wizard step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $code    Issue code.
	 * @return   string             Step name.
	 */
	private function _map_issue_to_step( $code ) {
		$step_mapping = array(
			'missing_name'       => 'basic',
			'missing_discount'   => 'discounts',
			'missing_products'   => 'products',
			'expired_but_active' => 'schedule',
			'scheduled_past'     => 'schedule',
			'extreme_discount'   => 'discounts',
			'very_high_discount' => 'discounts',
			'low_discount'       => 'discounts',
		);

		return isset( $step_mapping[ $code ] ) ? $step_mapping[ $code ] : '';
	}

	/**
	 * Get recommendations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of recommendations.
	 */
	private function _get_recommendations() {
		$recommendations = array();

		// Campaign name quality
		$name = isset( $this->campaign_data['basic']['name'] ) ? $this->campaign_data['basic']['name'] : '';
		if ( ! empty( $name ) && strlen( $name ) < 10 ) {
			$recommendations[] = __( 'Use a more descriptive campaign name', 'smart-cycle-discounts' );
		}

		// Description missing
		if ( empty( $this->campaign_data['basic']['description'] ) ) {
			$recommendations[] = __( 'Add a description to remember campaign details later', 'smart-cycle-discounts' );
		}

		// No end date
		if ( empty( $this->campaign_data['schedule']['end_date'] ) ) {
			$recommendations[] = __( 'Consider setting an end date to prevent campaigns running indefinitely', 'smart-cycle-discounts' );
		}

		return $recommendations;
	}

	/**
	 * Get enhanced recommendations with categories and priorities.
	 *
	 * Smart, context-aware recommendations based on campaign configuration,
	 * product data, and store status. Only shows relevant suggestions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of categorized recommendations.
	 */
	private function _get_enhanced_recommendations() {
		$recommendations = array();

		// Get product analysis data
		$product_stats = $this->_analyze_product_selection();

		// Discount Strategy Recommendations (plugin-actionable)
		$discount_recommendations = $this->_get_discount_strategy_recommendations( $product_stats );
		$recommendations          = array_merge( $recommendations, $discount_recommendations );

		// Timing Strategy Recommendations (plugin-actionable)
		$timing_recommendations = $this->_get_timing_strategy_recommendations();
		$recommendations        = array_merge( $recommendations, $timing_recommendations );

		// Product Selection Recommendations (plugin-actionable)
		$product_recommendations = $this->_get_product_selection_recommendations( $product_stats );
		$recommendations         = array_merge( $recommendations, $product_recommendations );

		// Campaign Management Recommendations (plugin-actionable)
		$management_recommendations = $this->_get_campaign_management_recommendations();
		$recommendations            = array_merge( $recommendations, $management_recommendations );

		// Discount Rules Warnings (configuration edge cases)
		$rules_warnings  = $this->_get_discount_rules_warnings();
		$recommendations = array_merge( $recommendations, $rules_warnings );

		// Add unique IDs to each recommendation for tracking
		foreach ( $recommendations as &$rec ) {
			$rec['id'] = $this->_generate_recommendation_id( $rec );
		}

		return $recommendations;
	}

	/**
	 * Generate unique ID for recommendation tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $recommendation    Recommendation data.
	 * @return   string                      Unique ID.
	 */
	private function _generate_recommendation_id( $recommendation ) {
		$parts = array(
			isset( $recommendation['category'] ) ? $recommendation['category'] : '',
			isset( $recommendation['priority'] ) ? $recommendation['priority'] : '',
			isset( $recommendation['message'] ) ? substr( $recommendation['message'], 0, 50 ) : '',
		);

		return md5( implode( '|', $parts ) );
	}

	/**
	 * Analyze product selection for recommendation generation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Product statistics and analysis.
	 */
	private function _analyze_product_selection() {
		$product_ids = $this->_get_selected_product_ids();

		$stats = array(
			'count'                => count( $product_ids ),
			'min_price'            => 0,
			'max_price'            => 0,
			'avg_price'            => 0,
			'total_value'          => 0,
			'price_variance_ratio' => 0,
			'has_stock_managed'    => false,
			'low_stock_count'      => 0,
			'out_of_stock_count'   => 0,
			'total_stock'          => 0,
		);

		if ( empty( $product_ids ) || ! class_exists( 'WooCommerce' ) ) {
			return $stats;
		}

		$prices       = array();
		$stock_counts = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Price analysis
			$price = $product->get_price();
			if ( '' !== $price && null !== $price ) {
				$prices[] = floatval( $price );
			}

			// Stock analysis
			if ( $product->managing_stock() ) {
				$stats['has_stock_managed'] = true;
				$stock                      = $product->get_stock_quantity();
				if ( null !== $stock ) {
					$stock_counts[] = intval( $stock );
					if ( $stock < 5 ) {
						++$stats['low_stock_count'];
					}
					if ( $stock < 1 ) {
						++$stats['out_of_stock_count'];
					}
				}
			}
		}

		// Calculate price statistics
		if ( ! empty( $prices ) ) {
			$stats['min_price']   = min( $prices );
			$stats['max_price']   = max( $prices );
			$stats['avg_price']   = array_sum( $prices ) / count( $prices );
			$stats['total_value'] = array_sum( $prices );

			if ( $stats['min_price'] > 0 ) {
				$stats['price_variance_ratio'] = $stats['max_price'] / $stats['min_price'];
			}
		}

		// Calculate stock statistics
		if ( ! empty( $stock_counts ) ) {
			$stats['total_stock'] = array_sum( $stock_counts );
		}

		return $stats;
	}

	/**
	 * Get discount strategy recommendations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_stats    Product statistics.
	 * @return   array                      Recommendations array.
	 */
	private function _get_discount_strategy_recommendations( $product_stats ) {
		$recommendations = array();
		$discount_type   = isset( $this->campaign_data['discounts']['discount_type'] ) ? $this->campaign_data['discounts']['discount_type'] : '';

		// Get discount value
		$discount_value = 0;
		if ( 'percentage' === $discount_type ) {
			$discount_value = isset( $this->campaign_data['discounts']['discount_value_percentage'] ) ? floatval( $this->campaign_data['discounts']['discount_value_percentage'] ) : 0;
		} elseif ( 'fixed' === $discount_type ) {
			$discount_value = isset( $this->campaign_data['discounts']['discount_value_fixed'] ) ? floatval( $this->campaign_data['discounts']['discount_value_fixed'] ) : 0;
		}

		// CRITICAL: Extremely high discount (90-99%)
		if ( 'percentage' === $discount_type && $discount_value >= 90 && $discount_value < 100 && $product_stats['avg_price'] > 0 ) {
			$avg_final            = $product_stats['avg_price'] * ( ( 100 - $discount_value ) / 100 );
			$recommended_discount = 50;

			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'critical',
				'impact'      => __( 'Protect profit margins and brand value', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'CRITICAL: %1$d%% discount is extremely high - a %2$s product becomes %3$s. This may cause significant profit loss and brand damage.', 'smart-cycle-discounts' ),
					$discount_value,
					$this->_format_price_plain( $product_stats['avg_price'] ),
					$this->_format_price_plain( $avg_final )
				),
				'icon'        => 'warning',
				'explanation' => sprintf(
					__( 'A %1$d%% discount leaves only %2$d%% of revenue, which may not cover costs. Such extreme discounts can permanently damage brand perception, train customers to only buy at deep discounts, and signal desperation to the market.', 'smart-cycle-discounts' ),
					$discount_value,
					( 100 - $discount_value )
				),
				'action'      => array(
					'type' => 'update_discount_value',
					'data' => array( 'discount_value' => $recommended_discount ),
				),
				'step'        => 'discount',
			);
		}

		// HIGH: Very high discount (70-89%)
		if ( 'percentage' === $discount_type && $discount_value >= 70 && $discount_value < 90 ) {
			$recommended_discount = 50;

			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'high',
				'impact'      => __( 'Improve profit margins by 20-30%', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( '%1$d%% discount is unusually high. Verify this is intentional and won\'t cause profit loss. Consider reducing to %2$d%%.', 'smart-cycle-discounts' ),
					$discount_value,
					$recommended_discount
				),
				'icon'        => 'warning',
				'explanation' => sprintf(
					__( 'Discounts above 70%% significantly impact profit margins and can devalue your brand. Unless this is a liquidation sale, consider whether you can achieve your goals with a more sustainable discount around %d%%.', 'smart-cycle-discounts' ),
					$recommended_discount
				),
				'action'      => array(
					'type' => 'update_discount_value',
					'data' => array( 'discount_value' => $recommended_discount ),
				),
				'step'        => 'discount',
			);
		}

		// HIGH: Discount too low (<5%)
		if ( 'percentage' === $discount_type && $discount_value > 0 && $discount_value < 5 ) {
			$recommended_discount = 15;

			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'high',
				'impact'      => __( 'Increase conversion rates significantly', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( '%1$d%% discount may not be compelling enough to drive conversions. Consider increasing to at least %2$d%% for better results.', 'smart-cycle-discounts' ),
					$discount_value,
					$recommended_discount
				),
				'icon'        => 'chart-line',
				'explanation' => __( 'Research shows that discounts below 10% often fail to motivate purchase behavior because they don\'t overcome customer inertia. The "sweet spot" for percentage discounts that drive action is typically 15-30%.', 'smart-cycle-discounts' ),
				'action'      => array(
					'type' => 'update_discount_value',
					'data' => array( 'discount_value' => $recommended_discount ),
				),
				'step'        => 'discount',
			);
		}

		// Recommendation: Switch high percentage to tiered
		// For mid-range products €50-€150 (low-price products <€50 get BOGO recommendation instead)
		if ( 'percentage' === $discount_type && $discount_value > 50 && $discount_value < 90 && $product_stats['avg_price'] >= 50 && $product_stats['avg_price'] < 150 ) {
			$tier1 = max( 15, round( $discount_value * 0.5 ) );
			$tier2 = max( 25, round( $discount_value * 0.7 ) );
			$tier3 = max( 35, round( $discount_value * 0.85 ) );

			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'high',
				'impact'      => __( 'Could improve margins by 20-30%', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'Switch to tiered pricing instead of flat %1$d%% - try: Buy 2 get %2$d%% off, Buy 3 get %3$d%% off, Buy 5+ get %4$d%% off. Same perceived value, better margins.', 'smart-cycle-discounts' ),
					$discount_value,
					$tier1,
					$tier2,
					$tier3
				),
				'icon'        => 'chart',
				'explanation' => sprintf(
					__( 'Your current %d%% flat discount gives the same rate regardless of cart size. Tiered discounts reward higher quantities with better rates, encouraging customers to buy more while protecting your margins on smaller orders.', 'smart-cycle-discounts' ),
					$discount_value
				),
				'action'      => array(
					'type' => 'change_discount_type',
					'data' => array(
						'discount_type' => 'tiered',
						'tiers'         => array(
							array(
								'quantity' => 2,
								'discount' => $tier1,
							),
							array(
								'quantity' => 3,
								'discount' => $tier2,
							),
							array(
								'quantity' => 5,
								'discount' => $tier3,
							),
						),
					),
				),
				'step'        => 'discount',
			);
		}

		// Recommendation: Fixed discount on wide price range
		if ( 'fixed' === $discount_type && $discount_value > 0 && $product_stats['price_variance_ratio'] > 3.0 ) {
			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'high',
				'impact'      => __( 'Improves customer experience consistency', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'Your price range (%1$s - %2$s) is too wide for a %3$s fixed discount. Switch to percentage discount or split into separate campaigns by price tier.', 'smart-cycle-discounts' ),
					$this->_format_price_plain( $product_stats['min_price'] ),
					$this->_format_price_plain( $product_stats['max_price'] ),
					$this->_format_price_plain( $discount_value )
				),
				'icon'        => 'warning',
				'explanation' => sprintf(
					__( 'A %1$s fixed discount represents %2$d%% off your cheapest product but only %3$d%% off your most expensive. This creates an inconsistent customer experience. Percentage discounts provide fair value across all price points.', 'smart-cycle-discounts' ),
					$this->_format_price_plain( $discount_value ),
					round( ( $discount_value / $product_stats['min_price'] ) * 100 ),
					round( ( $discount_value / $product_stats['max_price'] ) * 100 )
				),
				'action'      => array(
					'type' => 'change_discount_type',
					'data' => array(
						'discount_type'  => 'percentage',
						'discount_value' => round( ( $discount_value / $product_stats['avg_price'] ) * 100 ),
					),
				),
				'step'        => 'discount',
			);
		}

		// Recommendation: BOGO alternative for high-margin products
		if ( 'percentage' === $discount_type && $discount_value >= 50 && $product_stats['avg_price'] < 50 && $product_stats['count'] > 5 ) {
			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'medium',
				'impact'      => __( 'Could increase volume by 40%', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'Consider "Buy 2 Get 1 Free" instead of %d%% off - similar perceived value but better for inventory turnover and margins.', 'smart-cycle-discounts' ),
					$discount_value
				),
				'icon'        => 'gift',
				'explanation' => __( 'BOGO promotions encourage customers to buy more items per transaction. While a 50%% discount and BOGO offer similar economics, BOGO creates a perception of getting something free, which psychologically drives higher conversion and cart sizes.', 'smart-cycle-discounts' ),
				'action'      => array(
					'type' => 'change_discount_type',
					'data' => array(
						'discount_type' => 'bogo',
						'buy_quantity'  => 2,
						'get_quantity'  => 1,
					),
				),
				'step'        => 'discount',
			);
		}

		// Recommendation: Expensive products need different strategy
		// Only for 30-70% discounts on products ≥€150 (products <€150 get tiered recommendation, avoiding duplication)
		if ( 'percentage' === $discount_type && $discount_value > 30 && $discount_value < 70 && $product_stats['avg_price'] >= 150 ) {
			$recommended_discount = min( 25, round( $discount_value * 0.7 ) );
			$recommendations[]    = array(
				'category'    => 'discount',
				'priority'    => 'high',
				'impact'      => __( 'Maintains brand value and margins', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'High-value products (avg %1$s) perform better with moderate discounts (15-25%%) and shorter campaigns. Consider reducing to %2$d%% to maintain brand perception.', 'smart-cycle-discounts' ),
					$this->_format_price_plain( $product_stats['avg_price'] ),
					$recommended_discount
				),
				'icon'        => 'star',
				'explanation' => sprintf(
					__( 'Deep discounts (%d%%) on premium products can damage brand perception and train customers to wait for sales. Research shows luxury and high-value items maintain better long-term customer value with moderate discounts (15-25%%) that preserve brand integrity.', 'smart-cycle-discounts' ),
					$discount_value
				),
				'action'      => array(
					'type' => 'update_discount_value',
					'data' => array(
						'discount_value' => $recommended_discount,
					),
				),
				'step'        => 'discount',
			);
		}

		// Recommendation: Discount Depth Warning
		// Only show if more specific recommendations (tiered, BOGO, expensive products) don't apply
		$show_depth_warning = false;
		if ( 'percentage' === $discount_type && $discount_value > 50 && $discount_value < 70 ) {
			// Check if other specific recommendations will trigger
			$has_tiered_rec    = ( $product_stats['avg_price'] >= 50 && $product_stats['avg_price'] < 150 );
			$has_bogo_rec      = ( $product_stats['avg_price'] < 50 && $product_stats['count'] > 5 );
			$has_expensive_rec = ( $product_stats['avg_price'] >= 150 );

			// Only show depth warning if none of the specific recommendations apply
			if ( ! $has_tiered_rec && ! $has_bogo_rec && ! $has_expensive_rec ) {
				$show_depth_warning = true;
			}
		}

		if ( $show_depth_warning ) {
			$recommended_discount = 35;

			$recommendations[] = array(
				'category'    => 'discount',
				'priority'    => 'high',
				'impact'      => __( 'Could improve brand perception and margins by 15-20%', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'Your %1$d%% discount is very deep - consider %2$d%% with stronger messaging ("Limited Stock" or "Final Hours") to maintain brand value while creating urgency.', 'smart-cycle-discounts' ),
					$discount_value,
					$recommended_discount
				),
				'icon'        => 'warning',
				'explanation' => __( 'Discounts over 50% can devalue your brand and train customers to only buy during extreme sales. Research shows 30-40% discounts with scarcity messaging often perform better than deeper discounts while protecting margins and brand equity.', 'smart-cycle-discounts' ),
				'action'      => array(
					'type' => 'update_discount_value',
					'data' => array( 'discount_value' => $recommended_discount ),
				),
				'step'        => 'discount',
			);
		}

		// HIGH: Fixed discount exceeds product prices (25-49% affected - recommendation; >=50% remains as error)
		if ( 'fixed' === $discount_type && $discount_value > 0 && $product_stats['count'] > 0 ) {
			$product_ids    = $this->_get_selected_product_ids();
			$product_prices = array();

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product_prices[] = floatval( $product->get_price() );
				}
			}

			if ( ! empty( $product_prices ) ) {
				$affected_count = 0;
				foreach ( $product_prices as $price ) {
					if ( $price > 0 && $discount_value >= $price ) {
						++$affected_count;
					}
				}

				if ( $affected_count > 0 ) {
					$percentage_affected = ( $affected_count / count( $product_prices ) ) * 100;

					// Show recommendation for 25-49% affected (>=50% handled as critical error in validation)
					if ( $percentage_affected >= 25 && $percentage_affected < 50 ) {
						$min_price            = min( $product_prices );
						$recommended_discount = max( 1, $min_price - 0.01 );

						$recommendations[] = array(
							'category'    => 'discount',
							'priority'    => 'high',
							'impact'      => __( 'Prevent giving products away for free', 'smart-cycle-discounts' ),
							'message'     => sprintf(
								__( 'Your %1$s fixed discount exceeds the price of %2$d products (%3$d%% of selection). These products will be given away for FREE.', 'smart-cycle-discounts' ),
								$this->_format_price_plain( $discount_value ),
								$affected_count,
								round( $percentage_affected )
							),
							'icon'        => 'warning',
							'explanation' => sprintf(
								__( 'When a fixed discount equals or exceeds product prices, those products become free. This causes revenue loss and inventory issues. Consider reducing your discount to %s or switching to a percentage discount.', 'smart-cycle-discounts' ),
								$this->_format_price_plain( $recommended_discount )
							),
							'action'      => array(
								'type' => 'change_discount_type',
								'data' => array(
									'discount_type'  => 'percentage',
									'discount_value' => 25,
								),
							),
							'step'        => 'discount',
						);
					}
				}
			}
		}

		// HIGH: BOGO insufficient stock
		if ( 'bogo' === $discount_type ) {
			$buy_quantity      = isset( $this->campaign_data['discounts']['bogo_buy_quantity'] ) ? intval( $this->campaign_data['discounts']['bogo_buy_quantity'] ) : 1;
			$get_quantity      = isset( $this->campaign_data['discounts']['bogo_get_quantity'] ) ? intval( $this->campaign_data['discounts']['bogo_get_quantity'] ) : 1;
			$required_quantity = $buy_quantity + $get_quantity;

			$product_ids        = $this->_get_selected_product_ids();
			$low_stock_products = array();

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->managing_stock() ) {
					$stock = $product->get_stock_quantity();
					if ( null !== $stock && $stock < $required_quantity ) {
						$low_stock_products[] = array(
							'id'       => $product_id,
							'name'     => $product->get_name(),
							'stock'    => $stock,
							'required' => $required_quantity,
						);
					}
				}
			}

			if ( ! empty( $low_stock_products ) ) {
				$count        = count( $low_stock_products );
				$sample       = array_slice( $low_stock_products, 0, 3 );
				$product_list = array();

				foreach ( $sample as $prod ) {
					$product_list[] = sprintf( '%s (Stock: %d, Required: %d)', $prod['name'], $prod['stock'], $prod['required'] );
				}

				$message = sprintf(
					__( 'Your "Buy %1$d Get %2$d" campaign includes %3$d product(s) with insufficient stock. Customers cannot complete the offer for these items.', 'smart-cycle-discounts' ),
					$buy_quantity,
					$get_quantity,
					$count
				);

				if ( $count > 3 ) {
					$message .= ' ' . sprintf( __( 'Examples: %s', 'smart-cycle-discounts' ), implode( ', ', $product_list ) );
				}

				$recommendations[] = array(
					'category'    => 'discount',
					'priority'    => 'high',
					'impact'      => __( 'Prevent customer frustration', 'smart-cycle-discounts' ),
					'message'     => $message,
					'icon'        => 'warning',
					'explanation' => sprintf(
						__( 'BOGO offers require customers to purchase %1$d item(s) to receive %2$d free. Products with stock below %3$d units cannot fulfill this offer, leading to customer disappointment and potential refund requests.', 'smart-cycle-discounts' ),
						$buy_quantity,
						$get_quantity,
						$required_quantity
					),
					'action'      => null,
					'step'        => 'products',
				);
			}
		}

		// HIGH: Tiered discount unreachable
		if ( 'tiered' === $discount_type ) {
			$tiers = isset( $this->campaign_data['discounts']['tiers'] ) ? $this->campaign_data['discounts']['tiers'] : array();

			if ( ! empty( $tiers ) ) {
				$product_ids = $this->_get_selected_product_ids();
				$max_stock   = 0;

				foreach ( $product_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product && $product->managing_stock() ) {
						$stock = $product->get_stock_quantity();
						if ( null !== $stock && $stock > $max_stock ) {
							$max_stock = $stock;
						}
					}
				}

				if ( $max_stock > 0 ) {
					$unreachable_tiers = array();

					foreach ( $tiers as $tier ) {
						$threshold = isset( $tier['threshold'] ) ? intval( $tier['threshold'] ) : 0;
						if ( $threshold > $max_stock ) {
							$discount_tier_value = isset( $tier['discount_value'] ) ? $tier['discount_value'] : 0;
							$unreachable_tiers[] = array(
								'threshold' => $threshold,
								'discount'  => $discount_tier_value,
							);
						}
					}

					if ( ! empty( $unreachable_tiers ) ) {
						$count      = count( $unreachable_tiers );
						$first_tier = $unreachable_tiers[0];

						$recommendations[] = array(
							'category'    => 'discount',
							'priority'    => 'high',
							'impact'      => __( 'Improve customer experience', 'smart-cycle-discounts' ),
							'message'     => sprintf(
								__( '%1$d discount tier(s) cannot be reached because the highest stock level is %2$d units. For example, %3$d%% off at %4$d items requires more stock than available.', 'smart-cycle-discounts' ),
								$count,
								$max_stock,
								$first_tier['discount'],
								$first_tier['threshold']
							),
							'icon'        => 'warning',
							'explanation' => sprintf(
								__( 'Tiered discounts encourage customers to buy more by offering better rates at higher quantities. However, tiers requiring more units than your available stock frustrate customers and reduce trust in your offers.', 'smart-cycle-discounts' ),
								$max_stock
							),
							'action'      => null,
							'step'        => 'discount',
						);
					}
				}
			}
		}

		return $recommendations;
	}

	/**
	 * Get timing strategy recommendations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Recommendations array.
	 */
	private function _get_timing_strategy_recommendations() {
		$recommendations = array();
		$schedule        = isset( $this->campaign_data['schedule'] ) ? $this->campaign_data['schedule'] : array();
		$start_type      = isset( $schedule['start_type'] ) ? $schedule['start_type'] : 'immediate';
		$start_date      = isset( $schedule['start_date'] ) ? $schedule['start_date'] : '';
		$end_date        = isset( $schedule['end_date'] ) ? $schedule['end_date'] : '';

		// Calculate duration
		$duration_days      = null;
		$campaign_tz_string = isset( $schedule['timezone'] ) ? $schedule['timezone'] : wp_timezone_string();

		try {
			$campaign_tz = new DateTimeZone( $campaign_tz_string );

			if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
				// Combine date and time if available
				$start_time = isset( $schedule['start_time'] ) && ! empty( $schedule['start_time'] ) ? $schedule['start_time'] : '00:00';
				$end_time   = isset( $schedule['end_time'] ) && ! empty( $schedule['end_time'] ) ? $schedule['end_time'] : '23:59';

				$start_dt = scd_combine_date_time( $start_date, $start_time, $campaign_tz_string );
				$end_dt   = scd_combine_date_time( $end_date, $end_time, $campaign_tz_string );

				if ( ! $start_dt || ! $end_dt ) {
					// Invalid date/time - skip duration calculation
					$duration_days = null;
				}

				if ( $end_dt > $start_dt ) {
					$duration_days = ceil( ( $end_dt->getTimestamp() - $start_dt->getTimestamp() ) / DAY_IN_SECONDS );
				}
			} elseif ( 'immediate' === $start_type && ! empty( $end_date ) ) {
				$now_dt = new DateTime( 'now', $campaign_tz );

				$end_time = isset( $schedule['end_time'] ) && ! empty( $schedule['end_time'] ) ? $schedule['end_time'] : '23:59';
				$end_dt   = scd_combine_date_time( $end_date, $end_time, $campaign_tz_string );

				if ( ! $end_dt ) {
					// Invalid end date/time - skip duration calculation
					$duration_days = null;
				}

				if ( $end_dt > $now_dt ) {
					$duration_days = ceil( ( $end_dt->getTimestamp() - $now_dt->getTimestamp() ) / DAY_IN_SECONDS );
				}
			}
		} catch ( Exception $e ) {
			// Fallback to old behavior
			if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
				$start_ts = strtotime( $start_date );
				$end_ts   = strtotime( $end_date );
				if ( $start_ts && $end_ts && $end_ts > $start_ts ) {
					$duration_days = ceil( ( $end_ts - $start_ts ) / DAY_IN_SECONDS );
				}
			} elseif ( 'immediate' === $start_type && ! empty( $end_date ) ) {
				$now    = current_time( 'timestamp' );
				$end_ts = strtotime( $end_date );
				if ( $end_ts > $now ) {
					$duration_days = ceil( ( $end_ts - $now ) / DAY_IN_SECONDS );
				}
			}
		}

		// Recommendation: Indefinite campaigns
		if ( empty( $end_date ) ) {
			// Calculate suggested end date (14 days from start or from now)
			$suggested_end_date = '';
			if ( ! empty( $start_date ) ) {
				$suggested_end_date = date( 'Y-m-d', strtotime( $start_date . ' +14 days' ) );
			} elseif ( 'immediate' === $start_type ) {
				$suggested_end_date = date( 'Y-m-d', strtotime( '+14 days' ) );
			}

			$recommendations[] = array(
				'category'    => 'timing',
				'priority'    => 'high',
				'impact'      => __( 'Could increase conversion by 25%', 'smart-cycle-discounts' ),
				'message'     => __( 'Set a 7-14 day duration - limited-time offers create urgency and prevent discount fatigue. Most successful campaigns run 7-14 days.', 'smart-cycle-discounts' ),
				'icon'        => 'clock',
				'explanation' => __( 'Indefinite campaigns lack urgency, leading customers to delay purchases. Limited-time offers create FOMO (fear of missing out) which drives immediate action. Studies show 7-14 day campaigns achieve optimal balance between reach and urgency.', 'smart-cycle-discounts' ),
				'action'      => array(
					'type' => 'set_end_date',
					'data' => array(
						'end_date'      => $suggested_end_date,
						'duration_days' => 14,
					),
				),
				'step'        => 'schedule',
			);
		}

		// Recommendation: Optimal duration for price point
		$product_stats = $this->_analyze_product_selection();
		if ( null !== $duration_days && $duration_days > 14 && $product_stats['avg_price'] > 100 ) {
			// Calculate suggested shorter end date (7 days from start)
			$suggested_end_date = '';
			if ( ! empty( $start_date ) ) {
				$suggested_end_date = date( 'Y-m-d', strtotime( $start_date . ' +7 days' ) );
			} elseif ( 'immediate' === $start_type ) {
				$suggested_end_date = date( 'Y-m-d', strtotime( '+7 days' ) );
			}

			$recommendations[] = array(
				'category'    => 'timing',
				'priority'    => 'medium',
				'impact'      => __( 'Optimizes conversion window', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'High-value items (%s avg) convert best with shorter campaigns (5-7 days) - longer durations reduce urgency.', 'smart-cycle-discounts' ),
					$this->_format_price_plain( $product_stats['avg_price'] )
				),
				'icon'        => 'target',
				'explanation' => sprintf(
					__( 'Expensive purchases require research time, but extended campaigns (%1$d days) reduce urgency. For premium products averaging %2$s, a 5-7 day window provides enough consideration time while maintaining purchase urgency.', 'smart-cycle-discounts' ),
					$duration_days,
					$this->_format_price_plain( $product_stats['avg_price'] )
				),
				'action'      => array(
					'type' => 'set_end_date',
					'data' => array(
						'end_date'      => $suggested_end_date,
						'duration_days' => 7,
					),
				),
				'step'        => 'schedule',
			);
		}

		// Recommendation: Weekend Launch Strategy
		if ( ! empty( $start_date ) && null !== $duration_days ) {
			try {
				$start_time = isset( $schedule['start_time'] ) && ! empty( $schedule['start_time'] ) ? $schedule['start_time'] : '00:00';
				$start_dt   = scd_combine_date_time( $start_date, $start_time, $campaign_tz_string );

				if ( ! $start_dt ) {
					throw new Exception( 'Invalid start date/time' );
				}

				// Get day of week (1=Monday, 7=Sunday)
				$start_day = intval( $start_dt->format( 'N' ) );

				// Recommend weekend start for short campaigns starting mid-week
				if ( $start_day >= 1 && $start_day <= 3 && $duration_days <= 7 ) {
					$recommendations[] = array(
						'category'    => 'timing',
						'priority'    => 'medium',
						'impact'      => __( 'Could increase conversion rate by 20-30%', 'smart-cycle-discounts' ),
						'message'     => __( 'Campaign starts mid-week - Thursday or Friday starts capture better weekend shopping traffic with 20-30% higher conversion rates.', 'smart-cycle-discounts' ),
						'icon'        => 'calendar',
						'explanation' => __( 'E-commerce data shows weekend shoppers (Friday-Sunday) have higher conversion rates and larger cart sizes. Starting campaigns Thursday evening captures both weekend warriors and deal hunters, maximizing impact for short campaigns.', 'smart-cycle-discounts' ),
						'action'      => null, // Manual timing adjustment
						'step'        => 'schedule',
					);
				}
			} catch ( Exception $e ) {
				// Fallback to old behavior
				$start_timestamp = strtotime( $start_date );
				$start_day       = intval( date( 'N', $start_timestamp ) );

				if ( $start_day >= 1 && $start_day <= 3 && $duration_days <= 7 ) {
					$recommendations[] = array(
						'category'    => 'timing',
						'priority'    => 'medium',
						'impact'      => __( 'Could increase conversion rate by 20-30%', 'smart-cycle-discounts' ),
						'message'     => __( 'Campaign starts mid-week - Thursday or Friday starts capture better weekend shopping traffic with 20-30% higher conversion rates.', 'smart-cycle-discounts' ),
						'icon'        => 'calendar',
						'explanation' => __( 'E-commerce data shows weekend shoppers (Friday-Sunday) have higher conversion rates and larger cart sizes. Starting campaigns Thursday evening captures both weekend warriors and deal hunters, maximizing impact for short campaigns.', 'smart-cycle-discounts' ),
						'action'      => null,
						'step'        => 'schedule',
					);
				}
			}
		}

		// HIGH: Past start date
		if ( 'immediate' !== $start_type && ! empty( $start_date ) ) {
			// Get timezone from schedule data (defaults to WordPress timezone)
			$campaign_tz_string = isset( $schedule['timezone'] ) ? $schedule['timezone'] : wp_timezone_string();

			// Check if we have separate date/time fields (from wizard)
			$start_time = isset( $schedule['start_time'] ) && ! empty( $schedule['start_time'] ) ? $schedule['start_time'] : '00:00';

			try {
				// Parse start time in campaign timezone
				$campaign_tz = new DateTimeZone( $campaign_tz_string );
				$start_dt    = scd_combine_date_time( $start_date, $start_time, $campaign_tz_string );

				if ( ! $start_dt ) {
					throw new Exception( 'Invalid start date/time' );
				}

				// Get current time in campaign timezone for fair comparison
				$now_dt = new DateTime( 'now', $campaign_tz );

				if ( $start_dt < $now_dt ) {
					$recommendations[] = array(
						'category'    => 'timing',
						'priority'    => 'high',
						'impact'      => __( 'Avoid unexpected immediate launch', 'smart-cycle-discounts' ),
						'message'     => __( 'Campaign start date is in the past. It will start immediately when launched.', 'smart-cycle-discounts' ),
						'icon'        => 'warning',
						'explanation' => __( 'When you schedule a campaign for a date that has already passed, the system will start it immediately upon activation instead of waiting. This may not align with your planned marketing schedule or customer communications.', 'smart-cycle-discounts' ),
						'action'      => null,
						'step'        => 'schedule',
					);
				}
			} catch ( Exception $e ) {
				// Fallback: If datetime parsing fails, try simple date comparison
				$start_datetime_str = $start_date . ' ' . $start_time;
				$start_ts           = strtotime( $start_datetime_str );
				$now                = current_time( 'timestamp' );

				if ( $start_ts && $start_ts < $now ) {
					$recommendations[] = array(
						'category'    => 'timing',
						'priority'    => 'high',
						'impact'      => __( 'Avoid unexpected immediate launch', 'smart-cycle-discounts' ),
						'message'     => __( 'Campaign start date is in the past. It will start immediately when launched.', 'smart-cycle-discounts' ),
						'icon'        => 'warning',
						'explanation' => __( 'When you schedule a campaign for a date that has already passed, the system will start it immediately upon activation instead of waiting. This may not align with your planned marketing schedule or customer communications.', 'smart-cycle-discounts' ),
						'action'      => null,
						'step'        => 'schedule',
					);
				}
			}
		}

		// HIGH: Very short duration (<1 hour)
		if ( null !== $duration_days ) {
			$duration_hours = $duration_days * 24;

			if ( $duration_hours < 1 ) {
				$duration_minutes = round( $duration_hours * 60 );

				$recommendations[] = array(
					'category'    => 'timing',
					'priority'    => 'high',
					'impact'      => __( 'Ensure customers have time to respond', 'smart-cycle-discounts' ),
					'message'     => sprintf(
						__( 'Campaign duration is only %d minutes. This may not give customers enough time to see and respond to your offer.', 'smart-cycle-discounts' ),
						$duration_minutes
					),
					'icon'        => 'warning',
					'explanation' => sprintf(
						__( 'Marketing campaigns need time for customers to discover, consider, and act. A %d-minute window is too short for email notifications to be delivered, for customers to browse, and for conversions to occur. Consider at least 24-48 hours.', 'smart-cycle-discounts' ),
						$duration_minutes
					),
					'action'      => null,
					'step'        => 'schedule',
				);
			}
		}

		// MEDIUM: Very long duration (>365 days)
		if ( null !== $duration_days && $duration_days > 365 ) {
			$recommendations[] = array(
				'category'    => 'timing',
				'priority'    => 'medium',
				'impact'      => __( 'Better campaign control and analysis', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'Campaign runs for %d days. Consider breaking into shorter campaigns for better control and performance tracking.', 'smart-cycle-discounts' ),
					round( $duration_days )
				),
				'icon'        => 'calendar',
				'explanation' => sprintf(
					__( 'Year-long campaigns (%d days) lack urgency and make it difficult to measure effectiveness or adjust strategy. Breaking into quarterly or seasonal campaigns allows you to test, learn, and optimize while maintaining customer interest.', 'smart-cycle-discounts' ),
					round( $duration_days )
				),
				'action'      => null,
				'step'        => 'schedule',
			);
		}

		// HIGH: Schedule overlap with other campaigns
		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			try {
				$campaign_tz = new DateTimeZone( $campaign_tz_string );

				$start_datetime_str = $start_date;
				$end_datetime_str   = $end_date;

				if ( isset( $schedule['startTime'] ) && ! empty( $schedule['startTime'] ) ) {
					$start_datetime_str = $start_date . ' ' . $schedule['startTime'];
				}
				if ( isset( $schedule['endTime'] ) && ! empty( $schedule['endTime'] ) ) {
					$end_datetime_str = $end_date . ' ' . $schedule['endTime'];
				}

				$start_dt = new DateTime( $start_datetime_str, $campaign_tz );
				$end_dt   = new DateTime( $end_datetime_str, $campaign_tz );

				$start_ts = $start_dt->getTimestamp();
				$end_ts   = $end_dt->getTimestamp();

				$overlapping = $this->_check_schedule_overlap( $start_ts, $end_ts );

				if ( ! empty( $overlapping ) ) {
					$recommendations[] = $this->_build_overlap_recommendation( $overlapping );
				}
			} catch ( Exception $e ) {
				// Fallback to old behavior
				$start_ts = strtotime( $start_date );
				$end_ts   = strtotime( $end_date );

				if ( $start_ts && $end_ts ) {
					$overlapping = $this->_check_schedule_overlap( $start_ts, $end_ts );

					if ( ! empty( $overlapping ) ) {
						$recommendations[] = $this->_build_overlap_recommendation( $overlapping );
					}
				}
			}
		}

		return $recommendations;
	}

	/**
	 * Get product selection recommendations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_stats    Product statistics.
	 * @return   array                      Recommendations array.
	 */
	private function _get_product_selection_recommendations( $product_stats ) {
		$recommendations = array();
		$selection_type  = isset( $this->campaign_data['products']['product_selection_type'] ) ? $this->campaign_data['products']['product_selection_type'] : '';

		// Recommendation: Too many products
		if ( $product_stats['count'] > 50 ) {
			$recommendations[] = array(
				'category'    => 'products',
				'priority'    => 'medium',
				'impact'      => __( 'Improves promotional focus and tracking', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'With %d products selected, consider narrowing to your top 20-30 sellers for more focused promotion and easier performance tracking.', 'smart-cycle-discounts' ),
					$product_stats['count']
				),
				'icon'        => 'filter',
				'explanation' => sprintf(
					__( 'Campaigns with %d products dilute marketing focus and make performance tracking difficult. Customers also experience choice paralysis with too many options. Focusing on 20-30 top sellers creates clearer messaging and measurable results.', 'smart-cycle-discounts' ),
					$product_stats['count']
				),
				'action'      => null,
				'step'        => 'products',
			);
		}

		// Recommendation: Random products
		if ( 'random_products' === $selection_type ) {
			$recommendations[] = array(
				'category'    => 'products',
				'priority'    => 'low',
				'impact'      => __( 'Enables better tracking and optimization', 'smart-cycle-discounts' ),
				'message'     => __( 'Random selection makes it difficult to track performance - switch to specific products or categories for data-driven optimization.', 'smart-cycle-discounts' ),
				'icon'        => 'eye',
				'explanation' => __( 'Random product selection means you cannot track which products drove campaign success or measure ROI accurately. Specific product selection enables A/B testing, performance analysis, and data-driven campaign optimization over time.', 'smart-cycle-discounts' ),
				'action'      => array(
					'type' => 'change_selection_type',
					'data' => array(
						'selection_type' => 'specific_products',
					),
				),
				'step'        => 'products',
			);
		}

		// Recommendation: Product Category Diversity
		$unique_categories = $this->_get_unique_product_categories( $product_stats );
		if ( count( $unique_categories ) === 1 && $product_stats['count'] >= 5 ) {
			$category_name = reset( $unique_categories );

			$recommendations[] = array(
				'category'    => 'products',
				'priority'    => 'medium',
				'impact'      => __( 'Could increase average order value by 25-40%', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'All %1$d products are in "%2$s" category - add complementary products from related categories to encourage exploration and increase cart size.', 'smart-cycle-discounts' ),
					$product_stats['count'],
					$category_name
				),
				'icon'        => 'products',
				'explanation' => __( 'Single-category discounts limit discovery and cross-selling opportunities. Including complementary products (e.g., shoes + socks, laptops + accessories) increases average order value by 25-40% according to e-commerce studies.', 'smart-cycle-discounts' ),
				'action'      => null, // Manual product selection
				'step'        => 'products',
			);
		}

		// HIGH: Products already on sale
		$product_ids = $this->_get_selected_product_ids();
		if ( ! empty( $product_ids ) ) {
			$sale_products = array();

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->is_on_sale() ) {
					$sale_products[] = $product_id;
				}
			}

			if ( ! empty( $sale_products ) ) {
				$recommendations[] = array(
					'category'    => 'products',
					'priority'    => 'high',
					'impact'      => __( 'Prevent discount conflicts', 'smart-cycle-discounts' ),
					'message'     => sprintf(
						__( '%d product(s) are already on sale. Campaign discount may not apply correctly or may stack unexpectedly.', 'smart-cycle-discounts' ),
						count( $sale_products )
					),
					'icon'        => 'warning',
					'explanation' => sprintf(
						__( 'Products already on sale have sale prices set in WooCommerce. Your campaign discount may not override these prices, or may stack with them depending on your settings. This can cause confusion for customers and unexpected pricing. Review which products should be included.', 'smart-cycle-discounts' ),
						count( $sale_products )
					),
					'action'      => null,
					'step'        => 'products',
				);
			}
		}

		// MEDIUM: Low product coverage
		if ( ! empty( $this->coverage_data ) ) {
			$coverage_percentage = isset( $this->coverage_data['coverage_percentage'] ) ? $this->coverage_data['coverage_percentage'] : 0;

			if ( $coverage_percentage < 50 && $coverage_percentage > 0 ) {
				$recommendations[] = array(
					'category'    => 'products',
					'priority'    => 'medium',
					'impact'      => __( 'Increase effective product reach', 'smart-cycle-discounts' ),
					'message'     => sprintf(
						__( 'Only %d%% of selected products will receive discounts. Low coverage reduces campaign effectiveness and customer engagement.', 'smart-cycle-discounts' ),
						$coverage_percentage
					),
					'icon'        => 'warning',
					'explanation' => sprintf(
						__( 'With %d%% coverage, more than half of your products won\'t receive the discount. This can happen due to exclusions, stock issues, or conflicting campaigns. Low coverage reduces ROI and may confuse customers who expect discounts on more products. Review exclusions and conflicts to increase coverage.', 'smart-cycle-discounts' ),
						$coverage_percentage
					),
					'action'      => null,
					'step'        => 'products',
				);
			}
		}

		return $recommendations;
	}

	/**
	 * Get campaign management recommendations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Recommendations array.
	 */
	private function _get_campaign_management_recommendations() {
		$recommendations = array();

		// HIGH: Campaign conflicts with higher-priority campaigns
		if ( ! empty( $this->conflicts_data ) && ! empty( $this->conflicts_data['has_conflicts'] ) ) {
			$total_blocked = isset( $this->conflicts_data['total_products_blocked'] ) ? $this->conflicts_data['total_products_blocked'] : 0;

			$recommendations[] = array(
				'category'    => 'management',
				'priority'    => 'high',
				'impact'      => __( 'Prevent wasted effort and budget', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( '%d product(s) are blocked by higher-priority campaigns. These products will not receive your campaign discount, reducing effectiveness.', 'smart-cycle-discounts' ),
					$total_blocked
				),
				'icon'        => 'warning',
				'explanation' => sprintf(
					__( 'WooCommerce gives priority to campaigns with higher priority numbers. When %d products are blocked by other campaigns, you\'re spending effort on a campaign that won\'t reach its full potential. Either increase this campaign\'s priority or adjust product selection to avoid conflicts.', 'smart-cycle-discounts' ),
					$total_blocked
				),
				'action'      => null,
				'step'        => 'basic',
			);
		}

		// MEDIUM: Generic campaign name
		$name = isset( $this->campaign_data['basic']['name'] ) ? trim( $this->campaign_data['basic']['name'] ) : '';
		if ( ! empty( $name ) ) {
			$generic_names = array( 'test', 'new campaign', 'campaign', 'discount', 'sale', 'temp', 'untitled' );
			$name_lower    = strtolower( $name );
			$is_generic    = false;

			foreach ( $generic_names as $generic ) {
				if ( $name_lower === $generic || strpos( $name_lower, $generic ) === 0 ) {
					$is_generic = true;
					break;
				}
			}

			// Also check for very short names
			if ( ! $is_generic && strlen( $name ) < 10 ) {
				$is_generic = true;
			}

			if ( $is_generic ) {
				$recommendations[] = array(
					'category'    => 'management',
					'priority'    => 'medium',
					'impact'      => __( 'Easier to track and reference', 'smart-cycle-discounts' ),
					'message'     => sprintf(
						__( 'Campaign name "%s" is too generic. Use a descriptive name with timeframe or goal (e.g., "Spring Clearance 2025" vs "Sale") for easier tracking.', 'smart-cycle-discounts' ),
						$name
					),
					'icon'        => 'edit',
					'explanation' => sprintf(
						__( 'Generic names like "%s" make it hard to identify campaigns later when analyzing performance or planning future promotions. Descriptive names with timeframes (month/season/year) and goals (clearance/launch/holiday) create clear campaign history.', 'smart-cycle-discounts' ),
						$name
					),
					'action'      => null,
					'step'        => 'basic',
				);
			}
		}

		// Recommendation: Description
		if ( empty( $this->campaign_data['basic']['description'] ) ) {
			$recommendations[] = array(
				'category'    => 'management',
				'priority'    => 'low',
				'impact'      => __( 'Better campaign documentation', 'smart-cycle-discounts' ),
				'message'     => __( 'Add description documenting campaign goals, target audience, and expected outcomes - helps with future campaign planning and analysis.', 'smart-cycle-discounts' ),
				'icon'        => 'file-text',
				'explanation' => __( 'Campaign descriptions create institutional knowledge. When reviewing performance or planning new campaigns, documented goals (e.g., "Clear winter inventory, target repeat customers, expect 30% lift") provide context that helps you learn from past campaigns and improve future strategies.', 'smart-cycle-discounts' ),
				'action'      => null,
				'step'        => 'basic',
			);
		}

		return $recommendations;
	}

	/**
	 * Get discount rules warnings.
	 *
	 * Identifies potential issues with Configure Discount Rules field combinations
	 * that may cause unexpected behavior or confusion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Warnings array.
	 */
	private function _get_discount_rules_warnings() {
		$warnings  = array();
		$discounts = isset( $this->campaign_data['discounts'] ) ? $this->campaign_data['discounts'] : array();

		// Extract discount rules fields
		$usage_limit_per_customer = isset( $discounts['usage_limit_per_customer'] ) ? absint( $discounts['usage_limit_per_customer'] ) : 0;
		$lifetime_usage_cap       = isset( $discounts['lifetime_usage_cap'] ) ? absint( $discounts['lifetime_usage_cap'] ) : 0;
		$apply_to_sale_items      = isset( $discounts['apply_to_sale_items'] ) ? (bool) $discounts['apply_to_sale_items'] : false;
		$stack_with_others        = isset( $discounts['stack_with_others'] ) ? (bool) $discounts['stack_with_others'] : false;

		// MEDIUM: Customer limit exceeds lifetime cap
		if ( $usage_limit_per_customer > 0 && $lifetime_usage_cap > 0 && $usage_limit_per_customer > $lifetime_usage_cap ) {
			$warnings[] = array(
				'category'    => 'discount',
				'priority'    => 'medium',
				'impact'      => __( 'Set realistic customer expectations', 'smart-cycle-discounts' ),
				'message'     => sprintf(
					__( 'Customer limit (%1$d uses) exceeds lifetime campaign cap (%2$d uses). The campaign will end before any customer reaches their personal limit.', 'smart-cycle-discounts' ),
					$usage_limit_per_customer,
					$lifetime_usage_cap
				),
				'icon'        => 'info',
				'explanation' => sprintf(
					__( 'Your lifetime cap limits total redemptions across all customers to %1$d. With individual customers allowed %2$d uses each, the campaign will end when total usage hits %3$d - meaning customers will never reach their %4$d-use personal limit. This configuration works if you want to be generous with per-customer limits while controlling total campaign cost, but consider lowering the customer limit to match realistic expectations (e.g., if you expect 10 customers, set customer limit to %5$d).', 'smart-cycle-discounts' ),
					$lifetime_usage_cap,
					$usage_limit_per_customer,
					$lifetime_usage_cap,
					$usage_limit_per_customer,
					absint( $lifetime_usage_cap / 10 )
				),
				'action'      => null,
				'step'        => 'discounts',
			);
		}

		// MEDIUM: Sale items enabled (margin protection warning)
		if ( $apply_to_sale_items ) {
			$warnings[] = array(
				'category'    => 'discount',
				'priority'    => 'medium',
				'impact'      => __( 'Protect profit margins', 'smart-cycle-discounts' ),
				'message'     => __( 'Discount will stack with existing sale prices. Verify this won\'t reduce margins below your acceptable threshold.', 'smart-cycle-discounts' ),
				'icon'        => 'warning',
				'explanation' => __( 'Products already on sale have reduced prices. Applying this campaign discount on top creates double-discounting that can quickly erode margins or even sell below cost. For example: a $100 product on 20% sale ($80) with an additional 25% campaign discount becomes $60 - a total 40% off. Always calculate combined discount impact on your lowest-margin products before launch. Consider excluding sale items unless you\'ve verified margins remain profitable.', 'smart-cycle-discounts' ),
				'action'      => null,
				'step'        => 'discounts',
			);
		}

		// LOW: Campaign stacking enabled (informational)
		if ( $stack_with_others ) {
			$warnings[] = array(
				'category'    => 'discount',
				'priority'    => 'low',
				'impact'      => __( 'Awareness of discount combinations', 'smart-cycle-discounts' ),
				'message'     => __( 'Campaign stacking is enabled. This discount can combine with other active campaigns, which may create unexpected total discount amounts.', 'smart-cycle-discounts' ),
				'icon'        => 'admin-links',
				'explanation' => __( 'When multiple campaigns target the same products, WooCommerce applies discounts based on priority settings. Stacking can be powerful for targeted promotions (e.g., "20% category sale + 10% loyalty discount = 30% total") but requires careful planning. Monitor combined discount amounts to ensure they don\'t exceed your margin thresholds. If you have other active campaigns, review the Conflicts section to see how they interact.', 'smart-cycle-discounts' ),
				'action'      => null,
				'step'        => 'discounts',
			);
		}

		return $warnings;
	}

	/**
	 * Format price as plain text (no HTML) for use in recommendations.
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

	/**
	 * Get conflict preview details.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Conflict preview data.
	 */
	private function _get_conflict_preview() {
		$preview = array(
			'has_conflicts'   => false,
			'total_conflicts' => 0,
			'conflicts'       => array(),
		);

		if ( empty( $this->conflicts_data ) || empty( $this->conflicts_data['has_conflicts'] ) ) {
			return $preview;
		}

		$preview['has_conflicts']   = true;
		$preview['total_conflicts'] = isset( $this->conflicts_data['total_products_blocked'] ) ? $this->conflicts_data['total_products_blocked'] : 0;

		if ( ! empty( $this->conflicts_data['conflicts'] ) ) {
			foreach ( $this->conflicts_data['conflicts'] as $conflict ) {
				$preview['conflicts'][] = array(
					'campaign_name'     => isset( $conflict['name'] ) ? $conflict['name'] : __( 'Unknown Campaign', 'smart-cycle-discounts' ),
					'priority'          => isset( $conflict['priority'] ) ? $conflict['priority'] : 0,
					'affected_products' => isset( $conflict['product_count'] ) ? $conflict['product_count'] : 0,
				);
			}
		}

		return $preview;
	}

	/**
	 * Get enhanced exclusion details with reasons.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Enhanced exclusion data.
	 */
	private function _get_enhanced_exclusions() {
		$exclusions = array();

		if ( empty( $this->coverage_data ) || empty( $this->coverage_data['exclusions'] ) ) {
			return $exclusions;
		}

		foreach ( $this->coverage_data['exclusions'] as $exclusion ) {
			$exclusions[] = array(
				'label'  => isset( $exclusion['label'] ) ? $exclusion['label'] : __( 'Unknown Reason', 'smart-cycle-discounts' ),
				'count'  => isset( $exclusion['count'] ) ? $exclusion['count'] : 0,
				'reason' => isset( $exclusion['reason'] ) ? $exclusion['reason'] : 'general',
			);
		}

		return $exclusions;
	}

	/**
	 * Assess stock depletion risk for campaign products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Stock risk data.
	 */
	private function _assess_stock_risk() {
		$risk_data = array(
			'has_risk'          => false,
			'high_risk_count'   => 0,
			'medium_risk_count' => 0,
			'products'          => array(),
		);

		$product_ids = $this->_get_selected_product_ids();

		if ( empty( $product_ids ) || ! class_exists( 'WooCommerce' ) ) {
			return $risk_data;
		}

		// Get discount type to determine estimated demand
		$discount_type  = isset( $this->campaign_data['discounts']['discount_type'] ) ? $this->campaign_data['discounts']['discount_type'] : 'percentage';
		$discount_value = 0;

		if ( 'percentage' === $discount_type ) {
			$discount_value = isset( $this->campaign_data['discounts']['discount_value_percentage'] ) ? floatval( $this->campaign_data['discounts']['discount_value_percentage'] ) : 0;
		}

		// Estimate demand multiplier based on discount depth
		// Higher discounts typically generate more demand
		$demand_multiplier = 1.0;
		if ( $discount_value >= 50 ) {
			$demand_multiplier = 3.0; // 3x normal demand
		} elseif ( $discount_value >= 30 ) {
			$demand_multiplier = 2.0; // 2x normal demand
		} elseif ( $discount_value >= 15 ) {
			$demand_multiplier = 1.5; // 1.5x normal demand
		}

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$stock = $product->get_stock_quantity();

			if ( null === $stock ) {
				continue;
			}

			// Estimate expected orders (conservative baseline: 5 orders, adjusted by demand multiplier)
			$baseline_expected = 5;
			$estimated_demand  = ceil( $baseline_expected * $demand_multiplier );

			// Determine risk level
			$risk_level = 'none';

			if ( $stock < $estimated_demand * 0.5 ) {
				// Stock is less than 50% of estimated demand - HIGH RISK
				$risk_level = 'high';
				++$risk_data['high_risk_count'];
				$risk_data['has_risk'] = true;
			} elseif ( $stock < $estimated_demand ) {
				// Stock is less than estimated demand - MEDIUM RISK
				$risk_level = 'medium';
				++$risk_data['medium_risk_count'];
				$risk_data['has_risk'] = true;
			}

			if ( 'none' !== $risk_level ) {
				$risk_data['products'][] = array(
					'id'               => $product_id,
					'name'             => $product->get_name(),
					'stock'            => $stock,
					'estimated_demand' => $estimated_demand,
					'risk_level'       => $risk_level,
				);
			}
		}

		// Sort by risk level (high first) and limit to top 10
		if ( ! empty( $risk_data['products'] ) ) {
			usort(
				$risk_data['products'],
				function ( $a, $b ) {
					$risk_order = array(
						'high'   => 1,
						'medium' => 2,
						'low'    => 3,
					);
					$a_val      = isset( $risk_order[ $a['risk_level'] ] ) ? $risk_order[ $a['risk_level'] ] : 999;
					$b_val      = isset( $risk_order[ $b['risk_level'] ] ) ? $risk_order[ $b['risk_level'] ] : 999;
					return $a_val - $b_val;
				}
			);

			$risk_data['products'] = array_slice( $risk_data['products'], 0, 10 );
		}

		return $risk_data;
	}

	/**
	 * Get selected product IDs for analysis.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of product IDs.
	 */
	private function _get_selected_product_ids() {
		if ( empty( $this->coverage_data ) || empty( $this->coverage_data['product_ids'] ) ) {
			return array();
		}

		return array_slice( $this->coverage_data['product_ids'], 0, 100 ); // Limit to 100 for performance
	}

	/**
	 * Get unique product categories from product stats.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_stats    Product statistics.
	 * @return   array                      Array of unique category names.
	 */
	private function _get_unique_product_categories( $product_stats ) {
		$product_ids = $this->_get_selected_product_ids();
		$categories  = array();

		if ( empty( $product_ids ) || ! class_exists( 'WooCommerce' ) ) {
			return $categories;
		}

		foreach ( $product_ids as $product_id ) {
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[ $term->term_id ] = $term->name;
				}
			}
		}

		return $categories;
	}

	/**
	 * Check for schedule overlap with active campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $start_timestamp    Start timestamp.
	 * @param    int $end_timestamp      End timestamp.
	 * @return   array                      Array of overlapping campaigns.
	 */
	private function _check_schedule_overlap( $start_timestamp, $end_timestamp ) {
		return SCD_Campaign_Schedule_Validator::check_schedule_overlap(
			$start_timestamp,
			$end_timestamp,
			false // Only basic campaign info needed (id, name).
		);
	}

	/**
	 * Build schedule overlap recommendation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $overlapping    Array of overlapping campaigns.
	 * @return   array                    Recommendation array.
	 */
	private function _build_overlap_recommendation( $overlapping ) {
		return array(
			'category'    => 'timing',
			'priority'    => 'high',
			'impact'      => __( 'Prevent discount conflicts', 'smart-cycle-discounts' ),
			'message'     => sprintf(
				__( 'This campaign overlaps with %d other active campaign(s). Multiple simultaneous discounts may cause conflicts or confusion.', 'smart-cycle-discounts' ),
				count( $overlapping )
			),
			'icon'        => 'warning',
			'explanation' => sprintf(
				__( 'Running %d overlapping campaigns can lead to unexpected discount stacking, customer confusion about which deal applies, and difficulty tracking campaign performance. Consider staggering campaigns or using priority settings.', 'smart-cycle-discounts' ),
				count( $overlapping ) + 1
			),
			'action'      => null,
			'step'        => 'schedule',
		);
	}
}
