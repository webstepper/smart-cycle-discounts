<?php
/**
 * Advanced Settings Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/tabs/class-advanced-settings.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Settings Tab Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/tabs
 */
class SCD_Advanced_Settings extends SCD_Settings_Page_Base {

	/**
	 * Register settings sections and fields.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function register_sections( string $current_tab ): void {
		if ( $current_tab !== $this->tab_slug ) {
			return;
		}

		// Logging Configuration Section
		$this->add_section(
			'scd_advanced_debug',
			SCD_Icon_Helper::get( 'admin-tools', array( 'size' => 16 ) ) . ' ' . __( 'Logging Configuration', 'smart-cycle-discounts' ),
			'render_debug_section'
		);

		$this->add_field(
			'enable_debug_mode',
			__( 'Debug Mode', 'smart-cycle-discounts' ),
			'render_debug_mode_field',
			'scd_advanced_debug',
			array(
				'tooltip' => __( 'Enable debug mode to log detailed information for troubleshooting. Disable in production for better performance.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'log_level',
			__( 'Logging Level', 'smart-cycle-discounts' ),
			'render_log_level_field',
			'scd_advanced_debug',
			array(
				'tooltip' => __( 'Control the verbosity of log messages. "Warning" is recommended - captures errors and potential issues without excessive logging.', 'smart-cycle-discounts' ),
				'options' => array(
					'none'    => __( 'None - No logging', 'smart-cycle-discounts' ),
					'error'   => __( 'Error - Critical issues only', 'smart-cycle-discounts' ),
					'warning' => __( 'Warning - Errors and warnings (recommended)', 'smart-cycle-discounts' ),
					'info'    => __( 'Info - General information', 'smart-cycle-discounts' ),
					'debug'   => __( 'Debug - Detailed debugging (verbose)', 'smart-cycle-discounts' ),
				),
				'default' => 'warning',
			)
		);

		$this->add_field(
			'log_retention_days',
			__( 'Log Retention', 'smart-cycle-discounts' ),
			'render_log_retention_field',
			'scd_advanced_debug',
			array(
				'tooltip' => __( 'How long to keep log files before automatic cleanup. Set to 0 to keep logs indefinitely.', 'smart-cycle-discounts' ),
				'min'     => 0,
				'max'     => 365,
				'suffix'  => __( 'days', 'smart-cycle-discounts' ),
			)
		);

		// Uninstall Section
		$this->add_section(
			'scd_advanced_uninstall',
			SCD_Icon_Helper::get( 'delete', array( 'size' => 16 ) ) . ' ' . __( 'Uninstall Options', 'smart-cycle-discounts' ),
			'render_uninstall_section'
		);

		$this->add_field(
			'uninstall_data',
			__( 'Remove Data on Uninstall', 'smart-cycle-discounts' ),
			'render_uninstall_data_field',
			'scd_advanced_uninstall',
			array(
				'tooltip' => __( 'Completely remove all plugin data (campaigns, settings) when uninstalling. Cannot be undone.', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Render logging configuration section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_debug_section(): void {
		$tools_url = admin_url( 'admin.php?page=smart-cycle-discounts-tools' );
		echo '<p class="scd-section-description">';
		printf(
			/* translators: %s: URL to Tools & Maintenance page */
			wp_kses(
				__( 'Configure debug mode, logging levels, and log retention policies. To view log files, visit <a href="%s">Tools &amp; Maintenance</a>.', 'smart-cycle-discounts' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $tools_url )
		);
		echo '</p>';
	}

	/**
	 * Render debug mode field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_debug_mode_field( array $args ): void {
		$this->render_toggle_field( $args );

		$enabled = $this->get_setting( 'enable_debug_mode', false );
		if ( $enabled ) {
			echo ' ' . SCD_Badge_Helper::health_badge( 'warning', __( 'Active', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Render log level field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_log_level_field( array $args ): void {
		$this->render_select_field( $args );
	}

	/**
	 * Render log retention field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_log_retention_field( array $args ): void {
		$this->render_number_field( $args );
	}

	/**
	 * Render uninstall section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_uninstall_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'Configure what happens when the plugin is uninstalled.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render uninstall data field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_uninstall_data_field( array $args ): void {
		$this->render_toggle_field( $args );

		$enabled = $this->get_setting( 'uninstall_data', false );
		if ( $enabled ) {
			echo '<p class="description">';
			echo '<strong>' . esc_html__( 'Warning:', 'smart-cycle-discounts' ) . '</strong> ';
			echo esc_html__( 'All campaigns, settings, and data will be permanently deleted when you uninstall the plugin.', 'smart-cycle-discounts' );
			echo '</p>';
		}
	}

	/**
	 * Sanitize advanced settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data.
	 * @return   array              Sanitized data.
	 */
	protected function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Debug settings
		$debug_mode_enabled             = isset( $input['enable_debug_mode'] ) && '1' === $input['enable_debug_mode'];
		$sanitized['enable_debug_mode'] = $debug_mode_enabled;

		// Track when debug mode was enabled for auto-disable after 24 hours
		$old_settings      = $this->settings_manager->get_tab_settings( $this->tab_slug );
		$was_debug_enabled = isset( $old_settings['enable_debug_mode'] ) && $old_settings['enable_debug_mode'];

		if ( $debug_mode_enabled && ! $was_debug_enabled ) {
			// Debug mode just turned on - save timestamp
			$sanitized['debug_mode_enabled_at'] = time();
		} elseif ( ! $debug_mode_enabled ) {
			// Debug mode is off - reset timestamp
			$sanitized['debug_mode_enabled_at'] = 0;
		} else {
			// Debug mode remains on - preserve existing timestamp
			$sanitized['debug_mode_enabled_at'] = isset( $old_settings['debug_mode_enabled_at'] ) ? $old_settings['debug_mode_enabled_at'] : time();
		}

		// Log level - default to 'warning' for optimal support/noise balance
		$valid_levels           = array( 'none', 'error', 'warning', 'info', 'debug' );
		$sanitized['log_level'] = in_array( $input['log_level'] ?? '', $valid_levels, true )
			? $input['log_level']
			: 'warning';

		// Log retention
		$sanitized['log_retention_days'] = isset( $input['log_retention_days'] )
			? max( 0, min( 365, absint( $input['log_retention_days'] ) ) )
			: 7;

		// Uninstall data
		$sanitized['uninstall_data'] = isset( $input['uninstall_data'] ) && '1' === $input['uninstall_data'];

		return $sanitized;
	}
}
