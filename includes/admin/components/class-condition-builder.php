<?php
/**
 * Condition Builder Component
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Condition Builder Component
 *
 * Handles dynamic condition form building for product filtering.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Condition_Builder {

	/**
	 * Condition engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Condition_Engine    $condition_engine    Condition engine.
	 */
	private SCD_Condition_Engine $condition_engine;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the condition builder.
	 *
	 * @since    1.0.0
	 * @param    SCD_Condition_Engine $condition_engine    Condition engine.
	 * @param    SCD_Logger           $logger              Logger instance.
	 */
	public function __construct( SCD_Condition_Engine $condition_engine, SCD_Logger $logger ) {
		$this->condition_engine = $condition_engine;
		$this->logger           = $logger;
	}

	/**
	 * Render condition builder component.
	 *
	 * @since    1.0.0
	 * @param    array $args    Component arguments.
	 * @return   string           HTML output.
	 */
	public function render( array $args = array() ): string {
		$defaults = array(
			'conditions'     => array(),
			'name_prefix'    => 'conditions',
			'show_only_when' => 'all_products,random_products',
			'title'          => __( 'Optional Conditions', 'smart-cycle-discounts' ),
			'description'    => __( 'Add conditions to filter products based on specific criteria.', 'smart-cycle-discounts' ),
		);

		$args = wp_parse_args( $args, $defaults );

		try {
			ob_start();
			?>
			<div class="scd-condition-builder-wrapper" 
				data-show-when="<?php echo esc_attr( $args['show_only_when'] ); ?>">
				
				<div class="scd-condition-header">
					<h4 class="scd-condition-title">
						<?php echo esc_html( $args['title'] ); ?>
						<span class="scd-condition-toggle">
							<button type="button" class="button button-secondary scd-toggle-conditions" 
									data-expanded="<?php echo empty( $args['conditions'] ) ? 'false' : 'true'; ?>">
								<span class="dashicons dashicons-filter"></span>
								<span class="scd-toggle-text">
									<?php
									echo empty( $args['conditions'] ) ?
										esc_html__( 'Add Conditions', 'smart-cycle-discounts' ) :
										esc_html__( 'Hide Conditions', 'smart-cycle-discounts' );
									?>
								</span>
							</button>
						</span>
					</h4>
					<p class="scd-condition-description">
						<?php echo esc_html( $args['description'] ); ?>
					</p>
				</div>

				<div class="scd-conditions-container" 
					style="<?php echo empty( $args['conditions'] ) ? 'display: none;' : ''; ?>">
					
					<div class="scd-conditions-list">
						<?php echo $this->render_existing_conditions( $args['conditions'], $args['name_prefix'] ); ?>
					</div>

					<div class="scd-condition-actions">
						<button type="button" class="button scd-add-condition">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Add Condition', 'smart-cycle-discounts' ); ?>
						</button>

						<?php if ( ! empty( $args['conditions'] ) ) : ?>
						<button type="button" class="button button-secondary scd-clear-conditions">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear All', 'smart-cycle-discounts' ); ?>
						</button>
						<?php endif; ?>
					</div>

					<div class="scd-condition-preview">
						<div class="scd-preview-summary" id="scd-conditions-summary">
							<?php echo $this->render_conditions_summary( $args['conditions'] ); ?>
						</div>
					</div>
				</div>

				<?php echo $this->render_condition_template(); ?>
				<?php echo $this->render_condition_logic_script( $args['name_prefix'] ); ?>
			</div>
			<?php
			return ob_get_clean();

		} catch ( Exception $e ) {
			$this->logger->error(
				'Condition builder render failed',
				array(
					'args'  => $args,
					'error' => $e->getMessage(),
				)
			);
			return '<div class="scd-error">' . __( 'Failed to load condition builder.', 'smart-cycle-discounts' ) . '</div>';
		}
	}

	/**
	 * Render existing conditions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $conditions     Existing conditions.
	 * @param    string $name_prefix    Name prefix for form fields.
	 * @return   string                    HTML output.
	 */
	private function render_existing_conditions( array $conditions, string $name_prefix ): string {
		if ( empty( $conditions ) ) {
			return '<div class="scd-no-conditions">' .
					esc_html__( 'No conditions added yet. Click "Add Condition" to get started.', 'smart-cycle-discounts' ) .
					'</div>';
		}

		$output = '';
		foreach ( $conditions as $index => $condition ) {
			$output .= $this->render_single_condition( $condition, $index, $name_prefix );
		}

		return $output;
	}

	/**
	 * Render a single condition row.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $condition      Condition data.
	 * @param    int    $index          Condition index.
	 * @param    string $name_prefix    Name prefix for form fields.
	 * @return   string                    HTML output.
	 */
	private function render_single_condition( array $condition, int $index, string $name_prefix ): string {
		$properties = $this->condition_engine->get_supported_properties();
		$operators  = $this->condition_engine->get_supported_operators();

		$selected_property = $condition['property'] ?? '';
		$selected_operator = $condition['operator'] ?? '';
		$condition_values  = $condition['values'] ?? array( '' );

		ob_start();
		?>
		<div class="scd-condition-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="scd-condition-fields">
				<!-- Property Dropdown -->
				<div class="scd-condition-field scd-property-field">
					<select name="<?php echo esc_attr( $name_prefix ); ?>array(<?php echo esc_attr( $index ); ?>)[property]" 
							class="scd-condition-property" 
							data-index="<?php echo esc_attr( $index ); ?>">
						<option value=""><?php esc_html_e( 'Select Property', 'smart-cycle-discounts' ); ?></option>
						<?php foreach ( $properties as $key => $property ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" 
									data-type="<?php echo esc_attr( $property['type'] ); ?>"
									<?php selected( $selected_property, $key ); ?>>
								<?php echo esc_html( $property['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Operator Dropdown -->
				<div class="scd-condition-field scd-operator-field">
					<select name="<?php echo esc_attr( $name_prefix ); ?>array(<?php echo esc_attr( $index ); ?>)[operator]" 
							class="scd-condition-operator" 
							data-index="<?php echo esc_attr( $index ); ?>"
							<?php echo empty( $selected_property ) ? 'disabled' : ''; ?>>
						<option value=""><?php esc_html_e( 'Select Operator', 'smart-cycle-discounts' ); ?></option>
						<?php echo $this->render_operator_options( $operators, $selected_property, $selected_operator, $properties ); ?>
					</select>
				</div>

				<!-- Value Fields -->
				<div class="scd-condition-field scd-value-field">
					<?php echo $this->render_value_inputs( $condition_values, $selected_operator, $index, $name_prefix ); ?>
				</div>
			</div>

			<div class="scd-condition-actions">
				<button type="button" class="button button-small scd-remove-condition" 
						data-index="<?php echo esc_attr( $index ); ?>"
						title="<?php esc_attr_e( 'Remove condition', 'smart-cycle-discounts' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>

			<div class="scd-condition-validation">
				<?php echo $this->render_condition_validation( $condition ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render operator options based on property type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $operators           Available operators.
	 * @param    string $selected_property   Selected property.
	 * @param    string $selected_operator   Selected operator.
	 * @param    array  $properties          Available properties.
	 * @return   string                         HTML output.
	 */
	private function render_operator_options( array $operators, string $selected_property, string $selected_operator, array $properties ): string {
		if ( empty( $selected_property ) || ! isset( $properties[ $selected_property ] ) ) {
			return '';
		}

		$property_type = $properties[ $selected_property ]['type'];
		$output        = '';

		foreach ( $operators as $key => $operator ) {
			if ( in_array( $property_type, $operator['types'] ) ) {
				$output .= sprintf(
					'<option value="%s" data-value-count="%d" %s>%s</option>',
					esc_attr( $key ),
					$operator['value_count'],
					selected( $selected_operator, $key, false ),
					esc_html( $operator['label'] )
				);
			}
		}

		return $output;
	}

	/**
	 * Render value input fields.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $values          Condition values.
	 * @param    string $operator        Selected operator.
	 * @param    int    $index           Condition index.
	 * @param    string $name_prefix     Name prefix.
	 * @return   string                     HTML output.
	 */
	private function render_value_inputs( array $values, string $operator, int $index, string $name_prefix ): string {
		$operators   = $this->condition_engine->get_supported_operators();
		$value_count = isset( $operators[ $operator ] ) ? $operators[ $operator ]['value_count'] : 1;

		ob_start();
		?>
		<div class="scd-value-inputs" data-value-count="<?php echo esc_attr( $value_count ); ?>">
			<?php for ( $i = 0; $i < $value_count; $i++ ) : ?>
				<input type="text" 
						name="<?php echo esc_attr( $name_prefix ); ?>array(<?php echo esc_attr( $index ); ?>)[values]array(<?php echo esc_attr( $i ); ?>)" 
						class="scd-condition-value" 
						value="<?php echo esc_attr( $values[ $i ] ?? '' ); ?>"
						placeholder="<?php echo esc_attr( $this->get_value_placeholder( $operator, $i ) ); ?>"
						<?php echo empty( $operator ) ? 'disabled' : ''; ?> />
				
				<?php if ( $value_count === 2 && $i === 0 ) : ?>
					<span class="scd-value-separator"><?php esc_html_e( 'and', 'smart-cycle-discounts' ); ?></span>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get placeholder text for value inputs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $operator    Selected operator.
	 * @param    int    $index       Value index.
	 * @return   string                 Placeholder text.
	 */
	private function get_value_placeholder( string $operator, int $index ): string {
		switch ( $operator ) {
			case 'between':
			case 'not_between':
				return $index === 0 ? __( 'Min value', 'smart-cycle-discounts' ) : __( 'Max value', 'smart-cycle-discounts' );
			case 'contains':
			case 'not_contains':
			case 'starts_with':
			case 'ends_with':
				return __( 'Text to search', 'smart-cycle-discounts' );
			default:
				return __( 'Enter value', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Render condition validation feedback.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $condition    Condition data.
	 * @return   string                 HTML output.
	 */
	private function render_condition_validation( array $condition ): string {
		if ( empty( $condition ) ) {
			return '';
		}

		$validation = $this->condition_engine->validate_condition( $condition );

		if ( $validation ) {
			return '<div class="scd-validation-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span class="scd-validation-text">' . esc_html__( 'Valid condition', 'smart-cycle-discounts' ) . '</span>
                    </div>';
		} else {
			return '<div class="scd-validation-error">
                        <span class="dashicons dashicons-warning"></span>
                        <span class="scd-validation-text">' . esc_html__( 'Invalid condition', 'smart-cycle-discounts' ) . '</span>
                    </div>';
		}
	}

	/**
	 * Render conditions summary.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $conditions    Conditions array.
	 * @return   string                  HTML output.
	 */
	private function render_conditions_summary( array $conditions ): string {
		if ( empty( $conditions ) ) {
			return '<p class="scd-summary-empty">' .
					esc_html__( 'No conditions applied. All products from selected categories will be included.', 'smart-cycle-discounts' ) .
					'</p>';
		}

		$summaries = $this->condition_engine->get_condition_summaries( $conditions );

		if ( empty( $summaries ) ) {
			return '<p class="scd-summary-invalid">' .
					esc_html__( 'Some conditions are invalid and will be ignored.', 'smart-cycle-discounts' ) .
					'</p>';
		}

		ob_start();
		?>
		<div class="scd-conditions-summary">
			<p class="scd-summary-intro">
				<?php
				printf(
					esc_html( _n( 'Products will be filtered by %d condition:', 'Products will be filtered by %d conditions:', count( $summaries ), 'smart-cycle-discounts' ) ),
					count( $summaries )
				);
				?>
			</p>
			<ul class="scd-summary-list">
				<?php foreach ( $summaries as $summary ) : ?>
					<li class="scd-summary-item">
						<span class="scd-summary-text"><?php echo esc_html( $summary['summary'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render condition row template for JavaScript.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    HTML template.
	 */
	private function render_condition_template(): string {
		$properties = $this->condition_engine->get_supported_properties();
		$operators  = $this->condition_engine->get_supported_operators();

		ob_start();
		?>
		<script type="text/template" id="scd-condition-template">
			<div class="scd-condition-row" data-index="{{INDEX}}">
				<div class="scd-condition-fields">
					<!-- Property Dropdown -->
					<div class="scd-condition-field scd-property-field">
						<select name="{{NAME_PREFIX}}[{{INDEX}}][property]" 
								class="scd-condition-property" 
								data-index="{{INDEX}}">
							<option value=""><?php esc_html_e( 'Select Property', 'smart-cycle-discounts' ); ?></option>
							<?php foreach ( $properties as $key => $property ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" 
										data-type="<?php echo esc_attr( $property['type'] ); ?>">
									<?php echo esc_html( $property['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Operator Dropdown -->
					<div class="scd-condition-field scd-operator-field">
						<select name="{{NAME_PREFIX}}[{{INDEX}}][operator]" 
								class="scd-condition-operator" 
								data-index="{{INDEX}}" disabled>
							<option value=""><?php esc_html_e( 'Select Operator', 'smart-cycle-discounts' ); ?></option>
						</select>
					</div>

					<!-- Value Fields -->
					<div class="scd-condition-field scd-value-field">
						<div class="scd-value-inputs" data-value-count="1">
							<input type="text" 
									name="{{NAME_PREFIX}}[{{INDEX}}][values][0]" 
									class="scd-condition-value" 
									placeholder="<?php esc_attr_e( 'Enter value', 'smart-cycle-discounts' ); ?>" 
									disabled />
						</div>
					</div>
				</div>

				<div class="scd-condition-actions">
					<button type="button" class="button button-small scd-remove-condition" 
							data-index="{{INDEX}}"
							title="<?php esc_attr_e( 'Remove condition', 'smart-cycle-discounts' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>

				<div class="scd-condition-validation"></div>
			</div>
		</script>

		<!-- Operator options templates -->
		<?php foreach ( $properties as $property_key => $property ) : ?>
			<script type="text/template" id="scd-operators-<?php echo esc_attr( $property_key ); ?>">
				<option value=""><?php esc_html_e( 'Select Operator', 'smart-cycle-discounts' ); ?></option>
				<?php foreach ( $operators as $operator_key => $operator ) : ?>
					<?php if ( in_array( $property['type'], $operator['types'] ) ) : ?>
						<option value="<?php echo esc_attr( $operator_key ); ?>" 
								data-value-count="<?php echo esc_attr( $operator['value_count'] ); ?>">
							<?php echo esc_html( $operator['label'] ); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</script>
		<?php endforeach; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render condition logic JavaScript.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $name_prefix    Name prefix for form fields.
	 * @return   string                    JavaScript code.
	 */
	private function render_condition_logic_script( string $name_prefix ): string {
		ob_start();
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var conditionIndex = $('.scd-condition-row').length;
			var namePrefix = '<?php echo esc_js( $name_prefix ); ?>';

			// Toggle conditions visibility
			$('.scd-toggle-conditions').on('click', function() {
				var $button = $(this);
				var $container = $('.scd-conditions-container');
				var isExpanded = $button.data('expanded') === 'true';

				if (isExpanded) {
					$container.slideUp();
					$button.data('expanded', 'false');
					$button.find('.scd-toggle-text').text('<?php echo esc_js( __( 'Add Conditions', 'smart-cycle-discounts' ) ); ?>');
				} else {
					$container.slideDown();
					$button.data('expanded', 'true');
					$button.find('.scd-toggle-text').text('<?php echo esc_js( __( 'Hide Conditions', 'smart-cycle-discounts' ) ); ?>');
				}
			});

			// Add new condition
			$('.scd-add-condition').on('click', function() {
				var template = $('#scd-condition-template').html();
				var newCondition = template
					.replace(/\{\{INDEX\}\}/g, conditionIndex)
					.replace(/\{\{NAME_PREFIX\}\}/g, namePrefix);

				$('.scd-conditions-list').append(newCondition);
				updateNoConditionsMessage();
				conditionIndex++;
			});

			// Remove condition
			$(document).on('click', '.scd-remove-condition', function() {
				var $row = $(this).closest('.scd-condition-row');
				$row.fadeOut(300, function() {
					$row.remove();
					updateNoConditionsMessage();
					updateConditionsSummary();
				});
			});

			// Clear all conditions
			$('.scd-clear-conditions').on('click', function() {
				if (confirm('<?php echo esc_js( __( 'Are you sure you want to remove all conditions?', 'smart-cycle-discounts' ) ); ?>')) {
					$('.scd-condition-row').fadeOut(300, function() {
						$(this).remove();
						updateNoConditionsMessage();
						updateConditionsSummary();
					});
				}
			});

			// Handle property selection change
			$(document).on('change', '.scd-condition-property', function() {
				var $property = $(this);
				var $row = $property.closest('.scd-condition-row');
				var $operator = $row.find('.scd-condition-operator');
				var $values = $row.find('.scd-condition-value');
				var property = $property.val();

				// Reset operator and values
				$operator.html('<option value=""><?php echo esc_js( __( 'Select Operator', 'smart-cycle-discounts' ) ); ?></option>');
				$values.val('').prop('disabled', true);

				if (property) {
					// Load operators for this property
					var operatorTemplate = $('#scd-operators-' + property).html();
					if (operatorTemplate) {
						$operator.html(operatorTemplate).prop('disabled', false);
					}
				} else {
					$operator.prop('disabled', true);
				}

				updateConditionValidation($row);
				updateConditionsSummary();
			});

			// Handle operator selection change
			$(document).on('change', '.scd-condition-operator', function() {
				var $operator = $(this);
				var $row = $operator.closest('.scd-condition-row');
				var $valueContainer = $row.find('.scd-value-inputs');
				var $values = $row.find('.scd-condition-value');
				var operator = $operator.val();
				var valueCount = $operator.find('option:selected').data('value-count') || 1;

				// Update value inputs based on operator
				updateValueInputs($valueContainer, valueCount, $row.data('index'));

				if (operator) {
					$row.find('.scd-condition-value').prop('disabled', false);
				} else {
					$row.find('.scd-condition-value').prop('disabled', true);
				}

				updateConditionValidation($row);
				updateConditionsSummary();
			});

			// Handle value input changes
			$(document).on('input', '.scd-condition-value', function() {
				var $row = $(this).closest('.scd-condition-row');
				updateConditionValidation($row);
				updateConditionsSummary();
			});

			// Show/hide conditions based on product selection
			$(document).on('scd:product-selection-changed', function(e, data) {
				var $wrapper = $('.scd-condition-builder-wrapper');
				var showWhen = $wrapper.data('show-when').split(',');
				
				if (showWhen.includes(data.type)) {
					$wrapper.show();
				} else {
					$wrapper.hide();
				}
			});

			function updateValueInputs($container, valueCount, index) {
				$container.attr('data-value-count', valueCount);
				$container.empty();

				for (var i = 0; i < valueCount; i++) {
					var placeholder = getValuePlaceholder(i, valueCount);
					var input = '<input type="text" name="' + namePrefix + '[' + index + '][values][' + i + ']" ' +
								'class="scd-condition-value" placeholder="' + placeholder + '" />';
					
					$container.append(input);
					
					if (valueCount === 2 && i === 0) {
						$container.append('<span class="scd-value-separator"><?php echo esc_js( __( 'and', 'smart-cycle-discounts' ) ); ?></span>');
					}
				}
			}

			function getValuePlaceholder(index, count) {
				if (count === 2) {
					return index === 0 ? '<?php echo esc_js( __( 'Min value', 'smart-cycle-discounts' ) ); ?>' : '<?php echo esc_js( __( 'Max value', 'smart-cycle-discounts' ) ); ?>';
				}
				return '<?php echo esc_js( __( 'Enter value', 'smart-cycle-discounts' ) ); ?>';
			}

			function updateNoConditionsMessage() {
				var $list = $('.scd-conditions-list');
				var $noConditions = $list.find('.scd-no-conditions');
				var hasConditions = $list.find('.scd-condition-row').length > 0;

				if (hasConditions && $noConditions.length > 0) {
					$noConditions.remove();
				} elseif (!hasConditions && $noConditions.length === 0) {
					$list.append('<div class="scd-no-conditions"><?php echo esc_js( __( 'No conditions added yet. Click "Add Condition" to get started.', 'smart-cycle-discounts' ) ); ?></div>');
				}
			}

			function updateConditionValidation($row) {
				var $validation = $row.find('.scd-condition-validation');
				var property = $row.find('.scd-condition-property').val();
				var operator = $row.find('.scd-condition-operator').val();
				var values = array();
				
				$row.find('.scd-condition-value').each(function() {
					var val = $(this).val().trim();
					if (val) values.push(val);
				});

				var isValid = property && operator && values.length > 0;
				
				if (isValid) {
					$validation.html('<div class="scd-validation-success">' +
						'<span class="dashicons dashicons-yes-alt"></span>' +
						'<span class="scd-validation-text"><?php echo esc_js( __( 'Valid condition', 'smart-cycle-discounts' ) ); ?></span>' +
						'</div>');
				} elseif (property || operator || values.length > 0) {
					$validation.html('<div class="scd-validation-error">' +
						'<span class="dashicons dashicons-warning"></span>' +
						'<span class="scd-validation-text"><?php echo esc_js( __( 'Incomplete condition', 'smart-cycle-discounts' ) ); ?></span>' +
						'</div>');
				} else {
					$validation.empty();
				}
			}

			function updateConditionsSummary() {
				// This would make an AJAX call to get updated summary
				// For now, just show a placeholder
				var conditionCount = $('.scd-condition-row').length;
				var $summary = $('#scd-conditions-summary');
				
				if (conditionCount === 0) {
					$summary.html('<p class="scd-summary-empty"><?php echo esc_js( __( 'No conditions applied. All products from selected categories will be included.', 'smart-cycle-discounts' ) ); ?></p>');
				} else {
					$summary.html('<p class="scd-summary-pending"><?php echo esc_js( __( 'Conditions will be validated when you proceed to the next step.', 'smart-cycle-discounts' ) ); ?></p>');
				}
			}

			// Initialize validation for existing conditions
			$('.scd-condition-row').each(function() {
				updateConditionValidation($(this));
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate conditions array.
	 *
	 * @since    1.0.0
	 * @param    array $conditions    Conditions to validate.
	 * @return   array                   Validation result.
	 */
	public function validate_conditions( array $conditions ): array {
		$errors           = array();
		$valid_conditions = array();

		foreach ( $conditions as $index => $condition ) {
			if ( empty( $condition['property'] ) && empty( $condition['operator'] ) && empty( array_filter( $condition['values'] ?? array() ) ) ) {
				// Skip empty conditions
				continue;
			}

			if ( $this->condition_engine->validate_condition( $condition ) ) {
				$valid_conditions[] = $condition;
			} else {
				$errors[] = sprintf( __( 'Condition %d is invalid.', 'smart-cycle-discounts' ), $index + 1 );
			}
		}

		return array(
			'valid'      => empty( $errors ),
			'errors'     => $errors,
			'conditions' => $valid_conditions,
		);
	}

	/**
	 * Process conditions for product filtering.
	 *
	 * @since    1.0.0
	 * @param    array $conditions    Conditions to process.
	 * @return   array                   Processing result.
	 */
	public function process_conditions( array $conditions ): array {
		try {
			return $this->condition_engine->process_conditions( $conditions );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Process conditions failed',
				array(
					'conditions' => $conditions,
					'error'      => $e->getMessage(),
				)
			);

			return array(
				'success'    => false,
				'errors'     => array( __( 'Condition processing failed.', 'smart-cycle-discounts' ) ),
				'query_args' => array(),
			);
		}
	}
}

