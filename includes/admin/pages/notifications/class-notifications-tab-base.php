<?php
/**
 * Notifications Tab Base Class
 *
 * Base class for all notification tab implementations.
 * Provides common functionality for rendering fields and sections.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Notifications Tab Base Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications
 */
abstract class SCD_Notifications_Tab_Base {

	/**
	 * Tab slug.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $tab_slug    Tab identifier.
	 */
	protected string $tab_slug;

	/**
	 * Notifications page instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SCD_Notifications_Page    $page    Page instance.
	 */
	protected SCD_Notifications_Page $page;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	protected SCD_Logger $logger;

	/**
	 * Initialize tab.
	 *
	 * @since    1.0.0
	 * @param    string                 $tab_slug  Tab slug.
	 * @param    SCD_Notifications_Page $page      Page instance.
	 * @param    SCD_Logger             $logger    Logger instance.
	 */
	public function __construct( string $tab_slug, SCD_Notifications_Page $page, SCD_Logger $logger ) {
		$this->tab_slug = $tab_slug;
		$this->page     = $page;
		$this->logger   = $logger;

		$this->init();
	}

	/**
	 * Initialize tab.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	protected function init(): void {
		add_filter( 'scd_sanitize_notifications', array( $this, 'sanitize_tab_settings' ), 10, 2 );
	}

	/**
	 * Register sections and fields for this tab.
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
	abstract public function render_tab_content( string $current_tab ): void;

	/**
	 * Sanitize tab settings.
	 *
	 * @since    1.0.0
	 * @param    array  $settings      All settings.
	 * @param    string $current_tab   Current tab.
	 * @return   array                    Sanitized settings.
	 */
	public function sanitize_tab_settings( array $settings, string $current_tab ): array {
		// Only sanitize if this is the current tab
		if ( $current_tab !== $this->tab_slug ) {
			return $settings;
		}

		// Sanitize this tab's settings
		if ( isset( $settings['notifications'] ) && is_array( $settings['notifications'] ) ) {
			$settings['notifications'] = $this->sanitize_settings( $settings['notifications'] );
		}

		return $settings;
	}

	/**
	 * Sanitize settings (to be implemented by child classes).
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data.
	 * @return   array              Sanitized data.
	 */
	protected function sanitize_settings( array $input ): array {
		// Default implementation - child classes should override
		return $input;
	}

	/**
	 * Add settings section.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $id          Section ID.
	 * @param    string $title       Section title.
	 * @param    string $callback    Callback method name.
	 * @return   void
	 */
	protected function add_section( string $id, string $title, string $callback ): void {
		add_settings_section(
			$id,
			$title,
			array( $this, $callback ),
			'scd_notifications_' . $this->tab_slug
		);
	}

	/**
	 * Add settings field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $id        Field ID.
	 * @param    string $title     Field title.
	 * @param    string $callback  Callback method name.
	 * @param    string $section   Section ID.
	 * @param    array  $args      Field arguments.
	 * @return   void
	 */
	protected function add_field( string $id, string $title, string $callback, string $section, array $args = array() ): void {
		$args['field_id']  = $id;
		$args['label_for'] = $this->page->get_option_name() . '[notifications][' . $id . ']';

		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'scd_notifications_' . $this->tab_slug,
			$section,
			$args
		);
	}

	/**
	 * Get field value.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $field_id    Field ID.
	 * @param    mixed  $default     Default value.
	 * @return   mixed                  Field value.
	 */
	protected function get_value( string $field_id, $default = '' ) {
		$all_settings = get_option( $this->page->get_option_name(), array() );
		$tab_settings = isset( $all_settings['notifications'] ) ? $all_settings['notifications'] : array();

		return isset( $tab_settings[ $field_id ] ) ? $tab_settings[ $field_id ] : $default;
	}

	/**
	 * Get field name for form submission.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $field_id    Field ID.
	 * @return   string                 Field name.
	 */
	protected function get_field_name( string $field_id ): string {
		return $this->page->get_option_name() . '[notifications][' . $field_id . ']';
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
		$value       = $this->get_value( $field_id );
		$class       = isset( $args['class'] ) ? $args['class'] : 'regular-text';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$disabled    = isset( $args['disabled'] ) && $args['disabled'] ? ' disabled' : '';
		$pro_feature = isset( $args['pro_feature'] ) && $args['pro_feature'];

		printf(
			'<input type="text" id="%s" name="%s" value="%s" class="%s" placeholder="%s"%s />',
			esc_attr( $field_id ),
			esc_attr( $args['label_for'] ),
			esc_attr( $value ),
			esc_attr( $class ),
			esc_attr( $placeholder ),
			$disabled
		);

		if ( $pro_feature && ! $this->page->get_feature_gate()->is_enabled( 'premium_email_providers' ) ) {
			echo ' <span class="scd-pro-badge">' . esc_html__( 'PRO', 'smart-cycle-discounts' ) . '</span>';
		}

		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
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
		$value    = $this->get_value( $field_id );
		$options  = isset( $args['options'] ) ? $args['options'] : array();
		$class    = isset( $args['class'] ) ? $args['class'] : '';

		printf(
			'<select id="%s" name="%s" class="%s">',
			esc_attr( $field_id ),
			esc_attr( $args['label_for'] ),
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

		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}
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
		$field_id    = $args['field_id'];
		$value       = $this->get_value( $field_id, false );
		$checked     = $value ? ' checked="checked"' : '';
		$disabled    = isset( $args['disabled'] ) && $args['disabled'] ? ' disabled' : '';
		$pro_feature = isset( $args['pro_feature'] ) && $args['pro_feature'];

		// Check for notification-specific PRO features
		$notification_type   = isset( $args['notification_type'] ) ? $args['notification_type'] : null;
		$is_pro_notification = false;
		$has_access          = true;

		if ( $notification_type ) {
			$feature_gate        = $this->page->get_feature_gate();
			$is_pro_notification = ! $feature_gate->can_send_notification( $notification_type );
			$has_access          = $feature_gate->can_send_notification( $notification_type );

			// Disable checkbox if PRO feature and user doesn't have access
			if ( $is_pro_notification && ! $has_access ) {
				$disabled = ' disabled';
				$checked  = ''; // Uncheck PRO features for free users
			}
		}

		?>
		<div class="scd-toggle-wrapper<?php echo $is_pro_notification && ! $has_access ? ' scd-pro-feature-locked' : ''; ?>">
			<label class="scd-toggle-switch">
				<input type="checkbox"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $args['label_for'] ); ?>"
					value="1"
					<?php echo $checked . $disabled; ?>
					<?php if ( $is_pro_notification && ! $has_access ) : ?>
						data-pro-feature="true"
						data-notification-type="<?php echo esc_attr( $notification_type ); ?>"
					<?php endif; ?>
				/>
				<span class="scd-toggle-slider"></span>
			</label>

			<?php if ( $is_pro_notification ) : ?>
				<?php if ( ! $has_access ) : ?>
					<span class="scd-pro-badge scd-pro-badge-locked" title="<?php esc_attr_e( 'Upgrade to PRO to unlock this notification', 'smart-cycle-discounts' ); ?>">
						<?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?>
					</span>
				<?php else : ?>
					<span class="scd-pro-badge scd-pro-badge-active" title="<?php esc_attr_e( 'PRO Feature', 'smart-cycle-discounts' ); ?>">
						<?php esc_html_e( 'PRO', 'smart-cycle-discounts' ); ?>
					</span>
				<?php endif; ?>
			<?php else : ?>
				<span class="scd-free-badge" title="<?php esc_attr_e( 'Free Feature', 'smart-cycle-discounts' ); ?>">
					<?php esc_html_e( 'FREE', 'smart-cycle-discounts' ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php

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
		$field_id = $args['field_id'];
		$value    = $this->get_value( $field_id );
		$rows     = isset( $args['rows'] ) ? $args['rows'] : 5;
		$class    = isset( $args['class'] ) ? $args['class'] : 'large-text';

		printf(
			'<textarea id="%s" name="%s" rows="%d" class="%s">%s</textarea>',
			esc_attr( $field_id ),
			esc_attr( $args['label_for'] ),
			absint( $rows ),
			esc_attr( $class ),
			esc_textarea( $value )
		);

		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
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

	/**
	 * Render field label with tooltip.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $label     Label text.
	 * @param    string $field_id  Field ID.
	 * @param    string $tooltip   Tooltip text.
	 * @param    array  $args      Optional tooltip arguments.
	 * @return   void
	 */
	protected function render_label_with_tooltip( string $label, string $field_id, string $tooltip, array $args = array() ): void {
		SCD_Tooltip_Helper::render_label( $label, $field_id, $tooltip, $args );
	}
}
