<?php
/**
 * Blocks Integration Verification Script
 *
 * Run this via WP-CLI or Code Snippets to verify the blocks integration is properly set up.
 *
 * Usage (WP-CLI):
 * wp eval-file verify-blocks-integration.php
 *
 * @package WSSCD_Plugin
 * @since   1.5.70
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Allow running via WP-CLI.
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		die( 'Direct access not permitted.' );
	}
}

/**
 * Verification Results
 */
$results = array(
	'passed' => array(),
	'failed' => array(),
	'warnings' => array(),
);

/**
 * Helper function to add result
 */
function add_result( $type, $message ) {
	global $results;
	$results[ $type ][] = $message;
}

echo "=== WooCommerce Blocks Integration Verification ===\n\n";

// Test 1: Check if PHP class exists.
echo "[1/15] Checking if WSSCD_WC_Blocks_Integration class exists...\n";
if ( class_exists( 'WSSCD_WC_Blocks_Integration' ) ) {
	add_result( 'passed', 'WSSCD_WC_Blocks_Integration class is loaded' );
	echo "✅ PASS\n\n";
} else {
	add_result( 'failed', 'WSSCD_WC_Blocks_Integration class not found - check autoloader' );
	echo "❌ FAIL\n\n";
}

// Test 2: Check if IntegrationInterface exists.
echo "[2/15] Checking if WooCommerce Blocks IntegrationInterface exists...\n";
if ( interface_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
	add_result( 'passed', 'WooCommerce Blocks IntegrationInterface available' );
	echo "✅ PASS\n\n";
} else {
	add_result( 'warnings', 'WooCommerce Blocks IntegrationInterface not found (WC Blocks may not be active)' );
	echo "⚠️ WARNING\n\n";
}

// Test 3: Check if JavaScript file exists.
echo "[3/15] Checking if JavaScript file exists...\n";
$js_file = WSSCD_PLUGIN_DIR . '/resources/assets/js/frontend/wsscd-blocks-checkout.js';
if ( file_exists( $js_file ) ) {
	add_result( 'passed', 'JavaScript file exists: ' . $js_file );
	echo "✅ PASS (" . filesize( $js_file ) . " bytes)\n\n";
} else {
	add_result( 'failed', 'JavaScript file not found: ' . $js_file );
	echo "❌ FAIL\n\n";
}

// Test 4: Check if CSS file exists.
echo "[4/15] Checking if CSS file exists...\n";
$css_file = WSSCD_PLUGIN_DIR . '/resources/assets/css/frontend/wsscd-blocks-checkout.css';
if ( file_exists( $css_file ) ) {
	add_result( 'passed', 'CSS file exists: ' . $css_file );
	echo "✅ PASS (" . filesize( $css_file ) . " bytes)\n\n";
} else {
	add_result( 'failed', 'CSS file not found: ' . $css_file );
	echo "❌ FAIL\n\n";
}

// Test 5: Check JavaScript syntax.
echo "[5/15] Checking JavaScript syntax...\n";
if ( file_exists( $js_file ) ) {
	$js_content = file_get_contents( $js_file );
	if ( false !== strpos( $js_content, 'registerCheckoutFilters' ) ) {
		add_result( 'passed', 'JavaScript contains registerCheckoutFilters call' );
		echo "✅ PASS\n\n";
	} else {
		add_result( 'failed', 'JavaScript missing registerCheckoutFilters call' );
		echo "❌ FAIL\n\n";
	}
} else {
	echo "⏭️ SKIP (file not found)\n\n";
}

// Test 6: Check if Store API extension function exists.
echo "[6/15] Checking if Store API extension function exists...\n";
if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
	add_result( 'passed', 'Store API extension function available' );
	echo "✅ PASS\n\n";
} else {
	add_result( 'warnings', 'Store API extension function not available (WooCommerce may be outdated)' );
	echo "⚠️ WARNING\n\n";
}

// Test 7: Check CartItemSchema class.
echo "[7/15] Checking if CartItemSchema class exists...\n";
if ( class_exists( 'Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema' ) ) {
	add_result( 'passed', 'CartItemSchema class available' );
	echo "✅ PASS\n\n";
} else {
	add_result( 'warnings', 'CartItemSchema class not found (WooCommerce version may be too old)' );
	echo "⚠️ WARNING\n\n";
}

// Test 8: Check WooCommerce integration coordinator.
echo "[8/15] Checking if WooCommerce integration has blocks component...\n";
if ( function_exists( 'wsscd_get_container' ) ) {
	$container = wsscd_get_container();
	if ( $container && $container->has( 'woocommerce_integration' ) ) {
		$wc_integration = $container->get( 'woocommerce_integration' );

		try {
			$reflection = new ReflectionClass( $wc_integration );
			$property = $reflection->getProperty( 'blocks_integration' );
			$property->setAccessible( true );
			$blocks_integration = $property->getValue( $wc_integration );

			if ( $blocks_integration instanceof WSSCD_WC_Blocks_Integration ) {
				add_result( 'passed', 'Blocks integration component initialized in WooCommerce integration' );
				echo "✅ PASS\n\n";
			} elseif ( null === $blocks_integration ) {
				add_result( 'warnings', 'Blocks integration not initialized (WC Blocks may not be active)' );
				echo "⚠️ WARNING\n\n";
			} else {
				add_result( 'failed', 'Blocks integration has wrong type' );
				echo "❌ FAIL\n\n";
			}
		} catch ( Exception $e ) {
			add_result( 'failed', 'Error accessing blocks_integration property: ' . $e->getMessage() );
			echo "❌ FAIL\n\n";
		}
	} else {
		add_result( 'failed', 'WooCommerce integration not found in container' );
		echo "❌ FAIL\n\n";
	}
} else {
	add_result( 'failed', 'Container function not available' );
	echo "❌ FAIL\n\n";
}

// Test 9: Check FeaturesUtil for compatibility declaration.
echo "[9/15] Checking if FeaturesUtil is available...\n";
if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
	add_result( 'passed', 'FeaturesUtil class available for compatibility declaration' );
	echo "✅ PASS\n\n";
} else {
	add_result( 'warnings', 'FeaturesUtil not available (WooCommerce version may be too old)' );
	echo "⚠️ WARNING\n\n";
}

// Test 10: Check file permissions.
echo "[10/15] Checking file permissions...\n";
$files_to_check = array( $js_file, $css_file );
$perms_ok = true;
foreach ( $files_to_check as $file ) {
	if ( file_exists( $file ) && ! is_readable( $file ) ) {
		add_result( 'failed', 'File not readable: ' . $file );
		$perms_ok = false;
	}
}
if ( $perms_ok ) {
	add_result( 'passed', 'All files have correct permissions' );
	echo "✅ PASS\n\n";
} else {
	echo "❌ FAIL\n\n";
}

// Test 11: Check if autoloader has the class registered.
echo "[11/15] Checking autoloader registration...\n";
$autoloader_file = WSSCD_PLUGIN_DIR . '/includes/class-autoloader.php';
if ( file_exists( $autoloader_file ) ) {
	$autoloader_content = file_get_contents( $autoloader_file );
	if ( false !== strpos( $autoloader_content, 'WSSCD_WC_Blocks_Integration' ) ) {
		add_result( 'passed', 'Class registered in autoloader' );
		echo "✅ PASS\n\n";
	} else {
		add_result( 'failed', 'Class not registered in autoloader' );
		echo "❌ FAIL\n\n";
	}
} else {
	add_result( 'failed', 'Autoloader file not found' );
	echo "❌ FAIL\n\n";
}

// Test 12: Check method existence.
echo "[12/15] Checking if blocks integration has required methods...\n";
if ( class_exists( 'WSSCD_WC_Blocks_Integration' ) ) {
	$required_methods = array(
		'get_name',
		'initialize',
		'get_script_handles',
		'get_editor_script_handles',
		'get_script_data',
		'register_hooks',
		'extend_cart_item_data',
		'extend_cart_item_schema',
	);

	$missing_methods = array();
	foreach ( $required_methods as $method ) {
		if ( ! method_exists( 'WSSCD_WC_Blocks_Integration', $method ) ) {
			$missing_methods[] = $method;
		}
	}

	if ( empty( $missing_methods ) ) {
		add_result( 'passed', 'All required methods exist' );
		echo "✅ PASS\n\n";
	} else {
		add_result( 'failed', 'Missing methods: ' . implode( ', ', $missing_methods ) );
		echo "❌ FAIL\n\n";
	}
} else {
	echo "⏭️ SKIP (class not found)\n\n";
}

// Test 13: Check JavaScript has required functions.
echo "[13/15] Checking JavaScript has required functions...\n";
if ( file_exists( $js_file ) ) {
	$js_content = file_get_contents( $js_file );
	$required_js = array(
		'formatPrice',
		'cartItemPriceFilter',
		'cartItemClassFilter',
		'registerCheckoutFilters',
	);

	$missing_js = array();
	foreach ( $required_js as $func ) {
		if ( false === strpos( $js_content, $func ) ) {
			$missing_js[] = $func;
		}
	}

	if ( empty( $missing_js ) ) {
		add_result( 'passed', 'All required JavaScript functions present' );
		echo "✅ PASS\n\n";
	} else {
		add_result( 'failed', 'Missing JavaScript functions: ' . implode( ', ', $missing_js ) );
		echo "❌ FAIL\n\n";
	}
} else {
	echo "⏭️ SKIP (file not found)\n\n";
}

// Test 14: Check CSS has required classes.
echo "[14/15] Checking CSS has required classes...\n";
if ( file_exists( $css_file ) ) {
	$css_content = file_get_contents( $css_file );
	$required_css = array(
		'.wsscd-discounted-item del',
		'.wsscd-discounted-item ins',
	);

	$missing_css = array();
	foreach ( $required_css as $selector ) {
		if ( false === strpos( $css_content, $selector ) ) {
			$missing_css[] = $selector;
		}
	}

	if ( empty( $missing_css ) ) {
		add_result( 'passed', 'All required CSS selectors present' );
		echo "✅ PASS\n\n";
	} else {
		add_result( 'failed', 'Missing CSS selectors: ' . implode( ', ', $missing_css ) );
		echo "❌ FAIL\n\n";
	}
} else {
	echo "⏭️ SKIP (file not found)\n\n";
}

// Test 15: Check WordPress/WooCommerce versions.
echo "[15/15] Checking WordPress/WooCommerce versions...\n";
global $wp_version;
$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'not installed';

echo "WordPress: $wp_version\n";
echo "WooCommerce: $wc_version\n";

if ( version_compare( $wc_version, '8.3.0', '>=' ) ) {
	add_result( 'passed', 'WooCommerce version supports blocks (' . $wc_version . ')' );
	echo "✅ PASS\n\n";
} elseif ( 'not installed' === $wc_version ) {
	add_result( 'failed', 'WooCommerce not installed' );
	echo "❌ FAIL\n\n";
} else {
	add_result( 'warnings', 'WooCommerce version may not fully support blocks (' . $wc_version . ')' );
	echo "⚠️ WARNING\n\n";
}

// Summary.
echo "=== SUMMARY ===\n\n";

echo "✅ Passed: " . count( $results['passed'] ) . "\n";
foreach ( $results['passed'] as $msg ) {
	echo "  • $msg\n";
}
echo "\n";

if ( ! empty( $results['warnings'] ) ) {
	echo "⚠️ Warnings: " . count( $results['warnings'] ) . "\n";
	foreach ( $results['warnings'] as $msg ) {
		echo "  • $msg\n";
	}
	echo "\n";
}

if ( ! empty( $results['failed'] ) ) {
	echo "❌ Failed: " . count( $results['failed'] ) . "\n";
	foreach ( $results['failed'] as $msg ) {
		echo "  • $msg\n";
	}
	echo "\n";
}

// Final verdict.
if ( empty( $results['failed'] ) && count( $results['passed'] ) > 10 ) {
	echo "🎉 INTEGRATION READY! All critical tests passed.\n";
	echo "Note: Some warnings are expected if WooCommerce Blocks is not active yet.\n";
} elseif ( ! empty( $results['failed'] ) ) {
	echo "❌ INTEGRATION INCOMPLETE. Please fix the failed tests above.\n";
} else {
	echo "⚠️ INTEGRATION PARTIAL. Review warnings and ensure WooCommerce Blocks is installed.\n";
}

echo "\n";
