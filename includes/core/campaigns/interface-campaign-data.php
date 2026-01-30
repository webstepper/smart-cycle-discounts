<?php
/**
 * Campaign Data Interface
 *
 * Unified interface for campaign data sources (major events, weekly campaigns).
 * Enables polymorphic insights building with single set of column builders.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for campaign data sources.
 *
 * Provides unified access to campaign data regardless of source type.
 * Implementations adapt raw data from registries/definitions to common format.
 *
 * @since 1.3.0
 */
interface WSSCD_Campaign_Data {

	/**
	 * Get campaign identifier.
	 *
	 * @since  1.3.0
	 * @return string Campaign ID (e.g., 'valentines', 'fresh_start_monday').
	 */
	public function get_id(): string;

	/**
	 * Get campaign display name.
	 *
	 * @since  1.3.0
	 * @return string Localized campaign name.
	 */
	public function get_name(): string;

	/**
	 * Get campaign icon identifier.
	 *
	 * @since  1.3.0
	 * @return string Icon name for WSSCD_Icon_Helper.
	 */
	public function get_icon(): string;

	/**
	 * Get campaign description.
	 *
	 * @since  1.3.0
	 * @return string Description text.
	 */
	public function get_description(): string;

	/**
	 * Get suggested discount configuration.
	 *
	 * @since  1.3.0
	 * @return array{min: int, max: int, optimal: int} Discount percentages.
	 */
	public function get_suggested_discount(): array;

	/**
	 * Get opportunity content items for insights column.
	 *
	 * Returns weighted content pool for random selection.
	 *
	 * @since  1.3.0
	 * @return array<array{icon: string, text: string, weight: int}> Content pool items.
	 */
	public function get_opportunity_content(): array;

	/**
	 * Get strategy content items for insights column.
	 *
	 * Returns weighted content pool for random selection.
	 *
	 * @since  1.3.0
	 * @return array<array{icon: string, text: string, weight: int}> Content pool items.
	 */
	public function get_strategy_content(): array;

	/**
	 * Get timeline content items for insights column.
	 *
	 * Returns weighted content pool for random selection.
	 *
	 * @since  1.3.0
	 * @return array<array{icon: string, text: string, weight: int}> Content pool items.
	 */
	public function get_timeline_content(): array;

	/**
	 * Check if this is a major seasonal event.
	 *
	 * @since  1.3.0
	 * @return bool True if major event, false if weekly/recurring.
	 */
	public function is_major_event(): bool;
}
