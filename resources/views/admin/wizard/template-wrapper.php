<?php
/**
 * Wizard Step Template Wrapper
 * 
 * This template provides the standard fullscreen layout structure for all wizard steps
 * 
 * Usage:
 * Include this file and call scd_wizard_render_step() with your content
 * 
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a wizard step with the standard fullscreen layout
 * 
 * @since 1.0.0
 * @param array $args {
 *     Arguments for rendering the step
 * 
 *     @type string $title         Step title
 *     @type string $description   Step description
 *     @type string $content       Main content HTML
 *     @type string $sidebar       Sidebar content HTML (optional - will use registered sidebar if not provided)
 *     @type string $step          Step identifier for sidebar lookup (optional)
 *     @type string $form_id       Form ID (optional)
 *     @type array  $form_data     Form data array (optional)
 * }
 */
function scd_wizard_render_step( $args ) {
	$defaults = array(
		'title'       => '',
		'description' => '',
		'content'     => '',
		'sidebar'     => '',
		'step'        => '',
		'step_data'   => array(),
		'form_id'     => '',
		'form_data'   => array()
	);

	$args = wp_parse_args( $args, $defaults );
	
	// Validate required parameters
	if ( empty( $args['content'] ) ) {
		return;
	}
	
	// Sanitize step identifier
	$step_class = ! empty( $args['step'] ) ? sanitize_html_class( $args['step'] ) : '';
	
	// If no sidebar provided but step is specified, get from sidebar class
	if ( empty( $args['sidebar'] ) && ! empty( $args['step'] ) ) {
		if ( class_exists( 'SCD_Wizard_Sidebar' ) ) {
			$args['sidebar'] = SCD_Wizard_Sidebar::get_sidebar( $args['step'], $args['step_data'] );
		}
	}
	
	// Append additional sidebar content if provided
	if ( ! empty( $args['additional_sidebar'] ) ) {
		$args['sidebar'] .= $args['additional_sidebar'];
	}
	?>
	
	<div class="scd-step-main-content<?php echo $step_class ? ' scd-wizard-step--' . esc_attr( $step_class ) : ''; ?>">
		<?php
		// Content should be escaped by the caller
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['content'];
		?>
	</div><!-- .scd-step-main-content -->
	
	<?php if ( $args['sidebar'] ): ?>
		<!-- Help Sidebar -->
		<aside class="scd-step-sidebar">
			<?php
			// Sidebar content should be escaped by the caller
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $args['sidebar'];
			?>
		</aside>
	<?php endif; ?>
	
	<?php
}

/**
 * Render validation errors for a specific field
 * 
 * @since 1.0.0
 * @param array $validation_errors Array of validation errors
 * @param string $field_name Field name to check for errors
 * @return void
 */
function scd_wizard_field_errors( $validation_errors, $field_name ) {
	if ( isset( $validation_errors[$field_name] ) ): ?>
		<div class="scd-field-error">
			<?php foreach ( (array) $validation_errors[$field_name] as $error ): ?>
				<p class="error-message"><?php echo esc_html( $error ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif;
}

/**
 * Render the main validation notice
 * 
 * @since 1.0.0
 * @param array $validation_errors Array of validation errors
 * @return void
 */
function scd_wizard_validation_notice( $validation_errors ) {
	if ( ! empty( $validation_errors ) ): ?>
		<div class="notice notice-error is-dismissible scd-validation-notice">
			<p><strong><?php esc_html_e( 'Please correct the following errors:', 'smart-cycle-discounts' ); ?></strong></p>
			<ul>
				<?php foreach ( $validation_errors as $field => $errors ): ?>
					<?php foreach ( (array) $errors as $error ): ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif;
}

/**
 * Initialize wizard step state and variables
 * 
 * @since 1.0.0
 * @param array &$step_data Reference to step data variable
 * @param array &$validation_errors Reference to validation errors variable
 * @return void
 */
function scd_wizard_init_step_vars( &$step_data, &$validation_errors ) {
	// Initialize variables with safe defaults
	$step_data = isset( $step_data ) ? $step_data : array();
	$validation_errors = isset( $validation_errors ) ? $validation_errors : array();
	
	// Check nonce first for security
	if ( isset( $_GET['validation_error'] ) && isset( $_GET['_wpnonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'scd_wizard_validation' ) ) {
			return;
		}
		
		if ( absint( $_GET['validation_error'] ) === 1 ) {
			$user_id = get_current_user_id();
			
			// Load stored validation errors
			$stored_errors = get_transient( "scd_wizard_validation_errors_{$user_id}" );
			if ( $stored_errors ) {
				$validation_errors = $stored_errors;
				delete_transient( "scd_wizard_validation_errors_{$user_id}" );
			}
			
			// Load previous form data
			$stored_form_data = get_transient( "scd_wizard_form_data_{$user_id}" );
			if ( $stored_form_data ) {
				// Safely get the current step from request URI
				$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				$step_key = sanitize_key( basename( parse_url( $request_uri, PHP_URL_PATH ), '.php' ) );
				
				if ( ! empty( $step_key ) && isset( $stored_form_data[$step_key] ) ) {
					$step_data = array_merge( $step_data, $stored_form_data[$step_key] );
					delete_transient( "scd_wizard_form_data_{$user_id}" );
				}
			}
		}
	}
}

/**
 * Generate wizard state initialization script
 * 
 * @since 1.0.0
 * @param string $step_name The name of the wizard step (e.g., 'basic', 'products')
 * @param array $saved_data The saved data for this step
 * @param array $additional_data Additional data to include in state (optional)
 * @return void
 */
function scd_wizard_state_script( $step_name, $saved_data, $additional_data = array() ) {
	// Validate step name
	$step_name = sanitize_key( $step_name );
	if ( empty( $step_name ) ) {
		return;
	}

	// Extract validation rules if provided
	$validation_rules = null;
	if ( isset( $additional_data['validation'] ) ) {
		$validation_rules = $additional_data['validation'];
		unset( $additional_data['validation'] ); // Remove from additional data to avoid duplication
	}

	// CRITICAL: Recursively sanitize all data before outputting to JavaScript
	$sanitize_recursive = function( $value ) use ( &$sanitize_recursive ) {
		if ( is_array( $value ) ) {
			return array_map( $sanitize_recursive, $value );
		} elseif ( is_bool( $value ) || is_null( $value ) ) {
			return $value; // Booleans and nulls are safe
		} elseif ( is_numeric( $value ) ) {
			return $value; // Numbers are safe
		} else {
			// Sanitize strings for safe output
			// wp_json_encode() with JSON_HEX flags provides sufficient escaping for JavaScript context
			// Operators are validated via whitelist in Field_Definitions, no decode needed
			return sanitize_text_field( $value );
		}
	};

	$state_data = array_merge( array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'scd_wizard_nonce' ),
		'saved_data' => $sanitize_recursive( $saved_data )
	), $sanitize_recursive( $additional_data ) );

	// Convert snake_case to camelCase for JavaScript (consistent with Asset Localizer)
	if ( class_exists( 'SCD_Case_Converter' ) ) {
		$state_data = SCD_Case_Converter::snake_to_camel( $state_data );
	}

	?>
	<script type="text/javascript">
	window.scd<?php echo esc_js( ucfirst( $step_name ) ); ?>State = <?php echo wp_json_encode( $state_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	<?php if ( $validation_rules !== null ): ?>
	window.scd<?php echo esc_js( ucfirst( $step_name ) ); ?>Validation = <?php echo wp_json_encode( $sanitize_recursive( $validation_rules ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	<?php endif; ?>
	</script>
	<?php
}

/**
 * Generate card structure
 *
 * @since 1.0.0
 * @param array $args {
 *     Arguments for the card
 *
 *     @type string       $title       Card title (plain text, no HTML)
 *     @type string       $subtitle    Card subtitle/description (optional)
 *     @type string       $icon        Dashicon name without 'dashicons-' prefix (optional)
 *     @type string       $content     Card body content
 *     @type string       $class       Additional CSS classes (optional)
 *     @type string       $edit_step   Step to navigate to when edit button clicked (optional)
 *     @type bool         $collapsible Whether the card can be collapsed (optional)
 *     @type string       $id          Card ID for JavaScript targeting (optional)
 *     @type array|string $badge       Badge configuration (optional). Can be:
 *                                     - String: Badge text with default 'info' type
 *                                     - Array: array( 'text' => 'Badge text', 'type' => 'optional|required|info|success|warning|danger' )
 *     @type string       $indicator   Indicator type (optional): 'optional' or 'required'
 * }
 * @return void
 */
function scd_wizard_card( $args ) {
	$defaults = array(
		'title'       => '',
		'subtitle'    => '',
		'icon'        => '',
		'content'     => '',
		'class'       => '',
		'edit_step'   => '',
		'collapsible' => false,
		'id'          => '',
		'badge'       => '',
		'indicator'   => '',
		'help_topic'  => ''
	);

	$args = wp_parse_args( $args, $defaults );

	// Validate required parameters
	if ( empty( $args['title'] ) || empty( $args['content'] ) ) {
		return;
	}

	// Generate ID if not provided
	if ( empty( $args['id'] ) && $args['collapsible'] ) {
		$args['id'] = 'scd-card-' . sanitize_title( $args['title'] );
	}

	$card_classes = array( 'scd-card', 'scd-wizard-card' );
	if ( ! empty( $args['class'] ) ) {
		$card_classes[] = sanitize_html_class( $args['class'] );
	}
	?>
	<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
		 <?php if ( $args['id'] ): ?>id="<?php echo esc_attr( $args['id'] ); ?>"<?php endif; ?>
		 <?php if ( $args['collapsible'] ): ?>data-collapsible="true"<?php endif; ?>
		 <?php if ( ! empty( $args['help_topic'] ) ): ?>data-help-topic="<?php echo esc_attr( $args['help_topic'] ); ?>"<?php endif; ?>>
		<div class="scd-card__header">
			<h3 class="scd-card__title">
				<?php if ( $args['icon'] ): ?>
					<?php echo SCD_Icon_Helper::get( $args['icon'], array( 'size' => 16 ) ); ?>
				<?php endif; ?>
				<?php echo esc_html( $args['title'] ); ?>
				<?php
				// Render badge if provided
				if ( ! empty( $args['badge'] ) ) {
					$badge_text = '';
					$badge_type = 'info';

					if ( is_array( $args['badge'] ) ) {
						$badge_text = isset( $args['badge']['text'] ) ? $args['badge']['text'] : '';
						$badge_type = isset( $args['badge']['type'] ) ? $args['badge']['type'] : 'info';
					} else {
						$badge_text = $args['badge'];
					}

					if ( ! empty( $badge_text ) ) {
						printf(
							'<span class="scd-badge scd-badge--%s">%s</span>',
							esc_attr( $badge_type ),
							esc_html( $badge_text )
						);
					}
				}

				// Render indicator if provided (alternative to badge)
				if ( ! empty( $args['indicator'] ) && empty( $args['badge'] ) ) {
					$indicator_class = 'scd-' . sanitize_html_class( $args['indicator'] ) . '-indicator';
					$indicator_text = 'optional' === $args['indicator']
						? __( 'Optional', 'smart-cycle-discounts' )
						: __( 'Required', 'smart-cycle-discounts' );

					printf(
						'<span class="%s" aria-label="%s">*</span>',
						esc_attr( $indicator_class ),
						esc_attr( $indicator_text )
					);
				}
				?>
			</h3>
			<?php if ( $args['subtitle'] ): ?>
				<p class="scd-card__subtitle">
					<?php echo esc_html( $args['subtitle'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( $args['edit_step'] ): ?>
				<a href="#" class="scd-edit-step" data-edit-step="<?php echo esc_attr( $args['edit_step'] ); ?>">
					<?php esc_html_e( 'Edit', 'smart-cycle-discounts' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<div class="scd-card__content">
			<?php
			// Content should be escaped by the caller
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $args['content'];
			?>
		</div>
	</div>
	<?php
}

/**
 * Generate form field with all wrappers
 *
 * @since 1.0.0
 * @param array $args {
 *     Arguments for the form field
 *
 *     @type string $step Step identifier (e.g., 'basic', 'products') - if provided with field, reads from field definitions
 *     @type string $field Field key from field definitions (e.g., 'name', 'description')
 *     @type string $id Field ID (auto-generated from field if not provided)
 *     @type string $name Field name (auto-generated from field if not provided)
 *     @type string $label Field label
 *     @type string $type Input type (default: 'text')
 *     @type string $value Field value
 *     @type string $placeholder Placeholder text (optional)
 *     @type bool $required Is field required (default: false)
 *     @type string $class Additional CSS classes (optional)
 *     @type array $validation_errors Validation errors array (optional)
 *     @type string $description Field description/help text (optional)
 *     @type string $tooltip Tooltip text to display next to label (optional)
 *     @type array $attributes Additional HTML attributes (optional)
 * }
 * @return void
 */
function scd_wizard_form_field( $args ) {
	$defaults = array(
		'step'              => '',
		'field'             => '',
		'id'                => '',
		'name'              => '',
		'label'             => '',
		'type'              => '',
		'value'             => '',
		'placeholder'       => '',
		'required'          => false,
		'class'             => '',
		'validation_errors' => array(),
		'description'       => '',
		'tooltip'           => '',
		'attributes'        => array(),
		'options'           => array()
	);

	$args = wp_parse_args( $args, $defaults );

	// If step and field are provided, read from field definitions
	if ( ! empty( $args['step'] ) && ! empty( $args['field'] ) && class_exists( 'SCD_Field_Definitions' ) ) {
		$field_def = SCD_Field_Definitions::get_field( $args['step'], $args['field'] );

		if ( ! empty( $field_def ) ) {
			// Map field definition properties to template args
			$field_mapping = array(
				'type'        => 'type',
				'label'       => 'label',
				'required'    => 'required',
				'default'     => 'value',
				'description' => 'description',
				'field_name'  => 'name',
				'options'     => 'options',
				'tooltip'     => 'tooltip'
			);

			// Build field definition args (these become defaults)
			$def_args = array();
			foreach ( $field_mapping as $def_key => $arg_key ) {
				if ( isset( $field_def[ $def_key ] ) ) {
					$def_args[ $arg_key ] = $field_def[ $def_key ];
				}
			}

			// Extract attributes if present
			if ( isset( $field_def['attributes'] ) && is_array( $field_def['attributes'] ) ) {
				// Merge with provided attributes (provided takes precedence)
				$provided_attrs = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
				$def_args['attributes'] = array_merge( $field_def['attributes'], $provided_attrs );
				// Extract placeholder from attributes if present
				if ( isset( $field_def['attributes']['placeholder'] ) && empty( $args['placeholder'] ) ) {
					$def_args['placeholder'] = $field_def['attributes']['placeholder'];
				}
			}
			elseif ( isset( $args['attributes'] ) && is_array( $args['attributes'] ) ) {
				// Use provided attributes if no field definition attributes
				$def_args['attributes'] = $args['attributes'];
			}

			// Auto-generate ID if not provided (use field_name from definition or field key)
			if ( empty( $args['id'] ) ) {
				$def_args['id'] = ! empty( $field_def['field_name'] ) ? $field_def['field_name'] : $args['field'];
			}

			// Merge: field definitions as base, provided args override
			$args = array_merge( $def_args, array_filter( $args, function( $value ) {
				// Keep provided values that are not empty strings or empty arrays
				return $value !== '' && $value !== array();
			} ) );
		}
	}
	
	// Validate required parameters
	if ( empty( $args['id'] ) || empty( $args['name'] ) ) {
		return;
	}

	// Fallback: if type is still empty, default to 'text'
	if ( empty( $args['type'] ) ) {
		$args['type'] = 'text';
	}
	
	// Build attributes string
	$attr_string = '';
	if ( ! empty( $args['attributes'] ) && is_array( $args['attributes'] ) ) {
		foreach ( $args['attributes'] as $attr => $val ) {
			$attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $val ) );
		}
	}
	
	$has_error = isset( $args['validation_errors'][$args['name']] );
	$field_classes = array();

	// Apply appropriate enhanced styling class based on field type
	if ( 'select' === $args['type'] ) {
		$field_classes[] = 'scd-enhanced-select';
	} else {
		$field_classes[] = 'scd-enhanced-input'; // For text, number, email, textarea, etc.
	}

	if ( ! empty( $args['class'] ) ) {
		$field_classes[] = sanitize_html_class( $args['class'] );
	}
	if ( $has_error ) {
		$field_classes[] = 'error';
	}
	$field_class_string = implode( ' ', $field_classes );
	?>
	<div class="scd-form-field">
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
			<?php echo esc_html( $args['label'] ); ?>
			<?php if ( $args['required'] ): ?>
				<span class="required">*</span>
			<?php endif; ?>
			<?php if ( ! empty( $args['tooltip'] ) ): ?>
				<?php scd_wizard_field_helper( $args['tooltip'] ); ?>
			<?php endif; ?>
		</label>

		<?php if ( 'textarea' === $args['type'] ): ?>
			<textarea
				id="<?php echo esc_attr( $args['id'] ); ?>"
				name="<?php echo esc_attr( $args['name'] ); ?>"
				class="<?php echo esc_attr( $field_class_string ); ?>"
				placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				<?php echo $args['required'] ? 'aria-required="true"' : ''; ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in construction (line 373)
				echo $attr_string;
				?>
			><?php echo esc_textarea( $args['value'] ); ?></textarea>
		<?php elseif ( 'select' === $args['type'] && ! empty( $args['options'] ) ): ?>
			<select
				id="<?php echo esc_attr( $args['id'] ); ?>"
				name="<?php echo esc_attr( $args['name'] ); ?>"
				class="<?php echo esc_attr( $field_class_string ); ?>"
				<?php echo $args['required'] ? 'aria-required="true"' : ''; ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in construction (line 373)
				echo $attr_string;
				?>
			>
				<?php foreach ( $args['options'] as $opt_value => $opt_label ): ?>
					<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $args['value'], $opt_value ); ?>>
						<?php echo esc_html( $opt_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php else: ?>
			<input
				type="<?php echo esc_attr( $args['type'] ); ?>"
				id="<?php echo esc_attr( $args['id'] ); ?>"
				name="<?php echo esc_attr( $args['name'] ); ?>"
				value="<?php echo esc_attr( $args['value'] ); ?>"
				class="<?php echo esc_attr( $field_class_string ); ?>"
				placeholder="<?php echo esc_attr( $args['placeholder'] ?? '' ); ?>"
				<?php echo $args['required'] ? 'aria-required="true"' : ''; ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in construction (line 373)
				echo $attr_string;
				?>
			/>
		<?php endif; ?>

		<?php scd_wizard_field_errors( $args['validation_errors'] ?? array(), $args['name'] ); ?>
	</div>
	<?php
}

/**
 * Generate table row form field
 * 
 * @since 1.0.0
 * @param array $args {
 *     Arguments for the table field
 * 
 *     @type string $label Field label
 *     @type string $tooltip Tooltip text (optional)
 *     @type string $content Field content HTML
 * }
 * @return void
 */
function scd_wizard_table_field( $args ) {
	$defaults = array(
		'label'   => '',
		'tooltip' => '',
		'content' => ''
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Validate required parameters
	if ( empty( $args['label'] ) || empty( $args['content'] ) ) {
		return;
	}
	?>
	<tr>
		<th scope="row">
			<label>
				<?php
				// Allow safe HTML in labels (for required indicators, etc.)
				echo wp_kses( $args['label'], array(
					'span' => array(
						'class' => array(),
						'aria-label' => array()
					)
				) );
				?>
				<?php if ( $args['tooltip'] ): ?>
					<?php scd_wizard_field_helper( $args['tooltip'] ); ?>
				<?php endif; ?>
			</label>
		</th>
		<td>
			<?php 
			// Content should be escaped by the caller
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $args['content']; 
			?>
		</td>
	</tr>
	<?php
}

/**
 * Get product names from IDs efficiently
 * 
 * @since 1.0.0
 * @param array $product_ids Array of product IDs
 * @param int $limit Maximum number of names to return
 * @return array Array of product names
 */
function scd_wizard_get_product_names( $product_ids, $limit = 5 ) {
	if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
		return array();
	}
	
	// Sanitize and limit IDs
	$product_ids = array_map( 'absint', $product_ids );
	$product_ids = array_slice( $product_ids, 0, $limit );
	
	if ( empty( $product_ids ) ) {
		return array();
	}
	
	global $wpdb;
	
	// Get product names efficiently with a single query
	$placeholders = array_fill( 0, count( $product_ids ), '%d' );
	$placeholders = implode( ', ', $placeholders );
	
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title FROM {$wpdb->posts} 
			WHERE ID IN ({$placeholders}) 
			AND post_type = 'product' 
			AND post_status = 'publish'
			ORDER BY post_title ASC",
			$product_ids
		)
	);
	
	$product_names = array();
	if ( $results ) {
		foreach ( $results as $product ) {
			$product_names[] = $product->post_title;
		}
	}
	
	return $product_names;
}

/**
 * Get category names from IDs
 * 
 * @since 1.0.0
 * @param array $category_ids Array of category IDs
 * @return array Array of category names
 */
function scd_wizard_get_category_names( $category_ids ) {
	if ( empty( $category_ids ) || ! is_array( $category_ids ) ) {
		return array();
	}
	
	// Sanitize IDs
	$category_ids = array_map( 'absint', $category_ids );
	$category_ids = array_filter( $category_ids );
	
	if ( empty( $category_ids ) ) {
		return array();
	}
	
	$category_names = array();
	
	// Get all terms in a single query
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'include'    => $category_ids,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC'
	) );
	
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		foreach ( $terms as $term ) {
			$category_names[] = $term->name;
		}
	}
	
	return $category_names;
}

/**
 * Generate field helper/tooltip
 *
 * Uses centralized SCD_Tooltip_Helper for consistency
 *
 * @since 1.0.0
 * @param string $tooltip Tooltip text
 * @param array  $args    Optional tooltip arguments
 * @return void
 */
function scd_wizard_field_helper( $tooltip, $args = array() ) {
	if ( empty( $tooltip ) ) {
		return;
	}

	// Use centralized tooltip system
	if ( class_exists( 'SCD_Tooltip_Helper' ) ) {
		SCD_Tooltip_Helper::render( $tooltip, $args );
	}
}

/**
 * Generate loading indicator
 *
 * @since 1.0.0
 * @param string $id Element ID
 * @param string $text Loading text (default: 'Loading...')
 * @return void
 */
function scd_wizard_loading_indicator( $id, $text = '' ) {
	if ( empty( $id ) ) {
		return;
	}

	if ( empty( $text ) ) {
		$text = __( 'Loading...', 'smart-cycle-discounts' );
	}
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="scd-loading-overlay" style="display: none;" role="status">
		<span class="spinner is-active" aria-hidden="true"></span>
		<span class="scd-loading-text"><?php echo esc_html( $text ); ?></span>
	</div>
	<?php
}

/**
 * Generate enhanced form field with wrapper and suffix
 *
 * Creates a beautifully styled form field with optional icon, tooltip, and suffix label.
 * Uses the enhanced field pattern from the discount rules section for consistency.
 *
 * @since 1.0.0
 * @param array $args {
 *     Arguments for the enhanced form field
 *
 *     @type string $id Field ID (required)
 *     @type string $name Field name (required)
 *     @type string $label Field label (required)
 *     @type string $type Input type (default: 'text')
 *     @type string $value Field value
 *     @type string $placeholder Placeholder text
 *     @type bool $required Is field required (default: false)
 *     @type string $icon Dashicon name without 'dashicons-' prefix (optional)
 *     @type string $tooltip Tooltip text (optional)
 *     @type string $suffix Suffix text displayed after input (e.g., 'uses per cycle', 'minutes')
 *     @type string $min Minimum value for number inputs
 *     @type string $max Maximum value for number inputs
 *     @type string $step Step value for number inputs (e.g., '1', '0.01')
 *     @type string $class Additional CSS classes
 * }
 * @return void
 */
function scd_wizard_enhanced_field( $args ) {
	$defaults = array(
		'id'          => '',
		'name'        => '',
		'label'       => '',
		'type'        => 'text',
		'value'       => '',
		'placeholder' => '',
		'required'    => false,
		'icon'        => '',
		'tooltip'     => '',
		'suffix'      => '',
		'min'         => '',
		'max'         => '',
		'step'        => '',
		'class'       => ''
	);

	$args = wp_parse_args( $args, $defaults );

	// Validate required parameters
	if ( empty( $args['id'] ) || empty( $args['name'] ) || empty( $args['label'] ) ) {
		return;
	}
	?>
	<tr>
		<th scope="row">
			<label for="<?php echo esc_attr( $args['id'] ); ?>">
				<?php if ( $args['icon'] ): ?>
					<span class="scd-label-icon" title="<?php echo esc_attr( $args['label'] ); ?>">
						<?php echo SCD_Icon_Helper::get( $args['icon'], array( 'size' => 16 ) ); ?>
					</span>
				<?php endif; ?>
				<?php echo esc_html( $args['label'] ); ?>
				<?php if ( $args['required'] ): ?>
					<span class="required">*</span>
				<?php endif; ?>
				<?php if ( $args['tooltip'] ): ?>
					<span class="scd-field-helper" data-tooltip="<?php echo esc_attr( $args['tooltip'] ); ?>">
						<?php echo SCD_Icon_Helper::get( 'editor-help', array( 'size' => 16 ) ); ?>
					</span>
				<?php endif; ?>
			</label>
		</th>
		<td>
			<?php if ( $args['suffix'] ): ?>
				<div class="scd-input-wrapper">
			<?php endif; ?>

			<input type="<?php echo esc_attr( $args['type'] ); ?>"
			       id="<?php echo esc_attr( $args['id'] ); ?>"
			       name="<?php echo esc_attr( $args['name'] ); ?>"
			       value="<?php echo esc_attr( $args['value'] ); ?>"
			       <?php if ( $args['min'] !== '' ): ?>min="<?php echo esc_attr( $args['min'] ); ?>"<?php endif; ?>
			       <?php if ( $args['max'] !== '' ): ?>max="<?php echo esc_attr( $args['max'] ); ?>"<?php endif; ?>
			       <?php if ( $args['step'] !== '' ): ?>step="<?php echo esc_attr( $args['step'] ); ?>"<?php endif; ?>
			       class="scd-enhanced-input <?php echo esc_attr( $args['class'] ); ?>"
			       placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
			       <?php echo $args['required'] ? 'required aria-required="true"' : ''; ?>>

			<?php if ( $args['suffix'] ): ?>
				<span class="scd-field-suffix"><?php echo esc_html( $args['suffix'] ); ?></span>
				</div>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}