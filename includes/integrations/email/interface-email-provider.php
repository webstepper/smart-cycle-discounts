<?php
/**
 * Email Provider Interface
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/interface-email-provider.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Provider Interface
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email
 */
interface WSSCD_Email_Provider {

	/**
	 * Send email.
	 *
	 * @since    1.0.0
	 * @param    string $to         Recipient email address.
	 * @param    string $subject    Email subject.
	 * @param    string $content    Email content (HTML).
	 * @param    array  $headers    Optional. Email headers.
	 * @return   bool                  True on success, false on failure.
	 */
	public function send( string $to, string $subject, string $content, array $headers = array() ): bool;

	/**
	 * Send batch of emails.
	 *
	 * @since    1.0.0
	 * @param    array $emails    Array of email data.
	 * @return   array               Results array with success/failure status.
	 */
	public function send_batch( array $emails ): array;

	/**
	 * Get provider name.
	 *
	 * @since    1.0.0
	 * @return   string    Provider name.
	 */
	public function get_name(): string;

	/**
	 * Check if provider is configured and available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if available.
	 */
	public function is_available(): bool;

	/**
	 * Validate provider configuration.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid configuration.
	 */
	public function validate_config(): bool;

	/**
	 * Get sending statistics (if supported).
	 *
	 * @since    1.0.0
	 * @return   array    Statistics array or empty if not supported.
	 */
	public function get_stats(): array;
}
