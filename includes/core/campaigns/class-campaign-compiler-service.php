<?php
/**
 * Campaign Compiler Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-compiler-service.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Wizard Campaign Compiler Class
 *
 * Handles compilation and creation of campaigns from wizard data.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Compiler_Service {

	/**
	 * Campaign repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $campaign_repository    Campaign repository.
	 */
	private ?object $campaign_repository;

	/**
	 * Initialize the compiler.
	 *
	 * @since    1.0.0
	 * @param    object|null $campaign_repository    Campaign repository.
	 */
	public function __construct( ?object $campaign_repository = null ) {
		$this->campaign_repository = $campaign_repository;

		// If no repository provided, try to create one
		if ( ! $this->campaign_repository && class_exists( 'SCD_Campaign_Repository' ) ) {
			if ( class_exists( 'SCD_Database_Manager' ) && class_exists( 'SCD_Cache_Manager' ) ) {
				$this->campaign_repository = new SCD_Campaign_Repository(
					new SCD_Database_Manager(),
					new SCD_Cache_Manager()
				);
			}
		}
	}

	/**
	 * Compile campaign data from current wizard session.
	 *
	 * @since    1.0.0
	 * @return   array|null    Compiled campaign data or null if no session.
	 */
	public function compile_from_session(): ?array {
		// Load the wizard state service if not already loaded
		if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
		}

		$state_service = new SCD_Wizard_State_Service();

		// Get session data
		$session_data = $state_service->get_all_data();

		if ( empty( $session_data ) ) {
			return null;
		}

		// Compile the session data
		return $this->compile( $session_data );
	}

	/**
	 * Compile campaign data from wizard steps.
	 *
	 * @since    1.0.0
	 * @param    array    $steps_data            All steps data.
	 * @param    int|null $exclude_campaign_id   Campaign ID to exclude from duplicate checks (for editing).
	 * @return   array                              Compiled campaign data.
	 */
	public function compile( array $steps_data, ?int $exclude_campaign_id = null ): array {
		$compiled = array();

		// Add campaign ID for edit mode detection
		if ( $exclude_campaign_id ) {
			$compiled['id'] = $exclude_campaign_id;
		}

		// Merge all step data
		foreach ( $steps_data as $step => $step_data ) {
			if ( $step === '_meta' ) {
				continue; // Skip meta step
			}

			// Include review step data for launch_option
			$compiled = array_merge( $compiled, $step_data );

		}

		// Add metadata
		$compiled['created_by'] = get_current_user_id();
		$compiled['created_at'] = current_time( 'mysql' );

		// Add UUID if not present
		if ( empty( $compiled['uuid'] ) ) {
			$compiled['uuid'] = wp_generate_uuid4();
		}

		// Apply field transformations using Wizard Field Mapper
		if ( class_exists( 'SCD_Wizard_Field_Mapper' ) ) {
			$compiled = SCD_Wizard_Field_Mapper::transform_to_entity_fields( $compiled );
		}

		// Apply other transformations (schedule, settings, etc.)
		$compiled = $this->transform_campaign_data( $compiled );

		return apply_filters( 'scd_wizard_compile_campaign_data', $compiled, $steps_data );
	}

	/**
	 * Organize complex fields for proper JSON storage.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Campaign data.
	 * @return   array             Data with organized complex fields.
	 */
	private function organize_complex_fields( array $data ): array {
		// Initialize JSON storage containers
		if ( ! isset( $data['metadata'] ) ) {
			$data['metadata'] = array();
		}

		// Product conditions (complex field)
		if ( isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			$data['metadata']['product_conditions'] = $data['conditions'];
			$data['metadata']['conditions_logic']   = $data['conditions_logic'] ?? 'all';
			// Remove from main data as it's now in metadata
			unset( $data['conditions'] );
			unset( $data['conditions_logic'] );
		}

		// Smart criteria (if it's an array/complex)
		if ( ! empty( $data['smart_criteria'] ) && is_array( $data['smart_criteria'] ) ) {
			$data['metadata']['smart_criteria'] = $data['smart_criteria'];
		}

		// Initialize discount_rules once for all discount configurations
		$data['discount_rules'] = $data['discount_rules'] ?? array();

		// Tiered discounts (complex field) - now uses combined format
		if ( ! empty( $data['tiers'] ) ) {
			$data['discount_rules']['tiers']     = $data['tiers'];
			$data['discount_rules']['tier_mode'] = $data['tier_mode'] ?? 'percentage';
			$data['discount_rules']['tier_type'] = $data['tier_type'] ?? 'quantity';
		}

		// Spend thresholds (complex field) - now uses combined format
		if ( ! empty( $data['thresholds'] ) ) {
			$data['discount_rules']['thresholds']     = $data['thresholds'];
			$data['discount_rules']['threshold_mode'] = $data['threshold_mode'] ?? 'percentage';
		}

		return $data;
	}

	/**
	 * Format campaign data for wizard editing.
	 *
	 * Transforms saved campaign data (with discount_rules) back to the format
	 * expected by the JavaScript wizard (now uses combined format for tiers/thresholds).
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign|array $campaign    Campaign object or array.
	 * @return   array                             Formatted data for wizard.
	 */
	public function format_for_wizard( $campaign ): array {
		// Handle both Campaign objects and arrays
		if ( is_object( $campaign ) && method_exists( $campaign, 'to_array' ) ) {
			$data = $campaign->to_array();
		} elseif ( is_array( $campaign ) ) {
			$data = $campaign;
		} else {
			return array();
		}

		// Transform discount_rules back to JavaScript format
		if ( ! empty( $data['discount_rules'] ) && is_array( $data['discount_rules'] ) ) {
			$discount_rules = $data['discount_rules'];

			// Check if this is tiered discount configuration (now uses combined format)
			if ( isset( $discount_rules['tiers'] ) && is_array( $discount_rules['tiers'] ) ) {
				$data['tiers']     = $discount_rules['tiers'];
				$data['tier_mode'] = $discount_rules['tier_mode'] ?? 'percentage';
				$data['tier_type'] = $discount_rules['tier_type'] ?? 'quantity';
			}

			// Check if this is spend threshold discount configuration (now uses combined format)
			if ( isset( $discount_rules['thresholds'] ) && is_array( $discount_rules['thresholds'] ) ) {
				$data['thresholds']     = $discount_rules['thresholds'];
				$data['threshold_mode'] = $discount_rules['threshold_mode'] ?? 'percentage';
			}
		}

		return $data;
	}

	/**
	 * Transform campaign data for storage.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Raw campaign data.
	 * @return   array             Transformed data.
	 */
	private function transform_campaign_data( array $data ): array {
		// Build settings array that campaign validation expects
		$settings = array();

		// Add discount settings
		if ( isset( $data['discount_type'] ) ) {
			$settings['discount_type'] = $data['discount_type'];

			// Map discount value based on type and ensure it's also in main data
			$discount_value = 0;
			switch ( $data['discount_type'] ) {
				case 'percentage':
					$discount_value = $data['discount_value_percentage'] ?? $data['discount_value'] ?? 0;
					break;
				case 'fixed':
					$discount_value = $data['discount_value_fixed'] ?? $data['discount_value'] ?? 0;
					break;
				default:
					$discount_value = $data['discount_value'] ?? 0;
					break;
			}

			// CRITICAL: Cast to float - Campaign class requires float type
			$discount_value = (float) $discount_value;

			$settings['discount_value'] = $discount_value;
			// Ensure discount_value is also in main data array
			$data['discount_value'] = $discount_value;
		}

		// Add product/category/tag settings
		$settings['products']   = array();
		$settings['categories'] = array();
		$settings['tags']       = array();

		// Store category filter in the campaign's category_ids field
		if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
			// Remove 'all' value if present - empty array means all categories
			$category_ids = array_filter(
				$data['category_ids'],
				function ( $id ) {
					return $id !== 'all';
				}
			);

			if ( ! empty( $category_ids ) ) {
				// Store in main data array for campaign model
				$data['category_ids'] = array_map( 'intval', $category_ids );
				// Also store in metadata for additional info if needed
				$data['metadata']['category_ids'] = $data['category_ids'];
				// Store in settings for validation
				$settings['categories'] = $data['category_ids'];
			} else {
				// If only 'all' was present, set to empty array (means all categories)
				$data['category_ids']   = array();
				$settings['categories'] = array();
			}
		}

		// Transform product selection - maintain consistency with field definitions
		if ( isset( $data['product_selection_type'] ) ) {
			$selection_type = $data['product_selection_type'];

			switch ( $selection_type ) {
				case 'all_products':
					$data['applies_to_all_products'] = true;
					$settings['applies_to_all']      = true;
					$settings['products']            = array( 'all' );
					break;
				case 'specific_products':
					$data['applies_to_all_products'] = false;
					$data['product_ids']             = $data['product_ids'] ?? array();
					$settings['products']            = $data['product_ids'];
					break;
				case 'random_products':
					$data['applies_to_all_products'] = false;
					$settings['applies_to_all']      = false;
					// Don't set products to 'all' - they will be compiled when campaign activates
					$settings['products'] = array();
					// Store random_count in both settings and metadata
					$random_count             = $data['random_count'] ?? 5;
					$settings['random_count'] = $random_count;
					if ( ! isset( $data['metadata'] ) ) {
						$data['metadata'] = array();
					}
					$data['metadata']['random_count'] = $random_count;
					break;
				case 'smart_selection':
					$data['applies_to_all_products'] = false;
					$settings['smart_criteria']      = $data['smart_criteria'] ?? array();
					$settings['products']            = array( 'smart' );
					break;
			}
		}

		// Add discount rules/restrictions to settings
		if ( isset( $data['apply_to_sale_items'] ) ) {
			$settings['apply_to_sale_items'] = (bool) $data['apply_to_sale_items'];
		}

		if ( isset( $data['allow_coupons'] ) ) {
			$settings['allow_coupons'] = (bool) $data['allow_coupons'];
		}

		if ( isset( $data['stack_with_others'] ) ) {
			$settings['stack_with_others'] = (bool) $data['stack_with_others'];
		}

		// Add the settings array to campaign data
		$data['settings'] = $settings;

		// Organize complex fields for JSON storage
		$data = $this->organize_complex_fields( $data );

		// Transform discount data for configuration
		if ( isset( $data['discount_type'] ) ) {
			$discount_config = $this->build_discount_configuration( $data );

			// Store configuration in discount_rules field for Campaign model
			$data['discount_rules'] = $discount_config;

			// Also keep as discount_configuration for backward compatibility
			$data['discount_configuration'] = $discount_config;
		}

		// Transform schedule data
		if ( isset( $data['start_date'] ) || isset( $data['start_type'] ) ) {
			$campaign_timezone = $data['timezone'] ?? wp_timezone_string();
			$start_type        = $data['start_type'] ?? 'scheduled';

			// Handle immediate vs scheduled campaigns differently
			if ( 'immediate' === $start_type ) {
				// IMMEDIATE: Use current server time (WordPress timezone)
				// Single source of truth - server calculates both start and end times
				try {
					// Get current datetime in campaign timezone
					$now_dt       = new DateTime( 'now', new DateTimeZone( $campaign_timezone ) );
					$current_date = $now_dt->format( 'Y-m-d' );
					$current_time = $now_dt->format( 'H:i' );

					// Use DateTimeBuilder for type-safe UTC conversion
					$start_builder = SCD_DateTime_Builder::from_user_input(
						$current_date,
						$current_time,
						$campaign_timezone
					);

					if ( ! $start_builder->validate() ) {
						throw new InvalidArgumentException(
							__( 'Invalid immediate start date or time: ', 'smart-cycle-discounts' ) .
							implode( ', ', $start_builder->get_errors() )
						);
					}

					// Get UTC datetime for database storage
					$data['starts_at'] = $start_builder->to_mysql();

					// Set start_date and start_time separately (wizard expects separate fields)
					$data['start_date'] = $current_date; // Y-m-d only
					$data['start_time'] = $current_time; // H:i only
				} catch ( Exception $e ) {
					throw $e;
				}

				// Calculate end time - PRIORITY: User's explicit end_date takes precedence
				// FIX: User-selected end_date should be respected, not overridden by stale duration_seconds
				if ( isset( $data['end_date'] ) && isset( $data['end_time'] ) && ! empty( $data['end_date'] ) ) {
					// Use user's explicit end date/time selection (PRIMARY)
					$end_date = $data['end_date'];
					$end_time = $data['end_time'];

					// Use DateTimeBuilder for type-safe combination
					$end_builder = SCD_DateTime_Builder::from_user_input(
						$end_date,
						$end_time,
						$campaign_timezone
					);

					if ( ! $end_builder->validate() ) {
						throw new InvalidArgumentException(
							__( 'Invalid end date or time: ', 'smart-cycle-discounts' ) .
							implode( ', ', $end_builder->get_errors() )
						);
					}

					$data['ends_at'] = $end_builder->to_mysql();
				} elseif ( isset( $data['duration_seconds'] ) && 0 < $data['duration_seconds'] ) {
					// Fallback: Duration-based calculation (for backward compatibility with presets)
					$duration_seconds = absint( $data['duration_seconds'] );

					// Calculate end datetime by adding duration to start
					$end_dt = clone $now_dt;
					$end_dt->modify( '+' . $duration_seconds . ' seconds' );

					// Use DateTimeBuilder for consistent UTC conversion
					$end_builder = SCD_DateTime_Builder::from_user_input(
						$end_dt->format( 'Y-m-d' ),
						$end_dt->format( 'H:i' ),
						$campaign_timezone
					);

					if ( ! $end_builder->validate() ) {
						throw new InvalidArgumentException(
							__( 'Invalid duration-based end date or time: ', 'smart-cycle-discounts' ) .
							implode( ', ', $end_builder->get_errors() )
						);
					}

					$data['ends_at'] = $end_builder->to_mysql();
				}
			} else {
				// SCHEDULED: Use DateTime Builder for type-safe date/time combination
				if ( ! empty( $data['start_date'] ) ) {
					$start_date = $data['start_date'];
					$start_time = $data['start_time'] ?? '00:00';

					try {
						// Use DateTimeBuilder for validation and combination
						$builder = SCD_DateTime_Builder::from_user_input(
							$start_date,
							$start_time,
							$campaign_timezone
						);

						if ( ! $builder->validate() ) {
							throw new InvalidArgumentException(
								__( 'Invalid start date or time: ', 'smart-cycle-discounts' ) .
								implode( ', ', $builder->get_errors() )
							);
						}

						// Get UTC datetime for database storage
						$data['starts_at'] = $builder->to_mysql();
					} catch ( Exception $e ) {
						throw $e;
					}
				}

				// Map end_date to ends_at (convert to UTC) - for scheduled campaigns
				if ( ! empty( $data['end_date'] ) ) {
					$end_date = $data['end_date'];
					$end_time = $data['end_time'] ?? '23:59';

					try {
						// Use DateTimeBuilder for validation and combination
						$builder = SCD_DateTime_Builder::from_user_input(
							$end_date,
							$end_time,
							$campaign_timezone
						);

						if ( ! $builder->validate() ) {
							throw new InvalidArgumentException(
								__( 'Invalid end date or time: ', 'smart-cycle-discounts' ) .
								implode( ', ', $builder->get_errors() )
							);
						}

						// Get UTC datetime for database storage
						$data['ends_at'] = $builder->to_mysql();
					} catch ( Exception $e ) {
						throw $e;
					}
				}
			}

			// Build schedule configuration
			$data['schedule_configuration'] = $this->build_schedule_configuration( $data );
		}

		// Map campaign status based on launch option and start time
		// CRITICAL FIX: Respect user intent - launch_option is the PRIMARY decider
		$launch_option = $data['launch_option'] ?? null;
		$start_type    = $data['start_type'] ?? 'immediate';

		// Check if campaign start time is in the future
		$is_future_campaign = false;
		if ( ! empty( $data['starts_at'] ) ) {
			// Use DateTime for comparison (both in UTC)
			$start_dt           = new DateTime( $data['starts_at'], new DateTimeZone( 'UTC' ) );
			$now_dt             = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$is_future_campaign = ( $start_dt > $now_dt );
		}

		// Set status based on user intent and scheduling constraints
		// PRIORITY ORDER:
		// 1. User explicitly chose 'draft' → ALWAYS draft (allows reviewing future campaigns)
		// 2. User chose 'active' + future start → 'scheduled' (physical constraint)
		// 3. User chose 'active' + immediate start → 'active'
		// 4. No choice → preserve existing status or default to 'draft'

		if ( 'draft' === $launch_option ) {
			// PRIORITY 1: User wants draft - ALWAYS respect this choice
			// This allows users to save future campaigns for review without scheduling
			$data['status'] = 'draft';

		} elseif ( 'active' === $launch_option ) {
			// PRIORITY 2: User wants active, but physical constraints apply
			if ( $is_future_campaign ) {
				// Future campaigns cannot be immediately active - must be scheduled
				$data['status'] = 'scheduled';
			} else {
				// Immediate start - activate now
				$data['status'] = 'active';
			}
		} else {
			// PRIORITY 3: No explicit choice - preserve existing or default
			// When editing, preserve current status unless user made a choice
			// When creating, default to 'draft' for safety
			$data['status'] = $data['status'] ?? 'draft';
		}

		// Ensure campaign name is unique (including soft-deleted campaigns)
		if ( isset( $data['name'] ) && $this->campaign_repository ) {
			// When editing, exclude current campaign from uniqueness check
			$exclude_campaign_id = isset( $data['id'] ) ? (int) $data['id'] : null;
			$data['name']        = $this->get_unique_campaign_name( $data['name'], $exclude_campaign_id );
		}

		// Generate slug - but strip the (N) suffix from name first to get base slug
		if ( ! isset( $data['slug'] ) && isset( $data['name'] ) ) {
			// Remove (N) suffix from name before generating slug
			// This ensures "111 (2)" becomes base slug "111" not "111-2"
			$base_name    = preg_replace( '/\s*\(\d+\)$/', '', $data['name'] );
			$data['slug'] = sanitize_title( $base_name );
		}

		// Ensure slug is unique (handle soft-deleted campaigns)
		if ( isset( $data['slug'] ) && $this->campaign_repository ) {
			if ( method_exists( $this->campaign_repository, 'get_unique_slug' ) ) {
				$data['slug'] = $this->campaign_repository->get_unique_slug( $data['slug'] );
			}
		}

		return $data;
	}

	/**
	 * Build discount configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Campaign data.
	 * @return   array             Discount configuration.
	 */
	private function build_discount_configuration( array $data ): array {
		$config = array(
			'type'  => $data['discount_type'],
			'value' => (float) ( $data['discount_value'] ?? 0 ),
		);

		switch ( $data['discount_type'] ) {
			case 'percentage':
				$config['percentage'] = (float) ( $data['discount_value_percentage'] ?? $data['discount_value'] ?? 0 );
				break;
			case 'fixed':
				$config['amount'] = (float) ( $data['discount_value_fixed'] ?? $data['discount_value'] ?? 0 );
				break;
			case 'bogo':
				$config['buy_quantity']        = $data['bogo_buy_quantity'] ?? 1;
				$config['get_quantity']        = $data['bogo_get_quantity'] ?? 1;
				$config['discount_percentage'] = $data['bogo_discount_percentage'] ?? 100;
				break;
			case 'bundle':
				$config['bundle_size']  = $data['bundle_size'] ?? 2;
				$config['bundle_price'] = $data['bundle_price'] ?? 0;
				break;
			case 'tiered':
				// Handle tier data from JavaScript (now uses combined format)
				if ( ! empty( $data['tiers'] ) && is_array( $data['tiers'] ) ) {
					$config['tiers'] = $data['tiers'];
				}

				// Store tier mode and type
				if ( ! empty( $data['tier_mode'] ) ) {
					$config['tier_mode'] = $data['tier_mode'];
				}
				break;
			case 'spend_threshold':
				// Handle threshold data from JavaScript which sends percentage_spend_thresholds and fixed_spend_thresholds
				$config['thresholds'] = array();

				// Check for percentage thresholds
				if ( ! empty( $data['percentage_spend_thresholds'] ) && is_array( $data['percentage_spend_thresholds'] ) ) {
					$config['thresholds']     = $data['percentage_spend_thresholds'];
					$config['threshold_mode'] = 'percentage';
				}

				// Check for fixed thresholds (these would override percentage if both present)
				if ( ! empty( $data['fixed_spend_thresholds'] ) && is_array( $data['fixed_spend_thresholds'] ) ) {
					$config['thresholds']     = $data['fixed_spend_thresholds'];
					$config['threshold_mode'] = 'fixed';
				}

				// Also store threshold_mode from data if present
				if ( ! empty( $data['threshold_mode'] ) ) {
					$config['threshold_mode'] = $data['threshold_mode'];
				}
				break;
		}

		// Badge configuration
		if ( ! empty( $data['badge_enabled'] ) ) {
			$config['badge'] = array(
				'enabled'  => true,
				'text'     => $data['badge_text'] ?? '',
				'position' => $data['badge_position'] ?? 'top-right',
				'style'    => $data['badge_style'] ?? 'default',
			);
		}

		return $config;
	}

	/**
	 * Build schedule configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Campaign data.
	 * @return   array             Schedule configuration.
	 */
	private function build_schedule_configuration( array $data ): array {
		// start_date and end_date are already combined with time by transform_campaign_data
		$config = array(
			'start_date' => $data['start_date'],
			'end_date'   => $data['end_date'] ?? null,
			'timezone'   => $data['timezone'] ?? wp_timezone_string(),
		);

		// Time restrictions (for daily time windows, different from campaign start/end times)
		if ( ! empty( $data['daily_start_time'] ) || ! empty( $data['daily_end_time'] ) ) {
			$config['time_restrictions'] = array(
				'start_time' => $data['daily_start_time'] ?? '00:00',
				'end_time'   => $data['daily_end_time'] ?? '23:59',
			);
		}

		// Rotation settings
		if ( ! empty( $data['rotation_enabled'] ) ) {
			$config['rotation'] = array(
				'enabled'        => true,
				'interval'       => $data['rotation_interval'] ?? 24,
				'max_concurrent' => $data['max_concurrent_products'] ?? 1,
			);
		}

		// Recurring settings
		if ( ! empty( $data['recurring'] ) ) {
			$config['recurring'] = array(
				'enabled' => true,
				'pattern' => $data['recurring_pattern'] ?? 'daily',
				'days'    => $data['recurring_days'] ?? array(),
			);
		}

		return $config;
	}

	/**
	 * Create campaign from compiled data.
	 *
	 * @since    1.0.0
	 * @param    array $compiled_data    Compiled campaign data.
	 * @return   object|null               Created campaign or null on failure.
	 * @throws   Exception                 If creation fails.
	 */
	public function create_campaign( array $compiled_data ): ?object {
		if ( ! $this->campaign_repository ) {
			throw new Exception( 'Campaign repository not available' );
		}

		// Extract product IDs if present - prioritize product_ids over legacy products key
		$product_ids = $compiled_data['product_ids'] ?? array();
		if ( empty( $product_ids ) && isset( $compiled_data['products'] ) && is_array( $compiled_data['products'] ) ) {
			// Fallback to products key only if it contains actual product IDs (not 'all' or 'smart')
			if ( ! in_array( 'all', $compiled_data['products'], true ) && ! in_array( 'smart', $compiled_data['products'], true ) ) {
				$product_ids = $compiled_data['products'];
			}
		}

		// Use appropriate repository method
		if ( method_exists( $this->campaign_repository, 'save_campaign_with_products' ) && ! empty( $product_ids ) ) {
			$result = $this->campaign_repository->save_campaign_with_products( $compiled_data );
			// Convert false to null to match return type
			return false !== $result ? $result : null;
		}

		// For repositories without save_campaign_with_products, create and save campaign
		if ( class_exists( 'SCD_Campaign' ) ) {
			$campaign = new SCD_Campaign( $compiled_data );

			// Debug: Check validation before saving
				$validation_errors = $campaign->validate();
			if ( ! empty( $validation_errors ) ) {
			}

			if ( $this->campaign_repository->save( $campaign ) ) {
				// If campaign is being created as active AND requires compilation, trigger it
				if ( 'active' === $campaign->get_status() ) {
					$selection_type = $campaign->get_product_selection_type();
					if ( in_array( $selection_type, array( 'random_products', 'smart_selection' ), true ) ) {
						// Trigger the compilation hook so Campaign_Manager can handle it
						do_action( 'scd_campaign_activated', $campaign );
					}
				}

				return $campaign;
			}
		}

		return null;
	}

	/**
	 * Get unique campaign name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string   $name                 Original campaign name.
	 * @param    int|null $exclude_campaign_id  Campaign ID to exclude (for editing).
	 * @return   string                            Unique campaign name.
	 */
	private function get_unique_campaign_name( string $name, ?int $exclude_campaign_id = null ): string {
		global $wpdb;
		$table         = $wpdb->prefix . 'scd_campaigns';
		$original_name = $name;
		$counter       = 1;
		$max_attempts  = 100; // Prevent infinite loops

		// Keep checking until we find a unique name
		while ( $counter <= $max_attempts ) {
			// Check if name exists (including soft-deleted campaigns)
			// Exclude current campaign when editing
			if ( $exclude_campaign_id ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE name = %s AND id != %d",
						$name,
						$exclude_campaign_id
					)
				);
			} else {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE name = %s",
						$name
					)
				);
			}

			// Check for database errors
			if ( $wpdb->last_error ) {
				throw new Exception( 'Database error while checking campaign name uniqueness: ' . $wpdb->last_error );
			}

			if ( ! $exists ) {
				break; // Name is unique
			}

			// Generate new name with counter
			++$counter;
			$name = $original_name . ' (' . $counter . ')';

			// Add debug logging
		}

		// If we exhausted all attempts, throw an exception
		if ( $counter > $max_attempts ) {
			throw new Exception( 'Could not generate unique campaign name after ' . $max_attempts . ' attempts' );
		}

		return $name;
	}

	/**
	 * Validate compiled campaign data.
	 *
	 * @since    1.0.0
	 * @param    array $compiled_data    Compiled campaign data.
	 * @return   array                      Validation result.
	 */
	public function validate_compiled_data( array $compiled_data ): array {
		$errors = array();

		// Check required fields
		$required_fields = array( 'name', 'discount_type', 'product_selection_type' );
		foreach ( $required_fields as $field ) {
			if ( empty( $compiled_data[ $field ] ) ) {
				$errors[ $field ] = sprintf( __( '%s is required', 'smart-cycle-discounts' ), $field );
			}
		}

		// Validate campaign model if available
		if ( class_exists( 'SCD_Campaign' ) ) {
			$campaign     = new SCD_Campaign( $compiled_data );
			$model_errors = $campaign->validate();

			if ( ! empty( $model_errors ) ) {
				$errors = array_merge( $errors, $model_errors );
			}

			// Name uniqueness is now handled automatically in transform_campaign_data()
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}
}
