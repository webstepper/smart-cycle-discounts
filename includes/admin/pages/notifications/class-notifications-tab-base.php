<?php
/**
 * Notifications Tab Base Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/class-notifications-tab-base.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
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
abstract class WSSCD_Notifications_Tab_Base {

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
	 * @var      WSSCD_Notifications_Page    $page    Page instance.
	 */
	protected WSSCD_Notifications_Page $page;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	protected WSSCD_Logger $logger;

	/**
	 * Initialize tab.
	 *
	 * @since    1.0.0
	 * @param    string                 $tab_slug  Tab slug.
	 * @param    WSSCD_Notifications_Page $page      Page instance.
	 * @param    WSSCD_Logger             $logger    Logger instance.
	 */
	public function __construct( string $tab_slug, WSSCD_Notifications_Page $page, WSSCD_Logger $logger ) {
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
		add_filter( 'wsscd_sanitize_notifications', array( $this, 'sanitize_tab_settings' ), 10, 2 );
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
			'wsscd_notifications_' . $this->tab_slug
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

		// Append tooltip to title if provided - use wp_kses with SVG tags since tooltip contains SVG icon.
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			$title .= ' ' . wp_kses( WSSCD_Tooltip_Helper::get( $args['tooltip'] ), WSSCD_Icon_Helper::get_allowed_html_with_svg() );
		}

		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'wsscd_notifications_' . $this->tab_slug,
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

		// Merge with defaults
		$defaults     = $this->get_notification_defaults();
		$tab_settings = wp_parse_args( $tab_settings, $defaults );

		return isset( $tab_settings[ $field_id ] ) ? $tab_settings[ $field_id ] : $default;
	}

	/**
	 * Get notification default settings.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   array    Default notification settings.
	 */
	protected function get_notification_defaults(): array {
		return array(
			'email_provider'           => 'wpmail',
			'from_email'               => get_option( 'admin_email' ),
			'from_name'                => get_bloginfo( 'name' ),
			'additional_recipients'    => '',
			'sendgrid_api_key'         => '',
			'amazonses_access_key'     => '',
			'amazonses_secret_key'     => '',
			'amazonses_region'         => 'us-east-1',
			// FREE notifications - ON by default
			'notify_campaign_started'  => true,
			'notify_campaign_ended'    => true,
			'notify_errors'            => true,
			// PRO notifications - OFF by default
			'notify_campaign_ending'   => false,
			'notify_daily_report'      => false,
			'notify_weekly_report'     => false,
			'notify_performance_alert' => false,
			'notify_low_stock_alert'   => false,
			'notify_milestone_alert'   => false,
		);
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
			esc_attr( $disabled )
		);

		if ( $pro_feature && ! $this->page->get_feature_gate()->is_enabled( 'premium_email_providers' ) ) {
			echo ' ' . wp_kses_post( WSSCD_Badge_Helper::pro_badge() );
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

		$notification_type   = isset( $args['notification_type'] ) ? $args['notification_type'] : null;
		$is_pro_tier         = false;
		$has_access          = true;

		if ( $notification_type ) {
			$feature_gate = $this->page->get_feature_gate();
			$is_pro_tier  = $feature_gate->is_notification_pro_tier( $notification_type );
			$has_access   = $feature_gate->can_send_notification( $notification_type );

			// Disable checkbox if PRO feature and user doesn't have access
			if ( $is_pro_tier && ! $has_access ) {
				$disabled = ' disabled';
				$checked  = ''; // Uncheck PRO features for free users
			}
		}

		?>
		<div class="wsscd-toggle-wrapper<?php echo esc_attr( $is_pro_tier && ! $has_access ? ' wsscd-pro-feature-locked' : '' ); ?>">
			<label class="wsscd-toggle">
				<input type="checkbox"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $args['label_for'] ); ?>"
					value="1"
					<?php echo esc_attr( $checked . $disabled ); ?>
					<?php if ( $is_pro_tier && ! $has_access ) : ?>
						data-pro-feature="true"
						data-notification-type="<?php echo esc_attr( $notification_type ); ?>"
					<?php endif; ?>
				/>
				<span class="wsscd-toggle-slider"></span>
			</label>
		</div>
		<?php
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
		WSSCD_Tooltip_Helper::render( $text, $args );
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
		return WSSCD_Tooltip_Helper::get( $text, $args );
	}
}
