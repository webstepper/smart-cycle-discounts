<?php
/**
 * Import Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-import-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import Handler Class
 *
 * @since 1.0.0
 */
class SCD_Import_Handler extends SCD_Abstract_Ajax_Handler {

	use SCD_License_Validation_Trait;

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Cache manager instance.
	 *
	 * @var SCD_Cache_Manager|null
	 */
	private $cache = null;

	/**
	 * Constructor.
	 *
	 * @param object     $container Container instance.
	 * @param SCD_Logger $logger    Logger instance.
	 */
	public function __construct( $container, $logger ) {
		parent::__construct( $logger );
		$this->container = $container;

		// Get cache manager if available
		if ( $container->has( 'cache' ) ) {
			$this->cache = $container->get( 'cache' );
		}
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		return 'scd_ajax';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		$start_time = microtime( true );

		$license_check = $this->validate_license( 'critical' );
		if ( $this->license_validation_failed( $license_check ) ) {
			return $this->license_error_response( $license_check );
		}

		$import_data = isset( $request['import_data'] ) ? $request['import_data'] : '';

		// Log request start
		$this->logger->flow(
			'info',
			'AJAX START',
			'Processing import request',
			array(
				'data_size' => strlen( $import_data ),
				'user_id'   => get_current_user_id(),
			)
		);

		if ( empty( $import_data ) ) {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'No import data provided',
				array(
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'No import data provided', 'smart-cycle-discounts' ) );
		}

		// Decode JSON
		$data = json_decode( $import_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Invalid JSON data',
				array(
					'json_error'  => json_last_error_msg(),
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Invalid JSON data', 'smart-cycle-discounts' ) );
		}

		if ( ! isset( $data['type'] ) || ! isset( $data['data'] ) ) {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Invalid import file format',
				array(
					'has_type'    => isset( $data['type'] ),
					'has_data'    => isset( $data['data'] ),
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Invalid import file format', 'smart-cycle-discounts' ) );
		}

		// Route to appropriate import handler
		if ( 'campaigns' === $data['type'] ) {
			return $this->import_campaigns( $data['data'], $start_time );
		} elseif ( 'settings' === $data['type'] ) {
			return $this->import_settings( $data['data'], $start_time );
		} else {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Unknown import type',
				array(
					'import_type' => $data['type'],
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Unknown import type', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Import campaigns.
	 *
	 * @param array $campaigns  Campaigns data.
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function import_campaigns( $campaigns, $start_time ) {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$imported        = 0;
		$failed          = 0;
		$errors          = array();

		foreach ( $campaigns as $campaign ) {
			// Validate and sanitize campaign data
			$sanitized_campaign = $this->sanitize_campaign_data( $campaign );

			// Check for validation errors
			if ( is_wp_error( $sanitized_campaign ) ) {
				++$failed;
				if ( count( $errors ) < 3 ) {
					$errors[] = $sanitized_campaign->get_error_message();
				}
				continue;
			}

			// Insert sanitized campaign
			$result = $wpdb->insert( $campaigns_table, $sanitized_campaign );

			if ( $result ) {
				++$imported;
			} else {
				++$failed;
				// Only store first 3 errors to avoid bloating logs
				if ( count( $errors ) < 3 ) {
					$errors[] = $wpdb->last_error;
				}
			}
		}

		// Log import results
		if ( $failed > 0 ) {
			$this->logger->flow(
				'warning',
				'CAMPAIGN CREATE',
				'Campaigns imported with errors',
				array(
					'total'           => count( $campaigns ),
					'imported'        => $imported,
					'failed'          => $failed,
					'sample_errors'   => $errors,
					'user_id'         => get_current_user_id(),
					'_start_time'     => $start_time,
					'_include_memory' => true,
				)
			);
		} else {
			$this->logger->flow(
				'notice',
				'CAMPAIGN CREATE',
				'Campaigns imported successfully',
				array(
					'total'           => count( $campaigns ),
					'imported'        => $imported,
					'user_id'         => get_current_user_id(),
					'_start_time'     => $start_time,
					'_include_memory' => true,
				)
			);
		}

		// Clear all caches after campaign import
		if ( $this->cache && $imported > 0 ) {
			$this->cache->flush();
			$this->logger->info( 'All caches cleared after campaign import' );
		}

		return $this->success(
			array(
				'message' => sprintf(
				/* translators: %d: number of campaigns imported */
					_n( '%d campaign imported successfully', '%d campaigns imported successfully', $imported, 'smart-cycle-discounts' ),
					$imported
				),
			)
		);
	}

	/**
	 * Sanitize and validate campaign data for import.
	 *
	 * @param array $campaign Raw campaign data.
	 * @return array|WP_Error Sanitized campaign data or error.
	 */
	private function sanitize_campaign_data( $campaign ) {
		if ( ! is_array( $campaign ) ) {
			return new WP_Error( 'invalid_data', __( 'Campaign data must be an array', 'smart-cycle-discounts' ) );
		}

		// Define allowed fields with their sanitization/validation rules
		$field_rules = array(
			// Text fields
			'uuid'                     => array( 'type' => 'text', 'max_length' => 36, 'required' => false ),
			'name'                     => array( 'type' => 'text', 'max_length' => 255, 'required' => true ),
			'slug'                     => array( 'type' => 'slug', 'max_length' => 255, 'required' => true ),
			'description'              => array( 'type' => 'textarea', 'required' => false ),
			'template_id'              => array( 'type' => 'text', 'max_length' => 100, 'required' => false ),
			'color_theme'              => array( 'type' => 'color', 'max_length' => 7, 'required' => false ),
			'icon'                     => array( 'type' => 'text', 'max_length' => 100, 'required' => false ),
			'timezone'                 => array( 'type' => 'text', 'max_length' => 50, 'required' => false ),

			// Enum fields
			'status'                   => array(
				'type'    => 'enum',
				'options' => array( 'draft', 'scheduled', 'active', 'paused', 'expired', 'archived' ),
			),
			'product_selection_type'   => array(
				'type'    => 'enum',
				'options' => array( 'all_products', 'random_products', 'specific_products', 'smart_selection' ),
			),
			'rotation_type'            => array(
				'type'    => 'enum',
				'options' => array( 'sequential', 'random', 'performance_based', 'inventory_based' ),
			),
			'discount_type'            => array(
				'type'    => 'enum',
				'options' => array( 'percentage', 'fixed', 'bogo', 'tiered', 'spend_threshold' ),
			),

			// Integer fields
			'priority'                 => array( 'type' => 'int', 'min' => 0, 'max' => 255 ),
			'rotation_interval'        => array( 'type' => 'int', 'min' => 0 ),
			'max_concurrent_products'  => array( 'type' => 'int', 'min' => 0 ),
			'max_uses'                 => array( 'type' => 'int', 'min' => 0, 'allow_null' => true ),
			'max_uses_per_customer'    => array( 'type' => 'int', 'min' => 0, 'allow_null' => true ),
			'current_uses'             => array( 'type' => 'int', 'min' => 0 ),
			'products_count'           => array( 'type' => 'int', 'min' => 0 ),
			'orders_count'             => array( 'type' => 'int', 'min' => 0 ),
			'impressions_count'        => array( 'type' => 'int', 'min' => 0 ),
			'clicks_count'             => array( 'type' => 'int', 'min' => 0 ),

			// Boolean fields
			'rotation_enabled'         => array( 'type' => 'bool' ),

			// Decimal fields
			'discount_value'           => array( 'type' => 'decimal', 'precision' => 10, 'scale' => 4 ),
			'revenue_generated'        => array( 'type' => 'decimal', 'precision' => 15, 'scale' => 4 ),
			'conversion_rate'          => array( 'type' => 'decimal', 'precision' => 5, 'scale' => 2 ),

			// JSON fields
			'settings'                 => array( 'type' => 'json' ),
			'metadata'                 => array( 'type' => 'json' ),
			'product_ids'              => array( 'type' => 'json' ),
			'category_ids'             => array( 'type' => 'json' ),
			'tag_ids'                  => array( 'type' => 'json' ),
			'discount_rules'           => array( 'type' => 'json' ),
			'usage_limits'             => array( 'type' => 'json' ),

			// DateTime fields
			'starts_at'                => array( 'type' => 'datetime', 'allow_null' => true ),
			'ends_at'                  => array( 'type' => 'datetime', 'allow_null' => true ),
			'last_rotation_at'         => array( 'type' => 'datetime', 'allow_null' => true ),

			// User ID fields
			'created_by'               => array( 'type' => 'user_id', 'required' => false ),
			'updated_by'               => array( 'type' => 'user_id', 'allow_null' => true ),
		);

		$sanitized = array();

		// Validate required fields
		foreach ( $field_rules as $field => $rules ) {
			if ( ! empty( $rules['required'] ) && empty( $campaign[ $field ] ) ) {
				/* translators: %s: field name */
				return new WP_Error( 'missing_field', sprintf( __( 'Required field missing: %s', 'smart-cycle-discounts' ), $field ) );
			}
		}

		// Sanitize each field
		foreach ( $campaign as $field => $value ) {
			// Skip disallowed fields (whitelist approach)
			if ( ! isset( $field_rules[ $field ] ) ) {
				continue;
			}

			$rules = $field_rules[ $field ];

			// Handle null values
			if ( is_null( $value ) ) {
				if ( ! empty( $rules['allow_null'] ) ) {
					$sanitized[ $field ] = null;
					continue;
				} else {
					continue; // Skip null values for non-nullable fields
				}
			}

			// Sanitize based on type
			switch ( $rules['type'] ) {
				case 'text':
					$sanitized[ $field ] = sanitize_text_field( $value );
					if ( ! empty( $rules['max_length'] ) ) {
						$sanitized[ $field ] = substr( $sanitized[ $field ], 0, $rules['max_length'] );
					}
					break;

				case 'slug':
					$sanitized[ $field ] = sanitize_title( $value );
					if ( ! empty( $rules['max_length'] ) ) {
						$sanitized[ $field ] = substr( $sanitized[ $field ], 0, $rules['max_length'] );
					}
					break;

				case 'textarea':
					$sanitized[ $field ] = sanitize_textarea_field( $value );
					break;

				case 'color':
					$sanitized[ $field ] = sanitize_hex_color( $value );
					if ( false === $sanitized[ $field ] ) {
						$sanitized[ $field ] = '#2271b1'; // Default color
					}
					break;

				case 'enum':
					$clean_value = sanitize_key( $value );
					if ( in_array( $clean_value, $rules['options'], true ) ) {
						$sanitized[ $field ] = $clean_value;
					} else {
						$sanitized[ $field ] = $rules['options'][0]; // Default to first option
					}
					break;

				case 'int':
					$int_value = absint( $value );
					if ( isset( $rules['min'] ) && $int_value < $rules['min'] ) {
						$int_value = $rules['min'];
					}
					if ( isset( $rules['max'] ) && $int_value > $rules['max'] ) {
						$int_value = $rules['max'];
					}
					$sanitized[ $field ] = $int_value;
					break;

				case 'bool':
					$sanitized[ $field ] = (int) (bool) $value;
					break;

				case 'decimal':
					$float_value = (float) $value;
					// Ensure non-negative for monetary values
					if ( in_array( $field, array( 'discount_value', 'revenue_generated' ), true ) ) {
						$float_value = abs( $float_value );
					}
					$sanitized[ $field ] = number_format( $float_value, $rules['scale'], '.', '' );
					break;

				case 'json':
					if ( is_string( $value ) ) {
						$decoded = json_decode( $value, true );
						if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
							$sanitized[ $field ] = wp_json_encode( $decoded );
						}
					} elseif ( is_array( $value ) ) {
						$sanitized[ $field ] = wp_json_encode( $value );
					}
					break;

				case 'datetime':
					// Validate datetime format
					$date = date_create( $value );
					if ( $date ) {
						$sanitized[ $field ] = $date->format( 'Y-m-d H:i:s' );
					}
					break;

				case 'user_id':
					$user_id = absint( $value );
					if ( $user_id > 0 ) {
						$sanitized[ $field ] = $user_id;
					}
					break;
			}
		}

		// Generate UUID if not provided
		if ( empty( $sanitized['uuid'] ) ) {
			$sanitized['uuid'] = wp_generate_uuid4();
		}

		// Set created_by to current user if not provided
		if ( empty( $sanitized['created_by'] ) ) {
			$sanitized['created_by'] = get_current_user_id();
		}

		// Ensure slug is unique
		if ( ! empty( $sanitized['slug'] ) ) {
			$sanitized['slug'] = $this->ensure_unique_slug( $sanitized['slug'] );
		}

		// Remove id to create new campaigns (never import existing IDs)
		unset( $sanitized['id'] );

		return $sanitized;
	}

	/**
	 * Ensure campaign slug is unique.
	 *
	 * @param string $slug Base slug.
	 * @return string Unique slug.
	 */
	private function ensure_unique_slug( $slug ) {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$original_slug   = $slug;
		$counter         = 1;

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$campaigns_table} WHERE slug = %s", $slug ) ) > 0 ) {
			$slug = $original_slug . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Import settings.
	 *
	 * @param array $settings   Settings data.
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function import_settings( $settings, $start_time ) {
		$current_settings = get_option( 'scd_settings', array() );

		// Merge with imported settings
		$merged_settings = array_replace_recursive( $current_settings, $settings );

		$result = update_option( 'scd_settings', $merged_settings );

		// Log import results
		if ( $result ) {
			$this->logger->flow(
				'notice',
				'AJAX SUCCESS',
				'Settings imported successfully',
				array(
					'settings_count'  => count( $settings ),
					'merged_count'    => count( $merged_settings ),
					'user_id'         => get_current_user_id(),
					'_start_time'     => $start_time,
					'_include_memory' => true,
				)
			);

			// Clear all caches after settings import
			if ( $this->cache ) {
				$this->cache->flush();
				$this->logger->info( 'All caches cleared after settings import' );
			}
		} else {
			$this->logger->flow(
				'warning',
				'AJAX SUCCESS',
				'Settings import completed (no changes)',
				array(
					'settings_count' => count( $settings ),
					'user_id'        => get_current_user_id(),
					'_start_time'    => $start_time,
				)
			);
		}

		return $this->success(
			array(
				'message' => __( 'Settings imported successfully', 'smart-cycle-discounts' ),
			)
		);
	}
}
