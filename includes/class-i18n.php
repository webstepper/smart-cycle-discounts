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
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_i18n {

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
		$this->domain = SCD_TEXT_DOMAIN;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			$this->domain,
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
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
	 * Set the text domain.
	 *
	 * @since    1.0.0
	 * @param    string $domain    The text domain.
	 */
	public function set_domain( string $domain ): void {
		$this->domain = $domain;
	}

	/**
	 * Translate a string.
	 *
	 * @since    1.0.0
	 * @param    string $text       The text to translate.
	 * @param    string $context    Optional. Context for the translation.
	 * @return   string                The translated text.
	 */
	public function translate( string $text, string $context = '' ): string {
		if ( ! empty( $context ) ) {
			return _x( $text, $context, $this->domain );
		}
		return __( $text, $this->domain );
	}

	/**
	 * Translate a string with plural forms.
	 *
	 * @since    1.0.0
	 * @param    string $single     The singular form.
	 * @param    string $plural     The plural form.
	 * @param    int    $number     The number to determine singular/plural.
	 * @param    string $context    Optional. Context for the translation.
	 * @return   string                The translated text.
	 */
	public function translate_plural( string $single, string $plural, int $number, string $context = '' ): string {
		if ( ! empty( $context ) ) {
			return _nx( $single, $plural, $number, $context, $this->domain );
		}
		return _n( $single, $plural, $number, $this->domain );
	}

	/**
	 * Escape and translate a string for HTML output.
	 *
	 * @since    1.0.0
	 * @param    string $text       The text to translate and escape.
	 * @param    string $context    Optional. Context for the translation.
	 * @return   string                The escaped and translated text.
	 */
	public function esc_html( string $text, string $context = '' ): string {
		return esc_html( $this->translate( $text, $context ) );
	}

	/**
	 * Escape and translate a string for attribute output.
	 *
	 * @since    1.0.0
	 * @param    string $text       The text to translate and escape.
	 * @param    string $context    Optional. Context for the translation.
	 * @return   string                The escaped and translated text.
	 */
	public function esc_attr( string $text, string $context = '' ): string {
		return esc_attr( $this->translate( $text, $context ) );
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
	 * Get available languages for the plugin.
	 *
	 * @since    1.0.0
	 * @return   array    Array of available language codes.
	 */
	public function get_available_languages(): array {
		$languages    = array();
		$language_dir = SCD_LANGUAGES_DIR;

		if ( is_dir( $language_dir ) ) {
			$files = glob( $language_dir . '*.po' );
			foreach ( $files as $file ) {
				$filename = basename( $file, '.po' );
				if ( strpos( $filename, $this->domain . '-' ) === 0 ) {
					$lang_code = substr( $filename, strlen( $this->domain . '-' ) );
					array_push( $languages, $lang_code );
				}
			}
		}

		return $languages;
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
	 * Format a translatable string with sprintf.
	 *
	 * @since    1.0.0
	 * @param    string $text       The text to translate.
	 * @param    mixed  ...$args    Arguments for sprintf.
	 * @return   string                The formatted and translated text.
	 */
	public function sprintf( string $text, ...$args ): string {
		return sprintf( $this->translate( $text ), ...$args );
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
				$this->domain,
				SCD_LANGUAGES_DIR
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
		$stats = array(
			'domain'              => $this->domain,
			'locale'              => $this->get_locale(),
			'is_rtl'              => $this->is_rtl(),
			'textdomain_loaded'   => $this->is_textdomain_loaded(),
			'available_languages' => $this->get_available_languages(),
			'language_dir'        => SCD_LANGUAGES_DIR,
		);

		return $stats;
	}
}
