<?php
/**
 * Condition Builder Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components/class-condition-builder.php
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
 * Condition Builder Component
 *
 * Handles dynamic condition form building for product filtering.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Condition_Builder {

	/**
	 * Condition engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Condition_Engine    $condition_engine    Condition engine.
	 */
	private WSSCD_Condition_Engine $condition_engine;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Initialize the condition builder.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Condition_Engine $condition_engine    Condition engine.
	 * @param    WSSCD_Logger           $logger              Logger instance.
	 */
	public function __construct( WSSCD_Condition_Engine $condition_engine, WSSCD_Logger $logger ) {
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
			<div class="wsscd-condition-builder-wrapper" 
				data-show-when="<?php echo esc_attr( $args['show_only_when'] ); ?>">
				
				<div class="wsscd-condition-header">
					<h4 class="wsscd-condition-title">
						<?php echo esc_html( $args['title'] ); ?>
						<span class="wsscd-condition-toggle">
							<button type="button" class="button button-secondary wsscd-toggle-conditions"
									data-expanded="<?php echo esc_attr( empty( $args['conditions'] ) ? 'false' : 'true' ); ?>">
								<?php
								WSSCD_Icon_Helper::render( 'filter', array( 'size' => 16 ) );
								?>
								<span class="wsscd-toggle-text">
									<?php
									echo empty( $args['conditions'] ) ?
										esc_html__( 'Add Conditions', 'smart-cycle-discounts' ) :
										esc_html__( 'Hide Conditions', 'smart-cycle-discounts' );
									?>
								</span>
							</button>
						</span>
					</h4>
					<p class="wsscd-condition-description">
						<?php echo esc_html( $args['description'] ); ?>
					</p>
				</div>

				<div class="wsscd-conditions-container"
					style="<?php echo esc_attr( empty( $args['conditions'] ) ? 'display: none;' : '' ); ?>">
					
					<div class="wsscd-conditions-list">
						<?php
						WSSCD_HTML_Helper::output( $this->render_existing_conditions( $args['conditions'], $args['name_prefix'] ) );
						?>
					</div>

					<div class="wsscd-condition-actions">
						<button type="button" class="button wsscd-add-condition">
							<?php
							WSSCD_Icon_Helper::render( 'add', array( 'size' => 16 ) );
							?>
							<?php esc_html_e( 'Add Condition', 'smart-cycle-discounts' ); ?>
						</button>

						<?php if ( ! empty( $args['conditions'] ) ) : ?>
						<button type="button" class="button button-secondary wsscd-clear-conditions">
							<?php
							WSSCD_Icon_Helper::render( 'delete', array( 'size' => 16 ) );
							?>
							<?php esc_html_e( 'Clear All', 'smart-cycle-discounts' ); ?>
						</button>
						<?php endif; ?>
					</div>

					<div class="wsscd-condition-preview">
						<div class="wsscd-preview-summary" id="wsscd-conditions-summary">
							<?php
							WSSCD_HTML_Helper::output( $this->render_conditions_summary( $args['conditions'] ) );
							?>
						</div>
					</div>
				</div>

				<?php
				WSSCD_HTML_Helper::output( $this->render_condition_template() );
				WSSCD_HTML_Helper::output( $this->render_condition_logic_script( $args['name_prefix'] ) );
				?>
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
			return '<div class="wsscd-error">' . __( 'Failed to load condition builder.', 'smart-cycle-discounts' ) . '</div>';
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
			return '<div class="wsscd-no-conditions">' .
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

		$selected_property = $condition['condition_type'] ?? '';
		$selected_operator = $condition['operator'] ?? '';
		$condition_value   = $condition['value'] ?? '';
		$condition_value2  = $condition['value2'] ?? '';

		ob_start();
		?>
		<div class="wsscd-condition-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="wsscd-condition-fields">
				<!-- Property Dropdown -->
				<div class="wsscd-condition-field wsscd-property-field">
					<select name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $index ); ?>][condition_type]"
							class="wsscd-condition-type"
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
				<div class="wsscd-condition-field wsscd-operator-field">
					<select name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $index ); ?>][operator]"
							class="wsscd-condition-operator"
							data-index="<?php echo esc_attr( $index ); ?>"
							<?php disabled( empty( $selected_property ) ); ?>>
						<option value=""><?php esc_html_e( 'Select Operator', 'smart-cycle-discounts' ); ?></option>
						<?php
						echo wp_kses_post( $this->render_operator_options( $operators, $selected_property, $selected_operator, $properties ) );
						?>
					</select>
				</div>

				<!-- Value Fields -->
				<div class="wsscd-condition-field wsscd-value-field">
					<?php
					echo wp_kses_post( $this->render_value_inputs( $condition_value, $condition_value2, $selected_operator, $index, $name_prefix ) );
					?>
				</div>
			</div>

			<div class="wsscd-condition-actions">
				<button type="button" class="button button-small wsscd-remove-condition"
						data-index="<?php echo esc_attr( $index ); ?>"
						title="<?php esc_attr_e( 'Remove condition', 'smart-cycle-discounts' ); ?>">
					<?php
					WSSCD_Icon_Helper::render( 'delete', array( 'size' => 16 ) );
					?>
				</button>
			</div>

			<div class="wsscd-condition-validation">
				<?php
				WSSCD_HTML_Helper::output( $this->render_condition_validation( $condition ) );
				?>
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
	 * @param    string $value           Primary condition value.
	 * @param    string $value2          Secondary condition value (for between operators).
	 * @param    string $operator        Selected operator.
	 * @param    int    $index           Condition index.
	 * @param    string $name_prefix     Name prefix.
	 * @return   string                     HTML output.
	 */
	private function render_value_inputs( string $value, string $value2, string $operator, int $index, string $name_prefix ): string {
		$operators   = $this->condition_engine->get_supported_operators();
		$value_count = isset( $operators[ $operator ] ) ? $operators[ $operator ]['value_count'] : 1;

		ob_start();
		?>
		<div class="wsscd-value-inputs" data-value-count="<?php echo esc_attr( $value_count ); ?>">
			<input type="text"
					name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $index ); ?>][value]"
					class="wsscd-condition-value"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $this->get_value_placeholder( $operator, 0 ) ); ?>"
					<?php disabled( empty( $operator ) ); ?> />

			<?php if ( $value_count === 2 ) : ?>
				<span class="wsscd-value-separator"><?php esc_html_e( 'and', 'smart-cycle-discounts' ); ?></span>
				<input type="text"
						name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $index ); ?>][value2]"
						class="wsscd-condition-value wsscd-condition-value-between"
						value="<?php echo esc_attr( $value2 ); ?>"
						placeholder="<?php echo esc_attr( $this->get_value_placeholder( $operator, 1 ) ); ?>"
						<?php disabled( empty( $operator ) ); ?> />
			<?php endif; ?>
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

		// Use wp_kses with SVG allowed tags since wp_kses_post strips SVG elements.
		if ( $validation ) {
			return '<div class="wsscd-validation-success">
                        ' . wp_kses( WSSCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() ) . '
                        <span class="wsscd-validation-text">' . esc_html__( 'Valid condition', 'smart-cycle-discounts' ) . '</span>
                    </div>';
		} else {
			return '<div class="wsscd-validation-error">
                        ' . wp_kses( WSSCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() ) . '
                        <span class="wsscd-validation-text">' . esc_html__( 'Invalid condition', 'smart-cycle-discounts' ) . '</span>
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
			return '<p class="wsscd-summary-empty">' .
					esc_html__( 'No conditions applied. All products from selected categories will be included.', 'smart-cycle-discounts' ) .
					'</p>';
		}

		$summaries = $this->condition_engine->get_condition_summaries( $conditions );

		if ( empty( $summaries ) ) {
			return '<p class="wsscd-summary-invalid">' .
					esc_html__( 'Some conditions are invalid and will be ignored.', 'smart-cycle-discounts' ) .
					'</p>';
		}

		ob_start();
		?>
		<div class="wsscd-conditions-summary">
			<p class="wsscd-summary-intro">
				<?php
				printf(
					/* translators: %d: number of active product filter conditions */
					esc_html( _n( 'Products will be filtered by %d condition:', 'Products will be filtered by %d conditions:', count( $summaries ), 'smart-cycle-discounts' ) ),
					count( $summaries )
				);
				?>
			</p>
			<ul class="wsscd-summary-list">
				<?php foreach ( $summaries as $summary ) : ?>
					<li class="wsscd-summary-item">
						<span class="wsscd-summary-text"><?php echo esc_html( $summary['summary'] ); ?></span>
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
		<script type="text/template" id="wsscd-condition-template">
			<div class="wsscd-condition-row" data-index="{{INDEX}}">
				<div class="wsscd-condition-fields">
					<!-- Property Dropdown -->
					<div class="wsscd-condition-field wsscd-property-field">
						<select name="{{NAME_PREFIX}}[{{INDEX}}][condition_type]"
								class="wsscd-condition-type"
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
					<div class="wsscd-condition-field wsscd-operator-field">
						<select name="{{NAME_PREFIX}}[{{INDEX}}][operator]" 
								class="wsscd-condition-operator" 
								data-index="{{INDEX}}" disabled>
							<option value=""><?php esc_html_e( 'Select Operator', 'smart-cycle-discounts' ); ?></option>
						</select>
					</div>

					<!-- Value Fields -->
					<div class="wsscd-condition-field wsscd-value-field">
						<div class="wsscd-value-inputs" data-value-count="1">
							<input type="text"
									name="{{NAME_PREFIX}}[{{INDEX}}][value]"
									class="wsscd-condition-value"
									placeholder="<?php esc_attr_e( 'Enter value', 'smart-cycle-discounts' ); ?>"
									disabled />
						</div>
					</div>
				</div>

				<div class="wsscd-condition-actions">
					<button type="button" class="button button-small wsscd-remove-condition"
							data-index="{{INDEX}}"
							title="<?php esc_attr_e( 'Remove condition', 'smart-cycle-discounts' ); ?>">
						<?php
						WSSCD_Icon_Helper::render( 'delete', array( 'size' => 16 ) );
						?>
					</button>
				</div>

				<div class="wsscd-condition-validation"></div>
			</div>
		</script>

		<!-- Operator options templates -->
		<?php foreach ( $properties as $property_key => $property ) : ?>
			<script type="text/template" id="wsscd-operators-<?php echo esc_attr( $property_key ); ?>">
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
		// Localize the script data
		$script_data = array(
			'namePrefix'           => $name_prefix,
			'i18n'                 => array(
				'addConditions'       => __( 'Add Conditions', 'smart-cycle-discounts' ),
				'hideConditions'      => __( 'Hide Conditions', 'smart-cycle-discounts' ),
				'confirmClearAll'     => __( 'Are you sure you want to remove all conditions?', 'smart-cycle-discounts' ),
				'selectOperator'      => __( 'Select Operator', 'smart-cycle-discounts' ),
				'andSeparator'        => __( 'and', 'smart-cycle-discounts' ),
				'minValue'            => __( 'Min value', 'smart-cycle-discounts' ),
				'maxValue'            => __( 'Max value', 'smart-cycle-discounts' ),
				'enterValue'          => __( 'Enter value', 'smart-cycle-discounts' ),
				'noConditionsYet'     => __( 'No conditions added yet. Click "Add Condition" to get started.', 'smart-cycle-discounts' ),
				'validCondition'      => __( 'Valid condition', 'smart-cycle-discounts' ),
				'incompleteCondition' => __( 'Incomplete condition', 'smart-cycle-discounts' ),
				'noConditionsApplied' => __( 'No conditions applied. All products from selected categories will be included.', 'smart-cycle-discounts' ),
				'conditionsValidated' => __( 'Conditions will be validated when you proceed to the next step.', 'smart-cycle-discounts' ),
			),
			'icons'                => array(
				'check'   => WSSCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ),
				'warning' => WSSCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ),
			),
		);

		// Generate inline script with localized data
		$inline_script = 'window.wsscdConditionBuilderConfig = ' . wp_json_encode( $script_data ) . ';';

		// Add the configuration data
		wp_add_inline_script( 'jquery-core', $inline_script );

		// Now generate the main script without inline PHP
		ob_start();
		?>
		jQuery(document).ready(function($) {
			var config = window.wsscdConditionBuilderConfig || {};
			var i18n = config.i18n || {};
			var icons = config.icons || {};
			var conditionIndex = $('.wsscd-condition-row').length;
			var namePrefix = config.namePrefix || '';

			// Toggle conditions visibility
			$('.wsscd-toggle-conditions').on('click', function() {
				var $button = $(this);
				var $container = $('.wsscd-conditions-container');
				var isExpanded = $button.data('expanded') === 'true';

				if (isExpanded) {
					$container.slideUp();
					$button.data('expanded', 'false');
					$button.find('.wsscd-toggle-text').text(i18n.addConditions || 'Add Conditions');
				} else {
					$container.slideDown();
					$button.data('expanded', 'true');
					$button.find('.wsscd-toggle-text').text(i18n.hideConditions || 'Hide Conditions');
				}
			});

			$('.wsscd-add-condition').on('click', function() {
				var template = $('#wsscd-condition-template').html();
				var newCondition = template
					.replace(/\{\{INDEX\}\}/g, conditionIndex)
					.replace(/\{\{NAME_PREFIX\}\}/g, namePrefix);

				$('.wsscd-conditions-list').append(newCondition);
				updateNoConditionsMessage();
				conditionIndex++;
			});

			$(document).on('click', '.wsscd-remove-condition', function() {
				var $row = $(this).closest('.wsscd-condition-row');
				$row.fadeOut(300, function() {
					$row.remove();
					updateNoConditionsMessage();
					updateConditionsSummary();
				});
			});

			$('.wsscd-clear-conditions').on('click', function() {
				if (confirm(i18n.confirmClearAll || 'Are you sure you want to remove all conditions?')) {
					$('.wsscd-condition-row').fadeOut(300, function() {
						$(this).remove();
						updateNoConditionsMessage();
						updateConditionsSummary();
					});
				}
			});

			// Handle property selection change
			$(document).on('change', '.wsscd-condition-property', function() {
				var $property = $(this);
				var $row = $property.closest('.wsscd-condition-row');
				var $operator = $row.find('.wsscd-condition-operator');
				var $values = $row.find('.wsscd-condition-value');
				var property = $property.val();

				$operator.html('<option value="">' + (i18n.selectOperator || 'Select Operator') + '</option>');
				$values.val('').prop('disabled', true);

				if (property) {
					var operatorTemplate = $('#wsscd-operators-' + property).html();
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
			$(document).on('change', '.wsscd-condition-operator', function() {
				var $operator = $(this);
				var $row = $operator.closest('.wsscd-condition-row');
				var $valueContainer = $row.find('.wsscd-value-inputs');
				var $values = $row.find('.wsscd-condition-value');
				var operator = $operator.val();
				var valueCount = $operator.find('option:selected').data('value-count') || 1;

				updateValueInputs($valueContainer, valueCount, $row.data('index'));

				if (operator) {
					$row.find('.wsscd-condition-value').prop('disabled', false);
				} else {
					$row.find('.wsscd-condition-value').prop('disabled', true);
				}

				updateConditionValidation($row);
				updateConditionsSummary();
			});

			// Handle value input changes
			$(document).on('input', '.wsscd-condition-value', function() {
				var $row = $(this).closest('.wsscd-condition-row');
				updateConditionValidation($row);
				updateConditionsSummary();
			});

			// Show/hide conditions based on product selection
			$(document).on('wsscd:product-selection-changed', function(e, data) {
				var $wrapper = $('.wsscd-condition-builder-wrapper');
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
								'class="wsscd-condition-value" placeholder="' + placeholder + '" />';
					
					$container.append(input);
					
					if (valueCount === 2 && i === 0) {
						$container.append('<span class="wsscd-value-separator">' + (i18n.andSeparator || 'and') + '</span>');
					}
				}
			}

			function getValuePlaceholder(index, count) {
				if (count === 2) {
					return index === 0 ? (i18n.minValue || 'Min value') : (i18n.maxValue || 'Max value');
				}
				return i18n.enterValue || 'Enter value';
			}

			function updateNoConditionsMessage() {
				var $list = $('.wsscd-conditions-list');
				var $noConditions = $list.find('.wsscd-no-conditions');
				var hasConditions = $list.find('.wsscd-condition-row').length > 0;

				if (hasConditions && $noConditions.length > 0) {
					$noConditions.remove();
				} else if (!hasConditions && $noConditions.length === 0) {
					$list.append('<div class="wsscd-no-conditions">' + (i18n.noConditionsYet || 'No conditions added yet. Click "Add Condition" to get started.') + '</div>');
				}
			}

			function updateConditionValidation($row) {
				var $validation = $row.find('.wsscd-condition-validation');
				var property = $row.find('.wsscd-condition-property').val();
				var operator = $row.find('.wsscd-condition-operator').val();
				var values = [];

				$row.find('.wsscd-condition-value').each(function() {
					var val = $(this).val().trim();
					if (val) values.push(val);
				});

				var isValid = property && operator && values.length > 0;

				if (isValid) {
					$validation.html('<div class="wsscd-validation-success">' +
						(icons.check || '') +
						'<span class="wsscd-validation-text">' + (i18n.validCondition || 'Valid condition') + '</span>' +
						'</div>');
				} else if (property || operator || values.length > 0) {
					$validation.html('<div class="wsscd-validation-error">' +
						(icons.warning || '') +
						'<span class="wsscd-validation-text">' + (i18n.incompleteCondition || 'Incomplete condition') + '</span>' +
						'</div>');
				} else {
					$validation.empty();
				}
			}

			function updateConditionsSummary() {
				// This would make an AJAX call to get updated summary
				// For now, just show a placeholder
				var conditionCount = $('.wsscd-condition-row').length;
				var $summary = $('#wsscd-conditions-summary');

				if (conditionCount === 0) {
					$summary.html('<p class="wsscd-summary-empty">' + (i18n.noConditionsApplied || 'No conditions applied. All products from selected categories will be included.') + '</p>');
				} else {
					$summary.html('<p class="wsscd-summary-pending">' + (i18n.conditionsValidated || 'Conditions will be validated when you proceed to the next step.') + '</p>');
				}
			}

			$('.wsscd-condition-row').each(function() {
				updateConditionValidation($(this));
			});
		});
		<?php
		$js_code = ob_get_clean();

		// Use wp_add_inline_script for WordPress.org compliance
		wp_add_inline_script( 'jquery-core', $js_code );

		return '';
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
			if ( empty( $condition['condition_type'] ) && empty( $condition['operator'] ) && empty( $condition['value'] ) ) {
				// Skip empty conditions
				continue;
			}

			if ( $this->condition_engine->validate_condition( $condition ) ) {
				$valid_conditions[] = $condition;
			} else {
				/* translators: %d: condition number (1, 2, 3, etc.) */
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

