<?php
/**
 * Template Loader Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-template-loader.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Template Loader
 *
 * Handles template loading for frontend display.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Template_Loader {

	/**
	 * Template directory.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $template_dir    Template directory path.
	 */
	private string $template_dir;

	/**
	 * Initialize the template loader.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->template_dir = SCD_TEMPLATES_DIR;
	}

	/**
	 * Load a template file.
	 *
	 * @since    1.0.0
	 * @param    string $template    Template name.
	 * @param    array  $args        Template arguments.
	 * @param    bool   $echo        Whether to echo or return.
	 * @return   string|null            Template output or null if echoed.
	 */
	public function load_template( string $template, array $args = array(), bool $echo = true ): ?string {
		$template_path = $this->locate_template( $template );

		if ( ! $template_path ) {
			if ( $echo ) {
				echo '<!-- Template not found: ' . esc_html( $template ) . ' -->';
				return null;
			}
			return '<!-- Template not found: ' . esc_html( $template ) . ' -->';
		}

		// Extract args to variables
		if ( ! empty( $args ) ) {
			extract( $args, EXTR_SKIP );
		}

		if ( $echo ) {
			include $template_path;
			return null;
		}

		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Locate a template file.
	 *
	 * @since    1.0.0
	 * @param    string $template    Template name.
	 * @return   string|false           Template path or false if not found.
	 */
	public function locate_template( string $template ): string|false {
		// Sanitize template name to prevent path traversal
		$template = $this->sanitize_template_name( $template );

		if ( empty( $template ) ) {
			return false;
		}

		// Check theme directory first
		$theme_template = locate_template(
			array(
				'smart-cycle-discounts/' . $template,
				'scd/' . $template,
			)
		);

		if ( $theme_template ) {
			return $theme_template;
		}

		// Check plugin templates directory
		$plugin_template = $this->template_dir . $template;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Get template part.
	 *
	 * @since    1.0.0
	 * @param    string $slug    Template slug.
	 * @param    string $name    Template name.
	 * @param    array  $args    Template arguments.
	 * @return   void
	 */
	public function get_template_part( string $slug, string $name = '', array $args = array() ): void {
		$templates = array();

		if ( $name ) {
			$templates[] = "{$slug}-{$name}.php";
		}
		$templates[] = "{$slug}.php";

		foreach ( $templates as $template ) {
			$template_path = $this->locate_template( $template );
			if ( $template_path ) {
				$this->load_template( $template, $args, true );
				return;
			}
		}
	}

	/**
	 * Load frontend template.
	 *
	 * @since    1.0.0
	 * @param    string $template    Template name.
	 * @param    array  $args        Template arguments.
	 * @return   void
	 */
	public function load_frontend_template( string $template, array $args = array() ): void {
		$this->load_template( 'frontend/' . $template, $args, true );
	}

	/**
	 * Load email template.
	 *
	 * @since    1.0.0
	 * @param    string $template    Template name.
	 * @param    array  $args        Template arguments.
	 * @return   string                 Template output.
	 */
	public function load_email_template( string $template, array $args = array() ): string {
		return $this->load_template( 'emails/' . $template, $args, false ) ?: '';
	}

	/**
	 * Check if template exists.
	 *
	 * @since    1.0.0
	 * @param    string $template    Template name.
	 * @return   bool                   True if template exists.
	 */
	public function template_exists( string $template ): bool {
		return $this->locate_template( $template ) !== false;
	}

	/**
	 * Get template directory.
	 *
	 * @since    1.0.0
	 * @return   string    Template directory path.
	 */
	public function get_template_dir(): string {
		return $this->template_dir;
	}

	/**
	 * Get template (alias for load_template with echo=true).
	 *
	 * @since    1.0.0
	 * @param    string $template    Template name.
	 * @param    array  $args        Template arguments.
	 * @return   void
	 */
	public function get_template( string $template, array $args = array() ): void {
		$this->load_template( $template, $args, true );
	}

	/**
	 * Sanitize template name to prevent path traversal.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $template    Template name to sanitize.
	 * @return   string                 Sanitized template name.
	 */
	private function sanitize_template_name( string $template ): string {
		// Remove any path traversal attempts
		$template = str_replace( array( '..', './', '..\\', '.\\' ), '', $template );

		// Remove absolute paths
		$template = ltrim( $template, '/' );
		$template = ltrim( $template, '\\' );

		// Remove any protocol handlers
		$template = preg_replace( '#^[a-z]+://#i', '', $template );

		// Only allow alphanumeric, hyphens, underscores, forward slashes, and dots
		$template = preg_replace( '/[^a-zA-Z0-9\/_\-\.]/', '', $template );

		// Ensure it has a .php extension if no extension provided
		if ( ! preg_match( '/\.(php|html)$/i', $template ) ) {
			$template .= '.php';
		}

		return $template;
	}
}
