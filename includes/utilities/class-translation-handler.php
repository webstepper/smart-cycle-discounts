<?php
/**
 * Translation Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-translation-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Translation Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Translation_Handler {

	/**
	 * Cached translations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $translations    Cached translations.
	 */
	private static array $translations = array();

	/**
	 * Whether text domain is loaded.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $is_loaded    Whether text domain is loaded.
	 */
	private static bool $is_loaded = false;

	/**
	 * Get translated string.
	 *
	 * @since    1.0.0
	 * @param    string $key         Translation key.
	 * @param    string $default     Default text.
	 * @param    string $context     Optional context.
	 * @return   string                 Translated string.
	 */
	public static function get( string $key, string $default, string $context = '' ): string {
		// If text domain is loaded, return translated string
		if ( self::$is_loaded || did_action( 'init' ) ) {
			self::$is_loaded = true;
			return $context ? _x( $default, $context, 'smart-cycle-discounts' ) : __( $default, 'smart-cycle-discounts' );
		}

		// Return default for early calls
		return $default;
	}

	/**
	 * Get escaped translated string.
	 *
	 * @since    1.0.0
	 * @param    string $key         Translation key.
	 * @param    string $default     Default text.
	 * @param    string $context     Optional context.
	 * @return   string                 Escaped translated string.
	 */
	public static function get_escaped( string $key, string $default, string $context = '' ): string {
		return esc_html( self::get( $key, $default, $context ) );
	}

	/**
	 * Get translated string with sprintf support.
	 *
	 * @since    1.0.0
	 * @param    string $key         Translation key.
	 * @param    string $default     Default text with placeholders.
	 * @param    mixed  ...$args     Arguments for sprintf.
	 * @return   string                 Formatted translated string.
	 */
	public static function get_formatted( string $key, string $default, ...$args ): string {
		$translated = self::get( $key, $default );
		return sprintf( $translated, ...$args );
	}

	/**
	 * Get plugin action links translations.
	 *
	 * @since    1.0.0
	 * @return   array    Translated action links.
	 */
	public static function get_action_links(): array {
		return array(
			'campaigns' => self::get( 'action_link_campaigns', 'Campaigns' ),
			'analytics' => self::get( 'action_link_analytics', 'Analytics' ),
			'settings'  => self::get( 'action_link_settings', 'Settings' ),
		);
	}

	/**
	 * Get plugin meta links translations.
	 *
	 * @since    1.0.0
	 * @return   array    Translated meta links.
	 */
	public static function get_meta_links(): array {
		return array(
			'documentation' => self::get( 'meta_link_documentation', 'Documentation' ),
			'support'       => self::get( 'meta_link_support', 'Support' ),
		);
	}

	/**
	 * Get requirements checker translations.
	 *
	 * @since    1.0.0
	 * @return   array    Translated requirements messages.
	 */
	public static function get_requirements_messages(): array {
		return array(
			'php_version'            => self::get(
				'requirement_php_version',
				'Smart Cycle Discounts requires PHP version %s or higher. You are running version %s.'
			),
			'wp_version'             => self::get(
				'requirement_wp_version',
				'Smart Cycle Discounts requires WordPress version %s or higher. You are running version %s.'
			),
			'woocommerce_missing'    => self::get(
				'requirement_woocommerce_missing',
				'Smart Cycle Discounts requires WooCommerce to be installed and activated.'
			),
			'woocommerce_version'    => self::get(
				'requirement_woocommerce_version',
				'Smart Cycle Discounts requires WooCommerce version %s or higher. You are running version %s.'
			),
			'activation_error'       => self::get(
				'activation_error',
				'Smart Cycle Discounts cannot be activated. Please ensure WooCommerce 8.0+ is installed and you are running PHP 8.1+.'
			),
			'activation_error_title' => self::get(
				'activation_error_title',
				'Plugin Activation Error'
			),
		);
	}

	/**
	 * Mark text domain as loaded.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function mark_loaded(): void {
		self::$is_loaded = true;
	}
}
