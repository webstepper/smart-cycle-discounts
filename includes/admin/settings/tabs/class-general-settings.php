<?php
/**
 * General Settings Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/tabs/class-general-settings.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * General Settings Tab Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/tabs
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_General_Settings extends WSSCD_Settings_Page_Base {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Initialize general settings.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Settings_Manager $settings_manager  Settings manager.
	 * @param    WSSCD_Logger           $logger            Logger instance.
	 * @param    object               $container         Container instance.
	 */
	public function __construct( WSSCD_Settings_Manager $settings_manager, WSSCD_Logger $logger, object $container ) {
		parent::__construct( 'general', $settings_manager, $logger );
		$this->container = $container;
	}

	/**
	 * Register settings sections and fields.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function register_sections( string $current_tab ): void {
		// Only register if this is the current tab
		if ( $current_tab !== $this->tab_slug ) {
			return;
		}

		// Trash Management Section - use wp_kses with SVG allowed tags since wp_kses_post strips SVG.
		$this->add_section(
			'wsscd_general_trash',
			wp_kses( WSSCD_Icon_Helper::get( 'delete', array( 'size' => 16 ) ), WSSCD_Icon_Helper::get_allowed_svg_tags() ) . ' ' . esc_html__( 'Trash Management', 'smart-cycle-discounts' ),
			'render_trash_section'
		);

		$this->add_field(
			'trash_auto_purge',
			__( 'Auto-Purge Trash', 'smart-cycle-discounts' ),
			'render_trash_auto_purge_field',
			'wsscd_general_trash',
			array(
				'tooltip' => __( 'Automatically delete campaigns from trash after the retention period expires.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'trash_retention_days',
			__( 'Retention Period', 'smart-cycle-discounts' ),
			'render_trash_retention_field',
			'wsscd_general_trash',
			array(
				'tooltip' => __( 'How long deleted campaigns remain in trash before permanent deletion. Set to "Never" to disable automatic purging.', 'smart-cycle-discounts' ),
				'min'     => 1,
				'max'     => 365,
				'suffix'  => __( 'days', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'trash_status',
			__( 'Current Status', 'smart-cycle-discounts' ),
			'render_trash_status_field',
			'wsscd_general_trash',
			array()
		);
	}

	/**
	 * Render trash management section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_trash_section(): void {
		// Section title is rendered by WordPress Settings API
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure with escaped content.
		echo '<p class="wsscd-section-description">';
		echo esc_html__( 'Configure how trashed campaigns are handled automatically.', 'smart-cycle-discounts' );
		echo '</p>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render trash auto-purge field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_trash_auto_purge_field( array $args ): void {
		$this->render_toggle_field( $args );
	}

	/**
	 * Render trash retention field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_trash_retention_field( array $args ): void {
		$this->render_number_field( $args );
	}

	/**
	 * Render trash status field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_trash_status_field( array $args ): void {
		$trash_count = 0;
		if ( $this->container->has( 'campaign_repository' ) ) {
			$repository  = $this->container->get( 'campaign_repository' );
			$trash_count = $repository->count_trashed();
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure with escaped content.
		echo '<div class="wsscd-trash-status">';
		echo '<span class="wsscd-trash-count">';
		echo '<strong>' . absint( $trash_count ) . '</strong> ';
		echo absint( $trash_count ) === 1
			? esc_html__( 'campaign in trash', 'smart-cycle-discounts' )
			: esc_html__( 'campaigns in trash', 'smart-cycle-discounts' );
		echo '</span>';

		if ( 0 < $trash_count ) {
			// Build the empty trash URL with nonce
			$empty_trash_url = wp_nonce_url(
				admin_url( 'admin.php?page=wsscd-campaigns&action=empty_trash' ),
				'wsscd_empty_trash'
			);

			$confirm_message = esc_js( __( 'Are you sure you want to permanently delete all trashed campaigns? This cannot be undone.', 'smart-cycle-discounts' ) );
			echo '<div class="wsscd-trash-actions">';
			WSSCD_Button_Helper::secondary(
				__( 'Empty Trash Now', 'smart-cycle-discounts' ),
				array(
					'type'       => 'link',
					'href'       => $empty_trash_url,
					'icon'       => 'trash',
					'classes'    => array( 'wsscd-empty-trash-btn' ),
					'attributes' => array( 'onclick' => "return confirm('" . $confirm_message . "');" ),
				)
			);
			echo '</div>';
		} else {
			echo wp_kses_post( ' <span class="wsscd-trash-empty">' );
			WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ) . ' ';
			echo esc_html__( 'Trash is empty', 'smart-cycle-discounts' );
			echo '</span>';
		}
		echo '</div>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Sanitize general settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data.
	 * @return   array              Sanitized data.
	 */
	protected function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Trash auto-purge: accept '1' (raw form) or truthy; missing = off
		$sanitized['trash_auto_purge'] = ! empty( $input['trash_auto_purge'] );

		// Trash retention days
		if ( isset( $input['trash_retention_days'] ) ) {
			$days                              = absint( $input['trash_retention_days'] );
			$sanitized['trash_retention_days'] = max( 1, min( 365, $days ) );
		} else {
			$sanitized['trash_retention_days'] = 30;
		}

		return $sanitized;
	}
}
