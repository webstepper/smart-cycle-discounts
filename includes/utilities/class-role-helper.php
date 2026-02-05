<?php
/**
 * Role Helper Utility
 *
 * Provides helper functions for WordPress user role operations.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for user role operations.
 *
 * @since 1.3.0
 */
class WSSCD_Role_Helper {

	/**
	 * Get all available user roles for dropdown.
	 *
	 * Returns an array of role slugs mapped to translated display names.
	 * Includes all WordPress roles plus any custom roles added by plugins.
	 *
	 * @since  1.3.0
	 * @return array Role slug => Role name pairs.
	 */
	public static function get_available_roles(): array {
		$wp_roles = wp_roles();
		$roles    = array();

		foreach ( $wp_roles->get_names() as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}

		/**
		 * Filter available user roles for campaign targeting.
		 *
		 * Allows plugins to add custom roles or remove roles from the selection.
		 *
		 * @since 1.3.0
		 * @param array $roles Role slug => Role name pairs.
		 */
		return apply_filters( 'wsscd_available_user_roles', $roles );
	}

	/**
	 * Get available roles formatted for JavaScript.
	 *
	 * Returns roles in a format suitable for localization and JavaScript use.
	 *
	 * @since  1.3.0
	 * @return array Array of role objects with value and label.
	 */
	public static function get_roles_for_js(): array {
		$roles  = self::get_available_roles();
		$result = array();

		foreach ( $roles as $slug => $name ) {
			$result[] = array(
				'value' => $slug,
				'label' => $name,
			);
		}

		return $result;
	}

	/**
	 * Format roles array for display.
	 *
	 * Converts an array of role slugs to a human-readable string.
	 *
	 * @since  1.3.0
	 * @param  array $role_slugs Array of role slugs.
	 * @return string Comma-separated role names.
	 */
	public static function format_roles_for_display( array $role_slugs ): string {
		if ( empty( $role_slugs ) ) {
			return '';
		}

		$all_roles = self::get_available_roles();
		$names     = array();

		foreach ( $role_slugs as $slug ) {
			if ( isset( $all_roles[ $slug ] ) ) {
				$names[] = $all_roles[ $slug ];
			}
		}

		return implode( ', ', $names );
	}

	/**
	 * Validate role slugs.
	 *
	 * Checks if all provided role slugs are valid WordPress roles.
	 *
	 * @since  1.3.0
	 * @param  array $role_slugs Array of role slugs to validate.
	 * @return array Array of invalid role slugs (empty if all valid).
	 */
	public static function validate_roles( array $role_slugs ): array {
		if ( empty( $role_slugs ) ) {
			return array();
		}

		$available_roles = array_keys( self::get_available_roles() );
		$invalid_roles   = array_diff( $role_slugs, $available_roles );

		return array_values( $invalid_roles );
	}

	/**
	 * Check if a user has any of the specified roles.
	 *
	 * @since  1.3.0
	 * @param  WP_User|int $user       User object or user ID.
	 * @param  array       $role_slugs Array of role slugs to check.
	 * @return bool True if user has any of the specified roles.
	 */
	public static function user_has_any_role( $user, array $role_slugs ): bool {
		if ( empty( $role_slugs ) ) {
			return false;
		}

		if ( is_int( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! $user instanceof WP_User || 0 === $user->ID ) {
			return false;
		}

		return ! empty( array_intersect( $user->roles, $role_slugs ) );
	}

	/**
	 * Get the current user's roles.
	 *
	 * Returns an empty array for guests (non-logged-in users).
	 *
	 * @since  1.3.0
	 * @return array Array of role slugs for the current user.
	 */
	public static function get_current_user_roles(): array {
		$user = wp_get_current_user();

		if ( 0 === $user->ID ) {
			return array();
		}

		return $user->roles;
	}

	/**
	 * Check if the current user is eligible for a campaign based on role restrictions.
	 *
	 * @since  1.3.0
	 * @param  string $mode  Role mode: 'all', 'include', 'exclude'.
	 * @param  array  $roles Array of role slugs.
	 * @return bool True if the current user is eligible.
	 */
	public static function is_current_user_eligible( string $mode, array $roles ): bool {
		// No restriction - all users eligible.
		if ( 'all' === $mode ) {
			return true;
		}

		// No roles specified - treat as no restriction.
		if ( empty( $roles ) ) {
			return true;
		}

		// Get current user roles (empty array for guests).
		$user_roles = self::get_current_user_roles();

		// Check if user has any of the campaign roles.
		$user_has_role = ! empty( array_intersect( $user_roles, $roles ) );

		// Include mode: user must have one of the specified roles.
		// Exclude mode: user must NOT have any of the specified roles.
		return 'include' === $mode ? $user_has_role : ! $user_has_role;
	}

	/**
	 * Sanitize role slugs array.
	 *
	 * Ensures all role slugs are valid WordPress sanitized keys.
	 *
	 * @since  1.3.0
	 * @param  array $role_slugs Array of role slugs.
	 * @return array Sanitized array of role slugs.
	 */
	public static function sanitize_roles( array $role_slugs ): array {
		return array_map( 'sanitize_key', array_filter( $role_slugs ) );
	}
}
