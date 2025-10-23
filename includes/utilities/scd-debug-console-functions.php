<?php
/**
 * Debug Helper Functions
 *
 * Global debug functions that integrate with the SCD debug console.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if SCD debug mode is enabled.
 *
 * @since    1.0.0
 * @return   bool    True if debug mode is enabled.
 */
function scd_is_debug_enabled(): bool {
    return defined( 'WP_DEBUG' ) && WP_DEBUG && 
           defined( 'SCD_DEBUG_CONSOLE' ) && SCD_DEBUG_CONSOLE;
}

/**
 * Get debug console instance.
 *
 * @since    1.0.0
 * @return   SCD_Debug_Console|null    Debug console instance or null.
 */
function scd_get_debug_console() {
    if ( ! scd_is_debug_enabled() ) {
        return null;
    }

    static $debug_console = null;
    if ( null === $debug_console ) {
        try {
            $container = SCD_Container::get_instance();
            $debug_console = $container->get( 'debug_console' );
        } catch ( Exception $e ) {
            return null;
        }
    }

    return $debug_console;
}

/**
 * Log debug message.
 *
 * @since    1.0.0
 * @param    string    $message    Debug message.
 * @param    array     $context    Optional context.
 * @return   void
 */
function scd_debug( string $message, array $context = array() ): void {
    $console = scd_get_debug_console();
    if ( $console ) {
        $console->debug( $message, $context );
    }
}

/**
 * Log info message.
 *
 * @since    1.0.0
 * @param    string    $message    Info message.
 * @param    array     $context    Optional context.
 * @return   void
 */
function scd_info( string $message, array $context = array() ): void {
    $console = scd_get_debug_console();
    if ( $console ) {
        $console->info( $message, $context );
    }
}

/**
 * Log warning message.
 *
 * @since    1.0.0
 * @param    string    $message    Warning message.
 * @param    array     $context    Optional context.
 * @return   void
 */
function scd_warning( string $message, array $context = array() ): void {
    $console = scd_get_debug_console();
    if ( $console ) {
        $console->warning( $message, $context );
    }
}

/**
 * Log error message.
 *
 * @since    1.0.0
 * @param    string    $message    Error message.
 * @param    array     $context    Optional context.
 * @return   void
 */
function scd_error( string $message, array $context = array() ): void {
    $console = scd_get_debug_console();
    if ( $console ) {
        $console->error( $message, $context );
    }
}

/**
 * Get debug information about current campaign.
 *
 * @since    1.0.0
 * @param    int    $campaign_id    Optional campaign ID.
 * @return   array                  Campaign debug info.
 */
function scd_get_debug_info( int $campaign_id = 0 ): array {
    if ( ! scd_is_debug_enabled() ) {
        return array( 'error' => 'Debug mode not enabled' );
    }

    try {
        $container = SCD_Container::get_instance();
        $campaign_manager = $container->get( 'campaign_manager' );
        
        if ( $campaign_id > 0 ) {
            $campaign = $campaign_manager->find( $campaign_id );
            return array(
                'campaign' => $campaign ? $campaign->to_array() : null,
                'campaign_id' => $campaign_id,
            );
        }

        $active_campaigns = $campaign_manager->get_active_campaigns();
        return array(
            'active_campaigns_count' => count( $active_campaigns ),
            'active_campaigns' => array_slice( $active_campaigns, 0, 5 ), // First 5 only
            'system_info' => array(
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo( 'version' ),
                'memory_usage' => memory_get_usage( true ),
                'debug_mode' => scd_is_debug_enabled(),
            ),
        );

    } catch ( Exception $e ) {
        return array( 'error' => $e->getMessage() );
    }
}

/**
 * Get campaign by ID for debugging.
 *
 * @since    1.0.0
 * @param    int    $campaign_id    Campaign ID.
 * @return   mixed                  Campaign data or error.
 */
function scd_get_campaign( int $campaign_id ) {
    if ( ! scd_is_debug_enabled() ) {
        return 'Debug mode not enabled';
    }

    try {
        $container = SCD_Container::get_instance();
        $campaign_manager = $container->get( 'campaign_manager' );
        $campaign = $campaign_manager->find( $campaign_id );
        
        return $campaign ? $campaign->to_array() : 'Campaign not found';

    } catch ( Exception $e ) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Test validation for debugging.
 *
 * @since    1.0.0
 * @param    string    $step    Wizard step to validate.
 * @param    array     $data    Data to validate.
 * @return   mixed              Validation result.
 */
function scd_test_validation( string $step, array $data ) {
    if ( ! scd_is_debug_enabled() ) {
        return 'Debug mode not enabled';
    }

    try {
        $context = 'wizard_' . $step;
        $result = SCD_Validation::validate( $data, $context );
        
        $is_error = is_wp_error( $result );
        $response = array(
            'step' => $step,
            'context' => $context,
            'valid' => ! $is_error,
            'input_data' => $data,
            'input_keys' => array_keys( $data ),
        );
        
        if ( $is_error ) {
            $response['errors'] = array();
            foreach ( $result->get_error_codes() as $code ) {
                $response['errors'][] = array(
                    'code' => $code,
                    'message' => $result->get_error_message( $code ),
                    'data' => $result->get_error_data( $code ),
                );
            }
        } else {
            $response['sanitized_data'] = $result;
            $response['changes'] = array();
            
            // Show what changed during sanitization
            foreach ( $data as $key => $original ) {
                if ( isset( $result[$key] ) && $result[$key] !== $original ) {
                    $response['changes'][$key] = array(
                        'original' => $original,
                        'sanitized' => $result[$key],
                    );
                }
            }
        }
        
        return $response;

    } catch ( Exception $e ) {
        return array( 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() );
    }
}

/**
 * Inspect wizard state for debugging.
 *
 * @since    1.0.0
 * @return   mixed    Wizard state data.
 */
function scd_inspect_state() {
    if ( ! scd_is_debug_enabled() ) {
        return 'Debug mode not enabled';
    }

    try {
        $container = SCD_Container::get_instance();
        $wizard_state = $container->get( 'wizard_state_service' );
        
        $user_id = get_current_user_id();
        $state = $wizard_state->get_full_state( $user_id );
        
        return array(
            'user_id' => $user_id,
            'current_step' => $state['current_step'] ?? 'unknown',
            'data_keys' => array_keys( $state['data'] ?? array() ),
            'state_size' => strlen( serialize( $state ) ),
            'last_updated' => $state['last_updated'] ?? 'unknown',
        );

    } catch ( Exception $e ) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Run validation test scenarios.
 *
 * @since    1.0.0
 * @param    string    $step    Step to test (basic, products, discounts, schedule).
 * @return   mixed              Test results.
 */
function scd_run_validation_tests( string $step = 'basic' ) {
    if ( ! scd_is_debug_enabled() ) {
        return 'Debug mode not enabled';
    }

    $test_scenarios = array();
    
    switch ( $step ) {
        case 'basic':
            $test_scenarios = array(
                'valid_basic' => array(
                    'name' => 'Summer Sale Campaign',
                    'description' => 'A great summer discount campaign',
                    'priority' => 5,
                ),
                'empty_name' => array(
                    'name' => '',
                    'description' => 'Missing name test',
                    'priority' => 5,
                ),
                'short_name' => array(
                    'name' => 'Hi',
                    'description' => 'Name too short test',
                    'priority' => 5,
                ),
                'long_name' => array(
                    'name' => str_repeat( 'A', 101 ),
                    'description' => 'Name too long test',
                    'priority' => 5,
                ),
                'invalid_chars' => array(
                    'name' => 'Campaign<script>alert("xss")</script>',
                    'description' => 'XSS attempt test',
                    'priority' => 5,
                ),
                'invalid_priority' => array(
                    'name' => 'Valid Campaign',
                    'description' => 'Priority out of range test',
                    'priority' => 15,
                ),
                'long_description' => array(
                    'name' => 'Valid Campaign',
                    'description' => str_repeat( 'A', 1001 ),
                    'priority' => 5,
                ),
            );
            break;
            
        case 'products':
            $test_scenarios = array(
                'all_products' => array(
                    'product_selection_type' => 'all_products',
                ),
                'specific_products' => array(
                    'product_selection_type' => 'specific_products',
                    'selected_products' => array( 1, 2, 3 ),
                ),
                'product_categories' => array(
                    'product_selection_type' => 'product_categories',
                    'selected_categories' => array( 'clothing', 'electronics' ),
                ),
                'invalid_selection_type' => array(
                    'product_selection_type' => 'invalid_type',
                ),
            );
            break;
            
        case 'discounts':
            $test_scenarios = array(
                'percentage_discount' => array(
                    'discount_type' => 'percentage',
                    'discount_value' => 25,
                ),
                'fixed_discount' => array(
                    'discount_type' => 'fixed',
                    'discount_value' => 10.50,
                ),
                'invalid_percentage' => array(
                    'discount_type' => 'percentage',
                    'discount_value' => 150, // Over 100%
                ),
                'negative_discount' => array(
                    'discount_type' => 'fixed',
                    'discount_value' => -5,
                ),
            );
            break;
            
        case 'schedule':
            $test_scenarios = array(
                'valid_schedule' => array(
                    'start_date' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
                    'end_date' => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
                    'schedule_type' => 'date_range',
                ),
                'invalid_date_range' => array(
                    'start_date' => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
                    'end_date' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ), // End before start
                    'schedule_type' => 'date_range',
                ),
                'past_dates' => array(
                    'start_date' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
                    'end_date' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
                    'schedule_type' => 'date_range',
                ),
            );
            break;
            
        default:
            return array( 'error' => 'Unknown step: ' . $step );
    }
    
    $results = array(
        'step' => $step,
        'total_tests' => count( $test_scenarios ),
        'passed' => 0,
        'failed' => 0,
        'test_results' => array(),
    );
    
    foreach ( $test_scenarios as $test_name => $test_data ) {
        $test_result = scd_test_validation( $step, $test_data );
        
        $results['test_results'][$test_name] = $test_result;
        
        if ( is_array( $test_result ) && isset( $test_result['valid'] ) ) {
            if ( $test_result['valid'] ) {
                $results['passed']++;
            } else {
                $results['failed']++;
            }
        } else {
            $results['failed']++;
        }
    }
    
    $results['success_rate'] = round( ( $results['passed'] / $results['total_tests'] ) * 100, 2 );
    
    return $results;
}

/**
 * Get validation rules and constants.
 *
 * @since    1.0.0
 * @return   mixed    Validation rules data.
 */
function scd_get_validation_rules() {
    if ( ! scd_is_debug_enabled() ) {
        return 'Debug mode not enabled';
    }

    try {
        $rules = SCD_Validation::get_js_data();
        
        return array(
            'constants' => $rules['constants'] ?? array(),
            'messages' => $rules['messages'] ?? array(),
            'available_contexts' => array(
                'basic' => 'Basic campaign information',
                'products' => 'Product selection',
                'discounts' => 'Discount configuration', 
                'schedule' => 'Campaign scheduling',
                'campaign_complete' => 'Complete campaign validation',
            ),
        );

    } catch ( Exception $e ) {
        return array( 'error' => $e->getMessage() );
    }
}