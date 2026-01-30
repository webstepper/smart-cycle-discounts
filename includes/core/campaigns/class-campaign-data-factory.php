<?php
/**
 * Campaign Data Factory
 *
 * Creates appropriate WSSCD_Campaign_Data instances from campaign IDs.
 * Abstracts the lookup logic for major events vs weekly campaigns.
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
 * Campaign Data Factory Class.
 *
 * Factory pattern for creating campaign data adapters. Determines the correct
 * data source (major event registry or weekly definitions) and wraps in
 * appropriate adapter class.
 *
 * @since 1.3.0
 */
class WSSCD_Campaign_Data_Factory {

	/**
	 * Create campaign data instance from ID.
	 *
	 * Automatically determines if major event or weekly campaign based on
	 * the is_major_event hint or by checking both data sources.
	 *
	 * @since  1.3.0
	 * @param  string $campaign_id    Campaign identifier.
	 * @param  bool   $is_major_event Hint: is this a major event?
	 * @return WSSCD_Campaign_Data|null Campaign data or null if not found.
	 */
	public static function create( string $campaign_id, bool $is_major_event = false ): ?WSSCD_Campaign_Data {
		if ( empty( $campaign_id ) ) {
			return null;
		}

		// Try major event first if hinted.
		if ( $is_major_event ) {
			$adapter = self::try_major_event( $campaign_id );
			if ( $adapter ) {
				return $adapter;
			}
		}

		// Try weekly campaign.
		$adapter = self::try_weekly_campaign( $campaign_id );
		if ( $adapter ) {
			return $adapter;
		}

		// Fallback: try major event if not hinted (campaign might be misidentified).
		if ( ! $is_major_event ) {
			$adapter = self::try_major_event( $campaign_id );
			if ( $adapter ) {
				return $adapter;
			}
		}

		return null;
	}

	/**
	 * Try to create adapter from major event registry.
	 *
	 * @since  1.3.0
	 * @param  string $campaign_id Campaign ID.
	 * @return WSSCD_Major_Event_Data|null Adapter or null if not found.
	 */
	private static function try_major_event( string $campaign_id ): ?WSSCD_Major_Event_Data {
		$event = WSSCD_Campaign_Suggestions_Registry::get_event_by_id( $campaign_id );

		if ( $event ) {
			return new WSSCD_Major_Event_Data( $event );
		}

		return null;
	}

	/**
	 * Try to create adapter from weekly campaign definitions.
	 *
	 * @since  1.3.0
	 * @param  string $campaign_id Campaign ID.
	 * @return WSSCD_Weekly_Campaign_Data|null Adapter or null if not found.
	 */
	private static function try_weekly_campaign( string $campaign_id ): ?WSSCD_Weekly_Campaign_Data {
		$weekly = WSSCD_Weekly_Campaign_Definitions::get_by_id( $campaign_id );

		if ( $weekly ) {
			return new WSSCD_Weekly_Campaign_Data( $weekly );
		}

		return null;
	}

	/**
	 * Check if a campaign ID exists in any data source.
	 *
	 * @since  1.3.0
	 * @param  string $campaign_id Campaign ID.
	 * @return bool True if campaign exists.
	 */
	public static function exists( string $campaign_id ): bool {
		return null !== self::create( $campaign_id );
	}
}
