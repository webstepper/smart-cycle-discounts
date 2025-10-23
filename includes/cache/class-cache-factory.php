<?php
/**
 * Cache factory
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Cache Factory
 *
 * Creates cache instances based on configuration.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Cache_Factory {

    /**
     * Create cache instance.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   object             Cache instance.
     */
    public static function create(string $type = 'transient'): object {
        // Return the main cache manager which handles both object cache and transients
        return new SCD_Cache_Manager();
    }

    /**
     * Get available cache types.
     *
     * @since    1.0.0
     * @return   array    Available cache types.
     */
    public static function get_available_types(): array {
        $types = array( 'transient' );
        
        if ( wp_using_ext_object_cache() ) {
            $types[] = 'object';
        }
        
        return $types;
    }

    /**
     * Get recommended cache type.
     *
     * @since    1.0.0
     * @return   string    Recommended cache type.
     */
    public static function get_recommended_type(): string {
        return wp_using_ext_object_cache() ? 'object' : 'transient';
    }
}
