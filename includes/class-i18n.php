<?php
/**
 * I18N Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-i18n.php
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
 * Define the internationalization functionality.
 *
 * Provides locale utilities for the plugin. Note that translation loading
 * is handled automatically by WordPress.org for hosted plugins since WP 4.6.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_i18n {

	/**
	 * The text domain of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $domain    The text domain of the plugin.
	 */
	private string $domain;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->domain = 'smart-cycle-discounts';
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * Note: Since WordPress 4.6, plugins hosted on WordPress.org have translations
	 * loaded automatically. This method is now a no-op but retained for backwards
	 * compatibility with any code that may call it.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain(): void {
		// No-op: WordPress.org automatically loads translations for hosted plugins.
		// See: https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
	}

	/**
	 * Get the text domain.
	 *
	 * @since    1.0.0
	 * @return   string    The text domain.
	 */
	public function get_domain(): string {
		return $this->domain;
	}

	/**
	 * Check if the plugin text domain is loaded.
	 *
	 * @since    1.0.0
	 * @return   bool    True if loaded, false otherwise.
	 */
	public function is_textdomain_loaded(): bool {
		return is_textdomain_loaded( $this->domain );
	}

	/**
	 * Get the current locale.
	 *
	 * @since    1.0.0
	 * @return   string    The current locale.
	 */
	public function get_locale(): string {
		return get_locale();
	}

	/**
	 * Check if the current locale is RTL (Right-to-Left).
	 *
	 * @since    1.0.0
	 * @return   bool    True if RTL, false otherwise.
	 */
	public function is_rtl(): bool {
		return is_rtl();
	}

	/**
	 * Get language direction class for CSS.
	 *
	 * @since    1.0.0
	 * @return   string    'rtl' or 'ltr'.
	 */
	public function get_direction_class(): string {
		return $this->is_rtl() ? 'rtl' : 'ltr';
	}

	/**
	 * Get date format for the current locale.
	 *
	 * @since    1.0.0
	 * @return   string    The date format.
	 */
	public function get_date_format(): string {
		return get_option( 'date_format' );
	}

	/**
	 * Get time format for the current locale.
	 *
	 * @since    1.0.0
	 * @return   string    The time format.
	 */
	public function get_time_format(): string {
		return get_option( 'time_format' );
	}

	/**
	 * Get datetime format for the current locale.
	 *
	 * @since    1.0.0
	 * @return   string    The datetime format.
	 */
	public function get_datetime_format(): string {
		return $this->get_date_format() . ' ' . $this->get_time_format();
	}

	/**
	 * Format a date according to the current locale.
	 *
	 * @since    1.0.0
	 * @param    string|int $date      The date to format (timestamp or date string).
	 * @param    string     $format    Optional. Custom format string.
	 * @return   string                   The formatted date.
	 */
	public function format_date( $date, string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = $this->get_date_format();
		}

		if ( is_numeric( $date ) ) {
			return date_i18n( $format, (int) $date );
		}

		return date_i18n( $format, strtotime( $date ) );
	}

	/**
	 * Format a time according to the current locale.
	 *
	 * @since    1.0.0
	 * @param    string|int $time      The time to format (timestamp or time string).
	 * @param    string     $format    Optional. Custom format string.
	 * @return   string                   The formatted time.
	 */
	public function format_time( $time, string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = $this->get_time_format();
		}

		if ( is_numeric( $time ) ) {
			return date_i18n( $format, (int) $time );
		}

		return date_i18n( $format, strtotime( $time ) );
	}

	/**
	 * Format a datetime according to the current locale.
	 *
	 * @since    1.0.0
	 * @param    string|int $datetime    The datetime to format (timestamp or datetime string).
	 * @param    string     $format      Optional. Custom format string.
	 * @return   string                     The formatted datetime.
	 */
	public function format_datetime( $datetime, string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = $this->get_datetime_format();
		}

		if ( is_numeric( $datetime ) ) {
			return date_i18n( $format, (int) $datetime );
		}

		return date_i18n( $format, strtotime( $datetime ) );
	}

	/**
	 * Get number format settings for the current locale.
	 *
	 * @since    1.0.0
	 * @return   array    Array with decimal_separator and thousands_separator.
	 */
	public function get_number_format(): array {
		return array(
			'decimal_separator'   => wc_get_price_decimal_separator(),
			'thousands_separator' => wc_get_price_thousand_separator(),
			'decimals'            => wc_get_price_decimals(),
		);
	}

	/**
	 * Format a number according to the current locale.
	 *
	 * @since    1.0.0
	 * @param    float $number      The number to format.
	 * @param    int   $decimals    Optional. Number of decimal places.
	 * @return   string                 The formatted number.
	 */
	public function format_number( float $number, int $decimals = 2 ): string {
		$format = $this->get_number_format();
		return number_format(
			$number,
			$decimals,
			$format['decimal_separator'],
			$format['thousands_separator']
		);
	}

	/**
	 * Get currency format for the current locale.
	 *
	 * @since    1.0.0
	 * @param    float $amount      The amount to format.
	 * @return   string                 The formatted currency.
	 */
	public function format_currency( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}

		return $this->format_number( $amount, 2 );
	}

	/**
	 * Register JavaScript translations.
	 *
	 * @since    1.0.0
	 * @param    string $handle    The script handle.
	 */
	public function register_script_translations( string $handle ): void {
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				'smart-cycle-discounts',
				WSSCD_LANGUAGES_DIR
			);
		}
	}

	/**
	 * Get translation statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Array with translation statistics.
	 */
	public function get_translation_stats(): array {
		return array(
			'domain'            => $this->domain,
			'locale'            => $this->get_locale(),
			'is_rtl'            => $this->is_rtl(),
			'textdomain_loaded' => $this->is_textdomain_loaded(),
		);
	}
}
