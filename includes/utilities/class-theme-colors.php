<?php
/**
 * Theme Colors Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-theme-colors.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Colors Utility Class
 *
 * Provides WordPress admin color scheme colors for consistent theming.
 *
 * @since 1.0.0
 */
class WSSCD_Theme_Colors {

	/**
	 * Get theme colors based on WordPress admin color scheme.
	 *
	 * @since  1.0.0
	 * @return array Theme colors array with primary, secondary, success, warning, danger, accent.
	 */
	public static function get_theme_colors(): array {
		// WordPress admin color scheme.
		$admin_color_scheme = get_user_meta( get_current_user_id(), 'admin_color', true );

		// Default WordPress colors (fresh theme).
		$default_colors = array(
			'primary'   => '#2271b1',
			'secondary' => '#72aee6',
			'success'   => '#00a32a',
			'warning'   => '#dba617',
			'danger'    => '#d63638',
			'accent'    => '#3858e9',
		);

		// Admin color scheme variations.
		$admin_color_schemes = array(
			'fresh'     => array(
				'primary'   => '#2271b1',
				'secondary' => '#72aee6',
				'accent'    => '#3858e9',
			),
			'light'     => array(
				'primary'   => '#0073aa',
				'secondary' => '#006ba1',
				'accent'    => '#00a0d2',
			),
			'modern'    => array(
				'primary'   => '#1e1e1e',
				'secondary' => '#3c434a',
				'accent'    => '#0073aa',
			),
			'blue'      => array(
				'primary'   => '#096484',
				'secondary' => '#4796b3',
				'accent'    => '#52accc',
			),
			'coffee'    => array(
				'primary'   => '#46403c',
				'secondary' => '#59524c',
				'accent'    => '#c7a589',
			),
			'ectoplasm' => array(
				'primary'   => '#523f6d',
				'secondary' => '#6b5b73',
				'accent'    => '#a3b745',
			),
			'midnight'  => array(
				'primary'   => '#25282b',
				'secondary' => '#363b3f',
				'accent'    => '#e14d43',
			),
			'ocean'     => array(
				'primary'   => '#627c83',
				'secondary' => '#738e96',
				'accent'    => '#9ebaa0',
			),
			'sunrise'   => array(
				'primary'   => '#b43c38',
				'secondary' => '#cf4944',
				'accent'    => '#dd823b',
			),
		);

		// Merge scheme-specific colors with defaults.
		if ( isset( $admin_color_schemes[ $admin_color_scheme ] ) ) {
			return array_merge( $default_colors, $admin_color_schemes[ $admin_color_scheme ] );
		}

		return $default_colors;
	}
}
