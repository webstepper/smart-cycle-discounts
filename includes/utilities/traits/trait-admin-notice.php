<?php
/**
 * Admin Notice Trait
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/traits/trait-admin-notice.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Notice Trait
 *
 * Provides standardized admin notice functionality with:
 * - Multiple notice types (success, error, warning, info)
 * - Proper HTML escaping and security
 * - WCAG 2.2 AA accessibility compliance
 * - Dismissible notices
 * - Bulk notice management
 * - Transient-based notice persistence
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/traits
 * @author     Webstepper <contact@webstepper.io>
 */
trait WSSCD_Admin_Notice_Trait {

	/**
	 * Valid notice types.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $valid_notice_types    Valid notice types.
	 */
	private $valid_notice_types = array( 'success', 'error', 'warning', 'info' );

	/**
	 * Show error notice.
	 *
	 * @since    1.0.0
	 * @param    string  $message       Notice message.
	 * @param    boolean $dismissible   Whether notice is dismissible.
	 * @param    boolean $persistent    Whether to store in transient.
	 * @return   void
	 */
	protected function show_error_notice( $message, $dismissible = true, $persistent = false ) {
		$this->add_admin_notice( $message, 'error', $dismissible, $persistent );
	}

	/**
	 * Store notice in transient for display after redirect.
	 *
	 * @since    1.0.0
	 * @param    string  $message       Notice message.
	 * @param    string  $type          Notice type.
	 * @param    boolean $dismissible   Whether notice is dismissible.
	 * @param    int     $user_id       Optional user ID for user-specific notices.
	 * @return   void
	 */
	protected function store_persistent_notice( $message, $type = 'info', $dismissible = true, $user_id = 0 ) {
		if ( ! in_array( $type, $this->valid_notice_types, true ) ) {
			$type = 'info';
		}

		$user_id       = $user_id ?: get_current_user_id();
		$transient_key = "wsscd_admin_notice_{$user_id}";

		$notices = get_transient( $transient_key );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
			'timestamp'   => time(),
		);

		set_transient( $transient_key, $notices, HOUR_IN_SECONDS );
	}

	/**
	 * Display and clear persistent notices.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    Optional user ID.
	 * @return   void
	 */
	protected function display_persistent_notices( $user_id = 0 ) {
		$user_id       = $user_id ?: get_current_user_id();
		$transient_key = "wsscd_admin_notice_{$user_id}";

		$notices = get_transient( $transient_key );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$this->add_admin_notice(
				$notice['message'],
				$notice['type'],
				$notice['dismissible'],
				false // Don't store again
			);
		}

		delete_transient( $transient_key );
	}

	/**
	 * Add admin notice with proper escaping and WCAG compliance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string  $message       Notice message.
	 * @param    string  $type          Notice type.
	 * @param    boolean $dismissible   Whether notice is dismissible.
	 * @param    boolean $persistent    Whether to store in transient.
	 * @return   void
	 */
	private function add_admin_notice( $message, $type = 'info', $dismissible = true, $persistent = false ) {
		if ( ! in_array( $type, $this->valid_notice_types, true ) ) {
			$type = 'info';
		}

		if ( $persistent ) {
			$this->store_persistent_notice( $message, $type, $dismissible );
			return;
		}

		add_action(
			'admin_notices',
			function () use ( $message, $type, $dismissible ) {
				$this->_render_single_notice( $message, $type, $dismissible );
			}
		);
	}

	/**
	 * Render single admin notice.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @param    string  $message       Notice message.
	 * @param    string  $type          Notice type.
	 * @param    boolean $dismissible   Whether notice is dismissible.
	 * @return   void
	 */
	public function _render_single_notice( $message, $type, $dismissible ) {
		if ( ! $this->can_show_notices() ) {
			return;
		}

		$classes = array( 'notice', 'notice-' . $type );

		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}

		$aria_live = ( 'error' === $type ) ? 'assertive' : 'polite';

				printf(
			'<div class="%s" role="alert" aria-live="%s">
				<p>%s</p>
				%s
			</div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $aria_live ),
			wp_kses( $message, $this->_get_allowed_notice_html() ),
			$dismissible ? wp_kses( $this->_get_dismiss_button(), $this->_get_allowed_notice_html() ) : ''
		);
		
	}

	/**
	 * Get dismiss button HTML.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Dismiss button HTML.
	 */
	private function _get_dismiss_button() {
		return sprintf(
			'<button type="button" class="notice-dismiss">
				<span class="screen-reader-text">%s</span>
			</button>',
			esc_html( 'Dismiss this notice.' )
		);
	}

	/**
	 * Get allowed HTML tags for notices.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Allowed HTML tags and attributes.
	 */
	private function _get_allowed_notice_html() {
		return array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'class'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'button' => array(
				'type'  => array(),
				'class' => array(),
			),
			'strong' => array(),
			'em'     => array(),
			'code'   => array(
				'class' => array(),
			),
			'br'     => array(),
			'p'      => array(
				'class' => array(),
			),
			'span'   => array(
				'class' => array(),
			),
			'ul'     => array(
				'class' => array(),
			),
			'ol'     => array(
				'class' => array(),
			),
			'li'     => array(
				'class' => array(),
			),
		);
	}

	/**
	 * Check if user can see admin notices.
	 *
	 * @since    1.0.0
	 * @return   boolean    True if user can see notices.
	 */
	protected function can_show_notices() {
		return current_user_can( 'manage_options' ) ||
				current_user_can( 'edit_posts' ) ||
				current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Initialize admin notice functionality.
	 * Call this in your class constructor or init method.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	protected function init_admin_notices() {
		// Display persistent notices on admin pages
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, '_display_persistent_notices_hook' ) );
		}
	}

	/**
	 * Hook for displaying persistent notices.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @return   void
	 */
	public function _display_persistent_notices_hook() {
		$this->display_persistent_notices();
	}
}
