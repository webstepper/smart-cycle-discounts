<?php
/**
 * Settings Page Base Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/class-settings-page-base.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Settings Page Base Class
 *
 * Abstract base class that all settings tabs must extend.
 * Provides common functionality for:
 * - Section registration
 * - Field rendering
 * - Validation
 * - Sanitization
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
abstract class SCD_Settings_Page_Base {

	/**
	 * Tab slug.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $tab_slug    Tab slug.
	 */
	protected string $tab_slug;

	/**
	 * Settings manager instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SCD_Settings_Manager    $settings_manager    Settings manager.
	 */
	protected SCD_Settings_Manager $settings_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	protected SCD_Logger $logger;

	/**
	 * Initialize settings page.
	 *
	 * @since    1.0.0
	 * @param    string               $tab_slug          Tab slug.
	 * @param    SCD_Settings_Manager $settings_manager  Settings manager.
	 * @param    SCD_Logger           $logger            Logger instance.
	 */
	public function __construct( string $tab_slug, SCD_Settings_Manager $settings_manager, SCD_Logger $logger ) {
		$this->tab_slug         = $tab_slug;
		$this->settings_manager = $settings_manager;
		$this->logger           = $logger;
	}

	/**
	 * Initialize settings page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		add_action( 'scd_register_settings_sections', array( $this, 'register_sections' ) );
		add_action( 'scd_render_settings_tab', array( $this, 'render_tab_content' ) );
		add_filter( 'scd_sanitize_settings', array( $this, 'sanitize_tab_settings' ), 10, 3 );
	}

	/**
	 * Register settings sections and fields.
	 * Must be implemented by child classes.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	abstract public function register_sections( string $current_tab ): void;

	/**
	 * Render tab content.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function render_tab_content( string $current_tab ): void {
		// Only render if this is the current tab
		if ( $current_tab !== $this->tab_slug ) {
			return;
		}

		// Render sections using WordPress Settings API
		do_settings_sections( 'scd-settings-' . $this->tab_slug );
	}

	/**
	 * Sanitize tab settings.
	 *
	 * @since    1.0.0
	 * @param    array  $settings       All settings.
	 * @param    string $current_tab    Current active tab.
	 * @param    array  $raw_tab_input  Raw POST input for current tab (optional).
	 * @return   array                     Sanitized settings.
	 */
	public function sanitize_tab_settings( array $settings, string $current_tab, array $raw_tab_input = array() ): array {
		// Only sanitize if this is the current tab
		if ( $current_tab !== $this->tab_slug ) {
			return $settings;
		}

		// Apply tab-specific sanitization using raw input if provided
		if ( isset( $settings[ $this->tab_slug ] ) ) {
			// Pass raw input if available, otherwise use merged settings
			$input_to_sanitize           = ! empty( $raw_tab_input ) ? $raw_tab_input : $settings[ $this->tab_slug ];
			$settings[ $this->tab_slug ] = $this->sanitize_settings( $input_to_sanitize );
		}

		return $settings;
	}

	/**
	 * Sanitize settings for this tab.
	 * Can be overridden by child classes for custom sanitization.
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data for this tab.
	 * @return   array              Sanitized data.
	 */
	protected function sanitize_settings( array $input ): array {
		// Default sanitization - override in child classes
		return $input;
	}

	/**
	 * Add settings section.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $id          Section ID.
	 * @param    string $title       Section title.
	 * @param    string $callback    Optional. Callback function. Default empty.
	 * @return   void
	 */
	protected function add_section( string $id, string $title, string $callback = '' ): void {
		add_settings_section(
			$id,
			$title,
			$callback ? array( $this, $callback ) : '__return_null',
			'scd-settings-' . $this->tab_slug
		);
	}

	/**
	 * Add settings field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $id         Field ID.
	 * @param    string $title      Field title.
	 * @param    string $callback   Callback function.
	 * @param    string $section    Section ID.
	 * @param    array  $args       Optional. Field arguments. Default empty array.
	 * @return   void
	 */
	protected function add_field( string $id, string $title, string $callback, string $section, array $args = array() ): void {
		// Add tab slug to args
		$args['tab']       = $this->tab_slug;
		$args['field_id']  = $id;
		$args['label_for'] = $id; // Connect label to input

		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'scd-settings-' . $this->tab_slug,
			$section,
			$args
		);
	}

	/**
	 * Get setting value.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $key       Setting key.
	 * @param    mixed  $default   Default value.
	 * @return   mixed                Setting value.
	 */
	protected function get_setting( string $key, $default = null ) {
		$settings = $this->settings_manager->get_tab_settings( $this->tab_slug );

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Get field name attribute.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $field_id    Field ID.
	 * @return   string                 Field name.
	 */
	protected function get_field_name( string $field_id ): string {
		$option_name = $this->settings_manager->get_option_name();
		return sprintf( '%s[%s][%s]', $option_name, $this->tab_slug, $field_id );
	}

	/**
	 * Render toggle field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	protected function render_toggle_field( array $args ): void {
		$field_id = $args['field_id'];
		$value    = $this->get_setting( $field_id, false );
		$name     = $this->get_field_name( $field_id );

		// Render tooltip using centralized system
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}

		echo '<label class="scd-toggle">';
		printf(
			'<input type="checkbox" id="%s" name="%s" value="1" %s>',
			esc_attr( $field_id ),
			esc_attr( $name ),
			checked( $value, true, false )
		);
		echo '<span class="scd-toggle-slider"></span>';
		echo '</label>';
	}

	/**
	 * Render text field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	protected function render_text_field( array $args ): void {
		$field_id    = $args['field_id'];
		$value       = $this->get_setting( $field_id, '' );
		$name        = $this->get_field_name( $field_id );
		$class       = isset( $args['class'] ) ? $args['class'] : 'regular-text';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

		printf(
			'<input type="text" id="%s" name="%s" value="%s" class="%s" placeholder="%s">',
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $class ),
			esc_attr( $placeholder )
		);

		// Render tooltip using centralized system
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}

		// Render additional description if provided (separate from tooltip)
		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render number field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	protected function render_number_field( array $args ): void {
		$field_id = $args['field_id'];
		$value    = $this->get_setting( $field_id, 0 );
		$name     = $this->get_field_name( $field_id );
		$min      = isset( $args['min'] ) ? $args['min'] : 0;
		$max      = isset( $args['max'] ) ? $args['max'] : '';
		$step     = isset( $args['step'] ) ? $args['step'] : 1;
		$class    = isset( $args['class'] ) ? $args['class'] : 'small-text';

		printf(
			'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="%s">',
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step ),
			esc_attr( $class )
		);

		// Render tooltip using centralized system
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}

		// Render suffix if provided
		if ( isset( $args['suffix'] ) ) {
			echo ' <span class="description">' . esc_html( $args['suffix'] ) . '</span>';
		}
	}

	/**
	 * Render select field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	protected function render_select_field( array $args ): void {
		$field_id = $args['field_id'];
		$value    = $this->get_setting( $field_id, '' );
		$name     = $this->get_field_name( $field_id );
		$options  = isset( $args['options'] ) ? $args['options'] : array();
		$class    = isset( $args['class'] ) ? $args['class'] : 'regular-text';

		printf(
			'<select id="%s" name="%s" class="%s">',
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $class )
		);

		foreach ( $options as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';

		// Render tooltip using centralized system
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}
	}

	/**
	 * Render textarea field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	protected function render_textarea_field( array $args ): void {
		$field_id    = $args['field_id'];
		$value       = $this->get_setting( $field_id, '' );
		$name        = $this->get_field_name( $field_id );
		$rows        = isset( $args['rows'] ) ? $args['rows'] : 5;
		$class       = isset( $args['class'] ) ? $args['class'] : 'large-text';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

		printf(
			'<textarea id="%s" name="%s" rows="%s" class="%s" placeholder="%s">%s</textarea>',
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $rows ),
			esc_attr( $class ),
			esc_attr( $placeholder ),
			esc_textarea( $value )
		);

		// Render tooltip using centralized system
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}

		// Render additional description if provided (separate from tooltip)
		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render tooltip using global tooltip system.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $text    Tooltip text.
	 * @param    array  $args    Optional tooltip arguments.
	 * @return   void
	 */
	protected function render_tooltip( string $text, array $args = array() ): void {
		SCD_Tooltip_Helper::render( $text, $args );
	}

	/**
	 * Get tooltip HTML.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $text    Tooltip text.
	 * @param    array  $args    Optional tooltip arguments.
	 * @return   string             Tooltip HTML.
	 */
	protected function get_tooltip( string $text, array $args = array() ): string {
		return SCD_Tooltip_Helper::get( $text, $args );
	}
}
