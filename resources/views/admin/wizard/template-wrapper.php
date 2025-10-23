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
			$args['sidebar'] = SCD_Wizard_Sidebar::get_sidebar( $args['step'] );
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
			return sanitize_text_field( $value ); // Sanitize all strings
		}
	};

	$state_data = array_merge( array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'scd_wizard_nonce' ),
		'saved_data' => $sanitize_recursive( $saved_data )
	), $sanitize_recursive( $additional_data ) );

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
 *     @type string $title Card title
 *     @type string $subtitle Card subtitle/description (optional)
 *     @type string $icon Dashicon name without 'dashicons-' prefix (optional)
 *     @type string $content Card body content
 *     @type string $class Additional CSS classes (optional)
 *     @type string $edit_step Step to navigate to when edit button clicked (optional)
 *     @type bool $collapsible Whether the card can be collapsed (optional)
 *     @type string $id Card ID for JavaScript targeting (optional)
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
		'id'          => ''
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
		 <?php if ( $args['collapsible'] ): ?>data-collapsible="true"<?php endif; ?>>
		<div class="scd-card__header">
			<h3 class="scd-card__title">
				<?php if ( $args['icon'] ): ?>
					<span class="dashicons dashicons-<?php echo esc_attr( $args['icon'] ); ?>"></span>
				<?php endif; ?>
				<?php 
				// Check if title contains badge HTML
				if ( strpos( $args['title'], 'scd-badge' ) !== false ) {
					// Allow safe HTML for badges while preventing XSS
					echo wp_kses( $args['title'], array(
						'span' => array(
							'class' => array(),
							'aria-hidden' => array(),
							'id' => array()
						),
						'h2' => array(
							'class' => array(),
							'id' => array()
						)
					) );
				} else {
					// Regular text title - escape normally
					echo esc_html( $args['title'] );
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
 *     @type string $id Field ID
 *     @type string $name Field name
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
		'id'                => '',
		'name'              => '',
		'label'             => '',
		'type'              => 'text',
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
	
	// Validate required parameters
	if ( empty( $args['id'] ) || empty( $args['name'] ) ) {
		return;
	}
	
	// Build attributes string
	$attr_string = '';
	foreach ( $args['attributes'] as $attr => $val ) {
		$attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $val ) );
	}
	
	$has_error = isset( $args['validation_errors'][$args['name']] );
	$field_classes = array();
	if ( ! empty( $args['class'] ) ) {
		$field_classes[] = sanitize_html_class( $args['class'] );
	}
	if ( $has_error ) {
		$field_classes[] = 'error';
	}
	$field_class_string = implode( ' ', $field_classes );
	?>
	<div class="form-field">
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
			<?php echo esc_html( $args['label'] ); ?>
			<?php if ( $args['required'] ): ?>
				<span class="required">*</span>
			<?php endif; ?>
			<?php if ( $args['tooltip'] ): ?>
				<?php scd_wizard_field_helper( $args['tooltip'] ); ?>
			<?php endif; ?>
		</label>
		<div class="scd-field-container">
			<div class="scd-input-wrapper">
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
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
						<?php echo $args['required'] ? 'aria-required="true"' : ''; ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in construction (line 373)
						echo $attr_string;
						?>
					/>
				<?php endif; ?>
				
				<?php if ( 'hidden' !== $args['type'] ): ?>
					<span class="scd-field-status">
						<span class="scd-field-valid dashicons dashicons-yes-alt" style="display: none;" aria-hidden="true"></span>
						<span class="scd-field-invalid dashicons dashicons-dismiss" style="display: none;" aria-hidden="true"></span>
					</span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! empty( $args['description'] ) ): ?>
				<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php endif; ?>
			
			<?php scd_wizard_field_errors( $args['validation_errors'], $args['name'] ); ?>
		</div>
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
		<div class="scd-spinner" aria-hidden="true"></div>
		<span class="scd-loading-text"><?php echo esc_html( $text ); ?></span>
	</div>
	<?php
}